<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Obtener categorías y departamentos
$stmt = $pdo->prepare("SELECT id, nombre FROM categorias WHERE estado = 'activo'");
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, nombre FROM departamentos WHERE estado = 'activo'");
$stmt->execute();
$departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Producto | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#1e293b',
                        accent: '#3b82f6'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="max-w-4xl mx-auto">
                <!-- Encabezado -->
                <div class="flex items-center justify-between mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">Crear Nuevo Producto</h1>
                    <a href="index.php" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Volver
                    </a>
                </div>

                <!-- Formulario mejorado -->
                <form id="productoForm" class="bg-white rounded-xl shadow-lg p-8 space-y-8" enctype="multipart/form-data">
                    <!-- Información básica -->
                    <div class="space-y-6">
                        <div class="flex items-center gap-2 pb-2 border-b border-gray-200">
                            <i class="fas fa-info-circle text-blue-500"></i>
                            <h2 class="text-xl font-semibold text-gray-800">Información Básica</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label for="nombre" class="block text-sm font-medium text-gray-700">
                                    Nombre del Producto <span class="text-red-500">*</span>
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-box text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           id="nombre" 
                                           name="nombre" 
                                           required
                                           class="block w-full pl-10 pr-3 py-2.5 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Ej: Smartphone Samsung Galaxy">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="codigo_barras" class="block text-sm font-medium text-gray-700">
                                    Código de Barras <span class="text-red-500">*</span>
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-barcode text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           id="codigo_barras" 
                                           name="codigo_barras" 
                                           required
                                           class="block w-full pl-10 pr-12 py-2.5 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Escanea o ingresa el código">
                                    <button type="button" 
                                            onclick="generarCodigoBarras()"
                                            class="absolute inset-y-0 right-0 px-3 flex items-center bg-gray-50 hover:bg-gray-100 rounded-r-lg border-l transition-colors"
                                            title="Generar código">
                                        <i class="fas fa-magic text-gray-500"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="categoria_id" class="block text-sm font-medium text-gray-700">
                                    Categoría <span class="text-red-500">*</span>
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-tag text-gray-400"></i>
                                    </div>
                                    <select id="categoria_id" 
                                            name="categoria_id" 
                                            required
                                            class="block w-full pl-10 pr-10 py-2.5 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none">
                                        <option value="">Selecciona una categoría</option>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <option value="<?= $categoria['id'] ?>">
                                                <?= htmlspecialchars($categoria['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="departamento_id" class="block text-sm font-medium text-gray-700">
                                    Departamento <span class="text-red-500">*</span>
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-building text-gray-400"></i>
                                    </div>
                                    <select id="departamento_id" 
                                            name="departamento_id" 
                                            required
                                            class="block w-full pl-10 pr-10 py-2.5 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none">
                                        <option value="">Selecciona un departamento</option>
                                        <?php foreach ($departamentos as $departamento): ?>
                                            <option value="<?= $departamento['id'] ?>">
                                                <?= htmlspecialchars($departamento['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="unidad_medida" class="block text-sm font-medium text-gray-700">
                                    Unidad de Medida <span class="text-red-500">*</span>
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-ruler text-gray-400"></i>
                                    </div>
                                    <select id="unidad_medida" 
                                            name="unidad_medida" 
                                            required
                                            class="block w-full pl-10 pr-10 py-2.5 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none">
                                        <option value="">Selecciona una unidad</option>
                                        <option value="UNIDAD">Unidad</option>
                                        <option value="KILOGRAMO">Kilogramo</option>
                                        <option value="LITRO">Litro</option>
                                        <option value="METRO">Metro</option>
                                        <option value="CAJA">Caja</option>
                                        <option value="PAQUETE">Paquete</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="estado" class="block text-sm font-medium text-gray-700">
                                    Estado <span class="text-red-500">*</span>
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-toggle-on text-gray-400"></i>
                                    </div>
                                    <select id="estado" 
                                            name="estado" 
                                            required
                                            class="block w-full pl-10 pr-10 py-2.5 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none">
                                        <option value="activo">Activo</option>
                                        <option value="inactivo">Inactivo</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Precios y Márgenes -->
                    <div class="space-y-6">
                        <div class="flex items-center gap-2 pb-2 border-b border-gray-200">
                            <i class="fas fa-dollar-sign text-green-500"></i>
                            <h2 class="text-xl font-semibold text-gray-800">Precios y Márgenes</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div class="space-y-2">
                                <label for="precio_costo" class="block text-sm font-medium text-gray-700">
                                    Precio de Costo <span class="text-red-500">*</span>
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500">$</span>
                                    </div>
                                    <input type="number" 
                                           id="precio_costo" 
                                           name="precio_costo" 
                                           required
                                           step="0.01"
                                           min="0"
                                           onchange="calcularMargen()"
                                           class="block w-full pl-8 pr-3 py-2.5 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="0.00">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="precio_venta" class="block text-sm font-medium text-gray-700">
                                    Precio de Venta <span class="text-red-500">*</span>
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500">$</span>
                                    </div>
                                    <input type="number" 
                                           id="precio_venta" 
                                           name="precio_venta" 
                                           required
                                           step="0.01"
                                           min="0"
                                           onchange="calcularMargen()"
                                           class="block w-full pl-8 pr-3 py-2.5 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="0.00">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="margen_ganancia" class="block text-sm font-medium text-gray-700">
                                    Margen de Ganancia (%) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-percentage text-gray-400"></i>
                                    </div>
                                    <input type="number" 
                                           id="margen_ganancia" 
                                           name="margen_ganancia" 
                                           required
                                           step="0.01"
                                           min="0"
                                           readonly
                                           class="block w-full pl-10 pr-3 py-2.5 rounded-lg bg-gray-50 border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="0">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="impuesto" class="block text-sm font-medium text-gray-700">
                                    Impuesto (%) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-receipt text-gray-400"></i>
                                    </div>
                                    <input type="number" 
                                           id="impuesto" 
                                           name="impuesto" 
                                           required
                                           step="0.01"
                                           min="0"
                                           value="18"
                                           onchange="calcularMargen()"
                                           class="block w-full pl-10 pr-3 py-2.5 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="18">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Imágenes -->
                    <div class="space-y-6">
                        <div class="flex items-center gap-2 pb-2 border-b border-gray-200">
                            <i class="fas fa-images text-purple-500"></i>
                            <h2 class="text-xl font-semibold text-gray-800">Imágenes del Producto</h2>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-center justify-center w-full">
                                <label for="imagenes" 
                                       class="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6 px-4 text-center">
                                        <i class="fas fa-cloud-upload-alt text-4xl text-blue-500 mb-4"></i>
                                        <p class="mb-2 text-sm text-gray-700">
                                            <span class="font-semibold">Haz clic para subir</span> o arrastra y suelta
                                        </p>
                                        <p class="text-xs text-gray-500">PNG, JPG o JPEG (MAX. 800x400px)</p>
                                    </div>
                                    <input id="imagenes" 
                                           name="imagenes[]" 
                                           type="file" 
                                           class="hidden" 
                                           multiple 
                                           accept="image/*">
                                </label>
                            </div>
                            <div id="preview" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div class="space-y-6">
                        <div class="flex items-center gap-2 pb-2 border-b border-gray-200">
                            <i class="fas fa-align-left text-indigo-500"></i>
                            <h2 class="text-xl font-semibold text-gray-800">Descripción</h2>
                        </div>
                        <div class="space-y-2">
                            <label for="descripcion" class="block text-sm font-medium text-gray-700">
                                Descripción del Producto
                            </label>
                            <textarea id="descripcion" 
                                      name="descripcion" 
                                      rows="4" 
                                      class="block w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                                      placeholder="Describe las características del producto..."></textarea>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                        <button type="button" 
                                onclick="window.location.href='index.php'"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </button>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Guardar Producto
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Función para generar código de barras aleatorio
        function generarCodigoBarras() {
            const codigo = Math.floor(Math.random() * 9000000000000) + 1000000000000;
            document.getElementById('codigo_barras').value = codigo;
        }

        // Preview de imágenes
        document.getElementById('imagenes').addEventListener('change', function(e) {
            const preview = document.getElementById('preview');
            const files = Array.from(e.target.files);
            
            // Validar número máximo de archivos
            if (files.length > 10) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Solo puedes subir hasta 10 imágenes'
                });
                this.value = '';
                return;
            }

            // Limpiar preview
            preview.innerHTML = '';
            
            files.forEach((file, index) => {
                // Validar tipo de archivo
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Tipo de archivo no permitido',
                        text: `${file.name} no es un tipo de imagen válido`
                    });
                    return;
                }

                // Validar tamaño
                if (file.size > 25 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Archivo muy grande',
                        text: `${file.name} excede el tamaño máximo de 25MB`
                    });
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'relative group';
                    div.innerHTML = `
                        <div class="aspect-w-1 aspect-h-1 w-full overflow-hidden rounded-lg bg-gray-200">
                            <img src="${e.target.result}" class="h-32 w-full object-cover">
                            <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button type="button" 
                                        class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-2 hover:bg-red-600 focus:outline-none"
                                        onclick="removeImage(this)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                            ${index === 0 ? '<span class="absolute top-2 left-2 px-2 py-1 bg-blue-500 text-white text-xs rounded-md">Principal</span>' : ''}
                            <span class="absolute bottom-2 right-2 px-2 py-1 bg-gray-800 bg-opacity-75 text-white text-xs rounded-md">
                                ${(file.size / (1024 * 1024)).toFixed(2)} MB
                            </span>
                        </div>
                    `;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        });

        // Función para remover imagen
        function removeImage(button) {
            const container = button.closest('.relative');
            container.remove();
            
            // Actualizar etiquetas de imagen principal
            const previews = document.querySelectorAll('#preview .relative');
            if (previews.length > 0) {
                // Remover todas las etiquetas de principal
                document.querySelectorAll('.bg-blue-500.absolute').forEach(el => el.remove());
                
                // Agregar etiqueta de principal a la primera imagen
                const firstPreview = previews[0];
                const span = document.createElement('span');
                span.className = 'absolute top-2 left-2 px-2 py-1 bg-blue-500 text-white text-xs rounded-md';
                span.textContent = 'Principal';
                firstPreview.querySelector('.aspect-w-1').appendChild(span);
            }
        }

        // Manejo del formulario
        document.getElementById('productoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            Swal.fire({
                title: 'Guardando producto...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'procesar_producto.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.status) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: 'Producto guardado correctamente',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                window.location.href = 'index.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Hubo un error al guardar el producto'
                            });
                        }
                    } catch (e) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Hubo un error al procesar la respuesta'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un error al enviar la solicitud'
                    });
                }
            });
        });

        // Función para calcular el margen de ganancia
        function calcularMargen() {
            const precioCosto = parseFloat(document.getElementById('precio_costo').value) || 0;
            const precioVenta = parseFloat(document.getElementById('precio_venta').value) || 0;
            const impuesto = parseFloat(document.getElementById('impuesto').value) || 0;

            if (precioCosto > 0 && precioVenta > 0) {
                // Descontar el impuesto del precio de venta
                const precioSinImpuesto = precioVenta / (1 + (impuesto / 100));
                
                // Calcular el margen como porcentaje
                const margen = ((precioSinImpuesto - precioCosto) / precioCosto) * 100;
                
                document.getElementById('margen_ganancia').value = margen.toFixed(2);
            } else {
                document.getElementById('margen_ganancia').value = '0.00';
            }
        }

        // Agregar event listeners para los campos que afectan el cálculo
        ['precio_costo', 'precio_venta', 'impuesto'].forEach(id => {
            document.getElementById(id).addEventListener('input', calcularMargen);
        });

        // Validar que el precio de venta no sea menor al precio de costo
        document.getElementById('precio_venta').addEventListener('change', function() {
            const precioCosto = parseFloat(document.getElementById('precio_costo').value) || 0;
            const precioVenta = parseFloat(this.value) || 0;
            const impuesto = parseFloat(document.getElementById('impuesto').value) || 0;
            
            // Calcular precio de venta mínimo (costo + impuesto)
            const precioMinimo = precioCosto * (1 + (impuesto / 100));

            if (precioVenta < precioMinimo) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Advertencia',
                    text: 'El precio de venta no puede ser menor al precio de costo más impuestos',
                    confirmButtonText: 'Entendido'
                });
                this.value = precioMinimo.toFixed(2);
                calcularMargen();
            }
        });

        // Validar el formulario antes de enviar
        document.getElementById('productoForm').addEventListener('submit', function(e) {
            const precioCosto = parseFloat(document.getElementById('precio_costo').value) || 0;
            const precioVenta = parseFloat(document.getElementById('precio_venta').value) || 0;
            const margenGanancia = parseFloat(document.getElementById('margen_ganancia').value) || 0;

            if (precioVenta <= precioCosto) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'El precio de venta debe ser mayor al precio de costo',
                    confirmButtonText: 'Entendido'
                });
                return false;
            }

            if (margenGanancia <= 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'El margen de ganancia debe ser mayor a 0',
                    confirmButtonText: 'Entendido'
                });
                return false;
            }
        });
    </script>
</body>
</html>