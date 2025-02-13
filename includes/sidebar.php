<?php
include 'check_subscription.php';
include '../config/limiter.php';

// Definir la ruta actual
$current_page = basename(dirname($_SERVER['PHP_SELF']));
if ($current_page === 'modules') {
    $current_page = basename(dirname(dirname($_SERVER['PHP_SELF'])));
}

// Obtener el plan actual y acceso a módulos
$stmt = $pdo->prepare("SELECT plan_suscripcion FROM empresas WHERE id = ?");
$stmt->execute([$_SESSION['empresa_id']]);
$plan_actual = $stmt->fetchColumn();

// Función para verificar acceso al módulo
function tieneAccesoSidebar($modulo)
{
    global $PLANES, $plan_actual;
    return $PLANES[$plan_actual]['caracteristicas']['modulos'][$modulo] ?? false;
}

// Definir las categorías y sus módulos
$menu_categories = [
    'operaciones' => [
        'name' => 'Operaciones',
        'icon' => 'fas fa-star',
        'modules' => [
            'dashboard' => [
                'name' => 'Dashboard',
                'url' => '/welcome.php',
                'icon' => 'fas fa-tachometer-alt',
                'always_show' => true
            ],
            'pos' => [
                'name' => 'POS',
                'url' => '/modules/pos/index.php',
                'icon' => 'fas fa-cash-register',
                'always_show' => true  // Aseguramos que POS siempre se muestre
            ]
        ]
    ],
    'ventas' => [
        'name' => 'Ventas y Créditos',
        'icon' => 'fas fa-shopping-cart',
        'modules' => [
            'ventas' => [
                'name' => 'Ventas',
                'url' => '/modules/ventas/index.php',
                'icon' => 'fas fa-receipt'
            ],
            'cotizaciones' => [
                'name' => 'Cotizaciones',
                'url' => '/modules/cotizaciones/index.php',
                'icon' => 'fas fa-file-invoice-dollar'
            ],
            'creditos' => [
                'name' => 'Créditos',
                'url' => '/modules/creditos/index.php',
                'icon' => 'fas fa-credit-card'
            ]
        ]
    ],
    'finanzas' => [
        'name' => 'Finanzas',
        'icon' => 'fas fa-dollar-sign',
        'modules' => [
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
            'turnos' => [
                'name' => 'Turnos',
                'url' => '/modules/turnos/index.php',
                'icon' => 'fas fa-clock'
            ]
        ]
    ],
    'inventario' => [
        'name' => 'Inventario',
        'icon' => 'fas fa-boxes',
        'modules' => [
            'inventario' => [
                'name' => 'Productos',
                'url' => '/modules/inventario/index.php',
                'icon' => 'fas fa-box'
            ],
            'departamentos' => [
                'name' => 'Departamentos',
                'url' => '/modules/departamentos/index.php',
                'icon' => 'fas fa-sitemap'
            ],
            'bodegas' => [
                'name' => 'Bodegas',
                'url' => '/modules/bodegas/index.php',
                'icon' => 'fas fa-warehouse'
            ],
            'prestamos' => [
                'name' => 'Préstamos',
                'url' => '/modules/prestamos/index.php',
                'icon' => 'fas fa-calculator'
            ]
        ]
    ],
    'contactos' => [
        'name' => 'Contactos',
        'icon' => 'fas fa-address-book',
        'modules' => [
            'clientes' => [
                'name' => 'Clientes',
                'url' => '/modules/clientes/index.php',
                'icon' => 'fas fa-users'
            ],
            'proveedores' => [
                'name' => 'Proveedores',
                'url' => '/modules/proveedores/index.php',
                'icon' => 'fas fa-truck'
            ]
        ]
    ],
    'reportes' => [
        'name' => 'Reportes y Análisis',
        'icon' => 'fas fa-chart-line',
        'modules' => [
            'reportes' => [
                'name' => 'Reportes',
                'url' => '/modules/reportes/index.php',
                'icon' => 'fas fa-chart-bar'
            ]
        ]
    ],
    'configuracion' => [
        'name' => 'Configuración',
        'icon' => 'fas fa-cog',
        'description' => 'Configura la información de tu empresa y adapta Alegra a tu negocio.',
        'modules' => [
            'perfil' => [
                'name' => 'Mi Cuenta',
                'url' => '/modules/config/perfil/index.php',
                'icon' => 'fas fa-user-circle'
            ],
            'suscripcion' => [
                'name' => 'Suscripción',
                'url' => '/modules/config/suscripcion/index.php',
                'icon' => 'fas fa-crown'
            ],
            'empresa' => [
                'name' => 'Empresa',
                'url' => '/modules/config/empresas/index.php',
                'icon' => 'fas fa-building'
            ],
            'usuarios' => [
                'name' => 'Usuarios',
                'url' => '/modules/config/usuarios/index.php',
                'icon' => 'fas fa-users-cog'
            ],
            'seguridad' => [
                'name' => 'Seguridad',
                'url' => '/modules/config/seguridad/index.php',
                'icon' => 'fas fa-lock'
            ],
            'config' => [
                'name' => 'Configuración General',
                'url' => '/modules/config/index.php',
                'icon' => 'fas fa-sliders-h'
            ]
        ]
    ]
];

// Función para verificar si un módulo debe mostrarse
function shouldShowModule($module)
{
    // Si el módulo tiene always_show en true, siempre mostrarlo
    if (isset($module['always_show']) && $module['always_show']) {
        return true;
    }

    // Si no tiene module_key definido o tiene acceso al módulo
    return !isset($module['module_key']) || tieneAccesoSidebar($module['module_key']);
}

// Función para verificar si un módulo está activo
function isActive($itemUrl)
{
    $currentUrl = $_SERVER['REQUEST_URI'];
    $itemPath = parse_url($itemUrl, PHP_URL_PATH);
    $currentPath = parse_url($currentUrl, PHP_URL_PATH);
    return strpos($currentPath, $itemPath) === 0;
}

// Función para obtener el número de notificaciones por módulo
function getNotificationCount($module)
{
    // Aquí puedes implementar la lógica para obtener notificaciones reales
    $counts = [
        'ventas' => 0,
        'inventario' => 0,
        'clientes' => 0
    ];
    return $counts[$module] ?? 0;
}

?>

<aside class="min-h-screen w-72 bg-gradient-to-br from-white via-gray-50/50 to-indigo-50/30 border-r border-gray-200 flex flex-col fixed left-0 top-0 h-screen shadow-lg transition-all duration-300" id="sidebar">
    <!-- Contenedor principal con scroll -->
    <div class="flex-1 overflow-y-auto h-full pt-16 scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent">
        <!-- Navegación principal con categorías -->
        <nav class="px-3 py-4">
            <?php foreach ($menu_categories as $cat_key => $category):
                // Filtrar módulos accesibles
                $accessible_modules = array_filter($category['modules'], 'shouldShowModule');
                // Solo mostrar categoría si tiene módulos accesibles
                if (empty($accessible_modules)) continue;
            ?>
                <div class="mb-4 category-container group">
                    <!-- Encabezado de categoría -->
                    <button class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-medium text-gray-700 hover:text-indigo-600 rounded-lg hover:bg-white/80 hover:shadow-sm transition-all duration-200 category-header">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-gradient-to-br from-gray-50 to-white shadow-sm group-hover:shadow group-hover:from-indigo-50 group-hover:to-white transition-all duration-200">
                                <i class="<?= $category['icon'] ?> w-4 h-4 group-hover:scale-110 transition-transform duration-200 text-gray-500 group-hover:text-indigo-500"></i>
                            </div>
                            <span class="font-semibold ml-3"><?= $category['name'] ?></span>
                        </div>
                        <i class="fas fa-chevron-down transform transition-transform duration-200 text-gray-400 group-hover:text-indigo-500"></i>
                    </button>

                    <!-- Módulos de la categoría -->
                    <div class="space-y-0.5 mt-2 category-content">
                        <?php foreach ($accessible_modules as $mod_key => $module): ?>
                            <?php
                            $isLocked = isset($module['module_key']) && !tieneAccesoSidebar($module['module_key']);
                            $moduleUrl = $isLocked ? '/modules/empresa/planes.php' : $module['url'];
                            $isActive = isActive($module['url']);
                            ?>
                            <a href="<?= $moduleUrl ?>"
                                class="flex items-center px-4 py-2.5 text-sm rounded-lg transition-all duration-200 ml-2 group
                                      <?= $isActive
                                            ? 'text-indigo-700 bg-gradient-to-r from-indigo-50/80 to-white border-l-4 border-indigo-600 font-medium shadow-sm'
                                            : 'text-gray-600 hover:bg-white/80 hover:text-gray-900 hover:shadow-sm' ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 bg-gradient-to-br from-gray-50 to-white shadow-sm group-hover:shadow group-hover:from-indigo-50 group-hover:to-white transition-all duration-200 <?= $isActive ? 'from-indigo-100 to-white shadow' : '' ?>">
                                    <i class="<?= $module['icon'] ?> w-4 h-4 transition-all duration-200 
                                              <?= $isActive ? 'text-indigo-600 scale-110' : 'text-gray-500 group-hover:text-indigo-500 group-hover:scale-110' ?>"></i>
                                </div>
                                <span class="flex-1 <?= $isActive ? 'font-medium' : '' ?>"><?= $module['name'] ?></span>
                                <?php if ($isLocked): ?>
                                    <div class="ml-2 p-1 rounded-md bg-gray-50 group-hover:bg-white transition-all duration-200">
                                        <i class="fas fa-lock text-gray-400 group-hover:text-indigo-400 text-sm" title="Actualiza tu plan para acceder a este módulo"></i>
                                    </div>
                                <?php endif; ?>
                                <?php if ($notificationCount = getNotificationCount($mod_key)): ?>
                                    <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-gradient-to-r from-indigo-600 to-blue-600 rounded-full ml-2 transform transition-transform group-hover:scale-110 shadow-sm">
                                        <?= $notificationCount ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>
    </div>
</aside>

<!-- Espaciador fijo -->
<div class="w-72 flex-shrink-0"></div>

<!-- Overlay para móviles -->
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm z-40 lg:hidden hidden" id="sidebar-overlay"></div>

<style>
    .scrollbar-thin::-webkit-scrollbar {
        width: 6px;
    }

    .scrollbar-thin::-webkit-scrollbar-track {
        background: transparent;
    }

    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: #E5E7EB;
        border-radius: 3px;
    }

    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: #D1D5DB;
    }

    /* Animaciones suaves para las categorías */
    .category-content {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Efecto de hover en los íconos */
    .category-header:hover .fa-chevron-down {
        transform: rotate(-180deg);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Restaurar estado de las categorías
        document.querySelectorAll('.category-header').forEach(header => {
            const container = header.closest('.category-container');
            const content = container.querySelector('.category-content');
            const icon = header.querySelector('.fa-chevron-down');

            // Iniciar todas las categorías contraídas
            content.style.display = 'none';

            // Verificar si la categoría contiene el módulo activo
            const hasActiveModule = content.querySelector('a[class*="text-indigo-700"]');
            if (hasActiveModule) {
                content.style.display = 'block';
                content.style.maxHeight = content.scrollHeight + 'px';
                content.style.opacity = '1';
                header.classList.add('active');
                icon.classList.add('-rotate-180');
            }

            header.addEventListener('click', () => {
                const isHidden = content.style.display === 'none';

                if (isHidden) {
                    content.style.display = 'block';
                    setTimeout(() => {
                        content.style.maxHeight = content.scrollHeight + 'px';
                        content.style.opacity = '1';
                        header.classList.add('active');
                        icon.classList.add('-rotate-180');
                    }, 10);
                } else {
                    content.style.opacity = '0';
                    content.style.maxHeight = '0';
                    header.classList.remove('active');
                    icon.classList.remove('-rotate-180');
                    setTimeout(() => {
                        content.style.display = 'none';
                    }, 300);
                }
            });
        });
    });
</script>