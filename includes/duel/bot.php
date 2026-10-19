<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — il bot.
 *
 * Per ogni mossa possibile da' un punteggio guardando cosa farebbero i suoi
 * effetti sullo stato di adesso (danno atteso, KO, vita che manca agli
 * alleati, stati gia' presenti, PS) e sceglie la migliore. I livelli:
 * - facile: a volte (35%) sceglie a caso tra le mosse possibili
 * - normale: un po' di rumore sui punteggi
 * - difficile: sempre la mossa col punteggio piu' alto
 * Nella fase 6 arriva "incubo", che guarda anche la risposta dell'avversario.
 *
 * Non tocca lo stato e non usa il dado della partita: il caso dei livelli
 * bassi viene da un seme suo, cosi' le partite restano ripetibili.
 */

require_once __DIR__ . '/engine.php';

const DUEL_BOT_LEVELS = ['facile', 'normale', 'difficile'];

/** [mossa, bersaglio] per chi e' di turno. */
function duel_bot_choose(array $st, string $level = 'difficile', int $seed = 0): array
{
    $legal = duel_legal($st);
    if (!$legal) {
        return ['passa', 0];
    }
    $rnd = duel_rng_new($seed * 7919 + (int)$st['turn'] * 104729 + (int)$st['actor']);

    $options = [];
    foreach ($legal as $move => $targets) {
        foreach ($targets as $tg) {
            $options[] = [$move, (int)$tg, duel_bot_score($st, $move, (int)$tg)];
        }
    }

    if ($level === 'facile' && $rnd->getInt(0, 99) < 35) {
        $pick = $options[$rnd->getInt(0, count($options) - 1)];
        return [$pick[0], $pick[1]];
    }
    if ($level === 'normale') {
        foreach ($options as $i => $o) {
            $options[$i][2] += ($rnd->getInt(0, 300) - 150) / 1000;
        }
    }

    $best = $options[0];
    foreach ($options as $o) {
        if ($o[2] > $best[2]) {
            $best = $o;
        }
    }
    return [$best[0], $best[1]];
}

/** Punteggio di una mossa: piu' alto e' meglio. Scala: un KO vale circa 1. */
function duel_bot_score(array $st, string $move, int $target): float
{
    $id = (int)$st['actor'];
    $u = $st['units'][$id];
    $side = $u['side'];
    $rules = duel_rules();
    $ps = $st['ps'][$side];

    if ($move === 'passa') {
        return -1.0;
    }
    if ($move === 'guardia') {
        $score = 0.12;
        if (duel_hp_pct($u) < 35) {
            $score += 0.25;
        }
        if ($ps === 0) {
            $score += 0.15;
        }
        return $score;
    }

    $def = duel_move_def($u, $move);
    if (!$def) {
        return -1.0;
    }
    $score = 0.0;
    $act = ['move' => $move];

    foreach ($def['effects'] ?? [] as $eff) {
        $e = $eff['e'] ?? '';
        $to = duel_bot_targets($st, $id, $eff['to'] ?? 'target', $target);
        $p = isset($eff['chance']) ? max(0.1, min(1.0, $eff['chance'] / 100)) : 1.0;
        if (isset($eff['if'])) {
            $p *= 0.5; // condizioni: valgono meta'
        }

        switch ($e) {
            case 'damage':
                $hits = max(1, (int)($eff['hits'] ?? 1));
                foreach ($to as $t) {
                    $calc = duel_damage_calc($st, $id, $t, $eff, $act);
                    $cc = $calc['crit_chance'] / 100;
                    $exp = ($calc['base'] * (1 - $cc) + $calc['crit'] * $cc) * $hits;
                    $life = $st['units'][$t]['hp'] + $st['units'][$t]['shield'];
                    $score += min($exp, $life) / 700;
                    if ($exp >= $life) {
                        $score += 0.7;
                    } elseif ($calc['crit'] * $hits >= $life) {
                        $score += 0.7 * $cc;
                    }
                    $score += $st['units'][$t]['max']['atk'] / 4000; // colpire chi fa male
                }
                break;
            case 'heal':
                foreach ($to as $t) {
                    $tu = $st['units'][$t];
                    $base = ($eff['of'] ?? 'src_max') === 'target_max' ? $tu['max']['hp'] : $u['max']['hp'];
                    $amount = $base * ($eff['pct'] ?? 0) / 100;
                    $missing = $tu['max']['hp'] - $tu['hp'];
                    $score += min($amount, $missing) / 600 * 1.2;
                }
                break;
            case 'shield':
                $low = false;
                foreach (duel_alive($st, $side) as $a) {
                    $low = $low || duel_hp_pct($st['units'][$a]) < 60;
                }
                foreach ($to as $t) {
                    $base = ($eff['of'] ?? 'src_max') === 'target_max' ? $st['units'][$t]['max']['hp'] : $u['max']['hp'];
                    $score += $base * ($eff['pct'] ?? 0) / 100 / 700 * ($low ? 0.8 : 0.35);
                }
                break;
            case 'apply':
            case 'guardian':
                $k = $e === 'guardian' ? 'guardiano' : (string)$eff['k'];
                $neg = duel_status_is_negative($k);
                foreach ($to as $t) {
                    $mine = $st['units'][$t]['side'] === $side;
                    if ($neg === $mine && $t !== $id) {
                        continue; // non si mettono stati negativi agli alleati
                    }
                    $has = duel_has($st, $t, $k);
                    $val = duel_bot_status_value($st, $id, $t, $k, $eff);
                    $score += ($has ? $val * 0.3 : $val) * $p;
                }
                break;
            case 'revive':
                foreach ($st['units'] as $a) {
                    if ($a['side'] === $side && $a['ko']) {
                        $score += 1.1;
                        break;
                    }
                }
                break;
            case 'cleanse':
                foreach ($to as $t) {
                    foreach ($st['units'][$t]['fx'] as $fx) {
                        if (duel_status_is_negative($fx['k'])) {
                            $score += 0.15;
                        }
                    }
                }
                break;
            case 'ps':
                $score += 0.12 * (int)($eff['n'] ?? 1) * $p;
                break;
            case 'advance':
            case 'delay':
                $score += 0.1 * count($to) * $p;
                break;
            case 'extra_turn':
                $score += 0.35 * $p;
                break;
            case 'terrain':
                $score += 0.25;
                break;
            case 'form':
                $score += 0.5;
                break;
            case 'consume':
            case 'spread':
            case 'steal_buff':
            case 'dispel':
                $score += 0.15 * count($to);
                break;
        }
    }

    // Un PS vale circa quanto il danno in piu' che un'abilita' fa rispetto
    // all'Attacco (0,08 ≈ 55 di danno). Con 0,16 il bot non usava mai le
    // abilita' dei personaggi di rarita' bassa.
    if ($move === 'abilita') {
        $score -= 0.08 * duel_skill_cost($st, $id);
    }
    if ($move === 'attacco') {
        $score += $ps >= $rules['ps_max'] ? -0.04 : 0.08; // il PS che guadagna (o spreca, se sono pieni)
    }
    if ($move === 'ultimate') {
        $score *= 1.1;
    }
    return $score;
}

/**
 * Quanto vale mettere uno stato, nella stessa scala del danno (700 di danno
 * circa 1 punto): un potenziamento d'Attacco vale il danno in piu' che fara'
 * l'alleato, un indebolimento quello in piu' che fara' la squadra, un
 * controllo la minaccia che toglie.
 */
function duel_bot_status_value(array $st, int $src, int $t, string $k, array $eff): float
{
    $def = duel_status($k);
    if (!$def) {
        return 0.0;
    }
    $tu = $st['units'][$t];
    $v = (float)($eff['v'] ?? $def['v']);
    $turns = max(1, (int)($eff['t'] ?? $def['t']));
    $side = $st['units'][$src]['side'];
    $teamAtk = 0.0;
    foreach (duel_alive($st, $side) as $a) {
        $teamAtk += duel_eff($st, $a)['atk'];
    }
    $perAction = static fn(float $atk) => $atk * 1.4 * 0.6;

    if (in_array($k, ['immunita', 'resistenza'], true) || ($def['neg'] && (duel_has($st, $t, 'immunita') || ($def['cc'] && duel_has($st, $t, 'resistenza'))))) {
        return $def['neg'] ? 0.0 : 0.2;
    }
    switch ($k) {
        case 'atk_su':
            return $perAction(duel_eff($st, $t)['atk']) * $v / 100 * $turns / 700;
        case 'cdmg_su':
        case 'crit_su':
            return $perAction(duel_eff($st, $t)['atk']) * 0.25 * $v / 100 * $turns / 700;
        case 'def_giu':
        case 'marchio':
            return $perAction($teamAtk) / 3 * $v / 100 * $turns / 700;
        case 'vulnerabile':
            return 0.15;
        case 'veleno':
            return $tu['max']['hp'] * $v / 100 * (int)($eff['st'] ?? 1) * $turns / 700;
        case 'bruciatura':
            return $tu['max']['hp'] * $v / 100 * $turns / 700 + 0.05;
        case 'sanguinamento':
        case 'elettrizzato':
            return duel_eff($st, $src)['atk'] * $v / 100 * $turns / 700 + 0.05;
        case 'maledizione':
            return 0.25;
        case 'vel_su':
            return 0.1 * $turns;
        case 'vel_giu':
        case 'atk_giu':
        case 'corrosione':
            return 0.1 * $turns;
        case 'barriera':
        case 'indistruttibile':
        case 'rinascita':
            return 0.3;
        case 'provocazione':
            return 0.12;
        case 'rigenerazione':
            return min($tu['max']['hp'] * $v / 100 * $turns, $tu['max']['hp'] - $tu['hp'] + $tu['max']['hp'] * 0.2) / 700;
    }
    if ($def['cc']) {
        $threat = min(1.5, duel_eff($st, $t)['atk'] / 200);
        return 0.5 * $threat + 0.1;
    }
    return $def['neg'] ? 0.15 : 0.18;
}

/** Come duel_targets ma senza dado (per contare i bersagli possibili). */
function duel_bot_targets(array $st, int $id, string $to, int $target): array
{
    $side = $st['units'][$id]['side'];
    $enemy = duel_enemy_side($side);
    switch ($to) {
        case 'self':
            return [$id];
        case 'target':
            if ($target > 0) {
                return [$target];
            }
            $e = duel_alive($st, $enemy);
            return $e ? [$e[0]] : [];
        case 'allies':
            return duel_alive($st, $side);
        case 'allies_other':
            return array_values(array_filter(duel_alive($st, $side), static fn($a) => $a !== $id));
        case 'enemies':
            return duel_alive($st, $enemy);
        case 'other_enemies':
            return array_values(array_filter(duel_alive($st, $enemy), static fn($e) => $e !== $target));
        case 'random_enemy':
            $e = duel_alive($st, $enemy);
            return $e ? [$e[0]] : [];
        case 'lowest_ally':
        case 'debuffed_ally':
            $best = null;
            foreach (duel_alive($st, $side) as $a) {
                if ($best === null || duel_hp_pct($st['units'][$a]) < duel_hp_pct($st['units'][$best])) {
                    $best = $a;
                }
            }
            return $best ? [$best] : [];
    }
    return [];
}
