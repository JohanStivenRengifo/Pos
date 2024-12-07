<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$clientes = [];
$cotizaciones = [];

try {
    // Verificar si la tabla clientes existe y tiene la columna usuario_id
    $checkTable = $pdo->query("SHOW TABLES LIKE 'clientes'");
    if ($checkTable->rowCount() > 0) {
        // Verificar si existe la columna usuario_id
        $checkColumn = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'usuario_id'");
        if ($checkColumn->rowCount() > 0) {
            // Obtener listado de clientes activos asociados al usuario actual
            $query = "SELECT id, nombre FROM clientes WHERE usuario_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Si no existe la columna usuario_id, obtener todos los clientes
            $query = "SELECT id, nombre FROM clientes";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Verificar si la tabla cotizaciones existe
    $checkTable = $pdo->query("SHOW TABLES LIKE 'cotizaciones'");
    if ($checkTable->rowCount() > 0) {
        // Verificar si existe la columna usuario_id
        $checkColumn = $pdo->query("SHOW COLUMNS FROM cotizaciones LIKE 'usuario_id'");
        if ($checkColumn->rowCount() > 0) {
            // Obtener listado de cotizaciones del usuario actual
            $query = "SELECT c.*, cl.nombre as cliente 
                     FROM cotizaciones c 
                     LEFT JOIN clientes cl ON c.cliente_id = cl.id 
                     WHERE c.usuario_id = ?
                     ORDER BY c.fecha DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
        } else {
            // Si no existe la columna usuario_id, obtener todas las cotizaciones
            $query = "SELECT c.*, cl.nombre as cliente 
                     FROM cotizaciones c 
                     LEFT JOIN clientes cl ON c.cliente_id = cl.id 
                     ORDER BY c.fecha DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
        }
        $cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Agregar después de obtener las cotizaciones
    $hoy = date('Y-m-d');

    // Primero, actualizar todas las cotizaciones vencidas
    $query = "UPDATE cotizaciones 
             SET estado = 'Vencida'
             WHERE fecha_vencimiento < ? 
             AND estado NOT IN ('Facturado', 'Cancelada', 'Vencida')";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$hoy]);

    // Luego obtener el listado actualizado
    $query = "SELECT c.*, cl.nombre as cliente 
             FROM cotizaciones c 
             LEFT JOIN clientes cl ON c.cliente_id = cl.id 
             WHERE cl.user_id = ?
             ORDER BY c.fecha DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agregar información de días restantes o vencimiento
    foreach ($cotizaciones as &$cotizacion) {
        if ($cotizacion['estado'] !== 'Facturado' && 
            $cotizacion['estado'] !== 'Cancelada' && 
            $cotizacion['estado'] !== 'Vencida') {
            
            // Calcular días restantes usando la fecha actual
            $fecha_actual = new DateTime();
            $fecha_vencimiento = new DateTime($cotizacion['fecha']);
            $fecha_vencimiento->modify('+30 days');
            
            if ($fecha_actual > $fecha_vencimiento) {
                // Actualizar el estado a vencida
                $query = "UPDATE cotizaciones SET estado = 'Vencida' WHERE id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$cotizacion['id']]);
                $cotizacion['estado'] = 'Vencida';
            } else {
                // Calcular días restantes
                $interval = $fecha_actual->diff($fecha_vencimiento);
                $cotizacion['dias_restantes'] = $interval->days;
            }
        }
    }

} catch (PDOException $e) {
    $error_message = "Error en la base de datos: " . $e->getMessage();
    error_log("Error en cotizaciones/index.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cotizaciones | VendEasy</title>
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
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <strong class="font-bold">¡Éxito!</strong>
                        <span class="block sm:inline"><?= htmlspecialchars($_SESSION['success_message']) ?></span>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline"><?= htmlspecialchars($_SESSION['error_message']) ?></span>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Gestión de Cotizaciones</h2>
                        <a href="crear.php" 
                           class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>
                            Nueva Cotización
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Cotización</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimiento</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($cotizaciones)): ?>
                                    <tr>
                                        <td colspan="6" class="px-3 py-2 text-center text-gray-500">
                                            No hay cotizaciones registradas
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <?php foreach ($cotizaciones as $cotizacion): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <?= htmlspecialchars($cotizacion['numero']) ?>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <?= htmlspecialchars($cotizacion['cliente']) ?>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        $<?= number_format($cotizacion['total'], 2, ',', '.') ?>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?= $cotizacion['estado'] == 'Aprobada' ? 'bg-green-100 text-green-800' : 
                                               ($cotizacion['estado'] == 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                                               ($cotizacion['estado'] == 'Facturado' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')) ?>">
                                            <?= $cotizacion['estado'] ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <?php if ($cotizacion['estado'] === 'Pendiente' || $cotizacion['estado'] === 'Aprobada'): ?>
                                            <?php 
                                            $fecha_vencimiento = new DateTime($cotizacion['fecha']);
                                            $fecha_vencimiento->modify('+30 days');
                                            $fecha_actual = new DateTime();
                                            $interval = $fecha_actual->diff($fecha_vencimiento);
                                            $dias_restantes = $interval->invert ? 0 : $interval->days;
                                            ?>
                                            <?php if ($dias_restantes > 0): ?>
                                                <span class="text-xs <?= $dias_restantes <= 5 ? 'text-red-600 font-medium' : 'text-gray-600' ?>">
                                                    <?= $dias_restantes ?> días restantes
                                                </span>
                                            <?php else: ?>
                                                <span class="text-xs text-red-600 font-medium">Vencida</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">
                                                <?= $fecha_vencimiento->format('d/m/Y') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs font-medium flex items-center gap-1">
                                        <a href="ver.php?id=<?= $cotizacion['id'] ?>" 
                                           class="text-green-600 hover:text-green-900 p-1" 
                                           title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if ($cotizacion['estado'] !== 'Facturado' && $cotizacion['estado'] !== 'Cancelada'): ?>
                                            <a href="editar.php?id=<?= $cotizacion['id'] ?>" 
                                               class="text-indigo-600 hover:text-indigo-900 p-1" 
                                               title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <?php if ($cotizacion['estado'] !== 'Pendiente'): ?>
                                                <button onclick="cambiarEstado(<?= $cotizacion['id'] ?>, 'Pendiente')" 
                                                        class="text-yellow-600 hover:text-yellow-900 p-1"
                                                        title="Marcar como Pendiente">
                                                    <i class="fas fa-clock"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($cotizacion['estado'] !== 'Aprobada'): ?>
                                                <button onclick="cambiarEstado(<?= $cotizacion['id'] ?>, 'Aprobada')" 
                                                        class="text-green-600 hover:text-green-900 p-1"
                                                        title="Aprobar Cotización">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($cotizacion['estado'] === 'Aprobada'): ?>
                                                <button onclick="cambiarEstado(<?= $cotizacion['id'] ?>, 'Facturado')" 
                                                        class="text-blue-600 hover:text-blue-900 p-1"
                                                        title="Facturar Cotización">
                                                    <i class="fas fa-file-invoice"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($cotizacion['estado'] !== 'Cancelada'): ?>
                                                <button onclick="confirmarCancelacion(<?= $cotizacion['id'] ?>)" 
                                                        class="text-red-600 hover:text-red-900 p-1"
                                                        title="Cancelar Cotización">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <a href="imprimir.php?id=<?= $cotizacion['id'] ?>" 
                                           target="_blank"
                                           class="text-gray-600 hover:text-gray-900 p-1" 
                                           title="Imprimir">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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

        function verificarVencimientos() {
            fetch('verificar_vencimientos.php')
                .then(response => response.json())
                .then(data => {
                    if (data.actualizadas > 0) {
                        window.location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Verificar cada 5 minutos
        setInterval(verificarVencimientos, 5 * 60 * 1000);

        // Verificar al cargar la página
        document.addEventListener('DOMContentLoaded', verificarVencimientos);
    </script>
</body>
</html>
