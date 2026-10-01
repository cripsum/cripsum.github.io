<?php
/**
 * Accettazione del regolamento della chat globale.
 *
 * Arriva dal modulo in includes/chat_page_global.php. Dopo aver registrato
 * la scelta rimanda alla chat nella lingua da cui si è partiti (prima
 * tornava sempre a quella italiana).
 */
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$lang = (($_POST['lang'] ?? '') === 'en') ? 'en' : 'it';
$target = "/$lang/global-chat";

$userId = (int)($_SESSION['user_id'] ?? 0);
$token = (string)($_POST['csrf_token'] ?? '');
$validToken = (function_exists('csrf_validate') && csrf_validate($token))
    || (!empty($_SESSION['chat_csrf']) && hash_equals((string)$_SESSION['chat_csrf'], $token));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId > 0 && $validToken && !empty($_POST['accept'])) {
    $stmt = $mysqli->prepare('UPDATE utenti SET lineeGuidaChat = 1 WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    $_SESSION['lineeGuidaChat'] = 1;
}

header('Location: ' . $target);
exit();
