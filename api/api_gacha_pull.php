<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gacha_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function gacha_debug_rarity_from_input(array $input, bool $isAdmin): ?string
{
    if (!$isAdmin) return null;

    $rarity = gacha_normalize_rarity($input['debug_rarity'] ?? $input['rarity_override'] ?? '');
    $allowed = ['comune', 'raro', 'epico', 'leggendario', 'speciale', 'segreto', 'theone'];

    return in_array($rarity, $allowed, true) ? $rarity : null;
}

function gacha_debug_category_from_input(array $input, bool $isAdmin): ?string
{
    if (!$isAdmin) return null;

    $category = strtolower(trim((string)($input['debug_category'] ?? '')));
    return $category !== '' && preg_match('/^[a-z0-9_-]{1,40}$/', $category) ? $category : null;
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

    $input = gacha_read_input();
    $bannerType = strtolower(trim((string)($input['banner_id'] ?? $input['tipo_banner'] ?? 'standard')));
    $bannerType = in_array($bannerType, ['standard', 'evento', 'event'], true) ? $bannerType : 'standard';
    if ($bannerType === 'event') $bannerType = 'evento';

    $schema = gacha_schema_report($mysqli);
    if (!$schema['core_ready']) {
        gacha_json([
            'ok' => false,
            'status' => 'schema_missing',
            'message' => 'Migrazione gacha non applicata: mancano colonne o tabelle core.',
            'missing' => $schema['core_missing'],
        ], 500);
    }

    if ($bannerType === 'evento' && !$schema['event_ready']) {
        gacha_json([
            'ok' => false,
            'status' => 'schema_missing',
            'message' => 'Migrazione banner evento non applicata.',
            'missing' => $schema['event_missing'],
        ], 500);
    }

    $userId = (int)$_SESSION['user_id'];
    $mysqli->begin_transaction();

    $user = gacha_get_user_for_update($mysqli, $userId);
    $isAdmin = in_array($user['ruolo'], ['admin', 'owner'], true);
    $debugRarity = gacha_debug_rarity_from_input($input, $isAdmin);
    $debugCategory = gacha_debug_category_from_input($input, $isAdmin);

    $cost = 0;
    $eventBanner = null;
    $eventRateup = null;

    if ($bannerType === 'evento') {
        $eventBanner = gacha_get_active_event_banner($mysqli);
        if (!$eventBanner || empty($eventBanner['available']) || empty($eventBanner['rateup']['id'])) {
            gacha_throw('Nessun banner evento attivo.', 404);
        }

        $cost = max(0, (int)$eventBanner['costo']);
        if ($user['soldi'] < $cost) {
            gacha_throw('Punti insufficienti per questo banner.', 402, [
                'punti' => $user['soldi'],
                'costo' => $cost,
            ]);
        }

        $eventRateup = gacha_get_character_by_id($mysqli, (int)$eventBanner['rateup']['id']);
        if (!$eventRateup) {
            gacha_throw('Il personaggio rate up non esiste piu.', 500);
        }
    }

    $rolledRarity = $debugRarity ?: gacha_draw_weighted_rarity();
    $hitPity = false;
    $isHighRarity = gacha_is_high_rarity($rolledRarity);
    $outcome = 'standard_pool';
    $newPityStandard = $user['pity_standard'];
    $newPityEvento = $user['pity_evento'];
    $newGuaranteed = $user['garantito_evento'];

    if ($bannerType === 'standard') {
        $newPityStandard = $user['pity_standard'] + 1;

        if ($newPityStandard >= 80) {
            $hitPity = true;
            $isHighRarity = true;
            $characterRow = gacha_select_standard_high_character($mysqli);
        } else {
            $characterRow = gacha_select_character_with_fallback($mysqli, $rolledRarity, $debugCategory);
        }

        if (gacha_is_high_rarity((string)$characterRow['rarita'])) {
            $newPityStandard = 0;
        }

        $userUpdate = [
            'pity_standard' => $newPityStandard,
        ];
    } else {
        $newPityEvento = $user['pity_evento'] + 1;

        if ($newPityEvento >= 80) {
            $hitPity = true;
            $isHighRarity = true;
        }

        if ($isHighRarity) {
            if ($user['garantito_evento'] === 1) {
                $characterRow = $eventRateup;
                $newGuaranteed = 0;
                $outcome = 'guaranteed_rateup';
            } else {
                $wonRateup = random_int(1, 100) <= 50;
                if ($wonRateup) {
                    $characterRow = $eventRateup;
                    $newGuaranteed = 0;
                    $outcome = 'won_50_50';
                } else {
                    $characterRow = gacha_select_standard_high_character($mysqli);
                    $newGuaranteed = 1;
                    $outcome = 'lost_50_50';
                }
            }

            $newPityEvento = 0;
        } else {
            $characterRow = gacha_select_character_with_fallback($mysqli, $rolledRarity, $debugCategory);
        }

        $userUpdate = [
            'soldi' => $user['soldi'] - $cost,
            'pity_evento' => $newPityEvento,
            'garantito_evento' => $newGuaranteed,
        ];
    }

    $character = gacha_public_character($characterRow);
    if (!$character || empty($character['id'])) {
        gacha_throw('Pull non valida: personaggio mancante.', 500);
    }

    $isNew = gacha_add_character_to_inventory($mysqli, $userId, (int)$character['id']);
    gacha_update_user_after_pull($mysqli, $userId, $userUpdate);

    $mysqli->commit();

    $state = gacha_get_public_state($mysqli, $userId);

    gacha_json([
        'ok' => true,
        'status' => 'success',
        'banner' => $bannerType,
        'character' => $character,
        'is_new' => $isNew,
        'punti' => $state['user']['punti'],
        'pity' => $state['pity'],
        'state' => $state,
        'meta' => [
            'rarity_roll' => $rolledRarity,
            'hit_pity' => $hitPity,
            'is_high_rarity' => $isHighRarity,
            'outcome' => $outcome,
            'cost' => $cost,
            'debug_used' => (bool)($debugRarity || $debugCategory),
        ],
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

    error_log('api_gacha_pull error: ' . $e->getMessage());
    gacha_json([
        'ok' => false,
        'status' => 'error',
        'message' => 'Errore interno durante la pull.',
    ], 500);
}

