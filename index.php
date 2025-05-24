<?php
// index.php - COMPLETO E ATUALIZADO com redirecionamento por perfil

// Inclui configurações, conexão DB, funções base e GERA/OBTÉM $csrf_token
require_once __DIR__ . '/includes/config.php';
// Inclui funções para gerar header/footer HTML
require_once __DIR__ . '/includes/layout_index.php';

// Se o usuário já estiver logado, redireciona para o dashboard apropriado
// A função redirecionarUsuarioLogado deve ser atualizada para incluir gestor/usuario se necessário
redirecionarUsuarioLogado(); // <- VERIFICAR SE ESTA FUNÇÃO FOI ATUALIZADA

$erro = ''; // Inicializa variável de erro

// --- Processamento do Formulário de Login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erro = "Erro de validação da sessão. Por favor, tente recarregar a página e fazer login novamente.";
        dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'login_csrf_falha', 0, 'Token CSRF inválido ou ausente na tentativa de login.', $conexao);
    } else {
        // Token CSRF válido, processar login
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            $erro = "Por favor, preencha todos os campos (e-mail e senha).";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
             $erro = "Por favor, insira um endereço de e-mail válido.";
        } else {
            // Tenta fazer login (função dbLogin já atualizada para pegar dados da empresa)
            $resultadoLogin = dbLogin($email, $senha, $conexao);

            if (is_array($resultadoLogin)) {
                // --- Login BEM-SUCEDIDO ---
                session_regenerate_id(true); // Previne Session Fixation
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Novo token CSRF

                // Dados da sessão já foram definidos em dbLogin
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'login_sucesso', 1, 'Usuário logado com sucesso.', $conexao);

                // **** LÓGICA DE REDIRECIONAMENTO CORRIGIDA ****
                $perfilLogado = $_SESSION['perfil'] ?? null;
                $redirect_url = ''; // Inicializa URL de redirecionamento

                switch ($perfilLogado) {
                    case 'admin':
                        $redirect_url = BASE_URL . 'admin/dashboard_admin.php';
                        break;
                    case 'gestor_empresa':
                        // !! VERIFIQUE O CAMINHO CORRETO !!
                        $redirect_url = BASE_URL . 'gestor/dashboard_gestor.php'; // Assumindo pasta 'gestor'
                        break;
                    case 'auditor':
                         // !! VERIFIQUE O CAMINHO CORRETO !!
                         $redirect_url = BASE_URL . 'auditor/dashboard_auditor.php'; // Assumindo pasta 'auditor'
                         break;
                    // --- OPCIONAL: Adicionar um perfil 'usuario' comum ---
                    case 'usuario':
                         // !! VERIFIQUE O CAMINHO CORRETO !!
                         $redirect_url = BASE_URL . 'usuario/dashboard_user.php'; // Assumindo pasta 'usuario'
                         break;
                    // --- FIM OPCIONAL ---
                    default:
                        error_log("Tentativa de login com perfil inválido/nulo: " . ($perfilLogado ?? 'NULL') . " para usuário ID: " . ($_SESSION['usuario_id'] ?? 'N/A'));
                        session_unset(); session_destroy(); // Destrói sessão por segurança
                        $redirect_url = BASE_URL . 'index.php?erro=perfil_desconhecido';
                        break;
                }

                // Executa o redirecionamento
                header('Location: ' . $redirect_url);
                exit; // ESSENCIAL
                // **** FIM DA LÓGICA CORRIGIDA ****

            } else {
                // --- Login FALHOU ---
                $erro = $resultadoLogin; // Mensagem de erro vinda de dbLogin()
                dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'login_falha', 0, 'Tentativa de login falhou: ' . $erro . ' (Email: ' . htmlspecialchars($email) . ')', $conexao);
            }
        }
    }
} // Fim do if ($_SERVER['REQUEST_METHOD'] === 'POST')

// --- Preparação para Geração do HTML ---
$title = "ACodITools - Login";

// --- Início da Geração do HTML ---
echo getHeaderIndex($title);
?>

<div class="container mt-4 mt-md-5"> <?php /* Mais margem no mobile */ ?>
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6 col-xl-5"> <?php /* Colunas ajustadas */ ?>
            <div class="card login-card shadow-lg border-0"> <?php /* Classe e mais sombra */ ?>
                <div class="card-body p-4 p-md-5"> <?php /* Padding responsivo */ ?>
                    <div class="logo-container text-center mb-4">
                        <img src="<?= BASE_URL ?>assets/img/ACodITools_logo.png" alt="ACodITools Logo" class="img-fluid mb-2" style="max-width: 120px;"> <?php /* Logo menor */ ?>
                        <h1 class="h4 fw-bold text-primary">ACodITools</h1> <?php /* Título menor */ ?>
                    </div>

                    <?php /* Mensagens de erro (PHP e JS) */ ?>
                    <?php if (!empty($erro)): ?>
                        <div id="server-error-message" class="alert alert-danger small p-2" role="alert">
                            <i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($erro) ?>
                        </div>
                    <?php endif; ?>
                    <div id="js-error-message" class="alert alert-danger small p-2" role="alert" style="display: none;"></div>

                    <form method="POST" action="<?= BASE_URL ?>index.php" id="loginForm" class="needs-validation" novalidate> <?php /* Action com BASE_URL e validação */ ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <div class="form-floating mb-3"> <?php /* Usando form-floating */ ?>
                            <input type="email" class="form-control form-control-sm" id="email" name="email" placeholder="seu@email.com" required autocomplete="email">
                            <label for="email">E-mail</label>
                            <div class="invalid-feedback small">Por favor, insira um e-mail válido.</div>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control form-control-sm" id="senha" name="senha" placeholder="Senha" required autocomplete="current-password">
                             <label for="senha">Senha</label>
                             <div class="invalid-feedback small">Por favor, insira sua senha.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Entrar</button>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="forgot-password">
                                <a href="<?= BASE_URL ?>solicitar_senha.php" class="small">Esqueci minha senha</a>
                            </div>
                             <div class="signup-link">
                                <a href="<?= BASE_URL ?>solicitacao_acesso.php" class="small">Solicitar acesso</a>
                            </div>
                        </div>

                    </form>
                </div> <?php /* Fim card-body */ ?>
            </div> <?php /* Fim card */ ?>
        </div> <?php /* Fim col */ ?>
    </div> <?php /* Fim row */ ?>
</div> <?php /* Fim container */ ?>

<?php /* JavaScript de Validação Client-Side (Bootstrap + Opcional) */ ?>
<script>
    (function () {
      'use strict'
      // Fetch all the forms we want to apply custom Bootstrap validation styles to
      var forms = document.querySelectorAll('.needs-validation')
      // Loop over them and prevent submission
      Array.prototype.slice.call(forms)
        .forEach(function (form) {
          form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
              event.preventDefault()
              event.stopPropagation()
            }
            form.classList.add('was-validated')
          }, false)
        })
    })()

    // Script JS opcional para limpar erro do servidor se começar a digitar
    const emailInputJs = document.getElementById('email');
    const senhaInputJs = document.getElementById('senha');
    const serverErrorDivJs = document.getElementById('server-error-message');

    function hideServerError() {
        if (serverErrorDivJs) {
            serverErrorDivJs.style.display = 'none';
        }
        // Remove listener após a primeira interação para não rodar desnecessariamente
        emailInputJs?.removeEventListener('input', hideServerError);
        senhaInputJs?.removeEventListener('input', hideServerError);
    }

    if (serverErrorDivJs && (emailInputJs || senhaInputJs)) {
       emailInputJs?.addEventListener('input', hideServerError, { once: true }); // { once: true } remove automaticamente
       senhaInputJs?.addEventListener('input', hideServerError, { once: true });
    }

</script>

<?php
echo getFooterIndex();
?>