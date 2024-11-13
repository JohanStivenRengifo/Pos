<?php
// Funciones relacionadas con empresas

function getUserInfo($user_id) {
    global $pdo;
    try {
        // Obtener información básica del usuario
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.nombre
            FROM users u
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Obtener la empresa principal del usuario
            $stmt = $pdo->prepare("
                SELECT e.id as empresa_id
                FROM empresas e
                JOIN user_empresas ue ON e.id = ue.empresa_id
                WHERE ue.user_id = ? AND ue.es_principal = 1
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($empresa) {
                $user['empresa_id'] = $empresa['empresa_id'];
            }
        }
        
        return $user;
    } catch (Exception $e) {
        error_log("Error obteniendo información del usuario: " . $e->getMessage());
        return false;
    }
}

function getEmpresaInfo($empresa_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM empresas
            WHERE id = ?
        ");
        $stmt->execute([$empresa_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo información de la empresa: " . $e->getMessage());
        return false;
    }
}

function getUserEmpresas($user_id) {
    global $pdo;
    
    try {
        $query = "SELECT * FROM empresas WHERE usuario_id = ? ORDER BY nombre_empresa ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error al obtener empresas: " . $e->getMessage());
        throw new Exception("Error al obtener las empresas");
    }
}

function saveEmpresa($user_id, $empresa_id, $data, $logo = null) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        if ($empresa_id) {
            // Actualizar empresa existente
            $sql = "UPDATE empresas SET 
                nombre_empresa = ?, 
                nit = ?,
                regimen_fiscal = ?,
                direccion = ?, 
                telefono = ?, 
                correo_contacto = ?, 
                prefijo_factura = ?, 
                numero_inicial = ?, 
                numero_final = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND usuario_id = ?";
            
            $params = [
                $data['nombre_empresa'], 
                $data['nit'], 
                $data['regimen_fiscal'], 
                $data['direccion'], 
                $data['telefono'], 
                $data['correo_contacto'], 
                $data['prefijo_factura'], 
                $data['numero_inicial'], 
                $data['numero_final'],
                $empresa_id,
                $user_id
            ];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Manejar el logo si se ha subido uno nuevo
            if ($logo && $logo['error'] === UPLOAD_ERR_OK) {
                // ... código de manejo del logo ...
            }
        } else {
            // ... código para nueva empresa ...
        }

        $pdo->commit();
        return $empresa_id ?: $pdo->lastInsertId();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error guardando empresa: " . $e->getMessage());
        throw new Exception("Error al guardar la empresa: " . $e->getMessage());
    }
}

function setEmpresaPrincipal($user_id, $empresa_id) {
    global $pdo;
    
    try {
        // Comenzar transacción
        $pdo->beginTransaction();
        
        // Primero quitamos el estado principal de todas las empresas del usuario
        $query = "UPDATE empresas SET es_principal = 0 WHERE usuario_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        
        // Luego establecemos la nueva empresa principal
        $query = "UPDATE empresas SET es_principal = 1 WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$empresa_id, $user_id]);
        
        // Confirmar transacción
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        // Revertir cambios si hay error
        $pdo->rollBack();
        error_log("Error al establecer empresa principal: " . $e->getMessage());
        throw new Exception("No se pudo establecer la empresa principal");
    }
}

function eliminarEmpresa($empresa_id, $user_id) {
    global $pdo;
    
    try {
        // Primero verificamos que la empresa pertenezca al usuario
        $query = "SELECT * FROM empresas WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$empresa_id, $user_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception("No tienes permiso para eliminar esta empresa");
        }

        // Verificamos que no sea la empresa principal
        $query = "SELECT es_principal FROM empresas WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$empresa_id]);
        $empresa = $stmt->fetch();
        
        if ($empresa['es_principal']) {
            throw new Exception("No puedes eliminar la empresa principal");
        }

        // Eliminamos la empresa
        $query = "DELETE FROM empresas WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$empresa_id, $user_id]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error al eliminar empresa: " . $e->getMessage());
        throw new Exception("No se pudo eliminar la empresa: " . $e->getMessage());
    }
} 