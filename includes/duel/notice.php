<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — l'avviso su Discord quando qualcuno cerca una partita.
 *
 * L'unico messaggio dei duelli su Discord (decisione del 19/10/2026): chi e'
 * in coda da 20 secondi senza avversario fa scrivere al bot nel canale dei
 * duelli «X cerca una partita Ranked»; quando la partita parte il messaggio
 * diventa «partita trovata», se smette di cercare «ricerca finita».
 * Al massimo un avviso ogni 30 minuti per giocatore e uno ogni 5 minuti in
 * tutto. Solo con i duelli aperti a tutti e l'interruttore `discord` acceso.
 * L'altro messaggio e' quello della Torre conquistata (tower.php, op 'post').
 *
 * Il bot (presence-bot) pubblica con /v1/announce, target «duelli» (canale
 * DISCORD_DUELS_CHANNEL_ID nel suo .env, o quello che si chiama «duelli»),
 * e cambia il messaggio con /v1/announce/edit. Le chiamate partono dopo
 * aver risposto al browser: chi gioca non aspetta Discord.
 */

require_once __DIR__ . '/store.php';

const DUEL_NOTICE_AFTER = 20;        // secondi in coda prima dell'avviso
const DUEL_NOTICE_USER_MINUTES = 30; // un avviso ogni 30 minuti per giocatore
const DUEL_NOTICE_ALL_MINUTES = 5;   // uno ogni 5 minuti in tutto
const DUEL_LOBBY_URL = 'https://cripsum.com/it/duelli/';

function duel_notice_enabled(mysqli $m): bool
{
    $settings = duel_settings($m);
    return $settings['public'] === '1' && $settings['discord'] === '1' && rt_has_table($m, 'game_duel_notices');
}

/** Colore del rango, come gli emblemi dei duelli. */
function duel_notice_color(string $mode, int $rating): string
{
    if ($mode !== 'ranked') {
        return '#a1a1a6';
    }
    return ['leggenda' => '#ff453a', 'campione' => '#a855f7', 'diamante' => '#22b8cf', 'oro' => '#eab308',
        'argento' => '#c7c7cc', 'bronzo' => '#c8793a'][duel_rank($rating)['key']] ?? '#a1a1a6';
}

function duel_notice_mode_label(string $mode): string
{
    return $mode === 'ranked' ? 'Ranked' : 'Veloce';
}

/** Chiamata da duel_queue_pair(), gia' sotto il blocco della coda: niente doppioni. */
function duel_notice_maybe(mysqli $m, int $userId, string $mode, int $waited): void
{
    if ($waited < DUEL_NOTICE_AFTER || !duel_notice_enabled($m)) {
        return;
    }
    if (duel_row($m, 'SELECT id FROM game_duel_notices WHERE user_id = ? AND created_at >= NOW() - INTERVAL ? MINUTE LIMIT 1', 'ii', [$userId, DUEL_NOTICE_USER_MINUTES])) {
        return;
    }
    if (duel_row($m, 'SELECT id FROM game_duel_notices WHERE created_at >= NOW() - INTERVAL ? MINUTE LIMIT 1', 'i', [DUEL_NOTICE_ALL_MINUTES])) {
        return;
    }
    $stmt = duel_q($m, 'INSERT INTO game_duel_notices (user_id, mode) VALUES (?, ?)', 'is', [$userId, $mode]);
    $noticeId = (int)$stmt->insert_id;
    $stmt->close();

    $user = duel_row($m, 'SELECT u.username, s.rating FROM utenti u LEFT JOIN game_player_stats s ON s.user_id = u.id WHERE u.id = ?', 'i', [$userId]);
    $name = (string)($user['username'] ?? 'Qualcuno');
    $rating = (int)($user['rating'] ?? 1000);
    $rank = duel_rank($rating);
    $line = "**$name** cerca una partita **" . duel_notice_mode_label($mode) . '**';
    if ($mode === 'ranked') {
        $line .= ' · ' . $rank['label'] . ' (' . number_format($rating, 0, ',', '.') . ')';
    }
    duel_discord_queue([
        'op' => 'announce',
        'notice' => $noticeId,
        'payload' => [
            'target' => 'duelli',
            'title' => '⚔️ Cerca una partita',
            'description' => $line . "\nEntra nei Duelli e cerca anche tu: " . DUEL_LOBBY_URL,
            'url' => DUEL_LOBBY_URL,
            'color' => duel_notice_color($mode, $rating),
            'footer_text' => 'Duelli · cripsum.com',
        ],
    ]);
}

/**
 * La ricerca e' finita: 'found' (partita trovata, con $matchId) o 'gone'
 * (uscito dalla coda). Il messaggio su Discord cambia di conseguenza.
 */
function duel_notice_close(mysqli $m, int $userId, string $status, ?int $matchId = null): void
{
    if (!rt_has_table($m, 'game_duel_notices')) {
        return;
    }
    $open = duel_rows($m, "SELECT * FROM game_duel_notices WHERE user_id = ? AND status = 'open'", 'i', [$userId]);
    foreach ($open as $notice) {
        duel_exec($m, 'UPDATE game_duel_notices SET status = ?, closed_at = NOW() WHERE id = ?', 'si', [$status, (int)$notice['id']]);
        $payload = duel_notice_final($m, $notice + ['status' => $status], $matchId);
        if (!empty($notice['message_id'])) {
            duel_discord_queue(['op' => 'edit', 'payload' => ['channel_id' => (string)$notice['channel_id'], 'message_id' => (string)$notice['message_id']] + $payload]);
        }
    }
}

/** Il testo finale di un avviso. */
function duel_notice_final(mysqli $m, array $notice, ?int $matchId): array
{
    $userId = (int)$notice['user_id'];
    $mode = (string)$notice['mode'];
    $name = (string)(duel_row($m, 'SELECT username FROM utenti WHERE id = ?', 'i', [$userId])['username'] ?? 'Qualcuno');
    if ((string)$notice['status'] === 'found') {
        $other = '';
        if ($matchId) {
            $row = duel_row($m, 'SELECT u1.username AS a, u2.username AS b FROM game_matches g
                LEFT JOIN utenti u1 ON u1.id = g.player1_id LEFT JOIN utenti u2 ON u2.id = g.player2_id WHERE g.id = ?', 'i', [$matchId]);
            $other = (string)(($row['a'] ?? '') === $name ? ($row['b'] ?? '') : ($row['a'] ?? ''));
        }
        return [
            'title' => '✅ Partita trovata',
            'description' => $other !== ''
                ? "**$name** e **$other** si sfidano in " . duel_notice_mode_label($mode) . '.'
                : "**$name** ha trovato una partita " . duel_notice_mode_label($mode) . '.',
            'color' => '#30d158',
        ];
    }
    return [
        'title' => 'Ricerca finita',
        'description' => "**$name** non cerca più una partita.",
        'color' => '#6e6e73',
    ];
}

// ─────────────────────────────────────────────────────────────────────────
// Chiamate al bot, dopo la risposta
// ─────────────────────────────────────────────────────────────────────────

function duel_discord_queue(array $job): void
{
    $GLOBALS['__duel_discord'][] = $job;
}

function duel_discord_pending(): bool
{
    return !empty($GLOBALS['__duel_discord']);
}

/** Da chiamare dopo aver risposto al browser (api.php lo fa). */
function duel_discord_flush(mysqli $m): void
{
    $jobs = $GLOBALS['__duel_discord'] ?? [];
    $GLOBALS['__duel_discord'] = [];
    if (!$jobs || !function_exists('curl_init')) {
        return;
    }
    require_once __DIR__ . '/../bot_client.php';

    foreach (array_slice($jobs, 0, 6) as $job) {
        if ($job['op'] === 'announce') {
            $sent = duel_bot_post('/v1/announce', $job['payload']);
            if (!$sent || empty($sent['message_id'])) {
                continue;
            }
            duel_exec($m, 'UPDATE game_duel_notices SET channel_id = ?, message_id = ? WHERE id = ?', 'ssi',
                [(string)($sent['channel_id'] ?? ''), (string)$sent['message_id'], (int)$job['notice']]);
            // Se intanto la ricerca e' finita (partita trovata mentre il
            // messaggio partiva), il messaggio diventa subito quello finale.
            $notice = duel_row($m, 'SELECT * FROM game_duel_notices WHERE id = ?', 'i', [(int)$job['notice']]);
            if ($notice && (string)$notice['status'] !== 'open') {
                $matchId = null;
                if ((string)$notice['status'] === 'found') {
                    $match = duel_row($m, "SELECT id FROM game_matches WHERE engine_version IS NOT NULL AND (player1_id = ? OR player2_id = ?)
                        ORDER BY id DESC LIMIT 1", 'ii', [(int)$notice['user_id'], (int)$notice['user_id']]);
                    $matchId = $match ? (int)$match['id'] : null;
                }
                duel_bot_post('/v1/announce/edit', ['channel_id' => (string)$sent['channel_id'], 'message_id' => (string)$sent['message_id']]
                    + duel_notice_final($m, $notice, $matchId));
            }
        } elseif ($job['op'] === 'edit') {
            duel_bot_post('/v1/announce/edit', $job['payload']);
        } elseif ($job['op'] === 'post') {
            // Un messaggio che non cambia piu' (la Torre conquistata).
            duel_bot_post('/v1/announce', $job['payload']);
        }
    }
}

/** POST firmato al bot. Risposta decodificata, o null (registrato nel log). */
function duel_bot_post(string $path, array $payload): ?array
{
    $body = (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init(cripsum_bot_endpoint($path));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => cripsum_bot_headers([], $path, $body),
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
        error_log('[duel discord] ' . $path . ' (' . $status . '): ' . (is_string($response) ? substr($response, 0, 200) : 'nessuna risposta'));
        return null;
    }
    $data = json_decode((string)$response, true);
    return is_array($data) ? $data : [];
}
