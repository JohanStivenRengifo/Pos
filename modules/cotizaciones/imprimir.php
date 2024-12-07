<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$cotizacion = null;
$detalles = [];

try {
    if (!isset($_GET['id'])) {
        throw new Exception("ID de cotización no especificado");
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

    // Obtener la cotización
    $query = "SELECT c.*, 
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre,
                     cl.identificacion as cliente_identificacion,
                     cl.tipo_identificacion as cliente_tipo_identificacion,
                     cl.email as cliente_email,
                     cl.telefono as cliente_telefono,
                     cl.direccion as cliente_direccion
              FROM cotizaciones c
              LEFT JOIN clientes cl ON c.cliente_id = cl.id
              WHERE c.id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $cotizacion = $stmt->fetch();

    if (!$cotizacion) {
        throw new Exception("Cotización no encontrada");
    }

    // Obtener los detalles de la cotización
    $query = "SELECT cd.*, i.codigo_barras, i.nombre as producto_nombre
              FROM cotizacion_detalles cd
              LEFT JOIN inventario i ON cd.producto_id = i.id
              WHERE cd.cotizacion_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $detalles = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Cotización #<?= htmlspecialchars($cotizacion['numero'] ?? '') ?> | VendEasy</title>
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
            <h1 class="text-3xl font-bold text-gray-800">COTIZACIÓN</h1>
            <p class="text-xl text-gray-600 mt-2">#<?= htmlspecialchars($cotizacion['numero']) ?></p>
        </div>

        <!-- Información de la Empresa y Cliente -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            <div class="border-r pr-8">
                <h2 class="text-lg font-bold text-gray-800 mb-3 border-b pb-2">Información de la Empresa</h2>
                <p class="font-semibold text-gray-800 text-lg mb-2"><?= htmlspecialchars($empresa['nombre_empresa']) ?></p>
                <p class="text-gray-600 mb-1"><span class="font-medium">NIT:</span> <?= htmlspecialchars($empresa['nit']) ?></p>
                <p class="text-gray-600 mb-1"><span class="font-medium">Régimen:</span> <?= htmlspecialchars($empresa['regimen_fiscal']) ?></p>
                <p class="text-gray-600 mb-1"><span class="font-medium">Dirección:</span> <?= htmlspecialchars($empresa['direccion']) ?></p>
                <p class="text-gray-600 mb-1"><span class="font-medium">Teléfono:</span> <?= htmlspecialchars($empresa['telefono']) ?></p>
                <p class="text-gray-600"><span class="font-medium">Email:</span> <?= htmlspecialchars($empresa['correo_contacto']) ?></p>
            </div>
            <div class="pl-8">
                <h2 class="text-lg font-bold text-gray-800 mb-3 border-b pb-2">Información del Cliente</h2>
                <p class="font-semibold text-gray-800 text-lg mb-2"><?= htmlspecialchars($cotizacion['cliente_nombre']) ?></p>
                <p class="text-gray-600 mb-1"><span class="font-medium"><?= htmlspecialchars($cotizacion['cliente_tipo_identificacion']) ?>:</span> <?= htmlspecialchars($cotizacion['cliente_identificacion']) ?></p>
                <p class="text-gray-600 mb-1"><span class="font-medium">Email:</span> <?= htmlspecialchars($cotizacion['cliente_email']) ?></p>
                <p class="text-gray-600 mb-1"><span class="font-medium">Teléfono:</span> <?= htmlspecialchars($cotizacion['cliente_telefono']) ?></p>
                <p class="text-gray-600"><span class="font-medium">Dirección:</span> <?= htmlspecialchars($cotizacion['cliente_direccion']) ?></p>
            </div>
        </div>

        <!-- Fecha y Estado -->
        <div class="mb-6 bg-gray-50 p-4 rounded-lg">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-700 mb-1">
                        <span class="font-medium">Fecha de Emisión:</span> 
                        <?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?>
                    </p>
                    <p class="text-gray-700">
                        <span class="font-medium">Fecha de Vencimiento:</span> 
                        <?= date('d/m/Y', strtotime($cotizacion['fecha'] . ' + 30 days')) ?>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-gray-700">
                        <span class="font-medium">Estado:</span> 
                        <span class="inline-block px-3 py-1 rounded-full text-sm font-medium
                            <?= $cotizacion['estado'] == 'Aprobada' ? 'bg-green-100 text-green-800' : 
                               ($cotizacion['estado'] == 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                               ($cotizacion['estado'] == 'Facturado' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')) ?>">
                            <?= $cotizacion['estado'] ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Tabla de Productos -->
        <table class="min-w-full divide-y divide-gray-200 mb-8">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Precio Unit.</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($detalles as $detalle): ?>
                <tr class="text-sm">
                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($detalle['codigo_barras']) ?></td>
                    <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars($detalle['descripcion']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($detalle['cantidad']) ?></td>
                    <td class="px-4 py-3 text-right text-gray-600">$<?= number_format($detalle['precio_unitario'], 2, ',', '.') ?></td>
                    <td class="px-4 py-3 text-right text-gray-800">$<?= number_format($detalle['subtotal'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="4" class="px-4 py-3 text-right font-medium text-gray-700">Total:</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-800">$<?= number_format($cotizacion['total'], 2, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Términos y Condiciones -->
        <div class="mb-8 text-sm text-gray-600">
            <h3 class="font-bold text-gray-800 mb-2">Términos y Condiciones</h3>
            <ul class="list-disc list-inside space-y-1">
                <li>Esta cotización es válida hasta el <?= date('d/m/Y', strtotime($cotizacion['fecha'] . ' + 30 days')) ?>.</li>
                <li>Los precios pueden variar sin previo aviso después de la fecha de vencimiento.</li>
                <li>Los tiempos de entrega son estimados y comienzan a partir de la aprobación de la cotización.</li>
                <li>El pago debe realizarse según los términos acordados.</li>
                <li>Los precios incluyen IVA cuando aplica.</li>
            </ul>
        </div>

        <!-- Firmas -->
        <div class="grid grid-cols-2 gap-8 mt-16">
            <div class="text-center">
                <div class="border-t border-gray-400 pt-2">
                    <p class="font-medium text-gray-800">Firma del Vendedor</p>
                </div>
            </div>
            <div class="text-center">
                <div class="border-t border-gray-400 pt-2">
                    <p class="font-medium text-gray-800">Firma del Cliente</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Imprimir automáticamente al cargar la página
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500); // Pequeño retraso para asegurar que todo se cargue correctamente
        };
    </script>
</body>
</html> 