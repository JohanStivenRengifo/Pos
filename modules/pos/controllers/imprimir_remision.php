<?php
session_start();
require_once '../../../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

try {
    // Obtener datos de la venta
    $stmt = $pdo->prepare("
        SELECT v.*, 
               c.primer_nombre, c.segundo_nombre, c.apellidos, 
               c.identificacion, c.direccion, c.telefono,
               e.nombre_empresa, e.nit, e.direccion as empresa_direccion,
               e.telefono as empresa_telefono, e.logo, e.correo_contacto as empresa_email,
               e.regimen_fiscal
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN empresas e ON e.estado = 1 AND e.es_principal = 1
        WHERE v.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener detalles
    $stmt = $pdo->prepare("
        SELECT vd.*, i.nombre, i.codigo_barras, i.ubicacion
        FROM venta_detalles vd
        LEFT JOIN inventario i ON vd.producto_id = i.id
        WHERE vd.venta_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

function safe_text($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remisión - Venta #<?= safe_text($venta['numero_factura']) ?> | VendEasy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page { 
            size: 5.5in 8.5in; /* Tamaño media carta */
            margin: 0.5cm;
        }
        @media print {
            .no-print { display: none !important; }
            body { 
                print-color-adjust: exact; 
                -webkit-print-color-adjust: exact;
                padding: 0;
                margin: 0;
            }
            .page-container {
                padding: 10px !important;
                margin: 0 !important;
                box-shadow: none !important;
            }
        }
        .page-container { 
            width: 5.5in;
            min-height: 8.5in;
        }
        .table-row-alt:nth-child(even) {
            background-color: #f9fafb;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="page-container mx-auto bg-white shadow-lg my-4 p-4">
        <!-- Encabezado con logo -->
        <div class="text-center border-b pb-3 mb-4">
            <?php if (!empty($venta['logo'])): ?>
                <img src="<?= safe_text($venta['logo']) ?>" alt="Logo" class="mx-auto mb-2 h-16">
            <?php endif; ?>
            <h1 class="text-xl font-bold text-gray-800"><?= safe_text($venta['nombre_empresa']) ?></h1>
            <p class="text-sm text-gray-600">NIT: <?= safe_text($venta['nit']) ?></p>
            <p class="text-sm text-gray-600"><?= safe_text($venta['empresa_direccion']) ?></p>
            <p class="text-sm text-gray-600">Tel: <?= safe_text($venta['empresa_telefono']) ?></p>
            <p class="text-sm text-gray-600">Email: <?= safe_text($venta['empresa_email']) ?></p>
            <p class="text-sm text-gray-600">Régimen: <?= safe_text($venta['regimen_fiscal'] ?? 'No responsable de IVA') ?></p>
            
            <h2 class="text-lg font-bold mt-3 text-gray-800">REMISIÓN DE VENTA</h2>
            <p class="text-md font-semibold text-gray-700">No. <?= safe_text($venta['numero_factura']) ?></p>
            <p class="text-sm text-gray-600">Fecha: <?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></p>
        </div>

        <!-- Info cliente -->
        <div class="mb-4 text-sm">
            <div class="border rounded-lg p-3">
                <h3 class="font-bold mb-2 text-gray-700 bg-gray-100 p-2">INFORMACIÓN DEL CLIENTE</h3>
                <div class="grid grid-cols-2 gap-2">
                    <p><span class="font-semibold">Nombre:</span> <?= safe_text(trim($venta['primer_nombre'] . ' ' . $venta['segundo_nombre'] . ' ' . $venta['apellidos'])) ?></p>
                    <p><span class="font-semibold">ID:</span> <?= safe_text($venta['identificacion']) ?></p>
                    <p><span class="font-semibold">Teléfono:</span> <?= safe_text($venta['telefono']) ?></p>
                    <p class="col-span-2"><span class="font-semibold">Dirección:</span> <?= safe_text($venta['direccion']) ?></p>
                </div>
            </div>
        </div>

        <!-- Tabla de productos -->
        <div class="mb-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-2 px-2 text-left border">Producto</th>
                        <th class="py-2 px-2 text-center border w-16">Cant.</th>
                        <th class="py-2 px-2 text-right border w-24">Precio</th>
                        <th class="py-2 px-2 text-right border w-24">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr class="border">
                            <td class="py-2 px-2 border">
                                <div class="font-semibold"><?= safe_text($detalle['nombre']) ?></div>
                                <div class="text-xs text-gray-500"><?= safe_text($detalle['codigo_barras']) ?></div>
                            </td>
                            <td class="py-2 px-2 text-center border"><?= $detalle['cantidad'] ?></td>
                            <td class="py-2 px-2 text-right border">$<?= number_format($detalle['precio_unitario'], 0, ',', '.') ?></td>
                            <td class="py-2 px-2 text-right border">$<?= number_format($detalle['cantidad'] * $detalle['precio_unitario'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100">
                        <td colspan="3" class="py-2 px-2 text-right border font-bold">SUBTOTAL:</td>
                        <td class="py-2 px-2 text-right border">$<?= number_format($venta['total'] + $venta['descuento'], 0, ',', '.') ?></td>
                    </tr>
                    <tr class="bg-gray-100">
                        <td colspan="3" class="py-2 px-2 text-right border font-bold">DESCUENTO:</td>
                        <td class="py-2 px-2 text-right border">$<?= number_format($venta['descuento'], 0, ',', '.') ?></td>
                    </tr>
                    <tr class="bg-gray-100">
                        <td colspan="3" class="py-2 px-2 text-right border font-bold">TOTAL:</td>
                        <td class="py-2 px-2 text-right border font-bold">$<?= number_format($venta['total'], 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Firmas -->
        <div class="grid grid-cols-2 gap-4 mt-8 text-sm">
            <div class="text-center">
                <div class="border-t pt-1">
                    <p class="font-semibold">Firma del vendedor</p>
                </div>
            </div>
            <div class="text-center">
                <div class="border-t pt-1">
                    <p class="font-semibold">Firma del cliente</p>
                    <p class="text-xs text-gray-500"><?= safe_text(trim($venta['primer_nombre'] . ' ' . $venta['segundo_nombre'] . ' ' . $venta['apellidos'])) ?></p>
                    <p class="text-xs text-gray-500"><?= safe_text($venta['identificacion']) ?></p>
                </div>
            </div>
        </div>

        <!-- Pie de página -->
        <div class="text-center mt-8 text-sm text-gray-600">
            <p>GRACIAS POR SU COMPRA</p>
        </div>
    </div>

    <!-- Botón volver -->
    <div class="fixed bottom-4 right-4 no-print">
        <button onclick="window.close()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-times mr-2"></i>Cerrar
        </button>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html> 