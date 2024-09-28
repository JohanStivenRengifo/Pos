<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: ../../index.php");
    exit();
}

/**
 * Función para eliminar un producto por su código de barras y ID de usuario
 *
 * @param string $codigo_barras
 * @param int $user_id
 * @return bool
 */
function eliminarProducto($codigo_barras, $user_id)
{
    global $pdo;
    $query = "DELETE FROM inventario WHERE codigo_barras = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$codigo_barras, $user_id]);
}

// Inicialización de variables
$message = '';
$codigo_barras = $_GET['codigo_barras'] ?? null;

if ($codigo_barras) {
    $user_id = $_SESSION['user_id'];

    if (eliminarProducto($codigo_barras, $user_id)) {
        $message = "Producto eliminado exitosamente.";
    } else {
        $message = "Error al eliminar el producto. Puede que no exista.";
    }
} else {
    $message = "Código de barras no proporcionado.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Producto</title>
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
        <h2>Eliminar Producto</h2>
        <div class="message"><?= htmlspecialchars($message); ?></div>
        <a href="index.php" class="btn btn-primary">Volver a Listar Productos</a>
    </div>

</body>
</html>
