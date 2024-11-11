<?php
session_start();
require_once '../../config/db.php';
require_once 'generar_pdf_productos.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

try {
    $pdf_path = generarPDFProductos();
    
    if (file_exists($pdf_path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="productos.pdf"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        
        readfile($pdf_path);
        unlink($pdf_path);
        exit();
    } else {
        throw new Exception('No se pudo generar el archivo PDF');
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error al exportar productos: ' . $e->getMessage();
    header('Location: index.php');
    exit();
} 