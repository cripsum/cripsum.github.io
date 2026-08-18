<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/session_init.php';

header('Content-Type: application/json; charset=utf-8');

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

try {
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new Exception('Connessione al database non disponibile.');
    }

    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;

    // Fetch top scores excluding banned users
    $query = "
        SELECT 
            sl.utente_id,
            sl.best_time_ms,
            sl.map_slug,
            sl.updated_at,
            u.username,
            u.display_name,
            COALESCE(u.is_premium, 0) AS is_premium,
            u.discord_avatar,
            u.discord_use_avatar,
            u.discord_id
        FROM subway_leaderboard sl
        JOIN utenti u ON sl.utente_id = u.id
        WHERE sl.best_time_ms > 0
          AND (u.isBannato IS NULL OR u.isBannato = 0)
        ORDER BY sl.best_time_ms DESC, sl.updated_at ASC
        LIMIT ?
    ";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $leaderboard = [];
    $position = 1;

    while ($row = $result->fetch_assoc()) {
        $userId = (int)$row['utente_id'];
        $displayName = !empty($row['display_name']) ? $row['display_name'] : $row['username'];
        
        $avatarUrl = "/includes/get_pfp.php?id=" . $userId;
        if (!empty($row['discord_use_avatar']) && !empty($row['discord_avatar']) && !empty($row['discord_id'])) {
            $avatarUrl = "https://cdn.discordapp.com/avatars/" . urlencode((string)$row['discord_id']) . "/" . urlencode((string)$row['discord_avatar']) . ".png";
        }

        $leaderboard[] = [
            'rank' => $position++,
            'utente_id' => $userId,
            'username' => htmlspecialchars($row['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'display_name' => htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'is_premium' => (int)$row['is_premium'] === 1,
            'best_time_ms' => (int)$row['best_time_ms'],
            'map_slug' => htmlspecialchars($row['map_slug'] ?? 'london', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'updated_at' => $row['updated_at'],
            'avatar_url' => $avatarUrl
        ];
    }
    $stmt->close();

    // Check logged in user record
    $userRecord = null;
    if (isLoggedIn()) {
        $currentUserId = (int)$_SESSION['user_id'];
        $userStmt = $mysqli->prepare("
            SELECT sl.best_time_ms, sl.map_slug, sl.updated_at
            FROM subway_leaderboard sl
            WHERE sl.utente_id = ?
            LIMIT 1
        ");
        $userStmt->bind_param("i", $currentUserId);
        $userStmt->execute();
        $userRes = $userStmt->get_result();
        $userData = $userRes->fetch_assoc();
        $userStmt->close();

        if ($userData && (int)$userData['best_time_ms'] > 0) {
            $userBestMs = (int)$userData['best_time_ms'];
            $rankStmt = $mysqli->prepare("
                SELECT COUNT(*) + 1 AS user_rank
                FROM subway_leaderboard sl
                JOIN utenti u ON sl.utente_id = u.id
                WHERE sl.best_time_ms > ?
                  AND (u.isBannato IS NULL OR u.isBannato = 0)
            ");
            $rankStmt->bind_param("i", $userBestMs);
            $rankStmt->execute();
            $userRank = (int)($rankStmt->get_result()->fetch_assoc()['user_rank'] ?? 1);
            $rankStmt->close();

            $userRecord = [
                'has_record' => true,
                'rank' => $userRank,
                'best_time_ms' => $userBestMs,
                'map_slug' => htmlspecialchars($userData['map_slug'] ?? 'london', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'updated_at' => $userData['updated_at']
            ];
        } else {
            $userRecord = [
                'has_record' => false,
                'rank' => null,
                'best_time_ms' => 0,
                'map_slug' => null,
                'updated_at' => null
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => $leaderboard,
        'user_record' => $userRecord
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Errore nel caricamento della classifica.'
    ]);
}
