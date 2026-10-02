<?php
/**
 * «Ho visto le menzioni»: lo dice la pagina della chat globale quando una
 * menzione arriva mentre è aperta e in primo piano.
 *
 * Non serve il database: le menzioni da vedere stanno nel timbro personale
 * (includes/realtime.php). Aprire la pagina le azzera già da sé.
 */
require_once __DIR__ . '/../rt/_light.php';

$light = rt_light_user();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    rt_json(['ok' => false], 405);
}
rt_light_csrf();
cripsum_release_session();

rt_json(['ok' => true, 'cleared' => rt_mentions_clear($light['id'])]);
