<?php
// editar_usuario.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/layout_admin.php'; // Usando layout do admin

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../acesso_negado.php');
    exit;
}

$erro = '';
$sucesso = '';
$usuario = null;

// 1. Obter o ID do usuário da URL (GET)
if (isset($_GET['id'])) {
    $usuario_id = $_GET['id'];

    // 2. Buscar os dados do usuário no banco de dados
    $usuario = getUsuario($conexao, $usuario_id);  // Usamos a função do admin_functions.php

    if (!$usuario) {
        $erro = "Usuário não encontrado.";
    }
} else {
    $erro = "ID do usuário não fornecido.";
}


// 3. Processar o formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $perfil = $_POST['perfil'] ?? '';
    $ativo = isset($_POST['ativo']) ? 1 : 0; // Checkbox: se marcado, 1; senão, 0.

    // Validação
    $errors = [];
    if (empty($nome)) {
        $errors[] = "O nome é obrigatório.";
    }
    if (empty($email)) {
        $errors[] = "O e-mail é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "E-mail inválido.";
    }
     //Verifica se o email já existe em outro usuário
    if (dbEmailExisteEmOutroUsuario($email, $usuario_id, $conexao)) {
      $errors[] = "Este e-mail já está sendo usado por outro usuário.";
    }

    if (!in_array($perfil, ['admin', 'gestor', 'auditor'])) {
        $errors[] = "Perfil inválido.";
    }

    if (empty($errors)) {
        // Atualizar os dados do usuário
        $atualizacaoOK = atualizarUsuario($conexao, $usuario_id, $nome, $email, $perfil, $ativo);

        if ($atualizacaoOK) {
            //$sucesso = "Usuário atualizado com sucesso!";  // REMOVER - Redireciona agora

            //Renova a sessão, caso o admin edite o proprio perfil
            if($_SESSION['usuario_id'] == $usuario_id){
                $_SESSION['perfil'] = $usuario['perfil'];
            }

            // Adicionar mensagem de sucesso na sessão
            $_SESSION['sucesso'] = "Usuário atualizado com sucesso!";

            // Redirecionar para o dashboard do administrador
            header('Location: dashboard_admin.php');
            exit; // Importante: Terminar a execução após o redirecionamento

        } else {
            $erro = "Erro ao atualizar o usuário. Tente novamente.";
        }
    } else {
         $erro = implode("<br>", $errors);
    }
}

$title = "ACodITools - Editar Usuário";
echo getHeaderAdmin($title);
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Editar Usuário</h2>

                    <?php if ($erro): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $erro ?>
                        </div>
                    <?php endif; ?>

                    <?php // if ($sucesso):  REMOVIDO - A mensagem agora vai via sessão ?>

                    <?php if ($usuario): // Só exibe o formulário se o usuário existir ?>
                        <form method="POST" action="editar_usuario.php?id=<?= htmlspecialchars($usuario['id']) ?>">
                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="perfil" class="form-label">Perfil</label>
                                <select class="form-select" id="perfil" name="perfil" required>
                                    <option value="admin" <?= $usuario['perfil'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                    <option value="gestor" <?= $usuario['perfil'] === 'gestor' ? 'selected' : '' ?>>Gestor</option>
                                    <option value="auditor" <?= $usuario['perfil'] === 'auditor' ? 'selected' : '' ?>>Auditor</option>
                                </select>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="ativo" name="ativo" <?= $usuario['ativo'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ativo">Ativo</label>
                            </div>

                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                            <a href="dashboard_admin.php" class="btn btn-secondary">Cancelar</a>  </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<?php echo getFooterAdmin(); ?>