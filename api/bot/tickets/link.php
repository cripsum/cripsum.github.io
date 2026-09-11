<?php
declare(strict_types=1);

/**
 * Collega un thread Discord a un ticket nato sul sito.
 *
 * Lo chiama il bot subito dopo aver creato il thread, cosi' le risposte
 * successive sanno dove andare.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/ticket_helpers.php';

bot_require_key();
bot_require_method('POST');

$ticketId = bot_text('ticket_id', 12, true);
$threadId = bot_snowflake('thread_id');

$ticket = cripsum_ticket_find($mysqli, $ticketId);
if (!$ticket) {
    bot_fail('Ticket not found.', 404);
}

if (!cripsum_ticket_schema($mysqli)['thread']) {
    // Senza la colonna il ponte non puo' funzionare, ma non e' un errore del
    // chiamante: la migration non e' ancora stata applicata.
    bot_json([
        'ok' => true,
        'linked' => false,
        'reason' => 'schema_missing',
        'ticket_id' => $ticketId,
    ]);
}

$existing = (string)($ticket['discord_thread_id'] ?? '');
if ($existing !== '' && $existing !== $threadId) {
    bot_json([
        'ok' => true,
        'linked' => false,
        'reason' => 'already_linked',
        'thread_id' => $existing,
        'ticket_id' => $ticketId,
    ]);
}

if (!cripsum_ticket_attach_thread($mysqli, $ticketId, $threadId)) {
    bot_fail('Unable to link the thread.', 500);
}

bot_json([
    'ok' => true,
    'linked' => true,
    'ticket_id' => $ticketId,
    'thread_id' => $threadId,
]);
