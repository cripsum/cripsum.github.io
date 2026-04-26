<?php
require_once __DIR__ . '/bootstrap.php';
$user = chat_require_login_json($mysqli);
$userId = (int)$user['id'];
$data = chat_read_input();
chat_verify_csrf($data);

$messageId = (int)($data['id'] ?? 0);
$reason = trim((string)($data['reason'] ?? ''));
$reason = mb_substr($reason, 0, CHAT_MAX_REPORT_REASON, 'UTF-8');
if ($reason === '') $reason = 'Segnalazione utente';

if ($messageId <= 0) chat_json(['ok' => false, 'error' => 'Messaggio non valido.'], 422);

$stmt = $mysqli->prepare('SELECT user_id, deleted_at FROM messages WHERE id = ? LIMIT 1');
if (!$stmt) chat_json(['ok' => false, 'error' => 'Errore server.'], 500);
$stmt->bind_param('i', $messageId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row || !empty($row['deleted_at'])) chat_json(['ok' => false, 'error' => 'Messaggio non trovato.'], 404);
if ((int)$row['user_id'] === $userId) chat_json(['ok' => false, 'error' => 'Non puoi segnalare un tuo messaggio.'], 422);

$stmt = $mysqli->prepare('INSERT INTO chat_reports (message_id, reporter_id, reason, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = "open", created_at = NOW()');
if (!$stmt) chat_json(['ok' => false, 'error' => 'Tabella segnalazioni mancante. Esegui SQL.'], 500);
$stmt->bind_param('iis', $messageId, $userId, $reason);
$ok = $stmt->execute();
$stmt->close();

chat_json(['ok' => $ok, 'message' => 'Segnalazione inviata.']);
