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

$clip = animespot_clip(
    $current['track'],
    animespot_unlocked_seconds($current['round']),
    (float)($current['round']['avvio'] ?? 0)
);
if ($clip === null) {
    animespot_audio_fail(404, animespot_msg('no_audio', $lang));
}

// La sessione non serve più: liberarla evita di bloccare le richieste che il
// gioco fa in parallelo mentre il browser scarica la traccia.
cripsum_release_session();

header('Content-Type: audio/ogg');
header('Content-Length: ' . strlen($clip['bytes']));
header('Content-Disposition: inline; filename="animespot.ogg"');

// Dice al client se il taglio è esatto: quando non lo è, la traccia arriva
// più lunga del dovuto e a fermarsi al punto giusto deve pensarci lui.
header('X-Animespot-Exact: ' . ($clip['exact'] ? '1' : '0'));

echo $clip['bytes'];
