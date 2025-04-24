<?php
// includes/admin_functions.php

// --- Funções para Gestão de Usuários ---

/**
 * Obtém a lista de usuários do banco de dados.
 */
function getUsuarios($conexao, $excluir_admin_id = null, $pagina = 1, $itens_por_pagina = 10) {
    $offset = ($pagina - 1) * $itens_por_pagina;

    $sql = "SELECT id, nome, email, perfil, ativo, data_cadastro FROM usuarios";
    $params = [];

    if ($excluir_admin_id !== null) {
        $sql .= " WHERE id != ?";
        $params[] = $excluir_admin_id;
    }

    $sql .= " ORDER BY nome LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $itens_por_pagina;

    $stmt = $conexao->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contagem total de usuários (para paginação)
    $sql_count = "SELECT COUNT(*) FROM usuarios";
    $params_count = [];
    if ($excluir_admin_id !== null) {
        $sql_count .= " WHERE id != ?";
        $params_count[] = $excluir_admin_id;
    }
    $stmt_count = $conexao->prepare($sql_count);
    $stmt_count->execute($params_count);
    $total_usuarios = $stmt_count->fetchColumn();

     // Calcula o número total de páginas
    $total_paginas = ceil($total_usuarios / $itens_por_pagina);

    return [
        'usuarios' => $usuarios,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_usuarios' => $total_usuarios,
        ]
    ];
}

/**
 * Obtém os dados de um único usuário pelo ID.
 */
function getUsuario($conexao, $id) {
    $sql = "SELECT id, nome, email, perfil, ativo, data_cadastro, foto FROM usuarios WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
//Dados do usuario
function dbGetDadosUsuario($conexao,$usuario_id) {
    $stmt = $conexao->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    return $stmt->fetch();
}
/**
 * Atualiza os dados de um usuário no banco de dados.
 */
function atualizarUsuario($conexao, $id, $nome, $email, $perfil, $ativo, $empresa_id) {
    $sql = "UPDATE usuarios SET nome = ?, email = ?, perfil = ?, ativo = ? , empresa_id = ? WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$nome, $email, $perfil, $ativo, $empresa_id, $id]);
    return $stmt->rowCount() > 0;
}

/*
 * Ativa um usuário.
 */
function ativarUsuario($conexao, $usuario_id) {
    $sql = "UPDATE usuarios SET ativo = 1 WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$usuario_id]);
    return $stmt->rowCount() > 0;
}
// --- Funções para Formatação de Dados ---
/**
 * Formata um CPF para o padrão brasileiro.
 */
function formatarCNPJ(string $cnpj): string {
    $cnpjLImpo = preg_replace('/[^0-9]/', '', $cnpj);
    if (strlen($cnpjLImpo) != 14) {
        return $cnpj; // Retorna o original se não tiver 14 dígitos
    }
    // Aplica a máscara
    return vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($cnpjLImpo));
}

// --- Funções para o Dashboard ---
/**
 * Obtém as contagens para o dashboard.
 */
function getDashboardCounts(PDO $conexao): array {
    $counts = [
        'solicitacoes_acesso' => 0,
        'solicitacoes_reset' => 0,
        'usuarios_ativos' => 0,
        'total_empresas' => 0,
    ];

    try {
        // Contar solicitações de acesso pendentes
        $stmtAcesso = $conexao->query("SELECT COUNT(*) FROM solicitacoes_acesso WHERE status = 'pendente'");
        $counts['solicitacoes_acesso'] = (int) $stmtAcesso->fetchColumn();

        // Contar solicitações de reset pendentes
        $stmtReset = $conexao->query("SELECT COUNT(*) FROM solicitacoes_reset_senha WHERE status = 'pendente'");
        $counts['solicitacoes_reset'] = (int) $stmtReset->fetchColumn();

        // Contar usuários ativos
        $stmtUsuarios = $conexao->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1");
        $counts['usuarios_ativos'] = (int) $stmtUsuarios->fetchColumn();

        // Contar total de empresas
        $stmtEmpresas = $conexao->query("SELECT COUNT(*) FROM empresas");
        $counts['total_empresas'] = (int) $stmtEmpresas->fetchColumn();

    } catch (PDOException $e) {
        error_log("Erro ao buscar contagens do dashboard: " . $e->getMessage());
        // Retorna contagens como 0 em caso de erro, mas loga o problema.
    }

    return $counts;
}

/**
 * Desativa um usuário.
 */
function desativarUsuario($conexao, $usuario_id) {
    $sql = "UPDATE usuarios SET ativo = 0 WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$usuario_id]);
    return $stmt->rowCount() > 0;
}

/**
 * Exclui um usuário.
 */
function excluirUsuario($conexao, $usuario_id) {
    // Verifica se o usuário a ser excluído é o mesmo que está logado
    if ($usuario_id == $_SESSION['usuario_id']) {
        return false;  // Impede a autoexclusão
    }
    $sql = "DELETE FROM usuarios WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$usuario_id]);
    return $stmt->rowCount() > 0;
}

// --- Funções para Solicitações de Acesso ---

function getSolicitacoesAcessoPendentes($conexao) {
    $sql = "SELECT sa.id, sa.nome_completo, sa.email, sa.motivo, sa.data_solicitacao, e.nome as empresa_nome
            FROM solicitacoes_acesso sa
            INNER JOIN empresas e ON sa.empresa_id = e.id
            WHERE sa.status = 'pendente'
            ORDER BY sa.data_solicitacao";

    $stmt = $conexao->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obter os dados da solicitação por ID.

function getSolicitacaoAcesso($conexao, $solicitacao_id){
    $sql = "SELECT * FROM solicitacoes_acesso WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$solicitacao_id]);

    // Correção: Retornar diretamente o primeiro resultado (ou null se não houver)
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($result)) {
        return null; // Ou tratar o erro de outra forma, se preferir
    }

    return $result[0];


    // OU, usar fetch, já que só esperamos um resultado:
    // return $stmt->fetch(PDO::FETCH_ASSOC); // Forma MAIS SIMPLES (recomendada neste caso)
}
function getSolicitacaoReset($conexao, $solicitacao_id){
    $sql = "SELECT * FROM solicitacoes_reset_senha WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$solicitacao_id]);

    // Correção: Retornar diretamente o primeiro resultado (ou null se não houver)
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($result)) {
        return null; // Ou tratar o erro de outra forma, se preferir
    }

    return $result[0];


    // OU, usar fetch, já que só esperamos um resultado:
    // return $stmt->fetch(PDO::FETCH_ASSOC); // Forma MAIS SIMPLES (recomendada neste caso)
}

function aprovarSolicitacaoAcesso($conexao, $solicitacao_id, $senha_temporaria) {
    $conexao->beginTransaction(); // Inicia uma transação

    try {
        // 1. Obter dados da solicitação
        $solicitacao = getSolicitacaoAcesso($conexao, $solicitacao_id); //Função criada acima.
        if (!$solicitacao) {
            throw new Exception("Solicitação de acesso não encontrada (ID: $solicitacao_id).");
        }

        // 2. Criar o usuário
        $senha_hash = password_hash($senha_temporaria, PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuarios (nome, email, senha, perfil, ativo, empresa_id) VALUES (?, ?, ?, 'auditor', 1, ?)"; // Perfil auditor
        $stmt = $conexao->prepare($sql);
        $stmt->execute([$solicitacao['nome_completo'], $solicitacao['email'], $senha_hash, $solicitacao['empresa_id']]);
        $novo_usuario_id = $conexao->lastInsertId(); // Obtém o ID do novo usuário

        // 3. Atualizar o status da solicitação
        $sql = "UPDATE solicitacoes_acesso SET status = 'aprovada', admin_id = ?, data_aprovacao = NOW() WHERE id = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([$_SESSION['usuario_id'], $solicitacao_id]);

        // 4. (Opcional) Enviar e-mail simulado (log ou tabela) - Implementar depois

        $conexao->commit(); // Confirma a transação
        return true;

    } catch (Exception $e) {
        $conexao->rollBack(); // Desfaz a transação em caso de erro
        error_log("Erro ao aprovar solicitação de acesso (ID: $solicitacao_id): " . $e->getMessage());
        return false;
    }
}

function aprovarESetarSenhaTemp(PDO $conexao, int $solicitacao_id, string $senha_temporaria, int $admin_id): bool{
    try {
        // Inicia uma transação para garantir atomicidade
        $conexao->beginTransaction();

        // Obter o ID do usuário associado à solicitação
        $queryUsuario = "SELECT usuario_id FROM solicitacoes_reset_senha WHERE id = :solicitacao_id AND status = 'pendente'";
        $stmtUsuario = $conexao->prepare($queryUsuario);
        $stmtUsuario->bindParam(':solicitacao_id', $solicitacao_id, PDO::PARAM_INT);
        $stmtUsuario->execute();
        $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            throw new Exception("Solicitação de reset inválida ou já processada.");
        }

        $usuario_id = $usuario['usuario_id'];

        // Atualizar a senha do usuário e marcar como "primeiro acesso"
        $senha_hash = password_hash($senha_temporaria, PASSWORD_DEFAULT);
        $queryAtualizarSenha = "UPDATE usuarios SET senha = :senha, primeiro_acesso = 1 WHERE id = :usuario_id";
        $stmtAtualizarSenha = $conexao->prepare($queryAtualizarSenha);
        $stmtAtualizarSenha->bindParam(':senha', $senha_hash, PDO::PARAM_STR);
        $stmtAtualizarSenha->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        if (!$stmtAtualizarSenha->execute()) {
            throw new Exception("Erro ao atualizar a senha do usuário.");
        }

        // Atualizar o status da solicitação de reset para "aprovada"
        $queryAprovarSolicitacao = "UPDATE solicitacoes_reset_senha SET status = 'aprovada', admin_id = :admin_id, data_aprovacao = NOW() WHERE id = :solicitacao_id";
        $stmtAprovarSolicitacao = $conexao->prepare($queryAprovarSolicitacao);
        $stmtAprovarSolicitacao->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
        $stmtAprovarSolicitacao->bindParam(':solicitacao_id', $solicitacao_id, PDO::PARAM_INT);
        if (!$stmtAprovarSolicitacao->execute()) {
            throw new Exception("Erro ao aprovar a solicitação de reset.");
        }

        // Commit da transação
        $conexao->commit();
        return true;
    } catch (Exception $e) {
        // Rollback em caso de erro
        $conexao->rollBack();
        error_log("Erro em aprovarESetarSenhaTemp: " . $e->getMessage());
        return false;
    }
}


function rejeitarSolicitacaoAcesso($conexao, $solicitacao_id, $observacoes = '') {
   $sql = "UPDATE solicitacoes_acesso SET status = 'rejeitada', admin_id = ?, data_aprovacao = NOW(), observacoes = ? WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$_SESSION['usuario_id'], $observacoes, $solicitacao_id]);
     // (Opcional) Enviar e-mail simulado - Implementar depois
    return $stmt->rowCount() > 0;
}

// --- Funções para Solicitações de Reset de Senha ---

function getSolicitacoesResetPendentes($conexao) {
    $sql = "SELECT sr.id, sr.data_solicitacao, u.nome AS nome_usuario, u.email
            FROM solicitacoes_reset_senha sr
            INNER JOIN usuarios u ON sr.usuario_id = u.id
            WHERE sr.status = 'pendente'
            ORDER BY sr.data_solicitacao";
    $stmt = $conexao->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Rejeitar solicitação de reset.
function rejeitarSolicitacaoReset($conexao, $solicitacao_id, $observacoes = '') {
    $sql = "UPDATE solicitacoes_reset_senha SET status = 'rejeitada', admin_id = ?, data_rejeicao = NOW(), observacoes = ? WHERE id = ?";
     $stmt = $conexao->prepare($sql);
     $stmt->execute([$_SESSION['usuario_id'], $observacoes, $solicitacao_id]);
      // (Opcional) Enviar e-mail simulado - Implementar depois
     return $stmt->rowCount() > 0;

 }


function getDadosUsuarioPorSolicitacaoReset($conexao, $solicitacao_id) {
    $sql = "SELECT u.id, u.nome, u.email
            FROM usuarios u
            INNER JOIN solicitacoes_reset_senha s ON u.id = s.usuario_id
            WHERE s.id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$solicitacao_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


function redefinirSenha($conexao, $usuario_id, $nova_senha) {
    $hashed_password = password_hash($nova_senha, PASSWORD_DEFAULT);
    $sql = "UPDATE usuarios SET senha = ?, primeiro_acesso = 1 WHERE id = ?"; // Define primeiro_acesso = 1
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$hashed_password, $usuario_id]);
    return $stmt->rowCount() > 0;
}

function aprovarSolicitacaoReset($conexao, $solicitacao_id, $admin_id) {
     $sql = "UPDATE solicitacoes_reset_senha
            SET status = 'aprovada', admin_id = ?, data_aprovacao = NOW()
            WHERE id = ?";

    $stmt = $conexao->prepare($sql);
    $stmt->execute([$admin_id, $solicitacao_id]);

    return $stmt->rowCount() > 0; // Retorna true se a atualização foi bem-sucedida
}

// --- (Outras funções do admin_functions.php, como as de solicitação, podem vir aqui) ---

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
                COALESCE(u.email, 'N/A') AS email_usuario,
                la.usuario_id
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

function criarEmpresa(PDO $conexao, array $dados, int $admin_id): bool|string {
    $sql = "INSERT INTO empresas (nome, cnpj, razao_social, endereco, contato, telefone, email, logo, criado_por, modificado_por, ativo)
            VALUES (:nome, :cnpj, :razao_social, :endereco, :contato, :telefone, :email, :logo, :criado_por, :modificado_por, 1)"; // Default ativo=1
    $stmt = $conexao->prepare($sql);

    $cnpjLimpo = preg_replace('/[^0-9]/', '', $dados['cnpj']);
     if (!validarCNPJ($cnpjLimpo)) return "CNPJ inválido."; // Valida aqui
     if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) return "E-mail inválido.";

    try {
        $stmt->bindParam(':nome', $dados['nome']);
        $stmt->bindParam(':cnpj', $cnpjLimpo);
        $stmt->bindParam(':razao_social', $dados['razao_social']);
        $stmt->bindValue(':endereco', $dados['endereco'] ?: null);
        $stmt->bindParam(':contato', $dados['contato']);
        $stmt->bindParam(':telefone', $dados['telefone']);
        $stmt->bindParam(':email', $dados['email']);
        $stmt->bindValue(':logo', $dados['logo'] ?? null); // Aceita logo (será null na primeira inserção)
        $stmt->bindParam(':criado_por', $admin_id, PDO::PARAM_INT);
        $stmt->bindParam(':modificado_por', $admin_id, PDO::PARAM_INT); // Mesmo na criação

        return $stmt->execute(); // Retorna true ou false

    } catch (PDOException $e) {
        error_log("Erro ao criar empresa: " . $e->getMessage());
        if ($e->getCode() == 23000) { // Erro de constraint UNIQUE
             if (str_contains($e->getMessage(), 'cnpj')) return "Já existe uma empresa com este CNPJ.";
             // Adicionar verificação para email se ele também for UNIQUE
        }
        return "Erro inesperado ao criar a empresa.";
    }
}
//valida CNPJ - rotina para empresas.
function validarCNPJ($cnpj) {
    // Verificar se foi informado
  if(empty($cnpj))
    return false;
  // Remover caracteres especias
  $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
  // Verifica se o numero de digitos informados
  if (strlen($cnpj) != 14)
    return false;
      // Verifica se todos os digitos são iguais
  if (preg_match('/(\d)\1{13}/', $cnpj))
    return false;
  $b = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    for ($i = 0, $n = 0; $i < 12; $n += $cnpj[$i] * $b[++$i]);
    if ($cnpj[12] != ((($n %= 11) < 2) ? 0 : 11 - $n)) {
        return false;
    }
    for ($i = 0, $n = 0; $i <= 12; $n += $cnpj[$i] * $b[$i++]);
    if ($cnpj[13] != ((($n %= 11) < 2) ? 0 : 11 - $n)) {
        return false;
    }
  return true;
}

function getModelosAuditoria(
    PDO $conexao,
    int $pagina_atual = 1,
    int $itens_por_pagina = 15,
    string $filtro_status = 'todos',
    string $termo_busca = ''
): array {
    // Sanitiza e valida paginação
    $pagina_atual = max(1, $pagina_atual);
    $itens_por_pagina = max(1, $itens_por_pagina); // Evita divisão por zero
    $offset = ($pagina_atual - 1) * $itens_por_pagina;

    $modelos = [];
    $total_itens = 0;
    $params = []; // Array para parâmetros PDO

    // --- Construção da Query Base ---
    // Seleciona os campos e usa SQL_CALC_FOUND_ROWS para obter o total sem outra query complexa
    $sql_select = "SELECT SQL_CALC_FOUND_ROWS
                     m.id, m.nome, m.descricao, m.ativo, m.data_criacao, m.data_modificacao
                   FROM modelos_auditoria m"; // Alias 'm' para a tabela

    // --- Construção da Cláusula WHERE ---
    $where_clauses = [];

    // Filtro por Status
    if ($filtro_status === 'ativos') {
        $where_clauses[] = "m.ativo = :ativo_status";
        $params[':ativo_status'] = 1;
    } elseif ($filtro_status === 'inativos') {
        $where_clauses[] = "m.ativo = :ativo_status";
        $params[':ativo_status'] = 0;
    }
    // Se for 'todos', nenhuma cláusula de status é adicionada.

    // Filtro por Termo de Busca (no nome ou descrição)
    if (!empty($termo_busca)) {
        // Adiciona parênteses para garantir a lógica OR correta com outros filtros AND
        $where_clauses[] = "(m.nome LIKE :busca OR m.descricao LIKE :busca)";
        $params[':busca'] = '%' . $termo_busca . '%'; // Parâmetro para o LIKE
    }

    // Junta as cláusulas WHERE com AND
    $sql_where = "";
    if (!empty($where_clauses)) {
        $sql_where = " WHERE " . implode(' AND ', $where_clauses);
    }

    // --- Construção da Ordenação e Limite ---
    $sql_order_limit = " ORDER BY m.nome ASC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $itens_por_pagina; // PDO::PARAM_INT será definido no bindValue
    $params[':offset'] = $offset;          // PDO::PARAM_INT será definido no bindValue

    // --- Query Completa ---
    $sql = $sql_select . $sql_where . $sql_order_limit;

    // --- Execução ---
    try {
        $stmt = $conexao->prepare($sql);

        // Bind dos parâmetros (PDO lida com a tipagem)
        // Bind de status e busca (se existirem)
        if (isset($params[':ativo_status'])) {
            $stmt->bindValue(':ativo_status', $params[':ativo_status'], PDO::PARAM_INT);
        }
        if (isset($params[':busca'])) {
            $stmt->bindValue(':busca', $params[':busca'], PDO::PARAM_STR);
        }
        // Bind de limit e offset (sempre existem)
        $stmt->bindValue(':limit', $params[':limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $params[':offset'], PDO::PARAM_INT);

        $stmt->execute();
        $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obter o número total de linhas que *seriam* encontradas sem o LIMIT
        $total_itens = (int) $conexao->query("SELECT FOUND_ROWS()")->fetchColumn();

    } catch (PDOException $e) {
        error_log("Erro em getModelosAuditoria (Filtros: status=$filtro_status, busca=$termo_busca): " . $e->getMessage());
        // Em caso de erro, retorna arrays vazios e total 0 para evitar erros na página
        $modelos = [];
        $total_itens = 0;
    }

    // --- Cálculo da Paginação ---
    $total_paginas = ($itens_por_pagina > 0 && $total_itens > 0) ? ceil($total_itens / $itens_por_pagina) : 0;
    $paginacao = [
        'pagina_atual' => $pagina_atual,
        'total_paginas' => $total_paginas,
        'total_itens' => $total_itens,
        'itens_por_pagina' => $itens_por_pagina // Adiciona para referência
    ];

    // --- Retorno ---
    return ['modelos' => $modelos, 'paginacao' => $paginacao];
}
/**
 * Busca uma lista paginada de requisitos de auditoria com filtros.
 */
function getRequisitosAuditoria(
    PDO $conexao,
    int $pagina_atual = 1,
    int $itens_por_pagina = 15,
    string $filtro_status = 'todos',
    string $termo_busca = '',
    string $filtro_categoria = '',
    string $filtro_norma = ''
): array {
    $offset = ($pagina_atual - 1) * $itens_por_pagina;
    $requisitos = [];
    $total_itens = 0;
    $params = [];

    $sql_select = "SELECT SQL_CALC_FOUND_ROWS
                     r.id, r.codigo, r.nome, r.descricao, r.categoria, r.norma_referencia, r.ativo, r.data_criacao
                   FROM requisitos_auditoria r";
    $where_clauses = [];

    // Filtros
    if ($filtro_status === 'ativos') {
        $where_clauses[] = "r.ativo = :ativo"; $params[':ativo'] = 1;
    } elseif ($filtro_status === 'inativos') {
        $where_clauses[] = "r.ativo = :ativo"; $params[':ativo'] = 0;
    }
    if (!empty($termo_busca)) {
        $where_clauses[] = "(r.codigo LIKE :busca OR r.nome LIKE :busca OR r.descricao LIKE :busca)";
        $params[':busca'] = '%' . $termo_busca . '%';
    }
     if (!empty($filtro_categoria)) {
        $where_clauses[] = "r.categoria = :categoria"; $params[':categoria'] = $filtro_categoria;
    }
     if (!empty($filtro_norma)) {
        $where_clauses[] = "r.norma_referencia = :norma"; $params[':norma'] = $filtro_norma;
    }


    $sql_where = "";
    if (!empty($where_clauses)) {
        $sql_where = " WHERE " . implode(' AND ', $where_clauses);
    }

    $sql_order_limit = " ORDER BY r.norma_referencia ASC, r.codigo ASC, r.nome ASC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $itens_por_pagina;
    $params[':offset'] = $offset;

    $sql = $sql_select . $sql_where . $sql_order_limit;

    try {
        $stmt = $conexao->prepare($sql);
        // Bind dinâmico dos parâmetros existentes
        foreach ($params as $key => &$val) {
             // Define o tipo baseado no nome ou valor (simplificado)
            $param_type = (is_int($val) || $key === ':limit' || $key === ':offset' || $key === ':ativo') ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $val, $param_type);
        }
        unset($val); // Desfaz a referência

        $stmt->execute();
        $requisitos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_itens = (int) $conexao->query("SELECT FOUND_ROWS()")->fetchColumn();

    } catch (PDOException $e) {
        error_log("Erro em getRequisitosAuditoria: " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($params, true));
        $requisitos = []; $total_itens = 0;
    }

    $total_paginas = ($itens_por_pagina > 0 && $total_itens > 0) ? ceil($total_itens / $itens_por_pagina) : 0;
    $paginacao = [
        'pagina_atual' => $pagina_atual, 'total_paginas' => $total_paginas,
        'total_itens' => $total_itens, 'itens_por_pagina' => $itens_por_pagina
    ];

    return ['requisitos' => $requisitos, 'paginacao' => $paginacao];
}

/**
 * Cria um novo requisito de auditoria.
 */
function criarRequisitoAuditoria(PDO $conexao, array $dados, int $usuario_id): bool|string {
    // Validação básica (poderia ser mais extensa)
    if (empty($dados['nome']) || empty($dados['descricao'])) {
        return "Nome e Descrição são obrigatórios.";
    }

    // Verificar código único (se fornecido)
    if (!empty($dados['codigo'])) {
         try {
            $stmtCheck = $conexao->prepare("SELECT COUNT(*) FROM requisitos_auditoria WHERE codigo = :codigo");
            $stmtCheck->bindParam(':codigo', $dados['codigo']);
            $stmtCheck->execute();
            if ($stmtCheck->fetchColumn() > 0) {
                return "O código informado já está em uso.";
            }
         } catch (PDOException $e) { /* Ignora erro aqui, a constraint pegaria */ }
    }


    $sql = "INSERT INTO requisitos_auditoria
                (codigo, nome, descricao, categoria, norma_referencia, ativo, criado_por, modificado_por)
            VALUES
                (:codigo, :nome, :descricao, :categoria, :norma, :ativo, :criado_por, :modificado_por)";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindValue(':codigo', empty($dados['codigo']) ? null : $dados['codigo']);
        $stmt->bindParam(':nome', $dados['nome']);
        $stmt->bindParam(':descricao', $dados['descricao']);
        $stmt->bindValue(':categoria', empty($dados['categoria']) ? null : $dados['categoria']);
        $stmt->bindValue(':norma', empty($dados['norma_referencia']) ? null : $dados['norma_referencia']);
        $stmt->bindValue(':ativo', $dados['ativo'] ?? 1, PDO::PARAM_INT); // Default ativo
        $stmt->bindValue(':criado_por', $usuario_id, PDO::PARAM_INT);
        $stmt->bindValue(':modificado_por', $usuario_id, PDO::PARAM_INT); // Mesmo na criação

        return $stmt->execute();

    } catch (PDOException $e) {
        error_log("Erro em criarRequisitoAuditoria: " . $e->getMessage());
         if ($e->getCode() == '23000') { // Código de violação de constraint (UNIQUE)
             if (str_contains($e->getMessage(), 'codigo')) return "O código informado já está em uso.";
         }
        return "Erro inesperado ao salvar o requisito.";
    }
}

/**
 * Busca dados de um requisito específico.
 */
function getRequisitoAuditoria(PDO $conexao, int $id): ?array {
    try {
        $stmt = $conexao->prepare("SELECT * FROM requisitos_auditoria WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        return $req ?: null;
    } catch (PDOException $e) {
        error_log("Erro em getRequisitoAuditoria para ID $id: " . $e->getMessage());
        return null;
    }
}

/**
 * Atualiza um requisito de auditoria existente.
 */
function atualizarRequisitoAuditoria(PDO $conexao, int $id, array $dados, int $usuario_id): bool|string {
     if (empty($dados['nome']) || empty($dados['descricao'])) {
        return "Nome e Descrição são obrigatórios.";
    }

    // Verificar código único (se fornecido e DIFERENTE do ID atual)
    if (!empty($dados['codigo'])) {
         try {
            $stmtCheck = $conexao->prepare("SELECT COUNT(*) FROM requisitos_auditoria WHERE codigo = :codigo AND id != :id");
            $stmtCheck->bindParam(':codigo', $dados['codigo']);
             $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtCheck->execute();
            if ($stmtCheck->fetchColumn() > 0) {
                return "O código informado já está em uso por outro requisito.";
            }
         } catch (PDOException $e) { /* Ignora */ }
    }

    $sql = "UPDATE requisitos_auditoria SET
                codigo = :codigo,
                nome = :nome,
                descricao = :descricao,
                categoria = :categoria,
                norma_referencia = :norma,
                ativo = :ativo,
                modificado_por = :modificado_por
                -- data_modificacao atualiza automaticamente
            WHERE id = :id";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', empty($dados['codigo']) ? null : $dados['codigo']);
        $stmt->bindParam(':nome', $dados['nome']);
        $stmt->bindParam(':descricao', $dados['descricao']);
        $stmt->bindValue(':categoria', empty($dados['categoria']) ? null : $dados['categoria']);
        $stmt->bindValue(':norma', empty($dados['norma_referencia']) ? null : $dados['norma_referencia']);
        $stmt->bindValue(':ativo', $dados['ativo'] ?? 1, PDO::PARAM_INT);
        $stmt->bindValue(':modificado_por', $usuario_id, PDO::PARAM_INT);

        $stmt->execute();
        return true; // Retorna true mesmo se nenhuma linha for alterada (dados iguais)

    } catch (PDOException $e) {
        error_log("Erro em atualizarRequisitoAuditoria ID $id: " . $e->getMessage());
         if ($e->getCode() == '23000') {
             if (str_contains($e->getMessage(), 'codigo')) return "O código informado já está em uso por outro requisito.";
         }
        return "Erro inesperado ao atualizar o requisito.";
    }
}


/**
 * Ativa/Desativa um requisito de auditoria.
 */
function setStatusRequisitoAuditoria(PDO $conexao, int $id, bool $ativo): bool {
    try {
        $stmt = $conexao->prepare("UPDATE requisitos_auditoria SET ativo = :ativo WHERE id = :id");
        $stmt->bindValue(':ativo', $ativo ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $status = $ativo ? 'ativar' : 'desativar';
        error_log("Erro em setStatusRequisitoAuditoria ($status) ID $id: " . $e->getMessage());
        return false;
    }
}

// Funções para obter categorias e normas distintas (para filtros)
function getCategoriasRequisitos(PDO $conexao): array {
    try {
        $stmt = $conexao->query("SELECT DISTINCT categoria FROM requisitos_auditoria WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) { return []; }
}
function getNormasRequisitos(PDO $conexao): array {
     try {
        $stmt = $conexao->query("SELECT DISTINCT norma_referencia FROM requisitos_auditoria WHERE norma_referencia IS NOT NULL AND norma_referencia != '' ORDER BY norma_referencia");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) { return []; }
}

/**
 * Busca dados de um requisito pelo seu CÓDIGO único.
 *
 */
function getRequisitoAuditoriaPorCodigo(PDO $conexao, string $codigo): ?array {
    // Ignora busca se o código estiver vazio
    if (empty($codigo)) {
        return null;
    }
    try {
        $stmt = $conexao->prepare("SELECT * FROM requisitos_auditoria WHERE codigo = :codigo");
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        return $req ?: null;
    } catch (PDOException $e) {
        error_log("Erro em getRequisitoAuditoriaPorCodigo para Código '$codigo': " . $e->getMessage());
        return null;
    }
}

/**
 * Busca a contagem de falhas por dia nos últimos 7 dias.
 */
function getLoginLogsLast7Days(PDO $conexao): array {
    $labels = [];
    $data = [];

    // Hoje até o final do dia (23:59:59)
    $endDate = new DateTime('today 23:59:59');
    $startDate = (new DateTime('today'))->modify('-6 days'); // Inclui hoje

    // Cria um array com todas as datas no intervalo
    $dateRange = [];
    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $dateStr = $currentDate->format('Y-m-d');
        $labels[] = $currentDate->format('d/m');
        $dateRange[$dateStr] = 0;
        $currentDate->modify('+1 day');
    }

    $sql = "SELECT DATE(data_hora) as dia, COUNT(*) as total
            FROM logs_acesso
            WHERE acao <> 'login_sucesso'
              AND sucesso = 0
              AND data_hora BETWEEN :start_date AND :end_date
            GROUP BY dia
            ORDER BY dia ASC";

    try {
        $stmt = $conexao->prepare($sql);
        // Usa timestamps completos para garantir o filtro até o fim do dia
        $stmt->bindValue(':start_date', $startDate->format('Y-m-d 00:00:00'));
        $stmt->bindValue(':end_date', $endDate->format('Y-m-d 23:59:59'));
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            if (isset($dateRange[$row['dia']])) {
                $dateRange[$row['dia']] = (int)$row['total'];
            }
        }

        $data = array_values($dateRange);

    } catch (PDOException $e) {
        error_log("Erro ao buscar dados do gráfico de logins: " . $e->getMessage());
        $labels = [];
        $data = [];
    }

    return ['labels' => $labels, 'data' => $data];
}

function getEmpresaPorId(PDO $conexao, int $id): ?array {
    try {
        // Seleciona todos os campos relevantes da empresa (incluindo 'logo')
        $stmt = $conexao->prepare("SELECT * FROM empresas WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        return $empresa ?: null;
    } catch (PDOException $e) {
        error_log("Erro em getEmpresaPorId para ID $id: " . $e->getMessage());
        return null;
    }
}

function atualizarEmpresa(PDO $conexao, int $id, array $dados, int $admin_id): bool|string {
    // Validação básica (campos obrigatórios)
    if (empty($dados['nome']) || empty($dados['cnpj']) || empty($dados['razao_social']) || empty($dados['email']) || empty($dados['telefone']) || empty($dados['contato'])) {
         return "Erro interno: Dados obrigatórios ausentes para atualização.";
    }

    // Limpa e valida CNPJ
    $cnpjLimpo = preg_replace('/[^0-9]/', '', $dados['cnpj']);
    if (function_exists('validarCNPJ') && !validarCNPJ($cnpjLimpo)) {
         return "CNPJ inválido.";
    }
     // Valida E-mail
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        return "Formato de e-mail inválido.";
    }

    // Verificar se o CNPJ já existe em OUTRA empresa
    try {
        $stmtCheck = $conexao->prepare("SELECT COUNT(*) FROM empresas WHERE cnpj = :cnpj AND id != :id");
        $stmtCheck->bindParam(':cnpj', $cnpjLimpo);
        $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtCheck->execute();
        if ($stmtCheck->fetchColumn() > 0) {
            return "Já existe outra empresa cadastrada com este CNPJ.";
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar duplicação de CNPJ na atualização da empresa ID $id: " . $e->getMessage());
        return "Erro ao verificar dados da empresa. Tente novamente.";
    }

    // Query de atualização incluindo o campo 'logo'
    $sql = "UPDATE empresas SET
                nome = :nome,
                cnpj = :cnpj,
                razao_social = :razao_social,
                endereco = :endereco,
                contato = :contato,
                telefone = :telefone,
                email = :email,
                logo = :logo, -- Adicionado campo logo
                ativo = :ativo,
                modificado_por = :modificado_por
            WHERE id = :id";

    try {
        $stmt = $conexao->prepare($sql);

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':nome', $dados['nome']);
        $stmt->bindParam(':cnpj', $cnpjLimpo);
        $stmt->bindParam(':razao_social', $dados['razao_social']);
        $stmt->bindValue(':endereco', $dados['endereco'] ?: null);
        $stmt->bindParam(':contato', $dados['contato']);
        $stmt->bindParam(':telefone', $dados['telefone']);
        $stmt->bindParam(':email', $dados['email']);
        // Trata o campo logo (pode ser null se foi removido)
        $stmt->bindValue(':logo', $dados['logo'] ?? null); // Usa o valor final do logo (novo, antigo ou null)
        $stmt->bindValue(':ativo', $dados['ativo'] ?? 1, PDO::PARAM_INT);
        $stmt->bindParam(':modificado_por', $admin_id, PDO::PARAM_INT);

        $stmt->execute();
        return true;

    } catch (PDOException $e) {
        error_log("Erro em atualizarEmpresa ID $id: " . $e->getMessage());
         if ($e->getCode() == '23000') { return "Erro ao salvar: Possível CNPJ duplicado."; }
        return "Erro inesperado ao atualizar a empresa no banco de dados.";
    }
}

// Não esqueça da função validarCNPJ, se ela não estiver aqui
if (!function_exists('validarCNPJ')) {
    function validarCNPJ($cnpj): bool {
        $cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);
        if (strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) return false;
        for ($t = 12; $t < 14; $t++) {
            for ($d = 0, $p = ($t - 7), $c = 0; $c < $t; $c++) {
                $d += $cnpj[$c] * $p;
                $p = ($p < 3) ? 9 : $p - 1;
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cnpj[$c] != $d) return false;
        }
        return true;
    }
}

/**
 * Exclui um requisito de auditoria APENAS SE NÃO ESTIVER EM USO.
 */
function excluirRequisitoAuditoria(PDO $conexao, int $id): bool|string {
    try {
        // --- VERIFICAÇÃO DE DEPENDÊNCIAS (EXEMPLO - ADAPTAR!) ---
        // Esta é a parte crucial e depende de como você estrutura os modelos e auditorias.

        // Exemplo 1: Verificar se está em algum item de modelo (tabela hipotética 'modelo_itens')
        /*
        $stmtCheckModelo = $conexao->prepare("SELECT COUNT(*) FROM modelo_itens WHERE requisito_id = :id");
        $stmtCheckModelo->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtCheckModelo->execute();
        if ($stmtCheckModelo->fetchColumn() > 0) {
            return "Não é possível excluir: Requisito está sendo usado em um ou mais modelos de auditoria.";
        }
        */

        // Exemplo 2: Verificar se está em alguma resposta de auditoria (tabela hipotética 'auditoria_respostas')
        // Talvez permitir excluir se a auditoria estiver 'finalizada'?
        /*
        $stmtCheckAuditoria = $conexao->prepare(
            "SELECT COUNT(ar.id)
             FROM auditoria_respostas ar
             JOIN auditorias a ON ar.auditoria_id = a.id -- Supondo join com tabela de auditorias
             WHERE ar.requisito_id = :id
               AND a.status != 'finalizada' -- Exemplo: Não permite excluir se usado em auditoria não finalizada
            ");
        $stmtCheckAuditoria->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtCheckAuditoria->execute();
        if ($stmtCheckAuditoria->fetchColumn() > 0) {
             return "Não é possível excluir: Requisito foi usado em auditorias que ainda não foram finalizadas.";
        }
        */

        // SE NENHUMA DEPENDÊNCIA IMPEDITIVA FOR ENCONTRADA:
        // Você pode optar por desativar em vez de excluir:
        // return setStatusRequisitoAuditoria($conexao, $id, false); // Chama a função para desativar

        // OU, se realmente quiser excluir:
        $conexao->beginTransaction(); // Iniciar transação para exclusão segura

        // Excluir de tabelas filhas PRIMEIRO (se houver e se for desejado)
        // Ex: DELETE FROM modelo_itens WHERE requisito_id = :id
        // Ex: DELETE FROM auditoria_respostas WHERE requisito_id = :id (CUIDADO COM HISTÓRICO!)

        // Excluir o requisito principal
        $stmtDelete = $conexao->prepare("DELETE FROM requisitos_auditoria WHERE id = :id");
        $stmtDelete->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtDelete->execute();

        if ($stmtDelete->rowCount() > 0) {
            $conexao->commit(); // Confirma a exclusão
            return true; // Sucesso
        } else {
            // Se rowCount for 0, o requisito não existia ou já foi excluído
            $conexao->rollBack(); // Desfaz (embora nada tenha sido feito)
            return "Requisito não encontrado para exclusão (ID: $id).";
        }

    } catch (PDOException $e) {
        $conexao->rollBack(); // Desfaz em caso de erro
        error_log("Erro em excluirRequisitoAuditoria ID $id: " . $e->getMessage());
        // Verificar erro de chave estrangeira (FK constraint)
        if (str_contains($e->getMessage(), 'FOREIGN KEY constraint fails')) {
             return "Não é possível excluir: Este requisito está vinculado a outros registros (modelos ou auditorias). Considere desativá-lo.";
        }
        return "Erro inesperado ao tentar excluir o requisito.";
    }
}

/**
 * Tenta excluir uma empresa do banco de dados APÓS verificar se não há
 * usuários ou solicitações de acesso vinculadas a ela.
 *
 * @param PDO $conexao Conexão PDO.
 * @param int $empresa_id ID da empresa a excluir.
 * @return bool|string Retorna true se excluído com sucesso,
 *                     uma string de erro se não puder excluir (devido a dependências ou erro DB),
 *                     ou false em caso de erro muito inesperado (raro).
 */
function excluirEmpresa(PDO $conexao, int $empresa_id): bool|string {
    try {
        // --- VERIFICAÇÃO DE DEPENDÊNCIAS ---

        // 1. Verificar usuários vinculados
        $stmtCheckUsers = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE empresa_id = :empresa_id");
        $stmtCheckUsers->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmtCheckUsers->execute();
        $countUsuarios = $stmtCheckUsers->fetchColumn();

        if ($countUsuarios > 0) {
            return "Não é possível excluir: Existem {$countUsuarios} usuário(s) vinculados a esta empresa. Realoque ou remova os usuários primeiro.";
        }

        // 2. Verificar solicitações de acesso vinculadas
        $stmtCheckSolic = $conexao->prepare("SELECT COUNT(*) FROM solicitacoes_acesso WHERE empresa_id = :empresa_id");
        $stmtCheckSolic->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmtCheckSolic->execute();
        $countSolic = $stmtCheckSolic->fetchColumn();

        if ($countSolic > 0) {
            // Decidir se permite excluir mesmo com solicitações (talvez arquivadas?) ou impede.
            // Vamos impedir por segurança padrão.
            return "Não é possível excluir: Existem {$countSolic} solicitação(ões) de acesso vinculadas a esta empresa.";
            // Alternativa: Poderia apenas avisar e continuar, ou excluir/anonimizar as solicitações.
        }

        // 3. (Opcional) Verificar outras dependências (ex: auditorias futuras)
        // Adicione aqui verificações em outras tabelas que usem empresa_id, se houver.

        // --- EXCLUSÃO (Se passou nas verificações) ---
        $conexao->beginTransaction();

        // Adicional: Pegar o nome do logo ANTES de excluir para remover o arquivo depois
        $stmtLogo = $conexao->prepare("SELECT logo FROM empresas WHERE id = :id");
        $stmtLogo->bindParam(':id', $empresa_id, PDO::PARAM_INT);
        $stmtLogo->execute();
        $logoParaExcluir = $stmtLogo->fetchColumn();

        // Excluir a empresa
        $stmtDelete = $conexao->prepare("DELETE FROM empresas WHERE id = :id");
        $stmtDelete->bindParam(':id', $empresa_id, PDO::PARAM_INT);
        $deleteSuccess = $stmtDelete->execute();
        $rowCount = $stmtDelete->rowCount();

        if ($deleteSuccess && $rowCount > 0) {
            $conexao->commit(); // Confirma a exclusão do DB

            // Tenta excluir o arquivo de logo físico (melhor esforço)
            if ($logoParaExcluir) {
                $caminhoLogo = __DIR__ . '/../../uploads/logos/' . $logoParaExcluir;
                 error_log("Tentando excluir arquivo de logo após exclusão da empresa ID $empresa_id: $caminhoLogo"); // Log
                if (file_exists($caminhoLogo)) {
                    if (!@unlink($caminhoLogo)) {
                        error_log("AVISO: Falha ao excluir arquivo de logo '$logoParaExcluir' para empresa ID $empresa_id.");
                        // Não retorna erro para o usuário por isso, mas loga.
                    } else {
                         error_log("Arquivo de logo '$logoParaExcluir' excluído com sucesso.");
                    }
                } else {
                     error_log("AVISO: Arquivo de logo '$logoParaExcluir' não encontrado para exclusão (Empresa ID $empresa_id).");
                }
            }
            return true; // Sucesso na exclusão da empresa

        } else {
            // Se rowCount for 0, a empresa não existia ou já foi excluída
            $conexao->rollBack();
            error_log("Tentativa de excluir empresa ID $empresa_id falhou (rowCount=0). Empresa não encontrada?");
            return "Empresa não encontrada para exclusão (ID: $empresa_id).";
        }

    } catch (PDOException $e) {
        // Garante rollback em caso de erro durante as verificações ou delete
        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }
        error_log("Erro PDO em excluirEmpresa ID $empresa_id: " . $e->getMessage());
        // Verifica erro FK específico que pode ter passado pelas checagens iniciais
         if (str_contains($e->getMessage(), 'FOREIGN KEY constraint fails')) {
             return "Não é possível excluir: Erro de integridade. Verifique outras dependências.";
         }
        return "Erro inesperado ao tentar excluir a empresa.";
    }
}

/**
 * Busca todas as empresas ativas no banco de dados.
 */
function getEmpresasAtivas(PDO $conexao): array {
    try {
        $sql = "SELECT id, nome FROM empresas WHERE ativo = 1 ORDER BY nome ASC";
        $stmt = $conexao->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar empresas ativas: " . $e->getMessage());
        return []; // Retorna array vazio em caso de erro
    }
}

/**
 * Busca os itens (requisitos vinculados) de um modelo específico,
 * opcionalmente agrupados por seção.
 */
function getItensDoModelo(PDO $conexao, int $modelo_id, bool $agruparPorSecao = false): array {
    $sql = "SELECT
                mi.id as modelo_item_id, mi.secao, mi.ordem_item, mi.ordem_secao,
                r.id as requisito_id, r.codigo, r.nome, r.categoria, r.norma_referencia, r.ativo as requisito_ativo
            FROM modelo_itens mi
            JOIN requisitos_auditoria r ON mi.requisito_id = r.id
            WHERE mi.modelo_id = :modelo_id
            ORDER BY mi.ordem_secao ASC, mi.ordem_item ASC, r.codigo ASC, r.nome ASC"; // Ordenação completa

    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':modelo_id', $modelo_id, PDO::PARAM_INT);
        $stmt->execute();
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($agruparPorSecao) {
            $agrupado = [];
            foreach ($itens as $item) {
                $secao = $item['secao'] ?: 'Itens Gerais'; // Grupo padrão se não houver seção
                $agrupado[$secao][] = $item;
            }
            // Opcional: Ordenar seções se necessário (pela ordem_secao ou nome)
            // uksort($agrupado, function($a, $b) use ($agrupado) { ... });
            return $agrupado;
        } else {
            return $itens;
        }
    } catch (PDOException $e) {
        error_log("Erro em getItensDoModelo (Modelo ID: $modelo_id): " . $e->getMessage());
        return [];
    }
}

/**
 * Busca requisitos ATIVOS que AINDA NÃO estão associados a um modelo específico.
 * Útil para a tela de adicionar itens.
 */
function getRequisitosDisponiveisParaModelo(PDO $conexao, int $modelo_id, string $termo_busca = ''): array {
    $params = [':modelo_id' => $modelo_id];
    $whereBusca = '';
    if (!empty($termo_busca)) {
        $whereBusca = " AND (r.codigo LIKE :busca OR r.nome LIKE :busca OR r.descricao LIKE :busca)";
        $params[':busca'] = '%' . $termo_busca . '%';
    }

    // Seleciona requisitos ativos que NÃO estão na tabela modelo_itens para este modelo_id
    $sql = "SELECT r.id, r.codigo, r.nome, r.categoria, r.norma_referencia
            FROM requisitos_auditoria r
            WHERE r.ativo = 1
              AND r.id NOT IN (SELECT mi.requisito_id FROM modelo_itens mi WHERE mi.modelo_id = :modelo_id)
              {$whereBusca}
            ORDER BY r.norma_referencia ASC, r.categoria ASC, r.codigo ASC, r.nome ASC";

    try {
        $stmt = $conexao->prepare($sql);
        // Bind dinâmico
        foreach ($params as $key => &$val) { $stmt->bindValue($key, $val); } unset($val);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro em getRequisitosDisponiveis (Modelo ID: $modelo_id, Busca: $termo_busca): " . $e->getMessage());
        return [];
    }
}

/**
 * Adiciona um requisito a um modelo de auditoria.
 */
function adicionarRequisitoAoModelo(PDO $conexao, int $modelo_id, int $requisito_id, ?string $secao = null, int $ordem_item = 0): bool|string {
    // Verificar se já existe para não dar erro de constraint UNIQUE
    try {
        $stmtCheck = $conexao->prepare("SELECT COUNT(*) FROM modelo_itens WHERE modelo_id = :mid AND requisito_id = :rid");
        $stmtCheck->execute([':mid' => $modelo_id, ':rid' => $requisito_id]);
        if ($stmtCheck->fetchColumn() > 0) {
            return "Este requisito já está neste modelo."; // Evita erro DB
        }

        // Pega a próxima ordem da seção se não for informada (simplificado)
        $ordem_secao = 0;
        if ($secao) {
            $stmtOrdemSecao = $conexao->prepare("SELECT MAX(ordem_secao) FROM modelo_itens WHERE modelo_id = :mid AND secao = :sec");
            $stmtOrdemSecao->execute([':mid' => $modelo_id, ':sec' => $secao]);
            $maxOrdem = $stmtOrdemSecao->fetchColumn();
            // Se já existe seção, usa a mesma ordem. Se não, pega a próxima ordem geral.
            if ($maxOrdem !== null) {
                $ordem_secao = (int)$maxOrdem; // Usa a ordem existente da seção
            } else {
                 $stmtNextSecao = $conexao->prepare("SELECT MAX(ordem_secao) FROM modelo_itens WHERE modelo_id = :mid");
                 $stmtNextSecao->execute([':mid' => $modelo_id]);
                 $ordem_secao = ($stmtNextSecao->fetchColumn() ?? -1) + 1;
            }
        }
         // Pega a próxima ordem do item DENTRO da seção/modelo
         $stmtOrdemItem = $conexao->prepare("SELECT MAX(ordem_item) FROM modelo_itens WHERE modelo_id = :mid" . ($secao ? " AND secao = :sec" : " AND secao IS NULL"));
         $paramsOrdem = [':mid' => $modelo_id];
         if ($secao) $paramsOrdem[':sec'] = $secao;
         $stmtOrdemItem->execute($paramsOrdem);
         $ordem_item = ($stmtOrdemItem->fetchColumn() ?? -1) + 1;


        $sql = "INSERT INTO modelo_itens (modelo_id, requisito_id, secao, ordem_secao, ordem_item)
                VALUES (:modelo_id, :requisito_id, :secao, :ordem_secao, :ordem_item)";
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':modelo_id', $modelo_id, PDO::PARAM_INT);
        $stmt->bindParam(':requisito_id', $requisito_id, PDO::PARAM_INT);
        $stmt->bindValue(':secao', $secao ?: null); // Permite null
        $stmt->bindValue(':ordem_secao', $ordem_secao, PDO::PARAM_INT);
        $stmt->bindValue(':ordem_item', $ordem_item, PDO::PARAM_INT);

        return $stmt->execute();

    } catch (PDOException $e) {
        error_log("Erro adicionarRequisitoAoModelo (Mod: $modelo_id, Req: $requisito_id): " . $e->getMessage());
        return "Erro ao adicionar requisito ao modelo.";
    }
}

/**
 * Remove um requisito específico de um modelo (pelo ID da ligação em modelo_itens).
 */
function removerRequisitoDoModelo(PDO $conexao, int $modelo_item_id): bool {
    try {
        $sql = "DELETE FROM modelo_itens WHERE id = :id";
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':id', $modelo_item_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0; // Retorna true se removeu
    } catch (PDOException $e) {
         error_log("Erro removerRequisitoDoModelo (Item ID: $modelo_item_id): " . $e->getMessage());
        return false;
    }
}

// (Função para atualizar modelo básico - nome/descrição - Opcional, pode ser feita em outra função)
function atualizarModeloAuditoria(PDO $conexao, int $id, string $nome, ?string $descricao, bool $ativo, int $admin_id): bool|string {
     // Verificar nome único (excluindo o próprio ID)
     try {
         $stmtCheck = $conexao->prepare("SELECT COUNT(*) FROM modelos_auditoria WHERE nome = :nome AND id != :id");
         $stmtCheck->execute([':nome' => $nome, ':id' => $id]);
         if ($stmtCheck->fetchColumn() > 0) { return "Já existe outro modelo com este nome."; }

         $sql = "UPDATE modelos_auditoria SET nome = :nome, descricao = :descricao, ativo = :ativo, modificado_por = :admin_id WHERE id = :id";
         $stmt = $conexao->prepare($sql);
         $stmt->bindParam(':id', $id, PDO::PARAM_INT);
         $stmt->bindParam(':nome', $nome);
         $stmt->bindValue(':descricao', $descricao ?: null);
         $stmt->bindValue(':ativo', $ativo ? 1 : 0, PDO::PARAM_INT);
         $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
         return $stmt->execute();

     } catch (PDOException $e) {
        error_log("Erro atualizarModeloAuditoria ID $id: " . $e->getMessage());
         if ($e->getCode() == '23000') { return "Erro ao salvar: Nome duplicado."; }
        return "Erro inesperado ao atualizar o modelo.";
     }
}

/**
 * Exclui um modelo de auditoria e suas associações na tabela modelo_itens.
 * ATENÇÃO: Verificar se o modelo não está sendo usado em auditorias ANTES de chamar!
 */
function excluirModeloAuditoria(PDO $conexao, int $modelo_id): bool|string {
    // --- PASSO IMPORTANTE: VERIFICAÇÃO DE DEPENDÊNCIA EM AUDITORIAS ---
    // ANTES de excluir um modelo, VERIFIQUE se ele está vinculado a alguma
    // auditoria na tabela `auditorias`. Se estiver, NÃO permita a exclusão
    // ou defina uma regra clara (ex: desativar o modelo em vez de excluir).
    try {
        $stmtCheckAud = $conexao->prepare("SELECT COUNT(*) FROM auditorias WHERE modelo_id = :modelo_id");
        $stmtCheckAud->bindParam(':modelo_id', $modelo_id, PDO::PARAM_INT);
        $stmtCheckAud->execute();
        if ($stmtCheckAud->fetchColumn() > 0) {
            return "Não é possível excluir: Este modelo está sendo utilizado em uma ou mais auditorias. Considere desativá-lo.";
        }
    } catch (PDOException $e) {
         error_log("Erro ao verificar uso do modelo $modelo_id em auditorias: " . $e->getMessage());
         return "Erro ao verificar dependências do modelo.";
    }
    // --- FIM DA VERIFICAÇÃO DE DEPENDÊNCIA ---


    $inTransaction = $conexao->inTransaction();
    if (!$inTransaction) { $conexao->beginTransaction(); }

    try {
        // 1. Excluir os itens associados na tabela de ligação (CASCADE deve fazer isso, mas por segurança)
        // A constraint ON DELETE CASCADE na tabela `modelo_itens` já deve cuidar disso.
        // Se não tiver a constraint, descomente a linha abaixo:
        // $stmtDelItens = $conexao->prepare("DELETE FROM modelo_itens WHERE modelo_id = :modelo_id");
        // $stmtDelItens->execute([':modelo_id' => $modelo_id]);

        // 2. Excluir o modelo principal
        $stmtDelModelo = $conexao->prepare("DELETE FROM modelos_auditoria WHERE id = :id");
        $stmtDelModelo->bindParam(':id', $modelo_id, PDO::PARAM_INT);
        $deleteSuccess = $stmtDelModelo->execute();
        $rowCount = $stmtDelModelo->rowCount();

        if ($deleteSuccess && $rowCount > 0) {
            if (!$inTransaction) { $conexao->commit(); }
            return true; // Sucesso
        } else {
            if (!$inTransaction) { $conexao->rollBack(); }
            error_log("Tentativa de excluir modelo ID $modelo_id falhou (rowCount=0). Modelo não encontrado?");
            return "Modelo não encontrado para exclusão (ID: $modelo_id).";
        }

    } catch (PDOException $e) {
        if (!$inTransaction && $conexao->inTransaction()) { $conexao->rollBack(); }
        error_log("Erro PDO em excluirModeloAuditoria ID $modelo_id: " . $e->getMessage());
        // Erro FK pode ocorrer se a checagem de auditorias falhar por algum motivo
        if (str_contains($e->getMessage(), 'FOREIGN KEY constraint fails')) {
             return "Não é possível excluir: Erro de integridade. Verifique se o modelo está em uso.";
        }
        return "Erro inesperado ao tentar excluir o modelo.";
    }
}

/**
 * Atualiza a ordem dos itens dentro de um modelo/seção.
 * Recebe um array de IDs de modelo_itens na ordem desejada.
 */
function salvarOrdemItensModelo(PDO $conexao, int $modelo_id, array $ordem_item_ids, ?string $secao = null): bool {
     $inTransaction = $conexao->inTransaction();
     if (!$inTransaction) { $conexao->beginTransaction(); }
    try {
        $sql = "UPDATE modelo_itens SET ordem_item = :ordem WHERE id = :id AND modelo_id = :modelo_id";
         // Adiciona condição de seção se fornecida
         if ($secao !== null) {
             $sql .= " AND secao " . ($secao === 'Itens Gerais' ? "IS NULL" : "= :secao");
         }

        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':modelo_id', $modelo_id, PDO::PARAM_INT);
        if ($secao !== null && $secao !== 'Itens Gerais') {
             $stmt->bindParam(':secao', $secao);
        }

        foreach ($ordem_item_ids as $index => $itemId) {
            $ordem = $index; // Ordem baseada no índice do array
            $itemIdInt = (int)$itemId; // Garante que é inteiro
            $stmt->bindParam(':ordem', $ordem, PDO::PARAM_INT);
            $stmt->bindParam(':id', $itemIdInt, PDO::PARAM_INT);
            if (!$stmt->execute()) {
                 throw new Exception("Falha ao atualizar ordem para item ID $itemIdInt");
            }
        }
        if (!$inTransaction) { $conexao->commit(); }
        return true;
    } catch (Exception $e) {
         if (!$inTransaction && $conexao->inTransaction()) { $conexao->rollBack(); }
         error_log("Erro salvarOrdemItensModelo (Modelo: $modelo_id, Seção: $secao): " . $e->getMessage());
        return false;
    }
}

/** Cria um novo modelo */
function criarModeloAuditoria(PDO $conexao, string $nome, ?string $descricao, int $admin_id): bool|string {
    if (empty($nome)) return "Nome do modelo é obrigatório.";
    try {
        $stmtCheck = $conexao->prepare("SELECT COUNT(*) FROM modelos_auditoria WHERE nome = :nome");
        $stmtCheck->execute([':nome' => $nome]);
        if ($stmtCheck->fetchColumn() > 0) return "Já existe um modelo com este nome.";

        // **** QUERY CORRIGIDA: Inclui placeholders para criado_por e modificado_por ****
        $sql = "INSERT INTO modelos_auditoria
                    (nome, descricao, ativo, criado_por, modificado_por)
                VALUES
                    (:nome, :descricao, 1, :criado_por, :modificado_por)";
        $stmt = $conexao->prepare($sql);

        // **** PARÂMETROS CORRIGIDOS: Inclui os valores para os novos placeholders ****
        $params = [
            ':nome' => $nome,
            ':descricao' => $descricao, // PDO trata null
            ':criado_por' => $admin_id,
            ':modificado_por' => $admin_id // Mesmo ID na criação
        ];

        return $stmt->execute($params); // Executa com os 4 parâmetros nomeados

    } catch (PDOException $e) {
        error_log("Erro criarModeloAuditoria: " . $e->getMessage());
        if ($e->getCode() == '23000' && str_contains($e->getMessage(), 'uq_modelo_nome')) { return "Já existe um modelo com este nome (erro DB)."; }
        return "Erro DB ao criar modelo.";
    }
}

/** Ativa um modelo */
function ativarModeloAuditoria(PDO $conexao, int $id): bool {
    try { $stmt=$conexao->prepare("UPDATE modelos_auditoria SET ativo=1 WHERE id=:id"); return $stmt->execute([':id'=>$id]) && $stmt->rowCount()>0; }
    catch (PDOException $e) { error_log("Erro ativarModeloAuditoria ID $id: ".$e->getMessage()); return false; }
}

/** Desativa um modelo */
function desativarModeloAuditoria(PDO $conexao, int $id): bool {
    try { $stmt=$conexao->prepare("UPDATE modelos_auditoria SET ativo=0 WHERE id=:id"); return $stmt->execute([':id'=>$id]) && $stmt->rowCount()>0; }
    catch (PDOException $e) { error_log("Erro desativarModeloAuditoria ID $id: ".$e->getMessage()); return false; }
}

/** Busca um modelo pelo ID */
function getModeloAuditoria(PDO $conexao, int $id): ?array {
    try { $stmt = $conexao->prepare("SELECT * FROM modelos_auditoria WHERE id = :id"); $stmt->execute([':id'=>$id]); $m=$stmt->fetch(PDO::FETCH_ASSOC); return $m?:null; }
    catch (PDOException $e) { error_log("Erro getModeloAuditoria ID $id: ".$e->getMessage()); return null; }
}
