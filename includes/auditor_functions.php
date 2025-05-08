<?php
// Em includes/auditor_functions.php

require_once 'db.php'; // Inclui a conexão com o banco de dados
require_once 'config.php'; // Inclui funções de formatação de data e outras constantes

/**
 * Busca as auditorias atribuídas a um auditor específico (individualmente ou em equipe),
 * com filtros avançados e paginação. OTIMIZADA E REVISADA.
 *
 * @param PDO $conexao Conexão com o banco de dados.
 * @param int $auditor_id ID do auditor logado.
 * @param int $empresa_id ID da empresa do auditor (para validação de segurança).
 * @param int $pagina Página atual (mínimo 1).
 * @param int $por_pagina Itens por página (mínimo 1).
 * @param array $filtros Filtros opcionais ['titulo' => string, 'status' => string, 'gestor_id' => int, 'data_de' => string, 'data_ate' => string]
 * @return array Estrutura com ['auditorias' => [], 'paginacao' => ['pagina_atual' => int, 'total_paginas' => int, 'total_itens' => int, 'itens_por_pagina' => int]]
 */
function getMinhasAuditoriasAuditor(PDO $conexao, int $auditor_id, int $empresa_id, int $pagina = 1, int $por_pagina = 15, array $filtros = []): array
{
    // Validação inicial
    $pagina = max(1, $pagina);
    $por_pagina = max(1, $por_pagina);
    $offset = ($pagina - 1) * $por_pagina;

    // Inicializar resultado
    $resultado = [
        'auditorias' => [],
        'paginacao' => [
            'pagina_atual' => $pagina,
            'total_paginas' => 0,
            'total_itens' => 0,
            'itens_por_pagina' => $por_pagina
        ]
    ];

    // Parâmetros base
    $params = [
        ':param_auditor_id' => $auditor_id,
        ':param_empresa_id' => $empresa_id
    ];

    // Condição base
    $where_base = "a.empresa_id = :param_empresa_id AND (
        a.auditor_responsavel_id = :param_auditor_id 
        OR EXISTS (
            SELECT 1 
            FROM auditoria_secao_responsaveis asr 
            WHERE asr.auditoria_id = a.id 
            AND asr.auditor_designado_id = :param_auditor_id
        )
    )";

    // Filtros
    $where_filtros_arr = [];
    
    if (!empty($filtros['titulo']) && is_string($filtros['titulo'])) {
        $where_filtros_arr[] = "a.titulo LIKE :filtro_titulo";
        $params[':filtro_titulo'] = '%' . trim($filtros['titulo']) . '%';
    }
    
    if (!empty($filtros['status']) && is_string($filtros['status']) && $filtros['status'] !== 'todos') {
        if ($filtros['status'] === 'pendentes') {
            $where_filtros_arr[] = "a.status IN ('Planejada', 'Em Andamento', 'Rejeitada', 'Aguardando Correção Auditor')";
        } else {
            $where_filtros_arr[] = "a.status = :filtro_status";
            $params[':filtro_status'] = $filtros['status'];
        }
    }
    
    if (!empty($filtros['gestor_id']) && filter_var($filtros['gestor_id'], FILTER_VALIDATE_INT)) {
        $where_filtros_arr[] = "a.gestor_responsavel_id = :filtro_gestor_id";
        $params[':filtro_gestor_id'] = (int)$filtros['gestor_id'];
    }
    
    if (!empty($filtros['data_de']) && is_string($filtros['data_de'])) {
        $where_filtros_arr[] = "a.data_inicio_planejada >= :filtro_data_de";
        $params[':filtro_data_de'] = $filtros['data_de'];
    }
    
    if (!empty($filtros['data_ate']) && is_string($filtros['data_ate'])) {
        $where_filtros_arr[] = "a.data_inicio_planejada <= :filtro_data_ate";
        $params[':filtro_data_ate'] = $filtros['data_ate'];
    }

    // Montar WHERE completo
    $sql_where_completa = $where_base;
    if (!empty($where_filtros_arr)) {
        $sql_where_completa .= " AND " . implode(' AND ', $where_filtros_arr);
    }

    try {
        // Contagem total
        $sql_count = "
            SELECT COUNT(DISTINCT a.id)
            FROM auditorias a
            LEFT JOIN auditoria_secao_responsaveis asr ON asr.auditoria_id = a.id 
                AND asr.auditor_designado_id = :param_auditor_id
            WHERE {$sql_where_completa}
        ";
        
        $stmt_count = $conexao->prepare($sql_count);
        $stmt_count->execute($params);
        $total_itens = (int)$stmt_count->fetchColumn();

        $resultado['paginacao']['total_itens'] = $total_itens;
        $resultado['paginacao']['total_paginas'] = ($total_itens > 0 && $por_pagina > 0) 
            ? ceil($total_itens / $por_pagina) 
            : 1;

        if ($total_itens === 0) {
            return $resultado;
        }

        // Consulta principal
        $sql_select = "
            SELECT
                a.id,
                a.titulo,
                a.status,
                a.data_inicio_planejada,
                a.data_fim_planejada,
                a.auditor_responsavel_id,
                a.equipe_id,
                u_gestor.nome AS nome_gestor,
                
                -- Total de itens atribuídos ao auditor
                COALESCE((
                    SELECT COUNT(ai.id)
                    FROM auditoria_itens ai
                    WHERE ai.auditoria_id = a.id
                    AND (
                        (a.auditor_responsavel_id = :param_auditor_id_calc1 AND a.equipe_id IS NULL)
                        OR
                        (a.equipe_id IS NOT NULL AND ai.secao_item IN (
                            SELECT asr_calc.secao_modelo_nome
                            FROM auditoria_secao_responsaveis asr_calc
                            WHERE asr_calc.auditoria_id = a.id 
                            AND asr_calc.auditor_designado_id = :param_auditor_id_calc2
                        ))
                    )
                ), 0) AS total_itens_auditor,
                
                -- Itens respondidos pelo auditor
                COALESCE((
                    SELECT COUNT(ai_resp.id)
                    FROM auditoria_itens ai_resp
                    WHERE ai_resp.auditoria_id = a.id
                    AND ai_resp.respondido_por_auditor_id = :param_auditor_id_calc_resp
                    AND ai_resp.status_conformidade <> 'Pendente'
                ), 0) AS itens_respondidos_auditor,
                
                -- Seções atribuídas ao auditor
                COALESCE((
                    SELECT GROUP_CONCAT(DISTINCT asr_sec.secao_modelo_nome ORDER BY asr_sec.secao_modelo_nome SEPARATOR '|||')
                    FROM auditoria_secao_responsaveis asr_sec
                    WHERE asr_sec.auditoria_id = a.id 
                    AND asr_sec.auditor_designado_id = :param_auditor_id_calc_sec
                ), '') AS secoes_atribuidas_auditor
                
            FROM auditorias a
            LEFT JOIN usuarios u_gestor ON a.gestor_responsavel_id = u_gestor.id
            WHERE {$sql_where_completa}
            GROUP BY a.id
            ORDER BY
                CASE a.status
                    WHEN 'Planejada' THEN 1
                    WHEN 'Em Andamento' THEN 2
                    WHEN 'Rejeitada' THEN 3
                    WHEN 'Aguardando Correção Auditor' THEN 4
                    WHEN 'Concluída (Auditor)' THEN 5
                    WHEN 'Em Revisão' THEN 6
                    WHEN 'Aprovada' THEN 7
                    WHEN 'Cancelada' THEN 8
                    WHEN 'Pausada' THEN 9
                    ELSE 10
                END ASC,
                a.data_inicio_planejada ASC,
                a.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $conexao->prepare($sql_select);

        // Parâmetros com paginação
        $params_com_pag = array_merge($params, [
            ':param_auditor_id_calc1' => $auditor_id,
            ':param_auditor_id_calc2' => $auditor_id,
            ':param_auditor_id_calc_resp' => $auditor_id,
            ':param_auditor_id_calc_sec' => $auditor_id,
            ':limit' => $por_pagina,
            ':offset' => $offset
        ]);

        // Binding seguro
        foreach ($params_com_pag as $key => $val) {
            $pdoType = PDO::PARAM_STR;
            if (is_int($val) || strpos($key, '_id') !== false || in_array($key, [':limit', ':offset'])) {
                $pdoType = PDO::PARAM_INT;
            } elseif (is_null($val)) {
                $pdoType = PDO::PARAM_NULL;
            }
            $stmt->bindValue($key, $val, $pdoType);
        }

        $stmt->execute();
        $resultado['auditorias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Erro DB getMinhasAuditoriasAuditor (Auditor $auditor_id): " . $e->getMessage() . " | SQL: " . ($sql_select ?? $sql_count));
        if (function_exists('dbRegistrarLogAcesso')) {
            dbRegistrarLogAcesso($auditor_id, $_SERVER['REMOTE_ADDR'], 'get_minhas_auditorias_auditor_err_db', 0, 'Erro DB: ' . $e->getCode(), $conexao);
        }
        return $resultado; // Retorna resultado vazio em caso de erro
    }

    return $resultado;
}

/**
 * Busca dados agregados para o dashboard do auditor.
 *
 * @param PDO $conexao
 * @param int $auditor_id
 * @param int $empresa_id
 * @return array Contendo contagens e listas resumidas.
 */
function getAuditorDashboardStats(PDO $conexao, int $auditor_id, int $empresa_id): array {
    $stats = [
        'pendentes_iniciar' => 0,          // Planejadas atribuídas a ele
        'em_andamento' => 0,              // Em andamento atribuídas a ele
        'aguardando_revisao_gestor' => 0, // Concluídas por ele
        'solicitado_correcao' => 0,      // Status 'Rejeitada' ou um status customizado 'Correcao Solicitada'
        'planos_acao_pendentes' => 0,   // Planos de ação (da empresa toda ou atribuídos a ele?) - Simplificado: todos abertos na empresa.
        'auditorias_para_agir_lista' => [], // Lista resumida das próximas/pendentes
        'notificacoes' => [],            // Lista de alertas (prazos, correções)
        'recentes_concluidas_lista' => [], // Últimas concluídas por ele ou com decisão do gestor
    ];

    // Subquery para identificar auditorias do auditor (individual ou seção)
    $sql_auditorias_do_auditor = "SELECT DISTINCT a.id
                                  FROM auditorias a
                                  LEFT JOIN auditoria_secao_responsaveis asr ON a.id = asr.auditoria_id
                                  WHERE a.empresa_id = :empresa_id
                                  AND (a.auditor_responsavel_id = :auditor_id OR asr.auditor_designado_id = :auditor_id)";

    try {
        // 1. Contar auditorias por status relevantes PARA O AUDITOR
        $sqlStatus = "SELECT a.status, COUNT(DISTINCT a.id) as total
                      FROM auditorias a
                      WHERE a.id IN ({$sql_auditorias_do_auditor}) -- Filtra pelas auditorias DELE
                      GROUP BY a.status";
        $stmtStatus = $conexao->prepare($sqlStatus);
        $stmtStatus->execute([':empresa_id' => $empresa_id, ':auditor_id' => $auditor_id]);
        $statusCounts = $stmtStatus->fetchAll(PDO::FETCH_KEY_PAIR); // [Status => Count]

        $stats['pendentes_iniciar'] = (int)($statusCounts['Planejada'] ?? 0);
        $stats['em_andamento'] = (int)($statusCounts['Em Andamento'] ?? 0);
        $stats['aguardando_revisao_gestor'] = (int)($statusCounts['Concluída (Auditor)'] ?? 0);
        // Status que podem indicar necessidade de correção
        $stats['solicitado_correcao'] = (int)($statusCounts['Rejeitada'] ?? 0) + (int)($statusCounts['Aguardando Correção Auditor'] ?? 0); // Adicionar status custom se usar


        // 2. Contar Planos de Ação Abertos (Simplificado: da empresa, ou filtrar por itens de auditorias dele)
        $sqlPA = "SELECT COUNT(DISTINCT pa.id)
                  FROM auditoria_planos_acao pa
                  JOIN auditoria_itens ai ON pa.auditoria_item_id = ai.id
                  JOIN auditorias a ON ai.auditoria_id = a.id
                  WHERE a.empresa_id = :empresa_id
                  AND pa.status_acao IN ('Pendente', 'Em Andamento', 'Atrasada')"; // Status em aberto
                  // Para filtrar PA só de auditorias do auditor: AND a.id IN ($sql_auditorias_do_auditor) -> Adicionar params de novo
        $stmtPA = $conexao->prepare($sqlPA);
        $stmtPA->execute([':empresa_id' => $empresa_id]); // , ':auditor_id' => $auditor_id se filtrar auditorias dele
        $stats['planos_acao_pendentes'] = (int) $stmtPA->fetchColumn();


        // 3. Buscar Lista de Auditorias para Agir (Planejadas ou Em Andamento - Limitar)
        $sqlParaAgir = "SELECT a.id, a.titulo, a.status, a.data_inicio_planejada, a.data_fim_planejada,
                              a.auditor_responsavel_id -- Para saber se é individual
                        FROM auditorias a
                        WHERE a.id IN ({$sql_auditorias_do_auditor})
                        AND a.status IN ('Planejada', 'Em Andamento')
                        ORDER BY
                          CASE a.status
                            WHEN 'Em Andamento' THEN 1
                            WHEN 'Planejada' THEN 2
                            ELSE 3
                          END,
                          a.data_inicio_planejada ASC, a.data_fim_planejada ASC
                        LIMIT 5"; // Limita para dashboard
        $stmtParaAgir = $conexao->prepare($sqlParaAgir);
        $stmtParaAgir->execute([':empresa_id' => $empresa_id, ':auditor_id' => $auditor_id]);
        $stats['auditorias_para_agir_lista'] = $stmtParaAgir->fetchAll(PDO::FETCH_ASSOC);

        // 4. Buscar Notificações/Alertas (Ex: Prazos e Correções)
        $notificacoes = [];
        // a) Prazos Próximos (Ex: Próximos 7 dias)
        $data_limite_prazo = date('Y-m-d', strtotime('+7 days'));
        $sqlPrazos = "SELECT id, titulo, data_fim_planejada
                      FROM auditorias a
                      WHERE a.id IN ({$sql_auditorias_do_auditor})
                      AND a.status IN ('Planejada', 'Em Andamento', 'Pausada') -- Status ativos antes da conclusão
                      AND a.data_fim_planejada IS NOT NULL AND a.data_fim_planejada <= :data_limite";
        $stmtPrazos = $conexao->prepare($sqlPrazos);
        $stmtPrazos->execute([':empresa_id' => $empresa_id, ':auditor_id' => $auditor_id, ':data_limite' => $data_limite_prazo]);
        while ($row = $stmtPrazos->fetch(PDO::FETCH_ASSOC)) {
            $notificacoes[] = [
                'tipo' => 'prazo',
                'auditoria_id' => $row['id'],
                'titulo' => $row['titulo'],
                'data' => $row['data_fim_planejada'],
                'mensagem' => "Prazo final próximo: " . formatarDataSimples($row['data_fim_planejada'])
            ];
        }
         // b) Prazos Vencidos
         $hoje = date('Y-m-d');
         $sqlVencidos = "SELECT id, titulo, data_fim_planejada
                         FROM auditorias a
                         WHERE a.id IN ({$sql_auditorias_do_auditor})
                         AND a.status IN ('Planejada', 'Em Andamento', 'Pausada')
                         AND a.data_fim_planejada IS NOT NULL AND a.data_fim_planejada < :hoje";
         $stmtVencidos = $conexao->prepare($sqlVencidos);
         $stmtVencidos->execute([':empresa_id' => $empresa_id, ':auditor_id' => $auditor_id, ':hoje' => $hoje]);
          while ($row = $stmtVencidos->fetch(PDO::FETCH_ASSOC)) {
             $notificacoes[] = [
                 'tipo' => 'vencido',
                 'auditoria_id' => $row['id'],
                 'titulo' => $row['titulo'],
                 'data' => $row['data_fim_planejada'],
                 'mensagem' => "Prazo final VENCIDO em " . formatarDataSimples($row['data_fim_planejada'])
             ];
         }

        // c) Correções Solicitadas (Auditorias Rejeitadas ou com status customizado)
        $sqlCorrecoes = "SELECT id, titulo, data_aprovacao_rejeicao_gestor as data_rejeicao, observacoes_gerais_gestor
                         FROM auditorias a
                         WHERE a.id IN ({$sql_auditorias_do_auditor})
                         AND a.status IN ('Rejeitada', 'Aguardando Correção Auditor') -- Adicionar status se usar
                         ORDER BY data_aprovacao_rejeicao_gestor DESC";
        $stmtCorrecoes = $conexao->prepare($sqlCorrecoes);
        $stmtCorrecoes->execute([':empresa_id' => $empresa_id, ':auditor_id' => $auditor_id]);
         while ($row = $stmtCorrecoes->fetch(PDO::FETCH_ASSOC)) {
            $notificacoes[] = [
                'tipo' => 'correcao',
                'auditoria_id' => $row['id'],
                'titulo' => $row['titulo'],
                'data' => $row['data_rejeicao'],
                'mensagem' => "Correções solicitadas pelo gestor em " . formatarDataCompleta($row['data_rejeicao']),
                'detalhes' => $row['observacoes_gerais_gestor'] // Para mostrar em tooltip ou ao clicar
            ];
        }
        // Ordenar notificações (ex: mais recentes primeiro ou por tipo de urgência)
        usort($notificacoes, function($a, $b) {
             $prioridade = ['vencido' => 1, 'correcao' => 2, 'prazo' => 3];
             $prioA = $prioridade[$a['tipo']] ?? 99;
             $prioB = $prioridade[$b['tipo']] ?? 99;
             if ($prioA !== $prioB) return $prioA <=> $prioB;
             // Se a prioridade for a mesma, ordena pela data (mais recente primeiro)
             return ($b['data'] ?? '') <=> ($a['data'] ?? '');
        });
        $stats['notificacoes'] = array_slice($notificacoes, 0, 10); // Limitar notificações exibidas

        // 5. Últimas Concluídas (Por ele ou com decisão do gestor)
         $sqlRecentes = "SELECT a.id, a.titulo, a.status, a.data_modificacao,
                               GREATEST(IFNULL(a.data_conclusao_auditor, '0000-00-00'), IFNULL(a.data_aprovacao_rejeicao_gestor, '0000-00-00')) as data_evento_recente
                        FROM auditorias a
                        WHERE a.id IN ({$sql_auditorias_do_auditor})
                        AND a.status IN ('Concluída (Auditor)', 'Aprovada', 'Rejeitada')
                        ORDER BY data_evento_recente DESC, a.data_modificacao DESC
                        LIMIT 5";
         $stmtRecentes = $conexao->prepare($sqlRecentes);
         $stmtRecentes->execute([':empresa_id' => $empresa_id, ':auditor_id' => $auditor_id]);
         $stats['recentes_concluidas_lista'] = $stmtRecentes->fetchAll(PDO::FETCH_ASSOC);


    } catch (PDOException $e) {
        error_log("Erro getAuditorDashboardStats (Auditor: $auditor_id, Empresa: $empresa_id): " . $e->getMessage());
        // Retornar array vazio ou com defaults em caso de erro
    }

    return $stats;
}


// (Manter/Criar função formatarDataSimples e formatarDataCompleta, se não estiverem em config.php)

?>