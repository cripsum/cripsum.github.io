<?php
/**
 * Apertura comune delle pagine shop: <head>, tema e navbar.
 *
 * Variabili attese:
 *   $shopLang        'it' | 'en'
 *   $pageTitle       titolo della scheda (senza "Cripsum™ - ")
 *   $pageDescription descrizione per le anteprime dei link (facoltativa)
 *   $pageImage       immagine per le anteprime (facoltativa)
 *   $bodyClass       classi aggiuntive del body (facoltative)
 *   $bodyStyle       variabili CSS del tema (facoltative)
 *   $presence        ['title' => ..., 'state' => ...] per la Rich Presence (facoltativa)
 *
 * Si include dal livello principale della pagina: la navbar e head-import
 * leggono $mysqli e scrivono $lang e $t nello stesso scope.
 */

$ogTitle = 'Cripsum™ - ' . $pageTitle;
if (!empty($pageDescription)) {
    $ogDescription = $pageDescription;
}
if (!empty($pageImage)) {
    $ogImage = $pageImage;
}
?>
<!DOCTYPE html>
<html lang="<?php echo shop_h($shopLang); ?>">

<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <title><?php echo shop_h('Cripsum™ - ' . $pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php if (!empty($presence)) echo shop_presence_meta($presence['title'], $presence['state']); ?>
    <link rel="stylesheet" href="<?php echo shop_h(cripsum_asset('/assets/shop/catalog.css')); ?>">
    <script src="<?php echo shop_h(cripsum_asset('/assets/shop/catalog.js')); ?>" defer></script>
</head>

<body class="shop-page <?php echo shop_h($bodyClass ?? ''); ?>" style="<?php echo shop_h($bodyStyle ?? ''); ?>" data-shop-lang="<?php echo shop_h($shopLang); ?>">
    <?php include __DIR__ . '/../../navbar.php'; ?>
