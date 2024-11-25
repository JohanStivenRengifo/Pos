<?php
session_start();

// Incluir archivos necesarios usando rutas absolutas
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Verificar que las funciones existen
if (!function_exists('obtenerClientes')) {
    die("Error: La función obtenerClientes() no está definida. Ruta: " .'/includes/functions.php');
}

if (!function_exists('obtenerProductos')) {
    die("Error: La función obtenerProductos() no está definida. Ruta: " . '/includes/functions.php');
}

// Habilitar el reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Verifica si el usuario está autenticado
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../index.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Verificar si hay un turno activo para el usuario
    $stmt = $pdo->prepare("SELECT id, fecha_apertura, monto_inicial FROM turnos WHERE user_id = ? AND fecha_cierre IS NULL");
    if (!$stmt) {
        throw new Exception("Error preparando la consulta de turnos");
    }
    
    $stmt->execute([$user_id]);
    $turno_activo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si no hay turno activo, redirigir a la página de apertura de turno
    if (!$turno_activo) {
        header("Location: ./controllers/apertura_turno.php");
        exit();
    }

    // Guardar el ID del turno en la sesión
    $_SESSION['turno_id'] = $turno_activo['id'];

    // Obtener datos de clientes y productos
    $clientes = obtenerClientes($pdo, $user_id);
    $productos = obtenerProductos($pdo, $user_id);

    // Si llegamos aquí, todo está bien y podemos mostrar la página
    
} catch (Exception $e) {
    // Registrar el error
    error_log("Error en POS: " . $e->getMessage());
    
    // Mostrar un mensaje de error amigable
    die("Ha ocurrido un error. Por favor, contacte al administrador. Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap Bundle (incluye Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    
    <!-- Scripts propios -->
    <script src="js/index.js" defer></script>
</head>

<body class="h-full">
    <!-- Navbar -->
    <nav class="bg-indigo-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-12 items-center">
                <div class="flex-shrink-0">
                    <a href="johanrengifo.cloud/welcome.php" class="flex items-center text-white">
                        <i class="fas fa-cash-register mr-2"></i>
                        <span class="font-semibold">VendEasy POS</span>
                    </a>
                </div>
                <div class="flex items-center space-x-2">
                    <button class="text-white hover:bg-indigo-700 px-2 py-1 rounded-md text-sm font-medium transition-colors duration-200" id="sync-button">
                        <i class="fas fa-sync-alt mr-1"></i>Sincronizar
                    </button>
                    <button class="text-white hover:bg-indigo-700 px-2 py-1 rounded-md text-sm font-medium transition-colors duration-200" id="help-button">
                        <i class="fas fa-question-circle mr-1"></i>Ayuda
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Barra de información del turno -->
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 py-2">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Información del turno -->
                <div class="flex items-center">
                    <div class="flex items-center text-sm">
                        <i class="fas fa-clock text-gray-400 mr-2"></i>
                        <span class="text-gray-600">Turno iniciado:</span>
                        <span class="font-medium ml-2" id="hora-apertura" 
                              data-fecha-apertura="<?= $turno_activo['fecha_apertura'] ?>"
                              data-turno-id="<?= $turno_activo['id'] ?>">
                            <?= date('d/m/Y H:i', strtotime($turno_activo['fecha_apertura'])) ?>
                        </span>
                    </div>
                </div>

                <!-- Monto inicial -->
                <div class="flex items-center">
                    <div class="flex items-center text-sm">
                        <i class="fas fa-money-bill text-gray-400 mr-2"></i>
                        <span class="text-gray-600">Base inicial:</span>
                        <span class="font-medium ml-2">
                            $<?= number_format($turno_activo['monto_inicial'], 0, ',', '.') ?>
                        </span>
                    </div>
                </div>

                <!-- Total vendido -->
                <div class="flex items-center">
                    <div class="flex items-center text-sm">
                        <i class="fas fa-cash-register text-gray-400 mr-2"></i>
                        <span class="text-gray-600">Total vendido:</span>
                        <span class="font-medium ml-2" id="total-vendido">
                            Calculando...
                        </span>
                    </div>
                </div>

                <!-- Tiempo transcurrido -->
                <div class="flex items-center">
                    <div class="flex items-center text-sm">
                        <i class="fas fa-hourglass-half text-gray-400 mr-2"></i>
                        <span class="text-gray-600">Tiempo activo:</span>
                        <span class="font-medium ml-2" id="tiempo-transcurrido">Calculando...</span>
                    </div>
                </div>

                <!-- Botón cerrar turno -->
                <div class="flex justify-end items-center">
                    <button onclick="cerrarTurno(<?= $turno_activo['id'] ?>)" 
                            class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-door-open mr-2"></i>
                        Cerrar Turno
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="max-w-full mx-auto px-2 sm:px-4 lg:px-6 py-4 h-[calc(100vh-7rem)]">
        <div class="flex flex-col lg:flex-row gap-4 h-full">
            <!-- Panel izquierdo: Productos -->
            <div class="w-full lg:w-[65%] bg-white rounded-lg shadow-lg flex flex-col">
                <!-- Barra de búsqueda simplificada -->
                <div class="sticky top-0 z-10 bg-white p-3 border-b border-gray-200">
                    <div class="relative">
                        <input type="text" 
                               id="buscar-producto" 
                               class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200" 
                               placeholder="Buscar productos por nombre o código de barras">
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400 text-sm"></i>
                        </div>
                    </div>
                </div>

                <!-- Grid responsive de productos ajustado -->
                <div id="products-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 p-3 overflow-y-auto flex-1">
                    <?php 
                    if (empty($productos)) {
                        echo '<div class="col-span-full flex flex-col items-center justify-center py-8 text-gray-400">
                                <i class="fas fa-box-open text-3xl mb-3"></i>
                                <p class="text-sm font-medium">No hay productos disponibles</p>
                                <p class="text-xs text-gray-400">Agrega productos desde el módulo de inventario</p>
                              </div>';
                    } else {
                        foreach ($productos as $producto): 
                    ?>
                        <div class="item-view flex flex-col bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200 cursor-pointer border border-gray-100 relative"
                             data-id="<?= $producto['id'] ?>"
                             data-nombre="<?= htmlspecialchars($producto['nombre']) ?>"
                             data-precio="<?= $producto['precio'] ?>"
                             data-cantidad="<?= $producto['cantidad'] ?>"
                             data-codigo="<?= htmlspecialchars($producto['codigo_barras']) ?>">
                            
                            <div class="item-view__image-zone relative overflow-hidden aspect-square bg-gray-50">
                                <!-- Código de barras superior -->
                                <div class="absolute top-1 left-1 right-1 z-10">
                                    <span class="bg-white/80 backdrop-blur-sm text-gray-500 text-[10px] px-1.5 py-0.5 rounded-md inline-block">
                                        <?= htmlspecialchars($producto['codigo_barras']) ?>
                                    </span>
                                </div>

                                <!-- Inventario -->
                                <div class="absolute top-1 right-1 z-10">
                                    <span class="bg-indigo-100 text-indigo-700 text-[10px] px-1.5 py-0.5 rounded-md font-medium">
                                        <?= $producto['cantidad'] ?>
                                    </span>
                                </div>
                                
                                <?php 
                                $stmt = $pdo->prepare("
                                    SELECT ruta 
                                    FROM imagenes_producto 
                                    WHERE producto_id = ? 
                                    AND es_principal = 1 
                                    LIMIT 1
                                ");
                                $stmt->execute([$producto['id']]);
                                $imagen = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                $rutaImagen = ($imagen && !empty($imagen['ruta'])) 
                                    ? '/' . ltrim($imagen['ruta'], '/') 
                                    : '../../assets/img/no-image.png';
                                ?>
                                <img src="<?= htmlspecialchars($rutaImagen) ?>"
                                     alt="<?= htmlspecialchars($producto['nombre']) ?>"
                                     class="product-image w-full h-full object-cover object-center transition-transform duration-200 group-hover:scale-105"
                                     loading="lazy"
                                     onerror="this.src='../../assets/img/no-image.png'">
                            </div>

                            <div class="p-2 flex flex-col gap-1">
                                <!-- Nombre del producto -->
                                <p class="item-view__name text-[11px] text-gray-600 line-clamp-2 min-h-[2.5rem] leading-tight">
                                    <?= htmlspecialchars($producto['nombre']) ?>
                                </p>

                                <!-- Precio -->
                                <p class="item-view__price text-base font-bold text-indigo-600">
                                    $<?= number_format($producto['precio'], 0, ',', '.') ?>
                                </p>
                            </div>
                        </div>
                    <?php 
                        endforeach;
                    }
                    ?>
                </div>
            </div>

            <!-- Panel derecho: Carrito -->
            <div class="w-full lg:w-[35%] flex flex-col bg-white rounded-lg shadow-lg h-full">
                <!-- Encabezado del panel -->
                <div class="p-2 border-b border-gray-200">
                    <div class="flex flex-col gap-2 text-xs">  <!-- Cambiado a flex-col -->
                        <!-- Primera fila: Tipo y Numeración -->
                        <div class="flex gap-2">
                            <!-- Tipo documento -->
                            <div class="flex-1">
                                <label class="block text-gray-500 mb-0.5">
                                    <i class="fas fa-file-alt mr-1"></i>Tipo
                                </label>
                                <select id="tipo-documento" class="w-full border border-gray-300 rounded-md text-xs py-0.5 px-1">
                                    <option value="factura">Factura de venta</option>
                                    <option value="cotizacion">Cotización</option>
                                </select>
                            </div>
                            
                            <!-- Numeración -->
                            <div class="flex-1">
                                <label class="block text-gray-500 mb-0.5">
                                    <i class="fas fa-hashtag mr-1"></i>Numeración
                                </label>
                                <select id="numeracion" class="w-full border border-gray-300 rounded-md text-xs py-0.5 px-1">
                                    <option value="principal">Principal</option>
                                    <option value="electronica">Electrónica</option>
                                </select>
                            </div>
                        </div>

                        <!-- Segunda fila: Cliente -->
                        <div class="w-full">
                            <label class="block text-gray-500 mb-0.5">
                                <i class="fas fa-user mr-1"></i>Cliente
                            </label>
                            <div class="flex gap-1">
                                <select id="cliente-select" class="flex-1 border border-gray-300 rounded-md text-xs py-0.5 px-1">
                                    <option value="">Seleccione un cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= $cliente['id']; ?>" 
                                            <?= $cliente['nombre'] === 'Consumidor Final' ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($cliente['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="../modules/clientes" 
                                   class="px-2 border border-gray-300 rounded-md hover:bg-gray-50 text-gray-600 transition-colors duration-200">
                                    <i class="fas fa-plus text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de productos (altura ajustable) -->
                <div class="flex-1 overflow-auto p-3 border-b border-gray-200">
                    <div class="flex justify-between items-center mb-2">
                        <h5 class="text-sm font-medium flex items-center">
                            <i class="fas fa-shopping-cart mr-2"></i>Productos
                        </h5>
                        <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2 py-0.5 rounded">
                            <span id="cantidad-items">0</span> items
                        </span>
                    </div>

                    <!-- Tabla de productos (más compacta) -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-gray-700 bg-gray-50">
                                <tr>
                                    <th class="text-left py-1.5">Producto</th>
                                    <th class="text-center w-14">Cant.</th>
                                    <th class="text-right w-20">Precio</th>
                                    <th class="text-right w-20">Total</th>
                                    <th class="w-8"></th>
                                </tr>
                            </thead>
                            <tbody id="venta-lista"></tbody>
                        </table>
                        <div id="venta-lista-empty" class="text-center text-gray-500 py-6">
                            <i class="fas fa-shopping-basket text-2xl mb-2"></i>
                            <p class="text-xs">Aquí verás los productos de tu venta</p>
                        </div>
                    </div>
                </div>

                <!-- Resumen y totales (más compacto en una fila) -->
                <div class="p-2 bg-gray-50">
                    <!-- Fila única de totales -->
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <!-- Subtotal -->
                        <div class="flex flex-col">
                            <span class="text-gray-500">Subtotal:</span>
                            <span id="subtotal" class="font-semibold">$0,00</span>
                        </div>

                        <!-- Descuento input -->
                        <div class="flex flex-col">
                            <span class="text-gray-500">Descuento:</span>
                            <div class="flex items-center">
                                <input type="number" id="descuento" 
                                       class="w-10 border border-gray-300 rounded-l-md px-1 py-0.5 text-center"
                                       min="0" max="100" value="0">
                                <span class="inline-flex items-center px-1 border border-l-0 border-gray-300 rounded-r-md bg-gray-50">
                                    %
                                </span>
                            </div>
                        </div>

                        <!-- Descuento aplicado -->
                        <div class="flex flex-col">
                            <span class="text-gray-500">Desc. aplicado:</span>
                            <span id="descuento-monto" class="text-red-600">-$0,00</span>
                        </div>

                        <!-- Total -->
                        <div class="flex flex-col">
                            <span class="text-gray-500">Total:</span>
                            <span id="venta-total" class="font-bold">$0,00</span>
                        </div>
                    </div>

                    <!-- Método de pago (más compacto) -->
                    <div class="mt-2">
                        <label class="block text-xs text-gray-500">
                            <i class="fas fa-money-bill-wave mr-1"></i>Método de pago
                        </label>
                        <select id="metodo-pago" class="w-full border border-gray-300 rounded-md text-xs py-1" disabled>
                            <option value="efectivo" selected>Efectivo</option>
                        </select>
                    </div>

                    <!-- Botones de acción (más compactos) -->
                    <div class="space-y-1 mt-2">
                        <button id="procesar-venta" 
                                class="w-full bg-indigo-600 text-white py-1 px-3 rounded-md hover:bg-indigo-700 transition-colors text-xs disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                            <i class="fas fa-check-circle mr-1"></i>Confirmar Venta
                        </button>
                        
                        <div class="grid grid-cols-2 gap-1">
                            <button id="cancelar-venta" 
                                    class="w-full border border-gray-300 py-1 px-2 rounded-md hover:bg-gray-50 transition-colors text-xs">
                                <i class="fas fa-times-circle mr-1"></i>Cancelar
                            </button>
                            <button id="imprimir-ticket" 
                                    class="w-full bg-blue-500 text-white py-1 px-2 rounded-md hover:bg-blue-600 transition-colors text-xs">
                                <i class="fas fa-print mr-1"></i>Imprimir Ultima Venta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>