<?php
/**
 * Battito della chat globale: «sono ancora qui».
 *
 * Tiene il proprio nome nell'elenco di chi è in chat. Di norma si limita a
 * prolungare la presenza nel timbro, senza database. Passa dal database solo
 * quando serve davvero: la presenza non c'è ancora (prima visita) oppure il
 * conteggio degli utenti online è vecchio e qualcuno deve rifarlo.
 *
 * GET leave=1 toglie subito il nome dall'elenco (alla chiusura della pagina).
 */
require_once __DIR__ . '/../rt/_light.php';
require_once __DIR__ . '/../../includes/chat_global_view.php';

$light = rt_light_user();

if (!empty($_GET['leave'])) {
    cripsum_release_session();
    gc_presence_leave($light['id']);
    rt_json(['ok' => true]);
}

$stamp = rt_read('g');
if (rt_dir() !== '' && !gc_online_stale($stamp) && isset($stamp['settings']) && gc_presence_touch($light['id'])) {
    cripsum_release_session();
    rt_json(['ok' => true]);
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_global.php';

chat_run(static function () use ($mysqli, $userId, $chatUser): void {
    gc_presence_touch($userId, gc_presence_entry($chatUser));
    gc_online_refresh($mysqli);
    gc_restore_pinned($mysqli);
    $hidden = sc_refresh_hidden($mysqli, $userId);

    $stamp = rt_read('g');
    $aux = gc_aux_view($stamp, $userId, $hidden);
    send_success([
        'online_count' => $aux['online_count'],
        'typing' => $aux['typing'],
        'state' => ['seq' => (int)($stamp['seq'] ?? 0), 'aux' => (int)($stamp['aux'] ?? 0)] + $aux,
        'server_time' => date(DATE_ATOM),
    ]);
});
