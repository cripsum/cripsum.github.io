<?php
/**
 * api_gacha_history.php
 * GET /api/api_gacha_history?banner_id=standard&limit=60
 *
 * Restituisce la cronologia pull dell'utente per un banner specifico.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non autenticato']);
    exit();
}

$userId   = (int) $_SESSION['user_id'];
$bannerId = $_GET['banner_id'] ?? null;
$limit    = max(1, min(200, (int) ($_GET['limit'] ?? 60)));

if ($bannerId === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'banner_id mancante']);
    exit();
}

// Sanitizza banner_id
if ($bannerId !== 'standard' && (!is_numeric($bannerId) || (int)$bannerId <= 0)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'banner_id non valido']);
    exit();
}

// Conta totale pull su questo banner
$stmtCount = $mysqli->prepare(
    'SELECT COUNT(*) AS tot FROM gacha_pull_history WHERE utente_id = ? AND banner_id = ?'
);
$stmtCount->bind_param('is', $userId, $bannerId);
$stmtCount->execute();
$total = (int) $stmtCount->get_result()->fetch_assoc()['tot'];
$stmtCount->close();

// Fetch ultimi N pull con join personaggio per il nome
$stmt = $mysqli->prepare(
    'SELECT
        gph.personaggio_id,
        gph.`rarità`,
        gph.pity_al_momento,
        gph.esito_50_50,
        gph.is_new,
        gph.created_at,
        p.nome,
        p.img_url
     FROM gacha_pull_history gph
     INNER JOIN personaggi p ON p.id = gph.personaggio_id
     WHERE gph.utente_id = ? AND gph.banner_id = ?
     ORDER BY gph.id DESC
     LIMIT ?'
);
$stmt->bind_param('isi', $userId, $bannerId, $limit);
$stmt->execute();
$res = $stmt->get_result();

$pulls = [];
while ($row = $res->fetch_assoc()) {
    $pulls[] = [
        'nome'           => $row['nome'],
        'img_url'        => $row['img_url'],
        'rarità'         => $row['rarità'],
        'pity_al_momento'=> (int) $row['pity_al_momento'],
        'esito_50_50'    => $row['esito_50_50'] !== null ? (int) $row['esito_50_50'] : null,
        'is_new'         => (bool) $row['is_new'],
        'created_at'     => $row['created_at'],
    ];
}
$stmt->close();

echo json_encode([
    'status' => 'success',
    'banner_id' => $bannerId,
    'total'  => $total,
    'pulls'  => $pulls,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
