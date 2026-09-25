<?php
declare(strict_types=1);

/**
 * POST /api/gacha/azioni  { action, ... }
 *
 *   visto        { ids: [..] }            spegne il badge NEW (vuoto = tutti)
 *   preferito    { id, on }
 *   wishlist     { id, on }
 *   converti     { id?, copie?, rarita? } copie in eccesso → frammenti (senza id: tutte, o solo quelle rarita')
 *   compra       { id }                   personaggio dal negozio dei frammenti
 *   collezione   { categoria_id }         premio della collezione completa
 *   destino      { banner, id }           bersaglio del rate-up (id 0 = nessuno)
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/gacha/collection.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    gacha_api_fail('Metodo non consentito.', 405);
}

$input = gacha_api_input();
$action = (string)($input['action'] ?? '');
$id = (int)($input['id'] ?? 0);
$on = filter_var($input['on'] ?? true, FILTER_VALIDATE_BOOLEAN);

try {
    switch ($action) {
        case 'visto':
            $ids = is_array($input['ids'] ?? null) ? $input['ids'] : [];
            gacha_api_json(['ok' => true, 'aggiornati' => gacha_action_seen($mysqli, $gachaUserId, $ids)]);
            // no break
        case 'preferito':
            gacha_api_json(['ok' => true, 'preferito' => gacha_action_favorite($mysqli, $gachaUserId, $id, $on)]);
            // no break
        case 'wishlist':
            gacha_api_json(['ok' => true, 'wishlist' => gacha_action_wishlist($mysqli, $gachaUserId, $id, $on)]);
            // no break
        case 'converti':
            $copies = isset($input['copie']) ? (int)$input['copie'] : null;
            $rarities = is_array($input['rarita'] ?? null) ? $input['rarita'] : [];
            gacha_api_json(['ok' => true] + gacha_action_convert($mysqli, $gachaUserId, $id, $copies, $rarities));
            // no break
        case 'compra':
            gacha_api_json(['ok' => true] + gacha_action_buy($mysqli, $gachaUserId, $id));
            // no break
        case 'collezione':
            gacha_api_json(['ok' => true] + gacha_action_claim_collection($mysqli, $gachaUserId, (int)($input['categoria_id'] ?? 0)));
            // no break
        case 'destino':
            gacha_api_json(['ok' => true] + gacha_action_destino($mysqli, $gachaUserId, (string)($input['banner'] ?? ''), $id));
            // no break
        default:
            gacha_api_fail('Azione non valida.');
    }
} catch (GachaActionException $e) {
    gacha_api_fail($e->getMessage(), $e->http);
} catch (Throwable $e) {
    error_log('[gacha azioni] ' . $action . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    gacha_api_fail('Errore interno. Riprova.', 500);
}
