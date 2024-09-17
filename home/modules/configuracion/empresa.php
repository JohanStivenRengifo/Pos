<?php
require_once '../config/db_connection.php';

// Obtener datos actuales de la empresa
function getCompanyData() {
    $pdo = getPDOConnection();
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    if ($stmt === false) {
        return false;
    }
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Actualizar datos de la empresa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $logo = $_FILES['logo']['name'];

    if ($logo) {
        move_uploaded_file($_FILES['logo']['tmp_name'], '../uploads/' . $logo);
    } else {
        $logo = $_POST['logo_old'];
    }

    $pdo = getPDOConnection();
    $stmt = $pdo->prepare("UPDATE empresa SET nombre = ?, direccion = ?, telefono = ?, email = ?, logo = ? WHERE id = 1");
    $result = $stmt->execute([$nombre, $direccion, $telefono, $email, $logo]);

    if ($result) {
        echo "<p class='success-message'>Datos de la empresa actualizados correctamente.</p>";
    } else {
        echo "<p class='error-message'>Error al actualizar los datos de la empresa.</p>";
    }
}

$empresa = getCompanyData();
if ($empresa === false) {
    echo "<p class='error-message'>Error al obtener los datos de la empresa.</p>";
    $empresa = [
        'nombre' => '',
        'direccion' => '',
        'telefono' => '',
        'email' => '',
        'logo' => ''
    ];
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
.company-form {
    display: flex;
    flex-direction: column;
}

.company-form label {
    font-weight: bold;
    margin-bottom: 5px;
}

.company-form .form-input {
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.company-form .form-button {
    padding: 10px 15px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.company-form .form-button:hover {
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

/* Vista previa del logo */
.logo-preview {
    max-width: 100px;
    margin-top: 10px;
}

</style>

<div class="form-container">
    <h1>Datos de la Empresa</h1>
    <form method="post" enctype="multipart/form-data" class="company-form">
        <label for="nombre">Nombre de la Empresa:</label>
        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($empresa['nombre'] ?? ''); ?>" required class="form-input">

        <label for="direccion">Dirección:</label>
        <input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($empresa['direccion'] ?? ''); ?>" required class="form-input">

        <label for="telefono">Teléfono:</label>
        <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($empresa['telefono'] ?? ''); ?>" required class="form-input">

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($empresa['email'] ?? ''); ?>" required class="form-input">

        <label for="logo">Logo:</label>
        <input type="file" id="logo" name="logo" class="form-input">
        <input type="hidden" name="logo_old" value="<?php echo htmlspecialchars($empresa['logo'] ?? ''); ?>">
        <?php if (!empty($empresa['logo'])): ?>
            <img src="../uploads/<?php echo htmlspecialchars($empresa['logo']); ?>" alt="Logo Actual" class="logo-preview">
        <?php else: ?>
            <p>Ningún archivo seleccionado</p>
        <?php endif; ?>

        <button type="submit" class="form-button">Actualizar</button>
    </form>
</div>