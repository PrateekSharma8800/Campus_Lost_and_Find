<?php View::partial('admin-nav', ['active' => 'users']); ?>

<form method="get" action="/admin/users" class="cf-card filter-card mb-3" role="search">
    <div class="row g-2">
        <div class="col-12 col-md-7">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="search" name="q" class="form-control" placeholder="Search name, email or student ID" value="<?= e($filters['q']) ?>" aria-label="Search users">
            </div>
        </div>
        <div class="col-8 col-md-3">
            <select name="status" class="form-select" aria-label="Status" data-autosubmit>
                <option value="">All accounts</option>
                <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                <option value="inactive"<?= $filters['status'] === 'inactive' ? ' selected' : '' ?>>Deactivated</option>
            </select>
        </div>
        <div class="col-4 col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button></div>
    </div>
</form>

<p class="small text-muted mb-2"><?= $total ?> user<?= $total === 1 ? '' : 's' ?> found</p>

<?php if (!$users): ?>
    <?php View::partial('empty-state', ['icon' => 'people', 'title' => 'No users found', 'text' => 'Try a different search or filter.',
        'actionUrl' => '/admin/users', 'actionLabel' => 'Clear filters']) ?>
<?php else: ?>
    <div class="cf-card overflow-hidden">
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr><th>User</th><th>Role</th><th class="d-none d-md-table-cell">Items</th><th class="d-none d-lg-table-cell">Joined</th><th class="d-none d-lg-table-cell">Last login</th><th>Status</th><th class="text-end">Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $row): $isActive = (bool) $row['is_active']; ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar"><?= e(mb_strtoupper(mb_substr($row['name'], 0, 1))) ?></span>
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate"><?= e($row['name']) ?></div>
                                    <div class="small text-muted text-truncate"><?= e($row['email']) ?><?= $row['student_id'] ? ' · ' . e($row['student_id']) : '' ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="pill <?= $row['role'] === 'admin' ? 'pill-lost' : 'pill-status-closed' ?>"><?= e(ucfirst($row['role'])) ?></span></td>
                        <td class="d-none d-md-table-cell"><?= (int) $row['item_count'] ?></td>
                        <td class="d-none d-lg-table-cell small"><?= e(format_date($row['created_at'])) ?></td>
                        <td class="d-none d-lg-table-cell small"><?= $row['last_login_at'] ? e(time_ago($row['last_login_at'])) : '<span class="text-muted">Never</span>' ?></td>
                        <td><?= $isActive ? '<span class="pill pill-status-approved">Active</span>' : '<span class="pill pill-status-rejected">Deactivated</span>' ?></td>
                        <td class="text-end">
                            <?php if ($row['role'] === 'admin'): ?>
                                <span class="small text-muted">Protected</span>
                            <?php else: ?>
                                <form method="post" action="/admin/users/<?= (int) $row['id'] ?>/status" class="d-inline"
                                      data-confirm="<?= e($isActive ? "Deactivate {$row['name']}? They will be signed out and cannot log in." : "Reactivate {$row['name']}? They will be able to log in again.") ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="active" value="<?= $isActive ? '0' : '1' ?>">
                                    <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/users') ?>">
                                    <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-soft-danger' : 'btn-light' ?>">
                                        <?= $isActive ? '<i class="bi bi-person-x me-1"></i>Deactivate' : '<i class="bi bi-person-check me-1"></i>Activate' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php View::partial('admin-pagination', ['baseUrl' => '/admin/users', 'filters' => $filters, 'totalPages' => $totalPages]) ?>
<?php endif; ?>
