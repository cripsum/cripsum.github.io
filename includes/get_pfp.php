<?php
// Serves a user's profile picture.
//
// The endpoint must ALWAYS answer with a real image: browsers receive
// `X-Content-Type-Options: nosniff` from .htaccess, so any response without a
// correct image Content-Type renders as a broken image instead of showing the
// default avatar.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/discord_avatar_sync.php';
require_once __DIR__ . '/avatar_thumbnail.php';

// A page can request 20+ avatars at once. Holding the session lock while
// streaming each one would serialize them all behind each other.
cripsum_release_session();

const PFP_DEFAULT_FILE = __DIR__ . '/../img/abdul.jpg';

const PFP_ALLOWED_MIMES = [
    'image/jpeg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/avif' => 'avif',
];

const PFP_EXT_MIMES = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'avif' => 'image/avif',
];

/**
 * Streams the shared fallback avatar and stops the request.
 *
 * The cache window stays short so a user who uploads a picture stops being
 * served the placeholder almost immediately.
 */
function pfp_send_default(): void
{
    if (is_file(PFP_DEFAULT_FILE)) {
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize(PFP_DEFAULT_FILE));
        header('Cache-Control: public, max-age=300');
        readfile(PFP_DEFAULT_FILE);
        exit;
    }

    // Last resort: a 1x1 transparent GIF, still a valid image response.
    header('Content-Type: image/gif');
    header('Cache-Control: public, max-age=60');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

/**
 * Resolves the mime type to answer with, preferring the stored one and falling
 * back to sniffing so legacy rows with an empty `profile_pic_type` still work.
 */
function pfp_resolve_mime(?string $storedMime, ?string $filePath, ?string $buffer): ?string
{
    $storedMime = strtolower(trim((string)$storedMime));
    if ($storedMime !== '' && isset(PFP_ALLOWED_MIMES[$storedMime])) {
        return $storedMime;
    }

    $detected = '';
    if ($filePath !== null && is_file($filePath) && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = strtolower((string)finfo_file($finfo, $filePath));
            finfo_close($finfo);
        }
    } elseif ($buffer !== null && $buffer !== '' && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = strtolower((string)finfo_buffer($finfo, $buffer));
            finfo_close($finfo);
        }
    }

    if ($detected !== '' && isset(PFP_ALLOWED_MIMES[$detected])) {
        return $detected;
    }

    if ($filePath !== null) {
        $ext = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
        if (isset(PFP_EXT_MIMES[$ext])) {
            return PFP_EXT_MIMES[$ext];
        }
    }

    return null;
}

/**
 * Maps a stored `/uploads/...` value to an absolute path, refusing anything
 * that escapes the uploads directory.
 */
function pfp_resolve_upload_path(string $relative): ?string
{
    if (!str_starts_with($relative, '/uploads/')) {
        return null;
    }
    if (str_contains($relative, "\0") || str_contains($relative, '..')) {
        return null;
    }

    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    if ($uploadsRoot === false) {
        return null;
    }

    $candidate = realpath(__DIR__ . '/..' . $relative);
    if ($candidate === false || !is_file($candidate)) {
        return null;
    }

    $normalizedRoot = rtrim(str_replace('\\', '/', $uploadsRoot), '/') . '/';
    $normalizedFile = str_replace('\\', '/', $candidate);

    return str_starts_with($normalizedFile, $normalizedRoot) ? $candidate : null;
}

/** Builds the Discord CDN URL for an avatar hash, or null when unusable. */
function pfp_discord_avatar_url(string $discordId, string $avatarHash, int $size): ?string
{
    if (!preg_match('/^\d{15,25}$/', $discordId)) {
        return null;
    }
    if (!preg_match('/^(a_)?[a-f0-9]{16,64}$/i', $avatarHash)) {
        return null;
    }

    $ext = str_starts_with($avatarHash, 'a_') ? 'gif' : 'png';
    return 'https://cdn.discordapp.com/avatars/' . $discordId . '/' . $avatarHash . '.' . $ext . '?size=' . $size;
}

/** Streams a file with validating cache headers and 304 support. */
function pfp_send_file(string $path, string $mime): void
{
    $mtime = @filemtime($path) ?: time();
    $etag = '"' . md5($path . '|' . $mtime . '|' . filesize($path)) . '"';

    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('ETag: ' . $etag);

    $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
        http_response_code(304);
        exit;
    }

    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Still an image, so callers that forget the parameter show the placeholder
    // rather than a broken tag.
    pfp_send_default();
}

$userId = (int)$_GET['id'];
if ($userId <= 0) {
    pfp_send_default();
}

// `local=1` skips the Discord redirect and serves the stored picture instead.
// Nothing in the site emits it: it is kept as a manual escape hatch for
// debugging and for linking a user's uploaded picture explicitly.
$forceLocal = isset($_GET['local']) && $_GET['local'] !== '0';

// Callers ask for the size they actually render at; anything else snaps to the
// nearest supported step. 256 stays the default for links that predate this.
$size = avatar_thumb_normalize_size(isset($_GET['size']) ? (int)$_GET['size'] : 256);

$stmt = $mysqli->prepare(
    "SELECT profile_pic, profile_pic_type, discord_id, discord_avatar, discord_use_avatar
     FROM utenti WHERE id = ? LIMIT 1"
);
if (!$stmt) {
    pfp_send_default();
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    pfp_send_default();
}

// 1. Discord avatar, when the user explicitly opted in for it.
if (!$forceLocal && (int)($row['discord_use_avatar'] ?? 0) === 1) {
    $discordId = trim((string)($row['discord_id'] ?? ''));
    // The stored hash goes stale as soon as the user changes their Discord
    // picture, and the CDN answers 404 for the old one. Resolve (and persist)
    // the current hash before building the URL.
    $discordHash = discord_avatar_sync($mysqli, $userId, $discordId, $row['discord_avatar'] ?? null);

    // Discord only serves power-of-two sizes; ask for the smallest one that
    // still covers what we render.
    $discordSize = 256;
    foreach ([16, 32, 64, 128, 256, 512, 1024] as $candidate) {
        if ($candidate >= $size) {
            $discordSize = $candidate;
            break;
        }
    }
    $discordUrl = pfp_discord_avatar_url($discordId, (string)$discordHash, $discordSize);
    if ($discordUrl !== null) {
        header('Cache-Control: public, max-age=300');
        header('Location: ' . $discordUrl, true, 302);
        exit;
    }
}

$picValue = (string)($row['profile_pic'] ?? '');
$storedMime = $row['profile_pic_type'] ?? null;

if ($picValue !== '') {
    if (str_starts_with($picValue, '/uploads/')) {
        // 2. Uploaded file on disk.
        $filePath = pfp_resolve_upload_path($picValue);
        if ($filePath !== null) {
            $mime = pfp_resolve_mime($storedMime, $filePath, null);
            if ($mime !== null) {
                // Downscale first: the stored original can be several MB, and
                // every caller renders it far smaller than that.
                $binary = @file_get_contents($filePath);
                if ($binary !== false) {
                    $thumb = avatar_thumbnail(
                        $binary,
                        $mime,
                        $size,
                        'file:' . $filePath . ':' . (@filemtime($filePath) ?: 0)
                    );
                    if ($thumb !== null) {
                        pfp_send_file($thumb['path'], $thumb['mime']);
                    }
                }
                pfp_send_file($filePath, $mime);
            }
        }
        // File is gone or unreadable: fall through to the default avatar.
    } elseif (preg_match('#^https?://#i', $picValue)) {
        // 3. Remote URL. Only Discord's CDN is trusted as a redirect target so
        //    this endpoint can never be turned into an open redirect.
        $host = strtolower((string)parse_url($picValue, PHP_URL_HOST));
        if ($host === 'cdn.discordapp.com' && str_starts_with(strtolower($picValue), 'https://')) {
            header('Cache-Control: public, max-age=600');
            header('Location: ' . $picValue, true, 302);
            exit;
        }
    } else {
        // 4. Legacy binary blob stored directly in the column.
        $mime = pfp_resolve_mime($storedMime, null, $picValue);
        if ($mime !== null) {
            $thumb = avatar_thumbnail($picValue, $mime, $size, 'blob:' . $userId . ':' . md5($picValue));
            if ($thumb !== null) {
                pfp_send_file($thumb['path'], $thumb['mime']);
            }

            header('Content-Type: ' . $mime);
            header('Content-Length: ' . strlen($picValue));
            header('Cache-Control: public, max-age=86400');
            echo $picValue;
            exit;
        }
    }
}

// 5. Nothing usable: Discord's own default avatar when connected, else the
//    shared placeholder.
if (!$forceLocal) {
    $discordId = trim((string)($row['discord_id'] ?? ''));
    if ((int)($row['discord_use_avatar'] ?? 0) === 1 && preg_match('/^\d{15,25}$/', $discordId)) {
        $fallbackIndex = abs(((int)$discordId >> 22) % 6);
        header('Cache-Control: public, max-age=600');
        header('Location: https://cdn.discordapp.com/embed/avatars/' . $fallbackIndex . '.png', true, 302);
        exit;
    }
}

pfp_send_default();
