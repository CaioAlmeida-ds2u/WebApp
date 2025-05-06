<?php
// gestor/auditor/gerenciar_auditores.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_gestor.php';
require_once __DIR__ . '/../../includes/gestor_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$empresa_id = $_SESSION['usuario_empresa_id'];
$gestor_id = $_SESSION['usuario_id'];

$filtros_auditor = [];
$nome_filtro = $_GET['filtro_nome'] ?? '';
$status_filtro = (isset($_GET['filtro_status']) && $_GET['filtro_status'] !== '') ? (int)$_GET['filtro_status'] : '';
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina = 15;

if (!empty($nome_filtro)) { $filtros_auditor['nome'] = $nome_filtro; }
if ($status_filtro !== '') { $filtros_auditor['ativo'] = $status_filtro; }

// Função de busca (assumindo que getTodosAuditoresDaEmpresa ou getTodosAuditoresDaEmpresaPag existe e foi adaptada para paginação)
if (function_exists('getTodosAuditoresDaEmpresaPag')) {
    $auditores_data = getTodosAuditoresDaEmpresaPag($conexao, $empresa_id, $filtros_auditor, $pagina_atual, $itens_por_pagina);
} elseif (function_exists('getTodosAuditoresDaEmpresa')) {
    $auditores_data = getTodosAuditoresDaEmpresaPag($conexao, $empresa_id, $filtros_auditor, $pagina_atual, $itens_por_pagina);
    // trigger_error("Usando getTodosAuditoresDaEmpresa como fallback para getTodosAuditoresDaEmpresaPag.", E_USER_NOTICE);
} else {
    $auditores_data = ['auditores' => [], 'paginacao' => ['pagina_atual' => 1, 'total_paginas' => 0, 'total_itens' => 0, 'itens_por_pagina' => $itens_por_pagina]];
    trigger_error("Nenhuma função de busca de auditores paginada encontrada!", E_USER_ERROR);
}

$auditores = $auditores_data['auditores'] ?? [];
$paginacao = $auditores_data['paginacao'] ?? ['pagina_atual' => 1, 'total_paginas' => 0, 'total_itens' => 0, 'itens_por_pagina' => $itens_por_pagina];

$csrf_token = gerar_csrf_token();
$page_title = "Gerenciar Auditores";
echo getHeaderGestor($page_title);
?>
<!-- Campo CSRF global oculto para a página, usado pelos scripts AJAX -->
<input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

<style>
    #modalEquipesChecklist ul, #listaAuditoriasAuditor table { margin-bottom: 0; }
    #listaAuditoriasAuditor .table th, #listaAuditoriasAuditor .table td { padding: 0.5rem 0.4rem; vertical-align: middle;}
    #modalEquipesChecklist, #listaAuditoriasAuditor { max-height: 350px; overflow-y: auto; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-user-shield me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Auditores</li>
        </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>gestor/auditor/solicitar_auditor.php" class="btn btn-outline-primary rounded-pill px-3 action-button-secondary">
        <i class="fas fa-user-plus me-1"></i> Solicitar Novo Auditor
    </a>
</div>

<?php if ($msg_erro = obter_flash_message('erro')): ?> <div class="alert alert-danger gestor-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i> <?= $msg_erro ?> <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button></div> <?php endif; ?>
<?php if ($msg_sucesso = obter_flash_message('sucesso')): ?> <div class="alert alert-success gestor-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($msg_sucesso) ?> <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button></div> <?php endif; ?>

<div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
    <div class="card-header bg-light border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center flex-wrap">
        <h6 class="mb-0 fw-bold"><i class="fas fa-users me-2 text-primary opacity-75"></i>Auditores Vinculados (<?= htmlspecialchars($paginacao['total_itens'] ?? 0) ?>)</h6>
        <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex ms-auto small align-items-center gap-2" id="filtroAuditoresForm">
             <input type="search" name="filtro_nome" id="filtro_nome" class="form-control form-control-sm" placeholder="Nome..." value="<?= htmlspecialchars($nome_filtro) ?>" style="max-width: 170px;">
             <select name="filtro_status" id="filtro_status" class="form-select form-select-sm" style="max-width: 110px;">
                 <option value="">Status</option>
                 <option value="1" <?= ($status_filtro === 1) ? 'selected' : '' ?>>Ativos</option>
                 <option value="0" <?= ($status_filtro === 0) ? 'selected' : '' ?>>Inativos</option>
             </select>
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
            <?php if (!empty($nome_filtro) || $status_filtro !== ''): $queryParamsLimpar = []; ?>
                <a href="<?= BASE_URL ?>gestor/auditor/gerenciar_auditores.php<?= !empty($queryParamsLimpar) ? '?' . http_build_query($queryParamsLimpar) : '' ?>" class="btn btn-sm btn-outline-secondary ms-1" title="Limpar"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped table-sm mb-0 align-middle">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th style="width: 5%;" class="text-center">Foto</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Equipes (Ativas)</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 15%;">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabelaAuditoresBody">
                    <?php if (empty($auditores)): ?>
                        <tr><td colspan="6" class="text-center text-muted p-4"><i class="fas fa-info-circle d-block fs-4 mb-2 opacity-50"></i>Nenhum auditor encontrado<?= !empty($filtros_auditor) ? ' para os filtros aplicados' : '' ?>.</td></tr>
                    <?php else: ?>
                        <?php foreach ($auditores as $auditor): ?>
                            <tr id="auditor-row-<?= $auditor['id'] ?>">
                                <td class="text-center">
                                    <?php
                                        $fotoUrlParaDisplay = BASE_URL . 'assets/img/default_profile.png'; // Placeholder default
                                        if (!empty($auditor['foto'])) {
                                            $caminhoFotoFisico = $_SERVER['DOCUMENT_ROOT'] . '/webapp/uploads/fotos/' . ($auditor['foto'] ?? '');
                                            if (file_exists($caminhoFotoFisico)) {
                                                $fotoUrlParaDisplay = BASE_URL . 'uploads/fotos/' . rawurlencode($auditor['foto']);
                                            }
                                        }
                                    ?>
                                    <img src="<?= $fotoUrlParaDisplay ?>" alt="Foto de <?= htmlspecialchars($auditor['nome']) ?>" class="rounded-circle img-thumbnail" style="width: 35px; height: 35px; object-fit: cover;" loading="lazy">
                                </td>
                                <td class="fw-medium"><?= htmlspecialchars($auditor['nome']) ?></td>
                                <td class="small">
                                    <a href="mailto:<?= htmlspecialchars($auditor['email']) ?>" class="text-decoration-none text-secondary" title="Enviar email"><i class="fas fa-envelope fa-fw me-1 opacity-75"></i><?= htmlspecialchars($auditor['email']) ?></a>
                                </td>
                                <td class="small text-muted equipes-coluna">
                                    <?php if (!empty($auditor['equipes_associadas_ativas'])) { $nomes_equipes = explode(', ', $auditor['equipes_associadas_ativas']); echo '<div class="d-flex flex-wrap gap-1">'; foreach ($nomes_equipes as $nome_eq) { echo '<span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">' . htmlspecialchars($nome_eq) . '</span>'; } echo '</div>'; } else { echo '<em class="opacity-75">Nenhuma</em>'; } ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill px-2 py-1 <?= $auditor['ativo'] ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-danger-subtle text-danger-emphasis border border-danger-subtle' ?>" title="<?= $auditor['ativo'] ? 'Ativo' : 'Inativo' ?>">
                                        <?= $auditor['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td class="text-center action-buttons-table">
                                    <button type="button" class="btn btn-sm btn-outline-secondary action-btn me-1 btn-gerenciar-equipes-auditor"
                                            data-bs-toggle="modal" data-bs-target="#modalGerenciarEquipesDoAuditor"
                                            data-auditor-id="<?= $auditor['id'] ?>" data-auditor-nome="<?= htmlspecialchars($auditor['nome']) ?>"
                                            title="Gerenciar equipes deste auditor"> <i class="fas fa-users-cog fa-fw"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-info action-btn btn-ver-disponibilidade-auditor"
                                            data-bs-toggle="modal" data-bs-target="#modalVerDisponibilidadeAuditor"
                                            data-auditor-id="<?= $auditor['id'] ?>" data-auditor-nome="<?= htmlspecialchars($auditor['nome']) ?>"
                                            title="Ver auditorias e disponibilidade"> <i class="fas fa-calendar-check fa-fw"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (isset($paginacao) && $paginacao['total_paginas'] > 1):
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

<!-- MODAL: GERENCIAR EQUIPES DO AUDITOR -->
<div class="modal fade" id="modalGerenciarEquipesDoAuditor" tabindex="-1" aria-labelledby="modalGerenciarEquipesDoAuditorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-5" id="modalGerenciarEquipesDoAuditorLabel">Gerenciar Equipes para <span id="spanAuditorNomeEquipes" class="fw-bold">Auditor</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="loadingEquipesAuditor" class="text-center py-4" style="display: none;"> <div class="spinner-border text-primary"><span class="visually-hidden">Carregando...</span></div> <p class="mt-2 small">Carregando...</p> </div>
                <div id="errorEquipesAuditor" class="alert alert-danger py-2 small" style="display: none;"></div>
                <div id="contentEquipesAuditor" style="display: none;">
                    <p class="small text-muted">Marque as equipes (ativas) das quais <strong id="spanAuditorNomeEquipesRep">este auditor</strong> deve fazer parte.</p>
                    <form id="formModalGerenciarEquipes">
                        <input type="hidden" name="auditor_id_para_equipes" id="auditor_id_para_equipes" value="">
                        <div id="checklistEquipesAuditor" class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto;">
                            <p class="text-muted small">Aguardando dados...</p>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSalvarEquipesParaAuditor" disabled> <i class="fas fa-save me-1"></i> Salvar Alterações </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: VER DISPONIBILIDADE DO AUDITOR -->
<div class="modal fade" id="modalVerDisponibilidadeAuditor" tabindex="-1" aria-labelledby="modalVerDisponibilidadeAuditorLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-5" id="modalVerDisponibilidadeAuditorLabel">Auditorias de <span id="spanAuditorNomeDisp" class="fw-bold">Auditor</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="loadingDisponibilidadeAuditor" class="text-center py-4" style="display: none;"> <div class="spinner-border text-info"><span class="visually-hidden">Carregando...</span></div> <p class="mt-2 small">Buscando...</p> </div>
                <div id="errorDisponibilidadeAuditor" class="alert alert-warning py-2 small" style="display: none;"></div>
                <div id="contentDisponibilidadeAuditor" style="display: none;">
                    <p class="small text-muted fst-italic">Auditorias Planejadas ou Em Andamento (próximos 90 dias ou concluídas recentemente).</p>
                    <div id="listaAuditoriasDoAuditorNoModal"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- === JAVASCRIPT PARA OS MODAIS === -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Configurações globais
    const BASE_URL = "<?= BASE_URL ?>";
    const csrfToken = document.getElementById("csrf_token")?.value || "";

    // Configuração dos modais
    const modals = {
        equipes: {
            modal: document.getElementById("modalGerenciarEquipesDoAuditor"),
            elements: {
                nome: document.getElementById("spanAuditorNomeEquipes"),
                nomeRep: document.getElementById("spanAuditorNomeEquipesRep"),
                auditorId: document.getElementById("auditor_id_para_equipes"),
                checklist: document.getElementById("checklistEquipesAuditor"),
                loading: document.getElementById("loadingEquipesAuditor"),
                content: document.getElementById("contentEquipesAuditor"),
                error: document.getElementById("errorEquipesAuditor"),
                form: document.getElementById("formModalGerenciarEquipes"),
                saveBtn: document.getElementById("btnSalvarEquipesParaAuditor")
            },
            auditorId: null
        },
        disponibilidade: {
            modal: document.getElementById("modalVerDisponibilidadeAuditor"),
            elements: {
                nome: document.getElementById("spanAuditorNomeDisp"),
                loading: document.getElementById("loadingDisponibilidadeAuditor"),
                content: document.getElementById("contentDisponibilidadeAuditor"),
                error: document.getElementById("errorDisponibilidadeAuditor"),
                lista: document.getElementById("listaAuditoriasDoAuditorNoModal")
            },
            auditorId: null
        }
    };

    // Função utilitária para requisições AJAX
    async function fetchData(action, payload) {
        if (!csrfToken) throw new Error("Token CSRF ausente. Recarregue a página.");
        const formData = new FormData();
        formData.append("action", action);
        formData.append("csrf_token", csrfToken);
        Object.entries(payload).forEach(([key, value]) => formData.append(key, value));

        const response = await fetch(`${BASE_URL}gestor/ajax_handler_gestor.php`, {
            method: "POST",
            body: formData
        });

        if (!response.ok) throw new Error(`Erro HTTP: ${response.status}`);
        const data = await response.json();

        if (data.novo_csrf) {
            document.getElementById("csrf_token").value = data.novo_csrf;
        }

        if (!data.success) throw new Error(data.message || "Erro desconhecido.");
        return data;
    }

    // Função para exibir erros no modal
    function showError(errorDiv, message, loadingDiv = null, button = null, buttonText = null) {
        if (loadingDiv) loadingDiv.style.display = "none";
        errorDiv.textContent = message;
        errorDiv.style.display = "block";
        if (button && buttonText) {
            button.disabled = false;
            button.innerHTML = buttonText;
        }
    }

    // Função para exibir mensagens flash
    function showFlashMessage(message, type = "info") {
        const id = `flash-${Date.now()}`;
        const flash = document.createElement("div");
        flash.id = id;
        flash.className = `alert alert-${type} gestor-alert fade show alert-dismissible position-fixed top-0 start-50 translate-middle-x mt-3`;
        flash.style.zIndex = "2050";
        flash.innerHTML = `<div>${jsHtmlspecialchars(message)}</div><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>`;
        document.body.prepend(flash);

        const alert = new bootstrap.Alert(flash);
        setTimeout(() => alert.close(), 5000);
    }

    // Função para escapar HTML
    function jsHtmlspecialchars(str) {
        if (typeof str !== "string" && typeof str !== "number") return "";
        str = String(str);
        const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" };
        return str.replace(/[&<>"']/g, m => map[m]);
    }

    // Função para formatar datas
    function jsFormatDate(dateString) {
        if (!dateString) return "-";
        try {
            const date = new Date(`${dateString}T00:00:00`);
            if (isNaN(date.getTime())) return dateString;
            return `${date.getDate().toString().padStart(2, "0")}/${(date.getMonth() + 1).toString().padStart(2, "0")}/${date.getFullYear()}`;
        } catch {
            return dateString;
        }
    }

    // Configuração do modal de equipes
    if (modals.equipes.modal) {
        modals.equipes.modal.addEventListener("show.bs.modal", async ({ relatedTarget }) => {
            const { elements } = modals.equipes;
            modals.equipes.auditorId = relatedTarget.getAttribute("data-auditor-id");
            const auditorNome = relatedTarget.getAttribute("data-auditor-nome") || "Auditor";

            // Inicializar modal
            elements.nome.textContent = auditorNome;
            elements.nomeRep.textContent = auditorNome;
            elements.auditorId.value = modals.equipes.auditorId;
            elements.checklist.innerHTML = '<p class="text-muted small">Carregando...</p>';
            elements.error.style.display = "none";
            elements.content.style.display = "none";
            elements.loading.style.display = "block";
            elements.saveBtn.disabled = true;

            try {
                const data = await fetchData("get_equipes_para_auditor", {
                    auditor_id: modals.equipes.auditorId
                });

                let html = "";
                if (!data.equipes_empresa?.length) {
                    html = '<p class="text-muted small text-center py-2">Nenhuma equipe ativa encontrada.</p>';
                } else {
                    html = '<ul class="list-unstyled mb-0">';
                    data.equipes_empresa.forEach(equipe => {
                        const id = String(equipe.id);
                        const checked = (data.equipes_auditor || []).includes(id) ? "checked" : "";
                        const nome = jsHtmlspecialchars(equipe.nome);
                        html += `<li class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="equipes_auditor_modal[]" value="${id}" id="modal-equipe-${id}" ${checked}>
                            <label class="form-check-label small fw-normal" for="modal-equipe-${id}">${nome}</label>
                        </li>`;
                    });
                    html += "</ul>";
                }

                elements.checklist.innerHTML = html;
                elements.content.style.display = "block";
                elements.loading.style.display = "none";
                elements.saveBtn.disabled = false;
            } catch (error) {
                showError(elements.error, `Erro: ${error.message}`, elements.loading);
            }
        });

        modals.equipes.elements.saveBtn?.addEventListener("click", async () => {
            const { elements } = modals.equipes;
            elements.saveBtn.disabled = true;
            elements.saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
            elements.error.style.display = "none";

            const equipes = Array.from(elements.form.querySelectorAll("input[name='equipes_auditor_modal[]']:checked"))
                .map(cb => cb.value);

            try {
                await fetchData("salvar_associacoes_equipe_auditor", {
                    auditor_id: modals.equipes.auditorId,
                    equipes_ids: JSON.stringify(equipes)
                });

                showFlashMessage("Associações de equipes atualizadas!", "success");
                bootstrap.Modal.getInstance(modals.equipes.modal).hide();
                setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                showError(elements.error, `Erro ao salvar: ${error.message}`, null, elements.saveBtn, '<i class="fas fa-save me-1"></i>Salvar Alterações');
            }
        });
    }

    // Configuração do modal de disponibilidade
    if (modals.disponibilidade.modal) {
        modals.disponibilidade.modal.addEventListener("show.bs.modal", async ({ relatedTarget }) => {
            const { elements } = modals.disponibilidade;
            modals.disponibilidade.auditorId = relatedTarget.getAttribute("data-auditor-id");
            const auditorNome = relatedTarget.getAttribute("data-auditor-nome") || "Auditor";

            elements.nome.textContent = auditorNome;
            elements.lista.innerHTML = "";
            elements.error.style.display = "none";
            elements.content.style.display = "none";
            elements.loading.style.display = "block";

            try {
                const data = await fetchData("get_auditorias_do_auditor", {
                    auditor_id: modals.disponibilidade.auditorId
                });

                let html = "";
                if (!data.auditorias?.length) {
                    html = '<p class="text-muted text-center py-3">Nenhuma auditoria ativa encontrada.</p>';
                } else {
                    html = '<div class="table-responsive"><table class="table table-sm table-striped table-hover small align-middle">';
                    html += '<thead class="table-light"><tr><th>Auditoria</th><th>Seu Papel</th><th>Status</th><th>Início</th><th>Fim</th></tr></thead><tbody>';
                    data.auditorias.forEach(aud => {
                        const inicio = jsFormatDate(aud.data_inicio_planejada);
                        const fim = jsFormatDate(aud.data_fim_planejada);
                        const papel = aud.tipo_atribuicao === "equipe"
                            ? `Equipe ${aud.secao_modelo_nome ? `(Seção: ${jsHtmlspecialchars(aud.secao_modelo_nome)})` : "(Geral)"}`
                            : "Individual";
                        const link = `${BASE_URL}gestor/auditoria/detalhes_auditoria.php?id=${aud.id}`;

                        html += `<tr>
                            <td><a href="${link}" target="_blank" title="Ver detalhes">${jsHtmlspecialchars(aud.titulo)}</a></td>
                            <td>${jsHtmlspecialchars(papel)}</td>
                            <td><span class="badge bg-info-subtle text-info-emphasis">${jsHtmlspecialchars(aud.status)}</span></td>
                            <td>${inicio}</td>
                            <td>${fim}</td>
                        </tr>`;
                    });
                    html += "</tbody></table></div>";
                }

                elements.lista.innerHTML = html;
                elements.content.style.display = "block";
                elements.loading.style.display = "none";
            } catch (error) {
                showError(elements.error, `Erro: ${error.message}`, elements.loading);
            }
        });
    }

    // Inicializar tooltips do Bootstrap
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
});
</script>

<!-- ============================================================== -->
<!-- ======================== FIM JAVASCRIPT ===================== -->

<?php
echo getFooterGestor();
?>