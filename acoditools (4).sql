-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 25/05/2025 às 16:50
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
  `titulo` varchar(255) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `modelo_id` int(11) DEFAULT NULL,
  `workflow_template_id` int(11) DEFAULT NULL,
  `auditor_responsavel_id` int(11) DEFAULT NULL,
  `equipe_id` int(11) DEFAULT NULL,
  `auditor_lider_equipe_id` int(11) DEFAULT NULL,
  `gestor_responsavel_id` int(11) NOT NULL,
  `escopo` text DEFAULT NULL,
  `objetivo` text DEFAULT NULL,
  `instrucoes` text DEFAULT NULL,
  `justificativa_risco` text DEFAULT NULL,
  `criterios_sucesso_texto` text DEFAULT NULL,
  `data_inicio_planejada` date DEFAULT NULL,
  `data_fim_planejada` date DEFAULT NULL,
  `data_envio_aviso_auditoria` datetime DEFAULT NULL,
  `data_inicio_real` datetime DEFAULT NULL,
  `data_conclusao_auditor` datetime DEFAULT NULL,
  `data_submissao_para_lider` datetime DEFAULT NULL,
  `data_consolidacao_lider` datetime DEFAULT NULL,
  `data_aprovacao_rejeicao_gestor` datetime DEFAULT NULL,
  `status` enum('Planejada','Em Andamento','Pausada','Concluída (Auditor)','Aguardando Correção Auditor','Em Revisão','Aprovada','Rejeitada','Cancelada') NOT NULL DEFAULT 'Planejada',
  `status_interno_equipe` enum('Pendente Início Membros','Seções em Andamento','Aguardando Revisão Líder','Consolidada pelo Líder') DEFAULT NULL,
  `resultado_geral` enum('Conforme','Não Conforme','Parcialmente Conforme','N/A') DEFAULT NULL,
  `observacoes_gerais_gestor` text DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por` int(11) DEFAULT NULL,
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Instâncias de auditorias realizadas';

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_comunicacoes`
--

CREATE TABLE `auditoria_comunicacoes` (
  `id` bigint(20) NOT NULL,
  `auditoria_id` int(11) NOT NULL,
  `usuario_remetente_id` int(11) NOT NULL,
  `mensagem` text NOT NULL,
  `data_envio` timestamp NOT NULL DEFAULT current_timestamp(),
  `lido_em` datetime DEFAULT NULL,
  `anexo_path` varchar(512) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comunicações formais dentro de uma auditoria';

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_documentos_planejamento`
--

CREATE TABLE `auditoria_documentos_planejamento` (
  `id` int(11) NOT NULL,
  `auditoria_id` int(11) NOT NULL,
  `nome_arquivo_original` varchar(255) NOT NULL,
  `nome_arquivo_armazenado` varchar(255) NOT NULL,
  `caminho_arquivo` varchar(512) NOT NULL,
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamanho_bytes` bigint(20) UNSIGNED DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `data_upload` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_upload_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documentos de planejamento da auditoria';

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_evidencias`
--

CREATE TABLE `auditoria_evidencias` (
  `id` bigint(20) NOT NULL,
  `auditoria_item_id` bigint(20) NOT NULL,
  `nome_arquivo_original` varchar(255) NOT NULL,
  `nome_arquivo_armazenado` varchar(255) NOT NULL,
  `caminho_arquivo` varchar(512) NOT NULL,
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamanho_bytes` bigint(20) UNSIGNED DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `data_upload` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_upload_id` int(11) DEFAULT NULL,
  `submetido_por_perfil` enum('auditor','auditado','gestor') NOT NULL DEFAULT 'auditor'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Evidências anexadas aos itens de auditoria';

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_item_tipos_nc_selecionados`
--

CREATE TABLE `auditoria_item_tipos_nc_selecionados` (
  `id` bigint(20) NOT NULL,
  `auditoria_item_id` bigint(20) NOT NULL,
  `tipo_nc_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos de NC para um item de auditoria';

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_itens`
--

CREATE TABLE `auditoria_itens` (
  `id` bigint(20) NOT NULL,
  `auditoria_id` int(11) NOT NULL,
  `requisito_id` int(11) DEFAULT NULL,
  `modelo_item_id` bigint(20) DEFAULT NULL,
  `codigo_item` varchar(50) DEFAULT NULL,
  `nome_item` varchar(255) NOT NULL,
  `descricao_item` text NOT NULL,
  `categoria_item` varchar(100) DEFAULT NULL,
  `norma_item` varchar(100) DEFAULT NULL,
  `guia_evidencia_item` text DEFAULT NULL,
  `peso_item` int(11) DEFAULT 1,
  `secao_item` varchar(255) DEFAULT NULL,
  `ordem_item` int(11) DEFAULT 0,
  `status_conformidade` enum('Pendente','Conforme','Não Conforme','Parcial','N/A') NOT NULL DEFAULT 'Pendente',
  `observacoes_auditor` text DEFAULT NULL,
  `recomendacao_auditor` text DEFAULT NULL,
  `criticidade_achado_id` int(11) DEFAULT NULL,
  `data_resposta_auditor` datetime DEFAULT NULL,
  `respondido_por_auditor_id` int(11) DEFAULT NULL,
  `status_revisao_gestor` enum('Pendente','Revisado (Concordo Auditor)','Revisado (Plano de Ação Requerido)','Revisado (Discordo - Sem Ação)') NOT NULL DEFAULT 'Pendente',
  `observacoes_gestor` text DEFAULT NULL,
  `data_revisao_gestor` datetime DEFAULT NULL,
  `revisado_por_gestor_id` int(11) DEFAULT NULL,
  `status_item_workflow_auditor` enum('Pendente Avaliação','Em Avaliação Auditor','Avaliado Auditor','Aguardando Revisão Líder','Aprovado Líder','Retornado pelo Líder') DEFAULT 'Pendente Avaliação',
  `auditado_contato_responsavel_id` int(11) DEFAULT NULL,
  `resposta_auditado_texto` text DEFAULT NULL,
  `data_resposta_auditado` datetime DEFAULT NULL,
  `status_resposta_auditado` enum('Pendente Auditado','Respondido Auditado','Esclarecimento Solicitado Auditor') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens (requisitos) avaliados em uma auditoria';

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_planos_acao`
--

CREATE TABLE `auditoria_planos_acao` (
  `id` bigint(20) NOT NULL,
  `auditoria_item_id` bigint(20) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `descricao_acao` text NOT NULL,
  `responsavel_id` int(11) DEFAULT NULL,
  `responsavel_externo` varchar(255) DEFAULT NULL,
  `prazo_conclusao` date DEFAULT NULL,
  `criticidade_plano_acao` enum('Baixa','Media','Alta','Critica') DEFAULT NULL,
  `data_conclusao_real` datetime DEFAULT NULL,
  `status_acao` enum('Pendente','Em Andamento','Concluído (Aguardando Verificação)','Cancelada','Atrasada','Verificada (Eficaz)','Verificada (Ineficaz - Reabrir)') NOT NULL DEFAULT 'Pendente',
  `observacoes_execucao` text DEFAULT NULL,
  `verificado_por_id` int(11) DEFAULT NULL,
  `data_verificacao` datetime DEFAULT NULL,
  `observacoes_verificacao` text DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por` int(11) DEFAULT NULL,
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planos de ação para não conformidades';

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_secao_responsaveis`
--

CREATE TABLE `auditoria_secao_responsaveis` (
  `id` bigint(20) NOT NULL,
  `auditoria_id` int(11) NOT NULL,
  `secao_modelo_nome` varchar(255) NOT NULL,
  `auditor_designado_id` int(11) NOT NULL,
  `data_atribuicao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Atribuição de auditores a seções de um modelo';

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
  `contato` varchar(255) DEFAULT NULL COMMENT 'Nome do contato principal na empresa',
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL COMMENT 'Email principal de contato da empresa',
  `logo` varchar(255) DEFAULT NULL,
  `plano_assinatura_id` int(11) DEFAULT NULL,
  `data_cadastro_plataforma` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Quando a empresa foi registrada na AcodITools',
  `status_contrato_cliente` enum('Teste','Ativo','Inadimplente','Suspenso','Cancelado') NOT NULL DEFAULT 'Teste',
  `ativo_na_plataforma` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Se a conta da empresa está globalmente ativa na plataforma (controle do Admin AcodITools)',
  `data_inicio_contrato` date DEFAULT NULL,
  `data_fim_contrato` date DEFAULT NULL,
  `limite_usuarios_override` int(11) DEFAULT NULL COMMENT 'Sobrescreve limite de usuários do plano',
  `limite_armazenamento_override_mb` int(11) DEFAULT NULL COMMENT 'Sobrescreve limite de armazenamento (MB) do plano',
  `criado_por` int(11) DEFAULT NULL COMMENT 'Admin da AcodITools que registrou a empresa',
  `modificado_por` int(11) DEFAULT NULL COMMENT 'Admin da AcodITools que modificou por último',
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Empresa operacionalmente ativa (pode ser auditada). Status do contrato é separado.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Empresas clientes da plataforma AcodITools';

--
-- Despejando dados para a tabela `empresas`
--

INSERT INTO `empresas` (`id`, `nome`, `cnpj`, `razao_social`, `endereco`, `contato`, `telefone`, `email`, `logo`, `plano_assinatura_id`, `data_cadastro_plataforma`, `status_contrato_cliente`, `ativo_na_plataforma`, `data_inicio_contrato`, `data_fim_contrato`, `limite_usuarios_override`, `limite_armazenamento_override_mb`, `criado_por`, `modificado_por`, `data_modificacao`, `ativo`) VALUES
(1, 'AlimentaSP', '84842777000193', 'AlimentaSP limitada', NULL, NULL, '(11) 99090-7070', 'alimenta@teste.com', 'logo_cliente_1748182913_9818a925a58987ef.png', 1, '2025-05-19 02:57:57', 'Ativo', 1, NULL, NULL, NULL, NULL, 1, 1, '2025-05-25 14:21:53', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `empresa_departamentos_areas`
--

CREATE TABLE `empresa_departamentos_areas` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `nome_departamento_area` varchar(150) NOT NULL,
  `descricao_departamento_area` text DEFAULT NULL,
  `responsavel_departamento_usuario_id` int(11) DEFAULT NULL COMMENT 'Usuário (gestor ou auditado_contato) responsável pela área',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Departamentos/Áreas dentro de uma Empresa Cliente';

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipes`
--

CREATE TABLE `equipes` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por_gestor_id` int(11) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por_gestor_id` int(11) DEFAULT NULL,
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Equipes de Auditoria por Empresa Cliente';

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipe_membros`
--

CREATE TABLE `equipe_membros` (
  `id` int(11) NOT NULL,
  `equipe_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'FK para usuarios (perfil=auditor_empresa)',
  `ativo_na_equipe` tinyint(1) NOT NULL DEFAULT 1,
  `data_adesao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Associação entre Equipes e Auditores';

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_acesso`
--

CREATE TABLE `logs_acesso` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `empresa_id_contexto` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `data_hora` datetime DEFAULT current_timestamp(),
  `acao` varchar(255) DEFAULT NULL,
  `sucesso` tinyint(1) DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `entidade_afetada_tipo` varchar(50) DEFAULT NULL,
  `entidade_afetada_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `logs_acesso`
--

INSERT INTO `logs_acesso` (`id`, `usuario_id`, `empresa_id_contexto`, `ip_address`, `data_hora`, `acao`, `sucesso`, `detalhes`, `entidade_afetada_tipo`, `entidade_afetada_id`) VALUES
(1, 1, NULL, '::1', '2025-05-17 11:10:35', 'login_sucesso', 1, 'Usuário logado com sucesso.', NULL, NULL),
(2, NULL, NULL, '::1', '2025-05-18 19:16:07', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: admin@teste.com)', NULL, NULL),
(3, NULL, NULL, '::1', '2025-05-18 19:16:19', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: caio@teste.com)', NULL, NULL),
(4, 1, NULL, '::1', '2025-05-18 19:16:30', 'login_sucesso', 1, 'Usuário logado com sucesso.', NULL, NULL),
(5, NULL, NULL, '::1', '2025-05-18 23:49:07', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: caio@teste.com)', NULL, NULL),
(6, NULL, NULL, '::1', '2025-05-18 23:49:26', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: caio@teste.com)', NULL, NULL),
(7, NULL, NULL, '::1', '2025-05-18 23:49:31', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: caio@teste.com)', NULL, NULL),
(8, NULL, NULL, '::1', '2025-05-18 23:49:43', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: caio@teste.com)', NULL, NULL),
(9, NULL, NULL, '::1', '2025-05-18 23:50:02', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: caio@teste.com)', NULL, NULL),
(10, NULL, NULL, '::1', '2025-05-18 23:50:19', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: caio@teste.com)', NULL, NULL),
(11, 1, NULL, '::1', '2025-05-18 23:50:34', 'login_sucesso', 1, 'Usuário logado com sucesso.', NULL, NULL),
(12, 1, NULL, '::1', '2025-05-18 23:51:31', 'criar_plano_sucesso', 1, 'Plano: Basico', NULL, NULL),
(13, 1, NULL, '::1', '2025-05-18 23:57:57', 'registro_empresa_cliente', 1, 'Empresa ID: 1, Nome: AlimentaSP', NULL, NULL),
(14, NULL, NULL, '::1', '2025-05-18 23:59:28', 'solicitacao_acesso_sucesso', 1, 'Solicitação de acesso enviada para: jhonata@alimentasp.com', NULL, NULL),
(15, 1, NULL, '::1', '2025-05-18 23:59:42', 'aprovar_acesso', 0, 'ID: 1 (Falha DB)', NULL, NULL),
(16, 1, NULL, '::1', '2025-05-19 00:03:45', 'aprovar_acesso', 1, 'ID: 1', NULL, NULL),
(17, NULL, NULL, '::1', '2025-05-19 00:04:08', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: jhonata@alimetasp.com)', NULL, NULL),
(18, 3, NULL, '::1', '2025-05-19 00:04:26', 'login_sucesso', 1, 'Usuário logado com sucesso.', NULL, NULL),
(19, 3, NULL, '::1', '2025-05-19 00:06:18', 'login_sucesso', 1, 'Usuário logado com sucesso.', NULL, NULL),
(20, 3, NULL, '::1', '2025-05-19 00:13:48', 'login_sucesso', 1, 'Usuário logado com sucesso.', NULL, NULL),
(21, 3, NULL, '::1', '2025-05-19 00:23:34', 'login_sucesso', 1, 'Usuário logado com sucesso.', NULL, NULL),
(22, 3, NULL, '::1', '2025-05-19 00:24:16', 'login_sucesso', 1, 'Usuário logado com sucesso.', NULL, NULL),
(23, 3, NULL, '::1', '2025-05-19 00:27:38', 'login_sucesso', 1, 'Usuário logado com sucesso.', NULL, NULL),
(24, 1, NULL, '::1', '2025-05-24 19:06:15', 'login_sucesso', 1, 'Usuário logado com sucesso.', NULL, NULL),
(25, 1, NULL, '::1', '2025-05-25 10:28:18', 'login_sucesso', 1, 'Usuário logado com sucesso.', NULL, NULL),
(26, 1, NULL, '::1', '2025-05-25 10:50:02', 'reset_senha_manual', 1, 'Senha temporária gerada para usuário ID: 3 pelo Admin ID: 1.', NULL, NULL),
(27, 1, NULL, '::1', '2025-05-25 11:21:53', 'update_empresa_cliente', 1, 'Empresa ID: 1 atualizada.', NULL, NULL),
(28, 1, NULL, '::1', '2025-05-25 11:34:09', 'criar_requisito_sucesso', 1, 'Req: TESTANDO', NULL, NULL),
(29, 1, NULL, '::1', '2025-05-25 11:34:45', 'excluir_requisito_tentativa', 0, 'ID: 1', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_erros`
--

CREATE TABLE `logs_erros` (
  `id` int(11) NOT NULL,
  `tipo_erro` varchar(50) DEFAULT NULL,
  `mensagem_erro` text NOT NULL,
  `arquivo_origem` varchar(255) DEFAULT NULL,
  `data_erro` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelos_auditoria`
--

CREATE TABLE `modelos_auditoria` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo_modelo_id` int(11) DEFAULT NULL,
  `versao_modelo` varchar(20) NOT NULL DEFAULT '1.0',
  `data_ultima_revisao_modelo` date DEFAULT NULL,
  `proxima_revisao_sugerida_modelo` date DEFAULT NULL,
  `global_ou_empresa_id` int(11) DEFAULT NULL COMMENT 'NULL para globais, empresa_id se customizado',
  `disponibilidade_plano_ids_json` text DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por` int(11) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por` int(11) DEFAULT NULL,
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Modelos de auditoria globais ou por empresa';

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelo_itens`
--

CREATE TABLE `modelo_itens` (
  `id` bigint(20) NOT NULL,
  `modelo_id` int(11) NOT NULL,
  `requisito_id` int(11) NOT NULL,
  `secao` varchar(255) DEFAULT NULL,
  `ordem_secao` int(11) DEFAULT 0,
  `ordem_item` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens (requisitos) de um modelo';

-- --------------------------------------------------------

--
-- Estrutura para tabela `plano_acao_evidencias`
--

CREATE TABLE `plano_acao_evidencias` (
  `id` bigint(20) NOT NULL,
  `plano_acao_id` bigint(20) NOT NULL,
  `nome_arquivo_original` varchar(255) NOT NULL,
  `nome_arquivo_armazenado` varchar(255) NOT NULL,
  `caminho_arquivo` varchar(512) NOT NULL,
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamanho_bytes` bigint(20) UNSIGNED DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `data_upload` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_upload_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Evidências para Planos de Ação';

-- --------------------------------------------------------

--
-- Estrutura para tabela `plataforma_campos_personalizados_definicao`
--

CREATE TABLE `plataforma_campos_personalizados_definicao` (
  `id` int(11) NOT NULL,
  `nome_campo_interno` varchar(50) NOT NULL COMMENT 'Chave única, ex: ref_contrato_cliente',
  `label_campo_exibicao` varchar(100) NOT NULL,
  `tipo_campo` enum('TEXTO_CURTO','TEXTO_LONGO','NUMERO_INT','NUMERO_DEC','DATA','LISTA_OPCOES_UNICA','LISTA_OPCOES_MULTIPLA','CHECKBOX_SIM_NAO') NOT NULL,
  `opcoes_lista_db` text DEFAULT NULL COMMENT 'JSON array de strings se tipo_campo for LISTA_OPCOES',
  `aplicavel_a_entidade_db` text NOT NULL COMMENT 'JSON array ex: ["AUDITORIA_GERAL", "ITEM_AUDITORIA"]',
  `disponivel_para_planos_ids_db` text DEFAULT NULL COMMENT 'JSON array de IDs de plataforma_planos_assinatura. NULL ou [] para todos.',
  `placeholder_campo` varchar(150) DEFAULT NULL,
  `texto_ajuda_campo` varchar(255) DEFAULT NULL,
  `obrigatorio` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por_admin_id` int(11) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por_admin_id` int(11) DEFAULT NULL,
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Definições de campos personalizados globais';

-- --------------------------------------------------------

--
-- Estrutura para tabela `plataforma_categorias_modelo`
--

CREATE TABLE `plataforma_categorias_modelo` (
  `id` int(11) NOT NULL,
  `nome_categoria_modelo` varchar(100) NOT NULL,
  `descricao_categoria_modelo` text DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por_admin_id` int(11) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorias para modelos globais';

-- --------------------------------------------------------

--
-- Estrutura para tabela `plataforma_comunicados`
--

CREATE TABLE `plataforma_comunicados` (
  `id` int(11) NOT NULL,
  `titulo_comunicado` varchar(255) NOT NULL,
  `conteudo_comunicado` text NOT NULL,
  `data_publicacao` datetime NOT NULL,
  `data_expiracao` datetime DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `plataforma_configuracoes_globais`
--

CREATE TABLE `plataforma_configuracoes_globais` (
  `id` int(11) NOT NULL,
  `config_chave` varchar(100) NOT NULL COMMENT 'Chave única para a configuração',
  `config_valor` text DEFAULT NULL COMMENT 'Valor da configuração, armazenado como JSON',
  `descricao_config` varchar(255) DEFAULT NULL,
  `modificado_por_admin_id` int(11) DEFAULT NULL,
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações globais serializadas da plataforma';

--
-- Despejando dados para a tabela `plataforma_configuracoes_globais`
--

INSERT INTO `plataforma_configuracoes_globais` (`id`, `config_chave`, `config_valor`, `descricao_config`, `modificado_por_admin_id`, `data_modificacao`) VALUES
(1, 'metodologia_risco', '{\"tipo_calculo_risco\":\"Matricial\",\"escala_impacto_labels\":[\"Baixo\",\"Médio\",\"Alto\",\"Crítico\"],\"escala_impacto_valores\":[1,2,3,5],\"escala_probabilidade_labels\":[\"Rara\",\"Improvável\",\"Possível\",\"Provável\",\"Quase Certa\"],\"escala_probabilidade_valores\":[1,2,3,4,6],\"niveis_risco_resultado_labels\":[\"Muito Baixo\",\"Baixo\",\"Médio\",\"Alto\",\"Muito Alto\",\"Extremo\"],\"niveis_risco_cores_hex\":[\"#28a745\",\"#17a2b8\",\"#ffc107\",\"#fd7e14\",\"#dc3545\",\"#6f42c1\"],\"matriz_risco_definicao\":[]}', 'Configuração global da metodologia de avaliação de riscos', 1, '2025-05-18 23:31:03');

-- --------------------------------------------------------

--
-- Estrutura para tabela `plataforma_niveis_criticidade_achado`
--

CREATE TABLE `plataforma_niveis_criticidade_achado` (
  `id` int(11) NOT NULL,
  `nome_nivel_criticidade` varchar(50) NOT NULL,
  `descricao_nivel_criticidade` text DEFAULT NULL,
  `valor_ordenacao` int(11) NOT NULL DEFAULT 0 COMMENT 'Para ordenar os níveis logicamente',
  `cor_hex_associada` varchar(7) DEFAULT '#6c757d' COMMENT 'Cor HEX para representação visual',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por_admin_id` int(11) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por_admin_id` int(11) DEFAULT NULL,
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo global de Níveis de Criticidade para Achados';

-- --------------------------------------------------------

--
-- Estrutura para tabela `plataforma_planos_assinatura`
--

CREATE TABLE `plataforma_planos_assinatura` (
  `id` int(11) NOT NULL,
  `nome_plano` varchar(100) NOT NULL,
  `descricao_plano` text DEFAULT NULL,
  `preco_mensal` decimal(10,2) DEFAULT 0.00,
  `limite_empresas_filhas` int(11) DEFAULT 0 COMMENT '0 para ilimitado ou não aplicável',
  `limite_gestores_por_empresa` int(11) DEFAULT 1,
  `limite_auditores_por_empresa` int(11) DEFAULT 5,
  `limite_usuarios_auditados_por_empresa` int(11) DEFAULT 0 COMMENT '0 para ilimitado',
  `limite_auditorias_ativas_por_empresa` int(11) DEFAULT 10,
  `limite_armazenamento_mb_por_empresa` int(11) DEFAULT 1024 COMMENT 'Em Megabytes',
  `permite_modelos_customizados_empresa` tinyint(1) NOT NULL DEFAULT 0,
  `permite_campos_personalizados_empresa` tinyint(1) NOT NULL DEFAULT 0,
  `funcionalidades_extras_json` text DEFAULT NULL COMMENT 'JSON com flags de outras features',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planos de assinatura oferecidos pela AcodITools';

--
-- Despejando dados para a tabela `plataforma_planos_assinatura`
--

INSERT INTO `plataforma_planos_assinatura` (`id`, `nome_plano`, `descricao_plano`, `preco_mensal`, `limite_empresas_filhas`, `limite_gestores_por_empresa`, `limite_auditores_por_empresa`, `limite_usuarios_auditados_por_empresa`, `limite_auditorias_ativas_por_empresa`, `limite_armazenamento_mb_por_empresa`, `permite_modelos_customizados_empresa`, `permite_campos_personalizados_empresa`, `funcionalidades_extras_json`, `ativo`, `data_criacao`, `data_modificacao`) VALUES
(1, 'Basico', 'Plano Simples para execução de auditoria.', 150.00, 0, 1, 5, 5, 10, 1024, 1, 1, NULL, 1, '2025-05-19 02:51:31', '2025-05-19 02:51:31');

-- --------------------------------------------------------

--
-- Estrutura para tabela `plataforma_tickets_suporte`
--

CREATE TABLE `plataforma_tickets_suporte` (
  `id` int(11) NOT NULL,
  `empresa_id_cliente` int(11) NOT NULL COMMENT 'FK para empresas - Empresa cliente que abriu o ticket',
  `usuario_solicitante_id` int(11) NOT NULL COMMENT 'FK para usuarios - Usuário da empresa cliente que abriu o ticket',
  `assunto_ticket` varchar(255) NOT NULL,
  `descricao_ticket` text NOT NULL COMMENT 'Descrição inicial do problema ou dúvida',
  `status_ticket` enum('Aberto','Em Andamento','Aguardando Cliente','Resolvido','Fechado') NOT NULL DEFAULT 'Aberto',
  `prioridade_ticket` enum('Baixa','Normal','Alta','Urgente') NOT NULL DEFAULT 'Normal',
  `admin_acoditools_responsavel_id` int(11) DEFAULT NULL COMMENT 'FK para usuarios (perfil=admin) - Admin da AcodITools designado',
  `data_abertura` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_ultima_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `data_fechamento` datetime DEFAULT NULL,
  `categoria_ticket` varchar(100) DEFAULT NULL COMMENT 'Ex: Dúvida Funcionalidade, Problema Técnico, Sugestão',
  `origem_ticket` enum('Portal Cliente','Email','Telefone') DEFAULT 'Portal Cliente' COMMENT 'Como o ticket foi aberto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tickets de suporte abertos pelas empresas clientes';

-- --------------------------------------------------------

--
-- Estrutura para tabela `plataforma_ticket_comentarios`
--

CREATE TABLE `plataforma_ticket_comentarios` (
  `id` bigint(20) NOT NULL,
  `ticket_id` int(11) NOT NULL COMMENT 'FK para plataforma_tickets_suporte',
  `usuario_id_autor` int(11) NOT NULL COMMENT 'FK para usuarios (quem escreveu o comentário/resposta)',
  `texto_comentario` text NOT NULL,
  `data_comentario` timestamp NOT NULL DEFAULT current_timestamp(),
  `origem_comentario` enum('admin_plataforma','cliente_empresa') NOT NULL DEFAULT 'admin_plataforma' COMMENT 'Quem enviou: Admin da AcodITools ou Usuário do Cliente',
  `privado_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 se o comentário for uma nota interna do admin, não visível ao cliente',
  `anexo_comentario_path` varchar(512) DEFAULT NULL COMMENT 'Caminho para um anexo opcional no comentário'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comentários, respostas e histórico de interações de um ticket';

-- --------------------------------------------------------

--
-- Estrutura para tabela `plataforma_tipos_nao_conformidade`
--

CREATE TABLE `plataforma_tipos_nao_conformidade` (
  `id` int(11) NOT NULL,
  `nome_tipo_nc` varchar(150) NOT NULL,
  `descricao_tipo_nc` text DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por_admin_id` int(11) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por_admin_id` int(11) DEFAULT NULL,
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo global de Tipos de Não Conformidade';

-- --------------------------------------------------------

--
-- Estrutura para tabela `requisitos_auditoria`
--

CREATE TABLE `requisitos_auditoria` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `norma_referencia` varchar(100) DEFAULT NULL,
  `versao_norma_aplicavel` varchar(50) DEFAULT NULL,
  `data_ultima_revisao_norma` date DEFAULT NULL,
  `guia_evidencia` text DEFAULT NULL,
  `objetivo_controle` text DEFAULT NULL,
  `tecnicas_sugeridas` text DEFAULT NULL,
  `peso` int(11) DEFAULT 1 COMMENT 'Criticidade geral ou peso para cálculo de risco',
  `global_ou_empresa_id` int(11) DEFAULT NULL COMMENT 'NULL para globais da AcodITools, empresa_id se customizado',
  `disponibilidade_plano_ids_json` text DEFAULT NULL COMMENT 'JSON array de IDs de plataforma_planos_assinatura',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por` int(11) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por` int(11) DEFAULT NULL,
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Requisitos de auditoria globais ou por empresa';

--
-- Despejando dados para a tabela `requisitos_auditoria`
--

INSERT INTO `requisitos_auditoria` (`id`, `codigo`, `nome`, `descricao`, `categoria`, `norma_referencia`, `versao_norma_aplicavel`, `data_ultima_revisao_norma`, `guia_evidencia`, `objetivo_controle`, `tecnicas_sugeridas`, `peso`, `global_ou_empresa_id`, `disponibilidade_plano_ids_json`, `ativo`, `criado_por`, `data_criacao`, `modificado_por`, `data_modificacao`) VALUES
(1, 'LGPD', 'TESTANDO', 'TESTE', 'TESTE DE REGISTRO', 'LGPD', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1, 1, '2025-05-25 14:34:09', 1, '2025-05-25 14:34:09');

-- --------------------------------------------------------

--
-- Estrutura para tabela `solicitacoes_acesso`
--

CREATE TABLE `solicitacoes_acesso` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `nome_completo` varchar(255) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `perfil_solicitado` enum('gestor_empresa','auditor_empresa','auditado_contato') NOT NULL DEFAULT 'auditado_contato',
  `motivo` text NOT NULL,
  `data_solicitacao` datetime DEFAULT current_timestamp(),
  `status` enum('pendente','aprovada','rejeitada') DEFAULT 'pendente',
  `data_aprovacao_rejeicao` datetime DEFAULT NULL,
  `observacoes_admin` text DEFAULT NULL,
  `admin_id_processou` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Solicitações de acesso de novos usuários';

--
-- Despejando dados para a tabela `solicitacoes_acesso`
--

INSERT INTO `solicitacoes_acesso` (`id`, `email`, `nome_completo`, `empresa_id`, `perfil_solicitado`, `motivo`, `data_solicitacao`, `status`, `data_aprovacao_rejeicao`, `observacoes_admin`, `admin_id_processou`) VALUES
(1, 'jhonata@alimentasp.com', 'Jhonata da Rocha', 1, 'auditado_contato', 'Sou gerente', '2025-05-18 23:59:28', 'aprovada', '2025-05-19 00:03:45', NULL, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `solicitacoes_reset_senha`
--

CREATE TABLE `solicitacoes_reset_senha` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `token_reset` varchar(64) DEFAULT NULL,
  `data_solicitacao` datetime NOT NULL DEFAULT current_timestamp(),
  `data_expiracao_token` datetime DEFAULT NULL,
  `status` enum('pendente','aprovada','rejeitada','utilizado') NOT NULL DEFAULT 'pendente',
  `admin_id_aprovou` int(11) DEFAULT NULL,
  `data_aprovacao` datetime DEFAULT NULL,
  `observacoes_admin` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Solicitações de reset de senha';

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('admin','gestor_empresa','auditor_empresa','auditado_contato') NOT NULL DEFAULT 'auditado_contato' COMMENT 'admin é da Acoditools, outros são de empresas clientes',
  `is_empresa_admin_cliente` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se um gestor_empresa tem superpoderes na sua empresa',
  `especialidade_auditor` text DEFAULT NULL COMMENT 'JSON ou CSV de especialidades se perfil for auditor_empresa',
  `certificacoes_auditor` text DEFAULT NULL COMMENT 'JSON ou CSV de certificações se perfil for auditor_empresa',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `data_cadastro` datetime NOT NULL DEFAULT current_timestamp(),
  `foto` varchar(255) DEFAULT NULL,
  `primeiro_acesso` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = Não é o primeiro acesso, 1 = Primeiro acesso',
  `data_ultimo_login` datetime DEFAULT NULL,
  `empresa_id` int(11) DEFAULT NULL COMMENT 'NULL para perfil admin (Acoditools)',
  `departamento_area_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuários da plataforma e das empresas clientes';

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `perfil`, `is_empresa_admin_cliente`, `especialidade_auditor`, `certificacoes_auditor`, `ativo`, `data_cadastro`, `foto`, `primeiro_acesso`, `data_ultimo_login`, `empresa_id`, `departamento_area_id`) VALUES
(1, 'Caio Almeida', 'admin@teste.com', '$2y$10$VPBeDqjwilkHWeKPQTV8/.KqGLvvwb/CZFjQyHS44GSZ8TugQ5CdS', 'admin', 0, NULL, NULL, 1, '2025-05-17 11:10:13', 'user_1_67c8a2a7552ac_1741202087.jpg', 0, NULL, NULL, NULL),
(3, 'Jhonata da Rocha', 'jhonata@alimentasp.com', '$2y$10$pd.78EJX6XCS4G/zQgidPO.YIxDtLv5hcPjpmYqOgUZi42z3wmTIC', 'gestor_empresa', 0, NULL, NULL, 1, '2025-05-19 00:03:45', NULL, 1, NULL, 1, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `auditorias`
--
ALTER TABLE `auditorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aud_empresa_status` (`empresa_id`,`status`),
  ADD KEY `idx_aud_auditor_status` (`auditor_responsavel_id`,`status`),
  ADD KEY `idx_aud_gestor_status` (`gestor_responsavel_id`,`status`),
  ADD KEY `idx_aud_modelo` (`modelo_id`),
  ADD KEY `idx_aud_equipe` (`equipe_id`),
  ADD KEY `idx_aud_criado_por` (`criado_por`),
  ADD KEY `idx_aud_mod_por` (`modificado_por`),
  ADD KEY `idx_aud_lider_equipe` (`auditor_lider_equipe_id`);

--
-- Índices de tabela `auditoria_comunicacoes`
--
ALTER TABLE `auditoria_comunicacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ac_auditoria_id` (`auditoria_id`),
  ADD KEY `idx_ac_usuario_remetente_id` (`usuario_remetente_id`);

--
-- Índices de tabela `auditoria_documentos_planejamento`
--
ALTER TABLE `auditoria_documentos_planejamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_adp_nome_armazenado` (`nome_arquivo_armazenado`),
  ADD KEY `idx_adp_auditoria_id` (`auditoria_id`),
  ADD KEY `idx_adp_usuario_upload` (`usuario_upload_id`);

--
-- Índices de tabela `auditoria_evidencias`
--
ALTER TABLE `auditoria_evidencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ae_nome_armazenado` (`nome_arquivo_armazenado`),
  ADD KEY `idx_ae_item_id` (`auditoria_item_id`),
  ADD KEY `idx_ae_usuario_upload` (`usuario_upload_id`);

--
-- Índices de tabela `auditoria_item_tipos_nc_selecionados`
--
ALTER TABLE `auditoria_item_tipos_nc_selecionados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_aitnc_item_tiponc` (`auditoria_item_id`,`tipo_nc_id`),
  ADD KEY `idx_aitnc_auditoria_item_id` (`auditoria_item_id`),
  ADD KEY `idx_aitnc_tipo_nc_id` (`tipo_nc_id`);

--
-- Índices de tabela `auditoria_itens`
--
ALTER TABLE `auditoria_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_auditoria_status` (`auditoria_id`,`status_conformidade`),
  ADD KEY `idx_ai_requisito_orig` (`requisito_id`),
  ADD KEY `idx_ai_modelo_item_orig` (`modelo_item_id`),
  ADD KEY `idx_ai_auditor_resp` (`respondido_por_auditor_id`),
  ADD KEY `idx_ai_gestor_rev` (`revisado_por_gestor_id`),
  ADD KEY `idx_ai_criticidade_achado` (`criticidade_achado_id`),
  ADD KEY `idx_ai_auditado_contato` (`auditado_contato_responsavel_id`);

--
-- Índices de tabela `auditoria_planos_acao`
--
ALTER TABLE `auditoria_planos_acao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_apa_auditoria_item_id` (`auditoria_item_id`),
  ADD KEY `idx_apa_empresa_id` (`empresa_id`),
  ADD KEY `idx_apa_responsavel_id` (`responsavel_id`),
  ADD KEY `idx_apa_status_acao` (`status_acao`),
  ADD KEY `idx_apa_verificado_por_id` (`verificado_por_id`),
  ADD KEY `idx_apa_criado_por` (`criado_por`),
  ADD KEY `idx_apa_modificado_por` (`modificado_por`);

--
-- Índices de tabela `auditoria_secao_responsaveis`
--
ALTER TABLE `auditoria_secao_responsaveis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_asr_auditoria_secao_auditor` (`auditoria_id`,`secao_modelo_nome`,`auditor_designado_id`),
  ADD KEY `idx_asr_auditoria_id` (`auditoria_id`),
  ADD KEY `idx_asr_auditor_designado_id` (`auditor_designado_id`);

--
-- Índices de tabela `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_empresas_cnpj` (`cnpj`),
  ADD KEY `idx_empresas_nome` (`nome`),
  ADD KEY `idx_empresas_plano_assinatura` (`plano_assinatura_id`),
  ADD KEY `idx_empresas_criado_por` (`criado_por`),
  ADD KEY `idx_empresas_modificado_por` (`modificado_por`);

--
-- Índices de tabela `empresa_departamentos_areas`
--
ALTER TABLE `empresa_departamentos_areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_empresa_depto_nome` (`empresa_id`,`nome_departamento_area`),
  ADD KEY `idx_edepto_empresa` (`empresa_id`),
  ADD KEY `idx_edepto_responsavel` (`responsavel_departamento_usuario_id`);

--
-- Índices de tabela `equipes`
--
ALTER TABLE `equipes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_equipe_empresa_nome` (`empresa_id`,`nome`),
  ADD KEY `fk_equipe_criado_gestor_idx` (`criado_por_gestor_id`),
  ADD KEY `fk_equipe_mod_gestor_idx` (`modificado_por_gestor_id`);

--
-- Índices de tabela `equipe_membros`
--
ALTER TABLE `equipe_membros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_equipe_membro` (`equipe_id`,`usuario_id`),
  ADD KEY `idx_eqm_usuario` (`usuario_id`);

--
-- Índices de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_usuario_id_ref` (`usuario_id`),
  ADD KEY `idx_logs_data_hora` (`data_hora`),
  ADD KEY `idx_logs_acao` (`acao`),
  ADD KEY `idx_logs_empresa_contexto` (`empresa_id_contexto`);

--
-- Índices de tabela `logs_erros`
--
ALTER TABLE `logs_erros`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `modelos_auditoria`
--
ALTER TABLE `modelos_auditoria`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_modelo_nome_global_empresa` (`nome`,`global_ou_empresa_id`),
  ADD KEY `idx_modelo_ativo_global` (`ativo`,`global_ou_empresa_id`),
  ADD KEY `fk_modelo_criado_por_idx` (`criado_por`),
  ADD KEY `fk_modelo_modificado_por_idx` (`modificado_por`),
  ADD KEY `fk_modelos_tipo_idx` (`tipo_modelo_id`),
  ADD KEY `fk_modelo_global_emp_id_constr` (`global_ou_empresa_id`);

--
-- Índices de tabela `modelo_itens`
--
ALTER TABLE `modelo_itens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_moditem_modelo_requisito_secao` (`modelo_id`,`requisito_id`,`secao`),
  ADD KEY `idx_moditem_modelo_ordem` (`modelo_id`,`ordem_secao`,`ordem_item`),
  ADD KEY `idx_moditem_requisito_ref_idx` (`requisito_id`);

--
-- Índices de tabela `plano_acao_evidencias`
--
ALTER TABLE `plano_acao_evidencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pae_nome_armazenado` (`nome_arquivo_armazenado`),
  ADD KEY `idx_pae_plano_acao_id` (`plano_acao_id`),
  ADD KEY `idx_pae_usuario_upload_id` (`usuario_upload_id`);

--
-- Índices de tabela `plataforma_campos_personalizados_definicao`
--
ALTER TABLE `plataforma_campos_personalizados_definicao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pcpd_nome_interno` (`nome_campo_interno`),
  ADD KEY `fk_pcpd_criado_admin` (`criado_por_admin_id`),
  ADD KEY `fk_pcpd_mod_admin` (`modificado_por_admin_id`);

--
-- Índices de tabela `plataforma_categorias_modelo`
--
ALTER TABLE `plataforma_categorias_modelo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pcm_nome` (`nome_categoria_modelo`),
  ADD KEY `fk_pcm_criado_admin_idx` (`criado_por_admin_id`);

--
-- Índices de tabela `plataforma_comunicados`
--
ALTER TABLE `plataforma_comunicados`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `plataforma_configuracoes_globais`
--
ALTER TABLE `plataforma_configuracoes_globais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_config_chave` (`config_chave`),
  ADD KEY `fk_pcfg_mod_admin` (`modificado_por_admin_id`);

--
-- Índices de tabela `plataforma_niveis_criticidade_achado`
--
ALTER TABLE `plataforma_niveis_criticidade_achado`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pnca_nome` (`nome_nivel_criticidade`),
  ADD UNIQUE KEY `uq_pnca_ordem` (`valor_ordenacao`),
  ADD KEY `fk_pnca_criado_admin` (`criado_por_admin_id`),
  ADD KEY `fk_pnca_mod_admin` (`modificado_por_admin_id`);

--
-- Índices de tabela `plataforma_planos_assinatura`
--
ALTER TABLE `plataforma_planos_assinatura`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_nome_plano` (`nome_plano`);

--
-- Índices de tabela `plataforma_tickets_suporte`
--
ALTER TABLE `plataforma_tickets_suporte`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pts_empresa_status` (`empresa_id_cliente`,`status_ticket`),
  ADD KEY `idx_pts_solicitante` (`usuario_solicitante_id`),
  ADD KEY `idx_pts_admin_resp` (`admin_acoditools_responsavel_id`),
  ADD KEY `idx_pts_status` (`status_ticket`),
  ADD KEY `idx_pts_prioridade` (`prioridade_ticket`);

--
-- Índices de tabela `plataforma_ticket_comentarios`
--
ALTER TABLE `plataforma_ticket_comentarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ptc_ticket_id` (`ticket_id`),
  ADD KEY `idx_ptc_usuario_autor` (`usuario_id_autor`);

--
-- Índices de tabela `plataforma_tipos_nao_conformidade`
--
ALTER TABLE `plataforma_tipos_nao_conformidade`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ptnc_nome` (`nome_tipo_nc`),
  ADD KEY `fk_ptnc_criado_admin` (`criado_por_admin_id`),
  ADD KEY `fk_ptnc_mod_admin` (`modificado_por_admin_id`);

--
-- Índices de tabela `requisitos_auditoria`
--
ALTER TABLE `requisitos_auditoria`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_requisitos_codigo_contexto` (`codigo`,`global_ou_empresa_id`),
  ADD KEY `idx_req_ativo_global` (`ativo`,`global_ou_empresa_id`),
  ADD KEY `idx_req_categoria` (`categoria`),
  ADD KEY `idx_req_norma` (`norma_referencia`),
  ADD KEY `fk_req_criado_por_idx` (`criado_por`),
  ADD KEY `fk_req_modificado_por_idx` (`modificado_por`),
  ADD KEY `fk_req_global_emp_id_constr` (`global_ou_empresa_id`);

--
-- Índices de tabela `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_solacesso_empresa_id_ref` (`empresa_id`),
  ADD KEY `idx_solacesso_admin_id_ref` (`admin_id_processou`),
  ADD KEY `idx_solacesso_status` (`status`);

--
-- Índices de tabela `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token_reset_val` (`token_reset`),
  ADD KEY `idx_solreset_usuario_id_ref` (`usuario_id`),
  ADD KEY `idx_solreset_admin_id_ref` (`admin_id_aprovou`),
  ADD KEY `idx_solreset_status` (`status`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuarios_email` (`email`),
  ADD KEY `idx_usuarios_empresa_id` (`empresa_id`),
  ADD KEY `idx_usuarios_perfil` (`perfil`),
  ADD KEY `idx_usuarios_ativo` (`ativo`),
  ADD KEY `idx_usuarios_departamento` (`departamento_area_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `auditorias`
--
ALTER TABLE `auditorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `auditoria_comunicacoes`
--
ALTER TABLE `auditoria_comunicacoes`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `auditoria_documentos_planejamento`
--
ALTER TABLE `auditoria_documentos_planejamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `auditoria_evidencias`
--
ALTER TABLE `auditoria_evidencias`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `auditoria_item_tipos_nc_selecionados`
--
ALTER TABLE `auditoria_item_tipos_nc_selecionados`
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
-- AUTO_INCREMENT de tabela `auditoria_secao_responsaveis`
--
ALTER TABLE `auditoria_secao_responsaveis`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `empresa_departamentos_areas`
--
ALTER TABLE `empresa_departamentos_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `equipes`
--
ALTER TABLE `equipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `equipe_membros`
--
ALTER TABLE `equipe_membros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de tabela `logs_erros`
--
ALTER TABLE `logs_erros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT de tabela `plano_acao_evidencias`
--
ALTER TABLE `plano_acao_evidencias`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `plataforma_campos_personalizados_definicao`
--
ALTER TABLE `plataforma_campos_personalizados_definicao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `plataforma_categorias_modelo`
--
ALTER TABLE `plataforma_categorias_modelo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `plataforma_comunicados`
--
ALTER TABLE `plataforma_comunicados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `plataforma_configuracoes_globais`
--
ALTER TABLE `plataforma_configuracoes_globais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `plataforma_niveis_criticidade_achado`
--
ALTER TABLE `plataforma_niveis_criticidade_achado`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `plataforma_planos_assinatura`
--
ALTER TABLE `plataforma_planos_assinatura`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `plataforma_tickets_suporte`
--
ALTER TABLE `plataforma_tickets_suporte`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `plataforma_ticket_comentarios`
--
ALTER TABLE `plataforma_ticket_comentarios`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `plataforma_tipos_nao_conformidade`
--
ALTER TABLE `plataforma_tipos_nao_conformidade`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `requisitos_auditoria`
--
ALTER TABLE `requisitos_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `auditorias`
--
ALTER TABLE `auditorias`
  ADD CONSTRAINT `fk_auditoria_auditor_resp_usr_constr` FOREIGN KEY (`auditor_responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_criado_usr_id_constr` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_empresa_id_constr` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_equipe_id_constr` FOREIGN KEY (`equipe_id`) REFERENCES `equipes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_gestor_resp_usr_constr` FOREIGN KEY (`gestor_responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_lider_eq_usr_constr` FOREIGN KEY (`auditor_lider_equipe_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_mod_usr_id_constr` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_modelo_id_constr` FOREIGN KEY (`modelo_id`) REFERENCES `modelos_auditoria` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `auditoria_comunicacoes`
--
ALTER TABLE `auditoria_comunicacoes`
  ADD CONSTRAINT `fk_ac_auditoria_id_constr` FOREIGN KEY (`auditoria_id`) REFERENCES `auditorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ac_remetente_usr_constr` FOREIGN KEY (`usuario_remetente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `auditoria_documentos_planejamento`
--
ALTER TABLE `auditoria_documentos_planejamento`
  ADD CONSTRAINT `fk_adp_auditoria_id_constr` FOREIGN KEY (`auditoria_id`) REFERENCES `auditorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_adp_usuario_upload_constr` FOREIGN KEY (`usuario_upload_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `auditoria_evidencias`
--
ALTER TABLE `auditoria_evidencias`
  ADD CONSTRAINT `fk_ae_item_id_constr` FOREIGN KEY (`auditoria_item_id`) REFERENCES `auditoria_itens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ae_usuario_upload_constr` FOREIGN KEY (`usuario_upload_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `auditoria_item_tipos_nc_selecionados`
--
ALTER TABLE `auditoria_item_tipos_nc_selecionados`
  ADD CONSTRAINT `fk_aitnc_item_id_constr` FOREIGN KEY (`auditoria_item_id`) REFERENCES `auditoria_itens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_aitnc_tiponc_id_constr` FOREIGN KEY (`tipo_nc_id`) REFERENCES `plataforma_tipos_nao_conformidade` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `auditoria_itens`
--
ALTER TABLE `auditoria_itens`
  ADD CONSTRAINT `fk_ai_auditado_contato_usr_constr` FOREIGN KEY (`auditado_contato_responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ai_auditoria_id_constr` FOREIGN KEY (`auditoria_id`) REFERENCES `auditorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ai_crit_achado_id_constr` FOREIGN KEY (`criticidade_achado_id`) REFERENCES `plataforma_niveis_criticidade_achado` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ai_modelo_item_orig_id_constr` FOREIGN KEY (`modelo_item_id`) REFERENCES `modelo_itens` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ai_requisito_orig_id_constr` FOREIGN KEY (`requisito_id`) REFERENCES `requisitos_auditoria` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ai_resp_auditor_usr_constr` FOREIGN KEY (`respondido_por_auditor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ai_rev_gestor_usr_constr` FOREIGN KEY (`revisado_por_gestor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `auditoria_planos_acao`
--
ALTER TABLE `auditoria_planos_acao`
  ADD CONSTRAINT `fk_apa_criado_usr_constr` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_apa_empresa_id_constr` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_apa_item_id_constr` FOREIGN KEY (`auditoria_item_id`) REFERENCES `auditoria_itens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_apa_mod_usr_constr` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_apa_responsavel_usr_constr` FOREIGN KEY (`responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_apa_verificado_usr_constr` FOREIGN KEY (`verificado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `auditoria_secao_responsaveis`
--
ALTER TABLE `auditoria_secao_responsaveis`
  ADD CONSTRAINT `fk_asr_auditor_usr_constr` FOREIGN KEY (`auditor_designado_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asr_auditoria_id_constr` FOREIGN KEY (`auditoria_id`) REFERENCES `auditorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `empresas`
--
ALTER TABLE `empresas`
  ADD CONSTRAINT `fk_empresas_criado_por_usr_constr` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_empresas_mod_por_usr_constr` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_empresas_plano_constr` FOREIGN KEY (`plano_assinatura_id`) REFERENCES `plataforma_planos_assinatura` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `empresa_departamentos_areas`
--
ALTER TABLE `empresa_departamentos_areas`
  ADD CONSTRAINT `fk_edepto_empresa_constr` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_edepto_responsavel_usr_constr` FOREIGN KEY (`responsavel_departamento_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `equipes`
--
ALTER TABLE `equipes`
  ADD CONSTRAINT `fk_equipe_criado_gestor_usr_constr` FOREIGN KEY (`criado_por_gestor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_equipe_empresa_id_constr` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_equipe_mod_gestor_usr_constr` FOREIGN KEY (`modificado_por_gestor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `equipe_membros`
--
ALTER TABLE `equipe_membros`
  ADD CONSTRAINT `fk_eqm_equipe_id_constr` FOREIGN KEY (`equipe_id`) REFERENCES `equipes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_eqm_usuario_id_constr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `logs_acesso`
--
ALTER TABLE `logs_acesso`
  ADD CONSTRAINT `fk_logs_empresa_ctx_id_constr` FOREIGN KEY (`empresa_id_contexto`) REFERENCES `empresas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_logs_usuario_id_constr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `modelos_auditoria`
--
ALTER TABLE `modelos_auditoria`
  ADD CONSTRAINT `fk_modelo_criado_usr_constr` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_modelo_global_emp_id_constr` FOREIGN KEY (`global_ou_empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_modelo_mod_usr_constr` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_modelo_tipo_cat_constr` FOREIGN KEY (`tipo_modelo_id`) REFERENCES `plataforma_categorias_modelo` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `plano_acao_evidencias`
--
ALTER TABLE `plano_acao_evidencias`
  ADD CONSTRAINT `fk_pae_plano_acao_id_constr` FOREIGN KEY (`plano_acao_id`) REFERENCES `auditoria_planos_acao` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pae_usuario_upload_constr` FOREIGN KEY (`usuario_upload_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `plataforma_campos_personalizados_definicao`
--
ALTER TABLE `plataforma_campos_personalizados_definicao`
  ADD CONSTRAINT `fk_pcpd_criado_admin_constr` FOREIGN KEY (`criado_por_admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pcpd_mod_admin_constr` FOREIGN KEY (`modificado_por_admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `plataforma_categorias_modelo`
--
ALTER TABLE `plataforma_categorias_modelo`
  ADD CONSTRAINT `fk_pcm_admin_constr` FOREIGN KEY (`criado_por_admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `plataforma_configuracoes_globais`
--
ALTER TABLE `plataforma_configuracoes_globais`
  ADD CONSTRAINT `fk_pcfg_mod_admin_constr` FOREIGN KEY (`modificado_por_admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `plataforma_niveis_criticidade_achado`
--
ALTER TABLE `plataforma_niveis_criticidade_achado`
  ADD CONSTRAINT `fk_pnca_criado_admin_constr` FOREIGN KEY (`criado_por_admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pnca_mod_admin_constr` FOREIGN KEY (`modificado_por_admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `plataforma_tickets_suporte`
--
ALTER TABLE `plataforma_tickets_suporte`
  ADD CONSTRAINT `fk_pts_admin_responsavel` FOREIGN KEY (`admin_acoditools_responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pts_empresa_cliente` FOREIGN KEY (`empresa_id_cliente`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pts_usuario_solicitante` FOREIGN KEY (`usuario_solicitante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `plataforma_ticket_comentarios`
--
ALTER TABLE `plataforma_ticket_comentarios`
  ADD CONSTRAINT `fk_ptc_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `plataforma_tickets_suporte` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ptc_usuario_autor` FOREIGN KEY (`usuario_id_autor`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `plataforma_tipos_nao_conformidade`
--
ALTER TABLE `plataforma_tipos_nao_conformidade`
  ADD CONSTRAINT `fk_ptnc_criado_admin_constr` FOREIGN KEY (`criado_por_admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ptnc_mod_admin_constr` FOREIGN KEY (`modificado_por_admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `requisitos_auditoria`
--
ALTER TABLE `requisitos_auditoria`
  ADD CONSTRAINT `fk_req_criado_usr_constr` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_req_global_emp_id_constr` FOREIGN KEY (`global_ou_empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_req_mod_usr_constr` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  ADD CONSTRAINT `fk_solacesso_admin_id_constr` FOREIGN KEY (`admin_id_processou`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solacesso_empresa_id_constr` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  ADD CONSTRAINT `fk_solreset_admin_id_constr` FOREIGN KEY (`admin_id_aprovou`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solreset_usuario_id_constr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
