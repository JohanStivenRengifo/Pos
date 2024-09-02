<?php
function sendDeploymentNotification($username, $instance_name) {
    $to = "$username@example.com";
    $subject = "Despliegue de su POS completado";
    $message = file_get_contents('../templates/email_template.php');
    $headers = "From: no-reply@pos-system.com";

    mail($to, $subject, $message, $headers);
}
?>
