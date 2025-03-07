<?php
// /includes/gestao_func.php --- Funções para os gestores.

function getAuditores($conexao) {
    $query = "SELECT id, nome, email FROM usuarios WHERE perfil = 'auditor'";
    $stmt = $conexao->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}
?>
