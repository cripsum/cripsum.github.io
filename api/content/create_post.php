<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/community/media.php';
require_once __DIR__ . '/../../includes/content_moderation.php';

/*
 * Pubblica un post.
 *
 * I file arrivano in due modi:
 *   - media_ids: gli id dei file già caricati uno per uno con upload_media.php
 *     (più media per post, ognuno con la sua barra di avanzamento);
 *   - media: un solo file nella stessa richiesta, come prima. Resta per quando
 *     la tabella dei media aggiuntivi non c'è ancora.
 */
try {
    cv2_check_csrf();

    $user = cv2_require_login($mysqli);
    $userId = (int)$user['id'];
    $input = cv2_input();

    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $meta = cv2_meta($type);
    $schema = cm_schema($mysqli, $type);

    if (!$schema['ready']) {
        cv2_fail(cm_t('La sezione non è disponibile.', 'This section is not available.'), 503);
    }

    cm_check_can_post($mysqli, $type, $user);

    $title = trim((string)($input['titolo'] ?? ''));
    $description = trim((string)($input['descrizione'] ?? ''));
    $motivation = trim((string)($input['motivazione'] ?? ''));

    if ($title === '' || mb_strlen($title, 'UTF-8') > 120) {
        cv2_fail(cm_t('Scrivi un titolo (massimo 120 caratteri).', 'Write a title (120 characters at most).'));
    }
    if (mb_strlen($description, 'UTF-8') > 2000) {
        cv2_fail(cm_t('Descrizione troppo lunga: massimo 2000 caratteri.', 'Description too long: 2000 characters at most.'));
    }
    if (mb_strlen($motivation, 'UTF-8') > 2000) {
        cv2_fail(cm_t('Motivazione troppo lunga: massimo 2000 caratteri.', 'Motivation too long: 2000 characters at most.'));
    }
    if ($type === 'rimasto' && $motivation === '') {
        cv2_fail(cm_t('La motivazione è obbligatoria.', 'The motivation is required.'));
    }

    $tags = cm_tags_store(cm_tags_parse($input['tags'] ?? ($input['tag'] ?? '')), $schema['tag_length']);
    $isSpoiler = cv2_bool_int($input['is_spoiler'] ?? 0);

    $draftIds = $input['media_ids'] ?? null;
    if (is_string($draftIds)) {
        $draftIds = array_filter(explode(',', $draftIds));
    }

    $drafts = null;
    $media = null;
    $poster = null;
    $duration = null;

    if (is_array($draftIds) && $draftIds && $schema['extra']) {
        $drafts = cm_drafts_for_post($mysqli, $userId, $draftIds, cm_max_media($mysqli, $type));
    } else {
        $media = cm_process_upload('media');
        if ($media['kind'] === 'video') {
            $poster = cm_process_poster('poster');
            $media['width'] = max(0, min(8192, (int)($input['larghezza'] ?? 0))) ?: null;
            $media['height'] = max(0, min(8192, (int)($input['altezza'] ?? 0))) ?: null;
            $duration = max(0, min(3600, (int)($input['durata'] ?? 0))) ?: null;
        }
    }

    $mysqli->begin_transaction();

    $mime = $media['mime'] ?? '';
    $none = null;

    if ($type === 'rimasto') {
        $stmt = $mysqli->prepare('
            INSERT INTO toprimasti (id_utente, titolo, descrizione, motivazione, foto_rimasto, tipo_foto_rimasto, data_creazione, approvato, reazioni)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 0, 0)
        ');
        if (!$stmt) {
            throw new RuntimeException('nuovo rimasto: ' . $mysqli->error);
        }
        $stmt->bind_param('isssbs', $userId, $title, $description, $motivation, $none, $mime);
        $blobIndex = 4;
    } else {
        $stmt = $mysqli->prepare('
            INSERT INTO shitposts (id_utente, titolo, descrizione, foto_shitpost, tipo_foto_shitpost, data_creazione, approvato)
            VALUES (?, ?, ?, ?, ?, NOW(), 0)
        ');
        if (!$stmt) {
            throw new RuntimeException('nuovo shitpost: ' . $mysqli->error);
        }
        $stmt->bind_param('issbs', $userId, $title, $description, $none, $mime);
        $blobIndex = 3;
    }

    if ($media !== null) {
        cm_send_blob($stmt, $blobIndex, $media['blob']);
    }
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('nuovo post: ' . $error);
    }
    $postId = (int)$stmt->insert_id;
    $stmt->close();

    // Colonne arrivate con gli aggiornamenti: si scrivono solo se ci sono.
    $sets = [];
    $types = '';
    $params = [];

    if ($schema['tag']) {
        $sets[] = '`tag` = ?';
        $types .= 's';
        $params[] = $tags;
    }
    if ($schema['spoiler']) {
        $sets[] = 'is_spoiler = ?';
        $types .= 'i';
        $params[] = $isSpoiler;
    }
    if ($media !== null && $schema['dims']) {
        $sets[] = 'media_w = ?';
        $sets[] = 'media_h = ?';
        $sets[] = 'media_durata = ?';
        $types .= 'iii';
        array_push($params, $media['width'], $media['height'], $duration);
    }

    if ($sets) {
        $params[] = $postId;
        $types .= 'i';
        $stmt = $mysqli->prepare("UPDATE `{$meta['table']}` SET " . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('dettagli post: ' . $mysqli->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    if ($poster !== null && $schema['poster']) {
        $stmt = $mysqli->prepare("UPDATE `{$meta['table']}` SET anteprima = ? WHERE id = ? LIMIT 1");
        $stmt->bind_param('bi', $none, $postId);
        cm_send_blob($stmt, 0, $poster);
        $stmt->execute();
        $stmt->close();
    }

    if ($drafts !== null) {
        cm_drafts_attach($mysqli, $type, $postId, $userId, $drafts);
    }

    $mysqli->commit();

    // Lo staff e chi ha la fiducia delle regole vanno online subito: stessa
    // strada di un'approvazione, così annuncio e «nuovi post» partono da lì.
    $approved = false;
    if (cm_auto_approve($mysqli, $user)) {
        $outcome = cripsum_set_community_post_approval($mysqli, $type, $postId, true, ['actor_id' => $userId, 'notify' => false]);
        $approved = $outcome['ok'];
    }

    // Missioni: conta la pubblicazione, non l'approvazione. Uno shitpost è
    // anche «un post»: avanzano tutte e due, il Rewind lo conta una volta.
    if ($type === 'shitpost') {
        cm_track($mysqli, $userId, ['create_shitpost' => true, 'create_post' => false]);
    } else {
        cm_track($mysqli, $userId, ['create_post' => true]);
    }

    cv2_ok([
        'message' => $approved
            ? cm_t('Post pubblicato.', 'Post published.')
            : cm_t('Post inviato. Sarà visibile dopo l\'approvazione.', 'Post sent. It will be visible once approved.'),
        'post_id' => $postId,
        'approved' => $approved,
    ]);
} catch (Throwable $e) {
    @$mysqli->rollback();
    cm_crash('nuovo post', $e);
}
