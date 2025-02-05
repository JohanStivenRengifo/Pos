<?php
session_start();
require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$credito = null;
$pagos = [];

try {
    if (!isset($_GET['id'])) {
        throw new Exception("ID de crédito no especificado");
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

    // Obtener información del crédito
    $query = "SELECT c.*, 
                     v.numero_factura,
                     v.total as venta_total,
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre,
                     cl.identificacion as cliente_identificacion,
                     cl.tipo_identificacion as cliente_tipo_identificacion,
                     cl.email as cliente_email,
                     cl.telefono as cliente_telefono,
                     cl.direccion as cliente_direccion
              FROM creditos c
              LEFT JOIN ventas v ON c.venta_id = v.id
              LEFT JOIN clientes cl ON v.cliente_id = cl.id
              WHERE c.id = ? AND cl.user_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $credito = $stmt->fetch();

    if (!$credito) {
        throw new Exception("Crédito no encontrado");
    }

    // Obtener pagos del crédito
    $query = "SELECT * FROM creditos_pagos 
              WHERE credito_id = ? 
              ORDER BY numero_cuota ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $pagos = $stmt->fetchAll();

    // Calcular estadísticas
    $pagos_realizados = array_filter($pagos, fn($p) => $p['estado'] === 'Pagado');
    $total_pagado = array_sum(array_column($pagos_realizados, 'monto'));

    // Crear clase personalizada de PDF
    class CreditoPDF extends FPDF {
        function Header() {
            global $empresa, $credito;
            
            // Logo
            if (!empty($empresa['logo']) && file_exists('../../' . $empresa['logo'])) {
                $this->Image('../../' . $empresa['logo'], 10, 10, 30);
            }
            
            // Título del documento
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, mb_convert_encoding('ESTADO DE CUENTA', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->SetFont('Arial', '', 12);
            $this->Cell(0, 6, mb_convert_encoding('Factura #' . $credito['numero_factura'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            
            // Información de la empresa
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, mb_convert_encoding($empresa['nombre_empresa'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding('NIT: ' . $empresa['nit'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding($empresa['direccion'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            
            $this->Ln(5);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, mb_convert_encoding('Página ' . $this->PageNo() . '/{nb}', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
        }

        function InfoSection($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(230, 230, 230);
            $this->Cell(0, 8, mb_convert_encoding($title, 'ISO-8859-1', 'UTF-8'), 0, 1, 'L', true);
            $this->Ln(4);
        }

        function TableHeader() {
            $this->SetFont('Arial', 'B', 9);
            $this->SetFillColor(240, 240, 240);
            $this->Cell(15, 7, 'Cuota', 1, 0, 'C', true);
            $this->Cell(25, 7, 'Vencimiento', 1, 0, 'C', true);
            $this->Cell(30, 7, 'Capital', 1, 0, 'R', true);
            $this->Cell(30, 7, 'Interés', 1, 0, 'R', true);
            $this->Cell(30, 7, 'Total', 1, 0, 'R', true);
            $this->Cell(25, 7, 'Estado', 1, 0, 'C', true);
            $this->Cell(25, 7, 'Fecha Pago', 1, 1, 'C', true);
        }
    }

    // Crear nuevo PDF
    $pdf = new CreditoPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Información del Cliente
    $pdf->InfoSection('INFORMACIÓN DEL CLIENTE');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, mb_convert_encoding('Cliente: ' . $credito['cliente_nombre'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding($credito['cliente_tipo_identificacion'] . ': ' . $credito['cliente_identificacion'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Dirección: ' . $credito['cliente_direccion'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Teléfono: ' . $credito['cliente_telefono'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Ln(5);

    // Resumen del Crédito
    $pdf->InfoSection('RESUMEN DEL CRÉDITO');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(100, 6, 'Monto Total: $' . number_format($credito['monto_total'], 2, ',', '.'), 0, 0);
    $pdf->Cell(90, 6, 'Total Pagado: $' . number_format($total_pagado, 2, ',', '.'), 0, 1);
    $pdf->Cell(100, 6, 'Interés: ' . number_format($credito['interes'], 2) . '%', 0, 0);
    $pdf->Cell(90, 6, 'Saldo Pendiente: $' . number_format($credito['saldo_pendiente'], 2, ',', '.'), 0, 1);
    $pdf->Cell(100, 6, 'Plazo: ' . $credito['plazo'] . ' días', 0, 0);
    $pdf->Cell(90, 6, 'Valor Cuota: $' . number_format($credito['valor_cuota'], 2, ',', '.'), 0, 1);
    $pdf->Ln(5);

    // Plan de Pagos
    $pdf->InfoSection('PLAN DE PAGOS');
    $pdf->TableHeader();
    $pdf->SetFont('Arial', '', 9);
    
    foreach ($pagos as $pago) {
        $pdf->Cell(15, 6, $pago['numero_cuota'] . '/' . $credito['cuotas'], 1, 0, 'C');
        $pdf->Cell(25, 6, date('d/m/Y', strtotime($pago['fecha_vencimiento_cuota'])), 1, 0, 'C');
        $pdf->Cell(30, 6, '$' . number_format($pago['capital_pagado'], 2, ',', '.'), 1, 0, 'R');
        $pdf->Cell(30, 6, '$' . number_format($pago['interes_pagado'], 2, ',', '.'), 1, 0, 'R');
        $pdf->Cell(30, 6, '$' . number_format($pago['monto'], 2, ',', '.'), 1, 0, 'R');
        $pdf->Cell(25, 6, $pago['estado'], 1, 0, 'C');
        $pdf->Cell(25, 6, $pago['fecha_pago'] ? date('d/m/Y', strtotime($pago['fecha_pago'])) : '-', 1, 1, 'C');
    }

    // Espacios para firmas
    $pdf->Ln(20);
    $pdf->Cell(95, 0, '', 'T', 0, 'C');
    $pdf->Cell(10, 0, '', 0, 0);
    $pdf->Cell(95, 0, '', 'T', 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(95, 5, 'Firma del Cliente', 0, 0, 'C');
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Cell(95, 5, 'Por la Empresa', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(95, 5, mb_convert_encoding($credito['cliente_nombre'], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Cell(95, 5, mb_convert_encoding($empresa['nombre_empresa'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

    // Generar el PDF
    $pdf->Output('I', 'estado_cuenta_' . $credito['numero_factura'] . '_' . date('Y-m-d') . '.pdf');
    exit;

} catch (Exception $e) {
    error_log('Error generando estado de cuenta: ' . $e->getMessage());
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>Error al generar el estado de cuenta</h1>";
    echo "<p>Lo sentimos, ha ocurrido un error al generar el estado de cuenta. Por favor, inténtelo de nuevo más tarde.</p>";
} 