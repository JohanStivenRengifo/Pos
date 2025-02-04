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
               e.telefono as empresa_telefono, e.logo, e.ciudad
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN empresas e ON e.id = v.empresa_id
        WHERE v.id = ? AND v.user_id = ?
    ");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
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
<<<<<<< HEAD
    <title>Remisión - <?= safe_text($venta['numero_factura']) ?></title>
=======
    <title>Remisión - Venta #<?= safe_text($venta['numero_factura']) ?> | VendEasy</title>
>>>>>>> 23cb190e08b26fb210dc84b8c9f768514ce9ded0
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
            <p class="text-sm text-gray-600"><?= safe_text($venta['empresa_telefono']) ?></p>
            <h2 class="text-lg font-bold mt-3 text-gray-800">ORDEN DE DESPACHO</h2>
            <p class="text-md font-semibold text-gray-700">Remisión N° <?= safe_text($venta['numero_factura']) ?></p>
        </div>

        <!-- Info cliente y despacho -->
        <div class="grid grid-cols-1 gap-4 mb-4 text-sm">
            <div class="border rounded-lg p-3">
                <h3 class="font-bold mb-2 text-gray-700">Información de Despacho:</h3>
                <div class="grid grid-cols-2 gap-2">
                    <p><span class="font-semibold">Fecha:</span> <?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></p>
                    <p><span class="font-semibold">Cliente:</span> <?= safe_text(trim($venta['primer_nombre'] . ' ' . $venta['segundo_nombre'] . ' ' . $venta['apellidos'])) ?></p>
                    <p><span class="font-semibold">Identificación:</span> <?= safe_text($venta['identificacion']) ?></p>
                    <p><span class="font-semibold">Teléfono:</span> <?= safe_text($venta['telefono']) ?></p>
                    <p class="col-span-2"><span class="font-semibold">Dirección:</span> <?= safe_text($venta['direccion']) ?></p>
                </div>
            </div>
        </div>

        <!-- Tabla de productos -->
        <table class="w-full mb-4 text-sm">
            <thead class="bg-gray-200">
                <tr>
                    <th class="py-2 px-2 text-left">Ubicación</th>
                    <th class="py-2 px-2 text-left">Producto</th>
                    <th class="py-2 px-2 text-center w-16">Cant.</th>
                    <th class="py-2 px-2 text-center w-20">Desp.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                    <tr class="table-row-alt border-b">
                        <td class="py-2 px-2 text-gray-600"><?= safe_text($detalle['ubicacion'] ?: '-') ?></td>
                        <td class="py-2 px-2">
                            <div class="font-semibold"><?= safe_text($detalle['nombre']) ?></div>
                            <div class="text-xs text-gray-500"><?= safe_text($detalle['codigo_barras']) ?></div>
                        </td>
                        <td class="py-2 px-2 text-center"><?= $detalle['cantidad'] ?></td>
                        <td class="py-2 px-2">
                            <div class="border rounded w-5 h-5 mx-auto"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Firmas -->
        <div class="grid grid-cols-3 gap-4 mt-8 text-sm">
            <div class="text-center">
                <div class="border-t pt-1">
                    <p class="font-semibold">Entregado por</p>
                    <p class="text-xs text-gray-500">Nombre y firma</p>
                </div>
            </div>
            <div class="text-center">
                <div class="border-t pt-1">
                    <p class="font-semibold">Verificado por</p>
                    <p class="text-xs text-gray-500">Nombre y firma</p>
                </div>
            </div>
            <div class="text-center">
                <div class="border-t pt-1">
                    <p class="font-semibold">Recibido por</p>
                    <p class="text-xs text-gray-500">Nombre y firma</p>
                </div>
            </div>
        </div>

        <!-- Observaciones -->
        <div class="mt-4">
            <p class="font-semibold text-sm mb-1">Observaciones:</p>
            <div class="border rounded p-2 min-h-[60px] text-sm"></div>
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