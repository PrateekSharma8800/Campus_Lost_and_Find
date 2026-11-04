<div class="page-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-3">
    <div>
        <h1>My items</h1>
        <p>Everything you have posted. Open an item to edit it, review claims or mark it returned.</p>
    </div>
    <a href="/items/new" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Post item</a>
</div>

<?php if (!$items): ?>
    <?php View::partial('empty-state', [
        'icon' => 'collection', 'title' => "You haven't posted anything yet",
        'text' => 'Lost something, or found something that belongs to someone else? Post it in under a minute.',
        'actionUrl' => '/items/new', 'actionLabel' => 'Post your first item',
    ]) ?>
<?php else: ?>
    <div class="cf-card">
        <?php foreach ($items as $item): ?>
            <div class="list-row">
                <div class="list-thumb">
                    <?php if ($item['photo']): ?>
                        <img src="<?= e(photo_url($item['photo'])) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <i class="bi <?= e($item['category_icon'] ?: 'bi-box') ?>"></i>
                    <?php endif; ?>
                </div>
                <div class="list-main">
                    <a href="/items/<?= (int) $item['id'] ?>" class="list-title"><?= e($item['title']) ?></a>
                    <div class="d-flex flex-wrap gap-2 align-items-center mt-1">
                        <?= type_badge($item['type']) ?> <?= status_badge($item['status']) ?>
                        <?php if ($item['pending_claims'] > 0): ?>
                            <span class="pill pill-status-pending"><i class="bi bi-bell"></i><?= $item['pending_claims'] ?> claim<?= $item['pending_claims'] > 1 ? 's' : '' ?> to review</span>
                        <?php endif; ?>
                        <span class="small text-muted d-none d-md-inline">· <?= e($item['location_name']) ?> · posted <?= e(time_ago($item['created_at'])) ?></span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="/items/<?= (int) $item['id'] ?>/edit" class="btn btn-sm btn-light d-none d-sm-inline-block" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                    <a href="/items/<?= (int) $item['id'] ?>" class="btn btn-sm btn-light" aria-label="Open"><i class="bi bi-chevron-right"></i></a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
