<?php
/**
 * Apertura della pagina del team: <head>, tema e navbar.
 *
 * Variabili attese:
 *   $esLang          'it' | 'en'
 *   $pageTitle       titolo della scheda (senza "Cripsum™ - ")
 *   $pageDescription descrizione per le anteprime dei link (facoltativa)
 *   $pageImage       immagine per le anteprime (facoltativa)
 *   $bodyStyle       variabili CSS del tema (facoltative)
 *   $bodyData        attributi data-* del body per esports.js (facoltativi)
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

$esBodyAttrs = '';
foreach (($bodyData ?? []) as $esAttr => $esValue) {
    $esBodyAttrs .= ' data-' . $esAttr . '="' . shop_h($esValue) . '"';
}
?>
<!DOCTYPE html>
<html lang="<?php echo shop_h($esLang); ?>">

<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <title><?php echo shop_h('Cripsum™ - ' . $pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php if (!empty($presence)) echo shop_presence_meta($presence['title'], $presence['state']); ?>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7/css/flag-icons.min.css">
    <link rel="stylesheet" href="<?php echo shop_h(cripsum_asset('/assets/esports/esports.css')); ?>">
    <script src="<?php echo shop_h(cripsum_asset('/assets/esports/esports.js')); ?>" defer></script>
</head>

<body class="es-page" style="<?php echo shop_h($bodyStyle ?? ''); ?>" data-es-lang="<?php echo shop_h($esLang); ?>"<?php echo $esBodyAttrs; ?>>
    <?php include __DIR__ . '/../../navbar.php'; ?>
