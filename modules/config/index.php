<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT rol FROM users WHERE id = ? AND empresa_id = ?");
$stmt->execute([$_SESSION['user_id'], $_SESSION['empresa_id']]);
$usuario = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Configuración | VendEasy</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
        <?php include '../../includes/sidebar.php'; ?>

            <!-- Contenido Principal -->
            <div class="w-full lg:w-3/4 px-4">
            <!-- Encabezado de la página -->
            <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">
                        <i class="fas fa-cogs text-indigo-600 mr-3"></i>Panel de Configuración
                    </h1>
                    <p class="mt-2 text-gray-600">Gestiona todas las configuraciones de tu cuenta y empresa desde un solo lugar.</p>
                </div>

                <!-- Sección de Configuraciones Personales -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-user-cog text-indigo-500 mr-2"></i>
                        Configuraciones Personales
                    </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Perfil -->
                        <a href="perfil/" class="transform transition-all duration-300 hover:scale-105">
                            <div class="bg-white rounded-xl shadow-sm hover:shadow-md p-6 border border-gray-100">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-user-circle text-2xl text-indigo-600"></i>
                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-semibold text-gray-900">Mi Perfil</h3>
                                        <p class="text-sm text-gray-600">Gestiona tu información personal y preferencias</p>
                        </div>
                                    <div class="ml-auto">
                                        <i class="fas fa-chevron-right text-gray-400"></i>
                        </div>
                        </div>
                </div>
                        </a>

                        <!-- Seguridad -->
                        <a href="seguridad/" class="transform transition-all duration-300 hover:scale-105">
                            <div class="bg-white rounded-xl shadow-sm hover:shadow-md p-6 border border-gray-100">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-lock text-2xl text-green-600"></i>
                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-semibold text-gray-900">Seguridad</h3>
                                        <p class="text-sm text-gray-600">Contraseña y sesiones activas</p>
                        </div>
                                    <div class="ml-auto">
                                        <i class="fas fa-chevron-right text-gray-400"></i>
                        </div>
                        </div>
                </div>
                        </a>
                    </div>
                </div>

                <!-- Sección de Configuraciones de Empresa -->
                <?php if ($usuario['rol'] === 'administrador'): ?>
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-building text-indigo-500 mr-2"></i>
                        Configuraciones de Empresa
                        </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Empresa -->
                        <a href="../empresas/" class="transform transition-all duration-300 hover:scale-105">
                            <div class="bg-white rounded-xl shadow-sm hover:shadow-md p-6 border border-gray-100">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-building text-2xl text-blue-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-semibold text-gray-900">Empresa</h3>
                                        <p class="text-sm text-gray-600">Configura la información de tu empresa</p>
                                    </div>
                                    <div class="ml-auto">
                                        <i class="fas fa-chevron-right text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                        </a>

                        <!-- Suscripción -->
                        <a href="../config/suscripcion/" class="transform transition-all duration-300 hover:scale-105">
                            <div class="bg-white rounded-xl shadow-sm hover:shadow-md p-6 border border-gray-100">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-crown text-2xl text-purple-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-semibold text-gray-900">Suscripción</h3>
                                        <p class="text-sm text-gray-600">Gestiona tu plan y facturación</p>
                                    </div>
                                    <div class="ml-auto">
                                        <i class="fas fa-chevron-right text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                        </a>

                        <!-- Usuarios -->
                        <a href="usuarios/" class="transform transition-all duration-300 hover:scale-105">
                            <div class="bg-white rounded-xl shadow-sm hover:shadow-md p-6 border border-gray-100">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-users text-2xl text-yellow-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-semibold text-gray-900">Usuarios</h3>
                                        <p class="text-sm text-gray-600">Administra los usuarios del sistema</p>
                                    </div>
                                    <div class="ml-auto">
                                        <i class="fas fa-chevron-right text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Información Adicional -->
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-semibold mb-3">¿Necesitas ayuda?</h2>
                            <div class="space-y-2 text-white/90">
                                <p class="flex items-center">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Configura tu cuenta y empresa desde un solo lugar
                                </p>
                                <p class="flex items-center">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Gestiona permisos y accesos de usuarios
                                </p>
                                <p class="flex items-center">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Mantén el control de tu suscripción y facturación
                                </p>
                            </div>
                            <button class="mt-4 bg-white text-indigo-600 px-4 py-2 rounded-lg font-medium hover:bg-opacity-90 transition-colors">
                                <i class="fas fa-headset mr-2"></i>
                                Contactar Soporte
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
