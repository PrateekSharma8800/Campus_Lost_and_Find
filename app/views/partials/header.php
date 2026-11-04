<?php
$user = Auth::user();
$path = current_path();

/** "active" class for the current section */
$active = fn (string $prefix) => ($prefix === '/' ? $path === '/' : str_starts_with($path, $prefix)) ? ' active' : '';
?>
<nav class="navbar navbar-expand-lg app-navbar sticky-top py-2">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/">
            <span class="brand-mark"><i class="bi bi-search-heart" aria-hidden="true"></i></span>
            <span>Campus<span class="text-primary">Find</span></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3 gap-lg-1">
                <li class="nav-item"><a class="nav-link<?= $active('/') ?>" href="/">Home</a></li>
                <li class="nav-item"><a class="nav-link<?= ($path === '/items' || preg_match('#^/items/\d+$#', $path)) ? ' active' : '' ?>" href="/items">Browse items</a></li>
                <?php if ($user): ?>
                    <li class="nav-item"><a class="nav-link<?= $active('/dashboard') ?>" href="/dashboard">Dashboard</a></li>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link<?= $active('/admin') ?>" href="/admin">Admin</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <?php if ($user): ?>
                <div class="d-flex flex-column flex-lg-row gap-2 align-items-lg-center">
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Post item
                        </button>
                        <ul class="dropdown-menu dropdown-menu-lg-end">
                            <li><a class="dropdown-item" href="/items/new?type=lost"><i class="bi bi-exclamation-circle text-danger me-2"></i>I lost something</a></li>
                            <li><a class="dropdown-item" href="/items/new?type=found"><i class="bi bi-check-circle text-success me-2"></i>I found something</a></li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 w-100" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                            <span class="text-truncate nav-user-name"><?= e($user['name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-lg-end">
                            <li><span class="dropdown-item-text small text-muted"><?= e($user['email']) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/dashboard"><i class="bi bi-grid me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="/my-items"><i class="bi bi-collection me-2"></i>My items</a></li>
                            <li><a class="dropdown-item" href="/my-claims"><i class="bi bi-patch-check me-2"></i>My claims</a></li>
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
                </div>
            <?php else: ?>
                <div class="d-flex gap-2">
                    <a href="/login" class="btn btn-light">Log in</a>
                    <a href="/register" class="btn btn-primary">Sign up</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
