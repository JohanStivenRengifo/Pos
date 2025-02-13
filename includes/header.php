<?php
require_once __DIR__ . '/../config/db.php';

// Función helper para escape seguro
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Obtener información del usuario
$user = $_SESSION['user'] ?? [];

// Verificar si la ruta base está definida
$rutaBase = $rutaBase ?? '';
?>

<header class="sticky top-0 z-50 bg-white border-b border-gray-200 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo y nombre -->
            <div class="flex-shrink-0">
                <a href="<?= $rutaBase ?>welcome.php" class="flex items-center gap-2">
                    <span class="text-xl font-bold text-blue-600">VendEasy</span>
                </a>
            </div>

            <!-- Menú derecho -->
            <div class="flex items-center gap-4">
                <!-- Botón de sincronización -->
                <div class="relative">
                    <button onclick="toggleSyncMenu()" 
                            class="p-2 rounded-lg hover:bg-gray-100 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4 M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                        </svg>
                    </button>
                    
                    <div id="syncMenu" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 ring-1 ring-black ring-opacity-5">
                        <div class="p-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-800">Sincronización al día</h3>
                                    <p class="text-xs text-gray-500 mt-1">Todo está disponible para que registres tus ventas</p>
                                </div>
                                <button disabled class="p-2 rounded-lg bg-gray-100 text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4 M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-2">
                            <div class="space-y-1">
                                <!-- Items de sincronización -->
                                <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4 -4"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                                    </svg>
                                    <span class="text-sm text-gray-700">Ventas</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4 -4"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                                    </svg>
                                    <span class="text-sm text-gray-700">Productos</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4 -4"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                                    </svg>
                                    <span class="text-sm text-gray-700">Clientes</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botón de ayuda -->
                <div class="relative">
                    <button onclick="toggleHelpMenu()" 
                            class="p-2 rounded-lg hover:bg-gray-100 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 17l0 .01"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 13.5a1.5 1.5 0 0 1 1 -1.5a2.6 2.6 0 1 0 -3 -4"/>
                        </svg>
                    </button>
                    
                    <div id="helpMenu" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-lg border border-gray-200 ring-1 ring-black ring-opacity-5">
                        <div class="p-2">
                            <a href="https://wa.me/+573116035791" target="_blank" 
                               class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50">
                                <span class="text-sm text-gray-700">Contactar Soporte</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                            <a href="/ayuda.php" 
                               class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50">
                                <span class="text-sm text-gray-700">Blog de Ayuda</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Perfil del usuario -->
                <div class="relative ml-2">
                    <button onclick="toggleProfileMenu()" 
                            class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 hover:bg-gray-50 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                            <span class="text-sm font-medium text-blue-600">
                                <?= strtoupper(substr($user['nombre'] ?? '', 0, 1)); ?>
                            </span>
                        </div>
                        
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-medium text-gray-900"><?= e($user['nombre'] ?? ''); ?></p>
                            <p class="text-xs text-gray-500 truncate max-w-[150px]"><?= e($user['email'] ?? ''); ?></p>
                        </div>
                        
                        <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <!-- Menú del perfil -->
                    <div id="profileMenu" class="hidden absolute right-0 mt-2 w-72 bg-white rounded-lg shadow-lg border border-gray-200">
                        <div class="p-4 border-b border-gray-200">
                            <p class="text-sm font-medium text-gray-800"><?= e($user['nombre'] ?? ''); ?></p>
                            <p class="text-xs text-gray-500 mt-1"><?= e($user['email'] ?? ''); ?></p>
                        </div>
                        
                        <div class="p-2">
                            <a href="/modules/reportes/index.php" 
                               class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 group">
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 group-hover:text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                    </svg>
                                    <span class="text-sm text-gray-700">Resumen de Ventas</span>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 opacity-0 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                            
                            <form action="/modules/auth/logout.php" method="POST" class="mt-2 border-t border-gray-200">
                                <button type="submit" name="logout" 
                                        class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-left">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                    </svg>
                                    <span class="text-sm text-gray-700">Cerrar sesión</span>
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