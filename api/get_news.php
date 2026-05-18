<?php
/*
 * ─────────────────────────────────────────────
 *  Cripsum™ — News / Changelog API
 *  GET /api/get_news.php?lang=it|en
 * ─────────────────────────────────────────────
 *
 *  SQL per creare la tabella:
 *
 *  CREATE TABLE cripsum_news (
 *    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *    versione     VARCHAR(20)   DEFAULT NULL,
 *    titolo       VARCHAR(200)  NOT NULL,
 *    titolo_en    VARCHAR(200)  DEFAULT NULL,
 *    tag          VARCHAR(50)   DEFAULT NULL,
 *    tag_en       VARCHAR(50)   DEFAULT NULL,
 *    contenuto    TEXT          NOT NULL,
 *    contenuto_en TEXT          DEFAULT NULL,
 *    immagine     VARCHAR(300)  DEFAULT NULL,
 *    pinned       TINYINT(1)    NOT NULL DEFAULT 0,
 *    visibile     TINYINT(1)    NOT NULL DEFAULT 1,
 *    data_news    DATE          NOT NULL,
 *    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 *  Esempio inserimento news:
 *
 *  INSERT INTO cripsum_news
 *    (versione, titolo, titolo_en, tag, tag_en, contenuto, contenuto_en, data_news, pinned, visibile)
 *  VALUES
 *    ('6.0', 'Gacha System', 'Gacha System', 'feature', 'feature',
 *     "<p>Il nuovo sistema gacha è finalmente live!</p><ul><li>Nuovi banner evento</li><li>Pity system</li><li>Fix UI mobile</li></ul>",
 *     "<p>The new gacha system is finally live!</p><ul><li>New event banners</li><li>Pity system</li><li>Mobile UI fix</li></ul>",
 *     '2025-05-15', 1, 1);
 */

require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// --- Validazione lang ---
$lang = 'it';
if (isset($_GET['lang']) && $_GET['lang'] === 'en') {
    $lang = 'en';
}

// --- Controllo connessione DB ---
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['error' => 'db_unavailable']);
    exit;
}

// --- Query ---
$sql = "SELECT id, versione, titolo, titolo_en, tag, tag_en,
               contenuto, contenuto_en, immagine, pinned, data_news
        FROM cripsum_news
        WHERE visibile = 1
        ORDER BY pinned DESC, data_news DESC, id DESC
        LIMIT 30";

$result = $mysqli->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    exit;
}

$news    = [];
$latestId = 0;

while ($row = $result->fetch_assoc()) {
    // --- Fallback traduzioni ---
    $titolo    = ($lang === 'en' && !empty($row['titolo_en']))    ? $row['titolo_en']    : $row['titolo'];
    $tag       = ($lang === 'en' && !empty($row['tag_en']))       ? $row['tag_en']       : $row['tag'];
    $contenuto = ($lang === 'en' && !empty($row['contenuto_en'])) ? $row['contenuto_en'] : $row['contenuto'];

    /*
     * Sicurezza: il contenuto viene salvato nel DB già sanificato.
     * Se usi un pannello admin, sanitizza con HTMLPurifier o strip_tags con whitelist
     * PRIMA di inserire nel DB, non qui.
     */

    $item = [
        'id'        => (int)$row['id'],
        'versione'  => $row['versione'] ?? null,
        'titolo'    => $titolo,
        'tag'       => $tag ?? null,
        'contenuto' => $contenuto,
        'immagine'  => $row['immagine'] ?? null,
        'pinned'    => (bool)$row['pinned'],
        'data'      => $row['data_news'],
    ];

    $news[] = $item;

    // Il primo non-pinned (o il primo in assoluto) è il "latest" per l'auto-open
    if ($latestId === 0) {
        $latestId = (int)$row['id'];
    }
}

$result->free();

echo json_encode([
    'latest_id' => $latestId,
    'news'      => $news,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
