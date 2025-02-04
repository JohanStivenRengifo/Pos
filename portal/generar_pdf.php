<?php
require_once('../config/db.php');
require_once('../vendor/autoload.php'); // Asegúrate de tener instalado TCPDF via composer

function generarFacturaPDF($tipo, $id) {
    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Obtener datos de la factura
        $stmt = $db->prepare("
            SELECT v.*, c.primer_nombre, c.segundo_nombre, c.apellidos, c.identificacion
            FROM ventas v
            JOIN clientes c ON v.cliente_id = c.id
            WHERE v.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$factura) {
            throw new Exception('Factura no encontrada');
        }

        // Crear nuevo documento PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Configurar documento
        $pdf->SetCreator('Portal de Clientes');
        $pdf->SetAuthor('Tu Empresa');
        $pdf->SetTitle('Factura #' . $factura['numero_factura']);

        // Eliminar cabecera y pie de página predeterminados
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Agregar página
        $pdf->AddPage();

        // Contenido de la factura
        $html = '
        <h1>Factura #' . $factura['numero_factura'] . '</h1>
        <div style="margin-bottom: 20px;">
            <p><strong>Cliente:</strong> ' . htmlspecialchars($factura['primer_nombre'] . ' ' . $factura['segundo_nombre'] . ' ' . $factura['apellidos']) . '</p>
            <p><strong>Identificación:</strong> ' . htmlspecialchars($factura['identificacion']) . '</p>
            <p><strong>Fecha:</strong> ' . date('d/m/Y', strtotime($factura['fecha'])) . '</p>
        </div>
        ';

        // Agregar contenido al PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // Generar el PDF
        $pdf->Output('factura_' . $factura['numero_factura'] . '.pdf', 'D');

    } catch (Exception $e) {
        // Manejar error
        header('Location: detalles.php?tipo=' . urlencode($tipo) . '&id=' . $id . '&error=pdf');
        exit;
    }
}

// Validar parámetros
$tipo = filter_input(INPUT_GET, 'tipo', FILTER_SANITIZE_STRING);
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if ($tipo === 'Venta' && !empty($id)) {
    generarFacturaPDF($tipo, $id);
} else {
    header('Location: index.php');
    exit;
} 