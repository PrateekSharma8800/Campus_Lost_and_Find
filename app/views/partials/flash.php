<?php
$icons = ['success' => 'check-circle', 'danger' => 'exclamation-octagon', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'];
foreach (Session::pullFlash() as $flash):
    $type = isset($icons[$flash['type']]) ? $flash['type'] : 'info';
?>
    <div class="alert alert-<?= $type ?> alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-<?= $icons[$type] ?> flex-shrink-0 mt-1" aria-hidden="true"></i>
        <div><?= e($flash['message']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>
