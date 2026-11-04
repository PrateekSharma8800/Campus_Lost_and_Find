<?php
/** Post / edit item form. Vars: $mode (create|edit), $old, $errors, $item, $images, $categories, $locations */
$isEdit  = $mode === 'edit';
$type    = $old['type'] ?? 'lost';
$action  = $isEdit ? '/items/' . (int) $item['id'] . '/edit' : '/items';
$photoSlotsLeft = ImageUpload::MAX_FILES - count($images);
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">
        <div class="page-header">
            <a href="<?= $isEdit ? '/items/' . (int) $item['id'] : '/items' ?>" class="small text-muted"><i class="bi bi-arrow-left me-1"></i>Back</a>
            <h1 class="mt-2"><?= $isEdit ? 'Edit item' : 'Report an item' ?></h1>
            <p><?= $isEdit ? 'Update the details of your post.' : 'Give as many details as you can. It helps people recognise the item.' ?></p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger d-flex gap-2" role="alert">
                <i class="bi bi-exclamation-octagon flex-shrink-0 mt-1"></i>
                <div>Please fix the highlighted fields below.</div>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate data-once>
            <?= csrf_field() ?>

            <div class="cf-card p-3 p-md-4 mb-3">
                <div class="form-section-title">1. What happened?</div>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="type" value="<?= e($type) ?>">
                    <p class="mb-0">This is a <?= type_badge($type) ?> item post. The type can't be changed.</p>
                <?php else: ?>
                    <div class="type-choice">
                        <div>
                            <input type="radio" name="type" id="type_lost" value="lost"<?= $type === 'lost' ? ' checked' : '' ?> data-type-toggle>
                            <label for="type_lost">
                                <span class="icon-bubble rose"><i class="bi bi-exclamation-circle"></i></span>
                                <span><strong class="d-block">I lost something</strong><span class="small text-muted">Ask the campus for help</span></span>
                            </label>
                        </div>
                        <div>
                            <input type="radio" name="type" id="type_found" value="found"<?= $type === 'found' ? ' checked' : '' ?> data-type-toggle>
                            <label for="type_found">
                                <span class="icon-bubble green"><i class="bi bi-check-circle"></i></span>
                                <span><strong class="d-block">I found something</strong><span class="small text-muted">Help it get home</span></span>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="cf-card p-3 p-md-4 mb-3">
                <div class="form-section-title">2. Item details</div>
                <div class="mb-3">
                    <label for="f_title" class="form-label">Title</label>
                    <input type="text" id="f_title" name="title" class="form-control<?= invalid($errors, 'title') ?>" maxlength="150" required
                           value="<?= e($old['title'] ?? '') ?>" placeholder="e.g. Black leather wallet">
                    <?= feedback($errors, 'title') ?>
                </div>
                <div class="mb-3">
                    <label for="f_description" class="form-label">Description</label>
                    <textarea id="f_description" name="description" rows="4" maxlength="2000" required
                              class="form-control<?= invalid($errors, 'description') ?>"
                              placeholder="Colour, brand, size, anything that helps identify it"><?= e($old['description'] ?? '') ?></textarea>
                    <?= feedback($errors, 'description') ?>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="f_category" class="form-label">Category</label>
                        <select id="f_category" name="category_id" class="form-select<?= invalid($errors, 'category_id') ?>" required>
                            <option value="">Choose a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"<?= (string) ($old['category_id'] ?? '') === (string) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= feedback($errors, 'category_id') ?>
                    </div>
                    <div class="col-md-6">
                        <label for="f_date" class="form-label" data-date-label>Date <?= $type === 'found' ? 'found' : 'lost' ?></label>
                        <input type="date" id="f_date" name="date_occurred" class="form-control<?= invalid($errors, 'date_occurred') ?>" required
                               value="<?= e($old['date_occurred'] ?? '') ?>" max="<?= gmdate('Y-m-d', strtotime('+1 day')) ?>">
                        <?= feedback($errors, 'date_occurred') ?>
                    </div>
                </div>
            </div>

            <div class="cf-card p-3 p-md-4 mb-3">
                <div class="form-section-title">3. Where?</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="f_location" class="form-label">Campus location</label>
                        <select id="f_location" name="location_id" class="form-select<?= invalid($errors, 'location_id') ?>" required>
                            <option value="">Choose a location</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= (int) $location['id'] ?>"<?= (string) ($old['location_id'] ?? '') === (string) $location['id'] ? ' selected' : '' ?>><?= e($location['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= feedback($errors, 'location_id') ?>
                    </div>
                    <div class="col-md-6">
                        <label for="f_location_detail" class="form-label">Exact spot <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" id="f_location_detail" name="location_detail" maxlength="255"
                               class="form-control<?= invalid($errors, 'location_detail') ?>"
                               value="<?= e($old['location_detail'] ?? '') ?>" placeholder="e.g. 2nd floor, near the printers">
                        <?= feedback($errors, 'location_detail') ?>
                    </div>
                </div>
            </div>

            <div class="cf-card p-3 p-md-4 mb-3">
                <div class="form-section-title">4. Photos <span class="text-muted fw-normal text-lowercase">(optional, up to <?= ImageUpload::MAX_FILES ?>)</span></div>

                <?php if ($images): ?>
                    <p class="small text-muted mb-2">Current photos. Tick a photo to remove it.</p>
                    <div class="preview-grid mb-3">
                        <?php foreach ($images as $image): ?>
                            <div class="thumb">
                                <img src="<?= e(photo_url($image['file_name'])) ?>" alt="Item photo">
                                <label class="remove-check"><input type="checkbox" name="remove_images[]" value="<?= (int) $image['id'] ?>"> Remove</label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <label class="upload-zone d-block<?= isset($errors['photos']) ? ' border-danger' : '' ?>" data-upload-zone>
                    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-photo-input data-max="<?= ImageUpload::MAX_FILES ?>">
                    <span class="icon-bubble indigo mb-2"><i class="bi bi-cloud-arrow-up"></i></span>
                    <span class="d-block fw-semibold">Click to choose photos or drag them here</span>
                    <span class="d-block small text-muted">JPG, PNG or WebP · max 2 MB each · <?= max(0, $photoSlotsLeft) ?> slot<?= $photoSlotsLeft === 1 ? '' : 's' ?> available</span>
                </label>
                <?php if (isset($errors['photos'])): ?><div class="text-danger small mt-2"><i class="bi bi-exclamation-circle me-1"></i><?= e($errors['photos']) ?></div><?php endif; ?>
                <div class="preview-grid mt-3" data-photo-preview></div>
            </div>

            <div class="cf-card p-3 p-md-4 mb-4<?= $type === 'found' ? '' : ' d-none' ?>" data-found-only>
                <div class="form-section-title">5. Verification <span class="text-muted fw-normal text-lowercase">(only you can see this)</span></div>
                <label for="f_secret" class="form-label">Secret identifying detail <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" id="f_secret" name="secret_detail" maxlength="255" class="form-control<?= invalid($errors, 'secret_detail') ?>"
                       value="<?= e($old['secret_detail'] ?? '') ?>" placeholder="e.g. Has a red sticker inside, ₹200 note in the zip">
                <div class="form-text">Hidden from everyone else. Compare it with what claimants describe so you return the item to the real owner.</div>
                <?= feedback($errors, 'secret_detail') ?>
            </div>

            <div class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2">
                <a href="<?= $isEdit ? '/items/' . (int) $item['id'] : '/items' ?>" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-<?= $isEdit ? 'check-lg' : 'send' ?> me-1"></i><?= $isEdit ? 'Save changes' : 'Post item' ?>
                </button>
            </div>
        </form>
    </div>
</div>
