<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gacha_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    if (!isLoggedIn()) {
        gacha_json(['ok' => false, 'status' => 'error', 'message' => 'Devi essere loggato.'], 401);
    }

    if (isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->set_charset('utf8mb4');
    }

    $state = gacha_get_public_state($mysqli, (int)$_SESSION['user_id']);

    gacha_json([
        'ok' => true,
        'status' => 'success',
        'state' => $state,
    ]);
} catch (GachaApiException $e) {
    gacha_json(array_merge([
        'ok' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
    ], $e->extra), (int)$e->status);
} catch (Throwable $e) {
    error_log('api_gacha_state error: ' . $e->getMessage());
    gacha_json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Errore interno del gacha.',
    ], 500);
}

