<?php
/**
 * /it/negozio e /en/negozio, e la pagina di un prodotto /it/negozio/{slug}
 * (la regola in .htaccess passa lo slug come ?prodotto=). Si include da
 * it/negozio.php con $shopLang gia' impostata e $mysqli aperta.
 */

require_once __DIR__ . '/../catalog.php';
require_once __DIR__ . '/../strings.php';

$S = shop_strings($shopLang);
$vetrina = shop_catalog_ready($mysqli) ? shop_vetrina($mysqli, 'negozio', 'negozio', $shopLang) : null;

if (!$vetrina) {
    $pageTitle = $shopLang === 'en' ? 'Shop' : 'Negozio';
    $bodyClass = 'shop-theme-store';
    include __DIR__ . '/../partials/top.php';
    $stateIcon = 'fa-solid fa-screwdriver-wrench';
    $stateTitle = $S['maintenance_title'];
    $stateText = $S['maintenance_text'];
    $stateLink = ['href' => '/' . $shopLang . '/home', 'label' => $S['go_home']];
    include __DIR__ . '/../partials/state.php';
    include __DIR__ . '/../partials/bottom.php';
    return;
}

$productSlug = strtolower(trim((string)($_GET['prodotto'] ?? '')));

// I link della vecchia scheda rapida (/it/negozio?p=ps6) portano alla pagina
// del prodotto.
$legacySlug = strtolower(trim((string)($_GET['p'] ?? '')));
if ($productSlug === '' && preg_match('/^[a-z0-9-]{1,80}$/', $legacySlug)) {
    header('Location: ' . shop_product_url($shopLang, 'negozio', 'negozio', $legacySlug), true, 301);
    exit;
}

if ($productSlug !== '') {
    $expectedTipo = 'negozio';
    $expectedVetrinaSlug = 'negozio';
    include __DIR__ . '/prodotto.php';
    return;
}

$categories = shop_categories($mysqli, 'negozio', $shopLang);
$products = shop_products($mysqli, $vetrina, $shopLang, $categories);
$faq = shop_faq($mysqli, 'vetrina', $vetrina['id'], $shopLang);
$isPreview = false;
$otherCollections = [];
$hubUrl = null;

$pageTitle = $shopLang === 'en' ? 'Shop' : 'Negozio';
$pageDescription = $vetrina['subtitle'];
$pageImage = $vetrina['cover'] ?: ($products[0]['image'] ?? '');
$bodyClass = 'shop-theme-store';
$bodyStyle = $vetrina['style'];
$presence = [
    'title' => $shopLang === 'en' ? 'Shop' : 'Negozio',
    'state' => $shopLang === 'en' ? 'Buying useless stuff' : 'Acquistando minchiate',
];

include __DIR__ . '/../partials/top.php';
include __DIR__ . '/vetrina.php';
include __DIR__ . '/../partials/bottom.php';
