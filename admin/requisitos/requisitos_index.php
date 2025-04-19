<?php
// admin/requisitos/requisitos_index.php
// Versão focada em Listar, Criar, Ativar/Desativar (sem exibir erros de importação ainda)

require_once __DIR__ . '/../../includes/config.php';        // Config, DB, CSRF Token, Base URL
require_once __DIR__ . '/../../includes/layout_admin.php';   // Layout unificado
require_once __DIR__ . '/../../includes/admin_functions.php'; // Funções admin (CRUD Requisitos, etc.)

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens ---
$sucesso_msg = $_SESSION['sucesso'] ?? null;
$erro_msg = $_SESSION['erro'] ?? null;
$erro_criar_msg = $_SESSION['erro_criar_requisito'] ?? null; // Erro específico da criação
unset($_SESSION['sucesso'], $_SESSION['erro'], $_SESSION['erro_criar_requisito']);
$form_data = $_SESSION['form_data_requisito'] ?? []; // Para repopular form de criação
unset($_SESSION['form_data_requisito']);

// --- Processamento de Ações POST (Criar, Ativar, Desativar, Excluir - futuramente) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['erro'] = "Erro de validação da sessão. Ação não executada.";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'requisito_csrf_falha', 0, 'Token CSRF inválido.', $conexao);
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenera token
    $action = $_POST['action'] ?? null;
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    switch($action) {
        case 'criar_requisito':
            $dados_form = [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'nome' => trim($_POST['nome'] ?? ''),
                'descricao' => trim($_POST['descricao'] ?? ''),
                'categoria' => trim($_POST['categoria'] ?? ''),
                'norma_referencia' => trim($_POST['norma_referencia'] ?? ''),
                'ativo' => isset($_POST['ativo']) ? 1 : 0
            ];
            // Validação server-side básica (função criarRequisitoAuditoria também valida)
            if (empty($dados_form['nome']) || empty($dados_form['descricao'])) {
                 $_SESSION['erro_criar_requisito'] = "Nome e Descrição são obrigatórios.";
                 $_SESSION['form_data_requisito'] = $_POST;
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_requisito_falha_valid', 0, "Nome/Descrição vazios", $conexao);
            } else {
                $resultado = criarRequisitoAuditoria($conexao, $dados_form, $_SESSION['usuario_id']);
                if ($resultado === true) {
                    $_SESSION['sucesso'] = "Requisito '".htmlspecialchars($dados_form['nome'])."' criado com sucesso!";
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_requisito_sucesso', 1, "Req: {$dados_form['nome']}", $conexao);
                } else {
                    $_SESSION['erro_criar_requisito'] = $resultado; // Mensagem de erro da função
                    $_SESSION['form_data_requisito'] = $_POST; // Salva dados para repopular
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_requisito_falha', 0, "Falha: $resultado", $conexao);
                }
            }
            break;

        case 'ativar_requisito':
            if ($id && setStatusRequisitoAuditoria($conexao, $id, true)) {
                $_SESSION['sucesso'] = "Requisito ID $id ativado.";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_requisito', 1, "ID: $id", $conexao);
            } else {
                $_SESSION['erro'] = "Erro ao ativar o requisito ID $id.";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_requisito', 0, "ID: $id", $conexao);
            }
            break;

        case 'desativar_requisito':
             if ($id && setStatusRequisitoAuditoria($conexao, $id, false)) {
                $_SESSION['sucesso'] = "Requisito ID $id desativado.";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_requisito', 1, "ID: $id", $conexao);
            } else {
                $_SESSION['erro'] = "Erro ao desativar o requisito ID $id.";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_requisito', 0, "ID: $id", $conexao);
            }
            break;

         case 'excluir_requisito': // A SER IMPLEMENTADO COM SEGURANÇA
             // $resultado_exclusao = excluirRequisitoAuditoria($conexao, $id); // Chamar função real
             $_SESSION['erro'] = "FUNCIONALIDADE EXCLUIR REQUISITO (ID: $id) - AINDA NÃO IMPLEMENTADA.";
             dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_requisito_tentativa', 0, "ID: $id", $conexao);
             break;

         default:
            $_SESSION['erro'] = "Ação desconhecida.";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'requisito_acao_desconhecida', 0, 'Ação: ' . ($action ?? 'N/A'), $conexao);
    }
    // Redireciona para a própria página para mostrar mensagens e limpar o POST
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// --- Paginação e Filtros ---
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina = 20;
$filtro_status = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_REGEXP, ['options' => ['default' => 'todos', 'regexp' => '/^(todos|ativos|inativos)$/']]);
$termo_busca = trim(filter_input(INPUT_GET, 'busca', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filtro_categoria = trim(filter_input(INPUT_GET, 'categoria', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filtro_norma = trim(filter_input(INPUT_GET, 'norma', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

// Guarda filtros ativos para usar na paginação
$filtros_ativos_pag = array_filter([
    'status' => $filtro_status, 'busca' => $termo_busca,
    'categoria' => $filtro_categoria, 'norma' => $filtro_norma
]);

// --- Obter Dados para Exibição ---
$requisitos_data = getRequisitosAuditoria(
    $conexao, $pagina_atual, $itens_por_pagina, $filtro_status, $termo_busca, $filtro_categoria, $filtro_norma
);
$lista_requisitos = $requisitos_data['requisitos'];
$paginacao = $requisitos_data['paginacao'];

// Obter listas para dropdowns de filtro
$categorias_filtro = getCategoriasRequisitos($conexao);
$normas_filtro = getNormasRequisitos($conexao);

// --- Geração do HTML ---
$title = "ACodITools - Gerenciar Requisitos";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-tasks me-2"></i>Gerenciar Requisitos Mestre</h1>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCriarRequisito" aria-expanded="<?= $erro_criar_msg ? 'true' : 'false' ?>" aria-controls="collapseCriarRequisito">
            <i class="fas fa-plus me-1"></i> Novo Requisito
        </button>
    </div>

    <?php /* Mensagens Gerais */ ?>
    <?php if ($sucesso_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($erro_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <?php /* REMOVIDO Bloco de Erros Detalhados da Importação (será adicionado depois) */ ?>

    <?php /* ----- Card para Criar Novo Requisito (Colapsável) ----- */ ?>
    <div class="collapse <?= $erro_criar_msg ? 'show' : '' ?> mb-4" id="collapseCriarRequisito">
        <div class="card shadow-sm border-start border-primary border-3"> <?php /* Borda colorida */ ?>
            <div class="card-header bg-light"><i class="fas fa-plus-circle me-1"></i> Criar Novo Requisito</div>
            <div class="card-body">
                <?php if ($erro_criar_msg): ?>
                    <div class="alert alert-warning" role="alert"><?= $erro_criar_msg /* Permite HTML se necessário */ ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="createRequisitoForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="action" value="criar_requisito">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="codigo" class="form-label form-label-sm">Código (Opcional)</label>
                            <input type="text" class="form-control form-control-sm" id="codigo" name="codigo" value="<?= htmlspecialchars($form_data['codigo'] ?? '') ?>" maxlength="50">
                            <small class="form-text text-muted">Ex: A.5.1, LGPD.X</small>
                        </div>
                        <div class="col-md-9">
                            <label for="nome" class="form-label form-label-sm">Nome/Título Curto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="nome" name="nome" required maxlength="255" value="<?= htmlspecialchars($form_data['nome'] ?? '') ?>">
                            <div class="invalid-feedback">O nome/título é obrigatório.</div>
                        </div>
                        <div class="col-12">
                            <label for="descricao" class="form-label form-label-sm">Descrição Detalhada / Pergunta <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-sm" id="descricao" name="descricao" rows="3" required><?= htmlspecialchars($form_data['descricao'] ?? '') ?></textarea>
                             <div class="invalid-feedback">A descrição detalhada é obrigatória.</div>
                        </div>
                        <div class="col-md-6">
                             <label for="categoria" class="form-label form-label-sm">Categoria</label>
                             <input type="text" class="form-control form-control-sm" id="categoria" name="categoria" value="<?= htmlspecialchars($form_data['categoria'] ?? '') ?>" maxlength="100" list="categoriasExistentes">
                             <datalist id="categoriasExistentes">
                                 <?php foreach ($categorias_filtro as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?>
                             </datalist>
                             <small class="form-text text-muted">Ex: Controle de Acesso</small>
                        </div>
                        <div class="col-md-6">
                             <label for="norma_referencia" class="form-label form-label-sm">Norma de Referência</label>
                             <input type="text" class="form-control form-control-sm" id="norma_referencia" name="norma_referencia" value="<?= htmlspecialchars($form_data['norma_referencia'] ?? '') ?>" maxlength="100" list="normasExistentes">
                              <datalist id="normasExistentes">
                                 <?php foreach ($normas_filtro as $norma): ?><option value="<?= htmlspecialchars($norma) ?>"><?php endforeach; ?>
                             </datalist>
                              <small class="form-text text-muted">Ex: ISO 27001:2022, LGPD</small>
                        </div>
                         <div class="col-12">
                             <div class="form-check form-switch">
                                <?php
                                    // Define o estado 'checked' padrão para criação (geralmente ativo)
                                    // Se repopulando, usa o valor do form_data
                                    $checked_status = 'checked'; // Default para ativo
                                    if (isset($form_data['ativo']) && $form_data['ativo'] == 0) {
                                        $checked_status = ''; // Desmarca se veio 0 do form_data
                                    }
                                ?>
                                <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?= $checked_status ?>>
                                <label class="form-check-label" for="ativo">Requisito Ativo</label>
                            </div>
                         </div>
                    </div>
                    <hr class="my-3">
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-toggle="collapse" data-bs-target="#collapseCriarRequisito">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm">Salvar Novo Requisito</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php /* ----- Card Lista de Requisitos ----- */ ?>
    <div class="card shadow-sm">
        <div class="card-header bg-light">
             <div class="d-flex justify-content-between align-items-center flex-wrap gap-2"> <?php /* Gap para espaçamento */ ?>
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
                            <th scope="col">ID</th>
                            <th scope="col">Código</th>
                            <th scope="col">Nome</th>
                            <th scope="col">Categoria</th>
                            <th scope="col">Norma</th>
                            <th scope="col" class="text-center">Status</th>
                            <th scope="col" class="text-center">Ações</th>
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
                                            <?php if ($req['ativo']): ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Desativar o requisito <?= htmlspecialchars(addslashes($req['nome'])) ?>?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="action" value="desativar_requisito"><input type="hidden" name="id" value="<?= $req['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary" title="Desativar"><i class="fas fa-toggle-off fa-fw"></i></button></form>
                                            <?php else: ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Ativar o requisito <?= htmlspecialchars(addslashes($req['nome'])) ?>?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="action" value="ativar_requisito"><input type="hidden" name="id" value="<?= $req['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-success" title="Ativar"><i class="fas fa-toggle-on fa-fw"></i></button></form>
                                            <?php endif; ?>
                                             <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline" onsubmit="return confirm('ATENÇÃO! Excluir o requisito <?= htmlspecialchars(addslashes($req['nome'])) ?>? Verifique se ele não está em uso.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="action" value="excluir_requisito"><input type="hidden" name="id" value="<?= $req['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir Requisito"><i class="fas fa-trash-alt fa-fw"></i></button></form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
         <?php /* Paginação */ ?>
        <?php if (isset($paginacao) && $paginacao['total_paginas'] > 1): ?>
        <div class="card-footer bg-light py-2">
            <nav aria-label="Paginação de Requisitos">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                     <?php
                        // Mantém os filtros na paginação
                        $link_paginacao = "?" . http_build_query($filtros_ativos_pag) . "&pagina=";
                    ?>
                    <?php if ($paginacao['pagina_atual'] > 1): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] - 1) ?>">«</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">«</span></li> <?php endif; ?>
                    <?php $inicio = max(1, $paginacao['pagina_atual'] - 2); $fim = min($paginacao['total_paginas'], $paginacao['pagina_atual'] + 2); if ($inicio > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; for ($i = $inicio; $i <= $fim; $i++): ?><li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="<?= $link_paginacao . $i ?>"><?= $i ?></a></li><?php endfor; if ($fim < $paginacao['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                    <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] + 1) ?>">»</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">»</span></li> <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div> <?php /* Fim card lista */ ?>

    <?php /* Área para Importar/Exportar */ ?>
    <div class="card shadow-sm mt-4 border-start border-info border-3"> <?php /* Borda colorida */ ?>
        <div class="card-header bg-light"><i class="fas fa-exchange-alt me-1"></i> Importar / Exportar Requisitos</div>
        <div class="card-body">
             <p class="text-muted small">Utilize estas ferramentas para gerenciar requisitos em lote usando arquivos CSV.</p>
             <div class="row g-3">
                 <div class="col-md-6 border-end"> <?php /* Separador visual */ ?>
                     <h5 class="mb-3"><i class="fas fa-file-upload me-1 text-primary"></i> Importar CSV</h5>
                     <form action="<?= BASE_URL ?>admin/requisitos/importar_requisitos.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="importForm">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                         <div class="mb-3">
                            <label for="csv_file" class="form-label form-label-sm">Arquivo CSV:</label>
                            <input class="form-control form-control-sm" type="file" id="csv_file" name="csv_file" required accept=".csv, text/csv">
                            <div class="invalid-feedback">Selecione um arquivo CSV válido.</div>
                            <small class="form-text text-muted">Colunas esperadas: codigo, nome*, descricao*, categoria, norma_referencia, ativo (1/0 ou Sim/Nao). (* obrigatórias)</small>
                        </div>
                        <?php /* Opção de atualizar existentes (futuro) */ ?>
                        <!--
                        <div class="form-check form-switch mb-3">
                           <input class="form-check-input" type="checkbox" role="switch" id="atualizar_existentes" name="atualizar_existentes" value="1">
                           <label class="form-check-label small" for="atualizar_existentes">Atualizar requisitos existentes (baseado no Código)</label>
                        </div>
                        -->
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


</div> <?php /* Fim container-fluid */ ?>

<?php /* Script JS para validação */ ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validação geral Bootstrap
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

    // Foco no campo nome ao mostrar o form de criação por erro
    <?php if ($erro_criar_msg): ?>
        const collapseElement = document.getElementById('collapseCriarRequisito');
        if (collapseElement) {
            const bsCollapse = new bootstrap.Collapse(collapseElement, { toggle: false }); // Garante que está inicializado
            bsCollapse.show(); // Mostra o collapse se houver erro
            const nomeInput = document.getElementById('nome');
            if (nomeInput) {
                 // Pequeno delay para garantir que o collapse terminou de abrir antes do foco
                 setTimeout(() => nomeInput.focus(), 200);
            }
        }
    <?php endif; ?>

    // Prevenir envio do form de importação se nenhum arquivo for selecionado (validação extra)
    const importForm = document.getElementById('importForm');
    const fileInput = document.getElementById('csv_file');
    if (importForm && fileInput) {
        importForm.addEventListener('submit', function(event){
             if (fileInput.files.length === 0 && fileInput.required) {
                 // Se for requerido e não houver arquivo, impede e mostra validação
                 fileInput.classList.add('is-invalid'); // Força visualização do erro Bootstrap
                 const feedback = fileInput.nextElementSibling; // Assume que o invalid-feedback é o próximo
                  if (feedback && feedback.classList.contains('invalid-feedback')) {
                     feedback.style.display = 'block';
                 }
                 event.preventDefault();
                 event.stopPropagation();
             } else {
                  fileInput.classList.remove('is-invalid');
             }
        });
         // Limpa validação se o usuário selecionar um arquivo
         fileInput.addEventListener('change', function() {
             if (this.files.length > 0) {
                 this.classList.remove('is-invalid');
             }
         });
    }
});
</script>

<?php
echo getFooterAdmin();
?>