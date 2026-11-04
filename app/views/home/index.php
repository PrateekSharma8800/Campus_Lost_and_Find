<section class="hero mb-5">
    <div class="row align-items-center g-5">
        <div class="col-lg-7">
            <span class="hero-eyebrow mb-3"><i class="bi bi-stars"></i>Your campus lost &amp; found, online</span>
            <h1 class="mb-3">Lost it on campus?<br><span class="gradient-text">Let's find it together.</span></h1>
            <p class="lead text-muted mb-4">Post lost or found items in under a minute, search everything that's been handed in,
                and return items safely with owner verification.</p>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <?php if (Auth::check()): ?>
                    <a href="/items/new?type=lost" class="btn btn-primary btn-lg"><i class="bi bi-exclamation-circle me-2"></i>Report lost item</a>
                    <a href="/items/new?type=found" class="btn btn-light btn-lg"><i class="bi bi-check-circle me-2"></i>Report found item</a>
                <?php else: ?>
                    <a href="/register" class="btn btn-primary btn-lg"><i class="bi bi-person-plus me-2"></i>Create free account</a>
                    <a href="/items" class="btn btn-light btn-lg"><i class="bi bi-search me-2"></i>Browse items</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5 d-none d-lg-block" aria-hidden="true">
            <div class="hero-visual">
                <div class="cf-card"><span class="icon-bubble rose mb-2"><i class="bi bi-wallet2"></i></span><div class="fw-semibold">Brown wallet</div><div class="small text-muted mb-2">Cafeteria</div><?= type_badge('lost') ?></div>
                <div class="cf-card"><span class="icon-bubble green mb-2"><i class="bi bi-key"></i></span><div class="fw-semibold">Bike keys</div><div class="small text-muted mb-2">Parking lot</div><?= type_badge('found') ?></div>
                <div class="cf-card"><span class="icon-bubble indigo mb-2"><i class="bi bi-laptop"></i></span><div class="fw-semibold">Laptop charger</div><div class="small text-muted mb-2">Computer lab</div><?= status_badge('returned') ?></div>
                <div class="cf-card"><span class="icon-bubble amber mb-2"><i class="bi bi-person-vcard"></i></span><div class="fw-semibold">Student ID card</div><div class="small text-muted mb-2">Library</div><?= status_badge('claimed') ?></div>
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
            <h2 class="h4 mb-1">Recently posted</h2>
            <p class="text-muted mb-0 small">The latest open lost and found items on campus.</p>
        </div>
        <a href="/items" class="fw-semibold small">View all <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php if (!$latestItems): ?>
        <?php View::partial('empty-state', [
            'icon' => 'inbox', 'title' => 'No items yet',
            'text' => 'When students post lost or found items, they will show up here.',
        ]) ?>
    <?php else: ?>
        <div class="row g-3 g-lg-4">
            <?php foreach ($latestItems as $item): ?>
                <div class="col-12 col-sm-6 col-lg-3"><?php View::partial('item-card', ['item' => $item]) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section>
    <h2 class="h4 text-center mb-4">How CampusFind works</h2>
    <div class="row g-3 g-lg-4">
        <?php foreach ([
            ['megaphone', 'indigo', 'Report', 'Post a lost or found item with a description, location and photos.'],
            ['search', 'amber', 'Search', 'Filter by category, location and date to spot your item quickly.'],
            ['shield-check', 'green', 'Verify & return', 'Owners prove it is theirs with a detail only they would know.'],
        ] as $index => [$icon, $color, $heading, $text]): ?>
            <div class="col-md-4">
                <div class="cf-card p-4 h-100">
                    <span class="icon-bubble <?= $color ?> mb-3"><i class="bi bi-<?= $icon ?>"></i></span>
                    <div class="step-number mb-1">STEP <?= $index + 1 ?></div>
                    <h3 class="h5"><?= e($heading) ?></h3>
                    <p class="text-muted mb-0"><?= e($text) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
