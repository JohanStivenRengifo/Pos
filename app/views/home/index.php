<?php
// Configuración de cookies seguras
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => true, 
    'httponly' => true, 
    'samesite' => 'Strict'
]);

session_start();
require_once '../../helpers/database.php';
require_once '../../helpers/csrf.php';
require_once '../../helpers/security.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

// Generar token CSRF
$csrf_token = generate_csrf_token();

// Módulo y sub-módulo por defecto
$module = isset($_GET['module']) ? sanitizeInput($_GET['module']) : 'ingresos';
$submodule = isset($_GET['submodule']) ? sanitizeInput($_GET['submodule']) : 'index';

// Función para cargar sub-módulos
function load_submodule($module, $submodule) {
    $module_path = __DIR__ . "/modules/$module/$submodule.php";
    if (file_exists($module_path)) {
        include $module_path;
    } else {
        echo "<div class='error'>El sub-módulo '$submodule' en el módulo '$module' no se ha encontrado.</div>";
    }
}

// Configuración de la cabecera para evitar problemas de cacheo
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Sistema Contable</title>
    <link rel="stylesheet" href="../css/home.css">
</head>
<body>
    <?php 
    $header_path = './includes/header.php';
    $sidebar_path = './includes/sidebar.php';
    $footer_path = './includes/footer.php';

    if (file_exists($header_path)) {
        include $header_path; 
    } else {
        echo "<div class='error'>No se pudo cargar el archivo de cabecera.</div>";
    }

    if (file_exists($sidebar_path)) {
        include $sidebar_path; 
    } else {
        echo "<div class='error'>No se pudo cargar el archivo de barra lateral.</div>";
    }
    ?>
    <div id="wrapper">
        <div id="content">
            <?php load_submodule($module, $submodule); ?>
        </div>
    </div>
    <?php 
    if (file_exists($footer_path)) {
        include $footer_path; 
    } else {
        echo "<div class='error'>No se pudo cargar el archivo de pie de página.</div>";
    }
    ?>
    <script src="../js/script.js"></script>
</body>
</html>
