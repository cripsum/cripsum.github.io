<?php
/**
 * «C'è qualcosa di nuovo?» — senza database.
 *
 * È la richiesta che ogni pagina aperta ripete di continuo. Legge due
 * timbri (quello dell'utente e, se chi chiama sta guardando la chat globale,
 * quello globale) e risponde con gli eventi successivi al numero che il
 * browser ricorda. Quasi sempre la risposta è «niente».
 *
 * GET u=<numero>            eventi personali dopo quel numero (-1 = solo il numero attuale)
 *     g=<numero>            eventi della chat globale (facoltativo)
 *     ga=<numero>           contatore di «chi scrive / chi c'è / impostazioni»
 *
 * Gli eventi personali portano solo id: il testo dei messaggi privati lo
 * chiede il browser agli endpoint della chat, che controllano i permessi.
 */
require_once __DIR__ . '/_light.php';
require_once __DIR__ . '/../../includes/chat_global_view.php';

$user = rt_light_user();
cripsum_release_session();

if (rt_dir() === '') {
    // Cartella dei timbri non scrivibile: il browser ripiega sul controllo lento.
    rt_json(['ok' => true, 'off' => true]);
}

$out = ['ok' => true, 't' => time()];

$userStamp = rt_read('u:' . $user['id']);
$userCursor = isset($_GET['u']) ? (int)$_GET['u'] : -1;
$userEvents = rt_events_since($userStamp, $userCursor);
if ($userEvents !== null) {
    $out['u'] = $userEvents;
}

if (isset($_GET['g'])) {
    $hidden = isset($userStamp['hidden']) && is_array($userStamp['hidden']) ? array_map('intval', $userStamp['hidden']) : [];
    $hiddenMap = array_flip($hidden);
    $global = rt_read('g');

    $globalEvents = rt_events_since($global, (int)$_GET['g']);
    if ($globalEvents !== null) {
        $views = [];
        foreach ($globalEvents['events'] as $event) {
            $message = $event['m'] ?? null;
            if (!is_array($message) || isset($hiddenMap[(int)($message['user_id'] ?? 0)])) {
                continue;
            }
            if (!empty($message['reply']) && isset($hiddenMap[(int)($message['reply']['user_id'] ?? 0)])) {
                $message['reply']['message'] = '';
                $message['reply']['message_type'] = 'deleted';
            }
            $views[] = [
                's' => (int)$event['s'],
                't' => (string)$event['t'],
                'm' => gc_view($message, $user['id'], $user['ruolo'], $user['username']),
            ];
        }
        $globalEvents['events'] = $views;
        $out['g'] = $globalEvents;
    }

    $aux = (int)($global['aux'] ?? 0);
    if (!isset($_GET['ga']) || (int)$_GET['ga'] !== $aux) {
        $out['ga'] = ['aux' => $aux] + gc_aux_view($global, $user['id'], $hidden);
    }
    // Il conteggio degli online è vecchio: il primo che lo nota lo rifà.
    if (gc_online_stale($global) || !isset($global['present'][(string)$user['id']])) {
        $out['stale'] = true;
    }
}

rt_json($out);
