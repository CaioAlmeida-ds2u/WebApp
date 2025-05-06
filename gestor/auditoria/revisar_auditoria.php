<?php
// gestor/auditoria/revisar_auditoria.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_gestor.php';
require_once __DIR__ . '/../../includes/gestor_functions.php';
// require_once __DIR__ . '/../../includes/funcoes_download.php'; // Para links de download

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

// --- Processamento do Formulário de Revisão (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão. Tente novamente.');
    } else {
        $auditoria_id_post = filter_input(INPUT_POST, 'auditoria_id', FILTER_VALIDATE_INT);
        $acao_final_post = trim(filter_input(INPUT_POST, 'acao_final', FILTER_SANITIZE_STRING));
        $observacoes_gerais_post = trim(filter_input(INPUT_POST, 'observacoes_gerais_gestor', FILTER_SANITIZE_SPECIAL_CHARS));
        $resultado_geral_post = trim(filter_input(INPUT_POST, 'resultado_geral_auditoria', FILTER_SANITIZE_STRING));
        $decisoes_itens_post = $_POST['itens'] ?? []; // Array de itens [item_id => [status_revisao_gestor, observacoes_gestor]]

        if ($auditoria_id_post == $auditoria_id_get) { // Garante que estamos salvando a auditoria correta
            $decisoes_formatadas = [];
            foreach ($decisoes_itens_post as $item_id_str => $dados_item) {
                $item_id_intval = (int)$item_id_str;
                if ($item_id_intval > 0) {
                    $decisoes_formatadas[$item_id_intval] = [
                        // O gestor define o 'status_revisao_gestor' e suas observações.
                        // Ele NÃO define diretamente o 'status_conformidade' aqui, mas sua avaliação
                        // e a decisão final ('Aprovada', 'Rejeitada') implicitamente validam ou não as do auditor.
                        'status_revisao_gestor' => $dados_item['status_revisao_gestor'] ?? 'Revisado',
                        'observacoes_gestor' => trim($dados_item['observacoes_gestor'] ?? '')
                    ];
                }
            }

            if (salvarRevisaoGestor($conexao, $auditoria_id_post, $gestor_id_sessao, $decisoes_formatadas, $acao_final_post, $observacoes_gerais_post, $resultado_geral_post)) {
                definir_flash_message('sucesso', 'Revisão da auditoria salva com sucesso.');
                header('Location: ' . BASE_URL . 'gestor/auditoria/minhas_auditorias.php'); // Ou de volta para detalhes
                exit;
            } else {
                // A função salvarRevisaoGestor pode ter lançado uma Exception específica que queremos mostrar
                $erro_revisao = $_SESSION['flash_messages']['erro_revisao'] ?? 'Erro desconhecido ao salvar a revisão. Tente novamente.';
                unset($_SESSION['flash_messages']['erro_revisao']); // Limpa se foi usada
                definir_flash_message('erro', $erro_revisao);
            }
        } else {
            definir_flash_message('erro', 'Inconsistência no ID da auditoria ao salvar.');
        }
    }
    // Recarrega a página de revisão se houve erro para manter os dados do formulário (se eles forem repopulados)
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}


// --- Busca Dados para Exibição ---
$auditoria_detalhes = getDetalhesCompletosAuditoria($conexao, $auditoria_id_get, $empresa_id_sessao);

if (!$auditoria_detalhes) {
    definir_flash_message('erro', 'Auditoria não encontrada ou não pertence à sua empresa.');
    header('Location: ' . BASE_URL . 'gestor/auditoria/minhas_auditorias.php'); exit;
}
// Verificar se a auditoria está em um status válido para revisão
if (!in_array($auditoria_detalhes['info']['status'], ['Concluída (Auditor)', 'Em Revisão', 'Rejeitada'])) {
    definir_flash_message('aviso', 'Esta auditoria não está atualmente disponível para revisão. Status atual: ' . htmlspecialchars($auditoria_detalhes['info']['status']));
    header('Location: ' . BASE_URL . 'gestor/auditoria/detalhes_auditoria.php?id=' . $auditoria_id_get); exit;
}

$info_geral = $auditoria_detalhes['info'];
$documentos_planejamento = $auditoria_detalhes['documentos_planejamento'] ?? [];
$itens_por_secao = $auditoria_detalhes['itens_por_secao'] ?? [];
// Planos de ação e histórico não são editados aqui, mas podem ser visualizados se desejado.

$page_title = "Revisar Auditoria: " . htmlspecialchars(mb_strimwidth($info_geral['titulo'], 0, 50, "..."));
echo getHeaderGestor($page_title);

// Incluir funções auxiliares de exibição se não estiverem no config.php
if (!function_exists('exibirBadgeStatusItem')) { /* ... definições das funções auxiliares ... */ }
if (!function_exists('exibirBadgeStatusAuditoria')) { /* ... */ }
if (!function_exists('formatarDataCompleta')) { /* ... */ }
// etc.

$csrf_token = gerar_csrf_token();
?>
<style>
    /* (Estilos CSS similares aos de detalhes_auditoria.php) */
    .info-label { font-weight: 600; color: #6c757d; }
    .observacoes-bloco { white-space: pre-wrap; font-size: 0.9em; background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 5px; border: 1px solid #dee2e6; max-height: 200px; overflow-y: auto;}
    .evidencia-item { display: inline-block; padding: 0.3rem 0.6rem; margin: 0.3rem 0.3rem 0.3rem 0; border: 1px solid #ced4da; border-radius: 0.25rem; background-color: #e9ecef; font-size: 0.85rem; line-height: 1.2; color: #495057; text-decoration: none;}
    .evidencia-item:hover { background-color: #dde4ea; color: #212529; }
    .evidencia-item i { margin-right: 0.4rem; color: #6c757d; }
    .accordion-button:not(.collapsed) { background-color: rgba(var(--bs-warning-rgb), 0.1); color: var(--bs-emphasis-color); }
    .form-revisao-item .form-select-sm, .form-revisao-item .form-control-sm {font-size: 0.8rem;}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title text-truncate" style="max-width: 70vw;" title="<?= htmlspecialchars($info_geral['titulo']) ?>">
            <i class="fas fa-edit me-2 text-warning"></i>Revisar Auditoria: <?= htmlspecialchars(mb_strimwidth($info_geral['titulo'], 0, 50, "...")) ?>
        </h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb"><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php">Minhas Auditorias</a></li><li class="breadcrumb-item active" aria-current="page">Revisão ID #<?= $info_geral['id'] ?></li></ol></nav>
    </div>
    <a href="<?= BASE_URL ?>gestor/auditoria/detalhes_auditoria.php?id=<?= $info_geral['id'] ?>" class="btn btn-outline-secondary rounded-pill px-3"><i class="fas fa-eye me-1"></i> Ver Detalhes (Modo Leitura)</a>
</div>

<?php if ($msg_sucesso = obter_flash_message('sucesso')): ?><div class="alert alert-success gestor-alert"><i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($msg_sucesso) ?></div><?php endif; ?>
<?php if ($msg_erro = obter_flash_message('erro')): ?><div class="alert alert-danger gestor-alert"><i class="fas fa-exclamation-triangle me-2"></i> <?= $msg_erro ?></div><?php endif; ?>

<form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $info_geral['id'] ?>" id="formRevisaoAuditoria">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="auditoria_id" value="<?= $info_geral['id'] ?>">

    <!-- Card de Status e Infos Gerais (pode ser simplificado) -->
    <div class="card shadow-sm mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary opacity-75"></i>Informações da Auditoria</h6>
            <?= exibirBadgeStatusAuditoria($info_geral['status']) ?>
        </div>
        <div class="card-body p-3 small">
             <p class="mb-1"><span class="info-label">Título:</span> <?= htmlspecialchars($info_geral['titulo']) ?></p>
             <p class="mb-0"><span class="info-label">Responsável (Auditor/Equipe):</span> <?= $info_geral['responsavel_display'] ?></p>
        </div>
    </div>


    <!-- Card Itens da Auditoria para Revisão -->
    <div class="card shadow-sm mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2">
            <h6 class="mb-0 fw-bold"><i class="fas fa-pencil-ruler me-2 text-primary opacity-75"></i>Revisão dos Itens da Auditoria</h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($itens_por_secao)): ?>
                <p class="text-center text-muted p-4">Nenhum item encontrado.</p>
            <?php else: ?>
                <div class="accordion accordion-flush" id="accordionRevisaoItens">
                    <?php $idx_rev_secao = 0; foreach ($itens_por_secao as $nome_secao => $itens_da_secao): $idx_rev_secao++; ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingRevisaoSecao<?= $idx_rev_secao ?>">
                                <button class="accordion-button <?= ($idx_rev_secao > 1 && count($itens_por_secao) > 1) ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRevisaoSecao<?= $idx_rev_secao ?>" aria-expanded="<?= ($idx_rev_secao === 1 || count($itens_por_secao) === 1) ? 'true' : 'false' ?>">
                                    <?= htmlspecialchars($nome_secao) ?>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill ms-auto me-2"><?= count($itens_da_secao) ?> itens</span>
                                </button>
                            </h2>
                            <div id="collapseRevisaoSecao<?= $idx_rev_secao ?>" class="accordion-collapse collapse <?= ($idx_rev_secao === 1 || count($itens_por_secao) === 1) ? 'show' : '' ?>" data-bs-parent="#accordionRevisaoItens">
                                <ul class="list-group list-group-flush">
                                <?php foreach ($itens_da_secao as $item): ?>
                                <li class="list-group-item py-3 px-lg-4 px-md-3 px-2 form-revisao-item">
                                    <div class="d-flex w-100 justify-content-between mb-1">
                                        <h6 class="mb-0 fw-semibold">
                                            <?php if(!empty($item['codigo_item'])): ?><span class="text-primary"><?= htmlspecialchars($item['codigo_item']) ?>:</span> <?php endif; ?>
                                            <?= htmlspecialchars($item['nome_item']) ?>
                                        </h6>
                                        <!-- Status do Auditor (para referência) -->
                                        <div>Auditor: <?= exibirBadgeStatusItem($item['status_conformidade']) ?></div>
                                    </div>
                                    <?php if(!empty($item['descricao_item'])): ?><p class="mb-2 small text-body-secondary lh-sm"><?= nl2br(htmlspecialchars($item['descricao_item'])) ?></p><?php endif; ?>

                                    <div class="row gx-lg-4 gx-md-3 gy-3 mt-1">
                                        <div class="col-lg-6">
                                            <strong class="small d-block mb-1 text-muted"><i class="fas fa-user-edit me-1"></i>Avaliação do Auditor</strong>
                                            <?php if (!empty($item['observacoes_auditor'])): ?><div class="observacoes-bloco bg-light mb-2"><?= nl2br(htmlspecialchars($item['observacoes_auditor'])) ?></div><?php else: ?><p class="small text-muted fst-italic mb-2">Auditor não deixou observações.</p><?php endif; ?>
                                            <?php if (!empty($item['evidencias'])): ?>
                                                <p class="small mb-1"><span class="info-label"><i class="fas fa-paperclip me-1"></i>Evidências do Auditor:</span></p>
                                                <div class="evidencias-list">
                                                <?php foreach($item['evidencias'] as $ev): $url_ev = BASE_URL . 'includes/download_handler.php?tipo=evid&id=' . $ev['id'] . '&csrf_token=' . $csrf_token_links; ?>
                                                <a href="<?= htmlspecialchars($url_ev) ?>" class="evidencia-item" target="_blank"> <i class="fas <?= getIconePorTipoMime($ev['tipo_mime']) ?> fa-fw"></i> <?= htmlspecialchars(mb_strimwidth($ev['nome_arquivo_original'],0,20,"...")) ?></a>
                                                <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-lg-6 border-start-lg">
                                            <strong class="small d-block mb-1 text-dark"><i class="fas fa-user-tie me-1 text-success"></i>Sua Avaliação (Gestor)</strong>
                                            <div class="mb-2">
                                                <label for="item_status_revisao_<?= $item['id'] ?>" class="form-label form-label-sm visually-hidden">Status da Revisão do Gestor para o Item</label>
                                                <select name="itens[<?= $item['id'] ?>][status_revisao_gestor]" id="item_status_revisao_<?= $item['id'] ?>" class="form-select form-select-sm">
                                                    <option value="Revisado" <?= ($item['status_revisao_gestor'] ?? 'Revisado') == 'Revisado' ? 'selected' : '' ?>>Item Revisado (Concordo com Auditor)</option>
                                                    <option value="Ação Solicitada" <?= ($item['status_revisao_gestor'] ?? '') == 'Ação Solicitada' ? 'selected' : '' ?>>Ação Necessária / Discordo</option>
                                                    <!-- Adicionar outros status de revisão do gestor se necessário -->
                                                </select>
                                            </div>
                                            <div class="mb-0">
                                                <label for="item_obs_gestor_<?= $item['id'] ?>" class="form-label form-label-sm visually-hidden">Observações do Gestor para o Item</label>
                                                <textarea name="itens[<?= $item['id'] ?>][observacoes_gestor]" id="item_obs_gestor_<?= $item['id'] ?>" class="form-control form-control-sm" rows="2" placeholder="Suas observações/justificativas para este item..."><?= htmlspecialchars($item['observacoes_gestor'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
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

    <!-- Card Ações Finais do Gestor -->
    <div class="card shadow-sm mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2">
            <h6 class="mb-0 fw-bold"><i class="fas fa-gavel me-2 text-primary opacity-75"></i>Decisão Final da Auditoria</h6>
        </div>
        <div class="card-body p-4">
            <div class="mb-3">
                <label for="resultado_geral_auditoria" class="form-label form-label-sm fw-semibold">Resultado Geral da Auditoria (se Aprovada):</label>
                <select name="resultado_geral_auditoria" id="resultado_geral_auditoria" class="form-select form-select-sm">
                    <option value="" <?= empty($info_geral['resultado_geral']) ? 'selected' : '' ?>>Não Aplicável / Pendente</option>
                    <option value="Conforme" <?= ($info_geral['resultado_geral'] ?? '') === 'Conforme' ? 'selected' : '' ?>>Conforme</option>
                    <option value="Parcialmente Conforme" <?= ($info_geral['resultado_geral'] ?? '') === 'Parcialmente Conforme' ? 'selected' : '' ?>>Parcialmente Conforme</option>
                    <option value="Não Conforme" <?= ($info_geral['resultado_geral'] ?? '') === 'Não Conforme' ? 'selected' : '' ?>>Não Conforme</option>
                </select>
                <small class="form-text text-muted">Defina o resultado geral apenas se for aprovar a auditoria.</small>
            </div>
            <div class="mb-3">
                <label for="observacoes_gerais_gestor" class="form-label form-label-sm fw-semibold">Observações Gerais / Justificativa Final:</label>
                <textarea name="observacoes_gerais_gestor" id="observacoes_gerais_gestor" class="form-control" rows="4" placeholder="Suas observações finais sobre a auditoria como um todo. Obrigatório se Rejeitar ou Solicitar Correções."><?= htmlspecialchars($info_geral['observacoes_gerais_gestor'] ?? '') ?></textarea>
                <div class="invalid-feedback" id="obsGeraisFeedback">Observações são obrigatórias para esta ação.</div>
            </div>
            <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                 <button type="submit" name="acao_final" value="salvar_parcial" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="fas fa-save me-1"></i> Salvar Revisão Parcial
                </button>
                 <!-- <button type="submit" name="acao_final" value="solicitar_correcao" class="btn btn-outline-warning rounded-pill px-3" onclick="return verificarObsGerais(this);">
                    <i class="fas fa-undo me-1"></i> Solicitar Correções ao Auditor
                </button> -->
                <button type="submit" name="acao_final" value="rejeitar" class="btn btn-danger rounded-pill px-3" onclick="return verificarObsGerais(this);">
                    <i class="fas fa-times-circle me-1"></i> Rejeitar Auditoria
                </button>
                <button type="submit" name="acao_final" value="aprovar" class="btn btn-success rounded-pill px-4 action-button-main">
                    <i class="fas fa-check-circle me-1"></i> Aprovar Auditoria
                </button>
            </div>
        </div>
    </div>
</form>

<script>
    function verificarObsGerais(buttonEl) {
        const obsGeraisTextarea = document.getElementById('observacoes_gerais_gestor');
        const obsGeraisFeedback = document.getElementById('obsGeraisFeedback');
        const acao = buttonEl.value;

        if ((acao === 'rejeitar' || acao === 'solicitar_correcao') && obsGeraisTextarea.value.trim() === '') {
            obsGeraisTextarea.classList.add('is-invalid');
            obsGeraisFeedback.style.display = 'block';
            obsGeraisTextarea.focus();
            alert('Observações/Justificativa são obrigatórias para Rejeitar ou Solicitar Correções.');
            return false; // Impede o submit
        }
        obsGeraisTextarea.classList.remove('is-invalid');
        obsGeraisFeedback.style.display = 'none';
        return true; // Permite o submit
    }

    // Script para inicializar tooltips (se você os usar)
    // document.addEventListener('DOMContentLoaded', function () {
    //   var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    //   var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    //     return new bootstrap.Tooltip(tooltipTriggerEl)
    //   })
    // });
</script>

<?php
echo getFooterGestor();
?>