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

<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>
    
    <div class="flex">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="w-full p-4 md:p-6 lg:p-8 ml-[250px]">
            <div class="max-w-7xl mx-auto">
                <!-- Encabezado -->
                <div class="mb-6">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                        Centro de Ayuda
                    </h1>
                    <p class="text-gray-600 mt-2">Encuentra respuestas a tus preguntas y recursos útiles</p>
                </div>

                <!-- Barra de búsqueda -->
                <div class="mb-8">
                    <div class="relative">
                        <input type="text" 
                               placeholder="Buscar en la ayuda..." 
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition duration-200">
                        <button class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <!-- Grid de contenido principal -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Preguntas Frecuentes -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                            <div class="p-6">
                                <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                                    <i class="fas fa-question-circle text-blue-500 mr-2"></i>
                                    Preguntas Frecuentes
                                </h2>
                                
                                <div class="space-y-4">
                                    <!-- Pregunta 1 -->
                                    <div class="border-b border-gray-200 pb-4 last:border-0">
                                        <button class="faq-question w-full flex justify-between items-center text-left hover:bg-gray-50 p-3 rounded-lg transition-all duration-200">
                                            <span class="text-lg font-medium text-gray-900">¿Cómo visualizar los reportes?</span>
                                            <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300"></i>
                                        </button>
                                        <div class="faq-answer hidden mt-4 px-3">
                                            <div class="prose prose-blue max-w-none text-gray-600">
                                                <ol class="list-decimal pl-4 space-y-2">
                                                    <li>Accede al módulo "Reportes" desde el menú lateral</li>
                                                    <li>Selecciona el tipo de reporte que deseas generar</li>
                                                    <li>Configura los filtros según tus necesidades</li>
                                                    <li>Haz clic en "Generar Reporte"</li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pregunta 2 -->
                                    <div class="border-b border-gray-200 pb-4 last:border-0">
                                        <button class="faq-question w-full flex justify-between items-center text-left hover:bg-gray-50 p-3 rounded-lg transition-all duration-200">
                                            <span class="text-lg font-medium text-gray-900">¿Cómo realizar un respaldo manual?</span>
                                            <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300"></i>
                                        </button>
                                        <div class="faq-answer hidden mt-4 px-3">
                                            <div class="prose prose-blue max-w-none text-gray-600">
                                                <p>Los respaldos se realizan automáticamente, pero si necesitas uno manual:</p>
                                                <ol class="list-decimal pl-4 space-y-2 mt-2">
                                                    <li>Ve a Configuración > Respaldos</li>
                                                    <li>Haz clic en "Generar Respaldo Manual"</li>
                                                    <li>Espera a que el proceso termine</li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar de Ayuda Rápida -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Videos Tutoriales -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-play-circle text-blue-500 mr-2"></i>
                                Tutoriales
                            </h3>
                            
                            <div class="space-y-4">
                                <div class="group hover:bg-gray-50 p-4 rounded-lg transition-all duration-200 cursor-pointer">
                                    <h4 class="text-md font-medium text-gray-900">Nuevos tutoriales</h4>
                                    <p class="text-sm text-gray-600 mt-1">Próximamente nuevos videos explicativos.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tips Rápidos -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-lightbulb text-blue-500 mr-2"></i>
                                Tips Útiles
                            </h3>
                            
                            <div class="space-y-3">
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                                    <p class="text-sm text-gray-600">Mantén actualizados tus registros diariamente</p>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                                    <p class="text-sm text-gray-600">Revisa las conciliaciones bancarias mensualmente</p>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                                    <p class="text-sm text-gray-600">Verifica los reportes de inventario semanalmente</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contacto Soporte -->
                <div class="mt-8">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl shadow-lg p-6 md:p-8">
                        <div class="max-w-3xl mx-auto text-center">
                            <h3 class="text-2xl font-bold text-white mb-4">¿Necesitas ayuda adicional?</h3>
                            <p class="text-blue-100 mb-6">Nuestro equipo de soporte está disponible para ayudarte</p>
                            <a href="./contacto.php" 
                               class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                Contactar Soporte
                                <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mejorado el script del acordeón
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;
                const icon = question.querySelector('i');
                
                // Toggle de la pregunta actual
                const isExpanding = answer.classList.contains('hidden');
                
                // Animación suave para el ícono
                if (isExpanding) {
                    icon.style.transform = 'rotate(180deg)';
                    question.classList.add('bg-gray-50');
                } else {
                    icon.style.transform = 'rotate(0deg)';
                    question.classList.remove('bg-gray-50');
                }
                
                // Toggle del contenido
                answer.classList.toggle('hidden');
                
                // Animación de altura
                if (isExpanding) {
                    answer.style.maxHeight = '0';
                    setTimeout(() => {
                        answer.style.maxHeight = answer.scrollHeight + 'px';
                    }, 0);
                } else {
                    answer.style.maxHeight = '0';
                }
            });
        });
    </script>
</body>
</html> 