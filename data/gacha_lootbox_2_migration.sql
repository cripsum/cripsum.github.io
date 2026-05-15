-- Gacha & Lootbox System 2.0 migration for Cripsum.
-- Run this once in phpMyAdmin or your MySQL client before enabling event pulls.
-- The script is idempotent for columns, table and indexes.

SET @db_name := DATABASE();

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE utenti ADD COLUMN pity_standard INT NOT NULL DEFAULT 0',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'utenti' AND COLUMN_NAME = 'pity_standard'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE utenti ADD COLUMN pity_evento INT NOT NULL DEFAULT 0',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'utenti' AND COLUMN_NAME = 'pity_evento'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE utenti ADD COLUMN garantito_evento TINYINT(1) NOT NULL DEFAULT 0',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'utenti' AND COLUMN_NAME = 'garantito_evento'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE personaggi ADD COLUMN video_url VARCHAR(255) NULL DEFAULT NULL',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'personaggi' AND COLUMN_NAME = 'video_url'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE personaggi ADD COLUMN pool_evento TINYINT(1) NOT NULL DEFAULT 0',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'personaggi' AND COLUMN_NAME = 'pool_evento'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE personaggi ADD COLUMN in_pool_standard TINYINT(1) NOT NULL DEFAULT 1',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'personaggi' AND COLUMN_NAME = 'in_pool_standard'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE personaggi SET in_pool_standard = 1 WHERE in_pool_standard IS NULL;
UPDATE personaggi SET pool_evento = 0 WHERE pool_evento IS NULL;

CREATE TABLE IF NOT EXISTS banner_eventi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descrizione TEXT NULL,
    id_personaggio_rateup INT NOT NULL,
    banner_img_url VARCHAR(255) NULL,
    costo_punti INT NOT NULL DEFAULT 100,
    attivo TINYINT(1) NOT NULL DEFAULT 1,
    data_inizio DATETIME NULL,
    data_fine DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_banner_eventi_slug (slug),
    KEY idx_banner_eventi_attivo (attivo),
    KEY idx_banner_eventi_rateup (id_personaggio_rateup)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE personaggi ADD INDEX idx_personaggi_pool_standard_rarita (in_pool_standard, `rarità`)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'personaggi' AND INDEX_NAME = 'idx_personaggi_pool_standard_rarita'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE personaggi ADD INDEX idx_personaggi_pool_evento (pool_evento)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'personaggi' AND INDEX_NAME = 'idx_personaggi_pool_evento'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Example setup:
-- 1. Mark base secret characters as standard:
--    UPDATE personaggi SET in_pool_standard = 1 WHERE `rarità` IN ('segreto', 'theone') AND nome NOT IN ('YUNO');
-- 2. Mark limited event characters as event-only:
--    UPDATE personaggi SET in_pool_standard = 0, pool_evento = 1, video_url = 'yuno_reveal.mp4' WHERE nome = 'YUNO';
-- 3. Create or replace an active banner:
--    INSERT INTO banner_eventi (slug, nome, descrizione, id_personaggio_rateup, banner_img_url, costo_punti, attivo, data_inizio, data_fine)
--    VALUES ('yuno-evento', 'Yuno Rate Up', 'Banner limitato con pity a 80 e 50/50.', 123, 'yuno_banner.webp', 100, 1, NOW(), NULL)
--    ON DUPLICATE KEY UPDATE nome = VALUES(nome), descrizione = VALUES(descrizione), id_personaggio_rateup = VALUES(id_personaggio_rateup),
--    banner_img_url = VALUES(banner_img_url), costo_punti = VALUES(costo_punti), attivo = VALUES(attivo), data_inizio = VALUES(data_inizio), data_fine = VALUES(data_fine);

