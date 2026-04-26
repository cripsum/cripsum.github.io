<?php
require_once __DIR__ . '/bootstrap.php';
$user = chat_require_login_json($mysqli);
$userId = (int)$user['id'];
$data = chat_read_input();
chat_verify_csrf($data);

if (!chat_table_exists($mysqli, 'chat_reactions')) {
    chat_json(['ok' => false, 'error' => 'Reazioni non installate. Esegui lo SQL V2.1.'], 500);
}

$messageId = (int)($data['id'] ?? 0);
$emoji = trim((string)($data['emoji'] ?? ''));
$allowed = ['😭','🙏','🔥','💀','💯','😂','❤️','👍','👀','🗣️'];

if ($messageId <= 0 || !in_array($emoji, $allowed, true)) {
    chat_json(['ok' => false, 'error' => 'Reazione non valida.'], 422);
}

$stmt = $mysqli->prepare('SELECT id, deleted_at FROM messages WHERE id = ? LIMIT 1');
if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
$stmt->bind_param('i', $messageId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();
if (!$row || !empty($row['deleted_at'])) chat_json(['ok' => false, 'error' => 'Messaggio non trovato.'], 404);

$stmt = $mysqli->prepare('SELECT id FROM chat_reactions WHERE message_id = ? AND user_id = ? AND emoji = ? LIMIT 1');
if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
$stmt->bind_param('iis', $messageId, $userId, $emoji);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result ? $result->fetch_assoc() : null;
$stmt->close();

if ($existing) {
    $stmt = $mysqli->prepare('DELETE FROM chat_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?');
    if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
    $stmt->bind_param('iis', $messageId, $userId, $emoji);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $mysqli->prepare('INSERT INTO chat_reactions (message_id, user_id, emoji, created_at) VALUES (?, ?, ?, NOW())');
    if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
    $stmt->bind_param('iis', $messageId, $userId, $emoji);
    $stmt->execute();
    $stmt->close();
}

$messages = chat_fetch_messages($mysqli, $userId, ['after_id' => $messageId - 1, 'limit' => 1]);
chat_json(['ok' => true, 'message' => $messages[0] ?? null]);
