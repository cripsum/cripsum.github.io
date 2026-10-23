<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — sfide agli amici (fase 6, tavola 6).
 *
 * Una sfida va a un amico del sito (tabella friendships) e arriva nella
 * campanella e nella lobby. Due modi:
 * - 'live', adesso: chi sfida apre una stanza riservata all'amico e aspetta
 *   nell'arena come con un codice; «Accetta» lo fa entrare. Vale 10 minuti
 *   come le stanze.
 * - 'async', in differita: niente attesa. «Accetta» fa partire una partita
 *   da 24 ore a turno (game_matches.is_async): si gioca quando si puo', la
 *   campanella dice a chi tocca e ricorda le ultime 4 ore. La sfida vale un
 *   giorno.
 * Le sfide non contano per la classifica. Livelli: i vostri o pareggiati.
 *
 * Si carica da lobby.php (in fondo): usa le stanze e l'avvio delle partite.
 */

const DUEL_CHALLENGE_LIVE_MINUTES = 10;
const DUEL_CHALLENGE_ASYNC_HOURS = 24;
const DUEL_CHALLENGE_OPEN_MAX = 5;     // sfide mandate e non ancora risposte
const DUEL_ASYNC_OPEN_MAX = 10;        // partite in differita in corso
const DUEL_ASYNC_REMIND_HOURS = 4;
const DUEL_ONLINE_SECONDS = 180;       // come l'indicatore online del sito

/** Gli amici con chi e' online, la fascia e se c'e' gia' una sfida aperta. */
function duel_friends(mysqli $m, int $userId): array
{
    if (!rt_has_table($m, 'friendships')) {
        return [];
    }
    $seen = rt_has_col($m, 'utenti', 'ultimo_accesso') ? 'TIMESTAMPDIFF(SECOND, u.ultimo_accesso, NOW())' : 'NULL';
    $rows = duel_rows($m, "SELECT u.id, u.username, $seen AS idle, COALESCE(s.rating, 1000) AS rating, COALESCE(s.games_played, 0) AS games
        FROM friendships f
        JOIN utenti u ON u.id = IF(f.user_one_id = ?, f.user_two_id, f.user_one_id)
        LEFT JOIN game_player_stats s ON s.user_id = u.id
        WHERE f.user_one_id = ? OR f.user_two_id = ?
        LIMIT 300", 'iii', [$userId, $userId, $userId]);
    $open = [];
    if (duel_phase6_ready($m)) {
        foreach (duel_rows($m, "SELECT IF(from_user = ?, to_user, from_user) AS other FROM game_challenges
            WHERE status = 'pending' AND expires_at > NOW() AND (from_user = ? OR to_user = ?)", 'iii', [$userId, $userId, $userId]) as $r) {
            $open[(int)$r['other']] = true;
        }
    }
    $out = [];
    foreach ($rows as $r) {
        $idle = $r['idle'] === null ? null : (int)$r['idle'];
        $out[] = [
            'id' => (int)$r['id'], 'username' => (string)$r['username'],
            'online' => $idle !== null && $idle < DUEL_ONLINE_SECONDS, 'idle' => $idle,
            'rating' => (int)$r['rating'], 'rank' => duel_rank((int)$r['rating'])['key'], 'games' => (int)$r['games'],
            'open' => isset($open[(int)$r['id']]),
        ];
    }
    usort($out, static fn($a, $b) => [$b['online'], $a['idle'] ?? PHP_INT_MAX, $a['username']] <=> [$a['online'], $b['idle'] ?? PHP_INT_MAX, $b['username']]);
    return $out;
}

function duel_are_friends(mysqli $m, int $a, int $b): bool
{
    if (!rt_has_table($m, 'friendships')) {
        return false;
    }
    return (bool)duel_row($m, 'SELECT 1 FROM friendships WHERE (user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?) LIMIT 1',
        'iiii', [$a, $b, $b, $a]);
}

function duel_blocked_between(mysqli $m, int $a, int $b): bool
{
    if (!rt_has_table($m, 'blocked_users')) {
        return false;
    }
    return (bool)duel_row($m, 'SELECT 1 FROM blocked_users WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1',
        'iiii', [$a, $b, $b, $a]);
}

function duel_username(mysqli $m, int $userId): string
{
    return (string)(duel_row($m, 'SELECT username FROM utenti WHERE id = ?', 'i', [$userId])['username'] ?? '');
}

/** Manda una sfida. $kind: 'live' o 'async'. */
function duel_challenge_send(mysqli $m, int $userId, int $toUser, string $kind, string $levels, bool $costLimit, array $pids): array
{
    if (!duel_phase6_ready($m)) {
        throw duel_user_error('Le sfide agli amici non sono ancora attive.', 'Friend challenges are not enabled yet.', 503, 'NOT_READY');
    }
    $kind = $kind === 'async' ? 'async' : 'live';
    $levels = $levels === 'max' ? 'max' : 'own';
    if ($toUser === $userId || $toUser <= 0) {
        throw duel_user_error('Scegli un amico da sfidare.', 'Pick a friend to challenge.', 400, 'BAD_FRIEND');
    }
    if (!duel_are_friends($m, $userId, $toUser) || duel_blocked_between($m, $userId, $toUser)) {
        throw duel_user_error('Puoi sfidare solo i tuoi amici.', 'You can only challenge your friends.', 403, 'NOT_FRIEND');
    }
    $openMine = (int)(duel_row($m, "SELECT COUNT(*) AS n FROM game_challenges WHERE from_user = ? AND status = 'pending' AND expires_at > NOW()", 'i', [$userId])['n'] ?? 0);
    if ($openMine >= DUEL_CHALLENGE_OPEN_MAX) {
        throw duel_user_error('Hai già ' . DUEL_CHALLENGE_OPEN_MAX . ' sfide in attesa di risposta.', 'You already have ' . DUEL_CHALLENGE_OPEN_MAX . ' challenges waiting for an answer.', 429, 'TOO_MANY');
    }
    if (duel_row($m, "SELECT 1 FROM game_challenges WHERE status = 'pending' AND expires_at > NOW()
        AND ((from_user = ? AND to_user = ?) OR (from_user = ? AND to_user = ?)) LIMIT 1", 'iiii', [$userId, $toUser, $toUser, $userId])) {
        throw duel_user_error('C\'è già una sfida aperta con questo amico.', 'There is already an open challenge with this friend.', 409, 'ALREADY');
    }
    if ($kind === 'async' && duel_async_count($m, $userId) >= DUEL_ASYNC_OPEN_MAX) {
        throw duel_user_error('Hai già ' . DUEL_ASYNC_OPEN_MAX . ' partite in differita in corso.', 'You already have ' . DUEL_ASYNC_OPEN_MAX . ' turn-based matches going.', 429, 'TOO_MANY');
    }
    $team = duel_team_for_user($m, $userId, $pids, $levels === 'max');
    $budget = duel_rules()['ranked_budget'];
    if ($costLimit && $team['cost'] > $budget) {
        throw duel_user_error("La squadra costa {$team['cost']}: con il limite il massimo è $budget.", "The team costs {$team['cost']}: the limit is $budget.",
            400, 'TEAM_COST', ['cost' => $team['cost'], 'budget' => $budget]);
    }

    $room = null;
    if ($kind === 'live') {
        // Si gioca adesso: una stanza che aspetta solo l'amico.
        $room = duel_room_create($m, $userId, $team['pids'], ['levels' => $levels, 'cost_limit' => $costLimit, 'invite' => $toUser]);
    }
    $expires = $kind === 'live' ? 'NOW() + INTERVAL ' . DUEL_CHALLENGE_LIVE_MINUTES . ' MINUTE' : 'NOW() + INTERVAL ' . DUEL_CHALLENGE_ASYNC_HOURS . ' HOUR';
    $stmt = duel_q($m, "INSERT INTO game_challenges (from_user, to_user, kind, levels, cost_limit, team_json, match_id, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, $expires)", 'iissisi',
        [$userId, $toUser, $kind, $levels, $costLimit ? $budget : 0, json_encode($team['pids']), $room['match'] ?? null]);
    $id = (int)$stmt->insert_id;
    $stmt->close();
    if ($room) {
        duel_exec($m, 'UPDATE game_matches SET challenge_id = ? WHERE id = ?', 'ii', [$id, (int)$room['match']]);
    }

    $from = duel_username($m, $userId);
    [$it, $en] = $kind === 'live' ? ['adesso, live', 'now, live'] : ['in differita, 24 ore a turno', 'turn-based, 24 hours per turn'];
    duel_notify($m, $toUser, "$from ti sfida a duello", "$from challenges you to a duel",
        "Sfida $it. Accetta da Duelli (/it/duelli/): " . ($kind === 'live' ? 'vale 10 minuti.' : 'vale un giorno.'),
        "Challenge $en. Accept it from Duels (/en/duelli/): " . ($kind === 'live' ? 'it lasts 10 minutes.' : 'it lasts one day.'));
    rt_push_user($toUser, ['t' => 'dc', 'id' => $id, 'f' => $userId]);
    return ['challenge' => $id, 'kind' => $kind, 'match' => $room['match'] ?? null];
}

/** Accetta una sfida ricevuta con la propria squadra. */
function duel_challenge_accept(mysqli $m, int $userId, int $id, array $pids): array
{
    $c = duel_row($m, 'SELECT * FROM game_challenges WHERE id = ?', 'i', [$id]);
    if (!$c || (int)$c['to_user'] !== $userId) {
        throw duel_user_error('Sfida non trovata.', 'Challenge not found.', 404, 'NOT_FOUND');
    }
    if ((string)$c['status'] !== 'pending' || strtotime((string)$c['expires_at']) <= time()) {
        throw duel_user_error('La sfida non è più valida.', 'The challenge is no longer valid.', 409, 'GONE');
    }
    $from = (int)$c['from_user'];
    if ((string)$c['kind'] === 'live') {
        $code = (string)(duel_row($m, "SELECT room_code FROM game_matches WHERE id = ? AND status = 'waiting'", 'i', [(int)$c['match_id']])['room_code'] ?? '');
        if ($code === '') {
            duel_exec($m, "UPDATE game_challenges SET status = 'expired', answered_at = NOW() WHERE id = ? AND status = 'pending'", 'i', [$id]);
            throw duel_user_error('La sfida non è più valida.', 'The challenge is no longer valid.', 409, 'GONE');
        }
        $r = duel_room_join($m, $userId, $code, $pids);
        duel_exec($m, "UPDATE game_challenges SET status = 'accepted', answered_at = NOW() WHERE id = ?", 'i', [$id]);
        return ['match' => $r['match'], 'kind' => 'live'];
    }

    // In differita: la partita parte adesso, senza stanza.
    $matchId = duel_tx($m, static function () use ($m, $c, $id, $userId, $from, $pids): int {
        $row = duel_row($m, 'SELECT * FROM game_challenges WHERE id = ? FOR UPDATE', 'i', [$id]);
        if (!$row || (string)$row['status'] !== 'pending') {
            throw duel_user_error('La sfida non è più valida.', 'The challenge is no longer valid.', 409, 'GONE');
        }
        $max = (string)$c['levels'] === 'max';
        try {
            $teamFrom = duel_team_for_user($m, $from, json_decode((string)$c['team_json'], true) ?: [], $max);
        } catch (DuelUserError $e) {
            duel_exec($m, "UPDATE game_challenges SET status = 'cancelled', answered_at = NOW() WHERE id = ?", 'i', [$id]);
            throw duel_user_error('La squadra di chi ti ha sfidato non è più valida.', 'The challenger\'s team is no longer valid.', 409, 'GONE');
        }
        $teamMe = duel_team_for_user($m, $userId, $pids, $max);
        if ((int)$c['cost_limit'] > 0 && $teamMe['cost'] > (int)$c['cost_limit']) {
            throw duel_user_error("La squadra costa {$teamMe['cost']}: in questa sfida il massimo è {$c['cost_limit']}.",
                "The team costs {$teamMe['cost']}: this challenge allows {$c['cost_limit']} at most.", 400, 'TEAM_COST',
                ['cost' => $teamMe['cost'], 'budget' => (int)$c['cost_limit']]);
        }
        $matchId = duel_match_start($m, [
            'mode' => 'private', 'p1' => $from, 'p2' => $userId, 'team1' => $teamFrom, 'team2' => $teamMe,
            'levels' => (string)$c['levels'], 'cost_limit' => (int)$c['cost_limit'], 'async' => true, 'challenge_id' => $id,
        ]);
        duel_exec($m, "UPDATE game_challenges SET status = 'accepted', match_id = ?, answered_at = NOW() WHERE id = ?", 'ii', [$matchId, $id]);
        return $matchId;
    });
    $me = duel_username($m, $userId);
    duel_notify($m, $from, "$me ha accettato la sfida", "$me accepted your challenge",
        "La partita in differita è cominciata: la trovi in Duelli.", "The turn-based match has started: find it in Duels.");
    return ['match' => $matchId, 'kind' => 'async'];
}

/** Rifiuta (chi la riceve) o ritira (chi l'ha mandata) una sfida aperta. */
function duel_challenge_close(mysqli $m, int $userId, int $id, string $action): void
{
    $c = duel_row($m, 'SELECT * FROM game_challenges WHERE id = ?', 'i', [$id]);
    $mine = $c && ($action === 'decline' ? (int)$c['to_user'] === $userId : (int)$c['from_user'] === $userId);
    if (!$mine) {
        throw duel_user_error('Sfida non trovata.', 'Challenge not found.', 404, 'NOT_FOUND');
    }
    $status = $action === 'decline' ? 'declined' : 'cancelled';
    if (duel_exec($m, "UPDATE game_challenges SET status = ?, answered_at = NOW() WHERE id = ? AND status = 'pending'", 'si', [$status, $id]) !== 1) {
        return;
    }
    if ((string)$c['kind'] === 'live' && !empty($c['match_id'])) {
        $match = (int)$c['match_id'];
        if (duel_exec($m, "UPDATE game_matches SET status = 'cancelled', end_reason = ?, finished_at = NOW() WHERE id = ? AND status = 'waiting'",
            'si', [$status, $match]) > 0) {
            duel_stamp($match, ['st' => 'cancelled']);
        }
    }
    if ($action === 'decline') {
        $me = duel_username($m, $userId);
        rt_push_user((int)$c['from_user'], ['t' => 'dc', 'id' => $id, 'x' => 'declined']);
        duel_notify($m, (int)$c['from_user'], "$me ha rifiutato la sfida", "$me declined your challenge", "Riprova un'altra volta.", 'Try another time.', 30);
    }
}

/** Le sfide aperte di un giocatore: ricevute e mandate. */
function duel_challenges_for(mysqli $m, int $userId): array
{
    if (!duel_phase6_ready($m)) {
        return [];
    }
    $out = [];
    foreach (duel_rows($m, "SELECT c.*, uf.username AS from_name, ut.username AS to_name,
            TIMESTAMPDIFF(SECOND, NOW(), c.expires_at) AS left_s
        FROM game_challenges c JOIN utenti uf ON uf.id = c.from_user JOIN utenti ut ON ut.id = c.to_user
        WHERE c.status = 'pending' AND c.expires_at > NOW() AND (c.from_user = ? OR c.to_user = ?)
        ORDER BY c.created_at DESC LIMIT 20", 'ii', [$userId, $userId]) as $r) {
        $sent = (int)$r['from_user'] === $userId;
        $out[] = [
            'id' => (int)$r['id'], 'sent' => $sent, 'kind' => (string)$r['kind'], 'levels' => (string)$r['levels'],
            'cost_limit' => (int)$r['cost_limit'], 'other' => $sent ? (string)$r['to_name'] : (string)$r['from_name'],
            'other_id' => $sent ? (int)$r['to_user'] : (int)$r['from_user'],
            'left' => max(0, (int)$r['left_s']), 'match' => $r['match_id'] !== null ? (int)$r['match_id'] : null,
        ];
    }
    return $out;
}

function duel_async_count(mysqli $m, int $userId): int
{
    if (!rt_has_col($m, 'game_matches', 'is_async')) {
        return 0;
    }
    return (int)(duel_row($m, "SELECT COUNT(*) AS n FROM game_matches WHERE engine_version IS NOT NULL AND is_async = 1 AND status = 'active'
        AND (player1_id = ? OR player2_id = ?)", 'ii', [$userId, $userId])['n'] ?? 0);
}

/** Le partite in differita in corso: contro chi, a chi tocca, quanto manca. */
function duel_async_for(mysqli $m, int $userId): array
{
    if (!rt_has_col($m, 'game_matches', 'is_async')) {
        return [];
    }
    $out = [];
    foreach (duel_rows($m, "SELECT m.id, m.turn_number, m.current_turn_user_id, m.player1_id, m.player2_id,
            TIMESTAMPDIFF(SECOND, NOW(), m.turn_deadline) AS left_s, u1.username AS p1, u2.username AS p2
        FROM game_matches m LEFT JOIN utenti u1 ON u1.id = m.player1_id LEFT JOIN utenti u2 ON u2.id = m.player2_id
        WHERE m.engine_version IS NOT NULL AND m.is_async = 1 AND m.status = 'active' AND (m.player1_id = ? OR m.player2_id = ?)
        ORDER BY (m.current_turn_user_id = ?) DESC, m.turn_deadline LIMIT 20", 'iii', [$userId, $userId, $userId]) as $r) {
        $first = (int)$r['player1_id'] === $userId;
        $out[] = [
            'id' => (int)$r['id'], 'opponent' => (string)($first ? $r['p2'] : $r['p1']), 'opponent_id' => (int)($first ? $r['player2_id'] : $r['player1_id']),
            'turn' => (int)$r['turn_number'],
            'my_turn' => (int)($r['current_turn_user_id'] ?? 0) === $userId, 'left' => $r['left_s'] !== null ? max(0, (int)$r['left_s']) : null,
        ];
    }
    return $out;
}

/**
 * La parte delle sfide nella pulizia: sfide scadute, stanze delle sfide
 * chiuse da chi le aveva aperte, promemoria delle ultime 4 ore in differita.
 */
function duel_social_maintenance(mysqli $m): array
{
    $done = ['expired' => 0, 'reminded' => 0];
    if (!duel_phase6_ready($m)) {
        return $done;
    }
    $done['expired'] = duel_exec($m, "UPDATE game_challenges SET status = 'expired', answered_at = NOW() WHERE status = 'pending' AND expires_at <= NOW()");
    // La stanza di una sfida live chiusa o scaduta: la sfida non vale piu'.
    duel_exec($m, "UPDATE game_challenges c JOIN game_matches g ON g.id = c.match_id SET c.status = 'cancelled', c.answered_at = NOW()
        WHERE c.status = 'pending' AND c.kind = 'live' AND g.status = 'cancelled'");

    foreach (duel_rows($m, "SELECT * FROM game_matches WHERE engine_version IS NOT NULL AND is_async = 1 AND status = 'active'
        AND turn_deadline IS NOT NULL AND turn_deadline <= NOW() + INTERVAL ? HOUR AND reminded_v <= version LIMIT 50", 'i', [DUEL_ASYNC_REMIND_HOURS]) as $row) {
        $userId = (int)($row['current_turn_user_id'] ?? 0);
        // reminded_v = versione + 1: vale anche per il primo turno (versione 0).
        if ($userId <= 0 || duel_exec($m, 'UPDATE game_matches SET reminded_v = version + 1 WHERE id = ? AND reminded_v <= version', 'i', [(int)$row['id']]) !== 1) {
            continue;
        }
        $side = duel_match_side($row, $userId);
        $other = $side === null ? '' : duel_username($m, duel_side_user($row, 1 - $side));
        $hours = max(1, (int)round((strtotime((string)$row['turn_deadline']) - time()) / 3600));
        $id = (int)$row['id'];
        duel_notify($m, $userId, "Duello contro $other: ti restano $hours ore", "Duel against $other: $hours hours left",
            "Se il tempo scade parte la Guardia da sola; la seconda volta la partita è persa. /it/duelli/arena?m=$id",
            "If time runs out you Guard automatically; the second time you lose the match. /en/duelli/arena?m=$id");
        $done['reminded']++;
    }
    return $done;
}
