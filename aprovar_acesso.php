<?php
// aprovar_acesso.php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_functions.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: acesso_negado.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $solicitacao_id = $_GET['id'];

    if (!is_numeric($solicitacao_id)) {
        $erro = "ID da solicitação inválido.";
    } else {
        // Gerar senha temporária (8 caracteres aleatórios)
        $senha_temporaria = bin2hex(random_bytes(4));

        // Chamar a função para aprovar a solicitação
        $aprovacaoOK = aprovarSolicitacaoAcesso($conexao, $solicitacao_id, $senha_temporaria);

        if ($aprovacaoOK) {
            $sucesso = "Solicitação aprovada com sucesso!  A senha temporária do novo usuário é: <b>" . htmlspecialchars($senha_temporaria) . "</b>";
             // Registrar log de sucesso (usando a função do config.php)
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Aprovação de solicitação de acesso", 1, "Solicitação ID: $solicitacao_id aprovada.  Novo usuário criado.", $conexao);

        } else {
            $erro = "Erro ao aprovar a solicitação.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Aprovação de solicitação de acesso", 0, "Erro ao aprovar solicitação ID: $solicitacao_id.", $conexao);
        }
    }
} else {
    $erro = "ID da solicitação não fornecido.";
     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Aprovação de solicitação de acesso", 0, "ID da solicitação não fornecido (aprovar).", $conexao);
}


if ($sucesso) {
    $_SESSION['sucesso'] = $sucesso;
}
if ($erro) {
    $_SESSION['erro'] = $erro;
}
header('Location: dashboard_admin.php');
exit;
?>