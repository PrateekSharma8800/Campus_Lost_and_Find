<?php
/** One conversation. Vars: $item, $itemPhoto, $otherUser, $messages, $conversations, $errors, $draft */
$myId = Auth::id();
$lastDay = null;
$lastMine = null;
foreach ($messages as $message) {
    if ((int) $message['sender_id'] === $myId) {
        $lastMine = $message;   // used for the "Seen" label
    }
}
?>
<div class="cf-card chat-shell">
    <!-- Conversation list (desktop only) -->
    <div class="chat-sidebar d-none d-lg-block">
        <?php if ($conversations): ?>
            <?php View::partial('conversation-list', ['conversations' => $conversations,
                'activeItemId' => (int) $item['id'], 'activeUserId' => (int) $otherUser['id']]) ?>
        <?php else: ?>
            <p class="text-muted small p-4 mb-0">Your conversations will appear here.</p>
        <?php endif; ?>
    </div>

    <div class="chat-panel">
        <!-- Header: who you're talking to and which item -->
        <div class="chat-header">
            <a href="/messages" class="btn btn-light btn-sm d-lg-none" aria-label="Back to messages"><i class="bi bi-arrow-left"></i></a>
            <span class="avatar avatar-lg"><?= e(mb_strtoupper(mb_substr($otherUser['name'], 0, 1))) ?></span>
            <div class="min-w-0 flex-grow-1">
                <div class="fw-semibold text-truncate"><?= e($otherUser['name']) ?></div>
                <div class="small text-muted text-truncate">
                    <?= (int) $item['user_id'] === $myId ? 'About your post' : 'Posted' ?> &middot; <?= e($item['title']) ?>
                </div>
            </div>
            <a href="/items/<?= (int) $item['id'] ?>" class="chat-item-link d-none d-sm-flex">
                <span class="list-thumb list-thumb-sm">
                    <?php if ($itemPhoto): ?><img src="<?= e(photo_url($itemPhoto)) ?>" alt=""><?php else: ?><i class="bi bi-box"></i><?php endif; ?>
                </span>
                <span class="d-flex flex-column align-items-start gap-1">
                    <?= type_badge($item['type']) ?>
                    <span class="small fw-semibold">View item <i class="bi bi-arrow-right"></i></span>
                </span>
            </a>
        </div>

        <!-- Message history -->
        <div class="chat-body" data-chat-body>
            <?php if (!$messages): ?>
                <div class="chat-empty">
                    <span class="icon-bubble indigo mb-3"><i class="bi bi-hand-thumbs-up"></i></span>
                    <h2 class="h6 mb-1">Start the conversation</h2>
                    <p class="small text-muted mb-0">Say hello to <?= e($otherUser['name']) ?> and describe how you can help.
                        Never share passwords or bank details.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($messages as $message):
                $isMine = (int) $message['sender_id'] === $myId;
                $day = format_date($message['created_at']);
                if ($day !== $lastDay):
                    $lastDay = $day;
                    $dayLabel = $day === format_date(gmdate('Y-m-d')) ? 'Today'
                        : ($day === format_date(gmdate('Y-m-d', strtotime('-1 day'))) ? 'Yesterday' : $day);
            ?>
                    <div class="chat-day"><span><?= e($dayLabel) ?></span></div>
                <?php endif; ?>
                <div class="bubble-row <?= $isMine ? 'mine' : 'theirs' ?>">
                    <div class="bubble">
                        <div class="bubble-text"><?= e($message['body']) ?></div>
                        <div class="bubble-meta">
                            <!-- Time is written in UTC; app.js converts it to the reader's local time -->
                            <time datetime="<?= e(gmdate('Y-m-d\TH:i:s\Z', strtotime($message['created_at'] . ' UTC'))) ?>" data-local-time>
                                <?= e(date('H:i', strtotime($message['created_at'] . ' UTC'))) ?> UTC
                            </time>
                            <?php if ($isMine && $lastMine && $message['id'] === $lastMine['id'] && $message['read_at']): ?>
                                &middot; <i class="bi bi-check2-all"></i> Seen
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div id="latest"></div>
        </div>

        <!-- Reply box -->
        <form method="post" action="/messages/<?= (int) $item['id'] ?>/<?= (int) $otherUser['id'] ?>" class="chat-composer" novalidate data-once data-chat-form>
            <?= csrf_field() ?>
            <div class="flex-grow-1">
                <label for="f_body" class="visually-hidden">Message</label>
                <textarea id="f_body" name="body" rows="1" maxlength="<?= Message::MAX_LENGTH ?>" required
                          class="form-control chat-input<?= invalid($errors, 'body') ?>"
                          placeholder="Write a message…" data-chat-input autofocus><?= e($draft) ?></textarea>
                <?= feedback($errors, 'body') ?>
                <div class="d-flex justify-content-between small text-muted mt-1 px-1">
                    <span class="d-none d-sm-inline">Enter to send · Shift + Enter for a new line</span>
                    <span><span data-char-count><?= mb_strlen($draft) ?></span> / <?= Message::MAX_LENGTH ?></span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary chat-send" aria-label="Send message"><i class="bi bi-send-fill"></i></button>
        </form>
    </div>
</div>
