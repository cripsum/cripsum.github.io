<?php

declare(strict_types=1);

/**
 * La collezione e i potenziamenti dopo il vecchio motore dei duelli (fase 8
 * dei Duelli 2.0).
 *
 * Il vecchio motore dei duelli (game_helpers) serviva ancora alla collezione
 * (inventario, schede dei personaggi), ai due endpoint dei potenziamenti
 * (api/game/upgrade_*.php) e alla scheda «Duelli» di Personaggi nel
 * pannello. Qui ci sono solo quelle funzioni, con la stessa firma:
 * - i costi in copie dei potenziamenti sono quelli di sempre;
 * - statistiche e abilita' sono quelle dei Duelli 2.0 (includes/duel/), con
 *   i kit e i valori scritti nel pannello.
 */

require_once __DIR__ . '/../stats_tracker.php';
require_once __DIR__ . '/../mission_tracker.php';
require_once __DIR__ . '/../duel/text.php';
require_once __DIR__ . '/../duel/db.php';

// ── Risposte degli endpoint dei potenziamenti ───────────────────────────

function gd_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function gd_ok(array $data = []): void
{
    gd_json(['success' => true] + $data);
}

function gd_fail(string $msg, int $status = 400): void
{
    gd_json(['success' => false, 'message' => $msg], $status);
}

function gd_input(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    return is_array($json) ? $json : ($_POST ?: $_GET ?: []);
}

/**
 * Token CSRF per le azioni che cambiano l'inventario (potenziamenti): header
 * X-CSRF-Token o campo csrf_token del corpo, come nel resto del sito.
 */
function gd_require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (gd_input()['csrf_token'] ?? null);
    if (!function_exists('csrf_validate') || !csrf_validate(is_string($token) ? $token : null)) {
        gd_fail('Sessione scaduta. Ricarica la pagina.', 419);
    }
}

function gd_user_id(): int
{
    foreach (['user_id', 'utente_id', 'id'] as $k) {
        if (!empty($_SESSION[$k])) {
            return (int)$_SESSION[$k];
        }
    }
    return !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
}

function gd_require_login(): int
{
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        gd_fail('Devi essere loggato.', 401);
    }
    $id = gd_user_id();
    if ($id <= 0) {
        gd_fail('Sessione non valida.', 401);
    }
    return $id;
}

function gd_has_col(mysqli $m, string $table, string $col): bool
{
    return rt_has_col($m, $table, $col);
}

// ── Potenziamenti: costi in copie (quelli di sempre) ────────────────────

/**
 * Cosa passare come "categoria" alle funzioni di potenziamento, che trattano
 * da limitato chi ha "limited" nella categoria. Con la migration del gacha
 * il limitato e' un flag (personaggi.limitato) e la categoria e' libera: si
 * passa "limited" se il flag c'e', altrimenti niente. Senza la colonna vale
 * la categoria di sempre.
 */
function gd_limited_marker(array $ch): string
{
    if (array_key_exists('limitato', $ch) && $ch['limitato'] !== null) {
        return (int)$ch['limitato'] === 1 ? 'limited' : '';
    }
    return (string)($ch['categoria'] ?? '');
}

/** Copie per passare dal livello $current_level al successivo. */
function gd_get_upgrade_requirement(string $rarity, int $current_level, string $category = ''): int
{
    $lvl = max(1, min(5, $current_level));
    $rKey = strtolower(trim($rarity));
    $cKey = strtolower(trim($category));

    if (str_contains($rKey, 'one')) {
        return 1;
    }
    if (str_contains($rKey, 'limited') || str_contains($rKey, 'limitato') || str_contains($cKey, 'limited') || str_contains($cKey, 'limitato')) {
        return [1 => 1, 2 => 2, 3 => 2, 4 => 3, 5 => 3][$lvl];
    }
    if (str_contains($rKey, 'secret') || str_contains($rKey, 'segreto')) {
        return [1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6][$lvl];
    }
    if (str_contains($rKey, 'leggendario') || str_contains($rKey, 'legendary')) {
        return [1 => 5, 2 => 8, 3 => 12, 4 => 16, 5 => 20][$lvl];
    }
    if (str_contains($rKey, 'speciale') || str_contains($rKey, 'special')) {
        return [1 => 6, 2 => 10, 3 => 15, 4 => 20, 5 => 25][$lvl];
    }
    if (str_contains($rKey, 'epico') || str_contains($rKey, 'epic')) {
        return [1 => 10, 2 => 15, 3 => 20, 4 => 25, 5 => 30][$lvl];
    }
    if (str_contains($rKey, 'raro') || str_contains($rKey, 'rare')) {
        return [1 => 15, 2 => 25, 3 => 35, 4 => 45, 5 => 60][$lvl];
    }
    return [1 => 25, 2 => 50, 3 => 75, 4 => 100, 5 => 150][$lvl];
}

/** Quanto crescono HP, ATK e DEF a un livello: nei Duelli 2.0 e' uguale per tutti (level_step). */
function gd_get_stat_multiplier(string $rarity, int $level, string $category = ''): float
{
    $lvl = max(1, min(duel_rules()['level_max'], $level));
    return 1.0 + ($lvl - 1) * duel_rules()['level_step'] / 100;
}

// ── Statistiche e abilita' (Duelli 2.0) ─────────────────────────────────

/**
 * Mette in uso i kit e i valori scritti nel pannello: le schede devono dire
 * quello che succede in partita. Una volta per richiesta.
 */
function gd_use_duel_values(mysqli $m): void
{
    static $done = false;
    if (!$done && duel_db_ready($m)) {
        duel_use_current_values($m);
    }
    $done = true;
}

/** Un personaggio del database con i campi del duello. */
function gd_character(mysqli $m, int $pid): ?array
{
    return duel_db_characters($m, [$pid])[$pid] ?? null;
}

function gd_stats(mysqli $m, int $pid, int $level = 1): array
{
    gd_use_duel_values($m);
    return gd_stats_build($pid, gd_character($m, $pid) ?? [], null, $level, rt_lang());
}

/**
 * Le statistiche e il kit di un personaggio a un livello, dai dati gia'
 * letti (nome, rarita, ruolo, categoria/limitato): l'inventario la chiama
 * per tutti i personaggi dopo averli letti in blocco. Le chiavi sono quelle
 * di sempre; $cardRow (la vecchia game_card_stats) non conta piu': i numeri
 * stanno nei kit.
 *
 * L'abilita' costa PS (special_cost) e ha una ricarica in turni
 * (special_cooldown, 0 = nessuna); special_meta e' la riga gia' scritta.
 */
function gd_stats_build(int $pid, array $ch, ?array $cardRow, int $level = 1, string $lang = 'it'): array
{
    static $texts = [];
    $char = [
        'id' => $pid, 'nome' => (string)($ch['nome'] ?? 'Personaggio'), 'rarita' => (string)($ch['rarita'] ?? $ch['rarità'] ?? 'comune'),
        'ruolo' => (string)($ch['ruolo'] ?? 'DPS'), 'categoria' => (string)($ch['categoria'] ?? ''), 'limitato' => (int)($ch['limitato'] ?? 0),
    ];
    $kit = duel_character_kit($char, $level);
    $key = $pid . ':' . $lang . ':' . DuelOverrides::$gen;
    $t = $texts[$key] ??= duel_kit_text($kit, $lang);
    $s = $kit['stats'];

    return [
        'hp' => (int)$s['hp'], 'attack' => (int)$s['atk'], 'defense' => (int)$s['def'], 'speed' => (int)$s['spd'],
        'crit_rate' => (int)round((float)$s['crit']), 'crit_dmg' => (int)round((float)$s['cdmg']),
        'max_energy' => (int)duel_rules()['ps_max'],
        'role' => (string)$kit['role'],
        'special_name' => (string)($t['skill']['name'] ?? ''), 'special_desc' => (string)($t['skill']['text'] ?? ''),
        'special_cost' => (int)($kit['skill']['cost'] ?? 1), 'special_cooldown' => (int)($kit['skill']['cd'] ?? 0),
        'special_meta' => (string)($t['skill']['meta'] ?? ''),
        'passive_name' => (string)($t['passive']['name'] ?? ''), 'passive_desc' => (string)($t['passive']['text'] ?? ''),
        'ultimate_name' => isset($t['ultimate']) ? (string)$t['ultimate']['name'] : null,
        'ultimate_desc' => isset($t['ultimate']) ? (string)$t['ultimate']['text'] : null,
        'attack_name' => (string)($t['attack']['name'] ?? ''), 'attack_desc' => (string)($t['attack']['text'] ?? ''),
    ];
}
