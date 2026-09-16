<?php
declare(strict_types=1);

/**
 * Registra i controlli di stato fatti dal bot.
 *
 * Ogni controllo diventa una riga, un aggiornamento del riepilogo giornaliero
 * e — quando un servizio cade o torna — l'apertura o la chiusura di un
 * disservizio. E' da qui che la pagina /status prende uptime e cronologia:
 * prima le barre dei novanta giorni erano verdi per finta.
 *
 * Quando il sito non risponde il bot tiene da parte i controlli e li manda
 * tutti appena torna, dal piu' vecchio: ognuno porta `age_seconds`, cioe'
 * quanti secondi fa e' stato fatto. Senza, i giri persi erano proprio quelli
 * con il sito giu', e lo storico restava verde durante i disservizi.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'service_status_checks')) {
    bot_json(['ok' => true, 'available' => false]);
}

const STATUS_VALUES = ['operational', 'degraded', 'outage'];

// Sei ore di arretrati a un giro al minuto, ma in piu' richieste: il bot ne
// manda al massimo trenta giri (cinque servizi l'uno) alla volta.
const STATUS_MAX_CHECKS = 300;
const STATUS_MAX_AGE_SECONDS = 7 * 86400;

$body = bot_input();
$checks = is_array($body['checks'] ?? null) ? $body['checks'] : [];

if (!$checks) {
    bot_fail('No checks submitted.', 400);
}

/**
 * Gli orari li mette il database, mai PHP.
 *
 * Con `date()` l'ora veniva dal fuso di PHP e poi finiva confrontata con NOW()
 * di MySQL: se i due non coincidono — ed e' il caso normale, PHP sull'ora di
 * Roma e MySQL su UTC — ogni controllo risultava scritto due ore nel futuro.
 * La pagina mostrava "ultimo controllo -7199 s fa", e peggio: il controllo di
 * freschezza non scattava mai, quindi con il bot fermo restava tutto verde.
 * Con un orologio solo il problema non puo' ripresentarsi: anche un controllo
 * arretrato si colloca come NOW() meno la sua eta'.
 */
$stmtCheck = $mysqli->prepare(
    'INSERT INTO service_status_checks (service, status, latency_ms, http_code, error, checked_at)
     VALUES (?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? SECOND))'
);

// Riepilogo del giorno: si aggiorna in un colpo solo, senza rileggere.
$stmtDaily = $mysqli->prepare(
    "INSERT INTO service_status_daily (service, day, checks, failures, degraded, avg_latency_ms, worst_status)
     VALUES (?, DATE(DATE_SUB(NOW(), INTERVAL ? SECOND)), 1, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        checks = checks + 1,
        failures = failures + VALUES(failures),
        degraded = degraded + VALUES(degraded),
        avg_latency_ms = CASE
            WHEN VALUES(avg_latency_ms) IS NULL THEN avg_latency_ms
            WHEN avg_latency_ms IS NULL THEN VALUES(avg_latency_ms)
            ELSE ROUND(((avg_latency_ms * (checks - 1)) + VALUES(avg_latency_ms)) / checks)
        END,
        worst_status = CASE
            WHEN FIELD(VALUES(worst_status), 'operational', 'degraded', 'outage')
                 > FIELD(worst_status, 'operational', 'degraded', 'outage')
            THEN VALUES(worst_status)
            ELSE worst_status
        END"
);

if (!$stmtCheck || !$stmtDaily) {
    bot_fail('Unable to store the checks.', 500);
}

$hasIncidents = auth_table_exists($mysqli, 'service_status_incidents');
$stmtOpen = $stmtIncident = $stmtClose = null;

if ($hasIncidents) {
    $stmtOpen = $mysqli->prepare(
        'SELECT id FROM service_status_incidents
         WHERE service = ? AND ended_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );

    $stmtIncident = $mysqli->prepare(
        "INSERT INTO service_status_incidents (service, status, started_at, error)
         VALUES (?, 'outage', DATE_SUB(NOW(), INTERVAL ? SECOND), ?)"
    );

    // Anche la durata la calcola il database, sulle sue due date: con
    // strtotime() su un orario scritto in un altro fuso venivano fuori
    // disservizi di due ore che non erano mai esistiti.
    $stmtClose = $mysqli->prepare(
        'UPDATE service_status_incidents
         SET ended_at = DATE_SUB(NOW(), INTERVAL ? SECOND),
             duration_seconds = GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, DATE_SUB(NOW(), INTERVAL ? SECOND)))
         WHERE id = ? LIMIT 1'
    );

    $hasIncidents = $stmtOpen && $stmtIncident && $stmtClose;
}

/** Disservizio aperto per servizio: letto una volta, poi seguito qui. */
$openIncidents = [];
$recorded = [];

// Una transazione sola: prima ogni riga era un commit a se', e con gli
// arretrati sarebbero state centinaia di scritture su disco di fila.
$mysqli->begin_transaction();

try {
    foreach (array_slice($checks, 0, STATUS_MAX_CHECKS) as $check) {
        if (!is_array($check)) {
            continue;
        }

        $service = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string)($check['service'] ?? ''))));
        $status = (string)($check['status'] ?? '');

        if ($service === '' || !in_array($status, STATUS_VALUES, true)) {
            continue;
        }

        $latency = isset($check['latency_ms']) ? (int)$check['latency_ms'] : null;
        $httpCode = isset($check['http_code']) ? (int)$check['http_code'] : null;
        $error = isset($check['error']) && $check['error'] !== null
            ? mb_substr((string)$check['error'], 0, 250)
            : null;
        $age = max(0, min(STATUS_MAX_AGE_SECONDS, (int)($check['age_seconds'] ?? 0)));

        $stmtCheck->bind_param('ssiisi', $service, $status, $latency, $httpCode, $error, $age);
        $stmtCheck->execute();

        $isFailure = $status === 'outage' ? 1 : 0;
        $isDegraded = $status === 'degraded' ? 1 : 0;

        $stmtDaily->bind_param('siiiis', $service, $age, $isFailure, $isDegraded, $latency, $status);
        $stmtDaily->execute();

        // Disservizi: uno aperto per servizio, chiuso quando torna operativo.
        if ($hasIncidents) {
            if (!array_key_exists($service, $openIncidents)) {
                $stmtOpen->bind_param('s', $service);
                $stmtOpen->execute();
                $open = $stmtOpen->get_result()->fetch_assoc();
                $openIncidents[$service] = $open ? (int)$open['id'] : null;
            }

            if ($status === 'outage' && $openIncidents[$service] === null) {
                $stmtIncident->bind_param('sis', $service, $age, $error);
                $stmtIncident->execute();
                $openIncidents[$service] = (int)$mysqli->insert_id;
            } elseif ($status !== 'outage' && $openIncidents[$service] !== null) {
                $incidentId = $openIncidents[$service];
                $stmtClose->bind_param('iii', $age, $age, $incidentId);
                $stmtClose->execute();
                $openIncidents[$service] = null;
            }
        }

        $recorded[] = $service;
    }

    $mysqli->commit();
} catch (Throwable $e) {
    $mysqli->rollback();
    error_log('[status/record] ' . $e->getMessage());
    bot_fail('Unable to store the checks.', 500);
}

$stmtCheck->close();
$stmtDaily->close();
if ($hasIncidents) {
    $stmtOpen->close();
    $stmtIncident->close();
    $stmtClose->close();
}

// I controlli grezzi si tengono un mese: lo storico lungo vive nel riepilogo
// giornaliero, che non cresce. Farlo a ogni chiamata voleva dire scorrere la
// tabella intera una volta al minuto; una volta ogni tanto basta, e il LIMIT
// tiene breve anche la prima passata dopo tanto tempo.
if (random_int(1, 60) === 1) {
    $mysqli->query('DELETE FROM service_status_checks WHERE checked_at < DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 5000');
}

bot_json([
    'ok' => true,
    'available' => true,
    // Il bot manda gli arretrati solo a chi lo dichiara: una versione vecchia
    // di questo file li scriverebbe tutti con l'orario di adesso.
    'accepts_age' => true,
    'recorded' => count($recorded),
    'services' => array_values(array_unique($recorded)),
]);
