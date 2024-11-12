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
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .resumen-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(0,0,0,0.1);
        }
        .metodo-pago-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .metodo-pago-item:last-child {
            border-bottom: none;
        }
        .total-general {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
            padding-top: 1rem;
            margin-top: 1rem;
            border-top: 2px solid rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-clock mr-2"></i>Cierre de Turno</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <div class="row">
                            <!-- Información general del turno -->
                            <div class="col-md-6">
                                <div class="resumen-card">
                                    <h6 class="mb-3"><i class="fas fa-info-circle mr-2"></i>Información General</h6>
                                    <div class="metodo-pago-item">
                                        <span>Inicio del turno:</span>
                                        <strong><?= date('d/m/Y H:i', strtotime($turno['fecha_apertura'])) ?></strong>
                                    </div>
                                    <div class="metodo-pago-item">
                                        <span>Monto inicial:</span>
                                        <strong>$<?= number_format($turno['monto_inicial'], 2, ',', '.') ?></strong>
                                    </div>
                                    <div class="metodo-pago-item">
                                        <span>Total ventas:</span>
                                        <strong>$<?= number_format($turno['total_ventas'], 2, ',', '.') ?></strong>
                                    </div>
                                    <div class="metodo-pago-item">
                                        <span>Cantidad de ventas:</span>
                                        <strong><?= $turno['cantidad_ventas'] ?></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Resumen por método de pago -->
                            <div class="col-md-6">
                                <div class="resumen-card">
                                    <h6 class="mb-3"><i class="fas fa-money-bill-wave mr-2"></i>Ventas por Método de Pago</h6>
                                    <div class="metodo-pago-item">
                                        <span>Efectivo (<?= $turno['ventas_efectivo'] ?>):</span>
                                        <strong>$<?= number_format($turno['total_efectivo'], 2, ',', '.') ?></strong>
                                    </div>
                                    <div class="metodo-pago-item">
                                        <span>Tarjeta (<?= $turno['ventas_tarjeta'] ?>):</span>
                                        <strong>$<?= number_format($turno['total_tarjeta'], 2, ',', '.') ?></strong>
                                    </div>
                                    <div class="metodo-pago-item">
                                        <span>Transferencia (<?= $turno['ventas_transferencia'] ?>):</span>
                                        <strong>$<?= number_format($turno['total_transferencia'], 2, ',', '.') ?></strong>
                                    </div>
                                    <div class="metodo-pago-item">
                                        <span>Crédito (<?= $turno['ventas_credito'] ?>):</span>
                                        <strong>$<?= number_format($turno['total_credito'], 2, ',', '.') ?></strong>
                                    </div>
                                    <div class="total-general">
                                        <span>Total:</span>
                                        <strong>$<?= number_format($turno['total_ventas'], 2, ',', '.') ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Últimas ventas -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="resumen-card">
                                    <h6 class="mb-3"><i class="fas fa-receipt mr-2"></i>Últimas Ventas</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Hora</th>
                                                    <th>Cliente</th>
                                                    <th>Método</th>
                                                    <th class="text-right">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ultimas_ventas as $venta): ?>
                                                    <tr>
                                                        <td><?= date('H:i', strtotime($venta['fecha'])) ?></td>
                                                        <td><?= htmlspecialchars($venta['cliente_nombre']) ?></td>
                                                        <td><?= ucfirst($venta['metodo_pago']) ?></td>
                                                        <td class="text-right">$<?= number_format($venta['total'], 2, ',', '.') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Formulario de cierre -->
                        <form method="POST" action="" class="mt-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="monto_final">Monto Final en Caja</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">$</span>
                                            </div>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="monto_final" 
                                                   name="monto_final" 
                                                   step="0.01" 
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="observaciones">Observaciones</label>
                                        <textarea class="form-control" 
                                                  id="observaciones" 
                                                  name="observaciones" 
                                                  rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right mt-3">
                                <a href="../index.php" class="btn btn-secondary mr-2">
                                    <i class="fas fa-arrow-left mr-1"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-stop mr-2"></i>Cerrar Turno
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Calcular diferencia al ingresar monto final
        document.getElementById('monto_final').addEventListener('input', function() {
            const montoInicial = <?= $turno['monto_inicial'] ?>;
            const totalEfectivo = <?= $turno['total_efectivo'] ?>;
            const montoFinal = parseFloat(this.value) || 0;
            const diferenciaEsperada = (montoInicial + totalEfectivo) - montoFinal;
            
            // Aquí puedes agregar lógica para mostrar la diferencia
            // Por ejemplo, cambiar el color del input según si hay faltante o sobrante
            if (Math.abs(diferenciaEsperada) > 0.01) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>
</html> 