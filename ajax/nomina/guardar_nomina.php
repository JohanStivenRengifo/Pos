<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['empresa_id'])) {
            throw new Exception('Usuario no autenticado');
        }

        error_log("Iniciando guardado de nómina");
        error_log("POST data: " . print_r($_POST, true));

        $empresa_id = $_SESSION['empresa_id'];
        
        // Validar datos requeridos
        $required_fields = ['empleado_id', 'periodo', 'salario_base', 'deducciones'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Campo requerido faltante: {$field}");
            }
        }

        // Verificar que el empleado pertenezca a la empresa
        $query = "SELECT id FROM users 
                 WHERE id = :empleado_id 
                 AND empresa_id = :empresa_id 
                 AND rol IN ('cajero', 'supervisor')
                 AND estado = 'activo'";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':empleado_id' => $_POST['empleado_id'],
            ':empresa_id' => $empresa_id
        ]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Empleado no válido o no autorizado');
        }

        // Procesar y validar datos
        $empleado_id = intval($_POST['empleado_id']);
        $periodo = $_POST['periodo'];
        $salario_base = floatval($_POST['salario_base']);
        $deducciones = floatval($_POST['deducciones']);
        $bonificaciones = 0.00; // Valor por defecto según la estructura
        $fecha_pago = date('Y-m-d');

        // Validar formato del período (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            throw new Exception('Formato de período inválido. Use YYYY-MM');
        }

        // Verificar si ya existe una nómina para este empleado en este período
        $query = "SELECT id FROM nominas 
                 WHERE empleado_id = :empleado_id 
                 AND periodo = :periodo";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':empleado_id' => $empleado_id,
            ':periodo' => $periodo
        ]);
        
        if ($stmt->fetch()) {
            throw new Exception('Ya existe una nómina para este empleado en este período');
        }

        // Query de inserción (created_at y updated_at se manejan automáticamente)
        $query = "INSERT INTO nominas (
                    empleado_id, 
                    periodo, 
                    salario_base, 
                    deducciones, 
                    bonificaciones,
                    fecha_pago
                ) VALUES (
                    :empleado_id, 
                    :periodo, 
                    :salario_base, 
                    :deducciones, 
                    :bonificaciones,
                    :fecha_pago
                )";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':empleado_id' => $empleado_id,
            ':periodo' => $periodo,
            ':salario_base' => $salario_base,
            ':deducciones' => $deducciones,
            ':bonificaciones' => $bonificaciones,
            ':fecha_pago' => $fecha_pago
        ]);

        if (!$result) {
            throw new Exception('Error al ejecutar la inserción');
        }

        $lastId = $pdo->lastInsertId();
        error_log("Nómina guardada exitosamente con ID: " . $lastId);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Nómina guardada correctamente',
            'id' => $lastId
        ]);

    } catch (Exception $e) {
        error_log("Error en guardar_nomina.php: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage(),
            'debug' => [
                'post_data' => $_POST,
                'session' => [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'empresa_id' => $_SESSION['empresa_id'] ?? null
                ]
            ]
        ]);
    }
} 