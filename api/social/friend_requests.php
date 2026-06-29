<?php
require_once __DIR__ . '/bootstrap.php';

// 1. Carichiamo le richieste RICEVUTE
$queryReceived = "
    SELECT 
        r.id AS request_id,
        u.id AS user_id, u.username, u.ruolo, u.is_premium,
        r.created_at
    FROM friendship_requests r
    INNER JOIN utenti u ON u.id = r.sender_id
    WHERE r.receiver_id = ? AND r.status = 'pending'
    ORDER BY r.created_at DESC
";
$stmtRec = $mysqli->prepare($queryReceived);
$received = [];
if ($stmtRec) {
    $stmtRec->bind_param("i", $userId);
    $stmtRec->execute();
    $received = $stmtRec->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtRec->close();
}

// 2. Carichiamo le richieste INVIATE
$querySent = "
    SELECT 
        r.id AS request_id,
        u.id AS user_id, u.username, u.ruolo, u.is_premium,
        r.created_at
    FROM friendship_requests r
    INNER JOIN utenti u ON u.id = r.receiver_id
    WHERE r.sender_id = ? AND r.status = 'pending'
    ORDER BY r.created_at DESC
";
$stmtSent = $mysqli->prepare($querySent);
$sent = [];
if ($stmtSent) {
    $stmtSent->bind_param("i", $userId);
    $stmtSent->execute();
    $sent = $stmtSent->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtSent->close();
}

send_api_success([
    'received' => $received,
    'sent' => $sent
]);
?>
