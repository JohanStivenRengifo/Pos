<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Verificar si se proporcionó un ID de venta
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$venta_id = (int)$_GET['id'];

// Obtener los detalles de la venta
function getVentaDetails($venta_id) {
    global $pdo;
    try {
        $query = "SELECT v.*, c.nombre AS cliente_nombre, c.email AS cliente_email, 
                         c.telefono AS cliente_telefono, c.direccion AS cliente_direccion
                  FROM ventas v 
                  LEFT JOIN clientes c ON v.cliente_id = c.id 
                  WHERE v.id = :venta_id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':venta_id' => $venta_id
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en getVentaDetails: " . $e->getMessage());
        return null;
    }
}

// Obtener los productos de la venta
function getVentaProductos($venta_id) {
    global $pdo;
    try {
        $query = "SELECT vd.*, p.nombre AS producto_nombre, p.codigo 
                  FROM venta_detalles vd 
                  JOIN inventario p ON vd.producto_id = p.id 
                  WHERE vd.venta_id = :venta_id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([':venta_id' => $venta_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en getVentaProductos: " . $e->getMessage());
        return [];
    }
}

$venta = getVentaDetails($venta_id);
if (!$venta) {
    error_log("No se encontró la venta con ID: " . $venta_id);
    header("Location: index.php");
    exit();
}

$productos = getVentaProductos($venta_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Venta #<?= $venta_id ?> | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-8">
            <!-- Encabezado -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Detalle de Venta #<?= $venta_id ?></h1>
                    <p class="text-gray-600">Información completa de la transacción</p>
                </div>
                <div class="space-x-2">
                    <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        <i class="fas fa-arrow-left mr-2"></i>Volver
                    </a>
                    <button onclick="imprimirVenta(<?= $venta_id ?>)" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        <i class="fas fa-print mr-2"></i>Imprimir
                    </button>
                </div>
            </div>

            <!-- Información de la Venta -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">Información de la Venta</h2>
                    <div class="space-y-3">
                        <p><span class="font-medium">Fecha:</span> <?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></p>
                        <p><span class="font-medium">N° Factura:</span> <?= htmlspecialchars($venta['numero_factura']) ?></p>
                        <p><span class="font-medium">Estado:</span> 
                            <span class="px-2 py-1 rounded-full text-sm <?= $venta['anulada'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                <?= $venta['anulada'] ? 'Anulada' : 'Activa' ?>
                            </span>
                        </p>
                        <p><span class="font-medium">Total:</span> $<?= number_format($venta['total'], 2) ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">Información del Cliente</h2>
                    <div class="space-y-3">
                        <p><span class="font-medium">Nombre:</span> <?= htmlspecialchars($venta['cliente_nombre'] ?? 'N/A') ?></p>
                        <p><span class="font-medium">Email:</span> <?= htmlspecialchars($venta['cliente_email'] ?? 'N/A') ?></p>
                        <p><span class="font-medium">Teléfono:</span> <?= htmlspecialchars($venta['cliente_telefono'] ?? 'N/A') ?></p>
                        <p><span class="font-medium">Dirección:</span> <?= htmlspecialchars($venta['cliente_direccion'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>

            <!-- Tabla de Productos -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <h2 class="text-lg font-semibold p-6 border-b">Productos Vendidos</h2>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio Unit.</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($productos as $producto): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($producto['codigo']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($producto['producto_nombre']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= $producto['cantidad'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                $<?= number_format($producto['precio_unitario'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                $<?= number_format($producto['cantidad'] * $producto['precio_unitario'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-right font-medium">Total:</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold">
                                $<?= number_format($venta['total'], 2) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </main>
    </div>

    <script>
    function imprimirVenta(id) {
        window.open(`../pos/controllers/imprimir_ticket.php?id=${id}`, '_blank');
    }
    </script>
</body>
</html> 