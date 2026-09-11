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
        'SELECT id, username, display_name, profile_visibility, is_premium, soldi
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
