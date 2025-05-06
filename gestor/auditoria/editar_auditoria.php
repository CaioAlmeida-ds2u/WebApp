<?php
// gestor/auditoria/editar_auditoria.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_gestor.php';
require_once __DIR__ . '/../../includes/gestor_functions.php';
require_once __DIR__ . '/../../includes/funcoes_upload.php'; // Para uploads

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    header('Location: ' . BASE_URL . 'acesso_negado.php'); exit;
}

$empresa_id_sessao = (int)$_SESSION['usuario_empresa_id'];
$gestor_id_sessao = (int)$_SESSION['usuario_id'];

$auditoria_id_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$auditoria_id_edit) {
    definir_flash_message('erro', 'ID de auditoria inválido para edição.');
    header('Location: ' . BASE_URL . 'gestor/auditoria/minhas_auditorias.php'); exit;
}

// Buscar dados da auditoria para edição
$dados_atuais_auditoria = getDetalhesCompletosAuditoria($conexao, $auditoria_id_edit, $empresa_id_sessao);

if (!$dados_atuais_auditoria) {
    definir_flash_message('erro', 'Auditoria não encontrada ou acesso negado.');
    header('Location: ' . BASE_URL . 'gestor/auditoria/minhas_auditorias.php'); exit;
}

if ($dados_atuais_auditoria['info']['status'] !== 'Planejada') {
    definir_flash_message('aviso', 'Esta auditoria não pode mais ser editada (Status: ' . htmlspecialchars($dados_atuais_auditoria['info']['status']) . ')');
    header('Location: ' . BASE_URL . 'gestor/auditoria/detalhes_auditoria.php?id=' . $auditoria_id_edit); exit;
}

// --- Dados para Dropdowns ---
$modelos_dropdown = getModelosAtivos($conexao);
$auditores_individuais_dropdown = getAuditoresDaEmpresa($conexao, $empresa_id_sessao);
$equipes_dropdown = getEquipesDaEmpresa($conexao, $empresa_id_sessao);
$requisitos_por_categoria_todos = getRequisitosAtivosAgrupados($conexao);
$documentos_planejamento_atuais = $dados_atuais_auditoria['documentos_planejamento'] ?? [];


// --- Repopular Formulário com Dados Atuais ou POST ---
$titulo_form = $_POST['titulo'] ?? $dados_atuais_auditoria['info']['titulo'] ?? '';
$auditor_id_form = $_POST['auditor_id'] ?? $dados_atuais_auditoria['info']['auditor_responsavel_id'] ?? null;
$equipe_id_form = $_POST['equipe_id'] ?? $dados_atuais_auditoria['info']['equipe_id'] ?? null;
$escopo_form = $_POST['escopo'] ?? $dados_atuais_auditoria['info']['escopo'] ?? '';
$objetivo_form = $_POST['objetivo'] ?? $dados_atuais_auditoria['info']['objetivo'] ?? '';
$instrucoes_form = $_POST['instrucoes'] ?? $dados_atuais_auditoria['info']['instrucoes'] ?? '';
$data_inicio_form = $_POST['data_inicio'] ?? $dados_atuais_auditoria['info']['data_inicio_planejada'] ?? '';
$data_fim_form = $_POST['data_fim'] ?? $dados_atuais_auditoria['info']['data_fim_planejada'] ?? '';
$modelo_id_form = $_POST['modelo_id'] ?? $dados_atuais_auditoria['info']['modelo_id'] ?? null;
$requisitos_selecionados_form = $_POST['requisitos_selecionados'] ?? $dados_atuais_auditoria['info']['requisitos_selecionados_ids'] ?? [];
$secao_responsaveis_form = $_POST['secao_responsaveis'] ?? $dados_atuais_auditoria['info']['secao_responsaveis_mapa'] ?? [];


// Determinar modo de atribuição inicial
$modo_atribuicao_form = 'auditor';
if (!empty($equipe_id_form)) { $modo_atribuicao_form = 'equipe'; }
elseif (!empty($auditor_id_form)) { $modo_atribuicao_form = 'auditor'; }
$modo_atribuicao_form = $_POST['modo_atribuicao'] ?? $modo_atribuicao_form; // Prioriza POST

// Determinar modo de criação inicial (para auditor individual)
$modo_criacao_form = 'modelo';
if ($modo_atribuicao_form === 'auditor') {
    if (!empty($modelo_id_form)) $modo_criacao_form = 'modelo';
    elseif (!empty($requisitos_selecionados_form)) $modo_criacao_form = 'manual';
} elseif ($modo_atribuicao_form === 'equipe') {
    $modo_criacao_form = 'modelo';
}
if ($modo_atribuicao_form === 'auditor' && isset($_POST['modo_criacao'])) $modo_criacao_form = $_POST['modo_criacao'];


$csrf_token = gerar_csrf_token(); // Novo token para o formulário de edição
$erro_msg_flash = obter_flash_message('erro');


// --- Processamento do Formulário de Edição (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
        header('Location: ' . $_SERVER['REQUEST_URI']); exit;
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenera para próxima

    $errors_update = [];
    // Coletar e validar dados (Lógica similar à de criar_auditoria.php)
    $titulo_val = trim(filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_STRING)) ?: '';
    if (empty($titulo_val)) $errors_update[] = "Título obrigatório.";

    $escopo_val = trim(filter_input(INPUT_POST, 'escopo', FILTER_SANITIZE_STRING));
    $objetivo_val = trim(filter_input(INPUT_POST, 'objetivo', FILTER_SANITIZE_STRING));
    $instrucoes_val = trim(filter_input(INPUT_POST, 'instrucoes', FILTER_SANITIZE_STRING));
    $data_inicio_val = filter_input(INPUT_POST, 'data_inicio', FILTER_SANITIZE_STRING);
    $data_fim_val = filter_input(INPUT_POST, 'data_fim', FILTER_SANITIZE_STRING);

    if (!empty($data_inicio_val) && !DateTime::createFromFormat('Y-m-d', $data_inicio_val)) $errors_update[] = "Data de início inválida.";
    if (!empty($data_fim_val) && !DateTime::createFromFormat('Y-m-d', $data_fim_val)) $errors_update[] = "Data fim inválida.";
    if (!empty($data_inicio_val) && !empty($data_fim_val)) {
        $inicio_dt_val = DateTime::createFromFormat('Y-m-d', $data_inicio_val);
        $fim_dt_val = DateTime::createFromFormat('Y-m-d', $data_fim_val);
        if ($inicio_dt_val && $fim_dt_val && $fim_dt_val < $inicio_dt_val) $errors_update[] = "Data fim anterior à data de início.";
    }

    $modo_atribuicao_val_post = filter_input(INPUT_POST, 'modo_atribuicao', FILTER_SANITIZE_STRING);
    $auditor_id_upd_val = null; $equipe_id_upd_val = null; $modelo_id_upd_val = null;
    $requisitos_para_update_val = []; $secao_responsaveis_upd_val = [];

    if ($modo_atribuicao_val_post === 'auditor') {
        $auditor_id_upd_val = filter_input(INPUT_POST, 'auditor_id', FILTER_VALIDATE_INT) ?: null;
        if (empty($auditor_id_upd_val)) $errors_update[] = "Auditor Individual é obrigatório.";
        $modo_criacao_val_post = filter_input(INPUT_POST, 'modo_criacao', FILTER_SANITIZE_STRING);
        if (empty($modo_criacao_val_post)) $errors_update[] = "Selecione a origem dos itens para o auditor.";
        elseif ($modo_criacao_val_post === 'modelo') {
            $modelo_id_upd_val = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT) ?: null;
            if (empty($modelo_id_upd_val)) $errors_update[] = "Modelo Base é obrigatório para este modo.";
        } elseif ($modo_criacao_val_post === 'manual') {
            $req_sel_post = isset($_POST['requisitos_selecionados']) && is_array($_POST['requisitos_selecionados']) ? array_filter(array_map('intval',$_POST['requisitos_selecionados']),fn($id)=>$id>0) : [];
            if(empty($req_sel_post)) $errors_update[] = "Selecione ao menos um requisito para o modo manual."; else $requisitos_para_update_val = $req_sel_post;
            $modelo_id_upd_val = null;
        }
        $equipe_id_upd_val = null; // Garante que equipe é nula
    } elseif ($modo_atribuicao_val_post === 'equipe') {
        $equipe_id_upd_val = filter_input(INPUT_POST, 'equipe_id', FILTER_VALIDATE_INT) ?: null;
        if (empty($equipe_id_upd_val)) $errors_update[] = "Equipe é obrigatória.";
        $modelo_id_upd_val = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT) ?: null;
        if (empty($modelo_id_upd_val)) $errors_update[] = "Modelo Base é obrigatório para equipes.";
        if (isset($_POST['secao_responsaveis']) && is_array($_POST['secao_responsaveis'])) {
            foreach($_POST['secao_responsaveis'] as $sn_post => $aid_post) { if(!empty(trim($sn_post)) && filter_var($aid_post,FILTER_VALIDATE_INT)) $secao_responsaveis_upd_val[trim($sn_post)]=(int)$aid_post; }
        }
        $auditor_id_upd_val = null; // Garante que auditor individual é nulo
    } else { $errors_update[] = "Selecione o modo de atribuição (Individual ou Equipe)."; }

    $documentos_a_remover_ids_post = isset($_POST['remover_documento']) && is_array($_POST['remover_documento']) ? array_map('intval', $_POST['remover_documento']) : [];
    $novos_arquivos_processados = [];
    if (isset($_FILES['novos_documentos']) && !empty(array_filter($_FILES['novos_documentos']['name']))) {
        $temp_upload_dir_edit = rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/temp/' . session_id() . '_edit_' . time() . '/';
        $novos_docs_info = processarDocumentUploads($_FILES['novos_documentos'], $temp_upload_dir_edit);
        if (!$novos_docs_info['success']) { $errors_update[] = "Falha no upload de novos documentos: " . ($novos_docs_info['message'] ?? 'Erro desconhecido.'); }
        $novos_arquivos_processados = $novos_docs_info['files'] ?? [];
    }


    if (empty($errors_update)) {
        $dadosParaAtualizar = [
            'titulo' => $titulo_val, 'escopo' => $escopo_val, 'objetivo' => $objetivo_val, // Use as variáveis coletadas
            'instrucoes' => $instrucoes_val, 'data_inicio' => $data_inicio_val, 'data_fim' => $data_fim_val,
            'auditor_individual_id' => $auditor_id_upd_val, 'equipe_id' => $equipe_id_upd_val,
            'modelo_id' => $modelo_id_upd_val, 'requisitos_selecionados' => $requisitos_para_update_val,
            'secao_responsaveis' => $secao_responsaveis_upd_val
        ];

        $resultadoUpdate = atualizarAuditoriaPlanejada(
            $conexao, $auditoria_id_edit, $dadosParaAtualizar, $gestor_id_sessao,
            $novos_arquivos_processados, $documentos_a_remover_ids_post
        );

        if ($resultadoUpdate === true) {
            definir_flash_message('sucesso', 'Auditoria ID #' . $auditoria_id_edit . ' atualizada com sucesso!');
            header('Location: ' . BASE_URL . 'gestor/auditoria/detalhes_auditoria.php?id=' . $auditoria_id_edit);
            exit;
        } else {
            definir_flash_message('erro', is_string($resultadoUpdate) ? $resultadoUpdate : 'Erro desconhecido ao atualizar a auditoria.');
        }
    } else {
        definir_flash_message('erro', "<strong>Não foi possível atualizar:</strong><ul><li>" . implode("</li><li>", $errors_update) . "</li></ul>");
        // Limpeza de NOVOS arquivos temporários se a validação geral do form falhou
        if (!empty($novos_arquivos_processados)) { foreach ($novos_arquivos_processados as $doc_temp) { if (isset($doc_temp['caminho_temp']) && file_exists($doc_temp['caminho_temp'])) { @unlink($doc_temp['caminho_temp']); } } if(isset($temp_upload_dir_edit) && is_dir($temp_upload_dir_edit) && !(new FilesystemIterator($temp_upload_dir_edit))->valid()){ @rmdir($temp_upload_dir_edit); }}
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
}


$page_title = "Editar Auditoria: " . htmlspecialchars($titulo_form); // Usar $titulo_form aqui
echo getHeaderGestor($page_title);
?>
<!-- Adicionar input CSRF oculto global para o JS pegar (se ainda não houver um) -->
<input type="hidden" id="csrf_token_page_input_edit" value="<?= htmlspecialchars($csrf_token) ?>">

<style> /* Pode reutilizar o CSS de criar_auditoria.php se for o mesmo */
    #listaSecoesParaAtribuicao ul { max-height: 300px; overflow-y: auto; padding-right: 10px; border: 1px solid #dee2e6; padding: 10px; border-radius: .25rem; background-color: #fff;}
    .sticky-legend { position: sticky; top: -1px; z-index: 1; background-color: #f8f9fa !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-edit me-2 text-primary"></i>Editar Auditoria #<?= $auditoria_id_edit ?></h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php">Auditorias</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/auditoria/detalhes_auditoria.php?id=<?= $auditoria_id_edit ?>">Detalhes</a></li>
            <li class="breadcrumb-item active" aria-current="page">Editar Planejamento</li>
        </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>gestor/auditoria/detalhes_auditoria.php?id=<?= $auditoria_id_edit ?>" class="btn btn-outline-secondary rounded-pill px-3"><i class="fas fa-arrow-left me-1"></i> Voltar para Detalhes</a>
</div>

<?php if ($erro_msg_flash): ?> <div class="alert alert-danger gestor-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><div><?= $erro_msg_flash ?></div> <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert"></button></div> <?php endif; ?>
<?php if ($msg_sucesso = obter_flash_message('sucesso')): ?> <div class="alert alert-success gestor-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i><div><?= htmlspecialchars($msg_sucesso) ?></div> <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert"></button></div> <?php endif; ?>


<form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $auditoria_id_edit ?>" id="formEditarAuditoria" class="needs-validation" novalidate enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="auditoria_id" value="<?= $auditoria_id_edit ?>">

    <!-- CARD 1: INFORMAÇÕES GERAIS E PLANEJAMENTO -->
    <div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2"> <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary opacity-75"></i>Informações Gerais e Planejamento</h6> </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-12"> <label for="titulo" class="form-label form-label-sm fw-semibold">Título <span class="text-danger">*</span></label> <input type="text" class="form-control form-control-sm" id="titulo" name="titulo" value="<?= htmlspecialchars($titulo_form) ?>" required> <div class="invalid-feedback">Título.</div> </div>
                <div class="col-md-6"> <label for="data_inicio" class="form-label form-label-sm fw-semibold">Início Planejado</label> <input type="date" class="form-control form-control-sm" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($data_inicio_form) ?>"> <div class="invalid-feedback">Data.</div> </div>
                <div class="col-md-6"> <label for="data_fim" class="form-label form-label-sm fw-semibold">Fim Planejado</label> <input type="date" class="form-control form-control-sm" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim_form) ?>"> <div class="invalid-feedback" id="dataFimFeedback">Data.</div> </div>
                <div class="col-12"> <label for="escopo" class="form-label form-label-sm fw-semibold">Escopo</label> <textarea class="form-control form-control-sm" id="escopo" name="escopo" rows="2"><?= htmlspecialchars($escopo_form) ?></textarea> </div>
                <div class="col-12"> <label for="objetivo" class="form-label form-label-sm fw-semibold">Objetivo</label> <textarea class="form-control form-control-sm" id="objetivo" name="objetivo" rows="2"><?= htmlspecialchars($objetivo_form) ?></textarea> </div>
                <div class="col-12"> <label for="instrucoes" class="form-label form-label-sm fw-semibold">Instruções</label> <textarea class="form-control form-control-sm" id="instrucoes" name="instrucoes" rows="3"><?= htmlspecialchars($instrucoes_form) ?></textarea> </div>

                <div class="col-12 pt-3 mt-3 border-top">
                    <label class="form-label form-label-sm fw-semibold mb-2">Documentos de Planejamento Anexados</label>
                    <?php if (!empty($documentos_planejamento_atuais)): ?>
                        <p class="small text-muted mb-1">Marque para remover os documentos atuais:</p>
                        <div class="list-group list-group-flush list-group-sm mb-3 border rounded p-2" style="max-height: 150px; overflow-y:auto;">
                        <?php foreach($documentos_planejamento_atuais as $doc_atual): ?>
                            <div class="form-check list-group-item-action px-2 py-1">
                                <input class="form-check-input" type="checkbox" name="remover_documento[]" value="<?= $doc_atual['id'] ?>" id="remover_doc_<?= $doc_atual['id'] ?>">
                                <label class="form-check-label small w-100" for="remover_doc_<?= $doc_atual['id'] ?>">
                                    <i class="fas <?= getIconePorTipoMime($doc_atual['tipo_mime']) ?> fa-fw me-1 text-secondary"></i>
                                    <?= htmlspecialchars($doc_atual['nome_arquivo_original']) ?>
                                    <span class="text-muted x-small ms-1">(<?= formatarTamanhoArquivo($doc_atual['tamanho_bytes']) ?>)</span>
                                    <a href="<?= BASE_URL . 'includes/download_handler.php?tipo=plan&id=' . $doc_atual['id'] . '&csrf_token=' . $csrf_token ?>" target="_blank" class="float-end text-decoration-none x-small" title="Baixar este documento"><i class="fas fa-download"></i></a>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="small text-muted fst-italic">Nenhum documento anexado.</p>
                    <?php endif; ?>
                    <label for="novos_documentos" class="form-label form-label-sm fw-semibold mb-1 mt-2">Adicionar Novos Documentos</label>
                    <input type="file" class="form-control form-control-sm" id="novos_documentos" name="novos_documentos[]" multiple accept=".pdf,.xlsx,.xls,.docx,.doc,.jpg,.jpeg,.png,.gif">
                    <small class="form-text text-muted">Tamanho máx: <?= MAX_UPLOAD_SIZE_MB ?>MB/arquivo.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- CARD 2: ATRIBUIÇÃO E ITENS (Mesmo HTML de criar_auditoria.php, mas valores vêm de $..._form) -->
    <div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2"> <h6 class="mb-0 fw-bold"><i class="fas fa-sitemap me-2 text-primary opacity-75"></i>Atribuição e Definição dos Itens</h6> </div>
        <div class="card-body p-4">
            <fieldset class="mb-4 pb-3 border-bottom">
                 <legend class="form-label form-label fw-semibold mb-2 d-block"><span class="badge bg-primary-subtle text-primary-emphasis rounded-pill me-2">1</span>Atribuir para:</legend>
                 <div class="d-flex flex-wrap align-items-center">
                     <div class="form-check form-check-inline me-3 mb-2 mb-md-0"> <input class="form-check-input" type="radio" name="modo_atribuicao" id="modoAtribuirAuditor" value="auditor" <?= ($modo_atribuicao_form === 'auditor') ? 'checked' : '' ?> required> <label class="form-check-label" for="modoAtribuirAuditor"><i class="fas fa-user text-secondary me-1"></i>Auditor Individual</label> </div>
                     <div class="form-check form-check-inline"> <input class="form-check-input" type="radio" name="modo_atribuicao" id="modoAtribuirEquipe" value="equipe" <?= ($modo_atribuicao_form === 'equipe') ? 'checked' : '' ?> required> <label class="form-check-label" for="modoAtribuirEquipe"><i class="fas fa-users text-secondary me-1"></i>Equipe</label> </div>
                 </div>
                 <div class="invalid-feedback d-block" id="atribuicaoError" style="display: none;">Selecione atribuição.</div>
            </fieldset>

            <fieldset class="mb-3">
                <legend class="form-label fw-semibold mb-3 d-block"><span class="badge bg-primary-subtle text-primary-emphasis rounded-pill me-2">2</span>Detalhes e Itens:</legend>
                <div class="ps-lg-1">
                    <div class="p-3 border rounded bg-light mb-4" id="blocoAuditorIndividualWrapper" style="display: <?= ($modo_atribuicao_form === 'auditor') ? 'block' : 'none' ?>;">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label for="auditor_id" class="form-label form-label-sm fw-semibold">Auditor <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="auditor_id" name="auditor_id">
                                    <option value="">-- Selecione --</option>
                                    <?php foreach ($auditores_individuais_dropdown as $aud): ?><option value="<?= $aud['id'] ?>" <?= ($auditor_id_form == $aud['id']) ? 'selected' : '' ?>><?= htmlspecialchars($aud['nome']) ?></option><?php endforeach; ?>
                                </select> <div class="invalid-feedback">Auditor.</div>
                            </div>
                            <div class="col-lg-6 border-start-lg" id="blocoOrigemRequisitos" style="display: <?= ($modo_atribuicao_form === 'auditor') ? 'block' : 'none' ?>;">
                                <fieldset><legend class="form-label form-label-sm fw-semibold mb-2">Origem <span class="text-danger">*</span></legend>
                                <div class="d-flex">
                                     <div class="form-check form-check-inline me-3"> <input class="form-check-input" type="radio" name="modo_criacao" id="modoModelo" value="modelo" <?= ($modo_criacao_form === 'modelo') ? 'checked' : '' ?> required> <label class="form-check-label small" for="modoModelo">Modelo</label> </div>
                                     <div class="form-check form-check-inline"> <input class="form-check-input" type="radio" name="modo_criacao" id="modoManual" value="manual" <?= ($modo_criacao_form === 'manual') ? 'checked' : '' ?> required> <label class="form-check-label small" for="modoManual">Manual</label> </div>
                                </div> <div class="invalid-feedback d-block" id="modoCriacaoError" style="display:none;">Origem.</div>
                                </fieldset>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 border rounded bg-light mb-4" id="blocoEquipeAuditoriaWrapper" style="display: <?= ($modo_atribuicao_form === 'equipe') ? 'block' : 'none' ?>;">
                         <div class="row g-3">
                             <div class="col-lg-6">
                                <label for="equipe_id" class="form-label form-label-sm fw-semibold">Equipe <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="equipe_id" name="equipe_id">
                                    <option value="">-- Selecione --</option>
                                    <?php foreach ($equipes_dropdown as $eq): ?><option value="<?= $eq['id'] ?>" <?= ($equipe_id_form == $eq['id']) ? 'selected' : '' ?>><?= htmlspecialchars($eq['nome']) ?></option><?php endforeach; ?>
                                </select> <div class="invalid-feedback">Equipe.</div>
                             </div>
                             <div class="col-lg-6 d-flex align-items-end pb-1"><small class="form-text text-muted">Equipes usam Modelo.</small></div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="mb-4" id="blocoSelecaoModelo" style="display: none;">
                            <label for="modelo_id" class="form-label form-label-sm fw-semibold">Modelo Base <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="modelo_id" name="modelo_id">
                                <option value="">-- Selecione --</option>
                                <?php foreach ($modelos_dropdown as $mod): ?><option value="<?= $mod['id'] ?>" <?= ($modelo_id_form == $mod['id']) ? 'selected' : '' ?>><?= htmlspecialchars($mod['nome']) ?></option><?php endforeach; ?>
                            </select> <div class="invalid-feedback">Modelo.</div>
                        </div>
                        <div class="p-3 border rounded bg-white mb-4" id="blocoAtribuicaoSecoesModelo" style="display: none;">
                            <h6 class="fw-semibold mb-3 small text-primary-emphasis"><i class="fas fa-user-tag me-2"></i>Atribuir Auditores por Seção</h6>
                            <div id="listaSecoesParaAtribuicao" class="mb-2 ps-2"> <p class="text-muted small fst-italic">Selecione equipe e modelo.</p> </div>
                            <div id="loadingSecoes" class="text-center py-2" style="display:none;"> <div class="spinner-border spinner-border-sm text-primary"></div> <span class="ms-2 small">Carregando...</span> </div>
                        </div>
                        <div class="mb-4" id="blocoSelecaoManual" style="display: none;">
                            <label class="form-label form-label-sm fw-semibold mb-2">Requisitos <span class="text-danger">*</span></label>
                            <input type="search" id="filtroRequisitos" class="form-control form-control-sm mb-2" placeholder="Filtrar...">
                            <div id="requisitosChecklist" class="border rounded p-3 bg-white" style="max-height: 400px; overflow-y: auto;">
                                <?php if(empty($requisitos_por_categoria_todos)):?><p class="text-danger small">Nenhum.</p><?php else: ?>
                                <?php foreach($requisitos_por_categoria_todos as $cat => $reqs): ?>
                                <fieldset class="mb-3 categoria-group"><legend class="h6 small fw-semibold text-secondary border-bottom pb-1 mb-2 sticky-legend py-1 px-2 rounded-top"><?= htmlspecialchars($cat) ?><span class="badge bg-light text-dark border float-end"><?= count($reqs) ?></span></legend><div class="ms-2">
                                <?php foreach($reqs as $req_item): ?>
                                <div class="form-check requisito-item" data-texto="<?= htmlspecialchars(strtolower(($req_item['codigo']??'').' '.$req_item['nome'])) ?>">
                                    <input class="form-check-input" type="checkbox" name="requisitos_selecionados[]" value="<?= $req_item['id'] ?>" id="edit_req_<?= $req_item['id'] ?>" <?= in_array($req_item['id'], $requisitos_selecionados_form) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="edit_req_<?= $req_item['id'] ?>"><?php if(!empty($req_item['codigo'])): ?><strong><?= htmlspecialchars($req_item['codigo']) ?>:</strong> <?php endif; ?><?= htmlspecialchars($req_item['nome']) ?></label>
                                </div>
                                <?php endforeach; ?></div></fieldset>
                                <?php endforeach; ?><p class="text-center text-muted small mt-3 no-results-message" style="display:none;">Nenhum.</p><?php endif; ?>
                            </div> <div class="invalid-feedback d-block" id="requisitosError" style="display:none;">Selecione.</div>
                        </div>
                    </div>
                </div>
            </fieldset>
        </div>
    </div>

    <div class="mt-4 mb-5 d-flex justify-content-end">
        <a href="<?= BASE_URL ?>gestor/auditoria/detalhes_auditoria.php?id=<?= $auditoria_id_edit ?>" class="btn btn-secondary rounded-pill px-4 me-2">Cancelar</a>
        <button type="submit" class="btn btn-success rounded-pill px-4 action-button-main"> <i class="fas fa-save me-1"></i> Salvar Alterações </button>
    </div>
</form>

<!-- O JavaScript será o mesmo de criar_auditoria.php, com pequenas adaptações para IDs se necessário -->
<!-- A repopulação é feita principalmente pelo PHP ao renderizar os 'value', 'checked', 'selected' nos campos -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById('formEditarAuditoria'); // ID do form

    const modoAtribuirAuditorRadio = document.getElementById('modoAtribuirAuditor');
    const modoAtribuirEquipeRadio = document.getElementById('modoAtribuirEquipe');
    const atribuicaoErrorDiv = document.getElementById('atribuicaoError');

    const blocoAuditorIndividualWrapper = document.getElementById('blocoAuditorIndividualWrapper');
    const blocoEquipeAuditoriaWrapper = document.getElementById('blocoEquipeAuditoriaWrapper');

    const auditorSelect = document.getElementById('auditor_id');
    const blocoOrigemRequisitos = document.getElementById('blocoOrigemRequisitos'); // O fieldset
    const modoModeloRadio = document.getElementById('modoModelo');
    const modoManualRadio = document.getElementById('modoManual');
    const modoCriacaoErrorDiv = document.getElementById('modoCriacaoError');

    const equipeSelect = document.getElementById('equipe_id');
    const modeloSelect = document.getElementById('modelo_id');

    const blocoSelecaoModelo = document.getElementById('blocoSelecaoModelo');
    const blocoAtribuicaoSecoesModelo = document.getElementById('blocoAtribuicaoSecoesModelo');
    const listaSecoesParaAtribuicao = document.getElementById('listaSecoesParaAtribuicao');
    const loadingSecoes = document.getElementById('loadingSecoes');

    const blocoSelecaoManual = document.getElementById('blocoSelecaoManual');
    const requisitosChecklistContainer = document.getElementById('requisitosChecklist');
    const requisitosErrorDiv = document.getElementById('requisitosError');
    const filtroReqManualInput = document.getElementById('filtroRequisitos');
    const noResultsMessage = requisitosChecklistContainer?.querySelector('.no-results-message');
    let requisitosCheckboxes = requisitosChecklistContainer ? requisitosChecklistContainer.querySelectorAll('.requisito-item input[type="checkbox"]') : [];

    // Pega o token da página que foi inserido pelo PHP
    const csrfTokenPageInputElement = document.getElementById("csrf_token_page_input") || document.querySelector("input[name=csrf_token]"); // Fallback se o id não existir

    function updateFormDisplayAndRequirements() {
        const isAuditorIndividual = modoAtribuirAuditorRadio.checked;
        const isEquipe = modoAtribuirEquipeRadio.checked;
        let showSelecaoModelo = false;
        let showSelecaoManual = false;
        let showAtribuicaoSecoes = false;
        let needsAjaxCall = false;

        // Reset
        blocoAuditorIndividualWrapper.style.display = 'none';
        blocoEquipeAuditoriaWrapper.style.display = 'none';
        blocoSelecaoModelo.style.display = 'none';
        blocoSelecaoManual.style.display = 'none';
        blocoAtribuicaoSecoesModelo.style.display = 'none';
        if (blocoOrigemRequisitos) blocoOrigemRequisitos.style.display = 'none';


        auditorSelect.required = false;
        equipeSelect.required = false;
        modoModeloRadio.required = false;
        modoManualRadio.required = false;
        modeloSelect.required = false;
        modoModeloRadio.disabled = false;
        modoManualRadio.disabled = false;

        if (isAuditorIndividual) {
            blocoAuditorIndividualWrapper.style.display = '';
            auditorSelect.required = true;
            if(blocoOrigemRequisitos) blocoOrigemRequisitos.style.display = '';
            modoModeloRadio.required = true;
            modoManualRadio.required = true;

            if (modoModeloRadio.checked) { showSelecaoModelo = true; modeloSelect.required = true; }
            if (modoManualRadio.checked) { showSelecaoManual = true; }

        } else if (isEquipe) {
            blocoEquipeAuditoriaWrapper.style.display = '';
            equipeSelect.required = true;
            if(blocoOrigemRequisitos) blocoOrigemRequisitos.style.display = 'none';
            modoModeloRadio.checked = true; modoModeloRadio.disabled = true; modoManualRadio.disabled = true;
            showSelecaoModelo = true; modeloSelect.required = true;

            if (equipeSelect.value && modeloSelect.value) { showAtribuicaoSecoes = true; needsAjaxCall = true; }
            else { listaSecoesParaAtribuicao.innerHTML = '<p class="text-muted small fst-italic">Selecione equipe e modelo.</p>';}
        }

        blocoSelecaoModelo.style.display = showSelecaoModelo ? '' : 'none';
        blocoSelecaoManual.style.display = showSelecaoManual ? '' : 'none';
        blocoAtribuicaoSecoesModelo.style.display = showAtribuicaoSecoes ? '' : 'none';

        if (needsAjaxCall) loadSecoesParaAtribuicao(); // Para EDIÇÃO, esta função precisa usar os valores atuais de secao_responsaveis_form

        if (!showSelecaoModelo && !isEquipe && modeloSelect) modeloSelect.value = '';
        if (!showSelecaoManual) { if(requisitosCheckboxes) requisitosCheckboxes.forEach(cb => cb.checked = false); if(requisitosErrorDiv) requisitosErrorDiv.style.display = 'none';}
    }

    async function loadSecoesParaAtribuicao() {
        const equipeId = equipeSelect.value;
        const modeloId = modeloSelect.value;
        if (!modoAtribuirEquipeRadio.checked || !equipeId || !modeloId) {
            blocoAtribuicaoSecoesModelo.style.display = 'none';
            listaSecoesParaAtribuicao.innerHTML = '<p class="text-muted small fst-italic">Selecione equipe e modelo.</p>';
            return;
        }
        blocoAtribuicaoSecoesModelo.style.display = ''; listaSecoesParaAtribuicao.innerHTML = ''; loadingSecoes.style.display = 'flex';
        try {
            const formData = new FormData();
            formData.append("action", "get_secoes_e_membros"); formData.append("equipe_id", equipeId); formData.append("modelo_id", modeloId);
            formData.append("csrf_token", csrfTokenPageInputElement ? csrfTokenPageInputElement.value : document.querySelector("input[name=csrf_token]").value );

            const response = await fetch("<?= BASE_URL ?>gestor/auditoria/ajax_handler_auditoria.php", { method: "POST", body: formData });
            loadingSecoes.style.display = 'none';
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();

            if (data.novo_csrf && csrfTokenPageInputElement) csrfTokenPageInputElement.value = data.novo_csrf;

            if (data.success) {
                if (!data.secoes || data.secoes.length === 0) { listaSecoesParaAtribuicao.innerHTML = '<p class="text-warning small">Modelo sem seções.</p>'; return; }
                if (!data.membros_equipe || data.membros_equipe.length === 0) { listaSecoesParaAtribuicao.innerHTML = '<p class="text-danger small">Equipe sem membros.</p>'; return; }

                const secaoRespFormValores = <?= json_encode($secao_responsaveis_form ?: []) ?>; // Dados do PHP
                console.log("Valores de repopulação seções:", secaoRespFormValores);

                let html = '<ul class="list-unstyled mb-0">';
                data.secoes.forEach(secao => {
                    const secaoNomeHtmlId = 'edit_secao_resp_' + secao.replace(/[^a-zA-Z0-9_]/g, '_') + '_' + Math.random().toString(36).substring(7);
                    const secaoNomeForm = secao;
                    html += `<li class="mb-2 row align-items-center gx-2"><label for="${secaoNomeHtmlId}" class="col-sm-5 col-md-4 col-form-label col-form-label-sm fw-normal text-truncate" title="${jsHtmlspecialchars(secao)}">${jsHtmlspecialchars(secao)}:</label><div class="col-sm-7 col-md-8"><select name="secao_responsaveis[${jsHtmlspecialchars(secaoNomeForm)}]" id="${secaoNomeHtmlId}" class="form-select form-select-sm"><option value="">-- Não atribuído --</option>`;
                    data.membros_equipe.forEach(membro => {
                        let isSelected = '';
                        // A chave em secaoRespFormValores é o nome da seção. O valor é o ID do auditor.
                        if (secaoRespFormValores && typeof secaoRespFormValores === 'object' && secaoRespFormValores.hasOwnProperty(secaoNomeForm) && String(secaoRespFormValores[secaoNomeForm]) === String(membro.id)) {
                            isSelected = 'selected';
                        }
                        html += `<option value="${membro.id}" ${isSelected}>${jsHtmlspecialchars(membro.nome)}</option>`;
                    });
                    html += `</select></div></li>`;
                });
                html += '</ul>';
                listaSecoesParaAtribuicao.innerHTML = html;
            } else { listaSecoesParaAtribuicao.innerHTML = `<p class="text-danger small">Erro: ${jsHtmlspecialchars(data.message || '')}</p>`;}
        } catch (error) { loadingSecoes.style.display = 'none'; listaSecoesParaAtribuicao.innerHTML = '<p class="text-danger small">Falha ao carregar seções.</p>'; console.error("Erro AJAX Edit loadSecoes:", error); }
    }

    function jsHtmlspecialchars(str) { /* ... (função de escape) ... */ } // Implemente ou use a global

    // Listeners
    modoAtribuirAuditorRadio.addEventListener('change', updateFormDisplayAndRequirements);
    modoAtribuirEquipeRadio.addEventListener('change', updateFormDisplayAndRequirements);
    if(modoModeloRadio) modoModeloRadio.addEventListener('change', updateFormDisplayAndRequirements);
    if(modoManualRadio) modoManualRadio.addEventListener('change', updateFormDisplayAndRequirements);
    equipeSelect.addEventListener('change', updateFormDisplayAndRequirements);
    modeloSelect.addEventListener('change', updateFormDisplayAndRequirements);

    form.addEventListener('submit', function(event) { /* ... (Lógica de validação similar a criar_auditoria.php) ... */
        let formValido = true; let primeiroErro = null;
        // ... (resetar erros) ...
        updateFormDisplayAndRequirements();
        if (!form.checkValidity()) { formValido = false; primeiroErro = form.querySelector(':invalid:not(fieldset)'); }
        // ... (validações customizadas) ...
        if(!formValido){ event.preventDefault(); event.stopPropagation(); if(primeiroErro) setTimeout(() => {primeiroErro.focus(); primeiroErro.scrollIntoView({behavior:'smooth',block:'center'});},50); }
        form.classList.add('was-validated');
    });

    if (filtroReqManualInput && requisitosChecklistContainer) { /* ... código do filtro manual ... */ }

    // Inicialização
    updateFormDisplayAndRequirements();
    // Se equipe e modelo já estão selecionados (repopulação do PHP), carregar seções
    if (modoAtribuirEquipeRadio.checked && equipeSelect.value && modeloSelect.value) {
        loadSecoesParaAtribuicao();
    }
});
</script>

<?php
echo getFooterGestor();
?>