<?php
if (!isset($user_id)) {
    $user_id = $_SESSION['user_id'] ?? null;
    $email = $_SESSION['email'] ?? null;
    
    if ($user_id && $pdo) {
        // Obtener la información de la empresa y el usuario
        $stmt = $pdo->prepare("
            SELECT e.*, u.nombre as user_name, u.email, u.estado 
            FROM users u 
            LEFT JOIN empresas e ON e.id = u.empresa_id 
            WHERE u.id = ? 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $empresa_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<header class="header">
    <div class="logo">
        <a href="/welcome.php">VendEasy</a>
    </div>
    <div class="header-icons">
        <!-- Botón de sincronización -->
        <div class="sync-menu-container">
            <div class="sync-button" onclick="toggleSyncMenu()">
                <div class="refresh-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"></path>
                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"></path>
                    </svg>
                </div>
            </div>
            <div class="sync-menu" id="syncMenu">
                <div class="sync-header">
                    <div>
                        <p class="sync-title">Sincronización al día</p>
                        <p class="sync-subtitle">Todo está disponible para que registres tus ventas</p>
                    </div>
                    <button type="button" disabled class="sync-button-filled">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"></path>
                            <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"></path>
                        </svg>
                    </button>
                </div>
                <div class="sync-items">
                    <div class="sync-item">
                        <div class="sync-item-content">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path>
                                <path d="M9 12l2 2l4 -4"></path>
                            </svg>
                            <p>Ventas</p>
                        </div>
                    </div>
                    <div class="sync-item">
                        <div class="sync-item-content">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path>
                                <path d="M9 12l2 2l4 -4"></path>
                            </svg>
                            <p>Productos</p>
                        </div>
                    </div>
                    <div class="sync-item">
                        <div class="sync-item-content">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path>
                                <path d="M9 12l2 2l4 -4"></path>
                            </svg>
                            <p>Clientes</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Icono de ayuda -->
        <div class="help-icon" onclick="toggleHelpMenu()">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path>
                <path d="M12 17l0 .01"></path>
                <path d="M12 13.5a1.5 1.5 0 0 1 1 -1.5a2.6 2.6 0 1 0 -3 -4"></path>
            </svg>
            <div class="help-menu" id="helpMenu">
                <div class="menu-option p-3">
                    <a href="https://wa.me/+573116035791" target="_blank" class="p-2 d-flex align-items-center justify-content-between">
                        <p class="body-3-regular color-secondary">Contactar Soporte</p>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 7l-10 10"></path>
                            <path d="M8 7l9 0l0 9"></path>
                        </svg>
                    </a>
                </div>
                <div class="menu-option p-3">
                    <a href="/ayuda.php" class="p-2 d-flex align-items-center justify-content-between">
                        <p class="body-3-regular color-secondary">Blog de Ayuda</p>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 7l-10 10"></path>
                            <path d="M8 7l9 0l0 9"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Perfil del usuario -->
        <div class="profile-container" onclick="toggleProfileMenu()">
            <div class="profile-button">
                <div class="profile-content">
                    <?php if (!empty($empresa_info['logo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $empresa_info['logo'])): ?>
                        <div class="profile-logo-container">
                            <img src="/<?= htmlspecialchars($empresa_info['logo']); ?>" 
                                 alt="Logo empresa"
                                 class="profile-logo"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="profile-initial" style="display: none;">
                                <span><?= strtoupper(substr($empresa_info['user_name'] ?? $email, 0, 1)); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="profile-initial">
                            <span><?= strtoupper(substr($empresa_info['user_name'] ?? $email, 0, 1)); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="profile-info">
                        <span class="profile-name"><?= htmlspecialchars($empresa_info['user_name'] ?? ''); ?></span>
                        <span class="profile-email"><?= htmlspecialchars($email) ?></span>
                    </div>
                </div>
            </div>
            <!-- Menú desplegable del perfil -->
            <div class="profile-menu" id="profileMenu">
                <div class="menu-header p-4 border-bottom">
                    <p class="user-name"><?= htmlspecialchars($empresa_info['user_name'] ?? ''); ?></p>
                    <p class="user-email"><?= htmlspecialchars($email) ?></p>
                </div>
                <div class="menu-content">
                    <ul>
                        <li>
                            <a href="/modules/reportes/index.php" class="menu-item">
                                <div class="menu-item-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"></path>
                                        <path d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"></path>
                                        <path d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"></path>
                                        <path d="M4 20h14"></path>
                                    </svg>
                                    <p>Resumen de Ventas</p>
                                </div>
                                <svg class="external-link" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6"></path>
                                    <path d="M11 13l9 -9"></path>
                                    <path d="M15 4h5v5"></path>
                                </svg>
                            </a>
                        </li>
                        <!-- Más opciones del menú -->
                        <li>
                            <a href="/modules/config/index.php" class="menu-item">
                                <div class="menu-item-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M19.875 6.27c.7 .398 1.13 1.143 1.125 1.948v7.284c0 .809-.443 1.555-1.158 1.948l-6.75 4.27a2.269 2.269 0 0 1 -2.184 0l-6.75-4.27a2.225 2.225 0 0 1 -1.158 -1.948v-7.285c0-.809.443-1.554 1.158-1.947l6.75-3.98a2.33 2.33 0 0 1 2.25 0l6.75 3.98h-.033z"></path>
                                        <path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"></path>
                                    </svg>
                                    <p>Configuraciones</p>
                                </div>
                                <svg class="external-link" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6"></path>
                                    <path d="M11 13l9 -9"></path>
                                    <path d="M15 4h5v5"></path>
                                </svg>
                            </a>
                        </li>
                        <li>
                            <form action="" method="POST" class="menu-item">
                                <button type="submit" name="logout" class="logout-btn">
                                    <div class="menu-item-info">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"></path>
                                            <path d="M9 12h12l-3 -3"></path>
                                            <path d="M18 15l3 -3"></path>
                                        </svg>
                                        <p>Cerrar sesión</p>
                                    </div>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
.header {
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.header-icons {
    display: flex;
    align-items: center;
    gap: 20px;
}

/* Estilos del icono de ayuda */
.help-icon {
    position: relative;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.help-icon:hover {
    background-color: rgba(0,0,0,0.05);
}

/* Estilos del perfil */
.profile-container {
    position: relative;
    height: 100%;
    padding: 8px 0;
}

.profile-button {
    background-color: white;
    border-radius: 50px;
    padding: 6px 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
    cursor: pointer;
    border: 1px solid #eee;
}

.profile-button:hover {
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}

.profile-content {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px;
}

.profile-logo-container {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.profile-logo {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-initial {
    width: 32px;
    height: 32px;
    background-color: #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
    color: #6c757d;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.profile-info {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.profile-name {
    font-weight: 500;
    color: #2d3748;
    font-size: 0.9rem;
}

.profile-email {
    font-size: 0.8rem;
    color: #718096;
}

/* Menú desplegable */
.help-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 250px;
    margin-top: 8px;
    z-index: 1000;
}

.help-menu.show {
    display: block;
    animation: fadeIn 0.2s ease-out;
}

.menu-option {
    padding: 8px;
}

.menu-option a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    color: #2c3e50;
    text-decoration: none;
    transition: background-color 0.2s;
    border-radius: 6px;
}

.menu-option a:hover {
    background-color: #f8f9fa;
}

.menu-option p {
    margin: 0;
    font-size: 0.9rem;
}

/* Animaciones */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.help-menu.show {
    display: block;
    animation: fadeIn 0.2s ease-out;
}

/* Responsive */
@media (max-width: 768px) {
    .profile-info {
        display: none;
    }
    
    .profile-button {
        padding: 4px;
    }
    
    .help-icon svg {
        width: 20px;
        height: 20px;
    }
}

.status-chip {
    display: flex;
    align-items: center;
    gap: 6px;
    background-color: #ecfdf5;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    color: #059669;
    margin-left: 8px;
}

.status-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    color: #059669;
}

.status-text {
    font-weight: 500;
}

.sync-menu-container {
    position: relative;
    height: 100%;
    padding: 8px 0;
}

.sync-button {
    padding: 8px;
    border-radius: 50%;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.sync-button:hover {
    background-color: rgba(0,0,0,0.05);
}

.sync-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 300px;
    margin-top: 8px;
    z-index: 1000;
}

.sync-menu.show {
    display: block;
    animation: fadeIn 0.2s ease-out;
}

.sync-header {
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #eee;
}

.sync-title {
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
    font-size: 0.95rem;
}

.sync-subtitle {
    color: #6c757d;
    margin: 4px 0 0 0;
    font-size: 0.8rem;
}

.sync-button-filled {
    background: #f8f9fa;
    border: none;
    padding: 8px;
    border-radius: 8px;
    cursor: not-allowed;
    opacity: 0.7;
}

.sync-items {
    padding: 8px 16px;
}

.sync-item {
    padding: 12px 0;
    border-bottom: 1px solid #eee;
}

.sync-item:last-child {
    border-bottom: none;
}

.sync-item-content {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #2c3e50;
}

.sync-item-content svg {
    color: #10b981;
}

.sync-item-content p {
    margin: 0;
    font-size: 0.9rem;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Estilos para el menú de perfil */
.profile-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 280px;
    margin-top: 8px;
    z-index: 1000;
}

.profile-menu.show {
    display: block;
    animation: fadeIn 0.2s ease-out;
}

.menu-header {
    padding: 16px;
    border-bottom: 1px solid #eee;
}

.user-name {
    font-size: 0.9rem;
    color: #2c3e50;
    margin: 0;
    font-weight: 500;
}

.user-email {
    font-size: 0.8rem;
    color: #6c757d;
    margin: 4px 0 0 0;
}

.menu-content ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.menu-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    color: #2c3e50;
    text-decoration: none;
    transition: background-color 0.2s;
}

.menu-item:hover {
    background-color: #f8f9fa;
}

.menu-item-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.menu-item-info svg {
    color: #6c757d;
}

.menu-item-info p {
    margin: 0;
    font-size: 0.9rem;
}

.external-link {
    color: #6c757d;
    width: 20px;
    height: 20px;
}

.logout-btn {
    width: 100%;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    color: inherit;
    text-align: left;
}

.menu-item-info svg {
    width: 20px;
    height: 20px;
    color: #6c757d;
    transition: color 0.2s ease;
}

.menu-item:hover .menu-item-info svg {
    color: #007bff;
}

.external-link {
    opacity: 0;
    transition: opacity 0.2s ease;
}

.menu-item:hover .external-link {
    opacity: 1;
}
</style>

<script>
function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.classList.toggle('show');

    // Cerrar otros menús
    document.getElementById('helpMenu').classList.remove('show');
    document.getElementById('syncMenu').classList.remove('show');

    document.addEventListener('click', function closeMenu(e) {
        if (!e.target.closest('.profile-container')) {
            menu.classList.remove('show');
            document.removeEventListener('click', closeMenu);
        }
    });
}

function toggleHelpMenu() {
    const menu = document.getElementById('helpMenu');
    menu.classList.toggle('show');

    // Cerrar otros menús
    document.getElementById('profileMenu').classList.remove('show');
    document.getElementById('syncMenu').classList.remove('show');

    document.addEventListener('click', function closeMenu(e) {
        if (!e.target.closest('.help-icon')) {
            menu.classList.remove('show');
            document.removeEventListener('click', closeMenu);
        }
    });
}

function toggleSyncMenu() {
    const menu = document.getElementById('syncMenu');
    menu.classList.toggle('show');

    document.addEventListener('click', function closeSyncMenu(e) {
        if (!e.target.closest('.sync-menu-container')) {
            menu.classList.remove('show');
            document.removeEventListener('click', closeSyncMenu);
        }
    });
}
</script>