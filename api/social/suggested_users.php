<?php
require_once __DIR__ . '/bootstrap.php';

// Algoritmo di raccomandazione sociale:
// 1. Trova utenti seguiti dai nostri seguiti (connessioni di 2° grado).
// 2. Fallback su utenti popolari e attivi.
// 3. Filtra: se stessi, già seguiti, già amici, richieste pendenti, blocchi, profili privati.

$sql = "
    SELECT 
        u.id, u.username, u.display_name, u.ruolo, u.is_premium,
        (SELECT COUNT(*) FROM user_follows WHERE followed_id = u.id) AS followers_count,
        -- Calcolo connessioni in comune (persone che seguiamo e che seguono anche l'utente suggerito)
        (
            SELECT COUNT(*) 
            FROM user_follows f2
            WHERE f2.follower_id = ? AND f2.followed_id IN (
                SELECT f3.follower_id FROM user_follows f3 WHERE f3.followed_id = u.id
            )
        ) AS mutual_connections,
        -- Verifica se ci segue (per evidenziare il follow back)
        EXISTS(SELECT 1 FROM user_follows WHERE follower_id = u.id AND followed_id = ?) AS is_follower
    FROM utenti u
    LEFT JOIN user_social_settings s ON s.user_id = u.id
    WHERE u.id != ?
      -- Escludi utenti già seguiti
      AND NOT EXISTS (SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = u.id)
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
      -- Rispetta la privacy del profilo
    ORDER BY mutual_connections DESC, is_follower DESC, followers_count DESC, u.ultimo_accesso DESC
    LIMIT 8
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    send_api_error("Errore interno del server durante il calcolo dei suggerimenti.", "DATABASE_ERROR", 500);
}

$stmt->bind_param(
    "iiiiiiiiii",
    $userId, $userId,
    $userId, $userId, $userId, $userId,
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
    $s['followers_count'] = (int)$s['followers_count'];
    $s['mutual_connections'] = (int)$s['mutual_connections'];
    $s['is_follower'] = (bool)$s['is_follower']; // Se l'utente segue noi, suggeriamo il "Ricambia il follow"
}
unset($s);

send_api_success(['suggestions' => $suggestions]);
?>
