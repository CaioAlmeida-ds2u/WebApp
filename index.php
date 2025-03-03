<?php
// index.php

require_once __DIR__ . '/includes/config.php'; // Inclui configurações e funções
require_once __DIR__ . '/includes/layout_index.php';
require_once __DIR__ . '/includes/db.php'; // Inclui funções de banco de dados

redirecionarUsuarioLogado();

$erro = '';

// Verifica se o formulário foi submetido (método POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        // Tenta fazer login (chama a função login() do db.php, passando a conexão)
        $resultado = dbLogin($email, $senha, $conexao);

        if (is_array($resultado)) { // Se o login foi bem-sucedido, $resultado é o array com os dados do usuário
            $_SESSION['usuario_id'] = $resultado['id'];
            $_SESSION['perfil'] = $resultado['perfil'];
            $_SESSION['nome'] = $resultado['nome'];

              //Registra o log
            dbregistrarLogAcesso($resultado['id'], $_SERVER['REMOTE_ADDR'], 'login_sucesso', 1,  'Usuário logado com sucesso', $conexao);

            // Redirecionamento com base no perfil
            if ($resultado['perfil'] === 'admin') {
                header('Location: dashboard_admin.php');
            } else {
                header('Location: dashboard_auditor.php');
            }
            exit;

        } else { // Se o login falhou, $resultado é a mensagem de erro
            $erro = $resultado;
            //Registra o log de erro
            dbregistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'login_falha', 0, 'Tentativa de login: ' . $erro, $conexao);
        }
    }
}

// --- Início da Geração do HTML ---
$title = "ACodITools - Login";
echo getHeaderIndex($title);
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="logo-container">
                        <img src="assets/img/ACodITools_logo.png" alt="ACodITools Logo" class="img-fluid">
                        <h1>ACodITools</h1>
                    </div>
                    <?php if ($erro): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($erro) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php" id="loginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" required autocomplete="email" aria-label="Endereço de e-mail">
                        </div>
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha" name="senha" required autocomplete="current-password" aria-label="Senha">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Entrar</button>

                        <div class="forgot-password text-center mt-3">
                            <a href="solicitar_senha.php">Esqueci minha senha</a>
                        </div>
                        <p class="text-center mt-2">
                            Não tem conta?
                            <a href="solicitacao_acesso.php" class="signup-link">Solicite acesso</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Validação do lado do cliente (opcional, mas recomendado)
    document.getElementById('loginForm').addEventListener('submit', function(event) {
        let email = document.getElementById('email').value;
        let senha = document.getElementById('senha').value;
        let errors = [];

        if (!email) {
            errors.push("O campo de e-mail é obrigatório.");
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.push("Por favor, insira um endereço de e-mail válido.");
        }

        if (!senha) {
            errors.push("O campo de senha é obrigatório.");
        }

        if (errors.length > 0) {
            event.preventDefault(); // Impede o envio do formulário
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
<?php
echo getFooterIndex();
?>