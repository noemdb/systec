-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 07-11-2023 a las 16:00:21
-- Versión del servidor: 8.0.34-0ubuntu0.22.04.1
-- Versión de PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gabosys`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `age` int DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `profile` varchar(50) DEFAULT NULL,
  `rol` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `firstname`, `lastname`, `username`, `email`, `age`, `country`, `profile`, `rol`, `password`, `status`) VALUES
(2, 'noe', 'dominguez', 'noemdb', 'noemdb@gmail.com', 41, 'Venezuela', 'Ing', 'Programador', '$2y$10$QhAz/ds0ES9AlTB/tVU/aOlcuKbIKd96HNXiNnyoz6wN.O4aZK6oG', 1),
(3, 'jose', 'perez', 'jperez', 'jperez@gmail.com', 12, 'Venezuela', 'Profesor', 'Secretario', '$2y$10$lUzDkQKbg1US7W7rY/JK9eWhZ40hwM1.XyPFJNWR0AJ5c3uDkuQ7e', 1),
(4, 'Luis', 'Perez', 'lperez', 'lperez@sys.com', 40, 'Colombia', 'Maestro', 'Secretario', '$2y$10$qd9.cV4M43BR5hEF6Qtfp.MPnErBUotUlVqqqjkgyipk2IELvF2zC', 1),
(5, 'María', 'Perez', 'mperez', 'mperez@sys.com', 23, 'Venezuela', 'Literatura', 'Secretario', '$2y$10$TmJmLOBbLSF8W3CL5N3x0uFNLbqgFoI/cTPPqGRYpeeq34w.lKaDC', 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
