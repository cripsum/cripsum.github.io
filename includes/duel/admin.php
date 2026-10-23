<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — il pannello admin (fase 7, tavola 7).
 *
 * I dati dietro api/admin/duels.php: i kit dei personaggi (editor, testi,
 * prova tra bot, versioni, legami, colore e musica dell'ultimate), i valori
 * degli stati e delle regole, il bilanciamento dalle partite vere e dalle
 * prove, le stagioni e le partite.
 *
 * Tutto passa dalle tabelle che il motore legge dalla fase 3 (game_kits,
 * game_kit_versions, game_catalog_values, game_traits, game_settings): le
 * partite gia' cominciate tengono le regole con cui sono partite, i kit
 * nuovi valgono dalla partita dopo.
 *
 * Niente sessione qui: l'endpoint controlla che sia staff, il CSRF e scrive
 * nel registro admin.
 */

require_once __DIR__ . '/lobby.php';

/** Le parti di un kit che il pannello scrive; il resto viene dal ruolo. */
const DUEL_ADMIN_KIT_KEYS = ['passive', 'attack', 'skill', 'ultimate', 'stacks', 'forms', 'theme', 'stats_mods'];

/** Gli effetti del catalogo (engine.php, duel_run_effects()). */
const DUEL_ADMIN_EFFECTS = ['damage', 'heal', 'shield', 'apply', 'guardian', 'cleanse', 'dispel', 'steal_buff', 'spread', 'consume',
    'ps', 'charge', 'advance', 'delay', 'extra_turn', 'terrain', 'form', 'stacks', 'revive', 'remove'];

/** A chi va un effetto (engine.php, duel_targets()). */
const DUEL_ADMIN_TO = ['target', 'self', 'allies', 'allies_other', 'enemies', 'other_enemies', 'random_enemy', 'lowest_ally', 'debuffed_ally',
    'attacker', 'trigger'];

/** Su chi si usa una mossa (engine.php, duel_move_targets()). */
const DUEL_ADMIN_MOVE_TARGETS = ['enemy', 'ally', 'ally_other', 'self'];

/** Quando scatta una passiva (engine.php, duel_trigger() e i controlli sulla vita). */
const DUEL_ADMIN_WHEN = ['battle_start', 'turn_start', 'turn_end', 'after_action', 'on_crit', 'on_kill', 'on_damaged', 'on_ally_ko', 'self_low', 'ally_low'];

/** I modificatori permanenti (text.php, duel_text_mods()). */
const DUEL_ADMIN_MODS = ['atk_pct', 'def_pct', 'spd_pct', 'crit', 'cdmg', 'prec', 'ten', 'dmg_pct', 'pen', 'dodge', 'reflect', 'lifesteal',
    'red_pct', 'red_skill_pct', 'heal_in_pct', 'heal_out_pct'];

/** Le statistiche che un kit puo' ritoccare (kits.php, duel_kit_build()). */
const DUEL_ADMIN_STATS = ['hp', 'atk', 'def', 'spd', 'crit', 'cdmg', 'prec', 'ten'];

/** Il bilanciamento: sopra si limano, sotto si rinforzano (tavola 7). */
const DUEL_ADMIN_HIGH = 56.0;
const DUEL_ADMIN_LOW = 44.0;

/** La tabella delle prove del Bilanciamento c'e' (migrazione della fase 7). */
function duel_phase7_ready(mysqli $m): bool
{
    return rt_has_table($m, 'game_balance_sim');
}

/** «DANTE» → «Dante», «CRIPSUM (DISOCCUPATO)» → «Cripsum (Disoccupato)», come l'arena. */
function duel_admin_name(string $name): string
{
    return mb_convert_case(mb_strtolower(trim($name), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

/** La rarita' del duello a parole e col colore della lootbox. */
function duel_admin_rarity(string $key): array
{
    $defs = gacha_rarity_defs();
    $base = $key === 'segreto_limitato' ? 'segreto' : $key;
    $label = (string)($defs[$base]['it'] ?? ucfirst($key));
    return ['key' => $key, 'label' => $key === 'segreto_limitato' ? $label . ' limitato' : $label, 'color' => (string)($defs[$base]['color'] ?? '#9ca3af')];
}

/** Tutti i personaggi del gacha con i campi del duello: [id => riga]. */
function duel_admin_characters(mysqli $m): array
{
    static $all = null;
    if ($all === null) {
        $all = duel_db_characters($m, array_map(static fn(array $r): int => (int)$r['id'], duel_rows($m, 'SELECT id FROM personaggi')));
    }
    return $all;
}

/** I kit scritti dal pannello: [id => ['kit', 'version', 'updated_at', 'by']]. */
function duel_admin_db_kits(mysqli $m): array
{
    $out = [];
    if (!rt_has_table($m, 'game_kits')) {
        return $out;
    }
    foreach (duel_rows($m, 'SELECT k.character_id, k.kit_json, k.version, k.updated_at, u.username FROM game_kits k LEFT JOIN utenti u ON u.id = k.updated_by') as $r) {
        $kit = json_decode((string)$r['kit_json'], true);
        $out[(int)$r['character_id']] = ['kit' => is_array($kit) ? $kit : [], 'version' => (int)$r['version'],
            'updated_at' => (string)$r['updated_at'], 'by' => (string)($r['username'] ?? '')];
    }
    return $out;
}

/** Solo le parti di un kit che il pannello scrive. */
function duel_admin_kit_parts(array $kit): array
{
    $out = [];
    foreach (DUEL_ADMIN_KIT_KEYS as $key) {
        if (isset($kit[$key]) && $kit[$key] !== []) {
            $out[$key] = $kit[$key];
        }
    }
    return $out;
}

/** L'elenco della colonna di sinistra: chi ha un kit suo e chi lo prende dal ruolo. */
function duel_admin_kit_list(mysqli $m): array
{
    duel_use_current_values($m, true);
    $db = duel_admin_db_kits($m);
    $code = duel_unique_kits();
    $out = [];
    foreach (duel_admin_characters($m) as $id => $c) {
        $kit = duel_character_kit($c, 1);
        $out[] = [
            'id' => $id,
            'name' => duel_admin_name((string)$c['nome']),
            'img' => duel_media($c['img_url'] ?? ''),
            'role' => $kit['role'],
            'rarity' => duel_admin_rarity($kit['rarity']),
            'source' => isset($db[$id]) ? 'pannello' : (isset($code[$id]) ? 'unico' : 'ruolo'),
            'version' => $db[$id]['version'] ?? 0,
        ];
    }
    usort($out, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
    return $out;
}

/** Il kit del codice di un personaggio: quello che avrebbe senza il pannello. */
function duel_admin_code_kit(array $char): array
{
    $over = duel_overrides();
    $without = $over;
    unset($without['kits'][(int)$char['id']]);
    duel_overrides($without);
    try {
        return duel_character_kit($char, 1);
    } finally {
        duel_overrides($over);
    }
}

/** Il kit pronto per una partita con un kit di prova al posto di quello in uso. */
function duel_admin_kit_with(array $char, ?array $draft, int $level = 1): array
{
    $over = duel_overrides();
    if ($draft === null) {
        return duel_character_kit($char, $level);
    }
    $with = $over;
    $with['kits'][(int)$char['id']] = $draft;
    duel_overrides($with);
    try {
        return duel_character_kit($char, $level);
    } finally {
        duel_overrides($over);
    }
}

function duel_admin_char(mysqli $m, int $id): array
{
    $char = duel_admin_characters($m)[$id] ?? null;
    if (!$char) {
        throw duel_user_error('Personaggio non trovato.', 'Character not found.', 404, 'NOT_FOUND');
    }
    return $char;
}

/** Le versioni salvate di un kit, dalla piu' recente. */
function duel_admin_kit_versions(mysqli $m, int $id): array
{
    if (!rt_has_table($m, 'game_kit_versions')) {
        return [];
    }
    return array_map(static fn(array $r): array => [
        'id' => (int)$r['id'], 'version' => (int)$r['version'], 'note' => (string)$r['note'],
        'at' => (string)$r['created_at'], 'by' => (string)($r['username'] ?? ''),
    ], duel_rows($m, 'SELECT v.id, v.version, v.note, v.created_at, u.username FROM game_kit_versions v LEFT JOIN utenti u ON u.id = v.created_by
        WHERE v.character_id = ? ORDER BY v.version DESC, v.id DESC LIMIT 30', 'i', [$id]));
}

/** I legami: tutti, con quelli del personaggio (per id o per categoria). */
function duel_admin_traits_for(array $char): array
{
    $out = [];
    $id = (int)$char['id'];
    $cat = strtolower(trim((string)($char['categoria'] ?? '')));
    foreach (duel_traits() as $key => $t) {
        $byCat = $cat !== '' && in_array($cat, array_map('strtolower', $t['cat'] ?? []), true);
        $out[] = ['key' => (string)$key, 'it' => (string)$t['it'], 'en' => (string)$t['en'],
            'member' => $byCat || in_array($id, $t['ids'] ?? [], true), 'by_category' => $byCat,
            'bonus' => array_map(static fn($b): string => duel_text_trait_bonus((array)$b, 'it'), $t['tiers'] ?? [])];
    }
    return $out;
}

/** La scheda dell'editor: personaggio, kit in uso e del codice, testi, versioni, legami, musica. */
function duel_admin_kit(mysqli $m, int $id): array
{
    // Fresca: arriva anche subito dopo un salvataggio nella stessa richiesta.
    duel_use_current_values($m, true);
    $char = duel_admin_char($m, $id);
    $db = duel_admin_db_kits($m)[$id] ?? null;
    $full = duel_character_kit($char, 1);
    $code = duel_admin_code_kit($char);
    $max = duel_character_kit($char, duel_rules()['level_max']);
    $own = is_file(__DIR__ . '/../../audio/ultimates/' . $id . '.mp3') ? '/audio/ultimates/' . $id . '.mp3' : '';

    return [
        'character' => [
            'id' => $id, 'name' => duel_admin_name((string)$char['nome']), 'img' => duel_media($char['img_url'] ?? ''),
            'role' => $full['role'], 'rarity' => duel_admin_rarity($full['rarity']), 'category' => (string)($char['categoria'] ?? ''),
        ],
        'source' => $db ? 'pannello' : (isset(duel_unique_kits()[$id]) ? 'unico' : 'ruolo'),
        'version' => $db['version'] ?? 0,
        'updated' => $db ? ['at' => $db['updated_at'], 'by' => $db['by']] : null,
        'kit' => duel_admin_kit_parts($full),
        'code' => duel_admin_kit_parts($code),
        'text' => duel_admin_texts($full),
        'stats' => ['lv1' => $full['stats'], 'max' => $max['stats'], 'level_max' => duel_rules()['level_max']],
        'versions' => duel_admin_kit_versions($m, $id),
        'traits' => duel_admin_traits_for($char),
        'music' => ['own' => $own, 'pull' => duel_audio($char['audio_url'] ?? ''), 'default' => '/audio/ultimates/default.mp3'],
        'catalog' => duel_admin_catalog(),
    ];
}

/** Le famiglie degli stati, coi nomi del catalogo (tavola 8). */
const DUEL_ADMIN_FAMILIES = ['dot' => 'Danni nel tempo', 'ctrl' => 'Controllo', 'deb' => 'Indebolimenti', 'buf' => 'Potenziamenti', 'pro' => 'Protezione', 'spe' => 'Speciali'];

/** Stati e terreni per i menu dell'editor, coi valori in uso. */
function duel_admin_catalog(): array
{
    $statuses = [];
    foreach (duel_statuses() as $key => $s) {
        $statuses[] = ['key' => $key, 'name' => $s['it'], 'fam' => $s['fam'], 'fam_label' => DUEL_ADMIN_FAMILIES[$s['fam']] ?? $s['fam'],
            'neg' => (bool)$s['neg'], 'v' => $s['v'], 't' => $s['t'], 'stack' => $s['stack']];
    }
    $terrains = [];
    foreach (duel_terrains() as $key => $t) {
        $terrains[] = ['key' => $key, 'name' => $t['it'], 'g' => $t['g'] ?? 2];
    }
    return ['statuses' => $statuses, 'terrains' => $terrains];
}

function duel_admin_texts(array $kit): array
{
    return ['it' => duel_kit_text($kit, 'it'), 'en' => duel_kit_text($kit, 'en')];
}

// ─────────────────────────────────────────────────────────────────────────
// Controllo di un kit mandato dal pannello
// ─────────────────────────────────────────────────────────────────────────

/** Errore di un kit: un elenco di frasi, una per problema. */
final class DuelKitInvalid extends RuntimeException
{
    public array $errors;

    public function __construct(array $errors)
    {
        parent::__construct(implode(' ', $errors));
        $this->errors = $errors;
    }
}

/**
 * Un kit dal pannello, controllato: solo le parti note, effetti del
 * catalogo, numeri dentro limiti larghi. Le chiavi che l'editor non mostra
 * (i kit unici piu' ricchi: bonus, mix, aure...) restano com'erano, purche'
 * siano dati semplici. Lancia DuelKitInvalid con tutti gli errori.
 */
function duel_admin_kit_check(array $kit): array
{
    $err = [];
    foreach (array_keys($kit) as $key) {
        if (!in_array($key, DUEL_ADMIN_KIT_KEYS, true)) {
            $err[] = "Parte del kit sconosciuta: «{$key}».";
        }
    }
    $json = json_encode($kit);
    if ($json === false || strlen($json) > 60000) {
        $err[] = 'Il kit è troppo grande.';
    }
    if (duel_admin_depth($kit) > 8) {
        $err[] = 'Il kit ha troppi livelli uno dentro l\'altro.';
    }

    $forms = is_array($kit['forms'] ?? null) ? array_keys($kit['forms']) : [];
    $moves = ['attack' => 'Attacco', 'skill' => 'Abilità', 'ultimate' => 'Ultimate'];
    foreach ($moves as $key => $label) {
        if (!isset($kit[$key])) {
            continue;
        }
        $mv = $kit[$key];
        if (!is_array($mv)) {
            $err[] = "$label: non è una mossa.";
            continue;
        }
        $name = trim((string)($mv['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 60) {
            $err[] = "$label: il nome va da 1 a 60 caratteri.";
        }
        if (!in_array($mv['target'] ?? 'enemy', DUEL_ADMIN_MOVE_TARGETS, true)) {
            $err[] = "$label: bersaglio della mossa non valido.";
        }
        duel_admin_num($err, "$label: costo", $mv['cost'] ?? null, 0, 5);
        duel_admin_num($err, "$label: ricarica", $mv['cd'] ?? null, 0, 9);
        if (!is_array($mv['effects'] ?? null) || !$mv['effects']) {
            $err[] = "$label: serve almeno un effetto.";
            continue;
        }
        duel_admin_effects_check($err, $label, $mv['effects'], $forms);
    }

    if (isset($kit['passive'])) {
        $p = $kit['passive'];
        if (!is_array($p)) {
            $err[] = 'Passiva: non è una passiva.';
        } else {
            $name = trim((string)($p['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 60) {
                $err[] = 'Passiva: il nome va da 1 a 60 caratteri.';
            }
            foreach (['mods' => 'modificatori', 'aura' => 'aura'] as $k => $label) {
                if (isset($p[$k])) {
                    duel_admin_mods_check($err, "Passiva, $label", $p[$k], DUEL_ADMIN_MODS);
                }
            }
            foreach ((array)($p['triggers'] ?? []) as $i => $tr) {
                $where = 'Passiva, momento ' . ((int)$i + 1);
                if (!is_array($tr) || !in_array($tr['when'] ?? '', DUEL_ADMIN_WHEN, true)) {
                    $err[] = "$where: quando scatta?";
                    continue;
                }
                if (in_array($tr['when'], ['self_low', 'ally_low'], true)) {
                    duel_admin_num($err, "$where: soglia di vita", $tr['below'] ?? 30, 1, 99);
                }
                duel_admin_num($err, "$where: ogni quanti turni", $tr['every'] ?? null, 1, 20);
                if (!is_array($tr['effects'] ?? null) || !$tr['effects']) {
                    $err[] = "$where: serve almeno un effetto.";
                    continue;
                }
                duel_admin_effects_check($err, $where, $tr['effects'], $forms);
            }
            if (isset($p['fatal'])) {
                duel_admin_effects_check($err, 'Passiva, colpo fatale', (array)$p['fatal'], $forms);
            }
            foreach (['block_neg' => [1, 10], 'counter' => [1, 100], 'cc_feed' => [1, 50], 'overheal_shield' => [1, 100]] as $k => [$lo, $hi]) {
                duel_admin_num($err, "Passiva: $k", $p[$k] ?? null, $lo, $hi);
            }
        }
    }

    if (isset($kit['stacks'])) {
        $s = $kit['stacks'];
        if (!is_array($s) || trim((string)($s['name'] ?? '')) === '') {
            $err[] = 'Cariche: serve un nome.';
        } else {
            duel_admin_num($err, 'Cariche: massimo', $s['max'] ?? null, 1, 20);
            duel_admin_mods_check($err, 'Cariche, per carica', $s['per'] ?? [], DUEL_ADMIN_MODS);
        }
    }
    if (isset($kit['stats_mods'])) {
        duel_admin_mods_check($err, 'Statistiche', $kit['stats_mods'], DUEL_ADMIN_STATS, -50, 100);
    }
    if (isset($kit['theme'])) {
        $th = $kit['theme'];
        if (!is_array($th) || (isset($th['color']) && !preg_match('/^#[0-9a-f]{6}$/i', (string)$th['color']))) {
            $err[] = 'Colore dell\'ultimate: serve un colore come #dc2626.';
        }
        $music = (string)($th['music'] ?? '');
        if ($music !== '' && !preg_match('~^/audio/[A-Za-z0-9_./%-]{1,180}\.(?:mp3|ogg|m4a|aac|wav)$~', $music) || str_contains($music, '..')) {
            $err[] = 'Musica dell\'ultimate: un file in /audio/.';
        }
    }
    if ($err) {
        throw new DuelKitInvalid(array_values(array_unique($err)));
    }
    return $kit;
}

function duel_admin_depth(mixed $value, int $level = 0): int
{
    if (!is_array($value)) {
        return $level;
    }
    $max = $level + 1;
    foreach ($value as $v) {
        $max = max($max, duel_admin_depth($v, $level + 1));
    }
    return $max;
}

/** Un numero facoltativo tra $lo e $hi (null = non scritto). */
function duel_admin_num(array &$err, string $where, mixed $value, float $lo, float $hi): void
{
    if ($value === null) {
        return;
    }
    if (!is_numeric($value) || (float)$value < $lo || (float)$value > $hi) {
        $err[] = "$where: un numero da " . duel_text_num($lo) . ' a ' . duel_text_num($hi) . '.';
    }
}

function duel_admin_mods_check(array &$err, string $where, mixed $mods, array $keys, float $lo = -100, float $hi = 300): void
{
    if (!is_array($mods)) {
        $err[] = "$where: non è un elenco.";
        return;
    }
    foreach ($mods as $k => $v) {
        if (!in_array($k, $keys, true)) {
            $err[] = "$where: «{$k}» non esiste.";
        } else {
            duel_admin_num($err, "$where, $k", $v, $lo, $hi);
        }
    }
}

function duel_admin_effects_check(array &$err, string $where, array $effects, array $forms): void
{
    if (count($effects) > 16) {
        $err[] = "$where: al massimo 16 effetti.";
    }
    foreach (array_values($effects) as $i => $e) {
        $at = "$where, effetto " . ($i + 1);
        if (!is_array($e) || !in_array($e['e'] ?? '', DUEL_ADMIN_EFFECTS, true)) {
            $err[] = "$at: effetto sconosciuto.";
            continue;
        }
        if (isset($e['to']) && !in_array($e['to'], DUEL_ADMIN_TO, true)) {
            $err[] = "$at: a chi va?";
        }
        $type = $e['e'];
        if (in_array($type, ['damage', 'heal', 'shield', 'advance', 'delay', 'revive'], true) && !isset($e['pct']) && !isset($e['mix'])) {
            $err[] = "$at: manca la percentuale.";
        }
        duel_admin_num($err, "$at: percentuale", $e['pct'] ?? null, 0, $type === 'damage' ? 2000 : 300);
        duel_admin_num($err, "$at: colpi", $e['hits'] ?? null, 1, 10);
        duel_admin_num($err, "$at: Difesa ignorata", $e['pen'] ?? null, 0, 100);
        duel_admin_num($err, "$at: danno critico in più", $e['cdmg'] ?? null, 0, 300);
        duel_admin_num($err, "$at: probabilità", $e['chance'] ?? null, 0, 100);
        duel_admin_num($err, "$at: turni", $e['t'] ?? null, 0, 99);
        duel_admin_num($err, "$at: cariche", $e['st'] ?? null, 1, 10);
        duel_admin_num($err, "$at: valore", $e['v'] ?? null, -1000, 1000);
        duel_admin_num($err, "$at: giri", $e['g'] ?? null, 1, 20);
        if ($type === 'ps') {
            duel_admin_num($err, "$at: PS", $e['n'] ?? 1, 1, 5);
            if (!in_array($e['side'] ?? 'own', ['own', 'steal', 'enemy'], true)) {
                $err[] = "$at: PS a chi?";
            }
        } elseif ($type === 'charge') {
            duel_admin_num($err, "$at: carica", $e['n'] ?? 0, -100, 100);
        } elseif ($type === 'stacks') {
            duel_admin_num($err, "$at: cariche (−99 le azzera)", $e['n'] ?? 1, -99, 10);
        } elseif ($type === 'cleanse') {
            duel_admin_num($err, "$at: quanti stati", $e['n'] ?? 99, 1, 99);
        }
        if (in_array($type, ['apply', 'remove'], true) && !duel_status((string)($e['k'] ?? ''))) {
            $err[] = "$at: quale stato?";
        }
        if ($type === 'terrain' && !isset(duel_terrains()[(string)($e['k'] ?? '')])) {
            $err[] = "$at: quale terreno?";
        }
        if ($type === 'form' && !in_array((string)($e['k'] ?? ''), array_map('strval', $forms), true)) {
            $err[] = "$at: la forma non è tra quelle del kit.";
        }
        if ($type === 'consume') {
            foreach ((array)($e['k'] ?? []) as $k) {
                if (!duel_status((string)$k)) {
                    $err[] = "$at: stato da far detonare sconosciuto.";
                }
            }
        }
        if (in_array($type, ['heal', 'shield'], true) && !in_array($e['of'] ?? 'src_max', $type === 'heal' ? ['src_max', 'target_max', 'damage'] : ['src_max', 'target_max'], true)) {
            $err[] = "$at: percentuale di cosa?";
        }
        if (isset($e['if'])) {
            duel_admin_cond_check($err, $at, $e['if']);
        }
        foreach ((array)($e['bonus'] ?? []) as $b) {
            duel_admin_num($err, "$at: bonus", is_array($b) ? ($b['pct'] ?? null) : 'x', 0, 500);
            if (is_array($b) && isset($b['if'])) {
                duel_admin_cond_check($err, "$at, bonus", $b['if']);
            }
        }
    }
}

function duel_admin_cond_check(array &$err, string $at, mixed $cond): void
{
    if (!is_array($cond) || !$cond) {
        $err[] = "$at: condizione vuota.";
        return;
    }
    foreach ($cond as $k => $v) {
        switch ($k) {
            case 'chance':
                duel_admin_num($err, "$at: probabilità", $v, 1, 100);
                break;
            case 'crit': case 'killed':
                break;
            case 'target_has':
                if (!in_array($v, ['neg', 'cc'], true) && !duel_status((string)$v)) {
                    $err[] = "$at: stato della condizione sconosciuto.";
                }
                break;
            case 'target_hp_below': case 'self_hp_below': case 'self_hp_above':
                duel_admin_num($err, "$at: soglia di vita", $v, 1, 99);
                break;
            case 'target_stacks':
                if (!is_array($v) || !duel_status((string)($v[0] ?? '')) || !is_numeric($v[1] ?? null)) {
                    $err[] = "$at: cariche della condizione non valide.";
                }
                break;
            default:
                $err[] = "$at: condizione «{$k}» sconosciuta.";
        }
    }
}

/**
 * Il kit gira davvero: qualche partita veloce tra bot col kit di prova. Un
 * kit scritto male (un effetto con dati che il motore non si aspetta) si
 * ferma qui e non in una partita vera.
 */
function duel_admin_kit_smoke(mysqli $m, int $id, array $kit): void
{
    try {
        duel_admin_sim($m, ['focus' => $id, 'kit' => $kit, 'n' => 4, 'seed' => 7, 'level' => 'facile']);
    } catch (DuelUserError $e) {
        throw $e;
    } catch (Throwable $e) {
        throw new DuelKitInvalid(['La prova del kit si è fermata: ' . $e->getMessage()]);
    }
}

/** Anteprima: i testi e le statistiche del kit di prova, senza salvarlo. */
function duel_admin_kit_preview(mysqli $m, int $id, array $kit): array
{
    duel_use_current_values($m);
    $char = duel_admin_char($m, $id);
    $kit = duel_admin_kit_check($kit);
    $full = duel_admin_kit_with($char, $kit);
    return ['text' => duel_admin_texts($full), 'stats' => ['lv1' => $full['stats'], 'max' => duel_admin_kit_with($char, $kit, duel_rules()['level_max'])['stats']]];
}

/** Salva un kit: lo mette in uso e ne tiene la versione. Torna il numero di versione. */
function duel_admin_kit_save(mysqli $m, int $adminId, int $id, array $kit, string $note): int
{
    duel_use_current_values($m);
    duel_admin_char($m, $id);
    $kit = duel_admin_kit_check($kit);
    duel_admin_kit_smoke($m, $id, $kit);
    $json = json_encode($kit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    $note = mb_substr(trim($note), 0, 160);
    return duel_tx($m, static function () use ($m, $adminId, $id, $json, $note): int {
        $last = (int)(duel_row($m, 'SELECT MAX(version) AS v FROM game_kit_versions WHERE character_id = ?', 'i', [$id])['v'] ?? 0);
        $cur = (int)(duel_row($m, 'SELECT version FROM game_kits WHERE character_id = ? FOR UPDATE', 'i', [$id])['version'] ?? 0);
        $version = max($last, $cur) + 1;
        duel_exec($m, 'INSERT INTO game_kits (character_id, kit_json, version, updated_by) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE kit_json = VALUES(kit_json), version = VALUES(version), updated_by = VALUES(updated_by)', 'isii', [$id, $json, $version, $adminId]);
        duel_exec($m, 'INSERT INTO game_kit_versions (character_id, version, kit_json, note, created_by) VALUES (?, ?, ?, ?, ?)',
            'iissi', [$id, $version, $json, $note, $adminId]);
        return $version;
    });
}

/** Torna al kit del codice: il kit del pannello non vale piu' (resta nelle versioni). */
function duel_admin_kit_reset(mysqli $m, int $adminId, int $id): bool
{
    duel_use_current_values($m);
    $char = duel_admin_char($m, $id);
    $code = json_encode(duel_admin_kit_parts(duel_admin_code_kit($char)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    return duel_tx($m, static function () use ($m, $adminId, $id, $code): bool {
        if (duel_exec($m, 'DELETE FROM game_kits WHERE character_id = ?', 'i', [$id]) !== 1) {
            return false;
        }
        $last = (int)(duel_row($m, 'SELECT MAX(version) AS v FROM game_kit_versions WHERE character_id = ?', 'i', [$id])['v'] ?? 0);
        duel_exec($m, 'INSERT INTO game_kit_versions (character_id, version, kit_json, note, created_by) VALUES (?, ?, ?, ?, ?)',
            'iissi', [$id, $last + 1, $code, 'Tornato al kit del codice', $adminId]);
        return true;
    });
}

/** Rimette in uso una versione vecchia (diventa la versione nuova). */
function duel_admin_kit_restore(mysqli $m, int $adminId, int $id, int $versionId): int
{
    $row = duel_row($m, 'SELECT version, kit_json FROM game_kit_versions WHERE id = ? AND character_id = ?', 'ii', [$versionId, $id]);
    $kit = $row ? json_decode((string)$row['kit_json'], true) : null;
    if (!is_array($kit)) {
        throw duel_user_error('Versione non trovata.', 'Version not found.', 404, 'NOT_FOUND');
    }
    return duel_admin_kit_save($m, $adminId, $id, $kit, 'Torna alla versione ' . (int)$row['version']);
}

/**
 * I legami nel database. Finche' game_traits e' vuota valgono quelli del
 * codice (traits.php): alla prima modifica si copiano tutti, poi il
 * pannello cambia solo chi ne fa parte.
 */
function duel_admin_traits_seed(mysqli $m): void
{
    if ((int)(duel_row($m, 'SELECT COUNT(*) AS n FROM game_traits')['n'] ?? 0) > 0) {
        return;
    }
    $over = duel_overrides();
    duel_overrides(['rules' => $over['rules'], 'status' => $over['status'], 'terrain' => $over['terrain'], 'kits' => $over['kits'], 'traits' => null]);
    $code = duel_traits();
    duel_overrides($over);
    $sort = 0;
    foreach ($code as $key => $t) {
        duel_exec($m, 'INSERT IGNORE INTO game_traits (trait_key, name_it, name_en, categories, tiers_json, active, sort) VALUES (?, ?, ?, ?, ?, 1, ?)',
            'sssssi', [(string)$key, (string)$t['it'], (string)$t['en'], implode(',', $t['cat'] ?? []), json_encode($t['tiers'] ?? [], JSON_UNESCAPED_UNICODE), $sort += 10]);
        foreach ($t['ids'] ?? [] as $cid) {
            duel_exec($m, 'INSERT IGNORE INTO game_character_traits (character_id, trait_key) VALUES (?, ?)', 'is', [(int)$cid, (string)$key]);
        }
    }
}

/** I legami di un personaggio (solo quelli per nome: quelli per categoria restano). Torna [aggiunti, tolti]. */
function duel_admin_traits_set(mysqli $m, int $id, array $keys): array
{
    $char = duel_admin_char($m, $id);
    return duel_tx($m, static function () use ($m, $id, $keys, $char): array {
        duel_admin_traits_seed($m);
        $known = array_map(static fn(array $r): string => (string)$r['trait_key'], duel_rows($m, 'SELECT trait_key FROM game_traits'));
        $want = array_values(array_intersect($known, array_map('strval', $keys)));
        $have = array_map(static fn(array $r): string => (string)$r['trait_key'], duel_rows($m, 'SELECT trait_key FROM game_character_traits WHERE character_id = ?', 'i', [$id]));
        $add = array_values(array_diff($want, $have));
        $drop = array_values(array_diff($have, $want));
        foreach ($add as $k) {
            duel_exec($m, 'INSERT IGNORE INTO game_character_traits (character_id, trait_key) VALUES (?, ?)', 'is', [$id, $k]);
        }
        foreach ($drop as $k) {
            duel_exec($m, 'DELETE FROM game_character_traits WHERE character_id = ? AND trait_key = ?', 'is', [$id, $k]);
        }
        return [$add, $drop, (string)$char['nome']];
    });
}

// ─────────────────────────────────────────────────────────────────────────
// Stati e regole
// ─────────────────────────────────────────────────────────────────────────

/**
 * Le costanti che il pannello puo' cambiare, con il nome per chi le
 * regola. Fuori: la squadra da 3, il livello massimo e la scala dei turni,
 * che reggono il resto del sito.
 */
function duel_admin_rule_labels(): array
{
    return [
        'Danno' => [
            'def_k' => ['Quanto pesa la Difesa (K)', 'Danno = ATK × % × K / (K + DEF). Più alto, meno conta la Difesa.'],
            'variance_min' => ['Caso: minimo %', 'Ogni colpo varia tra minimo e massimo.'],
            'variance_max' => ['Caso: massimo %', ''],
            'crit_cap' => ['Tasso critico massimo', ''],
            'crit_dmg_base' => ['Danno critico di base %', ''],
        ],
        'Punti squadra e carica' => [
            'ps_start' => ['PS a inizio partita', ''],
            'ps_max' => ['PS massimi', ''],
            'charge_on_hit' => ['Carica a chi viene colpito', ''],
            'charge_on_kill' => ['Carica a chi mette KO', ''],
            'charge_guard_factor' => ['Carica con la Guardia %', 'Della carica di una mossa.'],
            'guard_reduction' => ['Guardia: danni in meno %', ''],
        ],
        'Tetti' => [
            'shield_cap' => ['Scudo massimo % HP', ''],
            'shield_decay' => ['Scudo perso a ogni turno %', ''],
            'reduction_cap' => ['Riduzione dei danni massima %', ''],
            'dodge_cap' => ['Schivata massima %', ''],
            'speed_up_cap' => ['Velocità in più massima %', ''],
            'speed_down_cap' => ['Velocità in meno massima %', ''],
            'stat_down_cap' => ['Statistiche in meno massime %', ''],
        ],
        'Stati' => [
            'tenacity_cut' => ['Tenacia che accorcia i controlli', 'Oltre questa Tenacia i controlli durano un turno in meno.'],
            'cc_resist_turns' => ['Turni di Resistenza al controllo', 'Dopo ogni controllo.'],
        ],
        'Sfinimento e partita' => [
            'fatigue_turn' => ['Sfinimento dal turno', ''],
            'fatigue_heal_cut' => ['Sfinimento: cure e scudi in meno %', ''],
            'fatigue_step' => ['Sfinimento: ogni quanti turni', ''],
            'fatigue_dmg' => ['Sfinimento: danni in più %', ''],
            'turn_limit' => ['Limite di turni', 'Poi vince chi ha più vita in percentuale.'],
            'ranked_budget' => ['Costo massimo della squadra in Ranked', ''],
            'level_step' => ['Livello: HP, ATK, DEF in più %', ''],
        ],
    ];
}

/** I numeri dei terreni nel pannello: nome, unita' e a cosa servono. */
const DUEL_ADMIN_TERRAIN_LABELS = [
    'g' => ['Durata di base', 'giri', 'Per il terreno da subito della Torre e per i kit che non scrivono la loro durata.'],
    'heal_pct' => ['Cure', '%', 'Le cure che fanno tutti, in più o in meno.'],
    'crit' => ['Critico', '%', 'Probabilità di critico di tutti.'],
    'spd_pct' => ['Velocità', '%', 'Velocità di tutti.'],
    'regen_pct' => ['Rigenerazione', '%', 'Quanto cura lo stato Rigenerazione.'],
    'stealth_bonus' => ['Furtività', 'turni in più', 'La Furtività messa mentre c\'è il terreno dura di più.'],
    'shock_ps' => ['Elettrizzato', 'PS persi in più', 'Chi è Elettrizzato e usa un\'abilità perde più PS.'],
    'round_poison' => ['Veleno a fine giro', 'cariche', 'A chi non ha già Veleno.'],
];

/** I numeri e gli stati bloccati di un terreno, per il pannello. */
function duel_admin_terrain_fields(array $t): array
{
    $out = [];
    foreach (array_keys(DUEL_TERRAIN_FIELDS) as $field) {
        $out[$field] = duel_terrain_field($t, $field);
    }
    return $out + ['blocks' => array_values($t['blocks'] ?? [])];
}

/** Le chiavi dei terreni che un kit mette, ovunque stiano (passive, mosse, forme). */
function duel_admin_terrain_keys(array $node): array
{
    $out = ($node['e'] ?? null) === 'terrain' && isset($node['k']) ? [(string)$node['k']] : [];
    foreach ($node as $child) {
        if (is_array($child)) {
            $out = array_merge($out, duel_admin_terrain_keys($child));
        }
    }
    return array_values(array_unique($out));
}

/** Chi mette ogni terreno: [kits => [terreno => nomi], floors => [terreno => piani della Torre]]. */
function duel_admin_terrain_users(mysqli $m): array
{
    $kits = [];
    foreach (duel_admin_characters($m) as $c) {
        foreach (duel_admin_terrain_keys(duel_character_kit($c, 1)) as $key) {
            $kits[$key][] = duel_admin_name((string)$c['nome']);
        }
    }
    foreach ($kits as &$names) {
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
    }
    unset($names);
    // I piani come sono scritti, senza far partire il generatore della Torre.
    $floors = [];
    if (rt_has_table($m, 'game_tower_floors')) {
        foreach (duel_rows($m, 'SELECT floor, rule_json FROM game_tower_floors ORDER BY floor') as $r) {
            $rule = json_decode((string)($r['rule_json'] ?? ''), true);
            if (is_array($rule) && !empty($rule['terrain'])) {
                $floors[(string)$rule['terrain']][] = (int)$r['floor'];
            }
        }
    }
    return ['kits' => $kits, 'floors' => $floors];
}

/** I valori di base (codice) e quelli in uso di regole, stati e terreni. */
function duel_admin_values(mysqli $m): array
{
    $over = duel_use_current_values($m, true);
    $now = duel_rules();
    $nowStatus = duel_statuses();
    $nowTerrain = duel_terrains();
    duel_overrides(['kits' => $over['kits'], 'traits' => $over['traits']]);
    $base = duel_rules();
    $baseStatus = duel_statuses();
    $baseTerrain = duel_terrains();
    duel_overrides($over);

    $groups = [];
    foreach (duel_admin_rule_labels() as $group => $keys) {
        $rows = [];
        foreach ($keys as $key => [$label, $help]) {
            $rows[] = ['key' => $key, 'label' => $label, 'help' => $help, 'base' => $base[$key], 'value' => $now[$key], 'changed' => isset($over['rules'][$key])];
        }
        $groups[] = ['name' => $group, 'rules' => $rows];
    }
    $statuses = [];
    foreach ($baseStatus as $key => $s) {
        $statuses[] = [
            'key' => $key, 'name' => $s['it'], 'fam' => $s['fam'], 'fam_label' => DUEL_ADMIN_FAMILIES[$s['fam']] ?? $s['fam'], 'neg' => (bool)$s['neg'], 'cc' => (bool)$s['cc'],
            'stack' => $s['stack'], 'desc' => strtr($nowStatus[$key]['d_it'], ['{v}' => duel_text_num((float)$nowStatus[$key]['v']), '{t}' => (string)$nowStatus[$key]['t'], '{max}' => (string)$nowStatus[$key]['max']]),
            'base' => ['v' => $s['v'], 't' => $s['t'], 'max' => $s['max']],
            'value' => ['v' => $nowStatus[$key]['v'], 't' => $nowStatus[$key]['t'], 'max' => $nowStatus[$key]['max']],
            'changed' => isset($over['status'][$key]),
        ];
    }
    $users = duel_admin_terrain_users($m);
    $terrains = [];
    foreach ($baseTerrain as $key => $t) {
        $terrains[] = [
            'key' => $key, 'name' => $t['it'], 'name_en' => $t['en'], 'desc' => $nowTerrain[$key]['d_it'],
            'base' => duel_admin_terrain_fields($t), 'value' => duel_admin_terrain_fields($nowTerrain[$key]),
            'changed' => isset($over['terrain'][$key]),
            'kits' => $users['kits'][$key] ?? [], 'floors' => $users['floors'][$key] ?? [],
        ];
    }
    $fields = [];
    foreach (DUEL_TERRAIN_FIELDS as $key => [$min, $max]) {
        [$label, $unit, $help] = DUEL_ADMIN_TERRAIN_LABELS[$key];
        $fields[] = ['key' => $key, 'label' => $label, 'unit' => $unit, 'help' => $help, 'min' => $min, 'max' => $max, 'mod' => in_array($key, DUEL_TERRAIN_MODS, true)];
    }
    return ['groups' => $groups, 'statuses' => $statuses, 'terrains' => $terrains, 'terrain_fields' => $fields];
}

/**
 * Valori dal pannello, controllati: [rules => [k => numero|null], status =>
 * [k => [v|t|max => numero|null]], terrain => [k => [numero => valore|null,
 * block => [stato => 1|0|null]]]]. null = torna al valore del codice. Per i
 * terreni arriva l'elenco intero degli stati bloccati (blocks): qui diventa
 * uno stato per riga, solo dove e' diverso dal codice.
 */
function duel_admin_values_check(array $in): array
{
    $err = [];
    $out = ['rules' => [], 'status' => [], 'terrain' => []];
    $allowed = [];
    foreach (duel_admin_rule_labels() as $keys) {
        $allowed += $keys;
    }
    foreach ((array)($in['rules'] ?? []) as $k => $v) {
        if (!isset($allowed[$k])) {
            $err[] = "Regola sconosciuta: $k.";
        } elseif ($v === null || $v === '') {
            $out['rules'][$k] = null;
        } elseif (!is_numeric($v) || (float)$v < 0 || (float)$v > 100000) {
            $err[] = $allowed[$k][0] . ': un numero da 0 in su.';
        } else {
            $out['rules'][$k] = (float)$v;
        }
    }
    $limits = ['v' => [-1000, 1000], 't' => [0, 99], 'max' => [1, 20]];
    foreach ((array)($in['status'] ?? []) as $k => $fields) {
        $def = duel_status((string)$k);
        if (!$def || !is_array($fields)) {
            $err[] = "Stato sconosciuto: $k.";
            continue;
        }
        foreach ($fields as $f => $v) {
            if (!isset($limits[$f])) {
                $err[] = "{$def['it']}: campo sconosciuto.";
            } elseif ($v === null || $v === '') {
                $out['status'][$k][$f] = null;
            } elseif (!is_numeric($v) || (float)$v < $limits[$f][0] || (float)$v > $limits[$f][1]) {
                $err[] = "{$def['it']}: valore fuori misura.";
            } else {
                $out['status'][$k][$f] = (float)$v;
            }
        }
    }
    $codeTerrains = duel_terrains_code();
    foreach ((array)($in['terrain'] ?? []) as $k => $fields) {
        $def = $codeTerrains[(string)$k] ?? null;
        if (!$def || !is_array($fields)) {
            $err[] = "Terreno sconosciuto: $k.";
            continue;
        }
        foreach ($fields as $f => $v) {
            if ($f === 'blocks') {
                $want = $v === null ? ($def['blocks'] ?? []) : (is_array($v) ? array_map('strval', $v) : null);
                if ($want === null) {
                    $err[] = "{$def['it']}: stati bloccati non validi.";
                    continue;
                }
                foreach (array_diff($want, array_keys(duel_statuses())) as $bad) {
                    $err[] = "{$def['it']}: stato sconosciuto ($bad).";
                }
                foreach (array_keys(duel_statuses()) as $s) {
                    $on = in_array($s, $want, true);
                    $out['terrain'][$k]['block'][$s] = $on === in_array($s, $def['blocks'] ?? [], true) ? null : ($on ? 1 : 0);
                }
            } elseif (!isset(DUEL_TERRAIN_FIELDS[$f])) {
                $err[] = "{$def['it']}: campo sconosciuto.";
            } elseif ($v === null || $v === '') {
                $out['terrain'][$k][$f] = null;
            } else {
                [$min, $max] = DUEL_TERRAIN_FIELDS[$f];
                if (!is_numeric($v) || (float)$v != (int)$v || (int)$v < $min || (int)$v > $max) {
                    $err[] = "{$def['it']}, " . DUEL_ADMIN_TERRAIN_LABELS[$f][0] . ": un numero intero da $min a $max.";
                } else {
                    $out['terrain'][$k][$f] = (int)$v;
                }
            }
        }
    }
    if ($err) {
        throw new DuelKitInvalid($err);
    }
    return $out;
}

/** Le regole, gli stati e i terreni di prova sopra quelli in uso (per la prova tra bot). */
function duel_admin_values_merge(array $over, array $values): array
{
    foreach ($values['terrain'] ?? [] as $k => $fields) {
        foreach ($fields as $f => $v) {
            if ($f === 'block') {
                foreach ($v as $s => $on) {
                    if ($on === null) {
                        unset($over['terrain'][$k]['block'][$s]);
                    } else {
                        $over['terrain'][$k]['block'][$s] = $on;
                    }
                }
                if (empty($over['terrain'][$k]['block'])) {
                    unset($over['terrain'][$k]['block']);
                }
            } elseif ($v === null) {
                unset($over['terrain'][$k][$f]);
            } else {
                $over['terrain'][$k][$f] = $v;
            }
        }
        if (empty($over['terrain'][$k])) {
            unset($over['terrain'][$k]);
        }
    }
    foreach ($values['rules'] ?? [] as $k => $v) {
        if ($v === null) {
            unset($over['rules'][$k]);
        } else {
            $over['rules'][$k] = $v;
        }
    }
    foreach ($values['status'] ?? [] as $k => $fields) {
        foreach ($fields as $f => $v) {
            if ($v === null) {
                unset($over['status'][$k][$f]);
            } else {
                $over['status'][$k][$f] = $v;
            }
        }
        if (empty($over['status'][$k])) {
            unset($over['status'][$k]);
        }
    }
    return $over;
}

/** Salva i valori: un valore uguale a quello del codice si toglie dalla tabella. Torna le modifiche. */
function duel_admin_values_save(mysqli $m, int $adminId, array $values): array
{
    $over = duel_use_current_values($m);
    duel_overrides(['kits' => $over['kits'], 'traits' => $over['traits']]);
    $base = duel_rules();
    $baseStatus = duel_statuses();
    $baseTerrain = duel_terrains();
    duel_overrides($over);
    $changes = [];
    duel_tx($m, static function () use ($m, $adminId, $values, $base, $baseStatus, $baseTerrain, &$changes): void {
        $put = static function (string $key, ?float $value, float $codeValue) use ($m, $adminId, &$changes): void {
            if ($value === null || abs($value - $codeValue) < 0.0005) {
                if (duel_exec($m, 'DELETE FROM game_catalog_values WHERE k = ?', 's', [$key]) > 0) {
                    $changes[$key] = 'codice';
                }
                return;
            }
            duel_exec($m, 'INSERT INTO game_catalog_values (k, v, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_by = VALUES(updated_by)',
                'sdi', [$key, $value, $adminId]);
            $changes[$key] = $value;
        };
        foreach ($values['rules'] as $k => $v) {
            $put('rule.' . $k, $v, (float)$base[$k]);
        }
        foreach ($values['status'] as $k => $fields) {
            foreach ($fields as $f => $v) {
                $put("status.$k.$f", $v, (float)$baseStatus[$k][$f]);
            }
        }
        foreach ($values['terrain'] ?? [] as $k => $fields) {
            foreach ($fields as $f => $v) {
                if ($f === 'block') {
                    foreach ($v as $s => $on) {
                        $put("terrain.$k.block.$s", $on === null ? null : (float)$on, in_array($s, $baseTerrain[$k]['blocks'] ?? [], true) ? 1.0 : 0.0);
                    }
                } else {
                    $put("terrain.$k.$f", $v === null ? null : (float)$v, (float)duel_terrain_field($baseTerrain[$k], $f));
                }
            }
        }
    });
    return $changes;
}

// ─────────────────────────────────────────────────────────────────────────
// Prove tra bot
// ─────────────────────────────────────────────────────────────────────────

/**
 * Un blocco di partite tra bot con le regole della Ranked (squadre a caso
 * di costo vicino al massimo, almeno un attaccante), a lati scambiati.
 * $o:
 * - focus: id del personaggio da provare, sempre in una squadra (0 = tutti a caso);
 * - kit: il kit di prova del focus (null = quello in uso);
 * - values: regole e stati di prova (duel_admin_values_check; null = in uso);
 * - n: partite (pari), seed, level (livello dei bot).
 * Torna partite, vittorie del focus, turni, finite al limite, danno medio
 * dell'abilita'; e per ogni personaggio e ruolo partite e vittorie.
 */
function duel_admin_sim(mysqli $m, array $o): array
{
    $current = duel_use_current_values($m);
    $over = $current;
    $focus = (int)($o['focus'] ?? 0);
    if (!empty($o['values'])) {
        $over = duel_admin_values_merge($over, $o['values']);
    }
    if ($focus > 0 && isset($o['kit']) && is_array($o['kit'])) {
        $over['kits'][$focus] = $o['kit'];
    }
    duel_overrides($over);
    try {
        return duel_admin_sim_run($m, $focus, max(2, min(400, (int)($o['n'] ?? 200))), (int)($o['seed'] ?? 1), (string)($o['level'] ?? 'difficile'));
    } finally {
        duel_overrides($current);
    }
}

function duel_admin_sim_run(mysqli $m, int $focus, int $n, int $seed, string $level): array
{
    $chars = duel_admin_characters($m);
    if ($focus > 0 && !isset($chars[$focus])) {
        throw duel_user_error('Personaggio non trovato.', 'Character not found.', 404, 'NOT_FOUND');
    }
    $kits = [];
    $info = [];
    foreach ($chars as $id => $c) {
        $kits[$id] = duel_character_kit($c, 6);
        $info[$id] = ['role' => $kits[$id]['role'], 'cost' => duel_rarity($kits[$id]['rarity'])['cost']];
    }
    $budget = duel_rules()['ranked_budget'];
    $attackers = ['DPS', 'Burst DPS', 'Sub DPS', 'Bruiser'];
    $rnd = new Random\Randomizer(new Random\Engine\Xoshiro256StarStar($seed));
    $pick = static function (?int $with) use ($rnd, $info, $budget, $attackers): array {
        for ($try = 0; $try < 50000; $try++) {
            $team = $rnd->pickArrayKeys($info, 3);
            if ($with !== null) {
                if (in_array($with, $team, true)) {
                    continue;
                }
                $team[0] = $with;
            }
            $cost = 0;
            $att = 0;
            foreach ($team as $i) {
                $cost += $info[$i]['cost'];
                $att += in_array($info[$i]['role'], $attackers, true) ? 1 : 0;
            }
            if ($cost <= $budget && $cost >= $budget - 2 && $att >= 1) {
                return $team;
            }
        }
        throw new RuntimeException('Nessuna squadra possibile con questo costo massimo.');
    };

    $out = ['games' => 0, 'wins' => 0, 'draws' => 0, 'turns' => 0, 'limit' => 0, 'skill_uses' => 0, 'skill_dmg' => 0, 'per' => [], 'roles' => []];
    for ($p = 0; $p < intdiv($n, 2); $p++) {
        $ta = $pick($focus > 0 ? $focus : null);
        do {
            $tb = $pick(null);
        } while ($focus > 0 && in_array($focus, $tb, true));
        foreach ([[$ta, $tb, 0], [$tb, $ta, 1]] as $g => [$x, $y, $mine]) {
            [$st] = duel_new_match([array_map(static fn($i) => $kits[$i], $x), array_map(static fn($i) => $kits[$i], $y)], $seed * 1000003 + $p * 2 + $g);
            $unit = null;
            foreach ($st['units'] as $uid => $u) {
                if ($focus > 0 && $u['pid'] === $focus && $u['side'] === $mine) {
                    $unit = $uid;
                }
            }
            $move = '';
            for ($i = 0; $i < 700 && !$st['over']; $i++) {
                [$mv, $tg] = duel_bot_choose($st, $level, $p * 2 + $g);
                [$st, $events] = duel_apply($st, $mv, $tg);
                if ($unit === null) {
                    continue;
                }
                foreach ($events as $ev) {
                    if ($ev['t'] === 'move') {
                        $move = $ev['u'] === $unit ? (string)$ev['m'] : '';
                        if ($move === 'abilita') {
                            $out['skill_uses']++;
                        }
                    } elseif ($ev['t'] === 'hit' && $move === 'abilita' && ($ev['src'] ?? null) === $unit) {
                        $out['skill_dmg'] += (int)($ev['amount'] ?? 0);
                    }
                }
            }
            $out['turns'] += (int)$st['turn'];
            $out['limit'] += ($st['reason'] ?? '') === 'limit' ? 1 : 0;
            if ($st['winner'] === null) {
                $out['draws']++;
                continue;
            }
            $out['games']++;
            $out['wins'] += $st['winner'] === $mine ? 1 : 0;
            foreach ($st['units'] as $u) {
                $pid = (int)$u['pid'];
                $won = $u['side'] === $st['winner'] ? 1 : 0;
                $out['per'][$pid]['g'] = ($out['per'][$pid]['g'] ?? 0) + 1;
                $out['per'][$pid]['w'] = ($out['per'][$pid]['w'] ?? 0) + $won;
                $role = $info[$pid]['role'] ?? '?';
                $out['roles'][$role]['g'] = ($out['roles'][$role]['g'] ?? 0) + 1;
                $out['roles'][$role]['w'] = ($out['roles'][$role]['w'] ?? 0) + $won;
            }
        }
    }
    if ($focus > 0) {
        unset($out['per']);
    }
    return $out;
}

/** «Rifai le prove» del Bilanciamento: si riparte da zero. */
function duel_admin_balance_sim_start(mysqli $m): void
{
    if (!duel_phase7_ready($m)) {
        throw duel_user_error('Manca la migrazione migrations/2026_10_21_duelli2_fase7.sql: eseguila in phpMyAdmin.', 'Missing migration.', 503, 'MIGRATION');
    }
    duel_exec($m, 'DELETE FROM game_balance_sim');
    duel_setting_set($m, 'sim_games', '0');
    duel_setting_set($m, 'sim_at', date('Y-m-d H:i:s'));
}

/** Un blocco delle prove del Bilanciamento, sommato a quelli prima. */
function duel_admin_balance_sim_chunk(mysqli $m, int $seed, int $n): array
{
    if (!duel_phase7_ready($m)) {
        throw duel_user_error('Manca la migrazione della fase 7.', 'Missing migration.', 503, 'MIGRATION');
    }
    $r = duel_admin_sim($m, ['focus' => 0, 'n' => $n, 'seed' => $seed, 'level' => 'difficile']);
    duel_tx($m, static function () use ($m, $r): void {
        foreach ($r['per'] as $pid => $s) {
            duel_exec($m, 'INSERT INTO game_balance_sim (character_id, games, wins) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE games = games + VALUES(games), wins = wins + VALUES(wins)', 'iii', [(int)$pid, (int)$s['g'], (int)$s['w']]);
        }
        duel_exec($m, "UPDATE game_settings SET v = CAST(v AS UNSIGNED) + ? WHERE k = 'sim_games'", 'i', [$r['games'] + $r['draws']]);
    });
    duel_settings($m, true);
    return ['games' => $r['games'] + $r['draws'], 'total' => (int)(duel_settings($m)['sim_games'] ?? 0)];
}

// ─────────────────────────────────────────────────────────────────────────
// Bilanciamento
// ─────────────────────────────────────────────────────────────────────────

/**
 * Vittorie e scelte per personaggio, ruolo e rarita': dalle partite vere
 * degli ultimi $days giorni (Ranked e Veloce, finite) e dalle prove tra bot.
 */
function duel_admin_balance(mysqli $m, int $days): array
{
    duel_use_current_values($m);
    $chars = duel_admin_characters($m);
    $real = [];
    $matches = 0;
    $rows = duel_rows($m, "SELECT state_json FROM game_matches WHERE engine_version IS NOT NULL AND status = 'finished' AND mode IN ('ranked', 'casual')
        AND finished_at >= NOW() - INTERVAL ? DAY AND state_json IS NOT NULL ORDER BY id DESC LIMIT 3000", 'i', [$days]);
    foreach ($rows as $r) {
        $st = json_decode((string)$r['state_json'], true);
        if (!is_array($st) || !isset($st['units'])) {
            continue;
        }
        $matches++;
        $winner = $st['winner'] ?? null;
        foreach ($st['units'] as $u) {
            $pid = (int)($u['pid'] ?? 0);
            $real[$pid]['g'] = ($real[$pid]['g'] ?? 0) + 1;
            $real[$pid]['w'] = ($real[$pid]['w'] ?? 0) + ($winner !== null && (int)$u['side'] === (int)$winner ? 1 : 0);
        }
    }
    $sim = [];
    if (duel_phase7_ready($m)) {
        foreach (duel_rows($m, 'SELECT character_id, games, wins FROM game_balance_sim') as $r) {
            $sim[(int)$r['character_id']] = ['g' => (int)$r['games'], 'w' => (int)$r['wins']];
        }
    }

    $pct = static fn(array $s): ?float => ($s['g'] ?? 0) > 0 ? round(100 * $s['w'] / $s['g'], 1) : null;
    $add = static function (array &$bucket, string $key, array $r, array $s): void {
        foreach (['real' => $r, 'sim' => $s] as $kind => $v) {
            $bucket[$key][$kind]['g'] = ($bucket[$key][$kind]['g'] ?? 0) + $v['g'];
            $bucket[$key][$kind]['w'] = ($bucket[$key][$kind]['w'] ?? 0) + $v['w'];
        }
    };
    $rows = [];
    $roles = [];
    $rarities = [];
    foreach ($chars as $id => $c) {
        $kit = duel_character_kit($c, 1);
        $r = $real[$id] ?? ['g' => 0, 'w' => 0];
        $s = $sim[$id] ?? ['g' => 0, 'w' => 0];
        // Il giudizio: le partite vere se sono abbastanza, altrimenti le prove.
        $judge = $r['g'] >= 20 ? $pct($r) : ($s['g'] >= 40 ? $pct($s) : null);
        $rows[] = [
            'id' => $id, 'name' => duel_admin_name((string)$c['nome']), 'img' => duel_media($c['img_url'] ?? ''),
            'role' => $kit['role'], 'rarity' => duel_admin_rarity($kit['rarity']),
            'picked' => $matches > 0 ? round(100 * $r['g'] / (2 * $matches), 1) : null,
            'real' => ['games' => $r['g'], 'win' => $pct($r)], 'sim' => ['games' => $s['g'], 'win' => $pct($s)],
            'status' => $judge === null ? 'pochi' : ($judge >= DUEL_ADMIN_HIGH ? 'limare' : ($judge <= DUEL_ADMIN_LOW ? 'rinforzare' : 'ok')),
        ];
        $add($roles, $kit['role'], $r, $s);
        $add($rarities, $kit['rarity'], $r, $s);
    }
    $group = static function (array $bucket, callable $label) use ($pct): array {
        $out = [];
        foreach ($bucket as $key => $v) {
            $out[] = ['key' => $key, 'label' => $label($key), 'real' => ['games' => $v['real']['g'], 'win' => $pct($v['real'])],
                'sim' => ['games' => $v['sim']['g'], 'win' => $pct($v['sim'])]];
        }
        return $out;
    };
    $settings = duel_settings($m, true);
    return [
        'days' => $days, 'matches' => $matches, 'high' => DUEL_ADMIN_HIGH, 'low' => DUEL_ADMIN_LOW,
        'sim' => ['ready' => duel_phase7_ready($m), 'games' => (int)($settings['sim_games'] ?? 0), 'at' => (string)($settings['sim_at'] ?? '')],
        'characters' => $rows,
        'roles' => $group($roles, static fn($k) => $k),
        'rarities' => $group($rarities, static fn($k) => duel_admin_rarity((string)$k)['label']),
    ];
}

// ─────────────────────────────────────────────────────────────────────────
// Stagioni
// ─────────────────────────────────────────────────────────────────────────

function duel_admin_seasons(mysqli $m): array
{
    $settings = duel_settings($m, true);
    $out = [
        'ready' => duel_phase6_ready($m), 'on' => ($settings['seasons'] ?? '0') === '1',
        'rewards' => duel_season_rewards($m), 'daily_win' => (int)($settings['daily_win'] ?? DUEL_DAILY_WIN),
        'days' => duel_season_days($m), 'placement' => DUEL_SEASON_PLACEMENT, 'current' => null, 'past' => [],
        'tiers' => array_map(static fn(string $k): string => duel_rank_label($k, 'it'), array_combine(array_keys(DUEL_SEASON_REWARDS), array_keys(DUEL_SEASON_REWARDS))),
    ];
    if (!$out['ready']) {
        return $out;
    }
    $cur = duel_row($m, "SELECT * FROM game_seasons WHERE status = 'active' ORDER BY number DESC LIMIT 1");
    if ($cur) {
        $n = duel_row($m, 'SELECT COUNT(*) AS players, SUM(games >= ?) AS placed FROM game_season_ratings WHERE season_id = ?', 'ii', [DUEL_SEASON_PLACEMENT, (int)$cur['id']]);
        $out['current'] = duel_season_view($cur) + ['id' => (int)$cur['id'], 'name' => (string)($cur['name'] ?? ''),
            'players' => (int)($n['players'] ?? 0), 'placed' => (int)($n['placed'] ?? 0)];
    }
    foreach (duel_rows($m, "SELECT s.*, COUNT(r.user_id) AS players, SUM(r.games >= ?) AS placed, SUM(r.reward) AS godos
        FROM game_seasons s LEFT JOIN game_season_ratings r ON r.season_id = s.id WHERE s.status = 'closed' GROUP BY s.id ORDER BY s.number DESC LIMIT 20",
        'i', [DUEL_SEASON_PLACEMENT]) as $s) {
        $top = duel_rows($m, 'SELECT u.username, r.peak, r.tier FROM game_season_ratings r LEFT JOIN utenti u ON u.id = r.user_id
            WHERE r.season_id = ? AND r.games >= ? ORDER BY r.peak DESC LIMIT 3', 'ii', [(int)$s['id'], DUEL_SEASON_PLACEMENT]);
        $out['past'][] = [
            'id' => (int)$s['id'], 'number' => (int)$s['number'], 'name' => (string)($s['name'] ?? ''),
            'starts_at' => (string)$s['starts_at'], 'ends_at' => (string)($s['closed_at'] ?? $s['ends_at']),
            'players' => (int)$s['players'], 'placed' => (int)($s['placed'] ?? 0), 'godos' => (int)($s['godos'] ?? 0),
            'top' => array_map(static fn(array $t): array => ['username' => (string)($t['username'] ?? '?'), 'peak' => (int)$t['peak'],
                'tier' => duel_rank_label((string)($t['tier'] ?? ''), 'it')], $top),
        ];
    }
    return $out;
}

/** Nome di una stagione (anche chiusa) e fine di quella in corso. */
function duel_admin_season_save(mysqli $m, int $id, string $name, ?string $endsAt): array
{
    $s = duel_row($m, 'SELECT * FROM game_seasons WHERE id = ?', 'i', [$id]);
    if (!$s) {
        throw duel_user_error('Stagione non trovata.', 'Season not found.', 404, 'NOT_FOUND');
    }
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (mb_strlen($name) > 60) {
        throw duel_user_error('Il nome è lungo al massimo 60 caratteri.', 'Name too long.', 422, 'INVALID');
    }
    $changes = [];
    if ($name !== (string)($s['name'] ?? '')) {
        duel_exec($m, 'UPDATE game_seasons SET name = ? WHERE id = ?', 'si', [$name !== '' ? $name : null, $id]);
        $changes['nome'] = ['da' => (string)($s['name'] ?? ''), 'a' => $name];
    }
    if ($endsAt !== null && $endsAt !== '' && (string)$s['status'] === 'active') {
        $ts = strtotime($endsAt);
        if ($ts === false || $ts < time() + 3600 || $ts > strtotime((string)$s['starts_at']) + 180 * 86400) {
            throw duel_user_error('La fine va almeno tra un\'ora e al massimo 180 giorni dopo l\'inizio.', 'Invalid end date.', 422, 'INVALID');
        }
        $value = date('Y-m-d H:i:s', $ts);
        if ($value !== (string)$s['ends_at']) {
            duel_exec($m, 'UPDATE game_seasons SET ends_at = ? WHERE id = ?', 'si', [$value, $id]);
            $changes['fine'] = ['da' => (string)$s['ends_at'], 'a' => $value];
        }
    }
    return $changes;
}

/** Premi per fascia, premio del giorno e durata delle stagioni nuove. */
function duel_admin_season_rules(mysqli $m, array $rewards, ?int $dailyWin, ?int $days): array
{
    $now = duel_season_rewards($m);
    $changes = [];
    foreach ($rewards as $tier => $godos) {
        if (!isset($now[$tier]) || !is_numeric($godos) || (int)$godos < 0 || (int)$godos > 100000) {
            throw duel_user_error('Premi: un numero di Godos da 0 a 100.000 per fascia.', 'Invalid rewards.', 422, 'INVALID');
        }
        if ((int)$godos !== $now[$tier]) {
            duel_setting_set($m, 'reward_' . $tier, (string)(int)$godos);
            $changes['premio_' . $tier] = ['da' => $now[$tier], 'a' => (int)$godos];
        }
    }
    if ($dailyWin !== null) {
        if ($dailyWin < 0 || $dailyWin > 10000) {
            throw duel_user_error('Prima vittoria del giorno: da 0 a 10.000 Godos.', 'Invalid daily reward.', 422, 'INVALID');
        }
        $old = (int)(duel_settings($m)['daily_win'] ?? DUEL_DAILY_WIN);
        if ($dailyWin !== $old) {
            duel_setting_set($m, 'daily_win', (string)$dailyWin);
            $changes['vittoria_del_giorno'] = ['da' => $old, 'a' => $dailyWin];
        }
    }
    if ($days !== null) {
        if ($days < 7 || $days > 120) {
            throw duel_user_error('Una stagione dura da 7 a 120 giorni.', 'Invalid length.', 422, 'INVALID');
        }
        $old = duel_season_days($m);
        if ($days !== $old) {
            duel_setting_set($m, 'season_days', (string)$days);
            $changes['giorni'] = ['da' => $old, 'a' => $days];
        }
    }
    return $changes;
}

// ─────────────────────────────────────────────────────────────────────────
// Partite
// ─────────────────────────────────────────────────────────────────────────

const DUEL_ADMIN_LIVE = ['waiting', 'team_select', 'active'];

function duel_admin_matches(mysqli $m, string $filter, string $mode, int $page): array
{
    $where = 'g.engine_version IS NOT NULL';
    $types = '';
    $params = [];
    if ($filter === 'live') {
        $where .= " AND g.status IN ('waiting', 'team_select', 'active')";
    } elseif (in_array($filter, ['finished', 'cancelled'], true)) {
        $where .= ' AND g.status = ?';
        $types .= 's';
        $params[] = $filter;
    }
    if (in_array($mode, ['ranked', 'casual', 'private', 'bot'], true)) {
        $where .= ' AND g.mode = ?';
        $types .= 's';
        $params[] = $mode;
    }
    $per = 30;
    $page = max(1, $page);
    $total = (int)(duel_row($m, "SELECT COUNT(*) AS n FROM game_matches g WHERE $where", $types, $params)['n'] ?? 0);
    $rows = duel_rows($m, "SELECT g.id, g.mode, g.status, g.player1_id, g.player2_id, g.winner_id, g.end_reason, g.bot_level, g.is_async, g.version,
            g.created_at, g.finished_at, g.state_json, u1.username AS p1, u2.username AS p2
        FROM game_matches g LEFT JOIN utenti u1 ON u1.id = g.player1_id LEFT JOIN utenti u2 ON u2.id = g.player2_id
        WHERE $where ORDER BY g.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $types, $params);
    $list = [];
    foreach ($rows as $r) {
        $st = $r['state_json'] ? json_decode((string)$r['state_json'], true) : null;
        // Chi ha vinto: il giocatore 1 o 2 (il bot e' il 2), pari, o niente se non e' finita.
        $result = null;
        if ((string)$r['status'] === 'finished') {
            $side = is_array($st) ? ($st['winner'] ?? null) : null;
            $result = $r['winner_id'] !== null ? ((int)$r['winner_id'] === (int)$r['player1_id'] ? 'p1' : 'p2') : ($side === null ? 'draw' : ($side === 0 ? 'p1' : 'p2'));
        }
        $list[] = [
            'id' => (int)$r['id'], 'mode' => (string)$r['mode'], 'status' => (string)$r['status'], 'async' => (int)($r['is_async'] ?? 0) === 1,
            'p1' => ['id' => (int)$r['player1_id'], 'name' => (string)($r['p1'] ?? '?')],
            'p2' => $r['mode'] === 'bot' ? ['id' => 0, 'name' => 'Bot · ' . (string)($r['bot_level'] ?? '')] : ['id' => (int)($r['player2_id'] ?? 0), 'name' => (string)($r['p2'] ?? '')],
            'result' => $result,
            'reason' => (string)($r['end_reason'] ?? ''), 'turn' => is_array($st) ? (int)($st['turn'] ?? 0) : 0, 'moves' => (int)$r['version'],
            'created_at' => (string)$r['created_at'], 'finished_at' => (string)($r['finished_at'] ?? ''),
        ];
    }
    $counts = ['live' => 0, 'finished' => 0, 'cancelled' => 0];
    foreach (duel_rows($m, "SELECT status, COUNT(*) AS n FROM game_matches WHERE engine_version IS NOT NULL GROUP BY status") as $c) {
        $key = in_array($c['status'], DUEL_ADMIN_LIVE, true) ? 'live' : (string)$c['status'];
        if (isset($counts[$key])) {
            $counts[$key] += (int)$c['n'];
        }
    }
    // Abbandoni sospetti: chi esce subito (entro 4 mosse) da Ranked e Veloce, 3 volte o piu' in una settimana.
    $suspects = array_map(static fn(array $r): array => ['id' => (int)$r['loser_id'], 'username' => (string)($r['username'] ?? '?'), 'count' => (int)$r['n'], 'last' => (string)$r['last']],
        duel_rows($m, "SELECT g.loser_id, u.username, COUNT(*) AS n, MAX(g.finished_at) AS last FROM game_matches g LEFT JOIN utenti u ON u.id = g.loser_id
            WHERE g.engine_version IS NOT NULL AND g.status = 'finished' AND g.end_reason IN ('forfeit', 'timeout') AND g.mode IN ('ranked', 'casual')
            AND g.version <= 4 AND g.finished_at >= NOW() - INTERVAL 7 DAY AND g.loser_id IS NOT NULL
            GROUP BY g.loser_id, u.username HAVING COUNT(*) >= 3 ORDER BY n DESC LIMIT 20"));
    $settings = duel_settings($m, true);
    return [
        'list' => $list, 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / $per)), 'counts' => $counts, 'suspects' => $suspects,
        'switches' => ['public' => ($settings['public'] ?? '0') === '1', 'discord' => ($settings['discord'] ?? '1') === '1'],
    ];
}

/** Annulla una partita non finita: niente punti a nessuno, chi gioca vede «annullata dallo staff». */
function duel_admin_match_cancel(mysqli $m, int $id): array
{
    $row = duel_row($m, 'SELECT id, status, mode, player1_id, player2_id, challenge_id FROM game_matches WHERE id = ? AND engine_version IS NOT NULL', 'i', [$id]);
    if (!$row) {
        throw duel_user_error('Partita non trovata.', 'Match not found.', 404, 'NOT_FOUND');
    }
    if (!in_array((string)$row['status'], DUEL_ADMIN_LIVE, true)) {
        throw duel_user_error('La partita è già finita.', 'The match is already over.', 409, 'OVER');
    }
    duel_tx($m, static function () use ($m, $id, $row): void {
        if (duel_exec($m, "UPDATE game_matches SET status = 'cancelled', end_reason = 'admin', finished_at = NOW(), turn_deadline = NULL, updated_at = NOW()
            WHERE id = ? AND status IN ('waiting', 'team_select', 'active')", 'i', [$id]) !== 1) {
            throw duel_user_error('La partita è appena finita.', 'The match just ended.', 409, 'OVER');
        }
        if (!empty($row['challenge_id'])) {
            duel_exec($m, "UPDATE game_challenges SET status = 'cancelled', answered_at = NOW() WHERE id = ? AND status = 'pending'", 'i', [(int)$row['challenge_id']]);
        }
        duel_after_commit(static fn() => duel_stamp($id, ['st' => 'cancelled', 'dl' => 0]));
    });
    return $row;
}

/** Gli interruttori del pannello: duelli aperti a tutti, avvisi su Discord, stagioni. */
function duel_admin_switch(mysqli $m, string $key, bool $on): array
{
    if (!in_array($key, ['public', 'discord', 'seasons', 'tower'], true)) {
        throw duel_user_error('Interruttore sconosciuto.', 'Unknown switch.', 400, 'INVALID');
    }
    duel_setting_set($m, $key, $on ? '1' : '0');
    $out = ['key' => $key, 'on' => $on];
    // Stagioni accese: la Stagione 1 (o la prossima) parte subito, non alla prossima pulizia.
    if ($key === 'seasons' && $on && duel_phase6_ready($m)) {
        $out['tick'] = duel_season_tick($m);
    }
    return $out;
}

// ─────────────────────────────────────────────────────────────────────────
// Legami (fase 8: crearli e cambiarli dal pannello)
// ─────────────────────────────────────────────────────────────────────────

/** I bonus che il motore sa dare a un legame (engine.php, duel_apply_traits()), coi limiti del pannello. */
const DUEL_ADMIN_TRAIT_BONUS = [
    'hp' => [0, 50], 'atk' => [0, 50], 'def' => [0, 50], 'crit' => [0, 50], 'cdmg' => [0, 100], 'spd_pct' => [0, 50],
    'lifesteal' => [0, 50], 'dmg_pct' => [0, 50], 'heal_out_pct' => [0, 100], 'heal_in_pct' => [0, 100], 'prec' => [0, 50],
    'charge' => [0, 100], 'regen' => [0, 20], 'ps' => [0, 2],
];

/**
 * Tutti i legami per il pannello: nome, categorie, chi ne fa parte (per nome
 * e per categoria), i bonus a 2, 3... membri, attivo o no. Finche' il
 * pannello non ne ha mai cambiato uno sono quelli del codice.
 */
function duel_admin_traits(mysqli $m): array
{
    duel_use_current_values($m, true);
    $chars = duel_admin_characters($m);
    $saved = (int)(duel_row($m, 'SELECT COUNT(*) AS n FROM game_traits')['n'] ?? 0) > 0;
    $list = [];
    if ($saved) {
        $ids = [];
        foreach (duel_rows($m, 'SELECT character_id, trait_key FROM game_character_traits') as $r) {
            $ids[(string)$r['trait_key']][] = (int)$r['character_id'];
        }
        foreach (duel_rows($m, 'SELECT * FROM game_traits ORDER BY sort, trait_key') as $r) {
            $tiers = json_decode((string)$r['tiers_json'], true);
            $list[(string)$r['trait_key']] = ['it' => (string)$r['name_it'], 'en' => (string)$r['name_en'], 'active' => (int)$r['active'] === 1,
                'cat' => array_values(array_filter(array_map('trim', explode(',', (string)$r['categories'])))), 'ids' => $ids[(string)$r['trait_key']] ?? [],
                'tiers' => is_array($tiers) ? $tiers : []];
        }
    } else {
        foreach (duel_traits() as $key => $t) {
            $list[(string)$key] = $t + ['active' => true, 'ids' => [], 'cat' => []];
        }
    }

    $out = [];
    foreach ($list as $key => $t) {
        $cats = array_map(static fn($c) => mb_strtolower((string)$c, 'UTF-8'), $t['cat']);
        $members = [];
        foreach ($chars as $id => $c) {
            $byCat = in_array(mb_strtolower(trim((string)($c['categoria'] ?? '')), 'UTF-8'), $cats, true);
            if ($byCat || in_array($id, $t['ids'], true)) {
                $members[] = ['id' => $id, 'name' => duel_admin_name((string)$c['nome']), 'img' => duel_media($c['img_url'] ?? ''), 'by_category' => $byCat];
            }
        }
        $tiers = [];
        foreach ($t['tiers'] as $need => $bonus) {
            $tiers[] = ['need' => (int)$need, 'bonus' => (array)$bonus, 'text' => duel_text_trait_bonus((array)$bonus, 'it')];
        }
        usort($tiers, static fn($a, $b) => $a['need'] <=> $b['need']);
        $out[] = ['key' => $key, 'it' => $t['it'], 'en' => $t['en'], 'active' => $t['active'], 'categories' => $t['cat'], 'ids' => $t['ids'],
            'members' => $members, 'tiers' => $tiers];
    }
    $categories = array_values(array_unique(array_filter(array_map(static fn($c) => trim((string)($c['categoria'] ?? '')), $chars))));
    sort($categories);
    $names = array_map(static fn($c) => ['id' => (int)$c['id'], 'name' => duel_admin_name((string)$c['nome'])], array_values($chars));
    usort($names, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return ['saved' => $saved, 'traits' => $out, 'bonus' => array_keys(DUEL_ADMIN_TRAIT_BONUS), 'categories' => $categories, 'characters' => $names];
}

/** Un legame dal pannello (nuovo o cambiato). Torna la chiave. */
function duel_admin_trait_save(mysqli $m, array $in): string
{
    $err = [];
    $isNew = !empty($in['new']);
    $key = strtolower(trim((string)($in['key'] ?? '')));
    if ($key === '' && $isNew) {
        $key = trim((string)preg_replace('/[^a-z0-9]+/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)($in['it'] ?? '')) ?: '')), '_');
    }
    if (!preg_match('/^[a-z0-9_]{2,32}$/', $key)) {
        $err[] = 'Il codice del legame: da 2 a 32 lettere minuscole, numeri o _.';
    }
    $it = trim((string)($in['it'] ?? ''));
    $en = trim((string)($in['en'] ?? '')) ?: $it;
    if ($it === '' || mb_strlen($it) > 60 || mb_strlen($en) > 60) {
        $err[] = 'Il nome va da 1 a 60 caratteri.';
    }
    $cats = array_values(array_unique(array_filter(array_map(static fn($c) => mb_substr(trim((string)$c), 0, 40), (array)($in['categories'] ?? [])))));
    $chars = duel_admin_characters($m);
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($in['ids'] ?? [])), static fn($id) => isset($chars[$id]))));
    if (count($cats) > 10 || count($ids) > 60) {
        $err[] = 'Al massimo 10 categorie e 60 personaggi.';
    }
    if (!$cats && !$ids) {
        $err[] = 'Un legame ha almeno un personaggio o una categoria.';
    }
    $tiers = [];
    foreach ((array)($in['tiers'] ?? []) as $tier) {
        $need = (int)($tier['need'] ?? 0);
        if ($need < 2 || $need > 3) {
            $err[] = 'I bonus valgono con 2 o 3 membri in squadra.';
            continue;
        }
        $bonus = [];
        foreach ((array)($tier['bonus'] ?? []) as $k => $v) {
            if (!isset(DUEL_ADMIN_TRAIT_BONUS[$k]) || !is_numeric($v)) {
                $err[] = "Bonus sconosciuto: $k.";
                continue;
            }
            [$lo, $hi] = DUEL_ADMIN_TRAIT_BONUS[$k];
            if ((float)$v < $lo || (float)$v > $hi) {
                $err[] = "Bonus $k: da $lo a $hi.";
                continue;
            }
            if ((float)$v > 0) {
                $bonus[$k] = (float)$v == (int)$v ? (int)$v : (float)$v;
            }
        }
        if ($bonus) {
            $tiers[$need] = $bonus;
        }
    }
    if (!$tiers) {
        $err[] = 'Serve almeno un bonus.';
    }
    if ($err) {
        throw new DuelKitInvalid(array_values(array_unique($err)));
    }
    ksort($tiers);
    $active = !array_key_exists('active', $in) || !empty($in['active']);
    duel_tx($m, static function () use ($m, $isNew, $key, $it, $en, $cats, $ids, $tiers, $active): void {
        duel_admin_traits_seed($m);
        $exists = duel_row($m, 'SELECT sort FROM game_traits WHERE trait_key = ?', 's', [$key]);
        if ($isNew && $exists) {
            throw new DuelKitInvalid(['Esiste già un legame con questo codice.']);
        }
        if (!$isNew && !$exists) {
            throw new DuelKitInvalid(['Legame non trovato.']);
        }
        $sort = $exists ? (int)$exists['sort'] : 10 + (int)(duel_row($m, 'SELECT MAX(sort) AS s FROM game_traits')['s'] ?? 0);
        duel_exec($m, 'INSERT INTO game_traits (trait_key, name_it, name_en, categories, tiers_json, active, sort) VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE name_it = VALUES(name_it), name_en = VALUES(name_en), categories = VALUES(categories), tiers_json = VALUES(tiers_json), active = VALUES(active)',
            'sssssii', [$key, $it, $en, implode(',', $cats), json_encode($tiers, JSON_UNESCAPED_UNICODE), $active ? 1 : 0, $sort]);
        duel_exec($m, 'DELETE FROM game_character_traits WHERE trait_key = ?', 's', [$key]);
        foreach ($ids as $id) {
            duel_exec($m, 'INSERT IGNORE INTO game_character_traits (character_id, trait_key) VALUES (?, ?)', 'is', [$id, $key]);
        }
    });
    return $key;
}

/** Toglie un legame (e chi ne faceva parte per nome). */
function duel_admin_trait_delete(mysqli $m, string $key): bool
{
    return duel_tx($m, static function () use ($m, $key): bool {
        duel_admin_traits_seed($m);
        duel_exec($m, 'DELETE FROM game_character_traits WHERE trait_key = ?', 's', [$key]);
        return duel_exec($m, 'DELETE FROM game_traits WHERE trait_key = ?', 's', [$key]) === 1;
    });
}
