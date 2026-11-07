<?php
/* Campus locations shown in the marquee (matches database/seed.sql) */
$places = [
    ['book', 'Main Library'], ['cup-hot', 'Cafeteria'], ['building', 'Academic Block A'],
    ['pc-display', 'Computer Lab'], ['easel', 'Auditorium'], ['trophy', 'Sports Complex'],
    ['house-door', 'Hostel Area'], ['p-square', 'Parking Lot'], ['signpost-split', 'Main Gate'],
];
/** Headline words, each wrapped so they can blur in one after another (CSS only, no inline styles). */
$headline = function (string $text, int $offset = 0): string {
    $html = [];
    foreach (explode(' ', $text) as $i => $word) {
        $html[] = '<span class="split-word d-' . min(15, $i + $offset) . '">' . e($word) . '</span>';
    }
    return implode(' ', $html);
};
?>
<section class="landing-hero">
    <div class="hero-glow" aria-hidden="true"></div>
    <div class="hero-rings" aria-hidden="true"></div>

    <div class="container text-center position-relative">
        <span class="hero-badge hero-in d-0"><i class="bi bi-stars" aria-hidden="true"></i>Your campus lost &amp; found, online</span>
        <h1><?= $headline('The easiest way to find') ?> <span class="grad"><?= $headline('what you lost on campus.', 5) ?></span></h1>
        <p class="hero-sub hero-in d-8">Post lost or found items in under a minute, search everything that&rsquo;s been
            handed in, and get things back safely with owner verification.</p>
        <div class="hero-ctas hero-in d-10">
            <?php if (Auth::check()): ?>
                <a href="/items/new?type=lost" class="btn btn-primary btn-lg">Report lost item</a>
                <a href="/items/new?type=found" class="link-arrow">Report found item <i class="bi bi-arrow-right"></i></a>
            <?php else: ?>
                <a href="/register" class="btn btn-primary btn-lg">Get started</a>
                <a href="#how-it-works" class="link-arrow">See how it works <i class="bi bi-arrow-right"></i></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="hero-stage hero-in d-12" aria-hidden="true">
        <div class="hero-window" data-tilt>
            <div class="win-bar">
                <span class="win-dots"><i></i><i></i><i></i></span>
                <span class="win-url"><i class="bi bi-lock me-1"></i>campusfind.app/board/lost-and-found</span>
                <span class="win-gear"><i class="bi bi-gear"></i></span>
            </div>
            <div class="win-body">
                <div class="win-rail">
                    <span class="brand-logo"><i class="bi bi-search-heart"></i></span>
                    <span class="on"><i class="bi bi-house"></i></span>
                    <i class="bi bi-bar-chart"></i><i class="bi bi-box"></i><i class="bi bi-people"></i><i class="bi bi-chat-square"></i>
                </div>
                <div class="win-side">
                    <div class="win-side-title">Campus Hub <span class="plus"><i class="bi bi-plus"></i></span></div>
                    <ul class="win-nav">
                        <li>Overview</li><li class="on">Lost items</li><li>Found items</li><li>Matches</li>
                    </ul>
                    <div class="win-label">HOTSPOTS</div>
                    <div class="win-hot">Main Library<div class="win-meter"><span></span>65%</div></div>
                    <div class="win-quote">&ldquo;Lost is just not found yet.&rdquo;</div>
                </div>
                <div class="win-main">
                    <div class="win-head">
                        <div><h3>Lost &amp; Found Board</h3><p>Track every item from report to return.</p></div>
                        <div class="win-actions"><span class="win-search"><i class="bi bi-search"></i>Search items...</span><span class="win-new">Post item</span></div>
                    </div>
                    <div class="board">
                        <div class="board-col">
                            <div class="col-head"><span>Reported lost<b>2</b></span><i class="bi bi-three-dots"></i></div>
                            <div class="col-add"><i class="bi bi-plus"></i> Add item</div>
                            <div class="task">
                                <div class="task-tags"><span class="tag violet">Electronics</span></div>
                                <h4>Black AirPods Case</h4>
                                <p>Left on a desk in the Computer Lab after the morning lecture.</p>
                                <div class="task-progress"><span>MATCH</span><span>30%</span></div>
                                <div class="task-bar"><span class="w30"></span></div>
                                <div class="task-foot"><span></span><span class="faces"><span>A</span><span>R</span></span></div>
                            </div>
                            <div class="task">
                                <div class="task-tags"><span class="tag blue">Books</span></div>
                                <h4>Data Structures Notes</h4>
                                <p>Blue spiral notebook, name on the inside cover.</p>
                            </div>
                        </div>
                        <div class="board-col">
                            <div class="col-head"><span>Possible match<b>1</b></span><i class="bi bi-three-dots"></i></div>
                            <div class="col-add"><i class="bi bi-plus"></i> Add item</div>
                            <div class="task hot">
                                <div class="task-tags"><span class="tag green">ID Card</span><span class="tag gray">Verified</span></div>
                                <h4>Student ID Card</h4>
                                <p>Found near the Main Library entrance. Owner confirmed the roll number.</p>
                                <div class="task-progress"><span>MATCH</span><span>80%</span></div>
                                <div class="task-bar green"><span class="w80"></span></div>
                                <div class="task-foot"><span><i class="bi bi-chat-square me-1"></i>12</span><span class="faces"><span>P</span><span>S</span><span>K</span></span></div>
                            </div>
                        </div>
                        <div class="board-col">
                            <div class="col-head"><span>Returned<b>1</b></span><i class="bi bi-three-dots"></i></div>
                            <div class="col-add"><i class="bi bi-plus"></i> Add item</div>
                            <div class="task">
                                <div class="task-tags"><span class="tag amber">Accessories</span></div>
                                <h4>Silver Water Bottle</h4>
                                <p>Handed back at the Sports Complex front desk.</p>
                                <div class="task-foot"><span><i class="bi bi-chat-square me-1"></i>5</span><span class="faces"><span>M</span><span>J</span></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="hero-marquee">
        <h2>Items turn up all over campus</h2>
        <div class="marquee">
            <div class="marquee-track">
                <?php foreach ([false, true] as $duplicate): ?>
                    <ul<?= $duplicate ? ' aria-hidden="true"' : '' ?>>
                        <?php foreach ($places as [$icon, $name]): ?>
                            <li><i class="bi bi-<?= $icon ?>" aria-hidden="true"></i><?= e($name) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="scroll-light-section">
    <span class="eyebrow">Why CampusFind</span>
    <p class="scroll-light" data-scroll-light>
        Every semester, hundreds of phones, wallets and ID cards go missing on campus. Most are found within a day
        but never make it back. <span class="accent">CampusFind connects the finder and the owner</span> so every
        item gets home.
    </p>
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
                <div class="col-6 col-lg-3"><?php View::partial('item-card', ['item' => $item]) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section id="how-it-works" class="pt-4">
    <div class="text-center mb-4 mb-lg-5">
        <span class="eyebrow">How it works</span>
        <h2 class="display-6 fw-medium mb-0">Three steps from lost to found.</h2>
    </div>
    <div class="row g-3 g-lg-4">
        <?php foreach ([
            ['megaphone', 'indigo', 'Report', 'Post a lost or found item with a description, location and photos.'],
            ['search', 'amber', 'Search', 'Filter by category, location and date to spot your item quickly.'],
            ['shield-check', 'green', 'Verify & return', 'Owners prove it is theirs with a detail only they would know.'],
        ] as $index => [$icon, $color, $heading, $text]): ?>
            <div class="col-md-4">
                <div class="cf-card step-card p-4 h-100" data-glow>
                    <span class="big-num" aria-hidden="true">0<?= $index + 1 ?></span>
                    <span class="icon-bubble <?= $color ?> mb-3"><i class="bi bi-<?= $icon ?>"></i></span>
                    <div class="step-number mb-1">STEP <?= $index + 1 ?></div>
                    <h3 class="h5"><?= e($heading) ?></h3>
                    <p class="text-muted mb-0"><?= e($text) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
