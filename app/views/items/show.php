<?php
/** Item detail. Vars: $item, $images, $isOwner, $claims, $myClaim, $canClaim, $errors, $old */
$itemId = (int) $item['id'];
?>
<a href="/items" class="small text-muted"><i class="bi bi-arrow-left me-1"></i>Back to all items</a>

<div class="row g-4 mt-1">
    <!-- Left: photos -->
    <div class="col-lg-7">
        <?php if ($images): ?>
            <div class="gallery-main cf-card">
                <img src="<?= e(photo_url($images[0]['file_name'])) ?>" alt="<?= e($item['title']) ?>" data-gallery-main>
            </div>
            <?php if (count($images) > 1): ?>
                <div class="gallery-thumbs">
                    <?php foreach ($images as $index => $image): ?>
                        <button type="button" class="<?= $index === 0 ? 'active' : '' ?>" data-gallery-thumb="<?= e(photo_url($image['file_name'])) ?>" aria-label="Show photo <?= $index + 1 ?>">
                            <img src="<?= e(photo_url($image['file_name'])) ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="gallery-main cf-card item-media d-flex align-items-center justify-content-center">
                <div class="text-center">
                    <i class="bi <?= e($item['category_icon'] ?: 'bi-box') ?> display-3 text-primary opacity-50"></i>
                    <p class="text-muted small mt-2 mb-0">No photos were added</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: details and actions -->
    <div class="col-lg-5">
        <div class="cf-card p-4">
            <div class="d-flex gap-2 flex-wrap mb-2">
                <?= type_badge($item['type']) ?> <?= status_badge($item['status']) ?>
                <?php if (!$item['is_approved']): ?><span class="pill pill-status-closed"><i class="bi bi-eye-slash"></i>Hidden by admin</span><?php endif; ?>
            </div>
            <h1 class="h3 mb-3 text-break"><?= e($item['title']) ?></h1>
            <p class="text-muted mb-4 text-break pre-line"><?= e($item['description']) ?></p>

            <dl class="detail-list mb-4">
                <dt><i class="bi bi-tag"></i>Category</dt><dd><?= e($item['category_name']) ?></dd>
                <dt><i class="bi bi-geo-alt"></i>Location</dt>
                <dd><?= e($item['location_name']) ?><?php if ($item['location_detail']): ?><span class="d-block small text-muted"><?= e($item['location_detail']) ?></span><?php endif; ?></dd>
                <dt><i class="bi bi-calendar3"></i><?= $item['type'] === 'lost' ? 'Lost on' : 'Found on' ?></dt><dd><?= e(format_date($item['date_occurred'])) ?></dd>
                <dt><i class="bi bi-person"></i>Posted by</dt><dd><?= e($item['owner_name']) ?> <span class="small text-muted">· <?= e(time_ago($item['created_at'])) ?></span></dd>
            </dl>

            <?php if ($isOwner): ?>
                <?php if ($item['secret_detail']): ?>
                    <div class="secret-box mb-4 small">
                        <strong><i class="bi bi-lock me-1"></i>Your secret detail:</strong> <?= e($item['secret_detail']) ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2">
                    <a href="/items/<?= $itemId ?>/edit" class="btn btn-light"><i class="bi bi-pencil me-1"></i>Edit</a>
                    <?php if (in_array($item['status'], ['open', 'claimed'], true)): ?>
                        <form method="post" action="/items/<?= $itemId ?>/status">
                            <?= csrf_field() ?><input type="hidden" name="status" value="returned">
                            <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Mark as returned</button>
                        </form>
                        <form method="post" action="/items/<?= $itemId ?>/status">
                            <?= csrf_field() ?><input type="hidden" name="status" value="closed">
                            <button class="btn btn-light"><i class="bi bi-archive me-1"></i>Close post</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="/items/<?= $itemId ?>/status">
                            <?= csrf_field() ?><input type="hidden" name="status" value="open">
                            <button class="btn btn-light"><i class="bi bi-arrow-counterclockwise me-1"></i>Reopen</button>
                        </form>
                    <?php endif; ?>
                    <button type="button" class="btn btn-soft-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="bi bi-trash me-1"></i>Delete</button>
                </div>

            <?php elseif ($myClaim): ?>
                <div class="alert alert-light border mb-0 d-flex gap-2 align-items-center">
                    <i class="bi bi-patch-check text-primary fs-5"></i>
                    <div>You claimed this item <?= e(time_ago($myClaim['created_at'])) ?>. Status: <?= status_badge($myClaim['status']) ?></div>
                </div>

            <?php elseif ($canClaim): ?>
                <a href="#claim" class="btn btn-primary w-100 py-2"><i class="bi bi-hand-index-thumb me-1"></i>This is mine, claim it</a>

            <?php elseif (!Auth::check() && $item['type'] === 'found' && $item['status'] === 'open'): ?>
                <a href="/login" class="btn btn-primary w-100 py-2">Log in to claim this item</a>

            <?php elseif ($item['type'] === 'lost' && $item['status'] === 'open'): ?>
                <div class="alert alert-light border mb-0 small"><i class="bi bi-info-circle me-1"></i>Found this item? Messaging the owner is coming in the next update.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canClaim): ?>
    <!-- Claim form -->
    <div class="row mt-4" id="claim">
        <div class="col-lg-7">
            <div class="cf-card p-4">
                <h2 class="section-title mb-1">Claim this item</h2>
                <p class="text-muted small mb-3">The finder will compare your answer with details only the real owner would know.</p>
                <form method="post" action="/items/<?= $itemId ?>/claim" novalidate data-once>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="f_proof" class="form-label">Describe a unique identifying detail</label>
                        <input type="text" id="f_proof" name="proof_answer" maxlength="255" required class="form-control<?= invalid($errors, 'proof_answer') ?>"
                               value="<?= e($old['proof_answer'] ?? '') ?>" placeholder="e.g. stickers, scratches, what's inside">
                        <?= feedback($errors, 'proof_answer') ?>
                    </div>
                    <div class="mb-3">
                        <label for="f_message" class="form-label">Message to the finder</label>
                        <textarea id="f_message" name="message" rows="3" maxlength="1000" required class="form-control<?= invalid($errors, 'message') ?>"
                                  placeholder="When and where you lost it, how to reach you"><?= e($old['message'] ?? '') ?></textarea>
                        <?= feedback($errors, 'message') ?>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Send claim</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($isOwner && $item['type'] === 'found'): ?>
    <!-- Claims received (owner only) -->
    <div class="row mt-4">
        <div class="col-lg-7">
            <div class="cf-card p-4">
                <h2 class="section-title mb-3">Claims <span class="text-muted fw-normal">(<?= count($claims) ?>)</span></h2>
                <?php if (!$claims): ?>
                    <p class="text-muted mb-0 small"><i class="bi bi-hourglass-split me-1"></i>No one has claimed this item yet.</p>
                <?php endif; ?>
                <?php foreach ($claims as $claim): ?>
                    <div class="claim-item">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <strong><?= e($claim['claimant_name']) ?></strong>
                                <span class="d-block small text-muted"><?= e($claim['claimant_email']) ?> · <?= e(time_ago($claim['created_at'])) ?></span>
                            </div>
                            <?= status_badge($claim['status']) ?>
                        </div>
                        <p class="small mb-1"><span class="text-muted">Identifying detail:</span> <?= e($claim['proof_answer']) ?></p>
                        <p class="small mb-0 text-break"><span class="text-muted">Message:</span> <?= e($claim['message']) ?></p>
                        <?php if ($claim['status'] === 'pending' && $item['status'] === 'open'): ?>
                            <div class="d-flex gap-2 mt-3">
                                <form method="post" action="/claims/<?= (int) $claim['id'] ?>/approve">
                                    <?= csrf_field() ?><button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Approve</button>
                                </form>
                                <form method="post" action="/claims/<?= (int) $claim['id'] ?>/reject">
                                    <?= csrf_field() ?><button class="btn btn-sm btn-light"><i class="bi bi-x-lg me-1"></i>Reject</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($isOwner): ?>
    <!-- Delete confirmation (Bootstrap modal instead of a browser pop-up) -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-body p-4 text-center">
                    <span class="icon-bubble rose mb-3"><i class="bi bi-trash"></i></span>
                    <h2 class="h5" id="deleteModalLabel">Delete this post?</h2>
                    <p class="text-muted small">The item, its photos and any claims will be permanently removed.</p>
                    <form method="post" action="/items/<?= $itemId ?>/delete" class="d-flex gap-2 justify-content-center">
                        <?= csrf_field() ?>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Yes, delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
