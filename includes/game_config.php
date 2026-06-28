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
    if (strpos($rKey, 'limited') !== false || strpos($rKey, 'limitato') !== false) $rKey = 'secret_limited';
    elseif (strpos($rKey, 'secret') !== false || strpos($rKey, 'segreto') !== false) $rKey = 'secret';
    elseif (strpos($rKey, 'one') !== false) $rKey = 'theone';
    elseif (strpos($rKey, 'speciale') !== false || strpos($rKey, 'special') !== false) $rKey = 'speciale';
    elseif (strpos($rKey, 'leggendario') !== false || strpos($rKey, 'legendary') !== false) $rKey = 'leggendario';
    elseif (strpos($rKey, 'epico') !== false || strpos($rKey, 'epic') !== false) $rKey = 'epico';
    elseif (strpos($rKey, 'raro') !== false || strpos($rKey, 'rare') !== false) $rKey = 'raro';
    else $rKey = 'comune';

    $b = $base[$rKey];
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
    if (strpos($rKey, 'limited') !== false || strpos($rKey, 'limitato') !== false) $rKey = 'secret_limited';
    elseif (strpos($rKey, 'secret') !== false || strpos($rKey, 'segreto') !== false) $rKey = 'secret';
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
            'special_cooldown' => 2,
            'ultimate_name' => 'Sussidio Statale Supremo',
            'ultimate_desc' => 'Ruba 3 Energia al bersaglio, gli infligge il 240% ATK come danno e si cura del 100% del danno inflitto.',
            'ultimate_effect' => ['type' => 'cripsum_unemployed_ultimate']
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
            'special_cooldown' => 3,
            'ultimate_name' => 'Stella della Redenzione',
            'ultimate_desc' => 'Cura tutto il team del 60% degli HP max, fornisce uno scudo pari al 45% degli HP max a tutti gli alleati e aumenta Crit Rate del 35% e Crit Dmg del 75% per 3 turni.',
            'ultimate_effect' => ['type' => 'shorekeeper_ultimate']
        ],
        49 => [
            'role' => $role,
            'passive_name' => 'Triplo Guscio Rosso',
            'passive_desc' => 'Ogni volta che subisce danni, usa un guscio rosso che aumenta la Velocità di tutti gli alleati del 10% (max 30% con il Triplo Guscio) per 2 turni.',
            'passive_effect' => ['type' => 'sossio_trash_passive'],
            'special_name' => 'Guscio Blu Fulminante',
            'special_desc' => 'Cura l\'alleato attivo del 30% degli HP max, ne aumenta l\'Attacco del 35% per 2 turni e gli fornisce 1 Energia.',
            'special_effect' => ['type' => 'sossio_trash_special'],
            'special_cost' => 2,
            'special_cooldown' => 2,
            'ultimate_name' => 'Dodge con la Stella',
            'ultimate_desc' => 'Cura tutto il team del 45% degli HP max, aumenta l\'Attacco del 40% per 3 turni e fornisce 3 Energia a tutti gli alleati.',
            'ultimate_effect' => ['type' => 'sossio_trash_ultimate']
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
            'special_cooldown' => 3,
            'ultimate_name' => 'Beatbox della Distruzione',
            'ultimate_desc' => 'Infligge il 220% ATK a tutti i nemici, Silenzia tutti i nemici per 2 turni e ha il 50% di probabilità di Stordirli per 1 turno.',
            'ultimate_effect' => ['type' => 'manuel_beatbox_ultimate']
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
            'special_cooldown' => 3,
            'ultimate_name' => 'Dibattito Devastante',
            'ultimate_desc' => 'Riduce la Difesa di tutti i nemici del 50% per 3 turni, attiva Provocazione su se stesso per 3 turni e si fornisce uno Scudo pari al 60% dei suoi HP max.',
            'ultimate_effect' => ['type' => 'charlie_kirk_ultimate']
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
            'special_cooldown' => 3,
            'ultimate_name' => 'Cyber Apocalypse',
            'ultimate_desc' => 'Sferra un attacco cybernetico (300% ATK) a bersaglio singolo che ignora la Difesa e gli Scudi, e Silenzia il bersaglio per 3 turni.',
            'ultimate_effect' => ['type' => 'zakator_opsec_ultimate']
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
            'special_cooldown' => 2,
            'ultimate_name' => 'Gooning Infinito',
            'ultimate_desc' => 'Cura se stesso del 50% degli HP max, ottiene uno Scudo pari al 40% degli HP max e aumenta il proprio Attacco del 50% per 3 turni.',
            'ultimate_effect' => ['type' => 'christian_gooner_ultimate']
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
            'special_cooldown' => 2,
            'ultimate_name' => 'Esecuzione Tattica',
            'ultimate_desc' => 'Infligge il 350% ATK a bersaglio singolo. Se il bersaglio ha meno del 50% HP, il danno è raddoppiato. Se sconfigge il bersaglio, si cura al 100% HP.',
            'ultimate_effect' => ['type' => 'aldrich_mercenary_ultimate']
        ],
        87 => [
            'role' => 'Healer',
            'passive_name' => 'Pelle Impermeabile',
            'passive_desc' => 'All\'inizio del suo turno, cura tutti gli alleati attivi del 6% degli HP max. Nauz riceve il 30% in più da tutte le fonti di cura.',
            'passive_effect' => ['type' => 'nauz_tricheco_passive'],
            'special_name' => 'Bolla Rigenerativa',
            'special_desc' => 'Cura l\'alleato attivo del 45% degli HP max e gli fornisce uno Scudo pari al 25% degli HP max di Nauz.',
            'special_effect' => ['type' => 'nauz_tricheco_special'],
            'special_cost' => 2,
            'special_cooldown' => 2,
            'ultimate_name' => 'Marea della Rinascita',
            'ultimate_desc' => 'Cura l\'intero team del 60% degli HP max, rimuove tutti i debuff e applica Immunità e Rigenerazione del 15% per 3 turni.',
            'ultimate_effect' => ['type' => 'nauz_tricheco_ultimate']
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
            'special_cooldown' => 3,
            'ultimate_name' => 'Cataclisma del Tungsteno',
            'ultimate_desc' => 'Infligge il 250% ATK a tutti i nemici, Stordisce tutti i nemici per 1 turno e fornisce a Tung God uno Scudo pari al 70% dei suoi HP max.',
            'ultimate_effect' => ['type' => 'tung_god_ultimate']
        ],
        98 => [
            'role' => $role,
            'passive_name' => 'Calcolo della Marionetta',
            'passive_desc' => 'Manipola il flusso del combattimento: ha il 35% di probabilità di rispondere con un contrattacco base immediato quando viene colpito.',
            'passive_effect' => ['type' => 'carmelo_passive'],
            'special_name' => 'Stratega dell\'Ombra',
            'special_desc' => 'Esegue un piano segreto che infligge il 160% ATK come danno. Se il bersaglio è Stordito o Congelato, il danno è raddoppiato (colpo calcolato).',
            'special_effect' => ['type' => 'carmelo_special'],
            'special_cost' => 2,
            'special_cooldown' => 2,
            'ultimate_name' => 'Stanza Bianca',
            'ultimate_desc' => 'Rinchiude il bersaglio nella Stanza Bianca: infligge il 300% ATK a bersaglio singolo. Se il bersaglio è Stordito o Congelato, infligge il 150% di danni in più e rimuove tutti i suoi buff.',
            'ultimate_effect' => ['type' => 'carmelo_ultimate']
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
            'special_cooldown' => 3,
            'ultimate_name' => 'Eclissi Cosmica',
            'ultimate_desc' => 'Fornisce a tutto il team uno Scudo pari al 50% dei suoi HP max, rende tutti gli alleati Immuni per 2 turni e cura l\'alleato attivo del 40% degli HP max.',
            'ultimate_effect' => ['type' => 'nauz_cosmic_ultimate']
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
            'special_cooldown' => 3,
            'ultimate_name' => 'Collasso della Supernova',
            'ultimate_desc' => 'Infligge il 300% ATK come danno a tutti i nemici, cura tutto il team del 40% degli HP max e ricarica 3 Energia a tutti gli alleati.',
            'ultimate_effect' => ['type' => 'cripsum_cosmic_ultimate']
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
            'special_cooldown' => 2,
            'ultimate_name' => 'Super-Picchiata Stratosferica',
            'ultimate_desc' => 'Infligge il 280% ATK al bersaglio, riduce la sua Velocità del 50% per 3 turni, e fornisce a Flight un turno extra immediato.',
            'ultimate_effect' => ['type' => 'flight_ultimate']
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
            'special_cooldown' => 3,
            'ultimate_name' => 'Sin Devil Trigger',
            'ultimate_desc' => 'Sferra un attacco devastante (350% ATK) che ignora il 50% della Difesa nemica, cura Dante del 50% del danno inflitto e gli fornisce Immunità per 2 turni.',
            'ultimate_effect' => ['type' => 'dante_ultimate']
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
            'special_cooldown' => 3,
            'ultimate_name' => 'Deep Stinger',
            'ultimate_desc' => 'Sferra una tempesta di tagli dimensionali (320% ATK) sul bersaglio, lo Congela al 100% per 1 turno e aumenta il proprio Crit Rate del 40% e Crit Dmg del 80% per 3 turni.',
            'ultimate_effect' => ['type' => 'vergil_ultimate']
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
            'special_cooldown' => 3,
            'ultimate_name' => 'Furia del Giudizio',
            'ultimate_desc' => 'Si fornisce uno Scudo pari al 80% dei suoi HP max, attiva Provocazione per 3 turni, e riduce l\'Attacco di tutti i nemici del 45% per 3 turni.',
            'ultimate_effect' => ['type' => 'netanyahu_ultimate']
        ],
        144 => [
            'role' => $role,
            'passive_name' => 'Pelle del Tirannosauro Isekai',
            'passive_desc' => 'Rianimato in un altro mondo con la pelle d\'acciaio del T-Rex: se subisce un colpo fatale, sopravvive con 1 HP e ottiene uno Scudo pari al 50% dei suoi HP max (una volta per match).',
            'passive_effect' => ['type' => 'protagonista_passive'],
            'special_name' => 'Carica del Triceratopo',
            'special_desc' => 'Carica con la forza di un Triceratopo corazzato infliggendo il 190% ATK. Se i suoi HP sono sotto il 50%, il danno aumenta del 50% e si cura del 30% degli HP max.',
            'special_effect' => ['type' => 'protagonista_special'],
            'special_cost' => 2,
            'special_cooldown' => 2,
            'ultimate_name' => 'Meteora del Cratere Cretaceo',
            'ultimate_desc' => 'Evoca l\'estinzione di massa dei dinosauri: sferra un colpo apocalittico (300% ATK). Se i suoi HP sono sotto il 50%, il danno è raddoppiato e si cura al 100% degli HP max.',
            'ultimate_effect' => ['type' => 'protagonista_ultimate']
        ],
    ];

    if (isset($special_configs[$pid])) {
        return $special_configs[$pid];
    }

    $is_special = ($rKey === 'secret' || $rKey === 'secret_limited' || $rKey === 'theone');

    $rKeyNorm = 'comune';
    if (strpos($rKey, 'one') !== false) $rKeyNorm = 'theone';
    elseif (strpos($rKey, 'limited') !== false || strpos($rKey, 'limitato') !== false) $rKeyNorm = 'secret_limited';
    elseif (strpos($rKey, 'secret') !== false || strpos($rKey, 'segreto') !== false) $rKeyNorm = 'secret';
    elseif (strpos($rKey, 'speciale') !== false || strpos($rKey, 'special') !== false) $rKeyNorm = 'speciale';
    elseif (strpos($rKey, 'leggendario') !== false || strpos($rKey, 'legendary') !== false) $rKeyNorm = 'leggendario';
    elseif (strpos($rKey, 'epico') !== false || strpos($rKey, 'epic') !== false) $rKeyNorm = 'epico';
    elseif (strpos($rKey, 'raro') !== false || strpos($rKey, 'rare') !== false) $rKeyNorm = 'raro';

    $rarity_scales = [
        'comune' => 0,
        'raro' => 1,
        'epico' => 2,
        'leggendario' => 3,
        'speciale' => 4,
        'secret' => 5,
        'secret_limited' => 5,
        'theone' => 5
    ];
    $tier = $rarity_scales[$rKeyNorm] ?? 0;

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
            $pct = [15, 20, 25, 30, 40, 50][$tier];
            $sh = [25, 30, 35, 40, 55, 75][$tier];
            
            $passive_name = 'Scudo Rigenerativo di ' . $nome;
            $passive_desc = "Inizia la battaglia con uno Scudo pari al {$pct}% dei suoi HP max.";
            $passive_effect = ['type' => 'shield_at_start', 'pct' => $pct];
            
            $special_name = 'Bastione di ' . $nome;
            $special_desc = "Ottiene uno Scudo pari al {$sh}% dei suoi HP max e applica Provocazione per 2 turni.";
            $special_effect = ['type' => ($tier >= 4 ? 'taunt_self_heavy' : 'taunt_self'), 'pct' => $sh];
            $special_cooldown = 3;
            break;
            
        case 'Bruiser':
            $pct = [3, 4, 5, 6, 7, 8][$tier];
            $dmg = [120, 135, 150, 165, 175, 190][$tier];
            $bleed = [10, 12, 15, 15, 15, 18][$tier];
            $dur = ($tier >= 4) ? 3 : 2;
            
            $passive_name = 'Determinazione di ' . $nome;
            $passive_desc = "Aumenta il proprio Attacco del {$pct}% per ogni 10% di HP persi.";
            $passive_effect = ['type' => ($tier >= 4 ? 'atk_scale_lost_hp_heavy' : 'atk_scale_lost_hp'), 'pct' => $pct];
            
            $special_name = 'Fendente di ' . $nome;
            $special_desc = "Infligge il {$dmg}% ATK come danno e applica Sanguinamento per {$dur} turni (perde il {$bleed}% degli HP max a turno).";
            $special_effect = ['type' => ($tier >= 4 ? 'apply_bleed_heavy' : 'apply_bleed'), 'dmg_pct' => $dmg, 'bleed_pct' => $bleed, 'duration' => $dur];
            break;
            
        case 'DPS':
            $crit = [6, 8, 10, 12, 14, 16][$tier];
            $dmg = [150, 165, 180, 195, 205, 220][$tier];
            $energy = [1, 1, 1, 2, 2, 2][$tier];
            
            $passive_name = 'Slancio Critico di ' . $nome;
            $passive_desc = "Aumenta il proprio Crit Rate del {$crit}% ogni volta che non mette a segno un colpo critico. Si resetta al verificarsi del critico.";
            $passive_effect = ['type' => ($tier >= 4 ? 'crit_ramp_on_non_crit_heavy' : 'crit_ramp_on_non_crit'), 'value' => $crit];
            
            $special_name = 'Tempesta di Colpi di ' . $nome;
            $special_desc = "Infligge il {$dmg}% ATK come danno. Se il colpo è critico, ricarica immediatamente {$energy} Energia.";
            $special_effect = ['type' => ($tier >= 4 ? 'flurry_of_blows_heavy' : 'flurry_of_blows'), 'dmg_pct' => $dmg, 'energy' => $energy];
            $special_cost = 3;
            $special_cooldown = 3;
            break;
            
        case 'Burst DPS':
            $cdmg = [25, 30, 35, 40, 50, 65][$tier];
            $dmg = [190, 205, 220, 235, 245, 260][$tier];
            $penalty = [30, 25, 20, 15, 10, 0][$tier];
            
            $passive_name = 'Precisione Letale di ' . $nome;
            $passive_desc = "Aumenta passivamente il proprio Danno Critico del {$cdmg}%.";
            $passive_effect = ['type' => ($tier >= 4 ? 'passive_crit_dmg_buff_heavy' : 'passive_crit_dmg_buff'), 'value' => $cdmg];
            
            $special_name = 'Colpo da Maestro di ' . $nome;
            $special_desc = ($penalty > 0)
                ? "Infligge il {$dmg}% ATK come danno a bersaglio singolo, ma riduce la propria Difesa del {$penalty}% per 1 turno."
                : "Infligge il {$dmg}% ATK come danno a bersaglio singolo (senza penalità alla propria difesa).";
            $special_effect = ['type' => ($tier >= 4 ? 'deadly_strike_heavy' : 'deadly_strike'), 'dmg_pct' => $dmg, 'penalty' => $penalty];
            $special_cost = 3;
            $special_cooldown = 3;
            break;
            
        case 'Sub DPS':
            $dmg_bonus = [12, 16, 20, 25, 30, 35][$tier];
            $dmg = [120, 130, 140, 155, 165, 180][$tier];
            $spd_red = [15, 20, 25, 30, 30, 35][$tier];
            
            $passive_name = 'Opportunismo di ' . $nome;
            $passive_desc = "Infligge il {$dmg_bonus}% di danni in più ai nemici che sono affetti da qualsiasi debuff o stato negativo.";
            $passive_effect = ['type' => ($tier >= 4 ? 'bonus_dmg_on_debuffed_heavy' : 'bonus_dmg_on_debuffed'), 'value' => $dmg_bonus];
            
            $special_name = 'Impatto Rallentante di ' . $nome;
            $special_desc = "Infligge il {$dmg}% ATK come danno e riduce la Velocità del bersaglio del {$spd_red}% per 2 turni.";
            $special_effect = ['type' => ($tier >= 4 ? 'distracting_strike_heavy' : 'distracting_strike'), 'dmg_pct' => $dmg, 'speed_reduction' => $spd_red];
            break;
            
        case 'Support':
            $pct = [8, 12, 15, 20, 25, 30][$tier];
            $sh = [15, 20, 25, 30, 35, 40][$tier];
            
            $passive_name = 'Barriera Protettiva di ' . $nome;
            $passive_desc = "All'inizio del match, applica uno Scudo pari al {$pct}% dei suoi HP max a tutto il team.";
            $passive_effect = ['type' => 'shield_team_at_start', 'pct' => $pct];
            
            $special_name = 'Fortezza Cristallina di ' . $nome;
            $special_desc = "Applica uno Scudo pari al {$sh}% degli HP max di questo personaggio a tutti i membri del team.";
            $special_effect = ['type' => ($tier >= 4 ? 'shield_all_allies_heavy' : 'shield_all_allies'), 'pct' => $sh];
            $special_cooldown = 3;
            break;
            
        case 'Healer':
            $val = [2, 3, 4, 5, 20, 35][$tier];
            $heal = [30, 35, 40, 45, 50, 55][$tier];
            $regen = [10, 12, 15, 18, 20, 22][$tier];
            
            if ($tier >= 4) {
                $passive_name = 'Sinfonia del Destino di ' . $nome;
                $passive_desc = "Quando un alleato va KO, lo risuscita immediatamente con il {$val}% dei suoi HP max (una volta per match).";
                $passive_effect = ['type' => 'destiny_resurrect', 'value' => $val];
            } else {
                $passive_name = 'Aura Curativa di ' . $nome;
                $passive_desc = "All'inizio di ogni suo turno, cura tutti gli alleati attivi del {$val}% dei loro HP max.";
                $passive_effect = ['type' => 'regen_all_allies', 'value' => $val];
            }
            
            $special_name = 'Soffio Vitale di ' . $nome;
            $special_desc = "Rigenera il {$heal}% degli HP max del personaggio attivo e applica Rigenerazione del {$regen}% a turno per 2 turni.";
            $special_effect = ['type' => ($tier >= 4 ? 'heal_active_regen_heavy' : 'heal_active_regen'), 'heal_pct' => $heal, 'regen_pct' => $regen];
            break;
            
        case 'Controller':
            $val = [8, 12, 15, 20, 25, 30][$tier];
            $dmg = [100, 110, 120, 135, 145, 160][$tier];
            $chance = [50, 65, 75, 85, 100, 100][$tier];
            
            if ($tier >= 4) {
                $passive_name = 'Presenza Eterea di ' . $nome;
                $passive_desc = "Ha il {$val}% di probabilità di ottenere un turno extra dopo aver eseguito un'azione.";
                $passive_effect = ['type' => 'ethereal_double_turn', 'chance' => $val];
            } else {
                $passive_name = 'Gelo sul Colpo di ' . $nome;
                $passive_desc = "Gli attacchi base hanno il {$val}% di probabilità di Congelare il bersaglio per 1 turno.";
                $passive_effect = ['type' => 'freeze_on_hit', 'chance' => $val];
            }
            
            $special_name = 'Onda d\'Urto di ' . $nome;
            $special_desc = ($tier >= 4)
                ? "Infligge il {$dmg}% ATK come danno, ruba fino a 2 Energia al bersaglio e lo Congela per 1 turno al 100%."
                : "Infligge il {$dmg}% ATK come danno e ha il {$chance}% di probabilità di Stordire il bersaglio per 1 turno.";
            $special_effect = ['type' => ($tier >= 4 ? 'dimensional_break_heavy' : 'stun_target'), 'dmg_pct' => $dmg, 'chance' => $chance];
            $special_cooldown = 3;
            break;
            
        case 'Debuffer':
            $chance = [8, 12, 15, 100, 100, 100][$tier];
            $poison = [10, 10, 10, 12, 15, 15][$tier];
            $def_red = [20, 25, 30, 35, 40, 45][$tier];
            
            $passive_name = 'Tossina di ' . $nome;
            $passive_desc = ($chance == 100)
                ? "Gli attacchi base applicano sempre Veleno ({$poison}% HP) per 2 turni."
                : "Gli attacchi base hanno il {$chance}% di probabilità di applicare Veleno ({$poison}% HP) per 2 turni.";
            $passive_effect = ['type' => ($tier >= 3 ? 'poison_on_hit_heavy' : 'poison_on_hit'), 'chance' => $chance, 'value' => $poison];
            
            $special_name = 'Nebbia Corrosiva di ' . $nome;
            $special_desc = "Riduce la Difesa del bersaglio del {$def_red}% per 2 turni e applica Veleno per 3 turni ({$poison}% HP max a turno).";
            $special_effect = ['type' => ($tier >= 4 ? 'toxic_mist_heavy' : 'toxic_mist'), 'def_reduction' => $def_red, 'poison_value' => $poison];
            $special_cooldown = 3;
            break;
            
        case 'Buffer':
            $val = [6, 8, 10, 12, 15, 15][$tier];
            $buff = [15, 20, 25, 30, 30, 35][$tier];
            $energy = [1, 1, 1, 1, 2, 2][$tier];
            
            $passive_name = 'Presenza Ispiratrice di ' . $nome;
            $passive_desc = "Aumenta l'Attacco di tutti gli alleati del {$val}% finché questo personaggio è in campo.";
            $passive_effect = ['type' => ($tier >= 4 ? 'team_atk_buff_heavy' : 'team_atk_buff'), 'value' => $val];
            
            $special_name = 'Canto di Battaglia di ' . $nome;
            $special_desc = ($tier >= 4)
                ? "Cura tutto il team del 25% degli HP max, rimuove tutti i debuff, aumenta l'Attacco del {$buff}% e la Velocità del 25% per 2 turni, e fornisce {$energy} Energia."
                : "Aumenta l'Attacco del team del {$buff}% e la Velocità del 20% per 2 turni, e fornisce {$energy} Energia ad ogni alleato.";
            $special_effect = ['type' => ($tier >= 4 ? 'stellar_blessing_heavy' : 'battle_cry'), 'buff_value' => $buff, 'energy' => $energy];
            $special_cooldown = 3;
            break;
            
        default:
            $passive_name = 'Abilità di ' . $nome;
            $passive_desc = 'Aumenta le capacità in battaglia.';
            $special_name = 'Attacco di ' . $nome;
            $special_desc = 'Un potente attacco speciale.';
            break;
    }

    // Ultimate Generica per Segreti / The One
    $ultimate_name = null;
    $ultimate_desc = null;
    $ultimate_effect = null;

    if ($is_special) {
        switch ($role) {
            case 'Tank':
                $ultimate_name = 'Egida Suprema dell\'Apocalisse';
                $ultimate_desc = 'Fornisce a tutto il team uno Scudo pari al 60% degli HP max di questo personaggio e attiva Provocazione per 3 turni.';
                $ultimate_effect = ['type' => 'generic_tank_ultimate'];
                break;
            case 'Bruiser':
                $ultimate_name = 'Ira del Colosso';
                $ultimate_desc = 'Infligge il 280% ATK come danno, applica Sanguinamento pesante (20% HP a turno per 3 turni) e aumenta l\'Attacco del 40% per 3 turni.';
                $ultimate_effect = ['type' => 'generic_bruiser_ultimate'];
                break;
            case 'DPS':
                $ultimate_name = 'Lama dell\'Infinito';
                $ultimate_desc = 'Infligge il 320% ATK come danno a bersaglio singolo. Se fa critico, ricarica completamente la propria Energia al 100%.';
                $ultimate_effect = ['type' => 'generic_dps_ultimate'];
                break;
            case 'Burst DPS':
                $ultimate_name = 'Esecuzione Finale';
                $ultimate_desc = 'Sferra un attacco devastante da 380% ATK a bersaglio singolo che ignora il 40% della Difesa nemica.';
                $ultimate_effect = ['type' => 'generic_burstdps_ultimate'];
                break;
            case 'Sub DPS':
                $ultimate_name = 'Colpo di Grazia';
                $ultimate_desc = 'Infligge il 260% ATK come danno. Aumenta il danno del 50% se il bersaglio ha debuff, e riduce la Velocità del bersaglio del 50% per 3 turni.';
                $ultimate_effect = ['type' => 'generic_subdps_ultimate'];
                break;
            case 'Support':
                $ultimate_name = 'Santuario Celestiale';
                $ultimate_desc = 'Applica uno Scudo pari al 50% degli HP max a tutti gli alleati e aumenta la loro Difesa del 40% per 3 turni.';
                $ultimate_effect = ['type' => 'generic_support_ultimate'];
                break;
            case 'Healer':
                $ultimate_name = 'Soffio della Resurrezione';
                $ultimate_desc = 'Cura tutto il team del 50% degli HP max, resuscita eventuali alleati KO al 30% HP ed applica Rigenerazione del 15% per 3 turni.';
                $ultimate_effect = ['type' => 'generic_healer_ultimate'];
                break;
            case 'Controller':
                $ultimate_name = 'Gelo Assoluto';
                $ultimate_desc = 'Infligge il 220% ATK come danno, Congela il bersaglio al 100% per 2 turni e gli sottrae 3 Energia.';
                $ultimate_effect = ['type' => 'generic_controller_ultimate'];
                break;
            case 'Debuffer':
                $ultimate_name = 'Maledizione Eterna';
                $ultimate_desc = 'Riduce l\'Attacco del bersaglio del 40%, la sua Difesa del 40% per 3 turni, e applica Veleno pesante (20% HP a turno per 3 turni).';
                $ultimate_effect = ['type' => 'generic_debuffer_ultimate'];
                break;
            case 'Buffer':
                $ultimate_name = 'Inno degli Dei';
                $ultimate_desc = 'Aumenta l\'Attacco di tutti gli alleati del 40%, la loro Velocità del 30% per 3 turni, e fornisce 3 Energia a ciascuno.';
                $ultimate_effect = ['type' => 'generic_buffer_ultimate'];
                break;
        }
    }

    // Se è un personaggio di rarità Speciale (tier 4), sovrascrivi i nomi con abilità a tema
    if ($tier === 4) {
        $custom_speciali = [
            4 => [
                'passive' => 'Debug in Produzione',
                'special' => 'Patch d\'Emergenza'
            ],
            34 => [
                'passive' => 'Riciclo Creativo',
                'special' => 'Lancio del Cestino'
            ],
            40 => [
                'passive' => 'Riscaldamento Muscolare',
                'special' => 'Capriola Acrobatica'
            ],
            43 => [
                'passive' => 'Scatto del Cossu',
                'special' => 'Fendente del Cossu'
            ],
            51 => [
                'passive' => 'Entrata in Scena',
                'special' => 'Spettacolo Pirotecnico'
            ],
            77 => [
                'passive' => 'Ragebait Professionale',
                'special' => 'Provocazione di Massa'
            ],
            89 => [
                'passive' => 'Fusa Protettive',
                'special' => 'Graffio Fulmineo'
            ],
            90 => [
                'passive' => 'Presenza Misteriosa',
                'special' => 'Colpo di Mantello'
            ],
            92 => [
                'passive' => 'Vibrazione Sonora',
                'special' => 'Urlo Melodico'
            ],
            103 => [
                'passive' => 'Innesco Rapido',
                'special' => 'Carica Esplosiva'
            ],
            112 => [
                'passive' => 'Portamento Reale',
                'special' => 'Tributo del Re'
            ],
            136 => [
                'passive' => 'Flusso Sburevole',
                'special' => 'Colpo Incontenibile'
            ]
        ];
        
        if (isset($custom_speciali[$pid])) {
            $passive_name = $custom_speciali[$pid]['passive'];
            $special_name = $custom_speciali[$pid]['special'];
        } else {
            // Fallback tematico basato sul ruolo
            $role_templates = [
                'Tank' => [
                    'passive' => 'Egida di ' . $nome,
                    'special' => 'Scudo Supremo di ' . $nome
                ],
                'Bruiser' => [
                    'passive' => 'Ira Incontenibile di ' . $nome,
                    'special' => 'Fendente Cruento di ' . $nome
                ],
                'DPS' => [
                    'passive' => 'Focalizzazione di ' . $nome,
                    'special' => 'Raffica Letale di ' . $nome
                ],
                'Burst DPS' => [
                    'passive' => 'Sovraccarico di ' . $nome,
                    'special' => 'Impatto Devastante di ' . $nome
                ],
                'Sub DPS' => [
                    'passive' => 'Opportunismo Letale di ' . $nome,
                    'special' => 'Colpo Rapido di ' . $nome
                ],
                'Support' => [
                    'passive' => 'Aura Protettiva di ' . $nome,
                    'special' => 'Barriera di Luce di ' . $nome
                ],
                'Healer' => [
                    'passive' => 'Sorgente Vitale di ' . $nome,
                    'special' => 'Cura Miracolosa di ' . $nome
                ],
                'Controller' => [
                    'passive' => 'Presenza Rallentante di ' . $nome,
                    'special' => 'Gelo di ' . $nome
                ],
                'Debuffer' => [
                    'passive' => 'Tossina Letale di ' . $nome,
                    'special' => 'Maledizione di ' . $nome
                ],
                'Buffer' => [
                    'passive' => 'Ispirazione di ' . $nome,
                    'special' => 'Canto della Vittoria di ' . $nome
                ]
            ];
            
            if (isset($role_templates[$role])) {
                $passive_name = $role_templates[$role]['passive'];
                $special_name = $role_templates[$role]['special'];
            }
        }
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
        'special_cooldown' => $special_cooldown,
        'ultimate_name' => $ultimate_name,
        'ultimate_desc' => $ultimate_desc,
        'ultimate_effect' => $ultimate_effect
    ];
}
