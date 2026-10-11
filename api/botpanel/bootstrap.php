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
