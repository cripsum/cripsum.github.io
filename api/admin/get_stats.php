<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $stats = [
        'users' => admin_safe_count($mysqli, "SELECT COUNT(*) AS total FROM utenti"),
        'banned' => admin_safe_count($mysqli, "SELECT COUNT(*) AS total FROM utenti WHERE isBannato = 1"),
        'admins' => admin_safe_count($mysqli, "SELECT COUNT(*) AS total FROM utenti WHERE ruolo IN ('admin', 'owner')"),
        'characters' => admin_table_exists($mysqli, 'personaggi') ? admin_safe_count($mysqli, "SELECT COUNT(*) AS total FROM personaggi") : 0,
        'achievements' => admin_table_exists($mysqli, 'achievement') ? admin_safe_count($mysqli, "SELECT COUNT(*) AS total FROM achievement") : 0,
        'inventory_rows' => admin_table_exists($mysqli, 'utenti_personaggi') ? admin_safe_count($mysqli, "SELECT COUNT(*) AS total FROM utenti_personaggi") : 0,
        'unlocked_achievements' => admin_table_exists($mysqli, 'utenti_achievement') ? admin_safe_count($mysqli, "SELECT COUNT(*) AS total FROM utenti_achievement") : 0,
        'shitposts' => admin_table_exists($mysqli, 'shitposts') ? admin_safe_count($mysqli, "SELECT COUNT(*) AS total FROM shitposts") : 0,
        'shitposts_pending' => admin_table_exists($mysqli, 'shitposts') ? admin_safe_count($mysqli, "SELECT COUNT(*) AS total FROM shitposts WHERE approvato = 0") : 0,
        'toprimasti' => admin_table_exists($mysqli, 'toprimasti') ? admin_safe_count($mysqli, "SELECT COUNT(*) AS total FROM toprimasti") : 0,
        'toprimasti_pending' => admin_table_exists($mysqli, 'toprimasti') ? admin_safe_count($mysqli, "SELECT COUNT(*) AS total FROM toprimasti WHERE approvato = 0") : 0,
    ];

    $latestUsers = [];
    $stmt = $mysqli->prepare("SELECT id, username, ruolo, isBannato, data_creazione FROM utenti ORDER BY data_creazione DESC LIMIT 6");
    if ($stmt && $stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $latestUsers[] = $row;
        $stmt->close();
    }

    admin_ok(['stats' => $stats, 'latest_users' => $latestUsers]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento statistiche.', 500);
}
