<?php
declare(strict_types=1);
ini_set('log_errors','1');
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/game_helpers.php';
require_once __DIR__ . '/../../includes/game_api.php';
if (function_exists('checkBan')) checkBan($mysqli);
