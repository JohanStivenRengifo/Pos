<?php
require_once('../config/db.php');

$tipo = filter_input(INPUT_GET, 'tipo', FILTER_SANITIZE_STRING);
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$detalles = array();
$documento = array();
$error = '';

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    switch($tipo) {
        case 'Venta':
            // Obtener detalles de la venta
            $stmt = $db->prepare("
                SELECT v.*, c.primer_nombre, c.segundo_nombre, c.apellidos, c.identificacion
                FROM ventas v
                JOIN clientes c ON v.cliente_id = c.id
                WHERE v.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);

            // Obtener productos de la venta (actualizado para usar inventario)
            $stmt = $db->prepare("
                SELECT vd.*, i.nombre as producto_nombre, i.codigo_barras
                FROM venta_detalles vd
                JOIN inventario i ON vd.producto_id = i.id
                WHERE vd.venta_id = :venta_id
            ");
            $stmt->execute(['venta_id' => $id]);
            $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'Cotización':
            // Obtener detalles de la cotización
            $stmt = $db->prepare("
                SELECT c.*, cl.primer_nombre, cl.segundo_nombre, cl.apellidos, cl.identificacion
                FROM cotizaciones c
                JOIN clientes cl ON c.cliente_id = cl.id
                WHERE c.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);

            // Obtener productos de la cotización (actualizado para usar inventario)
            $stmt = $db->prepare("
                SELECT cd.*, i.nombre as producto_nombre, i.codigo_barras
                FROM cotizacion_detalles cd
                JOIN inventario i ON cd.producto_id = i.id
                WHERE cd.cotizacion_id = :cotizacion_id
            ");
            $stmt->execute(['cotizacion_id' => $id]);
            $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'Crédito':
            // Obtener detalles del crédito
            $stmt = $db->prepare("
                SELECT cr.*, c.primer_nombre, c.segundo_nombre, c.apellidos, c.identificacion
                FROM creditos cr
                JOIN ventas v ON cr.venta_id = v.id
                JOIN clientes c ON v.cliente_id = c.id
                WHERE cr.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);

            // Obtener pagos del crédito
            $stmt = $db->prepare("
                SELECT *
                FROM creditos_pagos
                WHERE credito_id = :credito_id
                ORDER BY numero_cuota
            ");
            $stmt->execute(['credito_id' => $id]);
            $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles - <?php echo htmlspecialchars($tipo); ?> - Numercia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="shortcut icon" href="./favicon/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        .detail-card {
            transition: all 0.3s ease;
        }
        .detail-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Navbar simplificado -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center space-x-2">
                        <i class="fas fa-calculator text-primary text-2xl"></i>
                        <span class="text-xl font-bold text-gray-900">Numercia</span>
                    </a>
                </div>
                    <div class="flex items-center space-x-4">
                        <a href="index.php" class="text-gray-600 hover:text-gray-900 transition-colors duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Volver
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm"><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Breadcrumbs -->
                <nav class="flex mb-6" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="index.php" class="text-gray-700 hover:text-blue-600">
                                <i class="fas fa-home mr-2"></i>Inicio
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                <span class="text-gray-500">Detalles de <?php echo htmlspecialchars($tipo); ?></span>
                            </div>
                        </li>
                    </ol>
                </nav>

                <!-- Información General Mejorada -->
                <div class="bg-white shadow-lg rounded-lg p-6 mb-6 detail-card">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 mr-4">
                                <i class="fas fa-file-alt text-blue-600 text-2xl"></i>
                    </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">
                                    <?php echo htmlspecialchars($tipo); ?> #<?php echo htmlspecialchars($documento['numero_factura'] ?? $documento['id']); ?>
                                </h2>
                                <p class="text-gray-600">
                                    Emitido el <?php echo date('d/m/Y', strtotime($documento['fecha'] ?? $documento['fecha_inicio'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <?php if ($tipo === 'Venta' && $documento['estado'] !== 'Anulada'): ?>
                                <a href="generar_pdf.php?tipo=<?php echo urlencode($tipo); ?>&id=<?php echo $documento['id']; ?>" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-300 group">
                                    <i class="fas fa-print mr-2 group-hover:animate-bounce"></i>
                                    Reimprimir Factura
                                </a>
                            <?php endif; ?>
                            <span class="px-3 py-1 text-sm font-semibold rounded-full
                                <?php echo $documento['estado'] === 'Anulada' ? 'bg-red-100 text-red-800' : 
                                ($documento['estado'] === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                <?php echo htmlspecialchars($documento['estado']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Grid de información mejorado -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Cliente -->
                        <div class="p-6 bg-gray-50 rounded-lg hover:shadow-md transition-shadow duration-300">
                            <h3 class="font-semibold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-user mr-2 text-blue-500"></i>
                                <span>Información del Cliente</span>
                            </h3>
                            <div class="space-y-2">
                                <p class="text-sm text-gray-600 flex items-center">
                                    <span class="w-24 font-medium">Nombre:</span>
                                    <span><?php echo htmlspecialchars($documento['primer_nombre'] . ' ' . $documento['segundo_nombre'] . ' ' . $documento['apellidos']); ?></span>
                                </p>
                                <p class="text-sm text-gray-600 flex items-center">
                                    <span class="w-24 font-medium">ID:</span>
                                    <span><?php echo htmlspecialchars($documento['identificacion']); ?></span>
                                </p>
                            </div>
                        </div>

                        <!-- Detalles del Documento -->
                        <div class="p-6 bg-gray-50 rounded-lg hover:shadow-md transition-shadow duration-300">
                            <h3 class="font-semibold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-file-invoice mr-2 text-blue-500"></i>
                                <span>Detalles del Documento</span>
                            </h3>
                            <div class="space-y-2">
                                <p class="text-sm text-gray-600 flex items-center">
                                    <span class="w-24 font-medium">Total:</span>
                                    <span class="text-lg font-semibold text-blue-600">
                                        $<?php echo number_format($documento['total'] ?? $documento['monto_total'], 2, ',', '.'); ?>
                                    </span>
                                </p>
                                <?php if ($tipo === 'Venta'): ?>
                                    <p class="text-sm text-gray-600 flex items-center">
                                        <span class="w-24 font-medium">Método:</span>
                                        <span class="px-2 py-1 bg-gray-100 rounded-full text-xs">
                                            <?php echo htmlspecialchars($documento['metodo_pago']); ?>
                                        </span>
                                </p>
                            <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($tipo === 'Crédito'): ?>
                            <!-- Información del Crédito -->
                            <div class="p-6 bg-gray-50 rounded-lg hover:shadow-md transition-shadow duration-300">
                                <h3 class="font-semibold text-gray-700 mb-3 flex items-center">
                                    <i class="fas fa-credit-card mr-2 text-blue-500"></i>
                                    <span>Detalles del Crédito</span>
                                </h3>
                                <div class="space-y-2">
                                    <p class="text-sm text-gray-600 flex items-center">
                                        <span class="w-24 font-medium">Plazo:</span>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                                            <?php echo htmlspecialchars($documento['plazo']); ?> meses
                                        </span>
                                    </p>
                                    <p class="text-sm text-gray-600 flex items-center">
                                        <span class="w-24 font-medium">Cuota:</span>
                                        <span class="text-lg font-semibold text-blue-600">
                                            $<?php echo number_format($documento['valor_cuota'], 2, ',', '.'); ?>
                                </span>
                            </p>
                        </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Detalles mejorados -->
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-list-ul mr-2 text-blue-500"></i>
                            <?php
                            switch($tipo) {
                                case 'Venta':
                                    echo 'Productos Vendidos';
                                    break;
                                case 'Cotización':
                                    echo 'Productos Cotizados';
                                    break;
                                case 'Crédito':
                                    echo 'Pagos del Crédito';
                                    break;
                            }
                            ?>
                        </h3>
                        <?php if ($tipo !== 'Crédito'): ?>
                            <div class="text-sm text-gray-600">
                                <span class="font-medium">Total Items:</span>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full ml-2">
                                    <?php echo count($detalles); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <?php if ($tipo === 'Crédito'): ?>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-hashtag mr-1"></i>Cuota
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-calendar mr-1"></i>Fecha Vencimiento
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-dollar-sign mr-1"></i>Monto
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-info-circle mr-1"></i>Estado
                                        </th>
                                    <?php else: ?>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-barcode mr-1"></i>Código
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-box mr-1"></i>Producto
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-sort-amount-up mr-1"></i>Cantidad
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-tag mr-1"></i>Precio Unit.
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-calculator mr-1"></i>Subtotal
                                        </th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($detalles as $detalle): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <?php if ($tipo === 'Crédito'): ?>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($detalle['numero_cuota']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('d/m/Y', strtotime($detalle['fecha_vencimiento_cuota'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                $<?php echo number_format($detalle['monto'], 2, ',', '.'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $detalle['estado'] === 'Pagado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                    <?php echo htmlspecialchars($detalle['estado']); ?>
                                                </span>
                                            </td>
                                        <?php else: ?>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($detalle['codigo_barras']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($detalle['producto_nombre']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($detalle['cantidad']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                $<?php echo number_format($detalle['precio_unitario'], 2, ',', '.'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                $<?php echo number_format($detalle['cantidad'] * $detalle['precio_unitario'], 2, ',', '.'); ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white mt-12">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="text-center text-sm">
                    <p>&copy; <?php echo date('Y'); ?> Portal de Clientes. Todos los derechos reservados.</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html> 