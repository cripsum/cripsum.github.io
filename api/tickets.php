<?php
/**
 * Ticket di supporto, lato sito.
 *
 * GET                       elenco (i propri; tutti per lo staff)
 * GET  ?ticket_id=TK-…      un ticket con i suoi messaggi (`after` = solo i
 *                           messaggi con id più alto) e lo segna come letto
 * POST ticket_id, message, attachment     nuova risposta
 * POST action=toggle_status, ticket_id    chiude o riapre (solo staff)
 *
 * Ogni risposta e ogni cambio di stato vengono girati al bot Discord
 * (/v1/tickets/reply) con gli stessi campi di sempre: il thread collegato al
 * ticket riceve quello che si scrive qui, e quello che si scrive lì arriva
 * da api/bot/tickets/*. La struttura delle tabelle si controlla, non si crea:
 * le colonne del ponte con Discord sono nella migration.
 */
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bot_client.php';
require_once __DIR__ . '/../includes/ticket_helpers.php';
require_once __DIR__ . '/../includes/realtime.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

$reply = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
};
$fail = static function (string $it, string $en, int $status = 200) use ($reply): void {
    $reply(['ok' => false, 'error' => rt_t($it, $en)], $status);
};

if (!isLoggedIn()) {
    $fail('Non autenticato.', 'Not signed in.', 401);
}

$userId = (int)$_SESSION['user_id'];
$userRole = (string)($_SESSION['ruolo'] ?? 'utente');
$senderUsername = (string)($_SESSION['username'] ?? 'Utente');
$isAdmin = ($userRole === 'admin' || $userRole === 'owner');
$method = $_SERVER['REQUEST_METHOD'];

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (!cripsum_ticket_tables_ready($mysqli)) {
    if ($method === 'GET' && ($_GET['ticket_id'] ?? '') === '') {
        $reply(['ok' => true, 'tickets' => []]);
    }
    $fail('I ticket non sono disponibili.', 'Tickets are not available.', 503);
}

$hasRead = rt_has_col($mysqli, 'site_tickets', 'user_read') && rt_has_col($mysqli, 'site_tickets', 'admin_read');
$hasThread = rt_has_col($mysqli, 'site_tickets', 'discord_thread_id');
$hasSource = rt_has_col($mysqli, 'site_tickets', 'source');
$threadCol = $hasThread ? 'discord_thread_id' : 'NULL AS discord_thread_id';

/** Gira al bot una risposta o un cambio di stato. Se il bot non risponde, il ticket resta valido. */
$notifyBot = static function (array $payload): void {
    if (!function_exists('curl_init')) {
        return;
    }
    $ch = curl_init(cripsum_bot_endpoint('/v1/tickets/reply'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => cripsum_bot_headers(),
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
    curl_close($ch);
};

$findTicket = static function (string $ticketId) use ($mysqli, $threadCol) {
    $stmt = $mysqli->prepare("SELECT user_id, title, topic, status, created_at, $threadCol FROM site_tickets WHERE ticket_id = ? LIMIT 1");
    $stmt->bind_param('s', $ticketId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ticket ?: null;
};

try {
    if ($method === 'GET') {
        cripsum_release_session();
        $ticketId = (string)($_GET['ticket_id'] ?? '');

        // Caso A: un ticket e la sua conversazione.
        if ($ticketId !== '') {
            $ticket = $findTicket($ticketId);
            if (!$ticket) {
                $fail('Ticket non trovato.', 'Ticket not found.', 404);
            }
            if (!$isAdmin && (int)$ticket['user_id'] !== $userId) {
                $fail('Non autorizzato.', 'Not allowed.', 403);
            }

            if ($hasRead) {
                $column = $isAdmin ? 'admin_read' : 'user_read';
                $stmt = $mysqli->prepare("UPDATE site_tickets SET $column = 1 WHERE ticket_id = ? AND $column = 0");
                $stmt->bind_param('s', $ticketId);
                $stmt->execute();
                $wasUnread = $stmt->affected_rows > 0;
                $stmt->close();
                if ($wasUnread) {
                    // Il numero in navbar scende anche nelle altre schede.
                    rt_push_user($userId, ['t' => 'ls']);
                }
            }

            $after = (int)($_GET['after'] ?? 0);
            $stmt = $mysqli->prepare('
                SELECT tm.id, tm.sender_id, tm.message, tm.attachment_url, tm.created_at,
                       UNIX_TIMESTAMP(tm.created_at) AS ts, u.username, u.ruolo
                FROM site_ticket_messages tm
                LEFT JOIN utenti u ON u.id = tm.sender_id
                WHERE tm.ticket_id = ? AND tm.id > ?
                ORDER BY tm.created_at ASC, tm.id ASC
                LIMIT 500
            ');
            $stmt->bind_param('si', $ticketId, $after);
            $stmt->execute();
            $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $reply(['ok' => true, 'ticket' => $ticket + ['ticket_id' => $ticketId], 'messages' => $messages]);
        }

        // Caso B: l'elenco.
        $read = $hasRead ? ($isAdmin ? 't.admin_read' : 't.user_read') : '1';
        $source = $hasSource ? 't.source' : "'site'";
        $linked = $hasThread ? '(t.discord_thread_id IS NOT NULL)' : '0';
        $columns = "t.ticket_id, t.title, t.topic, t.status, t.created_at, t.updated_at,
                    UNIX_TIMESTAMP(t.updated_at) AS updated_ts, $read AS is_unread_status,
                    $source AS source, $linked AS on_discord";

        if ($isAdmin) {
            $stmt = $mysqli->prepare("
                SELECT $columns, t.user_id, u.username
                FROM site_tickets t
                LEFT JOIN utenti u ON u.id = t.user_id
                ORDER BY t.updated_at DESC
                LIMIT 300
            ");
        } else {
            $stmt = $mysqli->prepare("
                SELECT $columns
                FROM site_tickets t
                WHERE t.user_id = ?
                ORDER BY t.updated_at DESC
                LIMIT 300
            ");
            $stmt->bind_param('i', $userId);
        }
        $stmt->execute();
        $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $reply(['ok' => true, 'tickets' => $tickets]);
    }

    if ($method !== 'POST') {
        $fail('Metodo non consentito.', 'Method not allowed.', 405);
    }

    $csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
        $fail('Sessione scaduta. Ricarica la pagina.', 'Session expired. Reload the page.', 419);
    }

    $action = (string)($_POST['action'] ?? '');
    $ticketId = (string)($_POST['ticket_id'] ?? '');
    if ($ticketId === '') {
        $fail('ID ticket mancante.', 'Missing ticket ID.');
    }

    $ticket = $findTicket($ticketId);
    if (!$ticket) {
        $fail('Ticket non trovato.', 'Ticket not found.');
    }

    // AZIONE: chiudi o riapri.
    if ($action === 'toggle_status') {
        if (!$isAdmin) {
            $fail('Solo lo staff può chiudere o riaprire i ticket.', 'Only the staff can close or reopen tickets.');
        }

        $newStatus = ($ticket['status'] === 'open') ? 'closed' : 'open';
        $sql = $hasRead
            ? 'UPDATE site_tickets SET status = ?, user_read = 0, admin_read = 0 WHERE ticket_id = ?'
            : 'UPDATE site_tickets SET status = ? WHERE ticket_id = ?';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ss', $newStatus, $ticketId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            $fail('Impossibile aggiornare lo stato del ticket.', 'Could not update the ticket status.');
        }

        cripsum_ticket_signal($mysqli, $ticketId, $userId, 'both');

        $notifyBot([
            'ticket_id' => $ticketId,
            'title' => $ticket['title'],
            'sender' => $senderUsername,
            'role' => $userRole,
            'message' => '🔒 Lo stato del ticket è stato modificato in: **' . strtoupper($newStatus === 'closed' ? 'Chiuso' : 'Aperto') . '**.',
            'attachment_url' => null,
            // Se il ticket ha un thread collegato, la risposta va lì invece
            // che nel canale.
            'thread_id' => $ticket['discord_thread_id'] ?? null,
            'status' => $newStatus,
        ]);

        $reply(['ok' => true, 'status' => $newStatus]);
    }

    // AZIONE: nuova risposta.
    if (!$isAdmin && (int)$ticket['user_id'] !== $userId) {
        $fail('Non autorizzato.', 'Not allowed.');
    }
    if (!$isAdmin && $ticket['status'] !== 'open') {
        $fail('Questo ticket è chiuso.', 'This ticket is closed.');
    }

    // mb_scrub: una sequenza di byte non valida farebbe rifiutare l'intera riga al database.
    $message = trim(mb_scrub((string)($_POST['message'] ?? ''), 'UTF-8'));
    $hasFile = !empty($_FILES['attachment']['tmp_name']) && is_uploaded_file($_FILES['attachment']['tmp_name']);
    if ($message === '' && !$hasFile) {
        $fail('Campi obbligatori mancanti.', 'Required fields are missing.');
    }
    if (mb_strlen($message, 'UTF-8') > 5000) {
        $fail('Il messaggio è troppo lungo.', 'The message is too long.');
    }

    // Allegato immagine, facoltativo.
    $attachmentUrl = null;
    if ($hasFile) {
        $check = @getimagesize($_FILES['attachment']['tmp_name']);
        if ($check === false) {
            $fail('Il file non è un\'immagine valida.', 'The file is not a valid image.');
        }
        if ((int)$_FILES['attachment']['size'] > 5 * 1024 * 1024) {
            $fail('L\'immagine supera i 5MB.', 'The image is larger than 5MB.');
        }
        $allowedImageMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $detectedMime = (string)($check['mime'] ?? '');
        if (!isset($allowedImageMimes[$detectedMime])) {
            $fail('Formato immagine non supportato.', 'Unsupported image format.');
        }
        $uploadDir = __DIR__ . '/../uploads/tickets/';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $fail('Impossibile salvare l\'immagine.', 'Could not save the image.');
        }
        $fileName = 'img_' . bin2hex(random_bytes(16)) . '.' . $allowedImageMimes[$detectedMime];
        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName)) {
            $fail('Impossibile salvare l\'immagine.', 'Could not save the image.');
        }
        $attachmentUrl = '/uploads/tickets/' . $fileName;
    }

    // Solo un'immagine: il testo non può restare vuoto nel database.
    $stored = $message !== '' ? $message : '📎';

    if (!cripsum_ticket_add_message($mysqli, $ticketId, $userId, $stored, $attachmentUrl, $isAdmin ? 'user' : 'admin')) {
        $fail('Errore nel salvataggio del messaggio.', 'Could not save the message.');
    }

    // Chi scrive ha letto fin qui.
    if ($hasRead) {
        $column = $isAdmin ? 'admin_read' : 'user_read';
        $stmt = $mysqli->prepare("UPDATE site_tickets SET $column = 1 WHERE ticket_id = ?");
        $stmt->bind_param('s', $ticketId);
        $stmt->execute();
        $stmt->close();
    }

    $messageId = 0;
    $stmt = $mysqli->prepare('SELECT MAX(id) FROM site_ticket_messages WHERE ticket_id = ? AND sender_id = ?');
    $stmt->bind_param('si', $ticketId, $userId);
    $stmt->execute();
    $messageId = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();

    $notifyBot([
        'ticket_id' => $ticketId,
        'title' => $ticket['title'],
        'sender' => $senderUsername,
        'role' => $userRole,
        'message' => $stored,
        'attachment_url' => $attachmentUrl ? 'https://cripsum.com' . $attachmentUrl : null,
        'thread_id' => $ticket['discord_thread_id'] ?? null,
    ]);

    $reply(['ok' => true, 'attachment_url' => $attachmentUrl, 'message_id' => $messageId]);
} catch (Throwable $e) {
    error_log('[api/tickets] ' . $e->getMessage());
    $fail('Qualcosa è andato storto. Riprova.', 'Something went wrong. Try again.', 500);
}
