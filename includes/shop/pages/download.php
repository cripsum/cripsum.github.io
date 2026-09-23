<?php
/**
 * /it/download (lista) e /it/download/{slug} (dettaglio).
 * La regola in .htaccess passa lo slug come ?item=.
 *
 * Prima ogni dettaglio era un file PHP a se' (fortnite.php, osu.php...),
 * duplicato per le due lingue: adesso sono righe di download_items e
 * questa pagina li disegna tutti.
 */

require_once __DIR__ . '/../catalog.php';
require_once __DIR__ . '/../downloads.php';
require_once __DIR__ . '/../strings.php';

$S = shop_strings($shopLang);
$itemSlug = strtolower(trim((string)($_GET['item'] ?? '')));
$bodyClass = 'shop-theme-download';
$listUrl = '/' . $shopLang . '/download';

if (!shop_downloads_ready($mysqli)) {
    $pageTitle = 'Download';
    include __DIR__ . '/../partials/top.php';
    $stateIcon = 'fa-solid fa-screwdriver-wrench';
    $stateTitle = $S['maintenance_title'];
    $stateText = $S['maintenance_text'];
    $stateLink = ['href' => '/' . $shopLang . '/home', 'label' => $S['go_home']];
    include __DIR__ . '/../partials/state.php';
    include __DIR__ . '/../partials/bottom.php';
    return;
}

/* ── Dettaglio ───────────────────────────────────────────────────────── */

if ($itemSlug !== '') {
    $row = preg_match('/^[a-z0-9-]{1,80}$/', $itemSlug) ? shop_download_row($mysqli, $itemSlug) : null;
    $isPreview = $row && $row['stato'] === 'nascosto';

    if (!$row || ($isPreview && !shop_is_staff())) {
        http_response_code(404);
        $pageTitle = 'Download';
        include __DIR__ . '/../partials/top.php';
        $stateIcon = 'fa-solid fa-folder-open';
        $stateTitle = $S['not_found_title'];
        $stateText = $S['download_not_found'];
        $stateLink = ['href' => $listUrl, 'label' => $S['back_to_downloads']];
        include __DIR__ . '/../partials/state.php';
        include __DIR__ . '/../partials/bottom.php';
        return;
    }

    $item = shop_download_view($row, $shopLang);
    $others = array_values(array_filter(
        shop_downloads($mysqli, $shopLang),
        static fn(array $d): bool => $d['slug'] !== $item['slug'] && $d['state'] === 'disponibile'
    ));
    usort($others, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
    $others = array_slice($others, 0, 3);

    $pageTitle = $item['name'];
    $pageDescription = $item['short'] !== '' ? $item['short'] : $item['description'];
    $pageImage = $item['image'];
    $presence = [
        'title' => 'Download',
        'state' => ($shopLang === 'en' ? 'Downloading ' : 'Scaricando ') . $item['name'],
    ];

    include __DIR__ . '/../partials/top.php';
    include __DIR__ . '/download_item.php';
    include __DIR__ . '/../partials/bottom.php';
    return;
}

/* ── Lista ───────────────────────────────────────────────────────────── */

$page = shop_page_texts($mysqli, 'download', $shopLang);
$items = shop_downloads($mysqli, $shopLang);
$faq = shop_catalog_ready($mysqli) ? shop_faq($mysqli, 'download', null, $shopLang) : [];
$missing = isset($_GET['missing']);

// La lista ha gia' la sua voce in richpresence.js.
$pageTitle = 'Download';
$pageDescription = $page['subtitle'] !== '' ? $page['subtitle'] : null;
$presence = null;

include __DIR__ . '/../partials/top.php';
include __DIR__ . '/download_list.php';
include __DIR__ . '/../partials/bottom.php';
