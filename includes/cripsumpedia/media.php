<?php
/**
 * Cripsumpedia — le foto caricate.
 *
 * Una foto fatta col telefono si porta dietro i dati EXIF, compresa la
 * posizione GPS di dove è stata scattata. Prima finiva sul sito così com'era;
 * adesso viene ridisegnata con GD (come fa includes/avatar_thumbnail.php per
 * gli avatar): esce un file nuovo, senza dati nascosti, girato per il verso
 * giusto e con il lato lungo al massimo di 1600 px. Le GIF restano come sono
 * (GD perderebbe l'animazione e il formato non ha EXIF).
 */

const CP_IMAGE_MAX_SIDE = 1600;
const CP_IMAGE_MAX_SOURCE_PIXELS = 40000000; // le «bombe» da 1 MB che diventano 10 GB in memoria

/**
 * Il verso in cui va girata una foto JPEG (tag Orientation dell'EXIF, 1-8).
 * Letto a mano: l'estensione exif di PHP non è detto che ci sia.
 */
function cp_jpeg_orientation(string $binary): int
{
    $length = strlen($binary);
    if ($length < 4 || substr($binary, 0, 2) !== "\xFF\xD8") return 1;

    $pos = 2;
    while ($pos + 4 <= $length) {
        if ($binary[$pos] !== "\xFF") return 1;
        $marker = ord($binary[$pos + 1]);
        if ($marker === 0xDA || $marker === 0xD9) return 1; // comincia l'immagine: niente EXIF
        $size = (ord($binary[$pos + 2]) << 8) | ord($binary[$pos + 3]);

        if ($marker === 0xE1 && substr($binary, $pos + 4, 6) === "Exif\0\0") {
            $tiff = $pos + 10;
            $little = substr($binary, $tiff, 2) === 'II';
            $read = static function (int $at, int $bytes) use ($binary, $length, $little): int {
                if ($at < 0 || $at + $bytes > $length) return -1;
                $chunk = substr($binary, $at, $bytes);
                $value = unpack($bytes === 2 ? ($little ? 'v' : 'n') : ($little ? 'V' : 'N'), $chunk);
                return $value ? (int)$value[1] : -1;
            };
            $ifd = $read($tiff + 4, 4);
            if ($ifd < 0) return 1;
            $ifd += $tiff;
            $count = $read($ifd, 2);
            for ($i = 0; $i < $count && $i < 200; $i++) {
                $entry = $ifd + 2 + $i * 12;
                if ($read($entry, 2) === 0x0112) {
                    $orientation = $read($entry + 8, 2);
                    return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
                }
            }
            return 1;
        }
        $pos += 2 + $size;
    }
    return 1;
}

/** Gira l'immagine come dice l'EXIF. Restituisce l'immagine (quella nuova se ruotata). */
function cp_apply_orientation(GdImage $image, int $orientation): GdImage
{
    $rotate = static function (GdImage $source, int $angle): GdImage {
        $rotated = imagerotate($source, $angle, 0);
        return $rotated instanceof GdImage ? $rotated : $source;
    };

    switch ($orientation) {
        case 2: imageflip($image, IMG_FLIP_HORIZONTAL); return $image;
        case 3: return $rotate($image, 180);
        case 4: imageflip($image, IMG_FLIP_VERTICAL); return $image;
        case 5: imageflip($image, IMG_FLIP_VERTICAL); return $rotate($image, -90);
        case 6: return $rotate($image, -90);
        case 7: imageflip($image, IMG_FLIP_HORIZONTAL); return $rotate($image, -90);
        case 8: return $rotate($image, 90);
        default: return $image;
    }
}

/**
 * Ridisegna una foto caricata.
 *
 * Restituisce il file nuovo come stringa; null se GD non c'è o il file è una
 * GIF (allora si tiene l'originale); false se il file non è un'immagine che GD
 * sa leggere o è troppo grande (allora si scarta).
 */
function cp_reencode_image(string $path, string $ext): string|false|null
{
    if ($ext === 'gif') return null;
    if (!function_exists('imagecreatefromstring') || !function_exists('getimagesizefromstring')) return null;
    if ($ext === 'webp' && !function_exists('imagewebp')) return null;

    $binary = @file_get_contents($path);
    if (!is_string($binary) || $binary === '') return false;

    $info = @getimagesizefromstring($binary);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > CP_IMAGE_MAX_SOURCE_PIXELS) return false;

    $image = @imagecreatefromstring($binary);
    if (!$image instanceof GdImage) return false;

    if ($ext === 'jpg' || $ext === 'jpeg') {
        $image = cp_apply_orientation($image, cp_jpeg_orientation($binary));
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $scale = min(1, CP_IMAGE_MAX_SIDE / max($width, $height));
    if ($scale < 1) {
        $newWidth = max(1, (int)round($width * $scale));
        $newHeight = max(1, (int)round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $image = $resized;
    }

    ob_start();
    $written = match ($ext) {
        'png' => (static function (GdImage $img): bool {
            imagesavealpha($img, true);
            return imagepng($img, null, 6);
        })($image),
        'webp' => imagewebp($image, null, 85),
        default => imagejpeg($image, null, 85),
    };
    $out = (string)ob_get_clean();

    return $written && $out !== '' ? $out : false;
}
