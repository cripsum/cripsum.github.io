<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG CHAT SYSTEM ===\n";
echo "Current User ID: $userId\n\n";

// --- 1. Test presence.php UPDATE query ---
echo "1. Testing presence.php update...\n";
try {
    $ok = $mysqli->query("UPDATE utenti SET ultimo_accesso = NOW() WHERE id = $userId");
    if ($ok) {
        echo "SUCCESS: Updated ultimo_accesso.\n";
    } else {
        echo "FAILED: " . $mysqli->error . "\n";
    }
} catch (Throwable $e) {
    echo "EXCEPTiON: " . $e->getMessage() . "\n";
}

// --- 2. Test presence.php SELECT query ---
echo "\n2. Testing presence.php select query...\n";
$conversationId = 1; // ID fittizio per test
$queryOther = "
    SELECT 
        u.id, u.ultimo_accesso,
        cp.typing_status, cp.last_typing_at
    FROM private_conversation_participants cp
    INNER JOIN utenti u ON u.id = cp.user_id
    WHERE cp.conversation_id = ? AND cp.user_id != ?
    LIMIT 1
";
$stmt = $mysqli->prepare($queryOther);
if (!$stmt) {
    echo "PREPARE FAILED: " . $mysqli->error . "\n";
} else {
    $stmt->bind_param("ii", $conversationId, $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        echo "SUCCESS: Found participant status:\n";
        print_r($row);
    } else {
        echo "EXECUTE FAILED: " . $stmt->error . "\n";
    }
    $stmt->close();
}

// --- 3. Test get_chat_details.php ---
echo "\n3. Testing get_chat_details.php...\n";
echo "Checking participant settings...\n";
$stmtCheck = $mysqli->prepare("
    SELECT cp.nickname, cp.theme_color, cp.theme_bg, cp.favorite_emoji, c.is_group
    FROM private_conversation_participants cp
    INNER JOIN private_conversations c ON c.id = cp.conversation_id
    WHERE cp.conversation_id = ? AND cp.user_id = ?
    LIMIT 1
");
if (!$stmtCheck) {
    echo "PREPARE FAILED (Participant Check): " . $mysqli->error . "\n";
} else {
    $stmtCheck->bind_param("ii", $conversationId, $userId);
    if ($stmtCheck->execute()) {
        $row = $stmtCheck->get_result()->fetch_assoc();
        echo "SUCCESS: Participant settings:\n";
        print_r($row);
    } else {
        echo "EXECUTE FAILED: " . $stmtCheck->error . "\n";
    }
    $stmtCheck->close();
}

echo "\nChecking Pinned Messages...\n";
$queryPinned = "
    SELECT ppm.message_id, pm.message, pm.created_at, u.username as sender_username, ppm.pinned_at
    FROM private_pinned_messages ppm
    INNER JOIN private_messages pm ON pm.id = ppm.message_id
    INNER JOIN utenti u ON u.id = pm.sender_id
    WHERE ppm.conversation_id = ? AND pm.deleted_at IS NULL AND pm.deleted_for_all = 0
    ORDER BY ppm.id DESC
";
$stmtPinned = $mysqli->prepare($queryPinned);
if (!$stmtPinned) {
    echo "PREPARE FAILED (Pinned Messages): " . $mysqli->error . "\n";
} else {
    $stmtPinned->bind_param("i", $conversationId);
    if ($stmtPinned->execute()) {
        $rows = $stmtPinned->get_result()->fetch_all(MYSQLI_ASSOC);
        echo "SUCCESS: Found " . count($rows) . " pinned messages.\n";
    } else {
        echo "EXECUTE FAILED: " . $stmtPinned->error . "\n";
    }
    $stmtPinned->close();
}
?>
