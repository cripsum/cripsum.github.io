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

/** Every file that may hold the copy of one post (one per known extension). */
function content_media_cache_candidates(string $type, int $id): array
{
    $base = content_media_cache_dir() . '/' . content_media_cache_type($type) . '-' . $id . '.';
    $files = [];
    foreach (CONTENT_MEDIA_CACHE_EXT as $mime => $ext) {
        $files[$base . $ext] = $mime;
    }
    return $files;
}

/**
 * Sends the stored copy and stops the request, or returns when there is none
 * (or it has expired) so the caller goes on to the database.
 */
function content_media_cache_serve(string $type, int $id): void
{
    if ($id <= 0) {
        return;
    }

    foreach (content_media_cache_candidates($type, $id) as $file => $mime) {
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

        $etag = '"' . md5($file . '|' . $mtime . '|' . $size) . '"';
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=3600');
        header('ETag: ' . $etag);

        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }

        header('Content-Length: ' . $size);
        readfile($file);
        exit;
    }
}

/** Stores the media of an approved post. Unknown types are simply not kept. */
function content_media_cache_store(string $type, int $id, string $mime, string $blob): void
{
    if ($id <= 0 || $blob === '' || !isset(CONTENT_MEDIA_CACHE_EXT[$mime])) {
        return;
    }

    $dir = content_media_cache_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return;
    }

    // A post has one media: a copy left under another extension is stale.
    content_media_cache_forget($type, $id);

    $file = $dir . '/' . content_media_cache_type($type) . '-' . $id . '.' . CONTENT_MEDIA_CACHE_EXT[$mime];
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

/** Drops the stored copy of one post. Call it on delete, unapprove and edit. */
function content_media_cache_forget(string $type, int $id): void
{
    if ($id <= 0) {
        return;
    }

    foreach (content_media_cache_candidates($type, $id) as $file => $mime) {
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
