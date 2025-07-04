<?php
// includes/admin_functions.php

// --- Funções para Gestão de Usuários ---


/**
 * Obtém os dados de um único usuário pelo ID.
 */
function getUsuario(PDO $conexao, int $id): ?array {
    $sql = "SELECT
                u.id,
                u.nome,
                u.email,
                u.perfil,
                u.ativo,
                u.data_cadastro,
                u.foto,
                u.empresa_id,
                e.nome as nome_empresa,         -- Nome da empresa associada
                u.is_empresa_admin_cliente,     -- Flag para gestor principal da empresa cliente
                u.especialidade_auditor,        -- Especialidades do auditor
                u.certificacoes_auditor,        -- Certificações do auditor
                u.primeiro_acesso,              -- Para saber se o usuário precisa redefinir senha
                u.data_ultimo_login             -- Data do último login
            FROM usuarios u
            LEFT JOIN empresas e ON u.empresa_id = e.id -- LEFT JOIN para funcionar mesmo se empresa_id for NULL (admin da plataforma)
            WHERE u.id = :id";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $usuario ?: null; // Retorna o array do usuário ou null se não encontrado
    } catch (PDOException $e) {
        error_log("Erro em getUsuario para ID $id: " . $e->getMessage());
        return null; // Retorna null em caso de erro de banco
    }
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

function getSolicitacoesAcessoPendentes(PDO $conexao): array {
    $sql = "SELECT sa.id, sa.nome_completo, sa.email, sa.motivo, sa.data_solicitacao, sa.empresa_id, e.nome as empresa_nome, sa.perfil_solicitado
            FROM solicitacoes_acesso sa
            JOIN empresas e ON sa.empresa_id = e.id
            WHERE sa.status = 'pendente'
            ORDER BY sa.data_solicitacao ASC"; // Mais antigas primeiro
    try {
        $stmt = $conexao->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro em getSolicitacoesAcessoPendentes: " . $e->getMessage());
        return [];
    }
}

// Obter os dados da solicitação por ID.

function getSolicitacaoAcesso($conexao, $solicitacao_id){
    // Adicionado perfil_solicitado se existir na sua tabela
    $sql = "SELECT sa.*, e.nome as nome_empresa
            FROM solicitacoes_acesso sa
            JOIN empresas e ON sa.empresa_id = e.id
            WHERE sa.id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$solicitacao_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC); // Retorna array ou false se não encontrado
}

function getSolicitacaoReset(PDO $conexao, int $solicitacao_id): ?array {
    $sql = "SELECT sr.id, sr.usuario_id, sr.status, sr.data_solicitacao,
                   u.nome as nome_usuario, u.email as email_usuario
            FROM solicitacoes_reset_senha sr
            JOIN usuarios u ON sr.usuario_id = u.id
            WHERE sr.id = :id";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([':id' => $solicitacao_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Erro em getSolicitacaoReset ID $solicitacao_id: " . $e->getMessage());
        return null;
    }
}

function aprovarSolicitacaoAcesso(PDO $conexao, int $solicitacao_id, string $senha_temporaria): bool|string {
    $conexao->beginTransaction();
    try {
        $solicitacao = getSolicitacaoAcesso($conexao, $solicitacao_id); // Sua função existente
        if (!$solicitacao || $solicitacao['status'] !== 'pendente') {
            $conexao->rollBack();
            return "Solicitação de acesso inválida ou já processada (ID: $solicitacao_id).";
        }

        // Perfil e empresa são determinados pela solicitação
        $perfil_novo_usuario = $solicitacao['perfil_solicitado']; // da tabela solicitacoes_acesso
        $empresa_id_novo_usuario = $solicitacao['empresa_id'];   // da tabela solicitacoes_acesso

        // Validar se o perfil e empresa_id são consistentes
        $perfis_clientes_validos = ['gestor_empresa', 'auditor_empresa', 'auditado_contato'];
        if (!in_array($perfil_novo_usuario, $perfis_clientes_validos)) {
            $conexao->rollBack();
            return "Perfil solicitado ('".htmlspecialchars($perfil_novo_usuario)."') é inválido para um novo usuário cliente.";
        }
        if (empty($empresa_id_novo_usuario)) {
            $conexao->rollBack();
            return "A empresa não foi especificada na solicitação.";
        }

        // Verificar se o email já existe na tabela usuarios (caso raro, mas possível)
        $stmtCheckEmail = $conexao->prepare("SELECT id FROM usuarios WHERE email = :email");
        $stmtCheckEmail->execute([':email' => $solicitacao['email']]);
        if($stmtCheckEmail->fetch()){
            $conexao->rollBack();
            // Rejeitar a solicitação, pois o e-mail já existe.
            rejeitarSolicitacaoAcesso($conexao, $solicitacao_id, "Usuário já cadastrado com este e-mail.");
            return "Usuário já cadastrado com o e-mail fornecido. Solicitação rejeitada.";
        }


        $senha_hash = password_hash($senha_temporaria, PASSWORD_DEFAULT);
        $sql_criar_usuario = "INSERT INTO usuarios (nome, email, senha, perfil, ativo, empresa_id, primeiro_acesso, data_cadastro)
                              VALUES (:nome, :email, :senha, :perfil, 1, :empresa_id, 1, NOW())";
        $stmt_criar_usuario = $conexao->prepare($sql_criar_usuario);
        $stmt_criar_usuario->execute([
            ':nome' => $solicitacao['nome_completo'],
            ':email' => $solicitacao['email'],
            ':senha' => $senha_hash,
            ':perfil' => $perfil_novo_usuario,
            ':empresa_id' => $empresa_id_novo_usuario
        ]);

        $sql_update_solicitacao = "UPDATE solicitacoes_acesso
                                   SET status = 'aprovada',
                                       admin_id_processou = :admin_id,
                                       data_aprovacao_rejeicao = NOW()
                                   WHERE id = :solicitacao_id";
        $stmt_update_solicitacao = $conexao->prepare($sql_update_solicitacao);
        $stmt_update_solicitacao->execute([
            ':admin_id' => $_SESSION['usuario_id'],
            ':solicitacao_id' => $solicitacao_id
        ]);

        $conexao->commit();
        return true;

    } catch (PDOException $e) {
        if ($conexao->inTransaction()) $conexao->rollBack();
        error_log("Erro PDO ao aprovar solicitação ID $solicitacao_id: " . $e->getMessage());
        return "Erro de banco de dados ao aprovar solicitação.";
    } catch (Exception $e) { // Para exceções customizadas
        if ($conexao->inTransaction()) $conexao->rollBack();
        error_log("Erro ao aprovar solicitação ID $solicitacao_id: " . $e->getMessage());
        return $e->getMessage(); // Retorna a mensagem de erro da exceção
    }
}


function aprovarESetarSenhaTemp(PDO $conexao, int $solicitacao_id, string $senha_temporaria, int $admin_id): bool {
    $conexao->beginTransaction();
    try {
        // 1. Obter o usuario_id da solicitação
        $stmtSol = $conexao->prepare("SELECT usuario_id FROM solicitacoes_reset_senha WHERE id = :sol_id AND status = 'pendente'");
        $stmtSol->execute([':sol_id' => $solicitacao_id]);
        $usuario_id_reset = $stmtSol->fetchColumn();

        if (!$usuario_id_reset) {
            $conexao->rollBack();
            error_log("aprovarESetarSenhaTemp: Solicitação ID $solicitacao_id não encontrada ou não pendente.");
            return false;
        }
        $usuario_id_reset = (int)$usuario_id_reset;

        // 2. Redefinir a senha do usuário e marcar para primeiro acesso
        // Usar a função redefinirSenha se ela já faz isso, ou embutir a lógica aqui.
        // A sua função redefinirSenha já faz o hash e seta primeiro_acesso = 1.
        if (!redefinirSenha($conexao, $usuario_id_reset, $senha_temporaria)) {
             // redefinirSenha já loga erro, mas podemos adicionar contexto.
            error_log("aprovarESetarSenhaTemp: Falha ao chamar redefinirSenha para usuário ID $usuario_id_reset (Solicitação ID $solicitacao_id).");
            throw new Exception("Falha ao atualizar a senha do usuário.");
        }

        // 3. Atualizar o status da solicitação de reset
        // `admin_id_aprovou` é o nome correto da coluna na tabela `solicitacoes_reset_senha`
        $sql_update_reset = "UPDATE solicitacoes_reset_senha
                             SET status = 'aprovada',
                                 admin_id_aprovou = :admin_id,
                                 data_aprovacao = NOW(),
                                 token_reset = NULL, -- Limpar token se não for mais usado
                                 data_expiracao_token = NULL
                             WHERE id = :solicitacao_id";
        $stmt_update_reset = $conexao->prepare($sql_update_reset);
        $stmt_update_reset->execute([
            ':admin_id' => $admin_id,
            ':solicitacao_id' => $solicitacao_id
        ]);

        $conexao->commit();
        return true;

    } catch (Exception $e) {
        if ($conexao->inTransaction()) $conexao->rollBack();
        error_log("Erro em aprovarESetarSenhaTemp para Solicitação ID $solicitacao_id: " . $e->getMessage());
        return false;
    }
}

function rejeitarSolicitacaoAcesso(PDO $conexao, int $solicitacao_id, string $observacoes = ''): bool {
   // Adicionado 'admin_id_processou'
   $sql = "UPDATE solicitacoes_acesso SET status = 'rejeitada', admin_id_processou = :admin_id, data_aprovacao_rejeicao = NOW(), observacoes_admin = :obs WHERE id = :id";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':admin_id' => $_SESSION['usuario_id'], // Assume que está na sessão
            ':obs' => $observacoes,
            ':id' => $solicitacao_id
        ]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro em rejeitarSolicitacaoAcesso ID $solicitacao_id: " . $e->getMessage());
        return false;
    }
}



// --- Funções para Solicitações de Reset de Senha ---

function getSolicitacoesResetPendentes(PDO $conexao): array {
    $sql = "SELECT sr.id, sr.data_solicitacao,
                   u.nome AS nome_usuario, u.email as email_usuario, u.empresa_id as empresa_id_usuario,
                   e.nome AS nome_empresa_usuario
            FROM solicitacoes_reset_senha sr
            JOIN usuarios u ON sr.usuario_id = u.id
            LEFT JOIN empresas e ON u.empresa_id = e.id  -- LEFT JOIN para caso o usuário seja admin sem empresa
            WHERE sr.status = 'pendente'
            ORDER BY sr.data_solicitacao ASC";
    try {
        $stmt = $conexao->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro em getSolicitacoesResetPendentes: " . $e->getMessage());
        return [];
    }
}

//Rejeitar solicitação de reset.
function rejeitarSolicitacaoReset(PDO $conexao, int $solicitacao_id, string $observacoes = ''): bool {
    // Usar `admin_id_aprovou` para o admin que rejeitou, e `data_aprovacao` para data da ação.
    // Ou criar colunas específicas `admin_id_rejeitou`, `data_rejeicao`.
    // Vou usar as existentes para simplificar, mas ajuste conforme sua tabela.
    $sql = "UPDATE solicitacoes_reset_senha
            SET status = 'rejeitada',
                admin_id_aprovou = :admin_id, -- Reutilizando ou crie admin_id_rejeitou
                data_aprovacao = NOW(),       -- Reutilizando ou crie data_rejeicao
                observacoes_admin = :obs,
                token_reset = NULL,
                data_expiracao_token = NULL
            WHERE id = :id";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':admin_id' => $_SESSION['usuario_id'],
            ':obs' => $observacoes,
            ':id' => $solicitacao_id
        ]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro em rejeitarSolicitacaoReset ID $solicitacao_id: " . $e->getMessage());
        return false;
    }
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
    string $filtro_status = 'todos', // 'todos', 'ativos', 'inativos'
    string $termo_busca = '',
    ?int $filtro_empresa_id = null, // Para Admin SaaS, será sempre NULL para modelos globais
    ?int $filtro_categoria_id = null // NOVO filtro
): array {
    $offset = ($pagina_atual - 1) * $itens_por_pagina;
    $params = [];

    // Admin da plataforma só gerencia modelos com global_ou_empresa_id IS NULL
    $sql_base_where = " WHERE m.global_ou_empresa_id IS NULL ";

    $where_clauses = [];

    if ($filtro_status === 'ativos') {
        $where_clauses[] = "m.ativo = 1";
    } elseif ($filtro_status === 'inativos') {
        $where_clauses[] = "m.ativo = 0";
    }

    if (!empty($termo_busca)) {
        $where_clauses[] = "(m.nome LIKE :busca OR m.descricao LIKE :busca)";
        $params[':busca'] = '%' . $termo_busca . '%';
    }
    
    if ($filtro_categoria_id !== null && $filtro_categoria_id > 0) {
        $where_clauses[] = "m.tipo_modelo_id = :categoria_id";
        $params[':categoria_id'] = $filtro_categoria_id;
    }

    $sql_where_conditions = "";
    if (!empty($where_clauses)) {
        $sql_where_conditions = implode(' AND ', $where_clauses);
    }
    
    // Combina o filtro base com os condicionais
    $final_where_clause = $sql_base_where . ($sql_where_conditions ? ' AND ' . $sql_where_conditions : '');


    $sql_select = "SELECT SQL_CALC_FOUND_ROWS
                     m.id, m.nome, m.descricao, m.ativo, m.versao_modelo, m.data_criacao,
                     pcm.nome_categoria_modelo,
                     (SELECT COUNT(*) FROM modelo_itens mi WHERE mi.modelo_id = m.id) as total_itens
                   FROM modelos_auditoria m
                   LEFT JOIN plataforma_categorias_modelo pcm ON m.tipo_modelo_id = pcm.id
                   " . $final_where_clause; // Usa a cláusula WHERE combinada

    $sql_order_limit = " ORDER BY m.nome ASC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $itens_por_pagina;
    $params[':offset'] = $offset;

    $sql = $sql_select . $sql_order_limit;

    try {
        $stmt = $conexao->prepare($sql);
        foreach ($params as $key => &$val) {
            $stmt->bindValue($key, $val, (is_int($val) || $key === ':limit' || $key === ':offset' || $key === ':categoria_id') ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        unset($val);

        $stmt->execute();
        $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql_count = "SELECT COUNT(m.id) FROM modelos_auditoria m LEFT JOIN plataforma_categorias_modelo pcm ON m.tipo_modelo_id = pcm.id " . $final_where_clause;
        $stmt_count = $conexao->prepare($sql_count);
        // Re-bind dos parâmetros para contagem (sem limit/offset)
        $params_count = $params;
        unset($params_count[':limit'], $params_count[':offset']);
        foreach ($params_count as $key => &$val) {
             $stmt_count->bindValue($key, $val, (is_int($val) && $key !== ':busca' || $key === ':categoria_id') ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        unset($val);
        $stmt_count->execute();
        $total_itens = (int) $stmt_count->fetchColumn();

    } catch (PDOException $e) {
        error_log("Erro em getModelosAuditoria: " . $e->getMessage() . " SQL: $sql Params: " . print_r($params,true));
        return ['modelos' => [], 'paginacao' => ['pagina_atual' => 1, 'total_paginas' => 0, 'total_itens' => 0, 'itens_por_pagina' => $itens_por_pagina]];
    }

    return [
        'modelos' => $modelos,
        'paginacao' => [
            'pagina_atual' => $pagina_atual,
            'total_paginas' => ceil($total_itens / $itens_por_pagina),
            'total_itens' => $total_itens,
            'itens_por_pagina' => $itens_por_pagina
        ]
    ];
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
    // Validação básica (nome e descrição já validados na página, mas bom ter aqui)
    if (empty($dados['nome']) || empty($dados['descricao'])) {
        return "Nome e Descrição são obrigatórios.";
    }
    if (!isset($dados['peso']) || !is_numeric($dados['peso']) || (int)$dados['peso'] < 0) {
        return "Peso/Impacto inválido.";
    }

    // Verificar código único (se fornecido e não vazio)
    if (!empty($dados['codigo'])) {
         try {
            $stmtCheck = $conexao->prepare("SELECT COUNT(*) FROM requisitos_auditoria WHERE codigo = :codigo AND global_ou_empresa_id IS NULL"); // Só checa em globais
            $stmtCheck->bindParam(':codigo', $dados['codigo']);
            $stmtCheck->execute();
            if ($stmtCheck->fetchColumn() > 0) {
                return "O código '".htmlspecialchars($dados['codigo'])."' já está em uso por outro requisito global.";
            }
         } catch (PDOException $e) { /* Ignora erro aqui, a constraint pegaria */ }
    }

    // Converter array de IDs de plano para JSON
    $planos_json = null;
    if (!empty($dados['disponibilidade_planos_ids']) && is_array($dados['disponibilidade_planos_ids'])) {
        $planos_json = json_encode(array_map('intval', $dados['disponibilidade_planos_ids']));
        if ($planos_json === false) { // Erro na codificação JSON
            error_log("Erro ao codificar JSON de planos para novo requisito: " . json_last_error_msg());
            return "Erro interno ao processar a disponibilidade de planos.";
        }
    }


    $sql = "INSERT INTO requisitos_auditoria (
                codigo, nome, descricao, categoria, norma_referencia,
                versao_norma_aplicavel, data_ultima_revisao_norma,
                guia_evidencia, objetivo_controle, tecnicas_sugeridas,
                peso, ativo, global_ou_empresa_id, disponibilidade_plano_ids_json,
                criado_por, modificado_por, data_criacao, data_modificacao
            ) VALUES (
                :codigo, :nome, :descricao, :categoria, :norma_referencia,
                :versao_norma, :data_revisao_norma,
                :guia_evidencia, :objetivo_controle, :tecnicas_sugeridas,
                :peso, :ativo, NULL, :planos_json, -- global_ou_empresa_id é NULL
                :criado_por, :modificado_por, NOW(), NOW()
            )";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindValue(':codigo', empty($dados['codigo']) ? null : $dados['codigo']);
        $stmt->bindParam(':nome', $dados['nome']);
        $stmt->bindParam(':descricao', $dados['descricao']);
        $stmt->bindValue(':categoria', empty($dados['categoria']) ? null : $dados['categoria']);
        $stmt->bindValue(':norma_referencia', empty($dados['norma_referencia']) ? null : $dados['norma_referencia']);
        $stmt->bindValue(':versao_norma', empty($dados['versao_norma_aplicavel']) ? null : $dados['versao_norma_aplicavel']);
        $stmt->bindValue(':data_revisao_norma', empty($dados['data_ultima_revisao_norma']) ? null : $dados['data_ultima_revisao_norma']);
        $stmt->bindValue(':guia_evidencia', empty($dados['guia_evidencia']) ? null : $dados['guia_evidencia']);
        $stmt->bindValue(':objetivo_controle', empty($dados['objetivo_controle']) ? null : $dados['objetivo_controle']);
        $stmt->bindValue(':tecnicas_sugeridas', empty($dados['tecnicas_sugeridas']) ? null : $dados['tecnicas_sugeridas']);
        $stmt->bindValue(':peso', (int)$dados['peso'], PDO::PARAM_INT);
        $stmt->bindValue(':ativo', $dados['ativo'] ?? 1, PDO::PARAM_INT);
        $stmt->bindValue(':planos_json', $planos_json); // Pode ser NULL
        $stmt->bindValue(':criado_por', $usuario_id, PDO::PARAM_INT);
        $stmt->bindValue(':modificado_por', $usuario_id, PDO::PARAM_INT);

        return $stmt->execute();

    } catch (PDOException $e) {
        error_log("Erro em criarRequisitoAuditoria: " . $e->getMessage() . " DADOS: " . print_r($dados, true));
         if ($e->errorInfo[1] == 1062) { // Código de erro para UNIQUE constraint (MySQL)
             if (str_contains($e->getMessage(), 'codigo')) return "O código '".htmlspecialchars($dados['codigo'])."' já está em uso.";
         }
        return "Erro inesperado ao salvar o requisito no banco de dados.";
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
     if (!isset($dados['peso']) || !is_numeric($dados['peso']) || (int)$dados['peso'] < 0) {
        return "Peso/Impacto inválido.";
    }

    if (!empty($dados['codigo'])) {
         try {
            $stmtCheck = $conexao->prepare("SELECT COUNT(*) FROM requisitos_auditoria WHERE codigo = :codigo AND id != :id AND global_ou_empresa_id IS NULL");
            $stmtCheck->bindParam(':codigo', $dados['codigo']);
            $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtCheck->execute();
            if ($stmtCheck->fetchColumn() > 0) {
                return "O código '".htmlspecialchars($dados['codigo'])."' já está em uso por outro requisito global.";
            }
         } catch (PDOException $e) { /* Ignora */ }
    }
    
    $planos_json_update = null;
    if (!empty($dados['disponibilidade_planos_ids']) && is_array($dados['disponibilidade_planos_ids'])) {
        $planos_json_update = json_encode(array_map('intval', $dados['disponibilidade_planos_ids']));
         if ($planos_json_update === false) {
            error_log("Erro ao codificar JSON de planos para atualizar requisito ID $id: " . json_last_error_msg());
            return "Erro interno ao processar a disponibilidade de planos para atualização.";
        }
    } elseif (isset($dados['disponibilidade_plano_ids_json'])) { // Se já veio como JSON (ex: da edição)
        $planos_json_update = $dados['disponibilidade_plano_ids_json'];
    }


    $sql = "UPDATE requisitos_auditoria SET
                codigo = :codigo, nome = :nome, descricao = :descricao, categoria = :categoria, norma_referencia = :norma_referencia,
                versao_norma_aplicavel = :versao_norma, data_ultima_revisao_norma = :data_revisao_norma,
                guia_evidencia = :guia_evidencia, objetivo_controle = :objetivo_controle, tecnicas_sugeridas = :tecnicas_sugeridas,
                peso = :peso, ativo = :ativo, global_ou_empresa_id = NULL, disponibilidade_plano_ids_json = :planos_json,
                modificado_por = :modificado_por
                -- data_modificacao é ON UPDATE CURRENT_TIMESTAMP
            WHERE id = :id";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', empty($dados['codigo']) ? null : $dados['codigo']);
        $stmt->bindParam(':nome', $dados['nome']);
        $stmt->bindParam(':descricao', $dados['descricao']);
        $stmt->bindValue(':categoria', empty($dados['categoria']) ? null : $dados['categoria']);
        $stmt->bindValue(':norma_referencia', empty($dados['norma_referencia']) ? null : $dados['norma_referencia']);
        $stmt->bindValue(':versao_norma', empty($dados['versao_norma_aplicavel']) ? null : $dados['versao_norma_aplicavel']);
        $stmt->bindValue(':data_revisao_norma', empty($dados['data_ultima_revisao_norma']) ? null : $dados['data_ultima_revisao_norma']);
        $stmt->bindValue(':guia_evidencia', empty($dados['guia_evidencia']) ? null : $dados['guia_evidencia']);
        $stmt->bindValue(':objetivo_controle', empty($dados['objetivo_controle']) ? null : $dados['objetivo_controle']);
        $stmt->bindValue(':tecnicas_sugeridas', empty($dados['tecnicas_sugeridas']) ? null : $dados['tecnicas_sugeridas']);
        $stmt->bindValue(':peso', (int)$dados['peso'], PDO::PARAM_INT);
        $stmt->bindValue(':ativo', $dados['ativo'] ?? 1, PDO::PARAM_INT);
        $stmt->bindValue(':planos_json', $planos_json_update);
        $stmt->bindValue(':modificado_por', $usuario_id, PDO::PARAM_INT);

        $stmt->execute();
        // rowCount() pode ser 0 se os dados não mudaram, mas a query foi sucesso.
        return true;

    } catch (PDOException $e) {
        error_log("Erro em atualizarRequisitoAuditoria ID $id: " . $e->getMessage() . " DADOS: " . print_r($dados, true));
         if ($e->errorInfo[1] == 1062) {
             if (str_contains($e->getMessage(), 'codigo')) return "O código '".htmlspecialchars($dados['codigo'])."' já está em uso.";
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
        return [];
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
function atualizarModeloAuditoria(PDO $conexao, int $id, array $dados_modelo, int $admin_id): bool|string {
    if (empty($dados_modelo['nome'])) {
        return "O nome do modelo é obrigatório.";
    }
    // Adicionar mais validações para os novos campos aqui

    // Verificar nome duplicado em OUTROS modelos globais
    try {
        $stmtCheck = $conexao->prepare("SELECT id FROM modelos_auditoria WHERE nome = :nome AND global_ou_empresa_id IS NULL AND id != :id");
        $stmtCheck->execute([':nome' => $dados_modelo['nome'], ':id' => $id]);
        if ($stmtCheck->fetch()) {
            return "Já existe outro modelo global com este nome.";
        }
    } catch (PDOException $e) { /* ... log e erro ... */ return "Erro ao verificar nome."; }

    $disponibilidade_planos_json_update = null;
    if (!empty($dados_modelo['disponibilidade_planos_ids']) && is_array($dados_modelo['disponibilidade_planos_ids'])) {
        $ids_int_upd = array_map('intval', $dados_modelo['disponibilidade_planos_ids']);
        $ids_int_filtrados_upd = array_values(array_filter($ids_int_upd, function($id_plano){ return $id_plano > 0; }));
        $disponibilidade_planos_json_update = !empty($ids_int_filtrados_upd) ? json_encode($ids_int_filtrados_upd) : null;
    }

    $sql = "UPDATE modelos_auditoria SET
                nome = :nome,
                descricao = :descricao,
                tipo_modelo_id = :tipo_modelo_id,
                versao_modelo = :versao_modelo,
                data_ultima_revisao_modelo = :data_ultima_revisao,
                proxima_revisao_sugerida_modelo = :proxima_revisao_sugerida,
                disponibilidade_plano_ids_json = :planos_json,
                permite_copia_cliente = :permite_copia,
                ativo = :ativo,
                modificado_por = :admin_id
                -- global_ou_empresa_id não muda aqui (sempre NULL para globais)
                -- data_modificacao é ON UPDATE CURRENT_TIMESTAMP
            WHERE id = :id AND global_ou_empresa_id IS NULL"; // Segurança extra
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':nome' => $dados_modelo['nome'],
            ':descricao' => $dados_modelo['descricao'] ?: null,
            ':tipo_modelo_id' => $dados_modelo['tipo_modelo_id'] ?: null,
            ':versao_modelo' => $dados_modelo['versao_modelo'] ?: '1.0',
            ':data_ultima_revisao' => $dados_modelo['data_ultima_revisao_modelo'] ?: null,
            ':proxima_revisao_sugerida' => $dados_modelo['proxima_revisao_sugerida_modelo'] ?: null,
            ':planos_json' => $disponibilidade_planos_json_update,
            ':permite_copia' => (int)($dados_modelo['permite_copia_cliente'] ?? 0),
            ':ativo' => (int)($dados_modelo['ativo'] ?? 1),
            ':admin_id' => $admin_id,
            ':id' => $id
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao atualizar modelo ID $id: " . $e->getMessage() . " Dados: " . print_r($dados_modelo, true));
        if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) { return "Já existe um modelo com este nome (erro DB)."; }
        return "Erro de banco de dados ao atualizar o modelo.";
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
    $sql = "SELECT m.*, pcm.nome_categoria_modelo
            FROM modelos_auditoria m
            LEFT JOIN plataforma_categorias_modelo pcm ON m.tipo_modelo_id = pcm.id
            WHERE m.id = :id AND m.global_ou_empresa_id IS NULL"; // Apenas modelos globais
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([':id' => $id]);
        $modelo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($modelo) {
            // Decodificar o JSON de planos para o formulário
            $modelo['disponibilidade_planos_ids_array'] = json_decode($modelo['disponibilidade_plano_ids_json'] ?? '[]', true);
            if (!is_array($modelo['disponibilidade_planos_ids_array'])) {
                $modelo['disponibilidade_planos_ids_array'] = [];
            }
        }
        return $modelo ?: null;
    } catch (PDOException $e) {
        error_log("Erro em getModeloAuditoria ID $id: " . $e->getMessage());
        return null;
    }
}

function criarUsuarioPlataforma(PDO $conexao, string $nome, string $email, string $senha_inicial, string $perfil, int $ativo, ?int $empresa_id, bool $is_empresa_admin, ?string $especialidade, ?string $certificacoes, int $criado_por_admin_id): bool|array|string {
    // ... (validações de duplicação de e-mail, etc., como em aprovarSolicitacaoAcesso) ...

    $senha_hash = password_hash($senha_inicial, PASSWORD_DEFAULT);
    $sql = "INSERT INTO usuarios (nome, email, senha, perfil, ativo, empresa_id, is_empresa_admin_cliente, especialidade_auditor, certificacoes_auditor, primeiro_acesso, data_cadastro)
            VALUES (:nome, :email, :senha, :perfil, :ativo, :empresa_id, :is_admin_cli, :espec, :cert, 1, NOW())";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':nome' => $nome, ':email' => $email, ':senha' => $senha_hash,
            ':perfil' => $perfil, ':ativo' => $ativo, ':empresa_id' => $empresa_id,
            ':is_admin_cli' => (int)$is_empresa_admin,
            ':espec' => ($perfil === 'auditor_empresa' ? $especialidade : null),
            ':cert' => ($perfil === 'auditor_empresa' ? $certificacoes : null)
            // `:criado_por_admin_id` não está na sua tabela usuarios, mas seria bom ter um log de quem criou
        ]);
        $novo_id = $conexao->lastInsertId();
        return ['success' => true, 'id' => (int)$novo_id];
    } catch (PDOException $e) {
        error_log("Erro ao criar usuário: " . $e->getMessage());
        if ($e->errorInfo[1] == 1062) { // Código de erro para entrada duplicada (MySQL)
            return "Este e-mail já está cadastrado.";
        }
        return "Erro de banco de dados ao criar usuário.";
    }
}

/**
 * Obtém a lista de usuários do banco de dados com filtros adicionais para Admin da Plataforma.
 */
function getUsuarios(PDO $conexao, $excluir_admin_id = null, $pagina = 1, $itens_por_pagina = 10, array $filtros = []) {
    $offset = ($pagina - 1) * $itens_por_pagina;
    $sql_select = "SELECT SQL_CALC_FOUND_ROWS
                     u.id, u.nome, u.email, u.perfil, u.ativo, u.data_cadastro, u.foto,
                     u.empresa_id, e.nome as nome_empresa,
                     u.is_empresa_admin_cliente, u.especialidade_auditor, u.certificacoes_auditor, u.data_ultimo_login
                   FROM usuarios u
                   LEFT JOIN empresas e ON u.empresa_id = e.id";

    $where_clauses = [];
    $params = [];

    if ($excluir_admin_id !== null) {
        $where_clauses[] = "u.id != :excluir_admin_id";
        $params[':excluir_admin_id'] = (int)$excluir_admin_id;
    }

    // Aplicar filtros do array $filtros
    // Filtro por TIPO DE USUÁRIO
    if (isset($filtros['tipo_usuario']) && $filtros['tipo_usuario'] !== 'todos') {
        if ($filtros['tipo_usuario'] === 'plataforma') {
            $where_clauses[] = "(u.perfil = 'admin' AND u.empresa_id IS NULL)"; // Admin da AcodITools
        } elseif ($filtros['tipo_usuario'] === 'clientes') {
            $where_clauses[] = "u.empresa_id IS NOT NULL";
            // Se o tipo for 'clientes', verificar se há filtro de empresa_id específico
            if (!empty($filtros['empresa_id'])) {
                $where_clauses[] = "u.empresa_id = :filtro_empresa_id";
                $params[':filtro_empresa_id'] = (int)$filtros['empresa_id'];
            }
        }
    }

    // Filtro por PERFIL ESPECÍFICO (só se aplica se tipo_usuario não for 'plataforma' ou 'clientes' de forma restritiva, ou se for 'todos')
    // Se 'tipo_usuario' já filtrou (ex: 'plataforma' só pode ser perfil 'admin'), este filtro pode ser redundante ou complementar.
    if (!empty($filtros['perfil'])) {
        $perfis_validos_db = ['admin', 'gestor_empresa', 'auditor_empresa', 'auditado_contato'];
        if (in_array($filtros['perfil'], $perfis_validos_db)) {
             $where_clauses[] = "u.perfil = :filtro_perfil";
             $params[':filtro_perfil'] = $filtros['perfil'];
        }
    }

    // Filtro por STATUS (Ativo/Inativo)
    // Importante: isset() verifica se a chave existe, e $filtros['status_ativo'] !== null permite que 0 (inativo) seja um filtro válido.
    if (isset($filtros['status_ativo']) && $filtros['status_ativo'] !== null && $filtros['status_ativo'] !== '') {
        $where_clauses[] = "u.ativo = :filtro_status_ativo";
        $params[':filtro_status_ativo'] = (int)$filtros['status_ativo'];
    }

    // Filtro por TERMO DE BUSCA
    if (!empty($filtros['termo_busca'])) {
        $where_clauses[] = "(u.nome LIKE :termo_busca OR u.email LIKE :termo_busca)";
        $params[':termo_busca'] = '%' . $filtros['termo_busca'] . '%';
    }

    $sql_where = "";
    if (!empty($where_clauses)) {
        $sql_where = " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql_order_limit = " ORDER BY u.nome ASC LIMIT :limit OFFSET :offset";
    $params[':limit'] = (int)$itens_por_pagina;
    $params[':offset'] = (int)$offset;

    $sql = $sql_select . $sql_where . $sql_order_limit;

    try {
        $stmt = $conexao->prepare($sql);
        // Bind dos parâmetros
        // É crucial que as chaves em $params coincidam com os placeholders na query
        // E que o tipo seja correto com bindValue
        if (isset($params[':excluir_admin_id'])) $stmt->bindValue(':excluir_admin_id', $params[':excluir_admin_id'], PDO::PARAM_INT);
        if (isset($params[':filtro_empresa_id'])) $stmt->bindValue(':filtro_empresa_id', $params[':filtro_empresa_id'], PDO::PARAM_INT);
        if (isset($params[':filtro_perfil'])) $stmt->bindValue(':filtro_perfil', $params[':filtro_perfil'], PDO::PARAM_STR);
        if (isset($params[':filtro_status_ativo'])) $stmt->bindValue(':filtro_status_ativo', $params[':filtro_status_ativo'], PDO::PARAM_INT);
        if (isset($params[':termo_busca'])) $stmt->bindValue(':termo_busca', $params[':termo_busca'], PDO::PARAM_STR);

        $stmt->bindValue(':limit', $params[':limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $params[':offset'], PDO::PARAM_INT);

        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Contagem total para paginação (com os mesmos filtros WHERE)
        $sql_count = "SELECT COUNT(*) FROM usuarios u LEFT JOIN empresas e ON u.empresa_id = e.id" . $sql_where;
        $stmt_count = $conexao->prepare($sql_count);

        // Re-bind dos parâmetros para a contagem (exceto limit e offset)
        if (isset($params[':excluir_admin_id'])) $stmt_count->bindValue(':excluir_admin_id', $params[':excluir_admin_id'], PDO::PARAM_INT);
        if (isset($params[':filtro_empresa_id'])) $stmt_count->bindValue(':filtro_empresa_id', $params[':filtro_empresa_id'], PDO::PARAM_INT);
        if (isset($params[':filtro_perfil'])) $stmt_count->bindValue(':filtro_perfil', $params[':filtro_perfil'], PDO::PARAM_STR);
        if (isset($params[':filtro_status_ativo'])) $stmt_count->bindValue(':filtro_status_ativo', $params[':filtro_status_ativo'], PDO::PARAM_INT);
        if (isset($params[':termo_busca'])) $stmt_count->bindValue(':termo_busca', $params[':termo_busca'], PDO::PARAM_STR);

        $stmt_count->execute();
        $total_usuarios = (int) $stmt_count->fetchColumn();

        $total_paginas = ($itens_por_pagina > 0 && $total_usuarios > 0) ? ceil($total_usuarios / $itens_por_pagina) : 0;
        if ($total_paginas == 0 && $total_usuarios > 0) $total_paginas = 1;

    } catch (PDOException $e) {
        error_log("Erro em getUsuarios: " . $e->getMessage() . " SQL: $sql Params: " . print_r($params, true));
        $usuarios = [];
        $total_usuarios = 0;
        $total_paginas = 0;
    }

    return [
        'usuarios' => $usuarios,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_usuarios' => $total_usuarios,
            'total_itens' => $total_usuarios
        ]
    ];
}
/**
 * Atualiza os dados de um usuário no banco de dados.
 * Adaptação: Permitir definir `is_empresa_admin_cliente` e campos de especialidade/certificação.
 */
function atualizarUsuario(PDO $conexao, int $id, string $nome, string $email, string $perfil, int $ativo, ?int $empresa_id, bool $is_empresa_admin_cliente = false, ?string $especialidade_auditor = null, ?string $certificacoes_auditor = null): bool {
    // Validar perfil
    $perfis_validos_db = ['admin', 'gestor_empresa', 'auditor_empresa', 'auditado_contato'];
    if (!in_array($perfil, $perfis_validos_db)) {
        error_log("Tentativa de definir perfil inválido '$perfil' para usuário ID $id.");
        return false; // Perfil inválido não deve ser salvo.
    }

    // Se o perfil for 'admin' (da AcodITools), empresa_id DEVE ser NULL.
    if ($perfil === 'admin') {
        $empresa_id = null;
    } elseif ($empresa_id === null && in_array($perfil, ['gestor_empresa', 'auditor_empresa', 'auditado_contato'])) {
        // Perfis de cliente PRECISAM de empresa_id
        error_log("Tentativa de definir perfil de cliente ($perfil) sem empresa_id para usuário ID $id.");
        return false;
    }


    $sql = "UPDATE usuarios SET
                nome = :nome,
                email = :email,
                perfil = :perfil,
                ativo = :ativo,
                empresa_id = :empresa_id,
                is_empresa_admin_cliente = :is_empresa_admin,
                especialidade_auditor = :especialidade,
                certificacoes_auditor = :certificacoes
                -- data_modificacao é atualizada automaticamente pelo SGBD (ON UPDATE CURRENT_TIMESTAMP)
            WHERE id = :id";

    $params = [
        ':nome' => $nome,
        ':email' => $email,
        ':perfil' => $perfil,
        ':ativo' => $ativo,
        ':empresa_id' => $empresa_id, // PDO trata NULL corretamente
        ':is_empresa_admin' => (int)$is_empresa_admin_cliente, // Campo da tabela usuarios
        ':especialidade' => ($perfil === 'auditor_empresa' ? $especialidade_auditor : null), // Salva apenas se for auditor
        ':certificacoes' => ($perfil === 'auditor_empresa' ? $certificacoes_auditor : null), // Salva apenas se for auditor
        ':id' => $id
    ];

    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute($params);
        // rowCount() pode ser 0 se os dados forem os mesmos.
        // Para edição, sucesso significa que a query executou sem erro.
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao atualizar usuário ID $id: " . $e->getMessage() . " Params: " . print_r($params, true));
        return false;
    }
}

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

    // Definir formatos
    $dateFormatGroup = $tipo_periodo === 'mes' ? '%Y-%m' : '%Y-%m-%d';
    $dateFormatLabel = $tipo_periodo === 'mes' ? 'M/y' : 'd/m';

    // Calcular datas inicial e final
    $endDate = new DateTime('today');
    $startDate = (clone $endDate)->modify("-$quantidade_periodos $tipo_periodo");
    if ($tipo_periodo === 'mes') {
        $startDate->modify('first day of this month');
    }

    // Query SQL
    $sql = "SELECT DATE_FORMAT(data_cadastro_plataforma, '$dateFormatGroup') as periodo, COUNT(id) as total
            FROM empresas
            WHERE data_cadastro_plataforma >= :start_date AND data_cadastro_plataforma <= :end_date
            GROUP BY periodo
            ORDER BY periodo ASC";

    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate->format('Y-m-d'),
            ':end_date' => $endDate->format('Y-m-d')
        ]);
        $tempData = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tempData[$row['periodo']] = (int)$row['total'];
        }
    } catch (PDOException $e) {
        return ['labels' => [], 'data' => []];
    }

    // Gerar labels e dados com DatePeriod
    $interval = new DateInterval($tipo_periodo === 'mes' ? 'P1M' : 'P1D');
    $period = new DatePeriod($startDate, $interval, $endDate);

    foreach ($period as $date) {
        $labelKey = $date->format($tipo_periodo === 'mes' ? 'Y-m' : 'Y-m-d');
        $labels[] = $date->format($dateFormatLabel);
        $data[] = $tempData[$labelKey] ?? 0;
    }

    return ['labels' => $labels, 'data' => $data];
}

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

/**
 * Cria um novo Plano de Assinatura na plataforma.
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

/**
 * Lista os planos de assinatura. Pode filtrar por ativos.
 * Esta é uma versão mais simples, sem paginação, ideal para dropdowns.
 * Se precisar de paginação para uma tela de gerenciamento de planos, crie listarPlanosAssinaturaPaginado.
 */
function listarPlanosAssinatura(PDO $conexao, bool $apenas_ativos = false): array {
    $sql = "SELECT id, nome_plano, preco_mensal, ativo FROM plataforma_planos_assinatura";
    $params = [];

    if ($apenas_ativos) {
        $sql .= " WHERE ativo = :ativo";
        $params[':ativo'] = 1;
    }

    $sql .= " ORDER BY nome_plano ASC";

    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro em listarPlanosAssinatura: " . $e->getMessage());
        return []; // Retorna array vazio em caso de erro
    }
}

/**
 * Conta o número de Tipos de Não Conformidade globais ativos.
 */
function contarTiposNaoConformidadeGlobal(PDO $conexao): int {
    try {
        $stmt = $conexao->query("SELECT COUNT(*) FROM plataforma_tipos_nao_conformidade WHERE ativo = 1");
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erro ao contar Tipos de NC: " . $e->getMessage());
        return 0; // Retorna 0 em caso de erro para evitar quebra da página
    }
}

/**
 * Conta o número de Níveis de Criticidade globais ativos.
 */
function contarNiveisCriticidadeGlobal(PDO $conexao): int {
    try {
        $stmt = $conexao->query("SELECT COUNT(*) FROM plataforma_niveis_criticidade_achado WHERE ativo = 1");
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erro ao contar Níveis de Criticidade: " . $e->getMessage());
        return 0; // Retorna 0 em caso de erro para evitar quebra da página
    }
}

/**
 * Busca a configuração global da metodologia de risco.
 */
function getConfigMetodologiaRiscoGlobal(PDO $conexao): array {
    try {
        $stmt = $conexao->prepare("SELECT config_valor FROM plataforma_configuracoes_globais WHERE config_chave = :chave");
        $stmt->execute([':chave' => 'metodologia_risco']);
        $configJson = $stmt->fetchColumn();
        if ($configJson) {
            $config = json_decode($configJson, true, 512, JSON_THROW_ON_ERROR);
            return is_array($config) ? $config : [];
        }
    } catch (PDOException | JsonException $e) {
        error_log("Erro ao buscar metodologia de risco: " . $e->getMessage());
    }
    return [
        'tipo_calculo_risco' => 'Matricial',
        'escala_impacto_labels' => ['Baixo', 'Médio', 'Alto'],
        'escala_impacto_valores' => [1, 2, 3],
        'escala_probabilidade_labels' => ['Rara', 'Possível', 'Frequente'],
        'escala_probabilidade_valores' => [1, 2, 3],
        'matriz_risco_definicao' => [],
        'niveis_risco_resultado_labels' => ['Baixo', 'Médio', 'Alto'],
        'niveis_risco_cores_hex' => ['#28a745', '#ffc107', '#dc3545']
    ];
}

/**
 * Salva a configuração global da metodologia de risco.
 */
function salvarConfigMetodologiaRiscoGlobal(PDO $conexao, array $configRisco, int $adminId): bool {
    try {
        $configJson = json_encode($configRisco, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $sql = "INSERT INTO plataforma_configuracoes_globais 
                (config_chave, config_valor, descricao_config, modificado_por_admin_id, data_modificacao)
                VALUES (:chave, :valor, :descricao, :admin_id, NOW())
                ON DUPLICATE KEY UPDATE 
                config_valor = :valor_update, 
                descricao_config = :descricao_update, 
                modificado_por_admin_id = :admin_id_update, 
                data_modificacao = NOW()";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':chave' => 'metodologia_risco',
            ':valor' => $configJson,
            ':descricao' => 'Configuração global da metodologia de avaliação de riscos',
            ':admin_id' => $adminId,
            ':valor_update' => $configJson,
            ':descricao_update' => 'Configuração global da metodologia de avaliação de riscos',
            ':admin_id_update' => $adminId
        ]);
        return true;
    } catch (PDOException | JsonException $e) {
        error_log("Erro ao salvar metodologia de risco: " . $e->getMessage());
        return false;
    }
}

/**
 * Lista campos personalizados globais.
 */
function listarCamposPersonalizadosGlobaisPaginado(PDO $conexao): array {
    try {
        $sql = "SELECT id, nome_campo_interno, label_campo_exibicao, tipo_campo, 
                       aplicavel_a_entidade_db, disponivel_para_planos_ids_db, 
                       opcoes_lista_db, obrigatorio, ativo, placeholder_campo, texto_ajuda_campo
                FROM plataforma_campos_personalizados
                ORDER BY id ASC";
        $stmt = $conexao->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: [];
    } catch (PDOException $e) {
        error_log("Erro ao listar campos personalizados: " . $e->getMessage());
        return [];
    }
}

/**
 * Busca um campo personalizado por ID.
 */
function getCampoPersonalizadoGlobalPorId(PDO $conexao, int $id): ?array {
    try {
        $stmt = $conexao->prepare("SELECT id, nome_campo_interno, label_campo_exibicao, tipo_campo, 
                                          aplicavel_a_entidade_db, disponivel_para_planos_ids_db, 
                                          opcoes_lista_db, obrigatorio, ativo, placeholder_campo, texto_ajuda_campo
                                   FROM plataforma_campos_personalizados WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Erro ao buscar campo personalizado ID $id: " . $e->getMessage());
        return null;
    }
}

/**
 * Cria um novo campo personalizado global.
 */
function criarCampoPersonalizadoGlobal(PDO $conexao, array $dados, int $adminId) {
    try {
        $stmt = $conexao->prepare("INSERT INTO plataforma_campos_personalizados 
            (nome_campo_interno, label_campo_exibicao, tipo_campo, aplicavel_a_entidade_db, 
             disponivel_para_planos_ids_db, opcoes_lista_db, obrigatorio, ativo, 
             placeholder_campo, texto_ajuda_campo, criado_por_admin_id, data_criacao)
            VALUES (:nome, :label, :tipo, :aplicavel, :planos, :opcoes, :obrigatorio, :ativo, 
                    :placeholder, :texto_ajuda, :admin_id, NOW())");
        $stmt->execute([
            ':nome' => $dados['nome_campo_interno'],
            ':label' => $dados['label_campo_exibicao'],
            ':tipo' => $dados['tipo_campo'],
            ':aplicavel' => $dados['aplicavel_a_entidade_db'],
            ':planos' => $dados['disponivel_para_planos_ids_db'],
            ':opcoes' => $dados['opcoes_lista_db'],
            ':obrigatorio' => $dados['obrigatorio'],
            ':ativo' => $dados['ativo'],
            ':placeholder' => $dados['placeholder_campo'],
            ':texto_ajuda' => $dados['texto_ajuda_campo'],
            ':admin_id' => $adminId
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao criar campo personalizado: " . $e->getMessage());
        return "Erro ao salvar no banco de dados: " . $e->getMessage();
    }
}

/**
 * Atualiza um campo personalizado global.
 */
function atualizarCampoPersonalizadoGlobal(PDO $conexao, int $id, array $dados, int $adminId) {
    try {
        $stmt = $conexao->prepare("UPDATE plataforma_campos_personalizados 
            SET label_campo_exibicao = :label, tipo_campo = :tipo, aplicavel_a_entidade_db = :aplicavel, 
                disponivel_para_planos_ids_db = :planos, opcoes_lista_db = :opcoes, obrigatorio = :obrigatorio, 
                ativo = :ativo, placeholder_campo = :placeholder, texto_ajuda_campo = :texto_ajuda, 
                modificado_por_admin_id = :admin_id, data_modificacao = NOW()
            WHERE id = :id");
        $stmt->execute([
            ':label' => $dados['label_campo_exibicao'],
            ':tipo' => $dados['tipo_campo'],
            ':aplicavel' => $dados['aplicavel_a_entidade_db'],
            ':planos' => $dados['disponivel_para_planos_ids_db'],
            ':opcoes' => $dados['opcoes_lista_db'],
            ':obrigatorio' => $dados['obrigatorio'],
            ':ativo' => $dados['ativo'],
            ':placeholder' => $dados['placeholder_campo'],
            ':texto_ajuda' => $dados['texto_ajuda_campo'],
            ':admin_id' => $adminId,
            ':id' => $id
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao atualizar campo personalizado ID $id: " . $e->getMessage());
        return "Erro ao atualizar no banco de dados: " . $e->getMessage();
    }
}

/**
 * Obtém estatísticas gerais de uso da plataforma.
 */
function getPlataformaStatsUsoGeral(PDO $conexao): array {
    try {
        $stats = [
            'total_empresas_ativas_contrato' => 0,
            'total_usuarios_ativos_plataforma' => 0,
            'auditorias_criadas_ultimos_30d' => 0,
            'auditorias_em_andamento_total' => 0,
            'planos_acao_abertos_total' => 0,
            'modelos_globais_mais_usados' => []
        ];

        // Total de empresas ativas
        $stmt = $conexao->query("SELECT COUNT(*) AS total FROM empresas WHERE status = 'ATIVA' AND plano_assinatura_id IS NOT NULL");
        $stats['total_empresas_ativas_contrato'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total de usuários ativos
        $stmt = $conexao->query("SELECT COUNT(*) AS total FROM usuarios WHERE ativo = 1");
        $stats['total_usuarios_ativos_plataforma'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Auditorias criadas nos últimos 30 dias
        $stmt = $conexao->prepare("SELECT COUNT(*) AS total FROM auditorias WHERE data_criacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $stats['auditorias_criadas_ultimos_30d'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Auditorias em andamento
        $stmt = $conexao->query("SELECT COUNT(*) AS total FROM auditorias WHERE status = 'EM_ANDAMENTO'");
        $stats['auditorias_em_andamento_total'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Planos de ação abertos
        $stmt = $conexao->query("SELECT COUNT(*) AS total FROM planos_acao WHERE status = 'ABERTO'");
        $stats['planos_acao_abertos_total'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Modelos globais mais usados
        $stmt = $conexao->query("
            SELECT m.id, m.nome_modelo, COUNT(a.id) AS usos
            FROM modelos_auditoria m
            LEFT JOIN auditorias a ON a.modelo_id = m.id
            WHERE m.ativo = 1
            GROUP BY m.id, m.nome_modelo
            ORDER BY usos DESC
            LIMIT 5
        ");
        $stats['modelos_globais_mais_usados'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    } catch (PDOException $e) {
        error_log("Erro ao obter stats de uso: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtém estatísticas de recursos do servidor.
 */
function getPlataformaStatsRecursosServidor(): array {
    $stats = [
        'uso_cpu_percentual_atual' => 'N/D', // Não Disponível por padrão
        'uso_memoria_percentual_atual' => 'N/D',
        'uso_disco_uploads_gb_total' => 'N/D',
        'uso_disco_db_gb_total' => 'N/D',
        'media_tempo_resposta_ms' => rand(50, 250) // Simulado, idealmente viria de logs ou APM
    ];

    // Tenta obter dados se for Linux e funções não estiverem desabilitadas
    if (PHP_OS_FAMILY === 'Linux') {
        // --- CPU Load Average (não é exatamente % de uso, mas um indicador) ---
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load && isset($load[0])) {
                // Para tentar converter para algo parecido com porcentagem, precisaríamos do número de cores.
                // A chamada `exec('nproc')` pode ser desabilitada.
                $num_cores = 1; // Default para evitar divisão por zero
                if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
                     $nproc_output = @shell_exec('nproc'); // Tenta obter número de processadores
                     if (is_numeric(trim($nproc_output))) {
                         $num_cores = (int)trim($nproc_output);
                     }
                }
                // A interpretação de load average para % de uso é complexa.
                // Uma aproximação muito grosseira seria (load[0] / num_cores) * 100.
                // Mas load average pode exceder num_cores * 100%.
                // Vamos exibir o load average de 1 minuto diretamente ou um placeholder.
                // $stats['uso_cpu_percentual_atual'] = round(($load[0] / $num_cores) * 100);
                // $stats['uso_cpu_percentual_atual'] = min(100, $stats['uso_cpu_percentual_atual']); // Cap em 100%
                $stats['uso_cpu_percentual_atual'] = number_format($load[0], 2) . " (LA 1min)"; // Mostra o Load Average
            }
        }

        // --- Uso de Memória (lendo /proc/meminfo) ---
        if (is_readable('/proc/meminfo')) {
            $meminfo_content = @file_get_contents('/proc/meminfo');
            if ($meminfo_content) {
                $matches_total = [];
                $matches_available = []; // Em kernels mais novos, MemAvailable é melhor que MemFree + Buffers/Cache
                
                preg_match('/^MemTotal:\s+(\d+)\s*kB/im', $meminfo_content, $matches_total);
                preg_match('/^MemAvailable:\s+(\d+)\s*kB/im', $meminfo_content, $matches_available);

                if (!empty($matches_total[1]) && !empty($matches_available[1])) {
                    $mem_total_kb = (float)$matches_total[1];
                    $mem_available_kb = (float)$matches_available[1];
                    if ($mem_total_kb > 0) {
                        $mem_used_kb = $mem_total_kb - $mem_available_kb;
                        $stats['uso_memoria_percentual_atual'] = round(($mem_used_kb / $mem_total_kb) * 100);
                    }
                } else {
                    // Fallback para MemFree se MemAvailable não existir (kernels antigos)
                    $matches_free = [];
                    $matches_buffers = [];
                    $matches_cached = [];
                    preg_match('/^MemFree:\s+(\d+)\s*kB/im', $meminfo_content, $matches_free);
                    preg_match('/^Buffers:\s+(\d+)\s*kB/im', $meminfo_content, $matches_buffers);
                    preg_match('/^Cached:\s+(\d+)\s*kB/im', $meminfo_content, $matches_cached); // Cached (page cache)
                    // Algumas versões de /proc/meminfo têm SReclaimable ou Shmem para cache mais detalhado
                    
                    if (!empty($matches_total[1]) && !empty($matches_free[1])) {
                        $mem_total_kb = (float)$matches_total[1];
                        $mem_free_kb = (float)$matches_free[1];
                        $mem_buffers_kb = isset($matches_buffers[1]) ? (float)$matches_buffers[1] : 0;
                        $mem_cached_kb = isset($matches_cached[1]) ? (float)$matches_cached[1] : 0;
                        // Uso de memória = Total - (Livre + Buffers + Cache) (aproximação)
                        $mem_used_approx_kb = $mem_total_kb - ($mem_free_kb + $mem_buffers_kb + $mem_cached_kb);
                        if ($mem_total_kb > 0 && $mem_used_approx_kb >=0) {
                             $stats['uso_memoria_percentual_atual'] = round(($mem_used_approx_kb / $mem_total_kb) * 100);
                        }
                    }
                }
            }
        }
    }

    // --- Uso de Disco para Uploads ---
    // UPLOADS_BASE_PATH_ABSOLUTE precisa estar definido em config.php
    if (defined('UPLOADS_BASE_PATH_ABSOLUTE') && is_dir(UPLOADS_BASE_PATH_ABSOLUTE) && is_readable(UPLOADS_BASE_PATH_ABSOLUTE)) {
        try {
            // `disk_free_space` e `disk_total_space` referem-se à partição onde o diretório está.
            // Para o tamanho *usado pela pasta de uploads*, teríamos que iterar ou usar `du` (shell_exec).
            // Iterar pode ser MUITO lento. Vamos usar o espaço total/livre da partição como referência.
            
            $disk_total_bytes = @disk_total_space(UPLOADS_BASE_PATH_ABSOLUTE);
            $disk_free_bytes = @disk_free_space(UPLOADS_BASE_PATH_ABSOLUTE);

            if ($disk_total_bytes !== false && $disk_free_bytes !== false && $disk_total_bytes > 0) {
                $disk_used_bytes = $disk_total_bytes - $disk_free_bytes;
                // $stats['uso_disco_uploads_percentual'] = round(($disk_used_bytes / $disk_total_bytes) * 100); // % da partição
                // O que você tinha era o tamanho total dos uploads, que é diferente.
                // Para obter o tamanho *da pasta de uploads*, precisaríamos de algo como:
                $folder_size_bytes = 0;
                if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
                    // Tenta usar 'du'. CUIDADO: Segurança e performance.
                    // Certifique-se que UPLOADS_BASE_PATH_ABSOLUTE é um caminho seguro.
                    $path_escaped = escapeshellarg(rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/'));
                    $du_output = @shell_exec("du -sb " . $path_escaped); // -s para summary, -b para bytes
                    if ($du_output && preg_match('/^(\d+)/', $du_output, $matches_du)) {
                        $folder_size_bytes = (float)$matches_du[1];
                    }
                }
                // Se `du` não funcionar ou não estiver disponível, uma iteração recursiva seria o fallback,
                // mas pode ser muito lenta. Por ora, vamos deixar com `du` ou N/D.
                if ($folder_size_bytes > 0) {
                    $stats['uso_disco_uploads_gb_total'] = number_format($folder_size_bytes / (1024 * 1024 * 1024), 2);
                } else {
                    $stats['uso_disco_uploads_gb_total'] = 'N/D (du falhou)';
                }

            }
        } catch (Exception $e) {
            error_log("Erro ao calcular espaço em disco para uploads: " . $e->getMessage());
        }
    }


    // --- Uso de Disco para Banco de Dados (MySQL/MariaDB) ---
    global $conexao; // Precisa da conexão PDO global
    if (isset($conexao)) {
        try {
            // Tenta obter o nome do banco de dados da string de conexão DSN se não definido.
            $dbName = DB_NAME; // Definido em config.php

            if ($dbName) {
                $stmt = $conexao->prepare(
                    "SELECT table_schema AS db_name,
                    SUM(data_length + index_length) / 1024 / 1024 AS size_mb
                    FROM information_schema.tables
                    WHERE table_schema = :db_name
                    GROUP BY table_schema"
                );
                $stmt->bindParam(':db_name', $dbName, PDO::PARAM_STR);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && isset($result['size_mb'])) {
                    $stats['uso_disco_db_gb_total'] = number_format((float)$result['size_mb'] / 1024, 2); // Convertendo MB para GB
                }
            }
        } catch (PDOException $e) {
            error_log("Erro ao obter tamanho do banco de dados: " . $e->getMessage());
            // Não sobrescreve o 'N/D' se falhar
        }
    }

    // Limpar 'N/D' se o valor foi numérico 0 para CPU e Memória (significa que a leitura funcionou mas deu 0)
    if (is_numeric($stats['uso_cpu_percentual_atual'])) {
         // Mantém o valor numérico, mesmo que 0. Se for 'N/D (LA 1min)', não mexe.
    } else if ($stats['uso_cpu_percentual_atual'] === 'N/D' && strpos($stats['uso_cpu_percentual_atual'], '(LA 1min)') === false) {
        // Caso onde era 'N/D' e não foi atualizado para valor numérico ou LA
    }

    if (is_numeric($stats['uso_memoria_percentual_atual'])) {
        // Mantém, já é um número
    }

    return $stats;
}
/**
 * Obtém logs de erros críticos da aplicação.
 */
function getPlataformaLogsErrosCriticosApp(PDO $conexao, int $dias = 7): array {
    try {
        $stmt = $conexao->prepare("
            SELECT tipo_erro, mensagem_erro, arquivo_origem, data_erro
            FROM logs_erros
            WHERE data_erro >= DATE_SUB(NOW(), INTERVAL :dias DAY)
            ORDER BY data_erro DESC
            LIMIT 10
        ");
        $stmt->execute([':dias' => $dias]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter logs de erros: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtém tendência de falhas de login.
 */
function getPlataformaTendenciaLoginsFalhos(PDO $conexao, int $dias = 30): array {
    try {
        // Verificar se a tabela logs_acesso existe
        $stmt = $conexao->query("SHOW TABLES LIKE 'logs_acesso'");
        if ($stmt->rowCount() === 0) {
            error_log("Tabela logs_acesso não encontrada.");
            return ['labels' => [], 'data_falhas' => []];
        }

        $stmt = $conexao->prepare("
            SELECT DATE(created_at) AS dia, COUNT(*) AS total
            FROM logs_acesso
            WHERE sucesso = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL :dias DAY)
            GROUP BY dia
            ORDER BY dia ASC
        ");
        $stmt->execute([':dias' => $dias]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Preparar dados para o gráfico
        $labels = [];
        $data_falhas = [];
        $start_date = new DateTime("-{$dias} days");
        $end_date = new DateTime();
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

        $falhas_por_dia = array_column($result, 'total', 'dia');
        foreach ($period as $date) {
            $dia = $date->format('Y-m-d');
            $labels[] = $date->format('d/m');
            $data_falhas[] = isset($falhas_por_dia[$dia]) ? (int)$falhas_por_dia[$dia] : 0;
        }

        return [
            'labels' => $labels,
            'data_falhas' => $data_falhas
        ];
    } catch (PDOException $e) {
        error_log("Erro ao obter tendência de logins falhos: " . $e->getMessage());
        return ['labels' => [], 'data_falhas' => []];
    }
}

/**
 * Obtém verificações de integridade de dados.
 */
function getIntegridadeDados(PDO $conexao): array {
    try {
        $integridade = [
            'auditorias_sem_responsavel' => 0,
            'itens_sem_requisito_valido' => 0,
            'empresas_sem_plano_ativo' => 0
        ];

        // Verificar se a tabela auditorias existe
        $stmt = $conexao->query("SHOW TABLES LIKE 'auditorias'");
        if ($stmt->rowCount() === 0) {
            error_log("Tabela auditorias não encontrada.");
            return $integridade;
        }

        // Auditorias sem responsável
        $stmt = $conexao->query("
            SELECT COUNT(*) AS total
            FROM auditorias
            WHERE status = 'EM_ANDAMENTO' AND auditor_id IS NULL
        ");
        $integridade['auditorias_sem_responsavel'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Itens de auditoria órfãos
        $stmt = $conexao->query("
            SELECT COUNT(*) AS total
            FROM itens_auditoria ia
            JOIN auditorias a ON ia.auditoria_id = a.id
            LEFT JOIN requisitos r ON ia.requisito_id = r.id
            WHERE a.status = 'EM_ANDAMENTO' AND (r.id IS NULL OR r.ativo = 0)
        ");
        $integridade['itens_sem_requisito_valido'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Empresas sem plano ativo
        $stmt = $conexao->query("
            SELECT COUNT(*) AS total
            FROM empresas e
            LEFT JOIN planos_assinatura p ON e.plano_assinatura_id = p.id
            WHERE e.status = 'ATIVA' AND (p.id IS NULL OR p.ativo = 0)
        ");
        $integridade['empresas_sem_plano_ativo'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        return $integridade;
    } catch (PDOException $e) {
        error_log("Erro ao obter integridade de dados: " . $e->getMessage());
        return [
            'auditorias_sem_responsavel' => 0,
            'itens_sem_requisito_valido' => 0,
            'empresas_sem_plano_ativo' => 0
        ];
    }
}

/**
 * Define o status (ativo/inativo) de um Comunicado da Plataforma.
 */
function setStatusComunicadoPlataforma(PDO $conexao, int $comunicado_id, bool $ativo, int $admin_id_modificador): bool {
    try {
        // Assumindo que sua tabela tem 'data_modificacao' com ON UPDATE CURRENT_TIMESTAMP
        $sql = "UPDATE plataforma_comunicados SET ativo = :ativo, modificado_por_admin_id = :admin_id WHERE id = :id";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':ativo' => (int)$ativo,
            ':admin_id' => $admin_id_modificador,
            ':id' => $comunicado_id
        ]);
        $acao_log = $ativo ? 'ativar_comunicado_plataforma' : 'desativar_comunicado_plataforma';
        dbRegistrarLogAcesso($admin_id_modificador, $_SERVER['REMOTE_ADDR'], $acao_log, 1, "Comunicado ID: $comunicado_id", $conexao);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro em setStatusComunicadoPlataforma ID $comunicado_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Exclui um Comunicado da Plataforma.
 */
function excluirComunicadoPlataforma(PDO $conexao, int $comunicado_id, int $admin_id_acao): bool|string {
    try {
        // Não há dependências fortes típicas, mas é bom registrar quem excluiu.
        $stmt = $conexao->prepare("DELETE FROM plataforma_comunicados WHERE id = :id");
        $stmt->execute([':id' => $comunicado_id]);
        if ($stmt->rowCount() > 0) {
            dbRegistrarLogAcesso($admin_id_acao, $_SERVER['REMOTE_ADDR'], 'excluir_comunicado_plataforma', 1, "Comunicado ID: $comunicado_id excluído.", $conexao);
            return true;
        }
        return "Comunicado não encontrado para exclusão ou já excluído.";
    } catch (PDOException $e) {
        error_log("Erro ao excluir comunicado da plataforma ID $comunicado_id: " . $e->getMessage());
        return "Erro de banco de dados ao excluir o comunicado.";
    }
}

/**
 * Lista comunicados da plataforma com paginação.
 */
function listarComunicadosPlataformaPaginado(PDO $conexao, int $pagina = 1, int $itens_por_pagina = 10, array $filtros = []): array {
    $offset = ($pagina - 1) * $itens_por_pagina;

    $sql_select_campos = "SELECT SQL_CALC_FOUND_ROWS 
                            id, titulo_comunicado, conteudo_comunicado, 
                            data_publicacao, data_expiracao, ativo, 
                            usuario_criacao_id, segmento_planos_ids_json, data_criacao ";
    $sql_from = " FROM plataforma_comunicados ";
    $sql_where_conditions = [];
    $params_query = [];

    // Exemplo de como adicionar um filtro de status se você precisar no futuro:
    // if (isset($filtros['ativo']) && $filtros['ativo'] !== null && $filtros['ativo'] !== '') {
    //     $sql_where_conditions[] = "ativo = :ativo_filtro";
    //     $params_query[':ativo_filtro'] = (int)$filtros['ativo'];
    // }
    // Adicionar outros filtros aqui se necessário (busca por título, etc.)

    $sql_where = "";
    if (!empty($sql_where_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $sql_where_conditions);
    }

    $sql_order = " ORDER BY data_publicacao DESC, id DESC ";
    $sql_limit = " LIMIT :limit OFFSET :offset ";

    $sql_final = $sql_select_campos . $sql_from . $sql_where . $sql_order . $sql_limit;

    try {
        $stmt = $conexao->prepare($sql_final);

        // Bind de filtros (se houver)
        foreach ($params_query as $key => &$val) {
            $stmt->bindValue($key, $val, (is_int($val)) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        unset($val);

        $stmt->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Contagem total para paginação (com os mesmos filtros WHERE)
        $sql_count_query = "SELECT COUNT(id) " . $sql_from . $sql_where;
        $stmt_count = $conexao->prepare($sql_count_query);
        // Re-bind dos parâmetros para contagem (sem limit/offset)
        foreach ($params_query as $key => &$val) {
            $stmt_count->bindValue($key, $val, (is_int($val)) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        unset($val);
        $stmt_count->execute();
        $total_itens = (int) $stmt_count->fetchColumn();
        
        $total_paginas = ($itens_por_pagina > 0 && $total_itens > 0) ? ceil($total_itens / $itens_por_pagina) : 0;
        if ($total_paginas == 0 && $total_itens > 0) $total_paginas = 1; // Pelo menos 1 página se houver itens

        return [
            'comunicados' => $comunicados ?: [], // Garante que sempre retorna um array
            'paginacao' => [
                'pagina_atual' => $pagina,
                'total_paginas' => $total_paginas,
                'total_itens' => $total_itens,
                'itens_por_pagina' => $itens_por_pagina
            ]
        ];

    } catch (PDOException $e) {
        error_log("Erro em listarComunicadosPlataformaPaginado: " . $e->getMessage() . " SQL: " . $sql_final . " Params: " . print_r($params_query, true));
        return ['comunicados' => [], 'paginacao' => ['total_itens' => 0, 'total_paginas' => 0, 'pagina_atual' => $pagina, 'itens_por_pagina' => $itens_por_pagina]];
    }
}

/**
 * Conta o total de comunicados da plataforma.
 */
function countComunicadosPlataforma(PDO $conexao): int {
    try {
        $stmt = $conexao->query("SHOW TABLES LIKE 'plataforma_comunicados'");
        if ($stmt->rowCount() === 0) {
            return 0;
        }

        $stmt = $conexao->query("SELECT COUNT(*) AS total FROM plataforma_comunicados");
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (PDOException $e) {
        error_log("Erro em countComunicadosPlataforma: " . $e->getMessage());
        return 0;
    }
}

/**
 * Obtém um comunicado por ID.
 */
function getComunicadoPlataformaPorId(PDO $conexao, int $id): ?array {
    try {
        $stmt = $conexao->query("SHOW TABLES LIKE 'plataforma_comunicados'");
        if ($stmt->rowCount() === 0) {
            return null;
        }

        $stmt = $conexao->prepare("
            SELECT id, titulo_comunicado, conteudo_comunicado, data_publicacao, data_expiracao, ativo, segmento_planos_ids_json
            FROM plataforma_comunicados
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Erro em getComunicadoPlataformaPorId: " . $e->getMessage());
        return null;
    }
}

/**
 * Cria um novo comunicado.
 */
function criarComunicadoPlataforma(PDO $conexao, array $dados, int $usuario_id): bool|string {
    try {
        $stmt = $conexao->prepare("
            INSERT INTO plataforma_comunicados (
                titulo_comunicado, conteudo_comunicado, data_publicacao, data_expiracao, ativo, usuario_criacao_id, segmento_planos_ids_json
            ) VALUES (
                :titulo, :conteudo, :data_publicacao, :data_expiracao, :ativo, :usuario_id, :segmento_json
            )
        ");
        $stmt->execute([
            ':titulo' => $dados['titulo_comunicado'],
            ':conteudo' => $dados['conteudo_comunicado'],
            ':data_publicacao' => $dados['data_publicacao_db'],
            ':data_expiracao' => $dados['data_expiracao_db'],
            ':ativo' => $dados['ativo_comunicado'],
            ':usuario_id' => $usuario_id,
            ':segmento_json' => $dados['segmento_planos_json_db']
        ]);
        return true;
    } catch (PDOException $e) {
        return "Erro ao criar comunicado: " . $e->getMessage();
    }
}

/**
 * Atualiza um comunicado existente.
 */
function atualizarComunicadoPlataforma(PDO $conexao, int $id, array $dados, int $usuario_id): bool|string {
    try {
        $stmt = $conexao->prepare("
            UPDATE plataforma_comunicados
            SET
                titulo_comunicado = :titulo,
                conteudo_comunicado = :conteudo,
                data_publicacao = :data_publicacao,
                data_expiracao = :data_expiracao,
                ativo = :ativo,
                segmento_planos_ids_json = :segmento_json
            WHERE id = :id
        ");
        $stmt->execute([
            ':titulo' => $dados['titulo_comunicado'],
            ':conteudo' => $dados['conteudo_comunicado'],
            ':data_publicacao' => $dados['data_publicacao_db'],
            ':data_expiracao' => $dados['data_expiracao_db'],
            ':ativo' => $dados['ativo_comunicado'],
            ':segmento_json' => $dados['segmento_planos_json_db'],
            ':id' => $id
        ]);
        return true;
    } catch (PDOException $e) {
        return "Erro ao atualizar comunicado: " . $e->getMessage();
    }
}

/**
 * Lista os tickets de suporte com paginação e filtros.
 */
function listarTicketsSuportePaginado(PDO $conexao, int $pagina = 1, int $itens_por_pagina = 15, array $filtros = []): array {
    $offset = ($pagina - 1) * $itens_por_pagina;
    $params = [];
    $where_clauses = [];

    // Aplicar filtros
    if (!empty($filtros['busca_assunto'])) {
        $where_clauses[] = "(t.id = :busca_id OR t.assunto_ticket LIKE :busca_assunto)";
        // Tenta converter busca para int para ID, se falhar, usa para LIKE
        $params[':busca_id'] = filter_var($filtros['busca_assunto'], FILTER_VALIDATE_INT) ?: 0; // Evita erro se não for numérico
        $params[':busca_assunto'] = '%' . $filtros['busca_assunto'] . '%';
    }
    if (!empty($filtros['empresa_id_filtro'])) {
        $where_clauses[] = "t.empresa_id_cliente = :empresa_id";
        $params[':empresa_id'] = $filtros['empresa_id_filtro'];
    }
    if (!empty($filtros['status_ticket_filtro']) && $filtros['status_ticket_filtro'] !== 'todos') {
        $where_clauses[] = "t.status_ticket = :status_ticket";
        $params[':status_ticket'] = $filtros['status_ticket_filtro'];
    }
    if (!empty($filtros['prioridade_filtro']) && $filtros['prioridade_filtro'] !== 'todos') {
        $where_clauses[] = "t.prioridade_ticket = :prioridade_ticket";
        $params[':prioridade_ticket'] = $filtros['prioridade_filtro'];
    }
    if (!empty($filtros['admin_resp_filtro'])) {
        if ($filtros['admin_resp_filtro'] === 'nao_atribuido') {
            $where_clauses[] = "t.admin_acoditools_responsavel_id IS NULL";
        } else {
            $where_clauses[] = "t.admin_acoditools_responsavel_id = :admin_resp_id";
            $params[':admin_resp_id'] = $filtros['admin_resp_filtro'];
        }
    }

    $sql_where = "";
    if (!empty($where_clauses)) {
        $sql_where = " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql_select_base = "FROM plataforma_tickets_suporte t
                        LEFT JOIN empresas e ON t.empresa_id_cliente = e.id
                        LEFT JOIN usuarios u_solicitante ON t.usuario_solicitante_id = u_solicitante.id
                        LEFT JOIN usuarios u_admin_resp ON t.admin_acoditools_responsavel_id = u_admin_resp.id";

    $sql_count = "SELECT COUNT(t.id) " . $sql_select_base . $sql_where;

    $sql_data = "SELECT t.*, e.nome as nome_empresa_cliente,
                        u_solicitante.nome as nome_usuario_solicitante,
                        u_admin_resp.nome as nome_admin_responsavel
                 " . $sql_select_base . $sql_where . "
                 ORDER BY CASE t.status_ticket
                            WHEN 'Aberto' THEN 1
                            WHEN 'Em Andamento' THEN 2
                            WHEN 'Aguardando Cliente' THEN 3
                            WHEN 'Resolvido' THEN 4
                            WHEN 'Fechado' THEN 5
                            ELSE 6
                          END ASC,
                          CASE t.prioridade_ticket
                            WHEN 'Urgente' THEN 1
                            WHEN 'Alta' THEN 2
                            WHEN 'Normal' THEN 3
                            WHEN 'Baixa' THEN 4
                            ELSE 5
                          END ASC,
                          t.data_ultima_atualizacao DESC, t.id DESC
                 LIMIT :limit OFFSET :offset";

    try {
        $stmt_count = $conexao->prepare($sql_count);
        // Bind de parâmetros para contagem (exceto :busca_id que pode não ser INT)
        $params_count = $params;
        if(isset($params_count[':busca_id'])) unset($params_count[':busca_id']); // Não usar para count se o :busca_assunto já pega.
        foreach ($params_count as $key => &$val) { $stmt_count->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR); } unset($val);
        $stmt_count->execute();
        $total_itens = (int) $stmt_count->fetchColumn();

        $stmt_data = $conexao->prepare($sql_data);
        // Bind de todos os parâmetros para os dados
        foreach ($params as $key => &$val) { $stmt_data->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR); } unset($val);
        $stmt_data->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
        $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt_data->execute();
        $tickets = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

        $total_paginas = ($itens_por_pagina > 0 && $total_itens > 0) ? ceil($total_itens / $itens_por_pagina) : 1;

        return [
            'tickets' => $tickets,
            'paginacao' => [
                'pagina_atual' => $pagina,
                'total_paginas' => $total_paginas,
                'total_itens' => $total_itens,
                'itens_por_pagina' => $itens_por_pagina
            ]
        ];

    } catch (PDOException $e) {
        error_log("Erro em listarTicketsSuportePaginado: " . $e->getMessage());
        return ['tickets' => [], 'paginacao' => ['pagina_atual' => 1, 'total_paginas' => 0, 'total_itens' => 0, 'itens_por_pagina' => $itens_por_pagina]];
    }
}

/**
 * Busca os detalhes de um ticket de suporte, incluindo seu histórico de comentários.
 */
function getTicketSuporteDetalhes(PDO $conexao, int $ticket_id): ?array {
    $ticket_data = [];

    // Buscar dados do ticket
    $sql_ticket = "SELECT t.*, e.nome as nome_empresa_cliente,
                          u_sol.nome as nome_usuario_solicitante, u_sol.email as email_usuario_solicitante,
                          u_admin.nome as nome_admin_atribuido
                   FROM plataforma_tickets_suporte t
                   JOIN empresas e ON t.empresa_id_cliente = e.id
                   JOIN usuarios u_sol ON t.usuario_solicitante_id = u_sol.id
                   LEFT JOIN usuarios u_admin ON t.admin_acoditools_responsavel_id = u_admin.id
                   WHERE t.id = :ticket_id";
    try {
        $stmt_ticket = $conexao->prepare($sql_ticket);
        $stmt_ticket->execute([':ticket_id' => $ticket_id]);
        $ticket_data['ticket'] = $stmt_ticket->fetch(PDO::FETCH_ASSOC);

        if (!$ticket_data['ticket']) {
            return null; // Ticket não encontrado
        }

        // Buscar comentários/histórico do ticket
        $sql_comentarios = "SELECT tc.*, u_autor.nome as nome_autor_comentario, u_autor.perfil as perfil_autor_comentario
                            FROM plataforma_ticket_comentarios tc
                            JOIN usuarios u_autor ON tc.usuario_id_autor = u_autor.id
                            WHERE tc.ticket_id = :ticket_id
                            ORDER BY tc.data_comentario ASC";
        $stmt_comentarios = $conexao->prepare($sql_comentarios);
        $stmt_comentarios->execute([':ticket_id' => $ticket_id]);
        $ticket_data['comentarios'] = $stmt_comentarios->fetchAll(PDO::FETCH_ASSOC);

        return $ticket_data;

    } catch (PDOException $e) {
        error_log("Erro em getTicketSuporteDetalhes para ID $ticket_id: " . $e->getMessage());
        return null;
    }
}

/**
 * Adiciona um comentário a um ticket de suporte.
 */
function adicionarComentarioTicket(PDO $conexao, int $ticket_id, int $usuario_id_autor, string $texto_comentario, string $origem_comentario = 'admin', bool $privado_admin = false): bool {
    if (empty(trim($texto_comentario))) {
        return false; // Não adicionar comentários vazios
    }

    $sql = "INSERT INTO plataforma_ticket_comentarios
                (ticket_id, usuario_id_autor, texto_comentario, origem_comentario, privado_admin, data_comentario)
            VALUES
                (:ticket_id, :usuario_id_autor, :texto_comentario, :origem, :privado, NOW())";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':ticket_id' => $ticket_id,
            ':usuario_id_autor' => $usuario_id_autor,
            ':texto_comentario' => trim($texto_comentario),
            ':origem' => $origem_comentario,
            ':privado' => (int)$privado_admin
        ]);
        // Atualizar data_ultima_atualizacao do ticket principal
        $stmt_update_ticket = $conexao->prepare("UPDATE plataforma_tickets_suporte SET data_ultima_atualizacao = NOW() WHERE id = :ticket_id");
        $stmt_update_ticket->execute([':ticket_id' => $ticket_id]);

        return true;
    } catch (PDOException $e) {
        error_log("Erro ao adicionar comentário ao ticket ID $ticket_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Atualiza os dados de um ticket pelo Admin da Plataforma (status, prioridade, responsável, adiciona comentário).
 */
function atualizarTicketPeloAdmin(PDO $conexao, int $ticket_id, string $novo_status_ticket, ?int $novo_admin_resp_id, string $nova_prioridade_ticket, ?string $comentario_admin_texto, int $admin_logado_id): bool|string {

    // Validar status e prioridade
    $status_validos = ['Aberto', 'Em Andamento', 'Aguardando Cliente', 'Resolvido', 'Fechado'];
    $prioridades_validas = ['Baixa', 'Normal', 'Alta', 'Urgente'];

    if (!in_array($novo_status_ticket, $status_validos)) return "Status do ticket inválido.";
    if (!in_array($nova_prioridade_ticket, $prioridades_validas)) return "Prioridade do ticket inválida.";

    // Verificar se o admin responsável (se fornecido) é um admin da plataforma válido
    if ($novo_admin_resp_id !== null) {
        $stmtCheckAdmin = $conexao->prepare("SELECT id FROM usuarios WHERE id = :id AND perfil = 'admin' AND ativo = 1");
        $stmtCheckAdmin->execute([':id' => $novo_admin_resp_id]);
        if (!$stmtCheckAdmin->fetch()) {
            return "Administrador responsável selecionado é inválido.";
        }
    }

    $conexao->beginTransaction();
    try {
        $sql_update = "UPDATE plataforma_tickets_suporte SET
                            status_ticket = :status,
                            prioridade_ticket = :prioridade,
                            admin_acoditools_responsavel_id = :admin_resp_id,
                            data_ultima_atualizacao = NOW()
                       WHERE id = :ticket_id";
        $stmt_update = $conexao->prepare($sql_update);
        $stmt_update->execute([
            ':status' => $novo_status_ticket,
            ':prioridade' => $nova_prioridade_ticket,
            ':admin_resp_id' => $novo_admin_resp_id, // PDO trata NULL
            ':ticket_id' => $ticket_id
        ]);

        // Adicionar comentário se houver
        if (!empty(trim($comentario_admin_texto ?? ''))) {
            if (!adicionarComentarioTicket($conexao, $ticket_id, $admin_logado_id, trim($comentario_admin_texto), 'admin')) {
                throw new Exception("Falha ao adicionar comentário ao ticket.");
            }
        }

        $conexao->commit();
        dbRegistrarLogAcesso($admin_logado_id, $_SERVER['REMOTE_ADDR'], 'atualizar_ticket_suporte', 1, "Ticket ID: $ticket_id atualizado. Status: $novo_status_ticket, Prio: $nova_prioridade_ticket, AdminResp: $novo_admin_resp_id", $conexao);
        return true;

    } catch (Exception $e) { // Captura PDOException ou Exception customizada
        if ($conexao->inTransaction()) $conexao->rollBack();
        error_log("Erro ao atualizar ticket ID $ticket_id pelo admin: " . $e->getMessage());
        return "Erro de banco de dados ao atualizar o ticket: " . $e->getMessage();
    }
}

/**
 * Função auxiliar para buscar todos os usuários de um determinado perfil (ex: 'admin' para admin da plataforma)
 */
function getTodosUsuariosPorPerfil(PDO $conexao, string $perfil): array {
    try {
        $sql = "SELECT id, nome, email FROM usuarios WHERE perfil = :perfil AND ativo = 1 ORDER BY nome ASC";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([':perfil' => $perfil]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro em getTodosUsuariosPorPerfil para perfil '$perfil': " . $e->getMessage());
        return [];
    }
}

if (!function_exists('validarImagem')) {
    // Inclua aqui a definição da sua função validarImagem se ela não estiver
    // em um arquivo já incluído antes deste. Exemplo de uma versão simples:
    function validarImagem(array $imagem, int $maxSize = 2 * 1024 * 1024, array $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml']): ?string {
        if (!isset($imagem['error']) || is_array($imagem['error'])) {
            return "Parâmetros de upload inválidos.";
        }
        switch ($imagem['error']) {
            case UPLOAD_ERR_OK: break;
            case UPLOAD_ERR_NO_FILE: return null;
            // ... (outros cases de erro de upload) ...
            default: return "Erro desconhecido no upload (código: {$imagem['error']}).";
        }
        if ($imagem['size'] > $maxSize) {
            return "Arquivo excede o tamanho máximo permitido (" . ($maxSize / 1024 / 1024) . "MB).";
        }
        if ($imagem['size'] === 0 && $imagem['error'] === UPLOAD_ERR_OK) {
             return "O arquivo enviado está vazio.";
        }
        // Validação de tipo MIME real
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeTypeReal = finfo_file($finfo, $imagem['tmp_name']);
        finfo_close($finfo);
        if ($mimeTypeReal === false || !in_array(strtolower($mimeTypeReal), array_map('strtolower', $tiposPermitidos))) {
            $ext = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
            $mapExtToMime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'];
            if (!isset($mapExtToMime[$ext]) || !in_array(strtolower($mapExtToMime[$ext]), array_map('strtolower', $tiposPermitidos))) {
                 return "Tipo de arquivo não permitido. Tipos aceitos: " . implode(', ', $tiposPermitidos);
            }
        }
        return null; // Válido
    }
}


/**
 * Processa o upload do logo de uma empresa cliente.
 * Valida, move o arquivo para o diretório de logos de clientes, e remove o logo antigo se aplicável.
 */
function processarUploadLogoEmpresaCliente(
    array $logoFile,
    PDO $conexao, // Para logging
    ?string $logoAntigoParaRemover = null,
    string $uploadSubDir = 'logos_clientes/' // Subdiretório específico
): array {

    // Usar a constante global para o caminho base de uploads
    if (!defined('UPLOADS_BASE_PATH_ABSOLUTE')) {
        error_log("Constante UPLOADS_BASE_PATH_ABSOLUTE não definida em processarUploadLogoEmpresaCliente.");
        return ['success' => false, 'message' => 'Erro de configuração do servidor (caminho de uploads).', 'nome_arquivo' => $logoAntigoParaRemover];
    }

    // Validação do arquivo usando a função genérica (se desejar mais controle, use validarImagem diretamente)
    // Para logos, podemos ter um tamanho um pouco menor e tipos específicos.
    $maxSizeLogoCliente = 2 * 1024 * 1024; // 2MB
    $allowedTypesLogoCliente = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];

    // Se nenhum arquivo foi enviado, não é um erro, apenas retorna sucesso mantendo o logo antigo (se houver)
    if (!isset($logoFile['error']) || $logoFile['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'message' => 'Nenhum novo logo enviado.', 'nome_arquivo' => $logoAntigoParaRemover];
    }

    $erroValidacao = validarImagem($logoFile, $maxSizeLogoCliente, $allowedTypesLogoCliente);
    if ($erroValidacao !== null) {
        return ['success' => false, 'message' => $erroValidacao, 'nome_arquivo' => $logoAntigoParaRemover];
    }

    // Preparar Diretório de Destino
    $diretorioDestinoAbsoluto = rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/\\') . '/' . trim($uploadSubDir, '/\\') . '/';

    if (!is_dir($diretorioDestinoAbsoluto)) {
        if (!mkdir($diretorioDestinoAbsoluto, 0755, true) && !is_dir($diretorioDestinoAbsoluto)) { // Checa novamente se foi criado
            error_log("Falha ao criar diretório de upload para logos de clientes: " . $diretorioDestinoAbsoluto);
            return ['success' => false, 'message' => 'Erro de servidor (criação de diretório de logos).', 'nome_arquivo' => $logoAntigoParaRemover];
        }
    }
    if (!is_writable($diretorioDestinoAbsoluto)) {
        error_log("Diretório de upload de logos de clientes sem permissão de escrita: " . $diretorioDestinoAbsoluto);
        return ['success' => false, 'message' => 'Erro de servidor (permissão de diretório de logos).', 'nome_arquivo' => $logoAntigoParaRemover];
    }

    // Gerar nome de arquivo único e seguro
    $extensao = strtolower(pathinfo($logoFile['name'], PATHINFO_EXTENSION));
    // Usar um prefixo, talvez o ID da empresa se já soubermos, ou um hash/timestamp para unicidade
    // Se o ID da empresa ainda não foi gerado (caso de "criar empresa"), usar um placeholder temporário
    // ou gerar o nome do arquivo *após* a empresa ser criada e ter um ID.
    // Para esta função genérica, vamos usar apenas timestamp e random bytes.
    $nomeArquivoSeguro = 'logo_cliente_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extensao;
    $caminhoCompletoDestino = $diretorioDestinoAbsoluto . $nomeArquivoSeguro;

    // Mover o arquivo enviado
    if (move_uploaded_file($logoFile['tmp_name'], $caminhoCompletoDestino)) {
        // Se o upload do novo logo foi bem-sucedido, remover o antigo (se existir)
        if ($logoAntigoParaRemover) {
            $caminhoLogoAntigoAbs = $diretorioDestinoAbsoluto . $logoAntigoParaRemover;
            if (file_exists($caminhoLogoAntigoAbs)) {
                @unlink($caminhoLogoAntigoAbs); // Tenta remover, suprime erro se falhar
            }
        }
        // Não precisamos logar aqui, a função que chama (criar/editar empresa) deve logar a ação completa.
        return ['success' => true, 'message' => 'Logo enviado com sucesso!', 'nome_arquivo' => $nomeArquivoSeguro];
    } else {
        $phpUploadError = $logoFile['error'];
        error_log("Falha ao mover o arquivo de logo do cliente para o destino. Erro PHP de Upload: $phpUploadError. Temp: {$logoFile['tmp_name']}, Dest: $caminhoCompletoDestino");
        $mensagemErroUsuario = 'Erro ao salvar o arquivo de logo no servidor.';
        if ($phpUploadError === UPLOAD_ERR_INI_SIZE || $phpUploadError === UPLOAD_ERR_FORM_SIZE) {
             $mensagemErroUsuario = 'O arquivo de logo excede o tamanho máximo permitido.';
        }
        return ['success' => false, 'message' => $mensagemErroUsuario, 'nome_arquivo' => $logoAntigoParaRemover];
    }
}

function listarLogsAplicacaoPaginado(PDO $conexao, int $pagina = 1, int $itens_por_pagina = 20, array $filtros = []): array {
    $offset = ($pagina - 1) * $itens_por_pagina;
    $sql_select = "SELECT SQL_CALC_FOUND_ROWS le.id, le.tipo_erro, le.mensagem_erro, le.arquivo_origem, le.data_erro
                   FROM logs_erros le"; // Adicionar JOINs se tiver `usuario_id_contexto` etc.

    $where_clauses = [];
    $params = [];

    if (!empty($filtros['data_inicio'])) {
        $where_clauses[] = "DATE(le.data_erro) >= :data_inicio";
        $params[':data_inicio'] = $filtros['data_inicio'];
    }
    if (!empty($filtros['data_fim'])) {
        $where_clauses[] = "DATE(le.data_erro) <= :data_fim";
        $params[':data_fim'] = $filtros['data_fim'];
    }
    if (!empty($filtros['tipo_erro'])) {
        $where_clauses[] = "le.tipo_erro = :tipo_erro";
        $params[':tipo_erro'] = $filtros['tipo_erro'];
    }
    // Adicionar filtro por gravidade se o campo existir
    // if (!empty($filtros['gravidade'])) {
    //     $where_clauses[] = "le.gravidade = :gravidade";
    //     $params[':gravidade'] = $filtros['gravidade'];
    // }
    if (!empty($filtros['busca_livre'])) {
        $where_clauses[] = "(le.mensagem_erro LIKE :busca_livre OR le.arquivo_origem LIKE :busca_livre)";
        $params[':busca_livre'] = '%' . $filtros['busca_livre'] . '%';
    }

    $sql_where = "";
    if (!empty($where_clauses)) {
        $sql_where = " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql_order_limit = " ORDER BY le.data_erro DESC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $itens_por_pagina;
    $params[':offset'] = $offset;

    $sql = $sql_select . $sql_where . $sql_order_limit;

    try {
        $stmt = $conexao->prepare($sql);
        foreach ($params as $key => &$val) {
            $stmt->bindValue($key, $val, (is_int($val) || $key === ':limit' || $key === ':offset') ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        unset($val);

        $stmt->execute();
        $logs_erros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_itens = (int) $conexao->query("SELECT FOUND_ROWS()")->fetchColumn();
        $total_paginas = ($itens_por_pagina > 0 && $total_itens > 0) ? ceil($total_itens / $itens_por_pagina) : 0;
        if ($total_paginas == 0 && $total_itens > 0) $total_paginas = 1;

    } catch (PDOException $e) {
        error_log("Erro em listarLogsAplicacaoPaginado: " . $e->getMessage() . " SQL: $sql Params: " . print_r($params, true));
        return ['logs_erros' => [], 'paginacao' => ['pagina_atual' => 1, 'total_paginas' => 0, 'total_itens' => 0, 'itens_por_pagina' => $itens_por_pagina]];
    }

    return [
        'logs_erros' => $logs_erros,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_itens' => $total_itens,
            'itens_por_pagina' => $itens_por_pagina
        ]
    ];
}

function getTiposDeErroDistintos(PDO $conexao): array {
    try {
        $stmt = $conexao->query("SELECT DISTINCT tipo_erro FROM logs_erros WHERE tipo_erro IS NOT NULL AND tipo_erro != '' ORDER BY tipo_erro ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Erro em getTiposDeErroDistintos: " . $e->getMessage());
        return [];
    }
}

function getLogAplicacaoDetalhes(PDO $conexao, int $log_id): ?array {
    // Se sua tabela `logs_erros` tiver mais campos como `stack_trace`, `contexto_adicional`, adicione-os aqui.
    $sql = "SELECT le.* FROM logs_erros le WHERE le.id = :log_id";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute([':log_id' => $log_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Erro em getLogAplicacaoDetalhes ID $log_id: " . $e->getMessage());
        return null;
    }
}

function verificarCnpjDuplicadoOutraEmpresa(PDO $conexao, string $cnpj, int $id_empresa_atual): bool {
    // Limpar o CNPJ para garantir que apenas números sejam comparados
    $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);

    if (empty($cnpjLimpo) || strlen($cnpjLimpo) !== 14) {
        // CNPJ inválido para verificação, pode tratar como não duplicado ou lançar erro.
        // Neste contexto, se o CNPJ é inválido, a validação de formato do CNPJ já deveria ter pego.
        // Se chegou aqui com CNPJ inválido, podemos considerar que não há duplicata (pois não será salvo assim).
        return false;
    }

    try {
        $sql = "SELECT COUNT(*) FROM empresas WHERE cnpj = :cnpj AND id != :id_empresa_atual";
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':cnpj', $cnpjLimpo, PDO::PARAM_STR);
        $stmt->bindParam(':id_empresa_atual', $id_empresa_atual, PDO::PARAM_INT);
        $stmt->execute();
        
        $count = $stmt->fetchColumn();
        return $count > 0; // Se count > 0, significa que o CNPJ existe em outra empresa

    } catch (PDOException $e) {
        error_log("Erro ao verificar CNPJ duplicado para outra empresa: " . $e->getMessage() . " (CNPJ: $cnpjLimpo, ID Atual: $id_empresa_atual)");
        // Em caso de erro de banco, é mais seguro assumir que pode haver duplicata
        // para evitar salvar dados inconsistentes, ou retornar false e logar criticamente.
        // Retornar true pode bloquear uma edição válida. Retornar false é mais permissivo mas pode levar a erro no UPDATE.
        // Uma abordagem seria lançar a exceção para ser tratada pela página chamadora.
        // Por simplicidade, vamos retornar false e logar, assumindo que a constraint UNIQUE no DB pegaria o erro no final.
        return false; // Ou throw $e;
    }
}

function verificarCnpjDuplicado(PDO $conexao, string $cnpj): bool {
    $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);
    if (empty($cnpjLimpo) || strlen($cnpjLimpo) !== 14) {
        return false; // CNPJ inválido não deve ser considerado como duplicado aqui.
    }

    try {
        $sql = "SELECT COUNT(*) FROM empresas WHERE cnpj = :cnpj";
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':cnpj', $cnpjLimpo, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao verificar CNPJ duplicado: " . $e->getMessage() . " (CNPJ: $cnpjLimpo)");
        return false; // Assumir não duplicado em caso de erro DB, constraint pegará.
    }
}

function listarCategoriasModelo(PDO $conexao, bool $apenasAtivas = true): array {
    $sql = "SELECT id, nome_categoria_modelo FROM plataforma_categorias_modelo";
    if ($apenasAtivas) {
        $sql .= " WHERE ativo = 1"; // Assumindo que sua tabela categorias_modelo tem um campo 'ativo'
    }
    $sql .= " ORDER BY nome_categoria_modelo ASC";
    try {
        $stmt = $conexao->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao listar categorias de modelo: " . $e->getMessage());
        return [];
    }
}

function criarModeloAuditoria(PDO $conexao, array $dados_modelo, int $admin_id): bool|string {
    if (empty($dados_modelo['nome'])) {
        return "O nome do modelo é obrigatório.";
    }
    // Adicione outras validações essenciais aqui (ex: formato de data se preenchida)

    // Verificar se o nome do modelo já existe para modelos GLOBAIS
    try {
        $stmtCheck = $conexao->prepare("SELECT id FROM modelos_auditoria WHERE nome = :nome AND global_ou_empresa_id IS NULL");
        $stmtCheck->execute([':nome' => $dados_modelo['nome']]);
        if ($stmtCheck->fetch()) {
            return "Já existe um modelo global com este nome.";
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar nome duplicado do modelo (global) durante criação: " . $e->getMessage());
        return "Erro ao verificar dados do modelo. Tente novamente.";
    }

    $disponibilidade_planos_json = null;
    if (!empty($dados_modelo['disponibilidade_planos_ids']) && is_array($dados_modelo['disponibilidade_planos_ids'])) {
        $ids_int = array_map('intval', $dados_modelo['disponibilidade_planos_ids']);
        // Filtra IDs que se tornaram 0 após intval (eram inválidos ou string vazia) e re-indexa
        $ids_int_filtrados = array_values(array_filter($ids_int, function($id) { return $id > 0; }));
        if (!empty($ids_int_filtrados)) {
            $disponibilidade_planos_json = json_encode($ids_int_filtrados);
        } else {
            $disponibilidade_planos_json = null; // Ou '[]' se preferir um JSON array vazio
        }
    }

    // Campos que vão para o banco
    $db_data = [
        ':nome' => $dados_modelo['nome'],
        ':descricao' => $dados_modelo['descricao'] ?: null,
        ':tipo_modelo_id' => $dados_modelo['tipo_modelo_id'] ?: null, // Assegure que a validação no controller já tratou se for obrigatório
        ':versao_modelo' => $dados_modelo['versao_modelo'] ?: '1.0',
        ':data_ultima_revisao' => $dados_modelo['data_ultima_revisao_modelo'] ?: null,
        ':proxima_revisao_sugerida' => $dados_modelo['proxima_revisao_sugerida_modelo'] ?: null,
        ':planos_json' => $disponibilidade_planos_json,
        ':permite_copia' => (int)($dados_modelo['permite_copia_cliente'] ?? 0),
        ':ativo' => (int)($dados_modelo['ativo'] ?? 1),
        ':admin_id_criador' => $admin_id, // Placeholder para criado_por
        ':admin_id_modificador' => $admin_id  // Placeholder para modificado_por
    ];


    $sql = "INSERT INTO modelos_auditoria (
                nome, descricao, tipo_modelo_id, versao_modelo,
                data_ultima_revisao_modelo, proxima_revisao_sugerida_modelo,
                disponibilidade_plano_ids_json, permite_copia_cliente,
                ativo, global_ou_empresa_id, 
                criado_por, data_criacao, modificado_por, data_modificacao 
            ) VALUES (
                :nome, :descricao, :tipo_modelo_id, :versao_modelo,
                :data_ultima_revisao, :proxima_revisao_sugerida,
                :planos_json, :permite_copia,
                :ativo, NULL, 
                :admin_id_criador, NOW(), :admin_id_modificador, NOW() 
            )";
    try {
        $stmt = $conexao->prepare($sql);
        $stmt->execute($db_data); // PDO é inteligente o suficiente para mapear :admin_id_criador e :admin_id_modificador

        // Para retornar o ID do modelo criado, se necessário
        // $novo_id = $conexao->lastInsertId();
        // return ['success' => true, 'id' => (int)$novo_id];
        return true;

    } catch (PDOException $e) {
        error_log("Erro ao criar modelo de auditoria: " . $e->getMessage() . " SQL: " . $sql . " Dados: " . print_r($db_data, true));
        // Checagem mais segura de errorInfo
        if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) { // 1062 é o código do MySQL para Duplicate entry
             // Adicionalmente, verificar qual constraint foi violada se possível (analisando $e->errorInfo[2])
             if (str_contains(strtolower($e->getMessage()), 'uq_modelo_nome_global_empresa') || str_contains(strtolower($e->getMessage()), "for key 'nome'")) { // Adapte 'nome' para o nome real da sua constraint UNIQUE
                 return "Já existe um modelo global com este nome.";
             }
             return "Erro de duplicidade ao criar o modelo (código ou outro campo único).";
        }
        return "Erro de banco de dados ao criar o modelo. Verifique os logs para detalhes.";
    }
}

function criarCategoriaModelo(PDO $conexao, string $nome_categoria, ?string $descricao_categoria, bool $ativo, int $admin_id): bool|string {
    if (empty($nome_categoria)) {
        return "O nome da categoria do modelo é obrigatório.";
    }
    try {
        $stmtCheck = $conexao->prepare("SELECT id FROM plataforma_categorias_modelo WHERE nome_categoria_modelo = :nome");
        $stmtCheck->execute([':nome' => $nome_categoria]);
        if ($stmtCheck->fetch()) {
            return "Já existe uma categoria de modelo com este nome.";
        }

        $sql = "INSERT INTO plataforma_categorias_modelo (nome_categoria_modelo, descricao_categoria_modelo, ativo, criado_por_admin_id)
                VALUES (:nome, :desc, :ativo, :admin_id)";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':nome' => $nome_categoria,
            ':desc' => $descricao_categoria ?: null,
            ':ativo' => (int)$ativo,
            ':admin_id' => $admin_id
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro em criarCategoriaModelo: " . $e->getMessage());
        if ($e->errorInfo[1] == 1062) return "Nome da categoria já existe."; // MySQLErrNo 1062 para duplicate entry
        return "Erro de banco de dados ao criar a categoria do modelo.";
    }
}

function listarTodasCategoriasModelo(PDO $conexao): array {
    try {
        $stmt = $conexao->query("SELECT * FROM plataforma_categorias_modelo ORDER BY nome_categoria_modelo ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro em listarTodasCategoriasModelo: " . $e->getMessage());
        return [];
    }
}

function getCategoriaModeloPorId(PDO $conexao, int $id): ?array {
    try {
        $stmt = $conexao->prepare("SELECT * FROM plataforma_categorias_modelo WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Erro em getCategoriaModeloPorId ID $id: " . $e->getMessage());
        return null;
    }
}

function atualizarCategoriaModelo(PDO $conexao, int $id, string $nome_categoria, ?string $descricao_categoria, bool $ativo, int $admin_id): bool|string {
    if (empty($nome_categoria)) {
        return "O nome da categoria do modelo é obrigatório.";
    }
    try {
        // Verifica se o novo nome já existe em OUTRA categoria
        $stmtCheck = $conexao->prepare("SELECT id FROM plataforma_categorias_modelo WHERE nome_categoria_modelo = :nome AND id != :id");
        $stmtCheck->execute([':nome' => $nome_categoria, ':id' => $id]);
        if ($stmtCheck->fetch()) {
            return "Já existe outra categoria de modelo com este nome.";
        }

        // Assume que sua tabela plataforma_categorias_modelo tem um campo data_modificacao com ON UPDATE CURRENT_TIMESTAMP
        // e um modificado_por_admin_id. Se não, adicione-os.
        // Por simplicidade, vou omitir modificado_por_admin_id aqui, mas seria bom ter.
        $sql = "UPDATE plataforma_categorias_modelo SET
                    nome_categoria_modelo = :nome,
                    descricao_categoria_modelo = :desc,
                    ativo = :ativo
                    -- , modificado_por_admin_id = :admin_id
                WHERE id = :id";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            ':nome' => $nome_categoria,
            ':desc' => $descricao_categoria ?: null,
            ':ativo' => (int)$ativo,
            // ':admin_id' => $admin_id,
            ':id' => $id
        ]);
        return true; // Retorna true mesmo se nenhuma linha for alterada
    } catch (PDOException $e) {
        error_log("Erro em atualizarCategoriaModelo ID $id: " . $e->getMessage());
        if ($e->errorInfo[1] == 1062) return "Nome da categoria já existe.";
        return "Erro de banco de dados ao atualizar a categoria.";
    }
}

function setStatusCategoriaModelo(PDO $conexao, int $id, bool $ativo): bool {
    try {
        $sql = "UPDATE plataforma_categorias_modelo SET ativo = :ativo WHERE id = :id";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([':ativo' => (int)$ativo, ':id' => $id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro em setStatusCategoriaModelo ID $id: " . $e->getMessage());
        return false;
    }
}

function excluirCategoriaModelo(PDO $conexao, int $id): bool|string {
    try {
        // Verificar se a categoria está sendo usada por algum modelo
        $stmtCheckUso = $conexao->prepare("SELECT COUNT(*) FROM modelos_auditoria WHERE tipo_modelo_id = :categoria_id");
        $stmtCheckUso->execute([':categoria_id' => $id]);
        if ($stmtCheckUso->fetchColumn() > 0) {
            return "Esta categoria está sendo utilizada por um ou mais modelos de auditoria e não pode ser excluída. Considere desativá-la.";
        }

        $stmt = $conexao->prepare("DELETE FROM plataforma_categorias_modelo WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro em excluirCategoriaModelo ID $id: " . $e->getMessage());
        return "Erro de banco de dados ao excluir a categoria.";
    }
}

function salvarOrdemItensModelo(PDO $conexao, int $modelo_id, array $ordem_item_ids_int, ?string $secao = null): bool {
     // A transação agora é iniciada/commitada pela função que chama (ajax_handler) ou pelo script principal
     // se esta função for chamada em outros contextos. Se você quer que esta função seja sempre atômica,
     // pode manter a transação aqui. Por agora, vou assumir que o ajax_handler gerencia.

    try {
        $sql = "UPDATE modelo_itens SET ordem_item = :ordem
                WHERE id = :id AND modelo_id = :modelo_id";

        // Condição para a seção
        if ($secao === null) { // Seção é explicitamente nula (ex: 'Itens Gerais' mapeado para null)
            $sql .= " AND secao IS NULL";
        } else { // Seção tem um nome
            $sql .= " AND secao = :secao";
        }

        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':modelo_id', $modelo_id, PDO::PARAM_INT);

        if ($secao !== null) {
            $stmt->bindParam(':secao', $secao, PDO::PARAM_STR);
        }

        $sucesso_geral = true;
        foreach ($ordem_item_ids_int as $index => $itemId) {
            $ordem_atual = $index; // Ordem baseada no índice do array (0, 1, 2...)
            $stmt->bindParam(':ordem', $ordem_atual, PDO::PARAM_INT);
            $stmt->bindParam(':id', $itemId, PDO::PARAM_INT); // $itemId já deve ser int
            if (!$stmt->execute()) {
                error_log("Falha ao atualizar ordem para item ID $itemId no modelo ID $modelo_id, seção '$secao'.");
                $sucesso_geral = false;
                // Poderia lançar uma exceção aqui para forçar rollback se dentro de uma transação maior.
                // throw new Exception("Falha ao atualizar ordem para item ID $itemId");
            }
        }
        return $sucesso_geral; // Retorna true se todas as atualizações foram OK
    } catch (PDOException $e) { // Captura PDOException
         error_log("Erro PDO em salvarOrdemItensModelo (Modelo: $modelo_id, Seção: " . ($secao ?? 'NULL') . "): " . $e->getMessage());
        return false;
    } /*catch (Exception $e) { // Se você lançar exceção customizada acima
        error_log("Erro em salvarOrdemItensModelo (Modelo: $modelo_id, Seção: " . ($secao ?? 'NULL') . "): " . $e->getMessage());
        return false;
    }*/
}