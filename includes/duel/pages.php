<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — quello che serve alle pagine della fase 5 (lobby «Duelli»,
 * Squadra, fine partita): i dati che finiscono gia' dentro la pagina, cosi'
 * al primo caricamento non parte nessuna richiesta (come l'inventario).
 *
 * - duel_page_config(): lingua, indirizzi, colori delle rarita', ruoli.
 * - duel_card_view(), duel_traits_view(), duel_teams_view(): carte, legami
 *   e squadre salvate coi conti gia' fatti (costo, legami attivi, ruoli).
 * - duel_leaderboard(): classifica di tutti e degli amici.
 * - duel_lobby_view(): la lobby intera.
 * - duel_summary(): il riepilogo di una partita finita.
 */

require_once __DIR__ . '/lobby.php';
require_once __DIR__ . '/text.php';

const DUEL_ATTACK_ROLES = ['DPS', 'Burst DPS', 'Sub DPS', 'Bruiser'];
const DUEL_GUARD_ROLES = ['Tank', 'Support'];
const DUEL_HEAL_ROLES = ['Healer'];

/** Indirizzi delle pagine nuove. */
function duel_urls(string $lang): array
{
    $base = '/' . $lang . '/duelli/';
    return [
        'lobby' => $base, 'team' => $base . 'squadra', 'arena' => $base . 'arena', 'end' => $base . 'fine', 'replay' => $base . 'replay',
        'classic' => '/' . $lang . '/game/lobby.php',
    ];
}

/** Quello che serve a tutte le pagine nel browser. */
function duel_page_config(string $lang, int $userId): array
{
    $en = $lang === 'en';
    $rarities = [];
    $gacha = function_exists('gacha_rarity_defs') ? gacha_rarity_defs() : [];
    foreach (duel_rarities() as $key => $r) {
        $base = $key === 'segreto_limitato' ? 'segreto' : $key;
        $rarities[$key] = [
            'n' => $key === 'segreto_limitato' ? ($en ? 'Limited secret' : 'Segreto limitato') : ($gacha[$base][$lang] ?? ucfirst($base)),
            'c' => $gacha[$base]['color'] ?? '#9ca3af',
            'cost' => (int)$r['cost'],
        ];
    }
    return [
        'lang' => $lang,
        'me' => $userId,
        'csrf' => csrf_token(),
        'api' => '/api/duel/',
        'rt' => '/api/rt/poll.php',
        'urls' => duel_urls($lang),
        'rarities' => $rarities,
        'roles' => DUEL_ROLES,
        'budget' => duel_rules()['ranked_budget'],
        'level_max' => duel_rules()['level_max'],
    ];
}

/** Una carta: chi e', quanto costa, ruolo, livello, legami. */
function duel_card_view(array $char, int $level): array
{
    $kit = duel_character_kit($char, $level);
    return [
        'id' => (int)$char['id'], 'name' => (string)$char['nome'], 'img' => duel_media($char['img_url'] ?? ''),
        'rarity' => $kit['rarity'], 'role' => $kit['role'], 'level' => $level, 'cost' => duel_rarity($kit['rarity'])['cost'],
        'traits' => array_values($kit['traits'] ?? []), 'unique' => !empty($kit['unique']),
    ];
}

/** I legami con i bonus scritti: [chiave => [n, tiers => [2 => testo, 3 => testo]]]. */
function duel_traits_view(string $lang): array
{
    $out = [];
    foreach (duel_traits() as $key => $t) {
        $tiers = [];
        foreach ($t['tiers'] as $need => $bonus) {
            $tiers[(int)$need] = duel_text_trait_bonus($bonus, $lang);
        }
        ksort($tiers);
        $out[$key] = ['n' => (string)($t[$lang] ?? $t['it'] ?? $key), 'tiers' => $tiers];
    }
    return $out;
}

/**
 * Cosa dice una squadra: costo, legami attivi e quasi attivi, ruoli coperti.
 * $cards = le carte (duel_card_view) in campo, da 0 a 3.
 */
function duel_team_facts(array $cards, array $traitsView): array
{
    $cost = 0;
    $count = [];
    $roles = [];
    foreach ($cards as $c) {
        $cost += (int)$c['cost'];
        $roles[] = $c['role'];
        foreach ($c['traits'] as $t) {
            $count[$t] = ($count[$t] ?? 0) + 1;
        }
    }
    $traits = [];
    foreach ($count as $key => $n) {
        if (!isset($traitsView[$key])) {
            continue;
        }
        $needs = array_keys($traitsView[$key]['tiers']);
        $active = 0;
        foreach ($needs as $need) {
            if ($n >= $need) {
                $active = max($active, $need);
            }
        }
        $traits[] = ['key' => $key, 'n' => $n, 'max' => max($needs), 'active' => $active];
    }
    usort($traits, static fn($a, $b) => [$b['active'], $b['n']] <=> [$a['active'], $a['n']]);
    return [
        'cost' => $cost,
        'traits' => $traits,
        'attack' => (bool)array_intersect($roles, DUEL_ATTACK_ROLES),
        'guard' => (bool)array_intersect($roles, DUEL_GUARD_ROLES),
        'heal' => (bool)array_intersect($roles, DUEL_HEAL_ROLES),
    ];
}

/** Le squadre salvate con le carte (al livello della collezione) e i conti fatti. */
function duel_teams_view(mysqli $m, int $userId, string $lang, ?array $collection = null, ?array $traitsView = null): array
{
    $teams = duel_teams($m, $userId);
    if (!$teams) {
        return [];
    }
    $collection ??= duel_db_collection($m, $userId);
    $traitsView ??= duel_traits_view($lang);
    $chars = duel_db_characters($m, array_merge(...array_map(static fn($t) => $t['team'], $teams)));
    $out = [];
    foreach ($teams as $t) {
        $cards = [];
        $missing = 0;
        foreach ($t['team'] as $pid) {
            if (isset($chars[$pid], $collection[$pid])) {
                $cards[] = duel_card_view($chars[$pid], $collection[$pid]);
            } else {
                $missing++; // venduto o tolto dalla collezione dopo il salvataggio
            }
        }
        $out[] = ['slot' => $t['slot'], 'name' => $t['name'], 'cards' => $cards, 'missing' => $missing] + duel_team_facts($cards, $traitsView);
    }
    return $out;
}

/**
 * La classifica: i primi di tutti e gli amici (tabella friendships del sito,
 * se c'e'), con la posizione di chi guarda. Conta chi ha giocato almeno una
 * partita; i punti sono quelli di sempre (la «Stagione 0»).
 */
function duel_leaderboard(mysqli $m, int $userId): array
{
    $row = static fn(array $r): array => ['id' => (int)$r['id'], 'username' => (string)$r['username'], 'rating' => (int)$r['rating'],
        'rank' => duel_rank((int)$r['rating'])['key']];
    $all = array_map($row, duel_rows($m, 'SELECT s.user_id AS id, s.rating, u.username FROM game_player_stats s JOIN utenti u ON u.id = s.user_id
        WHERE s.games_played > 0 ORDER BY s.rating DESC, s.user_id LIMIT 10'));

    $mine = duel_row($m, 'SELECT rating, games_played FROM game_player_stats WHERE user_id = ?', 'i', [$userId]);
    $pos = null;
    if ($mine && (int)$mine['games_played'] > 0) {
        $pos = 1 + (int)(duel_row($m, 'SELECT COUNT(*) AS n FROM game_player_stats WHERE games_played > 0 AND (rating > ? OR (rating = ? AND user_id < ?))',
            'iii', [(int)$mine['rating'], (int)$mine['rating'], $userId])['n'] ?? 0);
    }

    $friends = [];
    if (rt_has_table($m, 'friendships')) {
        $ids = [$userId];
        foreach (duel_rows($m, 'SELECT IF(user_one_id = ?, user_two_id, user_one_id) AS fid FROM friendships WHERE user_one_id = ? OR user_two_id = ? LIMIT 300',
            'iii', [$userId, $userId, $userId]) as $f) {
            $ids[] = (int)$f['fid'];
        }
        $ids = array_values(array_unique($ids));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $friends = array_map($row, duel_rows($m, "SELECT u.id, u.username, COALESCE(s.rating, 1000) AS rating FROM utenti u
            LEFT JOIN game_player_stats s ON s.user_id = u.id
            WHERE u.id IN ($marks) AND (u.id = ? OR COALESCE(s.games_played, 0) > 0)
            ORDER BY rating DESC, u.id LIMIT 30", str_repeat('i', count($ids)) . 'i', [...$ids, $userId]));
    }
    return ['all' => $all, 'friends' => $friends, 'pos' => $pos];
}

/** La lobby: la parte comune (duel_hub) piu' squadre, classifica, coda, partita in corso. */
function duel_lobby_view(mysqli $m, int $userId, string $lang): array
{
    $hub = duel_hub($m, $userId);
    $hub['teams'] = duel_teams_view($m, $userId, $lang);
    $hub['traits'] = duel_traits_view($lang);
    $hub['board'] = duel_leaderboard($m, $userId);
    $hub['collection'] = count(duel_db_collection($m, $userId));

    // La partita aperta con chi e contro chi, e se tocca a te.
    if ($hub['active']) {
        $row = duel_match_row($m, (int)$hub['active']['id']);
        if ($row) {
            $side = duel_match_side($row, $userId);
            $players = duel_players_view($m, $row);
            $opp = $side === null ? null : ($players[1 - $side] ?? null);
            $hub['active']['opponent'] = $opp ? (string)$opp['username'] : null;
            $hub['active']['turn'] = (int)$row['turn_number'];
            $hub['active']['my_turn'] = $side !== null && (string)$row['status'] === 'active' && (int)($row['current_turn_user_id'] ?? 0) === $userId;
            $hub['active']['expires'] = (string)$row['status'] === 'waiting' ? strtotime((string)$row['created_at']) + DUEL_ROOM_MINUTES * 60 : null;
        }
    }
    foreach ($hub['live'] as $i => $live) {
        $hub['live'][$i]['watchers'] = duel_watchers($m, (int)$live['id']);
    }
    return $hub;
}

/** Quanti stanno guardando una partita (come duel_spectator_count dell'API). */
function duel_watchers(mysqli $m, int $matchId): int
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

/**
 * Chi puo' rivedere una partita finita: chi l'ha giocata, e gli altri se la
 * partita era aperta agli spettatori e non era contro il bot (come il replay).
 */
function duel_can_review(array $row, int $userId): bool
{
    if ((string)$row['status'] !== 'finished') {
        return false;
    }
    if (duel_match_side($row, $userId) !== null) {
        return true;
    }
    return (int)$row['spectator_allowed'] === 1 && !duel_is_bot_match($row);
}

/**
 * Il riepilogo di una partita finita (tavola 5): esito, punti con la soglia
 * del rango, MVP e i numeri di tutte e sei le carte. Il colpo piu' forte
 * viene dagli eventi salvati, non serve al motore.
 */
function duel_summary(mysqli $m, int $userId, array $row, string $lang): array
{
    duel_use_match_values(duel_match_rules($row));
    $st = duel_match_state($row);
    $static = duel_view_static($m, $row, $st);
    $side = duel_match_side($row, $userId);
    $winnerSide = $st['winner'] === null ? null : (int)$st['winner'];

    // Colpo piu' forte di ogni carta, e quanto e' durata la partita.
    $best = [];
    $times = duel_row($m, 'SELECT MIN(created_at) AS a, MAX(created_at) AS b FROM game_match_events WHERE match_id = ?', 'i', [(int)$row['id']]);
    foreach (duel_rows($m, 'SELECT payload_json FROM game_match_events WHERE match_id = ? AND version > 0', 'i', [(int)$row['id']]) as $r) {
        $p = json_decode((string)$r['payload_json'], true) ?: [];
        foreach ($p['ev'] ?? [] as $e) {
            if (($e['t'] ?? '') === 'hit' && isset($e['src']) && empty($e['miss'])) {
                $best[(int)$e['src']] = max($best[(int)$e['src']] ?? 0, (int)($e['amount'] ?? 0));
            }
        }
    }
    $seconds = ($times && $times['a'] && $times['b']) ? max(0, strtotime((string)$times['b']) - strtotime((string)$times['a'])) : 0;

    $units = [];
    $mvp = null;
    $mvpScore = -1;
    foreach ($st['units'] as $id => $u) {
        $id = (int)$id;
        $s = $u['stats'];
        $card = [
            'id' => $id, 'pid' => (int)$u['pid'], 'side' => (int)$u['side'], 'name' => (string)($static['units'][$id]['name'] ?? $u['name'] ?? ''),
            'img' => (string)($static['units'][$id]['img'] ?? ''), 'rarity' => (string)$u['rarity'], 'role' => (string)($static['units'][$id]['role'] ?? ''),
            'dealt' => (int)$s['dealt'], 'taken' => (int)$s['taken'], 'healed' => (int)$s['healed'], 'kos' => (int)$s['kos'], 'best' => $best[$id] ?? 0,
            'ko' => (bool)$u['ko'],
        ];
        $units[] = $card;
        // MVP: danni e cure contano uguale, un KO vale 400.
        $score = $card['dealt'] + $card['healed'] + 400 * $card['kos'] + ($winnerSide !== null && $card['side'] === $winnerSide ? 1 : 0);
        if ($score > $mvpScore) {
            $mvpScore = $score;
            $mvp = $id;
        }
    }

    // Punti Ranked: prima e dopo, soglie del rango.
    $rating = null;
    if ((string)$row['mode'] === 'ranked' && $side !== null && $winnerSide !== null && $row['winner_rating_delta'] !== null) {
        $won = $winnerSide === $side;
        $delta = (int)($won ? $row['winner_rating_delta'] : $row['loser_rating_delta']);
        $after = (int)($won ? $row['winner_rating_after'] : $row['loser_rating_after']);
        $before = $after - $delta;
        $rating = ['before' => $before, 'after' => $after, 'delta' => $delta, 'rank_before' => duel_rank($before), 'rank' => duel_rank($after)];
    }

    $players = $static['players'];
    $stats = $side === null ? null : duel_row($m, 'SELECT current_streak, wins, losses FROM game_player_stats WHERE user_id = ?', 'i', [$userId]);
    return [
        'id' => (int)$row['id'],
        'mode' => (string)$row['mode'],
        'side' => $side,
        'winner' => $winnerSide,
        'reason' => (string)($row['end_reason'] ?? $st['reason'] ?? ''),
        'turns' => (int)$st['turn'],
        'seconds' => $seconds,
        'players' => $players,
        'units' => $units,
        'mvp' => $mvp,
        'rating' => $rating,
        'streak' => $stats ? (int)$stats['current_streak'] : null,
        'rematch' => $side !== null && (string)$row['mode'] !== 'ranked',
        'bot' => duel_is_bot_match($row),
    ];
}
