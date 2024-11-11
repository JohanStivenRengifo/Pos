<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

$productos = [];

// Consultar productos
$query = "SELECT * FROM inventario WHERE user_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
</head>
<body>
    <header class="header">
        <div class="logo">
            <a href="../../welcome.php">VendEasy</a>
        </div>
        <div class="header-icons">
            <i class="fas fa-bell"></i>
            <div class="account">
                <h4><?= htmlspecialchars($email) ?></h4>
            </div>
        </div>
    </header>
    <div class="container">
        <nav>
        <div class="side_navbar">
                <span>Menú Principal</span>
                <a href="#" class="active">Dashboard</a>
                <a href="/modules/pos/index.php">POS</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
                <a href="/modules/proveedores/index.php">Proveedores</a>
                <a href="/modules/reportes/index.php">Reportes</a>
                <a href="/modules/config/index.php">Configuración</a>

                <div class="links">
                    <span>Enlaces Rápidos</span>
                    <a href="/ayuda.php">Ayuda</a>
                    <a href="/contacto.php">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Catálogo de Productos</h2>
            <div class="promo_card">
                <h1>Lista Completa de Productos</h1>
                <span>Visualice todos los productos en su inventario.</span>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Productos en Inventario</h4>
                    </div>
                    <?php if (count($productos) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Código de Barras</th>
                                    <th>Precio Costo</th>
                                    <th>Precio Venta</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $producto): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($producto['id']); ?></td>
                                        <td><?= htmlspecialchars($producto['nombre']); ?></td>
                                        <td><?= htmlspecialchars($producto['codigo_barras']); ?></td>
                                        <td><?= htmlspecialchars(number_format($producto['precio_costo'], 2, ',', '.')); ?></td>
                                        <td><?= htmlspecialchars(number_format($producto['precio_venta'], 2, ',', '.')); ?></td>
                                        <td><?= htmlspecialchars($producto['stock']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No hay productos disponibles en el catálogo.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
