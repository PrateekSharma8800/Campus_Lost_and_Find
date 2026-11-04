<?php /** One item in a grid. Var: $item (from Item::search / Item::latest) */ ?>
<a href="/items/<?= (int) $item['id'] ?>" class="cf-card item-card">
    <div class="item-media">
        <?php if ($item['photo']): ?>
            <img src="<?= e(photo_url($item['photo'])) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
        <?php else: ?>
            <div class="placeholder-icon"><i class="bi <?= e($item['category_icon'] ?: 'bi-box') ?>" aria-hidden="true"></i></div>
        <?php endif; ?>
        <div class="media-badges">
            <?= type_badge($item['type']) ?>
            <?php if ($item['status'] !== 'open'): ?><?= status_badge($item['status']) ?><?php endif; ?>
        </div>
    </div>
    <div class="item-body">
        <span class="small text-muted fw-medium"><?= e($item['category_name']) ?></span>
        <h3 class="item-title"><?= e($item['title']) ?></h3>
        <div class="mt-auto pt-2 d-flex flex-column gap-1">
            <span class="item-meta"><i class="bi bi-geo-alt"></i><?= e($item['location_name']) ?></span>
            <span class="item-meta"><i class="bi bi-calendar3"></i><?= e(format_date($item['date_occurred'])) ?></span>
        </div>
    </div>
</a>
