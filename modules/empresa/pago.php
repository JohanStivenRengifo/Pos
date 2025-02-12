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
    'basico' => 19900,
    'profesional' => 39900,
    'empresarial' => 69900
];

$planes_info = [
    'basico' => [
        'nombre' => 'Básico',
        'descripcion' => 'Ideal para emprendedores y pequeños negocios',
        'caracteristicas' => [
            'Facturas ilimitadas',
            '1 usuario',
            'Facturación electrónica',
            'Ingresos hasta $10M/mes'
        ]
    ],
    'profesional' => [
        'nombre' => 'Profesional',
        'descripcion' => 'Perfecto para negocios en crecimiento',
        'caracteristicas' => [
            'Facturas ilimitadas',
            '3 usuarios',
            'Facturación electrónica',
            'Ingresos hasta $40M/mes',
            'Control de inventario'
        ]
    ],
    'empresarial' => [
        'nombre' => 'Empresarial',
        'descripcion' => 'Para grandes empresas y corporaciones',
        'caracteristicas' => [
            'Facturas ilimitadas',
            'Usuarios ilimitados',
            'Facturación electrónica',
            'Ingresos hasta $180M/mes',
            'Personalización completa'
        ]
    ]
];

// Generar identificador único para la orden (formato simplificado sin caracteres especiales)
$timestamp = time();
$random = mt_rand(100000, 999999);
$order_id = "ORD" . $timestamp . $random;

// Configuración de Bold
$api_key = 'bzk3k0ktTyJJ_gt0b2JyREg63E_zu-55eL-ncNfnP5Y';
$secret_key = '2UOCeKb_5cwCa1eNFcK7Pg';
$amount = intval($precios[$plan]); // Asegurar que sea un entero
$currency = 'COP';

// Concatenar en el orden correcto y generar hash
$integrity_string = $order_id . $amount . $currency . $secret_key;
$integrity_hash = hash('sha256', $integrity_string);

// Preparar descripción (máximo 100 caracteres, sin caracteres especiales)
$plan_info = $planes_info[$plan];
$descripcion = substr(preg_replace('/[^a-zA-Z0-9\s-]/', '', "Plan {$plan_info['nombre']} - Suscripcion Mensual"), 0, 100);

// Preparar datos del cliente (asegurar que todos los campos requeridos estén presentes y válidos)
$customer_data = [
    'email' => filter_var($_SESSION['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $_SESSION['email'] : '',
    'fullName' => trim($_SESSION['nombre'] ?? ''),
    'documentType' => 'CC',
    'documentNumber' => preg_replace('/[^0-9]/', '', $_SESSION['documento'] ?? ''),
    'phone' => preg_replace('/[^0-9]/', '', $_SESSION['telefono'] ?? ''),
    'dialCode' => '+57'
];

// Validar que los datos requeridos estén presentes
if (empty($customer_data['email']) || empty($customer_data['fullName']) || empty($customer_data['documentNumber'])) {
    error_log("Datos de cliente incompletos para Bold Payment");
}

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
<body class="bg-gradient-to-br from-gray-50 to-blue-50">
    <div class="min-h-screen flex flex-col py-12 px-4 sm:px-6 lg:px-8">
        <!-- Header con progreso -->
        <div class="max-w-3xl mx-auto w-full mb-8">
            <div class="flex justify-between items-center relative">
                <div class="absolute inset-0 flex items-center">
                    <div class="h-1 w-full bg-gray-200 rounded">
                        <div class="h-1 bg-primary-500 rounded" style="width: 75%"></div>
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
                        <span class="text-sm">4</span>
                    </div>
                    <span class="mt-2 text-xs text-gray-500">Pago</span>
                </div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="max-w-4xl mx-auto w-full">
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="md:flex">
                    <!-- Resumen del plan -->
                    <div class="md:w-1/2 bg-gradient-to-br from-primary-600 to-primary-800 p-8 text-white">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-crown text-yellow-400"></i>
                            <h2 class="text-2xl font-bold">Plan <?= ucfirst($plan) ?></h2>
                        </div>
                        
                        <div class="mt-6">
                            <div class="flex items-baseline">
                                <span class="text-5xl font-bold">$<?= number_format($precios[$plan], 0, ',', '.') ?></span>
                                <span class="ml-2 text-primary-100">/mes</span>
                            </div>
                            <p class="mt-2 text-primary-100"><?= $plan_info['descripcion'] ?></p>
                        </div>

                        <div class="mt-8">
                            <h3 class="font-semibold mb-4 text-primary-100">Tu plan incluye:</h3>
                            <ul class="space-y-3">
                                <?php foreach ($plan_info['caracteristicas'] as $caracteristica): ?>
                                    <li class="flex items-center space-x-3">
                                        <i class="fas fa-check-circle text-green-400"></i>
                                        <span class="text-sm"><?= $caracteristica ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="mt-8 p-4 bg-white/10 rounded-lg">
                            <div class="flex items-center text-sm">
                                <i class="fas fa-shield-alt mr-2"></i>
                                <span>Pago seguro procesado por Bold</span>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de pago -->
                    <div class="md:w-1/2 p-8">
                        <div class="text-center mb-8">
                            <h3 class="text-xl font-semibold text-gray-900">Finalizar Compra</h3>
                            <p class="mt-2 text-sm text-gray-600">
                                Completa tu suscripción en simples pasos
                            </p>
                        </div>

                        <!-- Métodos de pago aceptados -->
                        <div class="mb-6">
                            <p class="text-sm text-gray-600 mb-3">Métodos de pago aceptados:</p>
                            <div class="flex space-x-4">
                                <i class="fab fa-cc-visa text-2xl text-gray-400"></i>
                                <i class="fab fa-cc-mastercard text-2xl text-gray-400"></i>
                                <i class="fab fa-cc-amex text-2xl text-gray-400"></i>
                                <span class="text-sm font-medium text-gray-600 flex items-center">
                                    <i class="fas fa-university mr-1"></i> PSE
                                </span>
                            </div>
                        </div>

                        <!-- Botón de pago Bold -->
                        <div class="space-y-4">
                            <!-- Contenedor para el botón -->
                            <div id="payment-button-container"></div>

                            <!-- Script de Bold -->
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    try {
                                        const config = {
                                            'data-bold-button': 'dark-L',
                                            'data-api-key': '<?= $api_key ?>',
                                            'data-amount': <?= $amount ?>,
                                            'data-currency': '<?= $currency ?>',
                                            'data-order-id': '<?= $order_id ?>',
                                            'data-integrity-signature': '<?= $integrity_hash ?>',
                                            'data-description': '<?= $descripcion ?>',
                                            'data-customer-data': <?= json_encode($customer_data) ?>,
                                            'data-redirection-url': '<?= 'https://' . $_SERVER['HTTP_HOST'] . '/modules/empresa/pago-respuesta.php' ?>'
                                        };

                                        // Validar configuración antes de crear el botón
                                        if (!config['data-api-key'] || !config['data-amount'] || !config['data-order-id'] || !config['data-integrity-signature']) {
                                            throw new Error('Faltan parámetros requeridos para la configuración del botón');
                                        }

                                        const container = document.getElementById('payment-button-container');
                                        if (!container) {
                                            throw new Error('Contenedor del botón no encontrado');
                                        }

                                        // Limpiar el contenedor antes de agregar el nuevo script
                                        container.innerHTML = '';
                                        
                                        const script = document.createElement('script');
                                        Object.entries(config).forEach(([key, value]) => {
                                            script.setAttribute(key, typeof value === 'object' ? JSON.stringify(value) : String(value));
                                        });
                                        
                                        script.src = 'https://checkout.bold.co/library/boldPaymentButton.js';
                                        script.onerror = function(error) {
                                            console.error('Error loading Bold Payment Button:', error);
                                            container.innerHTML = '<div class="text-red-500">Error al cargar el botón de pago. Por favor, recarga la página.</div>';
                                        };
                                        
                                        container.appendChild(script);

                                        // Monitorear errores específicos de Bold
                                        window.addEventListener('message', function(event) {
                                            if (event.data && event.data.type === 'BOLD_PAYMENT_ERROR') {
                                                console.error('Bold Payment Error:', event.data);
                                            }
                                        });

                                    } catch (error) {
                                        console.error('Error initializing Bold Payment Button:', error);
                                        const container = document.getElementById('payment-button-container');
                                        if (container) {
                                            container.innerHTML = '<div class="text-red-500">Error al inicializar el botón de pago. Por favor, recarga la página.</div>';
                                        }
                                    }
                                });
                            </script>

                            <!-- Debug info (solo visible en desarrollo) -->
                            <?php if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG']): ?>
                            <div class="mt-4 p-4 bg-gray-100 rounded-lg text-xs">
                                <pre class="overflow-auto"><?= json_encode([
                                    'order_id' => $order_id,
                                    'amount' => $amount,
                                    'currency' => $currency,
                                    'description' => $descripcion,
                                    'customer_data' => $customer_data
                                ], JSON_PRETTY_PRINT) ?></pre>
                            </div>
                            <?php endif; ?>

                            <!-- Mensaje de seguridad -->
                            <div class="text-center mt-4">
                                <p class="text-xs text-gray-500">
                                    <i class="fas fa-lock mr-1"></i>
                                    Transacción segura procesada por Bold
                                </p>
                            </div>

                            <div class="text-center mt-4">
                                <a href="planes.php" class="inline-flex items-center text-sm text-primary-600 hover:text-primary-500">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Volver a los planes
                                </a>
                            </div>
                        </div>

                        <!-- Garantías y seguridad -->
                        <div class="mt-8 space-y-4">
                            <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-lock text-green-500 mr-3"></i>
                                <div class="text-sm">
                                    <p class="font-medium text-gray-900">Pago 100% Seguro</p>
                                    <p class="text-gray-600">Tus datos están protegidos con encriptación SSL</p>
                                </div>
                            </div>
                            <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-undo text-green-500 mr-3"></i>
                                <div class="text-sm">
                                    <p class="font-medium text-gray-900">14 días de garantía</p>
                                    <p class="text-gray-600">No estás conforme, te devolvemos tu dinero</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-auto py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center text-sm text-gray-500">
                <p>¿Necesitas ayuda? <a href="../../contacto.php" class="text-primary-600 hover:text-primary-500">Contáctanos</a></p>
            </div>
        </div>
    </footer>
</body>
</html> 