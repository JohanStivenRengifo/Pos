<?php
require_once 'includes/functions.php';

session_start();
logoutUser();

header("Location: " . APP_URL . "/index.php");
exit();