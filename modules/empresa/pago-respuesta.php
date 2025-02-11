<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Obtener parámetros de Bold
$order_id = $_GET['bold-order-id'] ?? null;
$tx_status = $_GET['bold-tx-status'] ?? null;

// Validar que tengamos los parámetros necesarios
if (!$order_id || !$tx_status) {
    header('Location: planes.php');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Obtener información del pago actual
    $stmt = $pdo->prepare("
        SELECT plan, monto FROM pagos 
        WHERE bold_order_id = ?
    ");
    $stmt->execute([$order_id]);
    $pago_actual = $stmt->fetch();
    
    if (!$pago_actual) {
        throw new Exception("No se encontró el registro de pago");
    }
    
    if ($tx_status === 'approved') {
        // Desactivar cualquier suscripción activa anterior
        $stmt = $pdo->prepare("
            UPDATE pagos 
            SET estado = 'cancelado',
                updated_at = NOW()
            WHERE empresa_id = ? 
            AND estado = 'completado'
            AND fecha_fin_plan > NOW()
        ");
        $stmt->execute([$_SESSION['empresa_id']]);
        
        // Actualizar el registro de pago actual
        $stmt = $pdo->prepare("
            UPDATE pagos 
            SET estado = 'completado',
                fecha_inicio_plan = NOW(),
                fecha_fin_plan = DATE_ADD(NOW(), INTERVAL 1 MONTH),
                updated_at = NOW()
            WHERE bold_order_id = ?
        ");
        $stmt->execute([$order_id]);
        
        // Actualizar el plan de la empresa
        $stmt = $pdo->prepare("
            UPDATE empresas 
            SET plan_suscripcion = ?,
                fecha_inicio_plan = NOW(),
                fecha_fin_plan = DATE_ADD(NOW(), INTERVAL 1 MONTH)
            WHERE id = ?
        ");
        $stmt->execute([$pago_actual['plan'], $_SESSION['empresa_id']]);
        
        $_SESSION['success_message'] = "¡Tu pago ha sido procesado exitosamente! Tu plan ha sido actualizado.";
        
        $pdo->commit();
        header('Location: ../../welcome.php?payment_success=true');
        exit();
    } else {
        // Marcar el pago como fallido
        $stmt = $pdo->prepare("
            UPDATE pagos 
            SET estado = 'fallido',
                updated_at = NOW()
            WHERE bold_order_id = ?
        ");
        $stmt->execute([$order_id]);
        
        $pdo->commit();
        
        $_SESSION['error_message'] = "El pago no pudo ser procesado. Por favor, intenta nuevamente.";
        header('Location: planes.php?payment_failed=true');
        exit();
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error procesando respuesta de pago: " . $e->getMessage());
    $_SESSION['error_message'] = "Ocurrió un error al procesar el pago. Por favor, contacta a soporte.";
    header('Location: planes.php?payment_error=true');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesando Pago | VendEasy</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full text-center">
            <div class="rounded-full h-24 w-24 bg-blue-100 mx-auto flex items-center justify-center animate-spin">
                <i class="fas fa-circle-notch text-4xl text-blue-500"></i>
            </div>
            <h2 class="mt-6 text-3xl font-bold text-gray-900">Procesando Pago</h2>
            <p class="mt-2 text-gray-600">
                Por favor espera mientras procesamos tu pago...
            </p>
        </div>
    </div>
</body>
</html> 