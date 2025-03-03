<?php
// includes/db.php

// --- Funções de Acesso ao Banco de Dados (DAL) ---

// --- Usuários ---

function dbLogin($email, $senha, $conexao) {
    $sql = "SELECT id, nome, senha, perfil, ativo FROM usuarios WHERE email = ?"; // Adicione 'nome'
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        if ($usuario['ativo'] == 0) {
            return "Usuário desativado. Contate o administrador.";
        }
        return $usuario; // Retorna todos os dados do usuário, incluindo o nome.
    } else {
        return "Credenciais inválidas.";
    }
}
function dbGetNomeUsuario($conexao, $usuario_id) {
    $stmt = $conexao->prepare("SELECT nome FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ? $resultado['nome'] : null; // Retorna o nome ou null
}

function dbGetDadosUsuario($usuario_id, $conexao) {
    $stmt = $conexao->prepare("SELECT id, nome, email, perfil, foto, empresa_id FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    return $stmt->fetch();
}
//Função para buscar empresa pelo ID do usuario
function dbGetEmpresaDoUsuario($usuarioId, $conexao) {
    $stmt = $conexao->prepare("SELECT e.* FROM empresas e INNER JOIN usuarios u ON e.id = u.empresa_id WHERE u.id = ?");
    $stmt->execute([$usuarioId]);
    return $stmt->fetch(PDO::FETCH_ASSOC); // Retorna os dados da empresa ou null se não encontrar
}

// --- Solicitações de Acesso ---

function dbVerificaEmailExistente($email, $conexao) {
    //Verifica se o email ja foi cadastrado
    $stmt = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $email_existe_usuarios = $stmt->fetchColumn();

    $stmt = $conexao->prepare("SELECT COUNT(*) FROM solicitacoes_acesso WHERE email = ? and status = 'pendente'");
    $stmt->execute([$email]);
    $email_existe_solicitacoes = $stmt->fetchColumn();
    return [
        'usuarios' => $email_existe_usuarios,
        'solicitacoes' => $email_existe_solicitacoes,
    ];
}

function dbInserirSolicitacaoAcesso($nome, $email, $empresa, $motivo, $conexao) {
    $stmt = $conexao->prepare("INSERT INTO solicitacoes_acesso (nome_completo, email, empresa_id, motivo) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$nome, $email, $empresa, $motivo]);
}

// --- Logs ---

function dbRegistrarLogAcesso($usuario_id, $ip_address, $acao, $sucesso, $detalhes, $conexao) {
    $sql = "INSERT INTO logs_acesso (usuario_id, ip_address, acao, sucesso, detalhes) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$usuario_id ?? null, $ip_address, $acao, $sucesso, $detalhes]);
}

// --- Empresas --- (Função para buscar os dados das empresas)
function dbGetEmpresas($conexao) {
    $stmt = $conexao->prepare("SELECT id, nome FROM empresas ORDER BY nome");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Outras funções relacionadas ao banco de dados irão aqui ---