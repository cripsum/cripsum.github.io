<?php
/*
 * Helper condivisi dalle API di Subway Surfers (start_run, save_score).
 *
 * Una run nasce con start_run, che la registra in sessione con l'orario del
 * server, e si chiude con save_score. L'id della run lo genera il client:
 * cosi' il salvataggio puo' partire (o ripartire dopo un reload) anche se la
 * risposta di start_run non e' mai arrivata. In sessione restano piu' run
 * aperte insieme (una run nuova non cancella quella che si sta ancora
 * salvando, e due schede non si pestano i piedi) e l'elenco delle ultime run
 * salvate, per rispondere allo stesso modo ai reinvii senza contare due volte
 * statistiche e missioni.
 */

const SUBWAY_MAPS = [
    'bangkok', 'barcelona', 'beijing', 'berlin', 'buenosaires', 'cairo',
    'havana', 'hongkong', 'houston', 'iceland', 'london', 'mexico', 'miami',
    'monaco', 'moscow', 'neworleans', 'newyork', 'paris', 'rio',
    'saintpetersburg', 'sanfrancisco', 'tokyo', 'venice', 'winterholiday',
    'zurich',
];

const SUBWAY_MODES = ['original', 'training'];

// Quante run aperte e quanti esiti tenere in sessione.
const SUBWAY_MAX_OPEN_RUNS = 8;
const SUBWAY_MAX_DONE_RUNS = 20;

// Di quanto il client puo' dire di aver avviato la run prima che start_run
// arrivasse al server (rete lenta, tentativi ripetuti).
const SUBWAY_MAX_START_OFFSET_MS = 120000;

function subway_normalize_map($slug): string
{
    $slug = strtolower(trim((string)$slug));
    return in_array($slug, SUBWAY_MAPS, true) ? $slug : '';
}

function subway_valid_run_id($runId): bool
{
    return is_string($runId) && preg_match('/^[a-f0-9]{32}$/', $runId) === 1;
}

/**
 * Risponde con un errore JSON e lo scrive nel log del server, con il motivo e
 * il contesto: e' da qui che si capisce perche' un record non e' entrato.
 */
function subway_fail(int $http, string $code, string $message, string $endpoint, array $context = []): void
{
    $parts = [];
    foreach ($context as $key => $value) {
        $parts[] = $key . '=' . (is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value));
    }
    error_log(sprintf('[Subway %s] rifiuto %s (%d) %s', $endpoint, $code, $http, implode(' ', $parts)));

    http_response_code($http);
    echo json_encode([
        'status' => 'error',
        'code' => $code,
        'message' => $message,
    ]);
    exit();
}

function subway_register_run(string $runId, string $map, string $mode, float $startedAt): array
{
    if (!isset($_SESSION['subway_runs']) || !is_array($_SESSION['subway_runs'])) {
        $_SESSION['subway_runs'] = [];
    }

    // Un secondo start_run con lo stesso id e' un tentativo ripetuto: vale il
    // primo orario, altrimenti il tempo concesso si accorcerebbe.
    if (!isset($_SESSION['subway_runs'][$runId])) {
        $_SESSION['subway_runs'][$runId] = [
            'start' => $startedAt,
            'map' => $map,
            'mode' => $mode,
        ];
    }

    if (count($_SESSION['subway_runs']) > SUBWAY_MAX_OPEN_RUNS) {
        uasort($_SESSION['subway_runs'], fn($a, $b) => $b['start'] <=> $a['start']);
        $_SESSION['subway_runs'] = array_slice($_SESSION['subway_runs'], 0, SUBWAY_MAX_OPEN_RUNS, true);
    }

    // Residui del vecchio formato a token singolo.
    unset($_SESSION['subway_run_token'], $_SESSION['subway_run_start'], $_SESSION['subway_run_map']);

    return $_SESSION['subway_runs'][$runId];
}

function subway_open_run(string $runId): ?array
{
    $run = $_SESSION['subway_runs'][$runId] ?? null;
    return is_array($run) ? $run : null;
}

function subway_done_run(string $runId): ?array
{
    $done = $_SESSION['subway_done'][$runId] ?? null;
    return is_array($done) ? $done : null;
}

function subway_close_run(string $runId, ?array $result): void
{
    unset($_SESSION['subway_runs'][$runId]);
    if ($result === null) {
        return;
    }
    if (!isset($_SESSION['subway_done']) || !is_array($_SESSION['subway_done'])) {
        $_SESSION['subway_done'] = [];
    }
    $_SESSION['subway_done'][$runId] = $result;
    if (count($_SESSION['subway_done']) > SUBWAY_MAX_DONE_RUNS) {
        $_SESSION['subway_done'] = array_slice($_SESSION['subway_done'], -SUBWAY_MAX_DONE_RUNS, null, true);
    }
}

/**
 * L'upsert atomico richiede un indice UNIQUE sul solo utente_id: senza,
 * ON DUPLICATE KEY non scatta mai e ogni run aggiungerebbe una riga.
 */
function subway_has_unique_user_key(mysqli $mysqli): bool
{
    $res = $mysqli->query("SHOW INDEX FROM subway_leaderboard");
    if (!$res) {
        return false;
    }
    $indexes = [];
    while ($row = $res->fetch_assoc()) {
        $indexes[$row['Key_name']]['unique'] = (int)$row['Non_unique'] === 0;
        $indexes[$row['Key_name']]['columns'][] = $row['Column_name'];
    }
    $res->free();
    foreach ($indexes as $index) {
        if ($index['unique'] && $index['columns'] === ['utente_id']) {
            return true;
        }
    }
    return false;
}

/**
 * Salva il tempo tenendo il migliore. Ritorna [nuovo record?, record attuale,
 * mappa del record].
 */
function subway_store_best(mysqli $mysqli, int $userId, int $timeMs, string $map): array
{
    if (subway_has_unique_user_key($mysqli)) {
        // Le assegnazioni si valutano da sinistra a destra: best_time_ms va
        // aggiornato per ultimo, dopo i confronti che leggono il valore vecchio.
        $stmt = $mysqli->prepare("
            INSERT INTO subway_leaderboard (utente_id, best_time_ms, map_slug, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                map_slug = IF(VALUES(best_time_ms) > best_time_ms, VALUES(map_slug), map_slug),
                updated_at = IF(VALUES(best_time_ms) > best_time_ms, NOW(), updated_at),
                best_time_ms = GREATEST(best_time_ms, VALUES(best_time_ms))
        ");
        $stmt->bind_param('iis', $userId, $timeMs, $map);
        $stmt->execute();
        // 1 = riga nuova, 2 = record migliorato, 0 = niente di cambiato.
        $isNewBest = $stmt->affected_rows > 0;
        $stmt->close();
    } else {
        $upd = $mysqli->prepare("
            UPDATE subway_leaderboard
            SET best_time_ms = ?, map_slug = ?, updated_at = NOW()
            WHERE utente_id = ? AND best_time_ms < ?
        ");
        $upd->bind_param('isii', $timeMs, $map, $userId, $timeMs);
        $upd->execute();
        $isNewBest = $upd->affected_rows > 0;
        $upd->close();

        if (!$isNewBest) {
            $chk = $mysqli->prepare("SELECT 1 FROM subway_leaderboard WHERE utente_id = ? LIMIT 1");
            $chk->bind_param('i', $userId);
            $chk->execute();
            $exists = (bool)$chk->get_result()->fetch_row();
            $chk->close();
            if (!$exists) {
                $ins = $mysqli->prepare("
                    INSERT INTO subway_leaderboard (utente_id, best_time_ms, map_slug, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $ins->bind_param('iis', $userId, $timeMs, $map);
                $ins->execute();
                $ins->close();
                $isNewBest = true;
            }
        }
    }

    $sel = $mysqli->prepare("SELECT best_time_ms, map_slug FROM subway_leaderboard WHERE utente_id = ? ORDER BY best_time_ms DESC LIMIT 1");
    $sel->bind_param('i', $userId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc() ?: [];
    $sel->close();

    return [$isNewBest, (int)($row['best_time_ms'] ?? $timeMs), (string)($row['map_slug'] ?? $map)];
}
