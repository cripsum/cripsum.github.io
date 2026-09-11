<?php
declare(strict_types=1);

/**
 * Ticket aperto dal pannello su Discord.
 *
 * Il ticket finisce sul sito solo se chi lo apre ha l'account collegato: e' la
 * regola scelta per non riempire il pannello di ticket senza proprietario. Se
 * non e' collegato l'endpoint risponde linked=false e il bot tiene il thread
 * solo su Discord.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/ticket_helpers.php';

bot_require_key();
bot_require_method('POST');

/** Quanti ticket aperti puo' avere contemporaneamente un utente. */
const TICKET_MAX_OPEN = 3;
/** Quanti ticket puo' creare in mezz'ora. */
const TICKET_MAX_PER_WINDOW = 5;
const TICKET_WINDOW_MINUTES = 30;

$discordId = bot_discord_id('discord_id');
$threadId = bot_snowflake('thread_id', false);
$title = bot_text('title', 200, true);
$topic = bot_text('topic', 100, false, 'Altro');
$message = bot_text('message', 5000, true);

$user = bot_find_user($mysqli, $discordId);
if (!$user) {
    bot_json(['ok' => true, 'linked' => false]);
}

if ((int)($user['isBannato'] ?? 0) === 1) {
    bot_json(['ok' => true, 'linked' => true, 'banned' => true]);
}

$userId = (int)$user['id'];

// Limiti per utente. Non si usa auth_rate_limited() perche' quella conta anche
// per indirizzo IP, e dal bot l'IP e' sempre lo stesso: si bloccherebbero tutti
// a vicenda.
$stmtOpen = $mysqli->prepare(
    "SELECT
        SUM(status = 'open') AS aperti,
        SUM(created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)) AS recenti
     FROM site_tickets WHERE user_id = ?"
);

if ($stmtOpen) {
    $window = TICKET_WINDOW_MINUTES;
    $stmtOpen->bind_param('ii', $window, $userId);
    $stmtOpen->execute();
    $counts = $stmtOpen->get_result()->fetch_assoc() ?: [];
    $stmtOpen->close();

    if ((int)($counts['aperti'] ?? 0) >= TICKET_MAX_OPEN) {
        bot_json([
            'ok' => true,
            'linked' => true,
            'rejected' => 'too_many_open',
            'limit' => TICKET_MAX_OPEN,
        ]);
    }

    if ((int)($counts['recenti'] ?? 0) >= TICKET_MAX_PER_WINDOW) {
        bot_json([
            'ok' => true,
            'linked' => true,
            'rejected' => 'rate_limited',
            'retry_after_minutes' => TICKET_WINDOW_MINUTES,
        ]);
    }
}

$created = cripsum_ticket_create(
    $mysqli,
    $userId,
    $title,
    $topic,
    $message,
    'discord',
    $threadId !== '' ? $threadId : null
);

if (!$created) {
    bot_fail('Unable to create the ticket.', 500);
}

bot_json([
    'ok' => true,
    'linked' => true,
    'ticket_id' => $created['ticket_id'],
    'username' => (string)$user['username'],
    'topic' => cripsum_ticket_normalize_topic($topic),
    'ticket_url' => '/it/supporto?ticket=' . rawurlencode($created['ticket_id']),
]);
