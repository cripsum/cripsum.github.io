<?php

/**
 * api_gacha_history.php
 * GET /api/api_gacha_history?banner_id=standard&limit=60
 *
 * Cronologia delle pull dell'utente su un banner, con qualche statistica:
 * quante pull, quanti segreti, come sono andati i 50/50, pity medio a cui
 * sono usciti i segreti.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gacha/schema.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non autenticato']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$bannerId = $_GET['banner_id'] ?? null;
$limit = max(1, min(200, (int)($_GET['limit'] ?? 60)));

if ($bannerId === null || ($bannerId !== 'standard' && (!ctype_digit((string)$bannerId) || (int)$bannerId <= 0))) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'banner_id non valido']);
    exit();
}

$v2 = gacha_schema($mysqli)['history_v2'];
$extra = $v2 ? ', gph.featured, gph.gratuita, gph.costo' : ', NULL AS featured, 0 AS gratuita, 0 AS costo';

$stmt = $mysqli->prepare(
    "SELECT COUNT(*) AS tot,
            COALESCE(SUM(LOWER(`rarità`) IN ('segreto', 'theone')), 0) AS segreti,
            COALESCE(SUM(esito_50_50 = 1), 0) AS vinti,
            COALESCE(SUM(esito_50_50 = 0), 0) AS persi,
            AVG(CASE WHEN LOWER(`rarità`) IN ('segreto', 'theone') THEN pity_al_momento + 1 END) AS pity_medio
     FROM gacha_pull_history WHERE utente_id = ? AND banner_id = ?"
);
$stmt->bind_param('is', $userId, $bannerId);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$stmt = $mysqli->prepare(
    "SELECT gph.personaggio_id, gph.`rarità`, gph.pity_al_momento, gph.esito_50_50, gph.is_new, gph.created_at,
            p.nome, p.img_url $extra
     FROM gacha_pull_history gph
     INNER JOIN personaggi p ON p.id = gph.personaggio_id
     WHERE gph.utente_id = ? AND gph.banner_id = ?
     ORDER BY gph.id DESC
     LIMIT ?"
);
$stmt->bind_param('isi', $userId, $bannerId, $limit);
$stmt->execute();
$res = $stmt->get_result();

$pulls = [];
while ($row = $res->fetch_assoc()) {
    $pulls[] = [
        'personaggio_id' => (int)$row['personaggio_id'],
        'nome' => $row['nome'],
        'img_url' => $row['img_url'],
        'rarità' => $row['rarità'],
        'pity_al_momento' => (int)$row['pity_al_momento'],
        'esito_50_50' => $row['esito_50_50'] !== null ? (int)$row['esito_50_50'] : null,
        'is_new' => (bool)$row['is_new'],
        'featured' => $row['featured'] !== null ? (bool)$row['featured'] : null,
        'gratuita' => (bool)$row['gratuita'],
        'costo' => (int)$row['costo'],
        'created_at' => $row['created_at'],
    ];
}
$stmt->close();

echo json_encode([
    'status' => 'success',
    'banner_id' => $bannerId,
    'total' => (int)($totals['tot'] ?? 0),
    'stats' => [
        'segreti' => (int)($totals['segreti'] ?? 0),
        'vinti_50_50' => (int)($totals['vinti'] ?? 0),
        'persi_50_50' => (int)($totals['persi'] ?? 0),
        'pity_medio_segreti' => $totals['pity_medio'] !== null ? round((float)$totals['pity_medio'], 1) : null,
    ],
    'pulls' => $pulls,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
