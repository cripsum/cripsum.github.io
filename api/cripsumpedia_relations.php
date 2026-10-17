<?php

declare(strict_types=1);

require_once __DIR__ . '/../cripsumpedia/_bootstrap.php';

$lang = cp_detect_lang();
cp_require_admin(true);

if (!cp_schema_ready($mysqli)) {
    cp_json(['ok' => false, 'message' => cp_t('install_title', $lang)], 503);
}

$input = cp_read_input();
$action = (string)($input['action'] ?? $_GET['action'] ?? 'search');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
    if (!cp_validate_csrf(is_string($csrf) ? $csrf : '')) {
        cp_json(['ok' => false, 'message' => 'Sessione scaduta. Ricarica la pagina.'], 419);
    }
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

if ($action === 'get') {
    $entryId = (int)($_GET['entry_id'] ?? $input['entry_id'] ?? 0);
    if ($entryId <= 0) cp_json(['ok' => false, 'message' => 'ID non valido.'], 400);

    $relations = array_map(static function (array $relation) use ($mysqli, $lang): array {
        $item = cp_entry_public($relation, $lang, $mysqli, false);
        return [
            'relation_id' => (int)($relation['relation_id'] ?? 0),
            'target_id' => (int)$relation['id'],
            'title' => $item['title'],
            'type' => $item['type'],
            'type_label' => $item['type_label'],
            'url' => $item['url'],
            'image' => $item['image'],
            'relation_type' => $relation['relation_type'] ?? 'related',
            'relation_label' => cp_i18n($relation, 'relation_label', $lang),
            'weight' => (int)($relation['weight'] ?? 50),
        ];
    }, cp_fetch_relations($mysqli, $entryId, null, 120));

    cp_json(['ok' => true, 'relations' => $relations]);
}

// I collegamenti si salvano solo insieme alla voce (api/cripsumpedia_save.php),
// così ogni cambiamento finisce nella cronologia.

$query = trim((string)($_GET['q'] ?? ''));
$type = cp_normalize_type($_GET['type'] ?? null);
$exclude = (int)($_GET['exclude'] ?? 0);
$rows = $query !== ''
    ? cp_search_entries($mysqli, $query, $lang, ['type' => $type, 'limit' => 18])
    : cp_fetch_entries($mysqli, ['type' => $type, 'order' => 'latest', 'limit' => 18]);

$results = [];
foreach ($rows as $entry) {
    if ((int)$entry['id'] === $exclude) continue;
    $results[] = cp_entry_public($entry, $lang, $mysqli, false);
}

cp_json(['ok' => true, 'results' => $results]);
