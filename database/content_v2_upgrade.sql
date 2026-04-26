-- Content V2 upgrade per Shitpost e Top Rimasti
-- Fai backup prima.
-- Se una colonna esiste già, rimuovi quella riga dall'ALTER TABLE.

-- Rende i media più sicuri per video brevi.
ALTER TABLE shitposts
    MODIFY foto_shitpost LONGBLOB NULL;

ALTER TABLE toprimasti
    MODIFY foto_rimasto LONGBLOB NULL;

-- Campi extra opzionali per feed 2.0.
ALTER TABLE shitposts
    ADD COLUMN tag VARCHAR(40) NULL AFTER descrizione,
    ADD COLUMN is_spoiler TINYINT(1) NOT NULL DEFAULT 0 AFTER tag,
    ADD COLUMN views INT NOT NULL DEFAULT 0 AFTER approvato,
    ADD COLUMN updated_at DATETIME NULL AFTER views;

ALTER TABLE toprimasti
    ADD COLUMN tag VARCHAR(40) NULL AFTER motivazione,
    ADD COLUMN is_spoiler TINYINT(1) NOT NULL DEFAULT 0 AFTER tag,
    ADD COLUMN views INT NOT NULL DEFAULT 0 AFTER reazioni,
    ADD COLUMN updated_at DATETIME NULL AFTER views;

-- Like per Shitpost.
CREATE TABLE IF NOT EXISTS shitpost_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    id_shitpost INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shitpost_like (id_utente, id_shitpost),
    INDEX idx_shitpost_likes_post (id_shitpost),
    INDEX idx_shitpost_likes_user (id_utente)
);

-- Commenti generici per Top Rimasti.
-- Shitpost continua a usare commenti_shitpost per compatibilità.
CREATE TABLE IF NOT EXISTS content_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('shitpost', 'rimasto') NOT NULL,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    comment VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_content_comments_post (content_type, post_id),
    INDEX idx_content_comments_user (user_id),
    INDEX idx_content_comments_created (created_at)
);

-- Salvati/preferiti.
CREATE TABLE IF NOT EXISTS content_saves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('shitpost', 'rimasto') NOT NULL,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_content_save (content_type, post_id, user_id),
    INDEX idx_content_saves_user (user_id),
    INDEX idx_content_saves_post (content_type, post_id)
);

-- Segnalazioni.
CREATE TABLE IF NOT EXISTS content_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('shitpost', 'rimasto') NOT NULL,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('open', 'reviewed', 'dismissed') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewed_by INT NULL,
    UNIQUE KEY uq_content_report (content_type, post_id, user_id),
    INDEX idx_content_reports_status (status),
    INDEX idx_content_reports_post (content_type, post_id)
);

-- Visite con anti-doppione leggero.
CREATE TABLE IF NOT EXISTS content_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('shitpost', 'rimasto') NOT NULL,
    post_id INT NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_content_view_user (content_type, post_id, user_id),
    INDEX idx_content_views_post (content_type, post_id),
    INDEX idx_content_views_created (created_at)
);
