<?php
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Sicurezza: Solo admin o owner possono inizializzare il database
if (!isLoggedIn() || !in_array($_SESSION['ruolo'] ?? '', ['admin', 'owner'], true)) {
    http_response_code(403);
    die("Accesso negato. Solo gli amministratori possono inizializzare il database social.");
}

header('Content-Type: text/plain; charset=utf-8');
echo "Inizio inizializzazione database Cripsum™ Social Graph...\n\n";

// 1. Unificazione Tabella Blocchi
$checkOldBlocks = $mysqli->query("SHOW TABLES LIKE 'private_user_blocks'");
if ($checkOldBlocks && $checkOldBlocks->num_rows > 0) {
    echo "Rilevata vecchia tabella 'private_user_blocks'. Eseguo unificazione...\n";
    
    // Rinomina tabella
    if ($mysqli->query("RENAME TABLE `private_user_blocks` TO `blocked_users`")) {
        echo "[OK] Tabella rinominata in 'blocked_users'.\n";
        
        // Modifica colonne
        $mysqli->query("ALTER TABLE `blocked_users` DROP FOREIGN KEY `private_user_blocks_ibfk_1`");
        $mysqli->query("ALTER TABLE `blocked_users` DROP FOREIGN KEY `private_user_blocks_ibfk_2`");
        $mysqli->query("ALTER TABLE `blocked_users` DROP INDEX `block_pair`");
        
        if ($mysqli->query("ALTER TABLE `blocked_users` CHANGE COLUMN `user_id` `blocker_id` INT NOT NULL")) {
            echo "[OK] Colonna 'user_id' rinominata in 'blocker_id'.\n";
        }
        if ($mysqli->query("ALTER TABLE `blocked_users` CHANGE COLUMN `blocked_user_id` `blocked_id` INT NOT NULL")) {
            echo "[OK] Colonna 'blocked_user_id' rinominata in 'blocked_id'.\n";
        }
        
        // Ricrea indici e chiavi esterne
        $mysqli->query("ALTER TABLE `blocked_users` ADD UNIQUE KEY `blocker_blocked` (`blocker_id`, `blocked_id`)");
        $mysqli->query("ALTER TABLE `blocked_users` ADD CONSTRAINT `fk_blocker` FOREIGN KEY (`blocker_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE");
        $mysqli->query("ALTER TABLE `blocked_users` ADD CONSTRAINT `fk_blocked` FOREIGN KEY (`blocked_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE");
        echo "[OK] Vincoli e indici ricreati su 'blocked_users'.\n";
    } else {
        echo "[ERRORE] Ridenominazione tabella fallita: " . $mysqli->error . "\n";
    }
} else {
    // Se non esiste la vecchia tabella, creiamo direttamente la nuova
    $createBlocks = "
        CREATE TABLE IF NOT EXISTS `blocked_users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `blocker_id` INT NOT NULL,
            `blocked_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`blocker_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`blocked_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `blocker_blocked` (`blocker_id`, `blocked_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    if ($mysqli->query($createBlocks)) {
        echo "[OK] Tabella 'blocked_users' creata.\n";
    } else {
        echo "[ERRORE] Creazione 'blocked_users' fallita: " . $mysqli->error . "\n";
    }
}

// 2. Altre tabelle social
$tables = [
    "user_follows" => "
        CREATE TABLE IF NOT EXISTS `user_follows` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `follower_id` INT NOT NULL,
            `followed_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`follower_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`followed_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `follower_followed` (`follower_id`, `followed_id`),
            CONSTRAINT `no_self_follow` CHECK (`follower_id` <> `followed_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    "friendships" => "
        CREATE TABLE IF NOT EXISTS `friendships` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_one_id` INT NOT NULL,
            `user_two_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_one_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_two_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `friend_pair` (`user_one_id`, `user_two_id`),
            CONSTRAINT `no_self_friend` CHECK (`user_one_id` <> `user_two_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    "friendship_requests" => "
        CREATE TABLE IF NOT EXISTS `friendship_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sender_id` INT NOT NULL,
            `receiver_id` INT NOT NULL,
            `status` ENUM('pending', 'accepted', 'declined', 'cancelled') DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `responded_at` TIMESTAMP NULL,
            FOREIGN KEY (`sender_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`receiver_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `sender_receiver_request` (`sender_id`, `receiver_id`),
            CONSTRAINT `no_self_request` CHECK (`sender_id` <> `receiver_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    "user_social_settings" => "
        CREATE TABLE IF NOT EXISTS `user_social_settings` (
            `user_id` INT PRIMARY KEY,
            `profile_visibility` ENUM('public', 'private') DEFAULT 'public',
            `follow_permission` ENUM('everyone', 'registered', 'nobody') DEFAULT 'everyone',
            `friend_request_permission` ENUM('everyone', 'followers', 'following', 'mutual_followers', 'nobody') DEFAULT 'everyone',
            `message_permission` ENUM('everyone', 'followers', 'following', 'friends', 'nobody') DEFAULT 'everyone',
            `show_friend_count` TINYINT(1) DEFAULT 1,
            `show_followers_count` TINYINT(1) DEFAULT 1,
            `show_following_count` TINYINT(1) DEFAULT 1,
            `show_mutual_friends` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "
];

foreach ($tables as $name => $sql) {
    if ($mysqli->query($sql)) {
        echo "[OK] Tabella '$name' creata o gia' esistente.\n";
    } else {
        echo "[ERRORE] Creazione tabella '$name' fallita: " . $mysqli->error . "\n";
    }
}

// Inseriamo i valori di default per tutti gli utenti gia' registrati
if ($mysqli->query("INSERT IGNORE INTO user_social_settings (user_id) SELECT id FROM utenti")) {
    echo "[OK] Impostazioni sociali di default create per tutti gli utenti.\n";
} else {
    echo "[ERRORE] Impossibile inserire impostazioni sociali di default: " . $mysqli->error . "\n";
}

echo "\nInizializzazione completata.";
?>
