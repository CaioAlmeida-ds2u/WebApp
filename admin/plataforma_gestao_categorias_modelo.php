<?php
// admin/plataforma_gestao_categorias_modelo.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');
$erro_form_cat_mod = $_SESSION['erro_form_cat_mod'] ?? null; unset($_SESSION['erro_form_cat_mod']);
$form_data_cat_mod = $_SESSION['form_data_cat_mod'] ?? []; unset($_SESSION['form_data_cat_mod']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (TODA A SUA LÓGICA POST EXISTENTE - MANTENHA COMO ESTÁ)
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $action_cat_mod = $_POST['action'] ?? '';
        $cat_mod_id_acao = filter_input(INPUT_POST, 'cat_mod_id', FILTER_VALIDATE_INT);
        
        $nome_cat_mod_form = trim($_POST['nome_categoria_modelo'] ?? '');
        $desc_cat_mod_form = trim($_POST['descricao_categoria_modelo'] ?? '');
        $ativo_cat_mod_form = isset($_POST['ativo_categoria_modelo']) ? 1 : 0;

        $is_form_action = in_array($action_cat_mod, ['criar_categoria_modelo', 'salvar_edicao_categoria_modelo']);
        $has_form_error = !empty($_SESSION['erro_form_cat_mod']);
        if (!($is_form_action && $has_form_error)) {
            $_SESSION['csrf_token'] = gerar_csrf_token();
        }

        switch ($action_cat_mod) {
            case 'criar_categoria_modelo':
                if (empty($nome_cat_mod_form)) {
                    $_SESSION['erro_form_cat_mod'] = "O nome da categoria é obrigatório.";
                    $_SESSION['form_data_cat_mod'] = $_POST;
                } else {
                    $res_criacao_cat = criarCategoriaModelo($conexao, $nome_cat_mod_form, $desc_cat_mod_form, $ativo_cat_mod_form, $_SESSION['usuario_id']);
                    if ($res_criacao_cat === true) {
                        definir_flash_message('sucesso', "Categoria de Modelo '".htmlspecialchars($nome_cat_mod_form)."' criada.");
                    } else {
                        $_SESSION['erro_form_cat_mod'] = is_string($res_criacao_cat) ? $res_criacao_cat : "Erro ao criar categoria.";
                        $_SESSION['form_data_cat_mod'] = $_POST;
                    }
                }
                break;
            case 'salvar_edicao_categoria_modelo':
                if ($cat_mod_id_acao && !empty($nome_cat_mod_form)) {
                    $res_edicao_cat = atualizarCategoriaModelo($conexao, $cat_mod_id_acao, $nome_cat_mod_form, $desc_cat_mod_form, $ativo_cat_mod_form, $_SESSION['usuario_id']);
                    if ($res_edicao_cat === true) {
                        definir_flash_message('sucesso', "Categoria de Modelo (ID: $cat_mod_id_acao) atualizada.");
                    } else {
                        definir_flash_message('erro', is_string($res_edicao_cat) ? $res_edicao_cat : "Erro ao atualizar categoria ID $cat_mod_id_acao.");
                    }
                } else {
                    definir_flash_message('erro', "Dados inválidos para edição da Categoria de Modelo.");
                }
                break;
            case 'ativar_categoria_modelo':
            case 'desativar_categoria_modelo':
                if ($cat_mod_id_acao) {
                    $novo_stat_cat = ($action_cat_mod === 'ativar_categoria_modelo');
                    if (setStatusCategoriaModelo($conexao, $cat_mod_id_acao, $novo_stat_cat)) { // Removido admin_id se a função não usa
                        definir_flash_message('sucesso', "Status da Categoria de Modelo ID $cat_mod_id_acao atualizado.");
                    } else {
                        definir_flash_message('erro', "Erro ao atualizar status da Categoria ID $cat_mod_id_acao.");
                    }
                }
                break;
            case 'excluir_categoria_modelo':
                if ($cat_mod_id_acao) {
                    $res_exc_cat = excluirCategoriaModelo($conexao, $cat_mod_id_acao); // Removido admin_id se a função não usa
                    if ($res_exc_cat === true) {
                        definir_flash_message('sucesso', "Categoria de Modelo ID $cat_mod_id_acao excluída (se não estiver em uso).");
                    } else {
                        definir_flash_message('erro', is_string($res_exc_cat) ? $res_exc_cat : "Erro ao excluir Categoria ID $cat_mod_id_acao.");
                    }
                }
                break;
            default: // Adicionado default para o switch
                definir_flash_message('info', 'Ação desconhecida no POST.');
                break;
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
} // Fim do if ($_SERVER['REQUEST_METHOD'] === 'POST')

$lista_categorias_modelo = listarTodasCategoriasModelo($conexao);

$cat_mod_para_editar = null;
$edit_id_cat_mod = filter_input(INPUT_GET, 'edit_cat_mod_id', FILTER_VALIDATE_INT);
if ($edit_id_cat_mod) {
    $cat_mod_para_editar = getCategoriaModeloPorId($conexao, $edit_id_cat_mod);
    if (!$cat_mod_para_editar && !$erro_msg && !$sucesso_msg && !$erro_form_cat_mod) { // Evita sobrescrever msg de POST
        definir_flash_message('erro', "Categoria de Modelo para edição não encontrada (ID: $edit_id_cat_mod).");
    }
}

if (empty($_SESSION['csrf_token']) || ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$edit_id_cat_mod) || !empty($erro_form_cat_mod)) {
    $_SESSION['csrf_token'] = gerar_csrf_token();
}
$csrf_token_page = $_SESSION['csrf_token'];

$title = "ACodITools - Gerenciar Categorias de Modelo de Auditoria";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-sitemap me-2"></i>Gerenciar Categorias de Modelo</h1>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="modal" data-bs-target="#modalCriarEditarCatMod" data-action="criar">
            <i class="fas fa-plus me-1"></i> Nova Categoria de Modelo
        </button>
    </div>

    <?php if ($sucesso_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($erro_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($erro_form_cat_mod && !$edit_id_cat_mod): // Só mostra erro de criação aqui, erro de edição vai pro modal ?>
        <div class="alert alert-warning alert-dismissible fade show small" role="alert">
            <strong>Erro ao criar:</strong> <?= $erro_form_cat_mod ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fas fa-list-ul me-2 text-primary opacity-75"></i>Categorias Cadastradas</h6>
            <span class="badge bg-secondary rounded-pill"><?= count($lista_categorias_modelo) ?> categoria(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th>Nome da Categoria</th>
                            <th>Descrição</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 15%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_categorias_modelo)): ?>
                            <tr><td colspan="5" class="text-center text-muted p-4">Nenhuma categoria de modelo cadastrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_categorias_modelo as $cat_mod_item): ?>
                                <tr>
                                    <td class="fw-bold">#<?= htmlspecialchars($cat_mod_item['id']) ?></td>
                                    <td><?= htmlspecialchars($cat_mod_item['nome_categoria_modelo']) ?></td>
                                    <td class="small text-muted" title="<?= htmlspecialchars($cat_mod_item['descricao_categoria_modelo'] ?? '') ?>"><?= htmlspecialchars(mb_strimwidth($cat_mod_item['descricao_categoria_modelo'] ?? 'N/A', 0, 100, "...")) ?></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill <?= $cat_mod_item['ativo'] ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                                            <?= $cat_mod_item['ativo'] ? 'Ativa' : 'Inativa' ?>
                                        </span>
                                    </td>
                                    <td class="text-center action-buttons-table">
                                        <div class="d-inline-flex">
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1 action-btn btn-edit-cat-mod"
                                                    data-bs-toggle="modal" data-bs-target="#modalCriarEditarCatMod"
                                                    data-action="editar" <?php /* Adicionado para JS */ ?>
                                                    data-id="<?= $cat_mod_item['id'] ?>"
                                                    data-nome="<?= htmlspecialchars($cat_mod_item['nome_categoria_modelo']) ?>"
                                                    data-descricao="<?= htmlspecialchars($cat_mod_item['descricao_categoria_modelo'] ?? '') ?>"
                                                    data-ativo="<?= $cat_mod_item['ativo'] ?>"
                                                    title="Editar Categoria">
                                                <i class="fas fa-edit fa-fw"></i>
                                            </button>
                                            <?php $action_url_form_cat_mod = $_SERVER['PHP_SELF']; // As ações POST vão para a mesma página ?>
                                            <?php if ($cat_mod_item['ativo']): ?>
                                                <form method="POST" action="<?= $action_url_form_cat_mod ?>" class="d-inline me-1" onsubmit="return confirm('Desativar esta Categoria?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="desativar_categoria_modelo"><input type="hidden" name="cat_mod_id" value="<?= $cat_mod_item['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary action-btn" title="Desativar"><i class="fas fa-toggle-off fa-fw"></i></button></form>
                                            <?php else: ?>
                                                <form method="POST" action="<?= $action_url_form_cat_mod ?>" class="d-inline me-1" onsubmit="return confirm('Ativar esta Categoria?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="ativar_categoria_modelo"><input type="hidden" name="cat_mod_id" value="<?= $cat_mod_item['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-success action-btn" title="Ativar"><i class="fas fa-toggle-on fa-fw"></i></button></form>
                                            <?php endif; ?>
                                            <form method="POST" action="<?= $action_url_form_cat_mod ?>" class="d-inline" onsubmit="return confirm('EXCLUIR esta Categoria? Verifique se não está em uso por modelos.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="excluir_categoria_modelo"><input type="hidden" name="cat_mod_id" value="<?= $cat_mod_item['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Excluir"><i class="fas fa-trash-alt fa-fw"></i></button></form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Criar/Editar Categoria de Modelo -->
<div class="modal fade" id="modalCriarEditarCatMod" tabindex="-1" aria-labelledby="modalCatModLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="formModalCatMod" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                <input type="hidden" name="action" id="modal_cat_mod_action" value="criar_categoria_modelo">
                <input type="hidden" name="cat_mod_id" id="modal_cat_mod_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalCatModLabel">Nova Categoria de Modelo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php /* Mostrar erro de edição DENTRO do modal, erro de criação é mostrado na página principal */ ?>
                    <?php if ($erro_form_cat_mod && $edit_id_cat_mod && $cat_mod_para_editar): ?>
                        <div class="alert alert-warning small p-2" role="alert" id="modal_edit_error_placeholder_cat_mod"><?= $erro_form_cat_mod ?></div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="modal_nome_categoria_modelo" class="form-label form-label-sm">Nome da Categoria <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="modal_nome_categoria_modelo" name="nome_categoria_modelo" required maxlength="100"
                               value="<?= htmlspecialchars( ($edit_id_cat_mod && $cat_mod_para_editar && empty($form_data_cat_mod['nome_categoria_modelo'])) ? $cat_mod_para_editar['nome_categoria_modelo'] : ($form_data_cat_mod['nome_categoria_modelo'] ?? '') ) ?>">
                        <div class="invalid-feedback">Nome é obrigatório (máx 100 caracteres).</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_descricao_categoria_modelo" class="form-label form-label-sm">Descrição</label>
                        <textarea class="form-control form-control-sm" id="modal_descricao_categoria_modelo" name="descricao_categoria_modelo" rows="3"><?= htmlspecialchars( ($edit_id_cat_mod && $cat_mod_para_editar && empty($form_data_cat_mod['descricao_categoria_modelo'])) ? $cat_mod_para_editar['descricao_categoria_modelo'] : ($form_data_cat_mod['descricao_categoria_modelo'] ?? '') ) ?></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <?php
                            $modal_ativo_checked = true; // Default para criação
                            if ($edit_id_cat_mod && $cat_mod_para_editar) { // Modo edição
                                $modal_ativo_checked = !empty($form_data_cat_mod['ativo_categoria_modelo']) ? (bool)$form_data_cat_mod['ativo_categoria_modelo'] : (bool)$cat_mod_para_editar['ativo'];
                            } elseif (!empty($form_data_cat_mod) && isset($form_data_cat_mod['ativo_categoria_modelo'])) { // Repopulando criação com erro
                                $modal_ativo_checked = (bool)$form_data_cat_mod['ativo_categoria_modelo'];
                            }
                        ?>
                        <input class="form-check-input" type="checkbox" role="switch" id="modal_ativo_categoria_modelo" name="ativo_categoria_modelo" value="1" <?= $modal_ativo_checked ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="modal_ativo_categoria_modelo">Ativa (disponível para uso)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnSalvarModalCatMod"><i class="fas fa-save me-1"></i> Salvar Categoria</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const formsModalCatMod = document.querySelectorAll('#formModalCatMod.needs-validation');
    formsModalCatMod.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    const modalCatMod = document.getElementById('modalCriarEditarCatMod');
    if (modalCatMod) {
        const modalForm = modalCatMod.querySelector('form');
        const modalTitle = modalCatMod.querySelector('.modal-title');
        const actionInput = modalCatMod.querySelector('#modal_cat_mod_action');
        const idInput = modalCatMod.querySelector('#modal_cat_mod_id');
        const nomeInput = modalCatMod.querySelector('#modal_nome_categoria_modelo');
        const descInput = modalCatMod.querySelector('#modal_descricao_categoria_modelo');
        const ativoCheckbox = modalCatMod.querySelector('#modal_ativo_categoria_modelo');
        const errorPlaceholderModal = modalCatMod.querySelector('#modal_edit_error_placeholder_cat_mod'); // Para erro de edição

        modalCatMod.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const action = button ? button.dataset.action : 'criar'; // Pegar do data-action do botão
            modalForm.classList.remove('was-validated');
            if(errorPlaceholderModal) errorPlaceholderModal.style.display = 'none';

            if (action === 'editar' && button) {
                modalTitle.textContent = 'Editar Categoria de Modelo';
                actionInput.value = 'salvar_edicao_categoria_modelo';
                idInput.value = button.dataset.id;
                // Se NÃO houve erro no POST de edição (recarregamento por GET?edit_cat_mod_id=X)
                // E se NÃO estamos repopulando o form de edição com erro de POST
                if (!<?= json_encode($erro_form_cat_mod && $edit_id_cat_mod) ?>) {
                    nomeInput.value = button.dataset.nome;
                    descInput.value = button.dataset.descricao;
                    ativoCheckbox.checked = button.dataset.ativo === '1';
                }
                // Se houve erro no POST de edição, os valores já foram setados pelo PHP com $form_data_cat_mod
            } else { // Ação 'criar'
                modalTitle.textContent = 'Nova Categoria de Modelo';
                actionInput.value = 'criar_categoria_modelo';
                idInput.value = '';
                // Se NÃO estamos repopulando o form de criação com erro de POST
                if (!<?= json_encode($erro_form_cat_mod && !$edit_id_cat_mod) ?>) {
                    modalForm.reset();
                    ativoCheckbox.checked = true; // Default ativo para criar
                }
                // Se houve erro no POST de criação, os valores já foram setados pelo PHP com $form_data_cat_mod
            }
        });

        <?php if ($erro_form_cat_mod && $edit_id_cat_mod && $cat_mod_para_editar): ?>
        // Forçar modal de EDIÇÃO aberto se houve erro no POST de edição
        const modalInstanceParaEditarComErro = new bootstrap.Modal(modalCatMod);
        if (modalTitle) modalTitle.textContent = 'Editar Categoria (Verifique os Erros)';
        if (actionInput) actionInput.value = 'salvar_edicao_categoria_modelo';
        if (idInput) idInput.value = '<?= $edit_id_cat_mod ?>';
        // Os campos do form já foram populados com $form_data_cat_mod pelo PHP
        if(errorPlaceholderModal && '<?= !empty($erro_form_cat_mod) ?>') {
            errorPlaceholderModal.innerHTML = '<?= addslashes(str_replace("\n", "<br>", $erro_form_cat_mod)) ?>'; // Exibe erro no modal
            errorPlaceholderModal.style.display = 'block';
        }
        modalInstanceParaEditarComErro.show();
        <?php endif; ?>
    }
});
</script>

<?php
echo getFooterAdmin();
// Não precisa de 'exit' aqui se não houver mais código PHP a ser executado que poderia causar saída indesejada.
// A estrutura if/elseif/if para as etapas já controla o fluxo.
?>