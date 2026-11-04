<?php
/** Admin section header + tabs. Var: $active (overview|users|items|reports) */
$openReports = Report::countOpen();
$tabs = [
    'overview' => ['/admin', 'speedometer2', 'Overview'],
    'users'    => ['/admin/users', 'people', 'Users'],
    'items'    => ['/admin/items', 'box-seam', 'Items'],
    'reports'  => ['/admin/reports', 'flag', 'Reports'],
];
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-2">
        <span class="icon-bubble indigo"><i class="bi bi-shield-lock"></i></span>
        <div>
            <h1 class="h3 mb-0">Admin panel</h1>
            <span class="small text-muted">Manage users, posts and reports</span>
        </div>
    </div>
    <nav class="type-tabs admin-tabs" aria-label="Admin sections">
        <?php foreach ($tabs as $key => [$url, $icon, $label]): ?>
            <a href="<?= $url ?>" class="<?= $active === $key ? 'active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
                <i class="bi bi-<?= $icon ?> me-1"></i><?= $label ?>
                <?php if ($key === 'reports' && $openReports > 0): ?><span class="nav-badge ms-1"><?= $openReports ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
