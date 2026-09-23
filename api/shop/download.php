<?php
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/shop/downloads.php';

/**
 * Da qui passa ogni clic su "Scarica".
 *
 * Conta il download (una volta per sessione, i bot no), fa avanzare la
 * missione "scarica un contenuto" e poi reindirizza al link esterno o serve
 * il file come allegato. Prima la missione la segnalava il browser con una
 * POST a parte, che chiunque poteva ripetere a piacere.
 */

$lang = ($_GET['lang'] ?? 'it') === 'en' ? 'en' : 'it';
$slug = (string)($_GET['slug'] ?? '');

// Chi arriva qui da un link vecchio torna alla lista, che gli spiega che
// quel download non c'e' piu', invece di finire su una pagina bianca.
function download_not_found(string $lang): void
{
    header('Location: /' . $lang . '/download?missing=1', true, 302);
    exit;
}

if (!preg_match('/^[a-z0-9-]{2,80}$/', $slug) || !shop_downloads_ready($mysqli)) {
    download_not_found($lang);
}

$row = shop_download_row($mysqli, $slug);
$isStaff = shop_is_staff();

// I download nascosti li puo' provare solo lo staff; quelli "in arrivo" non
// hanno ancora niente da scaricare.
if (!$row || $row['stato'] === 'presto' || ($row['stato'] === 'nascosto' && !$isStaff)) {
    download_not_found($lang);
}

$source = shop_download_source($row, $lang);
$isFile = $row['tipo'] === 'file';
$filePath = $isFile ? shop_download_file_path($source) : null;

if ($source === '' || ($isFile && $filePath === null) || (!$isFile && !shop_valid_link($source))) {
    error_log('[download] sorgente non valida per ' . $slug);
    download_not_found($lang);
}

$userAgent = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
$isBot = $userAgent === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|embed/', $userAgent);

if (!$isBot && $row['stato'] === 'disponibile') {
    $counted = $_SESSION['shop_downloads_counted'] ?? [];
    if (!is_array($counted)) {
        $counted = [];
    }

    if (empty($counted[$slug])) {
        $counted[$slug] = time();
        $_SESSION['shop_downloads_counted'] = array_slice($counted, -50, null, true);

        try {
            $stmt = $mysqli->prepare('UPDATE download_items SET contatore = contatore + 1 WHERE id = ? LIMIT 1');
            if ($stmt) {
                $id = (int)$row['id'];
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('[download] contatore: ' . $e->getMessage());
        }

        if (isLoggedIn()) {
            try {
                require_once __DIR__ . '/../../includes/mission_tracker.php';
                trackMissionProgress($mysqli, (int)$_SESSION['user_id'], 'download_content');
            } catch (Throwable $e) {
                error_log('[download] missione: ' . $e->getMessage());
            }
        }
    }
}

// La sessione non serve piu': chiuderla subito evita che un download lungo
// blocchi le altre pagine aperte dallo stesso utente.
session_write_close();

if (!$isFile) {
    header('Cache-Control: no-store');
    header('Location: ' . $source, true, 302);
    exit;
}

$filename = trim((string)($row['nome_file'] ?? ''));
if ($filename === '') {
    $filename = basename($filePath);
}
$filename = str_replace(['"', "\r", "\n", '/', '\\'], '', $filename);
$ascii = preg_replace('/[^\x20-\x7e]/', '_', $filename) ?: 'download';

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($filePath);
exit;
