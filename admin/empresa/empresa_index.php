<?php
// admin/empresa/empresa_index.php
// Versão completa com busca, coluna de logo e outras melhorias

require_once __DIR__ . '/../../includes/config.php';        // Config, DB, CSRF Token, Base URL
require_once __DIR__ . '/../../includes/layout_admin.php';   // Layout unificado (Header/Footer)
require_once __DIR__ . '/../../includes/admin_functions.php'; // Funções admin (formatarCNPJ, getEmpresaPorId, etc.)
require_once __DIR__ . '/../../includes/db.php';           // dbGetEmpresas

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens ---
$sucesso_msg = $_SESSION['sucesso'] ?? null;
$erro_msg = $_SESSION['erro'] ?? null;
unset($_SESSION['sucesso'], $_SESSION['erro']);
$erro_registrar_msg = $_SESSION['erro_registrar'] ?? null;
unset($_SESSION['erro_registrar']);
$form_data = $_SESSION['form_data_empresa'] ?? [];
unset($_SESSION['form_data_empresa']);

// --- Processamento de Ações POST (Excluir) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'excluir_empresa') {

    // 1. Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['erro'] = "Erro de validação da sessão. Ação não executada.";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_empresa_csrf_falha', 0, 'Token CSRF inválido.', $conexao);
    } else {
        $empresa_id_excluir = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($empresa_id_excluir) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenera token

            // --- IMPLEMENTAR A FUNÇÃO excluirEmpresa ---
            // >> Substitua a linha abaixo pela chamada real: <<
            $resultado_exclusao = excluirEmpresa($conexao, $empresa_id_excluir);
            //$resultado_exclusao = "(FUNCIONALIDADE EXCLUIR EMPRESA AINDA NÃO IMPLEMENTADA NO BACKEND)"; // Simulação placeholder

            if ($resultado_exclusao === true) {
                 $_SESSION['sucesso'] = "Empresa ID $empresa_id_excluir excluída com sucesso!";
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_empresa_sucesso', 1, "Exclusão ID: $empresa_id_excluir", $conexao);
            } else {
                 // Tratar mensagem de erro específica da função (se string) ou erro genérico
                 $msgErroExclusao = is_string($resultado_exclusao) ? $resultado_exclusao : "Erro ao excluir empresa ID $empresa_id_excluir. Verifique dependências.";
                 $_SESSION['erro'] = $msgErroExclusao;
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_empresa_falha', 0, "Falha exclusão ID: $empresa_id_excluir. Motivo: $msgErroExclusao", $conexao);
            }
        } else {
            $_SESSION['erro'] = "ID inválido para exclusão.";
             dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_empresa_falha', 0, "ID inválido fornecido.", $conexao);
        }
    }
    // Redireciona para a lista para mostrar mensagem e evitar reenvio
    header('Location: ' . BASE_URL . 'admin/empresa/empresa_index.php?aba=empresas'); // Corrigido: empresas (plural)
    exit;
}

// --- Paginação e Filtros ---
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina = 10;
$termo_busca = trim(filter_input(INPUT_GET, 'busca', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''); // Usar sanitize apropriado
$filtros_ativos_pag = array_filter(['busca' => $termo_busca]); // Para paginação manter busca

// --- Obtenção da Lista de Empresas (com busca) ---
$empresa_data = dbGetEmpresas($conexao, $pagina_atual, $itens_por_pagina, $termo_busca);
$lista_empresas = $empresa_data['empresas'] ?? [];
$paginacao = $empresa_data['paginacao'] ?? ['total_paginas' => 0, 'pagina_atual' => 1, 'total_itens' => 0];
$erro_busca = $empresa_data['erro'] ?? null;

// --- Geração do HTML ---
$title = "ACodITools - Gestão de Empresas";
echo getHeaderAdmin($title);

$aba_ativa = $_GET['aba'] ?? 'empresas';
if ($erro_registrar_msg) { $aba_ativa = 'registrar-empresa'; }
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-building me-2"></i>Gestão de Empresas</h1>
        <div class="ms-auto">
            <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex align-items-center">
                <input type="hidden" name="aba" value="empresas">
                <label for="buscaEmpresa" class="visually-hidden">Buscar Empresa</label>
                <input class="form-control form-control-sm me-2" type="search" id="buscaEmpresa" name="busca" placeholder="Buscar por Nome, CNPJ, E-mail..." aria-label="Buscar Empresa" value="<?= htmlspecialchars($termo_busca) ?>">
                <button class="btn btn-sm btn-outline-primary" type="submit" title="Buscar"><i class="fas fa-search"></i></button>
                <?php if (!empty($termo_busca)): ?>
                     <a href="?aba=empresas" class="btn btn-sm btn-outline-secondary ms-1" title="Limpar Busca"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php /* Mensagens gerais */ ?>
    <?php if ($sucesso_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($erro_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($erro_busca): ?><div class="alert alert-warning alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($erro_busca) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>


    <?php /* Abas */ ?>
    <ul class="nav nav-tabs mb-3" id="empresaTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($aba_ativa === 'empresas') ? 'active' : '' ?>" id="empresas-tab" data-bs-toggle="tab" data-bs-target="#empresas-content" type="button" role="tab" aria-controls="empresas-content" aria-selected="<?= ($aba_ativa === 'empresas') ? 'true' : 'false' ?>">
                 <i class="fas fa-list me-1"></i> Empresas Cadastradas <span class="badge rounded-pill bg-secondary ms-1"><?= $paginacao['total_itens'] ?? 0 ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($aba_ativa === 'registrar-empresa') ? 'active' : '' ?>" id="registrar-empresa-tab" data-bs-toggle="tab" data-bs-target="#registrar-empresa-content" type="button" role="tab" aria-controls="registrar-empresa-content" aria-selected="<?= ($aba_ativa === 'registrar-empresa') ? 'true' : 'false' ?>">
                 <i class="fas fa-plus-circle me-1"></i> Registrar Empresa
            </button>
        </li>
    </ul>

    <div class="tab-content" id="empresaTabsContent">
        <?php /* ----- Aba Lista de Empresas ----- */ ?>
        <div class="tab-pane fade <?= ($aba_ativa === 'empresas') ? 'show active' : '' ?>" id="empresas-content" role="tabpanel" aria-labelledby="empresas-tab">
             <div class="card shadow-sm">
                 <div class="card-header bg-light">
                     <i class="fas fa-list me-1"></i>Lista de Empresas <?= !empty($termo_busca) ? '<span class="text-muted fst-italic">(Busca por: "'.htmlspecialchars($termo_busca).'")</span>' : '' ?>
                 </div>
                 <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" style="width: 5%;">Logo</th>
                                    <th scope="col" style="width: 5%;">ID</th>
                                    <th scope="col">Nome Fantasia</th>
                                    <th scope="col">CNPJ</th>
                                    <th scope="col">E-mail</th>
                                    <th scope="col" class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lista_empresas)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted p-4"> <?php /* Colspan 6 agora */ ?>
                                            <?php if (!empty($termo_busca)): ?>
                                                Nenhuma empresa encontrada para "<?= htmlspecialchars($termo_busca) ?>". <a href="?aba=empresas">Limpar busca</a>.
                                            <?php else: ?>
                                                 Nenhuma empresa cadastrada. Utilize a aba <a href="#" data-bs-toggle="tab" data-bs-target="#registrar-empresa-content">Registrar Empresa</a>.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($lista_empresas as $empresa_item): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $logoSrcLista = BASE_URL . 'assets/img/default-logo-sm.png'; // Placeholder padrão
                                                // **** CORREÇÃO AQUI ****
                                                // Usar $empresa_item['logo'] em vez de $empresa_data['logo']
                                                if (!empty($empresa_item['logo'])) {
                                                    $logoNomeArquivo = $empresa_item['logo']; // Nome do arquivo vindo do DB para ESTA empresa
                                                    $logoPathRelativo = 'uploads/logos/' . $logoNomeArquivo;
                                                    $logoPathAbsoluto = __DIR__.'/../../'.$logoPathRelativo; // Caminho absoluto para checar existência

                                                    if (file_exists($logoPathAbsoluto)) {
                                                         // Se existe, monta URL com cache bust
                                                         $logoSrcLista = BASE_URL . $logoPathRelativo . '?t=' . filemtime($logoPathAbsoluto);
                                                    } else {
                                                         // Se não existe o arquivo, mas há referência no DB, mostra erro
                                                         $logoSrcLista = BASE_URL . 'assets/img/logo-error-sm.png'; // Imagem de erro
                                                         error_log("AVISO: Logo '{$logoNomeArquivo}' para empresa ID {$empresa_item['id']} não encontrado em $logoPathAbsoluto");
                                                    }
                                                }
                                                // **** FIM DA CORREÇÃO ****
                                                ?>
                                                <img src="<?= $logoSrcLista ?>"
                                                     alt="Logo <?= htmlspecialchars($empresa_item['nome']) ?>"
                                                     style="width: 35px; height: 35px; object-fit: contain; background-color: #f8f9fa;"
                                                     class="img-thumbnail p-1 rounded-circle"
                                                     loading="lazy"> <?php /* Lazy loading */ ?>
                                            </td>
                                            <td><?= htmlspecialchars($empresa_item['id']) ?></td>
                                            <td><?= htmlspecialchars($empresa_item['nome']) ?></td>
                                            <td><?= function_exists('formatarCNPJ') ? htmlspecialchars(formatarCNPJ($empresa_item['cnpj'])) : htmlspecialchars($empresa_item['cnpj']) ?></td>
                                            <td><a href="mailto:<?= htmlspecialchars($empresa_item['email']) ?>" title="Enviar e-mail"><?= htmlspecialchars($empresa_item['email']) ?></a></td>
                                            <td class="text-center">
                                                 <div class="d-inline-flex flex-nowrap">
                                                    <a href="<?= BASE_URL ?>admin/empresa/editar_empresa.php?id=<?= $empresa_item['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Editar Empresa"><i class="fas fa-edit fa-fw"></i></a>
                                                    <form method="POST" action="<?= BASE_URL ?>admin/empresa/empresa_index.php" class="d-inline" onsubmit="return confirm('ATENÇÃO! Excluir a empresa <?= htmlspecialchars(addslashes($empresa_item['nome'])) ?>? Verifique dependências.');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="excluir_empresa">
                                                        <input type="hidden" name="id" value="<?= $empresa_item['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir Empresa"><i class="fas fa-trash-alt fa-fw"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                 </div>
                 <?php if (isset($paginacao) && $paginacao['total_paginas'] > 1): ?>
                 <div class="card-footer bg-light py-2">
                    <nav aria-label="Paginação de Empresas">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php $link_paginacao = "?aba=empresas&busca=".urlencode($termo_busca)."&pagina="; ?>
                            <?php if ($paginacao['pagina_atual'] > 1): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] - 1) ?>">«</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">«</span></li> <?php endif; ?>
                            <?php $inicio = max(1, $paginacao['pagina_atual'] - 2); $fim = min($paginacao['total_paginas'], $paginacao['pagina_atual'] + 2); if ($inicio > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; for ($i = $inicio; $i <= $fim; $i++): ?><li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="<?= $link_paginacao . $i ?>"><?= $i ?></a></li><?php endfor; if ($fim < $paginacao['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                            <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] + 1) ?>">»</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">»</span></li> <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
             </div>
        </div>

        <?php /* ----- Aba Registrar Empresa ----- */ ?>
        <div class="tab-pane fade <?= ($aba_ativa === 'registrar-empresa') ? 'show active' : '' ?>" id="registrar-empresa-content" role="tabpanel" aria-labelledby="registrar-empresa-tab">
             <div class="card shadow-sm">
                 <div class="card-header bg-light"><i class="fas fa-plus-circle me-1"></i> Registrar Nova Empresa</div>
                 <div class="card-body">
                     <?php if ($erro_registrar_msg): ?> <div class="alert alert-danger" role="alert"><?= $erro_registrar_msg ?></div> <?php endif; ?>
                     <?php /* Adicionado enctype */ ?>
                    <form action="<?= BASE_URL ?>admin/empresa/criar.php" method="POST" id="registerEmpresaForm" class="needs-validation" novalidate enctype="multipart/form-data">
                         <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label for="nome" class="form-label form-label-sm">Nome Fantasia <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="nome" name="nome" required value="<?= htmlspecialchars($form_data['nome'] ?? '') ?>" maxlength="255"><div class="invalid-feedback">Campo obrigatório.</div></div>
                            <div class="col-md-6"><label for="cnpj" class="form-label form-label-sm">CNPJ <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="cnpj" name="cnpj" required value="<?= htmlspecialchars($form_data['cnpj'] ?? '') ?>"><div class="invalid-feedback">CNPJ inválido/obrigatório.</div></div>
                        </div>
                        <div class="mb-3"><label for="razao_social" class="form-label form-label-sm">Razão Social <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="razao_social" name="razao_social" required value="<?= htmlspecialchars($form_data['razao_social'] ?? '') ?>" maxlength="255"><div class="invalid-feedback">Campo obrigatório.</div></div>
                        <div class="mb-3"><label for="endereco" class="form-label form-label-sm">Endereço Completo</label><input type="text" class="form-control form-control-sm" id="endereco" name="endereco" placeholder="Rua, Nº, Bairro, Cidade-UF, CEP" value="<?= htmlspecialchars($form_data['endereco'] ?? '') ?>"></div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label for="email" class="form-label form-label-sm">E-mail <span class="text-danger">*</span></label><input type="email" class="form-control form-control-sm" id="email" name="email" required value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" maxlength="255"><div class="invalid-feedback">E-mail inválido/obrigatório.</div></div>
                            <div class="col-md-6"><label for="telefone" class="form-label form-label-sm">Telefone <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="telefone" name="telefone" required value="<?= htmlspecialchars($form_data['telefone'] ?? '') ?>"><div class="invalid-feedback">Campo obrigatório.</div></div>
                        </div>
                        <div class="mb-3"><label for="contato" class="form-label form-label-sm">Contato Principal <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="contato" name="contato" required value="<?= htmlspecialchars($form_data['contato'] ?? '') ?>" maxlength="255"><div class="invalid-feedback">Campo obrigatório.</div></div>
                        <?php /* **** CAMPO LOGO **** */ ?>
                        <div class="mb-3">
                             <label for="logo" class="form-label form-label-sm">Logo da Empresa (Opcional)</label>
                             <input class="form-control form-control-sm" type="file" id="logo" name="logo" accept="image/jpeg, image/png, image/gif, image/svg+xml">
                             <small class="form-text text-muted">Envie JPG, PNG, GIF, SVG (máx 2MB).</small>
                             <div id="logo-preview-container-registro" class="mt-2" style="max-height: 100px;"></div> <?php /* Container para o preview */ ?>
                         </div>
                         <hr class="my-3">
                         <div class="d-flex justify-content-end">
                             <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-toggle="tab" data-bs-target="#empresas-content">Cancelar</button>
                             <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save me-1"></i>Registrar Empresa</button>
                         </div>
                    </form>
                 </div>
            </div>
        </div>
    </div>
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
    const forms = document.querySelectorAll('.needs-validation'); Array.from(forms).forEach(form => { form.addEventListener('submit', event => { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false); });
    // Ativar aba correta
    const urlParams = new URLSearchParams(window.location.search); const abaParam = urlParams.get('aba'); let targetTabId = 'empresas-content'; <?php if ($erro_registrar_msg): ?> targetTabId = 'registrar-empresa-content'; <?php elseif (!empty($abaParam)): ?> targetTabId = '<?= $aba_ativa === "registrar-empresa" ? "registrar-empresa-content" : "empresas-content" ?>'; <?php endif; ?> const triggerEl = document.querySelector(`#empresaTabs button[data-bs-target="#${targetTabId}"]`); if (triggerEl && bootstrap.Tab.getInstance(triggerEl) === null) { const tab = new bootstrap.Tab(triggerEl); tab.show(); }
    // Botão cancelar
    const cancelButton = document.querySelector('#registerEmpresaForm button[data-bs-target="#empresas-content"]'); if(cancelButton){ cancelButton.addEventListener('click', function() { const ETabTrigger=document.getElementById('empresas-tab'); if(ETabTrigger){const t=new bootstrap.Tab(ETabTrigger); t.show();} const rForm=document.getElementById('registerEmpresaForm'); if(rForm){rForm.reset(); rForm.classList.remove('was-validated'); const preview = document.getElementById('logo-preview-registro'); if(preview) preview.remove();} const errDiv=document.querySelector('#registrar-empresa-content .alert-danger'); if(errDiv)errDiv.style.display='none'; }); }
    // Validação extra import (se houver)
    const importForm = document.getElementById('importForm'); const fileInput = document.getElementById('csv_file'); if (importForm && fileInput){ /* ... */ }

    // Preview simples para logo no formulário de registro
    const inputLogoReg = document.getElementById('logo');
    const previewContainerReg = document.getElementById('logo-preview-container-registro');
    if (inputLogoReg && previewContainerReg) {
        inputLogoReg.addEventListener('change', function(event) {
            previewContainerReg.innerHTML = ''; // Limpa preview anterior
            const file = event.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.id = 'logo-preview-registro';
                    img.src = e.target.result;
                    img.alt = 'Preview Logo';
                    img.style.maxWidth = '150px'; img.style.maxHeight = '100px'; img.style.objectFit = 'contain';
                    img.classList.add('img-thumbnail', 'p-1');
                    previewContainerReg.appendChild(img);
                }
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>

<?php
echo getFooterAdmin();
?>