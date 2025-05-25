<?php
// admin/plataforma_editar_plano.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// Obter ID do Plano a Editar
$plano_id_editar = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$plano_id_editar) {
    definir_flash_message('erro', 'ID de plano de assinatura inválido ou não fornecido.');
    header('Location: ' . BASE_URL . 'admin/plataforma_gestao_planos_assinatura.php');
    exit;
}

// Carregar Dados do Plano
$plano_atual = getPlanoAssinaturaPorId($conexao, $plano_id_editar);
if (!$plano_atual) {
    definir_flash_message('erro', "Plano de assinatura com ID $plano_id_editar não encontrado.");
    header('Location: ' . BASE_URL . 'admin/plataforma_gestao_planos_assinatura.php');
    exit;
}
// Para repopular o form em caso de erro ou na primeira carga
$form_data_plano_edit = $plano_atual;

$erro_msg_local = ''; // Para erros de validação desta página

// Processamento do Formulário de Edição (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_post = filter_input(INPUT_POST, 'plano_id_form', FILTER_VALIDATE_INT);
    if (!validar_csrf_token($_POST['csrf_token'] ?? null) || $id_post !== $plano_id_editar) {
        definir_flash_message('erro', "Erro de validação da sessão ou ID inconsistente.");
        header('Location: ' . BASE_URL . 'admin/plataforma_gestao_planos_assinatura.php');
        exit;
    }

    // Coletar todos os dados do formulário
    $form_data_plano_edit['nome_plano'] = trim($_POST['nome_plano_edit'] ?? '');
    $form_data_plano_edit['descricao_plano'] = trim($_POST['descricao_plano_edit'] ?? '');
    $form_data_plano_edit['preco_mensal'] = filter_input(INPUT_POST, 'preco_mensal_edit', FILTER_VALIDATE_FLOAT, ['flags' => FILTER_NULL_ON_FAILURE]);
    
    $form_data_plano_edit['limite_empresas_filhas'] = filter_input(INPUT_POST, 'limite_empresas_filhas_edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 0]]);
    $form_data_plano_edit['limite_gestores_por_empresa'] = filter_input(INPUT_POST, 'limite_gestores_por_empresa_edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 1]]);
    $form_data_plano_edit['limite_auditores_por_empresa'] = filter_input(INPUT_POST, 'limite_auditores_por_empresa_edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 1]]);
    $form_data_plano_edit['limite_usuarios_auditados_por_empresa'] = filter_input(INPUT_POST, 'limite_usuarios_auditados_por_empresa_edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 0]]);
    $form_data_plano_edit['limite_auditorias_ativas_por_empresa'] = filter_input(INPUT_POST, 'limite_auditorias_ativas_por_empresa_edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 0]]);
    $form_data_plano_edit['limite_armazenamento_mb_por_empresa'] = filter_input(INPUT_POST, 'limite_armazenamento_mb_por_empresa_edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 0]]);
    
    $form_data_plano_edit['permite_modelos_customizados_empresa'] = isset($_POST['permite_modelos_customizados_empresa_edit']) ? 1 : 0;
    $form_data_plano_edit['permite_campos_personalizados_empresa'] = isset($_POST['permite_campos_personalizados_empresa_edit']) ? 1 : 0;
    $form_data_plano_edit['funcionalidades_extras_json'] = trim($_POST['funcionalidades_extras_json_edit'] ?? '');
    $form_data_plano_edit['ativo'] = isset($_POST['ativo_plano_edit']) ? 1 : 0;

    // Validações
    $validation_errors_edit_plano = [];
    if (empty($form_data_plano_edit['nome_plano'])) {
        $validation_errors_edit_plano[] = "O nome do plano é obrigatório.";
    }
    if ($form_data_plano_edit['preco_mensal'] !== null && $form_data_plano_edit['preco_mensal'] < 0) {
        $validation_errors_edit_plano[] = "O preço mensal não pode ser negativo.";
    }
    // Validar se funcionalidades_extras_json é um JSON válido (se preenchido)
    if (!empty($form_data_plano_edit['funcionalidades_extras_json'])) {
        json_decode($form_data_plano_edit['funcionalidades_extras_json']);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $validation_errors_edit_plano[] = "O campo 'Funcionalidades Extras (JSON)' não contém um JSON válido.";
        }
    } else {
        $form_data_plano_edit['funcionalidades_extras_json'] = null; // Salvar como NULL se vazio
    }


    if (empty($validation_errors_edit_plano)) {
        // Passar o array completo para a função de atualização
        $resultado_update = atualizarPlanoAssinatura($conexao, $plano_id_editar, $form_data_plano_edit, $_SESSION['usuario_id']);

        if ($resultado_update === true) {
            definir_flash_message('sucesso', "Plano '".htmlspecialchars($form_data_plano_edit['nome_plano'])."' (ID: $plano_id_editar) atualizado com sucesso!");
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_plano_sucesso', 1, "Plano ID: $plano_id_editar", $conexao);
            header('Location: ' . BASE_URL . 'admin/plataforma_gestao_planos_assinatura.php?pagina=' . ($_GET['pagina_origem'] ?? 1)); // Volta para a lista, idealmente na página correta
            exit;
        } else {
            $erro_msg_local = is_string($resultado_update) ? $resultado_update : "Erro ao salvar o plano no banco de dados.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_plano_falha_db', 0, "Plano ID: $plano_id_editar, Erro: $erro_msg_local", $conexao);
        }
    } else {
        $erro_msg_local = "<strong>Foram encontrados erros:</strong><ul><li>" . implode("</li><li>", $validation_errors_edit_plano) . "</li></ul>";
    }
    // Se houver erro, $form_data_plano_edit já contém os dados do POST para repopular
}


// Gerar CSRF token para o formulário GET
$_SESSION['csrf_token'] = gerar_csrf_token();
$csrf_token_page = $_SESSION['csrf_token'];

$title = "Editar Plano de Assinatura: " . htmlspecialchars($form_data_plano_edit['nome_plano']);
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-edit me-2"></i>Editar Plano de Assinatura</h1>
        <a href="<?= BASE_URL ?>admin/plataforma_gestao_planos_assinatura.php<?= isset($_GET['pagina_origem']) ? '?pagina_planos='.$_GET['pagina_origem'] : '' ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar para Lista de Planos
        </a>
    </div>

    <?php if (!empty($erro_msg_local)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $erro_msg_local /* Permite HTML dos erros de validação */ ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" id="editPlanoForm" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
        <input type="hidden" name="plano_id_form" value="<?= htmlspecialchars($plano_id_editar) ?>">

        <div class="card shadow-sm rounded-3 border-0">
            <div class="card-header bg-light border-bottom pt-3 pb-2">
                <h6 class="mb-0 fw-bold"><i class="fas fa-file-invoice-dollar me-2 text-primary opacity-75"></i>Editando Plano: <?= htmlspecialchars($form_data_plano_edit['nome_plano']) ?> (ID: <?= $plano_id_editar ?>)</h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label for="nome_plano_edit" class="form-label form-label-sm fw-semibold">Nome do Plano <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="nome_plano_edit" name="nome_plano_edit" value="<?= htmlspecialchars($form_data_plano_edit['nome_plano']) ?>" required maxlength="100">
                        <div class="invalid-feedback">Nome do plano é obrigatório.</div>
                    </div>
                    <div class="col-md-5">
                        <label for="preco_mensal_edit" class="form-label form-label-sm fw-semibold">Preço Mensal (R$)</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="preco_mensal_edit" name="preco_mensal_edit" value="<?= htmlspecialchars($form_data_plano_edit['preco_mensal'] ?? '') ?>" placeholder="Ex: 99.90" min="0">
                        <div class="invalid-feedback">Preço inválido.</div>
                    </div>
                    <div class="col-12">
                        <label for="descricao_plano_edit" class="form-label form-label-sm fw-semibold">Descrição Curta</label>
                        <textarea class="form-control form-control-sm" id="descricao_plano_edit" name="descricao_plano_edit" rows="2"><?= htmlspecialchars($form_data_plano_edit['descricao_plano'] ?? '') ?></textarea>
                    </div>

                    <h6 class="mt-4 mb-1 fw-semibold small text-muted border-top pt-3 col-12">Limites de Recursos por Empresa Cliente:</h6>
                    <div class="col-md-4 col-lg-3">
                        <label for="limite_gestores_por_empresa_edit" class="form-label form-label-sm">Max. Gestores</label>
                        <input type="number" class="form-control form-control-sm" id="limite_gestores_por_empresa_edit" name="limite_gestores_por_empresa_edit" value="<?= htmlspecialchars((int)($form_data_plano_edit['limite_gestores_por_empresa'] ?? 1)) ?>" min="0">
                        <small class="form-text text-muted">0 para ilimitado.</small>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label for="limite_auditores_por_empresa_edit" class="form-label form-label-sm">Max. Auditores</label>
                        <input type="number" class="form-control form-control-sm" id="limite_auditores_por_empresa_edit" name="limite_auditores_por_empresa_edit" value="<?= htmlspecialchars((int)($form_data_plano_edit['limite_auditores_por_empresa'] ?? 1)) ?>" min="0">
                        <small class="form-text text-muted">0 para ilimitado.</small>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label for="limite_usuarios_auditados_por_empresa_edit" class="form-label form-label-sm">Max. Auditados</label>
                        <input type="number" class="form-control form-control-sm" id="limite_usuarios_auditados_por_empresa_edit" name="limite_usuarios_auditados_por_empresa_edit" value="<?= htmlspecialchars((int)($form_data_plano_edit['limite_usuarios_auditados_por_empresa'] ?? 0)) ?>" min="0">
                        <small class="form-text text-muted">0 para ilimitado.</small>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label for="limite_auditorias_ativas_por_empresa_edit" class="form-label form-label-sm">Max. Auditorias Ativas</label>
                        <input type="number" class="form-control form-control-sm" id="limite_auditorias_ativas_por_empresa_edit" name="limite_auditorias_ativas_por_empresa_edit" value="<?= htmlspecialchars((int)($form_data_plano_edit['limite_auditorias_ativas_por_empresa'] ?? 0)) ?>" min="0">
                        <small class="form-text text-muted">0 para ilimitado.</small>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label for="limite_armazenamento_mb_por_empresa_edit" class="form-label form-label-sm">Armazenamento (MB)</label>
                        <input type="number" class="form-control form-control-sm" id="limite_armazenamento_mb_por_empresa_edit" name="limite_armazenamento_mb_por_empresa_edit" value="<?= htmlspecialchars((int)($form_data_plano_edit['limite_armazenamento_mb_por_empresa'] ?? 0)) ?>" min="0">
                        <small class="form-text text-muted">0 para ilimitado.</small>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label for="limite_empresas_filhas_edit" class="form-label form-label-sm">Max. Empresas Filhas</label>
                        <input type="number" class="form-control form-control-sm" id="limite_empresas_filhas_edit" name="limite_empresas_filhas_edit" value="<?= htmlspecialchars((int)($form_data_plano_edit['limite_empresas_filhas'] ?? 0)) ?>" min="0">
                        <small class="form-text text-muted">0 se não aplicável.</small>
                    </div>

                    <h6 class="mt-4 mb-1 fw-semibold small text-muted border-top pt-3 col-12">Permissões de Funcionalidades:</h6>
                    <div class="col-12">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="permite_modelos_customizados_empresa_edit" name="permite_modelos_customizados_empresa_edit" value="1" <?= !empty($form_data_plano_edit['permite_modelos_customizados_empresa']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="permite_modelos_customizados_empresa_edit">Permitir customização/criação de modelos por empresas clientes</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="permite_campos_personalizados_empresa_edit" name="permite_campos_personalizados_empresa_edit" value="1" <?= !empty($form_data_plano_edit['permite_campos_personalizados_empresa']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="permite_campos_personalizados_empresa_edit">Permitir uso de campos personalizados (habilitados pela AcodITools)</label>
                        </div>
                    </div>

                    <div class="col-12 mt-3">
                        <label for="funcionalidades_extras_json_edit" class="form-label form-label-sm fw-semibold">Funcionalidades Extras (JSON - Avançado)</label>
                        <textarea class="form-control form-control-sm" id="funcionalidades_extras_json_edit" name="funcionalidades_extras_json_edit" rows="3" placeholder='Ex: {"relatorios_avancados": true, "limite_api_requests": 1000}'><?= htmlspecialchars($form_data_plano_edit['funcionalidades_extras_json'] ?? '') ?></textarea>
                        <small class="form-text text-muted">Use para flags de features específicas. Deve ser um JSON válido.</small>
                        <div class="invalid-feedback">Formato JSON inválido.</div>
                    </div>

                     <div class="col-12 mt-3">
                         <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="ativo_plano_edit" name="ativo_plano_edit" value="1" <?= !empty($form_data_plano_edit['ativo']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="ativo_plano_edit">Plano Ativo (disponível para novas assinaturas)</label>
                        </div>
                     </div>
                </div>

                <hr class="my-4">
                 <div class="d-flex justify-content-end">
                     <a href="<?= BASE_URL ?>admin/plataforma_gestao_planos_assinatura.php<?= isset($_GET['pagina_origem']) ? '?pagina_planos='.$_GET['pagina_origem'] : '' ?>" class="btn btn-outline-secondary btn-sm me-2">Cancelar</a>
                     <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i> Salvar Alterações no Plano
                     </button>
                 </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editPlanoForm = document.getElementById('editPlanoForm');
    if (editPlanoForm) {
        editPlanoForm.addEventListener('submit', event => {
            // Validação de JSON para funcionalidades extras
            const jsonInput = document.getElementById('funcionalidades_extras_json_edit');
            if (jsonInput && jsonInput.value.trim() !== '') {
                try {
                    JSON.parse(jsonInput.value);
                    jsonInput.classList.remove('is-invalid'); // Remove erro se for válido
                } catch (e) {
                    jsonInput.classList.add('is-invalid'); // Adiciona erro Bootstrap
                    // O invalid-feedback do HTML já deve ter a mensagem, mas pode setar uma customizada:
                    // jsonInput.nextElementSibling.textContent = 'Formato JSON inválido.';
                    event.preventDefault(); 
                    event.stopPropagation();
                }
            } else {
                // Se estiver vazio, é válido (considerado NULL no backend)
                 if (jsonInput) jsonInput.classList.remove('is-invalid');
            }

            if (!editPlanoForm.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            editPlanoForm.classList.add('was-validated');
        }, false);
    }
});
</script>

<?php
echo getFooterAdmin();
?>