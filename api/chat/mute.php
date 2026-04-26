<?php
require_once __DIR__ . '/bootstrap.php';
$user = chat_require_login_json($mysqli);
$userId = (int)$user['id'];
$data = chat_read_input();
chat_verify_csrf($data);

$targetId = (int)($data['user_id'] ?? 0);
$muted = !empty($data['muted']);

if ($targetId <= 0 || $targetId === $userId) chat_json(['ok' => false, 'error' => 'Utente non valido.'], 422);

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
