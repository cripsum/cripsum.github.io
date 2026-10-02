<?php

/**
 * Cripsum™ — Achievement: il motore unico.
 *
 * Prima c'erano cinque copie della stessa INSERT (browser, gacha, foto
 * profilo, potenziamenti, pannello) e ognuna decideva per conto suo se
 * avvisare il Rewind e le missioni. Adesso chi vuole assegnare un achievement
 * passa da qui.
 *
 * COME SI SBLOCCA UN ACHIEVEMENT
 *
 *   1. `metrica` + `soglia` (la maggior parte): il server conta da solo dalle
 *      tabelle che ha già — casse aperte, amici, giorni attivi — e assegna
 *      quando il numero arriva alla soglia. Il conto parte:
 *        - dopo l'azione che lo fa salire (pull, duello, amicizia, missione);
 *        - dal battito di presenza, una volta ogni dieci minuti per sessione;
 *        - quando si apre la pagina degli achievement.
 *      Nessuno di questi numeri arriva dal browser.
 *   2. `claim_client = 1`: quelli che il server non può vedere (il jackpot
 *      del gambling, le 3 di notte secondo l'orologio di chi guarda). Li
 *      chiede il browser ad api/set_achievement.php. Sono un ricordo, non una
 *      prova: per questo non hanno mai un premio in Godos.
 *   3. Né l'uno né l'altro: li assegna solo il codice, con ach_grant()
 *      (la foto profilo personalizzata).
 *
 * SCHEMA
 *
 * Categorie, livelli, metriche e premi arrivano con
 * migrations/2026_10_02_achievements_v3.sql. Finché non è applicata il motore
 * lavora in modalità storica: i 25 achievement di prima, con le soglie che
 * erano scritte nel codice (ACH_LEGACY_METRICS), senza premi.
 *
 * Regole di questo file:
 *   - nessun DDL;
 *   - le funzioni che contano non lanciano mai: una tabella che manca spegne
 *     quella metrica e basta;
 *   - ach_grant() fa solo la INSERT. Statistiche, missioni e avviso in pagina
 *     partono a fine richiesta, così si può chiamare anche dentro una
 *     transazione altrui senza chiuderla per sbaglio.
 */

require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/stats_tracker.php';

/** Livelli dal più facile al più raro, con i punti che valgono di solito. */
const ACH_TIERS = [
    'bronzo'   => ['it' => 'Bronzo',   'en' => 'Bronze',   'punti' => 100],
    'argento'  => ['it' => 'Argento',  'en' => 'Silver',   'punti' => 250],
    'oro'      => ['it' => 'Oro',      'en' => 'Gold',     'punti' => 500],
    'platino'  => ['it' => 'Platino',  'en' => 'Platinum', 'punti' => 1000],
    'diamante' => ['it' => 'Diamante', 'en' => 'Diamond',  'punti' => 2000],
];

/** Le linguette della pagina, nell'ordine in cui compaiono. */
const ACH_CATEGORIES = [
    'esplorazione' => ['it' => 'Esplorazione', 'en' => 'Exploration', 'icon' => 'fa-compass'],
    'gacha'        => ['it' => 'Gacha',        'en' => 'Gacha',       'icon' => 'fa-box-open'],
    'giochi'       => ['it' => 'Giochi',       'en' => 'Games',       'icon' => 'fa-gamepad'],
    'social'       => ['it' => 'Social',       'en' => 'Social',      'icon' => 'fa-user-group'],
    'contenuti'    => ['it' => 'Contenuti',    'en' => 'Content',     'icon' => 'fa-image'],
    'progressione' => ['it' => 'Progressione', 'en' => 'Progress',    'icon' => 'fa-chart-line'],
    'account'      => ['it' => 'Account',      'en' => 'Account',     'icon' => 'fa-id-badge'],
];

/** I gradi: il titolo accanto al punteggio, dai punti in su. */
const ACH_RANKS = [
    ['min' => 0,     'it' => 'Novellino',     'en' => 'Rookie'],
    ['min' => 500,   'it' => 'Curioso',       'en' => 'Curious'],
    ['min' => 1500,  'it' => 'Esploratore',   'en' => 'Explorer'],
    ['min' => 3000,  'it' => 'Collezionista', 'en' => 'Collector'],
    ['min' => 6000,  'it' => 'Esperto',       'en' => 'Expert'],
    ['min' => 10000, 'it' => 'Maestro',       'en' => 'Master'],
    ['min' => 16000, 'it' => 'Leggenda',      'en' => 'Legend'],
    ['min' => 25000, 'it' => 'Mito',          'en' => 'Myth'],
];

/**
 * Soglie dei 25 achievement storici, per quando la migration non c'è ancora.
 * Sono gli stessi numeri che stavano in gacha/notify.php, in
 * api/game/upgrade_character.php e in js/achievements-globali.js.
 */
const ACH_LEGACY_METRICS = [
    1  => ['account', 1],
    5  => ['boxes', 1],
    6  => ['sezione_edits', 1],
    8  => ['boxes', 100],
    9  => ['commons_streak', 10],
    13 => ['days_legacy', 30],
    14 => ['seconds_legacy', 7200],
    15 => ['sezione_goonland', 1],
    16 => ['boxes', 500],
    18 => ['unique', 100],
    19 => ['clickgoon', 100],
    21 => ['ach_completion', 100],
    22 => ['maxed', 1],
    23 => ['maxed', 5],
    24 => ['maxed', 10],
    25 => ['maxed', 50],
];

/** Storici che assegna solo il codice (2: foto profilo personalizzata). */
const ACH_LEGACY_SERVER_ONLY = [2];

/** Quanti sblocchi può chiedere un browser in un minuto. */
const ACH_CLIENT_CLAIMS_PER_MINUTE = 12;

/** Ogni quanto il battito di presenza rifà i conti, per sessione. */
const ACH_HEARTBEAT_SYNC_SECONDS = 600;

/** Per quanto vale il conto di «ce l'ha il 3% dei giocatori». */
const ACH_RARITY_TTL = 600;

// ─────────────────────────────────────────────────────────────
//  METRICHE
// ─────────────────────────────────────────────────────────────

/**
 * Tutto quello che il server sa contare.
 *
 * `source` è la funzione ach_source_<nome>() che lo calcola: più metriche
 * della stessa sorgente costano una query sola. `format` dice alla pagina
 * come scrivere il progresso. Le etichette servono al pannello admin.
 *
 * @return array<string,array{source:string,it:string,en:string,format:string}>
 */
function ach_metric_defs(): array
{
    static $defs = null;
    if ($defs !== null) {
        return $defs;
    }

    $defs = [
        'account'             => ['source' => 'always',     'format' => 'bool',     'it' => 'Avere un account', 'en' => 'Having an account'],

        'boxes'               => ['source' => 'collection', 'format' => 'number',   'it' => 'Casse aperte', 'en' => 'Crates opened'],
        'unique'              => ['source' => 'collection', 'format' => 'number',   'it' => 'Personaggi diversi', 'en' => 'Different characters'],
        'collection_pct'      => ['source' => 'collection', 'format' => 'percent',  'it' => 'Collezione completata (%)', 'en' => 'Collection complete (%)'],
        'upgraded'            => ['source' => 'collection', 'format' => 'number',   'it' => 'Personaggi potenziati', 'en' => 'Upgraded characters'],
        'maxed'               => ['source' => 'collection', 'format' => 'number',   'it' => 'Personaggi al livello MAX', 'en' => 'Characters at MAX level'],
        'owned_leggendario'   => ['source' => 'collection', 'format' => 'number',   'it' => 'Personaggi Leggendari', 'en' => 'Legendary characters'],
        'owned_speciale'      => ['source' => 'collection', 'format' => 'number',   'it' => 'Personaggi Speciali', 'en' => 'Special characters'],
        'owned_segreto'       => ['source' => 'collection', 'format' => 'number',   'it' => 'Personaggi Segreti', 'en' => 'Secret characters'],
        'owned_theone'        => ['source' => 'collection', 'format' => 'number',   'it' => 'Personaggi The One', 'en' => 'The One characters'],

        'won_5050'            => ['source' => 'gacha',      'format' => 'number',   'it' => '50/50 vinti', 'en' => '50/50 won'],
        'lost_5050'           => ['source' => 'gacha',      'format' => 'number',   'it' => '50/50 persi', 'en' => '50/50 lost'],
        'max_pity'            => ['source' => 'gacha',      'format' => 'number',   'it' => 'Pity più alto raggiunto', 'en' => 'Highest pity reached'],
        'commons_streak'      => ['source' => 'gacha',      'format' => 'number',   'it' => 'Comuni di fila (ultime pull)', 'en' => 'Commons in a row (latest pulls)'],

        'duel_wins'           => ['source' => 'duels',      'format' => 'number',   'it' => 'Duelli vinti', 'en' => 'Duels won'],
        'duel_best_streak'    => ['source' => 'duels',      'format' => 'number',   'it' => 'Vittorie classificate di fila', 'en' => 'Ranked wins in a row'],

        'friends'             => ['source' => 'friends',    'format' => 'number',   'it' => 'Amici', 'en' => 'Friends'],
        'missions_done'       => ['source' => 'missions',   'format' => 'number',   'it' => 'Missioni completate', 'en' => 'Missions completed'],

        'seconds'             => ['source' => 'totals',     'format' => 'duration', 'it' => 'Tempo sul sito (misurato dal server)', 'en' => 'Time on site (server-measured)'],
        'seconds_legacy'      => ['source' => 'totals',     'format' => 'duration', 'it' => 'Tempo sul sito (compresi i vecchi cookie)', 'en' => 'Time on site (old cookies included)'],
        'days_active'         => ['source' => 'totals',     'format' => 'number',   'it' => 'Giorni attivi (misurati dal server)', 'en' => 'Active days (server-measured)'],
        'days_legacy'         => ['source' => 'totals',     'format' => 'number',   'it' => 'Giorni attivi (compresi i vecchi cookie)', 'en' => 'Active days (old cookies included)'],
        'best_streak'         => ['source' => 'totals',     'format' => 'number',   'it' => 'Giorni di fila (record)', 'en' => 'Day streak (best)'],

        'subway_played'       => ['source' => 'stats',      'format' => 'number',   'it' => 'Corse su Subway', 'en' => 'Subway runs'],
        'pullspot_won'        => ['source' => 'stats',      'format' => 'number',   'it' => 'Pullspot indovinati', 'en' => 'Pullspot solved'],
        'animespot_won'       => ['source' => 'stats',      'format' => 'number',   'it' => 'Sigle indovinate', 'en' => 'Openings guessed'],
        'animespot_first_try' => ['source' => 'stats',      'format' => 'number',   'it' => 'Sigle al primo tentativo', 'en' => 'Openings on the first try'],
        'votes_cast'          => ['source' => 'stats',      'format' => 'number',   'it' => 'Voti ai Top Rimasti', 'en' => 'Top Rimasti votes'],
        'pedia_reads'         => ['source' => 'stats',      'format' => 'number',   'it' => 'Giorni sulla CripsumPedia', 'en' => 'Days on the CripsumPedia'],

        'msg_global'          => ['source' => 'chat',       'format' => 'number',   'it' => 'Messaggi in chat globale', 'en' => 'Global chat messages'],
        'msg_private'         => ['source' => 'chat',       'format' => 'number',   'it' => 'Messaggi privati inviati', 'en' => 'Private messages sent'],

        'posts'               => ['source' => 'content',    'format' => 'number',   'it' => 'Post approvati', 'en' => 'Approved posts'],
        'likes_received'      => ['source' => 'content',    'format' => 'number',   'it' => 'Like ricevuti', 'en' => 'Likes received'],
        'comments'            => ['source' => 'content',    'format' => 'number',   'it' => 'Commenti scritti', 'en' => 'Comments written'],

        'clickgoon'           => ['source' => 'user',       'format' => 'number',   'it' => 'Click su GoonLand', 'en' => 'GoonLand clicks'],
        'profile_views'       => ['source' => 'user',       'format' => 'number',   'it' => 'Visite al profilo', 'en' => 'Profile views'],
        'godos'               => ['source' => 'user',       'format' => 'number',   'it' => 'Godos posseduti', 'en' => 'Godos owned'],
        'account_days'        => ['source' => 'user',       'format' => 'number',   'it' => 'Giorni dalla registrazione', 'en' => 'Days since sign-up'],
        'twofa'               => ['source' => 'user',       'format' => 'bool',     'it' => 'Verifica in due passaggi attiva', 'en' => 'Two-step verification on'],
        'discord'             => ['source' => 'user',       'format' => 'bool',     'it' => 'Discord collegato', 'en' => 'Discord linked'],
        'premium'             => ['source' => 'user',       'format' => 'bool',     'it' => 'Account Premium', 'en' => 'Premium account'],

        'badges_shown'        => ['source' => 'profile',    'format' => 'number',   'it' => 'Badge in vetrina sul profilo', 'en' => 'Badges shown on the profile'],
        'shop_purchases'      => ['source' => 'shop',       'format' => 'number',   'it' => 'Acquisti al negozio Godos', 'en' => 'Godos shop purchases'],

        'achievements'        => ['source' => 'achievements', 'format' => 'number',  'it' => 'Achievement sbloccati', 'en' => 'Achievements unlocked'],
        'ach_completion'      => ['source' => 'achievements', 'format' => 'percent', 'it' => 'Tutti gli altri achievement (%)', 'en' => 'Every other achievement (%)'],
    ];

    // «Apri la sezione X»: una metrica per ogni sezione che il battito di
    // presenza riconosce.
    foreach (STATS_PAGE_KEYS as $pageKey) {
        if ($pageKey === 'altro') {
            continue;
        }
        $defs['sezione_' . $pageKey] = [
            'source' => 'pages',
            'format' => 'bool',
            'it' => 'Visita alla sezione «' . $pageKey . '»',
            'en' => 'Visit to the "' . $pageKey . '" section',
        ];
    }

    return $defs;
}

/** Una riga, o null se la query non gira (tabella assente, colonna rinominata). */
function ach_row(mysqli $mysqli, string $sql, string $types = '', array $params = []): ?array
{
    try {
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return null;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: [];
    } catch (Throwable $e) {
        return null;
    }
}

/** Tutte le righe, o null se la query non gira. */
function ach_rows(mysqli $mysqli, string $sql, string $types = '', array $params = []): ?array
{
    try {
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return null;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    } catch (Throwable $e) {
        return null;
    }
}

/** Il primo valore della prima riga come intero, o null se la query non gira. */
function ach_scalar(mysqli $mysqli, string $sql, string $types = '', array $params = []): ?int
{
    $row = ach_row($mysqli, $sql, $types, $params);
    if ($row === null) {
        return null;
    }
    if ($row === []) {
        return 0;
    }
    $value = reset($row);
    return $value === null ? 0 : (int)$value;
}

/** «Leggendario », «THE ONE», «the_one»: una rarità scritta in qualsiasi forma, ridotta alla sua chiave. */
function ach_rarity_key(?string $value): string
{
    if (function_exists('gacha_rarity_key')) {
        return gacha_rarity_key($value);
    }

    $v = mb_strtolower(trim((string)$value), 'UTF-8');
    $v = strtr($v, ['à' => 'a', 'á' => 'a', ' ' => '', '_' => '', '-' => '']);
    $aliases = [
        'common' => 'comune', 'rare' => 'raro', 'epic' => 'epico', 'legendary' => 'leggendario',
        'mitico' => 'leggendario', 'special' => 'speciale', 'secret' => 'segreto', 'one' => 'theone',
    ];

    return $aliases[$v] ?? $v;
}

function ach_source_always(mysqli $mysqli, int $userId): array
{
    return ['account' => 1];
}

function ach_source_collection(mysqli $mysqli, int $userId): array
{
    // Con le colonne dei potenziamenti; se mancano si riprova senza.
    $row = ach_row(
        $mysqli,
        'SELECT COALESCE(SUM(`quantità` + `copie_usate`), 0) AS boxes, COUNT(*) AS uniq,
                COALESCE(SUM(livello >= 6), 0) AS maxed, COALESCE(SUM(livello >= 2), 0) AS upgraded
         FROM utenti_personaggi WHERE utente_id = ?',
        'i',
        [$userId]
    );
    if ($row === null) {
        $row = ach_row(
            $mysqli,
            'SELECT COALESCE(SUM(' . cripsum_boxes_sql($mysqli) . '), 0) AS boxes, COUNT(*) AS uniq FROM utenti_personaggi WHERE utente_id = ?',
            'i',
            [$userId]
        );
    }
    if ($row === null) {
        return [];
    }

    $values = [
        'boxes'  => (int)($row['boxes'] ?? 0),
        'unique' => (int)($row['uniq'] ?? 0),
    ];
    if (array_key_exists('maxed', $row)) {
        $values['maxed'] = (int)$row['maxed'];
        $values['upgraded'] = (int)$row['upgraded'];
    }

    $rarities = ach_rows(
        $mysqli,
        'SELECT p.`rarità` AS r, COUNT(*) AS n
         FROM utenti_personaggi up INNER JOIN personaggi p ON p.id = up.personaggio_id
         WHERE up.utente_id = ? GROUP BY p.`rarità`',
        'i',
        [$userId]
    );
    if ($rarities !== null) {
        $owned = ['leggendario' => 0, 'speciale' => 0, 'segreto' => 0, 'theone' => 0];
        foreach ($rarities as $rarity) {
            $key = ach_rarity_key($rarity['r'] ?? '');
            if (isset($owned[$key])) {
                $owned[$key] += (int)$rarity['n'];
            }
        }
        foreach ($owned as $key => $count) {
            $values['owned_' . $key] = $count;
        }
    }

    // I personaggi nascosti dal catalogo non contano nel totale da completare.
    $total = ach_scalar($mysqli, "SELECT COUNT(*) FROM personaggi WHERE catalogo <> 'nascosto'");
    if ($total === null) {
        $total = ach_scalar($mysqli, 'SELECT COUNT(*) FROM personaggi');
    }
    if ($total) {
        $values['collection_pct'] = (int)min(100, floor($values['unique'] / $total * 100));
    }

    return $values;
}

function ach_source_gacha(mysqli $mysqli, int $userId): array
{
    $values = [];

    $row = ach_row(
        $mysqli,
        'SELECT COALESCE(SUM(esito_50_50 = 1), 0) AS won, COALESCE(SUM(esito_50_50 = 0), 0) AS lost,
                COALESCE(MAX(pity_al_momento), 0) AS max_pity
         FROM gacha_pull_history WHERE utente_id = ?',
        'i',
        [$userId]
    );
    if ($row !== null) {
        $values['won_5050'] = (int)($row['won'] ?? 0);
        $values['lost_5050'] = (int)($row['lost'] ?? 0);
        $values['max_pity'] = (int)($row['max_pity'] ?? 0);
    }

    // Quanti comuni di fila nelle ultime pull, partendo dalla più recente.
    $last = ach_rows($mysqli, 'SELECT `rarità` AS r FROM gacha_pull_history WHERE utente_id = ? ORDER BY id DESC LIMIT 10', 'i', [$userId]);
    if ($last !== null) {
        $streak = 0;
        foreach ($last as $pull) {
            if (ach_rarity_key($pull['r'] ?? '') !== 'comune') {
                break;
            }
            $streak++;
        }
        $values['commons_streak'] = $streak;
    }

    return $values;
}

function ach_source_duels(mysqli $mysqli, int $userId): array
{
    $values = [];

    $wins = ach_scalar(
        $mysqli,
        "SELECT COALESCE(SUM(winner_id = ?), 0) FROM game_matches WHERE (player1_id = ? OR player2_id = ?) AND status = 'finished'",
        'iii',
        [$userId, $userId, $userId]
    );
    if ($wins !== null) {
        $values['duel_wins'] = $wins;
    }

    $streak = ach_scalar($mysqli, 'SELECT best_streak FROM game_player_stats WHERE user_id = ? LIMIT 1', 'i', [$userId]);
    if ($streak !== null) {
        $values['duel_best_streak'] = $streak;
    }

    return $values;
}

function ach_source_friends(mysqli $mysqli, int $userId): array
{
    $count = ach_scalar($mysqli, 'SELECT COUNT(*) FROM friendships WHERE user_one_id = ? OR user_two_id = ?', 'ii', [$userId, $userId]);
    return $count === null ? [] : ['friends' => $count];
}

function ach_source_missions(mysqli $mysqli, int $userId): array
{
    $count = ach_scalar($mysqli, 'SELECT COUNT(*) FROM user_missions WHERE user_id = ? AND completata = 1', 'i', [$userId]);
    return $count === null ? [] : ['missions_done' => $count];
}

/**
 * Tempo e giorni sul sito.
 *
 * Due versioni di ogni numero. Quella «legacy» somma anche i contatori che
 * prima vivevano nei cookie del browser: servono ai due achievement storici
 * (30 giorni, 2 ore), per non togliere a nessuno il progresso fatto. Ma un
 * cookie lo scrive chi vuole, quindi tutti gli achievement nuovi contano solo
 * quello che ha misurato il server.
 */
function ach_source_totals(mysqli $mysqli, int $userId): array
{
    if (!stats_available($mysqli)) {
        return [];
    }

    $row = ach_row(
        $mysqli,
        'SELECT total_seconds, legacy_seconds, total_days_active, legacy_days, longest_streak FROM user_stat_totals WHERE utente_id = ? LIMIT 1',
        'i',
        [$userId]
    );
    if ($row === null) {
        return [];
    }

    $seconds = (int)($row['total_seconds'] ?? 0);
    $days = (int)($row['total_days_active'] ?? 0);

    // I giorni dei cookie contano solo fino al primo giorno misurato dal
    // server: da lì in poi sarebbero gli stessi giorni contati due volte.
    $legacyDays = ach_scalar(
        $mysqli,
        "SELECT COUNT(*) FROM user_legacy_days l
         WHERE l.utente_id = ?
           AND l.`day` < COALESCE((SELECT MIN(d.`day`) FROM user_daily_stats d WHERE d.utente_id = ? AND d.seconds_active > 0), '9999-12-31')",
        'ii',
        [$userId, $userId]
    );
    if ($legacyDays === null) {
        $legacyDays = (int)($row['legacy_days'] ?? 0);
    }

    return [
        'seconds'        => $seconds,
        'seconds_legacy' => $seconds + (int)($row['legacy_seconds'] ?? 0),
        'days_active'    => $days,
        'days_legacy'    => $days + $legacyDays,
        'best_streak'    => (int)($row['longest_streak'] ?? 0),
    ];
}

/** Somme dei contatori giornalieri del Rewind: esistono da settembre 2026. */
function ach_source_stats(mysqli $mysqli, int $userId): array
{
    if (!stats_available($mysqli)) {
        return [];
    }

    $wanted = ['subway_runs', 'pullspot_won', 'animespot_won', 'animespot_first_try', 'votes_cast', 'pedia_reads'];
    $known = stats_existing_columns($mysqli);
    $columns = $known === [] ? $wanted : array_values(array_filter($wanted, static fn($c) => isset($known[$c])));
    if ($columns === []) {
        return [];
    }

    $select = implode(', ', array_map(static fn($c) => "COALESCE(SUM(`$c`), 0) AS `$c`", $columns));
    $row = ach_row($mysqli, "SELECT $select FROM user_daily_stats WHERE utente_id = ?", 'i', [$userId]);
    if ($row === null) {
        return [];
    }

    $values = [];
    foreach ($columns as $column) {
        $values[$column] = (int)($row[$column] ?? 0);
    }

    // Chi ha un tempo in classifica ha corso almeno una volta, anche prima
    // che esistessero i contatori.
    $runs = (int)($values['subway_runs'] ?? 0);
    unset($values['subway_runs']);
    if ($runs === 0 && ach_scalar($mysqli, 'SELECT COUNT(*) FROM subway_leaderboard WHERE utente_id = ?', 'i', [$userId])) {
        $runs = 1;
    }
    $values['subway_played'] = $runs;

    return $values;
}

function ach_source_chat(mysqli $mysqli, int $userId): array
{
    $values = [];

    $global = ach_scalar($mysqli, 'SELECT COUNT(*) FROM messages WHERE user_id = ?', 'i', [$userId]);
    if ($global !== null) {
        $values['msg_global'] = $global;
    }

    $private = ach_scalar($mysqli, 'SELECT COUNT(*) FROM private_messages WHERE sender_id = ?', 'i', [$userId]);
    if ($private !== null) {
        $values['msg_private'] = $private;
    }

    return $values;
}

function ach_source_content(mysqli $mysqli, int $userId): array
{
    $values = [];

    $posts = array_filter([
        ach_scalar($mysqli, 'SELECT COUNT(*) FROM shitposts WHERE id_utente = ? AND approvato = 1', 'i', [$userId]),
        ach_scalar($mysqli, 'SELECT COUNT(*) FROM toprimasti WHERE id_utente = ? AND approvato = 1', 'i', [$userId]),
    ], static fn($v) => $v !== null);
    if ($posts) {
        $values['posts'] = array_sum($posts);
    }

    $likes = ach_scalar(
        $mysqli,
        'SELECT COUNT(*) FROM shitpost_likes l INNER JOIN shitposts s ON s.id = l.id_shitpost WHERE s.id_utente = ?',
        'i',
        [$userId]
    );
    if ($likes !== null) {
        $values['likes_received'] = $likes;
    }

    $comments = array_filter([
        ach_scalar($mysqli, 'SELECT COUNT(*) FROM content_comments WHERE user_id = ?', 'i', [$userId]),
        ach_scalar($mysqli, 'SELECT COUNT(*) FROM commenti_shitpost WHERE id_utente = ?', 'i', [$userId]),
    ], static fn($v) => $v !== null);
    if ($comments) {
        $values['comments'] = array_sum($comments);
    }

    return $values;
}

function ach_source_user(mysqli $mysqli, int $userId): array
{
    $row = ach_row(
        $mysqli,
        'SELECT soldi, clickgoon, profile_views, data_creazione, is_premium, discord_id, twofa_enabled FROM utenti WHERE id = ? LIMIT 1',
        'i',
        [$userId]
    );
    if ($row === null) {
        // Installazione senza qualche colonna recente: almeno quelle di sempre.
        $row = ach_row($mysqli, 'SELECT soldi, clickgoon, data_creazione FROM utenti WHERE id = ? LIMIT 1', 'i', [$userId]);
    }
    if (!$row) {
        return [];
    }

    $values = [
        'godos'     => (int)($row['soldi'] ?? 0),
        'clickgoon' => (int)($row['clickgoon'] ?? 0),
    ];

    $created = strtotime((string)($row['data_creazione'] ?? ''));
    if ($created) {
        $values['account_days'] = max(0, (int)floor((time() - $created) / 86400));
    }
    if (array_key_exists('profile_views', $row)) {
        $values['profile_views'] = (int)$row['profile_views'];
        $values['premium'] = (int)$row['is_premium'] === 1 ? 1 : 0;
        $values['discord'] = trim((string)$row['discord_id']) !== '' ? 1 : 0;
        $values['twofa'] = (int)$row['twofa_enabled'] === 1 ? 1 : 0;
    }

    return $values;
}

function ach_source_profile(mysqli $mysqli, int $userId): array
{
    $count = ach_scalar($mysqli, 'SELECT COUNT(*) FROM utenti_profile_badges WHERE utente_id = ? AND is_visible = 1', 'i', [$userId]);
    return $count === null ? [] : ['badges_shown' => $count];
}

function ach_source_shop(mysqli $mysqli, int $userId): array
{
    $count = ach_scalar($mysqli, 'SELECT COUNT(*) FROM user_godos_shop_purchases WHERE user_id = ?', 'i', [$userId]);
    return $count === null ? [] : ['shop_purchases' => $count];
}

/** Le sezioni del sito aperte almeno una volta, dalle statistiche del Rewind. */
function ach_source_pages(mysqli $mysqli, int $userId): array
{
    if (!stats_available($mysqli)) {
        return [];
    }

    $rows = ach_rows($mysqli, 'SELECT page_key, SUM(views) AS v FROM user_page_stats WHERE utente_id = ? GROUP BY page_key', 'i', [$userId]);
    if ($rows === null) {
        return [];
    }

    $values = [];
    foreach (STATS_PAGE_KEYS as $pageKey) {
        $values['sezione_' . $pageKey] = 0;
    }
    foreach ($rows as $row) {
        $key = 'sezione_' . $row['page_key'];
        if (isset($values[$key]) && (int)$row['v'] > 0) {
            $values[$key] = 1;
        }
    }

    return $values;
}

/**
 * I valori delle metriche chieste. Una metrica che non si riesce a calcolare
 * manca dal risultato: chi legge la tratta come «non lo so», non come zero.
 *
 * Le metriche della sorgente `achievements` non passano di qui: dipendono da
 * cosa ha sbloccato l'utente e le calcola ach_values_from_unlocked().
 *
 * @param string[] $metrics
 * @return array<string,int>
 */
function ach_values(mysqli $mysqli, int $userId, array $metrics): array
{
    $defs = ach_metric_defs();
    $bySource = [];
    foreach (array_unique($metrics) as $metric) {
        $source = $defs[$metric]['source'] ?? null;
        if ($source !== null && $source !== 'achievements') {
            $bySource[$source][] = $metric;
        }
    }

    $values = [];
    foreach ($bySource as $source => $wanted) {
        $fn = 'ach_source_' . $source;
        try {
            $got = function_exists($fn) ? $fn($mysqli, $userId) : [];
        } catch (Throwable $e) {
            error_log('[achievements] sorgente ' . $source . ': ' . $e->getMessage());
            $got = [];
        }
        foreach ($wanted as $metric) {
            if (isset($got[$metric])) {
                $values[$metric] = (int)$got[$metric];
            }
        }
    }

    return $values;
}

/**
 * «Quanti ne hai» e «quanti ti mancano per averli tutti».
 *
 * @param array<int,array> $catalog   ach_catalog()
 * @param array<int,mixed> $unlocked  id sbloccati come chiavi
 * @return array{achievements:int,ach_completion:int}
 */
function ach_values_from_unlocked(array $catalog, array $unlocked): array
{
    $others = 0;
    $have = 0;
    foreach ($catalog as $id => $entry) {
        // «Sbloccali tutti» non conta sé stesso, né quelli tolti dalla pagina.
        if (!$entry['attivo'] || $entry['metrica'] === 'ach_completion') {
            continue;
        }
        $others++;
        if (isset($unlocked[$id])) {
            $have++;
        }
    }

    return [
        'achievements'   => count($unlocked),
        'ach_completion' => $others > 0 ? (int)floor($have / $others * 100) : 0,
    ];
}

// ─────────────────────────────────────────────────────────────
//  CATALOGO
// ─────────────────────────────────────────────────────────────

/**
 * Tutti gli achievement, con le colonne nuove o i loro valori storici.
 *
 * @return array<int,array<string,mixed>> id => achievement
 */
function ach_catalog(mysqli $mysqli, bool $refresh = false): array
{
    static $catalog = null;
    if ($catalog !== null && !$refresh) {
        return $catalog;
    }

    $rows = ach_rows($mysqli, 'SELECT * FROM achievement');
    if ($rows === null) {
        return $catalog = [];
    }

    $v3 = $rows !== [] && array_key_exists('metrica', $rows[0]);
    $GLOBALS['__ach_v3'] = $v3;

    $catalog = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $legacy = ACH_LEGACY_METRICS[$id] ?? null;

        if ($v3) {
            $metric = trim((string)($row['metrica'] ?? ''));
            $entry = [
                'chiave'       => (string)($row['chiave'] ?? ''),
                'categoria'    => isset(ACH_CATEGORIES[$row['categoria']]) ? (string)$row['categoria'] : 'esplorazione',
                'livello'      => isset(ACH_TIERS[$row['livello']]) ? (string)$row['livello'] : 'bronzo',
                'segreto'      => (int)$row['segreto'] === 1,
                'metrica'      => $metric !== '' && isset(ach_metric_defs()[$metric]) ? $metric : null,
                'soglia'       => max(1, (int)$row['soglia']),
                'serie'        => trim((string)($row['serie'] ?? '')) ?: null,
                'ordine'       => (int)$row['ordine'],
                'ricompensa'   => max(0, (int)$row['ricompensa']),
                'claim_client' => (int)$row['claim_client'] === 1,
                'attivo'       => (int)$row['attivo'] === 1,
            ];
        } else {
            $entry = [
                'chiave'       => '',
                'categoria'    => 'esplorazione',
                'livello'      => 'bronzo',
                'segreto'      => false,
                'metrica'      => $legacy[0] ?? null,
                'soglia'       => $legacy[1] ?? 1,
                'serie'        => null,
                'ordine'       => $id,
                'ricompensa'   => 0,
                // Prima della migration vale la regola di sempre: il browser
                // può chiedere tutto quello che il server non assegna da sé.
                'claim_client' => $legacy === null && !in_array($id, ACH_LEGACY_SERVER_ONLY, true),
                'attivo'       => true,
            ];
        }

        // Un premio su un achievement che il browser può chiedere sarebbe
        // Godos gratis per chiunque sappia fare una POST: non esiste.
        if ($entry['claim_client']) {
            $entry['ricompensa'] = 0;
        }

        $catalog[$id] = $entry + [
            'id'             => $id,
            'nome'           => (string)($row['nome'] ?? ''),
            'nome_en'        => (string)($row['nome_en'] ?? ''),
            'descrizione'    => (string)($row['descrizione'] ?? ''),
            'descrizione_en' => (string)($row['descrizione_en'] ?? ''),
            'punti'          => max(0, (int)($row['punti'] ?? 0)),
            'img_url'        => (string)($row['img_url'] ?? ''),
        ];
    }

    uasort($catalog, static fn($a, $b) => [$a['ordine'], $a['id']] <=> [$b['ordine'], $b['id']]);

    return $catalog;
}

/** Vero se la migration v3 è stata applicata. */
function ach_is_v3(mysqli $mysqli): bool
{
    if (!array_key_exists('__ach_v3', $GLOBALS)) {
        ach_catalog($mysqli);
    }
    if (empty($GLOBALS['__ach_v3']) && ach_catalog($mysqli) === []) {
        // Catalogo vuoto: lo schema non si legge dalle righe.
        return auth_column_exists($mysqli, 'achievement', 'metrica');
    }

    return !empty($GLOBALS['__ach_v3']);
}

/** Vero se utenti_achievement sa ricordare i premi incassati. */
function ach_has_claim_cols(mysqli $mysqli): bool
{
    return auth_column_exists($mysqli, 'utenti_achievement', 'riscattato');
}

/** Indirizzo dell'icona come lo vuole un tag <img>, da qualsiasi pagina. */
function ach_image_url(?string $raw): string
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '/img/achievements/_default.svg';
    }
    if (preg_match('#^(https?:)?//#i', $raw)) {
        return $raw;
    }
    // Il pannello storico salvava `../img/x.png`, come lo scrivono le pagine in it/.
    $raw = preg_replace('#^(\.\./)+#', '/', $raw);
    if ($raw[0] === '/') {
        return $raw;
    }

    return '/img/' . $raw;
}

/** Nome o descrizione nella lingua giusta, con l'italiano come ripiego. */
function ach_text(array $entry, string $field, string $lang): string
{
    if ($lang === 'en' && trim((string)($entry[$field . '_en'] ?? '')) !== '') {
        return (string)$entry[$field . '_en'];
    }

    return (string)($entry[$field] ?? '');
}

/**
 * Gli achievement dell'utente.
 *
 * @return array<int,array{at:?string,claimed:bool}> id => quando e se il premio è stato incassato
 */
function ach_user_unlocked(mysqli $mysqli, int $userId, bool $withClaims = false): array
{
    $claims = $withClaims && ach_has_claim_cols($mysqli);
    $rows = ach_rows(
        $mysqli,
        'SELECT achievement_id, `data`' . ($claims ? ', riscattato' : '') . ' FROM utenti_achievement WHERE utente_id = ?',
        'i',
        [$userId]
    ) ?? [];

    $unlocked = [];
    foreach ($rows as $row) {
        $unlocked[(int)$row['achievement_id']] = [
            'at'      => $row['data'] ?? null,
            'claimed' => $claims ? (int)$row['riscattato'] === 1 : false,
        ];
    }

    return $unlocked;
}

// ─────────────────────────────────────────────────────────────
//  ASSEGNAZIONE
// ─────────────────────────────────────────────────────────────

/**
 * Assegna un achievement. Vero solo se è stato assegnato adesso.
 *
 * La chiave primaria (utente, achievement) rende la INSERT atomica: due
 * richieste insieme non possono assegnarlo due volte, e una sola delle due
 * riceve `true`. Qui non si muove mai valuta: i Godos passano da ach_claim().
 *
 * Tutto il resto (statistiche del Rewind, missione «sblocca un achievement»,
 * avviso in pagina) parte a fine richiesta: vedi ach_flush_effects().
 */
function ach_grant(mysqli $mysqli, int $userId, int $achievementId, array $options = []): bool
{
    if ($userId <= 0 || $achievementId <= 0) {
        return false;
    }

    try {
        $stmt = $mysqli->prepare(
            'INSERT IGNORE INTO utenti_achievement (utente_id, achievement_id, `data`)
             SELECT ?, id, NOW() FROM achievement WHERE id = ?'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $userId, $achievementId);
        $stmt->execute();
        $granted = $stmt->affected_rows > 0;
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[achievements] grant ' . $achievementId . ' a ' . $userId . ': ' . $e->getMessage());
        return false;
    }

    if ($granted) {
        ach_queue_effects($mysqli, $userId, $achievementId, !empty($options['quiet']));
    }

    return $granted;
}

/** Coda degli effetti di uno sblocco, svuotata a fine richiesta. */
function &ach_effects_queue(): array
{
    static $queue = [];
    return $queue;
}

function ach_queue_effects(mysqli $mysqli, int $userId, int $achievementId, bool $quiet = false): void
{
    static $registered = false;

    $queue = &ach_effects_queue();
    $queue[] = ['user' => $userId, 'id' => $achievementId, 'quiet' => $quiet];

    if (!$registered) {
        $registered = true;
        register_shutdown_function(static function () use ($mysqli) {
            ach_flush_effects($mysqli);
        });
    }
}

/**
 * Quello che succede intorno a uno sblocco, dopo che la richiesta ha finito.
 *
 * A fine richiesta perché ach_grant() può essere chiamata dentro la
 * transazione di qualcun altro (il salvataggio del profilo): il tracker delle
 * missioni ne apre una sua e chiuderebbe quella esterna a metà. E se quella
 * transazione viene annullata l'achievement non c'è più: per questo prima di
 * avvisare si controlla che la riga esista davvero.
 */
function ach_flush_effects(mysqli $mysqli): void
{
    $queue = &ach_effects_queue();
    if ($queue === []) {
        return;
    }
    $pending = $queue;
    $queue = [];

    try {
        $catalog = ach_catalog($mysqli);

        foreach ($pending as $item) {
            $userId = (int)$item['user'];
            $id = (int)$item['id'];

            $exists = ach_scalar($mysqli, 'SELECT COUNT(*) FROM utenti_achievement WHERE utente_id = ? AND achievement_id = ?', 'ii', [$userId, $id]);
            if (!$exists) {
                continue;
            }

            stats_track_many($mysqli, $userId, [
                'achievements_unlocked' => 1,
                'achievement_points'    => (int)($catalog[$id]['punti'] ?? 0),
            ]);

            try {
                require_once __DIR__ . '/mission_tracker.php';
                trackMissionProgress($mysqli, $userId, 'unlock_achievement');
            } catch (Throwable $e) {
                error_log('[achievements] missioni: ' . $e->getMessage());
            }

            if (!$item['quiet']) {
                try {
                    require_once __DIR__ . '/realtime.php';
                    rt_push_user($userId, ['t' => 'ach', 'a' => $id]);
                } catch (Throwable $e) {
                    error_log('[achievements] avviso: ' . $e->getMessage());
                }
            }
        }

        // Le statistiche si scrivono a fine richiesta con un'altra funzione
        // di chiusura, che può essere già passata: meglio svuotare qui.
        stats_flush($mysqli);
    } catch (Throwable $e) {
        error_log('[achievements] effetti: ' . $e->getMessage());
    }
}

/**
 * Rifà i conti e assegna quello che l'utente ha raggiunto.
 *
 * @param string[]|null $sources  solo le metriche di queste sorgenti
 *                                (['collection', 'gacha'] dopo una pull);
 *                                null = tutte
 * @return int[] id appena assegnati
 */
function ach_sync(mysqli $mysqli, int $userId, ?array $sources = null, array $options = []): array
{
    if ($userId <= 0) {
        return [];
    }

    $granted = [];

    try {
        $catalog = ach_catalog($mysqli);
        if ($catalog === []) {
            return [];
        }

        $defs = ach_metric_defs();
        $unlocked = ach_user_unlocked($mysqli, $userId);

        $pending = [];
        $selfPending = [];
        foreach ($catalog as $id => $entry) {
            if (!$entry['attivo'] || $entry['metrica'] === null || isset($unlocked[$id])) {
                continue;
            }
            $source = $defs[$entry['metrica']]['source'] ?? null;
            if ($source === 'achievements') {
                $selfPending[$id] = $entry;
            } elseif ($source !== null && ($sources === null || in_array($source, $sources, true))) {
                $pending[$id] = $entry;
            }
        }

        if ($pending !== []) {
            $values = ach_values($mysqli, $userId, array_column($pending, 'metrica'));
            foreach ($pending as $id => $entry) {
                $value = $values[$entry['metrica']] ?? null;
                if ($value !== null && $value >= $entry['soglia'] && ach_grant($mysqli, $userId, $id, $options)) {
                    $granted[] = $id;
                    $unlocked[$id] = ['at' => null, 'claimed' => false];
                }
            }
        }

        // «Sbloccane 10» dipende da quanti ne hai: si guarda per ultimo, e
        // si riguarda finché uno sblocco ne porta un altro.
        for ($round = 0; $round < 4 && $selfPending !== []; $round++) {
            $values = ach_values_from_unlocked($catalog, $unlocked);
            $again = false;
            foreach ($selfPending as $id => $entry) {
                if (($values[$entry['metrica']] ?? 0) >= $entry['soglia'] && ach_grant($mysqli, $userId, $id, $options)) {
                    $granted[] = $id;
                    $unlocked[$id] = ['at' => null, 'claimed' => false];
                    unset($selfPending[$id]);
                    $again = true;
                }
            }
            if (!$again) {
                break;
            }
        }
    } catch (Throwable $e) {
        error_log('[achievements] sync utente ' . $userId . ': ' . $e->getMessage());
    }

    return $granted;
}

/**
 * Dal battito di presenza (api/update_activity.php), a sessione aperta.
 *
 * Due cose: «hai aperto la sezione X» — che vale anche per chi ha spento le
 * statistiche del Rewind, perché non passa da lì — e il ricalcolo generale,
 * al massimo una volta ogni dieci minuti.
 *
 * @return int[] id appena assegnati: il battito li rimanda al browser, che
 *               mostra il popup senza aspettare il giro del tempo reale
 */
function ach_heartbeat(mysqli $mysqli, int $userId, string $pageKey, bool $flushed): array
{
    if ($userId <= 0 || !isset($_SESSION)) {
        return [];
    }

    $now = time();
    $granted = [];

    // «Primi passi», appena la sessione comincia: due query, una volta sola.
    if (empty($_SESSION['ach_hello'])) {
        $_SESSION['ach_hello'] = 1;
        $granted = ach_sync($mysqli, $userId, ['always']);
    }

    if ($pageKey !== '' && $pageKey !== 'altro') {
        $seen = isset($_SESSION['ach_sections']) && is_array($_SESSION['ach_sections']) ? $_SESSION['ach_sections'] : [];
        if (!isset($seen[$pageKey])) {
            $seen[$pageKey] = 1;
            $_SESSION['ach_sections'] = $seen;

            $metric = 'sezione_' . $pageKey;
            foreach (ach_catalog($mysqli) as $id => $entry) {
                if ($entry['attivo'] && $entry['metrica'] === $metric && $entry['soglia'] <= 1 && ach_grant($mysqli, $userId, $id)) {
                    $granted[] = $id;
                }
            }
        }
    }

    if ($flushed && $now - (int)($_SESSION['ach_sync_at'] ?? 0) >= ACH_HEARTBEAT_SYNC_SECONDS) {
        $_SESSION['ach_sync_at'] = $now;
        $granted = array_merge($granted, ach_sync($mysqli, $userId));
    }

    return array_values(array_unique($granted));
}

// ─────────────────────────────────────────────────────────────
//  RICHIESTE DEL BROWSER
// ─────────────────────────────────────────────────────────────

/**
 * Il browser chiede un achievement (api/set_achievement.php).
 *
 * - Se è uno di quelli che conta il server, la richiesta vale «controlla
 *   adesso»: si rifà il conto e si assegna solo se torna.
 * - Se è uno di quelli che solo il browser può vedere, si assegna — per il
 *   merch solo col permesso lasciato in sessione dalla pagina di conferma
 *   dell'ordine.
 * - Tutti gli altri si rifiutano.
 * In ogni caso con un limite di richieste al minuto per sessione.
 *
 * @return string success | already_unlocked | not_found | not_eligible | throttled
 */
function ach_client_claim(mysqli $mysqli, int $userId, int $achievementId): string
{
    $entry = ach_catalog($mysqli)[$achievementId] ?? null;
    if ($entry === null || !$entry['attivo']) {
        return 'not_found';
    }

    $unlocked = ach_user_unlocked($mysqli, $userId);
    if (isset($unlocked[$achievementId])) {
        return 'already_unlocked';
    }

    // Ogni richiesta che arriva fin qui costa dei conti o assegna qualcosa:
    // un browser onesto ne fa una manciata, uno script che prova gli id a
    // tappeto si ferma qui.
    if (isset($_SESSION)) {
        $now = time();
        $recent = array_values(array_filter(
            is_array($_SESSION['ach_claims'] ?? null) ? $_SESSION['ach_claims'] : [],
            static fn($at) => $now - (int)$at < 60
        ));
        if (count($recent) >= ACH_CLIENT_CLAIMS_PER_MINUTE) {
            return 'throttled';
        }
        $recent[] = $now;
        $_SESSION['ach_claims'] = $recent;
    }

    if ($entry['metrica'] !== null) {
        if (ach_metric_defs()[$entry['metrica']]['source'] === 'achievements') {
            $value = ach_values_from_unlocked(ach_catalog($mysqli), $unlocked)[$entry['metrica']] ?? 0;
        } else {
            $value = ach_values($mysqli, $userId, [$entry['metrica']])[$entry['metrica']] ?? null;
        }
        if ($value === null || $value < $entry['soglia']) {
            return 'not_eligible';
        }

        return ach_grant($mysqli, $userId, $achievementId) ? 'success' : 'already_unlocked';
    }

    if (!$entry['claim_client']) {
        return 'not_eligible';
    }

    // Merch: api/shop/fake_order.php lascia il segno, la pagina di conferma
    // lo trasforma in questo permesso, che vale una volta.
    $isMerch = $entry['chiave'] === 'photographer' || ($entry['chiave'] === '' && $achievementId === 7);
    if ($isMerch && (int)($_SESSION['shop_achievement_ok'] ?? 0) !== $achievementId) {
        return 'not_eligible';
    }

    if (!ach_grant($mysqli, $userId, $achievementId)) {
        return 'already_unlocked';
    }
    if ($isMerch) {
        unset($_SESSION['shop_achievement_ok']);
    }

    return 'success';
}

// ─────────────────────────────────────────────────────────────
//  PREMI
// ─────────────────────────────────────────────────────────────

/**
 * Incassa i Godos di un achievement sbloccato, o di tutti quelli in attesa.
 *
 * La riga dell'utente si blocca in testa alla transazione: due richieste
 * insieme si mettono in fila, e la seconda trova il premio già incassato.
 * Il premio si legge dal catalogo adesso, non da quello che dice il browser.
 *
 * @return array{ok:bool,code?:string,claimed?:int[],godos?:int,balance?:int}
 */
function ach_claim(mysqli $mysqli, int $userId, ?int $achievementId = null): array
{
    if ($userId <= 0 || !ach_is_v3($mysqli) || !ach_has_claim_cols($mysqli)) {
        return ['ok' => false, 'code' => 'UNAVAILABLE'];
    }

    $claimed = [];
    $godos = 0;

    try {
        $mysqli->begin_transaction();

        $stmt = $mysqli->prepare('SELECT soldi FROM utenti WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$user) {
            $mysqli->rollback();
            return ['ok' => false, 'code' => 'NO_USER'];
        }

        $sql = 'SELECT ua.achievement_id, a.ricompensa
                FROM utenti_achievement ua
                INNER JOIN achievement a ON a.id = ua.achievement_id
                WHERE ua.utente_id = ? AND ua.riscattato = 0
                  AND a.ricompensa > 0 AND a.claim_client = 0';
        if ($achievementId !== null) {
            $sql .= ' AND a.id = ?';
        }
        $stmt = $mysqli->prepare($sql . ' FOR UPDATE');
        if ($achievementId !== null) {
            $stmt->bind_param('ii', $userId, $achievementId);
        } else {
            $stmt->bind_param('i', $userId);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!$rows) {
            $mysqli->rollback();
            return ['ok' => false, 'code' => 'NOTHING_TO_CLAIM'];
        }

        $mark = $mysqli->prepare(
            'UPDATE utenti_achievement SET riscattato = 1, riscattato_il = NOW()
             WHERE utente_id = ? AND achievement_id = ? AND riscattato = 0'
        );
        foreach ($rows as $row) {
            $id = (int)$row['achievement_id'];
            $mark->bind_param('ii', $userId, $id);
            $mark->execute();
            if ($mark->affected_rows === 1) {
                $claimed[] = $id;
                $godos += (int)$row['ricompensa'];
            }
        }
        $mark->close();

        if ($godos > 0) {
            $stmt = $mysqli->prepare('UPDATE utenti SET soldi = soldi + ? WHERE id = ?');
            $stmt->bind_param('ii', $godos, $userId);
            $stmt->execute();
            $stmt->close();
        }

        $mysqli->commit();
    } catch (Throwable $e) {
        try {
            $mysqli->rollback();
        } catch (Throwable $ignored) {
        }
        error_log('[achievements] claim utente ' . $userId . ': ' . $e->getMessage());
        return ['ok' => false, 'code' => 'INTERNAL_ERROR'];
    }

    if ($godos > 0) {
        stats_track($mysqli, $userId, 'godos_earned', $godos);
    }

    return [
        'ok'      => true,
        'claimed' => $claimed,
        'godos'   => $godos,
        'balance' => (int)$user['soldi'] + $godos,
    ];
}

// ─────────────────────────────────────────────────────────────
//  LA PAGINA
// ─────────────────────────────────────────────────────────────

/**
 * In quanti hanno ogni achievement, sul totale di chi ne ha almeno uno.
 *
 * Il conto riguarda tutti gli utenti e non cambia in fretta: resta in un file
 * per dieci minuti, accanto ai timbri del tempo reale (cartella chiusa al web
 * e fuori da git). Senza cartella scrivibile si rifà a ogni apertura.
 *
 * @return array{players:int,owners:array<int,int>}
 */
function ach_rarity(mysqli $mysqli): array
{
    $file = '';
    try {
        require_once __DIR__ . '/realtime.php';
        $dir = rt_dir();
        if ($dir !== '') {
            $file = $dir . '/ach_rarity.php';
        }
    } catch (Throwable $e) {
        $file = '';
    }

    if ($file !== '' && is_file($file) && time() - (int)@filemtime($file) < ACH_RARITY_TTL) {
        $raw = (string)@file_get_contents($file);
        $data = json_decode(substr($raw, strlen(CRIPSUM_RT_GUARD)), true);
        if (is_array($data) && isset($data['players'], $data['owners'])) {
            return ['players' => (int)$data['players'], 'owners' => array_map('intval', $data['owners'])];
        }
    }

    $rows = ach_rows($mysqli, 'SELECT achievement_id, COUNT(*) AS n FROM utenti_achievement GROUP BY achievement_id') ?? [];
    $owners = [];
    foreach ($rows as $row) {
        $owners[(int)$row['achievement_id']] = (int)$row['n'];
    }
    $players = (int)(ach_scalar($mysqli, 'SELECT COUNT(DISTINCT utente_id) FROM utenti_achievement') ?? 0);

    $result = ['players' => $players, 'owners' => $owners];
    if ($file !== '') {
        @file_put_contents($file, CRIPSUM_RT_GUARD . json_encode($result), LOCK_EX);
    }

    return $result;
}

/** Il grado che corrisponde a un punteggio, con la strada verso il prossimo. */
function ach_rank(int $points, string $lang = 'it'): array
{
    $index = 0;
    foreach (ACH_RANKS as $i => $rank) {
        if ($points >= $rank['min']) {
            $index = $i;
        }
    }

    $current = ACH_RANKS[$index];
    $next = ACH_RANKS[$index + 1] ?? null;
    $span = $next ? $next['min'] - $current['min'] : 0;

    return [
        'index'    => $index,
        'count'    => count(ACH_RANKS),
        'name'     => $lang === 'en' ? $current['en'] : $current['it'],
        'min'      => $current['min'],
        'next'     => $next ? ['name' => $lang === 'en' ? $next['en'] : $next['it'], 'min' => $next['min']] : null,
        'progress' => $span > 0 ? round(($points - $current['min']) / $span, 4) : 1,
    ];
}

/**
 * Tutto quello che serve alla pagina, in una richiesta sola.
 *
 * Rifà anche i conti (ach_sync): chi apre la pagina trova assegnato quello
 * che ha già raggiunto, compreso il passato di prima che il motore esistesse.
 * Di un achievement segreto ancora bloccato escono solo categoria, livello e
 * punti: nome, descrizione, icona, soglia e premio restano sul server.
 */
function ach_overview(mysqli $mysqli, int $userId, string $lang = 'it'): array
{
    $lang = $lang === 'en' ? 'en' : 'it';

    // Gli sblocchi fatti qui li festeggia la pagina: niente avviso doppio.
    $fresh = ach_sync($mysqli, $userId, null, ['quiet' => true]);

    $catalog = ach_catalog($mysqli);
    $v3 = ach_is_v3($mysqli);
    $canClaim = $v3 && ach_has_claim_cols($mysqli);
    $unlocked = ach_user_unlocked($mysqli, $userId, true);
    $defs = ach_metric_defs();

    $metrics = [];
    foreach ($catalog as $id => $entry) {
        if ($entry['metrica'] !== null && $entry['attivo'] && !isset($unlocked[$id])) {
            $metrics[] = $entry['metrica'];
        }
    }
    $values = ach_values($mysqli, $userId, $metrics) + ach_values_from_unlocked($catalog, $unlocked);

    $rarity = ach_rarity($mysqli);
    $players = max(0, (int)$rarity['players']);

    $items = [];
    $points = 0;
    $pointsTotal = 0;
    $claimable = 0;
    $claimableGodos = 0;
    $categories = [];
    foreach (ACH_CATEGORIES as $key => $category) {
        $categories[$key] = ['key' => $key, 'name' => $category[$lang], 'icon' => $category['icon'], 'total' => 0, 'unlocked' => 0];
    }

    foreach ($catalog as $id => $entry) {
        $isUnlocked = isset($unlocked[$id]);
        // Tolto dalla pagina: resta visibile solo a chi ce l'ha già.
        if (!$entry['attivo'] && !$isUnlocked) {
            continue;
        }

        $hidden = $entry['segreto'] && !$isUnlocked;
        $owners = (int)($rarity['owners'][$id] ?? 0);

        $item = [
            'id'       => $id,
            'category' => $entry['categoria'],
            'tier'     => $entry['livello'],
            'points'   => $entry['punti'],
            'secret'   => $entry['segreto'],
            'unlocked' => $isUnlocked,
            'order'    => $entry['ordine'],
            // Sotto i dieci giocatori una percentuale non dice niente.
            'owners_pct' => $players >= 10 ? round($owners / $players * 100, 1) : null,
        ];

        if ($hidden) {
            $item += ['key' => '', 'name' => '', 'description' => '', 'image' => '', 'series' => null, 'reward' => 0, 'client' => false, 'progress' => null];
        } else {
            $progress = null;
            if (!$isUnlocked && $entry['metrica'] !== null && isset($values[$entry['metrica']])) {
                $format = $defs[$entry['metrica']]['format'];
                if ($format !== 'bool') {
                    $progress = [
                        'current' => min((int)$values[$entry['metrica']], $entry['soglia']),
                        'target'  => $entry['soglia'],
                        'format'  => $format,
                    ];
                }
            }

            $item += [
                'key'         => $entry['chiave'],
                'name'        => ach_text($entry, 'nome', $lang),
                'description' => ach_text($entry, 'descrizione', $lang),
                'image'       => ach_image_url($entry['img_url']),
                'series'      => $entry['serie'],
                'reward'      => $entry['ricompensa'],
                'client'      => $entry['claim_client'],
                'progress'    => $progress,
            ];
        }

        if ($isUnlocked) {
            $item['unlocked_at'] = $unlocked[$id]['at'];
            $item['unlocked_ts'] = (int)strtotime((string)$unlocked[$id]['at']);
            $item['fresh'] = in_array($id, $fresh, true);
            $item['claimed'] = $unlocked[$id]['claimed'];
            $item['claimable'] = $canClaim && $entry['ricompensa'] > 0 && !$unlocked[$id]['claimed'];
            $points += $entry['punti'];
            if ($item['claimable']) {
                $claimable++;
                $claimableGodos += $entry['ricompensa'];
            }
        }

        $pointsTotal += $entry['punti'];
        $categories[$entry['categoria']]['total']++;
        if ($isUnlocked) {
            $categories[$entry['categoria']]['unlocked']++;
        }

        $items[] = $item;
    }

    $extras = [];
    // «Guarda tutti gli edit» lo conta il browser dai suoi cookie: gli serve
    // sapere quanti sono.
    $edits = ach_scalar($mysqli, "SELECT COUNT(*) FROM edits WHERE stato = 'pubblicato'");
    if ($edits) {
        $extras['edits_total'] = $edits;
    }
    // Il codice Konami si digita in questa pagina: al browser serve l'id da
    // chiedere, finché non lo ha sbloccato. Il nome resta segreto.
    foreach ($catalog as $id => $entry) {
        if ($entry['chiave'] === 'konami' && $entry['attivo'] && $entry['claim_client'] && !isset($unlocked[$id])) {
            $extras['konami_id'] = $id;
        }
    }

    $total = count($items);
    $unlockedCount = count(array_filter($items, static fn($item) => $item['unlocked']));

    return [
        'ok'         => true,
        'v3'         => $v3,
        'lang'       => $lang,
        'items'      => $items,
        'categories' => array_values(array_filter($categories, static fn($c) => $c['total'] > 0)),
        'tiers'      => array_map(static fn($tier) => $tier[$lang], ACH_TIERS),
        'summary'    => [
            'total'           => $total,
            'unlocked'        => $unlockedCount,
            'points'          => $points,
            'points_total'    => $pointsTotal,
            'claimable'       => $claimable,
            'claimable_godos' => $claimableGodos,
            'rank'            => ach_rank($points, $lang),
            'players'         => $players,
        ],
        'fresh'      => $fresh,
        'can_claim'  => $canClaim,
        // Con le statistiche spente tempo, giorni e serie non avanzano.
        'tracking'   => stats_available($mysqli) ? stats_tracking_allowed($mysqli, $userId) : true,
        'extras'     => $extras,
    ];
}

// ─────────────────────────────────────────────────────────────
//  PANNELLO ADMIN
// ─────────────────────────────────────────────────────────────

/**
 * I campi di un achievement come arrivano dal pannello, controllati.
 *
 * Tutto quello che finisce in una colonna passa da un elenco chiuso
 * (categorie, livelli, metriche) o da un intervallo di numeri. Restituisce
 * colonna => valore, oppure una stringa con l'errore da mostrare.
 *
 * @return array<string,mixed>|string
 */
function ach_admin_fields(array $input, bool $v3)
{
    $text = static fn(string $key): string => trim((string)($input[$key] ?? ''));
    $flag = static fn(string $key): int => !empty($input[$key]) && $input[$key] !== '0' ? 1 : 0;

    $name = $text('nome');
    if ($name === '' || mb_strlen($name) > 100) {
        return 'Nome achievement non valido (da 1 a 100 caratteri).';
    }
    if (mb_strlen($text('nome_en')) > 100) {
        return 'Nome inglese troppo lungo (massimo 100 caratteri).';
    }
    if (mb_strlen($text('descrizione')) > 600 || mb_strlen($text('descrizione_en')) > 600) {
        return 'Descrizione troppo lunga (massimo 600 caratteri).';
    }

    $image = $text('img_url');
    if (mb_strlen($image) > ($v3 ? 255 : 50)) {
        return 'Percorso dell’icona troppo lungo.';
    }
    // Un indirizzo http(s) o un percorso del sito: mai `javascript:` e simili.
    if ($image !== '' && !preg_match('#^(https?://[^\s"\'<>]+|[\w\-./ ]+)$#u', $image)) {
        return 'Percorso dell’icona non valido.';
    }

    $fields = [
        'nome'           => $name,
        'nome_en'        => $text('nome_en'),
        'descrizione'    => $text('descrizione') !== '' ? $text('descrizione') : null,
        'descrizione_en' => $text('descrizione_en') !== '' ? $text('descrizione_en') : null,
        'punti'          => max(0, min(100000, (int)($input['punti'] ?? 0))),
        'img_url'        => $image,
    ];

    if (!$v3) {
        return $fields;
    }

    $category = $text('categoria');
    $tier = $text('livello');
    if (!isset(ACH_CATEGORIES[$category])) {
        return 'Categoria non valida.';
    }
    if (!isset(ACH_TIERS[$tier])) {
        return 'Livello non valido.';
    }

    // Come si sblocca: lo conta il server, lo chiede il browser, o solo dal codice.
    $mode = $text('modo');
    $metric = $mode === 'server' ? $text('metrica') : '';
    if ($mode === 'server' && !isset(ach_metric_defs()[$metric])) {
        return 'Scegli cosa deve contare il server.';
    }

    $series = $text('serie');
    if ($series !== '' && !preg_match('/^[a-z0-9\-]{1,40}$/', $series)) {
        return 'La serie è un nome breve in minuscolo, senza spazi (es. «casse»).';
    }

    $client = $mode === 'client' ? 1 : 0;

    return $fields + [
        'categoria'    => $category,
        'livello'      => $tier,
        'segreto'      => $flag('segreto'),
        'metrica'      => $metric !== '' ? $metric : null,
        'soglia'       => max(1, min(100000000, (int)($input['soglia'] ?? 1))),
        'serie'        => $series !== '' ? $series : null,
        'ordine'       => max(0, min(1000000, (int)($input['ordine'] ?? 0))),
        // Quello che chiede il browser non paga mai: vedi ach_catalog().
        'ricompensa'   => $client ? 0 : max(0, min(100000, (int)($input['ricompensa'] ?? 0))),
        'claim_client' => $client,
        'attivo'       => $flag('attivo'),
    ];
}

/** Elenchi per i menu del pannello: categorie, livelli e metriche con il loro nome. */
function ach_admin_meta(): array
{
    $metrics = [];
    foreach (ach_metric_defs() as $key => $def) {
        $metrics[$key] = $def['it'];
    }

    return [
        'categories' => array_map(static fn($c) => $c['it'], ACH_CATEGORIES),
        'tiers'      => array_map(static fn($t) => $t['it'], ACH_TIERS),
        'tier_points' => array_map(static fn($t) => $t['punti'], ACH_TIERS),
        'metrics'    => $metrics,
    ];
}
