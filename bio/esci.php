<?php
/**
 * bio.cripsum.com/esci: si esce da bio e da cripsum.com insieme.
 *
 * Solo POST col token CSRF della sessione di bio. Si revoca la riga di
 * user_sessions a cui la sessione di bio e' legata: cosi' anche la sessione di
 * cripsum.com finisce alla sua prossima richiesta (auth_sync_current_device_session).
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bio_sso.php';

cripsum_bio_only('esci');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_validate($_POST['csrf_token'] ?? null)) {
    header('Location: /');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$parentId = (int)($_SESSION['auth_parent_session_id'] ?? 0);
if ($userId > 0 && $parentId > 0) {
    auth_revoke_device_session($mysqli, $userId, $parentId);
}

auth_destroy_local_session();
cripsum_sso_hint_cookie(false);
cripsum_bio_noauto();

header('Location: /');
exit;
