<?php
View::partial('admin-nav', ['active' => 'overview']);

// Chart scales: the tallest bar = 100%. Heights/widths use CSS classes in steps of 5% (no inline styles).
$maxPerDay   = max(1, ...array_map(fn ($d) => max($d['lost'], $d['found']), array_values($perDay)));
$maxCategory = max(1, ...array_map(fn ($c) => (int) $c['total'], $byCategory));
$totalItems  = max(1, array_sum($byStatus));
$pct = fn (int $value, int $max) => (int) (round($value / $max * 20) * 5);   // 0, 5, 10 ... 100
$activityIcons = [
    'user' => ['person-plus', 'indigo', 'New user'], 'item_lost' => ['exclamation-circle', 'rose', 'Lost item posted'],
    'item_found' => ['check-circle', 'green', 'Found item posted'], 'claim' => ['patch-question', 'amber', 'Claim on'],
    'report' => ['flag', 'rose', 'Report:'],
];
?>
<div class="row g-3 mb-4">
    <?php foreach ([
        ['people', 'indigo', 'Total users', $totals['users'], $totals['inactive_users'] . ' deactivated', '/admin/users'],
        ['box-seam', 'slate', 'Total items', $totals['items'], $totals['hidden'] . ' hidden', '/admin/items'],
        ['exclamation-circle', 'amber', 'Lost items', $totals['lost'], 'reported missing', '/admin/items?type=lost'],
        ['check-circle', 'indigo', 'Found items', $totals['found'], $totals['returned'] . ' returned', '/admin/items?type=found'],
        ['flag', 'rose', 'Open reports', $totals['open_reports'], 'waiting for review', '/admin/reports'],
        ['patch-question', 'green', 'Pending claims', $totals['pending_claims'], 'awaiting finders', '/admin/items'],
    ] as [$icon, $color, $label, $value, $hint, $link]): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= $link ?>" class="cf-card stat-card">
                <span class="icon-bubble <?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></span>
                <div class="stat-value"><?= $value ?></div>
                <div class="stat-label"><?= e($label) ?></div>
                <div class="small text-muted mt-1"><?= e($hint) ?></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <!-- Chart 1: items posted per day (grouped bars, lost vs found) -->
    <div class="col-lg-8">
        <div class="cf-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <div>
                    <h2 class="section-title">Items posted</h2>
                    <span class="small text-muted">Last 14 days</span>
                </div>
                <div class="chart-legend">
                    <span><i class="legend-swatch lost"></i>Lost</span>
                    <span><i class="legend-swatch found"></i>Found</span>
                </div>
            </div>
            <div class="bar-chart" role="img" aria-label="Lost and found items posted per day over the last 14 days">
                <?php foreach ($perDay as $day => $counts): ?>
                    <div class="bar-group" title="<?= e(date('d M', strtotime($day))) ?>: <?= $counts['lost'] ?> lost, <?= $counts['found'] ?> found">
                        <div class="bars">
                            <span class="bar lost h-pct-<?= $pct($counts['lost'], $maxPerDay) ?>"></span>
                            <span class="bar found h-pct-<?= $pct($counts['found'], $maxPerDay) ?>"></span>
                        </div>
                        <span class="bar-label"><?= e(date('d', strtotime($day))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- The same numbers as a table, for screen readers and exact values -->
            <details class="mt-3 small">
                <summary class="text-muted">View as table</summary>
                <table class="table table-sm mt-2 mb-0">
                    <thead><tr><th>Date</th><th>Lost</th><th>Found</th></tr></thead>
                    <tbody>
                        <?php foreach ($perDay as $day => $counts): ?>
                            <tr><td><?= e(format_date($day)) ?></td><td><?= $counts['lost'] ?></td><td><?= $counts['found'] ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        </div>
    </div>

    <!-- Chart 2: item status (single hue, direct labels) -->
    <div class="col-lg-4">
        <div class="cf-card p-4 h-100">
            <h2 class="section-title mb-1">Item status</h2>
            <span class="small text-muted d-block mb-3">
                <?= $totals['items'] ? round($byStatus['returned'] / $totalItems * 100) : 0 ?>% of all items returned to owners
            </span>
            <?php foreach ($byStatus as $status => $count): ?>
                <div class="hbar-row">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= e(ucfirst($status)) ?></span><span class="fw-semibold"><?= $count ?></span>
                    </div>
                    <div class="hbar-track"><span class="hbar-fill w-score-<?= $pct($count, $totalItems) ?>"></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Chart 3: items by category -->
    <div class="col-lg-6">
        <div class="cf-card p-4 h-100">
            <h2 class="section-title mb-3">Items by category</h2>
            <?php if (!$totals['items']): ?>
                <p class="text-muted small mb-0">No items posted yet.</p>
            <?php else: ?>
                <?php foreach ($byCategory as $category): ?>
                    <div class="hbar-row">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= e($category['name']) ?></span><span class="fw-semibold"><?= (int) $category['total'] ?></span>
                        </div>
                        <div class="hbar-track"><span class="hbar-fill w-score-<?= $pct((int) $category['total'], $maxCategory) ?>"></span></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="col-lg-6">
        <div class="cf-card h-100">
            <h2 class="section-title p-4 pb-2">Recent activity</h2>
            <?php if (!$activity): ?>
                <p class="text-muted small px-4 pb-4 mb-0">Nothing has happened yet.</p>
            <?php endif; ?>
            <?php foreach ($activity as $event):
                [$icon, $color, $prefix] = $activityIcons[$event['kind']] ?? ['dot', 'slate', ''];
                $label = $event['kind'] === 'report' ? Report::reasonLabel($event['label']) : $event['label'];
            ?>
                <a href="<?= e($event['link']) ?>" class="list-row text-reset">
                    <span class="icon-bubble <?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></span>
                    <span class="list-main">
                        <span class="list-title"><?= e($prefix) ?> <?= e($label) ?></span>
                        <span class="small text-muted"><?= e(time_ago($event['created_at'])) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
