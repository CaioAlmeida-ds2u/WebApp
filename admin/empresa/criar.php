<?php
// admin/empresas/criar.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/layout_admin.php';
require_once __DIR__ . '/../../includes/functions.php';

protegerPagina();

//Verifica se é admin
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../../acesso_negado.php');
    exit;
}

$erro = '';
$sucesso = '';
$nome = '';
$cnpj = '';
$razao_social = '';
$endereco = '';
$contato = '';
$telefone = '';
$email = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $cnpj = $_POST['cnpj'] ?? null;
    $razao_social = $_POST['razao_social'] ?? null;
    $endereco = $_POST['endereco'] ?? null;
    $contato = $_POST['contato'] ?? null;
    $telefone = $_POST['telefone'] ?? null;
    $email = $_POST['email'] ?? null;
    // A logo será tratada separadamente

    $errors = [];

    if (empty($nome)) {
        $errors[] = "O campo Nome Fantasia é obrigatório.";
    }
    if (!empty($cnpj) && !validarCNPJ($cnpj)) {
         $errors[] = "CNPJ inválido.";
     }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Formato de e-mail inválido.";
    }


    // Outras validações (opcional)...

    if (empty($errors)) {
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

      $resultado = criarEmpresa($conexao, $dados);

        if ($resultado === true) {
            $_SESSION['sucesso'] = "Empresa criada com sucesso!";
             // --- LOG ---
            dbRegistrarLogAcesso(
                $_SESSION['usuario_id'],  // ID do usuário logado (administrador)
                $_SERVER['REMOTE_ADDR'],  // IP do usuário
                'criar_empresa',        // Ação
                1,                      // Sucesso (1 = true)
                "Empresa criada: ID " . $conexao->lastInsertId() . ", Nome: " . $nome, // Detalhes
                $conexao               // Conexão com o banco de dados
            );
            header('Location: index.php'); // Redireciona para a listagem
            exit;

        } else {
             $erro = $resultado; // A função criarEmpresa retorna a mensagem de erro
             // --- LOG ---
            dbRegistrarLogAcesso(
                $_SESSION['usuario_id'],  // ID do usuário logado (administrador)
                $_SERVER['REMOTE_ADDR'],
                'criar_empresa',
                0,  //  Falha: 0
                "Erro ao criar a empresa: " . $erro, //Detalhes do erro.
                $conexao
            );
            // --- FIM DO LOG ---
        }
    } else {
        $erro = implode("<br>", $errors);
         dbRegistrarLogAcesso(
            $_SESSION['usuario_id'],  // ID do usuário logado (administrador)
            $_SERVER['REMOTE_ADDR'],
            'criar_empresa',
            0,  //  Falha: 0
            "Erro de validação: " . $erro, //Detalhes do erro.
            $conexao
        );
    }
}


$title = "ACodITools - Adicionar Empresa";
echo getHeaderAdmin($title);
?>

<div class="container mt-5">
    <h1>Adicionar Nova Empresa</h1>

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

    <form method="POST" action="criar.php" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="nome" class="form-label">Nome Fantasia *</label>
            <input type="text" class="form-control" id="nome" name="nome" required value="<?= htmlspecialchars($nome ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="cnpj" class="form-label">CNPJ</label>
            <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?= htmlspecialchars($cnpj ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="razao_social" class="form-label">Razão Social</label>
            <input type="text" class="form-control" id="razao_social" name="razao_social" value="<?= htmlspecialchars($razao_social ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="endereco" class="form-label">Endereço</label>
            <textarea class="form-control" id="endereco" name="endereco" rows="3"><?= htmlspecialchars($endereco ?? '') ?></textarea>
        </div>
        <div class="mb-3">
            <label for="contato" class="form-label">Contato</label>
            <input type="text" class="form-control" id="contato" name="contato" value="<?= htmlspecialchars($contato ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="telefone" class="form-label">Telefone</label>
            <input type="text" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($telefone ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="logo" class="form-label">Logo</label>
            <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
        </div>

        <button type="submit" class="btn btn-primary">Criar Empresa</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
<?php echo getFooterAdmin(); ?>