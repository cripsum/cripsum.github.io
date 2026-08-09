<?php
define('CRIPSUM_SKIP_SPECIAL_SESSION_REDIRECT', true);
require_once 'config/session_init.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

logoutUser($mysqli);
