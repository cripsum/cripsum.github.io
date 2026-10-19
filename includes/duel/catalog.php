<?php

declare(strict_types=1);

require_once __DIR__ . '/rules.php';

/**
 * Duelli 2.0 — il catalogo degli stati, dei terreni e delle interazioni.
 *
 * Ogni stato ha regole fisse e un valore base: un kit puo' usare il valore
 * base o scriverne uno suo. Le regole di ogni stato (quando fa effetto, come
 * si somma, se e' negativo, se e' un controllo) le applica il motore; qui ci
 * sono solo i dati.
 *
 * Campi:
 * - fam: famiglia (dot, ctrl, deb, buf, pro, spe)
 * - neg: negativo (lo frena la Tenacia, lo blocca l'Immunita', lo toglie la
 *   Purificazione)
 * - cc: controllo (dopo arriva la Resistenza al controllo)
 * - stack: 'refresh' (tiene il valore piu' alto e riparte la durata),
 *   'stacks' (somma cariche fino a max), 'once' (non si rimette se c'e' gia')
 * - v, t: valore e durata (turni di chi lo ha) di base; t = 0 vuol dire
 *   "finche' non si consuma"
 * - it, en: nome; d_it, d_en: descrizione con {v} {t} {max}
 */
function duel_statuses(): array
{
    static $all = null;
    static $gen = -1;
    if ($all !== null && $gen === DuelOverrides::$gen) {
        return $all;
    }
    $gen = DuelOverrides::$gen;

    $all = [
        // ── Danni nel tempo ───────────────────────────────────────────────
        'veleno' => ['fam' => 'dot', 'neg' => true, 'cc' => false, 'stack' => 'stacks', 'max' => 5, 'v' => 6, 't' => 2,
            'it' => 'Veleno', 'en' => 'Poison',
            'd_it' => 'A inizio turno perde il {v}% degli HP massimi per carica (max {max}). Passa sotto gli scudi.',
            'd_en' => 'Loses {v}% max HP per stack at turn start (max {max}). Ignores shields.'],
        'sanguinamento' => ['fam' => 'dot', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 30, 't' => 2,
            'it' => 'Sanguinamento', 'en' => 'Bleed',
            'd_it' => 'Ogni volta che agisce perde il {v}% dell\'ATK di chi l\'ha ferito.',
            'd_en' => 'Each time it acts it loses {v}% of the attacker\'s ATK.'],
        'bruciatura' => ['fam' => 'dot', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 6, 't' => 2,
            'it' => 'Bruciatura', 'en' => 'Burn',
            'd_it' => 'A inizio turno perde il {v}% degli HP massimi; le cure ricevute sono dimezzate.',
            'd_en' => 'Loses {v}% max HP at turn start; healing received is halved.'],
        'corrosione' => ['fam' => 'dot', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 6, 't' => 3,
            'it' => 'Corrosione', 'en' => 'Corrosion',
            'd_it' => 'La Difesa scende del {v}% in più a ogni turno, fino a −30%.',
            'd_en' => 'Defense drops by {v}% more each turn, down to −30%.'],
        'maledizione' => ['fam' => 'dot', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 25, 't' => 3,
            'it' => 'Maledizione', 'en' => 'Curse',
            'd_it' => 'Conserva il {v}% dei danni subiti e allo scadere li restituisce tutti insieme.',
            'd_en' => 'Stores {v}% of damage taken and deals it all at once when it expires.'],
        'elettrizzato' => ['fam' => 'dot', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 15, 't' => 2,
            'it' => 'Elettrizzato', 'en' => 'Shocked',
            'd_it' => 'Se usa un\'abilità subisce il {v}% dell\'ATK di chi l\'ha messo e la sua squadra perde 1 PS.',
            'd_en' => 'Using a skill deals {v}% of the source\'s ATK to it and its team loses 1 TP.'],

        // ── Controllo ─────────────────────────────────────────────────────
        'stordimento' => ['fam' => 'ctrl', 'neg' => true, 'cc' => true, 'stack' => 'once', 'max' => 1, 'v' => 0, 't' => 1,
            'it' => 'Stordimento', 'en' => 'Stun',
            'd_it' => 'Salta il prossimo turno.', 'd_en' => 'Skips its next turn.'],
        'congelamento' => ['fam' => 'ctrl', 'neg' => true, 'cc' => true, 'stack' => 'once', 'max' => 1, 'v' => 25, 't' => 1,
            'it' => 'Congelamento', 'en' => 'Freeze',
            'd_it' => 'Salta il turno; il colpo che lo libera fa +{v}% (Frantumazione).',
            'd_en' => 'Skips its turn; the hit that breaks it deals +{v}% (Shatter).'],
        'sonno' => ['fam' => 'ctrl', 'neg' => true, 'cc' => true, 'stack' => 'once', 'max' => 1, 'v' => 30, 't' => 3,
            'it' => 'Sonno', 'en' => 'Sleep',
            'd_it' => 'Salta i turni finché non viene colpito (max {t}). Il primo colpo fa +{v}%.',
            'd_en' => 'Skips turns until hit (max {t}). The first hit deals +{v}%.'],
        'paura' => ['fam' => 'ctrl', 'neg' => true, 'cc' => true, 'stack' => 'once', 'max' => 1, 'v' => 0, 't' => 1,
            'it' => 'Paura', 'en' => 'Fear',
            'd_it' => 'Niente abilità né ultimate; l\'Attacco va su un nemico a caso.',
            'd_en' => 'No skills or ultimate; its Attack hits a random enemy.'],
        'confusione' => ['fam' => 'ctrl', 'neg' => true, 'cc' => true, 'stack' => 'once', 'max' => 1, 'v' => 40, 't' => 2,
            'it' => 'Confusione', 'en' => 'Confusion',
            'd_it' => '{v}% di probabilità che l\'Attacco colpisca un suo alleato.',
            'd_en' => '{v}% chance that its Attack hits an ally.'],
        'silenzio' => ['fam' => 'ctrl', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 0, 't' => 2,
            'it' => 'Silenzio', 'en' => 'Silence',
            'd_it' => 'Niente abilità né ultimate: solo Attacco e Guardia.', 'd_en' => 'No skills or ultimate: only Attack and Guard.'],
        'disarmo' => ['fam' => 'ctrl', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 0, 't' => 1,
            'it' => 'Disarmo', 'en' => 'Disarm',
            'd_it' => 'Niente Attacco: non genera PS per la squadra.', 'd_en' => 'No Attack: cannot generate TP.'],
        'provocazione' => ['fam' => 'ctrl', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 0, 't' => 2,
            'it' => 'Provocazione', 'en' => 'Taunt',
            'd_it' => 'I colpi singoli dei nemici devono andare su di lui.', 'd_en' => 'Enemy single-target hits must target it.'],
        'fascino' => ['fam' => 'ctrl', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 0, 't' => 2,
            'it' => 'Fascino', 'en' => 'Charm',
            'd_it' => 'Non può colpire chi l\'ha affascinato.', 'd_en' => 'Cannot target whoever charmed it.'],

        // ── Indebolimenti ─────────────────────────────────────────────────
        'atk_giu' => ['fam' => 'deb', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 20, 't' => 2,
            'it' => 'Attacco −', 'en' => 'Attack −', 'd_it' => 'Attacco −{v}%.', 'd_en' => 'Attack −{v}%.'],
        'def_giu' => ['fam' => 'deb', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 25, 't' => 2,
            'it' => 'Difesa −', 'en' => 'Defense −', 'd_it' => 'Difesa −{v}%.', 'd_en' => 'Defense −{v}%.'],
        'vel_giu' => ['fam' => 'deb', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 20, 't' => 2,
            'it' => 'Velocità −', 'en' => 'Speed −', 'd_it' => 'Velocità −{v}%: arretra nell\'ordine dei turni.', 'd_en' => 'Speed −{v}%: falls back in the turn order.'],
        'crit_giu' => ['fam' => 'deb', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 15, 't' => 2,
            'it' => 'Critico −', 'en' => 'Crit −', 'd_it' => 'Probabilità di critico −{v}.', 'd_en' => 'Crit rate −{v}.'],
        'marchio' => ['fam' => 'deb', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 20, 't' => 2,
            'it' => 'Marchio', 'en' => 'Mark', 'd_it' => 'Subisce il {v}% di danni in più.', 'd_en' => 'Takes {v}% more damage.'],
        'vulnerabile' => ['fam' => 'deb', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 50, 't' => 2,
            'it' => 'Vulnerabile', 'en' => 'Vulnerable', 'd_it' => 'I critici contro di lui fanno il {v}% in più.', 'd_en' => 'Crits against it deal {v}% more.'],
        'ferita' => ['fam' => 'deb', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 50, 't' => 2,
            'it' => 'Ferita grave', 'en' => 'Grievous wound', 'd_it' => 'Cure e scudi ricevuti −{v}%.', 'd_en' => 'Healing and shields received −{v}%.'],
        'guardia_rotta' => ['fam' => 'deb', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 0, 't' => 1,
            'it' => 'Guardia rotta', 'en' => 'Guard break', 'd_it' => 'Perde gli scudi e non può usare Guardia.', 'd_en' => 'Loses shields and cannot Guard.'],
        'esposto' => ['fam' => 'deb', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 0, 't' => 1,
            'it' => 'Esposto', 'en' => 'Exposed', 'd_it' => 'Ignora schivata, barriera e riduzioni di danno.', 'd_en' => 'Ignores dodge, barrier and damage reduction.'],
        'taglia' => ['fam' => 'deb', 'neg' => true, 'cc' => false, 'stack' => 'once', 'max' => 1, 'v' => 2, 't' => 0,
            'it' => 'Taglia', 'en' => 'Bounty', 'd_it' => 'Chi lo mette KO da\' alla sua squadra {v} PS e 30 di carica.', 'd_en' => 'Whoever KOs it gives its team {v} TP and 30 charge.'],
        'prosciugato' => ['fam' => 'deb', 'neg' => true, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 15, 't' => 2,
            'it' => 'Prosciugato', 'en' => 'Drained', 'd_it' => 'Perde {v} di carica dell\'ultimate a inizio turno.', 'd_en' => 'Loses {v} ultimate charge at turn start.'],

        // ── Potenziamenti ─────────────────────────────────────────────────
        'atk_su' => ['fam' => 'buf', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 20, 't' => 2,
            'it' => 'Attacco +', 'en' => 'Attack +', 'd_it' => 'Attacco +{v}%.', 'd_en' => 'Attack +{v}%.'],
        'def_su' => ['fam' => 'buf', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 25, 't' => 2,
            'it' => 'Difesa +', 'en' => 'Defense +', 'd_it' => 'Difesa +{v}%.', 'd_en' => 'Defense +{v}%.'],
        'vel_su' => ['fam' => 'buf', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 20, 't' => 2,
            'it' => 'Velocità +', 'en' => 'Speed +', 'd_it' => 'Velocità +{v}%: avanza nell\'ordine dei turni.', 'd_en' => 'Speed +{v}%: moves up the turn order.'],
        'crit_su' => ['fam' => 'buf', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 20, 't' => 2,
            'it' => 'Critico +', 'en' => 'Crit +', 'd_it' => 'Probabilità di critico +{v}.', 'd_en' => 'Crit rate +{v}.'],
        'cdmg_su' => ['fam' => 'buf', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 30, 't' => 2,
            'it' => 'Danno critico +', 'en' => 'Crit damage +', 'd_it' => 'Danno critico +{v}%.', 'd_en' => 'Crit damage +{v}%.'],
        'rigenerazione' => ['fam' => 'buf', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 8, 't' => 2,
            'it' => 'Rigenerazione', 'en' => 'Regeneration', 'd_it' => 'A inizio turno si cura del {v}% degli HP massimi.', 'd_en' => 'Heals {v}% max HP at turn start.'],
        'rubavita' => ['fam' => 'buf', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 25, 't' => 2,
            'it' => 'Rubavita', 'en' => 'Lifesteal', 'd_it' => 'Si cura del {v}% del danno che fa.', 'd_en' => 'Heals for {v}% of damage dealt.'],
        'concentrazione' => ['fam' => 'buf', 'neg' => false, 'cc' => false, 'stack' => 'once', 'max' => 1, 'v' => 1, 't' => 0,
            'it' => 'Concentrazione', 'en' => 'Focus', 'd_it' => 'La prossima abilità costa {v} PS in meno.', 'd_en' => 'Next skill costs {v} TP less.'],
        'ispirato' => ['fam' => 'buf', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 20, 't' => 2,
            'it' => 'Ispirato', 'en' => 'Inspired', 'd_it' => '+{v} di carica dell\'ultimate a inizio turno.', 'd_en' => '+{v} ultimate charge at turn start.'],
        'furia' => ['fam' => 'buf', 'neg' => false, 'cc' => false, 'stack' => 'once', 'max' => 1, 'v' => 15, 't' => 0,
            'it' => 'Furia', 'en' => 'Fury', 'd_it' => '+{v}% di danni per ogni alleato KO.', 'd_en' => '+{v}% damage per KO\'d ally.'],
        'contrattacco' => ['fam' => 'buf', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 100, 't' => 1,
            'it' => 'Contrattacco', 'en' => 'Counter', 'd_it' => 'Quando viene colpito risponde con un Attacco ({v}%).', 'd_en' => 'Counters with an Attack when hit ({v}%).'],

        // ── Protezione ────────────────────────────────────────────────────
        'barriera' => ['fam' => 'pro', 'neg' => false, 'cc' => false, 'stack' => 'once', 'max' => 1, 'v' => 1, 't' => 2,
            'it' => 'Barriera', 'en' => 'Barrier', 'd_it' => 'Annulla del tutto il prossimo colpo.', 'd_en' => 'Fully blocks the next hit.'],
        'riflesso' => ['fam' => 'pro', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 20, 't' => 2,
            'it' => 'Riflesso', 'en' => 'Reflect', 'd_it' => 'Rimanda a chi attacca il {v}% del danno subito.', 'd_en' => 'Returns {v}% of damage taken to the attacker.'],
        'schivata' => ['fam' => 'pro', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 20, 't' => 2,
            'it' => 'Schivata', 'en' => 'Dodge', 'd_it' => '{v}% di evitare un colpo singolo.', 'd_en' => '{v}% chance to avoid a single-target hit.'],
        'furtivita' => ['fam' => 'pro', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 0, 't' => 1,
            'it' => 'Furtività', 'en' => 'Stealth', 'd_it' => 'Non può essere preso di mira dai colpi singoli finché non agisce.', 'd_en' => 'Cannot be targeted by single-target hits until it acts.'],
        'guardiano' => ['fam' => 'pro', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 40, 't' => 2,
            'it' => 'Protetto', 'en' => 'Guarded', 'd_it' => 'Il suo Guardiano prende il {v}% dei danni che riceve.', 'd_en' => 'Its Guardian takes {v}% of the damage it receives.'],
        'immunita' => ['fam' => 'pro', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 0, 't' => 1,
            'it' => 'Immunità', 'en' => 'Immunity', 'd_it' => 'Non riceve nuovi stati negativi.', 'd_en' => 'Cannot receive new negative effects.'],
        'indistruttibile' => ['fam' => 'pro', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 0, 't' => 1,
            'it' => 'Indistruttibile', 'en' => 'Undying', 'd_it' => 'Non scende sotto 1 HP.', 'd_en' => 'Cannot drop below 1 HP.'],
        'rinascita' => ['fam' => 'pro', 'neg' => false, 'cc' => false, 'stack' => 'once', 'max' => 1, 'v' => 30, 't' => 0,
            'it' => 'Rinascita', 'en' => 'Rebirth', 'd_it' => 'Se va KO torna in campo con il {v}% degli HP, una volta.', 'd_en' => 'When KO\'d it returns with {v}% HP, once.'],

        // ── Speciali ──────────────────────────────────────────────────────
        'resistenza' => ['fam' => 'spe', 'neg' => false, 'cc' => false, 'stack' => 'refresh', 'max' => 1, 'v' => 0, 't' => 1,
            'it' => 'Resistenza al controllo', 'en' => 'Control resistance', 'd_it' => 'Per un turno non riceve controlli.', 'd_en' => 'Cannot be controlled for a turn.'],
    ];

    // Valori cambiati dal pannello: solo valore, durata e massimo, solo numeri.
    foreach (duel_overrides()['status'] as $key => $values) {
        foreach (['v', 't', 'max'] as $field) {
            if (isset($all[$key], $values[$field]) && is_numeric($values[$field])) {
                $num = (float)$values[$field];
                $all[$key][$field] = $field === 'v' && floor($num) !== $num ? $num : (int)$num;
            }
        }
    }

    return $all;
}

/**
 * Stati che non sono nella lista sopra ma esistono nel catalogo come regole:
 * Scudo (e' una riserva di vita sulla carta), Cariche (contatori personali
 * dei kit), Forma (trasformazioni dei kit), Terreno (sul campo).
 */
function duel_status(string $key): ?array
{
    return duel_statuses()[$key] ?? null;
}

function duel_status_is_negative(string $key): bool
{
    return (bool)(duel_statuses()[$key]['neg'] ?? false);
}

/** Stati che tolgono o limitano il turno e fanno scattare la Resistenza. */
function duel_status_is_cc(string $key): bool
{
    return (bool)(duel_statuses()[$key]['cc'] ?? false);
}

/** Stati che impediscono di agire del tutto. */
function duel_skip_statuses(): array
{
    return ['stordimento', 'congelamento', 'sonno'];
}

/**
 * Terreni. Durata in giri: un giro = un turno per ogni carta in piedi nel
 * momento in cui il terreno viene messo.
 * - mods: modificatori per tutte le carte in campo
 * - blocks: stati che non si possono mettere finche' c'e'
 */
function duel_terrains(): array
{
    return [
        'notte' => ['it' => 'Notte', 'en' => 'Night', 'g' => 3, 'mods' => ['crit' => 10], 'stealth_bonus' => 1,
            'd_it' => 'Critico +10% a tutti. La Furtività dura un turno in più.', 'd_en' => 'Crit +10% to all. Stealth lasts one more turn.'],
        'tempesta' => ['it' => 'Tempesta', 'en' => 'Storm', 'g' => 3, 'mods' => ['spd_pct' => 10], 'shock_ps' => 1,
            'd_it' => 'Velocità +10% a tutti. Chi è Elettrizzato perde 1 PS in più.', 'd_en' => 'Speed +10% to all. Shocked units lose 1 more TP.'],
        'palude' => ['it' => 'Palude', 'en' => 'Swamp', 'g' => 2, 'mods' => ['regen_pct' => -50], 'round_poison' => 1,
            'd_it' => 'A fine giro 1 carica di Veleno a chi non ne ha. Rigenerazione dimezzata.', 'd_en' => 'At round end, 1 Poison stack to anyone without. Regeneration halved.'],
        'santuario' => ['it' => 'Santuario', 'en' => 'Sanctuary', 'g' => 3, 'mods' => ['heal_pct' => 30], 'blocks' => ['bruciatura', 'sanguinamento'],
            'd_it' => 'Cure +30%. Bruciatura e Sanguinamento non si possono mettere.', 'd_en' => 'Healing +30%. Burn and Bleed cannot be applied.'],
        'cielo_stellato' => ['it' => 'Cielo stellato', 'en' => 'Starry sky', 'g' => 3, 'mods' => ['heal_pct' => 20, 'crit' => 5],
            'd_it' => 'Cure +20% e Critico +5% a tutti.', 'd_en' => 'Healing +20% and Crit +5% to all.'],
    ];
}

/**
 * Le interazioni tra stati. Valori regolabili; le applica il motore.
 */
function duel_interactions(): array
{
    return [
        'frantumazione' => ['bonus' => 25, 'charge_loss' => 10],  // colpire un Congelato
        'risveglio' => ['bonus' => 30],                          // colpire chi dorme
        'detonazione' => ['mult' => 120],                        // consumare Veleno o Bruciatura
        'catena' => ['pct' => 30],                               // abilita' su un Elettrizzato
        'caccia' => ['ps' => 1],                                 // colpire chi ha Taglia e Marchio
        'muro' => true,                                          // il danno preso da Guardiano conta per il Riflesso
    ];
}
