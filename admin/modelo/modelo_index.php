<?php
// admin/modelo/modelo_index.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_admin.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { header('Location: '.BASE_URL.'acesso_negado.php'); exit; }

// --- Mensagens e Dados para Repopulação ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');
$erro_criar_msg = $_SESSION['erro_criar_modelo'] ?? null; // Erro específico da criação de modelo
unset($_SESSION['erro_criar_modelo']);
$form_data = $_SESSION['form_data_modelo'] ?? []; // Para repopular form de criação
unset($_SESSION['form_data_modelo']);

// --- Carregar Dados para Dropdowns dos Formulários ---
$planos_assinatura_disponiveis = listarPlanosAssinatura($conexao, true); // Apenas ativos
$categorias_modelo_disponiveis = listarCategoriasModelo($conexao, true); // Apenas ativas (função a ser criada/verificada)


// --- Processamento POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', "Erro de validação da sessão.");
        // Log já feito em validar_csrf_token ou pode adicionar um específico aqui
    } else {
        $action = $_POST['action'] ?? null;
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $_SESSION['csrf_token'] = gerar_csrf_token(); // Regenera para o próximo request

        switch($action) {
            case 'criar_modelo':
                $dados_modelo_para_criar = [
                    'nome' => trim($_POST['nome_modelo_criar'] ?? ''),
                    'descricao' => trim($_POST['descricao_modelo_criar'] ?? ''),
                    'tipo_modelo_id' => filter_input(INPUT_POST, 'tipo_modelo_id_criar', FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]), // flags dentro do array
                    'versao_modelo' => trim($_POST['versao_modelo_criar'] ?? '1.0'),
                    'data_ultima_revisao_modelo' => !empty($_POST['data_ultima_revisao_modelo_criar']) ? trim($_POST['data_ultima_revisao_modelo_criar']) : null,
                    'proxima_revisao_sugerida_modelo' => !empty($_POST['proxima_revisao_sugerida_modelo_criar']) ? trim($_POST['proxima_revisao_sugerida_modelo_criar']) : null,
                    'disponibilidade_planos_ids' => $_POST['disponibilidade_planos_ids_modelo_criar'] ?? [],
                    'permite_copia_cliente' => isset($_POST['permite_copia_cliente_criar']) ? 1 : 0,
                    'ativo' => isset($_POST['ativo_modelo_criar']) ? 1 : 0,
                    // global_ou_empresa_id não vem do form, é definido na função
                ];

                $validation_errors_criar_modelo = [];
                if (empty($dados_modelo_para_criar['nome'])) {
                    $validation_errors_criar_modelo[] = "O nome do modelo é obrigatório.";
                }
                if ($dados_modelo_para_criar['tipo_modelo_id'] === null && !empty($_POST['tipo_modelo_id_criar'])) { // Se POST não for vazio mas falhou a validação INT
                    $validation_errors_criar_modelo[] = "Categoria do Modelo selecionada é inválida.";
                }
                // Adicionar mais validações (formato de data, versão, etc.) se necessário
                if ($dados_modelo_para_criar['data_ultima_revisao_modelo'] !== null) {
                    $d_rev = DateTime::createFromFormat('Y-m-d', $dados_modelo_para_criar['data_ultima_revisao_modelo']);
                    if (!$d_rev || $d_rev->format('Y-m-d') !== $dados_modelo_para_criar['data_ultima_revisao_modelo']) {
                        $validation_errors_criar_modelo[] = "Data da Última Revisão inválida.";
                    }
                }
                 if ($dados_modelo_para_criar['proxima_revisao_sugerida_modelo'] !== null) {
                    $d_prox_rev = DateTime::createFromFormat('Y-m-d', $dados_modelo_para_criar['proxima_revisao_sugerida_modelo']);
                    if (!$d_prox_rev || $d_prox_rev->format('Y-m-d') !== $dados_modelo_para_criar['proxima_revisao_sugerida_modelo']) {
                        $validation_errors_criar_modelo[] = "Data da Próxima Revisão Sugerida inválida.";
                    } elseif ($dados_modelo_para_criar['data_ultima_revisao_modelo'] !== null && $d_prox_rev <= DateTime::createFromFormat('Y-m-d', $dados_modelo_para_criar['data_ultima_revisao_modelo'])) {
                        $validation_errors_criar_modelo[] = "Data da Próxima Revisão deve ser após a Última Revisão.";
                    }
                }


                if (empty($validation_errors_criar_modelo)) {
                    // A função criarModeloAuditoria precisa aceitar o array $dados_modelo_para_criar
                    $resultado_op = criarModeloAuditoria($conexao, $dados_modelo_para_criar, $_SESSION['usuario_id']);

                    if ($resultado_op === true || (is_array($resultado_op) && ($resultado_op['success'] ?? false))) {
                        definir_flash_message('sucesso', "Modelo '".htmlspecialchars($dados_modelo_para_criar['nome'])."' criado com sucesso!");
                        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_modelo_sucesso', 1, "Modelo criado: {$dados_modelo_para_criar['nome']}", $conexao);
                        unset($_SESSION['form_data_modelo']); // Limpa dados do form da sessão em sucesso
                    } else {
                        $_SESSION['erro_criar_modelo'] = is_string($resultado_op) ? $resultado_op : "Erro desconhecido ao criar o modelo.";
                        $_SESSION['form_data_modelo'] = $_POST; // Mantém dados POST para repopular
                        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_modelo_falha_db', 0, "Falha: ".(is_string($resultado_op)?$resultado_op:"Erro DB"), $conexao);
                    }
                } else {
                    $_SESSION['erro_criar_modelo'] = "<strong>Erro ao criar modelo:</strong><ul><li>" . implode("</li><li>", $validation_errors_criar_modelo) . "</li></ul>";
                    $_SESSION['form_data_modelo'] = $_POST;
                }
                // Redirecionar para a mesma página para exibir mensagens e manter o collapse se houver erro
                $redirect_hash = (empty($validation_errors_criar_modelo) && ($resultado_op === true || (is_array($resultado_op) && ($resultado_op['success'] ?? false)))) ? '' : '#collapseCriarModelo';
                header('Location: ' . $_SERVER['PHP_SELF'] . $redirect_hash);
                exit;
                break;

            case 'ativar_modelo':
                if ($id && ativarModeloAuditoria($conexao, $id)) { definir_flash_message('sucesso', "Modelo ID $id ativado."); /* log ... */ }
                else { definir_flash_message('erro', "Erro ao ativar modelo ID $id.");  /* log ... */ }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(['pagina' => $_GET['pagina'] ?? 1])); exit; // Mantém paginação
                break;

            case 'desativar_modelo':
                 if ($id && desativarModeloAuditoria($conexao, $id)) { definir_flash_message('sucesso', "Modelo ID $id desativado."); /* log ... */ }
                 else { definir_flash_message('erro', "Erro ao desativar modelo ID $id."); /* log ... */ }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(['pagina' => $_GET['pagina'] ?? 1])); exit;
                break;

            case 'excluir_modelo':
                 if($id) {
                    $resultado_exclusao = excluirModeloAuditoria($conexao, $id); // Esta função precisa verificar se o modelo está em uso
                     if ($resultado_exclusao === true) { definir_flash_message('sucesso', "Modelo ID $id excluído (se não estiver em uso)."); /* log ... */ }
                     else { $msgErroExclusao = is_string($resultado_exclusao) ? $resultado_exclusao : "Erro ao excluir modelo ID $id."; definir_flash_message('erro', $msgErroExclusao); /* log ... */}
                 } else { definir_flash_message('erro', "ID inválido para exclusão."); }
                 header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(['pagina' => $_GET['pagina'] ?? 1])); exit;
                 break;

            default:
                definir_flash_message('erro', "Ação desconhecida.");
                 header('Location: ' . $_SERVER['PHP_SELF']); exit;
        }
    }
    // header('Location: ' . $_SERVER['PHP_SELF']); exit; // Movido para dentro dos cases para controle de hash
}

// --- Paginação e Listagem de Modelos ---
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina = 15;
// Adapte getModelosAuditoria para aceitar filtros se necessário (ex: por categoria, status)
$modelos_data = getModelosAuditoria($conexao, $pagina_atual, $itens_por_pagina, 'todos', '', null); // Adicionado null para filtro_empresa_id
$lista_modelos = $modelos_data['modelos'];
$paginacao = $modelos_data['paginacao'];

$title = "Modelos Globais de Auditoria - AcodITools";
echo getHeaderAdmin($title);
$csrf_token_page = $_SESSION['csrf_token']; // Pega token da sessão
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom flex-wrap">
        <h1 class="h2"><i class="fas fa-clipboard-list me-2"></i>Modelos Globais de Auditoria</h1>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCriarModelo" aria-expanded="<?= !empty($erro_criar_msg) ? 'true' : 'false' ?>" aria-controls="collapseCriarModelo">
            <i class="fas fa-plus me-1"></i> Novo Modelo Global
        </button>
    </div>

    <?php if ($sucesso_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($erro_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="collapse <?= !empty($erro_criar_msg) ? 'show' : '' ?> mb-4" id="collapseCriarModelo">
        <div class="card shadow-sm border-start-lg border-primary border-3">
            <div class="card-header bg-light py-3"><h6 class="mb-0 fw-bold"><i class="fas fa-plus-circle me-1"></i> Criar Novo Modelo Global</h6></div>
            <div class="card-body p-4">
                <?php if ($erro_criar_msg): ?>
                    <div class="alert alert-warning small p-2" role="alert"><?= $erro_criar_msg ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="createModelForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                    <input type="hidden" name="action" value="criar_modelo">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nome_modelo_criar" class="form-label form-label-sm fw-semibold">Nome do Modelo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="nome_modelo_criar" name="nome_modelo_criar" required maxlength="255" value="<?= htmlspecialchars($form_data['nome_modelo_criar'] ?? ($form_data['nome_modelo'] ?? '')) ?>">
                            <div class="invalid-feedback">Nome obrigatório.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="tipo_modelo_id_criar" class="form-label form-label-sm fw-semibold">Categoria do Modelo</label>
                            <select class="form-select form-select-sm" id="tipo_modelo_id_criar" name="tipo_modelo_id_criar">
                                <option value="">-- Nenhuma --</option>
                                <?php foreach ($categorias_modelo_disponiveis as $cat_mod): ?>
                                    <option value="<?= $cat_mod['id'] ?>" <?= (($form_data['tipo_modelo_id_criar'] ?? ($form_data['tipo_modelo_id'] ?? '')) == $cat_mod['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat_mod['nome_categoria_modelo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="descricao_modelo_criar" class="form-label form-label-sm fw-semibold">Descrição</label>
                            <textarea class="form-control form-control-sm" id="descricao_modelo_criar" name="descricao_modelo_criar" rows="2"><?= htmlspecialchars($form_data['descricao_modelo_criar'] ?? ($form_data['descricao_modelo'] ?? '')) ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label for="versao_modelo_criar" class="form-label form-label-sm fw-semibold">Versão</label>
                            <input type="text" class="form-control form-control-sm" id="versao_modelo_criar" name="versao_modelo_criar" value="<?= htmlspecialchars($form_data['versao_modelo_criar'] ?? '1.0') ?>" maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label for="data_ultima_revisao_modelo_criar" class="form-label form-label-sm fw-semibold">Data da Última Revisão</label>
                            <input type="date" class="form-control form-control-sm" id="data_ultima_revisao_modelo_criar" name="data_ultima_revisao_modelo_criar" value="<?= htmlspecialchars($form_data['data_ultima_revisao_modelo_criar'] ?? '') ?>">
                            <div class="invalid-feedback">Data inválida.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="proxima_revisao_sugerida_modelo_criar" class="form-label form-label-sm fw-semibold">Próxima Revisão Sugerida</label>
                            <input type="date" class="form-control form-control-sm" id="proxima_revisao_sugerida_modelo_criar" name="proxima_revisao_sugerida_modelo_criar" value="<?= htmlspecialchars($form_data['proxima_revisao_sugerida_modelo_criar'] ?? '') ?>">
                             <div class="invalid-feedback">Data inválida.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label form-label-sm fw-semibold">Disponibilidade para Planos de Assinatura</label>
                            <div class="border rounded p-2 bg-light-subtle" style="max-height: 130px; overflow-y:auto;">
                                <?php if(empty($planos_assinatura_disponiveis)): ?>
                                    <small class="text-muted">Nenhum plano ativo para seleção.</small>
                                <?php else:
                                    $planos_selecionados_form_criar_mod = $form_data['disponibilidade_planos_ids_modelo_criar'] ?? ($form_data['disponibilidade_planos_ids'] ?? []);
                                    foreach($planos_assinatura_disponiveis as $plano_mod_form): ?>
                                    <div class="form-check form-check-sm">
                                        <input class="form-check-input" type="checkbox" name="disponibilidade_planos_ids_modelo_criar[]" value="<?= $plano_mod_form['id'] ?>" id="pl_mod_criar_<?= $plano_mod_form['id'] ?>"
                                            <?= in_array($plano_mod_form['id'], $planos_selecionados_form_criar_mod) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="pl_mod_criar_<?= $plano_mod_form['id'] ?>"><?= htmlspecialchars($plano_mod_form['nome_plano']) ?></label>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                            <small class="form-text text-muted">Selecione os planos que terão acesso a este modelo. Se nenhum, pode ser disponível para todos.</small>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="permite_copia_cliente_criar" name="permite_copia_cliente_criar" value="1" <?= !empty($form_data['permite_copia_cliente_criar']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="permite_copia_cliente_criar">Permitir que empresas clientes copiem e customizem este modelo</label>
                            </div>
                            <div class="form-check form-switch">
                                <?php $ativo_mod_criar_default = true; if(array_key_exists('ativo_modelo_criar', $form_data)) $ativo_mod_criar_default = !empty($form_data['ativo_modelo_criar']); ?>
                                <input class="form-check-input" type="checkbox" role="switch" id="ativo_modelo_criar" name="ativo_modelo_criar" value="1" <?= $ativo_mod_criar_default ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="ativo_modelo_criar">Modelo Ativo (disponível para uso)</label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm me-2" data-bs-toggle="collapse" data-bs-target="#collapseCriarModelo" aria-expanded="false">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Salvar Novo Modelo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="mb-0 fw-bold d-flex align-items-center"><i class="fas fa-list me-2 text-primary opacity-75"></i>Modelos Globais Cadastrados</h6>
            <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex align-items-center ms-auto small gx-2">
                <?php // Adicionar filtros para modelos (ex: por categoria, status) se desejar ?>
                <span class="badge bg-dark-subtle text-dark-emphasis rounded-pill py-1 px-2"><?= $paginacao['total_itens'] ?? 0 ?> modelo(s)</span>
            </form>
        </div>
        <div class="card-body p-0">
             <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th>Nome do Modelo</th>
                            <th>Categoria</th>
                            <th>Versão</th>
                            <th class="text-center">Itens</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                     <tbody>
                        <?php if (empty($lista_modelos)): ?>
                            <tr><td colspan="7" class="text-center text-muted p-4"><i class="fas fa-info-circle d-block fs-4 mb-2 opacity-50"></i>Nenhum modelo global cadastrado.</td></tr>
                        <?php else: foreach ($lista_modelos as $modelo): ?>
                        <tr>
                            <td class="fw-bold">#<?= htmlspecialchars($modelo['id']) ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>admin/modelo/editar_modelo.php?id=<?= $modelo['id'] ?>" class="text-decoration-none fw-medium">
                                    <?= htmlspecialchars($modelo['nome']) ?>
                                </a>
                                <?php if(!empty($modelo['descricao'])): ?>
                                    <small class="d-block text-muted fst-italic" title="<?= htmlspecialchars($modelo['descricao']) ?>"><?= htmlspecialchars(mb_strimwidth($modelo['descricao'], 0, 70, "...")) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                    <?= htmlspecialchars($modelo['nome_categoria_modelo'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= htmlspecialchars($modelo['versao_modelo'] ?? '1.0') ?></span></td>
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill">
                                    <?= htmlspecialchars($modelo['total_itens'] ?? 0) ?>
                                </span>
                            </td>
                            <td class="text-center"><span class="badge rounded-pill <?= $modelo['ativo']?'bg-success-subtle text-success-emphasis':'bg-danger-subtle text-danger-emphasis' ?>"><?= $modelo['ativo']?'Ativo':'Inativo' ?></span></td>
                            <td class="text-center action-buttons-table"><div class="d-inline-flex flex-nowrap">
                                <a href="<?= BASE_URL ?>admin/modelo/editar_modelo.php?id=<?= $modelo['id'] ?>" class="btn btn-sm btn-outline-primary me-1 action-btn" title="Editar Modelo e Seus Itens"><i class="fas fa-edit fa-fw"></i></a>
                                <?php $url_params_model_action = http_build_query(['pagina' => $pagina_atual]); ?>
                                <?php if ($modelo['ativo']): ?>
                                    <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>?<?= $url_params_model_action ?>" class="d-inline me-1" onsubmit="return confirm('Desativar o modelo <?= htmlspecialchars(addslashes($modelo['nome'])) ?>?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="desativar_modelo"><input type="hidden" name="id" value="<?= $modelo['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-warning action-btn" title="Desativar"><i class="fas fa-toggle-off fa-fw"></i></button></form>
                                <?php else: ?>
                                    <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>?<?= $url_params_model_action ?>" class="d-inline me-1" onsubmit="return confirm('Ativar o modelo <?= htmlspecialchars(addslashes($modelo['nome'])) ?>?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="ativar_modelo"><input type="hidden" name="id" value="<?= $modelo['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-success action-btn" title="Ativar"><i class="fas fa-toggle-on fa-fw"></i></button></form>
                                <?php endif; ?>
                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>?<?= $url_params_model_action ?>" class="d-inline" onsubmit="return confirm('EXCLUIR o modelo <?= htmlspecialchars(addslashes($modelo['nome'])) ?>? Esta ação não pode ser desfeita e removerá todos os seus itens.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="excluir_modelo"><input type="hidden" name="id" value="<?= $modelo['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Excluir Modelo"><i class="fas fa-trash-alt fa-fw"></i></button></form>
                            </div></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
             </div>
        </div>
         <?php if (isset($paginacao) && $paginacao['total_paginas'] > 1): ?>
         <div class="card-footer bg-light py-2"> <nav aria-label="Paginação de Modelos"> <ul class="pagination pagination-sm justify-content-center mb-0"> <?php $link_paginacao_modelo = "?pagina="; ?> <?php if ($paginacao['pagina_atual'] > 1): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao_modelo . ($paginacao['pagina_atual'] - 1) ?>">«</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">«</span></li> <?php endif; ?> <?php $inicio_m = max(1, $paginacao['pagina_atual'] - 2); $fim_m = min($paginacao['total_paginas'], $paginacao['pagina_atual'] + 2); if ($inicio_m > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; for ($i_m = $inicio_m; $i_m <= $fim_m; $i_m++): ?><li class="page-item <?= ($i_m == $paginacao['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="<?= $link_paginacao_modelo . $i_m ?>"><?= $i_m ?></a></li><?php endfor; if ($fim_m < $paginacao['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?> <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao_modelo . ($paginacao['pagina_atual'] + 1) ?>">»</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">»</span></li> <?php endif; ?> </ul> </nav> </div>
         <?php endif; ?>
    </div>
</div>
<?php
echo getFooterAdmin();
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const createModelForm = document.getElementById('createModelForm');
    if(createModelForm) {
        createModelForm.addEventListener('submit', event => {
            if (!createModelForm.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            createModelForm.classList.add('was-validated');
        }, false);
    }

    <?php if (!empty($erro_criar_msg)): ?>
        const collapseCreateModel = document.getElementById('collapseCriarModelo');
        if (collapseCreateModel) {
            let bsCollapseInstanceCreateModel = bootstrap.Collapse.getInstance(collapseCreateModel);
            if (!bsCollapseInstanceCreateModel) {
                bsCollapseInstanceCreateModel = new bootstrap.Collapse(collapseCreateModel, { toggle: false });
            }
            bsCollapseInstanceCreateModel.show();
            const nomeModeloInput = document.getElementById('nome_modelo_criar');
            if (nomeModeloInput) {
                 setTimeout(() => nomeModeloInput.focus(), 150);
            }
        }
    <?php endif; ?>
});
</script>