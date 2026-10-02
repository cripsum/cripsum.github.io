<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/discord_notify.php';

try {
    cv2_check_csrf();
    $user = cv2_require_login($mysqli);

    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $schema = cm_schema($mysqli, $type);

    if (!$schema['reports']) {
        cv2_fail(cm_t('Le segnalazioni non sono disponibili.', 'Reports are not available.'), 503);
    }

    $reason = cm_clip((string)($input['reason'] ?? ''), 500);
    if ($reason === '') {
        cv2_fail(cm_t('Scegli un motivo.', 'Pick a reason.'));
    }

    $post = cm_require_post($mysqli, $type, (int)($input['id'] ?? 0), $user, true);
    $id = $post['id'];
    $userId = (int)$user['id'];

    // Otto segnalazioni in dieci minuti bastano a chiunque.
    if (!cv2_is_admin($user) && cm_count($mysqli, 'SELECT COUNT(*) FROM content_reports WHERE user_id = ? AND created_at > NOW() - INTERVAL 10 MINUTE', 'i', [$userId]) >= 8) {
        cv2_fail(cm_t('Hai inviato troppe segnalazioni. Riprova più tardi.', 'You sent too many reports. Try again later.'), 429);
    }

    // Segnalare due volte lo stesso post aggiorna la segnalazione, non ne crea un'altra.
    $already = cm_count($mysqli, "SELECT COUNT(*) FROM content_reports WHERE content_type = ? AND post_id = ? AND user_id = ? AND status = 'open'", 'sii', [$type, $id, $userId]) > 0;

    $stmt = $mysqli->prepare("
        INSERT INTO content_reports (content_type, post_id, user_id, reason, status, created_at)
        VALUES (?, ?, ?, ?, 'open', NOW())
        ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = 'open', created_at = NOW()
    ");
    if (!$stmt) {
        throw new RuntimeException('segnalazione: ' . $mysqli->error);
    }
    $stmt->bind_param('siis', $type, $id, $userId, $reason);
    $stmt->execute();
    $stmt->close();

    // Al supporto su Discord arriva una volta sola per utente e per post.
    if (!$already) {
        $meta = cv2_meta($type);
        $stmt = $mysqli->prepare("
            SELECT p.descrizione, p.`{$meta['mime']}` AS mime, " . ($schema['poster'] ? '(p.anteprima IS NOT NULL)' : '0') . " AS has_poster, u.username
            FROM `{$meta['table']}` p
            LEFT JOIN utenti u ON u.id = p.id_utente
            WHERE p.id = ? LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        $mime = strtolower((string)($row['mime'] ?? ''));
        $mediaUrl = null;
        if (strncmp($mime, 'image/', 6) === 0) {
            $mediaUrl = 'https://cripsum.com/api/content/get_media.php?id=' . $id . '&type=' . rawurlencode($type);
        } elseif ((int)($row['has_poster'] ?? 0) === 1) {
            $mediaUrl = 'https://cripsum.com/api/content/get_media.php?id=' . $id . '&type=' . rawurlencode($type) . '&v=poster';
        }

        $sent = notifyDiscordSupportReport($type, [
            'target_id' => $id,
            'target_name' => ucfirst($type) . " #{$id}" . ($post['titolo'] !== '' ? " - \"{$post['titolo']}\"" : ''),
            'target_author' => (string)($row['username'] ?? ''),
            'target_author_id' => $post['id_utente'],
            'content_snippet' => (string)($row['descrizione'] ?? '') ?: $post['titolo'],
            'target_url' => 'https://cripsum.com' . cm_post_url($type, $id, 'it'),
            'media_url' => $mediaUrl,
            'reason' => $reason,
            'reporter_id' => $userId,
            'reporter_username' => $user['username'] ?? null,
            'reporter_role' => $user['ruolo'] ?? null,
            'reporter_discord_id' => $user['discord_id'] ?? null,
        ]);

        // La segnalazione è salvata e lo staff la vede nel pannello: se il bot
        // non risponde non è un errore per chi ha segnalato.
        if (!$sent) {
            error_log('[community] segnalazione ' . $type . ' #' . $id . ' non inoltrata a Discord');
        }
    }

    cv2_ok(['message' => cm_t('Segnalazione inviata. Grazie.', 'Report sent. Thank you.')]);
} catch (Throwable $e) {
    cm_crash('segnalazione', $e);
}
