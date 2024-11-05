<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];

function obtenerCategorias() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, nombre FROM categorias WHERE estado = 'activo' ORDER BY nombre ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerDepartamentos() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, nombre FROM departamentos WHERE estado = 'activo' ORDER BY nombre ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function categoriaExiste($nombre) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE LOWER(nombre) = LOWER(?)");
    $stmt->execute([trim($nombre)]);
    return $stmt->fetchColumn() > 0;
}

function departamentoExiste($nombre) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departamentos WHERE LOWER(nombre) = LOWER(?)");
    $stmt->execute([trim($nombre)]);
    return $stmt->fetchColumn() > 0;
}

header('Content-Type: application/json');

try {
    if (!isset($_POST['action'])) {
        throw new Exception('Acción no especificada');
    }

    switch ($_POST['action']) {
        case 'obtener_categorias':
            echo json_encode(obtenerCategorias());
            break;

        case 'obtener_departamentos':
            echo json_encode(obtenerDepartamentos());
            break;

        case 'crear_categoria':
            $nombre = trim($_POST['nombre'] ?? '');
            if (empty($nombre)) {
                throw new Exception('El nombre es requerido');
            }
            if (categoriaExiste($nombre)) {
                throw new Exception('Ya existe una categoría con este nombre');
            }

            $stmt = $pdo->prepare("
                INSERT INTO categorias (
                    nombre, 
                    descripcion,
                    estado, 
                    fecha_creacion
                ) VALUES (?, ?, 'activo', NOW())
            ");
            
            $descripcion = "Categoría creada desde el módulo de inventario";
            if (!$stmt->execute([$nombre, $descripcion])) {
                throw new Exception('Error al crear la categoría');
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $pdo->lastInsertId(),
                    'nombre' => $nombre
                ]
            ]);
            break;

        case 'crear_departamento':
            $nombre = trim($_POST['nombre'] ?? '');
            if (empty($nombre)) {
                throw new Exception('El nombre es requerido');
            }
            if (departamentoExiste($nombre)) {
                throw new Exception('Ya existe un departamento con este nombre');
            }

            $stmt = $pdo->prepare("
                INSERT INTO departamentos (
                    nombre, 
                    descripcion,
                    estado, 
                    fecha_creacion
                ) VALUES (?, ?, 'activo', NOW())
            ");
            
            $descripcion = "Departamento creado desde el módulo de inventario";
            if (!$stmt->execute([$nombre, $descripcion])) {
                throw new Exception('Error al crear el departamento');
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $pdo->lastInsertId(),
                    'nombre' => $nombre
                ]
            ]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 