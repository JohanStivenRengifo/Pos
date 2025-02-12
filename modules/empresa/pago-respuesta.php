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
                updated_at = NOW(),
                monto = CASE 
                    WHEN plan = 'basico' THEN 19900
                    WHEN plan = 'profesional' THEN 39900
                    WHEN plan = 'empresarial' THEN 69900
                    ELSE monto
                END
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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                        }
                    },
                    animation: {
                        'bounce-slow': 'bounce 2s infinite',
                        'spin-slow': 'spin 2s linear infinite',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50">
    <div class="min-h-screen flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <!-- Progress Steps -->
            <div class="mb-12">
                <div class="flex justify-between items-center relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="h-1 w-full bg-gray-200 rounded">
                            <div class="h-1 bg-primary-500 rounded" style="width: 100%"></div>
                        </div>
                    </div>
                    <div class="relative flex flex-col items-center">
                        <div class="rounded-full h-8 w-8 bg-primary-500 text-white flex items-center justify-center">
                            <i class="fas fa-check text-sm"></i>
                        </div>
                        <span class="mt-2 text-xs text-gray-500">Registro</span>
                    </div>
                    <div class="relative flex flex-col items-center">
                        <div class="rounded-full h-8 w-8 bg-primary-500 text-white flex items-center justify-center">
                            <i class="fas fa-check text-sm"></i>
                        </div>
                        <span class="mt-2 text-xs text-gray-500">Empresa</span>
                    </div>
                    <div class="relative flex flex-col items-center">
                        <div class="rounded-full h-8 w-8 bg-primary-500 text-white flex items-center justify-center">
                            <i class="fas fa-check text-sm"></i>
                        </div>
                        <span class="mt-2 text-xs text-gray-500">Plan</span>
                    </div>
                    <div class="relative flex flex-col items-center">
                        <div class="rounded-full h-8 w-8 bg-primary-500 text-white flex items-center justify-center">
                            <i class="fas fa-check text-sm"></i>
                        </div>
                        <span class="mt-2 text-xs text-gray-500">Pago</span>
                    </div>
                </div>
            </div>

            <!-- Estado del pago -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="p-8">
                    <?php if ($tx_status === 'approved'): ?>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-check text-2xl text-green-500"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900 mb-2">¡Pago Exitoso!</h2>
                            <p class="text-gray-600 mb-6">Tu suscripción ha sido activada correctamente</p>
                            
                            <div class="space-y-4">
                                <div class="p-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">Número de orden:</span>
                                        <span class="font-medium"><?= htmlspecialchars($order_id) ?></span>
                                    </div>
                                </div>
                                
                                <a href="../../welcome.php" 
                                   class="inline-flex items-center justify-center w-full px-4 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                                    <i class="fas fa-home mr-2"></i>
                                    Ir al Dashboard
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-times text-2xl text-red-500"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900 mb-2">Pago No Completado</h2>
                            <p class="text-gray-600 mb-6">Lo sentimos, hubo un problema al procesar tu pago</p>
                            
                            <div class="space-y-4">
                                <div class="p-4 bg-red-50 border border-red-100 rounded-lg">
                                    <p class="text-sm text-red-600">
                                        Por favor, intenta nuevamente o contacta a soporte si el problema persiste.
                                    </p>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <a href="planes.php" 
                                       class="inline-flex items-center justify-center px-4 py-3 border border-primary-600 text-primary-600 rounded-lg hover:bg-primary-50 transition-colors">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Volver
                                    </a>
                                    <a href="../../contacto.php" 
                                       class="inline-flex items-center justify-center px-4 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                                        <i class="fas fa-headset mr-2"></i>
                                        Soporte
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Información adicional -->
            <div class="mt-8 text-center">
                <p class="text-sm text-gray-600">
                    ¿Necesitas ayuda? <a href="../../contacto.php" class="text-primary-600 hover:text-primary-500">Contáctanos</a>
                </p>
            </div>
        </div>
    </div>

    <?php if ($tx_status === 'approved'): ?>
    <script>
        // Redirigir al dashboard después de 5 segundos
        setTimeout(() => {
            window.location.href = '../../welcome.php';
        }, 5000);
    </script>
    <?php endif; ?>
</body>
</html> 