<?php
// admin/plataforma_config_metodologia_risco.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php';

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// Verificar se o usuário está autenticado
if (!isset($_SESSION['usuario_id']) || !is_int($_SESSION['usuario_id'])) {
    definir_flash_message('erro', 'Erro: ID do administrador não está definido.');
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// Carregar Configurações de Risco Atuais
$configRiscoAtual = getConfigMetodologiaRiscoGlobal($conexao);

// Lógica de Mensagens Flash
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// Processamento do Formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $_SESSION['csrf_token'] = gerar_csrf_token();

        $novaConfigRisco = [
            'tipo_calculo_risco' => $_POST['tipo_calculo_risco'] ?? 'Matricial',
            'escala_impacto_labels' => array_values(array_filter($_POST['impacto_label'] ?? [], 'is_string')),
            'escala_impacto_valores' => array_values(array_map('intval', array_filter($_POST['impacto_valor'] ?? [], 'is_numeric'))),
            'escala_probabilidade_labels' => array_values(array_filter($_POST['probabilidade_label'] ?? [], 'is_string')),
            'escala_probabilidade_valores' => array_values(array_map('intval', array_filter($_POST['probabilidade_valor'] ?? [], 'is_numeric'))),
            'niveis_risco_resultado_labels' => array_values(array_filter($_POST['nivel_risco_label'] ?? [], 'is_string')),
            'niveis_risco_cores_hex' => array_values(array_filter($_POST['nivel_risco_cor'] ?? [], function($cor) {
                return preg_match('/^#[0-9A-Fa-f]{6}$/', $cor);
            })),
            'matriz_risco_definicao' => $_POST['matriz_risco'] ?? []
        ];

        // Validações
        $validation_errors = [];
        if (count($novaConfigRisco['escala_impacto_labels']) !== count($novaConfigRisco['escala_impacto_valores'])) {
            $validation_errors[] = "Número de labels e valores para Impacto não coincide.";
        }
        if (count($novaConfigRisco['escala_probabilidade_labels']) !== count($novaConfigRisco['escala_probabilidade_valores'])) {
            $validation_errors[] = "Número de labels e valores para Probabilidade não coincide.";
        }
        if (count($novaConfigRisco['niveis_risco_resultado_labels']) !== count($novaConfigRisco['niveis_risco_cores_hex'])) {
            $validation_errors[] = "Número de labels e cores para Níveis de Risco não coincide.";
        }
        if ($novaConfigRisco['tipo_calculo_risco'] === 'Matricial') {
            $expectedKeys = [];
            foreach ($novaConfigRisco['escala_impacto_labels'] as $impacto) {
                foreach ($novaConfigRisco['escala_probabilidade_labels'] as $prob) {
                    $expectedKeys[] = "{$impacto}_{$prob}";
                }
            }
            $missingKeys = array_diff($expectedKeys, array_keys($novaConfigRisco['matriz_risco_definicao']));
            if (!empty($missingKeys)) {
                $validation_errors[] = "A matriz de risco está incompleta. Preencha todas as combinações de Impacto e Probabilidade.";
            }
            // Validar se os valores da matriz estão nos níveis de risco definidos
            foreach ($novaConfigRisco['matriz_risco_definicao'] as $valor) {
                if (!in_array($valor, $novaConfigRisco['niveis_risco_resultado_labels'])) {
                    $validation_errors[] = "Valores na matriz de risco devem corresponder aos níveis de risco definidos.";
                    break;
                }
            }
        }

        if (empty($validation_errors)) {
            if (salvarConfigMetodologiaRiscoGlobal($conexao, $novaConfigRisco, $_SESSION['usuario_id'])) {
                definir_flash_message('sucesso', 'Metodologia de risco global atualizada com sucesso.');
                $configRiscoAtual = getConfigMetodologiaRiscoGlobal($conexao);
            } else {
                definir_flash_message('erro', 'Erro ao salvar a metodologia de risco.');
            }
        } else {
            definir_flash_message('erro', "<strong>Foram encontrados erros:</strong><ul><li>" . implode("</li><li>", $validation_errors) . "</li></ul>");
            $configRiscoAtual = $novaConfigRisco;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// CSRF token para o formulário
$csrf_token_page = $_SESSION['csrf_token'] ?? gerar_csrf_token();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = $csrf_token_page;
}

$title = "ACodITools - Configurar Metodologia de Risco Global";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-shield-alt me-2"></i>Configurar Metodologia de Risco Global</h1>
        <small class="text-muted">Defina os padrões da plataforma para avaliação de riscos.</small>
    </div>

    <?php if ($sucesso_msg): ?>
        <div class="alert alert-success custom-alert fade show" role="alert">
            <?= $sucesso_msg ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($erro_msg): ?>
        <div class="alert alert-danger custom-alert fade show" role="alert">
            <?= $erro_msg ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="configRiscoForm" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">

        <div class="card shadow-sm mb-4 rounded-3 border-0">
            <div class="card-header bg-light border-bottom pt-3 pb-2">
                <h6 class="mb-0 fw-bold"><i class="fas fa-calculator me-2 text-primary opacity-75"></i>Tipo de Cálculo de Risco</h6>
            </div>
            <div class="card-body p-3">
                <div class="mb-3">
                    <label for="tipo_calculo_risco" class="form-label form-label-sm fw-semibold">Método Principal de Cálculo de Risco na Plataforma:</label>
                    <select class="form-select form-select-sm" id="tipo_calculo_risco" name="tipo_calculo_risco">
                        <option value="Matricial" <?= ($configRiscoAtual['tipo_calculo_risco'] ?? '') === 'Matricial' ? 'selected' : '' ?>>Matriz de Risco (Impacto x Probabilidade)</option>
                        <option value="ProdutoSimples" <?= ($configRiscoAtual['tipo_calculo_risco'] ?? '') === 'ProdutoSimples' ? 'selected' : '' ?>>Produto Simples (Impacto * Probabilidade)</option>
                        <option value="PonderadoManualRequisito" <?= ($configRiscoAtual['tipo_calculo_risco'] ?? '') === 'PonderadoManualRequisito' ? 'selected' : '' ?>>Ponderação Manual por Criticidade do Requisito</option>
                    </select>
                    <small class="form-text text-muted">Define como o nível de risco será determinado para requisitos e auditorias.</small>
                </div>
            </div>
        </div>

        <div id="config_impacto_probabilidade_wrapper" style="display: <?= ($configRiscoAtual['tipo_calculo_risco'] ?? '') === 'Matricial' || ($configRiscoAtual['tipo_calculo_risco'] ?? '') === 'ProdutoSimples' ? 'block' : 'none' ?>;">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4 rounded-3 border-0">
                        <div class="card-header bg-light border-bottom pt-3 pb-2">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-bolt me-2 text-danger opacity-75"></i>Escala de Impacto</h6>
                        </div>
                        <div class="card-body p-3" id="impacto_escala_container">
                            <p class="small text-muted">Defina os níveis de impacto (ex: Baixo, Médio, Alto) e seus valores numéricos correspondentes.</p>
                            <?php
                            $impacto_labels = $configRiscoAtual['escala_impacto_labels'] ?? [];
                            $impacto_valores = $configRiscoAtual['escala_impacto_valores'] ?? [];
                            if (empty($impacto_labels)) {
                                $impacto_labels = ['Baixo', 'Médio', 'Alto'];
                                $impacto_valores = [1, 2, 3];
                            }
                            foreach ($impacto_labels as $i => $label):
                            ?>
                            <div class="row gx-2 mb-2 align-items-center dynamic-item-row">
                                <div class="col">
                                    <input type="text" name="impacto_label[]" class="form-control form-control-sm" value="<?= htmlspecialchars($label) ?>" placeholder="Label (Ex: Baixo)" required>
                                </div>
                                <div class="col-3">
                                    <input type="number" name="impacto_valor[]" class="form-control form-control-sm" value="<?= htmlspecialchars($impacto_valores[$i] ?? ($i+1)) ?>" placeholder="Valor" required min="1">
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-escala-item" title="Remover Nível">×</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <button type="button" class="btn btn-sm btn-outline-success mt-2" id="btnAddImpacto"><i class="fas fa-plus me-1"></i> Adicionar Nível de Impacto</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4 rounded-3 border-0">
                        <div class="card-header bg-light border-bottom pt-3 pb-2">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-question-circle me-2 text-info opacity-75"></i>Escala de Probabilidade</h6>
                        </div>
                        <div class="card-body p-3" id="probabilidade_escala_container">
                            <p class="small text-muted">Defina os níveis de probabilidade (ex: Rara, Possível, Frequente) e seus valores.</p>
                            <?php
                            $prob_labels = $configRiscoAtual['escala_probabilidade_labels'] ?? [];
                            $prob_valores = $configRiscoAtual['escala_probabilidade_valores'] ?? [];
                            if (empty($prob_labels)) {
                                $prob_labels = ['Rara', 'Possível', 'Frequente'];
                                $prob_valores = [1, 2, 3];
                            }
                            foreach ($prob_labels as $i => $label):
                            ?>
                            <div class="row gx-2 mb-2 align-items-center dynamic-item-row">
                                <div class="col">
                                    <input type="text" name="probabilidade_label[]" class="form-control form-control-sm" value="<?= htmlspecialchars($label) ?>" placeholder="Label (Ex: Rara)" required>
                                </div>
                                <div class="col-3">
                                    <input type="number" name="probabilidade_valor[]" class="form-control form-control-sm" value="<?= htmlspecialchars($prob_valores[$i] ?? ($i+1)) ?>" placeholder="Valor" required min="1">
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-escala-item" title="Remover Nível">×</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <button type="button" class="btn btn-sm btn-outline-success mt-2" id="btnAddProbabilidade"><i class="fas fa-plus me-1"></i> Adicionar Nível de Probabilidade</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="config_matriz_risco_wrapper" style="display: <?= ($configRiscoAtual['tipo_calculo_risco'] ?? '') === 'Matricial' ? 'block' : 'none' ?>;">
            <div class="card shadow-sm mb-4 rounded-3 border-0">
                <div class="card-header bg-light border-bottom pt-3 pb-2">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-th me-2 text-primary opacity-75"></i>Definição da Matriz de Risco (Impacto x Probabilidade)</h6>
                </div>
                <div class="card-body p-3">
                    <p class="small text-muted">Para cada combinação de Impacto e Probabilidade, selecione o Nível de Risco resultante. Os níveis devem ser definidos na seção "Níveis de Risco Resultantes" abaixo.</p>
                    <div class="table-responsive" id="matriz_risco_container">
                        <p class="text-center p-3"><i>Configure as escalas de Impacto e Probabilidade primeiro para gerar a matriz.</i></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 rounded-3 border-0">
            <div class="card-header bg-light border-bottom pt-3 pb-2">
                <h6 class="mb-0 fw-bold"><i class="fas fa-tachometer-alt me-2 text-success opacity-75"></i>Níveis de Risco Resultantes e Cores</h6>
            </div>
            <div class="card-body p-3" id="niveis_risco_resultado_container">
                <p class="small text-muted">Defina os possíveis níveis de risco que podem surgir do cálculo (ex: Muito Baixo, Baixo, Médio, Alto, Extremo) e uma cor para cada um.</p>
                <?php
                $niveis_labels = $configRiscoAtual['niveis_risco_resultado_labels'] ?? [];
                $niveis_cores = $configRiscoAtual['niveis_risco_cores_hex'] ?? [];
                if (empty($niveis_labels)) {
                    $niveis_labels = ['Baixo', 'Médio', 'Alto'];
                    $niveis_cores = ['#28a745', '#ffc107', '#dc3545'];
                }
                foreach ($niveis_labels as $i => $label):
                ?>
                <div class="row gx-2 mb-2 align-items-center dynamic-item-row">
                    <div class="col">
                        <input type="text" name="nivel_risco_label[]" class="form-control form-control-sm" value="<?= htmlspecialchars($label) ?>" placeholder="Label Nível (Ex: Baixo)" required>
                    </div>
                    <div class="col-3">
                        <input type="color" name="nivel_risco_cor[]" class="form-control form-control-sm form-control-color" value="<?= htmlspecialchars($niveis_cores[$i] ?? '#000000') ?>" title="Escolha uma cor">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-escala-item" title="Remover Nível">×</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="button" class="btn btn-sm btn-outline-success mt-2" id="btnAddNivelRisco"><i class="fas fa-plus me-1"></i> Adicionar Nível de Risco</button>
            </div>
        </div>

        <div id="config_ponderacao_manual_wrapper" style="display: <?= ($configRiscoAtual['tipo_calculo_risco'] ?? '') === 'PonderadoManualRequisito' ? 'block' : 'none' ?>;">
            <div class="card shadow-sm mb-4 rounded-3 border-0">
                <div class="card-header bg-light border-bottom pt-3 pb-2">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-balance-scale me-2 text-primary opacity-75"></i>Mapeamento de Criticidade (Peso do Requisito) para Nível de Risco</h6>
                </div>
                <div class="card-body p-3" id="mapeamento_peso_risco_container">
                    <p class="small text-muted">Se o cálculo for "Ponderado Manual", defina aqui as faixas de "Peso" (da tabela `requisitos_auditoria`) que correspondem a cada "Nível de Risco Resultante" definido acima.</p>
                    <p class="text-center p-3"><i>Configure os Níveis de Risco Resultantes primeiro. Depois, mapeie as faixas de Peso (do cadastro de requisitos) para cada nível aqui.</i></p>
                </div>
            </div>
        </div>

        <div class="mt-4 mb-5 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary rounded-pill px-4 action-button-main">
                <i class="fas fa-save me-1"></i> Salvar Configurações de Risco
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoCalculoSelect = document.getElementById('tipo_calculo_risco');
    const impactoProbWrapper = document.getElementById('config_impacto_probabilidade_wrapper');
    const matrizWrapper = document.getElementById('config_matriz_risco_wrapper');
    const ponderacaoWrapper = document.getElementById('config_ponderacao_manual_wrapper');
    const impactoContainer = document.getElementById('impacto_escala_container');
    const probContainer = document.getElementById('probabilidade_escala_container');

    function toggleConfigSections() {
        const tipoSelecionado = tipoCalculoSelect.value;
        impactoProbWrapper.style.display = (tipoSelecionado === 'Matricial' || tipoSelecionado === 'ProdutoSimples') ? 'block' : 'none';
        matrizWrapper.style.display = (tipoSelecionado === 'Matricial') ? 'block' : 'none';
        ponderacaoWrapper.style.display = (tipoSelecionado === 'PonderadoManualRequisito') ? 'block' : 'none';
        if (tipoSelecionado === 'Matricial') {
            gerarMatrizRiscoUI();
        }
    }

    if (tipoCalculoSelect) {
        tipoCalculoSelect.addEventListener('change', toggleConfigSections);
        toggleConfigSections();
    }

    function addEscalaItem(containerId, namePrefix) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const newRow = document.createElement('div');
        newRow.className = 'row gx-2 mb-2 align-items-center dynamic-item-row';
        newRow.innerHTML = `
            <div class="col"><input type="text" name="${namePrefix}_label[]" class="form-control form-control-sm" placeholder="Label" required></div>
            <div class="col-3"><input type="number" name="${namePrefix}_valor[]" class="form-control form-control-sm" placeholder="Valor" required min="1"></div>
            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-escala-item" title="Remover Nível">×</button></div>
        `;
        const addButton = container.querySelector('button[id^="btnAdd"]');
        if (addButton) {
            container.insertBefore(newRow, addButton);
        } else {
            container.appendChild(newRow);
        }
        // Regenerar matriz se for Matricial
        if (tipoCalculoSelect.value === 'Matricial') {
            gerarMatrizRiscoUI();
        }
    }

    function addNivelRiscoItem(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const newRow = document.createElement('div');
        newRow.className = 'row gx-2 mb-2 align-items-center dynamic-item-row';
        newRow.innerHTML = `
            <div class="col"><input type="text" name="nivel_risco_label[]" class="form-control form-control-sm" placeholder="Label Nível Risco (Ex: Crítico)" required></div>
            <div class="col-3"><input type="color" name="nivel_risco_cor[]" class="form-control form-control-sm form-control-color" value="#000000" title="Escolha uma cor"></div>
            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-escala-item" title="Remover Nível">×</button></div>
        `;
        const addButton = container.querySelector('button[id^="btnAdd"]');
        if (addButton) {
            container.insertBefore(newRow, addButton);
        } else {
            container.appendChild(newRow);
        }
        // Regenerar matriz se for Matricial
        if (tipoCalculoSelect.value === 'Matricial') {
            gerarMatrizRiscoUI();
        }
    }

    document.getElementById('btnAddImpacto')?.addEventListener('click', () => addEscalaItem('impacto_escala_container', 'impacto'));
    document.getElementById('btnAddProbabilidade')?.addEventListener('click', () => addEscalaItem('probabilidade_escala_container', 'probabilidade'));
    document.getElementById('btnAddNivelRisco')?.addEventListener('click', () => addNivelRiscoItem('niveis_risco_resultado_container'));

    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('btn-remove-escala-item')) {
            const row = event.target.closest('.dynamic-item-row');
            if (row) {
                row.remove();
                if (tipoCalculoSelect.value === 'Matricial') {
                    gerarMatrizRiscoUI();
                }
            }
        }
    });

    function gerarMatrizRiscoUI() {
        const matrizContainer = document.getElementById('matriz_risco_container');
        if (!matrizContainer || !impactoContainer || !probContainer) return;

        const impactoLabels = Array.from(impactoContainer.querySelectorAll('input[name="impacto_label[]"]')).map(input => input.value.trim()).filter(Boolean);
        const probLabels = Array.from(probContainer.querySelectorAll('input[name="probabilidade_label[]"]')).map(input => input.value.trim()).filter(Boolean);
        const niveisRiscoResultantes = Array.from(document.querySelectorAll('#niveis_risco_resultado_container input[name="nivel_risco_label[]"]')).map(input => input.value.trim()).filter(Boolean);

        if (impactoLabels.length === 0 || probLabels.length === 0 || niveisRiscoResultantes.length === 0) {
            matrizContainer.innerHTML = '<p class="text-center p-3 text-muted small"><i>Configure as escalas de Impacto, Probabilidade e os Níveis de Risco Resultantes primeiro.</i></p>';
            return;
        }

        const matrizSalva = <?= json_encode($configRiscoAtual['matriz_risco_definicao'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

        let tableHtml = '<table class="table table-bordered table-sm text-center small align-middle">';
        tableHtml += '<thead><tr><th class="bg-light" style="width: 15%;">Impacto <i class="fas fa-arrow-down"></i> / Prob. <i class="fas fa-arrow-right"></i></th>';
        probLabels.forEach(pLabel => {
            tableHtml += `<th class="bg-light">${htmlspecialchars(pLabel)}</th>`;
        });
        tableHtml += '</tr></thead><tbody>';

        impactoLabels.forEach(iLabel => {
            tableHtml += `<tr><th class="bg-light text-end pe-2">${htmlspecialchars(iLabel)}</th>`;
            probLabels.forEach(pLabel => {
                const chaveMatriz = `${iLabel}_${pLabel}`;
                const valorSalvo = matrizSalva[chaveMatriz] || '';
                tableHtml += '<td><select name="matriz_risco[' + htmlspecialchars(chaveMatriz) + ']" class="form-select form-select-sm" required>';
                tableHtml += '<option value="">-- Selecione --</option>';
                niveisRiscoResultantes.forEach(nivelR => {
                    const selected = (valorSalvo === nivelR) ? 'selected' : '';
                    tableHtml += `<option value="${htmlspecialchars(nivelR)}" ${selected}>${htmlspecialchars(nivelR)}</option>`;
                });
                tableHtml += '</select></td>';
            });
            tableHtml += '</tr>';
        });
        tableHtml += '</tbody></table>';
        matrizContainer.innerHTML = tableHtml;
    }

    if (impactoContainer && probContainer) {
        Array.from(impactoContainer.querySelectorAll('input')).forEach(el => el.addEventListener('input', () => setTimeout(gerarMatrizRiscoUI, 50)));
        Array.from(probContainer.querySelectorAll('input')).forEach(el => el.addEventListener('input', () => setTimeout(gerarMatrizRiscoUI, 50)));
    }
    const niveisRiscoInputs = document.querySelectorAll('#niveis_risco_resultado_container input');
    niveisRiscoInputs.forEach(el => el.addEventListener('input', () => setTimeout(gerarMatrizRiscoUI, 50)));

    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, function(match) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return map[match];
        });
    }

    const form = document.getElementById('configRiscoForm');
    if (form) {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }

    if (tipoCalculoSelect && tipoCalculoSelect.value) {
        toggleConfigSections();
        if (tipoCalculoSelect.value === 'Matricial') {
            setTimeout(gerarMatrizRiscoUI, 100);
        }
    }
});
</script>

<?php
echo getFooterAdmin();
?>