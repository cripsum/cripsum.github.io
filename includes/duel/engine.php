<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — il motore.
 *
 * Puro e deterministico: riceve lo stato della partita e una mossa e
 * restituisce il nuovo stato e la lista degli eventi (chi ha colpito chi,
 * quanto, se critico, quali stati sono entrati...). Niente database, niente
 * sessione, niente orologio: la stessa partita con lo stesso seme va sempre
 * allo stesso modo. Lo usano le partite vere, il bot, il simulatore e il
 * replay.
 *
 * Uso:
 *   [$state, $events] = duel_new_match([$teamA, $teamB], $seed);
 *   $mosse = duel_legal($state);               // cosa puo' fare chi e' di turno
 *   [$state, $events] = duel_apply($state, 'abilita', $targetId);
 *
 * Una squadra e' una lista di 3 kit (vedi kits.php), ognuno con 'level'.
 * Le carte hanno id 1-3 (squadra 0) e 4-6 (squadra 1).
 *
 * Le mosse sono 'attacco', 'abilita', 'ultimate', 'guardia' e, solo quando
 * non c'e' altro, 'passa'.
 */

require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/traits.php';

final class DuelError extends RuntimeException
{
}

// ─────────────────────────────────────────────────────────────────────────
// Dado con seme
// ─────────────────────────────────────────────────────────────────────────

function duel_rng_new(int $seed): Random\Randomizer
{
    return new Random\Randomizer(new Random\Engine\Xoshiro256StarStar($seed));
}

function duel_rng_save(Random\Randomizer $r): string
{
    return base64_encode(serialize($r->engine));
}

function duel_rng_load(string $saved): Random\Randomizer
{
    $engine = unserialize(base64_decode($saved), ['allowed_classes' => [Random\Engine\Xoshiro256StarStar::class]]);
    if (!$engine instanceof Random\Engine\Xoshiro256StarStar) {
        throw new DuelError('Stato del dado non valido.');
    }
    return new Random\Randomizer($engine);
}

/** Vero con probabilita' $pct su 100 (anche con decimali). */
function duel_roll(array &$c, float $pct): bool
{
    if ($pct <= 0) {
        return false;
    }
    if ($pct >= 100) {
        return true;
    }
    return $c['rnd']->getInt(0, 9999) < (int)round($pct * 100);
}

function duel_rand_int(array &$c, int $min, int $max): int
{
    return $max <= $min ? $min : $c['rnd']->getInt($min, $max);
}

// ─────────────────────────────────────────────────────────────────────────
// Creazione della partita
// ─────────────────────────────────────────────────────────────────────────

/**
 * Prepara una partita. $teams = [[kit, kit, kit], [kit, kit, kit]]; ogni kit
 * ha gia' 'stats' calcolate per il suo livello (kits.php).
 * Restituisce [stato, eventi]: lo stato aspetta la mossa della prima carta.
 */
function duel_new_match(array $teams, int $seed, array $opts = []): array
{
    $rules = duel_rules();
    if (count($teams) !== 2) {
        throw new DuelError('Servono due squadre.');
    }

    $st = [
        'v' => DUEL_ENGINE_VERSION,
        'rng' => duel_rng_save(duel_rng_new($seed)),
        'turn' => 0,
        'actor' => null,
        'extra' => null,
        'over' => false,
        'winner' => null,
        'reason' => null,
        'ps' => [$rules['ps_start'], $rules['ps_start']],
        'terrain' => null,
        'seq' => 0,
        'units' => [],
        'opts' => $opts,
    ];

    $id = 0;
    foreach ($teams as $side => $team) {
        $team = array_values($team);
        if (count($team) !== $rules['team_size']) {
            throw new DuelError('Ogni squadra ha 3 personaggi.');
        }
        foreach ($team as $slot => $kit) {
            $id++;
            $s = $kit['stats'];
            $st['units'][$id] = [
                'id' => $id,
                'side' => (int)$side,
                'slot' => (int)$slot,
                'pid' => (int)($kit['pid'] ?? 0),
                'name' => (string)($kit['name'] ?? 'Personaggio'),
                'rarity' => (string)($kit['rarity'] ?? 'comune'),
                'role' => (string)($kit['role'] ?? 'DPS'),
                'level' => (int)($kit['level'] ?? 1),
                'kit' => $kit,
                'max' => $s,
                'hp' => (int)$s['hp'],
                'shield' => 0,
                'charge' => 0,
                'av' => 0.0,
                'ko' => false,
                'ko_turn' => 0,
                'guard' => false,
                'fx' => [],
                'cd' => 0,
                'once' => [],
                'stk' => 0,
                'form' => null,
                'tmods' => [],
                'stats' => ['dealt' => 0, 'taken' => 0, 'healed' => 0, 'kos' => 0],
            ];
        }
    }

    $c = duel_ctx($st);
    duel_ev($c, ['t' => 'start']);
    duel_apply_traits($c);
    duel_apply_boons($c, (array)($opts['boons'] ?? []));
    // Torre: un piano col terreno da subito (vale per tutti; la fonte e' la prima carta nemica).
    if (!empty($opts['terrain']['k'])) {
        $src = 0;
        foreach ($c['st']['units'] as $id => $u) {
            if ($u['side'] === 1) {
                $src = (int)$id;
                break;
            }
        }
        duel_set_terrain($c, $src, (string)$opts['terrain']['k'], (int)($opts['terrain']['g'] ?? 0));
    }

    // Passive di inizio partita, carta per carta nell'ordine delle squadre.
    foreach (array_keys($c['st']['units']) as $uid) {
        duel_trigger($c, $uid, 'battle_start', []);
    }

    foreach ($c['st']['units'] as $uid => $u) {
        $c['st']['units'][$uid]['av'] = $rules['av_gauge'] / max(1.0, duel_eff($c['st'], $uid)['spd']);
    }

    duel_next_turn($c);
    return duel_ctx_close($c);
}

/**
 * Legami: chi ne fa parte prende statistiche in piu' (subito, prima degli
 * scudi d'inizio partita), modificatori come una passiva, carica, PS e
 * rigenerazione d'inizio partita.
 */
function duel_apply_traits(array &$c): void
{
    $defs = duel_traits();
    foreach ([0, 1] as $side) {
        $ids = [];
        $kits = [];
        foreach ($c['st']['units'] as $id => $u) {
            if ($u['side'] === $side) {
                $ids[] = (int)$id;
                $kits[] = $u['kit'];
            }
        }
        foreach (duel_team_traits($kits) as $key => [$tier, $who]) {
            $units = array_map(static fn($i) => $ids[$i], $who);
            duel_bonus_apply($c, $side, $units, $defs[$key]['tiers'][$tier]);
            duel_ev($c, ['t' => 'trait', 'side' => $side, 'k' => $key, 'tier' => $tier, 'units' => $units]);
        }
    }
}

/**
 * Torre: i potenziamenti di una squadra intera, con le stesse chiavi dei
 * legami (HP, ATK, DEF in %, critico, carica, PS...). Dopo i legami, prima
 * delle passive d'inizio partita. $boons: [lato => bonus].
 */
function duel_apply_boons(array &$c, array $boons): void
{
    foreach ($boons as $side => $bonus) {
        $side = (int)$side;
        if (!is_array($bonus) || !$bonus || !in_array($side, [0, 1], true)) {
            continue;
        }
        $units = [];
        foreach ($c['st']['units'] as $id => $u) {
            if ($u['side'] === $side) {
                $units[] = (int)$id;
            }
        }
        duel_bonus_apply($c, $side, $units, $bonus);
        duel_ev($c, ['t' => 'boon', 'side' => $side, 'b' => $bonus, 'units' => $units]);
    }
}

/** Un bonus di legame o di potenziamento su alcune carte di un lato. */
function duel_bonus_apply(array &$c, int $side, array $units, array $bonus): void
{
    foreach ($units as $uid) {
        $u = &$c['st']['units'][$uid];
        foreach (['hp', 'atk', 'def'] as $stat) {
            if (!empty($bonus[$stat])) {
                $u['max'][$stat] = (int)round($u['max'][$stat] * (1 + $bonus[$stat] / 100));
            }
        }
        $u['hp'] = $u['max']['hp'];
        foreach (['crit', 'cdmg', 'spd_pct', 'lifesteal', 'dmg_pct', 'heal_out_pct', 'heal_in_pct', 'prec'] as $mod) {
            if (!empty($bonus[$mod])) {
                $u['tmods'][$mod] = ($u['tmods'][$mod] ?? 0) + $bonus[$mod];
            }
        }
        unset($u);
        if (!empty($bonus['charge'])) {
            duel_add_charge($c, $uid, (int)$bonus['charge']);
        }
        if (!empty($bonus['regen'])) {
            duel_put_status($c, $uid, $uid, 'rigenerazione', (float)$bonus['regen'], 2, 1, []);
        }
    }
    if (!empty($bonus['ps'])) {
        duel_add_ps($c, $side, (int)$bonus['ps']);
    }
}

/** Contesto di lavoro: lo stato, il dado acceso e gli eventi. */
function duel_ctx(array $st): array
{
    return ['st' => $st, 'rnd' => duel_rng_load($st['rng']), 'ev' => [], 'depth' => 0, 'counters' => []];
}

function duel_ctx_close(array $c): array
{
    $c['st']['rng'] = duel_rng_save($c['rnd']);
    return [$c['st'], $c['ev']];
}

function duel_ev(array &$c, array $ev): void
{
    $ev['n'] = $c['st']['turn'];
    // Vita e scudo della carta dopo l'evento: l'arena anima le barre colpo
    // per colpo senza rifare i conti del motore.
    $who = $ev['dst'] ?? $ev['u'] ?? null;
    if ($who !== null && isset($c['st']['units'][$who])
        && in_array($ev['t'], ['hit', 'dot', 'heal', 'reflect', 'detonate', 'curse', 'chain', 'guarded', 'revive', 'shield', 'undying', 'ko'], true)) {
        $ev['hp'] = $c['st']['units'][$who]['hp'];
        $ev['sh'] = $c['st']['units'][$who]['shield'];
    }
    $c['ev'][] = $ev;
}

// ─────────────────────────────────────────────────────────────────────────
// Lettura dello stato
// ─────────────────────────────────────────────────────────────────────────

function duel_alive(array $st, int $side): array
{
    $out = [];
    foreach ($st['units'] as $id => $u) {
        if ($u['side'] === $side && !$u['ko']) {
            $out[] = (int)$id;
        }
    }
    return $out;
}

function duel_enemy_side(int $side): int
{
    return $side === 0 ? 1 : 0;
}

function duel_has(array $st, int $id, string $key): bool
{
    foreach ($st['units'][$id]['fx'] as $fx) {
        if ($fx['k'] === $key) {
            return true;
        }
    }
    return false;
}

function duel_fx(array $st, int $id, string $key): ?array
{
    foreach ($st['units'][$id]['fx'] as $fx) {
        if ($fx['k'] === $key) {
            return $fx;
        }
    }
    return null;
}

function duel_has_negative(array $st, int $id): bool
{
    foreach ($st['units'][$id]['fx'] as $fx) {
        if (duel_status_is_negative($fx['k'])) {
            return true;
        }
    }
    return false;
}

function duel_hp_pct(array $u): float
{
    return $u['max']['hp'] > 0 ? $u['hp'] * 100 / $u['max']['hp'] : 0.0;
}

/** Il kit in uso: con una Forma attiva le mosse della Forma sostituiscono quelle base. */
function duel_move_def(array $u, string $move): ?array
{
    $key = $move === 'attacco' ? 'attack' : ($move === 'abilita' ? 'skill' : ($move === 'ultimate' ? 'ultimate' : null));
    if ($key === null) {
        return null;
    }
    if ($u['form'] !== null) {
        $form = $u['kit']['forms'][$u['form']['k']] ?? null;
        if ($form && isset($form[$key])) {
            return $form[$key];
        }
    }
    return $u['kit'][$key] ?? null;
}

/**
 * Statistiche effettive di una carta: base + passive + aure degli alleati +
 * cariche + forma + stati + terreno. Tutte le percentuali sono in punti.
 */
function duel_eff(array $st, int $id): array
{
    $rules = duel_rules();
    $u = $st['units'][$id];
    $m = [
        'atk_pct' => 0, 'def_pct' => 0, 'spd_pct' => 0, 'crit' => 0, 'cdmg' => 0, 'prec' => 0, 'ten' => 0,
        'dmg_pct' => 0, 'taken_pct' => 0, 'red_pct' => 0, 'red_skill_pct' => 0, 'heal_out_pct' => 0, 'heal_in_pct' => 0,
        'dodge' => 0, 'reflect' => 0, 'lifesteal' => 0, 'pen' => 0, 'regen_pct' => 0,
    ];
    $add = static function (array &$m, ?array $mods, float $times = 1.0): void {
        if (!$mods) {
            return;
        }
        foreach ($mods as $k => $v) {
            if (isset($m[$k]) && is_numeric($v)) {
                $m[$k] += $v * $times;
            }
        }
    };

    $passive = $u['kit']['passive'] ?? [];
    $add($m, $passive['mods'] ?? null);
    $add($m, $u['tmods'] ?? null);

    // Aure: valgono per tutti gli alleati in piedi, chi le ha compreso.
    foreach ($st['units'] as $ally) {
        if ($ally['side'] === $u['side'] && !$ally['ko']) {
            $add($m, $ally['kit']['passive']['aura'] ?? null);
        }
    }

    foreach ($passive['scaling'] ?? [] as $sc) {
        $type = $sc['type'] ?? '';
        if ($type === 'missing_hp') {
            $steps = (int)floor((100 - duel_hp_pct($u)) / 10);
            $add($m, $sc['mods'] ?? null, max(0, $steps));
        } elseif ($type === 'hp_above' && duel_hp_pct($u) > ($sc['pct'] ?? 100)) {
            $add($m, $sc['mods'] ?? null);
        } elseif ($type === 'hp_below' && duel_hp_pct($u) < ($sc['pct'] ?? 0)) {
            $add($m, $sc['mods'] ?? null);
        } elseif ($type === 'team_ps_zero' && ($st['ps'][$u['side']] ?? 1) === 0) {
            $add($m, $sc['mods'] ?? null);
        }
    }

    if (($u['kit']['stacks'] ?? null) && $u['stk'] > 0) {
        $add($m, $u['kit']['stacks']['per'] ?? null, $u['stk']);
    }

    if ($u['form'] !== null) {
        $add($m, $u['kit']['forms'][$u['form']['k']]['mods'] ?? null);
    }

    $koAllies = 0;
    foreach ($st['units'] as $ally) {
        if ($ally['side'] === $u['side'] && $ally['ko']) {
            $koAllies++;
        }
    }

    foreach ($u['fx'] as $fx) {
        $v = (float)$fx['v'];
        switch ($fx['k']) {
            case 'atk_su': $m['atk_pct'] += $v; break;
            case 'atk_giu': $m['atk_pct'] -= $v; break;
            case 'def_su': $m['def_pct'] += $v; break;
            case 'def_giu': $m['def_pct'] -= $v; break;
            case 'corrosione': $m['def_pct'] -= (float)($fx['meta']['cur'] ?? $v); break;
            case 'vel_su': $m['spd_pct'] += $v; break;
            case 'vel_giu': $m['spd_pct'] -= $v; break;
            case 'crit_su': $m['crit'] += $v; break;
            case 'crit_giu': $m['crit'] -= $v; break;
            case 'cdmg_su': $m['cdmg'] += $v; break;
            case 'marchio': $m['taken_pct'] += $v; break;
            case 'schivata': $m['dodge'] += $v; break;
            case 'riflesso': $m['reflect'] += $v; break;
            case 'rubavita': $m['lifesteal'] += $v; break;
            case 'furia': $m['dmg_pct'] += $v * $koAllies; break;
            case 'bruciatura': $m['heal_in_pct'] -= 50; break;
            case 'ferita': $m['heal_in_pct'] -= $v; break;
        }
    }

    if ($st['terrain'] !== null) {
        $terrain = duel_terrains()[$st['terrain']['k']] ?? null;
        if ($terrain) {
            $tm = $terrain['mods'] ?? [];
            $m['crit'] += $tm['crit'] ?? 0;
            $m['spd_pct'] += $tm['spd_pct'] ?? 0;
            $m['regen_pct'] += $tm['regen_pct'] ?? 0;
            $m['heal_out_pct'] += $tm['heal_pct'] ?? 0;
        }
    }

    $floor = 1 - $rules['stat_down_cap'] / 100;
    $spdPct = max(-$rules['speed_down_cap'], min($rules['speed_up_cap'], $m['spd_pct']));

    return [
        'atk' => $u['max']['atk'] * max($floor, 1 + $m['atk_pct'] / 100),
        'def' => $u['max']['def'] * max($floor, 1 + $m['def_pct'] / 100),
        'spd' => max(1.0, $u['max']['spd'] * (1 + $spdPct / 100)),
        'crit' => $u['max']['crit'] + $m['crit'],
        'cdmg' => $u['max']['cdmg'] + $m['cdmg'],
        'prec' => $u['max']['prec'] + $m['prec'],
        'ten' => $u['max']['ten'] + $m['ten'],
        'dmg_pct' => $m['dmg_pct'],
        'taken_pct' => $m['taken_pct'],
        'red_pct' => $m['red_pct'],
        'red_skill_pct' => $m['red_skill_pct'],
        'heal_out_pct' => $m['heal_out_pct'],
        'heal_in_pct' => max(-75, $m['heal_in_pct']),
        'dodge' => min($rules['dodge_cap'], max(0, $m['dodge'])),
        'reflect' => max(0, $m['reflect']),
        'lifesteal' => max(0, $m['lifesteal']),
        'pen' => max(0, min(80, $m['pen'])),
        'regen_pct' => $m['regen_pct'],
    ];
}

/**
 * Sfinimento: dal turno 'fatigue_turn' cure e scudi ricevuti calano e i
 * danni crescono a ogni blocco di turni. Restituisce ['heal' => % in meno,
 * 'dmg' => % in piu'].
 */
function duel_fatigue(array $st): array
{
    $rules = duel_rules();
    $turn = (int)$st['turn'];
    if ($turn < $rules['fatigue_turn']) {
        return ['heal' => 0, 'dmg' => 0];
    }
    $steps = 1 + intdiv($turn - $rules['fatigue_turn'], $rules['fatigue_step']);
    return ['heal' => $rules['fatigue_heal_cut'], 'dmg' => $rules['fatigue_dmg'] * $steps];
}

// ─────────────────────────────────────────────────────────────────────────
// Ordine dei turni
// ─────────────────────────────────────────────────────────────────────────

/** Chi agisce ora e i prossimi: simula il valore d'azione senza toccare lo stato. */
function duel_timeline(array $st, int $count = 8): array
{
    $rules = duel_rules();
    $av = [];
    $spd = [];
    foreach ($st['units'] as $id => $u) {
        if (!$u['ko']) {
            $av[$id] = (float)$u['av'];
            $spd[$id] = duel_eff($st, (int)$id)['spd'];
        }
    }
    $out = [];
    if ($st['actor'] !== null && !$st['over']) {
        $out[] = (int)$st['actor'];
        $av[$st['actor']] = $rules['av_gauge'] / $spd[$st['actor']];
    }
    while (count($out) < $count && $av) {
        $next = duel_pick_lowest($av, $spd);
        $out[] = $next;
        $elapsed = $av[$next];
        foreach ($av as $id => $v) {
            $av[$id] = $v - $elapsed;
        }
        $av[$next] = $rules['av_gauge'] / $spd[$next];
    }
    return $out;
}

/** Il valore d'azione piu' basso; a pari merito il piu' veloce, poi l'id piu' basso. */
function duel_pick_lowest(array $av, array $spd): int
{
    $best = null;
    foreach ($av as $id => $v) {
        if ($best === null
            || $v < $av[$best] - 1e-9
            || (abs($v - $av[$best]) <= 1e-9 && ($spd[$id] > $spd[$best] || ($spd[$id] === $spd[$best] && $id < $best)))) {
            $best = $id;
        }
    }
    return (int)$best;
}

/**
 * Avanza (pct > 0) o ritarda (pct < 0) una carta nell'ordine: la percentuale
 * e' del suo giro intero (10.000 / Velocita'), come nella formula.
 */
function duel_shift_av(array &$c, int $id, float $pct): void
{
    $rules = duel_rules();
    if ($c['st']['units'][$id]['ko']) {
        return;
    }
    $cycle = $rules['av_gauge'] / duel_eff($c['st'], $id)['spd'];
    $c['st']['units'][$id]['av'] = max(0.0, $c['st']['units'][$id]['av'] - $cycle * $pct / 100);
    duel_ev($c, ['t' => 'order', 'u' => $id, 'pct' => $pct]);
}

/** Dopo un cambio di Velocita' il valore d'azione si riscala, cosi' la carta avanza o arretra subito. */
function duel_rescale_av(array &$c, int $id, float $oldSpd): void
{
    $u = &$c['st']['units'][$id];
    if ($u['ko']) {
        return;
    }
    $newSpd = duel_eff($c['st'], $id)['spd'];
    if ($newSpd > 0 && abs($newSpd - $oldSpd) > 0.01) {
        $u['av'] = $u['av'] * $oldSpd / $newSpd;
    }
}

// ─────────────────────────────────────────────────────────────────────────
// Turni
// ─────────────────────────────────────────────────────────────────────────

/**
 * Passa al prossimo che agisce e fa l'inizio del suo turno. Se non puo' agire
 * (stordito, congelato, addormentato, KO per il veleno) chiude il turno e
 * passa avanti, finche' qualcuno puo' agire o la partita finisce.
 */
function duel_next_turn(array &$c): void
{
    $rules = duel_rules();
    for ($guard = 0; $guard < 200 && !$c['st']['over']; $guard++) {
        $st = &$c['st'];
        if ($st['turn'] >= $rules['turn_limit']) {
            duel_end_by_limit($c);
            return;
        }

        if ($st['extra'] !== null && !$st['units'][$st['extra']]['ko']) {
            $id = (int)$st['extra'];
            $st['extra'] = null;
            duel_ev($c, ['t' => 'extra', 'u' => $id]);
        } else {
            $st['extra'] = null;
            $av = [];
            $spd = [];
            foreach ($st['units'] as $uid => $u) {
                if (!$u['ko']) {
                    $av[$uid] = (float)$u['av'];
                    $spd[$uid] = duel_eff($st, (int)$uid)['spd'];
                }
            }
            if (!$av) {
                return;
            }
            $id = duel_pick_lowest($av, $spd);
            $elapsed = $av[$id];
            foreach ($av as $uid => $v) {
                $st['units'][$uid]['av'] = $v - $elapsed;
            }
        }

        $st['turn']++;
        $st['actor'] = $id;
        duel_ev($c, ['t' => 'turn', 'u' => $id]);
        $since = $st['turn'] - $rules['fatigue_turn'];
        if ($since >= 0 && $since % $rules['fatigue_step'] === 0) {
            duel_ev($c, ['t' => 'fatigue'] + duel_fatigue($st));
        }
        duel_terrain_tick($c);

        if (duel_turn_start($c, $id)) {
            return; // puo' agire: si aspetta la mossa
        }
        // Non puo' agire: fine turno e avanti.
        duel_turn_end($c, $id);
        duel_check_end($c);
    }
}

/**
 * Inizio del turno di una carta: guardia che scade, danni nel tempo, cure,
 * carica, passive. Restituisce false se la carta non puo' agire.
 */
function duel_turn_start(array &$c, int $id): bool
{
    $u = &$c['st']['units'][$id];
    $u['guard'] = false;
    $max = $u['max']['hp'];
    // Lo scudo si consuma: non si accumula all'infinito.
    if ($u['shield'] > 0) {
        $u['shield'] = (int)floor($u['shield'] * (1 - duel_rules()['shield_decay'] / 100));
    }

    foreach ($u['fx'] as $i => $fx) {
        switch ($fx['k']) {
            case 'veleno':
                $dmg = (int)round($max * $fx['v'] * $fx['st'] / 100);
                duel_take($c, $id, $dmg, $fx['src'], ['pierce' => true, 'dot' => 'veleno']);
                break;
            case 'bruciatura':
                $dmg = (int)round($max * $fx['v'] / 100);
                duel_take($c, $id, $dmg, $fx['src'], ['dot' => 'bruciatura']);
                break;
            case 'corrosione':
                foreach ($c['st']['units'][$id]['fx'] as $j => $cur) {
                    if ($cur['id'] === $fx['id']) {
                        $c['st']['units'][$id]['fx'][$j]['meta']['cur'] = min(30.0, (float)($cur['meta']['cur'] ?? $cur['v']) + $cur['v']);
                    }
                }
                break;
            case 'rigenerazione':
                $regen = duel_eff($c['st'], $id)['regen_pct'];
                $amount = (int)round($max * $fx['v'] / 100 * (1 + $regen / 100));
                duel_heal($c, $fx['src'] ?: $id, $id, $amount, 'rigenerazione');
                break;
            case 'ispirato':
                duel_add_charge($c, $id, (int)$fx['v']);
                break;
            case 'prosciugato':
                duel_add_charge($c, $id, -(int)$fx['v']);
                break;
        }
        if ($c['st']['units'][$id]['ko'] || $c['st']['over']) {
            return false;
        }
    }

    duel_trigger($c, $id, 'turn_start', []);
    if ($c['st']['units'][$id]['ko'] || $c['st']['over']) {
        return false;
    }

    foreach (duel_skip_statuses() as $skip) {
        if (duel_has($c['st'], $id, $skip)) {
            duel_ev($c, ['t' => 'skip', 'u' => $id, 'k' => $skip]);
            return false;
        }
    }
    return true;
}

/**
 * Fine del turno: gli stati di chi ha agito perdono un turno (quelli messi
 * in questo turno no), le forme scadono, la ricarica scende. Finito un
 * controllo arriva la Resistenza al controllo.
 */
function duel_turn_end(array &$c, int $id): void
{
    $rules = duel_rules();
    $u = &$c['st']['units'][$id];
    if ($u['ko']) {
        return;
    }
    $keep = [];
    $expired = [];
    foreach ($u['fx'] as $fx) {
        if (!empty($fx['fresh'])) {
            $fx['fresh'] = false;
            $keep[] = $fx;
            continue;
        }
        if ((int)$fx['t'] <= 0) {
            $keep[] = $fx; // dura finche' non si consuma
            continue;
        }
        $fx['t'] = (int)$fx['t'] - 1;
        if ($fx['t'] > 0) {
            $keep[] = $fx;
        } else {
            $expired[] = $fx;
        }
    }
    $u['fx'] = $keep;
    if ($u['cd'] > 0) {
        $u['cd']--;
    }
    if ($u['form'] !== null) {
        $u['form']['t']--;
        if ($u['form']['t'] <= 0) {
            duel_ev($c, ['t' => 'form', 'u' => $id, 'k' => $u['form']['k'], 'op' => 'end']);
            $u['form'] = null;
        }
    }
    unset($u);

    $hadCc = false;
    foreach ($expired as $fx) {
        duel_ev($c, ['t' => 'status', 'u' => $id, 'k' => $fx['k'], 'op' => 'expire']);
        if ($fx['k'] === 'maledizione') {
            $pool = (int)round($fx['meta']['pool'] ?? 0);
            if ($pool > 0) {
                duel_ev($c, ['t' => 'curse', 'u' => $id, 'amount' => $pool]);
                duel_take($c, $id, $pool, (int)$fx['src'], ['pierce' => true, 'dot' => 'maledizione']);
            }
        }
        if (duel_status_is_cc($fx['k'])) {
            $hadCc = true;
        }
    }
    if ($hadCc && !$c['st']['units'][$id]['ko']) {
        duel_put_status($c, $id, $id, 'resistenza', 0, $rules['cc_resist_turns'], 1, ['fresh' => true]);
    }

    duel_trigger($c, $id, 'turn_end', []);
}

function duel_terrain_tick(array &$c): void
{
    $t = &$c['st']['terrain'];
    if ($t === null) {
        return;
    }
    $def = duel_terrains()[$t['k']] ?? [];
    $t['left']--;
    $t['since']++;
    if (!empty($def['round_poison']) && $t['round'] > 0 && $t['since'] % $t['round'] === 0) {
        foreach ($c['st']['units'] as $id => $u) {
            if (!$u['ko'] && !duel_has($c['st'], (int)$id, 'veleno')) {
                duel_put_status($c, (int)$t['src'], (int)$id, 'veleno', duel_status('veleno')['v'], 2, 1, ['chance' => 100, 'terrain' => true]);
            }
        }
    }
    if ($t['left'] <= 0) {
        duel_ev($c, ['t' => 'terrain', 'k' => $t['k'], 'op' => 'end']);
        $c['st']['terrain'] = null;
    }
}

// ─────────────────────────────────────────────────────────────────────────
// Mosse possibili
// ─────────────────────────────────────────────────────────────────────────

/**
 * Le mosse di chi e' di turno, con i bersagli validi:
 * ['attacco' => [4, 5], 'abilita' => [...], 'ultimate' => [...], 'guardia' => [0]]
 * Una mossa senza scelta di bersaglio ha [0].
 */
function duel_legal(array $st): array
{
    if ($st['over'] || $st['actor'] === null) {
        return [];
    }
    $id = (int)$st['actor'];
    $u = $st['units'][$id];
    $out = [];

    $feared = duel_has($st, $id, 'paura');
    $silenced = duel_has($st, $id, 'silenzio') || $feared;

    if (!duel_has($st, $id, 'disarmo')) {
        $def = duel_move_def($u, 'attacco');
        $targets = $feared ? [0] : duel_move_targets($st, $id, $def);
        if ($targets) {
            $out['attacco'] = $targets;
        }
    }

    if (!$silenced) {
        $skill = duel_move_def($u, 'abilita');
        if ($skill && $u['cd'] <= 0 && $st['ps'][$u['side']] >= duel_skill_cost($st, $id)) {
            $targets = duel_move_targets($st, $id, $skill);
            if ($targets) {
                $out['abilita'] = $targets;
            }
        }
        $ult = duel_move_def($u, 'ultimate');
        if ($ult && $u['charge'] >= duel_rules()['charge_max']) {
            $targets = duel_move_targets($st, $id, $ult);
            if ($targets) {
                $out['ultimate'] = $targets;
            }
        }
    }

    if (!duel_has($st, $id, 'guardia_rotta')) {
        $out['guardia'] = [0];
    }
    if (!$out) {
        $out['passa'] = [0];
    }
    return $out;
}

function duel_skill_cost(array $st, int $id): int
{
    $skill = duel_move_def($st['units'][$id], 'abilita');
    $cost = (int)($skill['cost'] ?? 1);
    if (duel_has($st, $id, 'concentrazione')) {
        $cost = max(0, $cost - (int)(duel_fx($st, $id, 'concentrazione')['v'] ?? 1));
    }
    return $cost;
}

/** Bersagli validi per una mossa: provocazione, furtivita' e fascino compresi. */
function duel_move_targets(array $st, int $id, ?array $def): array
{
    if (!$def) {
        return [];
    }
    $u = $st['units'][$id];
    $kind = $def['target'] ?? 'enemy';

    if ($kind === 'ally') {
        return duel_alive($st, $u['side']);
    }
    if ($kind === 'ally_other') {
        return array_values(array_filter(duel_alive($st, $u['side']), static fn($a) => $a !== $id)) ?: [$id];
    }
    if ($kind !== 'enemy') {
        return [0]; // a tutti, a se', nessuno
    }

    $enemies = duel_alive($st, duel_enemy_side($u['side']));
    if (!$enemies) {
        return [];
    }
    $visible = array_values(array_filter($enemies, static fn($e) => !duel_has($st, $e, 'furtivita')));
    if (!$visible) {
        $visible = $enemies;
    }
    $taunt = array_values(array_filter($visible, static fn($e) => duel_has($st, $e, 'provocazione')));
    if ($taunt) {
        $visible = $taunt;
    }
    $charm = duel_fx($st, $id, 'fascino');
    if ($charm) {
        $left = array_values(array_filter($visible, static fn($e) => $e !== (int)$charm['src']));
        if ($left) {
            $visible = $left;
        }
    }
    return $visible;
}

// ─────────────────────────────────────────────────────────────────────────
// Applicare una mossa
// ─────────────────────────────────────────────────────────────────────────

/**
 * La mossa di chi e' di turno. Lancia DuelError se non e' possibile.
 * Restituisce [nuovo stato, eventi].
 */
function duel_apply(array $st, string $move, int $target = 0): array
{
    if ($st['over']) {
        throw new DuelError('La partita e\' finita.');
    }
    $legal = duel_legal($st);
    if (!isset($legal[$move])) {
        throw new DuelError('Mossa non disponibile.');
    }
    if (!in_array($target, $legal[$move], true)) {
        if ($legal[$move] === [0]) {
            $target = 0;
        } else {
            throw new DuelError('Bersaglio non valido.');
        }
    }

    $c = duel_ctx($st);
    $id = (int)$c['st']['actor'];
    $side = $c['st']['units'][$id]['side'];
    $rules = duel_rules();
    $gain = duel_rarity($c['st']['units'][$id]['rarity'])['charge'];

    duel_ev($c, ['t' => 'move', 'u' => $id, 'm' => $move, 'tg' => $target]);

    // Chi agisce torna in fondo all'ordine subito: cosi' un "Avanza" o una
    // Velocita' + messi a se' stesso durante la mossa contano.
    $c['st']['units'][$id]['av'] = $rules['av_gauge'] / duel_eff($c['st'], $id)['spd'];

    // Sanguinamento: fa male a chi agisce.
    $bleed = duel_fx($c['st'], $id, 'sanguinamento');
    if ($bleed && $move !== 'passa') {
        duel_take($c, $id, (int)($bleed['meta']['amt'] ?? 0), (int)$bleed['src'], ['dot' => 'sanguinamento']);
    }
    if (duel_has($c['st'], $id, 'furtivita')) {
        duel_remove_status($c, $id, 'furtivita', 'used');
    }

    if (!$c['st']['units'][$id]['ko']) {
        $act = ['src' => $id, 'move' => $move, 'target' => $target, 'dealt' => 0, 'crit' => false, 'killed' => [], 'hit' => []];

        switch ($move) {
            case 'guardia':
                $c['st']['units'][$id]['guard'] = true;
                duel_add_ps($c, $side, 1);
                if (!empty($c['st']['units'][$id]['kit']['guard_taunt'])) {
                    duel_put_status($c, $id, $id, 'provocazione', 0, 1, 1, ['fresh' => true]);
                }
                duel_add_charge($c, $id, (int)round($gain * $rules['charge_guard_factor'] / 100));
                break;

            case 'attacco':
                duel_add_ps($c, $side, 1);
                $def = duel_move_def($c['st']['units'][$id], 'attacco');
                if (duel_has($c['st'], $id, 'paura')) {
                    $enemies = duel_alive($c['st'], duel_enemy_side($side));
                    $target = $enemies[duel_rand_int($c, 0, count($enemies) - 1)];
                    $act['target'] = $target;
                } elseif (duel_has($c['st'], $id, 'confusione') && duel_roll($c, (float)duel_fx($c['st'], $id, 'confusione')['v'])) {
                    $allies = array_values(array_filter(duel_alive($c['st'], $side), static fn($a) => $a !== $id));
                    if ($allies) {
                        $target = $allies[duel_rand_int($c, 0, count($allies) - 1)];
                        $act['target'] = $target;
                        $act['confused'] = true;
                        duel_ev($c, ['t' => 'confused', 'u' => $id, 'tg' => $target]);
                    }
                }
                duel_run_effects($c, $id, $def['effects'] ?? [], $target, $act);
                duel_add_charge($c, $id, $gain);
                break;

            case 'abilita':
                $def = duel_move_def($c['st']['units'][$id], 'abilita');
                $cost = duel_skill_cost($c['st'], $id);
                if (duel_has($c['st'], $id, 'concentrazione')) {
                    duel_remove_status($c, $id, 'concentrazione', 'used');
                }
                duel_add_ps($c, $side, -$cost);
                $c['st']['units'][$id]['cd'] = (int)($def['cd'] ?? 0);
                duel_shock($c, $id);
                if (!$c['st']['units'][$id]['ko']) {
                    duel_run_effects($c, $id, $def['effects'] ?? [], $target, $act);
                    duel_add_charge($c, $id, $gain);
                }
                break;

            case 'ultimate':
                $def = duel_move_def($c['st']['units'][$id], 'ultimate');
                $c['st']['units'][$id]['charge'] = 0;
                duel_ev($c, ['t' => 'charge', 'u' => $id, 'now' => 0]);
                duel_shock($c, $id);
                if (!$c['st']['units'][$id]['ko']) {
                    duel_run_effects($c, $id, $def['effects'] ?? [], $target, $act);
                }
                break;

            case 'passa':
                break;
        }

        if (in_array($move, ['attacco', 'abilita', 'ultimate'], true) && !$c['st']['units'][$id]['ko']) {
            duel_trigger($c, $id, 'after_action', ['act' => $act]);
        }
        duel_run_counters($c);
    }

    duel_check_end($c);
    if (!$c['st']['over']) {
        duel_turn_end($c, $id);
        duel_check_end($c);
        if (!$c['st']['over']) {
            duel_next_turn($c);
        }
    }
    return duel_ctx_close($c);
}

/** Elettrizzato: usare abilita' o ultimate fa male e toglie PS. */
function duel_shock(array &$c, int $id): void
{
    $fx = duel_fx($c['st'], $id, 'elettrizzato');
    if (!$fx) {
        return;
    }
    $side = $c['st']['units'][$id]['side'];
    duel_take($c, $id, (int)($fx['meta']['amt'] ?? 0), (int)$fx['src'], ['dot' => 'elettrizzato']);
    $loss = 1;
    if ($c['st']['terrain'] !== null && !empty(duel_terrains()[$c['st']['terrain']['k']]['shock_ps'])) {
        $loss += (int)duel_terrains()[$c['st']['terrain']['k']]['shock_ps'];
    }
    duel_add_ps($c, $side, -$loss);
}

// ─────────────────────────────────────────────────────────────────────────
// Effetti
// ─────────────────────────────────────────────────────────────────────────

/**
 * Esegue gli effetti di una mossa o di una passiva in ordine.
 * $act tiene il danno fatto, i critici e i KO di questa azione: servono alle
 * condizioni ('crit', 'killed') e alle cure "del danno fatto".
 */
function duel_run_effects(array &$c, int $src, array $effects, int $target, array &$act): void
{
    foreach ($effects as $eff) {
        if ($c['st']['over'] || $c['st']['units'][$src]['ko'] && empty($eff['even_ko'])) {
            return;
        }
        if (isset($eff['if']) && !duel_cond($c, $src, $eff['if'], $target, $act)) {
            continue;
        }
        $to = duel_targets($c, $src, $eff['to'] ?? 'target', $target, $act);
        $single = ($eff['to'] ?? 'target') === 'target' && ($act['single'] ?? true);

        switch ($eff['e'] ?? '') {
            case 'damage':
                $hits = max(1, (int)($eff['hits'] ?? 1));
                foreach ($to as $dst) {
                    for ($h = 0; $h < $hits; $h++) {
                        if ($c['st']['units'][$dst]['ko'] || $c['st']['over']) {
                            break;
                        }
                        duel_hit($c, $src, $dst, $eff, $act, $single);
                    }
                }
                break;
            case 'heal':
                foreach ($to as $dst) {
                    $of = $eff['of'] ?? 'src_max';
                    $base = $of === 'damage' ? $act['dealt']
                        : ($of === 'target_max' ? $c['st']['units'][$dst]['max']['hp'] : $c['st']['units'][$src]['max']['hp']);
                    duel_heal($c, $src, $dst, (int)round($base * ($eff['pct'] ?? 0) / 100), $eff['name'] ?? '');
                }
                break;
            case 'shield':
                foreach ($to as $dst) {
                    $of = $eff['of'] ?? 'src_max';
                    $base = $of === 'target_max' ? $c['st']['units'][$dst]['max']['hp'] : $c['st']['units'][$src]['max']['hp'];
                    duel_add_shield($c, $src, $dst, (int)round($base * ($eff['pct'] ?? 0) / 100));
                }
                break;
            case 'apply':
                foreach ($to as $dst) {
                    $k = (string)$eff['k'];
                    $base = duel_status($k);
                    if (!$base) {
                        continue;
                    }
                    duel_put_status($c, $src, $dst, $k, (float)($eff['v'] ?? $base['v']), (int)($eff['t'] ?? $base['t']), (int)($eff['st'] ?? 1), [
                        'chance' => (float)($eff['chance'] ?? 100),
                    ]);
                }
                break;
            case 'guardian':
                foreach ($to as $dst) {
                    if ($dst !== $src) {
                        duel_put_status($c, $src, $dst, 'guardiano', (float)($eff['v'] ?? 40), (int)($eff['t'] ?? 2), 1, []);
                    }
                }
                break;
            case 'cleanse':
                foreach ($to as $dst) {
                    duel_cleanse($c, $dst, (int)($eff['n'] ?? 99), true);
                }
                break;
            case 'dispel':
                foreach ($to as $dst) {
                    duel_cleanse($c, $dst, (int)($eff['n'] ?? 99), false);
                }
                break;
            case 'steal_buff':
                foreach ($to as $dst) {
                    duel_steal_buff($c, $src, $dst);
                }
                break;
            case 'spread':
                foreach ($to as $dst) {
                    duel_spread($c, $src, $dst);
                }
                break;
            case 'consume':
                foreach ($to as $dst) {
                    duel_detonate($c, $src, $dst, (array)($eff['k'] ?? ['veleno', 'bruciatura']));
                }
                break;
            case 'ps':
                $side = $c['st']['units'][$src]['side'];
                $n = (int)($eff['n'] ?? 1);
                if (($eff['side'] ?? 'own') === 'steal') {
                    $enemy = duel_enemy_side($side);
                    $taken = min($n, $c['st']['ps'][$enemy]);
                    duel_add_ps($c, $enemy, -$taken);
                    duel_add_ps($c, $side, $taken);
                } elseif (($eff['side'] ?? 'own') === 'enemy') {
                    duel_add_ps($c, duel_enemy_side($side), $n);
                } else {
                    duel_add_ps($c, $side, $n);
                }
                break;
            case 'charge':
                foreach ($to as $dst) {
                    duel_add_charge($c, $dst, (int)($eff['n'] ?? 0));
                }
                break;
            case 'advance':
                foreach ($to as $dst) {
                    duel_shift_av($c, $dst, (float)($eff['pct'] ?? 0));
                }
                break;
            case 'delay':
                foreach ($to as $dst) {
                    if (duel_cond_chance($c, $src, $dst, $eff)) {
                        duel_shift_av($c, $dst, -(float)($eff['pct'] ?? 0));
                    }
                }
                break;
            case 'extra_turn':
                $dst = $to[0] ?? $src;
                if (!$c['st']['units'][$dst]['ko']) {
                    $c['st']['extra'] = $dst;
                    duel_ev($c, ['t' => 'extra_given', 'u' => $dst]);
                }
                break;
            case 'terrain':
                duel_set_terrain($c, $src, (string)$eff['k'], (int)($eff['g'] ?? 0));
                break;
            case 'form':
                duel_set_form($c, $src, (string)$eff['k'], (int)($eff['t'] ?? 2));
                break;
            case 'stacks':
                duel_add_stacks($c, $src, (int)($eff['n'] ?? 1));
                break;
            case 'revive':
                duel_revive_ally($c, $src, (float)($eff['pct'] ?? 25));
                break;
            case 'remove':
                foreach ($to as $dst) {
                    duel_remove_status($c, $dst, (string)$eff['k'], 'removed');
                }
                break;
        }
    }
}

/** A chi va un effetto. */
function duel_targets(array &$c, int $src, string $to, int $target, array $act): array
{
    $st = $c['st'];
    $side = $st['units'][$src]['side'];
    $enemy = duel_enemy_side($side);
    switch ($to) {
        case 'self':
            return [$src];
        case 'target':
            if ($target > 0 && !$st['units'][$target]['ko']) {
                return [$target];
            }
            // Bersaglio caduto o mossa senza scelta: il primo nemico in piedi.
            $e = duel_alive($st, $enemy);
            return $e ? [$e[0]] : [];
        case 'allies':
            return duel_alive($st, $side);
        case 'allies_other':
            return array_values(array_filter(duel_alive($st, $side), static fn($a) => $a !== $src));
        case 'enemies':
            return duel_alive($st, $enemy);
        case 'other_enemies':
            return array_values(array_filter(duel_alive($st, $enemy), static fn($e) => $e !== $target));
        case 'random_enemy':
            $e = duel_alive($st, $enemy);
            return $e ? [$e[duel_rand_int($c, 0, count($e) - 1)]] : [];
        case 'lowest_ally':
            $best = null;
            foreach (duel_alive($st, $side) as $a) {
                if ($best === null || duel_hp_pct($st['units'][$a]) < duel_hp_pct($st['units'][$best])) {
                    $best = $a;
                }
            }
            return $best ? [$best] : [];
        case 'debuffed_ally':
            // L'alleato con piu' stati negativi (nessuno se stanno tutti bene).
            $best = null;
            $most = 0;
            foreach (duel_alive($st, $side) as $a) {
                $n = count(array_filter($st['units'][$a]['fx'], static fn($fx) => duel_status_is_negative($fx['k'])));
                if ($n > $most) {
                    $most = $n;
                    $best = $a;
                }
            }
            return $best ? [$best] : [];
        case 'attacker':
            $a = (int)($act['attacker'] ?? 0);
            return ($a > 0 && !$st['units'][$a]['ko']) ? [$a] : [];
        case 'trigger':
            $t = (int)($act['trigger_unit'] ?? 0);
            return ($t > 0 && !$st['units'][$t]['ko']) ? [$t] : [];
    }
    return [];
}

/** Condizioni degli effetti. */
function duel_cond(array &$c, int $src, array $cond, int $target, array $act): bool
{
    $st = $c['st'];
    foreach ($cond as $k => $v) {
        switch ($k) {
            case 'chance':
                if (!duel_roll($c, (float)$v)) {
                    return false;
                }
                break;
            case 'crit':
                if ((bool)$act['crit'] !== (bool)$v) {
                    return false;
                }
                break;
            case 'killed':
                if (((bool)$act['killed']) !== (bool)$v) {
                    return false;
                }
                break;
            case 'target_has':
                if ($target <= 0 || !duel_target_has($st, $target, (string)$v)) {
                    return false;
                }
                break;
            case 'target_hp_below':
                if ($target <= 0 || duel_hp_pct($st['units'][$target]) >= (float)$v) {
                    return false;
                }
                break;
            case 'target_stacks':
                // ['veleno', 3]: almeno 3 cariche di Veleno sul bersaglio.
                $fx = $target > 0 ? duel_fx($st, $target, (string)$v[0]) : null;
                if (!$fx || (int)$fx['st'] < (int)$v[1]) {
                    return false;
                }
                break;
            case 'self_hp_below':
                if (duel_hp_pct($st['units'][$src]) >= (float)$v) {
                    return false;
                }
                break;
            case 'self_hp_above':
                if (duel_hp_pct($st['units'][$src]) <= (float)$v) {
                    return false;
                }
                break;
        }
    }
    return true;
}

/** 'neg' = qualunque stato negativo, 'cc' = un controllo, altrimenti la chiave. */
function duel_target_has(array $st, int $id, string $what): bool
{
    if ($what === 'neg') {
        return duel_has_negative($st, $id);
    }
    if ($what === 'cc') {
        foreach ($st['units'][$id]['fx'] as $fx) {
            if (duel_status_is_cc($fx['k'])) {
                return true;
            }
        }
        return false;
    }
    if ($what === 'buff') {
        foreach ($st['units'][$id]['fx'] as $fx) {
            if (!duel_status_is_negative($fx['k']) && $fx['k'] !== 'resistenza') {
                return true;
            }
        }
        return false;
    }
    return duel_has($st, $id, $what);
}

function duel_cond_chance(array &$c, int $src, int $dst, array $eff): bool
{
    return !isset($eff['chance']) || duel_roll($c, (float)$eff['chance']);
}

// ─────────────────────────────────────────────────────────────────────────
// Danno
// ─────────────────────────────────────────────────────────────────────────

/**
 * Un colpo. Ordine: formula, critico, interazioni (Frantumazione, Risveglio),
 * schivata, barriera, guardiano, scudo, vita; poi riflesso, rubavita,
 * contrattacco, caccia, carica.
 */
function duel_hit(array &$c, int $src, int $dst, array $eff, array &$act, bool $single): int
{
    $rules = duel_rules();
    $calc = duel_damage_calc($c['st'], $src, $dst, $eff, $act);
    $exposed = duel_has($c['st'], $dst, 'esposto');

    // Chi colpisce un nemico controllato prende carica (passiva dei Controller alti).
    if (duel_target_has($c['st'], $dst, 'cc')) {
        $side = $c['st']['units'][$src]['side'];
        foreach (duel_alive($c['st'], $side) as $ally) {
            $feed = (int)($c['st']['units'][$ally]['kit']['passive']['cc_feed'] ?? 0);
            if ($feed > 0) {
                duel_add_charge($c, $src, $feed);
                break;
            }
        }
    }

    // Critico. Alcune passive rendono critico il primo colpo della partita.
    $forced = !empty($c['st']['units'][$src]['kit']['passive']['first_crit']) && empty($c['st']['units'][$src]['once']['first_hit']);
    $c['st']['units'][$src]['once']['first_hit'] = true;
    $crit = $forced || duel_roll($c, $calc['crit_chance']);
    $dmg = $crit ? $calc['crit'] : $calc['base'];
    $dmg *= duel_rand_int($c, $rules['variance_min'], $rules['variance_max']) / 100;

    // Interazioni con gli stati del bersaglio.
    $inter = duel_interactions();
    $tags = [];
    if (duel_has($c['st'], $dst, 'congelamento')) {
        $dmg *= 1 + $inter['frantumazione']['bonus'] / 100;
        duel_remove_status($c, $dst, 'congelamento', 'shatter');
        duel_add_charge($c, $dst, -$inter['frantumazione']['charge_loss']);
        $tags[] = 'frantumazione';
    }
    if (duel_has($c['st'], $dst, 'sonno')) {
        $dmg *= 1 + $inter['risveglio']['bonus'] / 100;
        duel_remove_status($c, $dst, 'sonno', 'wake');
        $tags[] = 'risveglio';
    }
    $dmg = max(1, (int)round($dmg));

    // Schivata (solo colpi singoli) e barriera.
    if ($single && !$exposed) {
        $dodge = duel_eff($c['st'], $dst)['dodge'];
        if ($dodge > 0 && duel_roll($c, $dodge)) {
            duel_ev($c, ['t' => 'hit', 'src' => $src, 'dst' => $dst, 'amount' => 0, 'miss' => true]);
            return 0;
        }
    }
    if (!$exposed && duel_has($c['st'], $dst, 'barriera')) {
        duel_remove_status($c, $dst, 'barriera', 'blocked');
        duel_ev($c, ['t' => 'hit', 'src' => $src, 'dst' => $dst, 'amount' => 0, 'blocked' => true]);
        return 0;
    }

    // Guardiano: un alleato prende una parte del colpo.
    $guard = duel_fx($c['st'], $dst, 'guardiano');
    if ($guard && (int)$guard['src'] !== $dst && !$c['st']['units'][(int)$guard['src']]['ko']) {
        $share = (int)round($dmg * $guard['v'] / 100);
        $dmg -= $share;
        if ($share > 0) {
            $g = (int)$guard['src'];
            duel_ev($c, ['t' => 'guarded', 'u' => $g, 'for' => $dst, 'amount' => $share]);
            $took = duel_take($c, $g, $share, $src, ['from_hit' => true]);
            duel_reflect($c, $g, $src, $took);
        }
    }

    $dealt = duel_take($c, $dst, $dmg, $src, ['pierce' => !empty($eff['pierce_shield']), 'from_hit' => true]);
    $act['dealt'] += $dealt;
    $act['crit'] = $act['crit'] || $crit;
    $act['hit'][] = $dst;
    $c['st']['units'][$src]['stats']['dealt'] += $dealt;

    $ev = ['t' => 'hit', 'src' => $src, 'dst' => $dst, 'amount' => $dealt, 'crit' => $crit];
    if ($tags) {
        $ev['tags'] = $tags;
    }
    duel_ev($c, $ev);

    if ($c['st']['units'][$dst]['ko']) {
        $act['killed'][] = $dst;
    } else {
        duel_add_charge($c, $dst, $rules['charge_on_hit']);
        // Maledizione: conserva una parte del danno.
        foreach ($c['st']['units'][$dst]['fx'] as $i => $fx) {
            if ($fx['k'] === 'maledizione') {
                $c['st']['units'][$dst]['fx'][$i]['meta']['pool'] = ($fx['meta']['pool'] ?? 0) + $dealt * $fx['v'] / 100;
            }
        }
        // Cariche che si azzerano quando si viene colpiti (lo Stile di Dante).
        $stk = $c['st']['units'][$dst]['kit']['stacks'] ?? null;
        if ($stk && !empty($stk['reset_on_damaged']) && $c['st']['units'][$dst]['stk'] > 0 && $dealt > 0) {
            $c['st']['units'][$dst]['stk'] = 0;
            duel_ev($c, ['t' => 'stacks', 'u' => $dst, 'now' => 0]);
        }
        duel_trigger($c, $dst, 'on_damaged', ['attacker' => $src, 'amount' => $dealt]);
        // Contrattacco (uno per azione).
        $counter = duel_fx($c['st'], $dst, 'contrattacco');
        $chance = $counter ? (float)$counter['v'] : (float)($c['st']['units'][$dst]['kit']['passive']['counter'] ?? 0);
        if ($chance > 0 && $src !== $dst && $c['st']['units'][$dst]['side'] !== $c['st']['units'][$src]['side']
            && empty($act['is_counter']) && !isset($c['counters'][$dst]) && duel_roll($c, $chance)) {
            $c['counters'][$dst] = $src;
        }
    }

    duel_reflect($c, $dst, $src, $dealt);

    // Rubavita.
    $ls = duel_eff($c['st'], $src)['lifesteal'];
    if ($ls > 0 && $dealt > 0 && !$c['st']['units'][$src]['ko']) {
        duel_heal($c, $src, $src, (int)round($dealt * $ls / 100), 'rubavita');
    }

    // Caccia: Taglia + Marchio.
    if (!$c['st']['units'][$dst]['ko'] && duel_has($c['st'], $dst, 'taglia') && duel_has($c['st'], $dst, 'marchio')) {
        duel_add_ps($c, $c['st']['units'][$src]['side'], (int)$inter['caccia']['ps']);
    }

    // Catena: abilita' o ultimate su un Elettrizzato.
    if (in_array($act['move'] ?? '', ['abilita', 'ultimate'], true) && empty($act['chained'])
        && duel_has($c['st'], $dst, 'elettrizzato') && $dealt > 0) {
        $others = array_values(array_filter(duel_alive($c['st'], $c['st']['units'][$dst]['side']), static fn($e) => $e !== $dst));
        if ($others) {
            $act['chained'] = true;
            $next = $others[duel_rand_int($c, 0, count($others) - 1)];
            $bounce = (int)round($dealt * $inter['catena']['pct'] / 100);
            duel_ev($c, ['t' => 'chain', 'src' => $dst, 'dst' => $next, 'amount' => $bounce]);
            duel_take($c, $next, $bounce, $src, ['from_hit' => true]);
            if (!$c['st']['units'][$next]['ko']) {
                duel_put_status($c, $src, $next, 'elettrizzato', duel_status('elettrizzato')['v'], 1, 1, ['chance' => 100]);
            }
        }
    }

    if ($crit && !$c['st']['units'][$src]['ko']) {
        duel_trigger($c, $src, 'on_crit', ['target' => $dst]);
    }
    return $dealt;
}

/**
 * La formula senza caso: danno normale, danno critico e probabilita' di
 * critico. La usano il colpo vero, l'anteprima e il bot.
 */
function duel_damage_calc(array $st, int $src, int $dst, array $eff, array $act = []): array
{
    $rules = duel_rules();
    $S = duel_eff($st, $src);
    $D = duel_eff($st, $dst);
    $u = $st['units'][$src];
    $t = $st['units'][$dst];

    // Base: % di una statistica o un mix (il Tank picchia con ATK e DEF).
    if (isset($eff['mix'])) {
        $base = 0.0;
        foreach ($eff['mix'] as $stat => $pct) {
            $base += ($stat === 'def' ? $S['def'] : ($stat === 'hp' ? $u['max']['hp'] : $S['atk'])) * $pct / 100;
        }
    } elseif (($eff['stat'] ?? 'atk') === 'target_max_hp') {
        $base = $t['max']['hp'] * ($eff['pct'] ?? 0) / 100;
    } else {
        $base = $S['atk'] * ($eff['pct'] ?? 100) / 100;
    }

    $pen = min(80, $S['pen'] + ($eff['pen'] ?? 0));
    $defFactor = !empty($eff['true']) ? 1.0 : $rules['def_k'] / ($rules['def_k'] + $D['def'] * (1 - $pen / 100));

    // Bonus di chi colpisce.
    $bonus = $S['dmg_pct'];
    foreach ($u['kit']['passive']['vs'] ?? [] as $vs) {
        if (isset($vs['if']['target_has']) && duel_target_has($st, $dst, (string)$vs['if']['target_has'])) {
            $bonus += $vs['dmg_pct'] ?? 0;
        } elseif (isset($vs['if']['target_hp_below']) && duel_hp_pct($t) < $vs['if']['target_hp_below']) {
            $bonus += $vs['dmg_pct'] ?? 0;
        }
    }
    foreach ($eff['bonus'] ?? [] as $b) {
        $ok = true;
        foreach ($b['if'] ?? [] as $k => $v) {
            if ($k === 'target_has' && !duel_target_has($st, $dst, (string)$v)) {
                $ok = false;
            } elseif ($k === 'target_hp_below' && duel_hp_pct($t) >= $v) {
                $ok = false;
            } elseif ($k === 'self_hp_below' && duel_hp_pct($u) >= $v) {
                $ok = false;
            }
        }
        if ($ok) {
            $bonus += $b['pct'] ?? 0;
        }
    }

    // Chi subisce: marchio, guardia, riduzioni.
    $exposed = duel_has($st, $dst, 'esposto');
    $taken = $D['taken_pct'];
    $red = 0.0;
    if (!$exposed) {
        $red = $D['red_pct'];
        if (in_array($act['move'] ?? '', ['abilita', 'ultimate'], true)) {
            $red += $D['red_skill_pct'];
        }
        if ($t['guard']) {
            $red += $rules['guard_reduction'];
        }
        $red = max(0.0, min((float)$rules['reduction_cap'], $red));
    }

    $fatigue = duel_fatigue($st)['dmg'];
    $plain = $base * $defFactor * (1 + $bonus / 100) * (1 + $taken / 100) * (1 - $red / 100) * (1 + $fatigue / 100);

    $critChance = max(0, min($rules['crit_cap'], $S['crit'] + ($eff['crit'] ?? 0)));
    $critMult = ($S['cdmg'] + ($eff['cdmg'] ?? 0)) / 100;
    if (duel_has($st, $dst, 'vulnerabile')) {
        $critMult += (duel_fx($st, $dst, 'vulnerabile')['v'] ?? 50) / 100;
    }

    return ['base' => $plain, 'crit' => $plain * $critMult, 'crit_chance' => $critChance];
}

/**
 * Danno a una carta: scudo, vita, Indistruttibile, colpo fatale delle
 * passive, KO. Restituisce il danno arrivato (scudo + vita).
 */
function duel_take(array &$c, int $id, int $amount, int $src, array $opts = []): int
{
    if ($amount <= 0 || $c['st']['units'][$id]['ko']) {
        return 0;
    }
    $u = &$c['st']['units'][$id];
    $left = $amount;
    $absorbed = 0;
    if (empty($opts['pierce']) && $u['shield'] > 0) {
        $absorbed = min($u['shield'], $left);
        $u['shield'] -= $absorbed;
        $left -= $absorbed;
    }
    $lost = min($u['hp'], $left);
    $u['hp'] -= $lost;
    unset($u);

    if (!empty($opts['dot'])) {
        duel_ev($c, ['t' => 'dot', 'u' => $id, 'k' => $opts['dot'], 'amount' => $amount]);
    }

    if ($c['st']['units'][$id]['hp'] <= 0) {
        if (duel_has($c['st'], $id, 'indistruttibile')) {
            $c['st']['units'][$id]['hp'] = 1;
            duel_ev($c, ['t' => 'undying', 'u' => $id]);
        } else {
            $fatal = $c['st']['units'][$id]['kit']['passive']['fatal'] ?? null;
            if ($fatal && empty($c['st']['units'][$id]['once']['fatal'])) {
                $c['st']['units'][$id]['once']['fatal'] = true;
                $c['st']['units'][$id]['hp'] = 1;
                duel_ev($c, ['t' => 'passive', 'u' => $id, 'name' => $c['st']['units'][$id]['kit']['passive']['name'] ?? '', 'why' => 'fatal']);
                $act = ['src' => $id, 'move' => 'passive', 'target' => $id, 'dealt' => 0, 'crit' => false, 'killed' => [], 'hit' => []];
                duel_run_effects($c, $id, $fatal, $id, $act);
            } else {
                duel_ko($c, $id, $src);
            }
        }
    } else {
        duel_check_thresholds($c, $id);
    }

    $got = $absorbed + $lost;
    $c['st']['units'][$id]['stats']['taken'] += $got;
    return $got;
}

function duel_reflect(array &$c, int $from, int $to, int $dealt): void
{
    if ($dealt <= 0 || $from === $to || $c['st']['units'][$from]['ko'] || $c['st']['units'][$to]['ko']) {
        return;
    }
    $r = duel_eff($c['st'], $from)['reflect'];
    if ($r <= 0) {
        return;
    }
    $back = (int)round($dealt * $r / 100);
    if ($back > 0) {
        duel_ev($c, ['t' => 'reflect', 'src' => $from, 'dst' => $to, 'amount' => $back]);
        duel_take($c, $to, $back, $from, ['pierce' => false]);
    }
}

/** Soglie di vita delle passive ("la prima volta sotto il 30%"). */
function duel_check_thresholds(array &$c, int $id): void
{
    $u = $c['st']['units'][$id];
    $pct = duel_hp_pct($u);
    foreach ($u['kit']['passive']['triggers'] ?? [] as $i => $tr) {
        if (($tr['when'] ?? '') === 'self_low' && $pct < ($tr['below'] ?? 30) && empty($u['once']['low' . $i])) {
            $c['st']['units'][$id]['once']['low' . $i] = true;
            duel_fire($c, $id, $tr, ['trigger_unit' => $id]);
        }
    }
    // Passive degli alleati: "quando un alleato scende sotto il X%".
    foreach ($c['st']['units'] as $aid => $ally) {
        if ($ally['ko'] || $ally['side'] !== $u['side']) {
            continue;
        }
        foreach ($ally['kit']['passive']['triggers'] ?? [] as $i => $tr) {
            if (($tr['when'] ?? '') === 'ally_low' && $pct < ($tr['below'] ?? 30)) {
                $key = 'ally' . $i . '_' . $id;
                if (empty($c['st']['units'][$aid]['once'][$key]) && (empty($tr['once']) || empty($c['st']['units'][$aid]['once']['ally' . $i]))) {
                    $c['st']['units'][$aid]['once'][$key] = true;
                    $c['st']['units'][$aid]['once']['ally' . $i] = true;
                    duel_fire($c, (int)$aid, $tr, ['trigger_unit' => $id]);
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────
// Cure, scudi, risorse
// ─────────────────────────────────────────────────────────────────────────

function duel_heal(array &$c, int $src, int $dst, int $amount, string $why = ''): int
{
    $u = $c['st']['units'][$dst];
    if ($amount <= 0 || $u['ko']) {
        return 0;
    }
    $out = $c['st']['units'][$src]['ko'] ? 0 : duel_eff($c['st'], $src)['heal_out_pct'];
    $in = duel_eff($c['st'], $dst)['heal_in_pct'];
    $amount = (int)round($amount * (1 + $out / 100) * (1 + $in / 100) * (1 - duel_fatigue($c['st'])['heal'] / 100));
    $real = min($amount, $u['max']['hp'] - $u['hp']);
    $c['st']['units'][$dst]['hp'] += max(0, $real);

    // Cure in eccesso che diventano scudo (passiva degli Healer alti).
    $over = $amount - max(0, $real);
    $conv = (float)($c['st']['units'][$src]['kit']['passive']['overheal_shield'] ?? 0);
    if ($over > 0 && $conv > 0) {
        duel_add_shield($c, $src, $dst, (int)min($over, $u['max']['hp'] * $conv / 100));
    }
    if ($real > 0) {
        $c['st']['units'][$src]['stats']['healed'] += $real;
        duel_ev($c, ['t' => 'heal', 'src' => $src, 'u' => $dst, 'amount' => $real, 'why' => $why]);
    }
    return max(0, $real);
}

function duel_add_shield(array &$c, int $src, int $dst, int $amount): void
{
    $rules = duel_rules();
    $u = $c['st']['units'][$dst];
    if ($amount <= 0 || $u['ko'] || duel_has($c['st'], $dst, 'guardia_rotta')) {
        return;
    }
    $ferita = duel_fx($c['st'], $dst, 'ferita');
    if ($ferita) {
        $amount = (int)round($amount * (1 - $ferita['v'] / 100));
    }
    $amount = (int)round($amount * (1 - duel_fatigue($c['st'])['heal'] / 100));
    $cap = (int)round($u['max']['hp'] * $rules['shield_cap'] / 100);
    $new = min($cap, $u['shield'] + $amount);
    if ($new > $u['shield']) {
        $c['st']['units'][$dst]['shield'] = $new;
        duel_ev($c, ['t' => 'shield', 'src' => $src, 'u' => $dst, 'amount' => $new - $u['shield'], 'now' => $new]);
    }
}

function duel_add_ps(array &$c, int $side, int $delta): void
{
    if ($delta === 0) {
        return;
    }
    $max = duel_rules()['ps_max'];
    $before = $c['st']['ps'][$side];
    $c['st']['ps'][$side] = max(0, min($max, $before + $delta));
    if ($c['st']['ps'][$side] !== $before) {
        duel_ev($c, ['t' => 'ps', 'side' => $side, 'delta' => $c['st']['ps'][$side] - $before, 'now' => $c['st']['ps'][$side]]);
    }
}

function duel_add_charge(array &$c, int $id, int $delta): void
{
    if ($delta === 0 || $c['st']['units'][$id]['ko']) {
        return;
    }
    $max = duel_rules()['charge_max'];
    $before = $c['st']['units'][$id]['charge'];
    $c['st']['units'][$id]['charge'] = max(0, min($max, $before + $delta));
    if ($c['st']['units'][$id]['charge'] !== $before) {
        duel_ev($c, ['t' => 'charge', 'u' => $id, 'now' => $c['st']['units'][$id]['charge']]);
    }
}

function duel_add_stacks(array &$c, int $id, int $n): void
{
    $def = $c['st']['units'][$id]['kit']['stacks'] ?? null;
    if (!$def) {
        return;
    }
    $before = $c['st']['units'][$id]['stk'];
    $c['st']['units'][$id]['stk'] = max(0, min((int)($def['max'] ?? 5), $before + $n));
    if ($c['st']['units'][$id]['stk'] !== $before) {
        duel_ev($c, ['t' => 'stacks', 'u' => $id, 'now' => $c['st']['units'][$id]['stk']]);
    }
}

function duel_set_form(array &$c, int $id, string $key, int $turns): void
{
    if (!isset($c['st']['units'][$id]['kit']['forms'][$key])) {
        return;
    }
    $c['st']['units'][$id]['form'] = ['k' => $key, 't' => $turns + 1]; // +1: il turno in cui entra non conta
    duel_ev($c, ['t' => 'form', 'u' => $id, 'k' => $key, 'op' => 'start', 'turns' => $turns]);
}

function duel_set_terrain(array &$c, int $src, string $key, int $giri): void
{
    $def = duel_terrains()[$key] ?? null;
    if (!$def) {
        return;
    }
    $alive = count(duel_alive($c['st'], 0)) + count(duel_alive($c['st'], 1));
    $giri = $giri > 0 ? $giri : (int)$def['g'];
    $c['st']['terrain'] = ['k' => $key, 'src' => $src, 'side' => $c['st']['units'][$src]['side'], 'left' => $giri * $alive, 'round' => $alive, 'since' => 0];
    duel_ev($c, ['t' => 'terrain', 'k' => $key, 'op' => 'start', 'u' => $src, 'giri' => $giri]);
}

function duel_revive_ally(array &$c, int $src, float $pct): void
{
    $side = $c['st']['units'][$src]['side'];
    $pick = null;
    foreach ($c['st']['units'] as $id => $u) {
        if ($u['side'] === $side && $u['ko'] && ($pick === null || $u['ko_turn'] > $c['st']['units'][$pick]['ko_turn'])) {
            $pick = (int)$id;
        }
    }
    if ($pick !== null) {
        duel_revive($c, $pick, $pct);
    }
}

function duel_revive(array &$c, int $id, float $pct): void
{
    $rules = duel_rules();
    $u = &$c['st']['units'][$id];
    $u['ko'] = false;
    $u['hp'] = max(1, (int)round($u['max']['hp'] * $pct / 100));
    $u['shield'] = 0;
    $u['fx'] = [];
    $u['charge'] = 0;
    $u['av'] = $rules['av_gauge'] / max(1.0, (float)$u['max']['spd']);
    $hp = $u['hp'];
    unset($u);
    duel_ev($c, ['t' => 'revive', 'u' => $id, 'hp' => $hp]);
}

// ─────────────────────────────────────────────────────────────────────────
// Stati
// ─────────────────────────────────────────────────────────────────────────

/**
 * Mette uno stato. Gli stati negativi passano per Immunita', Resistenza al
 * controllo, blocchi delle passive e probabilita' (base × (1 + Precisione −
 * Tenacia)). $opts: chance, fresh, terrain.
 */
function duel_put_status(array &$c, int $src, int $dst, string $k, float $v, int $t, int $stacks, array $opts): bool
{
    $rules = duel_rules();
    $def = duel_status($k);
    if (!$def || $c['st']['units'][$dst]['ko']) {
        return false;
    }

    // Terreni che bloccano uno stato.
    if ($c['st']['terrain'] !== null && in_array($k, duel_terrains()[$c['st']['terrain']['k']]['blocks'] ?? [], true)) {
        return false;
    }

    $enemyApplied = $c['st']['units'][$src]['side'] !== $c['st']['units'][$dst]['side'];
    if ($def['neg'] && $enemyApplied && empty($opts['terrain'])) {
        $resist = null;
        if (duel_has($c['st'], $dst, 'immunita')) {
            $resist = 'immunita';
        } elseif ($def['cc'] && duel_has($c['st'], $dst, 'resistenza')) {
            $resist = 'resistenza';
        } else {
            $block = (int)($c['st']['units'][$dst]['kit']['passive']['block_neg'] ?? 0);
            $used = (int)($c['st']['units'][$dst]['once']['blocked'] ?? 0);
            if ($block > $used) {
                $c['st']['units'][$dst]['once']['blocked'] = $used + 1;
                $resist = 'passiva';
            } else {
                $prec = duel_eff($c['st'], $src)['prec'];
                $ten = duel_eff($c['st'], $dst)['ten'];
                $chance = ($opts['chance'] ?? 100) * (1 + ($prec - $ten) / 100);
                if (!duel_roll($c, max(0, min(100, $chance)))) {
                    $resist = 'tenacia';
                }
            }
        }
        if ($resist !== null) {
            duel_ev($c, ['t' => 'status', 'u' => $dst, 'k' => $k, 'op' => 'resist', 'why' => $resist, 'src' => $src]);
            return false;
        }
        if ($def['cc'] && $t > 1 && duel_eff($c['st'], $dst)['ten'] > $rules['tenacity_cut']) {
            $t--;
        }
    }
    if ($k === 'furtivita' && $c['st']['terrain'] !== null && !empty(duel_terrains()[$c['st']['terrain']['k']]['stealth_bonus'])) {
        $t += (int)duel_terrains()[$c['st']['terrain']['k']]['stealth_bonus'];
    }

    $oldSpd = duel_eff($c['st'], $dst)['spd'];
    $fresh = !empty($opts['fresh']) || $c['st']['actor'] === $dst;
    $meta = [];
    if ($k === 'sanguinamento' || $k === 'elettrizzato') {
        $meta['amt'] = (int)round(duel_eff($c['st'], $src)['atk'] * $v / 100);
    }
    if ($k === 'corrosione') {
        $meta['cur'] = $v;
    }
    if ($k === 'maledizione') {
        $meta['pool'] = 0;
    }

    $fxList = &$c['st']['units'][$dst]['fx'];
    $found = null;
    foreach ($fxList as $i => $fx) {
        if ($fx['k'] === $k) {
            $found = $i;
            break;
        }
    }
    $op = 'add';
    if ($found !== null) {
        if ($def['stack'] === 'once') {
            unset($fxList);
            return false;
        }
        $op = 'refresh';
        $fx = $fxList[$found];
        if ($def['stack'] === 'stacks') {
            $fx['st'] = min((int)$def['max'], (int)$fx['st'] + max(1, $stacks));
        }
        $fx['v'] = max((float)$fx['v'], $v);
        $fx['t'] = max((int)$fx['t'], $t);
        $fx['src'] = $src;
        $fx['fresh'] = $fresh || !empty($fx['fresh']);
        if ($meta) {
            $fx['meta'] = array_merge($fx['meta'] ?? [], array_diff_key($meta, ['pool' => 1, 'cur' => 1]));
        }
        $fxList[$found] = $fx;
        $now = $fx;
    } else {
        $c['st']['seq']++;
        $now = [
            'id' => $c['st']['seq'], 'k' => $k, 'v' => $v, 't' => $t,
            'st' => $def['stack'] === 'stacks' ? min((int)$def['max'], max(1, $stacks)) : 1,
            'src' => $src, 'fresh' => $fresh, 'meta' => $meta,
        ];
        $fxList[] = $now;
    }
    unset($fxList);

    if ($k === 'guardia_rotta' && $c['st']['units'][$dst]['shield'] > 0) {
        $c['st']['units'][$dst]['shield'] = 0;
        duel_ev($c, ['t' => 'shield', 'u' => $dst, 'amount' => 0, 'now' => 0, 'broken' => true]);
    }

    duel_ev($c, ['t' => 'status', 'u' => $dst, 'k' => $k, 'op' => $op, 'v' => $now['v'], 'turns' => $now['t'], 'st' => $now['st'], 'src' => $src]);

    if (in_array($k, ['vel_su', 'vel_giu'], true)) {
        duel_rescale_av($c, $dst, $oldSpd);
    }
    return true;
}

function duel_remove_status(array &$c, int $id, string $k, string $why): void
{
    $oldSpd = duel_eff($c['st'], $id)['spd'];
    $before = count($c['st']['units'][$id]['fx']);
    $c['st']['units'][$id]['fx'] = array_values(array_filter($c['st']['units'][$id]['fx'], static fn($fx) => $fx['k'] !== $k));
    if (count($c['st']['units'][$id]['fx']) !== $before) {
        duel_ev($c, ['t' => 'status', 'u' => $id, 'k' => $k, 'op' => 'remove', 'why' => $why]);
        if (duel_status_is_cc($k) && in_array($why, ['shatter', 'wake', 'cleanse'], true)) {
            duel_put_status($c, $id, $id, 'resistenza', 0, duel_rules()['cc_resist_turns'], 1, ['fresh' => true]);
        }
        if (in_array($k, ['vel_su', 'vel_giu'], true)) {
            duel_rescale_av($c, $id, $oldSpd);
        }
    }
}

/** Purificazione ($negative = true) o Dissolvi (false): toglie fino a $n stati. */
function duel_cleanse(array &$c, int $id, int $n, bool $negative): void
{
    $removed = 0;
    foreach ($c['st']['units'][$id]['fx'] as $fx) {
        if ($removed >= $n) {
            break;
        }
        $isNeg = duel_status_is_negative($fx['k']);
        if ($fx['k'] === 'resistenza' || $isNeg !== $negative) {
            continue;
        }
        duel_remove_status($c, $id, $fx['k'], $negative ? 'cleanse' : 'dispel');
        $removed++;
    }
}

function duel_steal_buff(array &$c, int $src, int $dst): void
{
    $best = null;
    foreach ($c['st']['units'][$dst]['fx'] as $fx) {
        if (!duel_status_is_negative($fx['k']) && $fx['k'] !== 'resistenza' && $fx['k'] !== 'rinascita'
            && ($best === null || $fx['v'] > $best['v'])) {
            $best = $fx;
        }
    }
    if ($best === null) {
        return;
    }
    duel_remove_status($c, $dst, $best['k'], 'stolen');
    duel_put_status($c, $src, $src, $best['k'], (float)$best['v'], max(1, (int)$best['t']), (int)$best['st'], ['fresh' => true]);
    duel_ev($c, ['t' => 'steal', 'src' => $src, 'dst' => $dst, 'k' => $best['k']]);
}

/** Contagio: copia gli stati negativi del bersaglio sugli altri nemici, un turno in meno. */
function duel_spread(array &$c, int $src, int $dst): void
{
    $side = $c['st']['units'][$dst]['side'];
    foreach ($c['st']['units'][$dst]['fx'] as $fx) {
        if (!duel_status_is_negative($fx['k']) || $fx['k'] === 'taglia') {
            continue;
        }
        foreach (duel_alive($c['st'], $side) as $other) {
            if ($other !== $dst && (int)$fx['t'] - 1 > 0) {
                duel_put_status($c, $src, $other, $fx['k'], (float)$fx['v'], (int)$fx['t'] - 1, (int)$fx['st'], ['chance' => 100]);
            }
        }
    }
}

/** Detonazione: il Veleno o la Bruciatura che restavano fanno subito tutto il danno. */
function duel_detonate(array &$c, int $src, int $dst, array $keys): void
{
    $mult = duel_interactions()['detonazione']['mult'] / 100;
    foreach ($keys as $k) {
        $fx = duel_fx($c['st'], $dst, $k);
        if (!$fx || $c['st']['units'][$dst]['ko']) {
            continue;
        }
        $max = $c['st']['units'][$dst]['max']['hp'];
        $per = $k === 'veleno' ? $max * $fx['v'] * $fx['st'] / 100 : $max * $fx['v'] / 100;
        $dmg = (int)round($per * max(1, (int)$fx['t']) * $mult);
        duel_remove_status($c, $dst, $k, 'detonate');
        duel_ev($c, ['t' => 'detonate', 'src' => $src, 'u' => $dst, 'k' => $k, 'amount' => $dmg]);
        duel_take($c, $dst, $dmg, $src, ['pierce' => $k === 'veleno']);
    }
}

// ─────────────────────────────────────────────────────────────────────────
// Passive
// ─────────────────────────────────────────────────────────────────────────

/** Fa scattare le passive di una carta per un momento della partita. */
function duel_trigger(array &$c, int $id, string $when, array $info): void
{
    if ($c['st']['units'][$id]['ko'] || $c['st']['over'] || $c['depth'] > 4) {
        return;
    }
    foreach ($c['st']['units'][$id]['kit']['passive']['triggers'] ?? [] as $i => $tr) {
        if (($tr['when'] ?? '') !== $when) {
            continue;
        }
        if (!empty($tr['once'])) {
            if (!empty($c['st']['units'][$id]['once']['tr' . $i])) {
                continue;
            }
        }
        if (($tr['every'] ?? 0) > 0) {
            $count = (int)($c['st']['units'][$id]['once']['cnt' . $i] ?? 0) + 1;
            $c['st']['units'][$id]['once']['cnt' . $i] = $count;
            if ($count % (int)$tr['every'] !== 0) {
                continue;
            }
        }
        if (!empty($tr['once'])) {
            $c['st']['units'][$id]['once']['tr' . $i] = true;
        }
        duel_fire($c, $id, $tr, $info);
    }
}

function duel_fire(array &$c, int $id, array $tr, array $info): void
{
    $c['depth']++;
    $target = (int)($info['target'] ?? $info['attacker'] ?? $info['trigger_unit'] ?? 0);
    $act = $info['act'] ?? ['src' => $id, 'move' => 'passive', 'target' => $target, 'dealt' => 0, 'crit' => false, 'killed' => [], 'hit' => []];
    $act['attacker'] = $info['attacker'] ?? null;
    $act['trigger_unit'] = $info['trigger_unit'] ?? null;
    $act['single'] = false;
    if (empty($tr['silent'])) {
        duel_ev($c, ['t' => 'passive', 'u' => $id, 'name' => $c['st']['units'][$id]['kit']['passive']['name'] ?? '']);
    }
    duel_run_effects($c, $id, $tr['effects'] ?? [], $target, $act);
    $c['depth']--;
}

/** Contrattacchi in coda: ognuno risponde con il suo Attacco. */
function duel_run_counters(array &$c): void
{
    $queue = $c['counters'];
    $c['counters'] = [];
    foreach ($queue as $who => $against) {
        if ($c['st']['over'] || $c['st']['units'][$who]['ko'] || $c['st']['units'][$against]['ko']) {
            continue;
        }
        $def = duel_move_def($c['st']['units'][$who], 'attacco');
        $act = ['src' => $who, 'move' => 'counter', 'target' => $against, 'dealt' => 0, 'crit' => false, 'killed' => [], 'hit' => [], 'is_counter' => true];
        duel_ev($c, ['t' => 'counter', 'u' => $who, 'tg' => $against]);
        $effects = array_values(array_filter($def['effects'] ?? [], static fn($e) => ($e['e'] ?? '') === 'damage'));
        duel_run_effects($c, $who, $effects, $against, $act);
    }
}

// ─────────────────────────────────────────────────────────────────────────
// KO e fine partita
// ─────────────────────────────────────────────────────────────────────────

function duel_ko(array &$c, int $id, int $killer): void
{
    $rules = duel_rules();
    $u = $c['st']['units'][$id];

    // Rinascita: torna in campo una volta.
    $reb = duel_fx($c['st'], $id, 'rinascita');
    if ($reb) {
        duel_revive($c, $id, (float)$reb['v']);
        return;
    }

    $bounty = duel_has($c['st'], $id, 'taglia');
    $c['st']['units'][$id]['ko'] = true;
    $c['st']['units'][$id]['ko_turn'] = $c['st']['turn'];
    $c['st']['units'][$id]['hp'] = 0;
    $c['st']['units'][$id]['shield'] = 0;
    $c['st']['units'][$id]['fx'] = [];
    $c['st']['units'][$id]['form'] = null;
    if ($c['st']['extra'] === $id) {
        $c['st']['extra'] = null;
    }
    duel_ev($c, ['t' => 'ko', 'u' => $id, 'by' => $killer]);

    if ($killer > 0 && $killer !== $id && !$c['st']['units'][$killer]['ko'] && $c['st']['units'][$killer]['side'] !== $u['side']) {
        $c['st']['units'][$killer]['stats']['kos']++;
        duel_add_charge($c, $killer, $rules['charge_on_kill']);
        if ($bounty) {
            $kside = $c['st']['units'][$killer]['side'];
            duel_add_ps($c, $kside, (int)duel_status('taglia')['v']);
            foreach (duel_alive($c['st'], $kside) as $ally) {
                duel_add_charge($c, $ally, 30);
            }
        }
        duel_trigger($c, $killer, 'on_kill', ['target' => $id]);
    }
    foreach (duel_alive($c['st'], $u['side']) as $ally) {
        duel_trigger($c, $ally, 'on_ally_ko', ['trigger_unit' => $id]);
    }
    duel_check_end($c);
}

function duel_check_end(array &$c): void
{
    if ($c['st']['over']) {
        return;
    }
    $a = duel_alive($c['st'], 0);
    $b = duel_alive($c['st'], 1);
    if ($a && $b) {
        return;
    }
    $c['st']['over'] = true;
    $c['st']['actor'] = null;
    $c['st']['winner'] = $a ? 0 : ($b ? 1 : null);
    $c['st']['reason'] = 'ko';
    duel_ev($c, ['t' => 'end', 'winner' => $c['st']['winner'], 'reason' => 'ko']);
}

/** Limite di turni: vince chi ha piu' vita in percentuale (somma delle tre carte). */
function duel_end_by_limit(array &$c): void
{
    $pct = [0.0, 0.0];
    foreach ($c['st']['units'] as $u) {
        $pct[$u['side']] += $u['ko'] ? 0 : duel_hp_pct($u);
    }
    $winner = abs($pct[0] - $pct[1]) < 0.01 ? null : ($pct[0] > $pct[1] ? 0 : 1);
    $c['st']['over'] = true;
    $c['st']['actor'] = null;
    $c['st']['winner'] = $winner;
    $c['st']['reason'] = 'limit';
    duel_ev($c, ['t' => 'end', 'winner' => $winner, 'reason' => 'limit']);
}

/** Abbandono o tempo scaduto troppe volte: perde $side. */
function duel_forfeit(array $st, int $side, string $reason = 'forfeit'): array
{
    $c = duel_ctx($st);
    if (!$c['st']['over']) {
        $c['st']['over'] = true;
        $c['st']['actor'] = null;
        $c['st']['winner'] = duel_enemy_side($side);
        $c['st']['reason'] = $reason;
        duel_ev($c, ['t' => 'end', 'winner' => $c['st']['winner'], 'reason' => $reason]);
    }
    return duel_ctx_close($c);
}

// ─────────────────────────────────────────────────────────────────────────
// Anteprima e stato per il browser
// ─────────────────────────────────────────────────────────────────────────

/**
 * Quanto fara' una mossa su un bersaglio, senza tirare il dado: danno minimo
 * e massimo senza e con critico, probabilita' di critico e di KO (stimata).
 */
function duel_preview(array $st, string $move, int $target): array
{
    $rules = duel_rules();
    $id = (int)$st['actor'];
    $def = duel_move_def($st['units'][$id], $move);
    $out = ['min' => 0, 'max' => 0, 'crit_min' => 0, 'crit_max' => 0, 'crit_chance' => 0, 'ko_chance' => 0];
    if (!$def || $target <= 0) {
        return $out;
    }
    $act = ['move' => $move];
    foreach ($def['effects'] ?? [] as $eff) {
        if (($eff['e'] ?? '') !== 'damage' || !in_array($eff['to'] ?? 'target', ['target', 'enemies'], true)) {
            continue;
        }
        $hits = max(1, (int)($eff['hits'] ?? 1));
        $calc = duel_damage_calc($st, $id, $target, $eff, $act);
        $out['min'] += (int)round($calc['base'] * $rules['variance_min'] / 100) * $hits;
        $out['max'] += (int)round($calc['base'] * $rules['variance_max'] / 100) * $hits;
        $out['crit_min'] += (int)round($calc['crit'] * $rules['variance_min'] / 100) * $hits;
        $out['crit_max'] += (int)round($calc['crit'] * $rules['variance_max'] / 100) * $hits;
        $out['crit_chance'] = max($out['crit_chance'], (int)round($calc['crit_chance']));
    }
    $t = $st['units'][$target];
    $life = $t['hp'] + $t['shield'];
    if ($out['min'] >= $life) {
        $out['ko_chance'] = 100;
    } elseif ($out['crit_min'] >= $life) {
        $out['ko_chance'] = $out['crit_chance'];
    }
    return $out;
}

/** Lo stato da mandare al browser: senza il dado. */
function duel_public_state(array $st): array
{
    unset($st['rng']);
    foreach ($st['units'] as $id => $u) {
        $st['units'][$id]['eff'] = array_map(static fn($v) => is_float($v) ? round($v, 1) : $v, duel_eff($st, (int)$id));
    }
    $st['timeline'] = duel_timeline($st, 8);
    $st['legal'] = duel_legal($st);
    return $st;
}
