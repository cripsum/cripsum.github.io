<?php
declare(strict_types=1);

/**
 * Approva o rifiuta un contenuto in attesa, dai bottoni su Discord.
 *
 * Approvare fa esattamente quello che fa il pannello: alza `approvato` e fa
 * partire l'annuncio sul webhook. Rifiutare elimina il contenuto e avvisa
 * l'autore, con la stessa funzione condivisa usata dal pannello.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/content_moderation.php';
require_once __DIR__ . '/../../../includes/discord_notify.php';

bot_require_key();
bot_require_method('POST');

$actor = bot_require_actor($mysqli);

$body = bot_input();
$action = bot_text('action', 20, true);
$type = bot_text('content_type', 20, true);
$postId = (int)($body['post_id'] ?? 0);

if (!in_array($action, ['approve', 'reject'], true)) {
    bot_fail('Unknown action.', 400);
}

$types = cripsum_community_post_types();
if (!isset($types[$type])) {
    bot_fail('Unknown content type.', 400);
}

if ($postId <= 0) {
    bot_fail('Missing post_id.', 400);
}

$table = $types[$type]['table'];

if ($action === 'reject') {
    $deleted = cripsum_delete_community_post($mysqli, $type, $postId);

    if (!$deleted['ok']) {
        bot_fail($deleted['error'] ?? 'Unable to reject the content.', 500);
    }

    bot_log_action(
        $mysqli,
        $actor,
        $type === 'rimasto' ? 'delete_toprimasti' : 'delete_shitpost',
        $deleted['author_id'],
        ['post_id' => $postId, 'from' => 'approval_queue']
    );

    bot_json([
        'ok' => true,
        'action' => 'reject',
        'deleted' => $deleted['deleted'],
        'title' => $deleted['title'],
        'author_notified' => $deleted['deleted'] && $deleted['author_id'] !== null,
        'actor' => (string)$actor['username'],
    ]);
}

if (!auth_column_exists($mysqli, $table, 'approvato')) {
    bot_fail('This content type has no approval column.', 500);
}

$stmt = $mysqli->prepare("UPDATE `$table` SET approvato = 1 WHERE id = ? LIMIT 1");
if (!$stmt) {
    bot_fail('Unable to approve the content.', 500);
}

$stmt->bind_param('i', $postId);
if (!$stmt->execute()) {
    $stmt->close();
    bot_fail('Unable to approve the content.', 500);
}

$changed = $stmt->affected_rows;
$stmt->close();

// L'annuncio automatico sul canale dei post, come dal pannello admin.
try {
    notifyDiscordNewPost($mysqli, $postId, $type === 'rimasto' ? 'rimasto' : 'shitpost');
} catch (Throwable $e) {
    error_log('[Discord Webhook Error approve from bot] ' . $e->getMessage());
}

bot_log_action(
    $mysqli,
    $actor,
    $type === 'rimasto' ? 'approve_toprimasti' : 'approve_shitpost',
    null,
    ['post_id' => $postId, 'from' => 'approval_queue']
);

bot_json([
    'ok' => true,
    'action' => 'approve',
    'changed' => $changed > 0,
    'actor' => (string)$actor['username'],
]);
