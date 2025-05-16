<?php
// includes/admin_functions.php

// --- Funções para Gestão de Usuários ---


/**
 * Obtém os dados de um único usuário pelo ID.
 */
function getUsuario($conexao, $id) {
    $sql = "SELECT id, nome, email, perfil, ativo, data_cadastro, foto FROM usuarios WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
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

// includes/admin_functions.php

// =========================================================================
// FUNÇÕES EXISTENTES (Revisão e Sugestões de Adaptação)
// =========================================================================

// --- Funções para Gestão de Usuários ---

/**
 * Obtém a lista de usuários do banco de dados com filtros adicionais para Admin da Plataforma.
 */
function getUsuarios(PDO $conexao, $excluir_admin_id = null, $pagina = 1, $itens_por_pagina = 10, ?int $filtro_empresa_id = null, ?string $filtro_perfil = null, string $termo_busca_usuario = '') {
    $offset = ($pagina - 1) * $itens_por_pagina;
    $sql_select = "SELECT SQL_CALC_FOUND_ROWS u.id, u.nome, u.email, u.perfil, u.ativo, u.data_cadastro, u.empresa_id, e.nome as nome_empresa
                   FROM usuarios u
                   LEFT JOIN empresas e ON u.empresa_id = e.id";
    $where_clauses = [];
    $params = [];

    if ($excluir_admin_id !== null) {
        $where_clauses[] = "u.id != :excluir_admin_id";
        $params[':excluir_admin_id'] = $excluir_admin_id;
    }
    if ($filtro_empresa_id !== null) {
        $where_clauses[] = "u.empresa_id = :filtro_empresa_id";
        $params[':filtro_empresa_id'] = $filtro_empresa_id;
    }
    if ($filtro_perfil !== null && !empty($filtro_perfil)) {
        $where_clauses[] = "u.perfil = :filtro_perfil";
        $params[':filtro_perfil'] = $filtro_perfil;
    }
    if (!empty($termo_busca_usuario)) {
        $where_clauses[] = "(u.nome LIKE :busca_usr OR u.email LIKE :busca_usr)";
        $params[':busca_usr'] = '%' . $termo_busca_usuario . '%';
    }

    $sql_where = "";
    if (!empty($where_clauses)) {
        $sql_where = " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql_order_limit = " ORDER BY u.nome ASC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $itens_por_pagina;
    $params[':offset'] = $offset;

    $sql = $sql_select . $sql_where . $sql_order_limit;
    $stmt = $conexao->prepare($sql);
    foreach ($params as $key => &$val) { $stmt->bindValue($key, $val, (is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR)); } unset($val);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_usuarios = (int) $conexao->query("SELECT FOUND_ROWS()")->fetchColumn();
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
 * Atualiza os dados de um usuário no banco de dados.
 * Adaptação: Permitir definir `is_empresa_admin_cliente` e campos de especialidade/certificação.
 */
function atualizarUsuario(PDO $conexao, int $id, string $nome, string $email, string $perfil, int $ativo, ?int $empresa_id, bool $is_empresa_admin = false, ?string $especialidade = null, ?string $certificacoes = null): bool {
    // Lógica da query SQL para incluir os novos campos se eles forem adicionados à tabela `usuarios`
    // Ex: ..., is_empresa_admin_cliente = :is_empresa_admin, especialidade_auditor = :especialidade, ...
    $sql = "UPDATE usuarios SET nome = :nome, email = :email, perfil = :perfil, ativo = :ativo, empresa_id = :empresa_id";
    $params = [
        ':nome' => $nome, ':email' => $email, ':perfil' => $perfil, ':ativo' => $ativo,
        ':empresa_id' => $empresa_id, ':id' => $id
    ];

    // Adicionar campos opcionais SE eles existirem na tabela e forem passados
    // if ($perfil === 'gestor_empresa') { // Exemplo: is_empresa_admin só para gestores de empresa
    //     $sql .= ", is_empresa_admin_cliente = :is_empresa_admin";
    //     $params[':is_empresa_admin'] = (int)$is_empresa_admin;
    // }
    // if ($perfil === 'auditor_empresa') {
    //     $sql .= ", especialidade_auditor = :especialidade, certificacoes_auditor = :certificacoes";
    //     $params[':especialidade'] = $especialidade;
    //     $params[':certificacoes'] = $certificacoes;
    // }
    $sql .= " WHERE id = :id";

    $stmt = $conexao->prepare($sql);
    try {
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao atualizar usuário ID $id: " . $e->getMessage());
        return false;
    }
}

// --- Funções para Gestão de Empresas Clientes ---

/**
 * Registra uma nova empresa cliente na plataforma.
 */
function registrarNovaEmpresaCliente(PDO $conexao, array $dadosCliente, int $admin_id_criador): array {
    // Validações: nome, cnpj (único), email_contato, plano_assinatura_id
    if (empty($dadosCliente['nome_fantasia']) || empty($dadosCliente['cnpj_cliente']) || empty($dadosCliente['email_contato_cliente']) || empty($dadosCliente['plano_assinatura_id_cliente'])) {
        return ['success' => false, 'message' => 'Campos obrigatórios (Nome, CNPJ, E-mail Contato, Plano) não preenchidos.'];
    }
    if (!validarCNPJ($dadosCliente['cnpj_cliente'])) {
        return ['success' => false, 'message' => 'CNPJ inválido.'];
    }
    if (!filter_var($dadosCliente['email_contato_cliente'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'E-mail de contato inválido.'];
    }

    // Verificar duplicação de CNPJ
    $stmtCheckCnpj = $conexao->prepare("SELECT id FROM empresas WHERE cnpj = :cnpj");
    $stmtCheckCnpj->execute([':cnpj' => $dadosCliente['cnpj_cliente']]);
    if ($stmtCheckCnpj->fetch()) {
        return ['success' => false, 'message' => 'Já existe uma empresa cadastrada com este CNPJ.'];
    }

    $sql = "INSERT INTO empresas (nome, cnpj, razao_social, email, telefone, contato,
                logo, plano_assinatura_id, status_contrato_cliente, ativo_na_plataforma,
                criado_por, data_cadastro_plataforma)
            VALUES (:nome, :cnpj, :razao_social, :email, :telefone, :contato_principal,
                :logo_path, :plano_id, :status_contrato, 1,
                :admin_criador, NOW())";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':nome' => $dadosCliente['nome_fantasia'],
            ':cnpj' => $dadosCliente['cnpj_cliente'],
            ':razao_social' => $dadosCliente['razao_social'] ?: null,
            ':email' => $dadosCliente['email_contato_cliente'],
            ':telefone' => $dadosCliente['telefone_contato_cliente'] ?: null,
            ':contato_principal' => $dadosCliente['contato_principal_cliente'] ?? null, // Adicionar ao form se necessário
            ':logo_path' => $dadosCliente['logo_cliente_path'] ?? null,
            ':plano_id' => $dadosCliente['plano_assinatura_id_cliente'],
            ':status_contrato' => $dadosCliente['status_contrato_cliente'] ?? 'Teste',
            ':admin_criador' => $admin_id_criador
        ]);
        $novaEmpresaId = $conexao->lastInsertId();
        if ($novaEmpresaId) {
            dbRegistrarLogAcesso($admin_id_criador, $_SERVER['REMOTE_ADDR'], 'registro_empresa_cliente', 1, "Empresa ID: $novaEmpresaId, Nome: {$dadosCliente['nome_fantasia']}", $conexao);
            return ['success' => true, 'empresa_id' => (int)$novaEmpresaId, 'message' => 'Empresa cliente registrada.'];
        }
        return ['success' => false, 'message' => 'Falha ao obter ID da nova empresa.'];
    } catch (PDOException $e) {
        error_log("Erro ao registrar nova empresa cliente: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro de banco de dados ao registrar empresa.'];
    }
}

/**
 * Lista empresas clientes com paginação e filtros para o Admin da Plataforma.
 */
function listarEmpresasClientesPaginado(PDO $conexao, int $pagina = 1, int $itens_por_pagina = 15, array $filtros = []): array {
    $offset = ($pagina - 1) * $itens_por_pagina;
    $sql_select = "SELECT SQL_CALC_FOUND_ROWS e.id, e.nome as nome_fantasia, e.razao_social, e.cnpj as cnpj_cliente, e.email as email_contato_cliente, e.logo as logo_cliente_path, e.status_contrato_cliente, e.ativo_na_plataforma,
                        ppa.nome_plano as nome_plano_assinatura,
                        (SELECT COUNT(*) FROM usuarios u WHERE u.empresa_id = e.id AND u.ativo = 1) as total_usuarios_vinculados
                   FROM empresas e
                   LEFT JOIN plataforma_planos_assinatura ppa ON e.plano_assinatura_id = ppa.id";
    $where_clauses = [];
    $params = [];

    if (!empty($filtros['busca_cliente'])) {
        $where_clauses[] = "(e.nome LIKE :busca OR e.razao_social LIKE :busca OR e.cnpj LIKE :busca OR e.email LIKE :busca)";
        $params[':busca'] = '%' . $filtros['busca_cliente'] . '%';
    }
    if (!empty($filtros['plano_id_filtro'])) {
        $where_clauses[] = "e.plano_assinatura_id = :plano_id";
        $params[':plano_id'] = $filtros['plano_id_filtro'];
    }
    if (!empty($filtros['status_contrato_filtro']) && $filtros['status_contrato_filtro'] !== 'todos') {
        $where_clauses[] = "e.status_contrato_cliente = :status_contrato";
        $params[':status_contrato'] = $filtros['status_contrato_filtro'];
    }

    $sql_where = "";
    if(!empty($where_clauses)) { $sql_where = " WHERE " . implode(" AND ", $where_clauses); }

    $sql_order_limit = " ORDER BY e.nome ASC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $itens_por_pagina;
    $params[':offset'] = $offset;

    $sql = $sql_select . $sql_where . $sql_order_limit;
    // ... (lógica de execução e retorno similar a getUsuarios, adaptando nomes) ...
     try {
        $stmt = $conexao->prepare($sql);
        foreach ($params as $key => &$val) { $stmt->bindValue($key, $val, (is_int($val) && $key !== ':busca' ? PDO::PARAM_INT : PDO::PARAM_STR)); } unset($val);
        $stmt->execute();
        $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_itens = (int) $conexao->query("SELECT FOUND_ROWS()")->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erro listarEmpresasClientesPaginado: " . $e->getMessage());
        return ['empresas_clientes' => [], 'paginacao' => ['pagina_atual' => 1, 'total_paginas' => 0, 'total_itens' => 0]];
    }

    return [
        'empresas_clientes' => $empresas,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'total_paginas' => ceil($total_itens / $itens_por_pagina),
            'total_itens' => $total_itens,
        ]
    ];
}


/**
 * Busca dados de uma empresa cliente específica para edição pelo Admin da Plataforma.
 */
function getEmpresaClientePorId(PDO $conexao, int $empresa_id): ?array {
    // Query para buscar dados da empresa e nome do plano
    $sql = "SELECT e.*, ppa.nome_plano as nome_plano_assinatura
            FROM empresas e
            LEFT JOIN plataforma_planos_assinatura ppa ON e.plano_assinatura_id = ppa.id
            WHERE e.id = :empresa_id";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([':empresa_id' => $empresa_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    // Renomear chaves para consistência com o formulário se necessário,
    // por exemplo, se o form usa 'nome_fantasia_cliente_edit' mas a tabela é só 'nome'
    if ($empresa) {
        $empresa['nome_fantasia'] = $empresa['nome']; // Exemplo de mapeamento
        $empresa['cnpj_cliente'] = $empresa['cnpj'];
        $empresa['email_contato_cliente'] = $empresa['email'];
        $empresa['telefone_contato_cliente'] = $empresa['telefone'];
        $empresa['endereco_cliente'] = $empresa['endereco'];
        $empresa['contato_principal_cliente'] = $empresa['contato'];
        $empresa['logo_cliente_path'] = $empresa['logo'];
        // Adicionar campos de limites personalizados se eles existirem na tabela `empresas`
        // $empresa['limite_usuarios_personalizado'] = $empresa['limite_usuarios_override'];
        // $empresa['limite_armazenamento_personalizado_mb'] = $empresa['limite_armazenamento_override_mb'];
    }
    return $empresa ?: null;
}

/**
 * Atualiza os dados de uma empresa cliente.
 */
function atualizarDadosEmpresaCliente(PDO $conexao, int $empresa_id, array $dados_update, int $admin_id_acao): bool|string {
    // Validar dados essenciais (nome, cnpj, email, plano_id, status_contrato)
    if (empty($dados_update['nome_fantasia']) || /* ... outras validações ... */ empty($dados_update['plano_assinatura_id_novo'])) {
        return "Campos obrigatórios não preenchidos.";
    }
    // ... (validação de CNPJ duplicado em OUTRA empresa, e-mail) ...

    $sql = "UPDATE empresas SET
                nome = :nome, cnpj = :cnpj, razao_social = :razao_social, email = :email,
                telefone = :telefone, endereco = :endereco, contato = :contato,
                logo = :logo, plano_assinatura_id = :plano_id, status_contrato_cliente = :status_contrato,
                ativo_na_plataforma = :ativo_plat, modificado_por = :admin_id
                -- , limite_usuarios_override = :lim_usr, limite_armazenamento_override_mb = :lim_arm -- Campos opcionais de override
            WHERE id = :empresa_id";
    try {
        $stmt = $conexao->prepare($sql);
        $params = [
            ':nome' => $dados_update['nome_fantasia'],
            ':cnpj' => $dados_update['cnpj_cliente'],
            ':razao_social' => $dados_update['razao_social'] ?: null,
            ':email' => $dados_update['email_contato_cliente'],
            ':telefone' => $dados_update['telefone_contato_cliente'] ?: null,
            ':endereco' => $dados_update['endereco_cliente'] ?: null,
            ':contato' => $dados_update['contato_principal_cliente'] ?: null,
            ':logo' => $dados_update['logo_cliente_path_final'], // Nome do arquivo já processado
            ':plano_id' => $dados_update['plano_assinatura_id_novo'],
            ':status_contrato' => $dados_update['status_contrato_novo'],
            ':ativo_plat' => $dados_update['ativo_na_plataforma'],
            ':admin_id' => $admin_id_acao,
            ':empresa_id' => $empresa_id
            // ':lim_usr' => $dados_update['limite_usuarios_personalizado_cliente'] ?: NULL,
            // ':lim_arm' => $dados_update['limite_armazenamento_personalizado_cliente_mb'] ?: NULL,
        ];
        $stmt->execute($params);
        dbRegistrarLogAcesso($admin_id_acao, $_SERVER['REMOTE_ADDR'], 'update_empresa_cliente', 1, "Empresa ID: $empresa_id atualizada.", $conexao);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao atualizar empresa cliente ID $empresa_id: " . $e->getMessage());
        return "Erro de banco de dados ao atualizar empresa.";
    }
}

// ... (Outras funções de gestão de empresa cliente, como mudar status contrato) ...

// --- Funções para `plataforma_config_metodologia_risco.php` ---

/**
 * Busca a configuração global da metodologia de risco (serializada).
 */
function getConfigMetodologiaRiscoGlobal(PDO $conexao): array {
    // Esta é uma função genérica que pode ser usada para outras configs
    return getConfigGlobalSerializado($conexao, 'metodologia_risco_global', [
        // Valores default se não encontrar
        'tipo_calculo_risco' => 'Matricial',
        'escala_impacto_labels' => ['Baixo', 'Médio', 'Alto'],
        'escala_impacto_valores' => [1, 2, 3],
        'escala_probabilidade_labels' => ['Rara', 'Possível', 'Frequente'],
        'escala_probabilidade_valores' => [1, 2, 3],
        'matriz_risco_definicao' => [],
        'niveis_risco_resultado_labels' => ['Baixo', 'Médio', 'Alto', 'Crítico'],
        'niveis_risco_cores_hex' => ['#28a745', '#ffc107', '#dc3545', '#6f42c1']
    ]);
}

/**
 * Salva a configuração global da metodologia de risco (serializada).
 */
function salvarConfigMetodologiaRiscoGlobal(PDO $conexao, array $configRisco, int $admin_id): bool {
    // Esta é uma função genérica que pode ser usada para outras configs
    return salvarConfigGlobalSerializado($conexao, 'metodologia_risco_global', $configRisco, $admin_id);
}

// --- Funções CRUD para Catálogos Globais (`plataforma_catalogo_tipos_nao_conformidade.php`, `plataforma_catalogo_niveis_criticidade_achado.php`) ---
// Vou exemplificar para Tipos de Não Conformidade, as de Níveis de Criticidade seriam muito similares.

/**
 * Cria um novo Tipo de Não Conformidade Global.
 */
function criarTipoNaoConformidadeGlobal(PDO $conexao, string $nome, ?string $descricao, bool $ativo, int $admin_id): bool|string {
    if(empty($nome)) return "Nome do Tipo de NC é obrigatório.";
    try {
        $stmtCheck = $conexao->prepare("SELECT id FROM plataforma_tipos_nao_conformidade WHERE nome_tipo_nc = :nome");
        $stmtCheck->execute([':nome' => $nome]);
        if ($stmtCheck->fetch()) return "Já existe um Tipo de NC com este nome.";

        $sql = "INSERT INTO plataforma_tipos_nao_conformidade (nome_tipo_nc, descricao_tipo_nc, ativo, criado_por_admin_id, modificado_por_admin_id)
                VALUES (:nome, :desc, :ativo, :admin_id, :admin_id)";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':nome' => $nome, ':desc' => $descricao ?: null, ':ativo' => (int)$ativo,
            ':admin_id' => $admin_id
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro criarTipoNaoConformidadeGlobal: " . $e->getMessage());
        return "Erro DB ao criar Tipo de NC.";
    }
}

/**
 * Lista Tipos de Não Conformidade Globais com paginação.
 */
function listarTiposNaoConformidadeGlobalPaginado(PDO $conexao, int $pagina = 1, int $itens_por_pagina = 15): array {
    $offset = ($pagina - 1) * $itens_por_pagina;
    $sql_select = "SELECT SQL_CALC_FOUND_ROWS * FROM plataforma_tipos_nao_conformidade ORDER BY nome_tipo_nc ASC LIMIT :limit OFFSET :offset";
    $stmt = $conexao->prepare($sql_select);
    $stmt->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $tipos_nc = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_itens = (int) $conexao->query("SELECT FOUND_ROWS()")->fetchColumn();
    return [
        'tipos_nc' => $tipos_nc,
        'paginacao' => ['pagina_atual' => $pagina, 'total_paginas' => ceil($total_itens / $itens_por_pagina), 'total_itens' => $total_itens]
    ];
}

/**
 * Busca um Tipo de Não Conformidade Global por ID.
 */
function getTipoNaoConformidadeGlobalPorId(PDO $conexao, int $id): ?array {
    $stmt = $conexao->prepare("SELECT * FROM plataforma_tipos_nao_conformidade WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Atualiza um Tipo de Não Conformidade Global.
 */
function atualizarTipoNaoConformidadeGlobal(PDO $conexao, int $id, string $nome, ?string $descricao, bool $ativo, int $admin_id): bool|string {
    if(empty($nome)) return "Nome do Tipo de NC é obrigatório.";
    try {
        $stmtCheck = $conexao->prepare("SELECT id FROM plataforma_tipos_nao_conformidade WHERE nome_tipo_nc = :nome AND id != :id");
        $stmtCheck->execute([':nome' => $nome, ':id' => $id]);
        if ($stmtCheck->fetch()) return "Já existe outro Tipo de NC com este nome.";

        $sql = "UPDATE plataforma_tipos_nao_conformidade SET nome_tipo_nc = :nome, descricao_tipo_nc = :desc, ativo = :ativo, modificado_por_admin_id = :admin_id, data_modificacao = NOW() WHERE id = :id";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':nome' => $nome, ':desc' => $descricao ?: null, ':ativo' => (int)$ativo,
            ':admin_id' => $admin_id, ':id' => $id
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro atualizarTipoNaoConformidadeGlobal ID $id: " . $e->getMessage());
        return "Erro DB ao atualizar Tipo de NC.";
    }
}

/**
 * Define o status (ativo/inativo) de um Tipo de Não Conformidade Global.
 */
function setStatusTipoNaoConformidadeGlobal(PDO $conexao, int $id, bool $ativo, int $admin_id): bool {
    try {
        $sql = "UPDATE plataforma_tipos_nao_conformidade SET ativo = :ativo, modificado_por_admin_id = :admin_id, data_modificacao = NOW() WHERE id = :id";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([':ativo' => (int)$ativo, ':admin_id' => $admin_id, ':id' => $id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro setStatusTipoNaoConformidadeGlobal ID $id: " . $e->getMessage());
        return false;
    }
}

/**
 * Exclui um Tipo de Não Conformidade Global.
 */
function excluirTipoNaoConformidadeGlobal(PDO $conexao, int $id): bool|string {
    // *** IMPORTANTE: Verificar se este tipo_nc_id está em uso em `auditoria_item_tipos_nc_selecionados` ***
    // Se estiver em uso, retornar mensagem de erro.
    // Ex: $stmtCheckUso = $conexao->prepare("SELECT COUNT(*) FROM auditoria_item_tipos_nc_selecionados WHERE tipo_nc_id = :id");
    // if ($stmtCheckUso->fetchColumn() > 0) return "Tipo de NC em uso, não pode ser excluído.";
    try {
        $stmt = $conexao->prepare("DELETE FROM plataforma_tipos_nao_conformidade WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro excluirTipoNaoConformidadeGlobal ID $id: " . $e->getMessage());
        return "Erro DB ao excluir Tipo de NC.";
    }
}

// --- Funções CRUD para Níveis de Criticidade (plataforma_catalogo_niveis_criticidade_achado.php) ---
// ... (criarNivelCriticidadeGlobal, listarNiveisCriticidadeGlobal, getNivelCriticidadeGlobalPorId, etc. - muito similar às de Tipo de NC, adaptando nomes de tabela e campos) ...


// --- Funções para Configuração de Workflows (`plataforma_config_workflows_auditoria.php`) ---

/**
 * Busca a configuração global de workflows (serializada).
 */
function getWorkflowConfigGlobal(PDO $conexao): array {
    return getConfigGlobalSerializado($conexao, 'workflow_auditoria_global', [
        'status_disponiveis' => ['Planejada', 'Em Andamento', 'Pausada', 'Concluída (Auditor)', 'Em Revisão', 'Aprovada', 'Rejeitada', 'Cancelada', 'Aguardando Correção Auditor'],
        'regras_transicao' => [], 'templates_notificacao' => []
    ]);
}

/**
 * Salva a configuração global de workflows (serializada).
 */
function salvarWorkflowConfigGlobal(PDO $conexao, array $configWorkflow, int $admin_id): bool {
    // Adicionar validação da estrutura de $configWorkflow aqui se necessário
    return salvarConfigGlobalSerializado($conexao, 'workflow_auditoria_global', $configWorkflow, $admin_id);
}

// --- Funções CRUD para Campos Personalizados (`plataforma_gerenciamento_campos_personalizados.php`) ---
// ... (criarCampoPersonalizadoGlobal, listarCamposPersonalizadosGlobaisPaginado, getCampoPersonalizadoGlobalPorId, etc.) ...
// Lembre-se de tratar os campos JSON (aplicavel_a_entidade_db, opcoes_lista_db, disponivel_para_planos_ids_db)

// --- Funções para `plataforma_parametros_globais.php` já usam getConfigGlobalSerializado e salvarConfigGlobalSerializado ---

// --- Funções para `plataforma_monitoramento_e_saude.php` (algumas já listadas em getDashboardCountsAdminAcoditools) ---
// ... (getPlataformaStatsUsoGeral, getPlataformaStatsRecursosServidor (mock/externo), getPlataformaLogsErrosCriticosApp, getPlataformaTendenciaLoginsFalhos, funções de verificação de integridade) ...

// --- Funções já existentes (revisar se precisam de adaptação para contexto de Admin da Plataforma) ---
// getModelosAuditoria, getRequisitosAuditoria, etc., já foram adaptadas para terem filtros
// mas precisam garantir que, por padrão, o Admin da Plataforma veja os globais, ou possa filtrar por empresa_id.


// Função auxiliar genérica para buscar/salvar configurações serializadas
// Pode ir para db.php ou aqui mesmo
function getConfigGlobalSerializado(PDO $conexao, string $chave_config, array $valor_default = []): array {
    try {
        $stmt = $conexao->prepare("SELECT config_valor FROM plataforma_configuracoes_globais WHERE config_chave = :chave");
        $stmt->execute([':chave' => $chave_config]);
        $resultado = $stmt->fetchColumn();
        if ($resultado === false) return $valor_default; // Chave não existe, retorna default
        $configArray = json_decode($resultado, true);
        return is_array($configArray) ? $configArray : $valor_default; // Retorna array ou default se JSON inválido
    } catch (PDOException $e) {
        error_log("Erro getConfigGlobalSerializado para chave '$chave_config': " . $e->getMessage());
        return $valor_default;
    }
}

function salvarConfigGlobalSerializado(PDO $conexao, string $chave_config, array $dados_config, int $admin_id_modificador): bool {
    try {
        $valor_json = json_encode($dados_config);
        if ($valor_json === false) {
            error_log("Erro ao serializar JSON para config '$chave_config': " . json_last_error_msg());
            return false;
        }

        // UPSERT: Insere se não existir, atualiza se existir
        $sql = "INSERT INTO plataforma_configuracoes_globais (config_chave, config_valor, modificado_por_admin_id, data_modificacao)
                VALUES (:chave, :valor, :admin_id, NOW())
                ON DUPLICATE KEY UPDATE config_valor = VALUES(config_valor), modificado_por_admin_id = VALUES(modificado_por_admin_id), data_modificacao = NOW()";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':chave' => $chave_config,
            ':valor' => $valor_json,
            ':admin_id' => $admin_id_modificador
        ]);
        // Não podemos confiar no rowCount para UPSERT em todas as versões/configurações do MySQL
        // Vamos assumir sucesso se não houver exceção.
        dbRegistrarLogAcesso($admin_id_modificador, $_SERVER['REMOTE_ADDR'], 'salvar_config_global', 1, "Config: $chave_config atualizada", $conexao);
        return true;
    } catch (PDOException $e) {
        error_log("Erro salvarConfigGlobalSerializado para chave '$chave_config': " . $e->getMessage());
        return false;
    }
}

// includes/admin_functions.php

// Se config.php não incluir db.php, inclua aqui. Mas geralmente config.php centraliza isso.
// require_once __DIR__ . '/db.php';

// =========================================================================
// I. FUNÇÕES PARA DASHBOARD DO ADMIN DA PLATAFORMA
// =========================================================================

/**
 * Obtém contagens resumidas globais para o dashboard do Admin da Acoditools.
 */
function getDashboardCountsAdminAcoditools(PDO $conexao): array {
    $counts = [
        'solicitacoes_acesso_pendentes_globais' => 0,
        'solicitacoes_reset_pendentes_globais' => 0,
        'total_usuarios_admin_plataforma_ativos' => 0,
        'total_usuarios_clientes_ativos' => 0,
        'total_empresas_clientes_ativas' => 0,
        'total_empresas_clientes_teste' => 0,
        'total_empresas_clientes_suspensas' => 0,
        'total_requisitos_globais_ativos' => 0,
        'total_modelos_globais_ativos' => 0,
        'total_planos_assinatura_ativos' => 0,
        'tickets_suporte_abertos' => 0, // Se implementado
    ];

    try {
        // Solicitações globais
        $stmt = $conexao->query("SELECT COUNT(*) FROM solicitacoes_acesso WHERE status = 'pendente'");
        $counts['solicitacoes_acesso_pendentes_globais'] = (int) $stmt->fetchColumn();
        $stmt = $conexao->query("SELECT COUNT(*) FROM solicitacoes_reset_senha WHERE status = 'pendente'");
        $counts['solicitacoes_reset_pendentes_globais'] = (int) $stmt->fetchColumn();

        // Usuários da plataforma
        $stmt = $conexao->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin' AND ativo = 1"); // Assumindo 'admin' para AcodITools
        $counts['total_usuarios_admin_plataforma_ativos'] = (int) $stmt->fetchColumn();
        $stmt = $conexao->query("SELECT COUNT(*) FROM usuarios WHERE perfil != 'admin' AND ativo = 1 AND empresa_id IS NOT NULL");
        $counts['total_usuarios_clientes_ativos'] = (int) $stmt->fetchColumn();

        // Empresas Clientes por status de contrato
        $stmt = $conexao->query("SELECT status_contrato_cliente, COUNT(*) as total FROM empresas GROUP BY status_contrato_cliente");
        $empresasStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $counts['total_empresas_clientes_ativas'] = (int) ($empresasStatus['Ativo'] ?? 0);
        $counts['total_empresas_clientes_teste'] = (int) ($empresasStatus['Teste'] ?? 0);
        $counts['total_empresas_clientes_suspensas'] = (int) ($empresasStatus['Suspenso'] ?? 0);
        // Poderia adicionar outros status se relevante

        // Requisitos e Modelos Globais
        $stmt = $conexao->query("SELECT COUNT(*) FROM requisitos_auditoria WHERE ativo = 1 AND global_ou_empresa_id IS NULL");
        $counts['total_requisitos_globais_ativos'] = (int) $stmt->fetchColumn();
        $stmt = $conexao->query("SELECT COUNT(*) FROM modelos_auditoria WHERE ativo = 1 AND global_ou_empresa_id IS NULL");
        $counts['total_modelos_globais_ativos'] = (int) $stmt->fetchColumn();

        // Planos de Assinatura
        $stmt = $conexao->query("SELECT COUNT(*) FROM plataforma_planos_assinatura WHERE ativo = 1");
        $counts['total_planos_assinatura_ativos'] = (int) $stmt->fetchColumn();

        // Tickets de Suporte (se a tabela existir)
        if ($conexao->query("SHOW TABLES LIKE 'plataforma_tickets_suporte'")->rowCount() > 0) {
            $stmt = $conexao->query("SELECT COUNT(*) FROM plataforma_tickets_suporte WHERE status_ticket = 'Aberto'");
            $counts['tickets_suporte_abertos'] = (int) $stmt->fetchColumn();
        }

    } catch (PDOException $e) {
        error_log("Erro em getDashboardCountsAdminAcoditools: " . $e->getMessage());
    }
    return $counts;
}

/**
 * Obtém a contagem de empresas clientes ativas por plano de assinatura.
 */
function getContagemEmpresasPorPlano(PDO $conexao): array {
    $resultado = [];
    $sql = "SELECT ppa.nome_plano, COUNT(e.id) as total_empresas
            FROM plataforma_planos_assinatura ppa
            LEFT JOIN empresas e ON ppa.id = e.plano_assinatura_id AND e.status_contrato_cliente = 'Ativo' AND e.ativo_na_plataforma = 1
            WHERE ppa.ativo = 1
            GROUP BY ppa.id, ppa.nome_plano
            ORDER BY ppa.nome_plano ASC";
    try {
        $stmt = $conexao->query($sql);
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro em getContagemEmpresasPorPlano: " . $e->getMessage());
    }
    return $resultado;
}

/**
 * Busca alertas relevantes para o Admin da Plataforma.
 */
function getAlertasPlataformaAdmin(PDO $conexao, int $limite = 5): array {
    $alertas = [];
    // Exemplo: Empresas com contrato vencendo nos próximos 30 dias
    $sqlContratos = "SELECT id, nome, data_fim_contrato
                     FROM empresas
                     WHERE status_contrato_cliente = 'Ativo' AND data_fim_contrato BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                     ORDER BY data_fim_contrato ASC
                     LIMIT :limite";
    try {
        $stmt = $conexao->prepare($sqlContratos);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $alertas[] = [
                'tipo' => 'contrato_vencendo',
                'mensagem' => "Contrato da empresa '".htmlspecialchars($row['nome'])."' vence em " . formatarDataSimples($row['data_fim_contrato']) . ".",
                'link' => BASE_URL . 'admin/admin_editar_conta_cliente.php?id=' . $row['id'],
                'data_alerta_formatada' => formatarDataSimples($row['data_fim_contrato'])
            ];
        }
        // Adicionar outras queries para mais alertas (ex: limites de plano excedidos - mais complexo)
    } catch (PDOException $e) {
        error_log("Erro em getAlertasPlataformaAdmin: " . $e->getMessage());
    }
    return $alertas;
}

/**
 * Busca dados para o gráfico de novas empresas clientes por período.
 */
function getNovasContasClientesPorPeriodo(PDO $conexao, string $tipo_periodo = 'mes', int $quantidade_periodos = 6): array {
    $labels = [];
    $data = [];
    $dateFormatGroup = '';
    $dateFormatLabel = '';
    $interval = '';

    switch ($tipo_periodo) {
        case 'dia':
            $dateFormatGroup = '%Y-%m-%d'; $dateFormatLabel = 'd/m'; $interval = "$quantidade_periodos DAY";
            break;
        case 'semana':
            $dateFormatGroup = '%X-%V'; $dateFormatLabel = 'Sem %V/%y'; $interval = "$quantidade_periodos WEEK"; // Semana/Ano
            break;
        case 'mes':
        default:
            $dateFormatGroup = '%Y-%m'; $dateFormatLabel = 'M/y'; $interval = "$quantidade_periodos MONTH";
            break;
    }

    $sql = "SELECT DATE_FORMAT(data_cadastro_plataforma, '$dateFormatGroup') as periodo, COUNT(id) as total
            FROM empresas
            WHERE data_cadastro_plataforma >= DATE_SUB(CURDATE(), INTERVAL $interval)
            GROUP BY periodo
            ORDER BY periodo ASC";
    try {
        $stmt = $conexao->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Preparar labels para todos os períodos no intervalo, mesmo os sem dados
        $periodoCorrente = new DateTime(date('Y-m-01', strtotime("-$quantidade_periodos $tipo_periodo"))); // Início do primeiro período
        if ($tipo_periodo === 'dia') $periodoCorrente = new DateTime(date('Y-m-d', strtotime("-$quantidade_periodos $tipo_periodo")));
        if ($tipo_periodo === 'semana') $periodoCorrente = new DateTime(date('Y-m-d', strtotime("-$quantidade_periodos WEEK")));


        $endDate = new DateTime(date('Y-m-t')); // Fim do mês atual
        if ($tipo_periodo === 'dia') $endDate = new DateTime(date('Y-m-d'));
        if ($tipo_periodo === 'semana') $endDate = new DateTime(date('Y-m-d'));

        $tempData = [];
        while ($periodoCorrente <= $endDate) {
            $labelKey = '';
            if ($tipo_periodo === 'mes') { $labelKey = $periodoCorrente->format('Y-m'); $labels[] = $periodoCorrente->format('M/y');}
            elseif ($tipo_periodo === 'semana') { $labelKey = $periodoCorrente->format('o-W'); $labels[] = "Sem " . $periodoCorrente->format('W/y');} // o para ano ISO
            else { $labelKey = $periodoCorrente->format('Y-m-d'); $labels[] = $periodoCorrente->format('d/m');}
            
            $tempData[$labelKey] = 0;
            $periodoCorrente->modify("+1 $tipo_periodo");
        }

        foreach ($results as $row) {
             $periodoFormatadoResult = '';
             $dtResult = new DateTime($row['periodo'] . ($tipo_periodo === 'mes' ? '-01' : '')); // Adiciona dia 1 para meses
             if($tipo_periodo === 'semana') {
                // Para semana, o formato é YYYY-WW. Precisamos garantir consistência.
                // A query usa %X-%V que é ano-semana.
                $periodoFormatadoResult = $row['periodo']; // Já está no formato 'YYYY-WW'
             } else {
                 $periodoFormatadoResult = $dtResult->format($dateFormatGroup);
             }

            if (isset($tempData[$periodoFormatadoResult])) {
                $tempData[$periodoFormatadoResult] = (int)$row['total'];
            }
        }
        $data = array_values($tempData);
        // Se os labels não foram gerados perfeitamente pelo loop, pegar os que têm dados
        if(count($labels) !== count($data) || empty($labels)){
            $labels = array_keys($tempData); // fallback
             if ($tipo_periodo === 'mes') $labels = array_map(function($d){ return (new DateTime($d.'-01'))->format('M/y');}, $labels);
             if ($tipo_periodo === 'dia') $labels = array_map(function($d){ return (new DateTime($d))->format('d/m');}, $labels);
        }


    } catch (PDOException $e) {
        error_log("Erro em getNovasContasClientesPorPeriodo: " . $e->getMessage());
    }
    return ['labels' => $labels, 'data' => $data];
}


// ... (Outras funções de monitoramento e integridade de dados como sugerido anteriormente) ...


// =========================================================================
// II. FUNÇÕES PARA GESTÃO DE PLANOS DE ASSINATURA (plataforma_gestao_planos_assinatura.php)
// (Implementações como descritas na sua solicitação anterior, adaptadas para este arquivo)
// =========================================================================
// ... criarPlanoAssinatura, listarPlanosAssinaturaPaginado, getPlanoAssinaturaPorId, ...
// ... atualizarPlanoAssinatura, setStatusPlanoAssinatura, excluirPlanoAssinatura ...


// =========================================================================
// III. FUNÇÕES PARA GESTÃO DE CONTAS CLIENTES (admin_gerenciamento_contas_clientes.php, admin_editar_conta_cliente.php)
// (Implementações como descritas na sua solicitação anterior, adaptadas)
// =========================================================================
// ... registrarNovaEmpresaCliente, listarEmpresasClientesPaginado, getEmpresaClientePorId, ...
// ... atualizarDadosEmpresaCliente, mudarStatusContratoEmpresaCliente, verificarCnpjDuplicadoOutraEmpresa, ...
// ... processarUploadLogoEmpresaCliente (esta pode ficar em funcoes_upload.php e ser chamada) ...


// =========================================================================
// IV. FUNÇÕES PARA CONFIGURAÇÕES GLOBAIS DA PLATAFORMA
// (plataforma_config_metodologia_risco.php, plataforma_catalogos_globais.php (se separadas),
//  plataforma_config_workflows_auditoria.php, plataforma_gerenciamento_campos_personalizados.php,
//  plataforma_parametros_globais.php)
// =========================================================================

// --- Funções Genéricas para Configurações Serializadas ---
/**
 * Busca uma configuração global serializada (JSON) do banco de dados.
 */
/**
 * Salva/Atualiza uma configuração global serializada (JSON) no banco de dados.
 */
// ... (Funções CRUD para plataforma_niveis_criticidade_achado) ...
function criarNivelCriticidadeGlobal(PDO $conexao, string $nome, ?string $desc, int $ordem, string $cor, bool $ativo, int $admin_id) { /* ... */ }
function listarNiveisCriticidadeGlobal(PDO $conexao): array {
    try {
        $stmt = $conexao->query("SELECT * FROM plataforma_niveis_criticidade_achado ORDER BY valor_ordenacao ASC, nome_nivel_criticidade ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro em listarNiveisCriticidadeGlobal: " . $e->getMessage());
        return [];
    }
}
function getNivelCriticidadeGlobalPorId(PDO $conexao, int $id){ /* ... */ }
// ... (outras CRUD para Níveis de Criticidade) ...

// ... (Funções CRUD para plataforma_campos_personalizados_definicao) ...
function criarCampoPersonalizadoGlobal(PDO $conexao, array $dadosCampo, int $admin_id) { /* Lógica para validar nome_campo_interno, tipo, etc. */ }
function listarCamposPersonalizadosGlobaisPaginado(PDO $conexao, int $pagina = 1, int $itens_por_pagina = 15): array {
     // Simulação, implementar query real
    return ['campos_personalizados' => [], 'paginacao' => ['total_itens' => 0, 'total_paginas' => 0, 'pagina_atual' => 1]];
}
function getCampoPersonalizadoGlobalPorId(PDO $conexao, int $id) { /* ... */ }
// ... (outras CRUD para Campos Personalizados) ...

// --- Outras Funções herdadas e potencialmente adaptadas ---
// Todas as funções de gerenciamento de `requisitos_auditoria` e `modelos_auditoria`
// já existentes (criar, listar, editar, excluir, adicionar/remover item do modelo)
// agora operam no contexto de "globais da plataforma" quando chamadas pelo Admin da Acoditools.
// A adaptação principal é garantir que, ao criar/editar, o campo `global_ou_empresa_id`
// seja definido como NULL (ou um valor específico para Acoditools se preferir)
// e que o campo `disponibilidade_plano_ids_json` possa ser gerenciado.

// Exemplo de adaptação (para criarRequisitoAuditoria):
/*
function criarRequisitoAuditoriaGlobal(PDO $conexao, array $dados, int $admin_id_plataforma, ?string $disponibilidade_planos_json = null): bool|string {
    // ... validações ...
    $sql = "INSERT INTO requisitos_auditoria
                (codigo, nome, descricao, ..., global_ou_empresa_id, disponibilidade_plano_ids_json, criado_por, modificado_por)
            VALUES
                (:codigo, :nome, ..., NULL, :disp_planos, :criado_por, :modificado_por)";
    // ... bind e execute ...
}
*/

// Todas as funções de gerenciamento de usuários e empresas JÁ foram adaptadas ou criadas
// com a perspectiva SaaS (ex: listarEmpresasClientesPaginado).

/**
 * Cria um novo Plano de Assinatura na plataforma.
 *
 * @param PDO $conexao Conexão com o banco.
 * @param array $dadosPlano Array associativo com os dados do plano.
 *              Esperado: nome_plano, descricao_plano (opcional), preco_mensal (opcional),
 *                        limite_empresas_filhas, limite_gestores_por_empresa, etc.,
 *                        permite_modelos_customizados_empresa, permite_campos_personalizados_empresa, ativo.
 * @return bool|string True em sucesso, string com mensagem de erro em falha.
 */
function criarPlanoAssinatura(PDO $conexao, array $dadosPlano): bool|string {
    if (empty($dadosPlano['nome_plano'])) {
        return "O nome do plano é obrigatório.";
    }

    // Verificar se o nome do plano já existe
    try {
        $stmtCheck = $conexao->prepare("SELECT id FROM plataforma_planos_assinatura WHERE nome_plano = :nome_plano");
        $stmtCheck->execute([':nome_plano' => $dadosPlano['nome_plano']]);
        if ($stmtCheck->fetch()) {
            return "Já existe um plano de assinatura com este nome.";
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar nome duplicado do plano: " . $e->getMessage());
        return "Erro ao verificar dados do plano. Tente novamente.";
    }

    $sql = "INSERT INTO plataforma_planos_assinatura (
                nome_plano, descricao_plano, preco_mensal,
                limite_empresas_filhas, limite_gestores_por_empresa, limite_auditores_por_empresa,
                limite_usuarios_auditados_por_empresa, limite_auditorias_ativas_por_empresa,
                limite_armazenamento_mb_por_empresa,
                permite_modelos_customizados_empresa, permite_campos_personalizados_empresa,
                ativo, data_criacao, data_modificacao
            ) VALUES (
                :nome_plano, :descricao_plano, :preco_mensal,
                :lim_emp_filhas, :lim_gestores, :lim_auditores,
                :lim_auditados, :lim_auditorias, :lim_armazenamento,
                :perm_model_cust, :perm_campos_pers,
                :ativo, NOW(), NOW()
            )";

    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':nome_plano' => $dadosPlano['nome_plano'],
            ':descricao_plano' => $dadosPlano['descricao_plano'] ?: null,
            ':preco_mensal' => $dadosPlano['preco_mensal'] ?: null,
            ':lim_emp_filhas' => $dadosPlano['limite_empresas_filhas'] ?: 0,
            ':lim_gestores' => $dadosPlano['limite_gestores_por_empresa'] ?: 1,
            ':lim_auditores' => $dadosPlano['limite_auditores_por_empresa'] ?: 5,
            ':lim_auditados' => $dadosPlano['limite_usuarios_auditados_por_empresa'] ?: 0,
            ':lim_auditorias' => $dadosPlano['limite_auditorias_ativas_por_empresa'] ?: 10,
            ':lim_armazenamento' => $dadosPlano['limite_armazenamento_mb_por_empresa'] ?: 1024,
            ':perm_model_cust' => (int)($dadosPlano['permite_modelos_customizados_empresa'] ?? 0),
            ':perm_campos_pers' => (int)($dadosPlano['permite_campos_personalizados_empresa'] ?? 0),
            ':ativo' => (int)($dadosPlano['ativo'] ?? 1)
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao criar plano de assinatura: " . $e->getMessage());
        return "Erro de banco de dados ao criar o plano.";
    }
}

/**
 * Lista os planos de assinatura com paginação.
 *
 * @param PDO $conexao
 * @param int $pagina
 * @param int $itens_por_pagina
 * @param bool $apenas_ativos_param Se true, lista apenas planos ativos.
 * @return array Estrutura com ['planos' => [], 'paginacao' => [...]]
 */
function listarPlanosAssinaturaPaginado(PDO $conexao, int $pagina = 1, int $itens_por_pagina = 10, bool $apenas_ativos_param = false): array {
    $offset = ($pagina - 1) * $itens_por_pagina;
    $params = [];
    $where_clauses = [];

    if ($apenas_ativos_param) {
        $where_clauses[] = "ativo = 1";
    }

    $sql_where = "";
    if (!empty($where_clauses)) {
        $sql_where = " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql_select = "SELECT SQL_CALC_FOUND_ROWS * FROM plataforma_planos_assinatura " . $sql_where . " ORDER BY nome_plano ASC LIMIT :limit OFFSET :offset";

    try {
        $stmt = $conexao->prepare($sql_select);
        // Bind dos parâmetros de limite e offset
        $stmt->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        // Bind de outros parâmetros de filtro, se houver (ex: :ativo se $apenas_ativos_param)
        // if ($apenas_ativos_param) { $stmt->bindValue(':ativo', 1, PDO::PARAM_INT); }


        $stmt->execute();
        $planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_itens = (int) $conexao->query("SELECT FOUND_ROWS()")->fetchColumn();
        $total_paginas = ($itens_por_pagina > 0 && $total_itens > 0) ? ceil($total_itens / $itens_por_pagina) : 0;

        return [
            'planos' => $planos,
            'paginacao' => [
                'pagina_atual' => $pagina,
                'total_paginas' => $total_paginas,
                'total_itens' => $total_itens,
                'itens_por_pagina' => $itens_por_pagina
            ]
        ];
    } catch (PDOException $e) {
        error_log("Erro em listarPlanosAssinaturaPaginado: " . $e->getMessage());
        return ['planos' => [], 'paginacao' => ['pagina_atual' => 1, 'total_paginas' => 0, 'total_itens' => 0, 'itens_por_pagina' => $itens_por_pagina]];
    }
}


/**
 * Busca um plano de assinatura específico pelo ID.
 */
function getPlanoAssinaturaPorId(PDO $conexao, int $plano_id): ?array {
    try {
        $stmt = $conexao->prepare("SELECT * FROM plataforma_planos_assinatura WHERE id = :id");
        $stmt->execute([':id' => $plano_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Erro em getPlanoAssinaturaPorId ID $plano_id: " . $e->getMessage());
        return null;
    }
}

/**
 * Atualiza um plano de assinatura existente.
 */
function atualizarPlanoAssinatura(PDO $conexao, int $plano_id, array $dadosPlano, int $admin_id_modificador): bool|string {
    if (empty($dadosPlano['nome_plano'])) {
        return "O nome do plano é obrigatório.";
    }

    // Verificar se o nome do plano já existe em OUTRO plano
    try {
        $stmtCheck = $conexao->prepare("SELECT id FROM plataforma_planos_assinatura WHERE nome_plano = :nome_plano AND id != :plano_id");
        $stmtCheck->execute([':nome_plano' => $dadosPlano['nome_plano'], ':plano_id' => $plano_id]);
        if ($stmtCheck->fetch()) {
            return "Já existe outro plano de assinatura com este nome.";
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar nome duplicado do plano na atualização: " . $e->getMessage());
        return "Erro ao verificar dados do plano. Tente novamente.";
    }

    $sql = "UPDATE plataforma_planos_assinatura SET
                nome_plano = :nome_plano,
                descricao_plano = :descricao_plano,
                preco_mensal = :preco_mensal,
                limite_empresas_filhas = :lim_emp_filhas,
                limite_gestores_por_empresa = :lim_gestores,
                limite_auditores_por_empresa = :lim_auditores,
                limite_usuarios_auditados_por_empresa = :lim_auditados,
                limite_auditorias_ativas_por_empresa = :lim_auditorias,
                limite_armazenamento_mb_por_empresa = :lim_armazenamento,
                permite_modelos_customizados_empresa = :perm_model_cust,
                permite_campos_personalizados_empresa = :perm_campos_pers,
                ativo = :ativo,
                data_modificacao = NOW()
            WHERE id = :plano_id";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':nome_plano' => $dadosPlano['nome_plano'],
            ':descricao_plano' => $dadosPlano['descricao_plano'] ?: null,
            ':preco_mensal' => $dadosPlano['preco_mensal'] ?: null,
            ':lim_emp_filhas' => $dadosPlano['limite_empresas_filhas'] ?: 0,
            ':lim_gestores' => $dadosPlano['limite_gestores_por_empresa'] ?: 1,
            ':lim_auditores' => $dadosPlano['limite_auditores_por_empresa'] ?: 5,
            ':lim_auditados' => $dadosPlano['limite_usuarios_auditados_por_empresa'] ?: 0,
            ':lim_auditorias' => $dadosPlano['limite_auditorias_ativas_por_empresa'] ?: 10,
            ':lim_armazenamento' => $dadosPlano['limite_armazenamento_mb_por_empresa'] ?: 1024,
            ':perm_model_cust' => (int)($dadosPlano['permite_modelos_customizados_empresa'] ?? 0),
            ':perm_campos_pers' => (int)($dadosPlano['permite_campos_personalizados_empresa'] ?? 0),
            ':ativo' => (int)($dadosPlano['ativo'] ?? 1),
            ':plano_id' => $plano_id
        ]);
        // rowCount pode ser 0 se nenhum dado mudou, mas a query foi sucesso.
        // Para ser mais preciso, você pode comparar os dados antes e depois.
        // Por simplicidade, se não houver exceção, consideramos sucesso.
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao atualizar plano de assinatura ID $plano_id: " . $e->getMessage());
        return "Erro de banco de dados ao atualizar o plano.";
    }
}

/**
 * Define o status (ativo/inativo) de um Plano de Assinatura.
 */
function setStatusPlanoAssinatura(PDO $conexao, int $plano_id, bool $ativo): bool {
    try {
        $stmt = $conexao->prepare("UPDATE plataforma_planos_assinatura SET ativo = :ativo, data_modificacao = NOW() WHERE id = :id");
        $stmt->execute([':ativo' => (int)$ativo, ':id' => $plano_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro em setStatusPlanoAssinatura ID $plano_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Exclui um Plano de Assinatura, verificando se não está em uso.
 */
function excluirPlanoAssinatura(PDO $conexao, int $plano_id): bool|string {
    try {
        // Verificar se alguma empresa está usando este plano
        $stmtCheck = $conexao->prepare("SELECT COUNT(*) FROM empresas WHERE plano_assinatura_id = :plano_id");
        $stmtCheck->execute([':plano_id' => $plano_id]);
        if ($stmtCheck->fetchColumn() > 0) {
            return "Este plano está sendo utilizado por uma ou mais empresas clientes e não pode ser excluído. Considere desativá-lo.";
        }

        $stmt = $conexao->prepare("DELETE FROM plataforma_planos_assinatura WHERE id = :id");
        $stmt->execute([':id' => $plano_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao excluir plano de assinatura ID $plano_id: " . $e->getMessage());
        return "Erro de banco de dados ao excluir o plano.";
    }
}


// -------------------------------------------------------------------------
// II. FUNÇÕES GENÉRICAS PARA CONFIGURAÇÕES GLOBAIS SERIALIZADAS
// (Suporte para plataforma_parametros_globais.php, plataforma_config_metodologia_risco.php, etc.)
// -------------------------------------------------------------------------
