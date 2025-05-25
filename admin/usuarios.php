<?php
// admin/usuarios.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// --- Processamento de Ações POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $_SESSION['csrf_token'] = gerar_csrf_token();
    $action_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $action = $_POST['action'];
    $log_details = $action_id ? "ID Usuário/Solicitação: $action_id" : "Ação $action";

    if (!$action_id && !in_array($action, [])) {
        definir_flash_message('erro', "ID inválido para a ação '$action'.");
    } else {
        switch ($action) {
            case 'ativar_usuario':
                if (ativarUsuario($conexao, $action_id)) {
                    definir_flash_message('sucesso', "Usuário ID $action_id ativado!");
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_usuario', 1, $log_details, $conexao);
                } else {
                    definir_flash_message('erro', "Erro ao ativar usuário ID $action_id.");
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_usuario_falha', 0, $log_details, $conexao);
                }
                break;
            case 'desativar_usuario':
                if ($action_id == $_SESSION['usuario_id']) {
                    definir_flash_message('erro', "Você não pode desativar sua própria conta.");
                } elseif (desativarUsuario($conexao, $action_id)) {
                    definir_flash_message('sucesso', "Usuário ID $action_id desativado!");
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_usuario', 1, $log_details, $conexao);
                } else {
                    definir_flash_message('erro', "Erro ao desativar usuário ID $action_id.");
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_usuario_falha', 0, $log_details, $conexao);
                }
                break;
            case 'excluir_usuario':
                if ($action_id == $_SESSION['usuario_id']) {
                    definir_flash_message('erro', "Você não pode excluir sua própria conta.");
                } elseif (excluirUsuario($conexao, $action_id)) {
                    definir_flash_message('sucesso', "Usuário ID $action_id excluído!");
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_usuario', 1, $log_details, $conexao);
                } else {
                    definir_flash_message('erro', "Erro ao excluir usuário ID $action_id. Verifique dependências.");
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_usuario_falha', 0, $log_details, $conexao);
                }
                break;
            case 'aprovar_acesso':
                $senha_temporaria = bin2hex(random_bytes(4));
                $resultado_aprov = aprovarSolicitacaoAcesso($conexao, $action_id, $senha_temporaria);
                if ($resultado_aprov === true) {
                    definir_flash_message('sucesso', "Solicitação ID $action_id aprovada! Senha temporária: " . htmlspecialchars($senha_temporaria));
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'aprovar_acesso', 1, $log_details, $conexao);
                } else {
                    $msgErroAprov = is_string($resultado_aprov) ? $resultado_aprov : "Erro ao aprovar a solicitação de acesso ID $action_id.";
                    definir_flash_message('erro', $msgErroAprov);
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'aprovar_acesso_falha', 0, $log_details . ". Erro: " . $msgErroAprov, $conexao);
                }
                break;
            case 'rejeitar_acesso':
                if (rejeitarSolicitacaoAcesso($conexao, $action_id)) {
                    definir_flash_message('sucesso', "Solicitação de acesso ID $action_id rejeitada.");
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'rejeitar_acesso', 1, $log_details, $conexao);
                } else {
                    definir_flash_message('erro', "Erro ao rejeitar solicitação de acesso ID $action_id.");
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'rejeitar_acesso_falha', 0, $log_details, $conexao);
                }
                break;
            case 'redefinir_senha':
                $senha_temporaria_reset = bin2hex(random_bytes(8));
                if (aprovarESetarSenhaTemp($conexao, $action_id, $senha_temporaria_reset, $_SESSION['usuario_id'])) {
                    definir_flash_message('sucesso', "Senha redefinida para solicitação ID $action_id! Senha temporária: " . htmlspecialchars($senha_temporaria_reset));
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'redefinir_senha_aprov', 1, $log_details, $conexao);
                } else {
                    definir_flash_message('erro', "Erro ao redefinir senha para solicitação ID $action_id.");
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'redefinir_senha_falha', 0, $log_details, $conexao);
                }
                break;
            case 'rejeitar_reset':
                if (rejeitarSolicitacaoReset($conexao, $action_id)) {
                    definir_flash_message('sucesso', "Solicitação de reset ID $action_id rejeitada.");
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'rejeitar_reset_senha', 1, $log_details, $conexao);
                } else {
                    definir_flash_message('erro', "Erro ao rejeitar solicitação de reset ID $action_id.");
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'rejeitar_reset_falha', 0, $log_details, $conexao);
                }
                break;
            default:
                definir_flash_message('erro', "Ação desconhecida.");
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'acao_usuario_desconhecida', 0, 'Ação: ' . htmlspecialchars($action), $conexao);
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Paginação e Filtros para USUÁRIOS ---
$pagina_atual_usr = filter_input(INPUT_GET, 'pagina_usr', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina_usr = 15;

$default_filtros_get_usuarios = [
    'tipo_usuario' => 'todos',
    'empresa_id' => null,
    'perfil' => null,
    'status_ativo' => null,
    'termo_busca' => ''
];

$status_filtro_raw = filter_input(INPUT_GET, 'status_usr_filtro', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
$status_ativo = null; // Padrão explícito
if ($status_filtro_raw === 0 || $status_filtro_raw === 1) {
    $status_ativo = $status_filtro_raw;
} 

$filtros_get_usuarios = [
    'tipo_usuario' => filter_input(INPUT_GET, 'tipo_usuario_filtro', FILTER_SANITIZE_SPECIAL_CHARS) ?? $default_filtros_get_usuarios['tipo_usuario'],
    'empresa_id' => filter_input(INPUT_GET, 'empresa_id_filtro_usr', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $default_filtros_get_usuarios['empresa_id'],
    'perfil' => filter_input(INPUT_GET, 'perfil_filtro_usr', FILTER_SANITIZE_SPECIAL_CHARS) ?: $default_filtros_get_usuarios['perfil'],
    'status_ativo' => $status_ativo,
    'termo_busca' => trim(filter_input(INPUT_GET, 'busca_usuario', FILTER_SANITIZE_SPECIAL_CHARS) ?? $default_filtros_get_usuarios['termo_busca'])
];

// Ajuste para status_ativo: string vazia do select ("Todos Status") deve ser null
if ($filtros_get_usuarios['status_ativo'] === '') {
    $filtros_get_usuarios['status_ativo'] = null;
}

// Depuração: Logar os filtros recebidos
error_log("Filtros recebidos em usuarios.php: " . print_r($filtros_get_usuarios, true));

// Ajuste na lógica de exclusão do admin próprio
$excluir_admin_proprio = ($_SESSION['usuario_id'] && empty($filtros_get_usuarios['termo_busca']) && $filtros_get_usuarios['tipo_usuario'] !== 'plataforma');

// Chamar getUsuarios com os filtros
$usuarios_data = getUsuarios(
    $conexao,
    $excluir_admin_proprio ? $_SESSION['usuario_id'] : null,
    $pagina_atual_usr,
    $itens_por_pagina_usr,
    $filtros_get_usuarios
); 

$usuarios = $usuarios_data['usuarios'];
$paginacao_usuarios = $usuarios_data['paginacao'];

$empresas_filtro = getEmpresasAtivas($conexao);
$perfis_disponiveis_db = ['admin', 'gestor_empresa', 'auditor_empresa', 'auditado_contato'];

$solicitacoes_acesso = getSolicitacoesAcessoPendentes($conexao);
$solicitacoes_reset = getSolicitacoesResetPendentes($conexao);

$title = "ACodITools - Gestão de Usuários e Solicitações";
$csrf_token_page = $_SESSION['csrf_token'];
echo getHeaderAdmin($title);

$aba_ativa = $_GET['view'] ?? 'usuarios-cadastrados-tab';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-users-cog me-2"></i>Gestão de Usuários e Solicitações</h1>
        <a href="<?= BASE_URL ?>admin/editar_usuario.php" class="btn btn-sm btn-success">
            <i class="fas fa-user-shield me-1"></i> Novo Usuário da Ferramenta
        </a>
    </div>

    <?php if ($sucesso_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($sucesso_msg) ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($erro_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $erro_msg ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3" id="adminUserTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($aba_ativa === 'usuarios-cadastrados-tab') ? 'active' : '' ?>" id="usuarios-cadastrados-tab" data-bs-toggle="tab" data-bs-target="#usuarios-cadastrados-content" type="button" role="tab" aria-controls="usuarios-cadastrados-content" aria-selected="<?= ($aba_ativa === 'usuarios-cadastrados-tab') ? 'true' : 'false' ?>">
                <i class="fas fa-users me-1"></i>Usuários Cadastrados <span class="badge bg-secondary ms-1"><?= $paginacao_usuarios['total_itens'] ?? 0 ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link position-relative <?= ($aba_ativa === 'solicitacoes-acesso-tab') ? 'active' : '' ?>" id="solicitacoes-acesso-tab" data-bs-toggle="tab" data-bs-target="#solicitacoes-acesso-content" type="button" role="tab" aria-controls="solicitacoes-acesso-content" aria-selected="<?= ($aba_ativa === 'solicitacoes-acesso-tab') ? 'true' : 'false' ?>">
                <i class="fas fa-user-plus me-1"></i>Solicitações de Acesso
                <?php if (count($solicitacoes_acesso) > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= count($solicitacoes_acesso) ?><span class="visually-hidden">pendentes</span></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link position-relative <?= ($aba_ativa === 'solicitacoes-reset-tab') ? 'active' : '' ?>" id="solicitacoes-reset-tab" data-bs-toggle="tab" data-bs-target="#solicitacoes-reset-content" type="button" role="tab" aria-controls="solicitacoes-reset-content" aria-selected="<?= ($aba_ativa === 'solicitacoes-reset-tab') ? 'true' : 'false' ?>">
                <i class="fas fa-key me-1"></i>Solicitações de Senha
                <?php if (count($solicitacoes_reset) > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark"><?= count($solicitacoes_reset) ?><span class="visually-hidden">pendentes</span></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminUserTabsContent">
        <div class="tab-pane fade <?= ($aba_ativa === 'usuarios-cadastrados-tab') ? 'show active' : '' ?>" id="usuarios-cadastrados-content" role="tabpanel" aria-labelledby="usuarios-cadastrados-tab">
            <div class="card shadow-sm rounded-3 border-0">
                <div class="card-header bg-light border-bottom pt-3 pb-2">
                    <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" id="formFiltroUsuarios">
                        <input type="hidden" name="view" value="usuarios-cadastrados-tab">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3 col-lg-2">
                                <label for="tipo_usuario_filtro_form" class="form-label form-label-sm">Tipo de Usuário:</label>
                                <select name="tipo_usuario_filtro" id="tipo_usuario_filtro_form" class="form-select form-select-sm">
                                    <option value="todos" <?= ($filtros_get_usuarios['tipo_usuario'] === 'todos') ? 'selected' : '' ?>>Todos</option>
                                    <option value="plataforma" <?= ($filtros_get_usuarios['tipo_usuario'] === 'plataforma') ? 'selected' : '' ?>>Admins Plataforma</option>
                                    <option value="clientes" <?= ($filtros_get_usuarios['tipo_usuario'] === 'clientes') ? 'selected' : '' ?>>Usuários Clientes</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-lg-3" id="filtro_empresa_usr_div_container_form" style="<?= ($filtros_get_usuarios['tipo_usuario'] !== 'clientes') ? 'display:none;' : '' ?>">
                                <label for="empresa_id_filtro_usr_form" class="form-label form-label-sm">Empresa Cliente:</label>
                                <select name="empresa_id_filtro_usr" id="empresa_id_filtro_usr_form" class="form-select form-select-sm">
                                    <option value="">Todas Empresas Clientes</option>
                                    <?php foreach ($empresas_filtro as $emp_f): ?>
                                        <option value="<?= $emp_f['id'] ?>" <?= ($filtros_get_usuarios['empresa_id'] == $emp_f['id']) ? 'selected' : '' ?>><?= htmlspecialchars($emp_f['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-lg-2">
                                <label for="perfil_filtro_usr_form" class="form-label form-label-sm">Perfil:</label>
                                <select name="perfil_filtro_usr" id="perfil_filtro_usr_form" class="form-select form-select-sm">
                                    <option value="">Todos Perfis</option>
                                    <?php foreach ($perfis_disponiveis_db as $p_disp_val): ?>
                                        <option value="<?= $p_disp_val ?>" <?= ($filtros_get_usuarios['perfil'] === $p_disp_val) ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $p_disp_val)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-lg-2">
                                <label for="status_usr_filtro_form" class="form-label form-label-sm">Status:</label>
                                <select name="status_usr_filtro" id="status_usr_filtro_form" class="form-select form-select-sm">
                                    <option value="" <?= ($filtros_get_usuarios['status_ativo'] === null) ? 'selected' : '' ?>>Todos Status</option>
                                    <option value="1" <?= ($filtros_get_usuarios['status_ativo'] === 1) ? 'selected' : '' ?>>Ativos</option>
                                    <option value="0" <?= ($filtros_get_usuarios['status_ativo'] === 0 && $filtros_get_usuarios['status_ativo'] !== null) ? 'selected' : '' ?>>Inativos</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-lg-3">
                                <label for="busca_usuario_filtro_form" class="form-label form-label-sm">Buscar Usuário:</label>
                                <input type="search" name="busca_usuario" id="busca_usuario_filtro_form" class="form-control form-control-sm" placeholder="Nome ou E-mail..." value="<?= htmlspecialchars($filtros_get_usuarios['termo_busca']) ?>">
                            </div>
                            <div class="col-md-auto mt-3 mt-md-0 d-flex">
                                <button type="submit" class="btn btn-sm btn-primary flex-grow-1 me-1"><i class="fas fa-filter me-1"></i>Filtrar</button>
                                <a href="?view=usuarios-cadastrados-tab" class="btn btn-sm btn-outline-secondary flex-grow-1" title="Limpar Filtros"><i class="fas fa-times"></i></a>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0 align-middle">
                            <thead class="table-light small text-uppercase text-muted">
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Perfil</th>
                                    <th>Empresa Cliente</th>
                                    <th>Admin Empresa</th>
                                    <th class="text-center">Status</th>
                                    <th>Cadastro</th>
                                    <th>Último Login</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted p-4">
                                            Nenhum usuário encontrado
                                            <?php
                                            $filtros_aplicados = array_filter($filtros_get_usuarios, function ($value) {
                                                return $value !== null && $value !== '' && $value !== 'todos';
                                            });
                                            if (!empty($filtros_aplicados)) {
                                                echo ' com os filtros aplicados: ';
                                                $filtro_desc = [];
                                                if (!empty($filtros_get_usuarios['tipo_usuario']) && $filtros_get_usuarios['tipo_usuario'] !== 'todos') {
                                                    $filtro_desc[] = 'Tipo: ' . ($filtros_get_usuarios['tipo_usuario'] === 'plataforma' ? 'Admins Plataforma' : 'Clientes');
                                                }
                                                if (!empty($filtros_get_usuarios['empresa_id'])) {
                                                    $empresa_nome = array_reduce($empresas_filtro, function ($carry, $emp) use ($filtros_get_usuarios) {
                                                        return $emp['id'] == $filtros_get_usuarios['empresa_id'] ? $emp['nome'] : $carry;
                                                    }, 'Desconhecida');
                                                    $filtro_desc[] = 'Empresa: ' . htmlspecialchars($empresa_nome);
                                                }
                                                if (!empty($filtros_get_usuarios['perfil'])) {
                                                    $filtro_desc[] = 'Perfil: ' . ucfirst(str_replace('_', ' ', $filtros_get_usuarios['perfil']));
                                                }
                                                if ($filtros_get_usuarios['status_ativo'] !== null) {
                                                    $filtro_desc[] = 'Status: ' . ($filtros_get_usuarios['status_ativo'] ? 'Ativo' : 'Inativo');
                                                }
                                                if (!empty($filtros_get_usuarios['termo_busca'])) {
                                                    $filtro_desc[] = 'Busca: "' . htmlspecialchars($filtros_get_usuarios['termo_busca']) . '"';
                                                }
                                                echo implode(', ', $filtro_desc) . '.';
                                            } else {
                                                echo '.';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr class="<?= (!$usuario['ativo']) ? 'table-light text-muted opacity-75' : '' ?>">
                                            <td class="fw-bold">#<?= htmlspecialchars($usuario['id']) ?></td>
                                            <td>
                                                <img src="<?= (!empty($usuario['foto'])) ? BASE_URL . 'Uploads/fotos/' . $usuario['foto'] . '?t=' . time() : BASE_URL . 'assets/img/default_profile.png' ?>" alt="Foto" class="rounded-circle me-2" style="width:30px; height:30px; object-fit:cover; border: 1px solid #eee;">
                                                <?= htmlspecialchars($usuario['nome']) ?>
                                            </td>
                                            <td><a href="mailto:<?= htmlspecialchars($usuario['email']) ?>"><?= htmlspecialchars($usuario['email']) ?></a></td>
                                            <td><span class="badge bg-secondary-subtle text-secondary-emphasis border"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $usuario['perfil']))) ?></span></td>
                                            <td>
                                                <?php if ($usuario['empresa_id']): ?>
                                                    <a href="<?= BASE_URL ?>admin/admin_editar_conta_cliente.php?id=<?= $usuario['empresa_id'] ?>" class="text-decoration-none small" title="Ver empresa: <?= htmlspecialchars($usuario['nome_empresa'] ?? 'Empresa ID: ' . $usuario['empresa_id']) ?>">
                                                        <i class="fas fa-city fa-xs me-1 text-muted"></i><?= htmlspecialchars(mb_strimwidth($usuario['nome_empresa'] ?? 'ID: ' . $usuario['empresa_id'], 0, 25, "...")) ?>
                                                    </a>
                                                <?php elseif ($usuario['perfil'] === 'admin'): ?>
                                                    <span class="text-muted small fst-italic">AcodITools (Plataforma)</span>
                                                <?php else: ?>
                                                    <span class="text-muted small"><em>N/A</em></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($usuario['is_empresa_admin_cliente']): ?>
                                                    <span class="badge bg-primary-subtle text-primary-emphasis">Sim</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted">Não</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge rounded-pill <?= $usuario['ativo'] ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
                                                    <?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted"><?= htmlspecialchars((new DateTime($usuario['data_cadastro']))->format('d/m/y')) ?></td>
                                            <td class="small text-muted">
                                                <?= $usuario['data_ultimo_login'] ? htmlspecialchars((new DateTime($usuario['data_ultimo_login']))->format('d/m/y H:i')) : '<em>Nunca</em>' ?>
                                            </td>
                                            <td class="text-center action-buttons-table">
                                                <div class="d-inline-flex flex-nowrap">
                                                    <a href="<?= BASE_URL ?>admin/editar_usuario.php?id=<?= $usuario['id'] ?>" class="btn btn-sm btn-outline-primary me-1 action-btn" title="Editar Usuário"><i class="fas fa-edit fa-fw"></i></a>
                                                    <?php if ($usuario['ativo']): ?>
                                                        <form method="POST" action="<?= strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(array_merge(['view' => 'usuarios-cadastrados-tab'], array_filter($filtros_get_usuarios, function ($v) { return $v !== null && $v !== ''; }), ['pagina_usr' => $pagina_atual_usr])) ?>" class="d-inline me-1" onsubmit="return confirm('DESATIVAR este usuário?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                                                            <input type="hidden" name="action" value="desativar_usuario">
                                                            <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning action-btn" title="Desativar"><i class="fas fa-user-slash fa-fw"></i></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" action="<?= strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(array_merge(['view' => 'usuarios-cadastrados-tab'], array_filter($filtros_get_usuarios, function ($v) { return $v !== null && $v !== ''; }), ['pagina_usr' => $pagina_atual_usr])) ?>" class="d-inline me-1" onsubmit="return confirm('ATIVAR este usuário?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                                                            <input type="hidden" name="action" value="ativar_usuario">
                                                            <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success action-btn" title="Ativar"><i class="fas fa-user-check fa-fw"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" action="<?= strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(array_merge(['view' => 'usuarios-cadastrados-tab'], array_filter($filtros_get_usuarios, function ($v) { return $v !== null && $v !== ''; }), ['pagina_usr' => $pagina_atual_usr])) ?>" class="d-inline" onsubmit="return confirm('EXCLUIR PERMANENTEMENTE este usuário?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                                                        <input type="hidden" name="action" value="excluir_usuario">
                                                        <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Excluir"><i class="fas fa-trash-alt fa-fw"></i></button>
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
                <?php if ($paginacao_usuarios['total_paginas'] > 1): ?>
                    <div class="card-footer bg-light py-2">
                        <nav aria-label="Paginação de Usuários">
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <?php
                                $params_pag_query_base = array_merge(['view' => 'usuarios-cadastrados-tab'], array_filter($filtros_get_usuarios, function ($value) {
                                    return $value !== null && $value !== '';
                                }));
                                $link_pag_usuarios = "?" . http_build_query($params_pag_query_base) . "&pagina_usr=";
                                ?>
                                <?php if ($paginacao_usuarios['pagina_atual'] > 1): ?>
                                    <li class="page-item"><a class="page-link" href="<?= $link_pag_usuarios . ($paginacao_usuarios['pagina_atual'] - 1) ?>">«</a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link">«</span></li>
                                <?php endif; ?>
                                <?php
                                $inicio_pu = max(1, $paginacao_usuarios['pagina_atual'] - 2);
                                $fim_pu = min($paginacao_usuarios['total_paginas'], $paginacao_usuarios['pagina_atual'] + 2);
                                if ($inicio_pu > 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                for ($i = $inicio_pu; $i <= $fim_pu; $i++): ?>
                                    <li class="page-item <?= ($i == $paginacao_usuarios['pagina_atual']) ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $link_pag_usuarios . $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($fim_pu < $paginacao_usuarios['total_paginas']): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <?php if ($paginacao_usuarios['pagina_atual'] < $paginacao_usuarios['total_paginas']): ?>
                                    <li class="page-item"><a class="page-link" href="<?= $link_pag_usuarios . ($paginacao_usuarios['pagina_atual'] + 1) ?>">»</a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link">»</span></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane fade <?= ($aba_ativa === 'solicitacoes-acesso-tab') ? 'show active' : '' ?>" id="solicitacoes-acesso-content" role="tabpanel" aria-labelledby="solicitacoes-acesso-tab">
            <div class="card shadow-sm rounded-3 border-0">
                <div class="card-header bg-light"><h6 class="mb-0 fw-bold"><i class="fas fa-user-clock me-2 text-primary opacity-75"></i>Solicitações de Acesso Pendentes</h6></div>
                <div class="card-body p-0">
                    <?php if (empty($solicitacoes_acesso)): ?>
                        <p class="text-center text-muted p-4">Nenhuma solicitação de acesso pendente.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm mb-0 align-middle">
                                <thead class="table-light small text-uppercase text-muted">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>E-mail</th>
                                        <th>Empresa</th>
                                        <th>Perfil Sol.</th>
                                        <th>Motivo</th>
                                        <th>Data</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitacoes_acesso as $sa): ?>
                                        <tr>
                                            <td class="fw-bold">#<?= htmlspecialchars($sa['id']) ?></td>
                                            <td><?= htmlspecialchars($sa['nome_completo']) ?></td>
                                            <td><?= htmlspecialchars($sa['email']) ?></td>
                                            <td><a href="<?= BASE_URL ?>admin/admin_editar_conta_cliente.php?id=<?= $sa['empresa_id'] ?>" title="Ver Empresa: <?= htmlspecialchars($sa['empresa_nome']) ?>"><?= htmlspecialchars($sa['empresa_nome']) ?></a></td>
                                            <td><span class="badge bg-info-subtle text-info-emphasis"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $sa['perfil_solicitado'] ?? 'N/A'))) ?></span></td>
                                            <td title="<?= htmlspecialchars($sa['motivo']) ?>"><?= htmlspecialchars(mb_strimwidth($sa['motivo'], 0, 40, "...")) ?></td>
                                            <td class="small text-nowrap"><?= htmlspecialchars((new DateTime($sa['data_solicitacao']))->format('d/m/y H:i')) ?></td>
                                            <td class="text-center action-buttons-table">
                                                <div class="d-inline-flex flex-nowrap">
                                                    <form method="POST" action="<?= strtok($_SERVER['REQUEST_URI'], '?') . '?view=solicitacoes-acesso-tab' ?>" class="d-inline me-1" onsubmit="return confirm('Aprovar acesso para <?= htmlspecialchars(addslashes($sa['nome_completo'])) ?> com perfil <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $sa['perfil_solicitado']))) ?> na empresa <?= htmlspecialchars(addslashes($sa['empresa_nome'])) ?>? Uma senha temporária será gerada.');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                                                        <input type="hidden" name="action" value="aprovar_acesso">
                                                        <input type="hidden" name="id" value="<?= $sa['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success action-btn" title="Aprovar"><i class="fas fa-check fa-fw"></i></button>
                                                    </form>
                                                    <form method="POST" action="<?= strtok($_SERVER['REQUEST_URI'], '?') . '?view=solicitacoes-acesso-tab' ?>" class="d-inline" onsubmit="return confirm('Rejeitar acesso para <?= htmlspecialchars(addslashes($sa['nome_completo'])) ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                                                        <input type="hidden" name="action" value="rejeitar_acesso">
                                                        <input type="hidden" name="id" value="<?= $sa['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Rejeitar"><i class="fas fa-times fa-fw"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?= ($aba_ativa === 'solicitacoes-reset-tab') ? 'show active' : '' ?>" id="solicitacoes-reset-content" role="tabpanel" aria-labelledby="solicitacoes-reset-tab">
            <div class="card shadow-sm rounded-3 border-0">
                <div class="card-header bg-light"><h6 class="mb-0 fw-bold"><i class="fas fa-key me-2 text-primary opacity-75"></i>Solicitações de Reset de Senha Pendentes</h6></div>
                <div class="card-body p-0">
                    <?php if (empty($solicitacoes_reset)): ?>
                        <p class="text-center text-muted p-4">Nenhuma solicitação de reset de senha pendente.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm mb-0 align-middle">
                                <thead class="table-light small text-uppercase text-muted">
                                    <tr>
                                        <th>ID Sol.</th>
                                        <th>Usuário</th>
                                        <th>E-mail</th>
                                        <th>Empresa</th>
                                        <th>Data</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitacoes_reset as $sr): ?>
                                        <tr>
                                            <td class="fw-bold">#<?= htmlspecialchars($sr['id']) ?></td>
                                            <td><?= htmlspecialchars($sr['nome_usuario']) ?></td>
                                            <td><?= htmlspecialchars($sr['email_usuario'] ?? ($sr['email'] ?? 'N/A')) ?></td>
                                            <td><a href="<?= BASE_URL ?>admin/admin_editar_conta_cliente.php?id=<?= $sr['empresa_id_usuario'] ?? '' ?>" title="Ver Empresa: <?= htmlspecialchars($sr['nome_empresa_usuario'] ?? '') ?>"><?= htmlspecialchars($sr['nome_empresa_usuario'] ?? 'N/A') ?></a></td>
                                            <td class="small text-nowrap"><?= htmlspecialchars((new DateTime($sr['data_solicitacao']))->format('d/m/y H:i')) ?></td>
                                            <td class="text-center action-buttons-table">
                                                <div class="d-inline-flex flex-nowrap">
                                                    <form method="POST" action="<?= strtok($_SERVER['REQUEST_URI'], '?') . '?view=solicitacoes-reset-tab' ?>" class="d-inline me-1" onsubmit="return confirm('Gerar nova senha temporária para <?= htmlspecialchars(addslashes($sr['nome_usuario'])) ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                                                        <input type="hidden" name="action" value="redefinir_senha">
                                                        <input type="hidden" name="id" value="<?= $sr['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary action-btn" title="Gerar Senha Temporária"><i class="fas fa-key fa-fw"></i></button>
                                                    </form>
                                                    <form method="POST" action="<?= strtok($_SERVER['REQUEST_URI'], '?') . '?view=solicitacoes-reset-tab' ?>" class="d-inline" onsubmit="return confirm('Rejeitar solicitação de reset para <?= htmlspecialchars(addslashes($sr['nome_usuario'])) ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                                                        <input type="hidden" name="action" value="rejeitar_reset">
                                                        <input type="hidden" name="id" value="<?= $sr['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Rejeitar"><i class="fas fa-times fa-fw"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    let activeTabId = urlParams.get('view') || 'usuarios-cadastrados-tab';

    if (window.location.hash) {
        const hashTabId = window.location.hash.substring(1);
        if (document.getElementById(hashTabId)) activeTabId = hashTabId;
    }

    const tabButton = document.getElementById(activeTabId);
    if (tabButton) {
        let tabInstance = bootstrap.Tab.getInstance(tabButton);
        if (!tabInstance) {
            tabInstance = new bootstrap.Tab(tabButton);
        }
        tabInstance.show();
    }

    const tabButtons = document.querySelectorAll('#adminUserTabs button[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function (event) {
            const newUrl = new URL(window.location);
            const currentView = newUrl.searchParams.get('view');
            if (currentView !== event.target.id || !currentView) {
                if (!newUrl.searchParams.has('pagina_usr') && !newUrl.searchParams.has('busca_usuario')) {
                    newUrl.searchParams.set('view', event.target.id);
                    newUrl.searchParams.delete('pagina_usr');
                    window.history.replaceState({}, '', newUrl.toString());
                }
            }
        });
    });

    const tipoUsuarioSelectForm = document.getElementById('tipo_usuario_filtro_form');
    const filtroEmpresaDivContainerForm = document.getElementById('filtro_empresa_usr_div_container_form');
    const filtroEmpresaSelectForm = document.getElementById('empresa_id_filtro_usr_form');

    if (tipoUsuarioSelectForm && filtroEmpresaDivContainerForm && filtroEmpresaSelectForm) {
        function toggleEmpresaFilterForm() {
            filtroEmpresaDivContainerForm.style.display = (tipoUsuarioSelectForm.value === 'clientes') ? 'block' : 'none';
            if (tipoUsuarioSelectForm.value !== 'clientes') {
                filtroEmpresaSelectForm.value = '';
            }
        }
        tipoUsuarioSelectForm.addEventListener('change', toggleEmpresaFilterForm);
        toggleEmpresaFilterForm();
    }
});
</script>

<?php
echo getFooterAdmin();
?>