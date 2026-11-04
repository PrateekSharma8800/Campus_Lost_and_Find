<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Hello, <?= e($user['name']) ?></h1>
        <p class="text-muted mb-0">
            <?= e($user['email']) ?>
            <span class="badge text-bg-<?= $user['role'] === 'admin' ? 'danger' : 'secondary' ?> ms-1"><?= e(ucfirst($user['role'])) ?></span>
        </p>
    </div>
</div>

<div class="row g-3">
    <?php foreach ([
        ['exclamation-circle', 'My lost items', 'text-danger'],
        ['box-seam', 'My found items', 'text-success'],
        ['patch-question', 'My claims', 'text-warning'],
        ['chat-dots', 'Messages', 'text-primary'],
    ] as [$icon, $label, $color]): ?>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <i class="bi bi-<?= $icon ?> fs-3 <?= $color ?>" aria-hidden="true"></i>
                    <div class="fs-3 fw-bold mt-2">0</div>
                    <div class="text-muted small"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="alert alert-light border mt-4 mb-0">
    <i class="bi bi-tools me-1"></i> Posting and browsing items arrives in the next module.
</div>
