<?php
require_once '../../config/db.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Verificar si hay una sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit();
}

$id_empresa = $_SESSION['empresa_id'];

// Obtener información de la última suscripción
$sql = "SELECT fecha_vencimiento, estado, tipo_plan 
        FROM suscripciones 
        WHERE id_empresa = ? 
        ORDER BY id DESC 
        LIMIT 1";

$stmt = $conexion->prepare($sql);
$stmt->bind_param('i', $id_empresa);
$stmt->execute();
$result = $stmt->get_result();
$suscripcion = $result->fetch_assoc();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Renovar Suscripción</h1>
    <div class="card mb-4">
        <div class="card-body">
            <div class="alert alert-warning">
                <h4>Tu suscripción ha expirado o está por expirar</h4>
                <?php if ($suscripcion): ?>
                    <p>Fecha de vencimiento: <?php echo date('d/m/Y', strtotime($suscripcion['fecha_vencimiento'])); ?></p>
                    <p>Plan actual: <?php echo htmlspecialchars($suscripcion['tipo_plan']); ?></p>
                <?php endif; ?>
            </div>
            
            <h3>Planes Disponibles</h3>
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Plan Básico</h5>
                            <h6 class="card-subtitle mb-2 text-muted">$29.99/mes</h6>
                            <p class="card-text">
                                - Hasta 5 usuarios<br>
                                - Funciones básicas<br>
                                - Soporte por email
                            </p>
                            <a href="procesar_pago.php?plan=basico" class="btn btn-primary">Seleccionar</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Plan Profesional</h5>
                            <h6 class="card-subtitle mb-2 text-muted">$49.99/mes</h6>
                            <p class="card-text">
                                - Hasta 15 usuarios<br>
                                - Funciones avanzadas<br>
                                - Soporte prioritario
                            </p>
                            <a href="procesar_pago.php?plan=profesional" class="btn btn-primary">Seleccionar</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Plan Empresarial</h5>
                            <h6 class="card-subtitle mb-2 text-muted">$99.99/mes</h6>
                            <p class="card-text">
                                - Usuarios ilimitados<br>
                                - Todas las funciones<br>
                                - Soporte 24/7
                            </p>
                            <a href="procesar_pago.php?plan=empresarial" class="btn btn-primary">Seleccionar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?> 