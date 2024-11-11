<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Función para obtener los detalles de un producto
function obtenerDetallesProducto($codigo_barras, $user_id) {
    global $pdo;
    $query = "
        SELECT i.*, 
               c.nombre as categoria_nombre,
               d.nombre as departamento_nombre,
               GROUP_CONCAT(ip.ruta ORDER BY ip.orden ASC) as imagenes
        FROM inventario i
        LEFT JOIN categorias c ON i.categoria_id = c.id
        LEFT JOIN departamentos d ON i.departamento_id = d.id
        LEFT JOIN imagenes_producto ip ON i.id = ip.producto_id
        WHERE i.codigo_barras = ? AND i.user_id = ?
        GROUP BY i.id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$codigo_barras, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener el código de barras del producto
$codigo_barras = $_GET['codigo_barras'] ?? null;
$producto = null;

if ($codigo_barras) {
    $producto = obtenerDetallesProducto($codigo_barras, $user_id);
    if (!$producto) {
        $message = "Producto no encontrado.";
        $messageType = "error";
    }
} else {
    $message = "Código de barras no proporcionado.";
    $messageType = "error";
}

// Función para formatear moneda
function formatoMoneda($monto) {
    return '$' . number_format($monto, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Producto | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <style>
        .producto-detalle {
            background: #fff;
            padding: 25px;
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
            border-left: 3px solid #007bff;
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

        .galeria-imagenes {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .galeria-imagenes img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
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
                <a href="/welcome.php">Dashboard</a>
                <a href="/modules/pos/index.php">POS</a>
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
            <h2>Detalles del Producto</h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($producto): ?>
                <div class="producto-detalle">
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
                            <label>Descripción:</label>
                            <span><?= htmlspecialchars($producto['descripcion']) ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Stock Actual:</label>
                            <span><?= htmlspecialchars($producto['stock']) ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Precio Venta:</label>
                            <span><?= formatoMoneda($producto['precio_venta']) ?></span>
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

                    <h3>Galería de Imágenes</h3>
                    <div class="galeria-imagenes">
                        <?php 
                        if (!empty($producto['imagenes'])) {
                            $imagenes = explode(',', $producto['imagenes']);
                            foreach ($imagenes as $imagen): 
                                if (!empty($imagen)):
                        ?>
                            <img src="../../<?= htmlspecialchars($imagen) ?>" alt="Imagen del producto">
                        <?php 
                                endif;
                            endforeach;
                        } else {
                            echo '<p>No hay imágenes disponibles para este producto.</p>';
                        }
                        ?>
                    </div>

                    <div class="button-group">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver al Inventario
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 