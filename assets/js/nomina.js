document.addEventListener('DOMContentLoaded', function() {
    // Cargar datos iniciales
    cargarNominas();
    cargarEmpleados();

    // Event listeners
    document.getElementById('guardarNomina').addEventListener('click', guardarNomina);
});

function cargarNominas() {
    fetch('../../ajax/nomina/obtener_nominas.php')
        .then(response => response.json())
        .then(data => {
            const tabla = document.getElementById('tablaNomina');
            tabla.innerHTML = '';
            
            data.forEach(nomina => {
                tabla.innerHTML += `
                    <tr>
                        <td>${nomina.id}</td>
                        <td>${nomina.empleado}</td>
                        <td>${nomina.periodo}</td>
                        <td>${nomina.salario_base}</td>
                        <td>${nomina.deducciones}</td>
                        <td>${nomina.bonificaciones}</td>
                        <td>${nomina.total}</td>
                        <td>${nomina.fecha_pago}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editarNomina(${nomina.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="eliminarNomina(${nomina.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        })
        .catch(error => console.error('Error:', error));
}

function cargarEmpleados() {
    fetch('../../ajax/empleados/obtener_empleados.php')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('empleado_id');
            select.innerHTML = '<option value="">Seleccione un empleado</option>';
            
            data.forEach(empleado => {
                select.innerHTML += `
                    <option value="${empleado.id}">${empleado.nombre} ${empleado.apellido}</option>
                `;
            });
        })
        .catch(error => console.error('Error:', error));
}

function guardarNomina() {
    const formData = new FormData(document.getElementById('formularioNomina'));
    const nomina_id = document.getElementById('nomina_id').value;
    const url = nomina_id ? 
        '../../ajax/nomina/actualizar_nomina.php' : 
        '../../ajax/nomina/crear_nomina.php';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            $('#modalNomina').modal('hide');
            cargarNominas();
            document.getElementById('formularioNomina').reset();
        } else {
            alert('Error al guardar la nómina');
        }
    })
    .catch(error => console.error('Error:', error));
}

function editarNomina(id) {
    fetch(`../../ajax/nomina/obtener_nomina.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('nomina_id').value = data.id;
            document.getElementById('empleado_id').value = data.empleado_id;
            document.getElementById('periodo').value = data.periodo;
            document.getElementById('salario_base').value = data.salario_base;
            document.getElementById('deducciones').value = data.deducciones;
            document.getElementById('bonificaciones').value = data.bonificaciones;
            document.getElementById('fecha_pago').value = data.fecha_pago;
            
            $('#modalNomina').modal('show');
        })
        .catch(error => console.error('Error:', error));
}

function eliminarNomina(id) {
    if (confirm('¿Está seguro de que desea eliminar esta nómina?')) {
        fetch(`../../ajax/nomina/eliminar_nomina.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cargarNominas();
                } else {
                    alert('Error al eliminar la nómina');
                }
            })
            .catch(error => console.error('Error:', error));
    }
} 