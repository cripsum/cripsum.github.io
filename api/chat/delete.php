<?php
require_once __DIR__ . '/bootstrap.php';
$user = chat_require_login_json($mysqli);
$userId = (int)$user['id'];
$data = chat_read_input();
chat_verify_csrf($data);

$messageId = (int)($data['id'] ?? 0);
if ($messageId <= 0) chat_json(['ok' => false, 'error' => 'Messaggio non valido.'], 422);

$stmt = $mysqli->prepare('SELECT user_id, deleted_at FROM messages WHERE id = ? LIMIT 1');
if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
$stmt->bind_param('i', $messageId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) chat_json(['ok' => false, 'error' => 'Messaggio non trovato.'], 404);
if (!empty($row['deleted_at'])) chat_json(['ok' => true]);

$isOwner = (int)$row['user_id'] === $userId;
if (!$isOwner && !chat_is_mod((string)$user['ruolo'])) {
    chat_json(['ok' => false, 'error' => 'Non puoi eliminare questo messaggio.'], 403);
}

$stmt = $mysqli->prepare('UPDATE messages SET message = "", deleted_at = NOW(), deleted_by = ? WHERE id = ?');
if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
$stmt->bind_param('ii', $userId, $messageId);
$ok = $stmt->execute();
$stmt->close();

chat_json(['ok' => $ok, 'id' => $messageId]);
