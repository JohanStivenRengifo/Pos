<?php
session_start();
require_once '../../config/db.php';
require_once 'generar_pdf_productos.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

try {
    // Obtener parámetros de filtrado si existen
    $categoria_id = $_GET['categoria_id'] ?? null;
    $departamento_id = $_GET['departamento_id'] ?? null;
    $stock_minimo = isset($_GET['stock_minimo']) ? filter_var($_GET['stock_minimo'], FILTER_VALIDATE_BOOLEAN) : false;
    
    // Generar el PDF con los filtros
    $pdf_path = generarPDFProductos($categoria_id, $departamento_id, $stock_minimo);
    
    if (file_exists($pdf_path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="inventario_' . date('Y-m-d') . '.pdf"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        
        readfile($pdf_path);
        unlink($pdf_path);
        exit();
    } else {
        throw new Exception('No se pudo generar el archivo PDF');
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error al exportar productos: ' . $e->getMessage();
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Inventario | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
</head>

<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-body p-4">
            <!-- Encabezado -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 mb-2">Exportar Inventario</h1>
                        <p class="text-gray-600">Genera un reporte PDF de tu inventario con filtros personalizados</p>
                    </div>
                    <div>
                        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Volver
                        </a>
                    </div>
                </div>
            </div>

            <!-- Formulario de Filtros -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Opciones de Exportación</h2>
                
                <form action="" method="GET" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Categoría -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Filtrar por Categoría
                            </label>
                            <select name="categoria_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Departamento -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Filtrar por Departamento
                            </label>
                            <select name="departamento_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                                <option value="">Todos los departamentos</option>
                                <?php foreach ($departamentos as $departamento): ?>
                                    <option value="<?= $departamento['id'] ?>"><?= htmlspecialchars($departamento['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Stock Mínimo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Filtros Adicionales
                            </label>
                            <div class="space-y-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="stock_minimo" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-600">Solo productos bajo stock mínimo</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Opciones de Formato -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Opciones de Formato</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex items-center space-x-4">
                                <i class="fas fa-file-pdf text-red-500 text-2xl"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">Formato PDF</h4>
                                    <p class="text-sm text-gray-500">Documento portable ideal para imprimir</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4 opacity-50">
                                <i class="fas fa-file-excel text-green-500 text-2xl"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">Formato Excel (Próximamente)</h4>
                                    <p class="text-sm text-gray-500">Hoja de cálculo para análisis detallado</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                        <button type="reset" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-undo mr-2"></i>
                            Limpiar Filtros
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-download mr-2"></i>
                            Generar PDF
                        </button>
                    </div>
                </form>
            </div>

            <!-- Información Adicional -->
            <div class="bg-blue-50 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Información sobre la exportación</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc pl-5 space-y-1">
                                <li>El PDF generado incluirá todos los productos que coincidan con los filtros seleccionados</li>
                                <li>Se incluirá información detallada como código, nombre, stock y precios</li>
                                <li>El archivo se generará con la fecha actual para mejor organización</li>
                                <li>Asegúrate de tener suficiente espacio en disco para la descarga</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Mostrar loading al generar PDF
        document.querySelector('form').addEventListener('submit', function(e) {
            Swal.fire({
                title: 'Generando PDF',
                text: 'Por favor espera mientras se genera el documento...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });

        // Confirmar reset de filtros
        document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: '¿Limpiar filtros?',
                text: "Se resetearán todas las opciones seleccionadas",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, limpiar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.querySelector('form').reset();
                }
            });
        });
    </script>
</body>
</html> 