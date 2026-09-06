<?php
// Lets get_pfp.php answer without touching the database.
//
// The hosting caps how many PHP workers / MySQL connections one account may use
// at a time. A page showing twenty avatars opens twenty connections at once and
// the ones over the ceiling die with an empty HTTP 500, which is what made a
// different handful of profile pictures show up blank on every page load.
//
// Almost every avatar request is a repeat of one already answered, so the
// outcome is remembered in a tiny file and replayed with no database, no
// session and no image work at all.
//
// This file must stay dependency free: it is loaded before anything else.

const AVATAR_FASTCACHE_TTL_STAMPED = 604800; // 7 days: the key changes on update
const AVATAR_FASTCACHE_TTL_PLAIN = 300;      // 5 min when the caller sends no stamp

/** Directory holding the lookup records. */
function avatar_fastcache_dir(): string
{
    return __DIR__ . '/../uploads/avatar_cache/meta';
}

/**
 * Cache key for one answer.
 *
 * `$stamp` is the `t=` cache buster the pages already append (the profile's
 * last update time). When it is present the key changes as soon as the avatar
 * does, so the entry can be kept for a long time; without it the entry gets a
 * short life instead.
 */
function avatar_fastcache_path(int $userId, int $size, string $stamp, bool $forceLocal): ?string
{
    if ($userId <= 0) {
        return null;
    }

    $dir = avatar_fastcache_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }

    $key = $userId . '|' . $size . '|' . $stamp . '|' . ($forceLocal ? 'local' : 'any');

    return $dir . '/' . hash('sha256', $key) . '.json';
}

/**
 * Returns a remembered answer, or null.
 *
 * Shapes: ['kind' => 'file', 'path' => ..., 'mime' => ...]
 *         ['kind' => 'redirect', 'url' => ...]
 *         ['kind' => 'default']
 */
function avatar_fastcache_get(int $userId, int $size, string $stamp, bool $forceLocal): ?array
{
    $file = avatar_fastcache_path($userId, $size, $stamp, $forceLocal);
    if ($file === null || !is_file($file)) {
        return null;
    }

    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['kind']) || !isset($data['exp'])) {
        return null;
    }
    if ((int)$data['exp'] < time()) {
        return null;
    }

    // A remembered file may have been pruned in the meantime.
    if ($data['kind'] === 'file') {
        if (empty($data['path']) || !is_file($data['path'])) {
            return null;
        }
        return ['kind' => 'file', 'path' => (string)$data['path'], 'mime' => (string)($data['mime'] ?? 'image/webp')];
    }

    if ($data['kind'] === 'redirect') {
        $url = (string)($data['url'] ?? '');
        // Only ever replay a Discord CDN URL, never an arbitrary one.
        return str_starts_with($url, 'https://cdn.discordapp.com/')
            ? ['kind' => 'redirect', 'url' => $url]
            : null;
    }

    return $data['kind'] === 'default' ? ['kind' => 'default'] : null;
}

/** Remembers the answer for the next identical request. */
function avatar_fastcache_put(int $userId, int $size, string $stamp, bool $forceLocal, array $answer): void
{
    $file = avatar_fastcache_path($userId, $size, $stamp, $forceLocal);
    if ($file === null || empty($answer['kind'])) {
        return;
    }

    $answer['exp'] = time() + ($stamp !== '' ? AVATAR_FASTCACHE_TTL_STAMPED : AVATAR_FASTCACHE_TTL_PLAIN);

    // Written to a temp file first so a concurrent reader never sees a partial
    // record; the whole point is to survive heavy parallel traffic.
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, json_encode($answer), LOCK_EX) !== false) {
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }
}

/** Streams a cached file with validating headers, then stops the request. */
function avatar_fastcache_send_file(string $path, string $mime): void
{
    $mtime = @filemtime($path) ?: time();
    $size = @filesize($path);
    if ($size === false) {
        return; // caller falls through to the slow path
    }

    $etag = '"' . md5($path . '|' . $mtime . '|' . $size) . '"';

    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('ETag: ' . $etag);

    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }

    header('Content-Length: ' . $size);
    readfile($path);
    exit;
}
