-- Cripsum™ — Pullspot
--
-- Una riga per giocatore e per giorno. Senza questa tabella il gioco funziona
-- lo stesso, ma la partita vive solo in sessione: niente statistiche, niente
-- serie di vittorie, e cambiando dispositivo si ricomincia la giornata.

CREATE TABLE IF NOT EXISTS `pullspot_partite` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `utente_id`       INT NOT NULL,
    `giorno`          DATE NOT NULL,
    `personaggio_id`  INT NOT NULL,
    -- JSON dei tentativi: [{"type":"skip|wrong|correct","id":12,"nome":"..."}]
    `tentativi`       TEXT NOT NULL,
    `esito`           ENUM('in_corso', 'vinto', 'perso') NOT NULL DEFAULT 'in_corso',
    `tentativi_usati` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `creato_il`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aggiornato_il`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_pullspot_utente_giorno` (`utente_id`, `giorno`),
    KEY `idx_pullspot_utente_esito` (`utente_id`, `esito`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
