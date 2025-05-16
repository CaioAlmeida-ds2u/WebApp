<?php
// admin/plataforma_catalogo_tipos_nao_conformidade.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Precisará de funções CRUD para tipos_nc

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Admin da Acoditools
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');
$erro_form_msg = $_SESSION['erro_form_tipo_nc'] ?? null; unset($_SESSION['erro_form_tipo_nc']);
$form_data_tipo_nc = $_SESSION['form_data_tipo_nc'] ?? []; unset($_SESSION['form_data_tipo_nc']);

// --- Processamento de Ações POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $action = $_POST['action'] ?? '';
        $tipo_nc_id_acao = filter_input(INPUT_POST, 'tipo_nc_id', FILTER_VALIDATE_INT);
        $nome_tipo_nc_form = trim($_POST['nome_tipo_nc'] ?? '');
        $descricao_tipo_nc_form = trim($_POST['descricao_tipo_nc'] ?? '');
        $ativo_tipo_nc_form = isset($_POST['ativo_tipo_nc']) ? 1 : 0;

        // Regenerar token, exceto se for erro de validação do form de criar/editar
        $is_form_action_with_potential_repopulation = in_array($action, ['criar_tipo_nc', 'salvar_edicao_tipo_nc']);
        $has_form_error = !empty($_SESSION['erro_form_tipo_nc']); // Checa se já tem erro de validação antes de regenerar

        if (!($is_form_action_with_potential_repopulation && $has_form_error)) {
             $_SESSION['csrf_token'] = gerar_csrf_token();
        }


        switch ($action) {
            case 'criar_tipo_nc':
                if (empty($nome_tipo_nc_form)) {
                    $_SESSION['erro_form_tipo_nc'] = "O nome do tipo de não conformidade é obrigatório.";
                    $_SESSION['form_data_tipo_nc'] = $_POST;
                } else {
                    // *** Chamar função: criarTipoNaoConformidadeGlobal($conexao, $nome_tipo_nc_form, $descricao_tipo_nc_form, $ativo_tipo_nc_form, $_SESSION['usuario_id']) ***
                    $resultado_criacao = criarTipoNaoConformidadeGlobal($conexao, $nome_tipo_nc_form, $descricao_tipo_nc_form, $ativo_tipo_nc_form, $_SESSION['usuario_id']);
                    if ($resultado_criacao === true) {
                        definir_flash_message('sucesso', "Tipo de Não Conformidade '".htmlspecialchars($nome_tipo_nc_form)."' criado com sucesso.");
                    } else {
                        $_SESSION['erro_form_tipo_nc'] = is_string($resultado_criacao) ? $resultado_criacao : "Erro ao criar o tipo de não conformidade.";
                        $_SESSION['form_data_tipo_nc'] = $_POST;
                    }
                }
                break;

            case 'salvar_edicao_tipo_nc':
                if ($tipo_nc_id_acao && !empty($nome_tipo_nc_form)) {
                     // *** Chamar função: atualizarTipoNaoConformidadeGlobal($conexao, $tipo_nc_id_acao, $nome_tipo_nc_form, $descricao_tipo_nc_form, $ativo_tipo_nc_form, $_SESSION['usuario_id']) ***
                    $resultado_edicao = atualizarTipoNaoConformidadeGlobal($conexao, $tipo_nc_id_acao, $nome_tipo_nc_form, $descricao_tipo_nc_form, $ativo_tipo_nc_form, $_SESSION['usuario_id']);
                    if ($resultado_edicao === true) {
                        definir_flash_message('sucesso', "Tipo de Não Conformidade '".htmlspecialchars($nome_tipo_nc_form)."' (ID: $tipo_nc_id_acao) atualizado.");
                    } else {
                        // Se a atualização falhar, idealmente o editar.php (se for uma página separada) mostraria o erro.
                        // Aqui, podemos colocar na sessão para a página de edição carregar, ou mostrar um erro genérico.
                        definir_flash_message('erro', is_string($resultado_edicao) ? $resultado_edicao : "Erro ao atualizar o Tipo de Não Conformidade ID $tipo_nc_id_acao.");
                         // Para repopular o modal de edição corretamente, seria melhor redirecionar para a página com ?action=editar&id=...
                         // Mas por simplicidade, vamos só dar a mensagem de erro.
                    }
                } else {
                    definir_flash_message('erro', "Dados inválidos para edição do Tipo de Não Conformidade.");
                }
                break;

            case 'ativar_tipo_nc':
            case 'desativar_tipo_nc':
                if ($tipo_nc_id_acao) {
                    $novo_status_tnc = ($action === 'ativar_tipo_nc');
                    // *** Chamar função: setStatusTipoNaoConformidadeGlobal($conexao, $tipo_nc_id_acao, $novo_status_tnc, $_SESSION['usuario_id']) ***
                    if (setStatusTipoNaoConformidadeGlobal($conexao, $tipo_nc_id_acao, $novo_status_tnc, $_SESSION['usuario_id'])) {
                        definir_flash_message('sucesso', "Status do Tipo de Não Conformidade ID $tipo_nc_id_acao atualizado.");
                    } else {
                        definir_flash_message('erro', "Erro ao atualizar status do Tipo de Não Conformidade ID $tipo_nc_id_acao.");
                    }
                }
                break;

            case 'excluir_tipo_nc':
                if ($tipo_nc_id_acao) {
                    // *** Chamar função: excluirTipoNaoConformidadeGlobal($conexao, $tipo_nc_id_acao) ***
                    // Esta função deve verificar se o tipo de NC está em uso antes de excluir.
                    $resultado_exclusao_tnc = excluirTipoNaoConformidadeGlobal($conexao, $tipo_nc_id_acao);
                    if ($resultado_exclusao_tnc === true) {
                        definir_flash_message('sucesso', "Tipo de Não Conformidade ID $tipo_nc_id_acao excluído (se não estiver em uso).");
                    } else {
                        definir_flash_message('erro', is_string($resultado_exclusao_tnc) ? $resultado_exclusao_tnc : "Erro ao excluir Tipo de Não Conformidade ID $tipo_nc_id_acao.");
                    }
                }
                break;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}


// --- Busca de Dados para Exibição ---
$pagina_atual_tnc = filter_input(INPUT_GET, 'pagina_tnc', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina_tnc = 15; // Ou outro valor
// *** Chamar função: listarTiposNaoConformidadeGlobalPaginado($conexao, $pagina_atual_tnc, $itens_por_pagina_tnc, $filtros_tnc) ***
$tipos_nc_data = listarTiposNaoConformidadeGlobalPaginado($conexao, $pagina_atual_tnc, $itens_por_pagina_tnc); // Função a ser criada
$lista_tipos_nc = $tipos_nc_data['tipos_nc'] ?? [];
$paginacao_tnc = $tipos_nc_data['paginacao'] ?? ['total_paginas' => 0, 'pagina_atual' => 1, 'total_itens' => 0];


// Para o modal de edição, se um ID for passado via GET
$tipo_nc_para_editar = null;
$edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
if ($edit_id) {
    // *** Chamar função: getTipoNaoConformidadeGlobalPorId($conexao, $edit_id) ***
    $tipo_nc_para_editar = getTipoNaoConformidadeGlobalPorId($conexao, $edit_id); // Função a ser criada
    if (!$tipo_nc_para_editar) definir_flash_message('erro', "Tipo de Não Conformidade para edição não encontrado (ID: $edit_id).");
}

// CSRF token para os formulários
if (empty($_SESSION['csrf_token']) || ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$edit_id) || !empty($erro_form_msg)) {
    $_SESSION['csrf_token'] = gerar_csrf_token();
}
$csrf_token_page = $_SESSION['csrf_token'];


// --- Geração do HTML ---
$title = "ACodITools - Catálogo Global de Tipos de Não Conformidade";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-tags me-2"></i>Catálogo Global de Tipos de Não Conformidade</h1>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="modal" data-bs-target="#modalCriarEditarTipoNC" data-action="criar">
            <i class="fas fa-plus me-1"></i> Novo Tipo de NC
        </button>
    </div>

    <?php if ($sucesso_msg): /* ... Mensagens de sucesso ... */ endif; ?>
    <?php if ($erro_msg): /* ... Mensagens de erro ... */ endif; ?>
    <?php if ($erro_form_msg && !$edit_id): // Mostra erro do form de criar apenas se não estiver tentando editar ?>
        <div class="alert alert-warning alert-dismissible fade show small" role="alert">
            <strong>Erro ao criar:</strong> <?= $erro_form_msg ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    <!-- Card Lista de Tipos de NC -->
    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="mb-0 fw-bold d-flex align-items-center"><i class="fas fa-list-ul me-2 text-primary opacity-75"></i>Tipos Cadastrados</h6>
            <span class="badge bg-secondary rounded-pill"><?= $paginacao_tnc['total_itens'] ?? 0 ?> tipo(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th>Nome do Tipo de NC</th>
                            <th>Descrição</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 15%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_tipos_nc)): ?>
                            <tr><td colspan="5" class="text-center text-muted p-4"><i class="fas fa-info-circle d-block fs-4 mb-2 opacity-50"></i>Nenhum tipo de não conformidade cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_tipos_nc as $tipo_nc): ?>
                                <tr>
                                    <td class="fw-bold">#<?= htmlspecialchars($tipo_nc['id']) ?></td>
                                    <td><?= htmlspecialchars($tipo_nc['nome_tipo_nc']) ?></td>
                                    <td class="small text-muted" title="<?= htmlspecialchars($tipo_nc['descricao_tipo_nc'] ?? '') ?>"><?= htmlspecialchars(mb_strimwidth($tipo_nc['descricao_tipo_nc'] ?? 'N/A', 0, 100, "...")) ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $tipo_nc['ativo'] ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle' ?>">
                                            <?= $tipo_nc['ativo'] ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td class="text-center action-buttons-table">
                                        <div class="d-inline-flex flex-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1 action-btn btn-edit-tipo-nc"
                                                    data-bs-toggle="modal" data-bs-target="#modalCriarEditarTipoNC"
                                                    data-id="<?= $tipo_nc['id'] ?>"
                                                    data-nome="<?= htmlspecialchars($tipo_nc['nome_tipo_nc']) ?>"
                                                    data-descricao="<?= htmlspecialchars($tipo_nc['descricao_tipo_nc'] ?? '') ?>"
                                                    data-ativo="<?= $tipo_nc['ativo'] ?>"
                                                    title="Editar Tipo de NC">
                                                <i class="fas fa-edit fa-fw"></i>
                                            </button>
                                            <?php if ($tipo_nc['ativo']): ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Desativar este Tipo de NC?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="desativar_tipo_nc"><input type="hidden" name="tipo_nc_id" value="<?= $tipo_nc['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary action-btn" title="Desativar"><i class="fas fa-toggle-off fa-fw"></i></button></form>
                                            <?php else: ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Ativar este Tipo de NC?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="ativar_tipo_nc"><input type="hidden" name="tipo_nc_id" value="<?= $tipo_nc['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-success action-btn" title="Ativar"><i class="fas fa-toggle-on fa-fw"></i></button></form>
                                            <?php endif; ?>
                                            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline" onsubmit="return confirm('EXCLUIR este Tipo de NC? Verifique se não está em uso.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="excluir_tipo_nc"><input type="hidden" name="tipo_nc_id" value="<?= $tipo_nc['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Excluir"><i class="fas fa-trash-alt fa-fw"></i></button></form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (isset($paginacao_tnc) && $paginacao_tnc['total_paginas'] > 1): ?>
        <div class="card-footer bg-light py-2">
            <nav aria-label="Paginação de Tipos de NC">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php $link_pag_tnc = "?pagina_tnc="; /* Adicionar outros filtros se houver */ ?>
                    <?php if ($paginacao_tnc['pagina_atual'] > 1): ?><li class="page-item"><a class="page-link" href="<?= $link_pag_tnc . ($paginacao_tnc['pagina_atual'] - 1) ?>">«</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">«</span></li><?php endif; ?>
                    <?php for ($i = 1; $i <= $paginacao_tnc['total_paginas']; $i++): /* Simplificado por enquanto */ ?>
                        <li class="page-item <?= ($i == $paginacao_tnc['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="<?= $link_pag_tnc . $i ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($paginacao_tnc['pagina_atual'] < $paginacao_tnc['total_paginas']): ?><li class="page-item"><a class="page-link" href="<?= $link_pag_tnc . ($paginacao_tnc['pagina_atual'] + 1) ?>">»</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">»</span></li><?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Criar/Editar Tipo de Não Conformidade -->
<div class="modal fade" id="modalCriarEditarTipoNC" tabindex="-1" aria-labelledby="modalTipoNCLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="formModalTipoNC" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                <input type="hidden" name="action" id="modal_tipo_nc_action" value="criar_tipo_nc">
                <input type="hidden" name="tipo_nc_id" id="modal_tipo_nc_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTipoNCLabel">Novo Tipo de Não Conformidade</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($erro_form_msg && $edit_id): // Mostra erro do form de editar DENTRO do modal se estava editando ?>
                        <div class="alert alert-warning small p-2" role="alert" id="modal_edit_error_placeholder">
                            <strong>Erro ao salvar:</strong> <?= $erro_form_msg ?>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="modal_nome_tipo_nc" class="form-label form-label-sm">Nome do Tipo de NC <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="modal_nome_tipo_nc" name="nome_tipo_nc" required maxlength="150" value="<?= htmlspecialchars($edit_id && $tipo_nc_para_editar ? ($form_data_tipo_nc['nome_tipo_nc'] ?? $tipo_nc_para_editar['nome_tipo_nc']) : ($form_data_tipo_nc['nome_tipo_nc'] ?? '')) ?>">
                        <div class="invalid-feedback">Nome é obrigatório.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_descricao_tipo_nc" class="form-label form-label-sm">Descrição</label>
                        <textarea class="form-control form-control-sm" id="modal_descricao_tipo_nc" name="descricao_tipo_nc" rows="3"><?= htmlspecialchars($edit_id && $tipo_nc_para_editar ? ($form_data_tipo_nc['descricao_tipo_nc'] ?? $tipo_nc_para_editar['descricao_tipo_nc']) : ($form_data_tipo_nc['descricao_tipo_nc'] ?? '')) ?></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="modal_ativo_tipo_nc" name="ativo_tipo_nc" value="1"
                               <?= ($edit_id && $tipo_nc_para_editar) ? (!empty($form_data_tipo_nc) ? ($form_data_tipo_nc['ativo_tipo_nc'] ?? 0) : $tipo_nc_para_editar['ativo']) ? 'checked' : '' : (!empty($form_data_tipo_nc) ? ($form_data_tipo_nc['ativo_tipo_nc'] ?? 0) : 'checked') ?>>
                        <label class="form-check-label small" for="modal_ativo_tipo_nc">Ativo (disponível para uso)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnSalvarModalTipoNC"><i class="fas fa-save me-1"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Configurações injetadas pelo PHP
const config = <?php echo json_encode($js_config); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Validação Bootstrap geral
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    const modalCriarEditarTipoNC = document.getElementById('modalCriarEditarTipoNC');
    if (!modalCriarEditarTipoNC) return; // Sai se o modal não existe

    // Selecionar elementos do modal
    const modalForm = modalCriarEditarTipoNC.querySelector('form');
    const modalTitle = modalCriarEditarTipoNC.querySelector('.modal-title');
    const modalActionInput = modalCriarEditarTipoNC.querySelector('#modal_tipo_nc_action');
    const modalIdInput = modalCriarEditarTipoNC.querySelector('#modal_tipo_nc_id');
    const modalNomeInput = modalCriarEditarTipoNC.querySelector('#modal_nome_tipo_nc');
    const modalDescInput = modalCriarEditarTipoNC.querySelector('#modal_descricao_tipo_nc');
    const modalAtivoCheckbox = modalCriarEditarTipoNC.querySelector('#modal_ativo_tipo_nc');
    const modalErrorPlaceholder = modalCriarEditarTipoNC.querySelector('#modal_edit_error_placeholder');

    // Verificar se todos os elementos necessários existem
    if (!modalForm || !modalTitle || !modalActionInput || !modalIdInput || 
        !modalNomeInput || !modalDescInput || !modalAtivoCheckbox) {
        console.error('Um ou mais elementos do modal não foram encontrados.');
        return;
    }

    // Evento de abertura do modal
    modalCriarEditarTipoNC.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget; // Botão que acionou o modal
        const action = button ? button.getAttribute('data-action') : 'criar';

        // Limpar validação prévia
        modalForm.classList.remove('was-validated');
        if (modalErrorPlaceholder) {
            modalErrorPlaceholder.style.display = 'none';
        }

        if (action === 'editar' && button) {
            // Modo edição
            modalTitle.textContent = 'Editar Tipo de Não Conformidade';
            modalActionInput.value = 'salvar_edicao_tipo_nc';
            modalIdInput.value = button.getAttribute('data-id') || '';
            modalNomeInput.value = button.getAttribute('data-nome') || '';
            modalDescInput.value = button.getAttribute('data-descricao') || '';
            modalAtivoCheckbox.checked = button.getAttribute('data-ativo') === '1';
        } else {
            // Modo criação
            modalTitle.textContent = 'Novo Tipo de Não Conformidade';
            modalActionInput.value = 'criar_tipo_nc';
            modalIdInput.value = '';

            // Limpar formulário apenas se não houver erro de validação
            if (!config.has_validation_error) {
                modalForm.reset();
                modalAtivoCheckbox.checked = true; // Default para ativo
            }
        }
    });

    // Reabrir modal em caso de erro de validação (edição)
    if (config.has_validation_error && config.is_editing) {
        const modalInstance = new bootstrap.Modal(modalCriarEditarTipoNC);
        modalTitle.textContent = 'Editar Tipo de Não Conformidade (Verifique os Erros)';
        modalActionInput.value = 'salvar_edicao_tipo_nc';
        modalIdInput.value = config.edit_id;
        // Campos já foram populados pelo PHP com $form_data_tipo_nc
        modalInstance.show();
    } else if (config.has_validation_error && !config.is_editing) {
        // Mostrar collapse de criação em caso de erro ao criar
        const collapseCriar = document.getElementById('collapseCriarModelo');
        if (collapseCriar) {
            new bootstrap.Collapse(collapseCriar, { toggle: true }).show();
        }
    }
});
</script>

<?php
echo getFooterAdmin();
?>