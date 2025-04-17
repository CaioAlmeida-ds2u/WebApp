<?php
// solicitacao_acesso.php

// Includes Essenciais (Config, Layout, DB já vem com config)
// ** CAMINHOS CORRIGIDOS **
require_once __DIR__ . '/includes/config.php';     // Fornece $conexao, $csrf_token, BASE_URL
require_once __DIR__ . '/includes/layout_index.php'; // Funções getHeaderIndex, getFooterIndex

// Variáveis para mensagens
$erro = '';
$sucesso = '';

// --- Processamento do Formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erro = "Erro de validação da sessão. Por favor, tente recarregar a página e enviar novamente.";
        dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'solicitacao_acesso_csrf_falha', 0, 'Token CSRF inválido ou ausente.', $conexao);
    } else {
        // Token válido, processar dados
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $empresa_id = $_POST['empresa'] ?? ''; // ID da empresa selecionada
        $motivo = trim($_POST['motivo'] ?? '');

        // 2. Validação dos Campos
        $errors = [];
        if (empty($nome)) {
            $errors[] = "O campo Nome Completo é obrigatório.";
        }
        if (empty($email)) {
            $errors[] = "O campo E-mail é obrigatório.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Por favor, insira um endereço de e-mail válido.";
        }
        if (empty($empresa_id) || !is_numeric($empresa_id)) { // Verifica se é um ID numérico válido
            $errors[] = "Selecione uma empresa válida.";
        }
        if (empty($motivo)) {
            $errors[] = "O campo Motivo da Solicitação é obrigatório.";
        }

        // 3. Verificar se email já existe (se não houver outros erros)
        if (empty($errors)) {
            $verificacao = dbVerificaEmailExistente($email, $conexao);
            if ($verificacao['usuarios'] > 0) {
                $errors[] = "Este e-mail já está cadastrado em nosso sistema.";
            }
            if ($verificacao['solicitacoes'] > 0) {
                $errors[] = "Já existe uma solicitação de acesso pendente para este e-mail.";
            }
        }

        // 4. Inserir no Banco de Dados se não houver erros
        if (empty($errors)) {
            $insercaoOK = dbInserirSolicitacaoAcesso($nome, $email, (int)$empresa_id, $motivo, $conexao);
            if ($insercaoOK) {
                $sucesso = "Sua solicitação de acesso foi enviada com sucesso e aguarda aprovação.";
                // Opcional: Limpar os campos do POST para não repopular o form
                $_POST = []; // Limpa POST para não re-preencher o formulário
                // Log de sucesso
                dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'solicitacao_acesso_sucesso', 1, 'Solicitação de acesso enviada para: ' . htmlspecialchars($email), $conexao);
            } else {
                $erro = "Erro ao enviar a solicitação. Por favor, tente novamente mais tarde.";
                // Log de falha na inserção
                dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'solicitacao_acesso_falha_db', 0, 'Erro DB ao inserir solicitação para: ' . htmlspecialchars($email), $conexao);
            }
        } else {
            // Se houver erros de validação
            $erro = implode("<br>", $errors);
            // Log de falha na validação
            dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'solicitacao_acesso_falha_valid', 0, 'Falha de validação na solicitação para: ' . htmlspecialchars($email), $conexao);
        }
    } // Fim do else da validação CSRF
} // Fim do if POST

// --- Buscar Lista de Empresas para o Select ---
$empresas = dbGetEmpresasSolic($conexao); // Função do db.php

// --- Geração do HTML ---
$title = "ACodITools - Solicitação de Acesso";
echo getHeaderIndex($title); // Abre HTML, head, body, .main-content
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6"> <?php /* Ajuste leve na coluna */ ?>
            <div class="card">
                 <div class="card-body">
                    <div class="logo-container text-center mb-4">
                        <img src="<?= BASE_URL ?>assets/img/ACodITools_logo.png" alt="ACodITools Logo" class="img-fluid" style="max-width: 150px;">
                        <h1>ACodITools</h1>
                    </div>
                    <h2 class="card-title text-center mb-4">Solicitação de Acesso</h2>

                    <?php if ($erro): ?>
                        <div id="server-error-message" class="alert alert-danger" role="alert">
                            <?= $erro ?> <?php /* $erro já vem com <br> se for múltiplos erros */ ?>
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

                        <form method="POST" action="<?= BASE_URL ?>solicitacao_acesso.php" id="formSolicitacao">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <input type="text" class="form-control" id="nome" name="nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="empresa" class="form-label">Empresa</label>
                                <select class="form-select" id="empresa" name="empresa" required>
                                    <option value="" disabled <?= empty($_POST['empresa']) ? 'selected' : '' ?>>Selecione...</option>
                                    <?php foreach ($empresas as $empresa_item): ?>
                                        <option value="<?= htmlspecialchars($empresa_item['id']) ?>" <?= (isset($_POST['empresa']) && $_POST['empresa'] == $empresa_item['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($empresa_item['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (empty($empresas)): ?>
                                         <option value="" disabled>Nenhuma empresa cadastrada</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="motivo" class="form-label">Motivo da Solicitação</label>
                                <textarea class="form-control" id="motivo" name="motivo" rows="4" required><?= htmlspecialchars($_POST['motivo'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Enviar Solicitação</button>
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

<?php /* Manter a validação JS se desejar */ ?>
<script>
    const formSolicitacao = document.getElementById('formSolicitacao');
    const jsErrorDivSolic = document.getElementById('js-error-message'); // Target a div correta
    const serverErrorDivSolic = document.getElementById('server-error-message');

    if (formSolicitacao) {
        formSolicitacao.addEventListener('submit', function(event) {
            jsErrorDivSolic.style.display = 'none';
            jsErrorDivSolic.innerHTML = '';
             if (serverErrorDivSolic) {
                // serverErrorDivSolic.style.display = 'none';
             }


            let nome = document.getElementById('nome').value.trim();
            let email = document.getElementById('email').value.trim();
            let empresa = document.getElementById('empresa').value;
            let motivo = document.getElementById('motivo').value.trim();
            let errors = [];

            if (!nome) errors.push("O campo Nome Completo é obrigatório.");
            if (!email) {
                errors.push("O campo E-mail é obrigatório.");
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push("Por favor, insira um endereço de e-mail válido.");
            }
            if (!empresa) errors.push("Selecione uma empresa.");
            if (!motivo) errors.push("O campo Motivo da Solicitação é obrigatório.");

            if (errors.length > 0) {
                event.preventDefault(); // Impede o envio
                jsErrorDivSolic.innerHTML = errors.join("<br>");
                jsErrorDivSolic.style.display = 'block';
            }
        });
    }
</script>

<?php echo getFooterIndex(); // Fecha .main-content, body, html ?>