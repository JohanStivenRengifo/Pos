<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$success_message = '';

try {
    if (!isset($_GET['id'])) {
        throw new Exception("ID de cotización no especificado");
    }

    // Verificar que la cotización exista y pertenezca al usuario
    $query = "SELECT c.*, 
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre
              FROM cotizaciones c
              LEFT JOIN clientes cl ON c.cliente_id = cl.id
              WHERE c.id = ? AND cl.user_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $cotizacion = $stmt->fetch();

    if (!$cotizacion) {
        throw new Exception("Cotización no encontrada o no tiene permisos para eliminarla");
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Eliminar primero los detalles (por la restricción de llave foránea)
        $query = "DELETE FROM cotizacion_detalles WHERE cotizacion_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET['id']]);

        // Eliminar la cotización
        $query = "DELETE FROM cotizaciones WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET['id']]);

        // Confirmar transacción
        $pdo->commit();

        $_SESSION['success_message'] = "Cotización eliminada exitosamente";
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Error al eliminar la cotización: " . $e->getMessage());
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
    $_SESSION['error_message'] = $error_message;
    header("Location: index.php");
    exit;
}
?> 