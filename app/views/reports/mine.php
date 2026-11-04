<div class="page-header">
    <h1>My reports</h1>
    <p>Reports you have submitted and what the CampusFind team decided.</p>
</div>

<?php if (!$reports): ?>
    <?php View::partial('empty-state', [
        'icon' => 'flag', 'title' => 'No reports submitted',
        'text' => 'If you see a fake post or someone misusing CampusFind, use "Report" on the item or chat.',
    ]) ?>
<?php else: ?>
    <div class="cf-card">
        <?php foreach ($reports as $report):
            $statusClass = ['open' => 'pending', 'resolved' => 'approved', 'dismissed' => 'closed'][$report['status']];
        ?>
            <div class="list-row align-items-start">
                <span class="icon-bubble rose flex-shrink-0"><i class="bi bi-<?= $report['item_id'] ? 'file-earmark-text' : 'person-exclamation' ?>"></i></span>
                <div class="list-main">
                    <?php if ($report['item_id']): ?>
                        <a href="/items/<?= (int) $report['item_id'] ?>" class="list-title"><?= e($report['item_title']) ?></a>
                    <?php else: ?>
                        <span class="list-title">User: <?= e($report['reported_user_name'] ?? 'Deleted user') ?></span>
                    <?php endif; ?>
                    <div class="small text-muted mt-1"><?= e(Report::reasonLabel($report['reason'])) ?> · <?= e(time_ago($report['created_at'])) ?></div>
                    <div class="small mt-1 text-break"><?= e($report['details']) ?></div>
                </div>
                <span class="pill pill-status-<?= $statusClass ?>"><?= e(Report::STATUS_LABELS[$report['status']]) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
