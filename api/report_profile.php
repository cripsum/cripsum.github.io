<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_helpers.php';

require_once __DIR__ . '/../includes/discord_notify.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Devi essere loggato per segnalare un profilo.',
        'redirect' => '/it/accedi'
    ]);
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
$detail = mb_substr(trim((string)($input['detail'] ?? '')), 0, 500, 'UTF-8');
$allowedReasons = ['spam', 'inappropriate', 'harassment', 'other'];

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

if (!in_array($reason, $allowedReasons, true)) {
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
        $repStmt = $mysqli->prepare("SELECT username, display_name FROM utenti WHERE id = ? LIMIT 1");
        $reportedUsername = '';
        $reportedDisplayName = '';
        if ($repStmt) {
            $repStmt->bind_param('i', $reported_id);
            $repStmt->execute();
            $reportedRow = $repStmt->get_result()->fetch_assoc() ?: [];
            $reportedUsername = $reportedRow['username'] ?? "ID #{$reported_id}";
            $reportedDisplayName = trim((string)($reportedRow['display_name'] ?? ''));
            $repStmt->close();
        }

        $reporterUsername = $_SESSION['username'] ?? '';
        $reporterRole = $_SESSION['ruolo'] ?? '';
        $reporterDiscordId = $_SESSION['discord_id'] ?? '';
        $rStmt = $mysqli->prepare("SELECT username, ruolo, discord_id FROM utenti WHERE id = ? LIMIT 1");
        if ($rStmt) {
            $rStmt->bind_param('i', $reporter_id);
            $rStmt->execute();
            $reporterRow = $rStmt->get_result()->fetch_assoc() ?: [];
            $reporterUsername = $reporterRow['username'] ?? ($reporterUsername ?: "ID #{$reporter_id}");
            $reporterRole = $reporterRow['ruolo'] ?? $reporterRole;
            $reporterDiscordId = $reporterRow['discord_id'] ?? $reporterDiscordId;
            $rStmt->close();
        }

        $reasonMap = [
            'spam' => 'Spam / Pubblicità',
            'inappropriate' => 'Inappropriato / NSFW',
            'harassment' => 'Molestie / Bullismo',
            'other' => 'Altro motivo'
        ];
        $readableReason = $reasonMap[$reason] ?? $reason;

        $discordSent = notifyDiscordSupportReport('profile', [
            'target_id' => $reported_id,
            'target_name' => $reportedDisplayName !== ''
                ? "{$reportedDisplayName} (@{$reportedUsername})"
                : "@{$reportedUsername}",
            'target_author' => $reportedUsername,
            'target_author_id' => $reported_id,
            'target_url' => "https://cripsum.com/u/" . rawurlencode($reportedUsername),
            'reason' => $readableReason,
            'detail' => $detail,
            'reporter_id' => $reporter_id,
            'reporter_username' => $reporterUsername,
            'reporter_role' => $reporterRole,
            'reporter_discord_id' => $reporterDiscordId
        ]);

        if (!$discordSent) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => 'Segnalazione salvata, ma il supporto Discord non è raggiungibile.']);
            $stmt->close();
            exit();
        }

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
