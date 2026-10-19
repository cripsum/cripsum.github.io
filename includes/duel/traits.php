<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — i legami.
 *
 * Due o tre personaggi della stessa squadra con lo stesso tratto prendono un
 * piccolo bonus (solo loro, non tutta la squadra). Un personaggio puo' avere
 * piu' tratti. I tratti nascono dalla categoria del database o da un elenco
 * di id; nella fase 7 si assegnano dal pannello.
 *
 * Bonus (tiers: dal numero di membri in squadra):
 * - hp, atk, def: % sulle statistiche massime
 * - crit, cdmg, spd_pct, lifesteal, dmg_pct, heal_out_pct, heal_in_pct, prec:
 *   come le passive (vedi duel_eff)
 * - charge: carica dell'ultimate a inizio partita
 * - ps: punti squadra a inizio partita (una volta per legame)
 * - regen: Rigenerazione % per 2 turni a inizio partita
 */

function duel_traits(): array
{
    return [
        'amici' => ['it' => 'Amici di Cripsum', 'en' => 'Cripsum\'s friends', 'cat' => ['Friends'],
            'tiers' => [2 => ['hp' => 6], 3 => ['hp' => 6, 'atk' => 6]]],
        'meme' => ['it' => 'Meme', 'en' => 'Meme', 'cat' => ['meme'],
            'tiers' => [2 => ['crit' => 4], 3 => ['crit' => 4, 'cdmg' => 12]]],
        'anime' => ['it' => 'Anime', 'en' => 'Anime', 'cat' => ['anime'],
            'tiers' => [2 => ['spd_pct' => 5], 3 => ['spd_pct' => 5, 'charge' => 15]]],
        'poppy' => ['it' => 'Poppy', 'en' => 'Poppy', 'cat' => ['poppy'], 'ids' => [214],
            'tiers' => [2 => ['atk' => 8], 3 => ['atk' => 8, 'lifesteal' => 10]]],
        'nakano' => ['it' => 'Sorelle Nakano', 'en' => 'Nakano sisters', 'ids' => [53, 54, 55, 56, 57],
            'tiers' => [2 => ['def' => 10], 3 => ['def' => 10, 'atk' => 8]]],
        'kessoku' => ['it' => 'Kessoku Band', 'en' => 'Kessoku Band', 'ids' => [196, 197, 198, 199],
            'tiers' => [2 => ['charge' => 15], 3 => ['charge' => 25, 'ps' => 1]]],
        'dmc' => ['it' => 'Devil May Cry', 'en' => 'Devil May Cry', 'ids' => [141, 142],
            'tiers' => [2 => ['crit' => 12]]],
        'corte' => ['it' => 'La corte di Cripsum', 'en' => 'Cripsum\'s court', 'cat' => ['king'], 'ids' => [139],
            'tiers' => [2 => ['ps' => 1], 3 => ['ps' => 1, 'dmg_pct' => 8]]],
        'artisti' => ['it' => 'Artisti', 'en' => 'Artists', 'cat' => ['artist'],
            'tiers' => [2 => ['heal_out_pct' => 12], 3 => ['heal_out_pct' => 12, 'regen' => 5]]],
        'nauz' => ['it' => 'Nauz', 'en' => 'Nauz', 'ids' => [87, 138],
            'tiers' => [2 => ['heal_in_pct' => 15]]],
        'breaking_bad' => ['it' => 'Breaking Bad', 'en' => 'Breaking Bad', 'ids' => [28, 29],
            'tiers' => [2 => ['prec' => 15]]],
        'toki' => ['it' => 'Toki', 'en' => 'Toki', 'ids' => [45, 47],
            'tiers' => [2 => ['spd_pct' => 6]]],
        'xalx' => ['it' => 'Xalx Andrea', 'en' => 'Xalx Andrea', 'ids' => [2, 77],
            'tiers' => [2 => ['atk' => 8]]],
    ];
}

/** I tratti di un personaggio del database (id e categoria). */
function duel_character_traits(int $id, ?string $categoria): array
{
    $out = [];
    $cat = mb_strtolower(trim((string)$categoria), 'UTF-8');
    foreach (duel_traits() as $key => $t) {
        $byCat = false;
        foreach ($t['cat'] ?? [] as $c) {
            if (mb_strtolower($c, 'UTF-8') === $cat) {
                $byCat = true;
            }
        }
        if ($byCat || in_array($id, $t['ids'] ?? [], true)) {
            $out[] = $key;
        }
    }
    return $out;
}

/**
 * I legami attivi in una squadra: [tratto => [livello, [indici dei membri]]].
 * $kits = i 3 kit della squadra (con 'traits').
 */
function duel_team_traits(array $kits): array
{
    $members = [];
    foreach (array_values($kits) as $i => $kit) {
        foreach ($kit['traits'] ?? [] as $t) {
            $members[$t][] = $i;
        }
    }
    $defs = duel_traits();
    $out = [];
    foreach ($members as $t => $who) {
        if (!isset($defs[$t])) {
            continue;
        }
        $tier = 0;
        foreach (array_keys($defs[$t]['tiers']) as $need) {
            if (count($who) >= $need) {
                $tier = max($tier, $need);
            }
        }
        if ($tier > 0) {
            $out[$t] = [$tier, $who];
        }
    }
    return $out;
}
