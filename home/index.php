<?php
// Incluir archivos necesarios
require_once '../config/db_connection.php';
require_once './csrf.php';
require_once './functions.php';

// Generar token CSRF
$csrf_token = generate_csrf_token();

// Módulo y sub-módulo por defecto
$module = $_GET['module'] ?? 'ingresos';
$submodule = $_GET['submodule'] ?? 'index';

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
    <?php include './includes/header.php'; ?>
    <div id="wrapper">
        <?php include './includes/sidebar.php'; ?>
        <div id="content">
            <?php load_submodule($module, $submodule); ?>
        </div>
    </div>
    <?php include './includes/footer.php'; ?>
    <script src="../js/script.js"></script>
</body>
</html>
