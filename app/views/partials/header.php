<?php $user = Auth::user(); $path = current_path(); ?>
<nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/">
            <i class="bi bi-search-heart text-primary fs-4" aria-hidden="true"></i>
            <span>Campus<span class="text-primary">Find</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?= $path === '/' ? ' active' : '' ?>" href="/"<?= $path === '/' ? ' aria-current="page"' : '' ?>>Home</a>
                </li>
                <?php if ($user): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $path === '/dashboard' ? ' active' : '' ?>" href="/dashboard">Dashboard</a>
                    </li>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= str_starts_with($path, '/admin') ? ' active' : '' ?>" href="/admin">Admin</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <?php if ($user): ?>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        <span class="text-truncate nav-user-name"><?= e($user['name']) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-lg-end">
                        <li><span class="dropdown-item-text small text-muted"><?= e($user['email']) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/dashboard"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                        <?php if ($user['role'] === 'admin'): ?>
                            <li><a class="dropdown-item" href="/admin"><i class="bi bi-shield-lock me-2"></i>Admin panel</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="/logout" class="m-0">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="d-flex gap-2">
                    <a href="/login" class="btn btn-outline-primary">Log in</a>
                    <a href="/register" class="btn btn-primary">Sign up</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
