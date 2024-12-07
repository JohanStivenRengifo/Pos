<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    error_log("Error: Usuario no autenticado en obtener_prestamos.php");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    error_log("Buscando préstamos para user_id: " . $user_id);

    // Consulta principal de préstamos
    $query = "SELECT p.*, 
                     CONCAT(COALESCE(c.primer_nombre, ''), ' ', 
                           COALESCE(c.segundo_nombre, ''), ' ',
                           COALESCE(c.apellidos, '')) as cliente_nombre,
                     c.identificacion as cliente_identificacion
              FROM prestamos p 
              INNER JOIN clientes c ON p.cliente_id = c.id 
              WHERE p.user_id = :user_id 
              ORDER BY p.fecha_prestamo DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $prestamos = [];

    while ($row = $stmt->fetch()) {
        // Obtener los productos asociados al préstamo
        $query_productos = "
            SELECT 
                pp.id as prestamo_producto_id,
                pp.producto_id,
                pp.cantidad,
                pp.estado,
                pp.fecha_devolucion,
                pp.precio_venta_final,
                i.nombre as producto_nombre,
                i.codigo_barras,
                i.precio_venta,
                i.precio_costo,
                i.impuesto
            FROM prestamos_productos pp
            INNER JOIN inventario i ON pp.producto_id = i.id
            WHERE pp.prestamo_id = :prestamo_id";
        
        $stmt_productos = $pdo->prepare($query_productos);
        $stmt_productos->execute(['prestamo_id' => $row['id']]);
        $row['productos'] = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

        // Asegurarnos de que cada producto tenga los campos necesarios
        foreach ($row['productos'] as &$producto) {
            $producto['id'] = $producto['prestamo_producto_id']; // ID de prestamos_productos
            $producto['estado_producto'] = $producto['estado']; // Mantener compatibilidad
            $producto['precio_venta'] = $producto['precio_venta_final'] ?: $producto['precio_venta'];
        }

        $prestamos[] = $row;
    }

    error_log("Préstamos procesados: " . count($prestamos));
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $prestamos
    ]);

} catch (Exception $e) {
    error_log("Error en obtener_prestamos.php: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error en la base de datos',
        'message' => $e->getMessage()
    ]);
} 