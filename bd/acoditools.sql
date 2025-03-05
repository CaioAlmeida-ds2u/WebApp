-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 05/03/2025 às 23:56
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
  `endereco` text DEFAULT NULL,
  `contato` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `data_cadastro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `empresas`
--

INSERT INTO `empresas` (`id`, `nome`, `cnpj`, `razao_social`, `endereco`, `contato`, `telefone`, `email`, `logo`, `data_cadastro`) VALUES
(1, 'Empresa A', '11.222.333/0001-44', 'Empresa A Ltda.', 'Rua A, 123', 'João', '(11) 1111-1111', 'contato@empresa-a.com', NULL, '2024-03-05 10:00:00'),
(2, 'Empresa B', '55.666.777/0001-88', 'Empresa B S.A.', 'Av. B, 456', 'Maria', '(22) 2222-2222', 'contato@empresa-b.com', NULL, '2024-03-05 11:00:00');

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

--
-- Despejando dados para a tabela `logs_acesso`
--

INSERT INTO `logs_acesso` (`id`, `usuario_id`, `ip_address`, `data_hora`, `acao`, `sucesso`, `detalhes`) VALUES
(1, NULL, '::1', '2025-03-04 10:22:59', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(2, NULL, '::1', '2025-03-04 10:23:18', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(3, NULL, '::1', '2025-03-04 10:24:34', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(4, NULL, '::1', '2025-03-04 10:24:42', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(5, NULL, '::1', '2025-03-04 10:28:54', 'login_falha', 0, 'Tentativa de login: Erro de login.'),
(6, NULL, '::1', '2025-03-04 10:49:27', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(7, NULL, '::1', '2025-03-04 10:50:08', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(8, NULL, '::1', '2025-03-04 10:52:56', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(9, NULL, '::1', '2025-03-04 10:54:49', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(10, NULL, '::1', '2025-03-04 10:59:52', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(11, NULL, '::1', '2025-03-04 11:00:08', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(12, NULL, '::1', '2025-03-04 11:00:52', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(13, NULL, '::1', '2025-03-04 11:01:20', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(14, NULL, '::1', '2025-03-04 11:02:58', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(15, NULL, '::1', '2025-03-04 11:05:00', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(16, NULL, '::1', '2025-03-04 11:05:33', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(17, NULL, '::1', '2025-03-04 11:05:40', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(18, 1, '::1', '2025-03-04 20:01:53', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(19, 1, '::1', '2025-03-04 20:07:10', 'Rejeição de solicitação de acesso', 1, 'Solicitação ID: 3 rejeitada.'),
(20, 1, '::1', '2025-03-04 20:38:23', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(21, 1, '::1', '2025-03-05 07:30:37', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(22, NULL, '::1', '2025-03-05 10:27:17', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(23, 1, '::1', '2025-03-05 10:27:23', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(24, 1, '::1', '2025-03-05 10:50:29', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(25, 1, '::1', '2025-03-05 10:57:21', 'Desativação de usuário', 1, 'Usuário ID: 3 desativado'),
(26, 1, '::1', '2025-03-05 10:57:23', 'Ativação de usuário', 1, 'Usuário ID: 3 ativado'),
(27, 1, '::1', '2025-03-05 11:00:34', 'Redefinição de senha', 1, 'Senha redefinida para o usuário ID: 2 pelo Admin ID: 1'),
(28, 1, '::1', '2025-03-05 11:00:48', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(29, 1, '::1', '2025-03-05 11:01:06', 'Redefinição de senha', 1, 'Senha redefinida para o usuário ID: 3 pelo Admin ID: 1'),
(30, 1, '::1', '2025-03-05 11:21:57', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(31, 1, '::1', '2025-03-05 14:29:39', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(32, 1, '::1', '2025-03-05 14:29:54', 'Rejeição de solicitação de acesso', 1, 'Solicitação ID: 4 rejeitada.'),
(33, 1, '::1', '2025-03-05 14:30:16', 'Redefinição de senha', 1, 'Senha redefinida para o usuário ID: 2 pelo Admin ID: 1'),
(34, 1, '::1', '2025-03-05 14:40:03', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(35, 1, '::1', '2025-03-05 16:23:34', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(36, 1, '::1', '2025-03-05 16:33:51', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(37, 1, '::1', '2025-03-05 16:38:47', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(38, 1, '::1', '2025-03-05 17:02:32', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(39, 1, '::1', '2025-03-05 17:03:03', 'Desativação de usuário', 1, 'Usuário ID: 3 desativado'),
(40, 1, '::1', '2025-03-05 17:03:04', 'Desativação de usuário', 1, 'Usuário ID: 2 desativado'),
(41, 1, '::1', '2025-03-05 17:03:05', 'Desativação de usuário', 1, 'Usuário ID: 4 desativado'),
(42, 1, '::1', '2025-03-05 17:03:08', 'Ativação de usuário', 1, 'Usuário ID: 4 ativado'),
(43, 1, '::1', '2025-03-05 17:03:09', 'Ativação de usuário', 1, 'Usuário ID: 2 ativado'),
(44, 1, '::1', '2025-03-05 17:03:39', 'Ativação de usuário', 1, 'Usuário ID: 2 ativado'),
(45, 1, '::1', '2025-03-05 17:03:44', 'Ativação de usuário', 1, 'Usuário ID: 3 ativado'),
(46, 1, '::1', '2025-03-05 17:04:46', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(47, 1, '::1', '2025-03-05 17:05:06', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 5.'),
(48, 1, '::1', '2025-03-05 17:05:14', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 5.'),
(49, 1, '::1', '2025-03-05 17:05:24', 'Rejeição de solicitação de acesso', 1, 'Solicitação ID: 5 rejeitada.'),
(50, 1, '::1', '2025-03-05 19:00:31', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(51, 1, '::1', '2025-03-05 19:00:38', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 6.'),
(52, 1, '::1', '2025-03-05 19:00:45', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 6.'),
(53, 1, '::1', '2025-03-05 19:00:53', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 6.'),
(54, 1, '::1', '2025-03-05 19:01:27', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 6.'),
(55, 1, '::1', '2025-03-05 19:01:51', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 6.'),
(56, 1, '::1', '2025-03-05 19:02:47', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 6.'),
(57, 1, '::1', '2025-03-05 19:04:20', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 6.'),
(58, 1, '::1', '2025-03-05 19:08:54', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 6.'),
(59, 1, '::1', '2025-03-05 19:08:57', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 6.'),
(60, 1, '::1', '2025-03-05 19:12:22', 'Aprovação de solicitação de acesso', 1, 'Solicitação ID: 6 aprovada.  Novo usuário criado.'),
(61, 1, '::1', '2025-03-05 19:17:47', 'Aprovação de solicitação de acesso', 1, 'Solicitação ID: 5 aprovada.  Novo usuário criado.'),
(62, 1, '::1', '2025-03-05 19:19:02', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(63, 1, '::1', '2025-03-05 19:19:06', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 5.'),
(64, 1, '::1', '2025-03-05 19:19:12', 'Aprovação de solicitação de acesso', 0, 'Erro ao aprovar solicitação ID: 5.'),
(65, 1, '::1', '2025-03-05 19:19:48', 'Aprovação de solicitação de acesso', 1, 'Solicitação ID: 4 aprovada.  Novo usuário criado.'),
(66, 1, '::1', '2025-03-05 19:48:31', 'Exclusão de usuário', 1, 'Usuário ID: 2 excluído com sucesso pelo Admin ID: 1'),
(67, 1, '::1', '2025-03-05 19:50:17', 'Exclusão de usuário', 1, 'Usuário ID: 5 excluído com sucesso pelo Admin ID: 1'),
(68, 1, '::1', '2025-03-05 19:50:22', 'Exclusão de usuário', 1, 'Usuário ID: 4 excluído com sucesso pelo Admin ID: 1'),
(69, 1, '::1', '2025-03-05 19:50:24', 'Exclusão de usuário', 1, 'Usuário ID: 9 excluído com sucesso pelo Admin ID: 1'),
(70, 1, '::1', '2025-03-05 19:50:26', 'Exclusão de usuário', 1, 'Usuário ID: 3 excluído com sucesso pelo Admin ID: 1'),
(71, 1, '::1', '2025-03-05 19:50:28', 'Exclusão de usuário', 1, 'Usuário ID: 6 excluído com sucesso pelo Admin ID: 1');

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
  `comentarios_admin` text DEFAULT NULL,
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `solicitacoes_acesso`
--

INSERT INTO `solicitacoes_acesso` (`id`, `email`, `nome_completo`, `motivo`, `data_solicitacao`, `status`, `data_aprovacao`, `observacoes`, `admin_id`, `comentarios_admin`, `empresa_id`) VALUES
(3, 'caio@teste.com', 'Caio', 'Quero comer o gabriel', '2025-03-04 10:22:40', 'rejeitada', '2025-03-04 20:07:10', '', 1, NULL, 1),
(4, 'gabriel@teste.com.br', 'Caio Felipe de Almeida', 'Quero da a raba.', '2025-03-05 14:29:25', 'aprovada', '2025-03-05 19:19:48', '', 1, NULL, 1),
(5, 'ana@teste.com', 'Ana Opalkie', 'Quero ficar milionaria.', '2025-03-05 17:04:27', 'aprovada', '2025-03-05 19:17:47', '', 1, NULL, 1),
(6, 'jow@teste.com', 'jhonata da rocha', 'teste', '2025-03-05 19:00:14', 'aprovada', '2025-03-05 19:12:22', NULL, 1, NULL, 1);

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
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `solicitacoes_reset_senha`
--

INSERT INTO `solicitacoes_reset_senha` (`id`, `usuario_id`, `data_solicitacao`, `status`, `admin_id`, `data_aprovacao`, `observacoes`) VALUES
(3, 1, '2025-03-04 10:23:32', 'pendente', NULL, NULL, NULL),
(5, 1, '2025-03-05 17:02:17', 'pendente', NULL, NULL, NULL);

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
(1, 'Caio Teste', 'admin@teste.com', '$2y$10$FW/9KeQPHITpJo/QSIVBXOhcUlvFC9rjPVTu4MNLCoBmeST1YX2Ca', 'admin', 1, '2024-03-01 12:00:00', 'user_1_67c8a2a7552ac_1741202087.jpg', 1, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnpj` (`cnpj`);

--
-- Índices de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empresa_id` (`empresa_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Índices de tabela `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT de tabela `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `logs_acesso`
--
ALTER TABLE `logs_acesso`
  ADD CONSTRAINT `logs_acesso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  ADD CONSTRAINT `solicitacoes_acesso_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
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
