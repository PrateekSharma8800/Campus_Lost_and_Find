<?php /** Friendly "nothing here" box. Vars: $icon, $title, $text, optional $actionUrl + $actionLabel */ ?>
<div class="cf-card empty-state">
    <div class="icon-bubble indigo"><i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i></div>
    <h3><?= e($title) ?></h3>
    <p><?= e($text) ?></p>
    <?php if (!empty($actionUrl)): ?>
        <a href="<?= e($actionUrl) ?>" class="btn btn-primary"><?= e($actionLabel) ?></a>
    <?php endif; ?>
</div>
