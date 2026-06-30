<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

// 1. Insert Custom Badges
$badgesQuery = "
    INSERT IGNORE INTO `custom_badges` 
        (`id`, `slug`, `name`, `name_en`, `tooltip`, `tooltip_en`, `descrizione`, `descrizione_en`, `image_url`, `color`, `glow`, `animation`, `badge_type`) 
    VALUES
        (20, 'godos_to_waste', 'Godos da Buttare', 'Godos to Waste', 'Ho così tanti Godos da buttarli via!', 'I have so many Godos I can throw them away!', 'Ottenuto acquistando il badge dal Negozio Godos.', 'Obtained by purchasing the badge from the Godos Shop.', '/img/badges/godos_to_waste.png', '#fbbf24', 1, 'float', 'rare'),
        (21, 'godos_lover', 'Amante dei Godos', 'Godos Lover', 'Un badge per i veri sostenitori dei Godos!', 'A badge for true Godos lovers!', 'Ottenuto acquistando il badge dal Negozio Godos.', 'Obtained by purchasing the badge from the Godos Shop.', '/img/badges/godos_lover.png', '#ec4899', 1, 'pulse', 'custom')
";

if ($mysqli->query($badgesQuery)) {
    echo "1. Custom Badges (Godos to Waste & Godos Lover) check/insertion completed.\n";
} else {
    echo "Error inserting custom badges: " . $mysqli->error . "\n";
}

// 2. Check if godos_shop_items table exists, if so insert shop items
$tableCheck = $mysqli->query("SHOW TABLES LIKE 'godos_shop_items'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $shopQuery = "
        INSERT IGNORE INTO `godos_shop_items` 
            (`id`, `name_it`, `name_en`, `description_it`, `description_en`, `price_godos`, `item_type`, `item_value`, `availability`, `active`, `image_url`) 
        VALUES
            (1, 'Godos da Buttare', 'Godos to Waste', 'Un badge esclusivo per chi ha così tanti Godos da non sapere come spenderli.', 'An exclusive badge for those who have so many Godos they do not know how to spend them.', 50000, 'badge', '20', 10, 1, '/img/badges/godos_to_waste.png'),
            (2, 'Amante dei Godos', 'Godos Lover', 'Mostra il tuo supporto al sito ed il tuo amore per i Godos con questo badge speciale.', 'Show your support to the site and your love for Godos with this special badge.', 1000, 'badge', '21', NULL, 1, '/img/badges/godos_lover.png')
    ";

    if ($mysqli->query($shopQuery)) {
        echo "2. Godos Shop Items check/insertion completed.\n";
    } else {
        echo "Error inserting shop items: " . $mysqli->error . "\n";
    }
} else {
    // If shop table name is different, check for godos_store or shop_items
    echo "2. Table 'godos_shop_items' does not exist in this database schema.\n";
}

exit;
