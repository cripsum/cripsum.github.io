<?php

/**
 * api_gacha_multi_pull.php
 * POST /api/api_gacha_multi_pull
 *
 * Esegue 10 pull in un'unica transazione SQL.
 * Nessun round-trip per ogni singola pull → niente rate limit tra pull.
 * Rate limit: 1 richiesta multi ogni 5 secondi (sessione).
 *
 * Input JSON: { "banner_id": "standard"|int, "quantity": 10 }
 * Output JSON: { "status": "success", "pulls": [...10 risultati...], "soldi_rimasti": int, ... }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mission_tracker.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ════════════════════════════════════════════════════════════════════════════
// CONFIG (deve corrispondere a api_gacha_pull.php)
// ════════════════════════════════════════════════════════════════════════════

defined('PITY_STANDARD_SOFT') || define('PITY_STANDARD_SOFT', 70);
defined('PITY_STANDARD_HARD') || define('PITY_STANDARD_HARD', 90);
defined('PITY_EVENTO_SOFT')   || define('PITY_EVENTO_SOFT',   65);
defined('PITY_EVENTO_HARD')   || define('PITY_EVENTO_HARD',   80);

defined('MULTI_RATE_LIMIT_S') || define('MULTI_RATE_LIMIT_S', 5);   // secondi tra multi
defined('MULTI_MAX_QUANTITY') || define('MULTI_MAX_QUANTITY', 10);

defined('BASE_WEIGHTS_M') || define('BASE_WEIGHTS_M', [
    'comune'      => 51.00,
    'raro'        => 28.00,
    'epico'       => 13.00,
    'leggendario' =>  5.99,
    'speciale'    =>  1.80,
    'segreto'     =>  0.20,
    'theone'      =>  0.01,
]);

// ════════════════════════════════════════════════════════════════════════════
// VALIDAZIONI
// ════════════════════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non autenticato', 'code' => 'NOT_LOGGED_IN']);
    exit();
}

$userId = (int) $_SESSION['user_id'];

// Rate limit multi (sessione)
$now = time();
if (isset($_SESSION['gacha_multi_last_ts'])) {
    $elapsed = $now - (int)$_SESSION['gacha_multi_last_ts'];
    if ($elapsed < MULTI_RATE_LIMIT_S) {
        http_response_code(429);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Aspetta qualche secondo prima di fare un altra multi!',
            'code'    => 'RATE_LIMIT',
        ]);
        exit();
    }
}
$_SESSION['gacha_multi_last_ts'] = $now;

// Leggi body
$rawInput = file_get_contents('php://input');
$input    = !empty($rawInput) ? (json_decode($rawInput, true) ?? []) : [];
if (empty($input)) $input = $_POST;

$rawBannerId = $input['banner_id'] ?? null;
$quantity    = min(MULTI_MAX_QUANTITY, max(1, (int)($input['quantity'] ?? 10)));

if ($rawBannerId === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'banner_id mancante']);
    exit();
}

// ── Identifica banner ────────────────────────────────────────────────────────
$bannerType = null;
$bannerData = null;
$bannerId   = null;

if ($rawBannerId === 'standard') {
    $bannerType = 'standard';
    $bannerId   = 'standard';
} elseif (is_numeric($rawBannerId) && (int)$rawBannerId > 0) {
    $bannerIdInt = (int)$rawBannerId;
    $nowDt       = date('Y-m-d H:i:s');
    $stmtB = $mysqli->prepare(
        'SELECT id, nome, id_personaggio_rateup, costo_punti, data_fine
         FROM banner_eventi
         WHERE id = ? AND attivo = 1
           AND (data_inizio IS NULL OR data_inizio <= ?)
           AND (data_fine   IS NULL OR data_fine   >= ?)
         LIMIT 1'
    );
    if (!$stmtB) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'DB err']);
        exit();
    }
    $stmtB->bind_param('iss', $bannerIdInt, $nowDt, $nowDt);
    $stmtB->execute();
    $bannerData = $stmtB->get_result()->fetch_assoc();
    $stmtB->close();
    if (!$bannerData) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Banner non trovato o scaduto', 'code' => 'BANNER_NOT_FOUND']);
        exit();
    }
    $bannerType = 'evento';
    $bannerId   = (string)$bannerIdInt;
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'banner_id non valido']);
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// FUNZIONI CONDIVISE CON api_gacha_pull.php
// ════════════════════════════════════════════════════════════════════════════

function loadStandardPoolM(mysqli $db): array
{
    $pool = ['comune' => [], 'raro' => [], 'epico' => [], 'leggendario' => [], 'speciale' => [], 'segreto' => [], 'theone' => []];
    $stmt = $db->prepare(
        'SELECT id, nome, `rarità`, img_url, audio_url, video_url, descrizione, caratteristiche
         FROM personaggi WHERE in_pool_standard = 1'
    );
    if (!$stmt) return $pool;
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $r = strtolower(trim($row['rarità']));
        if (isset($pool[$r])) $pool[$r][] = $row;
    }
    $stmt->close();
    return $pool;
}

function selectRarityM(string $bannerType, int $pity, ?string $forceRarity = null): string
{
    if ($forceRarity) return $forceRarity;

    $weights = BASE_WEIGHTS_M;

    if ($bannerType === 'standard') {
        if ($pity >= PITY_STANDARD_SOFT) {
            $bonus = ($pity - PITY_STANDARD_SOFT + 1) * 4.0;
            $weights['speciale'] += $bonus;
            $weights['segreto']  += $bonus * 0.5;
        }
        if ($pity >= PITY_STANDARD_HARD) {
            return (mt_rand(1, 10) === 1) ? 'segreto' : 'speciale';
        }
    } elseif ($bannerType === 'evento') {
        if ($pity >= PITY_EVENTO_SOFT) {
            $bonus = ($pity - PITY_EVENTO_SOFT + 1) * 6.0;
            $weights['segreto'] += $bonus;
            $weights['theone']  += $bonus * 0.05;
        }
        if ($pity >= PITY_EVENTO_HARD) {
            return (mt_rand(1, 10) === 1) ? 'theone' : 'segreto';
        }
    }

    $total = array_sum($weights);
    $rand  = (mt_rand(0, PHP_INT_MAX - 1) / PHP_INT_MAX) * $total;
    foreach ($weights as $rarity => $weight) {
        $rand -= $weight;
        if ($rand <= 0) return $rarity;
    }
    return 'comune';
}

function pickFromPoolM(array $pool, string $rarity): ?array
{
    $candidates = $pool[$rarity] ?? [];
    if (empty($candidates)) {
        foreach (['comune', 'raro', 'epico', 'leggendario'] as $fb) {
            if (!empty($pool[$fb])) {
                $candidates = $pool[$fb];
                break;
            }
        }
    }
    if (empty($candidates)) return null;
    return $candidates[mt_rand(0, count($candidates) - 1)];
}

function loadCharByIdM(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT id, nome, `rarità`, img_url, audio_url, video_url, descrizione, caratteristiche
         FROM personaggi WHERE id = ? LIMIT 1'
    );
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $char = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $char ?: null;
}

// ════════════════════════════════════════════════════════════════════════════
// CARICA POOL (fuori dalla transazione, read-only)
// ════════════════════════════════════════════════════════════════════════════

$standardPool = loadStandardPoolM($mysqli);

// Admin force_rarity
$forceRarity = null;
$ruolo = $_SESSION['ruolo'] ?? 'utente';
if (in_array($ruolo, ['admin', 'owner'], true)) {
    $fr = $input['force_rarity'] ?? null;
    $validRarities = ['comune', 'raro', 'epico', 'leggendario', 'speciale', 'segreto', 'theone'];
    if ($fr && in_array($fr, $validRarities, true)) $forceRarity = $fr;
}

// ════════════════════════════════════════════════════════════════════════════
// TRANSAZIONE UNICA PER TUTTE E 10 LE PULL
// ════════════════════════════════════════════════════════════════════════════

$mysqli->begin_transaction();

try {
    // Blocca riga utente per tutta la durata delle 10 pull
    $stmtLock = $mysqli->prepare(
        'SELECT soldi, pity_standard, pity_evento, garantito_evento
         FROM utenti WHERE id = ? LIMIT 1 FOR UPDATE'
    );
    if (!$stmtLock) throw new RuntimeException('Prepare lock fallito');
    $stmtLock->bind_param('i', $userId);
    $stmtLock->execute();
    $user = $stmtLock->get_result()->fetch_assoc();
    $stmtLock->close();
    if (!$user) throw new RuntimeException('Utente non trovato', 404);

    $soldi        = (int)$user['soldi'];
    $pityStandard = (int)$user['pity_standard'];
    $pityEvento   = (int)$user['pity_evento'];
    $garantito    = (int)$user['garantito_evento'];

    // Verifica costo totale
    $costoSingola = ($bannerType === 'evento') ? (int)$bannerData['costo_punti'] : 0;
    $costoTotale  = $costoSingola * $quantity;
    if ($costoTotale > 0 && $soldi < $costoTotale) {
        throw new RuntimeException(
            "Punti insufficienti! Hai {$soldi} punti, servono {$costoTotale} per la multi.",
            402
        );
    }

    // Prepara statement riutilizzabili
    $stmtInv = $mysqli->prepare(
        'INSERT INTO utenti_personaggi (utente_id, personaggio_id, quantità, data)
         VALUES (?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE quantità = quantità + 1'
    );
    if (!$stmtInv) throw new RuntimeException('Prepare inventario fallito');

    $stmtHistNull = $mysqli->prepare(
        'INSERT INTO gacha_pull_history
           (utente_id, banner_id, personaggio_id, `rarità`, pity_al_momento, esito_50_50, is_new)
         VALUES (?, ?, ?, ?, ?, NULL, ?)'
    );
    $stmtHistVal = $mysqli->prepare(
        'INSERT INTO gacha_pull_history
           (utente_id, banner_id, personaggio_id, `rarità`, pity_al_momento, esito_50_50, is_new)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $pulls = [];
    $rateupId = ($bannerType === 'evento') ? (int)$bannerData['id_personaggio_rateup'] : 0;

    for ($i = 0; $i < $quantity; $i++) {
        $pityCorrente = ($bannerType === 'standard') ? $pityStandard : $pityEvento;
        $rarità       = selectRarityM($bannerType, $pityCorrente, $forceRarity);
        $isTopRarity  = in_array($rarità, ['segreto', 'theone'], true);
        $vinto50_50   = null;
        $personaggio  = null;

        // Aggiorna pity
        if ($bannerType === 'standard') {
            $pityStandard++;
            if (in_array($rarità, ['speciale', 'segreto', 'theone'], true)) $pityStandard = 0;
        } else {
            $pityEvento++;
        }

        // Selezione personaggio + 50/50
        if ($bannerType === 'evento' && $isTopRarity) {
            if ($garantito === 1) {
                $personaggio = loadCharByIdM($mysqli, $rateupId);
                $garantito   = 0;
                $vinto50_50  = 1;
            } else {
                if (mt_rand(0, 1) === 1) {
                    $personaggio = loadCharByIdM($mysqli, $rateupId);
                    $garantito   = 0;
                    $vinto50_50  = 1;
                } else {
                    $personaggio = pickFromPoolM($standardPool, 'segreto') ?? pickFromPoolM($standardPool, 'leggendario');
                    $garantito   = 1;
                    $vinto50_50  = 0;
                }
            }
            $pityEvento = 0;
        } else {
            $personaggio = pickFromPoolM($standardPool, $rarità);
        }

        if (!$personaggio) throw new RuntimeException("Nessun personaggio trovato (pull $i, rarità: $rarità)", 500);

        $personaggioId = (int)$personaggio['id'];

        // Upsert inventario
        $stmtInv->bind_param('ii', $userId, $personaggioId);
        $stmtInv->execute();
        $isNew = ($stmtInv->affected_rows === 1);
        $isNewInt = $isNew ? 1 : 0;

        // Storico pull
        if ($vinto50_50 !== null) {
            $v50 = (int)$vinto50_50;
            $stmtHistVal->bind_param('isisiii', $userId, $bannerId, $personaggioId, $rarità, $pityCorrente, $v50, $isNewInt);
            $stmtHistVal->execute();
        } else {
            $stmtHistNull->bind_param('isisii', $userId, $bannerId, $personaggioId, $rarità, $pityCorrente, $isNewInt);
            $stmtHistNull->execute();
        }

        $pulls[] = [
            'personaggio' => [
                'id'        => $personaggioId,
                'nome'      => $personaggio['nome'],
                'rarità'    => $personaggio['rarità'],
                'img_url'   => $personaggio['img_url'],
                'audio_url' => $personaggio['audio_url'],
                'video_url' => $personaggio['video_url'],
            ],
            'is_new'       => $isNew,
            'vinto_50_50'  => $vinto50_50,
            'pity_snapshot' => $pityCorrente,
        ];
    }

    // Scala punti una volta sola
    if ($costoTotale > 0) {
        $stmtMoney = $mysqli->prepare(
            'UPDATE utenti SET soldi = soldi - ? WHERE id = ? AND soldi >= ?'
        );
        $stmtMoney->bind_param('iii', $costoTotale, $userId, $costoTotale);
        $stmtMoney->execute();
        if ($stmtMoney->affected_rows === 0) {
            $stmtMoney->close();
            throw new RuntimeException('Punti insufficienti (race condition).', 402);
        }
        $stmtMoney->close();
    }

    // Aggiorna pity e garantito
    $stmtPity = $mysqli->prepare(
        'UPDATE utenti SET pity_standard=?, pity_evento=?, garantito_evento=? WHERE id=?'
    );
    $stmtPity->bind_param('iiii', $pityStandard, $pityEvento, $garantito, $userId);
    $stmtPity->execute();
    $stmtPity->close();

    $stmtInv->close();
    if ($stmtHistNull) $stmtHistNull->close();
    if ($stmtHistVal)  $stmtHistVal->close();

    $mysqli->commit();

    // ── MISSION TRACKING ─────────────────────────────────────────────────────
    // Eseguito DOPO il commit: il tracking non deve mai bloccare le pull.
    // Si itera su ogni singola pull del multi per aggiornare correttamente i contatori.
    try {
        $rarityRarePlus = ['raro', 'epico', 'leggendario', 'speciale', 'segreto', 'theone'];
        $rarityEpicPlus = ['epico', 'leggendario', 'speciale', 'segreto', 'theone'];

        foreach ($pulls as $pull) {
            $pullRarity = strtolower(trim($pull['personaggio']['rarità'] ?? ''));

            // Ogni pull = 1 apertura lootbox
            trackMissionProgress($mysqli, $userId, 'lootbox_open');

            if (in_array($pullRarity, $rarityRarePlus, true)) {
                trackMissionProgress($mysqli, $userId, 'get_rarity_rare');
            }
            if (in_array($pullRarity, $rarityEpicPlus, true)) {
                trackMissionProgress($mysqli, $userId, 'get_rarity_epic');
            }
        }
    } catch (Throwable $trackErr) {
        error_log('[MissionTracking gacha_multi_pull] ' . $trackErr->getMessage());
    }
    // ── /MISSION TRACKING ────────────────────────────────────────────────────

    // Leggi soldi aggiornati
    $stmtS = $mysqli->prepare('SELECT soldi FROM utenti WHERE id=? LIMIT 1');
    $stmtS->bind_param('i', $userId);
    $stmtS->execute();
    $soldiRimasti = (int)($stmtS->get_result()->fetch_assoc()['soldi'] ?? ($soldi - $costoTotale));
    $stmtS->close();

    echo json_encode([
        'status'        => 'success',
        'pulls'         => $pulls,
        'soldi_rimasti' => $soldiRimasti,
        'pity_standard' => $pityStandard,
        'pity_evento'   => $pityEvento,
        'garantito'     => (bool)$garantito,
        'costo_totale'  => $costoTotale,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (RuntimeException $e) {
    $mysqli->rollback();
    $code = $e->getCode();
    $http = match (true) {
        $code === 401 => 401,
        $code === 402 => 402,
        $code === 404 => 404,
        default => 500
    };
    http_response_code($http > 0 ? $http : 500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'code' => $code === 402 ? 'NO_POINTS' : 'SERVER_ERROR']);
} catch (Throwable $e) {
    $mysqli->rollback();
    error_log('[api_gacha_multi_pull] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore interno. Riprova.', 'code' => 'INTERNAL_ERROR']);
}
