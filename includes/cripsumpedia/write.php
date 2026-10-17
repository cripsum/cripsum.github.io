<?php
/**
 * Cripsumpedia — scrivere una voce (fase 4).
 *
 * Tutto passa dalla stessa forma di dati delle versioni (cp_entry_snapshot):
 * il modulo «Scrivi» manda i campi, cp_data_from_input() li controlla e li
 * mette in quella forma, cp_apply_data() li scrive nella voce. Così una voce
 * salvata dallo staff, una proposta approvata e una versione rimessa dalla
 * cronologia fanno esattamente la stessa strada.
 */

const CP_RELATION_TYPES = ['related', 'friend', 'rival', 'protagonist', 'present', 'created', 'invented', 'appears', 'cause'];
const CP_LIMITS = [
    'title' => 120, 'subtitle' => 120, 'content' => 20000, 'lore_date' => 80, 'summary' => 200,
    'aliases' => 10, 'alias' => 60, 'tags' => 8, 'tag' => 40, 'quotes' => 10, 'quote' => 300, 'speaker' => 80,
    'relations' => 20, 'relation_label' => 60,
];

/** Testo su una riga: niente caratteri di controllo, spazi compressi. */
function cp_clean_line(mixed $value): string
{
    $value = (string)preg_replace('~[\x00-\x1F\x7F]+~u', ' ', (string)$value);
    return trim((string)preg_replace('~\s+~u', ' ', $value));
}

/** Testo lungo: a capo come \n, niente caratteri di controllo tranne a capo e tab. */
function cp_clean_text(mixed $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", (string)$value);
    return trim((string)preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~u', '', $value));
}

function cp_len(string $value): int
{
    return mb_strlen($value, 'UTF-8');
}

/** Una foto caricata dalla Cripsumpedia (gli altri indirizzi li può mettere solo lo staff). */
function cp_is_pedia_upload(string $path): bool
{
    return preg_match('~^/img/cripsumpedia/\d{4}/\d{2}/[a-z0-9-]+\.(jpe?g|png|gif|webp)$~', $path) === 1;
}

/** I dati di una voce nuova, prima che qualcuno ci scriva. */
function cp_empty_data(string $type): array
{
    $fields = array_fill_keys(CP_REVISION_FIELDS, '');
    $fields['real_date'] = null;
    $fields['profile_user_id'] = null;
    $fields['featured'] = 0;
    $fields['importance'] = 50;
    $fields['canonical_status'] = 'canon';
    $fields['status'] = 'published';
    $fields['rarity'] = 'common';
    $fields['accent_color'] = '#2f6bff';
    return ['v' => 1, 'entry_type' => $type, 'slug' => '', 'fields' => $fields, 'aliases' => [], 'tags' => [], 'quotes' => [], 'relations' => []];
}

/**
 * Dal modulo «Scrivi» ai dati di una versione.
 *
 * $base sono i dati da cui si parte (la voce com'è, o una voce vuota): i
 * campi che il modulo non tocca (quelli di prima: rarità, colore, SEO…)
 * restano come sono. Restituisce ['data' => …, 'errors' => [campo => frase]].
 */
function cp_data_from_input(mysqli $mysqli, array $input, array $base, bool $staff, string $lang): array
{
    $errors = [];
    $data = $base;
    $f = &$data['fields'];
    $en = $lang === 'en';
    $say = static fn(string $it, string $enText): string => $en ? $enText : $it;

    if ($staff || $base['slug'] === '') {
        $type = cp_normalize_type($input['entry_type'] ?? $base['entry_type']);
        if (!$type) $errors['entry_type'] = $say('Scegli di cosa parla la voce.', 'Choose what the entry is about.');
        else $data['entry_type'] = $type;
    }

    foreach (['title' => true, 'title_en' => false] as $key => $required) {
        $value = cp_clean_line($input[$key] ?? '');
        if ($required && $value === '') $errors[$key] = $say('Serve un titolo.', 'The entry needs a title.');
        elseif (cp_len($value) > CP_LIMITS['title']) $errors[$key] = $say('Titolo troppo lungo (massimo 120 caratteri).', 'Title too long (120 characters max).');
        $f[$key] = $value;
    }
    foreach (['subtitle', 'subtitle_en'] as $key) {
        $value = cp_clean_line($input[$key] ?? '');
        if (cp_len($value) > CP_LIMITS['subtitle']) $errors[$key] = $say('«In breve» è una riga sola: massimo 120 caratteri.', '“In short” is one line: 120 characters max.');
        $f[$key] = $value;
    }
    foreach (['content_md', 'content_md_en'] as $key) {
        $value = cp_clean_text($input[$key] ?? '');
        if (cp_len($value) > CP_LIMITS['content']) $errors[$key] = $say('Testo troppo lungo (massimo 20.000 caratteri).', 'Text too long (20,000 characters max).');
        $f[$key] = $value;
    }
    // La descrizione di prima l'editor la porta nel testo o in «in breve».
    $f['description'] = '';
    $f['description_en'] = '';

    // Una foto sola: quella del modulo. Il banner di prima non si usa più.
    $image = trim((string)($input['image_url'] ?? ''));
    if ($image !== '' && $image !== (string)($base['fields']['image_url'] ?? '') && $image !== (string)($base['fields']['banner_url'] ?? '')
        && !cp_is_pedia_upload($image) && !($staff && preg_match('~^/img/[A-Za-z0-9_./ -]+$~', $image) && !str_contains($image, '..'))) {
        $errors['image_url'] = $say('La foto va caricata dal modulo.', 'Upload the photo from the form.');
    }
    $f['image_url'] = $image;
    $f['banner_url'] = '';

    $lore = cp_clean_line($input['lore_date'] ?? '');
    if (cp_len($lore) > CP_LIMITS['lore_date']) $errors['lore_date'] = $say('Data lore troppo lunga (massimo 80 caratteri).', 'Lore date too long (80 characters max).');
    $f['lore_date'] = $lore;
    $date = trim((string)($input['real_date'] ?? ''));
    if ($date !== '') {
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date || (int)$parsed->format('Y') < 1900 || $parsed > new DateTime('+1 day')) {
            $errors['real_date'] = $say('Data non valida.', 'Invalid date.');
            $date = '';
        }
    }
    $f['real_date'] = $date !== '' ? $date : null;

    // Il profilo sul sito, solo per una persona e solo dallo staff: collegare
    // una voce all'account di qualcuno lo decide lo staff.
    if ($data['entry_type'] !== 'person') {
        $f['profile_user_id'] = null;
    } elseif ($staff && array_key_exists('profile_username', $input)) {
        $username = ltrim(cp_clean_line($input['profile_username']), '@');
        $f['profile_user_id'] = null;
        if ($username !== '') {
            $stmt = $mysqli->prepare('SELECT id FROM utenti WHERE username = ? LIMIT 1');
            $found = 0;
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $found = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
                $stmt->close();
            }
            if ($found <= 0) $errors['profile_username'] = $say('Nessun account con questo username.', 'No account with this username.');
            else $f['profile_user_id'] = $found;
        }
    }

    if ($staff) {
        $f['status'] = in_array($input['status'] ?? '', ['published', 'draft'], true) ? (string)$input['status'] : ($f['status'] === 'draft' ? 'draft' : 'published');
        $f['canonical_status'] = in_array($input['canonical_status'] ?? '', ['canon', 'non_canon', 'disputed'], true) ? (string)$input['canonical_status'] : (string)$f['canonical_status'];
        $f['featured'] = !empty($input['featured']) ? 1 : 0;
    } elseif ($base['slug'] === '') {
        $f['status'] = 'published';
    }

    // Liste: soprannomi, tag, citazioni, collegamenti.
    $pairs = static function (mixed $list, int $max, int $maxLen) use (&$errors, $say): array {
        $out = [];
        $seen = [];
        foreach (is_array($list) ? $list : [] as $item) {
            $it = cp_clean_line(is_array($item) ? ($item['it'] ?? '') : $item);
            $enText = cp_clean_line(is_array($item) ? ($item['en'] ?? '') : '');
            if ($it === '' && $enText === '') continue;
            if ($it === '') $it = $enText;
            $key = mb_strtolower($it, 'UTF-8');
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = ['it' => mb_substr($it, 0, $maxLen, 'UTF-8'), 'en' => mb_substr($enText, 0, $maxLen, 'UTF-8')];
        }
        return array_slice($out, 0, $max);
    };
    $data['aliases'] = $pairs($input['aliases'] ?? [], CP_LIMITS['aliases'], CP_LIMITS['alias']);
    $data['tags'] = $pairs($input['tags'] ?? [], CP_LIMITS['tags'], CP_LIMITS['tag']);

    $quotes = [];
    foreach (is_array($input['quotes'] ?? null) ? $input['quotes'] : [] as $quote) {
        if (!is_array($quote)) continue;
        $text = cp_clean_line($quote['text'] ?? '');
        $textEn = cp_clean_line($quote['text_en'] ?? '');
        if ($text === '' && $textEn === '') continue;
        if (cp_len($text) > CP_LIMITS['quote'] || cp_len($textEn) > CP_LIMITS['quote']) {
            $errors['quotes'] = $say('Una citazione è troppo lunga (massimo 300 caratteri).', 'A quote is too long (300 characters max).');
        }
        $quotes[] = [
            'text' => mb_substr($text !== '' ? $text : $textEn, 0, CP_LIMITS['quote'], 'UTF-8'),
            'text_en' => mb_substr($textEn, 0, CP_LIMITS['quote'], 'UTF-8'),
            'speaker' => mb_substr(cp_clean_line($quote['speaker'] ?? ''), 0, CP_LIMITS['speaker'], 'UTF-8'),
            'speaker_en' => mb_substr(cp_clean_line($quote['speaker_en'] ?? ''), 0, CP_LIMITS['speaker'], 'UTF-8'),
            'featured' => !empty($quote['featured']) ? 1 : 0,
        ];
    }
    $data['quotes'] = array_slice($quotes, 0, CP_LIMITS['quotes']);

    $relations = [];
    $ids = [];
    foreach (is_array($input['relations'] ?? null) ? $input['relations'] : [] as $relation) {
        if (!is_array($relation)) continue;
        $target = (int)($relation['target_id'] ?? 0);
        $kind = (string)($relation['relation_type'] ?? 'related');
        if ($target <= 0 || isset($ids[$target])) continue;
        $ids[$target] = true;
        $relations[] = [
            'target_id' => $target,
            'relation_type' => in_array($kind, CP_RELATION_TYPES, true) ? $kind : 'related',
            'label' => mb_substr(cp_clean_line($relation['label'] ?? ''), 0, CP_LIMITS['relation_label'], 'UTF-8'),
            'label_en' => mb_substr(cp_clean_line($relation['label_en'] ?? ''), 0, CP_LIMITS['relation_label'], 'UTF-8'),
            'weight' => max(0, min(100, (int)($relation['weight'] ?? 50))),
            'bidirectional' => array_key_exists('bidirectional', $relation) ? (!empty($relation['bidirectional']) ? 1 : 0) : 1,
        ];
    }
    // Solo voci che esistono (e, per chi non è staff, pubblicate).
    if ($relations) {
        $marks = implode(',', array_fill(0, count($relations), '?'));
        $wanted = array_map(static fn(array $r): int => $r['target_id'], $relations);
        $sql = "SELECT id FROM cripsumpedia_entries WHERE id IN ($marks)" . cp_not_deleted_sql($mysqli) . ($staff ? '' : " AND status = 'published'");
        $stmt = $mysqli->prepare($sql);
        $valid = [];
        if ($stmt) {
            $stmt->bind_param(str_repeat('i', count($wanted)), ...$wanted);
            $stmt->execute();
            foreach (cp_stmt_all($stmt) as $row) $valid[(int)$row['id']] = true;
            $stmt->close();
        }
        $relations = array_values(array_filter($relations, static fn(array $r): bool => isset($valid[$r['target_id']])));
    }
    $data['relations'] = array_slice($relations, 0, CP_LIMITS['relations']);

    unset($f);
    return ['data' => $data, 'errors' => $errors];
}

/**
 * Da dove parte il modulo: la voce com'è (con la descrizione di prima portata
 * nel testo) o una voce vuota del tipo scelto.
 */
function cp_write_base(mysqli $mysqli, ?array $entry, string $type): array
{
    return $entry ? cp_data_for_editor(cp_entry_snapshot($mysqli, $entry)) : cp_empty_data(cp_normalize_type($type) ?? 'person');
}

/**
 * Le voci che hanno già questo titolo (o un soprannome, o l'indirizzo, uguale):
 * prima di scriverne una nuova. Chi non è staff vede solo quelle pubblicate.
 */
function cp_title_matches(mysqli $mysqli, string $title, int $ignoreId = 0, bool $staff = false, int $limit = 3): array
{
    $title = cp_clean_line($title);
    if (cp_len($title) < 2) return [];
    $slug = cp_slugify($title);
    $visible = $staff ? '' : " AND e.status = 'published'";
    $sql = "SELECT DISTINCT e.id, e.entry_type, e.slug, e.title, e.title_en, e.subtitle, e.subtitle_en, e.image_url, e.banner_url, e.status
            FROM cripsumpedia_entries e
            LEFT JOIN cripsumpedia_aliases a ON a.entry_id = e.id
            WHERE e.id <> ?" . cp_not_deleted_sql($mysqli, 'e') . $visible . "
              AND (e.title = ? OR e.title_en = ? OR e.slug = ? OR a.alias = ? OR a.alias_en = ?)
            ORDER BY e.id ASC LIMIT ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('isssssi', $ignoreId, $title, $title, $slug, $title, $title, $limit);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();
    return $rows;
}

// ── Scrivere nel database ───────────────────────────────────────────────────

/** Un indirizzo libero per il tipo di voce: «cripsum», poi «cripsum-2»… */
function cp_unique_slug(mysqli $mysqli, string $type, string $slug, int $ignoreId = 0): string
{
    $base = mb_substr(cp_slugify($slug), 0, 150, 'UTF-8');
    $candidate = $base;
    for ($i = 2; $i < 500; $i++) {
        $stmt = $mysqli->prepare('SELECT id FROM cripsumpedia_entries WHERE entry_type = ? AND slug = ? AND id <> ? LIMIT 1');
        if (!$stmt) return $candidate;
        $stmt->bind_param('ssi', $type, $candidate, $ignoreId);
        $stmt->execute();
        $taken = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$taken) return $candidate;
        $candidate = $base . '-' . $i;
    }
    return $base . '-' . bin2hex(random_bytes(3));
}

function cp_sync_tags(mysqli $mysqli, int $entryId, array $tags): void
{
    $stmt = $mysqli->prepare('DELETE FROM cripsumpedia_entry_tags WHERE entry_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $stmt->close();
    }
    foreach ($tags as $tag) {
        $name = (string)$tag['it'];
        $nameEn = (string)($tag['en'] ?? '');
        $slug = cp_slugify($name);
        $color = '#7dd3fc';
        $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_tags (name, name_en, slug, color, created_at, updated_at)
                                  VALUES (?, ?, ?, ?, NOW(), NOW())
                                  ON DUPLICATE KEY UPDATE name_en = IF(VALUES(name_en) = '', name_en, VALUES(name_en)), updated_at = NOW()");
        if (!$stmt) continue;
        $stmt->bind_param('ssss', $name, $nameEn, $slug, $color);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare('INSERT IGNORE INTO cripsumpedia_entry_tags (entry_id, tag_id) SELECT ?, id FROM cripsumpedia_tags WHERE slug = ? LIMIT 1');
        if (!$stmt) continue;
        $stmt->bind_param('is', $entryId, $slug);
        $stmt->execute();
        $stmt->close();
    }
}

function cp_sync_aliases(mysqli $mysqli, int $entryId, array $aliases): void
{
    $stmt = $mysqli->prepare('DELETE FROM cripsumpedia_aliases WHERE entry_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $stmt->close();
    }
    $stmt = $mysqli->prepare('INSERT INTO cripsumpedia_aliases (entry_id, alias, alias_en, normalized_alias, created_at) VALUES (?, ?, ?, ?, NOW())');
    if (!$stmt) return;
    foreach ($aliases as $alias) {
        $it = (string)$alias['it'];
        $en = (string)($alias['en'] ?? '');
        $normalized = cp_slugify($it);
        $stmt->bind_param('isss', $entryId, $it, $en, $normalized);
        $stmt->execute();
    }
    $stmt->close();
}

function cp_sync_quotes(mysqli $mysqli, int $entryId, array $quotes): void
{
    $stmt = $mysqli->prepare('DELETE FROM cripsumpedia_quotes WHERE entry_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $stmt->close();
    }
    $stmt = $mysqli->prepare('INSERT INTO cripsumpedia_quotes (entry_id, quote_text, quote_text_en, speaker, speaker_en, is_featured, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt) return;
    foreach ($quotes as $quote) {
        $text = (string)$quote['text'];
        $textEn = (string)($quote['text_en'] ?? '');
        $speaker = (string)($quote['speaker'] ?? '');
        $speakerEn = (string)($quote['speaker_en'] ?? '');
        $featured = (int)($quote['featured'] ?? 0);
        $stmt->bind_param('issssi', $entryId, $text, $textEn, $speaker, $speakerEn, $featured);
        $stmt->execute();
    }
    $stmt->close();
}

/** I collegamenti in uscita; quelli «nei due sensi» si scrivono anche al contrario. */
function cp_sync_relations(mysqli $mysqli, int $entryId, array $relations): void
{
    $stmt = $mysqli->prepare('DELETE FROM cripsumpedia_relations WHERE source_entry_id = ? OR (target_entry_id = ? AND is_bidirectional = 1)');
    if ($stmt) {
        $stmt->bind_param('ii', $entryId, $entryId);
        $stmt->execute();
        $stmt->close();
    }
    $sql = 'INSERT INTO cripsumpedia_relations
            (source_entry_id, target_entry_id, relation_type, relation_label, relation_label_en, weight, is_bidirectional, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE relation_label = VALUES(relation_label), relation_label_en = VALUES(relation_label_en),
                                    weight = VALUES(weight), is_bidirectional = VALUES(is_bidirectional), updated_at = NOW()';
    foreach ($relations as $relation) {
        $target = (int)$relation['target_id'];
        if ($target <= 0 || $target === $entryId) continue;
        $type = (string)$relation['relation_type'];
        $label = (string)($relation['label'] ?? '');
        $labelEn = (string)($relation['label_en'] ?? '');
        $weight = (int)($relation['weight'] ?? 50);
        $both = (int)($relation['bidirectional'] ?? 1);
        foreach ($both ? [[$entryId, $target], [$target, $entryId]] : [[$entryId, $target]] as [$from, $to]) {
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) continue;
            $stmt->bind_param('iisssii', $from, $to, $type, $label, $labelEn, $weight, $both);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * Scrive i dati di una versione nella voce: campi, soprannomi, tag, citazioni,
 * collegamenti, poi testo pronto, indirizzo vecchio e citazioni tra voci.
 * $entryId null = voce nuova. Restituisce l'id della voce, 0 se non riesce.
 */
function cp_apply_data(mysqli $mysqli, ?int $entryId, array $data, ?int $authorId): int
{
    $old = $entryId ? cp_fetch_entry($mysqli, null, (string)$entryId, true, true) : null;
    if ($entryId && !$old) return 0;

    $type = cp_normalize_type($data['entry_type'] ?? 'person') ?? 'person';
    $slug = cp_unique_slug($mysqli, $type, (string)($data['fields']['title'] ?? ''), (int)$entryId);
    // Un titolo che non cambia tiene l'indirizzo che ha (anche se è «cripsum-2»).
    if ($old && $old['entry_type'] === $type && cp_slugify((string)$old['title']) === cp_slugify((string)$data['fields']['title'])) {
        $slug = (string)$old['slug'];
    }

    $columns = ['entry_type' => $type, 'slug' => $slug];
    foreach (CP_REVISION_FIELDS as $field) {
        $columns[$field] = $data['fields'][$field] ?? null;
    }
    $columns['updated_by'] = $authorId;
    $ints = ['featured', 'profile_user_id', 'importance', 'updated_by', 'created_by'];

    if ($old) {
        $set = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", array_keys($columns)));
        $sql = "UPDATE cripsumpedia_entries SET $set, updated_at = NOW() WHERE id = ?";
        $values = array_values($columns);
        $values[] = $entryId;
        $types = implode('', array_map(static fn(string $c): string => in_array($c, $ints, true) ? 'i' : 's', array_keys($columns))) . 'i';
    } else {
        $columns['created_by'] = $authorId;
        $sql = 'INSERT INTO cripsumpedia_entries (`' . implode('`, `', array_keys($columns)) . '`, created_at, updated_at) VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ', NOW(), NOW())';
        $values = array_values($columns);
        $types = implode('', array_map(static fn(string $c): string => in_array($c, $ints, true) ? 'i' : 's', array_keys($columns)));
    }

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log('[cripsumpedia] salvataggio non preparato: ' . $mysqli->error);
        return 0;
    }
    $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) {
        error_log('[cripsumpedia] salvataggio non riuscito: ' . $stmt->error);
        $stmt->close();
        return 0;
    }
    $id = $old ? (int)$entryId : (int)$stmt->insert_id;
    $stmt->close();

    cp_sync_tags($mysqli, $id, $data['tags'] ?? []);
    cp_sync_aliases($mysqli, $id, $data['aliases'] ?? []);
    cp_sync_quotes($mysqli, $id, $data['quotes'] ?? []);
    cp_sync_relations($mysqli, $id, $data['relations'] ?? []);

    cp_store_entry_html($mysqli, $id);
    cp_remember_old_slug($mysqli, $id, (string)($old['entry_type'] ?? $type), (string)($old['slug'] ?? ''), $type, $slug);
    cp_refresh_mentions($mysqli, $id);
    return $id;
}

/**
 * Applica a $current solo quello che la proposta ha cambiato rispetto alla
 * versione da cui è partita ($base): se intanto qualcun altro ha cambiato un
 * altro campo, quel cambiamento resta. Restituisce i dati uniti e i campi
 * cambiati da tutte e due le parti (lì vince la proposta).
 */
function cp_patch_data(array $current, array $base, array $proposal): array
{
    $merged = $current;
    $conflicts = [];
    $same = static fn($a, $b): bool => json_encode($a, JSON_UNESCAPED_UNICODE) === json_encode($b, JSON_UNESCAPED_UNICODE);

    foreach ($proposal['fields'] as $field => $value) {
        $was = $base['fields'][$field] ?? null;
        if ($same($value, $was)) continue;
        $now = $current['fields'][$field] ?? null;
        if (!$same($now, $was) && !$same($now, $value)) $conflicts[] = $field;
        $merged['fields'][$field] = $value;
    }
    foreach (['entry_type', 'aliases', 'tags', 'quotes', 'relations'] as $key) {
        if ($same($proposal[$key] ?? null, $base[$key] ?? null)) continue;
        if (!$same($current[$key] ?? null, $base[$key] ?? null) && !$same($current[$key] ?? null, $proposal[$key] ?? null)) $conflicts[] = $key;
        $merged[$key] = $proposal[$key];
    }
    return ['data' => $merged, 'conflicts' => $conflicts];
}

/**
 * I dati per l'editor partendo da una voce scritta prima della fase 4: la
 * descrizione di allora diventa «in breve» (se è corta e manca) o l'inizio del
 * testo, così chi modifica la vede e niente si perde.
 */
function cp_data_for_editor(array $data): array
{
    foreach (['' => 'it', '_en' => 'en'] as $suffix => $unused) {
        $description = trim((string)($data['fields']['description' . $suffix] ?? ''));
        if ($description === '') continue;
        $subtitle = trim((string)($data['fields']['subtitle' . $suffix] ?? ''));
        $content = trim((string)($data['fields']['content_md' . $suffix] ?? ''));
        $flat = static fn(string $s): string => mb_strtolower(trim((string)preg_replace('~[\s*_>#`]+~u', ' ', $s)), 'UTF-8');

        if ($subtitle === '' && cp_len($description) <= CP_LIMITS['subtitle'] && !str_contains($description, "\n")) {
            $data['fields']['subtitle' . $suffix] = $description;
        } elseif ($flat($description) !== $flat($subtitle) && !str_starts_with($flat($content), $flat($description))) {
            $data['fields']['content_md' . $suffix] = trim($description . "\n\n" . $content);
        }
        $data['fields']['description' . $suffix] = '';
    }
    // Una foto sola: se c'era solo il banner, diventa la foto.
    if (trim((string)($data['fields']['image_url'] ?? '')) === '') {
        $data['fields']['image_url'] = (string)($data['fields']['banner_url'] ?? '');
    }
    $data['fields']['banner_url'] = '';
    return $data;
}
