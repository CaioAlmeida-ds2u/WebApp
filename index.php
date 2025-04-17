<?php
// index.php

// Inclui configurações, conexão DB, funções base e GERA/OBTÉM $csrf_token
require_once __DIR__ . '/includes/config.php';
// Inclui funções para gerar header/footer HTML
require_once __DIR__ . '/includes/layout_index.php';
// REMOVIDO: require_once __DIR__ . '/includes/db.php'; (Já incluído por config.php)

// Se o usuário já estiver logado, redireciona para o dashboard apropriado
redirecionarUsuarioLogado();

$erro = ''; // Inicializa variável de erro

// --- Processamento do Formulário de Login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Token inválido ou ausente - Falha de segurança
        $erro = "Erro de validação da sessão. Por favor, tente recarregar a página e fazer login novamente.";
        // Logar a falha de CSRF
        dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'login_csrf_falha', 0, 'Token CSRF inválido ou ausente na tentativa de login.', $conexao);

    } else {
        // Token CSRF válido, processar login
        $email = trim($_POST['email'] ?? ''); // Use trim para remover espaços extras
        $senha = $_POST['senha'] ?? '';     // Senha não deve ter trim

        if (empty($email) || empty($senha)) {
            $erro = "Por favor, preencha todos os campos (e-mail e senha).";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // Validação básica de email no servidor
             $erro = "Por favor, insira um endereço de e-mail válido.";
        } else {
            // Tenta fazer login (função do db.php)
            $resultadoLogin = dbLogin($email, $senha, $conexao);

            if (is_array($resultadoLogin)) {
                // --- Login BEM-SUCEDIDO ---

                // 1. Regenerar ID da Sessão (Previne Session Fixation)
                session_regenerate_id(true);

                // 2. Gerar um NOVO token CSRF para a nova sessão
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // 3. Registrar log de sucesso
                // Os dados da sessão (id, nome, perfil, foto) já foram definidos dentro de dbLogin()
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'login_sucesso', 1, 'Usuário logado com sucesso.', $conexao);

                // 4. Redirecionamento com base no perfil (Usando BASE_URL)
                if ($_SESSION['perfil'] === 'admin') {
                    header('Location: ' . BASE_URL . 'admin/dashboard_admin.php');
                } else {
                    // Assumindo que outros perfis (auditor) vão para dashboard_auditor.php
                    header('Location: ' . BASE_URL . 'auditor/dashboard_auditor.php');
                }
                exit; // Termina a execução após o redirecionamento

            } else {
                // --- Login FALHOU ---
                $erro = $resultadoLogin; // Mensagem de erro vinda de dbLogin()
                // Registrar log de falha (inclui email para análise, mas cuidado com privacidade)
                dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'login_falha', 0, 'Tentativa de login falhou: ' . $erro . ' (Email: ' . htmlspecialchars($email) . ')', $conexao);
            }
        }
    }
} // Fim do if ($_SERVER['REQUEST_METHOD'] === 'POST')


// --- Preparação para Geração do HTML ---
$title = "ACodITools - Login";

// --- Início da Geração do HTML ---
echo getHeaderIndex($title); // Inclui <!DOCTYPE>, <head>, abre <body> e <div class="main-content">
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5"> <?php /* Ajuste leve na coluna */ ?>
            <div class="card">
                <div class="card-body">
                    <div class="logo-container">
                        <!-- Usar BASE_URL para a imagem -->
                        <img src="<?= BASE_URL ?>assets/img/ACodITools_logo.png" alt="ACodITools Logo" class="img-fluid">
                        <h1>ACodITools</h1>
                    </div>

                    <?php /* Exibe a mensagem de erro do servidor (PHP) se houver */ ?>
                    <?php if (!empty($erro)): ?>
                        <div id="server-error-message" class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($erro) ?>
                        </div>
                    <?php endif; ?>

                    <?php /* Div para mensagens de erro do JavaScript (inicialmente vazio) */ ?>
                    <div id="js-error-message" class="alert alert-danger" role="alert" style="display: none;"></div>

                    <form method="POST" action="index.php" id="loginForm">
                        <?php /* Campo oculto com o Token CSRF */ ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" required autocomplete="email" aria-describedby="emailHelp">
                             <div id="emailHelp" class="form-text visually-hidden">Seu endereço de e-mail para login.</div>
                        </div>
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha" name="senha" required autocomplete="current-password" aria-describedby="passwordHelp">
                             <div id="passwordHelp" class="form-text visually-hidden">Sua senha de acesso.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Entrar</button>

                        <div class="forgot-password text-center mt-3">
                            <a href="<?= BASE_URL ?>solicitar_senha.php">Esqueci minha senha</a>
                        </div>
                        <p class="text-center mt-2 mb-0"> <?php /* Removido margin bottom extra */ ?>
                            Não tem conta?
                            <a href="<?= BASE_URL ?>solicitacao_acesso.php" class="signup-link">Solicite acesso</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php /* JavaScript de Validação Client-Side */ ?>
<script>
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const senhaInput = document.getElementById('senha');
    const jsErrorDiv = document.getElementById('js-error-message');
    const serverErrorDiv = document.getElementById('server-error-message'); // Para poder ocultá-lo

    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            let errors = [];
            jsErrorDiv.style.display = 'none'; // Esconde erros anteriores do JS
            jsErrorDiv.innerHTML = '';

            // Oculta erro do servidor se houver validação JS (opcional, mas evita confusão)
             if (serverErrorDiv) {
                // serverErrorDiv.style.display = 'none';
             }


            const email = emailInput.value.trim();
            const senha = senhaInput.value; // Não usar trim em senha

            if (!email) {
                errors.push("O campo de e-mail é obrigatório.");
            } else {
                // Regex simples para validação de formato de email (não garante existência)
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    errors.push("Por favor, insira um endereço de e-mail válido.");
                }
            }

            if (!senha) {
                errors.push("O campo de senha é obrigatório.");
            }
            // Poderia adicionar validação de tamanho mínimo de senha aqui se desejado

            if (errors.length > 0) {
                event.preventDefault(); // Impede o envio do formulário
                jsErrorDiv.innerHTML = errors.join("<br>");
                jsErrorDiv.style.display = 'block'; // Mostra a div de erros JS
            }
            // Se não houver erros no JS, o formulário será enviado normalmente para o PHP fazer a validação final e o login.
        });
    }
</script>

<?php
// --- Fim da Geração do HTML ---
echo getFooterIndex(); // Fecha <div class="main-content">, adiciona <footer>, scripts, fecha <body> e <html>
?>