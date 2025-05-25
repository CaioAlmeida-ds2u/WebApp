<?php
// admin/modelo/editar_modelo.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_admin.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php'); exit;
}

$modelo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$modelo_id) {
    definir_flash_message('erro', "ID de modelo inválido.");
    header('Location: ' . BASE_URL . 'admin/modelo/modelo_index.php'); exit;
}

// Carregar dados do modelo, incluindo o array de planos já decodificado
$modelo = getModeloAuditoria($conexao, $modelo_id);
if (!$modelo) {
    definir_flash_message('erro', "Modelo com ID $modelo_id não encontrado.");
    header('Location: ' . BASE_URL . 'admin/modelo/modelo_index.php'); exit;
}
// $modelo['disponibilidade_planos_ids_array'] já vem de getModeloAuditoria

$sucesso_msg = obter_flash_message('sucesso');
$erro_msg_geral = obter_flash_message('erro'); // Erros de ações AJAX podem não usar flash, mas sim a resposta JSON
$erro_msg_form_modelo = ''; // Para erros de validação do form principal

// Processamento do formulário PRINCIPAL (dados do modelo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'salvar_dados_modelo') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        $erro_msg_form_modelo = "Erro de validação de segurança. Tente novamente.";
    } else {
        // Coleta dos dados do formulário de edição do modelo
        $dados_modelo_update = [
            'nome' => trim($_POST['nome_modelo_edit'] ?? ''),
            'descricao' => trim($_POST['descricao_modelo_edit'] ?? ''),
            'tipo_modelo_id' => filter_input(INPUT_POST, 'tipo_modelo_id_edit', FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]),
            'versao_modelo' => trim($_POST['versao_modelo_edit'] ?? '1.0'),
            'data_ultima_revisao_modelo' => !empty($_POST['data_ultima_revisao_modelo_edit']) ? trim($_POST['data_ultima_revisao_modelo_edit']) : null,
            'proxima_revisao_sugerida_modelo' => !empty($_POST['proxima_revisao_sugerida_modelo_edit']) ? trim($_POST['proxima_revisao_sugerida_modelo_edit']) : null,
            'disponibilidade_planos_ids' => $_POST['disponibilidade_planos_ids_modelo_edit'] ?? [],
            'permite_copia_cliente' => isset($_POST['permite_copia_cliente_edit']) ? 1 : 0,
            'ativo' => isset($_POST['ativo_modelo_edit']) ? 1 : 0,
            'global_ou_empresa_id' => null // Garantir
        ];

        // Validações
        $validation_errors_model = [];
        if (empty($dados_modelo_update['nome'])) $validation_errors_model[] = "O nome do modelo é obrigatório.";
        // Adicionar mais validações...

        if (empty($validation_errors_model)) {
            $resultado_update_modelo = atualizarModeloAuditoria($conexao, $modelo_id, $dados_modelo_update, $_SESSION['usuario_id']);
            if ($resultado_update_modelo === true) {
                definir_flash_message('sucesso', "Dados do modelo '".htmlspecialchars($dados_modelo_update['nome'])."' atualizados.");
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'update_modelo_global', 1, "Modelo ID: $modelo_id", $conexao);
                // Recarregar dados do modelo após salvar
                $modelo = getModeloAuditoria($conexao, $modelo_id);
                 // Redireciona para limpar o POST e mostrar flash message
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } else {
                $erro_msg_form_modelo = is_string($resultado_update_modelo) ? $resultado_update_modelo : "Erro ao salvar dados do modelo.";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'update_modelo_global_falha', 0, "Modelo ID: $modelo_id, Erro: ".$erro_msg_form_modelo, $conexao);
            }
        } else {
            $erro_msg_form_modelo = "<strong>Foram encontrados erros:</strong><ul><li>" . implode("</li><li>", $validation_errors_model) . "</li></ul>";
        }
        // Se chegou aqui com erro, repopula $modelo com os dados do form para exibição
        if (!empty($erro_msg_form_modelo)){
            $modelo = array_merge($modelo, $dados_modelo_update); // $modelo já tem a estrutura correta
            $modelo['disponibilidade_planos_ids_array'] = $dados_modelo_update['disponibilidade_planos_ids']; // Mantém o array de IDs
        }
    }
}


// Carregar dados para UI APÓS possível POST e ANTES de renderizar
$itensDoModeloAgrupados = getItensDoModelo($conexao, $modelo_id, true); // true para agrupar por seção
$totalItensModelo = 0;
foreach($itensDoModeloAgrupados as $secaoItens) { $totalItensModelo += count($secaoItens); }
$requisitosDisponiveis = getRequisitosDisponiveisParaModelo($conexao, $modelo_id);
$planos_assinatura_disponiveis = listarPlanosAssinatura($conexao, true);
$categorias_modelo_disponiveis = listarCategoriasModelo($conexao, true);

// Token CSRF para todos os formulários na página
if ($_SERVER['REQUEST_METHOD'] === 'GET' || !empty($erro_msg_form_modelo) ) { // Regenera em GET ou se houve erro no form principal
    $_SESSION['csrf_token'] = gerar_csrf_token();
}
$csrf_token_page = $_SESSION['csrf_token'];


$title = "Editar Modelo Global: " . htmlspecialchars($modelo['nome']);
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
        <div>
            <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-edit me-2 text-primary"></i>Editar Modelo Global de Auditoria</h1>
            <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb"><li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/dashboard_admin.php">Dashboard</a></li><li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/modelo/modelo_index.php">Modelos Globais</a></li><li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($modelo['nome']) ?></li></ol></nav>
        </div>
        <a href="<?= BASE_URL ?>admin/modelo/modelo_index.php" class="btn btn-outline-secondary rounded-pill px-3 action-button-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
    </div>

    <?php if ($sucesso_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($erro_msg_geral): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($erro_msg_geral) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($erro_msg_form_modelo): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= $erro_msg_form_modelo ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <div id="ajax-messages-container"></div> <!-- Para mensagens de erro/sucesso do AJAX -->


    <div class="row g-4">
        <div class="col-lg-5 order-lg-2">
            <?php /* Card de Edição dos Dados do Modelo */ ?>
            <div class="card shadow-sm mb-4 rounded-3 border-0">
                <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary opacity-75"></i>Dados Gerais do Modelo</h6></div>
                <div class="card-body p-3">
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" id="editModelForm" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                        <input type="hidden" name="action" value="salvar_dados_modelo">
                        
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="nome_modelo_edit" class="form-label form-label-sm fw-semibold">Nome do Modelo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="nome_modelo_edit" name="nome_modelo_edit" value="<?= htmlspecialchars($modelo['nome'] ?? '') ?>" required maxlength="255">
                                <div class="invalid-feedback">Nome é obrigatório.</div>
                            </div>
                            <div class="col-md-5">
                                <label for="versao_modelo_edit" class="form-label form-label-sm fw-semibold">Versão</label>
                                <input type="text" class="form-control form-control-sm" id="versao_modelo_edit" name="versao_modelo_edit" value="<?= htmlspecialchars($modelo['versao_modelo'] ?? '1.0') ?>" maxlength="20">
                            </div>
                             <div class="col-12">
                                <label for="descricao_modelo_edit" class="form-label form-label-sm fw-semibold">Descrição</label>
                                <textarea class="form-control form-control-sm" id="descricao_modelo_edit" name="descricao_modelo_edit" rows="2"><?= htmlspecialchars($modelo['descricao'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-12">
                                <label for="tipo_modelo_id_edit" class="form-label form-label-sm fw-semibold">Categoria do Modelo</label>
                                <select class="form-select form-select-sm" id="tipo_modelo_id_edit" name="tipo_modelo_id_edit">
                                    <option value="">-- Nenhuma Categoria --</option>
                                    <?php foreach ($categorias_modelo_disponiveis as $cat_item): ?>
                                        <option value="<?= $cat_item['id'] ?>" <?= (($modelo['tipo_modelo_id'] ?? null) == $cat_item['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat_item['nome_categoria_modelo']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="data_ultima_revisao_modelo_edit" class="form-label form-label-sm fw-semibold">Data Últ. Revisão</label>
                                <input type="date" class="form-control form-control-sm" id="data_ultima_revisao_modelo_edit" name="data_ultima_revisao_modelo_edit" value="<?= htmlspecialchars($modelo['data_ultima_revisao_modelo'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="proxima_revisao_sugerida_modelo_edit" class="form-label form-label-sm fw-semibold">Próx. Revisão Sugerida</label>
                                <input type="date" class="form-control form-control-sm" id="proxima_revisao_sugerida_modelo_edit" name="proxima_revisao_sugerida_modelo_edit" value="<?= htmlspecialchars($modelo['proxima_revisao_sugerida_modelo'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label form-label-sm fw-semibold">Disponibilidade para Planos</label>
                                <div class="border rounded p-2 bg-light-subtle" style="max-height: 120px; overflow-y:auto;">
                                     <?php if(empty($planos_assinatura_disponiveis)): ?>
                                        <small class="text-muted">Nenhum plano ativo.</small>
                                    <?php else:
                                        foreach($planos_assinatura_disponiveis as $pl_opt_edit): ?>
                                        <div class="form-check form-check-sm">
                                            <input class="form-check-input" type="checkbox" name="disponibilidade_planos_ids_modelo_edit[]" value="<?= $pl_opt_edit['id'] ?>" id="pl_mod_edit_<?= $pl_opt_edit['id'] ?>"
                                                <?= in_array($pl_opt_edit['id'], $modelo['disponibilidade_planos_ids_array'] ?? []) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="pl_mod_edit_<?= $pl_opt_edit['id'] ?>"><?= htmlspecialchars($pl_opt_edit['nome_plano']) ?></label>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="permite_copia_cliente_edit" name="permite_copia_cliente_edit" value="1" <?= !empty($modelo['permite_copia_cliente']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="permite_copia_cliente_edit">Permitir cópia/customização por clientes</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="ativo_modelo_edit" name="ativo_modelo_edit" value="1" <?= !empty($modelo['ativo']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="ativo_modelo_edit">Modelo Ativo</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100 mt-3"><i class="fas fa-save me-1"></i> Salvar Alterações do Modelo</button>
                    </form>
                </div>
            </div>

            <?php /* Card Adicionar Requisitos ao Modelo (via AJAX) */ ?>
            <div class="card shadow-sm rounded-3 border-0 sticky-lg-top" style="top: 20px;">
                <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-plus-circle me-2 text-success opacity-75"></i>Adicionar Requisitos ao Modelo</h6></div>
                <div class="card-body p-3">
                    <form id="addItemAjaxForm" class="needs-validation" novalidate> <!-- Não tem action, será AJAX -->
                        <input type="hidden" name="csrf_token" class="csrf-token-ajax" value="<?= htmlspecialchars($csrf_token_page) ?>">
                        <input type="hidden" name="action" value="adicionar_itens_modelo">
                        <input type="hidden" name="modelo_id" value="<?= $modelo_id ?>">
                        <div class="mb-2">
                            <label for="requisitos_ids_ajax" class="form-label form-label-sm fw-semibold">Requisitos Disponíveis <span class="text-danger">*</span></label>
                            <input type="search" id="filtroReqDisponiveisAjax" class="form-control form-control-sm mb-1" placeholder="Filtrar requisitos disponíveis...">
                            <select multiple class="form-select form-select-sm" id="requisitos_ids_ajax" name="requisitos_ids[]" size="8" required>
                                <?php if(empty($requisitosDisponiveis)): ?>
                                    <option value="" disabled>Nenhum requisito disponível</option>
                                <?php else:
                                    // ... (lógica para optgroup igual ao seu código anterior) ...
                                    $grupoAtualDispAjax = null;
                                    foreach($requisitosDisponiveis as $reqDispAjax):
                                        $grupoDispAjax = $reqDispAjax['norma_referencia'] ?? ($reqDispAjax['categoria'] ?? 'Geral');
                                        if($grupoDispAjax !== $grupoAtualDispAjax) {
                                            if($grupoAtualDispAjax !== null) echo '</optgroup>';
                                            echo '<optgroup label="'.htmlspecialchars($grupoDispAjax).'">';
                                            $grupoAtualDispAjax = $grupoDispAjax;
                                        } ?>
                                        <option value="<?= $reqDispAjax['id'] ?>" data-texto="<?= htmlspecialchars(strtolower($reqDispAjax['codigo'] . ' ' . $reqDispAjax['nome'] . ' ' . $grupoDispAjax )) ?>">
                                            <?= htmlspecialchars($reqDispAjax['codigo'] ? $reqDispAjax['codigo'].': ' : 'ID '.$reqDispAjax['id'].': ') . htmlspecialchars($reqDispAjax['nome']) ?>
                                        </option>
                                    <?php endforeach;
                                    if($grupoAtualDispAjax !== null) echo '</optgroup>';
                                endif; ?>
                            </select>
                            <div class="invalid-feedback">Selecione pelo menos um requisito.</div>
                            <div class="form-text small text-muted mt-1">Use Ctrl/Cmd+Click ou Shift+Click para selecionar múltiplos.</div>
                        </div>
                        <div class="mb-3">
                            <label for="secao_add_ajax" class="form-label form-label-sm fw-semibold">Agrupar na Seção (Opcional)</label>
                            <input type="text" class="form-control form-control-sm" id="secao_add_ajax" name="secao_add" placeholder="Deixe vazio para 'Itens Gerais'" list="secoesExistentesModeloAjax">
                            <datalist id="secoesExistentesModeloAjax">
                                <?php
                                $secoesUnicasAjax = array_keys($itensDoModeloAgrupados);
                                sort($secoesUnicasAjax);
                                foreach ($secoesUnicasAjax as $secAjax):
                                    if($secAjax != 'Itens Gerais'): ?>
                                        <option value="<?= htmlspecialchars($secAjax) ?>">
                                <?php endif; endforeach; ?>
                            </datalist>
                        </div>
                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-plus me-1"></i> Adicionar Selecionados (AJAX)</button>
                    </form>
                </div>
            </div>
        </div>

        <?php /* Coluna Direita: Itens Atuais do Modelo (AJAX para remoção) */ ?>
        <div class="col-lg-7 order-lg-1">
            <div class="card shadow-sm rounded-3 border-0">
                <div class="card-header bg-light border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-list-ol me-2 text-primary opacity-75"></i>Itens Atuais no Modelo</h6>
                    <span class="badge bg-dark-subtle text-dark-emphasis rounded-pill"><?= $totalItensModelo ?> item(s)</span>
                </div>
                <div class="card-body p-3">
                   <?php if ($totalItensModelo === 0): // Verificação mais explícita ?>
                       <p class="text-center text-muted py-4">Nenhum requisito adicionado a este modelo ainda.</p>
                   <?php else: ?>
                       <p class="small text-muted fst-italic mb-3"><i class="fas fa-arrows-alt-v me-1"></i> Arraste <i class="fas fa-grip-vertical text-muted mx-1"></i> para reordenar dentro de cada seção.</p>
                       <?php foreach ($itensDoModeloAgrupados as $secao => $itensDaSecao): ?>
                           <fieldset class="border rounded p-3 mb-3 bg-light-subtle section-container" data-secao="<?= htmlspecialchars($secao) ?>" id="secao-container-<?= htmlspecialchars(str_replace(' ', '-', $secao)) ?>">
                               <legend class="h6 small fw-bold text-primary mb-2 d-flex justify-content-between align-items-center">
                                   <span><i class="fas <?= ($secao === 'Itens Gerais') ? 'fa-stream' : 'fa-folder-open' ?> me-1"></i> <?= htmlspecialchars($secao) ?></span>
                                   <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill"><?= count($itensDaSecao) ?></span>
                               </legend>
                               <ul class="list-group list-group-flush item-list rounded" style="cursor: move;">
                                   <?php foreach ($itensDaSecao as $item): ?>
                                   <li class="list-group-item d-flex justify-content-between align-items-center px-2 py-1 item-row" data-item-id="<?= $item['modelo_item_id'] ?>" id="modelo_item_row_<?= $item['modelo_item_id'] ?>">
                                       <div class="flex-grow-1 text-truncate me-2" style="min-width: 0;">
                                           <i class="fas fa-grip-vertical text-muted me-2 fa-xs drag-handle" title="Reordenar"></i>
                                           <span class="text-muted small me-1" title="ID do Requisito: <?= $item['requisito_id'] ?>">(#<?= $item['requisito_id'] ?>)</span>
                                           <strong class="small me-1"><?= htmlspecialchars($item['codigo'] ?: '') ?></strong>
                                           <span class="small" title="<?= htmlspecialchars($item['nome']) ?>"><?= htmlspecialchars(mb_strimwidth($item['nome'], 0, 55, "...")) ?></span>
                                           <span class="badge <?= $item['requisito_ativo'] ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?> ms-1 x-small" title="Status do Requisito Mestre"><?= $item['requisito_ativo'] ? 'Ativo' : 'Inativo' ?></span>
                                       </div>
                                       <button type="button" class="btn btn-sm btn-outline-danger border-0 py-0 px-1 btn-remove-item-ajax ms-auto" data-modelo-item-id="<?= $item['modelo_item_id'] ?>" title="Remover Item do Modelo (AJAX)"><i class="fas fa-times fa-xs"></i></button>
                                   </li>
                                   <?php endforeach; ?>
                               </ul>
                               <?php if(empty($itensDaSecao)): ?>
                                 <p class="small text-muted text-center mb-0 fst-italic">(Esta seção está vazia)</p>
                               <?php endif; ?>
                           </fieldset>
                       <?php endforeach; ?>
                   <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php echo getFooterAdmin(); ?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modeloIdGlobal = <?= $modelo_id ?>;
    const ajaxMessagesContainer = document.getElementById('ajax-messages-container');

    function displayAjaxMessage(message, type = 'success') {
        const alertType = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        if (ajaxMessagesContainer) {
            const msgDiv = document.createElement('div');
            msgDiv.className = `alert ${alertType} alert-dismissible fade show small py-2`;
            msgDiv.role = 'alert';
            msgDiv.innerHTML = `<i class="fas ${icon} me-2"></i>${message}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>`;
            ajaxMessagesContainer.prepend(msgDiv); // Adiciona no topo
            setTimeout(() => { bootstrap.Alert.getOrCreateInstance(msgDiv).close(); }, 5000);
        } else {
            alert(message); // Fallback
        }
    }

    function updateAllCsrfTokens(newToken) {
        document.querySelectorAll('input[name="csrf_token"], .csrf-token-ajax').forEach(input => {
            input.value = newToken;
        });
        // console.log('Tokens CSRF atualizados para:', newToken);
    }

    // Validação do Formulário Principal (Editar Dados do Modelo)
    const editModelForm = document.getElementById('editModelForm');
    if (editModelForm) {
        editModelForm.addEventListener('submit', event => {
            if (!editModelForm.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            editModelForm.classList.add('was-validated');
        }, false);
    }

    // Filtro da lista de requisitos disponíveis (para Adicionar AJAX)
    const filtroReqInputAjax = document.getElementById('filtroReqDisponiveisAjax');
    const selectReqAjax = document.getElementById('requisitos_ids_ajax');
    let originalOptionsDataAjax = [];

    if (selectReqAjax && selectReqAjax.options.length > 0 && selectReqAjax.options[0].value !== "") { // Só roda se houver opções
         originalOptionsDataAjax = Array.from(selectReqAjax.querySelectorAll('option')).map(opt => {
              if (!opt.value) return null;
              const parentLabel = opt.parentElement.tagName === 'OPTGROUP' ? opt.parentElement.label : null;
              return { value: opt.value, text: opt.text, searchText: (opt.dataset.texto || (opt.text + (parentLabel ? ' ' + parentLabel : ''))).toLowerCase(), group: parentLabel };
           }).filter(Boolean);
    }

    if (filtroReqInputAjax && selectReqAjax) {
        filtroReqInputAjax.addEventListener('input', function() {
            const termo = this.value.toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            selectReqAjax.innerHTML = '';
            let currentGroupLabel = null; let optgroupElement = null; let hasResults = false;

            originalOptionsDataAjax.forEach(optData => {
                 if (termo === '' || optData.searchText.normalize("NFD").replace(/[\u0300-\u036f]/g, "").includes(termo)) {
                    hasResults = true;
                    if (optData.group && optData.group !== currentGroupLabel) {
                        optgroupElement = document.createElement('optgroup');
                        optgroupElement.label = optData.group;
                        selectReqAjax.appendChild(optgroupElement);
                        currentGroupLabel = optData.group;
                    }
                    const optionElement = document.createElement('option');
                    optionElement.value = optData.value;
                    optionElement.textContent = optData.text;
                    if (optgroupElement && optData.group) { optgroupElement.appendChild(optionElement); }
                    else { selectReqAjax.appendChild(optionElement); }
                }
            });
            if (!hasResults) {
                  const noOpt = document.createElement('option');
                  noOpt.disabled = true;
                  noOpt.textContent = termo === '' ? 'Nenhum requisito disponível' : 'Nenhum requisito para "' + this.value + '"';
                  selectReqAjax.appendChild(noOpt);
            }
        });
    }

    // AJAX para Adicionar Itens
    const addItemAjaxForm = document.getElementById('addItemAjaxForm');
    if (addItemAjaxForm) {
        addItemAjaxForm.addEventListener('submit', function(event) {
            event.preventDefault();
            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                return;
            }
            this.classList.add('was-validated');

            const formData = new FormData(this);
            // O CSRF token já está no form e será pego por FormData.
            // O action e modelo_id também.

            fetch('<?= BASE_URL ?>admin/modelo/ajax_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.novo_csrf_token) updateAllCsrfTokens(data.novo_csrf_token);
                if (data.success) {
                    displayAjaxMessage(data.message, 'success');
                    // Idealmente, recarregar apenas a lista de itens do modelo e os requisitos disponíveis.
                    // Por simplicidade, vamos recarregar a página para ver todas as mudanças.
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    displayAjaxMessage(data.message || 'Erro desconhecido ao adicionar itens.', 'danger');
                }
            })
            .catch(error => {
                console.error('Erro AJAX ao adicionar itens:', error);
                displayAjaxMessage('Erro de comunicação ao tentar adicionar itens.', 'danger');
            });
        });
    }


    // AJAX para Remover Itens e Drag & Drop (Reordenação)
    document.querySelectorAll('.item-list').forEach(listEl => {
        if(!listEl.querySelector('li.item-row')) { // Se a lista não tem itens, não inicializa
            const secaoContainer = listEl.closest('.section-container');
            if (secaoContainer && !secaoContainer.querySelector('.fst-italic.text-muted')) { // Evita duplicar msg de seção vazia
                 const pVazio = document.createElement('p');
                 pVazio.className = 'small text-muted text-center mb-0 fst-italic';
                 pVazio.textContent = '(Esta seção está vazia)';
                 listEl.parentNode.appendChild(pVazio); // Adiciona msg após o UL
            }
            return;
        }

        new Sortable(listEl, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'bg-info-subtle opacity-75', // Classe para o fantasma
            onEnd: function (evt) {
                if (evt.oldIndex === evt.newIndex && evt.from === evt.to) return;

                const items = evt.to.querySelectorAll('li.item-row');
                const ordemIds = Array.from(items).map(el => el.dataset.itemId);
                const secaoContainer = evt.to.closest('.section-container');
                const secao = secaoContainer ? secaoContainer.dataset.secao : null;

                const formDataReorder = new FormData();
                formDataReorder.append('action', 'salvar_ordem');
                formDataReorder.append('modelo_id', modeloIdGlobal);
                formDataReorder.append('secao', secao === 'Itens Gerais' ? '' : secao);
                formDataReorder.append('ordem_ids', JSON.stringify(ordemIds));
                formDataReorder.append('csrf_token', document.querySelector('.csrf-token-ajax').value); // Pega de um input com a classe

                if (secaoContainer) secaoContainer.classList.add('border-warning'); // Feedback visual

                fetch('<?= BASE_URL ?>admin/modelo/ajax_handler.php', { method: 'POST', body: formDataReorder })
                .then(response => response.json())
                .then(data => {
                    if (secaoContainer) secaoContainer.classList.remove('border-warning');
                    if (data.novo_csrf_token) updateAllCsrfTokens(data.novo_csrf_token);
                    if (data.success) {
                        displayAjaxMessage(data.message || 'Ordem salva.', 'success');
                    } else {
                        displayAjaxMessage(data.message || 'Erro ao salvar ordem.', 'danger');
                        // Considerar reverter a ordem visualmente ou alertar para recarregar
                        // alert("Houve um erro ao salvar a nova ordem. A lista pode não estar correta. Recarregue a página.");
                    }
                }).catch(err => {
                    if (secaoContainer) secaoContainer.classList.remove('border-warning');
                    displayAjaxMessage('Erro de comunicação ao salvar ordem.', 'danger');
                });
            }
        });
    });

    // Event listener para botões de remover item (delegação de evento)
    document.querySelector('.card-body').addEventListener('click', function(event) {
        const removeButton = event.target.closest('.btn-remove-item-ajax');
        if (removeButton) {
            const modeloItemId = removeButton.dataset.modeloItemId;
            const csrfTokenVal = document.querySelector('.csrf-token-ajax').value;
            const listItemEl = document.getElementById(`modelo_item_row_${modeloItemId}`);

            if (!confirm('Tem certeza que deseja remover este item do modelo?')) return;

            const formDataRemove = new FormData();
            formDataRemove.append('action', 'remover_item_modelo');
            formDataRemove.append('modelo_item_id', modeloItemId);
            formDataRemove.append('csrf_token', csrfTokenVal);

            if(listItemEl) listItemEl.style.opacity = '0.5'; // Feedback visual

            fetch('<?= BASE_URL ?>admin/modelo/ajax_handler.php', {
                method: 'POST',
                body: formDataRemove
            })
            .then(response => response.json())
            .then(data => {
                if (data.novo_csrf_token) updateAllCsrfTokens(data.novo_csrf_token);
                if (data.success) {
                    displayAjaxMessage(data.message, 'success');
                    if (listItemEl) {
                        listItemEl.remove();
                        // Atualizar contagem da seção e total, se necessário
                    }
                } else {
                    if(listItemEl) listItemEl.style.opacity = '1';
                    displayAjaxMessage(data.message || 'Erro ao remover item.', 'danger');
                }
            })
            .catch(error => {
                if(listItemEl) listItemEl.style.opacity = '1';
                console.error('Erro AJAX ao remover item:', error);
                displayAjaxMessage('Erro de comunicação ao tentar remover o item.', 'danger');
            });
        }
    });
});
</script>