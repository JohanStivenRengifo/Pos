<?php

// Función para manejar el login
function handleLogin($email, $password) {
    global $error;
    
    try {
        $pdo = getPDOConnection();
        
        // Consulta para obtener el usuario y la empresa
        $stmt = $pdo->prepare('
            SELECT u.user_id, u.password, u.business_id, u.full_name, e.name AS company_name 
            FROM users u 
            JOIN Empresas e ON u.business_id = e.company_id 
            WHERE u.email = ?
        ');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verificar si la consulta trajo un usuario y su empresa
        if ($user && verifyPassword($password, $user['password'])) {
            if (isset($user['business_id'])) {
                // Iniciar sesión del usuario
                $_SESSION['user'] = [
                    'id' => $user['user_id'],
                    'email' => $email,
                    'full_name' => $user['full_name'],
                    'company_id' => $user['business_id'],
                    'company_name' => $user['company_name']
                ];

                // Redirigir al usuario
                header('Location: /home/index.php');
                exit;
            } else {
                $error = "El usuario no tiene una empresa asociada.";
            }
        } else {
            $error = "Credenciales inválidas.";
        }
    } catch (Exception $e) {
        $error = "Error en el inicio de sesión: " . htmlspecialchars($e->getMessage());
    }
}


?>