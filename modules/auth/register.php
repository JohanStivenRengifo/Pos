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

        // Insertar usuario
        $stmt = $pdo->prepare("INSERT INTO users (email, password, nombre, fecha_creacion) VALUES (?, ?, ?, NOW())");
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
                $result['status'] ? ['redirect' => '../../index.php'] : null);
        } else {
            if ($result['status']) {
                header("Location: ../../index.php");
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
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../css/login.css">
</head>
<body>
    <div class="register-container">
        <h2>Crear Cuenta</h2>
        <form id="registerForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label for="nombre">Nombre Completo</label>
                <input type="text" id="nombre" name="nombre" required 
                       minlength="3" maxlength="100">
            </div>

            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required 
                       autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required 
                           minlength="8">
                    <button type="button" class="toggle-password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small class="password-requirements">
                    Mínimo 8 caracteres, una mayúscula, una minúscula y un número
                </small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" 
                           required minlength="8">
                    <button type="button" class="toggle-password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-register">
                <span>Crear Cuenta</span>
                <div class="spinner"></div>
            </button>
        </form>

        <div class="login-link">
            <p>¿Ya tienes una cuenta?</p>
            <a href="../../index.php" class="btn-login">Iniciar Sesión</a>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registerForm');
        const submitButton = form.querySelector('button[type="submit"]');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        // Validación en tiempo real de la contraseña
        passwordInput.addEventListener('input', function() {
            const isValid = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(this.value);
            this.setCustomValidity(isValid ? '' : 'La contraseña no cumple con los requisitos');
        });

        // Validación en tiempo real de confirmación de contraseña
        confirmPasswordInput.addEventListener('input', function() {
            const isValid = this.value === passwordInput.value;
            this.setCustomValidity(isValid ? '' : 'Las contraseñas no coinciden');
        });

        // Manejar envío del formulario
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
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
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Ocurrió un error al procesar la solicitud'
                });
            } finally {
                submitButton.classList.remove('loading');
            }
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
    });
    </script>
</body>
</html>
