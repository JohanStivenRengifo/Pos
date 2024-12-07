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
    <meta name="description" content="Restablece tu contraseña de VendEasy">
    <title>Restablecer Contraseña | VendEasy</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/auth.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <i class="fas fa-lock-open auth-icon"></i>
            <h2>Crear nueva contraseña</h2>
            <p>Tu contraseña debe ser diferente a las anteriores</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form id="resetForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label for="password">Nueva Contraseña</label>
                <div class="password-container">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" required 
                           minlength="8"
                           placeholder="Mínimo 8 caracteres">
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-progress"></div>
                    </div>
                    <small class="strength-text">Fortaleza de la contraseña: <span>débil</span></small>
                    <ul class="password-requirements">
                        <li data-requirement="length">
                            <i class="fas fa-circle"></i> 
                            <span>Mínimo 8 caracteres</span>
                        </li>
                        <li data-requirement="uppercase">
                            <i class="fas fa-circle"></i> 
                            <span>Una letra mayúscula</span>
                        </li>
                        <li data-requirement="lowercase">
                            <i class="fas fa-circle"></i> 
                            <span>Una letra minúscula</span>
                        </li>
                        <li data-requirement="number">
                            <i class="fas fa-circle"></i> 
                            <span>Un número</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Nueva Contraseña</label>
                <div class="password-container">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           required minlength="8"
                           placeholder="Repite tu contraseña">
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small class="password-match-text">Las contraseñas coinciden</small>
            </div>

            <button type="submit" class="btn-auth">
                <span>Cambiar Contraseña</span>
                <div class="spinner"></div>
            </button>

            <div class="form-info">
                <i class="fas fa-shield-alt"></i>
                <p>Tu contraseña debe ser segura y fácil de recordar</p>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('resetForm');
        const submitButton = form.querySelector('button[type="submit"]');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthBar = document.querySelector('.strength-progress');
        const strengthText = document.querySelector('.strength-text span');
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
            strengthBar.className = 'strength-progress ' + 
                (percentage <= 25 ? 'weak' : 
                 percentage <= 50 ? 'fair' : 
                 percentage <= 75 ? 'good' : 'strong');

            strengthText.textContent = 
                percentage <= 25 ? 'débil' : 
                percentage <= 50 ? 'regular' : 
                percentage <= 75 ? 'buena' : 'fuerte';

            return requirements;
        }

        // Validación en tiempo real
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
            passwordMatchText.style.display = confirmPasswordInput.value ? 'block' : 'none';
            passwordMatchText.textContent = isMatch ? '✓ Las contraseñas coinciden' : '✗ Las contraseñas no coinciden';
            passwordMatchText.className = 'password-match-text ' + (isMatch ? 'match' : 'no-match');
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
                form.classList.add('was-validated');
                return;
            }

            submitButton.classList.add('loading');
            submitButton.disabled = true;

            try {
                const formData = new FormData(this);
                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }

                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    throw new Error('Error al procesar la respuesta del servidor');
                }
                
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
                    throw new Error(data.message || 'Error desconocido');
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Ocurrió un error al procesar la solicitud'
                });
            } finally {
                submitButton.classList.remove('loading');
                submitButton.disabled = false;
            }
        });
    });
    </script>
</body>
</html> 