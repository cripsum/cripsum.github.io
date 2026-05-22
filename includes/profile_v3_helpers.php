<?php
// Cripsum Custom Profiles 3.0 additive helpers.
// Requires profile_helpers.php and an active mysqli connection in callers.

if (!function_exists('profile_v3_table_exists')) {
    function profile_v3_table_exists(mysqli $mysqli, string $table): bool
    {
        static $cache = [];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
        if (array_key_exists($table, $cache)) return $cache[$table];
        $stmt = $mysqli->prepare('SHOW TABLES LIKE ?');
        if (!$stmt) return $cache[$table] = false;
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        return $cache[$table] = $exists;
    }
}

if (!function_exists('profile_v3_column_exists')) {
    function profile_v3_column_exists(mysqli $mysqli, string $table, string $column): bool
    {
        static $column_cache = [];

        // Validate table name to prevent SQL injection since we can't bind table names
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;

        // If we haven't fetched the columns for this table yet, do it once
        if (!isset($column_cache[$table])) {
            $column_cache[$table] = [];
            $result = $mysqli->query("SHOW COLUMNS FROM `$table`");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    // Store column names in lowercase for case-insensitive comparison
                    $column_cache[$table][strtolower($row['Field'])] = true;
                }
                $result->close();
            }
        }

        return isset($column_cache[$table][strtolower($column)]);
    }
}

function profile_v3_defaults(): array
{
    return [
        'display_name_en' => null,
        'bio_en' => null,
        'profile_status_en' => null,
        'profile_locale' => 'it',
        'profile_enter_enabled' => 0,
        'profile_enter_text' => null,
        'profile_enter_text_en' => null,
        'profile_enter_button' => null,
        'profile_enter_button_en' => null,
        'profile_enter_remember' => 1,
        'profile_background_mode' => 'upload',
        'profile_background_config' => null,
        'profile_youtube_url' => null,
        'profile_fallback_image_url' => null,
        'profile_canvas_effect' => 'none',
        'profile_canvas_config' => null,
        'profile_avatar_effect' => 'pfp-glow',
        'profile_avatar_shape' => 'circle',
        'profile_avatar_frame_url' => null,
        'profile_theme_preset' => 'cyber',
        'profile_font_family' => 'inter',
        'profile_noise_enabled' => 1,
        'profile_animations_enabled' => 1,
        'profile_builder_json' => null,
        'profile_plugins_json' => null,
        'profile_presets_json' => null,
    ];
}

function profile_v3_get_profile_extras(mysqli $mysqli, int $userId): array
{
    $defaults = profile_v3_defaults();
    $columns = [];
    foreach (array_keys($defaults) as $column) {
        if (profile_v3_column_exists($mysqli, 'utenti', $column)) {
            $columns[] = '`' . $column . '`';
        }
    }
    if (!$columns) return $defaults;

    $stmt = $mysqli->prepare('SELECT ' . implode(', ', $columns) . ' FROM utenti WHERE id = ? LIMIT 1');
    if (!$stmt) return $defaults;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return array_merge($defaults, $row);
}

function profile_v3_apply_extras(array $profile, mysqli $mysqli): array
{
    if (empty($profile['id'])) return array_merge(profile_v3_defaults(), $profile);
    return array_merge($profile, profile_v3_get_profile_extras($mysqli, (int)$profile['id']));
}

function profile_v3_lang(): string
{
    $requested = strtolower((string)($_GET['lang'] ?? $_SESSION['lang'] ?? $_COOKIE['cripsum_lang'] ?? ''));
    if (in_array($requested, ['it', 'en'], true)) return $requested;
    $path = strtolower(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    return str_starts_with($path, '/en/') ? 'en' : 'it';
}

function profile_v3_t(array $row, string $key, string $lang): string
{
    $primary = trim((string)($row[$key] ?? ''));
    if ($lang === 'en') {
        $translated = trim((string)($row[$key . '_en'] ?? ''));
        if ($translated !== '') return $translated;
    }
    return $primary;
}

function profile_v3_json_array(?string $raw, array $fallback = []): array
{
    $raw = trim((string)$raw);
    if ($raw === '') return $fallback;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function profile_v3_theme_presets(): array
{
    return [
        'cyber' => ['accent' => '#00f5ff', 'secondary' => '#8b5cf6', 'font' => 'space-grotesk'],
        'rose' => ['accent' => '#ff4fa3', 'secondary' => '#ffd1e8', 'font' => 'playfair'],
        'onyx' => ['accent' => '#d7dde8', 'secondary' => '#6b7280', 'font' => 'inter'],
        'toxic' => ['accent' => '#a3ff12', 'secondary' => '#00ffaa', 'font' => 'orbitron'],
        'vaporwave' => ['accent' => '#ff71ce', 'secondary' => '#01cdfe', 'font' => 'space-grotesk'],
        'crimson' => ['accent' => '#ff304f', 'secondary' => '#ffb000', 'font' => 'bebas'],
        'midnight' => ['accent' => '#60a5fa', 'secondary' => '#c084fc', 'font' => 'jetbrains'],
        'sakura' => ['accent' => '#ff9ac7', 'secondary' => '#fdf2f8', 'font' => 'poppins'],
    ];
}

function profile_v3_font_stack(string $key): string
{
    $fonts = [
        'inter' => "Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        'space-grotesk' => "'Space Grotesk', Inter, system-ui, sans-serif",
        'jetbrains' => "'JetBrains Mono', 'SFMono-Regular', Consolas, monospace",
        'vcr' => "'VCR OSD Mono', 'JetBrains Mono', monospace",
        'poppins' => "Poppins, Inter, system-ui, sans-serif",
        'playfair' => "'Playfair Display', Georgia, serif",
        'orbitron' => "Orbitron, Inter, system-ui, sans-serif",
        'bebas' => "'Bebas Neue', Impact, sans-serif",
    ];
    return $fonts[$key] ?? $fonts['inter'];
}

function profile_v3_allowed_font(string $value): string
{
    return profile_allowed_value($value, ['inter', 'space-grotesk', 'jetbrains', 'vcr', 'poppins', 'playfair', 'orbitron', 'bebas'], 'inter');
}

function profile_v3_allowed_preset(string $value): string
{
    return profile_allowed_value($value, array_keys(profile_v3_theme_presets()), 'cyber');
}

function profile_v3_allowed_canvas(string $value): string
{
    return profile_allowed_value($value, ['none', 'snow', 'sparks', 'matrix', 'stars', 'rain', 'orbs', 'fireflies', 'confetti', 'sakura', 'smoke'], 'none');
}

function profile_v3_sanitize_custom_html(string $html): string
{
    $html = mb_substr($html, 0, 4500, 'UTF-8');
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><a><span><code><pre><ul><ol><li><blockquote><hr>');
    $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $html) ?? '';
    $html = preg_replace('/\s+style\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $html) ?? '';
    $html = preg_replace('/(href\s*=\s*["\'])\s*javascript:[^"\']*(["\'])/iu', '$1#$2', $html) ?? '';
    $html = preg_replace('/(href\s*=\s*["\'])\s*data:[^"\']*(["\'])/iu', '$1#$2', $html) ?? '';
    return $html;
}

function profile_v3_clean_url(?string $url, bool $required = false): ?string
{
    $url = trim((string)$url);
    if ($url === '') return $required ? null : '';
    return profile_is_safe_url($url, true) ? $url : null;
}

function profile_v3_youtube_id(?string $url): ?string
{
    $url = trim((string)$url);
    if ($url === '') return null;
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
    $path = trim((string)(parse_url($url, PHP_URL_PATH) ?: ''), '/');
    parse_str((string)(parse_url($url, PHP_URL_QUERY) ?: ''), $query);
    if (str_contains($host, 'youtu.be') && $path !== '') return preg_replace('/[^a-zA-Z0-9_-]/', '', explode('/', $path)[0]);
    if (str_contains($host, 'youtube.com')) {
        if (!empty($query['v'])) return preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$query['v']);
        if (preg_match('~(?:embed|shorts)/([a-zA-Z0-9_-]+)~', $path, $m)) return $m[1];
    }
    return null;
}

function profile_v3_datetime_or_null(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    try {
        $date = new DateTime($value);
        return $date->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function profile_v3_slug(?string $value): string
{
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    $value = trim($value, '-_');
    return mb_substr($value, 0, 48, 'UTF-8');
}

function profile_v3_update_user_columns(mysqli $mysqli, int $userId, array $values): void
{
    $set = [];
    $params = [];
    $types = '';
    foreach ($values as $column => $pair) {
        if (!profile_v3_column_exists($mysqli, 'utenti', $column)) continue;
        $value = is_array($pair) && array_key_exists('value', $pair) ? $pair['value'] : $pair;
        $type = is_array($pair) && !empty($pair['type']) ? (string)$pair['type'] : (is_int($value) ? 'i' : 's');
        $set[] = '`' . $column . '` = ?';
        $params[] = $value;
        $types .= $type === 'i' ? 'i' : 's';
    }
    if (!$set) return;
    $params[] = $userId;
    $types .= 'i';
    $stmt = $mysqli->prepare('UPDATE utenti SET ' . implode(', ', $set) . ' WHERE id = ?');
    if (!$stmt) throw new RuntimeException('Unable to prepare profile v3 update.');
    $refs = [];
    foreach ($params as $index => $value) {
        $refs[$index] = &$params[$index];
    }
    $stmt->bind_param($types, ...$refs);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to save profile v3 settings.');
    }
    $stmt->close();
}

function profile_v3_normalize_canvas_config(?string $raw): string
{
    $data = profile_v3_json_array($raw, []);
    $config = [
        'speed' => min(max((float)($data['speed'] ?? 1), 0.1), 4),
        'density' => min(max((int)($data['density'] ?? 55), 5), 180),
        'color' => profile_normalize_hex_color($data['color'] ?? '#ffffff'),
        'opacity' => min(max((float)($data['opacity'] ?? 0.55), 0.05), 1),
        'fps' => min(max((int)($data['fps'] ?? 40), 18), 60),
    ];
    return json_encode($config, JSON_UNESCAPED_SLASHES);
}

function profile_v3_normalize_background_config(?string $raw): string
{
    $data = profile_v3_json_array($raw, []);
    $colors = $data['colors'] ?? ['#05070d', '#0f5bff', '#8b5cf6'];
    if (!is_array($colors)) $colors = [];
    $colors = array_slice(array_map(fn($c) => profile_normalize_hex_color((string)$c), $colors), 0, 3);
    while (count($colors) < 2) $colors[] = '#0f5bff';
    $config = [
        'colors' => $colors,
        'direction' => profile_allowed_value((string)($data['direction'] ?? '135deg'), ['90deg', '120deg', '135deg', '160deg', '180deg', 'circle'], '135deg'),
        'animated' => !empty($data['animated']) ? 1 : 0,
        'image_url' => profile_v3_clean_url($data['image_url'] ?? '', false) ?: '',
        'video_url' => profile_v3_clean_url($data['video_url'] ?? '', false) ?: '',
        'blur' => min(max((int)($data['blur'] ?? 0), 0), 20),
        'parallax' => !empty($data['parallax']) ? 1 : 0,
    ];
    return json_encode($config, JSON_UNESCAPED_SLASHES);
}

function profile_v3_normalize_plugins(?string $raw): string
{
    $data = profile_v3_json_array($raw, []);
    $plugins = [];
    foreach (array_slice($data, 0, 24) as $plugin) {
        if (!is_array($plugin)) continue;
        $plugins[] = [
            'id' => profile_v3_slug($plugin['id'] ?? ''),
            'enabled' => !empty($plugin['enabled']) ? 1 : 0,
            'config' => is_array($plugin['config'] ?? null) ? array_slice($plugin['config'], 0, 30, true) : [],
        ];
    }
    return json_encode($plugins, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function profile_v3_normalize_builder_input(?string $raw): string
{
    $data = profile_v3_json_array($raw, ['version' => 3, 'blocks' => []]);
    $allowed = ['bio', 'social', 'link', 'projects', 'gallery', 'video', 'audio', 'spotify', 'youtube', 'twitch', 'github', 'countdown', 'quote', 'table', 'contact', 'achievement', 'lootbox', 'custom_html'];
    $blocks = [];
    foreach (array_slice(($data['blocks'] ?? []), 0, 40) as $index => $block) {
        if (!is_array($block)) continue;
        $type = profile_allowed_value((string)($block['type'] ?? 'bio'), $allowed, 'bio');
        $blockData = is_array($block['data'] ?? null) ? $block['data'] : [];
        if ($type === 'custom_html') {
            $blockData['html'] = profile_v3_sanitize_custom_html((string)($blockData['html'] ?? ''));
        }
        $blocks[] = [
            'id' => profile_v3_slug($block['id'] ?? ('block-' . ($index + 1))) ?: 'block-' . ($index + 1),
            'type' => $type,
            'title' => profile_clean_text($block['title'] ?? '', 90),
            'title_en' => profile_clean_text($block['title_en'] ?? '', 90),
            'hidden' => !empty($block['hidden']) ? 1 : 0,
            'collapsed' => !empty($block['collapsed']) ? 1 : 0,
            'style' => is_array($block['style'] ?? null) ? array_slice($block['style'], 0, 16, true) : [],
            'data' => array_slice($blockData, 0, 60, true),
        ];
    }
    return json_encode(['version' => 3, 'blocks' => $blocks], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function profile_v3_get_builder(array $profile): array
{
    return profile_v3_json_array($profile['profile_builder_json'] ?? '', ['version' => 3, 'blocks' => []]);
}

function profile_v3_render_background(array $profile, ?string $backgroundUrl, string $backgroundType): void
{
    $mode = profile_allowed_value((string)($profile['profile_background_mode'] ?? 'upload'), ['upload', 'image', 'video', 'youtube', 'gradient'], 'upload');
    $config = profile_v3_json_array($profile['profile_background_config'] ?? '', []);
    $fallbackImage = trim((string)($profile['profile_fallback_image_url'] ?? ''));
    $defaultVideo = '/vid/Shorekeeper Wallpaper 4K Loop.mp4';
    $backgroundUrl = $backgroundUrl ?: $defaultVideo;
    $backgroundType = $backgroundUrl === $defaultVideo ? 'video/mp4' : $backgroundType;
    $blur = min(max((int)($config['blur'] ?? 0), 0), 20);
    $parallax = !empty($config['parallax']);
    $gradientColors = array_slice($config['colors'] ?? ['#05070d', '#0f5bff', '#8b5cf6'], 0, 3);
    $direction = (string)($config['direction'] ?? '135deg');
    $gradient = $direction === 'circle'
        ? 'radial-gradient(circle at 50% 20%, ' . implode(', ', array_map('profile_h', $gradientColors)) . ')'
        : 'linear-gradient(' . profile_h($direction) . ', ' . implode(', ', array_map('profile_h', $gradientColors)) . ')';
    $youtubeId = profile_v3_youtube_id($profile['profile_youtube_url'] ?? null);
    $imageUrl = trim((string)($config['image_url'] ?? ''));
    $videoUrl = trim((string)($config['video_url'] ?? ''));
?>
    <div class="bio-background bio-background-v3 mode-<?php echo profile_h($mode); ?> <?php echo !empty($config['animated']) ? 'is-animated-gradient' : ''; ?>" style="--bg-blur: <?php echo (int)$blur; ?>px; --profile-gradient-bg: <?php echo $gradient; ?>;" data-parallax="<?php echo $parallax ? '1' : '0'; ?>" aria-hidden="true">
        <?php if ($mode === 'youtube' && $youtubeId): ?>
            <iframe class="bio-background__media bio-background__youtube" src="https://www.youtube-nocookie.com/embed/<?php echo profile_h($youtubeId); ?>?autoplay=1&mute=1&controls=0&loop=1&playlist=<?php echo profile_h($youtubeId); ?>&modestbranding=1&playsinline=1&rel=0" title="" tabindex="-1" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture"></iframe>
            <?php if ($fallbackImage && profile_is_safe_url($fallbackImage, false)): ?><img class="bio-background__fallback" src="<?php echo profile_h($fallbackImage); ?>" alt="" loading="eager"><?php endif; ?>
        <?php elseif ($mode === 'gradient'): ?>
            <div class="bio-background__gradient"></div>
        <?php elseif ($mode === 'image' && $imageUrl && profile_is_safe_url($imageUrl, false)): ?>
            <img class="bio-background__media" src="<?php echo profile_h($imageUrl); ?>" alt="" loading="eager" decoding="async">
        <?php elseif ($mode === 'video' && $videoUrl && profile_is_safe_url($videoUrl, false)): ?>
            <video class="bio-background__media" autoplay muted loop playsinline preload="metadata" poster="<?php echo profile_h($fallbackImage); ?>">
                <source src="<?php echo profile_h($videoUrl); ?>" type="video/mp4">
            </video>
        <?php elseif (str_starts_with($backgroundType, 'video/')): ?>
            <video class="bio-background__media" autoplay muted loop playsinline preload="metadata" poster="<?php echo profile_h($fallbackImage); ?>">
                <source src="<?php echo profile_h($backgroundUrl); ?>" type="<?php echo profile_h($backgroundType); ?>">
            </video>
        <?php elseif (str_starts_with($backgroundType, 'image/')): ?>
            <img class="bio-background__media" src="<?php echo profile_h($backgroundUrl); ?>" alt="" loading="eager" decoding="async">
        <?php else: ?>
            <video class="bio-background__media" autoplay muted loop playsinline preload="metadata">
                <source src="<?php echo profile_h($defaultVideo); ?>" type="video/mp4">
            </video>
        <?php endif; ?>
        <div class="bio-background__overlay"></div>
        <?php if (!empty($profile['profile_noise_enabled'])): ?><div class="profile-noise-layer"></div><?php endif; ?>
        <div class="bio-orb bio-orb--one"></div>
        <div class="bio-orb bio-orb--two"></div>
        <div class="bio-grid-glow"></div>
    </div>
<?php
}

function profile_v3_link_redirect_url(array $link): string
{
    $slug = profile_v3_slug($link['short_slug'] ?? '');
    if ($slug !== '') return '/go/' . rawurlencode($slug);
    return (string)($link['url'] ?? '#');
}

function profile_v3_enrich_links(mysqli $mysqli, int $userId, array $links): array
{
    if (!$links || !profile_v3_column_exists($mysqli, 'utenti_links', 'short_slug')) return $links;
    $stmt = $mysqli->prepare('SELECT id, custom_icon_url, thumbnail_url, short_slug, schedule_starts_at, schedule_ends_at, is_hidden, is_separator, separator_title, click_count FROM utenti_links WHERE utente_id = ?');
    if (!$stmt) return $links;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $extraRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();
    $extras = [];
    foreach ($extraRows as $row) $extras[(int)$row['id']] = $row;
    $now = time();
    $merged = [];
    foreach ($links as $link) {
        $id = (int)($link['id'] ?? 0);
        if (isset($extras[$id])) $link = array_merge($link, $extras[$id]);
        if (!empty($link['is_hidden'])) continue;
        $start = !empty($link['schedule_starts_at']) ? strtotime((string)$link['schedule_starts_at']) : null;
        $end = !empty($link['schedule_ends_at']) ? strtotime((string)$link['schedule_ends_at']) : null;
        if ($start && $start > $now) continue;
        if ($end && $end < $now) continue;
        $merged[] = $link;
    }
    return $merged;
}

function profile_v3_update_link_extras(mysqli $mysqli, int $userId, int $linkId, array $row, string $url): void
{
    $slugValue = profile_v3_slug($row['short_slug'] ?? '');
    $values = [
        'custom_icon_url' => profile_v3_clean_url($row['custom_icon_url'] ?? '', false) ?: null,
        'thumbnail_url' => profile_v3_clean_url($row['thumbnail_url'] ?? '', false) ?: null,
        'short_slug' => $slugValue !== '' ? $slugValue : null,
        'schedule_starts_at' => profile_v3_datetime_or_null($row['schedule_starts_at'] ?? null),
        'schedule_ends_at' => profile_v3_datetime_or_null($row['schedule_ends_at'] ?? null),
        'is_hidden' => !empty($row['is_hidden']) ? 1 : 0,
        'is_separator' => !empty($row['is_separator']) ? 1 : 0,
        'separator_title' => profile_clean_text($row['separator_title'] ?? '', 70) ?: null,
    ];
    $set = [];
    $params = [];
    $types = '';
    foreach ($values as $column => $value) {
        if (!profile_v3_column_exists($mysqli, 'utenti_links', $column)) continue;
        $set[] = '`' . $column . '` = ?';
        $params[] = $value;
        $types .= is_int($value) ? 'i' : 's';
    }
    if ($set) {
        $params[] = $linkId;
        $types .= 'i';
        $stmt = $mysqli->prepare('UPDATE utenti_links SET ' . implode(', ', $set) . ' WHERE id = ?');
        if ($stmt) {
            $refs = [];
            foreach ($params as $i => $value) $refs[$i] = &$params[$i];
            $stmt->bind_param($types, ...$refs);
            $stmt->execute();
            $stmt->close();
        }
    }
    $slug = $slugValue;
    if ($slug !== '' && profile_v3_table_exists($mysqli, 'profile_short_links')) {
        $stmt = $mysqli->prepare('INSERT INTO profile_short_links (slug, utente_id, link_id, target_url, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE utente_id = VALUES(utente_id), link_id = VALUES(link_id), target_url = VALUES(target_url), is_active = 1, updated_at = NOW()');
        if ($stmt) {
            $stmt->bind_param('siis', $slug, $userId, $linkId, $url);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function profile_v3_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        $value = trim((string)($_SERVER[$key] ?? ''));
        if ($value === '') continue;
        $ip = trim(explode(',', $value)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '0.0.0.0';
}

function profile_v3_device_type(): string
{
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) return 'tablet';
    if (str_contains($ua, 'mobi') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) return 'mobile';
    return 'desktop';
}

function profile_v3_rate_limit(mysqli $mysqli, string $key, int $max, int $seconds, ?int $userId = null): bool
{
    $bucket = sha1(profile_v3_client_ip() . '|' . ($userId ?: 0) . '|' . $key);
    if (!profile_v3_table_exists($mysqli, 'profile_rate_limits')) {
        $_SESSION['profile_rate_limits'] ??= [];
        $now = time();
        $_SESSION['profile_rate_limits'][$bucket] = array_values(array_filter($_SESSION['profile_rate_limits'][$bucket] ?? [], fn($t) => ($now - (int)$t) < $seconds));
        if (count($_SESSION['profile_rate_limits'][$bucket]) >= $max) return false;
        $_SESSION['profile_rate_limits'][$bucket][] = $now;
        return true;
    }
    $windowStart = date('Y-m-d H:i:s', time() - $seconds);
    $stmt = $mysqli->prepare('DELETE FROM profile_rate_limits WHERE created_at < ?');
    if ($stmt) {
        $stmt->bind_param('s', $windowStart);
        $stmt->execute();
        $stmt->close();
    }
    $stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM profile_rate_limits WHERE rate_key = ? AND created_at >= ?');
    if (!$stmt) return true;
    $stmt->bind_param('ss', $bucket, $windowStart);
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    if ($total >= $max) return false;
    $stmt = $mysqli->prepare('INSERT INTO profile_rate_limits (rate_key, user_id, ip_address, created_at) VALUES (?, ?, ?, NOW())');
    if ($stmt) {
        $ip = profile_v3_client_ip();
        $uid = $userId ?: null;
        $stmt->bind_param('sis', $bucket, $uid, $ip);
        $stmt->execute();
        $stmt->close();
    }
    return true;
}

function profile_v3_track_event(mysqli $mysqli, int $profileId, string $eventType, ?int $linkId = null, ?string $meta = null): void
{
    if (!profile_v3_table_exists($mysqli, 'profile_analytics_events')) return;
    $eventType = profile_allowed_value($eventType, ['view', 'click', 'reaction', 'contact', 'share', 'qr'], 'view');
    $viewerId = profile_current_user_id();
    if ($viewerId === $profileId && $eventType === 'view') return;
    $ipHash = hash('sha256', profile_v3_client_ip() . '|cripsum-profile-v3');
    $userAgent = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8');
    $referrer = mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255, 'UTF-8');
    $device = profile_v3_device_type();
    $stmt = $mysqli->prepare('INSERT INTO profile_analytics_events (profile_id, viewer_id, event_type, link_id, referrer, device_type, ip_hash, user_agent, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt) return;
    $stmt->bind_param('iisisssss', $profileId, $viewerId, $eventType, $linkId, $referrer, $device, $ipHash, $userAgent, $meta);
    $stmt->execute();
    $stmt->close();
}

function profile_v3_record_view(mysqli $mysqli, int $profileId): void
{
    $_SESSION['profile_v3_viewed'] ??= [];
    $key = 'p' . $profileId;
    if (!empty($_SESSION['profile_v3_viewed'][$key])) return;
    $_SESSION['profile_v3_viewed'][$key] = time();
    profile_v3_track_event($mysqli, $profileId, 'view');
}

function profile_v3_rotate_csrf_token(): string
{
    $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['profile_csrf_token'];
}

function profile_v3_admin_log(mysqli $mysqli, int $adminId, int $targetId, string $action, string $details = ''): void
{
    if (!profile_v3_table_exists($mysqli, 'admin_logs')) return;
    $stmt = $mysqli->prepare('INSERT INTO admin_logs (admin_id, target_user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    if (!$stmt) return;
    $ip = profile_v3_client_ip();
    $stmt->bind_param('iisss', $adminId, $targetId, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

function profile_v3_completion_percent(array $profile, array $links, array $socials, array $blocks): int
{
    $score = 0;
    $score += !empty($profile['display_name']) ? 12 : 0;
    $score += !empty($profile['bio']) ? 14 : 0;
    $score += !empty($profile['profile_pic_type']) || !empty($profile['discord_use_avatar']) ? 12 : 0;
    $score += !empty($profile['profile_banner_type']) || ($profile['profile_background_mode'] ?? '') !== 'upload' ? 10 : 0;
    $score += count($links) > 0 ? 14 : 0;
    $score += count($socials) > 0 ? 10 : 0;
    $score += count($blocks) > 0 ? 12 : 0;
    $score += !empty($profile['profile_canvas_effect']) && $profile['profile_canvas_effect'] !== 'none' ? 8 : 0;
    $score += !empty($profile['profile_enter_enabled']) ? 8 : 0;
    return min(100, $score);
}

function profile_v3_list_custom_badges(mysqli $mysqli, int $userId): array
{
    if (!profile_v3_table_exists($mysqli, 'custom_badges') || !profile_v3_table_exists($mysqli, 'user_custom_badges')) return [];
    $stmt = $mysqli->prepare("
        SELECT cb.id, cb.name AS nome, cb.name_en AS nome_en, cb.tooltip AS descrizione, cb.tooltip_en AS descrizione_en,
               cb.icon, cb.image_url AS custom_image_url, cb.color, cb.glow, cb.animation, cb.badge_type, ucb.sort_order
        FROM user_custom_badges ucb
        INNER JOIN custom_badges cb ON cb.id = ucb.badge_id
        WHERE ucb.utente_id = ? AND ucb.is_visible = 1
        ORDER BY ucb.sort_order ASC, ucb.id ASC
        LIMIT 8
    ");
    if (!$stmt) return [];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();
    foreach ($rows as &$row) {
        $row['is_custom_badge'] = 1;
        $row['punti'] = 100;
    }
    return $rows;
}

function profile_v3_render_builder(array $builder, string $lang = 'it'): void
{
    $blocks = is_array($builder['blocks'] ?? null) ? $builder['blocks'] : [];
    if (!$blocks) return;
?>
    <section class="bio-card profile-builder-section js-reveal" aria-label="Profile modules">
        <div class="profile-builder-stack">
            <?php foreach ($blocks as $block): ?>
                <?php
                if (!is_array($block) || !empty($block['hidden'])) continue;
                $type = (string)($block['type'] ?? 'bio');
                $title = $lang === 'en' && trim((string)($block['title_en'] ?? '')) !== '' ? (string)$block['title_en'] : (string)($block['title'] ?? '');
                $data = is_array($block['data'] ?? null) ? $block['data'] : [];
                $collapsed = !empty($block['collapsed']);
                ?>
                <article class="profile-builder-block block-<?php echo profile_h($type); ?> <?php echo $collapsed ? 'is-collapsed' : ''; ?>" data-block-type="<?php echo profile_h($type); ?>">
                    <?php if ($title !== ''): ?><h2><?php echo profile_h($title); ?></h2><?php endif; ?>
                    <?php if ($type === 'bio'): ?>
                        <p><?php echo nl2br(profile_h((string)($data['text'] ?? ''))); ?></p>
                    <?php elseif (in_array($type, ['social', 'link', 'projects'], true)): ?>
                        <div class="profile-builder-link-list">
                            <?php foreach (array_slice(($data['items'] ?? []), 0, 12) as $item): ?>
                                <?php
                                if (!is_array($item)) continue;
                                $url = profile_v3_clean_url($item['url'] ?? '', false);
                                $label = profile_clean_text($item['label'] ?? $item['title'] ?? '', 80);
                                if (!$url || $label === '') continue;
                                ?>
                                <a href="<?php echo profile_h($url); ?>" target="_blank" rel="noopener noreferrer">
                                    <span><?php echo profile_h($label); ?></span>
                                    <i class="fas fa-arrow-up-right-from-square"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($type === 'quote'): ?>
                        <blockquote><?php echo profile_h((string)($data['quote'] ?? '')); ?><?php if (!empty($data['author'])): ?><cite><?php echo profile_h((string)$data['author']); ?></cite><?php endif; ?></blockquote>
                    <?php elseif ($type === 'gallery'): ?>
                        <div class="profile-gallery-grid">
                            <?php foreach (array_slice(($data['items'] ?? []), 0, 12) as $item): ?>
                                <?php $src = is_array($item) ? profile_v3_clean_url($item['url'] ?? '', false) : null; ?>
                                <?php if ($src): ?><img src="<?php echo profile_h($src); ?>" alt="<?php echo profile_h((string)($item['alt'] ?? '')); ?>" loading="lazy" decoding="async"><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($type === 'audio'): ?>
                        <?php $audioUrl = profile_v3_clean_url($data['url'] ?? '', false); ?>
                        <?php if ($audioUrl): ?><audio class="profile-builder-audio" src="<?php echo profile_h($audioUrl); ?>" controls preload="metadata"></audio><?php endif; ?>
                    <?php elseif (in_array($type, ['video', 'youtube', 'twitch', 'spotify'], true)): ?>
                        <?php echo profile_v3_render_embed($type, $data); ?>
                    <?php elseif ($type === 'github'): ?>
                        <?php $gh = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['username'] ?? '')); ?>
                        <?php if ($gh): ?><a class="profile-github-widget" href="https://github.com/<?php echo profile_h($gh); ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-github"></i><strong>@<?php echo profile_h($gh); ?></strong><span>GitHub</span></a><?php endif; ?>
                    <?php elseif ($type === 'countdown'): ?>
                        <div class="profile-countdown" data-countdown="<?php echo profile_h((string)($data['datetime'] ?? '')); ?>"><strong>--</strong><span><?php echo profile_h((string)($data['label'] ?? '')); ?></span></div>
                    <?php elseif ($type === 'table'): ?>
                        <div class="profile-mini-table" role="table">
                            <?php foreach (array_slice(($data['rows'] ?? []), 0, 12) as $row): ?>
                                <?php if (!is_array($row)) continue; ?><div role="row"><span><?php echo profile_h((string)($row['label'] ?? '')); ?></span><strong><?php echo profile_h((string)($row['value'] ?? '')); ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($type === 'contact'): ?>
                        <form class="profile-contact-form" data-profile-contact>
                            <input type="text" name="name" maxlength="70" autocomplete="name" placeholder="Name" required>
                            <input type="email" name="email" maxlength="120" autocomplete="email" placeholder="Email" required>
                            <textarea name="message" maxlength="800" rows="4" placeholder="Message" required></textarea>
                            <button type="submit" class="bio-button bio-button--primary"><i class="fas fa-paper-plane"></i>Send</button>
                        </form>
                    <?php elseif ($type === 'custom_html'): ?>
                        <div class="profile-custom-html"><?php echo profile_v3_sanitize_custom_html((string)($data['html'] ?? '')); ?></div>
                    <?php elseif (in_array($type, ['achievement', 'lootbox'], true)): ?>
                        <div class="profile-showcase-chip">
                            <i class="<?php echo $type === 'achievement' ? 'fas fa-trophy' : 'fas fa-dice-d20'; ?>"></i>
                            <strong><?php echo profile_h((string)($data['label'] ?? $data['text'] ?? ucfirst($type))); ?></strong>
                            <?php if (!empty($data['description'])): ?><span><?php echo profile_h((string)$data['description']); ?></span><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p><?php echo nl2br(profile_h((string)($data['text'] ?? $data['description'] ?? ''))); ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php
}

function profile_v3_render_embed(string $type, array $data): string
{
    $url = trim((string)($data['url'] ?? ''));
    if ($url === '') return '';
    if ($type === 'youtube') {
        $id = profile_v3_youtube_id($url);
        if (!$id) return '';
        return '<div class="profile-embed"><iframe loading="lazy" src="https://www.youtube-nocookie.com/embed/' . profile_h($id) . '?rel=0" allowfullscreen title="YouTube"></iframe></div>';
    }
    if ($type === 'spotify' && preg_match('~open\.spotify\.com/(track|album|playlist|artist)/([a-zA-Z0-9]+)~', $url, $m)) {
        return '<div class="profile-embed profile-embed-spotify"><iframe loading="lazy" src="https://open.spotify.com/embed/' . profile_h($m[1]) . '/' . profile_h($m[2]) . '" allow="encrypted-media" title="Spotify"></iframe></div>';
    }
    if ($type === 'twitch') {
        $channel = preg_replace('/[^a-zA-Z0-9_]/', '', basename(parse_url($url, PHP_URL_PATH) ?: $url));
        if (!$channel) return '';
        $parent = profile_h((string)($_SERVER['HTTP_HOST'] ?? 'cripsum.com'));
        return '<div class="profile-embed"><iframe loading="lazy" src="https://player.twitch.tv/?channel=' . profile_h($channel) . '&parent=' . $parent . '&muted=true" allowfullscreen title="Twitch"></iframe></div>';
    }
    if ($type === 'video' && profile_is_safe_url($url, false)) {
        return '<video class="profile-native-video" src="' . profile_h($url) . '" controls playsinline preload="metadata"></video>';
    }
    return '';
}
