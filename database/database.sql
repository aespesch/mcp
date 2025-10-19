-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 19/10/2025 às 14:22
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u886965341_crypto`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `candle_day`
--
-- Criação: 19/10/2025 às 13:40
-- Última atualização: 19/10/2025 às 14:08
--

CREATE TABLE `candle_day` (
  `cndl_id` int(11) NOT NULL,
  `cndl_symbol_pair_id` int(11) NOT NULL,
  `cndl_date` date NOT NULL,
  `cndl_created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `candle_time`
--
-- Criação: 19/10/2025 às 13:40
-- Última atualização: 19/10/2025 às 14:08
--

CREATE TABLE `candle_time` (
  `cntm_id` bigint(20) NOT NULL,
  `cntm_candle_day_id` int(11) NOT NULL,
  `cntm_hour` smallint(6) DEFAULT NULL,
  `cntm_open_price` decimal(20,8) NOT NULL,
  `cntm_high_price` decimal(20,8) NOT NULL,
  `cntm_low_price` decimal(20,8) NOT NULL,
  `cntm_close_price` decimal(20,8) NOT NULL,
  `cntm_volume` decimal(20,8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `symbol`
--
-- Criação: 19/10/2025 às 13:40
-- Última atualização: 19/10/2025 às 13:40
--

CREATE TABLE `symbol` (
  `smbl_id` int(11) NOT NULL,
  `smbl_code` varchar(4) NOT NULL,
  `smbl_name` varchar(100) DEFAULT NULL,
  `smbl_is_fiat` tinyint(1) NOT NULL DEFAULT 0,
  `smbl_created_at` timestamp NULL DEFAULT current_timestamp(),
  `smbl_updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `symbol_pair`
--
-- Criação: 19/10/2025 às 13:40
-- Última atualização: 19/10/2025 às 13:40
--

CREATE TABLE `symbol_pair` (
  `smpr_id` int(11) NOT NULL,
  `smpr_base_symbol_id` int(11) NOT NULL,
  `smpr_quote_symbol_id` int(11) NOT NULL,
  `smpr_is_active` tinyint(1) NOT NULL DEFAULT 1,
  `smpr_created_at` timestamp NULL DEFAULT current_timestamp(),
  `smpr_updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `candle_day`
--
ALTER TABLE `candle_day`
  ADD PRIMARY KEY (`cndl_id`),
  ADD UNIQUE KEY `uk_pair_date` (`cndl_symbol_pair_id`,`cndl_date`),
  ADD KEY `idx_cndl_date` (`cndl_date`),
  ADD KEY `idx_cndl_pair_date` (`cndl_symbol_pair_id`,`cndl_date`);

--
-- Índices de tabela `candle_time`
--
ALTER TABLE `candle_time`
  ADD PRIMARY KEY (`cntm_id`),
  ADD UNIQUE KEY `uk_candle_time` (`cntm_candle_day_id`,`cntm_hour`);

--
-- Índices de tabela `symbol`
--
ALTER TABLE `symbol`
  ADD PRIMARY KEY (`smbl_id`),
  ADD UNIQUE KEY `smbl_code` (`smbl_code`),
  ADD KEY `idx_smbl_code` (`smbl_code`),
  ADD KEY `idx_smbl_is_fiat` (`smbl_is_fiat`);

--
-- Índices de tabela `symbol_pair`
--
ALTER TABLE `symbol_pair`
  ADD PRIMARY KEY (`smpr_id`),
  ADD UNIQUE KEY `uk_base_quote` (`smpr_base_symbol_id`,`smpr_quote_symbol_id`),
  ADD KEY `smpr_quote_symbol_id` (`smpr_quote_symbol_id`),
  ADD KEY `idx_smpr_base_quote` (`smpr_base_symbol_id`,`smpr_quote_symbol_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `candle_day`
--
ALTER TABLE `candle_day`
  MODIFY `cndl_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `candle_time`
--
ALTER TABLE `candle_time`
  MODIFY `cntm_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `symbol`
--
ALTER TABLE `symbol`
  MODIFY `smbl_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `symbol_pair`
--
ALTER TABLE `symbol_pair`
  MODIFY `smpr_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `candle_day`
--
ALTER TABLE `candle_day`
  ADD CONSTRAINT `candle_day_ibfk_1` FOREIGN KEY (`cndl_symbol_pair_id`) REFERENCES `symbol_pair` (`smpr_id`);

--
-- Restrições para tabelas `candle_time`
--
ALTER TABLE `candle_time`
  ADD CONSTRAINT `candle_time_ibfk_1` FOREIGN KEY (`cntm_candle_day_id`) REFERENCES `candle_day` (`cndl_id`);

--
-- Restrições para tabelas `symbol_pair`
--
ALTER TABLE `symbol_pair`
  ADD CONSTRAINT `symbol_pair_ibfk_1` FOREIGN KEY (`smpr_base_symbol_id`) REFERENCES `symbol` (`smbl_id`),
  ADD CONSTRAINT `symbol_pair_ibfk_2` FOREIGN KEY (`smpr_quote_symbol_id`) REFERENCES `symbol` (`smbl_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

call sp_insert_candle('BNBBRL','2025-04-25 03:00:00+00:00',3410.0,3418.0,3409.0,3418.0,6.84);
call sp_insert_candle('BNBBRL','2025-04-25 03:15:00+00:00',3419.0,3423.0,3419.0,3423.0,0.024);
call sp_insert_candle('BNBBRL','2025-04-25 03:30:00+00:00',3421.0,3421.0,3418.0,3419.0,1.583);
call sp_insert_candle('BNBBRL','2025-04-25 03:45:00+00:00',3420.0,3423.0,3420.0,3421.0,0.301);
call sp_insert_candle('BNBBRL','2025-04-25 04:00:00+00:00',3423.0,3435.0,3422.0,3435.0,2.525);
call sp_insert_candle('BNBBRL','2025-04-25 04:15:00+00:00',3435.0,3441.0,3435.0,3441.0,4.39);
call sp_insert_candle('BNBBRL','2025-04-25 04:30:00+00:00',3435.0,3445.0,3435.0,3445.0,6.392);

SELECT
    base.smbl_code AS base_symbol,
    quote.smbl_code AS quote_symbol,
    cd.cndl_date AS date,
    TIME_FORMAT(SEC_TO_TIME(ct.cntm_minutes * 60), '%H:%i') AS time,
    ct.cntm_open_price AS open_price,
    ct.cntm_high_price AS high_price,
    ct.cntm_low_price AS low_price,
    ct.cntm_close_price AS close_price,
    ct.cntm_volume AS volume
FROM candle_time ct
INNER JOIN candle_day cd ON ct.cntm_candle_day_id = cd.cndl_id
INNER JOIN symbol_pair sp ON cd.cndl_symbol_pair_id = sp.smpr_id
INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
ORDER BY base.smbl_code, quote.smbl_code, cd.cndl_date, ct.cntm_minutes;