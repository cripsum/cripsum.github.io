<?php
/**
 * Un achievement, per il popup di sblocco.
 *
 * Risponde con una lista di una riga, come ha sempre fatto. Di un achievement
 * segreto risponde solo a chi l'ha già sbloccato: per tutti gli altri non
 * esiste.
 *
 * Endpoint : GET /api/get_achievement.php?achievement_id=N
 */
header("Access-Control-Allow-Origin: https://cripsum.com");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/achievements.php';

header('Content-Type: application/json; charset=utf-8');

$achievementId = (int)($_GET['achievement_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
cripsum_release_session();

$achievement = [];

try {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->set_charset('utf8mb4');
    }

    $entry = ach_catalog($mysqli)[$achievementId] ?? null;

    if ($entry !== null && $entry['segreto']) {
        $owned = $userId > 0 && ach_scalar(
            $mysqli,
            'SELECT COUNT(*) FROM utenti_achievement WHERE utente_id = ? AND achievement_id = ?',
            'ii',
            [$userId, $achievementId]
        );
        if (!$owned) {
            $entry = null;
        }
    }

    if ($entry !== null) {
        $achievement[] = [
            'id'             => $entry['id'],
            'nome'           => $entry['nome'],
            'nome_en'        => $entry['nome_en'],
            'descrizione'    => $entry['descrizione'],
            'descrizione_en' => $entry['descrizione_en'],
            'punti'          => $entry['punti'],
            'img_url'        => ach_image_url($entry['img_url']),
            'livello'        => $entry['livello'],
            'ricompensa'     => $entry['ricompensa'],
        ];
    }
} catch (Throwable $e) {
    error_log('get_achievement failed: ' . $e->getMessage());
}

echo json_encode($achievement, JSON_UNESCAPED_UNICODE);
