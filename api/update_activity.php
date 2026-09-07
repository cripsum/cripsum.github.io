<?php

/**
 * Cripsum™ — Heartbeat di attività
 *
 * Chiamato da includes/head-import.php su ogni pagina, ogni 25 secondi.
 *
 * Fa due cose:
 *   1. aggiorna `utenti.ultimo_accesso`, che alimenta l'indicatore online
 *      (comportamento storico di questo endpoint, invariato);
 *   2. accumula il tempo realmente attivo nelle statistiche del Rewind.
 *
 * Il tempo dichiarato dal client non viene mai creduto sulla parola: viene
 * limitato dal tempo davvero trascorso fra due battiti secondo l'orologio del
 * server, così una scheda che mente non può gonfiare il proprio totale.
 *
 * Le scritture sulle statistiche sono raggruppate: al massimo una al minuto
 * per utente, invece di una ogni 25 secondi.
 *
 * Endpoint : GET|POST /api/update_activity.php
 * Auth     : sessione PHP
 */

require_once __DIR__ . '/../config/session_init.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/stats_tracker.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

$userId = (int)$_SESSION['user_id'];

// ── Presenza online (invariato) ──────────────────────────────
$stmt = $mysqli->prepare('UPDATE utenti SET ultimo_accesso = NOW() WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

// ── Input ────────────────────────────────────────────────────
// sendBeacon manda un corpo JSON con content-type text/plain, la fetch
// normale usa la query string. Accettiamo entrambi.
$payload = [];
$rawBody = file_get_contents('php://input');
if (is_string($rawBody) && $rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}
$payload += $_POST + $_GET;

$pagePath    = (string)($payload['page'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
$pageKey     = stats_page_key_from_path(parse_url($pagePath, PHP_URL_PATH) ?? $pagePath);
$claimed     = (int)($payload['delta'] ?? 0);
$isFirstBeat = !empty($payload['first']);
$isFinal     = !empty($payload['final']);

$now  = time();
$last = (int)($_SESSION['stats_hb_last'] ?? 0);

// Tempo davvero passato dall'ultimo battito secondo il server.
$serverElapsed = ($last > 0 && $now >= $last) ? ($now - $last) : 0;

// Il valore buono è il minore fra quanto dichiara il client, quanto è
// passato davvero e il tetto assoluto. Al primo battito di una sessione
// non c'è un riferimento precedente, quindi non si accredita nulla.
$seconds = 0;
if ($claimed > 0 && $serverElapsed > 0) {
    $seconds = min($claimed, $serverElapsed, STATS_HEARTBEAT_MAX_SECONDS);
}

$_SESSION['stats_hb_last'] = $now;

// ── Import una tantum dei vecchi cookie ──────────────────────
// Il tempo e i giorni accumulati finora vivono solo nel browser
// (achievements-globali.js). Li recuperiamo al primo battito utile, così il
// Rewind non parte da zero per chi il sito lo usa da anni.
if (empty($_SESSION['stats_legacy_done'])) {
    $_SESSION['stats_legacy_done'] = true;

    $legacySeconds = null;
    $legacyDays    = null;

    // I cookie sono scritti come encodeURIComponent(JSON.stringify(v)).
    // PHP ha già fatto la sua decodifica percentuale riempiendo $_COOKIE,
    // ma alcuni proxy la lasciano intatta: proviamo prima il valore così
    // com'è, poi la variante decodificata una seconda volta.
    $decodeCookie = static function (string $raw) {
        $value = json_decode($raw, true);
        if ($value !== null) {
            return $value;
        }
        return json_decode(urldecode($raw), true);
    };

    if (isset($_COOKIE['timeSpent'])) {
        $decodedTime = $decodeCookie((string)$_COOKIE['timeSpent']);
        if (is_numeric($decodedTime)) {
            $legacySeconds = (int)$decodedTime;
        }
    }

    if (isset($_COOKIE['daysVisited'])) {
        $decodedDays = $decodeCookie((string)$_COOKIE['daysVisited']);
        if (is_array($decodedDays)) {
            $legacyDays = $decodedDays;
        }
    }

    // Il client può anche mandarli esplicitamente: il cookie daysVisited
    // supera spesso i 4 KB e in quel caso il browser non lo invia.
    if (isset($payload['legacy_seconds']) && is_numeric($payload['legacy_seconds'])) {
        $legacySeconds = max((int)$legacySeconds, (int)$payload['legacy_seconds']);
    }
    if (isset($payload['legacy_days']) && is_array($payload['legacy_days'])) {
        $legacyDays = array_merge($legacyDays ?? [], $payload['legacy_days']);
    }

    if ($legacySeconds !== null || $legacyDays !== null) {
        stats_import_legacy($mysqli, $userId, $legacySeconds, $legacyDays);
    }
}

// ── Accumulo e flush ─────────────────────────────────────────
//
// Il costo del tracciamento sta tutto qui, quindi il criterio è uno solo:
// scrivere al massimo una volta al minuto per utente, qualunque cosa faccia.
//
// Il buffer tiene secondi e visualizzazioni divisi per sezione del sito, così
// chi apre sei pagine in un minuto produce comunque una sola scrittura invece
// di sei. Una visualizzazione contata sessanta secondi più tardi non cambia
// nulla per l'utente; sei query in più per ogni pagina, su un sito con
// parecchia gente collegata, sì.
//
// Niente va perso: il battito finale su `pagehide` svuota il buffer anche per
// chi resta su una pagina meno di un minuto.

$buffer = $_SESSION['stats_hb_buf'] ?? [];
if (!is_array($buffer)) {
    $buffer = [];
}

if (!isset($buffer[$pageKey])) {
    $buffer[$pageKey] = ['seconds' => 0, 'views' => 0];
}

// I secondi appartengono alla pagina su cui sono stati spesi, cioè quella del
// battito precedente, non a quella appena aperta.
$previousPage = (string)($_SESSION['stats_hb_page'] ?? $pageKey);
if (!isset($buffer[$previousPage])) {
    $buffer[$previousPage] = ['seconds' => 0, 'views' => 0];
}

$buffer[$previousPage]['seconds'] += $seconds;
if ($isFirstBeat) {
    $buffer[$pageKey]['views'] += 1;
}

$lastFlush = (int)($_SESSION['stats_hb_flushed'] ?? 0);
if ($lastFlush === 0) {
    $lastFlush = $now;
}

$shouldFlush = $isFinal || ($now - $lastFlush) >= 60;

if ($shouldFlush) {
    $_SESSION['stats_hb_buf'] = [];
    $_SESSION['stats_hb_flushed'] = $now;

    stats_flush_buffer($mysqli, $userId, $buffer);
} else {
    $_SESSION['stats_hb_buf'] = $buffer;
    $_SESSION['stats_hb_flushed'] = $lastFlush;
}

$_SESSION['stats_hb_page'] = $pageKey;

// La sessione non serve più: liberiamo il lock così le altre richieste
// della stessa scheda non si mettono in coda dietro a questa.
cripsum_release_session();

http_response_code(200);
echo json_encode(['ok' => true]);
