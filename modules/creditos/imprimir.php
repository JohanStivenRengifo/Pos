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

    // Obtener información de la empresa principal
    $query = "SELECT * FROM empresas 
              WHERE usuario_id = ? AND es_principal = 1 
              LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $empresa = $stmt->fetch();

    if (!$empresa) {
        throw new Exception("No se encontró información de la empresa");
    }

    // Obtener información del crédito
    $query = "SELECT c.*, 
                     v.numero_factura,
                     v.total as venta_total,
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre,
                     cl.identificacion as cliente_identificacion,
                     cl.tipo_identificacion as cliente_tipo_identificacion,
                     cl.email as cliente_email,
                     cl.telefono as cliente_telefono,
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
    $total_pagado = array_sum(array_column($pagos_realizados, 'monto'));

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Crédito #<?= htmlspecialchars($credito['numero_factura'] ?? '') ?> | VendEasy</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .no-print {
                display: none;
            }
            @page {
                margin: 1cm;
                size: letter;
            }
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body class="bg-white p-8 max-w-4xl mx-auto">
    <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
        </div>
    <?php else: ?>
        <!-- Botón de Imprimir -->
        <div class="mb-6 no-print text-right">
            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-print mr-2"></i>Imprimir
            </button>
        </div>

        <!-- Encabezado -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">ESTADO DE CUENTA</h1>
            <p class="text-xl text-gray-600 mt-2">Factura #<?= htmlspecialchars($credito['numero_factura']) ?></p>
        </div>

        <!-- Información de la Empresa y Cliente -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            <div class="border-r pr-8">
                <h2 class="text-base font-bold text-gray-800 mb-3 border-b pb-2">Información de la Empresa</h2>
                <p class="font-semibold text-gray-800 text-sm mb-2"><?= htmlspecialchars($empresa['nombre_empresa']) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium">NIT:</span> <?= htmlspecialchars($empresa['nit']) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium">Régimen:</span> <?= htmlspecialchars($empresa['regimen_fiscal']) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium">Dirección:</span> <?= htmlspecialchars($empresa['direccion']) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium">Teléfono:</span> <?= htmlspecialchars($empresa['telefono']) ?></p>
                <p class="text-xs text-gray-600"><span class="font-medium">Email:</span> <?= htmlspecialchars($empresa['correo_contacto']) ?></p>
            </div>
            <div class="pl-8">
                <h2 class="text-base font-bold text-gray-800 mb-3 border-b pb-2">Información del Cliente</h2>
                <p class="font-semibold text-gray-800 text-sm mb-2"><?= htmlspecialchars($credito['cliente_nombre']) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium"><?= htmlspecialchars($credito['cliente_tipo_identificacion']) ?>:</span> <?= htmlspecialchars($credito['cliente_identificacion']) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium">Email:</span> <?= htmlspecialchars($credito['cliente_email']) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium">Teléfono:</span> <?= htmlspecialchars($credito['cliente_telefono']) ?></p>
                <p class="text-xs text-gray-600"><span class="font-medium">Dirección:</span> <?= htmlspecialchars($credito['cliente_direccion']) ?></p>
            </div>
        </div>

        <!-- Detalles del Crédito -->
        <div class="mb-6 bg-gray-50 p-4 rounded-lg">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-700 mb-1">
                        <span class="font-medium">Fecha de Inicio:</span> 
                        <?= date('d/m/Y', strtotime($credito['fecha_inicio'])) ?>
                    </p>
                    <p class="text-xs text-gray-700">
                        <span class="font-medium">Fecha de Vencimiento:</span> 
                        <?= date('d/m/Y', strtotime($credito['fecha_vencimiento'])) ?>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-700">
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
        </div>

        <!-- Resumen del Crédito -->
        <div class="mb-8">
            <h3 class="text-base font-bold text-gray-800 mb-4">Resumen del Crédito</h3>
            <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                    <p class="mb-2"><span class="font-medium">Monto Total:</span> $<?= number_format($credito['monto_total'], 2, ',', '.') ?></p>
                    <p class="mb-2"><span class="font-medium">Interés:</span> <?= number_format($credito['interes'], 2) ?>%</p>
                    <p class="mb-2"><span class="font-medium">Plazo:</span> <?= $credito['plazo'] ?> días</p>
                </div>
                <div>
                    <p class="mb-2"><span class="font-medium">Total Pagado:</span> $<?= number_format($total_pagado, 2, ',', '.') ?></p>
                    <p class="mb-2"><span class="font-medium">Saldo Pendiente:</span> $<?= number_format($credito['saldo_pendiente'], 2, ',', '.') ?></p>
                    <p class="mb-2"><span class="font-medium">Valor Cuota:</span> $<?= number_format($credito['valor_cuota'], 2, ',', '.') ?></p>
                </div>
            </div>
        </div>

        <!-- Plan de Pagos -->
        <table class="min-w-full divide-y divide-gray-200 mb-8">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuota</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimiento</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Capital</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Interés</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Pago</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($pagos as $pago): ?>
                <tr class="text-sm">
                    <td class="px-4 py-3"><?= $pago['numero_cuota'] ?>/<?= $credito['cuotas'] ?></td>
                    <td class="px-4 py-3"><?= date('d/m/Y', strtotime($pago['fecha_vencimiento_cuota'])) ?></td>
                    <td class="px-4 py-3 text-right">$<?= number_format($pago['capital_pagado'], 2, ',', '.') ?></td>
                    <td class="px-4 py-3 text-right">$<?= number_format($pago['interes_pagado'], 2, ',', '.') ?></td>
                    <td class="px-4 py-3 text-right">$<?= number_format($pago['monto'], 2, ',', '.') ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            <?= $pago['estado'] === 'Pagado' ? 'bg-green-100 text-green-800' : 
                               ($pago['estado'] === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                               'bg-red-100 text-red-800') ?>">
                            <?= $pago['estado'] ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?= $pago['fecha_pago'] ? date('d/m/Y', strtotime($pago['fecha_pago'])) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Firmas -->
        <div class="grid grid-cols-2 gap-8 mt-16">
            <div class="text-center">
                <div class="border-t border-gray-400 pt-2">
                    <p class="font-medium text-sm text-gray-800">Firma del Cliente</p>
                    <p class="text-xs text-gray-600"><?= htmlspecialchars($credito['cliente_nombre']) ?></p>
                    <p class="text-xs text-gray-600"><?= htmlspecialchars($credito['cliente_identificacion']) ?></p>
                </div>
            </div>
            <div class="text-center">
                <div class="border-t border-gray-400 pt-2">
                    <p class="font-medium text-sm text-gray-800">Por la Empresa</p>
                    <p class="text-xs text-gray-600"><?= htmlspecialchars($empresa['nombre_empresa']) ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Imprimir automáticamente al cargar la página
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html> 