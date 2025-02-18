<?php
session_start();
error_log("Iniciando prestamos/crear.php");

if (!isset($_SESSION['user_id'])) {
    error_log("Usuario no autenticado, redirigiendo a login");
    header('Location: ../../login.php');
    exit;
}

error_log("Usuario autenticado: " . $_SESSION['user_id']);
$error_message = '';
$success_message = '';
$clientes = [];

try {
    require_once '../../config/db.php';
    error_log("Conexión a base de datos establecida");

    // Verificar si las tablas necesarias existen
    $required_tables = ['prestamos', 'clientes', 'inventario'];
    foreach ($required_tables as $table) {
        $check = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check === false) {
            error_log("Error al verificar tabla $table: " . implode(" ", $pdo->errorInfo()));
            throw new Exception("Error al verificar la estructura de la base de datos");
        }
        if ($check->rowCount() == 0) {
            error_log("La tabla $table no existe");
            throw new Exception("La tabla $table no existe. Por favor, ejecute el script SQL de instalación.");
        }
    }

    error_log("Todas las tablas verificadas correctamente");

    // Obtener listado de clientes asociados al usuario actual
    $query = "SELECT id, 
                     CONCAT(COALESCE(primer_nombre, ''), ' ', 
                           COALESCE(segundo_nombre, ''), ' ', 
                           COALESCE(apellidos, '')) as nombre,
                     identificacion, telefono, email, foto_perfil 
              FROM clientes 
              WHERE user_id = :user_id
              ORDER BY primer_nombre, apellidos";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $clientes = $stmt->fetchAll();

    if (empty($clientes)) {
        $error_message = "No hay clientes registrados. Por favor, registre al menos un cliente antes de crear un préstamo.";
    }

    // Procesar el formulario cuando se envía
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar datos del formulario
        if (empty($_POST['cliente_id'])) {
            throw new Exception("Debe seleccionar un cliente");
        }
        if (empty($_POST['productos'])) {
            throw new Exception("Debe agregar al menos un producto");
        }

        $cliente_id = $_POST['cliente_id'];
        $productos = $_POST['productos'];
        
        // Iniciar transacción
        $pdo->beginTransaction();

        try {
            // Insertar el préstamo
            $query = "INSERT INTO prestamos (user_id, cliente_id, estado) VALUES (:user_id, :cliente_id, 'activo')";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'user_id' => $_SESSION['user_id'],
                'cliente_id' => $cliente_id
            ]);
            
            $prestamo_id = $pdo->lastInsertId();

            // Insertar los productos prestados y actualizar stock
            foreach ($productos as $producto) {
                // Validar stock disponible
                $check_stock = $pdo->prepare("SELECT stock FROM inventario WHERE id = :id AND user_id = :user_id");
                $check_stock->execute([
                    'id' => $producto['id'],
                    'user_id' => $_SESSION['user_id']
                ]);
                $stock_actual = $check_stock->fetch()['stock'];
                
                if ($stock_actual < $producto['cantidad']) {
                    throw new Exception("Stock insuficiente para el producto ID: " . $producto['id']);
                }
                
                // Insertar producto prestado
                $stmt = $pdo->prepare("INSERT INTO prestamos_productos (prestamo_id, producto_id, cantidad, estado) VALUES (:prestamo_id, :producto_id, :cantidad, 'prestado')");
                $stmt->execute([
                    'prestamo_id' => $prestamo_id,
                    'producto_id' => $producto['id'],
                    'cantidad' => $producto['cantidad']
                ]);
                
                // Actualizar stock
                $stmt = $pdo->prepare("UPDATE inventario SET stock = stock - :cantidad WHERE id = :id AND user_id = :user_id AND stock >= :cantidad");
                $stmt->execute([
                    'cantidad' => $producto['cantidad'],
                    'id' => $producto['id'],
                    'user_id' => $_SESSION['user_id']
                ]);
                
                if ($stmt->rowCount() == 0) {
                    throw new Exception("Error al actualizar el stock del producto ID: " . $producto['id']);
                }
            }

            $pdo->commit();
            header("Location: index.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    error_log("Error en prestamos/crear.php: " . $e->getMessage());
}

// Incluir el header
require_once '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Préstamo | Numercia</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex">
        <?php require_once '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 p-8">
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Nuevo Préstamo</h2>
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>Volver
                    </a>
                </div>

                <form id="prestamoForm" method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                            <select name="cliente_id" id="cliente_id" required
                                    onchange="mostrarFotoCliente(this.value)"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">Seleccione un cliente</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= htmlspecialchars($cliente['id']) ?>"
                                            data-foto="<?= htmlspecialchars($cliente['foto_perfil'] ?? '') ?>">
                                        <?= htmlspecialchars($cliente['nombre']) ?> - 
                                        <?= htmlspecialchars($cliente['identificacion']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="foto_cliente_container" class="hidden md:flex justify-center items-center">
                            <img id="foto_cliente" 
                                 src="" 
                                 alt="Foto del cliente"
                                 class="max-h-48 rounded-lg shadow-lg border-2 border-gray-200">
                        </div>
                    </div>

                    <div class="border-t pt-6">
                        <h4 class="text-lg font-medium mb-4">Productos a Prestar</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar Producto</label>
                                <input type="text" 
                                       id="producto_busqueda" 
                                       placeholder="Buscar por nombre o código" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <div id="sugerencias_productos" class="hidden absolute z-10 w-full mt-1 bg-white shadow-lg rounded-md py-1"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                                <div class="flex">
                                    <input type="number" 
                                           id="cantidad_producto" 
                                           min="1" 
                                           value="1"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-l-md">
                                    <button type="button" 
                                            onclick="agregarProducto()"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-r-md hover:bg-blue-700">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                        <th class="px-6 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody id="productos_container" class="bg-white divide-y divide-gray-200">
                                    <!-- Los productos se agregarán aquí dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 pt-6 border-t">
                        <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                            Cancelar
                        </a>
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Guardar Préstamo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../js/prestamos.js"></script>
</body>
</html> 