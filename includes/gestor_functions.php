<?php
// includes/gestor_functions.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php'; // Confere se config inclui db.php (seu código anterior incluía os dois)
// Incluir funcoes_upload para a lógica de mover arquivos temporários dentro da transação
require_once __DIR__ . '/funcoes_upload.php';


/**
 * Busca contagens relevantes para o dashboard do Gestor, filtradas pela sua empresa.
 */
function getGestorDashboardStats(PDO $conexao, int $empresa_id): array {
    $stats = [
        'total_ativas' => 0,
        'para_revisao' => 0,
        'nao_conformidades_abertas' => 0, // NCs ou Parciais
        'auditores_ativos' => 0,
        'equipes_ativas' => 0
    ];
    try {
        // Contagem de status de auditorias
        $sqlStatus = "SELECT status, COUNT(*) as total FROM auditorias WHERE empresa_id = :eid GROUP BY status";
        $stmtStatus = $conexao->prepare($sqlStatus);
        $stmtStatus->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmtStatus->execute();
        $statusCounts = $stmtStatus->fetchAll(PDO::FETCH_KEY_PAIR);

        $stats['total_ativas'] = ($statusCounts['Planejada'] ?? 0)
                              + ($statusCounts['Em Andamento'] ?? 0)
                              + ($statusCounts['Pausada'] ?? 0);
        $stats['para_revisao'] = ($statusCounts['Concluída (Auditor)'] ?? 0);
         // NOTA: "Em Revisão" são as que o gestor já começou a revisar.
         // Talvez total_ativas devesse incluir "Em Revisão" ou ter outro stat para "Em Andamento (Gestor)".
         // Pela lógica original, 'ativas' são as que ainda não chegaram para revisão. Mantendo original.


        // Contagem de não conformidades ou parciais abertas (em auditorias não finalizadas pelo gestor)
        $sqlNC = "SELECT COUNT(ai.id)
                  FROM auditoria_itens ai
                  JOIN auditorias a ON ai.auditoria_id = a.id
                  WHERE a.empresa_id = :eid
                    AND ai.status_conformidade IN ('Não Conforme', 'Parcial') -- Incluir Parcial no count de
                    AND a.status NOT IN ('Aprovada', 'Rejeitada', 'Cancelada')";
        $stmtNC = $conexao->prepare($sqlNC);
        $stmtNC->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmtNC->execute();
        $stats['nao_conformidades_abertas'] = (int) $stmtNC->fetchColumn();

        // Contagem de auditores ativos da empresa do gestor
        $stmtAuditores = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE empresa_id = :eid AND perfil = 'auditor' AND ativo = 1");
        $stmtAuditores->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmtAuditores->execute();
        $stats['auditores_ativos'] = (int) $stmtAuditores->fetchColumn();

        // NOVO: Contagem de equipes ativas da empresa do gestor
         $stmtEquipes = $conexao->prepare("SELECT COUNT(*) FROM equipes WHERE empresa_id = :eid AND ativo = 1");
        $stmtEquipes->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmtEquipes->execute();
        $stats['equipes_ativas'] = (int) $stmtEquipes->fetchColumn();


    } catch (PDOException $e) {
        error_log("Erro getGestorDashboardStats (Empresa ID: $empresa_id): " . $e->getMessage());
    }
    return $stats;
}

/**
 * Busca todas as equipes da empresa do gestor com contagem de membros E PAGINAÇÃO.
 */
function getTodasEquipesDaEmpresaPaginado(PDO $conexao, int $empresa_id, array $filtros = [], int $pagina_atual = 1, int $itens_por_pagina = 15): array {
    $offset = ($pagina_atual - 1) * $itens_por_pagina;
    $resultado = ['equipes' => [], 'paginacao' => []];
    $params = [':empresa_id' => $empresa_id];
    $sql_where_clauses = ["e.empresa_id = :empresa_id"];

    if (!empty($filtros['nome'])) {
        $sql_where_clauses[] = "e.nome LIKE :nome";
        $params[':nome'] = '%' . $filtros['nome'] . '%';
    }
    if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
        $sql_where_clauses[] = "e.ativo = :ativo_status";
        $params[':ativo_status'] = (int)$filtros['ativo'];
    }
    $sql_where = implode(" AND ", $sql_where_clauses);

    try {
        // Contar total
        $sql_count = "SELECT COUNT(e.id) FROM equipes e WHERE $sql_where";
        $stmt_count = $conexao->prepare($sql_count);
        $stmt_count->execute($params);
        $total_itens = (int)$stmt_count->fetchColumn();

        // Buscar equipes
        $sql_select = "SELECT e.id, e.nome, e.descricao, e.ativo,
                       (SELECT COUNT(*) FROM equipe_membros em WHERE em.equipe_id = e.id) as total_membros
                       FROM equipes e
                       WHERE $sql_where
                       ORDER BY e.nome ASC
                       LIMIT :limit OFFSET :offset";
        
        $stmt_select = $conexao->prepare($sql_select);
        // Adicionar params de paginação
        $params_com_pag = $params;
        $params_com_pag[':limit'] = $itens_por_pagina;
        $params_com_pag[':offset'] = $offset;

        foreach ($params_com_pag as $key => &$val) { // Passar por referência para bindValue
            if ($key === ':limit' || $key === ':offset') {
                $stmt_select->bindValue($key, $val, PDO::PARAM_INT);
            } else {
                $stmt_select->bindValue($key, $val);
            }
        }
        unset($val);

        $stmt_select->execute();
        $resultado['equipes'] = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

        $total_paginas = ($total_itens > 0 && $itens_por_pagina > 0) ? ceil($total_itens / $itens_por_pagina) : 0;
        $resultado['paginacao'] = [
            'pagina_atual' => $pagina_atual,
            'total_paginas' => (int)$total_paginas,
            'total_itens' => (int)$total_itens,
            'itens_por_pagina' => $itens_por_pagina
        ];

    } catch (PDOException $e) {
        error_log("Erro getTodasEquipesDaEmpresaPaginado (Empresa $empresa_id): " . $e->getMessage());
        // Retornar estrutura vazia em caso de erro
        $resultado['paginacao'] = ['pagina_atual' => 1, 'total_paginas' => 0, 'total_itens' => 0, 'itens_por_pagina' => $itens_por_pagina];
    }
    return $resultado;
}


/**
 * Busca as últimas N auditorias pendentes de revisão pelo gestor para a empresa.
 * Inclui o nome do responsável (Auditor ou Equipe).
 */
function getAuditoriasParaRevisar(PDO $conexao, int $empresa_id, int $limit = 5): array {
    $sql = "SELECT a.id, a.titulo, a.data_conclusao_auditor,
            u.nome as nome_auditor, -- Nome do auditor individual (se atribuído)
            e.nome as nome_equipe -- Nome da equipe (se atribuída)
            FROM auditorias a
            LEFT JOIN usuarios u ON a.auditor_responsavel_id = u.id -- JOIN com auditor
            LEFT JOIN equipes e ON a.equipe_id = e.id -- JOIN com equipe
            WHERE a.empresa_id = :eid AND a.status = 'Concluída (Auditor)'
            ORDER BY a.data_conclusao_auditor DESC, a.id DESC
            LIMIT :limit";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

         // Formatar quem é o responsável (Auditor ou Equipe) para exibição
         foreach ($results as &$auditoria) {
            if (!empty($auditoria['nome_equipe'])) { // Verifica o nome da equipe do JOIN
                 $auditoria['responsavel_display'] = 'Equipe: ' . htmlspecialchars($auditoria['nome_equipe']);
                 $auditoria['responsavel_tipo'] = 'Equipe'; // Pode ser útil para ícone
            } elseif (!empty($auditoria['nome_auditor'])) { // Verifica o nome do auditor do JOIN
                 $auditoria['responsavel_display'] = 'Auditor: ' . htmlspecialchars($auditoria['nome_auditor']);
                 $auditoria['responsavel_tipo'] = 'Auditor'; // Pode ser útil para ícone
            } else {
                 $auditoria['responsavel_display'] = 'Não atribuído';
                 $auditoria['responsavel_tipo'] = 'Nenhum';
            }
             // Remover colunas JOIN não mais necessárias para esta exibição
             unset($auditoria['nome_auditor'], $auditoria['nome_equipe']);
        }
        unset($auditoria); // Desfazer referência

        return $results;

    } catch (PDOException $e) {
        error_log("Erro getAuditoriasParaRevisar (Empresa ID: $empresa_id): " . $e->getMessage());
        return [];
    }
}

/**
 * Busca as últimas N auditorias modificadas recentemente para a empresa.
 */
// Essa função não precisou de alteração para as novas features, mas é listada para contexto.
function getAuditoriasRecentesEmpresa(PDO $conexao, int $empresa_id, int $limit = 5): array {
    $sql = "SELECT a.id, a.titulo, a.status, a.data_modificacao,
            u.nome as nome_auditor, -- Inclui nome do auditor individual se houver
            e.nome as nome_equipe -- Inclui nome da equipe se houver
            FROM auditorias a
            LEFT JOIN usuarios u ON a.auditor_responsavel_id = u.id
            LEFT JOIN equipes e ON a.equipe_id = e.id
            WHERE a.empresa_id = :eid
            ORDER BY a.data_modificacao DESC
            LIMIT :limit";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

         // Adicionar campo de exibição do responsável similar a getAuditoriasParaRevisar
         foreach ($results as &$auditoria) {
             if (!empty($auditoria['nome_equipe'])) {
                  $auditoria['responsavel_display'] = 'Equipe: ' . htmlspecialchars($auditoria['nome_equipe']);
             } elseif (!empty($auditoria['nome_auditor'])) {
                  $auditoria['responsavel_display'] = 'Auditor: ' . htmlspecialchars($auditoria['nome_auditor']);
             } else {
                  $auditoria['responsavel_display'] = 'Não atribuído';
             }
             unset($auditoria['nome_auditor'], $auditoria['nome_equipe']); // Remover campos de JOIN
         }
        unset($auditoria);

        return $results;

    } catch (PDOException $e) {
        error_log("Erro getAuditoriasRecentesEmpresa (Empresa ID: $empresa_id): " . $e->getMessage());
        return [];
    }
}

/**
 * Busca os auditores ativos de uma empresa específica.
 * Usada para popular dropdown de Auditor Individual.
 */
function getAuditoresDaEmpresa(PDO $conexao, int $empresa_id, int $limit = 0): array {
    $sql = "SELECT id, nome, email, foto FROM usuarios
            WHERE empresa_id = :eid AND perfil = 'auditor' AND ativo = 1
            ORDER BY nome ASC";
    if ($limit > 0) { $sql .= " LIMIT :limit"; }
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        if ($limit > 0) { $stmt->bindParam(':limit', $limit, PDO::PARAM_INT); }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro getAuditoresDaEmpresa (Empresa ID: $empresa_id): " . $e->getMessage());
        return [];
    }
}

/**
 * Busca as equipes ativas de uma empresa específica.
 * Usada para popular o dropdown na criação de auditoria.
 */
function getEquipesDaEmpresa(PDO $conexao, int $empresa_id): array {
    $sql = "SELECT id, nome FROM equipes
            WHERE empresa_id = :eid AND ativo = 1
            ORDER BY nome ASC";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro getEquipesDaEmpresa (Empresa ID: $empresa_id): " . $e->getMessage());
        return [];
    }
}


/**
 * Busca dados para o gráfico de status das auditorias da empresa.
 */
function getAuditoriaStatusChartData(PDO $conexao, int $empresa_id): array {
    $chartData = ['labels' => [], 'data' => []];
    $sql = "SELECT status, COUNT(*) as total
            FROM auditorias
            WHERE empresa_id = :eid
            GROUP BY status
            ORDER BY FIELD(status, 'Planejada', 'Em Andamento', 'Pausada', 'Concluída (Auditor)', 'Em Revisão', 'Aprovada', 'Rejeitada', 'Cancelada')";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            $chartData['labels'][] = $row['status'];
            $chartData['data'][] = (int)$row['total'];
        }
    } catch (PDOException $e) {
        error_log("Erro getAuditoriaStatusChartData (Empresa ID: $empresa_id): " . $e->getMessage());
    }
    return $chartData;
}

/**
 * Busca dados para o gráfico de conformidade das auditorias APROVADAS da empresa.
 */
function getConformidadeChartData(PDO $conexao, int $empresa_id): array {
    $labels = [];
    $data = [];
    $conformidadeMap = ['Conforme' => 0, 'Não Conforme' => 0, 'Parcial' => 0, 'N/A' => 0];
    $sql = "SELECT ai.status_conformidade, COUNT(ai.id) as total
            FROM auditoria_itens ai
            JOIN auditorias a ON ai.auditoria_id = a.id
            WHERE a.empresa_id = :eid
              AND a.status = 'Aprovada'
              AND ai.status_conformidade IN ('Conforme', 'Não Conforme', 'Parcial', 'N/A', 'Parcialmente Conforme', 'Não Aplicável')
            GROUP BY ai.status_conformidade";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mapear resultados do DB para os labels do gráfico
        foreach ($results as $row) {
            $status = $row['status_conformidade'];
            if ($status === 'Parcialmente Conforme') $status = 'Parcial';
            if ($status === 'Não Aplicável') $status = 'N/A';
            if (array_key_exists($status, $conformidadeMap)) {
                $conformidadeMap[$status] = (int)$row['total'];
            }
        }
        $labels = array_keys($conformidadeMap);
        $data = array_values($conformidadeMap);

    } catch (PDOException $e) {
        error_log("Erro getConformidadeChartData (Empresa ID: $empresa_id): " . $e->getMessage());
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Busca auditorias pertencentes a uma empresa específica, com filtros e paginação.
 */
function getAuditoriasPorEmpresa(PDO $conexao, int $empresa_id, array $filtros = [], int $pagina = 1, int $porPagina = 15): array {
    $offset = max(0, ($pagina - 1) * $porPagina);
    $params = [':empresa_id' => $empresa_id];
    $whereClauses = ['a.empresa_id = :empresa_id'];

    if (!empty($filtros['status'])) {
        if ($filtros['status'] === 'ativas') {
            $whereClauses[] = "a.status IN ('Planejada', 'Em Andamento', 'Pausada', 'Em Revisão')";
        } elseif ($filtros['status'] === 'revisar') {
            $whereClauses[] = "a.status = 'Concluída (Auditor)'";
        } elseif (in_array($filtros['status'], ['Planejada','Em Andamento','Pausada','Concluída (Auditor)','Em Revisão','Aprovada','Rejeitada','Cancelada'])) {
            $whereClauses[] = 'a.status = :status';
            $params[':status'] = $filtros['status'];
        }
    }
    if (!empty($filtros['titulo'])) {
        $filtro_sanitizado = '%' . filter_var($filtros['titulo'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH) . '%';
        $whereClauses[] = 'a.titulo LIKE :titulo';
        $params[':titulo'] = $filtro_sanitizado;
    }
     if (!empty($filtros['auditor_id']) && filter_var($filtros['auditor_id'], FILTER_VALIDATE_INT) > 0) {
        $whereClauses[] = 'a.auditor_responsavel_id = :auditor_id';
        $params[':auditor_id'] = (int)$filtros['auditor_id'];
    }
     if (!empty($filtros['equipe_id']) && filter_var($filtros['equipe_id'], FILTER_VALIDATE_INT) > 0) {
        $whereClauses[] = 'a.equipe_id = :equipe_id';
        $params[':equipe_id'] = (int)$filtros['equipe_id'];
    }


    $sqlWhere = implode(' AND ', $whereClauses);
    if (!empty($sqlWhere)) $sqlWhere = "WHERE " . $sqlWhere; // Adiciona WHERE apenas se houver cláusulas

    $auditorias = [];

    // Inclui nome modelo, nome auditor e nome equipe nos resultados
    $sql = "SELECT SQL_CALC_FOUND_ROWS a.id, a.titulo, a.status, a.data_inicio_planejada, a.data_fim_planejada, a.data_modificacao,
            a.modelo_id, m.nome as nome_modelo,
            a.auditor_responsavel_id, u_auditor.nome as nome_auditor,
            a.equipe_id, e.nome as nome_equipe
            FROM auditorias a
            LEFT JOIN modelos_auditoria m ON a.modelo_id = m.id
            LEFT JOIN usuarios u_auditor ON a.auditor_responsavel_id = u_auditor.id
            LEFT JOIN equipes e ON a.equipe_id = e.id
            $sqlWhere
            ORDER BY a.data_modificacao DESC LIMIT :limit OFFSET :offset";
    try {
        $stmt = $conexao->prepare($sql);
        foreach ($params as $key => &$val) {
            $pdoType = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $val, $pdoType);
        }
        unset($val);
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtCount = $conexao->query("SELECT FOUND_ROWS()");
        $totalRegistros = (int) $stmtCount->fetchColumn();

         // Formatar o display do responsável na lista de resultados
        foreach ($auditorias as &$auditoria) {
             if (!empty($auditoria['equipe_id'])) { // Verifica o ID, não o nome do JOIN que pode vir null
                  $auditoria['responsavel_display'] = 'Equipe: ' . htmlspecialchars($auditoria['nome_equipe'] ?? 'N/D');
             } elseif (!empty($auditoria['auditor_responsavel_id'])) {
                  $auditoria['responsavel_display'] = 'Auditor: ' . htmlspecialchars($auditoria['nome_auditor'] ?? 'N/D');
             } else {
                  $auditoria['responsavel_display'] = 'Não atribuído';
             }
              // Remover colunas JOIN não mais necessárias para esta exibição
             unset($auditoria['auditor_responsavel_id'], $auditoria['equipe_id'], $auditoria['nome_auditor'], $auditoria['nome_equipe']);
         }
        unset($auditoria);

    } catch (PDOException $e) {
        error_log("Erro busca getAuditoriasPorEmpresa (Empresa ID: $empresa_id, SQL: $sql): " . $e->getMessage());
    }

    $totalPaginas = ($porPagina > 0 && $totalRegistros > 0) ? ceil($totalRegistros / $porPagina) : 0;
    $paginacao = [
        'pagina_atual' => $pagina,
        'total_paginas' => $totalPaginas,
        'total_itens' => $totalRegistros,
        'itens_por_pagina' => $porPagina
    ];
    return ['auditorias' => $auditorias, 'paginacao' => $paginacao];
}


/**
 * Busca todos os modelos de auditoria ATIVOS para seleção.
 */
function getModelosAtivos(PDO $conexao): array {
    try {
        $sql = "SELECT id, nome FROM modelos_auditoria WHERE ativo = 1 ORDER BY nome ASC";
        $stmt = $conexao->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro buscar modelos ativos: " . $e->getMessage());
        return [];
    }
}

/**
 * Busca todos os requisitos ATIVOS, agrupados por Categoria/Norma.
 */
function getRequisitosAtivosAgrupados(PDO $conexao): array { // Removidos limite/pag default
    $requisitos_por_grupo = [];
    try {
        $sql = "SELECT id, codigo, nome, categoria, norma_referencia, guia_evidencia, peso -- Adicionado mais campos
                FROM requisitos_auditoria
                WHERE ativo = 1
                ORDER BY norma_referencia ASC, categoria ASC, codigo ASC, nome ASC"; // Ordenação para agrupar e listar
        $stmt = $conexao->prepare($sql);
        $stmt->execute();
        $requisitos = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all first

        foreach ($requisitos as $req) {
            // Lógica de agrupamento: Prioriza Norma, depois Categoria, fallback para Geral/Sem Categoria
            $grupo = !empty($req['norma_referencia']) ? htmlspecialchars($req['norma_referencia']) : null;
             $subgrupo = !empty($req['categoria']) ? htmlspecialchars($req['categoria']) : null;

             if ($grupo !== null && $subgrupo !== null) { $grupo .= ' - ' . $subgrupo; }
             elseif ($grupo === null && $subgrupo !== null) { $grupo = $subgrupo; }
             elseif ($grupo === null && $subgrupo === null) { $grupo = 'Itens Gerais / Sem Categoria'; }

            $requisitos_por_grupo[$grupo][] = $req;
        }

        // Manter a ordem alfabética das chaves do array (grupos)
         ksort($requisitos_por_grupo);


    } catch (PDOException $e) {
        error_log("Erro buscar req agrupados: " . $e->getMessage());
    }
    return $requisitos_por_grupo;
}

/**
 */
/**
 * Cria uma nova auditoria, incluindo lógica para equipes e atribuição por seção.
 *
 * @param PDO $conexao
 * @param array $dadosAuditoria Array com todos os dados da auditoria.
 *        Esperado: titulo, empresa_id, gestor_id,
 *                  auditor_individual_id (opcional), equipe_id (opcional), modelo_id (opcional),
 *                  requisitos_selecionados (array, opcional), secao_responsaveis (array [secao=>auditor_id], opcional),
 *                  escopo, objetivo, instrucoes, data_inicio, data_fim.
 * @param array $arquivosUpload Array com metadados dos arquivos para upload.
 * @return int|null ID da auditoria criada ou null em caso de falha.
 */
function criarAuditoria(PDO $conexao, array $dadosAuditoria, array $arquivosUpload = []): ?int {
    // Validações essenciais
    if (empty($dadosAuditoria['titulo']) || empty($dadosAuditoria['empresa_id']) || empty($dadosAuditoria['gestor_id'])) {
        error_log("criarAuditoria ERRO: Dados essenciais (título, empresa ou gestor) faltando. Dados recebidos: " . json_encode(array_keys($dadosAuditoria)));
        return null;
    }

    // Extrair e validar IDs principais
    $auditor_individual_id = $dadosAuditoria['auditor_individual_id'] ?? null;
    $equipe_id = $dadosAuditoria['equipe_id'] ?? null;
    $modelo_id = $dadosAuditoria['modelo_id'] ?? null;

    if (($auditor_individual_id !== null && !filter_var($auditor_individual_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) ||
        ($equipe_id !== null && !filter_var($equipe_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) ||
        ($modelo_id !== null && !filter_var($modelo_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))
    ) {
        error_log("criarAuditoria ERRO: ID de auditor, equipe ou modelo inválido. Auditor: $auditor_individual_id, Equipe: $equipe_id, Modelo: $modelo_id");
        return null;
    }

    // Regras de negócio para atribuição e tipo de criação
    if ($auditor_individual_id && $equipe_id) {
        error_log("criarAuditoria AVISO: Auditor e Equipe definidos. Priorizando Equipe (Auditor Individual será ignorado).");
        $auditor_individual_id = null;
    }
    if (!$auditor_individual_id && !$equipe_id) {
        error_log("criarAuditoria ERRO: Nenhum responsável (auditor individual ou equipe) definido.");
        return null;
    }
    if ($equipe_id && !$modelo_id) {
        error_log("criarAuditoria ERRO: Auditoria de Equipe (ID: $equipe_id) requer um Modelo Base, mas modelo_id não foi fornecido.");
        return null;
    }
    $requisitos_selecionados = $dadosAuditoria['requisitos_selecionados'] ?? [];
    if (!$auditor_individual_id && !$equipe_id) { /* Já coberto acima */ }
    elseif ($auditor_individual_id && !$modelo_id && empty($requisitos_selecionados)) {
        error_log("criarAuditoria ERRO: Auditor individual (ID: $auditor_individual_id) precisa de um Modelo Base ou de Requisitos Manuais selecionados.");
        return null;
    }
    if($equipe_id && !empty($requisitos_selecionados)){
        error_log("criarAuditoria AVISO: Auditoria de equipe usa modelo. Lista de requisitos manuais será ignorada.");
        $requisitos_selecionados = []; // Garante que não tenta usar
    }

    $secao_responsaveis_mapa = $dadosAuditoria['secao_responsaveis'] ?? [];

    $conexao->beginTransaction();
    $auditoria_id = null;
    $temp_upload_dir_da_requisicao = null;
    $arquivos_fisicamente_movidos = [];

    try {
        // 1. Inserir na tabela `auditorias`
        $sqlAuditoria = "INSERT INTO auditorias (
            titulo, empresa_id, modelo_id, auditor_responsavel_id, equipe_id, gestor_responsavel_id,
            escopo, objetivo, instrucoes, data_inicio_planejada, data_fim_planejada,
            status, criado_por, data_criacao, modificado_por, data_modificacao
        ) VALUES (
            :titulo, :empresa_id, :modelo_id, :auditor_responsavel_id, :equipe_id, :gestor_responsavel_id,
            :escopo, :objetivo, :instrucoes, :data_inicio_planejada, :data_fim_planejada,
            'Planejada', :criado_por, NOW(), :modificado_por, NOW()
        )";
        $stmtAuditoria = $conexao->prepare($sqlAuditoria);
        $stmtAuditoria->execute([
            ':titulo' => $dadosAuditoria['titulo'],
            ':empresa_id' => $dadosAuditoria['empresa_id'],
            ':modelo_id' => $modelo_id,
            ':auditor_responsavel_id' => $auditor_individual_id, // Será NULL se equipe_id estiver preenchido
            ':equipe_id' => $equipe_id,
            ':gestor_responsavel_id' => $dadosAuditoria['gestor_id'],
            ':escopo' => empty($dadosAuditoria['escopo']) ? null : $dadosAuditoria['escopo'],
            ':objetivo' => empty($dadosAuditoria['objetivo']) ? null : $dadosAuditoria['objetivo'],
            ':instrucoes' => empty($dadosAuditoria['instrucoes']) ? null : $dadosAuditoria['instrucoes'],
            ':data_inicio_planejada' => empty($dadosAuditoria['data_inicio']) ? null : $dadosAuditoria['data_inicio'],
            ':data_fim_planejada' => empty($dadosAuditoria['data_fim']) ? null : $dadosAuditoria['data_fim'],
            ':criado_por' => $dadosAuditoria['gestor_id'],
            ':modificado_por' => $dadosAuditoria['gestor_id']
        ]);
        $auditoria_id = $conexao->lastInsertId();
        if (!$auditoria_id || $auditoria_id == 0) {
            throw new Exception("Falha ao gerar ID para a nova auditoria.");
        }
        error_log("criarAuditoria INFO: Auditoria principal ID $auditoria_id inserida.");

        // 2. Popular `auditoria_itens`
        $mapa_requisitos_com_meta = []; // [req_id => ['secao_modelo' => ..., 'ordem_modelo' => ...]]
        if ($modelo_id) { // Auditoria baseada em modelo (seja individual ou equipe)
            $stmtItensModelo = $conexao->prepare("SELECT requisito_id, secao, ordem_item FROM modelo_itens WHERE modelo_id = :mid ORDER BY ordem_secao ASC, ordem_item ASC, id ASC");
            $stmtItensModelo->execute([':mid' => $modelo_id]);
            $itens_do_modelo_db = $stmtItensModelo->fetchAll(PDO::FETCH_ASSOC);
            if(empty($itens_do_modelo_db)) error_log("criarAuditoria AVISO: Modelo ID $modelo_id não possui itens em modelo_itens.");

            foreach ($itens_do_modelo_db as $item_mod) {
                $mapa_requisitos_com_meta[$item_mod['requisito_id']] = [
                    'secao_do_modelo' => trim($item_mod['secao']) ?: null,
                    'ordem_no_modelo' => (int)$item_mod['ordem_item']
                ];
            }
        } elseif (!empty($requisitos_selecionados)) { // Auditoria manual (só para auditor individual)
            $ordem_manual = 0;
            foreach ($requisitos_selecionados as $req_id_manual) {
                if(filter_var($req_id_manual, FILTER_VALIDATE_INT) && $req_id_manual > 0) {
                    $mapa_requisitos_com_meta[(int)$req_id_manual] = [
                        'secao_do_modelo' => null, // Será pego da categoria/norma do requisito mestre
                        'ordem_no_modelo' => $ordem_manual++
                    ];
                }
            }
        }

        if (!empty($mapa_requisitos_com_meta)) {
            $ids_para_buscar_detalhes = array_keys($mapa_requisitos_com_meta);
            $placeholders_req_det = implode(',', array_fill(0, count($ids_para_buscar_detalhes), '?'));
            $sqlGetReqDetails = "SELECT id, codigo, nome, descricao, categoria, norma_referencia, guia_evidencia, peso
                                 FROM requisitos_auditoria WHERE id IN ($placeholders_req_det) AND ativo = 1";
            $stmtGetDetails = $conexao->prepare($sqlGetReqDetails);
            $stmtGetDetails->execute($ids_para_buscar_detalhes);
            $mapa_detalhes_requisitos = $stmtGetDetails->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC); // Indexado pelo ID do requisito

            if (count($ids_para_buscar_detalhes) !== count($mapa_detalhes_requisitos)) {
                $faltantes = array_diff($ids_para_buscar_detalhes, array_keys($mapa_detalhes_requisitos));
                error_log("criarAuditoria ERRO: Falha ao buscar detalhes ou requisitos inativos/inexistentes selecionados. Faltantes: " . implode(',', $faltantes));
                throw new Exception("Um ou mais requisitos selecionados não puderam ser carregados (podem estar inativos ou foram excluídos).");
            }

            $sqlInsertAudItem = "INSERT INTO auditoria_itens (
                                auditoria_id, requisito_id, codigo_item, nome_item, descricao_item,
                                categoria_item, norma_item, guia_evidencia_item, peso_item,
                                secao_item, ordem_item, status_conformidade
                              ) VALUES (
                                :auditoria_id, :requisito_id, :codigo_item, :nome_item, :descricao_item,
                                :categoria_item, :norma_item, :guia_evidencia_item, :peso_item,
                                :secao_item, :ordem_item, 'Pendente')";
            $stmtInsertAudItem = $conexao->prepare($sqlInsertAudItem);
            $ordem_final_item = 0;

            foreach ($mapa_requisitos_com_meta as $req_id_map => $meta_do_mapa) {
                if (!isset($mapa_detalhes_requisitos[$req_id_map])) {
                    error_log("criarAuditoria AVISO: Requisito ID $req_id_map não encontrado no mapa de detalhes (pulando).");
                    continue;
                }
                $detalhe_req_mestre = $mapa_detalhes_requisitos[$req_id_map];

                // Determinar a 'secao_item' para auditoria_itens
                $secao_item_final = null;
                if (!empty($meta_do_mapa['secao_do_modelo'])) { // Prioriza seção do modelo
                    $secao_item_final = $meta_do_mapa['secao_do_modelo'];
                } else { // Se manual ou modelo sem seção, usa categoria/norma do requisito
                    if (!empty($detalhe_req_mestre['categoria'])) {
                        $secao_item_final = trim($detalhe_req_mestre['categoria']);
                    } elseif (!empty($detalhe_req_mestre['norma_referencia'])) {
                        $secao_item_final = trim($detalhe_req_mestre['norma_referencia']);
                    } else {
                         $secao_item_final = 'Geral'; // Fallback se tudo for vazio
                    }
                }

                $stmtInsertAudItem->execute([
                    ':auditoria_id' => $auditoria_id, ':requisito_id' => $req_id_map,
                    ':codigo_item' => $detalhe_req_mestre['codigo'], ':nome_item' => $detalhe_req_mestre['nome'],
                    ':descricao_item' => $detalhe_req_mestre['descricao'],
                    ':categoria_item' => $detalhe_req_mestre['categoria'], // Copia categoria original
                    ':norma_item' => $detalhe_req_mestre['norma_referencia'], // Copia norma original
                    ':guia_evidencia_item' => $detalhe_req_mestre['guia_evidencia'],
                    ':peso_item' => (int)($detalhe_req_mestre['peso'] ?: 1),
                    ':secao_item' => $secao_item_final, // Seção determinada acima
                    ':ordem_item' => $ordem_final_item++ // Ordem sequencial na auditoria
                ]);
            }
            error_log("criarAuditoria INFO: ".count($mapa_requisitos_com_meta)." itens inseridos para Auditoria ID $auditoria_id.");
        } else {
             error_log("criarAuditoria AVISO: Nenhum item (modelo ou manual) foi selecionado para Auditoria ID $auditoria_id.");
        }

        // 3. Inserir Responsáveis por Seção (SE FOR EQUIPE, modelo e mapa de seções)
        if ($equipe_id && $modelo_id && !empty($secao_responsaveis_mapa)) {
            error_log("criarAuditoria INFO: Processando responsáveis por seção para Auditoria ID $auditoria_id. Mapa: " . json_encode($secao_responsaveis_mapa));
            $sql_insert_secao_resp = "INSERT INTO auditoria_secao_responsaveis
                                        (auditoria_id, secao_modelo_nome, auditor_designado_id)
                                      VALUES (:auditoria_id, :secao_nome, :auditor_designado_id)";
            $stmt_secao_resp = $conexao->prepare($sql_insert_secao_resp);

            foreach ($secao_responsaveis_mapa as $secao_nome_key => $auditor_designado_val) {
                $secao_nome_para_db = trim($secao_nome_key);
                $auditor_designado_id_para_db = filter_var($auditor_designado_val, FILTER_VALIDATE_INT);

                if (!empty($secao_nome_para_db) && $auditor_designado_id_para_db && $auditor_designado_id_para_db > 0) {
                    try {
                        $stmt_secao_resp->execute([
                            ':auditoria_id' => $auditoria_id,
                            ':secao_nome' => $secao_nome_para_db,
                            ':auditor_designado_id' => $auditor_designado_id_para_db
                        ]);
                        error_log("  - Sucesso: Seção '{$secao_nome_para_db}' -> Auditor ID {$auditor_designado_id_para_db} para Auditoria ID {$auditoria_id}");
                    } catch (PDOException $e) {
                        error_log("  - ERRO PDO ao inserir seção '{$secao_nome_para_db}' (Auditor ID {$auditor_designado_id_para_db}): " . $e->getMessage() . " (Code: {$e->getCode()})");
                        throw new Exception("Falha crítica ao atribuir seção '{$secao_nome_para_db}'. Verifique integridade dos dados (auditor pode não existir ou não pertencer à equipe/empresa). Erro: {$e->getCode()}");
                    }
                } else if (!empty($secao_nome_para_db)) {
                    error_log("  - Seção '{$secao_nome_para_db}' IGNORADA (Auditoria ID {$auditoria_id}): Auditor ID inválido ou não atribuído ('{$auditor_designado_val}').");
                }
            }
        } elseif ($equipe_id && $modelo_id) {
             error_log("criarAuditoria AVISO: Auditoria de equipe (ID $auditoria_id) usando Modelo ID $modelo_id, mas o mapa 'secao_responsaveis' estava vazio ou não foi fornecido.");
        }

        // 4. Processar Uploads de Documentos de Planejamento
        if (!empty($arquivosUpload)) {
            if (!defined('UPLOADS_BASE_PATH_ABSOLUTE')) { throw new Exception('Constante UPLOADS_BASE_PATH_ABSOLUTE não definida.'); }
            $diretorio_destino_fisico = rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/auditorias_planejamento/' . $auditoria_id . '/';
            $caminho_relativo_db_base = 'auditorias_planejamento/' . $auditoria_id . '/';

            if (!is_dir($diretorio_destino_fisico)) {
                if (!mkdir($diretorio_destino_fisico, 0755, true)) { throw new Exception("Falha ao criar diretório de upload: $diretorio_destino_fisico"); }
            }
            if (!is_writable($diretorio_destino_fisico)) { throw new Exception("Diretório de upload sem permissão de escrita: $diretorio_destino_fisico"); }

            $sqlInsertDocPlan = "INSERT INTO auditoria_documentos_planejamento (auditoria_id, nome_arquivo_original, nome_arquivo_armazenado, caminho_arquivo, tipo_mime, tamanho_bytes, usuario_upload_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmtDocPlan = $conexao->prepare($sqlInsertDocPlan);
            $temp_upload_dir_da_requisicao = (!empty($arquivosUpload) && isset($arquivosUpload[0]['caminho_temp'])) ? dirname($arquivosUpload[0]['caminho_temp']) : null;

            foreach ($arquivosUpload as $doc_info_temp) {
                $nome_final_doc = $doc_info_temp['nome_armazenado'];
                $path_origem_doc = $doc_info_temp['caminho_temp'];
                $path_destino_final_doc = $diretorio_destino_fisico . $nome_final_doc;
                $path_relativo_db_doc = $caminho_relativo_db_base . $nome_final_doc;

                if (!rename($path_origem_doc, $path_destino_final_doc)) {
                    if(file_exists($path_origem_doc)) @unlink($path_origem_doc);
                    throw new Exception("Falha ao mover arquivo temporário '{$doc_info_temp['nome_original']}' para o destino final.");
                }
                $arquivos_fisicamente_movidos[] = $path_destino_final_doc; // Adiciona à lista dos que foram movidos

                if (!$stmtDocPlan->execute([$auditoria_id, $doc_info_temp['nome_original'], $nome_final_doc, $path_relativo_db_doc, $doc_info_temp['tipo_mime'], $doc_info_temp['tamanho_bytes'], $dadosAuditoria['gestor_id']])) {
                    if(file_exists($path_destino_final_doc)) @unlink($path_destino_final_doc); // Tenta remover o arquivo já movido
                    throw new PDOException("Falha ao registrar documento '{$doc_info_temp['nome_original']}' no banco. Erro: " . implode(" - ", $stmtDocPlan->errorInfo()));
                }
            }
             error_log("criarAuditoria INFO: Documentos de planejamento processados para Auditoria ID $auditoria_id.");
        }

        // Commit final
        $conexao->commit();
        error_log("criarAuditoria INFO: Transação CONCLUÍDA com sucesso para Auditoria ID $auditoria_id.");

        // Limpar diretório temporário da requisição se ele existir e estiver VAZIO agora
        if ($temp_upload_dir_da_requisicao && is_dir($temp_upload_dir_da_requisicao)) {
            if(count(scandir($temp_upload_dir_da_requisicao)) <= 2){ // Verifica se está vazio (. e ..)
                 @rmdir($temp_upload_dir_da_requisicao);
            } else {
                 error_log("criarAuditoria AVISO: Diretório temporário de upload '$temp_upload_dir_da_requisicao' não estava vazio após commit e não foi removido.");
            }
        }
        return (int)$auditoria_id;

    } catch (Exception $e) { // Captura PDOException e Exception geral
        if ($conexao->inTransaction()) {
            $conexao->rollBack();
            error_log("criarAuditoria ERRO CRÍTICO (Rollback): " . $e->getMessage() . " | Auditoria ID (potencial): $auditoria_id");
        } else {
            error_log("criarAuditoria ERRO CRÍTICO (Sem transação ativa no catch): " . $e->getMessage());
        }

        // Limpeza de arquivos que já foram fisicamente movidos para o destino final antes do erro/rollback
        if (!empty($arquivos_fisicamente_movidos)) {
            error_log("criarAuditoria ROLLBACK: Tentando limpar arquivos já movidos para destino final...");
            foreach($arquivos_fisicamente_movidos as $path_a_remover) {
                if(file_exists($path_a_remover)) {
                    if(@unlink($path_a_remover)){
                        error_log("  - Limpo do destino: " . $path_a_remover);
                    } else {
                        error_log("  - FALHA ao limpar do destino: " . $path_a_remover);
                    }
                }
            }
        }

        // Limpeza de arquivos que ainda possam estar no diretório temporário da requisição
        if ($temp_upload_dir_da_requisicao && is_dir($temp_upload_dir_da_requisicao)) {
            error_log("criarAuditoria ROLLBACK: Tentando limpar diretório temporário da requisição: $temp_upload_dir_da_requisicao");
            // Uma maneira mais robusta de limpar é iterar sobre $arquivosUpload e deletar $doc['caminho_temp']
            // Mas se rename falhou, o arquivo ainda está no temp.
            $files_in_temp_dir = glob($temp_upload_dir_da_requisicao . '/*');
            if ($files_in_temp_dir) {
                 foreach($files_in_temp_dir as $file_in_temp) { if(is_file($file_in_temp)) @unlink($file_in_temp); }
            }
            @rmdir($temp_upload_dir_da_requisicao); // Tenta remover o próprio diretório temp da requisição
        }

        return null; // Indica falha
    }
}

function getSecoesDoModelo($conexao, $modelo_id) {
    $sql = "SELECT DISTINCT secao FROM modelo_itens 
            WHERE modelo_id = :modelo_id AND secao IS NOT NULL AND TRIM(secao) <> '' 
            ORDER BY ordem_secao, secao"; // Adicionado ordem_secao para consistência
    
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        error_log("Erro ao preparar getSecoesDoModelo: " . $conexao->errorInfo());
        return false;
    }

    // Correção aqui - bindValue para PDO
    $stmt->bindValue(':modelo_id', $modelo_id, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        error_log("Erro ao executar getSecoesDoModelo: " . $stmt->errorInfo());
        return false;
    }

    $secoes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $secoes[] = $row['secao'];
    }
    
    return $secoes;
}

function getMembrosDaEquipeComPerfilAuditor($conexao, $equipe_id, $empresa_id) {
    $sql = "SELECT u.id, u.nome, u.perfil FROM usuarios u 
            JOIN equipe_membros em ON em.usuario_id = u.id
            WHERE em.equipe_id = :equipe_id AND u.empresa_id = :empresa_id AND u.perfil = 'auditor'";
    
    $stmt = $conexao->prepare($sql);
    
    if (!$stmt) {
        error_log("Erro ao preparar getMembrosDaEquipeComPerfilAuditor: " . $conexao->errorInfo());
        return false;
    }

    // Correção: use bindValue() para PDO
    $stmt->bindValue(':equipe_id', $equipe_id, PDO::PARAM_INT);
    $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);

    if (!$stmt->execute()) {
        error_log("Erro ao executar getMembrosDaEquipeComPerfilAuditor: " . $stmt->errorInfo());
        return false;
    }

    $membros = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $membros[] = $row;
    }
    
    return $membros;
}
// --- Funções para Gerenciar Auditores e Equipes (serão usadas nas novas páginas) ---

/**
 * Busca todos os usuários com perfil 'auditor' da empresa do gestor.
 * Poderia ser uma versão mais completa de getAuditoresDaEmpresa para a página de gerenciamento.
 */
function getTodosAuditoresDaEmpresa(PDO $conexao, int $empresa_id, array $filtros = []): array {
    // Implementar paginação e filtros se necessário para a página de gerenciamento
    $sql = "SELECT u.id, u.nome, u.email, u.ativo, 
                   (SELECT GROUP_CONCAT(eq.nome SEPARATOR ', ') 
                    FROM equipes eq 
                    JOIN equipe_membros em ON eq.id = em.equipe_id 
                    WHERE em.usuario_id = u.id AND eq.empresa_id = u.empresa_id) as equipes_associadas
            FROM usuarios u
            WHERE u.empresa_id = :empresa_id AND u.perfil = 'auditor' ";
    
    // Adicionar filtros de busca por nome, status, etc.
    if (!empty($filtros['nome'])) {
        $sql .= " AND u.nome LIKE :nome ";
        $params[':nome'] = '%' . $filtros['nome'] . '%';
    }
    if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
         $sql .= " AND u.ativo = :ativo_status ";
         $params[':ativo_status'] = (int)$filtros['ativo'];
    }

    $sql .= " ORDER BY u.nome ASC";
    // Adicionar LIMIT e OFFSET se implementar paginação

    $stmt = $conexao->prepare($sql);
    $params[':empresa_id'] = $empresa_id;
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Retornar também dados de paginação se implementado
}

/**
 * Cria uma nova equipe para a empresa do gestor.
 */
function criarEquipe(PDO $conexao, string $nome, ?string $descricao, int $empresa_id, int $gestor_id): ?int {
    if (empty(trim($nome))) return null;
    try {
        $sql = "INSERT INTO equipes (nome, descricao, empresa_id, ativo, criado_por, data_criacao, modificado_por, data_modificacao)
                VALUES (:nome, :descricao, :empresa_id, 1, :criado_por, NOW(), :modificado_por, NOW())";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':nome' => trim($nome),
            ':descricao' => empty(trim($descricao)) ? null : trim($descricao),
            ':empresa_id' => $empresa_id,
            ':criado_por' => $gestor_id,
            ':modificado_por' => $gestor_id
        ]);
        return (int)$conexao->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') { // Erro de constraint (nome duplicado para a empresa?)
            // Adicionar unique constraint (empresa_id, nome) na tabela equipes se necessário.
            error_log("Erro ao criar equipe (provável duplicidade): " . $e->getMessage());
            return -1; // Código especial para duplicidade
        }
        error_log("Erro DB ao criar equipe: " . $e->getMessage());
        return null;
    }
}

/**
 * Busca todas as equipes da empresa do gestor com contagem de membros.
 */
function getTodasEquipesDaEmpresa(PDO $conexao, int $empresa_id, array $filtros = []): array {
    $sql = "SELECT e.id, e.nome, e.descricao, e.ativo,
                   (SELECT COUNT(*) FROM equipe_membros em WHERE em.equipe_id = e.id) as total_membros
            FROM equipes e
            WHERE e.empresa_id = :empresa_id ";

    $params = [':empresa_id' => $empresa_id];
    if (!empty($filtros['nome'])) {
        $sql .= " AND e.nome LIKE :nome ";
        $params[':nome'] = '%' . $filtros['nome'] . '%';
    }
    if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
         $sql .= " AND e.ativo = :ativo_status ";
         $params[':ativo_status'] = (int)$filtros['ativo'];
    }
    $sql .= " ORDER BY e.nome ASC";

    $stmt = $conexao->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Retornar também dados de paginação se implementado
}

/**
 * Busca os detalhes de uma equipe específica, incluindo seus membros.
 */
function getDetalhesEquipe(PDO $conexao, int $equipe_id, int $empresa_id): ?array {
    // Verifica se a equipe pertence à empresa
    $sqlEquipe = "SELECT id, nome, descricao, ativo FROM equipes WHERE id = :equipe_id AND empresa_id = :empresa_id";
    $stmtEquipe = $conexao->prepare($sqlEquipe);
    $stmtEquipe->execute([':equipe_id' => $equipe_id, ':empresa_id' => $empresa_id]);
    $equipe = $stmtEquipe->fetch(PDO::FETCH_ASSOC);

    if (!$equipe) return null;

    // Busca membros
    $sqlMembros = "SELECT u.id, u.nome, u.email
                   FROM usuarios u
                   JOIN equipe_membros em ON u.id = em.usuario_id
                   WHERE em.equipe_id = :equipe_id AND u.perfil = 'auditor' AND u.ativo = 1
                   ORDER BY u.nome ASC";
    $stmtMembros = $conexao->prepare($sqlMembros);
    $stmtMembros->execute([':equipe_id' => $equipe_id]);
    $equipe['membros'] = $stmtMembros->fetchAll(PDO::FETCH_ASSOC);

    return $equipe;
}

/**
 * Atualiza os dados de uma equipe (nome, descrição, status).
 */
function atualizarEquipe(PDO $conexao, int $equipe_id, string $nome, ?string $descricao, bool $ativo, int $empresa_id, int $gestor_id): bool {
    try {
        $sql = "UPDATE equipes 
                SET nome = :nome, descricao = :descricao, ativo = :ativo, modificado_por = :modificado_por, data_modificacao = NOW()
                WHERE id = :equipe_id AND empresa_id = :empresa_id";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':nome' => trim($nome),
            ':descricao' => empty(trim($descricao)) ? null : trim($descricao),
            ':ativo' => (int)$ativo,
            ':modificado_por' => $gestor_id,
            ':equipe_id' => $equipe_id,
            ':empresa_id' => $empresa_id
        ]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao atualizar equipe ID $equipe_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Adiciona um auditor a uma equipe.
 */
function adicionarMembroEquipe(PDO $conexao, int $equipe_id, int $auditor_id, int $empresa_id): bool {
    // Verificar se auditor e equipe pertencem à mesma empresa e se auditor é perfil 'auditor'
    $sqlCheck = "SELECT COUNT(u.id) 
    FROM usuarios u 
    JOIN equipes eq ON u.empresa_id = eq.empresa_id
    WHERE u.id = :auditor_id 
      AND u.perfil = 'auditor' 
      AND u.empresa_id = :empresa_id1
      AND eq.id = :equipe_id 
      AND eq.empresa_id = :empresa_id2";
    $stmtCheck = $conexao->prepare($sqlCheck);
    $stmtCheck->execute([
        ':auditor_id' => $auditor_id,
        ':empresa_id1' => $empresa_id,
        ':empresa_id2' => $empresa_id,
        ':equipe_id' => $equipe_id
    ]);
    if ($stmtCheck->fetchColumn() == 0) {
        error_log("Tentativa de adicionar auditor ID $auditor_id à equipe ID $equipe_id falhou na validação de empresa/perfil.");
        return false;
    }

    try {
        $sql = "INSERT INTO equipe_membros (equipe_id, usuario_id) VALUES (:equipe_id, :usuario_id)";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([':equipe_id' => $equipe_id, ':usuario_id' => $auditor_id]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') { // Já é membro
            return true; // Considera sucesso se já existe
        }
        error_log("Erro ao adicionar membro ID $auditor_id à equipe ID $equipe_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove um auditor de uma equipe.
 */
function removerMembroEquipe(PDO $conexao, int $equipe_id, int $auditor_id): bool {
    try {
        $sql = "DELETE FROM equipe_membros WHERE equipe_id = :equipe_id AND usuario_id = :usuario_id";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([':equipe_id' => $equipe_id, ':usuario_id' => $auditor_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao remover membro ID $auditor_id da equipe ID $equipe_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Exclui uma equipe, apenas se não estiver vinculada a auditorias.
 */
function excluirEquipe(PDO $conexao, int $equipe_id, int $empresa_id): string {
    // Verificar se a equipe está em uso
    $sqlCheckUso = "SELECT COUNT(*) FROM auditorias WHERE equipe_id = :equipe_id";
    $stmtCheckUso = $conexao->prepare($sqlCheckUso);
    $stmtCheckUso->execute([':equipe_id' => $equipe_id]);
    if ($stmtCheckUso->fetchColumn() > 0) {
        return "em_uso"; // Não pode excluir
    }

    try {
        // Excluir membros primeiro (ou ON DELETE CASCADE na FK da tabela equipe_membros para equipes)
        $sqlDelMembros = "DELETE FROM equipe_membros WHERE equipe_id = :equipe_id";
        $stmtDelMembros = $conexao->prepare($sqlDelMembros);
        $stmtDelMembros->execute([':equipe_id' => $equipe_id]);
        
        // Excluir equipe
        $sql = "DELETE FROM equipes WHERE id = :equipe_id AND empresa_id = :empresa_id";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([':equipe_id' => $equipe_id, ':empresa_id' => $empresa_id]);
        
        if ($stmt->rowCount() > 0) {
            return "sucesso";
        } else {
            return "nao_encontrada"; // Não encontrou equipe para excluir (talvez já excluída ou pertence a outra empresa)
        }
    } catch (PDOException $e) {
        error_log("Erro ao excluir equipe ID $equipe_id: " . $e->getMessage());
        return "erro_db";
    }
}

/**
 * Busca todos os usuários com perfil 'auditor' da empresa do gestor, COM PAGINAÇÃO e filtros.
 * Retorna um array associativo com 'auditores' e 'paginacao'.
 */
function getTodosAuditoresDaEmpresaPag(PDO $conexao, int $empresa_id, array $filtros = [], int $pagina_atual = 1, int $itens_por_pagina = 15): array {
    $offset = ($pagina_atual - 1) * $itens_por_pagina;
    $resultado = ['auditores' => [], 'paginacao' => []]; // Estrutura de retorno padrão

    $params = [':empresa_id' => $empresa_id];
    $sql_where_clauses = ["u.empresa_id = :empresa_id", "u.perfil = 'auditor'"]; // Filtro base

    // Adicionar filtros de busca
    if (!empty($filtros['nome'])) {
        $sql_where_clauses[] = "u.nome LIKE :nome";
        $params[':nome'] = '%' . $filtros['nome'] . '%';
    }
    if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
        $sql_where_clauses[] = "u.ativo = :ativo_status";
        $params[':ativo_status'] = (int)$filtros['ativo'];
    }
    $sql_where = implode(" AND ", $sql_where_clauses);

    try {
        // 1. Contar total de registros com os filtros aplicados
        $sql_count = "SELECT COUNT(u.id) FROM usuarios u WHERE $sql_where";
        $stmt_count = $conexao->prepare($sql_count);
        $stmt_count->execute($params);
        $total_itens = (int)$stmt_count->fetchColumn();

        // 2. Buscar os registros da página atual com os filtros e JOIN para equipes
        $sql_select = "SELECT u.id, u.nome, u.email, u.ativo, u.foto,
                       (SELECT GROUP_CONCAT(eq.nome SEPARATOR ', ')
                        FROM equipes eq
                        JOIN equipe_membros em ON eq.id = em.equipe_id
                        WHERE em.usuario_id = u.id AND eq.empresa_id = u.empresa_id AND eq.ativo = 1) as equipes_associadas_ativas
                FROM usuarios u
                WHERE $sql_where
                ORDER BY u.nome ASC
                LIMIT :limit OFFSET :offset";

        $stmt_select = $conexao->prepare($sql_select);

        // Adicionar parâmetros de paginação aos parâmetros da query principal
        $params_com_pag = $params; // Copia params do WHERE
        $params_com_pag[':limit'] = $itens_por_pagina;
        $params_com_pag[':offset'] = $offset;

        // Bind de todos os parâmetros
        foreach ($params_com_pag as $key => &$val) { // Usar referência para bindValue decidir tipo corretamente
            if ($key === ':limit' || $key === ':offset' || $key === ':empresa_id' || $key === ':ativo_status') { // IDs e status são inteiros
                $stmt_select->bindValue($key, $val, PDO::PARAM_INT);
            } else {
                $stmt_select->bindValue($key, $val, PDO::PARAM_STR); // Default para strings (nome, etc.)
            }
        }
        unset($val); // Desfazer referência

        $stmt_select->execute();
        $resultado['auditores'] = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

        // 3. Calcular informações de paginação
        $total_paginas = ($total_itens > 0 && $itens_por_pagina > 0) ? ceil($total_itens / $itens_por_pagina) : 0;
        $resultado['paginacao'] = [
            'pagina_atual' => $pagina_atual,
            'total_paginas' => (int)$total_paginas,
            'total_itens' => (int)$total_itens,
            'itens_por_pagina' => $itens_por_pagina
        ];

    } catch (PDOException $e) {
        error_log("Erro getTodosAuditoresDaEmpresa (Empresa $empresa_id): " . $e->getMessage());
        // Retornar estrutura vazia em caso de erro
        $resultado['paginacao'] = ['pagina_atual' => 1, 'total_paginas' => 0, 'total_itens' => 0, 'itens_por_pagina' => $itens_por_pagina];
    }
    return $resultado;
}

/**
 * Busca auditorias do gestor responsável, com filtros avançados e paginação.
 * Inclui nome do modelo, auditor/equipe e contagens para indicadores.
 */
function getMinhasAuditorias(PDO $conexao, int $gestor_id, int $empresa_id, int $pagina = 1, int $por_pagina = 10, array $filtros = []): array {
    $offset = max(0, ($pagina - 1) * $por_pagina);
    $result = ['auditorias' => [], 'total' => 0, 'paginacao' => []];

    $params = [':gestor_id' => $gestor_id, ':empresa_id' => $empresa_id];
    $where_clauses = ["a.gestor_responsavel_id = :gestor_id", "a.empresa_id = :empresa_id"];

    // Aplicar filtros
    if (!empty($filtros['titulo_busca'])) {
        $where_clauses[] = "a.titulo LIKE :titulo_busca";
        $params[':titulo_busca'] = '%' . $filtros['titulo_busca'] . '%';
    }
    if (!empty($filtros['status_busca']) && $filtros['status_busca'] !== 'todos') {
        $where_clauses[] = "a.status = :status_busca";
        $params[':status_busca'] = $filtros['status_busca'];
    }
    if (!empty($filtros['auditor_busca']) && filter_var($filtros['auditor_busca'], FILTER_VALIDATE_INT)) {
        // Este filtro precisa considerar tanto auditor individual quanto auditores em seções de equipe
        // Para simplificar, vamos filtrar por auditor_responsavel_id ou se ele está em alguma seção_responsavel
        // (Subquery ou JOIN mais complexo seria necessário para pegar todas as auditorias onde ele participa em equipe)
        // POR HORA, VAMOS MANTER SIMPLES (filtra por auditor_responsavel_id) e podemos refinar.
        // OU filtrar por EQUIPE se um ID de equipe for passado
        if(isset($filtros['tipo_responsavel_busca']) && $filtros['tipo_responsavel_busca'] === 'equipe'){
            $where_clauses[] = "a.equipe_id = :responsavel_id_busca";
            $params[':responsavel_id_busca'] = (int)$filtros['auditor_busca']; // ID da equipe aqui
        } else {
            $where_clauses[] = "a.auditor_responsavel_id = :responsavel_id_busca";
            $params[':responsavel_id_busca'] = (int)$filtros['auditor_busca']; // ID do auditor aqui
        }
    }
    if (!empty($filtros['data_inicio_de'])) {
        $where_clauses[] = "a.data_inicio_planejada >= :data_inicio_de";
        $params[':data_inicio_de'] = $filtros['data_inicio_de'];
    }
    if (!empty($filtros['data_inicio_ate'])) {
        $where_clauses[] = "a.data_inicio_planejada <= :data_inicio_ate";
        $params[':data_inicio_ate'] = $filtros['data_inicio_ate'];
    }


    $sql_where = implode(' AND ', $where_clauses);

    try {
        // Contar total
        $sql_count = "SELECT COUNT(a.id) FROM auditorias a WHERE $sql_where";
        $stmt_count = $conexao->prepare($sql_count);
        $stmt_count->execute($params);
        $total_registros = (int)$stmt_count->fetchColumn();
        $result['total'] = $total_registros;

        // Buscar auditorias com JOINS e SUBQUERIES para contagens
        $sql_select = "SELECT
                           a.id, a.titulo, a.status, a.data_inicio_planejada, a.data_fim_planejada,
                           a.data_conclusao_auditor, a.data_aprovacao_rejeicao_gestor,
                           m.nome as nome_modelo,
                           u_auditor.nome as nome_auditor_individual, -- Auditor individual
                           eq.nome as nome_equipe, -- Nome da equipe
                           (SELECT COUNT(*) FROM auditoria_itens ai WHERE ai.auditoria_id = a.id) as total_itens,
                           (SELECT COUNT(*) FROM auditoria_itens ai WHERE ai.auditoria_id = a.id AND ai.status_conformidade != 'Pendente') as itens_respondidos,
                           (SELECT COUNT(*) FROM auditoria_itens ai WHERE ai.auditoria_id = a.id AND ai.status_conformidade IN ('Não Conforme', 'Parcial')) as itens_nao_conformes
                       FROM auditorias a
                       LEFT JOIN modelos_auditoria m ON a.modelo_id = m.id
                       LEFT JOIN usuarios u_auditor ON a.auditor_responsavel_id = u_auditor.id
                       LEFT JOIN equipes eq ON a.equipe_id = eq.id
                       WHERE $sql_where
                       ORDER BY a.data_criacao DESC
                       LIMIT :limit OFFSET :offset";

        $stmt = $conexao->prepare($sql_select);
        
        // Bind dos parâmetros do WHERE (já em $params)
        foreach ($params as $key => &$val) { $stmt->bindValue($key, $val); } unset($val);
        // Bind dos parâmetros de LIMIT e OFFSET (adicionar aos $params se não estiverem lá, ou bind separado)
        $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Adicionar lógica para display do responsável (auditor individual ou equipe)
        foreach ($auditorias as &$aud) {
            if (!empty($aud['nome_equipe'])) {
                $aud['responsavel_display'] = 'Equipe: ' . htmlspecialchars($aud['nome_equipe']);
            } elseif (!empty($aud['nome_auditor_individual'])) {
                $aud['responsavel_display'] = htmlspecialchars($aud['nome_auditor_individual']);
            } else {
                $aud['responsavel_display'] = '<em class="text-black-50">Não atribuído</em>';
            }
        }
        unset($aud);

        $result['auditorias'] = $auditorias;
        $totalPaginasCalculado = ($total_registros > 0 && $por_pagina > 0) ? ceil($total_registros / $por_pagina) : 1;
        $result['paginacao'] = [
            'pagina_atual' => $pagina,
            'total_paginas' => (int)$totalPaginasCalculado,
            'total_itens' => (int)$total_registros,
            'itens_por_pagina' => $por_pagina
        ];

    } catch (PDOException $e) {
        error_log("Erro getMinhasAuditorias (Gestor $gestor_id, Empresa $empresa_id, Filtros: " . json_encode($filtros) . "): " . $e->getMessage());
        // Retorna estrutura vazia em caso de erro para não quebrar a página
        $result['paginacao'] = ['pagina_atual' => $pagina, 'total_paginas' => 1, 'total_itens' => 0, 'itens_por_pagina' => $por_pagina];
    }
    return $result;
}

/**
 * Salva as decisões do gestor sobre os itens de uma auditoria e o status geral da auditoria.
 *
 * @param PDO $conexao
 * @param int $auditoria_id
 * @param int $gestor_id_revisor
 * @param array $decisoes_itens Array associativo [item_id => ['status_revisao_gestor' => 'Revisado'/'Ação Solicitada', 'observacoes_gestor' => 'texto', 'status_conformidade_gestor' => 'Conforme'/'Não Conforme'/...]]
 * @param string $acao_final 'salvar_parcial', 'aprovar', 'rejeitar', 'solicitar_correcao'
 * @param string|null $observacoes_gerais_auditoria
 * @param string|null $resultado_geral_auditoria (se aprovada: 'Conforme', 'Não Conforme', etc.)
 * @return bool True se sucesso, False se falha.
 */
function salvarRevisaoGestor(PDO $conexao, int $auditoria_id, int $gestor_id_revisor, array $decisoes_itens, string $acao_final, ?string $observacoes_gerais_auditoria = null, ?string $resultado_geral_auditoria = null): bool {

    // Verificar se a auditoria está em um status que permite revisão pelo gestor
    $stmt_check_status = $conexao->prepare("SELECT status FROM auditorias WHERE id = :auditoria_id AND gestor_responsavel_id = :gestor_id");
    $stmt_check_status->execute([':auditoria_id' => $auditoria_id, ':gestor_id' => $gestor_id_revisor]);
    $status_atual_auditoria = $stmt_check_status->fetchColumn();

    if (!$status_atual_auditoria || !in_array($status_atual_auditoria, ['Concluída (Auditor)', 'Em Revisão', 'Rejeitada'])) {
        error_log("salvarRevisaoGestor: Tentativa de revisar auditoria ID $auditoria_id com status inválido '$status_atual_auditoria' pelo gestor $gestor_id_revisor.");
        return false; // Não pode revisar este status
    }

    $conexao->beginTransaction();
    try {
        // 1. Atualizar cada item da auditoria com a decisão do gestor
        $sql_update_item = "UPDATE auditoria_itens SET
                                status_revisao_gestor = :status_revisao_gestor,
                                observacoes_gestor = :observacoes_gestor,
                                -- Opcional: Permitir ao gestor sobrescrever o status_conformidade
                                -- status_conformidade = :status_conformidade_gestor,
                                data_revisao_gestor = NOW(),
                                revisado_por_gestor_id = :gestor_id_revisor
                            WHERE id = :item_id AND auditoria_id = :auditoria_id";
        $stmt_update_item = $conexao->prepare($sql_update_item);

        foreach ($decisoes_itens as $item_id => $decisao) {
            $item_id_int = (int)$item_id;
            if ($item_id_int <= 0) continue; // Ignorar item_id inválido

            $status_rev_gestor = $decisao['status_revisao_gestor'] ?? 'Revisado'; // Default
            $obs_gestor = empty(trim($decisao['observacoes_gestor'])) ? null : trim($decisao['observacoes_gestor']);
            // $status_conf_gestor = $decisao['status_conformidade_gestor'] ?? null; // Se for permitir sobrescrever

            $stmt_update_item->execute([
                ':status_revisao_gestor' => $status_rev_gestor,
                ':observacoes_gestor' => $obs_gestor,
                // ':status_conformidade_gestor' => $status_conf_gestor, // Descomentar se necessário
                ':gestor_id_revisor' => $gestor_id_revisor,
                ':item_id' => $item_id_int,
                ':auditoria_id' => $auditoria_id
            ]);
        }

        // 2. Atualizar o status geral da auditoria
        $novo_status_auditoria = $status_atual_auditoria; // Por padrão, mantém o status se for salvar parcial
        $data_aprov_rej_gestor = null;
        $obs_gerais_db = $observacoes_gerais_auditoria; // Observações gerais do gestor
        $res_geral_db = null;

        switch ($acao_final) {
            case 'aprovar':
                $novo_status_auditoria = 'Aprovada';
                $data_aprov_rej_gestor = date('Y-m-d H:i:s');
                $res_geral_db = $resultado_geral_auditoria; // 'Conforme', 'Não Conforme', etc.
                break;
            case 'rejeitar':
                $novo_status_auditoria = 'Rejeitada';
                $data_aprov_rej_gestor = date('Y-m-d H:i:s');
                if (empty(trim($obs_gerais_db))) { // Justificativa é obrigatória para rejeitar
                    throw new Exception("A justificativa é obrigatória para rejeitar a auditoria.");
                }
                break;
            case 'solicitar_correcao':
                // Definir um novo status se precisar que o auditor veja que precisa corrigir
                // Por exemplo, 'Aguardando Correção Auditor' ou voltar para 'Em Andamento' com uma flag
                $novo_status_auditoria = 'Em Andamento'; // Ou um status específico
                 // Poderia limpar data_conclusao_auditor para indicar que voltou
                 // $sql_update_auditoria .= ", data_conclusao_auditor = NULL";
                if (empty(trim($obs_gerais_db))) { // Observações são importantes aqui
                    throw new Exception("Observações são necessárias ao solicitar correções.");
                }
                break;
            case 'salvar_parcial':
                $novo_status_auditoria = 'Em Revisão'; // Garante que fica neste status
                break;
            default:
                throw new Exception("Ação final inválida para a revisão.");
        }

        $sql_update_auditoria = "UPDATE auditorias SET
                                    status = :novo_status,
                                    data_aprovacao_rejeicao_gestor = :data_aprov_rej_gestor,
                                    observacoes_gerais_gestor = :obs_gerais_db,
                                    resultado_geral = :res_geral_db,
                                    modificado_por = :gestor_id_revisor,
                                    data_modificacao = NOW()
                                WHERE id = :auditoria_id";
        $stmt_update_auditoria = $conexao->prepare($sql_update_auditoria);
        $stmt_update_auditoria->execute([
            ':novo_status' => $novo_status_auditoria,
            ':data_aprov_rej_gestor' => $data_aprov_rej_gestor,
            ':obs_gerais_db' => empty(trim($obs_gerais_db)) ? null : trim($obs_gerais_db),
            ':res_geral_db' => $res_geral_db,
            ':gestor_id_revisor' => $gestor_id_revisor,
            ':auditoria_id' => $auditoria_id
        ]);

        $conexao->commit();
        // Log de sucesso da revisão
        if(function_exists('dbRegistrarLogAcesso')) {dbRegistrarLogAcesso($gestor_id_revisor, $_SERVER['REMOTE_ADDR'], 'revisao_auditoria_salva', 1, "Auditoria ID: $auditoria_id, Ação Final: $acao_final", $conexao);}
        return true;

    } catch (Exception $e) {
        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }
        error_log("Erro ao salvar revisão da auditoria ID $auditoria_id: " . $e->getMessage());
         if(function_exists('dbRegistrarLogAcesso')) {dbRegistrarLogAcesso($gestor_id_revisor, $_SERVER['REMOTE_ADDR'], 'revisao_auditoria_falha', 0, "Auditoria ID: $auditoria_id, Erro: ".$e->getMessage(), $conexao);}
        return false;
    }
}

/**
 * Atualiza uma auditoria existente que está no status 'Planejada'.
 *
 * @param PDO $conexao
 * @param int $auditoria_id_edit
 * @param array $dadosUpdate Contém todos os campos do formulário, similar a criarAuditoria.
 * @param int $gestor_id_atualizando
 * @param array $arquivosUploadNovos (se permitir mudar/adicionar documentos)
 * @param array $documentos_a_remover_ids (IDs de auditoria_documentos_planejamento a serem removidos)
 * @return bool True se sucesso, False ou string de erro.
 */
function atualizarAuditoriaPlanejada(PDO $conexao, int $auditoria_id_edit, array $dadosUpdate, int $gestor_id_atualizando, array $arquivosUploadNovos = [], array $documentos_a_remover_ids = []): bool|string {
    // Verificar se a auditoria existe e pode ser editada
    $stmt_check = $conexao->prepare("SELECT status, empresa_id FROM auditorias WHERE id = :id");
    $stmt_check->execute([':id' => $auditoria_id_edit]);
    $auditoria_db = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$auditoria_db) return "Auditoria não encontrada.";
    if ($auditoria_db['status'] !== 'Planejada') return "Apenas auditorias no status 'Planejada' podem ser editadas.";
    // Adicionar verificação se a auditoria pertence à empresa do gestor logado, se necessário:
    // if ($auditoria_db['empresa_id'] !== $_SESSION['usuario_empresa_id']) return "Acesso negado a esta auditoria.";


    $conexao->beginTransaction();
    try {
        // 1. Atualizar dados principais da tabela 'auditorias'
        $sql_update_aud = "UPDATE auditorias SET
                            titulo = :titulo,
                            escopo = :escopo,
                            objetivo = :objetivo,
                            instrucoes = :instrucoes,
                            data_inicio_planejada = :data_inicio,
                            data_fim_planejada = :data_fim,
                            auditor_responsavel_id = :auditor_individual_id,
                            equipe_id = :equipe_id,
                            modelo_id = :modelo_id,
                            modificado_por = :mod_por,
                            data_modificacao = NOW()
                           WHERE id = :auditoria_id";
        $stmt_update_aud = $conexao->prepare($sql_update_aud);
        $stmt_update_aud->execute([
            ':titulo' => $dadosUpdate['titulo'],
            ':escopo' => $dadosUpdate['escopo'],
            ':objetivo' => $dadosUpdate['objetivo'],
            ':instrucoes' => $dadosUpdate['instrucoes'],
            ':data_inicio' => $dadosUpdate['data_inicio'],
            ':data_fim' => $dadosUpdate['data_fim'],
            ':auditor_individual_id' => $dadosUpdate['auditor_individual_id'], // Pode ser null
            ':equipe_id' => $dadosUpdate['equipe_id'], // Pode ser null
            ':modelo_id' => $dadosUpdate['modelo_id'], // Pode ser null
            ':mod_por' => $gestor_id_atualizando,
            ':auditoria_id' => $auditoria_id_edit
        ]);

        // 2. Gerenciar itens da auditoria (esta é a parte mais complexa)
        // Se o modo de criação ou o modelo/requisitos mudaram, precisamos:
        //    a. Remover todos os itens antigos da auditoria.
        //    b. Adicionar os novos itens (baseado em modelo ou seleção manual).
        // Se o modo de criação é o mesmo e o modelo/requisitos são os mesmos,
        // mas o responsável (auditor/equipe) mudou, talvez não precise mexer nos itens.
        // Para simplificar, vamos assumir que se modelo_id ou requisitos_selecionados mudam,
        // nós REFAZEMOS todos os auditoria_itens.

        // Remover itens antigos
        $stmt_del_itens = $conexao->prepare("DELETE FROM auditoria_itens WHERE auditoria_id = :auditoria_id");
        $stmt_del_itens->execute([':auditoria_id' => $auditoria_id_edit]);

        // Adicionar novos itens (lógica similar a criarAuditoria)
        $requisitos_ids_origem = [];
        if ($dadosUpdate['modelo_id']) {
            $stmtReqs = $conexao->prepare("SELECT requisito_id, secao, ordem_item FROM modelo_itens WHERE modelo_id = :modelo_id ORDER BY ordem_secao ASC, ordem_item ASC, id ASC");
            $stmtReqs->execute([':modelo_id' => $dadosUpdate['modelo_id']]);
            $itens_modelo_atuais = $stmtReqs->fetchAll(PDO::FETCH_ASSOC);
            foreach ($itens_modelo_atuais as $im) {
                $requisitos_ids_origem[$im['requisito_id']] = ['secao' => $im['secao'], 'ordem_item' => $im['ordem_item']];
            }
        } elseif (!empty($dadosUpdate['requisitos_selecionados'])) {
            $idx = 0;
            foreach($dadosUpdate['requisitos_selecionados'] as $req_id_manual) {
                 $requisitos_ids_origem[$req_id_manual] = ['secao' => null, 'ordem_item' => $idx++];
            }
        }

        if (!empty($requisitos_ids_origem)) {
            $ids_unicos_req = array_keys($requisitos_ids_origem);
            $placeholders_req = implode(',', array_fill(0, count($ids_unicos_req), '?'));
            $stmtGetDetails = $conexao->prepare("SELECT id, codigo, nome, descricao, categoria, norma_referencia, guia_evidencia, peso FROM requisitos_auditoria WHERE id IN ($placeholders_req) AND ativo = 1");
            $stmtGetDetails->execute($ids_unicos_req);
            $reqDetailsMap = $stmtGetDetails->fetchAll(PDO::FETCH_KEY_PAIR); // id => row

            $sqlItemInsert = "INSERT INTO auditoria_itens (auditoria_id, requisito_id, codigo_item, nome_item, descricao_item, categoria_item, norma_item, guia_evidencia_item, peso_item, secao_item, ordem_item, status_conformidade) VALUES (:aud_id, :req_id, :cod, :nome, :desc, :cat, :norma, :guia, :peso, :sec, :ord, 'Pendente')";
            $stmtItemInsert = $conexao->prepare($sqlItemInsert);

            foreach ($requisitos_ids_origem as $req_id_key => $meta_item) {
                if (!isset($reqDetailsMap[$req_id_key])) continue; // Requisito não ativo ou não encontrado
                $detalheReq = $reqDetailsMap[$req_id_key];
                $stmtItemInsert->execute([
                    ':aud_id' => $auditoria_id_edit, ':req_id' => $detalheReq['id'], ':cod' => $detalheReq['codigo'], ':nome' => $detalheReq['nome'],
                    ':desc' => $detalheReq['descricao'], ':cat' => $detalheReq['categoria'], ':norma' => $detalheReq['norma_referencia'],
                    ':guia' => $detalheReq['guia_evidencia'], ':peso' => (int)($detalheReq['peso'] ?: 1),
                    ':sec' => $meta_item['secao'], ':ord' => $meta_item['ordem_item']
                ]);
            }
        }

        // 3. Gerenciar responsáveis por seção (se for equipe)
        // Remover atribuições antigas de seção
        $stmt_del_secao_resp = $conexao->prepare("DELETE FROM auditoria_secao_responsaveis WHERE auditoria_id = :auditoria_id");
        $stmt_del_secao_resp->execute([':auditoria_id' => $auditoria_id_edit]);

        // Adicionar novas atribuições de seção (se equipe e modelo e secao_responsaveis preenchido)
        if ($dadosUpdate['equipe_id'] && $dadosUpdate['modelo_id'] && !empty($dadosUpdate['secao_responsaveis'])) {
            $sql_secao_resp_insert = "INSERT INTO auditoria_secao_responsaveis (auditoria_id, secao_modelo_nome, auditor_designado_id) VALUES (:aud_id, :sec_nome, :aud_des_id)";
            $stmt_secao_resp = $conexao->prepare($sql_secao_resp_insert);
            foreach ($dadosUpdate['secao_responsaveis'] as $secao_nome => $aud_des_id) {
                if (!empty(trim($secao_nome)) && !empty($aud_des_id) && filter_var($aud_des_id, FILTER_VALIDATE_INT)) {
                    $stmt_secao_resp->execute([
                        ':aud_id' => $auditoria_id_edit,
                        ':sec_nome' => trim($secao_nome),
                        ':aud_des_id' => (int)$aud_des_id
                    ]);
                }
            }
        }

        // 4. Gerenciar Documentos de Planejamento
        // Remover documentos marcados para exclusão
        if (!empty($documentos_a_remover_ids)) {
            // Primeiro, buscar os caminhos dos arquivos para deletar do disco
            $placeholders_docs = implode(',', array_fill(0, count($documentos_a_remover_ids), '?'));
            $stmt_get_docs = $conexao->prepare("SELECT caminho_arquivo FROM auditoria_documentos_planejamento WHERE id IN ($placeholders_docs) AND auditoria_id = ?");
            $params_get_docs = $documentos_a_remover_ids;
            $params_get_docs[] = $auditoria_id_edit;
            $stmt_get_docs->execute($params_get_docs);
            $docs_para_deletar_disco = $stmt_get_docs->fetchAll(PDO::FETCH_COLUMN);

            foreach($docs_para_deletar_disco as $caminho_rel_doc_del) {
                $caminho_fisico_doc_del = rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/' . ltrim($caminho_rel_doc_del, '/');
                if (file_exists($caminho_fisico_doc_del)) { @unlink($caminho_fisico_doc_del); }
            }
            // Deletar do banco
            $stmt_del_docs = $conexao->prepare("DELETE FROM auditoria_documentos_planejamento WHERE id IN ($placeholders_docs) AND auditoria_id = ?");
            $stmt_del_docs->execute($params_get_docs);
        }

        // Adicionar novos documentos (lógica similar a criarAuditoria)
        if (!empty($arquivosUploadNovos)) {
             $diretorio_auditoria_final_fisico = rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/auditorias_planejamento/' . $auditoria_id_edit . '/';
             $caminho_relativo_base_db = 'auditorias_planejamento/' . $auditoria_id_edit . '/';
             if (!is_dir($diretorio_auditoria_final_fisico)) { if (!mkdir($diretorio_auditoria_final_fisico, 0755, true)) { throw new Exception("Falha ao criar dir. uploads."); } }

            $sqlInsertDoc = "INSERT INTO auditoria_documentos_planejamento (auditoria_id, nome_arquivo_original, nome_arquivo_armazenado, caminho_arquivo, tipo_mime, tamanho_bytes, usuario_upload_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmtDoc = $conexao->prepare($sqlInsertDoc);
            foreach ($arquivosUploadNovos as $docTemp) {
                $nome_final_arquivo = $docTemp['nome_armazenado'];
                $caminho_destino_completo = $diretorio_auditoria_final_fisico . $nome_final_arquivo;
                $caminho_relativo_db = $caminho_relativo_base_db . $nome_final_arquivo;
                if (!rename($docTemp['caminho_temp'], $caminho_destino_completo)) { if(file_exists($docTemp['caminho_temp'])) @unlink($docTemp['caminho_temp']); throw new Exception("Falha ao mover doc '{$docTemp['nome_original']}'."); }
                if (!$stmtDoc->execute([$auditoria_id_edit, $docTemp['nome_original'], $nome_final_arquivo, $caminho_relativo_db, $docTemp['tipo_mime'], $docTemp['tamanho_bytes'], $gestor_id_atualizando])) { if(file_exists($caminho_destino_completo)) @unlink($caminho_destino_completo); throw new Exception("Falha DB doc '{$docTemp['nome_original']}'.");}
            }
        }

        $conexao->commit();
        dbRegistrarLogAcesso($gestor_id_atualizando, $_SERVER['REMOTE_ADDR'], 'update_auditoria_planejada', 1, "Auditoria ID: $auditoria_id_edit atualizada.", $conexao);
        return true;

    } catch (Exception $e) {
        if ($conexao->inTransaction()) $conexao->rollBack();
        error_log("Erro ao atualizar auditoria planejada ID $auditoria_id_edit: " . $e->getMessage());
        // Limpeza de arquivos temporários de NOVOS uploads se a transação falhou
        if (!empty($arquivosUploadNovos)) {
            foreach ($arquivosUploadNovos as $docTemp) { if (isset($docTemp['caminho_temp']) && file_exists($docTemp['caminho_temp'])) { @unlink($docTemp['caminho_temp']); } }
             if (isset($diretorio_auditoria_final_fisico)) { // Se diretório de upload foi preparado
                 $temp_dir_req = dirname($arquivosUploadNovos[0]['caminho_temp']); // Diretório temp/sessao_timestamp
                 if (is_dir($temp_dir_req) && !(new FilesystemIterator($temp_dir_req))->valid()) { @rmdir($temp_dir_req); }
             }
        }
        return "Erro interno ao salvar: " . $e->getMessage();
    }
}


/**
 * Busca todos os detalhes de uma auditoria específica para visualização E EDIÇÃO.
 * Inclui informações gerais, itens, evidências, documentos de planejamento,
 * IDs de requisitos selecionados (para manuais) e mapa de responsáveis por seção (para equipes).
 * Valida se a auditoria pertence à empresa do gestor.
 */
function getDetalhesCompletosAuditoria(PDO $conexao, int $auditoria_id, int $empresa_id_gestor): ?array {
    $detalhes_auditoria = [];

    // 1. Dados Gerais da Auditoria
    $sql_geral = "SELECT a.*, m.nome as nome_modelo, u_auditor.nome as nome_auditor_individual,
                         u_gestor.nome as nome_gestor_responsavel, eq.nome as nome_equipe
                  FROM auditorias a
                  LEFT JOIN modelos_auditoria m ON a.modelo_id = m.id
                  LEFT JOIN usuarios u_auditor ON a.auditor_responsavel_id = u_auditor.id
                  LEFT JOIN usuarios u_gestor ON a.gestor_responsavel_id = u_gestor.id
                  LEFT JOIN equipes eq ON a.equipe_id = eq.id
                  WHERE a.id = :auditoria_id AND a.empresa_id = :empresa_id_gestor";
    $stmt_geral = $conexao->prepare($sql_geral);
    $stmt_geral->execute([':auditoria_id' => $auditoria_id, ':empresa_id_gestor' => $empresa_id_gestor]);
    $auditoria_geral = $stmt_geral->fetch(PDO::FETCH_ASSOC);

    if (!$auditoria_geral) return null;
    $detalhes_auditoria['info'] = $auditoria_geral;

    // Responsável Display e Detalhes de Seção para Edição
    $detalhes_auditoria['info']['secao_responsaveis_mapa'] = []; // Inicializa
    $detalhes_auditoria['info']['secoes_responsaveis_detalhes'] = []; // Para display em detalhes.php

    if (!empty($auditoria_geral['equipe_id']) && !empty($auditoria_geral['nome_equipe'])) {
        $detalhes_auditoria['info']['responsavel_display'] = 'Equipe: ' . htmlspecialchars($auditoria_geral['nome_equipe']);

        // *** Query para buscar responsáveis por seção, INCLUINDO auditor_designado_id ***
        $sql_secao_resp = "SELECT asr.secao_modelo_nome, u.nome as nome_auditor_secao, asr.auditor_designado_id
                           FROM auditoria_secao_responsaveis asr
                           JOIN usuarios u ON asr.auditor_designado_id = u.id
                           WHERE asr.auditoria_id = :auditoria_id
                           ORDER BY asr.secao_modelo_nome ASC";
        $stmt_secao_resp = $conexao->prepare($sql_secao_resp);
        $stmt_secao_resp->execute([':auditoria_id' => $auditoria_id]);
        $secoes_com_responsaveis_db = $stmt_secao_resp->fetchAll(PDO::FETCH_ASSOC);
        
        $detalhes_auditoria['info']['secoes_responsaveis_detalhes'] = $secoes_com_responsaveis_db; // Para display

        // Criar o mapa [nome_secao => auditor_id] para repopulação do formulário de edição
        foreach($secoes_com_responsaveis_db as $sr_db) {
            $detalhes_auditoria['info']['secao_responsaveis_mapa'][$sr_db['secao_modelo_nome']] = $sr_db['auditor_designado_id'];
        }

    } elseif (!empty($auditoria_geral['nome_auditor_individual'])) {
        $detalhes_auditoria['info']['responsavel_display'] = htmlspecialchars($auditoria_geral['nome_auditor_individual']);
    } else {
        $detalhes_auditoria['info']['responsavel_display'] = '<em class="text-muted">Não atribuído</em>';
    }

    // 2. Documentos de Planejamento (igual antes)
    $sql_docs_plan = "SELECT id, nome_arquivo_original, nome_arquivo_armazenado, caminho_arquivo, tipo_mime, tamanho_bytes, data_upload
                      FROM auditoria_documentos_planejamento
                      WHERE auditoria_id = :auditoria_id ORDER BY nome_arquivo_original ASC";
    $stmt_docs_plan = $conexao->prepare($sql_docs_plan);
    $stmt_docs_plan->execute([':auditoria_id' => $auditoria_id]);
    $detalhes_auditoria['documentos_planejamento'] = $stmt_docs_plan->fetchAll(PDO::FETCH_ASSOC);

    // 3. Itens da Auditoria, Evidências e Planos de Ação por Item (igual antes)
    $sql_itens = "SELECT ai.*, u_resp_auditor.nome as nome_respondido_por_auditor, u_rev_gestor.nome as nome_revisado_por_gestor
                  FROM auditoria_itens ai
                  LEFT JOIN usuarios u_resp_auditor ON ai.respondido_por_auditor_id = u_resp_auditor.id
                  LEFT JOIN usuarios u_rev_gestor ON ai.revisado_por_gestor_id = u_rev_gestor.id
                  WHERE ai.auditoria_id = :auditoria_id
                  ORDER BY ai.secao_item ASC, ai.ordem_item ASC, ai.id ASC";
    $stmt_itens = $conexao->prepare($sql_itens);
    $stmt_itens->execute([':auditoria_id' => $auditoria_id]);
    $itens_auditoria_db = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

    // Buscar IDs dos requisitos selecionados para auditorias manuais (para repopulação)
    if (empty($auditoria_geral['modelo_id']) && !empty($itens_auditoria_db)) {
        $detalhes_auditoria['info']['requisitos_selecionados_ids'] = array_values(array_unique(array_column($itens_auditoria_db, 'requisito_id')));
    } else {
        $detalhes_auditoria['info']['requisitos_selecionados_ids'] = [];
    }


    // Coleta de evidências e planos de ação (igual antes)
    $stmt_evidencias = $conexao->prepare("SELECT id, nome_arquivo_original, nome_arquivo_armazenado, caminho_arquivo, tipo_mime, tamanho_bytes, descricao, data_upload FROM auditoria_evidencias WHERE auditoria_item_id = :item_id ORDER BY nome_arquivo_original ASC");
    $stmt_planos_acao_item = $conexao->prepare("SELECT pa.*, u_resp_pa.nome as nome_responsavel_plano FROM auditoria_planos_acao pa LEFT JOIN usuarios u_resp_pa ON pa.responsavel_id = u_resp_pa.id WHERE pa.auditoria_item_id = :item_id ORDER BY pa.data_criacao ASC");

    foreach ($itens_auditoria_db as &$item_db_ref) { // Usar referência para modificar o array original
        $stmt_evidencias->execute([':item_id' => $item_db_ref['id']]);
        $item_db_ref['evidencias'] = $stmt_evidencias->fetchAll(PDO::FETCH_ASSOC);
        $stmt_planos_acao_item->execute([':item_id' => $item_db_ref['id']]);
        $item_db_ref['planos_acao'] = $stmt_planos_acao_item->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($item_db_ref); // Desfazer referência

    $itens_agrupados_por_secao = [];
    if (!empty($itens_auditoria_db)) {
        if ($detalhes_auditoria['info']['modelo_id']) {
            foreach ($itens_auditoria_db as $item_aud_val) { // Nova variável de loop
                $secao_nome = !empty(trim($item_aud_val['secao_item'])) ? trim($item_aud_val['secao_item']) : 'Itens Gerais / Sem Seção';
                $itens_agrupados_por_secao[$secao_nome][] = $item_aud_val;
            }
        } else {
            $itens_agrupados_por_secao['Itens Selecionados Manualmente'] = $itens_auditoria_db;
        }
    }
    $detalhes_auditoria['itens_por_secao'] = $itens_agrupados_por_secao;

    // Placeholders para Planos de Ação Gerais e Histórico (igual antes)
    $detalhes_auditoria['planos_acao_gerais'] = [];
    $detalhes_auditoria['historico_eventos'] = [];

    return $detalhes_auditoria;
}
