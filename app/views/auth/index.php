<?php
require_once '../../controllers/UserController.php';

$controller = new UserController();
$action = isset($_GET['action']) ? $_GET['action'] : null;

$controller->handleRequest($action);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Inicio - Sistema Contable</title>
    <link rel="stylesheet" href="/css/style.css">
</head>

<body>
    <div class="container">
        <header>
            <h1>Bienvenido al Sistema Contable</h1>
        </header>
        <main>
            <div class="guest-message">
                <p><a href='index.php?action=register' class='button'>Registrar</a></p>
                <p><a href='index.php?action=login' class='button'>Iniciar sesión</a></p>
            </div>
            <?php if (isset($error) && !empty($error)): ?>
                <p class='error'><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </main>
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Sistema Contable. Todos los derechos reservados.</p>
        </footer>
    </div>
</body>

</html>