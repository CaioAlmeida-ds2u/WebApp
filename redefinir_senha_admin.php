<?php
// redefinir_senha_admin.php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_functions.php';
require_once __DIR__ . '/includes/layout_admin.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: acesso_negado.php');
    exit;
}

$erro = '';
$sucesso = '';
$usuario = null; // Inicializa

if (isset($_GET['id'])) {
    $solicitacao_id = $_GET['id'];

    // Buscar dados da solicitação e do usuário associado
    $solicitacao = getSolicitacaoAcesso($conexao, $solicitacao_id); // Usamos a mesma função

    if ($solicitacao) {
        //Obter os dados do usuário
        $usuario = getDadosUsuarioPorSolicitacaoReset($conexao, $solicitacao_id); //Obtem os dados do usuário

        if (!$usuario) {
            $erro = "Usuário não encontrado para esta solicitação.";
        }
    } else {
        $erro = "Solicitação de reset de senha não encontrada.";
    }

} else {
    $erro = "ID da solicitação não fornecido.";
}

// Processar o formulário de nova senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

     $errors = [];
    // Validação da senha
    if (strlen($nova_senha) < 8) {
        $errors[] = "A senha deve ter pelo menos 8 caracteres.";
    }
    if (!preg_match("#[0-9]+#", $nova_senha)) {
        $errors[] = "A senha deve conter pelo menos um número.";
    }
    if (!preg_match("#[a-z]+#", $nova_senha)) {
        $errors[] = "A senha deve conter pelo menos uma letra minúscula.";
    }
    if (!preg_match("#[A-Z]+#", $nova_senha)) {
        $errors[] = "A senha deve conter pelo menos uma letra maiúscula.";
    }
    if (!preg_match("#[\W]+#", $nova_senha)) {  // \W representa caracteres não alfanuméricos
        $errors[] = "A senha deve conter pelo menos um caractere especial.";
    }

    if ($nova_senha !== $confirmar_senha) {
        $erro = "As senhas não coincidem.";
    }elseif(count($errors) > 0){
        $erro = implode("<br>", $errors);
    } else {
        // Redefinir a senha
        $redefinicaoOK = redefinirSenha($conexao, $usuario['id'], $nova_senha);

        if ($redefinicaoOK) {
             // Aprovar a solicitação (atualizar status)
            $aprovacaoOK = aprovarSolicitacaoReset($conexao, $solicitacao_id, $_SESSION['usuario_id']);

            if($aprovacaoOK){
                 $sucesso = "Senha redefinida com sucesso!";
                  // Registrar log de sucesso
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Redefinição de senha", 1, "Senha redefinida para o usuário ID: {$usuario['id']} pelo Admin ID: {$_SESSION['usuario_id']}", $conexao);

                // Destruir a sessão (logout) após a redefinição
                session_destroy();
                //Redireciona com sucesso
                $_SESSION['sucesso'] = $sucesso;
                header('Location: index.php');
                exit;
            }else{
                $erro = "Erro ao aprovar a solicitação de reset.";
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Redefinição de senha", 0, "Erro ao aprovar a solicitação de reset de senha (ID da solicitação: $solicitacao_id) para o usuário ID: {$usuario['id']}", $conexao);

            }

        } else {
            $erro = "Erro ao redefinir a senha. Tente novamente.";
              dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "Redefinição de senha", 0, "Erro ao redefinir a senha para o usuário ID: {$usuario['id']}", $conexao);
        }
    }
}


$title = "ACodITools - Redefinir Senha (Admin)";
echo getHeaderAdmin($title);
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Redefinir Senha</h2>

                    <?php if ($erro): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($erro) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($sucesso): ?>
                        <div class="alert alert-success" role="alert">
                            <?= htmlspecialchars($sucesso) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($usuario): ?>
                        <p>Usuário: <b><?= htmlspecialchars($usuario['nome']) ?></b> (<?= htmlspecialchars($usuario['email']) ?>)</p>

                        <form method="POST" action="redefinir_senha_admin.php?id=<?= htmlspecialchars($solicitacao_id) ?>">
                            <div class="mb-3">
                                <label for="nova_senha" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Redefinir Senha</button>
                             <a href="dashboard_admin.php" class="btn btn-secondary">Cancelar</a>
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