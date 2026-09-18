<?php
/**
 * Cripsum™ — Mission Generator
 * Genera missioni daily/weekly per un utente in modo lazy (solo se non esistono per il periodo).
 * Algoritmo anti-duplicati e anti-incompatibili integrato.
 *
 * @package Cripsum\Missions
 */

defined('ABSPATH') || define('ABSPATH', true); // protezione accesso diretto

require_once __DIR__ . '/security_helpers.php'; // auth_column_exists()

// ─────────────────────────────────────────────────────────────
//  COSTANTI CONFIGURAZIONE
// ─────────────────────────────────────────────────────────────

define('MISSIONS_DAILY_COUNT',  5);   // quante daily assegnare per giorno
define('MISSIONS_WEEKLY_COUNT', 3);   // quante weekly assegnare per settimana
define('MISSIONS_MAX_PER_CATEGORIA', 2); // max missioni della stessa categoria per selezione

/**
 * Composizione per difficoltà di una selezione.
 *
 * Con un pool di ottanta missioni lo shuffle puro può servire cinque missioni
 * difficili di fila a chi ha appena aperto l'account, o cinque banalità a chi
 * gioca da mesi. La giornata parte sempre da due cose facili e finisce con una
 * difficile; la settimana sale da media a epica.
 *
 * Se il pool non ha abbastanza missioni di una difficoltà, lo slot viene
 * riempito con quello che c'è: la quota è una preferenza, non un requisito.
 */
define('MISSIONS_DAILY_MIX',  ['facile', 'facile', 'media', 'media', 'difficile']);
define('MISSIONS_WEEKLY_MIX', ['media', 'difficile', 'epica']);

/**
 * Per quanti giorni una missione già assegnata resta fuori dal sorteggio.
 *
 * Senza memoria, con cinque estrazioni al giorno su un pool di cinquanta
 * daily, la stessa missione ricapita in media ogni dieci giorni — ma la
 * casualità essendo quella che è, capita anche tre giorni di fila, e sembra
 * che il sistema sia rotto.
 */
define('MISSIONS_DAILY_MEMORY_DAYS',  3);
define('MISSIONS_WEEKLY_MEMORY_DAYS', 14);


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
    $recent   = fetchRecentMissionIds($mysqli, $userId, $tipo, $periodo);
    $selected = selectMissionsFromPool($mysqli, $tipo, $count, $recent);

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
 * Seleziona N missioni dal pool.
 *
 * L'ordine dei filtri conta, e va dal più morbido al più rigido:
 *
 *  1. si tolgono le missioni viste di recente — ma solo se dopo ne restano
 *     abbastanza, altrimenti il filtro si disattiva da solo;
 *  2. il pool viene mescolato tenendo conto del `peso`, così le missioni del
 *     ciclo principale escono più spesso di quelle di nicchia;
 *  3. si riempie uno slot per volta seguendo la quota di difficoltà, e per
 *     ogni slot si prende la prima candidata che non sia incompatibile con le
 *     già scelte e non sfori il tetto per categoria;
 *  4. gli slot rimasti vuoti (difficoltà esaurita) si riempiono con qualsiasi
 *     candidata valida.
 *
 * @param mysqli $mysqli
 * @param string $tipo    'daily' | 'weekly'
 * @param int    $count   quante missioni selezionare
 * @param int[]  $exclude id di missioni viste di recente, da evitare
 * @return array  Array di righe missions (id, slug, categoria, incompatibili, ...)
 */
function selectMissionsFromPool(mysqli $mysqli, string $tipo, int $count, array $exclude = []): array
{
    $pool = fetchMissionPool($mysqli, $tipo);

    if (empty($pool)) {
        return [];
    }

    // ── 1. Memoria delle ultime estrazioni ───────────────────
    // Il filtro salta se lascerebbe il pool troppo magro per comporre una
    // selezione decente: meglio ripetere una missione che darne tre.
    if (!empty($exclude)) {
        $excludeMap = array_flip(array_map('intval', $exclude));
        $filtered   = array_values(array_filter(
            $pool,
            static fn(array $m): bool => !isset($excludeMap[(int)$m['id']])
        ));

        if (count($filtered) >= $count * 2) {
            $pool = $filtered;
        }
    }

    // ── 2. Mescolata pesata ──────────────────────────────────
    shuffleMissionPoolByWeight($pool);

    // ── 3. Slot per difficoltà, poi ── 4. riempimento libero ─
    $mix = $tipo === 'daily' ? MISSIONS_DAILY_MIX : MISSIONS_WEEKLY_MIX;

    // La quota è scritta per i conteggi di default: se qualcuno li cambia,
    // si allunga ripetendo l'ultima difficoltà o si accorcia.
    while (count($mix) < $count) {
        $mix[] = end($mix) ?: 'facile';
    }
    $mix = array_slice($mix, 0, $count);

    $selected       = [];
    $selectedSlugs  = [];
    $bannedSlugs    = [];
    $categoryCounts = [];
    $taken          = [];

    $pick = static function (?string $difficolta) use (
        &$pool, &$selected, &$selectedSlugs, &$bannedSlugs, &$categoryCounts, &$taken
    ): bool {
        foreach ($pool as $index => $mission) {
            if (isset($taken[$index])) {
                continue;
            }
            if ($difficolta !== null && ($mission['difficolta'] ?? '') !== $difficolta) {
                continue;
            }
            if (!missionFitsSelection($mission, $selectedSlugs, $bannedSlugs, $categoryCounts)) {
                continue;
            }

            $taken[$index]   = true;
            $selected[]      = $mission;
            $selectedSlugs[] = $mission['slug'];

            // Da qui in avanti nessuno di questi slug può più entrare.
            foreach (missionIncompatibleSlugs($mission) as $slug) {
                $bannedSlugs[$slug] = true;
            }

            $categoria = $mission['categoria'];
            $categoryCounts[$categoria] = ($categoryCounts[$categoria] ?? 0) + 1;

            return true;
        }

        return false;
    };

    $unfilled = 0;
    foreach ($mix as $difficolta) {
        if (!$pick($difficolta)) {
            $unfilled++;
        }
    }

    for ($i = 0; $i < $unfilled; $i++) {
        if (!$pick(null)) {
            break; // il pool non ha più niente di compatibile
        }
    }

    return $selected;
}

/**
 * Vero se la missione può entrare nella selezione così com'è messa ora.
 *
 * L'incompatibilità va guardata in tutte e due le direzioni, e non è un
 * dettaglio: nel pool è quasi sempre dichiarata da una parte sola. Se
 * `daily_gacha_multi_1` dice di non stare con `daily_lootbox_open_3` ma non
 * viceversa, controllare solo la lista della candidata le fa uscire insieme
 * ogni volta che la seconda viene pescata per prima — cioè metà delle volte.
 *
 * @param array<string,bool> $bannedSlugs slug vietati dalle già selezionate
 */
function missionFitsSelection(array $mission, array $selectedSlugs, array $bannedSlugs, array $categoryCounts): bool
{
    if (($categoryCounts[$mission['categoria']] ?? 0) >= MISSIONS_MAX_PER_CATEGORIA) {
        return false;
    }

    // Direzione 1: qualcuno già dentro ha dichiarato questa come incompatibile.
    if (isset($bannedSlugs[$mission['slug']])) {
        return false;
    }

    // Direzione 2: questa dichiara incompatibile qualcuno già dentro.
    foreach (missionIncompatibleSlugs($mission) as $incompSlug) {
        if (in_array($incompSlug, $selectedSlugs, true)) {
            return false;
        }
    }

    return true;
}

/**
 * Lista di slug incompatibili di una missione, sempre come array di stringhe.
 *
 * Il campo è un testo JSON scritto a mano nella migration: se qualcuno ci
 * mette dentro qualcosa di storto, qui diventa una lista vuota invece di far
 * fallire la generazione della giornata.
 *
 * @return string[]
 */
function missionIncompatibleSlugs(array $mission): array
{
    $decoded = json_decode((string)($mission['incompatibili'] ?? '[]'), true);

    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter($decoded, 'is_string'));
}

/**
 * Mescola il pool tenendo conto del peso di ogni missione.
 *
 * È il campionamento pesato di Efraimidis e Spirakis: a ogni riga si dà la
 * chiave u^(1/peso) con u casuale in (0,1], e si ordina per chiave
 * decrescente. Il risultato è un ordine casuale in cui una missione di peso 3
 * ha tre volte le probabilità di una di peso 1 di trovarsi davanti — senza
 * mai escludere nessuno, che è il motivo per cui non basterebbe ordinare per
 * peso e poi mescolare a gruppi.
 */
function shuffleMissionPoolByWeight(array &$pool): void
{
    $max = mt_getrandmax();

    foreach ($pool as &$mission) {
        $peso = max(1, (int)($mission['peso'] ?? 1));
        $u    = mt_rand(1, $max) / $max; // (0, 1]
        $mission['_sort_key'] = $peso === 1 ? $u : pow($u, 1 / $peso);
    }
    unset($mission);

    usort($pool, static fn(array $a, array $b): int => $b['_sort_key'] <=> $a['_sort_key']);
}


// ─────────────────────────────────────────────────────────────
//  DATABASE — QUERY HELPERS
// ─────────────────────────────────────────────────────────────

/**
 * Recupera tutto il pool di missioni attive per un tipo.
 *
 * `peso` è arrivato con l'espansione del pool: finché la migration non è
 * stata applicata la colonna non esiste, e in quel caso vale 1 per tutti —
 * cioè esattamente il comportamento di prima.
 */
function fetchMissionPool(mysqli $mysqli, string $tipo): array
{
    $peso = auth_column_exists($mysqli, 'missions', 'peso') ? 'peso' : '1 AS peso';

    $stmt = $mysqli->prepare("
        SELECT id, slug, categoria, titolo, titolo_en, descrizione, descrizione_en,
               icona, obiettivo, punti_reward, difficolta, evento_trigger, incompatibili,
               {$peso}
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
 * Missioni già assegnate a questo utente nei periodi immediatamente passati.
 *
 * Serve a non riproporre le stesse tre missioni giorno dopo giorno. Non è un
 * divieto: selectMissionsFromPool() lo ignora se restringerebbe troppo il
 * pool, e un errore qui non deve impedire la generazione.
 *
 * @return int[] id di missions
 */
function fetchRecentMissionIds(mysqli $mysqli, int $userId, string $tipo, string $periodo): array
{
    $days = $tipo === 'daily' ? MISSIONS_DAILY_MEMORY_DAYS : MISSIONS_WEEKLY_MEMORY_DAYS;

    try {
        $stmt = $mysqli->prepare("
            SELECT DISTINCT mission_id
            FROM user_missions
            WHERE user_id = ?
              AND tipo    = ?
              AND periodo >= DATE_SUB(?, INTERVAL ? DAY)
              AND periodo <  ?
        ");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('issis', $userId, $tipo, $periodo, $days, $periodo);
        $stmt->execute();
        $result = $stmt->get_result();

        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['mission_id'];
        }
        $stmt->close();

        return $ids;
    } catch (Throwable $e) {
        error_log('[fetchRecentMissionIds] ' . $e->getMessage());
        return [];
    }
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
function getMissionsPageData(mysqli $mysqli, int $userId, string $lang): array
{
    $dailyRaw  = ensureUserMissions($mysqli, $userId, 'daily');
    $weeklyRaw = ensureUserMissions($mysqli, $userId, 'weekly');

    $isPremium = 0;
    $stmt = $mysqli->prepare("SELECT is_premium FROM utenti WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $isPremium = (int)($res['is_premium'] ?? 0);
        $stmt->close();
    }

    $daily = localizeMissions($dailyRaw, $lang);
    $weekly = localizeMissions($weeklyRaw, $lang);

    if ($isPremium === 1) {
        foreach ($daily as &$m) {
            $m['punti_reward'] = (int)$m['punti_reward'] * 2;
        }
        unset($m);
        foreach ($weekly as &$m) {
            $m['punti_reward'] = (int)$m['punti_reward'] * 2;
        }
        unset($m);
    }

    return [
        'is_premium' => $isPremium,
        'daily'  => [
            'reset_at'  => getDailyResetTimestamp(),
            'periodo'   => getMissionDailyPeriod(),
            'missions'  => $daily,
        ],
        'weekly' => [
            'reset_at'  => getWeeklyResetTimestamp(),
            'periodo'   => getMissionWeeklyPeriod(),
            'missions'  => $weekly,
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
