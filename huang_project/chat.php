<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

require_login();

$user = current_user();
$users = chat_users($user['userId']);
$selectedUserId = isset($_GET['with']) ? $_GET['with'] : '';

if ($selectedUserId === '' && $users) {
    $selectedUserId = $users[0]['userId'];
}

$selectedUser = $selectedUserId !== '' ? get_user_by_id($selectedUserId) : null;
if ($selectedUser && $selectedUser['userId'] === $user['userId']) {
    $selectedUser = null;
}

page_header('Messages');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Realtime Chat</p>
            <h1>Messages</h1>
            <p class="muted">Send direct messages to any registered user. The conversation updates automatically.</p>
        </div>
    </div>
</section>

<section class="chat-shell mt">
    <aside class="panel chat-people">
        <h2>People</h2>
        <?php if (!$users): ?>
            <p class="muted">No other users found.</p>
        <?php else: ?>
            <div class="chat-user-list">
                <?php foreach ($users as $chatUser): ?>
                    <a class="<?php echo $selectedUserId === $chatUser['userId'] ? 'active' : ''; ?>" href="chat.php?with=<?php echo h($chatUser['userId']); ?>">
                        <?php echo layout_avatar($chatUser, 'avatar small'); ?>
                        <span>
                            <strong><?php echo h($chatUser['name']); ?></strong>
                            <em><?php echo h(role_label($chatUser['role'])); ?></em>
                        </span>
                        <?php if ((int) $chatUser['unreadCount'] > 0): ?>
                            <b><?php echo h($chatUser['unreadCount']); ?></b>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </aside>

    <section class="panel chat-panel">
        <?php if (!$selectedUser): ?>
            <div class="empty-state">Select a user to start chatting.</div>
        <?php else: ?>
            <div class="chat-head">
                <?php echo layout_avatar($selectedUser, 'avatar'); ?>
                <div>
                    <h2><?php echo h($selectedUser['name']); ?></h2>
                    <p class="muted"><?php echo h(role_label($selectedUser['role'])); ?> - <?php echo h($selectedUser['email']); ?></p>
                </div>
            </div>

            <div id="chatMessages" class="chat-messages" data-with="<?php echo h($selectedUser['userId']); ?>">
                <div class="empty-state">Loading messages...</div>
            </div>

            <form id="chatForm" class="chat-form" method="post" action="chat_api.php">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="receiverId" value="<?php echo h($selectedUser['userId']); ?>">
                <textarea id="chatText" name="message" rows="2" maxlength="1000" placeholder="Type a message..." required></textarea>
                <button type="submit">Send</button>
            </form>
        <?php endif; ?>
    </section>
</section>

<?php if ($selectedUser): ?>
<script>
(function () {
    var messagesEl = document.getElementById('chatMessages');
    var form = document.getElementById('chatForm');
    var text = document.getElementById('chatText');
    var otherUserId = messagesEl.getAttribute('data-with');
    var currentUserId = <?php echo json_encode($user['userId']); ?>;
    var busy = false;

    function htmlEscape(value) {
        return String(value || '').replace(/[&<>"']/g, function (ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[ch];
        });
    }

    function renderMessages(items) {
        var html = '';
        if (!items.length) {
            messagesEl.innerHTML = '<div class="empty-state">No messages yet.</div>';
            return;
        }

        for (var i = 0; i < items.length; i++) {
            var mine = items[i].senderId === currentUserId;
            html += '<div class="chat-bubble ' + (mine ? 'mine' : 'theirs') + '">';
            html += '<p>' + htmlEscape(items[i].message).replace(/\n/g, '<br>') + '</p>';
            html += '<span>' + htmlEscape(items[i].createdAt) + '</span>';
            html += '</div>';
        }

        messagesEl.innerHTML = html;
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function loadMessages() {
        if (busy) {
            return;
        }
        busy = true;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'chat_api.php?action=messages&with=' + encodeURIComponent(otherUserId), true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                busy = false;
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.ok) {
                            renderMessages(data.messages || []);
                        }
                    } catch (e) {}
                }
            }
        };
        xhr.send();
    }

    form.onsubmit = function (event) {
        event.preventDefault();
        var body = 'csrf_token=' + encodeURIComponent(form.csrf_token.value) +
            '&receiverId=' + encodeURIComponent(form.receiverId.value) +
            '&message=' + encodeURIComponent(text.value);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'chat_api.php?action=send', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                text.value = '';
                loadMessages();
            }
        };
        xhr.send(body);
    };

    loadMessages();
    window.setInterval(loadMessages, 3000);
})();
</script>
<?php endif; ?>
<?php page_footer(); ?>
