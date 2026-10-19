<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — prima della partita: squadre salvate, coda Veloce e Ranked,
 * stanze con codice, partite contro il bot, rivincita, lobby.
 *
 * Una persona gioca una partita alla volta: entrare in coda, aprire una
 * stanza o sfidare il bot chiude la stanza che aveva aperto e la toglie
 * dalla coda; con una partita in corso si viene rimandati li'.
 */

require_once __DIR__ . '/store.php';
require_once __DIR__ . '/notice.php';

const DUEL_QUEUE_STALE = 15;       // secondi senza segnale: fuori dalla coda
const DUEL_ROOM_MINUTES = 10;      // una stanza con codice aspetta 10 minuti
const DUEL_TEAM_SLOTS = 3;
const DUEL_BOT_BUDGET = ['facile' => 8, 'normale' => 11, 'difficile' => 14];

// ─────────────────────────────────────────────────────────────────────────
// Squadre salvate
// ─────────────────────────────────────────────────────────────────────────

function duel_teams(mysqli $m, int $userId): array
{
    $out = [];
    foreach (duel_rows($m, 'SELECT slot, name, c1, c2, c3 FROM game_teams WHERE user_id = ? ORDER BY slot', 'i', [$userId]) as $r) {
        $out[] = ['slot' => (int)$r['slot'], 'name' => (string)$r['name'], 'team' => [(int)$r['c1'], (int)$r['c2'], (int)$r['c3']]];
    }
    return $out;
}

function duel_team_save(mysqli $m, int $userId, int $slot, string $name, array $pids): array
{
    if ($slot < 1 || $slot > DUEL_TEAM_SLOTS) {
        throw duel_user_error('Posto squadra non valido.', 'Invalid team slot.', 400, 'BAD_SLOT');
    }
    $name = trim((string)preg_replace('/[\p{C}]+/u', '', $name));
    $name = mb_substr($name, 0, 40);
    if ($name === '') {
        $name = rt_t('Squadra ', 'Team ') . $slot;
    }
    $team = duel_team_for_user($m, $userId, $pids, false);
    [$c1, $c2, $c3] = $team['pids'];
    duel_exec($m, 'INSERT INTO game_teams (user_id, slot, name, c1, c2, c3) VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE name = VALUES(name), c1 = VALUES(c1), c2 = VALUES(c2), c3 = VALUES(c3)', 'iisiii', [$userId, $slot, $name, $c1, $c2, $c3]);
    return ['slot' => $slot, 'name' => $name, 'team' => $team['pids'], 'cost' => $team['cost']];
}

function duel_team_delete(mysqli $m, int $userId, int $slot): void
{
    duel_exec($m, 'DELETE FROM game_teams WHERE user_id = ? AND slot = ?', 'ii', [$userId, $slot]);
}

/** La squadra scelta: tre id (`team`) o il posto di una squadra salvata (`slot`). */
function duel_team_input(mysqli $m, int $userId, array $in): array
{
    if (isset($in['team']) && is_array($in['team'])) {
        return array_slice(array_map('intval', $in['team']), 0, 3);
    }
    $slot = (int)($in['slot'] ?? 0);
    foreach (duel_teams($m, $userId) as $team) {
        if ($team['slot'] === $slot) {
            return $team['team'];
        }
    }
    throw duel_user_error('Scegli una squadra.', 'Pick a team.', 400, 'NO_TEAM');
}

// ─────────────────────────────────────────────────────────────────────────
// Una partita alla volta
// ─────────────────────────────────────────────────────────────────────────

/** La partita 2.0 aperta di un giocatore (in corso o stanza in attesa). */
function duel_active_for(mysqli $m, int $userId): ?array
{
    $row = duel_row($m, "SELECT id, status, mode, room_code FROM game_matches
        WHERE engine_version IS NOT NULL AND status IN ('waiting', 'active') AND (player1_id = ? OR player2_id = ?)
        ORDER BY (status = 'active') DESC, id DESC LIMIT 1", 'ii', [$userId, $userId]);
    if (!$row) {
        return null;
    }
    return ['id' => (int)$row['id'], 'status' => (string)$row['status'], 'mode' => (string)$row['mode'],
        'code' => (string)$row['status'] === 'waiting' ? (string)$row['room_code'] : null];
}

/**
 * Prima di cominciare qualcosa di nuovo: con una partita in corso si torna
 * li'; la stanza aperta si chiude e si esce dalla coda.
 */
function duel_require_free(mysqli $m, int $userId, bool $keepQueue = false): void
{
    $active = duel_row($m, "SELECT id FROM game_matches WHERE engine_version IS NOT NULL AND status = 'active'
        AND (player1_id = ? OR player2_id = ?) LIMIT 1", 'ii', [$userId, $userId]);
    if ($active) {
        throw duel_user_error('Hai già una partita in corso.', 'You already have a match in progress.', 409, 'IN_MATCH', ['match' => (int)$active['id']]);
    }
    duel_exec($m, "UPDATE game_matches SET status = 'cancelled', end_reason = 'closed', finished_at = NOW(), updated_at = NOW()
        WHERE engine_version IS NOT NULL AND status = 'waiting' AND player1_id = ?", 'i', [$userId]);
    if (!$keepQueue && duel_exec($m, 'DELETE FROM game_queue WHERE user_id = ?', 'i', [$userId]) > 0) {
        duel_notice_close($m, $userId, 'gone');
    }
}

// ─────────────────────────────────────────────────────────────────────────
// Contro il bot
// ─────────────────────────────────────────────────────────────────────────

/** Tutti i personaggi, per le squadre del bot. */
function duel_db_all_characters(mysqli $m): array
{
    static $all = null;
    if ($all === null) {
        $ids = array_map(static fn($r) => (int)$r['id'], duel_rows($m, 'SELECT id FROM personaggi'));
        $all = duel_db_characters($m, $ids);
    }
    return $all;
}

/**
 * La squadra del bot: con le regole vere (costo come in Ranked, piu' basso
 * per i livelli facili), almeno un attaccante e uno che difende o cura.
 */
function duel_bot_team(mysqli $m, string $level, int $cardLevel): array
{
    $budget = DUEL_BOT_BUDGET[$level] ?? 11;
    $attack = ['DPS', 'Burst DPS', 'Sub DPS', 'Bruiser'];
    $guard = ['Tank', 'Support', 'Healer'];
    $pool = [];
    foreach (duel_db_all_characters($m) as $c) {
        $rarity = duel_rarity_key((string)$c['rarita'], !empty($c['limitato']));
        $pool[] = ['id' => (int)$c['id'], 'role' => duel_role_key((string)$c['ruolo']), 'cost' => duel_rarity($rarity)['cost']];
    }
    if (count($pool) < 3) {
        throw new RuntimeException('personaggi insufficienti per il bot');
    }
    $best = null;
    for ($try = 0; $try < 400; $try++) {
        $pick = [];
        foreach (array_rand($pool, 3) as $i) {
            $pick[] = $pool[$i];
        }
        $cost = array_sum(array_column($pick, 'cost'));
        $roles = array_column($pick, 'role');
        if ($cost > $budget || !array_intersect($roles, $attack) || !array_intersect($roles, $guard)) {
            continue;
        }
        if ($best === null || $cost > $best[1]) {
            $best = [array_column($pick, 'id'), $cost];
        }
        if ($cost >= $budget - 1) {
            break;
        }
    }
    if ($best === null) {
        $best = [array_column(array_slice($pool, 0, 3), 'id'), 0];
    }
    $levels = array_fill_keys($best[0], $cardLevel);
    return duel_team_build($m, $best[0], $levels, false);
}

function duel_start_bot(mysqli $m, int $userId, array $pids, string $level): array
{
    if (!in_array($level, DUEL_BOT_LEVELS, true)) {
        $level = 'normale';
    }
    duel_require_free($m, $userId);
    $team = duel_team_for_user($m, $userId, $pids, false);
    $avg = (int)round(array_sum(array_column($team['kits'], 'level')) / 3);
    $bot = duel_bot_team($m, $level, max(1, $avg));
    $id = duel_tx($m, static fn() => duel_match_start($m, [
        'mode' => 'bot', 'p1' => $userId, 'p2' => 0, 'team1' => $team, 'team2' => $bot,
        'levels' => 'own', 'bot_level' => $level,
    ]));
    return ['match' => $id];
}

// ─────────────────────────────────────────────────────────────────────────
// Coda: Veloce e Ranked
// ─────────────────────────────────────────────────────────────────────────

function duel_player_rating(mysqli $m, int $userId): int
{
    return (int)(duel_row($m, 'SELECT rating FROM game_player_stats WHERE user_id = ?', 'i', [$userId])['rating'] ?? 1000);
}

function duel_queue_join(mysqli $m, int $userId, string $mode, array $pids): array
{
    if (!in_array($mode, ['casual', 'ranked'], true)) {
        throw duel_user_error('Modalità non valida.', 'Invalid mode.', 400, 'BAD_MODE');
    }
    duel_require_free($m, $userId, true);
    $ranked = $mode === 'ranked';
    $team = duel_team_for_user($m, $userId, $pids, $ranked);
    $budget = duel_rules()['ranked_budget'];
    if ($ranked && $team['cost'] > $budget) {
        throw duel_user_error("La squadra costa {$team['cost']}: in Ranked il massimo è $budget.",
            "The team costs {$team['cost']}: Ranked allows $budget at most.", 400, 'TEAM_COST', ['cost' => $team['cost'], 'budget' => $budget]);
    }
    $rating = duel_player_rating($m, $userId);
    $teamJson = json_encode($team['pids']);
    // Chi cambia modalita' riparte da capo; chi ripete l'iscrizione tiene il suo posto.
    duel_exec($m, 'INSERT INTO game_queue (user_id, mode, rating, team_json, team_cost, joined_at, last_seen) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE joined_at = IF(mode = VALUES(mode), joined_at, NOW()), mode = VALUES(mode), rating = VALUES(rating),
            team_json = VALUES(team_json), team_cost = VALUES(team_cost), last_seen = NOW()',
        'isisi', [$userId, $mode, $rating, $teamJson, $team['cost']]);
    return duel_queue_pair($m, $userId);
}

/** Il segnale di chi aspetta in coda: tiene il posto e prova ad abbinare. */
function duel_queue_ping(mysqli $m, int $userId): array
{
    if (duel_exec($m, 'UPDATE game_queue SET last_seen = NOW() WHERE user_id = ?', 'i', [$userId]) === 0) {
        $here = duel_row($m, 'SELECT 1 FROM game_queue WHERE user_id = ?', 'i', [$userId]);
        if (!$here) {
            $active = duel_active_for($m, $userId);
            return $active && $active['status'] === 'active' ? ['status' => 'matched', 'match' => $active['id']] : ['status' => 'idle'];
        }
    }
    return duel_queue_pair($m, $userId);
}

function duel_queue_leave(mysqli $m, int $userId): void
{
    if (duel_exec($m, 'DELETE FROM game_queue WHERE user_id = ?', 'i', [$userId]) > 0) {
        duel_notice_close($m, $userId, 'gone');
    }
}

/** Quanto puo' essere lontano di punti l'avversario Ranked: si allarga con l'attesa. */
function duel_queue_window(int $waited): int
{
    if ($waited >= 60) {
        return PHP_INT_MAX;
    }
    return 100 + 50 * intdiv($waited, 10);
}

/**
 * Cerca un avversario. Un solo abbinamento alla volta in tutto il sito (un
 * blocco con nome): due ricerche nello stesso istante non prendono la stessa
 * persona e non si bloccano a vicenda.
 */
function duel_queue_pair(mysqli $m, int $userId): array
{
    $got = duel_row($m, "SELECT GET_LOCK('duel_queue', 3) AS ok");
    if ((int)($got['ok'] ?? 0) !== 1) {
        return ['status' => 'searching', 'waited' => 0];
    }
    try {
        $me = duel_row($m, 'SELECT *, TIMESTAMPDIFF(SECOND, joined_at, NOW()) AS waited FROM game_queue WHERE user_id = ?', 'i', [$userId]);
        if (!$me) {
            $active = duel_active_for($m, $userId);
            return $active && $active['status'] === 'active' ? ['status' => 'matched', 'match' => $active['id']] : ['status' => 'idle'];
        }
        $mode = (string)$me['mode'];
        $waited = (int)$me['waited'];
        $candidates = duel_rows($m, 'SELECT *, TIMESTAMPDIFF(SECOND, joined_at, NOW()) AS waited FROM game_queue
            WHERE mode = ? AND user_id <> ? AND last_seen >= NOW() - INTERVAL ? SECOND ORDER BY joined_at LIMIT 20',
            'sii', [$mode, $userId, DUEL_QUEUE_STALE]);

        foreach ($candidates as $other) {
            $otherId = (int)$other['user_id'];
            if ($mode === 'ranked') {
                $gap = abs((int)$me['rating'] - (int)$other['rating']);
                if ($gap > max(duel_queue_window($waited), duel_queue_window((int)$other['waited']))) {
                    continue;
                }
            }
            // Le squadre si ricontrollano: nel frattempo un personaggio puo' essere stato venduto.
            try {
                $teamOther = duel_team_for_user($m, $otherId, json_decode((string)$other['team_json'], true) ?: [], $mode === 'ranked');
            } catch (DuelUserError $e) {
                duel_exec($m, 'DELETE FROM game_queue WHERE user_id = ?', 'i', [$otherId]);
                continue;
            }
            $teamMe = duel_team_for_user($m, $userId, json_decode((string)$me['team_json'], true) ?: [], $mode === 'ranked');

            // Chi aspetta da piu' tempo e' il giocatore 1.
            $matchId = duel_tx($m, static function () use ($m, $mode, $otherId, $userId, $teamOther, $teamMe): int {
                duel_exec($m, 'DELETE FROM game_queue WHERE user_id IN (?, ?)', 'ii', [$otherId, $userId]);
                return duel_match_start($m, [
                    'mode' => $mode, 'p1' => $otherId, 'p2' => $userId, 'team1' => $teamOther, 'team2' => $teamMe,
                    'levels' => $mode === 'ranked' ? 'max' : 'own', 'cost_limit' => $mode === 'ranked' ? duel_rules()['ranked_budget'] : 0,
                ]);
            });
            duel_notice_close($m, $otherId, 'found', $matchId);
            duel_notice_close($m, $userId, 'found', $matchId);
            return ['status' => 'matched', 'match' => $matchId];
        }

        duel_notice_maybe($m, $userId, $mode, $waited);
        return ['status' => 'searching', 'mode' => $mode, 'waited' => $waited];
    } finally {
        duel_row($m, "SELECT RELEASE_LOCK('duel_queue') AS ok");
    }
}

// ─────────────────────────────────────────────────────────────────────────
// Stanze con codice
// ─────────────────────────────────────────────────────────────────────────

/**
 * Apre una stanza: chi ha il codice entra. Opzioni: livelli 'own' o 'max',
 * limite di costo (true = come in Ranked).
 */
function duel_room_create(mysqli $m, int $userId, array $pids, array $options): array
{
    duel_require_free($m, $userId);
    $levels = ($options['levels'] ?? 'own') === 'max' ? 'max' : 'own';
    $costLimit = !empty($options['cost_limit']) ? duel_rules()['ranked_budget'] : 0;
    $team = duel_team_for_user($m, $userId, $pids, $levels === 'max');
    if ($costLimit > 0 && $team['cost'] > $costLimit) {
        throw duel_user_error("La squadra costa {$team['cost']}: con il limite il massimo è $costLimit.",
            "The team costs {$team['cost']}: the limit is $costLimit.", 400, 'TEAM_COST', ['cost' => $team['cost'], 'budget' => $costLimit]);
    }
    $rules = ['mode' => 'private', 'levels' => $levels, 'cost_limit' => $costLimit, 'turn_seconds' => DUEL_TURN_SECONDS,
        'room' => ['team' => $team['pids']]];
    for ($try = 0; $try < 5; $try++) {
        $code = duel_room_code();
        if (!duel_row($m, 'SELECT 1 FROM game_matches WHERE room_code = ? LIMIT 1', 's', [$code])) {
            break;
        }
    }
    $data = duel_match_columns($m, [
        'room_code' => $code, 'status' => 'waiting', 'mode' => 'private', 'player1_id' => $userId, 'spectator_allowed' => 1,
        'engine_version' => DUEL_ENGINE_VERSION, 'version' => 0, 'rules_json' => json_encode($rules), 'team_cost_p1' => $team['cost'],
        'max_level' => $levels === 'max' ? 1 : 0,
    ]);
    $cols = implode(', ', array_map(static fn($col) => "`$col`", array_keys($data)));
    $marks = implode(', ', array_fill(0, count($data), '?'));
    $stmt = duel_q($m, "INSERT INTO game_matches ($cols) VALUES ($marks)", duel_types($data), array_values($data));
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return ['match' => $id, 'code' => $code, 'expires' => time() + DUEL_ROOM_MINUTES * 60];
}

function duel_room_join(mysqli $m, int $userId, string $code, array $pids): array
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    $limitKey = 'duel-room:' . $userId;
    if (function_exists('auth_rate_limited') && auth_rate_limited($m, $limitKey, 'duel_room', 10, 10)) {
        throw duel_user_error('Troppi codici sbagliati: riprova tra qualche minuto.', 'Too many wrong codes: try again in a few minutes.', 429, 'RATE_LIMIT');
    }
    // Il tentativo sbagliato si registra fuori da ogni transazione: dentro,
    // l'errore lo annullerebbe insieme al resto e il limite non scatterebbe mai.
    $miss = static function () use ($m, $userId, $limitKey): DuelUserError {
        if (function_exists('auth_record_rate_failure')) {
            auth_record_rate_failure($m, $userId, $limitKey, 'duel_room');
        }
        return duel_user_error('Nessuna stanza con questo codice.', 'No room with this code.', 404, 'ROOM_NOT_FOUND');
    };
    $open = strlen($code) === 8
        ? duel_row($m, "SELECT id, player1_id, created_at FROM game_matches WHERE room_code = ? AND engine_version IS NOT NULL AND status = 'waiting' LIMIT 1", 's', [$code])
        : null;
    if (!$open || strtotime((string)$open['created_at']) < time() - DUEL_ROOM_MINUTES * 60) {
        throw $miss();
    }
    if ((int)$open['player1_id'] === $userId) {
        throw duel_user_error('È la tua stanza: aspetta che entri qualcuno.', 'It\'s your room: wait for someone to join.', 409, 'ROOM_OWN');
    }
    duel_require_free($m, $userId);

    return duel_tx($m, static function () use ($m, $userId, $code, $pids): array {
        $row = duel_row($m, "SELECT * FROM game_matches WHERE room_code = ? AND engine_version IS NOT NULL AND status = 'waiting' LIMIT 1 FOR UPDATE", 's', [$code]);
        if (!$row) {
            // Qualcun altro e' entrato un attimo prima.
            throw duel_user_error('La stanza non è più disponibile.', 'The room is no longer available.', 409, 'ROOM_GONE');
        }
        $rules = duel_match_rules($row);
        $max = $rules['levels'] === 'max';
        $owner = (int)$row['player1_id'];
        try {
            $teamOwner = duel_team_for_user($m, $owner, $rules['room']['team'] ?? [], $max);
        } catch (DuelUserError $e) {
            duel_exec($m, "UPDATE game_matches SET status = 'cancelled', end_reason = 'closed', finished_at = NOW() WHERE id = ?", 'i', [(int)$row['id']]);
            throw duel_user_error('La stanza non è più valida.', 'The room is no longer valid.', 409, 'ROOM_GONE');
        }
        $teamMe = duel_team_for_user($m, $userId, $pids, $max);
        if ((int)$rules['cost_limit'] > 0 && $teamMe['cost'] > (int)$rules['cost_limit']) {
            throw duel_user_error("La squadra costa {$teamMe['cost']}: in questa stanza il massimo è {$rules['cost_limit']}.",
                "The team costs {$teamMe['cost']}: this room allows {$rules['cost_limit']} at most.", 400, 'TEAM_COST',
                ['cost' => $teamMe['cost'], 'budget' => (int)$rules['cost_limit']]);
        }
        $id = duel_match_start($m, [
            'mode' => 'private', 'p1' => $owner, 'p2' => $userId, 'team1' => $teamOwner, 'team2' => $teamMe,
            'levels' => $rules['levels'], 'cost_limit' => (int)$rules['cost_limit'], 'room_id' => (int)$row['id'],
            'room_options' => ['levels' => $rules['levels'], 'cost_limit' => (int)$rules['cost_limit']],
        ]);
        return ['match' => $id];
    });
}

// ─────────────────────────────────────────────────────────────────────────
// Rivincita
// ─────────────────────────────────────────────────────────────────────────

/**
 * Contro il bot riparte subito una partita uguale. Tra due persone si apre
 * una stanza con le stesse regole e la stessa squadra, e l'avversario riceve
 * il codice (evento 'dr' nel suo timbro personale).
 */
function duel_rematch(mysqli $m, int $userId, int $matchId): array
{
    $row = duel_match_row($m, $matchId);
    if (!$row) {
        throw duel_user_error('Partita non trovata.', 'Match not found.', 404, 'NOT_FOUND');
    }
    $side = duel_match_side($row, $userId);
    if ($side === null) {
        throw duel_user_error('Non giochi questa partita.', 'You are not playing this match.', 403, 'NOT_PLAYER');
    }
    if ((string)$row['status'] !== 'finished') {
        throw duel_user_error('La partita non è ancora finita.', 'The match is not over yet.', 409, 'NOT_OVER');
    }
    $rules = duel_match_rules($row);
    $pids = $rules['teams'][$side] ?? [];
    if (duel_is_bot_match($row)) {
        return duel_start_bot($m, $userId, $pids, (string)$row['bot_level']);
    }
    if ((string)$row['mode'] === 'ranked') {
        throw duel_user_error('In Ranked non c\'è rivincita: si torna in coda.', 'No rematch in Ranked: queue again.', 409, 'NO_REMATCH');
    }
    $room = duel_room_create($m, $userId, $pids, ['levels' => $rules['levels'], 'cost_limit' => (int)$rules['cost_limit'] > 0]);
    $opponent = duel_side_user($row, 1 - $side);
    if ($opponent > 0) {
        rt_push_user($opponent, ['t' => 'dr', 'm' => $room['match'], 'c' => $room['code'], 'f' => $userId, 'from' => $matchId]);
    }
    return $room;
}

// ─────────────────────────────────────────────────────────────────────────
// Lobby e collezione
// ─────────────────────────────────────────────────────────────────────────

function duel_hub(mysqli $m, int $userId): array
{
    $stats = duel_row($m, 'SELECT rating, wins, losses, games_played, current_streak, best_streak FROM game_player_stats WHERE user_id = ?', 'i', [$userId]);
    $rating = (int)($stats['rating'] ?? 1000);
    $queue = duel_row($m, 'SELECT mode, TIMESTAMPDIFF(SECOND, joined_at, NOW()) AS waited FROM game_queue WHERE user_id = ?', 'i', [$userId]);

    $live = [];
    foreach (duel_rows($m, "SELECT m.id, m.mode, m.turn_number, u1.username AS p1, u2.username AS p2
        FROM game_matches m LEFT JOIN utenti u1 ON u1.id = m.player1_id LEFT JOIN utenti u2 ON u2.id = m.player2_id
        WHERE m.engine_version IS NOT NULL AND m.status = 'active' AND m.mode <> 'bot' AND m.spectator_allowed = 1
        ORDER BY m.updated_at DESC LIMIT 8") as $r) {
        $live[] = ['id' => (int)$r['id'], 'mode' => (string)$r['mode'], 'turn' => (int)$r['turn_number'],
            'players' => [(string)($r['p1'] ?? ''), (string)($r['p2'] ?? '')]];
    }

    $recent = [];
    foreach (duel_rows($m, "SELECT m.id, m.mode, m.player1_id, m.player2_id, m.winner_id, m.end_reason, m.finished_at,
            m.winner_rating_delta, m.loser_rating_delta, u1.username AS p1, u2.username AS p2
        FROM game_matches m LEFT JOIN utenti u1 ON u1.id = m.player1_id LEFT JOIN utenti u2 ON u2.id = m.player2_id
        WHERE m.engine_version IS NOT NULL AND m.status = 'finished' AND (m.player1_id = ? OR m.player2_id = ?)
        ORDER BY m.finished_at DESC LIMIT 10", 'ii', [$userId, $userId]) as $r) {
        $mine = (int)$r['player1_id'] === $userId ? 0 : 1;
        $won = $r['winner_id'] !== null && (int)$r['winner_id'] === $userId;
        $draw = $r['winner_id'] === null;
        $opponent = (string)$r['mode'] === 'bot' ? 'Cripsum Bot' : (string)($mine === 0 ? ($r['p2'] ?? '') : ($r['p1'] ?? ''));
        $recent[] = ['id' => (int)$r['id'], 'mode' => (string)$r['mode'], 'opponent' => $opponent, 'won' => $won, 'draw' => $draw,
            'reason' => (string)$r['end_reason'], 'at' => (string)$r['finished_at'],
            'rating_delta' => $draw ? null : ($won ? ($r['winner_rating_delta'] !== null ? (int)$r['winner_rating_delta'] : null)
                : ($r['loser_rating_delta'] !== null ? (int)$r['loser_rating_delta'] : null))];
    }

    return [
        'me' => ['rating' => $rating, 'rank' => duel_rank($rating), 'wins' => (int)($stats['wins'] ?? 0), 'losses' => (int)($stats['losses'] ?? 0),
            'games' => (int)($stats['games_played'] ?? 0), 'streak' => (int)($stats['current_streak'] ?? 0), 'best_streak' => (int)($stats['best_streak'] ?? 0)],
        'active' => duel_active_for($m, $userId),
        'queue' => $queue ? ['mode' => (string)$queue['mode'], 'waited' => (int)$queue['waited']] : null,
        'teams' => duel_teams($m, $userId),
        'live' => $live,
        'recent' => $recent,
        'public' => duel_settings($m)['public'] === '1',
        'budget' => duel_rules()['ranked_budget'],
    ];
}

/** La collezione per la pagina Squadra: ogni personaggio con costo, statistiche e kit scritto. */
function duel_roster(mysqli $m, int $userId): array
{
    duel_use_current_values($m);
    $owned = duel_db_collection($m, $userId);
    $chars = duel_db_characters($m, array_keys($owned));
    $lang = rt_lang();
    $max = duel_rules()['level_max'];
    $out = [];
    foreach ($owned as $pid => $level) {
        if (!isset($chars[$pid])) {
            continue;
        }
        $kit = duel_character_kit($chars[$pid], $level);
        $kitMax = $level === $max ? $kit : duel_character_kit($chars[$pid], $max);
        $out[] = [
            'id' => $pid, 'name' => (string)$chars[$pid]['nome'], 'img' => (string)$chars[$pid]['img_url'],
            'rarity' => $kit['rarity'], 'role' => $kit['role'], 'level' => $level, 'cost' => duel_rarity($kit['rarity'])['cost'],
            'unique' => !empty($kit['unique']), 'stats' => $kit['stats'], 'stats_max' => $kitMax['stats'],
            'kit' => duel_kit_text($kit, $lang),
        ];
    }
    usort($out, static fn($a, $b) => [$b['cost'], $a['name']] <=> [$a['cost'], $b['name']]);
    return $out;
}
