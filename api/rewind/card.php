<?php

/**
 * Cripsum™ — Immagine di anteprima del Rewind
 *
 * Genera la card 1200x630 che Discord, WhatsApp e i social mostrano quando
 * qualcuno incolla il link di un Rewind condiviso.
 *
 * Endpoint : GET /api/rewind/card.php?token=<22 caratteri>
 * Auth     : nessuna — il token è l'autorizzazione, come per la pagina
 *            pubblica. Vale solo per i Rewind con is_public = 1.
 *
 * NOTA SULL'AMBIENTE
 * Questo file è l'unico del Rewind che non ho potuto eseguire in locale: il
 * PHP di sviluppo qui non ha GD. Per questo ogni capacità viene verificata
 * prima dell'uso e, se qualcosa manca, si ripiega su un disegno più semplice
 * o su un redirect all'immagine statica del sito, invece di restituire mezzo
 * PNG o un errore 500 dentro l'anteprima di un social.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/rewind_helpers.php';

/** Ripiego: l'immagine statica del sito. */
function rewind_card_fallback(): void
{
    header('Location: /img/og-default.jpg', true, 302);
    exit;
}

if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
    rewind_card_fallback();
}

$token = (string)($_GET['token'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]{22}$/', $token)) {
    rewind_card_fallback();
}

// Lettura diretta: rewind_load_shared() incrementerebbe il contatore delle
// visite, e un'anteprima generata da un bot non è una visita.
$row = rewind_row(
    $mysqli,
    'SELECT payload FROM user_rewind WHERE share_token = ? AND is_public = 1 LIMIT 1',
    's',
    [$token]
);

cripsum_release_session();

if ($row === null) {
    rewind_card_fallback();
}

$data = json_decode((string)$row['payload'], true);
if (!is_array($data)) {
    rewind_card_fallback();
}

// ─────────────────────────────────────────────────────────────
//  DATI DA MOSTRARE
// ─────────────────────────────────────────────────────────────

$lang = (($_GET['lang'] ?? '') === 'en') ? 'en' : 'it';
$isEn = $lang === 'en';

$name = (string)($data['user']['display_name'] ?? ($data['user']['username'] ?? 'Cripsum'));
$persona = (string)($data['persona'][$isEn ? 'name_en' : 'name_it'] ?? '');
$accent = (string)($data['persona']['color'] ?? '#2f6bff');
$accent2 = (string)($data['persona']['color_2'] ?? '#0b2a6b');

$stats = [
    [number_format((int)($data['time']['hours'] ?? 0), 0, ',', '.'), $isEn ? 'HOURS' : 'ORE'],
    [number_format((int)($data['time']['days_active'] ?? 0), 0, ',', '.'), $isEn ? 'DAYS' : 'GIORNI'],
    [number_format((int)($data['gacha']['pulls'] ?? 0), 0, ',', '.'), 'PULL'],
    [number_format((int)($data['social']['msg_total'] ?? 0), 0, ',', '.'), $isEn ? 'MESSAGES' : 'MESSAGGI'],
];

// ─────────────────────────────────────────────────────────────
//  DISEGNO
// ─────────────────────────────────────────────────────────────

const RW_CARD_W = 1200;
const RW_CARD_H = 630;

/** Converte "#rrggbb" in una tripletta, con ripiego sul blu del sito. */
function rewind_card_rgb(string $hex): array
{
    $hex = ltrim(trim($hex), '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return [47, 107, 255];
    }
    return [
        (int)hexdec(substr($hex, 0, 2)),
        (int)hexdec(substr($hex, 2, 2)),
        (int)hexdec(substr($hex, 4, 2)),
    ];
}

$img = @imagecreatetruecolor(RW_CARD_W, RW_CARD_H);
if ($img === false) {
    rewind_card_fallback();
}

[$r1, $g1, $b1] = rewind_card_rgb($accent2);
[$r2, $g2, $b2] = rewind_card_rgb($accent);

// Sfondo a sfumatura verticale, una riga alla volta: GD non ha i gradienti.
for ($y = 0; $y < RW_CARD_H; $y++) {
    $t = $y / (RW_CARD_H - 1);
    // Curva morbida, così la metà bassa resta scura e il testo si legge.
    $t = $t * $t;
    $color = imagecolorallocate(
        $img,
        (int)round($r1 + ($r2 - $r1) * (1 - $t) * 0.85),
        (int)round($g1 + ($g2 - $g1) * (1 - $t) * 0.85),
        (int)round($b1 + ($b2 - $b1) * (1 - $t) * 0.85)
    );
    imageline($img, 0, $y, RW_CARD_W, $y, $color);
}

$white = imagecolorallocate($img, 255, 255, 255);
$dim = imagecolorallocate($img, 210, 216, 232);
$accentColor = imagecolorallocate($img, $r2, $g2, $b2);

// Barra d'accento in alto.
imagefilledrectangle($img, 0, 0, RW_CARD_W, 8, $accentColor);

$fontPath = __DIR__ . '/../../assets/fonts/go3v2.ttf';
$hasTtf = function_exists('imagettftext')
    && is_readable($fontPath)
    && @imagettfbbox(20, 0, $fontPath, 'A') !== false;

/**
 * Scrive del testo, con o senza font vettoriale.
 * Con $centerX il testo viene centrato su quella colonna.
 */
$text = static function (string $value, int $size, int $x, int $y, int $color, ?int $centerX = null)
use ($img, $fontPath, $hasTtf): void {
    $value = trim($value);
    if ($value === '') {
        return;
    }

    if ($hasTtf) {
        if ($centerX !== null) {
            $box = @imagettfbbox($size, 0, $fontPath, $value);
            if ($box !== false) {
                $x = $centerX - (int)(($box[2] - $box[0]) / 2);
            }
        }
        @imagettftext($img, $size, 0, $x, $y, $color, $fontPath, $value);
        return;
    }

    // Senza FreeType resta il font bitmap interno: brutto ma leggibile, e
    // soprattutto sempre disponibile.
    $builtin = 5;
    $width = imagefontwidth($builtin) * strlen($value);
    if ($centerX !== null) {
        $x = $centerX - (int)($width / 2);
    }
    imagestring($img, $builtin, $x, $y - imagefontheight($builtin), $value, $color);
};

// Intestazione
$text('CRIPSUM REWIND', 22, 70, 100, $dim);

// Nome e archetipo
$text($name, 46, 70, 190, $white);
$text(mb_strtoupper($persona, 'UTF-8'), 68, 70, 285, $white);

// Riga delle statistiche
$columnWidth = (int)((RW_CARD_W - 140) / count($stats));
foreach (array_values($stats) as $index => [$value, $label]) {
    $centerX = 70 + $columnWidth * $index + (int)($columnWidth / 2);
    $text((string)$value, 54, 0, 470, $white, $centerX);
    $text((string)$label, 20, 0, 512, $dim, $centerX);
}

// Piè di pagina
$text('cripsum.com', 22, 70, 570, $dim);

header('Content-Type: image/png');
// Le anteprime vengono richieste da molti servizi: una cache lunga evita di
// rigenerare la stessa immagine a ogni condivisione.
header('Cache-Control: public, max-age=86400');

imagepng($img, null, 6);
imagedestroy($img);
