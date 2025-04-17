<?php
// includes/db.php

// --- Usuários ---
function dbLogin(string $email, string $senha, PDO $conexao): array|string {
    try {
        $sql = "SELECT id, nome, senha, perfil, ativo, foto FROM usuarios WHERE email = :email";
        $stmt = $conexao->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) { // Usuário encontrado
            if (password_verify($senha, $usuario['senha'])) { // Senha correta
                if ($usuario['ativo'] == 0) {
                    return "Usuário desativado. Contate o administrador.";
                }

                // Login bem-sucedido! Definir variáveis de sessão:
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['perfil'] = $usuario['perfil'];
                // Define o caminho relativo à raiz do projeto para a foto, se existir
                $_SESSION['foto'] = !empty($usuario['foto']) ? 'uploads/fotos/' . $usuario['foto'] : '';

                // Limpa a senha do array retornado por segurança
                unset($usuario['senha']);
                return $usuario;

            } else {
                // Senha incorreta
                return "Credenciais inválidas.";
            }
        } else {
            // Usuário (email) não encontrado
            return "Credenciais inválidas.";
        }
    } catch (PDOException $e) {
        error_log("Erro em dbLogin para email $email: " . $e->getMessage());
        return "Erro ao tentar fazer login. Tente novamente."; // Mensagem genérica
    }
}

function dbGetNomeUsuario(PDO $conexao, int $usuario_id): ?string {
    try {
        $stmt = $conexao->prepare("SELECT nome FROM usuarios WHERE id = :id");
        $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['nome'] : null;
    } catch (PDOException $e) {
        error_log("Erro em dbGetNomeUsuario para ID $usuario_id: " . $e->getMessage());
        return null;
    }
}

//Função para buscar empresa pelo ID do usuario
function dbGetEmpresaDoUsuario(int $usuarioId, PDO $conexao): ?array {
    try {
        $stmt = $conexao->prepare("SELECT e.* FROM empresas e INNER JOIN usuarios u ON e.id = u.empresa_id WHERE u.id = :usuario_id");
        $stmt->bindParam(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null; // Retorna array ou null
    } catch (PDOException $e) {
        error_log("Erro em dbGetEmpresaDoUsuario para ID $usuarioId: " . $e->getMessage());
        return null;
    }
}

// --- Solicitações de Acesso ---

function dbVerificaEmailExistente(string $email, PDO $conexao): array {
    $resultado = ['usuarios' => 0, 'solicitacoes' => 0];
    try {
        $stmt = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $resultado['usuarios'] = $stmt->fetchColumn();

        $stmt = $conexao->prepare("SELECT COUNT(*) FROM solicitacoes_acesso WHERE email = :email AND status = 'pendente'");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $resultado['solicitacoes'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erro em dbVerificaEmailExistente para email $email: " . $e->getMessage());
        // Retorna 0 em caso de erro para evitar bloqueio indevido? Ou lança exceção?
        // Por enquanto, retorna 0s para não bloquear, mas loga o erro.
    }
    return $resultado;
}

function dbInserirSolicitacaoAcesso(string $nome, string $email, int $empresa_id, string $motivo, PDO $conexao): bool {
    try {
        $stmt = $conexao->prepare("INSERT INTO solicitacoes_acesso (nome_completo, email, empresa_id, motivo) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$nome, $email, $empresa_id, $motivo]);
    } catch (PDOException $e) {
        error_log("Erro em dbInserirSolicitacaoAcesso para email $email: " . $e->getMessage());
        return false;
    }
}

// --- Logs ---

/**
 * Registra um evento de log no banco de dados.
 */
function dbRegistrarLogAcesso(?int $usuario_id, string $ip_address, string $acao, int $sucesso, string $detalhes, PDO $conexao): void {
    try {
        $sql = "INSERT INTO logs_acesso (usuario_id, ip_address, acao, sucesso, detalhes) VALUES (:uid, :ip, :acao, :suc, :det)";
        $stmt = $conexao->prepare($sql);
        $stmt->bindValue(':uid', $usuario_id, PDO::PARAM_INT); // Use bindValue para permitir null
        $stmt->bindParam(':ip', $ip_address);
        $stmt->bindParam(':acao', $acao);
        $stmt->bindParam(':suc', $sucesso, PDO::PARAM_INT);
        $stmt->bindParam(':det', $detalhes);
        $stmt->execute();
    } catch (PDOException $e) {
        // Logar o erro de log é complicado... talvez logar em arquivo?
        error_log("CRITICAL: Falha ao registrar log de acesso! User: $usuario_id, Acao: $acao, IP: $ip_address. Erro DB: " . $e->getMessage());
    }
}

// --- Empresas --- (Função para buscar os dados das empresas)
function dbGetEmpresas(
    PDO $conexao,
    int $pagina_atual = 1,
    int $itens_por_pagina = 10,
    string $termo_busca = ''
): array {
    // Validações iniciais
    $pagina_atual = max(1, $pagina_atual);
    $itens_por_pagina = max(1, $itens_por_pagina);
    $termo_busca = trim($termo_busca);
    $offset = ($pagina_atual - 1) * $itens_por_pagina;

    $empresas = [];
    $paginacao = ['pagina_atual' => $pagina_atual, 'total_paginas' => 0, 'total_itens' => 0];
    $params = [];

    // Query base
    $sql_select = "SELECT SQL_CALC_FOUND_ROWS
                     e.id, e.nome, e.cnpj, e.razao_social, e.email, e.telefone
                   FROM empresas e";

    // Cláusula WHERE
    $where_clauses = [];
    if (!empty($termo_busca)) {
        $busca_formatado = '%' . strtolower($termo_busca) . '%';
        $busca_numerico = preg_replace('/[^0-9]/', '', $termo_busca);

        $where = [];

        $where[] = "LOWER(e.nome) LIKE :busca_nome_val";
        $params[':busca_nome_val'] = $busca_formatado;

        if (!empty($busca_numerico)) {
            $where[] = "REPLACE(REPLACE(REPLACE(e.cnpj, '.', ''), '/', ''), '-', '') LIKE :busca_num_val";
            $params[':busca_num_val'] = '%' . $busca_numerico . '%';
        }

        $where[] = "e.cnpj LIKE :busca_cnpj_val";
        $params[':busca_cnpj_val'] = $busca_formatado;

        $where[] = "LOWER(e.razao_social) LIKE :busca_razao_val";
        $params[':busca_razao_val'] = $busca_formatado;

        $where[] = "LOWER(e.email) LIKE :busca_email_val";
        $params[':busca_email_val'] = $busca_formatado;

        $where_clauses[] = '(' . implode(' OR ', $where) . ')';
    }

    $params[':limit'] = $itens_por_pagina;
    $params[':offset'] = $offset;

    $sql_where = empty($where_clauses) ? "" : " WHERE " . implode(' AND ', $where_clauses);

    // Ordenação e limite
    $sql_order_limit = " ORDER BY e.nome ASC LIMIT :limit OFFSET :offset";

    // Query completa
    $sql = $sql_select . $sql_where . $sql_order_limit;

    try {
        $stmt = $conexao->prepare($sql);

        // Bind dos parâmetros
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }

        // Log para depuração (opcional)
        error_log("SQL: $sql, Params: " . json_encode($params));

        $stmt->execute();
        $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total de itens para paginação
        $total_itens = (int) $conexao->query("SELECT FOUND_ROWS()")->fetchColumn();
        $paginacao['total_itens'] = $total_itens;
        $paginacao['total_paginas'] = ($itens_por_pagina > 0 && $total_itens > 0) ? ceil($total_itens / $itens_por_pagina) : 0;

    } catch (PDOException $e) {
        error_log("Erro em dbGetEmpresas (Busca: $termo_busca, SQL: $sql): " . $e->getMessage());
        return [
            'empresas' => [],
            'paginacao' => $paginacao,
            'erro' => 'Erro ao buscar empresas. Contate o administrador.'
        ];
    }

    return [
        'empresas' => $empresas,
        'paginacao' => $paginacao
    ];
}

//DB para buscar empresas para a solicitação de acesso
function dbGetEmpresasSolic(PDO $conexao): array {
    try {
        $stmt = $conexao->query("SELECT id, nome FROM empresas ORDER BY nome");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro em dbGetEmpresasSolic: " . $e->getMessage());
        return [];
    }
}


// --- Outras funções relacionadas ao banco de dados irão aqui ---
function dbGetUsuario(int $id, PDO $conexao): ?array {
     try {
        $stmt = $conexao->prepare("SELECT id, nome, email, perfil, ativo, foto FROM usuarios WHERE id = :id"); // Adicionado foto
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
     } catch (PDOException $e) {
        error_log("Erro em dbGetUsuario para ID $id: " . $e->getMessage());
        return null;
    }
}

function dbVerificaSenha(int $usuario_id, string $senha, PDO $conexao): bool {
    try {
        $stmt = $conexao->prepare("SELECT senha FROM usuarios WHERE id = :id");
        $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        $hashSenha = $stmt->fetchColumn();
        return $hashSenha ? password_verify($senha, $hashSenha) : false;
    } catch (PDOException $e) {
        error_log("Erro em dbVerificaSenha para ID $usuario_id: " . $e->getMessage());
        return false;
    }
}
function dbAtualizarUsuario(int $id, string $nome, string $email, ?string $novaSenha, PDO $conexao): bool {
    try {
        if ($novaSenha !== null) {
            $hashedSenha = password_hash($novaSenha, PASSWORD_DEFAULT);
            $stmt = $conexao->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ? WHERE id = ?");
            $resultado = $stmt->execute([$nome, $email, $hashedSenha, $id]);
        } else {
            $stmt = $conexao->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
            $resultado = $stmt->execute([$nome, $email, $id]);
        }
        return $resultado;
    } catch (PDOException $e) {
        error_log("Erro em dbAtualizarUsuario para ID $id: " . $e->getMessage());
        return false;
    }
}

//Verificar email excluindo o usuario atual
function dbEmailExisteEmOutroUsuario(string $email, int $usuario_id_excluir, PDO $conexao): bool {
     try {
        $stmt = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :id");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':id', $usuario_id_excluir, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
     } catch (PDOException $e) {
        error_log("Erro em dbEmailExisteEmOutroUsuario para email $email: " . $e->getMessage());
        return false; // Assume que não existe em caso de erro? Ou lança exceção?
    }
}

// Adicionar estas funções em includes/db.php

/**
 * Verifica se um usuário existe e está ativo com base no e-mail.
 * @param string $email O e-mail a ser verificado.
 * @param PDO $conexao Conexão PDO.
 * @return int|null Retorna o ID do usuário se ativo e encontrado, ou null caso contrário.
 */
function dbVerificarUsuarioAtivoPorEmail(string $email, PDO $conexao): ?int {
    try {
        // Verifica se o e-mail existe E se o usuário está ativo (ativo = 1)
        $stmt = $conexao->prepare("SELECT id FROM usuarios WHERE email = :email AND ativo = 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $resultado = $stmt->fetchColumn(); // fetchColumn retorna o valor da primeira coluna ou false/null se não houver linha
        return $resultado ? (int)$resultado : null; // Converte para int se encontrou, senão retorna null
    } catch (PDOException $e) {
        error_log("Erro em dbVerificarUsuarioAtivoPorEmail para email $email: " . $e->getMessage());
        return null; // Retorna null em caso de erro de banco
    }
}

function dbInserirSolicitacaoReset(int $usuario_id, PDO $conexao): bool {
    try {
        // Insere a solicitação com status 'pendente' por padrão
        $stmt = $conexao->prepare("INSERT INTO solicitacoes_reset_senha (usuario_id) VALUES (:usuario_id)");
        $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        // Poderia haver erro se já existir uma solicitação pendente? Depende da estrutura da tabela.
        error_log("Erro em dbInserirSolicitacaoReset para usuario ID $usuario_id: " . $e->getMessage());
        return false;
    }
}

?>