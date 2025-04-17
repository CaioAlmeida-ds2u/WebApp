<?php
// admin/requisitos_index.php

require_once __DIR__ . '/../../includes/config.php';        // Config, DB, CSRF Token, Base URL
require_once __DIR__ . '/../../includes/layout_admin.php';   // Layout unificado
require_once __DIR__ . '/../../includes/admin_functions.php'; // Funções admin

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens ---
$sucesso_msg = $_SESSION['sucesso'] ?? null;
$erro_msg = $_SESSION['erro'] ?? null;
$erro_criar_requisito = $_SESSION['erro_criar_requisito'] ?? null;
$erro_criar_modelo = $_SESSION['erro_criar_modelo'] ?? null;
unset($_SESSION['sucesso'], $_SESSION['erro'], $_SESSION['erro_criar_requisito'], $_SESSION['erro_criar_modelo']);

// Dados do formulário para repopular em caso de erro
$form_data_requisito = $_SESSION['form_data_requisito'] ?? [];
$form_data_modelo = $_SESSION['form_data_modelo'] ?? [];
unset($_SESSION['form_data_requisito'], $_SESSION['form_data_modelo']);

// --- Processamento de Ações POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['erro'] = "Erro de validação da sessão. Ação não executada.";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'csrf_falha', 0, 'Token CSRF inválido.', $conexao);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Regenerar token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $action = $_POST['action'] ?? null;

    // --- Ação: Criar Requisito ---
    if ($action === 'criar_requisito') {
        $nome = trim($_POST['nome_requisito'] ?? '');
        $descricao = trim($_POST['descricao_requisito'] ?? '');
        $errors = [];

        if (empty($nome)) {
            $errors[] = "O nome do requisito é obrigatório.";
        }

        if (empty($errors)) {
            $resultado = criarRequisitoAuditoria($conexao, $nome, $descricao ?: null); // Assumindo função existente
            if ($resultado === true) {
                $_SESSION['sucesso'] = "Requisito '" . htmlspecialchars($nome) . "' criado com sucesso!";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_requisito_sucesso', 1, "Requisito criado: $nome", $conexao);
            } else {
                $_SESSION['erro'] = "Erro ao criar o requisito. Tente novamente."; // Mensagem geral para o usuário
                $_SESSION['form_data_requisito'] = $_POST; // Salva dados do formulário
                $_SESSION['erro_criar_requisito'] = $resultado; // Mensagem de erro específica
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_requisito_falha_db', 0, "Falha ao criar requisito: $resultado", $conexao);
            }
        } else {
            $_SESSION['erro_criar_requisito'] = "<strong>Erro ao criar:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
            $_SESSION['form_data_requisito'] = $_POST;
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_requisito_falha_valid', 0, "Erro de validação", $conexao);
        }
    }
    // --- Ação: Ativar Requisito ---
    elseif ($action === 'ativar_requisito') {
        $id_ativar = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id_ativar && ativarRequisitoAuditoria($conexao, $id_ativar)) {
            $_SESSION['sucesso'] = "Requisito ID $id_ativar ativado.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_requisito', 1, "ID: $id_ativar", $conexao);
        } else {
            $_SESSION['erro'] = "Erro ao ativar o requisito ID $id_ativar.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_requisito', 0, "ID: $id_ativar", $conexao);
        }
    }
    // --- Ação: Desativar Requisito ---
    elseif ($action === 'desativar_requisito') {
        $id_desativar = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id_desativar && desativarRequisitoAuditoria($conexao, $id_desativar)) {
            $_SESSION['sucesso'] = "Requisito ID $id_desativar desativado.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_requisito', 1, "ID: $id_desativar", $conexao);
        } else {
            $_SESSION['erro'] = "Erro ao desativar o requisito ID $id_desativar.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_requisito', 0, "ID: $id_desativar", $conexao);
        }
    }
    // --- Ação: Criar Modelo ---
    elseif ($action === 'criar_modelo') {
        $nome = trim($_POST['nome_modelo'] ?? '');
        $descricao = trim($_POST['descricao_modelo'] ?? '');
        $errors = [];

        if (empty($nome)) {
            $errors[] = "O nome do modelo é obrigatório.";
        }

        if (empty($errors)) {
            $resultado = criarModeloAuditoria($conexao, $nome, $descricao ?: null);
            if ($resultado === true) {
                $_SESSION['sucesso'] = "Modelo '" . htmlspecialchars($nome) . "' criado com sucesso!";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_modelo_sucesso', 1, "Modelo criado: $nome", $conexao);
            } else {
                $_SESSION['erro_criar_modelo'] = $resultado;
                $_SESSION['form_data_modelo'] = $_POST;
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_modelo_falha_db', 0, "Falha ao criar modelo: $resultado", $conexao);
            }
        } else {
            $_SESSION['erro_criar_modelo'] = "<strong>Erro ao criar:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
            $_SESSION['form_data_modelo'] = $_POST;
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_modelo_falha_valid', 0, "Erro de validação", $conexao);
        }
    }
    // --- Ação: Ativar Modelo ---
    elseif ($action === 'ativar_modelo') {
        $id_ativar = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id_ativar && ativarModeloAuditoria($conexao, $id_ativar)) {
            $_SESSION['sucesso'] = "Modelo ID $id_ativar ativado.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_modelo', 1, "ID: $id_ativar", $conexao);
        } else {
            $_SESSION['erro'] = "Erro ao ativar o modelo ID $id_ativar.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_modelo', 0, "ID: $id_ativar", $conexao);
        }
    }
    // --- Ação: Desativar Modelo ---
    elseif ($action === 'desativar_modelo') {
        $id_desativar = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id_desativar && desativarModeloAuditoria($conexao, $id_desativar)) {
            $_SESSION['sucesso'] = "Modelo ID $id_desativar desativado.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_modelo', 1, "ID: $id_desativar", $conexao);
        } else {
            $_SESSION['erro'] = "Erro ao desativar o modelo ID $id_desativar.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_modelo', 0, "ID: $id_desativar", $conexao);
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// --- Paginação e Filtros ---
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina = 15;

// Obter listas
$requisitos_data = getRequisitosAuditoria($conexao, $pagina_atual, $itens_por_pagina); // Assumindo função existente
$lista_requisitos = $requisitos_data['requisitos'];
$paginacao_requisitos = $requisitos_data['paginacao'];

$modelos_data = getModelosAuditoria($conexao, $pagina_atual, $itens_por_pagina);
$lista_modelos = $modelos_data['modelos'];
$paginacao_modelos = $modelos_data['paginacao'];

// --- Geração do HTML ---
$title = "ACodITools - Gerenciar Requisitos";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-list-alt me-2"></i>Gerenciar Requisitos</h1>
    </div>

    <?php if ($sucesso_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($sucesso_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($erro_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($erro_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="requisitosTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="requisitos-tab" data-bs-toggle="tab" data-bs-target="#requisitos" type="button" role="tab" aria-controls="requisitos" aria-selected="true">Requisitos de Auditoria</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="modelos-tab" data-bs-toggle="tab" data-bs-target="#modelos" type="button" role="tab" aria-controls="modelos" aria-selected="false">Modelos de Auditoria</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="ferramentas-tab" data-bs-toggle="tab" data-bs-target="#ferramentas" type="button" role="tab" aria-controls="ferramentas" aria-selected="false">Ferramentas Adicionais</button>
        </li>
    </ul>

    <div class="tab-content" id="requisitosTabContent">
        <!-- Aba Requisitos -->
        <div class="tab-pane fade show active" id="requisitos" role="tabpanel" aria-labelledby="requisitos-tab">
            <!-- Formulário Criar Requisito -->
            <div class="collapse <?= $erro_criar_requisito ? 'show' : '' ?> mb-4" id="collapseCriarRequisito">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-plus-circle me-1"></i> Criar Novo Requisito
                    </div>
                    <div class="card-body">
                        <?php if ($erro_criar_requisito): ?>
                            <div class="alert alert-warning" role="alert">
                                <?= $erro_criar_requisito ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="createRequisitoForm" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="action" value="criar_requisito">
                            <div class="mb-3">
                                <label for="nome_requisito" class="form-label">Nome do Requisito <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nome_requisito" name="nome_requisito" required maxlength="255" value="<?= htmlspecialchars($form_data_requisito['nome_requisito'] ?? '') ?>">
                                <div class="invalid-feedback">O nome do requisito é obrigatório (máx. 255 caracteres).</div>
                            </div>
                            <div class="mb-3">
                                <label for="descricao_requisito" class="form-label">Descrição (Opcional)</label>
                                <textarea class="form-control" id="descricao_requisito" name="descricao_requisito" rows="3"><?= htmlspecialchars($form_data_requisito['descricao_requisito'] ?? '') ?></textarea>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-toggle="collapse" data-bs-target="#collapseCriarRequisito">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Salvar Novo Requisito</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Lista de Requisitos -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-1"></i> Requisitos Cadastrados</span>
                    <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCriarRequisito" aria-expanded="<?= $erro_criar_requisito ? 'true' : 'false' ?>">Novo Requisito</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Descrição</th>
                                    <th>Status</th>
                                    <th>Criado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lista_requisitos)): ?>
                                    <tr><td colspan="6" class="text-center text-muted">Nenhum requisito cadastrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($lista_requisitos as $requisito): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($requisito['id']) ?></td>
                                            <td><?= htmlspecialchars($requisito['nome']) ?></td>
                                            <td title="<?= htmlspecialchars($requisito['descricao'] ?? '') ?>">
                                                <?= htmlspecialchars(mb_strimwidth($requisito['descricao'] ?? '', 0, 70, "...")) ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $requisito['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= $requisito['ativo'] ? 'Ativo' : 'Inativo' ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars((new DateTime($requisito['data_criacao']))->format('d/m/y H:i')) ?></td>
                                            <td>
                                                <div class="d-flex flex-nowrap">
                                                    <a href="<?= BASE_URL ?>admin/editar_requisito.php?id=<?= $requisito['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Editar"><i class="fas fa-edit"></i></a>
                                                    <?php if ($requisito['ativo']): ?>
                                                        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Desativar o requisito <?= htmlspecialchars(addslashes($requisito['nome'])) ?>?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                            <input type="hidden" name="action" value="desativar_requisito">
                                                            <input type="hidden" name="id" value="<?= $requisito['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Desativar"><i class="fas fa-toggle-off"></i></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Ativar o requisito <?= htmlspecialchars(addslashes($requisito['nome'])) ?>?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                            <input type="hidden" name="action" value="ativar_requisito">
                                                            <input type="hidden" name="id" value="<?= $requisito['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Ativar"><i class="fas fa-toggle-on"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Paginação Requisitos -->
                    <?php if ($paginacao_requisitos['total_paginas'] > 1): ?>
                        <nav aria-label="Paginação de Requisitos">
                            <ul class="pagination pagination-sm justify-content-center">
                                <?php if ($paginacao_requisitos['pagina_atual'] > 1): ?>
                                    <li class="page-item"><a class="page-link" href="?pagina=<?= $paginacao_requisitos['pagina_atual'] - 1 ?>">Anterior</a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link">Anterior</span></li>
                                <?php endif; ?>
                                <?php
                                $inicio = max(1, $paginacao_requisitos['pagina_atual'] - 2);
                                $fim = min($paginacao_requisitos['total_paginas'], $paginacao_requisitos['pagina_atual'] + 2);
                                if ($inicio > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                for ($i = $inicio; $i <= $fim; $i++): ?>
                                    <li class="page-item <?= ($i == $paginacao_requisitos['pagina_atual']) ? 'active' : '' ?>">
                                        <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($fim < $paginacao_requisitos['total_paginas']): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <?php if ($paginacao_requisitos['pagina_atual'] < $paginacao_requisitos['total_paginas']): ?>
                                    <li class="page-item"><a class="page-link" href="?pagina=<?= $paginacao_requisitos['pagina_atual'] + 1 ?>">Próxima</a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link">Próxima</span></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Aba Modelos -->
        <div class="tab-pane fade" id="modelos" role="tabpanel" aria-labelledby="modelos-tab">
            <!-- Formulário Criar Modelo -->
            <div class="collapse <?= $erro_criar_modelo ? 'show' : '' ?> mb-4" id="collapseCriarModelo">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-plus-circle me-1"></i> Criar Novo Modelo de Auditoria
                    </div>
                    <div class="card-body">
                        <?php if ($erro_criar_modelo): ?>
                            <div class="alert alert-warning" role="alert">
                                <?= $erro_criar_modelo ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="createModelForm" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="action" value="criar_modelo">
                            <div class="mb-3">
                                <label for="nome_modelo" class="form-label">Nome do Modelo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nome_modelo" name="nome_modelo" required maxlength="255" value="<?= htmlspecialchars($form_data_modelo['nome_modelo'] ?? '') ?>">
                                <div class="invalid-feedback">O nome do modelo é obrigatório (máx. 255 caracteres).</div>
                            </div>
                            <div class="mb-3">
                                <label for="descricao_modelo" class="form-label">Descrição (Opcional)</label>
                                <textarea class="form-control" id="descricao_modelo" name="descricao_modelo" rows="3"><?= htmlspecialchars($form_data_modelo['descricao_modelo'] ?? '') ?></textarea>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-toggle="collapse" data-bs-target="#collapseCriarModelo">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Salvar Novo Modelo</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Lista de Modelos -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-1"></i> Modelos Cadastrados</span>
                    <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCriarModelo" aria-expanded="<?= $erro_criar_modelo ? 'true' : 'false' ?>">Novo Modelo</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Descrição</th>
                                    <th>Status</th>
                                    <th>Criado em</th>
                                    <th>Modificado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lista_modelos)): ?>
                                    <tr><td colspan="7" class="text-center text-muted">Nenhum modelo de auditoria cadastrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($lista_modelos as $modelo): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($modelo['id']) ?></td>
                                            <td><?= htmlspecialchars($modelo['nome']) ?></td>
                                            <td title="<?= htmlspecialchars($modelo['descricao'] ?? '') ?>">
                                                <?= htmlspecialchars(mb_strimwidth($modelo['descricao'] ?? '', 0, 70, "...")) ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $modelo['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= $modelo['ativo'] ? 'Ativo' : 'Inativo' ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars((new DateTime($modelo['data_criacao']))->format('d/m/y H:i')) ?></td>
                                            <td><?= htmlspecialchars((new DateTime($modelo['data_modificacao']))->format('d/m/y H:i')) ?></td>
                                            <td>
                                                <div class="d-flex flex-nowrap">
                                                    <a href="<?= BASE_URL ?>admin/editar_modelo.php?id=<?= $modelo['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Editar Itens do Modelo"><i class="fas fa-edit"></i></a>
                                                    <?php if ($modelo['ativo']): ?>
                                                        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Desativar o modelo <?= htmlspecialchars(addslashes($modelo['nome'])) ?>? Ele não poderá ser usado para novas auditorias.');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                            <input type="hidden" name="action" value="desativar_modelo">
                                                            <input type="hidden" name="id" value="<?= $modelo['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Desativar"><i class="fas fa-toggle-off"></i></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Ativar o modelo <?= htmlspecialchars(addslashes($modelo['nome'])) ?>?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                            <input type="hidden" name="action" value="ativar_modelo">
                                                            <input type="hidden" name="id" value="<?= $modelo['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Ativar"><i class="fas fa-toggle-on"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Paginação Modelos -->
                    <?php if ($paginacao_modelos['total_paginas'] > 1): ?>
                        <nav aria-label="Paginação de Modelos">
                            <ul class="pagination pagination-sm justify-content-center">
                                <?php if ($paginacao_modelos['pagina_atual'] > 1): ?>
                                    <li class="page-item"><a class="page-link" href="?pagina=<?= $paginacao_modelos['pagina_atual'] - 1 ?>">Anterior</a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link">Anterior</span></li>
                                <?php endif; ?>
                                <?php
                                $inicio = max(1, $paginacao_modelos['pagina_atual'] - 2);
                                $fim = min($paginacao_modelos['total_paginas'], $paginacao_modelos['pagina_atual'] + 2);
                                if ($inicio > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                for ($i = $inicio; $i <= $fim; $i++): ?>
                                    <li class="page-item <?= ($i == $paginacao_modelos['pagina_atual']) ? 'active' : '' ?>">
                                        <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($fim < $paginacao_modelos['total_paginas']): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <?php if ($paginacao_modelos['pagina_atual'] < $paginacao_modelos['total_paginas']): ?>
                                    <li class="page-item"><a class="page-link" href="?pagina=<?= $paginacao_modelos['pagina_atual'] + 1 ?>">Próxima</a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link">Próxima</span></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Aba Ferramentas Adicionais -->
        <div class="tab-pane fade" id="ferramentas" role="tabpanel" aria-labelledby="ferramentas-tab">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-tools me-1"></i> Ferramentas Adicionais
                </div>
                <div class="card-body">
                    <p class="text-muted">Funcionalidades adicionais para suporte à auditoria:</p>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <i class="fas fa-file-export me-2"></i>
                            <a href="<?= BASE_URL ?>admin/exportar_requisitos.php" class="text-decoration-none">Exportar Requisitos (CSV/JSON)</a>
                            <small class="text-muted d-block">Exporte a lista de requisitos para análise externa.</small>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-chart-bar me-2"></i>
                            <a href="<?= BASE_URL ?>admin/relatorios_auditoria.php" class="text-decoration-none">Relatórios de Auditoria</a>
                            <small class="text-muted d-block">Gere relatórios detalhados sobre conformidade e auditorias.</small>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-cog me-2"></i>
                            <a href="<?= BASE_URL ?>admin/configuracoes_auditoria.php" class="text-decoration-none">Configurações Avançadas</a>
                            <small class="text-muted d-block">Personalize regras e parâmetros de auditoria.</small>
                        </li>
                    </ul>
                    <p class="text-center text-muted mt-3"><i>(Mais ferramentas serão adicionadas conforme necessário)</i></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script para validação Bootstrap -->
<script>
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>

<?php
echo getFooterAdmin();
?>