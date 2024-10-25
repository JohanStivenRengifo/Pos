<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

session_start();

if (!isUserLoggedIn($pdo)) {
    header("Location: " . APP_URL . "/index.php");
    exit();
}
