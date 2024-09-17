<?php
require_once 'fuctions/index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $password = password_hash(trim($_POST['password'] ?? ''), PASSWORD_BCRYPT);

    if (empty($email) || empty($fullName) || empty($companyName) || empty($password)) {
        echo "Please fill in all the fields.";
        return;
    }

    try {
        $pdo = getPDOConnection();
        $pdo->beginTransaction();

        // Check if email or company name already exists
        $stmt = $pdo->prepare("SELECT * FROM global_users WHERE email = ? OR company_name = ?");
        $stmt->execute([$email, $companyName]);
        if ($stmt->fetch()) {
            echo "Email or Company Name already registered.";
            return;
        }

        // Insert into global users table
        $stmt = $pdo->prepare("INSERT INTO global_users (email, full_name, company_name, password) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $fullName, $companyName, $password]);

        // Create user-specific database
        $userDbName = strtolower(preg_replace('/\s+/', '_', $companyName));
        $pdo->exec("CREATE DATABASE `$userDbName`");
        
        // Connect to the new database
        $userDbPdo = new PDO("mysql:host=localhost;dbname=$userDbName", DB_USER, DB_PASS);
        
        // Import template
        $sql = file_get_contents('./templates/db_template.sql');
        $userDbPdo->exec($sql);

        $pdo->commit();
        echo "Registration successful!";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Registration failed: " . $e->getMessage();
    }
}
?>
<head>
    <meta charset="UTF-8">
    <title>Registro - Sistema Contable</title>
    <link rel="stylesheet" href="/css/login.css">
</head>

<form method="POST" action="register.php">
    <input type="email" name="email" placeholder="Email" required>
    <input type="text" name="full_name" placeholder="Full Name" required>
    <input type="text" name="company_name" placeholder="Company Name" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Register</button>
</form>