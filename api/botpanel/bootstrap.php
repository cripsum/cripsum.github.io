<?php
declare(strict_types=1);

/**
 * Base comune agli endpoint del pannello del bot.
 *
 * Qui dentro non c'e' nessun controllo di accesso "di default": ogni
 * endpoint chiama per prima cosa botpanel_require_user() e poi, per tutto
 * cio' che riguarda un server, botpanel_access(). Le regole stanno in
 * includes/botpanel/core.php.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/botpanel/core.php';
require_once __DIR__ . '/../../includes/botpanel/modules.php';

if (!botpanel_tables_ready($mysqli)) {
    botpanel_fail(
        'not_installed',
        'Mancano le tabelle del pannello: esegui migrations/2026_10_11_discord_multiserver.sql.',
        503
    );
}

/**
 * Il server indicato nella richiesta, dalla query o dal corpo.
 */
function botpanel_request_guild(): string
{
    $guildId = (string)($_GET['guild'] ?? (botpanel_input()['guild'] ?? ''));

    if (!botpanel_is_snowflake($guildId)) {
        botpanel_fail('bad_guild', 'Server non valido.', 400);
    }

    return $guildId;
}

/**
 * Nel server di Cripsum il pannello decide cosa scrive il bot a nome del
 * sito: oltre ai permessi su Discord serve un account amministratore, come
 * e' sempre stato per i comandi /embed e /welcome.
 *
 * @param array{ruolo: string} $user
 * @param array{is_home: bool} $access
 */
function botpanel_require_home_admin(array $user, array $access): void
{
    if (!empty($access['is_home']) && !in_array($user['ruolo'], ['admin', 'owner'], true)) {
        botpanel_fail(
            'home_admin_required',
            'Nel server di Cripsum può salvare solo un amministratore del sito.',
            403
        );
    }
}

/**
 * Prima di ogni azione che fa fare qualcosa al bot (pubblicare, chiudere un
 * ticket, lanciare un giveaway): richiesta partita dalla pagina, limite di
 * frequenza, permessi riletti adesso su Discord, e nel server di Cripsum
 * solo un amministratore del sito.
 *
 * @param array{id: int, ruolo: string, discord_id: string} $user
 * @return array{access: array<string, mixed>, input: array<string, mixed>}
 */
function botpanel_begin_action(array $user, string $guildId, string $bucket = 'send', int $max = 20): array
{
    botpanel_require_write();
    botpanel_rate($bucket, $max, 60);

    $access = botpanel_access($user, $guildId, true);
    cripsum_release_session();
    botpanel_require_home_admin($user, $access);

    return ['access' => $access, 'input' => botpanel_input()];
}

/**
 * Chiede al bot di fare qualcosa a nome di chi usa il pannello. Se il bot
 * dice di no, la richiesta si ferma con il suo motivo.
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function botpanel_bot_action(string $path, array $user, string $guildId, array $payload, int $timeout = 15): array
{
    $response = botpanel_bot($path, ['guild_id' => $guildId, 'actor_discord_id' => $user['discord_id']] + $payload, $timeout);

    if ($response === null) {
        botpanel_fail('bot_unreachable', 'Il bot non risponde: riprova fra poco.', 503);
    }

    if (empty($response['success'])) {
        botpanel_fail('bot_refused', 'Poppy non ha potuto farlo.', 422, [
            'reason' => (string)($response['error'] ?? 'failed'),
            'detail' => mb_substr((string)($response['detail'] ?? ''), 0, 200),
        ]);
    }

    return $response;
}

/**
 * I messaggi che Poppy ha pubblicato per il pannello (pannello dei ticket,
 * menu dei ruoli, pannello delle candidature), per riscriverli sul posto.
 *
 * @return array<string, array{channel_id: string, message_id: string}>
 */
function botpanel_posts(mysqli $mysqli, string $guildId): array
{
    if (!auth_table_exists($mysqli, 'discord_bot_posts')) {
        return [];
    }

    $stmt = $mysqli->prepare('SELECT post_key, channel_id, message_id FROM discord_bot_posts WHERE guild_id = ?');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('s', $guildId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $posts = [];
    foreach ($rows as $row) {
        $posts[(string)$row['post_key']] = ['channel_id' => (string)$row['channel_id'], 'message_id' => (string)$row['message_id']];
    }

    return $posts;
}

function botpanel_post_save(mysqli $mysqli, string $guildId, string $key, string $channelId, string $messageId): void
{
    if (!auth_table_exists($mysqli, 'discord_bot_posts')
        || !botpanel_is_snowflake($channelId)
        || !botpanel_is_snowflake($messageId)
    ) {
        return;
    }

    $stmt = $mysqli->prepare(
        'INSERT INTO discord_bot_posts (guild_id, post_key, channel_id, message_id) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE channel_id = VALUES(channel_id), message_id = VALUES(message_id)'
    );

    if ($stmt) {
        $stmt->bind_param('ssss', $guildId, $key, $channelId, $messageId);
        $stmt->execute();
        $stmt->close();
    }
}

/** Solo indirizzi di Discord: finiscono nei link della pagina. */
function botpanel_discord_url($url): ?string
{
    $url = (string)$url;
    return str_starts_with($url, 'https://discord.com/channels/') ? $url : null;
}

/**
 * Un modulo e' acceso? Se e' stato salvato vale l'interruttore; altrimenti
 * il valore di partenza, che nel server di Cripsum e' "come prima del
 * pannello" (lo decide il bot dal suo .env).
 *
 * @param array<string, mixed> $definition
 * @param array{enabled: bool}|null $saved
 */
function botpanel_module_enabled(array $definition, ?array $saved, bool $isHome): bool
{
    if (empty($definition['toggle'])) {
        return true;
    }

    if ($saved !== null) {
        return $saved['enabled'];
    }

    // Stessi valori di presence-bot/src/modules.js.
    $homeLegacy = ['moderation', 'messages', 'tickets', 'giveaway', 'restricted'];

    return $isHome
        ? in_array($definition['id'] ?? '', $homeLegacy, true)
        : !empty($definition['default_enabled']);
}
