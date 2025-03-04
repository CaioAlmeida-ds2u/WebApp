-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 04/03/2025 às 03:38
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
(1, 'Empresa Teste', '12.345.678/0001-90', 'Empresa Teste Ltda.', 'Rua de Teste, 123', 'Contato Teste', '(11) 99999-9999', 'teste@empresa.com', 'caminho/para/logo.png', '2025-03-03 14:35:39');

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
(1, NULL, '::1', '2025-03-03 14:36:09', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(2, NULL, '::1', '2025-03-03 14:36:33', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(3, NULL, '::1', '2025-03-03 14:37:02', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(4, NULL, '::1', '2025-03-03 14:40:13', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(5, NULL, '::1', '2025-03-03 14:41:10', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(6, 1, '::1', '2025-03-03 14:41:49', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(7, 1, '::1', '2025-03-03 15:07:20', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(8, 1, '::1', '2025-03-03 15:42:55', 'Ativação de usuário', 1, 'Usuário ID: 3 ativado'),
(9, 1, '::1', '2025-03-03 15:42:57', 'Desativação de usuário', 1, 'Usuário ID: 3 desativado'),
(10, 1, '::1', '2025-03-03 15:44:40', 'Ativação de usuário', 1, 'Usuário ID: 3 ativado'),
(11, 1, '::1', '2025-03-03 15:45:14', 'Desativação de usuário', 1, 'Usuário ID: 4 desativado'),
(12, 1, '::1', '2025-03-03 15:50:26', 'Exclusão de usuário', 1, 'Usuário ID: 4 excluído'),
(13, 1, '::1', '2025-03-03 15:52:29', 'Exclusão de usuário', 0, 'Erro ao excluir usuário ID: 9999. Possível erro de chave estrangeira.'),
(14, 1, '::1', '2025-03-03 15:53:00', 'Exclusão de usuário', 0, 'ID do usuário não fornecido.'),
(15, 1, '::1', '2025-03-03 16:02:40', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(16, 1, '::1', '2025-03-03 16:19:05', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(17, 1, '::1', '2025-03-03 16:44:02', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(18, 1, '::1', '2025-03-03 17:22:08', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(19, 1, '::1', '2025-03-03 17:29:18', 'Desativação de usuário', 1, 'Usuário ID: 3 desativado'),
(20, NULL, '::1', '2025-03-03 17:29:30', 'login_falha', 0, 'Tentativa de login: Usuário desativado. Contate o administrador.'),
(21, 1, '::1', '2025-03-03 17:29:37', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(22, 1, '::1', '2025-03-03 19:01:27', 'Desativação de usuário', 1, 'Usuário ID: 2 desativado'),
(23, 1, '::1', '2025-03-03 19:01:29', 'Ativação de usuário', 1, 'Usuário ID: 2 ativado'),
(24, 1, '::1', '2025-03-03 19:01:34', 'Ativação de usuário', 1, 'Usuário ID: 3 ativado'),
(25, NULL, '::1', '2025-03-03 19:30:16', 'login_falha', 0, 'Tentativa de login: Credenciais inválidas.'),
(26, 1, '::1', '2025-03-03 19:30:21', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(27, 1, '::1', '2025-03-03 19:33:28', 'login_sucesso', 1, 'Usuário logado com sucesso'),
(28, 1, '::1', '2025-03-03 23:31:21', 'login_sucesso', 1, 'Usuário logado com sucesso');

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
  `empresa_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `perfil`, `ativo`, `data_cadastro`, `foto`, `empresa_id`) VALUES
(1, 'Admin Teste', 'admin@teste.com', '$2y$10$ebH47/zLEAybe2fQYRp3d.H7vXT3FR9EtZLdIPG.SRTZPIAESNXaa', 'admin', 1, '2025-03-03 14:35:39', NULL, NULL),
(2, 'Auditor Teste', 'auditor@teste.com', '$2y$10$ebH47/zLEAybe2fQYRp3d.H7vXT3FR9EtZLdIPG.SRTZPIAESNXaa', 'auditor', 1, '2025-03-03 15:19:06', NULL, NULL),
(3, 'Gestor Teste', 'gestor@teste.com', '$2y$10$ebH47/zLEAybe2fQYRp3d.H7vXT3FR9EtZLdIPG.SRTZPIAESNXaa', 'gestor', 1, '2025-03-03 15:19:06', NULL, NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de tabela `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `logs_acesso`
--
ALTER TABLE `logs_acesso`
  ADD CONSTRAINT `logs_acesso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

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
