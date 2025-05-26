<?php
// gestor/dashboard_gestor.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_gestor.php'; // Layout do Gestor
require_once __DIR__ . '/../includes/gestor_functions.php';
require_once __DIR__ . '/../includes/db.php';
// Para getComunicadosAtivos, pode estar em admin_functions ou um novo platform_functions.php
// require_once __DIR__ . '/../includes/admin_functions.php'; // Descomente se getComunicadosAtivos estiver lá

// Proteção e Verificação de Perfil
protegerPagina($conexao);
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
$usuario_gestor = dbGetDadosUsuario($conexao, $gestor_id);
if (!$usuario_gestor) {
    dbRegistrarLogAcesso($gestor_id, $_SERVER['REMOTE_ADDR'], 'dashboard_gestor_erro_usuario', 0, "ID de gestor não encontrado: $gestor_id", $conexao);
    header('Location: ' . BASE_URL . 'logout.php?erro=dados_usuario_invalido');
    exit;
}
$primeiro_acesso = ($usuario_gestor['primeiro_acesso'] == 1);

// --- Inicialização dos Dados para o Dashboard ---
$statsGestor = [
    'auditorias_ativas_total' => 0, 'auditorias_para_revisao_gestor' => 0,
    'planos_acao_abertos_gestor' => 0, 'planos_acao_atrasados_gestor' => 0,
    'total_nao_conformidades_abertas_empresa' => 0,
    // Novos stats
    'planos_acao_abertos_empresa' => 0,
    'planos_acao_pend_verificacao_empresa' => 0,
    'planos_acao_atrasados_empresa' => 0,
];
$auditoriasParaRevisarLista = [];
$planosAcaoUrgentesGestorLista = []; // Renomeado para clareza
$proximasAuditoriasAgendadasLista = [];
$statusChartDataEmpresa = ['labels' => [], 'data' => [], 'colors' => []];
$conformidadeChartDataEmpresa = ['labels' => [], 'data' => [], 'colors' => []];
$planosAcaoStatusChartData = ['labels' => [], 'data' => [], 'colors' => []];
$NCPorAreaChartData = ['labels' => [], 'data' => [], 'colors' => []];
$alertasGestor = [];
$comunicadosPlataforma = [];


if (!$primeiro_acesso) {
    // Buscar comunicados da plataforma
    if (function_exists('getComunicadosAtivos')) { // Função a ser criada
        $comunicadosPlataforma = getComunicadosAtivos($conexao);
    }

    // Stats existentes e novos (getGestorDashboardStats precisará ser atualizada)
    if (function_exists('getGestorDashboardStats')) {
        $statsNovos = getGestorDashboardStats($conexao, $empresa_id, $gestor_id);
        $statsGestor = array_merge($statsGestor, $statsNovos); // Mescla, priorizando os novos se houver conflito
    }

    // Listas rápidas
    if (function_exists('getAuditoriasParaRevisaoGestor')) { // Nome antigo da sua função
        $auditoriasParaRevisarLista = getAuditoriasParaRevisaoGestor($conexao, $empresa_id, $gestor_id, 3); // Limite menor para lista
    }
    if (function_exists('getPlanosAcaoUrgentesGestor')) { // Função a ser criada
        $planosAcaoUrgentesGestorLista = getPlanosAcaoUrgentesGestor($conexao, $gestor_id, $empresa_id, 7, 3); // Vencendo em 7 dias, limite de 3
    }
    if (function_exists('getProximasAuditoriasAgendadasEmpresa')) {
        $proximasAuditoriasAgendadasLista = getProximasAuditoriasAgendadasEmpresa($conexao, $empresa_id, 3);
    }

    // Gráficos
    if (function_exists('getAuditoriaStatusChartDataEmpresa')) { // Nome antigo da sua função
        $statusChartDataEmpresa = getAuditoriaStatusChartDataEmpresa($conexao, $empresa_id);
    }
    if (function_exists('getConformidadeTipoOuCriticidadeChartData')) { // Nome antigo
        $conformidadeChartDataEmpresa = getConformidadeTipoOuCriticidadeChartData($conexao, $empresa_id, 'criticidade_achado'); // Mudar para criticidade
    }
    if (function_exists('getPlanosAcaoStatusDashboardChart')) { // Nova função para gráfico de PA
        $planosAcaoStatusChartData = getPlanosAcaoStatusDashboardChart($conexao, $empresa_id);
    }
    if (function_exists('getNaoConformidadesPorAreaChartData')) { // Nova função para gráfico de NC por Área
        $NCPorAreaChartData = getNaoConformidadesPorAreaChartData($conexao, $empresa_id);
    }


    // Alertas
    if (function_exists('getAlertasParaGestor')) {
        $alertasGestor = getAlertasParaGestor($conexao, $empresa_id, $gestor_id);
    }
}

$title = "Painel de Controle Gestor - " . htmlspecialchars($empresa_nome);
echo getHeaderGestor($title);
$csrf_token_page = $_SESSION['csrf_token'];
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
            <div class="btn-toolbar flex-wrap gap-2">
                <a href="<?= BASE_URL ?>gestor/auditoria/solicitar_nova_auditoria_baseada_em_risco.php" class="btn btn-sm btn-outline-danger rounded-pill shadow-sm px-3" title="Planejar auditoria com base na análise de criticidade de áreas/processos">
                    <i class="fas fa-shield-virus me-1"></i> Auditoria por Risco
                </a>
                <a href="<?= BASE_URL ?>gestor/auditoria/criar_auditoria.php" class="btn btn-sm btn-primary rounded-pill shadow-sm px-3">
                    <i class="fas fa-plus me-1"></i> Auditoria Ad-hoc
                </a>
                 <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill dropdown-toggle px-3" type="button" id="dropdownAcoesRapidas" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bolt me-1"></i> Ações Rápidas
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-light" aria-labelledby="dropdownAcoesRapidas">
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/auditoria/meu_plano_de_auditoria_anual.php"><i class="fas fa-calendar-alt fa-fw me-2 text-muted"></i>Ver Plano Anual</a></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/auditoria/gestao_consolidada_planos_de_acao.php"><i class="fas fa-tasks-alt fa-fw me-2 text-muted"></i>Gerenciar Planos de Ação</a></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/relatorio_de_tendencias_de_nao_conformidades.php"><i class="fas fa-chart-line fa-fw me-2 text-muted"></i>Relatórios de NC</a></li>
                        <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/documentacao_central_da_area.php"><i class="fas fa-folder-open fa-fw me-2 text-muted"></i>Documentação Central</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if ($sucesso_msg): ?><div class="alert alert-success gestor-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($erro_msg): ?><div class="alert alert-danger gestor-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= $erro_msg ?><button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <!-- Comunicados da Plataforma -->
        <?php if(!empty($comunicadosPlataforma)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm rounded-3 border-info border-2">
                    <div class="card-header bg-info-subtle border-bottom-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-info-emphasis"><i class="fas fa-bullhorn me-2"></i>Comunicados Importantes da AcodITools</h6>
                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close" onclick="this.closest('.card').remove();"></button>
                    </div>
                    <div class="card-body p-3">
                        <?php foreach ($comunicadosPlataforma as $comunicado): ?>
                            <div class="comunicado-item mb-2 pb-2 <?= count($comunicadosPlataforma) > 1 ? 'border-bottom' : '' ?>">
                                <h6 class="fw-semibold mb-1 small text-primary"><?= htmlspecialchars($comunicado['titulo_comunicado']) ?> <small class="text-muted fw-normal ms-2">(<?= formatarDataSimples($comunicado['data_publicacao']) ?>)</small></h6>
                                <div class="x-small lh-sm text-body-secondary"><?= nl2br(htmlspecialchars($comunicado['conteudo_comunicado'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <!-- Seção de Alertas do Gestor -->
        <?php if(!empty($alertasGestor)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm rounded-3 border-warning border-2">
                    <div class="card-header bg-warning-subtle border-bottom-0 pt-3 pb-2">
                        <h6 class="mb-0 fw-bold text-warning-emphasis"><i class="fas fa-bell me-2"></i>Meus Avisos e Pendências</h6>
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
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-3 g-3 mb-4">
            <div class="col">
                <a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php?filtro_status=Planejada&filtro_status=Em Andamento" class="card text-decoration-none shadow-sm border-start border-5 border-primary h-100 dashboard-stat-card">
                    <div class="card-body text-center p-3"><div class="fs-2 fw-bolder"><?= htmlspecialchars($statsGestor['auditorias_ativas_total'] ?? 0) ?></div><div class="small text-uppercase text-muted fw-semibold">Auditorias Ativas/Planejadas</div></div>
                </a>
            </div>
            <div class="col">
                <a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php?filtro_status=Concluída (Auditor)" class="card text-decoration-none shadow-sm border-start border-5 border-warning h-100 dashboard-stat-card">
                     <div class="card-body text-center p-3"><div class="fs-2 fw-bolder"><?= htmlspecialchars($statsGestor['auditorias_para_revisao_gestor'] ?? 0) ?></div><div class="small text-uppercase text-muted fw-semibold">Aguardando Sua Revisão</div></div>
                </a>
            </div>
             <div class="col">
                <a href="<?= BASE_URL ?>gestor/relatorio_de_tendencias_de_nao_conformidades.php" class="card text-decoration-none shadow-sm border-start border-5 border-danger h-100 dashboard-stat-card">
                    <div class="card-body text-center p-3"><div class="fs-2 fw-bolder"><?= htmlspecialchars($statsGestor['total_nao_conformidades_abertas_empresa'] ?? 0) ?></div><div class="small text-uppercase text-muted fw-semibold">NCs Abertas (Empresa)</div></div>
                </a>
            </div>
            <div class="col">
                 <a href="<?= BASE_URL ?>gestor/auditoria/gestao_consolidada_planos_de_acao.php?filtro_status_pa=Pendente&filtro_status_pa=Em Andamento" class="card text-decoration-none shadow-sm border-start border-5 border-info h-100 dashboard-stat-card">
                    <div class="card-body text-center p-3"><div class="fs-2 fw-bolder"><?= htmlspecialchars($statsGestor['planos_acao_abertos_empresa'] ?? 0) ?></div><div class="small text-uppercase text-muted fw-semibold">P. Ação Abertos (Empresa)</div></div>
                </a>
            </div>
            <div class="col">
                <a href="<?= BASE_URL ?>gestor/auditoria/gestao_consolidada_planos_de_acao.php?filtro_status_pa=Concluído (Aguardando Verificação)" class="card text-decoration-none shadow-sm border-start border-5 border-purple h-100 dashboard-stat-card">
                    <div class="card-body text-center p-3"><div class="fs-2 fw-bolder"><?= htmlspecialchars($statsGestor['planos_acao_pend_verificacao_empresa'] ?? 0) ?></div><div class="small text-uppercase text-muted fw-semibold">P. Ação P/ Verificação (Empresa)</div></div>
                </a>
            </div>
            <div class="col">
                <a href="<?= BASE_URL ?>gestor/auditoria/gestao_consolidada_planos_de_acao.php?filtro_status_pa=Atrasado" class="card text-decoration-none shadow-sm border-start border-5 border-orange h-100 dashboard-stat-card">
                    <div class="card-body text-center p-3"><div class="fs-2 fw-bolder"><?= htmlspecialchars($statsGestor['planos_acao_atrasados_empresa'] ?? 0) ?></div><div class="small text-uppercase text-muted fw-semibold">P. Ação Atrasados (Empresa)</div></div>
                </a>
            </div>
        </div>

        <!-- Linha de Minhas Listas Rápidas -->
        <div class="row g-4 mb-4">
             <div class="col-lg-4">
                <div class="card shadow-sm rounded-3 border-0 h-100">
                    <div class="card-header bg-light border-bottom pt-3 pb-2">
                        <h6 class="mb-0 fw-bold d-flex align-items-center"><i class="fas fa-user-clock me-2 text-primary opacity-75"></i>Minhas Revisões Pendentes</h6>
                    </div>
                    <div class="list-group list-group-flush dashboard-list-group" style="max-height: 280px; overflow-y: auto;">
                        <?php if (empty($auditoriasParaRevisarLista)): ?>
                            <div class="list-group-item text-center text-muted small py-4"><i class="fas fa-thumbs-up fs-4 mb-2 d-block text-success"></i>Ótimo! Nenhuma revisão pendente.</div>
                        <?php else: ?>
                            <?php foreach ($auditoriasParaRevisarLista as $auditoria_rev): ?>
                                <a href="<?= BASE_URL ?>gestor/auditoria/revisar_auditoria.php?id=<?= $auditoria_rev['id'] ?>" class="list-group-item list-group-item-action py-2 px-3 list-group-item-light">
                                    <div class="d-flex w-100 justify-content-between">
                                        <span class="mb-1 fw-semibold text-truncate" title="<?= htmlspecialchars($auditoria_rev['titulo']) ?>"><i class="fas fa-file-signature fa-fw me-1 text-warning"></i><?= htmlspecialchars(mb_strimwidth($auditoria_rev['titulo'],0,25,"...")) ?></span>
                                        <small class="text-muted text-nowrap ps-2" title="Concluída pelo auditor em <?= formatarDataCompleta($auditoria_rev['data_conclusao_auditor']) ?>"><i class="far fa-clock me-1"></i> <?= formatarDataRelativa($auditoria_rev['data_conclusao_auditor']) ?></small>
                                    </div>
                                    <small class="text-muted d-block x-small ms-1">Responsável: <?= htmlspecialchars($auditoria_rev['responsavel_display'] ?? 'N/A') ?></small>
                                </a>
                            <?php endforeach; ?>
                             <a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php?filtro_status=Concluída (Auditor)" class="list-group-item text-center small py-2 bg-light-subtle text-primary fw-semibold">Ver Todas as Minhas Revisões</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm rounded-3 border-0 h-100">
                    <div class="card-header bg-light border-bottom pt-3 pb-2">
                        <h6 class="mb-0 fw-bold d-flex align-items-center"><i class="fas fa-tasks me-2 text-primary opacity-75"></i>Meus Planos de Ação Urgentes</h6>
                    </div>
                    <div class="list-group list-group-flush dashboard-list-group" style="max-height: 280px; overflow-y: auto;">
                        <?php if (empty($planosAcaoUrgentesGestorLista)): ?>
                            <div class="list-group-item text-center text-muted small py-4"><i class="fas fa-clipboard-check fs-4 mb-2 d-block text-success"></i>Nenhum plano de ação urgente sob sua responsabilidade.</div>
                        <?php else: ?>
                            <?php foreach ($planosAcaoUrgentesGestorLista as $pa_urgente): ?>
                                <a href="<?= BASE_URL ?>gestor/auditoria/detalhes_plano_acao.php?id=<?= $pa_urgente['id'] ?>" class="list-group-item list-group-item-action py-2 px-3 list-group-item-light">
                                    <div class="d-flex w-100 justify-content-between">
                                        <span class="mb-1 fw-semibold text-truncate" title="<?= htmlspecialchars($pa_urgente['descricao_acao']) ?>"><i class="fas fa-flag fa-fw me-1 <?= ($pa_urgente['status_acao'] === 'Atrasada' ? 'text-danger' : 'text-warning') ?>"></i><?= htmlspecialchars(mb_strimwidth($pa_urgente['descricao_acao'],0,25,"...")) ?></span>
                                        <small class="text-danger text-nowrap ps-2 fw-medium" title="Prazo: <?= formatarDataSimples($pa_urgente['prazo_conclusao']) ?>"><i class="far fa-calendar-times me-1"></i> <?= formatarDataSimples($pa_urgente['prazo_conclusao']) ?></small>
                                    </div>
                                    <small class="text-muted d-block x-small ms-1">Origem: Aud. #<?= $pa_urgente['auditoria_origem_id'] ?> (<?= htmlspecialchars(mb_strimwidth($pa_urgente['titulo_auditoria_origem'],0,20,"...")) ?>)</small>
                                </a>
                            <?php endforeach; ?>
                             <a href="<?= BASE_URL ?>gestor/auditoria/gestao_consolidada_planos_de_acao.php?filtro_responsavel_id=<?= $gestor_id ?>&filtro_status_pa=Pendente&filtro_status_pa=Em Andamento&filtro_status_pa=Atrasado" class="list-group-item text-center small py-2 bg-light-subtle text-primary fw-semibold">Ver Todos os Meus Planos de Ação</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm rounded-3 border-0 h-100">
                    <div class="card-header bg-light border-bottom pt-3 pb-2">
                        <h6 class="mb-0 fw-bold d-flex align-items-center"><i class="far fa-calendar-check me-2 text-primary opacity-75"></i>Auditorias Agendadas (Empresa)</h6>
                    </div>
                     <div class="list-group list-group-flush dashboard-list-group" style="max-height: 280px; overflow-y: auto;">
                        <?php if (empty($proximasAuditoriasAgendadasLista)): ?>
                            <div class="list-group-item text-center text-muted small py-4"><i class="fas fa-calendar-day fs-4 mb-2 d-block text-info"></i>Nenhuma auditoria agendada em breve na empresa.</div>
                        <?php else: ?>
                            <?php foreach ($proximasAuditoriasAgendadasLista as $aud_agenda): ?>
                                <a href="<?= BASE_URL ?>gestor/auditoria/detalhes_auditoria.php?id=<?= $aud_agenda['id'] ?>" class="list-group-item list-group-item-action py-2 px-3 list-group-item-light">
                                    <div class="d-flex w-100 justify-content-between">
                                        <span class="mb-1 fw-semibold text-truncate" title="<?= htmlspecialchars($aud_agenda['titulo']) ?>"><i class="fas fa-hourglass-start fa-fw me-1 text-info"></i><?= htmlspecialchars(mb_strimwidth($aud_agenda['titulo'],0,25,"...")) ?></span>
                                        <small class="text-success text-nowrap ps-2 fw-medium" title="Inicia em <?= formatarDataSimples($aud_agenda['data_inicio_planejada']) ?>"><i class="far fa-calendar-check me-1"></i> <?= formatarDataSimples($aud_agenda['data_inicio_planejada']) ?></small>
                                    </div>
                                    <small class="text-muted d-block x-small ms-1">Responsável: <?= htmlspecialchars($aud_agenda['responsavel_display'] ?? 'A definir') ?></small>
                                </a>
                            <?php endforeach; ?>
                              <a href="<?= BASE_URL ?>gestor/auditoria/meu_plano_de_auditoria_anual.php" class="list-group-item text-center small py-2 bg-light-subtle text-primary fw-semibold">Ver Plano Anual Completo da Empresa</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos de Status e Conformidade -->
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm h-100 rounded-3 border-0">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-pie-chart me-2 text-info opacity-75"></i>Status das Auditorias (Empresa)</h6></div>
                    <div class="card-body d-flex align-items-center justify-content-center p-2" style="min-height: 250px;">
                        <?php if (!empty($statusChartDataEmpresa['labels']) && (array_sum($statusChartDataEmpresa['data'] ?? []) > 0 || count($statusChartDataEmpresa['labels']) > 0) ): ?>
                            <canvas id="gestorStatusAuditoriaChart"></canvas>
                        <?php else: ?>
                            <div class="text-center text-muted py-4"><i class="fas fa-chart-pie fs-4 mb-2 d-block opacity-50"></i> Sem dados de auditorias.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
             <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm h-100 rounded-3 border-0">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-tasks-alt me-2 text-purple opacity-75"></i>Status dos Planos de Ação (Empresa)</h6></div>
                    <div class="card-body d-flex align-items-center justify-content-center p-2" style="min-height: 250px;">
                        <?php if (!empty($planosAcaoStatusChartData['labels']) && (array_sum($planosAcaoStatusChartData['data'] ?? []) > 0)): ?>
                            <canvas id="gestorPlanosAcaoStatusChart"></canvas>
                        <?php else: ?>
                            <div class="text-center text-muted py-4"><i class="fas fa-tasks fs-4 mb-2 d-block opacity-50"></i> Sem dados de planos de ação.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-12"> <?php /* Ocupa a linha inteira em MD se for o terceiro */ ?>
                <div class="card shadow-sm h-100 rounded-3 border-0">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-balance-scale-right me-2 text-success opacity-75"></i>NCs por Criticidade (Empresa)</h6></div>
                    <div class="card-body d-flex align-items-center justify-content-center p-2" style="min-height: 250px;">
                        <?php $totalItensGraficoConf = array_sum(array_values($conformidadeChartDataEmpresa['data'] ?? [])); ?>
                        <?php if ($totalItensGraficoConf > 0): ?>
                            <canvas id="gestorConformidadeChart"></canvas>
                        <?php else: ?>
                            <div class="text-center text-muted py-4"><i class="fas fa-tags fs-4 mb-2 d-block opacity-50"></i> Sem dados de não conformidades.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Futuro: Gráfico de NCs por Área/Processo
            <div class="col-lg-6 col-md-12">
                 <div class="card shadow-sm h-100 rounded-3 border-0">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-sitemap me-2 text-danger opacity-75"></i>NCs por Área/Processo (Top 5 - Empresa)</h6></div>
                    <div class="card-body d-flex align-items-center justify-content-center p-2" style="min-height: 250px;">
                        <?php // if (!empty($NCPorAreaChartData['labels']) && (array_sum($NCPorAreaChartData['data'] ?? []) > 0)): ?>
                            <canvas id="gestorNCPorAreaChart"></canvas>
                        <?php // else: ?>
                            <div class="text-center text-muted py-4"><i class="fas fa-info-circle fs-4 mb-2 d-block opacity-50"></i> Sem dados de NC por área.</div>
                        <?php // endif; ?>
                    </div>
                </div>
            </div>
            -->
        </div>
    <?php endif; /* Fim do else (não é primeiro acesso) */ ?>
<?php
if (!$primeiro_acesso) { echo '</main>'; } // Fechar main apenas se não for primeiro acesso
echo getFooterGestor();
?>

<?php if (!$primeiro_acesso): ?>
<script>
    // Suas funções JS hexToRgba, destroyExistingChart e formatarDataRelativa DEVEM estar em um arquivo JS global carregado no footer
    // Por exemplo, assets/js/utils.js ou scripts_gestor.js

    function initDashboardGestorCharts() {
        if (typeof Chart === 'undefined') { setTimeout(initDashboardGestorCharts, 200); return; }

        // Gráfico Status Auditorias
        const statusLabels = <?= json_encode($statusChartDataEmpresa['labels'] ?? []) ?>;
        const statusData = <?= json_encode($statusChartDataEmpresa['data'] ?? []) ?>;
        const statusColorsPHP = <?= json_encode($statusChartDataEmpresa['colors'] ?? []) ?>; // Se você gerar cores no PHP
        const ctxStatus = document.getElementById('gestorStatusAuditoriaChart');

        if(ctxStatus && Array.isArray(statusLabels) && statusLabels.length > 0 && Array.isArray(statusData) && statusData.some(d => d > 0)) {
            const defaultStatusColors = {'Planejada':'#6f42c1', 'Em Andamento':'#0d6efd', 'Pausada':'#adb5bd', 'Concluída (Auditor)':'#ffc107', 'Aguardando Correção Auditor':'#fd7e14', 'Em Revisão':'#0dcaf0', 'Aprovada':'#198754', 'Rejeitada':'#dc3545', 'Cancelada':'#6c757d'};
            const backgroundColors = statusColorsPHP && statusColorsPHP.length === statusLabels.length ? statusColorsPHP : statusLabels.map(label => defaultStatusColors[label] || '#cccccc');
            destroyExistingChart('gestorStatusAuditoriaChart');
            new Chart(ctxStatus, {
                type: 'pie',
                data: { labels: statusLabels, datasets: [{ data: statusData, backgroundColor: backgroundColors, borderColor: '#fff', borderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels:{ padding:15, boxWidth:12, font:{size:11}}}}}
            });
        }

        // Gráfico Status Planos de Ação
        const paStatusLabels = <?= json_encode($planosAcaoStatusChartData['labels'] ?? []) ?>;
        const paStatusData = <?= json_encode($planosAcaoStatusChartData['data'] ?? []) ?>;
        const paStatusColorsPHP = <?= json_encode($planosAcaoStatusChartData['colors'] ?? []) ?>;
        const ctxPaStatus = document.getElementById('gestorPlanosAcaoStatusChart');

        if(ctxPaStatus && Array.isArray(paStatusLabels) && paStatusLabels.length > 0 && Array.isArray(paStatusData) && paStatusData.some(d => d > 0)) {
             const defaultPaColors = { 'Pendente': '#ffc107', 'Em Andamento': '#0dcaf0', 'Atrasada': '#dc3545', 'Concluído (Aguardando Verificação)': '#fd7e14', 'Verificada (Eficaz)': '#198754', 'Verificada (Ineficaz - Reabrir)': '#6f42c1', 'Cancelada': '#6c757d' };
            const backgroundColorsPA = paStatusColorsPHP && paStatusColorsPHP.length === paStatusLabels.length ? paStatusColorsPHP : paStatusLabels.map(label => defaultPaColors[label] || '#cccccc');
            destroyExistingChart('gestorPlanosAcaoStatusChart');
            new Chart(ctxPaStatus, {
                type: 'doughnut',
                data: { labels: paStatusLabels, datasets: [{ data: paStatusData, backgroundColor: backgroundColorsPA, borderColor: '#fff', borderWidth: 2, hoverOffset: 4 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom', labels:{ padding:15, boxWidth:12, font:{size:11}}}}}
            });
        }


        // Gráfico Conformidade/Criticidade NCs
        const conformidadeLabels = <?= json_encode($conformidadeChartDataEmpresa['labels'] ?? []) ?>;
        const conformidadeData = <?= json_encode($conformidadeChartDataEmpresa['data'] ?? []) ?>;
        const conformidadeColorsPHP = <?= json_encode($conformidadeChartDataEmpresa['colors'] ?? []) ?>;
        const ctxConformidade = document.getElementById('gestorConformidadeChart');
        const totalItensConf = conformidadeData.reduce((a, b) => a + b, 0);

        if(ctxConformidade && Array.isArray(conformidadeLabels) && conformidadeLabels.length > 0 && Array.isArray(conformidadeData) && totalItensConf > 0) {
            const defaultConfColors = {'Conforme':'#28a745', 'Não Conforme':'#dc3545', 'Parcial':'#ffc107', 'N/A':'#adb5bd', 'Baixa':'#17a2b8', 'Media':'#ffc107', 'Alta':'#fd7e14', 'Critica':'#dc3545'}; // Adicionei cores para criticidades
            const backgroundColorsConf = conformidadeColorsPHP && conformidadeColorsPHP.length === conformidadeLabels.length ? conformidadeColorsPHP : conformidadeLabels.map(label => defaultConfColors[label] || hexToRgba(defaultConfColors[Object.keys(defaultConfColors)[Math.floor(Math.random()*Object.keys(defaultConfColors).length)]], 0.7) ); // Cor aleatória se não mapeada
            destroyExistingChart('gestorConformidadeChart');
            new Chart(ctxConformidade, {
                type: 'bar', // Mudado para Bar para melhor visualização de múltiplas categorias
                data: {
                    labels: conformidadeLabels,
                    datasets: [{
                        label: 'Contagem de Itens/NCs',
                        data: conformidadeData,
                        backgroundColor: backgroundColorsConf,
                        borderColor: backgroundColorsConf.map(c => c.replace('1)', '0.7)')), // Para borda
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, indexAxis: 'y', // Barras horizontais
                    scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.raw}` }}}
                }
            });
        }

        // Futuro Gráfico NC por Área (Exemplo de estrutura, dados virão do PHP)
        // const ncAreaLabels = <?//= json_encode($NCPorAreaChartData['labels'] ?? []) ?>;
        // const ncAreaData = <?//= json_encode($NCPorAreaChartData['data'] ?? []) ?>;
        // const ncAreaColors = <?//= json_encode($NCPorAreaChartData['colors'] ?? []) ?>; // Opcional
        // const ctxNCArea = document.getElementById('gestorNCPorAreaChart');
        // if(ctxNCArea && ncAreaLabels.length > 0 && ncAreaData.some(d => d > 0)) { /* ... lógica do gráfico ... */ }

    }

    document.addEventListener('DOMContentLoaded', () => {
        initDashboardGestorCharts();
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(el){ return new bootstrap.Tooltip(el); });

        // Formatar datas relativas nas listas (mantido)
         document.querySelectorAll('.dashboard-list-group small.text-muted[title^="Concluída"]').forEach(el => {
            try {
                let isoDate = el.title.split('em ')[1];
                const timeIcon = el.querySelector('.far.fa-clock');
                const textNodeToUpdate = timeIcon ? timeIcon.nextSibling : el.firstChild;
                if (textNodeToUpdate && textNodeToUpdate.nodeType === Node.TEXT_NODE && isoDate && isoDate.length > 10) {
                    textNodeToUpdate.nodeValue = ` ${formatarDataRelativa(isoDate)}`;
                } else if (!timeIcon && isoDate && isoDate.length > 10) { // Adicionado verificação de isoDate
                    el.textContent = ` ${formatarDataRelativa(isoDate)}`;
                }
            } catch (e) { /* ignorar erros de formatação aqui */ }
        });
    });
</script>
<?php endif; ?>