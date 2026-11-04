<?php
/**
 * Private messaging about an item.
 *
 * Access rules:
 *  - A conversation is always between the item's poster and one other user.
 *  - Anyone logged in may message the poster of a visible item.
 *  - The poster may only start a conversation with someone who claimed
 *    the item (or reply to someone who already wrote to them).
 */

declare(strict_types=1);

final class MessageController extends Controller
{
    /** GET /messages - inbox */
    public function index(): void
    {
        $this->view('messages/index', [
            'title'         => 'Messages',
            'conversations' => Message::conversationsFor(Auth::id()),
        ]);
    }

    /** GET /messages/{item}/{user} - one conversation */
    public function show(string $itemId, string $userId): void
    {
        [$item, $otherUser] = $this->loadConversation($itemId, $userId);

        // Opening the conversation marks the other person's messages as read.
        Message::markRead((int) $item['id'], (int) $otherUser['id'], Auth::id());

        $this->renderThread($item, $otherUser, [], '');
    }

    /** POST /messages/{item}/{user} - send a message */
    public function send(string $itemId, string $userId): void
    {
        [$item, $otherUser] = $this->loadConversation($itemId, $userId);

        // Keep line breaks, but remove spaces/new lines at the start and end.
        $body = trim($this->rawInput('body'));
        $validator = Validator::make(['body' => $body], ['body' => ['required', 'max:' . Message::MAX_LENGTH]], ['body' => 'Message']);

        if ($validator->fails()) {
            $this->renderThread($item, $otherUser, $validator->errors(), $body, 422);
            return;
        }

        Message::send((int) $item['id'], Auth::id(), (int) $otherUser['id'], $body);
        redirect('/messages/' . $item['id'] . '/' . $otherUser['id'] . '#latest');
    }

    // ------------------------------------------------------------------

    private function renderThread(array $item, array $otherUser, array $errors, string $draft, int $status = 200): void
    {
        $photos = ItemImage::forItem((int) $item['id']);
        $this->view('messages/show', [
            'title'         => 'Chat with ' . $otherUser['name'],
            'item'          => $item,
            'itemPhoto'     => $photos[0]['file_name'] ?? null,
            'otherUser'     => $otherUser,
            'messages'      => Message::thread((int) $item['id'], Auth::id(), (int) $otherUser['id']),
            'conversations' => Message::conversationsFor(Auth::id()),
            'errors'        => $errors,
            'draft'         => $draft,
        ], $status);
    }

    /**
     * Load the item and the other user, and check that the current user
     * is allowed to see this conversation. Stops with 403/404 otherwise.
     */
    private function loadConversation(string $itemId, string $userId): array
    {
        $item      = ctype_digit($itemId) ? Item::find((int) $itemId) : null;
        $otherUser = ctype_digit($userId) ? User::findById((int) $userId) : null;
        $myId      = Auth::id();

        if ($item === null || $otherUser === null || (int) $otherUser['id'] === $myId) {
            abort(404);
        }

        $ownerId = (int) $item['user_id'];
        $iAmOwner = $myId === $ownerId;

        // Hidden items can't be discussed by other users.
        if (!$item['is_approved'] && !$iAmOwner) {
            abort(404);
        }

        // One side of every conversation must be the item's poster.
        if (!$iAmOwner && (int) $otherUser['id'] !== $ownerId) {
            abort(403, 'You can only view your own conversations.');
        }

        // The poster can only contact people who claimed the item or already wrote to them.
        if ($iAmOwner) {
            $hasClaim = Claim::byUserForItem((int) $otherUser['id'], (int) $item['id']) !== null;
            if (!$hasClaim && !Message::conversationExists((int) $item['id'], $myId, (int) $otherUser['id'])) {
                abort(403, 'You can only message people who contacted you or claimed this item.');
            }
        }

        return [$item, $otherUser];
    }
}
