<?php
/**
 * Il catalogo pubblico degli achievement.
 *
 * Solo le colonne che servono a mostrarli: niente metriche né soglie. I
 * segreti escono solo per chi li ha già sbloccati, quelli tolti dalla pagina
 * non escono. La pagina degli achievement usa api/achievements/overview.php;
 * questo resta per chi leggeva già da qui.
 *
 * Endpoint : GET /api/get_all_achievement.php
 */
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/achievements.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
cripsum_release_session();

$achievements = [];

try {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->set_charset('utf8mb4');
    }

    $unlocked = $userId > 0 ? ach_user_unlocked($mysqli, $userId) : [];

    foreach (ach_catalog($mysqli) as $id => $entry) {
        if (!$entry['attivo'] || ($entry['segreto'] && !isset($unlocked[$id]))) {
            continue;
        }
        $achievements[] = [
            'id'             => $id,
            'nome'           => $entry['nome'],
            'nome_en'        => $entry['nome_en'],
            'descrizione'    => $entry['descrizione'],
            'descrizione_en' => $entry['descrizione_en'],
            'punti'          => $entry['punti'],
            'img_url'        => $entry['img_url'],
            'categoria'      => $entry['categoria'],
            'livello'        => $entry['livello'],
        ];
    }
} catch (Throwable $e) {
    error_log('get_all_achievement failed: ' . $e->getMessage());
}

echo json_encode($achievements, JSON_UNESCAPED_UNICODE);
