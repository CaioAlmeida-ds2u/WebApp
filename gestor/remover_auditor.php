<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Deletar o auditor
    $query = "DELETE FROM usuarios WHERE id = ?";
    $stmt = $conexao->prepare($query);

    if ($stmt->execute([$id])) {
        echo "<script>alert('Auditor removido com sucesso!'); window.location='gestao_auditores.php';</script>";
    } else {
        echo "<script>alert('Erro ao remover auditor.');</script>";
    }
} else {
    echo "<script>alert('ID do auditor não fornecido!'); window.location='gestao_auditores.php';</script>";
}
?>
