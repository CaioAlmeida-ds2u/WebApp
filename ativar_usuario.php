<?php
// ativar_usuario.php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_functions.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: acesso_negado.php');
    exit;
}

$erro = '';
$sucesso = '';

// 1. Obter o ID do usuário da URL (GET)
if (isset($_GET['id'])) {
    $usuario_id = $_GET['id'];

    // 2. Validar o ID (opcional, mas recomendado)
    if (!is_numeric($usuario_id)) {
        $erro = "ID de usuário inválido.";
    } else {
        // 3. Chamar a função para ativar o usuário
        $ativacaoOK = ativarUsuario($conexao, $usuario_id);

        if ($ativacaoOK) {
            $sucesso = "Usuário ativado com sucesso!";
            // Correto: $conexao é passada *fora* do array, como um argumento separado.
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Ativação de usuário", 1, "Usuário ID: $usuario_id ativado", $conexao);
        } else {
            $erro = "Erro ao ativar o usuário. Verifique se o usuário existe.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Ativação de usuário", 0, "Erro ao ativar usuário ID: $usuario_id", $conexao);
        }
    }

} else {
    $erro = "ID do usuário não fornecido.";
     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Ativação de usuário", 0, "ID do usuário não fornecido.", $conexao);
}

// 4. Redirecionar de volta para o dashboard do administrador (com mensagem)
if ($sucesso) {
    $_SESSION['sucesso'] = $sucesso;
}
if ($erro) {
    $_SESSION['erro'] = $erro;
}
header('Location: dashboard_admin.php');
exit;
?>