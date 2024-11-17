<?php
session_start();
require_once '../../../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['turno_id'])) {
    header("Location: ../../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$turno_id = $_SESSION['turno_id'];

try {
    // Primero verificamos que el turno exista
    $stmt = $pdo->prepare("
        SELECT * FROM turnos 
        WHERE id = ? AND user_id = ? AND fecha_cierre IS NULL
    ");
    $stmt->execute([$turno_id, $user_id]);
    $turno_base = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno_base) {
        throw new Exception('No se encontró el turno activo');
    }

    // Ahora obtenemos el resumen de ventas con una consulta ajustada a la estructura real
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.fecha_apertura,
            t.monto_inicial,
            t.fecha_cierre,
            COALESCE(SUM(v.total), 0) as total_ventas,
            COUNT(v.id) as cantidad_ventas,
            COALESCE(SUM(CASE WHEN v.metodo_pago = 'efectivo' AND v.anulada = 0 THEN v.total ELSE 0 END), 0) as total_efectivo,
            COALESCE(SUM(CASE WHEN v.metodo_pago = 'tarjeta' AND v.anulada = 0 THEN v.total ELSE 0 END), 0) as total_tarjeta,
            COALESCE(SUM(CASE WHEN v.metodo_pago = 'transferencia' AND v.anulada = 0 THEN v.total ELSE 0 END), 0) as total_transferencia,
            COALESCE(SUM(CASE WHEN v.metodo_pago = 'credito' AND v.anulada = 0 THEN v.total ELSE 0 END), 0) as total_credito,
            COUNT(CASE WHEN v.metodo_pago = 'efectivo' AND v.anulada = 0 THEN 1 END) as ventas_efectivo,
            COUNT(CASE WHEN v.metodo_pago = 'tarjeta' AND v.anulada = 0 THEN 1 END) as ventas_tarjeta,
            COUNT(CASE WHEN v.metodo_pago = 'transferencia' AND v.anulada = 0 THEN 1 END) as ventas_transferencia,
            COUNT(CASE WHEN v.metodo_pago = 'credito' AND v.anulada = 0 THEN 1 END) as ventas_credito
        FROM turnos t
        LEFT JOIN ventas v ON v.turno_id = t.id AND v.anulada = 0
        WHERE t.id = ? AND t.user_id = ?
        GROUP BY t.id, t.fecha_apertura, t.monto_inicial, t.fecha_cierre
    ");
    $stmt->execute([$turno_id, $user_id]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        $turno = $turno_base;
        // Inicializar valores en 0
        $turno['total_ventas'] = 0;
        $turno['cantidad_ventas'] = 0;
        $turno['total_efectivo'] = 0;
        $turno['total_tarjeta'] = 0;
        $turno['total_transferencia'] = 0;
        $turno['total_credito'] = 0;
        $turno['ventas_efectivo'] = 0;
        $turno['ventas_tarjeta'] = 0;
        $turno['ventas_transferencia'] = 0;
        $turno['ventas_credito'] = 0;
    }

    // Obtener las últimas ventas del turno con más detalles
    $stmt = $pdo->prepare("
        SELECT 
            v.id,
            v.fecha,
            v.total,
            v.subtotal,
            v.descuento,
            v.metodo_pago,
            v.numero_factura,
            v.tipo_documento,
            COALESCE(c.nombre, 'Cliente General') as cliente_nombre
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        WHERE v.turno_id = ? AND v.anulada = 0
        ORDER BY v.fecha DESC
        LIMIT 5
    ");
    $stmt->execute([$turno_id]);
    $ultimas_ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agregar debug
    if (empty($ultimas_ventas)) {
        error_log("No se encontraron ventas para el turno ID: " . $turno_id);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $monto_final = filter_input(INPUT_POST, 'monto_final', FILTER_VALIDATE_FLOAT);
        $observaciones = filter_input(INPUT_POST, 'observaciones', FILTER_SANITIZE_STRING);
        
        // Calcular la diferencia
        $diferencia = $monto_final - ($turno['monto_inicial'] + $turno['total_efectivo']);

        if ($monto_final !== false) {
            $stmt = $pdo->prepare("
                UPDATE turnos 
                SET fecha_cierre = NOW(),
                    monto_final = ?,
                    diferencia = ?,
                    observaciones = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$monto_final, $diferencia, $observaciones, $turno_id, $user_id]);

            unset($_SESSION['turno_id']);
            header("Location: ../index.php");
            exit();
        }
    }
} catch (Exception $e) {
    error_log("Error en cierre_turno.php: " . $e->getMessage());
    $error = "Error al procesar el cierre: " . $e->getMessage();
}

// Agregar debug para verificar los valores
error_log("Datos del turno: " . print_r($turno, true));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de Turno | VendEasy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="../../../favicon/favicon.ico">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-clock text-white text-2xl mr-3"></i>
                        <div>
                            <h1 class="text-xl font-bold text-white">Cierre de Turno</h1>
                            <p class="text-red-100 text-sm mt-1">Resumen y cierre de operaciones</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <?php if (isset($error)): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-md">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                                <p class="text-red-700"><?= $error ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Información General -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h2 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
                                <i class="fas fa-info-circle mr-2 text-gray-500"></i>
                                Información General
                            </h2>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                    <span class="text-gray-600">Inicio del turno</span>
                                    <span class="font-medium"><?= date('d/m/Y H:i', strtotime($turno['fecha_apertura'])) ?></span>
                                </div>
                                <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                    <span class="text-gray-600">Monto inicial</span>
                                    <span class="font-medium">$<?= number_format($turno['monto_inicial'], 2, ',', '.') ?></span>
                                </div>
                                <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                    <span class="text-gray-600">Total ventas</span>
                                    <span class="font-medium">$<?= number_format($turno['total_ventas'], 2, ',', '.') ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Cantidad de ventas</span>
                                    <span class="font-medium"><?= $turno['cantidad_ventas'] ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen por método de pago -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h2 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
                                <i class="fas fa-money-bill-wave mr-2 text-gray-500"></i>
                                Ventas por Método de Pago
                            </h2>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                    <span class="text-gray-600">Efectivo (<?= $turno['ventas_efectivo'] ?>)</span>
                                    <span class="font-medium">$<?= number_format($turno['total_efectivo'], 2, ',', '.') ?></span>
                                </div>
                                <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                    <span class="text-gray-600">Tarjeta (<?= $turno['ventas_tarjeta'] ?>)</span>
                                    <span class="font-medium">$<?= number_format($turno['total_tarjeta'], 2, ',', '.') ?></span>
                                </div>
                                <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                                    <span class="text-gray-600">Transferencia (<?= $turno['ventas_transferencia'] ?>)</span>
                                    <span class="font-medium">$<?= number_format($turno['total_transferencia'], 2, ',', '.') ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Crédito (<?= $turno['ventas_credito'] ?>)</span>
                                    <span class="font-medium">$<?= number_format($turno['total_credito'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-200">
                                <div class="flex justify-between items-center text-lg font-semibold">
                                    <span>Total</span>
                                    <span>$<?= number_format($turno['total_ventas'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Últimas ventas -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <h2 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
                            <i class="fas fa-receipt mr-2 text-gray-500"></i>
                            Últimas Ventas
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Método</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($ultimas_ventas as $venta): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('H:i', strtotime($venta['fecha'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= htmlspecialchars($venta['cliente_nombre']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= ucfirst($venta['metodo_pago']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                $<?= number_format($venta['total'], 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Formulario de cierre -->
                    <form method="POST" action="" class="bg-gray-50 rounded-lg p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="monto_final" class="block text-sm font-medium text-gray-700 mb-2">
                                    Monto Final en Caja
                                    <span class="text-sm text-gray-500">
                                        (Esperado: $<?= number_format($turno['monto_inicial'] + $turno['total_efectivo'], 2, ',', '.') ?>)
                                    </span>
                                </label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">$</span>
                                    </div>
                                    <input type="number" 
                                           id="monto_final" 
                                           name="monto_final" 
                                           step="0.01"
                                           required
                                           class="block w-full pl-7 pr-12 py-3 border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                                           value="<?= $turno['monto_inicial'] + $turno['total_efectivo'] ?>">
                                    <div id="diferencia" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-sm">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-2">
                                    Observaciones
                                </label>
                                <textarea id="observaciones" 
                                          name="observaciones" 
                                          rows="3"
                                          class="block w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500"></textarea>
                            </div>
                        </div>

                        <div class="flex justify-between items-center mt-6">
                            <a href="../index.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i class="fas fa-stop mr-2"></i>
                                Cerrar Turno
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const montoFinalInput = document.getElementById('monto_final');
            const diferenciaDiv = document.getElementById('diferencia');
            const montoInicial = <?= $turno['monto_inicial'] ?>;
            const totalEfectivo = <?= $turno['total_efectivo'] ?>;
            const montoEsperado = montoInicial + totalEfectivo;

            function actualizarDiferencia() {
                const montoFinal = parseFloat(montoFinalInput.value) || 0;
                const diferencia = montoFinal - montoEsperado;
                
                if (Math.abs(diferencia) > 0.01) {
                    montoFinalInput.classList.add('border-red-500', 'ring-red-500');
                    diferenciaDiv.classList.remove('text-green-600');
                    diferenciaDiv.classList.add('text-red-600');
                    if (diferencia > 0) {
                        diferenciaDiv.textContent = `Sobrante: $${Math.abs(diferencia).toFixed(2)}`;
                    } else {
                        diferenciaDiv.textContent = `Faltante: $${Math.abs(diferencia).toFixed(2)}`;
                    }
                } else {
                    montoFinalInput.classList.remove('border-red-500', 'ring-red-500');
                    diferenciaDiv.classList.remove('text-red-600');
                    diferenciaDiv.classList.add('text-green-600');
                    diferenciaDiv.textContent = '✓ Correcto';
                }
            }

            montoFinalInput.addEventListener('input', actualizarDiferencia);
            // Ejecutar al cargar para mostrar el estado inicial
            actualizarDiferencia();
        });
    </script>
</body>
</html> 