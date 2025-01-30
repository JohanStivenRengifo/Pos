<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../../config/db.php';

// Obtener información del usuario
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Usuario'; // Valor por defecto
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
</head>

<body class="h-full font-sans antialiased">
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

                    <!-- Perfil de usuario -->
                    <li class="h-full flex items-center relative">
                        <button onclick="togglePerfilMenu()" class="flex items-center space-x-2 px-3 py-2 rounded-full hover:bg-gray-100 transition-colors">
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
                        </button>
                        
                        <!-- Menú desplegable del perfil -->
                        <div id="perfil-menu" class="hidden absolute right-0 top-full mt-1 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                            <div class="py-1">
                                <div class="px-4 py-2 text-sm text-gray-700 border-b">
                                    <p class="font-medium"><?= htmlspecialchars($user_name) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($user_email) ?></p>
                                </div>
                                <a href="perfil/index.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user-circle mr-2"></i> Mi Perfil
                                </a>
                                <a href="configuracion/index.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-cog mr-2"></i> Configuración
                                </a>
                                <button onclick="cerrarSesion()" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Cerrar Sesión
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido principal ajustado -->
    <div class="pt-16 h-[calc(100vh-4rem)] overflow-hidden">
        <div class="h-full">
            <div class="flex flex-col lg:flex-row h-full">
                <!-- Panel izquierdo: Productos -->
                <div class="w-full lg:w-[65%] h-full flex flex-col bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
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

                    <!-- Grid de productos mejorado -->
                    <div id="products-grid" class="flex-1 overflow-y-auto">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4 p-4">
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
                                            $defaultImage = 'assets/img/no-image.png';
                                            
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
                <div class="w-full lg:w-[35%] h-full flex flex-col bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
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
                    <div class="bg-gray-50 border-b border-gray-200">
                        <div class="p-4 space-y-3">
                            <!-- Tipo documento y Numeración -->
                            <div class="flex gap-2">
                                <select id="tipo-documento" class="flex-1 border border-gray-300 rounded-lg text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                    <option value="factura">Factura de venta</option>
                                    <option value="cotizacion">Cotización</option>
                                </select>
                                <select id="numeracion" class="flex-1 border border-gray-300 rounded-lg text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                    <option value="principal">Principal</option>
                                    <option value="electronica">Electrónica</option>
                                </select>
                            </div>

                            <!-- Cliente -->
                            <div class="flex gap-2">
                                <select id="cliente-select" class="flex-1 border border-gray-300 rounded-lg text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                    <option value="">-- Seleccione un cliente --</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= $cliente['id'] ?>">
                                            <?= htmlspecialchars(trim($cliente['nombre'])) ?> - <?= htmlspecialchars($cliente['documento']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="../clientes/index.php" class="px-3 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600 transition-all duration-200 flex items-center justify-center hover:border-indigo-300">
                                    <i class="fas fa-plus"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de productos -->
                    <div class="flex-1 flex flex-col overflow-hidden">
                        <div class="p-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h5 class="text-sm font-medium flex items-center text-gray-700">
                                    <i class="fas fa-shopping-cart mr-2 text-indigo-500"></i>Productos
                                </h5>
                                <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2 py-0.5 rounded-lg">
                                    <span id="cantidad-items">0</span> items
                                </span>
                            </div>
                        </div>

                        <!-- Tabla de productos con scroll -->
                        <div class="flex-1 overflow-y-auto">
                            <div class="p-4">
                                <table class="w-full">
                                    <thead class="text-xs text-gray-700">
                                        <tr>
                                            <th class="text-left py-2">Producto</th>
                                            <th class="text-center w-20">Cant.</th>
                                            <th class="text-right w-24">Precio</th>
                                            <th class="text-right w-24">Total</th>
                                            <th class="w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="venta-lista"></tbody>
                                </table>
                                <div id="venta-lista-empty" class="text-center text-gray-500 py-8">
                                    <i class="fas fa-shopping-basket text-3xl mb-3 text-gray-300"></i>
                                    <p class="text-sm">Aquí verás los productos de tu venta</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel de totales y acciones -->
                    <div class="border-t border-gray-200">
                        <div class="p-4 bg-gray-50 space-y-4">
                            <!-- Totales -->
                            <div class="space-y-2">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span id="subtotal" class="font-medium">$0</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 text-sm">Descuento:</span>
                                    <div class="flex items-center gap-2">
                                        <input type="number" id="descuento"
                                            class="w-16 border border-gray-300 rounded-lg px-2 py-1 text-center text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                            min="0" max="100" value="0">
                                        <span class="text-sm text-gray-500">%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">Desc. aplicado:</span>
                                    <span id="descuento-monto" class="text-red-600">-$0</span>
                                </div>
                                <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                                    <span class="font-medium">Total:</span>
                                    <span id="venta-total" class="text-xl font-bold text-indigo-600">$0</span>
                                </div>
                            </div>

                            <!-- Método de pago -->
                            <select id="metodo-pago" class="w-full border border-gray-300 rounded-lg text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                            </select>

                            <!-- Botón procesar -->
                            <button id="procesar-venta"
                                class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white py-3 px-4 rounded-lg transition-all duration-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transform hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 shadow-sm hover:shadow-md flex items-center justify-center space-x-2"
                                disabled>
                                <i class="fas fa-check-circle text-lg"></i>
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

        function togglePerfilMenu() {
            const menu = document.getElementById('perfil-menu');
            menu.classList.toggle('hidden');
        }

        // Cerrar el menú cuando se hace clic fuera de él
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('perfil-menu');
            const perfilButton = event.target.closest('button[onclick="togglePerfilMenu()"]');
            
            if (!perfilButton && !menu.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });

        // Función mejorada para cerrar sesión
        function cerrarSesion() {
            Swal.fire({
                title: '¿Cerrar sesión?',
                text: "¿Estás seguro de que deseas salir?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4F46E5',
                cancelButtonColor: '#EF4444',
                confirmButtonText: 'Sí, cerrar sesión',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'auth/logout.php';
                }
            });
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