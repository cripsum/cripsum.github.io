<?php
/**
 * Cripsumpedia — quello che fa lo staff (fase 4): pubblicare, approvare o
 * rifiutare le proposte, rimettere una versione della cronologia, nascondere
 * e ripristinare voci, importare le persone da Chi siamo.
 */

/** I motivi di rifiuto pronti; «other» vuole la nota. */
function cp_rejection_reasons(): array
{
    return [
        'not_lore' => ['it' => 'Non è lore del server.', 'en' => 'It is not server lore.'],
        'duplicate' => ['it' => 'C\'è già una voce che ne parla.', 'en' => 'There is already an entry about it.'],
        'personal' => ['it' => 'Contiene informazioni personali (nome e cognome, indirizzo, scuola, numero, foto prese senza permesso).', 'en' => 'It contains personal information (full name, address, school, number, photos taken without permission).'],
        'offensive' => ['it' => 'Offende qualcuno per davvero.', 'en' => 'It genuinely offends someone.'],
        'incomplete' => ['it' => 'È scritta male o è da completare.', 'en' => 'It is badly written or incomplete.'],
        'other' => ['it' => 'Altro.', 'en' => 'Other.'],
    ];
}

/** Lo stato attuale di una voce come dati di una versione. */
function cp_entry_data(mysqli $mysqli, int $entryId): ?array
{
    $entry = cp_fetch_entry($mysqli, null, (string)$entryId, true, true);
    return $entry ? cp_entry_snapshot($mysqli, $entry) : null;
}

/**
 * Toglie la riga di una bozza o di una proposta che è appena andata online.
 *
 * La versione approvata è sempre una riga nuova, in fondo alla cronologia:
 * cronologia, «ultime modifiche» e «chi ha scritto» vanno in ordine di id, e
 * una proposta di una settimana fa approvata oggi deve finire dopo le
 * modifiche fatte nel frattempo.
 */
function cp_revision_drop(mysqli $mysqli, int $revisionId): void
{
    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_revisions WHERE id = ? AND status IN ('draft', 'reviewing') AND kind IN ('new', 'edit')");
    if ($stmt) {
        $stmt->bind_param('i', $revisionId);
        $stmt->execute();
        $stmt->close();
    }
}

/** Le foto caricate dalla Cripsumpedia usate in una versione (foto e testo). */
function cp_data_uploads(array $data): array
{
    $paths = [(string)($data['fields']['image_url'] ?? '')];
    foreach (['content_md', 'content_md_en'] as $field) {
        if (preg_match_all('~/img/cripsumpedia/\d{4}/\d{2}/[a-z0-9-]+\.(?:jpe?g|png|gif|webp)~', (string)($data['fields'][$field] ?? ''), $m)) {
            array_push($paths, ...$m[0]);
        }
    }
    return array_values(array_unique(array_filter($paths, 'cp_is_pedia_upload')));
}

/**
 * Lo staff pubblica subito (voce nuova o modifica). Restituisce l'id della
 * voce, 0 se non riesce.
 */
function cp_staff_publish(mysqli $mysqli, ?int $entryId, array $data, int $staffId, string $summary, ?int $draftId = null): int
{
    if ($entryId) {
        $entry = cp_fetch_entry($mysqli, null, (string)$entryId, true, true);
        if (!$entry || !empty($entry['deleted_at'])) return 0;
        cp_revision_baseline($mysqli, $entry);
    }
    $id = cp_apply_data($mysqli, $entryId, $data, $staffId);
    if ($id <= 0) return 0;

    cp_revision_record($mysqli, $id, $staffId, $entryId ? 'edit' : 'new', $summary);
    if ($draftId) {
        $draft = cp_revision_find($mysqli, $draftId);
        if ($draft && (int)$draft['user_id'] === $staffId && $draft['status'] === 'draft') cp_revision_drop($mysqli, $draftId);
    }
    return $id;
}

/**
 * Manda una bozza allo staff. Restituisce l'id della proposta o un errore.
 */
function cp_submit_proposal(mysqli $mysqli, int $userId, int $draftId, string $summary, string $lang): int|string
{
    $en = $lang === 'en';
    $draft = cp_revision_find($mysqli, $draftId);
    if (!$draft || (int)$draft['user_id'] !== $userId || $draft['status'] !== 'draft') {
        return $en ? 'This draft is no longer available.' : 'Questa bozza non c\'è più.';
    }
    $settings = cp_settings($mysqli);
    $max = $settings['max_pending'];
    if (cp_count_revisions($mysqli, $userId, 'pending') >= $max) {
        if ($max === 1) {
            return $en ? 'You already have a suggestion waiting: wait for the staff to read it.' : 'Hai già una proposta in attesa: aspetta che lo staff la legga.';
        }
        return $en ? "You already have $max suggestions waiting: wait for the staff to read them." : "Hai già $max proposte in attesa: aspetta che lo staff le legga.";
    }
    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM cripsumpedia_revisions WHERE user_id = ? AND status = 'pending' AND updated_at > NOW() - INTERVAL ? SECOND");
    if ($stmt) {
        $interval = CP_SUBMIT_INTERVAL;
        $stmt->bind_param('ii', $userId, $interval);
        $stmt->execute();
        $recent = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
        if ($recent > 0) return $en ? 'Wait a moment before sending another one.' : 'Aspetta un attimo prima di mandarne un\'altra.';
    }

    $summary = mb_substr($summary, 0, CP_LIMITS['summary'], 'UTF-8');
    $stmt = $mysqli->prepare("UPDATE cripsumpedia_revisions SET status = 'pending', summary = ?, updated_at = NOW(), created_at = NOW() WHERE id = ? AND status = 'draft'");
    if (!$stmt) return $en ? 'Sending failed.' : 'Invio non riuscito.';
    $stmt->bind_param('si', $summary, $draftId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$ok) return $en ? 'Sending failed.' : 'Invio non riuscito.';

    $author = (string)($_SESSION['username'] ?? '');
    $draft['status'] = 'pending';
    cp_notify_staff_new_proposal($mysqli, $draft, $author !== '' ? $author : 'Qualcuno');
    return $draftId;
}

/**
 * Approva una proposta. $edited: i dati sistemati dallo staff («Sistema e poi
 * approva»), al posto di quelli proposti. Restituisce ['ok', 'entry_id',
 * 'conflicts', 'error'].
 */
function cp_approve(mysqli $mysqli, int $revisionId, int $staffId, ?array $edited = null): array
{
    $revision = cp_revision_find($mysqli, $revisionId);
    if (!$revision || $revision['status'] !== 'pending') {
        return ['ok' => false, 'error' => 'Questa proposta è già stata gestita.'];
    }
    // Due staff che approvano insieme: passa solo il primo. «reviewing» e non
    // «approved»: finché non è applicata non deve sembrare l'ultima versione.
    $stmt = $mysqli->prepare("UPDATE cripsumpedia_revisions SET status = 'reviewing', reviewer_id = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'");
    if (!$stmt) return ['ok' => false, 'error' => 'Approvazione non riuscita.'];
    $stmt->bind_param('ii', $staffId, $revisionId);
    $stmt->execute();
    $mine = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$mine) return ['ok' => false, 'error' => 'Questa proposta è già stata gestita.'];

    $proposal = $edited ?? $revision['data_array'];
    $authorId = (int)($revision['user_id'] ?? 0) ?: null;
    $conflicts = [];
    $entryId = $revision['entry_id'] !== null ? (int)$revision['entry_id'] : null;

    if ($entryId) {
        $entry = cp_fetch_entry($mysqli, null, (string)$entryId, true, true);
        if (!$entry || !empty($entry['deleted_at'])) {
            cp_revision_reopen($mysqli, $revisionId);
            return ['ok' => false, 'error' => 'La voce è stata eliminata: la proposta non si può applicare.'];
        }
        cp_revision_baseline($mysqli, $entry);
        $current = cp_entry_snapshot($mysqli, $entry);
        $base = $revision['base_revision_id'] ? (cp_revision_find($mysqli, (int)$revision['base_revision_id'])['data_array'] ?? $current) : $current;
        $patch = cp_patch_data($current, $base, $proposal);
        $proposal = $patch['data'];
        $conflicts = $patch['conflicts'];
    }

    $id = cp_apply_data($mysqli, $entryId, $proposal, $authorId);
    if ($id <= 0) {
        cp_revision_reopen($mysqli, $revisionId);
        return ['ok' => false, 'error' => 'Approvazione non riuscita.'];
    }
    cp_revision_record($mysqli, $id, $authorId, $entryId ? 'edit' : 'new', (string)$revision['summary'], $staffId);
    cp_revision_drop($mysqli, $revisionId);
    // Le foto della proposta che lo staff ha tolto sistemandola.
    foreach (array_diff(cp_data_uploads($revision['data_array']), cp_data_uploads($proposal)) as $path) {
        cp_forget_unused_upload($mysqli, $path);
    }

    $title = cp_clip_title((string)($proposal['fields']['title'] ?? ''));
    $entryRow = cp_fetch_entry($mysqli, null, (string)$id, true, true);
    $urlIt = 'https://cripsum.com' . cp_entry_url($entryRow ?? [], 'it');
    $urlEn = 'https://cripsum.com' . cp_entry_url($entryRow ?? [], 'en');
    if ($authorId && $authorId !== $staffId) {
        $isNew = $revision['kind'] === 'new';
        cp_notify(
            $mysqli,
            $authorId,
            $isNew ? 'La tua voce è online' : 'La tua modifica è online',
            $isNew ? 'Your entry is live' : 'Your edit is live',
            ($isNew ? "«{$title}» ora è nella Cripsumpedia. Grazie!" : "La tua modifica a «{$title}» è stata approvata. Grazie!") . "\n\n" . $urlIt,
            ($isNew ? "\"{$title}\" is now in the Cripsumpedia. Thanks!" : "Your edit to \"{$title}\" was approved. Thanks!") . "\n\n" . $urlEn
        );
    }
    return ['ok' => true, 'entry_id' => $id, 'conflicts' => $conflicts, 'url' => cp_entry_url($entryRow ?? [], 'it')];
}

/** Se l'approvazione non va a buon fine, la proposta torna in attesa. */
function cp_revision_reopen(mysqli $mysqli, int $revisionId): void
{
    $stmt = $mysqli->prepare("UPDATE cripsumpedia_revisions SET status = 'pending', reviewer_id = NULL, reviewed_at = NULL WHERE id = ? AND status = 'reviewing'");
    if ($stmt) {
        $stmt->bind_param('i', $revisionId);
        $stmt->execute();
        $stmt->close();
    }
}

/** Rifiuta una proposta: il motivo arriva nella Posta di chi l'ha scritta. */
function cp_reject(mysqli $mysqli, int $revisionId, int $staffId, string $reason, string $note): array
{
    $reasons = cp_rejection_reasons();
    if (!isset($reasons[$reason])) return ['ok' => false, 'error' => 'Scegli un motivo.'];
    $note = mb_substr(cp_clean_line($note), 0, 500, 'UTF-8');
    if ($reason === 'other' && $note === '') return ['ok' => false, 'error' => 'Con «Altro» scrivi il motivo nella nota.'];

    $revision = cp_revision_find($mysqli, $revisionId);
    if (!$revision || $revision['status'] !== 'pending') return ['ok' => false, 'error' => 'Questa proposta è già stata gestita.'];
    $stmt = $mysqli->prepare("UPDATE cripsumpedia_revisions SET status = 'rejected', reviewer_id = ?, review_reason = ?, review_note = ?, reviewed_at = NOW()
                              WHERE id = ? AND status = 'pending'");
    if (!$stmt) return ['ok' => false, 'error' => 'Rifiuto non riuscito.'];
    $stmt->bind_param('issi', $staffId, $reason, $note, $revisionId);
    $stmt->execute();
    $mine = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$mine) return ['ok' => false, 'error' => 'Questa proposta è già stata gestita.'];

    foreach (cp_data_uploads($revision['data_array']) as $path) cp_forget_unused_upload($mysqli, $path);

    $authorId = (int)($revision['user_id'] ?? 0);
    if ($authorId > 0 && $authorId !== $staffId) {
        $title = cp_clip_title((string)$revision['title']);
        $isNew = $revision['kind'] === 'new';
        $why = $reason === 'other' ? ['it' => $note, 'en' => $note] : $reasons[$reason];
        $extraIt = $reason !== 'other' && $note !== '' ? "\n\nNota dello staff: {$note}" : '';
        $extraEn = $reason !== 'other' && $note !== '' ? "\n\nStaff note: {$note}" : '';
        cp_notify(
            $mysqli,
            $authorId,
            'La tua proposta non è stata pubblicata',
            'Your suggestion was not published',
            ($isNew ? "Lo staff non ha pubblicato la tua voce «{$title}»." : "Lo staff non ha pubblicato la tua modifica a «{$title}».") . "\nMotivo: {$why['it']}{$extraIt}\n\nPuoi riprenderla e mandarla di nuovo: https://cripsum.com/it/cripsumpedia/scrivi?riprendi={$revisionId}",
            ($isNew ? "The staff did not publish your entry \"{$title}\"." : "The staff did not publish your edit to \"{$title}\".") . "\nReason: {$why['en']}{$extraEn}\n\nYou can pick it up and send it again: https://cripsum.com/en/cripsumpedia/scrivi?riprendi={$revisionId}"
        );
    }
    return ['ok' => true];
}

/** Rimette una voce com'era in una versione della sua cronologia. */
function cp_restore(mysqli $mysqli, int $revisionId, int $staffId): array
{
    $revision = cp_revision_find($mysqli, $revisionId);
    if (!$revision || $revision['status'] !== 'approved' || !$revision['entry_id']) return ['ok' => false, 'error' => 'Versione non trovata.'];
    $entryId = (int)$revision['entry_id'];
    $entry = cp_fetch_entry($mysqli, null, (string)$entryId, true, true);
    if (!$entry || !empty($entry['deleted_at'])) return ['ok' => false, 'error' => 'La voce è eliminata: ripristinala prima dal pannello.'];

    cp_revision_baseline($mysqli, $entry);
    $id = cp_apply_data($mysqli, $entryId, $revision['data_array'], $staffId);
    if ($id <= 0) return ['ok' => false, 'error' => 'Ripristino non riuscito.'];
    // Come messaggio, la data della versione rimessa: la cronologia la scrive a parole.
    cp_revision_record($mysqli, $id, $staffId, 'restore', (string)$revision['created_at']);
    return ['ok' => true, 'entry_id' => $id];
}

/** Nasconde una voce (resta nel database con la sua cronologia). */
function cp_hide_entry(mysqli $mysqli, int $entryId, int $staffId): bool
{
    $stmt = $mysqli->prepare("UPDATE cripsumpedia_entries SET status = 'archived', deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $staffId, $entryId);
    $stmt->execute();
    $hidden = $stmt->affected_rows > 0;
    $stmt->close();
    if ($hidden) cp_revision_record($mysqli, $entryId, $staffId, 'hide');
    return $hidden;
}

/** Rimette online una voce nascosta, com'era nell'ultima versione prima di nasconderla. */
function cp_unhide_entry(mysqli $mysqli, int $entryId, int $staffId): bool
{
    $stmt = $mysqli->prepare("SELECT data FROM cripsumpedia_revisions WHERE entry_id = ? AND status = 'approved' AND kind <> 'hide' ORDER BY id DESC LIMIT 1");
    $status = 'published';
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
        $data = $row ? json_decode((string)$row[0], true) : null;
        if (is_array($data) && in_array($data['fields']['status'] ?? '', ['published', 'draft'], true)) $status = $data['fields']['status'];
    }
    $stmt = $mysqli->prepare('UPDATE cripsumpedia_entries SET status = ?, deleted_at = NULL, updated_by = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NOT NULL');
    if (!$stmt) return false;
    $stmt->bind_param('sii', $status, $staffId, $entryId);
    $stmt->execute();
    $done = $stmt->affected_rows > 0;
    $stmt->close();
    if ($done) cp_revision_record($mysqli, $entryId, $staffId, 'restore');
    return $done;
}

/** Cancella per sempre una voce nascosta, con tutto quello che la riguarda (solo l'owner). */
function cp_purge_entry(mysqli $mysqli, int $entryId): bool
{
    $entry = cp_fetch_entry($mysqli, null, (string)$entryId, true, true);
    if (!$entry || empty($entry['deleted_at'])) return false;
    $paths = [(string)($entry['image_url'] ?? ''), (string)($entry['banner_url'] ?? '')];
    foreach ([
        'DELETE FROM cripsumpedia_mentions WHERE entry_id = ? OR target_id = ?',
        'DELETE FROM cripsumpedia_slug_redirects WHERE entry_id = ? OR entry_id = ?',
        'DELETE FROM cripsumpedia_views_daily WHERE entry_id = ? OR entry_id = ?',
        'DELETE FROM cripsumpedia_revisions WHERE entry_id = ? OR entry_id = ?',
        'DELETE FROM cripsumpedia_entries WHERE id = ? OR id = ?',
    ] as $sql) {
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('ii', $entryId, $entryId);
        $stmt->execute();
        $stmt->close();
    }
    foreach ($paths as $path) cp_forget_unused_upload($mysqli, $path);
    return true;
}

// ── Liste per lo staff e per la cronologia ──────────────────────────────────

function cp_pending_count(mysqli $mysqli): int
{
    if (!cp_v2_ready($mysqli)) return 0;
    $result = $mysqli->query("SELECT COUNT(*) FROM cripsumpedia_revisions WHERE status = 'pending'");
    return $result ? (int)($result->fetch_row()[0] ?? 0) : 0;
}

/** Le proposte in attesa, dalla più vecchia. */
function cp_pending_list(mysqli $mysqli, int $limit = 50): array
{
    if (!cp_v2_ready($mysqli)) return [];
    // Un'approvazione rimasta a metà (pagina chiusa, errore) torna in coda.
    $mysqli->query("UPDATE cripsumpedia_revisions SET status = 'pending', reviewer_id = NULL, reviewed_at = NULL
                    WHERE status = 'reviewing' AND reviewed_at < NOW() - INTERVAL 10 MINUTE");
    $stmt = $mysqli->prepare("SELECT r.id, r.entry_id, r.kind, r.entry_type, r.title, r.summary, r.created_at, r.user_id, u.username
                              FROM cripsumpedia_revisions r LEFT JOIN utenti u ON u.id = r.user_id
                              WHERE r.status = 'pending' ORDER BY r.created_at ASC, r.id ASC LIMIT ?");
    if (!$stmt) return [];
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();
    return $rows;
}

/** La cronologia di una voce: le versioni approvate, dalla più recente. */
function cp_entry_history(mysqli $mysqli, int $entryId, int $limit = 100): array
{
    if (!cp_v2_ready($mysqli)) return [];
    $stmt = $mysqli->prepare("SELECT r.id, r.kind, r.summary, r.created_at, r.user_id, u.username, u.display_name, r.reviewer_id
                              FROM cripsumpedia_revisions r LEFT JOIN utenti u ON u.id = r.user_id
                              WHERE r.entry_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT ?");
    if (!$stmt) return [];
    $stmt->bind_param('ii', $entryId, $limit);
    $stmt->execute();
    $rows = cp_stmt_all($stmt);
    $stmt->close();
    return $rows;
}

/** Quante versioni ha la cronologia di una voce (per «Cronologia (n)»). */
function cp_history_count(mysqli $mysqli, int $entryId): int
{
    if (!cp_v2_ready($mysqli)) return 0;
    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM cripsumpedia_revisions WHERE entry_id = ? AND status = 'approved'");
    if (!$stmt) return 0;
    $stmt->bind_param('i', $entryId);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    return $count;
}

/** La versione approvata che viene prima di questa (per il confronto). */
function cp_previous_revision(mysqli $mysqli, array $revision): ?array
{
    if (!$revision['entry_id']) return null;
    $stmt = $mysqli->prepare("SELECT id FROM cripsumpedia_revisions WHERE entry_id = ? AND status = 'approved' AND id < ? ORDER BY id DESC LIMIT 1");
    if (!$stmt) return null;
    $entryId = (int)$revision['entry_id'];
    $id = (int)$revision['id'];
    $stmt->bind_param('ii', $entryId, $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $row ? cp_revision_find($mysqli, (int)$row[0]) : null;
}

// ── Import da Chi siamo ─────────────────────────────────────────────────────

/** I membri di Chi siamo che non hanno ancora una voce. */
function cp_chisiamo_candidates(mysqli $mysqli): array
{
    if (!cp_table_exists($mysqli, 'team_membri')) return [];
    $members = [];
    $result = $mysqli->query('SELECT * FROM team_membri WHERE visibile = 1 ORDER BY posizione ASC, id ASC');
    if (!$result) return [];
    while ($row = $result->fetch_assoc()) $members[] = $row;

    $out = [];
    foreach ($members as $member) {
        $name = trim((string)$member['nome']);
        $profile = (int)($member['profilo_utente_id'] ?? 0);
        $slug = cp_slugify($name);
        $stmt = $mysqli->prepare("SELECT 1 FROM cripsumpedia_entries e
                                  WHERE e.entry_type = 'person' AND (e.slug = ? OR e.title = ? OR (? > 0 AND e.profile_user_id = ?)
                                        OR EXISTS (SELECT 1 FROM cripsumpedia_aliases a WHERE a.entry_id = e.id AND a.alias = ?))
                                  LIMIT 1");
        if (!$stmt) continue;
        $stmt->bind_param('ssiis', $slug, $name, $profile, $profile, $name);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        if (!$exists) $out[] = $member;
    }
    return $out;
}

/** La prima frase di un testo, senza markdown, come riga «in breve». */
function cp_first_sentence(string $text): string
{
    $plain = trim((string)preg_replace(['~\[([^\]]+)\]\([^)]*\)~u', '~[*_`>#]+~u', '~\s+~u'], ['$1', '', ' '], $text));
    if (preg_match('~^(.{10,}?[.!?])(\s|$)~u', $plain, $m)) $plain = $m[1];
    return cp_len($plain) > CP_LIMITS['subtitle'] ? rtrim(mb_substr($plain, 0, CP_LIMITS['subtitle'] - 1, 'UTF-8')) . '…' : $plain;
}

/**
 * Crea una bozza (voce non pubblicata) per ogni membro di Chi siamo che non
 * ha ancora una voce: foto, frase, profilo collegato. Lo staff poi le
 * controlla e le pubblica. Si può rilanciare: chi ha già una voce si salta.
 */
function cp_import_chisiamo(mysqli $mysqli, int $staffId): array
{
    $created = [];
    foreach (cp_chisiamo_candidates($mysqli) as $member) {
        $data = cp_empty_data('person');
        $f = &$data['fields'];
        $f['title'] = mb_substr(trim((string)$member['nome']), 0, CP_LIMITS['title'], 'UTF-8');
        $f['status'] = 'draft';
        $description = trim((string)($member['descrizione'] ?? ''));
        $descriptionEn = trim((string)($member['descrizione_en'] ?? ''));
        $tag = trim((string)($member['tag'] ?? ''));
        $tagEn = trim((string)($member['tag_en'] ?? ''));
        $f['subtitle'] = $tag !== '' ? mb_substr($tag, 0, CP_LIMITS['subtitle'], 'UTF-8') : cp_first_sentence($description);
        $f['subtitle_en'] = $tagEn !== '' ? mb_substr($tagEn, 0, CP_LIMITS['subtitle'], 'UTF-8') : ($descriptionEn !== '' ? cp_first_sentence($descriptionEn) : '');
        $f['content_md'] = $description;
        $f['content_md_en'] = $descriptionEn;
        $f['image_url'] = trim((string)($member['foto'] ?? ''));
        $f['profile_user_id'] = (int)($member['profilo_utente_id'] ?? 0) ?: null;
        unset($f);

        $id = cp_apply_data($mysqli, null, $data, $staffId);
        if ($id <= 0) continue;
        cp_revision_record($mysqli, $id, $staffId, 'import', 'Importata da Chi siamo');
        $created[] = ['id' => $id, 'title' => $data['fields']['title']];
    }
    return $created;
}
