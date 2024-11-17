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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/auth.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h2>Crear cuenta nueva</h2>
            <p>Únete a VendEasy y empieza a gestionar tu negocio</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form id="registerForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label for="nombre">Nombre Completo</label>
                <div class="input-container">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="nombre" name="nombre" required 
                           minlength="3" maxlength="100"
                           placeholder="Ingresa tu nombre completo">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <div class="input-container">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" required 
                           autocomplete="email" spellcheck="false"
                           placeholder="ejemplo@correo.com">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-container password-container">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" required 
                           minlength="8"
                           placeholder="Mínimo 8 caracteres">
                    <button type="button" class="toggle-password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-progress"></div>
                    </div>
                    <small class="strength-text">La contraseña debe contener:</small>
                    <ul class="password-requirements">
                        <li data-requirement="length"><i class="fas fa-circle"></i> Mínimo 8 caracteres</li>
                        <li data-requirement="uppercase"><i class="fas fa-circle"></i> Una letra mayúscula</li>
                        <li data-requirement="lowercase"><i class="fas fa-circle"></i> Una letra minúscula</li>
                        <li data-requirement="number"><i class="fas fa-circle"></i> Un número</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña</label>
                <div class="input-container password-container">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           required minlength="8"
                           placeholder="Repite tu contraseña">
                    <button type="button" class="toggle-password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group checkbox-container">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">
                    Acepto los <a href="../../terminos-y-condiciones.php" class="btn-link">términos y condiciones</a>
                </label>
            </div>

            <button type="submit" class="btn-auth">
                <span>Crear Cuenta</span>
                <div class="spinner"></div>
            </button>
        </form>

        <div class="auth-footer">
            <p>¿Ya tienes una cuenta?</p>
            <a href="login.php" class="btn-link">Iniciar Sesión</a>
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

            submitButton.classList.add('loading');

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
                submitButton.classList.remove('loading');
            }
        });
    });
    </script>
</body>
</html>
