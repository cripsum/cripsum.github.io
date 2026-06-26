<?php
/**
 * cursor_helpers.php
 *
 * Server-side helpers for processing custom cursor uploads.
 * Handles image resizing, .cur validation, and .ani → animated GIF conversion.
 * Uses pure PHP GD — no ImageMagick or exec() calls.
 */

/**
 * Resize an uploaded image to $size × $size pixels and save as PNG.
 * Preserves transparency for PNG, WEBP, and GIF inputs.
 *
 * @param string $tmpPath   Path to the uploaded temp file
 * @param string $mimeType  Detected MIME type of the source image
 * @param string $outputPath Destination file path (will be .png)
 * @param int    $size       Target width and height (default 32)
 * @return bool True on success, false on failure
 */
function cursor_resize_image(string $tmpPath, string $mimeType, string $outputPath, int $size = 64): bool
{
    // Create GD image resource from the source file
    $src = null;
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $src = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            $src = @imagecreatefromwebp($tmpPath);
            break;
        case 'image/gif':
            // GD only loads the first frame of an animated GIF
            $src = @imagecreatefromgif($tmpPath);
            break;
        default:
            return false;
    }

    if (!$src) {
        return false;
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);

    // Create the output canvas with transparency support
    $dst = imagecreatetruecolor($size, $size);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }

    // Preserve transparency: fill with transparent background
    imagesavealpha($dst, true);
    imagealphablending($dst, false);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $transparent);

    // For source images that may have alpha, preserve it during copy
    imagealphablending($dst, true);

    // Resample the source into the $size × $size canvas
    $ok = imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, $srcW, $srcH);
    imagedestroy($src);

    if (!$ok) {
        imagedestroy($dst);
        return false;
    }

    // Save as PNG (always, for transparency support)
    imagesavealpha($dst, true);
    $result = imagepng($dst, $outputPath);
    imagedestroy($dst);

    return $result;
}


/**
 * Validate and copy a .cur file to the output path.
 * CUR header: 2 bytes reserved (0x0000), 2 bytes type (0x0002), 2 bytes count (>=1).
 *
 * @param string $tmpPath    Source file path
 * @param string $outputPath Destination file path
 * @return bool True on success, false on failure
 */
function cursor_process_cur_file(string $tmpPath, string $outputPath): bool
{
    $data = @file_get_contents($tmpPath, false, null, 0, 6);
    if ($data === false || strlen($data) < 6) {
        return false;
    }

    $header = unpack('vreserved/vtype/vcount', $data);
    if ($header['reserved'] !== 0 || $header['type'] !== 2 || $header['count'] < 1) {
        return false;
    }

    return copy($tmpPath, $outputPath);
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
 * Falls back to extracting the first frame as a static 32×32 PNG if full
 * animated conversion fails.
 *
 * @param string $aniPath    Source .ani file path
 * @param string $outputPath Destination file path (extension may change)
 * @return array ['ok' => bool, 'animated' => bool, 'ext' => 'gif'|'png'] or ['ok' => false, 'error' => '...']
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

    if ($anihData !== null && strlen($anihData) >= 36) {
        $anih = unpack(
            'VcbSizeOf/VnFrames/VnSteps/Vcx/Vcy/VcBitCount/VcPlanes/VjifRate/Vfl',
            $anihData
        );
        if ($anih) {
            $nFrames = $anih['nFrames'] ?: $nFrames;
            $nSteps  = $anih['nSteps'] ?: $nSteps;
            $jifRate = $anih['jifRate'] ?: $jifRate;
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
    $frameSize   = ($cx > 0 && $cx <= 256) ? min(128, max(32, $cx)) : 64;

    foreach ($iconChunks as $idx => $icoData) {
        $img = _cursor_ico_to_gd($icoData, $frameSize);
        if ($img) {
            $frameImages[$idx] = $img;
        }
    }

    if (empty($frameImages)) {
        return ['ok' => false, 'error' => 'Impossibile decodificare i frame del file .ani.'];
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

    // Fallback: if we ended up with only 0-1 usable frames, output static PNG
    if (count($animFrames) <= 1) {
        $singleFrame = $animFrames[0] ?? reset($frameImages);
        // Ensure it's 32×32
        $singleFrame = _cursor_ensure_size($singleFrame, $frameSize);
        $pngPath = preg_replace('/\.[^.]+$/', '.png', $outputPath);
        imagesavealpha($singleFrame, true);
        $ok = imagepng($singleFrame, $pngPath);
        _cursor_destroy_frames($frameImages);
        return $ok
            ? ['ok' => true, 'animated' => false, 'ext' => 'png']
            : ['ok' => false, 'error' => 'Impossibile salvare il frame statico PNG.'];
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
        ? ['ok' => true, 'animated' => true, 'ext' => 'gif']
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
function _cursor_ico_to_gd(string $icoData, int $size): ?\GdImage
{
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
