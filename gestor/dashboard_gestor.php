<?php
// gestor/dashboard_gestor.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_gestor.php'; // Layout do Gestor
require_once __DIR__ . '/../includes/gestor_functions.php';
// Se dbGetDadosUsuario e getLogsAcesso (ou uma versão filtrada para o gestor) estiverem aqui:
require_once __DIR__ . '/../includes/db.php'; // Ou admin_functions.php se for o caso

// Proteção e Verificação de Perfil
protegerPagina($conexao); // Assume que $conexao está disponível globalmente via config.php
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['perfil']) || $_SESSION['perfil'] !== 'gestor_empresa' || !isset($_SESSION['usuario_empresa_id'])) {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$gestor_id = (int)$_SESSION['usuario_id'];
$empresa_id = (int)$_SESSION['usuario_empresa_id'];
$empresa_nome = $_SESSION['empresa_nome'] ?? 'Minha Empresa';

$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// Verificar Primeiro Acesso do Gestor
$usuario_gestor = dbGetDadosUsuario($conexao, $gestor_id); // Usando a função que já deve existir
if (!$usuario_gestor) {
    dbRegistrarLogAcesso($gestor_id, $_SERVER['REMOTE_ADDR'], 'dashboard_gestor_erro_usuario', 0, "ID de gestor não encontrado: $gestor_id", $conexao);
    header('Location: ' . BASE_URL . 'logout.php?erro=dados_usuario_invalido');
    exit;
}
$primeiro_acesso = ($usuario_gestor['primeiro_acesso'] == 1);
$is_empresa_admin_cliente = ($usuario_gestor['is_empresa_admin_cliente'] == 1); // Para o menu


// --- Buscar Dados para o Dashboard (Apenas se NÃO for primeiro acesso) ---
$statsGestor = [
    'auditorias_ativas_total' => 0, 'auditorias_para_revisao_gestor' => 0,
    'planos_acao_abertos_gestor' => 0, 'planos_acao_atrasados_gestor' => 0,
    'total_nao_conformidades_abertas_empresa' => 0, 'total_documentos_repositorio' => 0,
    'proximas_auditorias_count' => 0
];
$auditoriasParaRevisarLista = [];
$planosAcaoPendentesOuAtrasadosLista = [];
$proximasAuditoriasAgendadasLista = [];
$statusChartDataEmpresa = ['labels' => [], 'data' => []];
$conformidadeChartDataEmpresa = ['labels' => [], 'data' => []]; // Poderia ser NCs por Tipo ou Criticidade
$alertasGestor = [];


if (!$primeiro_acesso) {
    // Estas funções PRECISAM SER CRIADAS E IMPLEMENTADAS em gestor_functions.php
    if (function_exists('getGestorDashboardStats')) {
        $statsGestor = getGestorDashboardStats($conexao, $empresa_id, $gestor_id);
    }
    if (function_exists('getAuditoriasParaRevisaoGestor')) {
        $auditoriasParaRevisarLista = getAuditoriasParaRevisaoGestor($conexao, $empresa_id, $gestor_id, 5);
    }
    if (function_exists('getPlanosAcaoPendentesParaGestor')) {
        $planosAcaoPendentesOuAtrasadosLista = getPlanosAcaoPendentesParaGestor($conexao, $empresa_id, $gestor_id, 5);
    }
    if (function_exists('getProximasAuditoriasAgendadasEmpresa')) {
        $proximasAuditoriasAgendadasLista = getProximasAuditoriasAgendadasEmpresa($conexao, $empresa_id, 5);
    }
    if (function_exists('getAuditoriaStatusChartDataEmpresa')) {
        $statusChartDataEmpresa = getAuditoriaStatusChartDataEmpresa($conexao, $empresa_id);
    }
    if (function_exists('getConformidadeTipoOuCriticidadeChartData')) {
        $conformidadeChartDataEmpresa = getConformidadeTipoOuCriticidadeChartData($conexao, $empresa_id, 'tipo_nc'); // 'tipo_nc' ou 'criticidade_achado'
    }
    if (function_exists('getAlertasParaGestor')) {
        $alertasGestor = getAlertasParaGestor($conexao, $empresa_id, $gestor_id); // Ex: planos de ação atrasados, revisões pendentes, etc.
    }
}

$title = "Painel de Controle Gestor - " . htmlspecialchars($empresa_nome);
echo getHeaderGestor($title); // Função do seu layout_gestor.php
$csrf_token_page = $_SESSION['csrf_token']; // Para o modal de primeiro acesso, se a função gerar_csrf_token não for chamada lá.
?>

    <?php if ($primeiro_acesso): ?>
        <div id="bloqueio-conteudo" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1060; display: flex; align-items: center; justify-content: center;">
            <div class="modal fade show" id="primeiroAcessoModal" tabindex="-1" aria-labelledby="primeiroAcessoModalLabel" aria-modal="true" role="dialog" style="display: block;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content shadow-lg border-0">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="primeiroAcessoModalLabel"><i class="fas fa-shield-alt me-2"></i>Primeiro Acesso - Redefinir Senha</h5>
                        </div>
                        <div class="modal-body p-4">
                            <p class="text-muted mb-3">Para sua segurança, por favor, defina uma nova senha para acessar a plataforma AcodITools.</p>
                            <form id="formRedefinirSenha" novalidate>
                                <input type="hidden" name="csrf_token_modal" value="<?= htmlspecialchars($csrf_token_page) ?>">
                                <div class="mb-3">
                                    <label for="nova_senha_modal" class="form-label form-label-sm">Nova Senha</label>
                                    <input type="password" class="form-control form-control-sm" id="nova_senha_modal" name="nova_senha" required minlength="8" aria-describedby="novaSenhaHelpModalGestor">
                                    <div id="novaSenhaHelpModalGestor" class="form-text small">Mínimo 8 caracteres, com maiúscula, minúscula e número.</div>
                                    <div class="invalid-feedback small">Senha inválida. Verifique os requisitos.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirmar_senha_modal" class="form-label form-label-sm">Confirmar Nova Senha</label>
                                    <input type="password" class="form-control form-control-sm" id="confirmar_senha_modal" name="confirmar_senha" required>
                                    <div class="invalid-feedback small">As senhas não coincidem.</div>
                                </div>
                                <div id="senha_error" class="alert alert-danger alert-sm mt-3 p-2 small" style="display: none;"></div>
                                <div id="senha_sucesso" class="alert alert-success alert-sm mt-3 p-2 small" style="display: none;"></div>
                                <button type="submit" class="btn btn-primary w-100 mt-3">Redefinir Senha e Continuar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div class="mb-2 mb-md-0">
                <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-user-tie me-2 text-primary"></i>Painel de Controle do Gestor</h1>
                <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                </ol></nav>
            </div>
            <div class="btn-toolbar">
                <a href="<?= BASE_URL ?>gestor/auditoria/solicitar_nova_auditoria_baseada_em_risco.php" class="btn btn-outline-danger rounded-pill shadow-sm px-3 me-2" title="Planejar auditoria com base na análise de criticidade de áreas/processos">
                    <i class="fas fa-shield-virus me-1"></i> Nova Auditoria por Risco
                </a>
                <a href="<?= BASE_URL ?>gestor/auditoria/criar_auditoria.php" class="btn btn-primary rounded-pill shadow-sm px-3">
                    <i class="fas fa-plus me-1"></i> Auditoria Ad-hoc
                </a>
            </div>
        </div>

        <?php if ($sucesso_msg): ?><div class="alert alert-success gestor-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($erro_msg): ?><div class="alert alert-danger gestor-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= $erro_msg ?><button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <!-- Seção de Alertas do Gestor -->
        <?php if(!empty($alertasGestor)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm rounded-3 border-0">
                    <div class="card-header bg-warning-subtle border-bottom-0 pt-3 pb-2">
                        <h6 class="mb-0 fw-bold text-warning-emphasis"><i class="fas fa-bell me-2"></i>Avisos e Pendências Importantes</h6>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($alertasGestor as $alerta_g): ?>
                            <a href="<?= htmlspecialchars($alerta_g['link'] ?? '#!') ?>" class="list-group-item list-group-item-action small py-2 px-3 list-group-item-light">
                                <i class="fas <?= ($alerta_g['tipo'] === 'prazo_pa_vencendo' ? 'fa-calendar-times text-danger' : 'fa-info-circle text-warning') ?> fa-fw me-2"></i>
                                <?= htmlspecialchars($alerta_g['mensagem']) ?>
                                <?php if(isset($alerta_g['data_referencia_formatada'])): ?> <small class="text-muted ms-2">(Ref: <?= htmlspecialchars($alerta_g['data_referencia_formatada']) ?>)</small> <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <!-- Cards de Stats Aprimorados -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-5 g-3 mb-4">
            <div class="col">
                <a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php?filtro_status_auditoria=Em+Andamento&filtro_status_auditoria=Planejada" class="card text-decoration-none shadow-sm border-start border-5 border-primary h-100 dashboard-stat-card">
                    <div class="card-body text-center p-3"><div class="fs-2 fw-bolder"><?= htmlspecialchars($statsGestor['auditorias_ativas_total'] ?? 0) ?></div><div class="small text-uppercase text-muted fw-semibold">Auditorias Ativas/Planejadas</div></div>
                </a>
            </div>
            <div class="col">
                <a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php?filtro_status_auditoria=Concluída (Auditor)" class="card text-decoration-none shadow-sm border-start border-5 border-warning h-100 dashboard-stat-card">
                     <div class="card-body text-center p-3"><div class="fs-2 fw-bolder"><?= htmlspecialchars($statsGestor['auditorias_para_revisao_gestor'] ?? 0) ?></div><div class="small text-uppercase text-muted fw-semibold">Aguardando Sua Revisão</div></div>
                </a>
            </div>
            <div class="col">
                 <a href="<?= BASE_URL ?>gestor/auditoria/gestao_consolidada_planos_de_acao.php?filtro_status_pa=Aberto" class="card text-decoration-none shadow-sm border-start border-5 border-info h-100 dashboard-stat-card">
                    <div class="card-body text-center p-3"><div class="fs-2 fw-bolder"><?= htmlspecialchars($statsGestor['planos_acao_abertos_gestor'] ?? 0) ?></div><div class="small text-uppercase text-muted fw-semibold">Seus Planos de Ação Abertos</div></div>
                </a>
            </div>
            <div class="col">
                <a href="<?= BASE_URL ?>gestor/auditoria/gestao_consolidada_planos_de_acao.php?filtro_status_pa=Atrasado" class="card text-decoration-none shadow-sm border-start border-5 border-danger h-100 dashboard-stat-card">
                    <div class="card-body text-center p-3"><div class="fs-2 fw-bolder"><?= htmlspecialchars($statsGestor['planos_acao_atrasados_gestor'] ?? 0) ?></div><div class="small text-uppercase text-muted fw-semibold">Seus Planos de Ação Atrasados</div></div>
                </a>
            </div>
             <div class="col">
                <a href="<?= BASE_URL ?>gestor/relatorio_de_tendencias_de_nao_conformidades.php" class="card text-decoration-none shadow-sm border-start border-5 border-secondary h-100 dashboard-stat-card">
                    <div class="card-body text-center p-3"><div class="fs-2 fw-bolder"><?= htmlspecialchars($statsGestor['total_nao_conformidades_abertas_empresa'] ?? 0) ?></div><div class="small text-uppercase text-muted fw-semibold">Total NCs Abertas (Empresa)</div></div>
                </a>
            </div>
        </div>

        <!-- Linha de Auditorias e Planos de Ação -->
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="card shadow-sm rounded-3 border-0 h-100">
                    <div class="card-header bg-light border-bottom pt-3 pb-2">
                        <h6 class="mb-0 fw-bold d-flex align-items-center">
                            <i class="fas fa-calendar-alt me-2 text-primary opacity-75"></i>Próximas Auditorias Agendadas na Empresa
                            <a href="<?= BASE_URL ?>gestor/auditoria/meu_plano_de_auditoria_anual.php" class="btn btn-sm btn-outline-secondary ms-auto py-0 px-2 x-small">Ver Plano Anual Completo</a>
                        </h6>
                    </div>
                    <div class="list-group list-group-flush dashboard-list-group" style="max-height: 280px; overflow-y: auto;">
                        <?php if (empty($proximasAuditoriasAgendadasLista)): ?>
                            <div class="list-group-item text-center text-muted small py-3">Nenhuma auditoria agendada em breve.</div>
                        <?php else: ?>
                            <?php foreach ($proximasAuditoriasAgendadasLista as $aud_agenda): ?>
                                <a href="<?= BASE_URL ?>gestor/auditoria/detalhes_auditoria.php?id=<?= $aud_agenda['id'] ?>" class="list-group-item list-group-item-action py-2 px-3">
                                    <div class="d-flex w-100 justify-content-between">
                                        <span class="mb-1 fw-semibold text-truncate" title="<?= htmlspecialchars($aud_agenda['titulo']) ?>"><?= htmlspecialchars(mb_strimwidth($aud_agenda['titulo'],0,45,"...")) ?></span>
                                        <small class="text-success text-nowrap ps-2 fw-medium"><i class="far fa-calendar-check me-1"></i> <?= htmlspecialchars(formatarDataSimples($aud_agenda['data_inicio_planejada'])) ?></small>
                                    </div>
                                    <small class="text-muted d-block x-small">Responsável: <?= htmlspecialchars($aud_agenda['responsavel_display'] ?? 'A definir') ?></small>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm rounded-3 border-0 h-100">
                     <div class="card-header bg-warning-subtle border-bottom pt-3 pb-2">
                        <h6 class="mb-0 fw-bold text-warning-emphasis d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i> Auditorias Aguardando Sua Revisão
                            <?php $countParaRevisar = count($auditoriasParaRevisarLista); ?>
                            <?php if ($countParaRevisar > 0): ?><span class="badge bg-warning text-dark ms-auto rounded-pill"><?= $countParaRevisar ?></span><?php endif; ?>
                        </h6>
                    </div>
                    <div class="list-group list-group-flush dashboard-list-group" style="max-height: 280px; overflow-y: auto;">
                        <?php if (empty($auditoriasParaRevisarLista)): ?>
                            <div class="list-group-item text-center text-muted small py-4"><i class="fas fa-thumbs-up fs-4 mb-2 d-block text-success"></i>Nenhuma auditoria para revisar no momento.</div>
                        <?php else: ?>
                            <?php foreach ($auditoriasParaRevisarLista as $auditoria_rev): ?>
                                <a href="<?= BASE_URL ?>gestor/auditoria/revisar_auditoria.php?id=<?= $auditoria_rev['id'] ?>" class="list-group-item list-group-item-action py-2 px-3 list-group-item-warning">
                                    <div class="d-flex w-100 justify-content-between">
                                        <span class="mb-1 fw-semibold text-truncate" title="<?= htmlspecialchars($auditoria_rev['titulo']) ?>"><?= htmlspecialchars(mb_strimwidth($auditoria_rev['titulo'],0,30,"...")) ?></span>
                                        <small class="text-muted text-nowrap ps-2" title="Concluída pelo auditor em <?= formatarDataCompleta($auditoria_rev['data_conclusao_auditor']) ?>"><i class="far fa-clock me-1"></i> <?= formatarDataRelativa($auditoria_rev['data_conclusao_auditor']) ?></small>
                                    </div>
                                    <small class="text-muted d-block x-small">Auditor(a)/Equipe: <?= htmlspecialchars($auditoria_rev['responsavel_display'] ?? 'N/A') ?></small>
                                </a>
                            <?php endforeach; ?>
                             <a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php?filtro_status_auditoria=Concluída (Auditor)" class="list-group-item text-center small py-2 bg-light text-primary fw-semibold">Ver Todas Pendentes de Revisão</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos de Status e Conformidade (mesma estrutura de antes) -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm h-100 rounded-3 border-0">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-pie-chart me-2 text-info opacity-75"></i>Status Geral das Auditorias (Empresa)</h6></div>
                    <div class="card-body d-flex align-items-center justify-content-center p-2" style="min-height: 250px;">
                        <?php if (!empty($statusChartDataEmpresa['labels']) && (array_sum($statusChartDataEmpresa['data'] ?? []) > 0 || count($statusChartDataEmpresa['labels']) > 0) ): ?>
                            <canvas id="gestorStatusAuditoriaChart"></canvas>
                        <?php else: ?>
                            <div class="text-center text-muted py-4"><i class="fas fa-info-circle fs-4 mb-2"></i> Nenhuma auditoria registrada para exibir status.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm h-100 rounded-3 border-0">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-balance-scale-right me-2 text-success opacity-75"></i>Tendência de Conformidade (NCs por Tipo/Criticidade)</h6></div>
                    <div class="card-body d-flex align-items-center justify-content-center p-2" style="min-height: 250px;">
                        <?php $totalItensGraficoConf = array_sum(array_values($conformidadeChartDataEmpresa['data'] ?? [])); ?>
                        <?php if ($totalItensGraficoConf > 0): ?>
                            <canvas id="gestorConformidadeChart"></canvas>
                        <?php else: ?>
                            <div class="text-center text-muted py-4"><i class="fas fa-info-circle fs-4 mb-2"></i> Sem dados de conformidade (nenhuma auditoria aprovada com itens avaliados).</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; /* Fim do else (não é primeiro acesso) */ ?>
<?php
if (!$primeiro_acesso) { echo '</main>'; }
echo getFooterGestor();
?>

<?php if (!$primeiro_acesso): ?>
<script>
    // Suas funções JS hexToRgba, destroyExistingChart e formatarDataRelativa devem estar aqui ou em um script global
    if (typeof formatarDataRelativa === 'undefined') { function formatarDataRelativa(dIso){ const d=new Date(dIso); if(isNaN(d)) return dIso; const n=new Date(); const s=Math.floor((n-d)/1000); const m=Math.floor(s/60); const h=Math.floor(m/60); const day=Math.floor(h/24); const w=Math.floor(day/7); const mon=Math.floor(day/30); if(s<60)return'agora'; if(m<60)return`${m} min`; if(h<24)return`${h}h`; if(day<7)return`${day}d`; if(w<4)return`${w} sem`; if(mon<12)return`${mon} mês`; return d.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric'});} }
    function hexToRgba(hex, alpha = 1) { try { const bigint=parseInt(hex.slice(1),16); const r=(bigint>>16)&255; const g=(bigint>>8)&255; const b=bigint&255; return`rgba(${r},${g},${b},${alpha})`; } catch(e){ return `rgba(108, 117, 125, ${alpha})`; } }
    function destroyExistingChart(canvasId) { const chartInstance = Chart.getChart(canvasId); if (chartInstance) { chartInstance.destroy(); } }

    function initDashboardGestorCharts() {
        if (typeof Chart === 'undefined') { setTimeout(initDashboardGestorCharts, 200); return; }

        const statusLabels = <?= json_encode($statusChartDataEmpresa['labels'] ?? []) ?>;
        const statusData = <?= json_encode($statusChartDataEmpresa['data'] ?? []) ?>;
        const ctxStatus = document.getElementById('gestorStatusAuditoriaChart');
        if(ctxStatus && Array.isArray(statusLabels) && statusLabels.length > 0 && Array.isArray(statusData) && statusData.some(d => d > 0)) {
            const statusColors = {'Planejada':'#6f42c1', 'Em Andamento':'#0d6efd', 'Pausada':'#adb5bd', 'Concluída (Auditor)':'#ffc107', 'Aguardando Correção Auditor':'#fd7e14', 'Em Revisão':'#0dcaf0', 'Aprovada':'#198754', 'Rejeitada':'#dc3545', 'Cancelada':'#6c757d'};
            const backgroundColors = statusLabels.map(label => statusColors[label] || '#cccccc');
            destroyExistingChart('gestorStatusAuditoriaChart');
            new Chart(ctxStatus, {
                type: 'pie', // Mudado para Pie para variedade
                data: { labels: statusLabels, datasets: [{ data: statusData, backgroundColor: backgroundColors, borderColor: '#fff', borderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels:{ padding:10, boxWidth:12, font:{size:11}}}}}
            });
        }

        const conformidadeLabels = <?= json_encode($conformidadeChartDataEmpresa['labels'] ?? []) ?>; // Ex: ['Conforme', 'Não Conforme', 'Parcial']
        const conformidadeData = <?= json_encode($conformidadeChartDataEmpresa['data'] ?? []) ?>; // Ex: [10, 2, 1]
        const ctxConformidade = document.getElementById('gestorConformidadeChart');
        const totalItensConf = conformidadeData.reduce((a, b) => a + b, 0);

        if(ctxConformidade && Array.isArray(conformidadeLabels) && conformidadeLabels.length > 0 && Array.isArray(conformidadeData) && totalItensConf > 0) {
            // As cores devem corresponder aos labels exatos que sua função de backend retorna para conformidade
            const conformidadeColors = {'Conforme':'#198754', 'Não Conforme':'#dc3545', 'Parcialmente Conforme':'#ffc107', 'Parcial':'#ffc107', 'N/A':'#adb5bd', 'Pendente':'#6c757d' };
            const backgroundColorsConf = conformidadeLabels.map(label => conformidadeColors[label] || '#cccccc');
            destroyExistingChart('gestorConformidadeChart');
            new Chart(ctxConformidade, {
                type: 'doughnut', // Mudado para Doughnut
                data: { labels: conformidadeLabels, datasets: [{ data: conformidadeData, backgroundColor: backgroundColorsConf, borderColor: '#fff', borderWidth: 2, hoverOffset: 4 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '50%', plugins: { legend: { position: 'right', labels:{ padding:10, boxWidth:12, font:{size:11}}}, tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.raw} (${((ctx.raw / totalItensConf) * 100).toFixed(1)}%)` }}}}
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initDashboardGestorCharts();
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(el){ return new bootstrap.Tooltip(el); });

        // Formatar datas relativas nas listas
         document.querySelectorAll('.dashboard-list-group small.text-muted[title^="Concluída"]').forEach(el => {
            try {
                let isoDate = el.title.split('em ')[1];
                const timeIcon = el.querySelector('.far.fa-clock');
                const textNodeToUpdate = timeIcon ? timeIcon.nextSibling : el.firstChild;
                if (textNodeToUpdate && textNodeToUpdate.nodeType === Node.TEXT_NODE && isoDate && isoDate.length > 10) {
                    textNodeToUpdate.nodeValue = ` ${formatarDataRelativa(isoDate)}`;
                } else if (!timeIcon) {
                    el.textContent = ` ${formatarDataRelativa(isoDate)}`;
                }
            } catch (e) { /* ignorar erros de formatação aqui */ }
        });
    });
</script>
<?php endif; ?>