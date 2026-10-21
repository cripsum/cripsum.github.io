<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — la partita nel database.
 *
 * Una partita e' una riga di game_matches (engine_version non nullo) con lo
 * stato del motore in state_json e un numero di versione che sale di uno a
 * ogni mossa. Ogni mossa scrive anche una riga in game_match_events con gli
 * eventi del motore: l'arena li anima, gli spettatori li seguono, il replay
 * li rigioca. La riga 0 tiene lo stato iniziale intero (dado compreso): il
 * replay riparte da li' e il motore, deterministico, rifa' la stessa
 * partita.
 *
 * Tutto quello che cambia una partita passa da una transazione con la riga
 * bloccata (SELECT ... FOR UPDATE): due mosse insieme, un doppio clic o il
 * tempo che scade mentre qualcuno gioca non si pestano i piedi.
 *
 * Il dado (seed e rng dentro lo stato) non esce mai prima della fine.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/bot.php';
require_once __DIR__ . '/text.php';
require_once __DIR__ . '/season.php';

const DUEL_TURN_SECONDS = 45;      // tempo per turno nelle partite live
const DUEL_TIMEOUTS_MAX = 3;       // turni scaduti che fanno perdere
const DUEL_DEADLINE_GRACE = 2;     // secondi di tolleranza per la rete
const DUEL_BOT_USER = 0;           // il bot in winner_id / loser_id, come nei duelli vecchi
const DUEL_ELO_K = 32;
const DUEL_RANKED_PAIR_DAY = 3;    // partite Ranked al giorno contro lo stesso avversario che contano
const DUEL_ASYNC_SECONDS = 86400;  // in differita: 24 ore a turno
const DUEL_ASYNC_TIMEOUTS = 2;     // in differita: al secondo turno scaduto si perde

// ─────────────────────────────────────────────────────────────────────────
// Leggere una partita
// ─────────────────────────────────────────────────────────────────────────

/** La riga di una partita 2.0 (null se non c'e' o e' del motore vecchio). */
function duel_match_row(mysqli $m, int $id, bool $lock = false): ?array
{
    if ($id <= 0) {
        return null;
    }
    return duel_row($m, 'SELECT * FROM game_matches WHERE id = ? AND engine_version IS NOT NULL LIMIT 1' . ($lock ? ' FOR UPDATE' : ''), 'i', [$id]);
}

/** Le regole salvate con la partita: modo, livelli, tempo, valori del pannello. */
function duel_match_rules(array $row): array
{
    $rules = json_decode((string)($row['rules_json'] ?? ''), true);
    return (is_array($rules) ? $rules : []) + [
        'mode' => (string)($row['mode'] ?? 'casual'),
        'levels' => 'own',
        'cost_limit' => 0,
        'turn_seconds' => DUEL_TURN_SECONDS,
        'timeouts_max' => DUEL_TIMEOUTS_MAX,
        'async' => false,
        'values' => ['rules' => [], 'status' => []],
    ];
}

/** Partita in differita (sfida a un amico, 24 ore a turno). */
function duel_is_async(array $row): bool
{
    return (int)($row['is_async'] ?? 0) === 1;
}

function duel_match_state(array $row): array
{
    $st = json_decode((string)($row['state_json'] ?? ''), true);
    if (!is_array($st) || empty($st['units'])) {
        throw new RuntimeException('stato della partita ' . (int)($row['id'] ?? 0) . ' illeggibile');
    }
    // JSON trasforma le chiavi numeriche delle carte in stringhe: si rimettono intere.
    $units = [];
    foreach ($st['units'] as $id => $u) {
        $units[(int)$id] = $u;
    }
    $st['units'] = $units;
    return $st;
}

/** 0 o 1 se l'utente gioca la partita, null se no. */
function duel_match_side(array $row, int $userId): ?int
{
    if ($userId > 0 && (int)$row['player1_id'] === $userId) {
        return 0;
    }
    if ($userId > 0 && (int)($row['player2_id'] ?? 0) === $userId) {
        return 1;
    }
    return null;
}

function duel_is_bot_match(array $row): bool
{
    return (string)$row['mode'] === 'bot';
}

/** Chi gioca una squadra: id utente, 0 per il bot. */
function duel_side_user(array $row, int $side): int
{
    if ($side === 1 && duel_is_bot_match($row)) {
        return DUEL_BOT_USER;
    }
    return (int)($side === 0 ? $row['player1_id'] : ($row['player2_id'] ?? 0));
}

function duel_actor_side(array $st): ?int
{
    if ($st['over'] || $st['actor'] === null) {
        return null;
    }
    return (int)$st['units'][(int)$st['actor']]['side'];
}

// ─────────────────────────────────────────────────────────────────────────
// Squadre
// ─────────────────────────────────────────────────────────────────────────

/**
 * Le tre carte di una squadra pronte per il motore. $levels: [id => livello]
 * dei personaggi posseduti; con $max tutti al livello massimo (Ranked).
 * Restituisce ['kits' => [...], 'cost' => int, 'pids' => [...]].
 */
function duel_team_build(mysqli $m, array $pids, array $levels, bool $max): array
{
    $pids = array_values(array_map('intval', $pids));
    if (count($pids) !== 3 || count(array_unique($pids)) !== 3) {
        throw duel_user_error('Scegli tre personaggi diversi.', 'Pick three different characters.', 400, 'TEAM_INVALID');
    }
    duel_use_current_values($m);
    $chars = duel_db_characters($m, $pids);
    $kits = [];
    $rarities = [];
    foreach ($pids as $pid) {
        if (!isset($chars[$pid])) {
            throw duel_user_error('Un personaggio della squadra non esiste più.', 'A character in the team no longer exists.', 400, 'TEAM_INVALID');
        }
        $level = $max ? duel_rules()['level_max'] : (int)($levels[$pid] ?? 1);
        $kit = duel_character_kit($chars[$pid], $level);
        $kit['pid'] = $pid;
        $kit['level'] = $level;
        $kits[] = $kit;
        $rarities[] = $kit['rarity'];
    }
    return ['kits' => $kits, 'cost' => duel_team_cost($rarities), 'pids' => $pids];
}

/** Una squadra di un giocatore, controllando che i personaggi siano suoi. */
function duel_team_for_user(mysqli $m, int $userId, array $pids, bool $max): array
{
    $pids = array_values(array_map('intval', $pids));
    $owned = duel_db_owned($m, $userId, $pids);
    foreach ($pids as $pid) {
        if (!isset($owned[$pid])) {
            throw duel_user_error('Nella squadra c\'è un personaggio che non hai.', 'Your team has a character you don\'t own.', 400, 'TEAM_NOT_OWNED');
        }
    }
    return duel_team_build($m, $pids, $owned, $max);
}

// ─────────────────────────────────────────────────────────────────────────
// Creare una partita
// ─────────────────────────────────────────────────────────────────────────

function duel_room_code(): string
{
    // Senza lettere e numeri che si confondono (0/O, 1/I/L).
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

/** Solo le colonne che esistono (lo schema vecchio non e' scritto da nessuna parte). */
function duel_match_columns(mysqli $m, array $data): array
{
    return array_filter($data, static fn($col) => rt_has_col($m, 'game_matches', (string)$col), ARRAY_FILTER_USE_KEY);
}

/**
 * Comincia una partita e la salva. Da chiamare dentro duel_tx().
 *
 * $p: mode (casual|ranked|private|bot), p1, p2 (id utente; 0 col bot),
 * team1, team2 (da duel_team_build), levels ('own'|'max'), cost_limit,
 * turn_seconds, bot_level, room_id (stanza in attesa da far partire),
 * async (in differita: 24 ore a turno, al secondo turno scaduto si perde),
 * challenge_id (la sfida da cui nasce).
 * Restituisce l'id della partita.
 */
function duel_match_start(mysqli $m, array $p): int
{
    $over = duel_use_current_values($m);
    $seed = random_int(1, PHP_INT_MAX);
    [$st, $events] = duel_new_match([$p['team1']['kits'], $p['team2']['kits']], $seed, ['mode' => $p['mode']]);

    $isBot = $p['mode'] === 'bot';
    $rules = [
        'mode' => $p['mode'],
        'levels' => $p['levels'] ?? 'own',
        'cost_limit' => (int)($p['cost_limit'] ?? 0),
        'turn_seconds' => $isBot ? 0 : (!empty($p['async']) ? DUEL_ASYNC_SECONDS : (int)($p['turn_seconds'] ?? DUEL_TURN_SECONDS)),
        'timeouts_max' => !empty($p['async']) ? DUEL_ASYNC_TIMEOUTS : DUEL_TIMEOUTS_MAX,
        'async' => !empty($p['async']),
        'teams' => [$p['team1']['pids'], $p['team2']['pids']],
        'values' => ['rules' => $over['rules'], 'status' => $over['status']],
    ];
    if (!empty($p['room_options'])) {
        $rules['room'] = $p['room_options'];
    }

    $data = duel_match_columns($m, [
        'status' => 'active',
        'mode' => $p['mode'],
        'player1_id' => (int)$p['p1'],
        'player2_id' => $isBot ? null : (int)$p['p2'],
        'spectator_allowed' => $isBot ? 0 : 1,
        'engine_version' => DUEL_ENGINE_VERSION,
        'seed' => $seed,
        'state_json' => json_encode($st, JSON_UNESCAPED_UNICODE),
        'version' => 0,
        'rules_json' => json_encode($rules, JSON_UNESCAPED_UNICODE),
        'team_cost_p1' => (int)$p['team1']['cost'],
        'team_cost_p2' => (int)$p['team2']['cost'],
        'bot_level' => $isBot ? (string)($p['bot_level'] ?? 'normale') : null,
        'max_level' => ($p['levels'] ?? 'own') === 'max' ? 1 : 0,
        'turn_number' => (int)$st['turn'],
        'timeouts_p1' => 0,
        'timeouts_p2' => 0,
        'player1_ready' => 1,
        'player2_ready' => 1,
        'is_async' => !empty($p['async']) ? 1 : 0,
        'challenge_id' => isset($p['challenge_id']) ? (int)$p['challenge_id'] : null,
    ]);

    if (!empty($p['room_id'])) {
        $id = (int)$p['room_id'];
        $set = implode(', ', array_map(static fn($col) => "`$col` = ?", array_keys($data)));
        duel_exec($m, "UPDATE game_matches SET $set, updated_at = NOW() WHERE id = ?", duel_types($data) . 'i', [...array_values($data), $id]);
    } else {
        $data = ['room_code' => duel_room_code()] + $data;
        $cols = implode(', ', array_map(static fn($col) => "`$col`", array_keys($data)));
        $marks = implode(', ', array_fill(0, count($data), '?'));
        $stmt = duel_q($m, "INSERT INTO game_matches ($cols) VALUES ($marks)", duel_types($data), array_values($data));
        $id = (int)$stmt->insert_id;
        $stmt->close();
    }

    duel_exec($m, 'INSERT INTO game_match_events (match_id, version, kind, user_id, payload_json) VALUES (?, 0, ?, NULL, ?)', 'iss',
        [$id, 'start', json_encode(['kind' => 'start', 'state' => $st, 'ev' => $events], JSON_UNESCAPED_UNICODE)]);

    $row = duel_match_row($m, $id, true);
    duel_use_match_values($rules);
    duel_bot_turns($m, $row, $st);
    duel_save($m, $row, $st);

    foreach ([(int)$p['p1'], $isBot ? 0 : (int)$p['p2']] as $userId) {
        if ($userId > 0) {
            duel_after_commit(static fn() => rt_push_user($userId, ['t' => 'dm', 'm' => $id]));
        }
    }
    return $id;
}

/** Tipi per bind_param dai valori. */
function duel_types(array $values): string
{
    $types = '';
    foreach ($values as $v) {
        $types .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
    }
    return $types;
}

// ─────────────────────────────────────────────────────────────────────────
// Mosse
// ─────────────────────────────────────────────────────────────────────────

/** Scadenza del turno di adesso: null per il bot, a partita finita o senza tempo. */
function duel_deadline(array $row, array $st): ?string
{
    $side = duel_actor_side($st);
    $seconds = (int)duel_match_rules($row)['turn_seconds'];
    if ($side === null || $seconds <= 0 || duel_side_user($row, $side) === DUEL_BOT_USER) {
        return null;
    }
    return date('Y-m-d H:i:s', time() + $seconds);
}

/**
 * Registra un passo della partita: una riga di eventi, versione +1. Lo stato
 * nella riga si scrive alla fine con duel_save() (una sola UPDATE anche
 * quando il bot gioca piu' turni di fila).
 */
function duel_step(mysqli $m, array &$row, array $st, array $events, string $kind, array $info, ?int $userId = null): void
{
    $version = (int)$row['version'] + 1;
    $payload = ['kind' => $kind] + $info + ['ev' => $events];
    duel_exec($m, 'INSERT INTO game_match_events (match_id, version, kind, user_id, payload_json) VALUES (?, ?, ?, ?, ?)', 'iisis',
        [(int)$row['id'], $version, $kind, $userId, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    $row['version'] = $version;
}

/** Scrive lo stato, la versione e la scadenza; se la partita e' finita la chiude. */
function duel_save(mysqli $m, array &$row, array $st, ?string $clientAction = null): void
{
    $deadline = duel_deadline($row, $st);
    $actorSide = duel_actor_side($st);
    $prevTurnUser = (int)($row['current_turn_user_id'] ?? 0);
    $data = duel_match_columns($m, [
        'state_json' => json_encode($st, JSON_UNESCAPED_UNICODE),
        'version' => (int)$row['version'],
        'turn_number' => (int)$st['turn'],
        'current_turn_user_id' => $actorSide === null ? null : duel_side_user($row, $actorSide),
        'timeouts_p1' => (int)$row['timeouts_p1'],
        'timeouts_p2' => (int)$row['timeouts_p2'],
    ]);
    $set = implode(', ', array_map(static fn($col) => "`$col` = ?", array_keys($data)));
    $types = duel_types($data);
    $values = array_values($data);
    $set .= ', turn_deadline = ?, updated_at = NOW()';
    $types .= 's';
    $values[] = $deadline;
    if ($clientAction !== null) {
        $set .= ', last_client_action = ?';
        $types .= 's';
        $values[] = $clientAction;
    }
    duel_exec($m, "UPDATE game_matches SET $set WHERE id = ?", $types . 'i', [...$values, (int)$row['id']]);
    $row['turn_deadline'] = $deadline;
    $row['state_json'] = null;
    $turnUser = $actorSide === null ? 0 : duel_side_user($row, $actorSide);
    $row['current_turn_user_id'] = $turnUser ?: null;
    if (duel_is_async($row) && !$st['over'] && $turnUser > 0 && $turnUser !== $prevTurnUser) {
        duel_async_turn_notice($m, $row, $turnUser, $deadline);
    }

    if ($st['over'] && (string)$row['status'] === 'active') {
        duel_finish($m, $row, $st);
    }

    $stamp = ['v' => (int)$row['version'], 'dl' => $deadline ? strtotime($deadline) : 0, 'st' => (string)$row['status']];
    $mid = (int)$row['id'];
    duel_after_commit(static fn() => duel_stamp($mid, $stamp));
}

/** Il bot gioca finche' tocca a lui (anche piu' turni di fila, o un turno extra). */
function duel_bot_turns(mysqli $m, array &$row, array &$st): void
{
    if (!duel_is_bot_match($row)) {
        return;
    }
    $level = in_array((string)$row['bot_level'], DUEL_BOT_LEVELS, true) ? (string)$row['bot_level'] : 'normale';
    for ($n = 0; $n < 30 && duel_actor_side($st) === 1; $n++) {
        [$move, $target] = duel_bot_choose($st, $level, (int)((int)$row['seed'] % 1000003) + (int)$row['version']);
        [$st, $events] = duel_apply($st, $move, (int)$target);
        duel_step($m, $row, $st, $events, 'bot', ['move' => $move, 'target' => (int)$target, 'side' => 1]);
    }
}

/**
 * La mossa di un giocatore. $version e' quella che il browser ha visto: se
 * nel frattempo la partita e' andata avanti la mossa viene rifiutata (409) e
 * il browser si aggiorna. $clientId riconosce il doppio invio della stessa
 * mossa, che non si applica due volte.
 */
function duel_play(mysqli $m, int $matchId, int $userId, int $version, string $move, int $target, string $clientId): array
{
    return duel_tx($m, static function () use ($m, $matchId, $userId, $version, $move, $target, $clientId): array {
        $row = duel_match_row($m, $matchId, true);
        if (!$row) {
            throw duel_user_error('Partita non trovata.', 'Match not found.', 404, 'NOT_FOUND');
        }
        $side = duel_match_side($row, $userId);
        if ($side === null) {
            throw duel_user_error('Non giochi questa partita.', 'You are not playing this match.', 403, 'NOT_PLAYER');
        }
        $action = $clientId !== '' ? $userId . ':' . $clientId : null;
        if ($action !== null && (string)($row['last_client_action'] ?? '') === $action) {
            return ['v' => (int)$row['version'], 'duplicate' => true];
        }
        if ((string)$row['status'] !== 'active') {
            throw duel_user_error('La partita è finita.', 'The match is over.', 409, 'MATCH_OVER', ['v' => (int)$row['version']]);
        }

        duel_use_match_values(duel_match_rules($row));
        $st = duel_match_state($row);
        if (duel_tick_locked($m, $row, $st)) {
            // Il turno scaduto va salvato: niente eccezione (annullerebbe la
            // transazione), l'endpoint risponde con l'errore dopo il commit.
            duel_save($m, $row, $st);
            return ['v' => (int)$row['version'], 'error' => 'TIMED_OUT'];
        }
        if ((int)$row['version'] !== $version) {
            throw duel_user_error('La partita è andata avanti: aggiorno.', 'The match moved on: refreshing.', 409, 'STALE', ['v' => (int)$row['version']]);
        }
        if (duel_actor_side($st) !== $side) {
            throw duel_user_error('Non è il tuo turno.', 'It\'s not your turn.', 409, 'NOT_YOUR_TURN', ['v' => (int)$row['version']]);
        }

        try {
            [$st, $events] = duel_apply($st, $move, $target);
        } catch (DuelError $e) {
            throw duel_user_error('Mossa non valida.', 'Invalid move.', 400, 'BAD_MOVE', ['v' => (int)$row['version']]);
        }
        duel_step($m, $row, $st, $events, 'move', ['move' => $move, 'target' => $target, 'side' => $side], $userId);
        duel_bot_turns($m, $row, $st);
        duel_save($m, $row, $st, $action);
        return ['v' => (int)$row['version']];
    });
}

/**
 * Tempo scaduto, a riga gia' bloccata: Guardia automatica (o la prima mossa
 * possibile) e un turno scaduto in piu'; al terzo la partita e' persa.
 * Restituisce true se ha cambiato qualcosa (poi va chiamato duel_save()).
 */
function duel_tick_locked(mysqli $m, array &$row, array &$st): bool
{
    if ((string)$row['status'] !== 'active' || empty($row['turn_deadline'])) {
        return false;
    }
    if (strtotime((string)$row['turn_deadline']) + DUEL_DEADLINE_GRACE > time()) {
        return false;
    }
    $side = duel_actor_side($st);
    if ($side === null) {
        return false;
    }
    $col = $side === 0 ? 'timeouts_p1' : 'timeouts_p2';
    $row[$col] = (int)$row[$col] + 1;
    $userId = duel_side_user($row, $side);

    if ($row[$col] >= (int)(duel_match_rules($row)['timeouts_max'] ?? DUEL_TIMEOUTS_MAX)) {
        [$st, $events] = duel_forfeit($st, $side, 'timeout');
        duel_step($m, $row, $st, $events, 'timeout', ['side' => $side, 'auto' => 'forfeit'], $userId);
        return true;
    }

    $legal = duel_legal($st);
    if (isset($legal['guardia'])) {
        [$move, $target] = ['guardia', 0];
    } elseif (isset($legal['passa'])) {
        [$move, $target] = ['passa', 0];
    } else {
        $move = (string)array_key_first($legal);
        $target = (int)$legal[$move][0];
    }
    [$st, $events] = duel_apply($st, $move, $target);
    duel_step($m, $row, $st, $events, 'timeout', ['move' => $move, 'target' => $target, 'side' => $side], $userId);
    duel_bot_turns($m, $row, $st);
    return true;
}

/** Controlla il tempo di una partita (chi guarda, la pulizia). True se e' cambiata. */
function duel_tick(mysqli $m, int $matchId): bool
{
    return duel_tx($m, static function () use ($m, $matchId): bool {
        $row = duel_match_row($m, $matchId, true);
        if (!$row || (string)$row['status'] !== 'active') {
            return false;
        }
        duel_use_match_values(duel_match_rules($row));
        $st = duel_match_state($row);
        $changed = false;
        // Piu' turni scaduti di fila (nessuno guarda da un po'): uno alla volta,
        // ognuno col suo tempo pieno, quindi qui al massimo uno.
        if (duel_tick_locked($m, $row, $st)) {
            duel_save($m, $row, $st);
            $changed = true;
        }
        return $changed;
    });
}

/** Abbandono. Una stanza in attesa invece si chiude e basta. */
function duel_forfeit_by(mysqli $m, int $matchId, int $userId): array
{
    return duel_tx($m, static function () use ($m, $matchId, $userId): array {
        $row = duel_match_row($m, $matchId, true);
        if (!$row) {
            throw duel_user_error('Partita non trovata.', 'Match not found.', 404, 'NOT_FOUND');
        }
        $side = duel_match_side($row, $userId);
        if ($side === null) {
            throw duel_user_error('Non giochi questa partita.', 'You are not playing this match.', 403, 'NOT_PLAYER');
        }
        if ((string)$row['status'] === 'waiting') {
            duel_exec($m, "UPDATE game_matches SET status = 'cancelled', end_reason = 'closed', finished_at = NOW(), updated_at = NOW() WHERE id = ?", 'i', [$matchId]);
            // La stanza di una sfida a un amico: anche la sfida e' ritirata, subito.
            if (!empty($row['challenge_id']) && rt_has_table($m, 'game_challenges')) {
                duel_exec($m, "UPDATE game_challenges SET status = 'cancelled', answered_at = NOW() WHERE id = ? AND status = 'pending'", 'i', [(int)$row['challenge_id']]);
            }
            return ['v' => (int)$row['version'], 'closed' => true];
        }
        if ((string)$row['status'] !== 'active') {
            throw duel_user_error('La partita è già finita.', 'The match is already over.', 409, 'MATCH_OVER');
        }
        duel_use_match_values(duel_match_rules($row));
        $st = duel_match_state($row);
        [$st, $events] = duel_forfeit($st, $side, 'forfeit');
        duel_step($m, $row, $st, $events, 'forfeit', ['side' => $side], $userId);
        duel_save($m, $row, $st);
        return ['v' => (int)$row['version']];
    });
}

// ─────────────────────────────────────────────────────────────────────────
// Fine partita
// ─────────────────────────────────────────────────────────────────────────

/**
 * Chiude la partita: vincitore, punti Ranked, e dopo il commit gli agganci di
 * sempre (Rewind, missioni, achievement), come faceva gd_finish().
 */
function duel_finish(mysqli $m, array &$row, array $st): void
{
    $winnerSide = $st['winner'] === null ? null : (int)$st['winner'];
    $users = [duel_side_user($row, 0), duel_side_user($row, 1)];
    $winner = $winnerSide === null ? null : $users[$winnerSide];
    $loser = $winnerSide === null ? null : $users[1 - $winnerSide];
    $reason = (string)($st['reason'] ?? 'ko');

    $rating = ['wd' => null, 'ld' => null, 'wa' => null, 'la' => null];
    if ((string)$row['mode'] === 'ranked' && $users[0] > 0 && $users[1] > 0) {
        $rating = duel_rating_apply($m, $row, $winnerSide);
        duel_season_record($m, $row, $rating['sides'], $winnerSide);
    }
    if ($winner !== null && $winner > 0) {
        duel_reward_daily_win($m, $row, $winner);
    }

    $data = duel_match_columns($m, [
        'winner_rating_delta' => $rating['wd'],
        'loser_rating_delta' => $rating['ld'],
        'winner_rating_after' => $rating['wa'],
        'loser_rating_after' => $rating['la'],
    ]);
    $set = 'status = \'finished\', winner_id = ?, loser_id = ?, end_reason = ?, finished_at = NOW(), turn_deadline = NULL, current_turn_user_id = NULL';
    if (!rt_has_col($m, 'game_matches', 'current_turn_user_id')) {
        $set = str_replace(', current_turn_user_id = NULL', '', $set);
    }
    $types = 'iis';
    $values = [$winner, $loser, $reason];
    foreach ($data as $col => $value) {
        $set .= ", `$col` = ?";
        $types .= 'i';
        $values[] = $value;
    }
    duel_exec($m, "UPDATE game_matches SET $set WHERE id = ?", $types . 'i', [...$values, (int)$row['id']]);
    $row['status'] = 'finished';
    $row['winner_id'] = $winner;
    $row['loser_id'] = $loser;
    $row['end_reason'] = $reason;

    duel_after_commit(static function () use ($m, $users, $winnerSide): void {
        duel_finish_hooks($m, $users, $winnerSide);
    });
    if (duel_is_async($row)) {
        duel_async_end_notice($m, $row, $users, $winnerSide);
    }
}

/** In differita: tocca a te (al massimo un avviso ogni ora per partita). */
function duel_async_turn_notice(mysqli $m, array $row, int $userId, ?string $deadline): void
{
    $side = duel_match_side($row, $userId);
    $other = $side === null ? 0 : duel_side_user($row, 1 - $side);
    $name = (string)(duel_row($m, 'SELECT username FROM utenti WHERE id = ?', 'i', [$other])['username'] ?? '');
    $id = (int)$row['id'];
    $hours = $deadline ? max(1, (int)round((strtotime($deadline) - time()) / 3600)) : 24;
    duel_after_commit(static fn() => duel_notify($m, $userId, "Duello: tocca a te contro $name", "Duel: your turn against $name",
        "Hai $hours ore per la tua mossa. Apri la partita da Duelli o da /it/duelli/arena?m=$id.",
        "You have $hours hours for your move. Open the match from Duels or /en/duelli/arena?m=$id.", 60));
}

/** In differita: com'e' finita (chi gioca non e' davanti allo schermo). */
function duel_async_end_notice(mysqli $m, array $row, array $users, ?int $winnerSide): void
{
    $names = [];
    foreach ($users as $side => $userId) {
        $names[$side] = (string)(duel_row($m, 'SELECT username FROM utenti WHERE id = ?', 'i', [$userId])['username'] ?? '');
    }
    $id = (int)$row['id'];
    foreach ($users as $side => $userId) {
        $other = $names[1 - $side];
        [$it, $en] = $winnerSide === null ? ['Pareggio', 'Draw'] : ($winnerSide === $side ? ['Hai vinto', 'You won'] : ['Hai perso', 'You lost']);
        duel_after_commit(static fn() => duel_notify($m, $userId, "Duello in differita: $it contro $other", "Turn-based duel: $en against $other",
            "La partita è finita. Il riepilogo è in /it/duelli/fine?m=$id.", "The match is over. The summary is at /en/duelli/fine?m=$id."));
    }
}

/** Rewind, missioni, achievement: gli stessi agganci dei duelli vecchi. Il bot resta fuori. */
function duel_finish_hooks(mysqli $m, array $users, ?int $winnerSide): void
{
    require_once __DIR__ . '/../stats_tracker.php';
    require_once __DIR__ . '/../mission_tracker.php';
    foreach ($users as $side => $userId) {
        if ($userId <= 0) {
            continue;
        }
        $won = $winnerSide === $side;
        $lost = $winnerSide !== null && !$won;
        try {
            stats_track_many($m, $userId, array_filter(['duels_played' => 1, 'duels_won' => $won ? 1 : 0, 'duels_lost' => $lost ? 1 : 0]));
            if (function_exists('trackMissionProgress')) {
                trackMissionProgress($m, $userId, 'play_duel');
                if ($won) {
                    trackMissionProgress($m, $userId, 'win_duel');
                }
            }
            if ($won) {
                require_once __DIR__ . '/../achievements.php';
                ach_sync($m, $userId, ['duels']);
            }
        } catch (Throwable $e) {
            error_log('[duel] agganci di fine partita: ' . $e->getMessage());
        }
    }
}

/**
 * Punti Ranked: Elo simmetrico (quanto prende chi vince lo perde chi perde,
 * niente inflazione). Contro lo stesso avversario contano al massimo
 * DUEL_RANKED_PAIR_DAY partite al giorno.
 */
function duel_rating_apply(mysqli $m, array $row, ?int $winnerSide): array
{
    $users = [(int)$row['player1_id'], (int)$row['player2_id']];
    foreach ($users as $userId) {
        duel_exec($m, 'INSERT IGNORE INTO game_player_stats (user_id) VALUES (?)', 'i', [$userId]);
    }
    $stats = [];
    foreach ($users as $side => $userId) {
        $stats[$side] = duel_row($m, 'SELECT * FROM game_player_stats WHERE user_id = ? FOR UPDATE', 'i', [$userId]) ?? ['rating' => 1000];
    }

    $pair = (int)(duel_row($m, "SELECT COUNT(*) AS n FROM game_matches
        WHERE engine_version IS NOT NULL AND mode = 'ranked' AND status = 'finished' AND id <> ?
          AND finished_at >= CURDATE()
          AND ((player1_id = ? AND player2_id = ?) OR (player1_id = ? AND player2_id = ?))", 'iiiii',
        [(int)$row['id'], $users[0], $users[1], $users[1], $users[0]])['n'] ?? 0);
    $counts = $pair < DUEL_RANKED_PAIR_DAY;

    $r = [(int)($stats[0]['rating'] ?? 1000), (int)($stats[1]['rating'] ?? 1000)];
    $expected0 = 1 / (1 + 10 ** (($r[1] - $r[0]) / 400));
    $score0 = $winnerSide === null ? 0.5 : ($winnerSide === 0 ? 1.0 : 0.0);
    $delta0 = $counts ? (int)round(DUEL_ELO_K * ($score0 - $expected0)) : 0;
    if ($counts && $winnerSide !== null && $delta0 === 0) {
        $delta0 = $winnerSide === 0 ? 1 : -1; // chi vince prende sempre almeno un punto
    }
    $deltas = [$delta0, -$delta0];
    $after = [max(100, $r[0] + $deltas[0]), max(100, $r[1] + $deltas[1])];

    foreach ([0, 1] as $side) {
        $won = $winnerSide === $side;
        $lost = $winnerSide !== null && !$won;
        duel_exec($m, 'UPDATE game_player_stats SET
                games_played = games_played + 1,
                wins = wins + ?, losses = losses + ?,
                rating = ?,
                season_points = season_points + ?,
                current_streak = IF(? = 1, current_streak + 1, 0),
                best_streak = IF(? = 1, GREATEST(best_streak, current_streak), best_streak)
            WHERE user_id = ?', 'iiiiiii',
            [$won ? 1 : 0, $lost ? 1 : 0, $after[$side], $won ? max(0, $deltas[$side]) : 0, $won ? 1 : 0, $won ? 1 : 0, $users[$side]]);
    }

    $sides = [[$r[0], $after[0]], [$r[1], $after[1]]];
    if ($winnerSide === null) {
        return ['wd' => $deltas[0], 'ld' => $deltas[1], 'wa' => $after[0], 'la' => $after[1], 'sides' => $sides];
    }
    return [
        'wd' => $deltas[$winnerSide], 'ld' => $deltas[1 - $winnerSide],
        'wa' => $after[$winnerSide], 'la' => $after[1 - $winnerSide], 'sides' => $sides,
    ];
}

// ─────────────────────────────────────────────────────────────────────────
// Tempo reale
// ─────────────────────────────────────────────────────────────────────────

/** Il timbro della partita: chi guarda lo legge senza database (api/duel/rt.php). */
function duel_stamp(int $matchId, array $values): void
{
    rt_update('d:' . $matchId, static function (array $data) use ($values): array {
        return array_merge($data, $values, ['at' => time()]);
    });
}

// ─────────────────────────────────────────────────────────────────────────
// Cosa vede il browser
// ─────────────────────────────────────────────────────────────────────────

function duel_rank(int $rating): array
{
    $tiers = [[1900, 'leggenda', 'Leggenda', 'Legend'], [1600, 'campione', 'Campione', 'Champion'], [1400, 'diamante', 'Diamante', 'Diamond'],
        [1200, 'oro', 'Oro', 'Gold'], [1000, 'argento', 'Argento', 'Silver'], [0, 'bronzo', 'Bronzo', 'Bronze']];
    foreach ($tiers as $i => [$min, $key, $it, $en]) {
        if ($rating >= $min) {
            return ['key' => $key, 'label' => rt_t($it, $en), 'min' => $min, 'next' => $i > 0 ? $tiers[$i - 1][0] : null];
        }
    }
    return ['key' => 'bronzo', 'label' => rt_t('Bronzo', 'Bronze'), 'min' => 0, 'next' => 1000];
}

/** Nome, immagine e punti di chi gioca. */
function duel_players_view(mysqli $m, array $row): array
{
    $out = [];
    foreach ([0, 1] as $side) {
        $userId = duel_side_user($row, $side);
        if ($userId === DUEL_BOT_USER) {
            $out[$side] = ['id' => 0, 'bot' => true, 'username' => 'Cripsum Bot', 'avatar' => '/img/Susremaster.png',
                'level' => (string)$row['bot_level'], 'rating' => null, 'rank' => null];
            continue;
        }
        $user = duel_row($m, 'SELECT u.username, s.rating FROM utenti u LEFT JOIN game_player_stats s ON s.user_id = u.id WHERE u.id = ?', 'i', [$userId]);
        $rating = (int)($user['rating'] ?? 1000);
        $out[$side] = ['id' => $userId, 'bot' => false, 'username' => (string)($user['username'] ?? 'Utente'),
            'avatar' => '/includes/get_pfp.php?id=' . $userId, 'rating' => $rating, 'rank' => duel_rank($rating), 'badge' => duel_season_badge($m, $userId)];
    }
    return $out;
}

/** La parte che non cambia: carte, kit coi testi, giocatori, regole. */
function duel_view_static(mysqli $m, array $row, array $st): array
{
    $pids = array_map(static fn($u) => (int)$u['pid'], $st['units']);
    $chars = duel_db_characters($m, $pids);
    $lang = rt_lang();
    $units = [];
    foreach ($st['units'] as $id => $u) {
        $kit = $u['kit'];
        $units[$id] = [
            'id' => (int)$id, 'side' => (int)$u['side'], 'slot' => (int)$u['slot'], 'pid' => (int)$u['pid'],
            'name' => (string)$u['name'], 'rarity' => (string)$u['rarity'], 'role' => (string)$u['role'], 'level' => (int)$u['level'],
            'img' => duel_media($chars[(int)$u['pid']]['img_url'] ?? ''),
            'audio' => duel_audio($chars[(int)$u['pid']]['audio_url'] ?? ''),
            'max' => $u['max'],
            'cost' => duel_rarity((string)$u['rarity'])['cost'],
            'theme' => $kit['theme'] ?? null,
            'kit' => duel_kit_text($kit, $lang),
            'moves' => [
                'abilita' => ['cost' => (int)($kit['skill']['cost'] ?? 1), 'cd' => (int)($kit['skill']['cd'] ?? 0), 'target' => (string)($kit['skill']['target'] ?? 'enemy')],
                'ultimate' => ['target' => (string)($kit['ultimate']['target'] ?? 'enemy')],
                'attacco' => ['target' => (string)($kit['attack']['target'] ?? 'enemy')],
            ],
            'forms' => array_map(static fn($f) => ['it' => $f['it'] ?? '', 'en' => $f['en'] ?? ''], $kit['forms'] ?? []),
            'stacks' => isset($kit['stacks']) ? ['name' => (string)$kit['stacks']['name'], 'max' => (int)$kit['stacks']['max']] : null,
        ];
    }
    $rules = duel_match_rules($row);
    return [
        'id' => (int)$row['id'],
        'mode' => (string)$row['mode'],
        'code' => (string)$row['mode'] === 'private' ? (string)$row['room_code'] : null,
        'players' => duel_players_view($m, $row),
        'units' => $units,
        'rules' => ['levels' => $rules['levels'], 'cost_limit' => (int)$rules['cost_limit'], 'turn_seconds' => (int)$rules['turn_seconds'],
            'async' => !empty($rules['async']), 'timeouts_max' => (int)$rules['timeouts_max'],
            'turn_limit' => duel_rules()['turn_limit'], 'ps_max' => duel_rules()['ps_max'], 'charge_max' => duel_rules()['charge_max']],
        'costs' => [(int)($row['team_cost_p1'] ?? 0), (int)($row['team_cost_p2'] ?? 0)],
    ];
}

/**
 * La parte che cambia a ogni mossa. Mosse possibili e anteprime solo a chi
 * deve giocare.
 */
function duel_view_dyn(array $row, array $st, ?int $viewerSide): array
{
    $units = [];
    foreach ($st['units'] as $id => $u) {
        $fx = [];
        foreach ($u['fx'] as $f) {
            $item = ['k' => (string)$f['k'], 'v' => $f['v'], 't' => (int)$f['t'], 'st' => (int)$f['st'], 'src' => (int)$f['src']];
            if (isset($f['meta']['pool'])) {
                $item['pool'] = (int)round((float)$f['meta']['pool']);
            }
            $fx[] = $item;
        }
        $units[$id] = [
            'hp' => (int)$u['hp'], 'shield' => (int)$u['shield'], 'charge' => (int)$u['charge'], 'ko' => (bool)$u['ko'],
            'guard' => (bool)$u['guard'], 'cd' => (int)$u['cd'], 'stk' => (int)$u['stk'], 'form' => $u['form'],
            'fx' => $fx,
            'eff' => array_map(static fn($v) => is_float($v) ? round($v, 1) : $v, duel_eff($st, (int)$id)),
            'stats' => $u['stats'],
        ];
    }
    $actorSide = duel_actor_side($st);
    $out = [
        'v' => (int)$row['version'],
        'status' => (string)$row['status'],
        'turn' => (int)$st['turn'],
        'actor' => $st['actor'] === null ? null : (int)$st['actor'],
        'actor_side' => $actorSide,
        'ps' => $st['ps'],
        'terrain' => $st['terrain'],
        'over' => (bool)$st['over'],
        'winner' => $st['winner'],
        'reason' => $st['reason'],
        'units' => $units,
        'timeline' => duel_timeline($st, 8),
        'deadline' => !empty($row['turn_deadline']) ? strtotime((string)$row['turn_deadline']) : null,
        'timeouts' => [(int)($row['timeouts_p1'] ?? 0), (int)($row['timeouts_p2'] ?? 0)],
        'fatigue' => duel_fatigue($st),
    ];
    if ($viewerSide !== null && $actorSide === $viewerSide && (string)$row['status'] === 'active') {
        $legal = duel_legal($st);
        $preview = [];
        foreach ($legal as $move => $targets) {
            foreach ($targets as $target) {
                if ($target > 0 && $move !== 'guardia') {
                    $preview[$move][$target] = duel_preview($st, $move, (int)$target);
                }
            }
        }
        $out['legal'] = $legal;
        $out['preview'] = $preview;
        $out['skill_cost'] = duel_skill_cost($st, (int)$st['actor']);
    }
    if ((string)$row['status'] === 'finished') {
        $out['result'] = [
            'winner_id' => $row['winner_id'] === null ? null : (int)$row['winner_id'],
            'reason' => (string)($row['end_reason'] ?? $st['reason'] ?? ''),
            'rating' => [
                'winner_delta' => isset($row['winner_rating_delta']) ? (int)$row['winner_rating_delta'] : null,
                'loser_delta' => isset($row['loser_rating_delta']) ? (int)$row['loser_rating_delta'] : null,
                'winner_after' => isset($row['winner_rating_after']) ? (int)$row['winner_rating_after'] : null,
                'loser_after' => isset($row['loser_rating_after']) ? (int)$row['loser_rating_after'] : null,
            ],
        ];
    }
    return $out;
}

/** Gli eventi dopo una versione, senza lo stato iniziale (che ha il dado). */
function duel_events_since(mysqli $m, int $matchId, int $since, int $limit = 60): array
{
    $rows = duel_rows($m, 'SELECT version, kind, user_id, payload_json FROM game_match_events WHERE match_id = ? AND version > ? ORDER BY version LIMIT ?',
        'iii', [$matchId, $since, $limit]);
    $out = [];
    foreach ($rows as $r) {
        $payload = json_decode((string)$r['payload_json'], true) ?: [];
        unset($payload['state']);
        $out[] = ['v' => (int)$r['version'], 'kind' => (string)$r['kind']] + $payload;
    }
    return $out;
}

/**
 * Il replay: si rigioca la partita dallo stato iniziale con le mosse
 * salvate. Solo a partita finita (prima il dado e' segreto).
 *
 * Una partita giocata con un motore di un'altra versione (DUEL_ENGINE_VERSION)
 * non si puo' rigiocare uguale: si usano gli eventi salvati a ogni passo e lo
 * stato solo all'inizio e alla fine ('legacy'; l'arena la suona di fila).
 */
function duel_replay(mysqli $m, array $row): array
{
    $rows = duel_rows($m, 'SELECT version, kind, payload_json FROM game_match_events WHERE match_id = ? ORDER BY version', 'i', [(int)$row['id']]);
    if (!$rows || (int)$rows[0]['version'] !== 0) {
        throw duel_user_error('Replay non disponibile.', 'Replay not available.', 404, 'NO_REPLAY');
    }
    duel_use_match_values(duel_match_rules($row));
    $start = json_decode((string)$rows[0]['payload_json'], true);
    $st = $start['state'];
    $units = [];
    foreach ($st['units'] as $id => $u) {
        $units[(int)$id] = $u;
    }
    $st['units'] = $units;

    $steps = [['v' => 0, 'kind' => 'start', 'ev' => $start['ev'] ?? [], 'dyn' => duel_view_dyn(['version' => 0, 'status' => 'active'] + $row, $st, null)]];
    $first = $st;
    $legacy = (int)($row['engine_version'] ?? DUEL_ENGINE_VERSION) !== DUEL_ENGINE_VERSION;
    foreach (array_slice($rows, 1) as $r) {
        $p = json_decode((string)$r['payload_json'], true) ?: [];
        if ($legacy) {
            $steps[] = ['v' => (int)$r['version'], 'kind' => (string)$r['kind'], 'move' => $p['move'] ?? null, 'target' => $p['target'] ?? null,
                'side' => $p['side'] ?? null, 'ev' => $p['ev'] ?? [], 'dyn' => null];
            continue;
        }
        if (($p['auto'] ?? '') === 'forfeit' || $r['kind'] === 'forfeit') {
            [$st, $events] = duel_forfeit($st, (int)$p['side'], $r['kind'] === 'forfeit' ? 'forfeit' : 'timeout');
        } else {
            [$st, $events] = duel_apply($st, (string)$p['move'], (int)($p['target'] ?? 0));
        }
        $steps[] = ['v' => (int)$r['version'], 'kind' => (string)$r['kind'], 'move' => $p['move'] ?? null, 'target' => $p['target'] ?? null,
            'side' => $p['side'] ?? null, 'ev' => $events, 'dyn' => duel_view_dyn(['version' => (int)$r['version'], 'status' => 'active'] + $row, $st, null)];
    }
    if ($legacy && count($steps) > 1) {
        $steps[count($steps) - 1]['dyn'] = duel_view_dyn(['version' => (int)$row['version'], 'status' => 'active'] + $row, duel_match_state($row), null);
    }
    return ['static' => duel_view_static($m, $row, $first), 'steps' => $steps, 'legacy' => $legacy];
}
