<?php
/**
 * Notifiche push: iscrizione dei dispositivi.
 *
 * GET                       chiave pubblica del sito, che il browser usa per iscriversi
 * POST action=subscribe     iscrive questo browser per l'utente collegato
 *      action=unsubscribe   lo toglie
 *      action=test          manda una notifica di prova ai dispositivi dell'utente
 *                           e dice cosa hanno risposto i servizi push
 *      action=drop          toglie un'iscrizione rimasta a un account che su
 *                           quel browser non è più collegato
 *
 * Non serve il database: le iscrizioni stanno nel timbro dell'utente
 * (includes/webpush.php).
 *
 * `drop` è l'unica azione senza sessione: la chiamano /sw.js e le pagine di
 * chi è uscito. A proteggerla è l'indirizzo push stesso, un URL lungo e
 * casuale che conosce solo il browser a cui appartiene.
 */
require_once __DIR__ . '/../rt/_light.php';
require_once __DIR__ . '/../../includes/webpush.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = $method === 'POST' ? rt_light_input() : [];
$action = (string)($input['action'] ?? '');

if ($method === 'POST' && $action === 'drop') {
    cripsum_release_session();
    $endpoint = (string)($input['endpoint'] ?? '');
    $owner = (int)($input['user'] ?? 0);
    if ($owner > 0 && wp_endpoint_allowed($endpoint)) {
        wp_unsubscribe($owner, [$endpoint]);
    }
    rt_json(['ok' => true]);
}

$light = rt_light_user();

if ($method !== 'POST') {
    cripsum_release_session();
    $keys = wp_keys();
    rt_json(['ok' => true, 'key' => $keys ? $keys['public'] : null]);
}

rt_light_csrf();
cripsum_release_session();

$endpoint = (string)($input['endpoint'] ?? '');

switch ($action) {
    case 'subscribe':
        if (!wp_keys()) {
            rt_json(['ok' => false, 'error' => 'unavailable'], 503);
        }
        $keys = isset($input['keys']) && is_array($input['keys']) ? $input['keys'] : [];
        if (!wp_subscribe($light['id'], $endpoint, (string)($keys['p256dh'] ?? ''), (string)($keys['auth'] ?? ''))) {
            rt_json(['ok' => false, 'error' => 'invalid'], 422);
        }
        // Lo stesso browser era iscritto per un altro account: da lì va tolto.
        $previous = (int)($input['replace_user'] ?? 0);
        if ($previous > 0 && $previous !== $light['id']) {
            wp_unsubscribe($previous, [$endpoint]);
        }
        rt_json(['ok' => true]);
        // no break: rt_json esce

    case 'unsubscribe':
        if ($endpoint !== '') {
            wp_unsubscribe($light['id'], [$endpoint]);
        }
        rt_json(['ok' => true]);
        // no break

    case 'test':
        $subs = wp_subs(rt_read('u:' . $light['id']));
        if (!$subs || !wp_keys()) {
            rt_json(['ok' => true, 'devices' => 0, 'results' => []]);
        }
        // Mandata subito e non a fine richiesta: qui serve sapere com'è andata.
        $results = wp_send([['user' => $light['id'], 'subs' => $subs, 'payload' => ['t' => 'test', 'u' => $light['id']], 'topic' => 'test']]);
        rt_json([
            'ok' => true,
            'devices' => count($subs),
            'results' => array_map(static fn($row) => [
                'service' => (string)parse_url($row['endpoint'], PHP_URL_HOST),
                'code' => $row['code'],
            ], $results),
        ]);
        // no break

    default:
        rt_json(['ok' => false, 'error' => 'action'], 400);
}
