<?php

/**
 * Cripsum™ — Rewind: lavoro schedulato
 *
 * Ricalcola le distribuzioni globali e rigenera i Rewind più vecchi.
 *
 * Non accetta un id utente: può solo rifare conti che riguardano dati già
 * esistenti, quindi non è puntabile contro nessuno. L'accesso richiede la
 * chiave condivisa dei job oppure una sessione admin.
 *
 * Endpoint : POST /api/rewind/precompute.php
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

$apiKey = (string)($_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '');
$isCron = $apiKey !== '' && defined('CRIPSUM_BOT_API_KEY') && hash_equals(CRIPSUM_BOT_API_KEY, $apiKey);

if (!$isCron) {
    // Fuori dal job schedulato vale la guardia admin standard, che si occupa
    // di ruolo, CSRF e log.
    require_once __DIR__ . '/../admin/bootstrap.php';
}

require_once __DIR__ . '/../../includes/rewind_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

cripsum_release_session();

$limit = isset($_POST['limit']) ? max(1, min(200, (int)$_POST['limit'])) : 25;

$aggregates = rewind_recompute_aggregates($mysqli, 'all');
$rebuilt = rewind_precompute_batch($mysqli, $limit);

echo json_encode([
    'ok'         => true,
    'aggregates' => $aggregates,
    'rebuilt'    => $rebuilt,
    'message'    => "Distribuzioni aggiornate, $rebuilt Rewind rigenerati.",
]);
