<?php
// admin/plataforma_config_metodologia_risco.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Precisará de funções para salvar/carregar configs de risco

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Perfil admin da Acoditools
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Carregar Configurações de Risco Atuais (se existirem) ---
// Isso viria de uma tabela `plataforma_configuracoes_globais` ou uma tabela específica `plataforma_metodologias_risco_config`
// Por simplicidade, vamos assumir que existe uma função que busca um array de configurações
// ou um registro específico para a metodologia de risco.
// $configRiscoAtual = getConfigMetodologiaRiscoGlobal($conexao); // Função a ser criada
// Simulação:
$configRiscoAtual = [
    'tipo_calculo_risco' => 'Matricial', // 'Matricial', 'ProdutoSimples', 'PonderadoManualRequisito'
    'escala_impacto_labels' => ['Baixo', 'Médio', 'Alto', 'Crítico'], // JSON do DB
    'escala_impacto_valores' => [1, 2, 3, 4], // JSON do DB
    'escala_probabilidade_labels' => ['Rara', 'Improvável', 'Possível', 'Provável', 'Quase Certa'], // JSON do DB
    'escala_probabilidade_valores' => [1, 2, 3, 4, 5], // JSON do DB
    'matriz_risco_definicao' => [], // <<<< GARANTA QUE SEJA UM ARRAY AQUI
    'niveis_risco_resultado_labels' => ['Baixo', 'Médio', 'Alto'],
    'niveis_risco_cores_hex' => ['#28a745', '#ffc107', '#dc3545'],
    'niveis_risco_resultado_labels' => ['Muito Baixo', 'Baixo', 'Médio', 'Alto', 'Muito Alto', 'Extremo'], // JSON do DB
    'niveis_risco_cores_hex' => ['#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545', '#6f42c1'] // JSON do DB
    // Se tipo_calculo_risco = 'ProdutoSimples', a matriz não é usada, mas os níveis de resultado sim.
    // Se tipo_calculo_risco = 'PonderadoManualRequisito', usamos o campo 'peso' da tabela 'requisitos_auditoria'
    // e o admin define aqui quais faixas de peso correspondem a quais níveis de risco (ex: peso 1-3 = Baixo, 4-7 = Médio, etc.)
];


// --- Lógica de Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// --- Processamento do Formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $_SESSION['csrf_token'] = gerar_csrf_token(); // Regenera

        $novaConfigRisco = [
            'tipo_calculo_risco' => $_POST['tipo_calculo_risco'] ?? 'Matricial',
            // Coletar escalas de impacto (labels e valores)
            'escala_impacto_labels' => array_values(array_filter($_POST['impacto_label'] ?? [])),
            'escala_impacto_valores' => array_values(array_map('intval', array_filter($_POST['impacto_valor'] ?? [], 'is_numeric'))),
            // Coletar escalas de probabilidade (labels e valores)
            'escala_probabilidade_labels' => array_values(array_filter($_POST['probabilidade_label'] ?? [])),
            'escala_probabilidade_valores' => array_values(array_map('intval', array_filter($_POST['probabilidade_valor'] ?? [], 'is_numeric'))),
            // Coletar níveis de risco resultado (labels e cores)
            'niveis_risco_resultado_labels' => array_values(array_filter($_POST['nivel_risco_label'] ?? [])),
            'niveis_risco_cores_hex' => array_values(array_filter($_POST['nivel_risco_cor'] ?? [])),
            // Matriz de Risco (mais complexo, viria como um array multidimensional ou JSON string)
            'matriz_risco_definicao' => $_POST['matriz_risco'] ?? [] // Supondo que venha estruturado
        ];

        // Validações básicas
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
        // Validações mais profundas (ex: valores únicos, matriz completa se tipo matricial) seriam importantes no backend.

        if (empty($validation_errors)) {
            // *** Chamar função de backend: salvarConfigMetodologiaRiscoGlobal($conexao, $novaConfigRisco, $_SESSION['usuario_id']) ***
            if (salvarConfigMetodologiaRiscoGlobal($conexao, $novaConfigRisco, $_SESSION['usuario_id'])) { // Função a ser criada
                definir_flash_message('sucesso', 'Metodologia de risco global atualizada com sucesso.');
                // Recarregar a configuração para refletir na página
                // $configRiscoAtual = getConfigMetodologiaRiscoGlobal($conexao);
                 // Simulando recarga por agora:
                $configRiscoAtual = $novaConfigRisco; // Apenas para repopular o form com o que foi salvo
            } else {
                definir_flash_message('erro', 'Erro ao salvar a metodologia de risco.');
            }
        } else {
            definir_flash_message('erro', "<strong>Foram encontrados erros:</strong><ul><li>" . implode("</li><li>", $validation_errors) . "</li></ul>");
            // Repopular form com os dados enviados (simulando a persistência)
            $configRiscoAtual = $novaConfigRisco;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); // Recarrega para mostrar mensagens e limpar POST
    exit;
}

// CSRF token para o formulário
$csrf_token_page = $_SESSION['csrf_token'] ?? gerar_csrf_token();
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = $csrf_token_page; }

$title = "ACodITools - Configurar Metodologia de Risco Global";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-shield-alt me-2"></i>Configurar Metodologia de Risco Global</h1>
        <small class="text-muted">Defina os padrões da plataforma para avaliação de riscos.</small>
    </div>

    <?php if ($sucesso_msg): ?><div class="alert alert-success custom-alert fade show" role="alert"><?= $sucesso_msg ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger custom-alert fade show" role="alert"><?= $erro_msg ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="configRiscoForm" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">

        <div class="card shadow-sm mb-4 rounded-3 border-0">
            <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-calculator me-2 text-primary opacity-75"></i>Tipo de Cálculo de Risco</h6></div>
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

        <!-- Seções para Impacto, Probabilidade, Matriz e Níveis de Resultado apareceriam condicionalmente com JS -->
        <!-- Abaixo, um exemplo simples, a interface real precisaria de JS para adicionar/remover linhas dinamicamente -->

        <div id="config_impacto_probabilidade_wrapper" style="display: <?= (($configRiscoAtual['tipo_calculo_risco'] ?? '') === 'Matricial' || ($configRiscoAtual['tipo_calculo_risco'] ?? '') === 'ProdutoSimples') ? 'block' : 'none' ?>;">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4 rounded-3 border-0">
                        <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-bolt me-2 text-danger opacity-75"></i>Escala de Impacto</h6></div>
                        <div class="card-body p-3" id="impacto_escala_container">
                            <p class="small text-muted">Defina os níveis de impacto (ex: Baixo, Médio, Alto) e seus valores numéricos correspondentes.</p>
                            <?php
                            $impacto_labels = $configRiscoAtual['escala_impacto_labels'] ?? ['Baixo', 'Médio', 'Alto'];
                            $impacto_valores = $configRiscoAtual['escala_impacto_valores'] ?? [1, 2, 3];
                            foreach ($impacto_labels as $i => $label): ?>
                            <div class="row gx-2 mb-2 align-items-center">
                                <div class="col"><input type="text" name="impacto_label[]" class="form-control form-control-sm" value="<?= htmlspecialchars($label) ?>" placeholder="Label (Ex: Baixo)" required></div>
                                <div class="col-3"><input type="number" name="impacto_valor[]" class="form-control form-control-sm" value="<?= htmlspecialchars($impacto_valores[$i] ?? ($i+1)) ?>" placeholder="Valor" required min="1"></div>
                                <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-escala-item" title="Remover Nível">×</button></div>
                            </div>
                            <?php endforeach; ?>
                            <button type="button" class="btn btn-sm btn-outline-success mt-2" id="btnAddImpacto"><i class="fas fa-plus me-1"></i> Adicionar Nível de Impacto</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4 rounded-3 border-0">
                        <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-question-circle me-2 text-info opacity-75"></i>Escala de Probabilidade</h6></div>
                        <div class="card-body p-3" id="probabilidade_escala_container">
                             <p class="small text-muted">Defina os níveis de probabilidade (ex: Rara, Possível, Frequente) e seus valores.</p>
                             <?php
                            $prob_labels = $configRiscoAtual['escala_probabilidade_labels'] ?? ['Rara', 'Possível', 'Frequente'];
                            $prob_valores = $configRiscoAtual['escala_probabilidade_valores'] ?? [1, 2, 3];
                            foreach ($prob_labels as $i => $label): ?>
                            <div class="row gx-2 mb-2 align-items-center">
                                <div class="col"><input type="text" name="probabilidade_label[]" class="form-control form-control-sm" value="<?= htmlspecialchars($label) ?>" placeholder="Label (Ex: Rara)" required></div>
                                <div class="col-3"><input type="number" name="probabilidade_valor[]" class="form-control form-control-sm" value="<?= htmlspecialchars($prob_valores[$i] ?? ($i+1)) ?>" placeholder="Valor" required min="1"></div>
                                <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-escala-item" title="Remover Nível">×</button></div>
                            </div>
                            <?php endforeach; ?>
                            <button type="button" class="btn btn-sm btn-outline-success mt-2" id="btnAddProbabilidade"><i class="fas fa-plus me-1"></i> Adicionar Nível de Probabilidade</button>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- Fim wrapper impacto_probabilidade -->

        <div id="config_matriz_risco_wrapper" style="display: <?= (($configRiscoAtual['tipo_calculo_risco'] ?? '') === 'Matricial') ? 'block' : 'none' ?>;">
            <div class="card shadow-sm mb-4 rounded-3 border-0">
                <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-th me-2 text-primary opacity-75"></i>Definição da Matriz de Risco (Impacto x Probabilidade)</h6></div>
                <div class="card-body p-3">
                    <p class="small text-muted">Para cada combinação de Impacto e Probabilidade, selecione o Nível de Risco resultante. Os níveis devem ser definidos na seção "Níveis de Risco Resultantes" abaixo.</p>
                    <div class="table-responsive" id="matriz_risco_container">
                        <!-- A matriz será gerada por JavaScript com base nas escalas de impacto e probabilidade -->
                        <p class="text-center p-3"><i>Configure as escalas de Impacto e Probabilidade primeiro para gerar a matriz.</i></p>
                    </div>
                </div>
            </div>
        </div> <!-- Fim wrapper matriz_risco -->


        <div class="card shadow-sm mb-4 rounded-3 border-0">
            <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-tachometer-alt me-2 text-success opacity-75"></i>Níveis de Risco Resultantes e Cores</h6></div>
            <div class="card-body p-3" id="niveis_risco_resultado_container">
                 <p class="small text-muted">Defina os possíveis níveis de risco que podem surgir do cálculo (ex: Muito Baixo, Baixo, Médio, Alto, Extremo) e uma cor para cada um.</p>
                 <?php
                $niveis_labels = $configRiscoAtual['niveis_risco_resultado_labels'] ?? ['Baixo', 'Médio', 'Alto'];
                $niveis_cores = $configRiscoAtual['niveis_risco_cores_hex'] ?? ['#28a745', '#ffc107', '#dc3545'];
                foreach ($niveis_labels as $i => $label): ?>
                <div class="row gx-2 mb-2 align-items-center">
                    <div class="col"><input type="text" name="nivel_risco_label[]" class="form-control form-control-sm" value="<?= htmlspecialchars($label) ?>" placeholder="Label Nível (Ex: Baixo)" required></div>
                    <div class="col-3"><input type="color" name="nivel_risco_cor[]" class="form-control form-control-sm form-control-color" value="<?= htmlspecialchars($niveis_cores[$i] ?? '#000000') ?>" title="Escolha uma cor"></div>
                    <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-escala-item" title="Remover Nível">×</button></div>
                </div>
                <?php endforeach; ?>
                <button type="button" class="btn btn-sm btn-outline-success mt-2" id="btnAddNivelRisco"><i class="fas fa-plus me-1"></i> Adicionar Nível de Risco</button>
            </div>
        </div>

         <div id="config_ponderacao_manual_wrapper" style="display: <?= (($configRiscoAtual['tipo_calculo_risco'] ?? '') === 'PonderadoManualRequisito') ? 'block' : 'none' ?>;">
             <div class="card shadow-sm mb-4 rounded-3 border-0">
                <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-balance-scale me-2 text-primary opacity-75"></i>Mapeamento de Criticidade (Peso do Requisito) para Nível de Risco</h6></div>
                <div class="card-body p-3" id="mapeamento_peso_risco_container">
                    <p class="small text-muted">Se o cálculo for "Ponderado Manual", defina aqui as faixas de "Peso" (da tabela `requisitos_auditoria`) que correspondem a cada "Nível de Risco Resultante" definido acima.</p>
                    <!-- Exemplo: -->
                    <!-- Nível: Baixo => Peso de 1 até 3 -->
                    <!-- Nível: Médio => Peso de 4 até 7 -->
                    <!-- (JS dinâmico para criar esses mapeamentos) -->
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
        toggleConfigSections(); // Estado inicial
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
        // Insere antes do botão "Adicionar"
        const addButton = container.querySelector('button[id^="btnAdd"]');
        if(addButton) container.insertBefore(newRow, addButton); else container.appendChild(newRow);
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
        if(addButton) container.insertBefore(newRow, addButton); else container.appendChild(newRow);
    }


    document.getElementById('btnAddImpacto')?.addEventListener('click', () => addEscalaItem('impacto_escala_container', 'impacto'));
    document.getElementById('btnAddProbabilidade')?.addEventListener('click', () => addEscalaItem('probabilidade_escala_container', 'probabilidade'));
    document.getElementById('btnAddNivelRisco')?.addEventListener('click', () => addNivelRiscoItem('niveis_risco_resultado_container'));

    // Delegação de evento para botões de remover
    document.addEventListener('click', function(event) {
        if (event.target && event.target.classList.contains('btn-remove-escala-item')) {
            event.target.closest('.dynamic-item-row, .row.gx-2.mb-2.align-items-center')?.remove();
            if (tipoCalculoSelect.value === 'Matricial') gerarMatrizRiscoUI(); // Regenera matriz se remover impacto/prob
        }
    });

    // Lógica para gerar a UI da Matriz de Risco (simplificada)
    // Esta função deve ser chamada quando as escalas de impacto/probabilidade mudam OU o tipo de cálculo é Matricial
    const impactoContainer = document.getElementById('impacto_escala_container');
    const probContainer = document.getElementById('probabilidade_escala_container');

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

        // Recuperar valores da matriz já salvos (do PHP -> JS, se houver)
        const matrizSalva = <?= json_encode($configRiscoAtual['matriz_risco_definicao'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

        let tableHtml = '<table class="table table-bordered table-sm text-center small align-middle">';
        // Cabeçalho da Probabilidade
        tableHtml += '<thead><tr><th class="bg-light" style="width: 15%;">Impacto <i class="fas fa-arrow-down"></i> / Prob. <i class="fas fa-arrow-right"></i></th>';
        probLabels.forEach(pLabel => {
            tableHtml += `<th class="bg-light">${htmlspecialchars(pLabel)}</th>`;
        });
        tableHtml += '</tr></thead><tbody>';

        // Linhas de Impacto
        impactoLabels.forEach(iLabel => {
            tableHtml += `<tr><th class="bg-light text-end pe-2">${htmlspecialchars(iLabel)}</th>`;
            probLabels.forEach(pLabel => {
                const chaveMatriz = `${iLabel}_${pLabel}`; // Chave para POST e para matrizSalva
                const valorSalvo = matrizSalva[chaveMatriz] || '';
                tableHtml += '<td><select name="matriz_risco[' + htmlspecialchars(chaveMatriz) + ']" class="form-select form-select-sm">';
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
    // Chama para gerar no load se for matricial
    if (impactoContainer && probContainer) {
         Array.from(impactoContainer.querySelectorAll('input')).forEach(el => el.addEventListener('input', gerarMatrizRiscoUI));
         Array.from(probContainer.querySelectorAll('input')).forEach(el => el.addEventListener('input', gerarMatrizRiscoUI));
    }
    const niveisRiscoInputs = document.querySelectorAll('#niveis_risco_resultado_container input');
    niveisRiscoInputs.forEach(el => el.addEventListener('input', gerarMatrizRiscoUI));


    // Helper JS para htmlspecialchars (simplificado)
    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, function (match) {
            const map = { '&': '&', '<': '<', '>': '>', '"': '"', "'": ''' };
            return map[match];
        });
    }

    // Validação Bootstrap
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

    // Dispara a geração da matriz e toggle de seções no carregamento da página se o tipo já estiver selecionado
    if (tipoCalculoSelect && tipoCalculoSelect.value) {
        toggleConfigSections();
        if (tipoCalculoSelect.value === 'Matricial') {
             // Força a regeração da UI da matriz para popular com valores do PHP.
             // Pequeno timeout para garantir que os inputs das escalas foram renderizados pelo PHP.
             setTimeout(gerarMatrizRiscoUI, 100);
        }
    }
});
</script>

<?php
echo getFooterAdmin();
?>