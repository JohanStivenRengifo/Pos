<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$companyName = strtolower(preg_replace('/\s+/', '_', $user['company_name']));

try {
    $userDbPdo = new PDO("mysql:host=localhost;dbname=$companyName", DB_USER, DB_PASS);
    // Fetch and display user's data
    echo "Welcome to your dashboard, " . htmlspecialchars($user['full_name']);

} catch (Exception $e) {
    echo "Failed to connect to your database: " . $e->getMessage();
}
?>
