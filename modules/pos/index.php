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
<html lang="es" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VendEasy POS</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>

    <!-- Alpine.js para interactividad -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Scripts propios -->
    <script src="js/index.js" defer></script>
    <script src="js/sidebar.js" defer></script>
    <script src="js/header.js" defer></script>

    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }

        .hover-scale {
            transition: transform 0.2s ease-in-out;
        }
        
        .hover-scale:hover {
            transform: scale(1.02);
        }

        /* Scrollbar personalizado */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body class="h-full font-sans antialiased">
    <!-- Barra superior moderna -->
    <nav class="bg-white border-b border-gray-200 h-16 fixed w-full top-0 z-50">
        <div class="h-full px-4">
            <div class="flex justify-between items-center h-full">
                <!-- Sección izquierda -->
                <div class="flex items-center h-full">
                    <!-- Botón de menú -->
                    <button id="sidebar-toggle" class="mr-3 text-gray-500 hover:text-gray-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 6l16 0"></path>
                            <path d="M4 12l16 0"></path>
                            <path d="M4 18l16 0"></path>
                        </svg>
                    </button>
                    <!-- Título de la página -->
                    <p class="text-gray-600 font-medium">Ventas</p>
                </div>

                <!-- Sección derecha -->
                <ul class="flex items-center h-full space-x-2">
                    <!-- Estado de conexión -->
                    <li class="h-full flex items-center">
                        <div class="flex items-center px-3 py-1 rounded-full bg-green-50 border border-green-200">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-green-500 mr-2">
                                <path d="M12 18l.01 0"></path>
                                <path d="M9.172 15.172a4 4 0 0 1 5.656 0"></path>
                                <path d="M6.343 12.343a8 8 0 0 1 11.314 0"></path>
                                <path d="M3.515 9.515c4.686 -4.687 12.284 -4.687 17 0"></path>
                            </svg>
                            <span class="text-xs text-green-700 font-medium">Disponible</span>
                        </div>
                    </li>

                    <!-- Botón de sincronización -->
                    <li class="h-full flex items-center">
                        <button onclick="sincronizarDatos()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors" title="Sincronizar datos">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-gray-600">
                                <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"></path>
                                <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"></path>
                            </svg>
                        </button>
                    </li>

                    <!-- Botón de historial -->
                    <li class="h-full flex items-center">
                        <button onclick="window.location.href='../ventas/historial.php'" class="p-2 hover:bg-gray-100 rounded-lg transition-colors" title="Historial de ventas">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-gray-600">
                                <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2"></path>
                                <path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z"></path>
                                <path d="M9 12l.01 0"></path>
                                <path d="M13 12l2 0"></path>
                                <path d="M9 16l.01 0"></path>
                                <path d="M13 16l2 0"></path>
                            </svg>
                        </button>
                    </li>

                    <!-- Botón de ayuda -->
                    <li class="h-full flex items-center">
                        <button onclick="mostrarAyuda()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors" title="Ayuda">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-gray-600">
                                <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path>
                                <path d="M12 17l0 .01"></path>
                                <path d="M12 13.5a1.5 1.5 0 0 1 1 -1.5a2.6 2.6 0 1 0 -3 -4"></path>
                            </svg>
                        </button>
                    </li>

                    <!-- Botón de menú -->
                    <li class="h-full flex items-center">
                        <button onclick="toggleMenuOpciones()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors" title="Más opciones">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-gray-600">
                                <path d="M5 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                <path d="M12 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                <path d="M19 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                <path d="M5 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                <path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                <path d="M19 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                <path d="M5 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                <path d="M12 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                <path d="M19 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                            </svg>
                        </button>
                        <!-- Menú desplegable de opciones -->
                        <div id="menuOpciones" class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu">
                            <a href="../config/perfil.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Configuración</a>
                            <a href="../ayuda/index.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Centro de ayuda</a>
                            <div class="border-t border-gray-100"></div>
                            <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100" role="menuitem">Cerrar sesión</a>
                        </div>
                    </li>

                    <!-- Perfil de usuario -->
                    <li class="h-full flex items-center relative">
                        <button onclick="togglePerfilMenu()" class="flex items-center space-x-2 px-3 py-2 rounded-full hover:bg-gray-100 transition-colors">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                <span class="text-sm font-medium text-indigo-600">
                                    <?= substr($_SESSION['nombre'] ?? $_SESSION['user_name'] ?? 'U', 0, 1) ?>
                                </span>
                            </div>
                            <div class="hidden md:block text-left">
                                <p class="text-sm font-medium text-gray-700">
                                    <?= $_SESSION['nombre'] ?? $_SESSION['user_name'] ?? 'Usuario' ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?= $_SESSION['rol'] ?? 'Usuario' ?>
                                </p>
                            </div>
                        </button>
                        <!-- Menú desplegable del perfil -->
                        <div id="perfilMenu" class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu">
                            <div class="px-4 py-2">
                                <p class="text-sm font-medium text-gray-900"><?= $_SESSION['nombre'] ?? $_SESSION['user_name'] ?? 'Usuario' ?></p>
                                <p class="text-xs text-gray-500 truncate"><?= $_SESSION['email'] ?? '' ?></p>
                            </div>
                            <div class="border-t border-gray-100"></div>
                            <a href="../config/perfil.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Mi perfil</a>
                            <a href="../config/preferencias.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Preferencias</a>
                            <div class="border-t border-gray-100"></div>
                            <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100" role="menuitem">Cerrar sesión</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Menú lateral -->
    <div id="sidebar" class="fixed left-0 top-16 h-[calc(100vh-4rem)] w-64 bg-white border-r border-gray-200 transform -translate-x-full transition-transform duration-300 ease-in-out z-40">
        <div class="p-4">
            <div class="space-y-4">
                <!-- Logo -->
                <div class="flex items-center space-x-3 mb-6">
                    <i class="fas fa-cash-register text-indigo-600 text-xl"></i>
                    <span class="text-lg font-semibold text-gray-800">VendEasy POS</span>
                </div>

                <!-- Enlaces del menú -->
                <a href="#" class="flex items-center space-x-3 text-gray-700 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-home"></i>
                    <span>Inicio</span>
                </a>
                <a href="#" class="flex items-center space-x-3 text-gray-700 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Ventas</span>
                </a>
                <a href="#" class="flex items-center space-x-3 text-gray-700 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-box"></i>
                    <span>Productos</span>
                </a>
                <a href="#" class="flex items-center space-x-3 text-gray-700 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-users"></i>
                    <span>Clientes</span>
                </a>
                <a href="#" class="flex items-center space-x-3 text-gray-700 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Overlay para el menú lateral -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden"></div>

    <!-- Contenido principal ajustado -->
    <div class="pt-16">
        <div class="max-w-full mx-auto px-4 py-6">
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Panel izquierdo: Productos -->
                <div class="w-full lg:w-[65%] bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col overflow-hidden">
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
                    <div id="products-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4 p-4 overflow-y-auto flex-1">
                        <?php 
                        if (empty($productos)) {
                            echo '<div class="col-span-full flex flex-col items-center justify-center py-12 text-gray-400">
                                    <i class="fas fa-box-open text-4xl mb-4 animate-bounce"></i>
                                    <p class="text-lg font-medium">No hay productos disponibles</p>
                                    <p class="text-sm text-gray-400 mt-2">Agrega productos desde el módulo de inventario</p>
                                  </div>';
                        } else {
                            foreach ($productos as $producto): 
                        ?>
                            <div class="item-view flex flex-col bg-white rounded-lg shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer border border-gray-200 hover:border-indigo-200 relative hover-scale fade-in"
                                 data-id="<?= $producto['id'] ?>"
                                 data-nombre="<?= htmlspecialchars($producto['nombre']) ?>"
                                 data-precio="<?= $producto['precio'] ?>"
                                 data-cantidad="<?= $producto['cantidad'] ?>"
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
                                        <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-1 rounded-md font-medium shadow-sm">
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
                                         class="product-image w-full h-full object-cover object-center transition-transform duration-300 group-hover:scale-110"
                                         loading="lazy"
                                         onerror="this.src='../../assets/img/no-image.png'">
                                </div>

                                <div class="p-3 flex flex-col gap-2">
                                    <!-- Nombre del producto -->
                                    <p class="item-view__name text-sm text-gray-700 line-clamp-2 min-h-[2.5rem] leading-snug font-medium">
                                        <?= htmlspecialchars($producto['nombre']) ?>
                                    </p>

                                    <!-- Precio -->
                                    <p class="item-view__price text-lg font-bold text-indigo-600">
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

                <!-- Panel derecho: Carrito mejorado -->
                <div class="w-full lg:w-[35%] flex flex-col bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Encabezado del panel -->
                    <div class="p-3 border-b border-gray-200 bg-gray-50">
                        <div class="flex gap-3">
                            <!-- Tipo documento y Numeración en una fila -->
                            <div class="flex-1">
                                <div class="flex gap-2">
                                    <select id="tipo-documento" class="flex-1 border border-gray-300 rounded-lg text-xs py-1.5 px-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                        <option value="factura">Factura de venta</option>
                                        <option value="cotizacion">Cotización</option>
                                    </select>
                                    <select id="numeracion" class="flex-1 border border-gray-300 rounded-lg text-xs py-1.5 px-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                        <option value="principal">Principal</option>
                                        <option value="electronica">Electrónica</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <!-- Cliente en segunda fila -->
                        <div class="mt-2">
                            <div class="flex gap-2">
                                <select id="cliente-select" class="flex-1 border border-gray-300 rounded-lg text-xs py-1.5 px-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                    <option value="">Seleccione un cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= $cliente['id']; ?>" 
                                            <?= $cliente['nombre'] === 'Consumidor Final' ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($cliente['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="../clientes/index.php" 
                                   class="px-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600 transition-all duration-200 flex items-center justify-center hover:border-indigo-300">
                                    <i class="fas fa-plus"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de productos con más espacio -->
                    <div class="flex-1 overflow-auto p-3">
                        <div class="flex justify-between items-center mb-3">
                            <h5 class="text-sm font-medium flex items-center text-gray-700">
                                <i class="fas fa-shopping-cart mr-2 text-indigo-500"></i>Productos
                            </h5>
                            <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2 py-0.5 rounded-lg">
                                <span id="cantidad-items">0</span> items
                            </span>
                        </div>

                        <!-- Tabla de productos -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="text-xs text-gray-700 bg-gray-50 rounded-lg">
                                    <tr>
                                        <th class="text-left py-1.5 px-2 rounded-l-lg">Producto</th>
                                        <th class="text-center w-16">Cant.</th>
                                        <th class="text-right w-24">Precio</th>
                                        <th class="text-right w-24">Total</th>
                                        <th class="w-10 rounded-r-lg"></th>
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

                    <!-- Resumen y totales compactados -->
                    <div class="p-3 bg-gray-50 border-t border-gray-200">
                        <!-- Totales en grid más compacto -->
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="col-span-2 flex justify-between items-center">
                                <span class="text-sm text-gray-500">Subtotal:</span>
                                <span id="subtotal" class="text-base font-semibold text-gray-700">$0,00</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Descuento:</span>
                                <div class="flex items-center">
                                    <input type="number" id="descuento" 
                                           class="w-12 border border-gray-300 rounded-l-lg px-1 py-0.5 text-center text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                           min="0" max="100" value="0">
                                    <span class="inline-flex items-center px-2 py-0.5 border border-l-0 border-gray-300 rounded-r-lg bg-gray-50 text-gray-500 text-sm">
                                        %
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Desc. aplicado:</span>
                                <span id="descuento-monto" class="text-sm font-semibold text-red-600">-$0,00</span>
                            </div>

                            <div class="col-span-2 flex justify-between items-center border-t border-gray-200 pt-2 mt-1">
                                <span class="text-sm font-medium text-gray-600">Total:</span>
                                <span id="venta-total" class="text-lg font-bold text-indigo-600">$0,00</span>
                            </div>
                        </div>

                        <!-- Método de pago compactado -->
                        <div class="mb-3">
                            <select id="metodo-pago" class="w-full border border-gray-300 rounded-lg text-sm py-1.5 px-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200" disabled>
                                <option value="efectivo" selected>Efectivo</option>
                            </select>
                        </div>

                        <!-- Botones de acción -->
                        <div class="space-y-2">
                            <button id="procesar-venta" 
                                    class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white py-3 px-4 rounded-lg transition-all duration-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transform hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 shadow-sm hover:shadow-md flex items-center justify-center space-x-2"
                                    disabled>
                                <i class="fas fa-check-circle text-lg"></i>
                                <span>Confirmar Venta</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>