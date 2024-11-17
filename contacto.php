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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Header mejorado -->
    <header class="bg-white shadow-sm fixed w-full z-50">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex justify-between items-center">
                <a href="/" class="flex items-center space-x-2 text-blue-600 text-xl font-bold">
                    <i class="fas fa-calculator"></i>
                    <span>VendEasy</span>
                </a>
                <a href="/" class="text-gray-600 hover:text-blue-600 transition-colors">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </nav>
    </header>

    <main class="pt-20 pb-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <!-- Encabezado de la página -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Contáctanos</h1>
                <p class="text-lg text-gray-600">¿Tienes alguna pregunta? Estamos aquí para ayudarte</p>
            </div>

            <!-- Tarjetas de información de contacto -->
            <div class="grid md:grid-cols-3 gap-6 mb-12">
                <div class="bg-white rounded-xl shadow-sm p-6 text-center hover:shadow-md transition-shadow">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-600 mb-4">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Email</h3>
                    <p class="text-gray-600">soporte@vendeasy.com</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6 text-center hover:shadow-md transition-shadow">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-600 mb-4">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Teléfono</h3>
                    <p class="text-gray-600">+57 3116035791</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 text-center hover:shadow-md transition-shadow">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-600 mb-4">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Ubicación</h3>
                    <p class="text-gray-600">Popayan, Colombia</p>
                </div>
            </div>

            <!-- Alertas mejoradas -->
            <?php if (isset($success)): ?>
                <div class="mb-6 p-4 rounded-lg bg-green-50 border border-green-200">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <p class="text-green-700"><?= htmlspecialchars($success) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulario mejorado -->
            <div class="bg-white rounded-xl shadow-sm p-8">
                <form method="POST" action="" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre completo
                        </label>
                        <input type="text" id="nombre" name="nombre" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Tu nombre">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                            Correo electrónico
                        </label>
                        <input type="email" id="email" name="email" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="tu@email.com">
                    </div>

                    <div>
                        <label for="asunto" class="block text-sm font-medium text-gray-700 mb-1">
                            Asunto
                        </label>
                        <input type="text" id="asunto" name="asunto" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="¿Sobre qué nos quieres contactar?">
                    </div>

                    <div>
                        <label for="mensaje" class="block text-sm font-medium text-gray-700 mb-1">
                            Mensaje
                        </label>
                        <textarea id="mensaje" name="mensaje" required 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-32"
                                  placeholder="Escribe tu mensaje aquí..."></textarea>
                    </div>

                    <button type="submit" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center space-x-2">
                        <i class="fas fa-paper-plane"></i>
                        <span>Enviar mensaje</span>
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html> 