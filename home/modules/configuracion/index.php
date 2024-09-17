<?php
require_once '../config/db_connection.php';

// Obtener el submódulo actual, por defecto 'empresa'
$submodule = isset($_GET['submodule']) ? $_GET['submodule'] : 'empresa';

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
            switch ($submodule) {
                case 'empresa':
                    include 'empresa.php';
                    break;
                case 'sistema':
                    include 'sistema.php';
                    break;
                case 'usuarios':
                    include 'usuarios.php';
                    break;
                case 'puntos_de_venta':
                    include 'puntos_de_venta.php';
                    break;
                case 'avanzada':
                    include 'avanzada.php';
                    break;
                case 'seguridad':
                    include 'seguridad.php';
                    break;
                default:
                    include 'empresa.php';
            }
            ?>
        </div>
    </div>
    <script src="../../js/script.js"></script>
</body>
</html>