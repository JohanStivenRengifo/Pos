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
                     v.total as venta_total,
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre,
                     cl.identificacion as cliente_identificacion,
                     cl.telefono as cliente_telefono,
                     cl.email as cliente_email,
                     cl.direccion as cliente_direccion
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

    // Obtener pagos del crédito
    $query = "SELECT * FROM creditos_pagos 
              WHERE credito_id = ? 
              ORDER BY numero_cuota ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $pagos = $stmt->fetchAll();

    // Calcular estadísticas
    $pagos_realizados = array_filter($pagos, fn($p) => $p['estado'] === 'Pagado');
    $pagos_atrasados = array_filter($pagos, fn($p) => $p['estado'] === 'Atrasado');
    $total_pagado = array_sum(array_column($pagos_realizados, 'monto'));
    $total_atrasado = array_sum(array_column($pagos_atrasados, 'monto'));

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Crédito | Numercia</title>
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
                        <!-- Encabezado -->
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-800">
                                Crédito - Factura #<?= htmlspecialchars($credito['numero_factura']) ?>
                            </h2>
                            <div class="flex space-x-2">
                                <a href="index.php" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-arrow-left mr-2"></i>Volver
                                </a>
                                <?php if ($credito['estado'] !== 'Pagado'): ?>
                                    <a href="registrar_pago.php?id=<?= $credito['id'] ?>" 
                                       class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                                        <i class="fas fa-dollar-sign mr-2"></i>Registrar Pago
                                    </a>
                                <?php endif; ?>
                                <a href="imprimir.php?id=<?= $credito['id'] ?>" 
                                   target="_blank"
                                   class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                                    <i class="fas fa-print mr-2"></i>Imprimir
                                </a>
                            </div>
                        </div>

                        <!-- Información del Cliente y Crédito -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
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
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Email:</span> 
                                    <?= htmlspecialchars($credito['cliente_email']) ?>
                                </p>
                                <p class="text-sm">
                                    <span class="font-medium">Dirección:</span> 
                                    <?= htmlspecialchars($credito['cliente_direccion']) ?>
                                </p>
                            </div>

                            <div class="bg-gray-50 p-4 rounded-lg">
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
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Cuotas:</span> 
                                    <?= $credito['cuotas'] ?> x $<?= number_format($credito['valor_cuota'], 2, ',', '.') ?>
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
                                <h3 class="text-sm font-medium text-green-800">Total Pagado</h3>
                                <p class="text-2xl font-bold text-green-900">
                                    $<?= number_format($total_pagado, 2, ',', '.') ?>
                                </p>
                            </div>
                            <div class="bg-red-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-red-800">Total Atrasado</h3>
                                <p class="text-2xl font-bold text-red-900">
                                    $<?= number_format($total_atrasado, 2, ',', '.') ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-800">Saldo Pendiente</h3>
                                <p class="text-2xl font-bold text-gray-900">
                                    $<?= number_format($credito['saldo_pendiente'], 2, ',', '.') ?>
                                </p>
                            </div>
                        </div>

                        <!-- Plan de Pagos -->
                        <div class="overflow-x-auto">
                            <h3 class="text-lg font-medium text-gray-700 mb-4">Plan de Pagos</h3>
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cuota</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vencimiento</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Monto</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Capital</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Interés</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha Pago</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Método</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($pagos as $pago): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2">
                                                <?= $pago['numero_cuota'] ?>/<?= $credito['cuotas'] ?>
                                            </td>
                                            <td class="px-3 py-2">
                                                <?= date('d/m/Y', strtotime($pago['fecha_vencimiento_cuota'])) ?>
                                            </td>
                                            <td class="px-3 py-2">
                                                $<?= number_format($pago['monto'], 2, ',', '.') ?>
                                            </td>
                                            <td class="px-3 py-2">
                                                $<?= number_format($pago['capital_pagado'], 2, ',', '.') ?>
                                            </td>
                                            <td class="px-3 py-2">
                                                $<?= number_format($pago['interes_pagado'], 2, ',', '.') ?>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    <?= $pago['estado'] === 'Pagado' ? 'bg-green-100 text-green-800' : 
                                                       ($pago['estado'] === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                                                       'bg-red-100 text-red-800') ?>">
                                                    <?= $pago['estado'] ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <?= $pago['fecha_pago'] ? date('d/m/Y', strtotime($pago['fecha_pago'])) : '-' ?>
                                            </td>
                                            <td class="px-3 py-2">
                                                <?= $pago['metodo_pago'] === 'Pendiente' ? '-' : $pago['metodo_pago'] ?>
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
</body>
</html> 