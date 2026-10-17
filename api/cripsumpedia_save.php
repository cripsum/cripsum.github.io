<?php
declare(strict_types=1);

/**
 * Cripsumpedia — scrivere (fase 4). Tutto in POST con il token CSRF.
 *
 *   favorite      aggiunge o toglie una voce dai preferiti
 *   draft         salva la bozza di chi scrive (il modulo la manda da solo)
 *   submit        lo staff pubblica, gli altri mandano la proposta allo staff
 *   discard       butta una bozza
 *   withdraw      una proposta in attesa torna bozza
 *   upload        carica una foto (ridisegnata, senza dati GPS)
 *   preview       il testo come si vedrà nella voce
 *   check_title   esiste già una voce con questo titolo?
 */

require_once __DIR__ . '/../cripsumpedia/_bootstrap.php';

$lang = cp_detect_lang();
$input = cp_read_input();
$action = (string)($input['action'] ?? $_GET['action'] ?? '');
$en = $lang === 'en';
$say = static fn(string $it, string $enText): string => $en ? $enText : $it;

if (!cp_schema_ready($mysqli)) {
    cp_json(['ok' => false, 'message' => cp_t('install_title', $lang)], 503);
}

// Le «Reaction» non ci sono più: erano l'unica scrittura della Cripsumpedia
// senza login né CSRF, e con uno script si gonfiava il punteggio di una voce.
if ($action === 'react') {
    cp_json(['ok' => false, 'message' => 'Funzione non più disponibile.'], 410);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cp_json(['ok' => false, 'message' => 'Metodo non consentito.'], 405);
}
if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    cp_json(['ok' => false, 'message' => cp_cannot_write_text('login', $lang, $mysqli)], 401);
}
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
if (!cp_validate_csrf(is_string($csrf) ? $csrf : '')) {
    cp_json(['ok' => false, 'message' => $say('Sessione scaduta. Ricarica la pagina.', 'Session expired. Reload the page.')], 419);
}
$userId = (int)($_SESSION['user_id'] ?? 0);
$staff = cp_is_admin_user();

if ($action === 'favorite') {
    $entryId = (int)($input['entry_id'] ?? 0);
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

    if (!cp_fetch_entry($mysqli, null, (string)$entryId)) {
        cp_json(['ok' => false, 'message' => cp_t('no_results', $lang)], 404);
    }
    $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_favorites (entry_id, user_id, created_at) VALUES (?, ?, NOW())");
    if (!$stmt) cp_json(['ok' => false, 'message' => 'Errore preferito.'], 500);
    $stmt->bind_param('ii', $entryId, $userId);
    $stmt->execute();
    $stmt->close();
    $bump = cp_v2_ready($mysqli) ? '' : ', trending_score = trending_score + 2';
    $mysqli->query("UPDATE cripsumpedia_entries SET favorites_count = favorites_count + 1$bump WHERE id = " . (int)$entryId);
    cp_json(['ok' => true, 'state' => 'added']);
}

if (!cp_v2_ready($mysqli)) {
    cp_json(['ok' => false, 'message' => cp_cannot_write_text('migration', $lang, $mysqli)], 503);
}

// Buttare una bozza o ritirare una proposta si può sempre, anche se intanto
// le proposte sono state chiuse.
if ($action === 'discard') {
    $ok = cp_discard_draft($mysqli, $userId, (int)($input['draft_id'] ?? 0));
    cp_json($ok ? ['ok' => true] : ['ok' => false, 'message' => $say('Questa bozza non c\'è più.', 'This draft is no longer available.')], $ok ? 200 : 404);
}
if ($action === 'withdraw') {
    $id = (int)($input['revision_id'] ?? 0);
    $ok = cp_withdraw_proposal($mysqli, $userId, $id);
    cp_json($ok
        ? ['ok' => true, 'url' => cp_url('write', ['bozza' => $id], $lang)]
        : ['ok' => false, 'message' => $say('Lo staff l\'ha già letta: non si può più ritirare.', 'The staff already read it: it can no longer be withdrawn.')], $ok ? 200 : 409);
}

[$canPropose, $why] = cp_can_propose($mysqli);
if (!$canPropose) {
    cp_json(['ok' => false, 'message' => cp_cannot_write_text($why, $lang, $mysqli)], 403);
}

// ── Foto ─────────────────────────────────────────────────────────────────────

if ($action === 'upload') {
    $file = $_FILES['file'] ?? null;
    if (!$file || is_array($file['name'] ?? null)) cp_json(['ok' => false, 'message' => $say('Nessuna foto ricevuta.', 'No photo received.')], 400);

    if (!$staff) {
        $today = cp_uploads_today($mysqli, $userId);
        if ($today === null) cp_json(['ok' => false, 'message' => $say('Per ora le foto le carica solo lo staff.', 'For now only the staff can upload photos.')], 503);
        if ($today >= CP_UPLOADS_PER_DAY) {
            cp_json(['ok' => false, 'message' => $say('Hai caricato troppe foto oggi: riprova domani.', 'You uploaded too many photos today: try again tomorrow.')], 429);
        }
    }

    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || (int)($file['size'] ?? 0) > 6 * 1024 * 1024) {
        cp_json(['ok' => false, 'message' => $say('Foto troppo grande (massimo 6 MB).', 'Photo too large (6 MB max).')], 413);
    }
    if ($error !== UPLOAD_ERR_OK) cp_json(['ok' => false, 'message' => $say('Caricamento non riuscito.', 'Upload failed.')], 400);

    $tmp = (string)$file['tmp_name'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
    if (!isset($allowed[$mime])) cp_json(['ok' => false, 'message' => $say('Va bene una foto JPG, PNG, GIF o WebP.', 'Use a JPG, PNG, GIF or WebP photo.')], 415);
    $ext = $allowed[$mime];

    $baseDir = realpath(__DIR__ . '/../img');
    if (!$baseDir) cp_json(['ok' => false, 'message' => 'Cartella img non trovata.'], 500);
    $targetDir = $baseDir . DIRECTORY_SEPARATOR . 'cripsumpedia' . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        cp_json(['ok' => false, 'message' => 'Impossibile creare la cartella delle foto.'], 500);
    }

    // Ridisegnata senza EXIF (niente posizione GPS) e al massimo 1600 px.
    $clean = cp_reencode_image($tmp, $ext);
    if ($clean === false) cp_json(['ok' => false, 'message' => $say('Questa foto non si riesce a leggere.', 'This photo cannot be read.')], 415);
    $name = mb_substr(cp_slugify(pathinfo((string)$file['name'], PATHINFO_FILENAME)), 0, 40, 'UTF-8');
    $fileName = trim($name, '-') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $targetDir . DIRECTORY_SEPARATOR . $fileName;
    $saved = is_string($clean) ? file_put_contents($target, $clean, LOCK_EX) !== false : move_uploaded_file($tmp, $target);
    if (!$saved) cp_json(['ok' => false, 'message' => $say('Caricamento non riuscito.', 'Upload failed.')], 500);

    $path = '/img/cripsumpedia/' . date('Y') . '/' . date('m') . '/' . $fileName;
    cp_record_upload($mysqli, $userId, $path);
    cp_json(['ok' => true, 'path' => $path]);
}

// ── Anteprima e titolo ───────────────────────────────────────────────────────

if ($action === 'preview') {
    $markdown = cp_clean_text($input['content_md'] ?? '');
    if (cp_len($markdown) > CP_LIMITS['content']) cp_json(['ok' => false, 'message' => $say('Testo troppo lungo.', 'Text too long.')], 413);
    $html = cp_resolve_wiki_links(cp_markdown_render($markdown), $mysqli, $lang);
    $html = cp_autolink_html($html, $mysqli, $lang, (int)($input['entry_id'] ?? 0));
    cp_json(['ok' => true, 'html' => $html]);
}

if ($action === 'check_title') {
    $matches = cp_title_matches($mysqli, (string)($input['title'] ?? ''), (int)($input['entry_id'] ?? 0), $staff);
    cp_json(['ok' => true, 'matches' => array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'title' => cp_i18n($row, 'title', $lang),
        'type' => cp_type_label((string)$row['entry_type'], $lang),
        'url' => cp_entry_url($row, $lang),
        'edit_url' => cp_edit_url($lang, (int)$row['id']),
        'draft' => $row['status'] !== 'published',
    ], $matches)]);
}

if ($action !== 'draft' && $action !== 'submit') {
    cp_json(['ok' => false, 'message' => 'Azione non valida.'], 400);
}

// ── Bozza e invio ────────────────────────────────────────────────────────────

$draftId = (int)($input['draft_id'] ?? 0) ?: null;
$draft = null;
if ($draftId) {
    $draft = cp_revision_find($mysqli, $draftId);
    if (!$draft || (int)$draft['user_id'] !== $userId || $draft['status'] !== 'draft') {
        cp_json(['ok' => false, 'message' => $say('Questa bozza non c\'è più: forse l\'hai mandata o buttata da un\'altra scheda.', 'This draft is gone: maybe you sent or deleted it from another tab.'), 'gone' => true], 409);
    }
    $entryId = $draft['entry_id'] !== null ? (int)$draft['entry_id'] : null;
} else {
    $entryId = (int)($input['entry_id'] ?? 0) ?: null;
}

$entry = null;
if ($entryId) {
    // Chi non è staff modifica solo voci pubblicate.
    $entry = cp_fetch_entry($mysqli, null, (string)$entryId, $staff, true);
    if (!$entry) cp_json(['ok' => false, 'message' => $say('Questa voce non c\'è più.', 'This entry is gone.')], 404);
    if (!empty($entry['deleted_at'])) cp_json(['ok' => false, 'message' => $say('Questa voce è stata eliminata.', 'This entry was deleted.')], 410);
    cp_revision_baseline($mysqli, $entry);
    $entry = cp_fetch_entry($mysqli, null, (string)$entryId, true, true) ?? $entry;
}

$base = cp_write_base($mysqli, $entry, (string)($input['entry_type'] ?? 'person'));
['data' => $data, 'errors' => $errors] = cp_data_from_input($mysqli, $input, $base, $staff, $lang);
$summary = mb_substr(cp_clean_line($input['summary'] ?? ''), 0, CP_LIMITS['summary'], 'UTF-8');
$baseRevisionId = $entry ? ((int)($entry['revision_id'] ?? 0) ?: null) : null;

if ($action === 'draft') {
    // Una bozza può essere a metà: manca il titolo, va bene lo stesso.
    if (($data['fields']['title'] ?? '') === '') unset($errors['title']);
    if ($errors) cp_json(['ok' => false, 'errors' => $errors, 'message' => reset($errors)], 422);
    $saved = cp_save_draft($mysqli, $userId, $draftId, $entryId, $baseRevisionId, $data, $summary, $lang);
    if (is_string($saved)) cp_json(['ok' => false, 'message' => $saved], 409);
    cp_json(['ok' => true, 'draft_id' => $saved, 'saved_at' => date('H:i')]);
}

// submit
if ($errors) cp_json(['ok' => false, 'errors' => $errors, 'message' => reset($errors)], 422);

if ($staff) {
    $id = cp_staff_publish($mysqli, $entryId, $data, $userId, $summary, $draftId);
    if ($id <= 0) cp_json(['ok' => false, 'message' => 'Salvataggio non riuscito.'], 500);
    $saved = cp_fetch_entry($mysqli, null, (string)$id, true);
    cp_json(['ok' => true, 'status' => 'published', 'id' => $id, 'url' => $saved ? cp_entry_url($saved, $lang) : cp_url('home', [], $lang)]);
}

if (!$entry) {
    $matches = cp_title_matches($mysqli, (string)$data['fields']['title'], 0, false, 1);
    if ($matches) {
        $match = $matches[0];
        cp_json([
            'ok' => false,
            'errors' => ['title' => $say('C\'è già una voce con questo nome: modifica quella.', 'There is already an entry with this name: edit that one.')],
            'message' => $say('C\'è già «' . cp_i18n($match, 'title', 'it') . '»: modifica quella.', 'There is already "' . cp_i18n($match, 'title', 'en') . '": edit that one.'),
            'match' => ['title' => cp_i18n($match, 'title', $lang), 'url' => cp_entry_url($match, $lang), 'edit_url' => cp_edit_url($lang, (int)$match['id'])],
        ], 409);
    }
} elseif (cp_revision_encode($data) === cp_revision_encode($base)) {
    cp_json(['ok' => false, 'message' => $say('Non hai cambiato niente.', 'You did not change anything.')], 422);
}
if (!$entry && trim((string)$data['fields']['content_md']) === '' && trim((string)$data['fields']['subtitle']) === '') {
    cp_json(['ok' => false, 'errors' => ['content_md' => $say('Scrivi almeno qualche riga.', 'Write at least a few lines.')], 'message' => $say('Scrivi almeno qualche riga.', 'Write at least a few lines.')], 422);
}

$saved = cp_save_draft($mysqli, $userId, $draftId, $entryId, $baseRevisionId, $data, $summary, $lang);
if (is_string($saved)) cp_json(['ok' => false, 'message' => $saved], 409);
$sent = cp_submit_proposal($mysqli, $userId, $saved, $summary, $lang);
if (is_string($sent)) cp_json(['ok' => false, 'message' => $sent, 'draft_id' => $saved], 429);
cp_json(['ok' => true, 'status' => 'pending', 'id' => $sent, 'url' => cp_url('write', ['inviata' => $sent], $lang)]);
