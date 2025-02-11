<?php
session_start();
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos y Condiciones | VendEasy</title>
    <meta name="description" content="Términos y condiciones de uso de VendEasy - Sistema de gestión empresarial">
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
                <a href="/" class="flex items-center space-x-3 text-blue-600 text-xl font-bold hover:text-blue-700 transition-colors">
                    <i class="fas fa-calculator"></i>
                    <span>VendEasy</span>
                </a>
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
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Términos y Condiciones</h1>
                <p class="text-gray-600">Última actualización: 12/11/2024</p>
            </div>

            <!-- Contenido principal -->
            <div class="bg-white rounded-2xl shadow-xl p-8 space-y-8">
                <!-- Sección de Aceptación -->
                <section class="space-y-4">
                    <div class="flex items-center space-x-3 text-blue-600">
                        <i class="fas fa-check-circle text-2xl"></i>
                        <h2 class="text-2xl font-semibold">Aceptación de los términos</h2>
                    </div>
                    <p class="text-gray-600 leading-relaxed pl-9">
                        Al acceder y utilizar VendEasy, aceptas estar sujeto a estos términos y condiciones.
                        Si no estás de acuerdo con alguno de estos términos, te recomendamos no utilizar el servicio.
                    </p>
                </section>

                <!-- Sección de Uso del Servicio -->
                <section class="space-y-4">
                    <div class="flex items-center space-x-3 text-blue-600">
                        <i class="fas fa-cogs text-2xl"></i>
                        <h2 class="text-2xl font-semibold">Uso del servicio</h2>
                    </div>
                    <div class="pl-9">
                        <p class="text-gray-600 mb-4">VendEasy te permite:</p>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div class="bg-blue-50 p-4 rounded-xl">
                                <div class="flex items-center space-x-3 mb-2">
                                    <i class="fas fa-chart-line text-blue-600"></i>
                                    <h3 class="font-medium text-gray-900">Gestión de Ventas</h3>
                                </div>
                                <p class="text-gray-600 text-sm">Control completo de tus ventas e inventario</p>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-xl">
                                <div class="flex items-center space-x-3 mb-2">
                                    <i class="fas fa-file-invoice-dollar text-blue-600"></i>
                                    <h3 class="font-medium text-gray-900">Facturación</h3>
                                </div>
                                <p class="text-gray-600 text-sm">Genera facturas y reportes detallados</p>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-xl">
                                <div class="flex items-center space-x-3 mb-2">
                                    <i class="fas fa-users text-blue-600"></i>
                                    <h3 class="font-medium text-gray-900">Gestión de Clientes</h3>
                                </div>
                                <p class="text-gray-600 text-sm">Administra tu cartera de clientes</p>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-xl">
                                <div class="flex items-center space-x-3 mb-2">
                                    <i class="fas fa-box text-blue-600"></i>
                                    <h3 class="font-medium text-gray-900">Control de Inventario</h3>
                                </div>
                                <p class="text-gray-600 text-sm">Gestiona tu stock en tiempo real</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Sección de Privacidad -->
                <section class="space-y-4">
                    <div class="flex items-center space-x-3 text-blue-600">
                        <i class="fas fa-shield-alt text-2xl"></i>
                        <h2 class="text-2xl font-semibold">Privacidad y protección de datos</h2>
                    </div>
                    <div class="pl-9">
                        <p class="text-gray-600 mb-4">
                            Nos comprometemos a proteger tu información personal según la Ley 1581 de 2012.
                            Consulta nuestra <a href="/politica-privacidad.php" class="text-blue-600 hover:text-blue-700 underline">Política de Privacidad</a>.
                        </p>
                        <div class="bg-blue-50 p-4 rounded-xl">
                            <div class="flex items-center space-x-3 mb-2">
                                <i class="fas fa-lock text-blue-600"></i>
                                <h3 class="font-medium text-gray-900">Seguridad garantizada</h3>
                            </div>
                            <p class="text-gray-600 text-sm">Tus datos están protegidos con los más altos estándares de seguridad</p>
                        </div>
                    </div>
                </section>

                <!-- Sección de Contacto -->
                <section class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-6 mt-8">
                    <div class="flex items-center space-x-3 text-blue-600 mb-4">
                        <i class="fas fa-headset text-2xl"></i>
                        <h2 class="text-2xl font-semibold">¿Necesitas ayuda?</h2>
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
                        <a href="mailto:soporte@vendeasy.com" 
                           class="flex items-center space-x-3 bg-white p-4 rounded-xl hover:shadow-md transition-shadow">
                            <i class="fas fa-envelope text-blue-500 text-2xl"></i>
                            <div>
                                <h3 class="font-medium text-gray-900">Email</h3>
                                <p class="text-gray-600 text-sm">soporte@vendeasy.com</p>
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
                    © <?= date('Y') ?> VendEasy. Todos los derechos reservados.
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
