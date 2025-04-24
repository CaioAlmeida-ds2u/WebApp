<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_gestor.php';
require_once __DIR__ . '/../includes/gestor_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$empresa_id = $_SESSION['usuario_empresa_id'];
$gestor_id = $_SESSION['usuario_id'];

$modelos = getModelosAtivos($conexao);
$auditores = getAuditoresDaEmpresa($conexao, $empresa_id);
$requisitos_por_categoria = getRequisitosAtivosAgrupados($conexao, 1, 100);

$titulo = '';
$modo_criacao = 'modelo';
$modelo_id = null;
$requisitos_selecionados = [];
$auditor_id = null;
$escopo = '';
$objetivo = '';
$instrucoes = '';
$data_inicio = '';
$data_fim = '';
$filtro_requisitos = '';
$csrf_token = gerar_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação de segurança. Por favor, tente novamente.');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $titulo = trim(filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_STRING)) ?: '';
    $auditor_id = filter_input(INPUT_POST, 'auditor_id', FILTER_VALIDATE_INT) ?: null;
    $escopo = trim(filter_input(INPUT_POST, 'escopo', FILTER_SANITIZE_STRING)) ?: null;
    $objetivo = trim(filter_input(INPUT_POST, 'objetivo', FILTER_SANITIZE_STRING)) ?: null;
    $instrucoes = trim(filter_input(INPUT_POST, 'instrucoes', FILTER_SANITIZE_STRING)) ?: null;
    $data_inicio = filter_input(INPUT_POST, 'data_inicio', FILTER_SANITIZE_STRING) ?: null;
    $data_fim = filter_input(INPUT_POST, 'data_fim', FILTER_SANITIZE_STRING) ?: null;
    $modo_criacao = filter_input(INPUT_POST, 'modo_criacao', FILTER_SANITIZE_STRING) ?? 'modelo';
    $filtro_requisitos = trim(filter_input(INPUT_POST, 'filtro_requisitos', FILTER_SANITIZE_STRING)) ?: '';

    $modelo_id = null;
    $requisitos_selecionados_post = [];

    if ($modo_criacao === 'modelo') {
        $modelo_id = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT) ?: null;
    } elseif ($modo_criacao === 'manual') {
        if (isset($_POST['requisitos_selecionados']) && is_array($_POST['requisitos_selecionados'])) {
            $requisitos_selecionados_post = array_filter(array_map('intval', $_POST['requisitos_selecionados']), fn($id) => $id > 0);
        }
        $requisitos_selecionados = $requisitos_selecionados_post;
    }

    $errors = [];
    if (empty($titulo)) $errors[] = "Título da auditoria é obrigatório.";
    if ($modo_criacao === 'modelo' && empty($modelo_id)) $errors[] = "Selecione um modelo válido.";
    if ($modo_criacao === 'manual' && empty($requisitos_selecionados_post)) $errors[] = "Selecione ao menos um requisito.";
    if (!empty($data_inicio) && !DateTime::createFromFormat('Y-m-d', $data_inicio)) $errors[] = "Data de início inválida (use AAAA-MM-DD).";
    if (!empty($data_fim) && !DateTime::createFromFormat('Y-m-d', $data_fim)) $errors[] = "Data fim inválida (use AAAA-MM-DD).";
    if (!empty($data_inicio) && !empty($data_fim)) {
        $inicio = new DateTime($data_inicio);
        $fim = new DateTime($data_fim);
        if ($fim < $inicio) $errors[] = "Data fim não pode ser anterior à data de início.";
    }

    if (empty($errors)) {
        $dadosAuditoria = [
            'titulo' => $titulo,
            'empresa_id' => $empresa_id,
            'gestor_id' => $gestor_id,
            'auditor_id' => $auditor_id,
            'escopo' => $escopo,
            'objetivo' => $objetivo,
            'instrucoes' => $instrucoes,
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim,
            'modelo_id' => $modelo_id,
            'requisitos_selecionados' => $requisitos_selecionados_post
        ];
        
        error_log("Enviando para criarAuditoria: " . json_encode($dadosAuditoria));
        $novaAuditoriaId = criarAuditoria($conexao, $dadosAuditoria);

        if ($novaAuditoriaId) {
            definir_flash_message('sucesso', "Auditoria '" . htmlspecialchars($titulo) . "' planejada com sucesso (ID: $novaAuditoriaId).");
            header('Location: ' . BASE_URL . 'gestor/minhas_auditorias.php');
            exit;
        } else {
            definir_flash_message('erro', "Erro ao criar a auditoria. Verifique os dados ou tente novamente.");
        }
    } else {
        definir_flash_message('erro', "<strong>Erros encontrados:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>");
    }
}

$title = "Criar Nova Auditoria";
echo getHeaderGestor($title);
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-plus-circle me-2 text-primary"></i>Criar Nova Auditoria</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb"><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/minhas_auditorias.php">Auditorias</a></li><li class="breadcrumb-item active" aria-current="page">Criar</li></ol></nav>
    </div>
    <a href="<?= BASE_URL ?>gestor/minhas_auditorias.php" class="btn btn-outline-secondary rounded-pill px-3 action-button-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar para Auditorias</a>
</div>

<?php if ($erro = obter_flash_message('erro')): ?>
    <div class="alert alert-danger gestor-alert fade show" role="alert">
        <i class="fas fa-exclamation-triangle flex-shrink-0 me-2"></i>
        <div><?= $erro ?></div>
        <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="formCriarAuditoria" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2">
            <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary opacity-75"></i>Informações Gerais</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-12">
                    <label for="titulo" class="form-label form-label-sm fw-semibold">Título da Auditoria <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="titulo" name="titulo" value="<?= htmlspecialchars($titulo) ?>" required placeholder="Ex: Auditoria Interna ISO 27001 - TI - Q3 2025">
                    <div class="invalid-feedback">Título obrigatório.</div>
                </div>
                <div class="col-md-6">
                    <label for="auditor_id" class="form-label form-label-sm fw-semibold">Auditor Responsável</label>
                    <select class="form-select form-select-sm" id="auditor_id" name="auditor_id">
                        <option value="">-- Não atribuído --</option>
                        <?php if (!empty($auditores)): ?>
                            <?php foreach ($auditores as $auditor): ?>
                                <option value="<?= $auditor['id'] ?>" <?= ($auditor_id == $auditor['id']) ? 'selected' : '' ?>><?= htmlspecialchars($auditor['nome']) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Nenhum auditor na sua empresa</option>
                        <?php endif; ?>
                    </select>
                    <small class="form-text text-muted">Pode ser definido depois.</small>
                </div>
                <div class="col-md-3">
                    <label for="data_inicio" class="form-label form-label-sm fw-semibold">Início Planejado</label>
                    <input type="date" class="form-control form-control-sm" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
                    <div class="invalid-feedback">Data de início inválida.</div>
                </div>
                <div class="col-md-3">
                    <label for="data_fim" class="form-label form-label-sm fw-semibold">Fim Planejado</label>
                    <input type="date" class="form-control form-control-sm" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
                    <div class="invalid-feedback">Data fim inválida ou anterior à data de início.</div>
                </div>
                <div class="col-12">
                    <label for="escopo" class="form-label form-label-sm fw-semibold">Escopo</label>
                    <textarea class="form-control form-control-sm" id="escopo" name="escopo" rows="2" placeholder="Descreva o que será auditado..."><?= htmlspecialchars($escopo) ?></textarea>
                </div>
                <div class="col-12">
                    <label for="objetivo" class="form-label form-label-sm fw-semibold">Objetivo</label>
                    <textarea class="form-control form-control-sm" id="objetivo" name="objetivo" rows="2" placeholder="Descreva o objetivo principal..."><?= htmlspecialchars($objetivo) ?></textarea>
                </div>
                <div class="col-12">
                    <label for="instrucoes" class="form-label form-label-sm fw-semibold">Instruções para Auditor</label>
                    <textarea class="form-control form-control-sm" id="instrucoes" name="instrucoes" rows="2" placeholder="Instruções ou informações adicionais..."><?= htmlspecialchars($instrucoes) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2">
            <h6 class="mb-0 fw-bold"><i class="fas fa-tasks me-2 text-primary opacity-75"></i>Itens da Auditoria</h6>
        </div>
        <div class="card-body p-4">
            <div class="mb-3 border-bottom pb-3">
                <label class="form-label form-label-sm fw-semibold">Origem dos Requisitos:</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="modo_criacao" id="modoModelo" value="modelo" <?= ($modo_criacao === 'modelo') ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="modoModelo">Usar Modelo Pré-definido</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="modo_criacao" id="modoManual" value="manual" <?= ($modo_criacao === 'manual') ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="modoManual">Selecionar Manualmente</label>
                </div>
            </div>

            <div id="selecaoModeloDiv" class="mb-3 <?= ($modo_criacao === 'manual') ? 'd-none' : '' ?>">
                <label for="modelo_id" class="form-label form-label-sm fw-semibold">Modelo Base <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" id="modelo_id" name="modelo_id" <?= ($modo_criacao === 'modelo') ? 'required' : '' ?>>
                    <option value="" <?= empty($modelo_id) ? 'selected' : '' ?>>-- Selecione um Modelo --</option>
                    <?php if (empty($modelos)): ?>
                        <option value="" disabled>Nenhum modelo ativo cadastrado</option>
                    <?php else: ?>
                        <?php foreach ($modelos as $modelo): ?>
                            <option value="<?= $modelo['id'] ?>" <?= ($modo_criacao === 'modelo' && $modelo_id == $modelo['id']) ? 'selected' : '' ?>><?= htmlspecialchars($modelo['nome']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <div class="invalid-feedback">Selecione um modelo.</div>
                <small class="form-text text-muted">Os requisitos deste modelo serão adicionados à auditoria.</small>
            </div>

            <div id="selecaoManualDiv" class="mb-3 <?= ($modo_criacao === 'modelo') ? 'd-none' : '' ?>">
                <label class="form-label form-label-sm fw-semibold">Requisitos Disponíveis <span class="text-danger">*</span></label>
                <p class="text-muted small mb-2">Marque os requisitos a serem incluídos nesta auditoria.</p>
                <input type="search" id="filtroRequisitos" class="form-control form-control-sm mb-2" placeholder="Filtrar requisitos por nome ou código..." value="<?= htmlspecialchars($filtro_requisitos) ?>">
                <div id="requisitosChecklist" class="requisitos-checklist-container border rounded p-3 bg-light-subtle" style="max-height: 450px; overflow-y: auto;">
                    <?php if (empty($requisitos_por_categoria)): ?>
                        <p class="text-danger small m-0">Nenhum requisito ativo encontrado. Cadastre requisitos na área de administração.</p>
                    <?php else: ?>
                        <?php foreach ($requisitos_por_categoria as $categoria => $requisitos): ?>
                            <fieldset class="mb-3 categoria-group">
                                <legend class="h6 small fw-bold text-secondary border-bottom pb-1 mb-2 sticky-top bg-light-subtle py-1"><?= htmlspecialchars($categoria) ?></legend>
                                <?php foreach ($requisitos as $req): ?>
                                    <div class="form-check requisito-item" data-texto="<?= htmlspecialchars(strtolower($req['codigo'] . ' ' . $req['nome'])) ?>">
                                        <input class="form-check-input" type="checkbox" name="requisitos_selecionados[]" value="<?= $req['id'] ?>" id="req_<?= $req['id'] ?>" <?= (in_array($req['id'], $requisitos_selecionados)) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="req_<?= $req['id'] ?>">
                                            <strong title="<?= htmlspecialchars($req['nome']) ?>"><?= htmlspecialchars($req['codigo'] ?: 'ID ' . $req['id']) ?>:</strong> <?= htmlspecialchars($req['nome']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>
                        <p class="text-center text-muted small mt-3 no-results-message" style="display: none;">Nenhum requisito encontrado para o filtro.</p>
                    <?php endif; ?>
                </div>
                <div class="invalid-feedback d-block" id="requisitosError" style="display: none;">Selecione ao menos um requisito.</div>
            </div>
        </div>
    </div>

    <div class="mt-4 mb-5 d-flex justify-content-end">
        <a href="<?= BASE_URL ?>gestor/minhas_auditorias.php" class="btn btn-secondary rounded-pill px-4 me-2">Cancelar</a>
        <button type="submit" class="btn btn-success rounded-pill px-4 action-button-main">
            <i class="fas fa-save me-1"></i> Criar Auditoria
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formCriarAuditoria');
    const modoModeloRadio = document.getElementById('modoModelo');
    const modoManualRadio = document.getElementById('modoManual');
    const selecaoModeloDiv = document.getElementById('selecaoModeloDiv');
    const selecaoManualDiv = document.getElementById('selecaoManualDiv');
    const modeloSelect = document.getElementById('modelo_id');
    const requisitosContainer = document.getElementById('requisitosChecklist');
    const requisitosCheckboxes = requisitosContainer?.querySelectorAll('.requisito-item input[type="checkbox"]');
    const requisitosError = document.getElementById('requisitosError');
    const filtroInput = document.getElementById('filtroRequisitos');
    const noResultsMessage = requisitosContainer?.querySelector('.no-results-message');

    function toggleCampos() {
        if (!modoModeloRadio || !modoManualRadio) return;
        if (modoModeloRadio.checked) {
            selecaoModeloDiv.classList.remove('d-none');
            selecaoManualDiv.classList.add('d-none');
            if (modeloSelect) modeloSelect.required = true;
            if (requisitosError) requisitosError.style.display = 'none';
        } else {
            selecaoModeloDiv.classList.add('d-none');
            selecaoManualDiv.classList.remove('d-none');
            if (modeloSelect) { modeloSelect.required = false; modeloSelect.value = ''; }
        }
    }

    if (modoModeloRadio) modoModeloRadio.addEventListener('change', toggleCampos);
    if (modoManualRadio) modoManualRadio.addEventListener('change', toggleCampos);
    toggleCampos();

    if (form) {
        form.addEventListener('submit', event => {
            let manualRequisitosValido = true;
            let datasValidas = true;
            const dataInicio = document.getElementById('data_inicio').value;
            const dataFim = document.getElementById('data_fim').value;

            if (dataInicio && dataFim && new Date(dataFim) < new Date(dataInicio)) {
                datasValidas = false;
                const dataFimInput = document.getElementById('data_fim');
                dataFimInput.classList.add('is-invalid');
                dataFimInput.nextElementSibling.textContent = 'Data fim não pode ser anterior à data de início.';
            }

            if (modoManualRadio.checked) {
                let algumSelecionado = false;
                if (requisitosCheckboxes) {
                    requisitosCheckboxes.forEach(cb => { if (cb.checked) algumSelecionado = true; });
                }
                if (!algumSelecionado) {
                    manualRequisitosValido = false;
                    if (requisitosError) requisitosError.style.display = 'block';
                    requisitosContainer?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    if (requisitosError) requisitosError.style.display = 'none';
                }
            } else {
                if (requisitosError) requisitosError.style.display = 'none';
            }

            if (!form.checkValidity() || !manualRequisitosValido || !datasValidas) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
            if (modoModeloRadio.checked && modeloSelect && !modeloSelect.checkValidity()) {
                modeloSelect.classList.add('is-invalid');
            } else if (modeloSelect) {
                modeloSelect.classList.remove('is-invalid');
            }
        });

        if (requisitosCheckboxes && requisitosError) {
            requisitosCheckboxes.forEach(cb => {
                cb.addEventListener('change', () => {
                    if (modoManualRadio.checked) {
                        let algumSelecionado = false;
                        requisitosCheckboxes.forEach(innerCb => { if (innerCb.checked) algumSelecionado = true; });
                        if (algumSelecionado) requisitosError.style.display = 'none';
                    }
                });
            });
        }
    }

    if (filtroInput && requisitosContainer) {
        filtroInput.addEventListener('input', function() {
            const termo = this.value.toLowerCase().trim();
            let algumVisivel = false;
            const todosItens = requisitosContainer.querySelectorAll('.requisito-item');
            const todasCategorias = requisitosContainer.querySelectorAll('.categoria-group');

            todosItens.forEach(item => {
                const textoItem = item.dataset.texto || '';
                const visivel = termo === '' || textoItem.includes(termo);
                item.style.display = visivel ? '' : 'none';
                if (visivel) algumVisivel = true;
            });

            todasCategorias.forEach(cat => {
                const itensVisiveisNaCategoria = cat.querySelectorAll('.requisito-item[style*="display: none;"]');
                const totalItensNaCategoria = cat.querySelectorAll('.requisito-item');
                cat.style.display = (itensVisiveisNaCategoria.length === totalItensNaCategoria.length && termo !== '') ? 'none' : '';
                if (cat.style.display === '' && termo !== '') algumVisivel = true;
            });

            if (noResultsMessage) {
                noResultsMessage.style.display = algumVisivel ? 'none' : 'block';
            }
        });

        if (filtroInput.value) {
            filtroInput.dispatchEvent(new Event('input'));
        }
    }
});
</script>

<?php
echo getFooterGestor();
?>