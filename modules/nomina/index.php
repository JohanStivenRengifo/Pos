<?php
// Verificar la sesión
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
// Incluir la conexión
require_once '../../config/db.php';
// Incluir el header
require_once '../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nomina | VendEasy</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex">
        <?php require_once '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 p-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">Gestión de Nómina</h1>
                    <button onclick="mostrarModalNomina()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>Nueva Nómina
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empleado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Salario Base</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deducciones</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-nominas">
                            <!-- Aquí se cargarán las nóminas dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para nueva nómina -->
    <div id="modal-nomina" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Nueva Nómina</h3>
                <form id="form-nomina">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Empleado</label>
                        <select id="empleado_id" name="empleado_id" class="w-full px-3 py-2 border rounded-lg" required>
                            <!-- Se cargarán los empleados dinámicamente -->
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Período</label>
                        <input type="month" id="periodo" name="periodo" class="w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Salario Base</label>
                        <input type="number" id="salario_base" name="salario_base" class="w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Deducciones</label>
                        <input type="number" id="deducciones" name="deducciones" class="w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="cerrarModalNomina()" class="mr-2 px-4 py-2 bg-gray-200 text-gray-800 rounded">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function mostrarModalNomina() {
            document.getElementById('modal-nomina').classList.remove('hidden');
        }

        function cerrarModalNomina() {
            document.getElementById('modal-nomina').classList.add('hidden');
        }

        // Cargar nóminas al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            cargarNominas();
            cargarEmpleados();
        });

        function cargarNominas() {
            fetch('../../ajax/nomina/obtener_nominas.php')
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(err.error || 'Error en el servidor');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    const tabla = document.getElementById('tabla-nominas');
                    tabla.innerHTML = '';
                    
                    if (data.length === 0) {
                        tabla.innerHTML = `
                            <tr class="border-b">
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No hay nóminas registradas
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    data.forEach(nomina => {
                        tabla.innerHTML += `
                            <tr class="border-b">
                                <td class="px-6 py-4 whitespace-nowrap">${nomina.nombre_empleado}</td>
                                <td class="px-6 py-4 whitespace-nowrap">${nomina.periodo}</td>
                                <td class="px-6 py-4 whitespace-nowrap">$${nomina.salario_base}</td>
                                <td class="px-6 py-4 whitespace-nowrap">$${nomina.deducciones}</td>
                                <td class="px-6 py-4 whitespace-nowrap">$${nomina.total}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button onclick="window.open('../../ajax/nomina/imprimir.php?id=${nomina.id}', '_blank')" 
                                            class="text-blue-500 hover:text-blue-700">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    const tabla = document.getElementById('tabla-nominas');
                    tabla.innerHTML = `
                        <tr class="border-b">
                            <td colspan="6" class="px-6 py-4 text-center text-red-500">
                                Error al cargar las nóminas: ${error.message}
                            </td>
                        </tr>
                    `;
                });
        }

        function cargarEmpleados() {
            fetch('../../ajax/empleados/obtener_empleados.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la red');
                    }
                    return response.json();
                })
                .then(response => {
                    if (response.error) {
                        throw new Error(response.error);
                    }
                    
                    const empleados = response.data || [];
                    const select = document.getElementById('empleado_id');
                    select.innerHTML = '<option value="">Seleccione un empleado</option>';
                    
                    if (empleados.length === 0) {
                        select.innerHTML += '<option value="" disabled>No hay empleados disponibles</option>';
                        console.log('No se encontraron empleados');
                        return;
                    }
                    
                    empleados.forEach(empleado => {
                        select.innerHTML += `
                            <option value="${empleado.id}">
                                ${empleado.nombre} - ${empleado.rol.charAt(0).toUpperCase() + empleado.rol.slice(1)}
                            </option>
                        `;
                    });
                    
                    console.log(`Se cargaron ${empleados.length} empleados`);
                })
                .catch(error => {
                    console.error('Error al cargar empleados:', error);
                    const select = document.getElementById('empleado_id');
                    select.innerHTML = '<option value="">Error al cargar empleados</option>';
                    alert('Error al cargar los empleados: ' + error.message);
                });
        }

        document.getElementById('form-nomina').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../../ajax/nomina/guardar_nomina.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.error || 'Error al guardar la nómina');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Nómina guardada correctamente');
                    cerrarModalNomina();
                    cargarNominas();
                    this.reset();
                } else {
                    throw new Error(data.error || 'Error al guardar la nómina');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message);
            });
        });

        function generarPDF(id) {
            window.open(`../../ajax/nomina/imprimir.php?id=${id}`, '_blank');
        }
    </script>
</body>
</html>
