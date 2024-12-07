<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$nomina = null;

try {
    if (!isset($_GET['id'])) {
        throw new Exception("ID de nómina no especificado");
    }

    // Obtener información de la empresa principal
    $query = "SELECT * FROM empresas 
              WHERE usuario_id = ? AND es_principal = 1 
              LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $empresa = $stmt->fetch();

    if (!$empresa) {
        throw new Exception("No se encontró información de la empresa");
    }

    // Obtener datos de la nómina
    $query = "SELECT n.*, 
                     u.nombre as empleado_nombre,
                     u.email as empleado_email,
                     u.rol as empleado_rol
              FROM nominas n
              JOIN users u ON n.empleado_id = u.id
              WHERE n.id = ? AND u.empresa_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id'], $_SESSION['empresa_id']]);
    $nomina = $stmt->fetch();

    if (!$nomina) {
        throw new Exception("Nómina no encontrada");
    }

    // Calcular el total
    $total = $nomina['salario_base'] - $nomina['deducciones'] + $nomina['bonificaciones'];

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Nómina | VendEasy</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .no-print {
                display: none;
            }
            @page {
                margin: 1cm;
                size: letter;
            }
        }
    </style>
</head>
<body class="bg-white p-8 max-w-4xl mx-auto">
    <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
        </div>
    <?php else: ?>
        <!-- Botón de Imprimir -->
        <div class="mb-6 no-print text-right">
            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-print mr-2"></i>Imprimir
            </button>
        </div>

        <!-- Encabezado -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">COMPROBANTE DE PAGO</h1>
            <p class="text-xl text-gray-600 mt-2">Período: <?= htmlspecialchars($nomina['periodo']) ?></p>
        </div>

        <!-- Información de la Empresa y Empleado -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            <div class="border-r pr-8">
                <h2 class="text-base font-bold text-gray-800 mb-3 border-b pb-2">Información de la Empresa</h2>
                <p class="font-semibold text-gray-800 text-sm mb-2"><?= htmlspecialchars($empresa['nombre_empresa']) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium">NIT:</span> <?= htmlspecialchars($empresa['nit']) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium">Dirección:</span> <?= htmlspecialchars($empresa['direccion']) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium">Teléfono:</span> <?= htmlspecialchars($empresa['telefono']) ?></p>
                <p class="text-xs text-gray-600"><span class="font-medium">Email:</span> <?= htmlspecialchars($empresa['correo_contacto']) ?></p>
            </div>
            <div class="pl-8">
                <h2 class="text-base font-bold text-gray-800 mb-3 border-b pb-2">Información del Empleado</h2>
                <p class="font-semibold text-gray-800 text-sm mb-2"><?= htmlspecialchars($nomina['empleado_nombre']) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium">Cargo:</span> <?= ucfirst(htmlspecialchars($nomina['empleado_rol'])) ?></p>
                <p class="text-xs text-gray-600 mb-1"><span class="font-medium">Email:</span> <?= htmlspecialchars($nomina['empleado_email']) ?></p>
                <p class="text-xs text-gray-600"><span class="font-medium">Fecha de Pago:</span> <?= date('d/m/Y', strtotime($nomina['fecha_pago'])) ?></p>
            </div>
        </div>

        <!-- Detalles de la Nómina -->
        <div class="mb-8">
            <h3 class="text-base font-bold text-gray-800 mb-4">Detalles del Pago</h3>
            <div class="bg-gray-50 p-6 rounded-lg">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="mb-3"><span class="font-medium">Salario Base:</span> $<?= number_format($nomina['salario_base'], 2, ',', '.') ?></p>
                        <p class="mb-3"><span class="font-medium">Bonificaciones:</span> $<?= number_format($nomina['bonificaciones'], 2, ',', '.') ?></p>
                        <p class="mb-3"><span class="font-medium">Deducciones:</span> $<?= number_format($nomina['deducciones'], 2, ',', '.') ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-gray-800">
                            <span class="mr-4">Total a Pagar:</span>
                            $<?= number_format($total, 2, ',', '.') ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Firmas -->
        <div class="grid grid-cols-2 gap-8 mt-16">
            <div class="text-center">
                <div class="border-t border-gray-400 pt-2">
                    <p class="font-medium text-sm text-gray-800">Firma del Empleado</p>
                    <p class="text-xs text-gray-600"><?= htmlspecialchars($nomina['empleado_nombre']) ?></p>
                </div>
            </div>
            <div class="text-center">
                <div class="border-t border-gray-400 pt-2">
                    <p class="font-medium text-sm text-gray-800">Por la Empresa</p>
                    <p class="text-xs text-gray-600"><?= htmlspecialchars($empresa['nombre_empresa']) ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Imprimir automáticamente al cargar la página
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html> 