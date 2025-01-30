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
               e.telefono as empresa_telefono, e.logo
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN empresas e ON e.usuario_id = v.user_id
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
    <title>Remisión - Venta #<?= safe_text($venta['numero_factura']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page { size: letter; margin: 1.5cm; }
        @media print {
            .no-print { display: none !important; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
        .page-container { max-width: 21cm; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="page-container mx-auto bg-white shadow-lg my-8 p-8">
        <!-- Encabezado -->
        <div class="text-center border-b pb-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">ORDEN DE DESPACHO</h1>
            <p class="text-lg text-gray-600">Remisión N° <?= safe_text($venta['numero_factura']) ?></p>
        </div>

        <!-- Info empresa y cliente -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            <div>
                <h3 class="font-bold mb-2">Información de Despacho:</h3>
                <p class="text-sm">Fecha: <?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></p>
                <p class="text-sm">Cliente: <?= safe_text(trim($venta['primer_nombre'] . ' ' . $venta['segundo_nombre'] . ' ' . $venta['apellidos'])) ?></p>
                <p class="text-sm">Dirección: <?= safe_text($venta['direccion']) ?></p>
                <p class="text-sm">Teléfono: <?= safe_text($venta['telefono']) ?></p>
            </div>
            <div>
                <h3 class="font-bold mb-2">Información de la Empresa:</h3>
                <p class="text-sm"><?= safe_text($venta['nombre_empresa']) ?></p>
                <p class="text-sm">NIT: <?= safe_text($venta['nit']) ?></p>
                <p class="text-sm">Tel: <?= safe_text($venta['empresa_telefono']) ?></p>
            </div>
        </div>

        <!-- Tabla de productos -->
        <table class="w-full mb-8">
            <thead class="bg-gray-100">
                <tr>
                    <th class="py-2 px-4 text-left w-24">Ubicación</th>
                    <th class="py-2 px-4 text-left">Producto</th>
                    <th class="py-2 px-4 text-center">Cantidad</th>
                    <th class="py-2 px-4 text-center">Despachado</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td class="py-2 px-4 text-sm text-gray-600"><?= safe_text($detalle['ubicacion'] ?: 'Sin ubicación') ?></td>
                        <td class="py-2 px-4">
                            <?= safe_text($detalle['nombre']) ?>
                            <br>
                            <span class="text-sm text-gray-500"><?= safe_text($detalle['codigo_barras']) ?></span>
                        </td>
                        <td class="py-2 px-4 text-center"><?= $detalle['cantidad'] ?></td>
                        <td class="py-2 px-4 text-center">
                            <div class="border rounded w-6 h-6 mx-auto"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Firmas -->
        <div class="grid grid-cols-3 gap-8 mt-12">
            <div class="text-center">
                <div class="border-t border-gray-400 pt-2">Entregado por</div>
            </div>
            <div class="text-center">
                <div class="border-t border-gray-400 pt-2">Verificado por</div>
            </div>
            <div class="text-center">
                <div class="border-t border-gray-400 pt-2">Recibido por</div>
            </div>
        </div>

        <!-- Observaciones -->
        <div class="mt-8 border-t pt-4">
            <p class="font-bold mb-2">Observaciones:</p>
            <div class="border rounded-lg p-4 min-h-[100px]"></div>
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