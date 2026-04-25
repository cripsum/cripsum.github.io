<?php
// Cripsum™ Profile System V2 helpers
// Richiede: config/session_init.php, config/database.php, includes/functions.php

if (!function_exists('profile_h')) {
    function profile_h($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

function profile_current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function profile_is_staff(): bool
{
    $role = $_SESSION['ruolo'] ?? 'utente';
    return in_array($role, ['admin', 'owner'], true);
}

function profile_can_edit(int $profileUserId): bool
{
    $currentUserId = profile_current_user_id();
    return ($currentUserId && $currentUserId === $profileUserId) || profile_is_staff();
}

function profile_csrf_token(): string
{
    if (empty($_SESSION['profile_csrf_token'])) {
        $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['profile_csrf_token'];
}

function profile_validate_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['profile_csrf_token'])
        && hash_equals($_SESSION['profile_csrf_token'], $token);
}

function profile_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function profile_is_valid_username(string $username): bool
{
    return (bool)preg_match('/^(?!_)(?!.*_$)[a-zA-Z0-9_]{3,20}$/', $username);
}

function profile_normalize_hex_color(?string $color): string
{
    $color = trim((string)$color);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        return strtolower($color);
    }
    return '#0f5bff';
}



function profile_is_valid_discord_id(?string $discordId): bool
{
    $discordId = trim((string)$discordId);
    return $discordId === '' || (bool)preg_match('/^\d{15,25}$/', $discordId);
}

function profile_short_url_label(?string $url): string
{
    $url = trim((string)$url);
    if ($url === '') return 'link';
    $host = parse_url($url, PHP_URL_HOST) ?: $url;
    $path = trim((string)(parse_url($url, PHP_URL_PATH) ?: ''), '/');
    $host = preg_replace('/^www\./i', '', $host) ?: $host;
    if ($path !== '') {
        $parts = explode('/', $path);
        $last = end($parts);
        if ($last) return '@' . mb_substr($last, 0, 26, 'UTF-8');
    }
    return mb_substr($host, 0, 30, 'UTF-8');
}

function profile_compact_number($number): string
{
    $number = (int)$number;
    if ($number >= 1000000) return rtrim(rtrim(number_format($number / 1000000, 1), '0'), '.') . 'M';
    if ($number >= 1000) return rtrim(rtrim(number_format($number / 1000, 1), '0'), '.') . 'K';
    return (string)$number;
}

function profile_allowed_value(string $value, array $allowed, string $fallback): string
{
    return in_array($value, $allowed, true) ? $value : $fallback;
}

function profile_is_safe_url(?string $url, bool $required = false): bool
{
    $url = trim((string)$url);
    if ($url === '') return !$required;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
    return in_array($scheme, ['http', 'https'], true);
}

function profile_clean_text(?string $value, int $max): string
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return mb_substr($value, 0, $max, 'UTF-8');
}

function profile_time_ago(?string $datetime): string
{
    if (!$datetime) return 'mai';
    try {
        $date = new DateTime($datetime, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Europe/Rome'));
        $now = new DateTime('now', new DateTimeZone('Europe/Rome'));
        $diff = $now->getTimestamp() - $date->getTimestamp();
        if ($diff < 60) return 'ora';
        if ($diff < 3600) return floor($diff / 60) . ' min fa';
        if ($diff < 86400) return floor($diff / 3600) . ' ore fa';
        if ($diff < 604800) return floor($diff / 86400) . ' giorni fa';
        return $date->format('d/m/Y');
    } catch (Throwable $e) {
        return 'sconosciuto';
    }
}

function profile_get_identifier(): ?string
{
    $identifier = $_GET['username'] ?? $_GET['u'] ?? $_GET['id'] ?? null;
    if ($identifier === null) return null;
    $identifier = trim((string)$identifier);
    return $identifier !== '' ? $identifier : null;
}

function profile_get_public_profile(mysqli $mysqli, string $identifier): ?array
{
    $byId = ctype_digit($identifier);
    $sql = "
        SELECT
            u.id,
            u.username,
            u.display_name,
            u.bio,
            u.data_creazione,
            u.soldi,
            u.ruolo,
            u.profile_banner_type,
            u.accent_color,
            u.profile_theme,
            u.profile_layout,
            u.profile_visibility,
            u.discord_id,
            u.profile_views,
            u.featured_badge_id,
            u.featured_project_id,
            u.featured_content_id,
            u.profile_updated_at,
            u.ultimo_accesso,
            COUNT(DISTINCT ua.achievement_id) AS num_achievement,
            COUNT(DISTINCT up.personaggio_id) AS num_personaggi,
            COALESCE(SUM(up.quantità), 0) AS total_personaggi
        FROM utenti u
        LEFT JOIN utenti_achievement ua ON ua.utente_id = u.id
        LEFT JOIN utenti_personaggi up ON up.utente_id = u.id
        WHERE " . ($byId ? "u.id = ?" : "LOWER(u.username) = LOWER(?)") . "
        GROUP BY u.id
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;
    if ($byId) {
        $id = (int)$identifier;
        $stmt->bind_param('i', $id);
    } else {
        $stmt->bind_param('s', $identifier);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
    return $profile ?: null;
}

function profile_get_edit_profile(mysqli $mysqli, int $userId): ?array
{
    $stmt = $mysqli->prepare("SELECT id, username, display_name, bio, data_creazione, ruolo, profile_banner_type, accent_color, profile_theme, profile_layout, profile_visibility, discord_id, profile_views, featured_badge_id, featured_project_id, featured_content_id, profile_updated_at FROM utenti WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $profile ?: null;
}

function profile_increment_views(mysqli $mysqli, int $profileId): void
{
    $viewerId = profile_current_user_id();
    if ($viewerId === $profileId) return;

    if (!isset($_SESSION['viewed_profiles_v2']) || !is_array($_SESSION['viewed_profiles_v2'])) {
        $_SESSION['viewed_profiles_v2'] = [];
    }
    if (in_array($profileId, $_SESSION['viewed_profiles_v2'], true)) return;

    $stmt = $mysqli->prepare("UPDATE utenti SET profile_views = profile_views + 1 WHERE id = ?");
    $stmt->bind_param('i', $profileId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['viewed_profiles_v2'][] = $profileId;
}

function profile_list_socials(mysqli $mysqli, int $userId, bool $onlyVisible = true): array
{
    $sql = "SELECT id, platform, label, url, sort_order, is_visible FROM utenti_social WHERE utente_id = ?" . ($onlyVisible ? " AND is_visible = 1" : "") . " ORDER BY sort_order ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_list_links(mysqli $mysqli, int $userId, bool $onlyVisible = true): array
{
    $sql = "SELECT id, title, description, url, icon, is_featured, sort_order, is_visible FROM utenti_links WHERE utente_id = ?" . ($onlyVisible ? " AND is_visible = 1" : "") . " ORDER BY is_featured DESC, sort_order ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_list_projects(mysqli $mysqli, int $userId, bool $onlyVisible = true): array
{
    $sql = "SELECT id, title, description, url, image_url, tech_stack, status, is_featured, sort_order, is_visible FROM utenti_projects WHERE utente_id = ?" . ($onlyVisible ? " AND is_visible = 1" : "") . " ORDER BY is_featured DESC, sort_order ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_list_contents(mysqli $mysqli, int $userId, bool $onlyVisible = true): array
{
    $sql = "SELECT id, content_type, title, description, url, thumbnail_url, is_featured, sort_order, is_visible FROM utenti_contents WHERE utente_id = ?" . ($onlyVisible ? " AND is_visible = 1" : "") . " ORDER BY is_featured DESC, sort_order ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_list_visible_badges(mysqli $mysqli, int $userId): array
{
    $stmt = $mysqli->prepare("
        SELECT a.id, a.nome, a.descrizione, a.img_url, a.punti, upb.sort_order
        FROM utenti_profile_badges upb
        INNER JOIN achievement a ON a.id = upb.achievement_id
        INNER JOIN utenti_achievement ua ON ua.achievement_id = a.id AND ua.utente_id = upb.utente_id
        WHERE upb.utente_id = ? AND upb.is_visible = 1
        ORDER BY upb.sort_order ASC, upb.id ASC
        LIMIT 8
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_list_unlocked_badges(mysqli $mysqli, int $userId): array
{
    $stmt = $mysqli->prepare("
        SELECT a.id, a.nome, a.descrizione, a.img_url, a.punti,
               CASE WHEN upb.id IS NULL THEN 0 ELSE 1 END AS selected
        FROM utenti_achievement ua
        INNER JOIN achievement a ON a.id = ua.achievement_id
        LEFT JOIN utenti_profile_badges upb ON upb.utente_id = ua.utente_id AND upb.achievement_id = a.id AND upb.is_visible = 1
        WHERE ua.utente_id = ?
        ORDER BY selected DESC, ua.data DESC
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_recent_activity(mysqli $mysqli, int $userId): array
{
    $activity = [];

    $stmt = $mysqli->prepare("SELECT activity_type, label, url, created_at FROM utenti_profile_activity WHERE utente_id = ? AND is_public = 1 ORDER BY created_at DESC LIMIT 6");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();

    if (count($activity) < 6) {
        $stmt = $mysqli->prepare("
            SELECT 'badge' AS activity_type, CONCAT('Ha sbloccato ', a.nome) AS label, NULL AS url, ua.data AS created_at
            FROM utenti_achievement ua
            INNER JOIN achievement a ON a.id = ua.achievement_id
            WHERE ua.utente_id = ?
            ORDER BY ua.data DESC
            LIMIT 6
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $badges = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
        $activity = array_slice(array_merge($activity, $badges), 0, 6);
    }

    return $activity;
}

function profile_record_activity(mysqli $mysqli, int $userId, string $type, string $label, ?string $url = null): void
{
    $type = profile_allowed_value($type, ['profile_update', 'project', 'content', 'badge', 'link'], 'profile_update');
    $label = profile_clean_text($label, 120);
    if ($label === '') return;
    $url = profile_is_safe_url($url) ? trim((string)$url) : null;
    $stmt = $mysqli->prepare("INSERT INTO utenti_profile_activity (utente_id, activity_type, label, url, is_public) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param('isss', $userId, $type, $label, $url);
    $stmt->execute();
    $stmt->close();
}

function profile_handle_image_upload(array $file, int $maxBytes): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['has_file' => false];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['has_file' => true, 'error' => 'Upload non riuscito.'];
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        return ['has_file' => true, 'error' => 'Immagine troppo pesante.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        return ['has_file' => true, 'error' => 'File non valido.'];
    }

    $info = @getimagesize($tmp);
    if (!$info || empty($info['mime'])) {
        return ['has_file' => true, 'error' => 'Il file non è una immagine valida.'];
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($info['mime'], $allowed, true)) {
        return ['has_file' => true, 'error' => 'Formato non supportato. Usa JPG, PNG, WEBP o GIF.'];
    }

    return [
        'has_file' => true,
        'blob' => file_get_contents($tmp),
        'mime' => $info['mime'],
    ];
}


function profile_handle_background_upload(array $file, int $maxBytes): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['has_file' => false];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['has_file' => true, 'error' => 'Upload non riuscito.'];
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        return ['has_file' => true, 'error' => 'File troppo pesante.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        return ['has_file' => true, 'error' => 'File non valido.'];
    }

    $originalName = strtolower((string)($file['name'] ?? ''));
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';

    $imageInfo = @getimagesize($tmp);
    if ($imageInfo && !empty($imageInfo['mime'])) {
        $mime = $imageInfo['mime'];
    }

    $allowedImages = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowedVideos = ['video/mp4', 'video/webm'];

    $isImage = in_array($mime, $allowedImages, true);
    $isVideo = in_array($mime, $allowedVideos, true) && in_array($extension, ['mp4', 'webm'], true);

    if (!$isImage && !$isVideo) {
        return ['has_file' => true, 'error' => 'Formato non supportato. Usa JPG, PNG, WEBP, GIF, MP4 o WEBM.'];
    }

    return [
        'has_file' => true,
        'blob' => file_get_contents($tmp),
        'mime' => $mime,
    ];
}

function profile_social_icon_class(string $platform): string
{
    $map = [
        'tiktok' => 'fab fa-tiktok',
        'instagram' => 'fab fa-instagram',
        'youtube' => 'fab fa-youtube',
        'twitch' => 'fab fa-twitch',
        'github' => 'fab fa-github',
        'discord' => 'fab fa-discord',
        'telegram' => 'fab fa-telegram-plane',
        'x' => 'fab fa-x-twitter',
        'twitter' => 'fab fa-x-twitter',
        'website' => 'fas fa-globe',
        'steam' => 'fab fa-steam',
        'other' => 'fas fa-link',
    ];
    return $map[strtolower($platform)] ?? 'fas fa-link';
}

function profile_status_label(string $status): string
{
    return match ($status) {
        'active' => 'Attivo',
        'paused' => 'In pausa',
        'finished' => 'Finito',
        'idea' => 'Idea',
        default => 'Attivo',
    };
}
