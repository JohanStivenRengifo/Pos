<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$estadisticas = [];
$creditos_recientes = [];
$pagos_recientes = [];

try {
    // Obtener estadísticas generales
    $query = "SELECT 
                COUNT(*) as total_creditos,
                SUM(monto_total) as total_prestado,
                SUM(monto_pagado) as total_pagado,
                SUM(saldo_pendiente) as total_pendiente,
                SUM(CASE WHEN estado = 'Vencido' THEN 1 ELSE 0 END) as creditos_vencidos,
                SUM(CASE WHEN estado = 'Al día' THEN 1 ELSE 0 END) as creditos_al_dia,
                SUM(CASE WHEN estado = 'Atrasado' THEN 1 ELSE 0 END) as creditos_atrasados,
                AVG(interes) as promedio_interes
              FROM creditos c
              LEFT JOIN ventas v ON c.venta_id = v.id
              LEFT JOIN clientes cl ON v.cliente_id = cl.id
              WHERE cl.user_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener créditos recientes
    $query = "SELECT c.*, 
                     v.numero_factura,
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre
              FROM creditos c
              LEFT JOIN ventas v ON c.venta_id = v.id
              LEFT JOIN clientes cl ON v.cliente_id = cl.id
              WHERE cl.user_id = ?
              ORDER BY c.created_at DESC
              LIMIT 5";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $creditos_recientes = $stmt->fetchAll();

    // Obtener pagos recientes
    $query = "SELECT cp.*, c.monto_total, v.numero_factura,
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre
              FROM creditos_pagos cp
              INNER JOIN creditos c ON cp.credito_id = c.id
              LEFT JOIN ventas v ON c.venta_id = v.id
              LEFT JOIN clientes cl ON v.cliente_id = cl.id
              WHERE cl.user_id = ? AND cp.estado = 'Pagado'
              ORDER BY cp.fecha_pago DESC
              LIMIT 5";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $pagos_recientes = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Créditos | VendEasy</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <!-- Estadísticas Generales -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">Estadísticas Generales</h2>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-blue-800">Total Créditos</h3>
                                <p class="text-2xl font-bold text-blue-900">
                                    <?= number_format($estadisticas['total_creditos']) ?>
                                </p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-green-800">Total Pagado</h3>
                                <p class="text-2xl font-bold text-green-900">
                                    $<?= number_format($estadisticas['total_pagado'], 2, ',', '.') ?>
                                </p>
                            </div>
                            <div class="bg-red-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-red-800">Total Pendiente</h3>
                                <p class="text-2xl font-bold text-red-900">
                                    $<?= number_format($estadisticas['total_pendiente'], 2, ',', '.') ?>
                                </p>
                            </div>
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-yellow-800">Interés Promedio</h3>
                                <p class="text-2xl font-bold text-yellow-900">
                                    <?= number_format($estadisticas['promedio_interes'], 2) ?>%
                                </p>
                            </div>
                        </div>

                        <!-- Gráfico de Estado de Créditos -->
                        <div class="mt-6">
                            <canvas id="creditosChart" height="100"></canvas>
                        </div>
                    </div>

                    <!-- Créditos y Pagos Recientes -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Créditos Recientes -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Créditos Recientes</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Factura</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($creditos_recientes as $credito): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-sm">
                                                    #<?= htmlspecialchars($credito['numero_factura']) ?>
                                                </td>
                                                <td class="px-3 py-2 text-sm">
                                                    <?= htmlspecialchars($credito['cliente_nombre']) ?>
                                                </td>
                                                <td class="px-3 py-2 text-sm text-right">
                                                    $<?= number_format($credito['monto_total'], 2, ',', '.') ?>
                                                </td>
                                                <td class="px-3 py-2 text-sm text-center">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                        <?= $credito['estado'] === 'Al día' ? 'bg-green-100 text-green-800' : 
                                                           ($credito['estado'] === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                                                           'bg-red-100 text-red-800') ?>">
                                                        <?= $credito['estado'] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pagos Recientes -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Pagos Recientes</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Factura</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($pagos_recientes as $pago): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-sm">
                                                    #<?= htmlspecialchars($pago['numero_factura']) ?>
                                                </td>
                                                <td class="px-3 py-2 text-sm">
                                                    <?= htmlspecialchars($pago['cliente_nombre']) ?>
                                                </td>
                                                <td class="px-3 py-2 text-sm text-right">
                                                    $<?= number_format($pago['monto'], 2, ',', '.') ?>
                                                </td>
                                                <td class="px-3 py-2 text-sm text-center">
                                                    <?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Gráfico de Estado de Créditos
        const ctx = document.getElementById('creditosChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Al día', 'Atrasados', 'Vencidos'],
                datasets: [{
                    data: [
                        <?= $estadisticas['creditos_al_dia'] ?>,
                        <?= $estadisticas['creditos_atrasados'] ?>,
                        <?= $estadisticas['creditos_vencidos'] ?>
                    ],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.2)',
                        'rgba(234, 179, 8, 0.2)',
                        'rgba(239, 68, 68, 0.2)'
                    ],
                    borderColor: [
                        'rgb(34, 197, 94)',
                        'rgb(234, 179, 8)',
                        'rgb(239, 68, 68)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: true,
                        text: 'Estado de Créditos'
                    }
                }
            }
        });
    </script>
</body>
</html> 