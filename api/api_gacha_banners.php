<?php
/**
 * api_gacha_banners.php
 * GET /api/api_gacha_banners
 *
 * Restituisce tutti i banner attivi + dati pity dell'utente loggato.
 * Non esegue pull. Solo lettura.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gacha_helpers.php';


header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Solo GET ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// ── Auth ─────────────────────────────────────────────────────────────────────
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non autenticato', 'code' => 'NOT_LOGGED_IN']);
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ── Leggi dati utente (pity + soldi) ─────────────────────────────────────────
$stmtUser = $mysqli->prepare(
    'SELECT soldi, pity_standard, pity_evento, garantito_evento
     FROM utenti
     WHERE id = ?
     LIMIT 1'
);
if (!$stmtUser) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore DB', 'code' => 'DB_PREPARE']);
    exit();
}
$stmtUser->bind_param('i', $userId);
$stmtUser->execute();
$resUser = $stmtUser->get_result();
$userData = $resUser->fetch_assoc();
$stmtUser->close();

if (!$userData) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Utente non trovato', 'code' => 'USER_NOT_FOUND']);
    exit();
}

$pityStandard  = (int) $userData['pity_standard'];
$pityEvento    = (int) $userData['pity_evento'];
$garantito     = (int) $userData['garantito_evento'];
$soldi         = (int) $userData['soldi'];

// ── Banner Standard ──────────────────────────────────────────────────────────
// Configurazione inline (modifica qui se vuoi costo o cooldown)
define('GACHA_STANDARD_COST',        0);        // 0 = gratuito
define('GACHA_STANDARD_PITY_SOFT',  70);        // pull da cui la % leggendario sale
define('GACHA_STANDARD_PITY_HARD',  90);        // pull che garantisce leggendario
define('GACHA_EVENTO_PITY_SOFT',    65);        // pull da cui la % segreto sale
define('GACHA_EVENTO_PITY_HARD',    80);        // pull che garantisce segreto (→ 50/50)

$standard = [
    'tipo'              => 'standard',
    'id'                => 'standard',
    'nome'              => 'Banner Standard',
    'descrizione'       => 'Il banner classico. Tira per ottenere personaggi di ogni rarità.',
    'costo'             => GACHA_STANDARD_COST,
    'pity_utente'       => $pityStandard,
    'pity_soft'         => GACHA_STANDARD_PITY_SOFT,
    'pity_hard'         => GACHA_STANDARD_PITY_HARD,
    'banner_img_url'    => null,
    'personaggio_rateup'=> null,
    'data_fine'         => null,
    'attivo'            => true,
];

// ── Banner Evento attivi ──────────────────────────────────────────────────────
$now = date('Y-m-d H:i:s');

$pCols = gacha_character_columns($mysqli);
$beCols = gacha_event_columns($mysqli);

$stmtBanner = $mysqli->prepare(
    "SELECT
        be.id,
        be.{$beCols['slug']} AS slug,
        be.{$beCols['name']} AS nome,
        be.{$beCols['description']} AS descrizione,
        be.{$beCols['rateup']} AS id_personaggio_rateup,
        be.{$beCols['image']} AS banner_img_url,
        be.{$beCols['cost']} AS costo_punti,
        be.{$beCols['starts']} AS data_inizio,
        be.{$beCols['ends']} AS data_fine,
        p.{$pCols['name']}         AS rateup_nome,
        p.{$pCols['description']}  AS rateup_descrizione,
        p.{$pCols['rarity']}       AS rateup_rarità,
        p.{$pCols['image']}      AS rateup_img_url,
        p.{$pCols['video']}    AS rateup_video_url,
        p.{$pCols['features']} AS rateup_caratteristiche
     FROM banner_eventi be
     INNER JOIN personaggi p ON p.id = be.{$beCols['rateup']}
     WHERE be.{$beCols['active']} = 1
       AND (be.{$beCols['starts']} IS NULL OR be.{$beCols['starts']} <= ?)
       AND (be.{$beCols['ends']}   IS NULL OR be.{$beCols['ends']}   >= ?)
     ORDER BY be.id ASC"
);
if (!$stmtBanner) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore DB banner', 'code' => 'DB_PREPARE_BANNER']);
    exit();
}
$stmtBanner->bind_param('ss', $now, $now);
$stmtBanner->execute();
$resBanner = $stmtBanner->get_result();

$eventiAttivi = [];
while ($row = $resBanner->fetch_assoc()) {
    $eventiAttivi[] = [
        'tipo'               => 'evento',
        'id'                 => (int) $row['id'],
        'slug'               => $row['slug'],
        'nome'               => $row['nome'],
        'descrizione'        => $row['descrizione'],
        'banner_img_url'     => $row['banner_img_url'],
        'costo'              => (int) $row['costo_punti'],
        'pity_condiviso'     => $pityEvento,  // condiviso tra tutti gli eventi
        'garantito_attivo'   => (bool) $garantito,
        'pity_soft'          => GACHA_EVENTO_PITY_SOFT,
        'pity_hard'          => GACHA_EVENTO_PITY_HARD,
        'data_inizio'        => $row['data_inizio'],
        'data_fine'          => $row['data_fine'],
        'attivo'             => true,
        'personaggio_rateup' => [
            'id'             => (int) $row['id_personaggio_rateup'],
            'nome'           => $row['rateup_nome'],
            'descrizione'    => $row['rateup_descrizione'],
            'rarità'         => $row['rateup_rarità'],
            'img_url'        => $row['rateup_img_url'],
            'video_url'      => $row['rateup_video_url'],
            'caratteristiche'=> $row['rateup_caratteristiche'],
        ],
    ];
}
$stmtBanner->close();

// ── Response finale ───────────────────────────────────────────────────────────
echo json_encode([
    'status'        => 'success',
    'soldi'         => $soldi,
    'pity_standard' => $pityStandard,
    'pity_evento'   => $pityEvento,
    'garantito'     => (bool) $garantito,
    'standard'      => $standard,
    'eventi'        => $eventiAttivi,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
