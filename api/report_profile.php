<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Devi essere loggato per segnalare un profilo.']);
    exit();
}

$reporter_id = (int)$_SESSION['user_id'];

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$reported_id = (int)($input['reported_user_id'] ?? 0);
$reason = trim((string)($input['reason'] ?? ''));
$detail = trim((string)($input['detail'] ?? ''));

if ($reported_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID utente segnalato non valido.']);
    exit();
}

if ($reported_id === $reporter_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Non puoi segnalare il tuo stesso profilo.']);
    exit();
}

if ($reason === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Seleziona un motivo per la segnalazione.']);
    exit();
}

// Make sure profile_reports table exists
if (!admin_table_exists($mysqli, 'profile_reports')) {
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS `profile_reports` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `reported_user_id` int(11) NOT NULL,
          `reporter_user_id` int(11) NOT NULL,
          `reason` varchar(100) NOT NULL,
          `detail` varchar(500) DEFAULT NULL,
          `status` enum('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `reviewed_at` datetime DEFAULT NULL,
          `reviewed_by` int(11) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_profile_report` (`reported_user_id`, `reporter_user_id`),
          KEY `idx_profile_reports_status` (`status`),
          KEY `fk_profile_reports_reported` (`reported_user_id`),
          KEY `fk_profile_reports_reporter` (`reporter_user_id`),
          CONSTRAINT `fk_profile_reports_reported` FOREIGN KEY (`reported_user_id`) REFERENCES `utenti` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_profile_reports_reporter` FOREIGN KEY (`reporter_user_id`) REFERENCES `utenti` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

$stmt = $mysqli->prepare("
    INSERT INTO profile_reports (reported_user_id, reporter_user_id, reason, detail, status, created_at)
    VALUES (?, ?, ?, ?, 'open', NOW())
    ON DUPLICATE KEY UPDATE reason = VALUES(reason), detail = VALUES(detail), status = 'open', created_at = NOW()
");

if ($stmt) {
    $stmt->bind_param('iiss', $reported_id, $reporter_id, $reason, $detail);
    if ($stmt->execute()) {
        echo json_encode(['ok' => true, 'message' => 'Segnalazione inviata con successo.']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Errore durante l\'invio della segnalazione.']);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore preparamento database.']);
}
