<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$cotizacion = null;
$detalles = [];

try {
    if (!isset($_GET['id'])) {
        throw new Exception("ID de cotización no especificado");
    }

    // Obtener la cotización
    $query = "SELECT c.*, 
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre,
                     cl.identificacion as cliente_identificacion,
                     cl.tipo_identificacion as cliente_tipo_identificacion,
                     cl.email as cliente_email,
                     cl.telefono as cliente_telefono,
                     cl.direccion as cliente_direccion
              FROM cotizaciones c
              LEFT JOIN clientes cl ON c.cliente_id = cl.id
              WHERE c.id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $cotizacion = $stmt->fetch();

    if (!$cotizacion) {
        throw new Exception("Cotización no encontrada");
    }

    // Obtener los detalles de la cotización
    $query = "SELECT cd.*, i.codigo_barras, i.nombre as producto_nombre
              FROM cotizacion_detalles cd
              LEFT JOIN inventario i ON cd.producto_id = i.id
              WHERE cd.cotizacion_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $detalles = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver #<?= htmlspecialchars($cotizacion['numero'] ?? '') ?> | Numercia</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
            <?php include '../../includes/sidebar.php'; ?>
            
            <div class="w-full lg:w-3/4 px-4">
                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <!-- Encabezado -->
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-800">
                                Cotización #<?= htmlspecialchars($cotizacion['numero']) ?>
                            </h2>
                            <div class="flex space-x-2">
                                <a href="index.php" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-arrow-left mr-2"></i>Volver
                                </a>

                                <!-- Botones de estado -->
                                <div class="flex space-x-2">
                                    <?php if ($cotizacion['estado'] !== 'Facturado' && 
                                              $cotizacion['estado'] !== 'Cancelada'): ?>
                                        
                                        <?php if ($cotizacion['estado'] !== 'Vencida'): ?>
                                            <?php if ($cotizacion['estado'] !== 'Pendiente'): ?>
                                                <button onclick="cambiarEstado(<?= $cotizacion['id'] ?>, 'Pendiente')" 
                                                        class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition-colors">
                                                    <i class="fas fa-clock mr-2"></i>Pendiente
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($cotizacion['estado'] !== 'Aprobada'): ?>
                                                <button onclick="cambiarEstado(<?= $cotizacion['id'] ?>, 'Aprobada')" 
                                                        class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors">
                                                    <i class="fas fa-check mr-2"></i>Aprobar
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($cotizacion['estado'] === 'Aprobada' || $cotizacion['estado'] === 'Vencida'): ?>
                                            <button onclick="cambiarEstado(<?= $cotizacion['id'] ?>, 'Facturado')" 
                                                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                                <i class="fas fa-file-invoice mr-2"></i>Facturar
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($cotizacion['estado'] !== 'Cancelada'): ?>
                                            <button onclick="confirmarCancelacion(<?= $cotizacion['id'] ?>)" 
                                                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                                                <i class="fas fa-ban mr-2"></i>Cancelar
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <a href="imprimir.php?id=<?= $cotizacion['id'] ?>" 
                                   target="_blank"
                                   class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                                    <i class="fas fa-print mr-2"></i>Imprimir
                                </a>
                            </div>
                        </div>

                        <!-- Información del Cliente -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 bg-gray-50 p-4 rounded-lg">
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Información del Cliente</h3>
                                <p class="text-sm"><?= htmlspecialchars($cotizacion['cliente_nombre']) ?></p>
                                <p class="text-sm"><?= htmlspecialchars($cotizacion['cliente_tipo_identificacion'] . ': ' . $cotizacion['cliente_identificacion']) ?></p>
                                <p class="text-sm"><?= htmlspecialchars($cotizacion['cliente_email']) ?></p>
                                <p class="text-sm"><?= htmlspecialchars($cotizacion['cliente_telefono']) ?></p>
                                <p class="text-sm"><?= htmlspecialchars($cotizacion['cliente_direccion']) ?></p>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Detalles de la Cotización</h3>
                                <p class="text-sm">Fecha: <?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?></p>
                                <p class="text-sm">Estado: 
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $cotizacion['estado'] == 'Aprobada' ? 'bg-green-100 text-green-800' : 
                                           ($cotizacion['estado'] == 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($cotizacion['estado'] == 'Facturado' ? 'bg-blue-100 text-blue-800' : 
                                           ($cotizacion['estado'] == 'Vencida' ? 'bg-orange-100 text-orange-800' : 
                                           'bg-red-100 text-red-800'))) ?>">
                                        <?= $cotizacion['estado'] ?>
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Tabla de Productos -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Precio Unit.</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($detalles as $detalle): ?>
                                    <tr class="text-sm">
                                        <td class="px-3 py-2"><?= htmlspecialchars($detalle['codigo_barras']) ?></td>
                                        <td class="px-3 py-2"><?= htmlspecialchars($detalle['descripcion']) ?></td>
                                        <td class="px-3 py-2"><?= htmlspecialchars($detalle['cantidad']) ?></td>
                                        <td class="px-3 py-2">$<?= number_format($detalle['precio_unitario'], 2, ',', '.') ?></td>
                                        <td class="px-3 py-2">$<?= number_format($detalle['subtotal'], 2, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="4" class="px-3 py-2 text-right font-medium">Total:</td>
                                        <td class="px-3 py-2 font-medium">$<?= number_format($cotizacion['total'], 2, ',', '.') ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    function cambiarEstado(id, estado) {
        let mensaje = '¿Está seguro de que desea cambiar el estado de la cotización a ' + estado + '?';
        
        if (estado === 'Facturado') {
            mensaje = '¿Está seguro de que desea facturar esta cotización? Se creará una venta y se actualizará el inventario.';
        } else if (estado === 'Cancelada') {
            mensaje = '¿Está seguro de que desea cancelar esta cotización? Esta acción no se puede deshacer.';
        }

        if (confirm(mensaje)) {
            fetch('cambiar_estado.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: id,
                    estado: estado
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (estado === 'Facturado') {
                        alert('Cotización facturada exitosamente. Se ha creado la venta correspondiente.');
                    }
                    window.location.reload();
                } else {
                    alert(data.message || 'Error al cambiar el estado');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
            });
        }
    }

    function confirmarCancelacion(id) {
        cambiarEstado(id, 'Cancelada');
    }
    </script>
</body>
</html> 