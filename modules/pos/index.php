<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/limiter.php';
require_once '../../includes/functions.php';

// Verificar si el usuario está logueado primero
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

// Verificar límite de facturas
try {
    $verificacion = verificarLimiteFacturasMensuales($pdo, $_SESSION['empresa_id']);

    if (is_array($verificacion) && !$verificacion['puede_facturar']) {
        // Obtener información del plan actual
        $stmt = $pdo->prepare("SELECT plan_suscripcion FROM empresas WHERE id = ?");
        $stmt->execute([$_SESSION['empresa_id']]);
        $plan_actual = $stmt->fetchColumn();
        
        $plan_siguiente = $plan_actual === PLAN_BASICO ? 'Profesional' : 'Empresarial';
        
        $_SESSION['error_message'] = "Has alcanzado el límite de facturas mensuales de tu plan actual (" . 
                                    $verificacion['facturas_actuales'] . " de " . 
                                    $verificacion['limite_facturas'] . "). " .
                                    "Actualiza al plan $plan_siguiente para continuar facturando.";
        
        header('Location: /modules/empresa/planes.php');
        exit();
    }

    // Si está cerca del límite (90% o más), mostrar advertencia
    if (isset($verificacion['facturas_disponibles']) && 
        $verificacion['facturas_disponibles'] !== -1 && 
        $verificacion['facturas_actuales'] >= ($verificacion['limite_facturas'] * 0.9)) {
        $_SESSION['warning_message'] = "¡Atención! Te quedan " . $verificacion['facturas_disponibles'] . 
                                      " facturas disponibles este mes. Considera actualizar tu plan para evitar interrupciones.";
    }
} catch (Exception $e) {
    error_log("Error al verificar límite de facturas: " . $e->getMessage());
    // Continuar con la ejecución normal incluso si hay un error en la verificación
}

// Obtener información del usuario
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Usuario';
$user_email = $_SESSION['user_email'];
$empresa_id = $_SESSION['empresa_id'];

// Obtener productos de la empresa actual
try {
    $stmt = $pdo->prepare("
        SELECT i.*, ip.ruta as imagen_ruta
        FROM inventario i
        LEFT JOIN imagenes_producto ip ON i.id = ip.producto_id AND ip.es_principal = 1
        WHERE i.user_id = ? 
        AND i.estado = 'activo'
        ORDER BY i.nombre ASC
    ");
    $stmt->execute([$user_id]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener clientes del usuario
    $stmt = $pdo->prepare("
        SELECT 
            id,
            CONCAT(COALESCE(primer_nombre, ''), ' ', COALESCE(segundo_nombre, ''), ' ', COALESCE(apellidos, '')) as nombre,
            identificacion as documento,
            email,
            telefono,
            celular,
            direccion,
            tipo_persona,
            responsabilidad_tributaria
        FROM clientes 
        WHERE user_id = ?
        ORDER BY primer_nombre ASC
    ");
    $stmt->execute([$user_id]);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Verificar si hay un turno abierto para el usuario
    $stmt = $pdo->prepare("
        SELECT id, monto_inicial, fecha_apertura 
        FROM turnos 
        WHERE user_id = ? 
        AND fecha_cierre IS NULL 
        ORDER BY fecha_apertura DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $turno_actual = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($turno_actual) {
        $_SESSION['turno_actual'] = $turno_actual['id'];
    } else {
        unset($_SESSION['turno_actual']);
    }
} catch (PDOException $e) {
    // Manejar el error de manera segura
    error_log("Error en la base de datos: " . $e->getMessage());
    $productos = [];
    $clientes = [];
}

// Verificar si la sesión está activa periódicamente
function checkSession()
{
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['valid' => false]);
        exit;
    }
    echo json_encode(['valid' => true]);
}

// Actualizar último acceso
function updateLastActivity()
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET ultimo_acceso = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Error actualizando último acceso: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
}

// Si es una solicitud AJAX para verificar la sesión
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    
    if ($_SERVER['REQUEST_URI'] === '/check_session.php') {
        checkSession();
        exit;
    }
    
    if ($_SERVER['REQUEST_URI'] === '/update_last_activity.php') {
        updateLastActivity();
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VendEasy POS</title>
    <link rel="icon" type="image/png" href="./favicon/favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="h-full font-sans antialiased">
    <?php if (isset($_SESSION['warning_message'])): ?>
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'Límite de Facturas',
            text: '<?= htmlspecialchars($_SESSION['warning_message']) ?>',
            showConfirmButton: true,
            confirmButtonText: 'Entendido',
            showCancelButton: true,
            cancelButtonText: 'Ver Planes',
            cancelButtonColor: '#3085d6'
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.cancel) {
                window.location.href = '/modules/empresa/planes.php';
            }
        });
    </script>
    <?php unset($_SESSION['warning_message']); endif; ?>

    <!-- Barra superior moderna -->
    <nav class="bg-white border-b border-gray-200 h-16 fixed w-full top-0 z-50">
        <div class="h-full px-4">
            <div class="flex justify-between items-center h-full">
                <!-- Sección izquierda -->
                <div class="flex items-center h-full">
                    <!-- Logo -->
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-cash-register text-indigo-600 text-xl"></i>
                        <span class="text-lg font-semibold text-gray-800">VendEasy POS</span>
                    </div>
                </div>

                <!-- Sección derecha -->
                <ul class="flex items-center h-full space-x-2">
                    <!-- Estado de conexión -->
                    <li class="h-full flex items-center">
                        <div id="connection-status" class="flex items-center px-3 py-1 rounded-full bg-green-50 border border-green-200">
                            <svg id="connection-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-green-500 mr-2">
                                <path d="M12 18l.01 0"></path>
                                <path d="M9.172 15.172a4 4 0 0 1 5.656 0"></path>
                                <path d="M6.343 12.343a8 8 0 0 1 11.314 0"></path>
                                <path d="M3.515 9.515c4.686 -4.687 12.284 -4.687 17 0"></path>
                            </svg>
                            <span id="connection-text" class="text-xs text-green-700 font-medium">Disponible</span>
                        </div>
                    </li>

                    <!-- Control de Turnos -->
                    <li class="h-full flex items-center">
                        <?php if (!$turno_actual): ?>
                        <!-- Botón Abrir Turno -->
                        <button onclick="abrirTurno()" class="flex items-center px-3 py-1.5 rounded-full bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 transition-colors">
                            <i class="fas fa-cash-register text-indigo-600 mr-2 text-sm"></i>
                            <span class="text-xs text-indigo-700 font-medium">Abrir Turno</span>
                        </button>
                        <?php else: ?>
                        <!-- Botón Cerrar Turno -->
                        <button onclick="cerrarTurno()" class="flex items-center px-3 py-1.5 rounded-full bg-yellow-50 border border-yellow-200 hover:bg-yellow-100 transition-colors">
                            <i class="fas fa-cash-register text-yellow-600 mr-2 text-sm"></i>
                            <span class="text-xs text-yellow-700 font-medium">Cerrar Turno</span>
                            <span class="ml-2 text-xs bg-yellow-200 text-yellow-800 px-2 py-0.5 rounded-full">
                                <?php 
                                $fecha_apertura = new DateTime($turno_actual['fecha_apertura']);
                                echo $fecha_apertura->format('H:i'); 
                                ?>
                            </span>
                        </button>
                        <?php endif; ?>
                    </li>

                    <!-- Reimprimir Facturas -->
                    <li class="h-full flex items-center">
                        <button onclick="mostrarFacturasElectronicas()" 
                            class="flex items-center px-3 py-1.5 rounded-full bg-blue-50 border border-blue-200 hover:bg-blue-100 transition-colors">
                            <i class="fas fa-receipt text-blue-600 mr-2 text-sm"></i>
                            <span class="text-xs text-blue-700 font-medium">Reimprimir Facturas</span>
                        </button>
                    </li>

                    <!-- Perfil de usuario simplificado -->
                    <li class="h-full flex items-center gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                <span class="text-sm font-medium text-indigo-600">
                                    <?= substr($user_name, 0, 1) ?>
                                </span>
                            </div>
                            <div class="hidden md:block text-left">
                                <p class="text-sm font-medium text-gray-700">
                                    <?= htmlspecialchars($user_name) ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?= htmlspecialchars($user_role) ?>
                                </p>
                            </div>
                        </div>
                        <button onclick="toggleProfileMenu()" class="ml-2 p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-full transition-colors">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido principal ajustado -->
    <div class="h-screen overflow-hidden">
        <div class="h-[calc(100vh-4rem)] mt-16">
            <!-- Contenedor principal con grid responsivo -->
            <div class="grid grid-cols-1 lg:grid-cols-12 h-full">
                <!-- Panel izquierdo: Productos -->
                <div class="lg:col-span-8 h-full flex flex-col bg-white shadow-sm border-r border-gray-200 overflow-hidden">
                    <!-- Barra de búsqueda mejorada -->
                    <div class="sticky top-0 z-10 bg-white p-4 border-b border-gray-200">
                        <div class="relative">
                            <input type="text"
                                id="buscar-producto"
                                class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200 placeholder-gray-400"
                                placeholder="Buscar productos por nombre o código de barras...">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Grid de productos mejorado con scroll -->
                    <div id="products-grid" class="flex-1 overflow-y-auto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 p-4">
                            <?php if (empty($productos)): ?>
                                <div class="col-span-full flex flex-col items-center justify-center py-12 text-gray-400">
                                    <i class="fas fa-box-open text-4xl mb-4 animate-bounce"></i>
                                    <p class="text-lg font-medium">No hay productos disponibles</p>
                                    <p class="text-sm text-gray-400 mt-2">Agrega productos desde el módulo de inventario</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($productos as $producto): ?>
                                    <div class="item-view flex flex-col bg-white rounded-lg shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer border border-gray-200 hover:border-indigo-200 relative hover-scale fade-in <?= $producto['stock'] <= 0 ? 'opacity-60' : '' ?>"
                                        data-id="<?= $producto['id'] ?>"
                                        data-nombre="<?= htmlspecialchars($producto['nombre']) ?>"
                                        data-precio="<?= $producto['precio_venta'] ?>"
                                        data-cantidad="<?= $producto['stock'] ?>"
                                        data-codigo="<?= htmlspecialchars($producto['codigo_barras']) ?>">

                                        <div class="item-view__image-zone relative overflow-hidden aspect-square bg-gray-50 rounded-t-lg">
                                            <!-- Código de barras superior -->
                                            <div class="absolute top-2 left-2 z-10">
                                                <span class="bg-white/90 backdrop-blur-sm text-gray-600 text-xs px-2 py-1 rounded-md inline-block shadow-sm">
                                                    <?= htmlspecialchars($producto['codigo_barras']) ?>
                                                </span>
                                            </div>

                                            <!-- Inventario -->
                                            <div class="absolute top-2 right-2 z-10">
                                                <?php if ($producto['stock'] <= 0): ?>
                                                    <span class="bg-red-100 text-red-700 text-xs px-2 py-1 rounded-md font-medium shadow-sm">
                                                        Agotado
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-1 rounded-md font-medium shadow-sm">
                                                        <?= $producto['stock'] ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($producto['stock'] <= 0): ?>
                                                <!-- Overlay para productos agotados -->
                                                <div class="absolute inset-0 bg-gray-900/50 flex items-center justify-center z-20">
                                                    <span class="bg-red-500 text-white px-4 py-2 rounded-lg font-medium transform -rotate-12 shadow-lg">
                                                        AGOTADO
                                                    </span>
                                                </div>
                                            <?php endif; ?>

                                            <?php
                                            // Ruta de la imagen por defecto (asegúrate de que esta ruta existe)
                                            $defaultImage = '../../assets/img/no-image.png';
                                            
                                            // Verificar si hay una imagen y construir la ruta
                                            $rutaImagen = !empty($producto['imagen_ruta']) 
                                                ? '/' . ltrim($producto['imagen_ruta'], '/') 
                                                : $defaultImage;
                                            ?>
                                            
                                            <img src="<?= htmlspecialchars($rutaImagen) ?>"
                                                alt="<?= htmlspecialchars($producto['nombre']) ?>"
                                                class="product-image w-full h-full object-cover object-center transition-transform duration-300 group-hover:scale-110"
                                                loading="lazy"
                                                onerror="this.src='<?= $defaultImage ?>'"
                                                <?= $producto['stock'] <= 0 ? 'style="filter: grayscale(100%)"' : '' ?>>
                                        </div>

                                        <div class="p-3 flex flex-col gap-2">
                                            <!-- Nombre del producto -->
                                            <p class="item-view__name text-sm text-gray-700 line-clamp-2 min-h-[2.5rem] leading-snug font-medium">
                                                <?= htmlspecialchars($producto['nombre']) ?>
                                            </p>

                                            <!-- Precio -->
                                            <div class="flex justify-between items-center">
                                                <p class="item-view__price text-lg font-bold <?= $producto['stock'] <= 0 ? 'text-gray-500' : 'text-indigo-600' ?>">
                                                    $<?= number_format($producto['precio_venta'], 0, ',', '.') ?>
                                                </p>
                                                <?php if ($producto['stock'] <= $producto['stock_minimo'] && $producto['stock'] > 0): ?>
                                                    <span class="text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded-md">
                                                        Stock bajo
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Panel derecho: Carrito mejorado -->
                <div class="lg:col-span-4 h-full flex flex-col bg-white shadow-sm border-l border-gray-200 overflow-hidden">
                    <?php if (!$turno_actual): ?>
                    <!-- Mensaje cuando no hay turno abierto -->
                    <div class="h-full flex flex-col items-center justify-center p-8 text-center">
                        <div class="bg-yellow-50 rounded-full p-4 mb-4">
                            <i class="fas fa-cash-register text-yellow-500 text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No hay un turno abierto</h3>
                        <p class="text-gray-500 mb-4">Debes abrir un turno antes de realizar ventas</p>
                        <button onclick="abrirTurno()" 
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-cash-register mr-2"></i>
                            Abrir Turno
                        </button>
                    </div>
                    <?php else: ?>
                    <!-- Encabezado del panel -->
                    <div class="bg-gray-50 border-b border-gray-200 py-2">
                        <div class="px-3 space-y-2">
                            <!-- Tipo documento y Numeración -->
                            <div class="grid grid-cols-2 gap-2">
                                <select id="tipo-documento" class="w-full border border-gray-300 rounded-lg text-xs py-1.5 px-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                    <option value="factura">Factura de venta</option>
                                    <option value="cotizacion">Cotización</option>
                                </select>
                                <select id="numeracion" class="w-full border border-gray-300 rounded-lg text-xs py-1.5 px-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                    <option value="principal">Principal</option>
                                    <option value="electronica">Electrónica</option>
                                </select>
                            </div>

                            <!-- Cliente -->
                            <div class="flex gap-2">
                                <select id="cliente-select" class="flex-1 border border-gray-300 rounded-lg text-xs py-1.5 px-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= $cliente['id'] ?>" <?= $cliente['id'] == '1' ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(trim($cliente['nombre'])) ?> - <?= htmlspecialchars($cliente['documento']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="../clientes/index.php" class="px-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600 transition-all duration-200 flex items-center justify-center hover:border-indigo-300">
                                    <i class="fas fa-plus"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de productos con scroll -->
                    <div class="flex-1 flex flex-col overflow-hidden">
                        <div class="py-2 px-3 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h5 class="text-xs font-medium flex items-center text-gray-700">
                                    <i class="fas fa-shopping-cart mr-2 text-indigo-500"></i>Productos
                                </h5>
                                <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2 py-0.5 rounded-lg">
                                    <span id="cantidad-items">0</span> items
                                </span>
                            </div>
                        </div>

                        <!-- Tabla de productos con scroll mejorado -->
                        <div class="flex-1 overflow-y-auto px-3">
                            <div class="min-w-full">
                                <table class="w-full">
                                    <thead class="text-xs text-gray-700 sticky top-0 bg-white z-10">
                                        <tr>
                                            <th class="text-left py-2 bg-white">Producto</th>
                                            <th class="text-center w-24 bg-white">Cant.</th>
                                            <th class="text-right w-20 bg-white">Precio</th>
                                            <th class="text-right w-20 bg-white">Total</th>
                                            <th class="w-8 bg-white"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="venta-lista" class="divide-y divide-gray-100">
                                        <!-- Los items se agregarán dinámicamente aquí -->
                                    </tbody>
                                </table>
                                <div id="venta-lista-empty" class="text-center text-gray-500 py-8">
                                    <i class="fas fa-shopping-basket text-3xl mb-3 text-gray-300"></i>
                                    <p class="text-sm">Aquí verás los productos de tu venta</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel de totales y acciones compacto -->
                    <div class="border-t border-gray-200">
                        <div class="p-3 bg-gray-50 space-y-2">
                            <!-- Totales en grid compacto -->
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span id="subtotal" class="font-medium">$0</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Descuento:</span>
                                    <div class="flex items-center gap-1">
                                        <input type="number" id="descuento"
                                            class="w-12 border border-gray-300 rounded px-1 py-0.5 text-center text-xs focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                            min="0" max="100" value="0">
                                        <span class="text-gray-500">%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Desc. aplicado:</span>
                                    <span id="descuento-monto" class="text-red-600">-$0</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="font-medium">Total:</span>
                                    <span id="venta-total" class="text-lg font-bold text-indigo-600">$0</span>
                                </div>
                            </div>

                            <!-- Método de pago -->
                            <select id="metodo-pago" class="w-full border border-gray-300 rounded-lg text-xs py-1.5 px-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                            </select>

                            <!-- Botón procesar -->
                            <button id="procesar-venta"
                                class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white py-2 px-4 rounded-lg transition-all duration-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transform hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 shadow-sm hover:shadow-md flex items-center justify-center space-x-2"
                                disabled>
                                <i class="fas fa-check-circle"></i>
                                <span>Confirmar Venta</span>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateConnectionStatus() {
            const statusDiv = document.getElementById("connection-status");
            const statusIcon = document.getElementById("connection-icon");
            const statusText = document.getElementById("connection-text");

            if (navigator.onLine) {
                // Online state
                statusDiv.className = "flex items-center px-3 py-1 rounded-full bg-green-50 border border-green-200";
                statusIcon.className = "text-green-500 mr-2";
                statusText.className = "text-xs text-green-700 font-medium";
                statusText.textContent = "Disponible";
            } else {
                // Offline state
                statusDiv.className = "flex items-center px-3 py-1 rounded-full bg-red-50 border border-red-200";
                statusIcon.className = "text-red-500 mr-2";
                statusText.className = "text-xs text-red-700 font-medium";
                statusText.textContent = "Sin conexión";
            }
        }

        // Initial status check
        updateConnectionStatus();

        // Listen for online and offline events
        window.addEventListener("online", updateConnectionStatus);
        window.addEventListener("offline", updateConnectionStatus);

        document.addEventListener('DOMContentLoaded', function() {
            // Verificar estado de la sesión periódicamente
            setInterval(function() {
                fetch('check_session.php')
                    .then(response => response.json())
                    .then(data => {
                        if (!data.valid) {
                            window.location.href = 'auth/login.php?mensaje=sesion_expirada';
                        }
                    })
                    .catch(error => {
                        console.error('Error verificando sesión:', error);
                    });
            }, 300000); // Verificar cada 5 minutos

            // Actualizar último acceso periódicamente
            setInterval(function() {
                fetch('update_last_activity.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).catch(error => {
                    console.error('Error actualizando actividad:', error);
                });
            }, 60000); // Actualizar cada minuto
        });

        function toggleProfileMenu() {
            // Redirigir directamente al logout
            window.location.href = 'auth/logout.php';
        }

        // Variable global para controlar el estado del turno
        window.turnoActivo = <?= $turno_actual ? 'true' : 'false' ?>;

        // Función para abrir turno
        function abrirTurno() {
            Swal.fire({
                title: 'Abrir Turno',
                html: `
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Monto Inicial</label>
                        <input type="number" id="monto_inicial" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="0.00" step="0.01" min="0">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Abrir Turno',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#4F46E5',
                cancelButtonColor: '#6B7280',
                preConfirm: () => {
                    const montoInicial = document.getElementById('monto_inicial').value;
                    if (!montoInicial || montoInicial < 0) {
                        Swal.showValidationMessage('Por favor ingrese un monto inicial válido');
                        return false;
                    }
                    return { monto_inicial: montoInicial };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/turnos/abrir.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(result.value)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.turnoActivo = true;
                            Swal.fire({
                                icon: 'success',
                                title: '¡Turno abierto!',
                                text: 'El turno se ha abierto correctamente.',
                                confirmButtonColor: '#4F46E5'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al abrir el turno');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message,
                            confirmButtonColor: '#EF4444'
                        });
                    });
                }
            });
        }

        // Función para cerrar turno
        function cerrarTurno() {
            Swal.fire({
                title: '¿Cerrar turno actual?',
                text: 'Deberás realizar el conteo de caja antes de cerrar el turno',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, cerrar turno',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#4F46E5',
                cancelButtonColor: '#6B7280',
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/turnos/obtener_total.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            mostrarFormularioCierre(data.total, data.monto_inicial, data.detalles);
                        } else {
                            throw new Error(data.message || 'Error al obtener el total del turno');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message,
                            confirmButtonColor: '#EF4444'
                        });
                    });
                }
            });
        }

        // Función para mostrar el formulario de cierre
        function mostrarFormularioCierre(totalSistema, montoInicial, detalles) {
            Swal.fire({
                title: 'Cierre de Turno',
                html: `
                    <div class="space-y-4">
                        <div class="text-left space-y-2">
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-sm font-medium text-gray-700">Resumen del Turno</p>
                                <div class="mt-2 space-y-1">
                                    <p class="text-sm text-gray-600">Monto Inicial: $${montoInicial.toLocaleString('es-CO')}</p>
                                    <p class="text-sm text-gray-600">Total Ventas: ${detalles.total_ventas}</p>
                                    <div class="border-t border-gray-200 my-2"></div>
                                    <p class="text-sm text-gray-600">Efectivo: $${detalles.efectivo.toLocaleString('es-CO')}</p>
                                    <p class="text-sm text-gray-600">Tarjeta: $${detalles.tarjeta.toLocaleString('es-CO')}</p>
                                    <p class="text-sm text-gray-600">Transferencia: $${detalles.transferencia.toLocaleString('es-CO')}</p>
                                    <div class="border-t border-gray-200 my-2"></div>
                                    <p class="text-sm font-medium text-gray-700">Total Sistema: $${totalSistema.toLocaleString('es-CO')}</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Monto Final (Conteo)</label>
                            <input type="number" id="monto_final" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" step="0.01" min="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                            <textarea id="observaciones" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" rows="3"></textarea>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Cerrar Turno',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#4F46E5',
                cancelButtonColor: '#6B7280',
                width: '32rem',
                preConfirm: () => {
                    const montoFinal = document.getElementById('monto_final').value;
                    const observaciones = document.getElementById('observaciones').value;
                    
                    if (!montoFinal || montoFinal < 0) {
                        Swal.showValidationMessage('Por favor ingrese un monto final válido');
                        return false;
                    }
                    
                    return {
                        monto_final: montoFinal,
                        observaciones: observaciones
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/turnos/cerrar.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(result.value)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.turnoActivo = false;
                            Swal.fire({
                                icon: 'success',
                                title: '¡Turno cerrado!',
                                text: 'El turno se ha cerrado correctamente.',
                                confirmButtonColor: '#4F46E5'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al cerrar el turno');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message,
                            confirmButtonColor: '#EF4444'
                        });
                    });
                }
            });
        }

        function imprimirRemision() {
            Swal.fire({
                title: '¿Desea imprimir la remisión?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, imprimir',
                cancelButtonText: 'No',
                confirmButtonColor: '#4F46E5',
                cancelButtonColor: '#6B7280',
            }).then((result) => {
                if (result.isConfirmed) {
                    // Aquí va la lógica para imprimir la remisión
                    imprimirTicket();
                } else {
                    imprimirTicket();
                }
            });
        }

        function imprimirTicket() {
            Swal.fire({
                title: '¿Desea imprimir el ticket de venta?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, imprimir',
                cancelButtonText: 'No',
                confirmButtonColor: '#4F46E5',
                cancelButtonColor: '#6B7280',
            }).then((result) => {
                if (result.isConfirmed) {
                    // Aquí va la lógica para imprimir el ticket
                    preguntarEnvioCorreo();
                } else {
                    preguntarEnvioCorreo();
                }
            });
        }

        function preguntarEnvioCorreo() {
            Swal.fire({
                title: '¿Desea enviar la factura por correo electrónico?',
                icon: 'question',
                input: 'email',
                inputLabel: 'Correo electrónico',
                inputPlaceholder: 'Ingrese el correo electrónico',
                showCancelButton: true,
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'No',
                confirmButtonColor: '#4F46E5',
                cancelButtonColor: '#6B7280',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Debe ingresar un correo electrónico';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarFacturaPorCorreo(result.value);
                }
            });
        }

        function enviarFacturaPorCorreo(email) {
            // Mostrar indicador de carga
            Swal.fire({
                title: 'Enviando factura...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Realizar la petición a la API de Alegra
            fetch('api/alegra/enviar_factura_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    facturaId: window.ultimaFacturaId // Asegúrate de tener esta variable disponible
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Correo enviado!',
                        text: 'La factura ha sido enviada correctamente',
                        confirmButtonColor: '#4F46E5'
                    });
                } else {
                    throw new Error(data.message || 'Error al enviar el correo');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message,
                    confirmButtonColor: '#EF4444'
                });
            });
        }

        // Función para procesar la venta
        async function procesarVenta() {
            if (!carrito.items.length || !document.getElementById('cliente-select').value) {
                Swal.fire({
                    title: 'Carrito vacío o cliente no seleccionado',
                    text: 'Agregue productos al carrito y seleccione un cliente antes de continuar',
                    icon: 'warning'
                });
                return;
            }

            const tipoDocumento = document.getElementById('tipo-documento').value;
            const clienteId = document.getElementById('cliente-select').value;
            const metodoPago = document.getElementById('metodo-pago').value;
            const descuentoPorcentaje = parseInt(document.getElementById('descuento').value) || 0;

            // Calcular totales
            let subtotal = 0;
            carrito.items.forEach(item => {
                subtotal += item.precio * item.cantidad;
            });
            
            const descuentoMonto = (subtotal * descuentoPorcentaje) / 100;
            const total = subtotal - descuentoMonto;

            try {
                const response = await fetch('api/ventas/procesar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        items: carrito.items,
                        cliente_id: clienteId,
                        tipo_documento: tipoDocumento,
                        numeracion: document.getElementById('numeracion').value,
                        metodo_pago: metodoPago,
                        descuento: descuentoPorcentaje, // Enviamos el porcentaje de descuento
                        subtotal: subtotal,
                        total: total
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Venta realizada!',
                        text: 'La venta se ha procesado correctamente'
                    }).then(() => {
                        // Limpiar carrito y recargar
                        carrito.items = [];
                        actualizarCarritoUI();
                        location.reload();
                    });
                } else {
                    throw new Error(data.message || 'Error al procesar la venta');
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            }
        }

        // Añadir la validación para el tipo de factura y cliente
        document.addEventListener('DOMContentLoaded', function() {
            const tipoDocumento = document.getElementById('tipo-documento');
            const numeracion = document.getElementById('numeracion');
            const clienteSelect = document.getElementById('cliente-select');

            function validarFacturaElectronica() {
                if (numeracion.value === 'electronica') {
                    // Ajusta este ID según el ID real del consumidor final en tu base de datos
                    const consumidorFinalId = "1"; 
                    const consumidorFinal = clienteSelect.querySelector(`option[value="${consumidorFinalId}"]`);
                    if (consumidorFinal) {
                        consumidorFinal.disabled = true;
                        if (clienteSelect.value === consumidorFinalId) {
                            clienteSelect.value = "";
                        }
                    }
                } else {
                    Array.from(clienteSelect.options).forEach(option => {
                        option.disabled = false;
                    });
                }
            }

            numeracion.addEventListener('change', validarFacturaElectronica);
            validarFacturaElectronica(); // Validar al cargar
        });

        function mostrarFacturasElectronicas() {
            // Mostrar loading
            Swal.fire({
                title: 'Cargando facturas...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Obtener facturas
            fetch('api/alegra/obtener_facturas.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarListaFacturas(data.facturas);
                    } else {
                        throw new Error(data.message || 'Error al obtener las facturas');
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message,
                        confirmButtonColor: '#EF4444'
                    });
                });
        }

        function mostrarListaFacturas(facturas) {
            let html = `
                <div class="max-h-[400px] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Número</th>
                                <th class="px-4 py-2 text-left">Cliente</th>
                                <th class="px-4 py-2 text-right">Total</th>
                                <th class="px-4 py-2 text-center">Fecha</th>
                                <th class="px-4 py-2 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            facturas.forEach(factura => {
                // Formatear el número de factura
                const numeroFactura = factura.numberTemplate ? 
                    (factura.numberTemplate.prefix || '') + factura.numberTemplate.number : 
                    factura.number;

                html += `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2">${numeroFactura}</td>
                        <td class="px-4 py-2">${factura.client ? factura.client.name : 'N/A'}</td>
                        <td class="px-4 py-2 text-right">$${Number(factura.total).toLocaleString('es-CO')}</td>
                        <td class="px-4 py-2 text-center">${new Date(factura.date).toLocaleDateString('es-CO')}</td>
                        <td class="px-4 py-2 text-center">
                            <button onclick="enviarFacturaEmail('${factura.id}')" 
                                class="bg-blue-100 text-blue-700 px-2 py-1 rounded-md hover:bg-blue-200 transition-colors">
                                <i class="fas fa-envelope"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;

            Swal.fire({
                title: 'Facturas Electrónicas',
                html: html,
                width: '800px',
                showCloseButton: true,
                showConfirmButton: false
            });
        }

        function reimprimirFactura(facturaId) {
            Swal.fire({
                title: 'Reimprimiendo factura...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('api/alegra/obtener_factura.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ facturaId: facturaId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Aquí puedes implementar la lógica de impresión
                    Swal.fire({
                        icon: 'success',
                        title: 'Factura reimpresa',
                        text: 'La factura se ha reimpreso correctamente',
                        confirmButtonColor: '#4F46E5'
                    });
                } else {
                    throw new Error(data.message || 'Error al reimprimir la factura');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message,
                    confirmButtonColor: '#EF4444'
                });
            });
        }

        function enviarFacturaEmail(facturaId) {
            Swal.fire({
                title: 'Enviar Factura por Correo',
                html: `
                    <div class="mb-4">
                        <input type="email" id="email-factura" class="swal2-input" placeholder="Correo electrónico">
                    </div>
                    <div class="mb-4">
                        <input type="text" id="asunto-correo" class="swal2-input" placeholder="Asunto del correo">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const email = document.getElementById('email-factura').value;
                    const asunto = document.getElementById('asunto-correo').value;
                    
                    if (!email) {
                        Swal.showValidationMessage('Por favor ingrese un correo electrónico');
                        return false;
                    }
                    
                    return { email, asunto };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarCorreoFactura(facturaId, result.value.email, result.value.asunto);
                }
            });
        }

        function enviarCorreoFactura(facturaId, email, asunto) {
            Swal.fire({
                title: 'Enviando factura...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('api/alegra/enviar_factura_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    facturaId: facturaId,
                    email: email,
                    asunto: asunto
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Correo enviado!',
                        text: 'La factura ha sido enviada correctamente',
                        confirmButtonColor: '#4F46E5'
                    });
                } else {
                    throw new Error(data.message || 'Error al enviar el correo');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message,
                    confirmButtonColor: '#EF4444'
                });
            });
        }

        // Función para agregar producto al carrito
        function agregarProductoAlCarrito(producto) {
            const listaVenta = document.getElementById('venta-lista');
            const listaEmpty = document.getElementById('venta-lista-empty');
            const cantidadItems = document.getElementById('cantidad-items');
            
            // Verificar si el producto ya está en el carrito
            const productoExistente = document.querySelector(`tr[data-id="${producto.id}"]`);
            
            if (productoExistente) {
                // Incrementar cantidad si el producto ya existe
                const cantidadInput = productoExistente.querySelector('input[type="number"]');
                const cantidadActual = parseInt(cantidadInput.value);
                if (cantidadActual < producto.cantidad) {
                    cantidadInput.value = cantidadActual + 1;
                    actualizarTotalProducto(productoExistente);
                }
            } else {
                // Agregar nuevo producto
                const tr = document.createElement('tr');
                tr.setAttribute('data-id', producto.id);
                tr.setAttribute('data-precio', producto.precio);
                tr.innerHTML = `
                    <td class="py-2">
                        <div class="flex flex-col">
                            <span class="font-medium text-xs text-gray-900">${producto.nombre}</span>
                            <span class="text-xs text-gray-500">${producto.codigo}</span>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="flex items-center justify-center gap-1">
                            <button onclick="decrementarCantidad(this)" 
                                class="text-gray-500 hover:text-indigo-600 p-1 rounded-full hover:bg-indigo-50">
                                <i class="fas fa-minus text-xs"></i>
                            </button>
                            <input type="number" 
                                class="w-12 text-center border border-gray-300 rounded px-1 py-0.5 text-xs"
                                min="1" 
                                max="${producto.cantidad}"
                                value="1"
                                onchange="validarCantidad(this, ${producto.cantidad})"
                                onkeyup="this.value=this.value.replace(/[^\\d]/,'')">
                            <button onclick="incrementarCantidad(this)" 
                                class="text-gray-500 hover:text-indigo-600 p-1 rounded-full hover:bg-indigo-50">
                                <i class="fas fa-plus text-xs"></i>
                            </button>
                        </div>
                    </td>
                    <td class="text-right text-xs">
                        $${Number(producto.precio).toLocaleString('es-CO')}
                    </td>
                    <td class="text-right text-xs font-medium">
                        $${Number(producto.precio).toLocaleString('es-CO')}
                    </td>
                    <td>
                        <button onclick="eliminarProducto(this.closest('tr'))" 
                            class="text-red-600 hover:text-red-800 p-1 rounded-full hover:bg-red-50">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                `;
                
                listaVenta.appendChild(tr);
            }
            
            // Actualizar UI
            actualizarTotales();
            actualizarVisibilidadCarritoVacio();
            
            // Habilitar botón de procesar venta
            document.getElementById('procesar-venta').disabled = false;
        }

        // Funciones para manipular cantidades
        function decrementarCantidad(button) {
            const input = button.parentElement.querySelector('input');
            const currentValue = parseInt(input.value);
            if (currentValue > 1) {
                input.value = currentValue - 1;
                actualizarTotalProducto(button.closest('tr'));
            }
        }

        function incrementarCantidad(button) {
            const input = button.parentElement.querySelector('input');
            const currentValue = parseInt(input.value);
            const maxValue = parseInt(input.getAttribute('max'));
            if (currentValue < maxValue) {
                input.value = currentValue + 1;
                actualizarTotalProducto(button.closest('tr'));
            }
        }

        function validarCantidad(input, maxCantidad) {
            let valor = parseInt(input.value) || 0;
            if (valor < 1) valor = 1;
            if (valor > maxCantidad) valor = maxCantidad;
            input.value = valor;
            actualizarTotalProducto(input.closest('tr'));
        }

        // Función para eliminar producto
        function eliminarProducto(tr) {
            tr.remove();
            actualizarTotales();
            actualizarVisibilidadCarritoVacio();
            
            // Deshabilitar botón si no hay productos
            const productos = document.querySelectorAll('#venta-lista tr');
            if (productos.length === 0) {
                document.getElementById('procesar-venta').disabled = true;
            }
        }

        // Función para actualizar el total de un producto
        function actualizarTotalProducto(tr) {
            const cantidad = parseInt(tr.querySelector('input[type="number"]').value);
            const precio = parseFloat(tr.getAttribute('data-precio'));
            const total = cantidad * precio;
            
            tr.querySelector('td:nth-child(4)').textContent = `$${total.toLocaleString('es-CO')}`;
            actualizarTotales();
        }

        // Función para actualizar los totales
        function actualizarTotales() {
            let subtotal = 0;
            const productos = document.querySelectorAll('#venta-lista tr');
            const cantidadItems = document.getElementById('cantidad-items');
            
            productos.forEach(tr => {
                const cantidad = parseInt(tr.querySelector('input[type="number"]').value);
                const precio = parseFloat(tr.getAttribute('data-precio'));
                subtotal += cantidad * precio;
            });
            
            // Actualizar contador de items
            cantidadItems.textContent = productos.length;
            
            // Actualizar subtotal
            document.getElementById('subtotal').textContent = `$${subtotal.toLocaleString('es-CO')}`;
            
            // Calcular y actualizar descuento
            const descuentoPorcentaje = parseInt(document.getElementById('descuento').value) || 0;
            const descuentoMonto = (subtotal * descuentoPorcentaje) / 100;
            document.getElementById('descuento-monto').textContent = `-$${descuentoMonto.toLocaleString('es-CO')}`;
            
            // Actualizar total
            const total = subtotal - descuentoMonto;
            document.getElementById('venta-total').textContent = `$${total.toLocaleString('es-CO')}`;
        }

        // Función para actualizar visibilidad del mensaje de carrito vacío
        function actualizarVisibilidadCarritoVacio() {
            const listaVenta = document.getElementById('venta-lista');
            const listaEmpty = document.getElementById('venta-lista-empty');
            
            if (listaVenta.children.length > 0) {
                listaEmpty.style.display = 'none';
            } else {
                listaEmpty.style.display = 'block';
            }
        }

        // Inicializar eventos
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar evento a los productos
            document.querySelectorAll('.item-view').forEach(item => {
                item.addEventListener('click', function() {
                    const producto = {
                        id: this.dataset.id,
                        nombre: this.dataset.nombre,
                        precio: this.dataset.precio,
                        cantidad: parseInt(this.dataset.cantidad),
                        codigo: this.dataset.codigo
                    };
                    
                    if (producto.cantidad > 0) {
                        agregarProductoAlCarrito(producto);
                    }
                });
            });
            
            // Evento para el input de descuento
            document.getElementById('descuento').addEventListener('input', function() {
                if (this.value > 100) this.value = 100;
                if (this.value < 0) this.value = 0;
                actualizarTotales();
            });

            // Seleccionar cliente por defecto (Consumidor Final)
            const clienteSelect = document.getElementById('cliente-select');
            if (clienteSelect) {
                const consumidorFinalOption = Array.from(clienteSelect.options).find(option => option.value === '1');
                if (consumidorFinalOption) {
                    clienteSelect.value = '1';
                }
            }
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="js/index.js" defer></script>
    <script src="js/header.js" defer></script>
    <script src="js/pos.js"></script>
</body>

</html>