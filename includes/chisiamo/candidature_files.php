<?php

/**
 * I file delle candidature per Chi siamo, senza dipendenze: li usano la
 * pagina della candidatura, il pannello e la cancellazione degli account
 * (account_data_helpers.php), che non deve tirarsi dietro lo shop.
 */

/** Foto delle candidature: fuori da img/, bloccate da .htaccess, le vede solo il pannello. */
const CHISIAMO_CANDIDATURE_DIR = __DIR__ . '/../../uploads/candidature';

/** Le candidature si tengono al massimo 12 mesi (privacy, "Per quanto tempo"). */
const CHISIAMO_CANDIDATURE_MESI = 12;

/** Percorso assoluto della foto di una candidatura, solo se e' davvero nella sua cartella. */
function chisiamo_candidatura_foto_path(?string $name): ?string
{
    $name = trim((string)$name);
    if ($name === '' || !preg_match('~^[a-z0-9_-]{1,80}\.(?:jpe?g|png|webp)$~i', $name)) {
        return null;
    }

    $path = CHISIAMO_CANDIDATURE_DIR . '/' . $name;
    return is_file($path) ? $path : null;
}

function chisiamo_candidatura_delete_foto(?string $name): void
{
    $path = chisiamo_candidatura_foto_path($name);
    if ($path !== null) {
        @unlink($path);
    }
}

/** Le foto delle candidature di un utente, prima che la cancellazione dell'account tolga le righe. */
function chisiamo_delete_user_candidature_files(mysqli $mysqli, int $userId): void
{
    try {
        $exists = $mysqli->query("SHOW TABLES LIKE 'team_candidature'");
        if (!$exists || $exists->num_rows === 0) {
            return;
        }
        $exists->free();

        $stmt = $mysqli->prepare('SELECT foto FROM team_candidature WHERE utente_id = ?');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            chisiamo_candidatura_delete_foto($row['foto']);
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[candidature] ' . $e->getMessage());
    }
}

/**
 * Cancella le candidature piu' vecchie di 12 mesi con le loro foto. Gira
 * con la pulizia oraria di account_cleanup_security_logs().
 */
function chisiamo_cleanup_candidature(mysqli $mysqli): void
{
    try {
        $exists = $mysqli->query("SHOW TABLES LIKE 'team_candidature'");
        if (!$exists || $exists->num_rows === 0) {
            return;
        }
        $exists->free();

        $res = $mysqli->query(
            'SELECT id, foto FROM team_candidature WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . CHISIAMO_CANDIDATURE_MESI . ' MONTH) LIMIT 500'
        );
        $ids = [];
        while ($res && ($row = $res->fetch_assoc())) {
            chisiamo_candidatura_delete_foto($row['foto']);
            $ids[] = (int)$row['id'];
        }

        if ($ids) {
            $mysqli->query('DELETE FROM team_candidature WHERE id IN (' . implode(',', $ids) . ')');
        }
    } catch (Throwable $e) {
        error_log('[candidature cleanup] ' . $e->getMessage());
    }
}
