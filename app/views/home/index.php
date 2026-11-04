<section class="hero rounded-4 p-4 p-md-5 mb-5 text-center text-md-start">
    <div class="row align-items-center g-4">
        <div class="col-md-7">
            <h1 class="display-5 fw-bold mb-3">Lost something on campus?</h1>
            <p class="lead text-muted mb-4">CampusFind connects people who lost something with the people who found it.
                Post an item in a minute, browse what's been found, and get it back safely.</p>
            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center justify-content-md-start">
                <?php if (Auth::check()): ?>
                    <a href="/dashboard" class="btn btn-primary btn-lg"><i class="bi bi-speedometer2 me-2"></i>Go to dashboard</a>
                <?php else: ?>
                    <a href="/register" class="btn btn-primary btn-lg"><i class="bi bi-person-plus me-2"></i>Create free account</a>
                    <a href="/login" class="btn btn-outline-primary btn-lg">Log in</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-5 text-center d-none d-md-block">
            <i class="bi bi-search-heart hero-icon text-primary" aria-hidden="true"></i>
        </div>
    </div>
</section>

<h2 class="h4 text-center mb-4">How it works</h2>
<div class="row g-4">
    <?php foreach ([
        ['megaphone', 'Report', 'Post a lost or found item with a description, location and photos.'],
        ['search', 'Match', 'Browse and search posts. CampusFind suggests possible matches for you.'],
        ['patch-check', 'Verify & return', 'Claim an item by answering a question only the real owner would know.'],
    ] as [$icon, $heading, $text]): ?>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm text-center p-3">
                <div class="card-body">
                    <div class="feature-icon mb-3"><i class="bi bi-<?= $icon ?>" aria-hidden="true"></i></div>
                    <h3 class="h5"><?= e($heading) ?></h3>
                    <p class="text-muted mb-0"><?= e($text) ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
