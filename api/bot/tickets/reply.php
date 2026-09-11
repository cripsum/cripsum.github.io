<?php
declare(strict_types=1);

/**
 * Messaggio scritto nel thread Discord, riportato nella chat del ticket sul
 * sito.
 *
 * Chi scrive viene attribuito al suo account Cripsum se ha il Discord
 * collegato; altrimenti il messaggio lo firma l'utente di servizio (poppy)
 * riportando il nome Discord in testa, perche' site_ticket_messages ha bisogno
 * comunque di un sender_id valido.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/ticket_helpers.php';

bot_require_key();
bot_require_method('POST');

$body = bot_input();
$threadId = bot_snowflake('thread_id', false);
$ticketId = bot_text('ticket_id', 12);
$discordId = bot_discord_id('discord_id');
$authorName = bot_text('author_name', 80, false, 'Discord');
$message = bot_text('message', 5000);

$ticket = cripsum_ticket_find($mysqli, $ticketId, $threadId);
if (!$ticket) {
    bot_fail('Ticket not found.', 404);
}

$attachmentUrl = null;
$attachment = $body['attachment'] ?? null;
if (is_array($attachment) && !empty($attachment['base64'])) {
    $attachmentUrl = cripsum_ticket_store_attachment((string)$attachment['base64']);
    if ($attachmentUrl === null) {
        bot_json([
            'ok' => true,
            'stored' => false,
            'reason' => 'invalid_attachment',
            'ticket_id' => (string)$ticket['ticket_id'],
        ]);
    }
}

if ($message === '' && $attachmentUrl === null) {
    bot_fail('Missing message.', 400);
}

$author = bot_find_user($mysqli, $discordId);
$isOwner = $author && (int)$author['id'] === (int)$ticket['user_id'];

if ($author) {
    $senderId = (int)$author['id'];
    $senderName = (string)$author['username'];
    $storedMessage = $message;
} else {
    $service = bot_service_user($mysqli);
    if (!$service) {
        bot_fail(
            'The bot service account does not exist on the site. Create the user "poppy" (role admin) first.',
            503
        );
    }

    $senderId = (int)$service['id'];
    $senderName = (string)$service['username'];
    // Il nome Discord e' testo di un utente: si neutralizzano le menzioni.
    $safeAuthor = str_replace(['@', '`'], ['@' . "\u{200b}", "'"], $authorName);
    $storedMessage = '**' . $safeAuthor . '** (Discord)' . ($message !== '' ? "\n" . $message : '');
}

// Se ha scritto il proprietario del ticket, il non letto tocca allo staff.
$unreadFor = $isOwner ? 'admin' : 'user';

if (!cripsum_ticket_add_message($mysqli, (string)$ticket['ticket_id'], $senderId, $storedMessage, $attachmentUrl, $unreadFor)) {
    bot_fail('Unable to store the message.', 500);
}

bot_json([
    'ok' => true,
    'stored' => true,
    'ticket_id' => (string)$ticket['ticket_id'],
    'status' => (string)$ticket['status'],
    'sender' => $senderName,
    'sender_linked' => (bool)$author,
    'is_owner' => $isOwner,
    'attachment_url' => $attachmentUrl,
]);
