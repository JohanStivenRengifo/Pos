<?php
// Función para obtener el estado de suscripción
function obtenerEstadoSuscripcion($pdo, $userId) {
    $sql = "SELECT estado, fecha_desactivacion FROM users WHERE id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $usuario = $stmt->fetch();
    if (!$usuario) {
        return ["estado" => "desconocido", "mensaje" => "Usuario no encontrado"];
    }

    if ($usuario['estado'] === 'activo') {
        return ["estado" => "activo", "mensaje" => "La suscripción está activa."];
    } elseif ($usuario['estado'] === 'inactivo') {
        $fechaDesactivacion = $usuario['fecha_desactivacion'];
        return ["estado" => "inactivo", "mensaje" => "Suscripción desactivada desde: $fechaDesactivacion"];
    } else {
        return ["estado" => "eliminado", "mensaje" => "El usuario ha sido eliminado."];
    }
}

// Función para validar acceso a una página
function validarAcceso($pdo, $userId, $pagina) {
    $estadoSuscripcion = obtenerEstadoSuscripcion($pdo, $userId);

    if ($estadoSuscripcion['estado'] === 'activo') {
        return true; // Permitir acceso
    } elseif ($estadoSuscripcion['estado'] === 'inactivo') {
        return false; // Denegar acceso
    } else {
        return false; // Denegar acceso para estados eliminados o desconocidos
    }
}

?>
