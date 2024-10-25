-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 25, 2024 at 02:46 PM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `global_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categorias`
--

CREATE TABLE `categorias` (
  `id` int NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `departamento_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `departamento_id`) VALUES
(1, 'Varios', 1),
(2, 'Herramientas Manuales', 1),
(3, 'Herramientas Eléctricas', 1),
(4, 'Materiales de Construcción', 2),
(5, 'Cemento', 2),
(6, 'Ladrillos', 2),
(7, 'Madera', 2),
(8, 'Plantadoras', 3),
(9, 'Semillas', 3),
(10, 'Cuidado del Jardín', 3),
(11, 'Cableado', 4),
(12, 'Interruptores', 4),
(13, 'Tomas de Corriente', 4),
(14, 'Tuberías', 5),
(15, 'Grifos', 5),
(16, 'Accesorios de Plomería', 5),
(17, 'Pinturas y Selladores', 6),
(18, 'Brochas y Rodillos', 6),
(19, 'Equipos de Seguridad', 7),
(20, 'Cámaras de Seguridad', 7),
(21, 'Cerraduras', 7);

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo_identificacion` varchar(50) NOT NULL,
  `identificacion` varchar(50) NOT NULL,
  `primer_nombre` varchar(100) NOT NULL,
  `segundo_nombre` varchar(100) DEFAULT NULL,
  `apellidos` varchar(100) NOT NULL,
  `municipio_departamento` varchar(100) DEFAULT NULL,
  `codigo_postal` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `clientes`
--

INSERT INTO `clientes` (`id`, `user_id`, `nombre`, `email`, `telefono`, `created_at`, `tipo_identificacion`, `identificacion`, `primer_nombre`, `segundo_nombre`, `apellidos`, `municipio_departamento`, `codigo_postal`) VALUES
(10, 4, 'Consumidor Final', 'email@email.com', '3216371125', '2024-09-28 21:48:45', 'cc', '1234567890', 'Consumidor', 'Final', '.', 'Popayan', '190017');

-- --------------------------------------------------------

--
-- Table structure for table `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `empresa_id` int DEFAULT NULL,
  `tipo` varchar(50) NOT NULL,
  `valor` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `configuracion`
--

INSERT INTO `configuracion` (`id`, `user_id`, `empresa_id`, `tipo`, `valor`) VALUES
(2, 4, NULL, 'numeracion_factura', 1),
(3, 4, NULL, 'numeracion_factura', 1),
(4, 4, NULL, 'numeracion_factura', 10);

-- --------------------------------------------------------

--
-- Table structure for table `cotizaciones`
--

CREATE TABLE `cotizaciones` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) DEFAULT '0.00',
  `fecha` datetime NOT NULL,
  `numero_cotizacion` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cotizaciones`
--

INSERT INTO `cotizaciones` (`id`, `user_id`, `cliente_id`, `total`, `descuento`, `fecha`, `numero_cotizacion`) VALUES
(1, 4, 10, '46000.00', '0.00', '2024-10-09 15:47:29', 'C2024-000001'),
(2, 4, 10, '98500.00', '0.00', '2024-10-10 07:40:45', 'C2024-000002'),
(3, 4, 10, '98500.00', '0.00', '2024-10-10 08:09:50', 'C2024-000003');

-- --------------------------------------------------------

--
-- Table structure for table `cotizacion_detalles`
--

CREATE TABLE `cotizacion_detalles` (
  `id` int NOT NULL,
  `cotizacion_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` int NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cotizacion_detalles`
--

INSERT INTO `cotizacion_detalles` (`id`, `cotizacion_id`, `producto_id`, `cantidad`, `precio_unitario`) VALUES
(1, 1, 71, 1, '23000.00'),
(2, 1, 68, 1, '23000.00'),
(3, 2, 1521, 1, '98500.00'),
(4, 3, 1521, 1, '98500.00');

-- --------------------------------------------------------

--
-- Table structure for table `creditos`
--

CREATE TABLE `creditos` (
  `id` int NOT NULL,
  `venta_id` int NOT NULL,
  `plazo` int NOT NULL,
  `interes` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departamentos`
--

CREATE TABLE `departamentos` (
  `id` int NOT NULL,
  `nombre` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `departamentos`
--

INSERT INTO `departamentos` (`id`, `nombre`) VALUES
(1, 'General'),
(2, 'Herramientas'),
(3, 'Construcción'),
(4, 'Jardinería'),
(5, 'Electricidad'),
(6, 'Plomería'),
(7, 'Pinturas'),
(8, 'Seguridad');

-- --------------------------------------------------------

--
-- Table structure for table `egresos`
--

CREATE TABLE `egresos` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `descripcion` text NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('pagado','deuda') DEFAULT 'pagado',
  `numero_factura` varchar(50) NOT NULL,
  `proveedor` varchar(255) NOT NULL,
  `fecha` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `empresas`
--

CREATE TABLE `empresas` (
  `id` int NOT NULL,
  `nombre_empresa` varchar(255) NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `correo_contacto` varchar(255) NOT NULL,
  `prefijo_factura` varchar(10) NOT NULL,
  `numero_inicial` int NOT NULL,
  `numero_final` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `empresas`
--

INSERT INTO `empresas` (`id`, `nombre_empresa`, `direccion`, `telefono`, `correo_contacto`, `prefijo_factura`, `numero_inicial`, `numero_final`) VALUES
(1, 'Ferreteria Obra Blanca', 'Calle 2#3a-18', '3112384067', 'obrablancajr@hotmail.com', 'POS', 1, 150000);

-- --------------------------------------------------------

--
-- Table structure for table `ingresos`
--

CREATE TABLE `ingresos` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventario`
--

CREATE TABLE `inventario` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `stock` int NOT NULL,
  `precio_costo` decimal(10,2) DEFAULT NULL,
  `impuesto` decimal(5,2) DEFAULT NULL,
  `precio_venta` decimal(10,2) DEFAULT NULL,
  `otro_dato` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `fecha_ingreso` datetime DEFAULT CURRENT_TIMESTAMP,
  `codigo_barras` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `departamento_id` int DEFAULT NULL,
  `categoria_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventario`
--

INSERT INTO `inventario` (`id`, `user_id`, `nombre`, `descripcion`, `stock`, `precio_costo`, `impuesto`, `precio_venta`, `otro_dato`, `fecha_ingreso`, `codigo_barras`, `departamento_id`, `categoria_id`) VALUES
(4458, 4, '1/4 2 En 1 Bler Negro', '1/4 2 En 1 Bler Negro', 16, '16040.00', '19.00', '23000.00', '1/4 2 En 1 Bler Negro', '2024-10-10 17:45:04', '101', 1, 1),
(4459, 6, '1/4 2 En 1 Gris Bler', '1/4 2 En 1 Gris Bler', 16, '16040.00', '19.00', '23000.00', '1/4 2 En 1 Gris Bler', '2024-10-10 17:45:04', '7708619524926', 1, 1),
(4460, 4, '1/4 Amarillo Oro T2 Corona', '1/4 Amarillo Oro T2 Corona', 0, '13919.00', '19.00', '23000.00', '1/4 Amarillo Oro T2 Corona', '2024-10-10 17:45:04', '7705389008885', 1, 1),
(4461, 4, '1/4 Anticorrosivo Negro Bler', '1/4 Anticorrosivo Negro Bler', 5, '13116.00', '19.00', '19500.00', '1/4 Anticorrosivo Negro Bler', '2024-10-10 17:45:04', '99', 1, 1),
(4462, 4, '1/4 Blanco Almendra T2 Corona', '1/4 Blanco Almendra T2 Corona', 10, '13919.00', '19.00', '23000.00', '1/4 Blanco Almendra T2 Corona', '2024-10-10 17:45:04', '7705389008601', 1, 1),
(4463, 4, '1/4 Blanco Hueso T2 Corona', '1/4 Blanco Hueso T2 Corona', 8, '13919.00', '19.00', '23000.00', '1/4 Blanco Hueso T2 Corona', '2024-10-10 17:45:04', '7705389008625', 1, 1),
(4464, 4, '1/4 Blanco T2 Corona', '1/4 Blanco T2 Corona', 1, '13919.00', '19.00', '23000.00', '1/4 Blanco T2 Corona', '2024-10-10 17:45:04', '7705389008595', 1, 1),
(4465, 4, '1/4 Esmalte Amarillo Bler', '1/4 Esmalte Amarillo Bler', 0, '17313.00', '19.00', '25000.00', '1/4 Esmalte Amarillo Bler', '2024-10-10 17:45:04', '1601006615459', 1, 1),
(4466, 4, '1/4 Esmalte Azul  Español Bler', '1/4 Esmalte Azul  Español Bler', 6, '19044.00', '19.00', '25000.00', '1/4 Esmalte Azul  Español Bler', '2024-10-10 17:45:04', '1605706', 1, 1),
(4467, 4, '1/4 Esmalte Azul Mar Bler', '1/4 Esmalte Azul Mar Bler', 0, '17313.00', '19.00', '25000.00', '1/4 Esmalte Azul Mar Bler', '2024-10-10 17:45:04', '1605806654632', 1, 1),
(4468, 4, '1/4 Esmalte Azul Verano Bler', '1/4 Esmalte Azul Verano Bler', 5, '17313.00', '19.00', '25000.00', '1/4 Esmalte Azul Verano Bler', '2024-10-10 17:45:04', '1605306650134', 1, 1),
(4469, 4, '1/4 Esmalte Blanco Bler', '1/4 Esmalte Blanco Bler', 10, '18490.00', '19.00', '25000.00', '1/4 Esmalte Blanco Bler', '2024-10-10 17:45:04', '1600006693184', 1, 1),
(4470, 4, '1/4 Esmalte Bler Caboa', '1/4 Esmalte Bler Caboa', 5, '17000.00', '19.00', '23000.00', '1/4 Esmalte Bler Caboa', '2024-10-10 17:45:04', '1603606703734', 1, 1),
(4471, 4, '1/4 Esmalte Caoba Bler', '1/4 Esmalte Caoba Bler', 11, '17313.00', '19.00', '25000.00', '1/4 Esmalte Caoba Bler', '2024-10-10 17:45:04', '98', 1, 1),
(4472, 4, '1/4 Esmalte Gris Claro Bler', '1/4 Esmalte Gris Claro Bler', 12, '19044.00', '19.00', '25000.00', '1/4 Esmalte Gris Claro Bler', '2024-10-10 17:45:04', '106106', 1, 1),
(4473, 4, '1/4 Esmalte Negro Bler', '1/4 Esmalte Negro Bler', 9, '17319.00', '19.00', '25000.00', '1/4 Esmalte Negro Bler', '2024-10-10 17:45:04', '7709158297869', 1, 1),
(4474, 4, '1/4 Esmalte Negro Every', '1/4 Esmalte Negro Every', 1, '13663.00', '19.00', '20000.00', '1/4 Esmalte Negro Every', '2024-10-10 17:45:04', '97', 1, 1),
(4475, 4, '1/4 Esmalte Verde Esmeralda Bler', '1/4 Esmalte Verde Esmeralda Bler', 11, '17313.00', '19.00', '25000.00', '1/4 Esmalte Verde Esmeralda Bler', '2024-10-10 17:45:04', '1604306654579', 1, 1),
(4476, 4, '1/4 Esmalte Verde Maquina Bler', '1/4 Esmalte Verde Maquina Bler', 5, '17313.00', '19.00', '25000.00', '1/4 Esmalte Verde Maquina Bler', '2024-10-10 17:45:04', '1604406654585', 1, 1),
(4477, 4, '1/4 Esmlate Amarillo Oxido', '1/4 Esmlate Amarillo Oxido', 6, '19044.00', '19.00', '25000.00', '1/4 Esmlate Amarillo Oxido', '2024-10-10 17:45:04', '1600206', 1, 1),
(4478, 4, '1/4 Esmlate Dorado Bler', '1/4 Esmlate Dorado Bler', 6, '19044.00', '19.00', '25000.00', '1/4 Esmlate Dorado Bler', '2024-10-10 17:45:04', '1605906', 1, 1),
(4479, 4, '1/4 Estucor  Interior Y Exterior', '1/4 Estucor  Interior Y Exterior', 0, '7000.00', '19.00', '12000.00', '1/4 Estucor  Interior Y Exterior', '2024-10-10 17:45:04', '7707181792177', 1, 1),
(4480, 4, '1/4 Gris Basalto T2 Corona', '1/4 Gris Basalto T2 Corona', 3, '13919.00', '19.00', '23000.00', '1/4 Gris Basalto T2 Corona', '2024-10-10 17:45:04', '7705389008724', 1, 1),
(4481, 4, '1/4 Laca Catalizada Brillante Caramelo Every', '1/4 Laca Catalizada Brillante Caramelo Every', 7, '17691.00', '19.00', '26000.00', '1/4 Laca Catalizada Brillante Caramelo Every', '2024-10-10 17:45:04', '81', 1, 1),
(4482, 4, '1/4 Laca Catalizada Brillante Every Miel', '1/4 Laca Catalizada Brillante Every Miel', 4, '17691.00', '19.00', '26000.00', '1/4 Laca Catalizada Brillante Every Miel', '2024-10-10 17:45:04', '82', 1, 1),
(4483, 4, '1/4 Laca Catalizada Brillante Every Nogal', '1/4 Laca Catalizada Brillante Every Nogal', 7, '17691.00', '19.00', '26000.00', '1/4 Laca Catalizada Brillante Every Nogal', '2024-10-10 17:45:04', '83', 1, 1),
(4484, 4, '1/4 Laca Catalizada Brillante Transparente Every', '1/4 Laca Catalizada Brillante Transparente Every', 8, '17691.00', '19.00', '26000.00', '1/4 Laca Catalizada Brillante Transparente Every', '2024-10-10 17:45:04', '84', 1, 1),
(4485, 4, '1/4 Laca Catalizada Semipermanente Every', '1/4 Laca Catalizada Semipermanente Every', 8, '18304.00', '19.00', '27000.00', '1/4 Laca Catalizada Semipermanente Every', '2024-10-10 17:45:04', '85', 1, 1),
(4486, 4, '1/4 Mandarina Tropical T2 Corona', '1/4 Mandarina Tropical T2 Corona', 9, '13919.00', '19.00', '23000.00', '1/4 Mandarina Tropical T2 Corona', '2024-10-10 17:45:04', '7705389008748', 1, 1),
(4487, 4, '1/4 Mangenta T2 Corona', '1/4 Mangenta T2 Corona', 8, '13919.00', '19.00', '23000.00', '1/4 Mangenta T2 Corona', '2024-10-10 17:45:04', '7705389008687', 1, 1),
(4488, 4, '1/4 Naranja Tentacion T2 Corona', '1/4 Naranja Tentacion T2 Corona', 4, '13919.00', '19.00', '23000.00', '1/4 Naranja Tentacion T2 Corona', '2024-10-10 17:45:04', '7705389008922', 1, 1),
(4489, 4, '1/4 Negro T2 Corona', '1/4 Negro T2 Corona', 2, '13919.00', '19.00', '23000.00', '1/4 Negro T2 Corona', '2024-10-10 17:45:04', '7705389008809', 1, 1),
(4490, 4, '1/4 Ocre T2 Corona', '1/4 Ocre T2 Corona', 4, '13919.00', '19.00', '23000.00', '1/4 Ocre T2 Corona', '2024-10-10 17:45:04', '7705389008847', 1, 1),
(4491, 4, '1/4 Palo De Rosa T1 Corona', '1/4 Palo De Rosa T1 Corona', 3, '19875.00', '19.00', '29000.00', '1/4 Palo De Rosa T1 Corona', '2024-10-10 17:45:04', '7705389014398', 1, 1),
(4492, 4, '1/4 Rojo Colonial T2 Corona', '1/4 Rojo Colonial T2 Corona', 6, '13919.00', '19.00', '23000.00', '1/4 Rojo Colonial T2 Corona', '2024-10-10 17:45:04', '7705389008762', 1, 1),
(4493, 4, '1/4 Rojo Vivo T2 Corona', '1/4 Rojo Vivo T2 Corona', 0, '13919.00', '19.00', '23000.00', '1/4 Rojo Vivo T2 Corona', '2024-10-10 17:45:04', '7705389008908', 1, 1),
(4494, 4, '1/4 Sellador Alta Lijabilidad', '1/4 Sellador Alta Lijabilidad', 8, '16314.00', '19.00', '24000.00', '1/4 Sellador Alta Lijabilidad', '2024-10-10 17:45:04', '86', 1, 1),
(4495, 4, '1/4 Verde Pino T2 Corona', '1/4 Verde Pino T2 Corona', 6, '13919.00', '19.00', '23000.00', '1/4 Verde Pino T2 Corona', '2024-10-10 17:45:04', '7705389008861', 1, 1),
(4496, 4, '1/4 Verde Primaveral T2 Corona', '1/4 Verde Primaveral T2 Corona', 6, '13919.00', '19.00', '23000.00', '1/4 Verde Primaveral T2 Corona', '2024-10-10 17:45:04', '7705389008700', 1, 1),
(4497, 4, '1/8 Anticorrosivo Gris Bler', '1/8 Anticorrosivo Gris Bler', 6, '8834.00', '19.00', '13000.00', '1/8 Anticorrosivo Gris Bler', '2024-10-10 17:45:04', '6506705651522', 1, 1),
(4498, 4, '1/8 Esmalte Amarillo Bler', '1/8 Esmalte Amarillo Bler', 3, '11967.00', '19.00', '17000.00', '1/8 Esmalte Amarillo Bler', '2024-10-10 17:45:04', '1601005651205', 1, 1),
(4499, 4, '1/8 Esmalte Blanco Bler', '1/8 Esmalte Blanco Bler', 0, '11967.00', '19.00', '17000.00', '1/8 Esmalte Blanco Bler', '2024-10-10 17:45:04', '1600005644866', 1, 1),
(4500, 4, '1/8 Esmalte Negro Bler', '1/8 Esmalte Negro Bler', 0, '11967.00', '19.00', '17000.00', '1/8 Esmalte Negro Bler', '2024-10-10 17:45:04', '1609005645125', 1, 1),
(4501, 4, '2 En 1 Blanco 1/4 Bler', '2 En 1 Blanco 1/4 Bler', 7, '19238.00', '19.00', '25000.00', '2 En 1 Blanco 1/4 Bler', '2024-10-10 17:45:04', '6510006690951', 1, 1),
(4502, 4, '2 En 1 Blanco Galon Bler', '2 En 1 Blanco Galon Bler', 0, '66836.00', '19.00', '80200.00', '2 En 1 Blanco Galon Bler', '2024-10-10 17:45:04', '6510010690961', 1, 1),
(4503, 4, '2 En 1 Gris Galon Bler', '2 En 1 Gris Galon Bler', 8, '59395.00', '19.00', '71200.00', '2 En 1 Gris Galon Bler', '2024-10-10 17:45:04', '7708619524001', 1, 1),
(4504, 4, '2 En 1 Negro Galon Bler', '2 En 1 Negro Galon Bler', 2, '59395.00', '19.00', '71200.00', '2 En 1 Negro Galon Bler', '2024-10-10 17:45:04', '17708619524640', 1, 1),
(4505, 4, 'Abrazadera Acero Inoxidable 1/2´´ X  7/8´´', 'Abrazadera Acero Inoxidable 1/2´´ X  7/8´´', 4, '692.00', '19.00', '1300.00', 'Abrazadera Acero Inoxidable 1/2´´ X  7/8´´', '2024-10-10 17:45:04', '7506240645467', 1, 1),
(4506, 4, 'Abrazadera Acero Inoxidable 1/4´´ X 5/8´´', 'Abrazadera Acero Inoxidable 1/4´´ X 5/8´´', 9, '691.00', '19.00', '900.00', 'Abrazadera Acero Inoxidable 1/4´´ X 5/8´´', '2024-10-10 17:45:04', '7506240645450', 1, 1),
(4507, 4, 'Abrazadera Amarilla 3/8', 'Abrazadera Amarilla 3/8', 30, '299.00', '19.00', '500.00', 'Abrazadera Amarilla 3/8', '2024-10-10 17:45:04', '7837', 1, 1),
(4508, 4, 'Abrazadera Amarilla Titan 1/4', 'Abrazadera Amarilla Titan 1/4', 12, '799.00', '19.00', '1200.00', 'Abrazadera Amarilla Titan 1/4', '2024-10-10 17:45:04', '78122', 1, 1),
(4509, 4, 'Abrazadera Anarilla 1/2', 'Abrazadera Anarilla 1/2', 12, '599.00', '19.00', '800.00', 'Abrazadera Anarilla 1/2', '2024-10-10 17:45:04', '7812', 1, 1),
(4510, 4, 'Abrazadera De Acero Inoxidable 1/2´´ - 3/4´´', 'Abrazadera De Acero Inoxidable 1/2´´ - 3/4´´', 7, '691.00', '19.00', '1000.00', 'Abrazadera De Acero Inoxidable 1/2´´ - 3/4´´', '2024-10-10 17:45:04', '7501206693933', 1, 1),
(4511, 4, 'Abrazadera Metalica 3/4 3010', 'Abrazadera Metalica 3/4 3010', 30, '1050.00', '19.00', '1600.00', 'Abrazadera Metalica 3/4 3010', '2024-10-10 17:45:04', '3010', 1, 1),
(4512, 4, 'Abrazadera Metalica Amarilla 1/2 203162', 'Abrazadera Metalica Amarilla 1/2 203162', 50, '600.00', '19.00', '1000.00', 'Abrazadera Metalica Amarilla 1/2 203162', '2024-10-10 17:45:04', '27702155048674', 1, 1),
(4513, 4, 'Abrazadera Titan 1 1/2', 'Abrazadera Titan 1 1/2', 12, '2264.00', '19.00', '3200.00', 'Abrazadera Titan 1 1/2', '2024-10-10 17:45:04', '13237', 1, 1),
(4514, 4, 'Abrazadera Titan 1-1 3/8', 'Abrazadera Titan 1-1 3/8', 12, '1510.00', '19.00', '2200.00', 'Abrazadera Titan 1-1 3/8', '2024-10-10 17:45:04', '7095', 1, 1),
(4515, 4, 'Abrazdera  Acero Inoxidable 3/8 A 5/8´´', 'Abrazdera  Acero Inoxidable 3/8 A 5/8´´', 10, '692.00', '19.00', '900.00', 'Abrazdera  Acero Inoxidable 3/8 A 5/8´´', '2024-10-10 17:45:04', '7501206693926', 1, 1),
(4516, 4, 'Accesorios Para Baño En Zinc Boccherini', 'Accesorios Para Baño En Zinc Boccherini', 1, '81699.00', '19.00', '120000.00', 'Accesorios Para Baño En Zinc Boccherini', '2024-10-10 17:45:04', '7707180678250', 1, 1),
(4517, 4, 'Aceite 3en1 Original 30ml', 'Aceite 3en1 Original 30ml', 6, '4100.00', '19.00', '5500.00', 'Aceite 3en1 Original 30ml', '2024-10-10 17:45:04', '79567570301', 1, 1),
(4518, 4, 'Aceite Multiusos Truper', 'Aceite Multiusos Truper', 6, '2326.00', '19.00', '3500.00', 'Aceite Multiusos Truper', '2024-10-10 17:45:04', '7501206689868', 1, 1),
(4519, 4, 'Aceitera 200ml Boccherini', 'Aceitera 200ml Boccherini', 1, '8200.00', '19.00', '12000.00', 'Aceitera 200ml Boccherini', '2024-10-10 17:45:04', '7707766454384', 1, 1),
(4520, 4, 'Acople Flexible Aluminio Uduke HT1213-1/27/8', 'Acople Flexible Aluminio Uduke HT1213-1/27/8', 4, '4750.00', '19.00', '6600.00', 'Acople Flexible Aluminio Uduke HT1213-1/27/8', '2024-10-10 17:45:04', '6973653175231', 1, 1),
(4521, 4, 'Acople Lavamanos Pcp', 'Acople Lavamanos Pcp', 3, '1360.00', '19.00', '2500.00', 'Acople Lavamanos Pcp', '2024-10-10 17:45:04', '7707331945415', 1, 1),
(4522, 4, 'Acople Lvms Y Lvpts Grival', 'Acople Lvms Y Lvpts Grival', 2, '2957.00', '19.00', '4000.00', 'Acople Lvms Y Lvpts Grival', '2024-10-10 17:45:04', '7706157618381', 1, 1),
(4523, 4, 'Acople Sanitario Grival', 'Acople Sanitario Grival', 10, '2876.00', '19.00', '4000.00', 'Acople Sanitario Grival', '2024-10-10 17:45:04', '7706157618602', 1, 1),
(4524, 4, 'Acople Sanitario Pcp', 'Acople Sanitario Pcp', 15, '1360.00', '19.00', '2500.00', 'Acople Sanitario Pcp', '2024-10-10 17:45:04', '7707331945712', 1, 1),
(4525, 4, 'Acople Sanitario Plasgrifo', 'Acople Sanitario Plasgrifo', 3, '1400.00', '19.00', '2200.00', 'Acople Sanitario Plasgrifo', '2024-10-10 17:45:04', '7700031014726', 1, 1),
(4526, 4, 'Acronal Al 50% Acroltex 1/4', 'Acronal Al 50% Acroltex 1/4', 2, '5650.00', '19.00', '7200.00', 'Acronal Al 50% Acroltex 1/4', '2024-10-10 17:45:04', '56897', 1, 1),
(4527, 4, 'Acronal Al 50% Acrotex 1/8', 'Acronal Al 50% Acrotex 1/8', 3, '3449.00', '19.00', '4800.00', 'Acronal Al 50% Acrotex 1/8', '2024-10-10 17:45:04', '23569', 1, 1),
(4528, 4, 'Acronal Al 50% Codesco 295-D 1/4', 'Acronal Al 50% Codesco 295-D 1/4', 0, '5800.00', '19.00', '8500.00', 'Acronal Al 50% Codesco 295-D 1/4', '2024-10-10 17:45:04', '78946', 1, 1),
(4529, 4, 'Acronal Al 50% Codesco 295-D Galon', 'Acronal Al 50% Codesco 295-D Galon', 0, '20000.00', '19.00', '28500.00', 'Acronal Al 50% Codesco 295-D Galon', '2024-10-10 17:45:04', '78945', 1, 1),
(4530, 4, 'Acronal Al 50% Galon Acroltex', 'Acronal Al 50% Galon Acroltex', 4, '18299.00', '19.00', '25800.00', 'Acronal Al 50% Galon Acroltex', '2024-10-10 17:45:04', '235689', 1, 1),
(4531, 4, 'Adaptador Hembra 1', 'Adaptador Hembra 1', 15, '1090.00', '19.00', '1600.00', 'Adaptador Hembra 1', '2024-10-10 17:45:04', '121104', 1, 1),
(4532, 4, 'Adaptador Hembra 1/2 Pvc G-Plast', 'Adaptador Hembra 1/2 Pvc G-Plast', 38, '344.00', '19.00', '500.00', 'Adaptador Hembra 1/2 Pvc G-Plast', '2024-10-10 17:45:04', '47', 1, 1),
(4533, 4, 'Adaptador Macho 1', 'Adaptador Macho 1', 8, '910.00', '19.00', '1300.00', 'Adaptador Macho 1', '2024-10-10 17:45:04', '121052', 1, 1),
(4534, 4, 'Adaptador Macho 1/2 G-Plast', 'Adaptador Macho 1/2 G-Plast', 13, '326.00', '19.00', '500.00', 'Adaptador Macho 1/2 G-Plast', '2024-10-10 17:45:04', '71', 1, 1),
(4535, 4, 'Adhesivos para Pared', 'Adhesivos para Pared', 12, '15000.00', '19.00', '20000.00', 'Adhesivos para Pared', '2024-10-10 17:45:04', '79461', 1, 1),
(4536, 4, 'Aerosl Amarillo Mercury', 'Aerosl Amarillo Mercury', 6, '5200.00', '19.00', '8000.00', 'Aerosl Amarillo Mercury', '2024-10-10 17:45:04', '7707692867432', 1, 1),
(4537, 4, 'Aerosol Aluminio 400ml Uduke', 'Aerosol Aluminio 400ml Uduke', 1, '6869.00', '19.00', '10000.00', 'Aerosol Aluminio 400ml Uduke', '2024-10-10 17:45:04', '6973653170991', 1, 1),
(4538, 4, 'Aerosol Amarillo Ocre Pintumix', 'Aerosol Amarillo Ocre Pintumix', 2, '5652.00', '19.00', '9500.00', 'Aerosol Amarillo Ocre Pintumix', '2024-10-10 17:45:04', '7707180672425', 1, 1),
(4539, 4, 'Aerosol Amarillo Uduke 400ml', 'Aerosol Amarillo Uduke 400ml', 5, '5850.00', '19.00', '8000.00', 'Aerosol Amarillo Uduke 400ml', '2024-10-10 17:45:04', '6973653171004', 1, 1),
(4540, 4, 'Aerosol Anticorrosivo Gris Pintumix', 'Aerosol Anticorrosivo Gris Pintumix', 1, '6949.00', '19.00', '10000.00', 'Aerosol Anticorrosivo Gris Pintumix', '2024-10-10 17:45:04', '7707180672494', 1, 1),
(4541, 4, 'Aerosol Anticorrosivo Negro 400ml Imdico', 'Aerosol Anticorrosivo Negro 400ml Imdico', 3, '6247.00', '19.00', '9000.00', 'Aerosol Anticorrosivo Negro 400ml Imdico', '2024-10-10 17:45:04', '1546', 1, 1),
(4542, 4, 'Aerosol Anticorrosivo Rojo 400ml Uduke', 'Aerosol Anticorrosivo Rojo 400ml Uduke', 5, '5850.00', '19.00', '8000.00', 'Aerosol Anticorrosivo Rojo 400ml Uduke', '2024-10-10 17:45:04', '6973653172490', 1, 1),
(4543, 4, 'Aerosol Azul Cielo Pintumix', 'Aerosol Azul Cielo Pintumix', 1, '5652.00', '19.00', '9500.00', 'Aerosol Azul Cielo Pintumix', '2024-10-10 17:45:04', '7707180671039', 1, 1),
(4544, 4, 'Aerosol Blanco Brillnte Imdico 400ml', 'Aerosol Blanco Brillnte Imdico 400ml', 14, '6294.00', '19.00', '9000.00', 'Aerosol Blanco Brillnte Imdico 400ml', '2024-10-10 17:45:04', '154', 1, 1),
(4545, 4, 'Aerosol Blanco Mate Imdico', 'Aerosol Blanco Mate Imdico', 7, '6545.00', '19.00', '9500.00', 'Aerosol Blanco Mate Imdico', '2024-10-10 17:45:04', 'C14K1007', 1, 1),
(4546, 4, 'Aerosol Blanco Mate Pinpinov', 'Aerosol Blanco Mate Pinpinov', 1, '7499.00', '19.00', '10500.00', 'Aerosol Blanco Mate Pinpinov', '2024-10-10 17:45:04', '30', 1, 1),
(4547, 4, 'Aerosol Blanco Pinpinov', 'Aerosol Blanco Pinpinov', 0, '6999.00', '19.00', '10000.00', 'Aerosol Blanco Pinpinov', '2024-10-10 17:45:04', '31', 1, 1),
(4548, 4, 'Aerosol Cafe Madera Pintumix', 'Aerosol Cafe Madera Pintumix', 1, '5652.00', '19.00', '9000.00', 'Aerosol Cafe Madera Pintumix', '2024-10-10 17:45:04', '7707180672432', 1, 1),
(4549, 4, 'Aerosol Magneta Fluorecemte Pintumix', 'Aerosol Magneta Fluorecemte Pintumix', 2, '11100.00', '19.00', '16000.00', 'Aerosol Magneta Fluorecemte Pintumix', '2024-10-10 17:45:04', '7707180671084', 1, 1),
(4550, 4, 'Aerosol Naranja Fluorecente Pintumix', 'Aerosol Naranja Fluorecente Pintumix', 0, '9103.00', '19.00', '14000.00', 'Aerosol Naranja Fluorecente Pintumix', '2024-10-10 17:45:04', '7707180672548', 1, 1),
(4551, 4, 'Aerosol Naranja Mercury', 'Aerosol Naranja Mercury', 6, '5200.00', '19.00', '8000.00', 'Aerosol Naranja Mercury', '2024-10-10 17:45:04', '7707692862871', 1, 1),
(4552, 4, 'Aerosol Naranja Pintumix', 'Aerosol Naranja Pintumix', 0, '6949.00', '19.00', '10000.00', 'Aerosol Naranja Pintumix', '2024-10-10 17:45:04', '7707180672463', 1, 1),
(4553, 4, 'Aerosol Negro Brillante Imdico 400ml', 'Aerosol Negro Brillante Imdico 400ml', 0, '6247.00', '19.00', '9000.00', 'Aerosol Negro Brillante Imdico 400ml', '2024-10-10 17:45:04', '155', 1, 1),
(4554, 4, 'Aerosol Negro Mate Pintumix', 'Aerosol Negro Mate Pintumix', 0, '6949.00', '19.00', '10000.00', 'Aerosol Negro Mate Pintumix', '2024-10-10 17:45:04', '7707180672340', 1, 1),
(4555, 4, 'Aerosol Oro Cadillac Pintumix', 'Aerosol Oro Cadillac Pintumix', 3, '8151.00', '19.00', '13000.00', 'Aerosol Oro Cadillac Pintumix', '2024-10-10 17:45:04', '7707180672449', 1, 1),
(4556, 4, 'Aerosol Rojo Brillante Pintu Mix', 'Aerosol Rojo Brillante Pintu Mix', 0, '6949.00', '19.00', '10000.00', 'Aerosol Rojo Brillante Pintu Mix', '2024-10-10 17:45:04', '7707180672395', 1, 1),
(4557, 4, 'Aerosol Rojo Mercury', 'Aerosol Rojo Mercury', 5, '5200.00', '19.00', '8000.00', 'Aerosol Rojo Mercury', '2024-10-10 17:45:04', '7707692860839', 1, 1),
(4558, 4, 'Aerosol Transparente Mate Pintumix', 'Aerosol Transparente Mate Pintumix', 2, '6949.00', '19.00', '10000.00', 'Aerosol Transparente Mate Pintumix', '2024-10-10 17:45:04', '7707180672470', 1, 1),
(4559, 4, 'Aerosol Uduke Anyicorrosivo Gris 400ml', 'Aerosol Uduke Anyicorrosivo Gris 400ml', 9, '5850.00', '19.00', '8000.00', 'Aerosol Uduke Anyicorrosivo Gris 400ml', '2024-10-10 17:45:04', '6973653172483', 1, 1),
(4560, 4, 'Aerosol Uduke Azul Traslucido Oscuro Ht40200', 'Aerosol Uduke Azul Traslucido Oscuro Ht40200', 6, '5850.00', '19.00', '9500.00', 'Aerosol Uduke Azul Traslucido Oscuro Ht40200', '2024-10-10 17:45:04', '6973653172216', 1, 1),
(4561, 4, 'Aerosol Uduke Naranja Amarillo 400ml', 'Aerosol Uduke Naranja Amarillo 400ml', 5, '5850.00', '19.00', '8000.00', 'Aerosol Uduke Naranja Amarillo 400ml', '2024-10-10 17:45:04', '6973653172285', 1, 1),
(4562, 4, 'Aerosol Uduke Negro Brillante 400ml (39)(HT40208)', 'Aerosol Uduke Negro Brillante 400ml (39)(HT40208)', 9, '5850.00', '19.00', '9500.00', 'Aerosol Uduke Negro Brillante 400ml (39)(HT40208)', '2024-10-10 17:45:04', '6973653171011', 1, 1),
(4563, 4, 'Aerosol Verde Fresco Pintumix', 'Aerosol Verde Fresco Pintumix', 3, '5652.00', '19.00', '9500.00', 'Aerosol Verde Fresco Pintumix', '2024-10-10 17:45:04', '7707180672418', 1, 1),
(4564, 4, 'Aerosol Verde Jade Pintumix', 'Aerosol Verde Jade Pintumix', 2, '5652.00', '19.00', '9500.00', 'Aerosol Verde Jade Pintumix', '2024-10-10 17:45:04', '7707180671046', 1, 1),
(4565, 4, 'Aerosol Violeta Pintumix', 'Aerosol Violeta Pintumix', 2, '6949.00', '19.00', '10000.00', 'Aerosol Violeta Pintumix', '2024-10-10 17:45:04', '7707180671077', 1, 1),
(4566, 4, 'Aersol Azul Diamante Pintumix', 'Aersol Azul Diamante Pintumix', 2, '5652.00', '19.00', '9500.00', 'Aersol Azul Diamante Pintumix', '2024-10-10 17:45:04', '7707180672401', 1, 1),
(4567, 4, 'Agua Stop Boccherini', 'Agua Stop Boccherini', 3, '5600.00', '19.00', '8500.00', 'Agua Stop Boccherini', '2024-10-10 17:45:04', '7707180672203', 1, 1),
(4568, 4, 'Agua Stop Dymaxter', 'Agua Stop Dymaxter', 8, '3150.00', '19.00', '4800.00', 'Agua Stop Dymaxter', '2024-10-10 17:45:04', '2516', 1, 1),
(4569, 4, 'Agua Stop Grival Sello Lengueta', 'Agua Stop Grival Sello Lengueta', 4, '9050.00', '19.00', '11500.00', 'Agua Stop Grival Sello Lengueta', '2024-10-10 17:45:04', '23503331', 1, 1),
(4570, 4, 'Agua Stop Sello Lengueta Universal Gri Plast', 'Agua Stop Sello Lengueta Universal Gri Plast', 0, '3900.00', '19.00', '6500.00', 'Agua Stop Sello Lengueta Universal Gri Plast', '2024-10-10 17:45:04', '7709251615287', 1, 1),
(4571, 4, 'Alicate Amarillo 8 1/2', 'Alicate Amarillo 8 1/2', 2, '8750.00', '19.00', '12200.00', 'Alicate Amarillo 8 1/2', '2024-10-10 17:45:04', '1962A', 1, 1),
(4572, 4, 'Alicate Articulado De Extension 8', 'Alicate Articulado De Extension 8', 2, '13299.00', '19.00', '19000.00', 'Alicate Articulado De Extension 8', '2024-10-10 17:45:04', '7707766457040', 1, 1),
(4573, 4, 'Alicate Combinacion Punta Plana10', 'Alicate Combinacion Punta Plana10', 3, '17400.00', '19.00', '24900.00', 'Alicate Combinacion Punta Plana10', '2024-10-10 17:45:04', '7707766455053', 1, 1),
(4574, 4, 'Alicate Corta Frio 8´´ Pretul', 'Alicate Corta Frio 8´´ Pretul', 2, '12492.00', '19.00', '17900.00', 'Alicate Corta Frio 8´´ Pretul', '2024-10-10 17:45:04', '7501206646182', 1, 1),
(4575, 4, 'Alicate De Combinacion Punta Plana 8', 'Alicate De Combinacion Punta Plana 8', 2, '12099.00', '19.00', '17300.00', 'Alicate De Combinacion Punta Plana 8', '2024-10-10 17:45:04', '7707766459280', 1, 1),
(4576, 4, 'Alicate De Electricista 7´´ Pretul', 'Alicate De Electricista 7´´ Pretul', 2, '12492.00', '19.00', '18000.00', 'Alicate De Electricista 7´´ Pretul', '2024-10-10 17:45:04', '7501206643464', 1, 1),
(4577, 4, 'Alicate Electricista 8´´ Pretul', 'Alicate Electricista 8´´ Pretul', 1, '13452.00', '19.00', '19500.00', 'Alicate Electricista 8´´ Pretul', '2024-10-10 17:45:04', '7501206643174', 1, 1),
(4578, 4, 'Alicate Electricista Boccherini 8', 'Alicate Electricista Boccherini 8', 0, '10099.00', '19.00', '14500.00', 'Alicate Electricista Boccherini 8', '2024-10-10 17:45:04', '7707180678564', 1, 1),
(4579, 4, 'Alicate Para Electricista Boccherini 7', 'Alicate Para Electricista Boccherini 7', 0, '9599.00', '19.00', '13800.00', 'Alicate Para Electricista Boccherini 7', '2024-10-10 17:45:04', '7707180678557', 1, 1),
(4580, 4, 'Alicate Punta Curva', 'Alicate Punta Curva', 1, '11800.00', '19.00', '16900.00', 'Alicate Punta Curva', '2024-10-10 17:45:04', '7707766452571', 1, 1),
(4581, 4, 'Alicate Punta De Aguja 6', 'Alicate Punta De Aguja 6', 0, '7099.00', '19.00', '11000.00', 'Alicate Punta De Aguja 6', '2024-10-10 17:45:04', '7707766455770', 1, 1),
(4582, 4, 'Amarra Plastica Blanca 2.5x100', 'Amarra Plastica Blanca 2.5x100', 11, '1199.00', '19.00', '1800.00', 'Amarra Plastica Blanca 2.5x100', '2024-10-10 17:45:04', '7707766458801', 1, 1),
(4583, 4, 'Amarra Plastica Blanca 2.5x150', 'Amarra Plastica Blanca 2.5x150', 9, '1800.00', '19.00', '2600.00', 'Amarra Plastica Blanca 2.5x150', '2024-10-10 17:45:04', '7707766450935', 1, 1),
(4584, 4, 'Amarra Plastica Blanca 3.5x200', 'Amarra Plastica Blanca 3.5x200', 3, '3499.00', '19.00', '5000.00', 'Amarra Plastica Blanca 3.5x200', '2024-10-10 17:45:04', '7707766451024', 1, 1),
(4585, 4, 'Amarra Plastica Blanca 3.5x250', 'Amarra Plastica Blanca 3.5x250', 3, '4299.00', '19.00', '6200.00', 'Amarra Plastica Blanca 3.5x250', '2024-10-10 17:45:04', '7707766451666', 1, 1),
(4586, 4, 'Amarra Plastica Blanca 3.5x300', 'Amarra Plastica Blanca 3.5x300', 0, '5000.00', '19.00', '7500.00', 'Amarra Plastica Blanca 3.5x300', '2024-10-10 17:45:04', '7707766452670', 1, 1),
(4587, 4, 'Amarra Plastica Negra 2.5x100', 'Amarra Plastica Negra 2.5x100', 9, '1199.00', '19.00', '1800.00', 'Amarra Plastica Negra 2.5x100', '2024-10-10 17:45:04', '7707766458740', 1, 1),
(4588, 4, 'Amarra Plastica Negra 2.5x150', 'Amarra Plastica Negra 2.5x150', 8, '1800.00', '19.00', '2600.00', 'Amarra Plastica Negra 2.5x150', '2024-10-10 17:45:04', '7707766456623', 1, 1),
(4589, 4, 'Amarra Plastica Negra 3.5x200', 'Amarra Plastica Negra 3.5x200', 0, '3499.00', '19.00', '5000.00', 'Amarra Plastica Negra 3.5x200', '2024-10-10 17:45:04', '7707766453240', 1, 1),
(4590, 4, 'Amarra Plastica Negra 3.5x250', 'Amarra Plastica Negra 3.5x250', 1, '4299.00', '19.00', '6200.00', 'Amarra Plastica Negra 3.5x250', '2024-10-10 17:45:04', '7707766458214', 1, 1),
(4591, 4, 'Amarra Plastica Uduke Negra 3.6x300mm', 'Amarra Plastica Uduke Negra 3.6x300mm', 3, '3999.00', '19.00', '5500.00', 'Amarra Plastica Uduke Negra 3.6x300mm', '2024-10-10 17:45:04', '6973653176689', 1, 1),
(4592, 4, 'Angulos PVC', 'Angulos PVC', 8, '0.00', '19.00', '2400.00', 'Angulos PVC', '2024-10-10 17:45:04', '12247', 1, 1),
(4593, 4, 'Anticorrosivo 1/4 Blanco Bler', 'Anticorrosivo 1/4 Blanco Bler', 2, '17173.00', '19.00', '20600.00', 'Anticorrosivo 1/4 Blanco Bler', '2024-10-10 17:45:04', '6500006682483', 1, 1),
(4594, 4, 'Anticorrosivo Blanco Galon Bler', 'Anticorrosivo Blanco Galon Bler', 10, '59794.00', '19.00', '71700.00', 'Anticorrosivo Blanco Galon Bler', '2024-10-10 17:45:04', '107', 1, 1),
(4595, 4, 'Anticorrosivo Gris 1/4 Bler', 'Anticorrosivo Gris 1/4 Bler', 8, '14008.00', '19.00', '19500.00', 'Anticorrosivo Gris 1/4 Bler', '2024-10-10 17:45:04', '7708619524728', 1, 1),
(4596, 4, 'Anticorrosivo Gris Galon Bler', 'Anticorrosivo Gris Galon Bler', 8, '50725.00', '19.00', '63000.00', 'Anticorrosivo Gris Galon Bler', '2024-10-10 17:45:04', '6506710690806', 1, 1),
(4597, 4, 'Arbol De Entrada Boccherini', 'Arbol De Entrada Boccherini', 0, '10900.00', '19.00', '23000.00', 'Arbol De Entrada Boccherini', '2024-10-10 17:45:04', '7707180671473', 1, 1),
(4598, 4, 'Arbol E Entrada Osiris', 'Arbol E Entrada Osiris', 0, '3380.00', '19.00', '5000.00', 'Arbol E Entrada Osiris', '2024-10-10 17:45:04', '2518', 1, 1),
(4599, 4, 'Asiento Sanitario Blanco Boccherini', 'Asiento Sanitario Blanco Boccherini', 0, '17850.00', '19.00', '27000.00', 'Asiento Sanitario Blanco Boccherini', '2024-10-10 17:45:04', '7707180670360', 1, 1),
(4600, 4, 'Aspersor Giratorio 3 Brazos Aluminio Truper Surtidor Agua', 'Aspersor Giratorio 3 Brazos Aluminio Truper Surtidor Agua', 1, '18000.00', '19.00', '24500.00', 'Aspersor Giratorio 3 Brazos Aluminio Truper Surtidor Agua', '2024-10-10 17:45:04', '7501206655320', 1, 1),
(4601, 4, 'Aspersor Giratorio 3 Brazos Pretul Surtidor Agua', 'Aspersor Giratorio 3 Brazos Pretul Surtidor Agua', 2, '9900.00', '19.00', '14000.00', 'Aspersor Giratorio 3 Brazos Pretul Surtidor Agua', '2024-10-10 17:45:04', '7501206655313', 1, 1),
(4602, 4, 'Aspersor Plastico Con Estaca De 2 Vias Surtidor Agua Pretul', 'Aspersor Plastico Con Estaca De 2 Vias Surtidor Agua Pretul', 1, '11000.00', '19.00', '14500.00', 'Aspersor Plastico Con Estaca De 2 Vias Surtidor Agua Pretul', '2024-10-10 17:45:04', '7501206695692', 1, 1),
(4603, 4, 'Aspersor Plastico Estaca Metalica Pretul Surtidor Agua', 'Aspersor Plastico Estaca Metalica Pretul Surtidor Agua', 2, '10000.00', '19.00', '13500.00', 'Aspersor Plastico Estaca Metalica Pretul Surtidor Agua', '2024-10-10 17:45:04', '7501206695685', 1, 1),
(4604, 4, 'Aspersor Plastico Pretul Surtidor Agua', 'Aspersor Plastico Pretul Surtidor Agua', 2, '5500.00', '19.00', '8000.00', 'Aspersor Plastico Pretul Surtidor Agua', '2024-10-10 17:45:04', '7501206695678', 1, 1),
(4605, 4, 'Aspersor Plastico Uduke', 'Aspersor Plastico Uduke', 2, '4344.00', '19.00', '6500.00', 'Aspersor Plastico Uduke', '2024-10-10 17:45:04', '6973877761104', 1, 1),
(4606, 4, 'Astronauta Star Light', 'Astronauta Star Light', 2, '105000.00', '19.00', '105000.00', 'Astronauta Star Light', '2024-10-10 17:45:04', '9693', 1, 1),
(4607, 4, 'BIULTO EXTRA FUERTE PEGANTE CERAMICA INTERIORES  25KL', 'BIULTO EXTRA FUERTE PEGANTE CERAMICA INTERIORES  25KL', 12, '14590.00', '19.00', '18200.00', 'BIULTO EXTRA FUERTE PEGANTE CERAMICA INTERIORES  25KL', '2024-10-10 17:45:04', '5404', 1, 1),
(4608, 4, 'BOQUILLA LATEX PORCLANATO Y CERAMICA', 'BOQUILLA LATEX PORCLANATO Y CERAMICA', 10, '6950.00', '19.00', '8500.00', 'BOQUILLA LATEX PORCLANATO Y CERAMICA', '2024-10-10 17:45:04', '60303', 1, 1),
(4609, 4, 'BOTELLA REPUESTO JABONERA BCHYW 345 46', 'BOTELLA REPUESTO JABONERA BCHYW 345 46', 2, '7800.00', '19.00', '11500.00', 'BOTELLA REPUESTO JABONERA BCHYW 345 46', '2024-10-10 17:45:04', '7707180676331', 1, 1),
(4610, 4, 'BOTONERA CORONA', 'BOTONERA CORONA', 1, '20000.00', '19.00', '28500.00', 'BOTONERA CORONA', '2024-10-10 17:45:04', '7706157618558', 1, 1),
(4611, 4, 'BROCA METAL 1/8 PULGADAS', 'BROCA METAL 1/8 PULGADAS', 4, '2100.00', '19.00', '2900.00', 'BROCA METAL 1/8 PULGADAS', '2024-10-10 17:45:04', '11118', 1, 1),
(4612, 4, 'BROCA METAL 3/8 PULGADAS', 'BROCA METAL 3/8 PULGADAS', 2, '14000.00', '19.00', '17500.00', 'BROCA METAL 3/8 PULGADAS', '2024-10-10 17:45:04', '11150', 1, 1),
(4613, 4, 'Balde Blanco Almendra T2 Corona', 'Balde Blanco Almendra T2 Corona', 1, '87000.00', '19.00', '135000.00', 'Balde Blanco Almendra T2 Corona', '2024-10-10 17:45:04', '7705389008618', 1, 1),
(4614, 4, 'Balde Blanco Hueso T2 Corona', 'Balde Blanco Hueso T2 Corona', 1, '87543.00', '19.00', '134000.00', 'Balde Blanco Hueso T2 Corona', '2024-10-10 17:45:04', '7705389008632', 1, 1),
(4615, 4, 'Balde De Construccion Arket Ht1067', 'Balde De Construccion Arket Ht1067', 11, '4600.00', '19.00', '5800.00', 'Balde De Construccion Arket Ht1067', '2024-10-10 17:45:04', '5913459', 1, 1),
(4616, 4, 'Balde Professional Blanca T1 Corona', 'Balde Professional Blanca T1 Corona', 5, '108079.00', '19.00', '160000.00', 'Balde Professional Blanca T1 Corona', '2024-10-10 17:45:04', '7705389011410', 1, 1),
(4617, 4, 'Balde Textuco Interior Corona', 'Balde Textuco Interior Corona', 2, '34045.00', '19.00', '49000.00', 'Balde Textuco Interior Corona', '2024-10-10 17:45:04', '7705389012011', 1, 1),
(4618, 4, 'Balde Total Blanca T2 Corona', 'Balde Total Blanca T2 Corona', 3, '78559.00', '19.00', '120000.00', 'Balde Total Blanca T2 Corona', '2024-10-10 17:45:04', '7705389011496', 1, 1),
(4619, 4, 'Bandeja Para Pintar Negra', 'Bandeja Para Pintar Negra', 0, '4302.00', '19.00', '5800.00', 'Bandeja Para Pintar Negra', '2024-10-10 17:45:04', '615780', 1, 1),
(4620, 4, 'Bascula Digital Boccherini', 'Bascula Digital Boccherini', 5, '14800.00', '19.00', '25000.00', 'Bascula Digital Boccherini', '2024-10-10 17:45:04', '7707766458252', 1, 1),
(4621, 4, 'Baston S Doble', 'Baston S Doble', 19, '26462.00', '19.00', '37900.00', 'Baston S Doble', '2024-10-10 17:45:04', '74965', 1, 1),
(4622, 4, 'Benjamin Beige Uduke HT20331', 'Benjamin Beige Uduke HT20331', 8, '2050.00', '19.00', '3000.00', 'Benjamin Beige Uduke HT20331', '2024-10-10 17:45:04', 'B1', 1, 1),
(4623, 4, 'Benjamin Negro Uduke HT20667', 'Benjamin Negro Uduke HT20667', 8, '2050.00', '19.00', '3000.00', 'Benjamin Negro Uduke HT20667', '2024-10-10 17:45:04', 'BEN12', 1, 1),
(4624, 4, 'Bisagra De Acero Rectangular 3x 2  Hermex', 'Bisagra De Acero Rectangular 3x 2  Hermex', 18, '2125.00', '19.00', '3000.00', 'Bisagra De Acero Rectangular 3x 2  Hermex', '2024-10-10 17:45:04', '7501206669068', 1, 1),
(4625, 4, 'Bisagra Rectangular 1-1/2  X 1 3/16 Hermex', 'Bisagra Rectangular 1-1/2  X 1 3/16 Hermex', 20, '860.00', '19.00', '1300.00', 'Bisagra Rectangular 1-1/2  X 1 3/16 Hermex', '2024-10-10 17:45:04', '7501206669037', 1, 1),
(4626, 4, 'Bisagra Rectangular 2-1/2 X 1 1/2 Hermex', 'Bisagra Rectangular 2-1/2 X 1 1/2 Hermex', 10, '1011.00', '19.00', '1500.00', 'Bisagra Rectangular 2-1/2 X 1 1/2 Hermex', '2024-10-10 17:45:04', '7501206669044', 1, 1),
(4627, 4, 'Bisagra Rectangular 21/2 X 1 5/8 Hermex', 'Bisagra Rectangular 21/2 X 1 5/8 Hermex', 10, '1517.00', '19.00', '2000.00', 'Bisagra Rectangular 21/2 X 1 5/8 Hermex', '2024-10-10 17:45:04', '7501206669051', 1, 1),
(4628, 4, 'Bisturi 18mm100mm Caucho Total HT-T511815', 'Bisturi 18mm100mm Caucho Total HT-T511815', 2, '4300.00', '19.00', '6000.00', 'Bisturi 18mm100mm Caucho Total HT-T511815', '2024-10-10 17:45:04', '6941639857266', 1, 1),
(4629, 4, 'Bisturi 9mm Plastico Pretul', 'Bisturi 9mm Plastico Pretul', 2, '1153.00', '19.00', '1800.00', 'Bisturi 9mm Plastico Pretul', '2024-10-10 17:45:04', '7501206641170', 1, 1),
(4630, 4, 'Bisturi Metalico Vharbor En Blister', 'Bisturi Metalico Vharbor En Blister', 4, '3200.00', '19.00', '4500.00', 'Bisturi Metalico Vharbor En Blister', '2024-10-10 17:45:04', '6973877761005', 1, 1),
(4631, 4, 'Bisturi Plastico Boccherini', 'Bisturi Plastico Boccherini', 0, '1500.00', '19.00', '2500.00', 'Bisturi Plastico Boccherini', '2024-10-10 17:45:04', '7707180678953', 1, 1),
(4632, 4, 'Bisturi Profesional Con Alma Metálica  Y Grip 18mm', 'Bisturi Profesional Con Alma Metálica  Y Grip 18mm', 3, '9100.00', '19.00', '11900.00', 'Bisturi Profesional Con Alma Metálica  Y Grip 18mm', '2024-10-10 17:45:04', '7501206645512', 1, 1),
(4633, 4, 'Bisturi aluminio BOCCHERINI', 'Bisturi aluminio BOCCHERINI', 4, '6000.00', '19.00', '8500.00', 'Bisturi aluminio BOCCHERINI', '2024-10-10 17:45:04', '7707180678946', 1, 1),
(4634, 4, 'Bocha 3 Cerda Mona B/Pintor', 'Bocha 3 Cerda Mona B/Pintor', 3, '5950.00', '19.00', '8100.00', 'Bocha 3 Cerda Mona B/Pintor', '2024-10-10 17:45:04', '7702155013944', 1, 1),
(4635, 4, 'Bomba Fumigadora 2lts Naranja', 'Bomba Fumigadora 2lts Naranja', 0, '15000.00', '19.00', '21500.00', 'Bomba Fumigadora 2lts Naranja', '2024-10-10 17:45:04', '2515', 1, 1),
(4636, 4, 'Bomba Fumigadora Manual Uduke 8 Lts 270 Onzas Ht20100', 'Bomba Fumigadora Manual Uduke 8 Lts 270 Onzas Ht20100', 1, '41800.00', '19.00', '58500.00', 'Bomba Fumigadora Manual Uduke 8 Lts 270 Onzas Ht20100', '2024-10-10 17:45:04', '6973653173039', 1, 1),
(4637, 4, 'Bomba Funigadora 1.5litros Fermetal', 'Bomba Funigadora 1.5litros Fermetal', 1, '9944.00', '19.00', '14500.00', 'Bomba Funigadora 1.5litros Fermetal', '2024-10-10 17:45:04', 'C01', 1, 1),
(4638, 4, 'Bomba Sanitario Plastica', 'Bomba Sanitario Plastica', 10, '2875.00', '19.00', '4500.00', 'Bomba Sanitario Plastica', '2024-10-10 17:45:04', '2539', 1, 1),
(4639, 4, 'Bombillo C211 Fermetal', 'Bombillo C211 Fermetal', 4, '4116.00', '19.00', '6000.00', 'Bombillo C211 Fermetal', '2024-10-10 17:45:04', '7593826000703', 1, 1),
(4640, 4, 'Bombillo C216 Fermetal', 'Bombillo C216 Fermetal', 4, '5011.00', '19.00', '7500.00', 'Bombillo C216 Fermetal', '2024-10-10 17:45:04', '7593826000734', 1, 1),
(4641, 4, 'Bombillo En Led Colores Lexmana', 'Bombillo En Led Colores Lexmana', 2, '1300.00', '19.00', '2500.00', 'Bombillo En Led Colores Lexmana', '2024-10-10 17:45:04', '2067', 1, 1),
(4642, 4, 'Bombillo Led 12w Mercury', 'Bombillo Led 12w Mercury', 9, '3950.00', '19.00', '5900.00', 'Bombillo Led 12w Mercury', '2024-10-10 17:45:04', '7707692862161', 1, 1),
(4643, 4, 'Bombillo Led 12w Vatio', 'Bombillo Led 12w Vatio', 7, '2499.00', '19.00', '3600.00', 'Bombillo Led 12w Vatio', '2024-10-10 17:45:04', '7707499407640', 1, 1),
(4644, 4, 'Bombillo Led 15w Matasancudos', 'Bombillo Led 15w Matasancudos', 2, '11850.00', '19.00', '16600.00', 'Bombillo Led 15w Matasancudos', '2024-10-10 17:45:04', '6906020200618', 1, 1),
(4645, 4, 'Bombillo Led 20w Mercury', 'Bombillo Led 20w Mercury', 0, '8400.00', '19.00', '12000.00', 'Bombillo Led 20w Mercury', '2024-10-10 17:45:04', '7707692869214', 1, 1),
(4646, 4, 'Bombillo Led 30W Recargable Osblack', 'Bombillo Led 30W Recargable Osblack', 4, '21750.00', '19.00', '31000.00', 'Bombillo Led 30W Recargable Osblack', '2024-10-10 17:45:04', '6906020120817', 1, 1),
(4647, 4, 'Bombillo Led 30w', 'Bombillo Led 30w', 3, '16700.00', '19.00', '22500.00', 'Bombillo Led 30w', '2024-10-10 17:45:04', '6973653171318', 1, 1),
(4648, 4, 'Bombillo Led 30w Vatio', 'Bombillo Led 30w Vatio', 0, '17999.00', '19.00', '25700.00', 'Bombillo Led 30w Vatio', '2024-10-10 17:45:04', '22008', 1, 1),
(4649, 4, 'Bombillo Led 3w Uduke Ht80394', 'Bombillo Led 3w Uduke Ht80394', 8, '2315.00', '19.00', '3400.00', 'Bombillo Led 3w Uduke Ht80394', '2024-10-10 17:45:04', '6973653171219', 1, 1),
(4650, 4, 'Bombillo Led 50w Vatio', 'Bombillo Led 50w Vatio', 1, '21999.00', '19.00', '31500.00', 'Bombillo Led 50w Vatio', '2024-10-10 17:45:04', '22010', 1, 1),
(4651, 4, 'Bombillo Led 5w Uduke Ht80395', 'Bombillo Led 5w Uduke Ht80395', 10, '3015.00', '19.00', '4500.00', 'Bombillo Led 5w Uduke Ht80395', '2024-10-10 17:45:04', '6973653171226', 1, 1),
(4652, 4, 'Bombillo Led 7w Uduke Ht80396', 'Bombillo Led 7w Uduke Ht80396', 8, '3077.00', '19.00', '4800.00', 'Bombillo Led 7w Uduke Ht80396', '2024-10-10 17:45:04', '6973653171233', 1, 1),
(4653, 4, 'Bombillo Led 9w Uduke Ht80397', 'Bombillo Led 9w Uduke Ht80397', 13, '3000.00', '19.00', '3800.00', 'Bombillo Led 9w Uduke Ht80397', '2024-10-10 17:45:04', '6973653171240', 1, 1),
(4654, 4, 'Bombillo Led 9w Vatio', 'Bombillo Led 9w Vatio', 10, '1600.00', '19.00', '3600.00', 'Bombillo Led 9w Vatio', '2024-10-10 17:45:04', '7707499401082', 1, 1),
(4655, 4, 'Bombillo Led C214 Fermetal', 'Bombillo Led C214 Fermetal', 4, '5011.00', '19.00', '7500.00', 'Bombillo Led C214 Fermetal', '2024-10-10 17:45:04', '7593826000727', 1, 1),
(4656, 4, 'Bombillo Led Tp Pera Vintage 4w Vatio', 'Bombillo Led Tp Pera Vintage 4w Vatio', 8, '8000.00', '19.00', '11500.00', 'Bombillo Led Tp Pera Vintage 4w Vatio', '2024-10-10 17:45:04', '7707499405356', 1, 1),
(4657, 4, 'Bombillo Transparente 100watt Fenix', 'Bombillo Transparente 100watt Fenix', 1, '987.00', '19.00', '2000.00', 'Bombillo Transparente 100watt Fenix', '2024-10-10 17:45:04', '7458692023402', 1, 1),
(4658, 4, 'Bombillo Vintage Ambar 2w Infinita', 'Bombillo Vintage Ambar 2w Infinita', 4, '5250.00', '19.00', '7300.00', 'Bombillo Vintage Ambar 2w Infinita', '2024-10-10 17:45:04', '7708827478295', 1, 1),
(4659, 4, 'Botonera Superior Boccherini', 'Botonera Superior Boccherini', 3, '7399.00', '19.00', '11000.00', 'Botonera Superior Boccherini', '2024-10-10 17:45:04', '7707180672814', 1, 1),
(4660, 4, 'Botonera Valvula Doble Descarga Boccherini', 'Botonera Valvula Doble Descarga Boccherini', 2, '9099.00', '19.00', '13000.00', 'Botonera Valvula Doble Descarga Boccherini', '2024-10-10 17:45:04', '7707180672821', 1, 1),
(4661, 4, 'Brcha 3 Piezas Total', 'Brcha 3 Piezas Total', 3, '5350.00', '19.00', '7500.00', 'Brcha 3 Piezas Total', '2024-10-10 17:45:04', '6941639847175', 1, 1),
(4662, 4, 'Breaker 15am Luminex', 'Breaker 15am Luminex', 5, '9500.00', '19.00', '13800.00', 'Breaker 15am Luminex', '2024-10-10 17:45:04', '17702089117845', 1, 1),
(4663, 4, 'Breaker 1p 15am 120/240v', 'Breaker 1p 15am 120/240v', 4, '8848.00', '19.00', '13000.00', 'Breaker 1p 15am 120/240v', '2024-10-10 17:45:04', '7708353535042', 1, 1),
(4664, 4, 'Breaker 20AMP Uduke Ht60036', 'Breaker 20AMP Uduke Ht60036', 0, '3939.00', '19.00', '5700.00', 'Breaker 20AMP Uduke Ht60036', '2024-10-10 17:45:04', '6973653172629', 1, 1),
(4665, 4, 'Breaker 30AMP Uduke Ht60037', 'Breaker 30AMP Uduke Ht60037', 1, '3939.00', '19.00', '5700.00', 'Breaker 30AMP Uduke Ht60037', '2024-10-10 17:45:04', '6973653172636', 1, 1),
(4666, 4, 'Breaker 40AMP Uduke Ht60038', 'Breaker 40AMP Uduke Ht60038', 3, '3939.00', '19.00', '5700.00', 'Breaker 40AMP Uduke Ht60038', '2024-10-10 17:45:04', '6973653172643', 1, 1),
(4667, 4, 'Breaker 50 AMP Uduke Ht60039', 'Breaker 50 AMP Uduke Ht60039', 3, '3939.00', '19.00', '5700.00', 'Breaker 50 AMP Uduke Ht60039', '2024-10-10 17:45:04', '6973653172650', 1, 1),
(4668, 4, 'Broca 3/8 Concreto Unidad Boccherini', 'Broca 3/8 Concreto Unidad Boccherini', 2, '1050.00', '19.00', '1500.00', 'Broca 3/8 Concreto Unidad Boccherini', '2024-10-10 17:45:04', '770718067973', 1, 1),
(4669, 4, 'Broca Acero 1/16 Truper', 'Broca Acero 1/16 Truper', 10, '2124.00', '19.00', '2700.00', 'Broca Acero 1/16 Truper', '2024-10-10 17:45:04', '17501206645915', 1, 1),
(4670, 4, 'Broca Acero 11/64 Truper', 'Broca Acero 11/64 Truper', 10, '2933.00', '19.00', '3900.00', 'Broca Acero 11/64 Truper', '2024-10-10 17:45:04', '7501206645987', 1, 1),
(4671, 4, 'Broca Acero 3/32 Truper', 'Broca Acero 3/32 Truper', 10, '2124.00', '19.00', '2700.00', 'Broca Acero 3/32 Truper', '2024-10-10 17:45:04', '17501206645939', 1, 1),
(4672, 4, 'Broca Acero 5/16 Truper', 'Broca Acero 5/16 Truper', 10, '2124.00', '19.00', '2700.00', 'Broca Acero 5/16 Truper', '2024-10-10 17:45:04', '7501206645925', 1, 1),
(4673, 4, 'Broca Acero 5/32', 'Broca Acero 5/32', 10, '2528.00', '19.00', '3500.00', 'Broca Acero 5/32', '2024-10-10 17:45:04', '17501206645977', 1, 1),
(4674, 4, 'Broca Acero 7/64 Truper', 'Broca Acero 7/64 Truper', 10, '2124.00', '19.00', '2700.00', 'Broca Acero 7/64 Truper', '2024-10-10 17:45:04', '17501206645946', 1, 1),
(4675, 4, 'Broca Acero 9/64 Truper', 'Broca Acero 9/64 Truper', 10, '2326.00', '19.00', '3000.00', 'Broca Acero 9/64 Truper', '2024-10-10 17:45:04', '17501206645960', 1, 1),
(4676, 4, 'Broca Concreto 3/8 Truper', 'Broca Concreto 3/8 Truper', 10, '4551.00', '19.00', '6000.00', 'Broca Concreto 3/8 Truper', '2024-10-10 17:45:04', '17501206634537', 1, 1),
(4677, 4, 'Broca Concreto o Muro 1/4 x 6 Truper', 'Broca Concreto o Muro 1/4 x 6 Truper', 10, '2649.00', '19.00', '3800.00', 'Broca Concreto o Muro 1/4 x 6 Truper', '2024-10-10 17:45:04', 'BRC1', 1, 1),
(4678, 4, 'Broca Concreto o Muro 5/16 x 6 Truper', 'Broca Concreto o Muro 5/16 x 6 Truper', 9, '3826.00', '19.00', '5200.00', 'Broca Concreto o Muro 5/16 x 6 Truper', '2024-10-10 17:45:04', 'BCR2', 1, 1),
(4679, 4, 'Broca Contreto o Muro 3/16 x 6 Truper', 'Broca Contreto o Muro 3/16 x 6 Truper', 3, '2257.00', '19.00', '3500.00', 'Broca Contreto o Muro 3/16 x 6 Truper', '2024-10-10 17:45:04', 'BCR3', 1, 1),
(4680, 4, 'Broca De Hierro 1/8 Pq X 10', 'Broca De Hierro 1/8 Pq X 10', 5, '3499.00', '19.00', '5500.00', 'Broca De Hierro 1/8 Pq X 10', '2024-10-10 17:45:04', '7707180679783', 1, 1),
(4681, 4, 'Broca De Muro Dewalt 1/8 X 2 1/2', 'Broca De Muro Dewalt 1/8 X 2 1/2', 3, '2600.00', '19.00', '3300.00', 'Broca De Muro Dewalt 1/8 X 2 1/2', '2024-10-10 17:45:04', '28877379104', 1, 1),
(4682, 4, 'Broca Hierro 3/8 Unidad Boccherini', 'Broca Hierro 3/8 Unidad Boccherini', 0, '1737.00', '19.00', '2500.00', 'Broca Hierro 3/8 Unidad Boccherini', '2024-10-10 17:45:04', '7707180679868', 1, 1),
(4683, 4, 'Broca Para Concreto 1/2paq  Boccherini', 'Broca Para Concreto 1/2paq  Boccherini', 2, '9996.00', '19.00', '15500.00', 'Broca Para Concreto 1/2paq  Boccherini', '2024-10-10 17:45:04', '7707180679745', 1, 1),
(4684, 4, 'Broca Para Concreto 3/8 Pq X10 Unidades Boccherini', 'Broca Para Concreto 3/8 Pq X10 Unidades Boccherini', 1, '9520.00', '19.00', '14700.00', 'Broca Para Concreto 3/8 Pq X10 Unidades Boccherini', '2024-10-10 17:45:04', '7707180679738', 1, 1),
(4685, 4, 'Broca Para Concreto Unid 1/2', 'Broca Para Concreto Unid 1/2', 1, '1999.00', '19.00', '3500.00', 'Broca Para Concreto Unid 1/2', '2024-10-10 17:45:04', '7707180679746', 1, 1),
(4686, 4, 'Broca Para Hierro 1/8 Unid Boccherini', 'Broca Para Hierro 1/8 Unid Boccherini', 5, '349.00', '19.00', '700.00', 'Broca Para Hierro 1/8 Unid Boccherini', '2024-10-10 17:45:04', '7707180679782', 1, 1),
(4687, 4, 'Broca Para Hierro 13/64 Pq X 10 Boccherini', 'Broca Para Hierro 13/64 Pq X 10 Boccherini', 1, '4998.00', '19.00', '7700.00', 'Broca Para Hierro 13/64 Pq X 10 Boccherini', '2024-10-10 17:45:04', '113', 1, 1),
(4688, 4, 'Broca Para Hierro 13/64 Unidad Boccherini', 'Broca Para Hierro 13/64 Unidad Boccherini', 6, '499.00', '19.00', '1000.00', 'Broca Para Hierro 13/64 Unidad Boccherini', '2024-10-10 17:45:04', '114', 1, 1),
(4689, 4, 'Broca Para Hierro 5/64 Paq. Boccherini', 'Broca Para Hierro 5/64 Paq. Boccherini', 1, '1904.00', '19.00', '3000.00', 'Broca Para Hierro 5/64 Paq. Boccherini', '2024-10-10 17:45:04', '7707180679752', 1, 1),
(4690, 4, 'Broca Para Hierro 5/64 Unid Boccherini', 'Broca Para Hierro 5/64 Unid Boccherini', 6, '190.00', '19.00', '500.00', 'Broca Para Hierro 5/64 Unid Boccherini', '2024-10-10 17:45:04', '7707180679751', 1, 1),
(4691, 4, 'Broca Para Hierro 7/32 Unid Boccherini', 'Broca Para Hierro 7/32 Unid Boccherini', 6, '535.00', '19.00', '1500.00', 'Broca Para Hierro 7/32 Unid Boccherini', '2024-10-10 17:45:04', '7707180679837', 1, 1),
(4692, 4, 'Broca Para Hierro 9/64', 'Broca Para Hierro 9/64', 5, '3799.00', '19.00', '6000.00', 'Broca Para Hierro 9/64', '2024-10-10 17:45:04', '7707180679790', 1, 1),
(4693, 4, 'Broca Para Hierro 9/64 Unid Boccherini', 'Broca Para Hierro 9/64 Unid Boccherini', 4, '379.00', '19.00', '800.00', 'Broca Para Hierro 9/64 Unid Boccherini', '2024-10-10 17:45:04', '7707180679791', 1, 1),
(4694, 4, 'Broca Para Madera  1/4´´ Truper', 'Broca Para Madera  1/4´´ Truper', 6, '2017.00', '19.00', '3300.00', 'Broca Para Madera  1/4´´ Truper', '2024-10-10 17:45:04', '7506240688037', 1, 1),
(4695, 4, 'Broca Para Madera 1/2´´ Truper', 'Broca Para Madera 1/2´´ Truper', 5, '5285.00', '19.00', '7600.00', 'Broca Para Madera 1/2´´ Truper', '2024-10-10 17:45:04', '7506240688082', 1, 1),
(4696, 4, 'Broca Para Madera 1/8´´ Truper', 'Broca Para Madera 1/8´´ Truper', 5, '1633.00', '19.00', '2400.00', 'Broca Para Madera 1/8´´ Truper', '2024-10-10 17:45:04', '7506240687993', 1, 1),
(4697, 4, 'Broca Para Madera 3/16´´ Truper', 'Broca Para Madera 3/16´´ Truper', 6, '1633.00', '19.00', '3000.00', 'Broca Para Madera 3/16´´ Truper', '2024-10-10 17:45:04', '7506240688013', 1, 1),
(4698, 4, 'Broca Para Madera 3/8´´ Truper', 'Broca Para Madera 3/8´´ Truper', 3, '2768.00', '19.00', '4000.00', 'Broca Para Madera 3/8´´ Truper', '2024-10-10 17:45:04', '7506240688068', 1, 1),
(4699, 4, 'Broca Para Madera 5/16´´', 'Broca Para Madera 5/16´´', 6, '2402.00', '19.00', '3500.00', 'Broca Para Madera 5/16´´', '2024-10-10 17:45:04', '7506240688051', 1, 1),
(4700, 4, 'Broca Para Madera 5/32´´ Truper', 'Broca Para Madera 5/32´´ Truper', 6, '1633.00', '19.00', '2900.00', 'Broca Para Madera 5/32´´ Truper', '2024-10-10 17:45:04', '7506240688006', 1, 1),
(4701, 4, 'Broca Para Madera 7/16´´ Truper', 'Broca Para Madera 7/16´´ Truper', 6, '4324.00', '19.00', '6500.00', 'Broca Para Madera 7/16´´ Truper', '2024-10-10 17:45:04', '7506240688075', 1, 1),
(4702, 4, 'Broca Para Madera 7/32´´ Truper', 'Broca Para Madera 7/32´´ Truper', 6, '1825.00', '19.00', '3200.00', 'Broca Para Madera 7/32´´ Truper', '2024-10-10 17:45:04', '7506240688020', 1, 1),
(4703, 4, 'Broca Plana Para Madera 1', 'Broca Plana Para Madera 1', 5, '4399.00', '19.00', '6500.00', 'Broca Plana Para Madera 1', '2024-10-10 17:45:04', '7707766454209', 1, 1),
(4704, 4, 'Broca Plana Para Madera 1/2', 'Broca Plana Para Madera 1/2', 4, '3299.00', '19.00', '5000.00', 'Broca Plana Para Madera 1/2', '2024-10-10 17:45:04', '7707766454520', 1, 1),
(4705, 4, 'Broca Plana Para Madera 1/4', 'Broca Plana Para Madera 1/4', 4, '2800.00', '19.00', '4000.00', 'Broca Plana Para Madera 1/4', '2024-10-10 17:45:04', '7707766454490', 1, 1),
(4706, 4, 'Broca SOS Plus Contreto o Muro 3/16 x 4 Truper', 'Broca SOS Plus Contreto o Muro 3/16 x 4 Truper', 6, '5102.00', '19.00', '7200.00', 'Broca SOS Plus Contreto o Muro 3/16 x 4 Truper', '2024-10-10 17:45:04', '7506240682028', 1, 1),
(4707, 4, 'Broca Truper 1/8 Truper', 'Broca Truper 1/8 Truper', 10, '2124.00', '19.00', '2700.00', 'Broca Truper 1/8 Truper', '2024-10-10 17:45:04', '57501206645951', 1, 1),
(4708, 4, 'Brocha 2 1/2 Cerda Mona B/Pintor', 'Brocha 2 1/2 Cerda Mona B/Pintor', 5, '4550.00', '19.00', '6200.00', 'Brocha 2 1/2 Cerda Mona B/Pintor', '2024-10-10 17:45:04', '7702155014101', 1, 1),
(4709, 4, 'Brocha 2 Cerda Mona B/Pintor', 'Brocha 2 Cerda Mona B/Pintor', 10, '3800.00', '19.00', '5000.00', 'Brocha 2 Cerda Mona B/Pintor', '2024-10-10 17:45:04', '7702155013937', 1, 1),
(4710, 4, 'Brocha 3 Uduke', 'Brocha 3 Uduke', 6, '2150.00', '19.00', '3000.00', 'Brocha 3 Uduke', '2024-10-10 17:45:04', '6973653178324', 1, 1),
(4711, 4, 'Brocha ARKET 1', 'Brocha ARKET 1', 2, '1715.00', '19.00', '2500.00', 'Brocha ARKET 1', '2024-10-10 17:45:04', '610001', 1, 1),
(4712, 4, 'Brocha ARKET 1/2', 'Brocha ARKET 1/2', 9, '1140.00', '19.00', '1800.00', 'Brocha ARKET 1/2', '2024-10-10 17:45:04', '610000', 1, 1),
(4713, 4, 'Brocha ARKET 2', 'Brocha ARKET 2', 1, '2515.00', '19.00', '3700.00', 'Brocha ARKET 2', '2024-10-10 17:45:04', '610003', 1, 1),
(4714, 4, 'Brocha ARKET 2 1/2', 'Brocha ARKET 2 1/2', 0, '3259.00', '19.00', '4900.00', 'Brocha ARKET 2 1/2', '2024-10-10 17:45:04', '610004', 1, 1),
(4715, 4, 'Brocha ARKET 3', 'Brocha ARKET 3', 4, '4430.00', '19.00', '6500.00', 'Brocha ARKET 3', '2024-10-10 17:45:04', '610005', 1, 1),
(4716, 4, 'Brocha ARKET 4', 'Brocha ARKET 4', 3, '6106.00', '19.00', '8900.00', 'Brocha ARKET 4', '2024-10-10 17:45:04', '610007', 1, 1),
(4717, 4, 'Brocha Buen Pintor 1 1/2', 'Brocha Buen Pintor 1 1/2', 6, '2850.00', '19.00', '4000.00', 'Brocha Buen Pintor 1 1/2', '2024-10-10 17:45:04', '7702155013920', 1, 1),
(4718, 4, 'Brocha Caribe 1 1/2', 'Brocha Caribe 1 1/2', 5, '3050.00', '19.00', '4000.00', 'Brocha Caribe 1 1/2', '2024-10-10 17:45:04', '7707342740504', 1, 1),
(4719, 4, 'Brocha Caribe 2', 'Brocha Caribe 2', 4, '3500.00', '19.00', '4600.00', 'Brocha Caribe 2', '2024-10-10 17:45:04', '7707342740511', 1, 1),
(4720, 4, 'Brocha Rojo-Azul 1', 'Brocha Rojo-Azul 1', 14, '1600.00', '19.00', '2500.00', 'Brocha Rojo-Azul 1', '2024-10-10 17:45:04', '7707180674405', 1, 1),
(4721, 4, 'Brocha Rojo-Azul 1 1/2 Boccherini', 'Brocha Rojo-Azul 1 1/2 Boccherini', 1, '2500.00', '19.00', '4000.00', 'Brocha Rojo-Azul 1 1/2 Boccherini', '2024-10-10 17:45:04', '7707180674412', 1, 1),
(4722, 4, 'Brocha Rojo-Azul 2 1/2', 'Brocha Rojo-Azul 2 1/2', 0, '5300.00', '19.00', '8000.00', 'Brocha Rojo-Azul 2 1/2', '2024-10-10 17:45:04', '7707180674436', 1, 1),
(4723, 4, 'Brocha Rojo-Azul 2 Boccherini', 'Brocha Rojo-Azul 2 Boccherini', 0, '3999.00', '19.00', '6000.00', 'Brocha Rojo-Azul 2 Boccherini', '2024-10-10 17:45:04', '7707180674429', 1, 1);
INSERT INTO `inventario` (`id`, `user_id`, `nombre`, `descripcion`, `stock`, `precio_costo`, `impuesto`, `precio_venta`, `otro_dato`, `fecha_ingreso`, `codigo_barras`, `departamento_id`, `categoria_id`) VALUES
(4724, 4, 'Brocha Rojo-Azul 3 Boccherini', 'Brocha Rojo-Azul 3 Boccherini', 0, '5999.00', '19.00', '9000.00', 'Brocha Rojo-Azul 3 Boccherini', '2024-10-10 17:45:04', '7707180674443', 1, 1),
(4725, 4, 'Brocha Rojo-Azul 4', 'Brocha Rojo-Azul 4', 0, '6399.00', '19.00', '9500.00', 'Brocha Rojo-Azul 4', '2024-10-10 17:45:04', '7707180674450', 1, 1),
(4726, 4, 'Brocha Rojo-Azul 5 Boccherini', 'Brocha Rojo-Azul 5 Boccherini', 2, '9799.00', '19.00', '14000.00', 'Brocha Rojo-Azul 5 Boccherini', '2024-10-10 17:45:04', '7707180674467', 1, 1),
(4727, 4, 'Brocha Rojo-Azul 6 Boccherini', 'Brocha Rojo-Azul 6 Boccherini', 6, '9199.00', '19.00', '16000.00', 'Brocha Rojo-Azul 6 Boccherini', '2024-10-10 17:45:04', '7707180674474', 1, 1),
(4728, 4, 'Brocha Rojo-Azul Boccherini 1/2', 'Brocha Rojo-Azul Boccherini 1/2', 2, '1399.00', '19.00', '2000.00', 'Brocha Rojo-Azul Boccherini 1/2', '2024-10-10 17:45:04', '7707180674399', 1, 1),
(4729, 4, 'Buje Lavamanos 2 Negro', 'Buje Lavamanos 2 Negro', 7, '2299.00', '19.00', '3500.00', 'Buje Lavamanos 2 Negro', '2024-10-10 17:45:04', '20599024', 1, 1),
(4730, 4, 'Buje Lavaplatos 2 Negro', 'Buje Lavaplatos 2 Negro', 8, '2299.00', '19.00', '3500.00', 'Buje Lavaplatos 2 Negro', '2024-10-10 17:45:04', '20599025', 1, 1),
(4731, 4, 'Buje Presion 2 X 1/2', 'Buje Presion 2 X 1/2', 17, '1440.00', '19.00', '2300.00', 'Buje Presion 2 X 1/2', '2024-10-10 17:45:04', '163086', 1, 1),
(4732, 4, 'Buje Presion 3/4 x 1/2', 'Buje Presion 3/4 x 1/2', 20, '215.00', '19.00', '400.00', 'Buje Presion 3/4 x 1/2', '2024-10-10 17:45:04', '163076', 1, 1),
(4733, 4, 'Buje Presion1 X 1/2', 'Buje Presion1 X 1/2', 17, '368.00', '19.00', '600.00', 'Buje Presion1 X 1/2', '2024-10-10 17:45:04', '163077', 1, 1),
(4734, 4, 'Buje Sanitario 4 X 3 Amarillo Arket', 'Buje Sanitario 4 X 3 Amarillo Arket', 4, '5297.00', '19.00', '7600.00', 'Buje Sanitario 4 X 3 Amarillo Arket', '2024-10-10 17:45:04', '1220094', 1, 1),
(4735, 4, 'CONJUNTO LLAVE DE REGULACION BCH 103', 'CONJUNTO LLAVE DE REGULACION BCH 103', 2, '11500.00', '19.00', '16500.00', 'CONJUNTO LLAVE DE REGULACION BCH 103', '2024-10-10 17:45:04', '7707766450591', 1, 1),
(4736, 4, 'COPAS DE PUNTA T-30', 'COPAS DE PUNTA T-30', 2, '5900.00', '19.00', '8200.00', 'COPAS DE PUNTA T-30', '2024-10-10 17:45:04', '7501206615737', 1, 1),
(4737, 4, 'COPAS DE PUNTA T-40', 'COPAS DE PUNTA T-40', 2, '5900.00', '19.00', '8200.00', 'COPAS DE PUNTA T-40', '2024-10-10 17:45:04', '7501206615744', 1, 1),
(4738, 4, 'CUÑETE ESTUCO EXTERIOR PULIMENTO ESPACOL', 'CUÑETE ESTUCO EXTERIOR PULIMENTO ESPACOL', 2, '68588.00', '19.00', '78800.00', 'CUÑETE ESTUCO EXTERIOR PULIMENTO ESPACOL', '2024-10-10 17:45:04', '3018', 1, 1),
(4739, 4, 'CUÑETE ESTUCO INTERIOR PULIMENTO ESPACOL', 'CUÑETE ESTUCO INTERIOR PULIMENTO ESPACOL', 6, '56778.00', '19.00', '68500.00', 'CUÑETE ESTUCO INTERIOR PULIMENTO ESPACOL', '2024-10-10 17:45:04', '3014', 1, 1),
(4740, 4, 'Cable #8 Rojo-Blanco 7hilos x metros', 'Cable #8 Rojo-Blanco 7hilos x metros', 4, '4000.00', '19.00', '4900.00', 'Cable #8 Rojo-Blanco 7hilos x metros', '2024-10-10 17:45:04', '770731321208', 1, 1),
(4741, 4, 'Cable Alambre Rojo Centelsa #14 X Metros', 'Cable Alambre Rojo Centelsa #14 X Metros', 48, '1180.00', '19.00', '1900.00', 'Cable Alambre Rojo Centelsa #14 X Metros', '2024-10-10 17:45:04', '7707313320186', 1, 1),
(4742, 4, 'Cable Concentrico Negro X 30metros', 'Cable Concentrico Negro X 30metros', 1, '120000.00', '19.00', '171000.00', 'Cable Concentrico Negro X 30metros', '2024-10-10 17:45:04', '770731321207', 1, 1),
(4743, 4, 'Cable Concentrico Negro x Metros', 'Cable Concentrico Negro x Metros', 30, '4000.00', '19.00', '6000.00', 'Cable Concentrico Negro x Metros', '2024-10-10 17:45:04', '770731321206', 1, 1),
(4744, 4, 'Cable Dúplex 214 Económico', 'Cable Dúplex 214 Económico', 48, '800.00', '19.00', '2200.00', 'Cable Dúplex 214 Económico', '2024-10-10 17:45:04', 'D1', 1, 1),
(4745, 4, 'Cafetera OSTER 35 Tazas BVSTDC3390013 Negro', 'Cafetera OSTER 35 Tazas BVSTDC3390013 Negro', 0, '189089.00', '19.00', '199000.00', 'Cafetera OSTER 35 Tazas BVSTDC3390013 Negro', '2024-10-10 17:45:04', '34264431959', 1, 1),
(4746, 4, 'Cafetera Oster Switch 12Tz Negra', 'Cafetera Oster Switch 12Tz Negra', 1, '94500.00', '19.00', '115900.00', 'Cafetera Oster Switch 12Tz Negra', '2024-10-10 17:45:04', '53891173148', 1, 1),
(4747, 4, 'Caja 2400 Plastica Electrica Pvc', 'Caja 2400 Plastica Electrica Pvc', 10, '2800.00', '19.00', '3500.00', 'Caja 2400 Plastica Electrica Pvc', '2024-10-10 17:45:04', '3', 1, 1),
(4748, 4, 'Caja Carga Amarilla Para Pistola De Fijacion Metal', 'Caja Carga Amarilla Para Pistola De Fijacion Metal', 2, '34000.00', '19.00', '38500.00', 'Caja Carga Amarilla Para Pistola De Fijacion Metal', '2024-10-10 17:45:04', '10042024', 1, 1),
(4749, 4, 'Caja Clavos Para Pistola De Fijacion X100', 'Caja Clavos Para Pistola De Fijacion X100', 2, '10000.00', '19.00', '15000.00', 'Caja Clavos Para Pistola De Fijacion X100', '2024-10-10 17:45:04', '10042023', 1, 1),
(4750, 4, 'Caja Con Tapa Para Camaras', 'Caja Con Tapa Para Camaras', 5, '4904.00', '19.00', '7000.00', 'Caja Con Tapa Para Camaras', '2024-10-10 17:45:04', '8723', 1, 1),
(4751, 4, 'Caja De Breaker Metalica 2 Circuitos', 'Caja De Breaker Metalica 2 Circuitos', 2, '19550.00', '19.00', '26500.00', 'Caja De Breaker Metalica 2 Circuitos', '2024-10-10 17:45:04', '568914', 1, 1),
(4752, 4, 'Caja De Puntilla Comun 3x9 Corsan 400g', 'Caja De Puntilla Comun 3x9 Corsan 400g', 3, '3549.00', '19.00', '4800.00', 'Caja De Puntilla Comun 3x9 Corsan 400g', '2024-10-10 17:45:04', '3CCL097NV', 1, 1),
(4753, 4, 'Caja Octagonal Plastica Blanca Elctrica Pvc', 'Caja Octagonal Plastica Blanca Elctrica Pvc', 0, '559.00', '19.00', '1200.00', 'Caja Octagonal Plastica Blanca Elctrica Pvc', '2024-10-10 17:45:04', '2', 1, 1),
(4754, 4, 'Caja Para Herramientas De 19 Con Compartimentos Pretul', 'Caja Para Herramientas De 19 Con Compartimentos Pretul', 1, '34391.00', '19.00', '44800.00', 'Caja Para Herramientas De 19 Con Compartimentos Pretul', '2024-10-10 17:45:04', '7506240654247', 1, 1),
(4755, 4, 'Caja Plastica 2x4 Electrica Pvc', 'Caja Plastica 2x4 Electrica Pvc', 38, '350.00', '19.00', '500.00', 'Caja Plastica 2x4 Electrica Pvc', '2024-10-10 17:45:04', '7599', 1, 1),
(4756, 4, 'Caja Plastica 4x4 Electrico', 'Caja Plastica 4x4 Electrico', 19, '866.00', '19.00', '1500.00', 'Caja Plastica 4x4 Electrico', '2024-10-10 17:45:04', '510002', 1, 1),
(4757, 4, 'Caldero #16 Aluminios Seve', 'Caldero #16 Aluminios Seve', 0, '0.00', '19.00', '16000.00', 'Caldero #16 Aluminios Seve', '2024-10-10 17:45:04', '614143812378', 1, 1),
(4758, 4, 'Canal Morgan Tipo A X3M', 'Canal Morgan Tipo A X3M', 3, '41241.00', '19.00', '68000.00', 'Canal Morgan Tipo A X3M', '2024-10-10 17:45:04', '1', 1, 1),
(4759, 4, 'Canaleta 12mm X 8mmx2mtrs', 'Canaleta 12mm X 8mmx2mtrs', 19, '2809.00', '19.00', '3800.00', 'Canaleta 12mm X 8mmx2mtrs', '2024-10-10 17:45:04', '7707692868187', 1, 1),
(4760, 4, 'Canaleta 24mm X 14mmx2mtrs', 'Canaleta 24mm X 14mmx2mtrs', 10, '5372.00', '19.00', '7000.00', 'Canaleta 24mm X 14mmx2mtrs', '2024-10-10 17:45:04', '7707692867739', 1, 1),
(4761, 4, 'Canaleta 40mm X 25mmx2mtrs', 'Canaleta 40mm X 25mmx2mtrs', 5, '12053.00', '19.00', '16000.00', 'Canaleta 40mm X 25mmx2mtrs', '2024-10-10 17:45:04', '7707692869429', 1, 1),
(4762, 4, 'Canaleta De 20mm X 10 Mmx2mtrs', 'Canaleta De 20mm X 10 Mmx2mtrs', 15, '4297.00', '19.00', '5600.00', 'Canaleta De 20mm X 10 Mmx2mtrs', '2024-10-10 17:45:04', '7707692860877', 1, 1),
(4763, 4, 'Canaleta Grander', 'Canaleta Grander', 3, '3200.00', '19.00', '5800.00', 'Canaleta Grander', '2024-10-10 17:45:04', '688752', 1, 1),
(4764, 4, 'Canaleta Pequeña', 'Canaleta Pequeña', 0, '2700.00', '19.00', '3800.00', 'Canaleta Pequeña', '2024-10-10 17:45:04', '264600', 1, 1),
(4765, 4, 'Canastilla Lavaplatos Acero 4.1/2', 'Canastilla Lavaplatos Acero 4.1/2', 2, '21749.00', '19.00', '28000.00', 'Canastilla Lavaplatos Acero 4.1/2', '2024-10-10 17:45:04', '7707180677246', 1, 1),
(4766, 4, 'Canastilla Lavaplatos Cardenas', 'Canastilla Lavaplatos Cardenas', 5, '5387.00', '19.00', '7500.00', 'Canastilla Lavaplatos Cardenas', '2024-10-10 17:45:04', '35', 1, 1),
(4767, 4, 'Canastilla Lavaplatos Fermetal', 'Canastilla Lavaplatos Fermetal', 0, '6384.00', '19.00', '9000.00', 'Canastilla Lavaplatos Fermetal', '2024-10-10 17:45:04', '7592032002051', 1, 1),
(4768, 4, 'Canastilla Lavaplatos Plastica Boccherini', 'Canastilla Lavaplatos Plastica Boccherini', 2, '8500.00', '19.00', '11000.00', 'Canastilla Lavaplatos Plastica Boccherini', '2024-10-10 17:45:04', '7707180674900', 1, 1),
(4769, 4, 'Canastilla Para Bombillo Ahorrador Grande', 'Canastilla Para Bombillo Ahorrador Grande', 6, '3300.00', '19.00', '5000.00', 'Canastilla Para Bombillo Ahorrador Grande', '2024-10-10 17:45:04', 'K01', 1, 1),
(4770, 4, 'Canastilla Plast-Grifos', 'Canastilla Plast-Grifos', 0, '4599.00', '19.00', '7000.00', 'Canastilla Plast-Grifos', '2024-10-10 17:45:04', '7700032501829', 1, 1),
(4771, 4, 'Cancamo #12 x144 Uni Uduke', 'Cancamo #12 x144 Uni Uduke', 142, '134.00', '19.00', '200.00', 'Cancamo #12 x144 Uni Uduke', '2024-10-10 17:45:04', '6973653172759', 1, 1),
(4772, 4, 'Cancamo #14 X144uni Uduke', 'Cancamo #14 X144uni Uduke', 145, '191.00', '19.00', '300.00', 'Cancamo #14 X144uni Uduke', '2024-10-10 17:45:04', '6973653172766', 1, 1),
(4773, 4, 'Cancamo #16 X144uni Uduke', 'Cancamo #16 X144uni Uduke', 143, '260.00', '19.00', '400.00', 'Cancamo #16 X144uni Uduke', '2024-10-10 17:45:04', '6973653172773', 1, 1),
(4774, 4, 'Cancamo #2 x144uni Uduke', 'Cancamo #2 x144uni Uduke', 143, '21.00', '19.00', '50.00', 'Cancamo #2 x144uni Uduke', '2024-10-10 17:45:04', '6973877765393', 1, 1),
(4775, 4, 'Cancamo #4 x144 Uni Uduke', 'Cancamo #4 x144 Uni Uduke', 144, '33.00', '19.00', '50.00', 'Cancamo #4 x144 Uni Uduke', '2024-10-10 17:45:04', '6973877765386', 1, 1),
(4776, 4, 'Cancamo #6 x144uni Uduke', 'Cancamo #6 x144uni Uduke', 141, '50.00', '19.00', '100.00', 'Cancamo #6 x144uni Uduke', '2024-10-10 17:45:04', '6973653172728', 1, 1),
(4777, 4, 'Cancamo #8 x144uni Uduke', 'Cancamo #8 x144uni Uduke', 145, '72.00', '19.00', '150.00', 'Cancamo #8 x144uni Uduke', '2024-10-10 17:45:04', '6973653172735', 1, 1),
(4778, 4, 'Candado 25mm Boccherini', 'Candado 25mm Boccherini', 7, '1600.00', '19.00', '2500.00', 'Candado 25mm Boccherini', '2024-10-10 17:45:04', '7707180674115', 1, 1),
(4779, 4, 'Candado 50mm Fanal', 'Candado 50mm Fanal', 0, '8332.00', '19.00', '14000.00', 'Candado 50mm Fanal', '2024-10-10 17:45:04', '798660207609', 1, 1),
(4780, 4, 'Candado De 63mm Fanal', 'Candado De 63mm Fanal', 1, '9700.00', '19.00', '16500.00', 'Candado De 63mm Fanal', '2024-10-10 17:45:04', '798660204646', 1, 1),
(4781, 4, 'Candado De Hierro 30mm Hermex Basico', 'Candado De Hierro 30mm Hermex Basico', 5, '3747.00', '19.00', '5500.00', 'Candado De Hierro 30mm Hermex Basico', '2024-10-10 17:45:04', '7501206663141', 1, 1),
(4782, 4, 'Candado De Hierro 32mm Boccherini', 'Candado De Hierro 32mm Boccherini', 4, '2700.00', '19.00', '3500.00', 'Candado De Hierro 32mm Boccherini', '2024-10-10 17:45:04', '7707180674114', 1, 1),
(4783, 4, 'Candado De Hierro 38mm Boccherini', 'Candado De Hierro 38mm Boccherini', 0, '2999.00', '19.00', '4900.00', 'Candado De Hierro 38mm Boccherini', '2024-10-10 17:45:04', '7707180674116', 1, 1),
(4784, 4, 'Candado De Hierro 40mm Hermex Basico', 'Candado De Hierro 40mm Hermex Basico', 3, '4516.00', '19.00', '6500.00', 'Candado De Hierro 40mm Hermex Basico', '2024-10-10 17:45:04', '7501206663103', 1, 1),
(4785, 4, 'Candado Hierro 25mm Fanal', 'Candado Hierro 25mm Fanal', 5, '3333.00', '19.00', '4800.00', 'Candado Hierro 25mm Fanal', '2024-10-10 17:45:04', '7707180674117', 1, 1),
(4786, 4, 'Candado Hierro 38mm Fanal', 'Candado Hierro 38mm Fanal', 0, '4999.00', '19.00', '7500.00', 'Candado Hierro 38mm Fanal', '2024-10-10 17:45:04', '7707180674118', 1, 1),
(4787, 4, 'Candado Hierro 50mm Hermex Basico', 'Candado Hierro 50mm Hermex Basico', 3, '7495.00', '19.00', '11000.00', 'Candado Hierro 50mm Hermex Basico', '2024-10-10 17:45:04', '7501206663110', 1, 1),
(4788, 4, 'Candado Intemperie 70mm Naranja Uduke', 'Candado Intemperie 70mm Naranja Uduke', 1, '15500.00', '19.00', '22500.00', 'Candado Intemperie 70mm Naranja Uduke', '2024-10-10 17:45:04', '6973653176009', 1, 1),
(4789, 4, 'Candado Laminado 30mm Hermex', 'Candado Laminado 30mm Hermex', 2, '7303.00', '19.00', '10500.00', 'Candado Laminado 30mm Hermex', '2024-10-10 17:45:04', '7501206684429', 1, 1),
(4790, 4, 'Candado Laminado 40mm Hermex', 'Candado Laminado 40mm Hermex', 2, '10570.00', '19.00', '15500.00', 'Candado Laminado 40mm Hermex', '2024-10-10 17:45:04', '7501206684436', 1, 1),
(4791, 4, 'Candado Laminado 45mm Hermex', 'Candado Laminado 45mm Hermex', 1, '12492.00', '19.00', '17900.00', 'Candado Laminado 45mm Hermex', '2024-10-10 17:45:04', '7501206617465', 1, 1),
(4792, 4, 'Candado Laminado 50mm Hermex', 'Candado Laminado 50mm Hermex', 1, '14413.00', '19.00', '21000.00', 'Candado Laminado 50mm Hermex', '2024-10-10 17:45:04', '7501206684443', 1, 1),
(4793, 4, 'Candado Laminado Recubierto De Plastico 50mm Hermex', 'Candado Laminado Recubierto De Plastico 50mm Hermex', 0, '28322.00', '19.00', '36500.00', 'Candado Laminado Recubierto De Plastico 50mm Hermex', '2024-10-10 17:45:04', '7506240609957', 1, 1),
(4794, 4, 'Candado Para Disco De Motocicleta 5.5 Mm', 'Candado Para Disco De Motocicleta 5.5 Mm', 1, '35402.00', '19.00', '46000.00', 'Candado Para Disco De Motocicleta 5.5 Mm', '2024-10-10 17:45:04', '7506240691464', 1, 1),
(4795, 4, 'Candado Para Moto Con Alarma Globy R8201N', 'Candado Para Moto Con Alarma Globy R8201N', 0, '40000.00', '19.00', '52000.00', 'Candado Para Moto Con Alarma Globy R8201N', '2024-10-10 17:45:04', '6978050082014', 1, 1),
(4796, 4, 'Candado Para Moto Con Alarma R8201N', 'Candado Para Moto Con Alarma R8201N', 1, '40000.00', '19.00', '52000.00', 'Candado Para Moto Con Alarma R8201N', '2024-10-10 17:45:04', '6930706006133', 1, 1),
(4797, 4, 'Careta Roja', 'Careta Roja', 1, '70000.00', '19.00', '98000.00', 'Careta Roja', '2024-10-10 17:45:04', '886352880114', 1, 1),
(4798, 4, 'Carga Verde Para Pistola', 'Carga Verde Para Pistola', 2, '34000.00', '19.00', '38500.00', 'Carga Verde Para Pistola', '2024-10-10 17:45:04', 'HERR3323', 1, 1),
(4799, 4, 'Carretilla Beyota Llanta Antipinchazo', 'Carretilla Beyota Llanta Antipinchazo', 6, '199900.00', '19.00', '240000.00', 'Carretilla Beyota Llanta Antipinchazo', '2024-10-10 17:45:04', '7702956225904', 1, 1),
(4800, 4, 'Cautin Tipo Lapiz 60watts Boccherini', 'Cautin Tipo Lapiz 60watts Boccherini', 1, '8200.00', '19.00', '12500.00', 'Cautin Tipo Lapiz 60watts Boccherini', '2024-10-10 17:45:04', '7707180679172', 1, 1),
(4801, 4, 'Cautin Tipo Lapiz Boccherini', 'Cautin Tipo Lapiz Boccherini', 1, '7199.00', '19.00', '11000.00', 'Cautin Tipo Lapiz Boccherini', '2024-10-10 17:45:04', '7707180679165', 1, 1),
(4802, 4, 'Cepillera Plast-Grifos', 'Cepillera Plast-Grifos', 4, '8498.00', '19.00', '12000.00', 'Cepillera Plast-Grifos', '2024-10-10 17:45:04', '7700031023025', 1, 1),
(4803, 4, 'Cepillo Alambre Grande', 'Cepillo Alambre Grande', 3, '4600.00', '19.00', '7000.00', 'Cepillo Alambre Grande', '2024-10-10 17:45:04', '7707180679509', 1, 1),
(4804, 4, 'Cepillo Alambre Plastico Pequeño', 'Cepillo Alambre Plastico Pequeño', 1, '2737.00', '19.00', '4500.00', 'Cepillo Alambre Plastico Pequeño', '2024-10-10 17:45:04', '7707180679493', 1, 1),
(4805, 4, 'Cepillo De Alambre Boccherini', 'Cepillo De Alambre Boccherini', 0, '2600.00', '19.00', '5000.00', 'Cepillo De Alambre Boccherini', '2024-10-10 17:45:04', '7707180679462', 1, 1),
(4806, 4, 'Cepillo De Alambre En Madera Boccherini', 'Cepillo De Alambre En Madera Boccherini', 0, '2261.00', '19.00', '4000.00', 'Cepillo De Alambre En Madera Boccherini', '2024-10-10 17:45:04', '7707180679479', 1, 1),
(4807, 4, 'Cerradura ARKET Alcoba', 'Cerradura ARKET Alcoba', 3, '16127.00', '19.00', '23000.00', 'Cerradura ARKET Alcoba', '2024-10-10 17:45:04', '800036', 1, 1),
(4808, 4, 'Cerradura De Pomo Para Baño Boccherini', 'Cerradura De Pomo Para Baño Boccherini', 0, '15199.00', '19.00', '20000.00', 'Cerradura De Pomo Para Baño Boccherini', '2024-10-10 17:45:04', '7707766451994', 1, 1),
(4809, 4, 'Cerradura De Sobreponer Derecha  Kinglock', 'Cerradura De Sobreponer Derecha  Kinglock', 0, '27642.00', '19.00', '39500.00', 'Cerradura De Sobreponer Derecha  Kinglock', '2024-10-10 17:45:04', '7701459201019', 1, 1),
(4810, 4, 'Cerradura Mega Inafer', 'Cerradura Mega Inafer', 1, '34395.00', '19.00', '43000.00', 'Cerradura Mega Inafer', '2024-10-10 17:45:04', '7707007772178', 1, 1),
(4811, 4, 'Cerradura Sobreponer Izquierda Kinglock', 'Cerradura Sobreponer Izquierda Kinglock', 0, '27642.00', '19.00', '39500.00', 'Cerradura Sobreponer Izquierda Kinglock', '2024-10-10 17:45:04', '7701459201026', 1, 1),
(4812, 4, 'Cerradura Supra Inafer', 'Cerradura Supra Inafer', 0, '34717.00', '19.00', '45000.00', 'Cerradura Supra Inafer', '2024-10-10 17:45:04', '7707007780852', 1, 1),
(4813, 4, 'Cerrojo Cilindro Perilla Acabado En Acero Cer-15', 'Cerrojo Cilindro Perilla Acabado En Acero Cer-15', 2, '18345.00', '19.00', '25500.00', 'Cerrojo Cilindro Perilla Acabado En Acero Cer-15', '2024-10-10 17:45:04', '7592032037145', 1, 1),
(4814, 4, 'Chapeta Lavamanos Plast Grifos Negra 8042', 'Chapeta Lavamanos Plast Grifos Negra 8042', 6, '1200.00', '19.00', '2200.00', 'Chapeta Lavamanos Plast Grifos Negra 8042', '2024-10-10 17:45:04', '7700031003928', 1, 1),
(4815, 4, 'Chapeta Lavamanos Plastigrifos', 'Chapeta Lavamanos Plastigrifos', 7, '974.00', '19.00', '1500.00', 'Chapeta Lavamanos Plastigrifos', '2024-10-10 17:45:04', '438', 1, 1),
(4816, 4, 'Charola Para Rodillo 14', 'Charola Para Rodillo 14', 1, '5462.00', '19.00', '7501.00', 'Charola Para Rodillo 14', '2024-10-10 17:45:04', '7501206602744', 1, 1),
(4817, 4, 'Chazo Metalico De Expa 3/8 X 3', 'Chazo Metalico De Expa 3/8 X 3', 85, '862.00', '19.00', '1200.00', 'Chazo Metalico De Expa 3/8 X 3', '2024-10-10 17:45:04', '866359', 1, 1),
(4818, 4, 'Chazo Pastico 1/4', 'Chazo Pastico 1/4', 990, '18.00', '19.00', '50.00', 'Chazo Pastico 1/4', '2024-10-10 17:45:04', '447', 1, 1),
(4819, 4, 'Chazo Plastico 1/2', 'Chazo Plastico 1/2', 472, '73.00', '19.00', '100.00', 'Chazo Plastico 1/2', '2024-10-10 17:45:04', 'IA0955', 1, 1),
(4820, 4, 'Chazo Plastico 3/16', 'Chazo Plastico 3/16', 1000, '17.00', '19.00', '50.00', 'Chazo Plastico 3/16', '2024-10-10 17:45:04', '448', 1, 1),
(4821, 4, 'Chazo Plastico 5/16', 'Chazo Plastico 5/16', 952, '33.00', '19.00', '50.00', 'Chazo Plastico 5/16', '2024-10-10 17:45:04', '7709396458350', 1, 1),
(4822, 4, 'Chazo Supra 5/16', 'Chazo Supra 5/16', 471, '198.00', '19.00', '300.00', 'Chazo Supra 5/16', '2024-10-10 17:45:04', '50332', 1, 1),
(4823, 4, 'Cheque', 'Cheque', 4, '9200.00', '19.00', '13500.00', 'Cheque', '2024-10-10 17:45:04', '6973877760542', 1, 1),
(4824, 4, 'Cheque Horizontal 1/2 Uduke', 'Cheque Horizontal 1/2 Uduke', 4, '9429.00', '19.00', '13500.00', 'Cheque Horizontal 1/2 Uduke', '2024-10-10 17:45:04', '13597', 1, 1),
(4825, 4, 'Chozo Metalico Expa 1/4 X 2 1/2', 'Chozo Metalico Expa 1/4 X 2 1/2', 83, '429.00', '19.00', '700.00', 'Chozo Metalico Expa 1/4 X 2 1/2', '2024-10-10 17:45:04', '866352', 1, 1),
(4826, 4, 'Chupa Ventosa Cap 25 Kls Para Vidrio Total', 'Chupa Ventosa Cap 25 Kls Para Vidrio Total', 2, '11450.00', '19.00', '15000.00', 'Chupa Ventosa Cap 25 Kls Para Vidrio Total', '2024-10-10 17:45:04', '6925582157727', 1, 1),
(4827, 4, 'Chupa Ventosa Cap 50 Kls Para Vidrio Total', 'Chupa Ventosa Cap 50 Kls Para Vidrio Total', 0, '21000.00', '19.00', '28000.00', 'Chupa Ventosa Cap 50 Kls Para Vidrio Total', '2024-10-10 17:45:04', '6925582157710', 1, 1),
(4828, 4, 'Cincel Punta Plana Concreto', 'Cincel Punta Plana Concreto', 4, '9678.00', '19.00', '13000.00', 'Cincel Punta Plana Concreto', '2024-10-10 17:45:04', '7707180679066', 1, 1),
(4829, 4, 'Cinta Aislante 19mm X 18m Pretul 20522', 'Cinta Aislante 19mm X 18m Pretul 20522', 5, '1719.00', '19.00', '2800.00', 'Cinta Aislante 19mm X 18m Pretul 20522', '2024-10-10 17:45:04', '7501206626627', 1, 1),
(4830, 4, 'Cinta Aislante Duke Energy 10mts HT20451C', 'Cinta Aislante Duke Energy 10mts HT20451C', 12, '1300.00', '19.00', '2500.00', 'Cinta Aislante Duke Energy 10mts HT20451C', '2024-10-10 17:45:04', '6977068140761', 1, 1),
(4831, 4, 'Cinta Aislante Duke Energy 15mts HT20449', 'Cinta Aislante Duke Energy 15mts HT20449', 11, '1850.00', '19.00', '3000.00', 'Cinta Aislante Duke Energy 15mts HT20449', '2024-10-10 17:45:04', '6973653178386', 1, 1),
(4832, 4, 'Cinta Aislante Duke Energy 5mts HT20448', 'Cinta Aislante Duke Energy 5mts HT20448', 12, '750.00', '19.00', '1400.00', 'Cinta Aislante Duke Energy 5mts HT20448', '2024-10-10 17:45:04', '6973653178379', 1, 1),
(4833, 4, 'Cinta Aislante Negra 19mm X 9m Pretul 20521', 'Cinta Aislante Negra 19mm X 9m Pretul 20521', 16, '960.00', '19.00', '1800.00', 'Cinta Aislante Negra 19mm X 9m Pretul 20521', '2024-10-10 17:45:04', '7501206626610', 1, 1),
(4834, 4, 'Cinta Autoadhesiva Para Reparacion Multiseal Sika X 10 Mts (metro $9.800)', 'Cinta Autoadhesiva Para Reparacion Multiseal Sika X 10 Mts (metro $9.800)', 2, '77000.00', '19.00', '88500.00', 'Cinta Autoadhesiva Para Reparacion Multiseal Sika X 10 Mts (metro $9.800)', '2024-10-10 17:45:04', '7612894377170', 1, 1),
(4835, 4, 'Cinta Automotriz La Original 3m', 'Cinta Automotriz La Original 3m', 2, '5000.00', '19.00', '6900.00', 'Cinta Automotriz La Original 3m', '2024-10-10 17:45:04', '7702098039025', 1, 1),
(4836, 4, 'Cinta De Enmascarar 12mm X 20m', 'Cinta De Enmascarar 12mm X 20m', 12, '1169.00', '19.00', '1900.00', 'Cinta De Enmascarar 12mm X 20m', '2024-10-10 17:45:04', '6985130720117', 1, 1),
(4837, 4, 'Cinta De Enmascarar 18mm X 20mt', 'Cinta De Enmascarar 18mm X 20mt', 0, '1578.00', '19.00', '2300.00', 'Cinta De Enmascarar 18mm X 20mt', '2024-10-10 17:45:04', '6985130720124', 1, 1),
(4838, 4, 'Cinta De Enmascarar 3/4 18mm x39mts Uduke', 'Cinta De Enmascarar 3/4 18mm x39mts Uduke', 15, '2250.00', '19.00', '3200.00', 'Cinta De Enmascarar 3/4 18mm x39mts Uduke', '2024-10-10 17:45:04', '6973653178645', 1, 1),
(4839, 4, 'Cinta De Enmascarar 36mm X 20mt', 'Cinta De Enmascarar 36mm X 20mt', 2, '3009.00', '19.00', '4500.00', 'Cinta De Enmascarar 36mm X 20mt', '2024-10-10 17:45:04', '6985130720148', 1, 1),
(4840, 4, 'Cinta De Enmascarar Soco De 2', 'Cinta De Enmascarar Soco De 2', 0, '9800.00', '19.00', '11500.00', 'Cinta De Enmascarar Soco De 2', '2024-10-10 17:45:04', '7707202197196', 1, 1),
(4841, 4, 'Cinta De Enmascarar Uduke 21mts', 'Cinta De Enmascarar Uduke 21mts', 0, '1827.00', '19.00', '2600.00', 'Cinta De Enmascarar Uduke 21mts', '2024-10-10 17:45:04', '6973653178607', 1, 1),
(4842, 4, 'Cinta De Enmascarar Uduke 43 Mts', 'Cinta De Enmascarar Uduke 43 Mts', 3, '2326.00', '19.00', '3300.00', 'Cinta De Enmascarar Uduke 43 Mts', '2024-10-10 17:45:04', '6973653178621', 1, 1),
(4843, 4, 'Cinta Doble Faz 1.5m Vharbor', 'Cinta Doble Faz 1.5m Vharbor', 4, '999.00', '19.00', '1400.00', 'Cinta Doble Faz 1.5m Vharbor', '2024-10-10 17:45:04', '6973653170465', 1, 1),
(4844, 4, 'Cinta Doble Faz 16mm X 2mts', 'Cinta Doble Faz 16mm X 2mts', 4, '1656.00', '19.00', '2400.00', 'Cinta Doble Faz 16mm X 2mts', '2024-10-10 17:45:04', '6973653178584', 1, 1),
(4845, 4, 'Cinta Doble Faz 16mm X 5m Uduke', 'Cinta Doble Faz 16mm X 5m Uduke', 3, '2740.00', '19.00', '3900.00', 'Cinta Doble Faz 16mm X 5m Uduke', '2024-10-10 17:45:04', '6973653178676', 1, 1),
(4846, 4, 'Cinta Enmascarar 1 24mm x20mts Soco', 'Cinta Enmascarar 1 24mm x20mts Soco', 8, '2150.00', '19.00', '3000.00', 'Cinta Enmascarar 1 24mm x20mts Soco', '2024-10-10 17:45:04', '7707202196526', 1, 1),
(4847, 4, 'Cinta Enmascarar 1 24mm x21mts  Uduke', 'Cinta Enmascarar 1 24mm x21mts  Uduke', 5, '1950.00', '19.00', '2800.00', 'Cinta Enmascarar 1 24mm x21mts  Uduke', '2024-10-10 17:45:04', '6973653178614', 1, 1),
(4848, 4, 'Cinta Enmascarar 1 24mm x39mts', 'Cinta Enmascarar 1 24mm x39mts', 12, '3000.00', '19.00', '4000.00', 'Cinta Enmascarar 1 24mm x39mts', '2024-10-10 17:45:04', '6973653178638', 1, 1),
(4849, 4, 'Cinta Enmascarar 2 48mm x20mts Cellux', 'Cinta Enmascarar 2 48mm x20mts Cellux', 6, '4300.00', '19.00', '5900.00', 'Cinta Enmascarar 2 48mm x20mts Cellux', '2024-10-10 17:45:04', '7701633023062', 1, 1),
(4850, 4, 'Cinta Led Blanca', 'Cinta Led Blanca', 10, '5000.00', '19.00', '12500.00', 'Cinta Led Blanca', '2024-10-10 17:45:04', 'S1235', 1, 1),
(4851, 4, 'Cinta Led Calida', 'Cinta Led Calida', 10, '5000.00', '19.00', '12500.00', 'Cinta Led Calida', '2024-10-10 17:45:04', 'S1234', 1, 1),
(4852, 4, 'Cinta Led Rgb Con Control 12v Colores', 'Cinta Led Rgb Con Control 12v Colores', 1, '23650.00', '19.00', '36000.00', 'Cinta Led Rgb Con Control 12v Colores', '2024-10-10 17:45:04', '911003', 1, 1),
(4853, 4, 'Cinta Negra 3m Templex', 'Cinta Negra 3m Templex', 0, '5000.00', '19.00', '6700.00', 'Cinta Negra 3m Templex', '2024-10-10 17:45:04', '7020980074153', 1, 1),
(4854, 4, 'Cinta Negra Pequeña', 'Cinta Negra Pequeña', 0, '1865.00', '19.00', '2800.00', 'Cinta Negra Pequeña', '2024-10-10 17:45:04', '511288482100', 1, 1),
(4855, 4, 'Cinta Panel Yeso Uduke x 20m', 'Cinta Panel Yeso Uduke x 20m', 10, '2388.00', '19.00', '3500.00', 'Cinta Panel Yeso Uduke x 20m', '2024-10-10 17:45:04', '6973653170489', 1, 1),
(4856, 4, 'Cinta Panel Yeso Uduke x 45m', 'Cinta Panel Yeso Uduke x 45m', 9, '4763.00', '19.00', '6900.00', 'Cinta Panel Yeso Uduke x 45m', '2024-10-10 17:45:04', '6973653170472', 1, 1),
(4857, 4, 'Cinta Panel Yeso Uduke x 90m', 'Cinta Panel Yeso Uduke x 90m', 5, '10068.00', '19.00', '14500.00', 'Cinta Panel Yeso Uduke x 90m', '2024-10-10 17:45:04', '6973653178560', 1, 1),
(4858, 4, 'Cinta Teflon 1/2´´ Truper', 'Cinta Teflon 1/2´´ Truper', 8, '864.00', '19.00', '1300.00', 'Cinta Teflon 1/2´´ Truper', '2024-10-10 17:45:04', '7501206641675', 1, 1),
(4859, 4, 'Cinta Teflon 10 metros', 'Cinta Teflon 10 metros', 0, '1404.00', '19.00', '2000.00', 'Cinta Teflon 10 metros', '2024-10-10 17:45:04', '846002', 1, 1),
(4860, 4, 'Cinta Teflon 1´´ Truper', 'Cinta Teflon 1´´ Truper', 7, '1537.00', '19.00', '2500.00', 'Cinta Teflon 1´´ Truper', '2024-10-10 17:45:04', '7506240602521', 1, 1),
(4861, 4, 'Cinta Teflon 3/4´´ Truper', 'Cinta Teflon 3/4´´ Truper', 8, '1153.00', '19.00', '2000.00', 'Cinta Teflon 3/4´´ Truper', '2024-10-10 17:45:04', '7501206641682', 1, 1),
(4862, 4, 'Cinta Teflon 8 Metros', 'Cinta Teflon 8 Metros', 1, '632.00', '19.00', '1000.00', 'Cinta Teflon 8 Metros', '2024-10-10 17:45:04', '846001', 1, 1),
(4863, 4, 'Cinta Teflon Grande', 'Cinta Teflon Grande', 0, '1800.00', '19.00', '3000.00', 'Cinta Teflon Grande', '2024-10-10 17:45:04', '152', 1, 1),
(4864, 4, 'Cinta Transparente  48mm20mt', 'Cinta Transparente  48mm20mt', 10, '1580.00', '19.00', '2300.00', 'Cinta Transparente  48mm20mt', '2024-10-10 17:45:04', '252', 1, 1),
(4865, 4, 'Cinta Transparente 150m Pretul', 'Cinta Transparente 150m Pretul', 3, '7800.00', '19.00', '10500.00', 'Cinta Transparente 150m Pretul', '2024-10-10 17:45:04', '7506240612773', 1, 1),
(4866, 4, 'Cinta Transparente 48mm40mt', 'Cinta Transparente 48mm40mt', 8, '2364.00', '19.00', '3400.00', 'Cinta Transparente 48mm40mt', '2024-10-10 17:45:04', '253', 1, 1),
(4867, 4, 'Cinta Transparente Uduke 18mts', 'Cinta Transparente Uduke 18mts', 0, '1839.00', '19.00', '2600.00', 'Cinta Transparente Uduke 18mts', '2024-10-10 17:45:04', '6973877763573', 1, 1),
(4868, 4, 'Cinta Trasparente Uduke 36mts', 'Cinta Trasparente Uduke 36mts', 11, '2200.00', '19.00', '3500.00', 'Cinta Trasparente Uduke 36mts', '2024-10-10 17:45:04', '6973877763603', 1, 1),
(4869, 4, 'Clavija  Industrial Negra', 'Clavija  Industrial Negra', 1, '2414.00', '19.00', '3000.00', 'Clavija  Industrial Negra', '2024-10-10 17:45:04', '36', 1, 1),
(4870, 4, 'Clavija Electro Control Polo Tierra 15 A', 'Clavija Electro Control Polo Tierra 15 A', 19, '3750.00', '19.00', '5200.00', 'Clavija Electro Control Polo Tierra 15 A', '2024-10-10 17:45:04', '7707356888414', 1, 1),
(4871, 4, 'Clavija Medalla Amarilla 15 Grande Redonda', 'Clavija Medalla Amarilla 15 Grande Redonda', 21, '799.00', '19.00', '1200.00', 'Clavija Medalla Amarilla 15 Grande Redonda', '2024-10-10 17:45:04', '25051', 1, 1),
(4872, 4, 'Clavija Plana Blanca', 'Clavija Plana Blanca', 0, '915.00', '19.00', '1500.00', 'Clavija Plana Blanca', '2024-10-10 17:45:04', '38', 1, 1),
(4873, 4, 'Clavija Relco 15 Grande', 'Clavija Relco 15 Grande', 11, '2050.00', '19.00', '2700.00', 'Clavija Relco 15 Grande', '2024-10-10 17:45:04', '504', 1, 1),
(4874, 4, 'Claviva o Tee Negra 3 Entradas Kontiki 15A Retie', 'Claviva o Tee Negra 3 Entradas Kontiki 15A Retie', 10, '3750.00', '19.00', '4800.00', 'Claviva o Tee Negra 3 Entradas Kontiki 15A Retie', '2024-10-10 17:45:04', 'CLA1', 1, 1),
(4875, 4, 'Clavo Acero X Unidad', 'Clavo Acero X Unidad', 14, '50.00', '19.00', '200.00', 'Clavo Acero X Unidad', '2024-10-10 17:45:04', '3CAH433GL', 1, 1),
(4876, 4, 'Clavo De Acero 2 1/2', 'Clavo De Acero 2 1/2', 2, '7051.00', '19.00', '9500.00', 'Clavo De Acero 2 1/2', '2024-10-10 17:45:04', '7705465200325', 1, 1),
(4877, 4, 'Clavo De Acero Liso 1', 'Clavo De Acero Liso 1', 0, '7051.00', '19.00', '9500.00', 'Clavo De Acero Liso 1', '2024-10-10 17:45:04', '7705465200295', 1, 1),
(4878, 4, 'Clavo De Acero Liso 1 1/2', 'Clavo De Acero Liso 1 1/2', 0, '7051.00', '19.00', '9500.00', 'Clavo De Acero Liso 1 1/2', '2024-10-10 17:45:04', '7705465200301', 1, 1),
(4879, 4, 'Clavo De Acero Liso 2', 'Clavo De Acero Liso 2', 2, '7051.00', '19.00', '9500.00', 'Clavo De Acero Liso 2', '2024-10-10 17:45:04', '7705465200318', 1, 1),
(4880, 4, 'Clavo De Acero Liso 3', 'Clavo De Acero Liso 3', 2, '7051.00', '19.00', '9500.00', 'Clavo De Acero Liso 3', '2024-10-10 17:45:04', '7705465200332', 1, 1),
(4881, 4, 'Clavo De Acero Liso 3 1/2', 'Clavo De Acero Liso 3 1/2', 0, '7051.00', '19.00', '9500.00', 'Clavo De Acero Liso 3 1/2', '2024-10-10 17:45:04', '7705465200349', 1, 1),
(4882, 4, 'Clavo De Acero Liso 3/4', 'Clavo De Acero Liso 3/4', 1, '7051.00', '19.00', '9500.00', 'Clavo De Acero Liso 3/4', '2024-10-10 17:45:04', '7705465200288', 1, 1),
(4883, 4, 'Clavo De Acero Liso 4', 'Clavo De Acero Liso 4', 1, '7051.00', '19.00', '9500.00', 'Clavo De Acero Liso 4', '2024-10-10 17:45:04', '7705465203425', 1, 1),
(4884, 4, 'Clavo De Acero Vertical 1', 'Clavo De Acero Vertical 1', 1, '7051.00', '19.00', '9500.00', 'Clavo De Acero Vertical 1', '2024-10-10 17:45:04', '7705465200134', 1, 1),
(4885, 4, 'Clavo De Acero Vertical 1 1/2', 'Clavo De Acero Vertical 1 1/2', 0, '7051.00', '19.00', '9500.00', 'Clavo De Acero Vertical 1 1/2', '2024-10-10 17:45:04', '7705465200141', 1, 1),
(4886, 4, 'Clavo De Acero Vertical 2', 'Clavo De Acero Vertical 2', 2, '7051.00', '19.00', '9500.00', 'Clavo De Acero Vertical 2', '2024-10-10 17:45:04', '7705465200158', 1, 1),
(4887, 4, 'Clavo De Acero Vertical 2 1/2', 'Clavo De Acero Vertical 2 1/2', 0, '7051.00', '19.00', '9500.00', 'Clavo De Acero Vertical 2 1/2', '2024-10-10 17:45:04', '7705465200165', 1, 1),
(4888, 4, 'Clavo De Acero Vertical 3', 'Clavo De Acero Vertical 3', 2, '7051.00', '19.00', '9500.00', 'Clavo De Acero Vertical 3', '2024-10-10 17:45:04', '7705465205917', 1, 1),
(4889, 4, 'Clavo De Acero Vertical 3 1/2', 'Clavo De Acero Vertical 3 1/2', 3, '7051.00', '19.00', '9500.00', 'Clavo De Acero Vertical 3 1/2', '2024-10-10 17:45:04', '7705465200189', 1, 1),
(4890, 4, 'Clavo De Acero Vertical 3/4', 'Clavo De Acero Vertical 3/4', 2, '7051.00', '19.00', '9500.00', 'Clavo De Acero Vertical 3/4', '2024-10-10 17:45:04', '7705465001717', 1, 1),
(4891, 4, 'Clavo De Acero Vertical 4', 'Clavo De Acero Vertical 4', 3, '7051.00', '19.00', '9500.00', 'Clavo De Acero Vertical 4', '2024-10-10 17:45:04', '7705465200196', 1, 1),
(4892, 4, 'Clavo De Acero X Unidad', 'Clavo De Acero X Unidad', 44, '50.00', '19.00', '200.00', 'Clavo De Acero X Unidad', '2024-10-10 17:45:04', '7705465200166', 1, 1),
(4893, 4, 'Clavo De Acero X Unidad 1 1/2', 'Clavo De Acero X Unidad 1 1/2', 111, '50.00', '19.00', '200.00', 'Clavo De Acero X Unidad 1 1/2', '2024-10-10 17:45:04', '7705465200142', 1, 1),
(4894, 4, 'Clavo De Caero X Unidad', 'Clavo De Caero X Unidad', 135, '50.00', '19.00', '100.00', 'Clavo De Caero X Unidad', '2024-10-10 17:45:04', '7705465200135', 1, 1),
(4895, 4, 'Codo Cpvc  Pequeño  Gerfor', 'Codo Cpvc  Pequeño  Gerfor', 12, '1074.00', '19.00', '1700.00', 'Codo Cpvc  Pequeño  Gerfor', '2024-10-10 17:45:04', '44', 1, 1),
(4896, 4, 'Codo Presion 1', 'Codo Presion 1', 0, '1090.00', '19.00', '1600.00', 'Codo Presion 1', '2024-10-10 17:45:04', '121003', 1, 1),
(4897, 4, 'Codo Presion 1/2 Pvc', 'Codo Presion 1/2 Pvc', 89, '261.00', '19.00', '500.00', 'Codo Presion 1/2 Pvc', '2024-10-10 17:45:04', '48', 1, 1),
(4898, 4, 'Codo Sanirario 45grdos X 4', 'Codo Sanirario 45grdos X 4', 0, '9500.00', '19.00', '10000.00', 'Codo Sanirario 45grdos X 4', '2024-10-10 17:45:04', '1660', 1, 1),
(4899, 4, 'Codo Sanitario 3 Amatillo Arket', 'Codo Sanitario 3 Amatillo Arket', 1, '4138.00', '19.00', '6000.00', 'Codo Sanitario 3 Amatillo Arket', '2024-10-10 17:45:04', '122003', 1, 1),
(4900, 4, 'Codo Sanitario Cxc 2', 'Codo Sanitario Cxc 2', 0, '1251.00', '19.00', '2000.00', 'Codo Sanitario Cxc 2', '2024-10-10 17:45:04', '162060', 1, 1),
(4901, 4, 'Combo Happy Blanco-Negro Semipedestal Corona', 'Combo Happy Blanco-Negro Semipedestal Corona', 1, '358841.00', '19.00', '474000.00', 'Combo Happy Blanco-Negro Semipedestal Corona', '2024-10-10 17:45:04', '108', 1, 1),
(4902, 4, 'Combo Happy II Blanco Con Semi Pedestal', 'Combo Happy II Blanco Con Semi Pedestal', 0, '398035.00', '19.00', '474000.00', 'Combo Happy II Blanco Con Semi Pedestal', '2024-10-10 17:45:04', '750108', 1, 1),
(4903, 4, 'Combo Happy Naranja  Semipedestal Corona', 'Combo Happy Naranja  Semipedestal Corona', 0, '363063.00', '19.00', '440000.00', 'Combo Happy Naranja  Semipedestal Corona', '2024-10-10 17:45:04', '109', 1, 1),
(4904, 4, 'Combo Happy Sin Pedestal Naranja', 'Combo Happy Sin Pedestal Naranja', 1, '394949.00', '19.00', '474000.00', 'Combo Happy Sin Pedestal Naranja', '2024-10-10 17:45:05', '77043720', 1, 1),
(4905, 4, 'Combo Happy Verde Semipedestal Corona', 'Combo Happy Verde Semipedestal Corona', 0, '369959.00', '19.00', '440000.00', 'Combo Happy Verde Semipedestal Corona', '2024-10-10 17:45:05', '7704372027711', 1, 1),
(4906, 4, 'Combo Laguna Azul Cielo Corona', 'Combo Laguna Azul Cielo Corona', 0, '294423.00', '19.00', '345000.00', 'Combo Laguna Azul Cielo Corona', '2024-10-10 17:45:05', '110', 1, 1),
(4907, 4, 'Combo Laguna Blanco Corona Sin Pedestal Griferia Negra', 'Combo Laguna Blanco Corona Sin Pedestal Griferia Negra', 1, '326253.00', '19.00', '365000.00', 'Combo Laguna Blanco Corona Sin Pedestal Griferia Negra', '2024-10-10 17:45:05', '111', 1, 1),
(4908, 4, 'Combo Laguna Con Pedestal Geigue', 'Combo Laguna Con Pedestal Geigue', 1, '382427.00', '19.00', '458000.00', 'Combo Laguna Con Pedestal Geigue', '2024-10-10 17:45:05', '7704372078', 1, 1),
(4909, 4, 'Concolor Beige X 2kg Corona', 'Concolor Beige X 2kg Corona', 7, '11536.00', '19.00', '15800.00', 'Concolor Beige X 2kg Corona', '2024-10-10 17:45:05', '7707181792337', 1, 1),
(4910, 4, 'Concolor Blanco Corona', 'Concolor Blanco Corona', 4, '11992.00', '19.00', '17500.00', 'Concolor Blanco Corona', '2024-10-10 17:45:05', '7707181793419', 1, 1),
(4911, 4, 'Concolor Negro Profundo Corona', 'Concolor Negro Profundo Corona', 3, '9779.00', '19.00', '15000.00', 'Concolor Negro Profundo Corona', '2024-10-10 17:45:05', '7707181792375', 1, 1),
(4912, 4, 'Conector Para Cable Cobre Rojo x5 Uduke', 'Conector Para Cable Cobre Rojo x5 Uduke', 8, '1450.00', '19.00', '2200.00', 'Conector Para Cable Cobre Rojo x5 Uduke', '2024-10-10 17:45:05', '7708376698557', 1, 1),
(4913, 4, 'Conector Para Cienta Led 110V', 'Conector Para Cienta Led 110V', 24, '3500.00', '19.00', '10000.00', 'Conector Para Cienta Led 110V', '2024-10-10 17:45:05', 'CON1', 1, 1),
(4914, 4, 'Conector Plastico Azul', 'Conector Plastico Azul', 100, '250.00', '19.00', '500.00', 'Conector Plastico Azul', '2024-10-10 17:45:05', '121', 1, 1),
(4915, 4, 'Conector Plastico Nranja', 'Conector Plastico Nranja', 0, '500.00', '19.00', '700.00', 'Conector Plastico Nranja', '2024-10-10 17:45:05', '120', 1, 1),
(4916, 4, 'Conector Platico Amarillo', 'Conector Platico Amarillo', 24, '600.00', '19.00', '800.00', 'Conector Platico Amarillo', '2024-10-10 17:45:05', '119', 1, 1),
(4917, 4, 'Conector Y  Metalico Plateado', 'Conector Y  Metalico Plateado', 3, '9609.00', '19.00', '12500.00', 'Conector Y  Metalico Plateado', '2024-10-10 17:45:05', '7501206655405', 1, 1),
(4918, 4, 'Conector Y Plastico Amarillo', 'Conector Y Plastico Amarillo', 17, '4551.00', '19.00', '6000.00', 'Conector Y Plastico Amarillo', '2024-10-10 17:45:05', '7501206655412', 1, 1),
(4919, 4, 'Conectores Platicos Rojo', 'Conectores Platicos Rojo', 0, '700.00', '19.00', '1000.00', 'Conectores Platicos Rojo', '2024-10-10 17:45:05', '118', 1, 1),
(4920, 4, 'Conjunto Griferia Sanitario Rioplast', 'Conjunto Griferia Sanitario Rioplast', 0, '10844.00', '19.00', '16500.00', 'Conjunto Griferia Sanitario Rioplast', '2024-10-10 17:45:05', '320010703', 1, 1),
(4921, 4, 'Convertidor Polo Tierra 3a2 Fino', 'Convertidor Polo Tierra 3a2 Fino', 10, '1099.00', '19.00', '1500.00', 'Convertidor Polo Tierra 3a2 Fino', '2024-10-10 17:45:05', '8039', 1, 1),
(4922, 4, 'Copa Mando 1/2 Punta Torx T-50 Truper', 'Copa Mando 1/2 Punta Torx T-50 Truper', 6, '9002.00', '19.00', '12000.00', 'Copa Mando 1/2 Punta Torx T-50 Truper', '2024-10-10 17:45:05', '7501206615331', 1, 1),
(4923, 4, 'Copa Mando 3/8 Punta Torx T-50 Truper', 'Copa Mando 3/8 Punta Torx T-50 Truper', 12, '5968.00', '19.00', '8000.00', 'Copa Mando 3/8 Punta Torx T-50 Truper', '2024-10-10 17:45:05', '7501206615768', 1, 1),
(4924, 4, 'Copa Punta Hexagonal De 1/2', 'Copa Punta Hexagonal De 1/2', 6, '11126.00', '19.00', '15001.00', 'Copa Punta Hexagonal De 1/2', '2024-10-10 17:45:05', '7501206615010', 1, 1),
(4925, 4, 'Corta Frio 8 Boccherini', 'Corta Frio 8 Boccherini', 0, '10599.00', '19.00', '15200.00', 'Corta Frio 8 Boccherini', '2024-10-10 17:45:05', '7707180678588', 1, 1),
(4926, 4, 'Cortador De Tubo 2 1/2 Truper', 'Cortador De Tubo 2 1/2 Truper', 2, '62000.00', '19.00', '76800.00', 'Cortador De Tubo 2 1/2 Truper', '2024-10-10 17:45:05', '7501206693001', 1, 1),
(4927, 4, 'Cortador De Tubo 3/4 Truper', 'Cortador De Tubo 3/4 Truper', 1, '17000.00', '19.00', '21000.00', 'Cortador De Tubo 3/4 Truper', '2024-10-10 17:45:05', '7506240635970', 1, 1),
(4928, 4, 'Cortador De Tuvo Boccherini', 'Cortador De Tuvo Boccherini', 0, '19700.00', '19.00', '28200.00', 'Cortador De Tuvo Boccherini', '2024-10-10 17:45:05', '7707766450898', 1, 1),
(4929, 4, 'Cortador De Vidrio Truper', 'Cortador De Vidrio Truper', 3, '13000.00', '19.00', '16500.00', 'Cortador De Vidrio Truper', '2024-10-10 17:45:05', '7501206602492', 1, 1),
(4930, 4, 'Cortafrio 7 Industrial Total', 'Cortafrio 7 Industrial Total', 2, '19750.00', '19.00', '24500.00', 'Cortafrio 7 Industrial Total', '2024-10-10 17:45:05', '6925582186451', 1, 1),
(4931, 4, 'Cortapernos 12 Mango Tubular Pretul', 'Cortapernos 12 Mango Tubular Pretul', 1, '45534.00', '19.00', '62500.00', 'Cortapernos 12 Mango Tubular Pretul', '2024-10-10 17:45:05', '7501206689097', 1, 1),
(4932, 4, 'Cortinero Doble Cafe 1', 'Cortinero Doble Cafe 1', 10, '1800.00', '19.00', '2600.00', 'Cortinero Doble Cafe 1', '2024-10-10 17:45:05', '8742', 1, 1),
(4933, 4, 'Cortinero Doble Cafe 1/2', 'Cortinero Doble Cafe 1/2', 10, '1376.00', '19.00', '2000.00', 'Cortinero Doble Cafe 1/2', '2024-10-10 17:45:05', '8744', 1, 1),
(4934, 4, 'Cortinero Doble Dorado 1', 'Cortinero Doble Dorado 1', 10, '1767.00', '19.00', '2500.00', 'Cortinero Doble Dorado 1', '2024-10-10 17:45:05', '8739', 1, 1),
(4935, 4, 'Cortinero Doble Dorado 1/2', 'Cortinero Doble Dorado 1/2', 10, '1598.00', '19.00', '2200.00', 'Cortinero Doble Dorado 1/2', '2024-10-10 17:45:05', '8741', 1, 1),
(4936, 4, 'Cortinero Dorado U Abierto 1/2', 'Cortinero Dorado U Abierto 1/2', 8, '520.00', '19.00', '800.00', 'Cortinero Dorado U Abierto 1/2', '2024-10-10 17:45:05', '8747', 1, 1),
(4937, 4, 'Cortinero Dorado U Abierto 3/4', 'Cortinero Dorado U Abierto 3/4', 6, '552.00', '19.00', '900.00', 'Cortinero Dorado U Abierto 3/4', '2024-10-10 17:45:05', '8746', 1, 1),
(4938, 4, 'Cortinero En U Abierto Dorado 1', 'Cortinero En U Abierto Dorado 1', 6, '684.00', '19.00', '1000.00', 'Cortinero En U Abierto Dorado 1', '2024-10-10 17:45:05', '8745', 1, 1),
(4939, 4, 'Cortinero Soporte Dorado 1', 'Cortinero Soporte Dorado 1', 9, '795.00', '19.00', '1200.00', 'Cortinero Soporte Dorado 1', '2024-10-10 17:45:05', '8733', 1, 1),
(4940, 4, 'Cruceta 14 Garvanizada', 'Cruceta 14 Garvanizada', 1, '25287.00', '19.00', '32900.00', 'Cruceta 14 Garvanizada', '2024-10-10 17:45:05', '7501206643624', 1, 1),
(4941, 4, 'Cruceta 18 Pulgadas', 'Cruceta 18 Pulgadas', 1, '34391.00', '19.00', '44800.00', 'Cruceta 18 Pulgadas', '2024-10-10 17:45:05', '7501206671450', 1, 1),
(4942, 4, 'Cruceta 20 Galvanizada', 'Cruceta 20 Galvanizada', 1, '35402.00', '19.00', '46100.00', 'Cruceta 20 Galvanizada', '2024-10-10 17:45:05', '7501206641873', 1, 1),
(4943, 4, 'Cubreboca Tipo Concha', 'Cubreboca Tipo Concha', 50, '606.00', '19.00', '1200.00', 'Cubreboca Tipo Concha', '2024-10-10 17:45:05', '7501206675120', 1, 1),
(4944, 4, 'Cuchilla  Para Guadaña Colima', 'Cuchilla  Para Guadaña Colima', 0, '7500.00', '19.00', '11000.00', 'Cuchilla  Para Guadaña Colima', '2024-10-10 17:45:05', '7706912802857', 1, 1),
(4945, 4, 'Cuchilla De Repuesto BISTURI YW500', 'Cuchilla De Repuesto BISTURI YW500', 5, '2400.00', '19.00', '3500.00', 'Cuchilla De Repuesto BISTURI YW500', '2024-10-10 17:45:05', '7707766453295', 1, 1),
(4946, 4, 'Cuchilla Guadaña 35x1 C13 Pesada Herragro', 'Cuchilla Guadaña 35x1 C13 Pesada Herragro', 1, '8005.00', '19.00', '11500.00', 'Cuchilla Guadaña 35x1 C13 Pesada Herragro', '2024-10-10 17:45:05', '7706335001882', 1, 1),
(4947, 4, 'Cuchilla Guadaña 35x1c16 Roja Liviana', 'Cuchilla Guadaña 35x1c16 Roja Liviana', 3, '8005.00', '19.00', '11500.00', 'Cuchilla Guadaña 35x1c16 Roja Liviana', '2024-10-10 17:45:05', '7706335001790', 1, 1),
(4948, 4, 'Cuerda De Amarre Uduke', 'Cuerda De Amarre Uduke', 1, '3435.00', '19.00', '5000.00', 'Cuerda De Amarre Uduke', '2024-10-10 17:45:05', '6973653170878', 1, 1),
(4949, 4, 'Curva 1/2 Pasada', 'Curva 1/2 Pasada', 252, '410.00', '19.00', '600.00', 'Curva 1/2 Pasada', '2024-10-10 17:45:05', '200182', 1, 1),
(4950, 4, 'Curva Conduit 90x1/2 Electrica Gerfor', 'Curva Conduit 90x1/2 Electrica Gerfor', 0, '1158.00', '19.00', '1700.00', 'Curva Conduit 90x1/2 Electrica Gerfor', '2024-10-10 17:45:05', '74', 1, 1),
(4951, 4, 'Curva Conduit 90x3/4 Electrica Gerfor', 'Curva Conduit 90x3/4 Electrica Gerfor', 12, '1871.00', '19.00', '2700.00', 'Curva Conduit 90x3/4 Electrica Gerfor', '2024-10-10 17:45:05', '75', 1, 1),
(4952, 4, 'Cuñete Blanco Arena Corona T2', 'Cuñete Blanco Arena Corona T2', 1, '146732.00', '19.00', '247000.00', 'Cuñete Blanco Arena Corona T2', '2024-10-10 17:45:05', '7705389008663', 1, 1),
(4953, 4, 'Cuñete Blanco Hueso', 'Cuñete Blanco Hueso', 1, '146732.00', '19.00', '247000.00', 'Cuñete Blanco Hueso', '2024-10-10 17:45:05', '7705389006560', 1, 1),
(4954, 4, 'Cuñete De Estuco Relleno La Roca', 'Cuñete De Estuco Relleno La Roca', 3, '56498.00', '19.00', '75000.00', 'Cuñete De Estuco Relleno La Roca', '2024-10-10 17:45:05', '61', 1, 1),
(4955, 4, 'Cuñete Fondeo Blanca T3 Corona', 'Cuñete Fondeo Blanca T3 Corona', 1, '118961.00', '19.00', '140000.00', 'Cuñete Fondeo Blanca T3 Corona', '2024-10-10 17:45:05', '7705389010581', 1, 1),
(4956, 4, 'Cuñete Lavable Blanco Almendra', 'Cuñete Lavable Blanco Almendra', 1, '146732.00', '19.00', '360000.00', 'Cuñete Lavable Blanco Almendra', '2024-10-10 17:45:05', '7705389003217', 1, 1),
(4957, 4, 'Cuñete Lavable Blanco Durazno T1 Corona', 'Cuñete Lavable Blanco Durazno T1 Corona', 1, '146732.00', '19.00', '360000.00', 'Cuñete Lavable Blanco Durazno T1 Corona', '2024-10-10 17:45:05', '7705389003293', 1, 1),
(4958, 4, 'Cuñete Professional Blanco T1 Corona', 'Cuñete Professional Blanco T1 Corona', 3, '199119.00', '19.00', '300000.00', 'Cuñete Professional Blanco T1 Corona', '2024-10-10 17:45:05', '7705389011427', 1, 1),
(4959, 4, 'Cuñete Textuco Interior', 'Cuñete Textuco Interior', 0, '39562.00', '19.00', '63000.00', 'Cuñete Textuco Interior', '2024-10-10 17:45:05', '7705389001008', 1, 1),
(4960, 4, 'Cuñete Total Blanca T2 Corona', 'Cuñete Total Blanca T2 Corona', 1, '151918.00', '19.00', '225000.00', 'Cuñete Total Blanca T2 Corona', '2024-10-10 17:45:05', '7705389010758', 1, 1),
(4961, 4, 'DESTORNILLADOR 1/4X6´PUNTA IMANTADA TSDSL6150', 'DESTORNILLADOR 1/4X6´PUNTA IMANTADA TSDSL6150', 2, '5400.00', '19.00', '8000.00', 'DESTORNILLADOR 1/4X6´PUNTA IMANTADA TSDSL6150', '2024-10-10 17:45:05', '6942210220325', 1, 1),
(4962, 4, 'Decametro X 20 Mtrs', 'Decametro X 20 Mtrs', 1, '11050.00', '19.00', '13500.00', 'Decametro X 20 Mtrs', '2024-10-10 17:45:05', '6973877760794', 1, 1),
(4963, 4, 'Decametro X 30 Mtrs', 'Decametro X 30 Mtrs', 0, '10200.00', '19.00', '15000.00', 'Decametro X 30 Mtrs', '2024-10-10 17:45:05', '6973877760800', 1, 1),
(4964, 4, 'Desague Flexible Doble  Unifer', 'Desague Flexible Doble  Unifer', 1, '14498.00', '19.00', '18500.00', 'Desague Flexible Doble  Unifer', '2024-10-10 17:45:05', '55', 1, 1),
(4965, 4, 'Desague Sencillo Multiusos Cromado', 'Desague Sencillo Multiusos Cromado', 0, '8650.00', '19.00', '12500.00', 'Desague Sencillo Multiusos Cromado', '2024-10-10 17:45:05', '2528', 1, 1),
(4966, 4, 'Desarmador X2 Mini Boccherini', 'Desarmador X2 Mini Boccherini', 2, '4700.00', '19.00', '7000.00', 'Desarmador X2 Mini Boccherini', '2024-10-10 17:45:05', '7707180678854', 1, 1),
(4967, 4, 'Desarmadores X2', 'Desarmadores X2', 1, '6999.00', '19.00', '10000.00', 'Desarmadores X2', '2024-10-10 17:45:05', '7707180678847', 1, 1),
(4968, 4, 'Destornillador Aislado 1000 V Thtisph2100', 'Destornillador Aislado 1000 V Thtisph2100', 2, '6700.00', '19.00', '10000.00', 'Destornillador Aislado 1000 V Thtisph2100', '2024-10-10 17:45:05', '6925582172355', 1, 1),
(4969, 4, 'Destornillador Cromo Juego X10 Piezas Thtd251001', 'Destornillador Cromo Juego X10 Piezas Thtd251001', 1, '18200.00', '19.00', '25500.00', 'Destornillador Cromo Juego X10 Piezas Thtd251001', '2024-10-10 17:45:05', '6941639815105', 1, 1),
(4970, 4, 'Destornillador Doble Punta Grande U.S.A 1688 4x4-6', 'Destornillador Doble Punta Grande U.S.A 1688 4x4-6', 2, '2250.00', '19.00', '4000.00', 'Destornillador Doble Punta Grande U.S.A 1688 4x4-6', '2024-10-10 17:45:05', '8268A', 1, 1),
(4971, 4, 'Destornillador Phillips 5 Mm X 3', 'Destornillador Phillips 5 Mm X 3', 1, '1900.00', '19.00', '2900.00', 'Destornillador Phillips 5 Mm X 3', '2024-10-10 17:45:05', '7707766455756', 1, 1),
(4972, 4, 'Destornillador Phillips 5 Mm X 6', 'Destornillador Phillips 5 Mm X 6', 4, '2300.00', '19.00', '3300.00', 'Destornillador Phillips 5 Mm X 6', '2024-10-10 17:45:05', '7707766458504', 1, 1),
(4973, 4, 'Destornillador Phillips 5mm X 4', 'Destornillador Phillips 5mm X 4', 3, '2000.00', '19.00', '3000.00', 'Destornillador Phillips 5mm X 4', '2024-10-10 17:45:05', '7707766457606', 1, 1),
(4974, 4, 'Destornillador Phillips 6mm X 1 1/2', 'Destornillador Phillips 6mm X 1 1/2', 2, '1800.00', '19.00', '2600.00', 'Destornillador Phillips 6mm X 1 1/2', '2024-10-10 17:45:05', '7707766454681', 1, 1),
(4975, 4, 'Destornillador Phillips 6mm X 4', 'Destornillador Phillips 6mm X 4', 3, '2200.00', '19.00', '3200.00', 'Destornillador Phillips 6mm X 4', '2024-10-10 17:45:05', '7707766453202', 1, 1),
(4976, 4, 'Destornillador Phillips 6mm X 6', 'Destornillador Phillips 6mm X 6', 4, '2500.00', '19.00', '3600.00', 'Destornillador Phillips 6mm X 6', '2024-10-10 17:45:05', '7707766453479', 1, 1),
(4977, 4, 'Destornillador Phillips 6mm X 8', 'Destornillador Phillips 6mm X 8', 2, '2800.00', '19.00', '4000.00', 'Destornillador Phillips 6mm X 8', '2024-10-10 17:45:05', '7707766458610', 1, 1),
(4978, 4, 'Destornillador Phillips 8mm X6 Boccherini', 'Destornillador Phillips 8mm X6 Boccherini', 4, '3799.00', '19.00', '5500.00', 'Destornillador Phillips 8mm X6 Boccherini', '2024-10-10 17:45:05', '7707766458177', 1, 1),
(4979, 4, 'Destornillador Plano 5mm X 3', 'Destornillador Plano 5mm X 3', 2, '1800.00', '19.00', '2600.00', 'Destornillador Plano 5mm X 3', '2024-10-10 17:45:05', '7707766458559', 1, 1),
(4980, 4, 'Destornillador Plano 6mm X 1 1/2', 'Destornillador Plano 6mm X 1 1/2', 3, '1700.00', '19.00', '2500.00', 'Destornillador Plano 6mm X 1 1/2', '2024-10-10 17:45:05', '7707766452373', 1, 1),
(4981, 4, 'Destornillador Plano 6mm X 4', 'Destornillador Plano 6mm X 4', 3, '2100.00', '19.00', '3000.00', 'Destornillador Plano 6mm X 4', '2024-10-10 17:45:05', '7707766457507', 1, 1),
(4982, 4, 'Destornillador Plano 6mm X 6', 'Destornillador Plano 6mm X 6', 4, '2400.00', '19.00', '3500.00', 'Destornillador Plano 6mm X 6', '2024-10-10 17:45:05', '7707766459075', 1, 1),
(4983, 4, 'Destornillador Plano 8mm X6', 'Destornillador Plano 8mm X6', 4, '3599.00', '19.00', '5200.00', 'Destornillador Plano 8mm X6', '2024-10-10 17:45:05', '7707766457736', 1, 1),
(4984, 4, 'Destornillador Punta Doble Uduke', 'Destornillador Punta Doble Uduke', 1, '3826.00', '19.00', '5500.00', 'Destornillador Punta Doble Uduke', '2024-10-10 17:45:05', '835', 1, 1),
(4985, 4, 'Destornilllador Plano 5 Mm X 6', 'Destornilllador Plano 5 Mm X 6', 4, '2200.00', '19.00', '3200.00', 'Destornilllador Plano 5 Mm X 6', '2024-10-10 17:45:05', '7707766459570', 1, 1),
(4986, 4, 'Destornilllador Plano 5mm X 4', 'Destornilllador Plano 5mm X 4', 4, '1900.00', '19.00', '2900.00', 'Destornilllador Plano 5mm X 4', '2024-10-10 17:45:05', '7707766450362', 1, 1),
(4987, 4, 'Destornilllador Plano 6mm X 8', 'Destornilllador Plano 6mm X 8', 4, '2700.00', '19.00', '3900.00', 'Destornilllador Plano 6mm X 8', '2024-10-10 17:45:05', '7707766453325', 1, 1),
(4988, 4, 'Diablo Rojo X300gr', 'Diablo Rojo X300gr', 5, '2800.00', '19.00', '4000.00', 'Diablo Rojo X300gr', '2024-10-10 17:45:05', '7709694849188', 1, 1);
INSERT INTO `inventario` (`id`, `user_id`, `nombre`, `descripcion`, `stock`, `precio_costo`, `impuesto`, `precio_venta`, `otro_dato`, `fecha_ingreso`, `codigo_barras`, `departamento_id`, `categoria_id`) VALUES
(4989, 4, 'Disco Corte Madera 7 Duke Energy Profesional', 'Disco Corte Madera 7 Duke Energy Profesional', 1, '10550.00', '19.00', '14500.00', 'Disco Corte Madera 7 Duke Energy Profesional', '2024-10-10 17:45:05', '6973877762903', 1, 1),
(4990, 4, 'Disco Corte Metal 4 1/2 Abracol', 'Disco Corte Metal 4 1/2 Abracol', 3, '2273.00', '19.00', '4000.00', 'Disco Corte Metal 4 1/2 Abracol', '2024-10-10 17:45:05', '7705059841613', 1, 1),
(4991, 4, 'Disco Corte Metal 4 1/2 Dewalt', 'Disco Corte Metal 4 1/2 Dewalt', 3, '2800.00', '19.00', '4500.00', 'Disco Corte Metal 4 1/2 Dewalt', '2024-10-10 17:45:05', '28877321608', 1, 1),
(4992, 4, 'Disco Corte Metal Bosh 4 1/2 Plano', 'Disco Corte Metal Bosh 4 1/2 Plano', 2, '2150.00', '19.00', '2900.00', 'Disco Corte Metal Bosh 4 1/2 Plano', '2024-10-10 17:45:05', '4059952524764', 1, 1),
(4993, 4, 'Disco Corte Metal Unitec Grande', 'Disco Corte Metal Unitec Grande', 1, '4500.00', '19.00', '7000.00', 'Disco Corte Metal Unitec Grande', '2024-10-10 17:45:05', '40', 1, 1),
(4994, 4, 'Disco De Corte Guadaña 9 X 40D Tungsteno', 'Disco De Corte Guadaña 9 X 40D Tungsteno', 25, '23500.00', '19.00', '33500.00', 'Disco De Corte Guadaña 9 X 40D Tungsteno', '2024-10-10 17:45:05', '82023990', 1, 1),
(4995, 4, 'Disco De Corte Metal Grande Dewalt', 'Disco De Corte Metal Grande Dewalt', 15, '5000.00', '19.00', '9000.00', 'Disco De Corte Metal Grande Dewalt', '2024-10-10 17:45:05', '28877321639', 1, 1),
(4996, 4, 'Disco De Sierra 4 1/2 Barracuda 24 Dientes', 'Disco De Sierra 4 1/2 Barracuda 24 Dientes', 0, '8925.00', '19.00', '13000.00', 'Disco De Sierra 4 1/2 Barracuda 24 Dientes', '2024-10-10 17:45:05', '7706912820363', 1, 1),
(4997, 4, 'Disco Diamantado De 4x2.2', 'Disco Diamantado De 4x2.2', 5, '16184.00', '19.00', '21500.00', 'Disco Diamantado De 4x2.2', '2024-10-10 17:45:05', '7501206652510', 1, 1),
(4998, 4, 'Disco Diamantado Segmentado', 'Disco Diamantado Segmentado', 8, '5141.00', '19.00', '7500.00', 'Disco Diamantado Segmentado', '2024-10-10 17:45:05', '612011', 1, 1),
(4999, 4, 'Disco Flap 4 1/2 Duke Energy', 'Disco Flap 4 1/2 Duke Energy', 1, '3350.00', '19.00', '4500.00', 'Disco Flap 4 1/2 Duke Energy', '2024-10-10 17:45:05', '6973653178751', 1, 1),
(5000, 4, 'Disco Metal ARKET 4 1/2', 'Disco Metal ARKET 4 1/2', 41, '1133.00', '19.00', '3000.00', 'Disco Metal ARKET 4 1/2', '2024-10-10 17:45:05', '611001', 1, 1),
(5001, 4, 'Disco Para Desbaste 4 1/2 Abracol', 'Disco Para Desbaste 4 1/2 Abracol', 1, '3381.00', '19.00', '4500.00', 'Disco Para Desbaste 4 1/2 Abracol', '2024-10-10 17:45:05', '7705509005251', 1, 1),
(5002, 4, 'Disco T41 4-1/2 x 1mm Corte Fino En Metal Pretul', 'Disco T41 4-1/2 x 1mm Corte Fino En Metal Pretul', 10, '2451.00', '19.00', '3600.00', 'Disco T41 4-1/2 x 1mm Corte Fino En Metal Pretul', '2024-10-10 17:45:05', '7506240624622', 1, 1),
(5003, 4, 'Disco T41 4-1/2 x 1mm Multimaterial Truper', 'Disco T41 4-1/2 x 1mm Multimaterial Truper', 2, '3619.00', '19.00', '5200.00', 'Disco T41 4-1/2 x 1mm Multimaterial Truper', '2024-10-10 17:45:05', '7506240658535', 1, 1),
(5004, 4, 'Disco T42 4-1/2 x 3.2mm Metal Truper', 'Disco T42 4-1/2 x 3.2mm Metal Truper', 4, '3385.00', '19.00', '4800.00', 'Disco T42 4-1/2 x 3.2mm Metal Truper', '2024-10-10 17:45:05', '7501206622247', 1, 1),
(5005, 4, 'Disco Tipo 41 De 9 X 2 Mm Corte Metal', 'Disco Tipo 41 De 9 X 2 Mm Corte Metal', 5, '5967.00', '19.00', '8000.00', 'Disco Tipo 41 De 9 X 2 Mm Corte Metal', '2024-10-10 17:45:05', '7506240624615', 1, 1),
(5006, 4, 'Dispensador De Papel Higenico Boccherini Grande', 'Dispensador De Papel Higenico Boccherini Grande', 1, '38500.00', '19.00', '55000.00', 'Dispensador De Papel Higenico Boccherini Grande', '2024-10-10 17:45:05', '7707180678298', 1, 1),
(5007, 4, 'Dispensador Ph Boccherini Pequeño', 'Dispensador Ph Boccherini Pequeño', 2, '11999.00', '19.00', '20000.00', 'Dispensador Ph Boccherini Pequeño', '2024-10-10 17:45:05', '7707180678267', 1, 1),
(5008, 4, 'Driver Fuente Para Panel Led 24w', 'Driver Fuente Para Panel Led 24w', 6, '5500.00', '19.00', '7500.00', 'Driver Fuente Para Panel Led 24w', '2024-10-10 17:45:05', 'LEDDRIVER', 1, 1),
(5009, 4, 'Driver Led Para Panel Led 18w', 'Driver Led Para Panel Led 18w', 6, '5050.00', '19.00', '6500.00', 'Driver Led Para Panel Led 18w', '2024-10-10 17:45:05', 'DR1', 1, 1),
(5010, 4, 'Driver Led Para Panel Led 3w', 'Driver Led Para Panel Led 3w', 1, '1700.00', '19.00', '2800.00', 'Driver Led Para Panel Led 3w', '2024-10-10 17:45:05', 'DR2', 1, 1),
(5011, 4, 'Driver Led Para Panel Led 48w', 'Driver Led Para Panel Led 48w', 2, '8500.00', '19.00', '11500.00', 'Driver Led Para Panel Led 48w', '2024-10-10 17:45:05', 'DR3', 1, 1),
(5012, 4, 'Ducha Baño 4 Plast Grifos', 'Ducha Baño 4 Plast Grifos', 1, '11650.00', '19.00', '16000.00', 'Ducha Baño 4 Plast Grifos', '2024-10-10 17:45:05', '7700032722620', 1, 1),
(5013, 4, 'Ducha Bidet Boccherini', 'Ducha Bidet Boccherini', 0, '45000.00', '19.00', '58000.00', 'Ducha Bidet Boccherini', '2024-10-10 17:45:05', '7707180675952', 1, 1),
(5014, 4, 'Ducha Cromada 4 Hidrogriferia', 'Ducha Cromada 4 Hidrogriferia', 4, '8600.00', '19.00', '12500.00', 'Ducha Cromada 4 Hidrogriferia', '2024-10-10 17:45:05', '2530', 1, 1),
(5015, 4, 'Ducha Electrica Graduable Boccerini', 'Ducha Electrica Graduable Boccerini', 1, '74650.00', '19.00', '93300.00', 'Ducha Electrica Graduable Boccerini', '2024-10-10 17:45:05', '7707180672081', 1, 1),
(5016, 4, 'Ducha Plastica Pinpinoy', 'Ducha Plastica Pinpinoy', 0, '12999.00', '19.00', '19000.00', 'Ducha Plastica Pinpinoy', '2024-10-10 17:45:05', '116', 1, 1),
(5017, 4, 'Ducha Regadera Cromada Cuadrada 8 Uduke', 'Ducha Regadera Cromada Cuadrada 8 Uduke', 0, '19300.00', '19.00', '28000.00', 'Ducha Regadera Cromada Cuadrada 8 Uduke', '2024-10-10 17:45:05', '6973653178836', 1, 1),
(5018, 4, 'Ducha Regadera Negra Redonda 8 Uduke', 'Ducha Regadera Negra Redonda 8 Uduke', 1, '23850.00', '19.00', '33400.00', 'Ducha Regadera Negra Redonda 8 Uduke', '2024-10-10 17:45:05', '6973877766765', 1, 1),
(5019, 4, 'Ducha Regadera Negra Uduke Ht1566', 'Ducha Regadera Negra Uduke Ht1566', 4, '10800.00', '19.00', '15000.00', 'Ducha Regadera Negra Uduke Ht1566', '2024-10-10 17:45:05', '6973877765720', 1, 1),
(5020, 4, 'Ducha Regadera Plast Grifos', 'Ducha Regadera Plast Grifos', 3, '7550.00', '19.00', '11000.00', 'Ducha Regadera Plast Grifos', '2024-10-10 17:45:05', '7700032709621', 1, 1),
(5021, 4, 'Duplex #12 X Metro Centelsa', 'Duplex #12 X Metro Centelsa', 99, '3700.00', '19.00', '4500.00', 'Duplex #12 X Metro Centelsa', '2024-10-10 17:45:05', '770731321204', 1, 1),
(5022, 4, 'Duplex #14 Dmrc X Metros', 'Duplex #14 Dmrc X Metros', 39, '2400.00', '19.00', '3500.00', 'Duplex #14 Dmrc X Metros', '2024-10-10 17:45:05', '770731321205', 1, 1),
(5023, 4, 'Duplex Rollo #12 Dmrc x 100metros', 'Duplex Rollo #12 Dmrc x 100metros', 2, '70000.00', '19.00', '94000.00', 'Duplex Rollo #12 Dmrc x 100metros', '2024-10-10 17:45:05', '770731321203', 1, 1),
(5024, 4, 'Duplex Rollo #14 Dmrc  x100metros', 'Duplex Rollo #14 Dmrc  x100metros', 1, '60000.00', '19.00', '80000.00', 'Duplex Rollo #14 Dmrc  x100metros', '2024-10-10 17:45:05', '770731321202', 1, 1),
(5025, 4, 'ESQUINERO LISO PVC R/G 3/4 ( 2.40 CM )', 'ESQUINERO LISO PVC R/G 3/4 ( 2.40 CM )', 20, '2750.00', '19.00', '3700.00', 'ESQUINERO LISO PVC R/G 3/4 ( 2.40 CM )', '2024-10-10 17:45:05', 'Z12', 1, 1),
(5026, 4, 'ESQUINERO PERFORADO 1 PULGADA ( 2.40 CM )', 'ESQUINERO PERFORADO 1 PULGADA ( 2.40 CM )', 5, '3400.00', '19.00', '4600.00', 'ESQUINERO PERFORADO 1 PULGADA ( 2.40 CM )', '2024-10-10 17:45:05', 'Z13', 1, 1),
(5027, 4, 'ESQUINERO PERFORADO 3/4 ( 2.40 CM )', 'ESQUINERO PERFORADO 3/4 ( 2.40 CM )', 25, '2950.00', '19.00', '3900.00', 'ESQUINERO PERFORADO 3/4 ( 2.40 CM )', '2024-10-10 17:45:05', 'Z11', 1, 1),
(5028, 4, 'Economizador En Abs', 'Economizador En Abs', 3, '13049.00', '19.00', '18000.00', 'Economizador En Abs', '2024-10-10 17:45:05', '7707180670339', 1, 1),
(5029, 4, 'Electrodo Loza', 'Electrodo Loza', 6, '3000.00', '19.00', '4500.00', 'Electrodo Loza', '2024-10-10 17:45:05', '697387776041', 1, 1),
(5030, 4, 'Empaque Tanque Sanitario Boccherino', 'Empaque Tanque Sanitario Boccherino', 10, '1900.00', '19.00', '3200.00', 'Empaque Tanque Sanitario Boccherino', '2024-10-10 17:45:05', '7707180671374', 1, 1),
(5031, 4, 'Engrapadora Tuper', 'Engrapadora Tuper', 1, '85000.00', '19.00', '105400.00', 'Engrapadora Tuper', '2024-10-10 17:45:05', '7501206671214', 1, 1),
(5032, 4, 'Escalera Tipo Tijera De 5 Peldaños Con Bandeja 150kg Truper', 'Escalera Tipo Tijera De 5 Peldaños Con Bandeja 150kg Truper', 1, '213932.00', '19.00', '290000.00', 'Escalera Tipo Tijera De 5 Peldaños Con Bandeja 150kg Truper', '2024-10-10 17:45:05', '7501206672655', 1, 1),
(5033, 4, 'Escarcha Para Graniplas', 'Escarcha Para Graniplas', 1, '5000.00', '19.00', '9000.00', 'Escarcha Para Graniplas', '2024-10-10 17:45:05', '11', 1, 1),
(5034, 4, 'Escoba Barre Prados', 'Escoba Barre Prados', 4, '6100.00', '19.00', '9500.00', 'Escoba Barre Prados', '2024-10-10 17:45:05', '615810', 1, 1),
(5035, 4, 'Escuadra Amarilla 10 Ht30146', 'Escuadra Amarilla 10 Ht30146', 3, '3300.00', '19.00', '5200.00', 'Escuadra Amarilla 10 Ht30146', '2024-10-10 17:45:05', '6973653177686', 1, 1),
(5036, 4, 'Escuadra Amarilla 12ht30147', 'Escuadra Amarilla 12ht30147', 3, '4300.00', '19.00', '6500.00', 'Escuadra Amarilla 12ht30147', '2024-10-10 17:45:05', '6973653177693', 1, 1),
(5037, 4, 'Escuadra Amarilla 14 Ht30148', 'Escuadra Amarilla 14 Ht30148', 3, '3700.00', '19.00', '5800.00', 'Escuadra Amarilla 14 Ht30148', '2024-10-10 17:45:05', '6973653177709', 1, 1),
(5038, 4, 'Escuadra Para Carpintero 10 Boccherini', 'Escuadra Para Carpintero 10 Boccherini', 1, '6780.00', '19.00', '10500.00', 'Escuadra Para Carpintero 10 Boccherini', '2024-10-10 17:45:05', '7707180679257', 1, 1),
(5039, 4, 'Escudo Cromado', 'Escudo Cromado', 6, '1100.00', '19.00', '2000.00', 'Escudo Cromado', '2024-10-10 17:45:05', '2533', 1, 1),
(5040, 4, 'Esmalte Amarillo Oxido Galon', 'Esmalte Amarillo Oxido Galon', 0, '61667.00', '19.00', '77000.00', 'Esmalte Amarillo Oxido Galon', '2024-10-10 17:45:05', '1600210', 1, 1),
(5041, 4, 'Esmalte Blanco Galon Bler', 'Esmalte Blanco Galon Bler', 3, '61667.00', '19.00', '83000.00', 'Esmalte Blanco Galon Bler', '2024-10-10 17:45:05', '1600010', 1, 1),
(5042, 4, 'Esoejo Marco Dorado', 'Esoejo Marco Dorado', 1, '288000.00', '19.00', '360000.00', 'Esoejo Marco Dorado', '2024-10-10 17:45:05', '8278', 1, 1),
(5043, 4, 'Espatula 5 Mango Naranja Boccherini', 'Espatula 5 Mango Naranja Boccherini', 0, '3213.00', '19.00', '5000.00', 'Espatula 5 Mango Naranja Boccherini', '2024-10-10 17:45:05', '7707180679455', 1, 1),
(5044, 4, 'Espatula Acero Inixidable Atlas 10cm', 'Espatula Acero Inixidable Atlas 10cm', 5, '3799.00', '19.00', '5200.00', 'Espatula Acero Inixidable Atlas 10cm', '2024-10-10 17:45:05', '7896380191628', 1, 1),
(5045, 4, 'Espatula Acero Inoxidable 12cm Atlas', 'Espatula Acero Inoxidable 12cm Atlas', 3, '4299.00', '19.00', '6000.00', 'Espatula Acero Inoxidable 12cm Atlas', '2024-10-10 17:45:05', '7896380190799', 1, 1),
(5046, 4, 'Espatula De Acero 8cm Atlas', 'Espatula De Acero 8cm Atlas', 1, '3451.00', '19.00', '4700.00', 'Espatula De Acero 8cm Atlas', '2024-10-10 17:45:05', '7896380191611', 1, 1),
(5047, 4, 'Espatula Mango Naranja 2 Boccherini', 'Espatula Mango Naranja 2 Boccherini', 0, '2142.00', '19.00', '3500.00', 'Espatula Mango Naranja 2 Boccherini', '2024-10-10 17:45:05', '7707180679424', 1, 1),
(5048, 4, 'Espatula Para Drywall 10', 'Espatula Para Drywall 10', 4, '6199.00', '19.00', '9000.00', 'Espatula Para Drywall 10', '2024-10-10 17:45:05', '7707766457804', 1, 1),
(5049, 4, 'Espatula Para Drywall Boccherini 8', 'Espatula Para Drywall Boccherini 8', 3, '5300.00', '19.00', '7600.00', 'Espatula Para Drywall Boccherini 8', '2024-10-10 17:45:05', '7707766450478', 1, 1),
(5050, 4, 'Espatula Plastica Para Masilla Con Mango', 'Espatula Plastica Para Masilla Con Mango', 1, '1000.00', '19.00', '1500.00', 'Espatula Plastica Para Masilla Con Mango', '2024-10-10 17:45:05', '9', 1, 1),
(5051, 4, 'Espatula masilla pequeña', 'Espatula masilla pequeña', 6, '496.00', '19.00', '1000.00', 'Espatula masilla pequeña', '2024-10-10 17:45:05', '7707342741112', 1, 1),
(5052, 4, 'Espejo Con Marco Dorado+ Led 6060', 'Espejo Con Marco Dorado+ Led 6060', 3, '0.00', '19.00', '360000.00', 'Espejo Con Marco Dorado+ Led 6060', '2024-10-10 17:45:05', '5689', 1, 1),
(5053, 4, 'Espejo Led 60x80 Ovalado', 'Espejo Led 60x80 Ovalado', 2, '280000.00', '19.00', '365000.00', 'Espejo Led 60x80 Ovalado', '2024-10-10 17:45:05', '81130', 1, 1),
(5054, 4, 'Espejo Led Redondo 8080 Palo Rosa', 'Espejo Led Redondo 8080 Palo Rosa', 0, '313999.00', '19.00', '395000.00', 'Espejo Led Redondo 8080 Palo Rosa', '2024-10-10 17:45:05', '8000200', 1, 1),
(5055, 4, 'Espuma Expansiva 500 Ml', 'Espuma Expansiva 500 Ml', 1, '25287.00', '19.00', '33000.00', 'Espuma Expansiva 500 Ml', '2024-10-10 17:45:05', '7506240620280', 1, 1),
(5056, 4, 'Espuma Expansiva De 300ml Truper', 'Espuma Expansiva De 300ml Truper', 1, '19218.00', '19.00', '26000.00', 'Espuma Expansiva De 300ml Truper', '2024-10-10 17:45:05', '7506240620273', 1, 1),
(5057, 4, 'Estuco Flex X 25kg Esplacol', 'Estuco Flex X 25kg Esplacol', 10, '32450.00', '19.00', '41900.00', 'Estuco Flex X 25kg Esplacol', '2024-10-10 17:45:05', '4721', 1, 1),
(5058, 4, 'Estuco Perfecto Bolsa x20kg', 'Estuco Perfecto Bolsa x20kg', 2, '30999.00', '19.00', '45000.00', 'Estuco Perfecto Bolsa x20kg', '2024-10-10 17:45:05', '29', 1, 1),
(5059, 4, 'Estuco Plastico Acrilico Exterior Esplacol X 28 Kls', 'Estuco Plastico Acrilico Exterior Esplacol X 28 Kls', 0, '68588.00', '19.00', '79800.00', 'Estuco Plastico Acrilico Exterior Esplacol X 28 Kls', '2024-10-10 17:45:05', '60804', 1, 1),
(5060, 4, 'Estuco Plastico Acrilico Interior X 28 Kls Esplacol', 'Estuco Plastico Acrilico Interior X 28 Kls Esplacol', 0, '56778.00', '19.00', '71500.00', 'Estuco Plastico Acrilico Interior X 28 Kls Esplacol', '2024-10-10 17:45:05', '60903', 1, 1),
(5061, 4, 'Estucor 1/4 Corona', 'Estucor 1/4 Corona', 0, '12500.00', '19.00', '18000.00', 'Estucor 1/4 Corona', '2024-10-10 17:45:05', '153478', 1, 1),
(5062, 4, 'Estucor Plastico Galon 6k Corona', 'Estucor Plastico Galon 6k Corona', 0, '25000.00', '19.00', '31000.00', 'Estucor Plastico Galon 6k Corona', '2024-10-10 17:45:05', '7707181792160', 1, 1),
(5063, 4, 'Estucor Resane Corona  1/16', 'Estucor Resane Corona  1/16', 30, '3500.00', '19.00', '7000.00', 'Estucor Resane Corona  1/16', '2024-10-10 17:45:05', '7705389000209', 1, 1),
(5064, 4, 'Estukados Sika X 40k', 'Estukados Sika X 40k', 38, '50780.00', '19.00', '61900.00', 'Estukados Sika X 40k', '2024-10-10 17:45:05', '954316784', 1, 1),
(5065, 4, 'Extención Naranja Brickell 3mts BK-1596', 'Extención Naranja Brickell 3mts BK-1596', 2, '5550.00', '19.00', '8000.00', 'Extención Naranja Brickell 3mts BK-1596', '2024-10-10 17:45:05', '7450077032009', 1, 1),
(5066, 4, 'Extención Naranja Brickell 4mts SB-582', 'Extención Naranja Brickell 4mts SB-582', 2, '6850.00', '19.00', '10000.00', 'Extención Naranja Brickell 4mts SB-582', '2024-10-10 17:45:05', '7450077009957', 1, 1),
(5067, 4, 'Extención Naranja Globy 10mts WJ-C01', 'Extención Naranja Globy 10mts WJ-C01', 2, '13700.00', '19.00', '19500.00', 'Extención Naranja Globy 10mts WJ-C01', '2024-10-10 17:45:05', '7453038488143', 1, 1),
(5068, 4, 'Extension En Cadena De Luces X 24 Bombillos', 'Extension En Cadena De Luces X 24 Bombillos', 1, '180000.00', '19.00', '240000.00', 'Extension En Cadena De Luces X 24 Bombillos', '2024-10-10 17:45:05', '607766407779', 1, 1),
(5069, 4, 'Extension Lavaplatos', 'Extension Lavaplatos', 6, '1430.00', '19.00', '2000.00', 'Extension Lavaplatos', '2024-10-10 17:45:05', '2522', 1, 1),
(5070, 4, 'Extension Magnetica Articulada 1/4 Truper 11875', 'Extension Magnetica Articulada 1/4 Truper 11875', 2, '15000.00', '19.00', '19500.00', 'Extension Magnetica Articulada 1/4 Truper 11875', '2024-10-10 17:45:05', '7506240676294', 1, 1),
(5071, 4, 'Extension Sifon Lavamanos', 'Extension Sifon Lavamanos', 5, '1430.00', '19.00', '2000.00', 'Extension Sifon Lavamanos', '2024-10-10 17:45:05', '2521', 1, 1),
(5072, 4, 'Extensión 2 Metros Titanuim', 'Extensión 2 Metros Titanuim', 2, '4214.00', '19.00', '6000.00', 'Extensión 2 Metros Titanuim', '2024-10-10 17:45:05', '7707692867135', 1, 1),
(5073, 4, 'Extensión 3 Metros Titanium', 'Extensión 3 Metros Titanium', 2, '4652.00', '19.00', '7000.00', 'Extensión 3 Metros Titanium', '2024-10-10 17:45:05', '7707692860082', 1, 1),
(5074, 4, 'Extensión 4Metros Titanium', 'Extensión 4Metros Titanium', 4, '5463.00', '19.00', '7800.00', 'Extensión 4Metros Titanium', '2024-10-10 17:45:05', '7707692862727', 1, 1),
(5075, 4, 'Extensión 5Metros Titanium', 'Extensión 5Metros Titanium', 3, '5992.00', '19.00', '7226.89', 'Extensión 5Metros Titanium', '2024-10-10 17:45:05', '7707692869702', 1, 1),
(5076, 4, 'Extensión 8 Metros Titanium', 'Extensión 8 Metros Titanium', 5, '8039.00', '19.00', '11500.00', 'Extensión 8 Metros Titanium', '2024-10-10 17:45:05', '7707692866169', 1, 1),
(5077, 4, 'Extesión 6 Mentros Titanium', 'Extesión 6 Mentros Titanium', 2, '7126.00', '19.00', '10500.00', 'Extesión 6 Mentros Titanium', '2024-10-10 17:45:05', '7707692860372', 1, 1),
(5078, 4, 'Extesión 9 Metros Titanium', 'Extesión 9 Metros Titanium', 1, '8769.00', '19.00', '13000.00', 'Extesión 9 Metros Titanium', '2024-10-10 17:45:05', '7707692868286', 1, 1),
(5079, 4, 'Extra Lavabilidad Colorvida Terracota Galon T1', 'Extra Lavabilidad Colorvida Terracota Galon T1', 2, '44490.00', '19.00', '55600.00', 'Extra Lavabilidad Colorvida Terracota Galon T1', '2024-10-10 17:45:05', '27', 1, 1),
(5080, 4, 'Extra-Lavabilidad Colorvida Blanco T1', 'Extra-Lavabilidad Colorvida Blanco T1', 5, '44490.00', '19.00', '55600.00', 'Extra-Lavabilidad Colorvida Blanco T1', '2024-10-10 17:45:05', 'BR1', 1, 1),
(5081, 4, 'Fija Color Beige Bolsa X 20kg', 'Fija Color Beige Bolsa X 20kg', 5, '10500.00', '19.00', '15000.00', 'Fija Color Beige Bolsa X 20kg', '2024-10-10 17:45:05', '325418', 1, 1),
(5082, 4, 'Filtro Hembra Boccherini', 'Filtro Hembra Boccherini', 5, '1900.00', '19.00', '2900.00', 'Filtro Hembra Boccherini', '2024-10-10 17:45:05', '7707180671299', 1, 1),
(5083, 4, 'Filtro Macho', 'Filtro Macho', 4, '1900.00', '19.00', '2900.00', 'Filtro Macho', '2024-10-10 17:45:05', '7707180671282', 1, 1),
(5084, 4, 'Flanche Tanque Alto Plas grifos 1', 'Flanche Tanque Alto Plas grifos 1', 8, '2650.00', '19.00', '4000.00', 'Flanche Tanque Alto Plas grifos 1', '2024-10-10 17:45:05', '7700031015129', 1, 1),
(5085, 4, 'Flanche Tanque Alto Plas grifos 1/2', 'Flanche Tanque Alto Plas grifos 1/2', 6, '2200.00', '19.00', '3200.00', 'Flanche Tanque Alto Plas grifos 1/2', '2024-10-10 17:45:05', '7700031015228', 1, 1),
(5086, 4, 'Flexometro 3 metros Boccherini', 'Flexometro 3 metros Boccherini', 7, '4500.00', '19.00', '6000.00', 'Flexometro 3 metros Boccherini', '2024-10-10 17:45:05', '7707180679240', 1, 1),
(5087, 4, 'Flexometro 3MTRS Truper', 'Flexometro 3MTRS Truper', 5, '8167.00', '19.00', '10200.00', 'Flexometro 3MTRS Truper', '2024-10-10 17:45:05', '7501206673317', 1, 1),
(5088, 4, 'Flexometro 5 Mts Truper Rsistente Impactos', 'Flexometro 5 Mts Truper Rsistente Impactos', 5, '13452.00', '19.00', '17000.00', 'Flexometro 5 Mts Truper Rsistente Impactos', '2024-10-10 17:45:05', '7501206673324', 1, 1),
(5089, 4, 'Flexometro 5MTS Truper', 'Flexometro 5MTS Truper', 3, '13452.00', '19.00', '17000.00', 'Flexometro 5MTS Truper', '2024-10-10 17:45:05', '7506240639534', 1, 1),
(5090, 4, 'Flexometro 7.5 Boccherini', 'Flexometro 7.5 Boccherini', 0, '9899.00', '19.00', '14500.00', 'Flexometro 7.5 Boccherini', '2024-10-10 17:45:05', '7707180679226', 1, 1),
(5091, 4, 'Flexometro 8MTS Truper', 'Flexometro 8MTS Truper', 3, '24023.00', '19.00', '29900.00', 'Flexometro 8MTS Truper', '2024-10-10 17:45:05', '7501206673331', 1, 1),
(5092, 4, 'Flexometro O Cinta Metrica Uduke 5m', 'Flexometro O Cinta Metrica Uduke 5m', 4, '7200.00', '19.00', '9000.00', 'Flexometro O Cinta Metrica Uduke 5m', '2024-10-10 17:45:05', '6973877760992', 1, 1),
(5093, 4, 'Flor 5 Puntas Grande', 'Flor 5 Puntas Grande', 100, '1200.00', '19.00', '1800.00', 'Flor 5 Puntas Grande', '2024-10-10 17:45:05', '50108', 1, 1),
(5094, 4, 'Flor 5 Puntas Mini', 'Flor 5 Puntas Mini', 100, '990.00', '19.00', '1500.00', 'Flor 5 Puntas Mini', '2024-10-10 17:45:05', '50126', 1, 1),
(5095, 4, 'Fluidmaster Valvula De Llenado', 'Fluidmaster Valvula De Llenado', 0, '29350.00', '19.00', '37000.00', 'Fluidmaster Valvula De Llenado', '2024-10-10 17:45:05', '39961344007', 1, 1),
(5096, 4, 'Foto celda Inadisa', 'Foto celda Inadisa', 2, '9000.00', '19.00', '12000.00', 'Foto celda Inadisa', '2024-10-10 17:45:05', '70', 1, 1),
(5097, 4, 'Freidora De Aire Digital Oster 4L', 'Freidora De Aire Digital Oster 4L', 4, '228970.00', '19.00', '255000.00', 'Freidora De Aire Digital Oster 4L', '2024-10-10 17:45:05', '53891168748', 1, 1),
(5098, 4, 'Fumigador De 2 Litros Truper', 'Fumigador De 2 Litros Truper', 1, '19218.00', '19.00', '25500.00', 'Fumigador De 2 Litros Truper', '2024-10-10 17:45:05', '7501206663165', 1, 1),
(5099, 4, 'GUANTE AMARILLO CORTO', 'GUANTE AMARILLO CORTO', 7, '5500.00', '19.00', '8000.00', 'GUANTE AMARILLO CORTO', '2024-10-10 17:45:05', '1532', 1, 1),
(5100, 4, 'GUANTE AMARRILLO LARGO', 'GUANTE AMARRILLO LARGO', 8, '7000.00', '19.00', '10000.00', 'GUANTE AMARRILLO LARGO', '2024-10-10 17:45:05', '1524', 1, 1),
(5101, 4, 'Galon Amarillo Oro T2 Corona', 'Galon Amarillo Oro T2 Corona', 0, '38239.00', '19.00', '57000.00', 'Galon Amarillo Oro T2 Corona', '2024-10-10 17:45:05', '7705389008892', 1, 1),
(5102, 4, 'Galon Anticorrosivo Ico Blanco', 'Galon Anticorrosivo Ico Blanco', 0, '39799.00', '19.00', '60000.00', 'Galon Anticorrosivo Ico Blanco', '2024-10-10 17:45:05', '7706360401596', 1, 1),
(5103, 4, 'Galon Anticorrosivo Negro Bler', 'Galon Anticorrosivo Negro Bler', 14, '43978.00', '19.00', '63000.00', 'Galon Anticorrosivo Negro Bler', '2024-10-10 17:45:05', '106', 1, 1),
(5104, 4, 'Galon Automotriz Negro Ppg +clzdr + Disolv', 'Galon Automotriz Negro Ppg +clzdr + Disolv', 2, '398735.00', '19.00', '499000.00', 'Galon Automotriz Negro Ppg +clzdr + Disolv', '2024-10-10 17:45:05', '9114', 1, 1),
(5105, 4, 'Galon Azul Mediterraneo T2 Corona', 'Galon Azul Mediterraneo T2 Corona', 5, '38239.00', '19.00', '57000.00', 'Galon Azul Mediterraneo T2 Corona', '2024-10-10 17:45:05', '7705389008830', 1, 1),
(5106, 4, 'Galon Blanco Almendra T2 Corona', 'Galon Blanco Almendra T2 Corona', 2, '38239.00', '19.00', '57000.00', 'Galon Blanco Almendra T2 Corona', '2024-10-10 17:45:05', '7705389006515', 1, 1),
(5107, 4, 'Galon Blanco Arena T2 Corona', 'Galon Blanco Arena T2 Corona', 5, '38239.00', '19.00', '57000.00', 'Galon Blanco Arena T2 Corona', '2024-10-10 17:45:05', '7705389008656', 1, 1),
(5108, 4, 'Galon Blanco Durazno T2 Corona', 'Galon Blanco Durazno T2 Corona', 1, '38239.00', '19.00', '57000.00', 'Galon Blanco Durazno T2 Corona', '2024-10-10 17:45:05', '7705389006539', 1, 1),
(5109, 4, 'Galon Blanco Hueso T2 Corona', 'Galon Blanco Hueso T2 Corona', 5, '38239.00', '19.00', '57000.00', 'Galon Blanco Hueso T2 Corona', '2024-10-10 17:45:05', '7705389006553', 1, 1),
(5110, 4, 'Galon Champaña T2 Corona', 'Galon Champaña T2 Corona', 4, '38239.00', '19.00', '57000.00', 'Galon Champaña T2 Corona', '2024-10-10 17:45:05', '7705389008793', 1, 1),
(5111, 4, 'Galon De Esmalte Alumino Every', 'Galon De Esmalte Alumino Every', 4, '51505.00', '19.00', '69000.00', 'Galon De Esmalte Alumino Every', '2024-10-10 17:45:05', '94', 1, 1),
(5112, 4, 'Galon De Esmalte Amarillo Every', 'Galon De Esmalte Amarillo Every', 0, '45640.00', '19.00', '67000.00', 'Galon De Esmalte Amarillo Every', '2024-10-10 17:45:05', '92', 1, 1),
(5113, 4, 'Galon De Esmalte Negro Every', 'Galon De Esmalte Negro Every', 1, '45640.00', '19.00', '67000.00', 'Galon De Esmalte Negro Every', '2024-10-10 17:45:05', '91', 1, 1),
(5114, 4, 'Galon De Laca B, Miel Every', 'Galon De Laca B, Miel Every', 0, '55074.00', '19.00', '79000.00', 'Galon De Laca B, Miel Every', '2024-10-10 17:45:05', '87', 1, 1),
(5115, 4, 'Galon Esmalte Amarillo Oxido', 'Galon Esmalte Amarillo Oxido', 3, '77800.00', '19.00', '83000.00', 'Galon Esmalte Amarillo Oxido', '2024-10-10 17:45:05', '326548', 1, 1),
(5116, 4, 'Galon Esmalte Amarillo Philaac', 'Galon Esmalte Amarillo Philaac', 1, '40500.00', '19.00', '60000.00', 'Galon Esmalte Amarillo Philaac', '2024-10-10 17:45:05', '7707001343633', 1, 1),
(5117, 4, 'Galon Esmalte Anoloc Champaña Bler', 'Galon Esmalte Anoloc Champaña Bler', 1, '57795.00', '19.00', '83000.00', 'Galon Esmalte Anoloc Champaña Bler', '2024-10-10 17:45:05', '1603010651256', 1, 1),
(5118, 4, 'Galon Esmalte Azul Oscuro Every', 'Galon Esmalte Azul Oscuro Every', 3, '45640.00', '19.00', '67000.00', 'Galon Esmalte Azul Oscuro Every', '2024-10-10 17:45:05', '95', 1, 1),
(5119, 4, 'Galon Esmalte Azul Verano Bler', 'Galon Esmalte Azul Verano Bler', 2, '57795.00', '19.00', '83000.00', 'Galon Esmalte Azul Verano Bler', '2024-10-10 17:45:05', '1605310651318', 1, 1),
(5120, 4, 'Galon Esmalte Blanco Every', 'Galon Esmalte Blanco Every', 0, '45640.00', '19.00', '67000.00', 'Galon Esmalte Blanco Every', '2024-10-10 17:45:05', '104', 1, 1),
(5121, 4, 'Galon Esmalte Blanco Mate Every', 'Galon Esmalte Blanco Mate Every', 3, '50995.00', '19.00', '75000.00', 'Galon Esmalte Blanco Mate Every', '2024-10-10 17:45:05', '103', 1, 1),
(5122, 4, 'Galon Esmalte Caoba Bler', 'Galon Esmalte Caoba Bler', 4, '57795.00', '19.00', '83000.00', 'Galon Esmalte Caoba Bler', '2024-10-10 17:45:05', '1603610651267', 1, 1),
(5123, 4, 'Galon Esmalte Caoba Philaac', 'Galon Esmalte Caoba Philaac', 6, '40500.00', '19.00', '60000.00', 'Galon Esmalte Caoba Philaac', '2024-10-10 17:45:05', '7707001314442', 1, 1),
(5124, 4, 'Galon Esmalte Dorado Bler', 'Galon Esmalte Dorado Bler', 6, '57795.00', '19.00', '83000.00', 'Galon Esmalte Dorado Bler', '2024-10-10 17:45:05', '1605910651343', 1, 1),
(5125, 4, 'Galon Esmalte Gris Claro Bler', 'Galon Esmalte Gris Claro Bler', 5, '57795.00', '19.00', '83000.00', 'Galon Esmalte Gris Claro Bler', '2024-10-10 17:45:05', '1606110646924', 1, 1),
(5126, 4, 'Galon Esmalte Gris Every', 'Galon Esmalte Gris Every', 4, '45640.00', '19.00', '67000.00', 'Galon Esmalte Gris Every', '2024-10-10 17:45:05', '96', 1, 1),
(5127, 4, 'Galon Esmalte Naranja Bler', 'Galon Esmalte Naranja Bler', 0, '55043.00', '19.00', '83000.00', 'Galon Esmalte Naranja Bler', '2024-10-10 17:45:05', '1601210651212', 1, 1),
(5128, 4, 'Galon Esmalte Negro Bler', 'Galon Esmalte Negro Bler', 3, '55043.00', '19.00', '79000.00', 'Galon Esmalte Negro Bler', '2024-10-10 17:45:05', '1609010651418', 1, 1),
(5129, 4, 'Galon Esmalte Negro Mate Every', 'Galon Esmalte Negro Mate Every', 3, '45894.00', '19.00', '70000.00', 'Galon Esmalte Negro Mate Every', '2024-10-10 17:45:05', '93', 1, 1),
(5130, 4, 'Galon Esmalte Negro Mate Pintuco', 'Galon Esmalte Negro Mate Pintuco', 2, '68000.00', '19.00', '85000.00', 'Galon Esmalte Negro Mate Pintuco', '2024-10-10 17:45:05', '7704488002886', 1, 1),
(5131, 4, 'Galon Esmalte Negro Philaac', 'Galon Esmalte Negro Philaac', 5, '40500.00', '19.00', '60000.00', 'Galon Esmalte Negro Philaac', '2024-10-10 17:45:05', '7707001323598', 1, 1),
(5132, 4, 'Galon Esmalte Rojo Bler', 'Galon Esmalte Rojo Bler', 2, '57795.00', '19.00', '83000.00', 'Galon Esmalte Rojo Bler', '2024-10-10 17:45:05', '7708648407603', 1, 1),
(5133, 4, 'Galon Esmalte Rojo Fiesta Lanco', 'Galon Esmalte Rojo Fiesta Lanco', 1, '69500.00', '19.00', '80000.00', 'Galon Esmalte Rojo Fiesta Lanco', '2024-10-10 17:45:05', '7705389007444', 1, 1),
(5134, 4, 'Galon Esmalte Rojo Fresa Pintuco', 'Galon Esmalte Rojo Fresa Pintuco', 9, '49800.00', '19.00', '73000.00', 'Galon Esmalte Rojo Fresa Pintuco', '2024-10-10 17:45:05', '7704488001957', 1, 1),
(5135, 4, 'Galon Esmalte Rojo Teja Bler', 'Galon Esmalte Rojo Teja Bler', 3, '57795.00', '19.00', '83000.00', 'Galon Esmalte Rojo Teja Bler', '2024-10-10 17:45:05', '1602110640450', 1, 1),
(5136, 4, 'Galon Esmalte Verde Chicle Bler', 'Galon Esmalte Verde Chicle Bler', 4, '57795.00', '19.00', '83000.00', 'Galon Esmalte Verde Chicle Bler', '2024-10-10 17:45:05', '1608910651382', 1, 1),
(5137, 4, 'Galon Esmalte Verde Esmeralda', 'Galon Esmalte Verde Esmeralda', 5, '57795.00', '19.00', '83000.00', 'Galon Esmalte Verde Esmeralda', '2024-10-10 17:45:05', '1604310654572', 1, 1),
(5138, 4, 'Galon Esmalte Verde Maquina Bler', 'Galon Esmalte Verde Maquina Bler', 4, '57795.00', '19.00', '83000.00', 'Galon Esmalte Verde Maquina Bler', '2024-10-10 17:45:05', '1604410648860', 1, 1),
(5139, 4, 'Galon Esmalte Verde Philaac', 'Galon Esmalte Verde Philaac', 13, '40500.00', '19.00', '60000.00', 'Galon Esmalte Verde Philaac', '2024-10-10 17:45:05', '7707001316491', 1, 1),
(5140, 4, 'Galon Gris Basalto T2 Corona', 'Galon Gris Basalto T2 Corona', 4, '38239.00', '19.00', '57000.00', 'Galon Gris Basalto T2 Corona', '2024-10-10 17:45:05', '7705389008731', 1, 1),
(5141, 4, 'Galon Laca B. Caramelo Every', 'Galon Laca B. Caramelo Every', 2, '55074.00', '19.00', '79000.00', 'Galon Laca B. Caramelo Every', '2024-10-10 17:45:05', '88', 1, 1),
(5142, 4, 'Galon Laca B. Nogal Every', 'Galon Laca B. Nogal Every', 4, '59154.00', '19.00', '85000.00', 'Galon Laca B. Nogal Every', '2024-10-10 17:45:05', '89', 1, 1),
(5143, 4, 'Galon Laca Maderpro Transparente Semimate', 'Galon Laca Maderpro Transparente Semimate', 3, '55584.00', '19.00', '80000.00', 'Galon Laca Maderpro Transparente Semimate', '2024-10-10 17:45:05', '680', 1, 1),
(5144, 4, 'Galon Magenta T2 Corona', 'Galon Magenta T2 Corona', 3, '38239.00', '19.00', '57000.00', 'Galon Magenta T2 Corona', '2024-10-10 17:45:05', '7705389008694', 1, 1),
(5145, 4, 'Galon Mandarina Trop T2 Corona', 'Galon Mandarina Trop T2 Corona', 0, '38239.00', '19.00', '57000.00', 'Galon Mandarina Trop T2 Corona', '2024-10-10 17:45:05', '7705389008755', 1, 1),
(5146, 4, 'Galon Naranja Tentacion T2 Corona', 'Galon Naranja Tentacion T2 Corona', 5, '38239.00', '19.00', '57000.00', 'Galon Naranja Tentacion T2 Corona', '2024-10-10 17:45:05', '7705389008939', 1, 1),
(5147, 4, 'Galon Negro T2 Corona', 'Galon Negro T2 Corona', 1, '38239.00', '19.00', '57000.00', 'Galon Negro T2 Corona', '2024-10-10 17:45:05', '7705389008816', 1, 1),
(5148, 4, 'Galon Ocre T2 Corona', 'Galon Ocre T2 Corona', 2, '38239.00', '19.00', '57000.00', 'Galon Ocre T2 Corona', '2024-10-10 17:45:05', '7705389008854', 1, 1),
(5149, 4, 'Galon Pintura Amarillo Pintulan', 'Galon Pintura Amarillo Pintulan', 0, '61000.00', '19.00', '69500.00', 'Galon Pintura Amarillo Pintulan', '2024-10-10 17:45:05', '7707031405080', 1, 1),
(5150, 4, 'Galon Primer Bler', 'Galon Primer Bler', 4, '94876.00', '19.00', '136000.00', 'Galon Primer Bler', '2024-10-10 17:45:05', '6400110655192', 1, 1),
(5151, 4, 'Galon Removedor Every', 'Galon Removedor Every', 4, '62724.00', '19.00', '85000.00', 'Galon Removedor Every', '2024-10-10 17:45:05', '102', 1, 1),
(5152, 4, 'Galon Rojo Colonial T2 Corona', 'Galon Rojo Colonial T2 Corona', 6, '38239.00', '19.00', '57000.00', 'Galon Rojo Colonial T2 Corona', '2024-10-10 17:45:05', '7705389008779', 1, 1),
(5153, 4, 'Galon Rojo Vivo T2 Corona', 'Galon Rojo Vivo T2 Corona', 0, '38239.00', '19.00', '57000.00', 'Galon Rojo Vivo T2 Corona', '2024-10-10 17:45:05', '7705389008915', 1, 1),
(5154, 4, 'Galon Sellador Tradicional Every', 'Galon Sellador Tradicional Every', 3, '54054.00', '19.00', '78000.00', 'Galon Sellador Tradicional Every', '2024-10-10 17:45:05', '90', 1, 1),
(5155, 4, 'Galon Verde Primaveral T2 Corona', 'Galon Verde Primaveral T2 Corona', 0, '38239.00', '19.00', '57000.00', 'Galon Verde Primaveral T2 Corona', '2024-10-10 17:45:05', '7705389008717', 1, 1),
(5156, 4, 'Galon verde Pino T2 Corona', 'Galon verde Pino T2 Corona', 2, '38239.00', '19.00', '57000.00', 'Galon verde Pino T2 Corona', '2024-10-10 17:45:05', '7705389008878', 1, 1),
(5157, 4, 'Gancho Adhesivo Extra Blanco 4055mm 826', 'Gancho Adhesivo Extra Blanco 4055mm 826', 24, '400.00', '19.00', '800.00', 'Gancho Adhesivo Extra Blanco 4055mm 826', '2024-10-10 17:45:05', 'GAN2', 1, 1),
(5158, 4, 'Gancho Adhesivo Extra-Jumbo Blanco 4653', 'Gancho Adhesivo Extra-Jumbo Blanco 4653', 24, '750.00', '19.00', '1200.00', 'Gancho Adhesivo Extra-Jumbo Blanco 4653', '2024-10-10 17:45:05', 'GAN1', 1, 1),
(5159, 4, 'Gancho Adhesivo Mediano Blanco', 'Gancho Adhesivo Mediano Blanco', 26, '242.00', '19.00', '500.00', 'Gancho Adhesivo Mediano Blanco', '2024-10-10 17:45:05', '827', 1, 1),
(5160, 4, 'Gancho Para Wpc', 'Gancho Para Wpc', 14, '700.00', '19.00', '1000.00', 'Gancho Para Wpc', '2024-10-10 17:45:05', '2315', 1, 1),
(5161, 4, 'Girasol 8 Puntas Grande', 'Girasol 8 Puntas Grande', 100, '1163.00', '19.00', '1700.00', 'Girasol 8 Puntas Grande', '2024-10-10 17:45:05', '50307', 1, 1),
(5162, 4, 'Girasol 8 Puntas Pequeño', 'Girasol 8 Puntas Pequeño', 100, '872.00', '19.00', '1300.00', 'Girasol 8 Puntas Pequeño', '2024-10-10 17:45:05', '200485', 1, 1),
(5163, 4, 'Grapa Conduit Galvanizada 1/2', 'Grapa Conduit Galvanizada 1/2', 0, '164.00', '19.00', '300.00', 'Grapa Conduit Galvanizada 1/2', '2024-10-10 17:45:05', '836', 1, 1),
(5164, 4, 'Grapa Para Cerca  1000 Gr Puma', 'Grapa Para Cerca  1000 Gr Puma', 25, '8040.00', '19.00', '9900.00', 'Grapa Para Cerca  1000 Gr Puma', '2024-10-10 17:45:05', '7705465008532', 1, 1),
(5165, 4, 'Grapa Plasatica Blanca Pequeña Sencilla', 'Grapa Plasatica Blanca Pequeña Sencilla', 1, '1020.00', '19.00', '1300.00', 'Grapa Plasatica Blanca Pequeña Sencilla', '2024-10-10 17:45:05', '1978', 1, 1),
(5166, 4, 'Grapa Plastica + Puntilla Negra', 'Grapa Plastica + Puntilla Negra', 0, '1083.00', '19.00', '2500.00', 'Grapa Plastica + Puntilla Negra', '2024-10-10 17:45:05', '5', 1, 1),
(5167, 4, 'Grapa Plastica Blanca', 'Grapa Plastica Blanca', 6, '164.00', '19.00', '300.00', 'Grapa Plastica Blanca', '2024-10-10 17:45:05', '837', 1, 1),
(5168, 4, 'Grapa Plastica Muro Plastica Coaxial C.A Mejia (Paquete x50 )', 'Grapa Plastica Muro Plastica Coaxial C.A Mejia (Paquete x50 )', 22, '1068.00', '19.00', '1800.00', 'Grapa Plastica Muro Plastica Coaxial C.A Mejia (Paquete x50 )', '2024-10-10 17:45:05', 'GRP1', 1, 1),
(5169, 4, 'Griferia Cocina Satinada Extraible 4489A', 'Griferia Cocina Satinada Extraible 4489A', 0, '120550.00', '19.00', '160000.00', 'Griferia Cocina Satinada Extraible 4489A', '2024-10-10 17:45:05', '8589', 1, 1),
(5170, 4, 'Griferia Completa Baño', 'Griferia Completa Baño', 3, '14000.00', '19.00', '19000.00', 'Griferia Completa Baño', '2024-10-10 17:45:05', '7700032104020', 1, 1),
(5171, 4, 'Griferia Completa Baño Arbol Delgada', 'Griferia Completa Baño Arbol Delgada', 4, '12499.00', '19.00', '17000.00', 'Griferia Completa Baño Arbol Delgada', '2024-10-10 17:45:05', '7700032104037', 1, 1),
(5172, 4, 'Griferia De Cocina Negra Cuello Flexible', 'Griferia De Cocina Negra Cuello Flexible', 2, '75000.00', '19.00', '105000.00', 'Griferia De Cocina Negra Cuello Flexible', '2024-10-10 17:45:05', '5076', 1, 1),
(5173, 4, 'Griferia Lavaplatos Aleta Nogal Grival', 'Griferia Lavaplatos Aleta Nogal Grival', 2, '51350.00', '19.00', '78500.00', 'Griferia Lavaplatos Aleta Nogal Grival', '2024-10-10 17:45:05', '455943331', 1, 1),
(5174, 4, 'Griferia Negra Cocina Extraible 8588', 'Griferia Negra Cocina Extraible 8588', 0, '80000.00', '19.00', '140000.00', 'Griferia Negra Cocina Extraible 8588', '2024-10-10 17:45:05', '8588', 1, 1),
(5175, 4, 'Griferia Pavco', 'Griferia Pavco', 1, '5700.00', '19.00', '10000.00', 'Griferia Pavco', '2024-10-10 17:45:05', '7707153749390', 1, 1),
(5176, 4, 'Griferia Sanitaria Osiris Manija Cromo', 'Griferia Sanitaria Osiris Manija Cromo', 1, '12300.00', '19.00', '18000.00', 'Griferia Sanitaria Osiris Manija Cromo', '2024-10-10 17:45:05', '2519', 1, 1),
(5177, 4, 'Grifo Cromado Uduke Jardin', 'Grifo Cromado Uduke Jardin', 0, '9299.00', '19.00', '13900.00', 'Grifo Cromado Uduke Jardin', '2024-10-10 17:45:05', '6973653176139', 1, 1),
(5178, 4, 'Grifo Dorado Uduke Mediano En Blister', 'Grifo Dorado Uduke Mediano En Blister', 0, '9999.00', '19.00', '13500.00', 'Grifo Dorado Uduke Mediano En Blister', '2024-10-10 17:45:05', '6973653176221', 1, 1),
(5179, 4, 'Grifo Racor Palanca 1/2 Uduke', 'Grifo Racor Palanca 1/2 Uduke', 5, '13700.00', '19.00', '19000.00', 'Grifo Racor Palanca 1/2 Uduke', '2024-10-10 17:45:05', 'LL1', 1, 1),
(5180, 4, 'Grifo Racor Palanca Cromado 1/2 Uduke', 'Grifo Racor Palanca Cromado 1/2 Uduke', 4, '5900.00', '19.00', '8500.00', 'Grifo Racor Palanca Cromado 1/2 Uduke', '2024-10-10 17:45:05', '6973877764143', 1, 1),
(5181, 4, 'Guante Anticorte Talla 10', 'Guante Anticorte Talla 10', 11, '12900.00', '19.00', '16000.00', 'Guante Anticorte Talla 10', '2024-10-10 17:45:05', '50678', 1, 1),
(5182, 4, 'Guante Domestico Amarillo Talla 7 1/2', 'Guante Domestico Amarillo Talla 7 1/2', 12, '3500.00', '19.00', '5000.00', 'Guante Domestico Amarillo Talla 7 1/2', '2024-10-10 17:45:05', '7709132893162', 1, 1),
(5183, 4, 'Guante Domestico Amarillo Talla 8', 'Guante Domestico Amarillo Talla 8', 9, '3500.00', '19.00', '5000.00', 'Guante Domestico Amarillo Talla 8', '2024-10-10 17:45:05', '7709132893100', 1, 1),
(5184, 4, 'Guante Domestico Amarillo Talla 9', 'Guante Domestico Amarillo Talla 9', 12, '3500.00', '19.00', '5000.00', 'Guante Domestico Amarillo Talla 9', '2024-10-10 17:45:05', '7709132893117', 1, 1),
(5185, 4, 'Guante Industrial Negro Uduke Calibre 25 Talla 10', 'Guante Industrial Negro Uduke Calibre 25 Talla 10', 6, '3650.00', '19.00', '5500.00', 'Guante Industrial Negro Uduke Calibre 25 Talla 10', '2024-10-10 17:45:05', '7707349920947', 1, 1),
(5186, 4, 'Guante Industrial Negro Uduke Calibre 25 Talla 9 1/2', 'Guante Industrial Negro Uduke Calibre 25 Talla 9 1/2', 6, '3650.00', '19.00', '5500.00', 'Guante Industrial Negro Uduke Calibre 25 Talla 9 1/2', '2024-10-10 17:45:05', '7707349920930', 1, 1),
(5187, 4, 'Guante Nitrilo Negro', 'Guante Nitrilo Negro', 9, '2600.00', '19.00', '3900.00', 'Guante Nitrilo Negro', '2024-10-10 17:45:05', '36273306', 1, 1),
(5188, 4, 'Guante Nitrilo Rojo', 'Guante Nitrilo Rojo', 11, '2600.00', '19.00', '3900.00', 'Guante Nitrilo Rojo', '2024-10-10 17:45:05', '36273305', 1, 1),
(5189, 4, 'Guante Sencillo  Negro Pepita', 'Guante Sencillo  Negro Pepita', 8, '1600.00', '19.00', '2500.00', 'Guante Sencillo  Negro Pepita', '2024-10-10 17:45:05', '200103', 1, 1),
(5190, 4, 'Herraje Asiento Genfor', 'Herraje Asiento Genfor', 0, '5165.00', '19.00', '10000.00', 'Herraje Asiento Genfor', '2024-10-10 17:45:05', '7707015376153', 1, 1),
(5191, 4, 'Herraje De Asiento Baño', 'Herraje De Asiento Baño', 7, '1291.00', '19.00', '3000.00', 'Herraje De Asiento Baño', '2024-10-10 17:45:05', '7', 1, 1),
(5192, 4, 'Hilo Para Albañil Rojo', 'Hilo Para Albañil Rojo', 6, '2629.00', '19.00', '4000.00', 'Hilo Para Albañil Rojo', '2024-10-10 17:45:05', '7506240651734', 1, 1),
(5193, 4, 'Hilo Para Guadaña X Metro', 'Hilo Para Guadaña X Metro', 112, '329.00', '19.00', '800.00', 'Hilo Para Guadaña X Metro', '2024-10-10 17:45:05', '7501206613597', 1, 1),
(5194, 4, 'Hilo Tesicol X 130mts Colores', 'Hilo Tesicol X 130mts Colores', 7, '2761.00', '19.00', '4500.00', 'Hilo Tesicol X 130mts Colores', '2024-10-10 17:45:05', '7707041401119', 1, 1),
(5195, 4, 'Hoja PVC Blanco Brillante', 'Hoja PVC Blanco Brillante', 77, '21500.00', '19.00', '26000.00', 'Hoja PVC Blanco Brillante', '2024-10-10 17:45:05', 'H1', 1, 1),
(5196, 4, 'Hoja PVC Blanco Madera', 'Hoja PVC Blanco Madera', 53, '21500.00', '19.00', '26000.00', 'Hoja PVC Blanco Madera', '2024-10-10 17:45:05', 'H2', 1, 1),
(5197, 4, 'Hoja PVC Ejecutivo 2', 'Hoja PVC Ejecutivo 2', 59, '21500.00', '19.00', '26000.00', 'Hoja PVC Ejecutivo 2', '2024-10-10 17:45:05', 'H4', 1, 1),
(5198, 4, 'Hoja PVC Marmol Humo', 'Hoja PVC Marmol Humo', 53, '21500.00', '19.00', '26000.00', 'Hoja PVC Marmol Humo', '2024-10-10 17:45:05', 'H3', 1, 1),
(5199, 4, 'Hoja PVC Marmolizado (1.22x2.44)', 'Hoja PVC Marmolizado (1.22x2.44)', 44, '95000.00', '19.00', '135000.00', 'Hoja PVC Marmolizado (1.22x2.44)', '2024-10-10 17:45:05', 'M1', 1, 1),
(5200, 4, 'Impermeabilizante 1/4 Corona', 'Impermeabilizante 1/4 Corona', 6, '26210.00', '19.00', '39500.00', 'Impermeabilizante 1/4 Corona', '2024-10-10 17:45:05', '7705389011007', 1, 1),
(5201, 4, 'Impermeabilizante Ed9 Texsa', 'Impermeabilizante Ed9 Texsa', 1, '10000.00', '19.00', '17000.00', 'Impermeabilizante Ed9 Texsa', '2024-10-10 17:45:05', '7707005000143', 1, 1),
(5202, 4, 'Impermuro Roca Color', 'Impermuro Roca Color', 30, '41999.00', '19.00', '53000.00', 'Impermuro Roca Color', '2024-10-10 17:45:05', '32', 1, 1),
(5203, 4, 'Interruptor  Doble Conmutable Veto', 'Interruptor  Doble Conmutable Veto', 15, '5193.00', '19.00', '6800.00', 'Interruptor  Doble Conmutable Veto', '2024-10-10 17:45:05', '7861145812049', 1, 1),
(5204, 4, 'Interruptor + Toma  New Light', 'Interruptor + Toma  New Light', 7, '3700.00', '19.00', '7500.00', 'Interruptor + Toma  New Light', '2024-10-10 17:45:05', '753153610281', 1, 1),
(5205, 4, 'Interruptor Doble New Light', 'Interruptor Doble New Light', 2, '3700.00', '19.00', '6500.00', 'Interruptor Doble New Light', '2024-10-10 17:45:05', '753153610304', 1, 1),
(5206, 4, 'Interruptor Sencillo New Light', 'Interruptor Sencillo New Light', 0, '3300.00', '19.00', '4000.00', 'Interruptor Sencillo New Light', '2024-10-10 17:45:05', '753153610359', 1, 1),
(5207, 4, 'Interruptor Sencillo Veto', 'Interruptor Sencillo Veto', 7, '3875.00', '19.00', '6000.00', 'Interruptor Sencillo Veto', '2024-10-10 17:45:05', '7861145812001', 1, 1),
(5208, 4, 'Jabonera Aluminio Boccherini Yw026', 'Jabonera Aluminio Boccherini Yw026', 2, '0.00', '19.00', '35000.00', 'Jabonera Aluminio Boccherini Yw026', '2024-10-10 17:45:05', '7707180675044', 1, 1),
(5209, 4, 'Jabonera Cristal Base Aluminio Boccherini Yw131', 'Jabonera Cristal Base Aluminio Boccherini Yw131', 3, '0.00', '19.00', '35000.00', 'Jabonera Cristal Base Aluminio Boccherini Yw131', '2024-10-10 17:45:05', '7707180678083', 1, 1),
(5210, 4, 'Jabonera Plast-Grifos', 'Jabonera Plast-Grifos', 3, '8498.00', '19.00', '11000.00', 'Jabonera Plast-Grifos', '2024-10-10 17:45:05', '7700031022929', 1, 1),
(5211, 4, 'Juego De 7 Copas Punta Torx 3/8 Truper', 'Juego De 7 Copas Punta Torx 3/8 Truper', 1, '39449.00', '19.00', '52000.00', 'Juego De 7 Copas Punta Torx 3/8 Truper', '2024-10-10 17:45:05', '7501206643389', 1, 1),
(5212, 4, 'Juego De 9 Copas 3/8 Metricas Petrul', 'Juego De 9 Copas 3/8 Metricas Petrul', 1, '23264.00', '19.00', '31000.00', 'Juego De 9 Copas 3/8 Metricas Petrul', '2024-10-10 17:45:05', '7501206644997', 1, 1),
(5213, 4, 'Juego De 9 Copas Manso 3/8 Pulgadas Petrul', 'Juego De 9 Copas Manso 3/8 Pulgadas Petrul', 1, '25287.00', '19.00', '33000.00', 'Juego De 9 Copas Manso 3/8 Pulgadas Petrul', '2024-10-10 17:45:05', '7501206644973', 1, 1),
(5214, 4, 'Juego De Baño En Cristal', 'Juego De Baño En Cristal', 0, '104999.00', '19.00', '130000.00', 'Juego De Baño En Cristal', '2024-10-10 17:45:05', '4', 1, 1),
(5215, 4, 'Juego De Copas 1/4 Y 3/8 39piezas', 'Juego De Copas 1/4 Y 3/8 39piezas', 0, '33199.00', '19.00', '48000.00', 'Juego De Copas 1/4 Y 3/8 39piezas', '2024-10-10 17:45:05', '7707180679325', 1, 1),
(5216, 4, 'Juego De Copas Sierra 5piezas', 'Juego De Copas Sierra 5piezas', 4, '11200.00', '19.00', '16000.00', 'Juego De Copas Sierra 5piezas', '2024-10-10 17:45:05', '7707766457934', 1, 1),
(5217, 4, 'Juego De Copas Torx Mando 3/8 Truper', 'Juego De Copas Torx Mando 3/8 Truper', 1, '59700.00', '19.00', '78000.00', 'Juego De Copas Torx Mando 3/8 Truper', '2024-10-10 17:45:05', '7506240600404', 1, 1),
(5218, 4, 'Juego De Gratas 3 Piezas Boccherini', 'Juego De Gratas 3 Piezas Boccherini', 3, '7800.00', '19.00', '12000.00', 'Juego De Gratas 3 Piezas Boccherini', '2024-10-10 17:45:05', '7707180679219', 1, 1),
(5219, 4, 'Juego De Herramientas Para Jardin Boccherini', 'Juego De Herramientas Para Jardin Boccherini', 2, '12099.00', '19.00', '17500.00', 'Juego De Herramientas Para Jardin Boccherini', '2024-10-10 17:45:05', '7707766454926', 1, 1),
(5220, 4, 'Juego De Llave Allen 8piezas Pulgadas', 'Juego De Llave Allen 8piezas Pulgadas', 2, '3399.00', '19.00', '5500.00', 'Juego De Llave Allen 8piezas Pulgadas', '2024-10-10 17:45:05', '7707180679141', 1, 1),
(5221, 4, 'Juego De Llaves Allen Tipo Llavero 8 Piezas Milimetricas', 'Juego De Llaves Allen Tipo Llavero 8 Piezas Milimetricas', 3, '3399.00', '19.00', '5500.00', 'Juego De Llaves Allen Tipo Llavero 8 Piezas Milimetricas', '2024-10-10 17:45:05', '7707180679158', 1, 1),
(5222, 4, 'Juego Llaves Allen Boccherini 8 Piezas', 'Juego Llaves Allen Boccherini 8 Piezas', 4, '6045.00', '19.00', '10500.00', 'Juego Llaves Allen Boccherini 8 Piezas', '2024-10-10 17:45:05', '7707180679134', 1, 1),
(5223, 4, 'Juego Para Baño 7p Negro Boccherini', 'Juego Para Baño 7p Negro Boccherini', 0, '52600.00', '19.00', '80000.00', 'Juego Para Baño 7p Negro Boccherini', '2024-10-10 17:45:05', '7707180673675', 1, 1),
(5224, 4, 'Juego de 6 Brocas Para Concreto Truper', 'Juego de 6 Brocas Para Concreto Truper', 3, '18680.00', '19.00', '25000.00', 'Juego de 6 Brocas Para Concreto Truper', '2024-10-10 17:45:05', '7501206668740', 1, 1),
(5225, 4, 'Junta Estrecha Multicolor', 'Junta Estrecha Multicolor', 8, '7900.00', '19.00', '12000.00', 'Junta Estrecha Multicolor', '2024-10-10 17:45:05', '110122', 1, 1),
(5226, 4, 'Kit De Seguridad X 3 Gafa -Tapaboca-Tapa Oido', 'Kit De Seguridad X 3 Gafa -Tapaboca-Tapa Oido', 2, '8350.00', '19.00', '12800.00', 'Kit De Seguridad X 3 Gafa -Tapaboca-Tapa Oido', '2024-10-10 17:45:05', '946', 1, 1),
(5227, 4, 'LAPIZ CARPINTERO BOCOLOR  TRUPER', 'LAPIZ CARPINTERO BOCOLOR  TRUPER', 5, '3700.00', '19.00', '5500.00', 'LAPIZ CARPINTERO BOCOLOR  TRUPER', '2024-10-10 17:45:05', '7506240691945', 1, 1),
(5228, 4, 'LAPIZ CARPINTERO TRUPER', 'LAPIZ CARPINTERO TRUPER', 3, '2500.00', '19.00', '3800.00', 'LAPIZ CARPINTERO TRUPER', '2024-10-10 17:45:05', '7506240691952', 1, 1),
(5229, 4, 'LAVAPLATO 750460220', 'LAVAPLATO 750460220', 1, '800000.00', '19.00', '1300000.00', 'LAVAPLATO 750460220', '2024-10-10 17:45:05', 'MS7546B', 1, 1),
(5230, 4, 'LAVAPLATOS DOBLE OSETA   INOXIDABLE', 'LAVAPLATOS DOBLE OSETA   INOXIDABLE', 2, '111700.00', '19.00', '165000.00', 'LAVAPLATOS DOBLE OSETA   INOXIDABLE', '2024-10-10 17:45:05', '100001000091', 1, 1),
(5231, 4, 'LIJA DE AGUA 150', 'LIJA DE AGUA 150', 9, '1300.00', '19.00', '1700.00', 'LIJA DE AGUA 150', '2024-10-10 17:45:05', '11621', 1, 1),
(5232, 4, 'LLAVE CUELLO DE GANSO ALETA MESA NEGRO MATE UDUKE', 'LLAVE CUELLO DE GANSO ALETA MESA NEGRO MATE UDUKE', 2, '16112.00', '19.00', '32500.00', 'LLAVE CUELLO DE GANSO ALETA MESA NEGRO MATE UDUKE', '2024-10-10 17:45:05', '6973877765683', 1, 1),
(5233, 4, 'LLAVE CUELLO DE GANSO FLEXIBLE MESA CRUCETA UDUKE', 'LLAVE CUELLO DE GANSO FLEXIBLE MESA CRUCETA UDUKE', 1, '14085.00', '19.00', '33500.00', 'LLAVE CUELLO DE GANSO FLEXIBLE MESA CRUCETA UDUKE', '2024-10-10 17:45:05', '6973653175071', 1, 1),
(5234, 4, 'LLAVE DE PUSH PARA ORINAL BOCCHERINI YW 044', 'LLAVE DE PUSH PARA ORINAL BOCCHERINI YW 044', 2, '29500.00', '19.00', '41500.00', 'LLAVE DE PUSH PARA ORINAL BOCCHERINI YW 044', '2024-10-10 17:45:05', '7707180676751', 1, 1),
(5235, 4, 'LLAVE INGLESA MULTIFUNCIONAL BOCCHERINI', 'LLAVE INGLESA MULTIFUNCIONAL BOCCHERINI', 3, '8500.00', '19.00', '12000.00', 'LLAVE INGLESA MULTIFUNCIONAL BOCCHERINI', '2024-10-10 17:45:05', '7707766455725', 1, 1),
(5236, 4, 'LLAVE LAVADORA ABS YW 550', 'LLAVE LAVADORA ABS YW 550', 2, '11500.00', '19.00', '16500.00', 'LLAVE LAVADORA ABS YW 550', '2024-10-10 17:45:05', '7707766458665', 1, 1),
(5237, 4, 'LLAVE MESA PALANCA UDUKE HT1185', 'LLAVE MESA PALANCA UDUKE HT1185', 0, '22962.00', '19.00', '33000.00', 'LLAVE MESA PALANCA UDUKE HT1185', '2024-10-10 17:45:05', '6973653175026', 1, 1),
(5238, 4, 'Laca Brillante Miel Every 1/4', 'Laca Brillante Miel Every 1/4', 0, '17691.00', '19.00', '27000.00', 'Laca Brillante Miel Every 1/4', '2024-10-10 17:45:05', '77', 1, 1),
(5239, 4, 'Lacamanos Dorado Canoa Baru', 'Lacamanos Dorado Canoa Baru', 1, '325000.00', '19.00', '390000.00', 'Lacamanos Dorado Canoa Baru', '2024-10-10 17:45:05', '92389', 1, 1),
(5240, 4, 'Lamina De Segueta Nicholson', 'Lamina De Segueta Nicholson', 50, '2988.00', '19.00', '4500.00', 'Lamina De Segueta Nicholson', '2024-10-10 17:45:05', '7891645161903', 1, 1),
(5241, 4, 'Lamina WPC', 'Lamina WPC', 25, '20000.00', '19.00', '29000.00', 'Lamina WPC', '2024-10-10 17:45:05', '963258', 1, 1),
(5242, 4, 'Lampara Aro Cristal  Colgante  93946', 'Lampara Aro Cristal  Colgante  93946', 1, '580000.00', '19.00', '750000.00', 'Lampara Aro Cristal  Colgante  93946', '2024-10-10 17:45:05', 'A1', 1, 1),
(5243, 4, 'Lampara Eglo x3 Cabezal Negro', 'Lampara Eglo x3 Cabezal Negro', 1, '180000.00', '19.00', '260000.00', 'Lampara Eglo x3 Cabezal Negro', '2024-10-10 17:45:05', 'L1', 1, 1),
(5244, 4, 'Lampara Estilo Plafon Blanca', 'Lampara Estilo Plafon Blanca', 1, '33000.00', '19.00', '65000.00', 'Lampara Estilo Plafon Blanca', '2024-10-10 17:45:05', 'P2', 1, 1),
(5245, 4, 'Lampara Led Panel Incrustar 18w Karluz Lighting', 'Lampara Led Panel Incrustar 18w Karluz Lighting', 19, '7500.00', '19.00', '23000.00', 'Lampara Led Panel Incrustar 18w Karluz Lighting', '2024-10-10 17:45:05', 'KA1', 1, 1),
(5246, 4, 'Lampara Led Sobreponer Decotativa Osaky 24w', 'Lampara Led Sobreponer Decotativa Osaky 24w', 8, '55000.00', '19.00', '100000.00', 'Lampara Led Sobreponer Decotativa Osaky 24w', '2024-10-10 17:45:05', '6923834525232', 1, 1),
(5247, 4, 'Lava Platos Socoda 1x52', 'Lava Platos Socoda 1x52', 1, '98740.00', '19.00', '145000.00', 'Lava Platos Socoda 1x52', '2024-10-10 17:45:05', '9.1101087499211E+18', 1, 1),
(5248, 4, 'Lavable Azul Mediterraneo Galon Corona', 'Lavable Azul Mediterraneo Galon Corona', 1, '62865.00', '19.00', '77000.00', 'Lavable Azul Mediterraneo Galon Corona', '2024-10-10 17:45:05', '7705389009141', 1, 1),
(5249, 4, 'Lavable Blanco Almendra Corona', 'Lavable Blanco Almendra Corona', 1, '62865.00', '19.00', '77000.00', 'Lavable Blanco Almendra Corona', '2024-10-10 17:45:05', '7705389003200', 1, 1);
INSERT INTO `inventario` (`id`, `user_id`, `nombre`, `descripcion`, `stock`, `precio_costo`, `impuesto`, `precio_venta`, `otro_dato`, `fecha_ingreso`, `codigo_barras`, `departamento_id`, `categoria_id`) VALUES
(5250, 4, 'Lavable Blanco Arena Corona', 'Lavable Blanco Arena Corona', 1, '62865.00', '19.00', '77000.00', 'Lavable Blanco Arena Corona', '2024-10-10 17:45:05', '7705389008984', 1, 1),
(5251, 4, 'Lavable Blanco Durazno Galon Corona', 'Lavable Blanco Durazno Galon Corona', 2, '62865.00', '19.00', '77000.00', 'Lavable Blanco Durazno Galon Corona', '2024-10-10 17:45:05', '7705389003286', 1, 1),
(5252, 4, 'Lavable Melon Corona', 'Lavable Melon Corona', 2, '62865.00', '19.00', '77000.00', 'Lavable Melon Corona', '2024-10-10 17:45:05', '7705389003248', 1, 1),
(5253, 4, 'Lavable Naranja Tentacion Corona', 'Lavable Naranja Tentacion Corona', 2, '62865.00', '19.00', '77000.00', 'Lavable Naranja Tentacion Corona', '2024-10-10 17:45:05', '7705389009240', 1, 1),
(5254, 4, 'Lavable Negro Corona', 'Lavable Negro Corona', 2, '62865.00', '19.00', '77000.00', 'Lavable Negro Corona', '2024-10-10 17:45:05', '7705389009127', 1, 1),
(5255, 4, 'Lavable Verde Primavera Corona', 'Lavable Verde Primavera Corona', 1, '62865.00', '19.00', '77000.00', 'Lavable Verde Primavera Corona', '2024-10-10 17:45:05', '7705389009028', 1, 1),
(5256, 4, 'Lavable rojo vivo corona', 'Lavable rojo vivo corona', 3, '61519.00', '19.00', '77000.00', 'Lavable rojo vivo corona', '2024-10-10 17:45:05', '7705389009233', 1, 1),
(5257, 4, 'Lavamanos  Porcelana 3939 Sobre Poner', 'Lavamanos  Porcelana 3939 Sobre Poner', 1, '164999.00', '19.00', '198000.00', 'Lavamanos  Porcelana 3939 Sobre Poner', '2024-10-10 17:45:05', '900022', 1, 1),
(5258, 4, 'Lavamanos 3636 Sobreponer', 'Lavamanos 3636 Sobreponer', 1, '160000.00', '19.00', '192000.00', 'Lavamanos 3636 Sobreponer', '2024-10-10 17:45:05', '9000200', 1, 1),
(5259, 4, 'Lavamanos 363612 Sobreponer Vessel Borde Negro', 'Lavamanos 363612 Sobreponer Vessel Borde Negro', 1, '134453.00', '19.00', '210000.00', 'Lavamanos 363612 Sobreponer Vessel Borde Negro', '2024-10-10 17:45:05', '56891', 1, 1),
(5260, 4, 'Lavamanos 4040 Sobreponer Blanco', 'Lavamanos 4040 Sobreponer Blanco', 1, '164999.00', '19.00', '198000.00', 'Lavamanos 4040 Sobreponer Blanco', '2024-10-10 17:45:05', '90002000', 1, 1),
(5261, 4, 'Lavamanos Donna', 'Lavamanos Donna', 1, '105262.00', '19.00', '132000.00', 'Lavamanos Donna', '2024-10-10 17:45:05', '22548', 1, 1),
(5262, 4, 'Lavamanos Dorado', 'Lavamanos Dorado', 1, '325000.00', '19.00', '390000.00', 'Lavamanos Dorado', '2024-10-10 17:45:05', '92394', 1, 1),
(5263, 4, 'Lavamanos Negro Brillante', 'Lavamanos Negro Brillante', 1, '119999.00', '19.00', '150000.00', 'Lavamanos Negro Brillante', '2024-10-10 17:45:05', '9000372', 1, 1),
(5264, 4, 'Lavamanos Placa 6047 Marmol Nero', 'Lavamanos Placa 6047 Marmol Nero', 1, '341998.00', '19.00', '411000.00', 'Lavamanos Placa 6047 Marmol Nero', '2024-10-10 17:45:05', '9000', 1, 1),
(5265, 4, 'Lavamanos Porcelana 36x36 Borde Oro', 'Lavamanos Porcelana 36x36 Borde Oro', 1, '317599.00', '19.00', '381120.00', 'Lavamanos Porcelana 36x36 Borde Oro', '2024-10-10 17:45:05', '900121', 1, 1),
(5266, 4, 'Lavamonos Ovalado', 'Lavamonos Ovalado', 1, '94737.00', '19.00', '120000.00', 'Lavamonos Ovalado', '2024-10-10 17:45:05', '35482', 1, 1),
(5267, 4, 'Lavaplatos 5343 + Accesorios Socoda', 'Lavaplatos 5343 + Accesorios Socoda', 1, '126400.00', '19.00', '158000.00', 'Lavaplatos 5343 + Accesorios Socoda', '2024-10-10 17:45:05', '7707180675129', 1, 1),
(5268, 4, 'Lavaplatos Acero Inoxidable Doble', 'Lavaplatos Acero Inoxidable Doble', 2, '164000.00', '19.00', '205000.00', 'Lavaplatos Acero Inoxidable Doble', '2024-10-10 17:45:05', '10189', 1, 1),
(5269, 4, 'Lavaplatos Inteligente Negro MS7546B 750460220', 'Lavaplatos Inteligente Negro MS7546B 750460220', 1, '800000.00', '19.00', '1200000.00', 'Lavaplatos Inteligente Negro MS7546B 750460220', '2024-10-10 17:45:05', 'LA1', 1, 1),
(5270, 4, 'Led Driver Repuesto 6w', 'Led Driver Repuesto 6w', 6, '3350.00', '19.00', '4500.00', 'Led Driver Repuesto 6w', '2024-10-10 17:45:05', '13096', 1, 1),
(5271, 4, 'Led Driver Repuesto 9w Panel Led', 'Led Driver Repuesto 9w Panel Led', 2, '4690.00', '19.00', '6000.00', 'Led Driver Repuesto 9w Panel Led', '2024-10-10 17:45:05', '13098', 1, 1),
(5272, 4, 'Led Frameless 18w', 'Led Frameless 18w', 6, '6985.00', '19.00', '10000.00', 'Led Frameless 18w', '2024-10-10 17:45:05', '7707692864806', 1, 1),
(5273, 4, 'Led Frameless 24w', 'Led Frameless 24w', 4, '9897.00', '19.00', '13000.00', 'Led Frameless 24w', '2024-10-10 17:45:05', '7707692868033', 1, 1),
(5274, 4, 'Led S/Poner 12w Cuadrado', 'Led S/Poner 12w Cuadrado', 3, '8600.00', '19.00', '12000.00', 'Led S/Poner 12w Cuadrado', '2024-10-10 17:45:05', '7707692869993', 1, 1),
(5275, 4, 'Led S/Poner 18w Cuadrado', 'Led S/Poner 18w Cuadrado', 6, '10505.00', '19.00', '14000.00', 'Led S/Poner 18w Cuadrado', '2024-10-10 17:45:05', '7707692864875', 1, 1),
(5276, 4, 'Lentes De Seguridad Ajustable Boccherini', 'Lentes De Seguridad Ajustable Boccherini', 0, '2856.00', '19.00', '5000.00', 'Lentes De Seguridad Ajustable Boccherini', '2024-10-10 17:45:05', '7707180679202', 1, 1),
(5277, 4, 'Lentes De Seguridad Transparente Boccherini', 'Lentes De Seguridad Transparente Boccherini', 2, '3199.00', '19.00', '5000.00', 'Lentes De Seguridad Transparente Boccherini', '2024-10-10 17:45:05', '7707180679189', 1, 1),
(5278, 4, 'Lija 220 Omega', 'Lija 220 Omega', 53, '733.00', '19.00', '1500.00', 'Lija 220 Omega', '2024-10-10 17:45:05', '17705509000697', 1, 1),
(5279, 4, 'Lija Abracol #100', 'Lija Abracol #100', 0, '1241.00', '19.00', '1600.00', 'Lija Abracol #100', '2024-10-10 17:45:05', '17705509000031', 1, 1),
(5280, 4, 'Lija Abracol #120', 'Lija Abracol #120', 10, '1153.00', '19.00', '1500.00', 'Lija Abracol #120', '2024-10-10 17:45:05', '17705509000048', 1, 1),
(5281, 4, 'Lija Abracol #150', 'Lija Abracol #150', 5, '1153.00', '19.00', '1500.00', 'Lija Abracol #150', '2024-10-10 17:45:05', '17705509000055', 1, 1),
(5282, 4, 'Lija Abracol #180', 'Lija Abracol #180', 5, '1153.00', '19.00', '1500.00', 'Lija Abracol #180', '2024-10-10 17:45:05', '17705509000062', 1, 1),
(5283, 4, 'Lija Abracol #220', 'Lija Abracol #220', 9, '1153.00', '19.00', '1500.00', 'Lija Abracol #220', '2024-10-10 17:45:05', '17705509000079', 1, 1),
(5284, 4, 'Lija Abracol #80', 'Lija Abracol #80', 0, '1241.00', '19.00', '1600.00', 'Lija Abracol #80', '2024-10-10 17:45:05', '17705509000024', 1, 1),
(5285, 4, 'Lija De Agua #150 Truper Cod 165131', 'Lija De Agua #150 Truper Cod 165131', 10, '1300.00', '19.00', '1700.00', 'Lija De Agua #150 Truper Cod 165131', '2024-10-10 17:45:05', '7501206685396', 1, 1),
(5286, 4, 'Lija Premier #240 Carborundum', 'Lija Premier #240 Carborundum', 33, '689.00', '19.00', '1500.00', 'Lija Premier #240 Carborundum', '2024-10-10 17:45:05', '7702301111234', 1, 1),
(5287, 4, 'Lija Premier #320 Carborundum', 'Lija Premier #320 Carborundum', 48, '689.00', '19.00', '1500.00', 'Lija Premier #320 Carborundum', '2024-10-10 17:45:05', '7702301111210', 1, 1),
(5288, 4, 'Lima 6 Truper', 'Lima 6 Truper', 1, '3540.00', '19.00', '4700.00', 'Lima 6 Truper', '2024-10-10 17:45:05', '7501206624210', 1, 1),
(5289, 4, 'Lima 7 Truper', 'Lima 7 Truper', 2, '5967.00', '19.00', '7800.00', 'Lima 7 Truper', '2024-10-10 17:45:05', '7501206624227', 1, 1),
(5290, 4, 'Lima 8 Truper', 'Lima 8 Truper', 0, '7282.00', '19.00', '9500.00', 'Lima 8 Truper', '2024-10-10 17:45:05', '7501206624234', 1, 1),
(5291, 4, 'Lima Colima', 'Lima Colima', 0, '3000.00', '19.00', '4800.00', 'Lima Colima', '2024-10-10 17:45:05', '7706912910309', 1, 1),
(5292, 4, 'Lima Motocierra 3/16 Redonda', 'Lima Motocierra 3/16 Redonda', 5, '2700.00', '19.00', '3600.00', 'Lima Motocierra 3/16 Redonda', '2024-10-10 17:45:05', '8237562002469', 1, 1),
(5293, 4, 'Lima Motosierra 7/32', 'Lima Motosierra 7/32', 6, '5500.00', '19.00', '7200.00', 'Lima Motosierra 7/32', '2024-10-10 17:45:05', '15168', 1, 1),
(5294, 4, 'Lima Redonda Larga Boccherini 8', 'Lima Redonda Larga Boccherini 8', 8, '5800.00', '19.00', '8100.00', 'Lima Redonda Larga Boccherini 8', '2024-10-10 17:45:05', '7707180679073', 1, 1),
(5295, 4, 'Lima Serrucho 4.5 Uduke', 'Lima Serrucho 4.5 Uduke', 6, '2200.00', '19.00', '3600.00', 'Lima Serrucho 4.5 Uduke', '2024-10-10 17:45:05', '8237562002292', 1, 1),
(5296, 4, 'Lima Triangular 6 Uduke', 'Lima Triangular 6 Uduke', 10, '3549.00', '19.00', '5000.00', 'Lima Triangular 6 Uduke', '2024-10-10 17:45:05', '8237562004234', 1, 1),
(5297, 4, 'Lima Triangular Pesado 7 Truper', 'Lima Triangular Pesado 7 Truper', 6, '5900.00', '19.00', '7700.00', 'Lima Triangular Pesado 7 Truper', '2024-10-10 17:45:05', '57501206624222', 1, 1),
(5298, 4, 'Lima Triangulo Corta Boccherini', 'Lima Triangulo Corta Boccherini', 0, '5000.00', '19.00', '6000.00', 'Lima Triangulo Corta Boccherini', '2024-10-10 17:45:05', '7707180679080', 1, 1),
(5299, 4, 'Lima Triangulo Larga Boccherini', 'Lima Triangulo Larga Boccherini', 0, '4165.00', '19.00', '6000.00', 'Lima Triangulo Larga Boccherini', '2024-10-10 17:45:05', '7707180679097', 1, 1),
(5300, 4, 'Limpiador De Juantas', 'Limpiador De Juantas', 1, '11500.00', '19.00', '15000.00', 'Limpiador De Juantas', '2024-10-10 17:45:05', '32547785', 1, 1),
(5301, 4, 'Limpiador De Pvc 1/128 Genfor', 'Limpiador De Pvc 1/128 Genfor', 1, '2056.00', '19.00', '3000.00', 'Limpiador De Pvc 1/128 Genfor', '2024-10-10 17:45:05', '7707015322129', 1, 1),
(5302, 4, 'Limpíador De Pvc 1/32 PEGACOL', 'Limpíador De Pvc 1/32 PEGACOL', 5, '2200.00', '19.00', '3100.00', 'Limpíador De Pvc 1/32 PEGACOL', '2024-10-10 17:45:05', 'PEGA1', 1, 1),
(5303, 4, 'Linterna Pretul 80m', 'Linterna Pretul 80m', 2, '20230.00', '19.00', '25500.00', 'Linterna Pretul 80m', '2024-10-10 17:45:05', '7506240691426', 1, 1),
(5304, 4, 'Linterna Recargable 1W', 'Linterna Recargable 1W', 1, '5350.00', '19.00', '8000.00', 'Linterna Recargable 1W', '2024-10-10 17:45:05', '7450077019093', 1, 1),
(5305, 4, 'Litro Tinte Para Madera Every Caramelo', 'Litro Tinte Para Madera Every Caramelo', 6, '19119.00', '19.00', '28000.00', 'Litro Tinte Para Madera Every Caramelo', '2024-10-10 17:45:05', '7703201005807', 1, 1),
(5306, 4, 'Litro Tinte Para Madera Every Nogal', 'Litro Tinte Para Madera Every Nogal', 6, '19119.00', '19.00', '28000.00', 'Litro Tinte Para Madera Every Nogal', '2024-10-10 17:45:05', '80', 1, 1),
(5307, 4, 'Litro Tinte Para Madera Every Wengue', 'Litro Tinte Para Madera Every Wengue', 6, '19119.00', '19.00', '28000.00', 'Litro Tinte Para Madera Every Wengue', '2024-10-10 17:45:05', '79', 1, 1),
(5308, 4, 'Litro Tinte Para Madera Miel Every', 'Litro Tinte Para Madera Miel Every', 4, '19119.00', '19.00', '28000.00', 'Litro Tinte Para Madera Miel Every', '2024-10-10 17:45:05', '78', 1, 1),
(5309, 4, 'Llana De Espuma 2 1/2 X 4', 'Llana De Espuma 2 1/2 X 4', 2, '11126.00', '19.00', '14500.00', 'Llana De Espuma 2 1/2 X 4', '2024-10-10 17:45:05', '7506240609971', 1, 1),
(5310, 4, 'Llana Lisa Con Mango Plastico Uduke', 'Llana Lisa Con Mango Plastico Uduke', 0, '6090.00', '19.00', '9000.00', 'Llana Lisa Con Mango Plastico Uduke', '2024-10-10 17:45:05', '6973877761531', 1, 1),
(5311, 4, 'Llana Lisa De 11 Mango De Plasticvo', 'Llana Lisa De 11 Mango De Plasticvo', 4, '12138.00', '19.00', '16000.00', 'Llana Lisa De 11 Mango De Plasticvo', '2024-10-10 17:45:05', '7501206635186', 1, 1),
(5312, 4, 'Llana Metalica Dentada Boccherini', 'Llana Metalica Dentada Boccherini', 5, '9199.00', '19.00', '14000.00', 'Llana Metalica Dentada Boccherini', '2024-10-10 17:45:05', '7707180679417', 1, 1),
(5313, 4, 'Llana Plana Para Graniplast Caribe', 'Llana Plana Para Graniplast Caribe', 6, '11448.00', '19.00', '16000.00', 'Llana Plana Para Graniplast Caribe', '2024-10-10 17:45:05', '60', 1, 1),
(5314, 4, 'Llanta Para Carretilla Sin Neumático 14x3 N-P 25008', 'Llanta Para Carretilla Sin Neumático 14x3 N-P 25008', 2, '15000.00', '19.00', '22000.00', 'Llanta Para Carretilla Sin Neumático 14x3 N-P 25008', '2024-10-10 17:45:05', '1017253420010', 1, 1),
(5315, 4, 'Llanta Para Carretilla Sin Neumático 16x4 N-P16 20584', 'Llanta Para Carretilla Sin Neumático 16x4 N-P16 20584', 3, '24276.00', '19.00', '32000.00', 'Llanta Para Carretilla Sin Neumático 16x4 N-P16 20584', '2024-10-10 17:45:05', '1017137410014', 1, 1),
(5316, 4, 'Llave Acerada SUS304', 'Llave Acerada SUS304', 1, '100000.00', '19.00', '160000.00', 'Llave Acerada SUS304', '2024-10-10 17:45:05', 'SUS304', 1, 1),
(5317, 4, 'Llave Agua Fria  Negra FT6185B', 'Llave Agua Fria  Negra FT6185B', 1, '84211.00', '19.00', '106000.00', 'Llave Agua Fria  Negra FT6185B', '2024-10-10 17:45:05', '64852', 1, 1),
(5318, 4, 'Llave Agua Fria Oro FT6185G', 'Llave Agua Fria Oro FT6185G', 1, '105262.00', '19.00', '132000.00', 'Llave Agua Fria Oro FT6185G', '2024-10-10 17:45:05', '34582', 1, 1),
(5319, 4, 'Llave Agua Fria Oro Rosa', 'Llave Agua Fria Oro Rosa', 2, '79900.00', '19.00', '105000.00', 'Llave Agua Fria Oro Rosa', '2024-10-10 17:45:05', '120001000', 1, 1),
(5320, 4, 'Llave Ajustable 6 Boccherini', 'Llave Ajustable 6 Boccherini', 3, '6299.00', '19.00', '10000.00', 'Llave Ajustable 6 Boccherini', '2024-10-10 17:45:05', '7707180678700', 1, 1),
(5321, 4, 'Llave Ajustable 8 Boccherini', 'Llave Ajustable 8 Boccherini', 3, '7500.00', '19.00', '12000.00', 'Llave Ajustable 8 Boccherini', '2024-10-10 17:45:05', '7707180678717', 1, 1),
(5322, 4, 'Llave Baja Dorada  R/5060', 'Llave Baja Dorada  R/5060', 1, '77999.00', '19.00', '97500.00', 'Llave Baja Dorada  R/5060', '2024-10-10 17:45:05', '12000100', 1, 1),
(5323, 4, 'Llave Bola 1/2 Cromada Uduke Italy', 'Llave Bola 1/2 Cromada Uduke Italy', 4, '8350.00', '19.00', '11800.00', 'Llave Bola 1/2 Cromada Uduke Italy', '2024-10-10 17:45:05', '6973877764259', 1, 1),
(5324, 4, 'Llave Bola 1/2 Grival', 'Llave Bola 1/2 Grival', 1, '19300.00', '19.00', '27000.00', 'Llave Bola 1/2 Grival', '2024-10-10 17:45:05', '7706157627390', 1, 1),
(5325, 4, 'Llave Bola Bronce 1/2 Uduke', 'Llave Bola Bronce 1/2 Uduke', 4, '8750.00', '19.00', '12300.00', 'Llave Bola Bronce 1/2 Uduke', '2024-10-10 17:45:05', '6973877764280', 1, 1),
(5326, 4, 'Llave Bola Pvc Lisa 1/2 Grival', 'Llave Bola Pvc Lisa 1/2 Grival', 4, '4800.00', '19.00', '6400.00', 'Llave Bola Pvc Lisa 1/2 Grival', '2024-10-10 17:45:05', '7706157699847', 1, 1),
(5327, 4, 'Llave Chorro Con Racor De 1/2 BCH YW039', 'Llave Chorro Con Racor De 1/2 BCH YW039', 1, '10300.00', '19.00', '16500.00', 'Llave Chorro Con Racor De 1/2 BCH YW039', '2024-10-10 17:45:05', '7707180676706', 1, 1),
(5328, 4, 'Llave Combinada Milimetrica 10mm', 'Llave Combinada Milimetrica 10mm', 1, '2999.00', '19.00', '4500.00', 'Llave Combinada Milimetrica 10mm', '2024-10-10 17:45:05', '7707180678731', 1, 1),
(5329, 4, 'Llave Combinada Milimetrica 11mm', 'Llave Combinada Milimetrica 11mm', 0, '3499.00', '19.00', '5000.00', 'Llave Combinada Milimetrica 11mm', '2024-10-10 17:45:05', '7707180678748', 1, 1),
(5330, 4, 'Llave Combinada Milimetrica 13mm', 'Llave Combinada Milimetrica 13mm', 1, '3699.00', '19.00', '5500.00', 'Llave Combinada Milimetrica 13mm', '2024-10-10 17:45:05', '7707180678755', 1, 1),
(5331, 4, 'Llave Combinada Milimetrica 15mm', 'Llave Combinada Milimetrica 15mm', 1, '4900.00', '19.00', '7000.00', 'Llave Combinada Milimetrica 15mm', '2024-10-10 17:45:05', '7707180678762', 1, 1),
(5332, 4, 'Llave Combinada Milimetrica 16mm', 'Llave Combinada Milimetrica 16mm', 2, '3332.00', '19.00', '4900.00', 'Llave Combinada Milimetrica 16mm', '2024-10-10 17:45:05', '7707180678779', 1, 1),
(5333, 4, 'Llave Combinada Milimetrica 17mm', 'Llave Combinada Milimetrica 17mm', 2, '3808.00', '19.00', '5800.00', 'Llave Combinada Milimetrica 17mm', '2024-10-10 17:45:05', '7707180678786', 1, 1),
(5334, 4, 'Llave Combinada Milimetrica 18mm', 'Llave Combinada Milimetrica 18mm', 2, '3927.00', '19.00', '6000.00', 'Llave Combinada Milimetrica 18mm', '2024-10-10 17:45:05', '7707180678793', 1, 1),
(5335, 4, 'Llave Combinada Milimetrica 19mm', 'Llave Combinada Milimetrica 19mm', 2, '4165.00', '19.00', '6500.00', 'Llave Combinada Milimetrica 19mm', '2024-10-10 17:45:05', '7707180678809', 1, 1),
(5336, 4, 'Llave Combinada Milimetrica 20mm', 'Llave Combinada Milimetrica 20mm', 2, '4641.00', '19.00', '6900.00', 'Llave Combinada Milimetrica 20mm', '2024-10-10 17:45:05', '7707180678816', 1, 1),
(5337, 4, 'Llave Combinada Milimetrica 22mm', 'Llave Combinada Milimetrica 22mm', 2, '5593.00', '19.00', '8500.00', 'Llave Combinada Milimetrica 22mm', '2024-10-10 17:45:05', '7707180678823', 1, 1),
(5338, 4, 'Llave Combinada Standard 1/2', 'Llave Combinada Standard 1/2', 0, '4900.00', '19.00', '7000.00', 'Llave Combinada Standard 1/2', '2024-10-10 17:45:05', '7707180678830', 1, 1),
(5339, 4, 'Llave Control Mas Acople Sanitario', 'Llave Control Mas Acople Sanitario', 5, '3300.00', '19.00', '4800.00', 'Llave Control Mas Acople Sanitario', '2024-10-10 17:45:05', '2523', 1, 1),
(5340, 4, 'Llave Cruceta Negra Mate Uduke HT1196', 'Llave Cruceta Negra Mate Uduke HT1196', 1, '16112.00', '19.00', '32500.00', 'Llave Cruceta Negra Mate Uduke HT1196', '2024-10-10 17:45:05', '6973877765638', 1, 1),
(5341, 4, 'Llave Cuello Plano Ft6185', 'Llave Cuello Plano Ft6185', 1, '64211.00', '19.00', '81000.00', 'Llave Cuello Plano Ft6185', '2024-10-10 17:45:05', '31548', 1, 1),
(5342, 4, 'Llave Cuerpo Abs Cuello Flexible C107 Flexible Fermetal', 'Llave Cuerpo Abs Cuello Flexible C107 Flexible Fermetal', 1, '23516.00', '19.00', '35000.00', 'Llave Cuerpo Abs Cuello Flexible C107 Flexible Fermetal', '2024-10-10 17:45:05', '7592032500946', 1, 1),
(5343, 4, 'Llave De  Tubo 8 Boccherini', 'Llave De  Tubo 8 Boccherini', 2, '12599.00', '19.00', '18000.00', 'Llave De  Tubo 8 Boccherini', '2024-10-10 17:45:05', '7707180678656', 1, 1),
(5344, 4, 'Llave De Lavadero Manija Palanca', 'Llave De Lavadero Manija Palanca', 1, '17899.00', '19.00', '20000.00', 'Llave De Lavadero Manija Palanca', '2024-10-10 17:45:05', '7707180676768', 1, 1),
(5345, 4, 'Llave De Lujo Ref45', 'Llave De Lujo Ref45', 1, '49500.00', '19.00', '60000.00', 'Llave De Lujo Ref45', '2024-10-10 17:45:05', '3155479', 1, 1),
(5346, 4, 'Llave De Paso 1 Lisa Uduke', 'Llave De Paso 1 Lisa Uduke', 2, '4300.00', '19.00', '6300.00', 'Llave De Paso 1 Lisa Uduke', '2024-10-10 17:45:05', '6973877760536', 1, 1),
(5347, 4, 'Llave De Paso 1/2 Rosca Uduke', 'Llave De Paso 1/2 Rosca Uduke', 3, '1800.00', '19.00', '2600.00', 'Llave De Paso 1/2 Rosca Uduke', '2024-10-10 17:45:05', '6973877760540', 1, 1),
(5348, 4, 'Llave De Paso Bola Lisa Uduke 1/2', 'Llave De Paso Bola Lisa Uduke 1/2', 7, '1750.00', '19.00', '2800.00', 'Llave De Paso Bola Lisa Uduke 1/2', '2024-10-10 17:45:05', '6973877760411', 1, 1),
(5349, 4, 'Llave De Paso Metalica 1/2 Boccherini', 'Llave De Paso Metalica 1/2 Boccherini', 0, '15099.00', '19.00', '22000.00', 'Llave De Paso Metalica 1/2 Boccherini', '2024-10-10 17:45:05', '7707766455138', 1, 1),
(5350, 4, 'Llave De Paso Naranja 1/2', 'Llave De Paso Naranja 1/2', 0, '4295.00', '19.00', '5800.00', 'Llave De Paso Naranja 1/2', '2024-10-10 17:45:05', '7707331940939', 1, 1),
(5351, 4, 'Llave De Paso Pvc 1 1/2 Rosca Uduke', 'Llave De Paso Pvc 1 1/2 Rosca Uduke', 4, '8970.00', '19.00', '12900.00', 'Llave De Paso Pvc 1 1/2 Rosca Uduke', '2024-10-10 17:45:05', '6973877760534', 1, 1),
(5352, 4, 'Llave De Paso Pvc 1 Rosca Uduke', 'Llave De Paso Pvc 1 Rosca Uduke', 5, '4370.00', '19.00', '6500.00', 'Llave De Paso Pvc 1 Rosca Uduke', '2024-10-10 17:45:05', '6973877760535', 1, 1),
(5353, 4, 'Llave De Paso Pvc 1/2 Lisa Uduke', 'Llave De Paso Pvc 1/2 Lisa Uduke', 1, '1950.00', '19.00', '2900.00', 'Llave De Paso Pvc 1/2 Lisa Uduke', '2024-10-10 17:45:05', '6973877760539', 1, 1),
(5354, 4, 'Llave De Paso Pvc 2 Lisa Uduke', 'Llave De Paso Pvc 2 Lisa Uduke', 3, '15180.00', '19.00', '21700.00', 'Llave De Paso Pvc 2 Lisa Uduke', '2024-10-10 17:45:05', '6973877760466', 1, 1),
(5355, 4, 'Llave De Paso Pvc 3/4 Lisa Uduke', 'Llave De Paso Pvc 3/4 Lisa Uduke', 5, '2700.00', '19.00', '3900.00', 'Llave De Paso Pvc 3/4 Lisa Uduke', '2024-10-10 17:45:05', '6973877760538', 1, 1),
(5356, 4, 'Llave De Paso Pvc 3/4 Rosca', 'Llave De Paso Pvc 3/4 Rosca', 3, '2700.00', '19.00', '3900.00', 'Llave De Paso Pvc 3/4 Rosca', '2024-10-10 17:45:05', '6973877760537', 1, 1),
(5357, 4, 'Llave De Paso Rosca 2 Uduke', 'Llave De Paso Rosca 2 Uduke', 3, '14370.00', '19.00', '20600.00', 'Llave De Paso Rosca 2 Uduke', '2024-10-10 17:45:05', '6973877760541', 1, 1),
(5358, 4, 'Llave De Paso Valvula Roscable De 1/2 Boccherini', 'Llave De Paso Valvula Roscable De 1/2 Boccherini', 1, '2400.00', '19.00', '4500.00', 'Llave De Paso Valvula Roscable De 1/2 Boccherini', '2024-10-10 17:45:05', '7707180676669', 1, 1),
(5359, 4, 'Llave De Taladro 13mm', 'Llave De Taladro 13mm', 4, '2100.00', '19.00', '3500.00', 'Llave De Taladro 13mm', '2024-10-10 17:45:05', '7707766452953', 1, 1),
(5360, 4, 'Llave De Tubo 10 Boccherini', 'Llave De Tubo 10 Boccherini', 3, '11900.00', '19.00', '17500.00', 'Llave De Tubo 10 Boccherini', '2024-10-10 17:45:05', '7707180678663', 1, 1),
(5361, 4, 'Llave Dorada R/ 88182', 'Llave Dorada R/ 88182', 1, '161999.00', '19.00', '202500.00', 'Llave Dorada R/ 88182', '2024-10-10 17:45:05', '1200040', 1, 1),
(5362, 4, 'Llave Extraible Negra I.O Tools Cabza Cromada', 'Llave Extraible Negra I.O Tools Cabza Cromada', 1, '90000.00', '19.00', '150000.00', 'Llave Extraible Negra I.O Tools Cabza Cromada', '2024-10-10 17:45:05', 'GRIF-01A', 1, 1),
(5363, 4, 'Llave Flexible Negra R/ 5076', 'Llave Flexible Negra R/ 5076', 0, '74997.00', '19.00', '94000.00', 'Llave Flexible Negra R/ 5076', '2024-10-10 17:45:05', '12000300', 1, 1),
(5364, 4, 'Llave Flexible Pared HT60091', 'Llave Flexible Pared HT60091', 1, '22135.00', '19.00', '35000.00', 'Llave Flexible Pared HT60091', '2024-10-10 17:45:05', '6973653175088', 1, 1),
(5365, 4, 'Llave Grifo Blanca Uduke', 'Llave Grifo Blanca Uduke', 6, '1853.00', '19.00', '2600.00', 'Llave Grifo Blanca Uduke', '2024-10-10 17:45:05', '6973653179222', 1, 1),
(5366, 4, 'Llave Grifo Palanca Con Racor Uduke Blanca', 'Llave Grifo Palanca Con Racor Uduke Blanca', 0, '2000.00', '19.00', '3500.00', 'Llave Grifo Palanca Con Racor Uduke Blanca', '2024-10-10 17:45:05', '6973653179246', 1, 1),
(5367, 4, 'Llave Individual Lpts Boccherini', 'Llave Individual Lpts Boccherini', 0, '35799.00', '19.00', '52000.00', 'Llave Individual Lpts Boccherini', '2024-10-10 17:45:05', '7707766451475', 1, 1),
(5368, 4, 'Llave Individual Lvms Buga Boccherini', 'Llave Individual Lvms Buga Boccherini', 2, '20000.00', '19.00', '29000.00', 'Llave Individual Lvms Buga Boccherini', '2024-10-10 17:45:05', '7707766453806', 1, 1),
(5369, 4, 'Llave Individual Lvms Nuqui Boccherini', 'Llave Individual Lvms Nuqui Boccherini', 1, '19099.00', '19.00', '28000.00', 'Llave Individual Lvms Nuqui Boccherini', '2024-10-10 17:45:05', '7707766459693', 1, 1),
(5370, 4, 'Llave Jardin Bronce Grival', 'Llave Jardin Bronce Grival', 2, '24359.00', '19.00', '35000.00', 'Llave Jardin Bronce Grival', '2024-10-10 17:45:05', '34', 1, 1),
(5371, 4, 'Llave Jardin Manguera Rioplast', 'Llave Jardin Manguera Rioplast', 2, '3500.00', '19.00', '6000.00', 'Llave Jardin Manguera Rioplast', '2024-10-10 17:45:05', '13', 1, 1),
(5372, 4, 'Llave Jardin Satinada Grival', 'Llave Jardin Satinada Grival', 3, '24899.00', '19.00', '35000.00', 'Llave Jardin Satinada Grival', '2024-10-10 17:45:05', '33', 1, 1),
(5373, 4, 'Llave Jardinera Para Manguera De Bola De 1/2', 'Llave Jardinera Para Manguera De Bola De 1/2', 0, '11319.00', '19.00', '16000.00', 'Llave Jardinera Para Manguera De Bola De 1/2', '2024-10-10 17:45:05', '7592032006660', 1, 1),
(5374, 4, 'Llave Lavamanos Baja  R/75c30', 'Llave Lavamanos Baja  R/75c30', 1, '119999.00', '19.00', '150000.00', 'Llave Lavamanos Baja  R/75c30', '2024-10-10 17:45:05', '1200010', 1, 1),
(5375, 4, 'Llave Lavamanos Cromada Rio Plast', 'Llave Lavamanos Cromada Rio Plast', 0, '8392.00', '19.00', '11800.00', 'Llave Lavamanos Cromada Rio Plast', '2024-10-10 17:45:05', '320011211', 1, 1),
(5376, 4, 'Llave Lavamanos Diamante Rioplast', 'Llave Lavamanos Diamante Rioplast', 2, '7385.00', '19.00', '12000.00', 'Llave Lavamanos Diamante Rioplast', '2024-10-10 17:45:05', '24206', 1, 1),
(5377, 4, 'Llave Lavamanos Individual Aleta Negro Mate Uduke HT1565', 'Llave Lavamanos Individual Aleta Negro Mate Uduke HT1565', 2, '12900.00', '19.00', '18000.00', 'Llave Lavamanos Individual Aleta Negro Mate Uduke HT1565', '2024-10-10 17:45:05', '6973877765713', 1, 1),
(5378, 4, 'Llave Lavamanos Individual Cruceta Negro Mate Uduke HT1564', 'Llave Lavamanos Individual Cruceta Negro Mate Uduke HT1564', 3, '10250.00', '19.00', '16000.00', 'Llave Lavamanos Individual Cruceta Negro Mate Uduke HT1564', '2024-10-10 17:45:05', '6973877765706', 1, 1),
(5379, 4, 'Llave Lavamanos Nogal De Grival', 'Llave Lavamanos Nogal De Grival', 2, '24500.00', '19.00', '29500.00', 'Llave Lavamanos Nogal De Grival', '2024-10-10 17:45:05', '541133331', 1, 1),
(5380, 4, 'Llave Lavamanos Rose Media', 'Llave Lavamanos Rose Media', 1, '129999.00', '19.00', '162500.00', 'Llave Lavamanos Rose Media', '2024-10-10 17:45:05', '1200200', 1, 1),
(5381, 4, 'Llave Lavaplatos Aleta Nogal Line Agrival 12348', 'Llave Lavaplatos Aleta Nogal Line Agrival 12348', 1, '51350.00', '19.00', '71500.00', 'Llave Lavaplatos Aleta Nogal Line Agrival 12348', '2024-10-10 17:45:05', '4445', 1, 1),
(5382, 4, 'Llave Lavaplatos Meson Tipo Pomo Boccherini', 'Llave Lavaplatos Meson Tipo Pomo Boccherini', 0, '24099.00', '19.00', '30000.00', 'Llave Lavaplatos Meson Tipo Pomo Boccherini', '2024-10-10 17:45:05', '7707180676799', 1, 1),
(5383, 4, 'Llave Lavaplatos Uduke Ht60093', 'Llave Lavaplatos Uduke Ht60093', 5, '24802.00', '19.00', '35500.00', 'Llave Lavaplatos Uduke Ht60093', '2024-10-10 17:45:05', '6973653170694', 1, 1),
(5384, 4, 'Llave Lvm T:palanca Boccherini', 'Llave Lvm T:palanca Boccherini', 1, '17600.00', '19.00', '26000.00', 'Llave Lvm T:palanca Boccherini', '2024-10-10 17:45:05', '7707180675389', 1, 1),
(5385, 4, 'Llave Lvms Fermetal C100', 'Llave Lvms Fermetal C100', 3, '8400.00', '19.00', '12000.00', 'Llave Lvms Fermetal C100', '2024-10-10 17:45:05', '7592032110015', 1, 1),
(5386, 4, 'Llave Lvms Fermetal C104LM', 'Llave Lvms Fermetal C104LM', 4, '12392.00', '19.00', '18500.00', 'Llave Lvms Fermetal C104LM', '2024-10-10 17:45:05', '7592032111272', 1, 1),
(5387, 4, 'Llave Lvms Fermetal C201', 'Llave Lvms Fermetal C201', 2, '8400.00', '19.00', '12000.00', 'Llave Lvms Fermetal C201', '2024-10-10 17:45:05', '7592032111289', 1, 1),
(5388, 4, 'Llave Lvpt Flexible Mercury Fgr14', 'Llave Lvpt Flexible Mercury Fgr14', 0, '31999.00', '19.00', '50000.00', 'Llave Lvpt Flexible Mercury Fgr14', '2024-10-10 17:45:06', '7707692863076', 1, 1),
(5389, 4, 'Llave Lvts Flexible Pared Fgr15 Mercury', 'Llave Lvts Flexible Pared Fgr15 Mercury', 0, '25579.00', '19.00', '45000.00', 'Llave Lvts Flexible Pared Fgr15 Mercury', '2024-10-10 17:45:06', '7707692867906', 1, 1),
(5390, 4, 'Llave Manguera Superliviana Cobre Boccherini', 'Llave Manguera Superliviana Cobre Boccherini', 2, '17600.00', '19.00', '26000.00', 'Llave Manguera Superliviana Cobre Boccherini', '2024-10-10 17:45:06', '7707766450133', 1, 1),
(5391, 4, 'Llave Media Dorada', 'Llave Media Dorada', 1, '54999.00', '19.00', '68750.00', 'Llave Media Dorada', '2024-10-10 17:45:06', '1200131', 1, 1),
(5392, 4, 'Llave Mesa Aleta Plast Grifos', 'Llave Mesa Aleta Plast Grifos', 1, '21800.00', '19.00', '29500.00', 'Llave Mesa Aleta Plast Grifos', '2024-10-10 17:45:06', '7700031018823', 1, 1),
(5393, 4, 'Llave Mesa Uduke Ht1184', 'Llave Mesa Uduke Ht1184', 2, '21962.00', '19.00', '32000.00', 'Llave Mesa Uduke Ht1184', '2024-10-10 17:45:06', '6973653175019', 1, 1),
(5394, 4, 'Llave Meson Fa12104 Boccherini', 'Llave Meson Fa12104 Boccherini', 0, '9910.00', '19.00', '18000.00', 'Llave Meson Fa12104 Boccherini', '2024-10-10 17:45:06', '7707180675259', 1, 1),
(5395, 4, 'Llave Meson Fa12106 Boccherini T:palanca', 'Llave Meson Fa12106 Boccherini T:palanca', 1, '21499.00', '19.00', '32000.00', 'Llave Meson Fa12106 Boccherini T:palanca', '2024-10-10 17:45:06', '7707180675266', 1, 1),
(5396, 4, 'Llave Meson Fa12163 Boccherini', 'Llave Meson Fa12163 Boccherini', 0, '23800.00', '19.00', '30000.00', 'Llave Meson Fa12163 Boccherini', '2024-10-10 17:45:06', '7707180677307', 1, 1),
(5397, 4, 'Llave Meson Palanca Yw008 Boccherini', 'Llave Meson Palanca Yw008 Boccherini', 0, '17500.00', '19.00', '28000.00', 'Llave Meson Palanca Yw008 Boccherini', '2024-10-10 17:45:06', '122', 1, 1),
(5398, 4, 'Llave Negra Lvms FT2804CB', 'Llave Negra Lvms FT2804CB', 0, '38947.00', '19.00', '49000.00', 'Llave Negra Lvms FT2804CB', '2024-10-10 17:45:06', '25483', 1, 1),
(5399, 4, 'Llave Noble Media Negra R/122254', 'Llave Noble Media Negra R/122254', 1, '94998.00', '19.00', '118750.00', 'Llave Noble Media Negra R/122254', '2024-10-10 17:45:06', '22000254', 1, 1),
(5400, 4, 'Llave Oro Rosa Baru R/ 8887', 'Llave Oro Rosa Baru R/ 8887', 1, '182070.00', '19.00', '227500.00', 'Llave Oro Rosa Baru R/ 8887', '2024-10-10 17:45:06', '12010004', 1, 1),
(5401, 4, 'Llave Palaca Negra HT1559', 'Llave Palaca Negra HT1559', 2, '23228.00', '19.00', '36800.00', 'Llave Palaca Negra HT1559', '2024-10-10 17:45:06', '6973877765652', 1, 1),
(5402, 4, 'Llave Palanca Inoxidable Uduke Ht60091', 'Llave Palanca Inoxidable Uduke Ht60091', 2, '26506.00', '19.00', '38000.00', 'Llave Palanca Inoxidable Uduke Ht60091', '2024-10-10 17:45:06', '6973653170670', 1, 1),
(5403, 4, 'Llave Para Lavadora Boccherini', 'Llave Para Lavadora Boccherini', 2, '14400.00', '19.00', '21000.00', 'Llave Para Lavadora Boccherini', '2024-10-10 17:45:06', '7707180679608', 1, 1),
(5404, 4, 'Llave Para Lavamanos Manija Acrilico Boccherini', 'Llave Para Lavamanos Manija Acrilico Boccherini', 5, '25500.00', '19.00', '37000.00', 'Llave Para Lavamanos Manija Acrilico Boccherini', '2024-10-10 17:45:06', '7707180676775', 1, 1),
(5405, 4, 'Llave Para Lavaplatos Flexible Blanca Unifer', 'Llave Para Lavaplatos Flexible Blanca Unifer', 2, '33000.00', '19.00', '46200.00', 'Llave Para Lavaplatos Flexible Blanca Unifer', '2024-10-10 17:45:06', '112502WHT', 1, 1),
(5406, 4, 'Llave Para Taladro 10mm', 'Llave Para Taladro 10mm', 6, '1900.00', '19.00', '3000.00', 'Llave Para Taladro 10mm', '2024-10-10 17:45:06', '7707766456708', 1, 1),
(5407, 4, 'Llave Pared Flexible Mercury', 'Llave Pared Flexible Mercury', 0, '25579.00', '19.00', '40000.00', 'Llave Pared Flexible Mercury', '2024-10-10 17:45:06', '159', 1, 1),
(5408, 4, 'Llave Pared Negra Ht1560', 'Llave Pared Negra Ht1560', 3, '15960.00', '19.00', '29500.00', 'Llave Pared Negra Ht1560', '2024-10-10 17:45:06', '6973877765669', 1, 1),
(5409, 4, 'Llave Pared Palanca HT1187', 'Llave Pared Palanca HT1187', 3, '14229.00', '19.00', '26500.00', 'Llave Pared Palanca HT1187', '2024-10-10 17:45:06', '6973653175040', 1, 1),
(5410, 4, 'Llave Peston USA 12', 'Llave Peston USA 12', 0, '17400.00', '19.00', '22000.00', 'Llave Peston USA 12', '2024-10-10 17:45:06', '6973653174340', 1, 1),
(5411, 4, 'Llave S Meson T:cruceta Fa12162 Boccherini', 'Llave S Meson T:cruceta Fa12162 Boccherini', 0, '25882.00', '19.00', '35000.00', 'Llave S Meson T:cruceta Fa12162 Boccherini', '2024-10-10 17:45:06', '7707180677291', 1, 1),
(5412, 4, 'Llave Sencilla Boccherni Tipo Palanca', 'Llave Sencilla Boccherni Tipo Palanca', 0, '20800.00', '19.00', '26000.00', 'Llave Sencilla Boccherni Tipo Palanca', '2024-10-10 17:45:06', '7707180679677', 1, 1),
(5413, 4, 'Llave Sencilla De Pared Boccherini', 'Llave Sencilla De Pared Boccherini', 2, '18999.00', '19.00', '27000.00', 'Llave Sencilla De Pared Boccherini', '2024-10-10 17:45:06', '7707180675167', 1, 1),
(5414, 4, 'Llave Sencilla Meson Lvpts Blanca Boccherini', 'Llave Sencilla Meson Lvpts Blanca Boccherini', 0, '18499.00', '19.00', '27000.00', 'Llave Sencilla Meson Lvpts Blanca Boccherini', '2024-10-10 17:45:06', '7707180677284', 1, 1),
(5415, 4, 'Llave Sencilla Tipo Cruceta JYN112 Boccherini', 'Llave Sencilla Tipo Cruceta JYN112 Boccherini', 1, '17999.00', '19.00', '28000.00', 'Llave Sencilla Tipo Cruceta JYN112 Boccherini', '2024-10-10 17:45:06', '7707180672173', 1, 1),
(5416, 4, 'Llavero Tipo Llave Expansiva', 'Llavero Tipo Llave Expansiva', 1, '3944.00', '19.00', '5000.00', 'Llavero Tipo Llave Expansiva', '2024-10-10 17:45:06', '7501206618677', 1, 1),
(5417, 4, 'MEZCLADOR LAVAPLATOS MANAURE BLANCO 8  BCH 123', 'MEZCLADOR LAVAPLATOS MANAURE BLANCO 8  BCH 123', 1, '50700.00', '19.00', '71500.00', 'MEZCLADOR LAVAPLATOS MANAURE BLANCO 8  BCH 123', '2024-10-10 17:45:06', '7707766452168', 1, 1),
(5418, 4, 'Manguera De Nievel  X Metros', 'Manguera De Nievel  X Metros', 1, '0.00', '19.00', '1700.00', 'Manguera De Nievel  X Metros', '2024-10-10 17:45:06', '3155478', 1, 1),
(5419, 4, 'Manguera Jardin X Metros De 1/2', 'Manguera Jardin X Metros De 1/2', 66, '1633.00', '19.00', '2500.00', 'Manguera Jardin X Metros De 1/2', '2024-10-10 17:45:06', '25046', 1, 1),
(5420, 4, 'Manija Boton', 'Manija Boton', 2, '5300.00', '19.00', '7000.00', 'Manija Boton', '2024-10-10 17:45:06', '7700031011121', 1, 1),
(5421, 4, 'Manija Para Sanitario Grifo Plast', 'Manija Para Sanitario Grifo Plast', 9, '2300.00', '19.00', '3500.00', 'Manija Para Sanitario Grifo Plast', '2024-10-10 17:45:06', '2520', 1, 1),
(5422, 4, 'Manija Platino Grande', 'Manija Platino Grande', 4, '6319.00', '19.00', '9000.00', 'Manija Platino Grande', '2024-10-10 17:45:06', '7703231012424', 1, 1),
(5423, 4, 'Manija Tanq Sanitario  Grival', 'Manija Tanq Sanitario  Grival', 2, '13757.00', '19.00', '18000.00', 'Manija Tanq Sanitario  Grival', '2024-10-10 17:45:06', '51', 1, 1),
(5424, 4, 'Manija Tanque', 'Manija Tanque', 2, '2800.00', '19.00', '3800.00', 'Manija Tanque', '2024-10-10 17:45:06', '7700032105423', 1, 1),
(5425, 4, 'Mapeset Gris Bulto X 25kg', 'Mapeset Gris Bulto X 25kg', 1, '18669.00', '19.00', '26800.00', 'Mapeset Gris Bulto X 25kg', '2024-10-10 17:45:06', '6625', 1, 1),
(5426, 4, 'Marco Para Segueta Boccherini', 'Marco Para Segueta Boccherini', 2, '9099.00', '19.00', '15000.00', 'Marco Para Segueta Boccherini', '2024-10-10 17:45:06', '7707180678922', 1, 1),
(5427, 4, 'Martillo 10 onzas Pequeño', 'Martillo 10 onzas Pequeño', 0, '13299.00', '19.00', '19000.00', 'Martillo 10 onzas Pequeño', '2024-10-10 17:45:06', '7707180678991', 1, 1),
(5428, 4, 'Martillo De Uña 14 Onzas', 'Martillo De Uña 14 Onzas', 3, '8092.00', '19.00', '13000.00', 'Martillo De Uña 14 Onzas', '2024-10-10 17:45:06', '7707180679004', 1, 1),
(5429, 4, 'Martillo De Uña 16 Onzas', 'Martillo De Uña 16 Onzas', 5, '8925.00', '19.00', '15000.00', 'Martillo De Uña 16 Onzas', '2024-10-10 17:45:06', '7707180679011', 1, 1),
(5430, 4, 'Martillo Metalico 16 Onz Boccherini', 'Martillo Metalico 16 Onz Boccherini', 2, '15099.00', '19.00', '21600.00', 'Martillo Metalico 16 Onz Boccherini', '2024-10-10 17:45:06', '7707180679035', 1, 1),
(5431, 4, 'Masilla Maxternit 1/16', 'Masilla Maxternit 1/16', 3, '5200.00', '19.00', '7500.00', 'Masilla Maxternit 1/16', '2024-10-10 17:45:06', '2508', 1, 1),
(5432, 4, 'Masilla Maxternit 1/32', 'Masilla Maxternit 1/32', 6, '3900.00', '19.00', '5700.00', 'Masilla Maxternit 1/32', '2024-10-10 17:45:06', '2507', 1, 1),
(5433, 4, 'Masilla Maxternit 1/4', 'Masilla Maxternit 1/4', 2, '12800.00', '19.00', '18500.00', 'Masilla Maxternit 1/4', '2024-10-10 17:45:06', '2510', 1, 1),
(5434, 4, 'Masilla Maxternit 1/8', 'Masilla Maxternit 1/8', 3, '8000.00', '19.00', '11500.00', 'Masilla Maxternit 1/8', '2024-10-10 17:45:06', '2509', 1, 1),
(5435, 4, 'Masilla Poliester + Catalizador  850gr Every', 'Masilla Poliester + Catalizador  850gr Every', 4, '18359.00', '19.00', '25000.00', 'Masilla Poliester + Catalizador  850gr Every', '2024-10-10 17:45:06', 'L850', 1, 1),
(5436, 4, 'Masilla Sintesolda 30grs', 'Masilla Sintesolda 30grs', 1, '2558.00', '19.00', '3700.00', 'Masilla Sintesolda 30grs', '2024-10-10 17:45:06', '7707210600404', 1, 1),
(5437, 4, 'Masilla Sintesolda Epoxi 50grs', 'Masilla Sintesolda Epoxi 50grs', 5, '3881.00', '19.00', '5900.00', 'Masilla Sintesolda Epoxi 50grs', '2024-10-10 17:45:06', '7707210601265', 1, 1),
(5438, 4, 'Mazo De Goma 16oz Boccherini', 'Mazo De Goma 16oz Boccherini', 3, '4600.00', '19.00', '7000.00', 'Mazo De Goma 16oz Boccherini', '2024-10-10 17:45:06', '707766454802', 1, 1),
(5439, 4, 'Mazo De Goma 24 Onz', 'Mazo De Goma 24 Onz', 3, '8400.00', '19.00', '12000.00', 'Mazo De Goma 24 Onz', '2024-10-10 17:45:06', '7707766454803', 1, 1),
(5440, 4, 'Mazo De Goma 8oz Boccherini', 'Mazo De Goma 8oz Boccherini', 3, '3999.00', '19.00', '6000.00', 'Mazo De Goma 8oz Boccherini', '2024-10-10 17:45:06', '7707766454801', 1, 1),
(5441, 4, 'Mega Pega Ceramico X 25kg Corona', 'Mega Pega Ceramico X 25kg Corona', 1, '20131.00', '19.00', '25200.00', 'Mega Pega Ceramico X 25kg Corona', '2024-10-10 17:45:06', '7707181799947', 1, 1),
(5442, 4, 'Metalzinc 1/16', 'Metalzinc 1/16', 3, '5300.00', '19.00', '7600.00', 'Metalzinc 1/16', '2024-10-10 17:45:06', '2512', 1, 1),
(5443, 4, 'Metalzinc 1/32', 'Metalzinc 1/32', 6, '4000.00', '19.00', '5800.00', 'Metalzinc 1/32', '2024-10-10 17:45:06', '2511', 1, 1),
(5444, 4, 'Metalzinc 1/4', 'Metalzinc 1/4', 2, '14300.00', '19.00', '20500.00', 'Metalzinc 1/4', '2024-10-10 17:45:06', '2514', 1, 1),
(5445, 4, 'Metalzinc 1/8', 'Metalzinc 1/8', 1, '9100.00', '19.00', '13000.00', 'Metalzinc 1/8', '2024-10-10 17:45:06', '2513', 1, 1),
(5446, 4, 'Metro 5 Metros Boton', 'Metro 5 Metros Boton', 1, '7900.00', '19.00', '11000.00', 'Metro 5 Metros Boton', '2024-10-10 17:45:06', '6925582187953', 1, 1),
(5447, 4, 'Metro Uduke 7 Metros', 'Metro Uduke 7 Metros', 1, '8800.00', '19.00', '12000.00', 'Metro Uduke 7 Metros', '2024-10-10 17:45:06', '8885', 1, 1),
(5448, 4, 'Metro X 7.5 Metros uduque', 'Metro X 7.5 Metros uduque', 0, '8800.00', '19.00', '12000.00', 'Metro X 7.5 Metros uduque', '2024-10-10 17:45:06', '6973877760848', 1, 1),
(5449, 4, 'Mezclacdor Lavaplatos Plastico Negro Fermetal GRI 4001', 'Mezclacdor Lavaplatos Plastico Negro Fermetal GRI 4001', 1, '46427.00', '19.00', '66500.00', 'Mezclacdor Lavaplatos Plastico Negro Fermetal GRI 4001', '2024-10-10 17:45:06', '7592032509031', 1, 1),
(5450, 4, 'Mezclador 8 Tipo Cruceta FA80090B Boccherini', 'Mezclador 8 Tipo Cruceta FA80090B Boccherini', 1, '34900.00', '19.00', '50000.00', 'Mezclador 8 Tipo Cruceta FA80090B Boccherini', '2024-10-10 17:45:06', '7707180672708', 1, 1),
(5451, 4, 'Mezclador 8 Tipo Palanca FA80090A Boccherini', 'Mezclador 8 Tipo Palanca FA80090A Boccherini', 0, '34900.00', '19.00', '55000.00', 'Mezclador 8 Tipo Palanca FA80090A Boccherini', '2024-10-10 17:45:06', '7707180672692', 1, 1),
(5452, 4, 'Mezclador De 8 2 Chorros  Boccherini', 'Mezclador De 8 2 Chorros  Boccherini', 0, '56899.00', '19.00', '85000.00', 'Mezclador De 8 2 Chorros  Boccherini', '2024-10-10 17:45:06', '7707180677673', 1, 1),
(5453, 4, 'Mezclador FLEXIBLE Cruceta Uduke Ht1182', 'Mezclador FLEXIBLE Cruceta Uduke Ht1182', 0, '36650.00', '19.00', '49500.00', 'Mezclador FLEXIBLE Cruceta Uduke Ht1182', '2024-10-10 17:45:06', '6973877763238', 1, 1),
(5454, 4, 'Mezclador Flexible Palanca Uduke Ht1181', 'Mezclador Flexible Palanca Uduke Ht1181', 0, '47794.00', '19.00', '68500.00', 'Mezclador Flexible Palanca Uduke Ht1181', '2024-10-10 17:45:06', '6973653174999', 1, 1),
(5455, 4, 'Mezclador Lavamanos 4  Buga Boccherini', 'Mezclador Lavamanos 4  Buga Boccherini', 2, '45999.00', '19.00', '66000.00', 'Mezclador Lavamanos 4  Buga Boccherini', '2024-10-10 17:45:06', '7707766458719', 1, 1),
(5456, 4, 'Mezclador Lavamanos Cruceta Negro Mate Uduke', 'Mezclador Lavamanos Cruceta Negro Mate Uduke', 1, '30300.00', '19.00', '42000.00', 'Mezclador Lavamanos Cruceta Negro Mate Uduke', '2024-10-10 17:45:06', '6973877765690', 1, 1),
(5457, 4, 'Mezclador Lavamanos Tipo Palanca Boccherini', 'Mezclador Lavamanos Tipo Palanca Boccherini', 0, '37900.00', '19.00', '55000.00', 'Mezclador Lavamanos Tipo Palanca Boccherini', '2024-10-10 17:45:06', '7707180679707', 1, 1),
(5458, 4, 'Mezclador Lavaplatos  8¨¨ Palanca Balta Linea Grival', 'Mezclador Lavaplatos  8¨¨ Palanca Balta Linea Grival', 1, '73450.00', '19.00', '95500.00', 'Mezclador Lavaplatos  8¨¨ Palanca Balta Linea Grival', '2024-10-10 17:45:06', '7706157664456', 1, 1),
(5459, 4, 'Mezclador Lavaplatos 8 Pulgadas Linea Grival', 'Mezclador Lavaplatos 8 Pulgadas Linea Grival', 1, '60650.00', '19.00', '84500.00', 'Mezclador Lavaplatos 8 Pulgadas Linea Grival', '2024-10-10 17:45:06', '325703331', 1, 1),
(5460, 4, 'Mezclador Lavaplatos Aleta Nogal Linea Grival', 'Mezclador Lavaplatos Aleta Nogal Linea Grival', 2, '70850.00', '19.00', '92500.00', 'Mezclador Lavaplatos Aleta Nogal Linea Grival', '2024-10-10 17:45:06', '455923331', 1, 1),
(5461, 4, 'Mezclador Lavaplatos Aleta Plast Grifos 5908', 'Mezclador Lavaplatos Aleta Plast Grifos 5908', 2, '32150.00', '19.00', '45000.00', 'Mezclador Lavaplatos Aleta Plast Grifos 5908', '2024-10-10 17:45:06', '7700031818324', 1, 1),
(5462, 4, 'Mezclador Lavaplatos Cruceta Negro Mate Uduke HT1554', 'Mezclador Lavaplatos Cruceta Negro Mate Uduke HT1554', 2, '29650.00', '19.00', '43500.00', 'Mezclador Lavaplatos Cruceta Negro Mate Uduke HT1554', '2024-10-10 17:45:06', '6973877765607', 1, 1),
(5463, 4, 'Mezclador Lavaplatos Negro Mate 8 Aleta Uduke', 'Mezclador Lavaplatos Negro Mate 8 Aleta Uduke', 2, '31650.00', '19.00', '45000.00', 'Mezclador Lavaplatos Negro Mate 8 Aleta Uduke', '2024-10-10 17:45:06', '6973877765744', 1, 1),
(5464, 4, 'Mezclador Lavaplatos Plastico Negro Fermetal GRI 4008', 'Mezclador Lavaplatos Plastico Negro Fermetal GRI 4008', 1, '59547.00', '19.00', '71500.00', 'Mezclador Lavaplatos Plastico Negro Fermetal GRI 4008', '2024-10-10 17:45:06', '7592032509109', 1, 1),
(5465, 4, 'Mezclador Lavaplatosmesa 8 Boccherini', 'Mezclador Lavaplatosmesa 8 Boccherini', 2, '49700.00', '19.00', '71000.00', 'Mezclador Lavaplatosmesa 8 Boccherini', '2024-10-10 17:45:06', '7707766454636', 1, 1),
(5466, 4, 'Mezclador Lvpts Fermetal Gri-1014p', 'Mezclador Lvpts Fermetal Gri-1014p', 0, '28800.00', '19.00', '45000.00', 'Mezclador Lvpts Fermetal Gri-1014p', '2024-10-10 17:45:06', '7592032506719', 1, 1),
(5467, 4, 'Mezclador Lvpts Gri-1013-1 Fermetal', 'Mezclador Lvpts Gri-1013-1 Fermetal', 2, '28800.00', '19.00', '45000.00', 'Mezclador Lvpts Gri-1013-1 Fermetal', '2024-10-10 17:45:06', '7592032110817', 1, 1),
(5468, 4, 'Mezclador Lvpts Manilla Orion Gri-10130', 'Mezclador Lvpts Manilla Orion Gri-10130', 2, '28800.00', '19.00', '45000.00', 'Mezclador Lvpts Manilla Orion Gri-10130', '2024-10-10 17:45:06', '7592032505736', 1, 1),
(5469, 4, 'Mezclador Lvpts Tipo Palanca Boccherini', 'Mezclador Lvpts Tipo Palanca Boccherini', 0, '36999.00', '19.00', '55000.00', 'Mezclador Lvpts Tipo Palanca Boccherini', '2024-10-10 17:45:06', '7707180675365', 1, 1),
(5470, 4, 'Mezclador Nogal Subj Grival', 'Mezclador Nogal Subj Grival', 2, '62000.00', '19.00', '85000.00', 'Mezclador Nogal Subj Grival', '2024-10-10 17:45:06', '706157041042', 1, 1),
(5471, 4, 'Mezclador Palanca Uduke Ht1180', 'Mezclador Palanca Uduke Ht1180', 1, '38384.00', '19.00', '46500.00', 'Mezclador Palanca Uduke Ht1180', '2024-10-10 17:45:06', '6973653174982', 1, 1),
(5472, 4, 'Mezclador Plast Grifos Cruceta 5909', 'Mezclador Plast Grifos Cruceta 5909', 1, '31150.00', '19.00', '43600.00', 'Mezclador Plast Grifos Cruceta 5909', '2024-10-10 17:45:06', '7700031818423', 1, 1),
(5473, 4, 'Mezclador Yw269 Boccherini Tipo Palanca', 'Mezclador Yw269 Boccherini Tipo Palanca', 3, '19000.00', '19.00', '57000.00', 'Mezclador Yw269 Boccherini Tipo Palanca', '2024-10-10 17:45:06', '7707180679691', 1, 1),
(5474, 4, 'Mineral Caja X 500grs Amarillo', 'Mineral Caja X 500grs Amarillo', 6, '2300.00', '19.00', '3300.00', 'Mineral Caja X 500grs Amarillo', '2024-10-10 17:45:06', '40016', 1, 1),
(5475, 4, 'Mineral Caja X 500grs Azul', 'Mineral Caja X 500grs Azul', 0, '2300.00', '19.00', '3300.00', 'Mineral Caja X 500grs Azul', '2024-10-10 17:45:06', '40018', 1, 1),
(5476, 4, 'Mineral Caja X 500grs Blanco', 'Mineral Caja X 500grs Blanco', 4, '2300.00', '19.00', '3300.00', 'Mineral Caja X 500grs Blanco', '2024-10-10 17:45:06', '40014', 1, 1),
(5477, 4, 'Mineral Caja X 500grs Cafe', 'Mineral Caja X 500grs Cafe', 6, '2300.00', '19.00', '3300.00', 'Mineral Caja X 500grs Cafe', '2024-10-10 17:45:06', '40020', 1, 1),
(5478, 4, 'Mineral Caja X500 Grs Negro', 'Mineral Caja X500 Grs Negro', 5, '2.00', '19.00', '3300.00', 'Mineral Caja X500 Grs Negro', '2024-10-10 17:45:06', '40019', 1, 1),
(5479, 4, 'Mineral Caja X500grs Rojo', 'Mineral Caja X500grs Rojo', 4, '2300.00', '19.00', '3300.00', 'Mineral Caja X500grs Rojo', '2024-10-10 17:45:06', '40015', 1, 1),
(5480, 4, 'Mineral Cajax 500grs Verde', 'Mineral Cajax 500grs Verde', 6, '2300.00', '19.00', '3300.00', 'Mineral Cajax 500grs Verde', '2024-10-10 17:45:06', '40017', 1, 1),
(5481, 4, 'Mini Cortador De Tubo Boccherini', 'Mini Cortador De Tubo Boccherini', 1, '9100.00', '19.00', '13000.00', 'Mini Cortador De Tubo Boccherini', '2024-10-10 17:45:06', '7707766455381', 1, 1),
(5482, 4, 'Mini Pinza De Punta Redonda 4.5', 'Mini Pinza De Punta Redonda 4.5', 2, '6799.00', '19.00', '10500.00', 'Mini Pinza De Punta Redonda 4.5', '2024-10-10 17:45:06', '7707766451628', 1, 1),
(5483, 4, 'Mini Rodillo Caribe 3', 'Mini Rodillo Caribe 3', 1, '2445.00', '19.00', '3500.00', 'Mini Rodillo Caribe 3', '2024-10-10 17:45:06', '7707342742430', 1, 1),
(5484, 4, 'Mini Rodillo De 4 Caribe', 'Mini Rodillo De 4 Caribe', 4, '2844.00', '19.00', '4200.00', 'Mini Rodillo De 4 Caribe', '2024-10-10 17:45:06', '7707342742447', 1, 1),
(5485, 4, 'Monocontrol Cisne FT2804', 'Monocontrol Cisne FT2804', 1, '84211.00', '19.00', '106000.00', 'Monocontrol Cisne FT2804', '2024-10-10 17:45:06', '35571', 1, 1),
(5486, 4, 'Monocontrol Cuadrado FN16806SH', 'Monocontrol Cuadrado FN16806SH', 1, '96842.00', '19.00', '122000.00', 'Monocontrol Cuadrado FN16806SH', '2024-10-10 17:45:06', '254785', 1, 1),
(5487, 4, 'Monocontrol Lvms F10101H-GM', 'Monocontrol Lvms F10101H-GM', 1, '173684.00', '19.00', '218000.00', 'Monocontrol Lvms F10101H-GM', '2024-10-10 17:45:06', '54782', 1, 1),
(5488, 4, 'Monocontrol Negra Mate FT2012B', 'Monocontrol Negra Mate FT2012B', 1, '115789.00', '19.00', '145000.00', 'Monocontrol Negra Mate FT2012B', '2024-10-10 17:45:06', '355478', 1, 1),
(5489, 4, 'Monocontrol Negra Mate FT2804B', 'Monocontrol Negra Mate FT2804B', 1, '100000.00', '19.00', '125000.00', 'Monocontrol Negra Mate FT2804B', '2024-10-10 17:45:06', '35547', 1, 1),
(5490, 4, 'Mosqueton Delta Acero 8mm', 'Mosqueton Delta Acero 8mm', 6, '2731.00', '19.00', '4500.00', 'Mosqueton Delta Acero 8mm', '2024-10-10 17:45:06', '7501206694510', 1, 1),
(5491, 4, 'Mueble Asiento Saniutario Corona Beige HT - 869713331', 'Mueble Asiento Saniutario Corona Beige HT - 869713331', 1, '29800.00', '19.00', '37000.00', 'Mueble Asiento Saniutario Corona Beige HT - 869713331', '2024-10-10 17:45:06', '869713331', 1, 1),
(5492, 4, 'Mueble Para Baño Cris Oscuro', 'Mueble Para Baño Cris Oscuro', 0, '364140.00', '19.00', '419000.00', 'Mueble Para Baño Cris Oscuro', '2024-10-10 17:45:06', '60000', 1, 1),
(5493, 4, 'Mueble Sanitario Beige Cardenas', 'Mueble Sanitario Beige Cardenas', 0, '23999.00', '19.00', '30000.00', 'Mueble Sanitario Beige Cardenas', '2024-10-10 17:45:06', '56', 1, 1),
(5494, 4, 'Mueble Sanitario Blanco', 'Mueble Sanitario Blanco', 1, '15400.00', '19.00', '25000.00', 'Mueble Sanitario Blanco', '2024-10-10 17:45:06', '2537', 1, 1),
(5495, 4, 'Multi Toma con Protector Para Voltaje Brickell + USB carga Rapida', 'Multi Toma con Protector Para Voltaje Brickell + USB carga Rapida', 2, '22850.00', '19.00', '31000.00', 'Multi Toma con Protector Para Voltaje Brickell + USB carga Rapida', '2024-10-10 17:45:06', '7450077012025', 1, 1),
(5496, 4, 'Multi Toma x6 Globy 2348B', 'Multi Toma x6 Globy 2348B', 4, '7175.00', '19.00', '10000.00', 'Multi Toma x6 Globy 2348B', '2024-10-10 17:45:06', '7453021108256', 1, 1),
(5497, 4, 'Multitoma 6 Salidas Titanium', 'Multitoma 6 Salidas Titanium', 0, '9800.00', '19.00', '14000.00', 'Multitoma 6 Salidas Titanium', '2024-10-10 17:45:06', '7707692864783', 1, 1),
(5498, 4, 'Naylon 60lbs-075', 'Naylon 60lbs-075', 5, '3299.00', '19.00', '4500.00', 'Naylon 60lbs-075', '2024-10-10 17:45:06', '6973877761746', 1, 1),
(5499, 4, 'Naylon Uduke 90m-16lbs-0.40', 'Naylon Uduke 90m-16lbs-0.40', 5, '1800.00', '19.00', '2500.00', 'Naylon Uduke 90m-16lbs-0.40', '2024-10-10 17:45:06', '6973877761678', 1, 1),
(5500, 4, 'Naylon Uduke 90m-35lbs-0.60mm', 'Naylon Uduke 90m-35lbs-0.60mm', 5, '2458.00', '19.00', '3000.00', 'Naylon Uduke 90m-35lbs-0.60mm', '2024-10-10 17:45:06', '6973877761715', 1, 1),
(5501, 4, 'Neumatico De 16 Para Carretilla', 'Neumatico De 16 Para Carretilla', 5, '9500.00', '19.00', '13000.00', 'Neumatico De 16 Para Carretilla', '2024-10-10 17:45:06', '7501206691076', 1, 1),
(5502, 4, 'Niple Para Ducha 30cm', 'Niple Para Ducha 30cm', 2, '6100.00', '19.00', '9000.00', 'Niple Para Ducha 30cm', '2024-10-10 17:45:06', '2532', 1, 1),
(5503, 4, 'Niple Para Ducha 40cm', 'Niple Para Ducha 40cm', 1, '7300.00', '19.00', '10500.00', 'Niple Para Ducha 40cm', '2024-10-10 17:45:06', '2531', 1, 1),
(5504, 4, 'Nivel Aluminio Boccherini', 'Nivel Aluminio Boccherini', 4, '8600.00', '19.00', '13500.00', 'Nivel Aluminio Boccherini', '2024-10-10 17:45:06', '7707180679363', 1, 1),
(5505, 4, 'Nivel Torpedo Boccherini', 'Nivel Torpedo Boccherini', 3, '3499.00', '19.00', '5500.00', 'Nivel Torpedo Boccherini', '2024-10-10 17:45:06', '7707180679356', 1, 1),
(5506, 4, 'Olla #30 Aluminios Elite', 'Olla #30 Aluminios Elite', 0, '0.00', '19.00', '35000.00', 'Olla #30 Aluminios Elite', '2024-10-10 17:45:06', '3155471', 1, 1),
(5507, 4, 'Olla #38 Aluminios Brayner', 'Olla #38 Aluminios Brayner', 1, '0.00', '19.00', '85000.00', 'Olla #38 Aluminios Brayner', '2024-10-10 17:45:06', '3155472', 1, 1),
(5508, 4, 'Olla #40x40 Aluminios Breyner', 'Olla #40x40 Aluminios Breyner', 1, '0.00', '19.00', '140000.00', 'Olla #40x40 Aluminios Breyner', '2024-10-10 17:45:06', '3155477', 1, 1),
(5509, 4, 'Olla Aluminios Joya', 'Olla Aluminios Joya', 0, '0.00', '19.00', '19000.00', 'Olla Aluminios Joya', '2024-10-10 17:45:06', '3155473', 1, 1),
(5510, 4, 'Olla Aluminios Joya', 'Olla Aluminios Joya', 0, '0.00', '19.00', '17000.00', 'Olla Aluminios Joya', '2024-10-10 17:45:06', '3155474', 1, 1);
INSERT INTO `inventario` (`id`, `user_id`, `nombre`, `descripcion`, `stock`, `precio_costo`, `impuesto`, `precio_venta`, `otro_dato`, `fecha_ingreso`, `codigo_barras`, `departamento_id`, `categoria_id`) VALUES
(5511, 4, 'Olla Aluminios Joya', 'Olla Aluminios Joya', 0, '0.00', '19.00', '15000.00', 'Olla Aluminios Joya', '2024-10-10 17:45:06', '3155475', 1, 1),
(5512, 4, 'Olla Aluminios Joya', 'Olla Aluminios Joya', 0, '0.00', '19.00', '12000.00', 'Olla Aluminios Joya', '2024-10-10 17:45:06', '3155476', 1, 1),
(5513, 4, 'Omegas PVC', 'Omegas PVC', 73, '0.00', '19.00', '3800.00', 'Omegas PVC', '2024-10-10 17:45:06', '12457', 1, 1),
(5514, 4, 'Ovalo Sencillo Tradicional', 'Ovalo Sencillo Tradicional', 15, '25800.00', '19.00', '36900.00', 'Ovalo Sencillo Tradicional', '2024-10-10 17:45:06', '500553', 1, 1),
(5515, 4, 'PEGANTE PEGACOL  EXTRA FUERTE PLUS GRIS  10KG', 'PEGANTE PEGACOL  EXTRA FUERTE PLUS GRIS  10KG', 10, '8750.00', '19.00', '10500.00', 'PEGANTE PEGACOL  EXTRA FUERTE PLUS GRIS  10KG', '2024-10-10 17:45:06', '2371', 1, 1),
(5516, 4, 'PEGANTE PEGACOL  EXTRA FUERTE PLUS GRIS  25KG INTEROR Y EXTERIOR', 'PEGANTE PEGACOL  EXTRA FUERTE PLUS GRIS  25KG INTEROR Y EXTERIOR', 12, '16450.00', '19.00', '19800.00', 'PEGANTE PEGACOL  EXTRA FUERTE PLUS GRIS  25KG INTEROR Y EXTERIOR', '2024-10-10 17:45:06', '2372', 1, 1),
(5517, 4, 'PERFIL PARA WPC BLANCO Y GRIS  2MTS', 'PERFIL PARA WPC BLANCO Y GRIS  2MTS', 12, '18000.00', '19.00', '26000.00', 'PERFIL PARA WPC BLANCO Y GRIS  2MTS', '2024-10-10 17:45:06', 'X-22122M', 1, 1),
(5518, 4, 'PISO PVC COLOR MARMOL', 'PISO PVC COLOR MARMOL', 268, '268000.00', '19.00', '268000.00', 'PISO PVC COLOR MARMOL', '2024-10-10 17:45:06', '56894743', 1, 1),
(5519, 4, 'Palanca Para Llanta 9´´ Truper', 'Palanca Para Llanta 9´´ Truper', 1, '5669.00', '19.00', '8500.00', 'Palanca Para Llanta 9´´ Truper', '2024-10-10 17:45:06', '7506240653080', 1, 1),
(5520, 4, 'Palustre De Albañil 5', 'Palustre De Albañil 5', 5, '4216.00', '19.00', '6500.00', 'Palustre De Albañil 5', '2024-10-10 17:45:06', '6973877761906', 1, 1),
(5521, 4, 'Palustre De Albañil Uduke 7', 'Palustre De Albañil Uduke 7', 4, '4538.00', '19.00', '8500.00', 'Palustre De Albañil Uduke 7', '2024-10-10 17:45:06', '6973877761920', 1, 1),
(5522, 4, 'Panel Led 12w Sobreponer (sirius)', 'Panel Led 12w Sobreponer (sirius)', 14, '9000.00', '19.00', '13500.00', 'Panel Led 12w Sobreponer (sirius)', '2024-10-10 17:45:06', '64', 1, 1),
(5523, 4, 'Panel Led 18w Cuadrado Incrustar HT80369', 'Panel Led 18w Cuadrado Incrustar HT80369', 4, '7050.00', '19.00', '10000.00', 'Panel Led 18w Cuadrado Incrustar HT80369', '2024-10-10 17:45:06', '6973653175378', 1, 1),
(5524, 4, 'Panel Led 18w Cuadrado Incrustar Sin Marco Uduke', 'Panel Led 18w Cuadrado Incrustar Sin Marco Uduke', 4, '9500.00', '19.00', '13300.00', 'Panel Led 18w Cuadrado Incrustar Sin Marco Uduke', '2024-10-10 17:45:06', '6973877764730', 1, 1),
(5525, 4, 'Panel Led 18w Cuadrado Sobreponer Uduke', 'Panel Led 18w Cuadrado Sobreponer Uduke', 6, '8250.00', '19.00', '11600.00', 'Panel Led 18w Cuadrado Sobreponer Uduke', '2024-10-10 17:45:06', '6973653174784', 1, 1),
(5526, 4, 'Panel Led 18w Incrustar (maxxiinght)', 'Panel Led 18w Incrustar (maxxiinght)', 0, '9000.00', '19.00', '16000.00', 'Panel Led 18w Incrustar (maxxiinght)', '2024-10-10 17:45:06', '4630017729180', 1, 1),
(5527, 4, 'Panel Led 18w Incrustar Redodndo Luz Clalida Uduke HT80368', 'Panel Led 18w Incrustar Redodndo Luz Clalida Uduke HT80368', 12, '7050.00', '19.00', '10500.00', 'Panel Led 18w Incrustar Redodndo Luz Clalida Uduke HT80368', '2024-10-10 17:45:06', '6973653175361', 1, 1),
(5528, 4, 'Panel Led 18w Redondo Incrustar Luz Dia Uduke HT80367', 'Panel Led 18w Redondo Incrustar Luz Dia Uduke HT80367', 10, '6000.00', '19.00', '8800.00', 'Panel Led 18w Redondo Incrustar Luz Dia Uduke HT80367', '2024-10-10 17:45:06', '6973653175347', 1, 1),
(5529, 4, 'Panel Led 18w Redondo Sobreponer Nippon Led', 'Panel Led 18w Redondo Sobreponer Nippon Led', 18, '13000.00', '19.00', '17000.00', 'Panel Led 18w Redondo Sobreponer Nippon Led', '2024-10-10 17:45:06', 'L18W', 1, 1),
(5530, 4, 'Panel Led 18w Sobreponer (sirius)', 'Panel Led 18w Sobreponer (sirius)', 0, '10000.00', '19.00', '18000.00', 'Panel Led 18w Sobreponer (sirius)', '2024-10-10 17:45:06', '66', 1, 1),
(5531, 4, 'Panel Led 24w Cuadrado Incrustar Luz Dia Uduke', 'Panel Led 24w Cuadrado Incrustar Luz Dia Uduke', 6, '11100.00', '19.00', '15600.00', 'Panel Led 24w Cuadrado Incrustar Luz Dia Uduke', '2024-10-10 17:45:06', '6973653174777', 1, 1),
(5532, 4, 'Panel Led 24w Incrustar Cuadrado Uduke', 'Panel Led 24w Incrustar Cuadrado Uduke', 3, '13500.00', '19.00', '18500.00', 'Panel Led 24w Incrustar Cuadrado Uduke', '2024-10-10 17:45:06', '6973877764747', 1, 1),
(5533, 4, 'Panel Led 24w Vharbor HT80513', 'Panel Led 24w Vharbor HT80513', 10, '10350.00', '19.00', '15000.00', 'Panel Led 24w Vharbor HT80513', '2024-10-10 17:45:06', '6973877764631', 1, 1),
(5534, 4, 'Panel Led 60 X 60 Bester', 'Panel Led 60 X 60 Bester', 4, '55000.00', '19.00', '80000.00', 'Panel Led 60 X 60 Bester', '2024-10-10 17:45:06', '115', 1, 1),
(5535, 4, 'Panel Led 6w sobreponer (uran Energy)', 'Panel Led 6w sobreponer (uran Energy)', 18, '8000.00', '19.00', '11000.00', 'Panel Led 6w sobreponer (uran Energy)', '2024-10-10 17:45:06', '68', 1, 1),
(5536, 4, 'Panel Led 9w Incrustar (uranenergy)', 'Panel Led 9w Incrustar (uranenergy)', 17, '7000.00', '19.00', '12500.00', 'Panel Led 9w Incrustar (uranenergy)', '2024-10-10 17:45:06', '67', 1, 1),
(5537, 4, 'Panel Led 9w Redondo Incrustar Sin Marco Uduke', 'Panel Led 9w Redondo Incrustar Sin Marco Uduke', 6, '4950.00', '19.00', '6900.00', 'Panel Led 9w Redondo Incrustar Sin Marco Uduke', '2024-10-10 17:45:06', '6973877764686', 1, 1),
(5538, 4, 'Panel Led Cuadrado 36w', 'Panel Led Cuadrado 36w', 7, '14200.00', '19.00', '19000.00', 'Panel Led Cuadrado 36w', '2024-10-10 17:45:06', '6973877764754', 1, 1),
(5539, 4, 'Panel Led Cuadrado 9w Incrustar Sin Marco Uduke', 'Panel Led Cuadrado 9w Incrustar Sin Marco Uduke', 4, '8900.00', '19.00', '11600.00', 'Panel Led Cuadrado 9w Incrustar Sin Marco Uduke', '2024-10-10 17:45:06', '6973877764723', 1, 1),
(5540, 4, 'Panel Led Frameless 36w Incrustar Luz Infinita Vatio', 'Panel Led Frameless 36w Incrustar Luz Infinita Vatio', 5, '15000.00', '19.00', '19000.00', 'Panel Led Frameless 36w Incrustar Luz Infinita Vatio', '2024-10-10 17:45:06', '24073', 1, 1),
(5541, 4, 'Panel Led Incrustar 18w Luz Blanca Vatio', 'Panel Led Incrustar 18w Luz Blanca Vatio', 6, '7709.00', '19.00', '13500.00', 'Panel Led Incrustar 18w Luz Blanca Vatio', '2024-10-10 17:45:06', '7707499405509', 1, 1),
(5542, 4, 'Panel Led Redondo 36w Incr', 'Panel Led Redondo 36w Incr', 2, '8450.00', '19.00', '17500.00', 'Panel Led Redondo 36w Incr', '2024-10-10 17:45:06', '6973877764716', 1, 1),
(5543, 4, 'Panel Led Sobreponer 18w Vatio', 'Panel Led Sobreponer 18w Vatio', 3, '9670.00', '19.00', '14200.00', 'Panel Led Sobreponer 18w Vatio', '2024-10-10 17:45:06', '7707499401457', 1, 1),
(5544, 4, 'Panel Led12w Incrustar (led´s Store)', 'Panel Led12w Incrustar (led´s Store)', 7, '8000.00', '19.00', '13000.00', 'Panel Led12w Incrustar (led´s Store)', '2024-10-10 17:45:06', '63', 1, 1),
(5545, 4, 'Panel Solar 100w Superled', 'Panel Solar 100w Superled', 2, '168000.00', '19.00', '220000.00', 'Panel Solar 100w Superled', '2024-10-10 17:45:06', '1234561223', 1, 1),
(5546, 4, 'Panel Solar 200w Superled', 'Panel Solar 200w Superled', 0, '198000.00', '19.00', '260000.00', 'Panel Solar 200w Superled', '2024-10-10 17:45:06', '9632528741', 1, 1),
(5547, 4, 'Papelera Cardenas Hidrogriferias', 'Papelera Cardenas Hidrogriferias', 5, '5400.00', '19.00', '8000.00', 'Papelera Cardenas Hidrogriferias', '2024-10-10 17:45:06', '2538', 1, 1),
(5548, 4, 'Par Tapones Lavamanos Boccherini', 'Par Tapones Lavamanos Boccherini', 2, '20500.00', '19.00', '30000.00', 'Par Tapones Lavamanos Boccherini', '2024-10-10 17:45:06', '7707180672029', 1, 1),
(5549, 4, 'Pasador Cobre Induma 2', 'Pasador Cobre Induma 2', 5, '2050.00', '19.00', '3000.00', 'Pasador Cobre Induma 2', '2024-10-10 17:45:06', '7702587741064', 1, 1),
(5550, 4, 'Pasador Cobre Induma 3', 'Pasador Cobre Induma 3', 6, '2900.00', '19.00', '3800.00', 'Pasador Cobre Induma 3', '2024-10-10 17:45:06', '7702587503105', 1, 1),
(5551, 4, 'Pasador Cobre Induma 4', 'Pasador Cobre Induma 4', 3, '3699.00', '19.00', '4800.00', 'Pasador Cobre Induma 4', '2024-10-10 17:45:06', '7702587220996', 1, 1),
(5552, 4, 'Pasta Para Soldar La Unica', 'Pasta Para Soldar La Unica', 4, '1734.00', '19.00', '2500.00', 'Pasta Para Soldar La Unica', '2024-10-10 17:45:06', '7704987003018', 1, 1),
(5553, 4, 'Pega Perfecto  X 25Kl', 'Pega Perfecto  X 25Kl', 0, '15000.00', '19.00', '19000.00', 'Pega Perfecto  X 25Kl', '2024-10-10 17:45:06', '7707343041679', 1, 1),
(5554, 4, 'Pega Todo Epoxy', 'Pega Todo Epoxy', 0, '9000.00', '19.00', '18000.00', 'Pega Todo Epoxy', '2024-10-10 17:45:06', '4987052200571', 1, 1),
(5555, 4, 'Pegacor Ceramico Gris X 25kg', 'Pegacor Ceramico Gris X 25kg', 0, '30214.00', '19.00', '37800.00', 'Pegacor Ceramico Gris X 25kg', '2024-10-10 17:45:06', '7707181799510', 1, 1),
(5556, 4, 'Pegadit Super Aghesivo Afix 5g', 'Pegadit Super Aghesivo Afix 5g', 6, '3250.00', '19.00', '4800.00', 'Pegadit Super Aghesivo Afix 5g', '2024-10-10 17:45:06', '7702505503835', 1, 1),
(5557, 4, 'Pegamix Pega Perfecto X2kg', 'Pegamix Pega Perfecto X2kg', 10, '13499.00', '19.00', '18000.00', 'Pegamix Pega Perfecto X2kg', '2024-10-10 17:45:06', '28', 1, 1),
(5558, 4, 'Pegante Astro Glue 728AS', 'Pegante Astro Glue 728AS', 11, '872.00', '19.00', '1500.00', 'Pegante Astro Glue 728AS', '2024-10-10 17:45:06', '7708827478066', 1, 1),
(5559, 4, 'Pegante PVC 1/32 Fina MC Soldamas', 'Pegante PVC 1/32 Fina MC Soldamas', 14, '4650.00', '19.00', '6500.00', 'Pegante PVC 1/32 Fina MC Soldamas', '2024-10-10 17:45:06', '7707214130280', 1, 1),
(5560, 4, 'Pegante Porcelanato Lates Gris X 25kg Esplacol', 'Pegante Porcelanato Lates Gris X 25kg Esplacol', 12, '25300.00', '19.00', '34500.00', 'Pegante Porcelanato Lates Gris X 25kg Esplacol', '2024-10-10 17:45:06', '4930', 1, 1),
(5561, 4, 'Pegante Super Glue - Gota Magica', 'Pegante Super Glue - Gota Magica', 11, '1167.00', '19.00', '2000.00', 'Pegante Super Glue - Gota Magica', '2024-10-10 17:45:06', '7372149118384', 1, 1),
(5562, 4, 'Perfil Aluminio 2mts Blanco', 'Perfil Aluminio 2mts Blanco', 6, '18000.00', '19.00', '26500.00', 'Perfil Aluminio 2mts Blanco', '2024-10-10 17:45:06', 'PER1', 1, 1),
(5563, 4, 'Perfil Aluminio 2mts Plateado', 'Perfil Aluminio 2mts Plateado', 6, '18000.00', '19.00', '26500.00', 'Perfil Aluminio 2mts Plateado', '2024-10-10 17:45:06', 'PER2', 1, 1),
(5564, 4, 'Perilla Prisma Universal', 'Perilla Prisma Universal', 8, '3100.00', '19.00', '5000.00', 'Perilla Prisma Universal', '2024-10-10 17:45:06', '2529', 1, 1),
(5565, 4, 'Perimetral En F PVC', 'Perimetral En F PVC', 84, '8000.00', '19.00', '10500.00', 'Perimetral En F PVC', '2024-10-10 17:45:06', '100004', 1, 1),
(5566, 4, 'Perimetral O Union En H PVC', 'Perimetral O Union En H PVC', 30, '8000.00', '19.00', '10500.00', 'Perimetral O Union En H PVC', '2024-10-10 17:45:06', '1000005', 1, 1),
(5567, 4, 'Perimetral O Unión En H Decorado', 'Perimetral O Unión En H Decorado', 15, '7500.00', '19.00', '10500.00', 'Perimetral O Unión En H Decorado', '2024-10-10 17:45:06', 'H7', 1, 1),
(5568, 4, 'Perimetral Pechi Palomo PVC Blanco', 'Perimetral Pechi Palomo PVC Blanco', 14, '7500.00', '19.00', '10500.00', 'Perimetral Pechi Palomo PVC Blanco', '2024-10-10 17:45:06', '1245789', 1, 1),
(5569, 4, 'Perimetral Pechi Palomo PVC Linea Plateado', 'Perimetral Pechi Palomo PVC Linea Plateado', 20, '7500.00', '19.00', '10500.00', 'Perimetral Pechi Palomo PVC Linea Plateado', '2024-10-10 17:45:06', 'H6', 1, 1),
(5570, 4, 'Piedra Para Afilar Boccherini', 'Piedra Para Afilar Boccherini', 5, '3799.00', '19.00', '5500.00', 'Piedra Para Afilar Boccherini', '2024-10-10 17:45:06', '7707180679318', 1, 1),
(5571, 4, 'Pintafacil Colorvida Amarillo Otoñal Galon T2', 'Pintafacil Colorvida Amarillo Otoñal Galon T2', 4, '33490.00', '19.00', '43500.00', 'Pintafacil Colorvida Amarillo Otoñal Galon T2', '2024-10-10 17:45:06', '26', 1, 1),
(5572, 4, 'Pintafacil Colorvida Azul Costeño Galon T2', 'Pintafacil Colorvida Azul Costeño Galon T2', 1, '33490.00', '19.00', '43500.00', 'Pintafacil Colorvida Azul Costeño Galon T2', '2024-10-10 17:45:06', '19', 1, 1),
(5573, 4, 'Pintafacil Colorvida Azul Porcelana Galon T2', 'Pintafacil Colorvida Azul Porcelana Galon T2', 3, '33490.00', '19.00', '45000.00', 'Pintafacil Colorvida Azul Porcelana Galon T2', '2024-10-10 17:45:06', '20', 1, 1),
(5574, 4, 'Pintafacil Colorvida Crema Galon T2', 'Pintafacil Colorvida Crema Galon T2', 4, '33490.00', '19.00', '43500.00', 'Pintafacil Colorvida Crema Galon T2', '2024-10-10 17:45:06', '25', 1, 1),
(5575, 4, 'Pintafacil Colorvida Gris Nube Galon T2', 'Pintafacil Colorvida Gris Nube Galon T2', 0, '33490.00', '19.00', '43500.00', 'Pintafacil Colorvida Gris Nube Galon T2', '2024-10-10 17:45:06', '23', 1, 1),
(5576, 4, 'Pintafacil Colorvida Lila Galon T2', 'Pintafacil Colorvida Lila Galon T2', 4, '33490.00', '19.00', '43500.00', 'Pintafacil Colorvida Lila Galon T2', '2024-10-10 17:45:06', '24', 1, 1),
(5577, 4, 'Pintafacil Colorvida Palo De Rosa Galon T2', 'Pintafacil Colorvida Palo De Rosa Galon T2', 4, '33490.00', '19.00', '43500.00', 'Pintafacil Colorvida Palo De Rosa Galon T2', '2024-10-10 17:45:06', '22', 1, 1),
(5578, 4, 'Pintafacil Colorvida Trigo Galon T2', 'Pintafacil Colorvida Trigo Galon T2', 4, '33490.00', '19.00', '43500.00', 'Pintafacil Colorvida Trigo Galon T2', '2024-10-10 17:45:06', '21', 1, 1),
(5579, 4, 'Pintura Fondeo Corona', 'Pintura Fondeo Corona', 4, '23040.00', '19.00', '35000.00', 'Pintura Fondeo Corona', '2024-10-10 17:45:06', '7705389010574', 1, 1),
(5580, 4, 'Pintura Super Plus Tipo 1 Blanco Galon', 'Pintura Super Plus Tipo 1 Blanco Galon', 3, '48472.00', '19.00', '63000.00', 'Pintura Super Plus Tipo 1 Blanco Galon', '2024-10-10 17:45:06', '7705389006485', 1, 1),
(5581, 4, 'Pintura Total Blanca T2 Corona', 'Pintura Total Blanca T2 Corona', 0, '38239.00', '19.00', '50500.00', 'Pintura Total Blanca T2 Corona', '2024-10-10 17:45:06', '7705389010741', 1, 1),
(5582, 4, 'Pinza Corte 6 Truper 17312', 'Pinza Corte 6 Truper 17312', 0, '15000.00', '19.00', '19500.00', 'Pinza Corte 6 Truper 17312', '2024-10-10 17:45:06', '7501206641286', 1, 1),
(5583, 4, 'Pinza De Corta Frio 7´´ Pretul', 'Pinza De Corta Frio 7´´ Pretul', 2, '10570.00', '19.00', '15500.00', 'Pinza De Corta Frio 7´´ Pretul', '2024-10-10 17:45:06', '7501206678138', 1, 1),
(5584, 4, 'Pinza De Corte 6 Truper 17331', 'Pinza De Corte 6 Truper 17331', 2, '22000.00', '19.00', '27500.00', 'Pinza De Corte 6 Truper 17331', '2024-10-10 17:45:06', '7501206619001', 1, 1),
(5585, 4, 'Pinza De Corte Diagonal 7 Pretul', 'Pinza De Corte Diagonal 7 Pretul', 2, '13000.00', '19.00', '16500.00', 'Pinza De Corte Diagonal 7 Pretul', '2024-10-10 17:45:06', '7506240643043', 1, 1),
(5586, 4, 'Pinza De Extension 10 Boccherini', 'Pinza De Extension 10 Boccherini', 1, '18399.00', '19.00', '26500.00', 'Pinza De Extension 10 Boccherini', '2024-10-10 17:45:06', '7707766451703', 1, 1),
(5587, 4, 'Pinza De Presion Hojalatera Boccherini', 'Pinza De Presion Hojalatera Boccherini', 1, '26400.00', '19.00', '37800.00', 'Pinza De Presion Hojalatera Boccherini', '2024-10-10 17:45:06', '7707766454148', 1, 1),
(5588, 4, 'Pinza De Presion Mordaza Curva', 'Pinza De Presion Mordaza Curva', 2, '13099.00', '19.00', '19000.00', 'Pinza De Presion Mordaza Curva', '2024-10-10 17:45:06', '7707180678632', 1, 1),
(5589, 4, 'Pinza Pela Cable 7´´ Truper', 'Pinza Pela Cable 7´´ Truper', 2, '23062.00', '19.00', '33000.00', 'Pinza Pela Cable 7´´ Truper', '2024-10-10 17:45:06', '7506240614388', 1, 1),
(5590, 4, 'Pinza Punta Y Corta 8 Boccherini', 'Pinza Punta Y Corta 8 Boccherini', 2, '9799.00', '19.00', '14000.00', 'Pinza Punta Y Corta 8 Boccherini', '2024-10-10 17:45:06', '7707180678601', 1, 1),
(5591, 4, 'Pinza Punta Y Corte 6 Boccherini', 'Pinza Punta Y Corte 6 Boccherini', 2, '7900.00', '19.00', '11800.00', 'Pinza Punta Y Corte 6 Boccherini', '2024-10-10 17:45:06', '7707180678595', 1, 1),
(5592, 4, 'Pistola Calafateo Con Cremallera Pretul', 'Pistola Calafateo Con Cremallera Pretul', 4, '9811.00', '19.00', '13000.00', 'Pistola Calafateo Con Cremallera Pretul', '2024-10-10 17:45:06', '7501206635131', 1, 1),
(5593, 4, 'Pistola Calafateo Lisa', 'Pistola Calafateo Lisa', 2, '9811.00', '19.00', '12800.00', 'Pistola Calafateo Lisa', '2024-10-10 17:45:06', '7501206635124', 1, 1),
(5594, 4, 'Pistola Calefactora Boccherini', 'Pistola Calefactora Boccherini', 0, '9207.00', '19.00', '12500.00', 'Pistola Calefactora Boccherini', '2024-10-10 17:45:06', '7707180679127', 1, 1),
(5595, 4, 'Pistola Electrica Para Pintar 800ml 400w Total', 'Pistola Electrica Para Pintar 800ml 400w Total', 0, '103050.00', '19.00', '135000.00', 'Pistola Electrica Para Pintar 800ml 400w Total', '2024-10-10 17:45:06', '6925582194937', 1, 1),
(5596, 4, 'Pistola Metalica Para Riego Boccherini', 'Pistola Metalica Para Riego Boccherini', 4, '3399.00', '19.00', '5000.00', 'Pistola Metalica Para Riego Boccherini', '2024-10-10 17:45:06', '7707180679288', 1, 1),
(5597, 4, 'Pistola Para Riego 5 Funciones Plastica', 'Pistola Para Riego 5 Funciones Plastica', 5, '6574.00', '19.00', '8600.00', 'Pistola Para Riego 5 Funciones Plastica', '2024-10-10 17:45:06', '7501206653845', 1, 1),
(5598, 4, 'Pistola Para Silicona Uduke', 'Pistola Para Silicona Uduke', 1, '7539.00', '19.00', '10500.00', 'Pistola Para Silicona Uduke', '2024-10-10 17:45:06', '6973653177273', 1, 1),
(5599, 4, 'Pistola Pintar Electrica Pm-11 Uduke', 'Pistola Pintar Electrica Pm-11 Uduke', 1, '85700.00', '19.00', '116000.00', 'Pistola Pintar Electrica Pm-11 Uduke', '2024-10-10 17:45:06', '6973877768196', 1, 1),
(5600, 4, 'Pistola Plastica 5 Funciones', 'Pistola Plastica 5 Funciones', 0, '4299.00', '19.00', '6500.00', 'Pistola Plastica 5 Funciones', '2024-10-10 17:45:06', '7707180679271', 1, 1),
(5601, 4, 'Pistola Plastica Riego Boccherini', 'Pistola Plastica Riego Boccherini', 2, '3999.00', '19.00', '5900.00', 'Pistola Plastica Riego Boccherini', '2024-10-10 17:45:06', '7707180679264', 1, 1),
(5602, 4, 'Plafon En Porcelana Corona', 'Plafon En Porcelana Corona', 36, '1923.00', '19.00', '3000.00', 'Plafon En Porcelana Corona', '2024-10-10 17:45:06', '73', 1, 1),
(5603, 4, 'Plafon Plastico Fino Blanco', 'Plafon Plastico Fino Blanco', 2, '1200.00', '19.00', '1800.00', 'Plafon Plastico Fino Blanco', '2024-10-10 17:45:06', '16973653173401', 1, 1),
(5604, 4, 'Plafon Policarbonato', 'Plafon Policarbonato', 0, '2150.00', '19.00', '3000.00', 'Plafon Policarbonato', '2024-10-10 17:45:06', '7707489072902', 1, 1),
(5605, 4, 'Plafon S720', 'Plafon S720', 2, '55000.00', '19.00', '95000.00', 'Plafon S720', '2024-10-10 17:45:06', 'P1', 1, 1),
(5606, 4, 'Platon Para Carretilla Pretul Rojo', 'Platon Para Carretilla Pretul Rojo', 1, '75000.00', '19.00', '95000.00', 'Platon Para Carretilla Pretul Rojo', '2024-10-10 17:45:06', '7506240665212', 1, 1),
(5607, 4, 'Poliuretano 100% Corona Blanco,Negro Y Gris', 'Poliuretano 100% Corona Blanco,Negro Y Gris', 51, '26462.00', '19.00', '39000.00', 'Poliuretano 100% Corona Blanco,Negro Y Gris', '2024-10-10 17:45:06', '7705389006706', 1, 1),
(5608, 4, 'Porta Electrodo 500amp Twah5006', 'Porta Electrodo 500amp Twah5006', 2, '22600.00', '19.00', '29800.00', 'Porta Electrodo 500amp Twah5006', '2024-10-10 17:45:06', '6925582185638', 1, 1),
(5609, 4, 'Porta Vaso En Aluminio', 'Porta Vaso En Aluminio', 3, '21749.00', '19.00', '35000.00', 'Porta Vaso En Aluminio', '2024-10-10 17:45:06', '7707180675075', 1, 1),
(5610, 4, 'Probador Corriente Alterna Boccherini', 'Probador Corriente Alterna Boccherini', 4, '1700.00', '19.00', '5000.00', 'Probador Corriente Alterna Boccherini', '2024-10-10 17:45:06', '7707180678892', 1, 1),
(5611, 4, 'Probador Corriente Tipo Mecanico Boccherini', 'Probador Corriente Tipo Mecanico Boccherini', 2, '7140.00', '19.00', '11000.00', 'Probador Corriente Tipo Mecanico Boccherini', '2024-10-10 17:45:06', '7707180678908', 1, 1),
(5612, 4, 'Professional Blanca T1 Corona', 'Professional Blanca T1 Corona', 12, '45032.00', '19.00', '65000.00', 'Professional Blanca T1 Corona', '2024-10-10 17:45:06', '7705389011403', 1, 1),
(5613, 4, 'Protector De Toma Corriente', 'Protector De Toma Corriente', 42, '250.00', '19.00', '400.00', 'Protector De Toma Corriente', '2024-10-10 17:45:06', '5048', 1, 1),
(5614, 4, 'Pulidora 4x1/2 710W Industrial + Mango Auxiliar Total', 'Pulidora 4x1/2 710W Industrial + Mango Auxiliar Total', 1, '111200.00', '19.00', '133500.00', 'Pulidora 4x1/2 710W Industrial + Mango Auxiliar Total', '2024-10-10 17:45:06', '6976057332460', 1, 1),
(5615, 4, 'Pulidora Angular Kalley Pa950', 'Pulidora Angular Kalley Pa950', 1, '107000.00', '19.00', '138000.00', 'Pulidora Angular Kalley Pa950', '2024-10-10 17:45:06', '7705946478281', 1, 1),
(5616, 4, 'Punta Para Taladro', 'Punta Para Taladro', 85, '1500.00', '19.00', '3000.00', 'Punta Para Taladro', '2024-10-10 17:45:06', '5482461', 1, 1),
(5617, 4, 'Punta Taladro Stanley S2PH2 8328', 'Punta Taladro Stanley S2PH2 8328', 30, '1600.00', '19.00', '2500.00', 'Punta Taladro Stanley S2PH2 8328', '2024-10-10 17:45:06', 'LS1', 1, 1),
(5618, 4, 'Puntas De Lanza Aluminio', 'Puntas De Lanza Aluminio', 20, '2050.00', '19.00', '2500.00', 'Puntas De Lanza Aluminio', '2024-10-10 17:45:06', '50289', 1, 1),
(5619, 4, 'Puntilla Puma 2 1/2 X 500 Gr', 'Puntilla Puma 2 1/2 X 500 Gr', 12, '4163.00', '19.00', '5000.00', 'Puntilla Puma 2 1/2 X 500 Gr', '2024-10-10 17:45:06', '7707234800095', 1, 1),
(5620, 4, 'Puntilla Puma 2 X 500 Gr', 'Puntilla Puma 2 X 500 Gr', 24, '4163.00', '19.00', '5000.00', 'Puntilla Puma 2 X 500 Gr', '2024-10-10 17:45:06', '860004', 1, 1),
(5621, 4, 'REGISTRO DUCHA AMAZONAS BPCCHERINI BCH 130', 'REGISTRO DUCHA AMAZONAS BPCCHERINI BCH 130', 1, '31300.00', '19.00', '43500.00', 'REGISTRO DUCHA AMAZONAS BPCCHERINI BCH 130', '2024-10-10 17:45:06', '7707766457101', 1, 1),
(5622, 4, 'REGISTRO DUCHA NUQUI BOCCHERINI BCH 116', 'REGISTRO DUCHA NUQUI BOCCHERINI BCH 116', 1, '31300.00', '19.00', '43500.00', 'REGISTRO DUCHA NUQUI BOCCHERINI BCH 116', '2024-10-10 17:45:06', '7707766456685', 1, 1),
(5623, 4, 'REGISTRO DUCHA PALANCA BOCCHERINI', 'REGISTRO DUCHA PALANCA BOCCHERINI', 1, '31300.00', '19.00', '43500.00', 'REGISTRO DUCHA PALANCA BOCCHERINI', '2024-10-10 17:45:06', '7707766450263', 1, 1),
(5624, 4, 'RODILLO TRUPER PROFESIONAL 9 PULGADAS', 'RODILLO TRUPER PROFESIONAL 9 PULGADAS', 6, '6800.00', '19.00', '9500.00', 'RODILLO TRUPER PROFESIONAL 9 PULGADAS', '2024-10-10 17:45:06', '7506240645221', 1, 1),
(5625, 4, 'Racor Manguera Bronce 1/2 Sin Oreja', 'Racor Manguera Bronce 1/2 Sin Oreja', 4, '4350.00', '19.00', '6000.00', 'Racor Manguera Bronce 1/2 Sin Oreja', '2024-10-10 17:45:06', '6973877764181', 1, 1),
(5626, 4, 'Racor Plastico Fino Manguera 1/2 Rojo', 'Racor Plastico Fino Manguera 1/2 Rojo', 32, '450.00', '19.00', '1000.00', 'Racor Plastico Fino Manguera 1/2 Rojo', '2024-10-10 17:45:06', '6', 1, 1),
(5627, 4, 'Raqueta Mata Mosquitos Recargable Doble Linterna', 'Raqueta Mata Mosquitos Recargable Doble Linterna', 1, '14400.00', '19.00', '20000.00', 'Raqueta Mata Mosquitos Recargable Doble Linterna', '2024-10-10 17:45:06', 'RT1', 1, 1),
(5628, 4, 'Ratchet De Copas Cuadrante 1/2', 'Ratchet De Copas Cuadrante 1/2', 1, '11543.00', '19.00', '17000.00', 'Ratchet De Copas Cuadrante 1/2', '2024-10-10 17:45:06', '7707180679349', 1, 1),
(5629, 4, 'Rayador De Aluminio Truper', 'Rayador De Aluminio Truper', 2, '15172.00', '19.00', '18500.00', 'Rayador De Aluminio Truper', '2024-10-10 17:45:06', '7501206634776', 1, 1),
(5630, 4, 'Reflector Led Luz RGB 50w + Control Mercury', 'Reflector Led Luz RGB 50w + Control Mercury', 3, '49000.00', '19.00', '75000.00', 'Reflector Led Luz RGB 50w + Control Mercury', '2024-10-10 17:45:06', '7707692863762', 1, 1),
(5631, 4, 'Reflector Led Solar 100w Enerlux', 'Reflector Led Solar 100w Enerlux', 0, '0.00', '19.00', '220000.00', 'Reflector Led Solar 100w Enerlux', '2024-10-10 17:45:06', 'E1', 1, 1),
(5632, 4, 'Reflector Led Solar 150w Karluz', 'Reflector Led Solar 150w Karluz', 0, '0.00', '19.00', '260000.00', 'Reflector Led Solar 150w Karluz', '2024-10-10 17:45:06', '7701KL2836', 1, 1),
(5633, 4, 'Reflector Led Solar 200w Enerlux', 'Reflector Led Solar 200w Enerlux', 0, '0.00', '19.00', '290000.00', 'Reflector Led Solar 200w Enerlux', '2024-10-10 17:45:06', 'E2', 1, 1),
(5634, 4, 'Reflector Led T 100w 6500k', 'Reflector Led T 100w 6500k', 1, '34104.00', '19.00', '45000.00', 'Reflector Led T 100w 6500k', '2024-10-10 17:45:06', '7707692862826', 1, 1),
(5635, 4, 'Reflector Led T 150w 6500k', 'Reflector Led T 150w 6500k', 1, '54992.00', '19.00', '71500.00', 'Reflector Led T 150w 6500k', '2024-10-10 17:45:06', '7707692867500', 1, 1),
(5636, 4, 'Reflector Led T 50w 6500k', 'Reflector Led T 50w 6500k', 2, '18176.00', '19.00', '24000.00', 'Reflector Led T 50w 6500k', '2024-10-10 17:45:06', '7707692867326', 1, 1),
(5637, 4, 'Reflector Led T200w 6500k', 'Reflector Led T200w 6500k', 0, '68242.00', '19.00', '89000.00', 'Reflector Led T200w 6500k', '2024-10-10 17:45:06', '7707692862949', 1, 1),
(5638, 4, 'Reflector Led T30w 6500k', 'Reflector Led T30w 6500k', 3, '13619.00', '19.00', '18000.00', 'Reflector Led T30w 6500k', '2024-10-10 17:45:06', '7707692869467', 1, 1),
(5639, 4, 'Regadera + Registro Grival', 'Regadera + Registro Grival', 1, '36170.00', '19.00', '50000.00', 'Regadera + Registro Grival', '2024-10-10 17:45:06', '50', 1, 1),
(5640, 4, 'Regadera 4 Acero Inoxidable Boccherini', 'Regadera 4 Acero Inoxidable Boccherini', 0, '10500.00', '19.00', '15000.00', 'Regadera 4 Acero Inoxidable Boccherini', '2024-10-10 17:45:06', 'REF23', 1, 1),
(5641, 4, 'Regadera 5 Funciones Boccherini', 'Regadera 5 Funciones Boccherini', 5, '20000.00', '19.00', '29000.00', 'Regadera 5 Funciones Boccherini', '2024-10-10 17:45:06', '7707180672234', 1, 1),
(5642, 4, 'Regadera Abs 6cromada Boccherini', 'Regadera Abs 6cromada Boccherini', 0, '41000.00', '19.00', '59000.00', 'Regadera Abs 6cromada Boccherini', '2024-10-10 17:45:06', '7707180671176', 1, 1),
(5643, 4, 'Regadera Abs 8 Boccherini Cuadrada', 'Regadera Abs 8 Boccherini Cuadrada', 2, '52199.00', '19.00', '75000.00', 'Regadera Abs 8 Boccherini Cuadrada', '2024-10-10 17:45:06', '7707180675860', 1, 1),
(5644, 4, 'Regadera Abs Rectangular 6 726c', 'Regadera Abs Rectangular 6 726c', 1, '43499.00', '19.00', '55000.00', 'Regadera Abs Rectangular 6 726c', '2024-10-10 17:45:06', '7707180671152', 1, 1),
(5645, 4, 'Regadera Acero Inoxidable 8 Boccherini Yw128', 'Regadera Acero Inoxidable 8 Boccherini Yw128', 1, '50883.00', '19.00', '76500.00', 'Regadera Acero Inoxidable 8 Boccherini Yw128', '2024-10-10 17:45:06', '7707180678014', 1, 1),
(5646, 4, 'Regadera Cromada 6 Boccherini D042', 'Regadera Cromada 6 Boccherini D042', 2, '48899.00', '19.00', '70000.00', 'Regadera Cromada 6 Boccherini D042', '2024-10-10 17:45:06', '7707180672289', 1, 1),
(5647, 4, 'Regadera Negra Rectangular Boccherini', 'Regadera Negra Rectangular Boccherini', 2, '55500.00', '19.00', '71000.00', 'Regadera Negra Rectangular Boccherini', '2024-10-10 17:45:06', '7707180676928', 1, 1),
(5648, 4, 'Regadera Plastica Cromada Fermetal R 003-1', 'Regadera Plastica Cromada Fermetal R 003-1', 1, '11221.00', '19.00', '16500.00', 'Regadera Plastica Cromada Fermetal R 003-1', '2024-10-10 17:45:06', '7592032505804', 1, 1),
(5649, 4, 'Registro De Salida Boccherini', 'Registro De Salida Boccherini', 5, '11900.00', '19.00', '17000.00', 'Registro De Salida Boccherini', '2024-10-10 17:45:06', '7707180679585', 1, 1),
(5650, 4, 'Registro Ducha 1/2 Unifer', 'Registro Ducha 1/2 Unifer', 1, '40889.00', '19.00', '52000.00', 'Registro Ducha 1/2 Unifer', '2024-10-10 17:45:06', '12', 1, 1),
(5651, 4, 'Registro Ducha Cierre Rapido Uduke', 'Registro Ducha Cierre Rapido Uduke', 6, '18954.00', '19.00', '27000.00', 'Registro Ducha Cierre Rapido Uduke', '2024-10-10 17:45:06', '6973877763009', 1, 1),
(5652, 4, 'Registro Ducha De 1/2 Boccherini', 'Registro Ducha De 1/2 Boccherini', 1, '28300.00', '19.00', '40500.00', 'Registro Ducha De 1/2 Boccherini', '2024-10-10 17:45:06', '7707180676683', 1, 1),
(5653, 4, 'Registro Ducha Grival Triceta Aluvia (AV4083) Grival Corona', 'Registro Ducha Grival Triceta Aluvia (AV4083) Grival Corona', 1, '51950.00', '19.00', '64400.00', 'Registro Ducha Grival Triceta Aluvia (AV4083) Grival Corona', '2024-10-10 17:45:06', 'RG1', 1, 1),
(5654, 4, 'Registro Ducha Palanca Aluvia Linea Grival', 'Registro Ducha Palanca Aluvia Linea Grival', 1, '53550.00', '19.00', '69600.00', 'Registro Ducha Palanca Aluvia Linea Grival', '2024-10-10 17:45:06', 'AV4073331', 1, 1),
(5655, 4, 'Registro Ducha Triceta Alivia Linea Grival', 'Registro Ducha Triceta Alivia Linea Grival', 1, '51950.00', '19.00', '67600.00', 'Registro Ducha Triceta Alivia Linea Grival', '2024-10-10 17:45:06', 'AV4083331', 1, 1),
(5656, 4, 'Registro Para Ducha Paso Completo Tipo Cruceta', 'Registro Para Ducha Paso Completo Tipo Cruceta', 1, '18800.00', '19.00', '27000.00', 'Registro Para Ducha Paso Completo Tipo Cruceta', '2024-10-10 17:45:06', '6973653176122', 1, 1),
(5657, 4, 'Registro Pomo Briza Grival', 'Registro Pomo Briza Grival', 1, '34799.00', '19.00', '45000.00', 'Registro Pomo Briza Grival', '2024-10-10 17:45:06', '117', 1, 1),
(5658, 4, 'Regulador Gas Manguera 1.5 Mts Amarilla', 'Regulador Gas Manguera 1.5 Mts Amarilla', 2, '11350.00', '19.00', '17000.00', 'Regulador Gas Manguera 1.5 Mts Amarilla', '2024-10-10 17:45:06', '1516', 1, 1),
(5659, 4, 'Rejilla Antiolores Anticucarachas Fermetal Rej C07', 'Rejilla Antiolores Anticucarachas Fermetal Rej C07', 2, '17661.00', '19.00', '24500.00', 'Rejilla Antiolores Anticucarachas Fermetal Rej C07', '2024-10-10 17:45:06', '7592032503442', 1, 1),
(5660, 4, 'Rejilla Lavaplatos Uduke Acero Inoxidavble 3 (90mm)', 'Rejilla Lavaplatos Uduke Acero Inoxidavble 3 (90mm)', 10, '3100.00', '19.00', '4500.00', 'Rejilla Lavaplatos Uduke Acero Inoxidavble 3 (90mm)', '2024-10-10 17:45:06', '6973653170755', 1, 1),
(5661, 4, 'Rejilla Lvpts Plast-Grifos', 'Rejilla Lvpts Plast-Grifos', 2, '4000.00', '19.00', '1500.00', 'Rejilla Lvpts Plast-Grifos', '2024-10-10 17:45:06', '52', 1, 1),
(5662, 4, 'Rejilla Para Lavaplatos Uduke', 'Rejilla Para Lavaplatos Uduke', 1, '3130.00', '19.00', '4500.00', 'Rejilla Para Lavaplatos Uduke', '2024-10-10 17:45:06', '6973653170762', 1, 1),
(5663, 4, 'Rejilla Para Piso Acero Inoxidable Boccherini', 'Rejilla Para Piso Acero Inoxidable Boccherini', 8, '16099.00', '19.00', '26000.00', 'Rejilla Para Piso Acero Inoxidable Boccherini', '2024-10-10 17:45:06', '7707180675983', 1, 1),
(5664, 4, 'Rejilla Ranurada 2', 'Rejilla Ranurada 2', 8, '1250.00', '19.00', '1900.00', 'Rejilla Ranurada 2', '2024-10-10 17:45:06', '2543', 1, 1),
(5665, 4, 'Rejilla Ranurada 2 1/2', 'Rejilla Ranurada 2 1/2', 4, '1650.00', '19.00', '2500.00', 'Rejilla Ranurada 2 1/2', '2024-10-10 17:45:06', '2544', 1, 1),
(5666, 4, 'Rejilla Ranurada 3', 'Rejilla Ranurada 3', 6, '1900.00', '19.00', '2900.00', 'Rejilla Ranurada 3', '2024-10-10 17:45:06', '2545', 1, 1),
(5667, 4, 'Rejilla Ranurada 4', 'Rejilla Ranurada 4', 7, '2250.00', '19.00', '3500.00', 'Rejilla Ranurada 4', '2024-10-10 17:45:06', '2546', 1, 1),
(5668, 4, 'Rejilla Sifon Antiolores Para Baño 4x4', 'Rejilla Sifon Antiolores Para Baño 4x4', 7, '9650.00', '19.00', '13900.00', 'Rejilla Sifon Antiolores Para Baño 4x4', '2024-10-10 17:45:06', '2547', 1, 1),
(5669, 4, 'Rejilla Sifon Pvc Boccherini Anti Cucarachas', 'Rejilla Sifon Pvc Boccherini Anti Cucarachas', 11, '1449.00', '19.00', '3000.00', 'Rejilla Sifon Pvc Boccherini Anti Cucarachas', '2024-10-10 17:45:06', '7707180678397', 1, 1),
(5670, 4, 'Rejilla Sifon Recortada Boccherini', 'Rejilla Sifon Recortada Boccherini', 4, '949.00', '19.00', '2000.00', 'Rejilla Sifon Recortada Boccherini', '2024-10-10 17:45:06', '7707180672975', 1, 1),
(5671, 4, 'Remachadora Boccherini', 'Remachadora Boccherini', 2, '10600.00', '19.00', '20000.00', 'Remachadora Boccherini', '2024-10-10 17:45:06', '7707180678915', 1, 1),
(5672, 4, 'Repuesto Asiento Sanitario Boccherini', 'Repuesto Asiento Sanitario Boccherini', 2, '9999.00', '19.00', '15000.00', 'Repuesto Asiento Sanitario Boccherini', '2024-10-10 17:45:06', '14', 1, 1),
(5673, 4, 'Repuesto Vastago', 'Repuesto Vastago', 5, '8698.00', '19.00', '11000.00', 'Repuesto Vastago', '2024-10-10 17:45:06', '41', 1, 1),
(5674, 4, 'Riel Para Spot', 'Riel Para Spot', 1, '8000.00', '19.00', '20000.00', 'Riel Para Spot', '2024-10-10 17:45:06', 'G1', 1, 1),
(5675, 4, 'Rodillo Antigoteo 9 Caribe', 'Rodillo Antigoteo 9 Caribe', 9, '6800.00', '19.00', '9400.00', 'Rodillo Antigoteo 9 Caribe', '2024-10-10 17:45:06', '7707342740818', 1, 1),
(5676, 4, 'Rodillo Goya 9¨ Bricolage', 'Rodillo Goya 9¨ Bricolage', 5, '7200.00', '19.00', '10500.00', 'Rodillo Goya 9¨ Bricolage', '2024-10-10 17:45:06', '7707062905528', 1, 1),
(5677, 4, 'Rodillo Popular 9 Caribe', 'Rodillo Popular 9 Caribe', 1, '4792.00', '19.00', '7600.00', 'Rodillo Popular 9 Caribe', '2024-10-10 17:45:06', '7707342742010', 1, 1),
(5678, 4, 'Rodillo Recargable Boccherini', 'Rodillo Recargable Boccherini', 0, '26180.00', '19.00', '38000.00', 'Rodillo Recargable Boccherini', '2024-10-10 17:45:06', '7707180678359', 1, 1),
(5679, 4, 'Rollo Duplex #12 Centelsa X100m', 'Rollo Duplex #12 Centelsa X100m', 1, '370000.00', '19.00', '462500.00', 'Rollo Duplex #12 Centelsa X100m', '2024-10-10 17:45:06', '7707313321206', 1, 1),
(5680, 4, 'SANITARIO HAPPY VERDE BICOLOR', 'SANITARIO HAPPY VERDE BICOLOR', 1, '261332.00', '19.00', '315000.00', 'SANITARIO HAPPY VERDE BICOLOR', '2024-10-10 17:45:06', '770561491292', 1, 1),
(5681, 4, 'SOLDADURA PVC 1/4 ARKET', 'SOLDADURA PVC 1/4 ARKET', 0, '40800.00', '19.00', '53500.00', 'SOLDADURA PVC 1/4 ARKET', '2024-10-10 17:45:06', '190006', 1, 1),
(5682, 4, 'Sanitario Elegance 2109 Blanco', 'Sanitario Elegance 2109 Blanco', 0, '330000.00', '19.00', '420000.00', 'Sanitario Elegance 2109 Blanco', '2024-10-10 17:45:06', '7075', 1, 1),
(5683, 4, 'Sanitario Elegance 2482 Beige', 'Sanitario Elegance 2482 Beige', 1, '380000.00', '19.00', '460000.00', 'Sanitario Elegance 2482 Beige', '2024-10-10 17:45:06', '7051', 1, 1),
(5684, 4, 'Sanitario Laguna Beige', 'Sanitario Laguna Beige', 0, '189900.00', '19.00', '280000.00', 'Sanitario Laguna Beige', '2024-10-10 17:45:06', '112', 1, 1),
(5685, 4, 'Sello Para Pintores Verde Corona', 'Sello Para Pintores Verde Corona', 2, '8639.00', '19.00', '12000.00', 'Sello Para Pintores Verde Corona', '2024-10-10 17:45:06', '7705389002708', 1, 1),
(5686, 4, 'Sello Puertas Y Ventanas Corona', 'Sello Puertas Y Ventanas Corona', 2, '11149.00', '19.00', '15000.00', 'Sello Puertas Y Ventanas Corona', '2024-10-10 17:45:06', '7705389002739', 1, 1),
(5687, 4, 'Sensor De Movimiento Aura Light', 'Sensor De Movimiento Aura Light', 1, '24000.00', '19.00', '30000.00', 'Sensor De Movimiento Aura Light', '2024-10-10 17:45:06', '69', 1, 1),
(5688, 4, 'Sensor Porta Bombillo E27 ZFR31 Zafiro', 'Sensor Porta Bombillo E27 ZFR31 Zafiro', 2, '13300.00', '19.00', '18600.00', 'Sensor Porta Bombillo E27 ZFR31 Zafiro', '2024-10-10 17:45:06', 'SEN12', 1, 1),
(5689, 4, 'Sensor Roseta E 27 Mercury', 'Sensor Roseta E 27 Mercury', 3, '15667.00', '19.00', '21000.00', 'Sensor Roseta E 27 Mercury', '2024-10-10 17:45:06', '7707692865506', 1, 1),
(5690, 4, 'Serrucho Mango Madera Boccherini', 'Serrucho Mango Madera Boccherini', 2, '11300.00', '19.00', '17000.00', 'Serrucho Mango Madera Boccherini', '2024-10-10 17:45:06', '7707766450942', 1, 1),
(5691, 4, 'Set Desarmadores 6 Piezas Pequeño', 'Set Desarmadores 6 Piezas Pequeño', 1, '4700.00', '19.00', '6800.00', 'Set Desarmadores 6 Piezas Pequeño', '2024-10-10 17:45:06', '7707180678878', 1, 1),
(5692, 4, 'Set Desarmadores 6 piezas', 'Set Desarmadores 6 piezas', 1, '6999.00', '19.00', '10000.00', 'Set Desarmadores 6 piezas', '2024-10-10 17:45:06', '7707180678861', 1, 1),
(5693, 4, 'Set Ducha Abs 5034k Boccherini', 'Set Ducha Abs 5034k Boccherini', 2, '22900.00', '19.00', '33000.00', 'Set Ducha Abs 5034k Boccherini', '2024-10-10 17:45:06', '7707180671251', 1, 1),
(5694, 4, 'Set Ducha Plastica', 'Set Ducha Plastica', 6, '7900.00', '19.00', '13000.00', 'Set Ducha Plastica', '2024-10-10 17:45:06', '7707180675754', 1, 1),
(5695, 4, 'Set ducha Yw263 Boccherini', 'Set ducha Yw263 Boccherini', 0, '13200.00', '19.00', '19000.00', 'Set ducha Yw263 Boccherini', '2024-10-10 17:45:06', '123', 1, 1),
(5696, 4, 'Sifon Antiolores 3x2', 'Sifon Antiolores 3x2', 5, '5200.00', '19.00', '7500.00', 'Sifon Antiolores 3x2', '2024-10-10 17:45:06', '2548', 1, 1),
(5697, 4, 'Sifon Antiolores 4x3', 'Sifon Antiolores 4x3', 6, '6000.00', '19.00', '8900.00', 'Sifon Antiolores 4x3', '2024-10-10 17:45:06', '2549', 1, 1),
(5698, 4, 'Sifon Extensible Flexible Fermetal Sif C08-1', 'Sifon Extensible Flexible Fermetal Sif C08-1', 2, '7454.00', '19.00', '11000.00', 'Sifon Extensible Flexible Fermetal Sif C08-1', '2024-10-10 17:45:06', '7592032500687', 1, 1),
(5699, 4, 'Sifon Flexible Blanco Federalli', 'Sifon Flexible Blanco Federalli', 2, '7499.00', '19.00', '11000.00', 'Sifon Flexible Blanco Federalli', '2024-10-10 17:45:06', '8520479600775', 1, 1),
(5700, 4, 'Sifon Flexible Lvps Y Lvms Boccherini', 'Sifon Flexible Lvps Y Lvms Boccherini', 0, '7099.00', '19.00', '11500.00', 'Sifon Flexible Lvps Y Lvms Boccherini', '2024-10-10 17:45:06', '7707180670384', 1, 1),
(5701, 4, 'Sifon Flexible Plateado', 'Sifon Flexible Plateado', 0, '9999.00', '19.00', '12000.00', 'Sifon Flexible Plateado', '2024-10-10 17:45:06', '6942150284012', 1, 1),
(5702, 4, 'Sifon Lavamanos  Rio Plast', 'Sifon Lavamanos  Rio Plast', 2, '3344.00', '19.00', '5200.00', 'Sifon Lavamanos  Rio Plast', '2024-10-10 17:45:06', '320012020', 1, 1),
(5703, 4, 'Sifon Lavamanos Completo Hidrogriferias Cardenas Blanco', 'Sifon Lavamanos Completo Hidrogriferias Cardenas Blanco', 3, '4000.00', '19.00', '6500.00', 'Sifon Lavamanos Completo Hidrogriferias Cardenas Blanco', '2024-10-10 17:45:06', '1568', 1, 1),
(5704, 4, 'Sifon Lavamanos Rejilla Cromo', 'Sifon Lavamanos Rejilla Cromo', 0, '4620.00', '19.00', '6600.00', 'Sifon Lavamanos Rejilla Cromo', '2024-10-10 17:45:06', '2524', 1, 1),
(5705, 4, 'Sika 101 - Mortero Plus Gris 10Kg', 'Sika 101 - Mortero Plus Gris 10Kg', 2, '85454.00', '19.00', '110000.00', 'Sika 101 - Mortero Plus Gris 10Kg', '2024-10-10 17:45:06', '7706788095537', 1, 1),
(5706, 4, 'Sika Estuka Estuco Acrilico Gl X 6.2 Kg', 'Sika Estuka Estuco Acrilico Gl X 6.2 Kg', 5, '22308.00', '19.00', '26800.00', 'Sika Estuka Estuco Acrilico Gl X 6.2 Kg', '2024-10-10 17:45:06', '7706788010127', 1, 1),
(5707, 4, 'Sika Joint Estuco Interior Masilla Multiusos 27Kg', 'Sika Joint Estuco Interior Masilla Multiusos 27Kg', 2, '46940.00', '19.00', '58500.00', 'Sika Joint Estuco Interior Masilla Multiusos 27Kg', '2024-10-10 17:45:06', '7706788094950', 1, 1),
(5708, 4, 'Sika Sanisil Transparente Antihongos X 300 Grs', 'Sika Sanisil Transparente Antihongos X 300 Grs', 5, '10028.00', '19.00', '13000.00', 'Sika Sanisil Transparente Antihongos X 300 Grs', '2024-10-10 17:45:06', '7756962003674', 1, 1),
(5709, 4, 'SikaWall Estuco Acrilico 30kg Interior Y Exterior', 'SikaWall Estuco Acrilico 30kg Interior Y Exterior', 1, '77578.00', '19.00', '84500.00', 'SikaWall Estuco Acrilico 30kg Interior Y Exterior', '2024-10-10 17:45:06', '7706788097876', 1, 1),
(5710, 4, 'Sikaflex® Universal Blanco', 'Sikaflex® Universal Blanco', 4, '18287.00', '19.00', '24000.00', 'Sikaflex® Universal Blanco', '2024-10-10 17:45:06', '7612895296500', 1, 1),
(5711, 4, 'Sikaflex® Universal Gris', 'Sikaflex® Universal Gris', 4, '18287.00', '19.00', '24000.00', 'Sikaflex® Universal Gris', '2024-10-10 17:45:06', '7612895306940', 1, 1),
(5712, 4, 'Silicona Barra Gruesa AH-Royal 21Cm', 'Silicona Barra Gruesa AH-Royal 21Cm', 30, '401.00', '19.00', '1000.00', 'Silicona Barra Gruesa AH-Royal 21Cm', '2024-10-10 17:45:06', '9557790567264', 1, 1),
(5713, 4, 'Silicona Estructura Y Pvc Lacurva', 'Silicona Estructura Y Pvc Lacurva', 0, '22000.00', '19.00', '28000.00', 'Silicona Estructura Y Pvc Lacurva', '2024-10-10 17:45:06', '25423365', 1, 1),
(5714, 4, 'Silicona Multi-Proposito Sika', 'Silicona Multi-Proposito Sika', 4, '10815.00', '19.00', '15000.00', 'Silicona Multi-Proposito Sika', '2024-10-10 17:45:06', '7756962004084', 1, 1),
(5715, 4, 'Silicona Para Pistola Afix Pegadit', 'Silicona Para Pistola Afix Pegadit', 0, '10238.00', '19.00', '13500.00', 'Silicona Para Pistola Afix Pegadit', '2024-10-10 17:45:06', '7702505810865', 1, 1),
(5716, 4, 'Silicona Roja 50ml Loctite', 'Silicona Roja 50ml Loctite', 1, '5100.00', '19.00', '6000.00', 'Silicona Roja 50ml Loctite', '2024-10-10 17:45:06', '7702045564075', 1, 1),
(5717, 4, 'Silicona Transparente X 270 Ml Tek Bond', 'Silicona Transparente X 270 Ml Tek Bond', 9, '8702.00', '19.00', '11500.00', 'Silicona Transparente X 270 Ml Tek Bond', '2024-10-10 17:45:06', '7898472259629', 1, 1),
(5718, 4, 'Socker Losa 4 Puntas', 'Socker Losa 4 Puntas', 6, '1250.00', '19.00', '2000.00', 'Socker Losa 4 Puntas', '2024-10-10 17:45:06', '7708329857062', 1, 1),
(5719, 4, 'Socker Pata Beige', 'Socker Pata Beige', 6, '999.00', '19.00', '1500.00', 'Socker Pata Beige', '2024-10-10 17:45:06', '1588', 1, 1),
(5720, 4, 'Sockert Caucho', 'Sockert Caucho', 6, '800.00', '19.00', '1200.00', 'Sockert Caucho', '2024-10-10 17:45:06', '2727', 1, 1),
(5721, 4, 'Soda Caustica 300g', 'Soda Caustica 300g', 2, '2800.00', '19.00', '4000.00', 'Soda Caustica 300g', '2024-10-10 17:45:06', '7709694849133', 1, 1),
(5722, 4, 'Soldadura De Pvc Genfor', 'Soldadura De Pvc Genfor', 0, '9392.00', '19.00', '11800.00', 'Soldadura De Pvc Genfor', '2024-10-10 17:45:06', '7707015322006', 1, 1),
(5723, 4, 'Soldadura Estaño Uduke', 'Soldadura Estaño Uduke', 3, '2972.00', '19.00', '4500.00', 'Soldadura Estaño Uduke', '2024-10-10 17:45:06', '6973653177167', 1, 1),
(5724, 4, 'Soldadura Gerfor 1/32', 'Soldadura Gerfor 1/32', 0, '10844.00', '19.00', '15500.00', 'Soldadura Gerfor 1/32', '2024-10-10 17:45:06', '7707015321993', 1, 1),
(5725, 4, 'Soldadura Liquida De Pvc MC 1/128', 'Soldadura Liquida De Pvc MC 1/128', 1, '1600.00', '19.00', '2500.00', 'Soldadura Liquida De Pvc MC 1/128', '2024-10-10 17:45:06', '7707214130266', 1, 1),
(5726, 4, 'Soldadura Pvc  Arkel 1/64', 'Soldadura Pvc  Arkel 1/64', 9, '2804.00', '19.00', '3700.00', 'Soldadura Pvc  Arkel 1/64', '2024-10-10 17:45:06', '190002', 1, 1),
(5727, 4, 'Soldadura Pvc 1/128 30 Ml Gerfor', 'Soldadura Pvc 1/128 30 Ml Gerfor', 0, '3454.00', '19.00', '4800.00', 'Soldadura Pvc 1/128 30 Ml Gerfor', '2024-10-10 17:45:06', '7707015322013', 1, 1),
(5728, 4, 'Soldadura Pvc 1/128 Arket', 'Soldadura Pvc 1/128 Arket', 0, '1836.00', '19.00', '2600.00', 'Soldadura Pvc 1/128 Arket', '2024-10-10 17:45:06', '190001', 1, 1),
(5729, 4, 'Soldadura Pvc 1/16 Arket', 'Soldadura Pvc 1/16 Arket', 3, '11487.00', '19.00', '18000.00', 'Soldadura Pvc 1/16 Arket', '2024-10-10 17:45:06', '190004', 1, 1),
(5730, 4, 'Soldadura Pvc 1/8 Arket', 'Soldadura Pvc 1/8 Arket', 3, '18189.00', '19.00', '28300.00', 'Soldadura Pvc 1/8 Arket', '2024-10-10 17:45:06', '190005', 1, 1),
(5731, 4, 'Soldadura Soldacol 1/128 Con Aplicador', 'Soldadura Soldacol 1/128 Con Aplicador', 4, '2900.00', '19.00', '4200.00', 'Soldadura Soldacol 1/128 Con Aplicador', '2024-10-10 17:45:06', '2505', 1, 1),
(5732, 4, 'Soldadura Soldacol 1/256 Con Aplicador', 'Soldadura Soldacol 1/256 Con Aplicador', 1, '1800.00', '19.00', '2600.00', 'Soldadura Soldacol 1/256 Con Aplicador', '2024-10-10 17:45:06', '2506', 1, 1),
(5733, 4, 'Soldadura Soldacol 1/32 Con Aplicador', 'Soldadura Soldacol 1/32 Con Aplicador', 0, '6300.00', '19.00', '9000.00', 'Soldadura Soldacol 1/32 Con Aplicador', '2024-10-10 17:45:06', '2503', 1, 1),
(5734, 4, 'Soldadura Soldacol 1/4', 'Soldadura Soldacol 1/4', 0, '32000.00', '19.00', '45700.00', 'Soldadura Soldacol 1/4', '2024-10-10 17:45:06', '2500', 1, 1),
(5735, 4, 'Soldadura Soldacol 1/64 Con Aplicador', 'Soldadura Soldacol 1/64 Con Aplicador', 0, '4000.00', '19.00', '5800.00', 'Soldadura Soldacol 1/64 Con Aplicador', '2024-10-10 17:45:06', '2504', 1, 1),
(5736, 4, 'Soldadura Soldacol 1/8', 'Soldadura Soldacol 1/8', 1, '18300.00', '19.00', '26500.00', 'Soldadura Soldacol 1/8', '2024-10-10 17:45:06', '2501', 1, 1),
(5737, 4, 'Soldadura Soldacol1/16', 'Soldadura Soldacol1/16', 0, '11100.00', '19.00', '16000.00', 'Soldadura Soldacol1/16', '2024-10-10 17:45:06', '2502', 1, 1),
(5738, 4, 'Soporte Cortina Cafe 1', 'Soporte Cortina Cafe 1', 10, '453.00', '19.00', '700.00', 'Soporte Cortina Cafe 1', '2024-10-10 17:45:06', '8736', 1, 1),
(5739, 4, 'Sosco Anticucaracha 4 X 3', 'Sosco Anticucaracha 4 X 3', 12, '4300.00', '19.00', '6500.00', 'Sosco Anticucaracha 4 X 3', '2024-10-10 17:45:06', '2540', 1, 1),
(5740, 4, 'Sosco Anticucaracha Plastico 3x2 Hidrogriferias', 'Sosco Anticucaracha Plastico 3x2 Hidrogriferias', 12, '1500.00', '19.00', '2500.00', 'Sosco Anticucaracha Plastico 3x2 Hidrogriferias', '2024-10-10 17:45:06', '2541', 1, 1),
(5741, 4, 'Sosco Ranurado 3x2', 'Sosco Ranurado 3x2', 4, '2950.00', '19.00', '4500.00', 'Sosco Ranurado 3x2', '2024-10-10 17:45:06', '2542', 1, 1),
(5742, 4, 'Splitter De Tv 2 Entradas Uduke', 'Splitter De Tv 2 Entradas Uduke', 9, '2806.00', '19.00', '4000.00', 'Splitter De Tv 2 Entradas Uduke', '2024-10-10 17:45:06', '6973653172049', 1, 1),
(5743, 4, 'Splitter TV 4 Salidas Economico 778K Uduke', 'Splitter TV 4 Salidas Economico 778K Uduke', 6, '1300.00', '19.00', '2000.00', 'Splitter TV 4 Salidas Economico 778K Uduke', '2024-10-10 17:45:06', '7708294276882', 1, 1),
(5744, 4, 'Sport Armado Blaco x3', 'Sport Armado Blaco x3', 1, '95000.00', '19.00', '150000.00', 'Sport Armado Blaco x3', '2024-10-10 17:45:06', 'CR2', 1, 1),
(5745, 4, 'Spot Armado Plateado x3', 'Spot Armado Plateado x3', 1, '95000.00', '19.00', '150000.00', 'Spot Armado Plateado x3', '2024-10-10 17:45:06', 'CR1', 1, 1),
(5746, 4, 'Spot Blanco', 'Spot Blanco', 4, '25000.00', '19.00', '36000.00', 'Spot Blanco', '2024-10-10 17:45:06', 'SP1', 1, 1),
(5747, 4, 'Subcj Lvpts 8p Balta Cruceta Grival', 'Subcj Lvpts 8p Balta Cruceta Grival', 2, '86448.00', '19.00', '108000.00', 'Subcj Lvpts 8p Balta Cruceta Grival', '2024-10-10 17:45:06', '57', 1, 1),
(5748, 4, 'Subcj Lvpts 8p Dalia Grival', 'Subcj Lvpts 8p Dalia Grival', 1, '86999.00', '19.00', '110000.00', 'Subcj Lvpts 8p Dalia Grival', '2024-10-10 17:45:06', '58', 1, 1),
(5749, 4, 'Subcj. Llave Lvpts Dalia Palanca Grival', 'Subcj. Llave Lvpts Dalia Palanca Grival', 0, '60899.00', '19.00', '79000.00', 'Subcj. Llave Lvpts Dalia Palanca Grival', '2024-10-10 17:45:06', '54', 1, 1),
(5750, 4, 'Subcj. Llave Lvpts Tamara Grival', 'Subcj. Llave Lvpts Tamara Grival', 1, '60899.00', '19.00', '78500.00', 'Subcj. Llave Lvpts Tamara Grival', '2024-10-10 17:45:06', '53', 1, 1),
(5751, 4, 'Super Blue Azul 8.0grs', 'Super Blue Azul 8.0grs', 15, '708.00', '19.00', '1500.00', 'Super Blue Azul 8.0grs', '2024-10-10 17:45:06', '7453088046287', 1, 1),
(5752, 4, 'Super Blue Rojo', 'Super Blue Rojo', 21, '458.00', '19.00', '1000.00', 'Super Blue Rojo', '2024-10-10 17:45:06', '7708514049579', 1, 1),
(5753, 4, 'Swiche Doble Gris Linea Luxor Intercambiable Sin Tapa', 'Swiche Doble Gris Linea Luxor Intercambiable Sin Tapa', 1, '6000.00', '19.00', '7800.00', 'Swiche Doble Gris Linea Luxor Intercambiable Sin Tapa', '2024-10-10 17:45:06', '6971580773193', 1, 1),
(5754, 4, 'Swiche Doble Incrustar Blanco Linea BK', 'Swiche Doble Incrustar Blanco Linea BK', 6, '4400.00', '19.00', '6500.00', 'Swiche Doble Incrustar Blanco Linea BK', '2024-10-10 17:45:06', '6973653171189', 1, 1),
(5755, 4, 'Swiche Doble Luxor Blanco Intercambiable Sin Tapa', 'Swiche Doble Luxor Blanco Intercambiable Sin Tapa', 6, '5600.00', '19.00', '7800.00', 'Swiche Doble Luxor Blanco Intercambiable Sin Tapa', '2024-10-10 17:45:06', '6971580773100', 1, 1),
(5756, 4, 'Swiche Doble Negro Linea Luxor Intercambiable Sin Tapa', 'Swiche Doble Negro Linea Luxor Intercambiable Sin Tapa', 10, '6000.00', '19.00', '8400.00', 'Swiche Doble Negro Linea Luxor Intercambiable Sin Tapa', '2024-10-10 17:45:06', '6971580774527', 1, 1),
(5757, 4, 'Swiche Negro Sencillo Linea Luxor Intercambiable Sin Tapa', 'Swiche Negro Sencillo Linea Luxor Intercambiable Sin Tapa', 2, '3750.00', '19.00', '5200.00', 'Swiche Negro Sencillo Linea Luxor Intercambiable Sin Tapa', '2024-10-10 17:45:06', '6971580774510', 1, 1),
(5758, 4, 'Swiche Sencillo Beige', 'Swiche Sencillo Beige', 3, '1149.00', '19.00', '2000.00', 'Swiche Sencillo Beige', '2024-10-10 17:45:06', '6973877760015', 1, 1),
(5759, 4, 'Swiche Sencillo Gris Linea Luxor Intercambiable Sin Tapa', 'Swiche Sencillo Gris Linea Luxor Intercambiable Sin Tapa', 6, '3750.00', '19.00', '5200.00', 'Swiche Sencillo Gris Linea Luxor Intercambiable Sin Tapa', '2024-10-10 17:45:06', '6971580773186', 1, 1),
(5760, 4, 'Swiche Sencillo Incrustar Linea BK HT20335', 'Swiche Sencillo Incrustar Linea BK HT20335', 5, '2311.00', '19.00', '4000.00', 'Swiche Sencillo Incrustar Linea BK HT20335', '2024-10-10 17:45:06', '6973653174500', 1, 1),
(5761, 4, 'Swiche Sencillo Luxor Blanco Intercambiable Sin Tapa V9003', 'Swiche Sencillo Luxor Blanco Intercambiable Sin Tapa V9003', 8, '3500.00', '19.00', '5200.00', 'Swiche Sencillo Luxor Blanco Intercambiable Sin Tapa V9003', '2024-10-10 17:45:06', '6971580773094', 1, 1),
(5762, 4, 'Swiche Timbre Uduke', 'Swiche Timbre Uduke', 10, '2975.00', '19.00', '4500.00', 'Swiche Timbre Uduke', '2024-10-10 17:45:06', '6973653174517', 1, 1),
(5763, 4, 'Swiche Triple Gris Linea Luxor Intercambiable Sin Tapa', 'Swiche Triple Gris Linea Luxor Intercambiable Sin Tapa', 10, '8300.00', '19.00', '11600.00', 'Swiche Triple Gris Linea Luxor Intercambiable Sin Tapa', '2024-10-10 17:45:06', '6971580773209', 1, 1),
(5764, 4, 'Swiche Triple Linea Luxor Blanco Intercambiable Sin Tapa V9009', 'Swiche Triple Linea Luxor Blanco Intercambiable Sin Tapa V9009', 8, '7650.00', '19.00', '10700.00', 'Swiche Triple Linea Luxor Blanco Intercambiable Sin Tapa V9009', '2024-10-10 17:45:06', '6971580773117', 1, 1);
INSERT INTO `inventario` (`id`, `user_id`, `nombre`, `descripcion`, `stock`, `precio_costo`, `impuesto`, `precio_venta`, `otro_dato`, `fecha_ingreso`, `codigo_barras`, `departamento_id`, `categoria_id`) VALUES
(5765, 4, 'Swiche Triple Linea Luxor Negro Intercambiable Sin Tapa V17009', 'Swiche Triple Linea Luxor Negro Intercambiable Sin Tapa V17009', 8, '8003.00', '19.00', '11600.00', 'Swiche Triple Linea Luxor Negro Intercambiable Sin Tapa V17009', '2024-10-10 17:45:06', '6971580774534', 1, 1),
(5766, 4, 'Tablero Para Breker De 2 Circuitos', 'Tablero Para Breker De 2 Circuitos', 0, '22387.00', '19.00', '35000.00', 'Tablero Para Breker De 2 Circuitos', '2024-10-10 17:45:06', '7709847417332', 1, 1),
(5767, 4, 'Tablero Portacircuitos Induma', 'Tablero Portacircuitos Induma', 1, '39183.00', '19.00', '56000.00', 'Tablero Portacircuitos Induma', '2024-10-10 17:45:06', '7702587578134', 1, 1),
(5768, 4, 'Taladro Inalambrico 1/2 Pretul', 'Taladro Inalambrico 1/2 Pretul', 0, '197242.00', '19.00', '260359.00', 'Taladro Inalambrico 1/2 Pretul', '2024-10-10 17:45:06', '7506240690061', 1, 1),
(5769, 4, 'Taladro Inalámbrico 3/8 Pretul + Set Puntas', 'Taladro Inalámbrico 3/8 Pretul + Set Puntas', 0, '136552.00', '19.00', '170000.00', 'Taladro Inalámbrico 3/8 Pretul + Set Puntas', '2024-10-10 17:45:06', '7506240659419', 1, 1),
(5770, 4, 'Taladro Percutor De Cable 1/2 Pretul 550W', 'Taladro Percutor De Cable 1/2 Pretul 550W', 1, '106208.00', '19.00', '140000.00', 'Taladro Percutor De Cable 1/2 Pretul 550W', '2024-10-10 17:45:06', '7506240681946', 1, 1),
(5771, 4, 'Taladro percutotor 1/2 Pretul 20v inalámbrico', 'Taladro percutotor 1/2 Pretul 20v inalámbrico', 1, '215000.00', '19.00', '260000.00', 'Taladro percutotor 1/2 Pretul 20v inalámbrico', '2024-10-10 17:45:06', '26117', 1, 1),
(5772, 4, 'Tapa Cuadrada Acero Inoxidable Borde Plata Line Luxor V3-02AC', 'Tapa Cuadrada Acero Inoxidable Borde Plata Line Luxor V3-02AC', 4, '2250.00', '19.00', '3500.00', 'Tapa Cuadrada Acero Inoxidable Borde Plata Line Luxor V3-02AC', '2024-10-10 17:45:06', '6971580773353', 1, 1),
(5773, 4, 'Tapa Huecos Plasticos Lavamanos', 'Tapa Huecos Plasticos Lavamanos', 4, '1608.00', '19.00', '3000.00', 'Tapa Huecos Plasticos Lavamanos', '2024-10-10 17:45:06', '3797', 1, 1),
(5774, 4, 'Tapa Linea Luxor Blanca Borde Dorado Perla B1802BD', 'Tapa Linea Luxor Blanca Borde Dorado Perla B1802BD', 12, '1950.00', '19.00', '2800.00', 'Tapa Linea Luxor Blanca Borde Dorado Perla B1802BD', '2024-10-10 17:45:06', '6971580776422', 1, 1),
(5775, 4, 'Tapa Linea Luxor Blanca Ovalada', 'Tapa Linea Luxor Blanca Ovalada', 22, '1250.00', '19.00', '1800.00', 'Tapa Linea Luxor Blanca Ovalada', '2024-10-10 17:45:06', '6971580773261', 1, 1),
(5776, 4, 'Tapa Linea Luxor Borde Interno Cromado V1902MC', 'Tapa Linea Luxor Borde Interno Cromado V1902MC', 12, '1750.00', '19.00', '2600.00', 'Tapa Linea Luxor Borde Interno Cromado V1902MC', '2024-10-10 17:45:06', '6971580777535', 1, 1),
(5777, 4, 'Tapa Linea Luxor Color Plata Convixos en 3D', 'Tapa Linea Luxor Color Plata Convixos en 3D', 8, '2500.00', '19.00', '3600.00', 'Tapa Linea Luxor Color Plata Convixos en 3D', '2024-10-10 17:45:06', '6971580777511', 1, 1),
(5778, 4, 'Tapa Linea Luxor Cuadrada Blanca', 'Tapa Linea Luxor Cuadrada Blanca', 2, '1250.00', '19.00', '1600.00', 'Tapa Linea Luxor Cuadrada Blanca', '2024-10-10 17:45:06', '6971580773278', 1, 1),
(5779, 4, 'Tapa Linea Luxor Dorada Cuadrada', 'Tapa Linea Luxor Dorada Cuadrada', 2, '1950.00', '19.00', '3000.00', 'Tapa Linea Luxor Dorada Cuadrada', '2024-10-10 17:45:06', '6971580773292', 1, 1),
(5780, 4, 'Tapa Linea Luxor Madera Claro Cuadrada Borde Dorado', 'Tapa Linea Luxor Madera Claro Cuadrada Borde Dorado', 2, '3650.00', '19.00', '4500.00', 'Tapa Linea Luxor Madera Claro Cuadrada Borde Dorado', '2024-10-10 17:45:06', '6971580773346', 1, 1),
(5781, 4, 'Tapa Linea Luxor Metalizada Cromada', 'Tapa Linea Luxor Metalizada Cromada', 0, '2250.00', '19.00', '3000.00', 'Tapa Linea Luxor Metalizada Cromada', '2024-10-10 17:45:06', '6971580773315', 1, 1),
(5782, 4, 'Tapa Linea Luxor Metalizada Negra', 'Tapa Linea Luxor Metalizada Negra', 2, '2250.00', '19.00', '3000.00', 'Tapa Linea Luxor Metalizada Negra', '2024-10-10 17:45:06', '6971580774619', 1, 1),
(5783, 4, 'Tapa Linea Luxornegro Brillante Borde Cromado Cuadrada', 'Tapa Linea Luxornegro Brillante Borde Cromado Cuadrada', 2, '3650.00', '19.00', '4500.00', 'Tapa Linea Luxornegro Brillante Borde Cromado Cuadrada', '2024-10-10 17:45:06', '6971580779829', 1, 1),
(5784, 4, 'Tapa Luxor Cristal Rosado Acrilico B1002-AR', 'Tapa Luxor Cristal Rosado Acrilico B1002-AR', 8, '4350.00', '19.00', '5600.00', 'Tapa Luxor Cristal Rosado Acrilico B1002-AR', '2024-10-10 17:45:06', '6971580777580', 1, 1),
(5785, 4, 'Tapa Negra Ovalada Linea Luxor Energy V1-02N', 'Tapa Negra Ovalada Linea Luxor Energy V1-02N', 15, '1850.00', '19.00', '2800.00', 'Tapa Negra Ovalada Linea Luxor Energy V1-02N', '2024-10-10 17:45:06', '6971580774603', 1, 1),
(5786, 4, 'Tapa Oidos Ajustable TR Industrial', 'Tapa Oidos Ajustable TR Industrial', 1, '18000.00', '19.00', '28000.00', 'Tapa Oidos Ajustable TR Industrial', '2024-10-10 17:45:06', '56893', 1, 1),
(5787, 4, 'Tapa Plastica De Paso  2x4 Blanca Induma', 'Tapa Plastica De Paso  2x4 Blanca Induma', 0, '541.00', '19.00', '800.00', 'Tapa Plastica De Paso  2x4 Blanca Induma', '2024-10-10 17:45:06', '7702587144643', 1, 1),
(5788, 4, 'Tapa Registro Boccherini', 'Tapa Registro Boccherini', 3, '4950.00', '19.00', '7000.00', 'Tapa Registro Boccherini', '2024-10-10 17:45:06', '7707180678441', 1, 1),
(5789, 4, 'Tapaboca Azul Pasta Ht90129', 'Tapaboca Azul Pasta Ht90129', 12, '1200.00', '19.00', '2000.00', 'Tapaboca Azul Pasta Ht90129', '2024-10-10 17:45:06', '6973877763207', 1, 1),
(5790, 4, 'Tapaboca Industrial Doble Ht30464 Np304', 'Tapaboca Industrial Doble Ht30464 Np304', 2, '9050.00', '19.00', '13500.00', 'Tapaboca Industrial Doble Ht30464 Np304', '2024-10-10 17:45:06', '6973877761364', 1, 1),
(5791, 4, 'Tapagoteras Texsa', 'Tapagoteras Texsa', 4, '10000.00', '19.00', '13500.00', 'Tapagoteras Texsa', '2024-10-10 17:45:06', '7707005000198', 1, 1),
(5792, 4, 'Tapon Liso 1/2 G-Plast', 'Tapon Liso 1/2 G-Plast', 75, '237.00', '19.00', '500.00', 'Tapon Liso 1/2 G-Plast', '2024-10-10 17:45:06', '105010', 1, 1),
(5793, 4, 'Tapon Rosca 1/2Pvc G-Plast', 'Tapon Rosca 1/2Pvc G-Plast', 81, '370.00', '19.00', '600.00', 'Tapon Rosca 1/2Pvc G-Plast', '2024-10-10 17:45:06', '72', 1, 1),
(5794, 4, 'Tapon Roscado 1', 'Tapon Roscado 1', 20, '1135.00', '19.00', '1700.00', 'Tapon Roscado 1', '2024-10-10 17:45:06', '121082', 1, 1),
(5795, 4, 'Tapon Soldable 1', 'Tapon Soldable 1', 19, '668.00', '19.00', '1000.00', 'Tapon Soldable 1', '2024-10-10 17:45:06', '121062', 1, 1),
(5796, 4, 'Tarros Plasticos', 'Tarros Plasticos', 0, '19000.00', '19.00', '25000.00', 'Tarros Plasticos', '2024-10-10 17:45:06', '157', 1, 1),
(5797, 4, 'Tee 2', 'Tee 2', 0, '9000.00', '19.00', '9500.00', 'Tee 2', '2024-10-10 17:45:06', '35145', 1, 1),
(5798, 4, 'Tee Blanca Uduke', 'Tee Blanca Uduke', 3, '1878.00', '19.00', '3000.00', 'Tee Blanca Uduke', '2024-10-10 17:45:06', '7708294276950', 1, 1),
(5799, 4, 'Tee Conector Blanco', 'Tee Conector Blanco', 12, '1249.00', '19.00', '1700.00', 'Tee Conector Blanco', '2024-10-10 17:45:06', '37707692865262', 1, 1),
(5800, 4, 'Tee Naranja Polo Tierra X 3', 'Tee Naranja Polo Tierra X 3', 3, '4950.00', '19.00', '6500.00', 'Tee Naranja Polo Tierra X 3', '2024-10-10 17:45:06', '7591996006334', 1, 1),
(5801, 4, 'Tee Naranja Polo/Tierrax3 Ovalada Sb-274', 'Tee Naranja Polo/Tierrax3 Ovalada Sb-274', 5, '4750.00', '19.00', '7000.00', 'Tee Naranja Polo/Tierrax3 Ovalada Sb-274', '2024-10-10 17:45:06', '7450077002828', 1, 1),
(5802, 4, 'Tee Presion 1/2pvc G-Plast', 'Tee Presion 1/2pvc G-Plast', 34, '624.00', '19.00', '900.00', 'Tee Presion 1/2pvc G-Plast', '2024-10-10 17:45:06', '46', 1, 1),
(5803, 4, 'Tee Sanitaria 2', 'Tee Sanitaria 2', 52, '2000.00', '19.00', '2800.00', 'Tee Sanitaria 2', '2024-10-10 17:45:06', '162111', 1, 1),
(5804, 4, 'Tee Sanitaria 2 Amarillo Arket', 'Tee Sanitaria 2 Amarillo Arket', 4, '3420.00', '19.00', '4900.00', 'Tee Sanitaria 2 Amarillo Arket', '2024-10-10 17:45:06', '122041', 1, 1),
(5805, 4, 'Tegistro Ducha Palanca Aluvia  Grival Corona', 'Tegistro Ducha Palanca Aluvia  Grival Corona', 1, '53550.00', '19.00', '66400.00', 'Tegistro Ducha Palanca Aluvia  Grival Corona', '2024-10-10 17:45:06', 'RG2', 1, 1),
(5806, 4, 'Tele Ducha 5funciones Boccherini', 'Tele Ducha 5funciones Boccherini', 0, '29000.00', '19.00', '42000.00', 'Tele Ducha 5funciones Boccherini', '2024-10-10 17:45:06', '7707180675969', 1, 1),
(5807, 4, 'Tele Ducha Cromada Boccherini', 'Tele Ducha Cromada Boccherini', 3, '30999.00', '19.00', '39000.00', 'Tele Ducha Cromada Boccherini', '2024-10-10 17:45:06', '7707180670285', 1, 1),
(5808, 4, 'Tenaza Para Carpintero 6', 'Tenaza Para Carpintero 6', 3, '9199.00', '19.00', '13500.00', 'Tenaza Para Carpintero 6', '2024-10-10 17:45:06', '7707180678618', 1, 1),
(5809, 4, 'Tenaza Para Carpintero 8', 'Tenaza Para Carpintero 8', 2, '13099.00', '19.00', '18900.00', 'Tenaza Para Carpintero 8', '2024-10-10 17:45:06', '7707180678625', 1, 1),
(5810, 4, 'Terminal 1/2 G-Plast', 'Terminal 1/2 G-Plast', 0, '189.00', '19.00', '300.00', 'Terminal 1/2 G-Plast', '2024-10-10 17:45:06', '123001', 1, 1),
(5811, 4, 'Terminal Bobre Redonda Macho', 'Terminal Bobre Redonda Macho', 200, '61.00', '19.00', '200.00', 'Terminal Bobre Redonda Macho', '2024-10-10 17:45:06', '16973653171254', 1, 1),
(5812, 4, 'Terminal Coaxial', 'Terminal Coaxial', 50, '1500.00', '19.00', '2000.00', 'Terminal Coaxial', '2024-10-10 17:45:06', '3254', 1, 1),
(5813, 4, 'Terminal Coaxial Rg 56', 'Terminal Coaxial Rg 56', 25, '449.00', '19.00', '600.00', 'Terminal Coaxial Rg 56', '2024-10-10 17:45:06', '3205', 1, 1),
(5814, 4, 'Terminal Cobre Plana Macho', 'Terminal Cobre Plana Macho', 200, '68.00', '19.00', '200.00', 'Terminal Cobre Plana Macho', '2024-10-10 17:45:06', '16973653171247', 1, 1),
(5815, 4, 'Textuco 1 Kilo Corona', 'Textuco 1 Kilo Corona', 10, '4800.00', '19.00', '6500.00', 'Textuco 1 Kilo Corona', '2024-10-10 17:45:06', '7705389000834', 1, 1),
(5816, 4, 'Textuco 400gramos Corona', 'Textuco 400gramos Corona', 19, '1491.00', '19.00', '5000.00', 'Textuco 400gramos Corona', '2024-10-10 17:45:06', '7705389012684', 1, 1),
(5817, 4, 'Thinner Litro', 'Thinner Litro', 7, '4858.00', '19.00', '7000.00', 'Thinner Litro', '2024-10-10 17:45:06', '17', 1, 1),
(5818, 4, 'Thinner Medio Galon', 'Thinner Medio Galon', 7, '9716.00', '19.00', '13000.00', 'Thinner Medio Galon', '2024-10-10 17:45:06', '16', 1, 1),
(5819, 4, 'Thinner Medio Litro', 'Thinner Medio Litro', 1, '2333.00', '19.00', '4000.00', 'Thinner Medio Litro', '2024-10-10 17:45:06', '158', 1, 1),
(5820, 4, 'Tijera Para Hojalatera 8´´ Pretul', 'Tijera Para Hojalatera 8´´ Pretul', 2, '23062.00', '19.00', '33000.00', 'Tijera Para Hojalatera 8´´ Pretul', '2024-10-10 17:45:06', '7506240607304', 1, 1),
(5821, 4, 'Tijera Para Hojalatero 10´´ Pretul', 'Tijera Para Hojalatero 10´´ Pretul', 2, '28827.00', '19.00', '41500.00', 'Tijera Para Hojalatero 10´´ Pretul', '2024-10-10 17:45:06', '7506240601944', 1, 1),
(5822, 4, 'Tijera Para Hojalatero 12´´ Pretul', 'Tijera Para Hojalatero 12´´ Pretul', 2, '33632.00', '19.00', '48500.00', 'Tijera Para Hojalatero 12´´ Pretul', '2024-10-10 17:45:06', '7506240601951', 1, 1),
(5823, 4, 'Tinte Para Madera Anime 1/8 Corona', 'Tinte Para Madera Anime 1/8 Corona', 4, '11211.00', '19.00', '27000.00', 'Tinte Para Madera Anime 1/8 Corona', '2024-10-10 17:45:06', '7705389010703', 1, 1),
(5824, 4, 'Tinte Para Madera Caoba 1/8 Corona', 'Tinte Para Madera Caoba 1/8 Corona', 2, '11211.00', '19.00', '27000.00', 'Tinte Para Madera Caoba 1/8 Corona', '2024-10-10 17:45:06', '7705389010659', 1, 1),
(5825, 4, 'Tinte Para Madera Wengue 1/8 Corona', 'Tinte Para Madera Wengue 1/8 Corona', 4, '11211.00', '19.00', '27000.00', 'Tinte Para Madera Wengue 1/8 Corona', '2024-10-10 17:45:06', '7705389010932', 1, 1),
(5826, 4, 'Toallero Aro Aluminio Boccherini Yw027', 'Toallero Aro Aluminio Boccherini Yw027', 2, '0.00', '19.00', '35000.00', 'Toallero Aro Aluminio Boccherini Yw027', '2024-10-10 17:45:06', '7707180675051', 1, 1),
(5827, 4, 'Toallero De Barra Boccherini', 'Toallero De Barra Boccherini', 0, '20899.00', '19.00', '30000.00', 'Toallero De Barra Boccherini', '2024-10-10 17:45:06', '7707180672616', 1, 1),
(5828, 4, 'Toallero De Manos Plast-Grifos', 'Toallero De Manos Plast-Grifos', 1, '10499.00', '19.00', '15000.00', 'Toallero De Manos Plast-Grifos', '2024-10-10 17:45:06', '7700031023223', 1, 1),
(5829, 4, 'Toma Aereo Relco 10a Pequeño', 'Toma Aereo Relco 10a Pequeño', 11, '2100.00', '19.00', '3000.00', 'Toma Aereo Relco 10a Pequeño', '2024-10-10 17:45:06', '1742', 1, 1),
(5830, 4, 'Toma Aereo Relco Grande 15a', 'Toma Aereo Relco Grande 15a', 5, '3749.00', '19.00', '5000.00', 'Toma Aereo Relco Grande 15a', '2024-10-10 17:45:06', '1743', 1, 1),
(5831, 4, 'Toma Blanco Sobreponer', 'Toma Blanco Sobreponer', 3, '2800.00', '19.00', '4000.00', 'Toma Blanco Sobreponer', '2024-10-10 17:45:06', '6973653174616', 1, 1),
(5832, 4, 'Toma Corriente Doble Vato', 'Toma Corriente Doble Vato', 2, '4200.00', '19.00', '6500.00', 'Toma Corriente Doble Vato', '2024-10-10 17:45:06', '7861145812070', 1, 1),
(5833, 4, 'Toma Doble + 2 USB Gris Linea Luxor Intercambiable Sin Tapa', 'Toma Doble + 2 USB Gris Linea Luxor Intercambiable Sin Tapa', 0, '24050.00', '19.00', '29000.00', 'Toma Doble + 2 USB Gris Linea Luxor Intercambiable Sin Tapa', '2024-10-10 17:45:06', '6971580773254', 1, 1),
(5834, 4, 'Toma Doble + 2 Usb Gris Linea Luxor Intercambiable Sin Tapa', 'Toma Doble + 2 Usb Gris Linea Luxor Intercambiable Sin Tapa', 1, '24050.00', '19.00', '29000.00', 'Toma Doble + 2 Usb Gris Linea Luxor Intercambiable Sin Tapa', '2024-10-10 17:45:06', '6971580774589', 1, 1),
(5835, 4, 'Toma Doble Incrustar Blanco Uduke', 'Toma Doble Incrustar Blanco Uduke', 9, '1279.00', '19.00', '2000.00', 'Toma Doble Incrustar Blanco Uduke', '2024-10-10 17:45:06', '6973653174586', 1, 1),
(5836, 4, 'Toma Doble Incrustar Linea BK Uduke', 'Toma Doble Incrustar Linea BK Uduke', 7, '3250.00', '19.00', '4600.00', 'Toma Doble Incrustar Linea BK Uduke', '2024-10-10 17:45:06', '6973653171196', 1, 1),
(5837, 4, 'Toma Doble Incrustar Negro Línea BT HT20722', 'Toma Doble Incrustar Negro Línea BT HT20722', 8, '3000.00', '19.00', '4400.00', 'Toma Doble Incrustar Negro Línea BT HT20722', '2024-10-10 17:45:06', '6973877765287', 1, 1),
(5838, 4, 'Toma Doble Linea Luxor Blanca Intercambiable Sin Tapa', 'Toma Doble Linea Luxor Blanca Intercambiable Sin Tapa', 0, '4150.00', '19.00', '5500.00', 'Toma Doble Linea Luxor Blanca Intercambiable Sin Tapa', '2024-10-10 17:45:06', '6971580773124', 1, 1),
(5839, 4, 'Toma Doble Sobreponer Beige Uduke', 'Toma Doble Sobreponer Beige Uduke', 5, '1600.00', '19.00', '2600.00', 'Toma Doble Sobreponer Beige Uduke', '2024-10-10 17:45:06', '6973653174623', 1, 1),
(5840, 4, 'Toma Industrial Negra', 'Toma Industrial Negra', 1, '3759.00', '19.00', '5000.00', 'Toma Industrial Negra', '2024-10-10 17:45:06', '37', 1, 1),
(5841, 4, 'Toma Multiple', 'Toma Multiple', 0, '8000.00', '19.00', '10500.00', 'Toma Multiple', '2024-10-10 17:45:06', '7453038000581', 1, 1),
(5842, 4, 'Toma Plano Blanco', 'Toma Plano Blanco', 4, '1332.00', '19.00', '2000.00', 'Toma Plano Blanco', '2024-10-10 17:45:06', '39', 1, 1),
(5843, 4, 'Toma Salida Coaxial Boccherini', 'Toma Salida Coaxial Boccherini', 8, '3699.00', '19.00', '5300.00', 'Toma Salida Coaxial Boccherini', '2024-10-10 17:45:06', '7707180673811', 1, 1),
(5844, 4, 'Toma Sencillo + 2 USB Blanco Incrustar Uduke', 'Toma Sencillo + 2 USB Blanco Incrustar Uduke', 2, '10400.00', '19.00', '14000.00', 'Toma Sencillo + 2 USB Blanco Incrustar Uduke', '2024-10-10 17:45:06', '6973877762965', 1, 1),
(5845, 4, 'Toma Sencillo Beige', 'Toma Sencillo Beige', 8, '1272.00', '19.00', '1900.00', 'Toma Sencillo Beige', '2024-10-10 17:45:06', '6973877760046', 1, 1),
(5846, 4, 'Toma Sencillo Incrustar Blanco HT20681', 'Toma Sencillo Incrustar Blanco HT20681', 8, '3150.00', '19.00', '4400.00', 'Toma Sencillo Incrustar Blanco HT20681', '2024-10-10 17:45:06', '6973653174692', 1, 1),
(5847, 4, 'Toma Swiche Blanco Linea Luxor Intercambiable Sin Tapa V9018', 'Toma Swiche Blanco Linea Luxor Intercambiable Sin Tapa V9018', 4, '3900.00', '19.00', '6000.00', 'Toma Swiche Blanco Linea Luxor Intercambiable Sin Tapa V9018', '2024-10-10 17:45:06', '6971580773131', 1, 1),
(5848, 4, 'Toma Swiche Intercambiable Grin Sin Tapa V3018 Luxor', 'Toma Swiche Intercambiable Grin Sin Tapa V3018 Luxor', 9, '4200.00', '19.00', '6000.00', 'Toma Swiche Intercambiable Grin Sin Tapa V3018 Luxor', '2024-10-10 17:45:06', '6971580773223', 1, 1),
(5849, 4, 'Toma Swiche Mixto Incrustar Blanco Línea BK HT20341', 'Toma Swiche Mixto Incrustar Blanco Línea BK HT20341', 6, '4300.00', '19.00', '6500.00', 'Toma Swiche Mixto Incrustar Blanco Línea BK HT20341', '2024-10-10 17:45:06', '6973653174548', 1, 1),
(5850, 4, 'Toma Swiche Mixto Plus Incrustar Blanco Línea BK HT20682', 'Toma Swiche Mixto Plus Incrustar Blanco Línea BK HT20682', 6, '3550.00', '19.00', '5200.00', 'Toma Swiche Mixto Plus Incrustar Blanco Línea BK HT20682', '2024-10-10 17:45:06', '6973653174708', 1, 1),
(5851, 4, 'Toma TV + LAN Blanca -  Linea Luxor Intercambiale Sin Tapa', 'Toma TV + LAN Blanca -  Linea Luxor Intercambiale Sin Tapa', 3, '3950.00', '19.00', '5500.00', 'Toma TV + LAN Blanca -  Linea Luxor Intercambiale Sin Tapa', '2024-10-10 17:45:06', '6971580773155', 1, 1),
(5852, 4, 'Tomacorriente Doble Horizontal Boccherini', 'Tomacorriente Doble Horizontal Boccherini', 7, '6000.00', '19.00', '7800.00', 'Tomacorriente Doble Horizontal Boccherini', '2024-10-10 17:45:06', '7707180674382', 1, 1),
(5853, 4, 'Tomacorriente Doble Vertical Boccherini', 'Tomacorriente Doble Vertical Boccherini', 0, '4864.00', '19.00', '7000.00', 'Tomacorriente Doble Vertical Boccherini', '2024-10-10 17:45:06', '7707180674344', 1, 1),
(5854, 4, 'Tomacorriente Sencillo Boccherini', 'Tomacorriente Sencillo Boccherini', 11, '3423.00', '19.00', '5000.00', 'Tomacorriente Sencillo Boccherini', '2024-10-10 17:45:06', '7707180674337', 1, 1),
(5855, 4, 'Tomacorriente Triple Boccherini', 'Tomacorriente Triple Boccherini', 0, '6899.00', '19.00', '9900.00', 'Tomacorriente Triple Boccherini', '2024-10-10 17:45:06', '7707180674368', 1, 1),
(5856, 4, 'Tope Resorte', 'Tope Resorte', 13, '2000.00', '19.00', '3000.00', 'Tope Resorte', '2024-10-10 17:45:06', '18', 1, 1),
(5857, 4, 'Tornillo Cabeza Lenteja 8x1/2 Punta Aguda', 'Tornillo Cabeza Lenteja 8x1/2 Punta Aguda', 115, '28.00', '19.00', '50.00', 'Tornillo Cabeza Lenteja 8x1/2 Punta Aguda', '2024-10-10 17:45:06', '50929', 1, 1),
(5858, 4, 'Tornillo Cabeza Pan 12x 2', 'Tornillo Cabeza Pan 12x 2', 958, '170.00', '19.00', '250.00', 'Tornillo Cabeza Pan 12x 2', '2024-10-10 17:45:06', '98637', 1, 1),
(5859, 4, 'Tornillo Cisterna Baño', 'Tornillo Cisterna Baño', 44, '647.00', '19.00', '1000.00', 'Tornillo Cisterna Baño', '2024-10-10 17:45:06', '8', 1, 1),
(5860, 4, 'Tornillo Drywall 6x1', 'Tornillo Drywall 6x1', 833, '28.00', '19.00', '50.00', 'Tornillo Drywall 6x1', '2024-10-10 17:45:06', '50293', 1, 1),
(5861, 4, 'Tornillo Drywall 6x1 1/2', 'Tornillo Drywall 6x1 1/2', 807, '35.00', '19.00', '50.00', 'Tornillo Drywall 6x1 1/2', '2024-10-10 17:45:06', '50296', 1, 1),
(5862, 4, 'Tornillo Drywall 6x1 1/4', 'Tornillo Drywall 6x1 1/4', 934, '35.00', '19.00', '50.00', 'Tornillo Drywall 6x1 1/4', '2024-10-10 17:45:06', '10020', 1, 1),
(5863, 4, 'Tornillo Drywall 6x2', 'Tornillo Drywall 6x2', 970, '50.00', '19.00', '100.00', 'Tornillo Drywall 6x2', '2024-10-10 17:45:06', '50295', 1, 1),
(5864, 4, 'Tornillo Drywall 8x 2 1/2', 'Tornillo Drywall 8x 2 1/2', 9964, '71.00', '19.00', '100.00', 'Tornillo Drywall 8x 2 1/2', '2024-10-10 17:45:06', '50286', 1, 1),
(5865, 4, 'Tornillo Lamina Avellan 12x2', 'Tornillo Lamina Avellan 12x2', 965, '140.00', '19.00', '200.00', 'Tornillo Lamina Avellan 12x2', '2024-10-10 17:45:06', '98756', 1, 1),
(5866, 4, 'Tornillo Punta Broca | Estructura PVC', 'Tornillo Punta Broca | Estructura PVC', 3550, '25.00', '19.00', '50.00', 'Tornillo Punta Broca | Estructura PVC', '2024-10-10 17:45:06', 'T2', 1, 1),
(5867, 4, 'Tornillo Punta Fina | Estructura PVC', 'Tornillo Punta Fina | Estructura PVC', 1425, '25.00', '19.00', '40.00', 'Tornillo Punta Fina | Estructura PVC', '2024-10-10 17:45:06', 'T1', 1, 1),
(5868, 4, 'Torx Industrial Juego 9 Piezas Tht106392', 'Torx Industrial Juego 9 Piezas Tht106392', 1, '17300.00', '19.00', '24500.00', 'Torx Industrial Juego 9 Piezas Tht106392', '2024-10-10 17:45:06', '6925582175196', 1, 1),
(5869, 4, 'Tubo 2 Sanitario Amarillo Arket', 'Tubo 2 Sanitario Amarillo Arket', 3, '23253.00', '19.00', '33300.00', 'Tubo 2 Sanitario Amarillo Arket', '2024-10-10 17:45:06', '64857', 1, 1),
(5870, 4, 'Tubo 3 Sanitario Amarillo Arket', 'Tubo 3 Sanitario Amarillo Arket', 0, '34097.00', '19.00', '48800.00', 'Tubo 3 Sanitario Amarillo Arket', '2024-10-10 17:45:06', '12548', 1, 1),
(5871, 4, 'Tubo 4 Sanitario Amarillo Arket', 'Tubo 4 Sanitario Amarillo Arket', 0, '49992.00', '19.00', '71500.00', 'Tubo 4 Sanitario Amarillo Arket', '2024-10-10 17:45:06', '35412', 1, 1),
(5872, 4, 'Tubo Conduit 1', 'Tubo Conduit 1', 200, '5500.00', '19.00', '8800.00', 'Tubo Conduit 1', '2024-10-10 17:45:06', '40824', 1, 1),
(5873, 4, 'Tubo Conduit 1/2', 'Tubo Conduit 1/2', 112, '2200.00', '19.00', '3800.00', 'Tubo Conduit 1/2', '2024-10-10 17:45:06', '40823', 1, 1),
(5874, 4, 'Tubo Cortinero 1/2¨ Cafe', 'Tubo Cortinero 1/2¨ Cafe', 2, '16881.00', '19.00', '24200.00', 'Tubo Cortinero 1/2¨ Cafe', '2024-10-10 17:45:06', '813281', 1, 1),
(5875, 4, 'Tubo Cortinero 3/4 Cafe', 'Tubo Cortinero 3/4 Cafe', 3, '23186.00', '19.00', '33200.00', 'Tubo Cortinero 3/4 Cafe', '2024-10-10 17:45:06', '813261', 1, 1),
(5876, 4, 'Tubo Cortinero 3/4 Cafe X 3mts', 'Tubo Cortinero 3/4 Cafe X 3mts', 1, '11620.00', '19.00', '16600.00', 'Tubo Cortinero 3/4 Cafe X 3mts', '2024-10-10 17:45:06', '813262', 1, 1),
(5877, 4, 'Tubo De Presion Blanco De 1/2 Arket', 'Tubo De Presion Blanco De 1/2 Arket', 60, '7500.00', '19.00', '9500.00', 'Tubo De Presion Blanco De 1/2 Arket', '2024-10-10 17:45:06', '125482', 1, 1),
(5878, 4, 'Tubo Regadera Boccherini', 'Tubo Regadera Boccherini', 19, '5953.00', '19.00', '11000.00', 'Tubo Regadera Boccherini', '2024-10-10 17:45:06', '7707180671275', 1, 1),
(5879, 4, 'Tubo Sanitario 6 Pesado', 'Tubo Sanitario 6 Pesado', 0, '153000.00', '19.00', '156000.00', 'Tubo Sanitario 6 Pesado', '2024-10-10 17:45:06', '1646', 1, 1),
(5880, 4, 'Tubo Sanitario Semipesado 2', 'Tubo Sanitario Semipesado 2', 12, '18900.00', '19.00', '25000.00', 'Tubo Sanitario Semipesado 2', '2024-10-10 17:45:06', '692IA', 1, 1),
(5881, 4, 'Tubo Sanitario Semipesado 3', 'Tubo Sanitario Semipesado 3', 8, '29000.00', '19.00', '38000.00', 'Tubo Sanitario Semipesado 3', '2024-10-10 17:45:06', '691IA', 1, 1),
(5882, 4, 'Tubo Sanitario Semipesado 4', 'Tubo Sanitario Semipesado 4', 15, '38800.00', '19.00', '50500.00', 'Tubo Sanitario Semipesado 4', '2024-10-10 17:45:06', '689IA', 1, 1),
(5883, 4, 'Tubo Sanitaro  2 Semipesado', 'Tubo Sanitaro  2 Semipesado', 16, '18900.00', '19.00', '25000.00', 'Tubo Sanitaro  2 Semipesado', '2024-10-10 17:45:06', '692', 1, 1),
(5884, 4, 'Tubo Sanitaro  3 Semipesado', 'Tubo Sanitaro  3 Semipesado', 20, '29000.00', '19.00', '38000.00', 'Tubo Sanitaro  3 Semipesado', '2024-10-10 17:45:06', '691', 1, 1),
(5885, 4, 'Tubo Sanitaro  4 Semipesado', 'Tubo Sanitaro  4 Semipesado', 15, '38800.00', '19.00', '50500.00', 'Tubo Sanitaro  4 Semipesado', '2024-10-10 17:45:06', '689', 1, 1),
(5886, 4, 'Tuvo Porta Papel H Plast Grifos', 'Tuvo Porta Papel H Plast Grifos', 5, '968.00', '19.00', '1500.00', 'Tuvo Porta Papel H Plast Grifos', '2024-10-10 17:45:06', '7700032005624', 1, 1),
(5887, 4, 'Und Amarra Plastica Blanca 3.6x300', 'Und Amarra Plastica Blanca 3.6x300', 100, '115.00', '19.00', '250.00', 'Und Amarra Plastica Blanca 3.6x300', '2024-10-10 17:45:07', '990199008', 1, 1),
(5888, 4, 'Und Broca Para Concreto 3/8 Boccherini', 'Und Broca Para Concreto 3/8 Boccherini', 0, '952.00', '19.00', '2000.00', 'Und Broca Para Concreto 3/8 Boccherini', '2024-10-10 17:45:07', '7707180679739', 1, 1),
(5889, 4, 'Unidad De Amarras Plasticas Blanca', 'Unidad De Amarras Plasticas Blanca', 20, '60.00', '19.00', '200.00', 'Unidad De Amarras Plasticas Blanca', '2024-10-10 17:45:07', '42', 1, 1),
(5890, 4, 'Union Cpvc 1/2 Gerfor', 'Union Cpvc 1/2 Gerfor', 20, '778.00', '19.00', '1200.00', 'Union Cpvc 1/2 Gerfor', '2024-10-10 17:45:07', '45', 1, 1),
(5891, 4, 'Union Electrica Conduit 1/2', 'Union Electrica Conduit 1/2', 12, '440.00', '19.00', '700.00', 'Union Electrica Conduit 1/2', '2024-10-10 17:45:07', '43', 1, 1),
(5892, 4, 'Union Lisa De Presion 1/2 G-Plast', 'Union Lisa De Presion 1/2 G-Plast', 25, '301.00', '19.00', '500.00', 'Union Lisa De Presion 1/2 G-Plast', '2024-10-10 17:45:07', '49', 1, 1),
(5893, 4, 'Union Presion 1', 'Union Presion 1', 5, '576.00', '19.00', '1000.00', 'Union Presion 1', '2024-10-10 17:45:07', '121043', 1, 1),
(5894, 4, 'Union Saniratia 1 1/2', 'Union Saniratia 1 1/2', 55, '650.00', '19.00', '1000.00', 'Union Saniratia 1 1/2', '2024-10-10 17:45:07', '162123', 1, 1),
(5895, 4, 'Union Sanitaria 2', 'Union Sanitaria 2', 55, '800.00', '19.00', '1300.00', 'Union Sanitaria 2', '2024-10-10 17:45:07', '162124', 1, 1),
(5896, 4, 'Union Sanitaria 3 Amarilla Arket', 'Union Sanitaria 3 Amarilla Arket', 3, '2078.00', '19.00', '3000.00', 'Union Sanitaria 3 Amarilla Arket', '2024-10-10 17:45:07', '122082', 1, 1),
(5897, 4, 'Union Sanitaria 4 Amarillo Arket', 'Union Sanitaria 4 Amarillo Arket', 3, '4153.00', '19.00', '6000.00', 'Union Sanitaria 4 Amarillo Arket', '2024-10-10 17:45:07', '122083', 1, 1),
(5898, 4, 'Universal Lisa De 1/2 Pvc', 'Universal Lisa De 1/2 Pvc', 4, '1450.00', '19.00', '2200.00', 'Universal Lisa De 1/2 Pvc', '2024-10-10 17:45:07', '6973877760596', 1, 1),
(5899, 4, 'Valvula  De Entrada Sanitario', 'Valvula  De Entrada Sanitario', 2, '13250.00', '19.00', '19000.00', 'Valvula  De Entrada Sanitario', '2024-10-10 17:45:07', '2517', 1, 1),
(5900, 4, 'Valvula  Plastica Pozuelo De 2', 'Valvula  Plastica Pozuelo De 2', 3, '900.00', '19.00', '1500.00', 'Valvula  Plastica Pozuelo De 2', '2024-10-10 17:45:07', '2526', 1, 1),
(5901, 4, 'Valvula Bola Bronce 1/2', 'Valvula Bola Bronce 1/2', 3, '10900.00', '19.00', '16000.00', 'Valvula Bola Bronce 1/2', '2024-10-10 17:45:07', '2535', 1, 1),
(5902, 4, 'Valvula De Llenado Eco', 'Valvula De Llenado Eco', 3, '18000.00', '19.00', '26000.00', 'Valvula De Llenado Eco', '2024-10-10 17:45:07', '96542', 1, 1),
(5903, 4, 'Valvula De Llenado Mercury', 'Valvula De Llenado Mercury', 0, '18000.00', '19.00', '24000.00', 'Valvula De Llenado Mercury', '2024-10-10 17:45:07', '7707692866770', 1, 1),
(5904, 4, 'Valvula De Salida Rio Plast', 'Valvula De Salida Rio Plast', 2, '4823.00', '19.00', '7000.00', 'Valvula De Salida Rio Plast', '2024-10-10 17:45:07', '320010105', 1, 1),
(5905, 4, 'Valvula Lavamanos', 'Valvula Lavamanos', 4, '3600.00', '19.00', '5004.00', 'Valvula Lavamanos', '2024-10-10 17:45:07', '7700032208728', 1, 1),
(5906, 4, 'Valvula Llenado Universal Plus Grival', 'Valvula Llenado Universal Plus Grival', 5, '35000.00', '19.00', '42000.00', 'Valvula Llenado Universal Plus Grival', '2024-10-10 17:45:07', '24699', 1, 1),
(5907, 4, 'Valvula Pesada Pvc Rosca Grival', 'Valvula Pesada Pvc Rosca Grival', 0, '4073.00', '19.00', '5900.00', 'Valvula Pesada Pvc Rosca Grival', '2024-10-10 17:45:07', '797553331', 1, 1),
(5908, 4, 'Valvula Plastica Pozuelo 3', 'Valvula Plastica Pozuelo 3', 6, '2360.00', '19.00', '3500.00', 'Valvula Plastica Pozuelo 3', '2024-10-10 17:45:07', '2527', 1, 1),
(5909, 4, 'Valvula Soldable De 1/2 Boccherini', 'Valvula Soldable De 1/2 Boccherini', 1, '2400.00', '19.00', '4000.00', 'Valvula Soldable De 1/2 Boccherini', '2024-10-10 17:45:07', '7707180676676', 1, 1),
(5910, 4, 'Valvula Tanque Alto Con Flotador Gerfor', 'Valvula Tanque Alto Con Flotador Gerfor', 0, '26190.00', '19.00', '33000.00', 'Valvula Tanque Alto Con Flotador Gerfor', '2024-10-10 17:45:07', '7707015311383', 1, 1),
(5911, 4, 'Vastago Cart Ceram Largo Grival', 'Vastago Cart Ceram Largo Grival', 3, '24794.00', '19.00', '31000.00', 'Vastago Cart Ceram Largo Grival', '2024-10-10 17:45:07', '10', 1, 1),
(5912, 4, 'Ventilador 2en1 Samurai AirPR NAA', 'Ventilador 2en1 Samurai AirPR NAA', 0, '145100.00', '19.00', '174000.00', 'Ventilador 2en1 Samurai AirPR NAA', '2024-10-10 17:45:07', '7702073314109', 1, 1),
(5913, 4, 'Ventilador 2en1 Samurai TS30 NAC', 'Ventilador 2en1 Samurai TS30 NAC', 0, '172697.00', '19.00', '210000.00', 'Ventilador 2en1 Samurai TS30 NAC', '2024-10-10 17:45:07', '7702073771001', 1, 1),
(5914, 4, 'Ventilador Negro 3En1', 'Ventilador Negro 3En1', 2, '0.00', '19.00', '135000.00', 'Ventilador Negro 3En1', '2024-10-10 17:45:07', '1854', 1, 1),
(5915, 4, 'Ventilador Samurai Silence Force Plus Negro 4606', 'Ventilador Samurai Silence Force Plus Negro 4606', 0, '177180.00', '19.00', '225000.00', 'Ventilador Samurai Silence Force Plus Negro 4606', '2024-10-10 17:45:07', '7702073774606', 1, 1),
(5916, 4, 'Ventilador Samurai Turbo Power Blanco', 'Ventilador Samurai Turbo Power Blanco', 1, '165099.00', '19.00', '180000.00', 'Ventilador Samurai Turbo Power Blanco', '2024-10-10 17:45:07', '7702073291004', 1, 1),
(5917, 4, 'Ventilador Samurai Turbo Power Negro', 'Ventilador Samurai Turbo Power Negro', 1, '165099.00', '19.00', '175000.00', 'Ventilador Samurai Turbo Power Negro', '2024-10-10 17:45:07', '7702073291011', 1, 1),
(5918, 4, 'Viguetas PVC', 'Viguetas PVC', 55, '0.00', '19.00', '3800.00', 'Viguetas PVC', '2024-10-10 17:45:07', '124578', 1, 1),
(5919, 4, 'Volteador De Aluminio Truper', 'Volteador De Aluminio Truper', 2, '12138.00', '19.00', '15800.00', 'Volteador De Aluminio Truper', '2024-10-10 17:45:07', '7501206634769', 1, 1),
(5920, 4, 'Y En Conector Boccherini', 'Y En Conector Boccherini', 0, '5800.00', '19.00', '8500.00', 'Y En Conector Boccherini', '2024-10-10 17:45:07', '7707180679295', 1, 1),
(5921, 4, 'Zimbra Plastica Con Tinta 30m', 'Zimbra Plastica Con Tinta 30m', 4, '16000.00', '19.00', '21600.00', 'Zimbra Plastica Con Tinta 30m', '2024-10-10 17:45:07', '7501206674710', 1, 1),
(5922, 4, 'freidora de aire 4 lt oster', 'freidora de aire 4 lt oster', 2, '155000.00', '19.00', '255000.00', 'freidora de aire 4 lt oster', '2024-10-10 17:45:07', '53891168717', 1, 1),
(5923, 4, 'garrucha 1¨1/2 pulgadas75kg', 'garrucha 1¨1/2 pulgadas75kg', 1, '18000.00', '19.00', '13000.00', 'garrucha 1¨1/2 pulgadas75kg', '2024-10-10 17:45:07', '7501206695142', 1, 1),
(5924, 4, 'garrucha 2 pulgadas 90kg', 'garrucha 2 pulgadas 90kg', 1, '9300.00', '19.00', '24500.00', 'garrucha 2 pulgadas 90kg', '2024-10-10 17:45:07', '7501206695159', 1, 1),
(5925, 4, 'gaveta apilable de plastico 25x17x11 cm truper 10890', 'gaveta apilable de plastico 25x17x11 cm truper 10890', 12, '7359.00', '19.00', '10000.00', 'gaveta apilable de plastico 25x17x11 cm truper 10890', '2024-10-10 17:45:07', '7506240646730', 1, 1),
(5926, 4, 'juego de 5 pinceles 24740', 'juego de 5 pinceles 24740', 3, '6200.00', '19.00', '8800.00', 'juego de 5 pinceles 24740', '2024-10-10 17:45:07', '7506240619079', 1, 1),
(5927, 4, 'malla polisombra al 80%  100mts', 'malla polisombra al 80%  100mts', 2, '422400.00', '19.00', '549000.00', 'malla polisombra al 80%  100mts', '2024-10-10 17:45:07', '849017', 1, 1),
(5928, 4, 'piragua media caña o media luna color natural  6mts', 'piragua media caña o media luna color natural  6mts', 20, '11536.00', '19.00', '15500.00', 'piragua media caña o media luna color natural  6mts', '2024-10-10 17:45:07', '38896', 1, 1),
(5929, 4, 'textuco gln x 6kl corona', 'textuco gln x 6kl corona', 1, '15825.00', '19.00', '23000.00', 'textuco gln x 6kl corona', '2024-10-10 17:45:07', '7705389000827', 1, 1),
(5930, 6, 'yee lavadora dicol', 'yee lavadora dicol', 4, '3788.00', '19.00', '5600.00', 'yee lavadora dicol', '2024-10-10 17:45:07', '7702217091095', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `attempt_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promociones`
--

CREATE TABLE `promociones` (
  `id` int NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `descuento` decimal(5,2) DEFAULT NULL,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `proveedores`
--

INSERT INTO `proveedores` (`id`, `user_id`, `nombre`, `email`, `telefono`, `direccion`, `created_at`) VALUES
(3, 4, 'ANDINA DE MATERIALES SAS', 'email@email.com', '1231231231', 'Colombia', '2024-09-28 23:02:22'),
(4, 4, 'BREYNNER ANDREY GOMEZ BOLIVAR', 'email@email.com', '1231231231', 'Colombia', '2024-09-28 23:02:57'),
(5, 4, 'CLAUDIA MILENA BARRETO CAMACHO', 'email@email.com', '1231231231', 'Colombia', '2024-09-28 23:03:19'),
(6, 4, 'COLOMBIANA DE COMERCIO S.A.', 'email@email.com', '1231231231', 'Colombia', '2024-09-28 23:03:43'),
(7, 4, 'FERREDISTARCO SAS', 'email@email.com', '1231231231', 'Colombia', '2024-09-28 23:04:01'),
(8, 4, 'INTERLUJOS', 'email@email.com', '1231231', 'Colombia', '2024-09-28 23:04:20'),
(9, 4, 'LA DISTRIBUIDORA MARACAIBO', 'email@email.com', '1231231', 'Colombia', '2024-09-28 23:04:37'),
(10, 4, 'TRUPER STORE SAS', 'email@email.com', '123123123', 'Colombia', '2024-09-28 23:04:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `empresa_id` int DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `remember_token` varchar(255) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nombre`, `empresa_id`, `email`, `password`, `fecha_creacion`, `fecha_actualizacion`, `remember_token`, `token_expires`) VALUES
(4, NULL, 1, 'johan_c6969@hotmail.com', '$2y$10$L1GkH1MvzY8242ESViywUO2.Iu2neydQFECtCphXu.GSLO/JEr2eS', '2024-09-26 14:13:39', '2024-09-26 14:13:39', '$2y$10$KfbQ.9BnD.frXkAnUWQD1OTfq.bHgin9Zv7ZVNfpSbbBWvH6tUGKe', '2024-11-15 21:35:01'),
(6, NULL, NULL, 'johan@gmail.com', '$2y$10$d2YguF0OIutsSkPQGR1y5.6Dou26knNOz3kHUIQ/g6x57VM7ZLW1y', '2024-10-12 14:02:52', '2024-10-12 14:02:52', '$2y$10$3HWHAwv776FAxKpN1lLN5OA/MoCQ5B/38OzPOZelCq.1w4EVRKS.2', '2024-11-11 14:14:24');

-- --------------------------------------------------------

--
-- Table structure for table `ventas`
--

CREATE TABLE `ventas` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `cliente_id` int DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `descuento` decimal(5,2) DEFAULT '0.00',
  `metodo_pago` varchar(50) NOT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  `numero_factura` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `venta_detalles`
--

CREATE TABLE `venta_detalles` (
  `id` int NOT NULL,
  `venta_id` int DEFAULT NULL,
  `producto_id` int DEFAULT NULL,
  `cantidad` int NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `departamento_id` (`departamento_id`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `empresa_ibfk_1` (`empresa_id`);

--
-- Indexes for table `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `creditos`
--
ALTER TABLE `creditos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venta_id` (`venta_id`);

--
-- Indexes for table `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `egresos`
--
ALTER TABLE `egresos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ingresos`
--
ALTER TABLE `ingresos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_codigo_barras` (`codigo_barras`),
  ADD KEY `fk_departamento_id` (`departamento_id`),
  ADD KEY `fk_categoria_id` (`categoria_id`),
  ADD KEY `fk_user_id` (`user_id`),
  ADD KEY `idx_codigo_barras_user_id` (`codigo_barras`,`user_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `promociones`
--
ALTER TABLE `promociones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_empresa_id` (`empresa_id`);

--
-- Indexes for table `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_factura` (`numero_factura`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indexes for table `venta_detalles`
--
ALTER TABLE `venta_detalles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venta_id` (`venta_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cotizaciones`
--
ALTER TABLE `cotizaciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `creditos`
--
ALTER TABLE `creditos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `egresos`
--
ALTER TABLE `egresos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ingresos`
--
ALTER TABLE `ingresos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5945;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promociones`
--
ALTER TABLE `promociones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `venta_detalles`
--
ALTER TABLE `venta_detalles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `categorias`
--
ALTER TABLE `categorias`
  ADD CONSTRAINT `categorias_ibfk_1` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `configuracion`
--
ALTER TABLE `configuracion`
  ADD CONSTRAINT `configuracion_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `creditos`
--
ALTER TABLE `creditos`
  ADD CONSTRAINT `creditos_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`);

--
-- Constraints for table `egresos`
--
ALTER TABLE `egresos`
  ADD CONSTRAINT `egresos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ingresos`
--
ALTER TABLE `ingresos`
  ADD CONSTRAINT `ingresos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventario`
--
ALTER TABLE `inventario`
  ADD CONSTRAINT `fk_categoria_id` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_departamento_id` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventario_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD CONSTRAINT `login_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `promociones`
--
ALTER TABLE `promociones`
  ADD CONSTRAINT `promociones_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `proveedores`
--
ALTER TABLE `proveedores`
  ADD CONSTRAINT `proveedores_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`);

--
-- Constraints for table `venta_detalles`
--
ALTER TABLE `venta_detalles`
  ADD CONSTRAINT `venta_detalles_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  ADD CONSTRAINT `venta_detalles_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `inventario` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
