<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — gli endpoint (api/duel/*.php, ognuno chiama duel_api_main()).
 *
 * Regole comuni:
 * - serve l'accesso; finche' i duelli non sono aperti a tutti (game_settings
 *   public = 1) entra solo lo staff;
 * - le azioni che cambiano qualcosa sono solo POST con JSON e il token
 *   X-CSRF-Token della pagina; le letture solo GET;
 * - al browser vanno messaggi semplici con un codice (`code`); i dettagli
 *   degli errori del server restano nel log con un riferimento (`ref`);
 * - la sessione si chiude subito dopo i controlli, cosi' le richieste
 *   dell'arena (stato, mossa, chat) non si mettono in fila tra loro;
 * - dopo la risposta partono gli avvisi Discord e, una volta al minuto, la
 *   pulizia (maint.php).
 *
 * Risposte: {"ok": true, ...} oppure {"ok": false, "error": "...", "code": "..."}.
 */

// Fuori da ogni funzione: config/database.php crea $mysqli nello spazio in
// cui viene incluso, e qui (incluso dal file dell'endpoint) e' quello globale.
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/maint.php';

/** Gli endpoint: metodo e funzione. */
function duel_api_routes(): array
{
    return [
        'hub' => ['GET', 'duel_ep_hub'],
        'roster' => ['GET', 'duel_ep_roster'],
        'team' => ['ANY', 'duel_ep_team'],
        'queue' => ['POST', 'duel_ep_queue'],
        'room' => ['POST', 'duel_ep_room'],
        'bot' => ['POST', 'duel_ep_bot'],
        'state' => ['GET', 'duel_ep_state'],
        'act' => ['POST', 'duel_ep_act'],
        'forfeit' => ['POST', 'duel_ep_forfeit'],
        'rematch' => ['POST', 'duel_ep_rematch'],
        'chat' => ['POST', 'duel_ep_chat'],
        'react' => ['POST', 'duel_ep_react'],
        'replay' => ['GET', 'duel_ep_replay'],
        'friends' => ['GET', 'duel_ep_friends'],
        'challenge' => ['POST', 'duel_ep_challenge'],
        'season' => ['GET', 'duel_ep_season'],
        'daily' => ['ANY', 'duel_ep_daily'],
    ];
}

function duel_api_main(string $endpoint): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');

    global $mysqli;
    $m = $mysqli;
    $route = duel_api_routes()[$endpoint] ?? null;
    if ($route === null) {
        duel_api_send(['ok' => false, 'error' => 'Not found', 'code' => 'NOT_FOUND'], 404);
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        duel_api_send(['ok' => false, 'error' => rt_t('Devi accedere.', 'You need to log in.'), 'code' => 'AUTH'], 401);
    }
    if (function_exists('checkBan')) {
        checkBan($m);
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $isPost = $method === 'POST';
    if (($route[0] === 'GET' && $method !== 'GET') || ($route[0] === 'POST' && !$isPost) || !in_array($method, ['GET', 'POST'], true)) {
        header('Allow: ' . ($route[0] === 'ANY' ? 'GET, POST' : $route[0]));
        duel_api_send(['ok' => false, 'error' => 'Method not allowed', 'code' => 'METHOD'], 405);
    }

    $in = [];
    if ($isPost) {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) {
            duel_api_send(['ok' => false, 'error' => rt_t('Richiesta non valida.', 'Invalid request.'), 'code' => 'BAD_JSON'], 400);
        }
        $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!function_exists('csrf_validate') || !csrf_validate($token)) {
            duel_api_send(['ok' => false, 'error' => rt_t('Sessione scaduta. Ricarica la pagina.', 'Session expired. Reload the page.'), 'code' => 'CSRF'], 419);
        }
    } else {
        $in = $_GET;
    }

    if (function_exists('cripsum_release_session')) {
        cripsum_release_session();
    }

    if (!duel_db_ready($m)) {
        duel_api_send(['ok' => false, 'error' => rt_t('I nuovi duelli non sono ancora disponibili.', 'The new duels are not available yet.'), 'code' => 'NOT_READY'], 503);
    }
    if (!duel_open_for($m)) {
        duel_api_send(['ok' => false, 'error' => rt_t('I nuovi duelli arrivano presto.', 'The new duels are coming soon.'), 'code' => 'CLOSED'], 403);
    }

    try {
        $out = ($route[1])($m, $userId, $in, $isPost);
        duel_api_send(['ok' => true] + $out, 200, $m);
    } catch (DuelUserError $e) {
        duel_api_send(['ok' => false, 'error' => $e->getMessage(), 'code' => $e->key] + $e->extra, $e->status, $m);
    } catch (Throwable $e) {
        $ref = bin2hex(random_bytes(4));
        error_log("[duel $ref] $endpoint: " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        duel_api_send(['ok' => false, 'error' => rt_t('Qualcosa è andato storto. Riprova.', 'Something went wrong. Try again.'), 'code' => 'SERVER', 'ref' => $ref], 500, $m);
    }
}

/**
 * Risponde e chiude la connessione col browser; poi, con il database
 * ancora aperto, avvisi Discord e pulizia.
 */
function duel_api_send(array $payload, int $status = 200, ?mysqli $m = null): never
{
    http_response_code($status);
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($m === null) {
        echo $body;
        exit;
    }
    ignore_user_abort(true);
    header('Content-Length: ' . strlen((string)$body));
    header('Connection: close');
    echo $body;
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    } elseif (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    try {
        duel_discord_flush($m);
        duel_maintenance($m);
        duel_discord_flush($m);
    } catch (Throwable $e) {
        error_log('[duel] dopo la risposta: ' . $e->getMessage());
    }
    exit;
}

function duel_in_int(array $in, string $key, int $default = 0): int
{
    return isset($in[$key]) && is_numeric($in[$key]) ? (int)$in[$key] : $default;
}

function duel_in_str(array $in, string $key, int $max = 64): string
{
    return isset($in[$key]) && is_scalar($in[$key]) ? mb_substr(trim((string)$in[$key]), 0, $max) : '';
}

// ─────────────────────────────────────────────────────────────────────────
// Lobby, squadre, collezione
// ─────────────────────────────────────────────────────────────────────────

function duel_ep_hub(mysqli $m, int $userId): array
{
    return duel_hub($m, $userId);
}

function duel_ep_roster(mysqli $m, int $userId): array
{
    return ['characters' => duel_roster($m, $userId), 'budget' => duel_rules()['ranked_budget']];
}

function duel_ep_team(mysqli $m, int $userId, array $in, bool $isPost): array
{
    if ($isPost) {
        $action = duel_in_str($in, 'action', 12);
        $slot = duel_in_int($in, 'slot');
        if ($action === 'delete') {
            duel_team_delete($m, $userId, $slot);
        } elseif ($action === 'save') {
            $team = is_array($in['team'] ?? null) ? $in['team'] : [];
            duel_team_save($m, $userId, $slot, duel_in_str($in, 'name', 80), $team);
        } else {
            throw duel_user_error('Azione non valida.', 'Invalid action.', 400, 'BAD_ACTION');
        }
    }
    $teams = duel_teams($m, $userId);
    duel_use_current_values($m);
    $chars = duel_db_characters($m, array_merge(...array_map(static fn($t) => $t['team'], $teams ?: [['team' => []]])));
    foreach ($teams as $i => $t) {
        $rarities = [];
        foreach ($t['team'] as $pid) {
            if (isset($chars[$pid])) {
                $rarities[] = duel_rarity_key((string)$chars[$pid]['rarita'], !empty($chars[$pid]['limitato']));
            }
        }
        $teams[$i]['cost'] = duel_team_cost($rarities);
    }
    return ['teams' => $teams, 'budget' => duel_rules()['ranked_budget']];
}

// ─────────────────────────────────────────────────────────────────────────
// Trovare una partita
// ─────────────────────────────────────────────────────────────────────────

function duel_ep_queue(mysqli $m, int $userId, array $in): array
{
    $action = duel_in_str($in, 'action', 12);
    if ($action === 'join') {
        return duel_queue_join($m, $userId, duel_in_str($in, 'mode', 12), duel_team_input($m, $userId, $in));
    }
    if ($action === 'ping') {
        return duel_queue_ping($m, $userId);
    }
    if ($action === 'leave') {
        duel_queue_leave($m, $userId);
        return ['status' => 'idle'];
    }
    throw duel_user_error('Azione non valida.', 'Invalid action.', 400, 'BAD_ACTION');
}

function duel_ep_room(mysqli $m, int $userId, array $in): array
{
    $action = duel_in_str($in, 'action', 12);
    if ($action === 'create') {
        return duel_room_create($m, $userId, duel_team_input($m, $userId, $in), [
            'levels' => duel_in_str($in, 'levels', 8), 'cost_limit' => !empty($in['cost_limit']),
        ]);
    }
    if ($action === 'join') {
        return duel_room_join($m, $userId, duel_in_str($in, 'code', 16), duel_team_input($m, $userId, $in));
    }
    throw duel_user_error('Azione non valida.', 'Invalid action.', 400, 'BAD_ACTION');
}

/**
 * Fase 8: la sfida del giorno. GET: lo scenario di oggi, il tuo risultato e
 * la classifica; POST action=start con la squadra (slot): la partita.
 */
function duel_ep_daily(mysqli $m, int $userId, array $in): array
{
    require_once __DIR__ . '/daily.php';
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        return duel_daily_start($m, $userId, duel_team_input($m, $userId, $in));
    }
    return ['daily' => duel_daily_view($m, $userId, rt_lang())];
}

function duel_ep_bot(mysqli $m, int $userId, array $in): array
{
    // keep_queue: si gioca col bot mentre si aspetta in coda (la lobby lo propone dopo un minuto).
    return duel_start_bot($m, $userId, duel_team_input($m, $userId, $in), duel_in_str($in, 'level', 12), !empty($in['keep_queue']));
}

// ─────────────────────────────────────────────────────────────────────────
// Fase 6: amici, sfide, stagione
// ─────────────────────────────────────────────────────────────────────────

/** Gli amici da sfidare (chi e' online, fascia, sfida gia' aperta) e le tue squadre. */
function duel_ep_friends(mysqli $m, int $userId): array
{
    return ['friends' => duel_friends($m, $userId), 'teams' => duel_teams($m, $userId), 'challenges' => duel_challenges_for($m, $userId)];
}

/** Sfide: send (to, kind live|async, levels, cost_limit, slot|team), accept (id, slot|team), decline, cancel (id). */
function duel_ep_challenge(mysqli $m, int $userId, array $in): array
{
    $action = duel_in_str($in, 'action', 12);
    if ($action === 'send') {
        return duel_challenge_send($m, $userId, duel_in_int($in, 'to'), duel_in_str($in, 'kind', 8), duel_in_str($in, 'levels', 4),
            !empty($in['cost_limit']), duel_team_input($m, $userId, $in));
    }
    if ($action === 'accept') {
        return duel_challenge_accept($m, $userId, duel_in_int($in, 'id'), duel_team_input($m, $userId, $in));
    }
    if ($action === 'decline' || $action === 'cancel') {
        duel_challenge_close($m, $userId, duel_in_int($in, 'id'), $action);
        return ['challenges' => duel_challenges_for($m, $userId)];
    }
    throw duel_user_error('Azione non valida.', 'Invalid action.', 400, 'BAD_ACTION');
}

function duel_ep_season(mysqli $m, int $userId): array
{
    return duel_season_page($m, $userId);
}

// ─────────────────────────────────────────────────────────────────────────
// La partita
// ─────────────────────────────────────────────────────────────────────────

/**
 * Lo stato di una partita per chi la guarda. `since` = l'ultima versione che
 * il browser ha: riceve gli eventi successivi; -1 (o se e' troppo indietro)
 * la fotografia intera. `c` e `r`: ultimo messaggio di chat e ultima
 * reazione visti.
 */
function duel_ep_state(mysqli $m, int $userId, array $in): array
{
    $matchId = duel_in_int($in, 'm');
    return duel_state_payload($m, $userId, $matchId, duel_in_int($in, 'since', -1), duel_in_int($in, 'c', -1), duel_in_int($in, 'r', -1));
}

function duel_state_payload(mysqli $m, int $userId, int $matchId, int $since, int $chatSince = -1, int $reactSince = -1): array
{
    $row = duel_match_row($m, $matchId);
    if (!$row) {
        throw duel_user_error('Partita non trovata.', 'Match not found.', 404, 'NOT_FOUND');
    }
    $side = duel_match_side($row, $userId);
    if ($side === null) {
        if ((int)$row['spectator_allowed'] !== 1 || duel_is_bot_match($row) || !in_array((string)$row['status'], ['active', 'finished'], true)) {
            throw duel_user_error('Non puoi guardare questa partita.', 'You can\'t watch this match.', 403, 'NO_SPECTATE');
        }
        duel_touch_spectator($m, $matchId, $userId);
    }

    if ((string)$row['status'] === 'active' && !empty($row['turn_deadline'])
        && strtotime((string)$row['turn_deadline']) + DUEL_DEADLINE_GRACE <= time()) {
        duel_tick($m, $matchId);
        $row = duel_match_row($m, $matchId);
    }

    $out = ['m' => $matchId, 'v' => (int)$row['version'], 'status' => (string)$row['status'], 'side' => $side, 'now' => time(), 'mode' => (string)$row['mode']];
    if ((string)$row['status'] === 'waiting') {
        $rules = duel_match_rules($row);
        $invite = (int)($rules['room']['invite'] ?? 0);
        return $out + ['code' => $side === 0 ? (string)$row['room_code'] : null,
            'expires' => strtotime((string)$row['created_at']) + DUEL_ROOM_MINUTES * 60,
            'rules' => ['levels' => $rules['levels'], 'cost_limit' => (int)$rules['cost_limit']],
            // Stanza di una sfida a un amico: si aspetta lui, non un codice.
            'invite' => $invite > 0 ? duel_username($m, $invite) : null];
    }
    if ((string)$row['status'] === 'cancelled') {
        // declined: l'amico ha rifiutato la sfida; queue: col bot in attesa, e' arrivato un avversario.
        return $out + ['reason' => (string)($row['end_reason'] ?? ''), 'next' => duel_active_for($m, $userId)['id'] ?? null];
    }

    duel_use_match_values(duel_match_rules($row));
    $st = duel_match_state($row);
    $version = (int)$row['version'];
    if ($since < 0 || $since > $version || $version - $since > 40) {
        $out['full'] = true;
        $out['static'] = duel_view_static($m, $row, $st);
    } else {
        $out['events'] = duel_events_since($m, $matchId, $since);
    }
    $out['dyn'] = duel_view_dyn($row, $st, $side);

    if ($side !== null && rt_has_table($m, 'game_match_chat')) {
        $out['chat'] = duel_chat_since($m, $matchId, $chatSince);
    }
    if (rt_has_table($m, 'game_match_reactions')) {
        $out['reactions'] = duel_reactions_since($m, $matchId, $reactSince);
    }
    if (!duel_is_bot_match($row)) {
        $out['spectators'] = duel_spectator_count($m, $matchId);
        // Chi gioca lo legge dal timbro (rt.php), senza chiedere lo stato.
        if ($side === null) {
            duel_stamp($matchId, ['s' => $out['spectators']]);
        }
    }
    return $out;
}

/** Chi sta guardando adesso (visto negli ultimi 35 secondi). */
function duel_spectator_count(mysqli $m, int $matchId): int
{
    if (!rt_has_table($m, 'game_spectators')) {
        return 0;
    }
    foreach (['last_seen_at', 'last_seen', 'updated_at'] as $col) {
        if (rt_has_col($m, 'game_spectators', $col)) {
            return (int)(duel_row($m, "SELECT COUNT(*) AS n FROM game_spectators WHERE match_id = ? AND `$col` >= NOW() - INTERVAL 35 SECOND", 'i', [$matchId])['n'] ?? 0);
        }
    }
    return 0;
}

function duel_ep_act(mysqli $m, int $userId, array $in): array
{
    $matchId = duel_in_int($in, 'm');
    $version = duel_in_int($in, 'v', -1);
    $move = duel_in_str($in, 'move', 12);
    $clientId = preg_replace('/[^A-Za-z0-9_-]/', '', duel_in_str($in, 'cid', 24));
    if (!in_array($move, ['attacco', 'abilita', 'ultimate', 'guardia', 'passa'], true)) {
        throw duel_user_error('Mossa non valida.', 'Invalid move.', 400, 'BAD_MOVE');
    }
    $result = duel_play($m, $matchId, $userId, $version, $move, duel_in_int($in, 'target'), (string)$clientId);
    if (($result['error'] ?? '') === 'TIMED_OUT') {
        throw duel_user_error('Il tempo del turno è scaduto.', 'Your turn timed out.', 409, 'TIMED_OUT', ['v' => $result['v']]);
    }
    // Gli eventi della mossa (e del bot) subito, senza un'altra richiesta.
    return ['duplicate' => !empty($result['duplicate'])] + duel_state_payload($m, $userId, $matchId, max(0, $version));
}

function duel_ep_forfeit(mysqli $m, int $userId, array $in): array
{
    return duel_forfeit_by($m, duel_in_int($in, 'm'), $userId);
}

function duel_ep_rematch(mysqli $m, int $userId, array $in): array
{
    return duel_rematch($m, $userId, duel_in_int($in, 'm'));
}

function duel_ep_replay(mysqli $m, int $userId, array $in): array
{
    $row = duel_match_row($m, duel_in_int($in, 'm'));
    if (!$row || (string)$row['status'] !== 'finished') {
        throw duel_user_error('Replay non disponibile.', 'Replay not available.', 404, 'NO_REPLAY');
    }
    $side = duel_match_side($row, $userId);
    if ($side === null && ((int)$row['spectator_allowed'] !== 1 || duel_is_bot_match($row))) {
        throw duel_user_error('Replay non disponibile.', 'Replay not available.', 403, 'NO_REPLAY');
    }
    return ['replay' => duel_replay($m, $row)];
}

// ─────────────────────────────────────────────────────────────────────────
// Chat dei giocatori, reazioni degli spettatori
// ─────────────────────────────────────────────────────────────────────────

function duel_chat_since(mysqli $m, int $matchId, int $since): array
{
    $rows = $since < 0
        ? array_reverse(duel_rows($m, 'SELECT c.id, c.user_id, c.message, c.created_at, u.username FROM game_match_chat c LEFT JOIN utenti u ON u.id = c.user_id
            WHERE c.match_id = ? ORDER BY c.id DESC LIMIT 30', 'i', [$matchId]))
        : duel_rows($m, 'SELECT c.id, c.user_id, c.message, c.created_at, u.username FROM game_match_chat c LEFT JOIN utenti u ON u.id = c.user_id
            WHERE c.match_id = ? AND c.id > ? ORDER BY c.id LIMIT 50', 'ii', [$matchId, $since]);
    return array_map(static fn($r) => ['id' => (int)$r['id'], 'user' => (int)$r['user_id'], 'name' => (string)($r['username'] ?? ''),
        'text' => (string)$r['message'], 'at' => (string)$r['created_at']], $rows);
}

function duel_reactions_since(mysqli $m, int $matchId, int $since): array
{
    $rows = $since < 0
        ? array_reverse(duel_rows($m, 'SELECT r.id, r.user_id, r.reaction, u.username FROM game_match_reactions r LEFT JOIN utenti u ON u.id = r.user_id
            WHERE r.match_id = ? ORDER BY r.id DESC LIMIT 10', 'i', [$matchId]))
        : duel_rows($m, 'SELECT r.id, r.user_id, r.reaction, u.username FROM game_match_reactions r LEFT JOIN utenti u ON u.id = r.user_id
            WHERE r.match_id = ? AND r.id > ? ORDER BY r.id LIMIT 30', 'ii', [$matchId, $since]);
    return array_map(static fn($r) => ['id' => (int)$r['id'], 'user' => (int)$r['user_id'], 'name' => (string)($r['username'] ?? ''),
        'r' => (string)$r['reaction']], $rows);
}

function duel_touch_spectator(mysqli $m, int $matchId, int $userId): void
{
    if (!rt_has_table($m, 'game_spectators')) {
        return;
    }
    foreach (['last_seen_at', 'last_seen', 'updated_at'] as $col) {
        if (rt_has_col($m, 'game_spectators', $col)) {
            duel_exec($m, "INSERT INTO game_spectators (match_id, user_id, `$col`) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE `$col` = NOW()", 'ii', [$matchId, $userId]);
            return;
        }
    }
    duel_exec($m, 'INSERT IGNORE INTO game_spectators (match_id, user_id) VALUES (?, ?)', 'ii', [$matchId, $userId]);
}

/** Chat tra i due giocatori: 220 caratteri, un messaggio al secondo. Gli spettatori non la vedono. */
function duel_ep_chat(mysqli $m, int $userId, array $in): array
{
    $matchId = duel_in_int($in, 'm');
    $row = duel_match_row($m, $matchId);
    if (!$row || duel_match_side($row, $userId) === null || duel_is_bot_match($row)) {
        throw duel_user_error('Chat non disponibile.', 'Chat not available.', 403, 'NO_CHAT');
    }
    $text = trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace('/[\p{C}]+/u', ' ', (string)($in['text'] ?? ''))));
    if ($text === '') {
        throw duel_user_error('Scrivi un messaggio.', 'Write a message.', 400, 'EMPTY');
    }
    if (mb_strlen($text) > 220) {
        throw duel_user_error('Messaggio troppo lungo (220 caratteri al massimo).', 'Message too long (220 characters max).', 400, 'TOO_LONG');
    }
    if (duel_row($m, 'SELECT 1 FROM game_match_chat WHERE match_id = ? AND user_id = ? AND created_at >= NOW() - INTERVAL 1 SECOND LIMIT 1', 'ii', [$matchId, $userId])) {
        throw duel_user_error('Piano: un messaggio alla volta.', 'Slow down: one message at a time.', 429, 'RATE_LIMIT');
    }
    $stmt = duel_q($m, 'INSERT INTO game_match_chat (match_id, user_id, message) VALUES (?, ?, ?)', 'iis', [$matchId, $userId, $text]);
    $id = (int)$stmt->insert_id;
    $stmt->close();
    duel_stamp($matchId, ['c' => $id]);
    return ['id' => $id];
}

const DUEL_REACTIONS = ['🔥', '💀', '👏', '😳', '⚡', '👀'];

/** Reazioni: solo per chi guarda, una al secondo. */
function duel_ep_react(mysqli $m, int $userId, array $in): array
{
    $matchId = duel_in_int($in, 'm');
    $reaction = duel_in_str($in, 'r', 32);
    $valid = in_array($reaction, DUEL_REACTIONS, true)
        || (rt_has_table($m, 'game_emojis') && duel_row($m, 'SELECT 1 FROM game_emojis WHERE code = ? LIMIT 1', 's', [$reaction]));
    if (!$valid) {
        throw duel_user_error('Reazione non valida.', 'Invalid reaction.', 400, 'BAD_REACTION');
    }
    $row = duel_match_row($m, $matchId);
    if (!$row || (int)$row['spectator_allowed'] !== 1 || duel_is_bot_match($row) || !in_array((string)$row['status'], ['active', 'finished'], true)) {
        throw duel_user_error('Non puoi reagire a questa partita.', 'You can\'t react to this match.', 403, 'NO_SPECTATE');
    }
    if (duel_match_side($row, $userId) !== null) {
        throw duel_user_error('Le reazioni sono per chi guarda.', 'Reactions are for spectators.', 403, 'PLAYER');
    }
    if (duel_row($m, 'SELECT 1 FROM game_match_reactions WHERE match_id = ? AND user_id = ? AND created_at >= NOW() - INTERVAL 1 SECOND LIMIT 1', 'ii', [$matchId, $userId])) {
        throw duel_user_error('Piano!', 'Slow down!', 429, 'RATE_LIMIT');
    }
    duel_touch_spectator($m, $matchId, $userId);
    $stmt = duel_q($m, 'INSERT INTO game_match_reactions (match_id, user_id, reaction) VALUES (?, ?, ?)', 'iis', [$matchId, $userId, $reaction]);
    $id = (int)$stmt->insert_id;
    $stmt->close();
    duel_stamp($matchId, ['r' => $id]);
    return ['id' => $id];
}
