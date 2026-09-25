<?php
/**
 * Inventario dell'utente nella forma storica (una riga per personaggio con
 * stats, stats_next e copie richieste). La pagina dell'inventario ora usa
 * api/gacha/collezione.php; questo resta per chi lo chiama ancora.
 *
 * Le statistiche si calcolano in blocco: prima erano fino a quattro query
 * per ogni personaggio posseduto.
 */
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gacha/collection.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode([]);
    exit;
}

$chars = gacha_characters($mysqli);
$owned = gacha_owned_rows($mysqli, $userId);
$cards = gacha_card_stats_rows($mysqli, array_keys($owned));

$out = [];
foreach ($owned as $id => $own) {
    $c = $chars[$id] ?? null;
    if (!$c) {
        continue;
    }
    $game = gacha_game_row($c);
    $level = $own['livello'];
    $out[] = [
        'id' => $id,
        'nome' => $c['nome'],
        'descrizione' => $c['descrizione'],
        'rarità' => $c['rarita'],
        'categoria' => $c['categoria'],
        'img_url' => $c['img_url'],
        'audio_url' => $c['audio_url'],
        'caratteristiche' => $c['caratteristiche'],
        'data' => $own['data'],
        'quantità' => $own['quantita'],
        'livello' => $level,
        'descrizione_en' => $c['descrizione_en'],
        'caratteristiche_en' => $c['caratteristiche_en'],
        'stats' => gd_stats_build($id, $game, $cards[$id] ?? null, $level),
        'stats_next' => $level < 6 ? gd_stats_build($id, $game, $cards[$id] ?? null, $level + 1) : null,
        'required_next' => $level < 6 ? gd_get_upgrade_requirement($c['rarita'], $level, gd_limited_marker($game)) : 0,
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
