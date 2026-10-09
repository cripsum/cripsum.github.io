<?php
declare(strict_types=1);

/**
 * La pagina 404, col tema del sito.
 *
 * Il contenuto resta in 404.html, che e' una pagina ferma: la include anche
 * profile.php quando un profilo non esiste, e li' deve uscire com'e'. Per
 * questo .htaccess manda qui i 404: alla pagina ferma si aggiungono
 * l'attributo sul tag <html> e i fogli del tema, come fanno tutte le altre.
 */

require_once __DIR__ . '/includes/theme.php';

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');

$page = (string)file_get_contents(__DIR__ . '/404.html');

ob_start();
cripsum_theme_head('static', 'error');
$sheets = (string)ob_get_clean();

$page = preg_replace('/<html lang="en">/', '<html lang="en"' . cripsum_theme_html_attr() . '>', $page, 1);
$page = preg_replace('~</head>~', $sheets . '</head>', (string)$page, 1);

echo $page;
