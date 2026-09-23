<?php
/**
 * /it/merch (elenco delle collezioni) e /it/merch/{slug} (una collezione).
 * La regola in .htaccess passa lo slug come ?collezione=.
 *
 * Con una sola collezione aperta l'elenco non serve: /it/merch mostra
 * direttamente quella, e quando ne arriva una seconda diventa l'elenco da
 * solo. Le collezioni "in arrivo" contano: il teaser di un drop e' proprio
 * il motivo per cui l'elenco esiste.
 */

require_once __DIR__ . '/../catalog.php';
require_once __DIR__ . '/../strings.php';

$S = shop_strings($shopLang);
$hubUrl = '/' . $shopLang . '/merch';
$requestedSlug = strtolower(trim((string)($_GET['collezione'] ?? '')));

if (!shop_catalog_ready($mysqli)) {
    $pageTitle = 'Merch';
    $bodyClass = 'shop-theme-merch';
    include __DIR__ . '/../partials/top.php';
    $stateIcon = 'fa-solid fa-screwdriver-wrench';
    $stateTitle = $S['maintenance_title'];
    $stateText = $S['maintenance_text'];
    $stateLink = ['href' => '/' . $shopLang . '/home', 'label' => $S['go_home']];
    include __DIR__ . '/../partials/state.php';
    include __DIR__ . '/../partials/bottom.php';
    return;
}

$collections = shop_merch_collections($mysqli, $shopLang);
$vetrina = null;

if ($requestedSlug !== '') {
    $vetrina = preg_match('/^[a-z0-9-]{1,60}$/', $requestedSlug)
        ? shop_vetrina($mysqli, 'merch', $requestedSlug, $shopLang)
        : null;

    // Una collezione nascosta la vede solo lo staff, per prepararla.
    if ($vetrina && $vetrina['state'] === 'nascosta' && !shop_is_staff()) {
        $vetrina = null;
    }

    if (!$vetrina) {
        http_response_code(404);
        $pageTitle = 'Merch';
        $bodyClass = 'shop-theme-merch';
        include __DIR__ . '/../partials/top.php';
        $stateIcon = 'fa-solid fa-shirt';
        $stateTitle = $S['not_found_title'];
        $stateText = $S['not_found_collection'];
        $stateLink = ['href' => $hubUrl, 'label' => $S['back_to_merch']];
        include __DIR__ . '/../partials/state.php';
        include __DIR__ . '/../partials/bottom.php';
        return;
    }
} elseif (count($collections) === 1 && $collections[0]['state'] === 'attiva') {
    $vetrina = shop_vetrina($mysqli, 'merch', $collections[0]['slug'], $shopLang);
    $hubUrl = null;
}

/* ── Elenco delle collezioni ─────────────────────────────────────────── */

if (!$vetrina) {
    $page = shop_page_texts($mysqli, 'merch', $shopLang);
    $faq = shop_faq($mysqli, 'merch', null, $shopLang);
    $latest = count($collections) > 1 ? shop_latest_merch($mysqli, $shopLang, 8) : [];

    $pageTitle = 'Merch';
    $pageDescription = $page['subtitle'];
    $pageImage = $collections[0]['cover'] ?? ($collections[0]['preview'][0] ?? '');
    $bodyClass = 'shop-theme-merch';
    $bodyStyle = $collections ? $collections[0]['style'] : '';
    $presence = [
        'title' => 'Merch',
        'state' => $shopLang === 'en' ? 'Browsing the merch' : 'Guardando il merch',
    ];

    include __DIR__ . '/../partials/top.php';
    include __DIR__ . '/merch_hub.php';
    include __DIR__ . '/../partials/bottom.php';
    return;
}

/* ── Una collezione ──────────────────────────────────────────────────── */

$isPreview = $vetrina['state'] !== 'attiva' && shop_is_staff();
$categories = shop_categories($mysqli, 'merch', $shopLang);
$products = shop_products($mysqli, $vetrina['id'], $shopLang, $categories);
$faq = shop_faq($mysqli, 'vetrina', $vetrina['id'], $shopLang);

$otherCollections = array_values(array_filter($collections, static fn(array $c): bool => $c['id'] !== $vetrina['id']));
if ($hubUrl !== null && !$otherCollections) {
    // Una sola collezione in tutto: niente "torna all'elenco" verso un
    // elenco che mostrerebbe di nuovo questa stessa pagina.
    $hubUrl = null;
}

$pageTitle = 'Merch ' . $vetrina['name'];
$pageDescription = $vetrina['subtitle'];
$pageImage = $vetrina['cover'] ?: ($vetrina['logo'] ?: ($products[0]['image'] ?? ''));
$bodyClass = 'shop-theme-merch';
$bodyStyle = $vetrina['style'];
$presence = [
    'title' => 'Merch',
    'state' => ($shopLang === 'en' ? 'Shopping the ' : 'Acquistando il merch di ') . $vetrina['name'] . ($shopLang === 'en' ? ' merch' : ''),
];

include __DIR__ . '/../partials/top.php';
include __DIR__ . '/vetrina.php';
include __DIR__ . '/../partials/bottom.php';
