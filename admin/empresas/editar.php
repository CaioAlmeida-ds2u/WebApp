<?php
// admin/empresas/editar.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_func1.php';
require_once __DIR__ . '/../../includes/admin_func2.php';
require_once __DIR__ . '/../../includes/layout_admin.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../../acesso_negado.php'); // Redireciona se não for admin
    exit;
}

$erro = '';
$sucesso = '';
$empresa = null; // Inicializa a variável

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $empresa_id = $_GET['id'];
     //Verificação se o ID é numérico
    if (!is_numeric($empresa_id)) {
      $erro = "ID inválido.";
      header("Location: index.php");
      exit();
    }
    $empresa = getEmpresa($conexao, $empresa_id);

    if (!$empresa) {
        $_SESSION['erro'] = "Empresa não encontrada.";
        header('Location: index.php'); // Redireciona se a empresa não existe
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {

    $empresa_id = $_POST['id'];
     //Verificação se o ID é numérico
    if (!is_numeric($empresa_id)) {
      $erro = "ID inválido.";
      header("Location: index.php");
      exit();
    }
    // 1. Receber e validar os dados do formulário (similar ao criar.php)
    $nome = $_POST['nome'] ?? '';
    $cnpj = $_POST['cnpj'] ?? null;
    $razao_social = $_POST['razao_social'] ?? null;
    $endereco = $_POST['endereco'] ?? null;
    $contato = $_POST['contato'] ?? null;
    $telefone = $_POST['telefone'] ?? null;
    $email = $_POST['email'] ?? null;

     $errors = [];

    if (empty($nome)) {
        $errors[] = "O campo Nome Fantasia é obrigatório.";
    }

     //Validação de CNPJ (opcional, mas recomendado)
    if (!empty($cnpj) && !validarCNPJ($cnpj)) {
        $errors[] = "CNPJ inválido.";
    }

    // Validação de e-mail (opcional, mas recomendado)
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
       $errors[] = "Formato de e-mail inválido.";
    }


    if (empty($errors)) {
        // 2. Preparar os dados para atualização
        $dados = [
            'nome' => $nome,
            'cnpj' => $cnpj,
            'razao_social' => $razao_social,
            'endereco' => $endereco,
            'contato' => $contato,
            'telefone' => $telefone,
            'email' => $email,
            // A logo será tratada separadamente, se necessário
        ];

        // 3. Chamar a função para atualizar a empresa
        $atualizacaoOK = atualizarEmpresa($conexao, $empresa_id, $dados);

        if ($atualizacaoOK) {
            $sucesso = "Empresa atualizada com sucesso!";
              // --- LOG ---
            dbRegistrarLogAcesso(
                $_SESSION['usuario_id'],
                $_SERVER['REMOTE_ADDR'],
                'editar_empresa',
                1, //Sucesso
                "Empresa ID: $empresa_id atualizada.",
                $conexao
            );
        // --- FIM DO LOG ---
            header('Location: index.php'); // Redireciona para a listagem
            exit;

        } else {
            $erro = "Erro ao atualizar a empresa. Tente novamente.";
            // --- LOG ---
            dbRegistrarLogAcesso(
                $_SESSION['usuario_id'],
                $_SERVER['REMOTE_ADDR'],
                'editar_empresa',
                0, //Falha
                "Erro ao editar a empresa ID: $empresa_id - " . $erro,
                $conexao
            );
             // --- FIM DO LOG ---
        }

    } else {
        $erro = implode("<br>", $errors);
         // --- LOG ---
        dbRegistrarLogAcesso(
            $_SESSION['usuario_id'],  // ID do usuário logado (administrador)
            $_SERVER['REMOTE_ADDR'],
            'editar_empresa',
            0,  //  Falha: 0
            "Erro de validação: " . $erro, //Detalhes do erro.
            $conexao
        );
        // --- FIM DO LOG ---
    }

    // Recarregar os dados da empresa (após a tentativa de atualização)
    $empresa = getEmpresa($conexao, $empresa_id);
    if (!$empresa) {
        $_SESSION['erro'] = "Empresa não encontrada.";
        header('Location: index.php');
        exit;
    }

} else {
    header('Location: index.php'); // Redireciona se não houver ID na URL
    exit;
}

$title = "ACodITools - Editar Empresa";
echo getHeaderAdmin($title);

?>

<div class="container mt-5">
    <h1>Editar Empresa</h1>

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

    <form method="POST" action="editar.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= htmlspecialchars($empresa['id']) ?>">

        <div class="mb-3">
            <label for="nome" class="form-label">Nome Fantasia *</label>
            <input type="text" class="form-control" id="nome" name="nome" required value="<?= htmlspecialchars($empresa['nome']) ?>">
        </div>
        <div class="mb-3">
            <label for="cnpj" class="form-label">CNPJ</label>
            <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?= htmlspecialchars($empresa['cnpj'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="razao_social" class="form-label">Razão Social</label>
            <input type="text" class="form-control" id="razao_social" name="razao_social" value="<?= htmlspecialchars($empresa['razao_social'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="endereco" class="form-label">Endereço</label>
            <textarea class="form-control" id="endereco" name="endereco" rows="3"><?= htmlspecialchars($empresa['endereco'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
            <label for="contato" class="form-label">Contato</label>
            <input type="text" class="form-control" id="contato" name="contato" value="<?= htmlspecialchars($empresa['contato'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="telefone" class="form-label">Telefone</label>
            <input type="text" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($empresa['telefone'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($empresa['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="logo" class="form-label">Logo</label>
            <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
        </div>

        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
<?php echo getFooterAdmin(); ?>