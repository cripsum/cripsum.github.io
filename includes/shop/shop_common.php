<?php

/**
 * Pezzi comuni a Negozio, Merch, Download e al loro pannello admin.
 *
 * Qui non c'e' niente che riguardi i soldi veri: lo Shop Gacha ha il suo
 * gacha_catalog.php e non passa di qui.
 */

require_once __DIR__ . '/../security_helpers.php';

function shop_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Il campo nella lingua chiesta, con ritorno all'italiano quando la
 * traduzione non e' stata scritta: meglio un prodotto in italiano che una
 * card vuota.
 */
function shop_pick(array $row, string $field, string $lang): string
{
    if ($lang === 'en') {
        $translated = trim((string)($row[$field . '_en'] ?? ''));
        if ($translated !== '') {
            return $translated;
        }
    }

    return trim((string)($row[$field] ?? ''));
}

/**
 * Vero se tutte le tabelle ci sono. Serve a far funzionare le pagine anche
 * prima che la migrazione venga applicata, invece di lanciare query rotte.
 */
function shop_tables_ready(?mysqli $mysqli, array $tables): bool
{
    if (!$mysqli instanceof mysqli) {
        return false;
    }

    foreach ($tables as $table) {
        if (!auth_table_exists($mysqli, $table)) {
            return false;
        }
    }

    return true;
}

/**
 * Le query delle pagine pubbliche: se qualcosa va storto si torna a una
 * lista vuota e la pagina mostra il suo stato vuoto, non un errore a meta'.
 */
function shop_fetch_all(mysqli $mysqli, string $sql, string $types = '', array $params = []): array
{
    try {
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    } catch (Throwable $e) {
        error_log('[shop] ' . $e->getMessage());
        return [];
    }
}

function shop_fetch_one(mysqli $mysqli, string $sql, string $types = '', array $params = []): ?array
{
    $rows = shop_fetch_all($mysqli, $sql, $types, $params);
    return $rows[0] ?? null;
}

/**
 * Prezzo finto in euro, scritto come lo scrive chi legge.
 */
function shop_price(float $value, string $lang): string
{
    if ($lang === 'en') {
        return '€' . number_format($value, 2, '.', ',');
    }

    return number_format($value, 2, ',', '.') . ' €';
}

/**
 * Percorso di un'immagine sempre assoluto.
 *
 * Nel database possono finire "/img/foto.jpg", "img/foto.jpg", "foto.jpg"
 * (il nome restituito dal caricamento dell'admin) o un indirizzo https: la
 * pagina riceve sempre qualcosa che funziona da qualunque URL, anche da
 * /it/merch/poppy dove un percorso relativo andrebbe a vuoto.
 */
function shop_asset_url(?string $path): string
{
    $path = trim((string)$path);

    if ($path === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    if (str_starts_with($path, '../')) {
        $path = '/' . ltrim(substr($path, 3), '/');
    }

    if (str_starts_with($path, '/')) {
        return $path;
    }

    if (str_starts_with($path, 'img/')) {
        return '/' . $path;
    }

    return '/img/' . ltrim($path, '/');
}

function shop_hex(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
}

function shop_hex_rgb(string $hex): string
{
    $hex = ltrim($hex, '#');
    return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
}

/**
 * Luminanza percepita da 0 a 1: decide se il testo sopra l'accento va scuro
 * (il giallo del merch di simonetussi) o chiaro (il viola del Negozio).
 */
function shop_hex_luminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
}

/**
 * Le variabili CSS del tema di una vetrina, pronte per un attributo style.
 *
 * Si scelgono tre colori nel pannello; tutto il resto (testo, grigi, bordi,
 * testo sui bottoni) nasce da quelli, cosi' una collezione nuova non puo'
 * uscire illeggibile.
 */
function shop_theme_style(array $vetrina): string
{
    $accent = shop_hex($vetrina['colore_accento'] ?? null, '#6d5dfc');
    $bg = shop_hex($vetrina['colore_sfondo'] ?? null, '#05070d');
    $bg2 = shop_hex($vetrina['colore_sfondo_2'] ?? null, '#0b1020');

    $vars = [
        '--shop-accent' => $accent,
        '--shop-accent-rgb' => shop_hex_rgb($accent),
        '--shop-on-accent' => shop_hex_luminance($accent) > 0.62 ? '#161000' : '#ffffff',
        '--shop-bg' => $bg,
        '--shop-bg-2' => $bg2,
    ];

    $css = '';
    foreach ($vars as $name => $value) {
        $css .= $name . ': ' . $value . '; ';
    }

    return trim($css);
}

/**
 * Testo semplice con gli indirizzi https resi cliccabili e gli a capo
 * rispettati. Niente HTML dal database: quello che scrive l'admin viene
 * sempre prima neutralizzato, poi arricchito.
 */
function shop_linkify(string $text): string
{
    $safe = shop_h($text);

    $linked = preg_replace_callback(
        '~https?://[^\s<>"\')]+~i',
        static function (array $match): string {
            // Il punto o la virgola a fine frase non fanno parte del link.
            $url = rtrim($match[0], '.,;:!?');
            $tail = substr($match[0], strlen($url));
            $label = preg_replace('~^https?://(www\.)?~i', '', $url);
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . rtrim((string)$label, '/') . '</a>' . $tail;
        },
        $safe
    );

    return nl2br($linked ?? $safe, false);
}

function shop_is_recent(?string $createdAt, int $days): bool
{
    if (!$createdAt) {
        return false;
    }

    $time = strtotime($createdAt);
    return $time !== false && $time >= time() - $days * 86400;
}

/**
 * Link accettabile per un bottone o un download esterno: un percorso interno
 * del sito o un indirizzo http(s). `javascript:`, `data:` e `//host` no.
 */
function shop_valid_link(string $value): bool
{
    $value = trim($value);

    if ($value === '' || strlen($value) > 500) {
        return false;
    }

    if (str_starts_with($value, '/')) {
        return !str_starts_with($value, '//');
    }

    return (bool)preg_match('~^https?://[^\s]+$~i', $value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
}

/**
 * I link interni si scrivono una volta sola, in italiano, e vengono girati
 * alla lingua di chi guarda.
 */
function shop_localize_link(string $link, string $lang): string
{
    if ($lang === 'it' || !str_starts_with($link, '/it/')) {
        return $link;
    }

    return '/' . $lang . substr($link, 3);
}

/**
 * Meta tag letto da richpresence.js: la presenza Discord di una pagina
 * costruita dal database ("Acquistando il merch di Poppy") senza doverla
 * scrivere a mano nella mappa degli URL.
 */
function shop_presence_meta(string $title, string $state): string
{
    return '<meta name="cripsum:presence-title" content="' . shop_h($title) . '">' . "\n"
        . '<meta name="cripsum:presence-state" content="' . shop_h($state) . '">';
}

/**
 * Coppie etichetta/valore salvate in JSON con le due lingue dentro:
 *   {"it": [["Materiale", "Cotone"]], "en": [["Material", "Cotton"]]}
 * Una lingua vuota ricade sull'altra. Esce [['label' => ..., 'value' => ...]].
 */
function shop_localized_pairs(?string $json, string $lang): array
{
    $data = json_decode((string)$json, true);
    if (!is_array($data)) {
        return [];
    }

    $list = $data[$lang] ?? [];
    if (!is_array($list) || !$list) {
        $list = $data['it'] ?? [];
    }

    $pairs = [];
    foreach (is_array($list) ? $list : [] as $pair) {
        // [etichetta, valore]; array_values regge anche un JSON scritto a mano con le chiavi.
        $pair = is_array($pair) ? array_values($pair) : [];
        if (count($pair) >= 2 && is_scalar($pair[0]) && is_scalar($pair[1]) && trim((string)$pair[0]) !== '' && trim((string)$pair[1]) !== '') {
            $pairs[] = ['label' => trim((string)$pair[0]), 'value' => trim((string)$pair[1])];
        }
    }

    return $pairs;
}

function shop_is_staff(): bool
{
    $role = $_SESSION['ruolo'] ?? '';
    return $role === 'admin' || $role === 'owner';
}
