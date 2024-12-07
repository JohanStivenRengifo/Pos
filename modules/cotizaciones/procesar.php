<?php
require_once '../../config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $cliente_id = $_POST['cliente_id'];
        $fecha = $_POST['fecha'];
        $productos = $_POST['productos'] ?? [];
        
        // Calcular el total
        $total = 0;
        foreach ($productos as $producto) {
            $total += $producto['cantidad'] * $producto['precio'];
        }

        $pdo->beginTransaction();

        if ($action === 'add') {
            // Generar número de cotización (formato: COT-001)
            $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(numero, 5) AS UNSIGNED)) as ultimo FROM cotizaciones");
            $ultimo = $stmt->fetch()['ultimo'];
            $siguiente = str_pad(($ultimo + 1), 3, '0', STR_PAD_LEFT);
            $numero = "COT-{$siguiente}";

            // Insertar cotización
            $stmt = $pdo->prepare("INSERT INTO cotizaciones (numero, cliente_id, fecha, total, estado) VALUES (?, ?, ?, ?, 'Pendiente')");
            $stmt->execute([$numero, $cliente_id, $fecha, $total]);
            $cotizacion_id = $pdo->lastInsertId();
        } else {
            $cotizacion_id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE cotizaciones SET cliente_id = ?, fecha = ?, total = ? WHERE id = ?");
            $stmt->execute([$cliente_id, $fecha, $total, $cotizacion_id]);
            
            // Eliminar productos anteriores
            $stmt = $pdo->prepare("DELETE FROM cotizacion_productos WHERE cotizacion_id = ?");
            $stmt->execute([$cotizacion_id]);
        }

        // Insertar productos
        $stmt = $pdo->prepare("INSERT INTO cotizacion_productos (cotizacion_id, nombre, cantidad, precio) VALUES (?, ?, ?, ?)");
        foreach ($productos as $producto) {
            $stmt->execute([
                $cotizacion_id,
                $producto['nombre'],
                $producto['cantidad'],
                $producto['precio']
            ]);
        }

        $pdo->commit();
        $_SESSION['success'] = "Cotización " . ($action === 'add' ? 'creada' : 'actualizada') . " exitosamente.";
        
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        
        $pdo->beginTransaction();
        
        // Eliminar productos
        $stmt = $pdo->prepare("DELETE FROM cotizacion_productos WHERE cotizacion_id = ?");
        $stmt->execute([$id]);
        
        // Eliminar cotización
        $stmt = $pdo->prepare("DELETE FROM cotizaciones WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        $_SESSION['success'] = "Cotización eliminada exitosamente.";
    }

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header('Location: index.php');
exit; 