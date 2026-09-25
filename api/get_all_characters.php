<?php
/**
 * Catalogo dei personaggi.
 *
 * Prima restituiva SELECT * a chiunque, anche senza login: nomi, immagini,
 * audio e video di tutti i segreti e dei limitati non ancora usciti. Adesso
 * vale la stessa regola dell'inventario: i personaggi che non possiedi si
 * vedono solo se il catalogo li mostra, e quelli "nascosti" non ci sono.
 */
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gacha/banners.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$owned = [];
$stmt = $mysqli->prepare('SELECT personaggio_id FROM utenti_personaggi WHERE utente_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_NUM) as $row) {
    $owned[(int)$row[0]] = true;
}
$stmt->close();

$out = [];
foreach (gacha_characters($mysqli) as $id => $c) {
    $mine = isset($owned[$id]);
    if (!$mine && $c['catalogo'] === 'nascosto') {
        continue;
    }
    if (!$mine && $c['catalogo'] === 'segreto') {
        $out[] = ['id' => $id, 'rarità' => $c['rarita'], 'categoria' => $c['categoria']];
        continue;
    }
    $out[] = [
        'id' => $id,
        'nome' => $c['nome'],
        'descrizione' => $c['descrizione'],
        'descrizione_en' => $c['descrizione_en'],
        'rarità' => $c['rarita'],
        'categoria' => $c['categoria'],
        'img_url' => $c['img_url'],
        'audio_url' => $mine ? $c['audio_url'] : null,
        'caratteristiche' => $c['caratteristiche'],
        'caratteristiche_en' => $c['caratteristiche_en'],
        'video_url' => $mine ? $c['video_url'] : null,
        'in_pool_standard' => $c['in_pool_standard'] ? 1 : 0,
        'limitato' => $c['limitato'] ? 1 : 0,
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
