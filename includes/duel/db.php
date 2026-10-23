<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — il database, la parte comune.
 *
 * Cosa c'e' (la migrazione migrations/2026_10_19_duelli2.sql e' stata
 * applicata?), gli interruttori di game_settings (duelli aperti a tutti o
 * solo allo staff, avvisi su Discord), i personaggi e chi li possiede, i
 * valori cambiati dal pannello. Lo schema si legge dalla cache del tempo
 * reale (includes/realtime.php): nessuna query in piu' a ogni richiesta.
 *
 * Il motore (engine.php) resta senza database: questo file gli passa i
 * valori del pannello con duel_overrides().
 */

require_once __DIR__ . '/kits.php';
require_once __DIR__ . '/../realtime.php';

/** Errore da mostrare a chi gioca, con lo stato HTTP e un codice per il browser. */
final class DuelUserError extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly string $key = 'ERROR',
        public readonly array $extra = []
    ) {
        parent::__construct($message);
    }
}

function duel_user_error(string $it, string $en, int $status = 400, string $key = 'ERROR', array $extra = []): DuelUserError
{
    return new DuelUserError(rt_t($it, $en), $status, $key, $extra);
}

// ─────────────────────────────────────────────────────────────────────────
// Query
// ─────────────────────────────────────────────────────────────────────────
//
// Il sito spegne le eccezioni di mysqli (MYSQLI_REPORT_OFF): qui ogni query
// che non riesce diventa un'eccezione, cosi' una transazione a meta' torna
// sempre indietro invece di andare avanti con dati sbagliati.

function duel_q(mysqli $m, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $st = $m->prepare($sql);
    if (!$st) {
        throw new RuntimeException('prepare: ' . $m->error . ' | ' . substr($sql, 0, 120));
    }
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    if (!$st->execute()) {
        $error = $st->error;
        $st->close();
        throw new RuntimeException('execute: ' . $error . ' | ' . substr($sql, 0, 120));
    }
    return $st;
}

/** Righe di una SELECT. */
function duel_rows(mysqli $m, string $sql, string $types = '', array $params = []): array
{
    $st = duel_q($m, $sql, $types, $params);
    $res = $st->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $st->close();
    return $rows;
}

function duel_row(mysqli $m, string $sql, string $types = '', array $params = []): ?array
{
    return duel_rows($m, $sql, $types, $params)[0] ?? null;
}

/** INSERT, UPDATE, DELETE: righe toccate. */
function duel_exec(mysqli $m, string $sql, string $types = '', array $params = []): int
{
    $st = duel_q($m, $sql, $types, $params);
    $n = $st->affected_rows;
    $st->close();
    return (int)$n;
}

/**
 * Una transazione. Quello che va fatto solo se va a buon fine (timbri del
 * tempo reale, avvisi, agganci a missioni e achievement) si mette in coda con
 * duel_after_commit() e parte dopo il commit.
 */
function duel_tx(mysqli $m, callable $fn): mixed
{
    $m->begin_transaction();
    try {
        $result = $fn();
        if (!$m->commit()) {
            throw new RuntimeException('commit: ' . $m->error);
        }
    } catch (Throwable $e) {
        $m->rollback();
        duel_after_commit(null, true);
        throw $e;
    }
    duel_after_commit(null);
    return $result;
}

/** In coda per dopo il commit; senza funzione esegue la coda (o la butta). */
function duel_after_commit(?callable $fn, bool $drop = false): void
{
    static $queue = [];
    if ($fn !== null) {
        $queue[] = $fn;
        return;
    }
    $run = $queue;
    $queue = [];
    if ($drop) {
        return;
    }
    foreach ($run as $job) {
        try {
            $job();
        } catch (Throwable $e) {
            error_log('[duel] dopo il commit: ' . $e->getMessage());
        }
    }
}

/** La migrazione dei Duelli 2.0 e' stata applicata. */
function duel_db_ready(mysqli $m): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = rt_has_col($m, 'game_matches', 'engine_version')
            && rt_has_col($m, 'game_matches', 'state_json')
            && rt_has_table($m, 'game_match_events')
            && rt_has_table($m, 'game_teams')
            && rt_has_table($m, 'game_queue')
            && rt_has_table($m, 'game_settings');
    }
    return $ready;
}

/** Gli interruttori di game_settings, letti una volta per richiesta. */
function duel_settings(mysqli $m, bool $fresh = false): array
{
    static $settings = null;
    if ($settings !== null && !$fresh) {
        return $settings;
    }
    $settings = ['public' => '0', 'discord' => '1', 'cleanup_at' => '0'];
    if (!rt_has_table($m, 'game_settings')) {
        return $settings;
    }
    $res = $m->query('SELECT k, v FROM game_settings');
    while ($res && ($row = $res->fetch_assoc())) {
        $settings[(string)$row['k']] = (string)$row['v'];
    }
    return $settings;
}

function duel_setting_set(mysqli $m, string $key, string $value): void
{
    duel_exec($m, 'INSERT INTO game_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', 'ss', [$key, $value]);
    duel_settings($m, true);
}

function duel_is_staff(): bool
{
    return in_array((string)($_SESSION['ruolo'] ?? ''), ['admin', 'owner'], true);
}

/** Finche' i duelli non sono aperti a tutti li usa solo lo staff. */
function duel_open_for(mysqli $m): bool
{
    return duel_settings($m)['public'] === '1' || duel_is_staff();
}

// ─────────────────────────────────────────────────────────────────────────
// Personaggi
// ─────────────────────────────────────────────────────────────────────────

/** Righe di `personaggi` per id, con i campi che servono al duello. */
function duel_db_characters(mysqli $m, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    if (!$ids) {
        return [];
    }
    $fields = ['p.id', 'p.nome', 'p.ruolo'];
    $fields[] = rt_has_col($m, 'personaggi', 'rarità') ? 'p.`rarità` AS rarita' : "'comune' AS rarita";
    $fields[] = rt_has_col($m, 'personaggi', 'img_url') ? 'p.img_url' : "'' AS img_url";
    $fields[] = rt_has_col($m, 'personaggi', 'audio_url') ? 'p.audio_url' : "'' AS audio_url";
    $fields[] = rt_has_col($m, 'personaggi', 'categoria') ? 'p.categoria' : "'' AS categoria";
    $fields[] = rt_has_col($m, 'personaggi', 'limitato') ? 'p.limitato' : '0 AS limitato';

    $marks = implode(',', array_fill(0, count($ids), '?'));
    $out = [];
    foreach (duel_rows($m, 'SELECT ' . implode(', ', $fields) . " FROM personaggi p WHERE p.id IN ($marks)", str_repeat('i', count($ids)), $ids) as $row) {
        $row['id'] = (int)$row['id'];
        $row['limitato'] = (int)($row['limitato'] ?? 0);
        $out[$row['id']] = $row;
    }
    return $out;
}

/**
 * L'immagine di un personaggio come indirizzo: nel database c'e' a volte un
 * percorso, a volte solo il nome del file in /img/ (come gacha_media()).
 */
function duel_media(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $path) || str_starts_with($path, '/')) {
        return $path;
    }
    return '/img/' . implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $path))));
}

/**
 * La musica del pull di un personaggio come indirizzo (come gacha_media con
 * /audio/): l'arena la suona nell'ultimate di chi non ha una musica sua.
 */
function duel_audio(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $path) || str_starts_with($path, '/')) {
        return $path;
    }
    return '/audio/' . implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $path))));
}

/** I personaggi posseduti tra quelli chiesti: [id => livello]. */
function duel_db_owned(mysqli $m, int $userId, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    if (!$ids) {
        return [];
    }
    $qty = rt_has_col($m, 'utenti_personaggi', 'quantità') ? 'up.`quantità`' : '1';
    $level = rt_has_col($m, 'utenti_personaggi', 'livello') ? 'up.livello' : '1';
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $rows = duel_rows($m, "SELECT up.personaggio_id AS id, $level AS livello FROM utenti_personaggi up
        WHERE up.utente_id = ? AND up.personaggio_id IN ($marks) AND $qty > 0", 'i' . str_repeat('i', count($ids)), [$userId, ...$ids]);
    $out = [];
    foreach ($rows as $row) {
        $out[(int)$row['id']] = max(1, min(duel_rules()['level_max'], (int)$row['livello']));
    }
    return $out;
}

/** Tutta la collezione di un giocatore: [id => livello]. */
function duel_db_collection(mysqli $m, int $userId): array
{
    $qty = rt_has_col($m, 'utenti_personaggi', 'quantità') ? 'up.`quantità`' : '1';
    $level = rt_has_col($m, 'utenti_personaggi', 'livello') ? 'up.livello' : '1';
    $out = [];
    foreach (duel_rows($m, "SELECT up.personaggio_id AS id, $level AS livello FROM utenti_personaggi up WHERE up.utente_id = ? AND $qty > 0", 'i', [$userId]) as $row) {
        $out[(int)$row['id']] = max(1, min(duel_rules()['level_max'], (int)$row['livello']));
    }
    return $out;
}

// ─────────────────────────────────────────────────────────────────────────
// Valori del pannello
// ─────────────────────────────────────────────────────────────────────────

/**
 * Quello che il pannello ha cambiato, nel formato di duel_overrides():
 * game_catalog_values ('rule.turn_limit', 'status.veleno.v',
 * 'terrain.notte.crit', 'terrain.santuario.block.veleno'...), game_kits,
 * game_traits con game_character_traits. Tabelle vuote o assenti = niente.
 */
function duel_db_overrides(mysqli $m, bool $fresh = false): array
{
    static $loaded = null;
    if ($loaded !== null && !$fresh) {
        return $loaded;
    }
    $out = ['rules' => [], 'status' => [], 'terrain' => [], 'kits' => [], 'traits' => null];

    if (rt_has_table($m, 'game_catalog_values')) {
        $res = $m->query('SELECT k, v FROM game_catalog_values');
        while ($res && ($row = $res->fetch_assoc())) {
            $parts = explode('.', (string)$row['k']);
            if ($parts[0] === 'rule' && count($parts) === 2) {
                $out['rules'][$parts[1]] = (float)$row['v'];
            } elseif ($parts[0] === 'status' && count($parts) === 3) {
                $out['status'][$parts[1]][$parts[2]] = (float)$row['v'];
            } elseif ($parts[0] === 'terrain' && count($parts) === 3) {
                $out['terrain'][$parts[1]][$parts[2]] = (float)$row['v'];
            } elseif ($parts[0] === 'terrain' && count($parts) === 4 && $parts[2] === 'block') {
                $out['terrain'][$parts[1]]['block'][$parts[3]] = (int)$row['v'];
            }
        }
    }

    if (rt_has_table($m, 'game_kits')) {
        $res = $m->query('SELECT character_id, kit_json FROM game_kits');
        while ($res && ($row = $res->fetch_assoc())) {
            $kit = json_decode((string)$row['kit_json'], true);
            if (is_array($kit)) {
                $out['kits'][(int)$row['character_id']] = $kit;
            }
        }
    }

    if (rt_has_table($m, 'game_traits')) {
        // Basta una riga (anche spenta) perche' valgano quelli del database: spenti tutti = nessun legame.
        $traits = [];
        $seeded = false;
        $res = $m->query('SELECT trait_key, name_it, name_en, categories, tiers_json, active FROM game_traits ORDER BY sort, trait_key');
        while ($res && ($row = $res->fetch_assoc())) {
            $seeded = true;
            $tiers = json_decode((string)$row['tiers_json'], true);
            if ((int)$row['active'] !== 1 || !is_array($tiers)) {
                continue;
            }
            $traits[(string)$row['trait_key']] = [
                'it' => (string)$row['name_it'],
                'en' => (string)$row['name_en'],
                'cat' => array_values(array_filter(array_map('trim', explode(',', (string)$row['categories'])))),
                'ids' => [],
                'tiers' => $tiers,
            ];
        }
        if ($seeded) {
            if ($traits && rt_has_table($m, 'game_character_traits')) {
                $res = $m->query('SELECT character_id, trait_key FROM game_character_traits');
                while ($res && ($row = $res->fetch_assoc())) {
                    if (isset($traits[(string)$row['trait_key']])) {
                        $traits[(string)$row['trait_key']]['ids'][] = (int)$row['character_id'];
                    }
                }
            }
            $out['traits'] = $traits;
        }
    }

    return $loaded = $out;
}

/**
 * Mette in uso i valori del pannello di adesso (partite nuove, testi dei
 * kit). $fresh: rilette dal database (il pannello, dopo aver salvato).
 */
function duel_use_current_values(mysqli $m, bool $fresh = false): array
{
    $over = duel_db_overrides($m, $fresh);
    duel_overrides($over);
    return $over;
}

/** Mette in uso le regole con cui e' cominciata una partita. */
function duel_use_match_values(array $rules): void
{
    duel_overrides([
        'rules' => $rules['values']['rules'] ?? [],
        'status' => $rules['values']['status'] ?? [],
        'terrain' => $rules['values']['terrain'] ?? [],
    ]);
}
