<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) admin_fail('ID shitpost non valido.');

    if (!admin_table_exists($mysqli, 'commenti_shitpost')) {
        admin_ok(['comments' => []]);
    }

    $stmt = $mysqli->prepare("
        SELECT c.id, c.id_utente, c.commento, c.data_commento, u.username
        FROM commenti_shitpost c
        LEFT JOIN utenti u ON u.id = c.id_utente
        WHERE c.id_shitpost = ?
        ORDER BY c.data_commento DESC
        LIMIT 200
    ");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query commenti non valida.'), 500);
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) admin_fail('Impossibile caricare i commenti.', 500);

    $res = $stmt->get_result();
    $comments = [];
    while ($row = $res->fetch_assoc()) $comments[] = $row;
    $stmt->close();

    admin_ok(['comments' => $comments]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento commenti. Dettaglio: ' . $e->getMessage(), 500);
}
