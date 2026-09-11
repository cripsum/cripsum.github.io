<?php
declare(strict_types=1);

/**
 * Contenuti in attesa di approvazione, per la coda di moderazione su Discord.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/content_moderation.php';

bot_require_key();
bot_require_method('GET');

bot_require_actor($mysqli);

$limit = max(1, min(25, (int)($_GET['limit'] ?? 10)));
$pending = [];

foreach (cripsum_community_post_types() as $type => $meta) {
    $table = $meta['table'];

    if (!auth_table_exists($mysqli, $table) || !auth_column_exists($mysqli, $table, 'approvato')) {
        continue;
    }

    $dateColumn = auth_column_exists($mysqli, $table, 'data_creazione') ? 'data_creazione' : 'id';

    $sql =
        "SELECT p.id, p.titolo, p.descrizione, p.id_utente, u.username, p.`$dateColumn` AS creato
         FROM `$table` p
         LEFT JOIN utenti u ON u.id = p.id_utente
         WHERE p.approvato = 0
         ORDER BY p.`$dateColumn` DESC
         LIMIT $limit";

    $result = $mysqli->query($sql);
    if (!$result) {
        continue;
    }

    while ($row = $result->fetch_assoc()) {
        $pending[] = [
            'type' => $type,
            'id' => (int)$row['id'],
            'title' => (string)($row['titolo'] ?? ''),
            'description' => mb_substr((string)($row['descrizione'] ?? ''), 0, 500),
            'author' => (string)($row['username'] ?? 'Utente eliminato'),
            'author_id' => (int)($row['id_utente'] ?? 0),
            'created_at' => $row['creato'],
            'url' => '/it/' . ($type === 'rimasto' ? 'rimasti' : 'shitpost') . '?post=' . (int)$row['id'],
            'media_url' => '/api/content/get_media.php?id=' . (int)$row['id'] . '&type=' . rawurlencode($type),
        ];
    }

    $result->free();
}

bot_json([
    'ok' => true,
    'pending' => $pending,
    'total' => count($pending),
]);
