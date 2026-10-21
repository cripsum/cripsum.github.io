<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — i kit.
 *
 * Un kit e' un array di dati: Attacco, Abilita', Ultimate e Passiva, ognuno
 * fatto di effetti del catalogo (vedi engine.php, duel_run_effects()). Chi
 * non ha un kit scritto apposta prende quello del suo ruolo alla sua
 * rarita' (duel_role_kit). Nella fase 2 i kit unici arrivano dal database.
 *
 * I numeri sono quelli dell'allegato duelli2-ruoli.md: valore a Comune ×k
 * (passive e abilita') o ×U (ultimate) della rarita', piu' gli effetti che
 * si sbloccano dall'Epico (tier 2), dal Leggendario (3), dallo Speciale (4)
 * e dal Segreto in su (5).
 */

require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/kits_unique.php';
require_once __DIR__ . '/traits.php';

/**
 * Il kit di un personaggio del database pronto per una partita: quello unico
 * se ce l'ha, altrimenti quello del suo ruolo alla sua rarita' (con i nomi a
 * tema per gli Speciali), piu' i suoi legami.
 * $char: una riga di `personaggi` (id, nome, rarita'/rarità, ruolo,
 * categoria, limitato).
 */
function duel_character_kit(array $char, int $level, array $opts = []): array
{
    $id = (int)($char['id'] ?? 0);
    $rarity = duel_rarity_key((string)($char['rarita'] ?? $char['rarità'] ?? ''), !empty($char['limitato']));
    $role = duel_role_key((string)($char['ruolo'] ?? ''));
    $kit = duel_role_kit($role, $rarity, $opts);

    // Un kit scritto dal pannello (game_kits) vale piu' di quello del codice.
    $unique = duel_overrides()['kits'][$id] ?? duel_unique_kits()[$id] ?? null;
    if ($unique) {
        // Le cariche del ruolo (lo Slancio del DPS) le fa salire la passiva
        // del ruolo: se il kit unico scrive la sua passiva senza cariche sue,
        // quelle del ruolo non salirebbero mai e il testo prometterebbe una
        // cosa che non succede.
        if (isset($unique['passive']) && !isset($unique['stacks'])) {
            unset($kit['stacks']);
        }
        foreach ($unique as $key => $value) {
            $kit[$key] = $value;
        }
        $kit['unique'] = true;
    } elseif (isset(duel_special_names()[$id])) {
        [$passive, $skill] = duel_special_names()[$id];
        $kit['passive']['name'] = $passive;
        $kit['skill']['name'] = $skill;
    }
    $kit['traits'] = duel_character_traits($id, (string)($char['categoria'] ?? ''));

    return duel_kit_build($kit, ['id' => $id, 'name' => (string)($char['nome'] ?? 'Personaggio')], $level);
}

function duel_kit_round(float $v): int
{
    return (int)round($v);
}

/**
 * Il kit del ruolo per una rarita'. $opts: name (per i nomi delle mosse),
 * cc ('sonno'|'paura'|'congelamento' per i Controller dal Leggendario),
 * dot ('veleno'|'corrosione' per i Debuffer).
 */
function duel_role_kit(string $role, string $rarityKey, array $opts = []): array
{
    $role = duel_role_key($role);
    $rar = duel_rarity($rarityKey);
    $k = $rar['k'];
    $U = $rar['u'];
    $T = $rar['tier'];
    $r = 'duel_kit_round';

    // Il simulatore ha mostrato che un supporto vale quanto i compagni che
    // aiuta, non quanto la sua rarita': un Buffer Comune (costo 1) accanto a
    // due Segreti rendeva troppo, un attaccante Comune troppo poco. Per i
    // supporti la scala sale di piu' (0,85 → 1,41), per gli attaccanti di
    // meno (1,08 → 1,36), per i Bruiser ancora meno (1,20 → 1,36: in alto
    // hanno gia' Furia e Indistruttibile). I Debuffer stanno coi supporti:
    // Veleno e Difesa − sono in percentuale e altrimenti non crescerebbero
    // con la rarita'. Gli Healer un po' meno ripidi (0,92 → 1,41). Tank e
    // Controller usano la scala della rarita' (1,00 → 1,35).
    if ($role === 'Bruiser') {
        $k = 1.20 + ($k - 1) * 0.45;
    } elseif (in_array($role, ['DPS', 'Burst DPS', 'Sub DPS'], true)) {
        $k = 1.08 + ($k - 1) * 0.8;
    } elseif ($role === 'Healer') {
        $k = 0.92 + ($k - 1) * 1.4;
    } elseif (in_array($role, ['Support', 'Buffer', 'Debuffer'], true)) {
        $k = 0.85 + ($k - 1) * 1.6;
    }

    $hit = static fn(int $pct, array $extra = []) => ['e' => 'damage', 'to' => 'target', 'pct' => $pct] + $extra;
    $kit = [
        'role' => $role,
        'rarity' => $rarityKey,
        'unique' => false,
        'attack' => ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [$hit(100)]],
        'skill' => null,
        'ultimate' => null,
        'passive' => ['name' => '', 'triggers' => []],
    ];

    switch ($role) {
        case 'Tank':
            $kit['attack'] = ['name' => 'Colpo di scudo', 'target' => 'enemy', 'effects' => [
                ['e' => 'damage', 'to' => 'target', 'mix' => ['atk' => 50, 'def' => 50]],
            ]];
            $kit['passive'] = ['name' => 'Muro', 'triggers' => [
                ['when' => 'battle_start', 'silent' => true, 'effects' => [['e' => 'shield', 'to' => 'self', 'pct' => $r(10 * $k)]]],
            ]];
            if ($T >= 3) {
                $kit['passive']['triggers'][] = ['when' => 'ally_low', 'below' => 30, 'effects' => [['e' => 'apply', 'to' => 'self', 'k' => 'provocazione', 't' => 1]]];
            }
            if ($T >= 5) {
                $kit['passive']['mods'] = ['reflect' => 15];
            }
            $skill = [['e' => 'shield', 'to' => 'self', 'pct' => $r(14 * $k)], ['e' => 'apply', 'to' => 'self', 'k' => 'provocazione', 't' => 2]];
            if ($T >= 2) {
                $skill[] = ['e' => 'apply', 'to' => 'self', 'k' => 'def_su', 'v' => 20, 't' => 2];
            }
            $kit['skill'] = ['name' => 'Bastione', 'cost' => 1, 'cd' => 2, 'target' => 'self', 'effects' => $skill];
            $ult = [
                ['e' => 'guardian', 'to' => 'allies_other', 'v' => min(50, $r(20 * $U)), 't' => 2],
                ['e' => 'shield', 'to' => 'self', 'pct' => $r(12 * $U)],
            ];
            if ($T >= 5) {
                $ult[] = ['e' => 'apply', 'to' => 'allies', 'k' => 'barriera', 't' => 2];
                $ult[] = ['e' => 'apply', 'to' => 'self', 'k' => 'riflesso', 'v' => 20, 't' => 2];
            }
            $kit['ultimate'] = ['name' => 'Guardiano', 'target' => 'self', 'effects' => $ult];
            break;

        case 'Bruiser':
            $kit['passive'] = ['name' => 'Furore', 'scaling' => [['type' => 'missing_hp', 'mods' => ['dmg_pct' => round(2.5 * $k, 1)]]], 'triggers' => []];
            if ($T >= 3) {
                $kit['passive']['triggers'][] = ['when' => 'self_low', 'below' => 30, 'effects' => [['e' => 'apply', 'to' => 'self', 'k' => 'rubavita', 'v' => 25, 't' => 2]]];
            }
            $skill = [$hit($r(165 * $k)), ['e' => 'apply', 'to' => 'target', 'k' => 'sanguinamento', 'v' => 30, 't' => 2, 'chance' => 100]];
            $skill[] = ['e' => 'heal', 'to' => 'self', 'of' => 'damage', 'pct' => 20, 'name' => 'rubavita'];
            $kit['skill'] = ['name' => 'Fendente', 'cost' => 1, 'target' => 'enemy', 'effects' => $skill];
            $ult = [$hit($r(260 * $U)), ['e' => 'heal', 'to' => 'self', 'of' => 'damage', 'pct' => 30, 'name' => 'rubavita']];
            if ($T >= 4) {
                $ult[] = ['e' => 'apply', 'to' => 'self', 'k' => 'furia', 'v' => 15, 't' => 0];
            }
            if ($T >= 5) {
                $ult[] = ['e' => 'apply', 'to' => 'self', 'k' => 'indistruttibile', 't' => 1];
            }
            $kit['ultimate'] = ['name' => 'Colpo del colosso', 'target' => 'enemy', 'effects' => $ult];
            break;

        case 'DPS':
            $kit['stacks'] = ['name' => 'Slancio', 'max' => 8, 'per' => ['crit' => round(6 * $k, 1)]];
            $kit['passive'] = ['name' => 'Slancio', 'triggers' => [
                ['when' => 'after_action', 'silent' => true, 'effects' => [['e' => 'stacks', 'n' => 1, 'if' => ['crit' => false]]]],
                ['when' => 'on_crit', 'silent' => true, 'effects' => [['e' => 'stacks', 'n' => -99]]],
            ]];
            if ($T >= 3) {
                $kit['passive']['triggers'][] = ['when' => 'on_crit', 'silent' => true, 'effects' => [['e' => 'charge', 'to' => 'self', 'n' => 10]]];
            }
            $skill = [$hit($r(250 * $k))];
            if ($T >= 2) {
                $skill[] = ['e' => 'ps', 'n' => 1, 'if' => ['crit' => true]];
            }
            $kit['skill'] = ['name' => 'Raffica', 'cost' => 2, 'target' => 'enemy', 'effects' => $skill];
            $ult = [$hit($r(300 * $U), $T >= 4 ? ['pen' => 25] : [])];
            if ($T >= 3) {
                $ult[] = ['e' => 'ps', 'n' => 2, 'if' => ['killed' => true]];
            }
            if ($T >= 5) {
                $ult[] = ['e' => 'extra_turn', 'to' => 'self', 'if' => ['killed' => true]];
            }
            $kit['ultimate'] = ['name' => 'Colpo finale', 'target' => 'enemy', 'effects' => $ult];
            break;

        case 'Burst DPS':
            $kit['attack'] = ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [$hit(110)]];
            $kit['passive'] = ['name' => 'Precisione letale', 'mods' => ['cdmg' => $r(20 * $k) + ($T >= 2 ? 10 : 0)], 'first_crit' => true, 'triggers' => []];
            $skill = [$hit($r(240 * $k), ['pen' => $T >= 3 ? 25 : 20])];
            $kit['skill'] = ['name' => 'Colpo da maestro', 'cost' => 2, 'target' => 'enemy', 'effects' => $skill];
            $ult = [];
            if ($T >= 4) {
                $ult[] = ['e' => 'apply', 'to' => 'target', 'k' => 'vulnerabile', 't' => 1, 'chance' => 100];
            }
            $ult[] = $hit($r(320 * $U), ['pen' => $T >= 5 ? 50 : 0, 'bonus' => [['if' => ['target_hp_below' => 40], 'pct' => 50]]]);
            $kit['ultimate'] = ['name' => 'Esecuzione', 'target' => 'enemy', 'effects' => $ult];
            break;

        case 'Sub DPS':
            $kit['attack'] = ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [$hit(90)]];
            $kit['passive'] = ['name' => 'Opportunismo', 'vs' => [['if' => ['target_has' => 'neg'], 'dmg_pct' => $r(12 * $k)]], 'triggers' => []];
            $kit['skill'] = ['name' => 'Colpo rallentante', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit($r(155 * $k)),
                ['e' => 'delay', 'to' => 'target', 'pct' => 15],
                ['e' => 'apply', 'to' => 'target', 'k' => 'vel_giu', 'v' => 15, 't' => 2, 'chance' => 100],
            ]];
            $ult = [['e' => 'damage', 'to' => 'enemies', 'pct' => $r(150 * $U)]];
            if ($T >= 3) {
                $ult[] = ['e' => 'spread', 'to' => 'target'];
            }
            if ($T >= 5) {
                $ult[] = ['e' => 'apply', 'to' => 'enemies', 'k' => 'marchio', 't' => 2, 'chance' => 100];
            }
            $kit['ultimate'] = ['name' => 'Pioggia di colpi', 'target' => 'enemy', 'effects' => $ult];
            break;

        case 'Support':
            $kit['attack'] = ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [$hit(80), ['e' => 'shield', 'to' => 'self', 'pct' => 5]]];
            $kit['passive'] = ['name' => 'Barriera iniziale', 'aura' => ['red_pct' => round(8 * $k, 1)], 'triggers' => [
                ['when' => 'battle_start', 'silent' => true, 'effects' => [['e' => 'shield', 'to' => 'allies', 'pct' => $r(8 * $k)]]],
            ]];
            if ($T >= 3) {
                $kit['passive']['triggers'][] = ['when' => 'turn_start', 'every' => 3, 'effects' => [['e' => 'cleanse', 'to' => 'debuffed_ally', 'n' => 1]]];
            }
            $skill = [['e' => 'shield', 'to' => 'allies', 'pct' => $r(16 * $k)], ['e' => 'apply', 'to' => 'allies', 'k' => 'def_su', 'v' => $r(15 * $k), 't' => 2]];
            if ($T >= 2) {
                $skill[] = ['e' => 'cleanse', 'to' => 'debuffed_ally', 'n' => 1];
            }
            $kit['skill'] = ['name' => 'Scudo di squadra', 'cost' => 1, 'target' => 'self', 'effects' => $skill];
            if ($T >= 3) {
                $ult = [['e' => 'apply', 'to' => 'allies', 'k' => 'barriera', 't' => 2], ['e' => 'cleanse', 'to' => 'allies', 'n' => 99]];
            } else {
                $ult = [['e' => 'shield', 'to' => 'allies', 'pct' => $r(25 * $U)], ['e' => 'cleanse', 'to' => 'allies', 'n' => 1]];
            }
            if ($T >= 4) {
                $ult[] = ['e' => 'apply', 'to' => 'allies', 'k' => 'immunita', 't' => 1];
            }
            if ($T >= 5) {
                $ult[] = ['e' => 'terrain', 'k' => 'santuario', 'g' => $T >= 7 ? 3 : 2];
            }
            $kit['ultimate'] = ['name' => 'Santuario', 'target' => 'self', 'effects' => $ult];
            break;

        case 'Healer':
            $kit['attack'] = ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [$hit(90)]];
            $kit['passive'] = ['name' => 'Cure costanti', 'triggers' => [
                ['when' => 'turn_start', 'silent' => true, 'effects' => [['e' => 'heal', 'to' => 'lowest_ally', 'of' => 'target_max', 'pct' => $r(4 * $k)]]],
            ]];
            if ($T >= 2) {
                $kit['passive']['overheal_shield'] = $T >= 3 ? 15 : 10;
            }
            $skill = [
                ['e' => 'heal', 'to' => 'target', 'of' => 'target_max', 'pct' => $r(20 * $k)],
                ['e' => 'apply', 'to' => 'target', 'k' => 'rigenerazione', 'v' => $r(5 * $k), 't' => 2],
            ];
            if ($T >= 2) {
                $skill[] = ['e' => 'cleanse', 'to' => 'target', 'n' => 1];
            }
            $kit['skill'] = ['name' => 'Soffio vitale', 'cost' => 1, 'target' => 'ally', 'effects' => $skill];
            $ult = [['e' => 'heal', 'to' => 'allies', 'of' => 'target_max', 'pct' => $r(20 * $U)]];
            if ($T >= 3) {
                $revive = [3 => 20, 4 => 25, 5 => 35, 6 => 35, 7 => 45][$T];
                array_unshift($ult, ['e' => 'revive', 'pct' => $revive]);
            }
            if ($T >= 5) {
                $ult[] = ['e' => 'apply', 'to' => 'allies', 'k' => 'rigenerazione', 'v' => 10, 't' => 2];
            } elseif ($T >= 2) {
                $ult[] = ['e' => 'apply', 'to' => 'allies', 'k' => 'rigenerazione', 'v' => 6, 't' => 2];
            }
            $kit['ultimate'] = ['name' => 'Rinascita', 'target' => 'self', 'effects' => $ult];
            break;

        case 'Controller':
            $kit['attack'] = ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [
                $hit(100),
                ['e' => 'delay', 'to' => 'target', 'pct' => 20, 'chance' => $r(10 * $k)],
            ]];
            $kit['passive'] = ['name' => 'Tempismo', 'vs' => [['if' => ['target_has' => 'cc'], 'dmg_pct' => $r(10 * $k)]], 'triggers' => []];
            if ($T >= 3) {
                $kit['passive']['cc_feed'] = 5;
            }
            $cc = $T <= 1 ? 'stordimento' : ($opts['cc'] ?? 'congelamento');
            $chance = [0 => 55, 1 => 55, 2 => 65, 3 => 75, 4 => 80, 5 => 85, 6 => 85, 7 => 90][$T];
            $kit['skill'] = ['name' => 'Blocco', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit($r(165 * $k)),
                ['e' => 'delay', 'to' => 'target', 'pct' => 20],
                ['e' => 'apply', 'to' => 'target', 'k' => $cc, 'chance' => $chance],
            ]];
            $ultCc = $T <= 1 ? 'stordimento' : $cc;
            $ult = [
                ['e' => 'damage', 'to' => 'enemies', 'pct' => $r(120 * $U)],
                ['e' => 'apply', 'to' => 'enemies', 'k' => $ultCc, 'chance' => min(75, $r(40 * $U))],
                ['e' => 'delay', 'to' => 'enemies', 'pct' => $r(20 * $U)],
            ];
            if ($T >= 5) {
                $ult[] = ['e' => 'terrain', 'k' => ($opts['terrain'] ?? 'tempesta'), 'g' => 2];
            }
            $kit['ultimate'] = ['name' => 'Tempo fermo', 'target' => 'self', 'effects' => $ult];
            break;

        case 'Debuffer':
            $dot = ($opts['dot'] ?? 'veleno') === 'corrosione' ? 'corrosione' : 'veleno';
            $attack = [$hit(100), ['e' => 'apply', 'to' => 'target', 'k' => $dot, 'st' => 1, 'chance' => min(100, $r(75 * $k))]];
            if ($T >= 3) {
                $attack[] = ['e' => 'apply', 'to' => 'target', 'k' => 'ferita', 't' => 2, 'if' => ['target_stacks' => ['veleno', 3]]];
            }
            $kit['attack'] = ['name' => 'Attacco', 'target' => 'enemy', 'effects' => $attack];
            $kit['passive'] = ['name' => 'Tossine', 'vs' => [['if' => ['target_has' => 'veleno'], 'dmg_pct' => $r(8 * $k)]], 'triggers' => []];
            $skill = [
                $hit($r(150 * $k)),
                ['e' => 'apply', 'to' => 'target', 'k' => 'def_giu', 'v' => $r(25 * $k), 't' => 2, 'chance' => 100],
                ['e' => 'apply', 'to' => 'target', 'k' => $dot, 'st' => 2, 'chance' => 100],
            ];
            if ($T >= 2) {
                $skill[] = ['e' => 'apply', 'to' => 'target', 'k' => 'marchio', 't' => 2, 'chance' => 100];
            }
            $kit['skill'] = ['name' => 'Nebbia', 'cost' => 1, 'target' => 'enemy', 'effects' => $skill];
            $ult = [];
            if ($T >= 4) {
                $ult[] = ['e' => 'consume', 'to' => 'enemies', 'k' => ['veleno', 'bruciatura']];
            }
            $ult[] = ['e' => 'apply', 'to' => 'enemies', 'k' => 'maledizione', 'v' => min(50, $r(25 * $U)), 't' => 3, 'chance' => 100];
            $ult[] = ['e' => 'apply', 'to' => 'enemies', 'k' => 'def_giu', 'v' => 20, 't' => 2, 'chance' => 100];
            if ($T >= 5) {
                $ult[] = ['e' => 'apply', 'to' => 'enemies', 'k' => 'ferita', 't' => 2, 'chance' => 100];
                $ult[] = ['e' => 'terrain', 'k' => 'palude', 'g' => 2];
            }
            $kit['ultimate'] = ['name' => 'Maledizione', 'target' => 'self', 'effects' => $ult];
            break;

        case 'Buffer':
            $kit['attack'] = ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [$hit(90)]];
            $kit['passive'] = ['name' => 'Presenza', 'aura' => ['atk_pct' => round(8 * $k, 1)], 'triggers' => []];
            $skill = [
                ['e' => 'apply', 'to' => 'target', 'k' => 'atk_su', 'v' => $r(24 * $k), 't' => 2],
                ['e' => 'advance', 'to' => 'target', 'pct' => 25],
                ['e' => 'ps', 'n' => 1],
            ];
            if ($T >= 2) {
                $skill[] = ['e' => 'apply', 'to' => 'target', 'k' => 'concentrazione'];
            }
            $kit['skill'] = ['name' => 'Canto', 'cost' => 1, 'target' => 'ally', 'effects' => $skill];
            $ult = [
                ['e' => 'apply', 'to' => 'allies', 'k' => 'atk_su', 'v' => $r(20 * $U), 't' => 2],
                ['e' => 'apply', 'to' => 'allies', 'k' => 'vel_su', 'v' => $r(10 * $U), 't' => 2],
                ['e' => 'ps', 'n' => $T >= 3 ? 2 : 1],
            ];
            if ($T >= 3) {
                $ult[] = ['e' => 'apply', 'to' => 'allies', 'k' => 'ispirato', 't' => 2];
            }
            if ($T >= 5) {
                $ult[] = ['e' => 'extra_turn', 'to' => 'target'];
            }
            $kit['ultimate'] = ['name' => 'Inno', 'target' => $T >= 5 ? 'ally_other' : 'self', 'effects' => $ult];
            break;
    }

    return $kit;
}

/**
 * Il kit pronto per una partita: nome, id del personaggio, livello e
 * statistiche. $kit puo' essere quello del ruolo o uno unico (che puo'
 * ritoccare le statistiche con 'stats_mods' in %).
 */
function duel_kit_build(array $kit, array $who, int $level): array
{
    $kit['pid'] = (int)($who['id'] ?? 0);
    $kit['name'] = (string)($who['name'] ?? 'Personaggio');
    $kit['level'] = max(1, min(duel_rules()['level_max'], $level));
    $stats = duel_stats($kit['role'], $kit['rarity'], $kit['level']);
    foreach ($kit['stats_mods'] ?? [] as $key => $pct) {
        if (isset($stats[$key])) {
            $stats[$key] = in_array($key, ['hp', 'atk', 'def', 'spd'], true)
                ? (int)round($stats[$key] * (1 + $pct / 100))
                : $stats[$key] + $pct;
        }
    }
    $kit['stats'] = $stats;
    return $kit;
}
