<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: /auth/login.php');
    exit;
}

try {
    // Obtener datos de la venta
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            c.primer_nombre,
            c.segundo_nombre,
            c.apellidos,
            c.identificacion,
            c.direccion,
            c.telefono,
            c.email,
            u.nombre as vendedor_nombre,
            e.nombre_empresa as empresa_nombre,
            e.nit as empresa_nit,
            e.direccion as empresa_direccion,
            e.telefono as empresa_telefono,
            e.correo_contacto as empresa_email,
            e.logo as empresa_logo,
            e.regimen_fiscal,
            e.tipo_persona as empresa_tipo,
            e.responsabilidad_tributaria as empresa_responsabilidad,
            e.departamento,
            e.municipio,
            e.codigo_postal
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN users u ON v.user_id = u.id
        LEFT JOIN empresas e ON u.empresa_id = e.id
        WHERE v.id = ? 
        AND u.empresa_id = ?
    ");
    
    $stmt->execute([$_GET['id'], $_SESSION['empresa_id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception('Venta no encontrada');
    }

    // Obtener detalles de la venta
    $stmt = $pdo->prepare("
        SELECT 
            vd.producto_id,
            i.nombre as producto_nombre,
            i.codigo_barras,
            i.descripcion,
            SUM(vd.cantidad) as cantidad,
            vd.precio_unitario,
            SUM(vd.cantidad * vd.precio_unitario) as total_item
        FROM venta_detalles vd
        LEFT JOIN inventario i ON vd.producto_id = i.id
        WHERE vd.venta_id = ?
        GROUP BY vd.producto_id, i.nombre, i.codigo_barras, vd.precio_unitario
        ORDER BY i.nombre ASC
    ");
    $stmt->execute([$_GET['id']]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formato = $_GET['formato'] ?? '80mm';
    
    // Generar HTML para impresión
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Factura #<?= $venta['numero_factura'] ?></title>
        <style>
            @page {
                <?php if ($formato === '80mm'): ?>
                margin: 0;
                size: 80mm auto;
                <?php else: ?>
                margin: 10mm;
                size: letter;
                <?php endif; ?>
            }
            @font-face {
                font-family: 'Open Sans';
                src: url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap');
            }
            body {
                font-family: 'Open Sans', 'Arial', sans-serif;
                margin: 0;
                padding: <?= $formato === '80mm' ? '5mm' : '10mm' ?>;
                font-size: <?= $formato === '80mm' ? '10px' : '12px' ?>;
                width: <?= $formato === '80mm' ? '70mm' : 'auto' ?>;
                line-height: 1.3;
                <?php if ($formato === 'carta'): ?>
                max-width: 210mm;
                margin: 0 auto;
                <?php endif; ?>
            }
            .header {
                text-align: center;
                margin-bottom: 5mm;
            }
            .logo {
                max-width: <?= $formato === '80mm' ? '40mm' : '60mm' ?>;
                height: auto;
                margin-bottom: 3mm;
                object-fit: contain;
            }
            .empresa-nombre {
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 2mm;
                text-transform: uppercase;
            }
            .info-empresa {
                font-size: 10px;
                line-height: 1.4;
                margin-bottom: 4mm;
            }
            .info-cliente, .info-factura {
                font-size: 10px;
                line-height: 1.4;
                margin-bottom: 3mm;
            }
            .linea {
                border-top: 1px dashed #000;
                margin: 3mm 0;
            }
            .producto {
                margin-bottom: 2.5mm;
            }
            .producto-nombre {
                font-weight: 600;
                font-size: 11px;
                margin-bottom: 0.5mm;
            }
            .producto-detalle {
                padding-left: 3mm;
                font-size: 10px;
            }
            .totales {
                text-align: right;
                margin: 4mm 0;
                font-size: 11px;
            }
            .totales .total-principal {
                font-weight: 600;
                font-size: 12px;
                margin-top: 1mm;
            }
            .texto-legal {
                font-size: 8px;
                text-align: justify;
                margin: 3mm 0;
                line-height: 1.3;
            }
            .footer {
                text-align: center;
                font-size: 9px;
                margin-top: 6mm;
                padding-top: 2mm;
                border-top: 1px solid #ccc;
            }
            
            <?php if ($formato === 'carta'): ?>
            .container {
                max-width: 190mm;
                margin: 0 auto;
                padding: 5mm;
                border: 1px solid #ddd;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            .producto {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 2mm 0;
            }
            .producto-nombre {
                flex: 1;
            }
            .producto-detalle {
                text-align: right;
                padding-left: 5mm;
            }
            <?php endif; ?>
        </style>
    </head>
    <body>
        <?php if ($formato === 'carta'): ?>
        <div class="container">
        <?php endif; ?>
        
        <div class="header">
            <?php if (!empty($venta['empresa_logo'])): ?>
                <?php
                // Construir la ruta correcta del logo
                $logoPath = $venta['empresa_logo'];
                if (strpos($logoPath, 'http') !== 0) {
                    // Si no es una URL completa, construir la ruta desde la raíz
                    $logoPath = '/uploads/logos/' . basename($logoPath);
                }
                ?>
                <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <div class="empresa-nombre"><?= htmlspecialchars($venta['empresa_nombre']) ?></div>
            <div class="info-empresa">
                NIT: <?= htmlspecialchars($venta['empresa_nit']) ?><br>
                <?= htmlspecialchars($venta['empresa_direccion']) ?><br>
                Teléfono: <?= htmlspecialchars($venta['empresa_telefono']) ?><br>
                <?= htmlspecialchars($venta['empresa_email']) ?><br>
                Régimen: <?= htmlspecialchars($venta['regimen_fiscal'] ?? 'No responsable de IVA') ?>
            </div>
        </div>

        <div class="linea"></div>

        <div class="info-cliente">
            Cliente: <?= htmlspecialchars($venta['primer_nombre'] . ' ' . $venta['apellidos']) ?><br>
            <?= $venta['tipo_identificacion'] ?? 'CC' ?>: <?= htmlspecialchars($venta['identificacion']) ?>
        </div>

        <div class="info-factura">
            <?= $venta['tipo_documento'] == 'factura' ? 'Factura de venta' : 'Documento' ?> N° <?= $venta['numero_factura'] ?><br>
            Fecha de emisión: <?= date('d/m/Y H:i:s', strtotime($venta['fecha'])) ?><br>
            Forma de pago: Contado<br>
            Método de pago: <?= ucfirst($venta['metodo_pago']) ?><br>
            Vendedor: <?= htmlspecialchars($venta['vendedor_nombre']) ?><br>
            Vencimiento: <?= date('d/m/Y', strtotime($venta['fecha'])) ?>
        </div>

        <div class="linea"></div>

        <?php 
        $total_productos = 0;
        foreach ($detalles as $detalle): 
            $total_productos += $detalle['cantidad'];
        ?>
            <div class="producto">
                <div class="producto-nombre"><?= htmlspecialchars($detalle['producto_nombre']) ?></div>
                <div class="producto-detalle">
                    <?= number_format($detalle['cantidad'], 0) ?> x $<?= number_format($detalle['precio_unitario'], 0, ',', '.') ?> = 
                    $<?= number_format($detalle['total_item'], 0, ',', '.') ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="linea"></div>

        <div class="totales">
            <div>Subtotal: $<?= number_format($venta['subtotal'], 0, ',', '.') ?></div>
            <?php if ($venta['descuento'] > 0): ?>
                <div>Descuento: $<?= number_format($venta['descuento'], 0, ',', '.') ?></div>
            <?php endif; ?>
            <div class="total-principal">Total: $<?= number_format($venta['total'], 0, ',', '.') ?></div>
            <div>Total recibido: $<?= number_format($venta['total'], 0, ',', '.') ?></div>
            <div style="margin-top: 2mm; font-size: 10px; color: #666;">
                Total de líneas: <?= count($detalles) ?><br>
                Total de productos: <?= $total_productos ?>
            </div>
        </div>

        <div class="texto-legal">
            Esta factura se asimila en todos sus efectos a una letra de cambio de conformidad con el Art. 774 del código de comercio. 
            Autorizo que en caso de incumplimiento de esta obligación sea reportado a las centrales de riesgo, se cobraran intereses por mora.
        </div>

        <div class="texto-legal">
            Con esta factura de venta el comprador declara haber recibido de forma real y materialmente las mercancías y/o servicios 
            descritos en este titulo valor.
        </div>

        <div class="footer">
            <strong><?= htmlspecialchars($venta['empresa_nombre']) ?></strong><br>
            <span style="color: #666;">Generado por Numercia POS</span><br>
            <span style="color: #666;">www.numercia.com</span>
        </div>

        <?php if ($formato === 'carta'): ?>
        </div>
        <?php endif; ?>

        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
} catch (Exception $e) {
    error_log("Error al imprimir comprobante: " . $e->getMessage());
    echo "Error al generar el comprobante: " . $e->getMessage();
} 