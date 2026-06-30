<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';
require_once __DIR__ . '/../../includes/gacha_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$apiKey = $_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '';
if (empty($apiKey) || !hash_equals((string)CRIPSUM_BOT_API_KEY, (string)$apiKey)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

if (!defined('GACHA_STANDARD_COST')) define('GACHA_STANDARD_COST', 0);
if (!defined('GACHA_STANDARD_PITY_SOFT')) define('GACHA_STANDARD_PITY_SOFT', 70);
if (!defined('GACHA_STANDARD_PITY_HARD')) define('GACHA_STANDARD_PITY_HARD', 90);
if (!defined('GACHA_EVENTO_PITY_SOFT')) define('GACHA_EVENTO_PITY_SOFT', 65);
if (!defined('GACHA_EVENTO_PITY_HARD')) define('GACHA_EVENTO_PITY_HARD', 80);

$standard = [
    'tipo' => 'standard',
    'id' => 'standard',
    'nome' => 'Banner Standard',
    'descrizione' => 'Il banner classico. Tira per ottenere personaggi di ogni rarità.',
    'costo' => GACHA_STANDARD_COST,
    'pity_soft' => GACHA_STANDARD_PITY_SOFT,
    'pity_hard' => GACHA_STANDARD_PITY_HARD,
    'banner_img_url' => null,
    'personaggio_rateup' => null,
    'data_inizio' => null,
    'data_fine' => null,
    'attivo' => true,
];

$now = date('Y-m-d H:i:s');
$pCols = gacha_character_columns($mysqli);
$beCols = gacha_event_columns($mysqli);

$requiredEventColumns = ['slug', 'name', 'description', 'rateup', 'image', 'cost', 'active', 'starts', 'ends'];
foreach ($requiredEventColumns as $key) {
    if (empty($beCols[$key])) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Banner schema is not ready.']);
        exit;
    }
}

$requiredCharacterColumns = ['name', 'description', 'rarity', 'image'];
foreach ($requiredCharacterColumns as $key) {
    if (empty($pCols[$key])) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Character schema is not ready.']);
        exit;
    }
}

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
        p.{$pCols['name']} AS rateup_nome,
        p.{$pCols['description']} AS rateup_descrizione,
        p.{$pCols['rarity']} AS rateup_rarità,
        p.{$pCols['image']} AS rateup_img_url
     FROM banner_eventi be
     INNER JOIN personaggi p ON p.id = be.{$beCols['rateup']}
     WHERE be.{$beCols['active']} = 1
       AND (be.{$beCols['starts']} IS NULL OR be.{$beCols['starts']} <= ?)
       AND (be.{$beCols['ends']} IS NULL OR be.{$beCols['ends']} >= ?)
     ORDER BY be.id ASC"
);

if (!$stmtBanner) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to prepare banner query.']);
    exit;
}

$stmtBanner->bind_param('ss', $now, $now);
$stmtBanner->execute();
$resBanner = $stmtBanner->get_result();

$eventiAttivi = [];
while ($row = $resBanner->fetch_assoc()) {
    $eventiAttivi[] = [
        'tipo' => 'evento',
        'id' => (int)$row['id'],
        'slug' => $row['slug'],
        'nome' => $row['nome'],
        'descrizione' => $row['descrizione'],
        'banner_img_url' => $row['banner_img_url'],
        'costo' => (int)$row['costo_punti'],
        'pity_soft' => GACHA_EVENTO_PITY_SOFT,
        'pity_hard' => GACHA_EVENTO_PITY_HARD,
        'data_inizio' => $row['data_inizio'],
        'data_fine' => $row['data_fine'],
        'attivo' => true,
        'personaggio_rateup' => [
            'id' => (int)$row['id_personaggio_rateup'],
            'nome' => $row['rateup_nome'],
            'descrizione' => $row['rateup_descrizione'],
            'rarità' => $row['rateup_rarità'],
            'img_url' => $row['rateup_img_url'],
        ],
    ];
}
$stmtBanner->close();

echo json_encode([
    'status' => 'success',
    'standard' => $standard,
    'eventi' => $eventiAttivi,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
