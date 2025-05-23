<?php
// admin/plataforma_monitoramento_e_saude.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php';

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// Lógica de Mensagens Flash
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// Buscar Dados para o Dashboard
$statsUsoPlataforma = getPlataformaStatsUsoGeral($conexao);
$statsRecursosServidor = getPlataformaStatsRecursosServidor();
$errosCriticosApp = getPlataformaLogsErrosCriticosApp($conexao, 7);
$tendenciaLoginsFalhos = getPlataformaTendenciaLoginsFalhos($conexao, 7); // Últimos 7 dias
$integridadeDados = getIntegridadeDados($conexao);

// Definir valores padrão em caso de erro
$statsUsoPlataforma = $statsUsoPlataforma ?: [
    'total_empresas_ativas_contrato' => 0,
    'total_usuarios_ativos_plataforma' => 0,
    'auditorias_criadas_ultimos_30d' => 0,
    'auditorias_em_andamento_total' => 0,
    'planos_acao_abertos_total' => 0,
    'modelos_globais_mais_usados' => []
];
$statsRecursosServidor = $statsRecursosServidor ?: [
    'uso_cpu_percentual_atual' => 0,
    'uso_memoria_percentual_atual' => 0,
    'uso_disco_uploads_gb_total' => 0,
    'uso_disco_db_gb_total' => 0,
    'media_tempo_resposta_ms' => 0
];
$errosCriticosApp = $errosCriticosApp ?: [];
$tendenciaLoginsFalhos = $tendenciaLoginsFalhos ?: ['labels' => [], 'data_falhas' => []];
$integridadeDados = $integridadeDados ?: [
    'auditorias_sem_responsavel' => 0,
    'itens_sem_requisito_valido' => 0,
    'empresas_sem_plano_ativo' => 0
];

$title = "ACodITools - Monitoramento e Saúde da Plataforma";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-tachometer-alt-average me-2"></i>Monitoramento e Saúde da Plataforma</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload();">
                <i class="fas fa-sync-alt me-1"></i> Atualizar Dados
            </button>
        </div>
    </div>

    <?php if ($sucesso_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $sucesso_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($erro_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $erro_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4" id="monitoramentoTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="uso-plataforma-tab" data-bs-toggle="tab" data-bs-target="#uso-plataforma-content" type="button" role="tab"><i class="fas fa-chart-line me-1"></i>Uso da Plataforma</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="recursos-servidor-tab" data-bs-toggle="tab" data-bs-target="#recursos-servidor-content" type="button" role="tab"><i class="fas fa-server me-1"></i>Recursos do Servidor</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="logs-erros-tab" data-bs-toggle="tab" data-bs-target="#logs-erros-content" type="button" role="tab"><i class="fas fa-bug me-1"></i>Logs e Erros</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="integridade-dados-tab" data-bs-toggle="tab" data-bs-target="#integridade-dados-content" type="button" role="tab"><i class="fas fa-database me-1"></i>Integridade de Dados</button>
        </li>
    </ul>

    <div class="tab-content" id="monitoramentoTabsContent">
        <!-- Aba Uso da Plataforma -->
        <div class="tab-pane fade show active" id="uso-plataforma-content" role="tabpanel">
            <h5 class="mb-3 fw-semibold"><i class="fas fa-users-cog me-2 text-primary opacity-75"></i>Métricas de Utilização</h5>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3 mb-4">
                <div class="col">
                    <div class="card text-bg-light h-100">
                        <div class="card-body text-center p-3">
                            <div class="fs-2 fw-bolder text-primary"><?= htmlspecialchars($statsUsoPlataforma['total_empresas_ativas_contrato']) ?></div>
                            <div class="small text-uppercase text-muted fw-semibold">Empresas Clientes Ativas</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-bg-light h-100">
                        <div class="card-body text-center p-3">
                            <div class="fs-2 fw-bolder text-primary"><?= htmlspecialchars($statsUsoPlataforma['total_usuarios_ativos_plataforma']) ?></div>
                            <div class="small text-uppercase text-muted fw-semibold">Total Usuários Ativos</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-bg-light h-100">
                        <div class="card-body text-center p-3">
                            <div class="fs-2 fw-bolder text-info"><?= htmlspecialchars($statsUsoPlataforma['auditorias_em_andamento_total']) ?></div>
                            <div class="small text-uppercase text-muted fw-semibold">Auditorias em Andamento</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-bg-light h-100">
                        <div class="card-body text-center p-3">
                            <div class="fs-2 fw-bolder text-success"><?= htmlspecialchars($statsUsoPlataforma['auditorias_criadas_ultimos_30d']) ?></div>
                            <div class="small text-uppercase text-muted fw-semibold">Novas Auditorias (30 Dias)</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-bg-light h-100">
                        <div class="card-body text-center p-3">
                            <div class="fs-2 fw-bolder text-warning"><?= htmlspecialchars($statsUsoPlataforma['planos_acao_abertos_total']) ?></div>
                            <div class="small text-uppercase text-muted fw-semibold">Planos de Ação Abertos</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm rounded-3 border-0">
                <div class="card-header bg-light border-bottom pt-3 pb-2">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-star me-2 text-warning opacity-75"></i>Modelos Globais Mais Utilizados</h6>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($statsUsoPlataforma['modelos_globais_mais_usados'])): ?>
                        <div class="list-group-item text-center text-muted small py-3">Nenhum dado de uso de modelos disponível.</div>
                    <?php else: ?>
                        <?php foreach ($statsUsoPlataforma['modelos_globais_mais_usados'] as $modelo_usado_info): ?>
                            <a href="<?= BASE_URL ?>admin/modelo/editar_modelo.php?id=<?= htmlspecialchars($modelo_usado_info['id'] ?? '') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center small py-2 px-3 list-group-item-light">
                                <span><i class="fas fa-clipboard-list fa-fw me-2 text-muted"></i><?= htmlspecialchars($modelo_usado_info['nome_modelo']) ?></span>
                                <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($modelo_usado_info['usos']) ?> usos</span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Aba Recursos do Servidor -->
        <div class="tab-pane fade" id="recursos-servidor-content" role="tabpanel">
            <h5 class="mb-3 fw-semibold"><i class="fas fa-hdd me-2 text-primary opacity-75"></i>Métricas de Infraestrutura</h5>
            <div class="row g-3">
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted small text-uppercase">Uso de CPU</h6>
                            <div class="progress" role="progressbar" aria-valuenow="<?= $statsRecursosServidor['uso_cpu_percentual_atual'] ?>" aria-valuemin="0" aria-valuemax="100" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-<?= ($statsRecursosServidor['uso_cpu_percentual_atual'] > 80 ? 'danger' : ($statsRecursosServidor['uso_cpu_percentual_atual'] > 60 ? 'warning' : 'success')) ?>" style="width: <?= $statsRecursosServidor['uso_cpu_percentual_atual'] ?>%;">
                                    <?= $statsRecursosServidor['uso_cpu_percentual_atual'] ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted small text-uppercase">Uso de Memória</h6>
                            <div class="progress" role="progressbar" aria-valuenow="<?= $statsRecursosServidor['uso_memoria_percentual_atual'] ?>" aria-valuemin="0" aria-valuemax="100" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-<?= ($statsRecursosServidor['uso_memoria_percentual_atual'] > 85 ? 'danger' : ($statsRecursosServidor['uso_memoria_percentual_atual'] > 70 ? 'warning' : 'success')) ?>" style="width: <?= $statsRecursosServidor['uso_memoria_percentual_atual'] ?>%;">
                                    <?= $statsRecursosServidor['uso_memoria_percentual_atual'] ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted small text-uppercase">Tempo Médio Resposta</h6>
                            <p class="card-text fs-4 fw-bold"><?= htmlspecialchars($statsRecursosServidor['media_tempo_resposta_ms']) ?> ms</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted small text-uppercase">Disco: Uploads Clientes</h6>
                            <p class="card-text fs-4 fw-bold"><?= htmlspecialchars($statsRecursosServidor['uso_disco_uploads_gb_total']) ?> GB</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted small text-uppercase">Disco: Banco de Dados</h6>
                            <p class="card-text fs-4 fw-bold"><?= htmlspecialchars($statsRecursosServidor['uso_disco_db_gb_total']) ?> GB</p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="mt-4 text-center text-muted small fst-italic">Para monitoramento detalhado, utilize ferramentas como Prometheus ou Zabbix.</p>
        </div>

        <!-- Aba Logs e Erros -->
        <div class="tab-pane fade" id="logs-erros-content" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card shadow-sm rounded-3 border-0 h-100">
                        <div class="card-header bg-light border-bottom pt-3 pb-2">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-user-shield me-2 text-danger opacity-75"></i>Tendência de Falhas de Login</h6>
                        </div>
                        <div class="card-body d-flex align-items-center justify-content-center p-2" style="min-height: 300px;">
                            <?php if (!empty($tendenciaLoginsFalhos['labels'])): ?>
                                <canvas id="plataformaTendenciaLoginsChart"></canvas>
                            <?php else: ?>
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                                    <span class="small">Sem dados recentes de falhas de login.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card shadow-sm rounded-3 border-0 h-100">
                        <div class="card-header bg-light border-bottom pt-3 pb-2">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-bug me-2 text-danger opacity-75"></i>Últimos Erros Críticos</h6>
                        </div>
                        <div class="list-group list-group-flush" style="max-height: 320px; overflow-y: auto;">
                            <?php if (empty($errosCriticosApp)): ?>
                                <div class="list-group-item text-center text-muted small py-4">Nenhum erro crítico registrado.</div>
                            <?php else: ?>
                                <?php foreach ($errosCriticosApp as $erroAppItem): ?>
                                    <div class="list-group-item small py-2 px-3">
                                        <div class="d-flex w-100 justify-content-between">
                                            <strong class="text-danger-emphasis text-truncate" title="<?= htmlspecialchars($erroAppItem['mensagem_erro']) ?>">
                                                <i class="fas fa-triangle-exclamation me-1"></i>
                                                <?= htmlspecialchars(mb_strimwidth($erroAppItem['mensagem_erro'], 0, 60, "...")) ?>
                                            </strong>
                                            <small class="text-muted text-nowrap ps-2" title="Ocorrido em"><?= htmlspecialchars(formatarDataRelativa($erroAppItem['data_erro'])) ?></small>
                                        </div>
                                        <div class="x-small text-muted mt-1">
                                            <span class="me-2" title="Tipo do Erro"><i class="fas fa-tag fa-xs"></i> <?= htmlspecialchars($erroAppItem['tipo_erro'] ?? 'Geral') ?></span>
                                            <span title="Arquivo de Origem"><i class="fas fa-file-code fa-xs"></i> <?= htmlspecialchars($erroAppItem['arquivo_origem'] ?? 'N/A') ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-light text-center py-2">
                            <a href="<?= BASE_URL ?>admin/logs_aplicacao_detalhados.php" class="btn btn-outline-secondary btn-sm">Ver Logs Completos</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aba Integridade de Dados -->
        <div class="tab-pane fade" id="integridade-dados-content" role="tabpanel">
            <h5 class="mb-3 fw-semibold"><i class="fas fa-check-double me-2 text-primary opacity-75"></i>Verificações de Integridade</h5>
            <div class="alert alert-warning small">
                <i class="fas fa-triangle-exclamation me-1"></i> Esta seção indica inconsistências nos dados que podem exigir manutenção.
            </div>
            <div class="row g-3">
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted small text-uppercase">Auditorias Sem Responsável</h6>
                            <p class="card-text fs-4 fw-bold <?= ($integridadeDados['auditorias_sem_responsavel'] > 0) ? 'text-danger' : 'text-success' ?>">
                                <?= htmlspecialchars($integridadeDados['auditorias_sem_responsavel']) ?>
                            </p>
                            <small class="text-muted">Auditorias em andamento sem responsável designado.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted small text-uppercase">Itens de Auditoria Órfãos</h6>
                            <p class="card-text fs-4 fw-bold <?= ($integridadeDados['itens_sem_requisito_valido'] > 0) ? 'text-danger' : 'text-success' ?>">
                                <?= htmlspecialchars($integridadeDados['itens_sem_requisito_valido']) ?>
                            </p>
                            <small class="text-muted">Itens em auditorias ativas com requisitos inválidos.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted small text-uppercase">Empresas Sem Plano Ativo</h6>
                            <p class="card-text fs-4 fw-bold <?= ($integridadeDados['empresas_sem_plano_ativo'] > 0) ? 'text-warning' : 'text-success' ?>">
                                <?= htmlspecialchars($integridadeDados['empresas_sem_plano_ativo']) ?>
                            </p>
                            <small class="text-muted">Empresas ativas sem plano de assinatura válido.</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <button type="button" class="btn btn-primary btn-sm" onclick="window.location.reload();">
                    <i class="fas fa-sync-alt me-1"></i> Executar Verificação Agora
                </button>
            </div>
        </div>
    </div>
</div>

<?php echo getFooterAdmin(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Função para destruir gráfico existente
    function destroyExistingChart(canvasId) {
        const existingChart = Chart.getChart(canvasId);
        if (existingChart) existingChart.destroy();
    }

    // Gráfico: Tendência de Falhas de Login
    const ctxFalhasLoginPlataforma = document.getElementById('plataformaTendenciaLoginsChart');
    if (ctxFalhasLoginPlataforma) {
        const falhasLoginLabelsPlat = <?= json_encode($tendenciaLoginsFalhos['labels'] ?? []) ?>;
        const falhasLoginDataPlat = <?= json_encode($tendenciaLoginsFalhos['data_falhas'] ?? []) ?>;
        const dangerColor = getComputedStyle(document.documentElement).getPropertyValue('--bs-danger').trim() || '#dc3545';

        destroyExistingChart('plataformaTendenciaLoginsChart');
        if (falhasLoginLabelsPlat.length > 0) {
            new Chart(ctxFalhasLoginPlataforma, {
                type: 'line',
                data: {
                    labels: falhasLoginLabelsPlat,
                    datasets: [{
                        label: 'Falhas de Login',
                        data: falhasLoginDataPlat,
                        borderColor: dangerColor,
                        backgroundColor: hexToRgba(dangerColor, 0.1),
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointBackgroundColor: dangerColor
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0, stepSize: Math.max(1, Math.ceil(Math.max(...falhasLoginDataPlat) / 5) || 1) } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    }

    // Inicializar Abas
    const triggerTabList = document.querySelectorAll('#monitoramentoTabs button');
    triggerTabList.forEach(triggerEl => {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        triggerEl.addEventListener('click', event => {
            event.preventDefault();
            tabTrigger.show();
        });
    });

    // Tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
});

// Função hexToRgba
function hexToRgba(hex, alpha = 1) {
    if (!hex || typeof hex !== 'string' || hex.charAt(0) !== '#') return `rgba(128,128,128, ${alpha})`;
    const bigint = parseInt(hex.slice(1), 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
</script>