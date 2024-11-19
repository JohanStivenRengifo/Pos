<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

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
                estado
            ) VALUES (
                ?, ?, ?, NOW(), 'administrador', 'activo'
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

        // Registrar el evento con created_at
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
    <title>Registro | VendEasy</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Crear cuenta nueva</h2>
            <p class="text-gray-600">Únete a VendEasy y empieza a gestionar tu negocio</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="mb-6 p-4 rounded-lg bg-red-50 text-red-700 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"></path>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form id="registerForm" method="POST" action="" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div>
                <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">
                    Nombre Completo
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <input type="text" id="nombre" name="nombre" required 
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           minlength="3" maxlength="100"
                           placeholder="Ingresa tu nombre completo">
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    Correo Electrónico
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <input type="email" id="email" name="email" required 
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           autocomplete="email" spellcheck="false"
                           placeholder="ejemplo@correo.com">
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    Contraseña
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input type="password" id="password" name="password" required 
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           minlength="8"
                           placeholder="Mínimo 8 caracteres">
                    <button type="button" 
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 transition-colors"
                            aria-label="Toggle password visibility">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                <div class="mt-2 space-y-2">
                    <div class="h-1.5 w-full bg-gray-200 rounded-full overflow-hidden">
                        <div class="strength-progress h-full transition-all duration-300"></div>
                    </div>
                    <p class="text-sm text-gray-600">La contraseña debe contener:</p>
                    <ul class="space-y-1 text-sm text-gray-600">
                        <li data-requirement="length" class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"></svg>
                            Mínimo 8 caracteres
                        </li>
                        <li data-requirement="uppercase" class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"></svg>
                            Una letra mayúscula
                        </li>
                        <li data-requirement="lowercase" class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"></svg>
                            Una letra minúscula
                        </li>
                        <li data-requirement="number" class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"></svg>
                            Un número
                        </li>
                    </ul>
                </div>
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                    Confirmar Contraseña
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           minlength="8"
                           placeholder="Repite tu contraseña">
                    <button type="button" 
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 transition-colors"
                            aria-label="Toggle password visibility">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex items-center">
                <input type="checkbox" id="terms" name="terms" required
                       class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="terms" class="ml-2 block text-sm text-gray-700">
                    Acepto los <a href="../../terminos-y-condiciones.php" class="text-blue-600 hover:text-blue-800 font-medium">términos y condiciones</a>
                </label>
            </div>

            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors relative">
                <span>Crear Cuenta</span>
                <div class="absolute right-4 top-1/2 -translate-y-1/2 hidden spinner">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">¿Ya tienes una cuenta?
                <a href="login.php" class="text-blue-600 hover:text-blue-800 font-medium transition-colors">
                    Iniciar Sesión
                </a>
            </p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registerForm');
        const submitButton = form.querySelector('button[type="submit"]');
        const spinner = submitButton.querySelector('.spinner');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthProgress = document.querySelector('.strength-progress');
        const requirementItems = document.querySelectorAll('[data-requirement]');

        // Función para actualizar el ícono de requisito
        function updateRequirementIcon(element, met) {
            const svg = element.querySelector('svg');
            svg.innerHTML = met ? 
                '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path>' :
                '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"></path>';
            
            element.classList.toggle('text-green-600', met);
            element.classList.toggle('text-gray-400', !met);
        }

        // Función para verificar requisitos de contraseña
        function checkPasswordStrength(password) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password)
            };

            requirementItems.forEach(item => {
                const requirement = item.dataset.requirement;
                updateRequirementIcon(item, requirements[requirement]);
            });

            const strength = Object.values(requirements).filter(Boolean).length;
            const percentage = (strength / 4) * 100;
            
            strengthProgress.style.width = `${percentage}%`;
            strengthProgress.className = `strength-progress h-full transition-all duration-300 ${
                percentage <= 25 ? 'bg-red-500' : 
                percentage <= 50 ? 'bg-yellow-500' : 
                percentage <= 75 ? 'bg-blue-500' : 'bg-green-500'
            }`;

            return requirements;
        }

        // Validación en tiempo real de la contraseña
        passwordInput.addEventListener('input', function() {
            const requirements = checkPasswordStrength(this.value);
            const isValid = Object.values(requirements).every(Boolean);
            this.setCustomValidity(isValid ? '' : 'La contraseña no cumple con los requisitos');
            
            // Validar confirmación si ya hay valor
            if (confirmPasswordInput.value) {
                confirmPasswordInput.dispatchEvent(new Event('input'));
            }
        });

        // Validación en tiempo real de confirmación de contraseña
        confirmPasswordInput.addEventListener('input', function() {
            const isValid = this.value === passwordInput.value;
            this.setCustomValidity(isValid ? '' : 'Las contraseñas no coinciden');
        });

        // Toggle password visibility
        document.querySelectorAll('button[aria-label="Toggle password visibility"]').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.querySelector('svg').classList.toggle('opacity-50');
            });
        });

        // Manejar envío del formulario
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!form.checkValidity()) {
                return;
            }

            submitButton.disabled = true;
            spinner.classList.remove('hidden');

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
                spinner.classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html>
