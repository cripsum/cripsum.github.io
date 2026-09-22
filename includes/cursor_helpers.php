<?php
/**
 * cursor_helpers.php
 *
 * Preparazione dei cursori caricati per il profilo (api/upload_profile_media.php).
 *
 * Il file salvato e' l'immagine "madre": margini trasparenti tolti, proporzioni
 * intatte, lato lungo al massimo CURSOR_MASTER_MAX. La misura con cui si vede
 * la sceglie chi modifica il profilo e la applica assets/js/profile-cursor.js,
 * quindi cambiarla non richiede di ricaricare il file.
 *
 * - immagini statiche (JPG, PNG, WEBP, GIF a un fotogramma) -> PNG;
 * - GIF e WebP animati -> restano come sono (GD non sa ridimensionarli);
 * - .cur -> PNG, con la punta scritta nel file;
 * - .ani -> GIF animata (o PNG se ha un solo fotogramma).
 *
 * Solo GD, niente ImageMagick ne' exec().
 */

/** Oltre i 128 px i browser non mostrano piu' un cursore. */
const CURSOR_MASTER_MAX = 128;

/** GIF e WebP animati restano come sono: oltre questo lato si rifiutano. */
const CURSOR_ANIMATED_MAX = 1024;

function cursor_gd_available(): bool
{
    return function_exists('imagecreatetruecolor') && function_exists('imagepng');
}

/** Un'immagine statica in GD, in truecolor e con la trasparenza. */
function cursor_load_image(string $path, string $mimeType): ?\GdImage
{
    $src = match ($mimeType) {
        'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        'image/gif' => @imagecreatefromgif($path),
        default => false,
    };
    if (!$src) {
        return null;
    }
    if (!imageistruecolor($src)) {
        imagepalettetotruecolor($src);
    }
    imagealphablending($src, false);
    imagesavealpha($src, true);
    return $src;
}

/** Una parte di $src ridisegnata in un'immagine nuova $w x $h, trasparenza compresa. */
function cursor_resample(\GdImage $src, int $sx, int $sy, int $sw, int $sh, int $w, int $h): \GdImage
{
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $w, $h, $sw, $sh);
    return $dst;
}

/**
 * Il riquadro dei pixel che si vedono, [x, y, larghezza, altezza], oppure null
 * se l'immagine e' tutta trasparente. Si parte dai bordi: su un'immagine piena
 * basta un pixel per riga.
 */
function cursor_visible_box(\GdImage $img): ?array
{
    $w = imagesx($img);
    $h = imagesy($img);
    // Alfa di GD: 0 opaco, 127 trasparente. Sotto 120 il pixel si vede.
    $visible = static fn(int $x, int $y): bool => ((imagecolorat($img, $x, $y) >> 24) & 0x7F) < 120;
    $rowHasPixels = static function (int $y) use ($w, $visible): bool {
        for ($x = 0; $x < $w; $x++) {
            if ($visible($x, $y)) {
                return true;
            }
        }
        return false;
    };
    $columnHasPixels = static function (int $x, int $top, int $bottom) use ($visible): bool {
        for ($y = $top; $y <= $bottom; $y++) {
            if ($visible($x, $y)) {
                return true;
            }
        }
        return false;
    };

    $top = 0;
    while ($top < $h && !$rowHasPixels($top)) {
        $top++;
    }
    if ($top === $h) {
        return null;
    }
    $bottom = $h - 1;
    while ($bottom > $top && !$rowHasPixels($bottom)) {
        $bottom--;
    }
    $left = 0;
    while ($left < $w - 1 && !$columnHasPixels($left, $top, $bottom)) {
        $left++;
    }
    $right = $w - 1;
    while ($right > $left && !$columnHasPixels($right, $top, $bottom)) {
        $right--;
    }
    return [$left, $top, $right - $left + 1, $bottom - $top + 1];
}

/**
 * Salva un cursore statico: via i margini trasparenti (spostano la punta e
 * rimpiccioliscono il disegno), lato lungo al massimo CURSOR_MASTER_MAX,
 * proporzioni intatte, PNG.
 *
 * `box` e' il riquadro tenuto, in pixel dell'immagine di partenza: serve a
 * spostare la punta dei .cur.
 *
 * @return array{ok:bool, width?:int, height?:int, box?:array, error?:string}
 */
function cursor_save_static(\GdImage $src, string $outputPath): array
{
    // Una foto enorme si riduce prima: cercare i bordi su milioni di pixel
    // richiederebbe secondi, e il cursore finale e' comunque piccolo.
    $w = imagesx($src);
    $h = imagesy($src);
    $work = $src;
    $pre = 1.0;
    if (max($w, $h) > 512) {
        $pre = 512 / max($w, $h);
        $work = cursor_resample($src, 0, 0, $w, $h, max(1, (int)round($w * $pre)), max(1, (int)round($h * $pre)));
    }

    $box = cursor_visible_box($work);
    if ($box === null) {
        if ($work !== $src) {
            imagedestroy($work);
        }
        return ['ok' => false, 'error' => 'L\'immagine è tutta trasparente: non c\'è niente da mostrare.'];
    }

    [$bx, $by, $bw, $bh] = $box;
    $scale = min(1.0, CURSOR_MASTER_MAX / max($bw, $bh));
    $outW = max(1, (int)round($bw * $scale));
    $outH = max(1, (int)round($bh * $scale));
    $out = cursor_resample($work, $bx, $by, $bw, $bh, $outW, $outH);
    if ($work !== $src) {
        imagedestroy($work);
    }

    $ok = imagepng($out, $outputPath);
    imagedestroy($out);
    if (!$ok) {
        return ['ok' => false, 'error' => 'Impossibile salvare il cursore.'];
    }

    return [
        'ok' => true,
        'width' => $outW,
        'height' => $outH,
        'box' => [$bx / $pre, $by / $pre, $bw / $pre, $bh / $pre],
    ];
}

/** Un'immagine statica caricata come cursore. */
function cursor_prepare_image(string $tmpPath, string $mimeType, string $outputPath): array
{
    $src = cursor_load_image($tmpPath, $mimeType);
    if (!$src) {
        return ['ok' => false, 'error' => 'Non riesco a leggere l\'immagine. Prova con un PNG.'];
    }
    $result = cursor_save_static($src, $outputPath);
    imagedestroy($src);
    return $result;
}

/** GIF o WebP con piu' di un fotogramma. */
function cursor_is_animated(string $path, string $mimeType): bool
{
    $data = @file_get_contents($path);
    if ($data === false) {
        return false;
    }
    if ($mimeType === 'image/gif') {
        // Ogni fotogramma ha il suo "graphic control extension" seguito
        // dall'immagine (o da un'altra estensione).
        return preg_match_all('#\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $data) > 1;
    }
    if ($mimeType === 'image/webp') {
        // WebP esteso (VP8X) con il bit dell'animazione acceso.
        return substr($data, 12, 4) === 'VP8X' && strlen($data) > 20 && (ord($data[20]) & 0x02) === 0x02;
    }
    return false;
}

/** La punta "x,y" in percentuale, con un decimale. */
function cursor_hotspot_string(float $x, float $y): string
{
    $format = static fn(float $n): string => rtrim(rtrim(number_format(max(0.0, min(100.0, $n)), 1, '.', ''), '0'), '.');
    return $format($x) . ',' . $format($y);
}

/**
 * Un file .cur (o .ico) diventa un PNG. La punta scritta nel file si porta
 * dietro, spostata sul riquadro tenuto.
 *
 * @return array{ok:bool, width?:int, height?:int, hotspot?:?string, error?:string}
 */
function cursor_prepare_cur(string $tmpPath, string $outputPath): array
{
    $data = @file_get_contents($tmpPath);
    if ($data === false || strlen($data) < 6) {
        return ['ok' => false, 'error' => 'File .cur non valido.'];
    }
    $header = unpack('vreserved/vtype/vcount', $data);
    if ($header['reserved'] !== 0 || !in_array($header['type'], [1, 2], true) || $header['count'] < 1) {
        return ['ok' => false, 'error' => 'File .cur non valido.'];
    }

    $hotspot = null;
    $img = _cursor_ico_to_gd($data, 256, $hotspot);
    if (!$img) {
        return ['ok' => false, 'error' => 'Non riesco a leggere il file .cur.'];
    }
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $result = cursor_save_static($img, $outputPath);
    imagedestroy($img);
    if (!$result['ok']) {
        return $result;
    }

    $result['hotspot'] = null;
    if ($hotspot !== null) {
        [$bx, $by, $bw, $bh] = $result['box'];
        $result['hotspot'] = cursor_hotspot_string(($hotspot[0] - $bx) / $bw * 100, ($hotspot[1] - $by) / $bh * 100);
    }
    return $result;
}


/**
 * Convert a Windows .ani (animated cursor) file to an animated GIF.
 *
 * The .ani format is RIFF-based:
 *   RIFF <size> ACON
 *     anih <size> <36-byte animation header>
 *     rate <size> <array of DWORD jiffies per step>
 *     seq  <size> <array of DWORD frame indices per step>
 *     LIST <size> fram
 *       icon <size> <complete ICO/CUR data>
 *       icon <size> <complete ICO/CUR data>
 *       ...
 *
 * Con un solo fotogramma utile diventa un PNG statico, come le immagini.
 * La punta del primo fotogramma torna in `hotspot` ("x,y" in percentuale).
 *
 * @param string $aniPath    Source .ani file path
 * @param string $outputPath Destination file path (extension may change)
 * @return array ['ok' => true, 'animated' => bool, 'ext' => 'gif'|'png', 'width', 'height', 'hotspot'] or ['ok' => false, 'error' => '...']
 */
function cursor_convert_ani_to_gif(string $aniPath, string $outputPath): array
{
    $data = @file_get_contents($aniPath);
    if ($data === false || strlen($data) < 12) {
        return ['ok' => false, 'error' => 'Impossibile leggere il file .ani.'];
    }

    // --- Parse RIFF container ---
    $riffSig = substr($data, 0, 4);
    $aconSig = substr($data, 8, 4);
    if ($riffSig !== 'RIFF' || $aconSig !== 'ACON') {
        return ['ok' => false, 'error' => 'Il file non è un file .ani valido (intestazione RIFF/ACON mancante).'];
    }

    $len = strlen($data);

    // Parse state
    $anihData = null;
    $rateData = null;
    $seqData  = null;
    $iconChunks = []; // raw ICO/CUR data for each frame

    // Walk the RIFF chunks starting after RIFF header (12 bytes)
    _cursor_parse_riff_chunks($data, 12, $len, $anihData, $rateData, $seqData, $iconChunks);

    if (empty($iconChunks)) {
        return ['ok' => false, 'error' => 'Nessun frame trovato nel file .ani.'];
    }

    // --- Parse anih header (36 bytes) ---
    $nFrames  = count($iconChunks);
    $nSteps   = $nFrames;
    $jifRate  = 10; // default: 10 jiffies ≈ 167ms
    $cx       = 0;  // larghezza dichiarata dei fotogrammi (0 = non detta)

    if ($anihData !== null && strlen($anihData) >= 36) {
        $anih = unpack(
            'VcbSizeOf/VnFrames/VnSteps/Vcx/Vcy/VcBitCount/VcPlanes/VjifRate/Vfl',
            $anihData
        );
        if ($anih) {
            $nFrames = $anih['nFrames'] ?: $nFrames;
            $nSteps  = $anih['nSteps'] ?: $nSteps;
            $jifRate = $anih['jifRate'] ?: $jifRate;
            $cx      = (int)$anih['cx'];
        }
    }

    // --- Build per-step delays (in hundredths of a second for GIF) ---
    // 1 jiffy = 1/60 sec.  GIF delay is in 1/100 sec.
    // delay_cs = jiffies * (100/60) ≈ jiffies * 1.6667
    $delays = [];
    if ($rateData !== null) {
        $rateCount = intdiv(strlen($rateData), 4);
        for ($i = 0; $i < $rateCount; $i++) {
            $j = unpack('V', $rateData, $i * 4)[1];
            $delays[] = max(2, (int)round($j * 100 / 60));
        }
    }
    // Fill remaining steps with default jifRate
    while (count($delays) < $nSteps) {
        $delays[] = max(2, (int)round($jifRate * 100 / 60));
    }

    // --- Build step-to-frame sequence ---
    $sequence = [];
    if ($seqData !== null) {
        $seqCount = intdiv(strlen($seqData), 4);
        for ($i = 0; $i < $seqCount; $i++) {
            $sequence[] = unpack('V', $seqData, $i * 4)[1];
        }
    }
    if (empty($sequence)) {
        $sequence = range(0, $nFrames - 1);
    }
    // Clamp to actual icon count
    $sequence = array_map(fn($idx) => min($idx, count($iconChunks) - 1), $sequence);

    // --- Extract GD images from each unique frame ---
    $frameImages = []; // index => GdImage
    // La misura vera dei fotogrammi (fino ai 128 px dei cursori): prima si
    // leggeva una variabile mai definita e l'avviso rompeva la risposta JSON.
    $frameSize   = ($cx > 0 && $cx <= 256) ? min(CURSOR_MASTER_MAX, max(16, $cx)) : 0;
    $hotspot     = null;

    foreach ($iconChunks as $idx => $icoData) {
        $frameHotspot = null;
        $img = _cursor_ico_to_gd($icoData, $frameSize ?: 256, $frameHotspot);
        if ($img) {
            if (!$frameImages && $frameHotspot !== null) {
                $hotspot = cursor_hotspot_string($frameHotspot[0] / imagesx($img) * 100, $frameHotspot[1] / imagesy($img) * 100);
            }
            $frameImages[$idx] = $img;
        }
    }

    if (empty($frameImages)) {
        return ['ok' => false, 'error' => 'Impossibile decodificare i frame del file .ani.'];
    }
    if (!$frameSize) {
        $first = reset($frameImages);
        $frameSize = min(CURSOR_MASTER_MAX, max(16, imagesx($first), imagesy($first)));
    }

    // --- Build the animation frames list in step order ---
    $animFrames  = [];
    $animDelays  = [];
    $validSteps  = min(count($sequence), count($delays));

    for ($s = 0; $s < $validSteps; $s++) {
        $fIdx = $sequence[$s];
        if (isset($frameImages[$fIdx])) {
            $animFrames[] = $frameImages[$fIdx];
            $animDelays[] = $delays[$s];
        }
    }

    // Un solo fotogramma utile: e' un cursore statico come gli altri.
    if (count($animFrames) <= 1) {
        $singleFrame = $animFrames[0] ?? reset($frameImages);
        $pngPath = preg_replace('/\.[^.]+$/', '.png', $outputPath);
        imagealphablending($singleFrame, false);
        imagesavealpha($singleFrame, true);
        $srcW = imagesx($singleFrame) ?: 1;
        $srcH = imagesy($singleFrame) ?: 1;
        $saved = cursor_save_static($singleFrame, $pngPath);
        _cursor_destroy_frames($frameImages);
        if (!$saved['ok']) {
            return $saved;
        }
        if ($hotspot !== null) {
            // La punta segue il riquadro tenuto, come nei .cur.
            [$px, $py] = array_map('floatval', explode(',', $hotspot));
            [$bx, $by, $bw, $bh] = $saved['box'];
            $hotspot = cursor_hotspot_string(($px / 100 * $srcW - $bx) / $bw * 100, ($py / 100 * $srcH - $by) / $bh * 100);
        }
        return ['ok' => true, 'animated' => false, 'ext' => 'png', 'width' => $saved['width'], 'height' => $saved['height'], 'hotspot' => $hotspot];
    }

    // --- Encode animated GIF ---
    // Ensure all frames are exactly $frameSize × $frameSize
    foreach ($animFrames as &$frm) {
        $frm = _cursor_ensure_size($frm, $frameSize);
    }
    unset($frm);

    $gifPath = preg_replace('/\.[^.]+$/', '.gif', $outputPath);
    $gifData = _cursor_encode_animated_gif($animFrames, $animDelays, $frameSize);
    _cursor_destroy_frames($frameImages);

    if ($gifData === false) {
        return ['ok' => false, 'error' => 'Errore nella codifica GIF animata.'];
    }

    $ok = file_put_contents($gifPath, $gifData) !== false;
    return $ok
        ? ['ok' => true, 'animated' => true, 'ext' => 'gif', 'width' => $frameSize, 'height' => $frameSize, 'hotspot' => $hotspot]
        : ['ok' => false, 'error' => 'Impossibile scrivere il file GIF.'];
}


// ============================================================================
//  Internal helper functions (not part of the public API)
// ============================================================================

/**
 * Recursively walk RIFF chunks and extract anih, rate, seq, and icon data.
 */
function _cursor_parse_riff_chunks(
    string $data, int $offset, int $end,
    ?string &$anihData, ?string &$rateData, ?string &$seqData, array &$iconChunks
): void {
    while ($offset + 8 <= $end) {
        $chunkId   = substr($data, $offset, 4);
        $chunkSize = unpack('V', $data, $offset + 4)[1];
        $offset   += 8;

        // Sanity: chunk must fit within bounds
        if ($chunkSize < 0 || $offset + $chunkSize > $end) {
            break;
        }

        if ($chunkId === 'LIST') {
            // LIST chunk has a 4-byte type identifier, then sub-chunks
            if ($chunkSize >= 4) {
                $listType = substr($data, $offset, 4);
                if ($listType === 'fram') {
                    // Parse icon sub-chunks inside the "fram" list
                    _cursor_parse_riff_chunks(
                        $data, $offset + 4, $offset + $chunkSize,
                        $anihData, $rateData, $seqData, $iconChunks
                    );
                } else {
                    // Other LIST types – recurse in case of nested structures
                    _cursor_parse_riff_chunks(
                        $data, $offset + 4, $offset + $chunkSize,
                        $anihData, $rateData, $seqData, $iconChunks
                    );
                }
            }
        } elseif ($chunkId === 'anih') {
            $anihData = substr($data, $offset, $chunkSize);
        } elseif ($chunkId === 'rate') {
            $rateData = substr($data, $offset, $chunkSize);
        } elseif ($chunkId === "seq ") {
            $seqData = substr($data, $offset, $chunkSize);
        } elseif ($chunkId === 'icon') {
            $iconChunks[] = substr($data, $offset, $chunkSize);
        }

        // Advance past chunk data (chunks are WORD-aligned)
        $offset += $chunkSize;
        if ($chunkSize % 2 !== 0) {
            $offset++;
        }
    }
}

/**
 * Decode an ICO/CUR blob into a GD image.
 * Each ICO/CUR has a 6-byte header + 16 bytes per directory entry + image data.
 * Image data is either embedded PNG or BMP (BITMAPINFOHEADER).
 *
 * @param string $icoData  Raw ICO/CUR bytes
 * @param int    $size     Desired output size (will pick closest entry)
 * @return \GdImage|null
 */
function _cursor_ico_to_gd(string $icoData, int $size, ?array &$hotspot = null): ?\GdImage
{
    $hotspot = null;
    if (strlen($icoData) < 6) {
        return null;
    }

    $hdr = unpack('vreserved/vtype/vcount', $icoData);
    $count = $hdr['count'];
    if ($count < 1 || strlen($icoData) < 6 + $count * 16) {
        return null;
    }

    // Find the best entry (prefer one closest to $size; if tie, prefer higher bit-depth)
    $bestIdx    = 0;
    $bestDiff   = PHP_INT_MAX;
    $bestBits   = 0;

    for ($i = 0; $i < $count; $i++) {
        $off = 6 + $i * 16;
        $w = ord($icoData[$off]);      // 0 means 256
        $h = ord($icoData[$off + 1]);  // 0 means 256
        if ($w === 0) $w = 256;
        if ($h === 0) $h = 256;

        // Bits: for CUR, bytes 4-5 are hotspot; for ICO, byte 6-7 encode color planes / bpp
        // We'll just use image size for picking
        $imgSize   = unpack('V', $icoData, $off + 8)[1];
        $imgOffset = unpack('V', $icoData, $off + 12)[1];

        $diff = abs($w - $size) + abs($h - $size);
        if ($diff < $bestDiff) {
            $bestDiff = $diff;
            $bestIdx  = $i;
        }
    }

    // Read the best entry
    $entryOff  = 6 + $bestIdx * 16;
    $imgSize   = unpack('V', $icoData, $entryOff + 8)[1];
    $imgOffset = unpack('V', $icoData, $entryOff + 12)[1];

    $entryW = ord($icoData[$entryOff]);
    $entryH = ord($icoData[$entryOff + 1]);
    if ($entryW === 0) $entryW = 256;
    if ($entryH === 0) $entryH = 256;

    // Nei .cur (tipo 2) i due campi "piani" e "bit" sono la punta del cursore.
    if ($hdr['type'] === 2) {
        $hotspot = [unpack('v', $icoData, $entryOff + 4)[1], unpack('v', $icoData, $entryOff + 6)[1]];
    }

    if ($imgOffset + $imgSize > strlen($icoData) || $imgSize < 8) {
        return null;
    }

    $imgData = substr($icoData, $imgOffset, $imgSize);

    // Check if the image data is PNG (starts with PNG signature: 0x89504E47)
    $pngSig = "\x89PNG";
    if (substr($imgData, 0, 4) === $pngSig) {
        $img = @imagecreatefromstring($imgData);
        return $img ?: null;
    }

    // Otherwise it's a BMP (BITMAPINFOHEADER)
    return _cursor_bmp_to_gd($imgData, $entryW, $entryH);
}

/**
 * Decode a raw BMP (BITMAPINFOHEADER) from an ICO/CUR entry into a GD image.
 * In ICO/CUR, the biHeight is 2× actual height because the AND mask is appended.
 *
 * @param string $bmpData  Raw BMP data (starting with BITMAPINFOHEADER)
 * @param int    $w        Width from the ICO directory entry
 * @param int    $h        Height from the ICO directory entry
 * @return \GdImage|null
 */
function _cursor_bmp_to_gd(string $bmpData, int $w, int $h): ?\GdImage
{
    if (strlen($bmpData) < 40) {
        return null;
    }

    $bi = unpack(
        'VbiSize/lbiWidth/lbiHeight/vbiPlanes/vbiBitCount/VbiCompression/' .
        'VbiSizeImage/lbiXPelsPerMeter/lbiYPelsPerMeter/VbiClrUsed/VbiClrImportant',
        $bmpData
    );

    $bpp = $bi['biBitCount'];
    $biWidth  = $bi['biWidth'];
    // biHeight in ICO is 2× actual (XOR pixels + AND mask)
    $biHeight = abs($bi['biHeight']) / 2;

    // Use the ICO entry dimensions if they seem valid, otherwise use BITMAPINFOHEADER
    $imgW = ($w > 0 && $w <= 256) ? $w : $biWidth;
    $imgH = ($h > 0 && $h <= 256) ? $h : (int)$biHeight;

    // Color table offset (right after the 40-byte header)
    $colorTableOffset = 40;
    $colorTable = [];

    if ($bpp <= 8) {
        $numColors = $bi['biClrUsed'] ?: (1 << $bpp);
        for ($c = 0; $c < $numColors; $c++) {
            $off = $colorTableOffset + $c * 4;
            if ($off + 4 > strlen($bmpData)) break;
            $b = ord($bmpData[$off]);
            $g = ord($bmpData[$off + 1]);
            $r = ord($bmpData[$off + 2]);
            // $a = ord($bmpData[$off + 3]); // reserved
            $colorTable[] = [$r, $g, $b];
        }
        $pixelDataOffset = $colorTableOffset + $numColors * 4;
    } else {
        $pixelDataOffset = $colorTableOffset;
    }

    // Create destination image with transparency
    $img = imagecreatetruecolor($imgW, $imgH);
    if (!$img) return null;
    imagesavealpha($img, true);
    imagealphablending($img, false);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    // Row stride: each row is padded to a 4-byte boundary
    $rowStride = (int)ceil(($imgW * $bpp) / 8);
    $rowStride = ($rowStride + 3) & ~3; // align to 4 bytes

    // XOR (color) data size
    $xorSize = $rowStride * $imgH;

    // AND mask: 1 bpp, rows also padded to 4 bytes
    $andRowStride = (int)ceil($imgW / 8);
    $andRowStride = ($andRowStride + 3) & ~3;
    $andOffset = $pixelDataOffset + $xorSize;

    $hasAndMask = ($andOffset + $andRowStride * $imgH) <= strlen($bmpData);

    // Scan if the 32-bit alpha channel is actually used (i.e. has any non-zero value)
    $hasRealAlpha = false;
    if ($bpp === 32) {
        for ($y = 0; $y < $imgH; $y++) {
            $srcY = $imgH - 1 - $y;
            $rowOffset = $pixelDataOffset + $srcY * $rowStride;
            for ($x = 0; $x < $imgW; $x++) {
                $pOff = $rowOffset + $x * 4;
                if ($pOff + 3 < strlen($bmpData)) {
                    if (ord($bmpData[$pOff + 3]) > 0) {
                        $hasRealAlpha = true;
                        break 2;
                    }
                }
            }
        }
    }

    // BMP rows are stored bottom-up
    for ($y = 0; $y < $imgH; $y++) {
        $srcY = $imgH - 1 - $y; // flip vertically
        $rowOffset = $pixelDataOffset + $srcY * $rowStride;

        for ($x = 0; $x < $imgW; $x++) {
            $alpha = 0; // opaque

            // Check AND mask for transparency
            if ($hasAndMask && ($bpp < 32 || !$hasRealAlpha)) {
                $andByteOff = $andOffset + $srcY * $andRowStride + intdiv($x, 8);
                if ($andByteOff < strlen($bmpData)) {
                    $andBit = (ord($bmpData[$andByteOff]) >> (7 - ($x % 8))) & 1;
                    if ($andBit) {
                        $alpha = 127; // fully transparent
                    }
                }
            }

            // Read pixel color from XOR data
            $r = $g = $b = 0;

            if ($bpp === 32) {
                $pOff = $rowOffset + $x * 4;
                if ($pOff + 4 <= strlen($bmpData)) {
                    $b = ord($bmpData[$pOff]);
                    $g = ord($bmpData[$pOff + 1]);
                    $r = ord($bmpData[$pOff + 2]);
                    if ($hasRealAlpha) {
                        $a = ord($bmpData[$pOff + 3]); // alpha channel (0=transparent, 255=opaque)
                        $alpha = (int)(127 - ($a * 127 / 255));
                    }
                }
            } elseif ($bpp === 24) {
                $pOff = $rowOffset + $x * 3;
                if ($pOff + 3 <= strlen($bmpData)) {
                    $b = ord($bmpData[$pOff]);
                    $g = ord($bmpData[$pOff + 1]);
                    $r = ord($bmpData[$pOff + 2]);
                }
            } elseif ($bpp === 8) {
                $pOff = $rowOffset + $x;
                if ($pOff < strlen($bmpData)) {
                    $idx = ord($bmpData[$pOff]);
                    if (isset($colorTable[$idx])) {
                        [$r, $g, $b] = $colorTable[$idx];
                    }
                }
            } elseif ($bpp === 4) {
                $pOff = $rowOffset + intdiv($x, 2);
                if ($pOff < strlen($bmpData)) {
                    $byte = ord($bmpData[$pOff]);
                    $idx  = ($x % 2 === 0) ? ($byte >> 4) : ($byte & 0x0F);
                    if (isset($colorTable[$idx])) {
                        [$r, $g, $b] = $colorTable[$idx];
                    }
                }
            } elseif ($bpp === 1) {
                $pOff = $rowOffset + intdiv($x, 8);
                if ($pOff < strlen($bmpData)) {
                    $byte = ord($bmpData[$pOff]);
                    $idx  = ($byte >> (7 - ($x % 8))) & 1;
                    if (isset($colorTable[$idx])) {
                        [$r, $g, $b] = $colorTable[$idx];
                    }
                }
            }

            $color = imagecolorallocatealpha($img, $r, $g, $b, $alpha);
            imagesetpixel($img, $x, $y, $color);
        }
    }

    return $img;
}

/**
 * Ensure a GD image is exactly $size × $size.
 * If it already matches, return as-is. Otherwise resample.
 */
function _cursor_ensure_size(\GdImage $img, int $size): \GdImage
{
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w === $size && $h === $size) {
        return $img;
    }

    $dst = imagecreatetruecolor($size, $size);
    imagesavealpha($dst, true);
    imagealphablending($dst, false);
    $trans = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $trans);
    imagealphablending($dst, true);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $size, $size, $w, $h);
    imagesavealpha($dst, true);

    return $dst;
}

/**
 * Destroy an array of GD image resources.
 */
function _cursor_destroy_frames(array $frames): void
{
    foreach ($frames as $f) {
        if ($f instanceof \GdImage) {
            imagedestroy($f);
        }
    }
}


// ============================================================================
//  Minimal animated GIF encoder (pure PHP, no external dependencies)
// ============================================================================

/**
 * Encode an array of GD images into an animated GIF binary string.
 * Each frame is quantized to 256 colors via GD's built-in palette conversion.
 *
 * @param \GdImage[] $frames  Array of GD truecolor images (all same dimensions)
 * @param int[]      $delays  Per-frame delay in centiseconds (1/100 sec)
 * @param int        $size    Width and height of each frame
 * @return string|false       Raw GIF binary data or false on failure
 */
function _cursor_encode_animated_gif(array $frames, array $delays, int $size): string|false
{
    $numFrames = count($frames);
    if ($numFrames < 1) return false;

    // -- GIF89a Header --
    $gif = "GIF89a";

    // Logical Screen Descriptor
    $gif .= pack('v', $size);   // width
    $gif .= pack('v', $size);   // height
    // Packed field: global color table flag=1, color resolution=7 (8 bits), sort=0, size=7 (256 colors)
    $gif .= "\xF7";             // 1 111 0 111
    $gif .= "\x00";             // background color index
    $gif .= "\x00";             // pixel aspect ratio

    // Global color table: 256 entries × 3 bytes (initialize with black, will be overwritten by first frame)
    // We'll use per-frame local color tables instead, so fill with zeros.
    $gif .= str_repeat("\x00", 256 * 3);

    // Netscape Application Extension (for looping)
    $gif .= "\x21\xFF\x0B";           // Application Extension introducer
    $gif .= "NETSCAPE2.0";            // Application identifier
    $gif .= "\x03\x01";               // Sub-block: data length=3, block type=1
    $gif .= pack('v', 0);             // Loop count: 0 = infinite
    $gif .= "\x00";                   // Block terminator

    // -- Encode each frame --
    for ($i = 0; $i < $numFrames; $i++) {
        $frame = $frames[$i];
        $delay = $delays[$i] ?? 10;

        // Create a palette image directly (8-bit)
        $palImg = imagecreate($size, $size);

        // The first color allocated to a palette image is index 0 (the transparent color)
        $transR = 255;
        $transG = 0;
        $transB = 255;
        $transIdx = imagecolorallocate($palImg, $transR, $transG, $transB); // index 0
        imagecolortransparent($palImg, $transIdx);
        $hasTransparency = true;

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $rgba = imagecolorat($frame, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;

                if ($alpha >= 100) {
                    // Transparent pixel: use the transparent index (0)
                    imagesetpixel($palImg, $x, $y, $transIdx);
                } else {
                    $r = ($rgba >> 16) & 0xFF;
                    $g = ($rgba >> 8) & 0xFF;
                    $b = $rgba & 0xFF;

                    // If color matches magenta exactly, change slightly to prevent transparency leak
                    if ($r === $transR && $g === $transG && $b === $transB) {
                        $r = 254;
                    }

                    $colorIdx = imagecolorallocate($palImg, $r, $g, $b);
                    if ($colorIdx === -1 || $colorIdx === false) {
                        // Palette full, find closest color
                        $colorIdx = imagecolorclosest($palImg, $r, $g, $b);
                    }
                    imagesetpixel($palImg, $x, $y, $colorIdx);
                }
            }
        }

        // Graphic Control Extension
        $gif .= "\x21\xF9\x04";
        // Packed: disposal method=1 (do not dispose), user input=0, transparent flag
        $disposalMethod = 1; // do not dispose (keep previous frame)
        $packed = ($disposalMethod << 2);
        if ($hasTransparency) {
            $packed |= 0x01; // transparent color flag
        }
        $gif .= chr($packed);
        $gif .= pack('v', $delay);       // delay time
        $gif .= chr($hasTransparency ? $transIdx : 0); // transparent color index
        $gif .= "\x00";                  // block terminator

        // Image Descriptor
        $gif .= "\x2C";                  // Image separator
        $gif .= pack('v', 0);            // left
        $gif .= pack('v', 0);            // top
        $gif .= pack('v', $size);        // width
        $gif .= pack('v', $size);        // height

        // Build local color table from the palette image
        $numColors = imagecolorstotal($palImg);
        // Local color table size must be a power of 2; find the right exponent
        $lctExp = 0;
        $lctSize = 2;
        while ($lctSize < $numColors) {
            $lctExp++;
            $lctSize *= 2;
        }
        if ($lctSize < 2) { $lctSize = 2; $lctExp = 0; }
        if ($lctExp > 7) { $lctExp = 7; $lctSize = 256; }

        // Packed: local color table flag=1, interlace=0, sort=0, reserved=0, size=$lctExp
        $gif .= chr(0x80 | $lctExp);

        // Write local color table
        $lct = '';
        for ($c = 0; $c < $lctSize; $c++) {
            if ($c < $numColors) {
                $rgb = imagecolorsforindex($palImg, $c);
                $lct .= chr($rgb['red']) . chr($rgb['green']) . chr($rgb['blue']);
            } else {
                $lct .= "\x00\x00\x00";
            }
        }
        $gif .= $lct;

        // LZW encode the pixel data
        $minCodeSize = max(2, $lctExp + 1);
        $pixels = '';
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $px = imagecolorat($palImg, $x, $y);
                $pixels .= chr($px);
            }
        }

        $lzwData = _cursor_lzw_encode($pixels, $minCodeSize);
        $gif .= chr($minCodeSize);

        // Split LZW data into sub-blocks (max 255 bytes each)
        $lzwLen = strlen($lzwData);
        $pos = 0;
        while ($pos < $lzwLen) {
            $blockLen = min(255, $lzwLen - $pos);
            $gif .= chr($blockLen) . substr($lzwData, $pos, $blockLen);
            $pos += $blockLen;
        }
        $gif .= "\x00"; // block terminator

        imagedestroy($palImg);
    }

    // GIF Trailer
    $gif .= "\x3B";

    return $gif;
}


/**
 * LZW-encode pixel data for GIF.
 *
 * @param string $data          Raw pixel indices (one byte per pixel)
 * @param int    $minCodeSize   Minimum code size (typically 2–8)
 * @return string               LZW-compressed binary data
 */
function _cursor_lzw_encode(string $data, int $minCodeSize): string
{
    $clearCode = 1 << $minCodeSize;
    $eoiCode   = $clearCode + 1;
    $nextCode  = $eoiCode + 1;
    $codeSize  = $minCodeSize + 1;
    $maxCode   = (1 << $codeSize);

    // Output bit buffer
    $outBits   = 0;
    $outBitPos = 0;
    $output    = '';

    // Helper: append a code of $codeSize bits to the output stream
    $appendCode = function (int $code) use (&$outBits, &$outBitPos, &$output, &$codeSize) {
        $outBits |= ($code << $outBitPos);
        $outBitPos += $codeSize;
        while ($outBitPos >= 8) {
            $output .= chr($outBits & 0xFF);
            $outBits >>= 8;
            $outBitPos -= 8;
        }
    };

    // Initialize code table with single-character entries
    $table = [];
    for ($i = 0; $i < $clearCode; $i++) {
        $table[chr($i)] = $i;
    }

    // Start with clear code
    $appendCode($clearCode);

    $len = strlen($data);
    if ($len === 0) {
        $appendCode($eoiCode);
        if ($outBitPos > 0) {
            $output .= chr($outBits & 0xFF);
        }
        return $output;
    }

    $prefix = $data[0];

    for ($i = 1; $i < $len; $i++) {
        $ch = $data[$i];
        $combined = $prefix . $ch;

        if (isset($table[$combined])) {
            $prefix = $combined;
        } else {
            // Output the code for $prefix
            $appendCode($table[$prefix]);

            // Add new entry to the table
            if ($nextCode < 4096) {
                $table[$combined] = $nextCode++;
                if ($nextCode > $maxCode && $codeSize < 12) {
                    $codeSize++;
                    $maxCode = 1 << $codeSize;
                }
            } else {
                // Table full – emit clear code and reset
                $appendCode($clearCode);
                $table = [];
                for ($j = 0; $j < $clearCode; $j++) {
                    $table[chr($j)] = $j;
                }
                $nextCode = $eoiCode + 1;
                $codeSize = $minCodeSize + 1;
                $maxCode  = 1 << $codeSize;
            }

            $prefix = $ch;
        }
    }

    // Output remaining prefix
    $appendCode($table[$prefix]);

    // End of Information
    $appendCode($eoiCode);

    // Flush remaining bits
    if ($outBitPos > 0) {
        $output .= chr($outBits & 0xFF);
    }

    return $output;
}
