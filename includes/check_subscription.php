<?php
function verificarSuscripcion($pdo, $empresa_id) {
    $stmt = $pdo->prepare("
        SELECT p.*, e.plan_suscripcion 
        FROM pagos p
        JOIN empresas e ON e.id = p.empresa_id
        WHERE p.empresa_id = ? 
        AND p.estado = 'completado'
        AND p.fecha_fin_plan >= NOW()
        ORDER BY p.fecha_pago DESC
        LIMIT 1
    ");
    
    $stmt->execute([$empresa_id]);
    return $stmt->fetch();
}

// Verificar suscripción
if (!isset($_SESSION['user_id']) || !isset($_SESSION['empresa_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$suscripcion = verificarSuscripcion($pdo, $_SESSION['empresa_id']);

if (!$suscripcion && !in_array($_SERVER['PHP_SELF'], [
    '/modules/empresa/planes.php',
    '/modules/empresa/pago.php',
    '/modules/empresa/pago-respuesta.php'
])) {
    $_SESSION['error_message'] = "Tu suscripción ha expirado. Por favor, renueva tu plan para continuar.";
    header('Location: /modules/empresa/planes.php');
    exit();
} 