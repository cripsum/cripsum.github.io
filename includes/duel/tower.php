<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — la Torre (piano proposte-home/duelli2-torre-piano.md).
 *
 * Cento piani uno sopra l'altro, contro il bot: si sale battendo il piano in
 * cui sei, i piani fatti si rigiocano senza premio. La tua squadra gioca coi
 * tuoi livelli veri e senza limite di costo (tranne i piani con una regola).
 *
 * - Dieci tratti da dieci piani; l'ultimo di ogni tratto e' un boss, quasi
 *   sempre un legame del sito. Un piano ogni cinque ha una regola (terreno,
 *   rarita' ammesse, costo, ruoli vietati, vittoria entro N turni).
 * - I nemici possono avere un potenziamento: gli stessi bonus dei legami,
 *   dati a tutta la squadra (motore: opts.boons).
 * - I piani vivono in game_tower_floors: il generatore li scrive la prima
 *   volta che servono e poi non li tocca piu' (un personaggio nuovo non cambia
 *   il piano su cui sei bloccato). Il pannello li cambia uno per uno.
 * - Premi: Godos la prima volta (game_rewards, mai due volte); ai boss un
 *   potenziamento gratuito, fino a una rarita' che sale coi piani.
 */

require_once __DIR__ . '/lobby.php';
require_once __DIR__ . '/text.php';

const DUEL_TOWER_FLOORS = 100;

/**
 * I dieci tratti: nome di partenza (si cambia dal pannello), rarita' dei
 * nemici, livello, bot e potenziamento (HP in %) da inizio a fine tratto.
 */
const DUEL_TOWER_SECTORS = [
    1 => ['it' => 'Il cortile', 'en' => 'The courtyard', 'rar' => ['comune'], 'lv' => [1, 2], 'bot' => ['facile', 'facile'], 'boon' => [0, 0]],
    2 => ['it' => 'Le scale', 'en' => 'The stairs', 'rar' => ['comune', 'raro'], 'lv' => [2, 3], 'bot' => ['facile', 'normale'], 'boon' => [0, 0]],
    3 => ['it' => 'Il salone', 'en' => 'The great hall', 'rar' => ['raro', 'epico'], 'lv' => [3, 3], 'bot' => ['normale', 'normale'], 'boon' => [0, 0]],
    4 => ['it' => 'La biblioteca', 'en' => 'The library', 'rar' => ['epico'], 'lv' => [3, 4], 'bot' => ['normale', 'normale'], 'boon' => [0, 5]],
    5 => ['it' => 'La sala d\'armi', 'en' => 'The armoury', 'rar' => ['epico', 'leggendario'], 'lv' => [4, 5], 'bot' => ['normale', 'difficile'], 'boon' => [5, 5]],
    6 => ['it' => 'I bastioni', 'en' => 'The ramparts', 'rar' => ['leggendario', 'speciale'], 'lv' => [5, 5], 'bot' => ['difficile', 'difficile'], 'boon' => [5, 10]],
    7 => ['it' => 'La torre di guardia', 'en' => 'The watchtower', 'rar' => ['speciale', 'segreto'], 'lv' => [5, 6], 'bot' => ['normale', 'difficile'], 'boon' => [0, 0]],
    8 => ['it' => 'Le nubi', 'en' => 'The clouds', 'rar' => ['segreto'], 'lv' => [6, 6], 'bot' => ['difficile', 'difficile'], 'boon' => [0, 5]],
    9 => ['it' => 'La tempesta', 'en' => 'The storm', 'rar' => ['segreto', 'segreto_limitato'], 'lv' => [6, 6], 'bot' => ['difficile', 'difficile'], 'boon' => [0, 5]],
    10 => ['it' => 'La cima', 'en' => 'The summit', 'rar' => ['segreto_limitato', 'theone'], 'lv' => [6, 6], 'bot' => ['difficile', 'incubo'], 'boon' => [5, 10]],
];

/**
 * I boss: [titolo it, titolo en, [id...], livello, bot, potenziamento].
 * Quasi tutti un legame del sito, cosi' il legame scatta anche per loro.
 */
const DUEL_TOWER_BOSSES = [
    10 => ['Il meme', 'The meme', [14, 36, 21], 2, 'normale', []],
    20 => ['Le sorelle Nakano', 'The Nakano sisters', [55, 54, 53], 2, 'normale', []],
    30 => ['Kessoku Band', 'Kessoku Band', [196, 198, 199], 3, 'normale', []],
    40 => ['Gli artisti', 'The artists', [92, 107, 132], 4, 'normale', []],
    50 => ['Amici di Cripsum', 'Cripsum\'s friends', [5, 6, 18], 5, 'difficile', ['hp' => 10, 'atk' => 5]],
    60 => ['Poppy', 'Poppy', [33, 34, 214], 5, 'difficile', ['hp' => 10, 'atk' => 5]],
    70 => ['La corte di Cripsum', 'Cripsum\'s court', [4, 46, 139], 6, 'difficile', ['hp' => 10, 'atk' => 5]],
    80 => ['Devil May Cry', 'Devil May Cry', [141, 142, 87], 6, 'difficile', ['hp' => 5]],
    90 => ['Principesse cosmiche', 'Cosmic princesses', [138, 139, 48], 6, 'incubo', ['hp' => 10, 'atk' => 5]],
    // Tarato con strumenti/duelli2/torre-cima.php: le squadre migliori di oggi lo vincono circa una volta su dieci.
    100 => ['La cima', 'The summit', [48, 48, 48], 6, 'incubo', ['hp' => 15, 'atk' => 10, 'def' => 5]],
];

/**
 * Le regole dei piani 5, 15, 25...: terreno da subito (terrain), rarita'
 * ammesse nella tua squadra (rarities), costo massimo (cost_max), ruoli
 * vietati (no_roles), vittoria entro N turni (turns). I nemici pronti
 * (carica o PS in piu') stanno nel potenziamento del piano.
 */
const DUEL_TOWER_RULES = [
    5 => ['terrain' => 'santuario'],
    15 => ['rarities' => ['comune', 'raro']],
    25 => ['terrain' => 'notte'],
    35 => ['turns' => 50],
    45 => ['terrain' => 'notte', 'no_roles' => ['Healer']],
    55 => ['terrain' => 'tempesta'],
    65 => ['cost_max' => 14],
    75 => ['terrain' => 'palude'],
    85 => ['no_roles' => ['Tank']],
    95 => ['terrain' => 'cielo_stellato', 'cost_max' => 16],
];

/** Piani con i nemici pronti (nel potenziamento): carica dell'ultimate o PS in piu'. */
const DUEL_TOWER_READY = [65 => ['charge' => 50], 95 => ['ps' => 1]];

/** Il potenziamento gratuito dei boss: la rarita' piu' alta che si puo' potenziare. */
const DUEL_TOWER_VOUCHERS = [10 => 'comune', 20 => 'raro', 30 => 'epico', 40 => 'epico', 50 => 'leggendario', 60 => 'leggendario',
    70 => 'speciale', 80 => 'speciale', 90 => 'segreto', 100 => 'theone'];

const DUEL_TOWER_ATTACK = ['DPS', 'Burst DPS', 'Sub DPS', 'Bruiser'];
const DUEL_TOWER_GUARD = ['Tank', 'Support', 'Healer', 'Buffer'];

/** La migrazione della Torre c'e'. */
function duel_tower_ready(mysqli $m): bool
{
    return rt_has_table($m, 'game_tower_floors') && rt_has_table($m, 'game_tower_progress')
        && rt_has_table($m, 'game_tower_clears') && rt_has_table($m, 'game_tower_vouchers');
}

/** Accesa per tutti, o solo per lo staff finche' l'interruttore e' spento. */
function duel_tower_open_for(mysqli $m): bool
{
    return (duel_settings($m)['tower'] ?? '0') === '1' || duel_is_staff();
}

function duel_tower_sector_of(int $floor): int
{
    return intdiv(max(1, $floor) - 1, 10) + 1;
}

/** I Godos della prima volta, dai valori del pannello (game_settings). */
function duel_tower_reward_default(mysqli $m, int $floor): int
{
    $s = duel_settings($m);
    $base = (int)($s['tower_godos_base'] ?? 20);
    $step = (int)($s['tower_godos_step'] ?? 2);
    $boss = (int)($s['tower_godos_boss'] ?? 5);
    return max(0, $base + $step * $floor + ($floor % 10 === 0 ? $boss * $floor : 0));
}

// ─────────────────────────────────────────────────────────────────────────
// Il generatore (senza database: lo usa anche il simulatore)
// ─────────────────────────────────────────────────────────────────────────

/**
 * Tutti i cento piani dai personaggi di oggi. $chars: [id => riga di
 * personaggi]. Sempre uguale con gli stessi personaggi: il dado parte dal
 * numero del piano. $rewards: [piano => Godos] (senza: 20 + 2 × piano...).
 */
function duel_tower_generate(array $chars, array $rewards = []): array
{
    $pool = [];
    foreach ($chars as $id => $c) {
        $rarity = duel_rarity_key((string)($c['rarita'] ?? $c['rarità'] ?? ''), !empty($c['limitato']));
        $pool[(int)$id] = ['id' => (int)$id, 'role' => duel_role_key((string)($c['ruolo'] ?? '')), 'rarity' => $rarity, 'img' => trim((string)($c['img_url'] ?? '')) !== ''];
    }
    $floors = [];
    $recent = [];                         // i nemici degli ultimi tre piani
    for ($f = 1; $f <= DUEL_TOWER_FLOORS; $f++) {
        $s = duel_tower_sector_of($f);
        $sec = DUEL_TOWER_SECTORS[$s];
        $i = ($f - 1) % 10;                  // 0..9 dentro il tratto
        $t = $i / 9;
        $lv = (int)round($sec['lv'][0] + ($sec['lv'][1] - $sec['lv'][0]) * $t);
        $bot = $i < 5 ? $sec['bot'][0] : $sec['bot'][1];
        $hp = (int)round($sec['boon'][0] + ($sec['boon'][1] - $sec['boon'][0]) * $t);
        $boon = $hp > 0 ? ['hp' => $hp, 'atk' => (int)round($hp * 0.6), 'def' => (int)round($hp * 0.4)] : [];
        $boon = array_filter($boon);
        $boss = DUEL_TOWER_BOSSES[$f] ?? null;
        if ($boss) {
            [$it, $en, $ids, $blv, $bbot, $bboon] = $boss;
            $team = array_map(static fn($id) => ['id' => (int)$id, 'level' => $blv], array_values(array_filter($ids, static fn($id) => isset($pool[$id]))));
            if (count($team) === 3) {
                $floors[$f] = duel_tower_floor_make($f, $team, $bbot, $bboon, ['it' => $it, 'en' => $en], true, $rewards);
                $recent = array_slice([...$recent, array_column($team, 'id')], -3);
                continue;
            }
            // Un boss con un personaggio che non c'e' piu' diventa un piano normale.
        }
        $team = duel_tower_pick($pool, $sec['rar'], $f, $recent);
        $recent = array_slice([...$recent, array_column($team, 'id')], -3);
        $floors[$f] = duel_tower_floor_make($f, array_map(static fn($p) => ['id' => $p['id'], 'level' => $lv], $team), $bot,
            $boon + (DUEL_TOWER_READY[$f] ?? []), null, false, $rewards);
    }
    return $floors;
}

/**
 * Tre personaggi delle rarita' del tratto, diversi tra loro, con almeno un
 * attaccante e uno che difende o cura. Diversi dai tre piani prima; se i
 * personaggi di quella rarita' sono pochi, almeno dal piano prima. Prima
 * quelli con la foto, se sono abbastanza da non ripetersi. $recent: gli id
 * dei piani prima, il piu' recente in fondo.
 */
function duel_tower_pick(array $pool, array $rarities, int $floor, array $recent): array
{
    $rnd = new Random\Randomizer(new Random\Engine\Xoshiro256StarStar(crc32('cripsum-tower:' . $floor)));
    $of = static fn(array $avoid) => array_values(array_filter($pool, static fn($p) => in_array($p['rarity'], $rarities, true) && !in_array($p['id'], $avoid, true)));
    $list = $of($recent ? array_merge(...$recent) : []);
    $withImg = array_values(array_filter($list, static fn($p) => $p['img']));
    $tries = [count($withImg) >= 9 ? $withImg : [], $list, $of($recent ? end($recent) : []), $of([])];
    foreach ($tries as $cand) {
        for ($try = 0; $try < 600 && count($cand) >= 3; $try++) {
            $pick = array_map(static fn($k) => $cand[$k], $rnd->pickArrayKeys($cand, 3));
            $roles = array_column($pick, 'role');
            if (array_intersect($roles, DUEL_TOWER_ATTACK) && array_intersect($roles, DUEL_TOWER_GUARD)) {
                return $pick;
            }
        }
    }
    return array_slice($list ?: array_values($pool), 0, 3);
}

/** Un piano come riga (prima del database). */
function duel_tower_floor_make(int $f, array $team, string $bot, array $boon, ?array $title, bool $boss, array $rewards): array
{
    return [
        'floor' => $f,
        'sector' => duel_tower_sector_of($f),
        'title' => $title,
        'team' => $team,
        'bot' => $bot,
        'boon' => $boon,
        'rule' => DUEL_TOWER_RULES[$f] ?? [],
        'reward' => $rewards[$f] ?? (20 + 2 * $f + ($f % 10 === 0 ? 5 * $f : 0)),
        'voucher' => DUEL_TOWER_VOUCHERS[$f] ?? null,
        'vouchers' => $f === DUEL_TOWER_FLOORS ? 2 : (isset(DUEL_TOWER_VOUCHERS[$f]) ? 1 : 0),
        'boss' => $boss,
        'auto' => true,
    ];
}

// ─────────────────────────────────────────────────────────────────────────
// I piani nel database
// ─────────────────────────────────────────────────────────────────────────

/** Una riga di game_tower_floors come piano. */
function duel_tower_from_row(array $r): array
{
    $team = json_decode((string)$r['team_json'], true);
    $boon = json_decode((string)($r['boon_json'] ?? ''), true);
    $rule = json_decode((string)($r['rule_json'] ?? ''), true);
    $max = duel_rules()['level_max'];
    $it = trim((string)($r['title_it'] ?? ''));
    return [
        'floor' => (int)$r['floor'],
        'sector' => (int)$r['sector'],
        'title' => $it !== '' ? ['it' => $it, 'en' => trim((string)($r['title_en'] ?? '')) ?: $it] : null,
        'team' => array_map(static fn($e) => ['id' => (int)($e['id'] ?? 0), 'level' => max(1, min($max, (int)($e['level'] ?? 1)))], is_array($team) ? $team : []),
        'bot' => in_array((string)$r['bot_level'], DUEL_BOT_LEVELS, true) ? (string)$r['bot_level'] : 'normale',
        'boon' => is_array($boon) ? array_filter($boon, static fn($v) => is_numeric($v) && (float)$v != 0) : [],
        'rule' => is_array($rule) ? $rule : [],
        'reward' => max(0, (int)$r['reward']),
        'voucher' => ($r['voucher_rarity'] ?? null) ? (string)$r['voucher_rarity'] : null,
        'vouchers' => max(0, (int)($r['vouchers'] ?? 0)),
        'boss' => (int)$r['boss'] === 1,
        'auto' => (int)$r['auto'] === 1,
    ];
}

/**
 * Tutti i piani. La prima volta (o se ne manca qualcuno) il generatore
 * scrive quelli che mancano, sotto un blocco con nome; poi non li tocca piu'.
 */
function duel_tower_floors(mysqli $m, bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $rows = duel_rows($m, 'SELECT * FROM game_tower_floors ORDER BY floor');
    if (count($rows) < DUEL_TOWER_FLOORS) {
        $got = duel_row($m, "SELECT GET_LOCK('duel_tower_floors', 5) AS ok");
        try {
            $have = array_flip(array_map(static fn($r) => (int)$r['floor'], duel_rows($m, 'SELECT floor FROM game_tower_floors')));
            $rewards = [];
            for ($f = 1; $f <= DUEL_TOWER_FLOORS; $f++) {
                $rewards[$f] = duel_tower_reward_default($m, $f);
            }
            foreach (duel_tower_generate(duel_db_all_characters($m), $rewards) as $f => $x) {
                if (!isset($have[$f])) {
                    duel_tower_floor_write($m, $x, null, true);
                }
            }
        } finally {
            if ((int)($got['ok'] ?? 0) === 1) {
                duel_row($m, "SELECT RELEASE_LOCK('duel_tower_floors') AS ok");
            }
        }
        $rows = duel_rows($m, 'SELECT * FROM game_tower_floors ORDER BY floor');
    }
    $out = [];
    foreach ($rows as $r) {
        $out[(int)$r['floor']] = duel_tower_from_row($r);
    }
    return $cache = $out;
}

/** Scrive un piano (generato o dal pannello). $ignore: solo se manca. */
function duel_tower_floor_write(mysqli $m, array $x, ?int $by, bool $ignore = false): void
{
    $sql = ($ignore ? 'INSERT IGNORE' : 'INSERT') . ' INTO game_tower_floors (floor, sector, title_it, title_en, team_json, bot_level, boon_json, rule_json,
        reward, voucher_rarity, vouchers, boss, auto, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    if (!$ignore) {
        $sql .= ' ON DUPLICATE KEY UPDATE sector = VALUES(sector), title_it = VALUES(title_it), title_en = VALUES(title_en), team_json = VALUES(team_json),
            bot_level = VALUES(bot_level), boon_json = VALUES(boon_json), rule_json = VALUES(rule_json), reward = VALUES(reward),
            voucher_rarity = VALUES(voucher_rarity), vouchers = VALUES(vouchers), boss = VALUES(boss), auto = VALUES(auto), updated_by = VALUES(updated_by)';
    }
    duel_exec($m, $sql, 'iissssssisiiii', [
        $x['floor'], $x['sector'], $x['title']['it'] ?? '', $x['title']['en'] ?? '', json_encode(array_values($x['team'])), $x['bot'],
        json_encode((object)$x['boon']), json_encode((object)$x['rule']), $x['reward'], $x['voucher'], $x['vouchers'], $x['boss'] ? 1 : 0,
        $x['auto'] ? 1 : 0, $by,
    ]);
}

/** I nomi dei tratti: quelli del pannello, altrimenti quelli di partenza. */
function duel_tower_sectors(mysqli $m): array
{
    $out = [];
    foreach (DUEL_TOWER_SECTORS as $n => $s) {
        $out[$n] = ['it' => $s['it'], 'en' => $s['en']];
    }
    foreach (duel_rows($m, 'SELECT n, name_it, name_en FROM game_tower_sectors') as $r) {
        $n = (int)$r['n'];
        if (isset($out[$n]) && trim((string)$r['name_it']) !== '') {
            $out[$n] = ['it' => (string)$r['name_it'], 'en' => trim((string)$r['name_en']) ?: (string)$r['name_it']];
        }
    }
    return $out;
}

// ─────────────────────────────────────────────────────────────────────────
// Squadre, regole, potenza
// ─────────────────────────────────────────────────────────────────────────

/**
 * La squadra nemica di un piano pronta per il motore. A differenza delle
 * squadre dei giocatori, lo stesso personaggio puo' esserci piu' volte
 * (il piano 100: tre Shorekeeper).
 */
function duel_tower_enemy(mysqli $m, array $floor): array
{
    duel_use_current_values($m);
    $chars = duel_db_characters($m, array_column($floor['team'], 'id'));
    $kits = [];
    $rarities = [];
    $pids = [];
    foreach ($floor['team'] as $e) {
        $c = $chars[$e['id']] ?? null;
        if (!$c) {
            throw duel_user_error('Questo piano ha un personaggio che non esiste più: lo sistema lo staff.', 'This floor has a character that no longer exists: staff will fix it.', 503, 'TOWER_BROKEN');
        }
        $kit = duel_character_kit($c, $e['level']);
        $kit['pid'] = $e['id'];
        $kit['level'] = $e['level'];
        $kits[] = $kit;
        $rarities[] = $kit['rarity'];
        $pids[] = $e['id'];
    }
    if (count($kits) !== 3) {
        throw duel_user_error('Questo piano è incompleto: lo sistema lo staff.', 'This floor is incomplete: staff will fix it.', 503, 'TOWER_BROKEN');
    }
    return ['kits' => $kits, 'cost' => duel_team_cost($rarities), 'pids' => $pids];
}

/**
 * Perche' una squadra non va bene per la regola del piano, o null.
 * $kits: le carte della tua squadra (rarity, role). Torna [it, en].
 */
function duel_tower_team_error(array $rule, array $kits): ?array
{
    $rarities = array_column($kits, 'rarity');
    if (!empty($rule['rarities'])) {
        require_once __DIR__ . '/daily.php';
        foreach ($rarities as $r) {
            if (!in_array($r, $rule['rarities'], true)) {
                return [duel_daily_rarity_text($rule['rarities'], 'it') . ': nella squadra c\'è un personaggio di un\'altra rarità.',
                    duel_daily_rarity_text($rule['rarities'], 'en') . ': your team has a character of another rarity.'];
            }
        }
    }
    $cost = duel_team_cost($rarities);
    if (($rule['cost_max'] ?? 0) > 0 && $cost > $rule['cost_max']) {
        return ["La squadra costa $cost: in questo piano il massimo è {$rule['cost_max']}.", "The team costs $cost: this floor allows {$rule['cost_max']} at most."];
    }
    foreach ((array)($rule['no_roles'] ?? []) as $role) {
        if (in_array($role, array_column($kits, 'role'), true)) {
            return ["Niente $role in questo piano.", "No $role on this floor."];
        }
    }
    return null;
}

/**
 * La potenza di una squadra: rarita' e livello di ogni carta, coi
 * potenziamenti (HP, ATK e DEF in %). Un numero da confrontare, non una
 * promessa: il foglio del piano dice «la tua» e «consigliata».
 */
function duel_tower_power(array $kits, array $boon = []): int
{
    $step = duel_rules()['level_step'];
    $total = 0.0;
    foreach ($kits as $k) {
        $p = duel_rarity((string)$k['rarity'])['power'] ?? 1.0;
        $total += 100 * $p * (1 + ((int)($k['level'] ?? 1) - 1) * $step / 100);
    }
    $mult = 1 + (($boon['hp'] ?? 0) + ($boon['atk'] ?? 0) + ($boon['def'] ?? 0)) / 300;
    return (int)round($total * $mult);
}

/** La potenza consigliata di un piano: quella dei nemici, un po' sopra. */
function duel_tower_power_recommended(array $enemyKits, array $boon): int
{
    return (int)round(duel_tower_power($enemyKits, $boon) * 1.05);
}

/** La regola a parole: «Notte da subito · niente Healer». */
function duel_tower_rule_text(array $rule, string $lang): string
{
    $en = $lang === 'en';
    $parts = [];
    if (!empty($rule['terrain'])) {
        $t = duel_terrains()[$rule['terrain']] ?? null;
        if ($t) {
            $parts[] = ($en ? $t['en'] . ' from the start' : $t['it'] . ' da subito');
        }
    }
    if (!empty($rule['rarities'])) {
        require_once __DIR__ . '/daily.php';
        $parts[] = duel_daily_rarity_text($rule['rarities'], $lang);
    }
    if (($rule['cost_max'] ?? 0) > 0) {
        $parts[] = $en ? 'team cost up to ' . $rule['cost_max'] : 'squadra fino a costo ' . $rule['cost_max'];
    }
    foreach ((array)($rule['no_roles'] ?? []) as $role) {
        $parts[] = ($en ? 'no ' : 'niente ') . $role;
    }
    if (($rule['turns'] ?? 0) > 0) {
        $parts[] = $en ? 'win within ' . $rule['turns'] . ' turns' : 'vinci entro ' . $rule['turns'] . ' turni';
    }
    $text = implode(' · ', $parts);
    return $text === '' ? '' : mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
}

// ─────────────────────────────────────────────────────────────────────────
// Giocare
// ─────────────────────────────────────────────────────────────────────────

/** Il piano piu' alto superato da un giocatore (0 = nessuno). */
function duel_tower_best(mysqli $m, int $userId): int
{
    return (int)(duel_row($m, 'SELECT best_floor FROM game_tower_progress WHERE user_id = ?', 'i', [$userId])['best_floor'] ?? 0);
}

/**
 * Una partita della Torre: il piano dopo l'ultimo superato, o uno gia'
 * superato (senza premio). La squadra coi tuoi livelli.
 */
function duel_tower_start(mysqli $m, int $userId, array $pids, int $floorNo): array
{
    if (!duel_tower_ready($m)) {
        throw duel_user_error('La Torre non è ancora pronta.', 'The Tower is not ready yet.', 503, 'TOWER_OFF');
    }
    if (!duel_tower_open_for($m)) {
        throw duel_user_error('La Torre apre presto.', 'The Tower opens soon.', 403, 'TOWER_CLOSED');
    }
    $best = duel_tower_best($m, $userId);
    if ($floorNo < 1 || $floorNo > DUEL_TOWER_FLOORS || $floorNo > $best + 1) {
        throw duel_user_error('Questo piano non è ancora aperto: prima batti quelli sotto.', 'This floor is not open yet: beat the ones below first.', 403, 'TOWER_LOCKED');
    }
    $floor = duel_tower_floors($m)[$floorNo] ?? null;
    if (!$floor) {
        throw duel_user_error('Piano non trovato.', 'Floor not found.', 404, 'TOWER_BROKEN');
    }
    duel_require_free($m, $userId);
    $team = duel_team_for_user($m, $userId, $pids, false);
    $error = duel_tower_team_error($floor['rule'], $team['kits']);
    if ($error) {
        throw duel_user_error($error[0], $error[1], 400, 'TOWER_TEAM');
    }
    $enemy = duel_tower_enemy($m, $floor);
    $engine = [];
    if ($floor['boon']) {
        $engine['boons'] = [1 => $floor['boon']];
    }
    if (!empty($floor['rule']['terrain'])) {
        $engine['terrain'] = ['k' => (string)$floor['rule']['terrain']];
    }
    $id = duel_tx($m, static fn() => duel_match_start($m, [
        'mode' => 'bot', 'p1' => $userId, 'p2' => 0, 'team1' => $team, 'team2' => $enemy,
        'levels' => 'own', 'bot_level' => $floor['bot'], 'engine' => $engine,
        'tower' => ['floor' => $floorNo, 'boss' => $floor['boss'], 'turns' => (int)($floor['rule']['turns'] ?? 0), 'replay' => $floorNo <= $best],
    ]));
    return ['match' => $id];
}

/**
 * A fine partita (duel_finish), dentro la sua transazione: il tentativo e,
 * se e' la prima volta che si supera il piano, il record, i Godos e il
 * potenziamento gratuito dei boss. Torna cosa e' successo (per la fine
 * partita, le missioni e gli achievement).
 */
function duel_tower_record(mysqli $m, array $row, array $st, array $tower): ?array
{
    if (!duel_tower_ready($m)) {
        return null;
    }
    $userId = (int)$row['player1_id'];
    $floorNo = (int)($tower['floor'] ?? 0);
    $floor = duel_tower_floors($m)[$floorNo] ?? null;
    if ($userId <= 0 || !$floor) {
        return null;
    }
    $turns = (int)$st['turn'];
    $won = $st['winner'] === 0 && ((int)($tower['turns'] ?? 0) <= 0 || $turns <= (int)$tower['turns']);
    duel_exec($m, 'INSERT INTO game_tower_progress (user_id, best_floor, attempts, wins) VALUES (?, 0, 1, ?)
        ON DUPLICATE KEY UPDATE attempts = attempts + 1, wins = wins + VALUES(wins)', 'ii', [$userId, $won ? 1 : 0]);
    $out = ['floor' => $floorNo, 'won' => $won, 'turns' => $turns, 'first' => false, 'godos' => 0, 'vouchers' => 0, 'boss' => $floor['boss']];
    if (!$won) {
        return $out;
    }
    $team = json_encode(array_map(static fn($u) => ['id' => (int)$u['pid'], 'level' => (int)$u['level']],
        array_values(array_filter($st['units'], static fn($u) => (int)$u['side'] === 0))));
    $first = duel_exec($m, 'INSERT IGNORE INTO game_tower_clears (user_id, floor, match_id, turns, team_json) VALUES (?, ?, ?, ?, ?)',
        'iiiis', [$userId, $floorNo, (int)$row['id'], $turns, $team]) === 1;
    if (!$first) {
        return $out;
    }
    $out['first'] = true;
    duel_exec($m, 'UPDATE game_tower_progress SET best_at = IF(? > best_floor, NOW(), best_at), best_floor = GREATEST(best_floor, ?) WHERE user_id = ?',
        'iii', [$floorNo, $floorNo, $userId]);
    if ($floor['reward'] > 0 && duel_reward_grant($m, $userId, 'tower', (string)$floorNo, $floor['reward'], (int)$row['id'])) {
        $out['godos'] = $floor['reward'];
    }
    for ($i = 1; $i <= $floor['vouchers'] && $floor['voucher']; $i++) {
        $out['vouchers'] += duel_exec($m, 'INSERT IGNORE INTO game_tower_vouchers (user_id, floor, n, max_rarity) VALUES (?, ?, ?, ?)',
            'iiis', [$userId, $floorNo, $i, $floor['voucher']]);
    }
    if ($floorNo === DUEL_TOWER_FLOORS) {
        duel_tower_notice_top($m, $userId);
        // Fase T4: il badge «In cima alla Torre». Se non si crea, il piano resta battuto.
        try {
            require_once __DIR__ . '/badges.php';
            $badge = duel_tower_top_badge($m);
            $out['badge'] = $badge !== null && duel_badge_give($m, $userId, $badge);
        } catch (Throwable $e) {
            error_log('[duel torre] badge del piano 100: ' . $e->getMessage());
        }
    }
    return $out;
}

/**
 * Decisione 9: una riga nel canale dei duelli su Discord quando qualcuno
 * conquista il piano 100. Solo a Torre aperta a tutti (le prove dello staff
 * non scrivono) e con l'interruttore `discord` acceso. Parte dopo la
 * risposta al browser, come gli altri avvisi (notice.php).
 */
function duel_tower_notice_top(mysqli $m, int $userId): void
{
    $s = duel_settings($m);
    if (($s['tower'] ?? '0') !== '1' || ($s['discord'] ?? '1') !== '1') {
        return;
    }
    require_once __DIR__ . '/notice.php';
    $name = (string)(duel_row($m, 'SELECT username FROM utenti WHERE id = ?', 'i', [$userId])['username'] ?? 'Qualcuno');
    $n = (int)(duel_row($m, 'SELECT COUNT(*) AS n FROM game_tower_clears WHERE floor = ?', 'i', [DUEL_TOWER_FLOORS])['n'] ?? 1);
    $url = 'https://cripsum.com/it/duelli/torre';
    duel_discord_queue(['op' => 'post', 'payload' => [
        'target' => 'duelli',
        'title' => '🏔️ In cima alla Torre',
        'description' => "**$name** ha battuto il piano 100 della Torre" . ($n <= 1 ? ', per primo.' : " ({$n}º a riuscirci).") . "\nProva anche tu: $url",
        'url' => $url,
        'color' => '#eab308',
        'footer_text' => 'Duelli · cripsum.com',
    ]]);
}

// ─────────────────────────────────────────────────────────────────────────
// Il potenziamento gratuito
// ─────────────────────────────────────────────────────────────────────────

/** I potenziamenti gratuiti ancora da usare. */
function duel_tower_vouchers(mysqli $m, int $userId): array
{
    return array_map(static fn($r) => ['id' => (int)$r['id'], 'floor' => (int)$r['floor'], 'max' => (string)$r['max_rarity']],
        duel_rows($m, 'SELECT id, floor, max_rarity FROM game_tower_vouchers WHERE user_id = ? AND used_at IS NULL ORDER BY floor, n', 'i', [$userId]));
}

/**
 * Usa un potenziamento gratuito: il personaggio (tuo, non oltre la rarita'
 * del buono, non gia' al livello massimo) sale di un livello senza copie.
 * Tutto in una transazione: il buono e il livello insieme o niente.
 */
function duel_tower_voucher_use(mysqli $m, int $userId, int $voucherId, int $pid): array
{
    return duel_tx($m, static function () use ($m, $userId, $voucherId, $pid): array {
        $v = duel_row($m, 'SELECT * FROM game_tower_vouchers WHERE id = ? AND user_id = ? FOR UPDATE', 'ii', [$voucherId, $userId]);
        if (!$v || $v['used_at'] !== null) {
            throw duel_user_error('Questo potenziamento non c\'è più.', 'This upgrade is gone.', 404, 'VOUCHER_GONE');
        }
        $own = duel_row($m, 'SELECT livello FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ? FOR UPDATE', 'ii', [$userId, $pid]);
        $char = duel_db_characters($m, [$pid])[$pid] ?? null;
        if (!$own || !$char) {
            throw duel_user_error('Non hai questo personaggio.', 'You don\'t own this character.', 400, 'VOUCHER_PICK');
        }
        $rarity = duel_rarity_key((string)$char['rarita'], !empty($char['limitato']));
        if (duel_rarity($rarity)['tier'] > duel_rarity((string)$v['max_rarity'])['tier']) {
            throw duel_user_error('Questo potenziamento arriva fino a una rarità più bassa.', 'This upgrade only goes up to a lower rarity.', 400, 'VOUCHER_PICK');
        }
        $level = (int)$own['livello'];
        if ($level >= duel_rules()['level_max']) {
            throw duel_user_error('È già al livello massimo.', 'Already at max level.', 400, 'VOUCHER_PICK');
        }
        duel_exec($m, 'UPDATE utenti_personaggi SET livello = livello + 1 WHERE utente_id = ? AND personaggio_id = ?', 'ii', [$userId, $pid]);
        duel_exec($m, 'UPDATE game_tower_vouchers SET used_at = NOW(), used_pid = ? WHERE id = ?', 'ii', [$pid, $voucherId]);
        return ['pid' => $pid, 'level' => $level + 1];
    });
}

// ─────────────────────────────────────────────────────────────────────────
// Quello che serve alle pagine
// ─────────────────────────────────────────────────────────────────────────

/** Le carte di una squadra di un piano (per la pagina e il foglio). */
function duel_tower_cards(mysqli $m, array $floor): array
{
    $chars = duel_db_characters($m, array_column($floor['team'], 'id'));
    $out = [];
    foreach ($floor['team'] as $e) {
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

/** La classifica: piu' in alto prima, a pari piano chi ci e' arrivato prima. */
function duel_tower_board(mysqli $m, int $limit = 20, ?array $userIds = null): array
{
    $where = 'p.best_floor > 0';
    $types = '';
    $params = [];
    if ($userIds !== null) {
        $userIds = array_values(array_unique(array_map('intval', $userIds))) ?: [0];
        $where .= ' AND p.user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')';
        $types = str_repeat('i', count($userIds));
        $params = $userIds;
    }
    return array_map(static fn($r) => ['id' => (int)$r['user_id'], 'username' => (string)($r['username'] ?? '?'), 'floor' => (int)$r['best_floor']],
        duel_rows($m, "SELECT p.user_id, u.username, p.best_floor FROM game_tower_progress p LEFT JOIN utenti u ON u.id = p.user_id
            WHERE $where ORDER BY p.best_floor DESC, p.best_at ASC LIMIT " . max(1, min(100, $limit)), $types, $params));
}

/**
 * La Torre per la pagina: i tratti, tutti i piani in breve, il tuo
 * progresso, i potenziamenti da usare.
 */
function duel_tower_view(mysqli $m, int $userId, string $lang): ?array
{
    if (!duel_tower_ready($m)) {
        return null;
    }
    $lang = $lang === 'en' ? 'en' : 'it';
    $floors = duel_tower_floors($m);
    $sectors = duel_tower_sectors($m);
    $me = duel_row($m, 'SELECT best_floor, best_at, attempts, wins FROM game_tower_progress WHERE user_id = ?', 'i', [$userId]);
    $best = (int)($me['best_floor'] ?? 0);
    $clears = [];
    foreach (duel_rows($m, 'SELECT floor, turns FROM game_tower_clears WHERE user_id = ?', 'i', [$userId]) as $r) {
        $clears[(int)$r['floor']] = (int)$r['turns'];
    }
    $chars = duel_db_characters($m, array_merge(...array_map(static fn($f) => array_column($f['team'], 'id'), array_values($floors))));
    $list = [];
    foreach ($floors as $n => $f) {
        $faces = [];
        foreach ($f['team'] as $e) {
            $c = $chars[$e['id']] ?? null;
            if ($c) {
                $faces[] = ['id' => $e['id'], 'name' => (string)$c['nome'], 'img' => duel_media($c['img_url'] ?? ''),
                    'rarity' => duel_rarity_key((string)$c['rarita'], !empty($c['limitato'])), 'level' => $e['level']];
            }
        }
        $list[] = [
            'n' => $n, 'sector' => $f['sector'], 'title' => $f['title'][$lang] ?? null, 'boss' => $f['boss'], 'bot' => $f['bot'],
            'team' => $faces, 'boon' => $f['boon'], 'boon_text' => $f['boon'] ? duel_text_trait_bonus($f['boon'], $lang) : '',
            'rule' => $f['rule'], 'rule_text' => duel_tower_rule_text($f['rule'], $lang),
            'reward' => $f['reward'], 'voucher' => $f['voucher'], 'vouchers' => $f['vouchers'],
            'state' => isset($clears[$n]) ? 'done' : ($n === $best + 1 ? 'now' : ($n <= $best ? 'done' : 'locked')),
            'turns' => $clears[$n] ?? null,
            // Come duel_tower_power_recommended, ma dalle facce: rarita' e livello bastano.
            'power' => (int)round(duel_tower_power($faces, $f['boon']) * 1.05),
        ];
    }
    // La tua posizione: piu' in alto prima, a pari piano chi ci e' arrivato prima.
    $pos = null;
    if ($best > 0 && !empty($me['best_at'])) {
        $pos = 1 + (int)(duel_row($m, 'SELECT COUNT(*) AS n FROM game_tower_progress WHERE best_floor > ? OR (best_floor = ? AND best_at < ?)',
            'iis', [$best, $best, (string)$me['best_at']])['n'] ?? 0);
    }
    return [
        'floors' => $list,
        'sectors' => array_values(array_map(static fn($s) => $s[$lang], $sectors)),
        'me' => ['best' => $best, 'next' => min(DUEL_TOWER_FLOORS, $best + 1), 'attempts' => (int)($me['attempts'] ?? 0), 'wins' => (int)($me['wins'] ?? 0),
            'bosses' => count(array_filter(array_keys($clears), static fn($n) => $n % 10 === 0)), 'done' => $best >= DUEL_TOWER_FLOORS, 'pos' => $pos],
        'vouchers' => duel_tower_vouchers($m, $userId),
        'board' => duel_tower_board($m, 20),
        'open' => duel_tower_open_for($m),
    ];
}

/**
 * Il foglio di un piano: squadra nemica, potenza, premi, e le tue squadre
 * salvate con la loro potenza e, se la regola del piano non le vuole, perche'.
 */
function duel_tower_sheet(mysqli $m, int $userId, int $floorNo, string $lang): array
{
    $floor = duel_tower_floors($m)[$floorNo] ?? null;
    if (!$floor) {
        throw duel_user_error('Piano non trovato.', 'Floor not found.', 404, 'TOWER_BROKEN');
    }
    $lang = $lang === 'en' ? 'en' : 'it';
    $enemy = duel_tower_enemy($m, $floor);
    $terrain = !empty($floor['rule']['terrain']) ? (duel_terrains()[$floor['rule']['terrain']] ?? null) : null;
    return [
        'n' => $floorNo, 'sector' => duel_tower_sectors($m)[$floor['sector']][$lang], 'title' => $floor['title'][$lang] ?? null, 'boss' => $floor['boss'],
        'bot' => $floor['bot'], 'enemy' => duel_tower_cards($m, $floor), 'boon' => $floor['boon'],
        'boon_text' => $floor['boon'] ? duel_text_trait_bonus($floor['boon'], $lang) : '',
        'rule' => $floor['rule'], 'rule_text' => duel_tower_rule_text($floor['rule'], $lang),
        'terrain' => $terrain ? ['k' => (string)$floor['rule']['terrain'], 'n' => $terrain[$lang], 'd' => $terrain['d_' . $lang], 'g' => (int)$terrain['g']] : null,
        'power' => duel_tower_power_recommended($enemy['kits'], $floor['boon']),
        'reward' => $floor['reward'], 'voucher' => $floor['voucher'], 'vouchers' => $floor['vouchers'],
        'cleared' => duel_row($m, 'SELECT 1 AS x FROM game_tower_clears WHERE user_id = ? AND floor = ?', 'ii', [$userId, $floorNo]) !== null,
        'open' => $floorNo <= duel_tower_best($m, $userId) + 1,
        'teams' => duel_tower_teams($m, $userId, $floor['rule'], $lang),
    ];
}

/**
 * Le tue squadre salvate davanti a un piano: chi c'e', la potenza e,
 * se non vanno bene, perche' (lo stesso controllo di duel_tower_start).
 */
function duel_tower_teams(mysqli $m, int $userId, array $rule, string $lang): array
{
    require_once __DIR__ . '/pages.php';
    $en = $lang === 'en';
    $out = [];
    foreach (duel_teams_view($m, $userId, $lang) as $t) {
        $error = null;
        if (count($t['cards']) < 3 || $t['missing']) {
            $error = $en ? 'Incomplete team' : 'Squadra incompleta';
        } elseif ($e = duel_tower_team_error($rule, $t['cards'])) {
            $error = $e[$en ? 1 : 0];
        }
        $out[] = ['slot' => $t['slot'], 'name' => $t['name'], 'names' => array_column($t['cards'], 'name'),
            'power' => duel_tower_power($t['cards']), 'error' => $error];
    }
    return $out;
}

/** Un piano in breve, per il riquadro della lobby e la fine partita. */
function duel_tower_floor_brief(mysqli $m, array $floor, string $lang): array
{
    $lang = $lang === 'en' ? 'en' : 'it';
    $levels = array_column($floor['team'], 'level') ?: [1];
    return [
        'n' => $floor['floor'], 'sector' => duel_tower_sectors($m)[$floor['sector']][$lang], 'title' => $floor['title'][$lang] ?? null,
        'boss' => $floor['boss'], 'bot' => $floor['bot'], 'levels' => [min($levels), max($levels)], 'team' => duel_tower_cards($m, $floor),
        'boon_text' => $floor['boon'] ? duel_text_trait_bonus($floor['boon'], $lang) : '', 'rule_text' => duel_tower_rule_text($floor['rule'], $lang),
        'reward' => $floor['reward'], 'voucher' => $floor['voucher'],
    ];
}

/** Il riquadro della lobby (tavola 21): il piano in cui sei. Null se la Torre e' spenta per te. */
function duel_tower_brief(mysqli $m, int $userId, string $lang): ?array
{
    if (!duel_tower_ready($m) || !duel_tower_open_for($m)) {
        return null;
    }
    $best = duel_tower_best($m, $userId);
    $floor = duel_tower_floors($m)[min(DUEL_TOWER_FLOORS, $best + 1)] ?? null;
    if (!$floor) {
        return null;
    }
    return ['best' => $best, 'floors' => DUEL_TOWER_FLOORS, 'done' => $best >= DUEL_TOWER_FLOORS, 'vouchers' => count(duel_tower_vouchers($m, $userId)),
        'floor' => duel_tower_floor_brief($m, $floor, $lang)];
}

/**
 * Chi si puo' potenziare con un buono fino a $maxRarity: i tuoi personaggi
 * di quella rarita' o sotto, non gia' al livello massimo. Prima quelli
 * delle squadre salvate, poi i piu' rari e i piu' alti.
 */
function duel_tower_pick_list(mysqli $m, int $userId, string $maxRarity): array
{
    $collection = duel_db_collection($m, $userId);
    if (!$collection) {
        return [];
    }
    $maxTier = duel_rarity($maxRarity)['tier'];
    $levelMax = duel_rules()['level_max'];
    $inTeams = array_flip(array_merge([], ...array_map(static fn($t) => $t['team'], duel_teams($m, $userId))));
    $out = [];
    foreach (duel_db_characters($m, array_keys($collection)) as $id => $c) {
        $rarity = duel_rarity_key((string)$c['rarita'], !empty($c['limitato']));
        $level = $collection[$id] ?? 1;
        $tier = duel_rarity($rarity)['tier'];
        if ($tier > $maxTier || $level >= $levelMax) {
            continue;
        }
        $kit = duel_character_kit($c, $level);
        $out[] = ['id' => (int)$id, 'name' => (string)$c['nome'], 'img' => duel_media($c['img_url'] ?? ''), 'rarity' => $rarity, 'role' => $kit['role'],
            'level' => $level, 'cost' => duel_rarity($rarity)['cost'], 'tier' => $tier, 'team' => isset($inTeams[$id])];
    }
    usort($out, static fn($a, $b) => [$b['team'], $b['tier'], $b['level'], $a['name']] <=> [$a['team'], $a['tier'], $a['level'], $b['name']]);
    return $out;
}

/**
 * La Torre nella fine partita (tavola 23): il piano, se e' superato (anche
 * la regola dei turni), se era la prima volta, i buoni appena vinti con chi
 * si puo' potenziare, la potenza per il consiglio, il piano dopo.
 */
function duel_tower_summary(mysqli $m, array $row, int $userId, string $lang): ?array
{
    $tower = duel_match_rules($row)['tower'] ?? null;
    if (!$tower || !duel_tower_ready($m) || (int)$row['player1_id'] !== $userId) {
        return null;
    }
    $n = (int)($tower['floor'] ?? 0);
    $floors = duel_tower_floors($m);
    $floor = $floors[$n] ?? null;
    if (!$floor) {
        return null;
    }
    $lang = $lang === 'en' ? 'en' : 'it';
    $st = duel_match_state($row);
    $turnsMax = (int)($tower['turns'] ?? 0);
    $won = ($st['winner'] ?? null) === 0;
    $first = duel_row($m, 'SELECT 1 AS x FROM game_tower_clears WHERE user_id = ? AND floor = ? AND match_id = ?', 'iii', [$userId, $n, (int)$row['id']]) !== null;

    // I buoni di questo piano ancora da usare (vinti adesso, o prima e mai usati).
    $vouchers = array_values(array_filter(duel_tower_vouchers($m, $userId), static fn($v) => $v['floor'] === $n));
    $mine = [];
    $theirs = [];
    foreach ($st['units'] as $u) {
        $k = ['rarity' => (string)$u['rarity'], 'level' => (int)$u['level']];
        if ((int)$u['side'] === 0) {
            $mine[] = $k;
        } else {
            $theirs[] = $k;
        }
    }
    $boon = (array)($tower['engine']['boons'][1] ?? []);
    // Quante volte hai giocato questo piano (le regole della partita dicono quale).
    $attempts = (int)(duel_row($m, "SELECT COUNT(*) AS n FROM game_matches WHERE player1_id = ? AND mode = 'bot' AND status = 'finished' AND rules_json LIKE ?",
        'is', [$userId, '%"tower":{"floor":' . $n . ',%'])['n'] ?? 0);
    $best = duel_tower_best($m, $userId);
    return [
        'n' => $n, 'sector' => duel_tower_sectors($m)[$floor['sector']][$lang], 'title' => $floor['title'][$lang] ?? null, 'boss' => $floor['boss'],
        'won' => $won, 'cleared' => $won && ($turnsMax <= 0 || (int)$st['turn'] <= $turnsMax), 'turns_max' => $turnsMax,
        'first' => $first, 'replay' => !empty($tower['replay']), 'reward' => $floor['reward'],
        'voucher' => $floor['voucher'], 'vouchers' => $vouchers,
        'pick' => $vouchers ? duel_tower_pick_list($m, $userId, $vouchers[0]['max']) : [],
        'power' => ['you' => duel_tower_power($mine), 'rec' => (int)round(duel_tower_power($theirs, $boon) * 1.05)],
        'attempts' => $attempts, 'best' => $best, 'top' => $n === DUEL_TOWER_FLOORS,
        // Fase T4: il badge «In cima alla Torre» e' sul profilo.
        'badge' => $n === DUEL_TOWER_FLOORS && rt_has_table($m, 'custom_badges') && rt_has_table($m, 'user_custom_badges')
            && duel_row($m, "SELECT 1 AS x FROM user_custom_badges ub JOIN custom_badges b ON b.id = ub.badge_id WHERE ub.utente_id = ? AND b.slug = 'duelli-torre-cima'",
                'i', [$userId]) !== null,
        'next' => $n < DUEL_TOWER_FLOORS && $n + 1 <= $best + 1 && isset($floors[$n + 1]) ? duel_tower_floor_brief($m, $floors[$n + 1], $lang) : null,
    ];
}
