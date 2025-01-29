<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

try {
    // Obtener datos de la cotización
    $stmt = $pdo->prepare("
        SELECT c.*,
               cl.primer_nombre, cl.segundo_nombre, cl.apellidos,
               cl.identificacion, cl.direccion, cl.telefono,
               cl.tipo_persona, cl.responsabilidad_tributaria,
               e.nombre_empresa, e.nit, e.direccion as empresa_direccion,
               e.telefono as empresa_telefono, e.correo_contacto,
               e.regimen_fiscal, e.logo
        FROM cotizaciones c
        LEFT JOIN clientes cl ON c.cliente_id = cl.id
        LEFT JOIN empresas e ON e.usuario_id = ?
        WHERE c.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $_GET['id']]);
    $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cotizacion) {
        throw new Exception('Cotización no encontrada');
    }

    // Obtener detalles de la cotización
    $stmt = $pdo->prepare("
        SELECT cd.*, i.nombre, i.codigo_barras
        FROM cotizacion_detalles cd
        LEFT JOIN inventario i ON cd.producto_id = i.id
        WHERE cd.cotizacion_id = ?
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
    <title>Cotización <?= safe_text($cotizacion['numero']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-container {
            max-width: 21cm;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            @page {
                size: letter;
                margin: 1.5cm;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="page-container mx-auto bg-white shadow-lg my-8 p-8">
        <!-- Encabezado -->
        <div class="flex justify-between items-start border-b pb-6">
            <div class="flex-1">
                <?php if ($cotizacion['logo']): ?>
                    <img src="<?= safe_text($cotizacion['logo']) ?>" alt="Logo" class="h-20 object-contain mb-4">
                <?php endif; ?>
                <h2 class="text-2xl font-bold text-gray-800"><?= safe_text($cotizacion['nombre_empresa']) ?></h2>
                <p class="text-gray-600">NIT: <?= safe_text($cotizacion['nit']) ?></p>
                <p class="text-gray-600"><?= safe_text($cotizacion['empresa_direccion']) ?></p>
                <p class="text-gray-600">Tel: <?= safe_text($cotizacion['empresa_telefono']) ?></p>
                <p class="text-gray-600"><?= safe_text($cotizacion['correo_contacto']) ?></p>
            </div>
            <div class="text-right">
                <h1 class="text-3xl font-bold text-indigo-600 mb-2">COTIZACIÓN</h1>
                <p class="text-lg font-semibold text-gray-700">N° <?= safe_text($cotizacion['numero']) ?></p>
                <p class="text-gray-600">Fecha: <?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?></p>
                <p class="text-gray-600">Válida hasta: <?= date('d/m/Y', strtotime($cotizacion['fecha_vencimiento'])) ?></p>
            </div>
        </div>

        <!-- Información del cliente -->
        <div class="mt-8 bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Información del Cliente</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-600"><span class="font-medium">Nombre:</span> <?= safe_text(trim($cotizacion['primer_nombre'] . ' ' . $cotizacion['segundo_nombre'] . ' ' . $cotizacion['apellidos'])) ?></p>
                    <p class="text-gray-600"><span class="font-medium">Identificación:</span> <?= safe_text($cotizacion['identificacion']) ?></p>
                </div>
                <div>
                    <p class="text-gray-600"><span class="font-medium">Teléfono:</span> <?= safe_text($cotizacion['telefono']) ?></p>
                    <p class="text-gray-600"><span class="font-medium">Dirección:</span> <?= safe_text($cotizacion['direccion']) ?></p>
                </div>
            </div>
        </div>

        <!-- Tabla de productos -->
        <div class="mt-8">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-3 px-4 text-left text-gray-700">Descripción</th>
                        <th class="py-3 px-4 text-center text-gray-700">Cantidad</th>
                        <th class="py-3 px-4 text-right text-gray-700">Precio Unit.</th>
                        <th class="py-3 px-4 text-right text-gray-700">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td class="py-3 px-4">
                                <p class="font-medium text-gray-800"><?= safe_text($detalle['descripcion']) ?></p>
                                <p class="text-sm text-gray-600"><?= safe_text($detalle['codigo_barras']) ?></p>
                            </td>
                            <td class="py-3 px-4 text-center"><?= number_format($detalle['cantidad'], 0) ?></td>
                            <td class="py-3 px-4 text-right">$<?= number_format($detalle['precio_unitario'], 0, ',', '.') ?></td>
                            <td class="py-3 px-4 text-right">$<?= number_format($detalle['subtotal'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="border-t-2 border-gray-200">
                    <tr>
                        <td colspan="3" class="py-3 px-4 text-right font-medium">Total:</td>
                        <td class="py-3 px-4 text-right font-bold text-lg text-indigo-600">
                            $<?= number_format($cotizacion['total'], 0, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Términos y condiciones -->
        <div class="mt-8 text-sm text-gray-600">
            <h4 class="font-semibold text-gray-800 mb-2">Términos y Condiciones:</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li>Esta cotización tiene una validez de 15 días a partir de la fecha de emisión.</li>
                <li>Los precios están sujetos a cambios sin previo aviso.</li>
                <li>Los tiempos de entrega se confirmarán al momento de la orden.</li>
                <li>Esta cotización no representa un compromiso de venta.</li>
            </ul>
        </div>

        <!-- Firma -->
        <div class="mt-12 pt-8 border-t">
            <div class="w-64 mx-auto text-center">
                <div class="border-b border-gray-400 pb-2"></div>
                <p class="mt-2 text-gray-600">Firma y Sello</p>
            </div>
        </div>
    </div>

    <!-- Botones de impresión -->
    <div class="fixed bottom-4 right-4 space-x-2 no-print">
        <button onclick="printFormat('carta')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
            <i class="fas fa-print mr-2"></i>Carta
        </button>
        <button onclick="printFormat('media-carta')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
            <i class="fas fa-file-alt mr-2"></i>Media Carta
        </button>
    </div>

    <script>
        function printFormat(format) {
            const container = document.querySelector('.page-container');
            
            if (format === 'media-carta') {
                container.style.maxWidth = '14cm';
                container.style.fontSize = '0.9em';
                container.style.padding = '15px';
            } else {
                container.style.maxWidth = '21cm';
                container.style.fontSize = '1em';
                container.style.padding = '2rem';
            }
            
            setTimeout(() => {
                window.print();
                // Restaurar tamaño original
                container.style.maxWidth = '';
                container.style.fontSize = '';
                container.style.padding = '';
            }, 100);
        }

        // Imprimir automáticamente al cargar
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html> 