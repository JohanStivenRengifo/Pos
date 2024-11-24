<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Verificar si ya hay un turno activo
$stmt = $pdo->prepare("SELECT id FROM turnos WHERE user_id = ? AND fecha_cierre IS NULL");
$stmt->execute([$user_id]);
if ($stmt->fetch()) {
    header("Location: ../index.php");
    exit();
}

// Obtener información del último turno
$stmt = $pdo->prepare("
    SELECT fecha_cierre, monto_final 
    FROM turnos 
    WHERE user_id = ? 
    ORDER BY fecha_cierre DESC 
    LIMIT 1
");
$stmt->execute([$user_id]);
$ultimo_turno = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto_inicial = filter_input(INPUT_POST, 'monto_inicial', FILTER_VALIDATE_FLOAT);
    $observaciones = filter_input(INPUT_POST, 'observaciones', FILTER_SANITIZE_STRING);
    
    if ($monto_inicial !== false) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO turnos (user_id, fecha_apertura, monto_inicial, observaciones) 
                VALUES (?, NOW(), ?, ?)
            ");
            $stmt->execute([$user_id, $monto_inicial, $observaciones]);
            
            $_SESSION['turno_id'] = $pdo->lastInsertId();
            header("Location: ../index.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error al abrir el turno: " . $e->getMessage();
        }
    } else {
        $error = "El monto inicial no es válido";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apertura de Turno | VendEasy</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Card principal -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-cash-register text-white text-2xl mr-3"></i>
                        <div>
                            <h1 class="text-xl font-bold text-white">Apertura de Turno</h1>
                            <p class="text-blue-100 text-sm mt-1">Complete la información para iniciar un nuevo turno</p>
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

                    <?php if ($ultimo_turno): ?>
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h2 class="text-gray-700 font-semibold mb-3 flex items-center">
                                <i class="fas fa-history mr-2 text-gray-500"></i>
                                Último Turno Cerrado
                            </h2>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-white p-3 rounded-lg shadow-sm">
                                    <span class="text-sm text-gray-500">Fecha de cierre</span>
                                    <p class="font-medium text-gray-800 mt-1">
                                        <?= date('d/m/Y H:i', strtotime($ultimo_turno['fecha_cierre'])) ?>
                                    </p>
                                </div>
                                <div class="bg-white p-3 rounded-lg shadow-sm">
                                    <span class="text-sm text-gray-500">Monto final</span>
                                    <p class="font-medium text-gray-800 mt-1">
                                        $<?= number_format($ultimo_turno['monto_final'], 2, ',', '.') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="aperturaTurnoForm" class="space-y-6">
                        <div>
                            <label for="monto_inicial" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-money-bill-wave mr-2 text-gray-500"></i>
                                Monto Inicial en Caja
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">$</span>
                                </div>
                                <input type="number" 
                                       id="monto_inicial" 
                                       name="monto_inicial" 
                                       step="0.01"
                                       required
                                       class="block w-full pl-7 pr-12 py-3 border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                       value="<?= $ultimo_turno ? $ultimo_turno['monto_final'] : '' ?>">
                            </div>
                            <p class="mt-2 text-sm text-gray-500">Ingrese el monto con el que inicia el turno</p>
                        </div>

                        <div>
                            <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-comment-alt mr-2 text-gray-500"></i>
                                Observaciones
                            </label>
                            <textarea id="observaciones" 
                                      name="observaciones" 
                                      rows="3"
                                      class="block w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Ingrese cualquier observación relevante..."></textarea>
                        </div>

                        <div class="flex justify-between items-center pt-4">
                            <a href="../index.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-play mr-2"></i>
                                Iniciar Turno
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('aperturaTurnoForm');
            const montoInput = document.getElementById('monto_inicial');

            form.addEventListener('submit', function(e) {
                if (!montoInput.value || montoInput.value < 0) {
                    e.preventDefault();
                    alert('Por favor ingrese un monto inicial válido');
                    montoInput.focus();
                }
            });

            montoInput.addEventListener('input', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        });
    </script>
</body>
</html> 