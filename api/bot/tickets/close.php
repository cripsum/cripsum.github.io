<?php
declare(strict_types=1);

/**
 * Apre o chiude un ticket da Discord.
 *
 * Puo' farlo lo staff (account admin/owner collegato) o il proprietario del
 * ticket. Il permesso si ricava dal database, mai da quello che manda il bot.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/ticket_helpers.php';

bot_require_key();
bot_require_method('POST');

$threadId = bot_snowflake('thread_id', false);
$ticketId = bot_text('ticket_id', 12);
$actorDiscordId = bot_discord_id('actor_discord_id');
$requested = bot_text('status', 10, false, 'closed');

$ticket = cripsum_ticket_find($mysqli, $ticketId, $threadId);
if (!$ticket) {
    bot_fail('Ticket not found.', 404);
}

$actor = bot_find_user($mysqli, $actorDiscordId);
if (!$actor) {
    bot_fail('The acting Discord account is not linked to a Cripsum account.', 403);
}

$isStaff = in_array((string)($actor['ruolo'] ?? ''), ['admin', 'owner'], true);
$isOwner = (int)$actor['id'] === (int)$ticket['user_id'];

if (!$isStaff && !$isOwner) {
    bot_fail('Not allowed to change this ticket.', 403);
}

// Riaprire un ticket e' una prerogativa dello staff: altrimenti un utente
// potrebbe riaprire all'infinito quello che gli e' stato chiuso.
$status = $requested === 'open' ? 'open' : 'closed';
if ($status === 'open' && !$isStaff) {
    bot_fail('Only the staff can reopen a ticket.', 403);
}

if ((string)$ticket['status'] === $status) {
    bot_json([
        'ok' => true,
        'changed' => false,
        'ticket_id' => (string)$ticket['ticket_id'],
        'status' => $status,
    ]);
}

if (!cripsum_ticket_set_status($mysqli, (string)$ticket['ticket_id'], $status)) {
    bot_fail('Unable to update the ticket.', 500);
}

// Nota nella conversazione, cosi' resta traccia anche sul sito.
$note = $status === 'closed'
    ? '🔒 Il ticket è stato chiuso da Discord.'
    : '🔓 Il ticket è stato riaperto da Discord.';
cripsum_ticket_add_message(
    $mysqli,
    (string)$ticket['ticket_id'],
    (int)$actor['id'],
    $note,
    null,
    $isOwner ? 'admin' : 'user'
);

if ($isStaff) {
    bot_log_action($mysqli, $actor, 'ticket_' . $status, (int)$ticket['user_id'], [
        'ticket_id' => (string)$ticket['ticket_id'],
    ]);
}

bot_json([
    'ok' => true,
    'changed' => true,
    'ticket_id' => (string)$ticket['ticket_id'],
    'status' => $status,
    'actor' => (string)$actor['username'],
]);
