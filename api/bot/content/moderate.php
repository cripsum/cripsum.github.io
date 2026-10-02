<?php
declare(strict_types=1);

/**
 * Approva o rifiuta un contenuto in attesa, dai bottoni su Discord.
 *
 * Approvare e rifiutare passano dalle stesse funzioni del pannello e della
 * pagina (includes/content_moderation.php): approvare mette online, annuncia
 * una volta sola e avvisa l'autore; rifiutare elimina il contenuto e avvisa
 * l'autore. `reason` e' facoltativo: una chiave di cripsum_rejection_reasons()
 * o un testo libero, che finisce nel messaggio all'autore.
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
    $deleted = cripsum_delete_community_post($mysqli, $type, $postId, [
        'reason' => bot_text('reason', 300),
        'reviewer_id' => (int)$actor['id'],
    ]);

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

$approved = cripsum_set_community_post_approval($mysqli, $type, $postId, true, ['actor_id' => (int)$actor['id']]);

if (!$approved['ok']) {
    bot_fail($approved['error'] ?? 'Unable to approve the content.', 500);
}

$changed = $approved['changed'] ? 1 : 0;

bot_log_action(
    $mysqli,
    $actor,
    $type === 'rimasto' ? 'approve_toprimasti' : 'approve_shitpost',
    $approved['author_id'],
    ['post_id' => $postId, 'from' => 'approval_queue']
);

bot_json([
    'ok' => true,
    'action' => 'approve',
    'changed' => $changed > 0,
    'actor' => (string)$actor['username'],
]);
