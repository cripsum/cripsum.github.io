<?php
/**
 * Cripsumpedia — chi può scrivere, bozze e proposte (fase 4).
 *
 * Lo staff (owner e admin) pubblica subito. Gli altri, quando le proposte sono
 * aperte dal pannello, scrivono una bozza (si salva da sola nel database) e la
 * mandano allo staff: finché non è approvata la vedono solo loro e lo staff.
 * Ogni bozza e ogni proposta è una riga di cripsumpedia_revisions:
 * status «draft», poi «pending», poi «approved» o «rejected».
 */

const CP_DRAFTS_MAX = 10;           // bozze aperte per persona
const CP_SUBMIT_INTERVAL = 30;      // secondi tra due proposte
const CP_UPLOADS_PER_DAY = 10;      // foto al giorno per chi non è staff

/** Le impostazioni del pannello, con i valori di sempre se la riga manca. */
function cp_settings(mysqli $mysqli): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $settings = ['proposals_open' => false, 'min_account_days' => 3, 'max_pending' => 3];
    if (!cp_v2_ready($mysqli)) return $cache = $settings;

    $result = $mysqli->query('SELECT proposals_open, min_account_days, max_pending FROM cripsumpedia_settings WHERE id = 1');
    if ($result && ($row = $result->fetch_assoc())) {
        $settings = [
            'proposals_open' => (int)$row['proposals_open'] === 1,
            'min_account_days' => (int)$row['min_account_days'],
            'max_pending' => max(1, (int)$row['max_pending']),
        ];
    }
    return $cache = $settings;
}

function cp_current_user_id(): int
{
    return function_exists('isLoggedIn') && isLoggedIn() ? (int)($_SESSION['user_id'] ?? 0) : 0;
}

/**
 * Chi sta scrivendo può proporre? Restituisce [true, ''] oppure [false, codice]:
 * login, closed, banned, blocked, young, migration.
 */
function cp_can_propose(mysqli $mysqli): array
{
    if (!cp_v2_ready($mysqli)) return [false, 'migration'];
    $userId = cp_current_user_id();
    if ($userId <= 0) return [false, 'login'];
    if (cp_is_admin_user()) return [true, ''];

    $settings = cp_settings($mysqli);
    if (!$settings['proposals_open']) return [false, 'closed'];

    $stmt = $mysqli->prepare('SELECT * FROM utenti WHERE id = ? LIMIT 1');
    if (!$stmt) return [false, 'login'];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) return [false, 'login'];
    if ((int)($user['isBannato'] ?? 0) === 1) return [false, 'banned'];

    $stmt = $mysqli->prepare('SELECT 1 FROM cripsumpedia_blocked WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $blocked = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        if ($blocked) return [false, 'blocked'];
    }

    $created = strtotime((string)($user['data_creazione'] ?? ''));
    if ($created && $settings['min_account_days'] > 0 && $created > time() - $settings['min_account_days'] * 86400) {
        return [false, 'young'];
    }
    return [true, ''];
}

/** La frase per chi non può scrivere, a parole. */
function cp_cannot_write_text(string $code, string $lang, mysqli $mysqli): string
{
    $days = cp_settings($mysqli)['min_account_days'];
    $it = [
        'login' => 'Accedi per scrivere nella Cripsumpedia.',
        'closed' => 'Per ora le voci le scrive lo staff. Presto potrà proporne una chiunque abbia un account.',
        'banned' => 'Con l\'account sospeso non si può scrivere.',
        'blocked' => 'Lo staff ti ha tolto la possibilità di proporre voci. Se pensi sia un errore, scrivi al supporto.',
        'young' => "Potrai proporre voci quando il tuo account avrà almeno $days giorni.",
        'migration' => 'La Cripsumpedia non è ancora pronta per le proposte.',
    ];
    $en = [
        'login' => 'Sign in to write in the Cripsumpedia.',
        'closed' => 'For now entries are written by the staff. Soon anyone with an account will be able to suggest one.',
        'banned' => 'Suspended accounts cannot write.',
        'blocked' => 'The staff turned off your suggestions. If you think it is a mistake, contact support.',
        'young' => "You can suggest entries once your account is at least $days days old.",
        'migration' => 'The Cripsumpedia is not ready for suggestions yet.',
    ];
    return ($lang === 'en' ? $en : $it)[$code] ?? ($lang === 'en' ? $en : $it)['login'];
}

/** Una versione (bozza, proposta…) per id. */
function cp_revision_find(mysqli $mysqli, int $revisionId): ?array
{
    if ($revisionId <= 0 || !cp_v2_ready($mysqli)) return null;
    $stmt = $mysqli->prepare('SELECT * FROM cripsumpedia_revisions WHERE id = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('i', $revisionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    $row['data_array'] = json_decode((string)$row['data'], true) ?: [];
    return $row;
}

/** La bozza aperta di una persona su una voce (o su una voce nuova, entry_id vuoto). */
function cp_user_draft_for(mysqli $mysqli, int $userId, ?int $entryId): ?array
{
    $sql = 'SELECT id FROM cripsumpedia_revisions WHERE user_id = ? AND status = \'draft\' AND '
        . ($entryId ? 'entry_id = ?' : 'entry_id IS NULL') . ' ORDER BY updated_at DESC LIMIT 1';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;
    if ($entryId) $stmt->bind_param('ii', $userId, $entryId);
    else $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? cp_revision_find($mysqli, (int)$row['id']) : null;
}

/** Bozze e proposte in attesa di una persona, per la pagina «Scrivi». */
function cp_user_open_revisions(mysqli $mysqli, int $userId): array
{
    if ($userId <= 0 || !cp_v2_ready($mysqli)) return [];
    $stmt = $mysqli->prepare("SELECT id, entry_id, kind, status, entry_type, title, updated_at FROM cripsumpedia_revisions
                              WHERE user_id = ? AND status IN ('draft', 'pending') ORDER BY updated_at DESC LIMIT 20");
    if (!$stmt) return [];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();
    return $rows;
}

function cp_count_revisions(mysqli $mysqli, int $userId, string $status): int
{
    $stmt = $mysqli->prepare('SELECT COUNT(*) FROM cripsumpedia_revisions WHERE user_id = ? AND status = ?');
    if (!$stmt) return 0;
    $stmt->bind_param('is', $userId, $status);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    return $count;
}

/**
 * Salva (o aggiorna) la bozza di chi scrive. Restituisce l'id della bozza,
 * o una stringa con l'errore.
 */
function cp_save_draft(mysqli $mysqli, int $userId, ?int $draftId, ?int $entryId, ?int $baseRevisionId, array $data, string $summary, string $lang): int|string
{
    $en = $lang === 'en';
    if ($draftId) {
        $draft = cp_revision_find($mysqli, $draftId);
        if (!$draft || (int)$draft['user_id'] !== $userId || $draft['status'] !== 'draft') {
            return $en ? 'This draft is no longer available.' : 'Questa bozza non c\'è più.';
        }
        $json = cp_revision_encode($data);
        $type = cp_normalize_type($data['entry_type'] ?? null);
        $title = mb_substr((string)($data['fields']['title'] ?? ''), 0, 190, 'UTF-8');
        $summary = mb_substr($summary, 0, CP_LIMITS['summary'], 'UTF-8');
        $stmt = $mysqli->prepare('UPDATE cripsumpedia_revisions SET data = ?, entry_type = ?, title = ?, summary = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmt) return $en ? 'Saving failed.' : 'Salvataggio non riuscito.';
        $stmt->bind_param('ssssi', $json, $type, $title, $summary, $draftId);
        $stmt->execute();
        $stmt->close();
        return $draftId;
    }

    if (cp_count_revisions($mysqli, $userId, 'draft') >= CP_DRAFTS_MAX) {
        return $en ? 'You have too many open drafts: finish or delete one first.' : 'Hai troppe bozze aperte: finiscine o buttane una prima.';
    }
    $id = cp_revision_insert($mysqli, [
        'entry_id' => $entryId,
        'base_revision_id' => $baseRevisionId,
        'user_id' => $userId,
        'kind' => $entryId ? 'edit' : 'new',
        'status' => 'draft',
        'data' => $data,
        'summary' => $summary,
    ]);
    return $id > 0 ? $id : ($en ? 'Saving failed.' : 'Salvataggio non riuscito.');
}

/** Butta una bozza (solo di chi l'ha scritta) e le sue foto non usate. */
function cp_discard_draft(mysqli $mysqli, int $userId, int $draftId): bool
{
    $draft = cp_revision_find($mysqli, $draftId);
    if (!$draft || (int)$draft['user_id'] !== $userId || $draft['status'] !== 'draft') return false;
    $stmt = $mysqli->prepare('DELETE FROM cripsumpedia_revisions WHERE id = ? AND status = \'draft\'');
    if (!$stmt) return false;
    $stmt->bind_param('i', $draftId);
    $stmt->execute();
    $stmt->close();
    foreach (cp_data_uploads($draft['data_array']) as $path) cp_forget_unused_upload($mysqli, $path);
    return true;
}

/** Una proposta in attesa torna bozza (la si vuole ancora cambiare). */
function cp_withdraw_proposal(mysqli $mysqli, int $userId, int $revisionId): bool
{
    $stmt = $mysqli->prepare("UPDATE cripsumpedia_revisions SET status = 'draft' WHERE id = ? AND user_id = ? AND status = 'pending'");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $revisionId, $userId);
    $stmt->execute();
    $done = $stmt->affected_rows > 0;
    $stmt->close();
    return $done;
}

/**
 * I dati per riprendere una proposta rifiutata: il modulo parte da lì e il
 * primo salvataggio crea la bozza. Per una modifica si parte dalla voce com'è
 * adesso e si rimettono solo i cambiamenti proposti allora.
 * Restituisce ['entry' => ?array, 'data' => array, 'summary' => string] o null.
 */
function cp_resume_rejected(mysqli $mysqli, int $userId, int $revisionId): ?array
{
    $revision = cp_revision_find($mysqli, $revisionId);
    if (!$revision || (int)$revision['user_id'] !== $userId || $revision['status'] !== 'rejected') return null;
    $data = $revision['data_array'];
    $entry = null;
    if ($revision['entry_id']) {
        $entry = cp_fetch_entry($mysqli, null, (string)$revision['entry_id'], false);
        if (!$entry) return null;
        $current = cp_write_base($mysqli, $entry, (string)$entry['entry_type']);
        $oldBase = $revision['base_revision_id'] ? (cp_revision_find($mysqli, (int)$revision['base_revision_id'])['data_array'] ?? null) : null;
        $data = cp_patch_data($current, $oldBase ? cp_data_for_editor($oldBase) : $current, $data)['data'];
    }
    // Le foto tolte col rifiuto non ci sono più.
    $image = (string)($data['fields']['image_url'] ?? '');
    if (cp_is_pedia_upload($image) && !is_file(__DIR__ . '/../..' . $image)) $data['fields']['image_url'] = '';
    return ['entry' => $entry, 'data' => $data, 'summary' => (string)$revision['summary']];
}

// ── Foto caricate ───────────────────────────────────────────────────────────

/** Quante foto ha caricato oggi una persona (null se il registro non c'è). */
function cp_uploads_today(mysqli $mysqli, int $userId): ?int
{
    if (!cp_table_exists($mysqli, 'cripsumpedia_uploads')) return null;
    $stmt = $mysqli->prepare('SELECT COUNT(*) FROM cripsumpedia_uploads WHERE user_id = ? AND created_at >= CURDATE()');
    if (!$stmt) return null;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    return $count;
}

function cp_record_upload(mysqli $mysqli, int $userId, string $path): void
{
    if (!cp_table_exists($mysqli, 'cripsumpedia_uploads')) return;
    $stmt = $mysqli->prepare('INSERT IGNORE INTO cripsumpedia_uploads (user_id, path, created_at) VALUES (?, ?, NOW())');
    if (!$stmt) return;
    $owner = $userId > 0 ? $userId : null;
    $stmt->bind_param('is', $owner, $path);
    $stmt->execute();
    $stmt->close();
}

/**
 * Toglie dal disco una foto caricata dalla Cripsumpedia se nessuna voce e
 * nessuna bozza o proposta ancora aperta la usa.
 */
function cp_forget_unused_upload(mysqli $mysqli, string $path): void
{
    if (!cp_is_pedia_upload($path)) return;
    $like = '%' . addcslashes($path, '%_\\') . '%';
    $stmt = $mysqli->prepare("SELECT
            (SELECT COUNT(*) FROM cripsumpedia_entries WHERE image_url = ? OR banner_url = ? OR content_md LIKE ? OR content_md_en LIKE ?)
          + (SELECT COUNT(*) FROM cripsumpedia_revisions WHERE status IN ('draft', 'pending', 'approved') AND data LIKE ?)");
    if (!$stmt) return;
    $stmt->bind_param('sssss', $path, $path, $like, $like, $like);
    $stmt->execute();
    $used = (int)($stmt->get_result()->fetch_row()[0] ?? 1);
    $stmt->close();
    if ($used > 0) return;

    $file = realpath(__DIR__ . '/../..' . $path);
    $root = realpath(__DIR__ . '/../../img/cripsumpedia');
    if ($file && $root && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
        @unlink($file);
    }
    if (cp_table_exists($mysqli, 'cripsumpedia_uploads')) {
        $stmt = $mysqli->prepare('DELETE FROM cripsumpedia_uploads WHERE path = ?');
        if ($stmt) {
            $stmt->bind_param('s', $path);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// ── Avvisi ──────────────────────────────────────────────────────────────────

/** Un messaggio nella Posta di qualcuno, con la campanella (come per Shitpost). */
function cp_notify(mysqli $mysqli, int $userId, string $titleIt, string $titleEn, string $bodyIt, string $bodyEn, int $quietMinutes = 0): void
{
    if ($userId <= 0) return;
    try {
        require_once __DIR__ . '/../community/community.php';
        if (function_exists('cm_notify')) {
            cm_notify($mysqli, $userId, $titleIt, $titleEn, $bodyIt, $bodyEn, 'system', $quietMinutes);
        }
    } catch (Throwable $e) {
        error_log('[cripsumpedia] avviso non mandato: ' . $e->getMessage());
    }
}

/** Gli account dello staff che approvano. */
function cp_staff_ids(mysqli $mysqli): array
{
    $ids = [];
    $result = $mysqli->query("SELECT id FROM utenti WHERE ruolo IN ('owner', 'admin')");
    if ($result) {
        while ($row = $result->fetch_row()) $ids[] = (int)$row[0];
    }
    return $ids;
}

/** Allo staff: c'è una proposta nuova (al massimo un avviso ogni mezz'ora a testa). */
function cp_notify_staff_new_proposal(mysqli $mysqli, array $revision, string $author): void
{
    $title = cp_clip_title((string)$revision['title']);
    $isNew = $revision['kind'] === 'new';
    $link = 'https://cripsum.com/it/cripsumpedia/revisione?id=' . (int)$revision['id'];
    $linkEn = 'https://cripsum.com/en/cripsumpedia/revisione?id=' . (int)$revision['id'];
    foreach (cp_staff_ids($mysqli) as $staffId) {
        if ($staffId === (int)$revision['user_id']) continue;
        cp_notify(
            $mysqli,
            $staffId,
            'Nuova proposta nella Cripsumpedia',
            'New Cripsumpedia suggestion',
            ($isNew ? "{$author} ha proposto una voce nuova: «{$title}»." : "{$author} ha proposto una modifica a «{$title}».") . "\n\n" . $link,
            ($isNew ? "{$author} suggested a new entry: \"{$title}\"." : "{$author} suggested an edit to \"{$title}\".") . "\n\n" . $linkEn,
            30
        );
    }
}

function cp_clip_title(string $title): string
{
    $title = trim($title);
    return mb_strlen($title, 'UTF-8') > 80 ? mb_substr($title, 0, 79, 'UTF-8') . '…' : $title;
}
