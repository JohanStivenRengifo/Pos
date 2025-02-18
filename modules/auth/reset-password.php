<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Verificar token
function verifyResetToken($pdo, $token) {
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, u.email, u.nombre 
            FROM password_resets pr
            JOIN users u ON u.id = pr.user_id
            WHERE pr.token = ? 
            AND pr.used = 0 
            AND pr.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error verificando token: " . $e->getMessage());
        return false;
    }
}

// Función para validar contraseña
function validatePassword($password) {
    $pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/";
    return preg_match($pattern, $password);
}

$token = $_GET['token'] ?? '';
$tokenData = null;
$error = null;
$success = false;

if (empty($token)) {
    $error = "Token inválido o expirado.";
} else {
    $tokenData = verifyResetToken($pdo, $token);
    if (!$tokenData) {
        $error = "El enlace ha expirado o ya ha sido utilizado.";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $tokenData) {
    try {
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        if (!validatePassword($password)) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Las contraseñas no coinciden.');
        }

        // Actualizar contraseña
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if (!$stmt->execute([$hashed_password, $tokenData['user_id']])) {
            throw new Exception('Error al actualizar la contraseña.');
        }

        // Marcar token como usado
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $stmt->execute([$tokenData['id']]);

        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña | Numercia</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex flex-col md:flex-row bg-gray-50">
    <!-- Sección lateral -->
    <div class="hidden lg:flex lg:w-1/2 bg-indigo-600 text-white p-12 flex-col justify-between">
        <div>
            <h1 class="text-4xl font-bold mb-4">Numercia</h1>
            <p class="text-indigo-100">Sistema integral de gestión empresarial</p>
        </div>
        <div class="space-y-6">
            <h2 class="text-3xl font-bold">Restablece tu contraseña</h2>
            <p class="text-xl text-indigo-100">Crea una nueva contraseña segura para proteger tu cuenta.</p>
            <div class="space-y-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-shield-alt text-indigo-300"></i>
                    <span>Usa una contraseña única</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-check-circle text-indigo-300"></i>
                    <span>Combina letras, números y símbolos</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-lock text-indigo-300"></i>
                    <span>Mínimo 8 caracteres</span>
                </div>
            </div>
        </div>
        <div class="text-sm text-indigo-100">
            © <?= date('Y') ?> Numercia. Todos los derechos reservados.
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-12">
        <div class="w-full max-w-md space-y-8">
            <?php if ($success): ?>
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-5xl text-green-500"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">¡Contraseña actualizada!</h2>
                    <p class="text-gray-600 mb-6">Tu contraseña ha sido restablecida correctamente.</p>
                    <a href="login.php" 
                       class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Iniciar Sesión
                    </a>
                </div>
            <?php elseif ($error): ?>
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-circle text-5xl text-red-500"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Error</h2>
                    <p class="text-gray-600 mb-6"><?= htmlspecialchars($error) ?></p>
                    <a href="recuperar-password.php" 
                       class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-redo mr-2"></i>
                        Solicitar nuevo enlace
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-gray-900">Nueva Contraseña</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Hola <?= htmlspecialchars($tokenData['nombre']) ?>, ingresa tu nueva contraseña
                    </p>
                </div>

                <form method="POST" class="mt-8 space-y-6">
                    <div class="space-y-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Nueva Contraseña</label>
                            <div class="mt-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="password" name="password" required
                                       class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="Mínimo 8 caracteres">
                                <button type="button" class="toggle-password absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirmar Contraseña</label>
                            <div class="mt-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="confirm_password" name="confirm_password" required
                                       class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="Repite tu contraseña">
                                <button type="button" class="toggle-password absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-xs text-gray-600">La contraseña debe contener:</p>
                            <ul class="text-xs text-gray-500 space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-circle text-[0.5rem] mr-2"></i>
                                    Mínimo 8 caracteres
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-circle text-[0.5rem] mr-2"></i>
                                    Al menos una letra mayúscula
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-circle text-[0.5rem] mr-2"></i>
                                    Al menos una letra minúscula
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-circle text-[0.5rem] mr-2"></i>
                                    Al menos un número
                                </li>
                            </ul>
                        </div>
                    </div>

                    <button type="submit" 
                        class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Restablecer Contraseña
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    });
    </script>
</body>
</html> 