-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 30, 2024 at 12:39 PM
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
(10, 4, 'Consumidor Final', 'email@email.com', '3216371125', '2024-09-28 21:48:45', 'cc', '1234567890', 'Consumidor', 'Final', '.', 'Popayan', '190017'),
(11, 7, 'Consumidor Final', 'email@email.com', '1231231239', '2024-10-28 17:25:03', 'Cedula', '1048067754', 'Consumidor', 'Final', 'Final', 'Cauca', '190017');

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
  `nombre_empresa` varchar(255) DEFAULT NULL,
  `nit` varchar(20) DEFAULT NULL,
  `regimen_fiscal` varchar(100) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo_contacto` varchar(100) DEFAULT NULL,
  `prefijo_factura` varchar(4) DEFAULT NULL,
  `numero_inicial` int DEFAULT '1',
  `numero_final` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `empresas`
--

INSERT INTO `empresas` (`id`, `nombre_empresa`, `nit`, `regimen_fiscal`, `direccion`, `telefono`, `correo_contacto`, `prefijo_factura`, `numero_inicial`, `numero_final`, `created_at`, `updated_at`) VALUES
(4, 'Johan Rengifo', '1048067754-9', 'Simplificado', 'Cra 2 #3A-18', '3116035791', 'johanrengifo78@gmail.com', 'POS', 1, 3000, '2024-10-30 04:49:49', '2024-10-30 04:53:37'),
(5, 'Juan Rengifo', '11205733', 'Simplificado', 'Popayan', '3116371125', 'j@gmail.com', 'POS', 1, 30000, '2024-10-30 04:55:24', '2024-10-30 04:55:24');

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
(5945, 4, 'Producto A', 'Producto A', 20, '15000.00', '19.00', '17850.00', 'Producto A', '2024-10-26 15:42:37', '650240000010', 1, 1),
(5946, 4, 'Producto B', 'Producto B', 3, '5000.00', '19.00', '7500.00', 'Producto B', '2024-10-26 18:13:48', '2', 1, 1),
(5947, 4, 'Producto C', 'Producto B', 4, '5000.00', '19.00', '7500.00', 'Producto B', '2024-10-26 18:13:48', '3', 1, 1),
(5948, 4, 'Producto D', 'Producto B', 5, '5000.00', '19.00', '7500.00', 'Producto B', '2024-10-26 18:13:48', '4', 1, 1),
(5949, 4, 'Producto E', 'Producto B', 5, '5000.00', '19.00', '7500.00', 'Producto B', '2024-10-26 18:13:48', '5', 1, 1);

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
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `login_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`id`, `user_id`, `ip_address`, `status`, `login_time`) VALUES
(1, 7, '127.0.0.1', 'success', '2024-10-29 22:41:13'),
(2, 7, '127.0.0.1', 'success', '2024-10-29 22:41:47'),
(3, 4, '127.0.0.1', 'success', '2024-10-29 22:54:54'),
(4, 4, '127.0.0.1', 'success', '2024-10-29 23:03:50'),
(5, 7, '127.0.0.1', 'success', '2024-10-29 23:54:48'),
(6, 4, '127.0.0.1', 'success', '2024-10-30 07:31:12');

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
(10, 4, 'TRUPER STORE SAS', 'email@email.com', '123123123', 'Colombia', '2024-09-28 23:04:59'),
(11, 4, 'ANDINA DE MATERIALES SAS', 'email@email.com', '1231231231', 'Colombia', '2024-10-26 20:40:09');

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
(4, 'Johan Rengifo', 4, 'johan_c6969@hotmail.com', '$2y$10$L1GkH1MvzY8242ESViywUO2.Iu2neydQFECtCphXu.GSLO/JEr2eS', '2024-09-26 14:13:39', '2024-09-26 14:13:39', '$2y$10$6VNzVdqfwNA5iTkC06AzT.FFuuXIrF8OHm/0muVh5wv6OcGztmsdy', '2024-11-28 22:54:54'),
(7, 'Johan', 5, 'johanrengifo78@gmail.com', '$2y$10$.K8n7RI3THgbjcsYos/5V.4qcERo6ybKsnGFXSVTLQUB9FT5lmtVW', '2024-10-28 16:35:25', '2024-10-28 16:35:25', '$2y$10$Y/OGvnW9saeLv0UD2IBpF.wsuMdIX1.ZDZdvmMUDS5PV.AKYHWxyy', '2024-11-28 22:41:13');

-- --------------------------------------------------------

--
-- Table structure for table `user_events`
--

CREATE TABLE `user_events` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `event_data` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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

--
-- Dumping data for table `ventas`
--

INSERT INTO `ventas` (`id`, `user_id`, `cliente_id`, `total`, `descuento`, `metodo_pago`, `fecha`, `numero_factura`) VALUES
(69, 4, 10, '7500.00', '0.00', 'efectivo', '2024-10-26 20:32:47', 'POS-000030');

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
-- Dumping data for table `venta_detalles`
--

INSERT INTO `venta_detalles` (`id`, `venta_id`, `producto_id`, `cantidad`, `precio_unitario`) VALUES
(91, 69, 5946, 1, '7500.00');

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
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `login_time` (`login_time`);

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
-- Indexes for table `user_events`
--
ALTER TABLE `user_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_type` (`event_type`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ingresos`
--
ALTER TABLE `ingresos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5950;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_events`
--
ALTER TABLE `user_events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `venta_detalles`
--
ALTER TABLE `venta_detalles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

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

-- Crear tablas necesarias
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100),
    empresa_id INT,
    remember_token VARCHAR(255),
    token_expires DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ... (resto de las tablas)
