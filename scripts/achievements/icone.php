<?php
/**
 * Cripsum™ — genera l'icona di un achievement, nello stile delle altre.
 *
 * Ogni icona è una medaglia SVG: la sagoma e il metallo dicono il livello
 * (bronzo tondo, argento esagonale, oro a sigillo, platino a scudo, diamante
 * sfaccettato), il simbolo al centro dice di cosa si tratta, i puntini in
 * basso il gradino dentro una serie.
 *
 * Solo da riga di comando (la cartella scripts/ è chiusa al web):
 *
 *   php scripts/achievements/icone.php --simboli
 *   php scripts/achievements/icone.php <nome-file> <livello> <simbolo> [gradino]
 *
 *   php scripts/achievements/icone.php casse-5000 diamante box 6
 *     → img/achievements/casse-5000.svg
 *
 * Poi nel pannello, campo «Icona»: achievements/casse-5000.svg
 * Un simbolo nuovo si aggiunge a $G: tratto su griglia 24x24, niente
 * riempimenti (lo stile è quello delle icone «a linea»).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$G = [
    'flag'       => '<path d="M5.5 21V3.5"/><path d="M5.5 4.5c3-1.8 5.300 1.600 8.500 0 1.700-.8 3.200-.9 5-.3v8.600c-1.800-.6-3.300-.5-5 .3-3.200 1.600-5.500-1.800-8.500 0"/>',
    'film'       => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9v6l5-3z"/>',
    'sparkles'   => '<path d="M10 3.500l1.700 4.800 4.800 1.700-4.800 1.700L10 16.500l-1.700-4.800L3.500 10l4.800-1.700z"/><path d="M17.500 14l.9 2.100 2.100.9-2.100.9-.9 2.100-.9-2.100-2.100-.9 2.100-.9z"/>',
    'cursor'     => '<path d="M5 3.500l13.500 6-5.500 2-2 5.500z"/><path d="M13.500 13.500l5.500 5.500"/>',
    'heart'      => '<path d="M12 20.500s-7.500-4.600-7.500-10.200A4.300 4.300 0 0 1 12 7.600a4.300 4.300 0 0 1 7.500 2.700c0 5.600-7.500 10.200-7.500 10.200z"/>',
    'camera'     => '<path d="M4 8.500A1.500 1.500 0 0 1 5.500 7h2l1.400-2h6.200l1.400 2h2A1.500 1.500 0 0 1 20 8.500v9a1.500 1.500 0 0 1-1.500 1.500h-13A1.500 1.500 0 0 1 4 17.500z"/><circle cx="12" cy="13" r="3.300"/>',
    'moon'       => '<path d="M19.500 14.500A8 8 0 1 1 9.500 4.500a6.300 6.300 0 0 0 10 10z"/><path d="M17 3.500v3M15.500 5h3"/>',
    'mouse'      => '<rect x="7" y="3" width="10" height="18" rx="5"/><path d="M12 7v3.500"/>',
    'flame'      => '<path d="M12 3c.6 3.200 5.500 5.600 5.500 10.600a5.500 5.500 0 0 1-11 0c0-1.900.8-3.300 1.900-4.500.3 1.400 1 2.200 1.900 2.600C10 9 10.800 6 12 3z"/>',
    'rewind'     => '<path d="M11 6.500v11L3.500 12z"/><path d="M20.500 6.500v11L13 12z"/>',
    'gamepad'    => '<path d="M7.500 7h9a4.500 4.500 0 0 1 4.500 4.500v2.800a2.700 2.700 0 0 1-4.900 1.500L15 14.500H9l-1.100 1.300A2.700 2.700 0 0 1 3 14.300v-2.800A4.500 4.500 0 0 1 7.500 7z"/><path d="M8 9.500v3M6.500 11h3"/><path d="M15.500 10h.01M17.500 12h.01"/>',
    'box'        => '<path d="M12 3l8 4.200v9.600L12 21l-8-4.200V7.200z"/><path d="M4 7.200l8 4.300 8-4.300"/><path d="M12 11.500V21"/>',
    'frown'      => '<circle cx="12" cy="12" r="8.500"/><path d="M8.500 16c.9-1.300 2.100-2 3.500-2s2.600.7 3.500 2"/><path d="M9 9.500h.01M15 9.500h.01"/>',
    'layers'     => '<path d="M12 3.500l8.500 4.500-8.500 4.500L3.500 8z"/><path d="M3.500 12l8.500 4.500 8.500-4.500"/><path d="M3.500 16l8.500 4.500 8.500-4.500"/>',
    'arrow-up'   => '<circle cx="12" cy="12" r="8.500"/><path d="M12 16.500v-9"/><path d="M8 11.500l4-4 4 4"/>',
    'star'       => '<path d="M12 3.200l2.700 5.600 6.100.8-4.500 4.300 1.100 6.100L12 17.100 6.600 20l1.100-6.100-4.500-4.300 6.100-.8z"/>',
    'crown'      => '<path d="M4 17.500L3 8l5 4 4-6.500 4 6.500 5-4-1 9.500z"/><path d="M4.500 20.500h15"/>',
    'gem'        => '<path d="M7 4h10l4 5.500-9 11-9-11z"/><path d="M3 9.500h18"/><path d="M9.500 4L8 9.500l4 11 4-11L14.500 4"/>',
    'star4'      => '<path d="M12 2.500l2.200 7.300 7.300 2.200-7.300 2.200-2.200 7.300-2.200-7.300L2.500 12l7.300-2.200z"/><circle cx="12" cy="12" r="1.200"/>',
    'clover'     => '<path d="M12 4v16"/><path d="M5.500 7h13"/><path d="M5.500 7L3 13a2.500 2.500 0 0 0 5 0z"/><path d="M18.500 7L16 13a2.500 2.500 0 0 0 5 0z"/><path d="M8.500 20h7"/>',
    'storm'      => '<path d="M7 16.500a4.500 4.500 0 0 1-.6-8.960A5.500 5.500 0 0 1 17 8.500a4 4 0 0 1 .5 7.970"/><path d="M13 12l-3 4.500h3.500L11 21"/>',
    'hourglass'  => '<path d="M6.500 3.500h11M6.500 20.500h11"/><path d="M7.500 3.500c0 4.500 4.500 5.500 4.500 8.500s-4.500 4-4.500 8.500"/><path d="M16.500 3.500c0 4.500-4.500 5.500-4.500 8.500s4.500 4 4.500 8.500"/>',
    'dice'       => '<rect x="4" y="4" width="16" height="16" rx="3.500"/><path d="M8.500 8.500h.01M15.500 8.500h.01M12 12h.01M8.500 15.500h.01M15.500 15.500h.01"/>',
    'trend-down' => '<path d="M3.500 6.500l6 6 3.500-3.500 7.500 7.500"/><path d="M15 17h5.500v-5.500"/>',
    'swords'     => '<path d="M5 4l12 12"/><path d="M14.500 18.500l4-4"/><path d="M17 17l3 3"/><path d="M19 4L7 16"/><path d="M5.500 14.500l4 4"/><path d="M7 17l-3 3"/>',
    'shield'     => '<path d="M12 3l7.500 2.800v5.700c0 4.500-3.100 7.800-7.500 9.500-4.400-1.700-7.500-5-7.500-9.500V5.800z"/><path d="M8.800 12l2.300 2.300 4.300-4.600"/>',
    'train'      => '<rect x="5.500" y="3.500" width="13" height="13.500" rx="3"/><path d="M5.500 11h13"/><path d="M9 14h.01M15 14h.01"/><path d="M8.500 17l-2 3.500M15.500 17l2 3.500"/>',
    'search'     => '<circle cx="10.500" cy="10.500" r="6.500"/><path d="M15.500 15.500l5 5"/>',
    'music'      => '<path d="M9 17.500v-12l10-2v12"/><circle cx="6.500" cy="17.500" r="2.500"/><circle cx="16.500" cy="15.500" r="2.500"/>',
    'bolt'       => '<path d="M13.500 2.500l-9 11H11l-1 8 9-11h-6.500z"/>',
    'user-plus'  => '<circle cx="9.500" cy="8" r="3.800"/><path d="M2.500 20c.5-3.800 3.300-6 7-6s6.500 2.200 7 6"/><path d="M18.500 7v6M15.500 10h6"/>',
    'bubble'     => '<path d="M4 6.500A2.500 2.500 0 0 1 6.500 4h11A2.500 2.500 0 0 1 20 6.500v7a2.500 2.500 0 0 1-2.500 2.500H11l-4.500 4v-4A2.500 2.500 0 0 1 4 13.500z"/>',
    'send'       => '<path d="M21 3L10.500 13.500"/><path d="M21 3l-6.500 18-4-7.500-7.500-4z"/>',
    'idcard'     => '<rect x="3" y="5" width="18" height="14" rx="2.500"/><circle cx="8.500" cy="10.500" r="2"/><path d="M5.500 16c.4-1.600 1.500-2.500 3-2.500s2.600.9 3 2.500"/><path d="M14.500 10h4M14.500 13.500h4"/>',
    'eye'        => '<path d="M2.500 12S6 5.500 12 5.500 21.500 12 21.500 12 18 18.500 12 18.500 2.500 12 2.500 12z"/><circle cx="12" cy="12" r="3"/>',
    'image'      => '<rect x="3.500" y="4.500" width="17" height="15" rx="2.500"/><circle cx="9" cy="9.500" r="1.600"/><path d="M4 17l4.500-4.500 3.500 3.500 3-3 5 5"/>',
    'thumb'      => '<path d="M7.500 10.500V20H5a1.500 1.500 0 0 1-1.500-1.500V12A1.500 1.500 0 0 1 5 10.500z"/><path d="M7.500 10.500l3.700-7a2.300 2.300 0 0 1 2.300 2.300v3.700h4.600a2 2 0 0 1 2 2.300l-1 6.500a2 2 0 0 1-2 1.700H7.500"/>',
    'comment'    => '<path d="M4 6.500A2.500 2.500 0 0 1 6.500 4h11A2.500 2.500 0 0 1 20 6.500v7a2.500 2.500 0 0 1-2.500 2.500H11l-4.500 4v-4A2.500 2.500 0 0 1 4 13.500z"/><path d="M8.500 10h.01M12 10h.01M15.500 10h.01"/>',
    'ballot'     => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12.300l2.800 2.800L16 9.500"/>',
    'book'       => '<path d="M12 6.500C10.500 5 8 4.500 4 4.500v14c4 0 6.500.5 8 2 1.500-1.500 4-2 8-2v-14c-4 0-6.500.5-8 2z"/><path d="M12 6.500v14"/>',
    'clock'      => '<circle cx="12" cy="12" r="8.500"/><path d="M12 7v5l3.500 2"/>',
    'calendar'   => '<rect x="4" y="5" width="16" height="15" rx="2.500"/><path d="M4 10h16"/><path d="M8.500 3v4M15.500 3v4"/><path d="M8.500 14.500l2.300 2.300 4.200-4.300"/>',
    'target'     => '<circle cx="12" cy="12" r="8.500"/><circle cx="12" cy="12" r="4.700"/><path d="M12 12h.01"/>',
    'trophy'     => '<path d="M7.500 4h9v5.500a4.500 4.500 0 0 1-9 0z"/><path d="M7.500 5.500h-3V7a3 3 0 0 0 3 3M16.500 5.500h3V7a3 3 0 0 1-3 3"/><path d="M12 14v3.500"/><path d="M9 17.500h6v3H9z"/>',
    'user'       => '<circle cx="12" cy="8" r="4"/><path d="M4 20.500c.6-4.300 3.800-6.500 8-6.500s7.400 2.200 8 6.500"/>',
    'key'        => '<circle cx="8" cy="15.500" r="4.500"/><path d="M11.300 12.300L20 3.500"/><path d="M16 7.500l3 3M13.500 10l2 2"/>',
    'link'       => '<path d="M10 14a4.200 4.200 0 0 0 6 0l3.200-3.200a4.200 4.200 0 0 0-6-6L12 6"/><path d="M14 10a4.200 4.200 0 0 0-6 0l-3.200 3.200a4.200 4.200 0 0 0 6 6L12 18"/>',
    'seal'       => '<path d="M12 2.800l2.400 1.900 3-.2.9 2.900 2.500 1.700-1 2.900 1 2.900-2.500 1.700-.9 2.900-3-.2-2.400 1.900-2.400-1.900-3 .2-.9-2.900-2.500-1.700 1-2.900-1-2.900 2.500-1.700.9-2.900 3 .2z"/><path d="M8.700 12.200l2.300 2.300 4.300-4.600"/>',
    'grid'       => '<rect x="4" y="4" width="6.800" height="6.800" rx="1.800"/><rect x="13.200" y="4" width="6.800" height="6.800" rx="1.800"/><rect x="4" y="13.200" width="6.800" height="6.800" rx="1.800"/><rect x="13.200" y="13.200" width="6.800" height="6.800" rx="1.800"/>',
    'bag'        => '<path d="M5 8h14l-1 12.500H6z"/><path d="M8.500 8V7a3.500 3.500 0 0 1 7 0v1"/>',
    'medal'      => '<circle cx="12" cy="14.500" r="5.500"/><circle cx="12" cy="14.500" r="2"/><path d="M8.500 3.500l2.500 5.600M15.500 3.500L13 9.100"/>',
    'coins'      => '<ellipse cx="12" cy="6.500" rx="7" ry="3"/><path d="M5 6.500V12c0 1.700 3.100 3 7 3s7-1.300 7-3V6.500"/><path d="M5 12v5.500c0 1.700 3.100 3 7 3s7-1.300 7-3V12"/>',
    'question'   => '<path d="M8.800 9a3.200 3.200 0 1 1 4.900 2.700c-1 .7-1.700 1.400-1.700 2.800"/><path d="M12 18.500h.01"/>',
];

// ── Livelli: metallo (chiaro, medio, scuro), fondo, colore del simbolo ─────
$T = [
    'bronzo'   => ['m' => ['#ffd2a8', '#d98a52', '#8a4a22'], 'plate' => ['#3b2416', '#170d07'], 'glyph' => '#ffe6d3', 'glow' => '#ff9a57', 'shape' => 'circle'],
    'argento'  => ['m' => ['#ffffff', '#b8c2d3', '#6c7689'], 'plate' => ['#2a3142', '#0e121b'], 'glyph' => '#f3f6fb', 'glow' => '#c5d2ea', 'shape' => 'hex'],
    'oro'      => ['m' => ['#fff6c2', '#f4c343', '#a8740a'], 'plate' => ['#3d2f08', '#161003'], 'glyph' => '#fff3c4', 'glow' => '#ffd24a', 'shape' => 'seal'],
    'platino'  => ['m' => ['#f2feff', '#86d6ea', '#3c8aa6'], 'plate' => ['#10344a', '#06141e'], 'glyph' => '#e2fbff', 'glow' => '#6fe3ff', 'shape' => 'shield'],
    'diamante' => ['m' => ['#a8f0ff', '#9a86ff', '#ff7bd5'], 'plate' => ['#2a1f66', '#0d0a26'], 'glyph' => '#ffffff', 'glow' => '#b9a6ff', 'shape' => 'cut'],
    'segreto'  => ['m' => ['#d9b8ff', '#8a5cf6', '#4c2a9e'], 'plate' => ['#24124d', '#0c061c'], 'glyph' => '#e9dbff', 'glow' => '#a97bff', 'shape' => 'circle'],
];

$n = static fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

/** Sagoma del livello, centrata su (64,64). */
$shape = static function (string $kind) use ($n): string {
    switch ($kind) {
        case 'hex':
            $pts = [];
            for ($i = 0; $i < 6; $i++) {
                $a = deg2rad(60 * $i - 90);
                $pts[] = $n(64 + 53 * cos($a)) . ',' . $n(64 + 53 * sin($a));
            }
            return '<polygon points="' . implode(' ', $pts) . '" stroke-width="8" stroke-linejoin="round"/>';

        case 'seal':
            $d = '';
            $steps = 180;
            for ($i = 0; $i <= $steps; $i++) {
                $a = 2 * M_PI * $i / $steps;
                $r = 54.5 + 3.4 * cos(12 * $a);
                $d .= ($i ? 'L' : 'M') . $n(64 + $r * sin($a)) . ' ' . $n(64 - $r * cos($a));
            }
            return '<path d="' . $d . 'Z" stroke-width="2" stroke-linejoin="round"/>';

        case 'shield':
            return '<path d="M64 8l46 14v40c0 30-20 50-46 60-26-10-46-30-46-60V22z" stroke-width="7" stroke-linejoin="round"/>';

        case 'cut':
            $pts = [];
            for ($i = 0; $i < 8; $i++) {
                $a = deg2rad(45 * $i - 90);
                $pts[] = $n(64 + 56 * cos($a)) . ',' . $n(64 + 56 * sin($a));
            }
            return '<polygon points="' . implode(' ', $pts) . '" stroke-width="6" stroke-linejoin="round"/>';

        default:
            return '<circle cx="64" cy="64" r="57"/>';
    }
};

$build = static function (string $tier, string $glyph, int $pips) use ($G, $T, $shape, $n): string {
    $t = $T[$tier];
    $s = $shape($t['shape']);
    $glyphSvg = $G[$glyph] ?? $G['star'];

    // Il simbolo occupa 60 px; con i puntini sale di poco per fargli posto.
    $scale = 2.5;
    $gy = $pips > 0 ? 29 : 34;
    $gx = 34;
    // Lo scudo ha il baricentro più in alto.
    if ($t['shape'] === 'shield') {
        $gy -= 5;
    }

    $svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="128" height="128">';
    $svg .= '<defs>';
    $svg .= '<linearGradient id="m" x1="0.15" y1="0" x2="0.85" y2="1"><stop offset="0" stop-color="' . $t['m'][0] . '"/><stop offset="0.5" stop-color="' . $t['m'][1] . '"/><stop offset="1" stop-color="' . $t['m'][2] . '"/></linearGradient>';
    $svg .= '<radialGradient id="p" cx="0.5" cy="0.38" r="0.75"><stop offset="0" stop-color="' . $t['plate'][0] . '"/><stop offset="1" stop-color="' . $t['plate'][1] . '"/></radialGradient>';
    $svg .= '<radialGradient id="g" cx="0.5" cy="0.45" r="0.5"><stop offset="0" stop-color="' . $t['glow'] . '" stop-opacity="0.42"/><stop offset="1" stop-color="' . $t['glow'] . '" stop-opacity="0"/></radialGradient>';
    $svg .= '<linearGradient id="s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity="0.5"/><stop offset="0.55" stop-color="#fff" stop-opacity="0"/></linearGradient>';
    $svg .= '</defs>';

    // Bordo di metallo, poi il fondo scuro rimpicciolito al centro.
    $svg .= '<g fill="url(#m)" stroke="url(#m)">' . $s . '</g>';
    $svg .= '<g fill="url(#s)" stroke="none" opacity="0.55">' . preg_replace('/ stroke-width="[^"]*"| stroke-linejoin="[^"]*"/', '', $s) . '</g>';
    $svg .= '<g transform="translate(64 64) scale(0.8) translate(-64 -64)">';
    $svg .= '<g fill="url(#p)" stroke="url(#p)">' . $s . '</g>';
    $svg .= '<g fill="url(#g)" stroke="none">' . preg_replace('/ stroke-width="[^"]*"| stroke-linejoin="[^"]*"/', '', $s) . '</g>';
    $svg .= '</g>';
    $svg .= '<g transform="translate(64 64) scale(0.8) translate(-64 -64)" fill="none" stroke="' . $t['m'][0] . '" stroke-opacity="0.45" stroke-width="1.6">' . preg_replace('/ stroke-width="[^"]*"/', '', $s) . '</g>';

    // Il diamante ha le sfaccettature.
    if ($t['shape'] === 'cut') {
        $svg .= '<g stroke="#fff" stroke-opacity="0.22" stroke-width="1.2" fill="none"><path d="M64 8v14M64 106v14M8 64h14M106 64h14M24.4 24.4l10 10M93.600 93.600l10 10M103.600 24.400l-10 10M34.400 93.600l-10 10"/></g>';
    }

    $svg .= '<g transform="translate(' . $gx . ' ' . $gy . ') scale(' . $scale . ')" fill="none" stroke="' . $t['glyph'] . '" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $glyphSvg . '</g>';

    if ($pips > 0) {
        $gap = 9;
        $x0 = 64 - ($pips - 1) * $gap / 2;
        $py = $t['shape'] === 'shield' ? 94 : 99;
        $svg .= '<g fill="' . $t['m'][0] . '">';
        for ($i = 0; $i < $pips; $i++) {
            $svg .= '<circle cx="' . $n($x0 + $i * $gap) . '" cy="' . $py . '" r="2.6"/>';
        }
        $svg .= '</g>';
    }

    return $svg . '</svg>' . "\n";
};

// ── Riga di comando ────────────────────────────────────────────────────────

$args = array_slice($argv, 1);

if (($args[0] ?? '') === '--simboli') {
    echo implode(' ', array_keys($G)), "\n";
    exit(0);
}

[$name, $tier, $glyph] = $args + [null, null, null];
$pips = max(0, min(6, (int)($args[3] ?? 0)));

if (!$name || !preg_match('/^[a-z0-9\-]{1,60}$/', $name) || !isset($T[$tier]) || !isset($G[$glyph])) {
    fwrite(STDERR, "Uso: php scripts/achievements/icone.php <nome-file> <livello> <simbolo> [gradino]\n"
        . "  nome-file  minuscole, cifre e trattini (diventa img/achievements/<nome-file>.svg)\n"
        . "  livello    " . implode(' | ', array_keys($T)) . "\n"
        . "  simbolo    php scripts/achievements/icone.php --simboli\n"
        . "  gradino    0-6: i puntini sotto il simbolo, per le serie\n");
    exit(1);
}

$target = dirname(__DIR__, 2) . '/img/achievements/' . $name . '.svg';
file_put_contents($target, $build($tier, $glyph, $pips));
echo "Scritto ", $target, "\n";
