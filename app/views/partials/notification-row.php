<?php
/** One notification as a POST form (opening it marks it read). Vars: $notification, optional $compact */
[$icon, $color] = Notification::STYLES[$notification['type']] ?? ['bi-bell', 'indigo'];
$isUnread = $notification['read_at'] === null;
?>
<form method="post" action="/notifications/<?= (int) $notification['id'] ?>/open" class="m-0">
    <?= csrf_field() ?>
    <button type="submit" class="notification<?= $isUnread ? ' unread' : '' ?><?= !empty($compact) ? ' compact' : '' ?>">
        <span class="icon-bubble <?= $color ?>"><i class="bi <?= $icon ?>"></i></span>
        <span class="notification-main">
            <span class="notification-text"><?= e($notification['message']) ?></span>
            <span class="notification-time"><?= e(time_ago($notification['created_at'])) ?></span>
        </span>
        <?php if ($isUnread): ?><span class="unread-dot" aria-label="Unread"></span><?php endif; ?>
    </button>
</form>
