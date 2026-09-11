<?php
declare(strict_types=1);

/**
 * Dettaglio di un ticket con la sua conversazione: serve al bot per la
 * trascrizione alla chiusura e per riallineare un thread.
 *
 * Lo leggono solo lo staff o il proprietario del ticket.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/ticket_helpers.php';

bot_require_key();
bot_require_method('GET');

$threadId = bot_snowflake('thread_id', false);
$ticketId = bot_text('ticket_id', 12);
$actorDiscordId = bot_discord_id('actor_discord_id');

$ticket = cripsum_ticket_find($mysqli, $ticketId, $threadId);
if (!$ticket) {
    bot_fail('Ticket not found.', 404);
}

$actor = bot_find_user($mysqli, $actorDiscordId);
if (!$actor) {
    bot_fail('The acting Discord account is not linked to a Cripsum account.', 403);
}

$isStaff = in_array((string)($actor['ruolo'] ?? ''), ['admin', 'owner'], true);
if (!$isStaff && (int)$actor['id'] !== (int)$ticket['user_id']) {
    bot_fail('Not allowed to read this ticket.', 403);
}

$messages = array_map(static function (array $row): array {
    return [
        'id' => (int)$row['id'],
        'author' => (string)($row['username'] ?? 'Utente eliminato'),
        'role' => (string)($row['ruolo'] ?? 'utente'),
        'message' => (string)$row['message'],
        'attachment_url' => $row['attachment_url'] ?? null,
        'created_at' => $row['created_at'],
    ];
}, cripsum_ticket_messages($mysqli, (string)$ticket['ticket_id']));

bot_json([
    'ok' => true,
    'ticket' => [
        'ticket_id' => (string)$ticket['ticket_id'],
        'title' => (string)$ticket['title'],
        'topic' => (string)$ticket['topic'],
        'status' => (string)$ticket['status'],
        'source' => (string)($ticket['source'] ?? 'site'),
        'owner' => (string)($ticket['username'] ?? ''),
        'owner_discord_id' => $ticket['discord_id'] ?? null,
        'thread_id' => $ticket['discord_thread_id'] ?? null,
        'created_at' => $ticket['created_at'],
        'url' => '/it/supporto?ticket=' . rawurlencode((string)$ticket['ticket_id']),
    ],
    'messages' => $messages,
]);
