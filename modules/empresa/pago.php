<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/limiter.php';
require_once '../../includes/functions.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Verificar si se seleccionó un plan
if (!isset($_GET['plan']) || !in_array($_GET['plan'], ['basico', 'profesional', 'empresarial'])) {
    header('Location: planes.php');
    exit();
}

$plan = $_GET['plan'];
$precios = [
    'basico' => 29900,
    'profesional' => 59900,
    'empresarial' => 119900
];

$planes_info = [
    'basico' => [
        'nombre' => 'Básico',
        'descripcion' => 'Ideal para emprendedores y pequeños negocios',
        'caracteristicas' => [
            'Hasta 100 facturas mensuales',
            '1 usuario',
            'Reportes básicos',
            'Soporte por email'
        ]
    ],
    'profesional' => [
        'nombre' => 'Profesional',
        'descripcion' => 'Perfecto para negocios en crecimiento',
        'caracteristicas' => [
            'Hasta 500 facturas mensuales',
            '3 usuarios',
            'Reportes avanzados',
            'Soporte prioritario 24/7',
            'Control de inventario'
        ]
    ],
    'empresarial' => [
        'nombre' => 'Empresarial',
        'descripcion' => 'Para grandes empresas y corporaciones',
        'caracteristicas' => [
            'Facturas ilimitadas',
            'Usuarios ilimitados',
            'API personalizada',
            'Soporte VIP',
            'Personalización completa'
        ]
    ]
];

// Generar identificador único para la orden
$order_id = 'SUB' . time() . rand(1000, 9999);

// Generar hash de integridad
$secret_key = 'srxZSHpl6H6usb1CVMQYeg';
$integrity_string = $order_id . $precios[$plan] . 'COP' . $secret_key;
$integrity_hash = hash('sha256', $integrity_string);

// Preparar descripción detallada del plan
$plan_info = $planes_info[$plan];
$descripcion = "{$plan_info['nombre']} - {$plan_info['descripcion']}";

// Registrar intento de pago en la base de datos
try {
    $stmt = $pdo->prepare("
        INSERT INTO pagos (
            empresa_id,
            usuario_id,
            plan,
            monto,
            fecha_pago,
            estado,
            metodo_pago,
            bold_order_id,
            detalles_transaccion,
            es_suscripcion
        ) VALUES (?, ?, ?, ?, NOW(), 'pendiente', 'bold', ?, ?, true)
    ");

    $detalles = json_encode([
        'plan' => $plan,
        'caracteristicas' => $plan_info['caracteristicas'],
        'precio' => $precios[$plan],
        'tipo' => 'suscripcion_mensual'
    ]);

    $stmt->execute([
        $_SESSION['empresa_id'],
        $_SESSION['user_id'],
        $plan,
        $precios[$plan],
        $order_id,
        $detalles
    ]);
} catch (Exception $e) {
    error_log("Error al registrar intento de pago: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesar Pago | VendEasy</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://checkout.bold.co/library/boldPaymentButton.js"></script>
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
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-bold text-gray-900">Procesar Pago</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Plan <?= ucfirst($plan) ?>
                </p>
                <p class="text-2xl font-bold text-primary-600">
                    $<?= number_format($precios[$plan], 0, ',', '.') ?> <span class="text-base font-normal text-gray-500">/mes</span>
                </p>
                
                <!-- Mostrar características del plan -->
                <div class="mt-4 text-left bg-white p-4 rounded-lg shadow-sm">
                    <h3 class="font-medium text-gray-900 mb-2">Incluye:</h3>
                    <ul class="space-y-2">
                        <?php foreach ($plan_info['caracteristicas'] as $caracteristica): ?>
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <?= $caracteristica ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="mt-8">
                <!-- Botón de pago Bold -->
                <script
                    data-bold-button
                    data-api-key="LHrE5O4DWOy1U3Mof-KR0T8uOtI9LppLBbVpl-BuLs0"
                    data-amount="<?= $precios[$plan] ?>"
                    data-currency="COP"
                    data-order-id="<?= $order_id ?>"
                    data-integrity-signature="<?= $integrity_hash ?>"
                    data-description="<?= htmlspecialchars($descripcion) ?> - Suscripción Mensual"
                    data-customer-data='{"email": "<?= $_SESSION['email'] ?>", "fullName": "<?= $_SESSION['nombre'] ?>"}'
                    data-redirection-url="<?= 'https://' . $_SERVER['HTTP_HOST'] . '/modules/empresa/pago-respuesta.php' ?>"
                    data-render-mode="embedded"
                    data-extra-data-1="<?= $plan ?>"
                    data-subscription="true"
                    data-subscription-interval="monthly"
                ></script>
            </div>

            <div class="text-center mt-4">
                <a href="planes.php" class="text-sm text-primary-600 hover:text-primary-500">
                    Volver a los planes
                </a>
            </div>
        </div>
    </div>
</body>
</html> 