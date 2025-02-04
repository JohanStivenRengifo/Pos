<?php
require_once('../config/db.php'); // Asegúrate de tener la conexión a la base de datos

// Inicializar variables
$cedula = '';
$resultados = array();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula = filter_input(INPUT_POST, 'cedula', FILTER_SANITIZE_STRING);
    
    if (!empty($cedula)) {
        try {
            $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmtCliente = $db->prepare("
                SELECT id, primer_nombre, segundo_nombre, apellidos, identificacion 
                FROM clientes 
                WHERE identificacion = :cedula
            ");
            $stmtCliente->execute(['cedula' => $cedula]);
            $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);
            
            if ($cliente) {
                // Consulta existente...
                $stmt = $db->prepare("
                    SELECT 
                        v.id,
                        v.numero_factura as numero,
                        v.fecha,
                        v.total,
                        CASE 
                            WHEN v.anulada = 1 THEN 'Anulada'
                            ELSE v.estado_factura 
                        END as estado,
                        'Venta' as tipo
                    FROM ventas v
                    WHERE v.cliente_id = :cliente_id
                    UNION ALL
                    SELECT 
                        c.id,
                        c.numero as numero,
                        c.fecha,
                        c.total,
                        c.estado,
                        'Cotización' as tipo
                    FROM cotizaciones c
                    WHERE c.cliente_id = :cliente_id
                    UNION ALL
                    SELECT 
                        cr.id,
                        CONCAT('CR-', cr.id) as numero,
                        cr.fecha_inicio as fecha,
                        cr.monto_total as total,
                        cr.estado,
                        'Crédito' as tipo
                    FROM creditos cr
                    INNER JOIN ventas v ON cr.venta_id = v.id
                    WHERE v.cliente_id = :cliente_id
                    ORDER BY fecha DESC
                ");
                
                $stmt->execute(['cliente_id' => $cliente['id']]);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $error = "No se encontró ningún cliente con esa identificación.";
            }
        } catch (PDOException $e) {
            $error = "Error de conexión: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Clientes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="shortcut icon" href="./favicon/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Agregamos fuente personalizada -->

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Navbar mejorado -->
        <nav class="bg-gradient-to-r from-blue-600 to-blue-800 shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <i class="fas fa-users text-white text-2xl mr-3"></i>
                        <h1 class="text-2xl font-bold text-white">Portal de Clientes</h1>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Hero Section Mejorada -->
            <div class="text-center mb-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white py-12 rounded-lg shadow-xl">
                <h2 class="text-4xl font-bold mb-4">Portal de Autogestión</h2>
                <p class="text-xl opacity-90">Accede a toda tu información comercial en un solo lugar</p>
                <div class="mt-8 flex justify-center space-x-4">
                    <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                        <i class="fas fa-file-invoice text-3xl mb-2"></i>
                        <p class="text-sm">Facturas</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                        <i class="fas fa-calculator text-3xl mb-2"></i>
                        <p class="text-sm">Cotizaciones</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                        <i class="fas fa-credit-card text-3xl mb-2"></i>
                        <p class="text-sm">Créditos</p>
                    </div>
                </div>
            </div>

            <!-- Formulario de búsqueda mejorado -->
            <div class="max-w-md mx-auto">
                <form method="POST" class="bg-white shadow-xl rounded-lg p-8 mb-8">
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-3" for="cedula">
                            <i class="fas fa-id-card mr-2"></i>Número de Identificación
                        </label>
                        <div class="relative">
                            <input class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 pl-10 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   type="text" id="cedula" name="cedula" 
                                   value="<?php echo htmlspecialchars($cedula); ?>" 
                                   placeholder="Ingresa tu identificación"
                                   required>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition-colors duration-300 flex items-center justify-center"
                            type="submit">
                        <i class="fas fa-search mr-2"></i>
                        Consultar
                    </button>
                </form>
            </div>

            <?php if (!empty($error)): ?>
                <div class="max-w-2xl mx-auto mt-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm"><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($resultados)): ?>
                <!-- Resumen estadístico -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <?php
                    $totalVentas = array_reduce($resultados, function($carry, $item) {
                        return $carry + ($item['tipo'] === 'Venta' ? 1 : 0);
                    }, 0);
                    
                    $totalCotizaciones = array_reduce($resultados, function($carry, $item) {
                        return $carry + ($item['tipo'] === 'Cotización' ? 1 : 0);
                    }, 0);
                    
                    $totalCreditos = array_reduce($resultados, function($carry, $item) {
                        return $carry + ($item['tipo'] === 'Crédito' ? 1 : 0);
                    }, 0);
                    ?>
                    
                    <div class="stats-card bg-white p-6 rounded-lg shadow-lg border-l-4 border-green-500">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 mr-4">
                                <i class="fas fa-file-invoice text-green-500 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Total Facturas</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $totalVentas; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card bg-white p-6 rounded-lg shadow-lg border-l-4 border-blue-500">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 mr-4">
                                <i class="fas fa-calculator text-blue-500 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Cotizaciones</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $totalCotizaciones; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card bg-white p-6 rounded-lg shadow-lg border-l-4 border-purple-500">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 mr-4">
                                <i class="fas fa-credit-card text-purple-500 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Créditos Activos</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $totalCreditos; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 max-w-7xl mx-auto">
                    <?php if (isset($cliente)): ?>
                        <div class="bg-white shadow-lg rounded-lg p-6 mb-6 border-l-4 border-blue-500">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-user-circle text-3xl text-blue-500 mr-3"></i>
                                <h2 class="text-xl font-semibold text-gray-800">Información del Cliente</h2>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-600">
                                <div>
                                    <p><i class="fas fa-user mr-2"></i>Nombre: 
                                        <span class="font-medium"><?php echo htmlspecialchars($cliente['primer_nombre'] . ' ' . $cliente['segundo_nombre'] . ' ' . $cliente['apellidos']); ?></span>
                                    </p>
                                </div>
                                <div>
                                    <p><i class="fas fa-id-card mr-2"></i>Identificación: 
                                        <span class="font-medium"><?php echo htmlspecialchars($cliente['identificacion']); ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white shadow-lg rounded-lg overflow-hidden border border-gray-200">
                        <div class="p-6 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">
                                <i class="fas fa-file-alt mr-2"></i>Documentos Encontrados
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-tag mr-1"></i>Tipo
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-hashtag mr-1"></i>Número
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-calendar mr-1"></i>Fecha
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-dollar-sign mr-1"></i>Total
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-info-circle mr-1"></i>Estado
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <i class="fas fa-cog mr-1"></i>Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($resultados as $resultado): ?>
                                        <tr class="hover:bg-gray-50 transition-colors duration-200">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php 
                                                        echo $resultado['tipo'] === 'Venta' ? 'bg-green-100 text-green-800' : 
                                                            ($resultado['tipo'] === 'Cotización' ? 'bg-blue-100 text-blue-800' : 
                                                            'bg-purple-100 text-purple-800'); 
                                                        ?>">
                                                        <?php echo htmlspecialchars($resultado['tipo']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($resultado['numero']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('d/m/Y', strtotime($resultado['fecha'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                $<?php echo number_format($resultado['total'], 2, ',', '.'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $resultado['estado'] === 'Anulada' ? 'bg-red-100 text-red-800' : 
                                                    ($resultado['estado'] === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                                    <?php echo htmlspecialchars($resultado['estado']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="detalles.php?tipo=<?php echo urlencode($resultado['tipo']); ?>&id=<?php echo $resultado['id']; ?>" 
                                                   class="text-blue-600 hover:text-blue-900 flex items-center">
                                                    <i class="fas fa-eye mr-1"></i>
                                                    Ver detalles
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="max-w-2xl mx-auto mt-4 bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg shadow">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm">No se encontraron resultados para esta identificación.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white mt-12">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="text-center text-sm">
                    <p>&copy; <?php echo date('Y'); ?> Portal de Clientes. Todos los derechos reservados.</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
