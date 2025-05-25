<?php
// admin/redefinir_senha_usuario_admin.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/layout_admin.php';

protegerPagina($conexao);

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$erro_msg_local = ''; // Para erros específicos desta página
$sucesso_msg_local = '';
$usuario_para_reset = null;
$solicitacao_id_processada = null; // Para saber se viemos de uma solicitação
$usuario_id_alvo = null; // ID do usuário cuja senha será resetada

// Tentar carregar via ID de solicitação
$solicitacao_id_get = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
// Tentar carregar via ID de usuário (para reset manual)
$usuario_id_get = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

// Validar CSRF Token para GET se for um reset manual vindo de um link
if ($usuario_id_get && !$solicitacao_id_get) { // Apenas se for reset manual (não tem solicitação)
    if (!validar_csrf_token($_GET['csrf_token'] ?? null)) {
        definir_flash_message('erro', "Erro de validação da sessão para redefinição manual.");
        header('Location: ' . BASE_URL . 'admin/usuarios.php?view=usuarios-cadastrados-tab');
        exit;
    }
}


if ($solicitacao_id_get) {
    $solicitacao_id_processada = $solicitacao_id_get;
    $solicitacao = getSolicitacaoReset($conexao, $solicitacao_id_processada); // Sua função que busca dados da solicitação

    if ($solicitacao && $solicitacao['status'] === 'pendente') {
        // Obtemos o usuario_id da própria solicitação (sua função getSolicitacoesResetPendentes já faz join com usuarios)
        // Se sua getSolicitacaoReset não faz, você precisará de getDadosUsuarioPorSolicitacaoReset
        // Assumindo que getSolicitacaoReset já traz 'usuario_id' e 'nome_usuario', 'email_usuario'
        $usuario_para_reset = [
            'id' => $solicitacao['usuario_id'], // Crucial que sua getSolicitacaoReset retorne isso
            'nome' => $solicitacao['nome_usuario'] ?? 'Desconhecido',
            'email' => $solicitacao['email_usuario'] ?? ($solicitacao['email'] ?? 'N/A')
        ];
        $usuario_id_alvo = $solicitacao['usuario_id'];
    } elseif ($solicitacao) {
        $erro_msg_local = "Esta solicitação de reset de senha (ID: $solicitacao_id_processada) já foi processada ou é inválida.";
    } else {
        $erro_msg_local = "Solicitação de reset de senha (ID: $solicitacao_id_processada) não encontrada.";
    }
} elseif ($usuario_id_get) {
    // Cenário de redefinição manual (vinda do `editar_usuario.php`)
    $usuario_id_alvo = $usuario_id_get;
    $usuario_para_reset = getUsuario($conexao, $usuario_id_alvo); // Sua função getUsuario
    if (!$usuario_para_reset) {
        $erro_msg_local = "Usuário (ID: $usuario_id_alvo) não encontrado para redefinição manual.";
        $usuario_id_alvo = null; // Invalida o alvo
    } elseif ($usuario_id_alvo == $_SESSION['usuario_id']){
        $erro_msg_local = "Você não pode redefinir sua própria senha por este método. Use a alteração de senha no seu perfil.";
        $usuario_id_alvo = null;
    }
} else {
    $erro_msg_local = "Nenhuma solicitação ou ID de usuário fornecido para redefinição.";
}


// Processar a geração de senha temporária (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario_id_alvo) {
    // Validar CSRF Token para o POST
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        $erro_msg_local = "Erro de validação da sessão. Tente novamente.";
        // Log já feito na função validar_csrf_token
    } else {
        $_SESSION['csrf_token'] = gerar_csrf_token(); // Regenera para o próximo request

        $senha_temporaria = bin2hex(random_bytes(6)); // Senha de 12 caracteres hex
        
        // A função `redefinirSenha` já faz o hash e marca primeiro_acesso = 1
        // Sua função `redefinirSenha` deve ter sido atualizada para incluir `primeiro_acesso = 1`
        $redefinicaoOK = redefinirSenha($conexao, $usuario_id_alvo, $senha_temporaria);

        if ($redefinicaoOK) {
            $log_acao_db = "reset_senha_manual";
            $log_detalhes_db = "Senha temporária gerada para usuário ID: {$usuario_id_alvo} pelo Admin ID: {$_SESSION['usuario_id']}.";

            // Se veio de uma solicitação, aprova a solicitação
            if ($solicitacao_id_processada) {
                if (aprovarSolicitacaoReset($conexao, $solicitacao_id_processada, $_SESSION['usuario_id'])) { // admin_id_aprovou
                    $log_acao_db = "reset_senha_solicitacao_aprov";
                    $log_detalhes_db .= " Solicitação ID: $solicitacao_id_processada aprovada.";
                } else {
                    // Mesmo que a senha tenha sido redefinida, o status da solicitação não foi atualizado
                    $erro_msg_local .= " Senha do usuário redefinida, mas houve um erro ao atualizar o status da solicitação de reset.";
                    // Logar esta falha secundária
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "reset_senha_solic_aprov_falha", 0, "Solicitação ID: $solicitacao_id_processada, Usuário ID: $usuario_id_alvo", $conexao);
                }
            }
            
            $sucesso_msg_local = "Senha temporária gerada com sucesso para o usuário! <strong>Senha Temporária: " . htmlspecialchars($senha_temporaria) . "</strong><br>Informe esta senha ao usuário e instrua-o a alterá-la no próximo login.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], $log_acao_db, 1, $log_detalhes_db, $conexao);

            definir_flash_message('sucesso', $sucesso_msg_local);
            header('Location: ' . BASE_URL . 'admin/usuarios.php?view=usuarios-cadastrados-tab');
            exit;
        } else {
            $erro_msg_local = "Erro ao gerar e salvar a nova senha para o usuário ID: $usuario_id_alvo. Tente novamente.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], "reset_senha_falha_db", 0, "Usuário ID: $usuario_id_alvo", $conexao);
        }
    }
}

// Gerar novo token CSRF para o formulário se for GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['csrf_token'] = gerar_csrf_token();
}
$csrf_token_page = $_SESSION['csrf_token'];


$title = "ACodITools - Redefinir Senha de Usuário";
// header('Content-Type: text/html; charset=utf-8'); // Já deve estar no layout
echo getHeaderAdmin($title);
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="card-title mb-0"><i class="fas fa-key me-2"></i>Redefinir Senha de Usuário</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($erro_msg_local): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $erro_msg_local /* Permite HTML */ ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($sucesso_msg_local): /* Sucesso aqui significaria que o POST falhou APÓS setar $sucesso_msg_local, o que é improvável dado o redirect */ ?>
                        <div class="alert alert-success" role="alert">
                            <?= $sucesso_msg_local /* Permite HTML */ ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($usuario_para_reset && $usuario_id_alvo): ?>
                        <p class="mb-2">Você está prestes a redefinir a senha para:</p>
                        <ul class="list-unstyled mb-3 small bg-light p-2 rounded border">
                            <li><strong>Usuário:</strong> <?= htmlspecialchars($usuario_para_reset['nome']) ?></li>
                            <li><strong>E-mail:</strong> <?= htmlspecialchars($usuario_para_reset['email']) ?></li>
                            <?php if ($solicitacao_id_processada): ?>
                                <li><strong>Ref. Solicitação ID:</strong> <?= htmlspecialchars($solicitacao_id_processada) ?></li>
                            <?php else: ?>
                                 <li><strong>Operação:</strong> Redefinição manual iniciada pelo Administrador.</li>
                            <?php endif; ?>
                        </ul>
                        
                        <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                            <?php // Não precisa enviar ID no post, já temos $usuario_id_alvo e $solicitacao_id_processada no escopo do PHP ?>

                            <p class="text-muted small">Ao clicar no botão abaixo, uma nova senha temporária será gerada e o usuário será instruído a alterá-la no próximo login (`primeiro_acesso` será ativado).</p>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-shield-alt me-1"></i> Gerar Nova Senha Temporária
                                </button>
                                <a href="<?= BASE_URL ?>admin/usuarios.php?view=usuarios-cadastrados-tab" class="btn btn-outline-secondary">Cancelar e Voltar</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <?php if (empty($erro_msg_local)): // Se não há erro explícito, mas $usuario_para_reset é nulo ?>
                            <div class="alert alert-warning">Nenhum usuário válido selecionado para redefinição.</div>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>admin/usuarios.php?view=usuarios-cadastrados-tab" class="btn btn-secondary">Voltar para Usuários</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php echo getFooterAdmin(); ?>