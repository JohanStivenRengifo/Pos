<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/functions.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Obtener información de la suscripción actual
$stmt = $pdo->prepare("
    SELECT e.*, p.* 
    FROM empresas e
    LEFT JOIN pagos p ON e.id = p.empresa_id 
    WHERE e.id = ? 
    AND p.estado = 'completado'
    AND p.es_suscripcion = 1
    ORDER BY p.fecha_pago DESC 
    LIMIT 1
");
$stmt->execute([$_SESSION['empresa_id']]);
$suscripcion = $stmt->fetch();

// Definir las características de los planes
$caracteristicas_planes = [
    'basico' => [
        'usuarios' => '5 usuarios',
        'modulos' => [
            'Ventas básicas',
            'Inventario básico',
            'Reportes básicos'
        ]
    ],
    'profesional' => [
        'usuarios' => 'Usuarios ilimitados',
        'modulos' => [
            'Todas las funciones de ventas',
            'Inventario avanzado',
            'Reportes avanzados',
            'Soporte 24/7',
            'Facturación electrónica'
        ]
    ],
    'empresarial' => [
        'usuarios' => 'Usuarios ilimitados',
        'modulos' => [
            'Todas las funciones premium',
            'Personalización total',
            'API acceso',
            'Soporte dedicado',
            'Múltiples sucursales',
            'Integración con otros sistemas'
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Suscripción | VendEasy</title>
    <link rel="icon" href="../../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../../../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
            <?php include '../../../includes/sidebar.php'; ?>

            <!-- Contenido Principal -->
            <div class="w-full lg:w-3/4 px-4">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-crown mr-2"></i>Mi Suscripción
                    </h1>

                    <!-- Estado de la Suscripción -->
                    <div class="bg-indigo-50 p-6 rounded-lg mb-8">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-xl font-semibold text-indigo-800">
                                    Plan <?php echo ucfirst($suscripcion['plan_suscripcion']); ?>
                                </h2>
                                <p class="text-indigo-600 mt-2">
                                    Válido hasta: <?php echo date('d/m/Y', strtotime($suscripcion['fecha_fin_plan'])); ?>
                                </p>
                            </div>
                            <div class="bg-indigo-100 p-3 rounded-full">
                                <i class="fas fa-crown text-3xl text-indigo-600"></i>
                            </div>
                        </div>

                        <!-- Detalles del Plan -->
                        <div class="bg-white rounded-lg p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Características de tu Plan</h3>
                            
                            <!-- Usuarios -->
                            <div class="mb-4">
                                <p class="text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-users mr-2 text-indigo-600"></i>
                                    <?php echo $caracteristicas_planes[$suscripcion['plan_suscripcion']]['usuarios']; ?>
                                </p>
                            </div>

                            <!-- Módulos y Características -->
                            <div class="space-y-2">
                                <?php foreach ($caracteristicas_planes[$suscripcion['plan_suscripcion']]['modulos'] as $caracteristica): ?>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-check text-green-500 mr-2"></i>
                                        <?php echo $caracteristica; ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Estado de Facturación -->
                        <div class="mt-6 bg-white rounded-lg p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Estado de Facturación</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Próxima factura</p>
                                    <p class="text-lg font-medium text-gray-900">
                                        <?php echo date('d/m/Y', strtotime($suscripcion['fecha_fin_plan'])); ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Monto mensual</p>
                                    <p class="text-lg font-medium text-gray-900">
                                        $<?php echo number_format($suscripcion['monto'], 2); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 