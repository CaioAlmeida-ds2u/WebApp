-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 21/04/2025 às 21:24
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
-- Estrutura para tabela `auditorias`
--

CREATE TABLE `auditorias` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL COMMENT 'Título descritivo da auditoria (ex: Auditoria Interna ISO 27001 - TI - Q1 2025)',
  `empresa_id` int(11) NOT NULL COMMENT 'FK para empresas - Empresa sendo auditada',
  `modelo_id` int(11) NOT NULL COMMENT 'FK para modelos_auditoria - Modelo base utilizado',
  `auditor_responsavel_id` int(11) DEFAULT NULL COMMENT 'FK para usuarios (perfil=auditor) - Auditor designado',
  `gestor_responsavel_id` int(11) DEFAULT NULL COMMENT 'FK para usuarios (perfil=gestor) - Gestor que criou/supervisiona',
  `escopo` text DEFAULT NULL COMMENT 'Delimitação do que será auditado',
  `objetivo` text DEFAULT NULL COMMENT 'Objetivo principal da auditoria',
  `instrucoes` text DEFAULT NULL COMMENT 'Instruções específicas para o auditor',
  `data_inicio_planejada` date DEFAULT NULL,
  `data_fim_planejada` date DEFAULT NULL,
  `data_inicio_real` datetime DEFAULT NULL COMMENT 'Quando o auditor efetivamente iniciou',
  `data_conclusao_auditor` datetime DEFAULT NULL COMMENT 'Quando o auditor submeteu para revisão do gestor',
  `data_aprovacao_rejeicao_gestor` datetime DEFAULT NULL COMMENT 'Quando o gestor aprovou ou rejeitou',
  `status` enum('Planejada','Em Andamento','Pausada','Concluída (Auditor)','Em Revisão','Aprovada','Rejeitada','Cancelada') NOT NULL DEFAULT 'Planejada',
  `resultado_geral` enum('Conforme','Não Conforme','Parcialmente Conforme','N/A') DEFAULT NULL COMMENT 'Resultado geral definido pelo gestor na aprovação (opcional)',
  `observacoes_gerais_gestor` text DEFAULT NULL COMMENT 'Comentários finais do gestor ao aprovar/rejeitar',
  `criado_por` int(11) DEFAULT NULL COMMENT 'Usuário que criou o registro (geralmente o gestor)',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por` int(11) DEFAULT NULL COMMENT 'Último usuário que modificou',
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registra cada instância de auditoria realizada';

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_evidencias`
--

CREATE TABLE `auditoria_evidencias` (
  `id` bigint(20) NOT NULL,
  `auditoria_item_id` bigint(20) NOT NULL COMMENT 'FK para auditoria_itens',
  `nome_arquivo_original` varchar(255) NOT NULL,
  `nome_arquivo_armazenado` varchar(255) NOT NULL COMMENT 'Nome seguro/único no servidor',
  `caminho_arquivo` varchar(512) NOT NULL COMMENT 'Caminho relativo ou absoluto do arquivo',
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamanho_bytes` bigint(20) UNSIGNED DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL COMMENT 'Descrição opcional da evidência',
  `data_upload` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_upload_id` int(11) DEFAULT NULL COMMENT 'FK para usuarios (quem fez upload)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Arquivos de evidência anexados aos itens da auditoria';

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_itens`
--

CREATE TABLE `auditoria_itens` (
  `id` bigint(20) NOT NULL,
  `auditoria_id` int(11) NOT NULL COMMENT 'FK para auditorias',
  `requisito_id` int(11) DEFAULT NULL COMMENT 'FK para requisitos_auditoria (Referência ao mestre, pode ser NULL se mestre for excluído)',
  `modelo_item_id` bigint(20) DEFAULT NULL COMMENT 'FK opcional para modelo_itens (Referência ao item específico do modelo usado)',
  `codigo_item` varchar(50) DEFAULT NULL,
  `nome_item` varchar(255) NOT NULL,
  `descricao_item` text NOT NULL,
  `categoria_item` varchar(100) DEFAULT NULL,
  `norma_item` varchar(100) DEFAULT NULL,
  `guia_evidencia_item` text DEFAULT NULL,
  `peso_item` int(11) DEFAULT 1,
  `secao_item` varchar(255) DEFAULT NULL,
  `ordem_item` int(11) DEFAULT 0 COMMENT 'Ordem dentro da seção/auditoria',
  `status_conformidade` enum('Pendente','Conforme','Não Conforme','Parcial','N/A') NOT NULL DEFAULT 'Pendente',
  `observacoes_auditor` text DEFAULT NULL,
  `data_resposta_auditor` datetime DEFAULT NULL,
  `respondido_por_auditor_id` int(11) DEFAULT NULL,
  `status_revisao_gestor` enum('Pendente','Revisado','Ação Solicitada') NOT NULL DEFAULT 'Pendente',
  `observacoes_gestor` text DEFAULT NULL,
  `data_revisao_gestor` datetime DEFAULT NULL,
  `revisado_por_gestor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens avaliados em uma auditoria específica e suas respostas';

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_planos_acao`
--

CREATE TABLE `auditoria_planos_acao` (
  `id` bigint(20) NOT NULL,
  `auditoria_item_id` bigint(20) NOT NULL COMMENT 'FK para auditoria_itens (item não conforme)',
  `descricao_acao` text NOT NULL COMMENT 'O que será feito para corrigir/prevenir',
  `responsavel_id` int(11) DEFAULT NULL COMMENT 'FK para usuarios (quem é o responsável pela ação - pode ser da empresa auditada)',
  `responsavel_externo` varchar(255) DEFAULT NULL COMMENT 'Nome do responsável se não for usuário do sistema',
  `prazo_conclusao` date DEFAULT NULL COMMENT 'Data limite para concluir a ação',
  `data_conclusao_real` datetime DEFAULT NULL COMMENT 'Quando a ação foi efetivamente concluída',
  `status_acao` enum('Pendente','Em Andamento','Concluída','Cancelada','Atrasada','Verificada') NOT NULL DEFAULT 'Pendente',
  `observacoes_execucao` text DEFAULT NULL COMMENT 'Comentários sobre o andamento ou conclusão da ação',
  `verificado_por_id` int(11) DEFAULT NULL COMMENT 'FK para usuarios (Auditor/Gestor que verificou a eficácia)',
  `data_verificacao` datetime DEFAULT NULL,
  `observacoes_verificacao` text DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL COMMENT 'Usuário que criou o plano',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por` int(11) DEFAULT NULL,
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planos de ação para tratar não conformidades identificadas';

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
(1, 'Empresa A', '31535376000124', 'Empresa A Ltda.', NULL, 'João', '(11) 1111-1111', 'contato@empresa-a.com', 'logo_empresa_1_1745160214.png', '2024-03-05 10:00:00', NULL, 1, '2025-04-20 14:43:34', 1),
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
(174, 1, '::1', '2025-04-19 13:42:34', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(175, 1, '::1', '2025-04-20 09:27:17', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(176, NULL, '::1', '2025-04-20 09:31:39', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: admin@teste.com)'),
(177, NULL, '::1', '2025-04-20 09:31:45', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: admin@teste.com)'),
(178, 1, '::1', '2025-04-20 09:31:54', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(179, 1, '::1', '2025-04-20 10:08:18', 'exportar_requisitos', 1, 'Iniciada exportação de requisitos para CSV.'),
(180, 1, '::1', '2025-04-20 10:08:18', 'exportar_requisitos', 1, 'Exportação CSV concluída. 5 requisitos exportados.'),
(181, 1, '::1', '2025-04-20 10:24:29', 'excluir_empresa_falha', 0, 'Falha exclusão ID: 3'),
(182, NULL, '::1', '2025-04-20 10:49:24', 'upload_logo_sucesso', 1, 'Empresa ID: 1, Arquivo: logo_empresa_1_1745156964.png'),
(183, 1, '::1', '2025-04-20 10:49:24', 'editar_empresa_falha_db', 0, 'ID: 1, Erro: CNPJ inválido.'),
(184, NULL, '::1', '2025-04-20 10:50:27', 'upload_logo_sucesso', 1, 'Empresa ID: 2, Arquivo: logo_empresa_2_1745157027.jpg'),
(185, 1, '::1', '2025-04-20 10:50:27', 'editar_empresa_falha_db', 0, 'ID: 2, Erro: CNPJ inválido.'),
(186, NULL, '::1', '2025-04-20 11:43:34', 'upload_logo_sucesso', 1, 'Empresa ID: 1, Arquivo: logo_empresa_1_1745160214.png'),
(187, 1, '::1', '2025-04-20 11:43:34', 'editar_empresa_sucesso', 1, 'ID: 1'),
(188, 1, '::1', '2025-04-20 11:52:32', 'excluir_requisito_tentativa', 0, 'ID: 4'),
(189, 1, '::1', '2025-04-20 11:52:42', 'editar_requisito_sucesso', 1, 'ID: 4'),
(190, 1, '::1', '2025-04-20 12:00:42', 'excluir_empresa_sucesso', 1, 'Exclusão ID: 2'),
(191, 1, '::1', '2025-04-20 12:00:48', 'excluir_empresa_falha', 0, 'Falha exclusão ID: 4. Motivo: Não é possível excluir: Existem 1 usuário(s) vinculados a esta empresa. Realoque ou remova os usuários primeiro.'),
(192, 1, '::1', '2025-04-20 12:00:58', 'excluir_empresa_falha', 0, 'Falha exclusão ID: 3. Motivo: Erro inesperado ao tentar excluir a empresa.'),
(193, 1, '::1', '2025-04-20 12:08:27', 'excluir_empresa_falha', 0, 'Falha exclusão ID: 3. Motivo: Não é possível excluir: Existem 1 solicitação(ões) de acesso vinculadas a esta empresa.'),
(194, 1, '::1', '2025-04-20 12:20:39', 'ativar_requisito', 1, 'ID: 4'),
(195, 1, '::1', '2025-04-20 12:20:43', 'desativar_requisito', 1, 'ID: 4'),
(196, 1, '::1', '2025-04-20 12:20:47', 'excluir_requisito_tentativa', 0, 'ID: 4'),
(197, 1, '::1', '2025-04-20 12:23:09', 'excluir_requisito_tentativa', 0, 'ID: 4'),
(198, 1, '::1', '2025-04-20 18:32:54', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(199, 1, '::1', '2025-04-20 18:48:45', 'desativar_requisito', 1, 'ID: 3'),
(200, 1, '::1', '2025-04-20 18:48:49', 'ativar_requisito', 1, 'ID: 1'),
(201, 1, '::1', '2025-04-20 18:48:52', 'ativar_requisito', 1, 'ID: 2'),
(202, 1, '::1', '2025-04-20 18:48:57', 'excluir_requisito_sucesso', 1, 'ID: 2'),
(203, 1, '::1', '2025-04-20 18:58:26', 'exportar_logs', 1, 'Exportando 2 logs. Filtros: filtro_data_inicio=2025-04-01&filtro_data_fim=2025-04-20&filtro_usuario_id=12'),
(204, 1, '::1', '2025-04-20 18:58:34', 'exportar_empresas', 1, 'Exportando 3 empresas.'),
(205, 1, '::1', '2025-04-20 18:58:45', 'exportar_usuarios', 1, 'Exportando 3 usuários. Filtros: status=ativos, perfil=admin'),
(206, 1, '::1', '2025-04-20 19:02:26', 'exportar_requisitos', 1, 'Iniciada exportação de requisitos para CSV.'),
(207, 1, '::1', '2025-04-20 19:02:26', 'exportar_requisitos', 1, 'Exportação CSV concluída. 3 requisitos exportados.'),
(208, 1, '::1', '2025-04-20 19:03:58', 'exportar_usuarios', 1, 'Exportando 3 usuários. Filtros: status=ativos, perfil=auditor'),
(209, 1, '::1', '2025-04-20 19:12:48', 'exportar_usuarios', 1, 'Exportando 0 usuários. Filtros: filtro_status=inativos'),
(210, 1, '::1', '2025-04-20 19:39:01', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(211, 1, '::1', '2025-04-20 19:40:05', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(212, 1, '::1', '2025-04-20 19:40:15', 'editar_usuario_sucesso', 1, 'Dados atualizados para ID: 12'),
(213, 12, '::1', '2025-04-20 19:40:37', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(214, 1, '::1', '2025-04-20 20:03:06', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(215, 1, '::1', '2025-04-20 20:04:08', 'criar_requisito_sucesso', 1, 'Req: Teste'),
(216, 1, '::1', '2025-04-20 20:04:21', 'desativar_requisito', 1, 'ID: 1'),
(217, 1, '::1', '2025-04-20 20:04:27', 'excluir_requisito_sucesso', 1, 'ID: 1'),
(218, 12, '::1', '2025-04-20 20:05:25', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(219, 12, '::1', '2025-04-20 20:14:20', 'solicitar_senha_sucesso', 1, 'Solicitação de reset de senha enviada.'),
(220, 1, '::1', '2025-04-20 20:14:26', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(221, 1, '::1', '2025-04-20 20:14:31', 'aprovar_reset_senha', 1, 'ID: 14'),
(222, 12, '::1', '2025-04-20 20:14:47', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(223, NULL, '::1', '2025-04-20 20:26:10', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: sarahsempaio@teste.com)'),
(224, NULL, '::1', '2025-04-20 20:26:20', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: sarahsempaio@teste.com)'),
(225, 12, '::1', '2025-04-20 20:26:36', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(226, NULL, '::1', '2025-04-21 15:52:08', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: sarahsempai@teste.com)'),
(227, 12, '::1', '2025-04-21 15:52:17', 'solicitar_senha_sucesso', 1, 'Solicitação de reset de senha enviada.'),
(228, 1, '::1', '2025-04-21 15:52:24', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(229, 1, '::1', '2025-04-21 15:52:32', 'aprovar_reset_senha', 1, 'ID: 15'),
(230, 12, '::1', '2025-04-21 15:52:49', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(231, 12, '::1', '2025-04-21 15:53:47', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(232, 1, '::1', '2025-04-21 15:53:59', 'login_sucesso', 1, 'Usuário logado com sucesso.');

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelos_auditoria`
--

CREATE TABLE `modelos_auditoria` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL COMMENT 'Nome descritivo do modelo (ex: ISO 27001:2022, LGPD Checklist)',
  `descricao` text DEFAULT NULL COMMENT 'Descrição mais detalhada do propósito ou escopo do modelo',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo (pode ser usado), 0 = Inativo',
  `criado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário admin que criou',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário admin que modificou por último',
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Modelos/templates base para auditorias';

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelo_itens`
--

CREATE TABLE `modelo_itens` (
  `id` bigint(20) NOT NULL,
  `modelo_id` int(11) NOT NULL COMMENT 'FK para modelos_auditoria',
  `requisito_id` int(11) NOT NULL COMMENT 'FK para requisitos_auditoria',
  `secao` varchar(255) DEFAULT NULL COMMENT 'Nome da seção/domínio dentro do modelo (ex: A.5 Políticas)',
  `ordem_secao` int(11) DEFAULT 0 COMMENT 'Ordem da seção dentro do modelo',
  `ordem_item` int(11) DEFAULT 0 COMMENT 'Ordem do item dentro da seção'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Define quais requisitos compõem cada modelo e sua estrutura';

-- --------------------------------------------------------

--
-- Estrutura para tabela `requisitos_auditoria`
--

CREATE TABLE `requisitos_auditoria` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL COMMENT 'Código único opcional para o requisito (ex: A.5.1.1, LGPD-ART-5)',
  `nome` varchar(255) NOT NULL COMMENT 'Título curto ou nome do requisito/controle',
  `descricao` text NOT NULL COMMENT 'Descrição detalhada do requisito ou pergunta da auditoria',
  `categoria` varchar(100) DEFAULT NULL COMMENT 'Categoria ou domínio (ex: Controle de Acesso, Política)',
  `norma_referencia` varchar(100) DEFAULT NULL COMMENT 'Norma ou regulamento associado (ex: ISO 27001, LGPD)',
  `guia_evidencia` text DEFAULT NULL COMMENT 'Orientações sobre as evidências esperadas (opcional)',
  `peso` int(11) DEFAULT 1 COMMENT 'Peso ou criticidade do requisito (opcional)',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo (pode ser usado), 0 = Inativo',
  `criado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário que criou',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário que modificou por último',
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lista mestra de requisitos/itens de auditoria';

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
(13, 1, '2025-04-16 23:40:00', 'aprovada', 10, '2025-04-16 23:41:09', NULL, NULL),
(14, 12, '2025-04-20 20:14:20', 'aprovada', 1, '2025-04-20 20:14:31', NULL, NULL),
(15, 12, '2025-04-21 15:52:17', 'aprovada', 1, '2025-04-21 15:52:32', NULL, NULL);

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
(12, 'sarah sempaio', 'sarahsempai@teste.com', '$2y$10$Zcr7OiAj1iZ8LPF9kNjrZuzFO9ol19ZccJbeK7g5zvTUAQAocodNe', 'gestor', 1, '2025-04-16 23:41:33', 'user_12_1744857808_297fc61f.jpg', 0, 4);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `auditorias`
--
ALTER TABLE `auditorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auditoria_empresa_status` (`empresa_id`,`status`),
  ADD KEY `idx_auditoria_auditor_status` (`auditor_responsavel_id`,`status`),
  ADD KEY `idx_auditoria_gestor_status` (`gestor_responsavel_id`,`status`),
  ADD KEY `idx_auditoria_modelo` (`modelo_id`),
  ADD KEY `fk_auditoria_criado_por` (`criado_por`),
  ADD KEY `fk_auditoria_modificado_por` (`modificado_por`);

--
-- Índices de tabela `auditoria_evidencias`
--
ALTER TABLE `auditoria_evidencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome_arquivo_armazenado` (`nome_arquivo_armazenado`),
  ADD KEY `idx_evidencia_item` (`auditoria_item_id`),
  ADD KEY `fk_evidencia_usuario` (`usuario_upload_id`);

--
-- Índices de tabela `auditoria_itens`
--
ALTER TABLE `auditoria_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auditem_auditoria_status` (`auditoria_id`,`status_conformidade`),
  ADD KEY `idx_auditem_requisito_orig` (`requisito_id`),
  ADD KEY `idx_auditem_modelo_item_orig` (`modelo_item_id`),
  ADD KEY `idx_auditem_auditor_resp` (`respondido_por_auditor_id`),
  ADD KEY `idx_auditem_gestor_rev` (`revisado_por_gestor_id`);

--
-- Índices de tabela `auditoria_planos_acao`
--
ALTER TABLE `auditoria_planos_acao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plano_item` (`auditoria_item_id`),
  ADD KEY `idx_plano_responsavel` (`responsavel_id`),
  ADD KEY `idx_plano_status` (`status_acao`),
  ADD KEY `idx_plano_verificado` (`verificado_por_id`),
  ADD KEY `fk_plano_criado_por` (`criado_por`),
  ADD KEY `fk_plano_modificado_por` (`modificado_por`);

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
  ADD KEY `idx_modelo_ativo` (`ativo`),
  ADD KEY `fk_modelo_criado_por` (`criado_por`),
  ADD KEY `fk_modelo_modificado_por` (`modificado_por`);

--
-- Índices de tabela `modelo_itens`
--
ALTER TABLE `modelo_itens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_moditem_modelo_requisito` (`modelo_id`,`requisito_id`) COMMENT 'Um requisito só pode estar uma vez em um modelo',
  ADD KEY `idx_moditem_modelo_secao` (`modelo_id`,`secao`),
  ADD KEY `idx_moditem_requisito` (`requisito_id`);

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
-- AUTO_INCREMENT de tabela `auditorias`
--
ALTER TABLE `auditorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `auditoria_evidencias`
--
ALTER TABLE `auditoria_evidencias`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `auditoria_itens`
--
ALTER TABLE `auditoria_itens`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `auditoria_planos_acao`
--
ALTER TABLE `auditoria_planos_acao`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=233;

--
-- AUTO_INCREMENT de tabela `modelos_auditoria`
--
ALTER TABLE `modelos_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `modelo_itens`
--
ALTER TABLE `modelo_itens`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `requisitos_auditoria`
--
ALTER TABLE `requisitos_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `auditorias`
--
ALTER TABLE `auditorias`
  ADD CONSTRAINT `fk_auditoria_auditor` FOREIGN KEY (`auditor_responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_auditoria_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_gestor` FOREIGN KEY (`gestor_responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_modelo` FOREIGN KEY (`modelo_id`) REFERENCES `modelos_auditoria` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_modificado_por` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `auditoria_evidencias`
--
ALTER TABLE `auditoria_evidencias`
  ADD CONSTRAINT `fk_evidencia_item` FOREIGN KEY (`auditoria_item_id`) REFERENCES `auditoria_itens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evidencia_usuario` FOREIGN KEY (`usuario_upload_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `auditoria_itens`
--
ALTER TABLE `auditoria_itens`
  ADD CONSTRAINT `fk_auditem_auditor_resp` FOREIGN KEY (`respondido_por_auditor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_auditem_auditoria` FOREIGN KEY (`auditoria_id`) REFERENCES `auditorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditem_gestor_rev` FOREIGN KEY (`revisado_por_gestor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_auditem_modelo_item_orig` FOREIGN KEY (`modelo_item_id`) REFERENCES `modelo_itens` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditem_requisito_orig` FOREIGN KEY (`requisito_id`) REFERENCES `requisitos_auditoria` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `auditoria_planos_acao`
--
ALTER TABLE `auditoria_planos_acao`
  ADD CONSTRAINT `fk_plano_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_plano_item` FOREIGN KEY (`auditoria_item_id`) REFERENCES `auditoria_itens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_plano_modificado_por` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_plano_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_plano_verificado_por` FOREIGN KEY (`verificado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

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
-- Restrições para tabelas `modelos_auditoria`
--
ALTER TABLE `modelos_auditoria`
  ADD CONSTRAINT `fk_modelo_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_modelo_modificado_por` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `modelo_itens`
--
ALTER TABLE `modelo_itens`
  ADD CONSTRAINT `fk_moditem_modelo` FOREIGN KEY (`modelo_id`) REFERENCES `modelos_auditoria` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_moditem_requisito` FOREIGN KEY (`requisito_id`) REFERENCES `requisitos_auditoria` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
