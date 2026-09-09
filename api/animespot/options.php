<?php

/**
 * Cripsum™ — API Animespot: difficoltà e frammenti
 *
 * Endpoint : POST /api/animespot/options.php
 * Body     : {"difficulty":1..5, "steps":[0,1,2,3,4]}
 * Auth     : sessione PHP + token CSRF
 *
 * Come nell'originale la difficoltà scelta vale dalla sigla successiva: chi la
 * cambia a metà partita non si vede sparire la traccia che sta ascoltando, e
 * soprattutto non può usare il cambio per scappare da una sigla difficile
 * senza contarsi la sconfitta.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/animespot_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

$lang = animespot_request_lang();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => animespot_msg('unauthenticated', $lang), 'code' => 'UNAUTHENTICATED']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => animespot_msg('method', $lang)]);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
$input = is_array($input) ? $input : $_POST;

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    http_response_code(419);
    echo json_encode(['error' => animespot_msg('csrf', $lang)]);
    exit;
}

checkBan($mysqli);

// Si cambia una voce alla volta: quello che non arriva resta com'era. Un
// pannello che manda solo il pulsante toccato non deve poter azzerare il resto.
$current = animespot_options();

$options = animespot_clean_options([
    'difficolta' => isset($input['difficulty']) ? (int)$input['difficulty'] : $current['difficolta'],
    'era'        => isset($input['era']) ? (int)$input['era'] : $current['era'],
    'passi'      => isset($input['steps']) && is_array($input['steps']) ? $input['steps'] : $current['passi'],
    'avvio'      => isset($input['start']) ? (string)$input['start'] : $current['avvio'],
    'ricerca'    => isset($input['search']) ? (string)$input['search'] : $current['ricerca'],
]);

animespot_options_save($options);

echo json_encode([
    'ok'      => true,
    'options' => $options,
    'pool'    => animespot_counts($mysqli, $options['era']),
    'eras'    => animespot_era_counts($mysqli),
    'name'    => animespot_level_name($options['difficolta'], $lang),
    'era_name' => animespot_era_name($options['era'], $lang),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
