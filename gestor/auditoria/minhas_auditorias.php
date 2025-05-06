<?php
// gestor/auditoria/minhas_auditorias.php (Atualizado)
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_gestor.php';
require_once __DIR__ . '/../../includes/gestor_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    header('Location: ' . BASE_URL . 'acesso_negado.php'); exit;
}
$empresa_id = $_SESSION['usuario_empresa_id'];
$gestor_id = $_SESSION['usuario_id'];

// --- Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// --- Coleta de Filtros e Paginação ---
$paginaAtual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itensPorPagina = 15;

$filtros_aplicados = [];
$filtro_titulo = trim(filter_input(INPUT_GET, 'filtro_titulo', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filtro_status = trim(filter_input(INPUT_GET, 'filtro_status', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'todos'); // 'todos' como default
$filtro_responsavel_id = filter_input(INPUT_GET, 'filtro_responsavel_id', FILTER_VALIDATE_INT); // Pode ser auditor_id ou equipe_id
$filtro_tipo_responsavel = filter_input(INPUT_GET, 'filtro_tipo_responsavel', FILTER_SANITIZE_SPECIAL_CHARS); // 'auditor' ou 'equipe'
$filtro_data_de = filter_input(INPUT_GET, 'filtro_data_de', FILTER_SANITIZE_SPECIAL_CHARS);
$filtro_data_ate = filter_input(INPUT_GET, 'filtro_data_ate', FILTER_SANITIZE_SPECIAL_CHARS);

if (!empty($filtro_titulo)) $filtros_aplicados['titulo_busca'] = $filtro_titulo;
if ($filtro_status !== 'todos') $filtros_aplicados['status_busca'] = $filtro_status;
if ($filtro_responsavel_id && $filtro_tipo_responsavel) {
    $filtros_aplicados['auditor_busca'] = $filtro_responsavel_id; // Nome genérico na função
    $filtros_aplicados['tipo_responsavel_busca'] = $filtro_tipo_responsavel;
}
if (!empty($filtro_data_de)) $filtros_aplicados['data_inicio_de'] = $filtro_data_de;
if (!empty($filtro_data_ate)) $filtros_aplicados['data_inicio_ate'] = $filtro_data_ate;

// --- Validação CSRF para filtros ---
$csrf_get = $_GET['csrf_token'] ?? ''; // CSRF token vem dos filtros
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET) && !isset($_GET['pagina']) && !validar_csrf_token($csrf_get)) {
    // Validar CSRF apenas se não for apenas uma navegação de página
    // Se for uma submissão de filtro (ex: tem filtro_titulo), aí sim valida
    if (!empty($filtro_titulo) || $filtro_status !== 'todos' || $filtro_responsavel_id || $filtro_data_de || $filtro_data_ate) {
        definir_flash_message('erro', 'Token de segurança inválido para os filtros. Tente novamente.');
        // Redireciona para a página sem os parâmetros de filtro problemáticos, mas mantém a paginação se houver
        $params_redirect = [];
        if (isset($_GET['pagina'])) $params_redirect['pagina'] = $_GET['pagina'];
        header('Location: ' . BASE_URL . 'gestor/auditoria/minhas_auditorias.php' . (!empty($params_redirect) ? '?' . http_build_query($params_redirect) : ''));
        exit;
    }
}
$csrf_token_form = gerar_csrf_token(); // Novo token para os formulários da página

// --- Buscar Auditorias ---
$dadosAuditorias = getMinhasAuditorias($conexao, $gestor_id, $empresa_id, $paginaAtual, $itensPorPagina, $filtros_aplicados);
$auditorias = $dadosAuditorias['auditorias'] ?? [];
$totalRegistros = $dadosAuditorias['paginacao']['total_itens'] ?? 0; // Ajustado para pegar da estrutura de paginacao
$totalPaginas = $dadosAuditorias['paginacao']['total_paginas'] ?? 1;

// --- Dados para Dropdowns de Filtro ---
$todos_auditores_empresa = getAuditoresDaEmpresa($conexao, $empresa_id); // Auditores individuais
$todas_equipes_empresa = getEquipesDaEmpresa($conexao, $empresa_id); // Equipes
$status_disponiveis = ['Planejada', 'Em Andamento', 'Pausada', 'Concluída (Auditor)', 'Em Revisão', 'Aprovada', 'Rejeitada', 'Cancelada'];

$page_title = "Minhas Auditorias";
echo getHeaderGestor($page_title);
?>
<style> /* Estilos para progresso e badges */
    .progress-sm { height: 0.6rem; }
    .prazo-vencido { color: var(--bs-danger); font-weight: bold; }
    .prazo-proximo { color: var(--bs-warning); }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-clipboard-list me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb"><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page">Auditorias</li></ol></nav>
    </div>
    <div class="btn-toolbar page-actions">
        <a href="<?= BASE_URL ?>gestor/auditoria/criar_auditoria.php" class="btn btn-primary rounded-pill shadow-sm px-3 action-button-main">
            <i class="fas fa-plus me-1"></i> Criar Nova Auditoria
        </a>
    </div>
</div>

<?php if ($sucesso_msg): ?><div class="alert alert-success gestor-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($erro_msg): ?><div class="alert alert-danger gestor-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= $erro_msg ?><button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card shadow-sm mb-4 rounded-3 border-0 filter-card">
    <div class="card-body p-3">
        <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="filter-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_form) ?>">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="filtro_titulo" class="form-label form-label-sm">Título:</label>
                    <input type="search" name="filtro_titulo" id="filtro_titulo" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_titulo) ?>">
                </div>
                <div class="col-md-2">
                    <label for="filtro_status" class="form-label form-label-sm">Status:</label>
                    <select name="filtro_status" id="filtro_status" class="form-select form-select-sm">
                        <option value="todos" <?= ($filtro_status === 'todos') ? 'selected' : '' ?>>Todos</option>
                        <?php foreach ($status_disponiveis as $status_opt): ?>
                        <option value="<?= htmlspecialchars($status_opt) ?>" <?= ($filtro_status === $status_opt) ? 'selected' : '' ?>><?= htmlspecialchars($status_opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filtro_responsavel_id" class="form-label form-label-sm">Responsável:</label>
                    <select name="filtro_responsavel_id" id="filtro_responsavel_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <optgroup label="Auditores Individuais">
                            <?php foreach($todos_auditores_empresa as $aud_filtro): ?>
                            <option value="<?= $aud_filtro['id'] ?>" data-tipo="auditor" <?= ($filtro_responsavel_id == $aud_filtro['id'] && $filtro_tipo_responsavel === 'auditor') ? 'selected' : '' ?>>
                                <?= htmlspecialchars($aud_filtro['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Equipes">
                             <?php foreach($todas_equipes_empresa as $eq_filtro): ?>
                            <option value="<?= $eq_filtro['id'] ?>" data-tipo="equipe" <?= ($filtro_responsavel_id == $eq_filtro['id'] && $filtro_tipo_responsavel === 'equipe') ? 'selected' : '' ?>>
                                Equipe: <?= htmlspecialchars($eq_filtro['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                    <input type="hidden" name="filtro_tipo_responsavel" id="filtro_tipo_responsavel" value="<?= htmlspecialchars($filtro_tipo_responsavel) ?>">
                </div>
                <div class="col-md-2 col-lg-2">
                    <label for="filtro_data_de" class="form-label form-label-sm">Início de:</label>
                    <input type="date" name="filtro_data_de" id="filtro_data_de" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_data_de) ?>">
                </div>
                 <div class="col-md-2 col-lg-2">
                    <label for="filtro_data_ate" class="form-label form-label-sm">Início até:</label>
                    <input type="date" name="filtro_data_ate" id="filtro_data_ate" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_data_ate) ?>">
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i> Filtrar</button>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php" class="btn btn-sm btn-outline-secondary w-100" title="Limpar Filtros"><i class="fas fa-times"></i> Limpar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm dashboard-list-card rounded-3 border-0">
    <div class="card-header list-card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0 fw-bold d-flex align-items-center"><i class="fas fa-list-alt me-2 text-primary opacity-75"></i>Lista de Auditorias</h6>
        <span class="badge bg-light text-dark border rounded-pill"><?= $totalRegistros ?> auditoria(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped table-sm align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th scope="col" class="text-center" style="width:5%">ID</th>
                    <th scope="col" style="width:25%">Título</th>
                    <th scope="col" style="width:15%">Modelo</th>
                    <th scope="col" style="width:15%">Responsável</th>
                    <th scope="col" class="text-center" style="width:10%">Status</th>
                    <th scope="col" class="text-center" style="width:10%">Progresso</th>
                    <th scope="col" class="text-center" style="width:10%">Prazo Início</th>
                    <th scope="col" class="text-center" style="width:10%;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($auditorias)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-info-circle fa-2x mb-2 d-block opacity-50"></i> Nenhuma auditoria encontrada.</td></tr>
                <?php else: ?>
                    <?php foreach ($auditorias as $auditoria): ?>
                        <?php
                            $progresso = 0;
                            if ($auditoria['total_itens'] > 0) {
                                $progresso = round(($auditoria['itens_respondidos'] / $auditoria['total_itens']) * 100);
                            }
                            $corProgresso = 'bg-secondary';
                            if ($progresso > 0 && $progresso < 30) $corProgresso = 'bg-danger';
                            elseif ($progresso >= 30 && $progresso < 70) $corProgresso = 'bg-warning';
                            elseif ($progresso >= 70 && $progresso < 100) $corProgresso = 'bg-info';
                            elseif ($progresso == 100) $corProgresso = 'bg-success';

                            $classePrazo = '';
                            if ($auditoria['data_inicio_planejada']) {
                                $dataInicioDt = new DateTime($auditoria['data_inicio_planejada']);
                                $hojeDt = new DateTime();
                                if ($hojeDt > $dataInicioDt && !in_array($auditoria['status'], ['Aprovada', 'Cancelada', 'Rejeitada'])) {
                                     // Se passou da data e não está finalizada/cancelada
                                     // $classePrazo = 'prazo-vencido'; // Ou apenas para data_fim_planejada
                                }
                            }
                            if ($auditoria['data_fim_planejada']) {
                                $dataFimDt = new DateTime($auditoria['data_fim_planejada']);
                                $hojeDt = new DateTime();
                                $diffDias = $hojeDt->diff($dataFimDt)->format("%r%a"); // Dias restantes, negativo se passou
                                if ($diffDias < 0 && !in_array($auditoria['status'], ['Aprovada', 'Cancelada', 'Rejeitada'])) {
                                    $classePrazo = 'prazo-vencido';
                                } elseif ($diffDias >= 0 && $diffDias <= 7 && !in_array($auditoria['status'], ['Aprovada', 'Cancelada', 'Rejeitada'])) {
                                    $classePrazo = 'prazo-proximo';
                                }
                            }
                        ?>
                        <tr>
                            <td class="text-center fw-bold">#<?= htmlspecialchars($auditoria['id']) ?></td>
                            <td class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($auditoria['titulo']) ?>">
                                <?= htmlspecialchars($auditoria['titulo']) ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($auditoria['nome_modelo'] ?? 'N/A (Manual)') ?></td>
                            <td class="small text-muted"><?= $auditoria['responsavel_display'] // Já vem com HTML seguro da função ?></td>
                            <td class="text-center">
                                <?php /* Seu código de badge de status (já bom) */
                                $status = $auditoria['status']; $badgeClass = 'bg-secondary';
                                if ($status == 'Planejada') $badgeClass = 'bg-light text-dark border'; elseif ($status == 'Em Andamento') $badgeClass = 'bg-primary'; elseif ($status == 'Em Revisão' || $status == 'Concluída (Auditor)') $badgeClass = 'bg-warning text-dark'; elseif ($status == 'Aprovada') $badgeClass = 'bg-success'; elseif ($status == 'Rejeitada' || $status == 'Cancelada') $badgeClass = 'bg-danger'; elseif ($status == 'Pausada') $badgeClass = 'bg-secondary';
                                ?>
                                <span class="badge rounded-pill <?= $badgeClass ?> x-small fw-semibold"><?= htmlspecialchars($status) ?></span>
                                <?php if ($auditoria['itens_nao_conformes'] > 0 && in_array($status, ['Em Revisão', 'Aprovada', 'Concluída (Auditor)'])): ?>
                                    <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle x-small ms-1" title="<?= $auditoria['itens_nao_conformes'] ?> Itens Não Conformes/Parciais">
                                        <i class="fas fa-exclamation-triangle fa-xs"></i> <?= $auditoria['itens_nao_conformes'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small">
                                <?php if ($auditoria['status'] === 'Planejada' || $auditoria['status'] === 'Cancelada'): ?>
                                    <span class="text-muted">-</span>
                                <?php else: ?>
                                    <div class="progress progress-sm" role="progressbar" aria-label="Progresso da auditoria" aria-valuenow="<?= $progresso ?>" aria-valuemin="0" aria-valuemax="100" title="<?= $progresso ?>% concluído (<?= $auditoria['itens_respondidos'] ?> de <?= $auditoria['total_itens'] ?> itens)">
                                        <div class="progress-bar <?= $corProgresso ?> progress-bar-striped <?= $progresso < 100 ? 'progress-bar-animated' : '' ?>" style="width: <?= $progresso ?>%"></div>
                                    </div>
                                     <span class="d-block x-small mt-1 text-muted"><?= $progresso ?>%</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small text-nowrap <?= $classePrazo ?>" title="Início: <?= htmlspecialchars(gmdate('d/m/Y', strtotime($auditoria['data_inicio_planejada']))) ?> | Fim: <?= htmlspecialchars(gmdate('d/m/Y', strtotime($auditoria['data_fim_planejada'] ?? 'N/A'))) ?>">
                                <?= $auditoria['data_inicio_planejada'] ? htmlspecialchars(gmdate('d/m/Y', strtotime($auditoria['data_inicio_planejada']))) : '--' ?>
                            </td>
                            <td class="text-center action-buttons-table">
                                <div class="d-inline-flex flex-nowrap">
                                     <?php // Ajustar links para as novas subpastas
                                        $linkRevisar = BASE_URL . "gestor/auditoria/revisar_auditoria.php?id=" . $auditoria['id'];
                                        $linkVer = BASE_URL . "gestor/auditoria/detalhes_auditoria.php?id=" . $auditoria['id']; // Assumindo que ver_auditoria se chama detalhes_auditoria
                                        $linkEditar = BASE_URL . "gestor/auditoria/editar_auditoria.php?id=" . $auditoria['id'];
                                        $linkExcluirFormAction = BASE_URL . "gestor/auditoria/excluir_auditoria.php"; // Assumindo que o handler de exclusão está lá
                                    ?>
                                    <?php if ($status == 'Concluída (Auditor)' || $status == 'Em Revisão'): ?>
                                        <a href="<?= $linkRevisar ?>" class="btn btn-sm btn-warning rounded-circle p-0 me-1 action-btn" style="width: 28px; height: 28px; line-height: 28px;" title="Revisar Auditoria"><i class="fas fa-check-double fa-xs"></i></a>
                                    <?php elseif (!in_array($status, ['Planejada'])): // Para a maioria dos outros status, só visualiza ?>
                                        <a href="<?= $linkVer ?>" class="btn btn-sm btn-outline-primary rounded-circle p-0 me-1 action-btn" style="width: 28px; height: 28px; line-height: 28px;" title="Ver Detalhes"><i class="fas fa-eye fa-xs"></i></a>
                                    <?php endif; ?>

                                    <?php if ($status == 'Planejada'): ?>
                                         <!-- Ver detalhes também para Planejada (já que o acima não cobre) -->
                                         <a href="<?= $linkVer ?>" class="btn btn-sm btn-outline-primary rounded-circle p-0 me-1 action-btn" style="width: 28px; height: 28px; line-height: 28px;" title="Ver Detalhes Planejamento"><i class="fas fa-search fa-xs"></i></a>
                                        <a href="<?= $linkEditar ?>" class="btn btn-sm btn-outline-secondary rounded-circle p-0 me-1 action-btn" style="width: 28px; height: 28px; line-height: 28px;" title="Editar Planejamento"><i class="fas fa-pencil-alt fa-xs"></i></a>
                                    <?php elseif (!in_array($status, ['Concluída (Auditor)', 'Em Revisão'])): // Não pode editar se já está para revisar ou além ?>
                                        <button class="btn btn-sm btn-outline-secondary rounded-circle p-0 me-1 action-btn disabled" style="width: 28px; height: 28px; line-height: 28px;" title="Edição não permitida" disabled><i class="fas fa-pencil-alt fa-xs"></i></button>
                                    <?php endif; ?>

                                    <?php if ($status == 'Planejada' || $status == 'Cancelada'): // Permitir excluir Planejada ou Cancelada ?>
                                        <form method="POST" action="<?= $linkExcluirFormAction ?>" class="d-inline" onsubmit="return confirm('Tem certeza que deseja EXCLUIR esta auditoria? Esta ação não pode ser desfeita.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_form) ?>">
                                            <input type="hidden" name="auditoria_id" value="<?= $auditoria['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle p-0 action-btn" style="width: 28px; height: 28px; line-height: 28px;" title="Excluir Auditoria"><i class="fas fa-trash-alt fa-xs"></i></button>
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
    <?php if ($totalPaginas > 1): ?>
        <div class="card-footer bg-light border-top d-flex justify-content-center py-2">
            <nav aria-label="Paginação de auditorias">
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    // Reconstruir links de paginação com todos os filtros GET atuais
                    $linkPaginacaoBase = BASE_URL . 'gestor/auditoria/minhas_auditorias.php';
                    $queryParamsPaginacao = $_GET; // Pega todos os GET atuais
                    unset($queryParamsPaginacao['pagina']); // Remove 'pagina' para adicionar depois
                    $queryStringBase = http_build_query($queryParamsPaginacao);
                    if (!empty($queryStringBase)) $queryStringBase .= '&';
                    ?>
                    <li class="page-item <?= ($paginaAtual <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="<?= $linkPaginacaoBase ?>?<?= $queryStringBase ?>pagina=<?= ($paginaAtual - 1) ?>">«</a></li>
                    <?php $inicio = max(1, $paginaAtual - 2); $fim = min($totalPaginas, $paginaAtual + 2); if ($inicio > 1) { echo '<li class="page-item"><a class="page-link" href="' . $linkPaginacaoBase . '?' . $queryStringBase . 'pagina=1">1</a></li>'; if ($inicio > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } } for ($i = $inicio; $i <= $fim; $i++){ echo '<li class="page-item ' . ($i == $paginaAtual ? 'active' : '') . '"><a class="page-link" href="' . $linkPaginacaoBase . '?' . $queryStringBase . 'pagina=' . $i . '">' . $i . '</a></li>'; } if ($fim < $totalPaginas) { if ($fim < $totalPaginas - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } echo '<li class="page-item"><a class="page-link" href="' . $linkPaginacaoBase . '?' . $queryStringBase . 'pagina=' . $totalPaginas . '">' . $totalPaginas . '</a></li>'; } ?>
                    <li class="page-item <?= ($paginaAtual >= $totalPaginas) ? 'disabled' : '' ?>"><a class="page-link" href="<?= $linkPaginacaoBase ?>?<?= $queryStringBase ?>pagina=<?= ($paginaAtual + 1) ?>">»</a></li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
<script>
    // Script para popular o campo oculto `filtro_tipo_responsavel` ao mudar o select de responsável
    document.addEventListener('DOMContentLoaded', function() {
        const selectResponsavel = document.getElementById('filtro_responsavel_id');
        const inputTipoResponsavel = document.getElementById('filtro_tipo_responsavel');

        if (selectResponsavel && inputTipoResponsavel) {
            selectResponsavel.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const tipo = selectedOption.dataset.tipo || '';
                inputTipoResponsavel.value = tipo;
            });

            // Para garantir que o valor correto é submetido se já houver uma seleção no carregamento da página
            if (selectResponsavel.value) {
                const selectedOptionOnInit = selectResponsavel.options[selectResponsavel.selectedIndex];
                inputTipoResponsavel.value = selectedOptionOnInit.dataset.tipo || '';
            }
        }
    });
</script>
<?php
echo getFooterGestor();
?>