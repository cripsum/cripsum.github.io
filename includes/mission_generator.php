<?php
/**
 * Cripsum™ — Mission Generator
 * Genera missioni daily/weekly per un utente in modo lazy (solo se non esistono per il periodo).
 * Algoritmo anti-duplicati e anti-incompatibili integrato.
 *
 * @package Cripsum\Missions
 */

defined('ABSPATH') || define('ABSPATH', true); // protezione accesso diretto

// ─────────────────────────────────────────────────────────────
//  COSTANTI CONFIGURAZIONE
// ─────────────────────────────────────────────────────────────

define('MISSIONS_DAILY_COUNT',  5);   // quante daily assegnare per giorno
define('MISSIONS_WEEKLY_COUNT', 3);   // quante weekly assegnare per settimana
define('MISSIONS_MAX_PER_CATEGORIA', 2); // max missioni della stessa categoria per selezione


// ─────────────────────────────────────────────────────────────
//  HELPER — PERIODI
// ─────────────────────────────────────────────────────────────

/**
 * Ritorna la data odierna nel formato DATE per il periodo daily.
 */
function getMissionDailyPeriod(): string
{
    return date('Y-m-d');
}

/**
 * Ritorna il lunedì della settimana corrente come periodo weekly.
 * Usa strtotime per essere compatibile con PHP 7.4+
 */
function getMissionWeeklyPeriod(): string
{
    $monday = strtotime('monday this week');
    // Se oggi è domenica, 'monday this week' può dare lunedì futuro — aggiusta:
    if (date('N') === '7') {
        $monday = strtotime('last monday');
    }
    return date('Y-m-d', $monday);
}

/**
 * Timestamp UTC mezzanotte del giorno dopo (reset daily).
 */
function getDailyResetTimestamp(): int
{
    return mktime(0, 0, 0, (int)date('m'), (int)date('d') + 1, (int)date('Y'));
}

/**
 * Timestamp UTC prossimo lunedì mezzanotte (reset weekly).
 */
function getWeeklyResetTimestamp(): int
{
    $nextMonday = strtotime('next monday');
    if (date('N') === '7') {
        // siamo domenica: next monday è domani
        $nextMonday = strtotime('+1 day');
    }
    return mktime(0, 0, 0,
        (int)date('m', $nextMonday),
        (int)date('d', $nextMonday),
        (int)date('Y', $nextMonday)
    );
}


// ─────────────────────────────────────────────────────────────
//  CORE — CHECK E GENERAZIONE LAZY
// ─────────────────────────────────────────────────────────────

/**
 * Punto di ingresso principale.
 * Assicura che l'utente abbia le missioni del periodo corrente.
 * Se non esistono le genera. Se esistono le ritorna.
 *
 * @param mysqli $mysqli
 * @param int    $userId
 * @param string $tipo    'daily' | 'weekly'
 * @return array  Array di righe user_missions JOIN missions
 */
function ensureUserMissions(mysqli $mysqli, int $userId, string $tipo): array
{
    if ($tipo === 'daily') {
        $periodo = getMissionDailyPeriod();
        $count   = MISSIONS_DAILY_COUNT;
    } else {
        $periodo = getMissionWeeklyPeriod();
        $count   = MISSIONS_WEEKLY_COUNT;
    }

    // ── 1. Controlla se esistono già per questo periodo ──────
    $existing = fetchUserMissionsForPeriod($mysqli, $userId, $tipo, $periodo);

    if (!empty($existing)) {
        return $existing;
    }

    // ── 2. Genera nuove missioni ─────────────────────────────
    $selected = selectMissionsFromPool($mysqli, $tipo, $count);

    if (empty($selected)) {
        // Pool vuoto o troppo pochi — ritorna vuoto senza crashare
        return [];
    }

    // ── 3. Inserisci in user_missions ────────────────────────
    assignMissionsToUser($mysqli, $userId, $selected, $tipo, $periodo);

    // ── 4. Ritorna le missioni appena create ─────────────────
    return fetchUserMissionsForPeriod($mysqli, $userId, $tipo, $periodo);
}


// ─────────────────────────────────────────────────────────────
//  ALGORITMO SELEZIONE DAL POOL
// ─────────────────────────────────────────────────────────────

/**
 * Seleziona N missioni dal pool con:
 *  - shuffle casuale (Fisher-Yates via shuffle() di PHP)
 *  - filtro incompatibilità (via slug JSON)
 *  - filtro per categoria (max MISSIONS_MAX_PER_CATEGORIA per tipo)
 *
 * @param mysqli $mysqli
 * @param string $tipo   'daily' | 'weekly'
 * @param int    $count  quante missioni selezionare
 * @return array  Array di righe missions (id, slug, categoria, incompatibili, ...)
 */
function selectMissionsFromPool(mysqli $mysqli, string $tipo, int $count): array
{
    // Fetch tutto il pool attivo per questo tipo
    $pool = fetchMissionPool($mysqli, $tipo);

    if (empty($pool)) {
        return [];
    }

    // Shuffle casuale
    shuffle($pool);

    $selected        = [];
    $selectedSlugs   = [];
    $categoryCounts  = [];

    foreach ($pool as $mission) {
        if (count($selected) >= $count) {
            break;
        }

        $slug      = $mission['slug'];
        $categoria = $mission['categoria'];

        // ── A. Controlla incompatibilità con già selezionate ──
        $incompatibili = json_decode($mission['incompatibili'] ?? '[]', true);
        if (!is_array($incompatibili)) {
            $incompatibili = [];
        }

        $hasConflict = false;
        foreach ($incompatibili as $incompSlug) {
            if (in_array($incompSlug, $selectedSlugs, true)) {
                $hasConflict = true;
                break;
            }
        }
        if ($hasConflict) {
            continue;
        }

        // ── B. Controlla il limite per categoria ──────────────
        $catCount = $categoryCounts[$categoria] ?? 0;
        if ($catCount >= MISSIONS_MAX_PER_CATEGORIA) {
            continue;
        }

        // ── C. Aggiunge alla selezione ────────────────────────
        $selected[]                  = $mission;
        $selectedSlugs[]             = $slug;
        $categoryCounts[$categoria]  = $catCount + 1;
    }

    return $selected;
}


// ─────────────────────────────────────────────────────────────
//  DATABASE — QUERY HELPERS
// ─────────────────────────────────────────────────────────────

/**
 * Recupera tutto il pool di missioni attive per un tipo.
 */
function fetchMissionPool(mysqli $mysqli, string $tipo): array
{
    $stmt = $mysqli->prepare("
        SELECT id, slug, categoria, titolo, titolo_en, descrizione, descrizione_en,
               icona, obiettivo, punti_reward, difficolta, evento_trigger, incompatibili
        FROM missions
        WHERE tipo = ? AND attiva = 1
        ORDER BY id ASC
    ");
    $stmt->bind_param('s', $tipo);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/**
 * Recupera le missioni assegnate a un utente per un periodo specifico,
 * con JOIN sui dati della missione.
 */
function fetchUserMissionsForPeriod(mysqli $mysqli, int $userId, string $tipo, string $periodo): array
{
    $stmt = $mysqli->prepare("
        SELECT
            um.id            AS user_mission_id,
            um.progresso,
            um.completata,
            um.riscattata,
            um.assigned_at,
            um.completed_at,
            um.claimed_at,
            m.id             AS mission_id,
            m.slug,
            m.categoria,
            m.titolo,
            m.titolo_en,
            m.descrizione,
            m.descrizione_en,
            m.icona,
            m.obiettivo,
            m.punti_reward,
            m.difficolta,
            m.evento_trigger
        FROM user_missions um
        JOIN missions m ON m.id = um.mission_id
        WHERE um.user_id = ?
          AND um.tipo    = ?
          AND um.periodo = ?
        ORDER BY um.id ASC
    ");
    $stmt->bind_param('iss', $userId, $tipo, $periodo);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/**
 * Inserisce le missioni selezionate nella tabella user_missions.
 * Usa INSERT IGNORE per sicurezza anti-race-condition.
 */
function assignMissionsToUser(mysqli $mysqli, int $userId, array $missions, string $tipo, string $periodo): void
{
    $stmt = $mysqli->prepare("
        INSERT IGNORE INTO user_missions (user_id, mission_id, tipo, periodo, progresso, completata, riscattata)
        VALUES (?, ?, ?, ?, 0, 0, 0)
    ");

    foreach ($missions as $mission) {
        $missionId = (int)$mission['id'];
        $stmt->bind_param('iiss', $userId, $missionId, $tipo, $periodo);
        $stmt->execute();
    }

    $stmt->close();
}


// ─────────────────────────────────────────────────────────────
//  UTILITY — DATI PER LA PAGINA
// ─────────────────────────────────────────────────────────────

/**
 * Prepara i dati completi per la risposta API (daily + weekly + timers).
 *
 * @param mysqli $mysqli
 * @param int    $userId
 * @param string $lang   'it' | 'en'
 * @return array
 */
function getMissionsPageData(mysqli $mysqli, int $userId, string $lang = 'it'): array
{
    $dailyRaw  = ensureUserMissions($mysqli, $userId, 'daily');
    $weeklyRaw = ensureUserMissions($mysqli, $userId, 'weekly');

    return [
        'daily'  => [
            'reset_at'  => getDailyResetTimestamp(),
            'periodo'   => getMissionDailyPeriod(),
            'missions'  => localizeMissions($dailyRaw, $lang),
        ],
        'weekly' => [
            'reset_at'  => getWeeklyResetTimestamp(),
            'periodo'   => getMissionWeeklyPeriod(),
            'missions'  => localizeMissions($weeklyRaw, $lang),
        ],
    ];
}

/**
 * Localizza i campi testuali in base alla lingua.
 * Se il campo _en è vuoto, fallback all'italiano.
 */
function localizeMissions(array $missions, string $lang): array
{
    if ($lang !== 'en') {
        return $missions;
    }

    foreach ($missions as &$m) {
        if (!empty($m['titolo_en'])) {
            $m['titolo'] = $m['titolo_en'];
        }
        if (!empty($m['descrizione_en'])) {
            $m['descrizione'] = $m['descrizione_en'];
        }
        unset($m['titolo_en'], $m['descrizione_en']);
    }
    unset($m);

    return $missions;
}
