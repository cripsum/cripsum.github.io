<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $userId = (int)($_GET['id'] ?? 0);
    if ($userId <= 0) admin_fail('ID utente non valido.');

    $user = admin_fetch_user($mysqli, $userId);
    if (!$user) admin_fail('Utente non trovato.', 404);
    $user['avatar_url'] = admin_avatar_url($userId);

    $inventory = [];
    if (admin_table_exists($mysqli, 'utenti_personaggi') && admin_table_exists($mysqli, 'personaggi')) {
        $cols = admin_character_columns($mysqli);
        $nameCol = $cols['name'] ?: 'nome';
        $imageCol = $cols['image'];
        $rarityCol = $cols['rarity'];
        $qtyCol = admin_inventory_quantity_column($mysqli);
        $select = "p.id, p." . admin_qcol($nameCol) . " AS nome";
        $select .= $imageCol ? ", p." . admin_qcol($imageCol) . " AS img_url" : ", NULL AS img_url";
        $select .= $rarityCol ? ", p." . admin_qcol($rarityCol) . " AS rarita" : ", NULL AS rarita";
        $select .= $qtyCol ? ", up." . admin_qcol($qtyCol) . " AS quantita" : ", 1 AS quantita";

        $stmt = $mysqli->prepare("SELECT $select FROM utenti_personaggi up INNER JOIN personaggi p ON p.id = up.personaggio_id WHERE up.utente_id = ? ORDER BY p." . admin_qcol($nameCol) . " ASC LIMIT 150");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $inventory[] = $row;
            $stmt->close();
        }
    }

    $achievements = [];
    if (admin_table_exists($mysqli, 'utenti_achievement') && admin_table_exists($mysqli, 'achievement')) {
        $cols = admin_achievement_columns($mysqli);
        $nameCol = $cols['name'] ?: 'nome';
        $descCol = $cols['description'];
        $imgCol = $cols['image'];
        $pointsCol = $cols['points'];
        $select = "a.id, a." . admin_qcol($nameCol) . " AS nome";
        $select .= $descCol ? ", a." . admin_qcol($descCol) . " AS descrizione" : ", NULL AS descrizione";
        $select .= $imgCol ? ", a." . admin_qcol($imgCol) . " AS img_url" : ", NULL AS img_url";
        $select .= $pointsCol ? ", a." . admin_qcol($pointsCol) . " AS punti" : ", 0 AS punti";
        $select .= admin_column_exists($mysqli, 'utenti_achievement', 'data') ? ", ua.data AS unlocked_at" : ", NULL AS unlocked_at";

        $stmt = $mysqli->prepare("SELECT $select FROM utenti_achievement ua INNER JOIN achievement a ON a.id = ua.achievement_id WHERE ua.utente_id = ? ORDER BY ua.achievement_id DESC LIMIT 150");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $achievements[] = $row;
            $stmt->close();
        }
    }

    admin_ok(['user' => $user, 'inventory' => $inventory, 'achievements' => $achievements]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento dettagli utente.', 500);
}
