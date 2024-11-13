<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/db.php';

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Ayuda - Sistema Contable</title>
    <link rel="icon" type="image/png" href="favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="css/welcome.css">
    <style>
        .help-container {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        .help-section {
            margin-bottom: 30px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .help-section h3 {
            color: #2962ff;
            margin-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .faq-item {
            margin-bottom: 20px;
        }

        .faq-question {
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .faq-answer {
            display: none;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
            margin-top: 5px;
        }

        .faq-question.active + .faq-answer {
            display: block;
        }

        .video-tutorial {
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .contact-support {
            text-align: center;
            padding: 20px;
            background: #e3f2fd;
            border-radius: 8px;
            margin-top: 30px;
        }

        .contact-support a {
            display: inline-block;
            padding: 10px 20px;
            background: #2962ff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }

        .contact-support a:hover {
            background: #1565c0;
        }

        /* Nuevos estilos para secciones contables */
        .accounting-tips {
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #2962ff;
            margin: 10px 0;
        }
        
        .important-note {
            background: #fff3e0;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .video-tutorial {
            border-left: 4px solid #4caf50;
        }
    </style>
</head>

<body>
<?php include 'includes/header.php'; ?>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-body">
            <div class="help-container">
                <h2>Centro de Ayuda - Sistema Contable</h2>

                <div class="help-section">
                    <h3>Preguntas Frecuentes sobre Contabilidad</h3>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            ¿Cómo registrar asientos contables? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Para registrar asientos contables:
                            <ol>
                                <li>Accede al módulo "Libro Diario"</li>
                                <li>Selecciona "Nuevo Asiento"</li>
                                <li>Ingresa la fecha y descripción</li>
                                <li>Añade las cuentas deudoras y acreedoras</li>
                                <li>Verifica que el asiento esté cuadrado</li>
                                <li>Guarda el asiento</li>
                            </ol>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            ¿Cómo generar estados financieros? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Para generar estados financieros:
                            <ul>
                                <li>Accede a "Reportes Financieros"</li>
                                <li>Selecciona el tipo de estado (Balance General, Estado de Resultados, etc.)</li>
                                <li>Define el período</li>
                                <li>Configura los filtros necesarios</li>
                                <li>Genera el reporte en PDF o Excel</li>
                            </ul>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            ¿Cómo realizar el cierre contable? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            El proceso de cierre contable incluye:
                            <ul>
                                <li>Verificar todos los asientos del período</li>
                                <li>Realizar ajustes necesarios</li>
                                <li>Generar asientos de cierre</li>
                                <li>Verificar balances finales</li>
                                <li>Generar reportes de cierre</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="help-section">
                    <h3>Guías y Tutoriales Contables</h3>
                    
                    <div class="accounting-tips">
                        <h4>Consejos para una Contabilidad Eficiente</h4>
                        <ul>
                            <li>Mantén al día los registros contables</li>
                            <li>Realiza conciliaciones bancarias mensualmente</li>
                            <li>Verifica los saldos de las cuentas regularmente</li>
                            <li>Mantén respaldo de todos los documentos importantes</li>
                        </ul>
                    </div>

                    <div class="video-tutorial">
                        <h4>Tutorial: Proceso Contable Completo</h4>
                        <p>Aprende el ciclo contable paso a paso.</p>
                    </div>

                    <div class="video-tutorial">
                        <h4>Tutorial: Reportes Financieros</h4>
                        <p>Genera e interpreta estados financieros.</p>
                    </div>
                </div>

                <div class="important-note">
                    <h4><i class="fas fa-exclamation-circle"></i> Nota Importante</h4>
                    <p>Recuerda realizar respaldos periódicos de tu información contable y verificar la normativa contable vigente en tu región.</p>
                </div>

                <div class="contact-support">
                    <h3>¿Necesitas Soporte Contable?</h3>
                    <p>Nuestro equipo de contadores está disponible para resolver tus dudas</p>
                    <a href="./contacto.php">Contactar Soporte Contable</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Script del acordeón (mantener el mismo)
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;
                const isActive = question.classList.contains('active');
                
                document.querySelectorAll('.faq-question').forEach(q => {
                    q.classList.remove('active');
                    q.nextElementSibling.style.display = 'none';
                });

                if (!isActive) {
                    question.classList.add('active');
                    answer.style.display = 'block';
                }
            });
        });
    </script>
</body>
</html> 