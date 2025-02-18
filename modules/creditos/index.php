<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$creditos = [];

try {
    // Obtener listado de créditos con información detallada
    $query = "SELECT c.*, 
                     v.numero_factura,
                     v.total as venta_total,
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre,
                     cl.identificacion as cliente_identificacion,
                     cl.telefono as cliente_telefono,
                     (SELECT COUNT(*) FROM creditos_pagos cp 
                      WHERE cp.credito_id = c.id AND cp.estado = 'Atrasado') as cuotas_atrasadas,
                     (SELECT COUNT(*) FROM creditos_pagos cp 
                      WHERE cp.credito_id = c.id AND cp.estado = 'Pagado') as cuotas_pagadas
              FROM creditos c
              LEFT JOIN ventas v ON c.venta_id = v.id
              LEFT JOIN clientes cl ON v.cliente_id = cl.id
              WHERE cl.user_id = ?
              ORDER BY 
                CASE c.estado
                    WHEN 'Atrasado' THEN 1
                    WHEN 'Vencido' THEN 2
                    WHEN 'Pendiente' THEN 3
                    WHEN 'Al día' THEN 4
                    WHEN 'Pagado' THEN 5
                END,
                c.fecha_vencimiento ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $creditos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular estadísticas
    $estadisticas = [
        'total_creditos' => array_sum(array_column($creditos, 'monto_total')),
        'total_pagado' => array_sum(array_column($creditos, 'monto_pagado')),
        'total_pendiente' => array_sum(array_column($creditos, 'saldo_pendiente')),
        'creditos_atrasados' => count(array_filter($creditos, fn($c) => $c['estado'] === 'Atrasado')),
        'creditos_vencidos' => count(array_filter($creditos, fn($c) => $c['estado'] === 'Vencido')),
        'creditos_al_dia' => count(array_filter($creditos, fn($c) => $c['estado'] === 'Al día'))
    ];

} catch (PDOException $e) {
    $error_message = "Error en la base de datos: " . $e->getMessage();
    error_log("Error en creditos/index.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Créditos | Numercia</title>
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
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Gestión de Créditos</h2>
                        <div class="flex space-x-2">
                            <a href="crear.php" 
                               class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>
                                Nuevo Crédito
                            </a>
                            <a href="reportes.php" 
                               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                                <i class="fas fa-chart-line mr-2"></i>
                                Reportes
                            </a>
                        </div>
                    </div>

                    <!-- Resumen de Créditos -->
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-blue-800">Total Créditos</h3>
                            <p class="text-2xl font-bold text-blue-900">
                                $<?= number_format($estadisticas['total_creditos'], 2, ',', '.') ?>
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
                            <h3 class="text-sm font-medium text-yellow-800">Créditos Atrasados</h3>
                            <p class="text-2xl font-bold text-yellow-900">
                                <?= $estadisticas['creditos_atrasados'] ?>
                            </p>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-red-800">Créditos Vencidos</h3>
                            <p class="text-2xl font-bold text-red-900">
                                <?= $estadisticas['creditos_vencidos'] ?>
                            </p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-green-800">Al Día</h3>
                            <p class="text-2xl font-bold text-green-900">
                                <?= $estadisticas['creditos_al_dia'] ?>
                            </p>
                        </div>
                    </div>

                    <!-- Tabla de Créditos -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Factura</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Monto</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cuotas</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Interés</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Próximo Pago</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($creditos)): ?>
                                    <tr>
                                        <td colspan="8" class="px-3 py-4 text-center text-gray-500">
                                            No hay créditos registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <?php foreach ($creditos as $credito): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2">
                                            <div>
                                                <div class="font-medium text-gray-900">
                                                    <?= htmlspecialchars($credito['cliente_nombre']) ?>
                                                </div>
                                                <div class="text-gray-500">
                                                    <?= htmlspecialchars($credito['cliente_identificacion']) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-gray-500">
                                            <?= htmlspecialchars($credito['numero_factura']) ?>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="text-gray-900">
                                                $<?= number_format($credito['monto_total'], 2, ',', '.') ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                Cuota: $<?= number_format($credito['valor_cuota'], 2, ',', '.') ?>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="text-gray-900">
                                                <?= $credito['cuotas_pagadas'] ?>/<?= $credito['cuotas'] ?>
                                            </div>
                                            <?php if ($credito['cuotas_atrasadas'] > 0): ?>
                                                <div class="text-xs text-red-600">
                                                    <?= $credito['cuotas_atrasadas'] ?> atrasadas
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 text-gray-900">
                                            <?= number_format($credito['interes'], 2) ?>%
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                <?= $credito['estado'] === 'Al día' ? 'bg-green-100 text-green-800' : 
                                                   ($credito['estado'] === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                                                   ($credito['estado'] === 'Atrasado' ? 'bg-orange-100 text-orange-800' : 
                                                   ($credito['estado'] === 'Pagado' ? 'bg-blue-100 text-blue-800' : 
                                                   'bg-red-100 text-red-800'))) ?>">
                                                <?= $credito['estado'] ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <?php
                                            $proxima_cuota = date('Y-m-d', strtotime($credito['fecha_inicio'] . ' + ' . $credito['plazo'] . ' days'));
                                            $dias_restantes = (strtotime($proxima_cuota) - time()) / (60 * 60 * 24);
                                            $clase_texto = $dias_restantes <= 5 ? 'text-red-600' : 'text-gray-600';
                                            ?>
                                            <span class="<?= $clase_texto ?>">
                                                <?= date('d/m/Y', strtotime($proxima_cuota)) ?>
                                                <?php if ($dias_restantes > 0): ?>
                                                    <span class="text-xs">(<?= floor($dias_restantes) ?> días)</span>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-sm">
                                            <div class="flex items-center space-x-2">
                                                <a href="ver.php?id=<?= $credito['id'] ?>" 
                                                   class="text-blue-600 hover:text-blue-900"
                                                   title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($credito['estado'] !== 'Pagado'): ?>
                                                    <a href="registrar_pago.php?id=<?= $credito['id'] ?>" 
                                                       class="text-green-600 hover:text-green-900"
                                                       title="Registrar pago">
                                                        <i class="fas fa-dollar-sign"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="historial.php?id=<?= $credito['id'] ?>" 
                                                   class="text-gray-600 hover:text-gray-900"
                                                   title="Ver historial">
                                                    <i class="fas fa-history"></i>
                                                </a>
                                                <a href="plan_pagos.php?id=<?= $credito['id'] ?>" 
                                                   class="text-indigo-600 hover:text-indigo-900"
                                                   title="Plan de pagos">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
