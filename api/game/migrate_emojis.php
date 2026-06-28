<?php
require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli' && !gd_is_admin()) {
    die("Accesso negato.");
}

$mysqli->query("
    CREATE TABLE IF NOT EXISTS `game_emojis` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `code` varchar(64) NOT NULL UNIQUE,
      `url` varchar(255) NOT NULL,
      `is_animated` tinyint(1) NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$mysqli->query("ALTER TABLE game_match_reactions MODIFY COLUMN reaction VARCHAR(64) NOT NULL;");

$default_emojis = [
    ['laughing', '/img/Laughing_emoji_no_background.webp', 0],
    ['amongus', '/img/amongus.jpg', 0],
    ['carmelo_gif', '/img/carmelo.gif', 1],
    ['cesso_gif', '/img/cesso.gif', 1],
    ['cuore', '/img/cuore.png', 0],
    ['rweeks_gif', '/img/rweeks.gif', 1],
    ['jackpot_gif', '/img/jackpot.gif', 1],
    ['sossio', '/img/sossio.png', 0],
    ['nauzadorabile', '/img/nauzadorabile.png', 0],
    ['smartguy', '/img/smartguy.jpg', 0],
    ['ado', '/img/ado.webp', 0],
    ['camionista', '/img/camionista.png', 0],
    ['gooner', '/img/christiangooner.jpg', 0]
];

$stmt = $mysqli->prepare("INSERT IGNORE INTO game_emojis (code, url, is_animated) VALUES (?, ?, ?)");
if ($stmt) {
    foreach ($default_emojis as $emoji) {
        $stmt->bind_param('ssi', $emoji[0], $emoji[1], $emoji[2]);
        $stmt->execute();
    }
    $stmt->close();
}

echo "Migrazione completata con successo!\n";
