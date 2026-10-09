<?php
declare(strict_types=1);

/**
 * Modalita' manutenzione.
 *
 * Si accende e si spegne dal pannello admin (Sito > Manutenzione), con un
 * motivo da mostrare a chi arriva e l'elenco degli utenti che possono
 * continuare a usare il sito mentre e' chiuso.
 *
 * Lo stato sta nel database, tabella site_maintenance (una riga sola):
 * migrations/2026_10_09_site_maintenance.sql. Finche' la tabella non c'e' il
 * sito e' semplicemente aperto.
 *
 * Il controllo lo fa cripsum_maintenance_guard(), chiamata in fondo a
 * config/database.php: tutte le pagine e tutte le API che usano il database
 * passano di li', con la sessione gia' aperta. Durante la manutenzione entra
 * solo chi e' nell'elenco. Restano raggiungibili le pagine dell'accesso (chi
 * e' nell'elenco deve poter fare il login), le chiamate da server a server
 * che non sono persone (bot, webhook dei pagamenti) e gli script da riga di
 * comando.
 *
 * Se resti chiuso fuori, da phpMyAdmin:
 *     UPDATE site_maintenance SET enabled = 0;
 */

const CRIPSUM_MAINTENANCE_REASON_MAX = 600;
const CRIPSUM_MAINTENANCE_ALLOWED_MAX = 50;

/**
 * Le pagine dell'accesso, sempre raggiungibili: senza, chi e' nell'elenco e
 * non e' loggato non avrebbe modo di farsi riconoscere. Chi fa il login e
 * non e' nell'elenco vede comunque la pagina di manutenzione subito dopo.
 */
const CRIPSUM_MAINTENANCE_LOGIN_SCRIPTS = [
    '/it/accedi.php',
    '/en/accedi.php',
    '/it/verifica-2fa.php',
    '/en/verifica-2fa.php',
    '/it/google_login.php',
    '/en/google_login.php',
    '/it/google_callback.php',
    '/en/google_callback.php',
    '/logout.php',
];

/** @return array{enabled:bool, reason_it:string, reason_en:string, since:?int, until:?int, allowed:int[], updated_by:int, updated_at:?int} */
function cripsum_maintenance_default(): array
{
    return [
        'enabled' => false,
        'reason_it' => '',
        'reason_en' => '',
        'since' => null,
        'until' => null,
        'allowed' => [],
        'updated_by' => 0,
        'updated_at' => null,
    ];
}

/** Porta un array qualsiasi alla forma di cripsum_maintenance_default(), scartando quello che non torna. */
function cripsum_maintenance_normalize(array $raw): array
{
    $text = static function ($value): string {
        $value = is_string($value) ? $value : '';
        $value = trim(str_replace(["\r\n", "\r"], "\n", strip_tags($value)));
        // Al massimo due a capo di fila: e' un motivo, non una pagina.
        $value = (string)preg_replace("/\n{3,}/", "\n\n", $value);

        return function_exists('mb_substr')
            ? mb_substr($value, 0, CRIPSUM_MAINTENANCE_REASON_MAX)
            : substr($value, 0, CRIPSUM_MAINTENANCE_REASON_MAX);
    };
    $time = static function ($value): ?int {
        $value = is_numeric($value) ? (int)$value : 0;

        return $value > 0 ? $value : null;
    };

    $allowed = [];
    foreach ((array)($raw['allowed'] ?? []) as $id) {
        $id = is_numeric($id) ? (int)$id : 0;
        if ($id > 0) {
            $allowed[$id] = $id;
        }
    }

    return [
        'enabled' => !empty($raw['enabled']),
        'reason_it' => $text($raw['reason_it'] ?? ''),
        'reason_en' => $text($raw['reason_en'] ?? ''),
        'since' => $time($raw['since'] ?? null),
        'until' => $time($raw['until'] ?? null),
        'allowed' => array_slice(array_values($allowed), 0, CRIPSUM_MAINTENANCE_ALLOWED_MAX),
        'updated_by' => max(0, (int)($raw['updated_by'] ?? 0)),
        'updated_at' => $time($raw['updated_at'] ?? null),
    ];
}

/** La riga di site_maintenance, o null se manca la tabella, manca la riga o la query non va. */
function cripsum_maintenance_row(mysqli $mysqli): ?array
{
    try {
        $result = $mysqli->query(
            'SELECT enabled, reason_it, reason_en, since_ts, until_ts, allowed_ids, updated_by, updated_ts
             FROM site_maintenance WHERE id = 1'
        );
        if (!$result instanceof mysqli_result) {
            return null;
        }
        $row = $result->fetch_assoc();
        $result->free();

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        // Tabella non ancora creata: non e' un errore, e' solo "sito aperto".
        return null;
    }
}

/** Vero se la tabella esiste: il pannello lo usa per dire di applicare la migrazione. */
function cripsum_maintenance_ready(mysqli $mysqli): bool
{
    try {
        $result = $mysqli->query('SELECT 1 FROM site_maintenance LIMIT 1');
        if ($result instanceof mysqli_result) {
            $result->free();

            return true;
        }
    } catch (Throwable $e) {
        // sotto
    }

    return false;
}

/**
 * Lo stato di adesso. Senza database, senza tabella o con una query che non
 * va vuol dire "sito aperto": un guasto qui non deve mai chiudere il sito a
 * tutti.
 */
function cripsum_maintenance_state(?mysqli $mysqli, bool $fresh = false): array
{
    static $state = null;
    if ($state !== null && !$fresh) {
        return $state;
    }
    if (!$mysqli instanceof mysqli) {
        return cripsum_maintenance_default();
    }

    $row = cripsum_maintenance_row($mysqli);
    if ($row === null) {
        return $state = cripsum_maintenance_default();
    }

    $allowed = json_decode((string)($row['allowed_ids'] ?? '[]'), true);

    return $state = cripsum_maintenance_normalize([
        'enabled' => (int)($row['enabled'] ?? 0) === 1,
        'reason_it' => (string)($row['reason_it'] ?? ''),
        'reason_en' => (string)($row['reason_en'] ?? ''),
        'since' => $row['since_ts'] ?? null,
        'until' => $row['until_ts'] ?? null,
        'allowed' => is_array($allowed) ? $allowed : [],
        'updated_by' => $row['updated_by'] ?? 0,
        'updated_at' => $row['updated_ts'] ?? null,
    ]);
}

/**
 * Salva lo stato. Chi salva entra sempre nell'elenco di chi puo' passare:
 * non ci si puo' chiudere fuori da soli.
 *
 * @return array|null lo stato salvato, null se non si riesce a scrivere (tabella mancante)
 */
function cripsum_maintenance_save(mysqli $mysqli, array $input, int $actorId): ?array
{
    $before = cripsum_maintenance_state($mysqli, true);
    $state = cripsum_maintenance_normalize($input);

    if ($actorId > 0 && !in_array($actorId, $state['allowed'], true)) {
        array_unshift($state['allowed'], $actorId);
        $state['allowed'] = array_slice($state['allowed'], 0, CRIPSUM_MAINTENANCE_ALLOWED_MAX);
    }

    $now = time();
    // "Da quando" e' il momento in cui e' stata accesa, non l'ultima modifica.
    $state['since'] = $state['enabled'] ? ($before['enabled'] && $before['since'] ? $before['since'] : $now) : null;
    if ($state['until'] !== null && $state['until'] <= $now) {
        $state['until'] = null;
    }

    $enabled = $state['enabled'] ? 1 : 0;
    $reasonIt = $state['reason_it'];
    $reasonEn = $state['reason_en'];
    $since = $state['since'];
    $until = $state['until'];
    $allowed = (string)json_encode($state['allowed']);
    $updatedBy = max(0, $actorId);

    try {
        // Una riga sola, sempre la numero 1: REPLACE la crea se manca e la riscrive se c'e'.
        $stmt = $mysqli->prepare(
            'REPLACE INTO site_maintenance
                (id, enabled, reason_it, reason_en, since_ts, until_ts, allowed_ids, updated_by, updated_ts)
             VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('issiisii', $enabled, $reasonIt, $reasonEn, $since, $until, $allowed, $updatedBy, $now);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return null;
        }
    } catch (Throwable $e) {
        error_log('[maintenance] ' . $e->getMessage());

        return null;
    }

    return cripsum_maintenance_state($mysqli, true);
}

function cripsum_maintenance_user_allowed(array $state, ?int $userId): bool
{
    return $userId !== null && $userId > 0 && in_array($userId, $state['allowed'], true);
}

/** Il percorso dello script che sta girando, da "/" in giu' (/it/accedi.php), a prescindere dall'indirizzo chiesto. */
function cripsum_maintenance_script(): string
{
    $root = str_replace('\\', '/', (string)realpath(dirname(__DIR__)));
    $script = str_replace('\\', '/', (string)realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')));

    if ($root !== '' && $script !== '' && str_starts_with($script, $root . '/')) {
        return substr($script, strlen($root));
    }

    return (string)($_SERVER['SCRIPT_NAME'] ?? '');
}

/**
 * Vero per le richieste che non vengono da una persona: riga di comando, bot
 * e webhook dei pagamenti (un pagamento gia' fatto va registrato comunque).
 */
function cripsum_maintenance_is_machine(): bool
{
    return PHP_SAPI === 'cli' || defined('CRIPSUM_STATELESS_REQUEST') || defined('CRIPSUM_MAINTENANCE_EXEMPT');
}

/**
 * La decisione, senza guardare l'ambiente: vero se questa richiesta va
 * fermata. $script e' il percorso dello script (/it/home.php), $machine dice
 * se a chiedere non e' una persona.
 */
function cripsum_maintenance_blocks(array $state, ?int $userId, string $script, bool $machine = false): bool
{
    if (empty($state['enabled']) || $machine) {
        return false;
    }
    if (in_array($script, CRIPSUM_MAINTENANCE_LOGIN_SCRIPTS, true)) {
        return false;
    }

    return !cripsum_maintenance_user_allowed($state, $userId);
}

/** La lingua in cui mostrare la pagina: quella dell'indirizzo, poi quella ricordata, poi quella del browser. */
function cripsum_maintenance_lang(): string
{
    $first = explode('/', trim((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? ''), '/'))[0] ?? '';
    if (in_array($first, ['it', 'en'], true)) {
        return $first;
    }
    if (function_exists('cripsum_preferred_lang')) {
        return cripsum_preferred_lang();
    }

    return 'it';
}

/** Vero se la richiesta aspetta dati e non una pagina. */
function cripsum_maintenance_wants_json(): bool
{
    $script = cripsum_maintenance_script();
    if (str_starts_with($script, '/api/')) {
        return true;
    }

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $ajax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

    return $ajax || (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html'));
}

/**
 * Risponde 503 e termina: JSON alle API, niente agli script che servono file
 * (avatar, immagini), la pagina di manutenzione a tutto il resto.
 */
function cripsum_maintenance_respond(array $state): void
{
    $lang = cripsum_maintenance_lang();
    $retry = 600;
    if ($state['until'] !== null && $state['until'] > time()) {
        $retry = max(60, min(86400, $state['until'] - time()));
    }

    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: ' . $retry);
        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex');
    }

    if (cripsum_maintenance_wants_json()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'ok' => false,
            'status' => 'error',
            'code' => 'MAINTENANCE',
            'message' => $lang === 'en' ? 'The site is under maintenance. Try again later.' : 'Il sito è in manutenzione. Riprova più tardi.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // includes/get_pfp.php e simili: chi li chiede vuole un file, non una pagina.
    if (str_starts_with(cripsum_maintenance_script(), '/includes/')) {
        exit;
    }

    require_once __DIR__ . '/maintenance_page.php';
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo cripsum_maintenance_page($state, $lang, [
        'username' => isset($_SESSION['user_id']) ? (string)($_SESSION['username'] ?? '') : '',
    ]);
    exit;
}

/**
 * Il controllo vero e proprio. Va chiamato con la sessione aperta (serve
 * sapere chi sta chiedendo) e il database collegato.
 *
 * Lascia anche un segno nella sessione di chi viene fermato: lo leggono gli
 * endpoint del tempo reale, che il database non lo aprono
 * (cripsum_maintenance_light_guard).
 */
function cripsum_maintenance_guard(?mysqli $mysqli): void
{
    $state = cripsum_maintenance_state($mysqli);
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $blocked = $state['enabled'] && cripsum_maintenance_blocks($state, $userId, cripsum_maintenance_script(), cripsum_maintenance_is_machine());

    if (session_status() === PHP_SESSION_ACTIVE) {
        $refused = $state['enabled'] && !cripsum_maintenance_user_allowed($state, $userId);
        if ($refused) {
            $_SESSION['cripsum_maintenance_blocked'] = time();
        } elseif (isset($_SESSION['cripsum_maintenance_blocked'])) {
            unset($_SESSION['cripsum_maintenance_blocked']);
        }
    }

    if ($blocked) {
        cripsum_maintenance_respond($state);
    }
}

/**
 * Per gli endpoint del tempo reale (api/rt/_light.php), che rispondono
 * migliaia di volte al minuto e per questo non aprono il database: non
 * possono leggere lo stato, ma la sessione sa se l'ultima richiesta "vera" di
 * questo visitatore e' stata fermata dalla manutenzione. Il segno lo toglie
 * la prima richiesta che passa; dopo un quarto d'ora scade comunque.
 */
function cripsum_maintenance_light_guard(): void
{
    $at = (int)($_SESSION['cripsum_maintenance_blocked'] ?? 0);
    if ($at <= 0 || time() - $at > 900 || cripsum_maintenance_is_machine()) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 60');
        header('Cache-Control: no-store, private');
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'status' => 'error', 'code' => 'MAINTENANCE']);
    exit;
}
