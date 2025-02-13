<?php
require_once __DIR__ . '/../config/db.php';

// Función helper para escape seguro
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Obtener información del usuario y la empresa
$user = $_SESSION['user'] ?? [];
$empresa = null;

if (isset($_SESSION['empresa_id'])) {
    $stmt = $pdo->prepare("SELECT e.*, 
        CASE 
            WHEN e.tipo_persona = 'juridica' THEN e.nombre_empresa
            ELSE CONCAT(COALESCE(e.primer_nombre, ''), ' ', COALESCE(e.segundo_nombre, ''), ' ', COALESCE(e.apellidos, ''))
        END as nombre_completo,
        e.nombre_empresa,
        CONCAT(e.tipo_identificacion, ': ', e.nit) as identificacion_completa,
        e.tipo_persona
    FROM empresas e 
    WHERE e.id = ?");
    $stmt->execute([$_SESSION['empresa_id']]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Verificar si la ruta base está definida
$rutaBase = $rutaBase ?? '';
?>

<header class="sticky top-0 z-50 bg-gradient-to-b from-white via-white/95 to-white/90 backdrop-blur-sm border-b border-gray-200 shadow-sm transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo y nombre -->
            <div class="flex-shrink-0">
                <a href="<?= $rutaBase ?>welcome.php" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 via-indigo-600 to-blue-700 rounded-xl flex items-center justify-center shadow-lg transform group-hover:scale-105 transition-all duration-200 group-hover:shadow-indigo-200">
                        <span class="text-lg font-bold text-white">V</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xl font-bold bg-gradient-to-r from-indigo-600 to-blue-600 bg-clip-text text-transparent">VendEasy</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V13.5zm0 2.25h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V18zm2.498-6.75h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V13.5zm0 2.25h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V18zm2.504-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zm0 2.25h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V18zm2.498-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zM8.25 6h7.5v2.25h-7.5V6zM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 002.25 2.25h10.5a2.25 2.25 0 002.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0012 2.25z" />
                        </svg>
                    </div>
                </a>
            </div>

            <!-- Menú derecho -->
            <div class="flex items-center gap-4">
                <!-- Botón de sincronización -->
                <div class="relative">
                    <button onclick="toggleSyncMenu()" 
                            class="p-2 rounded-lg hover:bg-gray-100/80 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 group">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600 group-hover:text-indigo-600 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4 M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                        </svg>
                    </button>
                    
                    <div id="syncMenu" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 ring-1 ring-black ring-opacity-5 transform transition-all duration-200 origin-top-right">
                        <div class="p-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-800">Sincronización al día</h3>
                                    <p class="text-xs text-gray-500 mt-1">Todo está disponible para que registres tus ventas</p>
                                </div>
                                <button disabled class="p-2 rounded-lg bg-gray-100 text-gray-400 transition-colors duration-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4 M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-2">
                            <div class="space-y-1">
                                <!-- Items de sincronización -->
                                <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50/80 transition-all duration-200 group">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-500 group-hover:scale-110 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4 -4"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                                    </svg>
                                    <span class="text-sm text-gray-700 group-hover:text-gray-900">Ventas</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50/80 transition-all duration-200 group">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-500 group-hover:scale-110 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4 -4"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                                    </svg>
                                    <span class="text-sm text-gray-700 group-hover:text-gray-900">Productos</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50/80 transition-all duration-200 group">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-500 group-hover:scale-110 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4 -4"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                                    </svg>
                                    <span class="text-sm text-gray-700 group-hover:text-gray-900">Clientes</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botón de ayuda -->
                <div class="relative">
                    <button onclick="toggleHelpMenu()" 
                            class="p-2 rounded-lg hover:bg-gray-100/80 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 group">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600 group-hover:text-indigo-600 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 17l0 .01"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 13.5a1.5 1.5 0 0 1 1 -1.5a2.6 2.6 0 1 0 -3 -4"/>
                        </svg>
                    </button>
                    
                    <div id="helpMenu" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-lg border border-gray-200 ring-1 ring-black ring-opacity-5 transform transition-all duration-200 origin-top-right">
                        <div class="p-2">
                            <a href="https://wa.me/+573116035791" target="_blank" 
                               class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50/80 transition-all duration-200 group">
                                <span class="text-sm text-gray-700 group-hover:text-gray-900">Contactar Soporte</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                            <a href="/ayuda.php" 
                               class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50/80 transition-all duration-200 group">
                                <span class="text-sm text-gray-700 group-hover:text-gray-900">Blog de Ayuda</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Perfil del usuario -->
                <div class="relative ml-2">
                    <button onclick="toggleProfileMenu()" 
                            class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 hover:bg-gray-50/80 hover:border-gray-300 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 group">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center shadow-sm group-hover:shadow transform group-hover:scale-105 transition-all duration-200">
                            <span class="text-sm font-medium text-white">
                                <?= strtoupper(substr($user['nombre'] ?? '', 0, 1)); ?>
                            </span>
                        </div>
                        
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-medium text-gray-900"><?= e($user['nombre'] ?? ''); ?></p>
                            <p class="text-xs text-gray-500 truncate max-w-[150px]"><?= e($user['email'] ?? ''); ?></p>
                        </div>
                        
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors duration-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <!-- Menú del perfil -->
                    <div id="profileMenu" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 transform transition-all duration-200 origin-top-right divide-y divide-gray-100">
                        <div class="p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center shadow-sm">
                                    <span class="text-lg font-bold text-white">
                                        <?= strtoupper(substr($user['nombre'] ?? '', 0, 1)); ?>
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900"><?= e($user['nombre'] ?? ''); ?></p>
                                    <p class="text-xs text-gray-500"><?= e($user['email'] ?? ''); ?></p>
                                </div>
                            </div>
                            <div class="mt-3 space-y-2 text-sm">
                                <?php if ($empresa): ?>
                                <div class="p-3 rounded-lg bg-gradient-to-br from-gray-50 to-white border border-gray-100 space-y-2">
                                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Información Empresarial</p>
                                    
                                    <!-- Nombre de la Empresa -->
                                    <div class="space-y-1">
                                        <p class="flex items-center gap-2 text-gray-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                            </svg>
                                            <span class="font-medium"><?= e($empresa['nombre_empresa']); ?></span>
                                        </p>
                                        <p class="flex items-center gap-2 text-gray-600 pl-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />
                                            </svg>
                                            <span><?= e($empresa['identificacion_completa']); ?></span>
                                        </p>
                                    </div>

                                    <!-- Badges de Rol y Tipo -->
                                    <div class="flex items-center gap-2 pt-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                        </svg>
                                        <div class="flex gap-2 flex-wrap">
                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                                                <?= ucfirst(e($user['rol'] ?? 'Usuario')); ?>
                                            </span>
                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                                <?= ucfirst(e($empresa['tipo_persona'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="p-2">
                            <a href="/modules/reportes/index.php" 
                               class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50/80 transition-all duration-200 group">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                </svg>
                                <span class="text-sm text-gray-700 group-hover:text-gray-900">Resumen de Ventas</span>
                            </a>
                            
                            <a href="/modules/config/index.php" 
                               class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50/80 transition-all duration-200 group">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="text-sm text-gray-700 group-hover:text-gray-900">Configuración</span>
                            </a>

                            <a href="/modules/config/seguridad/index.php" 
                               class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50/80 transition-all duration-200 group">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                                <span class="text-sm text-gray-700 group-hover:text-gray-900">Seguridad</span>
                            </a>
                        </div>
                        
                        <div class="p-2">
                            <form action="/modules/auth/logout.php" method="POST">
                                <button type="submit" name="logout" 
                                        class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-red-50/80 transition-all duration-200 text-left group">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 group-hover:text-red-500 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                    </svg>
                                    <span class="text-sm text-gray-700 group-hover:text-red-600 font-medium">Cerrar sesión</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.classList.toggle('hidden');

    // Cerrar otros menús
    document.getElementById('helpMenu').classList.add('hidden');
    document.getElementById('syncMenu').classList.add('hidden');

    // Manejador de clic fuera del menú
    const closeMenu = (e) => {
        if (!e.target.closest('.relative')) {
            menu.classList.add('hidden');
            document.removeEventListener('click', closeMenu);
        }
    };

    // Agregar el event listener después de un pequeño delay para evitar que se cierre inmediatamente
    setTimeout(() => {
        document.addEventListener('click', closeMenu);
    }, 100);
}

function toggleHelpMenu() {
    const menu = document.getElementById('helpMenu');
    menu.classList.toggle('hidden');

    // Cerrar otros menús
    document.getElementById('profileMenu').classList.add('hidden');
    document.getElementById('syncMenu').classList.add('hidden');

    // Manejador de clic fuera del menú
    const closeMenu = (e) => {
        if (!e.target.closest('.relative')) {
            menu.classList.add('hidden');
            document.removeEventListener('click', closeMenu);
        }
    };

    setTimeout(() => {
        document.addEventListener('click', closeMenu);
    }, 100);
}

function toggleSyncMenu() {
    const menu = document.getElementById('syncMenu');
    menu.classList.toggle('hidden');

    // Cerrar otros menús
    document.getElementById('profileMenu').classList.add('hidden');
    document.getElementById('helpMenu').classList.add('hidden');

    // Manejador de clic fuera del menú
    const closeMenu = (e) => {
        if (!e.target.closest('.relative')) {
            menu.classList.add('hidden');
            document.removeEventListener('click', closeMenu);
        }
    };

    setTimeout(() => {
        document.addEventListener('click', closeMenu);
    }, 100);
}

// Agregar manejador para cerrar menús al presionar Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.getElementById('profileMenu').classList.add('hidden');
        document.getElementById('helpMenu').classList.add('hidden');
        document.getElementById('syncMenu').classList.add('hidden');
    }
});
</script>