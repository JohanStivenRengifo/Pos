<?php
if (isset($_GET['module'])) {
    $module = $_GET['module'];
    $submodule = $_GET['submodule'] ?? null;
    switch ($module) {
        case 'Ingresos':
            include 'modules/ingresos.php';
            break;
        case 'Egresos':
            include 'modules/egresos.php';
            break;
        case 'Contactos':
            include 'modules/contactos.php';
            break;
        case 'Documentacion':
            switch ($submodule) {
                case 'ingresos':
                    include 'modules/documentacion/ingresos.php';
                    break;
                case 'egresos':
                    include 'modules/documentacion/egresos.php';
                    break;
                case 'contactos':
                    include 'modules/documentacion/contactos.php';
                    break;
                case 'inventarios':
                    include 'modules/documentacion/inventarios.php';
                    break;
                case 'contabilidad':
                    include 'modules/documentacion/contabilidad.php';
                    break;
                case 'reportes':
                    include 'modules/documentacion/reportes.php';
                    break;
                case 'pos':
                    include 'modules/documentacion/pos.php';
                    break;
                case 'configuracion':
                    include 'modules/documentacion/configuracion.php';
                    break;
                default:
                    include 'modules/documentacion/documentacion.php';
            }
            break;
        default:
            include 'modules/default.php';
    }
}
?>