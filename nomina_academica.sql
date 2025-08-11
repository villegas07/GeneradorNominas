-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-08-2025 a las 21:56:50
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `nomina_academica`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_unidades`
--

CREATE TABLE `catalogo_unidades` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `valor_base` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_factura`
--

CREATE TABLE `detalle_factura` (
  `id_detalle` int(11) NOT NULL,
  `id_factura` int(11) NOT NULL,
  `porcentaje_pago` enum('50%','100%') NOT NULL,
  `id_periodo` int(11) NOT NULL,
  `tipo_concepto` enum('Curso','Tesis de Grado','Proyecto','Seminario','Otro') NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `nombre_estudiante` varchar(100) DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `observacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente`
--

CREATE TABLE `docente` (
  `id_docente` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `identificacion` varchar(20) DEFAULT NULL,
  `nit` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente_unidad`
--

CREATE TABLE `docente_unidad` (
  `id_docente_unidad` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_unidad` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiante`
--

CREATE TABLE `estudiante` (
  `id_estudiante` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `identificacion` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `factura`
--

CREATE TABLE `factura` (
  `id_factura` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `total_pago` decimal(12,2) NOT NULL,
  `convenio` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `liquidacion`
--

CREATE TABLE `liquidacion` (
  `id_liquidacion` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_unidad` int(11) NOT NULL,
  `numero_estudiantes` int(11) DEFAULT 0,
  `valor_total` decimal(12,2) NOT NULL,
  `primer_pago` decimal(12,2) DEFAULT NULL,
  `segundo_pago` decimal(12,2) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `pago_inicial_pagado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago_factura`
--

CREATE TABLE `pago_factura` (
  `id_pago` int(11) NOT NULL,
  `id_factura` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `observacion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pais`
--

CREATE TABLE `pais` (
  `id_pais` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `periodo`
--

CREATE TABLE `periodo` (
  `id_periodo` int(11) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sede`
--

CREATE TABLE `sede` (
  `id_sede` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `id_pais` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidad_curricular`
--

CREATE TABLE `unidad_curricular` (
  `id_unidad` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `grupo` varchar(20) DEFAULT NULL,
  `valor` decimal(12,2) NOT NULL,
  `id_periodo` int(11) NOT NULL,
  `id_sede` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('admin','contabilidad','direccion','docente') NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `id_docente` int(11) DEFAULT NULL,
  `super_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `username`, `password_hash`, `rol`, `estado`, `id_docente`, `super_admin`) VALUES
(1, 'admin', '$2y$10$H7NBB7QWwk3mQUjVJ8WlFe5R2kUbXk7Plae.KfQRFhm1m7BfxB5iG', 'admin', 1, NULL, 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `catalogo_unidades`
--
ALTER TABLE `catalogo_unidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `detalle_factura`
--
ALTER TABLE `detalle_factura`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_factura` (`id_factura`),
  ADD KEY `id_periodo` (`id_periodo`);

--
-- Indices de la tabla `docente`
--
ALTER TABLE `docente`
  ADD PRIMARY KEY (`id_docente`),
  ADD UNIQUE KEY `identificacion` (`identificacion`);

--
-- Indices de la tabla `docente_unidad`
--
ALTER TABLE `docente_unidad`
  ADD PRIMARY KEY (`id_docente_unidad`),
  ADD KEY `id_docente` (`id_docente`),
  ADD KEY `id_unidad` (`id_unidad`);

--
-- Indices de la tabla `estudiante`
--
ALTER TABLE `estudiante`
  ADD PRIMARY KEY (`id_estudiante`),
  ADD UNIQUE KEY `identificacion` (`identificacion`);

--
-- Indices de la tabla `factura`
--
ALTER TABLE `factura`
  ADD PRIMARY KEY (`id_factura`),
  ADD KEY `id_docente` (`id_docente`);

--
-- Indices de la tabla `liquidacion`
--
ALTER TABLE `liquidacion`
  ADD PRIMARY KEY (`id_liquidacion`),
  ADD KEY `id_docente` (`id_docente`),
  ADD KEY `id_unidad` (`id_unidad`);

--
-- Indices de la tabla `pago_factura`
--
ALTER TABLE `pago_factura`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_factura` (`id_factura`);

--
-- Indices de la tabla `pais`
--
ALTER TABLE `pais`
  ADD PRIMARY KEY (`id_pais`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `periodo`
--
ALTER TABLE `periodo`
  ADD PRIMARY KEY (`id_periodo`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `sede`
--
ALTER TABLE `sede`
  ADD PRIMARY KEY (`id_sede`),
  ADD KEY `id_pais` (`id_pais`);

--
-- Indices de la tabla `unidad_curricular`
--
ALTER TABLE `unidad_curricular`
  ADD PRIMARY KEY (`id_unidad`),
  ADD KEY `id_periodo` (`id_periodo`),
  ADD KEY `id_sede` (`id_sede`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `id_docente` (`id_docente`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `catalogo_unidades`
--
ALTER TABLE `catalogo_unidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_factura`
--
ALTER TABLE `detalle_factura`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docente`
--
ALTER TABLE `docente`
  MODIFY `id_docente` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docente_unidad`
--
ALTER TABLE `docente_unidad`
  MODIFY `id_docente_unidad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estudiante`
--
ALTER TABLE `estudiante`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `factura`
--
ALTER TABLE `factura`
  MODIFY `id_factura` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `liquidacion`
--
ALTER TABLE `liquidacion`
  MODIFY `id_liquidacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pago_factura`
--
ALTER TABLE `pago_factura`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pais`
--
ALTER TABLE `pais`
  MODIFY `id_pais` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `periodo`
--
ALTER TABLE `periodo`
  MODIFY `id_periodo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sede`
--
ALTER TABLE `sede`
  MODIFY `id_sede` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `unidad_curricular`
--
ALTER TABLE `unidad_curricular`
  MODIFY `id_unidad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_factura`
--
ALTER TABLE `detalle_factura`
  ADD CONSTRAINT `detalle_factura_ibfk_1` FOREIGN KEY (`id_factura`) REFERENCES `factura` (`id_factura`),
  ADD CONSTRAINT `detalle_factura_ibfk_2` FOREIGN KEY (`id_periodo`) REFERENCES `periodo` (`id_periodo`);

--
-- Filtros para la tabla `docente_unidad`
--
ALTER TABLE `docente_unidad`
  ADD CONSTRAINT `docente_unidad_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id_docente`),
  ADD CONSTRAINT `docente_unidad_ibfk_2` FOREIGN KEY (`id_unidad`) REFERENCES `unidad_curricular` (`id_unidad`);

--
-- Filtros para la tabla `factura`
--
ALTER TABLE `factura`
  ADD CONSTRAINT `factura_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id_docente`);

--
-- Filtros para la tabla `liquidacion`
--
ALTER TABLE `liquidacion`
  ADD CONSTRAINT `liquidacion_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id_docente`),
  ADD CONSTRAINT `liquidacion_ibfk_2` FOREIGN KEY (`id_unidad`) REFERENCES `unidad_curricular` (`id_unidad`);

--
-- Filtros para la tabla `pago_factura`
--
ALTER TABLE `pago_factura`
  ADD CONSTRAINT `pago_factura_ibfk_1` FOREIGN KEY (`id_factura`) REFERENCES `factura` (`id_factura`);

--
-- Filtros para la tabla `sede`
--
ALTER TABLE `sede`
  ADD CONSTRAINT `sede_ibfk_1` FOREIGN KEY (`id_pais`) REFERENCES `pais` (`id_pais`);

--
-- Filtros para la tabla `unidad_curricular`
--
ALTER TABLE `unidad_curricular`
  ADD CONSTRAINT `unidad_curricular_ibfk_1` FOREIGN KEY (`id_periodo`) REFERENCES `periodo` (`id_periodo`),
  ADD CONSTRAINT `unidad_curricular_ibfk_2` FOREIGN KEY (`id_sede`) REFERENCES `sede` (`id_sede`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id_docente`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
