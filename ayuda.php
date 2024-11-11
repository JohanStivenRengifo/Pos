<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Incluir la configuración de la base de datos
require_once 'config/db.php';

// Obtener datos del usuario
$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayuda - VendEasy</title>
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
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">
            <a href="welcome.php">VendEasy</a>
        </div>
        <div class="header-icons">
            <i class="fas fa-bell"></i>
            <div class="account">
                <h4><?= htmlspecialchars($email) ?></h4>
            </div>
        </div>
    </header>

    <div class="container">
        <nav>
            <div class="side_navbar">
                <span>Menú Principal</span>
                <a href="welcome.php">Dashboard</a>
                <a href="/modules/pos/index.php">Punto de Venta</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
                <a href="/modules/proveedores/index.php">Proveedores</a>
                <a href="/modules/reportes/index.php">Reportes</a>
                <a href="/modules/config/index.php">Configuración</a>

                <div class="links">
                    <span>Enlaces Rápidos</span>
                    <a href="#" class="active">Ayuda</a>
                    <a href="./contacto.php">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <div class="help-container">
                <h2>Centro de Ayuda</h2>

                <div class="help-section">
                    <h3>Preguntas Frecuentes</h3>
                    <div class="faq-item">
                        <div class="faq-question">
                            ¿Cómo realizar una venta? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Para realizar una venta, sigue estos pasos:
                            <ol>
                                <li>Accede al módulo "Punto de Venta"</li>
                                <li>Selecciona los productos del catálogo</li>
                                <li>Añade la cantidad deseada</li>
                                <li>Selecciona el método de pago</li>
                                <li>Confirma la venta</li>
                            </ol>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            ¿Cómo gestionar el inventario? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            El módulo de inventario te permite:
                            <ul>
                                <li>Agregar nuevos productos</li>
                                <li>Actualizar existencias</li>
                                <li>Configurar alertas de stock bajo</li>
                                <li>Generar reportes de inventario</li>
                            </ul>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            ¿Cómo generar reportes? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Accede al módulo de Reportes donde podrás:
                            <ul>
                                <li>Seleccionar el tipo de reporte</li>
                                <li>Establecer el rango de fechas</li>
                                <li>Filtrar por categorías específicas</li>
                                <li>Exportar en diferentes formatos</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="help-section">
                    <h3>Guías Rápidas</h3>
                    <div class="video-tutorial">
                        <h4>Tutorial: Primeros pasos en VendEasy</h4>
                        <p>Aprende lo básico para comenzar a usar el sistema.</p>
                        <!-- Aquí puedes agregar un enlace a un video tutorial -->
                    </div>
                    <div class="video-tutorial">
                        <h4>Tutorial: Gestión de Ventas</h4>
                        <p>Aprende a gestionar ventas de manera eficiente.</p>
                        <!-- Aquí puedes agregar un enlace a un video tutorial -->
                    </div>
                </div>

                <div class="contact-support">
                    <h3>¿Necesitas más ayuda?</h3>
                    <p>Nuestro equipo de soporte está disponible para ayudarte</p>
                    <a href="./contacto.php">Contactar Soporte</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Script para manejar el acordeón de las FAQs
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;
                const isActive = question.classList.contains('active');
                
                // Cerrar todas las respuestas
                document.querySelectorAll('.faq-question').forEach(q => {
                    q.classList.remove('active');
                    q.nextElementSibling.style.display = 'none';
                });

                // Abrir/cerrar la respuesta actual
                if (!isActive) {
                    question.classList.add('active');
                    answer.style.display = 'block';
                }
            });
        });
    </script>
</body>

</html> 