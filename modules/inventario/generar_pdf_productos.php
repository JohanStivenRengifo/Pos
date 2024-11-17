<?php
session_start();
require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function generarPDFProductos($categoria_id = null, $departamento_id = null, $stock_minimo = false) {
    global $pdo;
    
    try {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('Usuario no autenticado');
        }

        // Construir la consulta base
        $query = "
            SELECT 
                p.*, 
                c.nombre as categoria_nombre,
                d.nombre as departamento_nombre,
                CASE 
                    WHEN p.stock <= p.stock_minimo THEN 'bajo'
                    WHEN p.stock <= (p.stock_minimo * 1.5) THEN 'medio'
                    ELSE 'normal'
                END as nivel_stock
            FROM inventario p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            LEFT JOIN departamentos d ON p.departamento_id = d.id
            WHERE p.user_id = :user_id";

        $params = [':user_id' => $_SESSION['user_id']];

        // Agregar filtros
        if ($categoria_id) {
            $query .= " AND p.categoria_id = :categoria_id";
            $params[':categoria_id'] = $categoria_id;
        }
        if ($departamento_id) {
            $query .= " AND p.departamento_id = :departamento_id";
            $params[':departamento_id'] = $departamento_id;
        }
        if ($stock_minimo) {
            $query .= " AND p.stock <= p.stock_minimo";
        }

        $query .= " ORDER BY p.nombre ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Configurar DOMPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Obtener información de la empresa
        $stmt = $pdo->prepare("SELECT * FROM configuracion WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Generar contenido HTML con diseño mejorado
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { 
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.4;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding: 20px;
                    border-bottom: 2px solid #eee;
                }
                .header img {
                    max-width: 150px;
                    margin-bottom: 10px;
                }
                .header h1 {
                    color: #2563eb;
                    margin: 0;
                    font-size: 24px;
                }
                .empresa-info {
                    margin-bottom: 20px;
                    font-size: 11px;
                    color: #666;
                }
                .reporte-info {
                    margin-bottom: 20px;
                    padding: 10px;
                    background: #f8fafc;
                    border-radius: 4px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    font-size: 10px;
                }
                th {
                    background-color: #2563eb;
                    color: white;
                    padding: 10px;
                    text-align: left;
                    font-weight: bold;
                }
                td {
                    padding: 8px;
                    border-bottom: 1px solid #e5e7eb;
                }
                tr:nth-child(even) {
                    background-color: #f8fafc;
                }
                .stock-bajo {
                    color: #dc2626;
                    font-weight: bold;
                }
                .stock-medio {
                    color: #d97706;
                }
                .stock-normal {
                    color: #059669;
                }
                .resumen {
                    margin-top: 30px;
                    padding: 15px;
                    background: #f8fafc;
                    border-radius: 4px;
                }
                .footer {
                    position: fixed;
                    bottom: 0;
                    width: 100%;
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                    padding: 10px 0;
                    border-top: 1px solid #eee;
                }
                .page-break {
                    page-break-after: always;
                }
                .pagenum:before {
                    content: counter(page);
                }
            </style>
        </head>
        <body>
            <div class="header">
                ' . ($config['logo'] ? '<img src="' . $config['logo'] . '" alt="Logo">' : '') . '
                <h1>Reporte de Inventario</h1>
                <div class="empresa-info">
                    <p><strong>' . htmlspecialchars($config['nombre_empresa']) . '</strong></p>
                    <p>' . htmlspecialchars($config['direccion']) . '</p>
                    <p>Tel: ' . htmlspecialchars($config['telefono']) . '</p>
                </div>
            </div>

            <div class="reporte-info">
                <p><strong>Fecha de generación:</strong> ' . date('d/m/Y H:i:s') . '</p>
                ' . ($categoria_id ? '<p><strong>Categoría:</strong> ' . $productos[0]['categoria_nombre'] . '</p>' : '') . '
                ' . ($departamento_id ? '<p><strong>Departamento:</strong> ' . $productos[0]['departamento_nombre'] . '</p>' : '') . '
                ' . ($stock_minimo ? '<p><strong>Filtro:</strong> Productos bajo stock mínimo</p>' : '') . '
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Stock</th>
                        <th>Mínimo</th>
                        <th>Precio</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>';
        
        $total_inventario = 0;
        $productos_bajo_stock = 0;
        foreach ($productos as $producto) {
            $valor_total = $producto['stock'] * $producto['precio_venta'];
            $total_inventario += $valor_total;
            
            if ($producto['stock'] <= $producto['stock_minimo']) {
                $productos_bajo_stock++;
            }
            
            $stock_class = 'stock-' . $producto['nivel_stock'];
            
            $html .= '<tr>
                <td>' . htmlspecialchars($producto['codigo_barras']) . '</td>
                <td>' . htmlspecialchars($producto['nombre']) . '</td>
                <td>' . htmlspecialchars($producto['categoria_nombre']) . '</td>
                <td class="' . $stock_class . '">' . number_format($producto['stock'], 0) . '</td>
                <td>' . number_format($producto['stock_minimo'], 0) . '</td>
                <td>$' . number_format($producto['precio_venta'], 2, ',', '.') . '</td>
                <td>$' . number_format($valor_total, 2, ',', '.') . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>

            <div class="resumen">
                <h3>Resumen del Inventario</h3>
                <p><strong>Total de Productos:</strong> ' . count($productos) . '</p>
                <p><strong>Productos Bajo Stock:</strong> ' . $productos_bajo_stock . '</p>
                <p><strong>Valor Total del Inventario:</strong> $' . number_format($total_inventario, 2, ',', '.') . '</p>
            </div>

            <div class="footer">
                Generado por VendEasy - Página <span class="pagenum"></span>
            </div>
        </body>
        </html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');

        $dompdf->getCanvas()->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $canvas->text(520, 15, 'Página ' . $pageNumber . ' de ' . $pageCount, $fontMetrics->get_font('Arial, sans-serif'), 10);
        });

        $dompdf->render();

        // Guardar el PDF temporalmente
        $output_dir = sys_get_temp_dir();
        $filename = 'inventario_' . date('YmdHis') . '.pdf';
        $output_path = $output_dir . '/' . $filename;
        
        file_put_contents($output_path, $dompdf->output());
        
        return $output_path;
        
    } catch (Exception $e) {
        throw new Exception('Error al generar el PDF: ' . $e->getMessage());
    }
} 