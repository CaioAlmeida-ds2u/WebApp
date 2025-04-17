-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 17/04/2025 às 04:34
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `acoditools`
--
CREATE DATABASE IF NOT EXISTS acoditools; 
use acoditools;

-- --------------------------------------------------------

--
-- Estrutura para tabela `empresas`
--

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `razao_social` varchar(255) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `contato` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `data_cadastro` datetime NOT NULL DEFAULT current_timestamp(),
  `criado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário que criou a empresa',
  `modificado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário que modificou a empresa pela última vez',
  `data_modificacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Data e hora da última modificação',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo, 0 = Inativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `empresas`
--

INSERT INTO `empresas` (`id`, `nome`, `cnpj`, `razao_social`, `endereco`, `contato`, `telefone`, `email`, `logo`, `data_cadastro`, `criado_por`, `modificado_por`, `data_modificacao`, `ativo`) VALUES
(1, 'Empresa A', '11.222.333/0001-44', 'Empresa A Ltda.', NULL, 'João', '(11) 1111-1111', 'contato@empresa-a.com', NULL, '2024-03-05 10:00:00', NULL, NULL, NULL, 1),
(2, 'Empresa B', '55.666.777/0001-88', 'Empresa B S.A.', NULL, 'Maria', '(22) 2222-2222', 'contato@empresa-b.com', NULL, '2024-03-05 11:00:00', NULL, NULL, NULL, 1),
(3, 'teste', '51511627000148', 'Jhonata', NULL, 'suga suga', '(11) 99999-9999', 'teste@teste.com', NULL, '2025-04-16 18:19:06', NULL, NULL, '2025-04-16 21:19:06', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_acesso`
--

CREATE TABLE `logs_acesso` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `data_hora` datetime DEFAULT current_timestamp(),
  `acao` varchar(255) DEFAULT NULL,
  `sucesso` tinyint(1) DEFAULT NULL,
  `detalhes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelos_auditoria`
--

CREATE TABLE `modelos_auditoria` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL COMMENT 'Nome descritivo do modelo (ex: ISO 27001:2022, LGPD Checklist)',
  `descricao` text DEFAULT NULL COMMENT 'Descrição mais detalhada do propósito ou escopo do modelo',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo (pode ser usado), 0 = Inativo',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Armazena os modelos/templates base para auditorias';

--
-- Despejando dados para a tabela `modelos_auditoria`
--

INSERT INTO `modelos_auditoria` (`id`, `nome`, `descricao`, `ativo`, `data_criacao`, `data_modificacao`) VALUES
(1, 'ISO 27001:2022 - Controles de Segurança', 'Checklist baseado nos controles do Anexo A da norma ISO 27001, versão 2022.', 1, '2025-04-16 13:37:25', '2025-04-16 13:37:25'),
(2, 'LGPD - Verificação de Conformidade', 'Modelo para avaliar a aderência aos principais requisitos da Lei Geral de Proteção de Dados (Lei nº 13.709/2018).', 1, '2025-04-16 13:37:25', '2025-04-16 13:37:25'),
(3, 'Auditoria Financeira Trimestral', 'Template padrão para auditorias financeiras recorrentes internas ou externas.', 0, '2025-04-16 13:37:25', '2025-04-16 13:37:25'),
(4, 'Controles Internos SOX (Seção 404)', 'Verificação de controles internos sobre relatórios financeiros (ICFR) relevantes para a Sarbanes-Oxley, Seção 404.', 1, '2025-04-16 13:37:25', '2025-04-16 13:37:25'),
(5, 'Análise de Riscos de TI', 'Modelo para identificação e avaliação de riscos relacionados à tecnologia da informação.', 1, '2025-04-16 13:37:25', '2025-04-16 13:37:25');

-- --------------------------------------------------------

--
-- Estrutura para tabela `solicitacoes_acesso`
--

CREATE TABLE `solicitacoes_acesso` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `nome_completo` varchar(255) NOT NULL,
  `motivo` text NOT NULL,
  `data_solicitacao` datetime DEFAULT current_timestamp(),
  `status` enum('pendente','aprovada','rejeitada') DEFAULT 'pendente',
  `data_aprovacao` datetime DEFAULT NULL,
  `observacoes` text DEFAULT NULL COMMENT 'Observações do administrador (opcional)',
  `admin_id` int(11) DEFAULT NULL COMMENT 'ID do administrador que aprovou/rejeitou a solicitação',
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `solicitacoes_acesso`
--

INSERT INTO `solicitacoes_acesso` (`id`, `email`, `nome_completo`, `motivo`, `data_solicitacao`, `status`, `data_aprovacao`, `observacoes`, `admin_id`, `empresa_id`) VALUES
(8, 'caio@teste.com', 'Caio Felipe de Almeida', 'Sou novo na empresa carai.', '2025-04-12 19:05:50', 'rejeitada', '2025-04-15 13:23:25', '', 1, 1),
(9, 'caio@teste.com', 'Caio Almeida', 'Quero fica ricu', '2025-04-16 21:28:51', 'aprovada', '2025-04-16 21:29:25', NULL, 10, 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `solicitacoes_reset_senha`
--

CREATE TABLE `solicitacoes_reset_senha` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_solicitacao` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
  `admin_id` int(11) DEFAULT NULL,
  `data_aprovacao` datetime DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `data_rejeicao` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `solicitacoes_reset_senha`
--

INSERT INTO `solicitacoes_reset_senha` (`id`, `usuario_id`, `data_solicitacao`, `status`, `admin_id`, `data_aprovacao`, `observacoes`, `data_rejeicao`) VALUES
(12, 10, '2025-04-16 20:17:21', 'aprovada', 1, '2025-04-16 20:50:09', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('admin','auditor','gestor') NOT NULL DEFAULT 'auditor',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `data_cadastro` datetime NOT NULL DEFAULT current_timestamp(),
  `foto` varchar(255) DEFAULT NULL,
  `primeiro_acesso` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = Não é o primeiro acesso, 1 = Primeiro acesso',
  `empresa_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `perfil`, `ativo`, `data_cadastro`, `foto`, `primeiro_acesso`, `empresa_id`) VALUES
(1, 'Caio Teste', 'admin@teste.com', '$2y$10$11nqqCa3A3zCzV/XR2wxfeHRYgW0epx3kDq8MJ8SY27RNm61G8v3q', 'admin', 1, '2024-03-01 12:00:00', 'user_1_67c8a2a7552ac_1741202087.jpg', 0, NULL),
(10, 'Jhonata', 'jhonata@teste.com', '$2y$10$UtY.H3ZTnlfRm/0MsNILUumhdhKaL8z8iKPvZc1OHYtpJcbzRz8LS', 'admin', 1, '2025-03-15 16:33:27', 'user_10_1744855693_fe268483.jpg', 0, 1);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnpj` (`cnpj`),
  ADD KEY `criado_por` (`criado_por`),
  ADD KEY `modificado_por` (`modificado_por`),
  ADD KEY `idx_empresa_nome` (`nome`);

--
-- Índices de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_log_datahora` (`data_hora`),
  ADD KEY `idx_log_acao` (`acao`);

--
-- Índices de tabela `modelos_auditoria`
--
ALTER TABLE `modelos_auditoria`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_modelo_nome` (`nome`),
  ADD KEY `idx_modelo_ativo` (`ativo`);

--
-- Índices de tabela `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empresa_id` (`empresa_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_solicitacao_status` (`status`);

--
-- Índices de tabela `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_solreset_status` (`status`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `empresa_id` (`empresa_id`),
  ADD KEY `idx_usuario_perfil` (`perfil`),
  ADD KEY `idx_usuario_ativo` (`ativo`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=152;

--
-- AUTO_INCREMENT de tabela `modelos_auditoria`
--
ALTER TABLE `modelos_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `empresas`
--
ALTER TABLE `empresas`
  ADD CONSTRAINT `empresas_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `empresas_ibfk_2` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `logs_acesso`
--
ALTER TABLE `logs_acesso`
  ADD CONSTRAINT `logs_acesso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  ADD CONSTRAINT `fk_solicitacao_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `solicitacoes_acesso_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  ADD CONSTRAINT `solicitacoes_reset_senha_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitacoes_reset_senha_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
