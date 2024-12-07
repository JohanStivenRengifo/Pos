<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['empresa_id'])) {
    error_log("Error: Usuario no autenticado. user_id: " . isset($_SESSION['user_id']) . ", empresa_id: " . isset($_SESSION['empresa_id']));
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

try {
    $empresa_id = $_SESSION['empresa_id'];
    error_log("Buscando empleados para empresa_id: " . $empresa_id);
    
    // Primero, verificar si hay usuarios en la tabla
    $queryTotal = "SELECT COUNT(*) as total FROM users";
    $stmtTotal = $pdo->query($queryTotal);
    $totalUsers = $stmtTotal->fetch()['total'];
    error_log("Total de usuarios en la base de datos: " . $totalUsers);

    // Verificar usuarios por empresa
    $queryEmpresa = "SELECT COUNT(*) as total FROM users WHERE empresa_id = :empresa_id";
    $stmtEmpresa = $pdo->prepare($queryEmpresa);
    $stmtEmpresa->execute([':empresa_id' => $empresa_id]);
    $totalEmpresa = $stmtEmpresa->fetch()['total'];
    error_log("Total de usuarios en la empresa " . $empresa_id . ": " . $totalEmpresa);

    // Consultar empleados activos (cajeros y supervisores)
    $query = "SELECT 
                u.id,
                u.nombre,
                u.email,
                u.rol,
                u.estado,
                u.empresa_id
              FROM users u
              WHERE u.empresa_id = :empresa_id 
              AND u.rol IN ('cajero', 'supervisor') 
              AND u.estado = 'activo'
              ORDER BY u.nombre ASC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([':empresa_id' => $empresa_id]);
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Empleados encontrados: " . count($empleados));
    error_log("Query ejecutado: " . str_replace(':empresa_id', $empresa_id, $query));
    
    if (count($empleados) > 0) {
        error_log("Primer empleado encontrado: " . json_encode($empleados[0]));
    } else {
        // Verificar roles disponibles
        $queryRoles = "SELECT DISTINCT rol FROM users WHERE empresa_id = :empresa_id";
        $stmtRoles = $pdo->prepare($queryRoles);
        $stmtRoles->execute([':empresa_id' => $empresa_id]);
        $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);
        error_log("Roles disponibles en la empresa: " . implode(', ', $roles));
        
        // Verificar estados
        $queryEstados = "SELECT DISTINCT estado FROM users WHERE empresa_id = :empresa_id";
        $stmtEstados = $pdo->prepare($queryEstados);
        $stmtEstados->execute([':empresa_id' => $empresa_id]);
        $estados = $stmtEstados->fetchAll(PDO::FETCH_COLUMN);
        error_log("Estados disponibles en la empresa: " . implode(', ', $estados));
    }
    
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
                'empresa_id' => $_SESSION['empresa_id']
            ]
        ]
    ]);
} catch(PDOException $e) {
    error_log("Error en obtener_empleados.php: " . $e->getMessage());
    error_log("SQL Query: " . $query);
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