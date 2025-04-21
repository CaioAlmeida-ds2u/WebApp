<?php
// includes/gestor_functions.php
// Funções específicas para o perfil Gestor
/**
 * Busca contagens relevantes para o dashboard do Gestor
 */
function getGestorDashboardStats(PDO $conexao, int $empresa_id): array {
    $stats = [
        'em_andamento' => 0,
        'para_revisao' => 0,
        'nao_conformidades_abertas' => 0, // Mais complexo, depende da estrutura de itens/respostas
        'auditores_ativos' => 0
    ];

    try {
        // Auditorias em Andamento ou Planejada (da empresa)
        $stmtAndamento = $conexao->prepare(
            "SELECT COUNT(*) FROM auditorias WHERE empresa_id = :eid AND status IN ('Planejada', 'Em Andamento')"
        );
        $stmtAndamento->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmtAndamento->execute();
        $stats['em_andamento'] = (int) $stmtAndamento->fetchColumn();

        // Auditorias Pendentes de Revisão (Concluída pelo Auditor)
        $stmtRevisao = $conexao->prepare(
            "SELECT COUNT(*) FROM auditorias WHERE empresa_id = :eid AND status = 'Concluída (Auditor)'"
        );
         $stmtRevisao->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
         $stmtRevisao->execute();
         $stats['para_revisao'] = (int) $stmtRevisao->fetchColumn();

        // Não Conformidades Abertas (Exemplo - PRECISA ADAPTAR à sua estrutura real)
        // Supõe que 'auditoria_itens' tem status_conformidade e link para auditoria
        /*
        $stmtNC = $conexao->prepare(
            "SELECT COUNT(ai.id)
             FROM auditoria_itens ai
             JOIN auditorias a ON ai.auditoria_id = a.id
             WHERE a.empresa_id = :eid
               AND ai.status_conformidade = 'Não Conforme'
               AND a.status NOT IN ('Aprovada', 'Rejeitada') -- Apenas de auditorias não finalizadas? Ou todas?
               -- Adicionar filtro para NCs que ainda não tem plano de ação, se aplicável
            ");
         $stmtNC->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
         $stmtNC->execute();
         $stats['nao_conformidades_abertas'] = (int) $stmtNC->fetchColumn();
         */
         // Placeholder por enquanto:
         $stats['nao_conformidades_abertas'] = 0;


        // Auditores Ativos da Empresa
        $stmtAuditores = $conexao->prepare(
            "SELECT COUNT(*) FROM usuarios WHERE empresa_id = :eid AND perfil = 'auditor' AND ativo = 1"
        );
        $stmtAuditores->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmtAuditores->execute();
        $stats['auditores_ativos'] = (int) $stmtAuditores->fetchColumn();

    } catch (PDOException $e) {
        error_log("Erro em getGestorDashboardStats para Empresa ID $empresa_id: " . $e->getMessage());
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
            WHERE a.empresa_id = :eid
              AND a.status = 'Concluída (Auditor)'
            ORDER BY a.data_conclusao_auditor DESC
            LIMIT :limit";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro em getAuditoriasParaRevisar para Empresa ID $empresa_id: " . $e->getMessage());
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
        error_log("Erro em getAuditoriasRecentesEmpresa para Empresa ID $empresa_id: " . $e->getMessage());
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
     if ($limit > 0) {
         $sql .= " LIMIT :limit";
     }
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        if ($limit > 0) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro em getAuditoresDaEmpresa para Empresa ID $empresa_id: " . $e->getMessage());
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
            ORDER BY FIELD(status, 'Planejada', 'Em Andamento', 'Pausada', 'Concluída (Auditor)', 'Em Revisão', 'Aprovada', 'Rejeitada', 'Cancelada')"; // Ordena por fluxo lógico

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
        error_log("Erro getAuditoriaStatusChartData: " . $e->getMessage());
    }
    return $chartData;
}

/**
 * Busca dados para o gráfico de conformidade (simplificado) das auditorias APROVADAS da empresa.
 */
function getConformidadeChartData(PDO $conexao, int $empresa_id): array {
    $conformidade = ['Conforme' => 0, 'Não Conforme' => 0, 'Parcial' => 0, 'N/A' => 0];
    $sql = "SELECT ai.status_conformidade, COUNT(ai.id) as total
            FROM auditoria_itens ai
            JOIN auditorias a ON ai.auditoria_id = a.id
            WHERE a.empresa_id = :eid
              AND a.status = 'Aprovada' -- Considera apenas auditorias aprovadas pelo gestor
              AND ai.status_conformidade IN ('Conforme', 'Não Conforme', 'Parcial', 'N/A') -- Ignora 'Pendente'
            GROUP BY ai.status_conformidade";

    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            switch ($row['status_conformidade']) {
                case 'Conforme': $conformidade['Conforme'] = (int)$row['total']; break;
                case 'Não Conforme': $conformidade['Não Conforme'] = (int)$row['total']; break;
                case 'Parcial': $conformidade['Parcial'] = (int)$row['total']; break; // Renomeado de Parcialmente Conforme para caber
                case 'N/A': $conformidade['N/A'] = (int)$row['total']; break; // Renomeado de Não Aplicável
            }
        }
    } catch (PDOException $e) {
        error_log("Erro getConformidadeChartData: " . $e->getMessage());
    }
    return $conformidade;
}

?>