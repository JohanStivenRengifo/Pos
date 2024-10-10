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
$confirmado = $_POST['confirmado'] ?? false;

if ($codigo_barras && $confirmado) {
    $user_id = $_SESSION['user_id'];

    if (eliminarProducto($codigo_barras, $user_id)) {
        $message = "Producto eliminado exitosamente.";
    } else {
        $message = "Error al eliminar el producto. Puede que no exista.";
    }
} elseif (!$codigo_barras) {
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>Menú Principal</h2>
        <ul>
            <li><a href="../../welcome.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="../../modules/ventas/index.php"><i class="fas fa-shopping-cart"></i> Ventas</a></li>
            <li><a href="../../modules/reportes/index.php"><i class="fas fa-chart-bar"></i> Reportes</a></li>
            <li><a href="../../modules/ingresos/index.php"><i class="fas fa-plus-circle"></i> Ingresos</a></li>
            <li><a href="../../modules/egresos/index.php"><i class="fas fa-minus-circle"></i> Egresos</a></li>
            <li><a href="../../modules/inventario/index.php"><i class="fas fa-box"></i> Productos</a></li>
            <li><a href="../../modules/clientes/index.php"><i class="fas fa-users"></i> Clientes</a></li>
            <li><a href="../../modules/proveedores/index.php"><i class="fas fa-truck"></i> Proveedores</a></li>
            <li><a href="../../modules/config/index.php"><i class="fas fa-cog"></i> Configuración</a></li>
            <form method="POST" action="">
                <button type="submit" name="logout" class="logout-button"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</button>
            </form>
        </ul>
    </div>

    <div class="main-content">
        <h2><i class="fas fa-trash"></i> Eliminar Producto</h2>
        <?php if (!empty($message)): ?>
            <div class="message"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($codigo_barras && !$confirmado): ?>
            <p>¿Estás seguro de que deseas eliminar el producto con código de barras <?= htmlspecialchars($codigo_barras) ?>?</p>
            <button onclick="confirmarEliminacion()" class="btn btn-danger"><i class="fas fa-trash"></i> Confirmar Eliminación</button>
        <?php endif; ?>
        <a href="index.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Volver a Listar Productos</a>
    </div>

    <script>
    function confirmarEliminacion() {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "No podrás revertir esta acción",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'eliminar.php?codigo_barras=<?= $codigo_barras ?>';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'confirmado';
                input.value = 'true';
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    <?php if (!empty($message)): ?>
    Swal.fire({
        icon: 'info',
        title: 'Notificación',
        text: '<?= $message ?>'
    });
    <?php endif; ?>
    </script>
</body>
</html>