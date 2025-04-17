<?php
// admin/logs.php

require_once __DIR__ . '/../includes/config.php';        // Config, DB, CSRF Token, Base URL
require_once __DIR__ . '/../includes/layout_admin.php';   // Layout unificado
require_once __DIR__ . '/../includes/admin_functions.php'; // getLogsAcesso, getTodosUsuarios

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Processamento de Filtros GET ---
// Sanitizar e validar todos os inputs GET
$filtro_data_inicio = filter_input(INPUT_GET, 'data_inicio', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^\d{4}-\d{2}-\d{2}$/']]);
$filtro_data_fim = filter_input(INPUT_GET, 'data_fim', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^\d{4}-\d{2}-\d{2}$/']]);
$filtro_usuario_id = filter_input(INPUT_GET, 'usuario_id', FILTER_VALIDATE_INT);
$filtro_acao = trim(filter_input(INPUT_GET, 'acao', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filtro_status = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]); // Aceita 0 ou 1
$filtro_busca = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina = 20; // Ajuste conforme necessário

// Construir array de parâmetros de filtro para passar para a função e para a paginação
$filtros_ativos = [];
if ($filtro_data_inicio) $filtros_ativos['data_inicio'] = $filtro_data_inicio;
if ($filtro_data_fim) $filtros_ativos['data_fim'] = $filtro_data_fim;
if ($filtro_usuario_id) $filtros_ativos['usuario_id'] = $filtro_usuario_id;
if (!empty($filtro_acao)) $filtros_ativos['acao'] = $filtro_acao;
// Usar !== null para permitir filtrar por status 0 (falha)
if ($filtro_status !== null && $filtro_status !== false) $filtros_ativos['status'] = $filtro_status;
if (!empty($filtro_busca)) $filtros_ativos['search'] = $filtro_busca;


// --- Obtenção dos Logs (passando filtros) ---
// Assumindo que getLogsAcesso aceita um array de filtros (precisa ajustar a função se não aceitar)
// A função anterior aceitava parâmetros individuais, vamos manter assim por enquanto
// $logs_data = getLogsAcesso($conexao, $pagina_atual, $itens_por_pagina, $filtros_ativos);
$logs_data = getLogsAcesso(
    $conexao,
    $pagina_atual,
    $itens_por_pagina,
    $filtro_data_inicio ?: '', // Passa vazio se filtro for inválido/ausente
    $filtro_data_fim ?: '',
    $filtro_usuario_id ?: '',
    $filtro_acao,
    // Passa '' se null para manter compatibilidade com a função anterior que checa !== ''
    ($filtro_status !== null && $filtro_status !== false) ? (string)$filtro_status : '',
    $filtro_busca
);

$logs = $logs_data['logs'] ?? [];
$paginacao = $logs_data['paginacao'] ?? ['total_paginas' => 0, 'pagina_atual' => 1, 'total_logs' => 0];

// --- Obtenção de Dados para os Selects de Filtro ---
$usuarios_filtro = getTodosUsuarios($conexao); // Assume que retorna array de ['id' => X, 'nome' => Y, 'email' => Z]
$acoes_filtro = [];
try {
    // Obter ações distintas para o dropdown
    $stmtAcoes = $conexao->query("SELECT DISTINCT acao FROM logs_acesso WHERE acao IS NOT NULL AND acao != '' ORDER BY acao");
    $acoes_filtro = $stmtAcoes->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Erro ao buscar ações distintas para filtro de logs: " . $e->getMessage());
}

// --- Geração do HTML ---
$title = "ACodITools - Logs de Acesso";
echo getHeaderAdmin($title); // Layout unificado
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-clipboard-list me-2"></i>Logs de Acesso do Sistema</h1>
    </div>

    <?php /* ----- Card de Filtros (Colapsável em telas menores?) ----- */ ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
           <i class="fas fa-filter me-1"></i> Filtros de Pesquisa
        </div>
        <div class="card-body">
            <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" id="filterLogsForm">
                <div class="row g-3">
                    <div class="col-md-6 col-lg-3">
                        <label for="data_inicio" class="form-label form-label-sm">Data Início:</label>
                        <input type="date" class="form-control form-control-sm" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($filtro_data_inicio ?? '') ?>">
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label for="data_fim" class="form-label form-label-sm">Data Fim:</label>
                        <input type="date" class="form-control form-control-sm" id="data_fim" name="data_fim" value="<?= htmlspecialchars($filtro_data_fim ?? '') ?>">
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label for="usuario_id" class="form-label form-label-sm">Usuário:</label>
                        <select class="form-select form-select-sm" id="usuario_id" name="usuario_id">
                            <option value="">-- Todos --</option>
                            <?php foreach ($usuarios_filtro as $usuario): ?>
                                <option value="<?= $usuario['id'] ?>" <?= ($filtro_usuario_id == $usuario['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($usuario['nome']) ?> (<?= htmlspecialchars($usuario['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="col-md-6 col-lg-3">
                        <label for="acao" class="form-label form-label-sm">Ação:</label>
                        <select class="form-select form-select-sm" id="acao" name="acao">
                            <option value="">-- Todas --</option>
                            <?php foreach ($acoes_filtro as $a): ?>
                                <option value="<?= htmlspecialchars($a) ?>" <?= ($filtro_acao == $a) ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label for="status" class="form-label form-label-sm">Status:</label>
                        <select class="form-select form-select-sm" id="status" name="status">
                            <option value="" <?= ($filtro_status === null || $filtro_status === false) ? 'selected' : '' ?>>-- Todos --</option>
                            <option value="1" <?= ($filtro_status === 1) ? 'selected' : '' ?>>Sucesso</option>
                            <option value="0" <?= ($filtro_status === 0) ? 'selected' : '' ?>>Falha</option>
                        </select>
                    </div>
                     <div class="col-md-6 col-lg-6">
                        <label for="search" class="form-label form-label-sm">Busca Livre (Detalhes, IP, etc):</label>
                        <input type="search" class="form-control form-control-sm" id="search" name="search" placeholder="Digite um termo..." value="<?= htmlspecialchars($filtro_busca ?? '') ?>">
                    </div>
                    <div class="col-md-12 col-lg-3 d-flex align-items-end">
                         <button type="submit" class="btn btn-primary btn-sm me-2 w-100"><i class="fas fa-filter me-1"></i> Aplicar Filtros</button>
                         <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary btn-sm w-100"><i class="fas fa-times me-1"></i> Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php /* ----- Tabela de Logs ----- */ ?>
    <div class="card shadow-sm">
         <div class="card-header bg-light d-flex justify-content-between align-items-center">
             <span><i class="fas fa-history me-1"></i>Registros Encontrados</span>
             <span class="badge bg-secondary"><?= $paginacao['total_logs'] ?? 0 ?> registro(s)</span>
         </div>
         <div class="card-body p-0">
             <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                           <th>ID</th>
                           <th>Data/Hora</th>
                           <th>Usuário</th>
                           <th>IP</th>
                           <th>Ação</th>
                           <th class="text-center">Status</th>
                           <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted p-4">
                                    <?php
                                    if (!empty($filtros_ativos)) {
                                        echo 'Nenhum log encontrado com os filtros aplicados. <a href="'.$_SERVER['PHP_SELF'].'">Limpar filtros</a>.';
                                    } else {
                                        echo 'Nenhum log registrado no sistema ainda.';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= htmlspecialchars($log['id']) ?></td>
                                    <td class="text-nowrap"><?= htmlspecialchars((new DateTime($log['data_hora']))->format('d/m/y H:i:s')) ?></td>
                                    <td title="<?= htmlspecialchars($log['email_usuario'] ?? 'N/A') ?>">
                                        <?= htmlspecialchars($log['nome_usuario'] ?? 'N/A') ?>
                                        <?= $log['usuario_id'] ? '(' . htmlspecialchars($log['usuario_id']) . ')' : '' ?>
                                    </td>
                                    <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($log['acao'] ?? 'N/A') ?></td>
                                    <td class="text-center">
                                         <span class="badge <?= $log['sucesso'] ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
                                            <?= $log['sucesso'] ? 'Sucesso' : 'Falha' ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 300px; overflow-wrap: break-word;" title="<?= htmlspecialchars($log['detalhes'] ?? '') ?>">
                                        <?= nl2br(htmlspecialchars(mb_strimwidth($log['detalhes'] ?? '', 0, 100, "..."))) ?>
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
            <nav aria-label="Paginação de Logs">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php
                    // Mantém os filtros na paginação
                    $params_paginacao_array = $filtros_ativos; // Começa com filtros ativos
                    $params_paginacao = http_build_query($params_paginacao_array);
                    $link_paginacao = "?{$params_paginacao}&pagina=";
                    ?>
                    <?php if ($paginacao['pagina_atual'] > 1): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] - 1) ?>">«</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">«</span></li> <?php endif; ?>
                    <?php
                    $inicio = max(1, $paginacao['pagina_atual'] - 2); $fim = min($paginacao['total_paginas'], $paginacao['pagina_atual'] + 2);
                    if ($inicio > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    for ($i = $inicio; $i <= $fim; $i++): ?><li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="<?= $link_paginacao . $i ?>"><?= $i ?></a></li><?php endfor;
                    if ($fim < $paginacao['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    ?>
                    <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] + 1) ?>">»</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">»</span></li> <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

</div> <?php /* Fim container-fluid */ ?>

<?php
// Inclui o Footer
echo getFooterAdmin();
?>