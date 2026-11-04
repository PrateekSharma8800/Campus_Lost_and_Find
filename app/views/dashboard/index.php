<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
    <div>
        <h1>Hello, <?= e($user['name']) ?> 👋</h1>
        <p><?= e($user['email']) ?>
            <span class="pill <?= $user['role'] === 'admin' ? 'pill-lost' : 'pill-status-closed' ?> ms-1"><?= e(ucfirst($user['role'])) ?></span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/items/new?type=lost" class="btn btn-light"><i class="bi bi-exclamation-circle text-danger me-1"></i>I lost something</a>
        <a href="/items/new?type=found" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>I found something</a>
    </div>
</div>

<?php if ($pendingReviews > 0): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-bell"></i>
        <div>You have <strong><?= $pendingReviews ?></strong> claim<?= $pendingReviews > 1 ? 's' : '' ?> waiting for your review. <a href="/my-items" class="alert-link">Review now</a></div>
    </div>
<?php endif; ?>

<?php if ($unreadMessages > 0): ?>
    <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="bi bi-chat-dots"></i>
        <div>You have <strong><?= $unreadMessages ?></strong> unread message<?= $unreadMessages > 1 ? 's' : '' ?>. <a href="/messages" class="alert-link">Open messages</a></div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['exclamation-circle', 'rose',  'Lost items posted',  $stats['lost'],  '/my-items'],
        ['box-seam',           'green', 'Found items posted', $stats['found'], '/my-items'],
        ['patch-check',        'amber', 'Claims made',        $claimsMade,     '/my-claims'],
        ['emoji-smile',        'indigo','Items returned',     $stats['returned'], '/my-items'],
    ] as [$icon, $color, $label, $value, $link]): ?>
        <div class="col-6 col-lg-3">
            <a href="<?= $link ?>" class="cf-card stat-card">
                <span class="icon-bubble <?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></span>
                <div class="stat-value"><?= (int) $value ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="section-title">Your recent posts</h2>
    <?php if ($recentItems): ?><a href="/my-items" class="small fw-semibold">View all <i class="bi bi-arrow-right"></i></a><?php endif; ?>
</div>

<?php if (!$recentItems): ?>
    <?php View::partial('empty-state', [
        'icon' => 'inbox', 'title' => 'Nothing posted yet',
        'text' => 'Your lost and found posts will appear here.',
        'actionUrl' => '/items/new', 'actionLabel' => 'Post an item',
    ]) ?>
<?php else: ?>
    <div class="cf-card">
        <?php foreach ($recentItems as $item): ?>
            <a href="/items/<?= (int) $item['id'] ?>" class="list-row text-reset">
                <div class="list-thumb">
                    <?php if ($item['photo']): ?><img src="<?= e(photo_url($item['photo'])) ?>" alt=""><?php else: ?><i class="bi <?= e($item['category_icon'] ?: 'bi-box') ?>"></i><?php endif; ?>
                </div>
                <div class="list-main">
                    <span class="list-title"><?= e($item['title']) ?></span>
                    <span class="small text-muted"><?= e($item['location_name']) ?> · <?= e(time_ago($item['created_at'])) ?></span>
                </div>
                <div class="d-flex gap-2 flex-wrap justify-content-end"><?= type_badge($item['type']) ?> <?= status_badge($item['status']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
