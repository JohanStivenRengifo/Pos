<?php
session_start();
require_once '../../config/db.php';

if (!isset($_GET['turno_id'])) {
    header('Location: ../index.php');
    exit();
}

$turnoId = $_GET['turno_id'];

try {
    // Obtener información del turno
    $stmt = $pdo->prepare("
        SELECT t.*, u.nombre as usuario_nombre
        FROM turnos t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND t.fecha_cierre IS NULL
    ");
    $stmt->execute([$turnoId]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        header('Location: ../index.php');
        exit();
    }

    // Obtener ventas del turno
    $stmt = $pdo->prepare("
        SELECT v.*, 
               CONCAT(c.primer_nombre, ' ', COALESCE(c.segundo_nombre, ''), ' ', c.apellidos) as cliente_nombre
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        WHERE v.turno_id = ?
        ORDER BY v.fecha DESC
        LIMIT 10
    ");
    $stmt->execute([$turnoId]);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular totales
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            COALESCE(SUM(total), 0) as total_vendido,
            COALESCE(SUM(CASE WHEN metodo_pago = 'efectivo' THEN total ELSE 0 END), 0) as total_efectivo,
            COALESCE(SUM(CASE WHEN metodo_pago != 'efectivo' THEN total ELSE 0 END), 0) as total_otros
        FROM ventas
        WHERE turno_id = ?
    ");
    $stmt->execute([$turnoId]);
    $totales = $stmt->fetch(PDO::FETCH_ASSOC);

    $dineroEsperado = $turno['monto_inicial'] + $totales['total_efectivo'];
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de Turno | VendEasy</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
</head>
<body class="h-full">
    <div class="min-h-full">
        <div class="bg-indigo-600 pb-32">
            <header class="py-10">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <h1 class="text-3xl font-bold tracking-tight text-white">Cierre de Turno</h1>
                </div>
            </header>
        </div>

        <main class="-mt-32">
            <div class="mx-auto max-w-7xl px-4 pb-12 sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg p-6 shadow">
                    <!-- Información del turno -->
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold mb-4">Información del Turno</h2>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-500">Apertura</p>
                                <p class="font-medium"><?= date('d/m/Y H:i', strtotime($turno['fecha_apertura'])) ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-500">Base Inicial</p>
                                <p class="font-medium">$<?= number_format($turno['monto_inicial'], 0, ',', '.') ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-500">Total Ventas</p>
                                <p class="font-medium"><?= $totales['total_ventas'] ?> ventas</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-500">Total Vendido</p>
                                <p class="font-medium">$<?= number_format($totales['total_vendido'], 0, ',', '.') ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Balance de caja -->
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold mb-4">Balance de Caja</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-green-50 p-4 rounded-lg">
                                <p class="text-sm text-green-600">Efectivo</p>
                                <p class="font-medium">$<?= number_format($totales['total_efectivo'], 0, ',', '.') ?></p>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <p class="text-sm text-blue-600">Otros Medios</p>
                                <p class="font-medium">$<?= number_format($totales['total_otros'], 0, ',', '.') ?></p>
                            </div>
                            <div class="bg-indigo-50 p-4 rounded-lg">
                                <p class="text-sm text-indigo-600">Dinero en Caja Esperado</p>
                                <p class="font-medium">$<?= number_format($dineroEsperado, 0, ',', '.') ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Conteo de caja -->
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold mb-4">Conteo de Caja</h2>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Dinero en caja</label>
                                <div class="text-sm text-gray-500 mb-2">Esperado en caja: $<?= number_format($dineroEsperado, 0, ',', '.') ?></div>
                                <input type="number" 
                                       id="dinero_caja" 
                                       value="<?= $dineroEsperado ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" 
                                       placeholder="Ingrese el monto contado">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Observaciones</label>
                                <textarea id="observaciones" 
                                          rows="3" 
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" 
                                          placeholder="Ingrese observaciones si las hay">Cierre de turno</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Últimas ventas -->
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold mb-4">Últimas Ventas</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Hora</th>
                                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Cliente</th>
                                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Total</th>
                                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Método</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <?php foreach ($ventas as $venta): ?>
                                    <tr>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <?= date('H:i', strtotime($venta['fecha'])) ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <?= htmlspecialchars($venta['cliente_nombre']) ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            $<?= number_format($venta['total'], 0, ',', '.') ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <?= ucfirst($venta['metodo_pago']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex justify-end space-x-3">
                        <button onclick="window.location.href='../index.php'" class="px-4 py-2 border rounded-md hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button onclick="confirmarCierre(<?= $turnoId ?>, <?= $dineroEsperado ?>)" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Confirmar Cierre
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    function confirmarCierre(turnoId, dineroEsperado) {
        const dineroCaja = parseFloat(document.getElementById('dinero_caja').value) || 0;
        const observaciones = document.getElementById('observaciones').value;
        const diferencia = dineroCaja - dineroEsperado;

        if (dineroCaja === 0) {
            Swal.fire({
                title: 'Error',
                text: 'Debe ingresar el dinero en caja',
                icon: 'error'
            });
            return;
        }

        let mensajeDiferencia = '';
        if (diferencia !== 0) {
            mensajeDiferencia = `\nDiferencia: ${diferencia > 0 ? 'Sobrante' : 'Faltante'} de $${Math.abs(diferencia).toLocaleString()}`;
        }

        Swal.fire({
            title: '¿Confirmar cierre de turno?',
            text: `Dinero en caja: $${dineroCaja.toLocaleString()}${mensajeDiferencia}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cerrar turno',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('./cerrar_turno.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        turno_id: turnoId,
                        dinero_caja: dineroCaja,
                        observaciones: observaciones
                    })
                })
                .then(async response => {
                    const text = await response.text();
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Error parsing JSON:', text);
                        throw new Error('Error en la respuesta del servidor');
                    }
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: '¡Turno cerrado!',
                            text: data.message,
                            icon: 'success'
                        }).then(() => {
                            window.location.href = 'apertura_turno.php';
                        });
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error',
                        text: error.message,
                        icon: 'error'
                    });
                });
            }
        });
    }
    </script>
</body>
</html>
<?php
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 