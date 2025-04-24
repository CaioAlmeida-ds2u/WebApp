<?php
// gestor/dashboard_gestor.php - VERSÃO COMPLETA FINAL (com Modal, Gráficos Corrigidos e Logs JS)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_gestor.php'; // Layout do Gestor
require_once __DIR__ . '/../includes/gestor_functions.php';
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
$usuario_gestor = dbGetDadosUsuario($conexao, $gestor_id);
if (!$usuario_gestor) { header('Location: ' . BASE_URL . 'logout.php?erro=dados_usuario_invalido'); exit; }
$primeiro_acesso = ($usuario_gestor['primeiro_acesso'] == 1);

// --- Buscar Dados para o Dashboard (Apenas se NÃO for primeiro acesso) ---
$statsGestor = [];
$auditoriasParaRevisar = [];
$auditoresAtivos = [];
$statusChartData = ['labels' => [], 'data' => []];
$conformidadeChartData = ['labels' => [], 'data' => []];
$logsRecentes = [];

if (!$primeiro_acesso) {
    $statsGestor = getGestorDashboardStats($conexao, $empresa_id);
    $auditoriasParaRevisar = getAuditoriasParaRevisar($conexao, $empresa_id, 5);
    $auditoresAtivos = getAuditoresDaEmpresa($conexao, $empresa_id, 5);
    $statusChartData = getAuditoriaStatusChartData($conexao, $empresa_id);
    $conformidadeChartData = getConformidadeChartData($conexao, $empresa_id); // A função agora retorna ['labels'=>[], 'data'=>[]]
    $logsRecentesData = getLogsAcesso($conexao, 1, 5);
    $logsRecentes = $logsRecentesData['logs'] ?? [];
}

// --- Geração do HTML ---
$title = "Dashboard Gestor - " . htmlspecialchars($empresa_nome);
echo getHeaderGestor($title); // Usa header do gestor (que abre <main>)
?>

    <?php /* ----- MODAL DE PRIMEIRO ACESSO ----- */ ?>
    <?php if ($primeiro_acesso): ?>
    <div id="bloqueio-conteudo" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1060; display: flex; align-items: center; justify-content: center;">
        <div class="modal fade show" id="primeiroAcessoModal" tabindex="-1" aria-labelledby="primeiroAcessoModalLabel" aria-modal="true" role="dialog" style="display: block;">
            <div class="modal fade show" id="primeiroAcessoModal" tabindex="-1" aria-labelledby="primeiroAcessoModalLabel" aria-modal="true" role="dialog" style="display: block;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-0"><div class="modal-header bg-primary text-white"><h5 class="modal-title" id="primeiroAcessoModalLabel"><i class="fas fa-shield-alt me-2"></i>Primeiro Acesso - Redefinir Senha</h5></div><div class="modal-body p-4"><p class="text-muted mb-3">Por segurança, defina uma nova senha.</p><form id="formRedefinirSenha" novalidate><input type="hidden" name="csrf_token_modal" value="<?= htmlspecialchars($csrf_token) ?>"><div class="mb-3"><label for="nova_senha_modal" class="form-label form-label-sm">Nova Senha</label><input type="password" class="form-control form-control-sm" id="nova_senha_modal" name="nova_senha" required minlength="8" aria-describedby="novaSenhaHelp"><div id="novaSenhaHelp" class="form-text small">Mínimo 8 caracteres, com maiúscula, minúscula e número.</div><div class="invalid-feedback small">Senha inválida. Verifique os requisitos.</div></div><div class="mb-3"><label for="confirmar_senha_modal" class="form-label form-label-sm">Confirmar Nova Senha</label><input type="password" class="form-control form-control-sm" id="confirmar_senha_modal" name="confirmar_senha" required><div class="invalid-feedback small">As senhas não coincidem.</div></div><div id="senha_error" class="alert alert-danger alert-sm mt-3 p-2 small" style="display: none;"></div><div id="senha_sucesso" class="alert alert-success alert-sm mt-3 p-2 small" style="display: none;"></div><button type="submit" class="btn btn-primary w-100 mt-3">Redefinir Senha</button></form></div></div></div></div>
        </div>
    </div>
    <?php else: ?>
        <?php /* ----- Conteúdo Normal do Dashboard (Dentro do <main>) ----- */ ?>
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                 <div class="mb-2 mb-md-0">
                    <h1 class="h3 mb-0 fw-bold"><i class="fas fa-user-tie me-2 text-primary"></i>Painel do Gestor</h1>
                    <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0"><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Home</a></li><li class="breadcrumb-item active" aria-current="page">Dashboard</li></ol></nav>
                 </div>
                 <div class="btn-toolbar">
                    <a href="<?= BASE_URL ?>gestor/criar_auditoria.php" class="btn btn-primary rounded-pill shadow-sm px-3 me-2"><i class="fas fa-plus me-1"></i> Nova Auditoria</a>
                    <button class="btn btn-outline-secondary rounded-pill px-3 disabled" data-bs-toggle="tooltip" title="Relatórios gerais da empresa (em breve)"><i class="fas fa-chart-line me-1"></i> Relatórios</button>
                </div>
            </div>

            <?php /* Notificações */ ?>
            <?php if ($sucesso_msg): ?><div class="alert alert-success custom-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
            <?php if ($erro_msg): ?><div class="alert alert-danger custom-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($erro_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

            <?php /* Cards de Stats */ ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4 mb-4">
                 <?php $statCardsGestor = [ ['color' => 'primary', 'icon' => 'fa-hourglass-half', 'title' => 'Auditorias Ativas', 'value' => $statsGestor['total_ativas'] ?? 0, 'link' => BASE_URL . 'gestor/minhas_auditorias.php?status=ativas'], ['color' => 'warning', 'icon' => 'fa-clipboard-list', 'title' => 'Aguardando Revisão', 'value' => $statsGestor['para_revisao'] ?? 0, 'link' => BASE_URL . 'gestor/minhas_auditorias.php?status=revisar'], ['color' => 'danger', 'icon' => 'fa-times-circle', 'title' => 'NCs Abertas*', 'value' => $statsGestor['nao_conformidades_abertas'] ?? 0, 'link' => '#', 'tooltip' => 'Não Conformidades em auditorias não finalizadas'], ['color' => 'success', 'icon' => 'fa-users-cog', 'title' => 'Auditores Ativos', 'value' => $statsGestor['auditores_ativos'] ?? 0, 'link' => BASE_URL . 'gestor/gerenciar_auditores.php'] ]; ?>
                <?php foreach ($statCardsGestor as $card): ?>
                <div class="col">
                    <div class="card shadow-sm border-0 rounded-3 h-100 stat-card stat-card-<?= $card['color'] ?>" <?= isset($card['tooltip'])?'data-bs-toggle="tooltip" title="'.htmlspecialchars($card['tooltip']).'"':'' ?>>
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 stat-card-icon me-3"> <i class="fas <?= $card['icon'] ?> fa-2x"></i> </div>
                                <div class="flex-grow-1"> <div class="fs-1 fw-bolder"><?= $card['value'] ?></div> <div class="text-muted small text-uppercase fw-semibold"><?= $card['title'] ?></div> </div>
                            </div>
                        </div>
                        <a href="<?= $card['link'] ?>" class="stretched-link" aria-label="Ver <?= $card['title'] ?>"></a>
                    </div>
                </div>
                <?php endforeach; ?>
                 <div class="col-12 mt-1"><small class="text-muted fst-italic">*Contagem de Não Conformidades (exemplo).</small></div>
            </div>

            <?php /* Linha com Gráficos e Pendências */ ?>
            <div class="row g-4 mb-4">
                <div class="col-lg-7 order-lg-1">
                    <?php /* Sub-linha para os dois gráficos */ ?>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="card shadow-sm h-100 rounded-3 border-0"><div class="card-header bg-transparent border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold d-flex align-items-center"><i class="fas fa-tasks me-2 text-primary opacity-75"></i>Status das Auditorias</h6></div><div class="card-body d-flex align-items-center justify-content-center p-3"><?php if (!empty($statusChartData['labels']) && !empty($statusChartData['data'])): ?><canvas id="statusAuditoriaChart" style="max-height: 220px; width: 100%;"></canvas><?php else: ?><div class="text-center text-muted py-4"><i class="fas fa-info-circle fs-4 mb-2 d-block text-muted"></i> Nenhuma auditoria.</div><?php endif; ?></div></div>
                        </div>
                        <div class="col-md-6">
                             <div class="card shadow-sm h-100 rounded-3 border-0"><div class="card-header bg-transparent border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold d-flex align-items-center"><i class="fas fa-check-double me-2 text-success opacity-75"></i>Conformidade (%)</h6></div><div class="card-body d-flex align-items-center justify-content-center p-3"><?php $totalConformidadeItens = array_sum(array_values($conformidadeChartData['data'] ?? [])); ?><?php if ($totalConformidadeItens > 0): ?><canvas id="conformidadeChart" style="max-height: 220px; width: 100%;"></canvas><?php else: ?><div class="text-center text-muted py-4"><i class="fas fa-info-circle fs-4 mb-2 d-block text-muted"></i> Sem dados (Aud. Aprovadas).</div><?php endif; ?></div></div>
                        </div>
                    </div>
                    <?php /* Auditorias Pendentes */ ?>
                     <div class="card shadow-sm rounded-3 border-0">
                        <div class="card-header bg-warning-subtle border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold text-warning-emphasis d-flex align-items-center"><i class="fas fa-exclamation-triangle me-2"></i> Auditorias Aguardando Sua Revisão <?php if (count($auditoriasParaRevisar) > 0): ?><span class="badge bg-warning text-dark ms-2 rounded-pill"><?= count($auditoriasParaRevisar) ?></span><?php endif; ?></h6></div>
                        <div class="list-group list-group-flush dashboard-list-group" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($auditoriasParaRevisar)): ?><div class="list-group-item text-center text-muted small py-4"><i class="fas fa-check-circle fs-4 mb-2 d-block text-success"></i> Ótimo trabalho! Nenhuma pendência.</div>
                            <?php else: ?>
                                <?php foreach ($auditoriasParaRevisar as $auditoria): ?> <a href="<?= BASE_URL ?>gestor/revisar_auditoria.php?id=<?= $auditoria['id'] ?>" class="list-group-item list-group-item-action py-2 px-3 list-group-item-action-subtle"><div class="d-flex w-100 justify-content-between"><span class="mb-1 fw-semibold text-primary text-truncate"><?= htmlspecialchars($auditoria['titulo']) ?></span><small class="text-muted text-nowrap ps-2" title="Concluída em <?= $auditoria['data_conclusao_auditor'] ? (new DateTime($auditoria['data_conclusao_auditor']))->format('d/m/Y H:i:s') : 'N/D' ?>"><i class="far fa-clock me-1"></i> <?= $auditoria['data_conclusao_auditor'] ? formatarDataRelativa($auditoria['data_conclusao_auditor']) : 'N/D' ?></small></div><small class="text-muted d-flex align-items-center"><i class="fas fa-user-edit me-2 opacity-75"></i> Auditor: <?= htmlspecialchars($auditoria['nome_auditor'] ?? 'N/A') ?></small></a> <?php endforeach; ?>
                                <a href="<?= BASE_URL ?>gestor/minhas_auditorias.php?status=revisar" class="list-group-item text-center small py-2 bg-light text-primary fw-semibold">Ver Todas as Pendentes</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php /* Coluna Lateral Direita - Auditores */ ?>
                <div class="col-lg-5 order-lg-2">
                     <div class="card shadow-sm rounded-3 border-0 h-100">
                        <div class="card-header bg-transparent border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center"><h6 class="mb-0 fw-bold d-flex align-items-center"><i class="fas fa-users-cog me-2 text-info opacity-75"></i>Auditores Ativos</h6><a href="<?= BASE_URL ?>gestor/gerenciar_auditores.php" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" style="font-size: 0.75em;">Gerenciar</a></div>
                        <div class="list-group list-group-flush dashboard-list-group" style="max-height: 450px; overflow-y: auto;"> <?php /* Aumenta altura */ ?>
                            <?php if (empty($auditoresAtivos)): ?><div class="list-group-item text-center text-muted small py-3"><i class="fas fa-user-slash fs-4 mb-2 d-block text-muted"></i> Nenhum auditor ativo.</div>
                            <?php else: ?>
                                <?php foreach ($auditoresAtivos as $auditor): $auditorFoto = (!empty($auditor['foto']) && file_exists(__DIR__.'/../uploads/fotos/'.$auditor['foto'])) ? BASE_URL.'uploads/fotos/'.$auditor['foto'] : BASE_URL.'assets/img/default_profile.png'; ?>
                                <div class="list-group-item d-flex align-items-center py-2 px-3 list-group-item-action-subtle">
                                    <img src="<?= $auditorFoto ?>" alt="Foto" width="35" height="35" class="rounded-circle me-3 object-fit-cover shadow-sm">
                                    <div class="small lh-sm flex-grow-1"> <strong class="d-block fw-semibold text-truncate"><?= htmlspecialchars($auditor['nome']) ?></strong><span class="text-muted text-truncate d-block"><?= htmlspecialchars($auditor['email']) ?></span></div>
                                    <a href="<?= BASE_URL ?>gestor/editar_auditor.php?id=<?= $auditor['id'] ?>" class="btn btn-sm btn-light border py-0 px-1 ms-2" title="Editar Auditor"><i class="fas fa-pencil-alt fa-xs text-primary"></i></a>
                                </div>
                                <?php endforeach; ?>
                           <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div> <?php /* Fim da linha principal */ ?>
    <?php endif; /* Fim do else (não é primeiro acesso) */ ?>

<?php
// ---- Fechamento da tag <main> ----
// Como o getHeaderGestor abre a tag <main>, precisamos fechá-la aqui
// ANTES de chamar getFooterGestor.
if (!$primeiro_acesso) {
    echo '</main>';
}
echo getFooterGestor(); // Usa footer do gestor
?>

<?php /* ----- Scripts (Gráficos e Tooltips) ----- */ ?>
<?php if (!$primeiro_acesso): ?>
<script>
    // Função auxiliar para datas relativas (se não estiver global)
    if (typeof formatarDataRelativa === 'undefined') { function formatarDataRelativa(dIso){ const d=new Date(dIso); if(isNaN(d)) return dIso; const n=new Date(); const s=Math.floor((n-d)/1000); const m=Math.floor(s/60); const h=Math.floor(m/60); const day=Math.floor(h/24); const w=Math.floor(day/7); const mon=Math.floor(day/30); if(s<60)return'agora'; if(m<60)return`${m} min atrás`; if(h<24)return`${h}h atrás`; if(day<7)return`${day}d atrás`; if(w<4)return`${w} sem atrás`; if(mon<12)return`${mon} mês(es) atrás`; return d.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric'});} }

    // Função para inicializar os gráficos
    function initGestorCharts() {
        if (typeof Chart === 'undefined') { console.warn("Chart.js não carregado ainda, tentando novamente..."); setTimeout(initGestorCharts, 200); return; }
        console.log("[DEBUG] Iniciando inicialização dos gráficos...");

        const hexToRgba = (hex, alpha = 1) => { try { const bigint=parseInt(hex.slice(1),16); const r=(bigint>>16)&255; const g=(bigint>>8)&255; const b=bigint&255; return`rgba(${r},${g},${b},${alpha})`; } catch(e){ return `rgba(108, 117, 125, ${alpha})`; } };
        const destroyChart = (ctx) => { if (!ctx) return; let c = Chart.getChart(ctx.id); if (c) { console.log(`[DEBUG] Destruindo gráfico ${ctx.id}`); c.destroy(); } };

        // --- Gráfico Status ---
        const ctxS = document.getElementById('statusAuditoriaChart');
        const sL = <?= json_encode($statusChartData['labels'] ?? []) ?>;
        const sD = <?= json_encode($statusChartData['data'] ?? []) ?>;
        console.log("[DEBUG] Dados Status:", { labels: sL, data: sD });

        if(ctxS && Array.isArray(sL) && sL.length > 0 && Array.isArray(sD)) {
            const sC={'Planejada':'#6c757d','Em Andamento':'#0d6efd','Pausada':'#adb5bd','Concluída (Auditor)':'#ffc107','Em Revisão':'#fd7e14','Aprovada':'#198754','Rejeitada':'#dc3545','Cancelada':'#e9ecef'};
            const sBg = sL.map(l => sC[l] || '#cccccc');
            destroyChart(ctxS);
            try {
                new Chart(ctxS, { type: 'doughnut', data: { labels: sL, datasets: [{ data: sD, backgroundColor: sBg, borderColor: '#fff', borderWidth: 2, hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: true, position: 'right', labels: { padding: 10, boxWidth: 10, font: { size: 10 } } }, tooltip: { callbacks: { label: c => `${c.label}: ${c.parsed}` } } } } });
                console.log("[DEBUG] Gráfico de Status OK.");
            } catch(e) { console.error("[DEBUG] Erro ao criar Chart Status:", e); }
        } else { console.log("[DEBUG] Canvas Status ou dados inválidos."); }

        // --- Gráfico Conformidade ---
        const ctxC = document.getElementById('conformidadeChart');
        const cDRaw = <?= json_encode($conformidadeChartData['data'] ?? []) ?>; // Pegando o array associativo de data
        console.log("[DEBUG] Dados Conformidade (raw):", cDRaw);

        if(ctxC && typeof cDRaw === 'object' && cDRaw !== null && Object.keys(cDRaw).length > 0) {
            const cL = Object.keys(cDRaw);
            const cV = Object.values(cDRaw);
            const cT = cV.reduce((a,b)=>a+b,0);
             console.log("[DEBUG] Dados Conformidade (processado):", { labels: cL, data: cV, total: cT });

            if(cT > 0) {
                 const cCols={'Conforme':'#198754','Não Conforme':'#dc3545','Parcial':'#ffc107','N/A':'#adb5bd','Pendente':'#6c757d'};
                 const cBg = cL.map(l=>cCols[l]||'#ccc');
                 destroyChart(ctxC);
                 try {
                     new Chart(ctxC,{type:'pie',data:{labels:cL,datasets:[{data:cV,backgroundColor:cBg,borderColor:'#fff',borderWidth:2,hoverOffset:4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:true,position:'right',labels:{padding:10,boxWidth:12,font:{size:10}}},tooltip:{callbacks:{label:c=>{let p=cT>0?((c.parsed/cT)*100).toFixed(1)+'%':'0%';return`${c.label}: ${c.parsed} (${p})`;}}}}}});
                     console.log("[DEBUG] Gráfico de Conformidade OK.");
                 } catch(e) { console.error("[DEBUG] Erro ao criar Chart Conformidade:", e); }
            } else { console.log("[DEBUG] Sem dados válidos (total 0) para Conformidade."); }
        } else { console.log("[DEBUG] Canvas Conformidade ou dados inválidos."); }
    }

    // Inicialização após DOM pronto
    document.addEventListener('DOMContentLoaded', () => {
        console.log("[DEBUG] DOMContentLoaded - Iniciando scripts da dashboard.");
        initGestorCharts();

        // Formatar datas relativas (agora com seletor mais específico)
        document.querySelectorAll('.dashboard-log-list a small.text-muted[title^="Concluída em"], .dashboard-log-list div small.text-muted[title]').forEach(el => {
            try {
                let isoDate = el.title.includes('Concluída em') ? el.title.split('em ')[1] : el.title;
                 // Tenta buscar o ícone do relógio ou usa o próprio elemento como referência
                const timeIcon = el.querySelector('.far.fa-clock');
                const textNode = timeIcon ? timeIcon.nextSibling : el.firstChild; // Nó de texto após o ícone ou o primeiro nó

                if (textNode && textNode.nodeType === Node.TEXT_NODE && isoDate && isoDate !== 'N/D' && isoDate.length > 10) { // Verifica se é um nó de texto e data válida
                    textNode.nodeValue = ` ${formatarDataRelativa(isoDate)}`;
                } else if (!timeIcon) {
                     // Fallback se não encontrar relógio, tenta atualizar o próprio texto do small
                     el.textContent = ` ${formatarDataRelativa(isoDate)}`;
                }
            } catch (e) {
                console.error("Erro ao formatar data relativa para:", el, e);
            }
        });

        // Inicializa Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(el){ return new bootstrap.Tooltip(el); });
        console.log("[DEBUG] Tooltips inicializados:", tooltipList.length);

    }); // Fim DOMContentLoaded Listener
</script>
<?php endif; ?>