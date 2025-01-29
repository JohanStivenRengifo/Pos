<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: welcome.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VendEasy - Sistema Contable Profesional</title>
    <meta name="description" content="Sistema contable profesional para empresas. Gestiona tus finanzas de manera eficiente con la plataforma líder en el mercado.">
    <link rel="icon" type="image/png" href="/favicon/favicon.ico" />
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
                }
            }
        }
    </script>
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
    <section class="pt-20 pb-32 bg-gradient-to-b from-blue-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20">
            <div class="text-center">
                <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                    <span class="block">Sistema Contable</span>
                    <span class="block text-primary">Profesional y Confiable</span>
                </h1>
                <p class="mt-3 max-w-md mx-auto text-base text-gray-500 sm:text-lg md:mt-5 md:text-xl md:max-w-3xl">
                    La solución completa para la gestión financiera de tu empresa. Miles de negocios confían en nosotros para su contabilidad diaria.
                </p>
                <div class="mt-5 max-w-md mx-auto sm:flex sm:justify-center md:mt-8">
                    <div class="rounded-md shadow">
                        <a href="modules/auth/register.php" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-primary hover:bg-secondary md:py-4 md:text-lg md:px-10 transition-colors duration-200">
                            Comenzar Ahora
                        </a>
                    </div>
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
                    <div class="text-4xl font-bold text-primary">100+</div>
                    <p class="mt-2 text-gray-600">Empresas Activas</p>
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
                    <div class="text-4xl font-bold text-primary">1M+</div>
                    <p class="mt-2 text-gray-600">Facturas Emitidas</p>
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

            <div class="mt-16 grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- Plan Básico -->
                <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300 p-8">
                    <h3 class="text-xl font-semibold text-gray-900">Básico</h3>
                    <p class="mt-4 text-gray-500">Ideal para emprendedores</p>
                    <p class="mt-8">
                        <span class="text-4xl font-extrabold text-gray-900">$3</span>
                        <span class="text-base font-medium text-gray-500">/mes</span>
                    </p>
                    <ul class="mt-8 space-y-4">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Hasta 100 facturas mensuales</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>1 Usuario</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Soporte por email</span>
                        </li>
                    </ul>
                    <a href="modules/auth/register.php" class="mt-8 block w-full bg-primary text-white text-center py-3 rounded-md hover:bg-secondary transition-colors duration-200">
                        Comenzar
                    </a>
                </div>

                <!-- Plan Profesional -->
                <div class="bg-white rounded-lg shadow-lg border-2 border-primary p-8 transform scale-105">
                    <div class="absolute top-0 right-0 bg-primary text-white px-4 py-1 rounded-bl-lg rounded-tr-lg text-sm font-medium">
                        Más Popular
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Profesional</h3>
                    <p class="mt-4 text-gray-500">Para pequeñas empresas</p>
                    <p class="mt-8">
                        <span class="text-4xl font-extrabold text-gray-900">$6</span>
                        <span class="text-base font-medium text-gray-500">/mes</span>
                    </p>
                    <ul class="mt-8 space-y-4">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Facturas ilimitadas</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>5 Usuarios</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Soporte prioritario 24/7</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Reportes avanzados</span>
                        </li>
                    </ul>
                    <a href="modules/auth/register.php" class="mt-8 block w-full bg-primary text-white text-center py-3 rounded-md hover:bg-secondary transition-colors duration-200">
                        Comenzar
                    </a>
                </div>

                <!-- Plan Empresarial -->
                <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300 p-8">
                    <h3 class="text-xl font-semibold text-gray-900">Empresarial</h3>
                    <p class="mt-4 text-gray-500">Para medianas y grandes empresas</p>
                    <p class="mt-8">
                        <span class="text-4xl font-extrabold text-gray-900">$9</span>
                        <span class="text-base font-medium text-gray-500">/mes</span>
                    </p>
                    <ul class="mt-8 space-y-4">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Todo lo de Profesional</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Usuarios ilimitados</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Facturas ilimitadas</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Sin Limites</span>
                        </li>
                    </ul>
                    <a href="modules/auth/register.php" class="mt-8 block w-full bg-primary text-white text-center py-3 rounded-md hover:bg-secondary transition-colors duration-200">
                        Contactar Ventas
                    </a>
                </div>
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
                    Somos la plataforma líder en soluciones contables para empresas en Latinoamérica. 
                    Nuestra misión es simplificar la gestión financiera de tu negocio con tecnología de última generación.
                </p>
            </div>

            <div class="mt-20 grid grid-cols-1 gap-8 md:grid-cols-3">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center h-12 w-12 rounded-md bg-primary text-white mb-4">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Seguridad Garantizada</h3>
                    <p class="mt-2 text-gray-500">
                        Certificación ISO 27001 y cumplimiento GDPR
                    </p>
                </div>

                <div class="text-center">
                    <div class="inline-flex items-center justify-center h-12 w-12 rounded-md bg-primary text-white mb-4">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Soporte Premium</h3>
                    <p class="mt-2 text-gray-500">
                        Equipo de soporte local 24/7
                    </p>
                </div>

                <div class="text-center">
                    <div class="inline-flex items-center justify-center h-12 w-12 rounded-md bg-primary text-white mb-4">
                        <i class="fas fa-sync"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Innovación Constante</h3>
                    <p class="mt-2 text-gray-500">
                        Actualizaciones mensuales con nuevas funciones
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4">VendEasy</h3>
                    <p class="text-gray-400 text-sm">
                        Sistema contable profesional para empresas modernas.
                    </p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Enlaces</h3>
                    <ul class="space-y-2">
                        <li><a href="#caracteristicas" class="text-gray-400 hover:text-white transition-colors">Características</a></li>
                        <li><a href="#precios" class="text-gray-400 hover:text-white transition-colors">Precios</a></li>
                        <li><a href="#nosotros" class="text-gray-400 hover:text-white transition-colors">Nosotros</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Legal</h3>
                    <ul class="space-y-2">
                        <li><a href="terminos-y-condiciones.php" class="text-gray-400 hover:text-white transition-colors">Términos y Condiciones</a></li>
                        <li><a href="politica-privacidad.php" class="text-gray-400 hover:text-white transition-colors">Política de Privacidad</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contacto</h3>
                    <ul class="space-y-2">
                        <li class="text-gray-400">soporte@vendeasy.com</li>
                        <li class="text-gray-400">+57 (1) 123-4567</li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 pt-8 border-t border-gray-800 text-center">
                <p class="text-gray-400 text-sm">
                    © <?php echo date('Y'); ?> VendEasy. Todos los derechos reservados.
                </p>
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