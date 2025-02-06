<?php
// Definir la ruta actual
$current_page = basename(dirname($_SERVER['PHP_SELF']));
if ($current_page === 'modules') {
    $current_page = basename(dirname(dirname($_SERVER['PHP_SELF'])));
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
                'icon' => 'fas fa-tachometer-alt'
            ],
            'pos' => [
                'name' => 'POS',
                'url' => '/modules/pos/index.php',
                'icon' => 'fas fa-cash-register'
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
        'modules' => [
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
            'config' => [
                'name' => 'Configuración',
                'url' => '/modules/config/index.php',
                'icon' => 'fas fa-sliders-h'
            ]
        ]
    ]
];

// Función para verificar si un módulo está activo
function isActive($itemUrl) {
    $currentUrl = $_SERVER['REQUEST_URI'];
    $itemPath = parse_url($itemUrl, PHP_URL_PATH);
    $currentPath = parse_url($currentUrl, PHP_URL_PATH);
    return strpos($currentPath, $itemPath) === 0;
}

// Función para obtener el número de notificaciones por módulo
function getNotificationCount($module) {
    // Aquí puedes implementar la lógica para obtener notificaciones reales
    $counts = [
        'ventas' => 0,
        'inventario' => 0,
        'clientes' => 0
    ];
    return $counts[$module] ?? 0;
}
?>

<aside class="min-h-screen w-72 bg-white border-r border-gray-200 flex flex-col fixed left-0 top-0 h-screen shadow-lg" id="sidebar">
    <!-- Contenedor principal con scroll -->
    <div class="flex-1 overflow-y-auto h-full pt-16">
        <!-- Navegación principal con categorías -->
        <nav class="px-3 py-4">
            <?php foreach ($menu_categories as $cat_key => $category): ?>
                <div class="mb-4 category-container">
                    <!-- Encabezado de categoría -->
                    <button class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-semibold text-gray-600 hover:text-indigo-600 rounded-lg hover:bg-indigo-50 transition-all duration-200 category-header group">
                        <div class="flex items-center">
                            <i class="<?= $category['icon'] ?> w-5 h-5 mr-3 group-hover:scale-110 transition-transform duration-200"></i>
                            <span><?= $category['name'] ?></span>
                        </div>
                        <i class="fas fa-chevron-down transform transition-transform duration-200"></i>
                    </button>

                    <!-- Módulos de la categoría -->
                    <div class="space-y-1 mt-1 category-content">
                        <?php foreach ($category['modules'] as $mod_key => $module): ?>
                            <a href="<?= $module['url'] ?>" 
                               class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 ml-2
                                      <?= isActive($module['url']) 
                                          ? 'text-indigo-700 bg-indigo-50 border-l-4 border-indigo-600' 
                                          : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                                <i class="<?= $module['icon'] ?> w-5 h-5 mr-3 transition-transform duration-200 hover:scale-110"></i>
                                <span class="flex-1"><?= $module['name'] ?></span>
                                <?php if ($notificationCount = getNotificationCount($mod_key)): ?>
                                    <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-indigo-100 bg-indigo-600 rounded-full ml-2">
                                        <?= $notificationCount ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>

        <!-- Enlaces rápidos -->
        <div class="mt-auto px-3 py-4 border-t border-gray-200">
            <a href="/contacto.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-all duration-200 group">
                <i class="fas fa-question-circle w-5 h-5 mr-3 group-hover:scale-110 transition-transform duration-200"></i>
                <span>Centro de Ayuda</span>
            </a>
        </div>
    </div>
</aside>

<!-- Espaciador fijo -->
<div class="w-72 flex-shrink-0"></div>

<!-- Overlay para móviles -->
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 lg:hidden hidden" id="sidebar-overlay"></div>

<style>
/* Estilos base del sidebar */
#sidebar {
    z-index: 50;
}

/* Estilos para la scrollbar */
#sidebar .flex-1::-webkit-scrollbar {
    width: 4px;
}

#sidebar .flex-1::-webkit-scrollbar-track {
    background: transparent;
}

#sidebar .flex-1::-webkit-scrollbar-thumb {
    background-color: #e5e7eb;
    border-radius: 4px;
}

#sidebar .flex-1:hover::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
}

/* Animaciones para las categorías */
.category-content {
    transition: all 0.3s ease;
    max-height: 0;
    opacity: 0;
    overflow: hidden;
}

.category-content.show {
    max-height: 500px;
    opacity: 1;
}

/* Animación para los íconos de las categorías */
.category-header.active i.fa-chevron-down {
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