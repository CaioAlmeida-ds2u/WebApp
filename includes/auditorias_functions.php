<?php
// includes/auditorias_functions.php
require_once __DIR__ . '/config.php';
session_start(); // Garante que a sessão está ativa para capturar o usuário logado

// Função para listar auditorias pendentes
function getAuditoriasPendentes($conexao) {
    $query = "SELECT a.id, u.nome AS auditor, a.descricao, a.data_criacao, a.status
              FROM auditorias a 
              JOIN usuarios u ON a.auditor_id = u.id";
    
    $stmt = $conexao->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para aprovar uma auditoria
function aprovar_auditoria($conexao, $auditoria_id, $observacoes = '') {
    return atualizarStatusAuditoria($conexao, $auditoria_id, 'aprovada', $observacoes);
}

// Função para rejeitar uma auditoria
function rejeitar_auditoria($conexao, $auditoria_id, $observacoes = '') {
    return atualizarStatusAuditoria($conexao, $auditoria_id, 'rejeitada', $observacoes);
}

// Função interna para atualizar status
function atualizarStatusAuditoria($conexao, $auditoria_id, $status, $observacoes) {
    if (!isset($_SESSION['usuario_id'])) {
        return ["success" => false, "message" => "Usuário não autenticado."];
    }

    $sql = "UPDATE auditorias SET status = ?, admin_id = ?, data_aprovacao = NOW(), observacoes = ? WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    
    if ($stmt->execute([$status, $_SESSION['usuario_id'], $observacoes, $auditoria_id])) {
        return ["success" => true, "message" => "Auditoria {$status} com sucesso!"];
    } else {
        return ["success" => false, "message" => "Erro ao atualizar a auditoria."];
    }
}
?>
