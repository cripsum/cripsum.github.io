<?php

/**
 * Catalogo dei download.
 *
 * Un download e' un file che sta sul sito oppure un link esterno. In
 * entrambi i casi la pagina non punta mai direttamente al file: passa da
 * /api/shop/download.php, che conta il download, fa avanzare la missione e
 * poi reindirizza o serve il file.
 */

require_once __DIR__ . '/shop_common.php';

/**
 * Cartelle da cui un download "file" puo' essere servito. Tutto il resto del
 * sito resta fuori, anche se nel database finisse un percorso inventato.
 * `uploads/downloads` e' dove finiscono i file caricati dal pannello.
 */
const SHOP_DOWNLOAD_DIRS = ['uploads/downloads', 'random stuff'];

/**
 * Estensioni caricabili dal pannello admin. Gli eseguibili (.exe, .msi,
 * .bat, .apk...) restano fuori: per quelli si mette il link esterno
 * alla fonte ufficiale, come si fa gia' per osu!.
 */
const SHOP_DOWNLOAD_EXTENSIONS = [
    'pdf', 'txt', 'md', 'zip', '7z', 'rar', 'tar', 'gz',
    'png', 'jpg', 'jpeg', 'gif', 'webp',
    'mp3', 'wav', 'ogg', 'm4a', 'flac',
    'mp4', 'webm', 'mov', 'mkv',
    'osk', 'osz', 'json', 'csv',
];

function shop_downloads_ready(?mysqli $mysqli): bool
{
    return shop_tables_ready($mysqli, ['download_items']);
}

function shop_site_root(): string
{
    return realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);
}

/**
 * Percorso assoluto di un file di download, oppure null se il file non
 * esiste o sta fuori dalle cartelle ammesse.
 */
function shop_download_file_path(string $source): ?string
{
    $source = rawurldecode(trim($source));
    if ($source === '' || str_contains($source, "\0")) {
        return null;
    }

    $root = shop_site_root();
    $real = realpath($root . '/' . ltrim($source, '/'));
    if ($real === false || !is_file($real)) {
        return null;
    }

    $real = str_replace('\\', '/', $real);
    $rootNorm = rtrim(str_replace('\\', '/', $root), '/');

    foreach (SHOP_DOWNLOAD_DIRS as $dir) {
        if (str_starts_with($real, $rootNorm . '/' . $dir . '/')) {
            return $real;
        }
    }

    return null;
}

function shop_format_bytes(int $bytes, string $lang): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $value = (float)$bytes;
    $unit = 0;

    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }

    $decimals = $unit === 0 || $value >= 100 ? 0 : 1;
    $sep = $lang === 'en' ? '.' : ',';

    return number_format($value, $decimals, $sep, $lang === 'en' ? ',' : '.') . ' ' . $units[$unit];
}

function shop_compact_count(int $count, string $lang): string
{
    if ($count >= 1000000) {
        return str_replace('.', $lang === 'en' ? '.' : ',', (string)round($count / 1000000, 1)) . 'M';
    }
    if ($count >= 1000) {
        return str_replace('.', $lang === 'en' ? '.' : ',', (string)round($count / 1000, 1)) . 'K';
    }

    return (string)$count;
}

/**
 * Meta e passi stanno in JSON con le due lingue dentro:
 *   meta:  {"it": [["Piattaforma", "Windows"]], "en": [["Platform", "Windows"]]}
 *   passi: {"it": ["Clicca...", "..."], "en": ["Click...", "..."]}
 * Una lingua vuota ricade sull'altra.
 */
function shop_download_json(?string $json): array
{
    $decoded = json_decode((string)$json, true);
    return is_array($decoded) ? $decoded : [];
}

function shop_download_localized_list(?string $json, string $lang): array
{
    $data = shop_download_json($json);
    $list = $data[$lang] ?? [];

    if (!is_array($list) || !$list) {
        $list = $data['it'] ?? [];
    }

    return is_array($list) ? $list : [];
}

/**
 * La sorgente giusta per la lingua di chi scarica: il file o link inglese,
 * se c'e', altrimenti quello italiano.
 */
function shop_download_source(array $row, string $lang): string
{
    if ($lang === 'en') {
        $en = trim((string)($row['sorgente_en'] ?? ''));
        if ($en !== '') {
            return $en;
        }
    }

    return trim((string)($row['sorgente'] ?? ''));
}

function shop_download_view(array $row, string $lang): array
{
    $type = $row['tipo'] === 'file' ? 'file' : 'link';
    $source = shop_download_source($row, $lang);
    $state = in_array($row['stato'], ['disponibile', 'presto', 'nascosto'], true) ? $row['stato'] : 'disponibile';

    $size = null;
    $ext = '';
    $host = '';

    if ($type === 'file') {
        $path = shop_download_file_path($source);
        if ($path !== null) {
            $size = (int)filesize($path);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        }
    } else {
        $host = (string)(parse_url($source, PHP_URL_HOST) ?: '');
        $host = preg_replace('/^www\./i', '', $host) ?? $host;
        $pathExt = strtolower(pathinfo((string)parse_url($source, PHP_URL_PATH), PATHINFO_EXTENSION));
        $ext = preg_match('/^[a-z0-9]{2,5}$/', $pathExt) ? $pathExt : '';
    }

    $slug = (string)$row['slug'];
    $meta = [];
    foreach (shop_download_localized_list($row['meta'] ?? null, $lang) as $pair) {
        if (is_array($pair) && count($pair) >= 2 && trim((string)$pair[0]) !== '' && trim((string)$pair[1]) !== '') {
            $meta[] = ['label' => trim((string)$pair[0]), 'value' => trim((string)$pair[1])];
        }
    }

    $steps = [];
    foreach (shop_download_localized_list($row['passi'] ?? null, $lang) as $step) {
        if (is_string($step) && trim($step) !== '') {
            $steps[] = trim($step);
        }
    }

    $short = shop_pick($row, 'descrizione_breve', $lang);

    return [
        'id' => (int)$row['id'],
        'slug' => $slug,
        'name' => shop_pick($row, 'nome', $lang),
        'kicker' => shop_pick($row, 'kicker', $lang),
        'short' => $short,
        'description' => shop_pick($row, 'descrizione', $lang) ?: $short,
        'image' => shop_asset_url($row['immagine'] ?? ''),
        'badge' => shop_pick($row, 'badge', $lang),
        'state' => $state,
        'type' => $type,
        // Un file che non si trova piu' sul disco non si offre: meglio
        // "non disponibile" che un 404 dopo il clic.
        'available' => $state === 'disponibile' && ($type === 'link' ? $source !== '' : $size !== null),
        'size' => $size,
        'size_label' => $size !== null ? shop_format_bytes($size, $lang) : '',
        'ext' => $ext,
        'host' => $host,
        'button' => shop_pick($row, 'testo_bottone', $lang),
        'note' => shop_pick($row, 'nota', $lang),
        'note_tone' => $row['nota_tono'] === 'avviso' ? 'warning' : 'info',
        'meta' => $meta,
        'steps' => $steps,
        'count' => (int)$row['contatore'],
        'count_label' => shop_compact_count((int)$row['contatore'], $lang),
        'featured' => (int)$row['in_evidenza'] === 1,
        'is_new' => shop_is_recent($row['created_at'] ?? null, 14),
        'position' => (int)$row['posizione'],
        'created' => strtotime((string)$row['created_at']) ?: 0,
        'url' => '/' . $lang . '/download/' . rawurlencode($slug),
        'go_url' => '/api/shop/download.php?slug=' . rawurlencode($slug) . '&lang=' . $lang,
    ];
}

function shop_downloads(mysqli $mysqli, string $lang): array
{
    $rows = shop_fetch_all(
        $mysqli,
        "SELECT * FROM download_items WHERE stato <> 'nascosto' ORDER BY posizione ASC, id ASC"
    );

    return array_map(static fn(array $row): array => shop_download_view($row, $lang), $rows);
}

function shop_download_row(mysqli $mysqli, string $slug): ?array
{
    return shop_fetch_one($mysqli, 'SELECT * FROM download_items WHERE slug = ? LIMIT 1', 's', [$slug]);
}

/**
 * L'icona che dice che cosa si sta per scaricare.
 */
function shop_download_icon(array $item): string
{
    if ($item['type'] === 'link') {
        return 'fa-solid fa-arrow-up-right-from-square';
    }

    return match ($item['ext']) {
        'pdf' => 'fa-solid fa-file-pdf',
        'zip', '7z', 'rar', 'tar', 'gz' => 'fa-solid fa-file-zipper',
        'txt', 'md', 'json', 'csv' => 'fa-solid fa-file-lines',
        'png', 'jpg', 'jpeg', 'gif', 'webp' => 'fa-solid fa-file-image',
        'mp3', 'wav', 'ogg', 'm4a', 'flac' => 'fa-solid fa-file-audio',
        'mp4', 'webm', 'mov', 'mkv' => 'fa-solid fa-file-video',
        default => 'fa-solid fa-file-arrow-down',
    };
}
