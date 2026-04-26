-- Chat Globale V2.1 - GIF Tenor + reazioni
-- Fai backup prima.
-- Se una colonna esiste già, rimuovi quella riga.

ALTER TABLE messages
    ADD COLUMN message_type ENUM('text','gif') NOT NULL DEFAULT 'text' AFTER message,
    ADD COLUMN media_url TEXT NULL AFTER message_type,
    ADD COLUMN media_preview_url TEXT NULL AFTER media_url,
    ADD COLUMN media_title VARCHAR(160) NULL AFTER media_preview_url;

CREATE TABLE IF NOT EXISTS chat_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(16) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_chat_reaction_once (message_id, user_id, emoji),
    INDEX idx_chat_reaction_message (message_id),
    CONSTRAINT fk_chat_reactions_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_reactions_user FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
