<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non autenticato.']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['ruolo'] ?? 'utente';
$isAdmin = ($userRole === 'admin' || $userRole === 'owner');
$method = $_SERVER['REQUEST_METHOD'];

// Assicurati che le tabelle dei ticket esistano (Auto-migrazione)
$mysqli->query("CREATE TABLE IF NOT EXISTS site_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id VARCHAR(12) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    topic VARCHAR(100) NOT NULL,
    status VARCHAR(20) DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$mysqli->query("CREATE TABLE IF NOT EXISTS site_ticket_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id VARCHAR(12) NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    attachment_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (ticket_id),
    FOREIGN KEY (ticket_id) REFERENCES site_tickets(ticket_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// Controllo e migrazione sicura per le colonne di notifica lettura
$checkUserRead = $mysqli->query("SHOW COLUMNS FROM site_tickets LIKE 'user_read'");
if ($checkUserRead && $checkUserRead->num_rows == 0) {
    $mysqli->query("ALTER TABLE site_tickets ADD COLUMN user_read TINYINT DEFAULT 0");
}
$checkAdminRead = $mysqli->query("SHOW COLUMNS FROM site_tickets LIKE 'admin_read'");
if ($checkAdminRead && $checkAdminRead->num_rows == 0) {
    $mysqli->query("ALTER TABLE site_tickets ADD COLUMN admin_read TINYINT DEFAULT 0");
}


if ($method === 'GET') {
    $ticketId = $_GET['ticket_id'] ?? '';

    // Caso A: Dettagli e messaggi di un singolo ticket
    if ($ticketId !== '') {
        $stmt = $mysqli->prepare("SELECT user_id, title, topic, status, created_at FROM site_tickets WHERE ticket_id = ?");
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'Errore database.']);
            exit();
        }
        $stmt->bind_param("s", $ticketId);
        $stmt->execute();
        $res = $stmt->get_result();
        $ticket = $res->fetch_assoc();
        $stmt->close();

        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Ticket non trovato.']);
            exit();
        }

        if (!$isAdmin && (int)$ticket['user_id'] !== $userId) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Non autorizzato.']);
            exit();
        }

        // Segna il ticket come letto per l'utente corrente
        if ($isAdmin) {
            $stmtRead = $mysqli->prepare("UPDATE site_tickets SET admin_read = 1 WHERE ticket_id = ?");
        } else {
            $stmtRead = $mysqli->prepare("UPDATE site_tickets SET user_read = 1 WHERE ticket_id = ?");
        }
        if ($stmtRead) {
            $stmtRead->bind_param("s", $ticketId);
            $stmtRead->execute();
            $stmtRead->close();
        }

        // Recupera la cronologia dei messaggi della chat
        $queryMessages = "
            SELECT tm.id, tm.sender_id, tm.message, tm.attachment_url, tm.created_at, u.username, u.ruolo
            FROM site_ticket_messages tm
            LEFT JOIN utenti u ON u.id = tm.sender_id
            WHERE tm.ticket_id = ?
            ORDER BY tm.created_at ASC
        ";
        $stmtMsgs = $mysqli->prepare($queryMessages);
        $stmtMsgs->bind_param("s", $ticketId);
        $stmtMsgs->execute();
        $messages = $stmtMsgs->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMsgs->close();

        echo json_encode([
            'ok' => true,
            'ticket' => $ticket,
            'messages' => $messages
        ]);
        exit();
    }

    // Caso B: Lista dei ticket
    if ($isAdmin) {
        $query = "
            SELECT t.ticket_id, t.title, t.topic, t.status, t.created_at, t.updated_at, t.admin_read AS is_unread_status, u.username 
            FROM site_tickets t
            LEFT JOIN utenti u ON u.id = t.user_id
            ORDER BY t.updated_at DESC
        ";
        $stmt = $mysqli->prepare($query);
    } else {
        $query = "
            SELECT ticket_id, title, topic, status, created_at, updated_at, user_read AS is_unread_status 
            FROM site_tickets 
            WHERE user_id = ?
            ORDER BY updated_at DESC
        ";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $userId);
    }

    $stmt->execute();
    $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['ok' => true, 'tickets' => $tickets]);
    exit();

} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    // AZIONE: Chiudi / Riapri Ticket
    if ($action === 'toggle_status') {
        $ticketId = $_POST['ticket_id'] ?? '';
        if (empty($ticketId)) {
            echo json_encode(['ok' => false, 'error' => 'ID ticket mancante.']);
            exit();
        }

        $stmt = $mysqli->prepare("SELECT user_id, title, status FROM site_tickets WHERE ticket_id = ?");
        $stmt->bind_param("s", $ticketId);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ticket) {
            echo json_encode(['ok' => false, 'error' => 'Ticket non trovato.']);
            exit();
        }

        if (!$isAdmin && (int)$ticket['user_id'] !== $userId) {
            echo json_encode(['ok' => false, 'error' => 'Non autorizzato.']);
            exit();
        }

        $newStatus = ($ticket['status'] === 'open') ? 'closed' : 'open';

        $stmtUpdate = $mysqli->prepare("UPDATE site_tickets SET status = ?, user_read = 0, admin_read = 0 WHERE ticket_id = ?");
        $stmtUpdate->bind_param("sss", $newStatus, $ticketId);
        
        if ($stmtUpdate->execute()) {
            $stmtUpdate->close();

            // Notifica il cambio di stato su Discord
            $senderUsername = $_SESSION['username'] ?? 'Utente';
            $botPayload = [
                'ticket_id' => $ticketId,
                'title' => $ticket['title'],
                'sender' => $senderUsername,
                'role' => $userRole,
                'message' => "🔒 Lo stato del ticket è stato modificato in: **" . strtoupper($newStatus === 'closed' ? 'Chiuso' : 'Aperto') . "**.",
                'attachment_url' => null
            ];

            if (function_exists('curl_init')) {
                $ch = curl_init('https://api.cripsum.com/v1/tickets/reply');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($botPayload),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_TIMEOUT => 3,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                curl_exec($ch);
                curl_close($ch);
            }

            echo json_encode(['ok' => true, 'status' => $newStatus]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Impossibile aggiornare lo stato del ticket.']);
        }
        exit();
    }

    // AZIONE: Invio di un messaggio nella chat
    $ticketId = $_POST['ticket_id'] ?? '';
    $message = trim($_POST['message'] ?? '');

    if (empty($ticketId) || empty($message)) {
        echo json_encode(['ok' => false, 'error' => 'Campi obbligatori mancanti.']);
        exit();
    }

    $stmt = $mysqli->prepare("SELECT user_id, title, status FROM site_tickets WHERE ticket_id = ?");
    $stmt->bind_param("s", $ticketId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ticket) {
        echo json_encode(['ok' => false, 'error' => 'Ticket non trovato.']);
        exit();
    }

    if (!$isAdmin && (int)$ticket['user_id'] !== $userId) {
        echo json_encode(['ok' => false, 'error' => 'Non autorizzato.']);
        exit();
    }

    // Gestione dell'allegato immagine (facoltativo)
    $attachmentUrl = null;
    if (!empty($_FILES['attachment']['tmp_name']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
        $check = getimagesize($_FILES['attachment']['tmp_name']);
        if ($check !== false) {
            if ($_FILES['attachment']['size'] <= 5 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../uploads/tickets/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                $fileName = uniqid('img_', true) . '.' . $ext;
                $targetFile = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
                    $attachmentUrl = '/uploads/tickets/' . $fileName;
                }
            } else {
                echo json_encode(['ok' => false, 'error' => 'L\'immagine supera i 5MB.']);
                exit();
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'Il file non è un\'immagine valida.']);
            exit();
        }
    }

    // Inserisce il messaggio nel DB
    $stmtMsg = $mysqli->prepare("INSERT INTO site_ticket_messages (ticket_id, sender_id, message, attachment_url) VALUES (?, ?, ?, ?)");
    $stmtMsg->bind_param("siss", $ticketId, $userId, $message, $attachmentUrl);
    
    if ($stmtMsg->execute()) {
        $stmtMsg->close();

        // Aggiorna lo stato di lettura del ticket
        $userReadVal = $isAdmin ? 0 : 1;
        $adminReadVal = $isAdmin ? 1 : 0;

        $stmtUpdate = $mysqli->prepare("UPDATE site_tickets SET user_read = ?, admin_read = ?, updated_at = NOW() WHERE ticket_id = ?");
        $stmtUpdate->bind_param("iis", $userReadVal, $adminReadVal, $ticketId);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        $senderUsername = $_SESSION['username'] ?? 'Utente';

        // Invia notifica a Discord
        $fullAttachmentUrl = $attachmentUrl ? 'https://cripsum.com' . $attachmentUrl : null;
        $botPayload = [
            'ticket_id' => $ticketId,
            'title' => $ticket['title'],
            'sender' => $senderUsername,
            'role' => $userRole,
            'message' => $message,
            'attachment_url' => $fullAttachmentUrl
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.cripsum.com/v1/tickets/reply');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($botPayload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }

        echo json_encode(['ok' => true, 'attachment_url' => $attachmentUrl]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Errore nel salvataggio del messaggio.']);
    }
    exit();
}
?>
