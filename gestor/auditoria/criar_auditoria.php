<?php
// gestor/auditoria/criar_auditoria.php - Versão REFORMULADA com Atribuição por Seção para Equipes e Layout Ajustado

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_gestor.php';
require_once __DIR__ . '/../../includes/gestor_functions.php';
require_once __DIR__ . '/../../includes/funcoes_upload.php'; // Para upload de documentos

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$empresa_id = $_SESSION['usuario_empresa_id'];
$gestor_id = $_SESSION['usuario_id'];

// --- Buscar Dados para os Dropdowns ---
$modelos = getModelosAtivos($conexao);
$auditores_individuais = getAuditoresDaEmpresa($conexao, $empresa_id);
$equipes = getEquipesDaEmpresa($conexao, $empresa_id);
$requisitos_por_categoria = getRequisitosAtivosAgrupados($conexao);

// --- Inicialização de Variáveis ---
// Renomeado variáveis POST para evitar conflito com $title e outras variáveis globais
$titulo_form = $_POST['titulo'] ?? '';
$modo_atribuicao_post = filter_input(INPUT_POST, 'modo_atribuicao', FILTER_SANITIZE_STRING) ?? 'auditor';
$auditor_id_post = filter_input(INPUT_POST, 'auditor_id', FILTER_VALIDATE_INT);
$equipe_id_post = filter_input(INPUT_POST, 'equipe_id', FILTER_VALIDATE_INT);
$escopo_form = $_POST['escopo'] ?? '';
$objetivo_form = $_POST['objetivo'] ?? '';
$instrucoes_form = $_POST['instrucoes'] ?? '';
$data_inicio_form = $_POST['data_inicio'] ?? '';
$data_fim_form = $_POST['data_fim'] ?? '';
$modo_criacao_post = ($modo_atribuicao_post === 'equipe') ? 'modelo' : ($_POST['modo_criacao'] ?? 'modelo');
$modelo_id_post = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT);
$requisitos_selecionados_post = isset($_POST['requisitos_selecionados']) && is_array($_POST['requisitos_selecionados'])
                                    ? array_filter(array_map('intval', $_POST['requisitos_selecionados']), fn($id) => $id > 0)
                                    : [];
$secao_responsaveis_post = $_POST['secao_responsaveis'] ?? [];

$csrf_token = gerar_csrf_token();
$erro_msg_flash = obter_flash_message('erro'); // Obter mensagem flash

// --- Processamento do Formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação CSRF
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão. Por favor, tente novamente.');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenera para a próxima

    $errors = []; // Array para acumular erros

    // Validação: Título
    $titulo_form_val = trim(filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_STRING)) ?: '';
    if (empty($titulo_form_val)) $errors[] = "Título da auditoria é obrigatório.";

    // Coleta (sem validação obrigatória aqui, pode ser feito em criarAuditoria)
    $escopo_form_val = trim(filter_input(INPUT_POST, 'escopo', FILTER_SANITIZE_STRING));
    $objetivo_form_val = trim(filter_input(INPUT_POST, 'objetivo', FILTER_SANITIZE_STRING));
    $instrucoes_form_val = trim(filter_input(INPUT_POST, 'instrucoes', FILTER_SANITIZE_STRING));

    // Validação: Datas
    $data_inicio_form_val = filter_input(INPUT_POST, 'data_inicio', FILTER_SANITIZE_STRING);
    $data_fim_form_val = filter_input(INPUT_POST, 'data_fim', FILTER_SANITIZE_STRING);
    if (!empty($data_inicio_form_val) && !DateTime::createFromFormat('Y-m-d', $data_inicio_form_val)) $errors[] = "Data de início planejada inválida (Formato AAAA-MM-DD).";
    if (!empty($data_fim_form_val) && !DateTime::createFromFormat('Y-m-d', $data_fim_form_val)) $errors[] = "Data fim planejada inválida (Formato AAAA-MM-DD).";
    if (!empty($data_inicio_form_val) && !empty($data_fim_form_val)) {
        $inicio_dt = DateTime::createFromFormat('Y-m-d', $data_inicio_form_val);
        $fim_dt = DateTime::createFromFormat('Y-m-d', $data_fim_form_val);
        if ($inicio_dt && $fim_dt && $fim_dt < $inicio_dt) $errors[] = "Data fim planejada não pode ser anterior à data de início.";
    }

    // Validação: Atribuição e Modo de Criação (Lógica Complexa)
    $modo_atribuicao_val = filter_input(INPUT_POST, 'modo_atribuicao', FILTER_SANITIZE_STRING);
    $auditor_id_val = null;
    $equipe_id_val = null;
    $modelo_id_val = null;
    $requisitos_para_criar_itens_val = [];
    $secao_responsaveis_input_val = [];

    if ($modo_atribuicao_val === 'auditor') {
        $auditor_id_val = filter_input(INPUT_POST, 'auditor_id', FILTER_VALIDATE_INT) ?: null;
        if (empty($auditor_id_val)) $errors[] = "Selecione um Auditor Individual.";

        $modo_criacao_val = filter_input(INPUT_POST, 'modo_criacao', FILTER_SANITIZE_STRING);
        if (empty($modo_criacao_val)) { // Verifica se o modo de criação foi selecionado
             $errors[] = "Selecione a origem dos itens (Modelo ou Manual) para o auditor individual.";
        } elseif ($modo_criacao_val === 'modelo') {
            $modelo_id_val = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT) ?: null;
            if (empty($modelo_id_val)) $errors[] = "Selecione um Modelo Base para o auditor individual.";
        } elseif ($modo_criacao_val === 'manual') {
            $req_sel_post_val = isset($_POST['requisitos_selecionados']) && is_array($_POST['requisitos_selecionados'])
                                            ? array_filter(array_map('intval', $_POST['requisitos_selecionados']), fn($id) => $id > 0)
                                            : [];
            if (empty($req_sel_post_val)) {
                $errors[] = "Selecione ao menos um requisito/controle para o auditor individual.";
            } else {
                 $requisitos_para_criar_itens_val = $req_sel_post_val;
            }
            $modelo_id_val = null; // Garante que modelo ID seja nulo se for manual
        } else {
            $errors[] = "Modo de criação de itens inválido para auditor individual.";
        }

    } elseif ($modo_atribuicao_val === 'equipe') {
        $equipe_id_val = filter_input(INPUT_POST, 'equipe_id', FILTER_VALIDATE_INT) ?: null;
        if (empty($equipe_id_val)) $errors[] = "Selecione uma Equipe.";

        $modelo_id_val = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT) ?: null;
        if (empty($modelo_id_val)) $errors[] = "Selecione um Modelo Base (obrigatório para equipes).";

        // Processar responsáveis por seção
        if (isset($_POST['secao_responsaveis']) && is_array($_POST['secao_responsaveis'])) {
            foreach($_POST['secao_responsaveis'] as $secao_nome => $auditor_designado_id) {
                // Chaves podem ter aspas, sanitizar nome da seção vindo da chave do array
                $secao_nome_sanitized = trim(filter_var($secao_nome, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
                $auditor_designado_id_validated = filter_var($auditor_designado_id, FILTER_VALIDATE_INT);

                if (!empty($secao_nome_sanitized) && $auditor_designado_id_validated) {
                    $secao_responsaveis_input_val[$secao_nome_sanitized] = $auditor_designado_id_validated;
                } elseif (!empty($secao_nome_sanitized) && $auditor_designado_id_validated === false && $auditor_designado_id !== '') {
                    // Opcional: Permitir seção "não atribuída" (valor vazio '') ou erro se qualquer outro valor inválido for passado.
                     $errors[] = "Auditor inválido selecionado para a seção: '" . htmlspecialchars($secao_nome_sanitized) . "'";
                } elseif (!empty($secao_nome_sanitized) && empty($auditor_designado_id)) {
                     // Permite 'Não atribuído'
                }
            }
            // Opcional: Validação para garantir que TODAS as seções retornadas pelo AJAX foram atribuídas.
            // Pode ser complexo se a lista de seções AJAX não for guardada. Mais simples é deixar opcional.
        }
        $requisitos_para_criar_itens_val = []; // Equipe sempre usa modelo, lista manual vazia.
        $auditor_id_val = null; // Garante que auditor individual seja nulo

    } else {
        $errors[] = "Selecione o modo de atribuição: Auditor Individual ou Equipe.";
    }

    // Validação e Processamento: Upload de Documentos
    $documentos_upload_info = ['success' => true, 'message' => '', 'files' => []];
    $arquivos_upload_processados = []; // Array para armazenar metadados dos arquivos temp válidos
    if (isset($_FILES['documentos']) && !empty(array_filter($_FILES['documentos']['name']))) {
        // Criar diretório temporário único para a requisição
        // Certifique-se que UPLOADS_BASE_PATH termina com '/'
        $temp_upload_dir = rtrim(UPLOADS_BASE_PATH, '/') . '/temp/' . session_id() . '_' . time() . '/';

        $documentos_upload_info = processarDocumentUploads($_FILES['documentos'], $temp_upload_dir); // Passa diretório físico

        if (!$documentos_upload_info['success']) {
            // Adiciona a mensagem de erro geral OU erros individuais se houver
            if (!empty($documentos_upload_info['message'])) {
                 $errors[] = "Falha(s) no upload: " . $documentos_upload_info['message'];
            } elseif (!empty($documentos_upload_info['errors'])) { // Se a função retorna um array de erros por arquivo
                 foreach ($documentos_upload_info['errors'] as $file_error) {
                     $errors[] = "Erro Upload (" . htmlspecialchars($file_error['original_name']) . "): " . htmlspecialchars($file_error['error']);
                 }
            }
        }
        // Mesmo com erro, $documentos_upload_info['files'] pode conter arquivos processados com sucesso antes do erro
        $arquivos_upload_processados = $documentos_upload_info['files'] ?? [];
    }


    // Criação da Auditoria no DB se não houver erros de validação
    $novaAuditoriaId = null;
    if (empty($errors)) {
        $dadosAuditoriaDB = [
            'titulo' => $titulo_form_val,
            'empresa_id' => $empresa_id,
            'gestor_id' => $gestor_id,
            'auditor_individual_id' => $auditor_id_val,
            'equipe_id' => $equipe_id_val,
            'escopo' => $escopo_form_val,
            'objetivo' => $objetivo_form_val,
            'instrucoes' => $instrucoes_form_val,
            'data_inicio' => empty($data_inicio_form_val) ? null : $data_inicio_form_val,
            'data_fim' => empty($data_fim_form_val) ? null : $data_fim_form_val,
            'modelo_id' => $modelo_id_val,
            'requisitos_selecionados' => $requisitos_para_criar_itens_val,
            'secao_responsaveis' => $secao_responsaveis_input_val
        ];

        // $arquivos_upload_processados contém os metadados dos arquivos temporários válidos
        $novaAuditoriaId = criarAuditoria($conexao, $dadosAuditoriaDB, $arquivos_upload_processados);

        if ($novaAuditoriaId && $novaAuditoriaId > 0) {
            definir_flash_message('sucesso', "Auditoria '" . htmlspecialchars($titulo_form_val) . "' planejada com sucesso (ID: $novaAuditoriaId).");
            dbRegistrarLogAcesso($gestor_id, $_SERVER['REMOTE_ADDR'], 'criar_auditoria_sucesso', 1, "Auditoria ID: $novaAuditoriaId. Título: $titulo_form_val. Modo Atrib: $modo_atribuicao_val.", $conexao);
            header('Location: ' . BASE_URL . 'gestor/auditoria/minhas_auditorias.php');
            exit;
        } else {
             // criarAuditoria já deve ter feito rollback e limpeza dos arquivos finais movidos
            $errors[] = "Erro interno ao salvar a auditoria. Consulte os logs do sistema para mais detalhes.";
            dbRegistrarLogAcesso($gestor_id, $_SERVER['REMOTE_ADDR'], 'criar_auditoria_falha_db', 0, "Falha DB/Proc para título: $titulo_form_val. Modo Atrib: $modo_atribuicao_val.", $conexao);
            // Não precisamos mais limpar os arquivos TEMP aqui, pois criarAuditoria lida com eles no erro.
             // No entanto, se a *validação* falhou ANTES de chamar criarAuditoria, eles ainda podem estar no temp.
             // (Ver bloco abaixo `if (!empty($errors))`)
        }
    }

    // Se chegou aqui com erros (de validação ou da criação)
    if (!empty($errors)) {
        definir_flash_message('erro', "<strong>Não foi possível criar a auditoria:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>");

        // Limpar arquivos temporários se eles foram processados com sucesso mas a validação do form falhou DEPOIS do upload.
        // Se a falha foi DENTRO de criarAuditoria, ela já limpou. Verificamos se $novaAuditoriaId é null para saber se falhou antes ou dentro.
        if (empty($novaAuditoriaId) && !empty($arquivos_upload_processados)) {
             foreach ($arquivos_upload_processados as $doc) {
                 if (isset($doc['caminho_temp']) && file_exists($doc['caminho_temp'])) {
                     @unlink($doc['caminho_temp']);
                 }
             }
             // Tenta remover o diretório temporário da requisição se ele existir e estiver vazio
             $primeiro_arquivo_temp = $arquivos_upload_processados[0]['caminho_temp'] ?? null;
             if ($primeiro_arquivo_temp) {
                 $diretorio_temp_req = dirname($primeiro_arquivo_temp);
                 if (is_dir($diretorio_temp_req) && !(new FilesystemIterator($diretorio_temp_req))->valid()) {
                     @rmdir($diretorio_temp_req);
                 }
             }
        }
        header('Location: ' . $_SERVER['PHP_SELF']); // Redireciona para si mesmo para mostrar erro flash e repopular form
        exit;
    }
}

// Definição final do título da página HTML
$page_title = "Criar Nova Auditoria";
echo getHeaderGestor($page_title);
?>
<style>
    /* Estilo opcional para lista de seções com scroll */
    #listaSecoesParaAtribuicao ul {
        max-height: 350px; /* Ajuste conforme necessário */
        overflow-y: auto;
        padding-right: 10px; /* Espaço para scrollbar, se aparecer */
        border: 1px solid #dee2e6; /* Borda sutil na lista */
        padding: 10px;
        border-radius: .25rem;
        background-color: #fff;
    }
    .sticky-legend {
        position: sticky;
        top: -1px; /* Pequeno ajuste para cobrir a borda do fieldset */
        z-index: 1;
        background-color: #f8f9fa !important; /* Cor de fundo para legenda fixa */
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-plus-circle me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb"><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php">Auditorias</a></li><li class="breadcrumb-item active" aria-current="page">Criar</li></ol></nav>
    </div>
    <a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php" class="btn btn-outline-secondary rounded-pill px-3 action-button-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
</div>

<?php if ($erro_msg_flash): ?>
    <div class="alert alert-danger gestor-alert fade show" role="alert">
        <i class="fas fa-exclamation-triangle flex-shrink-0 me-2"></i>
        <div><?= $erro_msg_flash /* Permite HTML básico do implode de erros */ ?></div>
        <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($sucesso_msg_flash = obter_flash_message('sucesso')): ?>
    <div class="alert alert-success gestor-alert fade show" role="alert">
        <i class="fas fa-check-circle flex-shrink-0 me-2"></i>
        <div><?= htmlspecialchars($sucesso_msg_flash) ?></div>
        <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="formCriarAuditoria" class="needs-validation" novalidate enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <!-- CARD 1: INFORMAÇÕES GERAIS E PLANEJAMENTO -->
    <div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2">
            <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary opacity-75"></i>Informações Gerais e Planejamento</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-12">
                    <label for="titulo" class="form-label form-label-sm fw-semibold">Título da Auditoria <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="titulo" name="titulo" value="<?= htmlspecialchars($titulo_form) ?>" required>
                    <div class="invalid-feedback">Título obrigatório.</div>
                </div>

                <div class="col-md-6">
                    <label for="data_inicio" class="form-label form-label-sm fw-semibold">Início Planejado</label>
                    <input type="date" class="form-control form-control-sm" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($data_inicio_form) ?>">
                    <div class="invalid-feedback">Data inválida.</div>
                </div>
                <div class="col-md-6">
                    <label for="data_fim" class="form-label form-label-sm fw-semibold">Fim Planejado</label>
                    <input type="date" class="form-control form-control-sm" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim_form) ?>">
                    <div class="invalid-feedback" id="dataFimFeedback">Data inválida ou anterior ao início.</div>
                </div>
                 <div class="col-12">
                    <label for="escopo" class="form-label form-label-sm fw-semibold">Escopo</label>
                    <textarea class="form-control form-control-sm" id="escopo" name="escopo" rows="2" placeholder="Descreva o que será auditado..."><?= htmlspecialchars($escopo_form) ?></textarea>
                </div>
                <div class="col-12">
                    <label for="objetivo" class="form-label form-label-sm fw-semibold">Objetivo</label>
                    <textarea class="form-control form-control-sm" id="objetivo" name="objetivo" rows="2" placeholder="Descreva o objetivo principal..."><?= htmlspecialchars($objetivo_form) ?></textarea>
                </div>
                <div class="col-12">
                    <label for="instrucoes" class="form-label form-label-sm fw-semibold">Instruções para Auditor(es)</label>
                    <textarea class="form-control form-control-sm" id="instrucoes" name="instrucoes" rows="3" placeholder="Instruções ou informações adicionais importantes para a execução da auditoria..."><?= htmlspecialchars($instrucoes_form) ?></textarea>
                </div>
                 <div class="col-12">
                     <label for="documentos" class="form-label form-label-sm fw-semibold mb-1">Documentos de Planejamento (Opcional)</label>
                     <input type="file" class="form-control form-control-sm" id="documentos" name="documentos[]" multiple accept=".pdf,.xlsx,.xls,.docx,.doc,.jpg,.jpeg,.png,.gif">
                     <small class="form-text text-muted">Anexe documentos relevantes (escopo detalhado, normas, políticas, etc.). Tipos permitidos: PDF, Office, Imagens. Tamanho máx: <?= MAX_UPLOAD_SIZE_MB ?>MB/arquivo.</small>
                      <div class="invalid-feedback">Verifique os arquivos selecionados (tipo ou tamanho).</div>
                 </div>
            </div>
        </div>
    </div>

    <!-- CARD 2: ATRIBUIÇÃO E DEFINIÇÃO DOS ITENS -->
    <div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2">
            <h6 class="mb-0 fw-bold"><i class="fas fa-sitemap me-2 text-primary opacity-75"></i>Atribuição e Definição dos Itens</h6>
        </div>
        <div class="card-body p-4">

            <!-- Passo 1: Escolha da Atribuição -->
            <fieldset class="mb-4 pb-3 border-bottom"> <!-- Usar fieldset para agrupar semanticamente -->
                 <legend class="form-label form-label fw-semibold mb-2 d-block"><span class="badge bg-primary-subtle text-primary-emphasis rounded-pill me-2">1</span>Atribuir para:</legend>
                 <div class="d-flex flex-wrap align-items-center">
                     <div class="form-check form-check-inline me-3 mb-2 mb-md-0">
                        <input class="form-check-input" type="radio" name="modo_atribuicao" id="modoAtribuirAuditor" value="auditor" <?= ($modo_atribuicao_post === 'auditor') ? 'checked' : '' ?> required>
                        <label class="form-check-label" for="modoAtribuirAuditor">
                            <i class="fas fa-user text-secondary me-1"></i>Auditor Individual
                        </label>
                     </div>
                     <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modo_atribuicao" id="modoAtribuirEquipe" value="equipe" <?= ($modo_atribuicao_post === 'equipe') ? 'checked' : '' ?> required>
                        <label class="form-check-label" for="modoAtribuirEquipe">
                            <i class="fas fa-users text-secondary me-1"></i>Equipe de Auditoria
                        </label>
                     </div>
                 </div>
                 <div class="invalid-feedback d-block" id="atribuicaoError" style="display: none;">Selecione um modo de atribuição.</div>
            </fieldset>

            <!-- Passo 2: Detalhes da Atribuição e Seleção de Itens -->
            <fieldset class="mb-3"> <!-- Fieldset para agrupar o Passo 2 -->
                <legend class="form-label fw-semibold mb-3 d-block"><span class="badge bg-primary-subtle text-primary-emphasis rounded-pill me-2">2</span>Detalhes e Itens:</legend>

                 <!-- Container para conteúdo condicional -->
                <div class="ps-lg-1"> <!-- Sem padding inicial, os blocos internos terão -->

                    <!-- Bloco para Auditor Individual (Container Principal) -->
                    <div class="p-3 border rounded bg-light mb-4" id="blocoAuditorIndividualWrapper" style="display: none;">
                        <div class="row g-3">
                            <div class="col-lg-6"> <!-- Ajustado para talvez dividir espaço -->
                                <label for="auditor_id" class="form-label form-label-sm fw-semibold">Auditor Responsável <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="auditor_id" name="auditor_id">
                                    <option value="">-- Selecione --</option>
                                    <?php foreach ($auditores_individuais as $auditor): ?>
                                        <option value="<?= $auditor['id'] ?>" <?= ($auditor_id_post == $auditor['id']) ? 'selected' : '' ?>><?= htmlspecialchars($auditor['nome']) ?></option>
                                    <?php endforeach; ?>
                                    <?php if (empty($auditores_individuais)): ?><option value="" disabled>Nenhum auditor cadastrado</option><?php endif; ?>
                                </select>
                                <div class="invalid-feedback">Selecione um auditor.</div>
                            </div>
                             <!-- Bloco Origem Itens DENTRO do Bloco Auditor Individual -->
                             <div class="col-lg-6 border-start-lg"> <!-- Colocar borda à esquerda em telas grandes -->
                                <fieldset id="blocoOrigemRequisitos">
                                    <legend class="form-label form-label-sm fw-semibold mb-2 d-block">Origem dos Itens <span class="text-danger">*</span></legend>
                                     <div class="d-flex flex-wrap align-items-center">
                                         <div class="form-check form-check-inline me-3 mb-2 mb-md-0">
                                            <input class="form-check-input" type="radio" name="modo_criacao" id="modoModelo" value="modelo" <?= ($modo_criacao_post === 'modelo' && $modo_atribuicao_post === 'auditor') ? 'checked' : '' ?> required>
                                            <label class="form-check-label small fw-normal" for="modoModelo"><i class="fas fa-clipboard-list me-1"></i>Usar Modelo</label>
                                         </div>
                                         <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="modo_criacao" id="modoManual" value="manual" <?= ($modo_criacao_post === 'manual' && $modo_atribuicao_post === 'auditor') ? 'checked' : '' ?> required>
                                            <label class="form-check-label small fw-normal" for="modoManual"><i class="fas fa-check-double me-1"></i>Selecionar Manual</label>
                                         </div>
                                    </div>
                                    <div class="invalid-feedback d-block" id="modoCriacaoError" style="display: none;">Selecione a origem dos itens.</div>
                                </fieldset>
                             </div>
                        </div>
                    </div>

                    <!-- Bloco para Equipe (Container Principal) -->
                     <div class="p-3 border rounded bg-light mb-4" id="blocoEquipeAuditoriaWrapper" style="display: none;">
                         <div class="row g-3">
                             <div class="col-lg-6"> <!-- Ajustado para talvez dividir espaço -->
                                <label for="equipe_id" class="form-label form-label-sm fw-semibold">Equipe Designada <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="equipe_id" name="equipe_id">
                                    <option value="">-- Selecione --</option>
                                    <?php if (!empty($equipes)): ?>
                                        <?php foreach ($equipes as $equipe): ?>
                                            <option value="<?= $equipe['id'] ?>" <?= ($equipe_id_post == $equipe['id']) ? 'selected' : '' ?>><?= htmlspecialchars($equipe['nome']) ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                         <option value="" disabled>Nenhuma equipe cadastrada</option>
                                    <?php endif; ?>
                                </select>
                                <div class="invalid-feedback">Selecione uma equipe.</div>
                             </div>
                              <div class="col-lg-6 d-flex align-items-end pb-1">
                                <small class="form-text text-muted"><i class="fas fa-info-circle me-1"></i>Auditorias de equipe sempre utilizam um Modelo Base.</small>
                             </div>
                        </div>
                    </div>

                    <!-- Conteúdo Dinâmico: Modelo / Atribuição de Seções / Manual -->
                    <div class="mt-3"> <!-- Espaçamento entre seleção auditor/equipe e o conteúdo -->

                        <!-- Bloco Seleção Modelo (Visível para Auditor > Modelo OU para Equipe) -->
                        <div class="mb-4" id="blocoSelecaoModelo" style="display: none;"> <!-- Removido p-3, etc. para não duplicar com wrappers -->
                            <label for="modelo_id" class="form-label form-label-sm fw-semibold">
                                Selecionar Modelo Base <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="modelo_id" name="modelo_id">
                                <option value="">-- Selecione um Modelo --</option>
                                <?php if (empty($modelos)): ?> <option value="" disabled>Nenhum modelo ativo</option>
                                <?php else: ?>
                                    <?php foreach ($modelos as $modelo): ?>
                                        <option value="<?= $modelo['id'] ?>" <?= ($modelo_id_post == $modelo['id']) ? 'selected' : '' ?>><?= htmlspecialchars($modelo['nome']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">Selecione um modelo.</div>
                        </div>

                        <!-- Bloco Atribuição Seções (Visível para Equipe + Modelo Selecionado) -->
                         <div class="p-3 border rounded bg-white mb-4" id="blocoAtribuicaoSecoesModelo" style="display: none;">
                            <h6 class="fw-semibold mb-3 small text-primary-emphasis"><i class="fas fa-tasks me-2"></i>Atribuir Responsáveis por Seção</h6>
                            <div id="listaSecoesParaAtribuicao" class="mb-2">
                                <p class="text-muted small fst-italic">Selecione a equipe e o modelo para listar as seções aqui.</p>
                            </div>
                            <div id="loadingSecoes" class="text-center py-2" style="display:none;">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Carregando...</span></div>
                                <span class="ms-2 small text-muted">Carregando seções e membros...</span>
                            </div>
                            <small class="form-text text-muted d-block mt-1"><i class="fas fa-info-circle fa-xs me-1"></i>Auditores devem ser membros ativos da equipe.</small>
                        </div>

                        <!-- Bloco Seleção Manual (Visível para Auditor > Manual) -->
                         <div class="mb-4" id="blocoSelecaoManual" style="display: none;">
                            <label class="form-label form-label-sm fw-semibold d-block mb-2">Selecionar Requisitos/Controles <span class="text-danger">*</span></label>
                            <input type="search" id="filtroRequisitos" class="form-control form-control-sm mb-2" placeholder="Filtrar por nome ou código...">
                            <div id="requisitosChecklist" class="requisitos-checklist-container border rounded p-3 bg-white" style="max-height: 400px; overflow-y: auto;">
                                <?php if (empty($requisitos_por_categoria)): ?>
                                    <p class="text-danger small m-0 text-center py-3">Nenhum requisito ativo cadastrado.</p>
                                <?php else: ?>
                                    <?php foreach ($requisitos_por_categoria as $categoria => $requisitos): ?>
                                        <fieldset class="mb-3 categoria-group">
                                            <legend class="h6 small fw-semibold text-secondary border-bottom pb-1 mb-2 sticky-legend py-1 px-2 rounded-top"> <!-- Classe sticky-legend adicionada -->
                                                 <?= htmlspecialchars($categoria) ?> <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill float-end"><?= count($requisitos) ?></span>
                                            </legend>
                                            <div class="ms-2">
                                            <?php foreach ($requisitos as $req): ?>
                                                <div class="form-check requisito-item" data-texto="<?= htmlspecialchars(strtolower(($req['codigo'] ?? '') . ' ' . $req['nome'])) ?>">
                                                    <input class="form-check-input" type="checkbox" name="requisitos_selecionados[]" value="<?= $req['id'] ?>" id="req_<?= $req['id'] ?>" <?= (in_array($req['id'], $requisitos_selecionados_post)) ? 'checked' : '' ?>>
                                                    <label class="form-check-label small fw-normal" for="req_<?= $req['id'] ?>">
                                                        <?php if(!empty($req['codigo'])): ?><strong title="<?= htmlspecialchars($req['nome']) ?>"><?= htmlspecialchars($req['codigo']) ?>:</strong> <?php endif; ?>
                                                        <?= htmlspecialchars($req['nome']) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                            </div>
                                        </fieldset>
                                    <?php endforeach; ?>
                                    <p class="text-center text-muted small mt-3 no-results-message" style="display: none;">Nenhum requisito encontrado.</p>
                                <?php endif; ?>
                            </div>
                             <div class="invalid-feedback d-block" id="requisitosError" style="display: none;">Selecione ao menos um requisito/controle.</div>
                         </div>
                    </div><!-- Fim mt-3 wrapper do conteúdo dinâmico -->

                </div> <!-- Fim ps-lg-1 -->
            </fieldset> <!-- Fim fieldset Passo 2 -->

        </div> <!-- Fim card-body Card 2 -->
    </div> <!-- Fim Card 2 -->

    <div class="mt-4 mb-5 d-flex justify-content-end">
        <a href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php" class="btn btn-secondary rounded-pill px-4 me-2">Cancelar</a>
        <button type="submit" class="btn btn-success rounded-pill px-4 action-button-main">
            <i class="fas fa-save me-1"></i> Criar Auditoria
        </button>
    </div>
</form>

<!-- JavaScript (sem mudanças significativas na lógica, apenas certificar que seletores correspondem) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurações centralizadas
    const CONFIG = {
        SELECTORS: {
            form: '#formCriarAuditoria',
            modoAtribuirAuditor: '#modoAtribuirAuditor',
            modoAtribuirEquipe: '#modoAtribuirEquipe',
            atribuicaoError: '#atribuicaoError',
            blocoAuditorIndividual: '#blocoAuditorIndividualWrapper',
            blocoEquipe: '#blocoEquipeAuditoriaWrapper',
            auditorSelect: '#auditor_id',
            blocoOrigemRequisitos: '#blocoOrigemRequisitos',
            modoModelo: '#modoModelo',
            modoManual: '#modoManual',
            modoCriacaoError: '#modoCriacaoError',
            equipeSelect: '#equipe_id',
            blocoSelecaoModelo: '#blocoSelecaoModelo',
            modeloSelect: '#modelo_id',
            blocoAtribuicaoSecoes: '#blocoAtribuicaoSecoesModelo',
            listaSecoes: '#listaSecoesParaAtribuicao',
            loadingSecoes: '#loadingSecoes',
            blocoSelecaoManual: '#blocoSelecaoManual',
            requisitosChecklist: '#requisitosChecklist',
            requisitosError: '#requisitosError',
            filtroRequisitos: '#filtroRequisitos',
            dataInicio: '#data_inicio',
            dataFim: '#data_fim',
            dataFimFeedback: '#dataFimFeedback',
            csrfToken: 'input[name="csrf_token"]'
        },
        MESSAGES: {
            noSections: '<p class="text-warning small text-center py-2"><i class="fas fa-info-circle me-1"></i>Este modelo não possui seções nomeadas ou elas estão vazias.</p>',
            noTeamMembers: '<p class="text-danger small text-center py-2"><i class="fas fa-users-slash me-1"></i>A equipe selecionada não possui membros (auditores) ativos.</p>',
            selectTeamAndModel: '<p class="text-muted small fst-italic">Selecione a equipe e o modelo para listar as seções aqui.</p>',
            ajaxError: '<p class="text-danger small text-center py-2"><i class="fas fa-wifi-slash me-1"></i>Falha na comunicação. Verifique sua conexão.</p>',
            ajaxUnknownError: '<p class="text-danger small text-center py-2"><i class="fas fa-exclamation-triangle me-1"></i>Erro: {message}</p>',
            dateInvalid: 'Data fim não pode ser anterior à data de início.'
        },
        DEBOUNCE_DELAY: 300 // ms
    };

    // Cache de elementos DOM
    const elements = {};
    for (const [key, selector] of Object.entries(CONFIG.SELECTORS)) {
        elements[key] = document.querySelector(selector);
    }

    // Cache de checkboxes de requisitos
    let requisitosCheckboxes = elements.requisitosChecklist
        ? elements.requisitosChecklist.querySelectorAll('.requisito-item input[type="checkbox"]')
        : [];

    // Valores repopulados do PHP
    const secaoRespPost = <?= json_encode($secao_responsaveis_post ?: []) ?>;

    // Função de debounce
    function debounce(func, delay) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => func(...args), delay);
        };
    }

    // Escapa caracteres especiais para HTML
    function htmlspecialchars(str) {
        if (typeof str !== 'string') return str;
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return str.replace(/[&<>"']/g, m => map[m]);
    }

    // Reseta a visibilidade e atributos
    function resetFormDisplay() {
        if (elements.blocoAuditorIndividual) elements.blocoAuditorIndividual.style.display = 'none';
        if (elements.blocoEquipe) elements.blocoEquipe.style.display = 'none';
        if (elements.blocoSelecaoModelo) elements.blocoSelecaoModelo.style.display = 'none';
        if (elements.blocoSelecaoManual) elements.blocoSelecaoManual.style.display = 'none';
        if (elements.blocoAtribuicaoSecoes) elements.blocoAtribuicaoSecoes.style.display = 'none';

        if (elements.auditorSelect) elements.auditorSelect.required = false;
        if (elements.equipeSelect) elements.equipeSelect.required = false;
        if (elements.modoModelo) elements.modoModelo.required = false;
        if (elements.modoManual) elements.modoManual.required = false;
        if (elements.modeloSelect) elements.modeloSelect.required = false;

        if (elements.modoModelo) elements.modoModelo.disabled = false;
        if (elements.modoManual) elements.modoManual.disabled = false;
    }

    // Atualiza a exibição do formulário
    function updateFormDisplay() {
        resetFormDisplay();
        const isAuditorIndividual = elements.modoAtribuirAuditor?.checked;
        const isEquipe = elements.modoAtribuirEquipe?.checked;
        let showSelecaoModelo = false;
        let showSelecaoManual = false;
        let showAtribuicaoSecoes = false;

        if (isAuditorIndividual) {
            if (elements.blocoAuditorIndividual) elements.blocoAuditorIndividual.style.display = 'block';
            if (elements.auditorSelect) elements.auditorSelect.required = true;
            if (elements.blocoOrigemRequisitos) elements.blocoOrigemRequisitos.style.display = 'block';
            if (elements.modoModelo) elements.modoModelo.required = true;
            if (elements.modoManual) elements.modoManual.required = true;

            if (elements.modoModelo?.checked) {
                showSelecaoModelo = true;
                if (elements.modeloSelect) elements.modeloSelect.required = true;
            } else if (elements.modoManual?.checked) {
                showSelecaoManual = true;
            }
        } else if (isEquipe) {
            if (elements.blocoEquipe) elements.blocoEquipe.style.display = 'block';
            if (elements.equipeSelect) elements.equipeSelect.required = true;
            if (elements.blocoOrigemRequisitos) elements.blocoOrigemRequisitos.style.display = 'none';
            if (elements.modoModelo) {
                elements.modoModelo.checked = true;
                elements.modoModelo.disabled = true;
            }
            if (elements.modoManual) elements.modoManual.disabled = true;

            showSelecaoModelo = true;
            if (elements.modeloSelect) elements.modeloSelect.required = true;

            if (elements.equipeSelect?.value && elements.modeloSelect?.value) {
                showAtribuicaoSecoes = true;
                loadSecoesParaAtribuicao();
            } else if (elements.listaSecoes) {
                elements.listaSecoes.innerHTML = CONFIG.MESSAGES.selectTeamAndModel;
            }
        }

        if (elements.blocoSelecaoModelo) elements.blocoSelecaoModelo.style.display = showSelecaoModelo ? 'block' : 'none';
        if (elements.blocoSelecaoManual) elements.blocoSelecaoManual.style.display = showSelecaoManual ? 'block' : 'none';
        if (elements.blocoAtribuicaoSecoes) elements.blocoAtribuicaoSecoes.style.display = showAtribuicaoSecoes ? 'block' : 'none';

        if (!showSelecaoModelo && !isEquipe && elements.modeloSelect) {
            elements.modeloSelect.value = '';
        }
        if (!showSelecaoManual && requisitosCheckboxes.length) {
            requisitosCheckboxes.forEach(cb => (cb.checked = false));
            if (elements.requisitosError) elements.requisitosError.style.display = 'none';
        }
    }

    // Carrega seções e membros via AJAX
    async function loadSecoesParaAtribuicao() {
        if (!elements.modoAtribuirEquipe?.checked || !elements.equipeSelect?.value || !elements.modeloSelect?.value) {
            if (elements.blocoAtribuicaoSecoes) elements.blocoAtribuicaoSecoes.style.display = 'none';
            if (elements.listaSecoes) elements.listaSecoes.innerHTML = CONFIG.MESSAGES.selectTeamAndModel;
            return;
        }

        if (elements.listaSecoes) elements.listaSecoes.innerHTML = '';
        if (elements.loadingSecoes) elements.loadingSecoes.style.display = 'flex';

        try {
            const formData = new FormData();
            formData.append('action', 'get_secoes_e_membros');
            formData.append('equipe_id', elements.equipeSelect.value);
            formData.append('modelo_id', elements.modeloSelect.value);
            formData.append('csrf_token', elements.csrfToken?.value || '');

            const response = await fetch('<?= BASE_URL ?>gestor/auditoria/ajax_handler_auditoria.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();

            if (elements.loadingSecoes) elements.loadingSecoes.style.display = 'none';

            if (data.success) {
                if (data.novo_csrf && elements.csrfToken) {
                    document.querySelectorAll(CONFIG.SELECTORS.csrfToken).forEach(input => (input.value = data.novo_csrf));
                }

                if (!data.secoes?.length) {
                    if (elements.listaSecoes) elements.listaSecoes.innerHTML = CONFIG.MESSAGES.noSections;
                    return;
                }
                if (!data.membros_equipe?.length) {
                    if (elements.listaSecoes) elements.listaSecoes.innerHTML = CONFIG.MESSAGES.noTeamMembers;
                    return;
                }

                let html = '<ul class="list-unstyled mb-0">';
                data.secoes.forEach(secao => {
                    const secaoNomeHtmlId = secao.replace(/[^a-zA-Z0-9_]/g, '_') + '_' + Math.random().toString(36).substring(7);
                    const secaoNomeForm = secao;
                    const secaoDisplay = htmlspecialchars(secao);

                    html += `
                        <li class="mb-2 row align-items-center gx-2">
                            <label for="secao_resp_${secaoNomeHtmlId}" class="col-sm-5 col-md-4 col-form-label col-form-label-sm fw-normal text-truncate" title="${secaoDisplay}">${secaoDisplay}:</label>
                            <div class="col-sm-7 col-md-8">
                                <select name="secao_responsaveis[${htmlspecialchars(secaoNomeForm)}]" id="secao_resp_${secaoNomeHtmlId}" class="form-select form-select-sm">
                                    <option value="">-- Não atribuído --</option>
                                    ${data.membros_equipe.map(membro => `
                                        <option value="${membro.id}" ${secaoRespPost[secaoNomeForm] == membro.id ? 'selected' : ''}>
                                            ${htmlspecialchars(membro.nome)}
                                        </option>
                                    `).join('')}
                                </select>
                            </div>
                        </li>`;
                });
                html += '</ul>';
                if (elements.listaSecoes) elements.listaSecoes.innerHTML = html;
            } else if (elements.listaSecoes) {
                elements.listaSecoes.innerHTML = CONFIG.MESSAGES.ajaxUnknownError.replace('{message}', htmlspecialchars(data.message || 'Erro desconhecido.'));
            }
        } catch (error) {
            if (elements.loadingSecoes) elements.loadingSecoes.style.display = 'none';
            if (elements.listaSecoes) elements.listaSecoes.innerHTML = CONFIG.MESSAGES.ajaxError;
            console.error('Erro AJAX:', error);
        }
    }

    // Valida o formulário
    function validateForm(event) {
        let isValid = true;
        let firstError = null;

        if (elements.atribuicaoError) elements.atribuicaoError.style.display = 'none';
        if (elements.modoCriacaoError) elements.modoCriacaoError.style.display = 'none';
        if (elements.requisitosError) elements.requisitosError.style.display = 'none';
        if (elements.dataFim) elements.dataFim.setCustomValidity('');
        if (elements.dataFimFeedback) elements.dataFimFeedback.style.display = 'none';
        if (elements.dataFim) elements.dataFim.classList.remove('is-invalid');

        if (elements.form && !elements.form.checkValidity()) {
            isValid = false;
            firstError = elements.form.querySelector(':invalid');
        }

        if (!elements.modoAtribuirAuditor?.checked && !elements.modoAtribuirEquipe?.checked) {
            if (elements.atribuicaoError) elements.atribuicaoError.style.display = 'block';
            isValid = false;
            if (!firstError) firstError = elements.modoAtribuirAuditor;
        }

        if (elements.modoAtribuirAuditor?.checked && !elements.modoModelo?.checked && !elements.modoManual?.checked) {
            if (elements.modoCriacaoError) elements.modoCriacaoError.style.display = 'block';
            isValid = false;
            if (!firstError) firstError = elements.modoModelo;
        }

        if (elements.modoAtribuirAuditor?.checked && elements.modoManual?.checked) {
            const anySelected = Array.from(requisitosCheckboxes).some(cb => cb.checked);
            if (!anySelected) {
                if (elements.requisitosError) elements.requisitosError.style.display = 'block';
                isValid = false;
                if (!firstError) firstError = elements.requisitosChecklist;
            }
        }

        if (elements.dataInicio?.value && elements.dataFim?.value) {
            const inicio = new Date(elements.dataInicio.value + 'T00:00:00');
            const fim = new Date(elements.dataFim.value + 'T00:00:00');
            if (!isNaN(inicio.getTime()) && !isNaN(fim.getTime()) && fim < inicio) {
                if (elements.dataFim) elements.dataFim.setCustomValidity(CONFIG.MESSAGES.dateInvalid);
                if (elements.dataFimFeedback) {
                    elements.dataFimFeedback.textContent = CONFIG.MESSAGES.dateInvalid;
                    elements.dataFimFeedback.style.display = 'block';
                }
                if (elements.dataFim) elements.dataFim.classList.add('is-invalid');
                isValid = false;
                if (!firstError) firstError = elements.dataFim;
            }
        }

        if (!isValid && event) {
            event.preventDefault();
            event.stopPropagation();
            if (firstError) {
                setTimeout(() => {
                    firstError.focus();
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            }
        }

        if (elements.form) elements.form.classList.add('was-validated');
    }

    // Filtra requisitos manuais
    const filterRequisitos = debounce(() => {
        if (!elements.filtroRequisitos || !elements.requisitosChecklist) return;

        const termo = elements.filtroRequisitos.value.toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        const categorias = elements.requisitosChecklist.querySelectorAll('.categoria-group');
        const noResultsMessage = elements.requisitosChecklist.querySelector('.no-results-message');
        let anyVisible = false;

        categorias.forEach(cat => {
            const itens = cat.querySelectorAll('.requisito-item');
            let anyItemVisible = false;

            itens.forEach(item => {
                const texto = (item.dataset.texto || '').normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                const visible = termo === '' || texto.includes(termo);
                item.style.display = visible ? '' : 'none';
                if (visible) anyItemVisible = true;
            });

            cat.style.display = anyItemVisible ? '' : 'none';
            if (anyItemVisible) anyVisible = true;
        });

        if (noResultsMessage) noResultsMessage.style.display = anyVisible ? 'none' : 'block';
    }, CONFIG.DEBOUNCE_DELAY);

    // Inicializa o formulário
    function init() {
        if (!elements.form || !elements.modoAtribuirAuditor || !elements.modoAtribuirEquipe) {
            console.error('Elementos essenciais do formulário não encontrados.');
            return;
        }

        if (!elements.modoAtribuirAuditor.checked && !elements.modoAtribuirEquipe.checked) {
            elements.modoAtribuirAuditor.checked = true;
        }

        elements.modoAtribuirAuditor.addEventListener('change', updateFormDisplay);
        elements.modoAtribuirEquipe.addEventListener('change', updateFormDisplay);
        if (elements.modoModelo) elements.modoModelo.addEventListener('change', updateFormDisplay);
        if (elements.modoManual) elements.modoManual.addEventListener('change', updateFormDisplay);
        if (elements.equipeSelect) elements.equipeSelect.addEventListener('change', updateFormDisplay);
        if (elements.modeloSelect) elements.modeloSelect.addEventListener('change', updateFormDisplay);
        elements.form.addEventListener('submit', validateForm);
        if (elements.filtroRequisitos) elements.filtroRequisitos.addEventListener('input', filterRequisitos);

        if (elements.filtroRequisitos?.value) filterRequisitos();
        updateFormDisplay();
    }

    init();
});
</script>

<?php
echo getFooterGestor();
?>