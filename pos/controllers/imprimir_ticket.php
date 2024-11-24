<?php
session_start();
require_once '../../config/db.php';

if (!isset($_GET['venta_id'])) {
    die('ID de venta no proporcionado');
}

$ventaId = $_GET['venta_id'];

try {
    // Obtener información de la venta
    $stmt = $pdo->prepare("
        SELECT v.*, 
               CONCAT(c.primer_nombre, ' ', COALESCE(c.segundo_nombre, ''), ' ', c.apellidos) as cliente_nombre,
               c.tipo_identificacion,
               c.identificacion as cliente_identificacion,
               c.municipio_departamento as cliente_direccion,
               c.telefono as cliente_telefono,
               c.email as cliente_email
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        WHERE v.id = ?
    ");
    $stmt->execute([$ventaId]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        die('Venta no encontrada');
    }

    // Obtener detalles de la venta
    $stmt = $pdo->prepare("
        SELECT vd.*, 
               i.nombre as producto_nombre,
               i.codigo_barras
        FROM venta_detalles vd
        JOIN inventario i ON vd.producto_id = i.id
        WHERE vd.venta_id = ?
    ");
    $stmt->execute([$ventaId]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener información de la empresa
    $stmt = $pdo->prepare("SELECT * FROM configuracion WHERE id = 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de Venta #<?= $ventaId ?></title>
    <style>
        @media print {
            body {
                font-family: 'Courier New', monospace;
                font-size: 12px;
                margin: 0;
                padding: 10px;
                width: 80mm;
            }
            .ticket {
                width: 100%;
            }
            .header, .footer {
                text-align: center;
                margin-bottom: 10px;
            }
            .divider {
                border-top: 1px dashed #000;
                margin: 5px 0;
            }
            .item {
                display: flex;
                justify-content: space-between;
                margin: 3px 0;
            }
            .totals {
                margin-top: 10px;
                text-align: right;
            }
            @page {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <h2><?= htmlspecialchars($config['nombre_empresa'] ?? 'VendEasy') ?></h2>
            <p><?= htmlspecialchars($config['direccion'] ?? '') ?></p>
            <p>Tel: <?= htmlspecialchars($config['telefono'] ?? '') ?></p>
            <p>NIT: <?= htmlspecialchars($config['nit'] ?? '') ?></p>
        </div>

        <div class="divider"></div>

        <div class="info">
            <p>Ticket #: <?= $ventaId ?></p>
            <p>Fecha: <?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></p>
            <p>Cliente: <?= htmlspecialchars($venta['cliente_nombre']) ?></p>
            <?php if ($venta['cliente_identificacion']): ?>
                <p><?= htmlspecialchars($venta['tipo_identificacion']) ?>: <?= htmlspecialchars($venta['cliente_identificacion']) ?></p>
            <?php endif; ?>
            <?php if ($venta['cliente_direccion']): ?>
                <p>Dirección: <?= htmlspecialchars($venta['cliente_direccion']) ?></p>
            <?php endif; ?>
            <?php if ($venta['cliente_telefono']): ?>
                <p>Tel: <?= htmlspecialchars($venta['cliente_telefono']) ?></p>
            <?php endif; ?>
        </div>

        <div class="divider"></div>

        <div class="items">
            <?php foreach ($detalles as $detalle): ?>
            <div class="item">
                <div>
                    <p><?= htmlspecialchars($detalle['producto_nombre']) ?></p>
                    <p><?= $detalle['cantidad'] ?> x $<?= number_format($detalle['precio_unitario'], 0, ',', '.') ?></p>
                </div>
                <div>
                    <p>$<?= number_format($detalle['cantidad'] * $detalle['precio_unitario'], 0, ',', '.') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="divider"></div>

        <div class="totals">
            <p>Subtotal: $<?= number_format($venta['subtotal'], 0, ',', '.') ?></p>
            <?php if ($venta['descuento'] > 0): ?>
            <p>Descuento: $<?= number_format($venta['descuento'], 0, ',', '.') ?></p>
            <?php endif; ?>
            <p><strong>Total: $<?= number_format($venta['total'], 0, ',', '.') ?></strong></p>
        </div>

        <div class="divider"></div>

        <div class="footer">
            <p>¡Gracias por su compra!</p>
            <p><?= htmlspecialchars($config['mensaje_ticket'] ?? '') ?></p>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
            window.onafterprint = function() {
                window.close();
            };
        };
    </script>
</body>
</html>
<?php
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?> 