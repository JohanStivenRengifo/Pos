<?php
$pdo = getPDOConnection();

// Verifica si el usuario está autenticado 
if (!isset($_SESSION['user_id'])) {
    die("Acceso no autorizado.");
}

// Obtener usuarios con paginación y filtrado
function getUsersAdvanced($pdo, $roleFilter = '', $searchQuery = '', $offset = 0, $limit = 10) {
    $sql = "SELECT * FROM users WHERE 1";
    $params = [];

    if ($roleFilter) {
        $sql .= " AND role = ?";
        $params[] = $roleFilter;
    }
    if ($searchQuery) {
        $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $params = array_merge($params, ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"]);
    }
    $sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Contar total de usuarios
function getUserCount($pdo, $roleFilter = '', $searchQuery = '') {
    $sql = "SELECT COUNT(*) FROM users WHERE 1";
    $params = [];

    if ($roleFilter) {
        $sql .= " AND role = ?";
        $params[] = $roleFilter;
    }
    if ($searchQuery) {
        $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $params = array_merge($params, ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"]);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

// Exportar a CSV
function exportToCSV($pdo, $roleFilter = '', $searchQuery = '') {
    $users = getUsersAdvanced($pdo, $roleFilter, $searchQuery, 0, 10000);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=usuarios.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nombre', 'Apellido', 'Email', 'Rol', 'Fecha de Creación', 'Última Actualización']);

    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['first_name'],
            $user['last_name'],
            $user['email'],
            $user['role'],
            $user['created_at'],
            $user['updated_at']
        ]);
    }

    fclose($output);
    exit();
}

// Manejar exportación
if (isset($_POST['export_csv'])) {
    exportToCSV($pdo, $_POST['role_filter'] ?? '', $_POST['search_query'] ?? '');
}

// Paginación
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filtros de búsqueda
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Obtener datos
$users = getUsersAdvanced($pdo, $roleFilter, $searchQuery, $offset, $limit);
$totalUsers = getUserCount($pdo, $roleFilter, $searchQuery);
$totalPages = ceil($totalUsers / $limit);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Avanzada de Usuarios</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f4f4f4;
        }
        h1, h2 {
            color: #333;
        }
        form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }
        input[type="text"], select {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input[type="submit"], button {
            padding: 10px 15px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 5px;
        }
        input[type="submit"]:hover, button:hover {
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: #fff;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            color: green;
            border: 1px solid #d4edda;
            background-color: #d4edda;
        }
        .error {
            color: red;
            border: 1px solid #f8d7da;
            background-color: #f8d7da;
        }
        .pagination {
            margin: 20px 0;
            text-align: center;
        }
        .pagination a {
            padding: 10px 15px;
            margin: 0 5px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination a.active {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>Gestión Avanzada de Usuarios</h1>

    <!-- Filtro y Búsqueda -->
    <form action="" method="get">
        <label for="role">Filtrar por Rol:</label>
        <select id="role" name="role">
            <option value="">Todos</option>
            <option value="vendedor" <?php echo $roleFilter === 'vendedor' ? 'selected' : ''; ?>>Vendedor</option>
            <option value="contador" <?php echo $roleFilter === 'contador' ? 'selected' : ''; ?>>Contador</option>
            <option value="administrador" <?php echo $roleFilter === 'administrador' ? 'selected' : ''; ?>>Administrador</option>
        </select>
        <label for="search">Buscar:</label>
        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
        <input type="submit" value="Buscar">
    </form>

    <!-- Exportar a CSV -->
    <form action="" method="post">
        <input type="hidden" name="role_filter" value="<?php echo htmlspecialchars($roleFilter); ?>">
        <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
        <input type="submit" name="export_csv" value="Exportar a CSV">
    </form>

    <!-- Lista de usuarios -->
    <h2>Lista de Usuarios</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Fecha de Creación</th>
                <th>Última Actualización</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($user['updated_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Paginación -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&role=<?php echo urlencode($roleFilter); ?>&search=<?php echo urlencode($searchQuery); ?>">Anterior</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&role=<?php echo urlencode($roleFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&role=<?php echo urlencode($roleFilter); ?>&search=<?php echo urlencode($searchQuery); ?>">Siguiente</a>
        <?php endif; ?>
    </div>
</body>
</html>