<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== CREATING GROUP CHAT DATABASE TABLES ===\n\n";

$tables = [
    "chats" => "
        CREATE TABLE IF NOT EXISTS `chats` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `type` VARCHAR(50) NOT NULL DEFAULT 'group_private',
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `avatar_url` VARCHAR(255) DEFAULT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `last_message_id` INT DEFAULT NULL,
            `last_message_at` TIMESTAMP NULL DEFAULT NULL,
            `is_archived` TINYINT(1) DEFAULT 0,
            INDEX `idx_chats_type` (`type`),
            INDEX `idx_chats_created_by` (`created_by`),
            INDEX `idx_chats_last_message_at` (`last_message_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    "chat_members" => "
        CREATE TABLE IF NOT EXISTS `chat_members` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `chat_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `role` VARCHAR(50) NOT NULL DEFAULT 'member',
            `status` VARCHAR(50) NOT NULL DEFAULT 'invited',
            `joined_at` TIMESTAMP NULL DEFAULT NULL,
            `left_at` TIMESTAMP NULL DEFAULT NULL,
            `last_read_message_id` INT DEFAULT NULL,
            `last_read_at` TIMESTAMP NULL DEFAULT NULL,
            `muted_until` TIMESTAMP NULL DEFAULT NULL,
            `notification_level` VARCHAR(50) NOT NULL DEFAULT 'all',
            `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_chat_user` (`chat_id`, `user_id`),
            INDEX `idx_members_chat_id` (`chat_id`),
            INDEX `idx_members_user_id` (`user_id`),
            INDEX `idx_members_status` (`status`),
            INDEX `idx_members_role` (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    "chat_messages" => "
        CREATE TABLE IF NOT EXISTS `chat_messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `chat_id` INT NOT NULL,
            `sender_id` INT NOT NULL,
            `body` TEXT DEFAULT NULL,
            `message_type` VARCHAR(50) NOT NULL DEFAULT 'text',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `edited_at` TIMESTAMP NULL DEFAULT NULL,
            `deleted_at` TIMESTAMP NULL DEFAULT NULL,
            `deleted_by` INT DEFAULT NULL,
            `reply_to_message_id` INT DEFAULT NULL,
            `metadata_json` JSON DEFAULT NULL,
            INDEX `idx_messages_chat_id_id` (`chat_id`, `id`),
            INDEX `idx_messages_chat_id_created` (`chat_id`, `created_at`),
            INDEX `idx_messages_sender_id` (`sender_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    "chat_invites" => "
        CREATE TABLE IF NOT EXISTS `chat_invites` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `chat_id` INT NOT NULL,
            `inviter_id` INT NOT NULL,
            `invitee_id` INT NOT NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `responded_at` TIMESTAMP NULL DEFAULT NULL,
            `expires_at` TIMESTAMP NULL DEFAULT NULL,
            INDEX `idx_invites_chat_id` (`chat_id`),
            INDEX `idx_invites_invitee_id` (`invitee_id`),
            INDEX `idx_invites_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    "chat_settings" => "
        CREATE TABLE IF NOT EXISTS `chat_settings` (
            `chat_id` INT PRIMARY KEY,
            `invite_permission` VARCHAR(50) NOT NULL DEFAULT 'everyone',
            `edit_info_permission` VARCHAR(50) NOT NULL DEFAULT 'owner_admins',
            `message_permission` VARCHAR(50) NOT NULL DEFAULT 'members',
            `approval_required` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "
];

foreach ($tables as $name => $sql) {
    echo "Creating table '$name'...\n";
    try {
        if ($mysqli->query($sql)) {
            echo "SUCCESS\n";
        } else {
            echo "FAILED: " . $mysqli->error . "\n";
        }
    } catch (Throwable $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Alter tables to add columns
echo "Altering table 'private_messages' to add GIPHY support...\n";
try {
    $mysqli->query("ALTER TABLE `private_messages` ADD COLUMN `message_type` VARCHAR(50) NOT NULL DEFAULT 'text' AFTER `message`");
    $mysqli->query("ALTER TABLE `private_messages` ADD COLUMN `media_url` VARCHAR(255) DEFAULT NULL AFTER `message_type`");
    $mysqli->query("ALTER TABLE `private_messages` ADD COLUMN `media_title` VARCHAR(255) DEFAULT NULL AFTER `media_url`");
    echo "SUCCESS\n";
} catch (Throwable $e) {
    echo "COLUMNS PROBABLY ALREADY EXIST: " . $e->getMessage() . "\n";
}

echo "Altering table 'chat_members' to add is_archived support...\n";
try {
    $mysqli->query("ALTER TABLE `chat_members` ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notification_level`");
    echo "SUCCESS\n";
} catch (Throwable $e) {
    echo "COLUMN PROBABLY ALREADY EXISTS: " . $e->getMessage() . "\n";
}

echo "Database creation queries complete.\n";
?>
