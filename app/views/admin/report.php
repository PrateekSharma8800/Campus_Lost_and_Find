<?php
View::partial('admin-nav', ['active' => 'reports']);
$statusClass = ['open' => 'pending', 'resolved' => 'approved', 'dismissed' => 'closed'][$report['status']];
$returnTo = '/admin/reports/' . (int) $report['id'];
?>
<a href="/admin/reports" class="small text-muted"><i class="bi bi-arrow-left me-1"></i>All reports</a>

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="cf-card p-4 mb-3">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <span class="small text-muted">Report #<?= (int) $report['id'] ?> · <?= e(format_date($report['created_at'])) ?></span>
                    <h2 class="h4 mb-0"><?= e(Report::reasonLabel($report['reason'])) ?></h2>
                </div>
                <span class="pill pill-status-<?= $statusClass ?>"><?= e(Report::STATUS_LABELS[$report['status']]) ?></span>
            </div>
            <p class="text-break pre-line mb-3"><?= e($report['details']) ?></p>
            <dl class="detail-list small mb-0">
                <dt><i class="bi bi-person"></i>Reported by</dt><dd><?= e($report['reporter_name']) ?> <span class="text-muted">(<?= e($report['reporter_email']) ?>)</span></dd>
                <?php if ($report['reviewed_at']): ?>
                    <dt><i class="bi bi-shield-check"></i>Reviewed</dt><dd><?= e($report['reviewer_name'] ?? 'Admin') ?> · <?= e(time_ago($report['reviewed_at'])) ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <?php if ($report['item_id']): ?>
            <div class="cf-card p-4 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 class="section-title">Reported post</h3>
                    <a href="/items/<?= (int) $report['item_id'] ?>" class="small fw-semibold">Open post <i class="bi bi-box-arrow-up-right"></i></a>
                </div>
                <div class="fw-semibold"><?= e($report['item_title']) ?></div>
                <div class="d-flex gap-2 my-2 flex-wrap">
                    <?= type_badge($report['item_type']) ?> <?= status_badge($report['item_status']) ?>
                    <?= $report['item_approved'] ? '<span class="pill pill-status-approved">Visible</span>' : '<span class="pill pill-status-closed"><i class="bi bi-eye-slash"></i>Hidden</span>' ?>
                </div>
                <p class="small text-muted text-break mb-3"><?= e(mb_strimwidth($report['item_description'], 0, 240, '…')) ?></p>
                <?php View::partial('admin-item-actions', ['itemId' => $report['item_id'], 'itemTitle' => $report['item_title'],
                    'isApproved' => (bool) $report['item_approved'], 'returnTo' => $returnTo]) ?>
            </div>
        <?php elseif (!$report['reported_user_id']): ?>
            <div class="alert alert-light border small">The reported post or user no longer exists.</div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <?php if ($report['reported_user_id']): ?>
            <div class="cf-card p-4 mb-3">
                <h3 class="section-title mb-3"><?= $report['item_id'] ? 'Post owner' : 'Reported user' ?></h3>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="avatar avatar-lg"><?= e(mb_strtoupper(mb_substr($report['reported_user_name'], 0, 1))) ?></span>
                    <div class="min-w-0">
                        <div class="fw-semibold text-truncate"><?= e($report['reported_user_name']) ?></div>
                        <div class="small text-muted text-truncate"><?= e($report['reported_user_email']) ?></div>
                    </div>
                </div>
                <dl class="detail-list small mb-3">
                    <dt>Account</dt><dd><?= $report['reported_user_active'] ? '<span class="pill pill-status-approved">Active</span>' : '<span class="pill pill-status-rejected">Deactivated</span>' ?></dd>
                    <dt>Joined</dt><dd><?= e(format_date($report['reported_user_joined'])) ?></dd>
                    <dt>Reports</dt><dd><?= (int) $report['reports_against_user'] ?> in total</dd>
                </dl>
                <?php if ($report['reported_user_role'] !== 'admin'): ?>
                    <form method="post" action="/admin/users/<?= (int) $report['reported_user_id'] ?>/status"
                          data-confirm="<?= e($report['reported_user_active'] ? "Deactivate {$report['reported_user_name']}? They will be signed out." : "Reactivate {$report['reported_user_name']}?") ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="active" value="<?= $report['reported_user_active'] ? '0' : '1' ?>">
                        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                        <button type="submit" class="btn btn-sm <?= $report['reported_user_active'] ? 'btn-soft-danger' : 'btn-light' ?>">
                            <i class="bi bi-person-<?= $report['reported_user_active'] ? 'x' : 'check' ?> me-1"></i><?= $report['reported_user_active'] ? 'Deactivate account' : 'Reactivate account' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="cf-card p-4">
            <h3 class="section-title mb-1">Decision</h3>
            <p class="small text-muted">The reporter is notified when you resolve or dismiss.</p>
            <div class="d-grid gap-2">
                <?php if ($report['status'] === 'open'): ?>
                    <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/status" class="d-grid" data-confirm="Mark this report as resolved (action was taken)?">
                        <?= csrf_field() ?><input type="hidden" name="status" value="resolved">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Resolve</button>
                    </form>
                    <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/status" class="d-grid" data-confirm="Dismiss this report (no problem found)?">
                        <?= csrf_field() ?><input type="hidden" name="status" value="dismissed">
                        <button type="submit" class="btn btn-light"><i class="bi bi-x-circle me-1"></i>Dismiss</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/status" class="d-grid">
                        <?= csrf_field() ?><input type="hidden" name="status" value="open">
                        <button type="submit" class="btn btn-light"><i class="bi bi-arrow-counterclockwise me-1"></i>Reopen report</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
