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
            WHERE 1=1"; //Truque para facilitar a adição de condições

    $params = [];

    // Filtros
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
    if ($status !== '') { // Note o uso de !== '' em vez de !empty()
        $sql .= " AND la.sucesso = ?";
        $params[] = (int)$status; // Força a conversão para inteiro (0 ou 1)
    }

     // Adiciona a cláusula WHERE para a pesquisa, caso haja termo
    if (!empty($search)) {
        $sql .= " AND (u.nome LIKE ? OR u.email LIKE ? OR la.acao LIKE ? OR la.detalhes LIKE ? OR la.ip_address LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $sql .= " ORDER BY la.data_hora DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $itens_por_pagina;
    $stmt = $conexao->prepare($sql);

    // Debug: Exibir a query e os parâmetros (descomente para depurar)
    // echo "SQL: " . $sql . "<br>";
    // echo "Params: " . print_r($params, true) . "<br>";
    // exit;

    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contagem total de logs (para paginação, SEM o LIMIT/OFFSET)
    $sql_count = "SELECT COUNT(*) FROM logs_acesso la LEFT JOIN usuarios u ON la.usuario_id = u.id WHERE 1=1"; // Mesmas condições WHERE da query principal
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

    // Adiciona a cláusula WHERE para a pesquisa, caso haja termo (para contagem total)
    if (!empty($search)) {
    $sql_count .= " AND (u.nome LIKE ? OR u.email LIKE ? OR la.acao LIKE ? OR la.detalhes LIKE ? OR la.ip_address LIKE ?)";
    $searchParam = "%$search%";
    $params_count[] = $searchParam;
    $params_count[] = $searchParam;
    $params_count[] = $searchParam;
    $params_count[] = $searchParam;
    $params_count[] = $searchParam;
    }

    $stmt_count = $conexao->prepare($sql_count);
    $stmt_count->execute($params_count);
    $total_logs = $stmt_count->fetchColumn();

    // Calcula o número total de páginas
    $total_paginas = ceil($total_logs / $itens_por_pagina);

    return [
        'logs' => $logs,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_logs' => $total_logs, // Usado no plural
        ]
    ];
}

function getTodosUsuarios($conexao) {
    $sql = "SELECT id, nome, email FROM usuarios ORDER BY nome"; // Consulta simples
    $stmt = $conexao->query($sql); // Sem parâmetros, query() é suficiente
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEmpresas($conexao, $pagina = 1, $itens_por_pagina = 10, $busca = '') {
    $offset = ($pagina - 1) * $itens_por_pagina;

    $sql = "SELECT id, nome, cnpj, razao_social, data_cadastro
            FROM empresas
            WHERE 1=1";  //Truque para facilitar.
    $params = [];

    // Adiciona a busca, se houver um termo de busca
    if (!empty($busca)) {
        $sql .= " AND (nome LIKE ? OR cnpj LIKE ? OR razao_social LIKE ?)";
        $params[] = "%$busca%"; // % antes e depois para buscar em qualquer lugar do texto
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }

    $sql .= " ORDER BY nome LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $itens_por_pagina;


    $stmt = $conexao->prepare($sql);
    $stmt->execute($params);
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contagem total de empresas (para paginação, COM busca)
    $sql_count = "SELECT COUNT(*) FROM empresas WHERE 1=1";
    $params_count = [];

    if (!empty($busca)) {
        $sql_count .= " AND (nome LIKE ? OR cnpj LIKE ? OR razao_social LIKE ?)";
        $params_count[] = "%$busca%";
        $params_count[] = "%$busca%";
        $params_count[] = "%$busca%";

    }

    $stmt_count = $conexao->prepare($sql_count);
    $stmt_count->execute($params_count);
    $total_empresas = $stmt_count->fetchColumn();

    // Calcula o número total de páginas
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

function criarEmpresa($conexao, $dados) {
    $sql = "INSERT INTO empresas (nome, cnpj, razao_social, endereco, contato, telefone, email)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conexao->prepare($sql);

    try {
        $stmt->execute([
            $dados['nome'],
            $dados['cnpj'],
            $dados['razao_social'],
            $dados['endereco'],
            $dados['contato'],
            $dados['telefone'],
            $dados['email']
        ]);
        return true; // Sucesso

    } catch (PDOException $e) {
        // Tratar erros de SQL (duplicidade de CNPJ, etc.)
       if ($e->getCode() == 23000) { // 23000 é o código genérico para violação de constraint (unique, etc.)
            preg_match("/Duplicate entry '(.*)' for key '(.*)'/", $e->getMessage(), $matches);
            if(!empty($matches)){
                $valorDuplicado = $matches[1];
                $nomeCampo = $matches[2];
                //Tratamento para exibir erros mais amigaveis
                if($nomeCampo == 'cnpj'){
                    $campo = 'CNPJ';

                }
                return "Erro: Já existe uma empresa com este $campo cadastrado";
            }
        }

        error_log("Erro ao criar empresa: " . $e->getMessage()); // Log completo do erro
        return "Erro inesperado ao criar a empresa.  Tente novamente."; // Mensagem genérica para o usuário
    }
}

function validarCNPJ($cnpj) {
    // Remove caracteres não numéricos
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

    // Verifica se o CNPJ tem 14 dígitos
    if (strlen($cnpj) != 14) {
        return false;
    }

    // Verifica se todos os dígitos são iguais (ex: 11.111.111/1111-11), o que é inválido
    if (preg_match('/^(\d)\1+$/', $cnpj)) {
        return false;
    }

    // Validação do CNPJ
    $soma = 0;
    $multiplicador = 5;
    for ($i = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $multiplicador;
        $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
    }
    $resto = $soma % 11;
    $digitoVerificador1 = ($resto < 2) ? 0 : 11 - $resto;

    $soma = 0;
    $multiplicador = 6;
    for ($i = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $multiplicador;
        $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
    }
    $resto = $soma % 11;
    $digitoVerificador2 = ($resto < 2) ? 0 : 11 - $resto;

    return $cnpj[12] == $digitoVerificador1 && $cnpj[13] == $digitoVerificador2;
}

?>