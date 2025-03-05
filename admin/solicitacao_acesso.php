<?php
// solicitacao_acesso.php
require_once __DIR__ . '/../includes/config.php'; //Configuração e conexao
require_once __DIR__ . '/../includes/layout_admin.php'; //Layout

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $empresa = $_POST['empresa'] ?? '';
    $motivo = $_POST['motivo'] ?? '';

    $errors = [];

    if (empty($nome)) {
        $errors[] = "O campo Nome Completo é obrigatório.";
    }
    if (empty($email)) {
        $errors[] = "O campo E-mail é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Por favor, insira um endereço de e-mail válido.";
    }
    if (empty($empresa)) {
        $errors[] = "Selecione uma empresa.";
    }
    if (empty($motivo)) {
        $errors[] = "O campo Motivo é obrigatório.";
    }

    //Verifica se o email já foi cadastrado usando a função do db.php
    $verificacao = dbVerificaEmailExistente($email, $conexao);
    if ($verificacao['usuarios'] > 0) {
        $errors[] = "Este e-mail já está cadastrado.";
    }
    if ($verificacao['solicitacoes'] > 0) {
        $errors[] = "Já existe uma solicitação de acesso pendente para este e-mail.";
    }


    if (empty($errors)) {
        // Inserir a solicitação usando a função do db.php
        $insercaoOK = dbInserirSolicitacaoAcesso($nome, $email, $empresa, $motivo, $conexao);
        if ($insercaoOK) {
            $sucesso = "Sua solicitação de acesso foi enviada com sucesso e aguarda aprovação.";
        } else {
            $erro = "Erro ao enviar a solicitação. Por favor, tente novamente.";
        }
    } else {
        $erro = implode("<br>", $errors);
    }
}

//Busca a lista de empresas, usando a função do db.php
$empresas = dbGetEmpresas($conexao);

$title = "ACodITools - Solicitação de Acesso";
echo getHeaderAdmin($title);
?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Solicitação de Acesso</h2>
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

                        <form method="POST" action="solicitacao_acesso.php" id="formSolicitacao">
                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <input type="text" class="form-control" id="nome" name="nome" required
                                       aria-label="Seu nome completo">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       autocomplete="email" aria-label="Seu endereço de e-mail">
                            </div>
                            <div class="mb-3">
                                <label for="empresa" class="form-label">Empresa</label>
                                <select class="form-select" id="empresa" name="empresa" required
                                        aria-label="Selecione a empresa">
                                    <option value="" selected>Selecione...</option>
                                    <?php foreach ($empresas as $empresa): ?>
                                        <option value="<?= htmlspecialchars($empresa['id']) ?>">
                                            <?= htmlspecialchars($empresa['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                    <label for="motivo" class="form-label">Motivo da Solicitação</label>
                                    <textarea class="form-control" id="motivo" name="motivo" rows="4" required aria-label="Descreva o motivo da sua solicitação"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Enviar Solicitação</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="index.php" class="btn btn-link">Voltar para o Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
                                        
    <script>
    document.getElementById('formSolicitacao').addEventListener('submit', function(event) {
        let nome = document.getElementById('nome').value;
        let email = document.getElementById('email').value;
        let empresa = document.getElementById('empresa').value;
        let motivo = document.getElementById('motivo').value;
        let errors = [];
    
        if (!nome) {
            errors.push("O campo Nome Completo é obrigatório.");
        }
        if (!email) {
            errors.push("O campo E-mail é obrigatório.");
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.push("Por favor, insira um endereço de e-mail válido.");
        }
        if (!empresa) {
            errors.push("Selecione uma empresa.");
        }
        if (!motivo) {
            errors.push("O campo Motivo é obrigatório.");
        }
    
        if (errors.length > 0) {
            event.preventDefault(); // Impede o envio
            let errorMsg = errors.join("<br>");
            let errorDiv = document.getElementById('error-message');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.id = 'error-message';
                errorDiv.classList.add('alert', 'alert-danger');
                document.querySelector('form').prepend(errorDiv);
            }
            errorDiv.innerHTML = errorMsg;
        }
    });
</script>

<?php echo getFooterAdmin(); ?>