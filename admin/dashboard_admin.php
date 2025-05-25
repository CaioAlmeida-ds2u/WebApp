<?php
// admin/dashboard_admin.php (Versão SaaS para Admin da Acoditools)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Funções do Admin da Acoditools

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Mantendo 'admin' conforme sua instrução
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens ---
$sucesso_msg = obter_flash_message('sucesso'); // Usando sua função de flash message
$erro_msg = obter_flash_message('erro');     // Usando sua função de flash message

// --- Verificar Primeiro Acesso ---
$usuario_id = $_SESSION['usuario_id'];
$usuario_admin_logado = dbGetDadosUsuario($conexao, $usuario_id);
if (!$usuario_admin_logado) {
    dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'dashboard_admin_erro_usuario', 0, "ID de admin não encontrado: $usuario_id", $conexao);
    header('Location: ' . BASE_URL . 'logout.php?erro=usuario_invalido');
    exit;
}
$primeiro_acesso = ($usuario_admin_logado['primeiro_acesso'] == 1);

// --- Buscar Dados para o Dashboard (APENAS SE NÃO FOR PRIMEIRO ACESSO) ---
$countsPlataforma = [ /* Valores Default */
    'solicitacoes_acesso_pendentes_globais' => 0,
    'solicitacoes_reset_pendentes_globais' => 0,
    'total_usuarios_admin_plataforma_ativos' => 0,
    'total_usuarios_clientes_ativos' => 0,
    'total_empresas_clientes_ativas' => 0,
    'total_empresas_clientes_teste' => 0,
    'total_empresas_clientes_suspensas' => 0,
    'total_requisitos_globais_ativos' => 0,
    'total_modelos_globais_ativos' => 0,
    'total_planos_assinatura_ativos' => 0,
    'tickets_suporte_abertos' => 0,
];
$empresasPorPlano = [];
$alertasPlataforma = [];
$logsRecentesPlataforma = [];
$graficoErrosLogin = ['labels' => [], 'data' => []];
$graficoNovasContas = ['labels' => [], 'data' => []];

if (!$primeiro_acesso) {
    // Chamadas para funções que VOCÊ PRECISARÁ IMPLEMENTAR em admin_functions.php
    if (function_exists('getDashboardCountsAdminAcoditools')) {
        $countsPlataforma = getDashboardCountsAdminAcoditools($conexao);
    }
    if (function_exists('getContagemEmpresasPorPlano')) {
        $empresasPorPlano = getContagemEmpresasPorPlano($conexao);
    }
    if (function_exists('getAlertasPlataformaAdmin')) {
        $alertasPlataforma = getAlertasPlataformaAdmin($conexao);
    }
    if (function_exists('getNovasContasClientesPorPeriodo')) {
        $graficoNovasContas = getNovasContasClientesPorPeriodo($conexao, 'mes', 6);
    }

    // Função getLogsAcesso precisa ser capaz de lidar com o contexto de admin da plataforma (ver todos os logs)
    // e idealmente trazer o nome da empresa se o log for de um usuário cliente.
    $logsRecentesData = getLogsAcesso($conexao, 1, 7, '', '', null, '', '', ''); // Pegar 7 logs recentes de toda plataforma
    $logsRecentesPlataforma = $logsRecentesData['logs'] ?? [];

    // Gráfico de Erros de Login (função existente, mas garantir que é global)
    $graficoErrosLogin = getLoginLogsLast7Days($conexao); // Essa função já busca globalmente? Se não, adaptar.
}

// --- Geração do HTML ---
$title = "ACodITools - Dashboard da Plataforma";
echo getHeaderAdmin($title); // Função do seu layout_admin.php
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
                        <p class="text-muted mb-3">Como este é seu primeiro acesso à ferramenta AcodITools, por segurança, você precisa definir uma nova senha pessoal.</p>
                        <form id="formRedefinirSenha" novalidate>
                            <input type="hidden" name="csrf_token_modal" value="<?= htmlspecialchars($csrf_token) ?>">
                            <div class="mb-3">
                                <label for="nova_senha_modal" class="form-label form-label-sm">Nova Senha</label>
                                <input type="password" class="form-control form-control-sm" id="nova_senha_modal" name="nova_senha" required minlength="8" aria-describedby="novaSenhaHelpModal">
                                <div id="novaSenhaHelpModal" class="form-text small">Mínimo 8 caracteres, incluindo maiúscula, minúscula e número.</div>
                                <div class="invalid-feedback small">Senha inválida. Verifique os requisitos.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirmar_senha_modal" class="form-label form-label-sm">Confirmar Nova Senha</label>
                                <input type="password" class="form-control form-control-sm" id="confirmar_senha_modal" name="confirmar_senha" required>
                                <div class="invalid-feedback small">As senhas não coincidem.</div>
                            </div>
                            <div id="senha_error" class="alert alert-danger alert-sm mt-3 p-2 small" style="display: none;"></div>
                            <div id="senha_sucesso" class="alert alert-success alert-sm mt-3 p-2 small" style="display: none;"></div>
                            <button type="submit" class="btn btn-primary w-100 mt-3">Redefinir Senha e Acessar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="container-fluid px-md-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center pt-3 pb-2 mb-4 border-bottom">
            <div class="mb-3 mb-md-0 text-center text-md-start">
                <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-server me-2 text-primary"></i>Dashboard da Ferramenta AcodITools</h1>
                <p class="text-muted small mb-0">Visão geral da saúde da ferramenta, clientes e configurações globais.</p>
            </div>
            <div class="btn-toolbar justify-content-center justify-content-md-end" role="toolbar" aria-label="Ações rápidas da plataforma">
                 <div class="btn-group btn-group-sm me-md-2 mb-2 mb-md-0 shadow-sm rounded">
                    <a href="<?= BASE_URL ?>admin/admin_gerenciamento_contas_clientes.php" class="btn btn-light border-secondary-subtle" title="Gerenciar Empresas Clientes"><i class="fas fa-city text-primary me-1"></i>Clientes</a>
                    <a href="<?= BASE_URL ?>admin/plataforma_gestao_planos_assinatura.php" class="btn btn-light border-secondary-subtle" title="Gerenciar Planos de Assinatura"><i class="fas fa-file-invoice-dollar text-primary me-1"></i>Planos</a>
                 </div>
                 <div class="btn-group btn-group-sm shadow-sm rounded">
                    <a href="#" class="btn btn-light border-secondary-subtle dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Configurações Globais da Plataforma">
                        <i class="fas fa-tools text-primary me-1"></i> Configurar Ferramenta
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-light mt-1">
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/modelo/modelo_index.php"><i class="fas fa-clipboard-list fa-fw me-2 text-muted"></i> Biblioteca de Modelos</a></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php"><i class="fas fa-tasks fa-fw me-2 text-muted"></i> Biblioteca de Requisitos</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_config_metodologia_risco.php"><i class="fas fa-shield-alt fa-fw me-2 text-muted"></i> Metodologia de Risco</a></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_catalogos_globais.php"><i class="fas fa-tags fa-fw me-2 text-muted"></i> Catálogos (NC, Criticidade)</a></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_config_workflows_auditoria.php"><i class="fas fa-project-diagram fa-fw me-2 text-muted"></i> Workflows de Auditoria</a></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_gerenciamento_campos_personalizados.php"><i class="fas fa-puzzle-piece fa-fw me-2 text-muted"></i> Campos Personalizados</a></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_parametros_globais.php"><i class="fas fa-sliders-h fa-fw me-2 text-muted"></i> Parâmetros Gerais</a></li>
                         <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_monitoramento_e_saude.php"><i class="fas fa-heartbeat fa-fw me-2 text-muted"></i> Monitoramento e Saúde</a></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/logs.php"><i class="fas fa-history fa-fw me-2 text-muted"></i> Logs Detalhados</a></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/admin_comunicados_plataforma.php"><i class="fas fa-bullhorn fa-fw me-2 text-muted"></i> Comunicados</a></li>
                         <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/configuracoes_admin.php"><i class="fas fa-user-cog fa-fw me-2 text-muted"></i> Meu Perfil</a></li>
                    </ul>
                 </div>
            </div>
        </div>

        <?php if ($sucesso_msg): ?><div class="alert alert-success gestor-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($erro_msg): ?><div class="alert alert-danger gestor-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= $erro_msg ?><button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <!-- Cards de Stats para Admin da Plataforma -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 row-cols-xxl-5 g-3 mb-4">
            <div class="col">
                <a href="<?= BASE_URL ?>admin/admin_gerenciamento_contas_clientes.php?status_contrato_filtro=Ativo" class="card text-decoration-none shadow-sm border-start border-5 border-success h-100 dashboard-stat-card">
                    <div class="card-body d-flex justify-content-between align-items-center py-3 px-3">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Clientes Ativos</div>
                            <div class="fs-3 fw-bolder"><?= htmlspecialchars($countsPlataforma['total_empresas_clientes_ativas'] ?? 0) ?></div>
                        </div><i class="fas fa-city fa-2x text-black-50 opacity-50"></i>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="<?= BASE_URL ?>admin/usuarios.php?filtro_perfil=gestor_empresa&filtro_perfil=auditor_empresa&filtro_perfil=auditado_contato&filtro_ativo=1" class="card text-decoration-none shadow-sm border-start border-5 border-primary h-100 dashboard-stat-card">
                    <div class="card-body d-flex justify-content-between align-items-center py-3 px-3">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Total Usuários Clientes</div>
                            <div class="fs-3 fw-bolder"><?= htmlspecialchars($countsPlataforma['total_usuarios_clientes_ativos'] ?? 0) ?></div>
                        </div><i class="fas fa-users fa-2x text-black-50 opacity-50"></i>
                    </div>
                </a>
            </div>
             <div class="col">
                <a href="<?= BASE_URL ?>admin/plataforma_gestao_planos_assinatura.php" class="card text-decoration-none shadow-sm border-start border-5 border-info h-100 dashboard-stat-card">
                    <div class="card-body d-flex justify-content-between align-items-center py-3 px-3">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Planos de Assinatura</div>
                            <div class="fs-3 fw-bolder"><?= htmlspecialchars($countsPlataforma['total_planos_assinatura_ativos'] ?? 0) ?></div>
                        </div><i class="fas fa-file-invoice-dollar fa-2x text-black-50 opacity-50"></i>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="<?= BASE_URL ?>admin/usuarios.php?aba=solicitacoes-acesso-tab" class="card text-decoration-none shadow-sm border-start border-5 border-warning h-100 dashboard-stat-card">
                    <div class="card-body d-flex justify-content-between align-items-center py-3 px-3">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Acessos Pendentes</div>
                            <div class="fs-3 fw-bolder"><?= htmlspecialchars($countsPlataforma['solicitacoes_acesso_pendentes_globais'] ?? 0) ?></div>
                        </div><i class="fas fa-user-plus fa-2x text-black-50 opacity-50"></i>
                    </div>
                </a>
            </div>
             <div class="col">
                <a href="<?= BASE_URL ?>admin/admin_suporte_a_clientes.php" class="card text-decoration-none shadow-sm border-start border-5 border-danger h-100 dashboard-stat-card">
                    <div class="card-body d-flex justify-content-between align-items-center py-3 px-3">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Tickets Suporte Abertos</div>
                            <div class="fs-3 fw-bolder"><?= htmlspecialchars($countsPlataforma['tickets_suporte_abertos'] ?? 0) ?></div> <?php /* Implementar se tiver tickets */ ?>
                        </div><i class="fas fa-headset fa-2x text-black-50 opacity-50"></i>
                    </div>
                </a>
            </div>
        </div>

        <!-- Seção de Alertas da Plataforma -->
        <?php if(!empty($alertasPlataforma)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm rounded-3 border-0">
                    <div class="card-header bg-warning-subtle border-bottom-0 pt-3 pb-2">
                        <h6 class="mb-0 fw-bold text-warning-emphasis"><i class="fas fa-bell me-2"></i>Alertas da Plataforma</h6>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 250px; overflow-y: auto;">
                        <?php foreach ($alertasPlataforma as $alerta): ?>
                            <a href="<?= htmlspecialchars($alerta['link'] ?? '#!') ?>" class="list-group-item list-group-item-action small py-2 px-3 list-group-item-light">
                                <i class="fas <?= ($alerta['tipo'] === 'contrato_vencendo' ? 'fa-file-contract text-danger' : ($alerta['tipo'] === 'limite_atingido' ? 'fa-exclamation-triangle text-warning' : 'fa-info-circle text-info') ) ?> fa-fw me-2"></i>
                                <?= htmlspecialchars($alerta['mensagem']) ?>
                                <?php if(isset($alerta['data_alerta_formatada'])): ?> <small class="text-muted ms-2">(<?= htmlspecialchars($alerta['data_alerta_formatada']) ?>)</small> <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                     <?php if(count($alertasPlataforma) >= 5 ): // Exemplo de link para ver todos os alertas ?>
                        <div class="card-footer text-center py-2 bg-light border-top">
                            <a href="<?= BASE_URL ?>admin/plataforma_alertas_completos.php" class="btn btn-sm btn-outline-secondary">Ver Todos os Alertas</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Linha de Gráficos e Atividades Recentes -->
        <div class="row g-4">
            <div class="col-lg-7 mb-4 mb-lg-0">
                <div class="card shadow-sm rounded-3 border-0 h-100">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-chart-line me-2 text-primary opacity-75"></i>Novas Empresas Clientes (Últimos 6 Meses)</h6></div>
                    <div class="card-body d-flex align-items-center justify-content-center p-2" style="min-height: 280px;">
                        <?php if (!empty($graficoNovasContas['labels']) && (array_sum($graficoNovasContas['data'] ?? []) > 0 || count($graficoNovasContas['labels']) > 0) ): ?>
                            <canvas id="novasContasChart"></canvas>
                        <?php else: ?>
                            <div class="text-center text-muted py-5"><i class="fas fa-info-circle fa-2x mb-2 text-light-emphasis"></i><span class="small">Sem dados suficientes para o gráfico de novas contas.</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm rounded-3 border-0 h-100">
                    <div class="card-header bg-light border-bottom pt-3 pb-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-stream me-2 text-info opacity-75"></i>Atividades Recentes na Plataforma</h6>
                            <a href="<?= BASE_URL ?>admin/logs.php" class="btn btn-outline-secondary btn-sm py-1 px-2" style="font-size: 0.75em;">Todos os Logs</a>
                        </div>
                    </div>
                    <div class="list-group list-group-flush dashboard-log-list" style="max-height: 300px; overflow-y: auto;">
                        <?php if (!empty($logsRecentesPlataforma)): ?>
                            <?php foreach ($logsRecentesPlataforma as $log):
                                $log_data = new DateTime($log['data_hora']);
                                $isSuccess = (bool) $log['sucesso'];
                                $iconClass = $isSuccess ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
                                $userText = htmlspecialchars($log['nome_usuario'] ?? 'Sistema');
                                $actionText = htmlspecialchars($log['acao']);
                                $empresaNomeLog = $log['nome_empresa_log'] ?? null; // Assumindo que getLogsAcesso pode retornar nome da empresa
                                $details = htmlspecialchars(mb_strimwidth($log['detalhes'] ?? '', 0, 50, "..."));
                            ?>
                            <div class="list-group-item px-3 py-2 lh-sm list-group-item-action-subtle">
                                <div class="d-flex w-100 justify-content-between mb-0">
                                    <div><i class="fas <?= $iconClass ?> fa-fw me-2 small"></i><strong class="fw-semibold small"><?= $actionText ?></strong>
                                        <?php if ($empresaNomeLog): ?>
                                            <span class="badge bg-light text-dark border x-small ms-1" title="Empresa Cliente: <?= htmlspecialchars($empresaNomeLog) ?>"><?= htmlspecialchars(mb_strimwidth($empresaNomeLog,0,15,"..")) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted text-nowrap ps-2" title="<?= $log_data->format('d/m/Y H:i:s') ?>"><?= formatarDataRelativa($log['data_hora']) ?></small>
                                </div>
                                <div class="text-muted small ps-4">
                                    <span class="me-1" title="Usuário: <?= $userText ?> (ID: <?= $log['usuario_id'] ?? 'N/A'?>)"><i class="fas fa-user fa-xs"></i> <?= $userText ?></span>
                                    <span title="IP: <?= htmlspecialchars($log['ip_address']) ?>"><i class="fas fa-network-wired fa-xs ms-2 me-1"></i><?= htmlspecialchars($log['ip_address']) ?></span>
                                    <?php if (!empty($log['detalhes'])): ?><span class="d-block fst-italic mt-1 text-truncate" title="<?= htmlspecialchars($log['detalhes']) ?>">↳ <?= $details ?></span><?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center text-muted py-5"><i class="fas fa-info-circle fa-2x mb-2 text-light-emphasis"></i><span class="small">Nenhuma atividade recente na ferramenta.</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; /* Fim do else (não é primeiro acesso) */ ?>

<?php
echo getFooterAdmin(); // Função do seu layout_admin.php
?>

<?php if (!$primeiro_acesso): // Scripts JS apenas se não for primeiro acesso ?>
<script>
// Função auxiliar para converter HEX para RGBA (para os gráficos)
function hexToRgba(hex, alpha = 1) { /* ... (código da função hexToRgba) ... */ }
function destroyExistingChart(canvasId) { /* ... (código da função destroyExistingChart) ... */ }


document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });

    const ctxErrosLogin = document.getElementById('loginChart');
    if (ctxErrosLogin) {
        const errosLoginLabels = <?= json_encode($graficoErrosLogin['labels'] ?? []) ?>;
        const errosLoginData = <?= json_encode($graficoErrosLogin['data'] ?? []) ?>;
        const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--bs-primary').trim() || '#0d6efd';
        destroyExistingChart('loginChart'); // ID do canvas

        if (errosLoginLabels.length > 0 && (Math.max(...errosLoginData) >= 0 || errosLoginData.every(v => v === 0))) {
            new Chart(ctxErrosLogin, { /* ... config do gráfico de erros de login ... */ });
        } else {
            ctxErrosLogin.parentElement.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-shield-alt fa-2x mb-2 text-light-emphasis"></i><span class="small">Nenhuma falha de login nos últimos 7 dias.</span></div>';
        }
    }

    const ctxNovasContas = document.getElementById('novasContasChart');
    if (ctxNovasContas) {
        const novasContasLabels = <?= json_encode($graficoNovasContas['labels'] ?? []) ?>;
        const novasContasData = <?= json_encode($graficoNovasContas['data'] ?? []) ?>;
        const successColor = getComputedStyle(document.documentElement).getPropertyValue('--bs-success').trim() || '#198754';
        destroyExistingChart('novasContasChart');

        if (novasContasLabels.length > 0 && (Math.max(...novasContasData) >= 0 || novasContasData.every(v => v === 0))) {
            new Chart(ctxNovasContas, {  /* ... config do gráfico de novas contas ... */  });
        } else {
             ctxNovasContas.parentElement.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-chart-line fa-2x mb-2 text-light-emphasis"></i><span class="small">Sem dados para o gráfico de novas contas.</span></div>';
        }
    }
});
</script>
<?php endif; // Fim scripts JS ?>