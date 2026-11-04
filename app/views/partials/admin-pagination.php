<?php /** Simple Previous / Next pagination for admin lists. Vars: $baseUrl, $filters, $totalPages */
if ($totalPages <= 1) return;
$page = $filters['page'];
$link = fn (int $p) => $baseUrl . '?' . http_build_query(array_filter(array_merge($filters, ['page' => $p]), fn ($v) => $v !== '' && $v !== 1));
?>
<nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Pages">
    <a class="btn btn-light btn-sm<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= e($link($page - 1)) ?>"><i class="bi bi-chevron-left"></i> Previous</a>
    <span class="small text-muted">Page <?= $page ?> of <?= $totalPages ?></span>
    <a class="btn btn-light btn-sm<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= e($link($page + 1)) ?>">Next <i class="bi bi-chevron-right"></i></a>
</nav>
