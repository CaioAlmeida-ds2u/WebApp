<?php
// admin/admin_editar_conta_cliente.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Precisará de getEmpresaClientePorId, atualizarDadosEmpresaCliente
require_once __DIR__ . '/../includes/funcoes_upload.php';  // Para o logo

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Perfil admin da Acoditools
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Obter ID da Empresa Cliente a Editar ---
$cliente_empresa_id_editar = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$cliente_empresa_id_editar) {
    definir_flash_message('erro', 'ID de empresa cliente inválido ou não fornecido.');
    header('Location: ' . BASE_URL . 'admin/admin_gerenciamento_contas_clientes.php');
    exit;
}

// --- Carregar Dados Atuais da Empresa Cliente ---
// *** Chamar função de backend: getEmpresaClientePorId($conexao, $cliente_empresa_id_editar) ***
// Esta função deve buscar da tabela `empresas` e fazer JOIN com `plataforma_planos_assinatura` para pegar o nome do plano.
$empresa_cliente_atual = getEmpresaClientePorId($conexao, $cliente_empresa_id_editar); // Função a ser criada

if (!$empresa_cliente_atual) {
    definir_flash_message('erro', "Empresa cliente com ID $cliente_empresa_id_editar não encontrada.");
    header('Location: ' . BASE_URL . 'admin/admin_gerenciamento_contas_clientes.php');
    exit;
}

// --- Buscar Planos de Assinatura Ativos para o Dropdown ---
// *** Chamar função de backend: listarPlanosAssinatura($conexao, true) ***
$planos_assinatura_disponiveis = listarPlanosAssinatura($conexao, true); // true para apenas ativos

// Status de contrato possíveis
$status_contrato_possiveis = ['Teste', 'Ativo', 'Inadimplente', 'Suspenso', 'Cancelado'];

// --- Lógica de Mensagens Flash (serão definidas no processamento POST) ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');


// --- Processamento do Formulário de Edição (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão. Ação não executada.');
    } else {
        $id_post = filter_input(INPUT_POST, 'cliente_empresa_id_form', FILTER_VALIDATE_INT);
        if ($id_post !== $cliente_empresa_id_editar) {
            definir_flash_message('erro', 'Inconsistência no ID da empresa. Ação não executada.');
        } else {
            $dados_update_cliente = [
                'nome_fantasia' => trim($_POST['nome_fantasia_cliente_edit'] ?? ''),
                'razao_social' => trim($_POST['razao_social_cliente_edit'] ?? ''),
                'cnpj_cliente' => preg_replace('/[^0-9]/', '', trim($_POST['cnpj_cliente_edit'] ?? '')),
                'email_contato_cliente' => trim($_POST['email_contato_cliente_edit'] ?? ''),
                'telefone_contato_cliente' => trim($_POST['telefone_contato_cliente_edit'] ?? ''),
                'endereco_cliente' => trim($_POST['endereco_cliente_edit'] ?? ''),
                'contato_principal_cliente' => trim($_POST['contato_principal_cliente_edit'] ?? ''),
                'plano_assinatura_id_novo' => filter_input(INPUT_POST, 'plano_assinatura_id_cliente_edit', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                'status_contrato_novo' => $_POST['status_contrato_cliente_edit'] ?? $empresa_cliente_atual['status_contrato_cliente'],
                'ativo_na_plataforma' => isset($_POST['ativo_na_plataforma_cliente_edit']) ? 1 : 0,
                // Limites personalizados (se houver lógica para isso além do plano)
                'limite_usuarios_personalizado_cliente' => filter_input(INPUT_POST, 'limite_usuarios_personalizado_cliente_edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => null]], FILTER_NULL_ON_FAILURE),
                'limite_armazenamento_personalizado_cliente_mb' => filter_input(INPUT_POST, 'limite_armazenamento_personalizado_cliente_mb_edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => null]], FILTER_NULL_ON_FAILURE),
            ];
            $logoFileClienteEdit = $_FILES['logo_cliente_edit'] ?? null;
            $removerLogoClienteAtual = isset($_POST['remover_logo_cliente_edit']);

            // Validações (similar ao criar, mas verificando CNPJ/Email contra OUTRAS empresas)
            $validation_errors = [];
            if (empty($dados_update_cliente['nome_fantasia'])) $validation_errors[] = "Nome Fantasia obrigatório.";
            if (empty($dados_update_cliente['cnpj_cliente'])) $validation_errors[] = "CNPJ obrigatório.";
            elseif (function_exists('validarCNPJ') && !validarCNPJ($dados_update_cliente['cnpj_cliente'])) $validation_errors[] = "CNPJ inválido.";
            else {
                 // *** Chamar função: verificarCnpjDuplicadoOutraEmpresa($conexao, $dados_update_cliente['cnpj_cliente'], $cliente_empresa_id_editar) ***
                 if (verificarCnpjDuplicadoOutraEmpresa($conexao, $dados_update_cliente['cnpj_cliente'], $cliente_empresa_id_editar)) {
                     $validation_errors[] = "Este CNPJ já está em uso por outra empresa cliente.";
                 }
            }
            if (empty($dados_update_cliente['email_contato_cliente'])) $validation_errors[] = "E-mail de contato obrigatório.";
            elseif (!filter_var($dados_update_cliente['email_contato_cliente'], FILTER_VALIDATE_EMAIL)) $validation_errors[] = "E-mail de contato inválido.";
            // Não precisa verificar duplicação de email aqui se for apenas um email de contato da empresa, e não de um usuário.

            $nome_logo_final_cliente = $empresa_cliente_atual['logo_cliente_path'] ?? null; // Mantém logo antigo

            if ($removerLogoClienteAtual && $nome_logo_final_cliente) {
                // *** Lógica para remover logo (usar UPLOADS_BASE_PATH_ABSOLUTE e subpasta /logos_clientes/) ***
                $caminhoAbsLogoAntigo = rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/logos_clientes/' . $nome_logo_final_cliente;
                if (file_exists($caminhoAbsLogoAntigo)) { if (@unlink($caminhoAbsLogoAntigo)) { $nome_logo_final_cliente = null; } else { $validation_errors[] = "Erro ao remover logo atual."; }
                } else { $nome_logo_final_cliente = null; /* Arquivo não existia mais */ }
            } elseif ($logoFileClienteEdit && $logoFileClienteEdit['error'] === UPLOAD_ERR_OK) {
                 // *** Chamar função: processarUploadLogoEmpresaCliente($logoFileClienteEdit, $conexao, $nome_logo_final_cliente) ***
                 // Passa o logo antigo para que a função possa removê-lo se o novo for salvo com sucesso.
                $resUpload = processarUploadLogoEmpresaCliente($logoFileClienteEdit, $conexao, $nome_logo_final_cliente);
                if ($resUpload['success']) {
                    $nome_logo_final_cliente = $resUpload['nome_arquivo'];
                } else {
                    $validation_errors[] = "Logo: " . $resUpload['message'];
                }
            }
            $dados_update_cliente['logo_cliente_path_final'] = $nome_logo_final_cliente;

            if (empty($validation_errors)) {
                // *** Chamar função de backend: atualizarDadosEmpresaCliente($conexao, $cliente_empresa_id_editar, $dados_update_cliente, $_SESSION['usuario_id']) ***
                $resultado_update = atualizarDadosEmpresaCliente($conexao, $cliente_empresa_id_editar, $dados_update_cliente, $_SESSION['usuario_id']);
                if ($resultado_update === true) {
                    definir_flash_message('sucesso', "Dados da empresa cliente '".htmlspecialchars($dados_update_cliente['nome_fantasia'])."' atualizados com sucesso!");
                    // Recarregar dados para exibir no formulário
                     $empresa_cliente_atual = getEmpresaClientePorId($conexao, $cliente_empresa_id_editar);
                } else {
                    definir_flash_message('erro', is_string($resultado_update) ? $resultado_update : "Erro desconhecido ao atualizar a empresa cliente.");
                }
            } else {
                definir_flash_message('erro', "<strong>Foram encontrados erros:</strong><ul><li>" . implode("</li><li>", $validation_errors) . "</li></ul>");
            }
        }
    }
    // Regenerar CSRF para a próxima requisição se não for erro de validação do formulário
    if (empty($validation_errors)) {
         $_SESSION['csrf_token'] = gerar_csrf_token();
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); // Recarrega para mostrar mensagens
    exit;
}


// CSRF token para o formulário
$csrf_token_page = $_SESSION['csrf_token'] ?? gerar_csrf_token();
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = $csrf_token_page; }

$title = "Editar Empresa Cliente: " . htmlspecialchars($empresa_cliente_atual['nome_fantasia']);
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-edit me-2"></i>Editar Empresa Cliente: <span class="fw-normal"><?= htmlspecialchars($empresa_cliente_atual['nome_fantasia']) ?></span></h1>
        <a href="<?= BASE_URL ?>admin/admin_gerenciamento_contas_clientes.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar para Lista de Clientes
        </a>
    </div>

    <?php if ($sucesso_msg): ?><div class="alert alert-success custom-alert fade show" role="alert"><?= $sucesso_msg ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger custom-alert fade show" role="alert"><?= $erro_msg /* Permite HTML */ ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" id="editClienteForm" class="needs-validation" novalidate enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
        <input type="hidden" name="cliente_empresa_id_form" value="<?= htmlspecialchars($empresa_cliente_atual['id']) ?>">

        <div class="row g-4">
            <!-- Coluna da Esquerda: Dados da Empresa e Contrato -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4 rounded-3 border-0">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-id-card me-2 text-primary opacity-75"></i>Dados Cadastrais</h6></div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nome_fantasia_cliente_edit" class="form-label form-label-sm fw-semibold">Nome Fantasia <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="nome_fantasia_cliente_edit" name="nome_fantasia_cliente_edit" value="<?= htmlspecialchars($empresa_cliente_atual['nome_fantasia']) ?>" required>
                                <div class="invalid-feedback">Campo obrigatório.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="razao_social_cliente_edit" class="form-label form-label-sm fw-semibold">Razão Social</label>
                                <input type="text" class="form-control form-control-sm" id="razao_social_cliente_edit" name="razao_social_cliente_edit" value="<?= htmlspecialchars($empresa_cliente_atual['razao_social'] ?? '') ?>">
                            </div>
                            <div class="col-md-5">
                                <label for="cnpj_cliente_edit" class="form-label form-label-sm fw-semibold">CNPJ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="cnpj_cliente_edit" name="cnpj_cliente_edit" value="<?= htmlspecialchars($empresa_cliente_atual['cnpj_cliente']) ?>" required>
                                 <div class="invalid-feedback">CNPJ obrigatório e válido.</div>
                            </div>
                            <div class="col-md-7">
                                <label for="endereco_cliente_edit" class="form-label form-label-sm fw-semibold">Endereço</label>
                                <input type="text" class="form-control form-control-sm" id="endereco_cliente_edit" name="endereco_cliente_edit" value="<?= htmlspecialchars($empresa_cliente_atual['endereco_cliente'] ?? '') ?>">
                            </div>
                            <div class="col-md-5">
                                <label for="email_contato_cliente_edit" class="form-label form-label-sm fw-semibold">E-mail Contato <span class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-sm" id="email_contato_cliente_edit" name="email_contato_cliente_edit" value="<?= htmlspecialchars($empresa_cliente_atual['email_contato_cliente']) ?>" required>
                                 <div class="invalid-feedback">E-mail inválido/obrigatório.</div>
                            </div>
                             <div class="col-md-4">
                                <label for="telefone_contato_cliente_edit" class="form-label form-label-sm fw-semibold">Telefone Contato</label>
                                <input type="text" class="form-control form-control-sm" id="telefone_contato_cliente_edit" name="telefone_contato_cliente_edit" value="<?= htmlspecialchars($empresa_cliente_atual['telefone_contato_cliente'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="contato_principal_cliente_edit" class="form-label form-label-sm fw-semibold">Nome Contato</label>
                                <input type="text" class="form-control form-control-sm" id="contato_principal_cliente_edit" name="contato_principal_cliente_edit" value="<?= htmlspecialchars($empresa_cliente_atual['contato_principal_cliente'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm rounded-3 border-0">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-file-contract me-2 text-primary opacity-75"></i>Plano e Contrato</h6></div>
                    <div class="card-body p-3">
                         <div class="row g-3">
                            <div class="col-md-6">
                                <label for="plano_assinatura_id_cliente_edit" class="form-label form-label-sm fw-semibold">Plano de Assinatura <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="plano_assinatura_id_cliente_edit" name="plano_assinatura_id_cliente_edit" required>
                                    <option value="">-- Selecione --</option>
                                    <?php foreach($planos_assinatura_disponiveis as $plano_opt): ?>
                                        <option value="<?= $plano_opt['id'] ?>" <?= ($empresa_cliente_atual['plano_assinatura_id'] == $plano_opt['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($plano_opt['nome_plano']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Selecione um plano.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="status_contrato_cliente_edit" class="form-label form-label-sm fw-semibold">Status do Contrato <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="status_contrato_cliente_edit" name="status_contrato_cliente_edit" required>
                                     <?php foreach ($status_contrato_possiveis as $status_val): ?>
                                    <option value="<?= $status_val ?>" <?= ($empresa_cliente_atual['status_contrato_cliente'] == $status_val) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status_val) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Selecione um status.</div>
                            </div>
                             <div class="col-md-6">
                                <label for="limite_usuarios_personalizado_cliente_edit" class="form-label form-label-sm fw-semibold">Limite Usuários (Personalizado)</label>
                                <input type="number" class="form-control form-control-sm" id="limite_usuarios_personalizado_cliente_edit" name="limite_usuarios_personalizado_cliente_edit" value="<?= htmlspecialchars($empresa_cliente_atual['limite_usuarios_personalizado'] ?? '') ?>" min="0" placeholder="Padrão do Plano">
                                <small class="form-text text-muted">Deixe vazio para usar o limite do plano.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="limite_armazenamento_personalizado_cliente_mb_edit" class="form-label form-label-sm fw-semibold">Armazenamento (MB Personalizado)</label>
                                <input type="number" class="form-control form-control-sm" id="limite_armazenamento_personalizado_cliente_mb_edit" name="limite_armazenamento_personalizado_cliente_mb_edit" value="<?= htmlspecialchars($empresa_cliente_atual['limite_armazenamento_personalizado_mb'] ?? '') ?>" min="0" placeholder="Padrão do Plano">
                            </div>
                             <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="ativo_na_plataforma_cliente_edit" name="ativo_na_plataforma_cliente_edit" value="1" <?= !empty($empresa_cliente_atual['ativo_na_plataforma']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="ativo_na_plataforma_cliente_edit">Conta Ativa na Plataforma AcodITools</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna da Direita: Logo e Ações -->
            <div class="col-lg-4">
                <div class="card shadow-sm rounded-3 border-0 sticky-lg-top" style="top: 20px;">
                     <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-image me-2 text-primary opacity-75"></i>Logo da Empresa Cliente</h6></div>
                    <div class="card-body p-3 text-center">
                        <div id="preview-container-logo-edit" class="mb-2">
                            <?php
                                $logoClienteEditSrc = BASE_URL . 'assets/img/default-logo.png'; // Placeholder
                                if (!empty($empresa_cliente_atual['logo_cliente_path']) && UPLOADS_BASE_PATH_ABSOLUTE && file_exists(rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/logos_clientes/' . $empresa_cliente_atual['logo_cliente_path'])) {
                                    $logoClienteEditSrc = BASE_URL . 'uploads/logos_clientes/' . $empresa_cliente_atual['logo_cliente_path'] . '?v=' . filemtime(rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/logos_clientes/' . $empresa_cliente_atual['logo_cliente_path']);
                                }
                            ?>
                            <img id="current-logo-cliente" src="<?= $logoClienteEditSrc ?>" alt="Logo Atual" class="img-thumbnail mb-2 shadow-sm" style="max-width: 200px; max-height: 150px; object-fit: contain; background-color: #f8f9fa;">
                            <img id="preview-logo-cliente" src="#" alt="Novo Logo" class="img-thumbnail mb-2 shadow-sm" style="max-width: 200px; max-height: 150px; object-fit: contain; background-color: #f8f9fa; display: none;">
                        </div>
                        <input type="file" class="form-control form-control-sm mb-2" id="logo_cliente_edit" name="logo_cliente_edit" accept="image/jpeg, image/png, image/gif, image/svg+xml">
                        <small class="form-text text-muted d-block">JPG, PNG, GIF, SVG (máx <?= MAX_UPLOAD_SIZE_MB ?>MB).</small>
                        <?php if (!empty($empresa_cliente_atual['logo_cliente_path'])): ?>
                            <div class="form-check form-check-inline mt-2">
                                <input class="form-check-input" type="checkbox" id="remover_logo_cliente_edit" name="remover_logo_cliente_edit" value="1">
                                <label class="form-check-label small text-danger" for="remover_logo_cliente_edit">Remover logo atual</label>
                            </div>
                        <?php endif; ?>
                    </div>
                     <div class="card-footer bg-light text-end border-0 pt-3 pb-3">
                        <a href="<?= BASE_URL ?>admin/admin_gerenciamento_contas_clientes.php" class="btn btn-secondary btn-sm me-2">Cancelar Alterações</a>
                        <button type="submit" name="action" value="salvar_alteracoes_cliente" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Salvar Dados do Cliente</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/imask/7.1.3/imask.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscaras
    if (typeof IMask !== 'undefined') {
        const cnpjInputEdit = document.getElementById('cnpj_cliente_edit');
        if (cnpjInputEdit) IMask(cnpjInputEdit, { mask: '00.000.000/0000-00' });
        const telInputEdit = document.getElementById('telefone_contato_cliente_edit');
        if (telInputEdit) IMask(telInputEdit, { mask: '(00) 0000[0]-0000' });
    }

    // Validação Bootstrap
    const formEditCliente = document.getElementById('editClienteForm');
    if (formEditCliente) {
        formEditCliente.addEventListener('submit', event => {
            if (!formEditCliente.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            formEditCliente.classList.add('was-validated');
        }, false);
    }

    // Preview do Logo na Edição
    const inputLogoEdit = document.getElementById('logo_cliente_edit');
    const previewLogoEdit = document.getElementById('preview-logo-cliente');
    const currentLogoCliente = document.getElementById('current-logo-cliente');
    const removerLogoCheckboxEdit = document.getElementById('remover_logo_cliente_edit');

    if (inputLogoEdit && previewLogoEdit && currentLogoCliente) {
        inputLogoEdit.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewLogoEdit.src = e.target.result;
                    previewLogoEdit.style.display = 'block';
                    currentLogoCliente.style.display = 'none';
                    if (removerLogoCheckboxEdit) removerLogoCheckboxEdit.checked = false;
                }
                reader.readAsDataURL(this.files[0]);
            } else {
                previewLogoEdit.src = '#';
                previewLogoEdit.style.display = 'none';
                currentLogoCliente.style.display = 'block';
            }
        });
    }
    if(removerLogoCheckboxEdit && inputLogoEdit) {
        removerLogoCheckboxEdit.addEventListener('change', function() {
            if(this.checked) {
                inputLogoEdit.value = ''; // Limpa seleção
                inputLogoEdit.dispatchEvent(new Event('change')); // Atualiza preview
            }
        });
    }
});
</script>

<?php
echo getFooterAdmin();
?>