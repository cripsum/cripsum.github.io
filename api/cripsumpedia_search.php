<?php
declare(strict_types=1);

require_once __DIR__ . '/../cripsumpedia/_bootstrap.php';

$lang = cp_detect_lang();

if (!cp_schema_ready($mysqli)) {
    cp_json(['ok' => false, 'message' => cp_t('install_title', $lang), 'results' => []], 503);
}

$action = trim((string)($_GET['action'] ?? 'search'));

if ($action === 'preview') {
    $id = (int)($_GET['id'] ?? 0);
    $entry = $id > 0 ? cp_fetch_entry($mysqli, null, (string)$id, false) : null;
    if (!$entry) cp_json(['ok' => false, 'message' => cp_t('no_results', $lang)], 404);

    $public = cp_entry_public($entry, $lang, $mysqli);
    $relations = cp_fetch_relations($mysqli, (int)$entry['id'], null, 5);
    cp_json([
        'ok' => true,
        'entry' => $public,
        'html' => [
            'title' => $public['title'],
            'description' => cp_excerpt($public['description'], 180),
            'image' => $public['image'],
            'type' => $public['type_label'],
            'relations' => array_map(static fn(array $row): string => cp_i18n($row, 'title', $lang), $relations),
        ],
    ]);
}

if ($action === 'random') {
    $type = cp_normalize_type($_GET['type'] ?? null);
    $rows = cp_fetch_entries($mysqli, ['type' => $type, 'order' => 'random', 'limit' => 1]);
    if (!$rows) cp_json(['ok' => false, 'message' => cp_t('no_results', $lang)], 404);
    $entry = cp_entry_public($rows[0], $lang, $mysqli, false);
    cp_json(['ok' => true, 'entry' => $entry, 'url' => $entry['url']]);
}

if ($action === 'tags') {
    $tags = array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'name' => cp_i18n($row, 'name', $lang),
        'slug' => $row['slug'],
        'color' => cp_valid_color($row['color'] ?? null, '#7dd3fc'),
    ], cp_fetch_tags($mysqli));
    cp_json(['ok' => true, 'tags' => $tags]);
}

$query = trim((string)($_GET['q'] ?? ''));
$type = cp_normalize_type($_GET['type'] ?? null);
$limit = max(1, min(30, (int)($_GET['limit'] ?? 12)));
$rows = $query !== ''
    ? cp_search_entries($mysqli, $query, $lang, ['type' => $type, 'limit' => $limit])
    : cp_fetch_entries($mysqli, ['type' => $type, 'order' => 'trending', 'limit' => $limit]);

$results = array_map(static fn(array $entry): array => cp_entry_public($entry, $lang, $mysqli), $rows);

cp_json([
    'ok' => true,
    'query' => $query,
    'results' => $results,
    'count' => count($results),
]);
