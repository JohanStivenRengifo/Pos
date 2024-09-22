<?php
require_once '../config/database.php';

$module = isset($_GET['submodule']) ? $_GET['submodule'] : 'productos'; 

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventarios - Sistema Contable</title>
    <link rel="stylesheet" href="../../css/home.css">
</head>
<body>
        <div id="wrapper">
                <div id="content">
            <?php
                switch ($module) {
                    case 'bodegas':
                        include 'bodegas.php';
                        break;
                    case 'productos':
                        include 'productos.php';
                        break;
                    default:
                        include 'productos.php';
                }
            ?>
        </div>
    </div>
    <script src="/js/script.js"></script>
</body>
</html>
