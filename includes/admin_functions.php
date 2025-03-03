<?php
// includes/admin_functions.php

// --- Funções para Gestão de Usuários ---

/**
 * Obtém a lista de usuários.
 *
 * @param PDO $conexao Conexão com o banco de dados.
 * @param int|null $excluir_admin_id ID do administrador a ser excluído da lista (opcional).
 * @param int $pagina Página atual (para paginação).
 * @param int $itens_por_pagina Número de itens por página.
 * @return array Array associativo com os dados dos usuários e informações de paginação.
 */
function getUsuarios($conexao, $excluir_admin_id = null, $pagina = 1, $itens_por_pagina = 10) {
    $offset = ($pagina - 1) * $itens_por_pagina;
    $sql = "SELECT id, nome, email, perfil, ativo, data_cadastro FROM usuarios";
    $params = [];

    if ($excluir_admin_id !== null) {
        $sql .= " WHERE id != ?";
        $params[] = $excluir_admin_id;
    }

    $sql .= " ORDER BY nome LIMIT ?, ?"; // Adiciona LIMIT e OFFSET para paginação
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
    $sql = "SELECT id, nome, email, perfil, ativo, data_cadastro FROM usuarios WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC); // Retorna UM usuário (ou null)
}

/**
 * Atualiza os dados de um usuário no banco de dados.
 */
function atualizarUsuario($conexao, $id, $nome, $email, $perfil, $ativo) {
    $sql = "UPDATE usuarios SET nome = ?, email = ?, perfil = ?, ativo = ? WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$nome, $email, $perfil, $ativo, $id]);
    return $stmt->rowCount() > 0; // Retorna true se a atualização foi bem-sucedida
}

/**
 * Verifica se um e-mail já existe para outro usuário.
 */
function dbEmailExisteEmOutroUsuario($email, $usuario_id, $conexao) {
    $stmt = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id != ?");
    $stmt->execute([$email, $usuario_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Ativa um usuário.
 */
function ativarUsuario($conexao, $usuario_id) {
    $sql = "UPDATE usuarios SET ativo = 1 WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$usuario_id]);
    return $stmt->rowCount() > 0;
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
        return false; // Impede a autoexclusão
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

function getSolicitacaoAcesso($conexao, $solicitacao_id){
    $sql = "SELECT * FROM solicitacoes_acesso WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$solicitacao_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);

}

function aprovarSolicitacaoAcesso($conexao, $solicitacao_id, $senha_temporaria) {
     $conexao->beginTransaction(); // Inicia uma transação

    try {
        // 1. Obter dados da solicitação
        $solicitacao = getSolicitacaoAcesso($conexao, $solicitacao_id);
        if (!$solicitacao) {
            throw new Exception("Solicitação de acesso não encontrada (ID: $solicitacao_id).");
        }

        // 2. Criar o usuário
        $senha_hash = password_hash($senha_temporaria, PASSWORD_DEFAULT);

         // Use o valor de 'empresa_id' vindo da tabela de solicitações
        $sql = "INSERT INTO usuarios (nome, email, senha, perfil, ativo, empresa_id) VALUES (?, ?, ?, 'auditor', 1, ?)";
        $stmt = $conexao->prepare($sql);

        // Passa o valor de empresa_id
        $stmt->execute([$solicitacao['nome_completo'], $solicitacao['email'], $senha_hash, $solicitacao['empresa_id']]);
        $novo_usuario_id = $conexao->lastInsertId();

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

function rejeitarSolicitacaoAcesso($conexao, $solicitacao_id, $observacoes = '') {
   $sql = "UPDATE solicitacoes_acesso SET status = 'rejeitada', admin_id = ?, data_aprovacao = NOW(), observacoes = ? WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$_SESSION['usuario_id'], $observacoes, $solicitacao_id]);
    return $stmt->rowCount() > 0;
}