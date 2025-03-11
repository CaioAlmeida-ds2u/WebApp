<?php
// includes/auditorias_functions.php
require_once __DIR__ . '/config.php';

// Função para listar auditorias pendentes
function getAuditoriasPendentes($conn) {

    $link = mysqli_connect("localhost", "root", "", "acoditools");
    $query = "SELECT a.id, u.nome AS auditor, a.descricao, a.data_criacao ,a.status
              FROM auditorias a 
              JOIN usuarios u ON a.auditor_id = u.id";
    
    $result = mysqli_query($link, $query);
    $auditorias = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $auditorias[] = $row;
    }
    
    return $auditorias;
}

// Função para aprovar uma auditoria
function aprovarAuditoria($conn, $id) {
    $query = "UPDATE auditorias SET status = 'aprovada' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Função para rejeitar uma auditoria
function rejeitarAuditoria($conn, $id) {
    $query = "UPDATE auditorias SET status = 'rejeitada' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
