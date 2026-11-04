<?php
/** Report form. Vars: $target (['item' => ?array, 'user' => array]), $reasons, $old, $errors */
$item = $target['item'];
$reportedUser = $target['user'];
$backUrl = $item ? '/items/' . (int) $item['id'] : '/messages';
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-9 col-lg-7 col-xl-6">
        <div class="page-header">
            <a href="<?= e($backUrl) ?>" class="small text-muted"><i class="bi bi-arrow-left me-1"></i>Back</a>
            <h1 class="mt-2"><?= $item ? 'Report this post' : 'Report a user' ?></h1>
            <p>Reports are private. The CampusFind team reviews every report.</p>
        </div>

        <div class="cf-card p-3 mb-3 d-flex align-items-center gap-3">
            <span class="icon-bubble rose"><i class="bi bi-<?= $item ? 'file-earmark-text' : 'person-exclamation' ?>"></i></span>
            <div class="min-w-0">
                <div class="small text-muted">You are reporting</div>
                <div class="fw-semibold text-truncate"><?= e($item ? $item['title'] : $reportedUser['name']) ?></div>
                <?php if ($item): ?><div class="small text-muted">Posted by <?= e($reportedUser['name']) ?></div><?php endif; ?>
            </div>
        </div>

        <form method="post" action="/reports" class="cf-card p-3 p-md-4" novalidate data-once>
            <?= csrf_field() ?>
            <?php if ($item): ?>
                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="user_id" value="<?= (int) $reportedUser['id'] ?>">
            <?php endif; ?>

            <fieldset class="mb-3">
                <legend class="form-label">What's wrong?</legend>
                <div class="reason-list">
                    <?php foreach ($reasons as $value => $label): ?>
                        <div>
                            <input type="radio" name="reason" id="reason_<?= e($value) ?>" value="<?= e($value) ?>"<?= ($old['reason'] ?? '') === $value ? ' checked' : '' ?> required>
                            <label for="reason_<?= e($value) ?>"><?= e($label) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['reason'])): ?><div class="text-danger small mt-2"><?= e($errors['reason']) ?></div><?php endif; ?>
            </fieldset>

            <div class="mb-4">
                <label for="f_details" class="form-label">Describe the problem</label>
                <textarea id="f_details" name="details" rows="4" maxlength="1000" required class="form-control<?= invalid($errors, 'details') ?>"
                          placeholder="What happened? Include anything that helps us check it."><?= e($old['details'] ?? '') ?></textarea>
                <?= feedback($errors, 'details') ?>
            </div>

            <div class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2">
                <a href="<?= e($backUrl) ?>" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-danger"><i class="bi bi-flag me-1"></i>Submit report</button>
            </div>
        </form>
    </div>
</div>
