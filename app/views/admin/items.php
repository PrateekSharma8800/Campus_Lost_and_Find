<?php View::partial('admin-nav', ['active' => 'items']); $returnTo = $_SERVER['REQUEST_URI'] ?? '/admin/items'; ?>

<form method="get" action="/admin/items" class="cf-card filter-card mb-3" role="search">
    <div class="row g-2">
        <div class="col-12 col-lg-4">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="search" name="q" class="form-control" placeholder="Search title, description or poster" value="<?= e($filters['q']) ?>" aria-label="Search items">
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select name="type" class="form-select" aria-label="Type" data-autosubmit>
                <option value="">All types</option>
                <option value="lost"<?= $filters['type'] === 'lost' ? ' selected' : '' ?>>Lost</option>
                <option value="found"<?= $filters['type'] === 'found' ? ' selected' : '' ?>>Found</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select name="status" class="form-select" aria-label="Status" data-autosubmit>
                <option value="">All statuses</option>
                <?php foreach (Item::STATUSES as $status): ?>
                    <option value="<?= $status ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= ucfirst($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select name="visibility" class="form-select" aria-label="Visibility" data-autosubmit>
                <option value="">Visible &amp; hidden</option>
                <option value="visible"<?= $filters['visibility'] === 'visible' ? ' selected' : '' ?>>Visible</option>
                <option value="hidden"<?= $filters['visibility'] === 'hidden' ? ' selected' : '' ?>>Hidden</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2 d-grid"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button></div>
    </div>
</form>

<p class="small text-muted mb-2"><?= $total ?> item<?= $total === 1 ? '' : 's' ?> found</p>

<?php if (!$items): ?>
    <?php View::partial('empty-state', ['icon' => 'box-seam', 'title' => 'No items found', 'text' => 'Try a different search or filter.',
        'actionUrl' => '/admin/items', 'actionLabel' => 'Clear filters']) ?>
<?php else: ?>
    <div class="cf-card overflow-hidden">
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr><th>Item</th><th class="d-none d-sm-table-cell">Type</th><th class="d-none d-sm-table-cell">Status</th><th class="d-none d-md-table-cell">Posted by</th><th class="d-none d-lg-table-cell">Posted</th><th class="d-none d-sm-table-cell">Visibility</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $row): ?>
                    <tr>
                        <td>
                            <a href="/items/<?= (int) $row['id'] ?>" class="d-flex align-items-center gap-2 text-reset">
                                <span class="list-thumb list-thumb-sm">
                                    <?php if ($row['photo']): ?><img src="<?= e(photo_url($row['photo'])) ?>" alt=""><?php else: ?><i class="bi <?= e($row['category_icon'] ?: 'bi-box') ?>"></i><?php endif; ?>
                                </span>
                                <span class="min-w-0">
                                    <span class="fw-semibold d-block text-truncate"><?= e($row['title']) ?></span>
                                    <span class="small text-muted"><?= e($row['category_name']) ?></span>
                                    <?php if ($row['open_reports'] > 0): ?><span class="pill pill-status-rejected ms-1"><i class="bi bi-flag"></i><?= (int) $row['open_reports'] ?></span><?php endif; ?>
                                    <!-- On phones the Type/Status/Visibility columns are hidden, so show them here -->
                                    <span class="d-flex d-sm-none flex-wrap gap-1 mt-1">
                                        <?= type_badge($row['type']) ?><?= status_badge($row['status']) ?>
                                        <?= $row['is_approved'] ? '' : '<span class="pill pill-status-closed"><i class="bi bi-eye-slash"></i>Hidden</span>' ?>
                                    </span>
                                </span>
                            </a>
                        </td>
                        <td class="d-none d-sm-table-cell"><?= type_badge($row['type']) ?></td>
                        <td class="d-none d-sm-table-cell"><?= status_badge($row['status']) ?></td>
                        <td class="d-none d-md-table-cell small"><?= e($row['owner_name']) ?></td>
                        <td class="d-none d-lg-table-cell small"><?= e(time_ago($row['created_at'])) ?></td>
                        <td class="d-none d-sm-table-cell"><?= $row['is_approved'] ? '<span class="pill pill-status-approved">Visible</span>' : '<span class="pill pill-status-closed"><i class="bi bi-eye-slash"></i>Hidden</span>' ?></td>
                        <td class="text-end">
                            <?php View::partial('admin-item-actions', ['itemId' => $row['id'], 'itemTitle' => $row['title'],
                                'isApproved' => (bool) $row['is_approved'], 'returnTo' => $returnTo]) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php View::partial('admin-pagination', ['baseUrl' => '/admin/items', 'filters' => $filters, 'totalPages' => $totalPages]) ?>
<?php endif; ?>
