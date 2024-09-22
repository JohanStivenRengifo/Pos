<?php
// Si no hay sesión iniciada, redirige a la página de inicio
if (!isset($_COOKIE['user_id'])) {
    header("Location: /login.php");
    exit;
}

// Recuperar el user_id desde la cookie
$user_id = (int)$_COOKIE['user_id'];

// Si el user_id no es válido, tratarlo como 'Invitado'
if ($user_id === 0) {
    $user_email = 'Invitado';
} else {
    // Si es necesario obtener el email u otra información del usuario desde la base de datos
    try {
        $pdo = getPDOConnection();
        $stmt = $pdo->prepare("SELECT email FROM Usuarios WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si se encuentra el usuario, usar su email
        if ($user) {
            $user_email = $user['email'];
        } else {
            // Si no se encuentra, tratar como 'Invitado'
            $user_email = 'Invitado';
        }
    } catch (Exception $e) {
        // Manejar cualquier error de conexión o consulta
        error_log("Error al obtener el email del usuario: " . $e->getMessage());
        $user_email = 'Invitado';
    }
}

// Ahora, $user_email tiene el email del usuario o 'Invitado'
?>

<header>
    <div class="logo">
        <h1>Sistema Contable</h1>
    </div>
    <div class="user-info">
        <span>Bienvenido, <?php echo htmlspecialchars($user_email); ?></span>
        <?php if (isset($_SESSION['email'])): ?>
            <a href="../../logout.php" class="logout">Cerrar Sesión</a>
        <?php else: ?>
            <a href="../../login.php" class="login">Iniciar Sesión</a>
        <?php endif; ?>
    </div>
</header>
