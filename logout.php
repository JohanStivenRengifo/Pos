<?php
session_start();
// Llamado a la Base de Datos
require_once './config/db_connection.php';
$pdo = getPDOConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        session_unset();
        session_destroy();


        header("Location: ../index.php");
        exit;
    } else {
        $error_message = "Token CSRF no válido.";
    }
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Logout</title>
    <link rel="stylesheet" href="./css/logout.css">
</head>
<body>
    <div class="logout-container">
        <h1>¿Seguro que quieres cerrar sesión?</h1>
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <form action="logout.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <button type="submit" class="btn-logout">Cerrar sesión</button>
            <a href="dashboard.php" class="btn-cancel">Cancelar</a>
        </form>
    </div>
</body>
</html>