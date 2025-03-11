<?php
function getLogsAcesso($conexao, $pagina = 1, $itens_por_pagina = 20, $data_inicio = '', $data_fim = '', $usuario_id = '', $acao = '', $status = '', $search = '') {
    $offset = ($pagina - 1) * $itens_por_pagina;

    $sql = "SELECT
                la.id,
                la.data_hora,
                la.ip_address,
                la.acao,
                la.sucesso,
                la.detalhes,
                COALESCE(u.nome, 'Usuário Desconhecido') AS nome_usuario,
                COALESCE(u.email, 'N/A') AS email_usuario
            FROM logs_acesso la
            LEFT JOIN usuarios u ON la.usuario_id = u.id
            WHERE 1=1";

    $params = [];

    if ($data_inicio) {
        $sql .= " AND DATE(la.data_hora) >= ?";
        $params[] = $data_inicio;
    }
    if ($data_fim) {
        $sql .= " AND DATE(la.data_hora) <= ?";
        $params[] = $data_fim;
    }
    if ($usuario_id) {
        $sql .= " AND la.usuario_id = ?";
        $params[] = $usuario_id;
    }
    if ($acao) {
        $sql .= " AND la.acao = ?";
        $params[] = $acao;
    }
    if ($status !== '') {
        $sql .= " AND la.sucesso = ?";
        $params[] = (int)$status;
    }
    if (!empty($search)) {
        $sql .= " AND (u.nome LIKE ? OR u.email LIKE ? OR la.acao LIKE ? OR la.detalhes LIKE ? OR la.ip_address LIKE ?)";
        $searchParam = "%$search%";
        array_push($params, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    }

    $sql .= " ORDER BY la.data_hora DESC LIMIT ?, ?";
    $params[] = (int)$offset;
    $params[] = (int)$itens_por_pagina;
    
    $stmt = $conexao->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql_count = "SELECT COUNT(*) FROM logs_acesso la LEFT JOIN usuarios u ON la.usuario_id = u.id WHERE 1=1";
    $params_count = [];

    if ($data_inicio) {
        $sql_count .= " AND DATE(la.data_hora) >= ?";
        $params_count[] = $data_inicio;
    }
    if ($data_fim) {
        $sql_count .= " AND DATE(la.data_hora) <= ?";
        $params_count[] = $data_fim;
    }
    if ($usuario_id) {
        $sql_count .= " AND la.usuario_id = ?";
        $params_count[] = $usuario_id;
    }
    if ($acao) {
        $sql_count .= " AND la.acao = ?";
        $params_count[] = $acao;
    }
    if ($status !== '') {
        $sql_count .= " AND la.sucesso = ?";
        $params_count[] = (int)$status;
    }
    if (!empty($search)) {
        $sql_count .= " AND (u.nome LIKE ? OR u.email LIKE ? OR la.acao LIKE ? OR la.detalhes LIKE ? OR la.ip_address LIKE ?)";
        $searchParam = "%$search%";
        array_push($params_count, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    }

    $stmt_count = $conexao->prepare($sql_count);
    $stmt_count->execute($params_count);
    $total_logs = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_logs / $itens_por_pagina);

    return [
        'logs' => $logs,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_logs' => $total_logs,
        ]
    ];
}


function getTodosUsuarios($conexao) {
    $sql = "SELECT id, nome, email FROM usuarios ORDER BY nome";
    $stmt = $conexao->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEmpresas($conexao, $pagina = 1, $itens_por_pagina = 10, $busca = '') {
    $offset = ($pagina - 1) * $itens_por_pagina;
    $sql = "SELECT id, nome, cnpj, razao_social, data_cadastro FROM empresas WHERE 1=1";
    $params = [];

    if (!empty($busca)) {
        $sql .= " AND (nome LIKE ? OR cnpj LIKE ? OR razao_social LIKE ?)";
        $searchParam = "%$busca%";
        array_push($params, $searchParam, $searchParam, $searchParam);
    }

    $sql .= " ORDER BY nome LIMIT ?, ?";
    $params[] = (int)$offset;
    $params[] = (int)$itens_por_pagina;
    
    $stmt = $conexao->prepare($sql);
    $stmt->execute($params);
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql_count = "SELECT COUNT(*) FROM empresas WHERE 1=1";
    $params_count = [];

    if (!empty($busca)) {
        $sql_count .= " AND (nome LIKE ? OR cnpj LIKE ? OR razao_social LIKE ?)";
        array_push($params_count, $searchParam, $searchParam, $searchParam);
    }

    $stmt_count = $conexao->prepare($sql_count);
    $stmt_count->execute($params_count);
    $total_empresas = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_empresas / $itens_por_pagina);

    return [
        'empresas' => $empresas,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_empresas' => $total_empresas,
        ]
    ];
}
?>
