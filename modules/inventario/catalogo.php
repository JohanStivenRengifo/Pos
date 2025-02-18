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

// Modificar la consulta para incluir información de categorías y departamentos
$query = "
    SELECT 
        i.*,
        c.nombre as categoria_nombre,
        d.nombre as departamento_nombre,
        COALESCE(ip.ruta, '') as imagen_principal
    FROM inventario i
    LEFT JOIN categorias c ON i.categoria_id = c.id
    LEFT JOIN departamentos d ON i.departamento_id = d.id
    LEFT JOIN imagenes_producto ip ON i.id = ip.producto_id AND ip.es_principal = 1
    WHERE i.user_id = ?
    ORDER BY i.nombre ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para formatear moneda si no existe
if (!function_exists('formatoMoneda')) {
    function formatoMoneda($monto) {
        return '$' . number_format($monto, 2, ',', '.');
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo | Numercia</title>
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
                        <h1 class="text-3xl font-bold text-gray-900">Catálogo de Productos</h1>
                        <p class="mt-2 text-sm text-gray-600">
                            Visualiza todos los productos en tu inventario
                        </p>
                    </div>
                    <a href="index.php" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Volver
                    </a>
                </div>

                <!-- Vista de catálogo -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="p-6 bg-gray-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-gray-800">
                                <i class="fas fa-book text-blue-600 mr-2"></i>
                                Lista de Productos
                            </h2>
                            <span class="text-sm text-gray-600">
                                Total: <?= count($productos) ?> productos
                            </span>
                        </div>
                    </div>

                    <?php if (count($productos) > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 p-6">
                            <?php foreach ($productos as $producto): ?>
                                <div class="bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                                    <!-- Imagen del producto -->
                                    <div class="aspect-w-16 aspect-h-9 rounded-t-lg overflow-hidden bg-gray-100">
                                        <?php if (!empty($producto['imagen_principal'])): ?>
                                            <img src="../../<?= htmlspecialchars($producto['imagen_principal']) ?>"
                                                 alt="<?= htmlspecialchars($producto['nombre']) ?>"
                                                 class="w-full h-48 object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-48 flex items-center justify-center bg-gray-100">
                                                <i class="fas fa-image text-gray-400 text-4xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Información del producto -->
                                    <div class="p-4 space-y-3">
                                        <h3 class="text-lg font-semibold text-gray-800 line-clamp-2">
                                            <?= htmlspecialchars($producto['nombre']) ?>
                                        </h3>
                                        
                                        <div class="space-y-2">
                                            <!-- Código de barras -->
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-barcode w-5 text-gray-400"></i>
                                                <span class="ml-2"><?= htmlspecialchars($producto['codigo_barras']) ?></span>
                                            </div>

                                            <!-- Categoría y Departamento -->
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-tag w-5 text-gray-400"></i>
                                                <span class="ml-2"><?= htmlspecialchars($producto['categoria_nombre']) ?></span>
                                            </div>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-building w-5 text-gray-400"></i>
                                                <span class="ml-2"><?= htmlspecialchars($producto['departamento_nombre']) ?></span>
                                            </div>

                                            <!-- Stock -->
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center text-sm text-gray-600">
                                                    <i class="fas fa-box w-5 text-gray-400"></i>
                                                    <span class="ml-2">Stock:</span>
                                                </div>
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
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $stockClass ?>">
                                                    <?= $producto['stock'] ?> (<?= $stockText ?>)
                                                </span>
                                            </div>

                                            <!-- Precios -->
                                            <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                                                <div class="text-sm text-gray-600">
                                                    Costo: <span class="font-medium"><?= formatoMoneda($producto['precio_costo']) ?></span>
                                                </div>
                                                <div class="text-sm font-medium text-blue-600">
                                                    Venta: <?= formatoMoneda($producto['precio_venta']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Botones de acción -->
                                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex justify-end space-x-2">
                                        <a href="ver.php?codigo_barras=<?= urlencode($producto['codigo_barras']) ?>"
                                           class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors">
                                            <i class="fas fa-eye mr-1"></i>
                                            Ver detalles
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                <i class="fas fa-box-open text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No hay productos disponibles</h3>
                            <p class="text-gray-500">Aún no has agregado productos a tu inventario.</p>
                            <a href="crear.php" 
                               class="inline-flex items-center px-4 py-2 mt-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>
                                Agregar Producto
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Script para activar el elemento actual del sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const currentUrl = window.location.pathname;
            const sidebarLinks = document.querySelectorAll('.side_navbar a');
            sidebarLinks.forEach(link => {
                if (link.getAttribute('href') === currentUrl) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
