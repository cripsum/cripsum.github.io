<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — la pulizia, senza cron.
 *
 * Al posto di un lavoro a orario (che su Hostinger va impostato a mano) la
 * fa una richiesta dei duelli qualsiasi, dopo aver risposto al browser, al
 * massimo una volta al minuto: chi la fa lo decide una sola UPDATE su
 * game_settings.cleanup_at (la prima che la cambia vince, le altre no).
 *
 * Cosa fa: turni scaduti delle partite che nessuno sta guardando, chi e'
 * sparito dalla coda, stanze con codice scadute, partite col bot lasciate a
 * meta' da piu' di due ore. Dalla fase 6 anche: sfide scadute, promemoria
 * delle partite in differita, fine della stagione con premi e reset.
 */

require_once __DIR__ . '/lobby.php';

const DUEL_CLEANUP_SECONDS = 60;
const DUEL_BOT_IDLE_HOURS = 2;

/** Vero se tocca a questa richiesta fare la pulizia. */
function duel_maintenance_claim(mysqli $m): bool
{
    duel_exec($m, "INSERT IGNORE INTO game_settings (k, v) VALUES ('cleanup_at', '0')");
    return duel_exec($m, "UPDATE game_settings SET v = UNIX_TIMESTAMP()
        WHERE k = 'cleanup_at' AND CAST(v AS UNSIGNED) <= UNIX_TIMESTAMP() - ?", 'i', [DUEL_CLEANUP_SECONDS]) === 1;
}

function duel_maintenance(mysqli $m, bool $force = false): array
{
    $done = ['claimed' => false, 'timeouts' => 0, 'queue' => 0, 'rooms' => 0, 'bot' => 0, 'challenges' => 0, 'reminded' => 0, 'season' => null];
    if (!duel_db_ready($m) || (!$force && !duel_maintenance_claim($m))) {
        return $done;
    }
    $done['claimed'] = true;

    // Turni scaduti: uno per partita a giro (ognuno ha poi il suo tempo pieno).
    $expired = duel_rows($m, "SELECT id FROM game_matches
        WHERE engine_version IS NOT NULL AND status = 'active' AND turn_deadline IS NOT NULL
          AND turn_deadline < NOW() - INTERVAL ? SECOND ORDER BY turn_deadline LIMIT 20", 'i', [DUEL_DEADLINE_GRACE]);
    foreach ($expired as $row) {
        try {
            $done['timeouts'] += duel_tick($m, (int)$row['id']) ? 1 : 0;
        } catch (Throwable $e) {
            error_log('[duel pulizia] partita ' . (int)$row['id'] . ': ' . $e->getMessage());
        }
    }

    // Chi non manda piu' il segnale esce dalla coda.
    foreach (duel_rows($m, 'SELECT user_id FROM game_queue WHERE last_seen < NOW() - INTERVAL ? SECOND', 'i', [DUEL_QUEUE_STALE]) as $row) {
        if (duel_exec($m, 'DELETE FROM game_queue WHERE user_id = ? AND last_seen < NOW() - INTERVAL ? SECOND', 'ii', [(int)$row['user_id'], DUEL_QUEUE_STALE]) > 0) {
            duel_notice_close($m, (int)$row['user_id'], 'gone');
            $done['queue']++;
        }
    }

    $done['rooms'] = duel_exec($m, "UPDATE game_matches SET status = 'cancelled', end_reason = 'expired', finished_at = NOW(), updated_at = NOW()
        WHERE engine_version IS NOT NULL AND status = 'waiting' AND created_at < NOW() - INTERVAL ? MINUTE", 'i', [DUEL_ROOM_MINUTES]);

    // Una partita col bot lasciata a meta' non conta: si annulla.
    $idle = duel_rows($m, "SELECT id FROM game_matches WHERE engine_version IS NOT NULL AND status = 'active' AND mode = 'bot'
        AND updated_at < NOW() - INTERVAL ? HOUR LIMIT 50", 'i', [DUEL_BOT_IDLE_HOURS]);
    foreach ($idle as $row) {
        $done['bot'] += duel_exec($m, "UPDATE game_matches SET status = 'cancelled', end_reason = 'idle', finished_at = NOW(), turn_deadline = NULL
            WHERE id = ? AND status = 'active'", 'i', [(int)$row['id']]);
        duel_stamp((int)$row['id'], ['st' => 'cancelled', 'dl' => 0]);
    }

    // Fase 6: sfide, promemoria in differita, stagioni.
    try {
        $social = duel_social_maintenance($m);
        $done['challenges'] = $social['expired'];
        $done['reminded'] = $social['reminded'];
        $done['season'] = duel_season_tick($m);
    } catch (Throwable $e) {
        error_log('[duel pulizia] fase 6: ' . $e->getMessage());
    }

    return $done;
}
