<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/functions.php';

// Obtener información de la suscripción actual
$stmt = $pdo->prepare("
    SELECT p.*, e.plan_suscripcion 
    FROM pagos p
    JOIN empresas e ON e.id = p.empresa_id
    WHERE p.empresa_id = ? 
    AND p.estado = 'completado'
    ORDER BY p.fecha_pago DESC
    LIMIT 1
");
$stmt->execute([$_SESSION['empresa_id']]);
$suscripcion = $stmt->fetch();

// Obtener historial de pagos
$stmt = $pdo->prepare("
    SELECT * FROM pagos 
    WHERE empresa_id = ? 
    ORDER BY fecha_pago DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['empresa_id']]);
$historial_pagos = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Suscripción | VendEasy</title>
    <link rel="icon" type="image/png" href="../../../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include '../../../includes/header.php'; ?>

    <div class="flex">
        <?php include '../../../includes/sidebar.php'; ?>

        <main class="flex-1 p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Encabezado -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900">Gestión de Suscripción</h1>
                    <p class="mt-2 text-sm text-gray-600">Administra tu plan y revisa el historial de pagos</p>
                </div>

                <!-- Estado de Suscripción Actual -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Plan Actual</h2>
                            <div class="mt-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                    <?= $suscripcion['estado'] === 'completado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                    <?= ucfirst($suscripcion['plan']) ?>
                                </span>
                            </div>
                            <div class="mt-4 space-y-2">
                                <p class="text-sm text-gray-600">
                                    <span class="font-medium">Fecha de inicio:</span> 
                                    <?= date('d/m/Y', strtotime($suscripcion['fecha_inicio_plan'])) ?>
                                </p>
                                <p class="text-sm text-gray-600">
                                    <span class="font-medium">Fecha de renovación:</span> 
                                    <?= date('d/m/Y', strtotime($suscripcion['fecha_fin_plan'])) ?>
                                </p>
                                <p class="text-sm text-gray-600">
                                    <span class="font-medium">Estado:</span>
                                    <span class="<?= $suscripcion['estado'] === 'completado' ? 'text-green-600' : 'text-yellow-600' ?>">
                                        <?= ucfirst($suscripcion['estado']) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div>
                            <a href="../../empresa/planes.php" 
                               class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                                <i class="fas fa-sync-alt mr-2"></i>
                                Cambiar Plan
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Historial de Pagos -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Historial de Pagos</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Plan
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Monto
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Referencia
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($historial_pagos as $pago): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= ucfirst($pago['plan']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        $<?= number_format($pago['monto'], 0, ',', '.') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?= $pago['estado'] === 'completado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= ucfirst($pago['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= $pago['bold_order_id'] ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 