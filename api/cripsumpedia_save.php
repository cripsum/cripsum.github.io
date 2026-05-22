<?php
declare(strict_types=1);

require_once __DIR__ . '/../cripsumpedia/_bootstrap.php';

$lang = cp_detect_lang();
$input = cp_read_input();
$action = (string)($input['action'] ?? $_GET['action'] ?? '');

if (!cp_schema_ready($mysqli)) {
    cp_json(['ok' => false, 'message' => cp_t('install_title', $lang)], 503);
}

function cp_api_lines(string $value): array
{
    $lines = preg_split('/\R/u', trim($value)) ?: [];
    $lines = array_map(static fn(string $line): string => trim($line), $lines);
    return array_values(array_filter($lines, static fn(string $line): bool => $line !== ''));
}

function cp_api_csv(string $value): array
{
    $parts = array_map(static fn(string $part): string => trim($part), explode(',', $value));
    return array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
}

function cp_api_unique_slug(mysqli $mysqli, string $type, string $slug, int $ignoreId = 0): string
{
    $base = cp_slugify($slug);
    $candidate = $base;
    $i = 2;
    while (true) {
        $stmt = $mysqli->prepare("SELECT id FROM cripsumpedia_entries WHERE entry_type = ? AND slug = ? AND id <> ? LIMIT 1");
        if (!$stmt) return $candidate;
        $stmt->bind_param('ssi', $type, $candidate, $ignoreId);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$exists) return $candidate;
        $candidate = $base . '-' . $i;
        $i++;
    }
}

function cp_api_sync_tags(mysqli $mysqli, int $entryId, array $namesIt, array $namesEn): void
{
    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_entry_tags WHERE entry_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $stmt->close();
    }

    $max = max(count($namesIt), count($namesEn));
    for ($i = 0; $i < $max; $i++) {
        $name = trim((string)($namesIt[$i] ?? $namesEn[$i] ?? ''));
        $nameEn = trim((string)($namesEn[$i] ?? ''));
        if ($name === '' && $nameEn === '') continue;
        if ($name === '') $name = $nameEn;
        $slug = cp_slugify($name);
        $color = '#7dd3fc';

        $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_tags (name, name_en, slug, color, created_at, updated_at)
                                  VALUES (?, ?, ?, ?, NOW(), NOW())
                                  ON DUPLICATE KEY UPDATE
                                      name = VALUES(name),
                                      name_en = IF(VALUES(name_en) = '', name_en, VALUES(name_en)),
                                      updated_at = NOW()");
        if (!$stmt) continue;
        $stmt->bind_param('ssss', $name, $nameEn, $slug, $color);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("SELECT id FROM cripsumpedia_tags WHERE slug = ? LIMIT 1");
        if (!$stmt) continue;
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $tagId = (int)($row['id'] ?? 0);
        if ($tagId <= 0) continue;

        $stmt = $mysqli->prepare("INSERT IGNORE INTO cripsumpedia_entry_tags (entry_id, tag_id) VALUES (?, ?)");
        if (!$stmt) continue;
        $stmt->bind_param('ii', $entryId, $tagId);
        $stmt->execute();
        $stmt->close();
    }
}

function cp_api_sync_aliases(mysqli $mysqli, int $entryId, array $aliasesIt, array $aliasesEn): void
{
    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_aliases WHERE entry_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $stmt->close();
    }

    $max = max(count($aliasesIt), count($aliasesEn));
    for ($i = 0; $i < $max; $i++) {
        $alias = trim((string)($aliasesIt[$i] ?? $aliasesEn[$i] ?? ''));
        $aliasEn = trim((string)($aliasesEn[$i] ?? ''));
        if ($alias === '' && $aliasEn === '') continue;
        if ($alias === '') $alias = $aliasEn;

        $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_aliases (entry_id, alias, alias_en, normalized_alias, created_at)
                                  VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) continue;
        $normalized = cp_slugify($alias);
        $stmt->bind_param('isss', $entryId, $alias, $aliasEn, $normalized);
        $stmt->execute();
        $stmt->close();
    }
}

function cp_api_sync_quotes(mysqli $mysqli, int $entryId, array $quotesIt, array $quotesEn): void
{
    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_quotes WHERE entry_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $stmt->close();
    }

    $max = max(count($quotesIt), count($quotesEn));
    for ($i = 0; $i < $max; $i++) {
        $quote = trim((string)($quotesIt[$i] ?? $quotesEn[$i] ?? ''));
        $quoteEn = trim((string)($quotesEn[$i] ?? ''));
        if ($quote === '' && $quoteEn === '') continue;
        if ($quote === '') $quote = $quoteEn;

        $speaker = '';
        $speakerEn = '';
        if (str_contains($quote, '|')) {
            [$quote, $speaker] = array_map('trim', explode('|', $quote, 2));
        }
        if (str_contains($quoteEn, '|')) {
            [$quoteEn, $speakerEn] = array_map('trim', explode('|', $quoteEn, 2));
        }

        $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_quotes (entry_id, quote_text, quote_text_en, speaker, speaker_en, is_featured, created_at)
                                  VALUES (?, ?, ?, ?, ?, 0, NOW())");
        if (!$stmt) continue;
        $stmt->bind_param('issss', $entryId, $quote, $quoteEn, $speaker, $speakerEn);
        $stmt->execute();
        $stmt->close();
    }
}

function cp_api_sync_relations(mysqli $mysqli, int $entryId, array $relations): void
{
    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_relations WHERE source_entry_id = ? OR (target_entry_id = ? AND is_bidirectional = 1)");
    if ($stmt) {
        $stmt->bind_param('ii', $entryId, $entryId);
        $stmt->execute();
        $stmt->close();
    }

    foreach ($relations as $relation) {
        if (!is_array($relation)) continue;
        $targetId = (int)($relation['target_id'] ?? $relation['id'] ?? 0);
        if ($targetId <= 0 || $targetId === $entryId) continue;

        $stmt = $mysqli->prepare("SELECT id FROM cripsumpedia_entries WHERE id = ? LIMIT 1");
        if (!$stmt) continue;
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$exists) continue;

        $relationType = preg_replace('/[^a-z0-9_\-]/i', '', (string)($relation['relation_type'] ?? 'related')) ?: 'related';
        $label = trim((string)($relation['relation_label'] ?? ''));
        $labelEn = trim((string)($relation['relation_label_en'] ?? ''));
        $weight = max(0, min(100, (int)($relation['weight'] ?? 50)));
        $bidirectional = array_key_exists('bidirectional', $relation) ? (!empty($relation['bidirectional']) ? 1 : 0) : 1;

        $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_relations
            (source_entry_id, target_entry_id, relation_type, relation_label, relation_label_en, weight, is_bidirectional, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE relation_label = VALUES(relation_label), relation_label_en = VALUES(relation_label_en), weight = VALUES(weight), is_bidirectional = VALUES(is_bidirectional), updated_at = NOW()");
        if ($stmt) {
            $stmt->bind_param('iisssii', $entryId, $targetId, $relationType, $label, $labelEn, $weight, $bidirectional);
            $stmt->execute();
            $stmt->close();
        }

        if ($bidirectional === 1) {
            $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_relations
                (source_entry_id, target_entry_id, relation_type, relation_label, relation_label_en, weight, is_bidirectional, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE relation_label = VALUES(relation_label), relation_label_en = VALUES(relation_label_en), weight = VALUES(weight), is_bidirectional = 1, updated_at = NOW()");
            if ($stmt) {
                $stmt->bind_param('iisssi', $targetId, $entryId, $relationType, $label, $labelEn, $weight);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

if ($action === 'react') {
    $entryId = (int)($input['entry_id'] ?? 0);
    $reaction = preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['reaction'] ?? 'hype')) ?: 'hype';
    if ($entryId <= 0 || !cp_table_exists($mysqli, 'cripsumpedia_reactions')) {
        cp_json(['ok' => false, 'message' => 'Reaction non disponibile.'], 400);
    }
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $sessionHash = hash('sha256', session_id() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_reactions WHERE entry_id = ? AND session_hash = ? AND reaction = ?");
    if ($stmt) {
        $stmt->bind_param('iss', $entryId, $sessionHash, $reaction);
        $stmt->execute();
        $removed = $stmt->affected_rows > 0;
        $stmt->close();
        if ($removed) {
            $mysqli->query("UPDATE cripsumpedia_entries SET reactions_count = IF(reactions_count > 0, reactions_count - 1, 0) WHERE id = " . (int)$entryId);
            cp_json(['ok' => true, 'state' => 'removed']);
        }
    }

    $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_reactions (entry_id, user_id, session_hash, reaction, created_at) VALUES (?, ?, ?, ?, NOW())");
    if (!$stmt) cp_json(['ok' => false, 'message' => 'Errore reaction.'], 500);
    $stmt->bind_param('iiss', $entryId, $userId, $sessionHash, $reaction);
    $stmt->execute();
    $stmt->close();
    $mysqli->query("UPDATE cripsumpedia_entries SET reactions_count = reactions_count + 1, trending_score = trending_score + 1 WHERE id = " . (int)$entryId);
    cp_json(['ok' => true, 'state' => 'added']);
}

if ($action === 'favorite') {
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        cp_json(['ok' => false, 'message' => 'Login richiesto per i preferiti.'], 401);
    }
    $entryId = (int)($input['entry_id'] ?? 0);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($entryId <= 0 || !cp_table_exists($mysqli, 'cripsumpedia_favorites')) {
        cp_json(['ok' => false, 'message' => 'Preferiti non disponibili.'], 400);
    }

    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_favorites WHERE entry_id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $entryId, $userId);
        $stmt->execute();
        $removed = $stmt->affected_rows > 0;
        $stmt->close();
        if ($removed) {
            $mysqli->query("UPDATE cripsumpedia_entries SET favorites_count = IF(favorites_count > 0, favorites_count - 1, 0) WHERE id = " . (int)$entryId);
            cp_json(['ok' => true, 'state' => 'removed']);
        }
    }

    $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_favorites (entry_id, user_id, created_at) VALUES (?, ?, NOW())");
    if (!$stmt) cp_json(['ok' => false, 'message' => 'Errore preferito.'], 500);
    $stmt->bind_param('ii', $entryId, $userId);
    $stmt->execute();
    $stmt->close();
    $mysqli->query("UPDATE cripsumpedia_entries SET favorites_count = favorites_count + 1, trending_score = trending_score + 2 WHERE id = " . (int)$entryId);
    cp_json(['ok' => true, 'state' => 'added']);
}

cp_require_admin(true);

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
if (!cp_validate_csrf(is_string($csrf) ? $csrf : '')) {
    cp_json(['ok' => false, 'message' => 'Sessione scaduta. Ricarica la pagina.'], 419);
}

if ($action === 'upload_media') {
    $uploaded = [];
    $files = $_FILES['files'] ?? null;
    if (!$files) cp_json(['ok' => false, 'message' => 'Nessun file ricevuto.'], 400);

    $baseDir = realpath(__DIR__ . '/../img');
    if (!$baseDir) cp_json(['ok' => false, 'message' => 'Cartella img non trovata.'], 500);
    $targetDir = $baseDir . DIRECTORY_SEPARATOR . 'cripsumpedia' . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        cp_json(['ok' => false, 'message' => 'Impossibile creare cartella upload.'], 500);
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($names as $idx => $name) {
        if (($errors[$idx] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        if ((int)($sizes[$idx] ?? 0) > 6 * 1024 * 1024) continue;
        $tmp = (string)($tmpNames[$idx] ?? '');
        $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
        $mime = $finfo->file($tmp) ?: '';
        if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) continue;
        $fileName = cp_slugify(pathinfo((string)$name, PATHINFO_FILENAME)) . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        if (!move_uploaded_file($tmp, $target)) continue;
        $uploaded[] = '/img/cripsumpedia/' . date('Y') . '/' . date('m') . '/' . $fileName;
    }

    cp_json(['ok' => true, 'files' => $uploaded]);
}

if ($action === 'delete_entry') {
    $entryId = (int)($input['id'] ?? 0);
    if ($entryId <= 0) cp_json(['ok' => false, 'message' => 'ID non valido.'], 400);
    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_entries WHERE id = ?");
    if (!$stmt) cp_json(['ok' => false, 'message' => 'Errore preparazione query.'], 500);
    $stmt->bind_param('i', $entryId);
    $ok = $stmt->execute();
    $stmt->close();
    cp_json(['ok' => $ok]);
}

if ($action !== 'save_entry') {
    cp_json(['ok' => false, 'message' => 'Azione non valida.'], 400);
}

$entryId = (int)($input['id'] ?? 0);
$entryType = cp_normalize_type($input['entry_type'] ?? 'person') ?? 'person';
$status = in_array(($input['status'] ?? 'draft'), ['draft', 'published', 'archived'], true) ? (string)$input['status'] : 'draft';
$canonical = in_array(($input['canonical_status'] ?? 'canon'), ['canon', 'non_canon', 'disputed'], true) ? (string)$input['canonical_status'] : 'canon';
$rarity = in_array(($input['rarity'] ?? 'common'), ['common', 'rare', 'epic', 'legendary', 'mythic', 'cursed'], true) ? (string)$input['rarity'] : 'common';
$title = trim((string)($input['title'] ?? ''));
$titleEn = trim((string)($input['title_en'] ?? ''));
if ($title === '' && $titleEn === '') cp_json(['ok' => false, 'message' => 'Titolo richiesto.'], 422);
if ($title === '') $title = $titleEn;
$slug = cp_api_unique_slug($mysqli, $entryType, (string)($input['slug'] ?? $title), $entryId);
$importance = max(0, min(100, (int)($input['importance'] ?? 50)));
$featured = !empty($input['featured']) ? 1 : 0;
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$fields = [
    'entry_type' => $entryType,
    'slug' => $slug,
    'title' => $title,
    'title_en' => $titleEn,
    'subtitle' => trim((string)($input['subtitle'] ?? '')),
    'subtitle_en' => trim((string)($input['subtitle_en'] ?? '')),
    'description' => trim((string)($input['description'] ?? '')),
    'description_en' => trim((string)($input['description_en'] ?? '')),
    'content_md' => trim((string)($input['content_md'] ?? '')),
    'content_md_en' => trim((string)($input['content_md_en'] ?? '')),
    'image_url' => trim((string)($input['image_url'] ?? '')),
    'banner_url' => trim((string)($input['banner_url'] ?? '')),
    'accent_color' => cp_valid_color((string)($input['accent_color'] ?? '#42f5b0')),
    'canonical_status' => $canonical,
    'rarity' => $rarity,
    'lore_date' => trim((string)($input['lore_date'] ?? '')),
    'real_date' => trim((string)($input['real_date'] ?? '')) ?: null,
    'importance' => $importance,
    'featured' => $featured,
    'seo_title' => trim((string)($input['seo_title'] ?? '')),
    'seo_title_en' => trim((string)($input['seo_title_en'] ?? '')),
    'seo_description' => trim((string)($input['seo_description'] ?? '')),
    'seo_description_en' => trim((string)($input['seo_description_en'] ?? '')),
    'status' => $status,
    'updated_by' => $userId,
];

$types = 'sssssssssssssssss' . 'ii' . 'sssss' . 'i';
$values = array_values($fields);

if ($entryId > 0) {
    $set = implode(', ', array_map(static fn(string $field): string => "$field = ?", array_keys($fields)));
    $sql = "UPDATE cripsumpedia_entries SET $set, updated_at = NOW() WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) cp_json(['ok' => false, 'message' => 'Errore query update: ' . $mysqli->error], 500);
    $values[] = $entryId;
    $types .= 'i';
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) cp_json(['ok' => false, 'message' => 'Salvataggio non riuscito.'], 500);
} else {
    $fields['created_by'] = $userId;
    $columns = implode(', ', array_keys($fields));
    $placeholders = rtrim(str_repeat('?, ', count($fields)), ', ');
    $sql = "INSERT INTO cripsumpedia_entries ($columns, created_at, updated_at) VALUES ($placeholders, NOW(), NOW())";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) cp_json(['ok' => false, 'message' => 'Errore query insert: ' . $mysqli->error], 500);
    $values = array_values($fields);
    $types .= 'i';
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    if (!$ok) {
        $message = $stmt->error ?: 'Inserimento non riuscito.';
        $stmt->close();
        cp_json(['ok' => false, 'message' => $message], 500);
    }
    $entryId = (int)$stmt->insert_id;
    $stmt->close();
}

cp_api_sync_tags($mysqli, $entryId, cp_api_csv((string)($input['tags'] ?? '')), cp_api_csv((string)($input['tags_en'] ?? '')));
cp_api_sync_aliases($mysqli, $entryId, cp_api_lines((string)($input['aliases'] ?? '')), cp_api_lines((string)($input['aliases_en'] ?? '')));
cp_api_sync_quotes($mysqli, $entryId, cp_api_lines((string)($input['quotes'] ?? '')), cp_api_lines((string)($input['quotes_en'] ?? '')));

$relationsJson = (string)($input['relations_json'] ?? '[]');
$relations = json_decode($relationsJson, true);
cp_api_sync_relations($mysqli, $entryId, is_array($relations) ? $relations : []);

$entry = cp_fetch_entry($mysqli, null, (string)$entryId, true);
cp_json([
    'ok' => true,
    'id' => $entryId,
    'slug' => $slug,
    'url' => $entry ? cp_entry_url($entry, $lang) : cp_url('admin', [], $lang),
    'message' => 'Voce salvata.',
]);
