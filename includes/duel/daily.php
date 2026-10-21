<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — la sfida del giorno (fase 8, tavola 1).
 *
 * Ogni giorno uno scenario uguale per tutti:
 * - una squadra nemica fissa, coi suoi livelli, e il livello del bot;
 * - le regole per la tua squadra: rarita' ammesse, costo massimo, un livello
 *   uguale per tutti;
 * - un obiettivo in turni (0 = basta vincere) e un premio in Godos.
 *
 * Si gioca contro il bot quante volte si vuole e conta il risultato migliore:
 * prima chi ha centrato l'obiettivo, poi chi ha vinto in meno turni, poi chi
 * c'e' arrivato prima. La prima volta che si centra l'obiettivo arriva il
 * premio (game_rewards, mai due volte).
 *
 * Lo scenario lo scrive lo staff nel pannello (Duelli › Sfida del giorno). Un
 * giorno senza scenario ne ha uno automatico, scelto dal giorno stesso (uguale
 * per tutti) e salvato alla prima richiesta, cosi' resta quello anche se
 * cambiano i personaggi.
 */

require_once __DIR__ . '/lobby.php';

/** I modelli degli scenari automatici, a rotazione. */
const DUEL_DAILY_TEMPLATES = ['muro', 'comuni', 'segreti', 'incubo'];

/** Gli obiettivi in turni dei modelli (tarati col simulatore: strumenti/duelli2/daily-calibra.php). */
const DUEL_DAILY_GOALS = ['muro' => 55, 'comuni' => 60, 'segreti' => 60, 'incubo' => 0];

/** Il premio di uno scenario automatico, la prima volta che si centra l'obiettivo. */
const DUEL_DAILY_REWARD = 100;

const DUEL_DAILY_ATTACK = ['DPS', 'Burst DPS', 'Sub DPS', 'Bruiser'];
const DUEL_DAILY_GUARD = ['Tank', 'Support', 'Healer'];

/** La migrazione della fase 8 c'e'. */
function duel_daily_ready(mysqli $m): bool
{
    return rt_has_table($m, 'game_daily') && rt_has_table($m, 'game_daily_results');
}

/** Oggi, nel fuso del sito (config/database.php imposta Europe/Rome). */
function duel_daily_today(): string
{
    return date('Y-m-d');
}

// ─────────────────────────────────────────────────────────────────────────
// Lo scenario automatico (senza database: lo usa anche il simulatore)
// ─────────────────────────────────────────────────────────────────────────

/**
 * Lo scenario automatico di un giorno: il modello gira coi giorni, i
 * personaggi li sceglie un dado che parte dal giorno, quindi e' lo stesso
 * per tutti e si puo' rifare. $chars: [id => riga di personaggi].
 */
function duel_daily_auto(string $day, array $chars): array
{
    $rnd = new Random\Randomizer(new Random\Engine\Xoshiro256StarStar(crc32('cripsum-daily:' . $day)));
    $pool = [];
    foreach ($chars as $id => $c) {
        $rarity = duel_rarity_key((string)($c['rarita'] ?? $c['rarità'] ?? ''), !empty($c['limitato']));
        $pool[(int)$id] = ['id' => (int)$id, 'name' => duel_daily_name((string)$c['nome']), 'role' => duel_role_key((string)($c['ruolo'] ?? '')),
            'rarity' => $rarity, 'cost' => duel_rarity($rarity)['cost'], 'tier' => duel_rarity($rarity)['tier']];
    }
    $template = DUEL_DAILY_TEMPLATES[intdiv((int)strtotime($day . ' 12:00:00'), 86400) % count(DUEL_DAILY_TEMPLATES)];
    $max = duel_rules()['level_max'];
    $budget = duel_rules()['ranked_budget'];
    $goal = DUEL_DAILY_GOALS[$template];

    /** Un personaggio a caso tra quelli che vanno bene, diverso da quelli gia' presi. */
    $one = static function (callable $ok, array $not = []) use ($rnd, $pool): ?array {
        $list = array_values(array_filter($pool, static fn($p) => $ok($p) && !in_array($p['id'], $not, true)));
        return $list ? $list[$rnd->getInt(0, count($list) - 1)] : null;
    };
    /** Tre personaggi con un attaccante e uno che difende o cura, costo tra $lo e $hi. */
    $team = static function (callable $ok, int $lo, int $hi) use ($rnd, $pool): ?array {
        $list = array_values(array_filter($pool, $ok));
        if (count($list) < 3) {
            return null;
        }
        for ($try = 0; $try < 3000; $try++) {
            $pick = array_map(static fn($k) => $list[$k], $rnd->pickArrayKeys($list, 3));
            $cost = array_sum(array_column($pick, 'cost'));
            $roles = array_column($pick, 'role');
            if ($cost >= $lo && $cost <= $hi && array_intersect($roles, DUEL_DAILY_ATTACK) && array_intersect($roles, DUEL_DAILY_GUARD)) {
                return $pick;
            }
        }
        return null;
    };

    $enemy = null;
    $bot = 'difficile';
    $level = $max;
    $rules = ['rarities' => [], 'cost_max' => $budget, 'level' => $max];
    switch ($template) {
        case 'muro':
            // Un Tank forte davanti, chi lo cura e chi lo aiuta dietro.
            $tank = $one(static fn($p) => $p['role'] === 'Tank' && $p['tier'] >= 3);
            $heal = $tank ? $one(static fn($p) => $p['role'] === 'Healer', [$tank['id']]) : null;
            $help = $heal ? $one(static fn($p) => in_array($p['role'], ['Support', 'Buffer'], true), [$tank['id'], $heal['id']]) : null;
            $enemy = $help ? [$tank, $heal, $help] : null;
            $title = ['Batti il muro di %s in %d turni', 'Break %s\'s wall in %d turns'];
            break;
        case 'comuni':
            $enemy = $team(static fn($p) => $p['tier'] <= 2, 5, 7);
            $rules['rarities'] = ['comune', 'raro'];
            $rules['cost_max'] = 0;
            $title = ['Solo Comuni e Rari contro %s', 'Only Commons and Rares against %s'];
            break;
        case 'segreti':
            // Piu' forti per rarita', ma piu' indietro di livello.
            $enemy = $team(static fn($p) => $p['tier'] >= 5, 18, 21);
            $bot = 'normale';
            $level = 3;
            $title = ['Tre Segreti: battili in %2$d turni', 'Three Secrets: beat them in %2$d turns'];
            break;
        default:
            $enemy = $team(static fn() => true, $budget - 4, $budget - 2);
            $bot = 'incubo';
            $title = ['Contro l\'Incubo: %s e compagni', 'Against the Nightmare: %s and friends'];
    }
    if (!$enemy) {
        // Pochi personaggi (il banco di prova): una squadra qualunque.
        $enemy = $team(static fn() => true, 3, 99) ?? array_slice(array_values($pool), 0, 3);
        $title = ['Batti %s e compagni', 'Beat %s and friends'];
    }
    return [
        'day' => $day,
        'title' => ['it' => sprintf($title[0], $enemy[0]['name'], $goal), 'en' => sprintf($title[1], $enemy[0]['name'], $goal)],
        'team' => array_map(static fn($p) => ['id' => $p['id'], 'level' => $level], $enemy),
        'bot' => $bot,
        'rules' => $rules,
        'goal' => $goal,
        'reward' => DUEL_DAILY_REWARD,
        'auto' => true,
        'template' => $template,
    ];
}

/** «DANTE» → «Dante», come l'arena. */
function duel_daily_name(string $name): string
{
    return mb_convert_case(mb_strtolower(trim($name), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

/**
 * La tua squadra va bene per lo scenario? Torna l'errore in italiano e
 * inglese, o null. $rarities: le rarita' del duello dei tre personaggi.
 */
function duel_daily_team_error(array $scenario, array $rarities): ?array
{
    $rules = $scenario['rules'];
    $allowed = $rules['rarities'] ?? [];
    if ($allowed) {
        foreach ($rarities as $r) {
            if (!in_array($r, $allowed, true)) {
                return [duel_daily_rarity_text($allowed, 'it') . ': ' . 'nella squadra c\'è un personaggio di un\'altra rarità.',
                    duel_daily_rarity_text($allowed, 'en') . ': your team has a character of another rarity.'];
            }
        }
    }
    $cost = duel_team_cost($rarities);
    if (($rules['cost_max'] ?? 0) > 0 && $cost > $rules['cost_max']) {
        return ["La squadra costa $cost: oggi il massimo è {$rules['cost_max']}.", "The team costs $cost: today's limit is {$rules['cost_max']}."];
    }
    return null;
}

/** «Solo Comuni e Rari», «Only Commons and Rares». */
function duel_daily_rarity_text(array $allowed, string $lang): string
{
    $defs = gacha_rarity_defs();
    $names = [];
    foreach ($allowed as $r) {
        $base = $r === 'segreto_limitato' ? 'segreto' : $r;
        $label = $defs[$base][$lang === 'en' ? 'en' : 'it'] ?? $r;
        $plural = $lang === 'en' ? $label . 's' : (['Comune' => 'Comuni', 'Raro' => 'Rari', 'Epico' => 'Epici', 'Leggendario' => 'Leggendari', 'Speciale' => 'Speciali', 'Segreto' => 'Segreti'][$label] ?? $label);
        if ($r === 'segreto_limitato') {
            $plural = $lang === 'en' ? 'limited Secrets' : 'Segreti limitati';
        }
        $names[] = $plural;
    }
    $names = array_values(array_unique($names));
    $last = array_pop($names);
    $list = $names ? implode(', ', $names) . ($lang === 'en' ? ' and ' : ' e ') . $last : $last;
    return ($lang === 'en' ? 'Only ' : 'Solo ') . $list;
}

/** Le regole a parole: «Solo Comuni e Rari · tutti al livello 6 · bot Difficile». */
function duel_daily_rules_text(array $scenario, string $lang): string
{
    $en = $lang === 'en';
    $r = $scenario['rules'];
    $parts = [];
    if (!empty($r['rarities'])) {
        $parts[] = duel_daily_rarity_text($r['rarities'], $lang);
    }
    if (($r['cost_max'] ?? 0) > 0) {
        $parts[] = $en ? 'team cost up to ' . $r['cost_max'] : 'squadra fino a costo ' . $r['cost_max'];
    }
    $parts[] = $en ? 'everyone at level ' . $r['level'] : 'tutti al livello ' . $r['level'];
    $bots = ['facile' => ['Facile', 'Easy'], 'normale' => ['Normale', 'Normal'], 'difficile' => ['Difficile', 'Hard'], 'incubo' => ['Incubo', 'Nightmare']];
    $parts[] = ($en ? 'bot ' : 'bot ') . ($bots[$scenario['bot']][$en ? 1 : 0] ?? $scenario['bot']);
    $text = implode(' · ', $parts);
    return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
}

/** L'obiettivo a parole: «Vinci entro 40 turni», «Vinci». */
function duel_daily_goal_text(array $scenario, string $lang): string
{
    $g = (int)$scenario['goal'];
    if ($g <= 0) {
        return $lang === 'en' ? 'Win' : 'Vinci';
    }
    return $lang === 'en' ? "Win within $g turns" : "Vinci entro $g turni";
}

// ─────────────────────────────────────────────────────────────────────────
// Lo scenario nel database
// ─────────────────────────────────────────────────────────────────────────

/** Una riga di game_daily come scenario. */
function duel_daily_from_row(array $r): array
{
    $team = json_decode((string)$r['team_json'], true);
    $rules = json_decode((string)$r['rules_json'], true);
    $max = duel_rules()['level_max'];
    return [
        'id' => (int)$r['id'],
        'day' => (string)$r['day'],
        'title' => ['it' => (string)$r['title_it'], 'en' => (string)$r['title_en']],
        'team' => array_map(static fn($e) => ['id' => (int)($e['id'] ?? 0), 'level' => max(1, min($max, (int)($e['level'] ?? $max)))], is_array($team) ? $team : []),
        'bot' => in_array((string)$r['bot_level'], DUEL_BOT_LEVELS, true) ? (string)$r['bot_level'] : 'difficile',
        'rules' => [
            'rarities' => array_values(array_map('strval', (array)($rules['rarities'] ?? []))),
            'cost_max' => max(0, (int)($rules['cost_max'] ?? 0)),
            'level' => max(1, min($max, (int)($rules['level'] ?? $max))),
        ],
        'goal' => max(0, (int)$r['goal_turns']),
        'reward' => max(0, (int)$r['reward']),
        'auto' => (int)$r['auto'] === 1,
    ];
}

/**
 * Lo scenario di un giorno. $create: se il giorno non ne ha uno, quello
 * automatico si salva (alla prima richiesta di oggi), cosi' resta lo stesso
 * per tutto il giorno anche se intanto cambiano i personaggi.
 */
function duel_daily_get(mysqli $m, string $day, bool $create = false): ?array
{
    $r = duel_row($m, 'SELECT * FROM game_daily WHERE day = ?', 's', [$day]);
    if (!$r && $create) {
        $sc = duel_daily_auto($day, duel_db_all_characters($m));
        duel_exec($m, 'INSERT IGNORE INTO game_daily (day, title_it, title_en, team_json, bot_level, rules_json, goal_turns, reward, auto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)',
            'ssssssii', [$day, $sc['title']['it'], $sc['title']['en'], json_encode($sc['team']), $sc['bot'], json_encode($sc['rules']), $sc['goal'], $sc['reward']]);
        $r = duel_row($m, 'SELECT * FROM game_daily WHERE day = ?', 's', [$day]);
    }
    return $r ? duel_daily_from_row($r) : null;
}

/** La squadra nemica come carte (per la lobby, il foglio e il pannello). */
function duel_daily_enemy(mysqli $m, array $sc): array
{
    $chars = duel_db_characters($m, array_column($sc['team'], 'id'));
    $out = [];
    foreach ($sc['team'] as $e) {
        $c = $chars[$e['id']] ?? null;
        if (!$c) {
            continue;
        }
        $kit = duel_character_kit($c, $e['level']);
        $out[] = ['id' => $e['id'], 'name' => (string)$c['nome'], 'img' => duel_media($c['img_url'] ?? ''), 'rarity' => $kit['rarity'],
            'role' => $kit['role'], 'level' => $e['level'], 'cost' => duel_rarity($kit['rarity'])['cost']];
    }
    return $out;
}

/**
 * La classifica di un giorno: prima chi ha centrato l'obiettivo, poi chi ha
 * vinto in meno turni, poi chi c'e' arrivato prima; poi chi ha solo provato.
 */
function duel_daily_board(mysqli $m, int $dailyId, int $limit = 10): array
{
    return array_map(static fn(array $r): array => [
        'id' => (int)$r['user_id'], 'username' => (string)($r['username'] ?? '?'), 'best' => $r['best_turns'] !== null ? (int)$r['best_turns'] : null,
        'completed' => (int)$r['completed'] === 1, 'attempts' => (int)$r['attempts'],
    ], duel_rows($m, 'SELECT r.user_id, u.username, r.best_turns, r.completed, r.attempts FROM game_daily_results r LEFT JOIN utenti u ON u.id = r.user_id
        WHERE r.daily_id = ? ORDER BY r.completed DESC, r.best_turns IS NULL, r.best_turns ASC, COALESCE(r.best_at, r.updated_at) ASC LIMIT ' . max(1, min(100, $limit)), 'i', [$dailyId]));
}

/** Il tuo risultato di un giorno e la tua posizione. */
function duel_daily_me(mysqli $m, int $dailyId, int $userId): ?array
{
    $r = duel_row($m, 'SELECT * FROM game_daily_results WHERE daily_id = ? AND user_id = ?', 'ii', [$dailyId, $userId]);
    if (!$r) {
        return null;
    }
    $pos = null;
    if ($r['best_turns'] !== null) {
        $at = (string)($r['best_at'] ?? $r['updated_at']);
        $pos = 1 + (int)(duel_row($m, 'SELECT COUNT(*) AS n FROM game_daily_results WHERE daily_id = ? AND best_turns IS NOT NULL AND (completed > ?
            OR (completed = ? AND (best_turns < ? OR (best_turns = ? AND COALESCE(best_at, updated_at) < ?))))', 'iiiiis',
            [$dailyId, (int)$r['completed'], (int)$r['completed'], (int)$r['best_turns'], (int)$r['best_turns'], $at])['n'] ?? 0);
    }
    return ['attempts' => (int)$r['attempts'], 'best' => $r['best_turns'] !== null ? (int)$r['best_turns'] : null,
        'completed' => (int)$r['completed'] === 1, 'pos' => $pos];
}

/** Quello che serve alla lobby: lo scenario di oggi, il tuo risultato, la classifica. */
function duel_daily_view(mysqli $m, int $userId, string $lang): ?array
{
    if (!duel_daily_ready($m)) {
        return null;
    }
    $sc = duel_daily_get($m, duel_daily_today(), true);
    if (!$sc) {
        return null;
    }
    $lang = $lang === 'en' ? 'en' : 'it';
    return [
        'id' => $sc['id'], 'day' => $sc['day'], 'title' => $sc['title'][$lang],
        'rules' => $sc['rules'], 'rules_text' => duel_daily_rules_text($sc, $lang), 'goal' => $sc['goal'], 'goal_text' => duel_daily_goal_text($sc, $lang),
        'reward' => $sc['reward'], 'bot' => $sc['bot'], 'enemy' => duel_daily_enemy($m, $sc),
        'me' => duel_daily_me($m, $sc['id'], $userId),
        'players' => (int)(duel_row($m, 'SELECT COUNT(*) AS n FROM game_daily_results WHERE daily_id = ?', 'i', [$sc['id']])['n'] ?? 0),
        'board' => duel_daily_board($m, $sc['id'], 10),
    ];
}

// ─────────────────────────────────────────────────────────────────────────
// Giocarla
// ─────────────────────────────────────────────────────────────────────────

/** Una partita della sfida di oggi, contro il bot dello scenario. */
function duel_daily_start(mysqli $m, int $userId, array $pids): array
{
    if (!duel_daily_ready($m)) {
        throw duel_user_error('La sfida del giorno non è ancora pronta.', 'The daily challenge is not ready yet.', 503, 'DAILY_OFF');
    }
    $sc = duel_daily_get($m, duel_daily_today(), true);
    if (!$sc || count($sc['team']) !== 3) {
        throw duel_user_error('Oggi non c\'è una sfida.', 'There is no challenge today.', 404, 'DAILY_NONE');
    }
    duel_require_free($m, $userId);
    $pids = array_values(array_map('intval', $pids));
    $owned = duel_db_owned($m, $userId, $pids);
    foreach ($pids as $pid) {
        if (!isset($owned[$pid])) {
            throw duel_user_error('Nella squadra c\'è un personaggio che non hai.', 'Your team has a character you don\'t own.', 400, 'TEAM_NOT_OWNED');
        }
    }
    // Il livello della sfida, uguale per tutti.
    $team = duel_team_build($m, $pids, array_fill_keys($pids, $sc['rules']['level']), false);
    $error = duel_daily_team_error($sc, array_column($team['kits'], 'rarity'));
    if ($error) {
        throw duel_user_error($error[0], $error[1], 400, 'DAILY_TEAM');
    }
    $enemy = duel_team_build($m, array_column($sc['team'], 'id'), array_column($sc['team'], 'level', 'id'), false);
    $id = duel_tx($m, static fn() => duel_match_start($m, [
        'mode' => 'bot', 'p1' => $userId, 'p2' => 0, 'team1' => $team, 'team2' => $enemy,
        'levels' => 'daily', 'bot_level' => $sc['bot'],
        'daily' => ['id' => $sc['id'], 'day' => $sc['day'], 'goal' => $sc['goal'], 'title' => $sc['title']],
    ]));
    return ['match' => $id];
}

/**
 * A fine partita (duel_finish): il tentativo, il risultato migliore e, la
 * prima volta che si centra l'obiettivo, il premio. Dentro la transazione
 * della mossa che ha chiuso la partita.
 */
function duel_daily_record(mysqli $m, array $row, array $st, array $daily): ?array
{
    if (!duel_daily_ready($m)) {
        return null;
    }
    $userId = (int)$row['player1_id'];
    $dailyId = (int)($daily['id'] ?? 0);
    $sc = duel_row($m, 'SELECT * FROM game_daily WHERE id = ?', 'i', [$dailyId]);
    if ($userId <= 0 || !$sc) {
        return null;
    }
    $sc = duel_daily_from_row($sc);
    $won = $st['winner'] === 0;
    $turns = (int)$st['turn'];
    $completed = $won && ($sc['goal'] <= 0 || $turns <= $sc['goal']);
    $old = duel_row($m, 'SELECT * FROM game_daily_results WHERE daily_id = ? AND user_id = ? FOR UPDATE', 'ii', [$dailyId, $userId]);
    $better = $won && (!$old || $old['best_turns'] === null || ($completed && (int)$old['completed'] === 0)
        || ((int)$old['completed'] === (int)$completed && $turns < (int)$old['best_turns']));
    if (!$old) {
        duel_exec($m, 'INSERT INTO game_daily_results (daily_id, user_id, attempts, best_turns, completed, best_at, best_match_id, completed_at)
            VALUES (?, ?, 1, ?, ?, ?, ?, ?)', 'iiiisis', [$dailyId, $userId, $won ? $turns : null, $completed ? 1 : 0, $won ? date('Y-m-d H:i:s') : null,
            $won ? (int)$row['id'] : null, $completed ? date('Y-m-d H:i:s') : null]);
    } else {
        duel_exec($m, 'UPDATE game_daily_results SET attempts = attempts + 1 WHERE daily_id = ? AND user_id = ?', 'ii', [$dailyId, $userId]);
        if ($better) {
            duel_exec($m, 'UPDATE game_daily_results SET best_turns = ?, completed = GREATEST(completed, ?), best_at = NOW(), best_match_id = ?,
                completed_at = COALESCE(completed_at, IF(? = 1, NOW(), NULL)) WHERE daily_id = ? AND user_id = ?', 'iiiiii',
                [$turns, $completed ? 1 : 0, (int)$row['id'], $completed ? 1 : 0, $dailyId, $userId]);
        }
    }
    $paid = $completed && $sc['reward'] > 0 && duel_reward_grant($m, $userId, 'daily_ch', $sc['day'], $sc['reward'], (int)$row['id']);
    return ['won' => $won, 'turns' => $turns, 'completed' => $completed, 'better' => $better || !$old && $won, 'paid' => $paid];
}

/** Per la fine partita: com'e' andata la sfida del giorno. */
function duel_daily_summary(mysqli $m, array $row, string $lang): ?array
{
    $daily = duel_match_rules($row)['daily'] ?? null;
    if (!$daily || !duel_daily_ready($m)) {
        return null;
    }
    $st = duel_match_state($row);
    $sc = duel_row($m, 'SELECT * FROM game_daily WHERE id = ?', 'i', [(int)$daily['id']]);
    if (!$sc) {
        return null;
    }
    $sc = duel_daily_from_row($sc);
    $won = ($st['winner'] ?? null) === 0;
    $turns = (int)($st['turn'] ?? 0);
    return [
        'title' => $sc['title'][$lang === 'en' ? 'en' : 'it'], 'goal' => $sc['goal'], 'goal_text' => duel_daily_goal_text($sc, $lang),
        'won' => $won, 'turns' => $turns, 'completed' => $won && ($sc['goal'] <= 0 || $turns <= $sc['goal']),
        'today' => $sc['day'] === duel_daily_today(), 'me' => duel_daily_me($m, $sc['id'], (int)$row['player1_id']),
    ];
}

// ─────────────────────────────────────────────────────────────────────────
// Pannello (Duelli › Sfida del giorno)
// ─────────────────────────────────────────────────────────────────────────

/** I giorni da 7 fa a fra 13: scenario (salvato o quello automatico che verrebbe), giocatori, chi l'ha centrata. */
function duel_daily_calendar(mysqli $m, string $from, int $days): array
{
    $stored = [];
    foreach (duel_rows($m, 'SELECT d.*, COUNT(r.user_id) AS players, COALESCE(SUM(r.completed), 0) AS done FROM game_daily d
        LEFT JOIN game_daily_results r ON r.daily_id = d.id WHERE d.day BETWEEN ? AND ? GROUP BY d.id', 'ss',
        [$from, date('Y-m-d', strtotime($from . ' +' . ($days - 1) . ' days'))]) as $r) {
        $stored[(string)$r['day']] = $r;
    }
    $chars = duel_db_all_characters($m);
    $today = duel_daily_today();
    $out = [];
    for ($i = 0; $i < $days; $i++) {
        $day = date('Y-m-d', strtotime($from . ' +' . $i . ' days'));
        $r = $stored[$day] ?? null;
        $sc = $r ? duel_daily_from_row($r) : duel_daily_auto($day, $chars);
        $out[] = [
            'day' => $day, 'when' => $day < $today ? 'past' : ($day === $today ? 'today' : 'future'),
            'saved' => $r !== null, 'auto' => $sc['auto'], 'title' => $sc['title'], 'team' => $sc['team'], 'enemy' => duel_daily_enemy($m, $sc),
            'bot' => $sc['bot'], 'rules' => $sc['rules'], 'rules_text' => duel_daily_rules_text($sc, 'it'), 'goal' => $sc['goal'], 'reward' => $sc['reward'],
            'players' => (int)($r['players'] ?? 0), 'done' => (int)($r['done'] ?? 0), 'id' => $r ? (int)$r['id'] : null,
        ];
    }
    return $out;
}

/** Uno scenario scritto nel pannello: controllato, salvato per il suo giorno. Torna il giorno. */
function duel_daily_save(mysqli $m, int $adminId, array $in): array
{
    $day = (string)($in['day'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || strtotime($day) === false) {
        throw duel_user_error('Giorno non valido.', 'Invalid day.', 422, 'INVALID');
    }
    if ($day < duel_daily_today()) {
        throw duel_user_error('I giorni passati non si cambiano.', 'Past days cannot be changed.', 422, 'INVALID');
    }
    $old = duel_row($m, 'SELECT d.id, (SELECT COUNT(*) FROM game_daily_results r WHERE r.daily_id = d.id) AS players FROM game_daily d WHERE d.day = ?', 's', [$day]);
    if ($old && (int)$old['players'] > 0) {
        throw duel_user_error('Qualcuno l\'ha già giocata: oggi non si cambia più.', 'Someone already played it.', 409, 'PLAYED');
    }
    $max = duel_rules()['level_max'];
    $ids = array_values(array_map('intval', (array)($in['team'] ?? [])));
    $levels = (array)($in['levels'] ?? []);
    $chars = duel_db_characters($m, $ids);
    if (count($ids) !== 3 || count(array_unique($ids)) !== 3 || count($chars) !== 3) {
        throw duel_user_error('La squadra nemica sono tre personaggi diversi.', 'The enemy team is three different characters.', 422, 'INVALID');
    }
    $team = [];
    foreach ($ids as $i => $id) {
        $team[] = ['id' => $id, 'level' => max(1, min($max, (int)($levels[$i] ?? $max)))];
    }
    $bot = (string)($in['bot'] ?? 'difficile');
    if (!in_array($bot, DUEL_BOT_LEVELS, true)) {
        throw duel_user_error('Livello del bot non valido.', 'Invalid bot level.', 422, 'INVALID');
    }
    $rarities = array_values(array_intersect(array_keys(duel_rarities()), array_map('strval', (array)($in['rarities'] ?? []))));
    $rules = ['rarities' => count($rarities) === count(duel_rarities()) ? [] : $rarities, 'cost_max' => max(0, min(30, (int)($in['cost_max'] ?? 0))),
        'level' => max(1, min($max, (int)($in['level'] ?? $max)))];
    $titleIt = trim((string)($in['title_it'] ?? ''));
    $titleEn = trim((string)($in['title_en'] ?? ''));
    if ($titleIt === '' || mb_strlen($titleIt) > 90 || mb_strlen($titleEn) > 90) {
        throw duel_user_error('Il titolo va da 1 a 90 caratteri.', 'The title is 1 to 90 characters.', 422, 'INVALID');
    }
    $goal = max(0, min(200, (int)($in['goal'] ?? 0)));
    $reward = max(0, min(5000, (int)($in['reward'] ?? 0)));
    // Una squadra che rispetta le regole deve esistere (almeno tre personaggi ammessi).
    $allowed = array_filter(duel_db_all_characters($m), static fn($c) => !$rules['rarities']
        || in_array(duel_rarity_key((string)$c['rarita'], !empty($c['limitato'])), $rules['rarities'], true));
    if (count($allowed) < 3) {
        throw duel_user_error('Con queste rarità non si fa una squadra.', 'No team is possible with these rarities.', 422, 'INVALID');
    }
    duel_exec($m, 'INSERT INTO game_daily (day, title_it, title_en, team_json, bot_level, rules_json, goal_turns, reward, auto, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?) ON DUPLICATE KEY UPDATE title_it = VALUES(title_it), title_en = VALUES(title_en), team_json = VALUES(team_json),
        bot_level = VALUES(bot_level), rules_json = VALUES(rules_json), goal_turns = VALUES(goal_turns), reward = VALUES(reward), auto = 0, created_by = VALUES(created_by)',
        'ssssssiii', [$day, $titleIt, $titleEn !== '' ? $titleEn : $titleIt, json_encode($team), $bot, json_encode($rules), $goal, $reward, $adminId]);
    return ['day' => $day, 'title' => $titleIt];
}

/** Toglie lo scenario scritto: il giorno torna a quello automatico. */
function duel_daily_delete(mysqli $m, string $day): bool
{
    $old = duel_row($m, 'SELECT d.id, (SELECT COUNT(*) FROM game_daily_results r WHERE r.daily_id = d.id) AS players FROM game_daily d WHERE d.day = ?', 's', [$day]);
    if (!$old) {
        return false;
    }
    if ((int)$old['players'] > 0 || $day < duel_daily_today()) {
        throw duel_user_error('Qualcuno l\'ha già giocata: non si toglie più.', 'Someone already played it.', 409, 'PLAYED');
    }
    return duel_exec($m, 'DELETE FROM game_daily WHERE id = ?', 'i', [(int)$old['id']]) === 1;
}
