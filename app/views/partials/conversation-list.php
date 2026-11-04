<?php
/** Conversation list (inbox). Vars: $conversations, optional $activeItemId + $activeUserId */
$activeItemId = $activeItemId ?? 0;
$activeUserId = $activeUserId ?? 0;
?>
<div class="conversation-list">
    <?php foreach ($conversations as $conversation):
        $isActive = (int) $conversation['item_id'] === $activeItemId && (int) $conversation['other_id'] === $activeUserId;
        $isUnread = (int) $conversation['unread'] > 0 && !$isActive;
        $fromMe   = (int) $conversation['last_sender_id'] === Auth::id();
    ?>
        <a href="/messages/<?= (int) $conversation['item_id'] ?>/<?= (int) $conversation['other_id'] ?>"
           class="conversation<?= $isActive ? ' active' : '' ?><?= $isUnread ? ' unread' : '' ?>">
            <div class="list-thumb">
                <?php if ($conversation['photo']): ?>
                    <img src="<?= e(photo_url($conversation['photo'])) ?>" alt="">
                <?php else: ?>
                    <i class="bi bi-box"></i>
                <?php endif; ?>
            </div>
            <div class="conversation-main">
                <div class="d-flex justify-content-between gap-2">
                    <span class="conversation-name"><?= e($conversation['other_name']) ?></span>
                    <span class="conversation-time"><?= e(time_ago($conversation['last_at'])) ?></span>
                </div>
                <div class="conversation-item"><?= e($conversation['item_title']) ?></div>
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <span class="conversation-preview"><?= $fromMe ? 'You: ' : '' ?><?= e(mb_strimwidth($conversation['last_body'], 0, 70, '…')) ?></span>
                    <?php if ($isUnread): ?><span class="nav-badge"><?= (int) $conversation['unread'] ?></span><?php endif; ?>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
</div>
