<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePost();
$_SESSION = [];
$cookieSettings = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $cookieSettings['path'],
    'secure' => $cookieSettings['secure'],
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_destroy();
redirect('index.php');
