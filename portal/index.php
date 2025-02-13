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
    <title>Portal de Clientes - VendEasy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="shortcut icon" href="../favicon/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen" x-data="{ showFilters: false }">
        <!-- Navbar simplificado -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center space-x-2">
                        <i class="fas fa-calculator text-primary text-2xl"></i>
                        <span class="text-xl font-bold text-gray-900">VendEasy</span>
                    </a>
                </div>
                    <div class="flex items-center space-x-4">
                        <a href="../" class="text-gray-600 hover:text-gray-900 transition-colors duration-200">
                            <i class="fas fa-home mr-2"></i>Inicio
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Encabezado y descripción -->
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Portal de Clientes</h1>
                <p class="text-gray-600 max-w-2xl mx-auto">
                    Gestiona en un solo lugar todas tus facturas, cotizaciones y créditos de manera fácil y segura.
                </p>
            </div>

            <!-- Formulario de búsqueda simplificado -->
            <div class="max-w-xl mx-auto mb-8">
                <form method="POST" class="flex gap-4">
                    <div class="flex-1">
                        <input class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               type="text" id="cedula" name="cedula" 
                               value="<?php echo htmlspecialchars($cedula); ?>" 
                               placeholder="Buscar por identificación..."
                               required>
                    </div>
                    <button class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200"
                            type="submit">
                        Buscar
                    </button>
                    <button type="button" 
                            @click="showFilters = !showFilters"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700">
                        <i class="fas fa-filter mr-2"></i>Filtrar
                    </button>
                </form>
            </div>

            <?php if (!empty($error)): ?>
                <div class="max-w-2xl mx-auto mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle text-xl mr-3"></i>
                        <p class="text-sm font-medium"><?php echo $error; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($resultados)): ?>
                <!-- Tarjetas de resumen -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <?php
                    $totalPagado = array_reduce($resultados, function($carry, $item) {
                        return $carry + ($item['estado'] !== 'Anulada' ? $item['total'] : 0);
                    }, 0);
                    
                    $totalPendiente = array_reduce($resultados, function($carry, $item) {
                        return $carry + ($item['estado'] === 'Pendiente' ? $item['total'] : 0);
                    }, 0);
                    ?>
                    
                    <div class="stats-card bg-white p-6 rounded-lg shadow-sm">
                        <p class="text-sm text-gray-600 mb-1">Total pagado</p>
                        <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($totalPagado, 0, ',', '.'); ?></p>
                    </div>
                    
                    <div class="stats-card bg-white p-6 rounded-lg shadow-sm">
                        <p class="text-sm text-gray-600 mb-1">Por pagar</p>
                        <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($totalPendiente, 0, ',', '.'); ?></p>
                    </div>
                    
                    <div class="stats-card bg-white p-6 rounded-lg shadow-sm">
                        <p class="text-sm text-gray-600 mb-1">Documentos</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($resultados); ?></p>
                    </div>
                    
                    <div class="stats-card bg-white p-6 rounded-lg shadow-sm">
                        <p class="text-sm text-gray-600 mb-1">Créditos activos</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalCreditos; ?></p>
                    </div>
                </div>

                <!-- Tabla de resultados -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($resultados as $resultado): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php 
                                                echo $resultado['tipo'] === 'Venta' ? 'bg-blue-100 text-blue-800' : 
                                                    ($resultado['tipo'] === 'Cotización' ? 'bg-green-100 text-green-800' : 
                                                    'bg-purple-100 text-purple-800'); 
                                                ?>">
                                                <?php echo htmlspecialchars($resultado['tipo']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                            <?php echo htmlspecialchars($resultado['numero']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d/m/Y', strtotime($resultado['fecha'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            $<?php echo number_format($resultado['total'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php echo $resultado['estado'] === 'Anulada' ? 'bg-red-100 text-red-800' : 
                                                ($resultado['estado'] === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                                <?php echo htmlspecialchars($resultado['estado']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="flex space-x-3">
                                                <a href="detalles.php?tipo=<?php echo urlencode($resultado['tipo']); ?>&id=<?php echo $resultado['id']; ?>" 
                                                   class="text-blue-600 hover:text-blue-900">
                                                    Ver detalle
                                                </a>
                                                <a href="generar_pdf.php?tipo=<?php echo urlencode($resultado['tipo']); ?>&id=<?php echo $resultado['id']; ?>" 
                                                   class="text-gray-600 hover:text-gray-900">
                                                    Descargar PDF
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Paginación -->
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Mostrando 1-<?php echo count($resultados); ?> de <?php echo count($resultados); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <!-- Footer minimalista -->
        <footer class="bg-white border-t border-gray-200 mt-12">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="text-center text-sm text-gray-500">
                    <p>&copy; <?php echo date('Y'); ?> VendEasy. Todos los derechos reservados.</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
