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
    <title>Ver Producto | Numercia</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#1e293b',
                        accent: '#3b82f6'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Encabezado -->
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Detalles del Producto</h1>
                        <p class="mt-2 text-sm text-gray-600">
                            Información detallada del producto
                        </p>
                    </div>
                    <a href="index.php" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Volver
                    </a>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="mb-8 bg-red-50 border-l-4 border-red-400 p-4 rounded-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">
                                    <?= htmlspecialchars($message) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($producto): ?>
                    <!-- Información del Producto -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <!-- Sección de imágenes -->
                        <div class="p-6 bg-gray-50 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                                <i class="fas fa-images text-blue-600 mr-2"></i>
                                Galería de Imágenes
                            </h2>
                            <?php if (!empty($producto['imagenes'])): ?>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <?php 
                                    $imagenes = explode(',', $producto['imagenes']);
                                    foreach ($imagenes as $imagen): 
                                        if (!empty($imagen)):
                                    ?>
                                        <div class="relative group">
                                            <img src="../../<?= htmlspecialchars($imagen) ?>" 
                                                 alt="Imagen del producto"
                                                 class="w-full h-48 object-cover rounded-lg shadow-sm group-hover:shadow-md transition-shadow">
                                            <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">
                                                <a href="../../<?= htmlspecialchars($imagen) ?>" 
                                                   target="_blank"
                                                   class="text-white hover:text-blue-200 transition-colors">
                                                    <i class="fas fa-expand-alt text-2xl"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                                    <i class="fas fa-image text-4xl text-gray-400 mb-2"></i>
                                    <p class="text-gray-500">No hay imágenes disponibles para este producto</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Detalles del producto -->
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Información básica -->
                                <div class="space-y-6">
                                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                        Información Básica
                                    </h3>
                                    
                                    <div class="space-y-4">
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <label class="block text-sm font-medium text-gray-500">Código de Barras</label>
                                            <p class="mt-1 text-lg font-medium text-gray-900">
                                                <i class="fas fa-barcode text-gray-400 mr-2"></i>
                                                <?= htmlspecialchars($producto['codigo_barras']) ?>
                                            </p>
                                        </div>

                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <label class="block text-sm font-medium text-gray-500">Nombre</label>
                                            <p class="mt-1 text-lg font-medium text-gray-900">
                                                <?= htmlspecialchars($producto['nombre']) ?>
                                            </p>
                                        </div>

                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <label class="block text-sm font-medium text-gray-500">Descripción</label>
                                            <p class="mt-1 text-gray-900">
                                                <?= htmlspecialchars($producto['descripcion']) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Información de stock y precios -->
                                <div class="space-y-6">
                                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                                        <i class="fas fa-chart-line text-green-600 mr-2"></i>
                                        Stock y Precios
                                    </h3>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <label class="block text-sm font-medium text-gray-500">Stock Actual</label>
                                            <?php
                                            $stockClass = 'text-green-600 bg-green-100';
                                            $stockText = 'Normal';
                                            if ($producto['stock'] === '0') {
                                                $stockClass = 'text-red-600 bg-red-100';
                                                $stockText = 'Agotado';
                                            } elseif ($producto['stock'] <= $producto['stock_minimo']) {
                                                $stockClass = 'text-yellow-600 bg-yellow-100';
                                                $stockText = 'Bajo';
                                            }
                                            ?>
                                            <div class="mt-1 flex items-center">
                                                <span class="text-2xl font-bold mr-2"><?= $producto['stock'] ?></span>
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $stockClass ?>">
                                                    <?= $stockText ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <label class="block text-sm font-medium text-gray-500">Stock Mínimo</label>
                                            <p class="mt-1 text-lg font-medium text-gray-900">
                                                <?= htmlspecialchars($producto['stock_minimo']) ?>
                                            </p>
                                        </div>

                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <label class="block text-sm font-medium text-gray-500">Precio de Venta</label>
                                            <p class="mt-1 text-lg font-bold text-blue-600">
                                                <?= formatoMoneda($producto['precio_venta']) ?>
                                            </p>
                                        </div>

                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <label class="block text-sm font-medium text-gray-500">Margen de Ganancia</label>
                                            <p class="mt-1 text-lg font-medium text-gray-900">
                                                <?= number_format($producto['margen_ganancia'], 2) ?>%
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Categorización -->
                                <div class="space-y-6">
                                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                                        <i class="fas fa-tags text-purple-600 mr-2"></i>
                                        Categorización
                                    </h3>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <label class="block text-sm font-medium text-gray-500">Categoría</label>
                                            <p class="mt-1 text-lg font-medium text-gray-900">
                                                <?= htmlspecialchars($producto['categoria_nombre']) ?>
                                            </p>
                                        </div>

                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <label class="block text-sm font-medium text-gray-500">Departamento</label>
                                            <p class="mt-1 text-lg font-medium text-gray-900">
                                                <?= htmlspecialchars($producto['departamento_nombre']) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                            <div class="flex justify-end space-x-4">
                                <a href="modificar.php?codigo_barras=<?= urlencode($producto['codigo_barras']) ?>" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-edit mr-2"></i>
                                    Editar Producto
                                </a>
                                <a href="index.php" 
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Volver al Inventario
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html> 