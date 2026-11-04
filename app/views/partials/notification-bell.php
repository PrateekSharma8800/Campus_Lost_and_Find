<?php /** Navbar bell with the 5 latest notifications. Vars: $notifications, $unread */ ?>
<div class="dropdown">
    <button class="btn btn-light bell-button position-relative w-100" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
            aria-expanded="false" aria-label="Notifications<?= $unread ? ", $unread unread" : '' ?>">
        <i class="bi bi-bell"></i><span class="d-lg-none ms-2">Notifications</span>
        <?php if ($unread > 0): ?><span class="nav-badge bell-badge"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
    </button>
    <div class="dropdown-menu dropdown-menu-lg-end notification-menu p-0">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <strong>Notifications</strong>
            <?php if ($unread > 0): ?>
                <form method="post" action="/notifications/read-all" class="m-0">
                    <?= csrf_field() ?><button class="btn btn-link btn-sm p-0">Mark all read</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if (!$notifications): ?>
            <div class="text-center text-muted small py-4"><i class="bi bi-bell-slash d-block fs-4 mb-1"></i>You're all caught up</div>
        <?php endif; ?>
        <?php foreach ($notifications as $notification): ?>
            <?php View::partial('notification-row', ['notification' => $notification, 'compact' => true]) ?>
        <?php endforeach; ?>
        <a href="/notifications" class="d-block text-center small fw-semibold py-2 border-top">View all</a>
    </div>
</div>
