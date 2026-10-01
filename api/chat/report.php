<?php
/**
 * Segnalazione di un messaggio della chat globale allo staff.
 *
 * La segnalazione vale appena è salvata: l'avviso su Discord è un di più, e
 * se Discord non risponde chi segnala non deve vedersi un errore.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_global.php';

chat_run(static function () use ($mysqli, $userId, $chatUser): void {
    $input = get_json_input();
    $messageId = (int)($input['id'] ?? 0);
    $reason = mb_substr(cc_clean_text((string)($input['reason'] ?? '')), 0, (int)CHAT_MAX_REPORT_REASON, 'UTF-8');
    if ($reason === '') {
        $reason = 'Segnalazione utente';
    }

    if (!rt_has_table($mysqli, 'chat_reports')) {
        throw new ChatError(rt_t('Le segnalazioni non sono disponibili.', 'Reports are not available.'), 503);
    }

    $stmt = $mysqli->prepare('SELECT m.user_id, m.message, m.deleted_at, u.username FROM messages m LEFT JOIN utenti u ON u.id = m.user_id WHERE m.id = ? LIMIT 1');
    $stmt->bind_param('i', $messageId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !empty($row['deleted_at'])) {
        throw new ChatError(rt_t('Messaggio non trovato.', 'Message not found.'), 404);
    }
    if ((int)$row['user_id'] === $userId) {
        throw new ChatError(rt_t('Non puoi segnalare un tuo messaggio.', 'You cannot report your own message.'), 422);
    }

    // Al massimo dieci segnalazioni all'ora a testa.
    $stmt = $mysqli->prepare('SELECT COUNT(*) FROM chat_reports WHERE reporter_id = ? AND created_at > NOW() - INTERVAL 1 HOUR');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $recent = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    if ($recent >= 10) {
        throw new ChatError(rt_t('Hai mandato molte segnalazioni: riprova più tardi.', 'You sent many reports: try again later.'), 429);
    }

    $stmt = $mysqli->prepare('INSERT INTO chat_reports (message_id, reporter_id, reason, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = "open", created_at = NOW()');
    $stmt->bind_param('iis', $messageId, $userId, $reason);
    $stmt->execute();
    $stmt->close();

    try {
        require_once __DIR__ . '/../../includes/discord_notify.php';
        if (function_exists('notifyDiscordSupportReport')) {
            notifyDiscordSupportReport('chat', [
                'target_id' => $messageId,
                'target_name' => "Messaggio Chat #{$messageId}",
                'target_author' => $row['username'] ?? "ID #{$row['user_id']}",
                'content_snippet' => $row['message'] ?? '',
                'target_url' => 'https://cripsum.com/it/global-chat?message=' . $messageId,
                'reason' => $reason,
                'reporter_id' => $userId,
                'reporter_username' => $chatUser['username'] ?? null,
                'reporter_role' => $chatUser['ruolo'] ?? null,
                'reporter_discord_id' => $chatUser['discord_id'] ?? null,
            ]);
        }
    } catch (Throwable $e) {
        error_log('[chat report] discord: ' . $e->getMessage());
    }

    send_success(['message' => rt_t('Segnalazione inviata. Grazie.', 'Report sent. Thank you.')]);
});
