<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/content_moderation.php';

/*
 * Modifica di testo, tag e spoiler. I media non si cambiano.
 *
 * Se l'autore cambia titolo, descrizione o motivazione di un post già
 * online, il post torna in attesa: altrimenti basterebbe farsi approvare
 * un post innocuo e riscriverlo dopo. Tag e spoiler non fanno tornare
 * indietro. Lo staff, e chi pubblica senza controllo per le regole del
 * pannello, restano online.
 */
try {
    cv2_check_csrf();

    $user = cv2_require_login($mysqli);
    $userId = (int)$user['id'];
    $isAdmin = cv2_is_admin($user);
    $input = cv2_input();

    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $meta = cv2_meta($type);
    $schema = cm_schema($mysqli, $type);

    $post = cm_require_post($mysqli, $type, (int)($input['id'] ?? 0), $user, false);
    $id = $post['id'];

    if (!$isAdmin && $post['id_utente'] !== $userId) {
        cv2_fail(cm_t('Non puoi modificare questo post.', 'You cannot edit this post.'), 403);
    }

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

    $stmt = $mysqli->prepare("SELECT titolo, descrizione" . ($type === 'rimasto' ? ', motivazione' : '') . " FROM `{$meta['table']}` WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $before = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $textChanged = trim((string)($before['titolo'] ?? '')) !== $title
        || trim((string)($before['descrizione'] ?? '')) !== $description
        || ($type === 'rimasto' && trim((string)($before['motivazione'] ?? '')) !== $motivation);

    $sets = ['titolo = ?', 'descrizione = ?'];
    $types = 'ss';
    $params = [$title, $description];

    if ($type === 'rimasto') {
        $sets[] = 'motivazione = ?';
        $types .= 's';
        $params[] = $motivation;
    }
    if ($schema['tag']) {
        $sets[] = '`tag` = ?';
        $types .= 's';
        $params[] = cm_tags_store(cm_tags_parse($input['tags'] ?? ($input['tag'] ?? '')), $schema['tag_length']);
    }
    if ($schema['spoiler']) {
        $sets[] = 'is_spoiler = ?';
        $types .= 'i';
        $params[] = cv2_bool_int($input['is_spoiler'] ?? 0);
    }
    if ($schema['updated']) {
        $sets[] = 'updated_at = NOW()';
    }

    $params[] = $id;
    $types .= 'i';

    $stmt = $mysqli->prepare("UPDATE `{$meta['table']}` SET " . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('modifica post: ' . $mysqli->error);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('modifica post: ' . $error);
    }
    $stmt->close();

    $backToReview = false;
    if ($textChanged && $post['approvato'] === 1 && !cm_auto_approve($mysqli, $user)) {
        $outcome = cripsum_set_community_post_approval($mysqli, $type, $id, false, ['actor_id' => $userId, 'notify' => false]);
        $backToReview = $outcome['ok'] && $outcome['changed'];
    }

    if ($isAdmin && $post['id_utente'] !== $userId) {
        require_once __DIR__ . '/../../includes/admin/admin_helpers.php';
        admin_log($mysqli, $userId, $type === 'rimasto' ? 'update_toprimasti' : 'update_shitpost', $post['id_utente'], ['post_id' => $id, 'title' => $title, 'from' => 'page']);
    }

    cv2_ok([
        'message' => $backToReview
            ? cm_t('Modifica salvata. Il post torna online dopo un nuovo controllo.', 'Edit saved. The post goes back online after a new review.')
            : cm_t('Post aggiornato.', 'Post updated.'),
        'pending' => $backToReview,
    ]);
} catch (Throwable $e) {
    cm_crash('modifica post', $e);
}
