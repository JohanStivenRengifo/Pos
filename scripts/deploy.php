<?php
$instance_name = "pos-system-" . strtolower($username);
$command = "lightsail create-instances --instance-names $instance_name --availability-zone us-east-1a --blueprint-id mean-4 --bundle-id micro_2_0 --user-data file://../templates/deployment_script.sh";

exec($command, $output, $return_var);

if ($return_var === 0) {
    // Notificar al usuario
    include_once 'notify.php';
    sendDeploymentNotification($username, $instance_name);
} else {
    echo "Error en el despliegue.";
}
?>
