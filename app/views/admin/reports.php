<?php View::partial('admin-nav', ['active' => 'reports']); ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="type-tabs" aria-label="Report status">
        <?php foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $value => $label): ?>
            <a href="/admin/reports?status=<?= $value ?>" class="<?= $status === $value ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
    <span class="small text-muted"><?= count($reports) ?> report<?= count($reports) === 1 ? '' : 's' ?></span>
</div>

<?php if (!$reports): ?>
    <?php View::partial('empty-state', $status === 'open'
        ? ['icon' => 'shield-check', 'title' => 'No open reports', 'text' => 'Everything has been reviewed. Nice work!']
        : ['icon' => 'flag', 'title' => 'No reports here', 'text' => 'Reports with this status will appear here.']) ?>
<?php else: ?>
    <div class="cf-card">
        <?php foreach ($reports as $report):
            $statusClass = ['open' => 'pending', 'resolved' => 'approved', 'dismissed' => 'closed'][$report['status']];
        ?>
            <a href="/admin/reports/<?= (int) $report['id'] ?>" class="list-row text-reset">
                <span class="icon-bubble rose"><i class="bi bi-<?= $report['item_id'] ? 'file-earmark-text' : 'person-exclamation' ?>"></i></span>
                <span class="list-main">
                    <span class="list-title">
                        <?= $report['item_id'] ? e($report['item_title']) : 'User: ' . e($report['reported_user_name'] ?? 'Deleted user') ?>
                    </span>
                    <span class="small text-muted d-block text-truncate">
                        <?= e(Report::reasonLabel($report['reason'])) ?> · by <?= e($report['reporter_name']) ?> · <?= e(time_ago($report['created_at'])) ?>
                    </span>
                </span>
                <span class="pill pill-status-<?= $statusClass ?>"><?= e(Report::STATUS_LABELS[$report['status']]) ?></span>
                <i class="bi bi-chevron-right text-muted"></i>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
