<?php
session_start();
require_once 'config/config.php';

// Procesar el formulario de contacto
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Token de seguridad inválido');
        }

        // Validar campos
        $nombre = filter_var(trim($_POST['nombre']), FILTER_SANITIZE_STRING);
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $asunto = filter_var(trim($_POST['asunto']), FILTER_SANITIZE_STRING);
        $mensaje = filter_var(trim($_POST['mensaje']), FILTER_SANITIZE_STRING);

        if (empty($nombre) || empty($email) || empty($asunto) || empty($mensaje)) {
            throw new Exception('Todos los campos son obligatorios');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo electrónico no es válido');
        }

        // Aquí iría la lógica para enviar el correo
        // Por ejemplo, usando PHPMailer o mail()

        $success = "Tu mensaje ha sido enviado. Te contactaremos pronto.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Generar CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto | VendEasy</title>
    <meta name="description" content="Contáctanos para cualquier duda o sugerencia sobre VendEasy">
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Estilos base del index.php */
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

        /* Header y navegación */
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

        /* Contenido principal */
        .main-content {
            padding: 8rem 2rem 4rem;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
        }

        .contact-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .contact-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .contact-header h1 {
            font-size: 2.5rem;
            color: var(--text-color);
            margin-bottom: 1rem;
        }

        .contact-header p {
            color: #6b7280;
            font-size: 1.1rem;
        }

        .contact-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .info-item {
            text-align: center;
        }

        .info-item i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .info-item h3 {
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .info-item p {
            color: #6b7280;
        }

        .contact-form {
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            font-family: inherit;
            font-size: 1rem;
        }

        .form-group textarea {
            height: 150px;
            resize: vertical;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        @media (max-width: 768px) {
            .contact-info {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .contact-header h1 {
                font-size: 2rem;
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
        </nav>
    </header>

    <main class="main-content">
        <div class="contact-container">
            <div class="contact-header">
                <h1>Contáctanos</h1>
                <p>¿Tienes alguna pregunta? Estamos aquí para ayudarte</p>
            </div>

            <div class="contact-info">
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <h3>Email</h3>
                    <p>soporte@vendeasy.com</p>
                </div>
                <div class="info-item">
                    <i class="fas fa-phone"></i>
                    <h3>Teléfono</h3>
                    <p>+57 3116035791</p>
                </div>
                <div class="info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Ubicación</h3>
                    <p>Popayan, Colombia</p>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form class="contact-form" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" required 
                           placeholder="Tu nombre">
                </div>

                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="tu@email.com">
                </div>

                <div class="form-group">
                    <label for="asunto">Asunto</label>
                    <input type="text" id="asunto" name="asunto" required 
                           placeholder="¿Sobre qué nos quieres contactar?">
                </div>

                <div class="form-group">
                    <label for="mensaje">Mensaje</label>
                    <textarea id="mensaje" name="mensaje" required 
                              placeholder="Escribe tu mensaje aquí..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i>
                    Enviar mensaje
                </button>
            </form>
        </div>
    </main>
</body>
</html> 