<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — i testi dei kit, scritti dai numeri veri.
 *
 * Oggi le descrizioni dei kit sono scritte a mano e in molti casi dicono
 * numeri diversi da quelli che usa il motore. Qui si costruiscono dagli
 * effetti del kit, in italiano e in inglese: cambiando un numero cambia
 * anche il testo.
 *
 *   duel_kit_text($kit, 'it') = ['attack' => [...], 'skill' => [...],
 *       'ultimate' => [...], 'passive' => [...]]
 * ogni voce: ['name' => ..., 'meta' => '2 PS · ricarica 2', 'text' => ...]
 */

require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/traits.php';

function duel_text_num(float $v): string
{
    return rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',');
}

/**
 * Articolo davanti a una percentuale: "al 25%" ma "all'8%", "il 30%" ma
 * "l'80%", "del 18%" (diciotto) ma "dell'11%". Si elide davanti ai numeri
 * che si leggono con una vocale: 1, 8, 11, 80-89, 800...
 */
function duel_text_art(string $art, float $v): string
{
    $n = duel_text_num($v);
    $int = (int)floor($v);
    $vowel = str_starts_with((string)$int, '8') || in_array($int, [1, 11], true);
    if (!$vowel) {
        return "$art $n%";
    }
    $el = ['al' => "all'", 'il' => "l'", 'del' => "dell'", 'dal' => "dall'"][$art] ?? "$art ";
    return $el . $n . '%';
}

/** A chi va un effetto: forma "a ..." (dat) o oggetto diretto (acc). */
function duel_text_who(string $to, string $moveTarget, string $lang, string $form = 'dat'): string
{
    $ally = in_array($moveTarget, ['ally', 'ally_other'], true);
    $it = [
        'target' => $ally ? ['all\'alleato scelto', 'l\'alleato scelto'] : ['al bersaglio', 'il bersaglio'],
        'self' => ['a sé', 'sé'],
        'allies' => ['a tutta la squadra', 'tutta la squadra'],
        'allies_other' => ['agli alleati', 'gli alleati'],
        'enemies' => ['a tutti i nemici', 'tutti i nemici'],
        'other_enemies' => ['agli altri nemici', 'gli altri nemici'],
        'random_enemy' => ['a un nemico a caso', 'un nemico a caso'],
        'lowest_ally' => ['all\'alleato con meno vita', 'l\'alleato con meno vita'],
        'debuffed_ally' => ['all\'alleato con più stati negativi', 'l\'alleato con più stati negativi'],
        'attacker' => ['a chi lo ha colpito', 'chi lo ha colpito'],
        'trigger' => ['a quell\'alleato', 'quell\'alleato'],
    ];
    $en = [
        'target' => $ally ? ['the chosen ally', 'the chosen ally'] : ['the target', 'the target'],
        'self' => ['itself', 'itself'],
        'allies' => ['the whole team', 'the whole team'],
        'allies_other' => ['its allies', 'its allies'],
        'enemies' => ['all enemies', 'all enemies'],
        'other_enemies' => ['the other enemies', 'the other enemies'],
        'random_enemy' => ['a random enemy', 'a random enemy'],
        'lowest_ally' => ['the ally with the lowest HP', 'the ally with the lowest HP'],
        'debuffed_ally' => ['the ally with the most debuffs', 'the ally with the most debuffs'],
        'attacker' => ['the attacker', 'the attacker'],
        'trigger' => ['that ally', 'that ally'],
    ];
    $map = $lang === 'en' ? $en : $it;
    $pair = $map[$to] ?? $map['target'];
    return $form === 'acc' ? $pair[1] : $pair[0];
}

/** Uno stato con il suo valore: "Difesa −25%", "2 cariche di Veleno (6% a carica)". */
function duel_text_status(string $k, array $eff, string $lang): string
{
    $def = duel_status($k);
    if (!$def) {
        return $k;
    }
    $name = $def[$lang === 'en' ? 'en' : 'it'];
    $v = (float)($eff['v'] ?? $def['v']);
    $n = duel_text_num($v);
    $st = (int)($eff['st'] ?? 1);

    switch ($k) {
        case 'atk_su': case 'def_su': case 'vel_su': case 'cdmg_su':
            $label = ['atk_su' => ['Attacco', 'Attack'], 'def_su' => ['Difesa', 'Defense'], 'vel_su' => ['Velocità', 'Speed'], 'cdmg_su' => ['Danno critico', 'Crit damage']][$k];
            return $label[$lang === 'en' ? 1 : 0] . " +{$n}%";
        case 'atk_giu': case 'def_giu': case 'vel_giu':
            $label = ['atk_giu' => ['Attacco', 'Attack'], 'def_giu' => ['Difesa', 'Defense'], 'vel_giu' => ['Velocità', 'Speed']][$k];
            return $label[$lang === 'en' ? 1 : 0] . " −{$n}%";
        case 'crit_su':
            return ($lang === 'en' ? 'Crit rate' : 'Critico') . " +{$n}";
        case 'crit_giu':
            return ($lang === 'en' ? 'Crit rate' : 'Critico') . " −{$n}";
        case 'veleno':
            return $lang === 'en'
                ? ($st === 1 ? "1 stack of Poison ({$n}% max HP)" : "{$st} stacks of Poison ({$n}% max HP each)")
                : ($st === 1 ? "1 carica di Veleno ({$n}% degli HP massimi)" : "{$st} cariche di Veleno ({$n}% degli HP massimi l'una)");
        case 'sanguinamento': case 'elettrizzato':
            return $lang === 'en' ? "$name ({$n}% of ATK)" : "$name ({$n}% dell'ATK)";
        case 'stordimento': case 'congelamento': case 'sonno': case 'paura': case 'silenzio': case 'disarmo':
        case 'provocazione': case 'fascino': case 'esposto': case 'guardia_rotta': case 'barriera': case 'immunita':
        case 'indistruttibile': case 'furtivita': case 'taglia': case 'concentrazione': case 'resistenza':
            return $name;
        case 'ispirato': case 'prosciugato':
            return "$name ({$n})";
        default:
            return "$name ({$n}%)";
    }
}

function duel_text_turns(int $t, string $lang): string
{
    if ($t <= 0) {
        return '';
    }
    if ($t >= 99) {
        return $lang === 'en' ? ' until the end of the match' : ' fino a fine partita';
    }
    return $lang === 'en' ? ($t === 1 ? ' for 1 turn' : " for $t turns") : ($t === 1 ? ' per 1 turno' : " per $t turni");
}

/** "Se è critico: ", "35% di probabilità: " ... */
function duel_text_cond(array $cond, string $lang): string
{
    $parts = [];
    foreach ($cond as $k => $v) {
        switch ($k) {
            case 'chance':
                $parts[] = $lang === 'en' ? duel_text_num((float)$v) . '% chance' : duel_text_num((float)$v) . '% di probabilità';
                break;
            case 'crit':
                $parts[] = $v ? ($lang === 'en' ? 'on a crit' : 'se è critico') : ($lang === 'en' ? 'if it is not a crit' : 'se non è critico');
                break;
            case 'killed':
                $parts[] = $lang === 'en' ? 'if it KOs the target' : 'se mette KO';
                break;
            case 'target_has':
                $what = $v === 'neg' ? ($lang === 'en' ? 'has a negative effect' : 'ha uno stato negativo')
                    : ($v === 'cc' ? ($lang === 'en' ? 'is controlled' : 'è sotto controllo')
                    : ($lang === 'en' ? 'has ' . (duel_status((string)$v)['en'] ?? $v) : 'ha ' . (duel_status((string)$v)['it'] ?? $v)));
                $parts[] = $lang === 'en' ? "if the target $what" : "se il bersaglio $what";
                break;
            case 'target_hp_below':
                $parts[] = $lang === 'en' ? "if the target is below {$v}% HP" : "se il bersaglio è sotto " . duel_text_art('il', (float)$v) . " della vita";
                break;
            case 'self_hp_below':
                $parts[] = $lang === 'en' ? "while below {$v}% HP" : "se è sotto " . duel_text_art('il', (float)$v) . " della vita";
                break;
            case 'self_hp_above':
                $parts[] = $lang === 'en' ? "while above {$v}% HP" : "se è sopra " . duel_text_art('il', (float)$v) . " della vita";
                break;
            case 'target_stacks':
                $sn = duel_status((string)$v[0])[$lang === 'en' ? 'en' : 'it'] ?? $v[0];
                $parts[] = $lang === 'en' ? "if the target has {$v[1]}+ stacks of $sn" : "se il bersaglio ha almeno {$v[1]} cariche di $sn";
                break;
        }
    }
    return $parts ? ucfirst(implode(', ', $parts)) . ': ' : '';
}

/** Una frase per effetto. $ctx: move target ('enemy', 'ally', 'self'), nome del kit, forme e cariche. */
function duel_text_effect(array $eff, array $ctx, string $lang): string
{
    $en = $lang === 'en';
    $mt = $ctx['target'] ?? 'enemy';
    $to = (string)($eff['to'] ?? 'target');
    $dat = duel_text_who($to, $mt, $lang, 'dat');
    $acc = duel_text_who($to, $mt, $lang, 'acc');
    $pct = duel_text_num((float)($eff['pct'] ?? 0));
    $s = '';

    switch ($eff['e'] ?? '') {
        case 'damage':
            if (isset($eff['mix'])) {
                $bits = [];
                foreach ($eff['mix'] as $stat => $p) {
                    $bits[] = duel_text_num((float)$p) . '% ' . strtoupper($stat);
                }
                $amount = implode(' + ', $bits);
            } elseif (($eff['stat'] ?? '') === 'target_max_hp') {
                $amount = $en ? "$pct% of the target's max HP" : "il $pct% degli HP massimi del bersaglio";
            } else {
                $amount = "$pct% ATK";
            }
            $hits = (int)($eff['hits'] ?? 1);
            if ($hits > 1) {
                $s = $en ? "Deals $hits hits of $amount to $dat" : "Infligge $hits colpi da $amount $dat";
            } else {
                $s = $en ? "Deals $amount to $dat" : "Infligge $amount $dat";
            }
            $extra = [];
            if (!empty($eff['true'])) {
                $extra[] = $en ? 'true damage (ignores Defense)' : 'danno vero (ignora la Difesa)';
            } elseif (!empty($eff['pen'])) {
                $extra[] = $en ? 'ignores ' . duel_text_num((float)$eff['pen']) . '% of Defense' : 'ignora ' . duel_text_art('il', (float)$eff['pen']) . ' della Difesa';
            }
            if (!empty($eff['pierce_shield'])) {
                $extra[] = $en ? 'goes through shields' : 'passa sotto gli scudi';
            }
            if (!empty($eff['cdmg'])) {
                $extra[] = $en ? '+' . duel_text_num((float)$eff['cdmg']) . '% crit damage' : '+' . duel_text_num((float)$eff['cdmg']) . '% di danno critico';
            }
            foreach ($eff['bonus'] ?? [] as $b) {
                $c = rtrim(duel_text_cond($b['if'] ?? [], $lang), ': ');
                $extra[] = '+' . duel_text_num((float)$b['pct']) . '% ' . lcfirst($c);
            }
            if ($extra) {
                $s .= ' (' . implode(', ', $extra) . ')';
            }
            break;
        case 'heal':
            $of = $eff['of'] ?? 'src_max';
            if ($of === 'damage') {
                $s = $en ? "Heals itself for $pct% of the damage dealt" : "Si cura " . duel_text_art('del', (float)($eff['pct'] ?? 0)) . " del danno fatto";
            } elseif ($of === 'target_max') {
                $plural = in_array($to, ['allies', 'allies_other', 'enemies', 'other_enemies'], true);
                $s = $en ? "Heals $acc for $pct% of their max HP" : "Cura $acc " . duel_text_art('del', (float)($eff['pct'] ?? 0)) . ($plural ? " dei loro HP massimi" : " dei suoi HP massimi");
            } elseif ($to === 'self') {
                $s = $en ? "Heals itself for $pct% of its max HP" : "Si cura " . duel_text_art('del', (float)($eff['pct'] ?? 0)) . " dei suoi HP massimi";
            } else {
                $s = $en ? "Heals $acc for $pct% of its own max HP" : "Cura $acc " . duel_text_art('del', (float)($eff['pct'] ?? 0)) . " degli HP massimi di chi la usa";
            }
            break;
        case 'shield':
            $base = ($eff['of'] ?? 'src_max') === 'target_max' ? ($en ? 'their' : 'loro') : ($en ? 'its own' : 'sua');
            $s = $en ? "Shield equal to $pct% of $base max HP to $dat" : "Scudo pari " . duel_text_art('al', (float)($eff['pct'] ?? 0)) . " della " . $base . " vita massima $dat";
            break;
        case 'apply':
            $k = (string)$eff['k'];
            $def = duel_status($k) ?? ['t' => 0];
            $t = (int)($eff['t'] ?? $def['t']);
            $status = duel_text_status($k, $eff, $lang) . duel_text_turns($t, $lang);
            $chance = (float)($eff['chance'] ?? 100);
            if ($k === 'veleno' || ($def['neg'] ?? false) || $to === 'self' || $to === 'allies' || $to === 'allies_other' || $to === 'target') {
                $s = $en ? "Applies $status to $dat" : "Mette $status $dat";
            }
            if ($chance < 100) {
                $s = ($en ? duel_text_num($chance) . "% chance: " : duel_text_num($chance) . "% di probabilità: ") . lcfirst($s);
            }
            break;
        case 'guardian':
            $s = $en
                ? "Guards $acc: takes " . duel_text_num((float)($eff['v'] ?? 40)) . "% of their damage" . duel_text_turns((int)($eff['t'] ?? 2), $lang)
                : "Fa da Guardiano $dat: prende " . duel_text_art('il', (float)($eff['v'] ?? 40))
                    . (in_array($to, ['allies', 'allies_other', 'enemies', 'other_enemies'], true) ? ' dei loro danni' : ' dei suoi danni') . duel_text_turns((int)($eff['t'] ?? 2), $lang);
            break;
        case 'cleanse':
            $n = (int)($eff['n'] ?? 99);
            $s = $n >= 99
                ? ($en ? "Removes all negative effects from $acc" : "Toglie tutti gli stati negativi $dat")
                : ($en ? "Removes $n negative effect" . ($n > 1 ? 's' : '') . " from $acc" : "Toglie " . ($n === 1 ? 'uno stato negativo' : "$n stati negativi") . " $dat");
            break;
        case 'dispel':
            $s = $en ? "Removes all buffs (Immunity included) from $acc" : "Toglie tutti i potenziamenti (Immunità compresa) $dat";
            break;
        case 'steal_buff':
            $s = $en ? "Steals the strongest buff from $acc" : "Ruba il potenziamento più forte $dat";
            break;
        case 'spread':
            $s = $en ? 'Contagion: copies the target\'s negative effects onto the other enemies' : 'Contagio: copia gli stati negativi del bersaglio sugli altri nemici';
            break;
        case 'consume':
            // "di" + articolo: del bersaglio, dell'alleato, degli alleati, di tutti i nemici.
            $gen = str_starts_with($acc, 'il ') ? 'del ' . substr($acc, 3)
                : (str_starts_with($acc, "l'") ? "dell'" . substr($acc, 2) : (str_starts_with($acc, 'gli ') ? 'degli ' . substr($acc, 4) : "di $acc"));
            $s = $en ? "Detonation: Poison and Burn on $acc deal all their damage at once (×1.2)" : "Detonazione: Veleno e Bruciatura $gen fanno subito tutto il loro danno (×1,2)";
            break;
        case 'ps':
            $n = (int)($eff['n'] ?? 1);
            if (($eff['side'] ?? 'own') === 'steal') {
                $s = $en ? "Steals $n TP from the enemy team" : "Ruba $n PS alla squadra nemica";
            } else {
                $s = $en ? "+$n TP to the team" : "+$n PS alla squadra";
            }
            break;
        case 'charge':
            $n = (int)($eff['n'] ?? 0);
            $s = $n >= 100 ? ($en ? "Fills the ultimate charge of $acc" : "Riempie la carica dell'ultimate $dat")
                : ($en ? "+$n ultimate charge to $dat" : "+$n di carica dell'ultimate $dat");
            break;
        case 'advance':
            $s = $en ? "Advances $acc by $pct%" : "Fa avanzare $acc " . duel_text_art('del', (float)($eff['pct'] ?? 0));
            break;
        case 'delay':
            $s = $en ? "Delays $acc by $pct%" : "Ritarda $acc " . duel_text_art('del', (float)($eff['pct'] ?? 0));
            if (isset($eff['chance'])) {
                $s = ($en ? duel_text_num((float)$eff['chance']) . '% chance: ' : duel_text_num((float)$eff['chance']) . '% di probabilità: ') . lcfirst($s);
            }
            break;
        case 'extra_turn':
            $s = $to === 'self' ? ($en ? 'Takes an extra turn' : 'Gioca un turno extra') : ($en ? "Gives an extra turn to $dat" : "Dà un turno extra $dat");
            break;
        case 'terrain':
            $t = duel_terrains()[$eff['k']] ?? ['it' => $eff['k'], 'en' => $eff['k'], 'g' => 2];
            $g = (int)($eff['g'] ?? $t['g']);
            $s = $en ? "Terrain {$t['en']} for $g rounds" : "Terreno {$t['it']} per $g giri";
            break;
        case 'form':
            $f = $ctx['forms'][$eff['k']] ?? [];
            $fname = $f[$lang === 'en' ? 'en' : 'it'] ?? $eff['k'];
            $mods = duel_text_mods($f['mods'] ?? [], $lang);
            $s = ($en ? "Transforms: $fname" : "Si trasforma: $fname") . duel_text_turns((int)($eff['t'] ?? 2), $lang) . ($mods ? " ($mods)" : '');
            break;
        case 'stacks':
            $n = (int)($eff['n'] ?? 1);
            $sname = $ctx['stacks']['name'] ?? ($en ? 'stacks' : 'cariche');
            $s = $n < 0 ? ($en ? "Resets $sname" : "Azzera le cariche di $sname") : ($en ? "+$n $sname" : "+$n $sname");
            break;
        case 'revive':
            $s = $en ? "Revives the last fallen ally with $pct% HP" : "Rianima l'ultimo alleato caduto con " . duel_text_art('il', (float)($eff['pct'] ?? 0)) . " della vita";
            break;
        case 'remove':
            $s = $en ? 'Removes ' . (duel_status((string)$eff['k'])['en'] ?? $eff['k']) : 'Toglie ' . (duel_status((string)$eff['k'])['it'] ?? $eff['k']);
            break;
    }
    if ($s === '') {
        return '';
    }
    if (isset($eff['if'])) {
        $s = duel_text_cond($eff['if'], $lang) . lcfirst($s);
    }
    return $s;
}

/** Modificatori permanenti: "Critico +10, Penetrazione 20%". */
function duel_text_mods(array $mods, string $lang): string
{
    $en = $lang === 'en';
    $labels = [
        'atk_pct' => ['Attacco %s%%', 'Attack %s%%'], 'def_pct' => ['Difesa %s%%', 'Defense %s%%'], 'spd_pct' => ['Velocità %s%%', 'Speed %s%%'],
        'crit' => ['Critico %s', 'Crit rate %s'], 'cdmg' => ['Danno critico %s%%', 'Crit damage %s%%'],
        'prec' => ['Precisione %s', 'Accuracy %s'], 'ten' => ['Tenacia %s', 'Tenacity %s'],
        'dmg_pct' => ['danni %s%%', 'damage %s%%'], 'pen' => ['ignora il %s%% della Difesa', 'ignores %s%% of Defense'],
        'dodge' => ['Schivata %s%%', 'Dodge %s%%'], 'reflect' => ['Riflesso %s%%', 'Reflect %s%%'], 'lifesteal' => ['Rubavita %s%%', 'Lifesteal %s%%'],
        'red_pct' => ['danni subiti −%s%%', 'damage taken −%s%%'], 'red_skill_pct' => ['danni subiti da abilità e ultimate −%s%%', 'damage taken from skills and ultimates −%s%%'],
        'heal_in_pct' => ['cure ricevute %s%%', 'healing received %s%%'], 'heal_out_pct' => ['cure %s%%', 'healing %s%%'],
    ];
    $out = [];
    foreach ($mods as $k => $v) {
        if (!isset($labels[$k]) || !is_numeric($v)) {
            continue;
        }
        $num = duel_text_num(abs((float)$v));
        $signed = in_array($k, ['pen', 'red_pct', 'red_skill_pct', 'dodge', 'reflect', 'lifesteal'], true) ? $num : (($v < 0 ? '−' : '+') . $num);
        $out[] = sprintf($labels[$k][$en ? 1 : 0], $signed);
    }
    return implode(', ', $out);
}

/** Il bonus di un legame a un livello: "HP +6%, ATK +6%" (vedi traits.php). */
function duel_text_trait_bonus(array $bonus, string $lang): string
{
    $en = $lang === 'en';
    $labels = [
        'hp' => ['HP +%s%%', 'HP +%s%%'], 'atk' => ['ATK +%s%%', 'ATK +%s%%'], 'def' => ['DEF +%s%%', 'DEF +%s%%'],
        'crit' => ['Critico +%s', 'Crit rate +%s'], 'cdmg' => ['danno critico +%s%%', 'crit damage +%s%%'], 'spd_pct' => ['Velocità +%s%%', 'Speed +%s%%'],
        'lifesteal' => ['Rubavita %s%%', 'Lifesteal %s%%'], 'dmg_pct' => ['danni +%s%%', 'damage +%s%%'], 'heal_out_pct' => ['cure +%s%%', 'healing +%s%%'],
        'heal_in_pct' => ['cure ricevute +%s%%', 'healing received +%s%%'], 'prec' => ['Precisione +%s', 'Accuracy +%s'],
        'charge' => ['%s di carica iniziale', '%s starting charge'], 'ps' => ['+%s PS iniziale', '+%s starting TP'],
        'regen' => ['Rigenerazione %s%% iniziale', 'starting Regeneration %s%%'],
    ];
    $out = [];
    foreach ($bonus as $k => $v) {
        if (isset($labels[$k]) && is_numeric($v)) {
            $out[] = sprintf($labels[$k][$en ? 1 : 0], duel_text_num((float)$v));
        }
    }
    $text = implode(', ', $out);
    return $en ? (string)preg_replace('/(?<=\d),(?=\d)/', '.', $text) : $text;
}

function duel_text_effects(array $effects, array $ctx, string $lang): string
{
    $parts = [];
    foreach ($effects as $eff) {
        $t = duel_text_effect($eff, $ctx, $lang);
        if ($t !== '') {
            $parts[] = $t;
        }
    }
    return $parts ? implode('. ', $parts) . '.' : '';
}

/** Il testo della passiva: modificatori, aure, condizioni e momenti in cui scatta. */
function duel_text_passive(array $kit, string $lang): string
{
    $en = $lang === 'en';
    $p = $kit['passive'] ?? [];
    $ctx = ['target' => 'enemy', 'forms' => $kit['forms'] ?? [], 'stacks' => $kit['stacks'] ?? null];
    $parts = [];

    if (!empty($p['mods'])) {
        $parts[] = ucfirst(duel_text_mods($p['mods'], $lang)) . '.';
    }
    if (!empty($p['aura'])) {
        $parts[] = ($en ? 'While alive, the whole team gets: ' : 'Finché è in vita, tutta la squadra ha: ') . duel_text_mods($p['aura'], $lang) . '.';
    }
    foreach ($p['scaling'] ?? [] as $sc) {
        $mods = duel_text_mods($sc['mods'] ?? [], $lang);
        switch ($sc['type'] ?? '') {
            case 'missing_hp':
                $parts[] = $en ? ucfirst($mods) . " for every 10% of missing HP." : ucfirst($mods) . ' per ogni 10% di vita persa.';
                break;
            case 'hp_above':
                $parts[] = $en ? "Above {$sc['pct']}% HP: $mods." : "Sopra " . duel_text_art('il', (float)$sc['pct']) . " della vita: $mods.";
                break;
            case 'hp_below':
                $parts[] = $en ? "Below {$sc['pct']}% HP: $mods." : "Sotto " . duel_text_art('il', (float)$sc['pct']) . " della vita: $mods.";
                break;
            case 'team_ps_zero':
                $parts[] = $en ? "When the team has 0 TP: $mods." : "Quando la squadra ha 0 PS: $mods.";
                break;
        }
    }
    foreach ($p['vs'] ?? [] as $vs) {
        $c = rtrim(duel_text_cond($vs['if'] ?? [], $lang), ': ');
        $parts[] = '+' . duel_text_num((float)$vs['dmg_pct']) . ($en ? '% damage ' : '% di danni ') . lcfirst($c) . '.';
    }
    if (!empty($p['block_neg'])) {
        $parts[] = $en ? "Blocks the first {$p['block_neg']} negative effects of the match." : "Blocca i primi {$p['block_neg']} stati negativi della partita.";
    }
    if (!empty($p['counter'])) {
        $parts[] = $en ? "{$p['counter']}% chance to counter with an Attack when hit." : "{$p['counter']}% di rispondere con un Attacco quando viene colpito.";
    }
    if (!empty($p['first_crit'])) {
        $parts[] = $en ? 'Its first hit of the match is always a crit.' : 'Il suo primo colpo della partita è sempre critico.';
    }
    if (!empty($p['cc_feed'])) {
        $parts[] = $en ? "Allies who hit a controlled enemy gain +{$p['cc_feed']} charge." : "Chi della squadra colpisce un nemico sotto controllo prende +{$p['cc_feed']} di carica.";
    }
    if (!empty($p['overheal_shield'])) {
        $parts[] = $en ? "Overhealing becomes Shield (max {$p['overheal_shield']}%)." : "Le cure in eccesso diventano Scudo (massimo {$p['overheal_shield']}%).";
    }
    if (!empty($p['fatal'])) {
        $parts[] = ($en ? 'Once per match, a fatal hit leaves it at 1 HP: ' : 'Una volta per partita, un colpo fatale lo lascia a 1 HP: ')
            . lcfirst(duel_text_effects($p['fatal'], $ctx + ['target' => 'self'], $lang));
    }
    if (!empty($kit['stacks'])) {
        $sk = $kit['stacks'];
        $per = duel_text_mods($sk['per'] ?? [], $lang);
        $parts[] = $en
            ? "{$sk['name']} stacks (max {$sk['max']}): $per each" . (!empty($sk['reset_on_damaged']) ? ', lost when hit.' : '.')
            : "Cariche «{$sk['name']}» (massimo {$sk['max']}): $per ciascuna" . (!empty($sk['reset_on_damaged']) ? ', si azzerano quando viene colpito.' : '.');
    }
    $when = [
        'battle_start' => ['A inizio partita', 'At the start of the match'],
        'turn_start' => ['A inizio turno', 'At the start of its turn'],
        'turn_end' => ['A fine turno', 'At the end of its turn'],
        'after_action' => ['Dopo ogni sua mossa', 'After each of its moves'],
        'on_crit' => ['Quando fa un critico', 'When it lands a crit'],
        'on_kill' => ['Quando mette KO un nemico', 'When it KOs an enemy'],
        'on_damaged' => ['Quando viene colpito', 'When hit'],
        'on_ally_ko' => ['Quando cade un alleato', 'When an ally falls'],
    ];
    foreach ($p['triggers'] ?? [] as $tr) {
        $w = $tr['when'] ?? '';
        if ($w === 'self_low') {
            $head = $en ? "The first time it drops below {$tr['below']}% HP" : "La prima volta che scende sotto " . duel_text_art('il', (float)$tr['below']) . " della vita";
        } elseif ($w === 'ally_low') {
            $head = $en ? "When an ally drops below {$tr['below']}% HP (once per ally)" : "Quando un alleato scende sotto " . duel_text_art('il', (float)$tr['below']) . " della vita (una volta per alleato)";
        } else {
            $head = $when[$w][$en ? 1 : 0] ?? $w;
        }
        if (!empty($tr['every'])) {
            $head = $en ? "Every {$tr['every']} turns" : "Ogni {$tr['every']} turni";
        }
        $body = duel_text_effects($tr['effects'] ?? [], $ctx, $lang);
        if ($body !== '') {
            $parts[] = $head . ': ' . lcfirst($body);
        }
    }
    return implode(' ', $parts);
}

/** Tutti i testi di un kit. */
function duel_kit_text(array $kit, string $lang = 'it'): array
{
    $en = $lang === 'en';
    $out = [];
    foreach (['attack' => 'attacco', 'skill' => 'abilita', 'ultimate' => 'ultimate'] as $key => $move) {
        $m = $kit[$key] ?? null;
        if (!$m) {
            continue;
        }
        $meta = [];
        if ($key === 'attack') {
            $meta[] = $en ? '+1 TP' : '+1 PS';
        } elseif ($key === 'skill') {
            $meta[] = ($m['cost'] ?? 1) . ($en ? ' TP' : ' PS');
            if (!empty($m['cd'])) {
                $meta[] = $en ? "cooldown {$m['cd']}" : "ricarica {$m['cd']}";
            }
        } else {
            $meta[] = $en ? 'full charge' : 'carica piena';
        }
        $ctx = ['target' => $m['target'] ?? 'enemy', 'forms' => $kit['forms'] ?? [], 'stacks' => $kit['stacks'] ?? null];
        $out[$key] = ['name' => (string)($m['name'] ?? ''), 'meta' => implode(' · ', $meta), 'text' => duel_text_effects($m['effects'] ?? [], $ctx, $lang)];
    }
    $out['passive'] = ['name' => (string)($kit['passive']['name'] ?? ''), 'meta' => '', 'text' => duel_text_passive($kit, $lang)];
    $traits = [];
    foreach ($kit['traits'] ?? [] as $t) {
        $traits[] = duel_traits()[$t][$en ? 'en' : 'it'] ?? $t;
    }
    $out['traits'] = $traits;
    if ($en) {
        // I numeri escono all'italiana (3,5): in inglese il decimale e' il punto.
        foreach ($out as $k => $v) {
            if ($k !== 'traits') {
                $out[$k]['text'] = preg_replace('/(?<=\d),(?=\d)/', '.', $v['text']);
            }
        }
    }
    return $out;
}
