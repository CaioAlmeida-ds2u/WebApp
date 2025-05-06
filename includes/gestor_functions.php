<?php
// includes/gestor_functions.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php'; // Confere se config inclui db.php (seu código anterior incluía os dois)
// Incluir funcoes_upload para a lógica de mover arquivos temporários dentro da transação
require_once __DIR__ . '/funcoes_upload.php';


/**
 * Busca auditorias do gestor responsável, filtradas pela empresa e com paginação.
 * Retorna um array com as auditorias e o total de auditorias encontradas.
 * Incluindo nome do modelo e auditor/equipe para exibição na lista.
 * // MODIFICADO: Adiciona colunas de equipe e verifica quem está atribuído.
 */
function getMinhasAuditorias(PDO $conexao, int $gestor_id, int $empresa_id, int $pagina = 1, int $por_pagina = 10, string $filtro = ''): array {
    $offset = max(0, ($pagina - 1) * $por_pagina);
    $result = ['auditorias' => [], 'total' => 0];

    $params = [':gestor_id' => $gestor_id, ':empresa_id' => $empresa_id];
    $where_clauses = ["gestor_responsavel_id = :gestor_id", "empresa_id = :empresa_id"];

    if ($filtro) {
        // Sanitiza filtro
         $filtro_sanitizado = '%' . filter_var($filtro, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH) . '%';
        $where_clauses[] = "(titulo LIKE :filtro OR escopo LIKE :filtro OR objetivo LIKE :filtro)";
        $params[':filtro'] = $filtro_sanitizado;
    }

    $sql_where = implode(' AND ', $where_clauses);

    try {
        // Contar total de auditorias com filtros
        $sql_count = "SELECT COUNT(*) as total FROM auditorias WHERE $sql_where";
        $stmt_count = $conexao->prepare($sql_count);
         // Bind dos parâmetros do WHERE
        foreach ($params as $key => &$val) { $stmt_count->bindValue($key, $val); } unset($val); // PDO decide tipo

        $stmt_count->execute();
        $result['total'] = (int)$stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

        // Buscar auditorias com paginação, filtros, nome modelo, nome auditor/equipe
        $sql = "SELECT a.id, a.titulo, a.status, a.data_inicio_planejada, a.data_fim_planejada, a.data_criacao, a.data_modificacao, -- Inclui datas criação/modificação para ordenação/contexto
                       a.modelo_id, m.nome as nome_modelo,
                       a.auditor_responsavel_id, u.nome as nome_auditor,
                       a.equipe_id, e.nome as nome_equipe -- JOIN com equipes
                FROM auditorias a
                LEFT JOIN modelos_auditoria m ON a.modelo_id = m.id
                LEFT JOIN usuarios u ON a.auditor_responsavel_id = u.id
                LEFT JOIN equipes e ON a.equipe_id = e.id -- JOIN com equipes
                WHERE $sql_where
                ORDER BY a.data_criacao DESC LIMIT :limit OFFSET :offset"; // Mantido ordenação por criação

        $stmt = $conexao->prepare($sql);
        // Bind dos parâmetros do WHERE
         foreach ($params as $key => &$val) { $stmt->bindValue($key, $val); } unset($val);
        // Bind dos parâmetros de LIMIT e OFFSET
        $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formatar quem é o responsável (Auditor ou Equipe) para exibição na lista
         foreach ($auditorias as &$auditoria) {
             if (!empty($auditoria['equipe_id'])) {
                  $auditoria['responsavel_display'] = 'Equipe: ' . htmlspecialchars($auditoria['nome_equipe'] ?? 'N/D');
             } elseif (!empty($auditoria['auditor_responsavel_id'])) {
                  $auditoria['responsavel_display'] = 'Auditor: ' . htmlspecialchars($auditoria['nome_auditor'] ?? 'N/D');
             } else {
                  $auditoria['responsavel_display'] = 'Não atribuído';
             }
              // Remover colunas de JOIN não mais necessárias
             unset($auditoria['auditor_responsavel_id'], $auditoria['equipe_id'], $auditoria['nome_auditor'], $auditoria['nome_equipe']);
         }
         unset($auditoria); // Desfazer referência

        $result['auditorias'] = $auditorias;


    } catch (PDOException $e) {
        error_log("Erro getMinhasAuditorias (Gestor ID: $gestor_id, Empresa ID: $empresa_id): " . $e->getMessage());
    }
    return $result;
}

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
 * Cria uma nova auditoria e popula seus itens.
 * Agora trata ATRIBUIÇÃO (auditor ou equipe) e DOCUMENTOS DE PLANEJAMENTO.
 * Usa TRANSACÃO para garantir atomicidade DB + FILE MOVEMENT.
 *
 * @param PDO $conexao Conexão PDO.
 * @param array $dadosAuditoria { titulo, empresa_id, gestor_id, auditor_id, equipe_id, escopo, objetivo, instrucoes, data_inicio, data_fim, modelo_id, requisitos_selecionados (array de IDs se manual) }
 *                              Note: espera que apenas um entre auditor_id e equipe_id seja INT > 0, o outro NULL. Validação deve ser feita antes.
 * @param array $arquivosUpload Array de metadados dos arquivos válidos processados temporariamente (saída de processarDocumentUploads).
 *                              Cada item: { nome_original, nome_armazenado, caminho_temp, tipo_mime, tamanho_bytes }
 * @return int|null Retorna o ID da nova auditoria em caso de sucesso ou null em caso de falha.
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

// ... (Restante das funções gestor_functions.php)

?>