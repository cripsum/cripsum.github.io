<?php
declare(strict_types=1);

/**
 * L'accesso su bio.cripsum.com passa da cripsum.com.
 *
 * Il cookie della sessione (__Host-cripsum_session) vale solo per l'host che
 * l'ha dato, per sicurezza, e deve restare cosi': bio non vede la sessione di
 * cripsum.com e ne ha una sua. Per aprirla, cripsum.com da' a bio un biglietto
 * usa e getta, come fanno i login "Accedi con...":
 *
 *   1. bio/accedi.php mette nel browser un valore casuale (lo state, in un
 *      cookie di bio) e manda a cripsum.com/auth/sso.php con il suo hash.
 *   2. auth/sso.php, se si e' dentro (altrimenti dopo il login di sempre),
 *      scrive nel database l'hash di un biglietto legato a chi e' dentro, alla
 *      sua riga di user_sessions e allo state, e rimanda su bio.
 *   3. bio/callback.php usa il biglietto una volta sola, entro 60 secondi, e
 *      solo se lo state del browser e' lo stesso: nessuno puo' far entrare un
 *      altro nel proprio account mandandogli un link.
 *
 * La sessione di bio resta legata alla riga di cripsum.com
 * (auth_sync_linked_session): uscendo da uno dei due si esce da entrambi.
 *
 * L'accesso automatico: chi e' dentro su cripsum.com ha un cookie-indizio
 * senza segreti (cripsum_sso_hint_cookie). Se bio lo vede e qui non c'e'
 * nessuno dentro, fa da solo il giro con auto=1: cripsum.com non chiede mai il
 * login, e se non c'e' nessuno risponde "none", bio spegne l'indizio e per
 * dieci minuti non riprova.
 *
 * Senza la migration 2026_10_18_bio_accesso.sql tutto questo e' spento: su bio
 * «Accedi» porta al login di cripsum.com come prima.
 */

require_once __DIR__ . '/bio.php';

/** Quanto vale un biglietto, in secondi. */
const CRIPSUM_SSO_TICKET_TTL = 60;

function cripsum_sso_available(mysqli $mysqli): bool
{
    return auth_table_exists($mysqli, 'auth_sso_tickets');
}

/** Il cookie dello state: __Host- in https, come la sessione. */
function cripsum_sso_state_cookie_name(): string
{
    return !empty(session_get_cookie_params()['secure']) ? '__Host-bio_sso' : 'bio_sso_dev';
}

/**
 * Mette nel browser uno state nuovo e ne restituisce l'hash, da mandare a
 * cripsum.com. Il cookie ricorda anche se era un accesso automatico.
 */
function cripsum_sso_start_state(bool $auto): string
{
    $state = bin2hex(random_bytes(32));

    setcookie(cripsum_sso_state_cookie_name(), $state . ($auto ? '.a' : ''), [
        'expires' => time() + 300,
        'path' => '/',
        'secure' => !empty(session_get_cookie_params()['secure']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return hash('sha256', $state);
}

/**
 * Legge lo state del browser e lo cancella (vale una volta sola).
 * Restituisce [state, era un accesso automatico].
 */
function cripsum_sso_take_state(): array
{
    $name = cripsum_sso_state_cookie_name();
    $raw = (string)($_COOKIE[$name] ?? '');

    setcookie($name, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty(session_get_cookie_params()['secure']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[$name]);

    $auto = str_ends_with($raw, '.a');
    $state = $auto ? substr($raw, 0, -2) : $raw;

    return [preg_match('/^[a-f0-9]{64}$/', $state) ? $state : '', $auto];
}

/**
 * Scrive un biglietto per $userId e lo restituisce (solo qui esiste in
 * chiaro: nel database c'e' l'hash). null se la tabella non c'e'.
 */
function cripsum_sso_issue(mysqli $mysqli, int $userId, ?int $userSessionId, string $stateHash, string $returnPath): ?string
{
    if ($userId <= 0 || !preg_match('/^[a-f0-9]{64}$/', $stateHash) || !cripsum_sso_available($mysqli)) {
        return null;
    }

    // I biglietti vecchi non servono a niente: si tengono un giorno, per capire
    // cosa e' successo se qualcuno dice che l'accesso non gli e' riuscito.
    $mysqli->query("DELETE FROM auth_sso_tickets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");

    $ticket = bin2hex(random_bytes(32));
    $ticketHash = hash('sha256', $ticket);
    $returnPath = cripsum_safe_return_path($returnPath);
    $ttl = CRIPSUM_SSO_TICKET_TTL;

    $stmt = $mysqli->prepare("
        INSERT INTO auth_sso_tickets
            (ticket_hash, state_hash, user_id, user_session_id, target, return_path, created_at, expires_at)
        VALUES (?, ?, ?, ?, 'bio', ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ssiisi', $ticketHash, $stateHash, $userId, $userSessionId, $returnPath, $ttl);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok ? $ticket : null;
}

/**
 * Usa un biglietto: una volta sola, in tempo, con lo state di questo browser.
 * Restituisce la riga (user_id, user_session_id, return_path) o null.
 */
function cripsum_sso_redeem(mysqli $mysqli, string $ticket, string $state): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $ticket) || !preg_match('/^[a-f0-9]{64}$/', $state) || !cripsum_sso_available($mysqli)) {
        return null;
    }

    $ticketHash = hash('sha256', $ticket);
    $stateHash = hash('sha256', $state);

    // Segnarlo come usato e controllarlo sono la stessa query: due richieste
    // con lo stesso biglietto non possono passare tutte e due.
    $use = $mysqli->prepare("
        UPDATE auth_sso_tickets
        SET used_at = NOW()
        WHERE ticket_hash = ?
          AND state_hash = ?
          AND target = 'bio'
          AND used_at IS NULL
          AND expires_at > NOW()
        LIMIT 1
    ");
    if (!$use) {
        return null;
    }

    $use->bind_param('ss', $ticketHash, $stateHash);
    $use->execute();
    $used = $use->affected_rows === 1;
    $use->close();

    if (!$used) {
        return null;
    }

    $stmt = $mysqli->prepare("SELECT user_id, user_session_id, return_path FROM auth_sso_tickets WHERE ticket_hash = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $ticketHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Su una pagina di bio, per un ospite con l'indizio: va a prendersi la
 * sessione da cripsum.com senza chiedere niente. Si chiama prima di stampare
 * la pagina; se non serve non fa niente.
 */
function cripsum_bio_auto_login(mysqli $mysqli): void
{
    if (cripsum_site() !== 'bio' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !empty($_SESSION['user_id'])) {
        return;
    }
    if (empty($_COOKIE[CRIPSUM_SSO_HINT_COOKIE]) || !empty($_COOKIE[CRIPSUM_BIO_NOAUTO_COOKIE]) || isset($_GET['preview_mode'])) {
        return;
    }
    if (!cripsum_sso_available($mysqli)) {
        return;
    }

    $return = cripsum_safe_return_path((string)($_SERVER['REQUEST_URI'] ?? '/'));
    header('Cache-Control: no-store');
    header('Location: /accedi?' . http_build_query(['auto' => 1, 'return' => $return]));
    exit;
}

/**
 * Le pagine di bio/ girano solo su bio: aperte da cripsum.com portano allo
 * stesso indirizzo su bio.
 */
function cripsum_bio_only(string $path): void
{
    if (cripsum_site() === 'bio') {
        return;
    }

    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . cripsum_bio_url($path . ($query !== '' ? '?' . $query : '')));
    exit;
}
