<?php
// admin/dashboard_admin.php - VERSÃO COM AJUSTES DE RESPONSIVIDADE

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php';

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens ---
$sucesso_msg = $_SESSION['sucesso'] ?? null;
$erro_msg = $_SESSION['erro'] ?? null;
unset($_SESSION['sucesso'], $_SESSION['erro']);

// --- Verificar Primeiro Acesso ---
$usuario_id = $_SESSION['usuario_id'];
$usuario = dbGetDadosUsuario($conexao, $usuario_id);
if (!$usuario) { header('Location: ' . BASE_URL . 'logout.php?erro=usuario_invalido'); exit; }
$primeiro_acesso = ($usuario['primeiro_acesso'] == 1);

// --- Buscar Dados para o Dashboard ---
$counts = ['solicitacoes_acesso' => 0, 'solicitacoes_reset' => 0, 'usuarios_ativos' => 0, 'total_empresas' => 0, 'total_requisitos' => 0];
$logsRecentes = [];
$chartData = ['labels' => [], 'data' => []];

if (!$primeiro_acesso) {
    $counts = getDashboardCounts($conexao);
    try {
        $stmtReq = $conexao->query("SELECT COUNT(*) FROM requisitos_auditoria WHERE ativo = 1");
        $counts['total_requisitos'] = (int) $stmtReq->fetchColumn();
    } catch (PDOException $e) { /* logado */ }
    $logsRecentesData = getLogsAcesso($conexao, 1, 5);
    $logsRecentes = $logsRecentesData['logs'] ?? [];
    $chartData = getLoginLogsLast7Days($conexao);
}

// --- Geração do HTML ---
$title = "ACodITools - Dashboard";
echo getHeaderAdmin($title);
?>

<?php /* ----- Modal de Primeiro Acesso ----- */ ?>
<?php if ($primeiro_acesso): ?>
    <?php // O HTML do modal permanece o mesmo ?>
    <div id="bloqueio-conteudo" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1060; display: flex; align-items: center; justify-content: center;">
         <div class="modal fade show" id="primeiroAcessoModal" tabindex="-1" aria-labelledby="primeiroAcessoModalLabel" aria-modal="true" role="dialog" style="display: block;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-0"><div class="modal-header bg-primary text-white"><h5 class="modal-title" id="primeiroAcessoModalLabel"><i class="fas fa-shield-alt me-2"></i>Primeiro Acesso - Redefinir Senha</h5></div><div class="modal-body p-4"><p class="text-muted mb-3">Por segurança, defina uma nova senha.</p><form id="formRedefinirSenha" novalidate><input type="hidden" name="csrf_token_modal" value="<?= htmlspecialchars($csrf_token) ?>"><div class="mb-3"><label for="nova_senha_modal" class="form-label form-label-sm">Nova Senha</label><input type="password" class="form-control form-control-sm" id="nova_senha_modal" name="nova_senha" required minlength="8" aria-describedby="novaSenhaHelp"><div id="novaSenhaHelp" class="form-text small">Mínimo 8 caracteres, com maiúscula, minúscula e número.</div><div class="invalid-feedback small">Senha inválida. Verifique os requisitos.</div></div><div class="mb-3"><label for="confirmar_senha_modal" class="form-label form-label-sm">Confirmar Nova Senha</label><input type="password" class="form-control form-control-sm" id="confirmar_senha_modal" name="confirmar_senha" required><div class="invalid-feedback small">As senhas não coincidem.</div></div><div id="senha_error" class="alert alert-danger alert-sm mt-3 p-2 small" style="display: none;"></div><div id="senha_sucesso" class="alert alert-success alert-sm mt-3 p-2 small" style="display: none;"></div><button type="submit" class="btn btn-primary w-100 mt-3">Redefinir Senha</button></form></div></div></div></div>
    </div>
<?php /* ----- Conteúdo Normal do Dashboard ----- */ ?>
<?php else: ?>
    <?php /* Usa a div .main-content-fluid aberta pelo getHeaderAdmin */ ?>
    <div class="container-fluid px-md-4 py-4"> <?php /* Padding responsivo */ ?>

        <?php /* ==== CABEÇALHO DA PÁGINA COM LAYOUT RESPONSIVO ==== */ ?>
        <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center pt-3 pb-2 mb-4 border-bottom">
            <div class="mb-3 mb-md-0 text-center text-md-start"> <?php /* Centraliza em mobile, alinha esquerda em desktop */ ?>
                <h1 class="h3 mb-0 fw-bold"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
                <p class="text-muted small mb-0">Visão geral e atalhos do sistema.</p>
            </div>
            <div class="btn-toolbar justify-content-center justify-content-md-end" role="toolbar" aria-label="Ações rápidas"> <?php /* Centraliza botões em mobile */ ?>
                 <div class="btn-group btn-group-sm me-md-2 mb-2 mb-md-0 shadow-sm rounded">
                    <a href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php" class="btn btn-light border-secondary-subtle"><i class="fas fa-tasks text-primary me-1 d-md-none"></i><span class="d-none d-md-inline"><i class="fas fa-tasks text-primary me-1"></i>Requisitos</span></a>
                    <a href="<?= BASE_URL ?>admin/usuarios.php" class="btn btn-light border-secondary-subtle"><i class="fas fa-users text-primary me-1 d-md-none"></i><span class="d-none d-md-inline"><i class="fas fa-users text-primary me-1"></i>Usuários</span></a>
                    <a href="<?= BASE_URL ?>admin/empresa/empresa_index.php" class="btn btn-light border-secondary-subtle"><i class="fas fa-building text-primary me-1 d-md-none"></i><span class="d-none d-md-inline"><i class="fas fa-building text-primary me-1"></i>Empresas</span></a>
                 </div>
                 <div class="btn-group btn-group-sm shadow-sm rounded">
                     <a href="<?= BASE_URL ?>admin/logs.php" class="btn btn-light border-secondary-subtle"><i class="fas fa-history text-primary me-1 d-md-none"></i><span class="d-none d-md-inline"><i class="fas fa-history text-primary me-1"></i>Logs</span></a>
                     <a href="<?= BASE_URL ?>admin/configuracoes_admin.php" class="btn btn-light border-secondary-subtle"><i class="fas fa-user-cog text-primary me-1 d-md-none"></i><span class="d-none d-md-inline"><i class="fas fa-user-cog text-primary me-1"></i>Perfil</span></a>
                 </div>
            </div>
        </div>
        <?php /* ==== FIM DO CABEÇALHO ==== */ ?>


        <?php /* Notificações (Alerts) */ ?>
        <?php if ($sucesso_msg): ?>
            <div class="alert alert-success d-flex align-items-start alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert"> <?php /* align-items-start */ ?>
                <i class="fas fa-check-circle flex-shrink-0 me-2 mt-1"></i> <?php /* mt-1 para alinhar com texto */ ?>
                <div class="flex-grow-1"><?= htmlspecialchars($sucesso_msg) ?></div>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($erro_msg): ?>
            <div class="alert alert-danger d-flex align-items-start alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert">
                 <i class="fas fa-exclamation-triangle flex-shrink-0 me-2 mt-1"></i>
                 <div class="flex-grow-1"><?= htmlspecialchars($erro_msg) ?></div>
                 <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php /* Cards de Stats */ ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-5 g-3 mb-4"> <?php /* Ajustado para xxl-5 */ ?>
             <?php
            $statCards = [ /* Array permanece o mesmo */
                 ['color' => 'primary', 'icon' => 'fa-user-plus', 'title' => 'Solic. Acesso Pendentes', 'value' => $counts['solicitacoes_acesso'] ?? 0, 'link' => BASE_URL . 'admin/usuarios.php#solicitacoes-acesso-tab'],
                 ['color' => 'warning', 'icon' => 'fa-key', 'title' => 'Solic. Senha Pendentes', 'value' => $counts['solicitacoes_reset'] ?? 0, 'link' => BASE_URL . 'admin/usuarios.php#solicitacoes-reset-tab'],
                 ['color' => 'success', 'icon' => 'fa-user-check', 'title' => 'Usuários Ativos', 'value' => $counts['usuarios_ativos'] ?? 0, 'link' => BASE_URL . 'admin/usuarios.php'],
                 ['color' => 'info', 'icon' => 'fa-building', 'title' => 'Empresas Cadastradas', 'value' => $counts['total_empresas'] ?? 0, 'link' => BASE_URL . 'admin/empresa/empresa_index.php'],
                 ['color' => 'secondary', 'icon' => 'fa-tasks', 'title' => 'Requisitos Ativos', 'value' => $counts['total_requisitos'] ?? 0, 'link' => BASE_URL . 'admin/requisitos/requisitos_index.php'],
            ];
            $linkTextMap = [ /* Mapeamento para textos de link (opcional) */
                'Solic. Acesso Pendentes' => 'Ver Pendentes', 'Solic. Senha Pendentes' => 'Ver Pendentes',
                'Usuários Ativos' => 'Gerenciar', 'Empresas Cadastradas' => 'Gerenciar', 'Requisitos Ativos' => 'Gerenciar'
            ];
            ?>
            <?php foreach ($statCards as $card): ?>
            <div class="col">
                <a class="card text-decoration-none shadow-sm border-start border-5 border-<?= $card['color'] ?> h-100 dashboard-stat-card" href="<?= $card['link'] ?>">
                    <div class="card-body d-flex justify-content-between align-items-center py-3 px-3">
                        <div>
                             <?php /* Título menor */ ?>
                            <div class="text-muted small text-uppercase fw-semibold mb-1"><?= $card['title'] ?></div>
                            <div class="fs-3 fw-bolder"><?= $card['value'] ?></div>
                        </div>
                        <i class="fas <?= $card['icon'] ?> fa-2x text-black-50 opacity-50"></i>
                    </div>
                    <?php /* Rodapé removido para simplicidade, link no card todo */ ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>


        <?php /* Linha com Gráfico e Atividade Recente */ ?>
        <div class="row g-4">
            <div class="col-lg-7 order-lg-1 mb-4 mb-lg-0"> <?php /* Adiciona margem inferior em mobile */ ?>
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white border-0 pt-3 pb-0"><h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i>Logins nos Últimos 7 Dias</h6></div>
                    <div class="card-body d-flex align-items-center justify-content-center p-2">
                         <?php if (!empty($chartData['labels']) && !empty($chartData['data']) && max($chartData['data']) > 0): ?>
                             <canvas id="loginChart" style="display: block; max-height: 280px; width: 100%;"></canvas>
                        <?php else: ?>
                             <div class="text-center text-muted py-5 vh-25 d-flex flex-column justify-content-center align-items-center"><i class="fas fa-info-circle fa-2x mb-2 text-light-emphasis"></i><span class="small">Sem dados de login recentes.</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 order-lg-2">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white border-0 pt-3 pb-2">
                        <div class="d-flex justify-content-between align-items-center"><h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>Últimas Atividades</h6><a href="<?= BASE_URL ?>admin/logs.php" class="btn btn-outline-secondary btn-sm py-1 px-2" style="font-size: 0.8em;">Ver Todos</a></div>
                    </div>
                    <div class="card-body p-0">
                         <?php if (!empty($logsRecentes)): ?>
                             <div class="list-group list-group-flush dashboard-log-list" style="max-height: 300px; overflow-y: auto;"> <?php /* Classe para estilo */ ?>
                                <?php foreach ($logsRecentes as $log):
                                    $log_data = new DateTime($log['data_hora']); $isSuccess = (bool) $log['sucesso'];
                                    $iconClass = $isSuccess ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
                                    $userText = htmlspecialchars($log['nome_usuario'] ?? 'Sistema');
                                    $actionText = htmlspecialchars($log['acao']);
                                    $details = htmlspecialchars(mb_strimwidth($log['detalhes'] ?? '', 0, 70, "...")); ?>
                                    <div class="list-group-item px-3 py-2 lh-sm">
                                        <div class="d-flex w-100 justify-content-between mb-0"><div><i class="fas <?= $iconClass ?> fa-fw me-2 small"></i><strong class="fw-semibold small"><?= $actionText ?></strong></div><small class="text-muted text-nowrap ps-2" title="<?= $log_data->format('d/m/Y H:i:s') ?>"><?= $log_data->format('d/m H:i') ?></small></div>
                                        <div class="text-muted small ps-4"><span class="me-1" title="Usuário: <?= $userText ?>"> <i class="fas fa-user fa-xs"></i> <?= $userText ?></span><span title="IP: <?= htmlspecialchars($log['ip_address']) ?>"><i class="fas fa-network-wired fa-xs ms-2 me-1"></i><?= htmlspecialchars($log['ip_address']) ?></span><?php if (!empty($log['detalhes'])): ?><span class="d-block fst-italic mt-1 text-truncate" title="<?= htmlspecialchars($log['detalhes']) ?>">↳ <?= $details ?></span><?php endif; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                             <div class="text-center text-muted py-5 vh-25 d-flex flex-column justify-content-center align-items-center"><i class="fas fa-info-circle fa-2x mb-2 text-light-emphasis"></i><span class="small">Nenhuma atividade recente.</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php /* Fechamento da div .main-content-fluid que foi aberta em getHeaderAdmin */ ?>
    </div>

<?php endif; /* Fim do else (não é primeiro acesso) */ ?>

<?php
// Chama o footer
echo getFooterAdmin();
?>

<?php /* ----- Script para Inicializar o Gráfico (Fora do HTML principal) ----- */ ?>
<?php if (!$primeiro_acesso && !empty($chartData['labels']) && !empty($chartData['data']) && max($chartData['data']) > 0): ?>
<script>
function initLoginChart() { /* Função igual à anterior */
    if (typeof Chart === 'undefined') { setTimeout(initLoginChart, 100); return; }
    const ctx = document.getElementById('loginChart'); if (!ctx) { return; }
    const chartLabels = <?= json_encode($chartData['labels']) ?>; const chartLoginData = <?= json_encode($chartData['data']) ?>;
    const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#1a3b5c';
    const primaryColorRgba = hexToRgba(primaryColor, 0.3); // Mais transparência
    let existingChart = Chart.getChart(ctx); if (existingChart) { existingChart.destroy(); }
    new Chart(ctx, { type: 'bar', data: { labels: chartLabels, datasets: [{ label: ' Logins', data: chartLoginData, backgroundColor: primaryColorRgba, borderColor: primaryColor, borderWidth: 1, borderRadius: 5, barPercentage: 0.7, categoryPercentage: 0.8 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: '#212529', titleColor: '#fff', bodyColor: '#fff', titleFont: { weight: 'bold' }, bodyFont: { size: 12 }, padding: 12, cornerRadius: 6, displayColors: false, callbacks: { label: ctx => `${ctx.parsed.y} login(s)` } } }, scales: { y: { beginAtZero: true, ticks: { precision: 0, stepSize: Math.max(1, Math.ceil(Math.max(...chartLoginData) / 5)) } , grid: { color: '#eee' } }, x: { grid: { display: false } } }, animation: { duration: 400 } } }); }
function hexToRgba(hex, alpha = 1) { const bigint = parseInt(hex.slice(1), 16); const r = (bigint >> 16) & 255; const g = (bigint >> 8) & 255; const b = bigint & 255; return `rgba(${r}, ${g}, ${b}, ${alpha})`; }
document.addEventListener('DOMContentLoaded', initLoginChart);
</script>
<?php endif; ?>