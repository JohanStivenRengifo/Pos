<?php
session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    // Verificar suscripción activa
    $stmt = $pdo->prepare("
        SELECT p.*, e.plan_suscripcion 
        FROM pagos p
        JOIN empresas e ON e.id = p.empresa_id
        WHERE p.empresa_id = ? 
        AND p.estado = 'completado'
        AND p.fecha_fin_plan >= NOW()
        ORDER BY p.fecha_pago DESC
        LIMIT 1
    ");
    
    $stmt->execute([$_SESSION['empresa_id']]);
    $suscripcion = $stmt->fetch();

    if (!$suscripcion) {
        // No tiene suscripción activa
        $_SESSION['error_message'] = "Tu suscripción ha expirado. Por favor, renueva tu plan para continuar.";
        header('Location: modules/empresa/planes.php');
        exit();
    }

    // Si tiene suscripción activa, redirigir al dashboard
    header('Location: welcome.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VendEasy - Sistema Contable Profesional para Empresas Colombianas</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="VendEasy es el sistema contable profesional líder en Colombia. Gestiona facturación electrónica, inventario y contabilidad de manera eficiente. Prueba gratuita por 14 días.">
    <meta name="keywords" content="sistema contable, facturación electrónica, contabilidad en línea, software contable, facturación Colombia, VendEasy">
    <meta name="author" content="VendEasy">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="VendEasy - Sistema Contable Profesional">
    <meta property="og:description" content="Sistema contable profesional para empresas colombianas. Gestiona tus finanzas de manera eficiente con la plataforma líder en el mercado.">
    <meta property="og:image" content="https://vendeasy.com/assets/img/og-image.jpg">
    <meta property="og:url" content="https://vendeasy.com">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="VendEasy - Sistema Contable Profesional">
    <meta name="twitter:description" content="Sistema contable profesional para empresas colombianas. Gestiona tus finanzas de manera eficiente.">
    <meta name="twitter:image" content="https://vendeasy.com/assets/img/twitter-card.jpg">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://vendeasy.com">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        secondary: '#1e40af',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in': 'fadeIn 1s ease-in',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'bounce-slow': 'bounce 3s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .hero-gradient {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        }
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>

<body class="font-sans antialiased text-gray-800 bg-white">
    <!-- Navbar -->
    <nav class="fixed w-full bg-white shadow-sm z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center space-x-2">
                        <i class="fas fa-calculator text-primary text-2xl"></i>
                        <span class="text-xl font-bold text-gray-900">VendEasy</span>
                    </a>
                </div>
                
                <div class="hidden md:flex md:items-center md:space-x-6">
                    <a href="#caracteristicas" class="text-gray-600 hover:text-gray-900">Características</a>
                    <a href="#precios" class="text-gray-600 hover:text-gray-900">Precios</a>
                    <a href="#nosotros" class="text-gray-600 hover:text-gray-900">Nosotros</a>
                    <a href="modules/auth/login.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-primary hover:text-primary-dark">
                        Ingresar
                    </a>
                    <a href="modules/auth/register.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-secondary transition-colors duration-200">
                        Comenzar Ahora
                    </a>
                </div>

                <div class="flex items-center md:hidden">
                    <button type="button" class="mobile-menu-button inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div class="hidden mobile-menu md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="#caracteristicas" class="block px-3 py-2 text-base font-medium text-gray-600 hover:text-gray-900">Características</a>
                <a href="#precios" class="block px-3 py-2 text-base font-medium text-gray-600 hover:text-gray-900">Precios</a>
                <a href="#nosotros" class="block px-3 py-2 text-base font-medium text-gray-600 hover:text-gray-900">Nosotros</a>
                <a href="modules/auth/login.php" class="block px-3 py-2 text-base font-medium text-primary hover:text-primary-dark">Ingresar</a>
                <a href="modules/auth/register.php" class="block px-3 py-2 text-base font-medium text-white bg-primary hover:bg-secondary rounded-md">Comenzar Ahora</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="min-h-screen flex items-center justify-center relative overflow-hidden bg-gradient-to-br from-gray-900 to-blue-900 text-white">
        <!-- Animated background elements -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute w-96 h-96 bg-blue-500 rounded-full filter blur-3xl opacity-20 -top-20 -left-20 floating"></div>
            <div class="absolute w-96 h-96 bg-purple-500 rounded-full filter blur-3xl opacity-20 -bottom-20 -right-20 floating" style="animation-delay: 1s;"></div>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="text-left animate-fade-in">
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight">
                        <span class="block mb-2">Sistema Contable</span>
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-purple-400">
                            Profesional y Confiable
                        </span>
                    </h1>
                    <p class="mt-6 text-xl text-gray-300 max-w-2xl animate-slide-up" style="animation-delay: 0.3s;">
                        Descubre una nueva forma de gestionar tu contabilidad y facturación. Una plataforma moderna y fresca, 
                        diseñada específicamente para empresas colombianas que buscan innovar.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-4 animate-slide-up" style="animation-delay: 0.6s;">
                        <a href="modules/auth/register.php" class="inline-flex items-center justify-center px-8 py-3 text-base font-medium rounded-lg text-white bg-gradient-to-r from-blue-500 to-blue-700 hover:from-blue-600 hover:to-blue-800 transform hover:scale-105 transition-all duration-200">
                            <span>Comienza ahora!</span>
                            <svg class="ml-2 -mr-1 w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="relative animate-fade-in" style="animation-delay: 0.6s;">
                    <div class="relative z-10 bg-white/10 backdrop-blur-lg rounded-2xl p-2 shadow-2xl transform hover:scale-105 transition-transform duration-300">
                        <img src="assets/img/dashboard-preview.png" alt="Dashboard VendEasy" class="rounded-xl shadow-lg" 
                             onerror="this.src='https://via.placeholder.com/600x400/2563eb/ffffff?text=Dashboard+VendEasy'"/>
                    </div>
                    <!-- Decorative elements -->
                    <div class="absolute -top-4 -right-4 w-72 h-72 bg-blue-500 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-bounce-slow"></div>
                    <div class="absolute -bottom-4 -left-4 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-bounce-slow" style="animation-delay: 1s;"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="caracteristicas" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                    Todo lo que necesitas para tu negocio
                </h2>
                <p class="mt-4 text-lg text-gray-500">
                    Funcionalidades diseñadas para hacer tu gestión financiera más eficiente
                </p>
            </div>

            <div class="mt-20 grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                <div class="relative p-6 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300">
                    <div class="text-primary text-3xl mb-4">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Facturación Electrónica</h3>
                    <p class="mt-2 text-gray-500">Cumple con la normativa vigente y emite facturas electrónicas de forma automática.</p>
                </div>

                <div class="relative p-6 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300">
                    <div class="text-primary text-3xl mb-4">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Reportes Avanzados</h3>
                    <p class="mt-2 text-gray-500">Visualiza el rendimiento de tu negocio con reportes detallados y personalizables.</p>
                </div>

                <div class="relative p-6 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300">
                    <div class="text-primary text-3xl mb-4">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Control de Inventario</h3>
                    <p class="mt-2 text-gray-500">Gestiona tu stock en tiempo real con alertas automáticas y múltiples bodegas.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="bg-gray-50 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white p-6 rounded-lg shadow-sm text-center">
                    <div class="text-4xl font-bold text-primary">10+</div>
                    <p class="mt-2 text-gray-600">Empresas Pioneras</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm text-center">
                    <div class="text-4xl font-bold text-primary">99.9%</div>
                    <p class="mt-2 text-gray-600">Disponibilidad</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm text-center">
                    <div class="text-4xl font-bold text-primary">24/7</div>
                    <p class="mt-2 text-gray-600">Soporte Técnico</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm text-center">
                    <div class="text-4xl font-bold text-primary">100%</div>
                    <p class="mt-2 text-gray-600">Satisfacción Garantizada</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="precios" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                    Planes diseñados para tu negocio
                </h2>
                <p class="mt-4 text-lg text-gray-500">
                    Elige el plan que mejor se adapte a tus necesidades
                </p>
            </div>

            <div class="mt-16 grid grid-cols-1 gap-6 lg:grid-cols-3 lg:gap-8">
                <!-- Plan Básico -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden transform hover:scale-105 transition-transform duration-300">
                    <div class="p-8">
                        <h3 class="text-xl font-semibold text-gray-900">Básico</h3>
                        <p class="mt-4 text-gray-500">Ideal para emprendedores y pequeños negocios</p>
                        <div class="mt-6">
                            <span class="text-4xl font-bold text-gray-900">$29.900</span>
                            <span class="text-gray-500">/mes</span>
                        </div>
                        <ul class="mt-8 space-y-4">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span>Hasta 100 facturas mensuales</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span>1 usuario</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span>Reportes básicos</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span>Soporte por email</span>
                            </li>
                        </ul>
                    </div>
                    <div class="px-8 pb-8">
                        <a href="modules/auth/register.php?plan=basic" class="block w-full bg-primary hover:bg-secondary text-white text-center py-3 rounded-lg transition-colors duration-200">
                            Comenzar ahora
                        </a>
                    </div>
                </div>

                <!-- Plan Profesional -->
                <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl shadow-xl overflow-hidden transform hover:scale-105 transition-transform duration-300">
                    <div class="absolute top-5 right-5">
                        <span class="bg-yellow-400 text-gray-900 text-xs font-bold px-3 py-1 rounded-full">MÁS POPULAR</span>
                    </div>
                    <div class="p-8">
                        <h3 class="text-xl font-semibold text-white">Profesional</h3>
                        <p class="mt-4 text-blue-100">Perfecto para negocios en crecimiento</p>
                        <div class="mt-6">
                            <span class="text-4xl font-bold text-white">$59.900</span>
                            <span class="text-blue-100">/mes</span>
                        </div>
                        <ul class="mt-8 space-y-4 text-white">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-3"></i>
                                <span>Hasta 500 facturas mensuales</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-3"></i>
                                <span>3 usuarios</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-3"></i>
                                <span>Reportes avanzados</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-3"></i>
                                <span>Soporte prioritario 24/7</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-3"></i>
                                <span>Control de inventario</span>
                            </li>
                        </ul>
                    </div>
                    <div class="px-8 pb-8">
                        <a href="modules/auth/register.php?plan=pro" class="block w-full bg-white text-primary hover:bg-gray-100 text-center py-3 rounded-lg transition-colors duration-200">
                            Comenzar ahora
                        </a>
                    </div>
                </div>

                <!-- Plan Empresarial -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden transform hover:scale-105 transition-transform duration-300">
                    <div class="p-8">
                        <h3 class="text-xl font-semibold text-gray-900">Empresarial</h3>
                        <p class="mt-4 text-gray-500">Para grandes empresas y corporaciones</p>
                        <div class="mt-6">
                            <span class="text-4xl font-bold text-gray-900">$119.900</span>
                            <span class="text-gray-500">/mes</span>
                        </div>
                        <ul class="mt-8 space-y-4">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span>Facturas ilimitadas</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span>Usuarios ilimitados</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span>API personalizada</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span>Soporte VIP</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span>Personalización completa</span>
                            </li>
                        </ul>
                    </div>
                    <div class="px-8 pb-8">
                        <a href="modules/auth/register.php?plan=enterprise" class="block w-full bg-primary hover:bg-secondary text-white text-center py-3 rounded-lg transition-colors duration-200">
                            Contactar ventas
                        </a>
                    </div>
                </div>
            </div>

            <!-- FAQs de precios -->
            <div class="mt-16 text-center">
                <p class="text-gray-500">
                    ¿Tienes preguntas sobre nuestros planes? 
                    <a href="#" class="text-primary hover:text-secondary">Consulta nuestras FAQs</a> 
                    o <a href="#" class="text-primary hover:text-secondary">contáctanos</a>
                </p>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="nosotros" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                    Sobre VendEasy
                </h2>
                <p class="mt-4 text-lg text-gray-500 max-w-3xl mx-auto">
                    Somos una innovadora plataforma de soluciones contables diseñada específicamente para empresas colombianas. 
                    Nuestra misión es revolucionar la gestión financiera de tu negocio con tecnología de última generación y un enfoque centrado en el usuario.
                </p>
            </div>

            <div class="mt-20 grid grid-cols-1 gap-8 md:grid-cols-3">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center h-12 w-12 rounded-md bg-primary text-white mb-4">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Seguridad Garantizada</h3>
                    <p class="mt-2 text-gray-500">
                        Tecnología de punta con certificaciones en proceso
                    </p>
                </div>

                <div class="text-center">
                    <div class="inline-flex items-center justify-center h-12 w-12 rounded-md bg-primary text-white mb-4">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Soporte Premium</h3>
                    <p class="mt-2 text-gray-500">
                        Equipo de soporte local comprometido
                    </p>
                </div>

                <div class="text-center">
                    <div class="inline-flex items-center justify-center h-12 w-12 rounded-md bg-primary text-white mb-4">
                        <i class="fas fa-sync"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Innovación Constante</h3>
                    <p class="mt-2 text-gray-500">
                        Desarrollo ágil con mejoras semanales
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Sección Logo y Descripción -->
            <div class="space-y-4">
                <h3 class="text-xl font-bold mb-4 text-blue-400">VendEasy</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Sistema contable profesional para empresas modernas que buscan optimizar sus procesos financieros.
                </p>
            </div>

            <!-- Enlaces Rápidos -->
            <div>
                <h3 class="text-lg font-semibold mb-4 text-blue-400">Enlaces</h3>
                <ul class="space-y-3">
                    <li><a href="#caracteristicas" class="text-gray-400 hover:text-white transition-colors duration-300 flex items-center"><i class="fas fa-chevron-right mr-2 text-xs"></i>Características</a></li>
                    <li><a href="#precios" class="text-gray-400 hover:text-white transition-colors duration-300 flex items-center"><i class="fas fa-chevron-right mr-2 text-xs"></i>Precios</a></li>
                    <li><a href="#nosotros" class="text-gray-400 hover:text-white transition-colors duration-300 flex items-center"><i class="fas fa-chevron-right mr-2 text-xs"></i>Nosotros</a></li>
                </ul>
            </div>

            <!-- Legal -->
            <div>
                <h3 class="text-lg font-semibold mb-4 text-blue-400">Legal</h3>
                <ul class="space-y-3">
                    <li><a href="terminos-y-condiciones.php" class="text-gray-400 hover:text-white transition-colors duration-300 flex items-center"><i class="fas fa-chevron-right mr-2 text-xs"></i>Términos y Condiciones</a></li>
                    <li><a href="politica-privacidad.php" class="text-gray-400 hover:text-white transition-colors duration-300 flex items-center"><i class="fas fa-chevron-right mr-2 text-xs"></i>Política de Privacidad</a></li>
                </ul>
            </div>

            <!-- Contacto -->
            <div>
                <h3 class="text-lg font-semibold mb-4 text-blue-400">Contacto</h3>
                <ul class="space-y-3">
                    <li class="flex items-center text-gray-400 hover:text-white transition-colors duration-300">
                        <i class="fas fa-envelope mr-3 text-blue-400"></i>
                        <a href="mailto:soporte@vendeasy.com">soporte@vendeasy.com</a>
                    </li>
                    <li class="flex items-center text-gray-400 hover:text-white transition-colors duration-300">
                        <i class="fas fa-phone mr-3 text-blue-400"></i>
                        <a href="tel:+573116035791">+57 311 603 5791</a>
                    </li>
                    <li class="flex items-center text-gray-400 hover:text-white transition-colors duration-300">
                        <i class="fab fa-whatsapp mr-3 text-blue-400"></i>
                        <a href="https://wa.me/573116035791" target="_blank" class="hover:text-green-400">
                            Chatea con nosotros
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Copyright -->
        <div class="mt-12 pt-8 border-t border-gray-800">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm">
                    © <?php echo date('Y'); ?> VendEasy. Todos los derechos reservados.
                </p>
                <div class="mt-4 md:mt-0">
                    <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">
                        Diseñado con <i class="fas fa-heart text-red-500 mx-1"></i> por <a href="https://github.com/JohanRengifo">Johan Rengifo</a>
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>


    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.querySelector('.mobile-menu-button');
            const mobileMenu = document.querySelector('.mobile-menu');

            menuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
                const icon = menuButton.querySelector('i');
                if (mobileMenu.classList.contains('hidden')) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                } else {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                }
            });

            // Close menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!menuButton.contains(event.target) && !mobileMenu.contains(event.target)) {
                    mobileMenu.classList.add('hidden');
                    const icon = menuButton.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });

            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                        // Close mobile menu if open
                        mobileMenu.classList.add('hidden');
                    }
                });
            });
        });
    </script>
</body>
</html>