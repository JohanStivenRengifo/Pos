<?php

// Modificar la sección donde se maneja el POST exitoso
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // ... código existente de validación ...

        // Agregar producto
        $producto_id = agregarProducto($user_id, $producto_data, $_FILES['imagenes'] ?? []);

        // Modificar la redirección para usar código de barras
        $_SESSION['success_message'] = "Producto agregado exitosamente";
        header("Location: ver.php?codigo_barras=" . urlencode($producto_data['codigo_barras']));
        exit;

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
    }
} 