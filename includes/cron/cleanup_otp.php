<?php
require_once __DIR__ . '/../../config/db.php';

// Eliminar códigos OTP expirados o usados
$stmt = $pdo->prepare("
    DELETE FROM otp_codes 
    WHERE expires_at < NOW() 
    OR used = TRUE 
    OR created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->execute(); 