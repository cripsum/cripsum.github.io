<?php
/**
 * CRIPSUM GAME CONFIG GENERATOR
 * Esegui questo script dal browser (es. https://cripsum.com/generate_game_config.php)
 * per generare automaticamente il file includes/game_config.php con statistiche,
 * ruoli, passive e speciali UNICI per OGNI personaggio presente nel database.
 */

// Disabilita la cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Carica il bootstrap per la connessione al database
$bootstrap_path = __DIR__ . '/api/game/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    die("Errore: Impossibile trovare api/game/bootstrap.php. Assicurati che lo script sia nella root del sito.");
}
require_once $bootstrap_path;

if (!isset($mysqli)) {
    die("Errore: Connessione al database non disponibile.");
}

// 1. Recupera tutti i personaggi dal database
$res = $mysqli->query("SELECT id, nome, rarità, categoria FROM personaggi ORDER BY id ASC");
if (!$res) {
    die("Errore nella query dei personaggi: " . $mysqli->error);
}

$personaggi = [];
while ($row = $res->fetch_assoc()) {
    // Normalizza la rarità
    $rarita = strtolower(trim($row['rarità']));
    if (in_array($rarita, ['secret', 'segreto', 'secret limited', 'segreto limited'], true)) {
        $rarita = strpos($rarita, 'limited') !== false ? 'secret_limited' : 'secret';
    } elseif (strpos($rarita, 'one') !== false) {
        $rarita = 'theone';
    }
    
    $row['rarita_clean'] = $rarita;
    $personaggi[] = $row;
}

// 2. Funzioni di generazione di nomi e abilità uniche
function generate_character_config(array $p): array {
    $id = (int)$p['id'];
    $nome = trim($p['nome']);
    $nomeLower = strtolower($nome);
    $rarita = $p['rarita_clean'];
    
    // Determina il ruolo in base a nome, categoria o ID
    $role = 'DPS';
    if (strpos($nomeLower, 'golem') !== false || strpos($nomeLower, 'scudo') !== false || strpos($nomeLower, 'difesa') !== false || strpos($nomeLower, 'guardia') !== false || strpos($nomeLower, 'roccia') !== false) {
        $role = 'Tank';
    } elseif (strpos($nomeLower, 'angelo') !== false || strpos($nomeLower, 'cura') !== false || strpos($nomeLower, 'medico') !== false || strpos($nomeLower, 'vita') !== false || strpos($nomeLower, 'santuario') !== false) {
        $role = 'Healer';
    } elseif (strpos($nomeLower, 'mago') !== false || strpos($nomeLower, 'gelo') !== false || strpos($nomeLower, 'tempesta') !== false || strpos($nomeLower, 'congelamento') !== false || strpos($nomeLower, 'control') !== false) {
        $role = 'Controller';
    } elseif (strpos($nomeLower, 'assassino') !== false || strpos($nomeLower, 'ombra') !== false || strpos($nomeLower, 'lama') !== false || strpos($nomeLower, 'rapido') !== false || strpos($nomeLower, 'silente') !== false) {
        $role = 'Burst DPS';
    } elseif (strpos($nomeLower, 'veleno') !== false || strpos($nomeLower, 'tossico') !== false || strpos($nomeLower, 'malediz') !== false || strpos($nomeLower, 'deb') !== false) {
        $role = 'Debuffer';
    } elseif (strpos($nomeLower, 'inno') !== false || strpos($nomeLower, 'canto') !== false || strpos($nomeLower, 'coro') !== false || strpos($nomeLower, 'buf') !== false) {
        $role = 'Buffer';
    } elseif (strpos($nomeLower, 'guerriero') !== false || strpos($nomeLower, 'bruiser') !== false || strpos($nomeLower, 'barbaro') !== false || strpos($nomeLower, 'spada') !== false) {
        $role = 'Bruiser';
    } elseif (strpos($nomeLower, 'supporto') !== false || strpos($nomeLower, 'barriera') !== false || strpos($nomeLower, 'cristallo') !== false) {
        $role = 'Support';
    } else {
        // Assegnazione deterministica basata sull'ID per varietà
        $roles = ['Tank', 'Bruiser', 'DPS', 'Burst DPS', 'Sub DPS', 'Support', 'Healer', 'Controller', 'Debuffer', 'Buffer'];
        $role = $roles[$id % count($roles)];
    }
    
    // Cose speciali per "The One", "Segreto" e "Segreto Limited"
    if ($rarita === 'theone' || strpos($nomeLower, 'the one') !== false) {
        return [
            'role' => 'Burst DPS',
            'passive_name' => 'Orgoglio Divino',
            'passive_desc' => 'Se va KO, si rianima immediatamente al 100% degli HP (una volta per match). Inoltre, finché è in vita, aumenta il Crit Rate del team del 25% e il Danno Critico del 50%.',
            'passive_effect' => ['type' => 'the_one_passive'],
            'special_name' => 'Sole Divino',
            'special_desc' => 'Infligge danni devastanti (220% ATK) al bersaglio, cura l\'intero team del 40% degli HP max e aumenta il Crit Rate del team del 25% e il Danno Critico del 50% per 3 turni.',
            'special_effect' => ['type' => 'the_one_special'],
            'special_cost' => 3,
            'special_cooldown' => 3
        ];
    }
    
    if ($rarita === 'secret' && strpos($nomeLower, 'limited') === false) {
        return [
            'role' => 'Controller',
            'passive_name' => 'Presenza Eterea di ' . $nome,
            'passive_desc' => 'Ha il 30% di probabilità di ottenere un turno extra dopo aver eseguito un\'azione.',
            'passive_effect' => ['type' => 'ethereal_double_turn'],
            'special_name' => 'Rottura Dimensionale di ' . $nome,
            'special_desc' => 'Infligge il 150% ATK come danno, ruba fino a 2 Energia al bersaglio e ha il 50% di probabilità di Congelarlo per 1 turno.',
            'special_effect' => ['type' => 'dimensional_break'],
            'special_cost' => 3,
            'special_cooldown' => 3
        ];
    }
    
    if ($rarita === 'secret_limited' || (strpos($nomeLower, 'secret') !== false && strpos($nomeLower, 'limited') !== false)) {
        return [
            'role' => 'Healer',
            'passive_name' => 'Sinfonia del Destino di ' . $nome,
            'passive_desc' => 'Quando un alleato va KO, lo risuscita immediatamente con il 35% dei suoi HP max (una volta per match).',
            'passive_effect' => ['type' => 'destiny_resurrect'],
            'special_name' => 'Benedizione Stellare di ' . $nome,
            'special_desc' => 'Cura tutto il team del 35% degli HP max, rimuove tutti i debuff e aumenta l\'Attacco del 25% e la Velocità del 20% per 2 turni.',
            'special_effect' => ['type' => 'stellar_blessing'],
            'special_cost' => 3,
            'special_cooldown' => 4
        ];
    }
    
    // Generazione abilità uniche basate sul ruolo per i personaggi normali
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
            $passive_name = 'Scudo Rigenerativo di ' . $nome;
            $passive_desc = 'Inizia la battaglia con uno Scudo pari al 25% dei suoi HP max.';
            $passive_effect = ['type' => 'shield_at_start', 'pct' => 25];
            
            $special_name = 'Bastione Impenetrabile di ' . $nome;
            $special_desc = 'Ottiene uno Scudo pari al 40% dei suoi HP max e applica Provocazione per 2 turni, costringendo i nemici ad attaccarlo.';
            $special_effect = ['type' => 'taunt_self'];
            $special_cost = 2;
            $special_cooldown = 3;
            break;
            
        case 'Bruiser':
            $passive_name = 'Determinazione di ' . $nome;
            $passive_desc = 'Aumenta il proprio Attacco del 5% per ogni 10% di HP persi.';
            $passive_effect = ['type' => 'atk_scale_lost_hp'];
            
            $special_name = 'Fendente Sanguinante di ' . $nome;
            $special_desc = 'Infligge il 150% ATK come danno e applica Sanguinamento al bersaglio per 2 turni (perde il 15% degli HP max a turno).';
            $special_effect = ['type' => 'apply_bleed'];
            $special_cost = 2;
            $special_cooldown = 2;
            break;
            
        case 'DPS':
            $passive_name = 'Slancio Critico di ' . $nome;
            $passive_desc = 'Aumenta il proprio Crit Rate del 10% ogni volta che non mette a segno un colpo critico. Si resetta al verificarsi del critico.';
            $passive_effect = ['type' => 'crit_ramp_on_non_crit'];
            
            $special_name = 'Tempesta di Colpi di ' . $nome;
            $special_desc = 'Infligge il 180% ATK come danno. Se il colpo è critico, ricarica immediatamente 1 Energia.';
            $special_effect = ['type' => 'flurry_of_blows'];
            $special_cost = 3;
            $special_cooldown = 3;
            break;
            
        case 'Burst DPS':
            $passive_name = 'Precisione Letale di ' . $nome;
            $passive_desc = 'Aumenta passivamente il proprio Danno Critico del 40%.';
            $passive_effect = ['type' => 'passive_crit_dmg_buff'];
            
            $special_name = 'Colpo da Maestro di ' . $nome;
            $special_desc = 'Infligge il 220% ATK come danno a bersaglio singolo, ma riduce la propria Difesa del 25% per 1 turno.';
            $special_effect = ['type' => 'deadly_strike'];
            $special_cost = 3;
            $special_cooldown = 3;
            break;
            
        case 'Sub DPS':
            $passive_name = 'Opportunismo di ' . $nome;
            $passive_desc = 'Infligge il 20% di danni in più ai nemici che sono affetti da qualsiasi debuff o stato negativo.';
            $passive_effect = ['type' => 'bonus_dmg_on_debuffed'];
            
            $special_name = 'Impatto Rallentante di ' . $nome;
            $special_desc = 'Infligge il 140% ATK come danno e riduce la Velocità del bersaglio del 25% per 2 turni.';
            $special_effect = ['type' => 'distracting_strike'];
            $special_cost = 2;
            $special_cooldown = 2;
            break;
            
        case 'Support':
            $passive_name = 'Barriera Protettiva di ' . $nome;
            $passive_desc = 'All\'inizio del match, applica uno Scudo pari al 15% dei suoi HP max a tutto il team.';
            $passive_effect = ['type' => 'shield_team_at_start', 'pct' => 15];
            
            $special_name = 'Fortezza Cristallina di ' . $nome;
            $special_desc = 'Applica uno Scudo pari al 25% degli HP max di questo personaggio a tutti i membri del team.';
            $special_effect = ['type' => 'shield_all_allies'];
            $special_cost = 2;
            $special_cooldown = 3;
            break;
            
        case 'Healer':
            $passive_name = 'Aura Curativa di ' . $nome;
            $passive_desc = 'All\'inizio di ogni suo turno, cura tutti gli alleati attivi del 5% dei loro HP max.';
            $passive_effect = ['type' => 'regen_all_allies'];
            
            $special_name = 'Soffio Vitale di ' . $nome;
            $special_desc = 'Rigenera il 40% degli HP max del personaggio attivo e applica Rigenerazione per 2 turni (cura il 15% a turno).';
            $special_effect = ['type' => 'heal_active_regen'];
            $special_cost = 2;
            $special_cooldown = 2;
            break;
            
        case 'Controller':
            $passive_name = 'Gelo sul Colpo di ' . $nome;
            $passive_desc = 'Gli attacchi base hanno il 15% di probabilità di Congelare il bersaglio per 1 turno.';
            $passive_effect = ['type' => 'freeze_on_hit'];
            
            $special_name = 'Onda d\'Urto di ' . $nome;
            $special_desc = 'Infligge il 120% ATK come danno e ha il 75% di probabilità di Stordire il bersaglio per 1 turno.';
            $special_effect = ['type' => 'stun_target'];
            $special_cost = 2;
            $special_cooldown = 3;
            break;
            
        case 'Debuffer':
            $passive_name = 'Tossina di ' . $nome;
            $passive_desc = 'Gli attacchi base applicano sempre Veleno al bersaglio per 2 turni (perde il 10% degli HP max a turno).';
            $passive_effect = ['type' => 'poison_on_hit'];
            
            $special_name = 'Nebbia Corrosiva di ' . $nome;
            $special_desc = 'Riduce la Difesa del bersaglio del 30% per 2 turni e applica Veleno per 3 turni (12% HP max a turno).';
            $special_effect = ['type' => 'toxic_mist'];
            $special_cost = 2;
            $special_cooldown = 3;
            break;
            
        case 'Buffer':
            $passive_name = 'Presenza Ispiratrice di ' . $nome;
            $passive_desc = 'Aumenta l\'Attacco di tutti gli alleati del 10% finché questo personaggio è in campo.';
            $passive_effect = ['type' => 'team_atk_buff', 'value' => 10];
            
            $special_name = 'Canto di Battaglia di ' . $nome;
            $special_desc = 'Aumenta l\'Attacco del team del 25% e la Velocità del 20% per 2 turni, e fornisce 1 Energia ad ogni alleato.';
            $special_effect = ['type' => 'battle_cry'];
            $special_cost = 2;
            $special_cooldown = 3;
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

// 3. Genera il codice sorgente per game_config.php
$config_code = "<?php\n";
$config_code .= "/**\n";
$config_code .= " * FILE DI CONFIGURAZIONE GENERATO AUTOMATICAMENTE\n";
$config_code .= " * Data di generazione: " . date('Y-m-d H:i:s') . "\n";
$config_code .= " * Contiene i ruoli e le abilità uniche per tutti i " . count($personaggi) . " personaggi del database.\n";
$config_code .= " */\n\n";

$config_code .= "if (!defined('CRIPSUM_GAME_CONFIG')) {\n";
$config_code .= "    define('CRIPSUM_GAME_CONFIG', true);\n";
$config_code .= "}\n\n";

// Definizione dei moltiplicatori dei ruoli
$config_code .= "/**\n";
$config_code .= " * Moltiplicatori delle statistiche basati sui 10 ruoli di gioco.\n";
$config_code .= " */\n";
$config_code .= "function gd_role_multipliers(): array {\n";
$config_code .= "    return [\n";
$config_code .= "        'Tank'      => ['hp' => 1.45, 'atk' => 0.65, 'def' => 1.50, 'spd' => 0.70, 'crit_rate' => 5,  'crit_dmg' => 120],\n";
$config_code .= "        'Bruiser'   => ['hp' => 1.20, 'atk' => 1.00, 'def' => 1.10, 'spd' => 0.90, 'crit_rate' => 10, 'crit_dmg' => 135],\n";
$config_code .= "        'DPS'       => ['hp' => 0.90, 'atk' => 1.30, 'def' => 0.75, 'spd' => 1.15, 'crit_rate' => 20, 'crit_dmg' => 150],\n";
$config_code .= "        'Burst DPS' => ['hp' => 0.80, 'atk' => 1.50, 'def' => 0.65, 'spd' => 1.25, 'crit_rate' => 25, 'crit_dmg' => 175],\n";
$config_code .= "        'Sub DPS'   => ['hp' => 1.00, 'atk' => 1.05, 'def' => 0.90, 'spd' => 1.10, 'crit_rate' => 15, 'crit_dmg' => 140],\n";
$config_code .= "        'Support'   => ['hp' => 1.10, 'atk' => 0.80, 'def' => 1.05, 'spd' => 1.15, 'crit_rate' => 8,  'crit_dmg' => 125],\n";
$config_code .= "        'Healer'    => ['hp' => 1.15, 'atk' => 0.70, 'def' => 0.95, 'spd' => 1.10, 'crit_rate' => 5,  'crit_dmg' => 120],\n";
$config_code .= "        'Controller'=> ['hp' => 1.00, 'atk' => 0.85, 'def' => 0.95, 'spd' => 1.20, 'crit_rate' => 10, 'crit_dmg' => 130],\n";
$config_code .= "        'Debuffer'  => ['hp' => 1.00, 'atk' => 0.95, 'def' => 0.95, 'spd' => 1.15, 'crit_rate' => 10, 'crit_dmg' => 130],\n";
$config_code .= "        'Buffer'    => ['hp' => 1.05, 'atk' => 0.75, 'def' => 1.00, 'spd' => 1.20, 'crit_rate' => 8,  'crit_dmg' => 125]\n";
$config_code .= "    ];\n";
$config_code .= "}\n\n";

// Definizione del calcolo statistiche
$config_code .= "/**\n";
$config_code .= " * Calcola le statistiche finali in base a rarità e ruolo.\n";
$config_code .= " */\n";
$config_code .= "function gd_calculate_character_stats(string \$rarity, string \$role): array {\n";
$config_code .= "    \$base = [\n";
$config_code .= "        'comune'      => ['hp' => 100, 'atk' => 30, 'def' => 20, 'spd' => 15],\n";
$config_code .= "        'raro'        => ['hp' => 120, 'atk' => 36, 'def' => 24, 'spd' => 17],\n";
$config_code .= "        'epico'       => ['hp' => 140, 'atk' => 42, 'def' => 28, 'spd' => 19],\n";
$config_code .= "        'leggendario' => ['hp' => 170, 'atk' => 51, 'def' => 34, 'spd' => 21],\n";
$config_code .= "        'speciale'    => ['hp' => 185, 'atk' => 55, 'def' => 37, 'spd' => 23],\n";
$config_code .= "        'secret'      => ['hp' => 200, 'atk' => 60, 'def' => 40, 'spd' => 25],\n";
$config_code .= "        'secret_limited' => ['hp' => 210, 'atk' => 63, 'def' => 42, 'spd' => 26],\n";
$config_code .= "        'theone'      => ['hp' => 240, 'atk' => 75, 'def' => 48, 'spd' => 30]\n";
$config_code .= "    ];\n";
$config_code .= "    \$rKey = strtolower(trim(\$rarity));\n";
$config_code .= "    if (strpos(\$rKey, 'limited') !== false) \$rKey = 'secret_limited';\n";
$config_code .= "    elseif (strpos(\$rKey, 'secret') !== false) \$rKey = 'secret';\n";
$config_code .= "    elseif (strpos(\$rKey, 'one') !== false) \$rKey = 'theone';\n\n";
$config_code .= "    \$b = \$base[\$rKey] ?? \$base['comune'];\n";
$config_code .= "    \$mults = gd_role_multipliers();\n";
$config_code .= "    \$m = \$mults[\$role] ?? ['hp' => 1.0, 'atk' => 1.0, 'def' => 1.0, 'spd' => 1.0, 'crit_rate' => 5, 'crit_dmg' => 150];\n\n";
$config_code .= "    return [\n";
$config_code .= "        'hp'        => (int)round(\$b['hp'] * \$m['hp']),\n";
$config_code .= "        'attack'    => (int)round(\$b['atk'] * \$m['atk']),\n";
$config_code .= "        'defense'   => (int)round(\$b['def'] * \$m['def']),\n";
$config_code .= "        'speed'     => (int)round(\$b['spd'] * \$m['spd']),\n";
$config_code .= "        'crit_rate' => (int)\$m['crit_rate'],\n";
$config_code .= "        'crit_dmg'  => (int)\$m['crit_dmg']\n";
$config_code .= "    ];\n";
$config_code .= "}\n\n";

// Definizione della funzione di configurazione personaggi
$config_code .= "/**\n";
$config_code .= " * Ritorna ruolo, abilità passiva e speciale per un determinato personaggio.\n";
$config_code .= " */\n";
$config_code .= "function gd_get_character_config(int \$pid, string \$rarity, string \$nome): array {\n";
$config_code .= "    static \$cache = null;\n";
$config_code .= "    if (\$cache === null) {\n";
$config_code .= "        \$cache = [\n";

// Ciclo su tutti i personaggi caricati dal database per inserire le loro configurazioni cablate
foreach ($personaggi as $p) {
    $cfg = generate_character_config($p);
    $config_code .= "            " . $p['id'] . " => [\n";
    $config_code .= "                'role' => '" . addslashes($cfg['role']) . "',\n";
    $config_code .= "                'passive_name' => '" . addslashes($cfg['passive_name']) . "',\n";
    $config_code .= "                'passive_desc' => '" . addslashes($cfg['passive_desc']) . "',\n";
    $config_code .= "                'passive_effect' => " . var_export($cfg['passive_effect'], true) . ",\n";
    $config_code .= "                'special_name' => '" . addslashes($cfg['special_name']) . "',\n";
    $config_code .= "                'special_desc' => '" . addslashes($cfg['special_desc']) . "',\n";
    $config_code .= "                'special_effect' => " . var_export($cfg['special_effect'], true) . ",\n";
    $config_code .= "                'special_cost' => " . $cfg['special_cost'] . ",\n";
    $config_code .= "                'special_cooldown' => " . $cfg['special_cooldown'] . "\n";
    $config_code .= "            ],\n";
}

$config_code .= "        ];\n";
$config_code .= "    }\n\n";

$config_code .= "    if (isset(\$cache[\$pid])) {\n";
$config_code .= "        return \$cache[\$pid];\n";
$config_code .= "    }\n\n";

// Fallback euristico per personaggi futuri aggiunti dinamicamente al database
$config_code .= "    // Fallback euristico se il personaggio non è pre-mappato\n";
$config_code .= "    \$nomeLower = strtolower(\$nome);\n";
$config_code .= "    \$role = 'DPS';\n";
$config_code .= "    if (strpos(\$nomeLower, 'golem') !== false || strpos(\$nomeLower, 'scudo') !== false || strpos(\$nomeLower, 'difesa') !== false) \$role = 'Tank';\n";
$config_code .= "    elseif (strpos(\$nomeLower, 'angelo') !== false || strpos(\$nomeLower, 'cura') !== false) \$role = 'Healer';\n";
$config_code .= "    elseif (strpos(\$nomeLower, 'assassino') !== false || strpos(\$nomeLower, 'ombra') !== false) \$role = 'Burst DPS';\n";
$config_code .= "    elseif (strpos(\$nomeLower, 'mago') !== false || strpos(\$nomeLower, 'gelo') !== false) \$role = 'Controller';\n";
$config_code .= "    elseif (strpos(\$nomeLower, 'veleno') !== false || strpos(\$nomeLower, 'tossico') !== false) \$role = 'Debuffer';\n\n";

$config_code .= "    return [\n";
$config_code .= "        'role' => \$role,\n";
$config_code .= "        'passive_name' => 'Abilità di ' . \$nome,\n";
$config_code .= "        'passive_desc' => 'Aumenta le capacità in battaglia.',\n";
$config_code .= "        'passive_effect' => [],\n";
$config_code .= "        'special_name' => 'Colpo di ' . \$nome,\n";
$config_code .= "        'special_desc' => 'Un potente attacco speciale.',\n";
$config_code .= "        'special_effect' => [],\n";
$config_code .= "        'special_cost' => 2,\n";
$config_code .= "        'special_cooldown' => 2\n";
$config_code .= "    ];\n";
$config_code .= "}\n";

// 4. Salva il file generato in includes/game_config.php
$target_config_path = __DIR__ . '/includes/game_config.php';
$written = file_put_contents($target_config_path, $config_code);

if ($written === false) {
    die("Errore: Impossibile scrivere in includes/game_config.php.");
}

echo "<h1>Generazione Completata con Successo!</h1>";
echo "<p>È stata creata una configurazione unica per ciascuno dei <strong>" . count($personaggi) . "</strong> personaggi del database.</p>";
echo "<p>Il file <strong>includes/game_config.php</strong> è stato aggiornato ed è pronto.</p>";
echo "<p><a href='/it/game/lobby.php'>Torna alla lobby dei duelli</a></p>";
?>
