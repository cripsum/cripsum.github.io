<?php
/**
 * api_gacha_pull.php
 * POST /api/api_gacha_pull
 *
 * Endpoint unico per le pull gacha.
 * Tutta la logica RNG, pity, 50/50 è ESCLUSIVAMENTE server-side.
 * Nessun RNG lato JS — nessun exploit possibile.
 *
 * Input JSON (o POST form):
 *   banner_id  string|int   "standard" oppure ID numerico del banner evento
 *   quantity   int          1  (10x per sviluppi futuri, ora accetta solo 1)
 *
 * Output JSON:
 *   status, personaggio, is_new, pity_attuale, garantito,
 *   vinto_50_50, costo_scalato, soldi_rimasti, tipo_banner
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ════════════════════════════════════════════════════════════════════════════
// CONFIGURAZIONE
// ════════════════════════════════════════════════════════════════════════════

const PULL_RATE_LIMIT_MS   = 800;   // ms minimi tra una pull e l'altra (anti-spam)
const PITY_STANDARD_SOFT   = 70;    // da qui aumenta % leggendario (standard)
const PITY_STANDARD_HARD   = 90;    // garantisce leggendario (standard)
const PITY_EVENTO_SOFT     = 65;    // da qui aumenta % segreto (evento)
const PITY_EVENTO_HARD     = 80;    // garantisce segreto → 50/50 (evento)

// Probabilità base (somma = 100)
const BASE_WEIGHTS = [
    'comune'      => 51.00,
    'raro'        => 28.00,
    'epico'       => 13.00,
    'leggendario' =>  5.99,
    'speciale'    =>  1.80,
    'segreto'     =>  0.20,
    'theone'      =>  0.01,
];

// Rarità "massime" che triggerano il 50/50 nei banner evento
const RARITY_TOP = ['segreto', 'theone'];

// ════════════════════════════════════════════════════════════════════════════
// VALIDAZIONI PRELIMINARI
// ════════════════════════════════════════════════════════════════════════════

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// Auth
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non autenticato', 'code' => 'NOT_LOGGED_IN']);
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ── Leggi body JSON o POST form ───────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
$input    = [];
if (!empty($rawInput)) {
    $input = json_decode($rawInput, true) ?? [];
}
// Fallback su $_POST
if (empty($input)) {
    $input = $_POST;
}

$rawBannerId = $input['banner_id'] ?? null;
$quantity    = (int) ($input['quantity'] ?? 1);

// Sanity
if ($rawBannerId === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'banner_id mancante', 'code' => 'MISSING_BANNER']);
    exit();
}
if ($quantity < 1 || $quantity > 1) {
    // Per ora solo pull 1x — 10x da implementare in futuro
    $quantity = 1;
}

// ── Rate limit anti-spam (sessione) ──────────────────────────────────────────
$now = microtime(true);
if (isset($_SESSION['gacha_last_pull_ts'])) {
    $elapsed = ($now - (float) $_SESSION['gacha_last_pull_ts']) * 1000; // ms
    if ($elapsed < PULL_RATE_LIMIT_MS) {
        http_response_code(429);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Aspetta un momento prima di pullare ancora!',
            'code'    => 'RATE_LIMIT',
            'wait_ms' => (int) ceil(PULL_RATE_LIMIT_MS - $elapsed),
        ]);
        exit();
    }
}
$_SESSION['gacha_last_pull_ts'] = $now;

// ════════════════════════════════════════════════════════════════════════════
// IDENTIFICA BANNER
// ════════════════════════════════════════════════════════════════════════════

$bannerType     = null;  // 'standard' | 'evento'
$bannerData     = null;  // dati da banner_eventi (solo se evento)
$bannerId       = null;  // stringa "standard" o int

if ($rawBannerId === 'standard') {
    $bannerType = 'standard';
    $bannerId   = 'standard';
} elseif (is_numeric($rawBannerId) && (int) $rawBannerId > 0) {
    $bannerIdInt = (int) $rawBannerId;
    $nowDt       = date('Y-m-d H:i:s');

    $stmtBanner = $mysqli->prepare(
        'SELECT id, nome, id_personaggio_rateup, costo_punti, data_fine
         FROM banner_eventi
         WHERE id = ?
           AND attivo = 1
           AND (data_inizio IS NULL OR data_inizio <= ?)
           AND (data_fine   IS NULL OR data_fine   >= ?)
         LIMIT 1'
    );
    if (!$stmtBanner) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Errore DB banner', 'code' => 'DB_ERR']);
        exit();
    }
    $stmtBanner->bind_param('iss', $bannerIdInt, $nowDt, $nowDt);
    $stmtBanner->execute();
    $resB = $stmtBanner->get_result();
    $bannerData = $resB->fetch_assoc();
    $stmtBanner->close();

    if (!$bannerData) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Banner non trovato o scaduto', 'code' => 'BANNER_NOT_FOUND']);
        exit();
    }
    $bannerType = 'evento';
    $bannerId   = (string) $bannerIdInt;
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'banner_id non valido', 'code' => 'INVALID_BANNER']);
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// CARICA POOL PERSONAGGI (prima della transazione, read-only)
// Nessun ORDER BY RAND() — usiamo array PHP + weighted random
// ════════════════════════════════════════════════════════════════════════════

/**
 * Carica tutti i personaggi del pool standard raggruppati per rarità.
 * Condizione: in_pool_standard = 1 (include sia standard che potenzialmente evento)
 * I personaggi con pool_evento = 1 e in_pool_standard = 0 NON escono dal banner standard.
 */
function loadStandardPool(mysqli $db): array {
    $pool = [
        'comune'      => [],
        'raro'        => [],
        'epico'       => [],
        'leggendario' => [],
        'speciale'    => [],
        'segreto'     => [],
        'theone'      => [],
    ];

    $stmt = $db->prepare(
        'SELECT id, nome, `rarità`, img_url, audio_url, video_url, descrizione, caratteristiche
         FROM personaggi
         WHERE in_pool_standard = 1'
    );
    if (!$stmt) return $pool;
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $r = strtolower(trim($row['rarità']));
        if (isset($pool[$r])) {
            $pool[$r][] = $row;
        }
    }
    $stmt->close();
    return $pool;
}

$standardPool = loadStandardPool($mysqli);

// ════════════════════════════════════════════════════════════════════════════
// FUNZIONI RNG SERVER-SIDE
// ════════════════════════════════════════════════════════════════════════════

/**
 * Estrae una rarità con pesi, applicando soft/hard pity.
 * Tutto server-side, nessun JS coinvolto.
 */
function selectRarity(string $bannerType, int $pity, array $adminOverride = []): string {
    // Admin override (debug only, ignorabile in prod)
    if (!empty($adminOverride['forza_rarità'])) {
        return $adminOverride['forza_rarità'];
    }

    $weights = BASE_WEIGHTS;

    if ($bannerType === 'standard') {
        // Soft pity: da pull PITY_STANDARD_SOFT la % leggendario sale del 10% per pull
        if ($pity >= PITY_STANDARD_SOFT) {
            $bonus = ($pity - PITY_STANDARD_SOFT + 1) * 10.0;
            $weights['leggendario'] += $bonus;
        }
        // Hard pity: garantisce leggendario
        if ($pity >= PITY_STANDARD_HARD) {
            return 'leggendario';
        }
    } elseif ($bannerType === 'evento') {
        // Soft pity: da pull PITY_EVENTO_SOFT la % segreto sale del 6% per pull
        if ($pity >= PITY_EVENTO_SOFT) {
            $bonus = ($pity - PITY_EVENTO_SOFT + 1) * 6.0;
            $weights['segreto']  += $bonus;
            $weights['theone']   += $bonus * 0.05;
        }
        // Hard pity: garantisce top rarity → 50/50
        if ($pity >= PITY_EVENTO_HARD) {
            // 90% segreto, 10% theone al hard pity
            return (mt_rand(1, 10) === 1) ? 'theone' : 'segreto';
        }
    }

    // Weighted random puro PHP — NO ORDER BY RAND()
    $total = array_sum($weights);
    // mt_rand garantisce migliore distribuzione di rand()
    $rand  = (mt_rand(0, PHP_INT_MAX - 1) / PHP_INT_MAX) * $total;

    foreach ($weights as $rarity => $weight) {
        $rand -= $weight;
        if ($rand <= 0) {
            return $rarity;
        }
    }

    return 'comune'; // fallback di sicurezza
}

/**
 * Sceglie un personaggio casuale da una pool di rarità.
 * Nessun ORDER BY RAND() — shuffle PHP sull'array pre-caricato.
 */
function pickFromPool(array $poolByRarity, string $rarity): ?array {
    $candidates = $poolByRarity[$rarity] ?? [];
    if (empty($candidates)) {
        // Fallback alla rarità più bassa disponibile
        foreach (['comune', 'raro', 'epico', 'leggendario'] as $fallback) {
            if (!empty($poolByRarity[$fallback])) {
                $candidates = $poolByRarity[$fallback];
                break;
            }
        }
    }
    if (empty($candidates)) return null;

    $idx = mt_rand(0, count($candidates) - 1);
    return $candidates[$idx];
}

/**
 * Carica un singolo personaggio per ID.
 * Usato per il rateup del banner evento.
 */
function loadCharacterById(mysqli $db, int $charId): ?array {
    $stmt = $db->prepare(
        'SELECT id, nome, `rarità`, img_url, audio_url, video_url, descrizione, caratteristiche
         FROM personaggi
         WHERE id = ?
         LIMIT 1'
    );
    if (!$stmt) return null;
    $stmt->bind_param('i', $charId);
    $stmt->execute();
    $res = $stmt->get_result();
    $char = $res->fetch_assoc();
    $stmt->close();
    return $char ?: null;
}

// ════════════════════════════════════════════════════════════════════════════
// TRANSAZIONE PRINCIPALE
// ════════════════════════════════════════════════════════════════════════════

$mysqli->begin_transaction();

try {

    // ── [1] Leggi e blocca riga utente ───────────────────────────────────────
    $stmtLock = $mysqli->prepare(
        'SELECT soldi, pity_standard, pity_evento, garantito_evento
         FROM utenti
         WHERE id = ?
         LIMIT 1
         FOR UPDATE'
    );
    if (!$stmtLock) throw new RuntimeException('Prepare lock utente fallito: ' . $mysqli->error);
    $stmtLock->bind_param('i', $userId);
    $stmtLock->execute();
    $resLock = $stmtLock->get_result();
    $user = $resLock->fetch_assoc();
    $stmtLock->close();

    if (!$user) throw new RuntimeException('Utente non trovato in transazione', 404);

    $soldi         = (int) $user['soldi'];
    $pityStandard  = (int) $user['pity_standard'];
    $pityEvento    = (int) $user['pity_evento'];
    $garantito     = (int) $user['garantito_evento'];

    // ── [2] Verifica e scala punti ────────────────────────────────────────────
    $costo = 0;
    if ($bannerType === 'evento') {
        $costo = (int) $bannerData['costo_punti'];
        if ($soldi < $costo) {
            throw new RuntimeException(
                "Punti insufficienti! Hai {$soldi} punti, ne servono {$costo}.",
                402
            );
        }
    }
    // Standard: gratuito (costo = 0)
    // Eventuale cooldown giornaliero da implementare qui se necessario

    // ── [3] Seleziona rarità con pity ─────────────────────────────────────────
    $pityCorrente = ($bannerType === 'standard') ? $pityStandard : $pityEvento;

    // Admin override (solo owner/admin, lato server — non trust client)
    $adminOverride = [];
    $ruolo = $_SESSION['ruolo'] ?? 'utente';
    if (in_array($ruolo, ['admin', 'owner'], true)) {
        // Parametro opzionale solo per admin: force_rarity
        $forceRarity = $input['force_rarity'] ?? null;
        $validRarities = ['comune', 'raro', 'epico', 'leggendario', 'speciale', 'segreto', 'theone'];
        if ($forceRarity && in_array($forceRarity, $validRarities, true)) {
            $adminOverride['forza_rarità'] = $forceRarity;
        }
    }

    $rarità = selectRarity($bannerType, $pityCorrente, $adminOverride);

    // ── [4] Aggiorna pity ─────────────────────────────────────────────────────
    $isTopRarity = in_array($rarità, RARITY_TOP, true);
    $vinto50_50  = null;  // null = non applicabile

    if ($bannerType === 'standard') {
        $pityStandard++;
        // Reset pity standard se ottenuto leggendario o superiore
        if (in_array($rarità, ['leggendario', 'speciale', 'segreto', 'theone'], true)) {
            $pityStandard = 0;
        }
    } else {
        $pityEvento++;
    }

    // ── [5] Selezione personaggio ─────────────────────────────────────────────
    $personaggio = null;

    if ($bannerType === 'evento' && $isTopRarity) {
        // ── GESTIONE 50/50 ────────────────────────────────────────────────────
        $rateupId = (int) $bannerData['id_personaggio_rateup'];

        if ($garantito === 1) {
            // Caso 3: Garantito attivo → win automatico
            $personaggio    = loadCharacterById($mysqli, $rateupId);
            $garantito      = 0;
            $vinto50_50     = 1;
        } else {
            // Caso 1 o 2: coin flip 50/50
            $flip = mt_rand(0, 1);
            if ($flip === 1) {
                // Vittoria 50/50
                $personaggio = loadCharacterById($mysqli, $rateupId);
                $garantito   = 0;
                $vinto50_50  = 1;
            } else {
                // Sconfitta 50/50 → segreto random dalla pool standard
                $personaggio = pickFromPool($standardPool, 'segreto');
                if (!$personaggio) {
                    // Fallback: prendi un leggendario se non ci sono segreti
                    $personaggio = pickFromPool($standardPool, 'leggendario');
                }
                $garantito   = 1;
                $vinto50_50  = 0;
            }
        }

        // Reset pity evento dopo trigger top rarity
        $pityEvento = 0;

    } else {
        // Pull normale (standard o evento non-top)
        $personaggio = pickFromPool($standardPool, $rarità);
    }

    if (!$personaggio) {
        throw new RuntimeException('Nessun personaggio trovato per questa rarità. Contatta un admin.', 500);
    }

    $personaggioId = (int) $personaggio['id'];

    // ── [6] Scala punti ───────────────────────────────────────────────────────
    if ($costo > 0) {
        $stmtMoney = $mysqli->prepare(
            'UPDATE utenti
             SET soldi = soldi - ?
             WHERE id = ? AND soldi >= ?'
        );
        if (!$stmtMoney) throw new RuntimeException('Prepare money update fallito');
        $stmtMoney->bind_param('iii', $costo, $userId, $costo);
        $stmtMoney->execute();

        if ($stmtMoney->affected_rows === 0) {
            $stmtMoney->close();
            throw new RuntimeException('Punti insufficienti (race condition intercettata).', 402);
        }
        $stmtMoney->close();
    }

    // ── [7] Aggiorna pity e garantito in utenti ───────────────────────────────
    $stmtPity = $mysqli->prepare(
        'UPDATE utenti
         SET pity_standard    = ?,
             pity_evento      = ?,
             garantito_evento = ?
         WHERE id = ?'
    );
    if (!$stmtPity) throw new RuntimeException('Prepare pity update fallito');
    $stmtPity->bind_param('iiii', $pityStandard, $pityEvento, $garantito, $userId);
    $stmtPity->execute();
    $stmtPity->close();

    // ── [8] Upsert inventario ─────────────────────────────────────────────────
    $stmtInv = $mysqli->prepare(
        'INSERT INTO utenti_personaggi (utente_id, personaggio_id, quantità, data)
         VALUES (?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE quantità = quantità + 1'
    );
    if (!$stmtInv) throw new RuntimeException('Prepare inventario fallito');
    $stmtInv->bind_param('ii', $userId, $personaggioId);
    $stmtInv->execute();

    // affected_rows: 1 = nuovo personaggio, 2 = copia aggiunta
    $isNew = ($stmtInv->affected_rows === 1);
    $stmtInv->close();

    // ── [9] Salva storico pull ────────────────────────────────────────────────
    $pitySnapshot  = $pityCorrente; // valore PRIMA di questa pull
    $vinto50_50Int = ($vinto50_50 !== null) ? (int) $vinto50_50 : null;
    $isNewInt      = $isNew ? 1 : 0;

    $stmtHistory = $mysqli->prepare(
        'INSERT INTO gacha_pull_history
           (utente_id, banner_id, personaggio_id, `rarità`, pity_al_momento, esito_50_50, is_new)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmtHistory) throw new RuntimeException('Prepare history fallito');

    if ($vinto50_50Int !== null) {
        $stmtHistory->bind_param('isisiii', $userId, $bannerId, $personaggioId, $rarità, $pitySnapshot, $vinto50_50Int, $isNewInt);
    } else {
        // bind con null per esito_50_50
        $stmtHistory->bind_param('isisiis',
            $userId, $bannerId, $personaggioId, $rarità, $pitySnapshot, $vinto50_50Int, $isNewInt
        );
        // MySQLi non gestisce NULL con bind_param 'i' direttamente, usiamo metodo alternativo
        $stmtHistory->close();

        $stmtHistory = $mysqli->prepare(
            'INSERT INTO gacha_pull_history
               (utente_id, banner_id, personaggio_id, `rarità`, pity_al_momento, esito_50_50, is_new)
             VALUES (?, ?, ?, ?, ?, NULL, ?)'
        );
        if (!$stmtHistory) throw new RuntimeException('Prepare history (null) fallito');
        $stmtHistory->bind_param('isisii', $userId, $bannerId, $personaggioId, $rarità, $pitySnapshot, $isNewInt);
    }

    $stmtHistory->execute();
    $stmtHistory->close();

    // ── COMMIT ────────────────────────────────────────────────────────────────
    $mysqli->commit();

    // ── Leggi soldi aggiornati ────────────────────────────────────────────────
    $stmtSoldi = $mysqli->prepare('SELECT soldi FROM utenti WHERE id = ? LIMIT 1');
    $stmtSoldi->bind_param('i', $userId);
    $stmtSoldi->execute();
    $resSoldi = $stmtSoldi->get_result();
    $soldiRow = $resSoldi->fetch_assoc();
    $stmtSoldi->close();
    $soldiRimasti = (int) ($soldiRow['soldi'] ?? ($soldi - $costo));

    // ── Response ─────────────────────────────────────────────────────────────
    echo json_encode([
        'status'         => 'success',
        'personaggio'    => [
            'id'              => (int) $personaggio['id'],
            'nome'            => $personaggio['nome'],
            'rarità'          => $personaggio['rarità'],
            'img_url'         => $personaggio['img_url'],
            'audio_url'       => $personaggio['audio_url'],
            'video_url'       => $personaggio['video_url'],
            'descrizione'     => $personaggio['descrizione'],
            'caratteristiche' => $personaggio['caratteristiche'],
        ],
        'is_new'         => $isNew,
        'pity_standard'  => $pityStandard,
        'pity_evento'    => $pityEvento,
        'garantito'      => (bool) $garantito,
        'vinto_50_50'    => $vinto50_50,
        'costo_scalato'  => $costo,
        'soldi_rimasti'  => $soldiRimasti,
        'tipo_banner'    => $bannerType,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (RuntimeException $e) {
    $mysqli->rollback();

    $code = $e->getCode();
    $httpCode = match(true) {
        $code === 401 => 401,
        $code === 402 => 402,
        $code === 404 => 404,
        $code === 429 => 429,
        default       => 500,
    };

    http_response_code($httpCode > 0 ? $httpCode : 500);

    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
        'code'    => match($code) {
            401 => 'NOT_LOGGED_IN',
            402 => 'NO_POINTS',
            404 => 'NOT_FOUND',
            default => 'SERVER_ERROR',
        },
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    $mysqli->rollback();

    error_log('[api_gacha_pull] Errore inatteso: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Errore interno del server. Riprova.',
        'code'    => 'INTERNAL_ERROR',
    ]);
}
