<?php
/**
 * Elenco delle chat di chi chiede: conversazioni private, gruppi, inviti ai
 * gruppi e contatori. Una chiamata sola: i filtri (attive, non lette,
 * archiviate, richieste) li applica la pagina su quello che riceve.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $groups = cg_list($mysqli, $userId);

    send_success([
        'privates' => cc_pm_list($mysqli, $userId),
        'groups' => $groups['groups'],
        'invites' => $groups['invites'],
        'counters' => cc_unread_summary($mysqli, $userId),
        'features' => [
            'requests' => rt_has_col($mysqli, 'private_conversation_participants', 'is_request'),
            'clear' => rt_has_col($mysqli, 'private_conversation_participants', 'cleared_before_id'),
            'timed_mute' => rt_has_col($mysqli, 'private_conversation_participants', 'muted_until'),
            'favorites' => rt_has_table($mysqli, 'private_favorites'),
            'pins' => rt_has_table($mysqli, 'private_pinned_messages'),
            'conversation_pins' => rt_has_table($mysqli, 'private_conversation_pins'),
            'privacy' => rt_has_table($mysqli, 'private_user_settings'),
        ],
    ]);
});
