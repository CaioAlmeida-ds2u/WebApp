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
function atualizarUsuario($conexao, $id, $nome, $email, $perfil, $ativo) {
    $sql = "UPDATE usuarios SET nome = ?, email = ?, perfil = ?, ativo = ? WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$nome, $email, $perfil, $ativo, $id]);
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

function aprovarESetarSenhaTemp(PDO $conexao, int $solicitacao_id, string $senha_temporaria, int $admin_id): bool
{
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

function criarEmpresa(PDO $conexao, array $dados): bool|string {
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
        if ($e->getCode() == 23000) {  // 23000 é o código genérico para violação de constraint (unique, etc.)
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

        error_log("Erro ao criar empresa: " . $e->getMessage());  // Log completo do erro
        return "Erro inesperado ao criar a empresa.  Tente novamente."; // Mensagem genérica para o usuário
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
// includes/admin_functions.php
// ... (outras funções) ...

// =============================================
// === Funções para Modelos de Auditoria =======
// =============================================

/**
 * Busca uma lista paginada de modelos de auditoria com filtros opcionais.
 *
 * @param PDO $conexao Conexão PDO.
 * @param int $pagina_atual Número da página atual (padrão 1).
 * @param int $itens_por_pagina Quantidade de itens por página (padrão 15).
 * @param string $filtro_status Filtra por status 'todos', 'ativos', 'inativos' (padrão 'todos').
 * @param string $termo_busca Filtra por nome ou descrição contendo o termo (opcional).
 * @return array Retorna ['modelos' => array, 'paginacao' => array].
 *               Em caso de erro, retorna ['modelos' => [], 'paginacao' => com total 0].
 */

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

// ... (outras funções como criarModeloAuditoria, ativarModeloAuditoria, etc.) ...

/*solicitação de reset
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

function rejeitarSolicitacaoReset($conexao, $solicitacao_id, $observacoes = '') {
   $sql = "UPDATE solicitacoes_reset_senha SET status = 'rejeitada', admin_id = ?, data_rejeicao = NOW(), observacoes = ? WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$_SESSION['usuario_id'], $observacoes, $solicitacao_id]);
     // (Opcional) Enviar e-mail simulado - Implementar depois
    return $stmt->rowCount() > 0;
}
*/