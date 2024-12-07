<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$credito = null;
$pagos = [];
$total_pagado = 0;

try {
    if (!isset($_GET['id'])) {
        throw new Exception("ID de crédito no especificado");
    }

    // Obtener información del crédito
    $query = "SELECT c.*, 
                     v.numero_factura,
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre,
                     cl.identificacion as cliente_identificacion
              FROM creditos c
              LEFT JOIN ventas v ON c.venta_id = v.id
              LEFT JOIN clientes cl ON v.cliente_id = cl.id
              WHERE c.id = ? AND cl.user_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $credito = $stmt->fetch();

    if (!$credito) {
        throw new Exception("Crédito no encontrado");
    }

    // Obtener historial de pagos
    $query = "SELECT cp.*, 
                     i.descripcion as ingreso_descripcion,
                     i.created_at as fecha_registro,
                     u.nombre as registrado_por
              FROM creditos_pagos cp
              LEFT JOIN ingresos i ON cp.id = i.credito_pago_id
              LEFT JOIN users u ON i.user_id = u.id
              WHERE cp.credito_id = ?
              ORDER BY cp.numero_cuota ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $pagos = $stmt->fetchAll();

    // Calcular totales
    $total_pagado = array_sum(array_map(function($pago) {
        return $pago['estado'] === 'Pagado' ? $pago['monto'] : 0;
    }, $pagos));

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Pagos | VendEasy</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
            <?php include '../../includes/sidebar.php'; ?>
            
            <div class="w-full lg:w-3/4 px-4">
                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-800">
                                Historial de Pagos - Factura #<?= htmlspecialchars($credito['numero_factura']) ?>
                            </h2>
                            <div class="flex space-x-2">
                                <a href="ver.php?id=<?= $_GET['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-arrow-left mr-2"></i>Volver
                                </a>
                                <button onclick="window.print()" class="text-gray-600 hover:text-gray-800">
                                    <i class="fas fa-print mr-2"></i>Imprimir
                                </button>
                            </div>
                        </div>

                        <!-- Información del Crédito -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 bg-gray-50 p-4 rounded-lg">
                            <div>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Cliente:</span> 
                                    <?= htmlspecialchars($credito['cliente_nombre']) ?>
                                </p>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Identificación:</span> 
                                    <?= htmlspecialchars($credito['cliente_identificacion']) ?>
                                </p>
                                <p class="text-sm">
                                    <span class="font-medium">Estado:</span>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?= $credito['estado'] === 'Al día' ? 'bg-green-100 text-green-800' : 
                                           ($credito['estado'] === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($credito['estado'] === 'Atrasado' ? 'bg-orange-100 text-orange-800' : 
                                           ($credito['estado'] === 'Pagado' ? 'bg-blue-100 text-blue-800' : 
                                           'bg-red-100 text-red-800'))) ?>">
                                        <?= $credito['estado'] ?>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Monto Total:</span> 
                                    $<?= number_format($credito['monto_total'], 2, ',', '.') ?>
                                </p>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Total Pagado:</span> 
                                    $<?= number_format($total_pagado, 2, ',', '.') ?>
                                </p>
                                <p class="text-sm">
                                    <span class="font-medium">Saldo Pendiente:</span> 
                                    $<?= number_format($credito['saldo_pendiente'], 2, ',', '.') ?>
                                </p>
                            </div>
                        </div>

                        <!-- Historial de Pagos -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cuota</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vencimiento</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Fecha Pago</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Método</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Registrado Por</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($pagos as $pago): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-sm">
                                                <?= $pago['numero_cuota'] ?>/<?= $credito['cuotas'] ?>
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                <?= date('d/m/Y', strtotime($pago['fecha_vencimiento_cuota'])) ?>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-right">
                                                $<?= number_format($pago['monto'], 2, ',', '.') ?>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-center">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    <?= $pago['estado'] === 'Pagado' ? 'bg-green-100 text-green-800' : 
                                                       ($pago['estado'] === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                                                       'bg-red-100 text-red-800') ?>">
                                                    <?= $pago['estado'] ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-center">
                                                <?= $pago['fecha_pago'] ? date('d/m/Y', strtotime($pago['fecha_pago'])) : '-' ?>
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                <?= $pago['metodo_pago'] ?? '-' ?>
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                <?php if ($pago['estado'] === 'Pagado'): ?>
                                                    <div>
                                                        <p class="font-medium"><?= htmlspecialchars($pago['registrado_por']) ?></p>
                                                        <p class="text-xs text-gray-500">
                                                            <?= date('d/m/Y H:i', strtotime($pago['fecha_registro'])) ?>
                                                        </p>
                                                    </div>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print {
                display: none;
            }
            body {
                background: white;
            }
            .container {
                max-width: none;
                padding: 0;
            }
            .shadow-md {
                box-shadow: none;
            }
        }
    </style>
</body>
</html> 