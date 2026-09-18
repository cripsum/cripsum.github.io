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

require_once __DIR__ . '/mission_generator.php';
require_once __DIR__ . '/stats_tracker.php';

// ─────────────────────────────────────────────────────────────
//  MAPPA DEGLI EVENTI SUPPORTATI
//  Aggiungere qui nuovi eventi senza toccare altro codice.
// ─────────────────────────────────────────────────────────────

// Ogni evento ha un alias human-readable (solo per log/debug).
const MISSION_EVENTS = [
    // ── Gacha e collezione ────────────────────────────────────
    'lootbox_open'       => 'Apertura lootbox',
    'gacha_multi_pull'   => 'Multi-pull da 10',
    'gacha_new_char'     => 'Personaggio mai posseduto',
    'get_rarity_rare'    => 'Rarity: raro ottenuto',
    'get_rarity_epic'    => 'Rarity: epico ottenuto',
    'get_rarity_special' => 'Rarity: speciale ottenuto',
    'get_rarity_secret'  => 'Rarity: segreto ottenuto',

    // ── Chat e social ─────────────────────────────────────────
    'send_message'         => 'Messaggio inviato',
    'send_private_message' => 'Messaggio privato inviato',
    'send_group_message'   => 'Messaggio di gruppo inviato',
    'use_global_chat'      => 'Chat globale usata',
    'add_friend'           => 'Amicizia stretta',
    'visit_profile'        => 'Profilo visitato',

    // ── Contenuti ─────────────────────────────────────────────
    'add_like'          => 'Reazione aggiunta',
    'like_shitpost'     => 'Like a uno shitpost',
    'vote_rimasti'      => 'Voto a un Top Rimasti',
    'create_post'       => 'Post pubblicato',
    'create_shitpost'   => 'Shitpost pubblicato',
    'comment_post'      => 'Commento lasciato',
    'view_edit'         => 'Edit visualizzato',
    'download_content'  => 'Contenuto scaricato',

    // ── Giochi ────────────────────────────────────────────────
    'play_subway'          => 'Corsa su Subway',
    'play_pullspot'        => 'Round di Pullspot',
    'win_pullspot'         => 'Pullspot indovinato',
    'pullspot_first_try'   => 'Pullspot al primo tentativo',
    'play_animespot'       => 'Sigla di Animespot giocata',
    'win_animespot'        => 'Sigla indovinata',
    'animespot_first_try'  => 'Sigla al primo tentativo',
    'play_duel'            => 'Duello giocato',
    'win_duel'             => 'Duello vinto',
    'upgrade_character'    => 'Personaggio potenziato',

    // ── Profilo, progressione ed economia ─────────────────────
    'edit_profile'       => 'Profilo modificato',
    'unlock_achievement' => 'Achievement sbloccato',
    'claim_mission'      => 'Ricompensa missione riscattata',
    'shop_purchase'      => 'Acquisto al negozio',
    'convert_shards'     => 'Godos convertiti in Shards',

    // ── Presenza ──────────────────────────────────────────────
    'daily_login'   => 'Login giornaliero',
    'view_page'     => 'Pagina visitata',
    'visit_section' => 'Sezione del sito aperta',
];

/**
 * Sezioni del sito che valgono una missione «vai a vedere».
 *
 * La chiave è quella di STATS_PAGE_KEYS (stats_page_key_from_path), il valore
 * è l'evento missione. L'aggancio è uno solo, in api/update_activity.php: il
 * battito di presenza conosce già la sezione su cui sta l'utente, quindi
 * queste missioni non richiedono di toccare nemmeno una pagina del sito.
 *
 * Chi arriva qui è già passato dalla whitelist di stats_page_key_from_path,
 * quindi la chiave non è mai una stringa arbitraria del client.
 */
const MISSION_SECTION_EVENTS = [
    'cripsumpedia'  => 'visit_cripsumpedia',
    'rimasti'       => 'visit_rimasti',
    'tiktokpedia'   => 'visit_tiktokpedia',
    'inventario'    => 'visit_inventario',
    'achievements'  => 'visit_achievements',
    'negozio'       => 'visit_negozio',
];

/**
 * Vero se l'evento può far avanzare una missione.
 *
 * Gli eventi di sezione non stanno in MISSION_EVENTS perché sono generati
 * dalla mappa qui sopra: tenerli in due posti vorrebbe dire dimenticarsene
 * in uno dei due alla prossima sezione aggiunta.
 */
function mission_event_exists(string $evento): bool
{
    return array_key_exists($evento, MISSION_EVENTS)
        || in_array($evento, MISSION_SECTION_EVENTS, true);
}


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

    if (!mission_event_exists($evento)) {
        // Evento non registrato — ignora silenziosamente
        return [];
    }

    // ── Statistiche Rewind ────────────────────────────────────
    // Aggangiate qui e non nei singoli file: ogni chiamata al tracker delle
    // missioni alimenta anche le statistiche annuali, senza toccare i quindici
    // punti del sito che già invocano questa funzione.
    trackStatsForMissionEvent($mysqli, $userId, $evento, $quantita);

    // Inizializza le missioni per oggi/questa settimana se non ancora fatto in
    // questa sessione.
    //
    // La chiave porta l'id utente perché alcuni eventi riguardano qualcun
    // altro: accettare un'amicizia fa avanzare anche le missioni di chi l'ha
    // chiesta. Con una chiave sola, il primo dei due a passare di qui
    // impediva la generazione delle missioni dell'altro, e il suo progresso
    // finiva nel vuoto.
    if (isset($_SESSION)) {
        $initKey = 'missions_initialized_' . $userId . '_' . date('Ymd');
        if (empty($_SESSION[$initKey])) {
            ensureUserMissions($mysqli, $userId, 'daily');
            ensureUserMissions($mysqli, $userId, 'weekly');
            $_SESSION[$initKey] = true;
        }
    } else {
        ensureUserMissions($mysqli, $userId, 'daily');
        ensureUserMissions($mysqli, $userId, 'weekly');
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
            m.slug,
            u.is_premium
        FROM user_missions um
        JOIN missions m ON m.id = um.mission_id
        JOIN utenti u ON u.id = um.user_id
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

            $updateStmt->bind_param(
                'iiii',
                $nuovoProgresso,
                $completata,
                $completata,
                $mission['user_mission_id']
            );
            $updateStmt->execute();

            // Se appena completata (non lo era prima) → aggiungi ai risultati
            if ($completata === 1 && (int)$mission['progresso'] < (int)$mission['obiettivo']) {
                $punti = (int)$mission['punti_reward'];
                if ((int)($mission['is_premium'] ?? 0) === 1) {
                    $punti *= 2;
                }
                $newlyDone[] = [
                    'user_mission_id' => (int)$mission['user_mission_id'],
                    'titolo'          => $mission['titolo'],
                    'punti_reward'    => $punti,
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
//  HELPER — SEZIONI VISITATE
// ─────────────────────────────────────────────────────────────

/**
 * Traccia l'apertura di una sezione del sito.
 *
 * Chiamata da api/update_activity.php, che è l'unico posto dove la sezione
 * corrente si conosce già senza doverla ricavare: il battito di presenza gira
 * su ogni pagina e ha già passato il percorso da stats_page_key_from_path().
 *
 * Il battito arriva ogni 25 secondi, quindi senza un freno questa funzione
 * scriverebbe sul database centoquaranta volte l'ora per utente. La sezione
 * conta una volta sola al giorno: la lista di quelle già viste sta in
 * sessione, come per trackDailyLogin(). Su due dispositivi contemporanei la
 * stessa sezione può contare due volte — è il prezzo di non fare una lettura
 * a ogni battito, e riguarda missioni da trenta punti.
 *
 * @param string $pageKey chiave di STATS_PAGE_KEYS
 */
function trackSectionVisit(mysqli $mysqli, int $userId, string $pageKey): void
{
    if ($userId <= 0 || $pageKey === '' || $pageKey === 'altro' || !isset($_SESSION)) {
        return;
    }

    $seenKey = 'mission_sections_' . date('Ymd');
    $seen    = $_SESSION[$seenKey] ?? null;

    if (!is_array($seen)) {
        // Le liste dei giorni passati non servono più: farebbero crescere la
        // sessione di una voce al giorno per sempre.
        foreach (array_keys($_SESSION) as $key) {
            if (is_string($key) && str_starts_with($key, 'mission_sections_')) {
                unset($_SESSION[$key]);
            }
        }
        $seen = [];
    }

    if (isset($seen[$pageKey])) {
        return;
    }

    $seen[$pageKey]       = true;
    $_SESSION[$seenKey]   = $seen;

    // «Visita N sezioni diverse»: ogni sezione nuova della giornata vale uno.
    trackMissionProgress($mysqli, $userId, 'visit_section', 1);

    // Le sezioni che hanno una missione dedicata valgono anche quella.
    if (isset(MISSION_SECTION_EVENTS[$pageKey])) {
        trackMissionProgress($mysqli, $userId, MISSION_SECTION_EVENTS[$pageKey], 1);
    }
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


// ─────────────────────────────────────────────────────────────
//  PONTE VERSO LE STATISTICHE DEL REWIND
// ─────────────────────────────────────────────────────────────

/**
 * Traduce un evento missione nella metrica corrispondente del Rewind.
 *
 * Qui sta solo quello che nessun altro conta. Chi manca, manca apposta:
 * `daily_login` e `view_page` li copre l'heartbeat; i tre sapori di messaggio,
 * le partite dei giochi, le amicizie, gli achievement e i personaggi nuovi
 * chiamano stats_track() dai rispettivi endpoint, dove si sa distinguere una
 * chat privata da una di gruppo e una vittoria da una sconfitta. Aggiungerli
 * anche qui vorrebbe dire contarli due volte.
 *
 * Le cinque righe in fondo invece coprono metriche che fino ad ora nessuno
 * scriveva: commenti, post, voti e letture della CripsumPedia esistevano in
 * STATS_METRICS ma restavano a zero nel Rewind.
 */
function trackStatsForMissionEvent(mysqli $mysqli, int $userId, string $evento, int $quantita): void
{
    static $map = [
        'lootbox_open'       => 'lootboxes_opened',
        'use_global_chat'    => 'msg_global',
        'visit_profile'      => 'profile_visits_made',
        'add_like'           => 'likes_given',
        'edit_profile'       => 'profile_edits',
        'view_edit'          => 'edits_viewed',
        'download_content'   => 'downloads',
        'get_rarity_rare'    => 'gacha_rare',
        'get_rarity_epic'    => 'gacha_epic',
        'get_rarity_special' => 'gacha_special',
        'get_rarity_secret'  => 'gacha_secret',

        'comment_post'        => 'comments_made',
        'create_post'         => 'posts_created',
        'create_shitpost'     => 'shitposts_created',
        'vote_rimasti'        => 'votes_cast',
        'visit_cripsumpedia'  => 'pedia_reads',
    ];

    if (!isset($map[$evento])) {
        return;
    }

    // stats_track() non lancia mai: se la migration non è stata applicata
    // esce da sola e le missioni proseguono normalmente.
    stats_track($mysqli, $userId, $map[$evento], $quantita);
}
