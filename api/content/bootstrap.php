<?php
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/content_v2_helpers.php';

mysqli_report(MYSQLI_REPORT_OFF);

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan') && function_exists('isLoggedIn') && isLoggedIn()) {
    checkBan($mysqli);
}

$currentUser = cv2_current_user($mysqli);
