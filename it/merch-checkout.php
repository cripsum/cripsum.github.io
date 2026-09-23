<?php
// Il checkout del merch adesso e' lo stesso del Negozio (/it/checkout?p=...).
// Chi arriva da un link vecchio, senza prodotto, torna al merch.
$product = (string)($_GET['p'] ?? '');

if (preg_match('/^[a-z0-9-]{1,80}$/', $product)) {
    header('Location: /it/checkout?p=' . rawurlencode($product), true, 301);
} else {
    header('Location: /it/merch', true, 301);
}
exit;
