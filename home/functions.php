<?php
require_once '../config/env_loader.php'; 

define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('BACKUP_PATH', rtrim(getenv('BACKUP_PATH'), '/') ?: '');
define('SOURCE_PATH', rtrim(getenv('SOURCE_PATH'), '/') ?: '');

function backupDatabase($databaseName, $backupPath) {
    if (!is_dir($backupPath)) {
        echo "<p class='error-message'>Ruta de backup no válida: $backupPath</p>";
        return;
    }

    $timestamp = date("Ymd_His");
    $backupFile = "{$backupPath}/{$databaseName}_{$timestamp}.sql";
    $command = "mysqldump -u " . DB_USER . " -p" . DB_PASS . " $databaseName > $backupFile";
    
    exec($command, $output, $return_var);

    if ($return_var === 0) {
        echo "<p class='success-message'>Backup de la base de datos realizado correctamente: $backupFile</p>";
    } else {
        echo "<p class='error-message'>Error al realizar el backup de la base de datos.</p>";
    }
}

function backupSystemFiles($sourcePath, $backupPath) {
    if (!is_dir($sourcePath)) {
        echo "<p class='error-message'>Ruta de origen no válida: $sourcePath</p>";
        return;
    }

    if (!is_dir($backupPath)) {
        echo "<p class='error-message'>Ruta de backup no válida: $backupPath</p>";
        return;
    }

    $timestamp = date("Ymd_His");
    $backupFile = "{$backupPath}/system_files_backup_{$timestamp}.tar.gz";
    $command = "tar -czf $backupFile -C $sourcePath .";
    
    exec($command, $output, $return_var);

    if ($return_var === 0) {
        echo "<p class='success-message'>Backup de los archivos del sistema realizado correctamente: $backupFile</p>";
    } else {
        echo "<p class='error-message'>Error al realizar el backup de los archivos del sistema.</p>";
    }
}

function performBackups($config) {
    if ($config['tipo_backup'] === 'completo' || $config['tipo_backup'] === 'incremental') {
        backupDatabase('nombre_de_tu_base_de_datos', BACKUP_PATH);
    }

    if ($config['tipo_backup'] === 'completo') {
        backupSystemFiles(SOURCE_PATH, BACKUP_PATH);
    }
}

function updateConfigurationAndModules($user_id, $config, $modulos_activos) {
    $pdo = getPDOConnection();
    $stmt = $pdo->prepare("UPDATE configuraciones SET zona_horaria = ?, moneda = ?, idioma = ?, frecuencia_backup = ?, tipo_backup = ?, ubicacion_backup = ?, modulos_activos = ? WHERE user_id = ?");
    $stmt->execute([
        $config['zona_horaria'], 
        $config['moneda'], 
        $config['idioma'], 
        $config['frecuencia_backup'], 
        $config['tipo_backup'], 
        $config['ubicacion_backup'], 
        json_encode($modulos_activos),
        $user_id
    ]);

    if ($stmt->rowCount() > 0) {
        echo "<p class='success-message'>Configuración actualizada correctamente.</p>";
    } else {
        echo "<p class='error-message'>No se pudo actualizar la configuración.</p>";
    }
}
?>