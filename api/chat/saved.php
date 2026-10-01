<?php
/**
 * Messaggi salvati (i «preferiti») di chi chiede, dal più recente, con la
 * conversazione da cui vengono. Escono solo quelli ancora visibili.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    if (!rt_has_table($mysqli, 'private_favorites')) {
        send_success(['messages' => [], 'available' => false]);
    }

    $cleared = rt_has_col($mysqli, 'private_conversation_participants', 'cleared_before_id')
        ? 'AND m.id > COALESCE(cp.cleared_before_id, 0)'
        : '';

    $stmt = $mysqli->prepare("
        SELECT m.id, m.conversation_id, m.sender_id, m.message, m.message_type, UNIX_TIMESTAMP(m.created_at) AS ts,
               su.username AS sender_username, o.user_id AS other_user_id, ou.username AS other_username,
               (SELECT a.file_type FROM private_message_attachments a WHERE a.message_id = m.id ORDER BY a.id ASC LIMIT 1) AS attachment_type
        FROM private_favorites f
        INNER JOIN private_messages m ON m.id = f.message_id AND m.deleted_at IS NULL AND m.deleted_for_all = 0
        INNER JOIN private_conversation_participants cp ON cp.conversation_id = m.conversation_id AND cp.user_id = f.user_id
        INNER JOIN private_conversation_participants o ON o.conversation_id = m.conversation_id AND o.user_id <> f.user_id
        INNER JOIN utenti su ON su.id = m.sender_id
        INNER JOIN utenti ou ON ou.id = o.user_id
        WHERE f.user_id = ? $cleared
          AND NOT EXISTS (SELECT 1 FROM private_message_deleted d WHERE d.message_id = m.id AND d.user_id = f.user_id)
        ORDER BY f.id DESC
        LIMIT 100
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $messages = [];
    foreach ($rows as $row) {
        $messages[] = [
            'id' => (int)$row['id'],
            'conversation_id' => (int)$row['conversation_id'],
            'sender_id' => (int)$row['sender_id'],
            'sender_username' => (string)$row['sender_username'],
            'other_user_id' => (int)$row['other_user_id'],
            'other_username' => (string)$row['other_username'],
            'text' => cc_preview($row['message'], 220),
            'message_type' => (string)$row['message_type'],
            'attachment_type' => $row['attachment_type'],
            'ts' => (int)$row['ts'],
        ];
    }

    send_success(['messages' => $messages, 'available' => true]);
});
