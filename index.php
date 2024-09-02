<?php
require_once '../config/db.php';
require_once '../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, 'main_db');
    $stmt = $db->prepare("SELECT id, password, dbname FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->bind_result($id, $hashed_password, $dbname);
    $stmt->fetch();

    if (password_verify($password, $hashed_password)) {
        session_start();
        $_SESSION['user_id'] = $id;
        $_SESSION['dbname'] = $dbname;

        header('Location: dashboard.php');
    } else {
        echo "Usuario o contraseña incorrectos.";
    }
}
?>
<form action="login.php" method="post">
    <label for="username">Nombre de Usuario:</label>
    <input type="text" id="username" name="username" required>
    <label for="password">Contraseña:</label>
    <input type="password" id="password" name="password" required>
    <button type="submit">Iniciar Sesión</button>
</form>
