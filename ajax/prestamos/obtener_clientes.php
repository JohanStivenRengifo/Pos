<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    error_log("Error: Usuario no autenticado en obtener_clientes.php");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    error_log("Buscando clientes para user_id: " . $user_id);
    
    // Primero, verificar si hay clientes en la tabla
    $queryTotal = "SELECT COUNT(*) as total FROM clientes";
    $result = $conn->query($queryTotal);
    $totalClientes = $result->fetch_assoc()['total'];
    error_log("Total de clientes en la base de datos: " . $totalClientes);

    // Verificar clientes por usuario
    $queryUser = "SELECT COUNT(*) as total FROM clientes WHERE user_id = ?";
    $stmt = $conn->prepare($queryUser);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $totalUser = $stmt->get_result()->fetch_assoc()['total'];
    error_log("Total de clientes del usuario " . $user_id . ": " . $totalUser);

    // Consultar clientes activos
    $query = "SELECT 
                id,
                CONCAT(COALESCE(primer_nombre, ''), ' ', 
                      COALESCE(segundo_nombre, ''), ' ',
                      COALESCE(apellidos, '')) as nombre,
                email,
                telefono,
                celular,
                identificacion,
                foto_perfil
              FROM clientes 
              WHERE user_id = ? 
              ORDER BY primer_nombre ASC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clientes = [];
    while ($row = $result->fetch_assoc()) {
        // Limpiar espacios múltiples en el nombre
        $row['nombre'] = trim(preg_replace('/\s+/', ' ', $row['nombre']));
        $clientes[] = $row;
    }
    
    error_log("Clientes encontrados: " . count($clientes));
    
    if (count($clientes) > 0) {
        error_log("Primer cliente encontrado: " . json_encode($clientes[0]));
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $clientes,
        'debug' => [
            'user_id' => $user_id,
            'total_clientes' => $totalClientes,
            'total_user' => $totalUser,
            'total_encontrados' => count($clientes)
        ]
    ]);

} catch(Exception $e) {
    error_log("Error en obtener_clientes.php: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error en la base de datos',
        'message' => $e->getMessage(),
        'debug' => [
            'user_id' => $user_id ?? 'no definido',
            'sql_error' => $e->getMessage()
        ]
    ]);
} 