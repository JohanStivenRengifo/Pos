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
    <title>VendEasy - Sistema Contable Inteligente</title>
    <meta name="description" content="Sistema contable inteligente para pequeñas y medianas empresas. Gestiona tus finanzas de manera fácil y eficiente.">
    <link rel="icon" type="image/png" href="/favicon/favicon.ico" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #3b82f6;
            --text-color: #1f2937;
            --light-bg: #f3f4f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            overflow-x: hidden;
        }

        .header {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
            position: fixed;
            width: 100%;
            z-index: 100;
        }

        .nav {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-link {
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: var(--primary-color);
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-outline {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .hero {
            padding: 8rem 2rem 4rem;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
            padding: 2rem 0;
        }

        .hero-title {
            font-size: 3.5rem;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            max-width: 800px;
            margin: 0 auto 2rem;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: #4b5563;
            max-width: 600px;
            margin: 0 auto 3rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 4rem;
        }

        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            text-align: left;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .stats-section {
            background: white;
            padding: 4rem 2rem;
        }

        .stats-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            text-align: center;
        }

        .stat-item h3 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .floating-element {
            position: absolute;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 50%;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .nav-links {
                display: none;
            }
        }

        .section-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: var(--text-color);
        }

        .features-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-block {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .feature-block-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .feature-block ul {
            list-style: none;
            margin-top: 1rem;
        }

        .feature-block li {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .feature-block li:before {
            content: "•";
            color: var(--primary-color);
            position: absolute;
            left: 0;
        }

        .pricing-section {
            background: var(--light-bg);
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .pricing-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            position: relative;
            text-align: center;
        }

        .pricing-card.featured {
            transform: scale(1.05);
            border: 2px solid var(--primary-color);
        }

        .popular-tag {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 1rem;
            border-radius: 1rem;
            font-size: 0.875rem;
        }

        .price {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 1.5rem 0;
        }

        .price span {
            font-size: 1rem;
            color: #666;
        }

        .about-section {
            background: white;
        }

        .about-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .about-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .about-feature {
            text-align: center;
        }

        .about-feature i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .pricing-card.featured {
                transform: none;
            }
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .stat-detail {
            font-size: 0.875rem;
            color: #666;
            margin-top: 0.5rem;
            text-align: center;
        }

        .demo-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 4rem 0;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .demo-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .demo-img {
            width: 100%;
            height: auto;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .demo-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .metric {
            background: white;
            padding: 1rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .metric i {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .demo-grid {
                grid-template-columns: 1fr;
            }
            
            .demo-metrics {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <nav class="nav">
            <a href="/" class="logo">
                <i class="fas fa-calculator"></i> VendEasy
            </a>
            <div class="nav-links">
                <a href="#features" class="nav-link">Características</a>
                <a href="#pricing" class="nav-link">Precios</a>
                <a href="#about" class="nav-link">Nosotros</a>
            </div>
            <div class="nav-buttons">
                <a href="modules/auth/login.php" class="btn btn-outline">
                    <i class="fas fa-sign-in-alt"></i> Ingresar
                </a>
                <a href="modules/auth/register.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Prueba Gratis
                </a>
            </div>
        </nav>
    </header>

    <main class="hero">
        <div class="floating-elements">
            <div class="floating-element" style="width: 300px; height: 300px; top: 10%; left: -150px;"></div>
            <div class="floating-element" style="width: 200px; height: 200px; top: 60%; right: -100px;"></div>
        </div>

        <div class="hero-container">
            <h1 class="hero-title">Contabilidad inteligente</h1>
            <p class="hero-subtitle">La forma más inteligente de gestionar tu contabilidad, facturación e inventario. Todo en un solo lugar. Ahora en versión BETA completamente gratuita.</p>

            <a href="modules/auth/register.php" class="btn btn-primary">
                Comienza Gratis
                <i class="fas fa-arrow-right"></i>
            </a>
            <p style="margin-top: 1rem; font-size: 0.875rem; color: #4b5563;">
                Al registrarte aceptas nuestros <a href="terminos-y-condiciones.php" style="color: var(--primary-color); text-decoration: underline;">Términos y Condiciones</a>
            </p>

            <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #4b5563;">
                Desarrollado con <i class="fas fa-heart" style="color: #e11d48;"></i> por
                <a href="https://github.com/JR-Solutions-Col"
                    target="_blank"
                    rel="noopener noreferrer"
                    style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                    JR-Solutions
                </a>
            </p>

            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-cloud feature-icon"></i>
                    <h3>100% en la Nube</h3>
                    <p>Accede desde cualquier dispositivo, sin necesidad de instalaciones</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-receipt feature-icon"></i>
                    <h3>Facturación Simple</h3>
                    <p>Genera facturas profesionales de manera rápida y sencilla</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-chart-bar feature-icon"></i>
                    <h3>Reportes en Tiempo Real</h3>
                    <p>Toma decisiones informadas con datos actualizados al instante</p>
                </div>
            </div>
        </div>
    </main>

    <section class="stats-section">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h3>Beta</h3>
                <p>Versión de lanzamiento</p>
                <div class="stat-detail">Acceso anticipado a todas las funciones</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-server"></i>
                </div>
                <h3>99.9%</h3>
                <p>Tiempo de actividad</p>
                <div class="stat-detail">Servidores redundantes de alta disponibilidad</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h3>24/7</h3>
                <p>Soporte técnico</p>
                <div class="stat-detail">Asistencia personalizada vía chat y email</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-infinity"></i>
                </div>
                <h3>Ilimitado</h3>
                <p>Sin restricciones</p>
                <div class="stat-detail">Usuarios, productos y transacciones sin límite</div>
            </div>
        </div>
    </section>

    <section class="demo-section">
        <div class="section-container">
            <h2 class="section-title">Conoce la plataforma en acción</h2>
            <div class="demo-grid">
                <div class="demo-card">
                    <img src="assets/img/dashboard-preview.png" alt="Dashboard" class="demo-img">
                    <h3>Dashboard Intuitivo</h3>
                    <p>Visualiza tus métricas clave en tiempo real</p>
                </div>
                <div class="demo-stats">
                    <div class="chart-container">
                        <canvas id="ventasChart"></canvas>
                    </div>
                    <div class="demo-metrics">
                        <div class="metric">
                            <i class="fas fa-chart-line"></i>
                            <span>Análisis de ventas</span>
                        </div>
                        <div class="metric">
                            <i class="fas fa-database"></i>
                            <span>Backup automático diario</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="features-section">
        <div class="section-container">
            <h2 class="section-title">Características que impulsan tu negocio</h2>
            <div class="features-container">
                <div class="feature-block">
                    <i class="fas fa-file-invoice feature-block-icon"></i>
                    <h3>Sistema de Facturación</h3>
                    <ul>
                        <li>Facturación sencilla</li>
                        <li>Envío por email</li>
                    </ul>
                </div>
                <div class="feature-block">
                    <i class="fas fa-boxes feature-block-icon"></i>
                    <h3>Control de Inventario</h3>
                    <ul>
                        <li>Gestión de stock en tiempo real</li>
                        <li>Múltiples bodegas</li>
                        <li>Códigos de barras</li>
                    </ul>
                </div>
                <div class="feature-block">
                    <i class="fas fa-chart-pie feature-block-icon"></i>
                    <h3>Reportes Financieros</h3>
                    <ul>
                        <li>Balance general</li>
                        <li>Estado de resultados</li>
                        <li>Flujo de caja</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="pricing" class="pricing-section">
        <div class="section-container">
            <h2 class="section-title">Versión Beta - ¡Gratis!</h2>
            <div class="pricing-grid">
                <div class="pricing-card featured" style="transform: none; max-width: 600px; margin: 0 auto;">
                    <div class="popular-tag">Beta</div>
                    <h3>Plan Completo</h3>
                    <div class="price">$0<span>/mes</span></div>
                    <p style="margin: 1rem 0; color: #666;">Durante el período de lanzamiento, accede a todas las funcionalidades sin costo</p>
                    <ul>
                        <li><i class="fas fa-check"></i> Usuarios ilimitados</li>
                        <li><i class="fas fa-check"></i> Sistema de facturación completo</li>
                        <li><i class="fas fa-check"></i> Control de inventario</li>
                        <li><i class="fas fa-check"></i> Reportes financieros</li>
                        <li><i class="fas fa-check"></i> Soporte técnico</li>
                        <li><i class="fas fa-check"></i> Actualizaciones gratuitas</li>
                    </ul>
                    <a href="modules/auth/register.php" class="btn btn-primary">Comenzar ahora</a>
                    <p style="margin-top: 1rem; font-size: 0.875rem; color: #666;">* Los precios se anunciarán al finalizar el período beta</p>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="about-section">
        <div class="section-container">
            <h2 class="section-title">Sobre VendEasy</h2>
            <div class="about-content">
                <div class="about-text">
                    <p>VendEasy nace de la necesidad de ofrecer una solución contable moderna y accesible para las PyMEs en Colombia. Nuestro compromiso es simplificar la gestión financiera de tu negocio con tecnología de punta.</p>
                    <div class="about-features">
                        <div class="about-feature">
                            <i class="fas fa-shield-alt"></i>
                            <h4>Seguridad Garantizada</h4>
                            <p>Datos encriptados y respaldos automáticos</p>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-headset"></i>
                            <h4>Soporte Local</h4>
                            <p>Equipo de soporte en Colombia</p>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-sync"></i>
                            <h4>Actualizaciones Constantes</h4>
                            <p>Mejoras continuas sin costo adicional</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer style="background: var(--light-bg); padding: 2rem; text-align: center; margin-top: 2rem;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <div style="margin-bottom: 1rem;">
                <a href="terminos-y-condiciones.php"
                    style="color: var(--text-color); text-decoration: none; font-size: 0.875rem; margin: 0 0.5rem;">
                    Términos y Condiciones
                </a>
                <span style="color: #666;">|</span>
                <a href="politica-privacidad.php"
                    style="color: var(--text-color); text-decoration: none; font-size: 0.875rem; margin: 0 0.5rem;">
                    Política de Privacidad
                </a>
            </div>
            <p style="color: var(--text-color); font-size: 0.875rem;">
                Desarrollado con <i class="fas fa-heart" style="color: #e11d48;"></i> por
                <a href="https://github.com/JR-Solutions-Col"
                    target="_blank"
                    rel="noopener noreferrer"
                    style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                    JR-Solutions
                </a>
            </p>
            <p style="color: #666; font-size: 0.75rem; margin-top: 0.5rem;">
                © <?php echo date('Y'); ?> VendEasy. Todos los derechos reservados.
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Configuración del gráfico de ventas
        const ctx = document.getElementById('ventasChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
                datasets: [{
                    label: 'Ventas Mensuales',
                    data: [12, 19, 15, 25, 22, 30],
                    borderColor: '#2563eb',
                    tension: 0.4,
                    fill: true,
                    backgroundColor: 'rgba(37, 99, 235, 0.1)'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
    </script>
</body>

</html>