<?php
// gestor/minhas_auditorias.php
// Página: Minhas Auditorias (Gestor)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_gestor.php';
require_once __DIR__ . '/../includes/gestor_functions.php';

// --- Segurança e Sessão ---
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    header('Location: ' . BASE_URL . 'acesso_negado.php'); exit;
}
$empresa_id = $_SESSION['usuario_empresa_id'];
$gestor_id = $_SESSION['usuario_id'];

// --- Validação CSRF para filtros ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET) && !validar_csrf_token($_GET['csrf_token'] ?? '')) {
    definir_flash_message('erro', 'Token CSRF inválido.');
    header('Location: ' . BASE_URL . 'gestor/minhas_auditorias.php');
    exit;
}

// --- Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// --- Filtros e Paginação ---
$paginaAtual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]) ?: 1;
$itensPorPagina = 15;
$filtro = trim(filter_input(INPUT_GET, 'titulo_busca', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

// --- Buscar Auditorias ---
$dadosAuditorias = getMinhasAuditorias($conexao, $gestor_id, $empresa_id, $paginaAtual, $itensPorPagina, $filtro);
$auditorias = $dadosAuditorias['auditorias'] ?? [];
$totalRegistros = $dadosAuditorias['total'] ?? 0;
$totalPaginas = $totalRegistros > 0 ? ceil($totalRegistros / $itensPorPagina) : 1;


// --- Buscar dados para filtros ---
$auditores_filtro = getAuditoresDaEmpresa($conexao, $empresa_id);

// --- Geração do HTML ---
$title = "Minhas Auditorias";
echo getHeaderGestor($title);

?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-clipboard-list me-2 text-primary"></i>Minhas Auditorias</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb"><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page">Auditorias</li></ol></nav>
    </div>
    <div class="btn-toolbar page-actions">
        <a href="<?= BASE_URL ?>gestor/criar_auditoria.php" class="btn btn-primary rounded-pill shadow-sm px-3 action-button-main">
            <i class="fas fa-plus me-1"></i> Criar Nova Auditoria
        </a>
    </div>
</div>

<?php if ($sucesso_msg): ?>
<div class="alert alert-success gestor-alert fade show" role="alert">
    <i class="fas fa-check-circle flex-shrink-0 me-2"></i>
    <span><?= nl2br(htmlspecialchars($sucesso_msg)) ?></span>
    <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
<?php if ($erro_msg): ?>
<div class="alert alert-danger gestor-alert fade show" role="alert">
    <i class="fas fa-exclamation-triangle flex-shrink-0 me-2"></i>
    <span><?= nl2br(htmlspecialchars($erro_msg)) ?></span>
    <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4 rounded-3 border-0 filter-card">
    <div class="card-body p-3">
        <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="filter-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerar_csrf_token()) ?>">
            <div class="row g-2 align-items-end">
                <div class="col-md-4 col-lg-3">
                    <label for="titulo_busca" class="form-label form-label-sm">Buscar por Título:</label>
                    <input type="search" name="titulo_busca" id="titulo_busca" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro) ?>">
                </div>
                <div class="col-auto ms-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i> Filtrar</button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-sm btn-outline-secondary ms-1" title="Limpar Filtros"><i class="fas fa-times"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm dashboard-list-card rounded-3 border-0">
    <div class="card-header list-card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0 fw-bold d-flex align-items-center"><i class="fas fa-list-alt me-2 text-primary opacity-75"></i>Lista de Auditorias</h6>
        <span class="badge bg-light text-dark border rounded-pill"><?= $totalRegistros ?> auditoria(s) encontrada(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped table-sm align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th scope="col" class="text-center" style="width:5%">ID</th>
                    <th scope="col" style="width:30%">Título</th>
                    <th scope="col" style="width:15%">Modelo</th>
                    <th scope="col" style="width:15%">Auditor</th>
                    <th scope="col" class="text-center" style="width:15%">Status</th>
                    <th scope="col" class="text-center" style="width:10%">Início Plan.</th>
                    <th scope="col" class="text-center" style="width:10%">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($auditorias)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-info-circle fa-2x mb-2 d-block opacity-50"></i> Nenhuma auditoria encontrada<?= $filtro ? ' com os filtros aplicados' : '' ?>.</td></tr>
                <?php else: ?>
                    <?php foreach ($auditorias as $auditoria): ?>
                        <tr>
                            <td class="text-center fw-bold">#<?= htmlspecialchars($auditoria['id']) ?></td>
                            <td class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($auditoria['titulo']) ?>">
                                <?= htmlspecialchars($auditoria['titulo']) ?>
                            </td>
                            <td class="small text-muted">
                                <?php
                                $modelo_id = $auditoria['modelo_id'] ?? null;
                                if ($modelo_id) {
                                    $sql = "SELECT nome FROM modelos_auditoria WHERE id = :id";
                                    $stmt = $conexao->prepare($sql);
                                    $stmt->execute([':id' => $modelo_id]);
                                    $modelo = $stmt->fetch(PDO::FETCH_ASSOC);
                                    echo htmlspecialchars($modelo['nome'] ?? 'N/A');
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($auditoria['auditor_nome'] ?? '<em class="text-black-50">Não atribuído</em>') ?></td>
                            <td class="text-center">
                                <?php
                                $status = $auditoria['status'];
                                $badgeClass = 'bg-secondary';
                                if ($status == 'Planejada') $badgeClass = 'bg-light text-dark border';
                                elseif ($status == 'Em Andamento') $badgeClass = 'bg-primary';
                                elseif ($status == 'Em Revisão' || $status == 'Concluída (Auditor)') $badgeClass = 'bg-warning text-dark';
                                elseif ($status == 'Aprovada') $badgeClass = 'bg-success';
                                elseif ($status == 'Rejeitada' || $status == 'Cancelada') $badgeClass = 'bg-danger';
                                elseif ($status == 'Pausada') $badgeClass = 'bg-secondary';
                                ?>
                                <span class="badge rounded-pill <?= $badgeClass ?> x-small fw-semibold"><?= htmlspecialchars($status) ?></span>
                            </td>
                            <td class="text-center small text-nowrap">
                                <?= $auditoria['data_inicio_planejada'] ? formatarDataRelativa($auditoria['data_inicio_planejada']) : '--' ?>
                            </td>
                            <td class="text-center action-buttons-table">
                                <div class="d-inline-flex flex-nowrap">
                                    <?php if ($auditoria['status'] == 'Concluída (Auditor)' || $auditoria['status'] == 'Em Revisão'): ?>
                                        <a href="<?= BASE_URL ?>gestor/revisar_auditoria.php?id=<?= $auditoria['id'] ?>" class="btn btn-sm btn-warning rounded-circle p-0 me-1 action-btn" style="width: 28px; height: 28px; line-height: 28px;" title="Revisar Auditoria"><i class="fas fa-check-double fa-xs"></i></a>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>gestor/ver_auditoria.php?id=<?= $auditoria['id'] ?>" class="btn btn-sm btn-outline-primary rounded-circle p-0 me-1 action-btn" style="width: 28px; height: 28px; line-height: 28px;" title="Ver Detalhes"><i class="fas fa-eye fa-xs"></i></a>
                                    <?php endif; ?>
                                    <?php if ($auditoria['status'] == 'Planejada'): ?>
                                        <a href="<?= BASE_URL ?>gestor/editar_auditoria.php?id=<?= $auditoria['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-circle p-0 me-1 action-btn" style="width: 28px; height: 28px; line-height: 28px;" title="Editar Planejamento"><i class="fas fa-pencil-alt fa-xs"></i></a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary rounded-circle p-0 me-1 action-btn disabled" style="width: 28px; height: 28px; line-height: 28px;" title="Edição não permitida neste status" disabled><i class="fas fa-pencil-alt fa-xs"></i></button>
                                    <?php endif; ?>
                                    <?php if ($auditoria['status'] == 'Planejada'): ?>
                                        <form method="POST" action="<?= BASE_URL ?>gestor/excluir_auditoria.php" class="d-inline" onsubmit="return confirm('Tem certeza que deseja EXCLUIR esta auditoria planejada?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerar_csrf_token()) ?>">
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
                    $linkPaginacaoBase = rtrim(BASE_URL, '/') . '/gestor/minhas_auditorias.php?';
                    $queryParams = $filtro ? ['titulo_busca' => $filtro, 'csrf_token' => gerar_csrf_token()] : ['csrf_token' => gerar_csrf_token()];
                    $queryParams['pagina'] = '';
                    $linkPaginacaoBase .= http_build_query($queryParams) . '&pagina=';
                    ?>
                    <li class="page-item <?= ($paginaAtual <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="<?= $linkPaginacaoBase . ($paginaAtual - 1) ?>">«</a></li>
                    <?php
                    $inicio = max(1, $paginaAtual - 2);
                    $fim = min($totalPaginas, $paginaAtual + 2);
                    if ($inicio > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    for ($i = $inicio; $i <= $fim; $i++):
                    ?>
                        <li class="page-item <?= ($i == $paginaAtual) ? 'active' : '' ?>"><a class="page-link" href="<?= $linkPaginacaoBase . $i ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($fim < $totalPaginas) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                    <li class="page-item <?= ($paginaAtual >= $totalPaginas) ? 'disabled' : '' ?>"><a class="page-link" href="<?= $linkPaginacaoBase . ($paginaAtual + 1) ?>">»</a></li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php
echo getFooterGestor();
?>