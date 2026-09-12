<?php
declare(strict_types=1);

/**
 * Registra i controlli di stato fatti dal bot.
 *
 * Ogni controllo diventa una riga, un aggiornamento del riepilogo giornaliero
 * e — quando un servizio cade o torna — l'apertura o la chiusura di un
 * disservizio. E' da qui che la pagina /status prende uptime e cronologia:
 * prima le barre dei novanta giorni erano verdi per finta.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'service_status_checks')) {
    bot_json(['ok' => true, 'available' => false]);
}

const STATUS_VALUES = ['operational', 'degraded', 'outage'];


$body = bot_input();
$checks = is_array($body['checks'] ?? null) ? $body['checks'] : [];

if (!$checks) {
    bot_fail('No checks submitted.', 400);
}

$recorded = [];

/**
 * Gli orari li mette il database, mai PHP.
 *
 * Con `date()` l'ora veniva dal fuso di PHP e poi finiva confrontata con NOW()
 * di MySQL: se i due non coincidono — ed e' il caso normale, PHP sull'ora di
 * Roma e MySQL su UTC — ogni controllo risultava scritto due ore nel futuro.
 * La pagina mostrava "ultimo controllo -7199 s fa", e peggio: il controllo di
 * freschezza non scattava mai, quindi con il bot fermo restava tutto verde.
 * Con un orologio solo il problema non puo' ripresentarsi.
 */
$stmtCheck = $mysqli->prepare(
    'INSERT INTO service_status_checks (service, status, latency_ms, http_code, error, checked_at)
     VALUES (?, ?, ?, ?, ?, NOW())'
);

if (!$stmtCheck) {
    bot_fail('Unable to store the checks.', 500);
}

foreach (array_slice($checks, 0, 20) as $check) {
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

    $stmtCheck->bind_param('ssiis', $service, $status, $latency, $httpCode, $error);
    $stmtCheck->execute();

    // Riepilogo del giorno: si aggiorna in un colpo solo, senza rileggere.
    $isFailure = $status === 'outage' ? 1 : 0;
    $isDegraded = $status === 'degraded' ? 1 : 0;

    $stmtDaily = $mysqli->prepare(
        "INSERT INTO service_status_daily (service, day, checks, failures, degraded, avg_latency_ms, worst_status)
         VALUES (?, CURDATE(), 1, ?, ?, ?, ?)
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

    if ($stmtDaily) {
        $stmtDaily->bind_param('siiis', $service, $isFailure, $isDegraded, $latency, $status);
        $stmtDaily->execute();
        $stmtDaily->close();
    }

    // Disservizi: uno aperto per servizio, chiuso quando torna operativo.
    if (auth_table_exists($mysqli, 'service_status_incidents')) {
        $stmtOpen = $mysqli->prepare(
            'SELECT id FROM service_status_incidents
             WHERE service = ? AND ended_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );

        $open = null;
        if ($stmtOpen) {
            $stmtOpen->bind_param('s', $service);
            $stmtOpen->execute();
            $open = $stmtOpen->get_result()->fetch_assoc();
            $stmtOpen->close();
        }

        if ($status === 'outage' && !$open) {
            $stmtIncident = $mysqli->prepare(
                "INSERT INTO service_status_incidents (service, status, started_at, error)
                 VALUES (?, 'outage', NOW(), ?)"
            );

            if ($stmtIncident) {
                $stmtIncident->bind_param('ss', $service, $error);
                $stmtIncident->execute();
                $stmtIncident->close();
            }
        } elseif ($status !== 'outage' && $open) {
            $incidentId = (int)$open['id'];

            // Anche la durata la calcola il database, sulle sue due date: con
            // strtotime() su un orario scritto in un altro fuso venivano fuori
            // disservizi di due ore che non erano mai esistiti.
            $stmtClose = $mysqli->prepare(
                'UPDATE service_status_incidents
                 SET ended_at = NOW(),
                     duration_seconds = GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, NOW()))
                 WHERE id = ? LIMIT 1'
            );

            if ($stmtClose) {
                $stmtClose->bind_param('i', $incidentId);
                $stmtClose->execute();
                $stmtClose->close();
            }
        }
    }

    $recorded[] = $service;
}

$stmtCheck->close();

// I controlli grezzi si tengono un mese: lo storico lungo vive nel riepilogo
// giornaliero, che non cresce.
$mysqli->query('DELETE FROM service_status_checks WHERE checked_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');

bot_json([
    'ok' => true,
    'available' => true,
    'recorded' => count($recorded),
    'services' => $recorded,
]);
