<?php

/**
 * Navbar ridotta al marchio.
 *
 * La usano le pagine di recupero password: niente menu, niente ricerca,
 * niente pannello account, cosi' chi sta reimpostando la password non ha
 * modo di uscire dal flusso per sbaglio.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/mission_tracker.php';
require_once __DIR__ . '/nav_render.php';

$navCtx = nav_bootstrap([
    'variant'      => 'minimal',
    'mysqli'       => $mysqli ?? null,
    'show_search'  => false,
    'show_lang'    => false,
    'show_account' => false,
    'brand_href'   => '',
]);

$uri        = $navCtx['uri'];
$lang       = $navCtx['lang'];
$t          = $navCtx['t'];
$isLoggedIn = $navCtx['isLoggedIn'];

if ($isLoggedIn) {
    $username   = $navCtx['username'];
    $userId     = $navCtx['userId'];
    $profilePic = $navCtx['profilePic'];
    $ruolo      = $navCtx['ruolo'];
    $nsfw       = $navCtx['nsfw'];
}

nav_render($navCtx);
