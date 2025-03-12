<?php
// desativar_usuario.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_functions.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../acesso_negado.php');
    exit;
}

$erro = '';
$sucesso = '';

if (isset($_GET['id'])) {
    $usuario_id = $_GET['id'];

    if (!is_numeric($usuario_id)) {
        $erro = "ID de usuário inválido.";
    } else {
        $desativacaoOK = desativarUsuario($conexao, $usuario_id);

        if ($desativacaoOK) {
            $sucesso = "Usuário desativado com sucesso!";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Desativação de usuário", 1, "Usuário ID: $usuario_id desativado", $conexao);
        } else {
            $erro = "Erro ao desativar o usuário. Verifique se o usuário existe.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Desativação de usuário", 0, "Erro ao desativar usuário ID: $usuario_id", $conexao);
        }
    }
} else {
    $erro = "ID do usuário não fornecido.";
    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Desativação de usuário", 0, "ID do usuário não fornecido.", $conexao);

}

if ($sucesso) {
    $_SESSION['sucesso'] = $sucesso;
}
if ($erro) {
    $_SESSION['erro'] = $erro;
}
header('Location: usuarios.php');
exit;
?>