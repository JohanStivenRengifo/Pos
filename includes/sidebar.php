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
        'url' => '/modules/pos/index.php',
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
?>

<div class="container">
        <nav>
            <div class="side_navbar">
                <span>Menú Principal</span>
                <a href="/welcome.php">Dashboard</a>
                <a href="/modules/pos/index.php">POS</a>
                <a href="/modules/turnos/index.php">Turnos</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
                <a href="/modules/proveedores/index.php">Proveedores</a>
                <a href="/modules/reportes/index.php">Reportes</a>
                <a href="/modules/config/index.php">Configuración</a>

                <div class="links">
                    <span>Enlaces Rápidos</span>
                    <a href="/ayuda.php">Ayuda</a>
                    <a href="/contacto.php">Soporte</a>
                </div>
            </div>
        </nav>