<?php
session_start();
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto | Numercia</title>
    <meta name="description" content="Contáctanos para cualquier duda o sugerencia sobre Numercia">
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .whatsapp-button:hover .whatsapp-tooltip {
            opacity: 1;
            transform: translateY(0);
        }
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
            <div class="text-center mb-16">
                <h1 class="text-5xl font-bold text-gray-900 mb-6">¿Necesitas ayuda?</h1>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Estamos aquí para ayudarte. Contáctanos a través de cualquiera de nuestros canales de comunicación.
                </p>
            </div>

            <!-- Tarjetas de contacto mejoradas -->
            <div class="grid md:grid-cols-3 gap-8 mb-16">
                <!-- WhatsApp Card -->
                <div class="bg-white rounded-2xl shadow-lg p-8 text-center transform hover:-translate-y-1 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-green-50 rounded-full -mr-12 -mt-12"></div>
                    <div class="relative">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-500 mb-6">
                            <i class="fab fa-whatsapp text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">WhatsApp</h3>
                        <p class="text-gray-600 mb-6">Respuesta inmediata</p>
                        <a href="https://wa.me/573116035791" target="_blank" 
                           class="whatsapp-button inline-flex items-center justify-center space-x-3 bg-green-500 hover:bg-green-600 text-white font-medium py-3 px-6 rounded-xl transition-all duration-300 w-full relative group">
                            <i class="fab fa-whatsapp text-xl"></i>
                            <span>Chatear ahora</span>
                            <span class="whatsapp-tooltip absolute -top-12 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white text-sm py-2 px-4 rounded-lg opacity-0 transition-all duration-300 translate-y-2">
                                Abrir chat
                            </span>
                        </a>
                    </div>
                </div>

                <!-- Email Card -->
                <div class="bg-white rounded-2xl shadow-lg p-8 text-center transform hover:-translate-y-1 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-blue-50 rounded-full -mr-12 -mt-12"></div>
                    <div class="relative">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 text-blue-500 mb-6">
                            <i class="fas fa-envelope text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Email</h3>
                        <p class="text-gray-600 mb-6">soporte@numercia.com</p>
                        <a href="mailto:soporte@numercia.com" 
                           class="inline-flex items-center justify-center space-x-3 bg-blue-500 hover:bg-blue-600 text-white font-medium py-3 px-6 rounded-xl transition-all duration-300 w-full">
                            <i class="fas fa-paper-plane"></i>
                            <span>Enviar email</span>
                        </a>
                    </div>
                </div>

                <!-- Ubicación Card -->
                <div class="bg-white rounded-2xl shadow-lg p-8 text-center transform hover:-translate-y-1 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-purple-50 rounded-full -mr-12 -mt-12"></div>
                    <div class="relative">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-purple-100 text-purple-500 mb-6">
                            <i class="fas fa-map-marker-alt text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Ubicación</h3>
                        <p class="text-gray-600 mb-6">Popayán, Colombia</p>
                        <a href="https://maps.google.com/?q=Popayan,Colombia" target="_blank"
                           class="inline-flex items-center justify-center space-x-3 bg-purple-500 hover:bg-purple-600 text-white font-medium py-3 px-6 rounded-xl transition-all duration-300 w-full">
                            <i class="fas fa-map-marked-alt"></i>
                            <span>Ver en mapa</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sección de horario de atención -->
            <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 text-indigo-500 mb-6">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
                <h3 class="text-2xl font-semibold text-gray-900 mb-4">Horario de atención</h3>
                <div class="grid md:grid-cols-2 gap-6 max-w-2xl mx-auto">
                    <div class="space-y-2">
                        <p class="text-gray-900 font-medium">Lunes a Viernes</p>
                        <p class="text-gray-600">8:00 AM - 6:00 PM</p>
                    </div>
                    <div class="space-y-2">
                        <p class="text-gray-900 font-medium">Sábados</p>
                        <p class="text-gray-600">9:00 AM - 2:00 PM</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html> 