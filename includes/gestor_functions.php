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
function criarAuditoria(PDO $conexao, array $dadosAuditoria, array $arquivosUpload = []): ?int {
    // Validação básica e de atribuição (redunante mas útil aqui)
     if (empty($dadosAuditoria['titulo']) || empty($dadosAuditoria['empresa_id']) || empty($dadosAuditoria['gestor_id'])) {
          error_log("criarAuditoria: Dados essenciais faltando na chamada. Dados: " . json_encode($dadosAuditoria));
          return null;
     }

    $auditor_id = $dadosAuditoria['auditor_id'] ?? null;
    $equipe_id = $dadosAuditoria['equipe_id'] ?? null;

     // Validar IDs se não são null
     if ($auditor_id !== null && !filter_var($auditor_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
          error_log("criarAuditoria: auditor_id inválido: " . $auditor_id); return null;
     }
      if ($equipe_id !== null && !filter_var($equipe_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
          error_log("criarAuditoria: equipe_id inválido: " . $equipe_id); return null;
     }

     // Assegurar que um E APENAS UM está preenchido (regra de negócio para esta versão)
     if ($auditor_id === null && $equipe_id === null) { error_log("criarAuditoria: Nenhuma atribuição (auditor nem equipe) fornecida."); return null; }
     if ($auditor_id !== null && $equipe_id !== null) { error_log("criarAuditoria: Ambas atribuições fornecidas. Usando apenas Auditor."); $equipe_id = null; }


    $conexao->beginTransaction();
    $auditoria_id = null; // Inicializa antes do try

    // Para o cleanup em caso de ROLLBACK, vamos manter uma lista dos arquivos que foram movidos para o destino FINAL.
     // Eles são movidos DENTRO da transação, então em caso de rollback, o sistema de arquivos NÃO desfaz o rename/move.
    $arquivos_salvos_no_final_paths = [];


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

        $paramsAuditoria = [
            ':titulo' => $dadosAuditoria['titulo'],
            ':empresa_id' => $dadosAuditoria['empresa_id'],
            ':modelo_id' => $dadosAuditoria['modelo_id'], // null ou ID INT
            ':auditor_responsavel_id' => $auditor_id, // null ou ID INT
            ':equipe_id' => $equipe_id, // null ou ID INT
            ':gestor_responsavel_id' => $dadosAuditoria['gestor_id'],
            ':escopo' => empty($dadosAuditoria['escopo']) ? null : $dadosAuditoria['escopo'],
            ':objetivo' => empty($dadosAuditoria['objetivo']) ? null : $dadosAuditoria['objetivo'],
            ':instrucoes' => empty($dadosAuditoria['instrucoes']) ? null : $dadosAuditoria['instrucoes'],
            ':data_inicio_planejada' => empty($dadosAuditoria['data_inicio']) ? null : $dadosAuditoria['data_inicio'],
            ':data_fim_planejada' => empty($dadosAuditoria['data_fim']) ? null : $dadosAuditoria['data_fim'],
            ':criado_por' => $dadosAuditoria['gestor_id'],
            ':modificado_por' => $dadosAuditoria['gestor_id']
        ];

        if (!$stmtAuditoria->execute($paramsAuditoria)) {
            // PDOException é lançada automaticamente em modo ERRMODE_EXCEPTION
            throw new Exception("Falha ao inserir auditoria principal."); // Lançar exceção genérica
        }

        $auditoria_id = $conexao->lastInsertId();
        if ($auditoria_id == 0) {
             throw new Exception("ID da auditoria não foi gerado corretamente após a inserção.");
        }

        // 2. Popular `auditoria_itens` com base em modelo ou requisitos manuais
        $requisitos_ids_origem = [];
        if ($dadosAuditoria['modelo_id']) {
            $sqlReqs = "SELECT requisito_id FROM modelo_itens WHERE modelo_id = :modelo_id ORDER BY ordem_item ASC, id ASC";
            $stmtReqs = $conexao->prepare($sqlReqs);
            $stmtReqs->execute([':modelo_id' => $dadosAuditoria['modelo_id']]);
            $requisitos_ids_origem = array_column($stmtReqs->fetchAll(PDO::FETCH_ASSOC), 'requisito_id');
        } elseif (!empty($dadosAuditoria['requisitos_selecionados'])) {
            // Requisitos manuais: Usar a ordem em que vieram do POST como ordem_item
            $requisitos_ids_origem = $dadosAuditoria['requisitos_selecionados'];
        }

        if (!empty($requisitos_ids_origem)) {
             // Fetch detalhes dos requisitos
             $placeholders = implode(',', array_fill(0, count($requisitos_ids_origem), '?'));
             $sqlGetReqDetails = "SELECT id, codigo, nome, descricao, categoria, norma_referencia, guia_evidencia, peso FROM requisitos_auditoria WHERE id IN ($placeholders)";
             $stmtGetReqDetails = $conexao->prepare($sqlGetReqDetails);
             // Passa os IDs em $requisitos_ids_origem, que podem não estar em ordem sequencial
             // PDO vai bindar corretamente.
             $stmtGetReqDetails->execute($requisitos_ids_origem);
             $requisitosDetails = $stmtGetReqDetails->fetchAll(PDO::FETCH_ASSOC);

             // Indexar detalhes por ID para acesso rápido
             $requisitosMap = [];
             foreach ($requisitosDetails as $req) { $requisitosMap[$req['id']] = $req; }

             // Verificar se todos os IDs da origem foram encontrados nos detalhes buscados
             if (count($requisitos_ids_origem) !== count($requisitosMap)) {
                  // Algum ID de requisito selecionado manualmente ou no modelo não foi encontrado (excluído?)
                  error_log("criarAuditoria: Divergência - IDs origem(".count($requisitos_ids_origem).") != IDs encontrados(".count($requisitosMap)."). Requisitos faltando: " . json_encode(array_diff($requisitos_ids_origem, array_keys($requisitosMap))));
                  // Decidir se isso é erro CRÍTICO ou apenas ignora os requisitos ausentes.
                  // Vamos lançar um erro por segurança/consistência.
                   throw new Exception("Falha ao carregar detalhes de um ou mais requisitos selecionados.");
             }


            $sqlItem = "INSERT INTO auditoria_itens (
                auditoria_id, requisito_id, codigo_item, nome_item, descricao_item,
                categoria_item, norma_item, guia_evidencia_item, peso_item, ordem_item,
                status_conformidade
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendente')";
            $stmtItem = $conexao->prepare($sqlItem);

            $ordem_item = 0; // Ordem visual dentro da auditoria, sequencial a partir de 0
            foreach ($requisitos_ids_origem as $requisito_id) { // Itera na ORDEM definida pela origem (modelo ou manual)
                $reqDetail = $requisitosMap[$requisito_id]; // Detalhes do requisito (garantido existir pelo check acima)

                $paramsItem = [
                    $auditoria_id,
                    $reqDetail['id'],
                    empty($reqDetail['codigo']) ? null : $reqDetail['codigo'],
                    $reqDetail['nome'],
                    $reqDetail['descricao'],
                    empty($reqDetail['categoria']) ? null : $reqDetail['categoria'],
                    empty($reqDetail['norma_referencia']) ? null : $reqDetail['norma_referencia'],
                    empty($reqDetail['guia_evidencia']) ? null : $reqDetail['guia_evidencia'],
                    empty($reqDetail['peso']) ? 1 : (int)$reqDetail['peso'],
                    $ordem_item, // Ordem baseada na posição no array de origem
                ];

                 if (!$stmtItem->execute($paramsItem)) {
                    $errorInfo = $stmtItem->errorInfo();
                    $errorMessage = "Falha ao inserir item da auditoria (requisito_id: {$reqDetail['id']}). SQLSTATE: " . $errorInfo[0] . ", Code: " . $errorInfo[1] . ", Msg: " . $errorInfo[2];
                    throw new PDOException($errorMessage); // Lançar exceção DB
                }
                $ordem_item++; // Próxima ordem
            }
        } else {
             // Nenhuma regra de negócio impede uma auditoria planejada sem itens por enquanto.
             // error_log("criarAuditoria: Nenhum item (requisito) para adicionar. Auditoria ID $auditoria_id criada sem itens.");
        }


        // 3. Processar Inserção de Documentos de Planejamento
        if (!empty($arquivosUpload)) {
             // Definir o diretório final para os arquivos desta auditoria (usando o ID gerado)
             $diretorio_base_uploads = __DIR__ . '/../../uploads/'; // Base da pasta uploads
             $diretorio_auditoria_final = $diretorio_base_uploads . 'auditorias/' . $auditoria_id . '/'; // Caminho físico final

             // Criar o diretório final para a auditoria se não existir
            if (!is_dir($diretorio_auditoria_final)) {
                 // Cria recursivamente. Permissões 0755 para dono (server) rwx, grupo/outros rx.
                 // Pode ser necessário ajustar UMask ou usar chmod explicitamente após mkdir.
                if (!mkdir($diretorio_auditoria_final, 0755, true)) {
                     throw new Exception("Falha ao criar diretório final para documentos da auditoria ID $auditoria_id.");
                }
            }
             // Verificar permissão de escrita no diretório final
             if (!is_writable($diretorio_auditoria_final)) {
                 throw new Exception("Permissão negada para escrever no diretório final para documentos da auditoria ID $auditoria_id.");
             }


            $sqlInsertDoc = "INSERT INTO auditoria_documentos_planejamento (
                auditoria_id, nome_arquivo_original, nome_arquivo_armazenado, caminho_arquivo, tipo_mime, tamanho_bytes, descricao, usuario_upload_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtDoc = $conexao->prepare($sqlInsertDoc);

            foreach ($arquivosUpload as $docTemp) { // Itera sobre os arquivos que processarDocumentUploads validou e salvou em temp
                 // $docTemp contém: { nome_original, nome_armazenado (o nome seguro gerado p/ temp), caminho_temp (FULL path original temp) }

                // O nome_armazenado de $docTemp já é o nome seguro e único que movemos para temp.
                // Usaremos este mesmo nome para o arquivo final E para salvar no DB.
                $nome_final_arquivo = $docTemp['nome_armazenado'];
                $caminho_destino_arquivo_final_completo = $diretorio_auditoria_final . $nome_final_arquivo;
                $caminho_relativo_db = 'auditorias/' . $auditoria_id . '/' . $nome_final_arquivo; // Caminho para salvar no DB

                // Mover o arquivo do diretório temporário ÚNICO desta requisição para o diretório FINAL da auditoria
                 // error_log("Mover doc temp: {$docTemp['caminho_temp']} para final: {$caminho_destino_arquivo_final_completo}");
                if (!rename($docTemp['caminho_temp'], $caminho_destino_arquivo_final_completo)) {
                     // Se falhou o rename, o arquivo PODE ter ficado no temp original. Tentar limpar de lá no rollback.
                     // throw new Exception("Falha ao mover arquivo '{$docTemp['nome_original']}' do temp para o destino final da auditoria.");
                      // Tentar limpar o arquivo temporário ANTES de lançar a exceção
                     if (file_exists($docTemp['caminho_temp'])) { @unlink($docTemp['caminho_temp']); } // Melhor Esforço
                      // Pode ser útil logar erro específico de rename se possível
                      throw new Exception("Falha ao mover arquivo '{$docTemp['nome_original']}' para o destino final.");
                }
                // error_log("Doc temp movido com sucesso para final.");

                // Inserir o registro do documento no banco de dados
                 $paramsDoc = [
                    $auditoria_id,
                    $docTemp['nome_original'],
                    $nome_final_arquivo, // Salva o nome único no destino
                    $caminho_relativo_db, // Salva o caminho relativo no DB
                    $docTemp['tipo_mime'],
                    $docTemp['tamanho_bytes'],
                    null, // Descrição não veio do HTML
                    $dadosAuditoria['gestor_id'] // Quem fez o upload
                 ];

                if (!$stmtDoc->execute($paramsDoc)) {
                     // Se falhar a inserção no DB para ESTE documento
                     // Lançar exceção. A transação fará Rollback.
                     // CRÍTICO: Limpar o arquivo recém movido para o destino final, pois o DB rollback NÃO o desfará!
                     if (file_exists($caminho_destino_arquivo_final_completo)) { @unlink($caminho_destino_arquivo_final_completo); } // Melhor Esforço
                     $errorInfo = $stmtDoc->errorInfo();
                     $errorMessage = "Falha ao inserir registro de documento para '{$docTemp['nome_original']}'. SQLSTATE: " . $errorInfo[0] . ", Code: " . $errorInfo[1] . ", Msg: " . $errorInfo[2];
                     throw new PDOException($errorMessage); // Lançar exceção DB

                }
                 // error_log("Registro de documento inserido no DB para '{$docTemp['nome_original']}'.");
                 // Manter o caminho do arquivo final salvo em uma lista para limpeza em caso de rollback (extra safe)
                 $arquivos_salvos_no_final_paths[] = $caminho_destino_arquivo_final_completo;

            } // Fim loop documentos
        }
        // --- FIM Processar Documentos ---


        // Se chegou aqui, TUDO (Auditoria, Itens, Documentos) deu certo dentro da transação
        $conexao->commit();
        // error_log("criarAuditoria: Transação COMMITADA com sucesso para Auditoria ID: $auditoria_id.");
        // NÃO precisamos limpar $arquivos_salvos_no_final_paths aqui, eles estão salvos no DB e no disco.

        return (int)$auditoria_id;


    } catch (Exception $e) { // Captura PDOException e outras Exceções lançadas acima
        // Qualquer exceção dentro do TRY faz o ROLLBACK do DB.
        if ($conexao->inTransaction()) {
             $conexao->rollBack();
            // error_log("criarAuditoria: Transação ROLLBACK por erro: " . $e->getMessage());
        }

        error_log("Erro CRÍTICO em criarAuditoria: " . $e->getMessage());
        // error_log("Erro Trace: " . $e->getTraceAsString());


        // --- CRÍTICO: LIMPAR ARQUIVOS TEMPORÁRIOS E ARQUIVOS MOVIDOS ANTES DO ROLLBACK ---
        // Itera sobre os arquivos que processarDocumentUploads indicou como válidos e salvos em temp.
        // Tenta excluir os arquivos ONDE ELES ESTÃO POTENCIALMENTE AGORA.
        if (!empty($arquivosUpload)) {
             foreach ($arquivosUpload as $docTemp) {
                 $cleaned = false;
                 // 1. Tenta limpar da localização temporária original (caminho_temp)
                if (file_exists($docTemp['caminho_temp'])) {
                     if (@unlink($docTemp['caminho_temp'])) { $cleaned = true; /* error_log("Rollback Cleanup: Limpo de TEMP: " . $docTemp['caminho_temp']);*/ }
                 }

                 // 2. Tenta limpar da localização final POTENCIAL, se a auditoria foi criada e o arquivo movido para lá antes do erro
                 // Verifica se o caminho final foi registrado em $arquivos_salvos_no_final_paths durante o processo antes do erro
                 $nome_final_arquivo = $docTemp['nome_armazenado'];
                 // Inferir o caminho final potencial com base no ID gerado (se houver) e o nome armazenado
                 if ($auditoria_id !== null && $auditoria_id > 0) {
                     $potencial_caminho_final = __DIR__ . '/../../uploads/auditorias/' . $auditoria_id . '/' . $nome_final_arquivo;
                     if (file_exists($potencial_caminho_final)) {
                         if (@unlink($potencial_caminho_final)) { $cleaned = true; /* error_log("Rollback Cleanup: Limpo de FINAL POTENCIAL: " . $potencial_caminho_final);*/ }
                     }
                 }
                 // Verificar se o arquivo estava na lista de arquivos movidos com sucesso antes do rollback e não foi limpado
                 if (!$cleaned && in_array($potencial_caminho_final, $arquivos_salvos_no_final_paths)) {
                     error_log("AVISO CRÍTICO: Arquivo em {$potencial_caminho_final} deveria ter sido limpado após rollback!");
                 }


                 if (!$cleaned) {
                     // Se após tentar limpar de temp e do local final potencial, ainda não foi marcado como limpo
                     // Isso indica que o arquivo original moveu para temp ($docTemp['caminho_temp'] foi válido)
                     // Mas a tentativa de limpá-lo do temp ou movê-lo para o final e depois limpar no final falhou.
                     // Este arquivo ficou órfão no sistema de arquivos. Deve ser limpo por cron.
                      error_log("AVISO CRÍTICO: Arquivo de upload de auditoria FALHOU LIMPEZA TOTAL após ROLLBACK: '{$docTemp['nome_original']}'. Caminho temp era: {$docTemp['caminho_temp']}");
                 }
            } // Fim foreach arquivosUpload para limpeza

            // Tentar remover o diretório temporário ÚNICO desta requisição (se ele existe e está vazio AGORA)
             $requestTempDirAbs = ($arquivosUpload[0]['caminho_temp'] ?? null) ? dirname($arquivosUpload[0]['caminho_temp']) . '/' : null;
            if ($requestTempDirAbs !== null && is_dir($requestTempDirAbs)) {
                $items_in_dir = scandir($requestTempDirAbs);
                if ($items_in_dir !== false && count($items_in_dir) === 2) { // Verifica se está vazio
                     if (!@rmdir($requestTempDirAbs)) { error_log("criarAuditoria: AVISO: Falha ao remover diretório temp vazio: " . $requestTempDirAbs); }
                } else {
                     // Diretório não vazio, pode ter arquivos que não foram limpados ou outros itens inesperados.
                     error_log("criarAuditoria: AVISO: Diretório temp '$requestTempDirAbs' não está vazio após limpeza, " . count($items_in_dir) . " itens restantes.");
                }
            }

            error_log("criarAuditoria: Limpeza de arquivos de upload (temp/final) concluída após erro e rollback.");

        } else {
             // Nenhum arquivo de upload para limpar.
        }


        // Retorna null para indicar falha (conforme assinatura da função)
        // O catch acima já logou o erro detalhado.
        return null;
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
