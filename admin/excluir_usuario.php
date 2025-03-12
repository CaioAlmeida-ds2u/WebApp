<?php
// admin/excluir_usuario.php

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
        //Verifica se o usuário a ser excluído é o mesmo que está logado.
        if($usuario_id == $_SESSION['usuario_id']){
            $erro = "Você não pode excluir sua própria conta.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Exclusão de usuário", 0, "Tentativa de excluir a própria conta. ID do Usuário: $usuario_id", $conexao);

        }else{
            // Excluir o usuário
            $exclusaoOK = excluirUsuario($conexao, $usuario_id);

            if ($exclusaoOK) {
                $sucesso = "Usuário excluído com sucesso!";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Exclusão de usuário", 1, "Usuário ID: $usuario_id excluído com sucesso pelo Admin ID: {$_SESSION['usuario_id']}", $conexao);
            } else {
                $erro = "Erro ao excluir o usuário.  Verifique se o usuário existe e se não há dependências.";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Exclusão de usuário", 0, "Erro ao excluir usuário ID: $usuario_id. Possível erro de chave estrangeira.", $conexao);
            }
        }
    }

} else {
    $erro = "ID do usuário não fornecido.";
    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Exclusão de usuário", 0, "ID do usuário não fornecido (excluir).", $conexao);
}

if ($sucesso) {
    $_SESSION['sucesso'] = $sucesso;
}
if ($erro) {
    $_SESSION['erro'] = $erro;
}
header('Location: usuarios.php');  // Caminho relativo!
exit;
?>