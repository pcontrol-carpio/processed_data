/*M!999999\- enable the sandbox mode */
-- MariaDB dump 10.19-11.7.2-MariaDB, for osx10.20 (arm64)
--
-- Host: 192.168.5.50    Database: receita
-- ------------------------------------------------------
-- Server version	10.6.22-MariaDB-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `base`
--
DROP TABLE IF EXISTS `processados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `processados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pasta` varchar(45) DEFAULT NULL,
  `completo` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

DROP TABLE IF EXISTS `completados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `completados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `processado_id` int(11) NOT NULL,
  `arquivo` varchar(100) NOT NULL,
  `iniciado_em` datetime DEFAULT NULL,
  `concluido_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `processado_id` (`processado_id`,`arquivo`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


DROP TABLE IF EXISTS `base`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `base` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `cnpj` varchar(20) NOT NULL,
  `nome_fantasia` varchar(255) DEFAULT NULL,
  `razao_social` varchar(255) DEFAULT NULL,
  `cnae_fiscal_principal` varchar(10) DEFAULT NULL,
  `natureza_juridica` int(11) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `municipio` int(11) DEFAULT NULL,
  `bairro` varchar(50) DEFAULT NULL,
  `data_inicio_atividade` int(11) DEFAULT NULL,
  `matriz` tinyint(1) DEFAULT NULL,
  `porte` tinyint(1) DEFAULT NULL,
  `capital_social` decimal(20,3) DEFAULT NULL,
  `simples` tinyint(1) DEFAULT NULL,
  `mei` tinyint(1) DEFAULT NULL,
  `situacao_cadastral` tinyint(1) DEFAULT NULL,
  `data_situacao_cadastral` int(11) DEFAULT NULL,
  `motivo_situacao_cadastral` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cnpj` (`cnpj`),
  KEY `uf` (`uf`),
  KEY `municipio` (`municipio`),
  KEY `situacao_cadastral` (`situacao_cadastral`),
  KEY `bairro` (`bairro`),
  KEY `cnae_principal` (`cnae_fiscal_principal`) USING BTREE,
  KEY `ano_fundacao` (`data_inicio_atividade`) USING BTREE,
  KEY `ano_inativacao` (`data_situacao_cadastral`) USING BTREE,
  KEY `estabelecimento_id` (`estabelecimento_id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `mei` (`mei`),
  KEY `simples` (`simples`),
  KEY `porte` (`porte`),
  KEY `matriz` (`matriz`),
  KEY `motivo_situacao_cadastral` (`motivo_situacao_cadastral`),
  KEY `natureza_juridica` (`natureza_juridica`),
  KEY `capital` (`capital_social`) USING BTREE,
  FULLTEXT KEY `empresa_nome` (`nome_fantasia`,`razao_social`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
ALTER TABLE base ADD UNIQUE (cnpj);

--
-- Table structure for table `completados`
--



--
-- Table structure for table `empresa`
--

DROP TABLE IF EXISTS `empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cnpj_basico` varchar(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `razao_social` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `natureza_juridica` int(11) DEFAULT NULL,
  `qualificacao_responsavel` tinyint(4) DEFAULT NULL,
  `capital_social` varchar(25) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `porte` tinyint(1) DEFAULT NULL,
  `ente_federativo` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cnpj_basico` (`cnpj_basico`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
ALTER TABLE empresa ADD UNIQUE (cnpj_basico);

--
-- Table structure for table `estabelecimento`
--

DROP TABLE IF EXISTS `estabelecimento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estabelecimento` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `cnpj` varchar(20) NOT NULL,
  `nome_fantasia` varchar(255) DEFAULT NULL,
  `cnpj_basico` varchar(8) DEFAULT NULL,
  `cnpj_ordem` varchar(5) DEFAULT NULL,
  `cnpj_dv` varchar(2) DEFAULT NULL,
  `matriz_filial` varchar(1) DEFAULT NULL,
  `situacao_cadastral` varchar(10) DEFAULT NULL,
  `data_situacao_cadastral` varchar(10) DEFAULT NULL,
  `motivo_situacao_cadastral` varchar(2) DEFAULT NULL,
  `nome_cidade_exterior` varchar(50) DEFAULT NULL,
  `pais` varchar(3) DEFAULT NULL,
  `data_inicio_atividade` varchar(10) DEFAULT NULL,
  `cnae_fiscal_principal` varchar(10) DEFAULT NULL,
  `cnae_fiscal_secundaria` longtext DEFAULT NULL,
  `tipo_logradouro` varchar(50) DEFAULT NULL,
  `logradouro` varchar(50) DEFAULT NULL,
  `numero` varchar(6) DEFAULT NULL,
  `complemento` varchar(50) DEFAULT NULL,
  `bairro` varchar(50) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `uf` varchar(2) DEFAULT NULL,
  `municipio` varchar(10) DEFAULT NULL,
  `ddd_1` varchar(2) DEFAULT NULL,
  `telefone1` varchar(10) DEFAULT NULL,
  `ddd_2` varchar(2) DEFAULT NULL,
  `telefone2` varchar(10) DEFAULT NULL,
  `ddd_fax` varchar(2) DEFAULT NULL,
  `fax` varchar(10) DEFAULT NULL,
  `correio_eletronico` varchar(50) DEFAULT NULL,
  `situacao_especial` varchar(25) DEFAULT NULL,
  `data_situacao_especial` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_id` (`empresa_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
ALTER TABLE estabelecimento ADD UNIQUE (cnpj);
--
-- Table structure for table `processados`
--


--
-- Table structure for table `simples`
--

DROP TABLE IF EXISTS `simples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `simples` (
  `cnpj_basico` varchar(8) DEFAULT NULL,
  `opcao_pelo_simples` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `data_opcao_pelo_simples` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `data_exclusao_simples` varchar(8) DEFAULT NULL,
  `opcao_pelo_mei` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `data_opcao_mei` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `data_exclusao_mei` varchar(8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
ALTER TABLE simples ADD UNIQUE (cnpj_basico);

--
-- Table structure for table `socio`
--

DROP TABLE IF EXISTS `socio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `socio` (
  `cnpj_basico` varchar(10) NOT NULL,
  `identificador_socio` tinyint(1) DEFAULT NULL,
  `nome_socio` varchar(150) DEFAULT NULL,
  `cnpj_cpf_socio` varchar(20) DEFAULT NULL,
  `qualificacao_socio` tinyint(4) DEFAULT NULL,
  `data_entrada_sociedade` int(11) DEFAULT NULL,
  `pais` char(4) DEFAULT NULL,
  `representante_legal` varchar(15) DEFAULT NULL,
  `nome_representante` varchar(50) DEFAULT NULL,
  `qualificacao_representante_legal` tinyint(4) DEFAULT NULL,
  `faixa_etaria` tinyint(1) DEFAULT NULL,
  KEY `qualificacao_socio` (`qualificacao_socio`),
  KEY `faixa_etaria` (`faixa_etaria`),
  KEY `data_entrada_sociedade` (`data_entrada_sociedade`),
  KEY `identificador_socio` (`identificador_socio`),
  KEY `cnpj_basico` (`cnpj_basico`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

ALTER TABLE socio ADD UNIQUE (cnpj_basico,nome_socio,cnpj_cpf_socio);

/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-06-16 16:06:24
