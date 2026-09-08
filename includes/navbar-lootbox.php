<?php

/**
 * Navbar delle pagine lootbox.
 *
 * Uguale a navbar.php, ma senza l'entrata in dissolvenza (la pagina ha gia'
 * le sue animazioni di apertura) e con il pulsante che apre il modale delle
 * impostazioni, che qui esiste.
 */

require_once __DIR__ . '/mission_tracker.php';
require_once __DIR__ . '/nav_render.php';

$navCtx = nav_bootstrap([
    'variant'        => 'main',
    'mysqli'         => $mysqli ?? null,
    'fadein'         => false,
    'settings_modal' => true,
]);

$uri         = $navCtx['uri'];
$lang        = $navCtx['lang'];
$altLang     = $navCtx['altLang'];
$altLabel    = $navCtx['altLabel'];
$curLabel    = $navCtx['curLabel'];
$switchUrl   = $navCtx['switchUrl'];
$t           = $navCtx['t'];
$isLoggedIn  = $navCtx['isLoggedIn'];
$unreadCount = $navCtx['unreadCount'];

if ($isLoggedIn) {
    $username     = $navCtx['username'];
    $userId       = $navCtx['userId'];
    $profilePic   = $navCtx['profilePic'];
    $ruolo        = $navCtx['ruolo'];
    $nsfw         = $navCtx['nsfw'];
    $richpresence = $navCtx['richpresence'];
}

nav_render($navCtx);
