<?php
// gestor/dashboard_gestor.php - Versão Funcional com Gráficos e Modal Primeiro Acesso

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_gestor.php'; // Layout do Gestor
require_once __DIR__ . '/../includes/gestor_functions.php'; // Funções específicas do Gestor
require_once __DIR__ . '/../includes/admin_functions.php'; // Para dbGetDadosUsuario e getLogsAcesso

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    header('Location: ' . BASE_URL . 'acesso_negado.php'); exit;
}

// --- Obter dados específicos do Gestor ---
$gestor_id = $_SESSION['usuario_id'];
$empresa_id = $_SESSION['usuario_empresa_id'];
$empresa_nome = $_SESSION['empresa_nome'] ?? 'Minha Empresa';

// --- Lógica de Mensagens ---
$sucesso_msg = $_SESSION['sucesso'] ?? null;
$erro_msg = $_SESSION['erro'] ?? null;
unset($_SESSION['sucesso'], $_SESSION['erro']);

// --- VERIFICAR PRIMEIRO ACESSO DO GESTOR ---
$usuario_gestor = dbGetDadosUsuario($conexao, $gestor_id); // Usar a função geral
if (!$usuario_gestor) { header('Location: ' . BASE_URL . 'logout.php?erro=dados_usuario_invalido'); exit; }
$primeiro_acesso = ($usuario_gestor['primeiro_acesso'] == 1); // Verifica o campo para o gestor

// --- Buscar Dados para o Dashboard (Apenas se NÃO for primeiro acesso) ---
$statsGestor = [];
$auditoriasParaRevisar = [];
$auditoriasRecentes = [];
$auditoresAtivos = [];
$statusChartData = ['labels' => [], 'data' => []];
$conformidadeChartData = ['labels' => [], 'data' => []];
$logsRecentes = []; // Inicializa logs recentes também

if (!$primeiro_acesso) {
    $statsGestor = getGestorDashboardStats($conexao, $empresa_id);
    $auditoriasParaRevisar = getAuditoriasParaRevisar($conexao, $empresa_id, 5);
    $auditoriasRecentes = getAuditoriasRecentesEmpresa($conexao, $empresa_id, 5);
    $auditoresAtivos = getAuditoresDaEmpresa($conexao, $empresa_id, 5);
    $statusChartData = getAuditoriaStatusChartData($conexao, $empresa_id);
    $conformidadeChartData = getConformidadeChartData($conexao, $empresa_id);
    // Buscar logs recentes (usando a função geral do admin, mas poderia ter uma específica)
    $logsRecentesData = getLogsAcesso($conexao, 1, 5); // Pega os 5 últimos logs GERAIS (poderia filtrar por empresa/usuários da empresa)
    $logsRecentes = $logsRecentesData['logs'] ?? [];
}

// --- Geração do HTML ---
$title = "Dashboard Gestor - " . htmlspecialchars($empresa_nome);
// Usa header do gestor (que abre <main>)
// Nota: getHeaderGestor NÃO deve abrir a div .main-content-fluid, fazemos isso aqui se necessário.
echo getHeaderGestor($title);
?>

    <?php /* ----- MODAL DE PRIMEIRO ACESSO (Exibido condicionalmente ANTES do <main>) ----- */ ?>
    <?php if ($primeiro_acesso): ?>
    <div id="bloqueio-conteudo" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1060; display: flex; align-items: center; justify-content: center;">
        <div class="modal fade show" id="primeiroAcessoModal" tabindex="-1" aria-labelledby="primeiroAcessoModalLabel" aria-modal="true" role="dialog" style="display: block;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg border-0">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="primeiroAcessoModalLabel"><i class="fas fa-shield-alt me-2"></i>Primeiro Acesso - Redefinir Senha</h5>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted mb-3">Por segurança, você precisa definir uma nova senha para continuar.</p>
                        <?php /* Formulário idêntico ao do admin, JS tratará */ ?>
                        <form id="formRedefinirSenha" novalidate>
                             <input type="hidden" name="csrf_token_modal" value="<?= htmlspecialchars($csrf_token) ?>">
                            <div class="mb-3">
                                <label for="nova_senha_modal" class="form-label form-label-sm">Nova Senha</label>
                                <input type="password" class="form-control form-control-sm" id="nova_senha_modal" name="nova_senha" required minlength="8" aria-describedby="novaSenhaHelp">
                                <div id="novaSenhaHelp" class="form-text small">Mínimo 8 caracteres, com maiúscula, minúscula e número.</div>
                                <div class="invalid-feedback small">Senha inválida. Verifique os requisitos.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirmar_senha_modal" class="form-label form-label-sm">Confirmar Nova Senha</label>
                                <input type="password" class="form-control form-control-sm" id="confirmar_senha_modal" name="confirmar_senha" required>
                                <div class="invalid-feedback small">As senhas não coincidem.</div>
                            </div>
                            <div id="senha_error" class="alert alert-danger alert-sm mt-3 p-2 small" style="display: none;"></div>
                            <div id="senha_sucesso" class="alert alert-success alert-sm mt-3 p-2 small" style="display: none;"></div>
                            <button type="submit" class="btn btn-primary w-100 mt-3">Redefinir Senha</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php /* Se for primeiro acesso, não exibe o resto da dashboard abaixo */ ?>
    <?php else: ?>
        <?php /* ----- Conteúdo Normal do Dashboard (Dentro do <main> aberto pelo layout) ----- */ ?>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
            <div>
                <h1 class="h2 fw-bold"><i class="fas fa-user-tie me-2"></i>Dashboard do Gestor</h1>
                <p class="text-muted mb-0">Empresa: <strong><?= htmlspecialchars($empresa_nome) ?></strong></p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="<?= BASE_URL ?>gestor/criar_auditoria.php" class="btn btn-sm btn-success shadow-sm">
                    <i class="fas fa-plus me-1"></i> Nova Auditoria
                </a>
                <a href="#" class="btn btn-sm btn-outline-secondary ms-2 disabled">
                    <i class="fas fa-file-alt me-1"></i> Relatórios Empresa
                </a>
            </div>
        </div>

        <?php /* Notificações */ ?>
        <?php if ($sucesso_msg): ?><div class="alert alert-success d-flex align-items-center alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert"><i class="fas fa-check-circle flex-shrink-0 me-2"></i><div><?= htmlspecialchars($sucesso_msg) ?></div><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
        <?php if ($erro_msg): ?><div class="alert alert-danger d-flex align-items-center alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert"><i class="fas fa-exclamation-triangle flex-shrink-0 me-2"></i><div><?= htmlspecialchars($erro_msg) ?></div><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

        <?php /* Cards de Stats */ ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-4">
             <?php $statCardsGestor = [ ['color' => 'primary', 'icon' => 'fa-hourglass-half', 'title' => 'Auditorias Ativas', 'value' => $statsGestor['total_ativas'] ?? 0, 'link' => BASE_URL . 'gestor/minhas_auditorias.php?status=ativas'], ['color' => 'warning', 'icon' => 'fa-clipboard-list', 'title' => 'Pendentes de Revisão', 'value' => $statsGestor['para_revisao'] ?? 0, 'link' => BASE_URL . 'gestor/minhas_auditorias.php?status=revisar'], ['color' => 'danger', 'icon' => 'fa-times-circle', 'title' => 'NCs Abertas*', 'value' => $statsGestor['nao_conformidades_abertas'] ?? 0, 'link' => '#'], ['color' => 'success', 'icon' => 'fa-users-cog', 'title' => 'Auditores Ativos', 'value' => $statsGestor['auditores_ativos'] ?? 0, 'link' => BASE_URL . 'gestor/gerenciar_auditores.php'] ]; ?>
            <?php foreach ($statCardsGestor as $card): ?> <div class="col"> <a class="card text-decoration-none shadow-sm border-start border-5 border-<?= $card['color'] ?> h-100 dashboard-stat-card" href="<?= $card['link'] ?>"><div class="card-body d-flex justify-content-between align-items-center py-3 px-3"><div><div class="text-muted small text-uppercase fw-semibold mb-1"><?= $card['title'] ?></div><div class="fs-3 fw-bolder"><?= $card['value'] ?></div></div><i class="fas <?= $card['icon'] ?> fa-2x text-black-50 opacity-50"></i></div></a> </div> <?php endforeach; ?>
             <div class="col-12"><small class="text-muted fst-italic">*Contagem de NCs em auditorias não finalizadas (exemplo).</small></div>
        </div>


        <?php /* Linha com Gráficos e Pendências */ ?>
        <div class="row g-4">
            <div class="col-lg-7 order-lg-1 mb-4 mb-lg-0">
                <?php /* Sub-linha para os dois gráficos */ ?>
                <div class="row g-4">
                    <div class="col-md-6"><div class="card shadow-sm h-100"><div class="card-header bg-white border-0 pt-3 pb-0"><h6 class="mb-0 fw-bold"><i class="fas fa-tasks me-2 text-primary"></i>Status das Auditorias</h6></div><div class="card-body d-flex align-items-center justify-content-center p-2"><?php if (!empty($statusChartData['labels'])): ?><canvas id="statusAuditoriaChart" style="display: block; max-height: 200px; width: 100%;"></canvas><?php else: ?><div class="text-center text-muted py-4 small"><i class="fas fa-info-circle mb-1 d-block"></i> Nenhuma auditoria.</div><?php endif; ?></div></div></div>
                    <div class="col-md-6"><div class="card shadow-sm h-100"><div class="card-header bg-white border-0 pt-3 pb-0"><h6 class="mb-0 fw-bold"><i class="fas fa-check-double me-2 text-primary"></i>Conformidade (Aprovadas)</h6></div><div class="card-body d-flex align-items-center justify-content-center p-2"><?php $totalConformidade = array_sum($conformidadeChartData); ?><?php if ($totalConformidade > 0): ?><canvas id="conformidadeChart" style="display: block; max-height: 200px; width: 100%;"></canvas><?php else: ?><div class="text-center text-muted py-4 small"><i class="fas fa-info-circle mb-1 d-block"></i> Sem dados.</div><?php endif; ?></div></div></div>
                </div>

                <?php /* Auditorias Pendentes de Revisão */ ?>
                <div class="card shadow-sm mt-4"><div class="card-header bg-warning bg-opacity-10 border-warning"><h6 class="mb-0 fw-bold text-warning-emphasis"><i class="fas fa-exclamation-circle me-1"></i> Auditorias Aguardando Sua Revisão</h6></div><div class="list-group list-group-flush dashboard-log-list" style="max-height: 250px; overflow-y: auto;"><?php if (empty($auditoriasParaRevisar)): ?><div class="list-group-item text-center text-muted small py-3">Nenhuma auditoria aguardando revisão.</div><?php else: ?><?php foreach ($auditoriasParaRevisar as $auditoria): ?><a href="<?= BASE_URL ?>gestor/revisar_auditoria.php?id=<?= $auditoria['id'] ?>" class="list-group-item list-group-item-action py-2 px-3"><div class="d-flex w-100 justify-content-between"><span class="mb-1 fw-semibold text-primary"><?= htmlspecialchars($auditoria['titulo']) ?></span><small class="text-muted text-nowrap ps-2" title="Concluída pelo auditor">Concluída: <?= $auditoria['data_conclusao_auditor'] ? (new DateTime($auditoria['data_conclusao_auditor']))->format('d/m H:i') : 'N/D' ?></small></div><small class="text-muted">Auditor: <?= htmlspecialchars($auditoria['nome_auditor'] ?? 'N/D') ?></small></a><?php endforeach; ?><?php endif; ?></div></div>
            </div>

            <?php /* Coluna Lateral Direita */ ?>
            <div class="col-lg-5 order-lg-2"><div class="card shadow-sm mb-4"><div class="card-header bg-light d-flex justify-content-between align-items-center"><span><i class="fas fa-users-cog me-1"></i> Auditores Ativos</span><a href="<?= BASE_URL ?>gestor/gerenciar_auditores.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.8em;">Gerenciar</a></div><div class="list-group list-group-flush" style="max-height: 180px; overflow-y: auto;"><?php if (empty($auditoresAtivos)): ?><div class="list-group-item text-center text-muted small py-3">Nenhum auditor ativo.</div><?php else: ?><?php foreach ($auditoresAtivos as $auditor): $auditorFoto = !empty($auditor['foto']) ? BASE_URL . 'uploads/fotos/' . $auditor['foto'] : BASE_URL . 'assets/img/default_profile.png'; ?><div class="list-group-item d-flex align-items-center py-2 px-3"><img src="<?= $auditorFoto ?>" alt="Foto" width="30" height="30" class="rounded-circle me-2 object-fit-cover"><div class="small lh-sm flex-grow-1"><strong class="d-block"><?= htmlspecialchars($auditor['nome']) ?></strong><span class="text-muted text-truncate d-block"><?= htmlspecialchars($auditor['email']) ?></span></div></div><?php endforeach; ?><?php endif; ?></div></div><div class="card shadow-sm"><div class="card-header bg-light d-flex justify-content-between align-items-center"><span><i class="fas fa-history me-1"></i> Últimas Atividades</span><a href="<?= BASE_URL ?>admin/logs.php?empresa_id=<?= $empresa_id ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.8em;">Ver Todos</a></div><div class="list-group list-group-flush dashboard-log-list" style="max-height: 200px; overflow-y: auto;"><?php if (empty($logsRecentes)): ?><div class="list-group-item text-center text-muted small py-3">Nenhuma atividade recente.</div><?php else: ?><?php foreach ($logsRecentes as $log): $log_data = new DateTime($log['data_hora']); $isSuccess=(bool)$log['sucesso']; $iconClass=$isSuccess?'fa-check-circle text-success':'fa-times-circle text-danger'; $userText=htmlspecialchars($log['nome_usuario']??'Sistema'); $actionText=htmlspecialchars($log['acao']); $details=htmlspecialchars(mb_strimwidth($log['detalhes']??'',0,60,"...")); ?><div class="list-group-item px-3 py-2 lh-sm"><div class="d-flex w-100 justify-content-between mb-0"><div><i class="fas <?= $iconClass ?> fa-fw me-2 small"></i><strong class="fw-semibold small"><?= $actionText ?></strong></div><small class="text-muted text-nowrap ps-2" title="<?= $log_data->format('d/m/Y H:i:s') ?>"><?= $log_data->format('d/m H:i') ?></small></div><div class="text-muted small ps-4"><span class="me-1" title="Usuário: <?= $userText ?>"> <i class="fas fa-user fa-xs"></i> <?= $userText ?></span><?php if(!empty($log['detalhes'])): ?><span class="d-block fst-italic mt-1 text-truncate" title="<?= htmlspecialchars($log['detalhes']) ?>">↳ <?= $details ?></span><?php endif; ?></div></div><?php endforeach; ?><?php endif; ?></div></div></div>
        </div>
    <?php endif; /* Fim do else (não é primeiro acesso) */ ?>

<?php
// Fechamento do <main> aberto em getHeaderGestor
// Se getHeaderGestor não abre <main>, remova esta linha. ASSUMINDO QUE ABRE:
?>
</main>

<?php
echo getFooterGestor(); // Usa footer do gestor
?>

<?php /* ----- Scripts para Inicializar os Gráficos (DEPOIS do footer) ----- */ ?>
<?php if (!$primeiro_acesso): ?>
<script>
function initGestorCharts() { /* Função igual à anterior */
    if (typeof Chart === 'undefined') { setTimeout(initGestorCharts, 150); return; }
    const ctxStatus = document.getElementById('statusAuditoriaChart'); const statusLabels = <?= json_encode($statusChartData['labels'] ?? []) ?>; const statusData = <?= json_encode($statusChartData['data'] ?? []) ?>; const statusColors = {'Planejada':'rgba(13,110,253,0.7)','Em Andamento':'rgba(108,117,125,0.7)','Pausada':'rgba(108,117,125,0.4)','Concluída (Auditor)':'rgba(255,193,7,0.7)','Em Revisão':'rgba(255,193,7,0.9)','Aprovada':'rgba(25,135,84,0.7)','Rejeitada':'rgba(220,53,69,0.7)','Cancelada':'rgba(108,117,125,0.2)'}; const statusBackgroundColors = statusLabels.map(label => statusColors[label] || '#cccccc');
    if (ctxStatus && statusLabels.length > 0) { let existingStatusChart = Chart.getChart(ctxStatus); if (existingStatusChart) existingStatusChart.destroy(); new Chart(ctxStatus, { type: 'doughnut', data: { labels: statusLabels, datasets: [{ data: statusData, backgroundColor: statusBackgroundColors, borderWidth: 1, borderColor: '#fff' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display:true, position: 'bottom', labels:{ padding:10, boxWidth: 10, font: { size: 10 }} } } } }); }
    const ctxConformidade = document.getElementById('conformidadeChart'); const conformidadeData = <?= json_encode($conformidadeChartData ?? []) ?>; const conformidadeLabels = Object.keys(conformidadeData); const conformidadeValues = Object.values(conformidadeData); const conformidadeColors = ['rgba(25,135,84,0.7)','rgba(220,53,69,0.7)','rgba(255,193,7,0.7)','rgba(108,117,125,0.7)']; const totalConformidadeItens = conformidadeValues.reduce((a, b) => a + b, 0);
    if (ctxConformidade && conformidadeLabels.length > 0 && totalConformidadeItens > 0) { let existingConfChart = Chart.getChart(ctxConformidade); if (existingConfChart) existingConfChart.destroy(); new Chart(ctxConformidade, { type: 'doughnut', data: { labels: conformidadeLabels, datasets: [{ data: conformidadeValues, backgroundColor: conformidadeColors, borderWidth: 1, borderColor: '#fff' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display:true, position: 'bottom', labels:{ padding:10, boxWidth: 10, font: { size: 10 }} } } } }); }
}
function hexToRgba(hex, alpha = 1) { const bigint = parseInt(hex.slice(1), 16); const r = (bigint >> 16) & 255; const g = (bigint >> 8) & 255; const b = bigint & 255; return `rgba(${r}, ${g}, ${b}, ${alpha})`; }
document.addEventListener('DOMContentLoaded', initGestorCharts);
</script>
<?php endif; ?>