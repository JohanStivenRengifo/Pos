<?php
require_once __DIR__ . '/../../../config/database.php'; 
require_once __DIR__ . '/../../../helpers/security.php';

$submodule = isset($_GET['submodule']) ? sanitizeInput($_GET['submodule']) : 'empresa';

$allowed_submodules = ['empresa', 'sistema', 'usuarios', 'puntos_de_venta', 'avanzada', 'seguridad'];
if (!in_array($submodule, $allowed_submodules)) {
    $submodule = 'empresa'; 
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración - Sistema POS</title>
    <link rel="stylesheet" href="../../css/home.css">
</head>
<body>
    <div id="wrapper">
        <div id="content">
            <?php
            $file_path = __DIR__ . "/$submodule.php";
            if (file_exists($file_path)) {
                include $file_path;
            } else {
                echo "<div class='error'>El sub-módulo '$submodule' no se ha encontrado.</div>";
            }
            ?>
        </div>
    </div>
    <script src="../../js/script.js"></script>
</body>
</html>