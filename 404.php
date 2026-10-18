<?php
declare(strict_types=1);

/**
 * La pagina 404, col tema del sito.
 *
 * Il contenuto resta in 404.html, che e' una pagina ferma: qui le si
 * aggiungono l'attributo sul tag <html> e il foglio del tema, come fanno
 * tutte le altre. Le regole che la vestono col tema stanno gia' dentro
 * 404.html e in assets/static/static.css, e valgono solo con quell'attributo.
 *
 * Ogni 404 deve passare da qui, mai da 404.html da sola, che senza
 * l'attributo esce con l'aspetto vecchio: .htaccess ci manda i 404 di Apache
 * e gli indirizzi /404 e /404.html, profile.php ci manda gli alias e i
 * profili che non esistono.
 */

require_once __DIR__ . '/includes/theme.php';

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');

$page = (string)file_get_contents(__DIR__ . '/404.html');

ob_start();
cripsum_theme_head();
$sheets = (string)ob_get_clean();

$page = preg_replace('/<html lang="en">/', '<html lang="en"' . cripsum_theme_html_attr() . '>', $page, 1);
$page = preg_replace('~</head>~', $sheets . '</head>', (string)$page, 1);

echo $page;
