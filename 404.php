<?php
declare(strict_types=1);

/**
 * La pagina 404.
 *
 * Ogni 404 passa da qui: .htaccess ci manda i 404 di Apache e gli indirizzi
 * /404 e /404.html (dove stava la pagina prima), profile.php ci manda gli
 * alias e i profili che non esistono. La pagina la disegna
 * includes/avviso_page.php, la stessa degli avvisi dei profili.
 */

require_once __DIR__ . '/includes/avviso_page.php';

cripsum_avviso_page([
    'status' => 404,
    'lang' => 'en',
    'title' => '404',
    'number' => '404',
    'headline' => "This page doesn't exist.",
    'text' => 'Seriously bro, where were you trying to go?',
    'meme' => ['src' => '/img/emojithinkingmeme.png', 'alt' => 'Thinking meme emoji'],
    'alert' => "The page you're looking for has been moved, deleted, or never existed.",
    'actions' => [
        ['href' => '/en/home', 'icon' => 'fa-house', 'label' => 'Go back home', 'primary' => true],
        ['href' => 'javascript:history.back()', 'icon' => 'fa-arrow-left', 'label' => 'Go back'],
    ],
]);
