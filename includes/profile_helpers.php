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

function profile_optional_hex_color(?string $color): ?string
{
    $color = trim((string)$color);
    if ($color === '') return null;
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        return strtolower($color);
    }
    return null;
}



function profile_is_valid_discord_id(?string $discordId): bool
{
    $discordId = trim((string)$discordId);
    return $discordId === '' || (bool)preg_match('/^\d{15,25}$/', $discordId);
}


function profile_discord_avatar_url(string $discordId, ?string $avatarHash, int $size = 256): ?string
{
    $discordId = trim($discordId);
    $avatarHash = trim((string)$avatarHash);
    if (!profile_is_valid_discord_id($discordId) || $discordId === '' || $avatarHash === '') return null;

    $ext = str_starts_with($avatarHash, 'a_') ? 'gif' : 'png';
    $size = in_array($size, [64, 128, 256, 512, 1024], true) ? $size : 256;

    return 'https://cdn.discordapp.com/avatars/' . rawurlencode($discordId) . '/' . rawurlencode($avatarHash) . '.' . $ext . '?size=' . $size;
}

function profile_avatar_url(array $profile, int $size = 256): string
{
    $discordId = trim((string)($profile['discord_id'] ?? ''));
    $discordAvatar = trim((string)($profile['discord_avatar'] ?? ''));
    $useDiscordAvatar = (int)($profile['discord_use_avatar'] ?? 0) === 1;

    if ($useDiscordAvatar) {
        $url = profile_discord_avatar_url($discordId, $discordAvatar, $size);
        if ($url) return $url;
    }

    $stamp = !empty($profile['profile_updated_at']) ? (int)strtotime((string)$profile['profile_updated_at']) : time();
    return '/includes/get_pfp.php?id=' . (int)$profile['id'] . '&t=' . $stamp;
}

function profile_display_name(array $profile): string
{
    $useDiscordName = (int)($profile['discord_use_display_name'] ?? 0) === 1;
    $discordName = trim((string)($profile['discord_global_name'] ?? '')) ?: trim((string)($profile['discord_username'] ?? ''));

    if ($useDiscordName && $discordName !== '') return $discordName;

    return trim((string)($profile['display_name'] ?? '')) ?: (string)($profile['username'] ?? 'Profilo');
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
    if (strpos($url, '/uploads/profile_media/') === 0) {
        return strpos($url, '..') === false;
    }
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
    if (!$datetime) return 'unknown';
    try {
        $date = new DateTime($datetime, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Europe/Rome'));
        $now = new DateTime('now', new DateTimeZone('Europe/Rome'));
        $diff = $now->getTimestamp() - $date->getTimestamp();
        if ($diff < 60) return 'now';
        if ($diff < 3600) return floor($diff / 60) . ' min ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        return $date->format('d/m/Y');
    } catch (Throwable $e) {
        return 'unknown';
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
            u.is_premium,
            u.profile_layout_snap,
            u.profile_music_theme,
            u.profile_cursor_effect,
            u.profile_cursor_custom_url,
            u.profile_bg_grain,
            u.display_name,
            u.bio,
            u.data_creazione,
            u.soldi,
            u.ruolo,
            u.profile_banner_type,
            u.accent_color,
            u.profile_secondary_color,
            u.profile_card_color,
            u.profile_text_color,
            u.profile_link_style,
            u.profile_button_shape,
            u.profile_theme,
            u.profile_layout,
            u.profile_visibility,
            u.discord_id,
            u.discord_username,
            u.discord_global_name,
            u.discord_avatar,
            u.discord_use_avatar,
            u.discord_use_display_name,
            u.discord_connected_at,
            u.profile_status,
            u.profile_show_stats,
            u.profile_show_socials,
            u.profile_show_links,
            u.profile_show_projects,
            u.profile_show_contents,
            u.profile_show_blocks,
            u.profile_show_badges,
            u.profile_show_activity,
            u.profile_show_discord,
            u.profile_music_url,
            u.profile_music_mime,
            u.profile_music_title,
            u.profile_music_artist,
            u.profile_show_audio_player,
            u.profile_effect,
            u.profile_show_characters,
            u.avatar_ring_enabled,
            u.avatar_ring_style,
            u.avatar_ring_color,
            u.profile_views,
            u.featured_badge_id,
            u.featured_project_id,
            u.featured_content_id,
            u.profile_updated_at,
            u.ultimo_accesso,
            u.profile_enter_text,
            u.profile_click_to_enter,
            u.profile_socials_style,
            u.profile_show_embeds,
            u.profile_sections_order,
            u.profile_badges_display,
            u.profile_badges_position,
            u.discord_server_invite,
            u.discord_server_cache,
            u.discord_server_cache_time,
            u.profile_font,
            u.profile_border_radius,
            u.profile_card_opacity,
            u.profile_card_blur,
            u.profile_border_opacity,
            u.profile_border_color,
            u.profile_border_width,
            u.profile_name_style,
            u.profile_ui_shape,
            u.profile_avatar_shape,
            u.profile_social_size,
            u.profile_icon_spacing,
            u.profile_badge_size,
            u.profile_button_size,
            u.profile_avatar_border,
            u.custom_alias,
            u.tilt_enabled,
            u.tilt_max,
            u.tilt_glare,
            u.tilt_zoom,
            u.tilt_speed,
            u.profile_tags_json,
            u.profile_tab_title,
            u.profile_tab_animation,
            u.profile_tab_animation_speed,
            u.profile_tab_animation_text,
            u.profile_corner_style,
            u.profile_corner_style_custom,
            u.profile_border_style,
            u.profile_sections_config,
            u.profile_cursor_custom_center,
            u.profile_cursor_custom_hover_url,
            u.profile_cursor_custom_hover_center,
            u.profile_hide_meta,
            u.profile_show_audio_btn,
            u.profile_audio_btn_position,
            u.profile_audio_default_volume,
            u.profile_bg_overlay_opacity,
            u.profile_bg_blur,
            u.profile_bg_orbs_opacity,
            u.profile_bg_use_video_audio,
            COALESCE(ach.num_achievement, 0) AS num_achievement,
            COALESCE(inv.num_personaggi, 0) AS num_personaggi,
            COALESCE(inv.total_personaggi, 0) AS total_personaggi
        FROM utenti u
        LEFT JOIN (
            SELECT utente_id, COUNT(DISTINCT achievement_id) AS num_achievement
            FROM utenti_achievement
            GROUP BY utente_id
        ) ach ON ach.utente_id = u.id
        LEFT JOIN (
            SELECT utente_id, COUNT(DISTINCT personaggio_id) AS num_personaggi, COALESCE(SUM(`quantità`), 0) AS total_personaggi
            FROM utenti_personaggi
            GROUP BY utente_id
        ) inv ON inv.utente_id = u.id
        WHERE " . ($byId ? "u.id = ?" : "LOWER(u.username) = LOWER(?)") . "
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

function profile_get_public_profile_by_alias(mysqli $mysqli, string $alias): ?array
{
    $sql = "
        SELECT
            u.id,
            u.username,
            u.is_premium,
            u.profile_layout_snap,
            u.profile_music_theme,
            u.profile_cursor_effect,
            u.profile_cursor_custom_url,
            u.profile_bg_grain,
            u.display_name,
            u.bio,
            u.data_creazione,
            u.soldi,
            u.ruolo,
            u.profile_banner_type,
            u.accent_color,
            u.profile_secondary_color,
            u.profile_card_color,
            u.profile_text_color,
            u.profile_link_style,
            u.profile_button_shape,
            u.profile_theme,
            u.profile_layout,
            u.profile_visibility,
            u.discord_id,
            u.discord_username,
            u.discord_global_name,
            u.discord_avatar,
            u.discord_use_avatar,
            u.discord_use_display_name,
            u.discord_connected_at,
            u.profile_status,
            u.profile_show_stats,
            u.profile_show_socials,
            u.profile_show_links,
            u.profile_show_projects,
            u.profile_show_contents,
            u.profile_show_blocks,
            u.profile_show_badges,
            u.profile_show_activity,
            u.profile_show_discord,
            u.profile_music_url,
            u.profile_music_mime,
            u.profile_music_title,
            u.profile_music_artist,
            u.profile_show_audio_player,
            u.profile_effect,
            u.profile_show_characters,
            u.avatar_ring_enabled,
            u.avatar_ring_style,
            u.avatar_ring_color,
            u.profile_views,
            u.featured_badge_id,
            u.featured_project_id,
            u.featured_content_id,
            u.profile_updated_at,
            u.ultimo_accesso,
            u.profile_enter_text,
            u.profile_click_to_enter,
            u.profile_socials_style,
            u.profile_show_embeds,
            u.profile_sections_order,
            u.profile_badges_display,
            u.profile_badges_position,
            u.discord_server_invite,
            u.discord_server_cache,
            u.discord_server_cache_time,
            u.profile_font,
            u.profile_border_radius,
            u.profile_card_opacity,
            u.profile_card_blur,
            u.profile_border_opacity,
            u.profile_border_color,
            u.profile_border_width,
            u.profile_name_style,
            u.profile_ui_shape,
            u.profile_avatar_shape,
            u.profile_social_size,
            u.profile_icon_spacing,
            u.profile_badge_size,
            u.profile_button_size,
            u.profile_avatar_border,
            u.custom_alias,
            u.tilt_enabled,
            u.tilt_max,
            u.tilt_glare,
            u.tilt_zoom,
            u.tilt_speed,
            u.profile_tags_json,
            u.profile_tab_title,
            u.profile_tab_animation,
            u.profile_tab_animation_speed,
            u.profile_tab_animation_text,
            u.profile_corner_style,
            u.profile_corner_style_custom,
            u.profile_border_style,
            u.profile_sections_config,
            u.profile_cursor_custom_center,
            u.profile_cursor_custom_hover_url,
            u.profile_cursor_custom_hover_center,
            u.profile_hide_meta,
            u.profile_show_audio_btn,
            u.profile_audio_btn_position,
            u.profile_audio_default_volume,
            u.profile_bg_overlay_opacity,
            u.profile_bg_blur,
            u.profile_bg_orbs_opacity,
            u.profile_bg_use_video_audio,
            COALESCE(ach.num_achievement, 0) AS num_achievement,
            COALESCE(inv.num_personaggi, 0) AS num_personaggi,
            COALESCE(inv.total_personaggi, 0) AS total_personaggi
        FROM utenti u
        LEFT JOIN (
            SELECT utente_id, COUNT(DISTINCT achievement_id) AS num_achievement
            FROM utenti_achievement
            GROUP BY utente_id
        ) ach ON ach.utente_id = u.id
        LEFT JOIN (
            SELECT utente_id, COUNT(DISTINCT personaggio_id) AS num_personaggi, COALESCE(SUM(`quantità`), 0) AS total_personaggi
            FROM utenti_personaggi
            GROUP BY utente_id
        ) inv ON inv.utente_id = u.id
        WHERE LOWER(u.custom_alias) = LOWER(?)
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param('s', $alias);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
    return $profile ?: null;
}

function profile_get_edit_profile(mysqli $mysqli, int $userId): ?array
{
    $stmt = $mysqli->prepare("SELECT id, username, is_premium, profile_layout_snap, profile_music_theme, profile_cursor_effect, profile_cursor_custom_url, profile_cursor_custom_center, profile_cursor_custom_hover_url, profile_cursor_custom_hover_center, profile_bg_grain, display_name, bio, data_creazione, ruolo, profile_banner_type, accent_color, profile_secondary_color, profile_card_color, profile_text_color, profile_link_style, profile_button_shape, profile_theme, profile_layout, profile_visibility, discord_id, discord_username, discord_global_name, discord_avatar, discord_use_avatar, discord_use_display_name, discord_connected_at, profile_status, profile_show_stats, profile_show_socials, profile_show_links, profile_show_projects, profile_show_contents, profile_show_blocks, profile_show_badges, profile_show_activity, profile_show_discord, profile_music_url, profile_music_mime, profile_music_title, profile_music_artist, profile_show_audio_player, profile_effect, avatar_ring_enabled, avatar_ring_style, avatar_ring_color, profile_views, featured_badge_id, featured_project_id, featured_content_id, profile_show_characters, profile_updated_at, profile_enter_text, profile_click_to_enter, profile_socials_style, profile_show_embeds, profile_sections_order, profile_badges_display, profile_badges_position, discord_server_invite, discord_server_cache, discord_server_cache_time, profile_font, profile_border_radius, profile_card_opacity, profile_card_blur, profile_border_opacity, profile_border_color, profile_border_width, profile_name_style, profile_ui_shape, profile_avatar_shape, profile_social_size, profile_icon_spacing, profile_badge_size, profile_button_size, profile_avatar_border, custom_alias, tilt_enabled, tilt_max, tilt_glare, tilt_zoom, tilt_speed, profile_tags_json, profile_tab_title, profile_tab_animation, profile_tab_animation_speed, profile_tab_animation_text, profile_corner_style, profile_corner_style_custom, profile_border_style, profile_sections_config, profile_cursor_custom_center, profile_cursor_custom_hover_url, profile_cursor_custom_hover_center, profile_hide_meta, profile_show_audio_btn, profile_audio_btn_position, profile_audio_default_volume, profile_bg_overlay_opacity, profile_bg_blur, profile_bg_orbs_opacity, profile_bg_use_video_audio FROM utenti WHERE id = ? LIMIT 1");
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
    $sql = "SELECT id, platform, label, display_username, url, sort_order, is_visible, icon FROM utenti_social WHERE utente_id = ?" . ($onlyVisible ? " AND is_visible = 1" : "") . " ORDER BY sort_order ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_list_links(mysqli $mysqli, int $userId, bool $onlyVisible = true): array
{
    $sql = "SELECT id, title, description, url, icon, button_style, is_featured, sort_order, is_visible, card_tag_text, card_tag_bg, card_tag_color FROM utenti_links WHERE utente_id = ?" . ($onlyVisible ? " AND is_visible = 1" : "") . " ORDER BY is_featured DESC, sort_order ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_list_projects(mysqli $mysqli, int $userId, bool $onlyVisible = true): array
{
    $sql = "SELECT id, title, description, url, image_url, tech_stack, status, is_featured, sort_order, is_visible, card_tag_text, card_tag_bg, card_tag_color FROM utenti_projects WHERE utente_id = ?" . ($onlyVisible ? " AND is_visible = 1" : "") . " ORDER BY is_featured DESC, sort_order ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_list_contents(mysqli $mysqli, int $userId, bool $onlyVisible = true): array
{
    $sql = "SELECT id, content_type, title, description, url, thumbnail_url, is_featured, sort_order, is_visible, card_tag_text, card_tag_bg, card_tag_color FROM utenti_contents WHERE utente_id = ?" . ($onlyVisible ? " AND is_visible = 1" : "") . " ORDER BY is_featured DESC, sort_order ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}


function profile_list_blocks(mysqli $mysqli, int $userId, bool $onlyVisible = true): array
{
    $sql = "SELECT id, block_type, title, body, media_url, media_type, is_featured, sort_order, is_visible, card_tag_text, card_tag_bg, card_tag_color, no_card_style, media_position, text_align, media_align, media_fit FROM utenti_profile_blocks WHERE utente_id = ?" . ($onlyVisible ? " AND is_visible = 1" : "") . " ORDER BY is_featured DESC, sort_order ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_media_type_from_url(?string $url, string $fallback = 'image'): string
{
    $path = strtolower((string)(parse_url((string)$url, PHP_URL_PATH) ?: ''));
    if (preg_match('/\.(mp4|webm)$/', $path)) return 'video';
    if (preg_match('/\.gif$/', $path)) return 'gif';
    if (preg_match('/\.(jpg|jpeg|png|webp|avif)$/', $path)) return 'image';
    return profile_allowed_value($fallback, ['image', 'gif', 'video'], 'image');
}

function profile_content_type_label(string $type): string
{
    return match ($type) {
        'edit' => 'Edit',
        'video' => 'Video',
        'game' => 'Gioco',
        'post' => 'Post',
        'text' => 'Testo',
        'image' => 'Immagine',
        'gif' => 'GIF',
        default => 'Contenuto',
    };
}

function profile_activity_icon(string $type): string
{
    return match ($type) {
        'project' => 'fa-solid fa-cubes',
        'content' => 'fa-solid fa-play',
        'badge' => 'fa-solid fa-trophy',
        'link' => 'fa-solid fa-link',
        'social' => 'fa-solid fa-share-nodes',
        'media' => 'fa-solid fa-image',
        'music' => 'fa-solid fa-music',
        'discord' => 'fa-brands fa-discord',
        'status' => 'fa-solid fa-signal',
        'theme' => 'fa-solid fa-palette',
        default => 'fa-solid fa-clock',
    };
}

function profile_hex_to_rgb(string $hex): ?array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } elseif (strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    } else {
        return null;
    }
    return [$r, $g, $b];
}

function profile_list_visible_badges(mysqli $mysqli, int $userId): array
{
    $isPremium = false;
    $stmtPrem = $mysqli->prepare("SELECT is_premium FROM utenti WHERE id = ? LIMIT 1");
    if ($stmtPrem) {
        $stmtPrem->bind_param("i", $userId);
        $stmtPrem->execute();
        $resPrem = $stmtPrem->get_result()->fetch_assoc();
        $isPremium = (int)($resPrem['is_premium'] ?? 0) === 1;
        $stmtPrem->close();
    }

    $limitClause = $isPremium ? "" : "LIMIT 8";

    $stmt = $mysqli->prepare("
        (SELECT 'achievement' AS badge_source,
                a.id,
                a.nome,
                a.nome_en,
                COALESCE(NULLIF(upb.custom_description, ''), a.descrizione) AS descrizione,
                COALESCE(NULLIF(upb.custom_description, ''), a.descrizione_en) AS descrizione_en,
                a.img_url,
                a.punti,
                upb.sort_order,
                NULL AS color,
                0 AS glow,
                'none' AS animation,
                'custom' AS badge_type,
                NULL AS icon
         FROM utenti_profile_badges upb
         INNER JOIN achievement a ON a.id = upb.achievement_id
         INNER JOIN utenti_achievement ua ON ua.achievement_id = a.id AND ua.utente_id = upb.utente_id
         WHERE upb.utente_id = ? AND upb.is_visible = 1)
        UNION ALL
        (SELECT 'custom' AS badge_source,
                cb.id,
                cb.name AS nome,
                cb.name_en AS nome_en,
                cb.descrizione,
                cb.descrizione_en,
                cb.image_url AS img_url,
                0 AS punti,
                ucb.sort_order,
                cb.color,
                cb.glow,
                cb.animation,
                cb.badge_type,
                cb.icon
         FROM user_custom_badges ucb
         INNER JOIN custom_badges cb ON cb.id = ucb.badge_id
         WHERE ucb.utente_id = ? AND ucb.is_visible = 1)
        ORDER BY sort_order ASC, id ASC
        $limitClause
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_list_unlocked_badges(mysqli $mysqli, int $userId): array
{
    $stmt = $mysqli->prepare("
        SELECT a.id, a.nome, a.nome_en, a.descrizione_en, a.img_url, a.punti,
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

function profile_list_all_user_badges(mysqli $mysqli, int $userId): array
{
    $stmt = $mysqli->prepare("
        (SELECT 'achievement' AS badge_source,
                a.id,
                a.nome,
                a.nome_en,
                a.img_url,
                NULL AS icon,
                NULL AS color,
                0 AS glow,
                'none' AS animation,
                'custom' AS badge_type,
                CASE WHEN upb.id IS NULL THEN 0 ELSE 1 END AS selected,
                COALESCE(upb.sort_order, 999) AS sort_order
         FROM utenti_achievement ua
         INNER JOIN achievement a ON a.id = ua.achievement_id
         LEFT JOIN utenti_profile_badges upb ON upb.utente_id = ua.utente_id AND upb.achievement_id = a.id
         WHERE ua.utente_id = ?)
        UNION ALL
        (SELECT 'custom' AS badge_source,
                cb.id,
                cb.name AS nome,
                cb.name_en AS nome_en,
                cb.image_url AS img_url,
                cb.icon,
                cb.color,
                cb.glow,
                cb.animation,
                cb.badge_type,
                COALESCE(ucb.is_visible, 0) AS selected,
                COALESCE(ucb.sort_order, 999) AS sort_order
         FROM user_custom_badges ucb
         INNER JOIN custom_badges cb ON cb.id = ucb.badge_id
         WHERE ucb.utente_id = ?)
        ORDER BY selected DESC, sort_order ASC, id ASC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function profile_fetch_discord_server_data(?string $inviteCode): ?array
{
    if (empty($inviteCode)) {
        return null;
    }
    $url = "https://discord.com/api/v10/invites/" . urlencode($inviteCode) . "?with_counts=true";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CripsumProfileWidget/1.0 (PHP)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (is_array($data) && isset($data['guild'])) {
            return [
                'code' => $data['code'] ?? $inviteCode,
                'server_name' => $data['guild']['name'] ?? '',
                'guild_id' => $data['guild']['id'] ?? '',
                'icon_hash' => $data['guild']['icon'] ?? null,
                'online_members' => $data['approximate_presence_count'] ?? 0,
                'total_members' => $data['approximate_member_count'] ?? 0,
                'fetched_at' => time()
            ];
        }
    }
    return null;
}



function profile_unlock_achievement(mysqli $mysqli, int $userId, int $achievementId): bool
{
    if ($userId <= 0 || $achievementId <= 0) return false;

    $stmt = $mysqli->prepare("
        INSERT INTO utenti_achievement (utente_id, achievement_id, data)
        SELECT ?, ?, NOW()
        WHERE EXISTS (SELECT 1 FROM achievement WHERE id = ?)
          AND NOT EXISTS (
              SELECT 1 FROM utenti_achievement
              WHERE utente_id = ? AND achievement_id = ?
          )
    ");
    if (!$stmt) return false;

    $stmt->bind_param('iiiii', $userId, $achievementId, $achievementId, $userId, $achievementId);
    $ok = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

function profile_recent_activity(mysqli $mysqli, int $userId): array
{
    $items = [];

    $stmt = $mysqli->prepare("SELECT activity_type, label, url, created_at FROM utenti_profile_activity WHERE utente_id = ? AND is_public = 1 ORDER BY created_at DESC LIMIT 8");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
    }

    $stmt = $mysqli->prepare("SELECT 'badge' AS activity_type, CONCAT('Badge: ', a.nome_en) AS label, NULL AS url, ua.data AS created_at FROM utenti_achievement ua INNER JOIN achievement a ON a.id = ua.achievement_id WHERE ua.utente_id = ? ORDER BY ua.data DESC LIMIT 4");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $items = array_merge($items, $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: []);
        $stmt->close();
    }

    $stmt = $mysqli->prepare("SELECT 'project' AS activity_type, CONCAT('Project: ', title) AS label, url, COALESCE(updated_at, created_at) AS created_at FROM utenti_projects WHERE utente_id = ? AND is_visible = 1 ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 3");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $items = array_merge($items, $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: []);
        $stmt->close();
    }

    $stmt = $mysqli->prepare("SELECT 'content' AS activity_type, CONCAT('Content: ', title) AS label, url, COALESCE(updated_at, created_at) AS created_at FROM utenti_contents WHERE utente_id = ? AND is_visible = 1 ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 3");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $items = array_merge($items, $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: []);
        $stmt->close();
    }

    if ($stmt = $mysqli->prepare("SELECT 'media' AS activity_type, CONCAT(block_type, ' section') AS label, NULL AS url, created_at FROM utenti_profile_blocks WHERE utente_id = ? AND is_visible = 1 ORDER BY created_at DESC LIMIT 3")) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $items = array_merge($items, $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: []);
        $stmt->close();
    }

    usort($items, function ($a, $b) {
        return strtotime((string)($b['created_at'] ?? '1970-01-01')) <=> strtotime((string)($a['created_at'] ?? '1970-01-01'));
    });

    $seen = [];
    $deduped = [];
    foreach ($items as $item) {
        $key = ($item['activity_type'] ?? '') . '|' . ($item['label'] ?? '') . '|' . ($item['created_at'] ?? '');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $deduped[] = $item;
        if (count($deduped) >= 6) break;
    }

    return $deduped;
}

function profile_record_activity(mysqli $mysqli, int $userId, string $type, string $label, ?string $url = null): void
{
    $type = profile_allowed_value($type, ['profile_update', 'project', 'content', 'badge', 'link', 'social', 'media', 'music', 'discord', 'status', 'theme'], 'profile_update');
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
        return ['has_file' => true, 'error' => 'Upload failed.'];
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        return ['has_file' => true, 'error' => 'The file is too large.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        return ['has_file' => true, 'error' => 'Invalid file.'];
    }

    $info = @getimagesize($tmp);
    if (!$info || empty($info['mime'])) {
        return ['has_file' => true, 'error' => 'The file is not a valid image.'];
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mime = $info['mime'];
    if (!in_array($mime, $allowed, true)) {
        return ['has_file' => true, 'error' => 'Unsupported format. Use JPG, PNG, WEBP, or GIF.'];
    }

    $ext = 'png';
    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
        $ext = 'jpg';
    } elseif ($mime === 'image/webp') {
        $ext = 'webp';
    } elseif ($mime === 'image/gif') {
        $ext = 'gif';
    }

    return [
        'has_file' => true,
        'tmp_path' => $tmp,
        'mime' => $mime,
        'ext' => $ext
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

    $exts = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm'
    ];
    $ext = $exts[$mime] ?? $extension;

    return [
        'has_file' => true,
        'tmp_path' => $tmp,
        'mime' => $mime,
        'ext' => $ext
    ];
}


function profile_handle_audio_upload(array $file, int $maxBytes): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['has_file' => false];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['has_file' => true, 'error' => 'Upload audio non riuscito.'];
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        return ['has_file' => true, 'error' => 'MP3 troppo pesante.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        return ['has_file' => true, 'error' => 'File audio non valido.'];
    }

    $originalName = strtolower((string)($file['name'] ?? ''));
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';

    $allowedMimes = ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/x-mp3', 'audio/mpeg3', 'application/octet-stream'];
    if ($extension !== 'mp3' || !in_array($mime, $allowedMimes, true)) {
        return ['has_file' => true, 'error' => 'Formato audio non supportato. Usa solo MP3.'];
    }

    return [
        'has_file' => true,
        'blob' => file_get_contents($tmp),
        'mime' => 'audio/mpeg',
    ];
}

function profile_social_icon_class(string $platform): string
{
    $map = [
        'tiktok' => 'fa-brands fa-tiktok',
        'instagram' => 'fa-brands fa-instagram',
        'youtube' => 'fa-brands fa-youtube',
        'twitch' => 'fa-brands fa-twitch',
        'github' => 'fa-brands fa-github',
        'discord' => 'fa-brands fa-discord',
        'telegram' => 'fa-brands fa-telegram',
        'x' => 'fa-brands fa-x-twitter',
        'twitter' => 'fa-brands fa-x-twitter',
        'spotify' => 'fa-brands fa-spotify',
        'soundcloud' => 'fa-brands fa-soundcloud',
        'steam' => 'fa-brands fa-steam',
        'reddit' => 'fa-brands fa-reddit-alien',
        'pinterest' => 'fa-brands fa-pinterest',
        'snapchat' => 'fa-brands fa-snapchat',
        'facebook' => 'fa-brands fa-facebook',
        'linkedin' => 'fa-brands fa-linkedin',
        'paypal' => 'fa-brands fa-paypal',
        'patreon' => 'fa-brands fa-patreon',
        'kick' => 'fa-solid fa-k',
        'bluesky' => 'fa-solid fa-cloud',
        'threads' => 'fa-brands fa-threads',
        'behance' => 'fa-brands fa-behance',
        'dribbble' => 'fa-brands fa-dribbble',
        'website' => 'fa-solid fa-globe',
        'email' => 'fa-solid fa-envelope',
        'other' => 'fa-solid fa-link',
    ];
    return $map[strtolower($platform)] ?? 'fa-solid fa-link';
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

function profile_bool_from_post(string $key, bool $default = true): int
{
    if (!array_key_exists($key, $_POST)) {
        return $default ? 1 : 0;
    }
    $value = $_POST[$key];
    if (is_array($value)) return 0;
    return in_array((string)$value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
}

function profile_badge_rarity(int $points): array
{
    if ($points >= 100) return ['label' => 'Legendary', 'class' => 'legendary'];
    if ($points >= 50) return ['label' => 'Epic', 'class' => 'epic'];
    if ($points >= 20) return ['label' => 'Rare', 'class' => 'rare'];
    return ['label' => 'Common', 'class' => 'common'];
}

function profile_list_inventory_characters(mysqli $mysqli, int $userId): array
{
    $stmt = $mysqli->prepare("
        SELECT
            p.id,
            p.nome,
            COALESCE(p.img_url, '')  AS img_url,
            COALESCE(p.rarità, '')   AS rarità,
            up.quantità,
            CASE WHEN upc.id IS NULL THEN 0 ELSE 1 END AS selected
        FROM utenti_personaggi up
        INNER JOIN personaggi p
               ON p.id = up.personaggio_id
        LEFT JOIN utenti_profile_characters upc
               ON upc.utente_id = up.utente_id
              AND upc.personaggio_id = p.id
        WHERE up.utente_id = ?
        ORDER BY selected DESC, p.rarità DESC, p.nome ASC
    ");
    if (!$stmt) return [];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

/**
 * Personaggi visibili sul profilo pubblico (max 12).
 */
function profile_list_displayed_characters(mysqli $mysqli, int $userId): array
{
    $stmt = $mysqli->prepare("
        SELECT
            p.id,
            p.nome,
            COALESCE(p.img_url, '') AS img_url,
            COALESCE(p.rarità, '')  AS rarità,
            up.quantità
        FROM utenti_profile_characters upc
        INNER JOIN personaggi p
               ON p.id = upc.personaggio_id
        INNER JOIN utenti_personaggi up
               ON up.utente_id = upc.utente_id
              AND up.personaggio_id = p.id
        WHERE upc.utente_id = ?
          AND upc.is_visible = 1
        ORDER BY upc.sort_order ASC, upc.id ASC
        LIMIT 12
    ");
    if (!$stmt) return [];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

/**
 * Risolve l'URL immagine di un personaggio.
 * Se img_url è un URL assoluto lo usa direttamente, altrimenti prepende /img/.
 */
function profile_character_img_url(array $character): string
{
    $img = trim((string)($character['img_url'] ?? ''));
    if ($img === '') return '';
    if (profile_is_safe_url($img)) return $img;
    return '/img/' . ltrim($img, '/');
}

/**
 * Mappa la rarità al CSS class name (allineato a .rarity-* dei badge).
 * Aggiungi qui altri alias se il tuo sistema usa nomi diversi.
 */
function profile_character_rarity_class(string $rarity): string
{
    return match (strtolower(trim($rarity))) {
        'leggendario', 'legendary', '5', '5★', 'ur'  => 'legendary',
        'epico',       'epic',      '4', '4★', 'ssr' => 'epic',
        'raro',        'rare',      '3', '3★', 'sr'  => 'rare',
        default                                       => 'common',
    };
}

/**
 * Recupera l'elenco degli embed configurati dall'utente.
 */
function profile_list_embeds(mysqli $mysqli, int $userId, bool $onlyVisible = true): array
{
    $sql = "SELECT id, type, title, url, sort_order, is_visible FROM utenti_embeds WHERE utente_id = ?" . ($onlyVisible ? " AND is_visible = 1" : "") . " ORDER BY sort_order ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

/**
 * Converte un URL di Spotify nel rispettivo link di embed sicuro.
 */
function profile_get_spotify_embed_url(string $url): ?string
{
    if (preg_match('#spotify\.com/(playlist|track|album|artist)/([a-zA-Z0-9]+)#i', $url, $matches)) {
        return 'https://open.spotify.com/embed/' . $matches[1] . '/' . $matches[2];
    }
    return null;
}

/**
 * Converte un URL di YouTube (watch, shorts, playlist o share) nel rispettivo link di embed sicuro.
 */
function profile_get_youtube_embed_url(string $url): ?string
{
    // Watch URL, shorts, share o embed
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/|youtube\.com/shorts/)([a-zA-Z0-9_-]+)#i', $url, $matches)) {
        $id = $matches[1];
        $embed = 'https://www.youtube.com/embed/' . $id;
        if (preg_match('#[?&]list=([a-zA-Z0-9_-]+)#i', $url, $listMatches)) {
            $embed .= '?list=' . $listMatches[1];
        }
        return $embed;
    }
    // Solo playlist
    if (preg_match('#youtube\.com/playlist\?list=([a-zA-Z0-9_-]+)#i', $url, $matches)) {
        return 'https://www.youtube.com/embed/videoseries?list=' . $matches[1];
    }
    return null;
}

function profile_format_name(string $displayName, array $styleConfig): string
{
    $animation = $styleConfig['animation'] ?? 'none';
    if ($animation === 'bounce') {
        $len = mb_strlen($displayName, 'UTF-8');
        $output = '';
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($displayName, $i, 1, 'UTF-8');
            if ($char === ' ') {
                $output .= '<span class="name-char space-char" style="--char-index: ' . $i . ';">&nbsp;</span>';
            } else {
                $output .= '<span class="name-char" style="--char-index: ' . $i . ';">' . htmlspecialchars($char, ENT_QUOTES, 'UTF-8') . '</span>';
            }
        }
        return $output;
    }
    return htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
}

function profile_markdown_to_html(?string $markdown): string
{
    if ($markdown === null || $markdown === '') {
        return '';
    }

    // Convertiamo i fine riga nello standard \n
    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

    // Escape dei caratteri HTML per sicurezza
    $html = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');

    // 1. Code Blocks (prima di altri tag, per evitare conflitti)
    $html = preg_replace_callback('/```(?:[a-zA-Z0-9#+-]+)?\n(.*?)\n```/s', function($matches) {
        return '<pre><code>' . $matches[1] . '</code></pre>';
    }, $html);

    // 2. Headings
    $html = preg_replace('/^######\s+(.*?)$/m', '<h6>$1</h6>', $html);
    $html = preg_replace('/^#####\s+(.*?)$/m', '<h5>$1</h5>', $html);
    $html = preg_replace('/^####\s+(.*?)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^###\s+(.*?)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^##\s+(.*?)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^#\s+(.*?)$/m', '<h1>$1</h1>', $html);

    // 3. Strikethrough (Sbarrato)
    $html = preg_replace('/~~(.*?)~~/', '<del>$1</del>', $html);

    // 4. Bold, Italic, Images, Links, Inline Code
    $html = preg_replace('/(\*\*|__)(.*?)\1/', '<strong>$2</strong>', $html);
    $html = preg_replace('/(\*|_)(.*?)\1/', '<em>$2</em>', $html);
    $html = preg_replace('/!\[(.*?)\]\((.*?)\)/', '<img src="$2" alt="$1" style="max-width:100%; height:auto;">', $html);
    $html = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $html);
    $html = preg_replace('/`(.*?)`/', '<code>$1</code>', $html);

    // 5. Horizontal Rules (hr)
    $html = preg_replace('/^\s*([-*_]){3,}\s*$/m', '<hr>', $html);

    // 6. Tabelle, Liste, Citazioni (lavorando riga per riga)
    $lines = explode("\n", $html);
    $inList = false; // 'ul', 'ol' o false
    $inBlockquote = false;
    $inTable = false;
    $tableRows = [];

    // Helper per renderizzare una tabella accumulata
    $renderTable = function(array $rows): string {
        if (count($rows) === 0) return '';
        $htmlTable = '<div style="overflow-x: auto; margin: 1rem 0;"><table class="profile-markdown-table" style="width: 100%; border-collapse: collapse; border: 1px solid rgba(255,255,255,0.1);">';
        
        $hasHeader = false;
        if (count($rows) > 1) {
            $separator = trim($rows[1]);
            // Rimuoviamo caratteri di formattazione della riga separatore
            $separator = str_replace(['-', ':', ' ', '|'], '', $separator);
            if ($separator === '') {
                $hasHeader = true;
            }
        }
        
        if ($hasHeader) {
            // Intestazione
            $htmlTable .= '<thead><tr>';
            $cols = explode('|', $rows[0]);
            foreach ($cols as $col) {
                $htmlTable .= '<th style="border: 1px solid rgba(255,255,255,0.1); padding: 8px 12px; font-weight: 600; text-align: left;">' . trim($col) . '</th>';
            }
            $htmlTable .= '</tr></thead>';
            
            // Corpo
            $htmlTable .= '<tbody>';
            for ($r = 2; $r < count($rows); $r++) {
                $htmlTable .= '<tr>';
                $cols = explode('|', $rows[$r]);
                foreach ($cols as $col) {
                    $htmlTable .= '<td style="border: 1px solid rgba(255,255,255,0.1); padding: 8px 12px;">' . trim($col) . '</td>';
                }
                $htmlTable .= '</tr>';
            }
            $htmlTable .= '</tbody>';
        } else {
            // Corpo semplice senza header
            $htmlTable .= '<tbody>';
            foreach ($rows as $row) {
                $htmlTable .= '<tr>';
                $cols = explode('|', $row);
                foreach ($cols as $col) {
                    $htmlTable .= '<td style="border: 1px solid rgba(255,255,255,0.1); padding: 8px 12px;">' . trim($col) . '</td>';
                }
                $htmlTable .= '</tr>';
            }
            $htmlTable .= '</tbody>';
        }
        $htmlTable .= '</table></div>';
        return $htmlTable;
    };

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);

        // --- Gestione Tabelle ---
        if (preg_match('/^\|(.*)\|$/', $trimmed, $matches)) {
            if ($inList) {
                $lines[$i - 1] .= '</' . $inList . '>';
                $inList = false;
            }
            if ($inBlockquote) {
                $lines[$i - 1] .= '</blockquote>';
                $inBlockquote = false;
            }

            if (!$inTable) {
                $inTable = true;
                $tableRows = [];
            }
            $tableRows[] = $matches[1];
            $lines[$i] = '__MARKDOWN_TABLE_ROW_PLACEHOLDER__'; 
            continue;
        } else {
            if ($inTable) {
                $tableHtml = $renderTable($tableRows);
                for ($k = $i - 1; $k >= 0; $k--) {
                    if ($lines[$k] === '__MARKDOWN_TABLE_ROW_PLACEHOLDER__') {
                        $lines[$k] = $tableHtml;
                        break;
                    }
                }
                $inTable = false;
            }
        }

        // --- Gestione Blockquote ---
        if (preg_match('/^&gt;\s?(.*)$/', $trimmed, $matches)) {
            if ($inList) {
                $lines[$i] = '</' . $inList . '>' . $lines[$i];
                $inList = false;
            }
            $bqContent = $matches[1];
            if (!$inBlockquote) {
                $lines[$i] = '<blockquote>' . $bqContent;
                $inBlockquote = true;
            } else {
                $lines[$i] = $bqContent;
            }
            continue;
        } else {
            if ($inBlockquote) {
                $lines[$i] = '</blockquote>' . $line;
                $inBlockquote = false;
            }
        }

        // --- Gestione Liste (ul e ol) ---
        if (preg_match('/^[\*\-]\s+(.*)$/', $trimmed, $matches)) {
            $li = $matches[1];
            if ($inList !== 'ul') {
                $prefix = $inList ? '</' . $inList . '>' : '';
                $lines[$i] = $prefix . '<ul><li>' . $li . '</li>';
                $inList = 'ul';
            } else {
                $lines[$i] = '<li>' . $li . '</li>';
            }
        } else if (preg_match('/^\d+\.\s+(.*)$/', $trimmed, $matches)) {
            $li = $matches[1];
            if ($inList !== 'ol') {
                $prefix = $inList ? '</' . $inList . '>' : '';
                $lines[$i] = $prefix . '<ol><li>' . $li . '</li>';
                $inList = 'ol';
            } else {
                $lines[$i] = '<li>' . $li . '</li>';
            }
        } else {
            if ($inList) {
                $lines[$i] = '</' . $inList . '>' . $line;
                $inList = false;
            }
        }
    }

    if ($inTable) {
        $tableHtml = $renderTable($tableRows);
        for ($k = count($lines) - 1; $k >= 0; $k--) {
            if ($lines[$k] === '__MARKDOWN_TABLE_ROW_PLACEHOLDER__') {
                $lines[$k] = $tableHtml;
                break;
            }
        }
    }
    if ($inBlockquote) {
        $lines[] = '</blockquote>';
    }
    if ($inList) {
        $lines[] = '</' . $inList . '>';
    }

    $lines = array_filter($lines, function($l) { return $l !== '__MARKDOWN_TABLE_ROW_PLACEHOLDER__'; });
    $html = implode("\n", $lines);

    $html = nl2br($html);

    $html = preg_replace('/(<h[1-6]>.*?<\/h[1-6]>)<br\s*\/?>/', '$1', $html);
    $html = preg_replace('/(<\/?[u|o]l>)<br\s*\/?>/', '$1', $html);
    $html = preg_replace('/(<li>.*?<\/li>)<br\s*\/?>/', '$1', $html);
    $html = preg_replace('/(<\/blockquote>)<br\s*\/?>/', '$1', $html);
    $html = preg_replace('/(<hr>)<br\s*\/?>/', '$1', $html);
    $html = preg_replace('/(<\/table>|<\/tr>|<\/thead>|<\/tbody>|<div style="overflow-x: auto; margin: 1rem 0;">)<br\s*\/?>/', '$1', $html);
    $html = preg_replace('/(<\/pre>|<pre><code>|<\/code><\/pre>)<br\s*\/?>/', '$1', $html);

    return $html;
}

function profile_render_icon(?string $icon, string $default = 'fa-solid fa-link', string $class = ''): string
{
    $icon = trim((string)$icon);
    if ($icon === '') {
        $icon = $default;
    }
    if ($icon === '') {
        return '';
    }

    if (str_starts_with($icon, 'uploads/profile_media/')) {
        $icon = '/' . $icon;
    }

    // Older section configs truncated uploaded paths to 50 characters. Resolve
    // a unique matching file so existing custom icons keep working.
    if (preg_match('#^/uploads/profile_media/user_\d+/(?:media|cursor)_[a-f0-9]+$#i', $icon)) {
        $root = realpath(__DIR__ . '/..');
        if ($root !== false) {
            $matches = glob($root . str_replace('/', DIRECTORY_SEPARATOR, $icon) . '*') ?: [];
            $matches = array_values(array_filter($matches, static function ($path) {
                return is_file($path) && preg_match('/\.(?:png|jpe?g|gif|webp|svg)$/i', $path);
            }));
            if (count($matches) === 1) {
                $relative = str_replace('\\', '/', substr($matches[0], strlen($root)));
                $icon = '/' . ltrim($relative, '/');
            }
        }
    }

    $isRemoteImage = preg_match('/^https?:\/\//i', $icon) === 1 && profile_is_safe_url($icon, true);
    $isUploadedImage = str_starts_with($icon, '/uploads/profile_media/') && profile_is_safe_url($icon, true);

    if ($isRemoteImage || $isUploadedImage) {
        $fallbackClass = trim($default) !== '' ? trim($default) : 'fa-solid fa-image';
        return '<span class="profile-custom-icon-wrap ' . profile_h($class) . '">' .
            '<img src="' . profile_h($icon) . '" class="profile-custom-icon" alt="" onerror="this.parentElement.classList.add(\'is-broken\')">' .
            '<i class="profile-custom-icon-fallback ' . profile_h($fallbackClass) . '" aria-hidden="true"></i>' .
            '</span>';
    }

    return '<i class="' . profile_h($icon) . ' ' . profile_h($class) . '"></i>';
}

function profile_cleanup_unused_media(mysqli $mysqli, int $userId): void
{
    $uploadDir = __DIR__ . '/../uploads/profile_media/user_' . $userId;
    if (!is_dir($uploadDir)) {
        return;
    }

    // Get all files on disk
    $filesOnDisk = [];
    $dirIter = new DirectoryIterator($uploadDir);
    foreach ($dirIter as $fileInfo) {
        if ($fileInfo->isFile()) {
            $filesOnDisk[] = $fileInfo->getFilename();
        }
    }

    if (empty($filesOnDisk)) {
        return;
    }

    // Collect all referenced files
    $referencedFiles = [];

    $addRef = function(?string $url) use (&$referencedFiles, $userId) {
        $url = trim((string)$url);
        if ($url === '') return;
        
        $pattern = "/\/uploads\/profile_media\/user_" . $userId . "\/([a-zA-Z0-9_.-]+)/i";
        if (preg_match($pattern, $url, $matches)) {
            $referencedFiles[strtolower($matches[1])] = true;
        }
    };

    // A. Active profile fields
    $stmt = $mysqli->prepare("SELECT profile_pic, profile_banner, profile_cursor_custom_url, profile_cursor_custom_hover_url FROM utenti WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $userRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($userRow) {
        $addRef($userRow['profile_pic']);
        $addRef($userRow['profile_banner']);
        $addRef($userRow['profile_cursor_custom_url']);
        $addRef($userRow['profile_cursor_custom_hover_url']);
    }

    // B. Custom blocks media
    $stmt = $mysqli->prepare("SELECT media_url FROM utenti_profile_blocks WHERE utente_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $addRef($row['media_url']);
    }
    $stmt->close();

    // C. Links, projects, contents
    $stmt = $mysqli->prepare("SELECT url, icon FROM utenti_links WHERE utente_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $addRef($row['url']);
        $addRef($row['icon']);
    }
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT url, image_url FROM utenti_projects WHERE utente_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $addRef($row['url']);
        $addRef($row['image_url']);
    }
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT url, thumbnail_url FROM utenti_contents WHERE utente_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $addRef($row['url']);
        $addRef($row['thumbnail_url']);
    }
    $stmt->close();

    // D. Presets data (regex scanning JSON data to preserve files referenced in presets)
    $stmt = $mysqli->prepare("SELECT preset_data FROM utenti_presets WHERE utente_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $presetJson = $row['preset_data'];
        if ($presetJson) {
            $pattern = "/\/uploads\/profile_media\/user_" . $userId . "\/([a-zA-Z0-9_.-]+)/i";
            if (preg_match_all($pattern, $presetJson, $matches)) {
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $filename) {
                        $referencedFiles[strtolower($filename)] = true;
                    }
                }
            }
        }
    }
    $stmt->close();

    // Delete unreferenced files
    foreach ($filesOnDisk as $file) {
        $lowerFile = strtolower($file);
        if (!isset($referencedFiles[$lowerFile])) {
            @unlink($uploadDir . '/' . $file);
        }
    }
}
