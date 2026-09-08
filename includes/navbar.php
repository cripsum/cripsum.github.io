<?php

/**
 * Navbar principale.
 *
 * Le voci stanno in nav_config.php e il markup in nav_render.php: qui
 * restano le opzioni di questa variante e le variabili che diverse pagine
 * leggono dopo l'include (it/shop.php usa $lang parecchie righe piu' sotto,
 * per dirne una).
 */

require_once __DIR__ . '/mission_tracker.php';
require_once __DIR__ . '/nav_render.php';

$navCtx = nav_bootstrap([
    'variant' => 'main',
    'mysqli'  => $mysqli ?? null,
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
