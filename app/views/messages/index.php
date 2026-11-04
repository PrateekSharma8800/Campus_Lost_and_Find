<div class="page-header">
    <h1>Messages</h1>
    <p>Private conversations about lost and found items.</p>
</div>

<?php if (!$conversations): ?>
    <?php View::partial('empty-state', [
        'icon' => 'chat-dots', 'title' => 'No conversations yet',
        'text' => 'Open any item and press "Message" to contact the person who posted it. Your chats will appear here.',
        'actionUrl' => '/items', 'actionLabel' => 'Browse items',
    ]) ?>
<?php else: ?>
    <div class="cf-card chat-shell">
        <div class="chat-sidebar">
            <?php View::partial('conversation-list', ['conversations' => $conversations]) ?>
        </div>
        <div class="chat-placeholder d-none d-lg-flex">
            <div class="text-center">
                <span class="icon-bubble indigo mb-3"><i class="bi bi-chat-square-text"></i></span>
                <h2 class="h5">Select a conversation</h2>
                <p class="text-muted small mb-0">Choose a chat on the left to read and reply.</p>
            </div>
        </div>
    </div>
<?php endif; ?>
