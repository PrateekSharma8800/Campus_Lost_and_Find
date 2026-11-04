<?php
/**
 * Items: browse, view, post, edit, delete, change status, my items, photos.
 */

declare(strict_types=1);

final class ItemController extends Controller
{
    // ------------------------------------------------------------------
    // Browse & search (public)
    // ------------------------------------------------------------------

    public function index(): void
    {
        // Read filters from the URL and keep only valid values.
        $filters = [
            'q'        => mb_substr($this->query('q'), 0, 100),
            'type'     => in_array($this->query('type'), ['lost', 'found'], true) ? $this->query('type') : '',
            'category' => ctype_digit($this->query('category')) ? $this->query('category') : '',
            'location' => ctype_digit($this->query('location')) ? $this->query('location') : '',
            'status'   => in_array($this->query('status'), [...Item::STATUSES, 'all'], true) ? $this->query('status') : '',
            'sort'     => $this->query('sort') === 'oldest' ? 'oldest' : '',
            'page'     => max(1, (int) $this->query('page')),
        ];

        $result = Item::search($filters);

        $this->view('items/index', [
            'title'      => 'Browse items',
            'filters'    => $filters,
            'items'      => $result['items'],
            'total'      => $result['total'],
            'totalPages' => max(1, (int) ceil($result['total'] / Item::PER_PAGE)),
            'categories' => Category::all(),
            'locations'  => Location::all(),
        ]);
    }

    // ------------------------------------------------------------------
    // Item detail page (public)
    // ------------------------------------------------------------------

    public function show(string $id): void
    {
        $item = $this->findItemOr404($id);
        $isOwner = $this->canManage($item);

        // Hidden (unapproved) items are only visible to their owner and admins.
        if (!$item['is_approved'] && !$isOwner) {
            abort(404);
        }

        $myClaim = null;
        if (Auth::check() && !$isOwner) {
            $myClaim = Claim::byUserForItem(Auth::id(), (int) $item['id']);
        }

        $this->view('items/show', [
            'title'    => $item['title'],
            'item'     => $item,
            'images'   => ItemImage::forItem((int) $item['id']),
            'isOwner'  => $isOwner,
            'claims'   => $isOwner ? Claim::forItem((int) $item['id']) : [],
            'myClaim'  => $myClaim,
            'canClaim' => Auth::check() && !$isOwner && $myClaim === null
                          && $item['type'] === 'found' && $item['status'] === 'open',
            'errors'   => [],
            'old'      => [],
        ]);
    }

    // ------------------------------------------------------------------
    // Post a new item
    // ------------------------------------------------------------------

    public function create(): void
    {
        $type = $this->query('type') === 'found' ? 'found' : 'lost';
        $this->renderForm('create', ['type' => $type, 'date_occurred' => gmdate('Y-m-d')], []);
    }

    public function store(): void
    {
        $data   = $this->readItemForm();
        $errors = $this->validateItem($data);
        $photos = ImageUpload::filesFromRequest('photos');

        $photoError = $this->checkPhotos($photos, 0);
        if ($photoError !== null) {
            $errors['photos'] = $photoError;
        }
        if ($errors) {
            $this->renderForm('create', $data, $errors, 422);
            return;
        }

        $itemId = $this->saveWithPhotos(function () use ($data) {
            return Item::create(Auth::id(), $data);
        }, $photos, 0);

        Session::flash('success', 'Your ' . $data['type'] . ' item has been posted.');
        redirect('/items/' . $itemId);
    }

    // ------------------------------------------------------------------
    // Edit an item (owner or admin)
    // ------------------------------------------------------------------

    public function edit(string $id): void
    {
        $item = $this->findManageableItem($id);
        $this->renderForm('edit', $item, [], 200, $item);
    }

    public function update(string $id): void
    {
        $item = $this->findManageableItem($id);
        $itemId = (int) $item['id'];

        $data = $this->readItemForm();
        $data['type'] = $item['type'];   // the type can't be changed after posting
        $errors = $this->validateItem($data);

        // Photos the user ticked for removal (only ids that belong to this item count).
        $existingIds = array_map(fn ($image) => (int) $image['id'], ItemImage::forItem($itemId));
        $removeIds = array_values(array_intersect(array_map('intval', (array) ($_POST['remove_images'] ?? [])), $existingIds));

        $photos = ImageUpload::filesFromRequest('photos');
        $photoError = $this->checkPhotos($photos, count($existingIds) - count($removeIds));
        if ($photoError !== null) {
            $errors['photos'] = $photoError;
        }
        if ($errors) {
            $this->renderForm('edit', $data + ['id' => $itemId], $errors, 422, $item);
            return;
        }

        foreach ($removeIds as $imageId) {
            ItemImage::delete($itemId, $imageId);
        }
        $this->saveWithPhotos(function () use ($itemId, $data) {
            Item::update($itemId, $data);
            return $itemId;
        }, $photos, count($existingIds) - count($removeIds));

        Session::flash('success', 'Your changes have been saved.');
        redirect('/items/' . $itemId);
    }

    // ------------------------------------------------------------------
    // Status, delete, my items
    // ------------------------------------------------------------------

    public function updateStatus(string $id): void
    {
        $item = $this->findManageableItem($id);
        $status = $this->input('status');

        if (!in_array($status, Item::STATUSES, true)) {
            abort(400);
        }
        Item::setStatus((int) $item['id'], $status);

        $messages = [
            'returned' => 'Great news! The item is marked as returned.',
            'closed'   => 'The post has been closed.',
            'open'     => 'The post is open again.',
            'claimed'  => 'The item is marked as claimed.',
        ];
        Session::flash('success', $messages[$status]);
        redirect('/items/' . $item['id']);
    }

    public function destroy(string $id): void
    {
        $item = $this->findManageableItem($id);
        Item::delete((int) $item['id']);
        Session::flash('success', 'The post "' . $item['title'] . '" was deleted.');
        redirect('/my-items');
    }

    public function myItems(): void
    {
        $this->view('items/mine', ['title' => 'My items', 'items' => Item::forUser(Auth::id())]);
    }

    // ------------------------------------------------------------------
    // Serve an uploaded photo
    // ------------------------------------------------------------------

    public function photo(string $file): void
    {
        // Only names we generated: 32 hex characters + allowed extension.
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $file)) {
            abort(404);
        }
        $image = ItemImage::findByFileName($file);
        $path = UPLOAD_PATH . '/' . $file;
        if ($image === null || !is_file($path)) {
            abort(404);
        }
        // Photos of hidden items are private to the owner and admins.
        if (!$image['is_approved'] && Auth::id() !== (int) $image['user_id'] && !Auth::isAdmin()) {
            abort(404);
        }

        header('Content-Type: ' . $image['mime_type']);   // type from our database, never guessed
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        header('Content-Disposition: inline; filename="' . $file . '"');
        readfile($path);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function readItemForm(): array
    {
        $type = $this->input('type') === 'found' ? 'found' : 'lost';
        return [
            'type'            => $type,
            'title'           => $this->input('title'),
            'description'     => $this->input('description'),
            'category_id'     => $this->input('category_id'),
            'location_id'     => $this->input('location_id'),
            'location_detail' => $this->input('location_detail'),
            'date_occurred'   => $this->input('date_occurred'),
            // Only finders set a secret detail (used to check claims).
            'secret_detail'   => $type === 'found' ? $this->input('secret_detail') : '',
        ];
    }

    private function validateItem(array $data): array
    {
        $validator = Validator::make($data, [
            'title'           => ['required', 'min:3', 'max:150'],
            'description'     => ['required', 'min:10', 'max:2000'],
            'category_id'     => ['required', 'integer'],
            'location_id'     => ['required', 'integer'],
            'location_detail' => ['nullable', 'max:255'],
            'date_occurred'   => ['required', 'past_date'],
            'secret_detail'   => ['nullable', 'max:255'],
        ], [
            'category_id'   => 'category',
            'location_id'   => 'location',
            'date_occurred' => 'Date',
            'secret_detail' => 'Secret detail',
        ]);
        $errors = $validator->errors();

        // The chosen category/location must really exist.
        if (!isset($errors['category_id']) && !Category::exists((int) $data['category_id'])) {
            $errors['category_id'] = 'Please choose a valid category.';
        }
        if (!isset($errors['location_id']) && !Location::exists((int) $data['location_id'])) {
            $errors['location_id'] = 'Please choose a valid location.';
        }
        return $errors;
    }

    /** First problem with the uploaded photos, or null. */
    private function checkPhotos(array $photos, int $alreadyAttached): ?string
    {
        if ($alreadyAttached + count($photos) > ImageUpload::MAX_FILES) {
            return 'You can attach at most ' . ImageUpload::MAX_FILES . ' photos per item.';
        }
        foreach ($photos as $photo) {
            $error = ImageUpload::validate($photo);
            if ($error !== null) {
                return $error;
            }
        }
        return null;
    }

    /**
     * Run $saveItem (create or update) and store the photos in ONE transaction.
     * If anything fails, the database is rolled back and saved files are removed.
     */
    private function saveWithPhotos(callable $saveItem, array $photos, int $alreadyAttached): int
    {
        $pdo = db();
        $savedFiles = [];
        $pdo->beginTransaction();
        try {
            $itemId = $saveItem();
            foreach ($photos as $index => $photo) {
                $saved = ImageUpload::save($photo);
                $savedFiles[] = $saved['file_name'];
                ItemImage::add($itemId, $saved, $alreadyAttached === 0 && $index === 0);
            }
            $pdo->commit();
            return $itemId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            foreach ($savedFiles as $fileName) {
                ImageUpload::deleteFile($fileName);
            }
            throw $e;
        }
    }

    private function renderForm(string $mode, array $old, array $errors, int $status = 200, ?array $item = null): void
    {
        $this->view('items/form', [
            'title'      => $mode === 'create' ? 'Report a ' . $old['type'] . ' item' : 'Edit item',
            'mode'       => $mode,
            'old'        => $old,
            'errors'     => $errors,
            'item'       => $item,
            'images'     => $item ? ItemImage::forItem((int) $item['id']) : [],
            'categories' => Category::all(),
            'locations'  => Location::all(),
        ], $status);
    }

    private function findItemOr404(string $id): array
    {
        $item = ctype_digit($id) ? Item::find((int) $id) : null;
        if ($item === null) {
            abort(404);
        }
        return $item;
    }

    /** The poster and admins may manage an item. */
    private function canManage(array $item): bool
    {
        return Auth::check() && (Auth::id() === (int) $item['user_id'] || Auth::isAdmin());
    }

    private function findManageableItem(string $id): array
    {
        $item = $this->findItemOr404($id);
        if (!$this->canManage($item)) {
            abort(403, 'Only the person who posted this item can change it.');
        }
        return $item;
    }
}
