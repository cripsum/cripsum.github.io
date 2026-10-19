<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — le regole: l'unico posto dove stanno i numeri del gioco.
 *
 * Formula del danno, punti squadra, carica dell'ultimate, ordine dei turni,
 * tetti, rarita' (statistiche, kit, ultimate, costo per la Ranked) e
 * statistiche dei dieci ruoli. Il motore (engine.php), i kit dei ruoli
 * (kits.php) e il simulatore leggono da qui; nella fase 2 il pannello potra'
 * cambiare questi valori dal database (duel_rules() li fondera').
 *
 * Nessuna query, nessuna sessione: si include anche da riga di comando.
 * Si include sempre con require_once.
 */

require_once __DIR__ . '/../gacha/config.php';

const DUEL_ENGINE_VERSION = 1;

/** I dieci ruoli, nell'ordine in cui si mostrano. */
const DUEL_ROLES = ['Tank', 'Bruiser', 'DPS', 'Burst DPS', 'Sub DPS', 'Support', 'Healer', 'Controller', 'Debuffer', 'Buffer'];

/**
 * Le costanti del combattimento. Valori di partenza: il simulatore e il
 * pannello li tarano senza toccare il codice.
 */
function duel_rules(): array
{
    static $rules = null;
    if ($rules !== null) {
        return $rules;
    }

    $rules = [
        // Danno = ATK × % × K / (K + DEF × (1 − penetrazione)) × bonus × critico × caso.
        'def_k' => 300,
        'variance_min' => 95,          // caso tra 0,95 e 1,05
        'variance_max' => 105,
        'crit_cap' => 75,              // tasso critico massimo
        'crit_dmg_base' => 150,

        // Punti squadra.
        'ps_start' => 3,
        'ps_max' => 5,

        // Carica dell'ultimate (0-100). Quella per mossa dipende dalla rarita'.
        'charge_max' => 100,
        'charge_on_hit' => 10,         // chi viene colpito
        'charge_on_kill' => 15,        // chi mette KO
        'charge_guard_factor' => 50,   // Guardia: meta' della carica di una mossa

        // Guardia.
        'guard_reduction' => 35,

        // Ordine dei turni: valore d'azione = gauge / Velocita'.
        'av_gauge' => 10000,

        // Tetti.
        'shield_cap' => 60,            // % HP massimi
        'shield_decay' => 30,          // lo scudo perde il 30% a ogni turno di chi lo ha
        'reduction_cap' => 60,         // riduzione dei danni totale
        'dodge_cap' => 40,
        'speed_up_cap' => 60,
        'speed_down_cap' => 50,
        'stat_down_cap' => 70,         // nessuna statistica scende sotto il 30%

        // Stati.
        'tenacity_cut' => 40,          // oltre questa Tenacia i controlli durano 1 turno in meno
        'cc_resist_turns' => 1,        // Resistenza al controllo dopo ogni controllo

        // Sfinimento: contro le partite che non finiscono (troppe cure e scudi).
        // Dal turno indicato cure e scudi ricevuti scendono e i danni salgono
        // a ogni blocco di turni.
        'fatigue_turn' => 40,
        'fatigue_heal_cut' => 60,      // cure e scudi -60%
        'fatigue_step' => 10,          // ogni 10 turni...
        'fatigue_dmg' => 25,           // ...+25% di danni a tutti

        // Partita.
        'turn_limit' => 90,            // poi vince chi ha piu' vita in percentuale
        'team_size' => 3,
        'ranked_budget' => 14,
        'level_max' => 6,
        'level_step' => 4,             // % a livello su HP, ATK, DEF
    ];

    return $rules;
}

/**
 * Le rarita' del duello. Rispetto al gacha c'e' in piu' "segreto_limitato":
 * nel gacha il limitato e' un flag, qui conta come rarita' a se'.
 *
 * - stat: moltiplicatore di HP, ATK, DEF
 * - spd: Velocita' in piu'
 * - k: moltiplicatore di passive e abilita'
 * - u: moltiplicatore dei numeri dell'ultimate
 * - tier: 0..7, sblocca gli effetti in piu' dei kit dei ruoli
 * - charge: carica dell'ultimate a ogni mossa
 * - cost: costo per la Ranked
 * - power: potere obiettivo (per il simulatore e i rapporti)
 */
function duel_rarities(): array
{
    return [
        'comune'           => ['stat' => 1.00, 'spd' => 0,  'k' => 1.00, 'u' => 1.00, 'tier' => 0, 'charge' => 25, 'cost' => 1, 'power' => 1.00],
        'raro'             => ['stat' => 1.08, 'spd' => 2,  'k' => 1.07, 'u' => 1.10, 'tier' => 1, 'charge' => 25, 'cost' => 2, 'power' => 1.08],
        'epico'            => ['stat' => 1.17, 'spd' => 4,  'k' => 1.13, 'u' => 1.26, 'tier' => 2, 'charge' => 22, 'cost' => 3, 'power' => 1.16],
        'leggendario'      => ['stat' => 1.23, 'spd' => 6,  'k' => 1.16, 'u' => 1.38, 'tier' => 3, 'charge' => 22, 'cost' => 4, 'power' => 1.25],
        'speciale'         => ['stat' => 1.26, 'spd' => 8,  'k' => 1.22, 'u' => 1.52, 'tier' => 4, 'charge' => 20, 'cost' => 5, 'power' => 1.34],
        'segreto'          => ['stat' => 1.32, 'spd' => 10, 'k' => 1.28, 'u' => 1.68, 'tier' => 5, 'charge' => 18, 'cost' => 6, 'power' => 1.44],
        'segreto_limitato' => ['stat' => 1.33, 'spd' => 11, 'k' => 1.29, 'u' => 1.71, 'tier' => 6, 'charge' => 18, 'cost' => 6, 'power' => 1.46],
        'theone'           => ['stat' => 1.36, 'spd' => 14, 'k' => 1.35, 'u' => 1.90, 'tier' => 7, 'charge' => 18, 'cost' => 7, 'power' => 1.55],
    ];
}

/**
 * La rarita' del duello da quella del database. Il flag "limitato" vale solo
 * per i Segreti (un The One resta The One). Sconosciuta = comune.
 */
function duel_rarity_key(?string $rarity, bool $limited = false): string
{
    $key = gacha_rarity_key($rarity);
    if ($key === '') {
        $key = 'comune';
    }
    if ($key === 'segreto' && $limited) {
        return 'segreto_limitato';
    }
    return $key;
}

function duel_rarity(string $key): array
{
    $all = duel_rarities();
    return $all[$key] ?? $all['comune'];
}

/**
 * Statistiche dei ruoli a Comune Lv. 1. Riferimento: HP 1000, ATK 150,
 * DEF 100, VEL 100. Critico e danno critico in %, Precisione e Tenacia in
 * punti percentuali.
 */
function duel_role_stats(): array
{
    return [
        'Tank'       => ['hp' => 1300, 'atk' => 105, 'def' => 145, 'spd' => 88,  'crit' => 5,  'cdmg' => 150, 'prec' => 0,  'ten' => 30],
        'Bruiser'    => ['hp' => 1250, 'atk' => 158, 'def' => 115, 'spd' => 95,  'crit' => 10, 'cdmg' => 150, 'prec' => 0,  'ten' => 15],
        'DPS'        => ['hp' => 950,  'atk' => 180, 'def' => 85,  'spd' => 105, 'crit' => 20, 'cdmg' => 160, 'prec' => 0,  'ten' => 0],
        'Burst DPS'  => ['hp' => 850,  'atk' => 182, 'def' => 75,  'spd' => 100, 'crit' => 20, 'cdmg' => 165, 'prec' => 0,  'ten' => 0],
        'Sub DPS'    => ['hp' => 950,  'atk' => 175, 'def' => 90,  'spd' => 110, 'crit' => 15, 'cdmg' => 150, 'prec' => 15, 'ten' => 0],
        'Support'    => ['hp' => 1100, 'atk' => 120, 'def' => 115, 'spd' => 105, 'crit' => 5,  'cdmg' => 150, 'prec' => 10, 'ten' => 15],
        'Healer'     => ['hp' => 1050, 'atk' => 128, 'def' => 100, 'spd' => 105, 'crit' => 5,  'cdmg' => 150, 'prec' => 0,  'ten' => 10],
        'Controller' => ['hp' => 1000, 'atk' => 180, 'def' => 95,  'spd' => 112, 'crit' => 10, 'cdmg' => 150, 'prec' => 30, 'ten' => 0],
        'Debuffer'   => ['hp' => 1000, 'atk' => 175, 'def' => 95,  'spd' => 105, 'crit' => 10, 'cdmg' => 150, 'prec' => 25, 'ten' => 0],
        'Buffer'     => ['hp' => 1000, 'atk' => 128, 'def' => 100, 'spd' => 115, 'crit' => 5,  'cdmg' => 150, 'prec' => 10, 'ten' => 10],
    ];
}

/** Il ruolo come lo scrive il database; sconosciuto = DPS (come oggi). */
function duel_role_key(?string $role): string
{
    $role = trim((string)$role);
    foreach (DUEL_ROLES as $known) {
        if (strcasecmp($known, $role) === 0) {
            return $known;
        }
    }
    return 'DPS';
}

/**
 * Le statistiche di un personaggio: ruolo × rarita' × livello. La Velocita'
 * sale solo con la rarita' (+2 a gradino), non coi livelli.
 */
function duel_stats(string $role, string $rarityKey, int $level): array
{
    $rules = duel_rules();
    $base = duel_role_stats()[duel_role_key($role)];
    $rar = duel_rarity($rarityKey);
    $level = max(1, min($rules['level_max'], $level));
    $mult = $rar['stat'] * (1 + ($level - 1) * $rules['level_step'] / 100);

    return [
        'hp' => (int)round($base['hp'] * $mult),
        'atk' => (int)round($base['atk'] * $mult),
        'def' => (int)round($base['def'] * $mult),
        'spd' => $base['spd'] + $rar['spd'],
        'crit' => $base['crit'],
        'cdmg' => $base['cdmg'],
        'prec' => $base['prec'],
        'ten' => $base['ten'],
    ];
}

/** Costo per la Ranked di una squadra (chiavi di rarita'). */
function duel_team_cost(array $rarityKeys): int
{
    $cost = 0;
    foreach ($rarityKeys as $key) {
        $cost += duel_rarity((string)$key)['cost'];
    }
    return $cost;
}
