<?php
declare(strict_types=1);

/**
 * CONFIGURAZIONE DEL NUOVO SISTEMA DEI DUELLI
 * Contiene i ruoli, le formule di calcolo delle statistiche e le abilità dei personaggi.
 */

// Ritorna le definizioni dei ruoli e i loro moltiplicatori di statistiche
function gd_get_role_config(): array {
    return [
        'Tank' => [
            'name' => 'Tank',
            'emoji' => '🛡️',
            'hp_mult' => 1.45,
            'atk_mult' => 0.65,
            'def_mult' => 1.50,
            'spd_mult' => 0.70,
            'crit_rate' => 5,
            'crit_dmg' => 120,
            'max_energy' => 4,
            'special_cost' => 2,
            'special_cooldown' => 3,
        ],
        'Bruiser' => [
            'name' => 'Bruiser',
            'emoji' => '⚔️🛡️',
            'hp_mult' => 1.20,
            'atk_mult' => 1.00,
            'def_mult' => 1.10,
            'spd_mult' => 0.90,
            'crit_rate' => 10,
            'crit_dmg' => 135,
            'max_energy' => 4,
            'special_cost' => 2,
            'special_cooldown' => 2,
        ],
        'DPS' => [
            'name' => 'DPS',
            'emoji' => '⚔️',
            'hp_mult' => 0.90,
            'atk_mult' => 1.30,
            'def_mult' => 0.75,
            'spd_mult' => 1.15,
            'crit_rate' => 20,
            'crit_dmg' => 150,
            'max_energy' => 4,
            'special_cost' => 2,
            'special_cooldown' => 2,
        ],
        'Burst DPS' => [
            'name' => 'Burst DPS',
            'emoji' => '💥',
            'hp_mult' => 0.80,
            'atk_mult' => 1.50,
            'def_mult' => 0.65,
            'spd_mult' => 1.25,
            'crit_rate' => 25,
            'crit_dmg' => 175,
            'max_energy' => 5,
            'special_cost' => 3,
            'special_cooldown' => 3,
        ],
        'Sub DPS' => [
            'name' => 'Sub DPS',
            'emoji' => '🗡️',
            'hp_mult' => 1.00,
            'atk_mult' => 1.05,
            'def_mult' => 0.90,
            'spd_mult' => 1.10,
            'crit_rate' => 15,
            'crit_dmg' => 140,
            'max_energy' => 4,
            'special_cost' => 2,
            'special_cooldown' => 2,
        ],
        'Support' => [
            'name' => 'Support',
            'emoji' => '🔮',
            'hp_mult' => 1.10,
            'atk_mult' => 0.80,
            'def_mult' => 1.05,
            'spd_mult' => 1.15,
            'crit_rate' => 8,
            'crit_dmg' => 125,
            'max_energy' => 4,
            'special_cost' => 2,
            'special_cooldown' => 3,
        ],
        'Healer' => [
            'name' => 'Healer',
            'emoji' => '💚',
            'hp_mult' => 1.15,
            'atk_mult' => 0.70,
            'def_mult' => 0.95,
            'spd_mult' => 1.10,
            'crit_rate' => 5,
            'crit_dmg' => 120,
            'max_energy' => 4,
            'special_cost' => 2,
            'special_cooldown' => 2,
        ],
        'Controller' => [
            'name' => 'Controller',
            'emoji' => '🌀',
            'hp_mult' => 1.00,
            'atk_mult' => 0.85,
            'def_mult' => 0.95,
            'spd_mult' => 1.20,
            'crit_rate' => 10,
            'crit_dmg' => 130,
            'max_energy' => 5,
            'special_cost' => 3,
            'special_cooldown' => 3,
        ],
        'Debuffer' => [
            'name' => 'Debuffer',
            'emoji' => '💀',
            'hp_mult' => 1.00,
            'atk_mult' => 0.95,
            'def_mult' => 0.95,
            'spd_mult' => 1.15,
            'crit_rate' => 10,
            'crit_dmg' => 130,
            'max_energy' => 4,
            'special_cost' => 2,
            'special_cooldown' => 2,
        ],
        'Buffer' => [
            'name' => 'Buffer',
            'emoji' => '✨',
            'hp_mult' => 1.05,
            'atk_mult' => 0.75,
            'def_mult' => 1.00,
            'spd_mult' => 1.20,
            'crit_rate' => 8,
            'crit_dmg' => 125,
            'max_energy' => 4,
            'special_cost' => 2,
            'special_cooldown' => 2,
        ],
    ];
}

// Ritorna le statistiche base in base a rarità e ruolo
function gd_calculate_character_stats(string $rarity, string $role): array {
    // Budget di punti base per rarità
    $budgets = [
        'comune' => 130,
        'raro' => 150,
        'epico' => 175,
        'leggendario' => 205,
        'speciale' => 225,
        'segreto' => 250,
        'theone' => 280,
    ];
    $rKey = gd_rarity_key($rarity);
    $budget = $budgets[$rKey] ?? $budgets['comune'];

    $roles = gd_get_role_config();
    $r = $roles[$role] ?? $roles['DPS'];

    // Distribuzione base del budget: HP (35%), ATK (35%), DEF (18%), SPD (12%)
    $hp_points = ($budget * 0.35) * $r['hp_mult'];
    $atk_points = ($budget * 0.35) * $r['atk_mult'];
    $def_points = ($budget * 0.18) * $r['def_mult'];
    $spd_points = ($budget * 0.12) * $r['spd_mult'];

    // Conversione punti -> statistiche reali
    $hp = (int)round($hp_points * 2.5 + 50);
    $attack = (int)round($atk_points + 10);
    $defense = (int)round($def_points + 5);
    $speed = (int)round($spd_points + 5);

    return [
        'hp' => $hp,
        'attack' => $attack,
        'defense' => $defense,
        'speed' => $speed,
        'max_energy' => $r['max_energy'],
        'crit_rate' => $r['crit_rate'],
        'crit_dmg' => $r['crit_dmg'],
        'special_cost' => $r['special_cost'],
        'special_cooldown' => $r['special_cooldown'],
        'role' => $role
    ];
}

// Determina il ruolo e le abilità di un personaggio specifico (o generico)
function gd_get_character_config(int $pid, string $rarity, string $nome): array {
    $rKey = gd_rarity_key($rarity);
    $nomeLower = strtolower($nome);

    // 1. Gestione Personaggi Speciali "The One", "Segreto" e "Segreto Limited"
    if ($rKey === 'theone' || strpos($nomeLower, 'the one') !== false) {
        return [
            'role' => 'Burst DPS',
            'passive_name' => 'Orgoglio Divino',
            'passive_desc' => 'Se va KO, si rianima immediatamente una volta per match al 100% degli HP. Inoltre, finché è in vita, aumenta il Crit Rate del team del 25% e il Crit Danno del 50%.',
            'passive_effect' => ['type' => 'the_one_passive'],
            'special_name' => 'Sole Divino',
            'special_desc' => 'Infligge danni devastanti (220% ATK) al bersaglio, cura l\'intero team del 40% degli HP max e aumenta il Crit Rate del team del 25% e il Crit Danno del 50% per 3 turni.',
            'special_effect' => ['type' => 'the_one_special'],
            'special_cost' => 3,
            'special_cooldown' => 3
        ];
    }

    if ($rKey === 'segreto') {
        // Se il nome contiene "limited", è un Segreto Limited
        if (strpos($nomeLower, 'limited') !== false || strpos($nomeLower, 'ltd') !== false || $pid % 2 === 0) {
            return [
                'role' => 'Healer',
                'passive_name' => 'Sinfonia del Destino',
                'passive_desc' => 'Una volta per partita, quando un alleato va KO, lo risuscita immediatamente con il 35% dei suoi HP max.',
                'passive_effect' => ['type' => 'destiny_resurrect'],
                'special_name' => 'Benedizione Stellare',
                'special_desc' => 'Cura tutti gli alleati del 35% dei loro HP max, rimuove tutti i loro debuff e aumenta il loro Attacco e Velocità del 25% per 2 turni.',
                'special_effect' => ['type' => 'stellar_blessing'],
                'special_cost' => 3,
                'special_cooldown' => 3
            ];
        } else {
            return [
                'role' => 'Controller',
                'passive_name' => 'Presenza Eterea',
                'passive_desc' => 'All\'inizio di ogni proprio turno, ha il 30% di probabilità di ottenere un turno extra immediato (Doppio Turno).',
                'passive_effect' => ['type' => 'ethereal_double_turn'],
                'special_name' => 'Rottura Dimensionale',
                'special_desc' => 'Infligge danni (150% ATK), ruba 2 Energia al bersaglio e ha il 50% di probabilità di Congelarlo per 1 turno.',
                'special_effect' => ['type' => 'dimensional_break'],
                'special_cost' => 2,
                'special_cooldown' => 3
            ];
        }
    }

    // 2. Assegnazione dei ruoli in base al nome o ID per i personaggi normali
    // Mappatura euristica basata sul nome del personaggio per renderlo più a tema
    $role = 'DPS';
    if (preg_match('/(tank|scudo|barriera|difesa|guardia|protezione|wall|muro|gigante|golem)/i', $nomeLower)) {
        $role = 'Tank';
    } elseif (preg_match('/(healer|cura|medico|vita|salute|santuario|angelo|prete|chierico)/i', $nomeLower)) {
        $role = 'Healer';
    } elseif (preg_match('/(support|assist|aiuto|magia|evocatore|bardo)/i', $nomeLower)) {
        $role = 'Support';
    } elseif (preg_match('/(buffer|canto|danza|furia|ira|carica|potenziamento)/i', $nomeLower)) {
        $role = 'Buffer';
    } elseif (preg_match('/(debuffer|veleno|tossico|maledizione|ombra|peste|sangue)/i', $nomeLower)) {
        $role = 'Debuffer';
    } elseif (preg_match('/(controller|stun|gelo|ghiaccio|neve|catena|blocco|tempo)/i', $nomeLower)) {
        $role = 'Controller';
    } elseif (preg_match('/(burst|assassino|ninja|lama|critico|tuono|fulmine|fuoco)/i', $nomeLower)) {
        $role = 'Burst DPS';
    } elseif (preg_match('/(bruiser|guerriero|combattente|berserker|ascia|martello)/i', $nomeLower)) {
        $role = 'Bruiser';
    } elseif (preg_match('/(sub|tattico|arcere|freccia|pistola|cecchino)/i', $nomeLower)) {
        $role = 'Sub DPS';
    } else {
        // Assegnazione deterministica basata sull'ID per varietà
        $roles_list = ['Tank', 'Bruiser', 'DPS', 'Burst DPS', 'Sub DPS', 'Support', 'Healer', 'Controller', 'Debuffer', 'Buffer'];
        $role = $roles_list[$pid % count($roles_list)];
    }

    // 3. Abilità generiche basate sul ruolo assegnato
    $generic_skills = [
        'Tank' => [
            'passive_name' => 'Corazza Adamantina',
            'passive_desc' => 'All\'inizio del match, ottiene uno Scudo pari al 30% degli HP max.',
            'passive_effect' => ['type' => 'shield_at_start', 'pct' => 30],
            'special_name' => 'Provocazione Assoluta',
            'special_desc' => 'Ottiene uno Scudo pari al 40% degli HP max e applica Provocazione (Taunt) su se stesso per 2 turni.',
            'special_effect' => ['type' => 'taunt_self', 'shield_pct' => 40, 'duration' => 2],
            'special_cost' => 2,
            'special_cooldown' => 3
        ],
        'Bruiser' => [
            'passive_name' => 'Determinazione del Guerriero',
            'passive_desc' => 'Aumenta l\'Attacco del 5% per ogni 10% di HP mancanti.',
            'passive_effect' => ['type' => 'atk_buff_on_lost_hp', 'val' => 5],
            'special_name' => 'Taglio Lacerante',
            'special_desc' => 'Infligge danni (150% ATK) e applica Sanguinamento (Bleed) al bersaglio per 2 turni (danni nel tempo).',
            'special_effect' => ['type' => 'apply_bleed', 'dmg_pct' => 15, 'duration' => 2, 'atk_mult' => 1.5],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
        'DPS' => [
            'passive_name' => 'Focalizzazione Tattica',
            'passive_desc' => 'Ogni attacco base non critico aumenta il Crit Rate del 10% (max 50%) fino al prossimo colpo critico.',
            'passive_effect' => ['type' => 'crit_rate_ramp', 'val' => 10],
            'special_name' => 'Raffica di Colpi',
            'special_desc' => 'Infligge ingenti danni (180% ATK). Se il colpo è Critico, ricarica 1 Energia.',
            'special_effect' => ['type' => 'flurry_of_blows', 'atk_mult' => 1.8],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
        'Burst DPS' => [
            'passive_name' => 'Punto Debole',
            'passive_desc' => 'Aumenta il Danno Critico di base del 40% (portandolo a 215%).',
            'passive_effect' => ['type' => 'crit_dmg_buff', 'val' => 40],
            'special_name' => 'Colpo Letale',
            'special_desc' => 'Infligge danni estremi (220% ATK), ma riduce la propria Difesa del 25% per 1 turno.',
            'special_effect' => ['type' => 'deadly_strike', 'atk_mult' => 2.2, 'self_debuff_def' => 25, 'duration' => 1],
            'special_cost' => 3,
            'special_cooldown' => 3
        ],
        'Sub DPS' => [
            'passive_name' => 'Opportunismo',
            'passive_desc' => 'Infligge il 20% di danni in più ai nemici affetti da qualsiasi effetto di stato negativo.',
            'passive_effect' => ['type' => 'more_dmg_on_debuffed', 'val' => 20],
            'special_name' => 'Colpo Distraente',
            'special_desc' => 'Infligge danni (140% ATK) e riduce la Velocità del bersaglio del 25% per 2 turni.',
            'special_effect' => ['type' => 'distracting_strike', 'atk_mult' => 1.4, 'debuff_spd' => 25, 'duration' => 2],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
        'Support' => [
            'passive_name' => 'Barriera Protettiva',
            'passive_desc' => 'Alla fine del proprio turno, dona uno Scudo pari al 15% degli HP max all\'alleato con la percentuale di HP più bassa.',
            'passive_effect' => ['type' => 'shield_lowest_ally', 'pct' => 15],
            'special_name' => 'Egida degli Alleati',
            'special_desc' => 'Fornisce uno Scudo pari al 25% degli HP max del Support a tutti i membri del team per 2 turni.',
            'special_effect' => ['type' => 'shield_all_allies', 'pct' => 25, 'duration' => 2],
            'special_cost' => 2,
            'special_cooldown' => 3
        ],
        'Healer' => [
            'passive_name' => 'Aura Curativa',
            'passive_desc' => 'All\'inizio del proprio turno, rigenera il 5% degli HP max a tutti i membri vivi del team.',
            'passive_effect' => ['type' => 'regen_all_allies', 'pct' => 5],
            'special_name' => 'Santuario della Vita',
            'special_desc' => 'Cura il personaggio attivo del 40% degli HP max e applica Rigenerazione (15% HP per turno) per 2 turni.',
            'special_effect' => ['type' => 'heal_active_regen', 'heal_pct' => 40, 'regen_pct' => 15, 'duration' => 2],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
        'Controller' => [
            'passive_name' => 'Sguardo Gelido',
            'passive_desc' => 'Gli attacchi base hanno il 15% di probabilità di Congelare il bersaglio per 1 turno.',
            'passive_effect' => ['type' => 'freeze_on_basic', 'chance' => 15],
            'special_name' => 'Gabbia Temporale',
            'special_desc' => 'Infligge danni (120% ATK) e ha il 75% di probabilità di Stordire il bersaglio per 1 turno.',
            'special_effect' => ['type' => 'stun_target', 'atk_mult' => 1.2, 'chance' => 75, 'duration' => 1],
            'special_cost' => 3,
            'special_cooldown' => 3
        ],
        'Debuffer' => [
            'passive_name' => 'Tocco Corrosivo',
            'passive_desc' => 'Gli attacchi base applicano Veleno per 2 turni (danni pari al 10% dell\'Attacco del Debuffer a inizio turno).',
            'passive_effect' => ['type' => 'poison_on_basic', 'dmg_pct' => 10, 'duration' => 2],
            'special_name' => 'Nebbia Tossica',
            'special_desc' => 'Applica Veleno (3 turni) e riduce la Difesa del bersaglio del 30% per 2 turni.',
            'special_effect' => ['type' => 'toxic_mist', 'debuff_def' => 30, 'poison_pct' => 12, 'duration' => 2, 'poison_duration' => 3],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
        'Buffer' => [
            'passive_name' => 'Presenza Ispiratrice',
            'passive_desc' => 'Aumenta la Velocità di tutti gli alleati del 10% finché questo personaggio è vivo.',
            'passive_effect' => ['type' => 'speed_buff_aura', 'val' => 10],
            'special_name' => 'Grido di Battaglia',
            'special_desc' => 'Aumenta l\'Attacco del 25% e la Velocità del 20% a tutti gli alleati per 2 turni. Ricarica 1 Energia.',
            'special_effect' => ['type' => 'battle_cry', 'atk_buff' => 25, 'spd_buff' => 20, 'energy_gain' => 1, 'duration' => 2],
            'special_cost' => 2,
            'special_cooldown' => 2
        ],
    ];

    return [
        'role' => $role,
        'passive_name' => $generic_skills[$role]['passive_name'],
        'passive_desc' => $generic_skills[$role]['passive_desc'],
        'passive_effect' => $generic_skills[$role]['passive_effect'],
        'special_name' => $generic_skills[$role]['special_name'],
        'special_desc' => $generic_skills[$role]['special_desc'],
        'special_effect' => $generic_skills[$role]['special_effect'],
        'special_cost' => $generic_skills[$role]['special_cost'],
        'special_cooldown' => $generic_skills[$role]['special_cooldown']
    ];
}
