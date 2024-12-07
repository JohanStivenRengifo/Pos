<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$success_message = '';
$credito = null;
$pagos_pendientes = [];

try {
    if (!isset($_GET['id'])) {
        throw new Exception("ID de crédito no especificado");
    }

    // Obtener información del crédito
    $query = "SELECT c.*, 
                     v.numero_factura,
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre,
                     cl.identificacion as cliente_identificacion
              FROM creditos c
              LEFT JOIN ventas v ON c.venta_id = v.id
              LEFT JOIN clientes cl ON v.cliente_id = cl.id
              WHERE c.id = ? AND cl.user_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $credito = $stmt->fetch();

    if (!$credito) {
        throw new Exception("Crédito no encontrado");
    }

    if ($credito['estado'] === 'Pagado') {
        throw new Exception("Este crédito ya está pagado completamente");
    }

    // Obtener pagos pendientes
    $query = "SELECT * FROM creditos_pagos 
              WHERE credito_id = ? AND estado != 'Pagado'
              ORDER BY numero_cuota ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $pagos_pendientes = $stmt->fetchAll();

    if (empty($pagos_pendientes)) {
        throw new Exception("No hay cuotas pendientes de pago");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['pago_id'])) {
            throw new Exception("Debe seleccionar una cuota");
        }
        if (empty($_POST['metodo_pago'])) {
            throw new Exception("Debe seleccionar un método de pago");
        }
        if (empty($_POST['fecha_pago'])) {
            throw new Exception("Debe especificar la fecha de pago");
        }

        // Iniciar transacción
        $pdo->beginTransaction();

        try {
            // Verificar si hay un turno activo
            $query = "SELECT id FROM turnos 
                      WHERE user_id = ? 
                      AND fecha_cierre IS NULL  /* Solo verificamos que no esté cerrado */
                      LIMIT 1";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            $turno_activo = $stmt->fetch();

            if (!$turno_activo && $_POST['metodo_pago'] === 'Efectivo') {
                throw new Exception("Debe abrir un turno para registrar pagos en efectivo");
            }

            // Obtener información del pago
            $query = "SELECT * FROM creditos_pagos WHERE id = ? AND credito_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_POST['pago_id'], $_GET['id']]);
            $pago = $stmt->fetch();

            if (!$pago) {
                throw new Exception("Cuota no encontrada");
            }

            if ($pago['estado'] === 'Pagado') {
                throw new Exception("Esta cuota ya está pagada");
            }

            // Registrar el pago
            $query = "UPDATE creditos_pagos 
                     SET estado = 'Pagado',
                         fecha_pago = ?,
                         metodo_pago = ?,
                         referencia = ?,
                         notas = ?
                     WHERE id = ?";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $_POST['fecha_pago'],
                $_POST['metodo_pago'],
                $_POST['referencia'] ?? null,
                $_POST['notas'] ?? null,
                $_POST['pago_id']
            ]);

            // Actualizar saldo del crédito
            $nuevo_saldo = $credito['saldo_pendiente'] - $pago['monto'];
            $nuevo_monto_pagado = $credito['monto_pagado'] + $pago['monto'];
            
            $query = "UPDATE creditos 
                     SET saldo_pendiente = ?,
                         monto_pagado = ?,
                         estado = CASE 
                            WHEN ? <= 0 THEN 'Pagado'
                            ELSE 'Al día'
                         END
                     WHERE id = ?";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $nuevo_saldo,
                $nuevo_monto_pagado,
                $nuevo_saldo,
                $_GET['id']
            ]);

            // Registrar el ingreso
            $query = "INSERT INTO ingresos (
                user_id,
                descripcion,
                monto,
                metodo_pago,
                categoria,
                tipo,
                estado,
                notas,
                credito_id,
                credito_pago_id
            ) VALUES (?, ?, ?, ?, 'Créditos', 'Abono', 'Completado', ?, ?, ?)";

            $descripcion = "Abono a crédito - Factura #{$credito['numero_factura']} - Cuota {$pago['numero_cuota']}/{$credito['cuotas']}";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $_SESSION['user_id'],
                $descripcion,
                $pago['monto'],
                $_POST['metodo_pago'],
                $_POST['notas'] ?? null,
                $credito['id'],
                $pago['id']
            ]);

            // Si hay turno activo y es efectivo, actualizar el monto final del turno
            if ($turno_activo && $_POST['metodo_pago'] === 'Efectivo') {
                $query = "UPDATE turnos 
                         SET monto_final = monto_final + ?,
                             diferencia = (monto_final + ?) - monto_inicial
                         WHERE id = ?";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    $pago['monto'],
                    $pago['monto'],
                    $turno_activo['id']
                ]);
            }

            $pdo->commit();
            $_SESSION['success_message'] = "Pago registrado exitosamente";
            header("Location: ver.php?id=" . $_GET['id']);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Pago | VendEasy</title>
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
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Registrar Pago</h2>
                        <a href="ver.php?id=<?= $_GET['id'] ?>" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left mr-2"></i>Volver
                        </a>
                    </div>

                    <!-- Información del Crédito -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Cliente:</span> 
                                    <?= htmlspecialchars($credito['cliente_nombre']) ?>
                                </p>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Identificación:</span> 
                                    <?= htmlspecialchars($credito['cliente_identificacion']) ?>
                                </p>
                                <p class="text-sm">
                                    <span class="font-medium">Factura:</span> 
                                    #<?= htmlspecialchars($credito['numero_factura']) ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Monto Total:</span> 
                                    $<?= number_format($credito['monto_total'], 2, ',', '.') ?>
                                </p>
                                <p class="text-sm mb-1">
                                    <span class="font-medium">Saldo Pendiente:</span> 
                                    $<?= number_format($credito['saldo_pendiente'], 2, ',', '.') ?>
                                </p>
                                <p class="text-sm">
                                    <span class="font-medium">Estado:</span> 
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?= $credito['estado'] === 'Al día' ? 'bg-green-100 text-green-800' : 
                                           ($credito['estado'] === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($credito['estado'] === 'Atrasado' ? 'bg-orange-100 text-orange-800' : 
                                           'bg-red-100 text-red-800')) ?>">
                                        <?= $credito['estado'] ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <form method="POST" class="space-y-6">
                        <!-- Selección de Cuota -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Seleccionar Cuota *</label>
                            <select name="pago_id" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">Seleccione una cuota</option>
                                <?php foreach ($pagos_pendientes as $pago): ?>
                                    <option value="<?= $pago['id'] ?>">
                                        Cuota <?= $pago['numero_cuota'] ?> - 
                                        Vence: <?= date('d/m/Y', strtotime($pago['fecha_vencimiento_cuota'])) ?> - 
                                        $<?= number_format($pago['monto'], 2, ',', '.') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Método de Pago -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Método de Pago *</label>
                            <select name="metodo_pago" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">Seleccione un método</option>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Tarjeta">Tarjeta</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>

                        <!-- Fecha de Pago -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Pago *</label>
                            <input type="date" name="fecha_pago" required
                                   value="<?= date('Y-m-d') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>

                        <!-- Referencia -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Referencia</label>
                            <input type="text" name="referencia"
                                   placeholder="Número de transferencia, cheque, etc."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>

                        <!-- Notas -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                            <textarea name="notas" rows="3"
                                      placeholder="Observaciones adicionales"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"></textarea>
                        </div>

                        <div class="flex justify-end space-x-4 pt-6 border-t">
                            <a href="ver.php?id=<?= $_GET['id'] ?>" 
                               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                Cancelar
                            </a>
                            <button type="submit"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Registrar Pago
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 