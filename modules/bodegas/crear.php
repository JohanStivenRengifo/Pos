<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['status' => false, 'message' => ''];
    
    try {
        // Validar datos requeridos
        if (empty($_POST['nombre'])) {
            throw new Exception('El nombre de la bodega es requerido');
        }
        
        $nombre = trim($_POST['nombre']);
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        
        // Verificar si ya existe una bodega con el mismo nombre
        $stmt = $pdo->prepare("SELECT id FROM bodegas WHERE UPPER(nombre) = UPPER(?) AND usuario_id = ?");
        $stmt->execute([strtoupper($nombre), $user_id]);
        if ($stmt->fetch()) {
            throw new Exception('Ya existe una bodega con este nombre');
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        try {
            // Insertar nueva bodega
            $stmt = $pdo->prepare("
                INSERT INTO bodegas (
                    nombre, 
                    ubicacion, 
                    usuario_id, 
                    estado
                ) VALUES (?, ?, ?, 1)
            ");
            
            if (!$stmt->execute([$nombre, $ubicacion, $user_id])) {
                throw new Exception('Error al crear la bodega');
            }
            
            $bodega_id = $pdo->lastInsertId();
            
            // Confirmar transacción
            $pdo->commit();
            
            $response = [
                'status' => true,
                'message' => 'Bodega creada exitosamente',
                'id' => $bodega_id
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $response = [
            'status' => false,
            'message' => $e->getMessage()
        ];
    }
    
    echo json_encode($response);
    exit;
}

// Si no es POST, redirigir al índice
header("Location: index.php");
exit; 