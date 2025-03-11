<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/gestor_func.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'gestor') {
    header('Location: ../acesso_negado.php');
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $auditoria_id = (int) $_GET['id'];
    $link = mysqli_connect("localhost", "root", "", "acoditools");

    $sql = "UPDATE auditorias SET status = 'rejeitada' WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $auditoria_id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['sucesso'] = "Auditoria rejeitada com sucesso!";
    } else {
        $_SESSION['erro'] = "Erro ao rejeitar auditoria.";
    }

    mysqli_stmt_close($stmt);
}

header("Location: dashboard_admin.php");
exit;
?>
