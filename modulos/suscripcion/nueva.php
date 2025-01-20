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
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Nueva Suscripción</h1>
    <div class="card mb-4">
        <div class="card-body">
            <div class="alert alert-info">
                <h4>¡Bienvenido al sistema!</h4>
                <p>Para comenzar a utilizar todas las funcionalidades, por favor seleccione un plan de suscripción.</p>
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
                            <a href="procesar_pago.php?plan=basico&tipo=nueva" class="btn btn-primary">Seleccionar</a>
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
                            <a href="procesar_pago.php?plan=profesional&tipo=nueva" class="btn btn-primary">Seleccionar</a>
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
                            <a href="procesar_pago.php?plan=empresarial&tipo=nueva" class="btn btn-primary">Seleccionar</a>
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