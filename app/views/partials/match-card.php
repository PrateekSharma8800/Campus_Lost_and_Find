<?php
/** One suggested match. Var: $match = ['item' => row, 'score' => int, 'reasons' => [...]] */
$matchItem = $match['item'];
$level = $match['score'] >= 75 ? 'high' : ($match['score'] >= 60 ? 'medium' : 'low');
$label = ['high' => 'Strong match', 'medium' => 'Good match', 'low' => 'Possible match'][$level];
?>
<a href="/items/<?= (int) $matchItem['id'] ?>" class="cf-card match-card">
    <div class="d-flex gap-3 align-items-center">
        <div class="list-thumb">
            <?php if ($matchItem['photo']): ?>
                <img src="<?= e(photo_url($matchItem['photo'])) ?>" alt="" loading="lazy">
            <?php else: ?>
                <i class="bi <?= e($matchItem['category_icon'] ?: 'bi-box') ?>"></i>
            <?php endif; ?>
        </div>
        <div class="min-w-0">
            <div class="fw-semibold text-truncate"><?= e($matchItem['title']) ?></div>
            <div class="small text-muted text-truncate"><?= e($matchItem['location_name']) ?> · <?= e(format_date($matchItem['date_occurred'])) ?></div>
        </div>
    </div>
    <div class="match-score mt-3">
        <div class="d-flex justify-content-between small mb-1">
            <span class="fw-semibold match-<?= $level ?>"><?= $label ?></span>
            <span class="fw-bold"><?= (int) $match['score'] ?>%</span>
        </div>
        <div class="progress" role="progressbar" aria-label="Match score" aria-valuenow="<?= (int) $match['score'] ?>" aria-valuemin="0" aria-valuemax="100">
            <!-- Width via a class (no inline style, keeps the CSP strict) -->
            <div class="progress-bar match-bar-<?= $level ?> w-score-<?= (int) (round($match['score'] / 5) * 5) ?>"></div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-1 mt-3">
        <?php foreach ($match['reasons'] as $reason): ?>
            <span class="match-reason"><i class="bi bi-check2"></i><?= e($reason) ?></span>
        <?php endforeach; ?>
    </div>
</a>
