<?php
// Iniciar la sesión
session_start();

// Verificar si el usuario está autenticado en sesión o cookies
if (!isset($_SESSION['user_id']) && !isset($_COOKIE['user_id'])) {
    // Redirigir al login si no está autenticado con un JSON de error
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'No autenticado',
        'message' => 'La sesión ha expirado o no está autenticado.'
    ]);
    exit();
}

// Si no está en sesión, cargar los datos desde las cookies
if (isset($_COOKIE['user_id']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_COOKIE['user_id'];
    $_SESSION['empresa_id'] = $_COOKIE['empresa_id'];
    $_SESSION['user_email'] = $_COOKIE['user_email'] ?? null;
    $_SESSION['user_nombre'] = $_COOKIE['user_nombre'] ?? null;
    error_log("Datos cargados desde cookies: user_id: {$_COOKIE['user_id']}, empresa_id: {$_COOKIE['empresa_id']}");
}

// Verificar si el usuario está autenticado en sesión
if (!isset($_SESSION['user_id'])) {
    // Redirigir al login si no está autenticado con un JSON de error
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'No autenticado',
        'message' => 'La sesión ha expirado o no está autenticado.'
    ]);
    exit();
}

// Incluir la conexión a la base de datos
require_once '../../config/db.php';

try {
    // Obtener el ID de la empresa desde la sesión
    $empresa_id = $_SESSION['empresa_id'];
    error_log("Buscando empleados para empresa_id: " . $empresa_id);
    
    // Obtener el total de usuarios en la base de datos
    $queryTotal = "SELECT COUNT(*) as total FROM users";
    $stmtTotal = $pdo->query($queryTotal);
    $totalUsers = $stmtTotal->fetch()['total'];
    error_log("Total de usuarios en la base de datos: " . $totalUsers);

    // Obtener el total de usuarios de la empresa específica
    $queryEmpresa = "SELECT COUNT(*) as total FROM users WHERE empresa_id = :empresa_id";
    $stmtEmpresa = $pdo->prepare($queryEmpresa);
    $stmtEmpresa->execute([':empresa_id' => $empresa_id]);
    $totalEmpresa = $stmtEmpresa->fetch()['total'];
    error_log("Total de usuarios en la empresa " . $empresa_id . ": " . $totalEmpresa);

    // Consultar los empleados activos (cajeros y supervisores) junto con la empresa asociada
    $query = "SELECT 
                u.id,
                u.nombre,
                u.email,
                u.rol,
                u.estado AS estado_usuario,
                e.nombre_empresa,
                e.estado AS estado_empresa
              FROM users u
              JOIN empresas e ON u.empresa_id = e.id
              WHERE u.empresa_id = :empresa_id 
              AND u.rol IN ('cajero', 'supervisor') 
              AND u.estado = 'activo'
              AND e.estado = 1
              ORDER BY u.nombre ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':empresa_id' => $empresa_id]);
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Empleados encontrados: " . count($empleados));
    
    // Si no se encuentran empleados, verificar roles y estados
    if (count($empleados) === 0) {
        // Verificar roles y estados de empleados
        $queryRoles = "SELECT DISTINCT rol FROM users WHERE empresa_id = :empresa_id";
        $stmtRoles = $pdo->prepare($queryRoles);
        $stmtRoles->execute([':empresa_id' => $empresa_id]);
        $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);
        error_log("Roles disponibles en la empresa: " . implode(', ', $roles));
        
        // Verificar estados de los empleados
        $queryEstados = "SELECT DISTINCT estado FROM users WHERE empresa_id = :empresa_id";
        $stmtEstados = $pdo->prepare($queryEstados);
        $stmtEstados->execute([':empresa_id' => $empresa_id]);
        $estados = $stmtEstados->fetchAll(PDO::FETCH_COLUMN);
        error_log("Estados disponibles en la empresa: " . implode(', ', $estados));
    }

    // Responder con los datos de los empleados
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $empleados,
        'debug' => [
            'empresa_id' => $empresa_id,
            'total_usuarios' => $totalUsers,
            'total_empresa' => $totalEmpresa,
            'total_empleados' => count($empleados),
            'session_info' => [
                'user_id' => $_SESSION['user_id'],
                'empresa_id' => $_SESSION['empresa_id'],
                'user_email' => $_SESSION['user_email'] ?? null,
                'user_nombre' => $_SESSION['user_nombre'] ?? null
            ]
        ]
    ]);
} catch (PDOException $e) {
    // Manejo de errores en la base de datos
    error_log("Error en obtener_empleados.php: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Error en la base de datos',
        'message' => $e->getMessage(),
        'debug' => [
            'empresa_id' => $empresa_id ?? 'no definido',
            'sql_error' => $e->getMessage(),
            'sql_query' => $query
        ]
    ]);
}
?>
