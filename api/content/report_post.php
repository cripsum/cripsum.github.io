<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/discord_notify.php';

try {
    cv2_check_csrf();
    $user = cv2_require_login($mysqli);
    cv2_rate_limit('cv2_last_report', 20, 'Stai segnalando troppo velocemente.');

    if (!cv2_table_exists($mysqli, 'content_reports')) cv2_fail('Tabella segnalazioni mancante. Esegui SQL upgrade.', 500);

    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $id = (int)($input['id'] ?? 0);
    $reason = cv2_validate_text((string)($input['reason'] ?? ''), 500, 'Motivo');

    if ($id <= 0) cv2_fail('ID non valido.');
    if ($reason === '') cv2_fail('Inserisci un motivo.');

    $stmt = $mysqli->prepare("
        INSERT INTO content_reports (content_type, post_id, user_id, reason, status, created_at)
        VALUES (?, ?, ?, ?, 'open', NOW())
        ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = 'open', created_at = NOW()
    ");
    if (!$stmt) cv2_fail('Query segnalazione non valida.', 500);

    $stmt->bind_param('siis', $type, $id, $user['id'], $reason);
    $stmt->execute();
    $stmt->close();

    $postTitle = '';
    $postDesc = '';
    $authorUsername = '';
    $authorUserId = 0;
    $mediaMime = '';

    if ($type === 'rimasto') {
        $pStmt = $mysqli->prepare("
            SELECT t.titolo, t.descrizione, t.tipo_foto_rimasto AS media_mime,
                   t.id_utente AS author_user_id, u.username
            FROM toprimasti t 
            LEFT JOIN utenti u ON t.id_utente = u.id 
            WHERE t.id = ? LIMIT 1
        ");
    } else {
        $pStmt = $mysqli->prepare("
            SELECT s.titolo, s.descrizione, s.tipo_foto_shitpost AS media_mime,
                   s.id_utente AS author_user_id, u.username
            FROM shitposts s 
            LEFT JOIN utenti u ON s.id_utente = u.id 
            WHERE s.id = ? LIMIT 1
        ");
    }

    if ($pStmt) {
        $pStmt->bind_param('i', $id);
        $pStmt->execute();
        $res = $pStmt->get_result();
        if ($postRow = $res->fetch_assoc()) {
            $postTitle = $postRow['titolo'] ?? '';
            $postDesc = $postRow['descrizione'] ?? '';
            $authorUsername = $postRow['username'] ?? '';
            $authorUserId = (int)($postRow['author_user_id'] ?? 0);
            $mediaMime = strtolower(trim((string)($postRow['media_mime'] ?? '')));
        }
        $pStmt->close();
    }

    $postUrl = "https://cripsum.com/it/" . ($type === 'rimasto' ? 'rimasti' : 'shitpost') . "?post=" . $id;
    $targetName = ucfirst($type) . " #{$id}" . ($postTitle ? " - \"{$postTitle}\"" : "");

    $discordSent = notifyDiscordSupportReport($type, [
        'target_id' => $id,
        'target_name' => $targetName,
        'target_author' => $authorUsername,
        'target_author_id' => $authorUserId,
        'content_snippet' => $postDesc ?: $postTitle,
        'target_url' => $postUrl,
        'media_url' => str_starts_with($mediaMime, 'image/')
            ? "https://cripsum.com/api/content/get_media.php?id={$id}&type=" . rawurlencode($type)
            : null,
        'reason' => $reason,
        'reporter_id' => $user['id'],
        'reporter_username' => $user['username'] ?? null,
        'reporter_role' => $user['ruolo'] ?? null,
        'reporter_discord_id' => $user['discord_id'] ?? null
    ]);

    if (!$discordSent) cv2_fail('Segnalazione salvata, ma non è stato possibile avvisare il supporto Discord.', 502);
    cv2_ok(['message' => 'Segnalazione inviata.']);
} catch (Throwable $e) {
    cv2_fail('Errore segnalazione: ' . $e->getMessage(), 500);
}
