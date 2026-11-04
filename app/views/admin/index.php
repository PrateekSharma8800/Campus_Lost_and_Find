<div class="d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-shield-lock fs-3 text-danger" aria-hidden="true"></i>
    <h1 class="h3 mb-0">Admin panel</h1>
</div>

<div class="row g-3">
    <?php foreach ([
        ['people', 'Total users', $users['total']],
        ['mortarboard', 'Students', $users['students']],
        ['shield-check', 'Admins', $users['admins']],
        ['box-seam', 'Items posted', $items],
    ] as [$icon, $label, $value]): ?>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <i class="bi bi-<?= $icon ?> fs-3 text-primary" aria-hidden="true"></i>
                    <div class="fs-3 fw-bold mt-2"><?= (int) $value ?></div>
                    <div class="text-muted small"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="alert alert-light border mt-4 mb-0">
    <i class="bi bi-tools me-1"></i> User, post, category and report management arrive with the Admin module.
</div>
