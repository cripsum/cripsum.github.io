<?php

/**
 * Navbar delle pagine profilo.
 *
 * Uguale a navbar.php salvo il cambio lingua, che nella barra compare solo
 * su edit-profile (altrove il link porterebbe fuori dal profilo che si sta
 * guardando). Nella riga mobile resta sempre.
 */

require_once __DIR__ . '/mission_tracker.php';
require_once __DIR__ . '/nav_render.php';

$navCtx = nav_bootstrap([
    'variant'           => 'main',
    'mysqli'            => $mysqli ?? null,
    'show_lang_desktop' => (bool)preg_match('#^/(it|en)/edit-profile\b#', $_SERVER['REQUEST_URI'] ?? ''),
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
