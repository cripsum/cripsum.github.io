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
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

$stmtCheck = $mysqli->prepare(
    'INSERT INTO service_status_checks (service, status, latency_ms, http_code, error, checked_at)
     VALUES (?, ?, ?, ?, ?, ?)'
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

    $stmtCheck->bind_param('ssiiss', $service, $status, $latency, $httpCode, $error, $now);
    $stmtCheck->execute();

    // Riepilogo del giorno: si aggiorna in un colpo solo, senza rileggere.
    $isFailure = $status === 'outage' ? 1 : 0;
    $isDegraded = $status === 'degraded' ? 1 : 0;

    $stmtDaily = $mysqli->prepare(
        "INSERT INTO service_status_daily (service, day, checks, failures, degraded, avg_latency_ms, worst_status)
         VALUES (?, ?, 1, ?, ?, ?, ?)
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
        $stmtDaily->bind_param('ssiiis', $service, $today, $isFailure, $isDegraded, $latency, $status);
        $stmtDaily->execute();
        $stmtDaily->close();
    }

    // Disservizi: uno aperto per servizio, chiuso quando torna operativo.
    if (auth_table_exists($mysqli, 'service_status_incidents')) {
        $stmtOpen = $mysqli->prepare(
            'SELECT id, started_at FROM service_status_incidents
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
                 VALUES (?, 'outage', ?, ?)"
            );

            if ($stmtIncident) {
                $stmtIncident->bind_param('sss', $service, $now, $error);
                $stmtIncident->execute();
                $stmtIncident->close();
            }
        } elseif ($status !== 'outage' && $open) {
            $duration = max(0, strtotime($now) - strtotime((string)$open['started_at']));
            $incidentId = (int)$open['id'];

            $stmtClose = $mysqli->prepare(
                'UPDATE service_status_incidents
                 SET ended_at = ?, duration_seconds = ?
                 WHERE id = ? LIMIT 1'
            );

            if ($stmtClose) {
                $stmtClose->bind_param('sii', $now, $duration, $incidentId);
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
