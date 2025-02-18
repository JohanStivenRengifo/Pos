<?php
session_start();
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidad | Numercia</title>
    <meta name="description" content="Política de privacidad y protección de datos de Numercia">
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen">
    <!-- Header mejorado -->
    <header class="bg-white/80 backdrop-blur-md shadow-sm fixed w-full z-50">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex justify-between items-center">
            <div class="flex items-center">
                    <a href="/" class="flex items-center space-x-2">
                        <img src="/favicon/favicon.ico" alt="Numercia Logo" class="h-8 w-8">
                        <span class="text-xl font-bold text-gray-900">Numercia</span>
                    </a>
                </div>
                <a href="/" class="flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition-colors bg-white/80 py-2 px-4 rounded-lg shadow-sm hover:shadow">
                    <i class="fas fa-arrow-left"></i>
                    <span>Volver</span>
                </a>
            </div>
        </nav>
    </header>

    <main class="pt-28 pb-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <!-- Encabezado de la página -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Política de Privacidad</h1>
                <p class="text-gray-600">Última actualización: 12/11/2024</p>
            </div>

            <!-- Contenido principal -->
            <div class="bg-white rounded-2xl shadow-xl p-8 space-y-8">
                <!-- Sección de Introducción -->
                <section class="space-y-4">
                    <div class="flex items-center space-x-3 text-blue-600">
                        <i class="fas fa-shield-alt text-2xl"></i>
                        <h2 class="text-2xl font-semibold">Protección de datos</h2>
                    </div>
                    <p class="text-gray-600 leading-relaxed pl-9">
                        En Numercia, la seguridad de tus datos es nuestra prioridad. Esta política describe cómo 
                        recopilamos, usamos y protegemos tu información personal.
                    </p>
                </section>

                <!-- Sección de Información Recopilada -->
                <section class="space-y-4">
                    <div class="flex items-center space-x-3 text-blue-600">
                        <i class="fas fa-database text-2xl"></i>
                        <h2 class="text-2xl font-semibold">Información que recopilamos</h2>
                    </div>
                    <div class="pl-9 grid md:grid-cols-2 gap-4">
                        <div class="bg-blue-50 p-4 rounded-xl">
                            <h3 class="font-medium text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-user-circle text-blue-600 mr-2"></i>
                                Datos personales
                            </h3>
                            <ul class="space-y-2 text-gray-600 text-sm">
                                <li class="flex items-start">
                                    <i class="fas fa-check text-blue-600 mt-1 mr-2"></i>
                                    <span>Nombre y apellidos</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-blue-600 mt-1 mr-2"></i>
                                    <span>Correo electrónico</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-blue-600 mt-1 mr-2"></i>
                                    <span>Teléfono de contacto</span>
                                </li>
                            </ul>
                        </div>
                        <div class="bg-blue-50 p-4 rounded-xl">
                            <h3 class="font-medium text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-building text-blue-600 mr-2"></i>
                                Datos empresariales
                            </h3>
                            <ul class="space-y-2 text-gray-600 text-sm">
                                <li class="flex items-start">
                                    <i class="fas fa-check text-blue-600 mt-1 mr-2"></i>
                                    <span>Nombre de la empresa</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-blue-600 mt-1 mr-2"></i>
                                    <span>NIT o identificación fiscal</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-blue-600 mt-1 mr-2"></i>
                                    <span>Dirección comercial</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Sección de Seguridad -->
                <section class="space-y-4">
                    <div class="flex items-center space-x-3 text-blue-600">
                        <i class="fas fa-lock text-2xl"></i>
                        <h2 class="text-2xl font-semibold">Medidas de seguridad</h2>
                    </div>
                    <div class="pl-9 grid md:grid-cols-2 gap-4">
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl">
                            <div class="flex items-center space-x-3 mb-3">
                                <i class="fas fa-shield-alt text-blue-600"></i>
                                <h3 class="font-medium text-gray-900">Protección de datos</h3>
                            </div>
                            <p class="text-gray-600 text-sm">
                                Utilizamos encriptación SSL/TLS para proteger la transmisión de datos sensibles
                            </p>
                        </div>
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl">
                            <div class="flex items-center space-x-3 mb-3">
                                <i class="fas fa-user-shield text-blue-600"></i>
                                <h3 class="font-medium text-gray-900">Acceso seguro</h3>
                            </div>
                            <p class="text-gray-600 text-sm">
                                Autenticación de dos factores y monitoreo constante de actividades
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Sección de Derechos -->
                <section class="space-y-4">
                    <div class="flex items-center space-x-3 text-blue-600">
                        <i class="fas fa-user-check text-2xl"></i>
                        <h2 class="text-2xl font-semibold">Tus derechos</h2>
                    </div>
                    <div class="pl-9">
                        <div class="bg-blue-50 p-6 rounded-xl space-y-4">
                            <p class="text-gray-600">Como usuario de Numercia, tienes derecho a:</p>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-eye text-blue-600 mt-1"></i>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Acceso</h4>
                                        <p class="text-sm text-gray-600">Ver tus datos personales</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-edit text-blue-600 mt-1"></i>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Rectificación</h4>
                                        <p class="text-sm text-gray-600">Modificar datos incorrectos</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-trash text-blue-600 mt-1"></i>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Eliminación</h4>
                                        <p class="text-sm text-gray-600">Solicitar borrado de datos</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-download text-blue-600 mt-1"></i>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Portabilidad</h4>
                                        <p class="text-sm text-gray-600">Exportar tu información</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Sección de Contacto -->
                <section class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-6 mt-8">
                    <div class="flex items-center space-x-3 text-blue-600 mb-4">
                        <i class="fas fa-headset text-2xl"></i>
                        <h2 class="text-2xl font-semibold">¿Dudas sobre privacidad?</h2>
                    </div>
                    <div class="grid md:grid-cols-2 gap-6">
                        <a href="https://wa.me/573116035791" target="_blank" 
                           class="flex items-center space-x-3 bg-white p-4 rounded-xl hover:shadow-md transition-shadow">
                            <i class="fab fa-whatsapp text-green-500 text-2xl"></i>
                            <div>
                                <h3 class="font-medium text-gray-900">WhatsApp</h3>
                                <p class="text-gray-600 text-sm">Respuesta inmediata</p>
                            </div>
                        </a>
                        <a href="mailto:privacidad@numercia.com" 
                           class="flex items-center space-x-3 bg-white p-4 rounded-xl hover:shadow-md transition-shadow">
                            <i class="fas fa-envelope text-blue-500 text-2xl"></i>
                            <div>
                                <h3 class="font-medium text-gray-900">Email</h3>
                                <p class="text-gray-600 text-sm">privacidad@numercia.com</p>
                            </div>
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <!-- Footer mejorado -->
    <footer class="bg-white/80 backdrop-blur-md border-t border-gray-200">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <div class="text-gray-600 text-sm">
                    © <?= date('Y') ?> Numercia. Todos los derechos reservados.
                </div>
                <div class="flex space-x-6">
                    <a href="/terminos-y-condiciones.php" class="text-gray-600 hover:text-blue-600 text-sm transition-colors">
                        Términos y Condiciones
                    </a>
                    <a href="/politica-privacidad.php" class="text-gray-600 hover:text-blue-600 text-sm transition-colors">
                        Política de Privacidad
                    </a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
