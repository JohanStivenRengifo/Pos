<?php
session_start();
require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function generarPDFProductos() {
    global $pdo;
    
    try {
        // Verificar si el usuario está autenticado
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('Usuario no autenticado');
        }

        // Obtener productos
        $stmt = $pdo->prepare("
            SELECT 
                p.*, 
                c.nombre as categoria_nombre,
                d.nombre as departamento_nombre
            FROM inventario p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            LEFT JOIN departamentos d ON p.departamento_id = d.id
            WHERE p.user_id = ?
            ORDER BY p.nombre ASC
        ");
        
        $stmt->execute([$_SESSION['user_id']]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Configurar DOMPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Generar contenido HTML
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { 
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .header h1 {
                    color: #333;
                    margin: 0;
                    padding: 10px 0;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }
                th {
                    background-color: #f8f9fa;
                    font-weight: bold;
                }
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .fecha {
                    text-align: right;
                    margin-bottom: 20px;
                    font-size: 11px;
                    color: #666;
                }
                .footer {
                    position: fixed;
                    bottom: 0;
                    width: 100%;
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                    padding: 10px 0;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Reporte de Inventario</h1>
            </div>
            <div class="fecha">
                Fecha de generación: ' . date('d/m/Y H:i:s') . '
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Departamento</th>
                        <th>Stock</th>
                        <th>Precio</th>
                        <th>Valor Total</th>
                    </tr>
                </thead>
                <tbody>';
        
        $total_inventario = 0;
        foreach ($productos as $producto) {
            $valor_total = $producto['stock'] * $producto['precio_venta'];
            $total_inventario += $valor_total;
            
            $html .= '<tr>
                <td>' . htmlspecialchars($producto['codigo_barras']) . '</td>
                <td>' . htmlspecialchars($producto['nombre']) . '</td>
                <td>' . htmlspecialchars($producto['categoria_nombre']) . '</td>
                <td>' . htmlspecialchars($producto['departamento_nombre']) . '</td>
                <td>' . number_format($producto['stock'], 0) . '</td>
                <td>$' . number_format($producto['precio_venta'], 2, ',', '.') . '</td>
                <td>$' . number_format($valor_total, 2, ',', '.') . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align: right;"><strong>Valor Total del Inventario:</strong></td>
                    <td><strong>$' . number_format($total_inventario, 2, ',', '.') . '</strong></td>
                </tr>
            </tfoot>
            </table>
            <div class="footer">
                Generado por VendEasy - Página {PAGE_NUM} de {PAGE_COUNT}
            </div>
        </body>
        </html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
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