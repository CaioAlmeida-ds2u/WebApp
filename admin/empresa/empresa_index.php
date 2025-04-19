<?php
// admin/empresa/empresa_index.php
// Versão completa com busca e outras melhorias

require_once __DIR__ . '/../../includes/config.php';        // Config, DB, CSRF Token, Base URL
require_once __DIR__ . '/../../includes/layout_admin.php';   // Layout unificado (Header/Footer)
require_once __DIR__ . '/../../includes/admin_functions.php'; // Funções admin (formatarCNPJ, etc.)
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
            // $resultado_exclusao = excluirEmpresa($conexao, $empresa_id_excluir); // <-- Chamar a função real aqui
            $resultado_exclusao = false; // Simula falha ou não implementação
            $mensagem_simulada = "(FUNCIONALIDADE EXCLUIR EMPRESA (ID: $empresa_id_excluir) - AINDA NÃO IMPLEMENTADA COMPLETAMENTE NO BACKEND)";

            // if ($resultado_exclusao === true) { // Usar a lógica real depois de implementar
            if ($resultado_exclusao) { // Simulação: Nunca entra aqui se for false
                 $_SESSION['sucesso'] = "Empresa ID $empresa_id_excluir excluída com sucesso! " . $mensagem_simulada;
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_empresa_sucesso', 1, "Exclusão ID: $empresa_id_excluir", $conexao);
            } else {
                 // Tratar mensagem de erro específica da função, se houver (ex: 'Não pode excluir: Usuários vinculados.')
                 // if (is_string($resultado_exclusao)) $_SESSION['erro'] = $resultado_exclusao; else ...
                 $_SESSION['erro'] = "Erro ao excluir empresa ID $empresa_id_excluir. Verifique dependências. " . $mensagem_simulada;
                  dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_empresa_falha', 0, "Falha exclusão ID: $empresa_id_excluir", $conexao);
            }
        } else {
            $_SESSION['erro'] = "ID inválido para exclusão.";
             dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_empresa_falha', 0, "ID inválido fornecido.", $conexao);
        }
    }
    // Redireciona para a lista para mostrar mensagem e evitar reenvio
    header('Location: ' . BASE_URL . 'admin/empresa/empresa_index.php?aba=empresas');
    exit;
}

// --- Paginação e Filtros ---
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina = 10;
$termo_busca = trim(filter_input(INPUT_GET, 'busca', FILTER_SANITIZE_STRING) ?? '');

// --- Obtenção da Lista de Empresas (com busca) ---
$empresa_data = dbGetEmpresas($conexao, $pagina_atual, $itens_por_pagina, $termo_busca);
$lista_empresas = $empresa_data['empresas'] ?? []; // Lista de empresas
$paginacao = $empresa_data['paginacao'] ?? ['total_paginas' => 0, 'pagina_atual' => 1, 'total_itens' => 0]; // Paginação padrão
$erro_busca = $empresa_data['erro'] ?? null; // Mensagem de erro na busca (se houver)


// --- Geração do HTML ---
$title = "ACodITools - Gestão de Empresas";
echo getHeaderAdmin($title); // Layout unificado

// Definir qual aba está ativa
$aba_ativa = $_GET['aba'] ?? 'empresas';
if ($erro_registrar_msg) { $aba_ativa = 'registrar-empresa'; }
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-building me-2"></i>Gestão de Empresas</h1>
        <div class="ms-auto">
            <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex align-items-center">
                <input type="hidden" name="aba" value="empresas"> <?php /* Manter aba ativa na busca */ ?>
                <label for="buscaEmpresa" class="visually-hidden">Buscar Empresa</label>
                <input class="form-control form-control-sm me-2" type="search" id="buscaEmpresa" name="busca" placeholder="Buscar por Nome, CNPJ, Razão..." aria-label="Buscar Empresa" value="<?= htmlspecialchars($termo_busca) ?>">
                <button class="btn btn-sm btn-outline-primary" type="submit" title="Buscar"><i class="fas fa-search"></i></button>
                <?php if (!empty($termo_busca)): ?>
                     <a href="?aba=empresas" class="btn btn-sm btn-outline-secondary ms-1" title="Limpar Busca"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php /* Mensagens gerais */ ?>
    <?php if ($sucesso_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($erro_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
             <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($erro_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($erro_busca): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($erro_busca) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

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
                        <table class="table table-striped table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Nome Fantasia</th>
                                    <th scope="col">CNPJ</th>
                                    <th scope="col">Razão Social</th>
                                    <th scope="col">E-mail</th>
                                    <th scope="col">Telefone</th>
                                    <th scope="col" class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lista_empresas)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted p-4">
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
                                            <td><?= htmlspecialchars($empresa_item['id']) ?></td>
                                            <td><?= htmlspecialchars($empresa_item['nome']) ?></td>
                                            <td><?= function_exists('formatarCNPJ') ? htmlspecialchars(formatarCNPJ($empresa_item['cnpj'])) : htmlspecialchars($empresa_item['cnpj']) ?></td>
                                            <td title="<?= htmlspecialchars($empresa_item['razao_social']) ?>"><?= htmlspecialchars(mb_strimwidth($empresa_item['razao_social'], 0, 40, "...")) ?></td>
                                            <td><a href="mailto:<?= htmlspecialchars($empresa_item['email']) ?>" title="Enviar e-mail para <?= htmlspecialchars($empresa_item['email']) ?>"><?= htmlspecialchars($empresa_item['email']) ?></a></td>
                                            <td><?= htmlspecialchars($empresa_item['telefone']) ?></td>
                                            <td class="text-center">
                                                <div class="d-inline-flex flex-nowrap">
                                                    <a href="<?= BASE_URL ?>admin/empresa/editar_empresa.php?id=<?= $empresa_item['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Editar Empresa"><i class="fas fa-edit"></i></a>
                                                    <form method="POST" action="<?= BASE_URL ?>admin/empresa/empresa_index.php" class="d-inline" onsubmit="return confirm('ATENÇÃO! Excluir a empresa <?= htmlspecialchars(addslashes($empresa_item['nome'])) ?>? Verifique dependências (usuários vinculados). Esta ação não pode ser desfeita.');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="excluir_empresa">
                                                        <input type="hidden" name="id" value="<?= $empresa_item['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir Empresa"><i class="fas fa-trash-alt"></i></button>
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
                 <div class="card-footer bg-light py-2"> <?php /* Padding menor no footer */ ?>
                    <nav aria-label="Paginação de Empresas">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php $link_paginacao = "?aba=empresas&busca=".urlencode($termo_busca)."&pagina="; ?>
                            <?php if ($paginacao['pagina_atual'] > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] - 1) ?>">« Ant</a></li>
                            <?php else: ?><li class="page-item disabled"><span class="page-link">« Ant</span></li><?php endif; ?>
                            <?php
                            $inicio = max(1, $paginacao['pagina_atual'] - 2); $fim = min($paginacao['total_paginas'], $paginacao['pagina_atual'] + 2);
                            if ($inicio > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            for ($i = $inicio; $i <= $fim; $i++): ?><li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="<?= $link_paginacao . $i ?>"><?= $i ?></a></li><?php endfor;
                            if ($fim < $paginacao['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            ?>
                            <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?>
                                <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] + 1) ?>">Próx »</a></li>
                            <?php else: ?><li class="page-item disabled"><span class="page-link">Próx »</span></li><?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
             </div>
        </div>

        <?php /* ----- Aba Registrar Empresa ----- */ ?>
        <div class="tab-pane fade <?= ($aba_ativa === 'registrar-empresa') ? 'show active' : '' ?>" id="registrar-empresa-content" role="tabpanel" aria-labelledby="registrar-empresa-tab">
             <div class="card shadow-sm">
                 <div class="card-header bg-light">
                    <i class="fas fa-plus-circle me-1"></i> Registrar Nova Empresa
                 </div>
                 <div class="card-body">
                     <?php if ($erro_registrar_msg): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $erro_registrar_msg ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>admin/empresa/criar.php" method="POST" id="registerEmpresaForm" class="needs-validation" novalidate>
                         <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div class="row g-3 mb-3"> <?php /* Adicionado mb-3 */ ?>
                            <div class="col-md-6">
                                <label for="nome" class="form-label">Nome Fantasia <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="nome" name="nome" required value="<?= htmlspecialchars($form_data['nome'] ?? '') ?>" maxlength="255">
                                <div class="invalid-feedback">O nome fantasia é obrigatório.</div>
                            </div>
                             <div class="col-md-6">
                                <label for="cnpj" class="form-label">CNPJ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="cnpj" name="cnpj" required placeholder="00.000.000/0000-00" value="<?= htmlspecialchars($form_data['cnpj'] ?? '') ?>">
                                <div class="invalid-feedback">CNPJ inválido ou obrigatório.</div>
                            </div>
                        </div>
                         <div class="mb-3">
                            <label for="razao_social" class="form-label">Razão Social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="razao_social" name="razao_social" required value="<?= htmlspecialchars($form_data['razao_social'] ?? '') ?>" maxlength="255">
                            <div class="invalid-feedback">A razão social é obrigatória.</div>
                        </div>
                         <div class="mb-3">
                             <label for="endereco" class="form-label">Endereço Completo</label>
                             <input type="text" class="form-control form-control-sm" id="endereco" name="endereco" placeholder="Rua, Número, Bairro, Cidade - Estado, CEP" value="<?= htmlspecialchars($form_data['endereco'] ?? '') ?>">
                             <small class="form-text text-muted">Opcional, mas recomendado.</small>
                         </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-sm" id="email" name="email" required value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" maxlength="255">
                                <div class="invalid-feedback">E-mail inválido ou obrigatório.</div>
                            </div>
                             <div class="col-md-6">
                                <label for="telefone" class="form-label">Telefone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="telefone" name="telefone" required value="<?= htmlspecialchars($form_data['telefone'] ?? '') ?>">
                                 <div class="invalid-feedback">O telefone é obrigatório.</div>
                            </div>
                        </div>
                         <div class="mb-3">
                            <label for="contato" class="form-label">Nome do Contato Principal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="contato" name="contato" required value="<?= htmlspecialchars($form_data['contato'] ?? '') ?>" maxlength="255">
                             <div class="invalid-feedback">O nome do contato é obrigatório.</div>
                        </div>

                         <hr> <?php /* Linha separadora antes dos botões */ ?>

                         <div class="d-flex justify-content-end">
                             <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-toggle="tab" data-bs-target="#empresas-content">Cancelar</button>
                             <button type="submit" class="btn btn-success btn-sm">Registrar Empresa</button>
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
        const cnpjInput = document.getElementById('cnpj');
        if (cnpjInput) IMask(cnpjInput, { mask: '00.000.000/0000-00' });
        const telInput = document.getElementById('telefone');
        if (telInput) IMask(telInput, { mask: '(00) 0000[0]-0000' });
    } else {
        console.error('IMask não carregado.');
    }

    // Validação Bootstrap
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Ativar aba correta ao carregar
    const urlParams = new URLSearchParams(window.location.search);
    const abaParam = urlParams.get('aba');
    let targetTabId = 'empresas-content'; // Padrão
    // Força aba de registro se houver erro específico ou se o parâmetro 'aba' for definido
    <?php if ($erro_registrar_msg): ?>
        targetTabId = 'registrar-empresa-content';
    <?php elseif (!empty($abaParam)): ?>
         targetTabId = '<?= $aba_ativa === "registrar-empresa" ? "registrar-empresa-content" : "empresas-content" ?>';
    <?php endif; ?>

    const triggerEl = document.querySelector(`#empresaTabs button[data-bs-target="#${targetTabId}"]`);
    if (triggerEl && bootstrap.Tab.getInstance(triggerEl) === null) { // Evita re-ativar desnecessariamente
        const tab = new bootstrap.Tab(triggerEl);
        tab.show();
    }

     // Adiciona funcionalidade ao botão de "Cancelar" no form de registro
     const cancelButton = document.querySelector('#registerEmpresaForm button[data-bs-target="#empresas-content"]');
     if (cancelButton) {
         cancelButton.addEventListener('click', function() {
             const empresasTabTrigger = document.getElementById('empresas-tab');
             if (empresasTabTrigger) {
                 const tab = new bootstrap.Tab(empresasTabTrigger);
                 tab.show();
             }
             // Limpa o formulário e a validação ao cancelar
             const regForm = document.getElementById('registerEmpresaForm');
             if(regForm) {
                 regForm.reset();
                 regForm.classList.remove('was-validated');
             }
             // Limpa a mensagem de erro de registro, se houver
              const errorDiv = document.querySelector('#registrar-empresa-content .alert-danger');
              if(errorDiv) errorDiv.style.display = 'none';
         });
     }
});
</script>

<?php
// Inclui o Footer
echo getFooterAdmin();
?>