<?php
// admin/requisitos/editar_requisito.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_admin.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Apenas Admin da Plataforma AcodITools
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// Obter ID do Requisito a Editar
$requisito_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$requisito_id) {
    definir_flash_message('erro', 'ID de requisito inválido ou não fornecido.');
    header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php');
    exit;
}

// Carregar Dados do Requisito (getRequisitoAuditoria já busca todos os campos com SELECT *)
$requisito_db = getRequisitoAuditoria($conexao, $requisito_id);
if (!$requisito_db) {
    definir_flash_message('erro', "Requisito com ID $requisito_id não encontrado.");
    header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php');
    exit;
}

// Para repopular o formulário em caso de erro ou na primeira carga
$requisito_form_data = $requisito_db;
// Decodificar o JSON de planos para o formulário
$requisito_form_data['disponibilidade_plano_ids_array'] = json_decode($requisito_db['disponibilidade_plano_ids_json'] ?? '[]', true);
if (!is_array($requisito_form_data['disponibilidade_plano_ids_array'])) { // Garantir que é um array
    $requisito_form_data['disponibilidade_plano_ids_array'] = [];
}


// Buscar planos de assinatura ativos para o select
$planos_assinatura_disponiveis = listarPlanosAssinatura($conexao, true); // true para apenas ativos

$erro_msg_local = ''; // Para erros de validação específicos desta página

// Processar o formulário de Edição (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_post = filter_input(INPUT_POST, 'requisito_id', FILTER_VALIDATE_INT);
    if (!validar_csrf_token($_POST['csrf_token'] ?? null) || $id_post !== $requisito_id) {
        definir_flash_message('erro', "Erro de validação da sessão ou ID inconsistente.");
        header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php');
        exit;
    }
    // $_SESSION['csrf_token'] = gerar_csrf_token(); // Será regenerado no próximo GET

    // Coletar dados do formulário
    $requisito_form_data['codigo'] = trim($_POST['codigo'] ?? '');
    $requisito_form_data['nome'] = trim($_POST['nome'] ?? '');
    $requisito_form_data['descricao'] = trim($_POST['descricao'] ?? '');
    $requisito_form_data['categoria'] = trim($_POST['categoria'] ?? '');
    $requisito_form_data['norma_referencia'] = trim($_POST['norma_referencia'] ?? '');
    $requisito_form_data['versao_norma_aplicavel'] = trim($_POST['versao_norma_aplicavel'] ?? '');
    $requisito_form_data['data_ultima_revisao_norma'] = !empty($_POST['data_ultima_revisao_norma']) ? trim($_POST['data_ultima_revisao_norma']) : null;
    $requisito_form_data['guia_evidencia'] = trim($_POST['guia_evidencia'] ?? '');
    $requisito_form_data['objetivo_controle'] = trim($_POST['objetivo_controle'] ?? '');
    $requisito_form_data['tecnicas_sugeridas'] = trim($_POST['tecnicas_sugeridas'] ?? '');
    $requisito_form_data['peso'] = filter_input(INPUT_POST, 'peso', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 0]]);
    $requisito_form_data['ativo'] = isset($_POST['ativo']) ? 1 : 0;
    // global_ou_empresa_id para requisitos globais sempre será NULL ao salvar por aqui.
    $requisito_form_data['global_ou_empresa_id'] = null;
    // Processar planos selecionados
    $planos_selecionados = $_POST['disponibilidade_planos_ids'] ?? [];
    $requisito_form_data['disponibilidade_plano_ids_json'] = !empty($planos_selecionados) ? json_encode(array_map('intval', $planos_selecionados)) : null;
    $requisito_form_data['disponibilidade_plano_ids_array'] = $planos_selecionados; // Para repopular o form

    // Validações (aqui você pode adicionar mais se necessário)
    $validation_errors = [];
    if (empty($requisito_form_data['nome'])) $validation_errors[] = "O Nome/Título do requisito é obrigatório.";
    if (empty($requisito_form_data['descricao'])) $validation_errors[] = "A Descrição Detalhada do requisito é obrigatória.";
    // Validação para data, se preenchida
    if ($requisito_form_data['data_ultima_revisao_norma'] !== null) {
        $d = DateTime::createFromFormat('Y-m-d', $requisito_form_data['data_ultima_revisao_norma']);
        if (!$d || $d->format('Y-m-d') !== $requisito_form_data['data_ultima_revisao_norma']) {
            $validation_errors[] = "Data da Última Revisão da Norma inválida.";
        }
    }


    if (empty($validation_errors)) {
        // Chamar função de atualização (que precisa ser adaptada para os novos campos)
        $resultado_update = atualizarRequisitoAuditoria($conexao, $requisito_id, $requisito_form_data, $_SESSION['usuario_id']);

        if ($resultado_update === true) {
            definir_flash_message('sucesso', "Requisito '".htmlspecialchars($requisito_form_data['nome'])."' (ID: $requisito_id) atualizado com sucesso!");
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_requisito_sucesso', 1, "ID do Requisito: $requisito_id", $conexao);
            header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php');
            exit;
        } else {
            $erro_msg_local = is_string($resultado_update) ? $resultado_update : "Erro ao salvar o requisito no banco de dados.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_requisito_falha_db', 0, "ID Requisito: $requisito_id, Erro: $erro_msg_local", $conexao);
        }
    } else {
        $erro_msg_local = "<strong>Foram encontrados erros:</strong><ul><li>" . implode("</li><li>", $validation_errors) . "</li></ul>";
    }
}

// Gerar novo token CSRF para o formulário ser exibido no GET ou após erro no POST
$_SESSION['csrf_token'] = gerar_csrf_token();
$csrf_token_page = $_SESSION['csrf_token'];

// Obter listas para dropdowns/datalists
$categorias_existentes = getCategoriasRequisitos($conexao);
$normas_existentes = getNormasRequisitos($conexao);

$title = "Editar Requisito Global: " . htmlspecialchars($requisito_form_data['nome']);
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-edit me-2"></i>Editar Requisito Global</h1>
        <a href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar para Lista de Requisitos
        </a>
    </div>

    <?php if (!empty($erro_msg_local)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $erro_msg_local /* Permite HTML dos erros de validação */ ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" id="editRequisitoForm" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
        <input type="hidden" name="requisito_id" value="<?= htmlspecialchars($requisito_id) ?>">

        <div class="card shadow-sm rounded-3 border-0">
            <div class="card-header bg-light border-bottom pt-3 pb-2">
                <h6 class="mb-0 fw-bold"><i class="fas fa-pencil-alt me-2 text-primary opacity-75"></i>Editando Requisito ID: <?= htmlspecialchars($requisito_id) ?></h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="codigo" class="form-label form-label-sm fw-semibold">Código</label>
                        <input type="text" class="form-control form-control-sm" id="codigo" name="codigo" value="<?= htmlspecialchars($requisito_form_data['codigo'] ?? '') ?>" maxlength="50">
                        <small class="form-text text-muted">Opcional. Ex: A.5.1</small>
                    </div>
                    <div class="col-md-9">
                        <label for="nome" class="form-label form-label-sm fw-semibold">Nome/Título Curto <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="nome" name="nome" required maxlength="255" value="<?= htmlspecialchars($requisito_form_data['nome'] ?? '') ?>">
                        <div class="invalid-feedback">O nome/título é obrigatório.</div>
                    </div>
                    <div class="col-12">
                        <label for="descricao" class="form-label form-label-sm fw-semibold">Descrição Detalhada / Pergunta <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="descricao" name="descricao" rows="3" required><?= htmlspecialchars($requisito_form_data['descricao'] ?? '') ?></textarea>
                         <div class="invalid-feedback">A descrição detalhada é obrigatória.</div>
                    </div>

                    <div class="col-md-6">
                         <label for="categoria" class="form-label form-label-sm fw-semibold">Categoria</label>
                         <input type="text" class="form-control form-control-sm" id="categoria" name="categoria" value="<?= htmlspecialchars($requisito_form_data['categoria'] ?? '') ?>" maxlength="100" list="categoriasExistentesList">
                         <datalist id="categoriasExistentesList">
                             <?php foreach ($categorias_existentes as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?>
                         </datalist>
                    </div>
                    <div class="col-md-6">
                         <label for="norma_referencia" class="form-label form-label-sm fw-semibold">Norma de Referência</label>
                         <input type="text" class="form-control form-control-sm" id="norma_referencia" name="norma_referencia" value="<?= htmlspecialchars($requisito_form_data['norma_referencia'] ?? '') ?>" maxlength="100" list="normasExistentesList">
                           <datalist id="normasExistentesList">
                             <?php foreach ($normas_existentes as $norma): ?><option value="<?= htmlspecialchars($norma) ?>"><?php endforeach; ?>
                         </datalist>
                    </div>
                    <div class="col-md-4">
                        <label for="versao_norma_aplicavel" class="form-label form-label-sm fw-semibold">Versão da Norma</label>
                        <input type="text" class="form-control form-control-sm" id="versao_norma_aplicavel" name="versao_norma_aplicavel" value="<?= htmlspecialchars($requisito_form_data['versao_norma_aplicavel'] ?? '') ?>" maxlength="50">
                    </div>
                    <div class="col-md-4">
                        <label for="data_ultima_revisao_norma" class="form-label form-label-sm fw-semibold">Última Revisão da Norma</label>
                        <input type="date" class="form-control form-control-sm" id="data_ultima_revisao_norma" name="data_ultima_revisao_norma" value="<?= htmlspecialchars($requisito_form_data['data_ultima_revisao_norma'] ?? '') ?>">
                         <div class="invalid-feedback">Data inválida.</div>
                    </div>
                     <div class="col-md-4">
                        <label for="peso" class="form-label form-label-sm fw-semibold">Peso/Impacto</label>
                        <input type="number" class="form-control form-control-sm" id="peso" name="peso" value="<?= htmlspecialchars((int)($requisito_form_data['peso'] ?? 1)) ?>" min="0" step="1" required>
                        <small class="form-text text-muted">Valor numérico para criticidade.</small>
                        <div class="invalid-feedback">Peso/Impacto deve ser um número não negativo.</div>
                    </div>


                    <div class="col-12">
                        <label for="guia_evidencia" class="form-label form-label-sm fw-semibold">Guia de Evidência</label>
                        <textarea class="form-control form-control-sm" id="guia_evidencia" name="guia_evidencia" rows="2" placeholder="Orientações sobre que tipo de evidência coletar..."><?= htmlspecialchars($requisito_form_data['guia_evidencia'] ?? '') ?></textarea>
                    </div>
                     <div class="col-12">
                        <label for="objetivo_controle" class="form-label form-label-sm fw-semibold">Objetivo do Controle</label>
                        <textarea class="form-control form-control-sm" id="objetivo_controle" name="objetivo_controle" rows="2" placeholder="Qual o objetivo principal deste requisito/controle..."><?= htmlspecialchars($requisito_form_data['objetivo_controle'] ?? '') ?></textarea>
                    </div>
                     <div class="col-12">
                        <label for="tecnicas_sugeridas" class="form-label form-label-sm fw-semibold">Técnicas de Auditoria Sugeridas</label>
                        <textarea class="form-control form-control-sm" id="tecnicas_sugeridas" name="tecnicas_sugeridas" rows="2" placeholder="Ex: Entrevista, Observação, Análise de Logs..."><?= htmlspecialchars($requisito_form_data['tecnicas_sugeridas'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label form-label-sm fw-semibold">Disponibilidade para Planos de Assinatura</label>
                        <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y:auto;">
                            <?php if(empty($planos_assinatura_disponiveis)): ?>
                                <small class="text-muted">Nenhum plano de assinatura cadastrado ou ativo.</small>
                            <?php else:
                                foreach($planos_assinatura_disponiveis as $plano_opt_item): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="disponibilidade_planos_ids[]" value="<?= $plano_opt_item['id'] ?>" id="plano_req_<?= $plano_opt_item['id'] ?>"
                                        <?= in_array($plano_opt_item['id'], $requisito_form_data['disponibilidade_plano_ids_array'] ?? []) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="plano_req_<?= $plano_opt_item['id'] ?>">
                                        <?= htmlspecialchars($plano_opt_item['nome_plano']) ?>
                                    </label>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <small class="form-text text-muted">Selecione os planos em que este requisito estará disponível. Se nenhum selecionado, pode ser interpretado como disponível para todos (verifique lógica de aplicação).</small>
                    </div>

                     <div class="col-12 mt-3">
                         <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?= !empty($requisito_form_data['ativo']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Requisito Ativo</label>
                        </div>
                     </div>
                 </div>

                <hr class="my-4">
                 <div class="d-flex justify-content-end">
                     <a href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php" class="btn btn-secondary btn-sm me-2">Cancelar</a>
                     <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i> Salvar Alterações no Requisito
                     </button>
                 </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editRequisitoForm');
    if (form) {
        form.addEventListener('submit', event => {
            // Validação extra para data, se necessário e se o HTML5 não pegar
            const dataRevisaoInput = document.getElementById('data_ultima_revisao_norma');
            if (dataRevisaoInput && dataRevisaoInput.value) {
                // Regex simples para YYYY-MM-DD. Uma validação mais robusta de data poderia ser adicionada.
                if (!/^\d{4}-\d{2}-\d{2}$/.test(dataRevisaoInput.value)) {
                     // Se inválido, o browser já deve impedir pelo type="date", mas como fallback:
                    dataRevisaoInput.classList.add('is-invalid'); // Adiciona classe de erro do Bootstrap
                    // O feedback 'invalid-feedback' do HTML já deve aparecer
                } else {
                    dataRevisaoInput.classList.remove('is-invalid');
                }
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }
});
</script>

<?php
echo getFooterAdmin();
?>