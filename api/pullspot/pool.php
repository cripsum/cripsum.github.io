<?php

/**
 * Cripsum™ — API Pullspot: chi è nel mazzo e chi no
 *
 * Endpoint : GET /api/pullspot/pool.php
 * Auth     : sessione PHP, solo admin e owner
 * Response : JSON
 *
 * Un personaggio che non esce mai non è un capriccio del caso: o non ha una
 * musica, o il file non c'è, o la sua traccia è condivisa da troppi. Questa
 * pagina lo dice, invece di lasciare che si indovini frugando nel database.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/pullspot_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

$lang = pullspot_request_lang();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => pullspot_msg('unauthenticated', $lang)]);
    exit;
}

$role = $_SESSION['ruolo'] ?? '';
if ($role !== 'admin' && $role !== 'owner') {
    http_response_code(403);
    echo json_encode(['error' => 'Riservato allo staff.']);
    exit;
}

checkBan($mysqli);

if (!pullspot_schema_ready($mysqli)) {
    http_response_code(503);
    echo json_encode(['error' => pullspot_msg('no_pool', $lang), 'code' => 'NO_POOL']);
    exit;
}

$columns = gacha_character_columns($mysqli);

$select  = '`id` AS id';
$select .= ', ' . gacha_qcol($columns['name']) . ' AS nome';
$select .= ', ' . gacha_qcol($columns['audio']) . ' AS audio_url';

$result = $mysqli->query('SELECT ' . $select . ' FROM `personaggi` ORDER BY `id` ASC');
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Query non riuscita.']);
    exit;
}

// Quante volte è usato ogni file: serve a spiegare le esclusioni per traccia
// condivisa, che altrimenti sembrano capitate a caso.
$rows = [];
$usage = [];

while ($row = $result->fetch_assoc()) {
    $path = pullspot_audio_path($row['audio_url'] ?? null);
    if ($path !== null) {
        $key = strtolower($path);
        $usage[$key] = ($usage[$key] ?? 0) + 1;
    }
    $rows[] = $row + ['_path' => $path];
}
$result->free();

$inPool = [];
$outPool = [];

foreach ($rows as $row) {
    $reason = pullspot_exclusion_reason($row);

    if ($reason === null && $row['_path'] !== null) {
        $shared = $usage[strtolower($row['_path'])] ?? 1;
        if ($shared > PULLSPOT_MAX_SHARED_AUDIO) {
            $reason = 'traccia condivisa da ' . $shared . ' personaggi (limite ' . PULLSPOT_MAX_SHARED_AUDIO . ')';
        }
    }

    $entry = [
        'id'    => (int)$row['id'],
        'nome'  => (string)($row['nome'] ?? ''),
        'audio' => (string)($row['audio_url'] ?? ''),
    ];

    if ($reason === null) {
        $inPool[] = $entry;
    } else {
        $outPool[] = $entry + ['motivo' => $reason];
    }
}

echo json_encode([
    'ok'        => true,
    'totale'    => count($rows),
    'giocabili' => count($inPool),
    'esclusi'   => count($outPool),
    'in_mazzo'  => $inPool,
    'fuori'     => $outPool,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
