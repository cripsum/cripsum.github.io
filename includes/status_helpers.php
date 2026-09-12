<?php
declare(strict_types=1);

/**
 * Lettura dello stato dei servizi per la pagina /status.
 *
 * Tutto qui dentro deve funzionare anche con il database irraggiungibile: e'
 * il caso in cui la pagina serve di piu'. Per questo la connessione si apre a
 * parte invece di usare config/database.php, che termina l'esecuzione se il
 * database non risponde.
 */

/**
 * Le credenziali si caricano qui, a livello globale.
 *
 * Farlo dentro una funzione le renderebbe variabili locali, e il
 * `require_once` segnerebbe comunque il file come gia' incluso: il successivo
 * config/database.php lo salterebbe e resterebbe senza credenziali, morendo
 * con "Access denied". Da fuori sembra un problema di password.
 */
$statusConfigPath = __DIR__ . '/../secure/config.php';
if (is_file($statusConfigPath)) {
    require_once $statusConfigPath;
}
unset($statusConfigPath);

/**
 * Stesso fuso di config/database.php, che questa pagina non include.
 *
 * Chi scrive i controlli passa da li' e si porta dietro `SET time_zone`; questa
 * pagina apre una connessione sua, che senza la stessa riga userebbe il fuso
 * predefinito del server MySQL. Con i due orologi disallineati l'eta' di un
 * controllo veniva negativa — il famoso "ultimo controllo -7199 s fa" — e il
 * controllo di freschezza non scattava mai, quindi a bot spento la pagina
 * restava tutta verde.
 */
date_default_timezone_set('Europe/Rome');

/** Ogni quanto il bot dovrebbe farsi vivo: oltre, si considera fermo. */
const STATUS_STALE_SECONDS = 300;

/** Servizi mostrati, nell'ordine in cui compaiono. */
function status_services(): array
{
    return [
        'website'  => ['label' => 'Sito web',            'hint' => 'cripsum.com',        'icon' => 'fa-globe'],
        'database' => ['label' => 'Database',            'hint' => 'account e contenuti', 'icon' => 'fa-database'],
        'storage'  => ['label' => 'Archiviazione file',  'hint' => 'immagini e allegati', 'icon' => 'fa-folder-open'],
        'bot_api'  => ['label' => 'API Rich Presence',   'hint' => 'api.cripsum.com',     'icon' => 'fa-code'],
        'discord'  => ['label' => 'Bot Discord',         'hint' => 'Poppy',               'icon' => 'fa-robot'],
    ];
}

/**
 * Connessione dedicata, che non uccide la pagina se fallisce.
 *
 * @return mysqli|null
 */
function status_connect(): ?mysqli
{
    static $link = false;

    if ($link !== false) {
        return $link;
    }

    $link = null;

    global $db_host, $db_user, $db_pass, $db_name;

    if (!isset($db_host, $db_user, $db_pass, $db_name)) {
        return null;
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    // Timeout corto: se il database non risponde la pagina deve dirlo subito,
    // non restare appesa il tempo di un timeout di sistema.
    $candidate = mysqli_init();
    if (!$candidate) {
        return null;
    }

    @$candidate->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    @$candidate->options(MYSQLI_OPT_READ_TIMEOUT, 3);

    if (!@$candidate->real_connect($db_host, $db_user, $db_pass, $db_name) || $candidate->connect_errno) {
        return null;
    }

    @$candidate->set_charset('utf8mb4');

    // La stessa riga di config/database.php: leggere e scrivere devono usare
    // lo stesso orologio, altrimenti ogni durata calcolata qui e' sbagliata
    // dell'offset fra i due fusi.
    @$candidate->query("SET time_zone = '" . date('P') . "'");

    $link = $candidate;

    return $link;
}

function status_table_exists(mysqli $link, string $table): bool
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $link->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) {
        return $cache[$table] = false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $cache[$table] = $exists;
}

/**
 * Ultimo controllo registrato per ogni servizio.
 *
 * @return array<string, array<string, mixed>>
 */
function status_latest_checks(?mysqli $link): array
{
    if (!$link || !status_table_exists($link, 'service_status_checks')) {
        return [];
    }

    $sql = "SELECT c.service, c.status, c.latency_ms, c.error, c.checked_at,
                   TIMESTAMPDIFF(SECOND, c.checked_at, NOW()) AS age
            FROM service_status_checks c
            INNER JOIN (
                SELECT service, MAX(id) AS max_id
                FROM service_status_checks
                GROUP BY service
            ) ultimo ON ultimo.max_id = c.id";

    $result = $link->query($sql);
    if (!$result) {
        return [];
    }

    $checks = [];
    while ($row = $result->fetch_assoc()) {
        // Un'eta' negativa vuol dire che il controllo risulta scritto nel
        // futuro: succede solo se chi scrive e chi legge usano due orologi
        // diversi. Ora l'ora la mette il database in entrambi i casi, ma se
        // ricapitasse va trattata come "appena arrivato" e non come un numero
        // negativo mostrato all'utente — ne' come un dato eternamente fresco,
        // che era il motivo per cui il sito restava verde con il bot spento.
        $age = (int)$row['age'];

        $checks[(string)$row['service']] = [
            'status' => (string)$row['status'],
            'latency_ms' => $row['latency_ms'] !== null ? (int)$row['latency_ms'] : null,
            'error' => $row['error'],
            'checked_at' => (string)$row['checked_at'],
            'age' => max(0, $age),
            'clock_skew' => $age < -60,
        ];
    }
    $result->free();

    return $checks;
}

/**
 * Riepilogo giornaliero degli ultimi $days giorni, per servizio.
 *
 * @return array<string, array<string, array<string, mixed>>>
 */
function status_daily(?mysqli $link, int $days = 90): array
{
    if (!$link || !status_table_exists($link, 'service_status_daily')) {
        return [];
    }

    $days = max(1, min(365, $days));
    $stmt = $link->prepare(
        'SELECT service, day, checks, failures, degraded, worst_status, avg_latency_ms
         FROM service_status_daily
         WHERE day >= DATE_SUB(CURDATE(), INTERVAL ? DAY)'
    );

    if (!$stmt) {
        return [];
    }

    $window = $days - 1;
    $stmt->bind_param('i', $window);
    $stmt->execute();
    $result = $stmt->get_result();

    $daily = [];
    while ($row = $result->fetch_assoc()) {
        $daily[(string)$row['service']][(string)$row['day']] = [
            'checks' => (int)$row['checks'],
            'failures' => (int)$row['failures'],
            'degraded' => (int)$row['degraded'],
            'worst' => (string)$row['worst_status'],
            'latency' => $row['avg_latency_ms'] !== null ? (int)$row['avg_latency_ms'] : null,
        ];
    }
    $stmt->close();

    return $daily;
}

/**
 * Disservizi recenti, il piu' recente per primo.
 *
 * @return array<int, array<string, mixed>>
 */
function status_incidents(?mysqli $link, int $limit = 12): array
{
    if (!$link || !status_table_exists($link, 'service_status_incidents')) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $result = $link->query(
        "SELECT service, status, started_at, ended_at, duration_seconds, error
         FROM service_status_incidents
         ORDER BY started_at DESC
         LIMIT $limit"
    );

    if (!$result) {
        return [];
    }

    $incidents = [];
    while ($row = $result->fetch_assoc()) {
        $incidents[] = [
            'service' => (string)$row['service'],
            'status' => (string)$row['status'],
            'started_at' => (string)$row['started_at'],
            'ended_at' => $row['ended_at'],
            'duration' => $row['duration_seconds'] !== null ? (int)$row['duration_seconds'] : null,
            'error' => $row['error'],
        ];
    }
    $result->free();

    return $incidents;
}

/**
 * Stato attuale di un servizio, tenendo conto di quanto e' vecchio il dato.
 *
 * Un controllo vecchio non vale come "tutto bene": vuol dire che il
 * controllore non sta piu' scrivendo, e questo va detto invece di mostrare un
 * verde rassicurante e falso.
 */
function status_current(array $checks, string $service): array
{
    if (!isset($checks[$service])) {
        return ['status' => 'unknown', 'latency_ms' => null, 'error' => null, 'age' => null];
    }

    $check = $checks[$service];

    if ($check['age'] > STATUS_STALE_SECONDS) {
        return [
            // Niente latenza: e' quella dell'ultimo controllo riuscito, e
            // mostrare un numero accanto a "Sconosciuto" farebbe credere che
            // qualcuno l'abbia misurata adesso.
            'status' => 'unknown',
            'latency_ms' => null,
            'error' => 'nessun controllo recente',
            'age' => $check['age'],
        ];
    }

    return $check;
}

/**
 * Stato del controllore, che e' cosa diversa dallo stato dei servizi.
 *
 * I controlli li scrive il bot, che gira su un'altra macchina. Se smettono di
 * arrivare, di cripsum.com non si sa piu' niente: l'ultimo dato letto resta
 * verde ma non vale piu'. Questa distinzione va mostrata, altrimenti la pagina
 * rassicura proprio quando non dovrebbe.
 *
 * @return array{age:?int, stale:bool, never:bool, skew:bool}
 */
function status_monitor(array $checks): array
{
    $newest = null;
    $skew = false;

    foreach ($checks as $check) {
        if ($newest === null || $check['age'] < $newest) {
            $newest = $check['age'];
        }
        if (!empty($check['clock_skew'])) {
            $skew = true;
        }
    }

    return [
        'age' => $newest,
        'never' => $newest === null,
        'stale' => $newest !== null && $newest > STATUS_STALE_SECONDS,
        'skew' => $skew,
    ];
}

/**
 * Percentuale di uptime su una finestra di giorni.
 */
function status_uptime(array $daily, string $service, int $days = 90): ?float
{
    $rows = $daily[$service] ?? [];
    if (!$rows) {
        return null;
    }

    $checks = 0;
    $failures = 0;

    foreach ($rows as $row) {
        $checks += $row['checks'];
        $failures += $row['failures'];
    }

    return $checks > 0 ? (($checks - $failures) / $checks) * 100 : null;
}

function status_format_duration(?int $seconds): string
{
    if ($seconds === null) {
        return '—';
    }

    if ($seconds <= 0) {
        return 'pochi istanti';
    }

    if ($seconds < 60) {
        return $seconds . ' s';
    }

    if ($seconds < 3600) {
        return round($seconds / 60) . ' min';
    }

    if ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        $minutes = round(($seconds % 3600) / 60);
        return $minutes > 0 ? "{$hours} h {$minutes} min" : "{$hours} h";
    }

    return round($seconds / 86400, 1) . ' giorni';
}

function status_label(string $status): string
{
    return [
        'operational' => 'Operativo',
        'degraded' => 'Rallentato',
        'outage' => 'Non raggiungibile',
        'unknown' => 'Sconosciuto',
    ][$status] ?? 'Sconosciuto';
}

/**
 * Lo stato complessivo e' il peggiore fra quelli dei servizi.
 */
function status_overall(array $states): string
{
    $rank = ['operational' => 0, 'unknown' => 1, 'degraded' => 2, 'outage' => 3];
    $worst = 'operational';

    foreach ($states as $state) {
        if (($rank[$state['status']] ?? 0) > ($rank[$worst] ?? 0)) {
            $worst = $state['status'];
        }
    }

    return $worst;
}
