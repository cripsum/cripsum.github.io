<?php
declare(strict_types=1);

/**
 * Base comune agli endpoint chiamati dal Presence Bot.
 *
 * Ogni endpoint deve chiamare bot_require_key() come prima cosa: la chiave
 * condivisa arriva nell'header X-Cripsum-Bot-Key ed e' la stessa che il sito
 * usa per parlare con il bot (includes/bot_client.php).
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';
require_once __DIR__ . '/../../includes/security_helpers.php';
require_once __DIR__ . '/../../includes/admin/admin_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/**
 * @param array<string, mixed> $payload
 */
function bot_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bot_fail(string $message, int $status = 400): void
{
    bot_json(['ok' => false, 'error' => $message], $status);
}

function bot_require_key(): void
{
    $apiKey = (string)($_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '');
    $expected = defined('CRIPSUM_BOT_API_KEY') ? (string)CRIPSUM_BOT_API_KEY : '';

    if ($apiKey === '' || $expected === '' || !hash_equals($expected, $apiKey)) {
        bot_fail('Access denied. Invalid or missing X-Cripsum-Bot-Key.', 403);
    }
}

function bot_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        bot_fail('Method not allowed.', 405);
    }
}

/**
 * Corpo JSON della richiesta, sempre come array.
 *
 * @return array<string, mixed>
 */
function bot_input(): array
{
    static $input = null;

    if ($input === null) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        $input = is_array($decoded) ? $decoded : [];

        // Il bot manda JSON; il fallback su $_POST serve alle richieste
        // form-encoded e agli script di prova da riga di comando.
        if ($input === [] && !empty($_POST)) {
            $input = $_POST;
        }
    }

    return $input;
}

/**
 * Legge un Discord ID da GET o dal corpo JSON, validandone il formato.
 */
function bot_discord_id(string $field, bool $required = true): string
{
    $body = bot_input();
    $value = trim((string)($_GET[$field] ?? $body[$field] ?? ''));

    if ($value === '') {
        if ($required) {
            bot_fail('Missing ' . $field . '.', 400);
        }
        return '';
    }

    if (!preg_match('/^\d{15,25}$/', $value)) {
        bot_fail('Invalid Discord ID.', 400);
    }

    return $value;
}

/**
 * Utente Cripsum collegato a un account Discord.
 *
 * @return array<string, mixed>|null
 */
function bot_find_user(mysqli $mysqli, string $discordId): ?array
{
    $stmt = $mysqli->prepare(
        'SELECT id, username, display_name, profile_visibility, is_premium, soldi,
                ruolo, isBannato, discord_id
         FROM utenti WHERE discord_id = ? LIMIT 1'
    );

    if (!$stmt) {
        bot_fail('Database query preparation failed.', 500);
    }

    $stmt->bind_param('s', $discordId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

/**
 * Un profilo non pubblico e' visibile solo al proprietario, come in
 * api/bot/profile.php.
 *
 * @param array<string, mixed> $user
 */
function bot_profile_is_private(array $user, bool $isSelf): bool
{
    if ($isSelf) {
        return false;
    }

    return (string)($user['profile_visibility'] ?? 'public') !== 'public';
}

function bot_display_name(array $user): string
{
    $display = trim((string)($user['display_name'] ?? ''));

    return $display !== '' ? $display : (string)($user['username'] ?? '');
}

/**
 * Utente Cripsum per id numerico.
 *
 * @return array<string, mixed>|null
 */
function bot_find_user_by_id(mysqli $mysqli, int $userId): ?array
{
    $stmt = $mysqli->prepare(
        'SELECT id, username, display_name, profile_visibility, is_premium, soldi,
                ruolo, isBannato, discord_id
         FROM utenti WHERE id = ? LIMIT 1'
    );

    if (!$stmt) {
        bot_fail('Database query preparation failed.', 500);
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

/**
 * Utente Cripsum per username.
 *
 * @return array<string, mixed>|null
 */
function bot_find_user_by_username(mysqli $mysqli, string $username): ?array
{
    $stmt = $mysqli->prepare(
        'SELECT id, username, display_name, profile_visibility, is_premium, soldi,
                ruolo, isBannato, discord_id
         FROM utenti WHERE username = ? LIMIT 1'
    );

    if (!$stmt) {
        bot_fail('Database query preparation failed.', 500);
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

/**
 * Chi sta eseguendo l'azione.
 *
 * Il bot controlla il ruolo staff su Discord prima di chiamare; qui si
 * controlla il lato che conta davvero, cioe' che quel Discord ID sia legato a
 * un account Cripsum con ruolo admin o owner e non bannato. Nessun campo
 * inviato dal chiamante puo' concedere privilegi: l'unico dato usato e'
 * l'identificativo, il ruolo si legge dal database.
 *
 * @return array<string, mixed>
 */
function bot_require_actor(mysqli $mysqli, string $field = 'actor_discord_id'): array
{
    $discordId = bot_discord_id($field);
    $actor = bot_find_user($mysqli, $discordId);

    if (!$actor) {
        bot_fail('The acting Discord account is not linked to a Cripsum account.', 403);
    }

    if ((int)($actor['isBannato'] ?? 0) === 1) {
        bot_fail('The acting account is banned.', 403);
    }

    if (!in_array((string)($actor['ruolo'] ?? ''), ['admin', 'owner'], true)) {
        bot_fail('The acting account is not a Cripsum administrator.', 403);
    }

    return $actor;
}

/**
 * Registra l'azione nel log admin del sito, a nome dell'account che l'ha
 * eseguita, annotando che e' arrivata da Discord.
 *
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $details
 */
function bot_log_action(mysqli $mysqli, array $actor, string $action, ?int $targetUserId = null, array $details = []): void
{
    $details['via'] = 'discord';
    $details['actor_discord_id'] = (string)($actor['discord_id'] ?? '');

    admin_log($mysqli, (int)$actor['id'], $action, $targetUserId, $details);
}

/**
 * L'utente di servizio con cui il bot scrive sul sito (di norma "poppy").
 *
 * CRIPSUM_BOT_SERVICE_USER accetta sia l'identificativo numerico sia lo
 * username: l'id e' piu' solido, perche' non si rompe se l'utente viene
 * rinominato. L'utente deve esistere gia': non viene creato al volo.
 *
 * @return array<string, mixed>|null
 */
function bot_service_user(mysqli $mysqli): ?array
{
    static $service = null;

    if ($service === null) {
        $configured = defined('CRIPSUM_BOT_SERVICE_USER')
            ? trim((string)CRIPSUM_BOT_SERVICE_USER)
            : 'poppy';

        $service = ctype_digit($configured)
            ? bot_find_user_by_id($mysqli, (int)$configured)
            : bot_find_user_by_username($mysqli, $configured);

        $service = $service ?: false;
    }

    return $service ?: null;
}

/**
 * Valore di testo dal corpo JSON o dalla query, con limite di lunghezza.
 */
function bot_text(string $field, int $maxLength = 500, bool $required = false, string $default = ''): string
{
    $body = bot_input();
    $value = trim((string)($_GET[$field] ?? $body[$field] ?? ''));

    if ($value === '') {
        if ($required) {
            bot_fail('Missing ' . $field . '.', 400);
        }
        return $default;
    }

    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

/**
 * Identificativo Discord generico (canale, messaggio, thread, guild).
 */
function bot_snowflake(string $field, bool $required = true): string
{
    $body = bot_input();
    $value = trim((string)($_GET[$field] ?? $body[$field] ?? ''));

    if ($value === '') {
        if ($required) {
            bot_fail('Missing ' . $field . '.', 400);
        }
        return '';
    }

    if (!preg_match('/^\d{15,25}$/', $value)) {
        bot_fail('Invalid ' . $field . '.', 400);
    }

    return $value;
}
