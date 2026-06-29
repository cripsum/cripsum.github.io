<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== TEMPORARY REMOTE DIAGNOSTIC ===\n\n";

$tables = [
    'utenti',
    'private_conversations',
    'private_conversation_participants',
    'private_messages',
    'private_pinned_messages',
    'private_message_attachments',
    'private_message_deleted'
];

foreach ($tables as $table) {
    echo "--- DESCRIBE $table ---\n";
    $res = $mysqli->query("DESCRIBE `$table`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "  {$row['Field']} - {$row['Type']} (Null: {$row['Null']}, Key: {$row['Key']}, Default: {$row['Default']})\n";
        }
    } else {
        echo "  ERROR: " . $mysqli->error . "\n";
    }
    echo "\n";
}

echo "--- TESTING get_chat_details.php QUERY ---\n";
try {
    $conversationId = 1;
    $userId = 1;
    $stmtCheck = $mysqli->prepare("
        SELECT cp.nickname, cp.theme_color, cp.theme_bg, cp.favorite_emoji, c.is_group
        FROM private_conversation_participants cp
        INNER JOIN private_conversations c ON c.id = cp.conversation_id
        WHERE cp.conversation_id = ? AND cp.user_id = ?
        LIMIT 1
    ");
    if (!$stmtCheck) {
        echo "PREPARE FAILED: " . $mysqli->error . "\n";
    } else {
        echo "PREPARE SUCCESSFUL\n";
        $stmtCheck->close();
    }
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
}

echo "\n--- TESTING presence.php NEW MESSAGES QUERY ---\n";
try {
    $conversationId = 1;
    $lastMessageId = 1;
    $userId = 1;
    $queryNew = "
        SELECT 
            m.id, m.conversation_id, m.sender_id, u.username as sender_username, m.message, 
            m.reply_to_id, m.forwarded_from_id, m.is_edited, m.ephemeral_timer, m.created_at,
            reply_m.message AS reply_message_text, reply_u.username AS reply_username
        FROM private_messages m
        INNER JOIN utenti u ON u.id = m.sender_id
        LEFT JOIN private_messages reply_m ON reply_m.id = m.reply_to_id
        LEFT JOIN utenti reply_u ON reply_u.id = reply_m.sender_id
        WHERE m.conversation_id = ? AND m.id > ? AND m.sender_id != ? AND m.deleted_at IS NULL
          AND NOT EXISTS (SELECT 1 FROM private_message_deleted pmd WHERE pmd.message_id = m.id AND pmd.user_id = ?)
        ORDER BY m.id ASC
    ";
    $stmtNew = $mysqli->prepare($queryNew);
    if (!$stmtNew) {
        echo "PREPARE FAILED: " . $mysqli->error . "\n";
    } else {
        echo "PREPARE SUCCESSFUL\n";
        $stmtNew->close();
    }
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
?>
