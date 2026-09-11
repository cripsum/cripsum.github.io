<?php
declare(strict_types=1);

/**
 * Azioni sui bottoni delle segnalazioni pubblicate su Discord.
 *
 * Sono azioni sul sito, quindi serve un account Cripsum admin/owner collegato
 * al Discord di chi preme il bottone; la logica di eliminazione e di cambio
 * stato e' la stessa del pannello admin (includes/content_moderation.php), non
 * una copia.
 *
 * Si agisce sul **contenuto segnalato**, non sulla singola riga: se lo stesso
 * post e' stato segnalato da piu' persone, l'azione le chiude tutte.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/content_moderation.php';

bot_require_key();
bot_require_method('POST');

const REPORT_ACTIONS = ['reviewed', 'dismissed', 'delete'];

$actor = bot_require_actor($mysqli);

$action = bot_text('action', 20, true);
$source = bot_text('source', 20, true);
$contentType = bot_text('content_type', 20);
$body = bot_input();
$targetId = (int)($body['target_id'] ?? $_GET['target_id'] ?? 0);

if (!in_array($action, REPORT_ACTIONS, true)) {
    bot_fail('Unknown report action.', 400);
}

if (!array_key_exists($source, cripsum_report_tables())) {
    bot_fail('Unknown report source.', 400);
}

if ($targetId <= 0) {
    bot_fail('Missing target_id.', 400);
}

$response = [
    'ok' => true,
    'action' => $action,
    'actor' => (string)$actor['username'],
    'target_id' => $targetId,
];

if ($action === 'delete') {
    // Da qui si eliminano solo i contenuti della community. Profili e
    // messaggi di chat restano al pannello admin, dove c'e' il contesto.
    if ($source !== 'content' || !array_key_exists($contentType, cripsum_community_post_types())) {
        bot_fail('Only community posts can be deleted from Discord.', 400);
    }

    $deleted = cripsum_delete_community_post($mysqli, $contentType, $targetId);

    if (!$deleted['ok']) {
        bot_fail($deleted['error'] ?? 'Unable to delete the content.', 500);
    }

    bot_log_action(
        $mysqli,
        $actor,
        $contentType === 'rimasto' ? 'delete_toprimasti' : 'delete_shitpost',
        $deleted['author_id'],
        ['post_id' => $targetId, 'from' => 'report_button']
    );

    $closed = cripsum_set_reports_status_for_target(
        $mysqli,
        $source,
        $targetId,
        'reviewed',
        (int)$actor['id'],
        $contentType
    );

    $response['deleted'] = $deleted['deleted'];
    $response['title'] = $deleted['title'];
    $response['author_notified'] = $deleted['deleted'] && $deleted['author_id'] !== null;
    $response['reports_closed'] = $closed['affected'];
    $response['report_status'] = 'reviewed';

    bot_json($response);
}

$status = $action === 'dismissed' ? 'dismissed' : 'reviewed';
$updated = cripsum_set_reports_status_for_target(
    $mysqli,
    $source,
    $targetId,
    $status,
    (int)$actor['id'],
    $contentType
);

if (!$updated['ok']) {
    bot_fail($updated['error'] ?? 'Unable to update the reports.', 500);
}

bot_log_action($mysqli, $actor, 'update_report_status', null, [
    'source' => $source,
    'target_id' => $targetId,
    'status' => $status,
    'affected' => $updated['affected'],
    'from' => 'report_button',
]);

$response['report_status'] = $status;
$response['reports_closed'] = $updated['affected'];

bot_json($response);
