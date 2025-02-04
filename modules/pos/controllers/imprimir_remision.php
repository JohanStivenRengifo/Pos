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
            font-size: 12px;
        }
        .header-info {
            font-size: 11px;
            line-height: 1.3;
        }
        .table-items td, .table-items th {
            padding: 4px 6px;
            border: 1px solid #ddd;
            font-size: 11px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="page-container mx-auto bg-white shadow-lg my-4 p-4">
        <!-- Encabezado -->
        <div class="text-center mb-4">
            <h1 class="text-xl font-bold"><?= safe_text($venta['nombre_empresa']) ?></h1>
            <div class="header-info text-gray-600">
                <p>NIT <?= safe_text($venta['nit']) ?></p>
                <p><?= safe_text($venta['empresa_direccion']) ?></p>
                <p><?= safe_text($venta['empresa_telefono']) ?></p>
                <p><?= safe_text($venta['empresa_email']) ?></p>
            </div>
            
            <h2 class="text-lg font-bold mt-4 mb-2">Remisión</h2>
            <p class="font-bold">No. <?= safe_text($venta['numero_factura']) ?></p>
        </div>

        <!-- Información del cliente -->
        <div class="mb-4 text-sm">
            <table class="w-full text-sm">
                <tr>
                    <td class="py-1"><strong>SEÑOR(ES):</strong> <?= safe_text(trim($venta['primer_nombre'] . ' ' . $venta['segundo_nombre'] . ' ' . $venta['apellidos'])) ?></td>
                    <td class="py-1"><strong>CC:</strong> <?= safe_text($venta['identificacion']) ?></td>
                </tr>
                <tr>
                    <td class="py-1"><strong>DIRECCIÓN:</strong> <?= safe_text($venta['direccion']) ?></td>
                    <td class="py-1"><strong>TELÉFONO:</strong> <?= safe_text($venta['telefono']) ?></td>
                </tr>
                <tr>
                    <td class="py-1"><strong>FECHA DE EXPEDICIÓN:</strong> <?= date('d/m/Y', strtotime($venta['fecha'])) ?></td>
                    <td class="py-1"><strong>FECHA DE VENCIMIENTO:</strong> <?= date('d/m/Y', strtotime($venta['fecha'])) ?></td>
                </tr>
            </table>
        </div>

        <!-- Tabla de productos -->
        <table class="w-full table-items mb-4">
            <thead>
                <tr class="bg-gray-100">
                    <th class="text-left">Ítem</th>
                    <th class="text-right">Precio</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-right">Descuento</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): 
                    $descuento_porcentaje = ($detalle['descuento'] / $detalle['precio_unitario']) * 100;
                    $precio_con_descuento = $detalle['precio_unitario'] - $detalle['descuento'];
                    $total_item = $precio_con_descuento * $detalle['cantidad'];
                ?>
                    <tr>
                        <td>
                            <?= safe_text($detalle['nombre']) ?>
                            <?php if (!empty($detalle['codigo_barras'])): ?>
                                <br><span class="text-xs text-gray-500">(<?= safe_text($detalle['codigo_barras']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">$<?= number_format($detalle['precio_unitario'], 0, ',', '.') ?></td>
                        <td class="text-center"><?= $detalle['cantidad'] ?></td>
                        <td class="text-right"><?= number_format($descuento_porcentaje, 2) ?>%</td>
                        <td class="text-right">$<?= number_format($total_item, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totales -->
        <div class="w-1/2 ml-auto">
            <table class="w-full text-sm">
                <tr>
                    <td class="py-1 text-right"><strong>Subtotal:</strong></td>
                    <td class="py-1 text-right">$<?= number_format($venta['total'] + $venta['descuento'], 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td class="py-1 text-right"><strong>Descuento:</strong></td>
                    <td class="py-1 text-right">$<?= number_format($venta['descuento'], 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td class="py-1 text-right"><strong>Subtotal:</strong></td>
                    <td class="py-1 text-right">$<?= number_format($venta['total'], 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td class="py-1 text-right"><strong>IVA (19.00%):</strong></td>
                    <td class="py-1 text-right">$<?= number_format($venta['total'] * 0.19, 0, ',', '.') ?></td>
                </tr>
                <tr class="font-bold">
                    <td class="py-1 text-right">Total:</td>
                    <td class="py-1 text-right">$<?= number_format($venta['total'] * 1.19, 0, ',', '.') ?></td>
                </tr>
            </table>
        </div>

        <!-- Firmas -->
        <div class="grid grid-cols-2 gap-4 mt-8 pt-8 text-sm">
            <div class="text-center">
                <div class="border-t pt-1">
                    <p>ELABORADO POR</p>
                </div>
            </div>
            <div class="text-center">
                <div class="border-t pt-1">
                    <p>ACEPTADA, FIRMA Y/O SELLO Y FECHA</p>
                </div>
            </div>
        </div>

        <!-- Pie de página -->
        <div class="text-center mt-8 text-xs text-gray-500">
            <p>Generado en www.johanrengifo.cloud</p>
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