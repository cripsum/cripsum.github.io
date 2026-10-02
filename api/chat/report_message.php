<?php
/**
 * Segnala un messaggio di una chat privata o di gruppo.
 *
 * Lo staff non legge queste chat. Chi segnala manda allo staff una copia di
 * quel messaggio (e, se lo sceglie, dei pochi che lo precedono): la copia
 * diventa un ticket di supporto «Segnalazione Utente», che lo staff vede
 * nella posta e nel thread Discord come ogni altro ticket, e lì può
 * rispondere. Non serve altro: niente accesso alla conversazione, nessuna
 * tabella in più.
 *
 * Più segnalazioni sulla stessa persona finiscono nello stesso ticket,
 * finché resta aperto. L'autore del messaggio non viene avvisato.
 *
 * POST { kind: private|group, message_id, reason, context: bool }
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/bot_client.php';
require_once __DIR__ . '/../../includes/ticket_helpers.php';

const REPORT_CONTEXT = 4;
const REPORT_PER_HOUR = 6;

chat_run(static function () use ($mysqli, $userId, $chatUser): void {
    $input = get_json_input();
    $kind = ($input['kind'] ?? '') === 'group' ? 'group' : 'private';
    $messageId = (int)($input['message_id'] ?? 0);
    $reason = mb_substr(cc_clean_text((string)($input['reason'] ?? '')), 0, 500, 'UTF-8');
    $withContext = !empty($input['context']);

    if (!rt_has_table($mysqli, 'site_tickets') || !rt_has_table($mysqli, 'site_ticket_messages')) {
        throw new ChatError(rt_t('Le segnalazioni non sono disponibili.', 'Reports are not available.'), 503);
    }
    if ($messageId <= 0) {
        throw new ChatError(rt_t('Messaggio non valido.', 'Invalid message.'), 422);
    }

    // Il messaggio si legge con gli stessi controlli della chat: si può
    // segnalare solo ciò che si può vedere.
    if ($kind === 'group') {
        $context = cg_message_context($mysqli, $messageId, $userId);
        $chatId = (int)$context['message']['chat_id'];
        $target = cg_one($mysqli, $userId, $chatId, $messageId);
        $where = 'nel gruppo «' . (string)$context['member']['name'] . '» (ID ' . $chatId . ')';
        $before = $withContext ? cg_fetch($mysqli, $userId, $chatId, ['before' => $messageId, 'limit' => REPORT_CONTEXT])['messages'] : [];
    } else {
        $context = cc_pm_message_context($mysqli, $messageId, $userId);
        $target = cc_pm_one($mysqli, $context['pair'], $messageId);
        $where = 'in chat privata';
        $before = $withContext ? cc_pm_fetch($mysqli, $context['pair'], ['before' => $messageId, 'limit' => REPORT_CONTEXT])['messages'] : [];
    }

    if (!$target || !empty($target['is_deleted']) || ($target['message_type'] ?? '') === 'system') {
        throw new ChatError(rt_t('Messaggio non trovato.', 'Message not found.'), 404);
    }
    $authorId = (int)$target['sender_id'];
    if ($authorId === $userId || $authorId <= 0) {
        throw new ChatError(rt_t('Non puoi segnalare un tuo messaggio.', 'You cannot report your own message.'), 422);
    }

    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM site_ticket_messages WHERE sender_id = ? AND message LIKE '🚩%' AND created_at > NOW() - INTERVAL 1 HOUR");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $recent = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    if ($recent >= REPORT_PER_HOUR) {
        throw new ChatError(rt_t('Hai mandato molte segnalazioni: riprova più tardi.', 'You sent many reports: try again later.'), 429);
    }

    // La copia che arriva allo staff. È in italiano come gli altri testi di
    // servizio dei ticket: la legge lo staff, non chi segnala.
    $line = static function (array $message): string {
        $text = trim((string)($message['body'] ?? ''));
        if (($message['message_type'] ?? '') === 'gif') {
            $text = 'GIF: ' . (string)($message['media_url'] ?? '');
        }
        if (!empty($message['is_deleted'])) {
            $text = '(messaggio eliminato)';
        }
        foreach ((array)($message['attachments'] ?? []) as $file) {
            $text .= ($text !== '' ? "\n" : '') . 'Allegato: https://cripsum.com' . (string)$file['file_path'];
        }
        return mb_substr($text !== '' ? $text : '(vuoto)', 0, 1500, 'UTF-8');
    };
    $stamp = static fn(array $message): string => date('d/m/Y H:i', (int)$message['ts']);

    $authorName = (string)$target['sender_username'];
    $body = "🚩 Segnalazione di un messaggio $where\n"
        . 'Autore: @' . $authorName . ' (ID ' . $authorId . ")\n"
        . 'Motivo: ' . ($reason !== '' ? $reason : 'non indicato') . "\n\n"
        . 'Messaggio segnalato (#' . $messageId . ', ' . $stamp($target) . "):\n"
        . $line($target);

    $previous = '';
    foreach ($before as $message) {
        if (($message['message_type'] ?? '') === 'system') {
            continue;
        }
        $previous .= "\n[" . $stamp($message) . '] @' . (string)$message['sender_username'] . ': ' . mb_substr($line($message), 0, 400, 'UTF-8');
    }
    if ($previous !== '') {
        $body .= "\n\nMessaggi precedenti, inclusi da chi segnala:" . $previous;
    }
    $body = mb_substr($body, 0, 4900, 'UTF-8');

    $title = 'Segnalazione messaggio: @' . $authorName;
    $topic = 'Segnalazione Utente';
    $username = (string)($chatUser['username'] ?? '');
    $role = (string)($chatUser['ruolo'] ?? 'utente');

    $callBot = static function (string $path, array $payload): ?array {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init(cripsum_bot_endpoint($path));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => cripsum_bot_headers(),
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $decoded = is_string($response) ? json_decode($response, true) : null;
        return is_array($decoded) ? $decoded : null;
    };

    // Un ticket aperto sulla stessa persona raccoglie anche questa segnalazione.
    $threadCol = rt_has_col($mysqli, 'site_tickets', 'discord_thread_id') ? 'discord_thread_id' : 'NULL AS discord_thread_id';
    $stmt = $mysqli->prepare("SELECT ticket_id, $threadCol FROM site_tickets WHERE user_id = ? AND status = 'open' AND title = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('is', $userId, $title);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $ticketId = (string)$existing['ticket_id'];
        if (!cripsum_ticket_add_message($mysqli, $ticketId, $userId, $body, null, 'admin')) {
            throw new ChatError(rt_t('Segnalazione non riuscita. Riprova.', 'The report failed. Try again.'), 500);
        }
        // Gli stessi campi di una risposta scritta dalla posta (api/tickets.php).
        $callBot('/v1/tickets/reply', [
            'ticket_id' => $ticketId,
            'title' => $title,
            'sender' => $username,
            'role' => $role,
            'message' => $body,
            'attachment_url' => null,
            'thread_id' => $existing['discord_thread_id'] ?? null,
        ]);
    } else {
        $created = cripsum_ticket_create($mysqli, $userId, $title, $topic, $body, 'site');
        if (!$created) {
            throw new ChatError(rt_t('Segnalazione non riuscita. Riprova.', 'The report failed. Try again.'), 500);
        }
        $ticketId = $created['ticket_id'];
        cripsum_ticket_signal($mysqli, $ticketId, $userId, 'admin');

        // Gli stessi campi di un ticket aperto dalla pagina Supporto.
        $answer = $callBot('/v1/tickets', [
            'ticket_id' => $ticketId,
            'username' => $username,
            'user_id' => $userId,
            'role' => $role,
            'contact' => 'Loggato sul sito',
            'discord_id' => (string)($chatUser['discord_id'] ?? ''),
            'title' => $title,
            'topic' => $topic,
            'message' => $body,
            'attachment_url' => null,
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
        $threadId = is_array($answer) && !empty($answer['success']) ? (string)($answer['thread_id'] ?? '') : '';
        if ($threadId !== '') {
            cripsum_ticket_attach_thread($mysqli, $ticketId, $threadId);
        }
    }

    // Chi segnala ha scritto lui il ticket: per lui è già letto.
    if (rt_has_col($mysqli, 'site_tickets', 'user_read')) {
        $stmt = $mysqli->prepare('UPDATE site_tickets SET user_read = 1 WHERE ticket_id = ?');
        $stmt->bind_param('s', $ticketId);
        $stmt->execute();
        $stmt->close();
    }

    send_success([
        'ticket_id' => $ticketId,
        'ticket_url' => '/' . rt_lang() . '/inbox?section=tickets&t=' . rawurlencode($ticketId),
        'message' => rt_t('Segnalazione inviata. La trovi tra i tuoi ticket.', 'Report sent. You can find it among your tickets.'),
    ]);
});
