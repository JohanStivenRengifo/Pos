<?php
session_start();
require_once '../../../config/db.php';

// Definir constantes para el manejo de logos
define('LOGO_PATH', '../../../assets/img/logos/');
define('DEFAULT_LOGO', '../../../assets/img/logos/default-logo.jpg');

// Verificar y crear el directorio de logos si no existe
if (!file_exists(LOGO_PATH)) {
    mkdir(LOGO_PATH, 0777, true);
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Obtener información de la empresa
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$_SESSION['empresa_id']]);
$empresa = $stmt->fetch();

// Procesar el formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            UPDATE empresas SET 
                nombre_empresa = ?,
                nit = ?,
                regimen_fiscal = ?,
                direccion = ?,
                telefono = ?,
                correo_contacto = ?,
                tipo_persona = ?,
                tipo_identificacion = ?,
                responsabilidad_tributaria = ?,
                moneda = ?,
                departamento = ?,
                municipio = ?,
                pais = ?,
                prefijo_factura = ?,
                numero_inicial = ?,
                numero_final = ?,
                primer_nombre = ?,
                segundo_nombre = ?,
                apellidos = ?,
                tipo_nacionalidad = ?,
                codigo_postal = ?,
                plan_suscripcion = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $_POST['nombre_empresa'],
            $_POST['nit'],
            $_POST['regimen_fiscal'],
            $_POST['direccion'],
            $_POST['telefono'],
            $_POST['correo_contacto'],
            $_POST['tipo_persona'],
            $_POST['tipo_identificacion'],
            $_POST['responsabilidad_tributaria'],
            $_POST['moneda'],
            $_POST['departamento'],
            $_POST['municipio'],
            $_POST['pais'],
            $_POST['prefijo_factura'],
            $_POST['numero_inicial'],
            $_POST['numero_final'],
            $_POST['primer_nombre'],
            $_POST['segundo_nombre'],
            $_POST['apellidos'],
            $_POST['tipo_nacionalidad'],
            $_POST['codigo_postal'],
            $_POST['plan_suscripcion'],
            $_SESSION['empresa_id']
        ]);

        // Procesar logo si se subió uno nuevo
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($_FILES['logo']['type'], $allowed_types)) {
                $_SESSION['error_message'] = "Solo se permiten archivos JPG y PNG.";
            } elseif ($_FILES['logo']['size'] > $max_size) {
                $_SESSION['error_message'] = "El archivo es demasiado grande. El tamaño máximo permitido es 2MB.";
            } else {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                
                // Eliminar logo anterior si existe
                if (!empty($empresa['logo'])) {
                    $old_logo = LOGO_PATH . $empresa['logo'];
                    if (file_exists($old_logo)) {
                        unlink($old_logo);
                    }
                }
                
                // Generar nombre único para el logo
                $new_filename = uniqid() . '.' . $ext;
                $target_file = LOGO_PATH . $new_filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                    // Actualizar la base de datos con el nuevo nombre del logo
                    $stmt = $pdo->prepare("UPDATE empresas SET logo = ? WHERE id = ?");
                    $stmt->execute([$new_filename, $_SESSION['empresa_id']]);
                } else {
                    $_SESSION['error_message'] = "Error al subir el archivo. Por favor, intente nuevamente.";
                }
            }
        }

        $_SESSION['success_message'] = "Información de la empresa actualizada correctamente";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error al actualizar la información: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Empresa | Numercia</title>
    <link rel="icon" href="../../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../../../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
            <?php include '../../../includes/sidebar.php'; ?>

            <!-- Contenido Principal -->
            <div class="w-full lg:w-3/4 px-4">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-building mr-2"></i>Configuración de Empresa
                    </h1>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                            <?php 
                            echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                            <?php 
                            echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Formulario de Empresa -->
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <!-- Logo de la Empresa -->
                        <div class="bg-gray-50 p-6 rounded-lg">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Logo de la Empresa</h2>
                            <div class="flex items-center space-x-6">
                                <div class="w-40 h-40 relative">
                                    <?php
                                    $logo_url = DEFAULT_LOGO;
                                    if (!empty($empresa['logo'])) {
                                        $logo_file = LOGO_PATH . $empresa['logo'];
                                        if (file_exists($logo_file)) {
                                            $logo_url = $logo_file;
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($logo_url); ?>" 
                                         alt="Logo de la empresa" 
                                         class="w-full h-full object-contain border rounded-lg"
                                         id="preview-logo">
                                    <label for="logo" class="absolute bottom-0 right-0 bg-indigo-600 text-white rounded-full p-2 hover:bg-indigo-700 cursor-pointer">
                                        <i class="fas fa-camera"></i>
                                        <input type="file" id="logo" name="logo" class="hidden" accept="image/jpeg,image/png">
                                    </label>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <p>Formatos permitidos: JPG, PNG</p>
                                    <p>Tamaño máximo: 2MB</p>
                                </div>
                            </div>
                        </div>

                        <!-- Información Básica -->
                        <div class="bg-gray-50 p-6 rounded-lg">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Información Básica</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nombre de la Empresa</label>
                                    <input type="text" name="nombre_empresa" value="<?php echo htmlspecialchars($empresa['nombre_empresa']); ?>" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">NIT/RUT</label>
                                    <input type="text" name="nit" value="<?php echo htmlspecialchars($empresa['nit']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                                    <input type="tel" name="telefono" value="<?php echo htmlspecialchars($empresa['telefono']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" name="correo_contacto" value="<?php echo htmlspecialchars($empresa['correo_contacto']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                </div>
                            </div>
                        </div>

                        <!-- Dirección -->
                        <div class="bg-gray-50 p-6 rounded-lg">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Dirección</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Dirección</label>
                                    <input type="text" name="direccion" value="<?php echo htmlspecialchars($empresa['direccion']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Departamento</label>
                                    <input type="text" name="departamento" value="<?php echo htmlspecialchars($empresa['departamento']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Municipio</label>
                                    <input type="text" name="municipio" value="<?php echo htmlspecialchars($empresa['municipio']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">País</label>
                                    <select name="pais" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="Colombia" <?php echo $empresa['pais'] === 'Colombia' ? 'selected' : ''; ?>>Colombia</option>
                                        <option value="México" <?php echo $empresa['pais'] === 'México' ? 'selected' : ''; ?>>México</option>
                                        <option value="Perú" <?php echo $empresa['pais'] === 'Perú' ? 'selected' : ''; ?>>Perú</option>
                                        <option value="Chile" <?php echo $empresa['pais'] === 'Chile' ? 'selected' : ''; ?>>Chile</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Configuración Fiscal -->
                        <div class="bg-gray-50 p-6 rounded-lg">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Configuración Fiscal</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Régimen Fiscal</label>
                                    <select name="regimen_fiscal" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="Régimen Común" <?php echo $empresa['regimen_fiscal'] === 'Régimen Común' ? 'selected' : ''; ?>>Régimen Común</option>
                                        <option value="Régimen Simplificado" <?php echo $empresa['regimen_fiscal'] === 'Régimen Simplificado' ? 'selected' : ''; ?>>Régimen Simplificado</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tipo de Persona</label>
                                    <select name="tipo_persona" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="natural" <?php echo $empresa['tipo_persona'] === 'natural' ? 'selected' : ''; ?>>Persona Natural</option>
                                        <option value="juridica" <?php echo $empresa['tipo_persona'] === 'juridica' ? 'selected' : ''; ?>>Persona Jurídica</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tipo de Identificación</label>
                                    <select name="tipo_identificacion" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="NIT" <?php echo $empresa['tipo_identificacion'] === 'NIT' ? 'selected' : ''; ?>>NIT</option>
                                        <option value="CC" <?php echo $empresa['tipo_identificacion'] === 'CC' ? 'selected' : ''; ?>>Cédula de Ciudadanía</option>
                                        <option value="CE" <?php echo $empresa['tipo_identificacion'] === 'CE' ? 'selected' : ''; ?>>Cédula de Extranjería</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Moneda Principal</label>
                                    <select name="moneda" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="COP" <?php echo $empresa['moneda'] === 'COP' ? 'selected' : ''; ?>>COP - Peso Colombiano</option>
                                        <option value="USD" <?php echo $empresa['moneda'] === 'USD' ? 'selected' : ''; ?>>USD - Dólar Americano</option>
                                        <option value="MXN" <?php echo $empresa['moneda'] === 'MXN' ? 'selected' : ''; ?>>MXN - Peso Mexicano</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Responsabilidad Tributaria</label>
                                    <select name="responsabilidad_tributaria" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="No responsable de IVA" <?php echo $empresa['responsabilidad_tributaria'] === 'No responsable de IVA' ? 'selected' : ''; ?>>No responsable de IVA</option>
                                        <option value="Responsable de IVA" <?php echo $empresa['responsabilidad_tributaria'] === 'Responsable de IVA' ? 'selected' : ''; ?>>Responsable de IVA</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Información Adicional -->
                        <div class="bg-gray-50 p-6 rounded-lg mt-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Información Adicional</h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Primer Nombre</label>
                                    <input type="text" name="primer_nombre" value="<?php echo htmlspecialchars($empresa['primer_nombre'] ?? ''); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Segundo Nombre</label>
                                    <input type="text" name="segundo_nombre" value="<?php echo htmlspecialchars($empresa['segundo_nombre'] ?? ''); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Apellidos</label>
                                    <input type="text" name="apellidos" value="<?php echo htmlspecialchars($empresa['apellidos'] ?? ''); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Prefijo Factura</label>
                                    <input type="text" name="prefijo_factura" value="<?php echo htmlspecialchars($empresa['prefijo_factura']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Número Inicial</label>
                                    <input type="number" name="numero_inicial" value="<?php echo htmlspecialchars($empresa['numero_inicial']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Número Final</label>
                                    <input type="number" name="numero_final" value="<?php echo htmlspecialchars($empresa['numero_final']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Código Postal</label>
                                    <input type="text" name="codigo_postal" value="<?php echo htmlspecialchars($empresa['codigo_postal'] ?? ''); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tipo de Nacionalidad</label>
                                    <select name="tipo_nacionalidad" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="nacional" <?php echo ($empresa['tipo_nacionalidad'] ?? '') === 'nacional' ? 'selected' : ''; ?>>Nacional</option>
                                        <option value="extranjero" <?php echo ($empresa['tipo_nacionalidad'] ?? '') === 'extranjero' ? 'selected' : ''; ?>>Extranjero</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Plan de Suscripción</label>
                                    <div class="mt-1 block w-full p-2 bg-gray-50 rounded-md border border-gray-300">
                                        <?php
                                        $planes = [
                                            'basico' => 'Básico',
                                            'profesional' => 'Profesional',
                                            'empresarial' => 'Empresarial'
                                        ];
                                        echo htmlspecialchars($planes[$empresa['plan_suscripcion']] ?? 'Básico');
                                        ?>
                                        <input type="hidden" name="plan_suscripcion" value="<?php echo htmlspecialchars($empresa['plan_suscripcion']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex justify-end space-x-4">
                            <button type="button" onclick="window.location.href='index.php'" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">
                                Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('logo').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            const file = e.target.files[0];
            
            // Verificar el tamaño del archivo (2MB máximo)
            if (file.size > 2 * 1024 * 1024) {
                alert('El archivo es demasiado grande. El tamaño máximo permitido es 2MB.');
                this.value = '';
                return;
            }
            
            // Verificar el tipo de archivo
            if (!file.type.match('image/jpeg') && !file.type.match('image/png')) {
                alert('Solo se permiten archivos JPG y PNG.');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview-logo').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });
    </script>
</body>
</html> 