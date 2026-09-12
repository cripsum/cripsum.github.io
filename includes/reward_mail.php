<?php
declare(strict_types=1);

/**
 * Invio di posta con premi, nello stesso formato del pannello admin
 * (api/admin/messages.php): l'utente riceve un messaggio in posta e riscuote
 * il premio da li'.
 *
 * Lo usa il bot Discord per consegnare i premi dei giveaway, cosi' i premi
 * vinti su Discord seguono esattamente la strada di quelli mandati a mano.
 */

require_once __DIR__ . '/security_helpers.php';

/** Tipi di premio accettati, gli stessi di site_message_rewards. */
function cripsum_reward_types(): array
{
    return ['points', 'godoshards', 'character', 'badge', 'premium'];
}

/**
 * Normalizza e valida un elenco di premi.
 *
 * @param array<int, array<string, mixed>> $rewards
 * @return array{ok: bool, rewards: array<int, array{type: string, value: string, quantity: int}>, error: string|null}
 */
function cripsum_normalize_rewards(array $rewards): array
{
    $allowed = cripsum_reward_types();
    $clean = [];

    foreach ($rewards as $reward) {
        if (!is_array($reward)) {
            continue;
        }

        $type = trim((string)($reward['type'] ?? ''));
        $value = trim((string)($reward['value'] ?? ''));
        $quantity = (int)($reward['quantity'] ?? 1);

        if ($type === '' && $value === '') {
            continue;
        }

        if (!in_array($type, $allowed, true)) {
            return ['ok' => false, 'rewards' => [], 'error' => "Tipo premio non valido: $type"];
        }

        if ($value === '') {
            return ['ok' => false, 'rewards' => [], 'error' => "Il premio $type non ha un valore."];
        }

        // Per i premi numerici il valore deve essere un numero positivo.
        if (in_array($type, ['points', 'godoshards'], true)) {
            if (!ctype_digit($value) || (int)$value <= 0) {
                return ['ok' => false, 'rewards' => [], 'error' => "Il premio $type vuole un numero positivo."];
            }
        }

        $clean[] = [
            'type' => $type,
            'value' => mb_substr($value, 0, 190),
            'quantity' => max(1, min(999, $quantity)),
        ];
    }

    if (!$clean) {
        return ['ok' => false, 'rewards' => [], 'error' => 'Nessun premio valido.'];
    }

    return ['ok' => true, 'rewards' => $clean, 'error' => null];
}

/**
 * Crea un messaggio di posta con i suoi premi e lo consegna ai destinatari.
 *
 * @param int[] $recipientIds
 * @param array<int, array{type: string, value: string, quantity: int}> $rewards
 * @return array{ok: bool, message_id: int|null, error: string|null}
 */
function cripsum_send_reward_mail(
    mysqli $mysqli,
    ?int $senderId,
    array $recipientIds,
    string $titleIt,
    string $titleEn,
    string $contentIt,
    string $contentEn,
    array $rewards = [],
    // Questa funzione manda posta con un premio dentro: la categoria giusta e'
    // "rewards". Il valore predefinito era 'system', e chi la chiamava senza
    // pensarci si ritrovava il messaggio fra gli avvisi automatici.
    string $category = 'rewards'
): array {
    $recipientIds = array_values(array_unique(array_filter(array_map('intval', $recipientIds), fn($id) => $id > 0)));

    if (!$recipientIds) {
        return ['ok' => false, 'message_id' => null, 'error' => 'Nessun destinatario.'];
    }

    if (!auth_table_exists($mysqli, 'site_messages') || !auth_table_exists($mysqli, 'site_message_recipients')) {
        return ['ok' => false, 'message_id' => null, 'error' => 'Le tabelle della posta non esistono.'];
    }

    $hasRewardsTable = auth_table_exists($mysqli, 'site_message_rewards');
    if ($rewards && !$hasRewardsTable) {
        return ['ok' => false, 'message_id' => null, 'error' => 'La tabella site_message_rewards non esiste.'];
    }

    $targetType = count($recipientIds) > 1 ? 'group' : 'single';

    $mysqli->begin_transaction();

    try {
        $stmt = $mysqli->prepare(
            'INSERT INTO site_messages (sender_id, title_it, title_en, content_it, content_en, category, target_type)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new RuntimeException('Query del messaggio non valida.');
        }

        $stmt->bind_param('issssss', $senderId, $titleIt, $titleEn, $contentIt, $contentEn, $category, $targetType);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Impossibile creare il messaggio.');
        }

        $messageId = (int)$mysqli->insert_id;
        $stmt->close();

        if ($rewards) {
            $stmtReward = $mysqli->prepare(
                'INSERT INTO site_message_rewards (message_id, reward_type, reward_value, quantity)
                 VALUES (?, ?, ?, ?)'
            );

            if (!$stmtReward) {
                throw new RuntimeException('Query dei premi non valida.');
            }

            foreach ($rewards as $reward) {
                $stmtReward->bind_param('issi', $messageId, $reward['type'], $reward['value'], $reward['quantity']);
                if (!$stmtReward->execute()) {
                    $stmtReward->close();
                    throw new RuntimeException('Impossibile salvare un premio.');
                }
            }

            $stmtReward->close();
        }

        $stmtRecipient = $mysqli->prepare(
            'INSERT IGNORE INTO site_message_recipients (message_id, recipient_id) VALUES (?, ?)'
        );

        if (!$stmtRecipient) {
            throw new RuntimeException('Query dei destinatari non valida.');
        }

        foreach ($recipientIds as $recipientId) {
            $stmtRecipient->bind_param('ii', $messageId, $recipientId);
            $stmtRecipient->execute();
        }

        $stmtRecipient->close();
        $mysqli->commit();

        return ['ok' => true, 'message_id' => $messageId, 'error' => null];
    } catch (Throwable $e) {
        if ($mysqli->errno) {
            @$mysqli->rollback();
        }

        return ['ok' => false, 'message_id' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Descrizione leggibile di un premio, per gli annunci su Discord.
 *
 * @param array{type: string, value: string, quantity: int} $reward
 */
function cripsum_reward_label(array $reward): string
{
    $quantity = (int)($reward['quantity'] ?? 1);
    $value = (string)($reward['value'] ?? '');
    $suffix = $quantity > 1 ? " ×$quantity" : '';

    switch ($reward['type'] ?? '') {
        case 'points':
            return number_format((int)$value, 0, ',', '.') . ' Godos';
        case 'godoshards':
            return number_format((int)$value, 0, ',', '.') . ' Godo Shards';
        case 'character':
            return 'Personaggio ' . $value . $suffix;
        case 'badge':
            return 'Badge #' . $value . $suffix;
        case 'premium':
            return 'Cripsum Premium';
        default:
            return $value . $suffix;
    }
}
