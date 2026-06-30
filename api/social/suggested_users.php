<?php
// api/social/suggested_users.php
// Recommendation system using mutual friends and fallback to active users (follow-less friendship model).

require_once __DIR__ . '/bootstrap.php';

$sql = "
    SELECT 
        u.id, u.username, u.display_name, u.ruolo, u.is_premium,
        -- Calcolo amici in comune (mutual connections)
        (
            SELECT COUNT(*)
            FROM friendships f1
            INNER JOIN friendships f2 ON 
                (IF(f1.user_one_id = ?, f1.user_two_id, f1.user_one_id) = IF(f2.user_one_id = u.id, f2.user_two_id, f2.user_one_id))
            WHERE (f1.user_one_id = ? OR f1.user_two_id = ?)
              AND (f2.user_one_id = u.id OR f2.user_two_id = u.id)
        ) AS mutual_connections
    FROM utenti u
    LEFT JOIN user_social_settings s ON s.user_id = u.id
    WHERE u.id != ?
      -- Escludi amici esistenti
      AND NOT EXISTS (SELECT 1 FROM friendships WHERE user_one_id = LEAST(?, u.id) AND user_two_id = GREATEST(?, u.id))
      -- Escludi richieste pendenti
      AND NOT EXISTS (
          SELECT 1 FROM friendship_requests 
          WHERE ((sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?)) 
            AND status = 'pending'
      )
      -- Escludi utenti bloccati (in entrambe le direzioni)
      AND NOT EXISTS (
          SELECT 1 FROM blocked_users b 
          WHERE (b.blocker_id = ? AND b.blocked_id = u.id)
             OR (b.blocker_id = u.id AND b.blocked_id = ?)
      )
    ORDER BY mutual_connections DESC, u.ultimo_accesso DESC
    LIMIT 8
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    send_api_error("Errore interno del server durante il calcolo dei suggerimenti.", "DATABASE_ERROR", 500);
}

$stmt->bind_param(
    "iiiiiiiiii",
    $userId, $userId, $userId,
    $userId,
    $userId, $userId,
    $userId, $userId,
    $userId, $userId
);

$stmt->execute();
$res = $stmt->get_result();
$suggestions = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($suggestions as &$s) {
    $s['id'] = (int)$s['id'];
    $s['display_name'] = $s['display_name'] ?: $s['username'];
    $s['followers_count'] = 0; // Keeping structure compatible
    $s['mutual_connections'] = (int)$s['mutual_connections'];
    $s['is_follower'] = false; // Follows removed
}
unset($s);

send_api_success(['suggestions' => $suggestions]);
?>
