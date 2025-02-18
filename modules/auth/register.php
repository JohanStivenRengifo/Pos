<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../config/mail.php';

// Clase para manejar respuestas JSON
class ApiResponse {
    public static function send($status, $message, $data = null) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// Función para validar contraseña
function validatePassword($password) {
    // Mínimo 8 caracteres, al menos una letra mayúscula, una minúscula y un número
    $pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/";
    return preg_match($pattern, $password);
}

// Función para registrar usuario
function registerUser($pdo, $email, $password, $nombre = '') {
    try {
        $pdo->beginTransaction();

        // Verificar si el email ya existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Este correo electrónico ya está registrado.');
        }

        // Hash de la contraseña
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insertar usuario con rol de administrador
        $stmt = $pdo->prepare("
            INSERT INTO users (
                email, 
                password, 
                nombre, 
                fecha_creacion,
                rol,
                estado,
                empresa_id,
                two_factor_enabled,
                fecha_desactivacion,
                remember_token,
                token_expires
            ) VALUES (
                ?, ?, ?, NOW(), 'administrador', 'activo', NULL, 0, NULL, NULL, NULL
            )
        ");
        $result = $stmt->execute([$email, $hashed_password, $nombre]);

        if (!$result) {
            throw new Exception('Error al registrar el usuario.');
        }

        $user_id = $pdo->lastInsertId();

        // Crear cliente Consumidor Final
        $stmt = $pdo->prepare("INSERT INTO clientes (
            user_id,
            nombre,
            email,
            telefono,
            created_at,
            tipo_identificacion,
            identificacion,
            primer_nombre,
            segundo_nombre,
            apellidos,
            municipio_departamento,
            codigo_postal
        ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $user_id,
            'Consumidor Final',
            'consumidorfinal@example.com',
            '0000000000',
            'CC', // Cédula de Ciudadanía
            '222222222', // Identificación genérica
            'Consumidor',
            '',
            'Final',
            'Bogotá D.C., Colombia',
            '110111' // Código postal de Bogotá
        ]);

        // Registrar el evento
        $stmt = $pdo->prepare("
            INSERT INTO user_events (
                user_id, 
                event_type, 
                event_data,
                created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        
        $event_data = json_encode([
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'action' => 'register',
            'email' => $email,
            'nombre' => $nombre
        ]);
        
        $stmt->execute([
            $user_id,
            'register',
            $event_data
        ]);

        $pdo->commit();
        
        // Enviar correo de bienvenida
        try {
            $mailer = new MailController();
            $mailer->sendWelcomeEmail($email, $nombre);
        } catch (Exception $e) {
            error_log("Error enviando correo de bienvenida: " . $e->getMessage());
            // No interrumpimos el registro si falla el envío del correo
        }
        
        // Iniciar sesión automáticamente después del registro
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['nombre'] = $nombre;
        $_SESSION['rol'] = 'administrador';
        
        return ['status' => true, 'message' => 'Registro exitoso'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

// Procesar solicitud de registro
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Token de seguridad inválido');
        }

        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $nombre = trim($_POST['nombre'] ?? '');
        
        // Validaciones
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Por favor ingrese un correo electrónico válido.');
        }

        if (!validatePassword($password)) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Las contraseñas no coinciden.');
        }

        $result = registerUser($pdo, $email, $password, $nombre);

        if (isAjaxRequest()) {
            ApiResponse::send($result['status'], $result['message'], 
                $result['status'] ? ['redirect' => '../empresa/setup.php'] : null);
        } else {
            if ($result['status']) {
                header("Location: ../empresa/setup.php");
                exit();
            } else {
                $error = $result['message'];
            }
        }
    } catch (Exception $e) {
        if (isAjaxRequest()) {
            ApiResponse::send(false, $e->getMessage());
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
    <meta name="description" content="Sistema de gestión empresarial Numercia - Registro de cuenta">
    <title>Numercia | Crear Cuenta</title>
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
            <h1 class="text-4xl font-bold mb-4">Numercia</h1>
            <p class="text-primary-100">Sistema integral de gestión empresarial</p>
        </div>
        <div class="space-y-6">
            <h2 class="text-3xl font-bold">Comienza tu viaje hacia el éxito empresarial</h2>
            <p class="text-xl text-primary-100">Todo lo que necesitas para gestionar tu negocio en un solo lugar</p>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-chart-line text-primary-300"></i>
                    <span>Control total</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-shield-alt text-primary-300"></i>
                    <span>100% Seguro</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-sync text-primary-300"></i>
                    <span>Tiempo real</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-mobile-alt text-primary-300"></i>
                    <span>Multiplataforma</span>
                </div>
            </div>
        </div>
        <div class="text-sm text-primary-100">
            © <?= date('Y') ?> Numercia. Todos los derechos reservados.
        </div>
    </div>

    <!-- Formulario de registro -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-12">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-bold text-gray-900">Crear cuenta nueva</h2>
                <p class="mt-2 text-sm text-gray-600">Únete a Numercia y empieza a gestionar tu negocio</p>
            </div>

            <?php if (isset($error)): ?>
            <div class="p-4 bg-red-100 border border-red-200 text-red-700 rounded-lg flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form id="registerForm" method="POST" action="" class="mt-8 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="space-y-4">
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre Completo</label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" id="nombre" name="nombre" required 
                                   minlength="3" maxlength="100"
                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Ingresa tu nombre completo">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" id="email" name="email" required 
                                   autocomplete="email" spellcheck="false"
                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="ejemplo@empresa.com">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
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
                            <p class="text-xs text-gray-600">La contraseña debe contener:</p>
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
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirmar Contraseña</label>
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
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="terms" name="terms" required
                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                        <label for="terms" class="ml-2 block text-sm text-gray-700">
                            Acepto los <a href="../../terminos-y-condiciones.php" class="text-primary-600 hover:text-primary-500">términos y condiciones</a>
                        </label>
                    </div>
                </div>

                <button type="submit" 
                    class="group relative w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150 ease-in-out">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-user-plus"></i>
                    </span>
                    <span class="mx-auto">Crear Cuenta</span>
                    <div class="spinner hidden absolute right-4 top-1/2 transform -translate-y-1/2"></div>
                </button>

                <div class="text-center">
                    <p class="text-sm text-gray-600">
                        ¿Ya tienes una cuenta?
                        <a href="login.php" class="font-medium text-primary-600 hover:text-primary-500">
                            Iniciar Sesión
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registerForm');
        const submitButton = form.querySelector('button[type="submit"]');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthBar = document.querySelector('.strength-progress');
        const requirementItems = document.querySelectorAll('.password-requirements li');

        // Función para verificar requisitos de contraseña
        function checkPasswordStrength(password) {
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

            // Calcular fortaleza
            const strength = Object.values(requirements).filter(Boolean).length;
            const percentage = (strength / 4) * 100;
            strengthBar.style.width = `${percentage}%`;
            strengthBar.className = 'strength-progress ' + 
                (percentage <= 25 ? 'weak' : 
                 percentage <= 50 ? 'fair' : 
                 percentage <= 75 ? 'good' : 'strong');

            return requirements;
        }

        // Validación en tiempo real de la contraseña
        passwordInput.addEventListener('input', function() {
            const requirements = checkPasswordStrength(this.value);
            const isValid = Object.values(requirements).every(Boolean);
            this.setCustomValidity(isValid ? '' : 'La contraseña no cumple con los requisitos');
        });

        // Validación en tiempo real de confirmación de contraseña
        confirmPasswordInput.addEventListener('input', function() {
            const isValid = this.value === passwordInput.value;
            this.setCustomValidity(isValid ? '' : 'Las contraseñas no coinciden');
        });

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

        // Manejar envío del formulario
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
                        title: '¡Registro exitoso!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
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