<?php
$pdo = getPDOConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener el ID del usuario actual
$user_id = $_SESSION['user_id'];

// Obtener configuración del usuario
function getUserConfiguration($user_id) {
    $pdo = getPDOConnection();
    $stmt = $pdo->prepare("SELECT * FROM configuraciones WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Inicializar la configuración del usuario
$config = getUserConfiguration($user_id);
if ($config === false) {
    $config = [
        'zona_horaria' => '',
        'moneda' => '',
        'idioma' => '',
        'frecuencia_backup' => '',
        'tipo_backup' => '',
        'ubicacion_backup' => '',
        'modulos_activos' => json_encode(['empresa' => 1, 'sistema' => 1, 'usuarios' => 1, 'puntos_de_venta' => 1, 'avanzada' => 1, 'seguridad' => 1])
    ];
}

// Actualizar configuración del usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['perform_backup'])) {
        performBackups($config);
    } else {
        $zona_horaria = $_POST['zona_horaria'] ?? '';
        $moneda = $_POST['moneda'] ?? '';
        $idioma = $_POST['idioma'] ?? '';
        $frecuencia_backup = $_POST['frecuencia_backup'] ?? '';
        $tipo_backup = $_POST['tipo_backup'] ?? '';
        $ubicacion_backup = $_POST['ubicacion_backup'] ?? '';
        $modulos_activos = isset($_POST['modulos_activos']) ? json_encode($_POST['modulos_activos']) : json_encode([]);

        $config = [
            'zona_horaria' => $zona_horaria,
            'moneda' => $moneda,
            'idioma' => $idioma,
            'frecuencia_backup' => $frecuencia_backup,
            'tipo_backup' => $tipo_backup,
            'ubicacion_backup' => $ubicacion_backup,
            'modulos_activos' => $modulos_activos,
        ];

        updateConfigurationAndModules($user_id, $config, json_decode($modulos_activos, true));
    }
}

// Función para obtener opciones para listas desplegables
function getOptions($type) {
    $options = [];
    switch ($type) {
        case 'zona_horaria':
            $options = DateTimeZone::listIdentifiers();
            break;
        case 'moneda':
            $options = ['USD' => 'Dólar Estadounidense', 'EUR' => 'Euro', 'COP' => 'Peso Colombiano'];
            break;
        case 'idioma':
            $options = ['es' => 'Español', 'en' => 'Inglés', 'fr' => 'Francés'];
            break;
        case 'frecuencia_backup':
            $options = ['diario' => 'Diario', 'semanal' => 'Semanal', 'mensual' => 'Mensual'];
            break;
        case 'tipo_backup':
            $options = ['completo' => 'Completo', 'incremental' => 'Incremental', 'diferencial' => 'Diferencial'];
            break;
    }
    return $options;
}
?>

<style>
    /* Estilo general del contenedor del formulario */
    .form-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        background-color: #f9f9f9;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* Estilo para el formulario */
    .config-form {
        display: flex;
        flex-direction: column;
    }

    .config-form label {
        font-weight: bold;
        margin-bottom: 5px;
    }

    .config-form .form-input,
    .config-form .form-select {
        padding: 10px;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .config-form .form-button {
        padding: 10px 15px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .config-form .form-button:hover {
        background-color: #0056b3;
    }

    /* Estilo para mensajes de éxito y error */
    .success-message {
        color: #28a745;
        font-weight: bold;
        margin-bottom: 15px;
    }

    .error-message {
        color: #dc3545;
        font-weight: bold;
        margin-bottom: 15px;
    }
</style>

<div class="form-container">
    <h1>Configuración del Sistema</h1>
    <form method="post" class="config-form">
        <button type="submit" name="perform_backup" class="form-button">Realizar Backup Ahora</button>
        <br>
        <label for="zona_horaria">Zona Horaria:</label>
        <select id="zona_horaria" name="zona_horaria" class="form-select">
            <?php
            $zona_horaria_options = getOptions('zona_horaria');
            foreach ($zona_horaria_options as $zone) {
                $selected = ($config['zona_horaria'] === $zone) ? 'selected' : '';
                echo "<option value=\"$zone\" $selected>" . htmlspecialchars($zone) . "</option>";
            }
            ?>
        </select>

        <label for="moneda">Moneda:</label>
        <select id="moneda" name="moneda" class="form-select">
            <?php
            $moneda_options = getOptions('moneda');
            foreach ($moneda_options as $value => $label) {
                $selected = ($config['moneda'] === $value) ? 'selected' : '';
                echo "<option value=\"$value\" $selected>" . htmlspecialchars($label) . "</option>";
            }
            ?>
        </select>

        <label for="idioma">Idioma:</label>
        <select id="idioma" name="idioma" class="form-select">
            <?php
            $idioma_options = getOptions('idioma');
            foreach ($idioma_options as $value => $label) {
                $selected = ($config['idioma'] === $value) ? 'selected' : '';
                echo "<option value=\"$value\" $selected>" . htmlspecialchars($label) . "</option>";
            }
            ?>
        </select>

        <label for="frecuencia_backup">Frecuencia de Backup:</label>
        <select id="frecuencia_backup" name="frecuencia_backup" class="form-select">
            <?php
            $frecuencia_backup_options = getOptions('frecuencia_backup');
            foreach ($frecuencia_backup_options as $value => $label) {
                $selected = ($config['frecuencia_backup'] === $value) ? 'selected' : '';
                echo "<option value=\"$value\" $selected>" . htmlspecialchars($label) . "</option>";
            }
            ?>
        </select>

        <label for="tipo_backup">Tipo de Backup:</label>
        <select id="tipo_backup" name="tipo_backup" class="form-select">
            <?php
            $tipo_backup_options = getOptions('tipo_backup');
            foreach ($tipo_backup_options as $value => $label) {
                $selected = ($config['tipo_backup'] === $value) ? 'selected' : '';
                echo "<option value=\"$value\" $selected>" . htmlspecialchars($label) . "</option>";
            }
            ?>
        </select>

        <label for="ubicacion_backup">Ubicación de Backup:</label>
        <input type="text" id="ubicacion_backup" name="ubicacion_backup" value="<?php echo htmlspecialchars($config['ubicacion_backup'] ?? ''); ?>" class="form-input">

        <label for="modulos_activos">Módulos Activos:</label>
        <select id="modulos_activos" name="modulos_activos[]" multiple class="form-select">
            <?php
            $modulos = ['empresa', 'sistema', 'usuarios', 'puntos_de_venta', 'avanzada', 'seguridad'];
            $modulos_activos = json_decode($config['modulos_activos'] ?? json_encode([]), true);
            foreach ($modulos as $modulo) {
                $selected = isset($modulos_activos[$modulo]) ? 'selected' : '';
                echo "<option value=\"$modulo\" $selected>" . ucfirst($modulo) . "</option>";
            }
            ?>
        </select>

        <button type="submit" class="form-button">Actualizar Configuración</button><br>
    </form>
</div>