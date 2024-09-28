<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
$user_id = $_SESSION['user_id'] ?? $_COOKIE['user_id'] ?? null;
$email = $_SESSION['email'] ?? $_COOKIE['email'] ?? null;

if (!$user_id || !$email) {
    header("Location: ../../index.php");
    exit();
}

// Configuración de paginación
$limit = 30; // Número máximo de ventas por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Función para obtener las ventas del usuario con paginación
function getUserVentas($user_id, $limit, $offset) {
    global $pdo;
    $query = "SELECT v.*, c.nombre AS cliente_nombre 
              FROM ventas v 
              LEFT JOIN clientes c ON v.cliente_id = c.id 
              WHERE v.user_id = ? 
              ORDER BY v.fecha_venta DESC 
              LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para contar el total de ventas del usuario
function countUserVentas($user_id) {
    global $pdo;
    $query = "SELECT COUNT(*) FROM ventas WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Obtener las ventas y el total
$ventas = getUserVentas($user_id, $limit, $offset);
$total_ventas = countUserVentas($user_id);
$total_pages = ceil($total_ventas / $limit);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas</title>
    <link rel="stylesheet" href="../../css/modulos.css">
</head>
<body>

<div class="sidebar">
    <h2>Menú Principal</h2>
    <ul>
        <li><a href="../../welcome.php">Inicio</a></li>
        <li><a href="../../modules/ventas/index.php">Ventas</a></li>
        <li><a href="../../modules/reportes/index.php">Reportes</a></li>
        <li><a href="../../modules/ingresos/index.php">Ingresos</a></li>
        <li><a href="../../modules/egresos/index.php">Egresos</a></li>
        <li><a href="../../modules/inventario/index.php">Productos</a></li>
        <li><a href="../../modules/clientes/index.php">Clientes</a></li>
        <li><a href="../../modules/proveedores/index.php">Proveedores</a></li>
        <li><a href="../../modules/config/index.php">Configuración</a></li>
        <form method="POST" action="">
            <button type="submit" name="logout" class="logout-button">Cerrar Sesión</button>
        </form>
    </ul>
</div>

<div class="main-content">
    <h2>Reporte de Ventas</h2>
    <div class="table-container">
        <?php if (count($ventas) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID Venta</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $venta): ?>
                        <tr>
                            <td><?= htmlspecialchars($venta['id']); ?></td>
                            <td><?= htmlspecialchars($venta['fecha_venta']); ?></td>
                            <td><?= htmlspecialchars($venta['cliente_nombre']); ?></td>
                            <td><?= htmlspecialchars(number_format($venta['total'], 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Paginación -->
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i; ?>" class="<?= $i === $page ? 'active' : ''; ?>"><?= $i; ?></a>
                <?php endfor; ?>
            </div>
        <?php else: ?>
            <p>No hay ventas registradas.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
