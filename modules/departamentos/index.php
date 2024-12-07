<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Clase para manejar respuestas JSON
class ApiResponse {
    public static function send($status, $message, $data = null) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// Funciones para manejar departamentos
function getDepartamentos($user_id) {
    global $pdo;
    $query = "SELECT * FROM departamentos WHERE user_id = ? ORDER BY nombre ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCategorias($user_id) {
    global $pdo;
    $query = "SELECT * FROM categorias WHERE user_id = ? ORDER BY nombre ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addDepartamento($user_id, $data) {
    global $pdo;
    try {
        $query = "INSERT INTO departamentos (user_id, nombre, descripcion, estado, fecha_creacion) 
                 VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $user_id,
            $data['nombre'],
            $data['descripcion'],
            $data['estado'] ?? 'activo'
        ]);

        return [
            'status' => $result,
            'message' => $result ? 'Departamento creado exitosamente' : 'Error al crear el departamento'
        ];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function addCategoria($user_id, $data) {
    global $pdo;
    try {
        $query = "INSERT INTO categorias (user_id, nombre, descripcion, estado, fecha_creacion) 
                 VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $user_id,
            $data['nombre'],
            $data['descripcion'],
            $data['estado'] ?? 'activo'
        ]);

        return [
            'status' => $result,
            'message' => $result ? 'Categoría creada exitosamente' : 'Error al crear la categoría'
        ];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function updateDepartamento($user_id, $id, $data) {
    global $pdo;
    try {
        $query = "UPDATE departamentos 
                 SET nombre = ?, descripcion = ?, estado = ? 
                 WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $data['nombre'],
            $data['descripcion'],
            $data['estado'],
            $id,
            $user_id
        ]);

        return [
            'status' => $result,
            'message' => $result ? 'Departamento actualizado exitosamente' : 'Error al actualizar el departamento'
        ];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function updateCategoria($user_id, $id, $data) {
    global $pdo;
    try {
        $query = "UPDATE categorias 
                 SET nombre = ?, descripcion = ?, estado = ? 
                 WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $data['nombre'],
            $data['descripcion'],
            $data['estado'],
            $id,
            $user_id
        ]);

        return [
            'status' => $result,
            'message' => $result ? 'Categoría actualizada exitosamente' : 'Error al actualizar la categoría'
        ];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function deleteDepartamento($user_id, $id) {
    global $pdo;
    try {
        $query = "DELETE FROM departamentos WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$id, $user_id]);

        return [
            'status' => $result,
            'message' => $result ? 'Departamento eliminado exitosamente' : 'Error al eliminar el departamento'
        ];
    } catch (PDOException $e) {
        return [
            'status' => false,
            'message' => 'No se puede eliminar el departamento porque tiene elementos asociados'
        ];
    }
}

function deleteCategoria($user_id, $id) {
    global $pdo;
    try {
        $query = "DELETE FROM categorias WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$id, $user_id]);

        return [
            'status' => $result,
            'message' => $result ? 'Categoría eliminada exitosamente' : 'Error al eliminar la categoría'
        ];
    } catch (PDOException $e) {
        return [
            'status' => false,
            'message' => 'No se puede eliminar la categoría porque tiene elementos asociados'
        ];
    }
}

// Manejador de peticiones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    $tipo = $_POST['tipo'] ?? '';

    switch ($action) {
        case 'add':
            if ($tipo === 'departamento') {
                $result = addDepartamento($user_id, $_POST);
            } else {
                $result = addCategoria($user_id, $_POST);
            }
            ApiResponse::send($result['status'], $result['message']);
            break;

        case 'update':
            $id = (int)$_POST['id'];
            if ($tipo === 'departamento') {
                $result = updateDepartamento($user_id, $id, $_POST);
            } else {
                $result = updateCategoria($user_id, $id, $_POST);
            }
            ApiResponse::send($result['status'], $result['message']);
            break;

        case 'delete':
            $id = (int)$_POST['id'];
            if ($tipo === 'departamento') {
                $result = deleteDepartamento($user_id, $id);
            } else {
                $result = deleteCategoria($user_id, $id);
            }
            ApiResponse::send($result['status'], $result['message']);
            break;

        default:
            ApiResponse::send(false, 'Acción no válida');
    }
}

$departamentos = getDepartamentos($user_id);
$categorias = getCategorias($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departamentos y Categorías | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="w-full lg:w-3/4 px-4">
                <!-- Tabs -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button onclick="switchTab('departamentos')"
                                    class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                    data-tab="departamentos">
                                Departamentos
                            </button>
                            <button onclick="switchTab('categorias')"
                                    class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                    data-tab="categorias">
                                Categorías
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Contenido de Departamentos -->
                <div id="departamentos-content" class="tab-content">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">Gestión de Departamentos</h2>
                            <button onclick="showModal('departamento')" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>
                                Nuevo Departamento
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Nombre
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Descripción
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($departamentos as $departamento): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($departamento['nombre']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($departamento['descripcion']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                       <?= $departamento['estado'] === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= ucfirst(htmlspecialchars($departamento['estado'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick='editItem("departamento", <?= json_encode($departamento) ?>)'
                                                    class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteItem('departamento', <?= $departamento['id'] ?>)"
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Contenido de Categorías -->
                <div id="categorias-content" class="tab-content hidden">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">Gestión de Categorías</h2>
                            <button onclick="showModal('categoria')" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>
                                Nueva Categoría
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Nombre
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Descripción
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($categorias as $categoria): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($categoria['nombre']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($categoria['descripcion']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                       <?= $categoria['estado'] === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= ucfirst(htmlspecialchars($categoria['estado'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick='editItem("categoria", <?= json_encode($categoria) ?>)'
                                                    class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteItem('categoria', <?= $categoria['id'] ?>)"
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Departamentos y Categorías -->
    <div id="formModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex flex-col">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-xl font-bold text-gray-900" id="modalTitle">Nuevo Departamento</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="itemForm" class="space-y-6 py-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="tipo" value="departamento">
                    <input type="hidden" name="id" value="">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre *
                        </label>
                        <input type="text" name="nombre" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Descripción
                        </label>
                        <textarea name="descripcion" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Estado
                        </label>
                        <select name="estado"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-4 pt-4 border-t">
                        <button type="button" onclick="closeModal()"
                                class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('border-blue-500', 'text-blue-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });
        
        document.getElementById(`${tabName}-content`).classList.remove('hidden');
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('border-blue-500', 'text-blue-600');
    }

    function showModal(tipo) {
        const modal = document.getElementById('formModal');
        const form = document.getElementById('itemForm');
        
        // Resetear el formulario
        form.reset();
        form.action.value = 'add';
        form.tipo.value = tipo;
        form.id.value = '';
        
        // Actualizar título
        document.getElementById('modalTitle').textContent = `Nuevo ${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`;
        
        modal.classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('formModal').classList.add('hidden');
    }

    function editItem(tipo, item) {
        const modal = document.getElementById('formModal');
        const form = document.getElementById('itemForm');
        
        form.action.value = 'update';
        form.tipo.value = tipo;
        form.id.value = item.id;
        form.nombre.value = item.nombre;
        form.descripcion.value = item.descripcion;
        form.estado.value = item.estado;
        
        document.getElementById('modalTitle').textContent = `Editar ${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`;
        
        modal.classList.remove('hidden');
    }

    async function deleteItem(tipo, id) {
        const result = await Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('tipo', tipo);
            formData.append('id', id);

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();
                
                if (result.status) {
                    Swal.fire({
                        title: '¡Eliminado!',
                        text: result.message,
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: result.message,
                        icon: 'error'
                    });
                }
            } catch (error) {
                Swal.fire({
                    title: 'Error',
                    text: 'Error al procesar la solicitud',
                    icon: 'error'
                });
            }
        }
    }

    document.getElementById('itemForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            
            if (result.status) {
                Swal.fire({
                    title: '¡Éxito!',
                    text: result.message,
                    icon: 'success'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: result.message,
                    icon: 'error'
                });
            }
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: 'Error al procesar la solicitud',
                icon: 'error'
            });
        }
    });

    // Inicializar la primera pestaña
    switchTab('departamentos');
    </script>
</body>
</html>
