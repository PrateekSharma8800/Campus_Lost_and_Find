<div class="page-header">
    <h1>My claims</h1>
    <p>Items you have claimed and the finder's decision.</p>
</div>

<?php if (!$claims): ?>
    <?php View::partial('empty-state', [
        'icon' => 'patch-question', 'title' => 'No claims yet',
        'text' => 'If you spot your lost item among the found posts, open it and press "This is mine".',
        'actionUrl' => '/items?type=found', 'actionLabel' => 'Browse found items',
    ]) ?>
<?php else: ?>
    <div class="cf-card">
        <?php foreach ($claims as $claim): ?>
            <div class="list-row">
                <div class="list-thumb">
                    <?php if ($claim['photo']): ?>
                        <img src="<?= e(photo_url($claim['photo'])) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <i class="bi bi-box"></i>
                    <?php endif; ?>
                </div>
                <div class="list-main">
                    <a href="/items/<?= (int) $claim['item_id'] ?>" class="list-title"><?= e($claim['item_title']) ?></a>
                    <div class="small text-muted mt-1">Claimed <?= e(time_ago($claim['created_at'])) ?></div>
                </div>
                <?= status_badge($claim['status']) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
