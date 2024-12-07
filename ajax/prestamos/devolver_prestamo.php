<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Usuario no autenticado'
    ]);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $prestamo_id = $data['prestamo_id'];
    $cliente_id = $data['cliente_id'];
    $devoluciones = $data['devoluciones'];
    $user_id = $_SESSION['user_id'];

    // Agregar logs para depuración
    error_log("Datos recibidos: " . json_encode($data));

    $pdo->beginTransaction();

    // Verificar que el préstamo pertenece al usuario y obtener información completa
    $stmt = $pdo->prepare("
        SELECT p.id, p.estado, p.cliente_id 
        FROM prestamos p 
        WHERE p.id = :id AND p.user_id = :user_id
    ");
    $stmt->execute([
        ':id' => $prestamo_id,
        ':user_id' => $user_id
    ]);
    
    $prestamo = $stmt->fetch();
    if (!$prestamo) {
        throw new Exception("Préstamo no encontrado o no autorizado");
    }

    // Obtener todos los productos del préstamo con su información
    $stmt = $pdo->prepare("
        SELECT 
            pp.prestamo_id,
            pp.producto_id,
            pp.cantidad,
            pp.estado,
            i.precio_venta,
            i.precio_costo,
            i.impuesto,
            i.nombre as producto_nombre
        FROM prestamos_productos pp
        INNER JOIN inventario i ON i.id = pp.producto_id
        WHERE pp.prestamo_id = :prestamo_id 
    ");
    $stmt->execute([':prestamo_id' => $prestamo_id]);
    $productos_prestamo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log para depuración
    error_log("Productos del préstamo encontrados: " . json_encode($productos_prestamo));
    
    // Crear un mapa de productos para fácil acceso
    $productos_map = [];
    foreach ($productos_prestamo as $prod) {
        $productos_map[$prod['producto_id']] = $prod;
    }

    error_log("Mapa de productos creado: " . json_encode($productos_map));
    error_log("Devoluciones a procesar: " . json_encode($devoluciones));

    // Validar cada devolución
    foreach ($devoluciones as $devolucion) {
        error_log("Procesando devolución: " . json_encode($devolucion));
        
        if (!isset($productos_map[$devolucion['producto_id']])) {
            error_log("Producto no encontrado en el mapa. ID buscado: " . $devolucion['producto_id']);
            error_log("IDs disponibles: " . implode(", ", array_keys($productos_map)));
            throw new Exception("El producto ID: {$devolucion['producto_id']} no pertenece a este préstamo");
        }

        $producto_info = $productos_map[$devolucion['producto_id']];
        
        // Validar que el producto no haya sido procesado ya
        if ($producto_info['estado'] !== 'prestado') {
            throw new Exception("El producto {$producto_info['producto_nombre']} ya fue procesado como '{$producto_info['estado']}'");
        }

        // Si es venta, validar el precio
        if ($devolucion['estado'] === 'vendido') {
            if (!isset($devolucion['precio_venta']) || $devolucion['precio_venta'] <= 0) {
                throw new Exception("Debe especificar un precio de venta válido para {$producto_info['producto_nombre']}");
            }
            if ($devolucion['precio_venta'] < $producto_info['precio_costo']) {
                throw new Exception("El precio de venta no puede ser menor al costo para {$producto_info['producto_nombre']}");
            }
        }
    }

    $productos_vendidos = [];
    
    // Procesar las devoluciones
    foreach ($devoluciones as $devolucion) {
        $producto_info = $productos_map[$devolucion['producto_id']];
        
        // Actualizar estado y precio_venta_final del producto en el préstamo
        $stmt = $pdo->prepare("
            UPDATE prestamos_productos 
            SET estado = :estado,
                fecha_devolucion = NOW(),
                precio_venta_final = :precio_venta
            WHERE prestamo_id = :prestamo_id 
            AND producto_id = :producto_id
        ");
        
        $stmt->execute([
            ':estado' => $devolucion['estado'],
            ':precio_venta' => $devolucion['precio_venta'],
            ':prestamo_id' => $prestamo_id,
            ':producto_id' => $devolucion['producto_id']
        ]);

        if ($devolucion['estado'] === 'devuelto') {
            // Devolver al inventario
            $stmt = $pdo->prepare("
                UPDATE inventario 
                SET stock = stock + :cantidad 
                WHERE id = :producto_id AND user_id = :user_id
            ");
            
            $stmt->execute([
                ':cantidad' => $producto_info['cantidad'],
                ':producto_id' => $devolucion['producto_id'],
                ':user_id' => $user_id
            ]);
        } else if ($devolucion['estado'] === 'vendido') {
            $productos_vendidos[] = [
                'producto_id' => $devolucion['producto_id'],
                'cantidad' => $producto_info['cantidad'],
                'precio_venta' => $devolucion['precio_venta'],
                'precio_costo' => $producto_info['precio_costo'],
                'impuesto' => $producto_info['impuesto']
            ];
        }
    }

    // Si hay productos vendidos, crear una venta
    if (!empty($productos_vendidos)) {
        // Obtener el turno activo del usuario
        $stmt = $pdo->prepare("
            SELECT id 
            FROM turnos 
            WHERE user_id = :user_id 
            AND fecha_cierre IS NULL
            ORDER BY fecha_apertura DESC 
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $user_id]);
        $turno = $stmt->fetch();

        if (!$turno) {
            throw new Exception("No hay un turno activo para realizar la venta. Por favor, abra un turno primero.");
        }

        // Antes de crear la venta, obtener la información de la empresa y el último número de factura
        $stmt = $pdo->prepare("
            SELECT id, prefijo_factura, ultimo_numero, numero_inicial, numero_final 
            FROM empresas 
            WHERE usuario_id = :user_id 
            AND estado = 1 
            ORDER BY es_principal DESC 
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $user_id]);
        $empresa = $stmt->fetch();

        if (!$empresa) {
            throw new Exception("No se encontró configuración de empresa para generar la factura");
        }

        // Calcular el siguiente número de factura
        $siguiente_numero = ($empresa['ultimo_numero'] ?? $empresa['numero_inicial']) + 1;

        if ($siguiente_numero > $empresa['numero_final']) {
            throw new Exception("Se ha alcanzado el límite de numeración de facturas");
        }

        // Generar el número de factura con el formato
        $numero_factura = $empresa['prefijo_factura'] . str_pad($siguiente_numero, 8, '0', STR_PAD_LEFT);

        // Modificar la inserción de la venta para incluir el número de factura
        $stmt = $pdo->prepare("
            INSERT INTO ventas (
                user_id, 
                cliente_id, 
                total,
                subtotal,
                descuento,
                metodo_pago,
                fecha,
                tipo_documento,
                numeracion_tipo,
                turno_id,
                estado_factura,
                numero_factura,
                numeracion
            ) VALUES (
                :user_id, 
                :cliente_id, 
                0,
                0,
                0,
                'efectivo',
                NOW(),
                'prestamo',
                'principal',
                :turno_id,
                'completado',
                :numero_factura,
                'principal'
            )
        ");

        $stmt->execute([
            ':user_id' => $user_id,
            ':cliente_id' => $cliente_id,
            ':turno_id' => $turno['id'],
            ':numero_factura' => $numero_factura
        ]);

        $venta_id = $pdo->lastInsertId();
        $subtotal_venta = 0;

        // Registrar productos vendidos
        foreach ($productos_vendidos as $producto) {
            // Validar que el precio de venta no sea menor que el costo
            if ($producto['precio_venta'] < $producto['precio_costo']) {
                throw new Exception("El precio de venta no puede ser menor al costo del producto");
            }

            $subtotal = $producto['cantidad'] * $producto['precio_venta'];
            
            // Calcular el impuesto basado en el precio de venta
            $impuesto_valor = ($subtotal * $producto['impuesto']) / 100;
            $subtotal_venta += $subtotal;

            $stmt = $pdo->prepare("
                INSERT INTO venta_detalles (
                    venta_id, 
                    producto_id, 
                    cantidad, 
                    precio_unitario
                ) VALUES (
                    :venta_id, 
                    :producto_id, 
                    :cantidad, 
                    :precio_unitario
                )
            ");
            
            $stmt->execute([
                ':venta_id' => $venta_id,
                ':producto_id' => $producto['producto_id'],
                ':cantidad' => $producto['cantidad'],
                ':precio_unitario' => $producto['precio_venta']
            ]);
        }

        // Actualizar totales de la venta
        $stmt = $pdo->prepare("
            UPDATE ventas 
            SET total = :total,
                subtotal = :subtotal,
                fecha = NOW()
            WHERE id = :id
        ");

        // El total incluye el subtotal más los impuestos
        $stmt->execute([
            ':total' => $subtotal_venta,
            ':subtotal' => $subtotal_venta,
            ':id' => $venta_id
        ]);

        // Actualizar el último número usado en la empresa
        $stmt = $pdo->prepare("
            UPDATE empresas 
            SET ultimo_numero = :ultimo_numero 
            WHERE id = :empresa_id
        ");
        $stmt->execute([
            ':ultimo_numero' => $siguiente_numero,
            ':empresa_id' => $empresa['id']
        ]);
    }

    // Verificar si todos los productos del préstamo están procesados
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_productos,
            SUM(CASE WHEN estado = 'prestado' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'devuelto' THEN 1 ELSE 0 END) as devueltos,
            SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as vendidos
        FROM prestamos_productos
        WHERE prestamo_id = :prestamo_id
    ");
    $stmt->execute([':prestamo_id' => $prestamo_id]);
    $conteo = $stmt->fetch();

    // Si no hay productos pendientes (prestados), el préstamo está completamente procesado
    $estado_prestamo = $conteo['pendientes'] == 0 ? 'devuelto' : 'activo';

    // Actualizar estado del préstamo
    $stmt = $pdo->prepare("UPDATE prestamos SET estado = :estado WHERE id = :id");
    $stmt->execute([
        ':estado' => $estado_prestamo,
        ':id' => $prestamo_id
    ]);

    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Devolución registrada exitosamente',
        'estado_final' => $estado_prestamo,
        'venta_generada' => !empty($productos_vendidos),
        'estadisticas' => [
            'total_productos' => $conteo['total_productos'],
            'devueltos' => $conteo['devueltos'],
            'vendidos' => $conteo['vendidos'],
            'pendientes' => $conteo['pendientes']
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error en devolver_prestamo.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar la devolución',
        'message' => $e->getMessage()
    ]);
} 