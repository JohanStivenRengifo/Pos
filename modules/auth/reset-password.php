<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Verificar token
$token = $_GET['token'] ?? '';
if (empty($token)) {
    header("Location: login.php");
    exit();
}

// Verificar si el token es válido y no ha expirado
$stmt = $pdo->prepare("
    SELECT pr.*, u.email 
    FROM password_resets pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.token = ? AND pr.used = 0 AND pr.expiry_date > NOW()
");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $_SESSION['error'] = "El enlace de recuperación no es válido o ha expirado.";
    header("Location: login.php");
    exit();
}

// Procesar cambio de contraseña
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Token de seguridad inválido');
        }

        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Validaciones
        if (empty($password) || empty($confirm_password)) {
            throw new Exception('Por favor complete todos los campos');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Las contraseñas no coinciden');
        }

        if (!validatePassword($password)) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número');
        }

        // Actualizar contraseña
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $pdo->beginTransaction();

        try {
            // Actualizar contraseña del usuario
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $reset['user_id']]);

            // Marcar token como usado
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $stmt->execute([$reset['id']]);

            $pdo->commit();

            if (isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => true,
                    'message' => 'Contraseña actualizada correctamente',
                    'data' => ['redirect' => 'login.php']
                ]);
                exit();
            } else {
                $_SESSION['success'] = "Tu contraseña ha sido actualizada. Por favor, inicia sesión.";
                header("Location: login.php");
                exit();
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new Exception('Error al actualizar la contraseña: ' . $e->getMessage());
        }

    } catch (Exception $e) {
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => false,
                'message' => $e->getMessage()
            ]);
            exit();
        } else {
            $error = $e->getMessage();
        }
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
    <meta name="description" content="Sistema de gestión empresarial VendEasy - Restablecimiento de contraseña">
    <title>VendEasy | Restablecer Contraseña</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 3px solid #fff;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .password-requirements li {
            transition: all 0.3s ease;
        }
        .password-requirements li.met {
            color: #059669;
        }
        .password-requirements li.met i {
            color: #059669;
        }
        .strength-progress {
            transition: all 0.3s ease;
            height: 4px;
            width: 0;
            background-color: #ef4444;
        }
        .strength-progress.weak { background-color: #ef4444; }
        .strength-progress.fair { background-color: #f59e0b; }
        .strength-progress.good { background-color: #10b981; }
        .strength-progress.strong { background-color: #059669; }
    </style>
</head>

<body class="min-h-screen flex flex-col md:flex-row bg-gray-50">
    <!-- Sección lateral con imagen y mensaje de bienvenida -->
    <div class="hidden lg:flex lg:w-1/2 bg-primary-600 text-white p-12 flex-col justify-between">
        <div>
            <h1 class="text-4xl font-bold mb-4">VendEasy</h1>
            <p class="text-primary-100">Sistema integral de gestión empresarial</p>
        </div>
        <div class="space-y-6">
            <h2 class="text-3xl font-bold">Restablece tu contraseña</h2>
            <p class="text-xl text-primary-100">Crea una nueva contraseña segura para tu cuenta</p>
            <div class="space-y-4">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-primary-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-shield-alt text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-medium">Contraseña segura</h3>
                        <p class="text-sm text-primary-100">Usa una combinación de letras, números y símbolos</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-primary-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-fingerprint text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-medium">Única</h3>
                        <p class="text-sm text-primary-100">No reutilices contraseñas de otras cuentas</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-primary-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-brain text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-medium">Memorable</h3>
                        <p class="text-sm text-primary-100">Fácil de recordar pero difícil de adivinar</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-sm text-primary-100">
            © <?= date('Y') ?> VendEasy. Todos los derechos reservados.
        </div>
    </div>

    <!-- Formulario de restablecimiento -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-12">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <div class="mx-auto w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-lock-open text-3xl text-primary-600"></i>
                </div>
                <h2 class="mt-2 text-3xl font-bold text-gray-900">Crear nueva contraseña</h2>
                <p class="mt-2 text-sm text-gray-600">Tu contraseña debe ser diferente a las anteriores</p>
            </div>

            <?php if (isset($error)): ?>
            <div class="p-4 bg-red-100 border border-red-200 text-red-700 rounded-lg flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form id="resetForm" method="POST" action="" class="mt-8 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="space-y-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Nueva Contraseña</label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="password" name="password" required 
                                   minlength="8"
                                   class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Mínimo 8 caracteres">
                            <button type="button" class="toggle-password absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2 space-y-2">
                            <div class="bg-gray-200 rounded-full h-1">
                                <div class="strength-progress rounded-full"></div>
                            </div>
                            <p class="text-xs text-gray-600">Fortaleza de la contraseña: <span class="strength-text font-medium">débil</span></p>
                            <ul class="password-requirements grid grid-cols-2 gap-2 text-xs text-gray-500">
                                <li data-requirement="length" class="flex items-center space-x-1">
                                    <i class="fas fa-circle text-xs"></i>
                                    <span>Mínimo 8 caracteres</span>
                                </li>
                                <li data-requirement="uppercase" class="flex items-center space-x-1">
                                    <i class="fas fa-circle text-xs"></i>
                                    <span>Una mayúscula</span>
                                </li>
                                <li data-requirement="lowercase" class="flex items-center space-x-1">
                                    <i class="fas fa-circle text-xs"></i>
                                    <span>Una minúscula</span>
                                </li>
                                <li data-requirement="number" class="flex items-center space-x-1">
                                    <i class="fas fa-circle text-xs"></i>
                                    <span>Un número</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirmar Nueva Contraseña</label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   required minlength="8"
                                   class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Repite tu contraseña">
                            <button type="button" class="toggle-password absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="password-match-text mt-1 text-sm hidden"></p>
                    </div>
                </div>

                <div class="bg-primary-50 p-4 rounded-lg border border-primary-100">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-lightbulb text-primary-600"></i>
                        </div>
                        <div class="text-sm text-primary-700">
                            <strong>Consejo:</strong> Una buena contraseña debe ser única y fácil de recordar para ti, 
                            pero difícil de adivinar para otros.
                        </div>
                    </div>
                </div>

                <button type="submit" 
                    class="group relative w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150 ease-in-out">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-check"></i>
                    </span>
                    <span class="mx-auto">Cambiar Contraseña</span>
                    <div class="spinner hidden absolute right-4 top-1/2 transform -translate-y-1/2"></div>
                </button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('resetForm');
        const submitButton = form.querySelector('button[type="submit"]');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthBar = document.querySelector('.strength-progress');
        const strengthText = document.querySelector('.strength-text');
        const requirementItems = document.querySelectorAll('.password-requirements li');
        const passwordMatchText = document.querySelector('.password-match-text');

        function updatePasswordStrength(password) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password)
            };

            // Actualizar indicadores visuales
            requirementItems.forEach(item => {
                const requirement = item.dataset.requirement;
                const icon = item.querySelector('i');
                if (requirements[requirement]) {
                    item.classList.add('met');
                    icon.className = 'fas fa-check-circle';
                } else {
                    item.classList.remove('met');
                    icon.className = 'fas fa-circle';
                }
            });

            // Calcular y mostrar fortaleza
            const strength = Object.values(requirements).filter(Boolean).length;
            const percentage = (strength / 4) * 100;
            
            strengthBar.style.width = `${percentage}%`;
            strengthBar.className = 'strength-progress rounded-full ' + 
                (percentage <= 25 ? 'weak' : 
                 percentage <= 50 ? 'fair' : 
                 percentage <= 75 ? 'good' : 'strong');

            strengthText.textContent = 
                percentage <= 25 ? 'débil' : 
                percentage <= 50 ? 'regular' : 
                percentage <= 75 ? 'buena' : 'fuerte';

            strengthText.className = 'strength-text font-medium ' +
                (percentage <= 25 ? 'text-red-600' : 
                 percentage <= 50 ? 'text-yellow-600' : 
                 percentage <= 75 ? 'text-green-600' : 'text-emerald-600');

            return requirements;
        }

        // Validación en tiempo real de la contraseña
        passwordInput.addEventListener('input', function() {
            const requirements = updatePasswordStrength(this.value);
            const isValid = Object.values(requirements).every(Boolean);
            this.setCustomValidity(isValid ? '' : 'La contraseña no cumple con los requisitos');
            
            if (confirmPasswordInput.value) {
                validatePasswordMatch();
            }
        });

        function validatePasswordMatch() {
            const isMatch = confirmPasswordInput.value === passwordInput.value;
            confirmPasswordInput.setCustomValidity(isMatch ? '' : 'Las contraseñas no coinciden');
            passwordMatchText.classList.remove('hidden');
            passwordMatchText.textContent = isMatch ? '✓ Las contraseñas coinciden' : '✗ Las contraseñas no coinciden';
            passwordMatchText.className = 'mt-1 text-sm ' + (isMatch ? 'text-green-600' : 'text-red-600');
        }

        confirmPasswordInput.addEventListener('input', validatePasswordMatch);

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

        // Form submission
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!form.checkValidity()) {
                return;
            }

            submitButton.disabled = true;
            submitButton.querySelector('.spinner').classList.remove('hidden');

            try {
                const formData = new FormData(this);
                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                
                if (data.status) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Contraseña actualizada!',
                        text: 'Tu contraseña ha sido cambiada exitosamente',
                        confirmButtonText: 'Iniciar sesión',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = data.data.redirect;
                    });
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Ocurrió un error al procesar la solicitud'
                });
            } finally {
                submitButton.disabled = false;
                submitButton.querySelector('.spinner').classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html> 