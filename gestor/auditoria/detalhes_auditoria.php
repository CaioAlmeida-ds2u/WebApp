<?php
// gestor/auditoria/detalhes_auditoria.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_gestor.php';
require_once __DIR__ . '/../../includes/gestor_functions.php';
// Opcional: incluir se você tem um handler central para downloads e funções de formatação de arquivo
// require_once __DIR__ . '/../../includes/funcoes_download.php'; // Se tiver função para forçar download

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    header('Location: ' . BASE_URL . 'acesso_negado.php'); exit;
}

$empresa_id_sessao = (int)$_SESSION['usuario_empresa_id'];
$gestor_id_sessao = (int)$_SESSION['usuario_id'];

$auditoria_id_get = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$auditoria_id_get) {
    definir_flash_message('erro', 'ID de auditoria inválido.');
    header('Location: ' . BASE_URL . 'gestor/auditoria/minhas_auditorias.php'); exit;
}

// Buscar todos os detalhes da auditoria
$auditoria_detalhes = getDetalhesCompletosAuditoria($conexao, $auditoria_id_get, $empresa_id_sessao);

if (!$auditoria_detalhes) {
    definir_flash_message('erro', 'Auditoria não encontrada ou você não tem permissão para acessá-la.');
    header('Location: ' . BASE_URL . 'gestor/auditoria/minhas_auditorias.php'); exit;
}

$info_geral = $auditoria_detalhes['info'];
$documentos_planejamento = $auditoria_detalhes['documentos_planejamento'] ?? [];
$itens_por_secao = $auditoria_detalhes['itens_por_secao'] ?? [];
// Certificar que estas chaves existem, mesmo que com arrays vazios, vindo da função
$planos_acao_gerais = $auditoria_detalhes['planos_acao_gerais'] ?? [];
$historico_eventos = $auditoria_detalhes['historico_eventos'] ?? [];


$page_title = "Detalhes: " . htmlspecialchars(mb_strimwidth($info_geral['titulo'],0,50,"..."));
echo getHeaderGestor($page_title);

// Funções auxiliares (mantidas no arquivo como no seu original)
if (!function_exists('exibirBadgeStatusItem')) {
    function exibirBadgeStatusItem($status) {
        $badge_class = 'bg-secondary-subtle text-secondary-emphasis';
        switch ($status) {
            case 'Pendente': $badge_class = 'bg-light text-dark border'; break;
            case 'Conforme': $badge_class = 'bg-success-subtle text-success-emphasis border-success-subtle'; break;
            case 'Não Conforme': $badge_class = 'bg-danger-subtle text-danger-emphasis border-danger-subtle'; break;
            case 'Parcial': $badge_class = 'bg-warning-subtle text-warning-emphasis border-warning-subtle'; break;
            case 'N/A': $badge_class = 'bg-info-subtle text-info-emphasis border-info-subtle'; break;
        }
        return '<span class="badge rounded-pill ' . $badge_class . ' x-small fw-semibold">' . htmlspecialchars($status) . '</span>';
    }
}
if (!function_exists('exibirBadgeStatusAuditoria')) {
    function exibirBadgeStatusAuditoria($status) {
        $badgeClass = 'bg-dark'; 
        if ($status == 'Planejada') $badgeClass = 'bg-light text-dark border'; elseif ($status == 'Em Andamento') $badgeClass = 'bg-primary'; elseif ($status == 'Concluída (Auditor)') $badgeClass = 'bg-warning text-dark'; elseif ($status == 'Em Revisão') $badgeClass = 'bg-info text-dark'; elseif ($status == 'Aprovada') $badgeClass = 'bg-success'; elseif ($status == 'Rejeitada') $badgeClass = 'bg-danger'; elseif ($status == 'Cancelada') $badgeClass = 'bg-secondary';
        return '<span class="badge rounded-pill ' . $badgeClass . ' fs-6 px-3 py-1">' . htmlspecialchars($status) . '</span>';
    }
}
// Adicionar as outras funções auxiliares aqui se não estiverem em config.php
if (!function_exists('formatarDataCompleta')) { function formatarDataCompleta(?string $dataHoraIso, string $formato = 'd/m/Y H:i', string $default = 'N/D'): string { if (empty($dataHoraIso)) return $default; try { $dt = new DateTime($dataHoraIso); return $dt->format($formato); } catch (Exception $e) { return $default; } } }
if (!function_exists('formatarDataSimples')) { function formatarDataSimples(?string $dataIso, string $formato = 'd/m/Y', string $default = 'N/D'): string { if (empty($dataIso)) return $default; try { $dt = new DateTime($dataIso); return $dt->format($formato); } catch (Exception $e) { return $default; } } }
if (!function_exists('formatarTamanhoArquivo')) { function formatarTamanhoArquivo($bytes) { if ($bytes >= 1073741824) { return number_format($bytes / 1073741824, 2) . ' GB'; } elseif ($bytes >= 1048576) { return number_format($bytes / 1048576, 2) . ' MB'; } elseif ($bytes >= 1024) { return number_format($bytes / 1024, 2) . ' KB'; } elseif ($bytes > 1) { return $bytes . ' bytes'; } elseif ($bytes == 1) { return $bytes . ' byte'; } else { return '0 bytes'; } } }
if (!function_exists('getIconePorTipoMime')) { function getIconePorTipoMime($mime_type) { if (empty($mime_type)) return 'fa-file'; if (str_starts_with($mime_type, 'image/')) return 'fa-file-image text-success'; if (str_starts_with($mime_type, 'audio/')) return 'fa-file-audio text-info'; if (str_starts_with($mime_type, 'video/')) return 'fa-file-video text-purple'; switch (strtolower($mime_type)) { case 'application/pdf': return 'fa-file-pdf text-danger'; case 'application/msword': case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': return 'fa-file-word text-primary'; case 'application/vnd.ms-excel': case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': return 'fa-file-excel text-success'; case 'application/vnd.ms-powerpoint': case 'application/vnd.openxmlformats-officedocument.presentationml.presentation': return 'fa-file-powerpoint text-warning'; case 'application/zip': case 'application/x-rar-compressed': case 'application/x-7z-compressed': return 'fa-file-archive text-secondary'; case 'text/plain': return 'fa-file-alt text-muted'; case 'text/csv': return 'fa-file-csv text-info'; default: return 'fa-file text-muted'; } } }
if (!function_exists('exibirBadgeStatusPlanoAcao')) { function exibirBadgeStatusPlanoAcao($status) { $badge_class = 'bg-secondary'; switch ($status) { case 'Pendente': $badge_class = 'bg-warning text-dark'; break; case 'Em Andamento': $badge_class = 'bg-info text-dark'; break; case 'Concluída': $badge_class = 'bg-success'; break; case 'Cancelada': $badge_class = 'bg-dark'; break; case 'Atrasada': $badge_class = 'bg-danger'; break; case 'Verificada': $badge_class = 'bg-primary'; break; } return '<span class="badge rounded-pill ' . $badge_class . '">' . htmlspecialchars($status) . '</span>'; } }

$csrf_token_links = gerar_csrf_token(); // Para links de download GET

?>
<style>
    .info-label { font-weight: 600; color: #6c757d; }
    .info-value { color: #212529; }
    dl.row dt { padding-right: 0.5rem; }
    dl.row dd { padding-left: 0.5rem; }

    .evidencia-item {
        display: inline-block; padding: 0.3rem 0.6rem; margin: 0.3rem 0.3rem 0.3rem 0;
        border: 1px solid #ced4da; border-radius: 0.25rem; background-color: #e9ecef;
        font-size: 0.85rem; line-height: 1.2; color: #495057;
        text-decoration: none;
    }
    .evidencia-item:hover { background-color: #dde4ea; color: #212529; }
    .evidencia-item i { margin-right: 0.4rem; color: #6c757d; }
    .evidencias-list { margin-top: 0.5rem; }

    .plano-acao-card { font-size:0.875em; }
    .plano-acao-card .card-body { padding: 0.6rem 0.8rem; }

    .observacoes-bloco {
        white-space: pre-wrap; font-size: 0.9em; background-color: #f8f9fa;
        padding: 10px 12px; border-radius: 0.25rem; margin-top: 5px; border: 1px solid #dee2e6;
        max-height: 250px; overflow-y: auto; line-height: 1.5;
    }
    .bg-success-light { background-color: #d1e7dd !important; }
    .bg-danger-light { background-color: #f8d7da !important; }

    .historico-item { border-bottom: 1px dashed #e0e0e0; padding-bottom: 0.6rem; margin-bottom: 0.6rem; }
    .historico-item:last-child { border-bottom: 0; margin-bottom: 0; }
    .historico-data { font-weight: 500; color: #0d6efd; }
    .historico-usuario { color: #6c757d; }

    .accordion-button { font-size: 0.95rem; padding: 0.8rem 1rem; }
    .accordion-button:not(.collapsed) { background-color: rgba(var(--bs-primary-rgb), 0.05); color: var(--bs-emphasis-color); box-shadow: none;}
    .accordion-body { padding: 0 !important; }
    .list-group-item { padding: 0.8rem 1rem; }
    .accordion-item:first-of-type .accordion-button { border-top-left-radius: .25rem; border-top-right-radius: .25rem;}
    .accordion-item:last-of-type .accordion-button.collapsed { border-bottom-right-radius: .25rem; border-bottom-left-radius: .25rem;}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title text-truncate" style="max-width: 70vw;" title="<?= htmlspecialchars($info_geral['titulo']) ?>">
            <i class="fas fa-file-alt me-2 text-primary"></i>Detalhes: <?= htmlspecialchars(mb_strimwidth($info_geral['titulo'], 0, 60, "...")) ?>
        </h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php">Minhas Auditorias</a></li>
            <li class="breadcrumb-item active" aria-current="page">Detalhes ID #<?= $info_geral['id'] ?></li>
        </ol></nav>
    </div>
    <div class="btn-toolbar"> <!-- Removida classe 'page-actions- Ligar toolbar' se não definida -->
        <?php if (in_array($info_geral['status'], ['Concluída (Auditor)', 'Em Revisão', 'Rejeitada'])): ?>
            <a href="<?= BASE_URL ?>gestor/auditoria/revisar_auditoria.php?id=<?= $info_geral['id'] ?>" class="btn btn-warning rounded-pill px-3 me-2 shadow-sm"> <i class="fas fa-check-double me-1"></i> <?= ($info_geral['status'] === 'Rejeitada') ? 'Revisar Novamente' : 'Revisar' ?> </a>
        <?php endif; ?>
        <?php if (in_array($info_geral['status'], ['Planejada'])): ?>
            <a href="<?= BASE_URL ?>gestor/auditoria/editar_auditoria.php?id=<?= $info_geral['id'] ?>" class="btn btn-outline-secondary rounded-pill px-3 me-2"> <i class="fas fa-edit me-1"></i> Editar </a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-success rounded-pill px-3 me-2" onclick="alert('Exportar Relatório (PDF/Excel) - Em desenvolvimento.');"> <i class="fas fa-file-export me-1"></i> Exportar </button>
        <a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php" class="btn btn-secondary rounded-pill px-3"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
    </div>
</div>

<?php if ($s = obter_flash_message('sucesso')): ?><div class="alert alert-success gestor-alert"><i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($s) ?></div><?php endif; ?>
<?php if ($e = obter_flash_message('erro')): ?><div class="alert alert-danger gestor-alert"><i class="fas fa-exclamation-triangle me-2"></i> <?= $e ?></div><?php endif; ?>

<!-- Card 1: Informações Gerais -->
<div class="card shadow-sm mb-4 rounded-3 border-0">
    <div class="card-header bg-light border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary opacity-75"></i>Informações da Auditoria</h6>
        <?= exibirBadgeStatusAuditoria($info_geral['status']) ?>
    </div>
    <div class="card-body p-4">
        <dl class="row gy-2 mb-0">
            <dt class="col-sm-3 col-lg-2 info-label">ID:</dt> <dd class="col-sm-9 col-lg-4 info-value">#<?= $info_geral['id'] ?></dd>
            <dt class="col-sm-3 col-lg-2 info-label">Título:</dt> <dd class="col-sm-9 col-lg-10 info-value"><?= htmlspecialchars($info_geral['titulo']) ?></dd>
            <dt class="col-sm-3 col-lg-2 info-label">Responsável:</dt>
            <dd class="col-sm-9 col-lg-10 info-value">
                <?= $info_geral['responsavel_display'] ?>
                <?php if ($info_geral['equipe_id'] && !empty($info_geral['secoes_responsaveis_detalhes'])): ?>
                    <small class="d-block text-muted mt-1"><em>(Seções:
                        <?php $sr_parts = []; foreach($info_geral['secoes_responsaveis_detalhes'] as $sr) { $sr_parts[] = htmlspecialchars($sr['secao_modelo_nome']) . ": " . htmlspecialchars($sr['nome_auditor_secao']); } echo implode('; ', $sr_parts); ?>)
                    </em></small>
                 <?php endif; ?>
            </dd>
            <dt class="col-sm-3 col-lg-2 info-label">Gestor:</dt> <dd class="col-sm-9 col-lg-4 info-value"><?= htmlspecialchars($info_geral['nome_gestor_responsavel'] ?? 'N/D') ?></dd>
            <dt class="col-sm-3 col-lg-2 info-label">Modelo Base:</dt> <dd class="col-sm-9 col-lg-4 info-value"><?= htmlspecialchars($info_geral['nome_modelo'] ?? 'Auditoria Manual') ?></dd>
            <dt class="col-sm-3 col-lg-2 info-label">Início Plan.:</dt> <dd class="col-sm-9 col-lg-4 info-value"><?= formatarDataSimples($info_geral['data_inicio_planejada']) ?></dd>
            <dt class="col-sm-3 col-lg-2 info-label">Fim Plan.:</dt> <dd class="col-sm-9 col-lg-4 info-value"><?= formatarDataSimples($info_geral['data_fim_planejada']) ?></dd>
            <dt class="col-sm-3 col-lg-2 info-label">Início Real:</dt> <dd class="col-sm-9 col-lg-4 info-value"><?= formatarDataCompleta($info_geral['data_inicio_real'], 'd/m/Y H:i', 'Não iniciado') ?></dd>
            <dt class="col-sm-3 col-lg-2 info-label">Conclusão Aud.:</dt> <dd class="col-sm-9 col-lg-4 info-value"><?= formatarDataCompleta($info_geral['data_conclusao_auditor'], 'd/m/Y H:i', 'Pendente') ?></dd>
            <dt class="col-sm-3 col-lg-2 info-label">Decisão Gestor:</dt> <dd class="col-sm-9 col-lg-4 info-value"><?= formatarDataCompleta($info_geral['data_aprovacao_rejeicao_gestor'], 'd/m/Y H:i', 'Pendente') ?></dd>

            <?php if(!empty($info_geral['escopo'])): ?> <dt class="col-sm-12 col-lg-2 info-label mt-2">Escopo:</dt> <dd class="col-sm-12 col-lg-10 mt-lg-2 info-value"><div class="observacoes-bloco border-0 p-0 bg-transparent"><?= nl2br(htmlspecialchars($info_geral['escopo'])) ?></div></dd> <?php endif; ?>
            <?php if(!empty($info_geral['objetivo'])): ?> <dt class="col-sm-12 col-lg-2 info-label mt-2">Objetivo:</dt> <dd class="col-sm-12 col-lg-10 mt-lg-2 info-value"><div class="observacoes-bloco border-0 p-0 bg-transparent"><?= nl2br(htmlspecialchars($info_geral['objetivo'])) ?></div></dd> <?php endif; ?>
            <?php if(!empty($info_geral['instrucoes'])): ?> <dt class="col-sm-12 col-lg-2 info-label mt-2">Instruções:</dt> <dd class="col-sm-12 col-lg-10 mt-lg-2 info-value"><div class="observacoes-bloco border-0 p-0 bg-transparent"><?= nl2br(htmlspecialchars($info_geral['instrucoes'])) ?></div></dd> <?php endif; ?>
            <?php if(!empty($info_geral['observacoes_gerais_gestor']) && in_array($info_geral['status'], ['Aprovada', 'Rejeitada'])): ?> <dt class="col-sm-12 col-lg-2 info-label mt-2">Obs. Finais Gestor:</dt> <dd class="col-sm-12 col-lg-10 mt-lg-2 info-value"><div class="observacoes-bloco <?= $info_geral['status'] === 'Aprovada' ? 'border-success-subtle bg-success-light' : 'border-danger-subtle bg-danger-light' ?>"><?= nl2br(htmlspecialchars($info_geral['observacoes_gerais_gestor'])) ?></div></dd> <?php endif; ?>
        </dl>
    </div>
</div>

<!-- Card: Documentos de Planejamento -->
<?php if (!empty($documentos_planejamento)): ?>
<div class="card shadow-sm mb-4 rounded-3 border-0">
    <div class="card-header bg-light border-bottom pt-3 pb-2"> <h6 class="mb-0 fw-bold"><i class="fas fa-folder-open me-2 text-primary opacity-75"></i>Documentos de Planejamento</h6> </div>
    <div class="list-group list-group-flush p-2">
        <?php foreach ($documentos_planejamento as $doc):
            // Usar o handler de download seguro (VOCÊ PRECISA CRIAR ESTE HANDLER)
            $url_download_doc = BASE_URL . 'includes/download_handler.php?tipo=plan&id=' . $doc['id'] . '&csrf_token=' . $csrf_token_links;
        ?>
        <a href="<?= htmlspecialchars($url_download_doc) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 rounded mb-1 border">
            <div> <i class="fas <?= getIconePorTipoMime($doc['tipo_mime']) ?> fa-lg fa-fw me-2"></i> <?= htmlspecialchars($doc['nome_arquivo_original']) ?> <small class="text-muted ms-2">(<?= formatarTamanhoArquivo($doc['tamanho_bytes']) ?>)</small> </div>
            <small class="text-muted"><i class="fas fa-cloud-download-alt me-1"></i> Baixar (<?= formatarDataCompleta($doc['data_upload'],'d/m/y') ?>)</small>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>


<!-- Card: Itens da Auditoria -->
<div class="card shadow-sm mb-4 rounded-3 border-0">
    <div class="card-header bg-light border-bottom pt-3 pb-2"> <h6 class="mb-0 fw-bold"><i class="fas fa-tasks me-2 text-primary opacity-75"></i>Itens Avaliados</h6> </div>
    <div class="card-body p-0">
        <?php if (empty($itens_por_secao)): ?>
            <p class="text-center text-muted p-4">Nenhum item de auditoria encontrado.</p>
        <?php else: ?>
            <div class="accordion accordion-flush" id="accordionItensAuditoriaDetalhes">
                <?php $idx_secao = 0; foreach ($itens_por_secao as $nome_secao => $itens_da_secao): $idx_secao++;
                    $todos_conformes_na_secao = true; $algum_nao_conforme_na_secao = false; $algum_pendente_na_secao = false;
                    foreach ($itens_da_secao as $item_check) {
                        if ($item_check['status_conformidade'] !== 'Conforme' && $item_check['status_conformidade'] !== 'N/A') $todos_conformes_na_secao = false;
                        if (in_array($item_check['status_conformidade'], ['Não Conforme', 'Parcial'])) $algum_nao_conforme_na_secao = true;
                        if ($item_check['status_conformidade'] === 'Pendente') $algum_pendente_na_secao = true;
                    }
                    $accordion_button_class = "";
                    if ($algum_nao_conforme_na_secao) $accordion_button_class = "accordion-button-nc"; // text-danger
                    elseif ($algum_pendente_na_secao && $info_geral['status'] === 'Em Andamento') $accordion_button_class = "accordion-button-pendente"; // text-warning
                    elseif ($todos_conformes_na_secao && !empty($itens_da_secao)) $accordion_button_class = "accordion-button-conforme"; // text-success
                ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingSecaoDet_<?= $idx_secao ?>">
                            <button class="accordion-button <?= ($idx_secao > 1 && count($itens_por_secao) > 1 && !$algum_nao_conforme_na_secao) ? 'collapsed' : '' ?> <?= $accordion_button_class ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSecaoDet_<?= $idx_secao ?>" aria-expanded="<?= ($idx_secao === 1 || count($itens_por_secao) === 1 || $algum_nao_conforme_na_secao) ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($nome_secao) ?>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill ms-auto me-2"><?= count($itens_da_secao) ?> itens</span>
                            </button>
                        </h2>
                        <div id="collapseSecaoDet_<?= $idx_secao ?>" class="accordion-collapse collapse <?= ($idx_secao === 1 || count($itens_por_secao) === 1 || $algum_nao_conforme_na_secao) ? 'show' : '' ?>" data-bs-parent="#accordionItensAuditoriaDetalhes">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($itens_da_secao as $item_idx => $item): ?>
                                <li class="list-group-item py-3 px-lg-4 px-md-3 px-2">
                                    <div class="d-flex w-100 justify-content-between mb-1">
                                        <h6 class="mb-0 fw-semibold fs-6 text-dark">
                                            <?php if(!empty($item['codigo_item'])): ?><span class="text-primary me-1"><?= htmlspecialchars($item['codigo_item']) ?></span><?php endif; ?>
                                            <?= htmlspecialchars($item['nome_item']) ?>
                                        </h6>
                                        <?= exibirBadgeStatusItem($item['status_conformidade']) ?>
                                    </div>
                                    <?php if(!empty($item['descricao_item'])): ?><p class="mb-2 small text-body-secondary lh-sm"><?= nl2br(htmlspecialchars($item['descricao_item'])) ?></p><?php endif; ?>
                                    <?php if(!empty($item['guia_evidencia_item'])): ?><p class="small fst-italic text-info-emphasis bg-info-subtle p-2 rounded-1 mb-2"><i class="fas fa-info-circle fa-fw me-1"></i><strong>Guia:</strong> <?= nl2br(htmlspecialchars($item['guia_evidencia_item'])) ?></p><?php endif; ?>

                                    <div class="row gx-lg-4 gx-md-3 gy-3 mt-1 pt-2 border-top">
                                        <div class="col-lg-6">
                                            <strong class="small d-block mb-1 text-dark"><i class="fas fa-user-edit me-1 text-primary"></i>Avaliação do Auditor</strong>
                                            <?php if ($item['respondido_por_auditor_id']): ?> <p class="x-small text-muted mb-1">Por: <?= htmlspecialchars($item['nome_respondido_por_auditor'] ?? 'ID '.$item['respondido_por_auditor_id']) ?> em <?= formatarDataCompleta($item['data_resposta_auditor']) ?></p> <?php endif; ?>
                                            <?php if (!empty($item['observacoes_auditor'])): ?><div class="observacoes-bloco mb-2"><?= nl2br(htmlspecialchars($item['observacoes_auditor'])) ?></div><?php else: ?><p class="small text-muted fst-italic mb-2">Nenhuma observação do auditor.</p><?php endif; ?>
                                            <?php if (!empty($item['evidencias'])): ?>
                                                <p class="small mb-1 mt-2"><span class="info-label"><i class="fas fa-paperclip me-1"></i>Evidências:</span></p>
                                                <div class="evidencias-list">
                                                <?php foreach($item['evidencias'] as $ev): $url_ev = BASE_URL . 'includes/download_handler.php?tipo=evid&id=' . $ev['id'] . '&csrf_token=' . $csrf_token_links; ?>
                                                <a href="<?= htmlspecialchars($url_ev) ?>" class="evidencia-item" title="Baixar <?= htmlspecialchars($ev['nome_arquivo_original']) ?>"> <i class="fas <?= getIconePorTipoMime($ev['tipo_mime']) ?> fa-fw"></i> <?= htmlspecialchars(mb_strimwidth($ev['nome_arquivo_original'], 0, 25, "...")) ?> <small class="text-muted ms-1">(<?= formatarTamanhoArquivo($ev['tamanho_bytes']) ?>)</small> </a>
                                                <?php endforeach; ?>
                                                </div>
                                            <?php else: ?><p class="small text-muted fst-italic mb-2">Nenhuma evidência anexada.</p><?php endif; ?>
                                        </div>
                                        <div class="col-lg-6 border-start-lg">
                                            <strong class="small d-block mb-1 text-dark"><i class="fas fa-user-tie me-1 text-success"></i>Revisão do Gestor</strong>
                                            <?php if($item['revisado_por_gestor_id'] || !empty($item['observacoes_gestor'])): ?>
                                                <?php if ($item['revisado_por_gestor_id']): ?> <p class="x-small text-muted mb-1">Por: <?= htmlspecialchars($item['nome_revisado_por_gestor'] ?? 'ID '.$item['revisado_por_gestor_id']) ?> em <?= formatarDataCompleta($item['data_revisao_gestor']) ?></p> <?php endif; ?>
                                                <?php if (!empty($item['observacoes_gestor'])): ?><div class="observacoes-bloco mb-2"><?= nl2br(htmlspecialchars($item['observacoes_gestor'])) ?></div><?php else: ?><p class="small text-muted fst-italic mb-2">Nenhuma obs. adicional.</p><?php endif; ?>
                                                <p class="x-small text-muted mb-1"><span class="info-label">Decisão Gestor:</span> <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['status_revisao_gestor']) ?></span></p>
                                            <?php else: ?><p class="text-muted small fst-italic">Não revisado pelo gestor.</p><?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if(!empty($item['planos_acao'])): ?>
                                    <div class="mt-3 pt-3 border-top">
                                        <strong class="small d-block mb-2"><i class="fas fa-clipboard-check me-1 text-warning"></i>Planos de Ação Vinculados</strong>
                                        <?php foreach($item['planos_acao'] as $pa_idx => $pa): ?>
                                        <div class="card plano-acao-card shadow-none border-start-3 mb-2 <?= $pa['status_acao'] == 'Concluída' ? 'border-success' : ($pa['status_acao'] == 'Pendente' || $pa['status_acao'] == 'Em Andamento' ? 'border-warning' : 'border-secondary') ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start"> <p class="mb-1 small"><strong>#<?= $pa_idx + 1 ?>:</strong> <?= nl2br(htmlspecialchars($pa['descricao_acao'])) ?></p> <?= exibirBadgeStatusPlanoAcao($pa['status_acao']) ?> </div>
                                                <p class="mb-1 x-small"><span class="info-label">Resp.:</span> <?= htmlspecialchars($pa['nome_responsavel_plano'] ?: ($pa['responsavel_externo'] ?: 'N/D')) ?></p>
                                                <div class="d-flex justify-content-between x-small"> <span><span class="info-label">Prazo:</span> <?= formatarDataSimples($pa['prazo_conclusao']) ?></span> <?php if($pa['data_conclusao_real']): ?> <span><span class="info-label">Concluído:</span> <?= formatarDataCompleta($pa['data_conclusao_real'], 'd/m/Y') ?> </span> <?php endif; ?> </div>
                                                <?php if(!empty($pa['observacoes_execucao'])): ?><p class="mt-1 mb-0 x-small fst-italic"><span class="info-label">Obs. Exec.:</span> <?= nl2br(htmlspecialchars($pa['observacoes_execucao'])) ?></p><?php endif; ?>
                                                <?php if($pa['verificado_por_id'] && !empty($pa['observacoes_verificacao'])): ?><p class="mt-1 mb-0 x-small fst-italic"><span class="info-label">Obs. Verif.:</span> <?= nl2br(htmlspecialchars($pa['observacoes_verificacao'])) ?></p><?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Card: Planos de Ação Gerais (se implementado no backend) -->
<?php if (!empty($planos_acao_gerais)): ?>
<div class="card shadow-sm mb-4 rounded-3 border-0">
    <div class="card-header bg-light border-bottom pt-3 pb-2"> <h6 class="mb-0 fw-bold"><i class="fas fa-flag-checkered me-2 text-primary opacity-75"></i>Planos de Ação Gerais da Auditoria</h6> </div>
    <div class="list-group list-group-flush p-2">
        <?php foreach($planos_acao_gerais as $pa_geral): ?>
            <div class="list-group-item plano-acao-card shadow-none border-start-3 mb-2 p-0">
                 <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <p class="mb-1"><strong>Ação:</strong> <?= nl2br(htmlspecialchars($pa_geral['descricao_acao_geral'] ?? $pa_geral['descricao'])) ?></p>
                        <?= exibirBadgeStatusPlanoAcao($pa_geral['status_acao_geral'] ?? $pa_geral['status']) ?>
                    </div>
                    <p class="mb-1 small"><span class="info-label">Responsável:</span> <?= htmlspecialchars($pa_geral['nome_responsavel_geral'] ?? ($pa_geral['responsavel_externo_geral'] ?? 'N/D')) ?></p>
                    <p class="mb-0 small"><span class="info-label">Prazo:</span> <?= formatarDataSimples($pa_geral['prazo_conclusao_geral'] ?? $pa_geral['prazo']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php elseif (in_array($info_geral['status'], ['Aprovada', 'Rejeitada']) && ($info_geral['resultado_geral'] ?? '') === 'Não Conforme'): ?>
    <div class="card shadow-sm mb-4 rounded-3 border-0"><div class="card-body p-3 text-muted text-center small">Nenhum plano de ação geral foi cadastrado, embora o resultado geral tenha sido Não Conforme.</div></div>
<?php endif; ?>


<!-- Card: Histórico de Eventos (se implementado no backend) -->
<?php if (!empty($historico_eventos)): ?>
<div class="card shadow-sm mb-4 rounded-3 border-0">
    <div class="card-header bg-light border-bottom pt-3 pb-2"> <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary opacity-75"></i>Histórico de Eventos da Auditoria</h6> </div>
    <div class="card-body p-3">
        <ul class="list-unstyled small">
            <?php foreach($historico_eventos as $idx_evt => $evento): ?>
            <li class="historico-item <?= $idx_evt === 0 ? 'pt-0' : '' ?>"> <!-- Remove padding top do primeiro -->
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="historico-data"><i class="far fa-clock me-1"></i><?= formatarDataCompleta($evento['data_evento']) ?></span>
                    <span class="historico-usuario text-muted"><i class="fas fa-user-edit fa-xs me-1"></i><?= htmlspecialchars($evento['nome_usuario_evento'] ?? 'Sistema') ?></span>
                </div>
                <p class="mb-0 text-body-secondary"><?= htmlspecialchars($evento['descricao_evento']) ?></p>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php elseif(in_array($info_geral['status'], ['Em Andamento', 'Concluída (Auditor)', 'Em Revisão', 'Aprovada', 'Rejeitada', 'Cancelada'])): // Mostra msg se a auditoria já progrediu ?>
     <div class="card shadow-sm mb-4 rounded-3 border-0"><div class="card-body p-3 text-muted text-center small">Nenhum histórico de eventos detalhado disponível para esta auditoria.</div></div>
<?php endif; ?>


<?php
echo getFooterGestor();
?>