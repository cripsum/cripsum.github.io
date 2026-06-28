<?php
/**
 * CRIPSUM GAME CONFIG
 * Contiene i moltiplicatori dei ruoli e la logica delle abilità passive/speciali.
 */

if (!defined('CRIPSUM_GAME_CONFIG')) {
    define('CRIPSUM_GAME_CONFIG', true);
}

/**
 * Moltiplicatori delle statistiche basati sui 10 ruoli di gioco.
 */
function gd_role_multipliers(): array {
    return [
        'Tank'      => ['hp' => 1.45, 'atk' => 0.65, 'def' => 1.50, 'spd' => 0.70, 'crit_rate' => 5,  'crit_dmg' => 120],
        'Bruiser'   => ['hp' => 1.20, 'atk' => 1.00, 'def' => 1.10, 'spd' => 0.90, 'crit_rate' => 10, 'crit_dmg' => 135],
        'DPS'       => ['hp' => 0.90, 'atk' => 1.30, 'def' => 0.75, 'spd' => 1.15, 'crit_rate' => 20, 'crit_dmg' => 150],
        'Burst DPS' => ['hp' => 0.80, 'atk' => 1.50, 'def' => 0.65, 'spd' => 1.25, 'crit_rate' => 25, 'crit_dmg' => 175],
        'Sub DPS'   => ['hp' => 1.00, 'atk' => 1.05, 'def' => 0.90, 'spd' => 1.10, 'crit_rate' => 15, 'crit_dmg' => 140],
        'Support'   => ['hp' => 1.10, 'atk' => 0.80, 'def' => 1.05, 'spd' => 1.15, 'crit_rate' => 8,  'crit_dmg' => 125],
        'Healer'    => ['hp' => 1.15, 'atk' => 0.70, 'def' => 0.95, 'spd' => 1.10, 'crit_rate' => 5,  'crit_dmg' => 120],
        'Controller'=> ['hp' => 1.00, 'atk' => 0.85, 'def' => 0.95, 'spd' => 1.20, 'crit_rate' => 10, 'crit_dmg' => 130],
        'Debuffer'  => ['hp' => 1.00, 'atk' => 0.95, 'def' => 0.95, 'spd' => 1.15, 'crit_rate' => 10, 'crit_dmg' => 130],
        'Buffer'    => ['hp' => 1.05, 'atk' => 0.75, 'def' => 1.00, 'spd' => 1.20, 'crit_rate' => 8,  'crit_dmg' => 125]
    ];
}

/**
 * Calcola le statistiche finali in base a rarità e ruolo.
 */
function gd_calculate_character_stats(string $rarity, string $role): array {
    $base = [
        'comune'      => ['hp' => 100, 'atk' => 30, 'def' => 20, 'spd' => 15],
        'raro'        => ['hp' => 120, 'atk' => 36, 'def' => 24, 'spd' => 17],
        'epico'       => ['hp' => 140, 'atk' => 42, 'def' => 28, 'spd' => 19],
        'leggendario' => ['hp' => 170, 'atk' => 51, 'def' => 34, 'spd' => 21],
        'speciale'    => ['hp' => 185, 'atk' => 55, 'def' => 37, 'spd' => 23],
        'secret'      => ['hp' => 200, 'atk' => 60, 'def' => 40, 'spd' => 25],
        'secret_limited' => ['hp' => 210, 'atk' => 63, 'def' => 42, 'spd' => 26],
        'theone'      => ['hp' => 240, 'atk' => 75, 'def' => 48, 'spd' => 30]
    ];
    $rKey = strtolower(trim($rarity));
    if (strpos($rKey, 'limited') !== false) $rKey = 'secret_limited';
    elseif (strpos($rKey, 'secret') !== false) $rKey = 'secret';
    elseif (strpos($rKey, 'one') !== false) $rKey = 'theone';

    $b = $base[$rKey] ?? $base['comune'];
    $mult = gd_role_multipliers();
    $m = $mult[$role] ?? ['hp' => 1.0, 'atk' => 1.0, 'def' => 1.0, 'spd' => 1.0, 'crit_rate' => 5, 'crit_dmg' => 150];

    return [
        'hp'        => (int)round($b['hp'] * $m['hp']),
        'attack'    => (int)round($b['atk'] * $m['atk']),
        'defense'   => (int)round($b['def'] * $m['def']),
        'speed'     => (int)round($b['spd'] * $m['spd']),
        'crit_rate' => (int)$m['crit_rate'],
        'crit_dmg'  => (int)$m['crit_dmg']
    ];
}

/**
 * Ritorna ruolo, abilità passiva e speciale per un determinato personaggio.
 */
function gd_get_character_config(int $pid, string $rarity, string $nome, string $role = 'DPS'): array {
    $nomeLower = strtolower($nome);
    $rKey = strtolower(trim($rarity));
    if (strpos($rKey, 'limited') !== false) $rKey = 'secret_limited';
    elseif (strpos($rKey, 'secret') !== false) $rKey = 'secret';
    elseif (strpos($rKey, 'one') !== false) $rKey = 'theone';

    // 1. Definizioni specifiche e uniche per i 18 personaggi speciali
    $special_configs = [
        46 => [
            'role' => $role,
            'passive_name' => 'Sussidio di Disoccupazione',
            'passive_desc' => 'Gli attacchi base hanno il 35% di probabilità di rubare 1 Energia. Se ha 0 Energia, la sua Difesa aumenta del 40%.',
            'passive_effect' => ['type' => 'cripsum_unemployed_passive'],
            'special_name' => 'Reddito di Cittadinanza',
            'special_desc' => 'Infligge il 150% ATK come danno, assorbe il 15% degli HP attuali del bersaglio per curarsi e riduce l\'Attacco nemico del 30% per 2 turni.',
            'special_effect' => ['type' => 'cripsum_unemployed_special'],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
        48 => [
            'role' => $role,
            'passive_name' => 'Custode del Cosmo',
            'passive_desc' => 'Se va KO, si rianima immediatamente al 100% degli HP (una volta per match). Inoltre, finché è in vita, aumenta il Crit Rate del team del 25% e il Danno Critico del 50%.',
            'passive_effect' => ['type' => 'the_one_passive'],
            'special_name' => 'Giardino Stellare',
            'special_desc' => 'Infligge danni devastanti (220% ATK) al bersaglio, cura l\'intero team del 40% degli HP max e aumenta il Crit Rate del team del 25% e il Danno Critico del 50% per 3 turni.',
            'special_effect' => ['type' => 'the_one_special'],
            'special_cost' => 3,
            'special_cooldown' => 3
        ],
        49 => [
            'role' => $role,
            'passive_name' => 'Ballo del Trash',
            'passive_desc' => 'Ogni volta che subisce danni, aumenta la Velocità di tutti gli alleati del 10% (max 30%) per 2 turni.',
            'passive_effect' => ['type' => 'sossio_trash_passive'],
            'special_name' => 'Spettacolo di Sossio',
            'special_desc' => 'Cura l\'alleato attivo del 30% degli HP max, ne aumenta l\'Attacco del 35% per 2 turni e gli fornisce 1 Energia.',
            'special_effect' => ['type' => 'sossio_trash_special'],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
        50 => [
            'role' => $role,
            'passive_name' => 'Ritmo Asfissiante',
            'passive_desc' => 'Gli attacchi base hanno il 25% di probabilità di Stordire il bersaglio per 1 turno.',
            'passive_effect' => ['type' => 'manuel_beatbox_passive'],
            'special_name' => 'Drop di Cassa',
            'special_desc' => 'Infligge il 140% ATK come danno e applica Silenzio al bersaglio per 2 turni (impedendogli di usare mosse speciali).',
            'special_effect' => ['type' => 'manuel_beatbox_special'],
            'special_cost' => 2,
            'special_cooldown' => 3
        ],
        64 => [
            'role' => $role,
            'passive_name' => 'Fatti e Logica',
            'passive_desc' => 'Tutti i suoi attacchi ignorano il 30% della Difesa del bersaglio.',
            'passive_effect' => ['type' => 'charlie_kirk_passive'],
            'special_name' => 'Punto di Svolta',
            'special_desc' => 'Infligge il 160% ATK come danno, attiva Provocazione su se stesso per 2 turni e riduce la Difesa del bersaglio del 35% per 2 turni.',
            'special_effect' => ['type' => 'charlie_kirk_special'],
            'special_cost' => 2,
            'special_cooldown' => 3
        ],
        75 => [
            'role' => $role,
            'passive_name' => 'Crittografia End-to-End',
            'passive_desc' => 'È completamente immune a tutti gli effetti di stato negativi e debuff.',
            'passive_effect' => ['type' => 'zakator_opsec_passive'],
            'special_name' => 'Attacco Zero-Day',
            'special_desc' => 'Esegue un attacco fulmineo (240% ATK) che ignora completamente qualsiasi Scudo attivo sul bersaglio.',
            'special_effect' => ['type' => 'zakator_opsec_special'],
            'special_cost' => 3,
            'special_cooldown' => 3
        ],
        76 => [
            'role' => $role,
            'passive_name' => 'Stato di Edging',
            'passive_desc' => 'Quando i suoi HP scendono sotto il 50%, il suo Attacco raddoppia (+100%) ma la sua Velocità si riduce del 20%.',
            'passive_effect' => ['type' => 'christian_gooner_passive'],
            'special_name' => 'Gooning Inarrestabile',
            'special_desc' => 'Infligge il 180% ATK come danno, si cura del 50% del danno inflitto e ottiene uno Scudo pari al 20% dei suoi HP max.',
            'special_effect' => ['type' => 'christian_gooner_special'],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
        86 => [
            'role' => $role,
            'passive_name' => 'Contratto Lucroso',
            'passive_desc' => 'Aumenta il Crit Rate del 20%. Quando sconfigge un nemico, si cura del 40% degli HP max e ottiene 2 Energia.',
            'passive_effect' => ['type' => 'aldrich_mercenary_passive'],
            'special_name' => 'Raffica di Piombo',
            'special_desc' => 'Esegue 3 colpi rapidi da 70% ATK (210% totale) e applica Sanguinamento per 2 turni (15% HP a turno).',
            'special_effect' => ['type' => 'aldrich_mercenary_special'],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
        87 => [
            'role' => $role,
            'passive_name' => 'Spesso Strato di Grasso',
            'passive_desc' => 'Aumenta la sua Difesa del 15% dei suoi HP max. È completamente immune al Congelamento.',
            'passive_effect' => ['type' => 'nauz_tricheco_passive'],
            'special_name' => 'Carica del Tricheco',
            'special_desc' => 'Infligge il 120% ATK (aumentato del 15% della sua Difesa) come danno, attiva Provocazione per 2 turni e ha il 50% di probabilità di Congelare.',
            'special_effect' => ['type' => 'nauz_tricheco_special'],
            'special_cost' => 2,
            'special_cooldown' => 3
        ],
        88 => [
            'role' => $role,
            'passive_name' => 'Lega di Tungsteno',
            'passive_desc' => 'Riflette il 30% di tutti i danni diretti subiti all\'attaccante.',
            'passive_effect' => ['type' => 'tung_god_passive'],
            'special_name' => 'Impatto Orbitale',
            'special_desc' => 'Colpisce il nemico (140% ATK), lo Stordisce per 1 turno e si fornisce uno Scudo pari al 50% dei suoi HP max.',
            'special_effect' => ['type' => 'tung_god_special'],
            'special_cost' => 2,
            'special_cooldown' => 3
        ],
        98 => [
            'role' => $role,
            'passive_name' => 'Contrattacco Furbo',
            'passive_desc' => 'Ha il 35% di probabilità di rispondere con un attacco base immediato quando viene colpito.',
            'passive_effect' => ['type' => 'carmelo_passive'],
            'special_name' => 'Colpo a Sorpresa',
            'special_desc' => 'Infligge il 160% ATK come danno. Se il bersaglio è Stordito o Congelato, infligge il doppio dei danni.',
            'special_effect' => ['type' => 'carmelo_special'],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
        138 => [
            'role' => $role,
            'passive_name' => 'Polvere di Stelle',
            'passive_desc' => 'All\'inizio del suo turno, cura l\'alleato con meno HP del 10% dei suoi HP max.',
            'passive_effect' => ['type' => 'nauz_cosmic_passive'],
            'special_name' => 'Costellazione Protettiva',
            'special_desc' => 'Fornisce a tutti gli alleati uno Scudo pari al 30% dei suoi HP max, rimuove tutti i debuff e aumenta la Difesa del 25% per 2 turni.',
            'special_effect' => ['type' => 'nauz_cosmic_special'],
            'special_cost' => 2,
            'special_cooldown' => 3
        ],
        139 => [
            'role' => $role,
            'passive_name' => 'Nebulosa Aurea',
            'passive_desc' => 'Finché è in vita, aumenta il Crit Rate di tutti gli alleati del 15% e la loro Velocità del 10%.',
            'passive_effect' => ['type' => 'cripsum_cosmic_passive'],
            'special_name' => 'Supernova dell\'Anima',
            'special_desc' => 'Infligge il 150% ATK al nemico, cura tutto il team del 25% degli HP max e fornisce 2 Energia a tutti gli alleati.',
            'special_effect' => ['type' => 'cripsum_cosmic_special'],
            'special_cost' => 2,
            'special_cooldown' => 3
        ],
        140 => [
            'role' => $role,
            'passive_name' => 'Evitamento Aereo',
            'passive_desc' => 'Ha il 25% di probabilità di schivare completamente qualsiasi attacco ricevuto.',
            'passive_effect' => ['type' => 'flight_passive'],
            'special_name' => 'Picchiata Sonica',
            'special_desc' => 'Infligge il 170% ATK come danno, aumenta la propria Velocità del 30% e riduce quella del bersaglio del 30% per 2 turni.',
            'special_effect' => ['type' => 'flight_special'],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
        141 => [
            'role' => $role,
            'passive_name' => 'Stile Elegantissimo',
            'passive_desc' => 'Ogni attacco andato a segno aumenta il suo Attacco del 5% (fino a +25%). Si resetta se subisce danni.',
            'passive_effect' => ['type' => 'dante_passive'],
            'special_name' => 'Devil Trigger',
            'special_desc' => 'Sferra un attacco devastante (230% ATK) che ignora il 30% della Difesa nemica e lo cura del 25% del danno inflitto.',
            'special_effect' => ['type' => 'dante_special'],
            'special_cost' => 3,
            'special_cooldown' => 3
        ],
        142 => [
            'role' => $role,
            'passive_name' => 'Concentrazione Pura',
            'passive_desc' => 'I suoi attacchi base colpiscono due volte (il secondo colpo infligge il 40% del danno).',
            'passive_effect' => ['type' => 'vergil_passive'],
            'special_name' => 'Judgement Cut End',
            'special_desc' => 'Infligge il 200% ATK come danno, ha il 35% di probabilità di Congelare il bersaglio e aumenta il proprio Crit Rate del 30% per 2 turni.',
            'special_effect' => ['type' => 'vergil_special'],
            'special_cost' => 3,
            'special_cooldown' => 3
        ],
        143 => [
            'role' => $role,
            'passive_name' => 'Iron Dome',
            'passive_desc' => 'Riduce del 25% tutti i danni da attacchi speciali subiti da lui e da tutto il suo team.',
            'passive_effect' => ['type' => 'netanyahu_passive'],
            'special_name' => 'Linea Rossa',
            'special_desc' => 'Si fornisce uno Scudo pari al 40% dei suoi HP max, attiva Provocazione per 2 turni e riduce l\'Attacco nemico del 30% per 2 turni.',
            'special_effect' => ['type' => 'netanyahu_special'],
            'special_cost' => 2,
            'special_cooldown' => 3
        ],
        144 => [
            'role' => $role,
            'passive_name' => 'Scudo di Trama',
            'passive_desc' => 'Se subisce un colpo fatale, sopravvive con 1 HP e ottiene uno Scudo pari al 50% dei suoi HP max (una volta per match).',
            'passive_effect' => ['type' => 'protagonista_passive'],
            'special_name' => 'Colpo del Protagonista',
            'special_desc' => 'Infligge il 190% ATK. Se i suoi HP sono sotto il 50%, infligge il 50% di danni in più e si cura del 30% dei suoi HP max.',
            'special_effect' => ['type' => 'protagonista_special'],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
    ];

    if (isset($special_configs[$pid])) {
        return $special_configs[$pid];
    }

    $is_special = ($rKey === 'secret' || $rKey === 'secret_limited' || $rKey === 'theone');

    // Generazione abilità uniche basate sul ruolo per tutti gli altri personaggi
    $passive_name = '';
    $passive_desc = '';
    $passive_effect = [];
    $special_name = '';
    $special_desc = '';
    $special_effect = [];
    $special_cost = 2;
    $special_cooldown = 2;

    switch ($role) {
        case 'Tank':
            if ($is_special) {
                $passive_name = 'Egida Suprema di ' . $nome;
                $passive_desc = 'Inizia la battaglia con uno Scudo leggendario pari al 50% dei suoi HP max.';
                $passive_effect = ['type' => 'shield_at_start', 'pct' => 50];
                
                $special_name = 'Bastione Divino di ' . $nome;
                $special_desc = 'Ottiene uno Scudo pari al 75% dei suoi HP max e applica Provocazione per 2 turni.';
                $special_effect = ['type' => 'taunt_self_heavy'];
                $special_cooldown = 3;
            } else {
                $passive_name = 'Scudo Rigenerativo di ' . $nome;
                $passive_desc = 'Inizia la battaglia con uno Scudo pari al 25% dei suoi HP max.';
                $passive_effect = ['type' => 'shield_at_start', 'pct' => 25];
                
                $special_name = 'Bastione Impenetrabile di ' . $nome;
                $special_desc = 'Ottiene uno Scudo pari al 40% dei suoi HP max e applica Provocazione per 2 turni.';
                $special_effect = ['type' => 'taunt_self'];
                $special_cooldown = 3;
            }
            break;
            
        case 'Bruiser':
            if ($is_special) {
                $passive_name = 'Ira Immortale di ' . $nome;
                $passive_desc = 'Aumenta il proprio Attacco dell\'8% per ogni 10% di HP persi.';
                $passive_effect = ['type' => 'atk_scale_lost_hp_heavy'];
                
                $special_name = 'Lacerazione Divina di ' . $nome;
                $special_desc = 'Infligge il 180% ATK como danno e applica Sanguinamento al bersaglio per 3 turni (perde il 15% degli HP max a turno).';
                $special_effect = ['type' => 'apply_bleed_heavy'];
            } else {
                $passive_name = 'Determinazione di ' . $nome;
                $passive_desc = 'Aumenta il proprio Attacco del 5% per ogni 10% di HP persi.';
                $passive_effect = ['type' => 'atk_scale_lost_hp'];
                
                $special_name = 'Fendente Sanguinante di ' . $nome;
                $special_desc = 'Infligge il 150% ATK come danno e applica Sanguinamento al bersaglio per 2 turni (perde il 15% degli HP max a turno).';
                $special_effect = ['type' => 'apply_bleed'];
            }
            break;
            
        case 'DPS':
            if ($is_special) {
                $passive_name = 'Flusso Etereo di ' . $nome;
                $passive_desc = 'Aumenta il proprio Crit Rate del 15% ogni volta che non mette a segno un colpo critico. Si resetta al verificarsi del critico.';
                $passive_effect = ['type' => 'crit_ramp_on_non_crit_heavy'];
                
                $special_name = 'Danza delle Spade di ' . $nome;
                $special_desc = 'Infligge il 210% ATK come danno. Se il colpo è critico, ricarica immediatamente 2 Energia.';
                $special_effect = ['type' => 'flurry_of_blows_heavy'];
                $special_cost = 3;
                $special_cooldown = 3;
            } else {
                $passive_name = 'Slancio Critico di ' . $nome;
                $passive_desc = 'Aumenta il proprio Crit Rate del 10% ogni volta che non mette a segno un colpo critico. Si resetta al verificarsi del critico.';
                $passive_effect = ['type' => 'crit_ramp_on_non_crit'];
                
                $special_name = 'Tempesta di Colpi di ' . $nome;
                $special_desc = 'Infligge il 180% ATK come danno. Se il colpo è critico, ricarica immediatamente 1 Energia.';
                $special_effect = ['type' => 'flurry_of_blows'];
                $special_cost = 3;
                $special_cooldown = 3;
            }
            break;
            
        case 'Burst DPS':
            if ($is_special) {
                $passive_name = 'Istinto Omicida di ' . $nome;
                $passive_desc = 'Aumenta passivamente il proprio Danno Critico del 65%.';
                $passive_effect = ['type' => 'passive_crit_dmg_buff_heavy'];
                
                $special_name = 'Colpo dell\'Apocalisse di ' . $nome;
                $special_desc = 'Infligge il 250% ATK come danno a bersaglio singolo (senza penalità alla propria difesa).';
                $special_effect = ['type' => 'deadly_strike_heavy'];
                $special_cost = 3;
                $special_cooldown = 3;
            } else {
                $passive_name = 'Precisione Letale di ' . $nome;
                $passive_desc = 'Aumenta passivamente il proprio Danno Critico del 40%.';
                $passive_effect = ['type' => 'passive_crit_dmg_buff'];
                
                $special_name = 'Colpo da Maestro di ' . $nome;
                $special_desc = 'Infligge il 220% ATK come danno a bersaglio singolo, ma riduce la propria Difesa del 25% per 1 turno.';
                $special_effect = ['type' => 'deadly_strike'];
                $special_cost = 3;
                $special_cooldown = 3;
            }
            break;
            
        case 'Sub DPS':
            if ($is_special) {
                $passive_name = 'Cacciatore di Teste di ' . $nome;
                $passive_desc = 'Infligge il 35% di danni in più ai nemici che sono affetti da qualsiasi debuff o stato negativo.';
                $passive_effect = ['type' => 'bonus_dmg_on_debuffed_heavy'];
                
                $special_name = 'Impatto Devastante di ' . $nome;
                $special_desc = 'Infligge il 170% ATK come danno e riduce la Velocità del bersaglio del 35% per 2 turni.';
                $special_effect = ['type' => 'distracting_strike_heavy'];
            } else {
                $passive_name = 'Opportunismo di ' . $nome;
                $passive_desc = 'Infligge il 20% di danni in più ai nemici che sono affetti da qualsiasi debuff o stato negativo.';
                $passive_effect = ['type' => 'bonus_dmg_on_debuffed'];
                
                $special_name = 'Impatto Rallentante di ' . $nome;
                $special_desc = 'Infligge il 140% ATK come danno e riduce la Velocità del bersaglio del 25% per 2 turni.';
                $special_effect = ['type' => 'distracting_strike'];
            }
            break;
            
        case 'Support':
            if ($is_special) {
                $passive_name = 'Santuario Arcano di ' . $nome;
                $passive_desc = 'All\'inizio del match, applica uno Scudo leggendario pari al 30% dei suoi HP max a tutto il team.';
                $passive_effect = ['type' => 'shield_team_at_start', 'pct' => 30];
                
                $special_name = 'Egida del Cosmo di ' . $nome;
                $special_desc = 'Applica uno Scudo pari al 40% degli HP max di questo personaggio a tutti i membri del team.';
                $special_effect = ['type' => 'shield_all_allies_heavy'];
                $special_cooldown = 3;
            } else {
                $passive_name = 'Barriera Protettiva di ' . $nome;
                $passive_desc = 'All\'inizio del match, applica uno Scudo pari al 15% dei suoi HP max a tutto il team.';
                $passive_effect = ['type' => 'shield_team_at_start', 'pct' => 15];
                
                $special_name = 'Fortezza Cristallina di ' . $nome;
                $special_desc = 'Applica uno Scudo pari al 25% degli HP max di questo personaggio a tutti i membri del team.';
                $special_effect = ['type' => 'shield_all_allies'];
                $special_cooldown = 3;
            }
            break;
            
        case 'Healer':
            if ($is_special) {
                $passive_name = 'Sinfonia del Destino di ' . $nome;
                $passive_desc = 'Quando un alleato va KO, lo risuscita immediatamente con il 35% dei suoi HP max (una volta per match).';
                $passive_effect = ['type' => 'destiny_resurrect'];
                
                $special_name = 'Rinascita Celeste di ' . $nome;
                $special_desc = 'Rigenera il 55% degli HP max del personaggio attivo e applica Rigenerazione del 20% a turno per 2 turni.';
                $special_effect = ['type' => 'heal_active_regen_heavy'];
            } else {
                $passive_name = 'Aura Curativa di ' . $nome;
                $passive_desc = 'All\'inizio di ogni suo turno, cura tutti gli alleati attivi del 5% dei loro HP max.';
                $passive_effect = ['type' => 'regen_all_allies'];
                
                $special_name = 'Soffio Vitale di ' . $nome;
                $special_desc = 'Rigenera il 40% degli HP max del personaggio attivo e applica Rigenerazione per 2 turni (cura il 15% a turno).';
                $special_effect = ['type' => 'heal_active_regen'];
            }
            break;
            
        case 'Controller':
            if ($is_special) {
                $passive_name = 'Presenza Eterea di ' . $nome;
                $passive_desc = 'Ha il 30% di probabilità di ottenere un turno extra dopo aver eseguito un\'azione.';
                $passive_effect = ['type' => 'ethereal_double_turn'];
                
                $special_name = 'Rottura Dimensionale di ' . $nome;
                $special_desc = 'Infligge il 150% ATK come danno, ruba fino a 2 Energia al bersaglio e lo Congela per 1 turno al 100%.';
                $special_effect = ['type' => 'dimensional_break_heavy'];
                $special_cost = 3;
                $special_cooldown = 3;
            } else {
                $passive_name = 'Gelo sul Colpo di ' . $nome;
                $passive_desc = 'Gli attacchi base hanno il 15% di probabilità di Congelare il bersaglio per 1 turno.';
                $passive_effect = ['type' => 'freeze_on_hit'];
                
                $special_name = 'Onda d\'Urto di ' . $nome;
                $special_desc = 'Infligge il 120% ATK come danno e ha il 75% di probabilità di Stordire il bersaglio per 1 turno.';
                $special_effect = ['type' => 'stun_target'];
                $special_cooldown = 3;
            }
            break;
            
        case 'Debuffer':
            if ($is_special) {
                $passive_name = 'Contagio Mortale di ' . $nome;
                $passive_desc = 'Gli attacchi base applicano sempre Veleno (15% HP) per 2 turni.';
                $passive_effect = ['type' => 'poison_on_hit_heavy'];
                
                $special_name = 'Piaga Apocalittica di ' . $nome;
                $special_desc = 'Riduce la Difesa del bersaglio del 40% per 2 turni e applica Veleno per 3 turni (15% HP max a turno).';
                $special_effect = ['type' => 'toxic_mist_heavy'];
                $special_cooldown = 3;
            } else {
                $passive_name = 'Tossina di ' . $nome;
                $passive_desc = 'Gli attacchi base applicano sempre Veleno al bersaglio per 2 turni (perde il 10% degli HP max a turno).';
                $passive_effect = ['type' => 'poison_on_hit'];
                
                $special_name = 'Nebbia Corrosiva di ' . $nome;
                $special_desc = 'Riduce la Difesa del bersaglio del 30% per 2 turni e applica Veleno per 3 turni (12% HP max a turno).';
                $special_effect = ['type' => 'toxic_mist'];
                $special_cooldown = 3;
            }
            break;
            
        case 'Buffer':
            if ($is_special) {
                $passive_name = 'Canto del Trionfo di ' . $nome;
                $passive_desc = 'Aumenta l\'Attacco di tutti gli alleati del 15% finché questo personaggio è in campo.';
                $passive_effect = ['type' => 'team_atk_buff_heavy', 'value' => 15];
                
                $special_name = 'Benedizione Stellare di ' . $nome;
                $special_desc = 'Cura tutto il team del 35% degli HP max, rimuove tutti i debuff, aumenta l\'Attacco del 35% e la Velocità del 25% per 2 turni, e fornisce 2 Energia.';
                $special_effect = ['type' => 'stellar_blessing_heavy'];
                $special_cost = 3;
                $special_cooldown = 3;
            } else {
                $passive_name = 'Presenza Ispiratrice di ' . $nome;
                $passive_desc = 'Aumenta l\'Attacco di tutti gli alleati del 10% finché questo personaggio è in campo.';
                $passive_effect = ['type' => 'team_atk_buff', 'value' => 10];
                
                $special_name = 'Canto di Battaglia di ' . $nome;
                $special_desc = 'Aumenta l\'Attacco del team del 25% e la Velocità del 20% per 2 turni, e fornisce 1 Energia ad ogni alleato.';
                $special_effect = ['type' => 'battle_cry'];
                $special_cooldown = 3;
            }
            break;
            
        default:
            $passive_name = 'Abilità di ' . $nome;
            $passive_desc = 'Aumenta le capacità in battaglia.';
            $special_name = 'Attacco di ' . $nome;
            $special_desc = 'Un potente attacco speciale.';
            break;
    }

    return [
        'role' => $role,
        'passive_name' => $passive_name,
        'passive_desc' => $passive_desc,
        'passive_effect' => $passive_effect,
        'special_name' => $special_name,
        'special_desc' => $special_desc,
        'special_effect' => $special_effect,
        'special_cost' => $special_cost,
        'special_cooldown' => $special_cooldown
    ];
}
