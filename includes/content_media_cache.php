<?php
// Lets api/content/media.php answer without touching the database.
//
// Post media (shitpost and Top Rimasti) live as blobs in the database, so every
// image on a page used to cost a session and a MySQL connection. The hosting
// allows about twenty connections at a time: a page with a dozen posts plus its
// avatars went over it and the surplus died with an empty HTTP 500.
//
// Only APPROVED posts are ever stored here, because the fast path cannot check
// who is asking. A copy lasts an hour and is dropped on the spot when the post
// is deleted, taken out of approval or edited (content_media_cache_forget()).
// The folder is closed to the web in .htaccess: files leave it only through
// media.php, which honours the expiry.
//
// A post can have several media, and each one a few versions: the file itself,
// the feed thumbnail, the cover frame of a video. They are told apart in the
// file name: {type}-{id}[-{n}][.{variant}].{ext}. The first media of a post,
// in full, keeps the plain name it always had.
//
// This file must stay dependency free: it is loaded before anything else.

const CONTENT_MEDIA_CACHE_TTL = 3600;

const CONTENT_MEDIA_CACHE_EXT = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
];

function content_media_cache_dir(): string
{
    return __DIR__ . '/../uploads/content_cache';
}

/** Same rule as cv2_normalize_type(), repeated here to stay dependency free. */
function content_media_cache_type(string $type): string
{
    return $type === 'rimasto' || $type === 'toprimasti' || $type === 'rimasti' ? 'rimasto' : 'shitpost';
}

function content_media_cache_variant(string $variant): string
{
    return $variant === 'thumb' || $variant === 'poster' ? $variant : '';
}

/** File name without the extension for one media of a post, in one version. */
function content_media_cache_base(string $type, int $id, int $n = 0, string $variant = ''): string
{
    $variant = content_media_cache_variant($variant);

    return content_media_cache_dir() . '/' . content_media_cache_type($type) . '-' . $id
        . ($n > 0 ? '-' . $n : '')
        . ($variant !== '' ? '.' . $variant : '');
}

/** Every file that may hold that copy (one per known extension). */
function content_media_cache_candidates(string $type, int $id, int $n = 0, string $variant = ''): array
{
    $base = content_media_cache_base($type, $id, $n, $variant) . '.';
    $files = [];
    foreach (CONTENT_MEDIA_CACHE_EXT as $mime => $ext) {
        $files[$base . $ext] = $mime;
    }
    return $files;
}

/**
 * Sends a media and stops the request. Give either a file or the bytes.
 *
 * Honours Range: without it a video cannot be seeked, and Safari on iPhone
 * refuses to play it at all.
 */
function content_media_send(string $mime, ?string $file, ?string $blob = null, ?string $etag = null): void
{
    $size = $file !== null ? (int)@filesize($file) : strlen((string)$blob);
    if ($size <= 0) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline');
    header('Cache-Control: private, max-age=3600');
    header('Accept-Ranges: bytes');
    if ($etag !== null) {
        header('ETag: ' . $etag);
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }
    }

    $start = 0;
    $end = $size - 1;
    $range = (string)($_SERVER['HTTP_RANGE'] ?? '');

    if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m) && ($m[1] !== '' || $m[2] !== '')) {
        if ($m[1] === '') {
            // "The last N bytes".
            $start = max(0, $size - (int)$m[2]);
        } else {
            $start = (int)$m[1];
            if ($m[2] !== '') {
                $end = min($end, (int)$m[2]);
            }
        }

        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }

        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    $length = $end - $start + 1;
    header('Content-Length: ' . $length);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if ($file === null) {
        echo $length === $size ? $blob : substr((string)$blob, $start, $length);
        exit;
    }

    $handle = @fopen($file, 'rb');
    if (!$handle) {
        exit;
    }
    if ($start > 0) {
        fseek($handle, $start);
    }
    $left = $length;
    while ($left > 0 && !feof($handle)) {
        $chunk = fread($handle, min(262144, $left));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($handle);
    exit;
}

/**
 * Sends the stored copy and stops the request, or returns when there is none
 * (or it has expired) so the caller goes on to the database.
 */
function content_media_cache_serve(string $type, int $id, int $n = 0, string $variant = ''): void
{
    if ($id <= 0 || $n < 0) {
        return;
    }

    foreach (content_media_cache_candidates($type, $id, $n, $variant) as $file => $mime) {
        $mtime = @filemtime($file);
        if ($mtime === false) {
            continue;
        }
        if ($mtime + CONTENT_MEDIA_CACHE_TTL < time()) {
            @unlink($file);
            continue;
        }

        $size = @filesize($file);
        if ($size === false || $size <= 0) {
            continue;
        }

        content_media_send($mime, $file, null, '"' . md5($file . '|' . $mtime . '|' . $size) . '"');
    }
}

/** Stores one media of an approved post. Unknown types are simply not kept. */
function content_media_cache_store(string $type, int $id, string $mime, string $blob, int $n = 0, string $variant = ''): void
{
    if ($id <= 0 || $n < 0 || $blob === '' || !isset(CONTENT_MEDIA_CACHE_EXT[$mime])) {
        return;
    }

    $dir = content_media_cache_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return;
    }

    // One copy per media and version: one left under another extension is stale.
    foreach (content_media_cache_candidates($type, $id, $n, $variant) as $old => $ignored) {
        if (is_file($old)) {
            @unlink($old);
        }
    }

    $file = content_media_cache_base($type, $id, $n, $variant) . '.' . CONTENT_MEDIA_CACHE_EXT[$mime];
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $blob, LOCK_EX) !== false) {
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    // Now and then, clear out what has expired: copies of posts nobody opens
    // any more (or deleted together with their author) must not pile up.
    if (random_int(1, 40) === 1) {
        content_media_cache_sweep();
    }
}

/**
 * Drops every stored copy of one post: all its media, in all versions.
 * Call it on delete, unapprove and edit.
 */
function content_media_cache_forget(string $type, int $id): void
{
    if ($id <= 0) {
        return;
    }

    // "type-12." and "type-12-": post 123 must not be caught.
    $prefix = content_media_cache_dir() . '/' . content_media_cache_type($type) . '-' . $id;
    foreach (array_merge(glob($prefix . '.*') ?: [], glob($prefix . '-*') ?: []) as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/** Removes every expired copy and any temp file left behind. */
function content_media_cache_sweep(): void
{
    $limit = time() - CONTENT_MEDIA_CACHE_TTL;
    foreach (glob(content_media_cache_dir() . '/*') ?: [] as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && $mtime < $limit) {
            @unlink($file);
        }
    }
}
