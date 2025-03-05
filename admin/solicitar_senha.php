<?php
// solicitar_senha.php

require_once __DIR__ . '/../includes/config.php'; // Configuração e conexão
require_once __DIR__ . '/../includes/layout_admin.php'; // Layout

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        $erro = "Por favor, insira seu e-mail.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Por favor, insira um e-mail válido.";
    } else {
        // 1. Verificar se o e-mail existe (e se o usuário está ativo).
        $stmt = $conexao->prepare("SELECT id FROM usuarios WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            $erro = "Este e-mail não está cadastrado ou a conta está inativa.";
        } else {
            // 2. Registrar a solicitação no banco de dados.
            $stmt = $conexao->prepare("INSERT INTO solicitacoes_reset_senha (usuario_id) VALUES (?)");
            $insercaoOK = $stmt->execute([$usuario['id']]);


            if ($insercaoOK) {
                $sucesso = "Sua solicitação de redefinição de senha foi enviada ao administrador.";
            } else {
                $erro = "Erro ao registrar a solicitação. Tente novamente.";
                error_log("Erro ao inserir solicitação de reset: " . $stmt->errorInfo()[2]); // Loga o erro
            }
            $stmt = null;
        }
    }
}

$title = "ACodITools - Solicitar Nova Senha";
echo getHeaderAdmin($title);
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="logo-container text-center mb-4">
                        <h1 class="mt-3">Recuperar Senha</h1>
                    </div>

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

                    <form method="POST" action="solicitar_senha.php">
                        <div class="mb-3">
                            <label for="email" class="form-label">Seu E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" required aria-label="Seu endereço de e-mail">
                            <div class="form-text">Insira o e-mail associado à sua conta.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Solicitar Redefinição</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="index.php" class="btn btn-link">Voltar para o Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php echo getFooterAdmin(); ?>