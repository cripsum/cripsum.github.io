<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/theme.php';
require_once __DIR__ . '/../includes/security_helpers.php';
require_once __DIR__ . '/../includes/cripsumpedia/schema.php';
require_once __DIR__ . '/../includes/cripsumpedia/content.php';
require_once __DIR__ . '/../includes/cripsumpedia/revisions.php';
require_once __DIR__ . '/../includes/cripsumpedia/views.php';
require_once __DIR__ . '/../includes/cripsumpedia/media.php';
require_once __DIR__ . '/../includes/cripsumpedia/lists.php';
require_once __DIR__ . '/../includes/cripsumpedia/ui.php';

mysqli_report(MYSQLI_REPORT_OFF);

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

const CP_ENTRY_TYPES = ['person', 'event', 'meme'];
const CP_SCHEMA_TABLES = [
    'cripsumpedia_entries',
    'cripsumpedia_relations',
    'cripsumpedia_tags',
    'cripsumpedia_entry_tags',
    'cripsumpedia_views',
    'cripsumpedia_aliases',
    'cripsumpedia_quotes',
];

function cp_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cp_detect_lang(): string
{
    $candidate = $_GET['lang'] ?? '';
    if (!is_string($candidate) || $candidate === '') {
        $parts = explode('/', trim((string)($_SERVER['REQUEST_URI'] ?? ''), '/'));
        $candidate = $parts[0] ?? '';
    }
    return in_array($candidate, ['it', 'en'], true) ? $candidate : 'it';
}

function cp_translations(string $lang): array
{
    $dict = [
        'it' => [
            'home' => 'Home',
            'pedia' => 'Cripsumpedia',
            'subtitle' => 'Cerca persone, eventi e meme di Cripsum.',
            'search' => 'Cerca',
            'search_placeholder' => 'Cerca una pagina...',
            'people' => 'Persone',
            'events' => 'Eventi',
            'memes' => 'Meme',
            'person' => 'Persona',
            'event' => 'Evento',
            'meme' => 'Meme',
            'latest' => 'Recenti',
            'trending' => 'In evidenza',
            'popular' => 'Popolari',
            'event_day' => 'Evento del giorno',
            'random_quote' => 'Citazione del giorno',
            'open' => 'Apri',
            'read' => 'Apri',
            'admin' => 'Admin lore',
            'new_entry' => 'Nuova pagina',
            'editor' => 'Editor',
            'timeline' => 'Timeline',
            'all' => 'Tutti',
            'filters' => 'Filtri',
            'tag' => 'Tag',
            'status' => 'Stato',
            'canon' => 'Canonico',
            'non_canon' => 'Non canonico',
            'disputed' => 'Dibattuto',
            'archived' => 'Archiviato',
            'draft' => 'Bozza',
            'published' => 'Pubblicato',
            'relations' => 'Relazioni',
            'related_people' => 'Persone correlate',
            'related_events' => 'Eventi correlati',
            'related_memes' => 'Meme collegati',
            'quick_info' => 'Info rapide',
            'aliases' => 'Alias',
            'quotes' => 'Citazioni',
            'created' => 'Creata',
            'updated' => 'Ultima modifica',
            'views' => 'views',
            'importance' => 'Importanza',
            'lore_date' => 'Data lore',
            'real_date' => 'Data reale',
            'focus' => 'Focus lettura',
            'share' => 'Condividi',
            'favorite' => 'Preferito',
            'reaction' => 'Reaction',
            'copy_link' => 'Copia link',
            'random' => 'Random',
            'no_results' => 'Nessun risultato',
            'install_title' => 'Cripsumpedia non installata',
            'install_text' => 'Importa lo schema SQL prima di usare questa sezione.',
            'back_home' => 'Torna alla home',
            'save' => 'Salva',
            'delete' => 'Elimina',
            'preview' => 'Preview',
            'content' => 'Contenuto',
            'metadata' => 'Metadati',
            'media' => 'Media',
            'seo' => 'SEO',
            'language_it' => 'Italiano',
            'language_en' => 'Inglese',
            'empty' => 'Nessuna pagina qui.',
        ],
        'en' => [
            'home' => 'Home',
            'pedia' => 'Cripsumpedia',
            'subtitle' => 'Find Cripsum people, events and memes.',
            'search' => 'Search',
            'search_placeholder' => 'Search a page...',
            'people' => 'People',
            'events' => 'Events',
            'memes' => 'Memes',
            'person' => 'Person',
            'event' => 'Event',
            'meme' => 'Meme',
            'latest' => 'Recent',
            'trending' => 'Featured',
            'popular' => 'Popular',
            'event_day' => 'Event of the day',
            'random_quote' => 'Quote of the day',
            'open' => 'Open',
            'read' => 'Open',
            'admin' => 'Lore admin',
            'new_entry' => 'New page',
            'editor' => 'Editor',
            'timeline' => 'Timeline',
            'all' => 'All',
            'filters' => 'Filters',
            'tag' => 'Tag',
            'status' => 'Status',
            'canon' => 'Canon',
            'non_canon' => 'Non canon',
            'disputed' => 'Disputed',
            'archived' => 'Archived',
            'draft' => 'Draft',
            'published' => 'Published',
            'relations' => 'Relations',
            'related_people' => 'Related people',
            'related_events' => 'Related events',
            'related_memes' => 'Linked memes',
            'quick_info' => 'Quick info',
            'aliases' => 'Aliases',
            'quotes' => 'Quotes',
            'created' => 'Created',
            'updated' => 'Last edited',
            'views' => 'views',
            'importance' => 'Importance',
            'lore_date' => 'Lore date',
            'real_date' => 'Real date',
            'focus' => 'Reading focus',
            'share' => 'Share',
            'favorite' => 'Favorite',
            'reaction' => 'Reaction',
            'copy_link' => 'Copy link',
            'random' => 'Random',
            'no_results' => 'No results found',
            'install_title' => 'Cripsumpedia is not installed',
            'install_text' => 'Import the SQL schema before using this section.',
            'back_home' => 'Back home',
            'save' => 'Save',
            'delete' => 'Delete',
            'preview' => 'Preview',
            'content' => 'Content',
            'metadata' => 'Metadata',
            'media' => 'Media',
            'seo' => 'SEO',
            'language_it' => 'Italian',
            'language_en' => 'English',
            'empty' => 'No pages here.',
        ],
    ];

    return $dict[$lang] ?? $dict['it'];
}

function cp_t(string $key, ?string $lang = null): string
{
    $lang = $lang ?: cp_detect_lang();
    $dict = cp_translations($lang);
    return $dict[$key] ?? $key;
}

function cp_i18n(array $row, string $field, string $lang): string
{
    $value = '';
    if ($lang === 'en' && array_key_exists($field . '_en', $row)) {
        $value = trim((string)($row[$field . '_en'] ?? ''));
    }
    if ($value === '') {
        $value = trim((string)($row[$field] ?? ''));
    }
    return $value;
}

function cp_is_admin_user(): bool
{
    $role = $_SESSION['ruolo'] ?? '';
    return $role === 'admin' || $role === 'owner';
}

function cp_require_admin(bool $json = false): void
{
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        if ($json) cp_json(['ok' => false, 'message' => 'Login richiesto.'], 401);
        header('Location: /it/accedi');
        exit;
    }
    if (!cp_is_admin_user()) {
        if ($json) cp_json(['ok' => false, 'message' => 'Permessi admin richiesti.'], 403);
        http_response_code(403);
        echo 'Non autorizzato.';
        exit;
    }
}

// Lo stesso token del resto del sito (includes/security_helpers.php).
function cp_csrf_token(): string
{
    return csrf_token();
}

function cp_validate_csrf(?string $token): bool
{
    return csrf_validate($token);
}

function cp_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cp_read_input(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input') ?: '{}';
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function cp_table_exists(mysqli $mysqli, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return $cache[$table] = false;

    $stmt = $mysqli->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = ? LIMIT 1");
    if (!$stmt) return $cache[$table] = false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $cache[$table] = $exists;
}

function cp_schema_ready(mysqli $mysqli): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    foreach (CP_SCHEMA_TABLES as $table) {
        if (!cp_table_exists($mysqli, $table)) {
            return $ready = false;
        }
    }
    return $ready = true;
}

function cp_stmt_all(mysqli_stmt $stmt): array
{
    $result = $stmt->get_result();
    if (!$result) return [];
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function cp_slugify(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
        $value = $ascii;
    }
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? strtolower($value) : bin2hex(random_bytes(4));
}

function cp_normalize_type(?string $type): ?string
{
    $type = strtolower(trim((string)$type));
    $map = [
        'person' => 'person',
        'people' => 'person',
        'persona' => 'person',
        'persone' => 'person',
        'event' => 'event',
        'events' => 'event',
        'evento' => 'event',
        'eventi' => 'event',
        'meme' => 'meme',
        'memes' => 'meme',
    ];
    return $map[$type] ?? null;
}

function cp_category_slug(string $type, string $lang): string
{
    return match ($type) {
        'person' => $lang === 'en' ? 'people' : 'persone',
        'event' => $lang === 'en' ? 'events' : 'eventi',
        default => $lang === 'en' ? 'memes' : 'meme',
    };
}

function cp_type_label(string $type, string $lang): string
{
    return cp_t($type === 'person' ? 'person' : ($type === 'event' ? 'event' : 'meme'), $lang);
}

function cp_type_plural(string $type, string $lang): string
{
    return cp_t($type === 'person' ? 'people' : ($type === 'event' ? 'events' : 'memes'), $lang);
}

function cp_type_icon(string $type): string
{
    return match ($type) {
        'person' => 'fa-user-astronaut',
        'event' => 'fa-timeline',
        default => 'fa-face-grin-squint-tears',
    };
}

function cp_valid_color(?string $color, string $fallback = '#2f6bff'): string
{
    $color = trim((string)$color);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : $fallback;
}

function cp_asset_url(?string $path, string $fallback = '/img/Susremaster.png'): string
{
    $path = trim((string)$path);
    if ($path === '') return $fallback;
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    if (str_starts_with($path, '/')) return $path;
    if (str_starts_with($path, 'img/')) return '/' . $path;
    return '/img/' . ltrim($path, '/');
}

function cp_base_url(?string $lang = null): string
{
    $lang = $lang ?: cp_detect_lang();
    return '/' . $lang . '/cripsumpedia';
}

function cp_url(string $route = 'home', array $params = [], ?string $lang = null): string
{
    $lang = $lang ?: cp_detect_lang();
    $base = cp_base_url($lang);
    $query = [];

    if ($route === 'home') {
        $url = $base;
    } elseif ($route === 'search') {
        $url = $base . '/search';
    } elseif ($route === 'admin') {
        $url = $base . '/admin';
    } elseif ($route === 'editor') {
        $url = $base . '/editor';
    } elseif ($route === 'category') {
        $type = cp_normalize_type($params['type'] ?? 'person') ?? 'person';
        $url = $base . '/' . cp_category_slug($type, $lang);
        unset($params['type']);
    } elseif ($route === 'entry') {
        $type = cp_normalize_type($params['type'] ?? 'person') ?? 'person';
        $slug = cp_slugify((string)($params['slug'] ?? ''));
        $url = $base . '/' . cp_category_slug($type, $lang) . '/' . $slug;
        unset($params['type'], $params['slug']);
    } else {
        $url = $base;
    }

    foreach ($params as $key => $value) {
        if ($value !== null && $value !== '') $query[$key] = $value;
    }
    return $query ? $url . '?' . http_build_query($query) : $url;
}

function cp_entry_url(array $entry, string $lang): string
{
    return cp_url('entry', [
        'type' => $entry['entry_type'] ?? 'person',
        'slug' => $entry['slug'] ?? '',
    ], $lang);
}

function cp_excerpt(string $text, int $length = 180): string
{
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    if (mb_strlen($plain, 'UTF-8') <= $length) return $plain;
    return rtrim(mb_substr($plain, 0, $length - 1, 'UTF-8')) . '...';
}

function cp_safe_date(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') return '';
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : $value;
}

function cp_status_label(?string $status, string $lang): string
{
    return match ($status) {
        'non_canon' => cp_t('non_canon', $lang),
        'disputed' => cp_t('disputed', $lang),
        'archived' => cp_t('archived', $lang),
        default => cp_t('canon', $lang),
    };
}

function cp_fetch_stats(mysqli $mysqli): array
{
    $stats = ['person' => 0, 'event' => 0, 'meme' => 0, 'views' => 0, 'quotes' => 0, 'relations' => 0];
    if (!cp_schema_ready($mysqli)) return $stats;

    $sql = "SELECT entry_type, COUNT(*) AS total, COALESCE(SUM(views_count), 0) AS views
            FROM cripsumpedia_entries
            WHERE status = 'published'
            GROUP BY entry_type";
    if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $type = (string)$row['entry_type'];
            if (isset($stats[$type])) {
                $stats[$type] = (int)$row['total'];
                $stats['views'] += (int)$row['views'];
            }
        }
    }

    if ($result = $mysqli->query("SELECT COUNT(*) AS total FROM cripsumpedia_quotes")) {
        $row = $result->fetch_assoc();
        $stats['quotes'] = (int)($row['total'] ?? 0);
    }

    if ($result = $mysqli->query("SELECT COUNT(*) AS total FROM cripsumpedia_relations")) {
        $row = $result->fetch_assoc();
        $stats['relations'] = (int)($row['total'] ?? 0);
    }
    return $stats;
}

function cp_fetch_entries(mysqli $mysqli, array $options = []): array
{
    if (!cp_schema_ready($mysqli)) return [];

    $where = [];
    $types = '';
    $params = [];
    $joins = '';

    $status = $options['status'] ?? 'published';
    if ($status !== 'all') {
        $where[] = 'e.status = ?';
        $types .= 's';
        $params[] = $status;
    }
    if (cp_v2_ready($mysqli)) {
        $where[] = 'e.deleted_at IS NULL';
    }

    $entryType = cp_normalize_type($options['type'] ?? null);
    if ($entryType) {
        $where[] = 'e.entry_type = ?';
        $types .= 's';
        $params[] = $entryType;
    }

    $tag = trim((string)($options['tag'] ?? ''));
    if ($tag !== '') {
        $joins .= ' INNER JOIN cripsumpedia_entry_tags et_filter ON et_filter.entry_id = e.id
                    INNER JOIN cripsumpedia_tags t_filter ON t_filter.id = et_filter.tag_id ';
        $where[] = 't_filter.slug = ?';
        $types .= 's';
        $params[] = cp_slugify($tag);
    }

    $query = trim((string)($options['q'] ?? ''));
    if ($query !== '') {
        $like = '%' . addcslashes($query, '%_\\') . '%'; // % e _ cercati come testo
        $where[] = "(e.title LIKE ? OR e.title_en LIKE ? OR e.description LIKE ? OR e.description_en LIKE ?
                    OR e.content_md LIKE ? OR e.content_md_en LIKE ?
                    OR EXISTS (SELECT 1 FROM cripsumpedia_aliases a WHERE a.entry_id = e.id AND (a.alias LIKE ? OR a.alias_en LIKE ?))
                    OR EXISTS (SELECT 1 FROM cripsumpedia_quotes q WHERE q.entry_id = e.id AND (q.quote_text LIKE ? OR q.quote_text_en LIKE ?)))";
        $types .= 'ssssssssss';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
    }

    $lang = cp_detect_lang();
    $order = match ((string)($options['order'] ?? 'latest')) {
        'popular' => 'e.views_count DESC, e.updated_at DESC',
        'trending' => cp_trending_order_sql($mysqli, 'e'),
        'importance' => 'e.importance DESC, e.real_date DESC, e.updated_at DESC',
        'timeline' => 'COALESCE(e.real_date, e.created_at) ASC, e.importance DESC',
        'alphabetical' => ($lang === 'en' ? "COALESCE(NULLIF(e.title_en, ''), e.title) ASC" : "e.title ASC"),
        'date' => 'COALESCE(e.real_date, e.created_at) DESC',
        'updated' => 'e.updated_at DESC, e.id DESC',
        'random' => 'RAND()',
        default => 'e.created_at DESC, e.id DESC',
    };

    $limit = max(1, min(80, (int)($options['limit'] ?? 24)));
    $offset = max(0, (int)($options['offset'] ?? 0));
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT DISTINCT e.*
            FROM cripsumpedia_entries e
            $joins
            $whereSql
            ORDER BY $order
            LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();
    $unique = [];
    $excludeId = isset($options['exclude']) ? (int)$options['exclude'] : 0;
    foreach ($rows as $row) {
        $relatedId = (int)($row['id'] ?? 0);
        if ($relatedId <= 0 || $relatedId === $excludeId || isset($unique[$relatedId])) continue;
        $unique[$relatedId] = $row;
    }
    return array_values($unique);
}

function cp_count_entries(mysqli $mysqli, array $options = []): int
{
    if (!cp_schema_ready($mysqli)) return 0;

    $where = [];
    $types = '';
    $params = [];

    $status = $options['status'] ?? 'published';
    if ($status !== 'all') {
        $where[] = 'status = ?';
        $types .= 's';
        $params[] = $status;
    }

    $entryType = cp_normalize_type($options['type'] ?? null);
    if ($entryType) {
        $where[] = 'entry_type = ?';
        $types .= 's';
        $params[] = $entryType;
    }
    if (cp_v2_ready($mysqli)) {
        $where[] = 'deleted_at IS NULL';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM cripsumpedia_entries $whereSql");
    if (!$stmt) return 0;
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

function cp_fetch_entry(mysqli $mysqli, ?string $type, string $slugOrId, bool $includeDrafts = false, bool $includeDeleted = false): ?array
{
    if (!cp_schema_ready($mysqli)) return null;

    $isId = ctype_digit($slugOrId);
    $statusSql = $includeDrafts ? '' : " AND status = 'published'";
    if (!$includeDeleted) {
        $statusSql .= cp_not_deleted_sql($mysqli);
    }

    if ($isId) {
        $id = (int)$slugOrId;
        $stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_entries WHERE id = ?$statusSql LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
    } else {
        $normalizedType = cp_normalize_type($type);
        if (!$normalizedType) return null;
        $stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_entries WHERE entry_type = ? AND slug = ?$statusSql LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('ss', $normalizedType, $slugOrId);
    }

    $stmt->execute();
    $entry = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $entry ?: null;
}

function cp_fetch_tags(mysqli $mysqli, ?int $entryId = null): array
{
    if (!cp_schema_ready($mysqli)) return [];
    if ($entryId) {
        $stmt = $mysqli->prepare("SELECT t.*
            FROM cripsumpedia_tags t
            INNER JOIN cripsumpedia_entry_tags et ON et.tag_id = t.id
            WHERE et.entry_id = ?
            ORDER BY t.name ASC");
        if (!$stmt) return [];
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $rows = cp_stmt_all($stmt);
        $stmt->close();
        return $rows;
    }

    $rows = [];
    if ($result = $mysqli->query("SELECT * FROM cripsumpedia_tags ORDER BY name ASC")) {
        while ($row = $result->fetch_assoc()) $rows[] = $row;
    }
    return $rows;
}

function cp_fetch_aliases(mysqli $mysqli, int $entryId): array
{
    if (!cp_schema_ready($mysqli)) return [];
    $stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_aliases WHERE entry_id = ? ORDER BY alias ASC");
    if (!$stmt) return [];
    $stmt->bind_param('i', $entryId);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();
    return $rows;
}

function cp_fetch_quotes(mysqli $mysqli, ?int $entryId = null, int $limit = 20, bool $featuredOnly = false): array
{
    if (!cp_schema_ready($mysqli)) return [];
    $limit = max(1, min(50, $limit));

    if ($entryId) {
        $featuredSql = $featuredOnly ? ' AND is_featured = 1' : '';
        $stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_quotes WHERE entry_id = ?$featuredSql ORDER BY is_featured DESC, created_at DESC LIMIT ?");
        if (!$stmt) return [];
        $stmt->bind_param('ii', $entryId, $limit);
    } else {
        $featuredSql = $featuredOnly ? 'WHERE q.is_featured = 1' : '';
        $stmt = $mysqli->prepare("SELECT q.*, e.entry_type, e.slug, e.title, e.title_en
            FROM cripsumpedia_quotes q
            INNER JOIN cripsumpedia_entries e ON e.id = q.entry_id
            $featuredSql
            ORDER BY RAND()
            LIMIT ?");
        if (!$stmt) return [];
        $stmt->bind_param('i', $limit);
    }

    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();
    return $rows;
}

function cp_fetch_relations(mysqli $mysqli, int $entryId, ?string $targetType = null, int $limit = 80): array
{
    if (!cp_schema_ready($mysqli)) return [];
    $limit = max(1, min(120, $limit));
    $typeSql = '';
    $types = 'ii';
    $params = [$entryId, $entryId];

    if ($targetType) {
        $normalized = cp_normalize_type($targetType);
        if ($normalized) {
            $typeSql = ' WHERE related_entry_type = ?';
            $types .= 's';
            $params[] = $normalized;
        }
    }

    // Un collegamento «nei due sensi» è salvato due volte (A→B e B→A), quindi
    // la stessa voce torna sia in uscita sia in entrata: si tiene una riga sola
    // per voce collegata. Per questo se ne chiedono il doppio.
    $fetch = $limit * 2;
    $sql = "SELECT * FROM (
                SELECT r.id AS relation_id, r.relation_type, r.relation_label, r.relation_label_en, r.weight,
                       'out' AS direction, e.*,
                       e.entry_type AS related_entry_type
                FROM cripsumpedia_relations r
                INNER JOIN cripsumpedia_entries e ON e.id = r.target_entry_id
                WHERE r.source_entry_id = ? AND e.status = 'published'
                UNION ALL
                SELECT r.id AS relation_id, r.relation_type, r.relation_label, r.relation_label_en, r.weight,
                       'in' AS direction, e.*,
                       e.entry_type AS related_entry_type
                FROM cripsumpedia_relations r
                INNER JOIN cripsumpedia_entries e ON e.id = r.source_entry_id
                WHERE r.target_entry_id = ? AND e.status = 'published'
            ) rel
            $typeSql
            ORDER BY weight DESC, direction DESC, updated_at DESC
            LIMIT $fetch";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();

    $unique = [];
    foreach ($rows as $row) {
        $relatedId = (int)($row['id'] ?? 0);
        if ($relatedId > 0 && !isset($unique[$relatedId])) {
            $unique[$relatedId] = $row;
        }
    }
    return array_slice(array_values($unique), 0, $limit);
}

function cp_search_entries(mysqli $mysqli, string $query, string $lang, array $options = []): array
{
    if (!cp_schema_ready($mysqli)) return [];
    $query = trim($query);
    if ($query === '') return [];

    $type = cp_normalize_type($options['type'] ?? null);
    $limit = max(1, min(30, (int)($options['limit'] ?? 12)));
    $like = '%' . addcslashes($query, '%_\\') . '%'; // % e _ cercati come testo
    $whereType = $type ? 'AND e.entry_type = ?' : '';

    $sql = "SELECT DISTINCT e.*,
                   CASE
                       WHEN e.title = ? OR e.title_en = ? THEN 120
                       WHEN e.title LIKE ? OR e.title_en LIKE ? THEN 90
                       WHEN EXISTS (SELECT 1 FROM cripsumpedia_aliases a WHERE a.entry_id = e.id AND (a.alias LIKE ? OR a.alias_en LIKE ?)) THEN 76
                       WHEN e.description LIKE ? OR e.description_en LIKE ? THEN 55
                       ELSE 30
                   END AS relevance
            FROM cripsumpedia_entries e
            LEFT JOIN cripsumpedia_entry_tags et ON et.entry_id = e.id
            LEFT JOIN cripsumpedia_tags t ON t.id = et.tag_id
            WHERE e.status = 'published'
              $whereType
              AND (
                  e.title LIKE ? OR e.title_en LIKE ? OR e.description LIKE ? OR e.description_en LIKE ?
                  OR e.content_md LIKE ? OR e.content_md_en LIKE ?
                  OR t.name LIKE ? OR t.name_en LIKE ?
                  OR EXISTS (SELECT 1 FROM cripsumpedia_aliases a WHERE a.entry_id = e.id AND (a.alias LIKE ? OR a.alias_en LIKE ?))
                  OR EXISTS (SELECT 1 FROM cripsumpedia_quotes q WHERE q.entry_id = e.id AND (q.quote_text LIKE ? OR q.quote_text_en LIKE ?))
              )
            ORDER BY relevance DESC, e.views_count DESC
            LIMIT ?";

    $params = [$query, $query, $like, $like, $like, $like, $like, $like];
    $types = 'ssssssss';
    if ($type) {
        $params[] = $type;
        $types .= 's';
    }
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $limit);
    $types .= 'ssssssssssssi';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();

    foreach ($rows as &$row) {
        $haystack = cp_i18n($row, 'title', $lang) . ' ' . cp_i18n($row, 'description', $lang);
        $distance = levenshtein(mb_strtolower($query, 'UTF-8'), mb_strtolower(mb_substr($haystack, 0, 255, 'UTF-8'), 'UTF-8'));
        $row['_fuzzy_distance'] = $distance;
    }
    unset($row);

    usort($rows, static function (array $a, array $b): int {
        $rel = ((int)($b['relevance'] ?? 0)) <=> ((int)($a['relevance'] ?? 0));
        if ($rel !== 0) return $rel;
        return ((int)($a['_fuzzy_distance'] ?? 999)) <=> ((int)($b['_fuzzy_distance'] ?? 999));
    });

    return $rows;
}

function cp_entry_public(array $entry, string $lang, mysqli $mysqli, bool $withTags = true): array
{
    $tags = $withTags ? cp_fetch_tags($mysqli, (int)$entry['id']) : [];
    return [
        'id' => (int)$entry['id'],
        'type' => $entry['entry_type'],
        'type_label' => cp_type_label((string)$entry['entry_type'], $lang),
        'title' => cp_i18n($entry, 'title', $lang),
        'subtitle' => cp_i18n($entry, 'subtitle', $lang),
        'description' => cp_i18n($entry, 'description', $lang),
        'slug' => $entry['slug'],
        'url' => cp_entry_url($entry, $lang),
        'image' => cp_asset_url($entry['image_url'] ?? null),
        'banner' => cp_asset_url($entry['banner_url'] ?? null, cp_asset_url($entry['image_url'] ?? null)),
        'accent' => cp_valid_color($entry['accent_color'] ?? null),
        'canonical_status' => $entry['canonical_status'] ?? 'canon',
        'rarity' => $entry['rarity'] ?? 'common',
        'importance' => (int)($entry['importance'] ?? 0),
        'views' => (int)($entry['views_count'] ?? 0),
        'created_at' => $entry['created_at'] ?? null,
        'updated_at' => $entry['updated_at'] ?? null,
        'tags' => array_map(static fn(array $tag): array => [
            'id' => (int)$tag['id'],
            'name' => cp_i18n($tag, 'name', $lang),
            'slug' => $tag['slug'],
            'color' => cp_valid_color($tag['color'] ?? null, '#7dd3fc'),
        ], $tags),
    ];
}

function cp_lore_terms(mysqli $mysqli, string $lang): array
{
    static $cache = [];
    if (isset($cache[$lang])) return $cache[$lang];
    if (!cp_schema_ready($mysqli)) return $cache[$lang] = [];

    $terms = [];
    $sql = "SELECT id, entry_type, slug, title, title_en FROM cripsumpedia_entries WHERE status = 'published'";
    if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            foreach (['title', 'title_en'] as $field) {
                $term = trim((string)($row[$field] ?? ''));
                if (mb_strlen($term, 'UTF-8') < 3) continue;
                $key = mb_strtolower($term, 'UTF-8');
                $terms[$key] = [
                    'term' => $term,
                    'id' => (int)$row['id'],
                    'type' => $row['entry_type'],
                    'slug' => $row['slug'],
                    'url' => cp_entry_url($row, $lang),
                ];
            }
        }
    }

    $sql = "SELECT a.alias, a.alias_en, e.id, e.entry_type, e.slug
            FROM cripsumpedia_aliases a
            INNER JOIN cripsumpedia_entries e ON e.id = a.entry_id
            WHERE e.status = 'published'";
    if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            foreach (['alias', 'alias_en'] as $field) {
                $term = trim((string)($row[$field] ?? ''));
                // Un soprannome corto è quasi sempre anche una parola comune.
                if (mb_strlen($term, 'UTF-8') < 4) continue;
                $key = mb_strtolower($term, 'UTF-8');
                $terms[$key] = [
                    'term' => $term,
                    'id' => (int)$row['id'],
                    'type' => $row['entry_type'],
                    'slug' => $row['slug'],
                    'url' => cp_entry_url($row, $lang),
                ];
            }
        }
    }

    uasort($terms, static fn(array $a, array $b): int => mb_strlen($b['term'], 'UTF-8') <=> mb_strlen($a['term'], 'UTF-8'));
    return $cache[$lang] = array_values($terms);
}

function cp_autolink_html(string $html, mysqli $mysqli, string $lang, ?int $currentEntryId = null): string
{
    $terms = array_values(array_filter(cp_lore_terms($mysqli, $lang), static fn(array $term): bool => (int)$term['id'] !== $currentEntryId));
    if (!$terms) return $html;

    $patternParts = [];
    $lookup = [];
    foreach ($terms as $term) {
        $lower = mb_strtolower($term['term'], 'UTF-8');
        $lookup[$lower] = $term;
        $patternParts[] = preg_quote($term['term'], '~');
    }
    $pattern = '~(?<![\p{L}\p{N}_])(' . implode('|', $patternParts) . ')(?![\p{L}\p{N}_])~iu';
    $parts = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if (!$parts) return $html;

    // Come in una wiki: una voce si collega solo la prima volta che compare, e
    // mai dentro un link, un titoletto, il titolo di uno spoiler o del codice.
    $skipDepth = 0;
    $linked = [];
    $out = '';
    foreach ($parts as $part) {
        if (str_starts_with($part, '<')) {
            if (preg_match('~^<(a|h[2-4]|summary|code)\b~i', $part)) $skipDepth++;
            if (preg_match('~^</(a|h[2-4]|summary|code)>~i', $part)) $skipDepth = max(0, $skipDepth - 1);
            $out .= $part;
            continue;
        }
        if ($skipDepth > 0) {
            $out .= $part;
            continue;
        }
        $out .= preg_replace_callback($pattern, static function (array $m) use ($lookup, &$linked): string {
            $key = mb_strtolower($m[1], 'UTF-8');
            if (!isset($lookup[$key])) return $m[0];
            $term = $lookup[$key];
            if (isset($linked[$term['id']])) return $m[0];
            $linked[$term['id']] = true;
            return '<a class="cp-lore-link" href="' . cp_h($term['url']) . '" data-lore-id="' . (int)$term['id'] . '">' . $m[0] . '</a>';
        }, $part) ?? $part;
    }
    return $out;
}

function cp_safe_url(string $url): string
{
    $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');
    if ($url === '') return '#';
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return preg_match('/^https?:\/\//i', $url) ? $url : '#';
    }
    if (str_starts_with($url, '/')) return $url;
    return '#';
}

function cp_render_inline_markdown(string $text): string
{
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = preg_replace_callback('/`([^`]+)`/u', static fn(array $m): string => '<code>' . $m[1] . '</code>', $text) ?? $text;
    $text = preg_replace_callback('/\*\*([^*]+)\*\*/u', static fn(array $m): string => '<strong>' . $m[1] . '</strong>', $text) ?? $text;
    $text = preg_replace_callback('/(?<!\*)\*([^*]+)\*(?!\*)/u', static fn(array $m): string => '<em>' . $m[1] . '</em>', $text) ?? $text;
    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/u', static function (array $m): string {
        $url = cp_safe_url($m[2]);
        return '<a href="' . cp_h($url) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
    }, $text) ?? $text;
    return $text;
}

/**
 * L'id di un titoletto, per l'indice della voce: «Chi è» → «chi-e», unico
 * dentro lo stesso testo («storia», «storia-2»). $reset ricomincia da capo.
 */
function cp_heading_id(string $text, bool $reset = false): string
{
    static $used = [];
    if ($reset) {
        $used = [];
        return '';
    }
    // Le lettere accentate a mano: iconv le traslittera in modo diverso a
    // seconda del sistema («à» → «a» su Linux, «`a» su Windows).
    $plain = strtr(mb_strtolower($text, 'UTF-8'), [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
    ]);
    $base = trim((string)preg_replace('~[^a-z0-9]+~', '-', strtolower((string)(@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $plain) ?: ''))), '-');
    if ($base === '') $base = 'sezione';
    $id = $base;
    for ($n = 2; isset($used[$id]); $n++) {
        $id = $base . '-' . $n;
    }
    $used[$id] = true;
    return $id;
}

/**
 * Markdown → HTML, senza i collegamenti alle altre voci: è quello che si salva
 * in content_html (i collegamenti si aggiungono quando la voce viene mostrata).
 */
function cp_markdown_render(string $markdown, bool $nested = false): string
{
    if (!$nested) cp_heading_id('', true);
    $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown));
    if ($markdown === '') return '';

    $markdown = preg_replace_callback('/\[spoiler(?::([^\]]+))?\](.*?)\[\/spoiler\]/isu', static function (array $m): string {
        $title = trim((string)($m[1] ?? 'Spoiler'));
        $body = trim((string)($m[2] ?? ''));
        return "\n\n:::spoiler " . $title . "\n" . $body . "\n:::\n\n";
    }, $markdown) ?? $markdown;

    $blocks = preg_split("/\n{2,}/", $markdown) ?: [];
    $html = [];

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;

        if (preg_match('/^:::spoiler\s*(.*?)\n(.*?)\n:::$/su', $block, $m)) {
            $summary = cp_h(trim($m[1]) !== '' ? trim($m[1]) : 'Spoiler');
            $body = cp_markdown_render(trim($m[2]), true);
            $html[] = '<details class="cp-spoiler"><summary>' . $summary . '</summary><div>' . $body . '</div></details>';
            continue;
        }

        if (preg_match('/^\[timeline\](.*?)\[\/timeline\]$/su', $block, $m)) {
            $items = [];
            foreach (preg_split('/\n+/', trim($m[1])) ?: [] as $line) {
                $bits = array_map('trim', explode('|', $line, 3));
                if (count($bits) >= 2) {
                    $items[] = '<li><time>' . cp_h($bits[0]) . '</time><strong>' . cp_h($bits[1]) . '</strong><span>' . cp_h($bits[2] ?? '') . '</span></li>';
                }
            }
            if ($items) $html[] = '<ol class="cp-content-timeline">' . implode('', $items) . '</ol>';
            continue;
        }

        if (preg_match('/^(https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{6,}))/u', $block, $m)) {
            $videoId = cp_h($m[2]);
            $html[] = '<div class="cp-embed"><iframe src="https://www.youtube.com/embed/' . $videoId . '" title="YouTube video" loading="lazy" allowfullscreen></iframe></div>';
            continue;
        }

        if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/u', $block, $m)) {
            $src = cp_safe_url($m[2]);
            $alt = cp_h($m[1]);
            $html[] = '<figure class="cp-content-image"><img src="' . cp_h($src) . '" alt="' . $alt . '" loading="lazy"><figcaption>' . $alt . '</figcaption></figure>';
            continue;
        }

        if (preg_match('/^(#{1,4})\s+(.+)$/u', $block, $m)) {
            $level = min(4, max(2, strlen($m[1]) + 1));
            $inner = cp_render_inline_markdown(trim($m[2]));
            $id = cp_heading_id(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $html[] = '<h' . $level . ' id="' . cp_h($id) . '">' . $inner . '</h' . $level . '>';
            continue;
        }

        $lines = preg_split('/\n/', $block) ?: [];
        $allQuotes = $lines && count(array_filter($lines, static fn(string $line): bool => preg_match('/^\s*>/u', $line) === 1)) === count($lines);
        if ($allQuotes) {
            $quote = implode('<br>', array_map(static fn(string $line): string => cp_render_inline_markdown(trim(preg_replace('/^\s*>\s?/u', '', $line) ?? '')), $lines));
            $html[] = '<blockquote>' . $quote . '</blockquote>';
            continue;
        }

        $allBullets = $lines && count(array_filter($lines, static fn(string $line): bool => preg_match('/^\s*[-*]\s+/u', $line) === 1)) === count($lines);
        if ($allBullets) {
            $items = array_map(static fn(string $line): string => '<li>' . cp_render_inline_markdown(trim(preg_replace('/^\s*[-*]\s+/u', '', $line) ?? '')) . '</li>', $lines);
            $html[] = '<ul>' . implode('', $items) . '</ul>';
            continue;
        }

        $allNumbers = $lines && count(array_filter($lines, static fn(string $line): bool => preg_match('/^\s*\d+\.\s+/u', $line) === 1)) === count($lines);
        if ($allNumbers) {
            $items = array_map(static fn(string $line): string => '<li>' . cp_render_inline_markdown(trim(preg_replace('/^\s*\d+\.\s+/u', '', $line) ?? '')) . '</li>', $lines);
            $html[] = '<ol>' . implode('', $items) . '</ol>';
            continue;
        }

        $html[] = '<p>' . implode('<br>', array_map('cp_render_inline_markdown', $lines)) . '</p>';
    }

    return implode("\n", $html);
}

/**
 * $assets: 'public' per le pagine da leggere (foglio e script nuovi, fase 2),
 * 'staff' per editor e pannello, che fino alla fase 4 restano com'erano
 * (staff.css e staff.js sono il vecchio cripsumpedia.css/.js).
 */
function cp_render_head(string $title, string $description, string $lang, string $bodyClass = 'cp-body', ?string $ogImage = null, string $assets = 'public'): void
{
    $staff = $assets === 'staff';
    // Una voce senza banner prende l'anteprima del sito, quella di head-import.
    $ogImage = $ogImage ? cp_asset_url($ogImage) : null;

    // I tag dell'anteprima li stampa head-import: qui si passano solo i valori,
    // altrimenti uscirebbero due volte e i social prenderebbero i primi.
    $ogTitle = $title;
    $ogDescription = $description;
?>
    <?php include __DIR__ . '/../includes/head-import.php'; ?>
    <meta name="theme-color" content="#0a0a0b">
    <title><?= cp_h($title) ?></title>
    <link rel="icon" href="/img/Susremaster.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@<?= $staff ? '400;600;700;800;900' : '400;500;600' ?>&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.2.0/css/all.min.css">
    <?php if ($staff): ?>
    <link rel="stylesheet" href="<?= cripsum_theme_asset('/cripsumpedia/staff.css') ?>">
    <?php endif; ?>
    <?php cripsum_theme_head(); ?>
    <?php if (!$staff): ?>
    <link rel="stylesheet" href="<?= cripsum_theme_asset('/cripsumpedia/cripsumpedia.css') ?>">
    <?php endif; ?>
    <script>
        document.documentElement.classList.add('cp-js');
        window.Cripsumpedia = {
            lang: <?= json_encode($lang) ?>,
            searchEndpoint: '/api/cripsumpedia_search.php',
            saveEndpoint: '/api/cripsumpedia_save.php',
            relationsEndpoint: '/api/cripsumpedia_relations.php',
            csrf: <?= json_encode(cp_csrf_token()) ?>
        };
    </script>
    <script src="<?= cripsum_theme_asset($staff ? '/cripsumpedia/staff.js' : '/cripsumpedia/cripsumpedia.js') ?>" defer></script>
<?php
}

function cp_render_background(): void
{
?>
    <div class="cp-progress" data-cp-progress aria-hidden="true"></div>
<?php
}

function cp_render_topbar(string $lang, string $active = 'home'): void
{
    global $mysqli;
    $nsfw = $nsfw ?? 0;
    $ruolo = $ruolo ?? '';
    include __DIR__ . '/../includes/navbar.php';
}

function cp_render_footer(string $lang): void
{
    $footerLang = $lang;
?>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <div class="cp-toast" data-cp-toast role="status" aria-live="polite" hidden></div>
    <div class="cp-hover-card" data-cp-hover-card aria-hidden="true" hidden></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<?php
}

function cp_render_install_notice(string $lang): void
{
?>
    <section class="cp-install">
        <i class="fa-solid fa-database"></i>
        <h1><?= cp_h(cp_t('install_title', $lang)) ?></h1>
        <p><?= cp_h(cp_t('install_text', $lang)) ?></p>
        <code>migrations/2026_10_17_cripsumpedia_schema.sql</code>
    </section>
<?php
}

function cp_render_breadcrumbs(string $lang, array $items): void
{
?>
    <nav class="cp-breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= cp_h(cp_url('home', [], $lang)) ?>"><?= cp_h(cp_t('pedia', $lang)) ?></a>
        <?php foreach ($items as $item): ?>
            <i class="fa-solid fa-chevron-right"></i>
            <?php if (!empty($item['url'])): ?>
                <a href="<?= cp_h($item['url']) ?>"><?= cp_h($item['label']) ?></a>
            <?php else: ?>
                <span><?= cp_h($item['label']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
<?php
}

function cp_seeded_daily_index(int $count): int
{
    if ($count <= 0) return 0;
    return (int)(hexdec(substr(hash('crc32b', date('Y-m-d')), 0, 6)) % $count);
}

function cp_highlight(string $text, string $query): string
{
    $query = trim($query);
    if ($query === '') return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $pattern = '/' . preg_quote(htmlspecialchars($query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '/') . '/i';
    return preg_replace_callback($pattern, static fn($m) => '<mark class="cp-highlight">' . $m[0] . '</mark>', $escaped) ?? $escaped;
}
