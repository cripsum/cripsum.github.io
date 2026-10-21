<?php
/**
 * Duelli 2.0 — «è cambiato qualcosa nella partita?» senza database.
 *
 * L'arena e chi guarda lo chiedono di continuo (circa una volta al secondo
 * quando tocca all'altro): risponde leggendo solo il timbro della partita
 * (includes/realtime.php, chiave d:<id>). Quando la versione cambia, o il
 * tempo del turno e' scaduto, il browser chiede api/duel/state.php.
 *
 * GET m=<partita> → {ok, now, v, dl, st, c, r, s}
 *   v  versione della partita (-1 = timbro non ancora scritto: chiedere state.php)
 *   dl scadenza del turno (unix, 0 = nessuna)
 *   st stato (active, finished, cancelled)
 *   c, r ultimo messaggio di chat e ultima reazione
 *   s    quanti stanno guardando
 *
 * Nel timbro non c'e' niente di privato: chi puo' vedere la partita lo
 * decide state.php.
 */
require_once __DIR__ . '/../rt/_light.php';

rt_light_user();
cripsum_release_session();

$matchId = (int)($_GET['m'] ?? 0);
if ($matchId <= 0) {
    rt_json(['ok' => false, 'error' => 'match'], 400);
}
if (rt_dir() === '') {
    rt_json(['ok' => true, 'off' => true, 'now' => time()]);
}

$stamp = rt_read('d:' . $matchId);
rt_json([
    'ok' => true,
    'now' => time(),
    'v' => isset($stamp['v']) ? (int)$stamp['v'] : -1,
    'dl' => (int)($stamp['dl'] ?? 0),
    'st' => (string)($stamp['st'] ?? ''),
    'c' => (int)($stamp['c'] ?? 0),
    'r' => (int)($stamp['r'] ?? 0),
    's' => (int)($stamp['s'] ?? 0),
]);
