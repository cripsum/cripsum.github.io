-- Chat Globale V2 - upgrade database
-- Fai backup prima di eseguire.
-- Se una colonna o un indice esiste già, rimuovi quella riga prima di lanciare lo script.

ALTER TABLE messages
    ADD COLUMN edited_at DATETIME NULL AFTER created_at,
    ADD COLUMN deleted_at DATETIME NULL AFTER edited_at,
    ADD COLUMN deleted_by INT NULL AFTER deleted_at,
    ADD COLUMN client_nonce VARCHAR(90) NULL AFTER deleted_by;

ALTER TABLE messages ADD INDEX idx_messages_created_id (created_at, id);
ALTER TABLE messages ADD INDEX idx_messages_user_created (user_id, created_at);
ALTER TABLE messages ADD INDEX idx_messages_reply_to (reply_to);
ALTER TABLE messages ADD UNIQUE KEY uq_messages_nonce (client_nonce);

CREATE TABLE IF NOT EXISTS chat_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason VARCHAR(300) NOT NULL,
    status ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_report_once (message_id, reporter_id),
    INDEX idx_report_status (status, created_at),
    CONSTRAINT fk_chat_reports_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_reports_user FOREIGN KEY (reporter_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_mutes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    muter_id INT NOT NULL,
    muted_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_chat_mute (muter_id, muted_id),
    INDEX idx_chat_mute_muter (muter_id),
    CONSTRAINT fk_chat_mutes_muter FOREIGN KEY (muter_id) REFERENCES utenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_mutes_muted FOREIGN KEY (muted_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_typing (
    user_id INT PRIMARY KEY,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_chat_typing_user FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_word_filters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    word VARCHAR(80) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
