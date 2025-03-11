<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_func1.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../acesso_negado.php');
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $auditoria_id = (int) $_GET['id'];

    $sql = "UPDATE auditorias SET status = 'aprovada' WHERE id = ?";
    $stmt = $conexao->prepare($sql); // Usando PDO corretamente
    $stmt->execute([$auditoria_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['sucesso'] = "Auditoria aprovada com sucesso!";
    } else {
        $_SESSION['erro'] = "Erro ao aprovar auditoria ou nenhum registro alterado.";
    }
}

header("Location: dashboard_admin.php");
exit;
?>
