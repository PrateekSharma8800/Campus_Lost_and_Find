<?php /** Page links for the browse page. Vars: $filters, $totalPages */
if ($totalPages <= 1) return;
$current = $filters['page'];
?>
<nav aria-label="Item pages" class="mt-4">
    <ul class="pagination justify-content-center flex-wrap">
        <li class="page-item<?= $current <= 1 ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e(items_url($filters, ['page' => $current - 1])) ?>" aria-label="Previous">&laquo;</a>
        </li>
        <?php for ($page = 1; $page <= $totalPages; $page++): ?>
            <?php if ($page === 1 || $page === $totalPages || abs($page - $current) <= 2): ?>
                <li class="page-item<?= $page === $current ? ' active' : '' ?>">
                    <a class="page-link" href="<?= e(items_url($filters, ['page' => $page])) ?>"><?= $page ?></a>
                </li>
            <?php elseif (abs($page - $current) === 3): ?>
                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
            <?php endif; ?>
        <?php endfor; ?>
        <li class="page-item<?= $current >= $totalPages ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e(items_url($filters, ['page' => $current + 1])) ?>" aria-label="Next">&raquo;</a>
        </li>
    </ul>
</nav>
