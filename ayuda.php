<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/db.php';

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Ayuda - Sistema Contable</title>
    <link rel="icon" type="image/png" href="favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/welcome.css">
</head>

<body class="bg-gray-50">
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-body">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Centro de Ayuda - Sistema Contable</h2>

                <!-- Sección de Búsqueda -->
                <div class="mb-8">
                    <div class="relative">
                        <input type="text" 
                               placeholder="Buscar ayuda..." 
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <button class="absolute right-3 top-3 text-gray-400">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <!-- Preguntas Frecuentes -->
                <div class="bg-white rounded-xl shadow-sm mb-8 overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-6">
                            Preguntas Frecuentes sobre Contabilidad
                        </h3>
                        
                        <div class="space-y-4">
                            <!-- Pregunta 1 -->
                            <div class="border-b border-gray-200 pb-4">
                                <button class="faq-question w-full flex justify-between items-center text-left">
                                    <span class="text-lg font-medium text-gray-900">¿Cómo visualizar los reportes?</span>
                                    <i class="fas fa-chevron-down text-gray-500 transition-transform duration-200"></i>
                                </button>
                                <div class="faq-answer mt-4 hidden">
                                    <div class="prose prose-blue max-w-none">
                                        <ol class="list-decimal pl-4 space-y-2 text-gray-600">
                                            <li>Accede al módulo "Reportes"</li>
                                            <li>El reporte se cargará en la pantalla</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>

                            <!-- Más preguntas... -->
                        </div>
                    </div>
                </div>

                <!-- Guías y Tutoriales -->
                <div class="grid md:grid-cols-2 gap-6 mb-8">
                    <!-- Videos Tutoriales -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-6">
                            Próximamente
                        </h3>
                        
                        <div class="space-y-4">
                            <div class="group hover:bg-gray-50 p-4 rounded-lg transition duration-200">
                                <h4 class="text-lg font-medium text-gray-900 mb-2">
                                    <i class="fas fa-play-circle text-blue-500 mr-2"></i>
                                    Nuevos tutoriales en camino
                                </h4>
                                <p class="text-gray-600">Estamos trabajando en nuevos tutoriales para ti.</p>
                                <button class="mt-3 text-blue-600 hover:text-blue-700 font-medium">
                                    ¡Mantente atento! <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                            
                            <!-- Más videos... -->
                        </div>
                    </div>

                    <!-- Tips Contables -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-6">
                            Tips Contables
                        </h3>
                        
                        <div class="space-y-4">
                            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                                <ul class="space-y-2 text-gray-600">
                                    <li class="flex items-center">
                                        <i class="fas fa-check text-blue-500 mr-2"></i>
                                        Mantén al día los registros contables
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check text-blue-500 mr-2"></i>
                                        Realiza conciliaciones bancarias mensualmente
                                    </li>
                                    <!-- Más tips... -->
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nota Importante -->
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg mb-8">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-yellow-400 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-yellow-800">Nota Importante</h3>
                            <p class="text-yellow-700 mt-2">
                                Recuerda que los respaldos periódicos de tu información contable se realizan de forma automatica por <b>VendEasy</b>.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Contacto Soporte -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-8 text-center">
                    <h3 class="text-2xl font-bold text-white mb-4">¿Necesitas Soporte Contable?</h3>
                    <p class="text-blue-100 mb-6">Nuestro equipo de contadores está disponible para resolver tus dudas</p>
                    <a href="./contacto.php" 
                       class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-blue-600 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                        Contactar Soporte Contable
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Script mejorado para el acordeón
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;
                const icon = question.querySelector('i');
                const isActive = question.classList.contains('active');
                
                // Cerrar todas las respuestas
                document.querySelectorAll('.faq-question').forEach(q => {
                    q.classList.remove('active');
                    q.nextElementSibling.classList.add('hidden');
                    q.querySelector('i').classList.remove('rotate-180');
                });

                if (!isActive) {
                    question.classList.add('active');
                    answer.classList.remove('hidden');
                    icon.classList.add('rotate-180');
                }
            });
        });
    </script>
</body>
</html> 