<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gacha_helpers.php';
require_once __DIR__ . '/../includes/redeem_codes.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        gacha_json(['ok' => false, 'status' => 'error', 'message' => 'Metodo non consentito.'], 405);
    }

    if (!isLoggedIn()) {
        gacha_json(['ok' => false, 'status' => 'error', 'message' => 'Devi essere loggato.'], 401);
    }

    if (isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->set_charset('utf8mb4');
    }

    $schema = gacha_schema_report($mysqli);
    if (!$schema['core_ready']) {
        gacha_json([
            'ok' => false,
            'status' => 'schema_missing',
            'message' => 'Migrazione gacha non applicata.',
            'missing' => $schema['core_missing'],
        ], 500);
    }

    $input = gacha_read_input();
    $code = strtolower(trim((string)($input['code'] ?? $input['codice'] ?? '')));

    $codes = cripsum_redeem_codes();
    $codeEntry = $codes[$code] ?? null;

    if (!$codeEntry || ($codeEntry['tipo'] ?? null) !== 'personaggio') {
        gacha_throw('Codice non valido.', 404);
    }
    $codeStatus = cripsum_redeem_code_status($codeEntry);
    if ($codeStatus === 'expired') {
        gacha_throw('Questo codice e scaduto.', 410, ['code' => 'EXPIRED']);
    }
    if ($codeStatus !== 'active') {
        gacha_throw('Questo codice non e disponibile al momento.', 404, ['code' => strtoupper($codeStatus)]);
    }

    $userId = (int)$_SESSION['user_id'];
    $characterName = (string)$codeEntry['nome'];

    $mysqli->begin_transaction();

    $nameCol = gacha_character_columns($mysqli)['name'];
    $stmt = $mysqli->prepare('SELECT ' . gacha_character_select_sql($mysqli, 'p') . ' FROM personaggi p WHERE ' . gacha_qfield('p', $nameCol) . ' = ? LIMIT 1');
    if (!$stmt) gacha_throw('Query codice non valida.', 500);
    $stmt->bind_param('s', $characterName);
    $stmt->execute();
    $characterRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$characterRow) {
        gacha_throw('Personaggio del codice non trovato.', 404);
    }

    if (gacha_user_has_character($mysqli, $userId, (int)$characterRow['id'])) {
        gacha_throw('Codice gia riscattato o personaggio gia presente in inventario.', 409);
    }

    $isNew = gacha_add_character_to_inventory($mysqli, $userId, (int)$characterRow['id']);
    $mysqli->commit();

    $state = gacha_get_public_state($mysqli, $userId);

    gacha_json([
        'ok' => true,
        'status' => 'success',
        'character' => gacha_public_character($characterRow),
        'is_new' => $isNew,
        'state' => $state,
        'message' => 'Codice riscattato.',
    ]);
} catch (GachaApiException $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->rollback();
    }

    gacha_json(array_merge([
        'ok' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
    ], $e->extra), (int)$e->status);
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->rollback();
    }

    error_log('api_redeem_gacha_code error: ' . $e->getMessage());
    gacha_json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Errore interno durante il riscatto.',
    ], 500);
}
