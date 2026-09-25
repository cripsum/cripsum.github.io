<?php

/**
 * Cosa c'e' nel database, per il gacha.
 *
 * La migration `2026_09_25_gacha_banner.sql` si applica a mano, e i file
 * possono arrivare prima di lei: in quel caso tutto deve continuare a
 * funzionare come prima (banner dalla vecchia `banner_eventi`, un rate-up
 * a banner, pity nelle colonne di `utenti`). Ogni funzione nuova chiede qui
 * se il suo pezzo di schema esiste e, se manca, si spegne in silenzio.
 */

require_once __DIR__ . '/config.php';

/** Colonne di una tabella (vuoto se la tabella non c'e'). Una query a tabella. */
function gacha_cols(mysqli $mysqli, string $table): array
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return $cache[$table] = [];
    }

    $cols = [];
    try {
        $res = $mysqli->query('SHOW COLUMNS FROM `' . $table . '`');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cols[] = (string)$row['Field'];
            }
            $res->free();
        }
    } catch (Throwable $e) {
        $cols = [];
    }

    return $cache[$table] = $cols;
}

function gacha_has_col(mysqli $mysqli, string $table, string $column): bool
{
    return in_array($column, gacha_cols($mysqli, $table), true);
}

function gacha_has_table(mysqli $mysqli, string $table): bool
{
    return gacha_cols($mysqli, $table) !== [];
}

/**
 * Riassunto dello schema. `v2` vuol dire migration applicata: tabella
 * `gacha_banner` con le colonne nuove.
 */
function gacha_schema(mysqli $mysqli): array
{
    static $schema = null;
    if ($schema !== null) {
        return $schema;
    }

    $v2 = gacha_has_col($mysqli, 'gacha_banner', 'pity_gruppo');
    $bannerTable = $v2 ? 'gacha_banner' : (gacha_has_table($mysqli, 'banner_eventi') ? 'banner_eventi' : null);

    return $schema = [
        'v2' => $v2,
        'banner_table' => $bannerTable,
        'banner_personaggi' => $v2 && gacha_has_table($mysqli, 'gacha_banner_personaggi'),
        'banner_rarita' => $v2 && gacha_has_table($mysqli, 'gacha_banner_rarita'),
        'pity' => gacha_has_table($mysqli, 'gacha_pity'),
        'destino' => gacha_has_table($mysqli, 'gacha_destino'),
        'categorie' => gacha_has_table($mysqli, 'personaggi_categorie'),
        'limitato' => gacha_has_col($mysqli, 'personaggi', 'limitato'),
        'catalogo' => gacha_has_col($mysqli, 'personaggi', 'catalogo'),
        'aggiunto_il' => gacha_has_col($mysqli, 'personaggi', 'aggiunto_il'),
        'ruolo' => gacha_has_col($mysqli, 'personaggi', 'ruolo'),
        'inv_livello' => gacha_has_col($mysqli, 'utenti_personaggi', 'livello'),
        'inv_visto' => gacha_has_col($mysqli, 'utenti_personaggi', 'visto'),
        'inv_preferito' => gacha_has_col($mysqli, 'utenti_personaggi', 'preferito'),
        'inv_ultima' => gacha_has_col($mysqli, 'utenti_personaggi', 'ultima_copia_il'),
        'inv_usate' => gacha_has_col($mysqli, 'utenti_personaggi', 'copie_usate'),
        'history' => gacha_has_table($mysqli, 'gacha_pull_history'),
        'history_v2' => gacha_has_col($mysqli, 'gacha_pull_history', 'gratuita'),
        'wishlist' => gacha_has_table($mysqli, 'utenti_wishlist'),
        'collezioni' => gacha_has_table($mysqli, 'utenti_collezioni_premi') && gacha_has_table($mysqli, 'personaggi_categorie'),
        'frammenti' => gacha_has_col($mysqli, 'utenti', 'frammenti') && gacha_has_table($mysqli, 'gacha_frammenti_acquisti'),
        'premium' => gacha_has_col($mysqli, 'utenti', 'is_premium'),
        'shards' => gacha_has_col($mysqli, 'utenti', 'godoshards_balance'),
        'visibility' => gacha_has_col($mysqli, 'utenti', 'profile_visibility'),
    ];
}

/** Il personaggio e' limitato? Dal flag se c'e', altrimenti dalla vecchia categoria. */
function gacha_is_limited(array $row): bool
{
    if (array_key_exists('limitato', $row) && $row['limitato'] !== null) {
        return (int)$row['limitato'] === 1;
    }
    $cat = mb_strtolower(trim((string)($row['categoria'] ?? '')), 'UTF-8');
    return $cat === 'limited' || $cat === 'limitato';
}

/**
 * Visibilita' nel catalogo di chi non possiede il personaggio. Senza la
 * colonna si comporta come l'inventario di prima: tutto "???".
 */
function gacha_catalog_mode(array $row): string
{
    $mode = (string)($row['catalogo'] ?? '');
    return in_array($mode, ['visibile', 'segreto', 'nascosto'], true) ? $mode : 'segreto';
}

/** Percorso di un file media come lo usano le pagine (/img/..., /vid/...). */
function gacha_media(?string $path, string $base = '/img/'): ?string
{
    $path = trim((string)$path);
    if ($path === '') {
        return null;
    }
    if (preg_match('~^https?://~i', $path) || str_starts_with($path, '/')) {
        return $path;
    }
    $segments = array_map('rawurlencode', explode('/', str_replace('\\', '/', $path)));
    return rtrim($base, '/') . '/' . implode('/', $segments);
}
