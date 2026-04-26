<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

mysqli_report(MYSQLI_REPORT_OFF);

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

$isLoggedIn = function_exists('isLoggedIn') && isLoggedIn();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentUsername = trim((string)($_SESSION['username'] ?? ''));

function home_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function home_abs_url(string $path): string
{
    if (preg_match('/^https?:\/\//i', $path)) return $path;
    return 'https://cripsum.com/' . ltrim($path, '/');
}

function home_table_exists(mysqli $mysqli, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return $cache[$table] = false;

    $stmt = $mysqli->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND BINARY TABLE_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) return $cache[$table] = false;

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $cache[$table] = $exists;
}

function home_columns(mysqli $mysqli, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return $cache[$table] = [];

    $result = $mysqli->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
    if (!$result) return $cache[$table] = [];

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = (string)($row['Field'] ?? '');
    }

    return $cache[$table] = array_filter($columns);
}

function home_normalize_col(string $name): string
{
    $name = mb_strtolower($name, 'UTF-8');
    return strtr($name, [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
    ]);
}

function home_first_col(mysqli $mysqli, string $table, array $candidates): ?string
{
    $columns = home_columns($mysqli, $table);
    if (!$columns) return null;

    foreach ($candidates as $candidate) {
        foreach ($columns as $real) {
            if ($real === $candidate) return $real;
        }
    }

    foreach ($candidates as $candidate) {
        $candidateNorm = home_normalize_col($candidate);
        foreach ($columns as $real) {
            if (home_normalize_col($real) === $candidateNorm) return $real;
        }
    }

    return null;
}

function home_qcol(string $column): string
{
    return '`' . str_replace('`', '``', $column) . '`';
}

function home_count(mysqli $mysqli, string $sql, string $types = '', array $params = []): int
{
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return 0;

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['total'] ?? 0);
}

function home_compact_number(int $number): string
{
    if ($number >= 1000000) return str_replace('.0', '', number_format($number / 1000000, 1)) . 'M';
    if ($number >= 1000) return str_replace('.0', '', number_format($number / 1000, 1)) . 'K';
    return (string)$number;
}

function home_time_ago(?string $datetime): string
{
    if (!$datetime) return '';
    $time = strtotime($datetime);
    if (!$time) return '';

    $diff = time() - $time;
    if ($diff < 60) return 'ora';
    if ($diff < 3600) return floor($diff / 60) . ' min fa';
    if ($diff < 86400) return floor($diff / 3600) . ' ore fa';
    if ($diff < 604800) return floor($diff / 86400) . ' giorni fa';

    return date('d/m/Y', $time);
}

function home_fetch_latest_shitposts(mysqli $mysqli, int $limit = 4): array
{
    if (!home_table_exists($mysqli, 'shitposts')) return [];

    $hasViews = in_array('views', home_columns($mysqli, 'shitposts'), true);
    $hasTag = in_array('tag', home_columns($mysqli, 'shitposts'), true);
    $hasLikes = home_table_exists($mysqli, 'shitpost_likes');
    $hasComments = home_table_exists($mysqli, 'commenti_shitpost');

    $viewsSql = $hasViews ? 'COALESCE(s.`views`, 0)' : '0';
    $tagSql = $hasTag ? 's.`tag`' : 'NULL';
    $likesSql = $hasLikes ? '(SELECT COUNT(*) FROM shitpost_likes l WHERE l.id_shitpost = s.id)' : '0';
    $commentsSql = $hasComments ? '(SELECT COUNT(*) FROM commenti_shitpost c WHERE c.id_shitpost = s.id)' : '0';

    $sql = "
        SELECT
            s.id,
            s.id_utente,
            s.titolo,
            s.descrizione,
            s.tipo_foto_shitpost,
            s.data_creazione,
            CASE WHEN s.foto_shitpost IS NULL THEN 0 ELSE 1 END AS has_media,
            $viewsSql AS views,
            $tagSql AS tag,
            $likesSql AS score,
            $commentsSql AS comments_count,
            u.username
        FROM shitposts s
        LEFT JOIN utenti u ON u.id = s.id_utente
        WHERE s.approvato = 1
        ORDER BY s.data_creazione DESC
        LIMIT ?
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $limit);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $items = [];

    while ($row = $result->fetch_assoc()) {
        $isVideo = str_starts_with((string)($row['tipo_foto_shitpost'] ?? ''), 'video/');
        $row['image_url'] = ((int)$row['has_media'] === 1 && !$isVideo)
            ? '/api/content/media.php?type=shitpost&id=' . (int)$row['id']
            : '/img/og-default.jpg';
        $row['url'] = '/it/shitpost?post=' . (int)$row['id'];
        $items[] = $row;
    }

    $stmt->close();
    return $items;
}

function home_fetch_top_rimasti(mysqli $mysqli, int $limit = 4): array
{
    if (!home_table_exists($mysqli, 'toprimasti')) return [];

    $hasViews = in_array('views', home_columns($mysqli, 'toprimasti'), true);
    $hasTag = in_array('tag', home_columns($mysqli, 'toprimasti'), true);
    $hasVotes = home_table_exists($mysqli, 'voti_toprimasti');
    $hasComments = home_table_exists($mysqli, 'content_comments');

    $viewsSql = $hasViews ? 'COALESCE(t.`views`, 0)' : '0';
    $tagSql = $hasTag ? 't.`tag`' : 'NULL';
    $votesSql = $hasVotes ? '(SELECT COUNT(*) FROM voti_toprimasti v WHERE v.id_post = t.id)' : 'COALESCE(t.reazioni, 0)';
    $commentsSql = $hasComments ? "(SELECT COUNT(*) FROM content_comments c WHERE c.content_type = 'rimasto' AND c.post_id = t.id)" : '0';

    $sql = "
        SELECT
            t.id,
            t.id_utente,
            t.titolo,
            t.descrizione,
            t.motivazione,
            t.tipo_foto_rimasto,
            t.data_creazione,
            CASE WHEN t.foto_rimasto IS NULL THEN 0 ELSE 1 END AS has_media,
            $viewsSql AS views,
            $tagSql AS tag,
            $votesSql AS score,
            $commentsSql AS comments_count,
            u.username
        FROM toprimasti t
        LEFT JOIN utenti u ON u.id = t.id_utente
        WHERE t.approvato = 1
        ORDER BY score DESC, t.data_creazione DESC
        LIMIT ?
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $limit);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $items = [];

    while ($row = $result->fetch_assoc()) {
        $isVideo = str_starts_with((string)($row['tipo_foto_rimasto'] ?? ''), 'video/');
        $row['image_url'] = ((int)$row['has_media'] === 1 && !$isVideo)
            ? '/api/content/media.php?type=rimasto&id=' . (int)$row['id']
            : '/img/og-default.jpg';
        $row['url'] = '/it/rimasti?post=' . (int)$row['id'];
        $items[] = $row;
    }

    $stmt->close();
    return $items;
}

function home_fetch_active_users(mysqli $mysqli, int $limit = 6): array
{
    if (!home_table_exists($mysqli, 'utenti')) return [];

    $displayCol = home_first_col($mysqli, 'utenti', ['display_name', 'nome_visualizzato']);
    $lastCol = home_first_col($mysqli, 'utenti', ['ultimo_accesso', 'last_activity', 'last_seen', 'updated_at', 'data_creazione']);
    $visibilityCol = home_first_col($mysqli, 'utenti', ['profile_visibility']);

    $displaySql = $displayCol ? home_qcol($displayCol) : 'NULL';
    $lastSql = $lastCol ? home_qcol($lastCol) : 'data_creazione';
    $where = $visibilityCol ? "WHERE COALESCE(" . home_qcol($visibilityCol) . ", 'public') = 'public'" : '';

    $sql = "
        SELECT id, username, $displaySql AS display_name, $lastSql AS last_seen
        FROM utenti
        $where
        ORDER BY $lastSql DESC
        LIMIT ?
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $limit);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $items = [];

    while ($row = $result->fetch_assoc()) {
        $row['url'] = '/u/' . rawurlencode(strtolower((string)$row['username']));
        $items[] = $row;
    }

    $stmt->close();
    return $items;
}

function home_fetch_recent_achievements(mysqli $mysqli, int $limit = 4): array
{
    if (!home_table_exists($mysqli, 'utenti_achievement') || !home_table_exists($mysqli, 'achievement')) return [];

    $dateCol = home_first_col($mysqli, 'utenti_achievement', ['data', 'data_sblocco', 'created_at']);
    $achImgCol = home_first_col($mysqli, 'achievement', ['img_url', 'icona', 'image_url']);
    $achDescCol = home_first_col($mysqli, 'achievement', ['descrizione', 'description']);

    $dateSql = $dateCol ? 'ua.' . home_qcol($dateCol) : 'NULL';
    $imgSql = $achImgCol ? 'a.' . home_qcol($achImgCol) : 'NULL';
    $descSql = $achDescCol ? 'a.' . home_qcol($achDescCol) : 'NULL';

    $sql = "
        SELECT
            ua.utente_id,
            u.username,
            a.nome,
            $descSql AS descrizione,
            $imgSql AS img_url,
            $dateSql AS unlocked_at
        FROM utenti_achievement ua
        INNER JOIN utenti u ON u.id = ua.utente_id
        INNER JOIN achievement a ON a.id = ua.achievement_id
        ORDER BY $dateSql DESC
        LIMIT ?
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $limit);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $items = [];
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row['profile_url'] = '/u/' . rawurlencode(strtolower((string)$row['username']));
        $items[] = $row;
    }

    $stmt->close();
    return $items;
}

function home_user_data(mysqli $mysqli, int $userId): array
{
    if ($userId <= 0) return [];

    $data = [];

    if (home_table_exists($mysqli, 'utenti')) {
        $displayCol = home_first_col($mysqli, 'utenti', ['display_name', 'nome_visualizzato']);
        $bioCol = home_first_col($mysqli, 'utenti', ['bio']);
        $select = 'id, username, email, ruolo';
        $select .= $displayCol ? ', ' . home_qcol($displayCol) . ' AS display_name' : ', NULL AS display_name';
        $select .= $bioCol ? ', ' . home_qcol($bioCol) . ' AS bio' : ', NULL AS bio';

        $stmt = $mysqli->prepare("SELECT $select FROM utenti WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            if ($stmt->execute()) {
                $data['user'] = $stmt->get_result()->fetch_assoc() ?: null;
            }
            $stmt->close();
        }
    }

    if (home_table_exists($mysqli, 'utenti_personaggi')) {
        $qtyCol = home_first_col($mysqli, 'utenti_personaggi', ['quantità', 'quantita', 'quantity']);
        if ($qtyCol) {
            $qty = home_qcol($qtyCol);
            $data['pulls'] = home_count($mysqli, "SELECT COALESCE(SUM($qty), 0) AS total FROM utenti_personaggi WHERE utente_id = ?", 'i', [$userId]);
        } else {
            $data['pulls'] = home_count($mysqli, "SELECT COUNT(*) AS total FROM utenti_personaggi WHERE utente_id = ?", 'i', [$userId]);
        }
    }

    if (home_table_exists($mysqli, 'utenti_achievement')) {
        $data['achievements'] = home_count($mysqli, "SELECT COUNT(*) AS total FROM utenti_achievement WHERE utente_id = ?", 'i', [$userId]);
    }

    if (home_table_exists($mysqli, 'shitposts')) {
        $data['shitposts'] = home_count($mysqli, "SELECT COUNT(*) AS total FROM shitposts WHERE id_utente = ?", 'i', [$userId]);
    }

    if (home_table_exists($mysqli, 'toprimasti')) {
        $data['rimasti'] = home_count($mysqli, "SELECT COUNT(*) AS total FROM toprimasti WHERE id_utente = ?", 'i', [$userId]);
    }

    return $data;
}

$stats = [
    'users' => home_table_exists($mysqli, 'utenti') ? home_count($mysqli, "SELECT COUNT(*) AS total FROM utenti") : 0,
    'shitposts' => home_table_exists($mysqli, 'shitposts') ? home_count($mysqli, "SELECT COUNT(*) AS total FROM shitposts WHERE approvato = 1") : 0,
    'rimasti' => home_table_exists($mysqli, 'toprimasti') ? home_count($mysqli, "SELECT COUNT(*) AS total FROM toprimasti WHERE approvato = 1") : 0,
    'achievements' => home_table_exists($mysqli, 'achievement') ? home_count($mysqli, "SELECT COUNT(*) AS total FROM achievement") : 0,
];

$latestShitposts = home_fetch_latest_shitposts($mysqli, 4);
$topRimasti = home_fetch_top_rimasti($mysqli, 4);
$activeUsers = home_fetch_active_users($mysqli, 6);
$recentAchievements = home_fetch_recent_achievements($mysqli, 4);
$userData = $isLoggedIn ? home_user_data($mysqli, $currentUserId) : [];
$user = $userData['user'] ?? null;
$displayName = trim((string)($user['display_name'] ?? '')) ?: ($currentUsername ?: 'utente');

$primaryActionUrl = $isLoggedIn && $currentUsername ? '/u/' . rawurlencode(strtolower($currentUsername)) : '/it/registrati';
$primaryActionText = $isLoggedIn ? 'Vai al profilo' : 'Crea account';

$sections = [
    [
        'id' => 'bio',
        'title' => 'Bio',
        'text' => 'Profilo pubblico, link, badge e contenuti in evidenza.',
        'icon' => 'fas fa-user-astronaut',
        'url' => $isLoggedIn && $currentUsername ? '/u/' . rawurlencode(strtolower($currentUsername)) : '/it/accedi',
        'meta' => $isLoggedIn ? 'Il tuo spazio' : 'Account',
        'enabled' => true,
    ],
    [
        'id' => 'chat',
        'title' => 'Chat Globale',
        'text' => 'Parla con gli altri utenti del sito.',
        'icon' => 'fas fa-comments',
        'url' => '/it/global-chat',
        'meta' => 'Live',
        'enabled' => true,
    ],
    [
        'id' => 'shitpost',
        'title' => 'Shitpost',
        'text' => 'Meme, GIF e post della community.',
        'icon' => 'fas fa-image',
        'url' => '/it/shitpost',
        'meta' => home_compact_number($stats['shitposts']) . ' post',
        'enabled' => home_table_exists($mysqli, 'shitposts'),
    ],
    [
        'id' => 'lootbox',
        'title' => 'Lootbox',
        'text' => 'Apri casse, colleziona personaggi e sblocca badge.',
        'icon' => 'fas fa-box-open',
        'url' => '/it/lootbox',
        'meta' => 'Game',
        'enabled' => true,
    ],
    [
        'id' => 'achievement',
        'title' => 'Achievement',
        'text' => 'Obiettivi e badge del tuo account.',
        'icon' => 'fas fa-trophy',
        'url' => '/it/achievement',
        'meta' => home_compact_number($stats['achievements']) . ' badge',
        'enabled' => home_table_exists($mysqli, 'achievement'),
    ],
    [
        'id' => 'rimasti',
        'title' => 'Top Rimasti',
        'text' => 'I post più votati dalla community.',
        'icon' => 'fas fa-ranking-star',
        'url' => '/it/rimasti',
        'meta' => home_compact_number($stats['rimasti']) . ' post',
        'enabled' => home_table_exists($mysqli, 'toprimasti'),
    ],
    [
        'id' => 'pedia',
        'title' => 'CripsumPedia',
        'text' => 'Pagine, profili e lore del sito.',
        'icon' => 'fas fa-book-skull',
        'url' => '/it/cripsumpedia',
        'meta' => 'Wiki',
        'enabled' => true,
    ],
    [
        'id' => 'goonland',
        'title' => 'GoonLand',
        'text' => 'Esperimenti, pagine strane e cose interne.',
        'icon' => 'fas fa-wand-magic-sparkles',
        'url' => '/it/goonland',
        'meta' => 'Hub',
        'enabled' => true,
    ],
];

$enabledSections = array_values(array_filter($sections, fn($item) => $item['enabled']));

$ogTitle = 'Cripsum™';
$ogDescription = 'Profili, chat, shitpost, Top Rimasti, lootbox, achievement e contenuti della community.';
$ogImage = home_abs_url('/img/og-default.jpg');
$ogUrl = home_abs_url(strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/home'), '#'));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include __DIR__ . '/../includes/head-import.php'; ?>
    <title>Cripsum™ - Home</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo home_h($ogDescription); ?>">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo home_h($ogTitle); ?>">
    <meta property="og:description" content="<?php echo home_h($ogDescription); ?>">
    <meta property="og:image" content="<?php echo home_h($ogImage); ?>">
    <meta property="og:url" content="<?php echo home_h($ogUrl); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo home_h($ogTitle); ?>">
    <meta name="twitter:description" content="<?php echo home_h($ogDescription); ?>">
    <meta name="twitter:image" content="<?php echo home_h($ogImage); ?>">
    <link rel="stylesheet" href="/assets/home-v3/home-v3.css?v=3.0-home">
    <script src="/assets/home-v3/home-v3.js?v=3.0-home" defer></script>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1527058839538660" crossorigin="anonymous"></script>
</head>
<body class="home-v3-body" data-logged="<?php echo $isLoggedIn ? '1' : '0'; ?>">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/impostazioni.php'; ?>

    <div class="home-bg" aria-hidden="true">
        <span class="home-orb home-orb--one"></span>
        <span class="home-orb home-orb--two"></span>
        <span class="home-grid"></span>
    </div>

    <main class="home-shell">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="home-alert" role="alert">
                <i class="fas fa-triangle-exclamation"></i>
                <span><?php echo home_h($_SESSION['error_message']); ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <section class="home-hero home-reveal">
            <div class="home-hero__left">
                <span class="home-pill">Cripsum™ / GoonLand</span>

                <?php if ($isLoggedIn): ?>
                    <h1>Ciao, <?php echo home_h($displayName); ?>.</h1>
                    <p>Questa è la tua base: profilo, feed, chat, lootbox e contenuti della community.</p>
                <?php else: ?>
                    <h1>Profili, meme, chat e caos utile.</h1>
                    <p>Cripsum è un sito personale con bio pubbliche, chat globale, shitpost, Top Rimasti, lootbox e achievement.</p>
                <?php endif; ?>

                <div class="home-actions">
                    <a class="home-btn home-btn--primary" href="<?php echo home_h($primaryActionUrl); ?>">
                        <i class="<?php echo $isLoggedIn ? 'fas fa-user' : 'fas fa-user-plus'; ?>"></i>
                        <span><?php echo home_h($primaryActionText); ?></span>
                    </a>

                    <?php if (!$isLoggedIn): ?>
                        <a class="home-btn home-btn--ghost" href="/it/accedi">
                            <i class="fas fa-right-to-bracket"></i>
                            <span>Accedi</span>
                        </a>
                    <?php endif; ?>

                    <a class="home-btn home-btn--ghost" href="/it/shitpost">
                        <i class="fas fa-image"></i>
                        <span>Apri il feed</span>
                    </a>
                </div>

                <?php if ($isLoggedIn && $user): ?>
                    <div class="home-profile-mini">
                        <img src="/includes/get_pfp.php?id=<?php echo (int)$currentUserId; ?>" alt="" loading="eager">
                        <div>
                            <strong>@<?php echo home_h($user['username'] ?? $currentUsername); ?></strong>
                            <?php if (!empty($user['bio'])): ?>
                                <span><?php echo home_h(mb_substr((string)$user['bio'], 0, 90)); ?></span>
                            <?php else: ?>
                                <span>Il tuo profilo è pronto da personalizzare.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="home-command-card" aria-label="Ricerca rapida">
                <div class="home-command-card__head">
                    <i class="fas fa-terminal"></i>
                    <span>Vai rapido</span>
                </div>

                <label class="home-search">
                    <i class="fas fa-search"></i>
                    <input id="homeQuickSearch" type="search" placeholder="Cerca sezioni">
                </label>

                <div class="home-quick-results" id="homeQuickResults">
                    <?php foreach (array_slice($enabledSections, 0, 5) as $section): ?>
                        <a href="<?php echo home_h($section['url']); ?>" data-search-item data-title="<?php echo home_h(strtolower($section['title'] . ' ' . $section['text'])); ?>">
                            <i class="<?php echo home_h($section['icon']); ?>"></i>
                            <span><?php echo home_h($section['title']); ?></span>
                            <small><?php echo home_h($section['meta']); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>

                <a class="home-continue" id="homeContinue" href="#" hidden>
                    <i class="fas fa-rotate-left"></i>
                    <span>Continua</span>
                </a>
            </aside>
        </section>

        <section class="home-stats home-reveal" aria-label="Statistiche sito">
            <?php if ($stats['users'] > 0): ?>
                <article>
                    <strong><?php echo home_h(home_compact_number($stats['users'])); ?></strong>
                    <span>Utenti</span>
                </article>
            <?php endif; ?>
            <?php if ($stats['shitposts'] > 0): ?>
                <article>
                    <strong><?php echo home_h(home_compact_number($stats['shitposts'])); ?></strong>
                    <span>Shitpost</span>
                </article>
            <?php endif; ?>
            <?php if ($stats['rimasti'] > 0): ?>
                <article>
                    <strong><?php echo home_h(home_compact_number($stats['rimasti'])); ?></strong>
                    <span>Top Rimasti</span>
                </article>
            <?php endif; ?>
            <?php if ($stats['achievements'] > 0): ?>
                <article>
                    <strong><?php echo home_h(home_compact_number($stats['achievements'])); ?></strong>
                    <span>Achievement</span>
                </article>
            <?php endif; ?>
        </section>

        <?php if ($isLoggedIn): ?>
            <section class="home-user-panel home-reveal">
                <div class="home-section-head">
                    <div>
                        <span class="home-kicker">Account</span>
                        <h2>Il tuo riepilogo</h2>
                    </div>
                    <a class="home-mini-link" href="<?php echo home_h($primaryActionUrl); ?>">Apri profilo</a>
                </div>

                <div class="home-user-stats">
                    <?php if (($userData['pulls'] ?? 0) > 0): ?>
                        <article><strong><?php echo home_h(home_compact_number((int)$userData['pulls'])); ?></strong><span>Pull</span></article>
                    <?php endif; ?>
                    <?php if (($userData['achievements'] ?? 0) > 0): ?>
                        <article><strong><?php echo home_h(home_compact_number((int)$userData['achievements'])); ?></strong><span>Badge</span></article>
                    <?php endif; ?>
                    <?php if (($userData['shitposts'] ?? 0) > 0): ?>
                        <article><strong><?php echo home_h(home_compact_number((int)$userData['shitposts'])); ?></strong><span>Shitpost</span></article>
                    <?php endif; ?>
                    <?php if (($userData['rimasti'] ?? 0) > 0): ?>
                        <article><strong><?php echo home_h(home_compact_number((int)$userData['rimasti'])); ?></strong><span>Rimasti</span></article>
                    <?php endif; ?>
                    <?php if (empty($userData['pulls']) && empty($userData['achievements']) && empty($userData['shitposts']) && empty($userData['rimasti'])): ?>
                        <div class="home-soft-empty">Appena userai il sito, qui comparirà il tuo riepilogo.</div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="home-section home-reveal">
            <div class="home-section-head">
                <div>
                    <span class="home-kicker">Sezioni</span>
                    <h2>Le parti principali</h2>
                </div>
            </div>

            <div class="home-section-grid">
                <?php foreach ($enabledSections as $section): ?>
                    <a class="home-section-card" href="<?php echo home_h($section['url']); ?>" data-track-section="<?php echo home_h($section['id']); ?>" data-search-item data-title="<?php echo home_h(strtolower($section['title'] . ' ' . $section['text'])); ?>">
                        <span class="home-section-card__meta"><?php echo home_h($section['meta']); ?></span>
                        <i class="<?php echo home_h($section['icon']); ?>"></i>
                        <strong><?php echo home_h($section['title']); ?></strong>
                        <p><?php echo home_h($section['text']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($latestShitposts || $topRimasti): ?>
            <section class="home-content-split home-reveal">
                <?php if ($latestShitposts): ?>
                    <div class="home-feed-block">
                        <div class="home-section-head">
                            <div>
                                <span class="home-kicker">Feed</span>
                                <h2>Ultimi shitpost</h2>
                            </div>
                            <a class="home-mini-link" href="/it/shitpost">Vedi tutto</a>
                        </div>

                        <div class="home-post-grid">
                            <?php foreach ($latestShitposts as $post): ?>
                                <a class="home-post-card" href="<?php echo home_h($post['url']); ?>">
                                    <img src="<?php echo home_h($post['image_url']); ?>" alt="" loading="lazy">
                                    <div>
                                        <strong><?php echo home_h($post['titolo'] ?: 'Senza titolo'); ?></strong>
                                        <span>@<?php echo home_h($post['username'] ?: 'utente'); ?> · <?php echo home_h(home_time_ago($post['data_creazione'] ?? null)); ?></span>
                                        <small><i class="fas fa-fire"></i><?php echo home_h(home_compact_number((int)$post['score'])); ?> <i class="fas fa-comment"></i><?php echo home_h(home_compact_number((int)$post['comments_count'])); ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($topRimasti): ?>
                    <div class="home-feed-block">
                        <div class="home-section-head">
                            <div>
                                <span class="home-kicker">Top</span>
                                <h2>Rimasti più votati</h2>
                            </div>
                            <a class="home-mini-link" href="/it/rimasti">Classifica</a>
                        </div>

                        <div class="home-post-list">
                            <?php foreach ($topRimasti as $index => $post): ?>
                                <a class="home-rank-card" href="<?php echo home_h($post['url']); ?>">
                                    <span class="home-rank-number">#<?php echo $index + 1; ?></span>
                                    <img src="<?php echo home_h($post['image_url']); ?>" alt="" loading="lazy">
                                    <div>
                                        <strong><?php echo home_h($post['titolo'] ?: 'Senza titolo'); ?></strong>
                                        <span>@<?php echo home_h($post['username'] ?: 'utente'); ?></span>
                                    </div>
                                    <em><i class="fas fa-fire"></i><?php echo home_h(home_compact_number((int)$post['score'])); ?></em>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($activeUsers || $recentAchievements): ?>
            <section class="home-side-content home-reveal">
                <?php if ($activeUsers): ?>
                    <div class="home-people">
                        <div class="home-section-head">
                            <div>
                                <span class="home-kicker">Community</span>
                                <h2>Utenti recenti</h2>
                            </div>
                        </div>

                        <div class="home-user-list">
                            <?php foreach ($activeUsers as $profile): ?>
                                <a class="home-user-row" href="<?php echo home_h($profile['url']); ?>">
                                    <img src="/includes/get_pfp.php?id=<?php echo (int)$profile['id']; ?>" alt="" loading="lazy">
                                    <div>
                                        <strong><?php echo home_h($profile['display_name'] ?: $profile['username']); ?></strong>
                                        <span>@<?php echo home_h($profile['username']); ?><?php echo home_time_ago($profile['last_seen']) ? ' · ' . home_h(home_time_ago($profile['last_seen'])) : ''; ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($recentAchievements): ?>
                    <div class="home-achievements">
                        <div class="home-section-head">
                            <div>
                                <span class="home-kicker">Badge</span>
                                <h2>Sblocchi recenti</h2>
                            </div>
                        </div>

                        <div class="home-achievement-list">
                            <?php foreach ($recentAchievements as $achievement): ?>
                                <a class="home-achievement-row" href="<?php echo home_h($achievement['profile_url']); ?>">
                                    <?php if (!empty($achievement['img_url'])): ?>
                                        <img src="/img/<?php echo home_h(ltrim((string)$achievement['img_url'], '/')); ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <span><i class="fas fa-medal"></i></span>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo home_h($achievement['nome']); ?></strong>
                                        <small>@<?php echo home_h($achievement['username']); ?><?php echo home_time_ago($achievement['unlocked_at']) ? ' · ' . home_h(home_time_ago($achievement['unlocked_at'])) : ''; ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!$isLoggedIn): ?>
            <section class="home-cta home-reveal">
                <div>
                    <span class="home-kicker">Account</span>
                    <h2>Entra e rendi tuo il sito.</h2>
                    <p>Puoi creare la bio, commentare, votare, usare la chat e sbloccare achievement.</p>
                </div>
                <div class="home-actions">
                    <a class="home-btn home-btn--primary" href="/it/registrati">Registrati</a>
                    <a class="home-btn home-btn--ghost" href="/it/accedi">Accedi</a>
                </div>
            </section>
        <?php endif; ?>

        <section class="home-actions-row home-reveal">
            <button class="home-btn home-btn--ghost" type="button" data-bs-toggle="modal" data-bs-target="#homeInfoModal">
                <i class="fas fa-circle-info"></i>
                <span>Info</span>
            </button>

            <button class="home-btn home-btn--ghost js-copy-home" type="button" data-url="https://cripsum.com/it/home">
                <i class="fas fa-link"></i>
                <span>Copia link</span>
            </button>

            <button class="home-btn home-btn--ghost" type="button" onclick="if (typeof unlockAchievement === 'function') unlockAchievement(10); window.open('https://youtu.be/xvFZjo5PgG0?si=uPsap7ILF_8aYheh', '_blank', 'noopener');">
                <i class="fas fa-gift"></i>
                <span>V-bucks gratis</span>
            </button>
        </section>
    </main>

    <div class="modal fade home-modal" id="homeInfoModal" tabindex="-1" aria-labelledby="homeInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content home-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="homeInfoModalLabel">Cripsum™</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    <p>Il sito unisce profili, contenuti della community e piccole feature sperimentali.</p>
                    <p class="home-muted">Alcune parti sono meme o esperimenti. Usale come tali.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement">
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <div id="homeToast" class="home-toast" role="status" aria-live="polite"></div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
