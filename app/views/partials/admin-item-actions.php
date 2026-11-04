<?php /** Hide/show + delete buttons for one item. Vars: $itemId, $itemTitle, $isApproved, $returnTo */ ?>
<div class="d-inline-flex gap-1">
    <form method="post" action="/admin/items/<?= (int) $itemId ?>/visibility"
          data-confirm="<?= e($isApproved ? "Hide \"$itemTitle\" from the public? The poster will be notified." : "Make \"$itemTitle\" visible again?") ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="approved" value="<?= $isApproved ? '0' : '1' ?>">
        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
        <button type="submit" class="btn btn-sm btn-light" title="<?= $isApproved ? 'Hide' : 'Show' ?>">
            <i class="bi bi-<?= $isApproved ? 'eye-slash' : 'eye' ?>"></i><span class="d-none d-xl-inline ms-1"><?= $isApproved ? 'Hide' : 'Show' ?></span>
        </button>
    </form>
    <form method="post" action="/admin/items/<?= (int) $itemId ?>/delete"
          data-confirm="<?= e("Permanently delete \"$itemTitle\" with its photos, claims and messages? This cannot be undone.") ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
        <button type="submit" class="btn btn-sm btn-soft-danger" title="Delete"><i class="bi bi-trash"></i><span class="d-none d-xl-inline ms-1">Delete</span></button>
    </form>
</div>
