<?php

/**
 * Navbar della sezione GoonLand.
 *
 * Le voci di sinistra sono tutte sue (vedi nav_primary_menu con variant
 * 'goonland') e non c'e' la ricerca utenti; il pannello account resta lo
 * stesso delle altre pagine.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/mission_tracker.php';
require_once __DIR__ . '/nav_render.php';

$navCtx = nav_bootstrap([
    'variant'     => 'goonland',
    'mysqli'      => $mysqli ?? null,
    'show_search' => false,
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
