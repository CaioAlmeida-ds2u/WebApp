<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';



/**
 * Busca auditorias do gestor responsável, filtradas pela empresa e com paginação.
 * Retorna um array com as auditorias e o total de auditorias encontradas.
 */
function getMinhasAuditorias(PDO $conexao, int $gestor_id, int $empresa_id, int $pagina = 1, int $por_pagina = 10, string $filtro = ''): array {
    $offset = max(0, ($pagina - 1) * $por_pagina);
    $result = ['auditorias' => [], 'total' => 0];

    try {
        // Contar total de auditorias
        $sql_count = "SELECT COUNT(*) as total 
                      FROM auditorias 
                      WHERE gestor_responsavel_id = :gestor_id 
                      AND empresa_id = :empresa_id";
        if ($filtro) {
            $sql_count .= " AND (titulo LIKE :filtro OR escopo LIKE :filtro OR objetivo LIKE :filtro)";
        }
        $stmt = $conexao->prepare($sql_count);
        $stmt->bindValue(':gestor_id', $gestor_id, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        if ($filtro) {
            $stmt->bindValue(':filtro', "%$filtro%", PDO::PARAM_STR);
        }
        $stmt->execute();
        $result['total'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Buscar auditorias
        $sql = "SELECT id, titulo, status, data_inicio_planejada, data_fim_planejada, modelo_id,
                       (SELECT nome FROM usuarios WHERE id = auditor_responsavel_id) as auditor_nome
                FROM auditorias 
                WHERE gestor_responsavel_id = :gestor_id 
                AND empresa_id = :empresa_id";
        if ($filtro) {
            $sql .= " AND (titulo LIKE :filtro OR escopo LIKE :filtro OR objetivo LIKE :filtro)";
        }
        $sql .= " ORDER BY data_criacao DESC LIMIT :limit OFFSET :offset";
        $stmt = $conexao->prepare($sql);
        $stmt->bindValue(':gestor_id', $gestor_id, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($filtro) {
            $stmt->bindValue(':filtro', "%$filtro%", PDO::PARAM_STR);
        }
        $stmt->execute();
        $result['auditorias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro getMinhasAuditorias: " . $e->getMessage());
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
        'nao_conformidades_abertas' => 0,
        'auditores_ativos' => 0
    ];
    try {
        $sqlStatus = "SELECT status, COUNT(*) as total FROM auditorias WHERE empresa_id = :eid GROUP BY status";
        $stmtStatus = $conexao->prepare($sqlStatus);
        $stmtStatus->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmtStatus->execute();
        $statusCounts = $stmtStatus->fetchAll(PDO::FETCH_KEY_PAIR);

        $stats['total_ativas'] = ($statusCounts['Planejada'] ?? 0)
                              + ($statusCounts['Em Andamento'] ?? 0)
                              + ($statusCounts['Pausada'] ?? 0)
                              + ($statusCounts['Em Revisão'] ?? 0);
        $stats['para_revisao'] = $statusCounts['Concluída (Auditor)'] ?? 0;

        $sqlNC = "SELECT COUNT(ai.id)
                  FROM auditoria_itens ai
                  JOIN auditorias a ON ai.auditoria_id = a.id
                  WHERE a.empresa_id = :eid
                    AND ai.status_conformidade = 'Não Conforme'
                    AND a.status NOT IN ('Aprovada', 'Rejeitada', 'Cancelada')";
        $stmtNC = $conexao->prepare($sqlNC);
        $stmtNC->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmtNC->execute();
        $stats['nao_conformidades_abertas'] = (int) $stmtNC->fetchColumn();

        $stmtAuditores = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE empresa_id = :eid AND perfil = 'auditor' AND ativo = 1");
        $stmtAuditores->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmtAuditores->execute();
        $stats['auditores_ativos'] = (int) $stmtAuditores->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erro getGestorDashboardStats (Empresa ID: $empresa_id): " . $e->getMessage());
    }
    return $stats;
}

/**
 * Busca as últimas N auditorias pendentes de revisão pelo gestor para a empresa.
 */
function getAuditoriasParaRevisar(PDO $conexao, int $empresa_id, int $limit = 5): array {
    $sql = "SELECT a.id, a.titulo, a.data_conclusao_auditor, u.nome as nome_auditor
            FROM auditorias a
            LEFT JOIN usuarios u ON a.auditor_responsavel_id = u.id
            WHERE a.empresa_id = :eid AND a.status = 'Concluída (Auditor)'
            ORDER BY a.data_conclusao_auditor DESC, a.id DESC
            LIMIT :limit";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro getAuditoriasParaRevisar (Empresa ID: $empresa_id): " . $e->getMessage());
        return [];
    }
}

/**
 * Busca as últimas N auditorias modificadas recentemente para a empresa.
 */
function getAuditoriasRecentesEmpresa(PDO $conexao, int $empresa_id, int $limit = 5): array {
    $sql = "SELECT a.id, a.titulo, a.status, a.data_modificacao, u.nome as nome_auditor
            FROM auditorias a
            LEFT JOIN usuarios u ON a.auditor_responsavel_id = u.id
            WHERE a.empresa_id = :eid
            ORDER BY a.data_modificacao DESC
            LIMIT :limit";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro getAuditoriasRecentesEmpresa (Empresa ID: $empresa_id): " . $e->getMessage());
        return [];
    }
}

/**
 * Busca os auditores ativos de uma empresa específica.
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
              AND ai.status_conformidade IN ('Conforme', 'Não Conforme', 'Parcial', 'N/A')
            GROUP BY ai.status_conformidade";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $whereClauses[] = 'a.titulo LIKE :titulo';
        $params[':titulo'] = '%' . $filtros['titulo'] . '%';
    }
    if (!empty($filtros['auditor_id']) && filter_var($filtros['auditor_id'], FILTER_VALIDATE_INT)) {
        $whereClauses[] = 'a.auditor_responsavel_id = :auditor_id';
        $params[':auditor_id'] = (int)$filtros['auditor_id'];
    }

    $sqlWhere = implode(' AND ', $whereClauses);
    $auditorias = [];

    $sql = "SELECT SQL_CALC_FOUND_ROWS a.id, a.titulo, a.status, a.data_inicio_planejada, a.data_fim_planejada, a.data_modificacao, m.nome as nome_modelo, u_auditor.nome as nome_auditor
            FROM auditorias a
            LEFT JOIN modelos_auditoria m ON a.modelo_id = m.id
            LEFT JOIN usuarios u_auditor ON a.auditor_responsavel_id = u_auditor.id
            WHERE $sqlWhere ORDER BY a.data_modificacao DESC LIMIT :limit OFFSET :offset";
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
    } catch (PDOException $e) {
        error_log("Erro busca getAuditoriasPorEmpresa: " . $e->getMessage());
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
function getRequisitosAtivosAgrupados(PDO $conexao, int $pagina = 1, int $porPagina = 100): array {
    $offset = max(0, ($pagina - 1) * $porPagina);
    $requisitos_por_grupo = [];
    try {
        $sql = "SELECT id, nome, codigo, categoria, norma_referencia
                FROM requisitos_auditoria
                WHERE ativo = 1
                ORDER BY norma_referencia ASC, categoria ASC, codigo ASC, nome ASC
                LIMIT :limit OFFSET :offset";
        $stmt = $conexao->prepare($sql);
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        while ($req = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $grupo = !empty($req['norma_referencia']) ? $req['norma_referencia'] : 'Geral';
            if (!empty($req['categoria'])) {
                $grupo .= ' - ' . $req['categoria'];
            } elseif (empty($req['norma_referencia'])) {
                $grupo = $req['categoria'] ?: 'Sem Categoria';
            }
            $requisitos_por_grupo[$grupo][] = $req;
        }
    } catch (PDOException $e) {
        error_log("Erro buscar req agrupados: " . $e->getMessage());
    }
    return $requisitos_por_grupo;
}
/**
 * Cria uma nova auditoria e popula seus itens com base em um MODELO ou REQUISITOS SELECIONADOS.
 */
function criarAuditoria(PDO $conexao, array $dados): ?int {
    try {
        $conexao->beginTransaction();

        // Log dos dados recebidos para debug
        error_log("Dados criarAuditoria: " . json_encode($dados));

        // Query para auditorias
        $sql = "INSERT INTO auditorias (
            titulo, empresa_id, modelo_id, auditor_responsavel_id, gestor_responsavel_id, 
            escopo, objetivo, instrucoes, data_inicio_planejada, data_fim_planejada, 
            status, criado_por, data_criacao, modificado_por, data_modificacao
        ) VALUES (
            :titulo, :empresa_id, :modelo_id, :auditor_responsavel_id, :gestor_responsavel_id, 
            :escopo, :objetivo, :instrucoes, :data_inicio_planejada, :data_fim_planejada, 
            'Planejada', :criado_por, NOW(), :modificado_por, NOW()
        )";
        
        error_log("Query auditorias: $sql");
        $stmt = $conexao->prepare($sql);
        
        // Mapeamento dos parâmetros
        $params = [
            ':titulo' => $dados['titulo'],
            ':empresa_id' => $dados['empresa_id'],
            ':modelo_id' => $dados['modelo_id'],
            ':auditor_responsavel_id' => $dados['auditor_id'],
            ':gestor_responsavel_id' => $dados['gestor_id'],
            ':escopo' => $dados['escopo'],
            ':objetivo' => $dados['objetivo'],
            ':instrucoes' => $dados['instrucoes'],
            ':data_inicio_planejada' => $dados['data_inicio'],
            ':data_fim_planejada' => $dados['data_fim'],
            ':criado_por' => $dados['gestor_id'],
            ':modificado_por' => $dados['gestor_id']
        ];
        
        error_log("Parâmetros auditorias: " . json_encode($params));
        
        // Executar a query e verificar o sucesso
        if (!$stmt->execute($params)) {
            throw new PDOException("Falha ao inserir auditoria na tabela auditorias.");
        }
        
        // Obter o ID da auditoria
        $auditoria_id = $conexao->lastInsertId();
        error_log("Auditoria ID gerado: $auditoria_id");
        
        // Validar o auditoria_id
        if ($auditoria_id == 0) {
            throw new PDOException("ID da auditoria não foi gerado corretamente (lastInsertId retornou 0).");
        }

        // Inserção dos itens da auditoria
        $requisitos = [];
        if ($dados['modelo_id']) {
            $sql = "SELECT requisito_id FROM modelo_itens WHERE modelo_id = :modelo_id ORDER BY ordem_item, id";
            error_log("Query modelo_itens: $sql");
            $stmt = $conexao->prepare($sql);
            $stmt->execute([':modelo_id' => $dados['modelo_id']]);
            $requisitos = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'requisito_id');
            error_log("Requisitos do modelo: " . json_encode($requisitos));
        } elseif (!empty($dados['requisitos_selecionados'])) {
            $requisitos = $dados['requisitos_selecionados'];
            error_log("Requisitos manuais: " . json_encode($requisitos));
        }

        if (!empty($requisitos)) {
            $sql = "INSERT INTO auditoria_itens (
                auditoria_id, requisito_id, codigo_item, nome_item, descricao_item, 
                categoria_item, norma_item, guia_evidencia_item, peso_item, ordem_item, 
                status_conformidade
            ) SELECT 
                :auditoria_id, r.id, r.codigo, r.nome, r.descricao, 
                r.categoria, r.norma_referencia, r.guia_evidencia, r.peso, 
                :ordem_item, 'Pendente'
            FROM requisitos_auditoria r WHERE r.id = :requisito_id";
            error_log("Query auditoria_itens: $sql");
            $stmt = $conexao->prepare($sql);
            foreach ($requisitos as $ordem => $requisito_id) {
                $params = [
                    ':auditoria_id' => $auditoria_id,
                    ':requisito_id' => $requisito_id,
                    ':ordem_item' => $ordem + 1
                ];
                error_log("Parâmetros auditoria_itens: " . json_encode($params));
                if (!$stmt->execute($params)) {
                    throw new PDOException("Falha ao inserir item da auditoria (requisito_id: $requisito_id).");
                }
            }
        }

        $conexao->commit();
        return (int)$auditoria_id;
    } catch (PDOException $e) {
        $conexao->rollBack();
        error_log("Erro criarAuditoria: " . $e->getMessage() . " | Última query: $sql | Últimos parâmetros: " . json_encode($params ?? []));
        return null;
    }
}