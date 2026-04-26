<?php
require_once __DIR__ . '/bootstrap.php';
$user = chat_require_login_json($mysqli);
$userId = (int)$user['id'];
$data = chat_read_input();
chat_verify_csrf($data);

$messageId = (int)($data['id'] ?? 0);
$message = chat_clean_message((string)($data['message'] ?? ''));

if ($messageId <= 0) chat_json(['ok' => false, 'error' => 'Messaggio non valido.'], 422);
if ($error = chat_message_error($message)) chat_json(['ok' => false, 'error' => $error], 422);
if (chat_has_bad_word($mysqli, $message)) chat_json(['ok' => false, 'error' => 'Messaggio bloccato dal filtro.'], 422);

$stmt = $mysqli->prepare('SELECT user_id, created_at, deleted_at FROM messages WHERE id = ? LIMIT 1');
if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
$stmt->bind_param('i', $messageId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) chat_json(['ok' => false, 'error' => 'Messaggio non trovato.'], 404);
if (!empty($row['deleted_at'])) chat_json(['ok' => false, 'error' => 'Messaggio già eliminato.'], 422);
if ((int)$row['user_id'] !== $userId) chat_json(['ok' => false, 'error' => 'Puoi modificare solo i tuoi messaggi.'], 403);

$created = strtotime((string)$row['created_at']) ?: 0;
if (!chat_is_mod((string)$user['ruolo']) && time() - $created > CHAT_EDIT_WINDOW_SECONDS) {
    chat_json(['ok' => false, 'error' => 'Tempo per la modifica scaduto.'], 403);
}

$stmt = $mysqli->prepare('UPDATE messages SET message = ?, edited_at = NOW() WHERE id = ? AND user_id = ?');
if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
$stmt->bind_param('sii', $message, $messageId, $userId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) chat_json(['ok' => false, 'error' => 'Modifica fallita.'], 500);
$messages = chat_fetch_messages($mysqli, $userId, ['after_id' => $messageId - 1, 'limit' => 1]);
chat_json(['ok' => true, 'message' => $messages[0] ?? null]);
