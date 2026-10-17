<?php
/**
 * Cripsumpedia — la cronologia delle voci.
 *
 * Ogni versione di una voce è una riga di cripsumpedia_revisions con tutti i
 * suoi campi in JSON (testo, foto, soprannomi, tag, citazioni, collegamenti):
 * basta quella riga per sapere com'era la voce e per rimetterla così.
 *
 * Dalla fase 1 ogni salvataggio dello staff aggiunge una versione approvata.
 * Le voci scritte prima non avevano cronologia: la prima volta che vengono
 * toccate (o che lo staff apre il pannello) si salva com'erano, come versione
 * «baseline», così anche il primo cambiamento si può annullare.
 * Dalla fase 4 qui passano anche le proposte degli utenti (bozza, in attesa).
 */

/** I campi di una voce che fanno parte di una versione. */
const CP_REVISION_FIELDS = [
    'title', 'title_en', 'subtitle', 'subtitle_en', 'description', 'description_en',
    'content_md', 'content_md_en', 'image_url', 'banner_url', 'lore_date', 'real_date',
    'canonical_status', 'status', 'featured', 'profile_user_id',
    // Non si vedono più dalla fase 2 ma esistono ancora: senza, rimettere una
    // versione vecchia le perderebbe.
    'accent_color', 'rarity', 'importance',
    'seo_title', 'seo_title_en', 'seo_description', 'seo_description_en',
];

const CP_REVISION_INT_FIELDS = ['featured', 'profile_user_id', 'importance'];

/** Com'è adesso una voce, in una forma che si può salvare e confrontare. */
function cp_entry_snapshot(mysqli $mysqli, array $entry): array
{
    $entryId = (int)$entry['id'];

    // Vuoto e NULL sono la stessa cosa, e così gli a capo di Windows (i moduli
    // li mandano come \r\n): non devono sembrare un cambiamento.
    $fields = [];
    foreach (CP_REVISION_FIELDS as $field) {
        $value = $entry[$field] ?? null;
        if (in_array($field, CP_REVISION_INT_FIELDS, true)) {
            $fields[$field] = $value === null || $value === '' ? null : (int)$value;
        } elseif ($field === 'real_date') {
            $fields[$field] = $value === null || $value === '' ? null : (string)$value;
        } else {
            $fields[$field] = str_replace(["\r\n", "\r"], "\n", (string)$value);
        }
    }

    $aliases = [];
    $stmt = $mysqli->prepare("SELECT alias, alias_en FROM cripsumpedia_aliases WHERE entry_id = ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        foreach (cp_stmt_all($stmt) as $row) {
            $aliases[] = ['it' => (string)$row['alias'], 'en' => (string)($row['alias_en'] ?? '')];
        }
        $stmt->close();
    }

    $tags = array_map(static fn(array $row): array => [
        'it' => (string)$row['name'],
        'en' => (string)($row['name_en'] ?? ''),
    ], cp_fetch_tags($mysqli, $entryId));

    $quotes = [];
    $stmt = $mysqli->prepare("SELECT quote_text, quote_text_en, speaker, speaker_en, is_featured
                              FROM cripsumpedia_quotes WHERE entry_id = ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        foreach (cp_stmt_all($stmt) as $row) {
            $quotes[] = [
                'text' => (string)$row['quote_text'],
                'text_en' => (string)($row['quote_text_en'] ?? ''),
                'speaker' => (string)($row['speaker'] ?? ''),
                'speaker_en' => (string)($row['speaker_en'] ?? ''),
                'featured' => (int)$row['is_featured'],
            ];
        }
        $stmt->close();
    }

    // Solo i collegamenti che partono da questa voce: quelli «nei due sensi»
    // sono salvati anche da qui, gli altri appartengono alla voce di partenza.
    $relations = [];
    $stmt = $mysqli->prepare("SELECT target_entry_id, relation_type, relation_label, relation_label_en, weight, is_bidirectional
                              FROM cripsumpedia_relations WHERE source_entry_id = ?
                              ORDER BY target_entry_id ASC, relation_type ASC");
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        foreach (cp_stmt_all($stmt) as $row) {
            $relations[] = [
                'target_id' => (int)$row['target_entry_id'],
                'relation_type' => (string)$row['relation_type'],
                'label' => (string)($row['relation_label'] ?? ''),
                'label_en' => (string)($row['relation_label_en'] ?? ''),
                'weight' => (int)$row['weight'],
                'bidirectional' => (int)$row['is_bidirectional'],
            ];
        }
        $stmt->close();
    }

    return [
        'v' => 1,
        'entry_type' => (string)$entry['entry_type'],
        'slug' => (string)$entry['slug'],
        'fields' => $fields,
        'aliases' => $aliases,
        'tags' => $tags,
        'quotes' => $quotes,
        'relations' => $relations,
    ];
}

function cp_revision_encode(array $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
}

/**
 * Aggiunge una versione. $row: entry_id, base_revision_id, user_id, kind,
 * status, data (array), summary, reviewer_id, reviewed (bool), created_at.
 * Restituisce l'id, o 0 se non è riuscita.
 */
function cp_revision_insert(mysqli $mysqli, array $row): int
{
    $data = (array)($row['data'] ?? []);
    $entryId = isset($row['entry_id']) ? (int)$row['entry_id'] : null;
    $baseId = isset($row['base_revision_id']) ? (int)$row['base_revision_id'] : null;
    $userId = isset($row['user_id']) ? (int)$row['user_id'] : null;
    $kind = (string)($row['kind'] ?? 'edit');
    $status = (string)($row['status'] ?? 'draft');
    $entryType = cp_normalize_type($data['entry_type'] ?? null);
    $title = mb_substr(trim((string)($data['fields']['title'] ?? '')), 0, 190, 'UTF-8');
    $json = cp_revision_encode($data);
    $summary = mb_substr(trim((string)($row['summary'] ?? '')), 0, 200, 'UTF-8');
    $reviewerId = isset($row['reviewer_id']) ? (int)$row['reviewer_id'] : null;
    $createdAt = (string)($row['created_at'] ?? '') ?: date('Y-m-d H:i:s');
    $reviewedAt = !empty($row['reviewed']) ? $createdAt : null;

    $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_revisions
        (entry_id, base_revision_id, user_id, kind, status, entry_type, title, data, summary, reviewer_id, created_at, updated_at, reviewed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log('[cripsumpedia] versione non preparata: ' . $mysqli->error);
        return 0;
    }
    $stmt->bind_param('iiissssssisss', $entryId, $baseId, $userId, $kind, $status, $entryType, $title, $json, $summary, $reviewerId, $createdAt, $createdAt, $reviewedAt);
    if (!$stmt->execute()) {
        error_log('[cripsumpedia] versione non salvata: ' . $stmt->error);
        $stmt->close();
        return 0;
    }
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function cp_set_current_revision(mysqli $mysqli, int $entryId, int $revisionId): void
{
    $stmt = $mysqli->prepare("UPDATE cripsumpedia_entries SET revision_id = ? WHERE id = ?");
    if (!$stmt) return;
    $stmt->bind_param('ii', $revisionId, $entryId);
    $stmt->execute();
    $stmt->close();
}

/** L'ultima versione approvata di una voce: id e dati così come sono salvati. */
function cp_latest_approved_revision(mysqli $mysqli, int $entryId): ?array
{
    $stmt = $mysqli->prepare("SELECT id, data FROM cripsumpedia_revisions
                              WHERE entry_id = ? AND status = 'approved'
                              ORDER BY id DESC LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $entryId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Prima che una voce scritta prima della cronologia cambi: salva com'era.
 * Non fa niente se la voce ha già una versione.
 */
function cp_revision_baseline(mysqli $mysqli, array $entry): void
{
    if (!cp_v2_ready($mysqli) || !empty($entry['revision_id'])) return;

    $entryId = (int)$entry['id'];
    $latest = cp_latest_approved_revision($mysqli, $entryId);
    if ($latest) {
        cp_set_current_revision($mysqli, $entryId, (int)$latest['id']);
        return;
    }

    $authorId = (int)($entry['updated_by'] ?? 0) ?: (int)($entry['created_by'] ?? 0);
    $when = (string)($entry['updated_at'] ?? '') ?: (string)($entry['created_at'] ?? '');
    $revisionId = cp_revision_insert($mysqli, [
        'entry_id' => $entryId,
        'user_id' => $authorId ?: null,
        'kind' => 'baseline',
        'status' => 'approved',
        'data' => cp_entry_snapshot($mysqli, $entry),
        'reviewer_id' => $authorId ?: null,
        'reviewed' => true,
        'created_at' => $when,
    ]);
    if ($revisionId > 0) cp_set_current_revision($mysqli, $entryId, $revisionId);
}

/**
 * Dopo un salvataggio: la voce com'è adesso diventa la versione online.
 * $userId è chi l'ha scritta, $reviewerId chi l'ha approvata (lo stesso, se è
 * lo staff a scrivere). Se non è cambiato niente rispetto all'ultima, non
 * aggiunge niente. Restituisce l'id della versione online, o null prima della
 * migrazione.
 */
function cp_revision_record(mysqli $mysqli, int $entryId, ?int $userId, string $kind = 'edit', string $summary = '', ?int $reviewerId = null): ?int
{
    if (!cp_v2_ready($mysqli)) return null;

    $entry = cp_fetch_entry($mysqli, null, (string)$entryId, true, true);
    if (!$entry) return null;

    $data = cp_entry_snapshot($mysqli, $entry);
    $latest = cp_latest_approved_revision($mysqli, $entryId);
    if ($latest && $kind === 'edit' && hash_equals((string)$latest['data'], cp_revision_encode($data))) {
        cp_set_current_revision($mysqli, $entryId, (int)$latest['id']);
        return (int)$latest['id'];
    }

    $revisionId = cp_revision_insert($mysqli, [
        'entry_id' => $entryId,
        'base_revision_id' => $latest ? (int)$latest['id'] : null,
        'user_id' => $userId,
        'kind' => $kind,
        'status' => 'approved',
        'data' => $data,
        'summary' => $summary,
        'reviewer_id' => $reviewerId ?? $userId,
        'reviewed' => true,
    ]);
    if ($revisionId <= 0) return null;
    cp_set_current_revision($mysqli, $entryId, $revisionId);
    return $revisionId;
}

/**
 * Mette in pari le voci salvate prima della migrazione: versione di partenza,
 * HTML pronto, citazioni. Lo chiama il pannello dello staff a ogni apertura;
 * quando non c'è niente da fare costa una query.
 */
function cp_v2_backfill(mysqli $mysqli): array
{
    $done = ['baseline' => 0, 'html' => 0];
    if (!cp_v2_ready($mysqli)) return $done;

    $stamp = CP_RENDER_STAMP . '%';
    $stmt = $mysqli->prepare("SELECT id, revision_id FROM cripsumpedia_entries
                              WHERE deleted_at IS NULL
                                AND (revision_id IS NULL
                                     OR content_html IS NULL
                                     OR (content_html <> '' AND content_html NOT LIKE ?))");
    if (!$stmt) return $done;
    $stmt->bind_param('s', $stamp);
    $stmt->execute();
    $todo = cp_stmt_all($stmt);
    $stmt->close();

    foreach ($todo as $row) {
        $entryId = (int)$row['id'];
        if ($row['revision_id'] === null) {
            $entry = cp_fetch_entry($mysqli, null, (string)$entryId, true, true);
            if ($entry) {
                cp_revision_baseline($mysqli, $entry);
                cp_refresh_mentions($mysqli, $entryId);
                $done['baseline']++;
            }
        }
        if (cp_store_entry_html($mysqli, $entryId)) $done['html']++;
    }
    return $done;
}
