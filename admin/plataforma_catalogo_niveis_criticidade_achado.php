<?php
// admin/plataforma_catalogo_niveis_criticidade_achado.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Precisará de funções CRUD para niveis_criticidade

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Admin da Acoditools
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');
// Para erros específicos do formulário de criar/editar no modal
$erro_form_crit = $_SESSION['erro_form_nivel_crit'] ?? null; unset($_SESSION['erro_form_nivel_crit']);
$form_data_crit = $_SESSION['form_data_nivel_crit'] ?? []; unset($_SESSION['form_data_nivel_crit']);

// --- Processamento de Ações POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $action_crit = $_POST['action'] ?? '';
        $nivel_crit_id_acao = filter_input(INPUT_POST, 'nivel_crit_id', FILTER_VALIDATE_INT);
        $nome_nivel_crit_form = trim($_POST['nome_nivel_crit'] ?? '');
        $descricao_nivel_crit_form = trim($_POST['descricao_nivel_crit'] ?? '');
        $valor_ordenacao_form = filter_input(INPUT_POST, 'valor_ordenacao_crit', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $cor_hex_form = trim($_POST['cor_hex_crit'] ?? '#6c757d'); // Default para cinza
        $ativo_nivel_crit_form = isset($_POST['ativo_nivel_crit']) ? 1 : 0;

        $is_form_action_crit = in_array($action_crit, ['criar_nivel_crit', 'salvar_edicao_nivel_crit']);
        $has_form_error_crit = !empty($_SESSION['erro_form_nivel_crit']);

        if (!($is_form_action_crit && $has_form_error_crit)) {
             $_SESSION['csrf_token'] = gerar_csrf_token();
        }

        switch ($action_crit) {
            case 'criar_nivel_crit':
                if (empty($nome_nivel_crit_form)) {
                    $_SESSION['erro_form_nivel_crit'] = "O nome do nível de criticidade é obrigatório.";
                    $_SESSION['form_data_nivel_crit'] = $_POST;
                } elseif ($valor_ordenacao_form === null || $valor_ordenacao_form < 0) {
                    $_SESSION['erro_form_nivel_crit'] = "O valor de ordenação deve ser um número não negativo.";
                    $_SESSION['form_data_nivel_crit'] = $_POST;
                }
                else {
                    // *** Chamar função: criarNivelCriticidadeGlobal($conexao, $nome, $desc, $ordem, $cor, $ativo, $admin_id) ***
                    $res_criacao = criarNivelCriticidadeGlobal($conexao, $nome_nivel_crit_form, $descricao_nivel_crit_form, $valor_ordenacao_form, $cor_hex_form, $ativo_nivel_crit_form, $_SESSION['usuario_id']);
                    if ($res_criacao === true) {
                        definir_flash_message('sucesso', "Nível de Criticidade '".htmlspecialchars($nome_nivel_crit_form)."' criado.");
                    } else {
                        $_SESSION['erro_form_nivel_crit'] = is_string($res_criacao) ? $res_criacao : "Erro ao criar nível.";
                        $_SESSION['form_data_nivel_crit'] = $_POST;
                    }
                }
                break;

            case 'salvar_edicao_nivel_crit':
                if ($nivel_crit_id_acao && !empty($nome_nivel_crit_form) && $valor_ordenacao_form !== null && $valor_ordenacao_form >= 0) {
                    // *** Chamar função: atualizarNivelCriticidadeGlobal($conexao, $id, $nome, $desc, $ordem, $cor, $ativo, $admin_id) ***
                    $res_edicao = atualizarNivelCriticidadeGlobal($conexao, $nivel_crit_id_acao, $nome_nivel_crit_form, $descricao_nivel_crit_form, $valor_ordenacao_form, $cor_hex_form, $ativo_nivel_crit_form, $_SESSION['usuario_id']);
                    if ($res_edicao === true) {
                        definir_flash_message('sucesso', "Nível de Criticidade (ID: $nivel_crit_id_acao) atualizado.");
                    } else {
                        definir_flash_message('erro', is_string($res_edicao) ? $res_edicao : "Erro ao atualizar nível ID $nivel_crit_id_acao.");
                    }
                } else {
                    definir_flash_message('erro', "Dados inválidos para edição do Nível de Criticidade.");
                }
                break;

            case 'ativar_nivel_crit':
            case 'desativar_nivel_crit':
                if ($nivel_crit_id_acao) {
                    $novo_stat_crit = ($action_crit === 'ativar_nivel_crit');
                    // *** Chamar função: setStatusNivelCriticidadeGlobal($conexao, $id, $ativo, $admin_id) ***
                    if (setStatusNivelCriticidadeGlobal($conexao, $nivel_crit_id_acao, $novo_stat_crit, $_SESSION['usuario_id'])) {
                        definir_flash_message('sucesso', "Status do Nível de Criticidade ID $nivel_crit_id_acao atualizado.");
                    } else {
                        definir_flash_message('erro', "Erro ao atualizar status do Nível ID $nivel_crit_id_acao.");
                    }
                }
                break;

            case 'excluir_nivel_crit':
                if ($nivel_crit_id_acao) {
                     // *** Chamar função: excluirNivelCriticidadeGlobal($conexao, $id) ***
                     // Função deve verificar se o nível está em uso (ex: em auditoria_itens.criticidade_achado_id)
                    $res_exc_crit = excluirNivelCriticidadeGlobal($conexao, $nivel_crit_id_acao);
                    if ($res_exc_crit === true) {
                        definir_flash_message('sucesso', "Nível de Criticidade ID $nivel_crit_id_acao excluído (se não estiver em uso).");
                    } else {
                        definir_flash_message('erro', is_string($res_exc_crit) ? $res_exc_crit : "Erro ao excluir Nível ID $nivel_crit_id_acao.");
                    }
                }
                break;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Busca de Dados para Exibição ---
// *** Chamar função: listarNiveisCriticidadeGlobal($conexao) *** (sem paginação por enquanto, geralmente são poucos)
$lista_niveis_crit = listarNiveisCriticidadeGlobal($conexao); // Função a ser criada

// Para o modal de edição, se um ID for passado via GET para popular
$nivel_crit_para_editar = null;
$edit_id_crit = filter_input(INPUT_GET, 'edit_crit_id', FILTER_VALIDATE_INT);
if ($edit_id_crit) {
    // *** Chamar função: getNivelCriticidadeGlobalPorId($conexao, $edit_id_crit) ***
    $nivel_crit_para_editar = getNivelCriticidadeGlobalPorId($conexao, $edit_id_crit); // Função a ser criada
    if (!$nivel_crit_para_editar) definir_flash_message('erro', "Nível de Criticidade para edição não encontrado (ID: $edit_id_crit).");
}

if (empty($_SESSION['csrf_token']) || ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$edit_id_crit) || !empty($erro_form_crit)) {
    $_SESSION['csrf_token'] = gerar_csrf_token();
}
$csrf_token_page = $_SESSION['csrf_token'];

$title = "ACodITools - Catálogo Global de Níveis de Criticidade dos Achados";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-exclamation-triangle me-2"></i>Catálogo Global de Níveis de Criticidade</h1>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="modal" data-bs-target="#modalCriarEditarNivelCrit" data-action="criar">
            <i class="fas fa-plus me-1"></i> Novo Nível de Criticidade
        </button>
    </div>

    <?php if ($sucesso_msg): /* ... Mensagens de sucesso ... */ endif; ?>
    <?php if ($erro_msg): /* ... Mensagens de erro ... */ endif; ?>
    <?php if ($erro_form_crit && !$edit_id_crit): ?>
        <div class="alert alert-warning alert-dismissible fade show small" role="alert">
            <strong>Erro ao criar:</strong> <?= $erro_form_crit ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fas fa-stream me-2 text-primary opacity-75"></i>Níveis de Criticidade Cadastrados</h6>
            <span class="badge bg-secondary rounded-pill"><?= count($lista_niveis_crit) ?> nível(is)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th>Nome do Nível</th>
                            <th class="text-center">Ordem</th>
                            <th class="text-center">Cor</th>
                            <th>Descrição</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 15%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_niveis_crit)): ?>
                            <tr><td colspan="7" class="text-center text-muted p-4">Nenhum nível de criticidade cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_niveis_crit as $nivel_crit): ?>
                                <tr>
                                    <td class="fw-bold">#<?= htmlspecialchars($nivel_crit['id']) ?></td>
                                    <td><?= htmlspecialchars($nivel_crit['nome_nivel_criticidade']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($nivel_crit['valor_ordenacao']) ?></td>
                                    <td class="text-center">
                                        <span class="badge p-2" style="background-color: <?= htmlspecialchars($nivel_crit['cor_hex_associada'] ?? '#6c757d') ?>;" title="<?= htmlspecialchars($nivel_crit['cor_hex_associada'] ?? 'Cor Padrão') ?>">
                                                
                                        </span>
                                    </td>
                                    <td class="small text-muted" title="<?= htmlspecialchars($nivel_crit['descricao_nivel_criticidade'] ?? '') ?>"><?= htmlspecialchars(mb_strimwidth($nivel_crit['descricao_nivel_criticidade'] ?? 'N/A', 0, 80, "...")) ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $nivel_crit['ativo'] ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                                            <?= $nivel_crit['ativo'] ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td class="text-center action-buttons-table">
                                        <div class="d-inline-flex">
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1 action-btn btn-edit-nivel-crit"
                                                    data-bs-toggle="modal" data-bs-target="#modalCriarEditarNivelCrit"
                                                    data-id="<?= $nivel_crit['id'] ?>"
                                                    data-nome="<?= htmlspecialchars($nivel_crit['nome_nivel_criticidade']) ?>"
                                                    data-descricao="<?= htmlspecialchars($nivel_crit['descricao_nivel_criticidade'] ?? '') ?>"
                                                    data-ordem="<?= htmlspecialchars($nivel_crit['valor_ordenacao']) ?>"
                                                    data-cor="<?= htmlspecialchars($nivel_crit['cor_hex_associada'] ?? '#6c757d') ?>"
                                                    data-ativo="<?= $nivel_crit['ativo'] ?>"
                                                    title="Editar Nível de Criticidade">
                                                <i class="fas fa-edit fa-fw"></i>
                                            </button>
                                            <?php if ($nivel_crit['ativo']): ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Desativar este Nível?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="desativar_nivel_crit"><input type="hidden" name="nivel_crit_id" value="<?= $nivel_crit['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary action-btn" title="Desativar"><i class="fas fa-toggle-off fa-fw"></i></button></form>
                                            <?php else: ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Ativar este Nível?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="ativar_nivel_crit"><input type="hidden" name="nivel_crit_id" value="<?= $nivel_crit['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-success action-btn" title="Ativar"><i class="fas fa-toggle-on fa-fw"></i></button></form>
                                            <?php endif; ?>
                                            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline" onsubmit="return confirm('EXCLUIR este Nível? Verifique se não está em uso.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="excluir_nivel_crit"><input type="hidden" name="nivel_crit_id" value="<?= $nivel_crit['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Excluir"><i class="fas fa-trash-alt fa-fw"></i></button></form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php /* Paginação pode ser adicionada aqui se a lista crescer muito */ ?>
    </div>
</div>

<!-- Modal para Criar/Editar Nível de Criticidade -->
<div class="modal fade" id="modalCriarEditarNivelCrit" tabindex="-1" aria-labelledby="modalNivelCritLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="formModalNivelCrit" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                <input type="hidden" name="action" id="modal_nivel_crit_action" value="criar_nivel_crit">
                <input type="hidden" name="nivel_crit_id" id="modal_nivel_crit_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalNivelCritLabel">Novo Nível de Criticidade</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($erro_form_crit && $edit_id_crit): ?>
                        <div class="alert alert-warning small p-2" role="alert" id="modal_edit_error_placeholder_crit">
                            <strong>Erro ao salvar:</strong> <?= $erro_form_crit ?>
                        </div>
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="modal_nome_nivel_crit" class="form-label form-label-sm">Nome do Nível <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="modal_nome_nivel_crit" name="nome_nivel_crit" required maxlength="50"
                                   value="<?= htmlspecialchars($edit_id_crit && $nivel_crit_para_editar ? ($form_data_crit['nome_nivel_crit'] ?? $nivel_crit_para_editar['nome_nivel_criticidade']) : ($form_data_crit['nome_nivel_crit'] ?? '')) ?>">
                            <div class="invalid-feedback">Nome é obrigatório (máx 50 caracteres).</div>
                        </div>
                        <div class="col-md-3">
                            <label for="modal_valor_ordenacao_crit" class="form-label form-label-sm">Valor de Ordenação <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="modal_valor_ordenacao_crit" name="valor_ordenacao_crit" required min="0"
                                   value="<?= htmlspecialchars($edit_id_crit && $nivel_crit_para_editar ? ($form_data_crit['valor_ordenacao_crit'] ?? $nivel_crit_para_editar['valor_ordenacao']) : ($form_data_crit['valor_ordenacao_crit'] ?? '0')) ?>">
                            <div class="invalid-feedback">Valor numérico (0+) para ordenar.</div>
                        </div>
                        <div class="col-md-2">
                             <label for="modal_cor_hex_crit" class="form-label form-label-sm">Cor</label>
                             <input type="color" class="form-control form-control-sm form-control-color" id="modal_cor_hex_crit" name="cor_hex_crit"
                                    value="<?= htmlspecialchars($edit_id_crit && $nivel_crit_para_editar ? ($form_data_crit['cor_hex_crit'] ?? $nivel_crit_para_editar['cor_hex_associada']) : ($form_data_crit['cor_hex_crit'] ?? '#6c757d')) ?>" title="Escolha uma cor representativa">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="modal_descricao_nivel_crit" class="form-label form-label-sm">Descrição</label>
                        <textarea class="form-control form-control-sm" id="modal_descricao_nivel_crit" name="descricao_nivel_crit" rows="2"><?= htmlspecialchars($edit_id_crit && $nivel_crit_para_editar ? ($form_data_crit['descricao_nivel_crit'] ?? $nivel_crit_para_editar['descricao_nivel_criticidade']) : ($form_data_crit['descricao_nivel_crit'] ?? '')) ?></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="modal_ativo_nivel_crit" name="ativo_nivel_crit" value="1"
                               <?= ($edit_id_crit && $nivel_crit_para_editar) ? (!empty($form_data_crit) ? ($form_data_crit['ativo_nivel_crit'] ?? 0) : $nivel_crit_para_editar['ativo']) ? 'checked' : '' : (!empty($form_data_crit) ? ($form_data_crit['ativo_nivel_crit'] ?? 0) : 'checked') ?>>
                        <label class="form-check-label small" for="modal_ativo_nivel_crit">Ativo (disponível para uso)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnSalvarModalNivelCrit"><i class="fas fa-save me-1"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validação Bootstrap
    const formsModalCrit = document.querySelectorAll('#formModalNivelCrit.needs-validation');
    formsModalCrit.forEach(form => { /* ... (validação Bootstrap padrão) ... */ });

    const modalCriarEditarNivelCrit = document.getElementById('modalCriarEditarNivelCrit');
    if (modalCriarEditarNivelCrit) {
        const modalForm = modalCriarEditarNivelCrit.querySelector('form');
        const modalTitle = modalCriarEditarNivelCrit.querySelector('.modal-title');
        const actionInput = modalCriarEditarNivelCrit.querySelector('#modal_nivel_crit_action');
        const idInput = modalCriarEditarNivelCrit.querySelector('#modal_nivel_crit_id');
        const nomeInput = modalCriarEditarNivelCrit.querySelector('#modal_nome_nivel_crit');
        const descInput = modalCriarEditarNivelCrit.querySelector('#modal_descricao_nivel_crit');
        const ordemInput = modalCriarEditarNivelCrit.querySelector('#modal_valor_ordenacao_crit');
        const corInput = modalCriarEditarNivelCrit.querySelector('#modal_cor_hex_crit');
        const ativoCheckbox = modalCriarEditarNivelCrit.querySelector('#modal_ativo_nivel_crit');
        const errorPlaceholder = modalCriarEditarNivelCrit.querySelector('#modal_edit_error_placeholder_crit');

        modalCriarEditarNivelCrit.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const action = button ? button.getAttribute('data-action') : 'criar';
            modalForm.classList.remove('was-validated');
            if(errorPlaceholder) errorPlaceholder.style.display = 'none';

            if (action === 'editar' && button) {
                modalTitle.textContent = 'Editar Nível de Criticidade';
                actionInput.value = 'salvar_edicao_nivel_crit';
                idInput.value = button.getAttribute('data-id');
                nomeInput.value = button.getAttribute('data-nome');
                descInput.value = button.getAttribute('data-descricao');
                ordemInput.value = button.getAttribute('data-ordem');
                corInput.value = button.getAttribute('data-cor');
                ativoCheckbox.checked = button.getAttribute('data-ativo') === '1';
            } else {
                modalTitle.textContent = 'Novo Nível de Criticidade';
                actionInput.value = 'criar_nivel_crit';
                idInput.value = '';
                if (!<?= json_encode(!empty($erro_form_crit) && !$edit_id_crit) ?>) {
                    modalForm.reset();
                    ativoCheckbox.checked = true;
                    corInput.value = '#6c757d'; // Reset cor para default
                    ordemInput.value = '0'; // Reset ordem para default
                }
            }
        });

        <?php if ($erro_form_crit && $edit_id_crit && $nivel_crit_para_editar): ?>
        const modalInstanceCrit = new bootstrap.Modal(modalCriarEditarNivelCrit);
        modalCriarEditarNivelCrit.querySelector('.modal-title').textContent = 'Editar Nível de Criticidade (Verifique os Erros)';
        modalCriarEditarNivelCrit.querySelector('#modal_nivel_crit_action').value = 'salvar_edicao_nivel_crit';
        modalCriarEditarNivelCrit.querySelector('#modal_nivel_crit_id').value = '<?= $edit_id_crit ?>';
        // Campos já populados pelo PHP
        modalInstanceCrit.show();
        <?php endif; ?>
    }
});
</script>

<?php
echo getFooterAdmin();
?>