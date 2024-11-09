<?php
// Funciones generales
function obtenerClientes($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, nombre FROM clientes WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerProductos($pdo, $user_id) {
    $sql = "SELECT 
                i.id, 
                i.nombre, 
                i.precio_venta as precio, 
                i.stock as cantidad, 
                i.codigo_barras, 
                CONCAT('/', ip.ruta) as imagen
            FROM inventario i 
            LEFT JOIN imagenes_producto ip ON i.id = ip.producto_id AND ip.es_principal = 1
            WHERE i.user_id = :user_id 
                AND i.estado = 1 
            ORDER BY i.nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug para ver las rutas
    foreach ($productos as $producto) {
        if (!empty($producto['imagen'])) {
            error_log("Ruta de imagen para producto {$producto['nombre']}: {$producto['imagen']}");
        }
    }
    
    return $productos;
}

function buscarClientes($pdo, $user_id, $query) {
    $query = "%$query%";
    $stmt = $pdo->prepare("SELECT id, nombre, documento FROM clientes WHERE user_id = ? AND (nombre LIKE ? OR documento LIKE ?) LIMIT 10");
    $stmt->execute([$user_id, $query, $query]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function guardarCliente($pdo, $user_id, $nombre, $documento, $email) {
    $stmt = $pdo->prepare("INSERT INTO clientes (user_id, nombre, documento, email) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $nombre, $documento, $email])) {
        $id = $pdo->lastInsertId();
        return [
            'id' => $id,
            'nombre' => $nombre,
            'documento' => $documento,
            'email' => $email
        ];
    }
    return false;
}

function generarNumeroFactura($pdo, $user_id) {
    // Obtener información de la empresa del usuario
    $stmt = $pdo->prepare("
        SELECT 
            e.prefijo_factura, 
            e.numero_inicial, 
            e.numero_final 
        FROM users u
        JOIN empresas e ON u.empresa_id = e.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        throw new Exception('No se encontró la configuración de la empresa');
    }

    // Obtener el último número usado
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(numero_factura, '-', -1) AS UNSIGNED)) as ultimo_numero
        FROM ventas v
        JOIN users u ON v.user_id = u.id
        JOIN empresas e ON u.empresa_id = e.id
        WHERE e.prefijo_factura = ?
    ");
    $stmt->execute([$empresa['prefijo_factura']]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    // Determinar el próximo número
    $ultimo_numero = $resultado['ultimo_numero'];
    $proximo_numero = $ultimo_numero ? ($ultimo_numero + 1) : $empresa['numero_inicial'];

    // Verificar que no exceda el número final permitido
    if ($proximo_numero > $empresa['numero_final']) {
        throw new Exception('Se ha alcanzado el número máximo de facturas permitido');
    }

    // Generar el número de factura con el formato: PREFIJO-NUMERO
    $numero_factura = sprintf(
        '%s-%06d',
        $empresa['prefijo_factura'],
        $proximo_numero
    );

    return $numero_factura;
}