<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: ../../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $venta_id = (int)$_GET['id'];

    // Obtener los detalles de la venta
    $stmt = $pdo->prepare("SELECT * FROM ventas WHERE id = ? AND user_id = ?");
    $stmt->execute([$venta_id, $user_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($venta) {
        // Formulario para editar la venta
        ?>
        <h1>Modificar Venta</h1>
        <form id="edit-form">
            <input type="hidden" name="id" value="<?= htmlspecialchars($venta['id']); ?>">
            <label for="total">Total:</label>
            <input type="text" name="total" id="total" value="<?= htmlspecialchars($venta['total']); ?>" required>
            <button type="submit">Guardar Cambios</button>
        </form>

        <script>
        $(document).ready(function() {
            $('#edit-form').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'guardar_edicion.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        alert(response);
                        window.location.href = 'index.php'; // Redirigir a la lista de ventas
                    },
                    error: function() {
                        alert("Error al guardar la edición.");
                    }
                });
            });
        });
        </script>
        <?php
    } else {
        echo "<p>No se encontró la venta.</p>";
    }
} else {
    echo "<p>ID de venta no especificado.</p>";
}
?>
