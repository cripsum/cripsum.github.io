<?php

/**
 * Cripsum™ — API Animespot: traccia
 *
 * Endpoint : GET /api/animespot/audio.php
 * Auth     : sessione PHP
 * Response : audio/ogg
 *
 * Serve solo i secondi che il giocatore ha già sbloccato, tagliati sul confine
 * di una pagina Ogg. Il nome del file resta dietro a questo endpoint: su
 * AnimeThemes si chiama come l'anime, quindi puntare direttamente a
 * a.animethemes.moe vorrebbe dire scrivere la risposta nell'URL.
 *
 * Due accorgimenti tengono il gioco reattivo:
 *
 * 1. la sessione si molla appena si sa che cosa servire. Scaricare da
 *    AnimeThemes può prendere un secondo, e finché la sessione è bloccata
 *    ogni altra richiesta del gioco resta in coda dietro a questa: era il
 *    motivo per cui saltare due volte di fila sembrava inchiodare la pagina;
 * 2. finita la risposta si continua a scaricare il resto della sigla. Chi
 *    sbaglia il primo tentativo trova il frammento più lungo già pronto.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/animespot_helpers.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('Accept-Ranges: none');

$lang = animespot_request_lang();

function animespot_audio_fail(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

if (!isLoggedIn()) {
    animespot_audio_fail(401, animespot_msg('unauthenticated', $lang));
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    animespot_audio_fail(405, animespot_msg('method', $lang));
}

checkBan($mysqli);

$current = animespot_bootstrap($mysqli);

if ($current === null || empty($current['track'])) {
    animespot_audio_fail(503, animespot_msg('no_catalog', $lang));
}

$track    = $current['track'];
$unlocked = animespot_unlocked_seconds($current['round']);
$from     = (float)($current['round']['avvio'] ?? 0);

// Lo scarto che chiede il client.
//
// Il silenzio digitale in testa lo riconosce il server dai byte, ma certe
// sigle hanno un'apertura che *sembra* musica — l'encoder ci spende
// settecento byte a pacchetto — e non si sente lo stesso: è aria, non suono.
// Da qui non c'è modo di accorgersene senza decodificare, e decodificare Opus
// in PHP non si può. Chi può è il browser, che il pezzo lo decodifica
// comunque: se scopre che non si sente niente lo richiede più avanti, e questo
// è il parametro con cui lo dice. Non svela niente — quanti secondi saltare non
// dice di che anime si tratta — ma va comunque tenuto corto.
$shift = (float)($_GET['shift'] ?? 0);
if (!is_finite($shift) || $shift < 0) $shift = 0.0;
$from += min(8.0, $shift);

// Da qui in poi non si tocca più la sessione: quello che serve è già in mano,
// e il resto è rete e disco. Chiuderla adesso libera la coda.
cripsum_release_session();

// Tutta la sigla in una discesa sola, non un pezzo per frammento.
//
// I frammenti sono cinque e crescono: chiederne uno alla volta vuol dire
// cinque viaggi in rete, e ognuno è un'attesa in mezzo alla partita. Scaricare
// subito quanto serve al più lungo costa qualche decimo di secondo qui e li
// rende tutti istantanei — compreso questo, perché il pezzo che gli serve è il
// primo che arriva.
animespot_cache_warm($track, $from);

$clip = animespot_clip($track, $unlocked, $from);
if ($clip === null) {
    animespot_audio_fail(404, animespot_msg('no_audio', $lang));
}

header('Content-Type: audio/ogg');
header('Content-Length: ' . strlen($clip['bytes']));
header('Content-Disposition: inline; filename="animespot.ogg"');

// Dice al client se il taglio è esatto: quando non lo è, la traccia arriva
// più lunga del dovuto e a fermarsi al punto giusto deve pensarci lui.
header('X-Animespot-Exact: ' . ($clip['exact'] ? '1' : '0'));

echo $clip['bytes'];
