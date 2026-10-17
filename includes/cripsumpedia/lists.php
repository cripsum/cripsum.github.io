<?php
/**
 * Cripsumpedia — le liste delle pagine da leggere: voce in evidenza, ultime
 * modifiche, tag di una categoria, chi cita una voce, chi l'ha scritta,
 * accadde oggi, chi c'era a un evento.
 */

/**
 * Chi vede i pulsanti per scrivere. Per ora solo lo staff: dalla fase 4 anche
 * chi ha un account, quando le proposte sono aperte dal pannello.
 */
function cp_can_write(): bool
{
    return cp_is_admin_user();
}

/** Dove porta «Scrivi una voce» (per ora l'editor dello staff). */
function cp_write_url(string $lang, ?string $type = null, string $title = ''): string
{
    return cp_url('editor', ['type' => cp_normalize_type($type), 'title' => trim($title)], $lang);
}

/**
 * La voce in evidenza: una di quelle segnate dallo staff («featured»), che
 * cambia ogni giorno; se nessuna è segnata, la più letta degli ultimi 14 giorni.
 */
function cp_featured_entry(mysqli $mysqli): ?array
{
    if (!cp_schema_ready($mysqli)) return null;

    $rows = [];
    $sql = "SELECT * FROM cripsumpedia_entries
            WHERE status = 'published' AND featured = 1" . cp_not_deleted_sql($mysqli) . "
            ORDER BY id ASC";
    if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) $rows[] = $row;
    }
    if ($rows) return $rows[cp_seeded_daily_index(count($rows))];

    return cp_fetch_entries($mysqli, ['order' => 'trending', 'limit' => 1])[0] ?? null;
}

/**
 * Le ultime voci toccate, una riga per voce: chi, cosa ha fatto, quando.
 * Con la cronologia vengono dalle versioni; prima, dall'ultima modifica.
 *
 * @return array<int, array{entry: array, kind: string, at: string, user_id: int, username: string, name: string}>
 */
function cp_recent_changes(mysqli $mysqli, int $limit = 5): array
{
    if (!cp_schema_ready($mysqli)) return [];
    $limit = max(1, min(20, $limit));

    if (cp_v2_ready($mysqli)) {
        $sql = "SELECT e.*, r.kind AS change_kind, r.created_at AS changed_at, r.user_id AS change_user_id,
                       u.username AS change_username, u.display_name AS change_name
                FROM cripsumpedia_revisions r
                INNER JOIN (
                    SELECT entry_id, MAX(id) AS id FROM cripsumpedia_revisions
                    WHERE status = 'approved' AND entry_id IS NOT NULL AND kind <> 'hide'
                    GROUP BY entry_id
                ) last ON last.id = r.id
                INNER JOIN cripsumpedia_entries e ON e.id = r.entry_id
                LEFT JOIN utenti u ON u.id = r.user_id
                WHERE e.status = 'published' AND e.deleted_at IS NULL
                ORDER BY r.created_at DESC, r.id DESC
                LIMIT ?";
    } else {
        $sql = "SELECT e.*, 'edit' AS change_kind, e.updated_at AS changed_at, e.updated_by AS change_user_id,
                       u.username AS change_username, u.display_name AS change_name
                FROM cripsumpedia_entries e
                LEFT JOIN utenti u ON u.id = e.updated_by
                WHERE e.status = 'published'
                ORDER BY e.updated_at DESC, e.id DESC
                LIMIT ?";
    }

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();

    return array_map(static fn(array $row): array => [
        'entry' => $row,
        'kind' => (string)$row['change_kind'],
        'at' => (string)$row['changed_at'],
        'user_id' => (int)($row['change_user_id'] ?? 0),
        'username' => (string)($row['change_username'] ?? ''),
        'name' => (string)(($row['change_name'] ?? '') ?: ($row['change_username'] ?? '')),
    ], $rows);
}

/** I tag usati dalle voci pubblicate di un tipo, con quante voci li hanno. */
function cp_category_tags(mysqli $mysqli, string $type, int $limit = 12): array
{
    if (!cp_schema_ready($mysqli)) return [];
    $limit = max(1, min(40, $limit));

    $stmt = $mysqli->prepare("SELECT t.id, t.name, t.name_en, t.slug, COUNT(*) AS total
                              FROM cripsumpedia_tags t
                              INNER JOIN cripsumpedia_entry_tags et ON et.tag_id = t.id
                              INNER JOIN cripsumpedia_entries e ON e.id = et.entry_id
                              WHERE e.entry_type = ? AND e.status = 'published'" . cp_not_deleted_sql($mysqli, 'e') . "
                              GROUP BY t.id, t.name, t.name_en, t.slug
                              ORDER BY total DESC, t.name ASC
                              LIMIT ?");
    if (!$stmt) return [];
    $stmt->bind_param('si', $type, $limit);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();
    return $rows;
}

/**
 * Chi ha toccato una voce per ultimo e quando, e in quanti ci hanno scritto.
 *
 * @return array{user_id: int, username: string, name: string, at: string, authors: ?int}
 */
function cp_entry_authorship(mysqli $mysqli, array $entry): array
{
    $out = [
        'user_id' => (int)($entry['updated_by'] ?? 0) ?: (int)($entry['created_by'] ?? 0),
        'username' => '',
        'name' => '',
        'at' => (string)(($entry['updated_at'] ?? '') ?: ($entry['created_at'] ?? '')),
        'authors' => null,
    ];

    if (cp_v2_ready($mysqli)) {
        $entryId = (int)$entry['id'];
        $stmt = $mysqli->prepare("SELECT user_id, created_at FROM cripsumpedia_revisions
                                  WHERE entry_id = ? AND status = 'approved'
                                  ORDER BY id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $entryId);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $out['user_id'] = (int)($row['user_id'] ?? 0);
                $out['at'] = (string)$row['created_at'];
            }
            $stmt->close();
        }
        $stmt = $mysqli->prepare("SELECT COUNT(DISTINCT user_id) AS total FROM cripsumpedia_revisions
                                  WHERE entry_id = ? AND status = 'approved' AND user_id IS NOT NULL");
        if ($stmt) {
            $stmt->bind_param('i', $entryId);
            $stmt->execute();
            $out['authors'] = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $stmt->close();
        }
    }

    if ($out['user_id'] > 0) {
        $stmt = $mysqli->prepare("SELECT username, display_name FROM utenti WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $out['user_id']);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $out['username'] = (string)$row['username'];
                $out['name'] = (string)(($row['display_name'] ?? '') ?: $row['username']);
            }
            $stmt->close();
        }
    }
    return $out;
}

/** L'account collegato a una persona (profilo sul sito), se c'è. */
function cp_entry_profile(mysqli $mysqli, array $entry): ?array
{
    $userId = (int)($entry['profile_user_id'] ?? 0);
    if ($userId <= 0) return null;

    $stmt = $mysqli->prepare("SELECT id, username FROM utenti WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Le voci pubblicate che nominano questa nel loro testo. */
function cp_mentioned_in(mysqli $mysqli, int $entryId, int $limit = 12): array
{
    if (!cp_v2_ready($mysqli)) return [];
    $limit = max(1, min(40, $limit));

    $stmt = $mysqli->prepare("SELECT e.* FROM cripsumpedia_mentions m
                              INNER JOIN cripsumpedia_entries e ON e.id = m.entry_id
                              WHERE m.target_id = ? AND e.status = 'published' AND e.deleted_at IS NULL
                              ORDER BY e.title ASC
                              LIMIT ?");
    if (!$stmt) return [];
    $stmt->bind_param('ii', $entryId, $limit);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();
    return $rows;
}

/** Gli eventi successi in questo giorno, negli anni passati. */
function cp_events_on_this_day(mysqli $mysqli, int $limit = 3): array
{
    if (!cp_schema_ready($mysqli)) return [];

    $stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_entries
                              WHERE entry_type = 'event' AND status = 'published'" . cp_not_deleted_sql($mysqli) . "
                                AND real_date IS NOT NULL
                                AND MONTH(real_date) = MONTH(CURDATE()) AND DAY(real_date) = DAY(CURDATE())
                                AND YEAR(real_date) < YEAR(CURDATE())
                              ORDER BY real_date DESC
                              LIMIT ?");
    if (!$stmt) return [];
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();
    return $rows;
}

/**
 * Le persone collegate a ogni evento (chi c'era), in una query sola.
 *
 * @param int[] $eventIds
 * @return array<int, array<int, array>> id evento => voci delle persone
 */
function cp_people_for_events(mysqli $mysqli, array $eventIds): array
{
    $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds))));
    if (!$eventIds || !cp_schema_ready($mysqli)) return [];

    $marks = implode(',', array_fill(0, count($eventIds), '?'));
    $sql = "SELECT pair.event_id, p.*
            FROM (
                SELECT r.source_entry_id AS event_id, r.target_entry_id AS person_id FROM cripsumpedia_relations r
                WHERE r.source_entry_id IN ($marks)
                UNION
                SELECT r.target_entry_id AS event_id, r.source_entry_id AS person_id FROM cripsumpedia_relations r
                WHERE r.target_entry_id IN ($marks)
            ) pair
            INNER JOIN cripsumpedia_entries p ON p.id = pair.person_id
            WHERE p.entry_type = 'person' AND p.status = 'published'" . cp_not_deleted_sql($mysqli, 'p') . "
            ORDER BY p.title ASC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    $params = array_merge($eventIds, $eventIds);
    $stmt->bind_param(str_repeat('i', count($params)), ...$params);
    $stmt->execute();
    $out = [];
    foreach (cp_stmt_all($stmt) as $row) {
        $out[(int)$row['event_id']][(int)$row['id']] = $row;
    }
    $stmt->close();
    return array_map('array_values', $out);
}
