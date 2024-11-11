<?php
session_start();

// Si el usuario ya está autenticado, redirigir al dashboard
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
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
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
        }

        .header {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.5rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
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
            display: flex;
            align-items: center;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            color: var(--text-color);
        }

        .hero-text p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            color: #4b5563;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .feature-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .feature-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .hero-image img {
            width: 100%;
            height: auto;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .features {
                grid-template-columns: 1fr;
            }

            .hero-text h1 {
                font-size: 2.5rem;
            }

            .hero-image {
                order: -1;
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
            <div class="nav-buttons">
                <a href="modules/auth/login.php" class="btn btn-outline">Ingresar</a>
                <a href="modules/auth/register.php" class="btn btn-primary">Registrarme</a>
            </div>
        </nav>
    </header>

    <main class="hero">
        <div class="hero-content">
            <div class="hero-text">
                <h1>Gestiona tu negocio de manera inteligente</h1>
                <p>Sistema contable diseñado para hacer tu vida más fácil. Automatiza tus procesos, toma mejores decisiones y haz crecer tu negocio.</p>
                <a href="modules/auth/register.php" class="btn btn-primary">
                    Comenzar ahora
                    <i class="fas fa-arrow-right"></i>
                </a>

                <div class="features">
                    <div class="feature-card">
                        <i class="fas fa-chart-line feature-icon"></i>
                        <h3>Reportes en tiempo real</h3>
                        <p>Visualiza el rendimiento de tu negocio al instante</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-receipt feature-icon"></i>
                        <h3>Facturación fácil</h3>
                        <p>Genera facturas profesionales en segundos</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-lock feature-icon"></i>
                        <h3>Seguridad avanzada</h3>
                        <p>Tus datos siempre protegidos</p>
                    </div>
                </div>
            </div>
            <div class="hero-image">
                <img src="assets/img/dashboard-preview.png" alt="Dashboard VendEasy" 
                     onerror="this.src='https://via.placeholder.com/600x400?text=Dashboard+Preview'">
            </div>
        </div>
    </main>
</body>
</html>

