<?php
// api/chat/mute.php
// Dual endpoint: Mutes a group chat if 'chat_id' is provided, otherwise mutes a user in global chat.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();

// --- GROUP CHAT MUTE ROUTING ---
if (isset($input['chat_id'])) {
    $chatId = (int)$input['chat_id'];
    $duration = isset($input['duration']) ? (int)$input['duration'] : -1; // -1 = permanent
    
    if (!$chatId) {
        send_error("ID chat non valido.");
    }
    
    if (!isChatMember($mysqli, $chatId, $userId)) {
        send_error("Non partecipi a questo gruppo.", 403);
    }
    
    $mutedUntil = null;
    if ($duration > 0) {
        $mutedUntil = date('Y-m-d H:i:s', time() + $duration);
    } elseif ($duration === -1) {
        // 10 years in future
        $mutedUntil = date('Y-m-d H:i:s', time() + (10 * 365 * 24 * 3600));
    }
    
    $stmt = $mysqli->prepare("UPDATE chat_members SET muted_until = ? WHERE chat_id = ? AND user_id = ?");
    if (!$stmt) {
        send_error("Errore interno del server.", 500);
    }
    $stmt->bind_param("sii", $mutedUntil, $chatId, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        send_success([
            'muted_until' => $mutedUntil,
            'message' => "Gruppo silenziato con successo."
        ]);
    } else {
        send_error("Impossibile silenziare il gruppo.");
    }
}

// --- ORIGINAL GLOBAL CHAT USER MUTE LOGIC ---
$user = chat_require_login_json($mysqli);
$userId = (int)$user['id'];
$data = $input; // get_json_input already reads it

$targetId = (int)($data['user_id'] ?? 0);
$muted = !empty($data['muted']);

if ($targetId <= 0 || $targetId === $userId) {
    chat_json(['ok' => false, 'error' => 'Utente non valido.'], 422);
}

if ($muted) {
    $stmt = $mysqli->prepare('INSERT IGNORE INTO chat_mutes (muter_id, muted_id, created_at) VALUES (?, ?, NOW())');
    if (!$stmt) chat_json(['ok' => false, 'error' => 'Tabella mute mancante. Esegui SQL.'], 500);
    $stmt->bind_param('ii', $userId, $targetId);
} else {
    $stmt = $mysqli->prepare('DELETE FROM chat_mutes WHERE muter_id = ? AND muted_id = ?');
    if (!$stmt) chat_json(['ok' => false, 'error' => 'Tabella mute mancante. Esegui SQL.'], 500);
    $stmt->bind_param('ii', $userId, $targetId);
}
$ok = $stmt->execute();
$stmt->close();
chat_json(['ok' => $ok]);
?>
