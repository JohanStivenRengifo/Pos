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
<body class="bg-gray-100">
    <?php include '../../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
            <?php include '../../includes/sidebar.php'; ?>

            <!-- Contenido Principal -->
            <div class="w-full lg:w-3/4 px-4">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-cogs mr-2"></i>Panel de Configuración
                    </h1>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Perfil -->
                        <a href="perfil/" class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-user-circle text-2xl text-indigo-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-lg font-semibold text-gray-900">Mi Perfil</h2>
                                    <p class="text-sm text-gray-600">Gestiona tu información personal y preferencias</p>
                                </div>
                            </div>
                        </a>

                        <!-- Seguridad -->
                        <a href="seguridad/" class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-lock text-2xl text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-lg font-semibold text-gray-900">Seguridad</h2>
                                    <p class="text-sm text-gray-600">Contraseña y sesiones activas</p>
                                </div>
                            </div>
                        </a>

                        <!-- Empresa -->
                        <?php if ($usuario['rol'] === 'administrador'): ?>
                        <a href="../empresas/" class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-building text-2xl text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-lg font-semibold text-gray-900">Empresa</h2>
                                    <p class="text-sm text-gray-600">Configura la información de tu empresa</p>
                                </div>
                            </div>
                        </a>
                        <?php endif; ?>

                        <!-- Suscripción -->
                        <?php if ($usuario['rol'] === 'administrador'): ?>
                        <a href="../config/suscripcion/" class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-crown text-2xl text-purple-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-lg font-semibold text-gray-900">Suscripción</h2>
                                    <p class="text-sm text-gray-600">Gestiona tu plan y facturación</p>
                                </div>
                            </div>
                        </a>
                        <?php endif; ?>

                        <!-- Usuarios -->
                        <?php if ($usuario['rol'] === 'administrador'): ?>
                        <a href="usuarios/" class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-users text-2xl text-yellow-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-lg font-semibold text-gray-900">Usuarios</h2>
                                    <p class="text-sm text-gray-600">Administra los usuarios del sistema</p>
                                </div>
                            </div>
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Información Adicional -->
                    <div class="mt-8 p-6 bg-gray-50 rounded-lg">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-info-circle mr-2"></i>Información
                        </h2>
                        <div class="text-sm text-gray-600 space-y-2">
                            <p>• Desde aquí puedes acceder a todas las configuraciones de tu cuenta y empresa.</p>
                            <p>• Algunas opciones solo están disponibles para administradores del sistema.</p>
                            <p>• Si necesitas ayuda, contacta con nuestro soporte técnico.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
