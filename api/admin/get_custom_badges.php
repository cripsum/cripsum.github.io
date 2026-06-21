<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!admin_table_exists($mysqli, 'custom_badges')) {
        echo json_encode(['ok' => true, 'badges' => []]);
        exit();
    }

    $result = $mysqli->query("SELECT id, slug, name, name_en FROM custom_badges ORDER BY name ASC");
    if (!$result) {
        throw new Exception("Errore nel recupero dei badge personalizzati.");
    }

    $badges = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['ok' => true, 'badges' => $badges]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
