<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Validar datos requeridos
    if (empty($_POST['nombre'])) {
        throw new Exception('El nombre del cliente es requerido');
    }

    // Separar el nombre completo en sus componentes
    $nombreCompleto = explode(' ', trim($_POST['nombre']));
    $primer_nombre = $nombreCompleto[0] ?? '';
    $segundo_nombre = $nombreCompleto[1] ?? '';
    $apellidos = implode(' ', array_slice($nombreCompleto, 2));

    // Preparar la consulta ajustada a la estructura de la tabla
    $stmt = $pdo->prepare("
        INSERT INTO clientes (
            user_id,
            nombre,
            email,
            telefono,
            tipo_identificacion,
            identificacion,
            primer_nombre,
            segundo_nombre,
            apellidos,
            municipio_departamento,
            codigo_postal,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    // Ejecutar la consulta con los datos mapeados correctamente
    $stmt->execute([
        $user_id,
        $_POST['nombre'], // nombre completo
        $_POST['email'] ?? null,
        $_POST['telefono'] ?? null,
        'CC', // valor por defecto para tipo_identificacion
        $_POST['documento'] ?? null, // documento mapeado a identificacion
        $primer_nombre,
        $segundo_nombre,
        $apellidos,
        $_POST['direccion'] ?? null, // direccion mapeada a municipio_departamento
        null, // codigo_postal
    ]);

    $cliente_id = $pdo->lastInsertId();

    // Obtener los datos del cliente recién creado
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => true,
        'message' => 'Cliente guardado correctamente',
        'cliente' => [
            'id' => $cliente['id'],
            'nombre' => $cliente['nombre']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}
