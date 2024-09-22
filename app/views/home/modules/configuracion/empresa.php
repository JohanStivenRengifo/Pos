<?php
// Incluir la conexión a la base de datos
require_once __DIR__ . '/../../../config/database.php';

try {
    $pdo = getPDOConnection(); // Asegúrate de que esta función esté disponible
} catch (Exception $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

// Recuperar el user_id desde la cookie como company_id
$company_id = isset($_COOKIE['user_id']) ? (int)$_COOKIE['user_id'] : null;

// Inicializar la variable para evitar errores si no existe información
$empresa = [
    'company_id' => '',
    'nombre_comercial' => '',
    'tipo_identificacion' => '',
    'identificacion' => '',
    'direccion' => '',
    'telefono' => '',
    'codigo_postal' => '',
    'moneda' => 'COP',
    'sector' => '',
    'numero_empleados' => '',
    'factura_default' => 'standard',
    'logo' => ''
];

// Verificar si hay un ID de empresa para editar
if (isset($_GET['company_id'])) {
    $company_id = (int)$_GET['company_id'];

    // Obtener datos de la empresa
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE company_id = :company_id AND user_id = :user_id");
    $stmt->execute(['company_id' => $company_id, 'user_id' => $company_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    // Asegúrate de que el usuario tenga acceso a esta empresa
    if (!$empresa) {
        die("Empresa no encontrada.");
    }
}

// Guardar datos cuando se envíe el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar y sanitizar los datos del formulario
    $nombre_comercial = htmlspecialchars($_POST['nombre_comercial']);
    $tipo_identificacion = $_POST['tipo_identificacion'];
    $identificacion = htmlspecialchars($_POST['identificacion']);
    $direccion = htmlspecialchars($_POST['direccion']);
    $telefono = htmlspecialchars($_POST['telefono']);
    $codigo_postal = htmlspecialchars($_POST['codigo_postal']);
    $moneda = $_POST['moneda'];
    $sector = $_POST['sector'];
    $numero_empleados = $_POST['numero_empleados'];
    $factura_default = $_POST['factura_default'];

    // Manejar la carga del logo
    $logo = $_POST['logo_old'] ?? ''; // Mantener el logo actual si no se sube uno nuevo
    if (isset($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo = $_FILES['logo']['name'];
        $logo_tmp = $_FILES['logo']['tmp_name'];
        move_uploaded_file($logo_tmp, "../uploads/$logo"); // Guardar el logo en la carpeta correspondiente
    }

    // Si es una edición
    if (isset($_POST['company_id'])) {
        // Actualizar la empresa
        $sql = "UPDATE empresas 
                SET nombre_comercial = :nombre_comercial, tipo_identificacion = :tipo_identificacion, 
                    identificacion = :identificacion, direccion = :direccion, telefono = :telefono, 
                    codigo_postal = :codigo_postal, moneda = :moneda, sector = :sector, 
                    numero_empleados = :numero_empleados, factura_default = :factura_default, 
                    logo = :logo 
                WHERE company_id = :company_id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'nombre_comercial' => $nombre_comercial,
            'tipo_identificacion' => $tipo_identificacion,
            'identificacion' => $identificacion,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'codigo_postal' => $codigo_postal,
            'moneda' => $moneda,
            'sector' => $sector,
            'numero_empleados' => $numero_empleados,
            'factura_default' => $factura_default,
            'logo' => $logo,
            'company_id' => $company_id // Asegurarte de que el company_id sea igual al user_id de la cookie
        ]);
    } else {
        // Insertar una nueva empresa
        $sql = "INSERT INTO empresas (nombre_comercial, tipo_identificacion, identificacion, direccion, telefono, 
                                      codigo_postal, moneda, sector, numero_empleados, factura_default, logo, company_id) 
                VALUES (:nombre_comercial, :tipo_identificacion, :identificacion, :direccion, :telefono, 
                        :codigo_postal, :moneda, :sector, :numero_empleados, :factura_default, :logo, :company_id)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'nombre_comercial' => $nombre_comercial,
            'tipo_identificacion' => $tipo_identificacion,
            'identificacion' => $identificacion,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'codigo_postal' => $codigo_postal,
            'moneda' => $moneda,
            'sector' => $sector,
            'numero_empleados' => $numero_empleados,
            'factura_default' => $factura_default,
            'logo' => $logo,
            'company_id' => $company_id // Asegurarte de que el company_id sea igual al user_id de la cookie
        ]);
    }

    // Redirigir o mostrar mensaje de éxito
    header("Location: empresas.php?success=1");
    exit;
}
?>

<div class="form-container">
    <h1>Configurar Empresa</h1>
    <form action="procesar_empresa.php" method="post" enctype="multipart/form-data" class="company-form">
        <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($empresa['company_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="logo_old" value="<?php echo htmlspecialchars($empresa['logo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <!-- Logo -->
        <label for="logo">Logo:</label>
        <input type="file" id="logo" name="logo" class="form-input">
        <?php if (!empty($empresa['logo'])): ?>
            <img src="../uploads/<?php echo htmlspecialchars($empresa['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Actual" class="logo-preview">
        <?php else: ?>
            <p>No se eligió ningún archivo</p>
        <?php endif; ?>

        <!-- Nombre Comercial -->
        <label for="nombre_comercial">Nombre Comercial:</label>
        <input type="text" id="nombre_comercial" name="nombre_comercial" value="<?php echo htmlspecialchars($empresa['nombre_comercial'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="form-input">

        <!-- Tipo de Identificación -->
        <label for="tipo_identificacion">Tipo de Identificación:</label>
        <select id="tipo_identificacion" name="tipo_identificacion" class="form-input" required>
            <option value="CC" <?php if ($empresa['tipo_identificacion'] == 'CC') echo 'selected'; ?>>Cédula de Ciudadanía</option>
            <option value="NIT" <?php if ($empresa['tipo_identificacion'] == 'NIT') echo 'selected'; ?>>NIT</option>
        </select>

        <!-- Identificación -->
        <label for="identificacion">Número de Identificación:</label>
        <input type="text" id="identificacion" name="identificacion" value="<?php echo htmlspecialchars($empresa['identificacion'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="form-input">

        <!-- Dirección -->
        <label for="direccion">Dirección:</label>
        <input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($empresa['direccion'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="form-input">

        <!-- Teléfono -->
        <label for="telefono">Teléfono:</label>
        <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($empresa['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="form-input">

        <!-- Código Postal -->
        <label for="codigo_postal">Código Postal:</label>
        <input type="text" id="codigo_postal" name="codigo_postal" value="<?php echo htmlspecialchars($empresa['codigo_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="form-input">

        <!-- Moneda -->
        <label for="moneda">Moneda:</label>
        <select id="moneda" name="moneda" class="form-input" required>
            <option value="COP" <?php if ($empresa['moneda'] == 'COP') echo 'selected'; ?>>COP - Peso Colombiano</option>
            <option value="USD" <?php if ($empresa['moneda'] == 'USD') echo 'selected'; ?>>USD - Dólar Americano</option>
        </select>

        <!-- Sector -->
        <label for="sector">Sector:</label>
        <select id="sector" name="sector" class="form-input">
            <option value="comercio" <?php if ($empresa['sector'] == 'comercio') echo 'selected'; ?>>Comercio</option>
            <option value="servicios" <?php if ($empresa['sector'] == 'servicios') echo 'selected'; ?>>Servicios</option>
            <option value="manufactura" <?php if ($empresa['sector'] == 'manufactura') echo 'selected'; ?>>Manufactura</option>
            <option value="tecnologia" <?php if ($empresa['sector'] == 'tecnologia') echo 'selected'; ?>>Tecnología</option>
        </select>

        <!-- Número de Empleados -->
        <label for="numero_empleados">Número de Empleados:</label>
        <select id="numero_empleados" name="numero_empleados" class="form-input" required>
            <option value="1-10" <?php if ($empresa['numero_empleados'] == '1-10') echo 'selected'; ?>>1-10</option>
            <option value="11-50" <?php if ($empresa['numero_empleados'] == '11-50') echo 'selected'; ?>>11-50</option>
            <option value="51-100" <?php if ($empresa['numero_empleados'] == '51-100') echo 'selected'; ?>>51-100</option>
            <option value="101-500" <?php if ($empresa['numero_empleados'] == '101-500') echo 'selected'; ?>>101-500</option>
            <option value="501-1000" <?php if ($empresa['numero_empleados'] == '501-1000') echo 'selected'; ?>>501-1000</option>
            <option value="1000+" <?php if ($empresa['numero_empleados'] == '1000+') echo 'selected'; ?>>Más de 1000</option>
        </select>

        <!-- Factura por Defecto -->
        <label for="factura_default">Factura por Defecto:</label>
        <select id="factura_default" name="factura_default" class="form-input">
            <option value="standard" <?php if ($empresa['factura_default'] == 'standard') echo 'selected'; ?>>Emitir facturas estándar</option>
        </select>

        <!-- Botones -->
        <button type="submit" class="form-button">Guardar</button>
        <button type="button" class="form-button" onclick="window.history.back()">Cancelar</button>
    </form>
</div>