<?php
ini_set('session.gc_maxlifetime', 604800);
ini_set('session.cookie_lifetime', 604800);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 1000);
session_set_cookie_params([
    'lifetime' => 604800,
    'path' => '/',
    'domain' => '.cripsum.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

if($_SESSION["user_id"] == 13){
    header("Location: uwu");
}

?>