<?php
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin/admin_helpers.php';

mysqli_report(MYSQLI_REPORT_OFF);
if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

$adminUser = admin_require_access($mysqli, true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $inputForCsrf = admin_read_input();
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($inputForCsrf['csrf_token'] ?? null);
    if (!admin_validate_csrf($csrf)) {
        admin_fail('Sessione scaduta. Ricarica la pagina.', 419);
    }
    $GLOBALS['admin_input'] = $inputForCsrf;
}

function admin_input(): array
{
    return $GLOBALS['admin_input'] ?? admin_read_input();
}
