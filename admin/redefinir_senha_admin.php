<?php
// redefinir_senha_admin.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/layout_admin.php';

protegerPagina($conexao);

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../acesso_negado.php');
    exit;
}

$erro = '';
$sucesso = '';
$usuario = null; // Inicializa

if (isset($_GET['id'])) {
    $solicitacao_id = $_GET['id'];

    // Buscar dados da solicitação e do usuário associado
    $solicitacao = getSolicitacaoReset($conexao, $solicitacao_id);

    if ($solicitacao) {
        //Obter os dados do usuário
        $usuario = getDadosUsuarioPorSolicitacaoReset($conexao, $solicitacao_id);

        if (!$usuario) {
            $erro = "Usuário não encontrado para esta solicitação.";
        }
    } else {
        $erro = "Solicitação de reset de senha não encontrada.";
    }

} else {
    $erro = "ID da solicitação não fornecido.";
}

// Processar a geração de senha temporária
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    // 1. Gerar senha temporária
    $senha_temporaria = bin2hex(random_bytes(8)); // Gera uma senha aleatória de 16 caracteres hexadecimais (8 bytes)
    $senha_hash = password_hash($senha_temporaria, PASSWORD_DEFAULT);

    // 2. Atualizar a senha do usuário no banco de dados e marcar para primeiro acesso
    $redefinicaoOK = redefinirSenha($conexao, $usuario['id'], $senha_hash);

    if ($redefinicaoOK) {
        // 3. Aprovar a solicitação de reset (atualizar status na tabela de solicitações)
        $aprovacaoOK = aprovarSolicitacaoReset($conexao, $solicitacao_id, $_SESSION['usuario_id']);

        if($aprovacaoOK){
            
            $sucesso = "Senha temporária gerada e usuário marcado para primeiro acesso com sucesso! Senha Temporária: " . $senha_temporaria ."";
            // Registrar log de sucesso
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Geração de senha temporária", 1, "Senha temporária gerada para o usuário ID: {$usuario['id']} pelo Admin ID: {$_SESSION['usuario_id']}. Usuário marcado para primeiro acesso.", $conexao);

            $_SESSION['sucesso'] = $sucesso; // Armazena a mensagem de sucesso na sessão.
            header('Location: usuarios.php'); // Redireciona para o dashboard.
            exit; // Importante: Termina a execução após o redirecionamento.
        } else {
            $erro = "Erro ao aprovar a solicitação de reset.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Geração de senha temporária", 0, "Erro ao aprovar a solicitação de reset de senha (ID da solicitação: $solicitacao_id) para o usuário ID: {$usuario['id']}", $conexao);
        }

    } else {
        $erro = "Erro ao gerar e salvar a senha temporária. Tente novamente.";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Geração de senha temporária", 0, "Erro ao gerar e salvar a senha temporária para o usuário ID: {$usuario['id']}", $conexao);
    }
}

$title = "ACodITools - Gerar Senha Temporária (Admin)";
header('Content-Type: text/html; charset=utf-8'); // Adicionado para garantir a renderização correta do HTML
echo getHeaderAdmin($title);
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Gerar Senha Temporária</h2>
                    <?php if ($erro): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $erro ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['sucesso'])): ?>
                        <div class="alert alert-success" role="alert">
                            <?= $_SESSION['sucesso'] ?>
                        </div>
                        <?php unset($_SESSION['sucesso']); ?>
                    <?php elseif ($sucesso): ?>
                        <div class="alert alert-success" role="alert">
                            <?= $sucesso ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($usuario): ?>
                        <p>Usuário: <b><?= htmlspecialchars($usuario['nome']) ?></b> (<?= htmlspecialchars($usuario['email']) ?>)</p>
                        <form method="POST" action="redefinir_senha_admin.php?id=<?= htmlspecialchars($solicitacao_id) ?>">
                            <p>Clique no botão abaixo para gerar uma senha temporária para este usuário e marcá-lo para o primeiro acesso.</p>
                            <button type="submit" class="btn btn-primary">Gerar Senha Temporária</button>
                            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                        </form>
                    <?php else: ?>
                        <p>Solicitação de reset de senha inválida.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php echo getFooterAdmin(); ?>
