<?php
require_once __DIR__ . '/bootstrap.php';
$user = chat_require_login_json($mysqli);
$userId = (int)$user['id'];
$data = chat_read_input();
chat_verify_csrf($data);
chat_touch_user($mysqli, $userId);

$message = chat_clean_message((string)($data['message'] ?? ''));
$replyTo = isset($data['reply_to']) && $data['reply_to'] !== '' ? (int)$data['reply_to'] : null;
$clientNonce = trim((string)($data['client_nonce'] ?? ''));
$clientNonce = preg_match('/^[a-zA-Z0-9._:-]{8,90}$/', $clientNonce) ? $clientNonce : bin2hex(random_bytes(16));

if ($error = chat_message_error($message)) {
    chat_json(['ok' => false, 'error' => $error], 422);
}

if (chat_has_bad_word($mysqli, $message)) {
    chat_json(['ok' => false, 'error' => 'Messaggio bloccato dal filtro.'], 422);
}

$rate = chat_rate_limit_ok($mysqli, $userId);
if (!$rate['ok'] && !chat_is_mod((string)$user['ruolo'])) {
    chat_json(['ok' => false, 'error' => 'Aspetta ancora ' . $rate['wait'] . 's.', 'wait' => $rate['wait']], 429);
}

if ($replyTo !== null) {
    $stmt = $mysqli->prepare('SELECT id FROM messages WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
    $stmt->bind_param('i', $replyTo);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) {
        $stmt->close();
        chat_json(['ok' => false, 'error' => 'Messaggio di risposta non trovato.'], 404);
    }
    $stmt->close();
}

if ($replyTo !== null) {
    $stmt = $mysqli->prepare('INSERT INTO messages (user_id, message, reply_to, created_at, client_nonce) VALUES (?, ?, ?, NOW(), ?)');
    if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
    $stmt->bind_param('isis', $userId, $message, $replyTo, $clientNonce);
} else {
    $stmt = $mysqli->prepare('INSERT INTO messages (user_id, message, created_at, client_nonce) VALUES (?, ?, NOW(), ?)');
    if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
    $stmt->bind_param('iss', $userId, $message, $clientNonce);
}

try {
    $ok = $stmt->execute();
} catch (Throwable $e) {
    $ok = false;
}
$messageId = (int)$mysqli->insert_id;
$stmt->close();

if (!$ok) {
    // Se il client ha mandato due volte lo stesso nonce, restituisci il messaggio già creato.
    $stmt = $mysqli->prepare('SELECT id FROM messages WHERE client_nonce = ? AND user_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('si', $clientNonce, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if ($row) $messageId = (int)$row['id'];
    }
}

if ($messageId <= 0) {
    chat_json(['ok' => false, 'error' => 'Non sono riuscito a inviare il messaggio.'], 500);
}

$messages = chat_fetch_messages($mysqli, $userId, ['after_id' => $messageId - 1, 'limit' => 1]);
chat_upsert_typing($mysqli, $userId, false);
chat_json(['ok' => true, 'message' => $messages[0] ?? null]);
