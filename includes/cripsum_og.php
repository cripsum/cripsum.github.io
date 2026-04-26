<?php
if (!defined('CRIPSUM_OG_LOADED')) {
    define('CRIPSUM_OG_LOADED', true);
}

function cripsum_og_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cripsum_og_site_url(): string
{
    return 'https://cripsum.com';
}

function cripsum_og_abs(?string $url): string
{
    $url = trim((string)$url);
    if ($url === '') {
        return cripsum_og_site_url() . '/img/og-default.jpg';
    }

    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }

    if (str_starts_with($url, '/')) {
        return cripsum_og_site_url() . $url;
    }

    return cripsum_og_site_url() . '/' . ltrim($url, '/');
}

function cripsum_og_current_url(): string
{
    $uri = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '#');
    return cripsum_og_site_url() . $uri;
}

function cripsum_og_trim(string $text, int $max = 180): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($text === '') return '';

    if (mb_strlen($text, 'UTF-8') <= $max) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $max - 1, 'UTF-8')) . '…';
}

function cripsum_og_default(string $kind = 'site'): array
{
    $titles = [
        'shitpost' => 'Shitpost - Cripsum™',
        'rimasto' => 'Top Rimasti - Cripsum™',
        'profile' => 'Profilo - Cripsum™',
        'site' => 'Cripsum™',
    ];

    $descriptions = [
        'shitpost' => 'Meme, GIF e post della community.',
        'rimasto' => 'I post più votati dalla community.',
        'profile' => 'Profilo pubblico su Cripsum™.',
        'site' => 'Cripsum™',
    ];

    return [
        'title' => $titles[$kind] ?? $titles['site'],
        'description' => $descriptions[$kind] ?? $descriptions['site'],
        'image' => cripsum_og_abs('/img/og-default.jpg'),
        'url' => cripsum_og_current_url(),
        'type' => 'website',
        'image_type' => 'image/jpeg',
        'image_width' => '1200',
        'image_height' => '630',
    ];
}

function cripsum_og_table_exists(mysqli $mysqli, string $table): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;

    try {
        $stmt = $mysqli->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND BINARY TABLE_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) return false;

        $stmt->bind_param('s', $table);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    } catch (Throwable $e) {
        return false;
    }
}

function cripsum_og_column_exists(mysqli $mysqli, string $table, string $column): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[\p{L}\p{N}_]+$/u', $column)) {
        return false;
    }

    try {
        $stmt = $mysqli->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND BINARY COLUMN_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) return false;

        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    } catch (Throwable $e) {
        return false;
    }
}

function cripsum_og_content(mysqli $mysqli, string $type): array
{
    $type = in_array($type, ['rimasto', 'toprimasti', 'rimasti'], true) ? 'rimasto' : 'shitpost';
    $postId = isset($_GET['post']) ? (int)$_GET['post'] : 0;

    $default = cripsum_og_default($type === 'rimasto' ? 'rimasto' : 'shitpost');

    if ($postId <= 0) {
        return $default;
    }

    if ($type === 'rimasto') {
        if (!cripsum_og_table_exists($mysqli, 'toprimasti')) return $default;

        $stmt = $mysqli->prepare("
            SELECT
                t.id,
                t.titolo,
                t.descrizione,
                t.motivazione,
                t.tipo_foto_rimasto AS media_mime,
                t.approvato,
                u.username
            FROM toprimasti t
            LEFT JOIN utenti u ON u.id = t.id_utente
            WHERE t.id = ?
            LIMIT 1
        ");
    } else {
        if (!cripsum_og_table_exists($mysqli, 'shitposts')) return $default;

        $stmt = $mysqli->prepare("
            SELECT
                s.id,
                s.titolo,
                s.descrizione,
                NULL AS motivazione,
                s.tipo_foto_shitpost AS media_mime,
                s.approvato,
                u.username
            FROM shitposts s
            LEFT JOIN utenti u ON u.id = s.id_utente
            WHERE s.id = ?
            LIMIT 1
        ");
    }

    if (!$stmt) return $default;

    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$post || (int)($post['approvato'] ?? 0) !== 1) {
        return $default;
    }

    $title = trim((string)($post['titolo'] ?? ''));
    $description = trim((string)($post['descrizione'] ?? ''));

    if ($description === '' && $type === 'rimasto') {
        $description = trim((string)($post['motivazione'] ?? ''));
    }

    if ($description === '') {
        $description = $default['description'];
    }

    $username = trim((string)($post['username'] ?? ''));
    $suffix = $type === 'rimasto' ? 'Top Rimasti' : 'Shitpost';

    $mime = (string)($post['media_mime'] ?? '');
    $isVideo = str_starts_with($mime, 'video/');

    $image = $isVideo
        ? cripsum_og_abs('/img/og-default.jpg')
        : cripsum_og_abs('/api/content/media.php?type=' . $type . '&id=' . $postId);

    return [
        'title' => ($title !== '' ? $title : $suffix) . ' - Cripsum™',
        'description' => cripsum_og_trim(($username !== '' ? '@' . $username . ' · ' : '') . $description, 180),
        'image' => $image,
        'url' => cripsum_og_current_url(),
        'type' => 'article',
        'image_type' => $isVideo ? 'image/jpeg' : ($mime ?: 'image/jpeg'),
        'image_width' => '1200',
        'image_height' => '630',
    ];
}

function cripsum_og_profile(mysqli $mysqli, ?array $profile = null, ?string $identifier = null): array
{
    $default = cripsum_og_default('profile');

    if (!$profile && $identifier) {
        if (!cripsum_og_table_exists($mysqli, 'utenti')) return $default;

        $hasDisplayName = cripsum_og_column_exists($mysqli, 'utenti', 'display_name');
        $hasBio = cripsum_og_column_exists($mysqli, 'utenti', 'bio');
        $hasVisibility = cripsum_og_column_exists($mysqli, 'utenti', 'profile_visibility');

        $select = 'id, username';
        $select .= $hasDisplayName ? ', display_name' : ", NULL AS display_name";
        $select .= $hasBio ? ', bio' : ", NULL AS bio";
        $select .= $hasVisibility ? ', profile_visibility' : ", 'public' AS profile_visibility";

        $stmt = $mysqli->prepare("SELECT $select FROM utenti WHERE username = ? LIMIT 1");
        if (!$stmt) return $default;

        $stmt->bind_param('s', $identifier);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }

    if (!$profile) return $default;

    $visibility = (string)($profile['profile_visibility'] ?? 'public');
    if ($visibility === 'private' || $visibility === 'logged_in') {
        return [
            'title' => 'Profilo privato - Cripsum™',
            'description' => 'Questo profilo non è pubblico.',
            'image' => cripsum_og_abs('/img/og-default.jpg'),
            'url' => cripsum_og_current_url(),
            'type' => 'profile',
            'image_type' => 'image/jpeg',
            'image_width' => '1200',
            'image_height' => '630',
        ];
    }

    $id = (int)($profile['id'] ?? 0);
    $username = trim((string)($profile['username'] ?? ''));
    $displayName = trim((string)($profile['display_name'] ?? ''));

    if ($displayName === '') {
        $displayName = $username !== '' ? $username : 'Profilo';
    }

    $bio = trim((string)($profile['bio'] ?? ''));
    if ($bio === '') {
        $bio = $username !== '' ? '@' . $username . ' su Cripsum™' : 'Profilo pubblico su Cripsum™.';
    }

    $url = $username !== ''
        ? cripsum_og_abs('/u/' . rawurlencode(strtolower($username)))
        : cripsum_og_current_url();

    return [
        'title' => $displayName . ' - Cripsum™',
        'description' => cripsum_og_trim($bio, 180),
        'image' => $id > 0 ? cripsum_og_abs('/includes/get_pfp.php?id=' . $id) : cripsum_og_abs('/img/og-default.jpg'),
        'url' => $url,
        'type' => 'profile',
        'image_type' => 'image/jpeg',
        'image_width' => '1200',
        'image_height' => '630',
    ];
}

function cripsum_og_print(array $og): void
{
    $og = array_merge(cripsum_og_default(), $og);

    $title = cripsum_og_h($og['title']);
    $description = cripsum_og_h($og['description']);
    $image = cripsum_og_h(cripsum_og_abs($og['image']));
    $url = cripsum_og_h($og['url']);
    $type = cripsum_og_h($og['type']);
    $imageType = cripsum_og_h($og['image_type'] ?? 'image/jpeg');
    $imageWidth = cripsum_og_h($og['image_width'] ?? '1200');
    $imageHeight = cripsum_og_h($og['image_height'] ?? '630');

    echo "\n";
    echo '    <meta name="description" content="' . $description . '">' . "\n";
    echo '    <meta property="og:site_name" content="Cripsum™">' . "\n";
    echo '    <meta property="og:type" content="' . $type . '">' . "\n";
    echo '    <meta property="og:title" content="' . $title . '">' . "\n";
    echo '    <meta property="og:description" content="' . $description . '">' . "\n";
    echo '    <meta property="og:url" content="' . $url . '">' . "\n";
    echo '    <meta property="og:image" content="' . $image . '">' . "\n";
    echo '    <meta property="og:image:secure_url" content="' . $image . '">' . "\n";
    echo '    <meta property="og:image:type" content="' . $imageType . '">' . "\n";
    echo '    <meta property="og:image:width" content="' . $imageWidth . '">' . "\n";
    echo '    <meta property="og:image:height" content="' . $imageHeight . '">' . "\n";
    echo '    <meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '    <meta name="twitter:title" content="' . $title . '">' . "\n";
    echo '    <meta name="twitter:description" content="' . $description . '">' . "\n";
    echo '    <meta name="twitter:image" content="' . $image . '">' . "\n";
}
