<?php
require __DIR__ . '/includes/gtss.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$action = isset($_GET['action']) ? $_GET['action'] : '';

function chat_json($data)
{
    echo json_encode($data);
    exit;
}

if ($action === 'messages') {
    $withUserId = isset($_GET['with']) ? $_GET['with'] : '';
    $withUser = get_user_by_id($withUserId);

    if (!$withUser || $withUser['userId'] === $user['userId']) {
        chat_json(array('ok' => false, 'error' => 'Invalid user.'));
    }

    db_exec(
        "UPDATE chat_messages
         SET isRead = 1
         WHERE senderId = '" . db_escape($withUserId) . "'
           AND receiverId = '" . db_escape($user['userId']) . "'"
    );

    $messages = db_all(
        "SELECT messageId, senderId, receiverId, message, isRead, createdAt
         FROM chat_messages
         WHERE (senderId = '" . db_escape($user['userId']) . "' AND receiverId = '" . db_escape($withUserId) . "')
            OR (senderId = '" . db_escape($withUserId) . "' AND receiverId = '" . db_escape($user['userId']) . "')
         ORDER BY createdAt ASC
         LIMIT 100"
    );

    chat_json(array('ok' => true, 'messages' => $messages));
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrfToken)) {
        chat_json(array('ok' => false, 'error' => 'Invalid token.'));
    }

    $receiverId = isset($_POST['receiverId']) ? $_POST['receiverId'] : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $receiver = get_user_by_id($receiverId);

    if (!$receiver || $receiver['userId'] === $user['userId']) {
        chat_json(array('ok' => false, 'error' => 'Invalid recipient.'));
    }

    if ($message === '' || strlen($message) > 1000) {
        chat_json(array('ok' => false, 'error' => 'Message must be between 1 and 1000 characters.'));
    }

    $messageId = uuid_v4();
    db_exec(
        "INSERT INTO chat_messages (messageId, senderId, receiverId, message, isRead, createdAt)
         VALUES ('" . db_escape($messageId) . "', '" . db_escape($user['userId']) . "', '" . db_escape($receiverId) . "', '" . db_escape($message) . "', 0, NOW())"
    );

    chat_json(array(
        'ok' => true,
        'message' => array(
            'messageId' => $messageId,
            'senderId' => $user['userId'],
            'receiverId' => $receiverId,
            'message' => $message,
            'isRead' => 0,
            'createdAt' => date('Y-m-d H:i:s'),
        ),
    ));
}

chat_json(array('ok' => false, 'error' => 'Unknown action.'));
?>
