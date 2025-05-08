<?php
// auditor/minhas_auditorias_auditor.php - Listagem de Auditorias para o Auditor

require_once __DIR__ . '/../includes/config.php'; // Ajuste o caminho
require_once __DIR__ . '/../includes/layout_auditor.php'; // Usa o layout do AUDITOR
require_once __DIR__ . '/../includes/auditor_functions.php'; // Onde está getMinhasAuditoriasAuditor
require_once __DIR__ . '/../includes/db.php'; // Se funções auxiliares estiverem aqui

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'auditor') {
    header('Location: ' . BASE_URL . 'acesso_negado.php'); exit;
}

$auditor_id = (int)$_SESSION['usuario_id'];
$empresa_id = (int)$_SESSION['usuario_empresa_id'];

// --- Processar Filtros e Paginação ---
$filtros = [];
$filtro_titulo = trim(filter_input(INPUT_GET, 'filtro_titulo', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filtro_status = trim(filter_input(INPUT_GET, 'filtro_status', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'todos');
$filtro_gestor_id = filter_input(INPUT_GET, 'filtro_gestor_id', FILTER_VALIDATE_INT);
// Adicionar mais filtros se necessário (ex: datas)

if (!empty($filtro_titulo)) $filtros['titulo'] = $filtro_titulo;
if ($filtro_status !== 'todos') $filtros['status'] = $filtro_status;
if ($filtro_gestor_id) $filtros['gestor_id'] = $filtro_gestor_id;
// Adicionar filtros de data a $filtros['data_de'] / $filtros['data_ate'] se implementados

$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina = 15;

// Validação CSRF para filtros (se enviados via GET com token)
$csrf_get = $_GET['csrf_token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET) && !isset($_GET['pagina']) && !validar_csrf_token($csrf_get)) {
    // Validar apenas se filtros foram submetidos (evita bloquear paginação)
     if (!empty(array_intersect_key($_GET, array_flip(['filtro_titulo', 'filtro_status', 'filtro_gestor_id'])))) {
        definir_flash_message('erro', 'Token de segurança inválido. Tente filtrar novamente.');
        header('Location: ' . BASE_URL . 'auditor/minhas_auditorias_auditor.php');
        exit;
     }
}
$csrf_token_form = gerar_csrf_token(); // Token para formulário de filtros

// --- Buscar Dados ---
// Função principal que busca auditorias do auditor com filtros e paginação
$auditorias_data = getMinhasAuditoriasAuditor($conexao, $auditor_id, $empresa_id, $pagina_atual, $itens_por_pagina, $filtros);
$auditorias = $auditorias_data['auditorias'] ?? [];
$paginacao = $auditorias_data['paginacao'] ?? ['pagina_atual' => 1, 'total_paginas' => 0, 'total_itens' => 0, 'itens_por_pagina' => $itens_por_pagina];

// --- Buscar Dados para Filtros (ex: lista de gestores da empresa) ---
$gestores_empresa = []; // Array para popular o dropdown de gestores
try {
    $stmtGestores = $conexao->prepare("SELECT id, nome FROM usuarios WHERE empresa_id = :empresa_id AND perfil = 'gestor' AND ativo = 1 ORDER BY nome");
    $stmtGestores->execute([':empresa_id' => $empresa_id]);
    $gestores_empresa = $stmtGestores->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Erro ao buscar gestores para filtro auditor: " . $e->getMessage());
}
$status_disponiveis_filtro = ['Planejada', 'Em Andamento', 'Concluída (Auditor)', 'Em Revisão', 'Aprovada', 'Rejeitada', 'Cancelada']; // Ajuste conforme seus status

$page_title = "Minhas Auditorias";
echo getHeaderAuditor($page_title);

// Funções auxiliares de exibição (coloque em config.php ou helpers.php idealmente)
if (!function_exists('formatarDataSimples')) { function formatarDataSimples(?string $d, string $f='d/m/Y', string $def='N/D') { try { return empty($d) ? $def : (new DateTime($d))->format($f); } catch(Exception $e){ return $def; }} }
if (!function_exists('exibirBadgeStatusAuditoria')) { function exibirBadgeStatusAuditoria($s){ $c = 'bg-secondary'; /* ...lógica do badge... */ if ($s=='Planejada') $c='bg-light text-dark border'; elseif($s=='Em Andamento') $c='bg-primary'; elseif($s=='Concluída (Auditor)') $c='bg-warning text-dark'; elseif($s=='Em Revisão') $c='bg-info text-dark'; elseif($s=='Aprovada') $c='bg-success'; elseif($s=='Rejeitada'||$s=='Cancelada') $c='bg-danger'; return "<span class=\"badge rounded-pill $c px-2 py-1 x-small\">".htmlspecialchars($s).'</span>';}}
?>
<style>
    .filter-form .form-select-sm, .filter-form .form-control-sm { font-size: 0.85rem; }
    .table th { font-weight: 500; }
    .progress-sm { height: 0.6rem; background-color: #e9ecef;}
    .prazo-vencido { color: var(--bs-danger) !important; font-weight: bold; }
    .prazo-proximo { color: var(--bs-warning-text-emphasis) !important; } /* Cor de aviso para prazo próximo */
    .action-btn-xs { padding: 0.15rem 0.4rem; font-size: 0.75rem; } /* Botão menor */
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-tasks me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>auditor/dashboard_auditor.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Minhas Auditorias</li>
        </ol></nav>
    </div>
    <!-- Outros botões de ação se necessário -->
</div>

<?php if ($msg = obter_flash_message('sucesso')): ?><div class="alert alert-success auditor-alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($msg = obter_flash_message('erro')): ?><div class="alert alert-danger auditor-alert"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($msg = obter_flash_message('aviso')): ?><div class="alert alert-warning auditor-alert"><i class="fas fa-info-circle me-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>


<!-- Card de Filtros -->
<div class="card shadow-sm mb-4 rounded-3 border-0 filter-card">
    <div class="card-body p-3">
        <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="filter-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_form) ?>">
            <div class="row g-2 align-items-end">
                <div class="col-md-4 col-lg-4">
                    <label for="filtro_titulo" class="form-label form-label-sm">Título:</label>
                    <input type="search" name="filtro_titulo" id="filtro_titulo" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_titulo) ?>" placeholder="Buscar por título...">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="filtro_status" class="form-label form-label-sm">Status:</label>
                    <select name="filtro_status" id="filtro_status" class="form-select form-select-sm">
                        <option value="todos" <?= ($filtro_status === 'todos') ? 'selected' : '' ?>>Todos</option>
                        <?php foreach ($status_disponiveis_filtro as $status_opt): ?>
                        <option value="<?= htmlspecialchars($status_opt) ?>" <?= ($filtro_status === $status_opt) ? 'selected' : '' ?>><?= htmlspecialchars($status_opt) ?></option>
                        <?php endforeach; ?>
                         <option value="pendentes" <?= ($filtro_status === 'pendentes') ? 'selected' : '' ?>>Pendentes de Ação</option> <!-- Filtro especial -->
                    </select>
                </div>
                <div class="col-md-3 col-lg-3">
                    <label for="filtro_gestor_id" class="form-label form-label-sm">Gestor:</label>
                    <select name="filtro_gestor_id" id="filtro_gestor_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                         <?php foreach($gestores_empresa as $gestor_filtro): ?>
                            <option value="<?= $gestor_filtro['id'] ?>" <?= ($filtro_gestor_id == $gestor_filtro['id']) ? 'selected' : '' ?>><?= htmlspecialchars($gestor_filtro['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Adicionar filtros de data se necessário -->
                <div class="col-md-auto ms-md-auto mt-2 mt-md-0 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i> Filtrar</button>
                    <a href="<?= BASE_URL ?>auditor/minhas_auditorias_auditor.php" class="btn btn-sm btn-outline-secondary w-100" title="Limpar Filtros"><i class="fas fa-times"></i> Limpar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Card da Tabela -->
<div class="card shadow-sm dashboard-list-card rounded-3 border-0">
    <div class="card-header list-card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0 fw-bold d-flex align-items-center"><i class="fas fa-list-alt me-2 text-primary opacity-75"></i>Suas Auditorias Atribuídas</h6>
        <span class="badge bg-light text-dark border rounded-pill"><?= htmlspecialchars($paginacao['total_itens'] ?? 0) ?> encontrada(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th scope="col" class="text-center" style="width:5%">ID</th>
                    <th scope="col" style="width:28%">Título</th>
                    <th scope="col" style="width:15%">Gestor Resp.</th>
                    <th scope="col" style="width:15%">Sua Atribuição</th>
                    <th scope="col" class="text-center" style="width:10%">Status</th>
                    <th scope="col" class="text-center" style="width:12%">Progresso (Seu)</th>
                    <th scope="col" class="text-center" style="width:5%">Início</th>
                    <th scope="col" class="text-center" style="width:10%">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($auditorias)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-info-circle fa-2x mb-2 d-block opacity-50"></i> Nenhuma auditoria encontrada<?= !empty($filtros) ? ' com os filtros aplicados' : '' ?>.</td></tr>
                <?php else: ?>
                    <?php foreach ($auditorias as $aud):
                        // Determinar tipo de atribuição para este auditor nesta auditoria
                        $atribuicao_display = 'N/D';
                        $secoes_auditor = []; // Armazena seções se for de equipe
                        if ($aud['auditor_responsavel_id'] == $auditor_id) {
                            $atribuicao_display = 'Individual';
                        } elseif ($aud['equipe_id']) {
                            // Buscar seções específicas (A função getMinhasAuditoriasAuditor precisa retornar isso)
                             if (isset($aud['secoes_atribuidas_auditor']) && !empty($aud['secoes_atribuidas_auditor'])) {
                                $secoes_auditor = explode('|||', $aud['secoes_atribuidas_auditor']); // Assumindo que a função retorna concatenado com |||
                                $atribuicao_display = 'Equipe (' . count($secoes_auditor) . ' Seção(ões))';
                            } else {
                                 $atribuicao_display = 'Equipe (Erro?)'; // Se é de equipe mas não achou seção atribuída?
                            }
                        }

                        // Calcular progresso DESTE auditor
                        $progresso_auditor = 0;
                        $total_itens_auditor = $aud['total_itens_auditor'] ?? 0;
                        $itens_respondidos_auditor = $aud['itens_respondidos_auditor'] ?? 0;
                        if ($total_itens_auditor > 0) {
                            $progresso_auditor = round(($itens_respondidos_auditor / $total_itens_auditor) * 100);
                        }
                         $corProgressoAuditor = 'bg-secondary';
                         if ($progresso_auditor > 0 && $progresso_auditor < 30) $corProgressoAuditor = 'bg-danger';
                         elseif ($progresso_auditor >= 30 && $progresso_auditor < 70) $corProgressoAuditor = 'bg-warning';
                         elseif ($progresso_auditor >= 70 && $progresso_auditor < 100) $corProgressoAuditor = 'bg-info';
                         elseif ($progresso_auditor >= 100) $corProgressoAuditor = 'bg-success';

                        // Lógica para o botão de ação principal
                        $acao_principal_link = BASE_URL . 'auditor/detalhes_auditoria_readonly.php?id=' . $aud['id']; // Default: Ver detalhes
                        $acao_principal_texto = 'Ver';
                        $acao_principal_icon = 'fa-eye';
                        $acao_principal_class = 'btn-outline-primary';
                        if($aud['status'] === 'Planejada') {
                            // Idealmente, 'Iniciar' faria um POST, mas simplificamos para linkar para execução
                            $acao_principal_link = BASE_URL . 'auditor/executar_auditoria.php?id=' . $aud['id'] . '&action=iniciar'; // Adiciona action iniciar
                            $acao_principal_texto = 'Iniciar'; $acao_icon = 'fa-play'; $acao_principal_class = 'btn-success';
                        } elseif ($aud['status'] === 'Em Andamento' || $aud['status'] === 'Rejeitada') {
                             $acao_principal_link = BASE_URL . 'auditor/executar_auditoria.php?id=' . $aud['id'];
                             $acao_principal_texto = ($aud['status'] === 'Rejeitada') ? 'Corrigir' : 'Continuar';
                             $acao_icon = ($aud['status'] === 'Rejeitada') ? 'fa-exclamation-triangle' : 'fa-arrow-right';
                             $acao_principal_class = ($aud['status'] === 'Rejeitada') ? 'btn-danger' : 'btn-primary';
                        }
                    ?>
                        <tr>
                            <td class="text-center fw-bold">#<?= $aud['id'] ?></td>
                            <td class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($aud['titulo']) ?>">
                                <a href="<?= $acao_principal_link ?>" class="text-decoration-none text-dark fw-medium"><?= htmlspecialchars($aud['titulo']) ?></a>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($aud['nome_gestor'] ?? 'N/D') ?></td>
                            <td class="small text-muted" title="<?= !empty($secoes_auditor) ? 'Seções: ' . htmlspecialchars(implode(', ', $secoes_auditor)) : '' ?>">
                                <?= $atribuicao_display ?>
                            </td>
                            <td class="text-center"><?= exibirBadgeStatusAuditoria($aud['status']) ?></td>
                            <td class="text-center small">
                                <?php if ($aud['status'] === 'Planejada' || $aud['status'] === 'Cancelada'): echo '-';
                                else: ?>
                                    <div class="progress progress-sm" role="progressbar" aria-valuenow="<?= $progresso_auditor ?>" aria-valuemin="0" aria-valuemax="100" title="<?= $progresso_auditor ?>% dos seus itens respondidos (<?= $itens_respondidos_auditor ?>/<?= $total_itens_auditor ?>)">
                                        <div class="progress-bar <?= $corProgressoAuditor ?> progress-bar-striped <?= ($progresso_auditor < 100 && $aud['status'] === 'Em Andamento') ? 'progress-bar-animated' : '' ?>" style="width: <?= $progresso_auditor ?>%"></div>
                                    </div>
                                    <span class="d-block x-small mt-1 text-muted"><?= $progresso_auditor ?>%</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small text-nowrap">
                                <?= formatarDataSimples($aud['data_inicio_planejada'], 'd/m/y') ?>
                            </td>
                            <td class="text-center action-buttons-table">
                                <a href="<?= $acao_principal_link ?>" class="btn btn-<?= $acao_principal_class ?> btn-xs rounded-pill px-2 py-0" title="<?= $acao_texto ?> Auditoria">
                                    <i class="fas <?= $acao_icon ?> fa-xs"></i><span class="d-none d-md-inline ms-1"><?= $acao_texto ?></span>
                                </a>
                                <!-- Adicionar outras ações como 'Pausar' ou 'Ver Histórico' aqui -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div><!-- /.table-responsive -->

    <?php // Componente de Paginação (idêntico ao de gerenciar_auditores)
    if (isset($paginacao) && $paginacao['total_paginas'] > 1):
        $queryParamsPaginacao = $_GET; unset($queryParamsPaginacao['pagina']);
        $queryString = http_build_query($queryParamsPaginacao);
        if (!empty($queryString)) $queryString .= '&'; $linkBase = "?" . $queryString . "pagina=";
    ?>
        <div class="card-footer bg-light py-2 px-3">
            <nav aria-label="Paginação" class="d-flex justify-content-center">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($paginacao['pagina_atual'] <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="<?= $linkBase . ($paginacao['pagina_atual'] - 1) ?>">«</a></li>
                    <?php $range = 2; $start = max(1, $paginacao['pagina_atual'] - $range); $end = min($paginacao['total_paginas'], $paginacao['pagina_atual'] + $range); if ($start > 1) { echo '<li class="page-item"><a class="page-link" href="' . $linkBase . '1">1</a></li>'; if ($start > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } } for ($i = $start; $i <= $end; $i++) { echo '<li class="page-item ' . ($i == $paginacao['pagina_atual'] ? 'active' : '') . '"><a class="page-link" href="' . $linkBase . $i . '">' . $i . '</a></li>'; } if ($end < $paginacao['total_paginas']) { if ($end < $paginacao['total_paginas'] - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } echo '<li class="page-item"><a class="page-link" href="' . $linkBase . $paginacao['total_paginas'] . '">' . $paginacao['total_paginas'] . '</a></li>'; } ?>
                    <li class="page-item <?= ($paginacao['pagina_atual'] >= $paginacao['total_paginas']) ? 'disabled' : '' ?>"><a class="page-link" href="<?= $linkBase . ($paginacao['pagina_atual'] + 1) ?>">»</a></li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php
// Adicionar JS específico da página, se necessário (ex: para confirmação de 'Iniciar')
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectResponsavelFiltro = document.getElementById('filtro_gestor_id'); // Corrigido para filtro de GESTOR
        const inputTipoResponsavelFiltro = document.getElementById('filtro_tipo_responsavel'); // Ainda precisamos desse? Talvez não se só filtra por gestor.

         if(selectResponsavelFiltro && inputTipoResponsavelFiltro) {
            // Se o filtro de responsável fosse por Auditor ou Equipe, esta lógica faria sentido.
            // Como agora é por GESTOR, não precisamos do data-tipo e do campo oculto tipo_responsavel.
            // Removendo a lógica JS que popula o campo oculto.
             // Certifique-se que o filtro no backend em getMinhasAuditoriasAuditor usa 'gestor_id' se filtro_gestor_id for passado.
             console.log("Filtro por Gestor selecionado: " + selectResponsavelFiltro.value);
        }

         // Lógica para confirmar 'Iniciar' Auditoria (se o botão 'Iniciar' fosse um submit de formulário)
         // const iniciarForms = document.querySelectorAll('.form-iniciar-auditoria');
         // iniciarForms.forEach(form => {
         //     form.addEventListener('submit', function(e) {
         //         if (!confirm('Tem certeza que deseja iniciar esta auditoria? Esta ação não pode ser desfeita.')) {
         //             e.preventDefault();
         //         }
         //     });
         // });
    });
</script>
<?php
echo getFooterAuditor();
?>