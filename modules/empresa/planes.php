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

// Obtener información de la empresa
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$_SESSION['empresa_id'], $_SESSION['user_id']]);
$empresa = $stmt->fetch();

if (!$empresa) {
    header('Location: setup.php');
    exit();
}

// Agregar después del header y antes de mostrar los planes
if (isset($_SESSION['error_message'])): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="bg-red-50 border-l-4 border-red-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selección de Plan | Numercia</title>
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
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold text-gray-900">Selecciona tu Plan</h1>
            </div>
        </header>

        <!-- Progress Steps -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex justify-between items-center mb-8">
                <div class="w-full max-w-3xl mx-auto">
                    <div class="flex items-center justify-between relative">
                        <div class="w-full absolute top-1/2 transform -translate-y-1/2">
                            <div class="h-1 bg-primary-200"></div>
                        </div>
                        <div class="relative flex flex-col items-center">
                            <div class="w-10 h-10 bg-primary-600 rounded-full flex items-center justify-center z-10">
                                <i class="fas fa-check text-white"></i>
                            </div>
                            <span class="text-sm mt-2">Registro</span>
                        </div>
                        <div class="relative flex flex-col items-center">
                            <div class="w-10 h-10 bg-primary-600 rounded-full flex items-center justify-center z-10">
                                <i class="fas fa-check text-white"></i>
                            </div>
                            <span class="text-sm mt-2">Empresa</span>
                        </div>
                        <div class="relative flex flex-col items-center">
                            <div class="w-10 h-10 bg-primary-600 rounded-full flex items-center justify-center z-10">
                                <span class="text-white">3</span>
                            </div>
                            <span class="text-sm mt-2">Plan</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Planes Grid -->
            <div class="mt-12 grid gap-8 lg:grid-cols-3">
                <!-- Plan Básico -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="px-6 py-8">
                        <h3 class="text-2xl font-semibold text-gray-900">Básico</h3>
                        <p class="mt-2 text-gray-600">Ideal para emprendedores y pequeños negocios</p>
                        <p class="mt-4">
                            <span class="text-4xl font-bold">$19.900</span>
                            <span class="text-gray-600">/mes</span>
                        </p>
                        <ul class="mt-6 space-y-4">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Facturas ilimitadas</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>1 usuario</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Facturación electrónica</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Ingresos hasta $10M/mes</span>
                            </li>
                        </ul>
                        <a href="pago.php?plan=basico" 
                           class="mt-8 block w-full bg-primary-600 text-white text-center py-3 rounded-lg hover:bg-primary-700 transition duration-150">
                            Seleccionar Plan
                        </a>
                    </div>
                </div>

                <!-- Plan Profesional -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden border-2 border-primary-500 relative">
                    <div class="absolute top-0 right-0 bg-primary-500 text-white px-4 py-1 rounded-bl-lg">
                        MÁS POPULAR
                    </div>
                    <div class="px-6 py-8">
                        <h3 class="text-2xl font-semibold text-gray-900">Profesional</h3>
                        <p class="mt-2 text-gray-600">Perfecto para negocios en crecimiento</p>
                        <p class="mt-4">
                            <span class="text-4xl font-bold">$39.900</span>
                            <span class="text-gray-600">/mes</span>
                        </p>
                        <ul class="mt-6 space-y-4">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Facturas ilimitadas</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>3 usuarios</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Facturación electrónica</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Ingresos hasta $40M/mes</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Control de inventario</span>
                            </li>
                        </ul>
                        <a href="pago.php?plan=profesional" 
                           class="mt-8 block w-full bg-primary-600 text-white text-center py-3 rounded-lg hover:bg-primary-700 transition duration-150">
                            Seleccionar Plan
                        </a>
                    </div>
                </div>

                <!-- Plan Empresarial -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="px-6 py-8">
                        <h3 class="text-2xl font-semibold text-gray-900">Empresarial</h3>
                        <p class="mt-2 text-gray-600">Para grandes empresas y corporaciones</p>
                        <p class="mt-4">
                            <span class="text-4xl font-bold">$69.900</span>
                            <span class="text-gray-600">/mes</span>
                        </p>
                        <ul class="mt-6 space-y-4">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Facturas ilimitadas</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Usuarios ilimitados</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Facturación electrónica</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Ingresos hasta $180M/mes</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Personalización completa</span>
                            </li>
                        </ul>
                        <a href="pago.php?plan=empresarial" 
                           class="mt-8 block w-full bg-primary-600 text-white text-center py-3 rounded-lg hover:bg-primary-700 transition duration-150">
                            Seleccionar Plan
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 