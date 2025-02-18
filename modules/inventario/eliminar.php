<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

/**
 * Función para obtener información del producto antes de eliminar
 */
function obtenerProducto($codigo_barras, $user_id) {
    global $pdo;
    $query = "SELECT i.*, 
                     c.nombre as categoria_nombre,
                     d.nombre as departamento_nombre
              FROM inventario i
              LEFT JOIN categorias c ON i.categoria_id = c.id
              LEFT JOIN departamentos d ON i.departamento_id = d.id
              WHERE i.codigo_barras = ? AND i.user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$codigo_barras, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Función para eliminar un producto y sus imágenes
 */
function eliminarProducto($codigo_barras, $user_id)
{
    global $pdo;
    try {
        $pdo->beginTransaction();

        // Obtener información del producto antes de eliminar
        $producto = obtenerProducto($codigo_barras, $user_id);
        if (!$producto) {
            throw new Exception("Producto no encontrado.");
        }

        // Eliminar imágenes físicas
        $query = "SELECT ruta FROM imagenes_producto WHERE producto_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$producto['id']]);
        $imagenes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($imagenes as $ruta) {
            $ruta_completa = __DIR__ . '/../../' . $ruta;
            if (file_exists($ruta_completa)) {
                unlink($ruta_completa);
            }
        }

        // Registrar en historial antes de eliminar
        $query = "INSERT INTO historial_productos (producto_id, user_id, tipo_cambio, detalle) 
                 VALUES (?, ?, 'eliminacion', ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $producto['id'],
            $user_id,
            json_encode([
                'codigo_barras' => $producto['codigo_barras'],
                'nombre' => $producto['nombre'],
                'stock' => $producto['stock'],
                'precio_venta' => $producto['precio_venta'],
                'fecha_eliminacion' => date('Y-m-d H:i:s')
            ])
        ]);

        // Eliminar el producto (las imágenes se eliminarán en cascada)
        $query = "DELETE FROM inventario WHERE codigo_barras = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$codigo_barras, $user_id]);

        if (!$result) {
            throw new Exception("Error al eliminar el producto.");
        }

        $pdo->commit();
        return [
            'success' => true,
            'message' => "Producto eliminado exitosamente.",
            'producto' => $producto
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Inicialización de variables
$message = '';
$messageType = '';
$codigo_barras = $_GET['codigo_barras'] ?? null;
$confirmado = $_POST['confirmado'] ?? false;
$producto = null;

if ($codigo_barras) {
    $producto = obtenerProducto($codigo_barras, $user_id);
    
    if ($confirmado) {
        $result = eliminarProducto($codigo_barras, $user_id);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        $producto = $result['success'] ? null : $producto;
    }
} else {
    $message = "Código de barras no proporcionado.";
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Producto | Numercia</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <style>
        .producto-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .producto-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-group {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #dc3545;
        }

        .info-group label {
            display: block;
            color: #6c757d;
            margin-bottom: 5px;
            font-size: 0.9em;
        }

        .info-group span {
            font-weight: 500;
            color: #343a40;
        }

        .warning-message {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">
            <a href="../../welcome.php">Numercia</a>
        </div>
        <div class="header-icons">
            <div class="user-info">
                <?php if (!empty($empresa_info['logo'])): ?>
                    <div class="company-logo">
                        <img src="/<?= htmlspecialchars($empresa_info['logo']); ?>" alt="Logo empresa">
                    </div>
                <?php else: ?>
                    <div class="company-logo default-logo">
                        <i class="fas fa-building"></i>
                    </div>
                <?php endif; ?>
                <div class="account">
                    <h4><?= htmlspecialchars($email) ?></h4>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <nav>
            <div class="side_navbar">
                <span>Menú Principal</span>
                <a href="/welcome.php">Dashboard</a>
                <a href="/pos/index.php">POS</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php" class="active">Inventario</a>
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
            <h2>Eliminar Producto</h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($producto && !$confirmado): ?>
                <div class="producto-card">
                    <div class="warning-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>¡Atención!</strong> Esta acción eliminará permanentemente el producto y todas sus imágenes asociadas.
                    </div>

                    <div class="producto-info">
                        <div class="info-group">
                            <label>Código de Barras:</label>
                            <span><?= htmlspecialchars($producto['codigo_barras']) ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Nombre:</label>
                            <span><?= htmlspecialchars($producto['nombre']) ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Stock Actual:</label>
                            <span><?= htmlspecialchars($producto['stock']) ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Precio Venta:</label>
                            <span>$<?= number_format($producto['precio_venta'], 2, ',', '.') ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Categoría:</label>
                            <span><?= htmlspecialchars($producto['categoria_nombre']) ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Departamento:</label>
                            <span><?= htmlspecialchars($producto['departamento_nombre']) ?></span>
                        </div>
                    </div>

                    <div class="button-group">
                        <button onclick="confirmarEliminacion()" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Confirmar Eliminación
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </div>
            <?php elseif (!$producto && $messageType === 'success'): ?>
                <div class="button-group">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver al Inventario
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function confirmarEliminacion() {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
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

    <?php if (!empty($message) && $messageType === 'success'): ?>
    Swal.fire({
        icon: 'success',
        title: 'Éxito',
        text: '<?= $message ?>',
        showConfirmButton: false,
        timer: 2000
    }).then(() => {
        setTimeout(() => {
            window.location.href = 'index.php';
        }, 500);
    });
    <?php elseif (!empty($message) && $messageType === 'error'): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: '<?= $message ?>'
    });
    <?php endif; ?>
    </script>
</body>
</html>