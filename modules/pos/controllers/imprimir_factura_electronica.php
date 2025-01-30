<?php
require_once '../../../config/db.php';
require_once 'alegra_integration.php';

// Habilitar el reporte de errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['id'])) {
    die('ID de venta no especificado');
}

try {
    // Obtener datos de la venta
    $stmt = $pdo->prepare("
        SELECT v.*, 
               c.primer_nombre, c.segundo_nombre, c.apellidos, 
               c.identificacion, c.email, c.telefono, c.direccion,
               e.nombre_empresa as empresa_nombre,
               e.nit as empresa_nit,
               e.direccion as empresa_direccion,
               e.telefono as empresa_telefono,
               e.correo_contacto as empresa_email
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN empresas e ON e.estado = 1 AND e.es_principal = 1
        WHERE v.id = ? AND v.numeracion = 'electronica'
    ");
    $stmt->execute([$_GET['id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception('Venta electrónica no encontrada');
    }

    if (empty($venta['alegra_id'])) {
        throw new Exception('La venta no tiene un ID de Alegra asociado');
    }

    // Intentar obtener el PDF de Alegra
    $alegra = new AlegraIntegration();
    $alegraResponse = $alegra->getInvoiceDetails($venta['alegra_id']);

    if (!$alegraResponse['success'] || empty($alegraResponse['data']['pdf_url'])) {
        error_log('No se pudo obtener PDF de Alegra, usando impresión local');
        header('Location: imprimir_factura.php?id=' . $_GET['id']);
        exit;
    }

    // Verificar que la URL sea válida
    $pdfUrl = $alegraResponse['data']['pdf_url'];
    if (filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
        header('Location: ' . $pdfUrl);
        exit;
    } else {
        error_log('URL de PDF no válida: ' . $pdfUrl);
        header('Location: imprimir_factura.php?id=' . $_GET['id']);
        exit;
    }

} catch (Exception $e) {
    error_log('Error en imprimir_factura_electronica.php: ' . $e->getMessage());
    header('Location: imprimir_factura.php?id=' . $_GET['id']);
    exit;
} 