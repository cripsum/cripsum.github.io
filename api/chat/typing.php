<?php
/**
 * «Sta scrivendo».
 *
 *   senza altri parametri   → chat globale: finisce solo nel timbro, il
 *                             database non viene nemmeno aperto;
 *   con `conversation_id`   → chat privata;
 *   con `chat_id`           → gruppo.
 *
 * Per chat private e gruppi serve una query (chi chiama deve farne parte),
 * e l'avviso parte solo se chi scrive non ha disattivato «sta scrivendo».
 */
require_once __DIR__ . '/../rt/_light.php';

$light = rt_light_user();
$input = rt_light_input();
rt_light_csrf();

$typing = !empty($input['typing']);
$conversationId = (int)($input['conversation_id'] ?? 0);
$chatId = (int)($input['chat_id'] ?? 0);

if ($conversationId <= 0 && $chatId <= 0) {
    cripsum_release_session();
    if ($light['username'] !== '') {
        rt_global_typing($light['id'], $light['username'], $typing);
    }
    rt_json(['ok' => true]);
}

require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId, $typing, $conversationId, $chatId): void {
    if (!sc_settings($mysqli, $userId)['typing']) {
        send_success(['sent' => false]);
    }

    $event = ['t' => 'ty', 'f' => $userId, 'on' => $typing ? 1 : 0, 'until' => time() + CRIPSUM_RT_TYPING_TTL];

    if ($chatId > 0) {
        cg_require($mysqli, $chatId, $userId);
        $event['g'] = $chatId;
        $event['n'] = cg_username($mysqli, $userId);
        rt_push_users(array_diff(cg_member_ids($mysqli, $chatId), [$userId]), $event);
        send_success(['sent' => true]);
    }

    $pair = cc_pm_require($mysqli, $conversationId, $userId);
    // Finché la richiesta non è accettata, chi la riceve non vede nemmeno
    // che l'altro sta scrivendo; e a chi ti ha bloccato non arriva nulla.
    if (!$pair['other']['is_request'] && !$pair['me']['is_request']
        && !sc_is_blocked($mysqli, $userId, (int)$pair['other']['user_id'])) {
        $event['c'] = $conversationId;
        rt_push_user((int)$pair['other']['user_id'], $event);
    }
    send_success(['sent' => true]);
});
