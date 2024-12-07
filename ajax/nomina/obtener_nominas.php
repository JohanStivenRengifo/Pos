<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['empresa_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

try {
    $empresa_id = $_SESSION['empresa_id'];
    
    // Obtener nóminas solo de empleados de la misma empresa
    $query = "SELECT 
                n.*,
                u.nombre as nombre_empleado,
                u.email,
                u.rol,
                (n.salario_base - n.deducciones + n.bonificaciones) as total
              FROM nominas n 
              JOIN users u ON n.empleado_id = u.id 
              WHERE u.empresa_id = :empresa_id 
              AND u.estado = 'activo'
              AND u.rol IN ('cajero', 'supervisor')
              ORDER BY n.periodo DESC, u.nombre ASC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([':empresa_id' => $empresa_id]);
    $nominas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear los números para la presentación
    foreach ($nominas as &$nomina) {
        $nomina['salario_base'] = number_format($nomina['salario_base'], 2);
        $nomina['deducciones'] = number_format($nomina['deducciones'], 2);
        $nomina['bonificaciones'] = number_format($nomina['bonificaciones'], 2);
        $nomina['total'] = number_format($nomina['total'], 2);
    }
    
    header('Content-Type: application/json');
    echo json_encode($nominas);
} catch(PDOException $e) {
    error_log("Error en obtener_nominas.php: " . $e->getMessage());
    error_log("SQL Query: " . $query);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Error en la base de datos: ' . $e->getMessage(),
        'debug' => [
            'sql_error' => $e->getMessage(),
            'query' => $query
        ]
    ]);
} 