<?php
// admin/admin_gerenciamento_contas_clientes.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Precisará de funções CRUD para empresas com foco SaaS
require_once __DIR__ . '/../includes/funcoes_upload.php'; // Para logo da empresa

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');
$erro_form_msg = $_SESSION['erro_form_cliente'] ?? null; unset($_SESSION['erro_form_cliente']);
$form_data_cliente = $_SESSION['form_data_cliente'] ?? []; unset($_SESSION['form_data_cliente']);

// --- Processamento de Ações POST (Criar/Atualizar/Mudar Status de Contrato) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $action = $_POST['action'] ?? '';
        $cliente_empresa_id_acao = filter_input(INPUT_POST, 'cliente_empresa_id', FILTER_VALIDATE_INT);
        $_SESSION['csrf_token'] = gerar_csrf_token(); // Regenera logo

        switch ($action) {
            case 'registrar_cliente':
                $dados_cliente_form = [
                    'nome_fantasia' => trim($_POST['nome_fantasia_cliente'] ?? ''),
                    'razao_social' => trim($_POST['razao_social_cliente'] ?? ''),
                    'cnpj_cliente' => preg_replace('/[^0-9]/', '', trim($_POST['cnpj_cliente'] ?? '')),
                    'email_contato_cliente' => trim($_POST['email_contato_cliente'] ?? ''),
                    'telefone_contato_cliente' => trim($_POST['telefone_contato_cliente'] ?? ''),
                    'plano_assinatura_id_cliente' => filter_input(INPUT_POST, 'plano_assinatura_id_cliente', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                    'status_contrato_cliente' => $_POST['status_contrato_cliente'] ?? 'Teste', // Default para Teste
                    // Poderia ter mais campos como endereço, admin_principal_da_empresa_email, etc.
                ];
                $logoFileCliente = $_FILES['logo_cliente'] ?? null;

                if (empty($dados_cliente_form['nome_fantasia']) || empty($dados_cliente_form['cnpj_cliente']) || empty($dados_cliente_form['email_contato_cliente']) || empty($dados_cliente_form['plano_assinatura_id_cliente'])) {
                    $_SESSION['erro_form_cliente'] = "Nome Fantasia, CNPJ, E-mail de Contato e Plano de Assinatura são obrigatórios.";
                    $_SESSION['form_data_cliente'] = $_POST;
                } elseif (function_exists('validarCNPJ') && !validarCNPJ($dados_cliente_form['cnpj_cliente'])) {
                    $_SESSION['erro_form_cliente'] = "CNPJ inválido.";
                    $_SESSION['form_data_cliente'] = $_POST;
                } elseif (!filter_var($dados_cliente_form['email_contato_cliente'], FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['erro_form_cliente'] = "E-mail de contato inválido.";
                    $_SESSION['form_data_cliente'] = $_POST;
                }
                else {
                    // Processar upload de logo SE enviado
                    $nome_logo_salvo = null;
                    if ($logoFileCliente && $logoFileCliente['error'] === UPLOAD_ERR_OK) {
                        // Usar uma função adaptada para processar e salvar logo, retornando o nome do arquivo
                        // Esta função deveria salvar em um subdiretório específico para logos de clientes
                        $resultadoUploadLogo = processarUploadLogoEmpresaCliente($logoFileCliente, $conexao); // Função hipotética
                        if ($resultadoUploadLogo['success']) {
                            $nome_logo_salvo = $resultadoUploadLogo['nome_arquivo'];
                        } else {
                            $_SESSION['erro_form_cliente'] = "Erro no upload do logo: " . $resultadoUploadLogo['message'];
                            $_SESSION['form_data_cliente'] = $_POST;
                            // Não prossegue se o upload falhou, a menos que seja opcional
                        }
                    }
                    $dados_cliente_form['logo_cliente_path'] = $nome_logo_salvo;


                    if (empty($_SESSION['erro_form_cliente'])) { // Só continua se não houve erro no logo (se o logo for obrigatório e falhar)
                        // *** Chamar função de backend: registrarNovaEmpresaCliente($conexao, $dados_cliente_form, $_SESSION['usuario_id']) ***
                        $resultado_registro = registrarNovaEmpresaCliente($conexao, $dados_cliente_form, $_SESSION['usuario_id']);
                        if ($resultado_registro['success']) {
                            definir_flash_message('sucesso', "Empresa cliente '".htmlspecialchars($dados_cliente_form['nome_fantasia'])."' registrada com sucesso. ID: " . $resultado_registro['empresa_id']);
                            // Aqui poderia ter um passo para criar o primeiro usuário GESTOR para esta empresa.
                        } else {
                            $_SESSION['erro_form_cliente'] = $resultado_registro['message'] ?? "Erro desconhecido ao registrar a empresa cliente.";
                            $_SESSION['form_data_cliente'] = $_POST;
                            // Se o logo foi salvo e o DB falhou, idealmente remover o arquivo de logo salvo.
                            if ($nome_logo_salvo && UPLOADS_BASE_PATH_ABSOLUTE) { @unlink(rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/logos_clientes/' . $nome_logo_salvo); }
                        }
                    }
                }
                break;

            // Outros cases para ATUALIZAR plano, MUDAR STATUS de contrato, SUSPENDER, CANCELAR
            // Exemplo:
            case 'mudar_status_contrato':
                if ($cliente_empresa_id_acao && isset($_POST['novo_status_contrato'])) {
                    $novo_status = trim($_POST['novo_status_contrato']);
                    // *** Chamar função: mudarStatusContratoEmpresaCliente($conexao, $cliente_empresa_id_acao, $novo_status, $_SESSION['usuario_id']) ***
                    if (mudarStatusContratoEmpresaCliente($conexao, $cliente_empresa_id_acao, $novo_status, $_SESSION['usuario_id'])) {
                        definir_flash_message('sucesso', "Status do contrato da empresa ID $cliente_empresa_id_acao atualizado para '$novo_status'.");
                    } else {
                        definir_flash_message('erro', "Erro ao atualizar status do contrato da empresa ID $cliente_empresa_id_acao.");
                    }
                }
                break;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}


// --- Busca de Dados para Exibição ---
$pagina_atual_clientes = filter_input(INPUT_GET, 'pagina_clientes', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina_clientes = 15;
$filtros_clientes = [
    'busca_cliente' => trim(filter_input(INPUT_GET, 'busca_cliente', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''),
    'plano_id_filtro' => filter_input(INPUT_GET, 'plano_id_filtro', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
    'status_contrato_filtro' => trim(filter_input(INPUT_GET, 'status_contrato_filtro', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'todos')
];

// *** Chamar função de backend: listarEmpresasClientesPaginado($conexao, $pagina_atual_clientes, $itens_por_pagina_clientes, $filtros_clientes) ***
$clientes_data = listarEmpresasClientesPaginado($conexao, $pagina_atual_clientes, $itens_por_pagina_clientes, $filtros_clientes); // Função a ser criada
$lista_clientes = $clientes_data['empresas_clientes'] ?? [];
$paginacao_clientes = $clientes_data['paginacao'] ?? ['total_paginas' => 0, 'pagina_atual' => 1, 'total_itens' => 0];

// Buscar planos para o dropdown de filtro e formulário
// *** Chamar função de backend: listarPlanosAssinatura($conexao, true) // true para apenas ativos ***
$planos_assinatura_ativos = listarPlanosAssinatura($conexao, true); // Função a ser criada (pode ser a mesma usada na pag. de planos)

// Status de contrato possíveis para o filtro/dropdown
$status_contrato_possiveis = ['Teste', 'Ativo', 'Inadimplente', 'Suspenso', 'Cancelado'];


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($erro_form_msg)) { // Evita regenerar em POST com erro de validação
    $_SESSION['csrf_token'] = gerar_csrf_token();
}
$csrf_token_page = $_SESSION['csrf_token'];


// --- Geração do HTML ---
$title = "ACodITools - Gerenciamento de Empresas Clientes";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-city me-2"></i>Gerenciamento de Empresas Clientes</h1>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRegistrarCliente" aria-expanded="<?= !empty($erro_form_msg) ? 'true' : 'false' ?>" aria-controls="collapseRegistrarCliente">
            <i class="fas fa-plus me-1"></i> Registrar Nova Empresa Cliente
        </button>
    </div>

    <?php if ($sucesso_msg): /* ... Mensagens de sucesso ... */ endif; ?>
    <?php if ($erro_msg): /* ... Mensagens de erro ... */ endif; ?>

    <!-- Card para Registrar Novo Cliente (Colapsável) -->
    <div class="collapse <?= !empty($erro_form_msg) ? 'show' : '' ?> mb-4" id="collapseRegistrarCliente">
        <div class="card shadow-sm border-start border-primary border-3">
            <div class="card-header bg-light"><i class="fas fa-user-plus me-1"></i> Registrar Nova Empresa Cliente</div>
            <div class="card-body p-4">
                <?php if ($erro_form_msg): ?>
                    <div class="alert alert-warning small p-2" role="alert"><?= $erro_form_msg ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="registerClienteForm" class="needs-validation" novalidate enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                    <input type="hidden" name="action" value="registrar_cliente">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nome_fantasia_cliente" class="form-label form-label-sm fw-semibold">Nome Fantasia <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="nome_fantasia_cliente" name="nome_fantasia_cliente" value="<?= htmlspecialchars($form_data_cliente['nome_fantasia_cliente'] ?? '') ?>" required>
                            <div class="invalid-feedback">Nome Fantasia é obrigatório.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="razao_social_cliente" class="form-label form-label-sm fw-semibold">Razão Social</label>
                            <input type="text" class="form-control form-control-sm" id="razao_social_cliente" name="razao_social_cliente" value="<?= htmlspecialchars($form_data_cliente['razao_social_cliente'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="cnpj_cliente" class="form-label form-label-sm fw-semibold">CNPJ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="cnpj_cliente" name="cnpj_cliente" value="<?= htmlspecialchars($form_data_cliente['cnpj_cliente'] ?? '') ?>" required placeholder="00.000.000/0000-00">
                            <div class="invalid-feedback">CNPJ obrigatório e válido.</div>
                        </div>
                         <div class="col-md-4">
                            <label for="email_contato_cliente" class="form-label form-label-sm fw-semibold">E-mail Principal Contato <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-sm" id="email_contato_cliente" name="email_contato_cliente" value="<?= htmlspecialchars($form_data_cliente['email_contato_cliente'] ?? '') ?>" required>
                            <div class="invalid-feedback">E-mail de contato inválido/obrigatório.</div>
                        </div>
                         <div class="col-md-4">
                            <label for="telefone_contato_cliente" class="form-label form-label-sm fw-semibold">Telefone Contato</label>
                            <input type="text" class="form-control form-control-sm" id="telefone_contato_cliente" name="telefone_contato_cliente" value="<?= htmlspecialchars($form_data_cliente['telefone_contato_cliente'] ?? '') ?>" placeholder="(00) 00000-0000">
                        </div>
                        <div class="col-md-6">
                            <label for="plano_assinatura_id_cliente" class="form-label form-label-sm fw-semibold">Plano de Assinatura <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="plano_assinatura_id_cliente" name="plano_assinatura_id_cliente" required>
                                <option value="">-- Selecione um Plano --</option>
                                <?php foreach($planos_assinatura_ativos as $plano): ?>
                                    <option value="<?= $plano['id'] ?>" <?= (isset($form_data_cliente['plano_assinatura_id_cliente']) && $form_data_cliente['plano_assinatura_id_cliente'] == $plano['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($plano['nome_plano']) ?> (R$ <?= htmlspecialchars(number_format($plano['preco_mensal'] ?? 0, 2, ',', '.')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione um plano.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="status_contrato_cliente" class="form-label form-label-sm fw-semibold">Status do Contrato <span class="text-danger">*</span></label>
                             <select class="form-select form-select-sm" id="status_contrato_cliente" name="status_contrato_cliente" required>
                                <?php foreach ($status_contrato_possiveis as $status_opt): ?>
                                <option value="<?= $status_opt ?>" <?= (isset($form_data_cliente['status_contrato_cliente']) && $form_data_cliente['status_contrato_cliente'] == $status_opt) ? 'selected' : ($status_opt === 'Teste' && !isset($form_data_cliente['status_contrato_cliente']) ? 'selected' : '') ?>>
                                    <?= htmlspecialchars($status_opt) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione um status para o contrato.</div>
                        </div>
                         <div class="col-12">
                             <label for="logo_cliente" class="form-label form-label-sm fw-semibold">Logo da Empresa Cliente (Opcional)</label>
                             <input class="form-control form-control-sm" type="file" id="logo_cliente" name="logo_cliente" accept="image/jpeg, image/png, image/gif, image/svg+xml">
                             <small class="form-text text-muted">Envie JPG, PNG, GIF, SVG (máx <?= MAX_UPLOAD_SIZE_MB ?>MB).</small>
                         </div>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-toggle="collapse" data-bs-target="#collapseRegistrarCliente">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle me-1"></i> Registrar Cliente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Card Lista de Clientes -->
    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="mb-0 fw-bold d-flex align-items-center"><i class="fas fa-list-ul me-2 text-primary opacity-75"></i>Empresas Clientes Cadastradas</h6>
            <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex ms-auto small align-items-center gap-2 mt-2 mt-md-0" id="formFiltroClientes">
                <input type="search" name="busca_cliente" class="form-control form-control-sm" placeholder="Buscar por Nome, CNPJ..." value="<?= htmlspecialchars($filtros_clientes['busca_cliente']) ?>" style="max-width: 200px;">
                <select name="plano_id_filtro" class="form-select form-select-sm" style="max-width: 180px;">
                    <option value="">Todos os Planos</option>
                    <?php foreach ($planos_assinatura_ativos as $plano_f): ?>
                        <option value="<?= $plano_f['id'] ?>" <?= ($filtros_clientes['plano_id_filtro'] == $plano_f['id']) ? 'selected' : '' ?>><?= htmlspecialchars($plano_f['nome_plano']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status_contrato_filtro" class="form-select form-select-sm" style="max-width: 150px;">
                    <option value="todos" <?= ($filtros_clientes['status_contrato_filtro'] === 'todos') ? 'selected' : '' ?>>Todos Status</option>
                    <?php foreach($status_contrato_possiveis as $status_f): ?>
                    <option value="<?= $status_f ?>" <?= ($filtros_clientes['status_contrato_filtro'] === $status_f) ? 'selected' : '' ?>><?= htmlspecialchars($status_f) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <?php if (!empty($filtros_clientes['busca_cliente']) || !empty($filtros_clientes['plano_id_filtro']) || $filtros_clientes['status_contrato_filtro'] !== 'todos'): ?>
                    <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="btn btn-sm btn-outline-secondary ms-1" title="Limpar Filtros"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 5%;">Logo</th>
                            <th>Nome Fantasia / Razão Social</th>
                            <th>CNPJ</th>
                            <th>Plano</th>
                            <th class="text-center">Status Contrato</th>
                            <th class="text-center">Usuários</th> <?php /* Contagem de usuários da empresa */ ?>
                            <th class="text-center" style="width: 15%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_clientes)): ?>
                            <tr><td colspan="8" class="text-center text-muted p-4"><i class="fas fa-info-circle d-block fs-4 mb-2 opacity-50"></i>Nenhuma empresa cliente encontrada<?= (!empty(array_filter($filtros_clientes))) ? ' com os filtros aplicados.' : '.' ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_clientes as $cliente): ?>
                                <?php
                                $logoClienteSrc = BASE_URL . 'assets/img/default-logo-sm.png';
                                if (!empty($cliente['logo_cliente_path']) && UPLOADS_BASE_PATH_ABSOLUTE && file_exists(rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/logos_clientes/' . $cliente['logo_cliente_path'])) {
                                    $logoClienteSrc = BASE_URL . 'uploads/logos_clientes/' . $cliente['logo_cliente_path'] . '?t=' . filemtime(rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/logos_clientes/' . $cliente['logo_cliente_path']);
                                }
                                ?>
                                <tr>
                                    <td class="fw-bold">#<?= htmlspecialchars($cliente['id']) ?></td>
                                    <td><img src="<?= $logoClienteSrc ?>" alt="Logo" style="width: 30px; height: 30px; object-fit: contain; background-color: #f8f9fa;" class="img-thumbnail p-0 rounded-circle"></td>
                                    <td>
                                        <?= htmlspecialchars($cliente['nome_fantasia']) ?>
                                        <small class="d-block text-muted"><?= htmlspecialchars($cliente['razao_social'] ?? '') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars(function_exists('formatarCNPJ') ? formatarCNPJ($cliente['cnpj_cliente']) : $cliente['cnpj_cliente']) ?></td>
                                    <td><span class="badge bg-info-subtle text-info-emphasis"><?= htmlspecialchars($cliente['nome_plano_assinatura'] ?? 'N/A') ?></span></td>
                                    <td class="text-center">
                                        <?php $statusClass = 'bg-secondary';
                                        if($cliente['status_contrato_cliente'] === 'Ativo') $statusClass = 'bg-success';
                                        else if($cliente['status_contrato_cliente'] === 'Teste') $statusClass = 'bg-primary';
                                        else if($cliente['status_contrato_cliente'] === 'Suspenso') $statusClass = 'bg-warning text-dark';
                                        else if($cliente['status_contrato_cliente'] === 'Cancelado') $statusClass = 'bg-danger';
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($cliente['status_contrato_cliente']) ?></span>
                                    </td>
                                    <td class="text-center"><?= htmlspecialchars($cliente['total_usuarios_vinculados'] ?? 0) ?></td>
                                    <td class="text-center action-buttons-table">
                                        <div class="d-inline-flex flex-nowrap">
                                            <a href="<?= BASE_URL ?>admin/admin_editar_conta_cliente.php?id=<?= $cliente['id'] ?>" class="btn btn-sm btn-outline-primary me-1 action-btn" title="Editar Dados do Cliente e Plano"><i class="fas fa-edit fa-fw"></i></a>
                                            <a href="<?= BASE_URL ?>admin/usuarios.php?empresa_id_filtro=<?= $cliente['id'] ?>" class="btn btn-sm btn-outline-info me-1 action-btn" title="Gerenciar Usuários deste Cliente"><i class="fas fa-users fa-fw"></i></a>
                                            <?php // Ação de Mudar Status do Contrato (ex: Suspender/Reativar)
                                            // Poderia ser um modal ou outra página
                                            ?>
                                            <!-- <button type="button" class="btn btn-sm btn-outline-warning action-btn" title="Alterar Status Contrato"><i class="fas fa-power-off fa-fw"></i></button> -->
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (isset($paginacao_clientes) && $paginacao_clientes['total_paginas'] > 1): ?>
        <div class="card-footer bg-light py-2">
            <nav aria-label="Paginação de Empresas Clientes">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php
                        $params_pag = $filtros_clientes; unset($params_pag['pagina_clientes']);
                        $link_pag_cli = "?" . http_build_query($params_pag) . "&pagina_clientes=";
                    ?>
                    <?php if ($paginacao_clientes['pagina_atual'] > 1): ?><li class="page-item"><a class="page-link" href="<?= $link_pag_cli . ($paginacao_clientes['pagina_atual'] - 1) ?>">«</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">«</span></li><?php endif; ?>
                    <?php $inicio_pc = max(1, $paginacao_clientes['pagina_atual'] - 2); $fim_pc = min($paginacao_clientes['total_paginas'], $paginacao_clientes['pagina_atual'] + 2); if ($inicio_pc > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; for ($i = $inicio_pc; $i <= $fim_pc; $i++): ?><li class="page-item <?= ($i == $paginacao_clientes['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="<?= $link_pag_cli . $i ?>"><?= $i ?></a></li><?php endfor; if ($fim_pc < $paginacao_clientes['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                    <?php if ($paginacao_clientes['pagina_atual'] < $paginacao_clientes['total_paginas']): ?><li class="page-item"><a class="page-link" href="<?= $link_pag_cli . ($paginacao_clientes['pagina_atual'] + 1) ?>">»</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">»</span></li><?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/imask/7.1.3/imask.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscaras para CNPJ e Telefone no formulário de registro
    const cnpjInputReg = document.getElementById('cnpj_cliente');
    if (cnpjInputReg && typeof IMask !== 'undefined') { IMask(cnpjInputReg, { mask: '00.000.000/0000-00' }); }
    const telInputReg = document.getElementById('telefone_contato_cliente');
    if (telInputReg && typeof IMask !== 'undefined') { IMask(telInputReg, { mask: '(00) 0000[0]-0000' }); }

    // Validação Bootstrap
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Foco no campo nome fantasia ao mostrar o form de registro por erro
    <?php if ($erro_form_msg): ?>
        const collapseCliente = document.getElementById('collapseRegistrarCliente');
        if (collapseCliente) {
            const bsCollapse = new bootstrap.Collapse(collapseCliente, { toggle: false });
            bsCollapse.show();
            const nomeFantasiaInput = document.getElementById('nome_fantasia_cliente');
            if (nomeFantasiaInput) { setTimeout(() => nomeFantasiaInput.focus(), 200); }
        }
    <?php endif; ?>
});
</script>

<?php
echo getFooterAdmin();
?>