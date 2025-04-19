-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 20/04/2025 às 00:56
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
(3, 'teste', '51511627000148', 'Jhonata', NULL, 'suga suga', '(11) 99999-9999', 'teste@teste.com', NULL, '2025-04-16 18:19:06', NULL, NULL, '2025-04-16 21:19:06', 1),
(4, 'Gabriel Sugador', '08494375000167', 'Gabriel Sugador de mandioca', 'Seila MORA NO CU DO CARAI', 'Gabriel Filho JR', '(11) 99999-9999', 'gabriel@teste.com', NULL, '2025-04-16 23:39:08', NULL, NULL, '2025-04-17 02:39:08', 1);

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
(152, 1, '::1', '2025-04-16 23:36:47', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(153, 10, '::1', '2025-04-16 23:37:21', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(154, 10, '::1', '2025-04-16 23:39:08', 'criar_empresa_sucesso', 1, 'Empresa criada: Gabriel Sugador'),
(155, 10, '::1', '2025-04-16 23:39:45', 'desativar_usuario', 1, 'ID: 1'),
(156, 10, '::1', '2025-04-16 23:39:47', 'ativar_usuario', 1, 'ID: 1'),
(157, 1, '::1', '2025-04-16 23:40:00', 'solicitar_senha_sucesso', 1, 'Solicitação de reset de senha enviada.'),
(158, NULL, '::1', '2025-04-16 23:40:40', 'solicitacao_acesso_sucesso', 1, 'Solicitação de acesso enviada para: sarahsempai@teste.com'),
(159, 10, '::1', '2025-04-16 23:40:51', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(160, 10, '::1', '2025-04-16 23:41:09', 'aprovar_reset_senha', 1, 'ID: 13'),
(161, 10, '::1', '2025-04-16 23:41:33', 'aprovar_acesso', 1, 'ID: 10'),
(162, 10, '::1', '2025-04-16 23:41:49', 'editar_usuario_sucesso', 1, 'Dados atualizados para ID: 12'),
(163, 12, '::1', '2025-04-16 23:42:09', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(164, 12, '::1', '2025-04-16 23:43:28', 'config_admin_upload_sucesso', 1, 'Foto de perfil atualizada.'),
(165, 1, '::1', '2025-04-16 23:43:52', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(166, 1, '::1', '2025-04-17 16:11:09', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(167, 1, '::1', '2025-04-19 10:56:55', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(168, 1, '::1', '2025-04-19 11:40:43', 'exportar_requisitos', 1, 'Iniciada exportação de requisitos para CSV.'),
(169, 1, '::1', '2025-04-19 11:40:43', 'exportar_requisitos', 1, 'Exportação CSV concluída. 5 requisitos exportados.'),
(170, 1, '::1', '2025-04-19 11:41:17', 'ativar_requisito', 1, 'ID: 4'),
(171, 1, '::1', '2025-04-19 11:41:29', 'desativar_requisito', 1, 'ID: 4'),
(172, 1, '::1', '2025-04-19 11:41:33', 'desativar_requisito', 1, 'ID: 1'),
(173, 1, '::1', '2025-04-19 11:41:36', 'desativar_requisito', 1, 'ID: 2'),
(174, 1, '::1', '2025-04-19 13:42:34', 'login_sucesso', 1, 'Usuário logado com sucesso.');

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
-- Estrutura para tabela `requisitos_auditoria`
--

CREATE TABLE `requisitos_auditoria` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL COMMENT 'Código único opcional para o requisito (ex: A.5.1.1, LGPD-ART-5)',
  `nome` varchar(255) NOT NULL COMMENT 'Título curto ou nome do requisito/controle',
  `descricao` text NOT NULL COMMENT 'Descrição detalhada do requisito ou pergunta da auditoria',
  `categoria` varchar(100) DEFAULT NULL COMMENT 'Categoria ou domínio (ex: Controle de Acesso, Política, Procedimento)',
  `norma_referencia` varchar(100) DEFAULT NULL COMMENT 'Norma ou regulamento associado (ex: ISO 27001, LGPD)',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo (pode ser usado), 0 = Inativo',
  `criado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário que criou',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário que modificou por último',
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lista mestra de requisitos/itens de auditoria';

--
-- Despejando dados para a tabela `requisitos_auditoria`
--

INSERT INTO `requisitos_auditoria` (`id`, `codigo`, `nome`, `descricao`, `categoria`, `norma_referencia`, `ativo`, `criado_por`, `data_criacao`, `modificado_por`, `data_modificacao`) VALUES
(1, 'A.5.1', 'Políticas para segurança da informação', 'Um conjunto de políticas para segurança da informação deve ser definido, aprovado pela direção, publicado e comunicado para os funcionários e partes externas relevantes.', 'Políticas', 'ISO 27001:2022', 0, 1, '2025-04-19 14:19:07', NULL, '2025-04-19 14:41:33'),
(2, 'A.6.1.1', 'Papéis e responsabilidades', 'Todos as responsabilidades por segurança da informação devem ser definidas e alocadas.', 'Organização da Segurança', 'ISO 27001:2022', 0, 1, '2025-04-19 14:19:07', NULL, '2025-04-19 14:41:36'),
(3, 'LGPD.ART.6', 'Princípios do Tratamento de Dados', 'Verificar se o tratamento de dados pessoais observa os princípios da finalidade, adequação, necessidade, livre acesso, qualidade dos dados, transparência, segurança, prevenção, não discriminação e responsabilização.', 'Princípios', 'LGPD', 1, 1, '2025-04-19 14:19:07', NULL, '2025-04-19 14:19:07'),
(4, 'FIN.REC.01', 'Conciliação Bancária', 'Realizar a conciliação de todas as contas bancárias da empresa mensalmente.', 'Financeiro', 'Controle Interno', 0, 1, '2025-04-19 14:19:07', NULL, '2025-04-19 14:41:29'),
(5, 'TI.SEG.05', 'Controle de Acesso Lógico', 'Garantir que o acesso aos sistemas e dados seja concedido com base no princípio do menor privilégio e revisto periodicamente.', 'Controle de Acesso', 'Segurança de TI', 1, 1, '2025-04-19 14:19:07', NULL, '2025-04-19 14:19:07');

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
(9, 'caio@teste.com', 'Caio Almeida', 'Quero fica ricu', '2025-04-16 21:28:51', 'aprovada', '2025-04-16 21:29:25', NULL, 10, 3),
(10, 'sarahsempai@teste.com', 'sarah sempaio', 'mandachuva', '2025-04-16 23:40:40', 'aprovada', '2025-04-16 23:41:33', NULL, 10, 4);

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
(12, 10, '2025-04-16 20:17:21', 'aprovada', 1, '2025-04-16 20:50:09', NULL, NULL),
(13, 1, '2025-04-16 23:40:00', 'aprovada', 10, '2025-04-16 23:41:09', NULL, NULL);

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
(1, 'Caio Teste', 'admin@teste.com', '$2y$10$mu.p7Rh7nV0Mjew733j0ju/Vqdjbab427iNBAj/BxGNDLnaMOiLhO', 'admin', 1, '2024-03-01 12:00:00', 'user_1_67c8a2a7552ac_1741202087.jpg', 0, NULL),
(10, 'Jhonata', 'jhonata@teste.com', '$2y$10$UtY.H3ZTnlfRm/0MsNILUumhdhKaL8z8iKPvZc1OHYtpJcbzRz8LS', 'admin', 1, '2025-03-15 16:33:27', 'user_10_1744855693_fe268483.jpg', 0, 1),
(12, 'sarah sempaio', 'sarahsempai@teste.com', '$2y$10$7TgoWobeWSpFooe8iKmdwuJqdajOJ7Pk573fQNwMlvSk4z9g8Jp2.', 'admin', 1, '2025-04-16 23:41:33', 'user_12_1744857808_297fc61f.jpg', 0, 4);

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
-- Índices de tabela `requisitos_auditoria`
--
ALTER TABLE `requisitos_auditoria`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_req_ativo` (`ativo`),
  ADD KEY `idx_req_categoria` (`categoria`),
  ADD KEY `idx_req_norma` (`norma_referencia`),
  ADD KEY `fk_req_criado_por` (`criado_por`),
  ADD KEY `fk_req_modificado_por` (`modificado_por`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=175;

--
-- AUTO_INCREMENT de tabela `modelos_auditoria`
--
ALTER TABLE `modelos_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `requisitos_auditoria`
--
ALTER TABLE `requisitos_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
-- Restrições para tabelas `requisitos_auditoria`
--
ALTER TABLE `requisitos_auditoria`
  ADD CONSTRAINT `fk_req_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_req_modificado_por` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

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
