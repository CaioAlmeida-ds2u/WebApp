<?php
// admin/configuracoes_admin.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/layout_admin.php'; // Layout da área administrativa
require_once __DIR__ . '/../includes/admin_functions.php';


// Verifica se o usuário está logado e é um administrador
if (!usuarioEstaLogado() || $_SESSION['perfil'] !== 'admin') {
    redirecionarParaLogin($conexao); // Usa a função que agora está em config.php
}


$erro = '';
$sucesso = '';
$usuario_id = $_SESSION['usuario_id'];

// Carrega os dados atuais do usuário
$dadosUsuario = dbGetUsuario($usuario_id, $conexao);

if (!$dadosUsuario) {
    $erro = "Usuário não encontrado.";
}


// --- Processamento do Formulário ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarNovaSenha = $_POST['confirmar_nova_senha'] ?? '';

    // Validação
    $errors = [];

    if (empty($nome)) {
        $errors[] = "O campo Nome é obrigatório.";
    }
    if (empty($email)) {
        $errors[] = "O campo E-mail é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Por favor, insira um endereço de e-mail válido.";
    }

    //Verifica se o email já foi cadastrado, *excluindo* o usuário atual.
    $emailExiste = dbVerificaEmailExistente($email, $conexao);
    if($emailExiste['usuarios'] > 0){
        $errors[] = "Este e-mail já está cadastrado para outro usuário.";
    }


    // Se nova senha foi fornecida, validar
    if (!empty($novaSenha)) {
        if (empty($senhaAtual)) {
            $errors[] = "Para alterar a senha, você deve digitar a senha atual.";
        } elseif (!dbVerificaSenha($usuario_id, $senhaAtual, $conexao)) {
            $errors[] = "Senha atual incorreta.";
        } elseif ($novaSenha !== $confirmarNovaSenha) {
            $errors[] = "A nova senha e a confirmação não coincidem.";
        } elseif (strlen($novaSenha) < 8) {
            $errors[] = "A nova senha deve ter pelo menos 8 caracteres.";
        }
    }


    if (empty($errors)) {
        // Atualiza os dados
        $atualizacaoOK = dbAtualizarUsuario($usuario_id, $nome, $email, empty($novaSenha) ? null : $novaSenha, $conexao);

        if ($atualizacaoOK) {
            $sucesso = "Dados atualizados com sucesso!";
             // Atualiza o nome na sessão, se tiver sido modificado
            $_SESSION['nome'] = $nome;
             dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'atualizacao_perfil', 1, 'Perfil atualizado com sucesso.', $conexao);
            // Recarrega os dados do usuário (para refletir as mudanças)
            $dadosUsuario = dbGetUsuario($usuario_id, $conexao);
        } else {
            $erro = "Erro ao atualizar os dados. Por favor, tente novamente.";
             dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'atualizacao_perfil_erro', 0, 'Falha na atualização do perfil: ' . $erro, $conexao);
        }
    } else {
        $erro = implode("<br>", $errors);
    }
}

// --- Geração do HTML ---
$title = "Configurações do Perfil";
echo getHeaderAdmin($title);
?>

<div class="container mt-5">
    <h1>Configurações do Perfil</h1>

    <?php if ($erro): ?>
        <div class="alert alert-danger" role="alert">
            <?= $erro ?>
        </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alert alert-success" role="alert">
            <?= htmlspecialchars($sucesso) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="configuracoes_admin.php">
        <div class="mb-3">
            <label for="nome" class="form-label">Nome Completo</label>
            <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($dadosUsuario['nome']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($dadosUsuario['email']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="senha_atual" class="form-label">Senha Atual</label>
            <input type="password" class="form-control" id="senha_atual" name="senha_atual" autocomplete="current-password">
            <small class="form-text text-muted">Digite a senha atual para confirmar alterações ou alterar a senha.</small>
        </div>
        <div class="mb-3">
            <label for="nova_senha" class="form-label">Nova Senha</label>
            <input type="password" class="form-control" id="nova_senha" name="nova_senha" autocomplete="new-password">
             <small class="form-text text-muted">Deixe em branco para manter a senha atual.</small>
        </div>
        <div class="mb-3">
            <label for="confirmar_nova_senha" class="form-label">Confirmar Nova Senha</label>
            <input type="password" class="form-control" id="confirmar_nova_senha" name="confirmar_nova_senha" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
    </form>
</div>

<?php echo getFooterAdmin(); ?>