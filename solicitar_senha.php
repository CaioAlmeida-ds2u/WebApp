<?php
// solicitar_senha.php

// Includes Essenciais
// ** CAMINHOS CORRIGIDOS **
require_once __DIR__ . '/includes/config.php';     // Fornece $conexao, $csrf_token, BASE_URL, db.php
require_once __DIR__ . '/includes/layout_index.php'; // Funções getHeaderIndex, getFooterIndex

// Variáveis para mensagens
$erro = '';
$sucesso = '';

// --- Processamento do Formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erro = "Erro de validação da sessão. Por favor, tente recarregar a página e enviar novamente.";
        dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'solicitar_senha_csrf_falha', 0, 'Token CSRF inválido ou ausente.', $conexao);
    } else {
        // Token válido, processar dados
        $email = trim($_POST['email'] ?? '');

        // 2. Validação do Campo Email
        if (empty($email)) {
            $erro = "Por favor, insira seu e-mail.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "Por favor, insira um endereço de e-mail válido.";
        } else {
            // 3. Verificar se o usuário existe e está ativo (usando função do db.php)
            $usuario_id = dbVerificarUsuarioAtivoPorEmail($email, $conexao);

            if ($usuario_id === null) {
                // Ou não existe, ou está inativo, ou houve erro no DB
                $erro = "Este e-mail não está cadastrado ou a conta associada está inativa.";
                // Logar (sem ID de usuário, pois não foi encontrado/ativo)
                 dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'solicitar_senha_falha_usr', 0, 'Usuário não encontrado/inativo para e-mail: ' . htmlspecialchars($email), $conexao);
            } else {
                // 4. Usuário válido, tentar inserir solicitação (usando função do db.php)
                $insercaoOK = dbInserirSolicitacaoReset($usuario_id, $conexao);

                if ($insercaoOK) {
                    $sucesso = "Sua solicitação de redefinição de senha foi enviada ao administrador.";
                    // Opcional: Limpar POST
                    $_POST = [];
                    // Log de sucesso
                    dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'solicitar_senha_sucesso', 1, 'Solicitação de reset de senha enviada.', $conexao);
                } else {
                    $erro = "Erro ao registrar sua solicitação. Por favor, tente novamente mais tarde.";
                    // Log de falha na inserção (erro DB)
                     dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'solicitar_senha_falha_db', 0, 'Erro DB ao inserir solicitação de reset.', $conexao);
                    // O erro específico do PDO já foi logado dentro da função dbInserirSolicitacaoReset
                }
            }
        }

        // Se houve erro de validação (email vazio/inválido), logar também
        if (!empty($erro) && !isset($usuario_id)) { // Apenas se o erro for de validação inicial
             dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'solicitar_senha_falha_valid', 0, 'Falha de validação no campo e-mail (' . $erro . ')', $conexao);
        }

    } // Fim do else da validação CSRF
} // Fim do if POST

// --- Geração do HTML ---
$title = "ACodITools - Recuperar Senha";
echo getHeaderIndex($title); // Abre HTML, head, body, .main-content
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card">
                <div class="card-body">
                    <div class="logo-container text-center mb-4">
                        <img src="<?= BASE_URL ?>assets/img/ACodITools_logo.png" alt="ACodITools Logo" class="img-fluid" style="max-width: 150px;">
                        <h1 class="mt-2">ACodITools</h1> <?php /* Ajuste leve */ ?>
                    </div>
                    <h2 class="card-title text-center mb-4">Recuperar Senha</h2>

                    <?php if ($erro): ?>
                        <div id="server-error-message" class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($erro) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($sucesso): ?>
                        <div class="alert alert-success" role="alert">
                            <?= htmlspecialchars($sucesso) ?>
                        </div>
                         <?php /* Se houve sucesso, não mostramos o formulário novamente */ ?>
                    <?php else: ?>
                         <?php /* Div para erros do JS */ ?>
                        <div id="js-error-message" class="alert alert-danger" role="alert" style="display: none;"></div>

                        <form method="POST" action="<?= BASE_URL ?>solicitar_senha.php" id="formSolicitarSenha">
                             <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                            <div class="mb-3">
                                <label for="email" class="form-label">Seu E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" required aria-describedby="emailHelpReset" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                <div id="emailHelpReset" class="form-text">Insira o e-mail associado à sua conta ativa.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Solicitar Redefinição</button>
                        </form>
                    <?php endif; ?>

                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>index.php" class="btn btn-link">Voltar para o Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php /* Validação JS Opcional */ ?>
<script>
    const formSolicitarSenha = document.getElementById('formSolicitarSenha');
    const emailInputReset = document.getElementById('email');
    const jsErrorDivReset = document.getElementById('js-error-message');
    const serverErrorDivReset = document.getElementById('server-error-message');

    if (formSolicitarSenha) {
        formSolicitarSenha.addEventListener('submit', function(event) {
            jsErrorDivReset.style.display = 'none';
            jsErrorDivReset.innerHTML = '';
             if (serverErrorDivReset) {
                // serverErrorDivReset.style.display = 'none';
             }

            const email = emailInputReset.value.trim();
            let errors = [];

            if (!email) {
                errors.push("O campo de e-mail é obrigatório.");
            } else {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    errors.push("Por favor, insira um endereço de e-mail válido.");
                }
            }

            if (errors.length > 0) {
                event.preventDefault(); // Impede o envio
                jsErrorDivReset.innerHTML = errors.join("<br>");
                jsErrorDivReset.style.display = 'block';
            }
        });
    }
</script>


<?php echo getFooterIndex(); // Fecha .main-content, body, html ?>