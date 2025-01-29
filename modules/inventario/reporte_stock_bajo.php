<?php
session_start();
require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Obtener productos con stock bajo
function obtenerProductosStockBajo($pdo, $user_id) {
    $query = "
            SELECT 
                i.*, 
            c.nombre as categoria_nombre,
            d.nombre as departamento_nombre,
            COALESCE(b.nombre, 'Sin asignar') as bodega_nombre,
            (i.stock * i.precio_venta) as valor_total
            FROM inventario i
            LEFT JOIN categorias c ON i.categoria_id = c.id
            LEFT JOIN departamentos d ON i.departamento_id = d.id
        LEFT JOIN inventario_bodegas ib ON i.id = ib.producto_id
        LEFT JOIN bodegas b ON ib.bodega_id = b.id
            WHERE i.user_id = ? AND i.stock <= i.stock_minimo
        ORDER BY i.stock ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

$productos = obtenerProductosStockBajo($pdo, $user_id);

// Función para formatear moneda
function formatoMoneda($monto) {
    return '$' . number_format($monto, 2, ',', '.');
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Stock Bajo | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#1e293b',
                        accent: '#3b82f6'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>

    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>

        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Encabezado del reporte -->
                <div class="mb-8">
                    <div class="flex justify-between items-center">
                        <h1 class="text-3xl font-bold text-gray-900">Reporte de Stock Bajo</h1>
                        <div class="flex space-x-3">
                            <button onclick="exportarPDF()" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                <i class="fas fa-file-pdf mr-2"></i>
                                Exportar PDF
                            </button>
                            <button onclick="exportarExcel()" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fas fa-file-excel mr-2"></i>
                                Exportar Excel
                            </button>
                        </div>
                    </div>
                    <p class="mt-2 text-gray-600">Productos que requieren reabastecimiento inmediato</p>
                </div>

                <!-- Resumen estadístico -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 mr-4">
                                <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Total Productos</p>
                                <p class="text-2xl font-bold text-gray-900"><?= count($productos) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 mr-4">
                                <i class="fas fa-box text-yellow-500 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Stock Total</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?= array_sum(array_column($productos, 'stock')) ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 mr-4">
                                <i class="fas fa-dollar-sign text-blue-500 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Valor Total</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?= formatoMoneda(array_sum(array_column($productos, 'valor_total'))) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de productos -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="p-6 bg-gradient-to-r from-red-500 to-red-600">
                        <h2 class="text-xl font-semibold text-white">Listado de Productos con Stock Bajo</h2>
                        <p class="text-red-100 mt-1">
                            Fecha del reporte: <?= date('d/m/Y H:i') ?>
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Producto
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Stock Actual
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Stock Mínimo
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Categoría
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Bodega
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Valor
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($productos as $producto): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($producto['nombre']) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($producto['codigo_barras']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $producto['stock'] === 0 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                <?= $producto['stock'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-500">
                                                <?= $producto['stock_minimo'] ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($producto['categoria_nombre']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($producto['bodega_nombre']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= formatoMoneda($producto['valor_total']) ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        function exportarExcel() {
            // Crear una copia de la tabla sin las columnas de acciones
            const tabla = document.querySelector('table').cloneNode(true);
            
            // Convertir la tabla HTML a una hoja de cálculo
            const wb = XLSX.utils.table_to_book(tabla, {sheet: "Productos Stock Bajo"});
            
            // Guardar el archivo
            XLSX.writeFile(wb, `reporte_stock_bajo_${new Date().toISOString().slice(0,10)}.xlsx`);
        }

        function exportarPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4'); // 'l' para landscape
            
            // Obtener el contenedor principal del reporte
            const element = document.querySelector('main');

            Swal.fire({
                title: 'Generando PDF',
                text: 'Por favor espere...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    
                    html2canvas(element, {
                        scale: 2,
                        useCORS: true,
                        logging: false
                    }).then(canvas => {
                        const imgData = canvas.toDataURL('image/png');
                        
                        // Dimensiones de la página A4 apaisada
                        const pageWidth = 297;
                        const pageHeight = 210;
                        
                        // Calcular las dimensiones manteniendo la proporción
                        const imgWidth = pageWidth - 20;
                        const imgHeight = (canvas.height * imgWidth) / canvas.width;
                        
                        // Agregar la imagen al PDF
                        doc.addImage(imgData, 'PNG', 10, 10, imgWidth, imgHeight);
                        
                        // Si el contenido es más largo que una página, agregar más páginas
                        let heightLeft = imgHeight;
                        let position = 10;
                        
                        while (heightLeft >= pageHeight) {
                            position = heightLeft - pageHeight;
                            doc.addPage();
                            doc.addImage(imgData, 'PNG', 10, -position, imgWidth, imgHeight);
                            heightLeft -= pageHeight;
                        }
                        
                        // Guardar el PDF
                        doc.save(`reporte_stock_bajo_${new Date().toISOString().slice(0,10)}.pdf`);
                        
                        Swal.close();
                    }).catch(error => {
                        console.error('Error al generar el PDF:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Hubo un problema al generar el PDF'
                        });
                    });
                }
            });
        }
    </script>
</body>
</html>