<?php
/**
 * Cripsum™ — Mission Tracker
 * Funzione centralizzata per aggiornare i progressi delle missioni.
 *
 * USO (in qualsiasi file del sito):
 *   require_once '../includes/mission_tracker.php';
 *   trackMissionProgress($mysqli, $userId, 'lootbox_open');
 *   trackMissionProgress($mysqli, $userId, 'send_message', 1);
 *   trackMissionProgress($mysqli, $userId, 'get_rarity_epic', 1);
 *
 * @package Cripsum\Missions
 */

defined('ABSPATH') || define('ABSPATH', true);

// ─────────────────────────────────────────────────────────────
//  MAPPA DEGLI EVENTI SUPPORTATI
//  Aggiungere qui nuovi eventi senza toccare altro codice.
// ─────────────────────────────────────────────────────────────

// Ogni evento ha un alias human-readable (solo per log/debug).
const MISSION_EVENTS = [
    'lootbox_open'      => 'Apertura lootbox',
    'send_message'      => 'Messaggio inviato',
    'use_global_chat'   => 'Chat globale usata',
    'visit_profile'     => 'Profilo visitato',
    'add_like'          => 'Like aggiunto',
    'edit_profile'      => 'Profilo modificato',
    'view_edit'         => 'Edit visualizzato',
    'download_content'  => 'Contenuto scaricato',
    'get_rarity_rare'   => 'Rarity: rare ottenuto',
    'get_rarity_epic'   => 'Rarity: epic ottenuto',
    'daily_login'       => 'Login giornaliero',
    'view_page'         => 'Pagina visitata',
];


// ─────────────────────────────────────────────────────────────
//  FUNZIONE PRINCIPALE
// ─────────────────────────────────────────────────────────────

/**
 * Aggiorna il progresso delle missioni attive dell'utente
 * che hanno l'evento specificato come trigger.
 *
 * - Solo missioni del periodo corrente (daily → oggi, weekly → settimana corrente)
 * - Solo missioni non ancora completate
 * - Sicuro: nessun valore di progresso arriva dal client
 * - Ritorna array delle missioni completate in questa chiamata (utile per notifiche)
 *
 * @param mysqli $mysqli
 * @param int    $userId    Deve essere > 0
 * @param string $evento    Una delle chiavi di MISSION_EVENTS
 * @param int    $quantita  Incremento (default 1)
 * @return array            Missioni appena completate [{user_mission_id, titolo, punti_reward}]
 */
function trackMissionProgress(mysqli $mysqli, int $userId, string $evento, int $quantita = 1): array
{
    // ── Guardie ───────────────────────────────────────────────
    if ($userId <= 0 || $quantita <= 0) {
        return [];
    }

    if (!array_key_exists($evento, MISSION_EVENTS)) {
        // Evento non registrato — ignora silenziosamente
        return [];
    }

    $oggi       = date('Y-m-d');
    $lunedi     = _getMissionWeeklyPeriodForTracker();
    $newlyDone  = [];

    // ── Cerca tutte le missioni attive che matchano l'evento ──
    // Una singola query per daily + weekly, filtrata per periodo
    $stmt = $mysqli->prepare("
        SELECT
            um.id        AS user_mission_id,
            um.progresso,
            um.tipo,
            m.obiettivo,
            m.punti_reward,
            m.titolo,
            m.slug
        FROM user_missions um
        JOIN missions m ON m.id = um.mission_id
        WHERE um.user_id     = ?
          AND m.evento_trigger = ?
          AND um.completata  = 0
          AND um.riscattata  = 0
          AND (
                (um.tipo = 'daily'  AND um.periodo = ?)
             OR (um.tipo = 'weekly' AND um.periodo = ?)
          )
        FOR UPDATE
    ");

    // Usa transazione per consistenza e anti-race-condition
    $mysqli->begin_transaction();

    try {
        $stmt->bind_param('isss', $userId, $evento, $oggi, $lunedi);
        $stmt->execute();
        $result = $stmt->get_result();

        $toUpdate = [];
        while ($row = $result->fetch_assoc()) {
            $toUpdate[] = $row;
        }
        $stmt->close();

        if (empty($toUpdate)) {
            $mysqli->commit();
            return [];
        }

        // ── Aggiorna ogni missione trovata ────────────────────
        $updateStmt = $mysqli->prepare("
            UPDATE user_missions
            SET progresso    = ?,
                completata   = ?,
                completed_at = IF(? = 1 AND completed_at IS NULL, NOW(), completed_at)
            WHERE id = ?
        ");

        foreach ($toUpdate as $mission) {
            $nuovoProgresso = min(
                (int)$mission['progresso'] + $quantita,
                (int)$mission['obiettivo']
            );
            $completata = ($nuovoProgresso >= (int)$mission['obiettivo']) ? 1 : 0;

            $updateStmt->bind_param('iiii',
                $nuovoProgresso,
                $completata,
                $completata,
                $mission['user_mission_id']
            );
            $updateStmt->execute();

            // Se appena completata (non lo era prima) → aggiungi ai risultati
            if ($completata === 1 && (int)$mission['progresso'] < (int)$mission['obiettivo']) {
                $newlyDone[] = [
                    'user_mission_id' => (int)$mission['user_mission_id'],
                    'titolo'          => $mission['titolo'],
                    'punti_reward'    => (int)$mission['punti_reward'],
                    'slug'            => $mission['slug'],
                ];
            }
        }

        $updateStmt->close();
        $mysqli->commit();

    } catch (Exception $e) {
        $mysqli->rollback();
        error_log('[MissionTracker] Errore su evento "' . $evento . '" user ' . $userId . ': ' . $e->getMessage());
        return [];
    }

    return $newlyDone;
}


// ─────────────────────────────────────────────────────────────
//  HELPER — LOGIN GIORNALIERO (chiama una volta per sessione)
// ─────────────────────────────────────────────────────────────

/**
 * Traccia il login giornaliero dell'utente.
 * Chiama questa funzione all'inizio della sessione (es. session_init.php o navbar).
 * Usa SESSION per evitare tracciamenti multipli nella stessa sessione.
 *
 * @param mysqli $mysqli
 * @param int    $userId
 */
function trackDailyLogin(mysqli $mysqli, int $userId): void
{
    $todayKey = 'mission_login_tracked_' . date('Ymd');

    if (!empty($_SESSION[$todayKey])) {
        return; // già tracciato oggi in questa sessione
    }

    trackMissionProgress($mysqli, $userId, 'daily_login', 1);
    $_SESSION[$todayKey] = true;
}


// ─────────────────────────────────────────────────────────────
//  INTERNAL HELPER
// ─────────────────────────────────────────────────────────────

/**
 * Calcola il lunedì della settimana corrente (usato internamente).
 * Duplicato locale per non dipendere da mission_generator.php in ogni file.
 */
function _getMissionWeeklyPeriodForTracker(): string
{
    $monday = strtotime('monday this week');
    if (date('N') === '7') {
        $monday = strtotime('last monday');
    }
    return date('Y-m-d', $monday);
}
