<?php
$pdo = getPDOConnection();

// Verifica si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    die("Acceso no autorizado.");
}

// Obtener reportes (esto dependerá de los reportes que quieras generar)
function getReportData($pdo, $startDate, $endDate) {
    $stmt = $pdo->prepare("SELECT * FROM report_data WHERE date BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Manejar la generación del reporte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $reportData = getReportData($pdo, $startDate, $endDate);

    // Exportar el reporte a CSV
    if (isset($_POST['export_csv'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=reportes.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Nombre', 'Cantidad', 'Fecha', 'Otro Campo']);

        foreach ($reportData as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes</title>
    <link rel="stylesheet" href="reportes.css">
</head>
<body>
    <h1>Generación de Reportes</h1>
    
    <!-- Formulario para generar reportes -->
    <form action="" method="post">
        <label for="start_date">Fecha de Inicio:</label>
        <input type="date" id="start_date" name="start_date" required>
        
        <label for="end_date">Fecha de Fin:</label>
        <input type="date" id="end_date" name="end_date" required>
        
        <input type="submit" name="generate_report" value="Generar Reporte">
    </form>
    
    <?php if (isset($reportData)): ?>
        <h2>Reporte de Datos</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Cantidad</th>
                    <th>Fecha</th>
                    <th>Otro Campo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                        <td><?php echo htmlspecialchars($row['other_field']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Exportar a CSV -->
        <form action="" method="post">
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            <input type="submit" name="export_csv" value="Exportar a CSV">
        </form>
    <?php endif; ?>
</body>
</html>
