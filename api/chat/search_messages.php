<?php
/**
 * Ricerca nel testo dei messaggi di una chat: un gruppo (`chat_id`) o una
 * conversazione privata (`conversation_id`). Solo per chi ne fa parte, e
 * solo fra i messaggi che può vedere.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $query = trim((string)($_GET['query'] ?? $_GET['q'] ?? ''));
    $query = mb_substr($query, 0, 80, 'UTF-8');
    if (mb_strlen($query, 'UTF-8') < 2) {
        send_success(['results' => []]);
    }
    // % e _ scritti dall'utente valgono come caratteri normali.
    $like = '%' . addcslashes($query, '\\%_') . '%';
    $results = [];

    if (!empty($_GET['conversation_id'])) {
        $pair = cc_pm_require($mysqli, (int)$_GET['conversation_id'], $userId);
        [$visible, $types, $params] = cc_pm_visible_sql($pair);
        $stmt = $mysqli->prepare("
            SELECT m.id, m.sender_id, m.message, UNIX_TIMESTAMP(m.created_at) AS ts, u.username
            FROM private_messages m INNER JOIN utenti u ON u.id = m.sender_id
            WHERE $visible AND m.deleted_for_all = 0 AND m.message LIKE ?
            ORDER BY m.id DESC LIMIT 40
        ");
        $all = array_merge($params, [$like]);
        $stmt->bind_param($types . 's', ...$all);
    } else {
        $chatId = (int)($_GET['chat_id'] ?? 0);
        cg_require($mysqli, $chatId, $userId);
        $stmt = $mysqli->prepare("
            SELECT m.id, m.sender_id, m.body AS message, UNIX_TIMESTAMP(m.created_at) AS ts, u.username
            FROM chat_messages m LEFT JOIN utenti u ON u.id = m.sender_id
            WHERE m.chat_id = ? AND m.deleted_at IS NULL AND m.message_type <> 'system' AND m.body LIKE ?
            ORDER BY m.id DESC LIMIT 40
        ");
        $stmt->bind_param('is', $chatId, $like);
    }

    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $results[] = [
            'id' => (int)$row['id'],
            'sender_id' => (int)$row['sender_id'],
            'sender_username' => (string)($row['username'] ?? ''),
            'text' => cc_preview($row['message'], 200),
            'body' => cc_preview($row['message'], 200),
            'ts' => (int)$row['ts'],
        ];
    }
    $stmt->close();

    send_success(['results' => $results]);
});
