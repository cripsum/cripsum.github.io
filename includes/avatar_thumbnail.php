<?php
// Generates and caches downscaled avatars.
//
// Profile pictures are stored at whatever resolution the user uploaded, and
// get_pfp.php used to stream that original everywhere. The supporters row on
// the homepage renders them at 48x48 yet was pulling ~10 MB (single files up to
// 2.7 MB), doubled by the marquee clones: many <img> elements never finished
// decoding and showed up broken. Serving a thumbnail fixes that at the source.

const AVATAR_THUMB_QUALITY = 82;
const AVATAR_THUMB_MAX_SOURCE_PIXELS = 40000000; // refuse decompression bombs
const AVATAR_THUMB_SKIP_BELOW_BYTES = 24576;     // already small: not worth it

/** Sizes a caller may ask for. */
function avatar_thumb_allowed_sizes(): array
{
    return [32, 48, 64, 96, 128, 192, 256, 384, 512, 1024];
}

/** Snaps any requested size to the closest supported one. */
function avatar_thumb_normalize_size(int $size): int
{
    $allowed = avatar_thumb_allowed_sizes();
    if (in_array($size, $allowed, true)) {
        return $size;
    }

    $best = 256;
    $bestDelta = PHP_INT_MAX;
    foreach ($allowed as $candidate) {
        $delta = abs($candidate - $size);
        if ($delta < $bestDelta) {
            $bestDelta = $delta;
            $best = $candidate;
        }
    }

    return $best;
}

/**
 * True for a GIF carrying more than one frame.
 *
 * Each frame is introduced by a Graphic Control Extension block, so counting
 * those separates an animation from a still GIF without decoding anything.
 */
function avatar_thumb_is_animated_gif(string $binary): bool
{
    if (strncmp($binary, 'GIF', 3) !== 0) {
        return false;
    }

    $frames = 0;
    $offset = 0;
    // 0x21 0xF9 0x04 = Graphic Control Extension, one per frame.
    $marker = chr(0x21) . chr(0xF9) . chr(0x04);

    while (($offset = strpos($binary, $marker, $offset)) !== false) {
        $frames++;
        if ($frames > 1) {
            return true;
        }
        $offset += 3;
    }

    return false;
}

/** Directory holding generated thumbnails. */
function avatar_thumb_dir(): string
{
    return __DIR__ . '/../uploads/avatar_cache';
}

/** True when GD can do the work. */
function avatar_thumb_available(): bool
{
    return function_exists('imagecreatefromstring')
        && function_exists('imagecopyresampled')
        && function_exists('getimagesizefromstring');
}

/**
 * Returns ['path' => ..., 'mime' => ...] for a cached thumbnail of $binary,
 * or null when the original should be served untouched.
 *
 * $cacheKey must change whenever the source image changes.
 */
function avatar_thumbnail(string $binary, string $sourceMime, int $size, string $cacheKey): ?array
{
    if ($binary === '' || !avatar_thumb_available()) {
        return null;
    }

    $size = avatar_thumb_normalize_size($size);

    // Small files are already cheap; re-encoding them buys nothing.
    if (strlen($binary) <= AVATAR_THUMB_SKIP_BELOW_BYTES) {
        return null;
    }

    // GD can only write a single frame, so resizing an animated GIF would drop
    // the animation. Do it anyway for small thumbnails, where the motion is not
    // visible and the saving is enormous, but leave larger requests (the
    // profile page) with the original moving image.
    if ($size >= 128 && avatar_thumb_is_animated_gif($binary)) {
        return null;
    }

    $info = @getimagesizefromstring($binary);
    if (!is_array($info) || empty($info[0]) || empty($info[1])) {
        return null;
    }
    [$srcW, $srcH] = [(int)$info[0], (int)$info[1]];

    // Already smaller than the target: nothing to gain.
    if (max($srcW, $srcH) <= $size) {
        return null;
    }
    if ($srcW * $srcH > AVATAR_THUMB_MAX_SOURCE_PIXELS) {
        return null;
    }

    $useWebp = function_exists('imagewebp');
    $extension = $useWebp ? 'webp' : 'png';
    $mime = $useWebp ? 'image/webp' : 'image/png';

    $dir = avatar_thumb_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }

    $name = hash('sha256', $cacheKey . '|' . $size . '|' . $extension) . '.' . $extension;
    $path = $dir . '/' . $name;

    if (is_file($path) && filesize($path) > 0) {
        return ['path' => $path, 'mime' => $mime];
    }

    avatar_thumb_prune($dir);

    return avatar_thumb_render($binary, $srcW, $srcH, $size, $path, $mime, $useWebp)
        ? ['path' => $path, 'mime' => $mime]
        : null;
}

/**
 * Occasionally drops thumbnails nothing has asked for in a long time.
 *
 * Re-uploading a picture changes the cache key, so the old entries would
 * otherwise pile up forever. Runs rarely and only when a thumbnail is being
 * generated anyway, so it never costs a normal request anything.
 */
function avatar_thumb_prune(string $dir, int $maxAgeDays = 60): void
{
    if (random_int(1, 400) !== 1) {
        return;
    }

    $cutoff = time() - ($maxAgeDays * 86400);
    foreach (glob($dir . '/*.{webp,png}', GLOB_BRACE) ?: [] as $file) {
        $touched = @filemtime($file);
        if ($touched !== false && $touched < $cutoff) {
            @unlink($file);
        }
    }
}

/** Does the actual decode / resample / encode, writing atomically. */
function avatar_thumb_render(string $binary, int $srcW, int $srcH, int $size, string $path, string $mime, bool $useWebp): bool
{
    $src = @imagecreatefromstring($binary);
    if (!$src) {
        return false;
    }

    // Cover-crop to a square, mirroring how the avatars are displayed.
    $side = min($srcW, $srcH);
    $offsetX = (int)(($srcW - $side) / 2);
    $offsetY = (int)(($srcH - $side) / 2);

    $dst = @imagecreatetruecolor($size, $size);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }

    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $transparent);
    imagealphablending($dst, true);

    $ok = @imagecopyresampled($dst, $src, 0, 0, $offsetX, $offsetY, $size, $size, $side, $side);
    imagedestroy($src);

    if (!$ok) {
        imagedestroy($dst);
        return false;
    }

    imagealphablending($dst, false);
    imagesavealpha($dst, true);

    // Write to a temp file first so a concurrent request never reads a partial
    // image, then move it into place.
    $tmp = $path . '.' . getmypid() . '.tmp';
    $written = $useWebp
        ? @imagewebp($dst, $tmp, AVATAR_THUMB_QUALITY)
        : @imagepng($dst, $tmp, 6);
    imagedestroy($dst);

    if (!$written || !is_file($tmp)) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }

    return true;
}
