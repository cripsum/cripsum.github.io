<?php
declare(strict_types=1);

/**
 * Stato interno del sito, per il controllo esterno del bot.
 *
 * Risponde sempre, anche quando qualcosa e' rotto: e' il contenuto a dire cosa
 * non va. Non espone nulla di sensibile — solo se i pezzi rispondono e quanto
 * ci mettono — quindi non richiede autenticazione: deve funzionare anche
 * quando la parte autenticata e' proprio quella caduta.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// Le credenziali vanno caricate a livello globale: dentro una funzione
// diventerebbero locali e il require_once impedirebbe a chiunque altro di
// rileggerle.
$statusConfigPath = __DIR__ . '/../../secure/config.php';
if (is_file($statusConfigPath)) {
    require_once $statusConfigPath;
}

$startedAt = microtime(true);
$services = [];

$measure = static function (callable $check): array {
    $start = microtime(true);

    try {
        $error = $check();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    return [
        'status' => $error === null ? 'operational' : 'outage',
        'latency_ms' => (int)round((microtime(true) - $start) * 1000),
        'error' => $error === null ? null : mb_substr((string)$error, 0, 200),
    ];
};

// --- Database --------------------------------------------------------------
// Si apre una connessione a parte invece di includere config/database.php:
// quello termina la pagina con die() se il database non risponde, e qui
// servirebbe esattamente il contrario — rispondere che non risponde.
$services['database'] = $measure(static function () {
    global $db_host, $db_user, $db_pass, $db_name;

    if (!isset($db_host, $db_user, $db_pass, $db_name)) {
        return 'credenziali del database incomplete';
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    // Timeout corto: il controllo deve concludersi in fretta anche quando il
    // database non risponde, altrimenti e' il controllo stesso a bloccarsi.
    $link = mysqli_init();
    if (!$link) {
        return 'driver mysqli non disponibile';
    }

    @$link->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    @$link->options(MYSQLI_OPT_READ_TIMEOUT, 3);

    if (!@$link->real_connect($db_host, $db_user, $db_pass, $db_name) || $link->connect_errno) {
        return 'connessione rifiutata (' . $link->connect_errno . ')';
    }

    $result = @$link->query('SELECT 1');
    if (!$result) {
        $error = $link->error;
        $link->close();
        return 'query di prova fallita: ' . $error;
    }

    $result->free();
    $link->close();

    return null;
});

// --- Spazio di archiviazione ----------------------------------------------
$services['storage'] = $measure(static function () {
    $uploads = __DIR__ . '/../../uploads';

    if (!is_dir($uploads)) {
        return 'cartella uploads mancante';
    }

    if (!is_writable($uploads)) {
        return 'cartella uploads non scrivibile';
    }

    return null;
});

$worst = 'operational';
foreach ($services as $service) {
    if ($service['status'] === 'outage') {
        $worst = 'outage';
        break;
    }
}

echo json_encode([
    'ok' => true,
    'status' => $worst,
    'services' => $services,
    'php' => PHP_VERSION,
    'checked_at' => date(DATE_ATOM),
    'total_ms' => (int)round((microtime(true) - $startedAt) * 1000),
], JSON_UNESCAPED_SLASHES);
