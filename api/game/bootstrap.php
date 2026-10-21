<?php
declare(strict_types=1);
ini_set('log_errors','1');
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
// Qui restano solo i potenziamenti della collezione (il vecchio motore dei duelli non c'e' piu').
require_once __DIR__ . '/../../includes/gacha/upgrades.php';
if (function_exists('checkBan')) checkBan($mysqli);
