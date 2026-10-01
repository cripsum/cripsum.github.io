<?php
/**
 * Gruppi in cui posso invitare una certa persona: quelli dove ho il
 * permesso di invitare e lei non è già dentro. Vale solo per gli amici.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $targetId = (int)($_GET['target_id'] ?? 0);
    if ($targetId <= 0 || $targetId === $userId) {
        throw new ChatError(rt_t('Utente non valido.', 'Invalid user.'), 422);
    }
    if (!sc_are_friends($mysqli, $userId, $targetId)) {
        send_success(['groups' => [], 'reason' => 'not_friends']);
    }

    $stmt = $mysqli->prepare("
        SELECT c.id AS chat_id, c.name
        FROM chat_members m
        INNER JOIN chats c ON c.id = m.chat_id AND c.is_archived = 0
        LEFT JOIN chat_settings s ON s.chat_id = c.id
        WHERE m.user_id = ? AND m.status = 'active'
          AND (m.role IN ('owner', 'admin') OR COALESCE(s.invite_permission, 'everyone') = 'everyone')
          AND NOT EXISTS (
              SELECT 1 FROM chat_members m2
              WHERE m2.chat_id = c.id AND m2.user_id = ? AND m2.status IN ('active', 'invited')
          )
        ORDER BY c.name ASC
        LIMIT 100
    ");
    $stmt->bind_param('ii', $userId, $targetId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    send_success(['groups' => array_map(static fn($r) => ['chat_id' => (int)$r['chat_id'], 'name' => (string)$r['name']], $rows)]);
});
