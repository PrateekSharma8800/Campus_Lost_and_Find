<div class="page-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-3">
    <div>
        <h1>Notifications</h1>
        <p><?= $unreadCount ? "You have $unreadCount unread notification" . ($unreadCount > 1 ? 's' : '') . '.' : 'You are all caught up.' ?></p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <div class="type-tabs">
            <a href="/notifications" class="<?= $onlyUnread ? '' : 'active' ?>">All</a>
            <a href="/notifications?filter=unread" class="<?= $onlyUnread ? 'active' : '' ?>">Unread</a>
        </div>
        <?php if ($unreadCount > 0): ?>
            <form method="post" action="/notifications/read-all" class="m-0" data-once>
                <?= csrf_field() ?><button type="submit" class="btn btn-light"><i class="bi bi-check2-all me-1"></i>Mark all read</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!$notifications): ?>
    <?php View::partial('empty-state', [
        'icon' => 'bell', 'title' => $onlyUnread ? 'No unread notifications' : 'No notifications yet',
        'text' => 'You will be notified here when someone claims your item or decides on your claim.',
    ]) ?>
<?php else: ?>
    <div class="cf-card overflow-hidden">
        <?php foreach ($notifications as $notification): ?>
            <?php View::partial('notification-row', ['notification' => $notification]) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
