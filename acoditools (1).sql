-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 08/05/2025 às 12:00
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
  `modelo_id` int(11) DEFAULT NULL COMMENT 'FK para modelos_auditoria - Modelo base utilizado',
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
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `equipe_id` int(11) DEFAULT NULL COMMENT 'FK para equipes - Equipe designada para a auditoria (alternativa ao auditor individual)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registra cada instância de auditoria realizada';

--
-- Despejando dados para a tabela `auditorias`
--

INSERT INTO `auditorias` (`id`, `titulo`, `empresa_id`, `modelo_id`, `auditor_responsavel_id`, `gestor_responsavel_id`, `escopo`, `objetivo`, `instrucoes`, `data_inicio_planejada`, `data_fim_planejada`, `data_inicio_real`, `data_conclusao_auditor`, `data_aprovacao_rejeicao_gestor`, `status`, `resultado_geral`, `observacoes_gerais_gestor`, `criado_por`, `data_criacao`, `modificado_por`, `data_modificacao`, `equipe_id`) VALUES
(5, 'teste', 4, 2, 12, 10, 'teste', 'teste', 'teste', '2025-04-23', '2025-04-23', NULL, NULL, '2025-05-06 23:52:52', 'Rejeitada', NULL, 'NÃO FEZ DIREITO ESSA POHA', 10, '2025-04-23 22:49:07', 10, '2025-05-06 21:52:52', NULL),
(11, 'teste', 4, 2, NULL, 10, 'teste', 'teste', 'teste', '2025-05-06', '2025-05-06', NULL, NULL, '2025-05-06 23:51:59', 'Aprovada', 'Parcialmente Conforme', 'TA RUIM MAS TA BOM', 10, '2025-05-06 12:49:42', 10, '2025-05-06 21:51:59', 1),
(12, 'Testando Nova auditoria', 4, 2, NULL, 10, 'teste de escopo', 'teste de objetivo', 'devera executar com urgencia', '2025-05-06', '2025-05-06', NULL, NULL, NULL, 'Planejada', NULL, NULL, 10, '2025-05-06 22:02:13', 10, '2025-05-06 22:36:06', 2),
(13, 'teste', 4, NULL, 12, 10, 'teste', 'teste', 'teste', '2025-05-09', '2025-05-22', NULL, NULL, NULL, 'Planejada', NULL, NULL, 10, '2025-05-07 17:11:44', 10, '2025-05-07 17:11:44', NULL),
(14, 'teste2', 4, NULL, 12, 10, 'teste', 'teste', 'teste', '2025-05-07', '2025-05-30', NULL, NULL, NULL, 'Planejada', NULL, NULL, 10, '2025-05-07 17:27:29', 10, '2025-05-07 17:27:29', NULL),
(15, 'teste model', 4, 2, NULL, 10, 'teste model', 'teste model', 'teste model', '2025-05-10', '2025-05-27', NULL, NULL, NULL, 'Planejada', NULL, NULL, 10, '2025-05-07 17:29:13', 10, '2025-05-07 17:29:13', 2);

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria_documentos_planejamento`
--

CREATE TABLE `auditoria_documentos_planejamento` (
  `id` int(11) NOT NULL,
  `auditoria_id` int(11) NOT NULL COMMENT 'FK para auditorias',
  `nome_arquivo_original` varchar(255) NOT NULL,
  `nome_arquivo_armazenado` varchar(255) NOT NULL COMMENT 'Nome seguro/único no servidor',
  `caminho_arquivo` varchar(512) NOT NULL COMMENT 'Caminho relativo do arquivo (pasta upload/auditorias/ID...)',
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamanho_bytes` bigint(20) UNSIGNED DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL COMMENT 'Descrição opcional do documento',
  `data_upload` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_upload_id` int(11) DEFAULT NULL COMMENT 'FK para usuarios (quem fez upload - o gestor)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documentos anexados pelo gestor na etapa de planejamento/criação';

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

--
-- Despejando dados para a tabela `auditoria_itens`
--

INSERT INTO `auditoria_itens` (`id`, `auditoria_id`, `requisito_id`, `modelo_item_id`, `codigo_item`, `nome_item`, `descricao_item`, `categoria_item`, `norma_item`, `guia_evidencia_item`, `peso_item`, `secao_item`, `ordem_item`, `status_conformidade`, `observacoes_auditor`, `data_resposta_auditor`, `respondido_por_auditor_id`, `status_revisao_gestor`, `observacoes_gestor`, `data_revisao_gestor`, `revisado_por_gestor_id`) VALUES
(17, 5, 5, NULL, 'A.6.1.1', 'Responsabilidades de segurança da informação', 'As responsabilidades e funções de segurança devem ser claramente definidas.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 1, 'Pendente', NULL, NULL, NULL, 'Revisado', NULL, '2025-05-06 18:52:52', 10),
(18, 5, 4, NULL, 'A.5.1.2', 'Revisão das políticas de segurança', 'As políticas devem ser revisadas regularmente e sempre que mudanças significativas ocorrerem.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 2, 'Pendente', NULL, NULL, NULL, 'Revisado', NULL, '2025-05-06 18:52:52', 10),
(19, 5, 6, NULL, 'A.6.1.2', 'Segregação de funções', 'As funções devem ser separadas para reduzir o risco de acesso não autorizado ou erro.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 3, 'Pendente', NULL, NULL, NULL, 'Revisado', NULL, '2025-05-06 18:52:52', 10),
(20, 5, 7, NULL, 'A.6.1.3', 'Contato com autoridades', 'Devem ser estabelecidos contatos com autoridades relevantes para segurança da informação.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 4, 'Pendente', NULL, NULL, NULL, 'Revisado', NULL, '2025-05-06 18:52:52', 10),
(21, 5, 3, NULL, 'A.5.1.1', 'Políticas de segurança da informação', 'Devem ser definidas, aprovadas por gestão e comunicadas a todos os envolvidos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 5, 'Pendente', NULL, NULL, NULL, 'Revisado', NULL, '2025-05-06 18:52:52', 10),
(22, 5, 8, NULL, 'A.6.1.4', 'Contato com grupos de interesse especial', 'Organizações devem manter contato com grupos de segurança ou fóruns do setor.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 6, 'Pendente', NULL, NULL, NULL, 'Revisado', NULL, '2025-05-06 18:52:52', 10),
(23, 5, 9, NULL, 'A.6.1.5', 'Segurança da informação no gerenciamento de projetos', 'Segurança da informação deve ser integrada ao gerenciamento de projetos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 7, 'Pendente', NULL, NULL, NULL, 'Revisado', NULL, '2025-05-06 18:52:52', 10),
(24, 5, 10, NULL, 'A.6.2.1', 'Dispositivos móveis e trabalho remoto', 'Políticas e medidas de segurança devem ser aplicadas ao uso de dispositivos móveis e trabalho remoto.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 8, 'Pendente', NULL, NULL, NULL, 'Revisado', NULL, '2025-05-06 18:52:52', 10),
(27, 11, 5, NULL, 'A.6.1.1', 'Responsabilidades de segurança da informação', 'As responsabilidades e funções de segurança devem ser claramente definidas.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 0, 'Pendente', NULL, NULL, NULL, 'Revisado', 'Esta bom essa poha', '2025-05-06 18:51:59', 10),
(28, 11, 4, NULL, 'A.5.1.2', 'Revisão das políticas de segurança', 'As políticas devem ser revisadas regularmente e sempre que mudanças significativas ocorrerem.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 1, 'Pendente', NULL, NULL, NULL, 'Revisado', 'Sei la, essa pika mesmo', '2025-05-06 18:51:59', 10),
(29, 11, 6, NULL, 'A.6.1.2', 'Segregação de funções', 'As funções devem ser separadas para reduzir o risco de acesso não autorizado ou erro.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 2, 'Pendente', NULL, NULL, NULL, 'Ação Solicitada', 'ta errado, tem que validar direito FILHO DA PUTA', '2025-05-06 18:51:59', 10),
(30, 11, 7, NULL, 'A.6.1.3', 'Contato com autoridades', 'Devem ser estabelecidos contatos com autoridades relevantes para segurança da informação.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 3, 'Pendente', NULL, NULL, NULL, 'Revisado', 'AI DELICIA', '2025-05-06 18:51:59', 10),
(31, 11, 3, NULL, 'A.5.1.1', 'Políticas de segurança da informação', 'Devem ser definidas, aprovadas por gestão e comunicadas a todos os envolvidos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 4, 'Pendente', NULL, NULL, NULL, 'Revisado', 'GOSTOSO', '2025-05-06 18:51:59', 10),
(32, 11, 8, NULL, 'A.6.1.4', 'Contato com grupos de interesse especial', 'Organizações devem manter contato com grupos de segurança ou fóruns do setor.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 5, 'Pendente', NULL, NULL, NULL, 'Revisado', 'UIIII', '2025-05-06 18:51:59', 10),
(33, 11, 9, NULL, 'A.6.1.5', 'Segurança da informação no gerenciamento de projetos', 'Segurança da informação deve ser integrada ao gerenciamento de projetos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 6, 'Pendente', NULL, NULL, NULL, 'Revisado', 'DILIÇA', '2025-05-06 18:51:59', 10),
(34, 11, 10, NULL, 'A.6.2.1', 'Dispositivos móveis e trabalho remoto', 'Políticas e medidas de segurança devem ser aplicadas ao uso de dispositivos móveis e trabalho remoto.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 7, 'Pendente', NULL, NULL, NULL, 'Revisado', 'MEU PAU', '2025-05-06 18:51:59', 10),
(35, 12, 5, NULL, 'A.6.1.1', 'Responsabilidades de segurança da informação', 'As responsabilidades e funções de segurança devem ser claramente definidas.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 0, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(36, 12, 4, NULL, 'A.5.1.2', 'Revisão das políticas de segurança', 'As políticas devem ser revisadas regularmente e sempre que mudanças significativas ocorrerem.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 1, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(37, 12, 6, NULL, 'A.6.1.2', 'Segregação de funções', 'As funções devem ser separadas para reduzir o risco de acesso não autorizado ou erro.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 2, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(38, 12, 7, NULL, 'A.6.1.3', 'Contato com autoridades', 'Devem ser estabelecidos contatos com autoridades relevantes para segurança da informação.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 3, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(39, 12, 3, NULL, 'A.5.1.1', 'Políticas de segurança da informação', 'Devem ser definidas, aprovadas por gestão e comunicadas a todos os envolvidos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 4, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(40, 12, 8, NULL, 'A.6.1.4', 'Contato com grupos de interesse especial', 'Organizações devem manter contato com grupos de segurança ou fóruns do setor.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 5, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(41, 12, 9, NULL, 'A.6.1.5', 'Segurança da informação no gerenciamento de projetos', 'Segurança da informação deve ser integrada ao gerenciamento de projetos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 6, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(42, 12, 10, NULL, 'A.6.2.1', 'Dispositivos móveis e trabalho remoto', 'Políticas e medidas de segurança devem ser aplicadas ao uso de dispositivos móveis e trabalho remoto.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 7, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(43, 13, 3, NULL, 'A.5.1.1', 'Políticas de segurança da informação', 'Devem ser definidas, aprovadas por gestão e comunicadas a todos os envolvidos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 0, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(44, 13, 5, NULL, 'A.6.1.1', 'Responsabilidades de segurança da informação', 'As responsabilidades e funções de segurança devem ser claramente definidas.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 1, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(45, 13, 7, NULL, 'A.6.1.3', 'Contato com autoridades', 'Devem ser estabelecidos contatos com autoridades relevantes para segurança da informação.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, NULL, 2, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(46, 13, 76, NULL, 'A.17.3', 'Treinamento de Conformidade', 'Capacitar equipe sobre requisitos legais', 'Conformidade', 'ISO/IEC 27002', NULL, 1, NULL, 3, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(47, 13, 45, NULL, 'A.16.1', 'Continuidade de Negócios', 'Planejar continuidade das operações', 'Continuidade', 'ISO/IEC 27002', NULL, 1, NULL, 4, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(48, 13, 88, NULL, 'A.10.4', 'Criptografia de Backup', 'Proteger backups com criptografia', 'Criptografia', 'ISO/IEC 27002', NULL, 1, NULL, 5, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(49, 13, 11, NULL, 'A.5.1', 'Política de Segurança da Informação', 'Estabelecer uma política para gerenciar a segurança da informação', 'Governança', 'ISO/IEC 27002', NULL, 1, NULL, 6, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(50, 13, 77, NULL, 'B.1.4', 'Riscos de Terceiros', 'Gerenciar riscos associados a terceiros', 'Gestão de Riscos', 'ISO/IEC 27003', NULL, 1, NULL, 7, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(51, 13, 53, NULL, 'B.2.2', 'Escopo do SGSI', 'Definir o escopo do sistema de gestão', 'Implementação SGSI', 'ISO/IEC 27003', NULL, 1, NULL, 8, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(52, 13, 82, NULL, 'C.2.3', 'Relatórios de Auditoria', 'Gerar relatórios de auditorias realizadas', 'Monitoramento', 'ISO/IEC 27004', NULL, 1, NULL, 9, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(53, 14, 3, NULL, 'A.5.1.1', 'Políticas de segurança da informação', 'Devem ser definidas, aprovadas por gestão e comunicadas a todos os envolvidos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Controles organizacionais', 0, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(54, 14, 4, NULL, 'A.5.1.2', 'Revisão das políticas de segurança', 'As políticas devem ser revisadas regularmente e sempre que mudanças significativas ocorrerem.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Controles organizacionais', 1, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(55, 14, 5, NULL, 'A.6.1.1', 'Responsabilidades de segurança da informação', 'As responsabilidades e funções de segurança devem ser claramente definidas.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Controles organizacionais', 2, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(56, 14, 6, NULL, 'A.6.1.2', 'Segregação de funções', 'As funções devem ser separadas para reduzir o risco de acesso não autorizado ou erro.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Controles organizacionais', 3, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(57, 14, 7, NULL, 'A.6.1.3', 'Contato com autoridades', 'Devem ser estabelecidos contatos com autoridades relevantes para segurança da informação.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Controles organizacionais', 4, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(58, 14, 8, NULL, 'A.6.1.4', 'Contato com grupos de interesse especial', 'Organizações devem manter contato com grupos de segurança ou fóruns do setor.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Controles organizacionais', 5, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(59, 14, 9, NULL, 'A.6.1.5', 'Segurança da informação no gerenciamento de projetos', 'Segurança da informação deve ser integrada ao gerenciamento de projetos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Controles organizacionais', 6, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(60, 14, 10, NULL, 'A.6.2.1', 'Dispositivos móveis e trabalho remoto', 'Políticas e medidas de segurança devem ser aplicadas ao uso de dispositivos móveis e trabalho remoto.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Controles organizacionais', 7, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(61, 14, 31, NULL, 'A.10.2', 'Gestão de Chaves', 'Gerenciar chaves criptográficas com segurança', 'Criptografia', 'ISO/IEC 27002', NULL, 1, 'Criptografia', 8, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(62, 14, 69, NULL, 'A.10.3', 'Criptografia de Dados em Trânsito', 'Proteger dados em trânsito com criptografia', 'Criptografia', 'ISO/IEC 27002', NULL, 1, 'Criptografia', 9, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(63, 14, 88, NULL, 'A.10.4', 'Criptografia de Backup', 'Proteger backups com criptografia', 'Criptografia', 'ISO/IEC 27002', NULL, 1, 'Criptografia', 10, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(64, 14, 36, NULL, 'A.12.2', 'Registro de Incidentes', 'Registrar e analisar incidentes de segurança', 'Gestão de Incidentes', 'ISO/IEC 27002', NULL, 1, 'Gestão de Incidentes', 11, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(65, 14, 37, NULL, 'A.12.3', 'Resposta a Incidentes', 'Estabelecer plano de resposta a incidentes', 'Gestão de Incidentes', 'ISO/IEC 27002', NULL, 1, 'Gestão de Incidentes', 12, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(66, 14, 71, NULL, 'A.12.4', 'Notificação de Incidentes', 'Notificar incidentes às partes relevantes', 'Gestão de Incidentes', 'ISO/IEC 27002', NULL, 1, 'Gestão de Incidentes', 13, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(67, 14, 17, NULL, 'A.6.3', 'Treinamento de Conscientização', 'Capacitar funcionários sobre segurança da informação', 'Recursos Humanos', 'ISO/IEC 27002', NULL, 1, 'Recursos Humanos', 14, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(68, 14, 18, NULL, 'A.6.4', 'Processo Disciplinar', 'Definir ações para violações de segurança', 'Recursos Humanos', 'ISO/IEC 27002', NULL, 1, 'Recursos Humanos', 15, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(69, 14, 56, NULL, 'B.3.2', 'Comunicação SGSI', 'Comunicar políticas e objetivos do SGSI', 'Governança', 'ISO/IEC 27003', NULL, 1, 'Governança', 16, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(70, 14, 53, NULL, 'B.2.2', 'Escopo do SGSI', 'Definir o escopo do sistema de gestão', 'Implementação SGSI', 'ISO/IEC 27003', NULL, 1, 'Implementação SGSI', 17, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(71, 14, 60, NULL, 'C.1.2', 'Coleta de Dados', 'Coletar dados para métricas de segurança', 'Monitoramento', 'ISO/IEC 27004', NULL, 1, 'Monitoramento', 18, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(72, 14, 61, NULL, 'C.1.3', 'Análise de Métricas', 'Analisar métricas para melhoria', 'Monitoramento', 'ISO/IEC 27004', NULL, 1, 'Monitoramento', 19, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(73, 14, 62, NULL, 'C.2.1', 'Relatórios de Desempenho', 'Gerar relatórios de desempenho do SGSI', 'Monitoramento', 'ISO/IEC 27004', NULL, 1, 'Monitoramento', 20, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(74, 15, 4, NULL, 'A.5.1.2', 'Revisão das políticas de segurança', 'As políticas devem ser revisadas regularmente e sempre que mudanças significativas ocorrerem.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Seção 5', 0, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(75, 15, 3, NULL, 'A.5.1.1', 'Políticas de segurança da informação', 'Devem ser definidas, aprovadas por gestão e comunicadas a todos os envolvidos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Seção 5', 1, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(76, 15, 5, NULL, 'A.6.1.1', 'Responsabilidades de segurança da informação', 'As responsabilidades e funções de segurança devem ser claramente definidas.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Seção 6', 2, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(77, 15, 6, NULL, 'A.6.1.2', 'Segregação de funções', 'As funções devem ser separadas para reduzir o risco de acesso não autorizado ou erro.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Seção 6', 3, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(78, 15, 7, NULL, 'A.6.1.3', 'Contato com autoridades', 'Devem ser estabelecidos contatos com autoridades relevantes para segurança da informação.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Seção 6', 4, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(79, 15, 8, NULL, 'A.6.1.4', 'Contato com grupos de interesse especial', 'Organizações devem manter contato com grupos de segurança ou fóruns do setor.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Seção 6', 5, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(80, 15, 9, NULL, 'A.6.1.5', 'Segurança da informação no gerenciamento de projetos', 'Segurança da informação deve ser integrada ao gerenciamento de projetos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Seção 6', 6, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL),
(81, 15, 10, NULL, 'A.6.2.1', 'Dispositivos móveis e trabalho remoto', 'Políticas e medidas de segurança devem ser aplicadas ao uso de dispositivos móveis e trabalho remoto.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 'Seção 6', 7, 'Pendente', NULL, NULL, NULL, 'Pendente', NULL, NULL, NULL);

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
-- Estrutura para tabela `auditoria_secao_responsaveis`
--

CREATE TABLE `auditoria_secao_responsaveis` (
  `id` bigint(20) NOT NULL,
  `auditoria_id` int(11) NOT NULL,
  `secao_modelo_nome` varchar(255) NOT NULL COMMENT 'Nome da seção do modelo (ex: "A.5 Políticas")',
  `auditor_designado_id` int(11) NOT NULL COMMENT 'FK para usuarios (auditor da equipe designado para esta seção)',
  `data_atribuicao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Atribuição de auditores a seções de um modelo para uma auditoria específica.';

--
-- Despejando dados para a tabela `auditoria_secao_responsaveis`
--

INSERT INTO `auditoria_secao_responsaveis` (`id`, `auditoria_id`, `secao_modelo_nome`, `auditor_designado_id`, `data_atribuicao`) VALUES
(1, 15, 'Seção 5', 12, '2025-05-07 17:29:13'),
(2, 15, 'Seção 6', 12, '2025-05-07 17:29:13');

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
(4, 'Gabriel Sugador', '08494375000167', 'Gabriel Sugador de mandioca', 'Seila MORA NO CU DO CARAI', 'Gabriel Filho JR', '(11) 99999-9999', 'gabriel@teste.com', 'logo_empresa_4_1745270551.png', '2025-04-16 23:39:08', NULL, 1, '2025-04-21 21:22:31', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipes`
--

CREATE TABLE `equipes` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL COMMENT 'FK para empresas - Empresa que a equipe pertence',
  `nome` varchar(255) NOT NULL COMMENT 'Nome da equipe (ex: Equipe de TI, Equipe de ISO 27001)',
  `descricao` text DEFAULT NULL COMMENT 'Descrição opcional da equipe',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo (pode ser usada), 0 = Inativo',
  `criado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário que criou a equipe',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário que modificou por último',
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Gerencia Equipes de Auditoria por Empresa';

--
-- Despejando dados para a tabela `equipes`
--

INSERT INTO `equipes` (`id`, `empresa_id`, `nome`, `descricao`, `ativo`, `criado_por`, `data_criacao`, `modificado_por`, `data_modificacao`) VALUES
(1, 4, 'Equipe Responsavel por comer cu de curioso', 'Vai comer geral', 1, 10, '2025-05-06 12:35:10', 10, '2025-05-06 12:40:41'),
(2, 4, 'Recursos Desumanos', 'Membro para sugar ate o talo', 1, 10, '2025-05-06 20:38:03', 10, '2025-05-06 20:38:03');

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipe_membros`
--

CREATE TABLE `equipe_membros` (
  `id` int(11) NOT NULL,
  `equipe_id` int(11) NOT NULL COMMENT 'FK para equipes',
  `usuario_id` int(11) NOT NULL COMMENT 'FK para usuarios (perfil=auditor) - Membro da equipe',
  `ativo_na_equipe` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo na equipe, 0 = Inativo (manteve histórico)',
  `data_adesao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Associação entre Equipes e Usuários (Auditores)';

--
-- Despejando dados para a tabela `equipe_membros`
--

INSERT INTO `equipe_membros` (`id`, `equipe_id`, `usuario_id`, `ativo_na_equipe`, `data_adesao`) VALUES
(5, 2, 12, 1, '2025-05-06 20:38:19');

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
(232, 1, '::1', '2025-04-21 15:53:59', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(233, 12, '::1', '2025-04-21 16:34:01', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(234, 1, '::1', '2025-04-21 18:22:15', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(235, NULL, '::1', '2025-04-21 18:22:31', 'upload_logo_sucesso', 1, 'Empresa ID: 4, Arquivo: logo_empresa_4_1745270551.png'),
(236, 1, '::1', '2025-04-21 18:22:31', 'editar_empresa_sucesso', 1, 'ID: 4'),
(237, 12, '::1', '2025-04-21 18:22:51', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(238, 12, '::1', '2025-04-21 18:25:38', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(239, NULL, '::1', '2025-04-21 18:31:37', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: sarahsempai@teste.com)'),
(240, 12, '::1', '2025-04-21 18:31:56', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(241, 1, '::1', '2025-04-22 10:56:38', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(242, 1, '::1', '2025-04-22 11:30:03', 'editar_usuario_falha_db', 0, 'Erro DB ou nenhuma alteração para ID: 10'),
(243, 1, '::1', '2025-04-22 11:33:33', 'editar_usuario_sucesso', 1, 'Dados atualizados para ID: 10'),
(244, 10, '::1', '2025-04-22 11:37:01', 'solicitar_senha_sucesso', 1, 'Solicitação de reset de senha enviada.'),
(245, 1, '::1', '2025-04-22 11:37:08', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(246, 1, '::1', '2025-04-22 11:37:33', 'aprovar_reset_senha', 1, 'ID: 16'),
(247, 10, '::1', '2025-04-22 11:37:51', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(248, 1, '::1', '2025-04-22 12:50:24', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(249, 12, '::1', '2025-04-22 12:55:33', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(250, 1, '::1', '2025-04-22 18:43:45', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(251, 1, '::1', '2025-04-22 18:44:18', 'criar_requisito_sucesso', 1, 'Req: TESTE'),
(252, 10, '::1', '2025-04-22 18:44:30', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(253, 10, '::1', '2025-04-22 19:03:25', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(254, 10, '::1', '2025-04-22 19:56:14', 'criar_auditoria_falha', 0, 'SQLSTATE[HY093]: Invalid parameter number'),
(255, 10, '::1', '2025-04-22 19:59:37', 'criar_auditoria_falha', 0, 'SQLSTATE[HY093]: Invalid parameter number'),
(256, 10, '::1', '2025-04-23 09:40:54', 'criar_auditoria_falha', 0, 'SQLSTATE[HY093]: Invalid parameter number'),
(257, 10, '::1', '2025-04-23 09:47:08', 'criar_auditoria_falha', 0, 'SQLSTATE[HY093]: Invalid parameter number'),
(258, 10, '::1', '2025-04-23 09:47:16', 'criar_auditoria_falha', 0, 'SQLSTATE[HY093]: Invalid parameter number'),
(259, 1, '::1', '2025-04-23 13:37:12', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(260, 1, '::1', '2025-04-23 14:08:14', 'criar_modelo_falha_db', 0, 'Falha: Erro DB ao criar modelo.'),
(261, 1, '::1', '2025-04-23 14:11:49', 'criar_modelo_falha_db', 0, 'Falha: Erro DB ao criar modelo.'),
(262, 1, '::1', '2025-04-23 14:18:01', 'criar_modelo_sucesso', 1, 'Modelo criado: teste modelo'),
(263, 1, '::1', '2025-04-23 14:18:34', 'add_req_modelo', 1, 'Mod: 1, Req: 2'),
(264, 1, '::1', '2025-04-23 14:21:10', 'criar_modelo_sucesso', 1, 'Modelo criado: ANEXO A'),
(265, 1, '::1', '2025-04-23 14:21:26', 'add_req_modelo', 1, 'Mod: 2, Req: 2'),
(266, 1, '::1', '2025-04-23 14:21:39', 'edit_modelo_sucesso', 1, 'ID: 2'),
(267, 1, '::1', '2025-04-23 14:21:42', 'edit_modelo_sucesso', 1, 'ID: 2'),
(268, 1, '::1', '2025-04-23 14:21:42', 'edit_modelo_sucesso', 1, 'ID: 2'),
(269, 10, '::1', '2025-04-23 14:22:29', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(270, 1, '::1', '2025-04-23 17:19:22', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(271, 1, '::1', '2025-04-23 17:19:53', 'exportar_requisitos', 1, 'Iniciada exportação de requisitos para CSV.'),
(272, 1, '::1', '2025-04-23 17:19:53', 'exportar_requisitos', 1, 'Exportação CSV concluída. 1 requisitos exportados.'),
(273, 1, '::1', '2025-04-23 17:25:34', 'importar_req_inicio', 1, 'Iniciando importação de: csvteste.csv'),
(274, 1, '::1', '2025-04-23 17:25:34', 'importar_req_sucesso', 1, 'Importado: csvteste.csv. Resumo: 8 criados, 0 valid, 0 db.'),
(275, 1, '::1', '2025-04-23 17:25:40', 'excluir_requisito_sucesso', 1, 'ID: 2'),
(276, 1, '::1', '2025-04-23 17:26:14', 'add_req_modelo', 1, 'Mod: 2, Req: 3'),
(277, 1, '::1', '2025-04-23 17:26:33', 'add_req_modelo', 1, 'Mod: 2, Req: 4'),
(278, 1, '::1', '2025-04-23 17:26:42', 'add_req_modelo', 1, 'Mod: 2, Req: 5'),
(279, 1, '::1', '2025-04-23 17:26:47', 'add_req_modelo', 1, 'Mod: 2, Req: 6'),
(280, 1, '::1', '2025-04-23 17:26:52', 'add_req_modelo', 1, 'Mod: 2, Req: 7'),
(281, 1, '::1', '2025-04-23 17:26:57', 'add_req_modelo', 1, 'Mod: 2, Req: 8'),
(282, 1, '::1', '2025-04-23 17:27:03', 'add_req_modelo', 1, 'Mod: 2, Req: 9'),
(283, 1, '::1', '2025-04-23 17:27:09', 'add_req_modelo', 1, 'Mod: 2, Req: 10'),
(284, 1, '::1', '2025-04-23 17:31:42', 'ordem_modelo_sucesso_ajax', 1, 'Mod: 2, Sec: ANEXO A'),
(285, 1, '::1', '2025-04-23 17:31:46', 'rem_req_modelo', 1, 'Mod: 2, Item: 3'),
(286, 1, '::1', '2025-04-23 17:31:49', 'rem_req_modelo', 1, 'Mod: 2, Item: 10'),
(287, 1, '::1', '2025-04-23 17:32:06', 'ordem_modelo_sucesso_ajax', 1, 'Mod: 2, Sec: ANEXO A'),
(288, 1, '::1', '2025-04-23 17:32:08', 'ordem_modelo_sucesso_ajax', 1, 'Mod: 2, Sec: ANEXO A'),
(289, 1, '::1', '2025-04-23 17:44:58', 'rem_req_modelo', 1, 'Mod: 2, Item: 4'),
(290, 1, '::1', '2025-04-23 17:44:59', 'rem_req_modelo', 1, 'Mod: 2, Item: 6'),
(291, 1, '::1', '2025-04-23 17:45:01', 'rem_req_modelo', 1, 'Mod: 2, Item: 7'),
(292, 1, '::1', '2025-04-23 17:45:04', 'rem_req_modelo', 1, 'Mod: 2, Item: 5'),
(293, 1, '::1', '2025-04-23 17:45:06', 'rem_req_modelo', 1, 'Mod: 2, Item: 8'),
(294, 1, '::1', '2025-04-23 17:45:07', 'rem_req_modelo', 1, 'Mod: 2, Item: 9'),
(295, 1, '::1', '2025-04-23 17:45:26', 'add_req_modelo_multi', 1, 'Mod: 2, Qtd Sucesso: 2, IDs: 3,4, Secao: Seção 5'),
(296, 1, '::1', '2025-04-23 17:45:35', 'add_req_modelo_multi', 1, 'Mod: 2, Qtd Sucesso: 6, IDs: 5,6,7,8,9,10, Secao: Seção 6'),
(297, 1, '::1', '2025-04-23 17:45:57', 'desativar_requisito', 1, 'ID: 5'),
(298, 1, '::1', '2025-04-23 17:46:19', 'ativar_requisito', 1, 'ID: 5'),
(299, 1, '::1', '2025-04-23 17:47:24', 'rem_req_modelo', 1, 'Mod: 2, Item: 11'),
(300, 1, '::1', '2025-04-23 17:47:32', 'add_req_modelo_multi', 1, 'Mod: 2, Qtd Sucesso: 1, IDs: 3, Secao: Seção 5'),
(301, 10, '::1', '2025-04-23 19:45:40', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(302, 1, '::1', '2025-04-26 15:26:15', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(303, NULL, '::1', '2025-04-29 11:34:36', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: caio@admin.com)'),
(304, 1, '::1', '2025-04-29 11:34:41', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(305, 10, '::1', '2025-04-29 11:34:55', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(306, 1, '::1', '2025-05-06 08:00:16', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(307, 10, '::1', '2025-05-06 08:00:29', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(308, 10, '::1', '2025-05-06 09:49:42', 'criar_auditoria_sucesso', 1, 'Auditoria criada ID: 11. Título: teste. Modo Atrib: equipe.'),
(309, 1, '::1', '2025-05-06 09:53:40', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(310, 10, '::1', '2025-05-06 09:54:39', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(311, 10, '::1', '2025-05-06 14:00:31', 'gestor_ajax_csrf_fail', 0, 'Token inválido no AJAX.'),
(312, 10, '::1', '2025-05-06 15:33:07', 'gestor_assoc_equipe_auditor_ok', 1, 'Auditor ID: 12 atualizado equipes. Add: . Rem: 1'),
(313, 10, '::1', '2025-05-06 15:33:14', 'gestor_assoc_equipe_auditor_ok', 1, 'Auditor ID: 12 atualizado equipes. Add: 1. Rem: '),
(314, 10, '::1', '2025-05-06 16:59:46', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(315, 10, '::1', '2025-05-06 17:37:32', 'gestor_assoc_equipes_aud_ok', 1, 'Auditor: 12. Add:. Rem:1'),
(316, 10, '::1', '2025-05-06 17:37:36', 'gestor_assoc_equipes_aud_ok', 1, 'Auditor: 12. Add:1. Rem:'),
(317, 10, '::1', '2025-05-06 17:38:19', 'gestor_assoc_equipes_aud_ok', 1, 'Auditor: 12. Add:2. Rem:1'),
(318, 10, '::1', '2025-05-06 18:20:15', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(319, 10, '::1', '2025-05-06 18:51:40', 'revisao_auditoria_salva', 1, 'Auditoria ID: 11, Ação Final: salvar_parcial'),
(320, 10, '::1', '2025-05-06 18:51:59', 'revisao_auditoria_salva', 1, 'Auditoria ID: 11, Ação Final: aprovar'),
(321, 10, '::1', '2025-05-06 18:52:52', 'revisao_auditoria_salva', 1, 'Auditoria ID: 5, Ação Final: rejeitar'),
(322, 10, '::1', '2025-05-06 19:02:13', 'criar_auditoria_sucesso', 1, 'Auditoria ID: 12. Título: Testando Nova auditoria. Modo Atrib: equipe.'),
(323, 12, '::1', '2025-05-07 13:15:19', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(324, 12, '::1', '2025-05-07 13:33:03', 'get_minhas_auditorias_auditor_fail', 0, 'Erro DB: HY093'),
(325, 10, '::1', '2025-05-07 13:33:24', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(326, 10, '::1', '2025-05-07 13:34:18', 'criar_auditoria_falha_db', 0, 'Falha DB/Proc para título: teste. Modo Atrib: auditor.'),
(327, 10, '::1', '2025-05-07 13:34:59', 'criar_auditoria_falha_db', 0, 'Falha DB/Proc para título: teste. Modo Atrib: auditor.'),
(328, 10, '::1', '2025-05-07 14:11:44', 'criar_auditoria_sucesso', 1, 'Auditoria ID: 13. Título: teste. Modo Atrib: auditor.'),
(329, NULL, '::1', '2025-05-07 14:12:00', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: sarahsempai@teste.com)'),
(330, NULL, '::1', '2025-05-07 14:12:07', 'login_falha', 0, 'Tentativa de login falhou: Credenciais inválidas. (Email: sarahsempai@teste.com)'),
(331, 12, '::1', '2025-05-07 14:12:15', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(332, 12, '::1', '2025-05-07 14:12:18', 'get_minhas_auditorias_auditor_fail', 0, 'Erro DB: HY093'),
(333, 10, '::1', '2025-05-07 14:26:35', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(334, 10, '::1', '2025-05-07 14:27:29', 'criar_auditoria_sucesso', 1, 'Auditoria ID: 14. Título: teste2. Modo Atrib: auditor.'),
(335, 10, '::1', '2025-05-07 14:29:13', 'criar_auditoria_sucesso', 1, 'Auditoria ID: 15. Título: teste model. Modo Atrib: equipe.'),
(336, 12, '::1', '2025-05-07 14:30:03', 'login_sucesso', 1, 'Usuário logado com sucesso.'),
(337, 12, '::1', '2025-05-07 14:30:06', 'get_minhas_auditorias_auditor_fail', 0, 'Erro DB: HY093'),
(338, 12, '::1', '2025-05-07 14:31:52', 'get_minhas_auditorias_auditor_fail', 0, 'Erro DB: HY093'),
(339, 12, '::1', '2025-05-07 14:43:03', 'get_minhas_auditorias_auditor_err_db', 0, 'Erro DB: HY093');

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

--
-- Despejando dados para a tabela `modelos_auditoria`
--

INSERT INTO `modelos_auditoria` (`id`, `nome`, `descricao`, `ativo`, `criado_por`, `data_criacao`, `modificado_por`, `data_modificacao`) VALUES
(2, 'ANEXO A', 'Auditoria completa do anexo A.', 1, 1, '2025-04-23 17:21:10', 1, '2025-04-23 17:21:10');

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

--
-- Despejando dados para a tabela `modelo_itens`
--

INSERT INTO `modelo_itens` (`id`, `modelo_id`, `requisito_id`, `secao`, `ordem_secao`, `ordem_item`) VALUES
(12, 2, 4, 'Seção 5', 0, 1),
(13, 2, 5, 'Seção 6', 1, 0),
(14, 2, 6, 'Seção 6', 1, 1),
(15, 2, 7, 'Seção 6', 1, 2),
(16, 2, 8, 'Seção 6', 1, 3),
(17, 2, 9, 'Seção 6', 1, 4),
(18, 2, 10, 'Seção 6', 1, 5),
(19, 2, 3, 'Seção 5', 0, 2);

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

--
-- Despejando dados para a tabela `requisitos_auditoria`
--

INSERT INTO `requisitos_auditoria` (`id`, `codigo`, `nome`, `descricao`, `categoria`, `norma_referencia`, `guia_evidencia`, `peso`, `ativo`, `criado_por`, `data_criacao`, `modificado_por`, `data_modificacao`) VALUES
(3, 'A.5.1.1', 'Políticas de segurança da informação', 'Devem ser definidas, aprovadas por gestão e comunicadas a todos os envolvidos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 1, 1, '2025-04-23 20:25:34', 1, '2025-04-23 20:25:34'),
(4, 'A.5.1.2', 'Revisão das políticas de segurança', 'As políticas devem ser revisadas regularmente e sempre que mudanças significativas ocorrerem.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 1, 1, '2025-04-23 20:25:34', 1, '2025-04-23 20:25:34'),
(5, 'A.6.1.1', 'Responsabilidades de segurança da informação', 'As responsabilidades e funções de segurança devem ser claramente definidas.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 1, 1, '2025-04-23 20:25:34', 1, '2025-04-23 20:46:19'),
(6, 'A.6.1.2', 'Segregação de funções', 'As funções devem ser separadas para reduzir o risco de acesso não autorizado ou erro.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 1, 1, '2025-04-23 20:25:34', 1, '2025-04-23 20:25:34'),
(7, 'A.6.1.3', 'Contato com autoridades', 'Devem ser estabelecidos contatos com autoridades relevantes para segurança da informação.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 1, 1, '2025-04-23 20:25:34', 1, '2025-04-23 20:25:34'),
(8, 'A.6.1.4', 'Contato com grupos de interesse especial', 'Organizações devem manter contato com grupos de segurança ou fóruns do setor.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 1, 1, '2025-04-23 20:25:34', 1, '2025-04-23 20:25:34'),
(9, 'A.6.1.5', 'Segurança da informação no gerenciamento de projetos', 'Segurança da informação deve ser integrada ao gerenciamento de projetos.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 1, 1, '2025-04-23 20:25:34', 1, '2025-04-23 20:25:34'),
(10, 'A.6.2.1', 'Dispositivos móveis e trabalho remoto', 'Políticas e medidas de segurança devem ser aplicadas ao uso de dispositivos móveis e trabalho remoto.', 'Controles organizacionais', 'ISO/IEC 27001:2022', NULL, 1, 1, 1, '2025-04-23 20:25:34', 1, '2025-04-23 20:25:34'),
(11, 'A.5.1', 'Política de Segurança da Informação', 'Estabelecer uma política para gerenciar a segurança da informação', 'Governança', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(12, 'A.5.2', 'Revisão da Política', 'Revisar a política de segurança regularmente', 'Governança', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(13, 'A.5.3', 'Organização da Segurança', 'Definir papéis e responsabilidades de segurança', 'Governança', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(14, 'A.5.4', 'Segregação de Funções', 'Separar funções para evitar conflitos de interesse', 'Governança', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(15, 'A.6.1', 'Triagem de Pessoal', 'Verificar antecedentes de novos funcionários', 'Recursos Humanos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(16, 'A.6.2', 'Termos de Emprego', 'Incluir responsabilidades de segurança nos contratos', 'Recursos Humanos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(17, 'A.6.3', 'Treinamento de Conscientização', 'Capacitar funcionários sobre segurança da informação', 'Recursos Humanos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(18, 'A.6.4', 'Processo Disciplinar', 'Definir ações para violações de segurança', 'Recursos Humanos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(19, 'A.7.1', 'Inventário de Ativos', 'Identificar e catalogar ativos de informação', 'Gestão de Ativos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(20, 'A.7.2', 'Propriedade de Ativos', 'Atribuir responsáveis pelos ativos', 'Gestão de Ativos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(21, 'A.7.3', 'Classificação da Informação', 'Classificar informações por sensibilidade', 'Gestão de Ativos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(22, 'A.7.4', 'Rotulagem de Informações', 'Rotular informações conforme classificação', 'Gestão de Ativos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(23, 'A.8.1', 'Controle de Acesso Físico', 'Proteger áreas com controles físicos', 'Segurança Física', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(24, 'A.8.2', 'Manutenção de Equipamentos', 'Realizar manutenção regular de equipamentos', 'Segurança Física', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(25, 'A.8.3', 'Proteção contra Ameaças Ambientais', 'Proteger ativos contra desastres naturais', 'Segurança Física', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(26, 'A.9.1', 'Política de Controle de Acesso', 'Definir regras para acesso a sistemas', 'Controle de Acesso', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(27, 'A.9.2', 'Gestão de Usuários', 'Gerenciar criação e remoção de contas', 'Controle de Acesso', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(28, 'A.9.3', 'Autenticação de Usuários', 'Implementar autenticação robusta', 'Controle de Acesso', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(29, 'A.9.4', 'Privilégios de Acesso', 'Conceder acesso mínimo necessário', 'Controle de Acesso', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(30, 'A.10.1', 'Controles Criptográficos', 'Usar criptografia para proteger dados', 'Criptografia', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(31, 'A.10.2', 'Gestão de Chaves', 'Gerenciar chaves criptográficas com segurança', 'Criptografia', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(32, 'A.11.1', 'Gestão de Operações', 'Documentar procedimentos operacionais', 'Operações', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(33, 'A.11.2', 'Gestão de Capacidade', 'Monitorar e planejar capacidade de TI', 'Operações', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(34, 'A.11.3', 'Proteção contra Malware', 'Implementar controles contra malware', 'Operações', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(35, 'A.12.1', 'Gestão de Incidentes', 'Definir processo para gerenciar incidentes', 'Gestão de Incidentes', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(36, 'A.12.2', 'Registro de Incidentes', 'Registrar e analisar incidentes de segurança', 'Gestão de Incidentes', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(37, 'A.12.3', 'Resposta a Incidentes', 'Estabelecer plano de resposta a incidentes', 'Gestão de Incidentes', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(38, 'A.13.1', 'Controles de Rede', 'Proteger redes contra acessos não autorizados', 'Segurança de Rede', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(39, 'A.13.2', 'Segurança de Transferência', 'Proteger dados durante transferências', 'Segurança de Rede', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(40, 'A.14.1', 'Aquisição de Sistemas', 'Definir requisitos de segurança para novos sistemas', 'Desenvolvimento', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:28', 1, '2025-04-26 18:32:28'),
(41, 'A.14.2', 'Desenvolvimento Seguro', 'Adotar práticas seguras no desenvolvimento', 'Desenvolvimento', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(42, 'A.14.3', 'Teste de Segurança', 'Testar sistemas antes da implantação', 'Desenvolvimento', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(43, 'A.15.1', 'Relações com Fornecedores', 'Estabelecer acordos de segurança com fornecedores', 'Gestão de Fornecedores', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(44, 'A.15.2', 'Monitoramento de Fornecedores', 'Monitorar conformidade dos fornecedores', 'Gestão de Fornecedores', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(45, 'A.16.1', 'Continuidade de Negócios', 'Planejar continuidade das operações', 'Continuidade', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(46, 'A.16.2', 'Teste de Continuidade', 'Testar planos de continuidade regularmente', 'Continuidade', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(47, 'A.17.1', 'Conformidade Legal', 'Garantir conformidade com leis aplicáveis', 'Conformidade', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(48, 'A.17.2', 'Auditoria de Segurança', 'Realizar auditorias regulares de segurança', 'Conformidade', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(49, 'B.1.1', 'Análise de Riscos', 'Identificar e avaliar riscos de segurança', 'Gestão de Riscos', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(50, 'B.1.2', 'Tratamento de Riscos', 'Definir ações para mitigar riscos', 'Gestão de Riscos', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(51, 'B.1.3', 'Monitoramento de Riscos', 'Acompanhar riscos continuamente', 'Gestão de Riscos', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(52, 'B.2.1', 'Planejamento SGSI', 'Planejar a implementação do SGSI', 'Implementação SGSI', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(53, 'B.2.2', 'Escopo do SGSI', 'Definir o escopo do sistema de gestão', 'Implementação SGSI', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(54, 'B.2.3', 'Documentação SGSI', 'Manter documentação do SGSI', 'Implementação SGSI', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(55, 'B.3.1', 'Suporte da Alta Direção', 'Garantir apoio da liderança para o SGSI', 'Governança', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(56, 'B.3.2', 'Comunicação SGSI', 'Comunicar políticas e objetivos do SGSI', 'Governança', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(57, 'B.4.1', 'Melhoria Contínua', 'Implementar melhorias no SGSI', 'Melhoria', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(58, 'B.4.2', 'Análise Crítica', 'Revisar o SGSI periodicamente', 'Melhoria', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(59, 'C.1.1', 'Métricas de Segurança', 'Definir métricas para avaliar o SGSI', 'Monitoramento', 'ISO/IEC 27004', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(60, 'C.1.2', 'Coleta de Dados', 'Coletar dados para métricas de segurança', 'Monitoramento', 'ISO/IEC 27004', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(61, 'C.1.3', 'Análise de Métricas', 'Analisar métricas para melhoria', 'Monitoramento', 'ISO/IEC 27004', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(62, 'C.2.1', 'Relatórios de Desempenho', 'Gerar relatórios de desempenho do SGSI', 'Monitoramento', 'ISO/IEC 27004', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(63, 'C.2.2', 'Revisão de Métricas', 'Revisar métricas regularmente', 'Monitoramento', 'ISO/IEC 27004', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(64, 'A.5.5', 'Gestão de Vulnerabilidades', 'Identificar e corrigir vulnerabilidades', 'Operações', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(65, 'A.6.5', 'Acordos de Confidencialidade', 'Exigir NDAs para dados sensíveis', 'Recursos Humanos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(66, 'A.7.5', 'Descarte Seguro', 'Garantir descarte seguro de ativos', 'Gestão de Ativos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(67, 'A.8.4', 'Monitoramento de Acesso Físico', 'Monitorar entradas e saídas em áreas seguras', 'Segurança Física', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(68, 'A.9.5', 'Revisão de Acesso', 'Revisar permissões de acesso periodicamente', 'Controle de Acesso', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(69, 'A.10.3', 'Criptografia de Dados em Trânsito', 'Proteger dados em trânsito com criptografia', 'Criptografia', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(70, 'A.11.4', 'Gestão de Mudanças', 'Controlar mudanças em sistemas de TI', 'Operações', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(71, 'A.12.4', 'Notificação de Incidentes', 'Notificar incidentes às partes relevantes', 'Gestão de Incidentes', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(72, 'A.13.3', 'Segmentação de Rede', 'Implementar segmentação para maior segurança', 'Segurança de Rede', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(73, 'A.14.4', 'Manutenção de Sistemas', 'Manter sistemas atualizados e seguros', 'Desenvolvimento', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(74, 'A.15.3', 'Auditoria de Fornecedores', 'Auditar fornecedores regularmente', 'Gestão de Fornecedores', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(75, 'A.16.3', 'Recuperação de Desastres', 'Planejar recuperação após desastres', 'Continuidade', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(76, 'A.17.3', 'Treinamento de Conformidade', 'Capacitar equipe sobre requisitos legais', 'Conformidade', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(77, 'B.1.4', 'Riscos de Terceiros', 'Gerenciar riscos associados a terceiros', 'Gestão de Riscos', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(78, 'B.2.4', 'Integração de Controles', 'Integrar controles ao SGSI', 'Implementação SGSI', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(79, 'B.3.3', 'Alocação de Recursos', 'Garantir recursos suficientes para o SGSI', 'Governança', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(80, 'B.4.3', 'Correção de Não Conformidades', 'Corrigir desvios identificados no SGSI', 'Melhoria', 'ISO/IEC 27003', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(81, 'C.1.4', 'Indicadores de Risco', 'Definir indicadores para riscos de segurança', 'Monitoramento', 'ISO/IEC 27004', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(82, 'C.2.3', 'Relatórios de Auditoria', 'Gerar relatórios de auditorias realizadas', 'Monitoramento', 'ISO/IEC 27004', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(83, 'A.5.6', 'Gestão de Mudanças Organizacionais', 'Controlar mudanças organizacionais', 'Governança', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(84, 'A.6.6', 'Conscientização de Terceiros', 'Capacitar terceiros sobre segurança', 'Recursos Humanos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(85, 'A.7.6', 'Transferência de Ativos', 'Controlar transferência de ativos', 'Gestão de Ativos', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(86, 'A.8.5', 'Segurança de Equipamentos Externos', 'Proteger equipamentos fora das instalações', 'Segurança Física', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(87, 'A.9.6', 'Gestão de Credenciais', 'Proteger credenciais de acesso', 'Controle de Acesso', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(88, 'A.10.4', 'Criptografia de Backup', 'Proteger backups com criptografia', 'Criptografia', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(89, 'A.11.5', 'Monitoramento de Sistemas', 'Monitorar sistemas para detectar anomalias', 'Operações', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29'),
(90, 'A.12.5', 'Lições Aprendidas', 'Documentar lições de incidentes', 'Gestão de Incidentes', 'ISO/IEC 27002', NULL, 1, 1, 1, '2025-04-26 18:32:29', 1, '2025-04-26 18:32:29');

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
(15, 12, '2025-04-21 15:52:17', 'aprovada', 1, '2025-04-21 15:52:32', NULL, NULL),
(16, 10, '2025-04-22 11:37:01', 'aprovada', 1, '2025-04-22 11:37:33', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('admin','auditor','gestor','usuario') NOT NULL DEFAULT 'usuario',
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
(10, 'Jhonata', 'jhonata@teste.com', '$2y$10$u8LY2v4bANOCI7zdzNnH..4zb9.LGTf0x16D/iktdT4ucobqjZclG', 'gestor', 1, '2025-03-15 16:33:27', 'user_10_1744855693_fe268483.jpg', 0, 4),
(12, 'sarah sempaio', 'sarahsempai@teste.com', '$2y$10$mu.p7Rh7nV0Mjew733j0ju/Vqdjbab427iNBAj/BxGNDLnaMOiLhO', 'auditor', 1, '2025-04-16 23:41:33', 'user_12_1744857808_297fc61f.jpg', 0, 4);

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
  ADD KEY `fk_auditoria_modificado_por` (`modificado_por`),
  ADD KEY `idx_auditoria_equipe` (`equipe_id`);

--
-- Índices de tabela `auditoria_documentos_planejamento`
--
ALTER TABLE `auditoria_documentos_planejamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome_arquivo_armazenado` (`nome_arquivo_armazenado`),
  ADD KEY `idx_documento_auditoria` (`auditoria_id`),
  ADD KEY `fk_documento_usuario` (`usuario_upload_id`);

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
-- Índices de tabela `auditoria_secao_responsaveis`
--
ALTER TABLE `auditoria_secao_responsaveis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_auditoria_secao_auditor` (`auditoria_id`,`secao_modelo_nome`),
  ADD KEY `fk_asr_auditoria` (`auditoria_id`),
  ADD KEY `fk_asr_auditor` (`auditor_designado_id`);

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
-- Índices de tabela `equipes`
--
ALTER TABLE `equipes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_equipe_empresa_nome` (`empresa_id`,`nome`),
  ADD KEY `fk_equipe_empresa` (`empresa_id`),
  ADD KEY `fk_equipe_criado_por` (`criado_por`),
  ADD KEY `fk_equipe_modificado_por` (`modificado_por`);

--
-- Índices de tabela `equipe_membros`
--
ALTER TABLE `equipe_membros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_equipe_membro` (`equipe_id`,`usuario_id`),
  ADD KEY `fk_equipe_membro_usuario` (`usuario_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
-- AUTO_INCREMENT de tabela `auditoria_itens`
--
ALTER TABLE `auditoria_itens`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT de tabela `auditoria_planos_acao`
--
ALTER TABLE `auditoria_planos_acao`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `auditoria_secao_responsaveis`
--
ALTER TABLE `auditoria_secao_responsaveis`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `equipes`
--
ALTER TABLE `equipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `equipe_membros`
--
ALTER TABLE `equipe_membros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=340;

--
-- AUTO_INCREMENT de tabela `modelos_auditoria`
--
ALTER TABLE `modelos_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `modelo_itens`
--
ALTER TABLE `modelo_itens`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de tabela `requisitos_auditoria`
--
ALTER TABLE `requisitos_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT de tabela `solicitacoes_acesso`
--
ALTER TABLE `solicitacoes_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `solicitacoes_reset_senha`
--
ALTER TABLE `solicitacoes_reset_senha`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
  ADD CONSTRAINT `fk_auditoria_equipe` FOREIGN KEY (`equipe_id`) REFERENCES `equipes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_gestor` FOREIGN KEY (`gestor_responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_modelo` FOREIGN KEY (`modelo_id`) REFERENCES `modelos_auditoria` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auditoria_modificado_por` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `auditoria_documentos_planejamento`
--
ALTER TABLE `auditoria_documentos_planejamento`
  ADD CONSTRAINT `fk_documento_auditoria` FOREIGN KEY (`auditoria_id`) REFERENCES `auditorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documento_usuario` FOREIGN KEY (`usuario_upload_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Restrições para tabelas `auditoria_secao_responsaveis`
--
ALTER TABLE `auditoria_secao_responsaveis`
  ADD CONSTRAINT `fk_asr_auditor` FOREIGN KEY (`auditor_designado_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asr_auditoria` FOREIGN KEY (`auditoria_id`) REFERENCES `auditorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `empresas`
--
ALTER TABLE `empresas`
  ADD CONSTRAINT `empresas_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `empresas_ibfk_2` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `equipes`
--
ALTER TABLE `equipes`
  ADD CONSTRAINT `fk_equipe_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_equipe_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_equipe_modificado_por` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `equipe_membros`
--
ALTER TABLE `equipe_membros`
  ADD CONSTRAINT `fk_equipe_membro_equipe` FOREIGN KEY (`equipe_id`) REFERENCES `equipes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_equipe_membro_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
