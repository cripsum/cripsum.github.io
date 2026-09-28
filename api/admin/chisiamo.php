<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/admin/admin_shop_helpers.php';
require_once __DIR__ . '/../../includes/chisiamo/chisiamo.php';
require_once __DIR__ . '/../../includes/reward_mail.php';

/**
 * Chi siamo dal pannello (Pagine > Chi siamo).
 *
 * GET  ?action=list                  membri, social e numero di candidature nuove
 * GET  ?action=candidature           le candidature, le nuove per prime
 * GET  ?action=candidatura_foto&id=N la foto di una candidatura (non e' pubblica)
 * POST action = save_member | set_visible | reorder | delete_member
 *             | approve_candidatura | reject_candidatura | delete_candidatura
 *
 * I testi della testata e del riquadro "Unisciti al Team!" passano da
 * shop_content.php (pagina "chisiamo"), come quelli di Download e Merch.
 */

$action = admin_shop_action();
$adminId = (int)$adminUser['id'];

if (!admin_table_exists($mysqli, 'team_membri') || !admin_table_exists($mysqli, 'team_candidature')) {
    if ($action === 'list') {
        admin_ok(['ready' => false, 'message' => 'Tabelle di Chi siamo mancanti: applica migrations/2026_09_28_chisiamo_edits.sql e ricarica.']);
    }
    admin_fail('Tabelle di Chi siamo mancanti: applica la migrazione 2026_09_28_chisiamo_edits.sql.', 409);
}

/* ── Pezzi comuni ───────────────────────────────────────────────────── */

/** Il profilo scritto nel form (username) → id dell'account, o null se vuoto. */
function chisiamo_admin_profile(mysqli $mysqli, array $input): ?int
{
    $username = ltrim(trim((string)($input['profilo'] ?? '')), '@');
    if ($username === '') {
        return null;
    }

    $user = admin_shop_row($mysqli, 'SELECT id FROM utenti WHERE username = ? LIMIT 1', 's', [$username]);
    if (!$user) {
        admin_fail('Profilo Cripsum: l\'utente «' . $username . '» non esiste.');
    }

    return (int)$user['id'];
}

/** I social del form (social_instagram, social_tiktok...) → JSON, o null se sono tutti vuoti. */
function chisiamo_admin_socials(array $input): ?string
{
    $networks = chisiamo_social_networks();
    $links = [];

    foreach (CHISIAMO_SOCIALS as $key) {
        $value = trim((string)($input['social_' . $key] ?? ''));
        if ($value === '') {
            continue;
        }
        if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }
        if (mb_strlen($value) > 255 || !preg_match('~^https://[^\s]+$~i', $value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            admin_fail($networks[$key]['label'] . ': serve un link https completo.');
        }
        $links[$key] = $value;
    }

    return $links ? json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

function chisiamo_admin_member(mysqli $mysqli, int $id): array
{
    $row = admin_shop_row($mysqli, 'SELECT * FROM team_membri WHERE id = ? LIMIT 1', 'i', [$id]);
    if (!$row) {
        admin_fail('Membro non trovato.', 404);
    }

    return $row;
}

function chisiamo_admin_candidatura(mysqli $mysqli, int $id): array
{
    $row = admin_shop_row($mysqli, 'SELECT * FROM team_candidature WHERE id = ? LIMIT 1', 'i', [$id]);
    if (!$row) {
        admin_fail('Candidatura non trovata.', 404);
    }

    return $row;
}

/** La risposta a chi si e' candidato, nella posta del sito. Se non parte, la decisione resta comunque. */
function chisiamo_admin_notify(mysqli $mysqli, int $adminId, int $userId, bool $approved, string $reason = ''): bool
{
    if ($approved) {
        $it = "La tua candidatura per la pagina Chi siamo è stata accettata: benvenuto nel team! Tra poco comparirai nella pagina.";
        $en = "Your application for the About us page has been accepted: welcome to the team! You will appear on the page soon.";
    } else {
        $it = "La tua candidatura per la pagina Chi siamo non è stata accettata.";
        $en = "Your application for the About us page was not accepted.";
        if ($reason !== '') {
            $it .= "\n\nMotivo: " . $reason;
            $en .= "\n\nReason: " . $reason;
        }
        $it .= "\n\nPuoi mandarne un'altra quando vuoi.";
        $en .= "\n\nYou can send another one whenever you like.";
    }

    $result = cripsum_send_reward_mail(
        $mysqli,
        $adminId,
        [$userId],
        'Candidatura per Chi siamo',
        'About us application',
        $it,
        $en,
        [],
        'system'
    );

    return (bool)($result['ok'] ?? false);
}

/**
 * La foto di una candidatura approvata diventa pubblica: si copia in
 * img/team/ (una cartella del pannello, che si ripulisce da sola) e la
 * copia privata si cancella.
 */
function chisiamo_admin_publish_photo(?string $name): ?string
{
    $source = chisiamo_candidatura_foto_path($name);
    if ($source === null) {
        return null;
    }

    $dir = __DIR__ . '/../../img/team';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        admin_fail('Non riesco a creare la cartella img/team sul server.', 500);
    }

    $file = time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower(pathinfo($source, PATHINFO_EXTENSION));
    if (!@copy($source, $dir . '/' . $file)) {
        admin_fail('Non sono riuscito a copiare la foto della candidatura.', 500);
    }

    return '/img/team/' . $file;
}

try {
    if ($action === 'list') {
        $members = chisiamo_member_rows($mysqli, false);
        foreach ($members as &$member) {
            $member['foto_url'] = shop_asset_url($member['foto'] ?? '');
            $member['profilo'] = (string)($member['profilo_username'] ?? '');
            $socials = json_decode((string)($member['social'] ?? ''), true);
            foreach (CHISIAMO_SOCIALS as $key) {
                $member['social_' . $key] = is_array($socials) ? (string)($socials[$key] ?? '') : '';
            }
            $member['social_count'] = is_array($socials) ? count($socials) : 0;
        }
        unset($member);

        $networks = chisiamo_social_networks();
        $pending = admin_shop_row($mysqli, "SELECT COUNT(*) AS n FROM team_candidature WHERE stato = 'nuova'");

        admin_ok([
            'ready' => true,
            'members' => $members,
            'socials' => array_map(static fn(string $k): array => ['key' => $k] + $networks[$k], CHISIAMO_SOCIALS),
            'pending' => (int)($pending['n'] ?? 0),
        ]);
    }

    if ($action === 'candidature') {
        $rows = admin_shop_rows(
            $mysqli,
            "SELECT c.id, c.utente_id, c.nome, c.descrizione, c.social_nome, c.social_link, c.stato, c.membro_id,
                    c.created_at, c.deciso_at, (c.foto IS NOT NULL AND c.foto <> '') AS ha_foto, u.username
             FROM team_candidature c
             LEFT JOIN utenti u ON u.id = c.utente_id
             ORDER BY (c.stato = 'nuova') DESC, c.created_at DESC
             LIMIT 200"
        );
        admin_ok(['candidature' => $rows]);
    }

    if ($action === 'candidatura_foto') {
        $row = chisiamo_admin_candidatura($mysqli, (int)($_GET['id'] ?? 0));
        $path = chisiamo_candidatura_foto_path($row['foto']);
        if ($path === null) {
            admin_fail('Foto non disponibile.', 404);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        header('Content-Type: ' . (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mime : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit();
    }

    admin_shop_require_post();
    $input = admin_input();

    switch ($action) {
        case 'save_member':
            $id = (int)($input['id'] ?? 0);
            $existing = $id > 0 ? chisiamo_admin_member($mysqli, $id) : null;

            $fields = [
                'nome' => admin_shop_text($input, 'nome', 'Nome', 60, true),
                'foto' => admin_shop_image($input, 'foto', 'Foto'),
                'profilo_utente_id' => chisiamo_admin_profile($mysqli, $input),
                'link' => admin_shop_link($input, 'link', 'Link esterno'),
                'tag' => admin_shop_text($input, 'tag', 'Tag (IT)', 60),
                'tag_en' => admin_shop_text($input, 'tag_en', 'Tag (EN)', 60),
                'descrizione' => admin_shop_text($input, 'descrizione', 'Descrizione (IT)', 1500, true),
                'descrizione_en' => admin_shop_text($input, 'descrizione_en', 'Descrizione (EN)', 1500),
                'social' => chisiamo_admin_socials($input),
                'visibile' => admin_shop_bool($input, 'visibile'),
            ];
            $types = 'ssissssssi';

            if ($existing) {
                $sets = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", array_keys($fields)));
                admin_shop_exec($mysqli, "UPDATE team_membri SET $sets WHERE id = ? LIMIT 1", $types . 'i', [...array_values($fields), $id], 'Salvataggio non riuscito.')->close();
                admin_media_cleanup($mysqli, [$existing['foto']], $adminId);
                admin_log($mysqli, $adminId, 'chisiamo_update_member', null, ['member_id' => $id, 'nome' => $fields['nome']]);
                admin_ok(['message' => 'Membro salvato.', 'id' => $id]);
            }

            $fields['posizione'] = admin_shop_next_position($mysqli, 'SELECT MAX(posizione) FROM team_membri');
            $columns = '`' . implode('`, `', array_keys($fields)) . '`';
            $marks = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = admin_shop_exec($mysqli, "INSERT INTO team_membri ($columns) VALUES ($marks)", $types . 'i', array_values($fields), 'Creazione non riuscita.');
            $newId = (int)$stmt->insert_id;
            $stmt->close();
            admin_log($mysqli, $adminId, 'chisiamo_create_member', null, ['member_id' => $newId, 'nome' => $fields['nome']]);
            admin_ok(['message' => 'Membro aggiunto.', 'id' => $newId]);

        case 'set_visible':
            $id = (int)($input['id'] ?? 0);
            chisiamo_admin_member($mysqli, $id);
            $visible = admin_shop_bool($input, 'visibile');
            admin_shop_exec($mysqli, 'UPDATE team_membri SET visibile = ? WHERE id = ? LIMIT 1', 'ii', [$visible, $id], 'Aggiornamento non riuscito.')->close();
            admin_log($mysqli, $adminId, 'chisiamo_visible_member', null, ['member_id' => $id, 'visibile' => $visible]);
            admin_ok(['message' => $visible ? 'Ora è visibile.' : 'Nascosto dalla pagina.']);

        case 'reorder':
            $ids = admin_shop_ids($input);
            $own = array_map('intval', array_column(admin_shop_rows($mysqli, 'SELECT id FROM team_membri'), 'id'));
            if (array_diff($ids, $own)) {
                admin_fail('Ordine non valido: ricarica la pagina.');
            }
            admin_shop_reorder($mysqli, 'team_membri', 'posizione', $ids);
            admin_log($mysqli, $adminId, 'chisiamo_reorder_members');
            admin_ok(['message' => 'Ordine aggiornato.']);

        case 'delete_member':
            $id = (int)($input['id'] ?? 0);
            $member = chisiamo_admin_member($mysqli, $id);
            admin_shop_exec($mysqli, 'DELETE FROM team_membri WHERE id = ? LIMIT 1', 'i', [$id], 'Eliminazione non riuscita.')->close();
            admin_media_cleanup($mysqli, [$member['foto']], $adminId);
            admin_log($mysqli, $adminId, 'chisiamo_delete_member', null, ['nome' => $member['nome']]);
            admin_ok(['message' => 'Membro eliminato.']);

        case 'approve_candidatura':
            $id = (int)($input['id'] ?? 0);
            $row = chisiamo_admin_candidatura($mysqli, $id);
            if ($row['stato'] !== 'nuova') {
                admin_fail('Questa candidatura è già stata decisa.');
            }

            $photo = chisiamo_admin_publish_photo($row['foto']);
            $socials = null;
            $link = trim((string)($row['social_link'] ?? ''));
            if ($link !== '' && preg_match('~^https://~i', $link)) {
                $socials = json_encode([chisiamo_social_key_from_url($link) => $link], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $profile = admin_shop_row($mysqli, 'SELECT id FROM utenti WHERE id = ? LIMIT 1', 'i', [(int)$row['utente_id']]);

            // Nasce nascosto: il pannello apre subito la sua scheda per
            // ritoccarla e metterla in pagina.
            $position = admin_shop_next_position($mysqli, 'SELECT MAX(posizione) FROM team_membri');
            $profileId = $profile ? (int)$profile['id'] : null;
            $stmt = admin_shop_exec(
                $mysqli,
                'INSERT INTO team_membri (nome, foto, profilo_utente_id, descrizione, social, visibile, posizione) VALUES (?, ?, ?, ?, ?, 0, ?)',
                'ssissi',
                [mb_substr((string)$row['nome'], 0, 60), $photo, $profileId, (string)$row['descrizione'], $socials, $position],
                'Non sono riuscito a creare il membro.'
            );
            $memberId = (int)$stmt->insert_id;
            $stmt->close();

            admin_shop_exec(
                $mysqli,
                "UPDATE team_candidature SET stato = 'approvata', membro_id = ?, foto = NULL, deciso_at = NOW() WHERE id = ? LIMIT 1",
                'ii',
                [$memberId, $id],
                'Non sono riuscito ad aggiornare la candidatura.'
            )->close();
            chisiamo_candidatura_delete_foto($row['foto']);

            $notified = $profile ? chisiamo_admin_notify($mysqli, $adminId, (int)$profile['id'], true) : false;
            admin_log($mysqli, $adminId, 'chisiamo_approve_application', $profileId, ['candidatura_id' => $id, 'member_id' => $memberId]);
            admin_ok([
                'message' => $notified ? 'Candidatura approvata: gli è arrivato un messaggio nella posta.' : 'Candidatura approvata.',
                'member_id' => $memberId,
            ]);

        case 'reject_candidatura':
            $id = (int)($input['id'] ?? 0);
            $row = chisiamo_admin_candidatura($mysqli, $id);
            if ($row['stato'] !== 'nuova') {
                admin_fail('Questa candidatura è già stata decisa.');
            }
            $reason = (string)(admin_shop_text($input, 'motivo', 'Motivo', 500) ?? '');

            // La foto non serve piu': si cancella subito invece di aspettare i 12 mesi.
            admin_shop_exec(
                $mysqli,
                "UPDATE team_candidature SET stato = 'rifiutata', foto = NULL, deciso_at = NOW() WHERE id = ? LIMIT 1",
                'i',
                [$id],
                'Non sono riuscito ad aggiornare la candidatura.'
            )->close();
            chisiamo_candidatura_delete_foto($row['foto']);

            $userId = (int)$row['utente_id'];
            $exists = admin_shop_row($mysqli, 'SELECT id FROM utenti WHERE id = ? LIMIT 1', 'i', [$userId]);
            $notified = $exists ? chisiamo_admin_notify($mysqli, $adminId, $userId, false, $reason) : false;
            admin_log($mysqli, $adminId, 'chisiamo_reject_application', $exists ? $userId : null, ['candidatura_id' => $id]);
            admin_ok(['message' => $notified ? 'Candidatura rifiutata: gli è arrivato un messaggio nella posta.' : 'Candidatura rifiutata.']);

        case 'delete_candidatura':
            $id = (int)($input['id'] ?? 0);
            $row = chisiamo_admin_candidatura($mysqli, $id);
            admin_shop_exec($mysqli, 'DELETE FROM team_candidature WHERE id = ? LIMIT 1', 'i', [$id], 'Eliminazione non riuscita.')->close();
            chisiamo_candidatura_delete_foto($row['foto']);
            admin_log($mysqli, $adminId, 'chisiamo_delete_application', null, ['candidatura_id' => $id, 'nome' => $row['nome']]);
            admin_ok(['message' => 'Candidatura eliminata.']);
    }

    admin_fail('Azione non riconosciuta.', 400);
} catch (Throwable $e) {
    error_log('[admin chisiamo] ' . $e->getMessage());
    admin_fail('Errore del server su Chi siamo.', 500);
}
