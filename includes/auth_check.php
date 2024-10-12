<?php
require_once __DIR__ . '/functions.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['remember_token'])) {
        if (!validateRememberToken()) {
            setcookie('remember_token', '', time() - 3600, COOKIE_PATH, COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_HTTPONLY);
        }
    }
    
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . APP_URL . "/index.php");
        exit();
    }
}
