<?php
$hasFilters = $filters['q'] !== '' || $filters['category'] !== '' || $filters['location'] !== ''
           || $filters['status'] !== '' || $filters['sort'] !== '';
?>
<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
    <div>
        <h1>Browse lost &amp; found items</h1>
        <p><?= $total ?> item<?= $total === 1 ? '' : 's' ?> <?= $hasFilters || $filters['type'] ? 'match your search' : 'currently listed' ?></p>
    </div>
    <div class="type-tabs" role="tablist" aria-label="Item type">
        <a href="<?= e(items_url($filters, ['type' => '', 'page' => 1])) ?>" class="<?= $filters['type'] === '' ? 'active' : '' ?>">All</a>
        <a href="<?= e(items_url($filters, ['type' => 'lost', 'page' => 1])) ?>" class="<?= $filters['type'] === 'lost' ? 'active' : '' ?>">Lost</a>
        <a href="<?= e(items_url($filters, ['type' => 'found', 'page' => 1])) ?>" class="<?= $filters['type'] === 'found' ? 'active' : '' ?>">Found</a>
    </div>
</div>

<!-- Search & filters: a normal GET form, so results can be bookmarked and shared -->
<form method="get" action="/items" class="cf-card filter-card mb-4" role="search">
    <?php if ($filters['type'] !== ''): ?><input type="hidden" name="type" value="<?= e($filters['type']) ?>"><?php endif; ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-lg-4">
            <div class="search-input">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" name="q" class="form-control" placeholder="Search e.g. wallet, calculator, ID card"
                       value="<?= e($filters['q']) ?>" maxlength="100" aria-label="Search items">
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select name="category" class="form-select" aria-label="Category" data-autosubmit>
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"<?= $filters['category'] === (string) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select name="location" class="form-select" aria-label="Location" data-autosubmit>
                <option value="">All locations</option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?= (int) $location['id'] ?>"<?= $filters['location'] === (string) $location['id'] ? ' selected' : '' ?>><?= e($location['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select name="status" class="form-select" aria-label="Status" data-autosubmit>
                <option value="">Active items</option>
                <option value="open"<?= $filters['status'] === 'open' ? ' selected' : '' ?>>Open only</option>
                <option value="returned"<?= $filters['status'] === 'returned' ? ' selected' : '' ?>>Returned</option>
                <option value="all"<?= $filters['status'] === 'all' ? ' selected' : '' ?>>All statuses</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2 d-flex gap-2">
            <select name="sort" class="form-select" aria-label="Sort" data-autosubmit>
                <option value="">Newest</option>
                <option value="oldest"<?= $filters['sort'] === 'oldest' ? ' selected' : '' ?>>Oldest</option>
            </select>
            <button type="submit" class="btn btn-primary px-3" aria-label="Search"><i class="bi bi-search"></i></button>
        </div>
    </div>
    <?php if ($hasFilters): ?>
        <div class="mt-2 small"><a href="<?= e($filters['type'] ? '/items?type=' . $filters['type'] : '/items') ?>"><i class="bi bi-x-circle me-1"></i>Clear filters</a></div>
    <?php endif; ?>
</form>

<?php if (!$items): ?>
    <?php View::partial('empty-state', $hasFilters || $filters['type'] ? [
        'icon' => 'search', 'title' => 'No items match your search',
        'text' => 'Try different keywords or remove some filters.',
        'actionUrl' => '/items', 'actionLabel' => 'Show all items',
    ] : [
        'icon' => 'inbox', 'title' => 'No items posted yet',
        'text' => 'Be the first to report something you lost or found on campus.',
        'actionUrl' => '/items/new?type=found', 'actionLabel' => 'Post an item',
    ]) ?>
<?php else: ?>
    <div class="row g-3 g-lg-4">
        <?php foreach ($items as $item): ?>
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                <?php View::partial('item-card', ['item' => $item]) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php View::partial('pagination', ['filters' => $filters, 'totalPages' => $totalPages]) ?>
<?php endif; ?>
