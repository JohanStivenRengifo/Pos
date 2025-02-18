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

try {
    if (!isset($_GET['id'])) {
        throw new Exception("ID de crédito no especificado");
    }

    // Obtener información del crédito
    $query = "SELECT c.*, 
                     v.numero_factura,
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre,
                     cl.identificacion as cliente_identificacion,
                     cl.telefono as cliente_telefono,
                     cl.email as cliente_email
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

    // Obtener plan de pagos
    $query = "SELECT * FROM creditos_pagos 
              WHERE credito_id = ?
              ORDER BY numero_cuota ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $pagos = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan de Pagos | Numercia</title>
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
                                Plan de Pagos - Factura #<?= htmlspecialchars($credito['numero_factura']) ?>
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

                        <!-- Información del Cliente y Crédito -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 bg-gray-50 p-4 rounded-lg">
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Información del Cliente</h3>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Nombre:</span> 
                                    <?= htmlspecialchars($credito['cliente_nombre']) ?>
                                </p>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Identificación:</span> 
                                    <?= htmlspecialchars($credito['cliente_identificacion']) ?>
                                </p>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Teléfono:</span> 
                                    <?= htmlspecialchars($credito['cliente_telefono']) ?>
                                </p>
                                <p class="text-sm">
                                    <span class="font-medium">Email:</span> 
                                    <?= htmlspecialchars($credito['cliente_email']) ?>
                                </p>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Detalles del Crédito</h3>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Monto Total:</span> 
                                    $<?= number_format($credito['monto_total'], 2, ',', '.') ?>
                                </p>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Interés:</span> 
                                    <?= number_format($credito['interes'], 2) ?>%
                                </p>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Plazo:</span> 
                                    <?= $credito['plazo'] ?> días
                                </p>
                                <p class="text-sm">
                                    <span class="font-medium">Cuotas:</span> 
                                    <?= $credito['cuotas'] ?> x $<?= number_format($credito['valor_cuota'], 2, ',', '.') ?>
                                </p>
                            </div>
                        </div>

                        <!-- Resumen de Pagos -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-blue-800">Total Crédito</h3>
                                <p class="text-2xl font-bold text-blue-900">
                                    $<?= number_format($credito['monto_total'], 2, ',', '.') ?>
                                </p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-green-800">Pagado</h3>
                                <p class="text-2xl font-bold text-green-900">
                                    $<?= number_format($credito['monto_pagado'], 2, ',', '.') ?>
                                </p>
                            </div>
                            <div class="bg-red-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-red-800">Pendiente</h3>
                                <p class="text-2xl font-bold text-red-900">
                                    $<?= number_format($credito['saldo_pendiente'], 2, ',', '.') ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-800">Valor Cuota</h3>
                                <p class="text-2xl font-bold text-gray-900">
                                    $<?= number_format($credito['valor_cuota'], 2, ',', '.') ?>
                                </p>
                            </div>
                        </div>

                        <!-- Plan de Pagos -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cuota</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vencimiento</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Capital</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Interés</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Días Restantes</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($pagos as $pago): ?>
                                        <?php
                                        $dias_restantes = 0;
                                        if ($pago['estado'] !== 'Pagado') {
                                            $fecha_vencimiento = new DateTime($pago['fecha_vencimiento_cuota']);
                                            $fecha_actual = new DateTime();
                                            $interval = $fecha_actual->diff($fecha_vencimiento);
                                            $dias_restantes = $fecha_vencimiento > $fecha_actual ? $interval->days : -$interval->days;
                                        }
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-sm">
                                                <?= $pago['numero_cuota'] ?>/<?= $credito['cuotas'] ?>
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                <?= date('d/m/Y', strtotime($pago['fecha_vencimiento_cuota'])) ?>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-right">
                                                $<?= number_format($pago['capital_pagado'], 2, ',', '.') ?>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-right">
                                                $<?= number_format($pago['interes_pagado'], 2, ',', '.') ?>
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
                                                <?php if ($pago['estado'] === 'Pagado'): ?>
                                                    <span class="text-green-600">Pagado</span>
                                                <?php else: ?>
                                                    <span class="<?= $dias_restantes < 0 ? 'text-red-600' : 
                                                                   ($dias_restantes <= 5 ? 'text-yellow-600' : 'text-gray-600') ?>">
                                                        <?= $dias_restantes < 0 ? abs($dias_restantes) . ' días vencido' : 
                                                           ($dias_restantes === 0 ? 'Vence hoy' : $dias_restantes . ' días') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Notas -->
                        <div class="mt-6 text-sm text-gray-600">
                            <p class="mb-1">* Los pagos deben realizarse en las fechas establecidas para evitar cargos por mora.</p>
                            <p class="mb-1">* El interés aplicado es del <?= number_format($credito['interes'], 2) ?>% sobre el monto total.</p>
                            <p>* Para consultas sobre su crédito, comuníquese con nosotros.</p>
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