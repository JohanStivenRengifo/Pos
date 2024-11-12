<?php
session_start();
require_once '../../../config/db.php';

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
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #4a90e2;
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        .card-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
        }
        .form-control:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
        }
        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }
        .btn-primary {
            background-color: #4a90e2;
            border-color: #4a90e2;
        }
        .btn-primary:hover {
            background-color: #357abd;
            border-color: #357abd;
            transform: translateY(-1px);
        }
        .info-box {
            background-color: #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .input-group-text {
            border-radius: 10px 0 0 10px;
            background-color: #f8f9fa;
        }
        .input-group .form-control {
            border-radius: 0 10px 10px 0;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-cash-register mr-2"></i>Apertura de Turno
                        </h4>
                        <p class="mb-0 mt-2 text-white-50">
                            Complete la información para iniciar un nuevo turno
                        </p>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($ultimo_turno): ?>
                            <div class="info-box">
                                <h6 class="mb-3">
                                    <i class="fas fa-history mr-2"></i>Información del Último Turno
                                </h6>
                                <div class="info-item">
                                    <span>Fecha de cierre:</span>
                                    <strong><?= date('d/m/Y H:i', strtotime($ultimo_turno['fecha_cierre'])) ?></strong>
                                </div>
                                <div class="info-item">
                                    <span>Monto final:</span>
                                    <strong>$<?= number_format($ultimo_turno['monto_final'], 2, ',', '.') ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="aperturaTurnoForm">
                            <div class="form-group">
                                <label for="monto_inicial">
                                    <i class="fas fa-money-bill-wave mr-2"></i>Monto Inicial en Caja
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">$</span>
                                    </div>
                                    <input type="number" 
                                           class="form-control" 
                                           id="monto_inicial" 
                                           name="monto_inicial" 
                                           step="0.01" 
                                           required
                                           <?php if ($ultimo_turno): ?>
                                           value="<?= $ultimo_turno['monto_final'] ?>"
                                           <?php endif; ?>>
                                </div>
                                <small class="form-text text-muted">
                                    Ingrese el monto con el que inicia el turno
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="observaciones">
                                    <i class="fas fa-comment-alt mr-2"></i>Observaciones
                                </label>
                                <textarea class="form-control" 
                                          id="observaciones" 
                                          name="observaciones" 
                                          rows="3"
                                          placeholder="Ingrese cualquier observación relevante para la apertura del turno"></textarea>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <a href="../index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left mr-2"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-play mr-2"></i>Iniciar Turno
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

            // Formatear el monto mientras se escribe
            montoInput.addEventListener('input', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        });
    </script>
</body>
</html> 