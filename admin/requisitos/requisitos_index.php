<?php
// admin/requisitos/requisitos_index.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_admin.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');
$erro_criar_msg = $_SESSION['erro_criar_requisito'] ?? null;
unset($_SESSION['erro_criar_requisito']);
$form_data = $_SESSION['form_data_requisito'] ?? [];
unset($_SESSION['form_data_requisito']);

// ***** NOVO: Carregar planos para o formulário de criação *****
$planos_assinatura_disponiveis = listarPlanosAssinatura($conexao, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        $_SESSION['erro'] = "Erro de validação da sessão. Ação não executada.";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'requisito_csrf_falha', 0, 'Token CSRF inválido.', $conexao);
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
    $_SESSION['csrf_token'] = gerar_csrf_token(); // Regenera token
    $action = $_POST['action'] ?? null;
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    switch($action) {
        case 'criar_requisito':
            // Usar nomes com sufixo _criar para os inputs do formulário de criação
            // para evitar conflito com os filtros da página.
            $dados_form_criar = [
                'codigo' => trim($_POST['codigo_criar'] ?? ''),
                'nome' => trim($_POST['nome_criar'] ?? ''),
                'descricao' => trim($_POST['descricao_criar'] ?? ''),
                'categoria' => trim($_POST['categoria_criar'] ?? ''),
                'norma_referencia' => trim($_POST['norma_referencia_criar'] ?? ''),
                'versao_norma_aplicavel' => trim($_POST['versao_norma_aplicavel_criar'] ?? ''),
                'data_ultima_revisao_norma' => !empty($_POST['data_ultima_revisao_norma_criar']) ? trim($_POST['data_ultima_revisao_norma_criar']) : null,
                'guia_evidencia' => trim($_POST['guia_evidencia_criar'] ?? ''),
                'objetivo_controle' => trim($_POST['objetivo_controle_criar'] ?? ''),
                'tecnicas_sugeridas' => trim($_POST['tecnicas_sugeridas_criar'] ?? ''),
                'peso' => filter_input(INPUT_POST, 'peso_criar', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 0]]),
                'ativo' => isset($_POST['ativo_criar']) ? 1 : 0,
                'global_ou_empresa_id' => null,
                'disponibilidade_planos_ids' => $_POST['disponibilidade_planos_ids_criar'] ?? []
            ];

            $validation_errors_criar = [];
            if (empty($dados_form_criar['nome'])) $validation_errors_criar[] = "Nome/Título Curto é obrigatório.";
            if (empty($dados_form_criar['descricao'])) $validation_errors_criar[] = "Descrição Detalhada / Pergunta é obrigatória.";
            if (!is_numeric($dados_form_criar['peso']) || (int)$dados_form_criar['peso'] < 0) $validation_errors_criar[] = "Peso/Impacto deve ser um número não negativo.";
            if ($dados_form_criar['data_ultima_revisao_norma'] !== null) {
                $d_criar_check = DateTime::createFromFormat('Y-m-d', $dados_form_criar['data_ultima_revisao_norma']);
                if (!$d_criar_check || $d_criar_check->format('Y-m-d') !== $dados_form_criar['data_ultima_revisao_norma']) {
                    $validation_errors_criar[] = "Data da Última Revisão da Norma inválida.";
                }
            }

            if (empty($validation_errors_criar)) {
                // A função criarRequisitoAuditoria foi adaptada para receber este array completo
                $resultado_criar = criarRequisitoAuditoria($conexao, $dados_form_criar, $_SESSION['usuario_id']);
                if ($resultado_criar === true) {
                    $_SESSION['sucesso'] = "Requisito '".htmlspecialchars($dados_form_criar['nome'])."' criado com sucesso!";
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_requisito_sucesso', 1, "Req: {$dados_form_criar['nome']}", $conexao);
                    unset($_SESSION['form_data_requisito']);
                } else {
                    $_SESSION['erro_criar_requisito'] = is_string($resultado_criar) ? $resultado_criar : "Erro desconhecido ao criar requisito.";
                    $_SESSION['form_data_requisito'] = $_POST; // Salva todo o POST para repopular
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_requisito_falha_db', 0, "Falha: " . (is_string($resultado_criar) ? $resultado_criar : "Erro DB"), $conexao);
                }
            } else {
                $_SESSION['erro_criar_requisito'] = "<strong>Erro ao criar:</strong><ul><li>" . implode("</li><li>", $validation_errors_criar) . "</li></ul>";
                $_SESSION['form_data_requisito'] = $_POST;
            }
            header('Location: ' . $_SERVER['PHP_SELF'] . (empty($validation_errors_criar) && $resultado_criar === true ? '' : '#collapseCriarRequisito'));
            exit;
            break;

        case 'ativar_requisito':
            if ($id && setStatusRequisitoAuditoria($conexao, $id, true)) {
                $_SESSION['sucesso'] = "Requisito ID $id ativado.";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_requisito', 1, "ID: $id", $conexao);
            } else {
                $_SESSION['erro'] = "Erro ao ativar o requisito ID $id.";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_requisito', 0, "ID: $id", $conexao);
            }
            header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter(['status' => $_GET['status'] ?? 'todos', 'busca' => $_GET['busca'] ?? '', 'categoria' => $_GET['categoria'] ?? '', 'norma' => $_GET['norma'] ?? '', 'pagina' => $_GET['pagina'] ?? 1])));
            exit;
            break;

        case 'desativar_requisito':
             if ($id && setStatusRequisitoAuditoria($conexao, $id, false)) {
                $_SESSION['sucesso'] = "Requisito ID $id desativado.";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_requisito', 1, "ID: $id", $conexao);
            } else {
                $_SESSION['erro'] = "Erro ao desativar o requisito ID $id.";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_requisito', 0, "ID: $id", $conexao);
            }
            header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter(['status' => $_GET['status'] ?? 'todos', 'busca' => $_GET['busca'] ?? '', 'categoria' => $_GET['categoria'] ?? '', 'norma' => $_GET['norma'] ?? '', 'pagina' => $_GET['pagina'] ?? 1])));
            exit;
            break;

         case 'excluir_requisito':
             if ($id) {
                 $resultado_exclusao = excluirRequisitoAuditoria($conexao, $id);
                 if ($resultado_exclusao === true) {
                     $_SESSION['sucesso'] = "Requisito ID $id excluído com sucesso (se não estiver em uso).";
                     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_requisito_sucesso', 1, "ID: $id", $conexao);
                 } else {
                     $_SESSION['erro'] = is_string($resultado_exclusao) ? $resultado_exclusao : "Erro ao excluir requisito ID $id.";
                     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_requisito_falha', 0, "ID: $id, Erro: ". (is_string($resultado_exclusao) ? $resultado_exclusao : "Erro DB"), $conexao);
                 }
             } else {
                $_SESSION['erro'] = "ID inválido para exclusão.";
             }
             header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter(['status' => $_GET['status'] ?? 'todos', 'busca' => $_GET['busca'] ?? '', 'categoria' => $_GET['categoria'] ?? '', 'norma' => $_GET['norma'] ?? '', 'pagina' => $_GET['pagina'] ?? 1])));
             exit;
             break;

         default:
            $_SESSION['erro'] = "Ação desconhecida.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'requisito_acao_desconhecida', 0, 'Ação: ' . ($action ?? 'N/A'), $conexao);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
    }
}

$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina = 20;
$filtro_status = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_REGEXP, ['options' => ['default' => 'todos', 'regexp' => '/^(todos|ativos|inativos)$/']]);
$termo_busca = trim(filter_input(INPUT_GET, 'busca', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filtro_categoria = trim(filter_input(INPUT_GET, 'categoria', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filtro_norma = trim(filter_input(INPUT_GET, 'norma', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

$filtros_ativos_pag = array_filter([
    'status' => $filtro_status, 'busca' => $termo_busca,
    'categoria' => $filtro_categoria, 'norma' => $filtro_norma
]);

$requisitos_data = getRequisitosAuditoria(
    $conexao, $pagina_atual, $itens_por_pagina, $filtro_status, $termo_busca, $filtro_categoria, $filtro_norma
);
$lista_requisitos = $requisitos_data['requisitos'];
$paginacao = $requisitos_data['paginacao'];

$categorias_filtro = getCategoriasRequisitos($conexao);
$normas_filtro = getNormasRequisitos($conexao);

$title = "ACodITools - Gerenciar Requisitos Globais";
echo getHeaderAdmin($title);
$csrf_token_page = $_SESSION['csrf_token']; // Pega o token atual da sessão para os forms
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-tasks me-2"></i>Gerenciar Requisitos Globais</h1>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCriarRequisito" aria-expanded="<?= !empty($erro_criar_msg) ? 'true' : 'false' ?>" aria-controls="collapseCriarRequisito">
            <i class="fas fa-plus me-1"></i> Novo Requisito Global
        </button>
    </div>

    <?php if ($sucesso_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($erro_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="collapse <?= !empty($erro_criar_msg) ? 'show' : '' ?> mb-4" id="collapseCriarRequisito">
        <div class="card shadow-sm border-start border-primary border-3">
            <div class="card-header bg-light"><i class="fas fa-plus-circle me-1"></i> Criar Novo Requisito Global</div>
            <div class="card-body p-4">
                <?php if ($erro_criar_msg): ?>
                    <div class="alert alert-warning small p-2" role="alert"><?= $erro_criar_msg ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="createRequisitoForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                    <input type="hidden" name="action" value="criar_requisito">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="codigo_criar" class="form-label form-label-sm fw-semibold">Código</label>
                            <input type="text" class="form-control form-control-sm" id="codigo_criar" name="codigo_criar" value="<?= htmlspecialchars($form_data['codigo_criar'] ?? '') ?>" maxlength="50">
                            <small class="form-text text-muted">Opcional. Ex: A.5.1</small>
                        </div>
                        <div class="col-md-9">
                            <label for="nome_criar" class="form-label form-label-sm fw-semibold">Nome/Título Curto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="nome_criar" name="nome_criar" required maxlength="255" value="<?= htmlspecialchars($form_data['nome_criar'] ?? '') ?>">
                            <div class="invalid-feedback">O nome/título é obrigatório.</div>
                        </div>
                        <div class="col-12">
                            <label for="descricao_criar" class="form-label form-label-sm fw-semibold">Descrição Detalhada / Pergunta <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-sm" id="descricao_criar" name="descricao_criar" rows="3" required><?= htmlspecialchars($form_data['descricao_criar'] ?? '') ?></textarea>
                             <div class="invalid-feedback">A descrição detalhada é obrigatória.</div>
                        </div>

                        <div class="col-md-6">
                             <label for="categoria_criar" class="form-label form-label-sm fw-semibold">Categoria</label>
                             <input type="text" class="form-control form-control-sm" id="categoria_criar" name="categoria_criar" value="<?= htmlspecialchars($form_data['categoria_criar'] ?? '') ?>" maxlength="100" list="categoriasExistentesListCriar">
                             <datalist id="categoriasExistentesListCriar">
                                 <?php foreach ($categorias_filtro as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"></option><?php endforeach; ?>
                             </datalist>
                        </div>
                        <div class="col-md-6">
                             <label for="norma_referencia_criar" class="form-label form-label-sm fw-semibold">Norma de Referência</label>
                             <input type="text" class="form-control form-control-sm" id="norma_referencia_criar" name="norma_referencia_criar" value="<?= htmlspecialchars($form_data['norma_referencia_criar'] ?? '') ?>" maxlength="100" list="normasExistentesListCriar">
                               <datalist id="normasExistentesListCriar">
                                 <?php foreach ($normas_filtro as $norma): ?><option value="<?= htmlspecialchars($norma) ?>"></option><?php endforeach; ?>
                             </datalist>
                        </div>
                        <div class="col-md-4">
                            <label for="versao_norma_aplicavel_criar" class="form-label form-label-sm fw-semibold">Versão da Norma</label>
                            <input type="text" class="form-control form-control-sm" id="versao_norma_aplicavel_criar" name="versao_norma_aplicavel_criar" value="<?= htmlspecialchars($form_data['versao_norma_aplicavel_criar'] ?? '') ?>" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label for="data_ultima_revisao_norma_criar" class="form-label form-label-sm fw-semibold">Última Revisão da Norma</label>
                            <input type="date" class="form-control form-control-sm" id="data_ultima_revisao_norma_criar" name="data_ultima_revisao_norma_criar" value="<?= htmlspecialchars($form_data['data_ultima_revisao_norma_criar'] ?? '') ?>">
                             <div class="invalid-feedback">Data inválida. Use formato AAAA-MM-DD.</div>
                        </div>
                         <div class="col-md-4">
                            <label for="peso_criar" class="form-label form-label-sm fw-semibold">Peso/Impacto <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="peso_criar" name="peso_criar" value="<?= htmlspecialchars($form_data['peso_criar'] ?? '1') ?>" min="0" step="1" required>
                            <div class="invalid-feedback">Peso/Impacto deve ser um número não negativo.</div>
                        </div>

                        <div class="col-12">
                            <label for="guia_evidencia_criar" class="form-label form-label-sm fw-semibold">Guia de Evidência</label>
                            <textarea class="form-control form-control-sm" id="guia_evidencia_criar" name="guia_evidencia_criar" rows="2" placeholder="Orientações sobre que tipo de evidência coletar..."><?= htmlspecialchars($form_data['guia_evidencia_criar'] ?? '') ?></textarea>
                        </div>
                         <div class="col-12">
                            <label for="objetivo_controle_criar" class="form-label form-label-sm fw-semibold">Objetivo do Controle</label>
                            <textarea class="form-control form-control-sm" id="objetivo_controle_criar" name="objetivo_controle_criar" rows="2" placeholder="Qual o objetivo principal deste requisito/controle..."><?= htmlspecialchars($form_data['objetivo_controle_criar'] ?? '') ?></textarea>
                        </div>
                         <div class="col-12">
                            <label for="tecnicas_sugeridas_criar" class="form-label form-label-sm fw-semibold">Técnicas de Auditoria Sugeridas</label>
                            <textarea class="form-control form-control-sm" id="tecnicas_sugeridas_criar" name="tecnicas_sugeridas_criar" rows="2" placeholder="Ex: Entrevista, Observação, Análise de Logs..."><?= htmlspecialchars($form_data['tecnicas_sugeridas_criar'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label form-label-sm fw-semibold">Disponibilidade para Planos de Assinatura</label>
                            <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y:auto;">
                                <?php if(empty($planos_assinatura_disponiveis)): ?>
                                    <small class="text-muted">Nenhum plano de assinatura ativo para seleção.</small>
                                <?php else:
                                    $planos_selecionados_criar_form = $form_data['disponibilidade_planos_ids_criar'] ?? [];
                                    foreach($planos_assinatura_disponiveis as $plano_item_form): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="disponibilidade_planos_ids_criar[]" value="<?= $plano_item_form['id'] ?>" id="plano_req_criar_form_<?= $plano_item_form['id'] ?>"
                                            <?= in_array($plano_item_form['id'], $planos_selecionados_criar_form) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="plano_req_criar_form_<?= $plano_item_form['id'] ?>">
                                            <?= htmlspecialchars($plano_item_form['nome_plano']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                            <small class="form-text text-muted">Selecione os planos. Se nenhum, disponível para todos (verifique lógica).</small>
                        </div>

                         <div class="col-12 mt-3">
                             <div class="form-check form-switch">
                                <?php
                                    $ativo_criar_default_val = true;
                                    if (array_key_exists('ativo_criar', $form_data)) {
                                        $ativo_criar_default_val = !empty($form_data['ativo_criar']);
                                    }
                                ?>
                                <input class="form-check-input" type="checkbox" role="switch" id="ativo_criar" name="ativo_criar" value="1" <?= $ativo_criar_default_val ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ativo_criar">Requisito Ativo</label>
                            </div>
                         </div>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-toggle="collapse" data-bs-target="#collapseCriarRequisito" aria-expanded="false">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Salvar Novo Requisito</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php /* ----- Card Lista de Requisitos (SEU CÓDIGO EXISTENTE AQUI) ----- */ ?>
    <div class="card shadow-sm">
        <div class="card-header bg-light">
             <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                 <span><i class="fas fa-list me-1"></i> Requisitos Cadastrados</span>
                 <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex align-items-center flex-grow-1 flex-md-grow-0 ms-md-auto">
                     <select name="status" class="form-select form-select-sm me-1" onchange="this.form.submit()" title="Filtrar por Status" style="max-width: 130px;">
                         <option value="todos" <?= ($filtro_status == 'todos') ? 'selected' : '' ?>>Todos Status</option>
                         <option value="ativos" <?= ($filtro_status == 'ativos') ? 'selected' : '' ?>>Ativos</option>
                         <option value="inativos" <?= ($filtro_status == 'inativos') ? 'selected' : '' ?>>Inativos</option>
                     </select>
                     <select name="categoria" class="form-select form-select-sm me-1" onchange="this.form.submit()" title="Filtrar por Categoria" style="max-width: 180px;">
                          <option value="">Todas Categorias</option>
                         <?php foreach ($categorias_filtro as $cat): ?><option value="<?= htmlspecialchars($cat) ?>" <?= ($filtro_categoria == $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option><?php endforeach; ?>
                     </select>
                      <select name="norma" class="form-select form-select-sm me-1" onchange="this.form.submit()" title="Filtrar por Norma" style="max-width: 180px;">
                          <option value="">Todas Normas</option>
                         <?php foreach ($normas_filtro as $norma): ?><option value="<?= htmlspecialchars($norma) ?>" <?= ($filtro_norma == $norma) ? 'selected' : '' ?>><?= htmlspecialchars($norma) ?></option><?php endforeach; ?>
                     </select>
                     <input type="search" name="busca" class="form-control form-control-sm me-1" placeholder="Buscar..." value="<?= htmlspecialchars($termo_busca) ?>" title="Buscar em Código, Nome ou Descrição" style="max-width: 200px;">
                     <button type="submit" class="btn btn-sm btn-outline-secondary me-1" title="Aplicar Busca"><i class="fas fa-search"></i></button>
                     <?php if ($filtro_status != 'todos' || !empty($termo_busca) || !empty($filtro_categoria) || !empty($filtro_norma)): ?>
                         <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-sm btn-outline-secondary" title="Limpar Filtros"><i class="fas fa-times"></i></a>
                     <?php endif; ?>
                 </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ID</th><th scope="col">Código</th><th scope="col">Nome</th><th scope="col">Categoria</th>
                            <th scope="col">Norma</th><th scope="col" class="text-center">Status</th><th scope="col" class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_requisitos)): ?>
                             <tr><td colspan="7" class="text-center text-muted p-4">Nenhum requisito encontrado com os filtros aplicados. <a href="<?= $_SERVER['PHP_SELF'] ?>">Limpar filtros</a> ou <a href="#" data-bs-toggle="collapse" data-bs-target="#collapseCriarRequisito">adicione um novo requisito</a>.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_requisitos as $req): ?>
                                <tr>
                                    <td><?= htmlspecialchars($req['id']) ?></td>
                                    <td><?= htmlspecialchars($req['codigo'] ?? '') ?></td>
                                    <td title="<?= htmlspecialchars($req['descricao']) ?>"><?= htmlspecialchars($req['nome']) ?></td>
                                    <td><?= htmlspecialchars($req['categoria'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($req['norma_referencia'] ?? '-') ?></td>
                                    <td class="text-center">
                                         <span class="badge <?= $req['ativo'] ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle' ?>">
                                            <?= $req['ativo'] ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex flex-nowrap">
                                            <a href="<?= BASE_URL ?>admin/requisitos/editar_requisito.php?id=<?= $req['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Editar Requisito"><i class="fas fa-edit fa-fw"></i></a>
                                            <?php $url_params_action = http_build_query(array_filter(['status' => $_GET['status'] ?? 'todos', 'busca' => $_GET['busca'] ?? '', 'categoria' => $_GET['categoria'] ?? '', 'norma' => $_GET['norma'] ?? '', 'pagina' => $_GET['pagina'] ?? 1])); ?>
                                            <?php if ($req['ativo']): ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>?<?= $url_params_action ?>" class="d-inline me-1" onsubmit="return confirm('Desativar o requisito <?= htmlspecialchars(addslashes($req['nome'])) ?>?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="desativar_requisito"><input type="hidden" name="id" value="<?= $req['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary" title="Desativar"><i class="fas fa-toggle-off fa-fw"></i></button></form>
                                            <?php else: ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>?<?= $url_params_action ?>" class="d-inline me-1" onsubmit="return confirm('Ativar o requisito <?= htmlspecialchars(addslashes($req['nome'])) ?>?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="ativar_requisito"><input type="hidden" name="id" value="<?= $req['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-success" title="Ativar"><i class="fas fa-toggle-on fa-fw"></i></button></form>
                                            <?php endif; ?>
                                             <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>?<?= $url_params_action ?>" class="d-inline" onsubmit="return confirm('ATENÇÃO! Excluir o requisito <?= htmlspecialchars(addslashes($req['nome'])) ?>? Verifique se ele não está em uso.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="excluir_requisito"><input type="hidden" name="id" value="<?= $req['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir Requisito"><i class="fas fa-trash-alt fa-fw"></i></button></form>
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
            <nav aria-label="Paginação de Requisitos">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                     <?php $link_paginacao = "?" . http_build_query($filtros_ativos_pag) . "&pagina="; ?>
                    <?php if ($paginacao['pagina_atual'] > 1): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] - 1) ?>">«</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">«</span></li> <?php endif; ?>
                    <?php $inicio = max(1, $paginacao['pagina_atual'] - 2); $fim = min($paginacao['total_paginas'], $paginacao['pagina_atual'] + 2); if ($inicio > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; for ($i = $inicio; $i <= $fim; $i++): ?><li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="<?= $link_paginacao . $i ?>"><?= $i ?></a></li><?php endfor; if ($fim < $paginacao['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                    <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] + 1) ?>">»</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">»</span></li> <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <?php /* Área para Importar/Exportar (SEU CÓDIGO EXISTENTE AQUI) */ ?>
    <div class="card shadow-sm mt-4 border-start border-info border-3">
        <div class="card-header bg-light"><i class="fas fa-exchange-alt me-1"></i> Importar / Exportar Requisitos</div>
        <div class="card-body">
             <p class="text-muted small">Utilize estas ferramentas para gerenciar requisitos em lote usando arquivos CSV.</p>
             <div class="row g-3">
                 <div class="col-md-6 border-end-md">
                     <h5 class="mb-3"><i class="fas fa-file-upload me-1 text-primary"></i> Importar CSV</h5>
                     <form action="<?= BASE_URL ?>admin/requisitos/importar_requisitos.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="importForm">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                         <div class="mb-3">
                            <label for="csv_file" class="form-label form-label-sm">Arquivo CSV:</label>
                            <input class="form-control form-control-sm" type="file" id="csv_file" name="csv_file" required accept=".csv, text/csv">
                            <div class="invalid-feedback">Selecione um arquivo CSV válido.</div>
                            <small class="form-text text-muted">Colunas esperadas (mínimo): codigo, nome*, descricao*, categoria, norma_referencia, peso*, versao_norma_aplicavel, data_ultima_revisao_norma, guia_evidencia, objetivo_controle, tecnicas_sugeridas, disponibilidade_planos_ids (IDs separados por '|'), ativo (1/0). * obrigatórias.</small>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-upload me-1"></i> Processar Importação</button>
                     </form>
                 </div>
                 <div class="col-md-6">
                    <h5 class="mb-3"><i class="fas fa-file-download me-1 text-success"></i> Exportar CSV</h5>
                    <p class="small">Exporte a lista de requisitos (todos ou apenas os filtrados).</p>
                    <?php $link_export = BASE_URL . "admin/requisitos/exportar_requisitos.php?" . http_build_query($filtros_ativos_pag); ?>
                    <a href="<?= $link_export ?>" class="btn btn-sm btn-success"><i class="fas fa-download me-1"></i> Exportar Lista Atual (<?= $paginacao['total_itens'] ?? 0 ?>)</a>
                     <a href="<?= BASE_URL ?>admin/requisitos/exportar_requisitos.php" class="btn btn-sm btn-outline-secondary ms-1" title="Exportar todos os requisitos, ignorando filtros"><i class="fas fa-globe me-1"></i> Exportar Todos</a>
                 </div>
             </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const createForm = document.getElementById('createRequisitoForm');
    if (createForm) {
        createForm.addEventListener('submit', event => {
            if (!createForm.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            createForm.classList.add('was-validated');
        }, false);
    }

    <?php if (!empty($erro_criar_msg)): ?>
        const collapseElement = document.getElementById('collapseCriarRequisito');
        if (collapseElement) {
            let bsCollapseInstance = bootstrap.Collapse.getInstance(collapseElement);
            if (!bsCollapseInstance) {
                bsCollapseInstance = new bootstrap.Collapse(collapseElement, { toggle: false });
            }
            bsCollapseInstance.show();
            const nomeInputCriar = document.getElementById('nome_criar');
            if (nomeInputCriar) {
                 setTimeout(() => nomeInputCriar.focus(), 150); // Pequeno delay para garantir
            }
        }
    <?php endif; ?>

    const importForm = document.getElementById('importForm');
    const fileInput = document.getElementById('csv_file');
    if (importForm && fileInput) {
        importForm.addEventListener('submit', function(event){
             if (fileInput.files.length === 0 && fileInput.required) {
                 fileInput.classList.add('is-invalid');
                 const feedback = fileInput.closest('.mb-3').querySelector('.invalid-feedback'); // Melhor seletor
                  if (feedback) { feedback.style.display = 'block'; }
                 event.preventDefault();
                 event.stopPropagation();
             } else {
                  fileInput.classList.remove('is-invalid');
             }
        });
         fileInput.addEventListener('change', function() {
             if (this.files.length > 0) { this.classList.remove('is-invalid'); }
         });
    }
});
</script>

<?php
echo getFooterAdmin();
?>