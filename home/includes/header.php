<?php
session_start(); // Asegúrate de iniciar la sesión

// Verifica si el usuario ha iniciado sesión
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : 'Invitado';

?>

<header>
    <div class="logo">
        <h1>Sistema Contable</h1>
    </div>
    <div class="user-info">
        <span>Bienvenido, <?php echo htmlspecialchars($user_email); ?></span>
        <?php if (isset($_SESSION['email'])): ?>
            <a href="/auth/logout.php" class="logout">Cerrar Sesión</a>
        <?php else: ?>
            <a href="/auth/login.php" class="login">Iniciar Sesión</a>
        <?php endif; ?>
    </div>
</header>
