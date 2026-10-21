<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — i kit unici.
 *
 * I 30 personaggi che avevano un kit scritto apposta in
 * includes/game_config.php, rifatti con gli effetti del catalogo: stessi
 * nomi, stessa idea, numeri riportati nella fascia della loro rarita' e
 * passati per il simulatore. Il ruolo resta quello del database: il kit
 * sostituisce solo le parti che scrive (attacco, abilita', ultimate,
 * passiva, cariche, forme); il resto viene dal kit del ruolo.
 *
 * Piu' 5 kit per i Segreti arrivati il 21/10/2026 (216, 237, 243, 265 e
 * il limitato 266): ogni Segreto ha un kit suo, come gli altri.
 *
 * 'theme' e' il colore della scena dell'ultimate (quello di oggi in
 * assets/js/game.js).
 *
 * Piu' in fondo: i nomi a tema dei 16 Speciali che non hanno un kit unico.
 */

function duel_unique_kits(): array
{
    static $kits = null;
    if ($kits !== null) {
        return $kits;
    }

    $hit = static fn(int $pct, array $x = []) => ['e' => 'damage', 'to' => 'target', 'pct' => $pct] + $x;
    $all = static fn(int $pct, array $x = []) => ['e' => 'damage', 'to' => 'enemies', 'pct' => $pct] + $x;
    $ap = static fn(string $k, string $to = 'target', array $x = []) => ['e' => 'apply', 'to' => $to, 'k' => $k] + $x;
    $sh = static fn(int $pct, string $to = 'self', array $x = []) => ['e' => 'shield', 'to' => $to, 'pct' => $pct] + $x;
    $heal = static fn(int $pct, string $to = 'self', array $x = []) => ['e' => 'heal', 'to' => $to, 'pct' => $pct] + $x;
    $leech = static fn(int $pct) => ['e' => 'heal', 'to' => 'self', 'of' => 'damage', 'pct' => $pct, 'name' => 'rubavita'];

    $kits = [
        46 => [ // CRIPSUM (DISOCCUPATO) — Debuffer, Segreto
            'theme' => ['color' => '#ffd700'],
            'passive' => ['name' => 'Sussidio di Disoccupazione', 'scaling' => [['type' => 'team_ps_zero', 'mods' => ['def_pct' => 40]]], 'triggers' => []],
            'attack' => ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [
                $hit(100), ['e' => 'ps', 'side' => 'steal', 'n' => 1, 'if' => ['chance' => 35]],
            ]],
            'skill' => ['name' => 'Reddito di Cittadinanza', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit(170), $leech(40), $ap('atk_giu', 'target', ['v' => 25, 't' => 2, 'chance' => 100]),
            ]],
            'ultimate' => ['name' => 'Sussidio Statale Supremo', 'target' => 'enemy', 'effects' => [
                ['e' => 'ps', 'side' => 'steal', 'n' => 2], $hit(365), $leech(50),
                $ap('atk_giu', 'enemies', ['v' => 30, 't' => 2, 'chance' => 100]),
            ]],
        ],
        48 => [ // SHOREKEEPER — Support, The One
            'theme' => ['color' => '#38bdf8'],
            'passive' => ['name' => 'Custode del Cosmo', 'aura' => ['crit' => 5, 'cdmg' => 10, 'red_pct' => 5], 'triggers' => [
                ['when' => 'battle_start', 'silent' => true, 'effects' => [$ap('rinascita', 'self', ['v' => 30, 't' => 0])]],
            ]],
            'skill' => ['name' => 'Giardino Stellare', 'cost' => 2, 'target' => 'enemy', 'effects' => [
                $hit(180), $heal(10, 'allies', ['of' => 'target_max']), $ap('crit_su', 'allies', ['v' => 15, 't' => 2]),
            ]],
            'ultimate' => ['name' => 'Stella della Redenzione', 'target' => 'self', 'effects' => [
                ['e' => 'revive', 'pct' => 40], $heal(30, 'allies', ['of' => 'target_max']), $sh(20, 'allies'),
                ['e' => 'terrain', 'k' => 'cielo_stellato', 'g' => 3],
            ]],
        ],
        49 => [ // SOSSIO — Buffer, Segreto
            'theme' => ['color' => '#facc15'],
            'passive' => ['name' => 'Triplo Guscio Rosso', 'aura' => ['atk_pct' => 8], 'triggers' => [
                ['when' => 'on_damaged', 'silent' => true, 'effects' => [$ap('vel_su', 'allies', ['v' => 10, 't' => 2])]],
            ]],
            'skill' => ['name' => 'Guscio Blu Fulminante', 'cost' => 1, 'target' => 'ally', 'effects' => [
                $heal(25, 'target', ['of' => 'target_max']), $ap('atk_su', 'target', ['v' => 35, 't' => 2]), ['e' => 'ps', 'n' => 1],
            ]],
            'ultimate' => ['name' => 'Dodge con la Stella', 'target' => 'self', 'effects' => [
                $heal(30, 'allies', ['of' => 'target_max']), $ap('atk_su', 'allies', ['v' => 35, 't' => 2]),
                ['e' => 'ps', 'n' => 2], ['e' => 'advance', 'to' => 'allies_other', 'pct' => 30],
            ]],
        ],
        50 => [ // MANUEL (BEATBOXER) — Controller, Segreto
            'theme' => ['color' => '#a855f7'],
            'passive' => ['name' => 'Ritmo Asfissiante', 'vs' => [['if' => ['target_has' => 'cc'], 'dmg_pct' => 15]], 'triggers' => []],
            'attack' => ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [$hit(100), $ap('stordimento', 'target', ['chance' => 20])]],
            'skill' => ['name' => 'Drop di Cassa', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit(170), $ap('silenzio', 'target', ['t' => 2, 'chance' => 70]), ['e' => 'delay', 'to' => 'target', 'pct' => 20],
            ]],
            'ultimate' => ['name' => 'Beatbox della Distruzione', 'target' => 'self', 'effects' => [
                $all(150), $ap('silenzio', 'enemies', ['t' => 2, 'chance' => 55]), $ap('stordimento', 'enemies', ['chance' => 25]),
            ]],
        ],
        64 => [ // CHARLIE KIRK — Debuffer, Segreto
            'theme' => ['color' => '#ef4444'],
            'passive' => ['name' => 'Fatti e Logica', 'mods' => ['pen' => 20], 'triggers' => []],
            'attack' => ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [$hit(100), $ap('def_giu', 'target', ['v' => 15, 't' => 1, 'chance' => 50])]],
            'skill' => ['name' => 'Punto di Svolta', 'cost' => 1, 'cd' => 2, 'target' => 'enemy', 'effects' => [
                $hit(155), $ap('def_giu', 'target', ['v' => 30, 't' => 2, 'chance' => 100]),
                $ap('provocazione', 'self', ['t' => 2]), $sh(15),
            ]],
            'ultimate' => ['name' => 'Dibattito Devastante', 'target' => 'self', 'effects' => [
                $ap('def_giu', 'enemies', ['v' => 40, 't' => 3, 'chance' => 100]), $ap('marchio', 'enemies', ['t' => 2, 'chance' => 100]),
                $ap('provocazione', 'self', ['t' => 3]), $sh(45),
            ]],
        ],
        75 => [ // ZAKATOR (OPSEC DEMON) — Burst DPS, Segreto
            'theme' => ['color' => '#22c55e'],
            'passive' => ['name' => 'Crittografia End-to-End', 'block_neg' => 2, 'mods' => ['ten' => 40, 'cdmg' => 30], 'triggers' => []],
            'skill' => ['name' => 'Attacco Zero-Day', 'cost' => 2, 'target' => 'enemy', 'effects' => [$hit(345, ['pierce_shield' => true, 'pen' => 20])]],
            'ultimate' => ['name' => 'Cyber Apocalypse', 'target' => 'enemy', 'effects' => [
                $hit(540, ['true' => true, 'pierce_shield' => true]), $ap('silenzio', 'target', ['t' => 2, 'chance' => 100]),
            ]],
        ],
        76 => [ // CHRISTIAN (GOONER FINAL BOSS) — Bruiser, Segreto
            'theme' => ['color' => '#ec4899'],
            'forms' => ['edging' => ['it' => 'Stato di Edging', 'en' => 'Edging state', 'mods' => ['atk_pct' => 30, 'spd_pct' => -15, 'lifesteal' => 15]]],
            'passive' => ['name' => 'Stato di Edging', 'triggers' => [
                ['when' => 'self_low', 'below' => 50, 'effects' => [['e' => 'form', 'k' => 'edging', 't' => 99]]],
            ]],
            'skill' => ['name' => 'Gooning Inarrestabile', 'cost' => 1, 'target' => 'enemy', 'effects' => [$hit(185), $leech(35), $sh(10)]],
            'ultimate' => ['name' => 'Gooning Infinito', 'target' => 'enemy', 'effects' => [
                $hit(250), $heal(45), $sh(35), $ap('atk_su', 'self', ['v' => 50, 't' => 3]),
            ]],
        ],
        86 => [ // ALDRICH IL MERCENARIO CHAN — DPS, Segreto
            'theme' => ['color' => '#f97316'],
            'passive' => ['name' => 'Contratto Lucroso', 'mods' => ['crit' => 10], 'triggers' => [
                ['when' => 'on_kill', 'effects' => [$heal(30), ['e' => 'ps', 'n' => 1], ['e' => 'charge', 'to' => 'self', 'n' => 20]]],
            ]],
            'skill' => ['name' => 'Raffica di Piombo', 'cost' => 2, 'target' => 'enemy', 'effects' => [
                $hit(90, ['hits' => 3]), $ap('sanguinamento', 'target', ['v' => 30, 't' => 2, 'chance' => 100]),
            ]],
            'ultimate' => ['name' => 'Esecuzione Tattica', 'target' => 'enemy', 'effects' => [
                $hit(380, ['bonus' => [['if' => ['target_hp_below' => 50], 'pct' => 60]]]), $heal(50, 'self', ['if' => ['killed' => true]]),
            ]],
        ],
        87 => [ // NAUZ U TRICHECU — Healer, Segreto
            'theme' => ['color' => '#06b6d4'],
            'passive' => ['name' => 'Pelle Impermeabile', 'mods' => ['heal_in_pct' => 30], 'triggers' => [
                ['when' => 'turn_start', 'silent' => true, 'effects' => [$heal(5, 'allies', ['of' => 'target_max'])]],
            ]],
            'skill' => ['name' => 'Bolla Rigenerativa', 'cost' => 1, 'target' => 'ally', 'effects' => [
                $heal(35, 'target', ['of' => 'target_max']), $sh(20, 'target'),
            ]],
            'ultimate' => ['name' => 'Marea della Rinascita', 'target' => 'self', 'effects' => [
                $heal(45, 'allies', ['of' => 'target_max']), ['e' => 'cleanse', 'to' => 'allies', 'n' => 99], $ap('immunita', 'allies', ['t' => 1]),
            ]],
        ],
        88 => [ // TUNG GOD — Tank, Segreto limitato
            'theme' => ['color' => '#e2e8f0'],
            'passive' => ['name' => 'Lega di Tungsteno', 'mods' => ['reflect' => 6], 'triggers' => [
                ['when' => 'battle_start', 'silent' => true, 'effects' => [$sh(8)]],
            ]],
            'skill' => ['name' => 'Impatto Orbitale', 'cost' => 1, 'cd' => 2, 'target' => 'enemy', 'effects' => [
                ['e' => 'damage', 'to' => 'target', 'mix' => ['atk' => 60, 'def' => 60]], $ap('stordimento', 'target', ['chance' => 35]),
                $sh(12), $ap('provocazione', 'self', ['t' => 2]),
            ]],
            'ultimate' => ['name' => 'Cataclisma del Tungsteno', 'target' => 'self', 'effects' => [
                ['e' => 'damage', 'to' => 'enemies', 'mix' => ['atk' => 70, 'def' => 70]], $ap('stordimento', 'enemies', ['chance' => 35]),
                $ap('barriera', 'self'), $ap('provocazione', 'self', ['t' => 2]),
            ]],
        ],
        98 => [ // CARMELO — Sub DPS, Segreto
            'theme' => ['color' => '#f8fafc'],
            'passive' => ['name' => 'Calcolo della Marionetta', 'counter' => 40, 'vs' => [['if' => ['target_has' => 'neg'], 'dmg_pct' => 20]], 'triggers' => []],
            'skill' => ['name' => 'Stratega dell\'Ombra', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit(195, ['bonus' => [['if' => ['target_has' => 'cc'], 'pct' => 80]]]), ['e' => 'delay', 'to' => 'target', 'pct' => 25], $ap('paura', 'target', ['chance' => 40]),
            ]],
            'ultimate' => ['name' => 'Stanza Bianca', 'target' => 'enemy', 'effects' => [
                $hit(330, ['bonus' => [['if' => ['target_has' => 'cc'], 'pct' => 120]]]), ['e' => 'dispel', 'to' => 'target', 'n' => 99],
            ]],
        ],
        138 => [ // NAUZ (PRINCIPESSA COSMICA) — Support, Segreto limitato
            'theme' => ['color' => '#a78bfa'],
            'passive' => ['name' => 'Polvere di Stelle', 'aura' => ['red_pct' => 8], 'triggers' => [
                ['when' => 'turn_start', 'silent' => true, 'effects' => [$heal(6, 'lowest_ally', ['of' => 'target_max'])]],
            ]],
            'skill' => ['name' => 'Costellazione Protettiva', 'cost' => 1, 'target' => 'self', 'effects' => [
                $sh(20, 'allies'), ['e' => 'cleanse', 'to' => 'allies', 'n' => 99], $ap('def_su', 'allies', ['v' => 22, 't' => 2]),
            ]],
            'ultimate' => ['name' => 'Eclissi Cosmica', 'target' => 'self', 'effects' => [
                $sh(28, 'allies'), $ap('immunita', 'allies', ['t' => 1]), $heal(20, 'allies', ['of' => 'target_max']),
            ]],
        ],
        139 => [ // CRIPSUM (PRINCIPESSA COSMICA) — Buffer, Segreto limitato
            'theme' => ['color' => '#f472b6'],
            'passive' => ['name' => 'Nebulosa Aurea', 'aura' => ['crit' => 6, 'spd_pct' => 5, 'atk_pct' => 4], 'triggers' => []],
            'skill' => ['name' => 'Supernova dell\'Anima', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit(140), $heal(6, 'allies', ['of' => 'target_max']), ['e' => 'ps', 'n' => 1],
            ]],
            'ultimate' => ['name' => 'Collasso della Supernova', 'target' => 'self', 'effects' => [
                $all(180), $heal(20, 'allies', ['of' => 'target_max']), ['e' => 'ps', 'n' => 1], $ap('ispirato', 'allies', ['t' => 2]),
            ]],
        ],
        140 => [ // FLIGHT — Sub DPS, Segreto limitato
            'theme' => ['color' => '#38bdf8'],
            'passive' => ['name' => 'Evitamento Aereo', 'mods' => ['dodge' => 15], 'triggers' => []],
            'skill' => ['name' => 'Picchiata Sonica', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit(160), $ap('vel_su', 'self', ['v' => 20, 't' => 2]), $ap('vel_giu', 'target', ['v' => 20, 't' => 2, 'chance' => 100]),
            ]],
            'ultimate' => ['name' => 'Super-Picchiata Stratosferica', 'target' => 'enemy', 'effects' => [
                $hit(300), ['e' => 'delay', 'to' => 'target', 'pct' => 30], ['e' => 'extra_turn', 'to' => 'self'],
            ]],
        ],
        141 => [ // DANTE — Burst DPS, Segreto limitato
            'theme' => ['color' => '#dc2626'],
            'stacks' => ['name' => 'Stile', 'max' => 5, 'per' => ['dmg_pct' => 7], 'reset_on_damaged' => true],
            'forms' => ['sin_devil_trigger' => ['it' => 'Sin Devil Trigger', 'en' => 'Sin Devil Trigger', 'mods' => ['atk_pct' => 50, 'lifesteal' => 40],
                'attack' => ['name' => 'Ribellione', 'target' => 'enemy', 'effects' => [['e' => 'damage', 'to' => 'target', 'pct' => 160]]]]],
            'passive' => ['name' => 'Stile Elegantissimo', 'triggers' => [
                ['when' => 'after_action', 'silent' => true, 'effects' => [['e' => 'stacks', 'n' => 1]]],
            ]],
            'skill' => ['name' => 'Devil Trigger', 'cost' => 2, 'target' => 'enemy', 'effects' => [$hit(300, ['pen' => 30]), $leech(25), ['e' => 'stacks', 'n' => 1]]],
            'ultimate' => ['name' => 'Sin Devil Trigger', 'target' => 'enemy', 'effects' => [
                ['e' => 'form', 'k' => 'sin_devil_trigger', 't' => 2], $ap('immunita', 'self', ['t' => 2]), $hit(330),
            ]],
        ],
        142 => [ // VERGIL — DPS, Segreto limitato
            'theme' => ['color' => '#60a5fa'],
            'passive' => ['name' => 'Concentrazione Pura', 'mods' => ['crit' => 10], 'triggers' => []],
            'attack' => ['name' => 'Taglio doppio', 'target' => 'enemy', 'effects' => [$hit(100), $hit(35)]],
            'skill' => ['name' => 'Judgement Cut End', 'cost' => 2, 'target' => 'enemy', 'effects' => [
                $hit(270), $ap('congelamento', 'target', ['chance' => 40]), $ap('crit_su', 'self', ['v' => 30, 't' => 2]),
            ]],
            'ultimate' => ['name' => 'Deep Stinger', 'target' => 'enemy', 'effects' => [
                $hit(490), $ap('congelamento', 'target', ['chance' => 100]), $ap('crit_su', 'self', ['v' => 40, 't' => 3]), $ap('cdmg_su', 'self', ['v' => 60, 't' => 3]),
            ]],
        ],
        143 => [ // BENJAMIN NETANYAHU — Tank, Segreto limitato
            'theme' => ['color' => '#a3a3a3'],
            'passive' => ['name' => 'Iron Dome', 'aura' => ['red_skill_pct' => 6], 'triggers' => [
                ['when' => 'battle_start', 'silent' => true, 'effects' => [$sh(8)]],
            ]],
            'skill' => ['name' => 'Linea Rossa', 'cost' => 1, 'cd' => 2, 'target' => 'enemy', 'effects' => [
                $sh(16), $ap('provocazione', 'self', ['t' => 2]), $ap('atk_giu', 'target', ['v' => 20, 't' => 2, 'chance' => 100]),
            ]],
            'ultimate' => ['name' => 'Furia del Giudizio', 'target' => 'self', 'effects' => [
                $sh(28), $ap('provocazione', 'self', ['t' => 2]), $ap('atk_giu', 'enemies', ['v' => 20, 't' => 2, 'chance' => 100]),
            ]],
        ],
        144 => [ // IL PROTAGONISTA — Bruiser, Segreto limitato
            'theme' => ['color' => '#f97316'],
            'passive' => ['name' => 'Pelle del Tirannosauro Isekai', 'fatal' => [$sh(25)],
                'scaling' => [['type' => 'missing_hp', 'mods' => ['dmg_pct' => 3]]], 'triggers' => []],
            'skill' => ['name' => 'Carica del Triceratopo', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit(185, ['bonus' => [['if' => ['self_hp_below' => 50], 'pct' => 50]]]), $heal(20, 'self', ['if' => ['self_hp_below' => 50]]),
            ]],
            'ultimate' => ['name' => 'Meteora del Cratere Cretaceo', 'target' => 'enemy', 'effects' => [
                $hit(340, ['bonus' => [['if' => ['self_hp_below' => 50], 'pct' => 60]]]), $heal(30),
            ]],
        ],
        145 => [ // TERMINI E CONDIZIONI — DPS, Speciale
            'passive' => ['name' => 'Clausola Vessatoria', 'vs' => [['if' => ['target_has' => 'def_giu'], 'dmg_pct' => 10]], 'triggers' => []],
            'attack' => ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [$hit(100), $ap('def_giu', 'target', ['v' => 25, 't' => 2, 'chance' => 100])]],
            'skill' => ['name' => 'Firma in Calce', 'cost' => 2, 'target' => 'enemy', 'effects' => [$hit(330), ['e' => 'ps', 'n' => 1, 'if' => ['target_has' => 'neg']]]],
            'ultimate' => ['name' => 'Rinnovo Automatico', 'target' => 'enemy', 'effects' => [
                $hit(340), $ap('maledizione', 'target', ['v' => 30, 't' => 3, 'chance' => 100]), $ap('taglia', 'target', ['chance' => 100]),
            ]],
        ],
        146 => [ // LYON (RAGADI ANALI) — Sub DPS, Speciale
            'passive' => ['name' => 'Urlo delle Ragadi', 'scaling' => [['type' => 'hp_above', 'pct' => 70, 'mods' => ['atk_pct' => 10]]],
                'vs' => [['if' => ['target_has' => 'neg'], 'dmg_pct' => 12]], 'triggers' => []],
            'skill' => ['name' => 'Attacco dell\'Agonia', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit(170), $ap('vel_giu', 'target', ['v' => 25, 't' => 2, 'chance' => 100]), $ap('sanguinamento', 'target', ['v' => 30, 't' => 2, 'chance' => 100]),
            ]],
            'ultimate' => ['name' => 'Grido Lacerante', 'target' => 'self', 'effects' => [
                $all(190), $ap('sanguinamento', 'enemies', ['v' => 30, 't' => 2, 'chance' => 100]), $ap('vel_giu', 'enemies', ['v' => 20, 't' => 2, 'chance' => 100]),
            ]],
        ],
        147 => [ // GINGIO — Support, Speciale
            'passive' => ['name' => 'Panino d\'Emergenza', 'aura' => ['red_pct' => 8], 'triggers' => [
                ['when' => 'ally_low', 'below' => 40, 'effects' => [$sh(25, 'trigger')]],
            ]],
            'skill' => ['name' => 'Ce lo facciamo un cazzo di panino o no?', 'cost' => 1, 'target' => 'self', 'effects' => [
                $sh(20, 'allies'), $ap('def_su', 'allies', ['v' => 30, 't' => 2]),
            ]],
            'ultimate' => ['name' => 'Panino per tutti', 'target' => 'self', 'effects' => [
                $heal(25, 'allies', ['of' => 'target_max']), $sh(20, 'allies'), $ap('def_su', 'allies', ['v' => 30, 't' => 2]),
            ]],
        ],
        148 => [ // MIGHTY EAGLE — Debuffer, Speciale
            'passive' => ['name' => 'Lancio della Sardina', 'triggers' => [
                ['when' => 'turn_start', 'effects' => [$ap('def_giu', 'enemies', ['v' => 15, 't' => 1, 'chance' => 100])]],
            ]],
            'attack' => ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [$hit(100), $ap('veleno', 'target', ['st' => 1, 'chance' => 70])]],
            'skill' => ['name' => 'Yeah Yeah... Special move or something', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit(175), $ap('def_giu', 'target', ['v' => 30, 't' => 2, 'chance' => 100]), $ap('veleno', 'target', ['st' => 2, 'chance' => 100]),
            ]],
            'ultimate' => ['name' => 'Pioggia di Sardine', 'target' => 'enemy', 'effects' => [
                $all(170), $ap('corrosione', 'enemies', ['t' => 3, 'chance' => 100]), $ap('veleno', 'target', ['st' => 3, 'chance' => 100]),
            ]],
        ],
        149 => [ // JOE BARTOLOZZI — Buffer, Speciale
            'passive' => ['name' => 'Screaming at Comments', 'aura' => ['atk_pct' => 10], 'triggers' => []],
            'skill' => ['name' => 'Green Screen Rage', 'cost' => 1, 'target' => 'ally', 'effects' => [
                $ap('atk_su', 'target', ['v' => 40, 't' => 2]), ['e' => 'ps', 'n' => 1], ['e' => 'advance', 'to' => 'target', 'pct' => 30], $ap('concentrazione', 'target'),
            ]],
            'ultimate' => ['name' => 'Rabbia in Diretta', 'target' => 'self', 'effects' => [
                $ap('atk_su', 'allies', ['v' => 35, 't' => 2]), $ap('vel_su', 'allies', ['v' => 15, 't' => 2]), ['e' => 'ps', 'n' => 2], $ap('ispirato', 'allies', ['t' => 2]),
            ]],
        ],
        150 => [ // THE BRUTAL MOGGER — DPS, Segreto
            'passive' => ['name' => 'Mogging Brutale', 'mods' => ['crit' => 20], 'triggers' => [
                ['when' => 'on_crit', 'silent' => true, 'effects' => [$ap('vulnerabile', 'target', ['t' => 2, 'chance' => 100])]],
            ]],
            'skill' => ['name' => 'Jawline Devastante', 'cost' => 2, 'target' => 'enemy', 'effects' => [$hit(250, ['cdmg' => 50])]],
            'ultimate' => ['name' => 'Mewing Divino', 'target' => 'enemy', 'effects' => [
                $hit(470, ['pen' => 40]), ['e' => 'charge', 'to' => 'self', 'n' => 100, 'if' => ['killed' => true]],
            ]],
        ],
        151 => [ // WHIP — Tank, Segreto
            'passive' => ['name' => 'Dipendenza da FIFA', 'mods' => ['red_pct' => 10], 'triggers' => [
                ['when' => 'battle_start', 'silent' => true, 'effects' => [$sh(10)]],
            ]],
            'skill' => ['name' => 'Puff da 9000 Tiri', 'cost' => 1, 'cd' => 2, 'target' => 'self', 'effects' => [
                $ap('provocazione', 'self', ['t' => 2]), $sh(22), $ap('def_su', 'self', ['v' => 25, 't' => 2]),
            ]],
            'ultimate' => ['name' => 'Chillata Droga e Fumo', 'target' => 'self', 'effects' => [
                ['e' => 'guardian', 'to' => 'allies_other', 'v' => 35, 't' => 2], $sh(35), $sh(15, 'allies_other'),
            ]],
        ],
        152 => [ // LEXUS — Support, Segreto
            'passive' => ['name' => 'Kai in Guardia', 'aura' => ['red_pct' => 10], 'triggers' => []],
            'skill' => ['name' => 'Passeggiata a Ferrara', 'cost' => 1, 'target' => 'self', 'effects' => [
                $sh(28, 'allies'), ['e' => 'cleanse', 'to' => 'allies', 'n' => 99], $ap('def_su', 'allies', ['v' => 20, 't' => 2]),
            ]],
            'ultimate' => ['name' => 'Fedeltà di Kai', 'target' => 'self', 'effects' => [
                $ap('barriera', 'allies'), $ap('def_su', 'allies', ['v' => 40, 't' => 2]), $ap('immunita', 'allies', ['t' => 1]),
            ]],
        ],
        153 => [ // LACLY — DPS, Segreto limitato
            'passive' => ['name' => 'Snipe gol guscio verde', 'mods' => ['prec' => 15], 'triggers' => []],
            'attack' => ['name' => 'Attacco', 'target' => 'enemy', 'effects' => [
                $hit(100), ['e' => 'ps', 'side' => 'steal', 'n' => 1, 'if' => ['chance' => 35]], $ap('veleno', 'target', ['st' => 1, 'chance' => 50]),
            ]],
            'skill' => ['name' => 'Kurumi: Zafkiel', 'cost' => 2, 'target' => 'enemy', 'effects' => [$hit(290, ['pierce_shield' => true])]],
            'ultimate' => ['name' => 'Yud Bet', 'target' => 'enemy', 'effects' => [
                $hit(440), ['e' => 'charge', 'to' => 'self', 'n' => 100, 'if' => ['crit' => true]], $ap('congelamento', 'target', ['chance' => 100, 'if' => ['crit' => true]]),
            ]],
        ],
        154 => [ // LOW TIER GOD — DPS, Segreto limitato
            'passive' => ['name' => 'You Should... Now!', 'mods' => ['crit' => 20, 'cdmg' => 10], 'triggers' => []],
            'skill' => ['name' => 'Your Life is Nothing', 'cost' => 2, 'target' => 'enemy', 'effects' => [
                $hit(250), $ap('stordimento', 'target', ['chance' => 50]), $ap('elettrizzato', 'target', ['t' => 2, 'chance' => 100]),
            ]],
            'ultimate' => ['name' => 'Lightning Bolt', 'target' => 'enemy', 'effects' => [
                $hit(460, ['pierce_shield' => true]), $ap('stordimento', 'target', ['chance' => 40]), $ap('elettrizzato', 'enemies', ['t' => 2, 'chance' => 100]),
            ]],
        ],
        214 => [ // POPPY (BICCHIERE) — Bruiser, Segreto limitato
            'passive' => ['name' => 'OH POPPI', 'scaling' => [['type' => 'missing_hp', 'mods' => ['dmg_pct' => 4]]], 'triggers' => [
                ['when' => 'self_low', 'below' => 40, 'effects' => [$sh(25)]],
            ]],
            'skill' => ['name' => 'Richiamo del bombo', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit(195), $ap('stordimento', 'target', ['chance' => 45]), $ap('sanguinamento', 'target', ['v' => 30, 't' => 2, 'chance' => 100]),
            ]],
            'ultimate' => ['name' => 'Morso finale spaccabicchieri', 'target' => 'enemy', 'effects' => [
                $hit(410), $ap('atk_su', 'self', ['v' => 30, 't' => 3]), $heal(30),
            ]],
        ],
        215 => [ // RIAS GREMORY — DPS, Segreto limitato
            'theme' => ['color' => '#ff1f4b'],
            'passive' => ['name' => 'Potere della Rovina', 'mods' => ['crit' => 15], 'triggers' => [
                ['when' => 'on_crit', 'silent' => true, 'effects' => [$ap('veleno', 'target', ['st' => 1, 'chance' => 100])]],
            ]],
            'skill' => ['name' => 'Estinzione di Gremory', 'cost' => 2, 'target' => 'enemy', 'effects' => [
                $hit(230, ['bonus' => [['if' => ['target_has' => 'neg'], 'pct' => 50]]]),
            ]],
            'ultimate' => ['name' => 'Potere della Distruzione', 'target' => 'enemy', 'effects' => [
                ['e' => 'dispel', 'to' => 'target', 'n' => 99], $hit(420, ['pen' => 40]),
            ]],
        ],

        // ── Aggiunti il 21/10/2026 con i personaggi 216-266 ─────────────────
        216 => [ // VERITY — DPS, Segreto: la verita' viene fuori
            'theme' => ['color' => '#facc15'],
            'passive' => ['name' => 'Occhio della Verità', 'mods' => ['crit' => 10, 'prec' => 20],
                'vs' => [['if' => ['target_has' => 'esposto'], 'dmg_pct' => 20]], 'triggers' => []],
            'skill' => ['name' => 'Rivelazione', 'cost' => 2, 'target' => 'enemy', 'effects' => [
                $hit(240, ['pierce_shield' => true]), $ap('esposto', 'target', ['t' => 2, 'chance' => 100]), $ap('marchio', 'target', ['t' => 2, 'chance' => 100]),
            ]],
            'ultimate' => ['name' => 'Verità Assoluta', 'target' => 'enemy', 'effects' => [
                ['e' => 'dispel', 'to' => 'target', 'n' => 99], $hit(450, ['pen' => 30]),
            ]],
        ],
        237 => [ // LA PEACE — Healer, Segreto: nessuno si fa male
            'theme' => ['color' => '#86efac'],
            'passive' => ['name' => 'Nessuno Soffre', 'mods' => ['heal_out_pct' => 10], 'triggers' => [
                ['when' => 'ally_low', 'below' => 30, 'effects' => [$heal(20, 'trigger', ['of' => 'target_max']), $ap('immunita', 'trigger', ['t' => 1])]],
            ]],
            'skill' => ['name' => 'Tregua', 'cost' => 1, 'target' => 'ally', 'effects' => [
                $heal(28, 'target', ['of' => 'target_max']), ['e' => 'cleanse', 'to' => 'target', 'n' => 2], $ap('rigenerazione', 'target', ['v' => 6, 't' => 2]),
            ]],
            'ultimate' => ['name' => 'Pace nel Mondo', 'target' => 'self', 'effects' => [
                ['e' => 'revive', 'pct' => 30], $heal(30, 'allies', ['of' => 'target_max']), $ap('disarmo', 'enemies', ['t' => 1, 'chance' => 40]),
            ]],
        ],
        243 => [ // MYCHY (DOPPIAGGIO SICILIA) — Support, Segreto: la Famiglia protegge
            'theme' => ['color' => '#a8a29e'],
            'passive' => ['name' => 'Accento Siciliano', 'aura' => ['red_pct' => 8], 'mods' => ['ten' => 20], 'triggers' => [
                ['when' => 'battle_start', 'silent' => true, 'effects' => [$sh(10, 'allies')]],
            ]],
            'skill' => ['name' => 'Un\'Offerta che Non Puoi Rifiutare', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit(120), $ap('fascino', 'target', ['t' => 2, 'chance' => 80]), $sh(18, 'allies'),
            ]],
            'ultimate' => ['name' => 'La Famiglia', 'target' => 'self', 'effects' => [
                $ap('barriera', 'allies'), ['e' => 'guardian', 'to' => 'allies_other', 'v' => 25, 't' => 2], ['e' => 'ps', 'n' => 2],
            ]],
        ],
        265 => [ // GIGACHAD — Burst DPS, Segreto: sguardo e mascella
            'theme' => ['color' => '#e5e7eb'],
            'passive' => ['name' => 'Mascella d\'Acciaio', 'first_crit' => true, 'mods' => ['cdmg' => 20, 'ten' => 30], 'triggers' => [
                ['when' => 'on_kill', 'effects' => [$ap('ispirato', 'allies', ['t' => 2])]],
            ]],
            'skill' => ['name' => 'Sguardo Sigma', 'cost' => 2, 'target' => 'enemy', 'effects' => [
                $hit(270, ['pen' => 20]), $ap('paura', 'target', ['t' => 1, 'chance' => 50]),
            ]],
            'ultimate' => ['name' => 'Average Enjoyer', 'target' => 'enemy', 'effects' => [
                $ap('vulnerabile', 'target', ['t' => 1, 'chance' => 100]), $hit(500, ['bonus' => [['if' => ['target_hp_below' => 40], 'pct' => 35]]]),
            ]],
        ],
        266 => [ // CHRI MUCCA — Bruiser, Segreto limitato: latte e carica
            'theme' => ['color' => '#fafafa'],
            'passive' => ['name' => 'Latte Fresco', 'scaling' => [['type' => 'missing_hp', 'mods' => ['dmg_pct' => 3.5]]], 'triggers' => [
                ['when' => 'turn_start', 'silent' => true, 'effects' => [$heal(4)]],
            ]],
            'skill' => ['name' => 'Carica della Mucca', 'cost' => 1, 'target' => 'enemy', 'effects' => [
                $hit(235), $ap('stordimento', 'target', ['chance' => 30]), $leech(25),
            ]],
            'ultimate' => ['name' => 'Stampede', 'target' => 'self', 'effects' => [
                $all(220), $ap('stordimento', 'enemies', ['chance' => 30]), $heal(25), $ap('def_su', 'self', ['v' => 30, 't' => 2]),
            ]],
        ],
    ];

    return $kits;
}

/**
 * I nomi a tema degli Speciali senza kit unico, su passiva e abilita' del
 * loro ruolo: i 12 di sempre (game_config.php) e i 4 arrivati il 21/10/2026.
 */
function duel_special_names(): array
{
    return [
        4 => ['Debug in Produzione', 'Patch d\'Emergenza'],
        34 => ['Riciclo Creativo', 'Lancio del Cestino'],
        40 => ['Riscaldamento Muscolare', 'Capriola Acrobatica'],
        43 => ['Scatto del Cossu', 'Fendente del Cossu'],
        51 => ['Entrata in Scena', 'Spettacolo Pirotecnico'],
        77 => ['Ragebait Professionale', 'Provocazione di Massa'],
        89 => ['Fusa Protettive', 'Graffio Fulmineo'],
        90 => ['Presenza Misteriosa', 'Colpo di Mantello'],
        92 => ['Vibrazione Sonora', 'Urlo Melodico'],
        103 => ['Innesco Rapido', 'Carica Esplosiva'],
        112 => ['Portamento Reale', 'Tributo del Re'],
        136 => ['Flusso Sburevole', 'Colpo Incontenibile'],
        // Aggiunti il 21/10/2026.
        223 => ['Sarabanda!', 'Indovina la Canzone'],          // ENRICO PAPI, Burst DPS
        246 => ['Immunità Parlamentare', 'Promessa Elettorale'], // PAUL E. TICIAN, Tank
        247 => ['Arcobaleno Interiore', 'Colpo Arcobaleno'],     // CODY MILLER, Burst DPS
        264 => ['Striscia la Notizia', 'Fritto Misto'],          // IL GABIBBO, Controller
    ];
}
