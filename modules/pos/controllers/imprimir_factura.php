<?php
session_start();
require_once '../config/database.php';

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
               c.tipo_persona, c.responsabilidad_tributaria,
               e.nombre_empresa, e.nit, e.direccion as empresa_direccion,
               e.telefono as empresa_telefono, e.correo_contacto,
               e.regimen_fiscal, e.logo
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN empresas e ON e.usuario_id = v.user_id
        WHERE v.id = ? AND v.user_id = ?
    ");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception('Venta no encontrada');
    }

    // Obtener detalles de la venta
    $stmt = $pdo->prepare("
        SELECT vd.*, i.nombre, i.codigo_barras
        FROM venta_detalles vd
        LEFT JOIN inventario i ON vd.producto_id = i.id
        WHERE vd.venta_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Agregar verificación para campos nulos
function safe_text($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura <?= safe_text($venta['numero_factura']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos base */
        :root {
            --page-width: 210mm;
            --page-padding: 20px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
                background: none;
            }
            .print-container {
                box-shadow: none;
                padding: var(--page-padding);
                margin: 0;
            }
        }

        /* Estilos para ticket 80mm */
        @media print and (max-width: 80mm) {
            :root {
                --page-width: 80mm;
                --page-padding: 2mm;
            }
            body {
                font-size: 8pt;
            }
            .print-container {
                padding: 2mm;
            }
            table {
                font-size: 7pt;
            }
            th, td {
                padding: 2px 4px;
            }
        }

        /* Estilos para media carta */
        @media print and (max-width: 140mm) {
            :root {
                --page-width: 140mm;
                --page-padding: 5mm;
            }
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }

        .print-container {
            background: white;
            width: var(--page-width);
            margin: 0 auto;
            padding: var(--page-padding);
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .header h2 {
            margin: 0;
            font-size: 1.2em;
            font-weight: bold;
        }

        .header p {
            margin: 3px 0;
            font-size: 0.9em;
        }

        .invoice-details {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 0;
            margin: 10px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        th, td {
            text-align: left;
            padding: 4px;
            border-bottom: 1px solid #ddd;
        }

        .totals {
            text-align: right;
            margin-top: 10px;
        }

        .totals p {
            margin: 2px 0;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 0.8em;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }

        /* Botones de formato de impresión */
        .print-options {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        .print-button {
            padding: 10px 20px;
            background: #4F46E5;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .print-button:hover {
            background: #4338CA;
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="header">
            <h2><?= safe_text($venta['nombre_empresa']) ?></h2>
            <p><?= safe_text($venta['nit']) ?></p>
            <p><?= safe_text($venta['empresa_direccion']) ?></p>
            <p>Tel: <?= safe_text($venta['empresa_telefono']) ?></p>
            <p><?= safe_text($venta['correo_contacto']) ?></p>
            <p><?= safe_text($venta['regimen_fiscal']) ?></p>
        </div>

        <div class="invoice-details">
            <h3>FACTURA DE VENTA N° <?= safe_text($venta['numero_factura']) ?></h3>
            <p>Fecha: <?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></p>
            <p>Método de pago: <?= safe_text($venta['metodo_pago']) ?></p>
        </div>

        <div class="customer-info">
            <p><strong>Cliente:</strong> <?= safe_text(trim($venta['primer_nombre'] . ' ' . $venta['segundo_nombre'] . ' ' . $venta['apellidos'])) ?></p>
            <p><strong>ID:</strong> <?= safe_text($venta['identificacion']) ?></p>
            <?php if (!empty($venta['telefono'])): ?>
                <p><strong>Tel:</strong> <?= safe_text($venta['telefono']) ?></p>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Desc</th>
                    <th>Cant</th>
                    <th>Precio</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?= safe_text($detalle['nombre']) ?></td>
                        <td><?= $detalle['cantidad'] ?></td>
                        <td>$<?= number_format($detalle['precio_unitario'], 0, ',', '.') ?></td>
                        <td>$<?= number_format($detalle['precio_unitario'] * $detalle['cantidad'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <p>Subtotal: $<?= number_format($venta['subtotal'], 0, ',', '.') ?></p>
            <?php if ($venta['descuento'] > 0): ?>
                <p>Descuento (<?= $venta['descuento'] ?>%): -$<?= number_format(($venta['subtotal'] * $venta['descuento'] / 100), 0, ',', '.') ?></p>
            <?php endif; ?>
            <h3>TOTAL: $<?= number_format($venta['total'], 0, ',', '.') ?></h3>
        </div>

        <div class="footer">
            <p>¡Gracias por su compra!</p>
            <p>Esta factura se asimila en todos sus efectos a una letra de cambio de conformidad con el Art. 774 del código de comercio.</p>
        </div>
    </div>

    <!-- Botones de formato de impresión -->
    <div class="print-options no-print">
        <button onclick="printFormat('80mm')" class="print-button">
            <i class="fas fa-receipt"></i>
            Ticket 80mm
        </button>
        <button onclick="printFormat('media-carta')" class="print-button">
            <i class="fas fa-file-alt"></i>
            Media Carta
        </button>
        <button onclick="printFormat('carta')" class="print-button">
            <i class="fas fa-print"></i>
            Carta
        </button>
    </div>

    <script>
        function printFormat(format) {
            const container = document.querySelector('.print-container');
            
            switch(format) {
                case '80mm':
                    container.style.width = '80mm';
                    container.style.fontSize = '8pt';
                    break;
                case 'media-carta':
                    container.style.width = '140mm';
                    container.style.fontSize = '10pt';
                    break;
                case 'carta':
                    container.style.width = '210mm';
                    container.style.fontSize = '12pt';
                    break;
            }
            
            setTimeout(() => {
                window.print();
                // Restaurar el tamaño original después de imprimir
                container.style.width = '';
                container.style.fontSize = '';
            }, 100);
        }
    </script>
</body>
</html> 