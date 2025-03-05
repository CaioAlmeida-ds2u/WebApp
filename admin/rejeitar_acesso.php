<?php
// rejeitar_acesso.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_functions.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../acesso_negado.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $solicitacao_id = $_GET['id'];

    if (!is_numeric($solicitacao_id)) {
        $erro = "ID da solicitação inválido.";
    } else {
       //Obtem os dados da solicitação
        $solicitacao = getSolicitacaoAcesso($conexao, $solicitacao_id);

        // Chamar a função para rejeitar a solicitação.
        $rejeicaoOK = rejeitarSolicitacaoAcesso($conexao, $solicitacao_id); // Poderiamos passar observações aqui, em um segundo argumento.

        if ($rejeicaoOK) {
            $sucesso = "Solicitação rejeitada com sucesso!";
             // Registrar log
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Rejeição de solicitação de acesso", 1, "Solicitação ID: $solicitacao_id rejeitada.", $conexao);

        } else {
            $erro = "Erro ao rejeitar a solicitação.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Rejeição de solicitação de acesso", 0, "Erro ao rejeitar solicitação ID: $solicitacao_id", $conexao);

        }
    }
} else {
    $erro = "ID da solicitação não fornecido.";
      dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Rejeição de solicitação de acesso", 0, "ID da solicitação não fornecido (rejeitar).", $conexao);

}

if ($sucesso) {
    $_SESSION['sucesso'] = $sucesso;
}
if ($erro) {
    $_SESSION['erro'] = $erro;
}
header('Location: dashboard_admin.php'); // Redireciona de volta para o dashboard
exit;
?>