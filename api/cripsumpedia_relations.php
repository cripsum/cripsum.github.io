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

    $rawRelations = cp_fetch_relations($mysqli, $entryId, null, 120);
    // Deduplicate: bidirectional relations may appear in both directions
    $seenTids = [];
    $uniqueRels = [];
    foreach ($rawRelations as $r) {
        $tid = (int)$r['id'];
        if (!isset($seenTids[$tid])) {
            $seenTids[$tid] = true;
            $uniqueRels[] = $r;
        }
    }

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
    }, $uniqueRels);

    cp_json(['ok' => true, 'relations' => $relations]);
}

if ($action === 'save') {
    $entryId = (int)($input['entry_id'] ?? 0);
    $relations = $input['relations'] ?? [];
    if (!is_array($relations)) {
        $relations = json_decode((string)$relations, true);
    }
    if ($entryId <= 0 || !is_array($relations)) cp_json(['ok' => false, 'message' => 'Payload non valido.'], 400);

    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_relations WHERE source_entry_id = ? OR (target_entry_id = ? AND is_bidirectional = 1)");
    if ($stmt) {
        $stmt->bind_param('ii', $entryId, $entryId);
        $stmt->execute();
        $stmt->close();
    }

    $saved = 0;
    foreach ($relations as $relation) {
        if (!is_array($relation)) continue;
        $targetId = (int)($relation['target_id'] ?? 0);
        if ($targetId <= 0 || $targetId === $entryId) continue;
        $relationType = preg_replace('/[^a-z0-9_\-]/i', '', (string)($relation['relation_type'] ?? 'related')) ?: 'related';
        $label = trim((string)($relation['relation_label'] ?? ''));
        $labelEn = trim((string)($relation['relation_label_en'] ?? ''));
        $weight = max(0, min(100, (int)($relation['weight'] ?? 50)));
        $bidirectional = array_key_exists('bidirectional', $relation) ? (!empty($relation['bidirectional']) ? 1 : 0) : 1;

        $stmt = $mysqli->prepare("INSERT IGNORE INTO cripsumpedia_relations
            (source_entry_id, target_entry_id, relation_type, relation_label, relation_label_en, weight, is_bidirectional, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if (!$stmt) continue;
        $stmt->bind_param('iisssii', $entryId, $targetId, $relationType, $label, $labelEn, $weight, $bidirectional);
        $stmt->execute();
        $stmt->close();
        $saved++;

        if ($bidirectional === 1) {
            $stmt = $mysqli->prepare("INSERT IGNORE INTO cripsumpedia_relations
                (source_entry_id, target_entry_id, relation_type, relation_label, relation_label_en, weight, is_bidirectional, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
            if ($stmt) {
                $stmt->bind_param('iisssi', $targetId, $entryId, $relationType, $label, $labelEn, $weight);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    cp_json(['ok' => true, 'saved' => $saved]);
}

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
