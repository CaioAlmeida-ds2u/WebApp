<?php
// admin/empresa/editar_empresa.php - COM CAMPO LOGO

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_admin.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
// **** INCLUIR UPLOAD ****
require_once __DIR__ . '/../../includes/funcoes_upload.php';
require_once __DIR__ . '/../../includes/db.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { /* ... Acesso negado ... */ exit; }

// --- Obter ID e Carregar Dados ---
$empresa_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$empresa_id) { /* ... ID inválido ... */ header('Location: '.BASE_URL.'admin/empresa/empresa_index.php'); exit;}
$empresa = getEmpresaPorId($conexao, $empresa_id);
if (!$empresa) { /* ... Empresa não encontrada ... */ header('Location: '.BASE_URL.'admin/empresa/empresa_index.php'); exit;}

$erro = '';

// --- Processar Edição (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar CSRF e ID
    $id_post = filter_input(INPUT_POST, 'empresa_id', FILTER_VALIDATE_INT);
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) || $id_post !== $empresa_id) {
        /* ... Erro CSRF ... */
         $_SESSION['erro'] = "Erro de validação."; header('Location: '.BASE_URL.'admin/empresa/empresa_index.php'); exit;
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // 2. Obter Dados Form + Upload Logo
    $dados_form = [
        'nome' => trim($_POST['nome'] ?? ''), 'cnpj' => trim($_POST['cnpj'] ?? ''),
        'razao_social' => trim($_POST['razao_social'] ?? ''), 'endereco' => trim($_POST['endereco'] ?? ''),
        'contato' => trim($_POST['contato'] ?? ''), 'telefone' => trim($_POST['telefone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''), 'ativo' => isset($_POST['ativo']) ? 1 : 0
    ];
    // **** PEGAR DADOS DO LOGO ****
    $logoFile = $_FILES['logo'] ?? null;
    $removerLogoAtual = isset($_POST['remover_logo']); // Checkbox para remover

    // 3. Validação Campos Texto (igual ao criar.php)
    $errors = [];
    if (empty($dados_form['nome'])) $errors[] = "Nome Fantasia obrigatório.";
    if (empty($dados_form['cnpj'])) $errors[] = "CNPJ obrigatório.";
    // ... (outras validações obrigatórias) ...

    $nomeArquivoLogoFinal = $empresa['logo']; // Mantém o logo antigo por padrão

    // 4. Processar Upload / Remoção do Logo (ANTES de atualizar o DB)
    if ($removerLogoAtual && $nomeArquivoLogoFinal) {
        // Tenta remover o logo antigo
        $caminhoLogoAntigo = __DIR__ . '/../../uploads/logos/' . $nomeArquivoLogoFinal;
        if (file_exists($caminhoLogoAntigo) && is_writable(dirname($caminhoLogoAntigo))) {
            if (unlink($caminhoLogoAntigo)) {
                 $nomeArquivoLogoFinal = null; // Removeu com sucesso
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'remover_logo_sucesso', 1, "Logo removido Empresa ID: $empresa_id", $conexao);
            } else {
                 $errors[] = "Erro ao tentar remover o logo atual.";
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'remover_logo_falha', 0, "Logo: $nomeArquivoLogoFinal, Empresa ID: $empresa_id", $conexao);
            }
        } else {
             $nomeArquivoLogoFinal = null; // Arquivo não existe mais, apenas limpa a referência
        }
    } elseif ($logoFile && $logoFile['error'] === UPLOAD_ERR_OK) {
        // Se enviou um NOVO logo (e não marcou para remover)
        $resultadoUpload = processarUploadLogoEmpresa($logoFile, $empresa_id, $conexao, $empresa['logo']); // Passa logo antigo para exclusão
        if ($resultadoUpload['success']) {
            $nomeArquivoLogoFinal = $resultadoUpload['nome_arquivo']; // Novo nome do arquivo
        } else {
            $errors[] = "Erro no Upload do Logo: " . $resultadoUpload['message'];
            // O log de erro já foi feito dentro de processarUploadLogoEmpresa
        }
    }
    // Se não enviou novo logo e não marcou para remover, $nomeArquivoLogoFinal continua sendo o antigo.

    // 5. Atualizar Dados no Banco (se não houve erros)
    if (empty($errors)) {
        // Adiciona o nome final do logo aos dados a serem atualizados
        $dados_form['logo'] = $nomeArquivoLogoFinal;

        $resultado = atualizarEmpresa($conexao, $empresa_id, $dados_form, $_SESSION['usuario_id']);

        if ($resultado === true) {
            $_SESSION['sucesso'] = "Empresa '".htmlspecialchars($dados_form['nome'])."' atualizada!";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_empresa_sucesso', 1, "ID: $empresa_id", $conexao);
            header('Location: ' . BASE_URL . 'admin/empresa/empresa_index.php');
            exit;
        } else {
            $erro = $resultado; // Erro da função (CNPJ duplicado, DB, etc)
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_empresa_falha_db', 0, "ID: $empresa_id, Erro: $resultado", $conexao);
            // Repopula com os dados POSTADOS
            $empresa = array_merge($empresa, $dados_form);
            // Reverte o nome do logo para o original se o DB falhou mas upload funcionou
             if(isset($resultadoUpload) && $resultadoUpload['success'] && $resultado !== true) {
                 $empresa['logo'] = $nomeArquivoLogoFinal; // Mantém o novo nome para exibição no form
             } else {
                 $empresa['logo'] = $empresa['logo']; // Mantém o original do banco
             }
        }
    } else {
         $erro = "<strong>Foram encontrados os seguintes erros:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_empresa_falha_valid', 0, "ID: $empresa_id, Erros: ".implode(', ',$errors), $conexao);
         // Repopula com os dados POSTADOS
         $empresa = array_merge($empresa, $dados_form);
         $empresa['logo'] = $empresa['logo']; // Mantém o logo original se houve erro de validação
    }
}

// --- Geração do HTML ---
$title = "Editar Empresa: " . htmlspecialchars($empresa['nome'] ?? 'Inválida');
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-edit me-2"></i>Editar Empresa</h1>
        <a href="<?= BASE_URL ?>admin/empresa/empresa_index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
    </div>

    <?php if ($erro): ?> <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= $erro ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div> <?php endif; ?>

    <?php /* enctype necessário para upload */ ?>
    <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" id="editEmpresaForm" class="needs-validation" novalidate enctype="multipart/form-data">
         <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
         <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($empresa['id']) ?>">

         <div class="card shadow-sm">
             <div class="card-header bg-light">Editando dados de: <strong><?= htmlspecialchars($empresa['nome']) ?></strong> (ID: <?= htmlspecialchars($empresa['id']) ?>)</div>
             <div class="card-body">
                 <div class="row g-3">
                     <?php /* Coluna para Dados */ ?>
                     <div class="col-lg-8">
                         <div class="row g-3">
                             <div class="col-md-6">
                                 <label for="nome" class="form-label form-label-sm">Nome Fantasia <span class="text-danger">*</span></label>
                                 <input type="text" class="form-control form-control-sm" id="nome" name="nome" required value="<?= htmlspecialchars($empresa['nome'] ?? '') ?>" maxlength="255">
                                 <div class="invalid-feedback">Campo obrigatório.</div>
                             </div>
                              <div class="col-md-6">
                                 <label for="cnpj" class="form-label form-label-sm">CNPJ <span class="text-danger">*</span></label>
                                 <input type="text" class="form-control form-control-sm" id="cnpj" name="cnpj" required value="<?= htmlspecialchars($empresa['cnpj'] ?? '') ?>">
                                 <div class="invalid-feedback">CNPJ inválido ou obrigatório.</div>
                             </div>
                              <div class="col-12">
                                 <label for="razao_social" class="form-label form-label-sm">Razão Social <span class="text-danger">*</span></label>
                                 <input type="text" class="form-control form-control-sm" id="razao_social" name="razao_social" required value="<?= htmlspecialchars($empresa['razao_social'] ?? '') ?>" maxlength="255">
                                 <div class="invalid-feedback">Campo obrigatório.</div>
                             </div>
                              <div class="col-12">
                                  <label for="endereco" class="form-label form-label-sm">Endereço Completo</label>
                                  <input type="text" class="form-control form-control-sm" id="endereco" name="endereco" value="<?= htmlspecialchars($empresa['endereco'] ?? '') ?>">
                              </div>
                              <div class="col-md-6">
                                  <label for="email" class="form-label form-label-sm">E-mail <span class="text-danger">*</span></label>
                                  <input type="email" class="form-control form-control-sm" id="email" name="email" required value="<?= htmlspecialchars($empresa['email'] ?? '') ?>" maxlength="255">
                                  <div class="invalid-feedback">E-mail inválido ou obrigatório.</div>
                              </div>
                               <div class="col-md-6">
                                  <label for="telefone" class="form-label form-label-sm">Telefone <span class="text-danger">*</span></label>
                                  <input type="text" class="form-control form-control-sm" id="telefone" name="telefone" required value="<?= htmlspecialchars($empresa['telefone'] ?? '') ?>">
                                   <div class="invalid-feedback">Campo obrigatório.</div>
                              </div>
                              <div class="col-12">
                                 <label for="contato" class="form-label form-label-sm">Contato Principal <span class="text-danger">*</span></label>
                                 <input type="text" class="form-control form-control-sm" id="contato" name="contato" required value="<?= htmlspecialchars($empresa['contato'] ?? '') ?>" maxlength="255">
                                  <div class="invalid-feedback">Campo obrigatório.</div>
                              </div>
                               <div class="col-12">
                                 <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?= !empty($empresa['ativo']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ativo">Empresa Ativa</label>
                                </div>
                              </div>
                         </div>
                     </div>

                     <?php /* Coluna para Logo */ ?>
                     <div class="col-lg-4 border-start-lg">
                         <div class="text-center sticky-lg-top pt-lg-3"> <?php /* Sticky no desktop */ ?>
                             <label for="logo" class="form-label form-label-sm fw-bold">Logo da Empresa</label>
                             <div id="preview-container-logo" class="mb-2">
                                <?php
                                    $logoAtualSrc = BASE_URL . 'assets/img/default-logo.png'; // Placeholder
                                    if (!empty($empresa['logo'])) {
                                        $logoPath = 'uploads/logos/' . $empresa['logo'];
                                        if (file_exists(__DIR__.'/../../'.$logoPath)) { // Verifica se arquivo existe
                                            $logoAtualSrc = BASE_URL . $logoPath . '?v=' . filemtime(__DIR__.'/../../'.$logoPath); // Cache bust
                                        } else {
                                             $logoAtualSrc = BASE_URL . 'assets/img/logo-error.png'; // Indica que o arquivo sumiu
                                        }
                                    }
                                ?>
                                <img id="current-logo" src="<?= $logoAtualSrc ?>" alt="Logo Atual" class="img-thumbnail mb-2" style="max-width: 180px; max-height: 150px; object-fit: contain; background-color: #f8f9fa;">
                                <img id="preview-logo" src="#" alt="Novo Logo" class="img-thumbnail mb-2" style="max-width: 180px; max-height: 150px; object-fit: contain; background-color: #f8f9fa; display: none;">
                            </div>
                            <input type="file" class="form-control form-control-sm" id="logo" name="logo" accept="image/jpeg, image/png, image/gif, image/svg+xml">
                            <small class="form-text text-muted d-block mt-1">JPG, PNG, GIF, SVG (máx 2MB).</small>
                            <?php if (!empty($empresa['logo'])): ?>
                                <div class="form-check form-check-inline mt-2">
                                    <input class="form-check-input" type="checkbox" id="remover_logo" name="remover_logo" value="1">
                                    <label class="form-check-label small text-danger" for="remover_logo">Remover logo atual</label>
                                </div>
                             <?php endif; ?>
                         </div>
                     </div>
                 </div>
             </div>
             <div class="card-footer bg-light text-end">
                  <a href="<?= BASE_URL ?>admin/empresa/empresa_index.php" class="btn btn-secondary btn-sm me-2">Cancelar</a>
                  <button type="submit" class="btn btn-primary btn-sm">Salvar Alterações</button>
             </div>
        </div>
    </form>

</div>

<?php /* Scripts JS */ ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/imask/7.1.3/imask.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscaras
    if (typeof IMask !== 'undefined') {
        const cnpjInput = document.getElementById('cnpj'); if (cnpjInput) IMask(cnpjInput, { mask: '00.000.000/0000-00' });
        const telInput = document.getElementById('telefone'); if (telInput) IMask(telInput, { mask: '(00) 0000[0]-0000' });
    }

    // Validação Bootstrap
    const form = document.getElementById('editEmpresaForm');
    if (form) { form.addEventListener('submit', event => { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false); }

    // Preview do Logo
    const inputLogo = document.getElementById('logo');
    const previewLogo = document.getElementById('preview-logo');
    const currentLogo = document.getElementById('current-logo');
    const removerLogoCheckbox = document.getElementById('remover_logo');

    if (inputLogo && previewLogo && currentLogo) {
        inputLogo.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewLogo.src = e.target.result;
                    previewLogo.style.display = 'block';
                    currentLogo.style.display = 'none';
                    if (removerLogoCheckbox) removerLogoCheckbox.checked = false; // Desmarca remover se selecionar novo
                }
                reader.readAsDataURL(this.files[0]);
            } else { // Limpa preview se cancelar seleção
                previewLogo.src = '#';
                previewLogo.style.display = 'none';
                currentLogo.style.display = 'block';
            }
        });
    }

    // Lógica para o checkbox de remover logo
    if(removerLogoCheckbox && inputLogo) {
        removerLogoCheckbox.addEventListener('change', function() {
            if(this.checked) {
                inputLogo.value = ''; // Limpa seleção de arquivo se marcar para remover
                inputLogo.dispatchEvent(new Event('change')); // Dispara evento change para atualizar preview
                previewLogo.style.display = 'none';
                currentLogo.style.display = 'block'; // Mostra o atual (que será removido no backend)
            }
        });
    }

});
</script>

<?php
echo getFooterAdmin();
?>