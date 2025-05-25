<?php
// admin/editar_usuario.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php';
// require_once __DIR__ . '/../includes/db.php'; // getUsuario e atualizarUsuario já estão em admin_functions.php

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$usuario_id_editar = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$modo_criacao = ($usuario_id_editar === null || $usuario_id_editar === false); // Determina se estamos criando ou editando

$usuario_dados_form = []; // Para repopular o formulário em caso de erro ou na edição
$page_title = "Novo Usuário da Plataforma";

if (!$modo_criacao) {
    $usuario_db = getUsuario($conexao, $usuario_id_editar);
    if (!$usuario_db) {
        definir_flash_message('erro', "Usuário com ID $usuario_id_editar não encontrado.");
        header('Location: ' . BASE_URL . 'admin/usuarios.php?view=usuarios-cadastrados-tab');
        exit;
    }
    $usuario_dados_form = $usuario_db; // Popula com dados do banco para edição
    $page_title = "Editar Usuário: " . htmlspecialchars($usuario_db['nome']);
} else {
    // Valores padrão para criação
    $usuario_dados_form = [
        'id' => null, 'nome' => '', 'email' => '', 'perfil' => '', 'ativo' => 1,
        'empresa_id' => null, 'is_empresa_admin_cliente' => 0,
        'especialidade_auditor' => '', 'certificacoes_auditor' => ''
    ];
}

$empresas = getEmpresasAtivas($conexao);
// Perfis exatos do ENUM do banco de dados
$perfis_db_validos = ['admin', 'gestor_empresa', 'auditor_empresa', 'auditado_contato'];

$erro_msg_local = ''; // Para erros de validação do formulário atual
$form_action_url = $modo_criacao ? (BASE_URL . "admin/editar_usuario.php") : (BASE_URL . "admin/editar_usuario.php?id=" . $usuario_id_editar);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Se for edição, o ID no POST deve bater com o ID do GET
    $id_post_check = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
    if (!$modo_criacao && ($id_post_check !== $usuario_id_editar)) {
        definir_flash_message('erro', "Inconsistência no ID do usuário. Ação não executada.");
        header('Location: ' . BASE_URL . 'admin/usuarios.php?view=usuarios-cadastrados-tab');
        exit;
    }
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        // definir_flash_message já loga CSRF em admin_functions.php, mas podemos adicionar contexto
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_criar_usuario_csrf_falha', 0, "ID: " . ($usuario_id_editar ?? 'NOVO'), $conexao);
        definir_flash_message('erro', "Erro de validação da sessão. Por favor, tente novamente.");
        header('Location: ' . $form_action_url); // Recarrega a mesma página
        exit;
    }
    // Regenerar token na sessão após o uso (ou no início da próxima carga de página GET)
    // $_SESSION['csrf_token'] = gerar_csrf_token(); // Feito no GET load

    // Coletar dados do formulário
    $usuario_dados_form['nome'] = trim($_POST['nome'] ?? '');
    $usuario_dados_form['email'] = trim($_POST['email'] ?? '');
    $usuario_dados_form['perfil'] = $_POST['perfil'] ?? '';
    $usuario_dados_form['ativo'] = isset($_POST['ativo']) ? 1 : 0;
    $usuario_dados_form['empresa_id'] = filter_input(INPUT_POST, 'empresa_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $usuario_dados_form['is_empresa_admin_cliente'] = isset($_POST['is_empresa_admin_cliente']) ? 1 : 0;
    $usuario_dados_form['especialidade_auditor'] = trim($_POST['especialidade_auditor'] ?? '');
    $usuario_dados_form['certificacoes_auditor'] = trim($_POST['certificacoes_auditor'] ?? '');

    $nova_senha_inicial = null;
    if ($modo_criacao) {
        $nova_senha_inicial = $_POST['senha_inicial'] ?? '';
        $confirmar_senha_inicial = $_POST['confirmar_senha_inicial'] ?? '';
    }

    $errors_validation = [];
    if (empty($usuario_dados_form['nome'])) $errors_validation[] = "O nome completo é obrigatório.";
    if (empty($usuario_dados_form['email'])) $errors_validation[] = "O e-mail é obrigatório.";
    elseif (!filter_var($usuario_dados_form['email'], FILTER_VALIDATE_EMAIL)) $errors_validation[] = "Formato de e-mail inválido.";
    elseif (dbEmailExisteEmOutroUsuario($usuario_dados_form['email'], $usuario_id_editar, $conexao)) { // Passa ID de edição para excluir ele mesmo da checagem
        $errors_validation[] = "Este e-mail já está sendo usado por outro usuário.";
    }

    if (empty($usuario_dados_form['perfil']) || !in_array($usuario_dados_form['perfil'], $perfis_db_validos)) {
        $errors_validation[] = "Perfil selecionado inválido.";
    } else {
        // Validação de empresa_id com base no perfil
        if ($usuario_dados_form['perfil'] === 'admin') {
            $usuario_dados_form['empresa_id'] = null; // Admin da plataforma NÃO tem empresa
            $usuario_dados_form['is_empresa_admin_cliente'] = 0; // E não é admin de cliente
            $usuario_dados_form['especialidade_auditor'] = null;
            $usuario_dados_form['certificacoes_auditor'] = null;
        } elseif (in_array($usuario_dados_form['perfil'], ['gestor_empresa', 'auditor_empresa', 'auditado_contato'])) {
            if (empty($usuario_dados_form['empresa_id'])) {
                $errors_validation[] = "Uma empresa deve ser selecionada para este perfil de usuário.";
            } else {
                // Checa se a empresa_id existe
                $empresaValidaPost = false;
                foreach ($empresas as $emp) { if ($emp['id'] == $usuario_dados_form['empresa_id']) { $empresaValidaPost = true; break; } }
                if (!$empresaValidaPost) $errors_validation[] = "A empresa selecionada é inválida.";
            }
            // Se não for gestor_empresa, is_empresa_admin_cliente deve ser 0
            if ($usuario_dados_form['perfil'] !== 'gestor_empresa') {
                $usuario_dados_form['is_empresa_admin_cliente'] = 0;
            }
            // Se não for auditor_empresa, especialidade e certificações são nulas
            if ($usuario_dados_form['perfil'] !== 'auditor_empresa') {
                 $usuario_dados_form['especialidade_auditor'] = null;
                 $usuario_dados_form['certificacoes_auditor'] = null;
            }
        }
    }

    if (!$modo_criacao && $usuario_id_editar == $_SESSION['usuario_id'] && $usuario_dados_form['ativo'] == 0) {
        $errors_validation[] = "Você não pode desativar sua própria conta.";
    }
    if (!$modo_criacao && $usuario_id_editar == $_SESSION['usuario_id'] && $usuario_dados_form['perfil'] !== $_SESSION['perfil']) {
        $errors_validation[] = "Você não pode alterar seu próprio perfil.";
        $usuario_dados_form['perfil'] = $_SESSION['perfil']; // Reverte para o perfil atual da sessão
    }


    if ($modo_criacao) {
        if (empty($nova_senha_inicial)) $errors_validation[] = "A senha inicial é obrigatória para novos usuários.";
        elseif (strlen($nova_senha_inicial) < 8) $errors_validation[] = "A senha inicial deve ter pelo menos 8 caracteres.";
        // Adicionar regex de complexidade de senha se desejar, igual ao do seu modal de primeiro acesso
        // elseif(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $nova_senha_inicial)) $errors_validation[] = "Senha inicial fraca.";
        elseif ($nova_senha_inicial !== $confirmar_senha_inicial) $errors_validation[] = "As senhas iniciais não coincidem.";
    }

    if (empty($errors_validation)) {
        if ($modo_criacao) {
            $resultado_op = criarUsuarioPlataforma( // Função a ser criada em admin_functions.php
                $conexao,
                $usuario_dados_form['nome'],
                $usuario_dados_form['email'],
                $nova_senha_inicial, // Senha em texto plano, a função fará o hash
                $usuario_dados_form['perfil'],
                $usuario_dados_form['ativo'],
                $usuario_dados_form['empresa_id'],
                $usuario_dados_form['is_empresa_admin_cliente'],
                $usuario_dados_form['especialidade_auditor'],
                $usuario_dados_form['certificacoes_auditor'],
                $_SESSION['usuario_id'] // Admin que está criando
            );
            $acao_log = 'criar_usuario_plataforma';
            $msg_sucesso = "Usuário '" . htmlspecialchars($usuario_dados_form['nome']) . "' criado com sucesso!";
            $msg_falha_db = "Erro ao criar o novo usuário.";

        } else { // Modo Edição
            $resultado_op = atualizarUsuario(
                $conexao, $usuario_id_editar, $usuario_dados_form['nome'], $usuario_dados_form['email'],
                $usuario_dados_form['perfil'], $usuario_dados_form['ativo'], $usuario_dados_form['empresa_id'],
                (bool)$usuario_dados_form['is_empresa_admin_cliente'], // Cast para bool
                $usuario_dados_form['especialidade_auditor'], $usuario_dados_form['certificacoes_auditor']
            );
            // Para `atualizarUsuario`, ela retorna true mesmo se nenhuma linha foi afetada.
            // Consideramos sucesso se não for false.
            $acao_log = 'editar_usuario';
            $msg_sucesso = "Usuário '" . htmlspecialchars($usuario_dados_form['nome']) . "' atualizado com sucesso!";
            $msg_falha_db = "Nenhuma alteração detectada ou erro ao salvar no banco de dados.";
        }

        if ($resultado_op === true || (is_array($resultado_op) && ($resultado_op['success'] ?? false))) {
            $novo_id_criado = $modo_criacao && is_array($resultado_op) ? $resultado_op['id'] : $usuario_id_editar;
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], $acao_log . '_sucesso', 1, "ID Usuário afetado: $novo_id_criado", $conexao);
            definir_flash_message('sucesso', $msg_sucesso);
            header('Location: ' . BASE_URL . 'admin/usuarios.php?view=usuarios-cadastrados-tab');
            exit;
        } else {
            $erro_msg_local = is_string($resultado_op) ? $resultado_op : $msg_falha_db;
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], $acao_log . '_falha_db', 0, "ID: " . ($usuario_id_editar ?? 'NOVO') . ", Erro: " . $erro_msg_local, $conexao);
        }
    } else {
        $erro_msg_local = "<strong>Foram encontrados os seguintes erros:</strong><ul><li>" . implode("</li><li>", $errors_validation) . "</li></ul>";
        // Log já feito na validação de CSRF se for o caso, ou log geral de falha de validação
        // dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], $modo_criacao ? 'criar_usuario_falha_valid' : 'editar_usuario_falha_valid', 0, "ID: ".($usuario_id_editar ?? 'NOVO'), $conexao);
    }
}

// Gerar novo token CSRF para o formulário ser exibido
$_SESSION['csrf_token'] = gerar_csrf_token();
$csrf_token_page = $_SESSION['csrf_token'];

$title = $page_title; // Definido no início (Novo ou Editar)
echo getHeaderAdmin($title);
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
         <h1 class="h2"><?= $modo_criacao ? '<i class="fas fa-user-plus me-2"></i>Novo Usuário da Plataforma' : '<i class="fas fa-user-edit me-2"></i>Editar Usuário' ?></h1>
         <a href="<?= BASE_URL ?>admin/usuarios.php?view=usuarios-cadastrados-tab" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar para Usuários
         </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <?= $modo_criacao ? "Preencha os dados do novo usuário:" : "Dados de: <strong>" . htmlspecialchars($usuario_dados_form['nome']) . "</strong>" ?>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($erro_msg_local)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                           <?= $erro_msg_local /* Permite HTML de erros de validação */ ?>
                           <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= htmlspecialchars($form_action_url) ?>" id="editUserForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                        <?php if (!$modo_criacao): ?>
                            <input type="hidden" name="usuario_id" value="<?= htmlspecialchars($usuario_dados_form['id']) ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nome" class="form-label form-label-sm fw-semibold">Nome Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="nome" name="nome" value="<?= htmlspecialchars($usuario_dados_form['nome']) ?>" required>
                                <div class="invalid-feedback">O nome completo é obrigatório.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label form-label-sm fw-semibold">E-mail <span class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-sm" id="email" name="email" value="<?= htmlspecialchars($usuario_dados_form['email']) ?>" required>
                                <div class="invalid-feedback">Por favor, insira um e-mail válido.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="perfil" class="form-label form-label-sm fw-semibold">Perfil <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="perfil" name="perfil" required <?= (!$modo_criacao && $usuario_id_editar == $_SESSION['usuario_id']) ? 'disabled' : '' ?>>
                                    <option value="" disabled <?= empty($usuario_dados_form['perfil']) ? 'selected' : ''?>>Selecione...</option>
                                    <?php foreach ($perfis_db_validos as $p_val): ?>
                                        <option value="<?= $p_val ?>" <?= ($usuario_dados_form['perfil'] === $p_val) ? 'selected' : '' ?>>
                                            <?= ucfirst(str_replace('_', ' ', $p_val)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$modo_criacao && $usuario_id_editar == $_SESSION['usuario_id']): ?>
                                    <small class="form-text text-muted">Você não pode alterar seu próprio perfil.</small>
                                <?php endif; ?>
                                <div class="invalid-feedback">Selecione um perfil válido.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="empresa_id" class="form-label form-label-sm fw-semibold">Empresa Cliente <span class="text-danger" id="empresa_obrigatoria_asterisco_edit" style="display: none;">*</span></label>
                                <select class="form-select form-select-sm" id="empresa_id" name="empresa_id" <?= ($usuario_dados_form['perfil'] === 'admin' && !empty($usuario_dados_form['perfil'])) ? 'disabled' : '' ?>>
                                    <option value="">-- Nenhuma (Admin da Plataforma) --</option>
                                    <?php if (!empty($empresas)): foreach ($empresas as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" <?= ($usuario_dados_form['empresa_id'] == $emp['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['nome']) ?>
                                        </option>
                                    <?php endforeach; endif; ?>
                                </select>
                                <div class="invalid-feedback" id="empresa_error_msg_edit">Selecione uma empresa válida para este perfil.</div>
                            </div>

                            <?php if ($modo_criacao): ?>
                                <div class="col-md-6">
                                    <label for="senha_inicial" class="form-label form-label-sm fw-semibold">Senha Inicial <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control form-control-sm" id="senha_inicial" name="senha_inicial" required minlength="8">
                                    <div class="invalid-feedback">Senha inicial obrigatória (mín. 8 caracteres).</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirmar_senha_inicial" class="form-label form-label-sm fw-semibold">Confirmar Senha <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control form-control-sm" id="confirmar_senha_inicial" name="confirmar_senha_inicial" required>
                                    <div class="invalid-feedback">As senhas não coincidem.</div>
                                </div>
                            <?php else: // Modo Edição - Opção de redefinir senha ?>
                                <div class="col-12">
                                    <a href="<?= BASE_URL ?>admin/redefinir_senha_admin.php?user_id=<?= $usuario_id_editar ?>&csrf_token=<?= $csrf_token_page ?>" class="btn btn-sm btn-outline-warning">
                                        <i class="fas fa-key me-1"></i> Redefinir Senha Manualmente
                                    </a>
                                    <small class="form-text text-muted d-block">Irá gerar uma senha temporária e marcar para o usuário redefinir no próximo login.</small>
                                </div>
                            <?php endif; ?>

                            <div class="col-12 mt-3" id="opcoes_gestor_empresa_div" style="display:none;">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_empresa_admin_cliente" name="is_empresa_admin_cliente" value="1" <?= !empty($usuario_dados_form['is_empresa_admin_cliente']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="is_empresa_admin_cliente">Este gestor é o Administrador Principal da Conta Cliente (terá mais permissões dentro da empresa)</label>
                                </div>
                            </div>

                            <div class="col-12 mt-3" id="opcoes_auditor_empresa_div" style="display:none;">
                                <div class="mb-2">
                                    <label for="especialidade_auditor" class="form-label form-label-sm fw-semibold">Especialidade(s) do Auditor</label>
                                    <textarea class="form-control form-control-sm" id="especialidade_auditor" name="especialidade_auditor" rows="2" placeholder="Ex: ISO 9001, LGPD, Controles Internos... (separado por vírgula ou linha)"><?= htmlspecialchars($usuario_dados_form['especialidade_auditor'] ?? '') ?></textarea>
                                </div>
                                <div class="mb-2">
                                    <label for="certificacoes_auditor" class="form-label form-label-sm fw-semibold">Certificações do Auditor</label>
                                    <textarea class="form-control form-control-sm" id="certificacoes_auditor" name="certificacoes_auditor" rows="2" placeholder="Ex: Lead Auditor ISO 27001, CISA..."><?= htmlspecialchars($usuario_dados_form['certificacoes_auditor'] ?? '') ?></textarea>
                                </div>
                            </div>


                            <div class="col-12 mt-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?= ($usuario_dados_form['ativo'] ?? 0) ? 'checked' : '' ?> <?= (!$modo_criacao && $usuario_id_editar == $_SESSION['usuario_id']) ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="ativo">Usuário Ativo</label>
                                    <?php if (!$modo_criacao && $usuario_id_editar == $_SESSION['usuario_id']): ?>
                                        <small class="form-text text-muted d-block">Você não pode desativar sua própria conta.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <div class="d-flex justify-content-end">
                            <a href="<?= BASE_URL ?>admin/usuarios.php?view=usuarios-cadastrados-tab" class="btn btn-secondary btn-sm me-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary btn-sm">
                               <i class="fas fa-save me-1"></i> <?= $modo_criacao ? 'Criar Usuário' : 'Salvar Alterações' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editUserForm');
    const perfilSelect = document.getElementById('perfil');
    const empresaSelect = document.getElementById('empresa_id');
    const empresaAsterisco = document.getElementById('empresa_obrigatoria_asterisco_edit');
    const empresaFeedback = document.getElementById('empresa_error_msg_edit');
    const opcoesGestorDiv = document.getElementById('opcoes_gestor_empresa_div');
    const opcoesAuditorDiv = document.getElementById('opcoes_auditor_empresa_div');
    const nomeInput = document.getElementById('nome');

    <?php if ($modo_criacao && empty($usuario_dados_form['nome'])): // Foca no nome se for criação e nome estiver vazio ?>
        if(nomeInput) { setTimeout(() => nomeInput.focus(), 100); }
    <?php endif; ?>


    function toggleConditionalFields() {
        const perfilSelecionado = perfilSelect.value;
        const perfisComEmpresaObrigatoria = ['gestor_empresa', 'auditor_empresa', 'auditado_contato'];
        const ehAdminPlataforma = (perfilSelecionado === 'admin');

        // Lógica da Empresa
        if (ehAdminPlataforma) {
            empresaSelect.value = ''; // Limpa seleção
            empresaSelect.disabled = true;
            empresaSelect.required = false;
            empresaAsterisco.style.display = 'none';
            empresaSelect.classList.remove('is-invalid');
            if(empresaFeedback) empresaFeedback.textContent = 'Selecione uma empresa válida para este perfil.';
        } else if (perfisComEmpresaObrigatoria.includes(perfilSelecionado)) {
            empresaSelect.disabled = false;
            empresaSelect.required = true;
            empresaAsterisco.style.display = 'inline';
        } else { // Outros casos ou perfil não selecionado
            empresaSelect.disabled = false;
            empresaSelect.required = false;
            empresaAsterisco.style.display = 'none';
            empresaSelect.classList.remove('is-invalid');
            if(empresaFeedback) empresaFeedback.textContent = 'Selecione uma empresa válida para este perfil.';
        }

        // Lógica para Opções de Gestor da Empresa
        if (opcoesGestorDiv) {
            opcoesGestorDiv.style.display = (perfilSelecionado === 'gestor_empresa') ? 'block' : 'none';
        }

        // Lógica para Opções de Auditor da Empresa
        if (opcoesAuditorDiv) {
            opcoesAuditorDiv.style.display = (perfilSelecionado === 'auditor_empresa') ? 'block' : 'none';
        }
    }

    if (perfilSelect && empresaSelect) {
        toggleConditionalFields(); // Chama no carregamento da página
        perfilSelect.addEventListener('change', toggleConditionalFields);
    }

    if (form) {
        form.addEventListener('submit', event => {
            toggleConditionalFields(); // Garante que a obrigatoriedade e estado disabled estejam corretos antes da validação

            <?php if ($modo_criacao): ?>
            const senhaInicial = document.getElementById('senha_inicial').value;
            const confirmarSenhaInicial = document.getElementById('confirmar_senha_inicial').value;
            const confirmarSenhaInput = document.getElementById('confirmar_senha_inicial');
            if (senhaInicial && confirmarSenhaInicial && senhaInicial !== confirmarSenhaInicial) {
                confirmarSenhaInput.setCustomValidity("As senhas não coincidem.");
            } else {
                confirmarSenhaInput.setCustomValidity("");
            }
            // Adicionar aqui validação de complexidade da senha se desejar (com regex)
            <?php endif; ?>


            if (empresaSelect.required && !empresaSelect.value && !empresaSelect.disabled) {
                empresaSelect.classList.add('is-invalid');
                if(empresaFeedback) empresaFeedback.textContent = 'Uma empresa é obrigatória para este perfil.';
                // Não precisa de event.preventDefault() aqui, o checkValidity() já faz isso.
            } else if (empresaSelect.disabled && empresaSelect.required){
                // Se estiver desabilitado mas era obrigatório, remove o required para não bloquear o submit
                // Isso pode acontecer se o JS não rodar corretamente.
                empresaSelect.required = false;
            }

            if (!form.checkValidity()) {
              event.preventDefault();
              event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);

        if(empresaSelect) {
            empresaSelect.addEventListener('change', () => {
                if (empresaSelect.value || !empresaSelect.required) {
                     empresaSelect.classList.remove('is-invalid');
                     if(empresaFeedback) empresaFeedback.textContent = 'Selecione uma empresa válida para este perfil.';
                }
            });
        }
    }
});
</script>

<?php
echo getFooterAdmin();
?>