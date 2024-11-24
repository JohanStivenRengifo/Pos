<?php
// Definir la ruta actual
$current_page = basename(dirname($_SERVER['PHP_SELF']));
if ($current_page === 'modules') {
    $current_page = basename(dirname(dirname($_SERVER['PHP_SELF'])));
}

// Definir el menú y sus rutas
$menu_items = [
    'dashboard' => [
        'name' => 'Dashboard',
        'url' => '/welcome.php',
        'icon' => 'fas fa-tachometer-alt'
    ],
    'pos' => [
        'name' => 'POS',
        'url' => 'pos/index.php',
        'icon' => 'fas fa-cash-register'
    ],
    'ingresos' => [
        'name' => 'Ingresos',
        'url' => '/modules/ingresos/index.php',
        'icon' => 'fas fa-money-bill-wave'
    ],
    'egresos' => [
        'name' => 'Egresos',
        'url' => '/modules/egresos/index.php',
        'icon' => 'fas fa-money-bill-wave-alt'
    ],
    'ventas' => [
        'name' => 'Ventas',
        'url' => '/modules/ventas/index.php',
        'icon' => 'fas fa-shopping-cart'
    ],
    'inventario' => [
        'name' => 'Inventario',
        'url' => '/modules/inventario/index.php',
        'icon' => 'fas fa-boxes'
    ],
    'clientes' => [
        'name' => 'Clientes',
        'url' => '/modules/clientes/index.php',
        'icon' => 'fas fa-users'
    ],
    'proveedores' => [
        'name' => 'Proveedores',
        'url' => '/modules/proveedores/index.php',
        'icon' => 'fas fa-truck'
    ],
    'reportes' => [
        'name' => 'Reportes',
        'url' => '/modules/reportes/index.php',
        'icon' => 'fas fa-chart-bar'
    ],
    'config' => [
        'name' => 'Configuración',
        'url' => '/modules/config/index.php',
        'icon' => 'fas fa-cog'
    ]
];

// Mejorar la función isActive para ser más precisa
function isActive($itemUrl) {
    $currentUrl = $_SERVER['REQUEST_URI'];
    $itemPath = parse_url($itemUrl, PHP_URL_PATH);
    $currentPath = parse_url($currentUrl, PHP_URL_PATH);
    return $itemPath === $currentPath;
}

// Función para obtener el número de notificaciones por módulo
function getNotificationCount($module) {
    // Aquí puedes implementar la lógica para obtener notificaciones reales
    $counts = [
        'ventas' => 3,
        'inventario' => 5,
        'clientes' => 2
    ];
    return $counts[$module] ?? 0;
}
?>

<aside class="min-h-screen w-72 bg-white border-r border-gray-200 flex flex-col fixed left-0 top-0 h-screen transition-all duration-300" id="sidebar">
    <!-- Contenedor principal con scroll -->
    <div class="flex-1 overflow-y-auto h-full pt-16">
        <!-- Perfil del usuario -->
        <div class="px-6 py-4 border-b border-gray-100">
            <div class="flex items-center space-x-3">
                <?php if (!empty($empresa_info['logo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $empresa_info['logo'])): ?>
                    <div class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center overflow-hidden">
                        <img src="/<?= htmlspecialchars($empresa_info['logo']) ?>" 
                             alt="Logo empresa" 
                             class="w-full h-full object-contain"
                             onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%23CBD5E0\'%3E%3Cpath d=\'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z\'/%3E%3C/svg%3E';">
                        </div>
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center text-white font-semibold text-lg shadow-lg">
                        <?= strtoupper(substr($empresa_info['nombre_empresa'] ?? 'E', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-medium text-gray-900 truncate">
                        <?= htmlspecialchars($empresa_info['user_name'] ?? $_SESSION['nombre'] ?? 'Usuario') ?>
                    </h2>
                    <p class="text-xs text-gray-500 truncate flex items-center">
                        <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>
                        Disponible
                    </p>
                </div>
            </div>
        </div>

        <!-- Navegación principal con grupos -->
        <nav class="px-4 py-4">
            <!-- Grupo: Operaciones Principales -->
            <div class="mb-6">
                <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                    Operaciones Principales
                </h3>
                <div class="space-y-1">
                    <a href="/welcome.php" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 
                              <?= isActive('/welcome.php') ? 'text-indigo-700 bg-indigo-50 hover:bg-indigo-100' : 'text-gray-700 hover:bg-gray-50' ?>">
                        <i class="fas fa-tachometer-alt w-5 h-5 mr-3"></i>
                        <span class="flex-1">Dashboard</span>
                    </a>
                    
                    <a href="https://pos.johanrengifo.cloud/" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200
                              <?= isActive('https://pos.johanrengifo.cloud/') ? 'text-indigo-700 bg-indigo-50 hover:bg-indigo-100' : 'text-gray-700 hover:bg-gray-50' ?>">
                        <i class="fas fa-cash-register w-5 h-5 mr-3"></i>
                        <span class="flex-1">POS</span>
                    </a> 
                </div>
            </div>

            <!-- Grupo: Finanzas -->
            <div class="mb-6">
                <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                    Finanzas
                </h3>
                <div class="space-y-1">
                    <a href="/modules/ingresos/index.php" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200
                              <?= isActive('/modules/ingresos/index.php') ? 'text-green-700 bg-green-50 hover:bg-green-100' : 'text-gray-700 hover:bg-gray-50' ?>">
                        <i class="fas fa-money-bill-wave w-5 h-5 mr-3"></i>
                        <span class="flex-1">Ingresos</span>
                    </a>
                    
                    <a href="/modules/egresos/index.php" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200
                              <?= isActive('/modules/egresos/index.php') ? 'text-red-700 bg-red-50 hover:bg-red-100' : 'text-gray-700 hover:bg-gray-50' ?>">
                        <i class="fas fa-money-bill-wave-alt w-5 h-5 mr-3"></i>
                        <span class="flex-1">Egresos</span>
                    </a>
                    
                    <a href="/modules/turnos/index.php" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200
                              <?= isActive('/modules/turnos/index.php') ? 'text-blue-700 bg-blue-50 hover:bg-blue-100' : 'text-gray-700 hover:bg-gray-50' ?>">
                        <i class="fas fa-clock w-5 h-5 mr-3"></i>
                        <span class="flex-1">Turnos</span>
                    </a>
                </div>
            </div>

            <!-- Grupo: Gestión -->
            <div class="mb-6">
                <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                    Gestión
                </h3>
                <div class="space-y-1">
                    <?php foreach (['ventas', 'inventario', 'clientes', 'proveedores'] as $module): ?>
                        <a href="/modules/<?= $module ?>/index.php" 
                           class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200
                                  <?= isActive("/modules/$module/index.php") ? 'text-indigo-700 bg-indigo-50 hover:bg-indigo-100' : 'text-gray-700 hover:bg-gray-50' ?>">
                            <i class="<?= $menu_items[$module]['icon'] ?> w-5 h-5 mr-3"></i>
                            <span class="flex-1"><?= $menu_items[$module]['name'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Grupo: Análisis -->
            <div class="mb-6">
                <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                    Análisis
                </h3>
                <div class="space-y-1">
                    <a href="/modules/reportes/index.php" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                        <i class="fas fa-chart-bar w-5 h-5 mr-3"></i>
                        <span class="flex-1">Reportes</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Enlaces rápidos y configuración -->
        <div class="mt-auto px-4 py-4 border-t border-gray-200">
            <div class="space-y-1">
                <a href="/modules/config/index.php" class="group flex items-center px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition-all duration-200">
                    <i class="fas fa-cog w-5 h-5 mr-3"></i>
                    <span>Configuración</span>
                </a>
                
                <a href="/ayuda.php" class="group flex items-center px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition-all duration-200">
                    <i class="fas fa-question-circle w-5 h-5 mr-3"></i>
                    <span>Centro de Ayuda</span>
                </a>

                <button id="toggleSidebar" class="w-full group flex items-center px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition-all duration-200">
                    <i class="fas fa-chevron-left w-5 h-5 mr-3"></i>
                    <span>Contraer menú</span>
                </button>
            </div>
        </div>
    </div>
</aside>

<!-- Espaciador -->
<div class="w-72 flex-shrink-0 transition-all duration-300" id="sidebar-spacer"></div>

<!-- Overlay para móviles -->
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 lg:hidden hidden" id="sidebar-overlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarSpacer = document.getElementById('sidebar-spacer');
    const toggleBtn = document.getElementById('toggleSidebar');
    let isCollapsed = false;

    function toggleSidebar() {
        isCollapsed = !isCollapsed;
        
        if (isCollapsed) {
            sidebar.classList.remove('w-72');
            sidebar.classList.add('w-20');
            sidebarSpacer.classList.remove('w-72');
            sidebarSpacer.classList.add('w-20');
            
            // Ocultar texto
            document.querySelectorAll('#sidebar span:not(.icon), #sidebar h3').forEach(el => {
                el.classList.add('hidden');
            });
            
            toggleBtn.innerHTML = '<i class="fas fa-chevron-right w-5 h-5"></i>';
        } else {
            sidebar.classList.remove('w-20');
            sidebar.classList.add('w-72');
            sidebarSpacer.classList.remove('w-20');
            sidebarSpacer.classList.add('w-72');
            
            // Mostrar texto
            document.querySelectorAll('#sidebar span:not(.icon), #sidebar h3').forEach(el => {
                el.classList.remove('hidden');
            });
            
            toggleBtn.innerHTML = '<i class="fas fa-chevron-left w-5 h-5 mr-3"></i><span>Contraer menú</span>';
        }

        // Guardar estado en localStorage
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }

    // Event listener para el botón de alternar
    toggleBtn.addEventListener('click', toggleSidebar);

    // Restaurar estado del sidebar
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        toggleSidebar();
    }
});
</script>

<style>
/* Estilos para la scrollbar */
#sidebar .flex-1::-webkit-scrollbar {
    width: 4px;
}

#sidebar .flex-1::-webkit-scrollbar-track {
    background: transparent;
}

#sidebar .flex-1::-webkit-scrollbar-thumb {
    background-color: #e5e7eb;
    border-radius: 2px;
}

#sidebar .flex-1:hover::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
}

/* Asegurar que el contenido del sidebar sea scrolleable */
#sidebar .flex-1 {
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: #e5e7eb transparent;
}

/* Ajustes para el modo contraído */
#sidebar.w-20 .group span,
#sidebar.w-20 h3 {
    display: none;
}

#sidebar.w-20 .group {
    justify-content: center;
}

#sidebar.w-20 .group i {
    margin-right: 0;
}
</style>