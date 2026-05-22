-- Cripsum Custom Profiles 3.0 migration
-- MariaDB 10.6+/MySQL 8 compatible, additive and safe to rerun on MariaDB 11.x.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `utenti`
  ADD COLUMN IF NOT EXISTS `display_name_en` varchar(40) DEFAULT NULL AFTER `display_name`,
  ADD COLUMN IF NOT EXISTS `bio_en` varchar(280) DEFAULT NULL AFTER `bio`,
  ADD COLUMN IF NOT EXISTS `profile_status_en` varchar(60) DEFAULT NULL AFTER `profile_status`,
  ADD COLUMN IF NOT EXISTS `profile_locale` enum('it','en') NOT NULL DEFAULT 'it' AFTER `profile_status_en`,
  ADD COLUMN IF NOT EXISTS `profile_enter_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `profile_show_audio_player`,
  ADD COLUMN IF NOT EXISTS `profile_enter_text_en` varchar(80) DEFAULT NULL AFTER `profile_enter_text`,
  ADD COLUMN IF NOT EXISTS `profile_enter_button` varchar(40) DEFAULT NULL AFTER `profile_enter_text_en`,
  ADD COLUMN IF NOT EXISTS `profile_enter_button_en` varchar(40) DEFAULT NULL AFTER `profile_enter_button`,
  ADD COLUMN IF NOT EXISTS `profile_enter_remember` tinyint(1) NOT NULL DEFAULT 1 AFTER `profile_enter_button_en`,
  ADD COLUMN IF NOT EXISTS `profile_background_mode` enum('upload','image','video','youtube','gradient') NOT NULL DEFAULT 'upload' AFTER `profile_enter_remember`,
  ADD COLUMN IF NOT EXISTS `profile_background_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`profile_background_config`)) AFTER `profile_background_mode`,
  ADD COLUMN IF NOT EXISTS `profile_youtube_url` varchar(255) DEFAULT NULL AFTER `profile_background_config`,
  ADD COLUMN IF NOT EXISTS `profile_fallback_image_url` varchar(255) DEFAULT NULL AFTER `profile_youtube_url`,
  ADD COLUMN IF NOT EXISTS `profile_canvas_effect` enum('none','snow','sparks','matrix','stars','rain','orbs','fireflies','confetti','sakura','smoke') NOT NULL DEFAULT 'none' AFTER `profile_effect`,
  ADD COLUMN IF NOT EXISTS `profile_canvas_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`profile_canvas_config`)) AFTER `profile_canvas_effect`,
  ADD COLUMN IF NOT EXISTS `profile_avatar_effect` varchar(32) NOT NULL DEFAULT 'pfp-glow' AFTER `avatar_ring_color`,
  ADD COLUMN IF NOT EXISTS `profile_avatar_shape` enum('circle','squircle','hexagon') NOT NULL DEFAULT 'circle' AFTER `profile_avatar_effect`,
  ADD COLUMN IF NOT EXISTS `profile_avatar_frame_url` varchar(255) DEFAULT NULL AFTER `profile_avatar_shape`,
  ADD COLUMN IF NOT EXISTS `profile_theme_preset` enum('cyber','rose','onyx','toxic','vaporwave','crimson','midnight','sakura') NOT NULL DEFAULT 'cyber' AFTER `profile_theme`,
  ADD COLUMN IF NOT EXISTS `profile_font_family` enum('inter','space-grotesk','jetbrains','vcr','poppins','playfair','orbitron','bebas') NOT NULL DEFAULT 'inter' AFTER `profile_theme_preset`,
  ADD COLUMN IF NOT EXISTS `profile_noise_enabled` tinyint(1) NOT NULL DEFAULT 1 AFTER `profile_font_family`,
  ADD COLUMN IF NOT EXISTS `profile_animations_enabled` tinyint(1) NOT NULL DEFAULT 1 AFTER `profile_noise_enabled`,
  ADD COLUMN IF NOT EXISTS `profile_builder_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`profile_builder_json`)) AFTER `profile_animations_enabled`,
  ADD COLUMN IF NOT EXISTS `profile_plugins_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`profile_plugins_json`)) AFTER `profile_builder_json`,
  ADD COLUMN IF NOT EXISTS `profile_presets_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`profile_presets_json`)) AFTER `profile_plugins_json`,
  ADD INDEX IF NOT EXISTS `idx_utenti_profile_theme_preset` (`profile_theme_preset`),
  ADD INDEX IF NOT EXISTS `idx_utenti_profile_locale` (`profile_locale`);

ALTER TABLE `utenti_social`
  ADD COLUMN IF NOT EXISTS `label_en` varchar(40) DEFAULT NULL AFTER `label`,
  ADD COLUMN IF NOT EXISTS `custom_icon_url` varchar(255) DEFAULT NULL AFTER `url`,
  ADD COLUMN IF NOT EXISTS `analytics_key` varchar(80) DEFAULT NULL AFTER `custom_icon_url`;

ALTER TABLE `utenti_links`
  ADD COLUMN IF NOT EXISTS `title_en` varchar(60) DEFAULT NULL AFTER `title`,
  ADD COLUMN IF NOT EXISTS `description_en` varchar(160) DEFAULT NULL AFTER `description`,
  ADD COLUMN IF NOT EXISTS `custom_icon_url` varchar(255) DEFAULT NULL AFTER `icon`,
  ADD COLUMN IF NOT EXISTS `thumbnail_url` varchar(255) DEFAULT NULL AFTER `custom_icon_url`,
  ADD COLUMN IF NOT EXISTS `short_slug` varchar(64) DEFAULT NULL AFTER `thumbnail_url`,
  ADD COLUMN IF NOT EXISTS `schedule_starts_at` datetime DEFAULT NULL AFTER `short_slug`,
  ADD COLUMN IF NOT EXISTS `schedule_ends_at` datetime DEFAULT NULL AFTER `schedule_starts_at`,
  ADD COLUMN IF NOT EXISTS `is_hidden` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_visible`,
  ADD COLUMN IF NOT EXISTS `is_separator` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_hidden`,
  ADD COLUMN IF NOT EXISTS `separator_title` varchar(70) DEFAULT NULL AFTER `is_separator`,
  ADD COLUMN IF NOT EXISTS `separator_title_en` varchar(70) DEFAULT NULL AFTER `separator_title`,
  ADD COLUMN IF NOT EXISTS `click_count` int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `sort_order`,
  ADD UNIQUE KEY IF NOT EXISTS `uq_utenti_links_short_slug` (`short_slug`),
  ADD INDEX IF NOT EXISTS `idx_utenti_links_schedule` (`utente_id`, `is_visible`, `is_hidden`, `schedule_starts_at`, `schedule_ends_at`);

ALTER TABLE `utenti_projects`
  ADD COLUMN IF NOT EXISTS `title_en` varchar(70) DEFAULT NULL AFTER `title`,
  ADD COLUMN IF NOT EXISTS `description_en` varchar(260) DEFAULT NULL AFTER `description`,
  ADD COLUMN IF NOT EXISTS `tech_stack_en` varchar(160) DEFAULT NULL AFTER `tech_stack`;

ALTER TABLE `utenti_contents`
  ADD COLUMN IF NOT EXISTS `title_en` varchar(70) DEFAULT NULL AFTER `title`,
  ADD COLUMN IF NOT EXISTS `description_en` varchar(220) DEFAULT NULL AFTER `description`;

ALTER TABLE `utenti_profile_blocks`
  MODIFY COLUMN `block_type` enum('text','image','gif','video','bio','social','link','projects','gallery','audio','spotify','youtube','twitch','github','countdown','quote','table','contact','achievement','lootbox','custom_html') NOT NULL DEFAULT 'text',
  ADD COLUMN IF NOT EXISTS `title_en` varchar(80) DEFAULT NULL AFTER `title`,
  ADD COLUMN IF NOT EXISTS `body_en` text DEFAULT NULL AFTER `body`,
  ADD COLUMN IF NOT EXISTS `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings_json`)) AFTER `media_type`,
  ADD COLUMN IF NOT EXISTS `is_collapsed` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_featured`,
  ADD INDEX IF NOT EXISTS `idx_profile_blocks_type` (`utente_id`, `block_type`, `is_visible`, `sort_order`);

CREATE TABLE IF NOT EXISTS `custom_badges` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `name` varchar(80) NOT NULL,
  `name_en` varchar(80) DEFAULT NULL,
  `tooltip` varchar(160) DEFAULT NULL,
  `tooltip_en` varchar(160) DEFAULT NULL,
  `icon` varchar(120) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#00f5ff',
  `glow` tinyint(1) NOT NULL DEFAULT 1,
  `animation` enum('none','pulse','shine','float','glitch') NOT NULL DEFAULT 'none',
  `badge_type` enum('staff','verified','developer','artist','rare','custom') NOT NULL DEFAULT 'custom',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_custom_badges_slug` (`slug`),
  KEY `idx_custom_badges_type` (`badge_type`),
  KEY `fk_custom_badges_creator` (`created_by`),
  CONSTRAINT `fk_custom_badges_creator` FOREIGN KEY (`created_by`) REFERENCES `utenti` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_custom_badges` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `utente_id` int(11) NOT NULL,
  `badge_id` int(10) UNSIGNED NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `assigned_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_custom_badge` (`utente_id`, `badge_id`),
  KEY `idx_user_custom_badges_visible` (`utente_id`, `is_visible`, `sort_order`),
  KEY `fk_user_custom_badges_badge` (`badge_id`),
  KEY `fk_user_custom_badges_assigner` (`assigned_by`),
  CONSTRAINT `fk_user_custom_badges_user` FOREIGN KEY (`utente_id`) REFERENCES `utenti` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_custom_badges_badge` FOREIGN KEY (`badge_id`) REFERENCES `custom_badges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_custom_badges_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `utenti` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `profile_analytics_events` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `profile_id` int(11) NOT NULL,
  `viewer_id` int(11) DEFAULT NULL,
  `event_type` enum('view','click','reaction','contact','share','qr') NOT NULL DEFAULT 'view',
  `link_id` int(10) UNSIGNED DEFAULT NULL,
  `referrer` varchar(255) DEFAULT NULL,
  `device_type` enum('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
  `ip_hash` char(64) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_profile_analytics_profile_day` (`profile_id`, `created_at`),
  KEY `idx_profile_analytics_event` (`profile_id`, `event_type`, `created_at`),
  KEY `idx_profile_analytics_link` (`link_id`, `created_at`),
  KEY `fk_profile_analytics_viewer` (`viewer_id`),
  CONSTRAINT `fk_profile_analytics_profile` FOREIGN KEY (`profile_id`) REFERENCES `utenti` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profile_analytics_viewer` FOREIGN KEY (`viewer_id`) REFERENCES `utenti` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_profile_analytics_link` FOREIGN KEY (`link_id`) REFERENCES `utenti_links` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `profile_reactions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `reaction` varchar(16) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_profile_reaction_identity` (`profile_id`, `reaction`, `ip_hash`),
  KEY `idx_profile_reactions_counts` (`profile_id`, `reaction`),
  KEY `fk_profile_reactions_user` (`user_id`),
  CONSTRAINT `fk_profile_reactions_profile` FOREIGN KEY (`profile_id`) REFERENCES `utenti` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profile_reactions_user` FOREIGN KEY (`user_id`) REFERENCES `utenti` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `profile_media` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `utente_id` int(11) NOT NULL,
  `media_type` enum('image','video','audio','file') NOT NULL DEFAULT 'image',
  `storage_path` varchar(255) NOT NULL,
  `public_url` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `width` int(10) UNSIGNED DEFAULT NULL,
  `height` int(10) UNSIGNED DEFAULT NULL,
  `alt_text` varchar(160) DEFAULT NULL,
  `alt_text_en` varchar(160) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_profile_media_user` (`utente_id`, `media_type`, `created_at`),
  CONSTRAINT `fk_profile_media_user` FOREIGN KEY (`utente_id`) REFERENCES `utenti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `profile_short_links` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `link_id` int(10) UNSIGNED DEFAULT NULL,
  `target_url` varchar(500) NOT NULL,
  `clicks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_profile_short_links_slug` (`slug`),
  KEY `idx_profile_short_links_user` (`utente_id`, `is_active`),
  KEY `fk_profile_short_links_link` (`link_id`),
  CONSTRAINT `fk_profile_short_links_user` FOREIGN KEY (`utente_id`) REFERENCES `utenti` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profile_short_links_link` FOREIGN KEY (`link_id`) REFERENCES `utenti_links` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `profile_rate_limits` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rate_key` char(40) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_profile_rate_limits_key_time` (`rate_key`, `created_at`),
  KEY `idx_profile_rate_limits_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `profile_theme_presets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `utente_id` int(11) DEFAULT NULL,
  `slug` varchar(80) NOT NULL,
  `name` varchar(80) NOT NULL,
  `name_en` varchar(80) DEFAULT NULL,
  `preset_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`preset_json`)),
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `uses_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_profile_theme_preset_owner_slug` (`utente_id`, `slug`),
  KEY `idx_profile_theme_presets_public` (`is_public`, `uses_count`),
  CONSTRAINT `fk_profile_theme_presets_user` FOREIGN KEY (`utente_id`) REFERENCES `utenti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `custom_badges` (`slug`, `name`, `name_en`, `tooltip`, `tooltip_en`, `icon`, `color`, `glow`, `animation`, `badge_type`)
VALUES
  ('staff', 'Staff', 'Staff', 'Membro dello staff Cripsum', 'Cripsum staff member', 'fas fa-shield-halved', '#60a5fa', 1, 'shine', 'staff'),
  ('verified', 'Verificato', 'Verified', 'Profilo verificato', 'Verified profile', 'fas fa-circle-check', '#22c55e', 1, 'pulse', 'verified'),
  ('developer', 'Developer', 'Developer', 'Costruttore di cose belle', 'Builder of good things', 'fas fa-code', '#a78bfa', 1, 'none', 'developer'),
  ('artist', 'Artist', 'Artist', 'Creator visivo', 'Visual creator', 'fas fa-palette', '#fb7185', 1, 'float', 'artist'),
  ('rare', 'Rare', 'Rare', 'Badge raro', 'Rare badge', 'fas fa-gem', '#facc15', 1, 'shine', 'rare');

SET FOREIGN_KEY_CHECKS = 1;
