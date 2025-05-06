<?php
// gestor/criar_auditoria.php - Versão COM Atribuição por Auditor/Equipe e Upload de Documentos

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_gestor.php';
require_once __DIR__ . '/../includes/gestor_functions.php';
// Incluir funções de upload
require_once __DIR__ . '/../includes/funcoes_upload.php';

// Proteção da página: Verifica se está logado e se é GESTOR
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// IDs do Gestor e da Empresa logados
$empresa_id = $_SESSION['usuario_empresa_id'];
$gestor_id = $_SESSION['usuario_id'];

// --- Buscar Dados para os Dropdowns e Checklists ---
$modelos = getModelosAtivos($conexao);
$auditores = getAuditoresDaEmpresa($conexao, $empresa_id);
$equipes = getEquipesDaEmpresa($conexao, $empresa_id); // NOVO: Buscar equipes da empresa
// Ajuste para getRequisitosAtivosAgrupados caso o limite ou paginação afete a lista
$requisitos_por_categoria = getRequisitosAtivosAgrupados($conexao); // Pega todos os ativos se a função permitir sem limite/pag padrão
                                                                    // (seu código atual pega 100)

// --- Inicialização de Variáveis (para repopular formulário em caso de erro POST) ---
$titulo = $_POST['titulo'] ?? ''; // Pega do POST se existir, senão vazio
// Use filter_input com valores padrão null para que campos não preenchidos não causem notice
$auditor_id_post = filter_input(INPUT_POST, 'auditor_id', FILTER_VALIDATE_INT);
$equipe_id_post = filter_input(INPUT_POST, 'equipe_id', FILTER_VALIDATE_INT); // NOVO
$modo_atribuicao_post = filter_input(INPUT_POST, 'modo_atribuicao', FILTER_SANITIZE_STRING) ?? 'auditor'; // NOVO, padrão auditor
$escopo = $_POST['escopo'] ?? '';
$objetivo = $_POST['objetivo'] ?? '';
$instrucoes = $_POST['instrucoes'] ?? '';
$data_inicio = $_POST['data_inicio'] ?? '';
$data_fim = $_POST['data_fim'] ?? '';
$modo_criacao = $_POST['modo_criacao'] ?? 'modelo';
$modelo_id_post = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT);
$requisitos_selecionados_post = isset($_POST['requisitos_selecionados']) && is_array($_POST['requisitos_selecionados'])
                                    ? array_filter(array_map('intval', $_POST['requisitos_selecionados']), fn($id) => $id > 0)
                                    : [];

$csrf_token = gerar_csrf_token(); // Gera novo token para o formulário

$erro_msg = obter_flash_message('erro'); // Obter mensagem flash (se houver de POST anterior)

// --- Processamento do Formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Re-valida CSRF e regenera token ANTES de qualquer outra validação POST
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão. Por favor, tente novamente.');
        // Redirecionar para a mesma página após POST com erro
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
     // CSRF válido, regenera token para a PRÓXIMA requisição GET
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $errors = []; // Acumula erros de validação

    // 1. Validação de Campos Gerais
    $titulo = trim(filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_STRING)) ?: '';
    if (empty($titulo)) $errors[] = "Título da auditoria é obrigatório.";

    $escopo = trim(filter_input(INPUT_POST, 'escopo', FILTER_SANITIZE_STRING));
    $objetivo = trim(filter_input(INPUT_POST, 'objetivo', FILTER_SANITIZE_STRING));
    $instrucoes = trim(filter_input(INPUT_POST, 'instrucoes', FILTER_SANITIZE_STRING));

    $data_inicio = filter_input(INPUT_POST, 'data_inicio', FILTER_SANITIZE_STRING);
    $data_fim = filter_input(INPUT_POST, 'data_fim', FILTER_SANITIZE_STRING);

    if (!empty($data_inicio) && !DateTime::createFromFormat('Y-m-d', $data_inicio)) $errors[] = "Data de início planejada inválida (use AAAA-MM-DD).";
    if (!empty($data_fim) && !DateTime::createFromFormat('Y-m-d', $data_fim)) $errors[] = "Data fim planejada inválida (use AAAA-MM-DD).";
    if (!empty($data_inicio) && !empty($data_fim)) {
        // Verifica se as datas são válidas ANTES de comparar
        $inicio_dt = DateTime::createFromFormat('Y-m-d', $data_inicio);
        $fim_dt = DateTime::createFromFormat('Y-m-d', $data_fim);
        if ($inicio_dt && $fim_dt && $fim_dt < $inicio_dt) $errors[] = "Data fim planejada não pode ser anterior à data de início.";
    }

    // 2. Validação de Origem dos Requisitos (Modelo vs Manual)
    $modo_criacao = filter_input(INPUT_POST, 'modo_criacao', FILTER_SANITIZE_STRING); // Já inicializado antes
    $modelo_id = null;
    $requisitos_para_criar_itens = []; // IDs dos requisitos a serem usados na auditoria

    if ($modo_criacao === 'modelo') {
        $modelo_id = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT) ?: null;
        if (empty($modelo_id)) $errors[] = "Selecione um modelo válido.";
        // (A função criarAuditoria buscará os requisitos do modelo)
    } elseif ($modo_criacao === 'manual') {
        $requisitos_selecionados_post = isset($_POST['requisitos_selecionados']) && is_array($_POST['requisitos_selecionados'])
                                            ? array_filter(array_map('intval', $_POST['requisitos_selecionados']), fn($id) => $id > 0)
                                            : [];
        if (empty($requisitos_selecionados_post)) {
            $errors[] = "Selecione ao menos um requisito.";
        } else {
             $requisitos_para_criar_itens = $requisitos_selecionados_post; // Usar na criação se manual
        }
    } else {
         $errors[] = "Modo de criação de requisitos inválido.";
    }


    // 3. Validação de Atribuição (Auditor vs Equipe) - NOVO
    $modo_atribuicao = filter_input(INPUT_POST, 'modo_atribuicao', FILTER_SANITIZE_STRING) ?? 'auditor'; // Default se não vier
    $auditor_id = null; // Virá null ou o ID selecionado
    $equipe_id = null;  // Virá null ou o ID selecionado

    if ($modo_atribuicao === 'auditor') {
        $auditor_id = filter_input(INPUT_POST, 'auditor_id', FILTER_VALIDATE_INT) ?: null;
        // Validar se o auditor_id selecionado existe e pertence à empresa do gestor (Opcional, mas bom)
        // Poderia chamar getAuditoresDaEmpresa() e verificar se o ID está no array.
    } elseif ($modo_atribuicao === 'equipe') {
        $equipe_id = filter_input(INPUT_POST, 'equipe_id', FILTER_VALIDATE_INT) ?: null;
        // Validar se a equipe_id selecionada existe e pertence à empresa do gestor (Opcional)
        // Poderia chamar getEquipesDaEmpresa() e verificar se o ID está no array.
    } else {
         $errors[] = "Modo de atribuição inválido.";
    }

    // Se um modo de atribuição válido foi selecionado, verificar se um auditor/equipe foi escolhido nesse modo
     if (($modo_atribuicao === 'auditor' && empty($auditor_id)) || ($modo_atribuicao === 'equipe' && empty($equipe_id))) {
          $errors[] = "Selecione um Auditor ou uma Equipe para atribuir a auditoria.";
     }


    // 4. Processamento de Upload de Documentos - NOVO
    // Salva os arquivos em um diretório temporário primeiro, ANTES da transação DB.
    // A função de upload deve lidar com validações de tamanho/tipo por arquivo e retornar metadados
    $documentos_upload_info = ['success' => true, 'message' => '', 'files' => []]; // Inicializa
    $arquivos_upload_processados = []; // Lista apenas dos arquivos válidos salvos temp

    // Verifica se o campo 'documentos' foi enviado e se contém arquivos (desconsidera campos vazios)
    if (isset($_FILES['documentos']) && !empty(array_filter($_FILES['documentos']['name']))) {
        $temp_upload_dir = 'uploads/temp/' . session_id() . '_' . time() . '/'; // Diretório temporário por sessão+timestamp
        $documentos_upload_info = processarDocumentUploads($_FILES['documentos'], $temp_upload_dir); // Função deve salvar em $temp_upload_dir

        if (!$documentos_upload_info['success']) {
             // Erros de upload devem ser críticos para a criação? Sim, se o documento é essencial.
             // Se sim, adiciona o erro e ABORTA a criação.
             // Se não, pode apenas logar e continuar sem os arquivos que falharam.
             // Vamos tratar como crítico por agora.
            $errors[] = "Falha(s) no upload dos documentos: " . $documentos_upload_info['message'];
             // Importante: Se houver arquivos processados com sucesso (mas outros falharam, e add_errors inclui msg),
             // eles AINDA estarão em $documentos_upload_info['files'].
             // Precisamos limpar esses arquivos TEMPORÁRIOS se a criação da auditoria falhar globalmente.
             $arquivos_upload_processados = $documentos_upload_info['files']; // Armazena os que deram "certo" no upload inicial
             error_log("Upload de documentos falhou ou parcial. Info: " . json_encode($documentos_upload_info));
        } else {
            // Todos os uploads individuais foram processados com sucesso e salvos em temp.
            $arquivos_upload_processados = $documentos_upload_info['files']; // Lista de arquivos salvos temporariamente
            // Opcional: Registrar sucesso parcial ou total dos uploads
             // dbRegistrarLogAcesso($gestor_id, $_SERVER['REMOTE_ADDR'], 'auditoria_doc_upload_temp_sucesso', 1, "Arquivos temp ok. Qtd: " . count($arquivos_upload_processados), $conexao);
        }
    }
     // Se não foram enviados arquivos, $arquivos_upload_processados fica vazio, o que é ok.


    // 5. Processar Criação da Auditoria no DB se não houver erros de validação/upload
    $novaAuditoriaId = null;
    if (empty($errors)) {
        $dadosAuditoriaDB = [
            'titulo' => $titulo,
            'empresa_id' => $empresa_id,
            'gestor_id' => $gestor_id,
            'auditor_id' => $auditor_id, // Passa o ID selecionado ou null
            'equipe_id' => $equipe_id,   // Passa o ID selecionado ou null
            'escopo' => $escopo,
            'objetivo' => $objetivo,
            'instrucoes' => $instrucoes,
            'data_inicio' => empty($data_inicio) ? null : $data_inicio,
            'data_fim' => empty($data_fim) ? null : $data_fim,
            'modelo_id' => $modelo_id, // Passa o ID do modelo ou null
            'requisitos_selecionados' => $requisitos_para_criar_itens // Passa IDs se manual, senão array vazio
        ];

        // NOVO: Chamar a função criarAuditoria com os dados dos uploads
        // A função agora é responsável por MOVER os arquivos de TEMP para o local FINAL
        // DENTRO da transação e inserir no DB, e limpar no ROLLBACK.
        $novaAuditoriaId = criarAuditoria($conexao, $dadosAuditoriaDB, $arquivos_upload_processados);


        if ($novaAuditoriaId) {
            // Criação de Auditoria e Documentos (se houver) SUCEDIDA
            definir_flash_message('sucesso', "Auditoria '" . htmlspecialchars($titulo) . "' planejada com sucesso (ID: $novaAuditoriaId).");
             dbRegistrarLogAcesso($gestor_id, $_SERVER['REMOTE_ADDR'], 'criar_auditoria_sucesso', 1, "Auditoria criada ID: $novaAuditoriaId. Título: $titulo", $conexao);
            header('Location: ' . BASE_URL . 'gestor/minhas_auditorias.php');
            exit;
        } else {
            // Erro na função criarAuditoria (já logado internamente e rollback)
            $errors[] = "Erro ao salvar a auditoria no banco de dados ou processar documentos. Verifique os logs para mais detalhes.";
            dbRegistrarLogAcesso($gestor_id, $_SERVER['REMOTE_ADDR'], 'criar_auditoria_falha_db', 0, "Erro interno DB/Docs.", $conexao);
            // O rollback da transação e a limpeza dos arquivos temporários devem ter sido tratados na função criarAuditoria.
        }
    } // Fim if empty($errors)

    // Se chegou aqui, houve erros. Configura a mensagem flash de erro.
    if (!empty($errors)) {
        definir_flash_message('erro', "<strong>Não foi possível criar a auditoria:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>");

        // Importante: Limpar os arquivos temporários que foram salvos ANTES do erro de validação/DB
        if (!empty($arquivos_upload_processados)) {
             // Assumindo que caminho_temp é o full path para o arquivo temporário
             foreach ($arquivos_upload_processados as $doc) {
                 if (file_exists($doc['caminho_temp'])) {
                     @unlink($doc['caminho_temp']); // Limpa arquivo temp, ignora falhas
                 }
             }
             // O diretório temporário (ex: uploads/temp/sessao_timestamp/) também deveria ser limpo
             // Mas isso é mais complexo (precisaria saber o path base) e talvez seja feito por um script de limpeza periódica do sistema
             // ou garantido que processarDocumentUploads sempre use um subdiretório específico que pode ser excluído se a requisição falhar tarde.
            error_log("Arquivos temporários de upload removidos após erro na criação da auditoria.");
        }

        // Redireciona para a própria página para mostrar os erros flash e repopular formulário
        // Note: Os dados POSTados para campos de texto serão mantidos na inicialização das variáveis $titulo, $escopo, etc.
        // Os arquivos upload NUNCA são repopulados automaticamente pelo navegador em caso de POST.
         // Os checkboxes de requisitos manuais ($requisitos_selecionados_post) são repopulados.
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
} // Fim do if POST

// --- Geração do HTML ---
// As variáveis de repopulação ($titulo, $auditor_id_post, $modo_atribuicao_post, etc) são usadas aqui
$title = "Criar Nova Auditoria";
echo getHeaderGestor($title);
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-plus-circle me-2 text-primary"></i>Criar Nova Auditoria</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb"><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/minhas_auditorias.php">Auditorias</a></li><li class="breadcrumb-item active" aria-current="page">Criar</li></ol></nav>
    </div>
    <a href="<?= BASE_URL ?>gestor/minhas_auditorias.php" class="btn btn-outline-secondary rounded-pill px-3 action-button-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar para Auditorias</a>
</div>

<?php if ($erro = obter_flash_message('erro')): ?>
    <div class="alert alert-danger gestor-alert fade show" role="alert">
        <i class="fas fa-exclamation-triangle flex-shrink-0 me-2"></i>
        <div><?= $erro ?></div> <?php /* Mensagem já formatada */ ?>
        <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($sucesso = obter_flash_message('sucesso')): ?>
    <div class="alert alert-success gestor-alert fade show" role="alert">
        <i class="fas fa-check-circle flex-shrink-0 me-2"></i>
        <div><?= $sucesso ?></div>
        <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>


<form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="formCriarAuditoria" class="needs-validation" novalidate enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2">
            <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary opacity-75"></i>Informações Gerais</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-12">
                    <label for="titulo" class="form-label form-label-sm fw-semibold">Título da Auditoria <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="titulo" name="titulo" value="<?= htmlspecialchars($titulo) ?>" required placeholder="Ex: Auditoria Interna ISO 27001 - TI - Q3 2025">
                    <div class="invalid-feedback">Título obrigatório.</div>
                </div>

                <?php /* NOVO: Atribuição por Auditor ou Equipe */ ?>
                <div class="col-12 border-top pt-3 mt-3"> <?php /* Separador visual */ ?>
                     <label class="form-label form-label-sm fw-semibold mb-2 d-block">Atribuir a: <span class="text-danger">*</span></label>
                     <div class="d-flex align-items-center">
                         <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="modo_atribuicao" id="modoAtribuirAuditor" value="auditor" <?= ($modo_atribuicao_post === 'auditor') ? 'checked' : '' ?> required>
                            <label class="form-check-label small" for="modoAtribuirAuditor">Auditor Individual</label>
                         </div>
                         <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="modo_atribuicao" id="modoAtribuirEquipe" value="equipe" <?= ($modo_atribuicao_post === 'equipe') ? 'checked' : '' ?> required>
                            <label class="form-check-label small" for="modoAtribuirEquipe">Equipe</label>
                         </div>
                         <div class="invalid-feedback d-none d-sm-block">Selecione um modo de atribuição.</div> <?php /* Feedback Bootstrap */ ?>
                    </div>
                </div>

                <div class="col-md-6" id="atribuirAuditorDiv">
                    <label for="auditor_id" class="form-label form-label-sm fw-semibold">Auditor Responsável <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="auditor_id" name="auditor_id" <?= ($modo_atribuicao_post === 'auditor') ? 'required' : '' ?>>
                        <option value="">-- Selecione um Auditor --</option>
                        <?php if (!empty($auditores)): ?>
                            <?php foreach ($auditores as $auditor): ?>
                                <option value="<?= $auditor['id'] ?>" <?= ($auditor_id_post == $auditor['id']) ? 'selected' : '' ?>><?= htmlspecialchars($auditor['nome']) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Nenhum auditor ativo na sua empresa</option>
                        <?php endif; ?>
                    </select>
                    <div class="invalid-feedback">Selecione um auditor.</div>
                    <small class="form-text text-muted">Se a auditoria for atribuída a uma equipe, este campo será ignorado.</small>
                </div>

                 <div class="col-md-6" id="atribuirEquipeDiv">
                    <label for="equipe_id" class="form-label form-label-sm fw-semibold">Equipe Designada <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="equipe_id" name="equipe_id" <?= ($modo_atribuicao_post === 'equipe') ? 'required' : '' ?>>
                        <option value="">-- Selecione uma Equipe --</option>
                        <?php if (!empty($equipes)): ?>
                            <?php foreach ($equipes as $equipe): ?>
                                <option value="<?= $equipe['id'] ?>" <?= ($equipe_id_post == $equipe['id']) ? 'selected' : '' ?>><?= htmlspecialchars($equipe['nome']) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Nenhuma equipe ativa na sua empresa</option>
                        <?php endif; ?>
                    </select>
                     <div class="invalid-feedback">Selecione uma equipe.</div>
                    <small class="form-text text-muted">Membros da equipe designada poderão participar.</small>
                </div>
                 <?php /* FIM NOVO: Atribuição */ ?>


                <div class="col-md-3 mt-3 pt-3 border-top"> <?php /* Mantido, talvez reposicionado */ ?>
                    <label for="data_inicio" class="form-label form-label-sm fw-semibold">Início Planejado</label>
                    <input type="date" class="form-control form-control-sm" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
                    <div class="invalid-feedback">Data de início inválida.</div>
                </div>
                <div class="col-md-3 mt-3 pt-3 border-top"> <?php /* Mantido, talvez reposicionado */ ?>
                    <label for="data_fim" class="form-label form-label-sm fw-semibold">Fim Planejado</label>
                    <input type="date" class="form-control form-control-sm" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
                    <div class="invalid-feedback">Data fim inválida ou anterior à data de início.</div>
                </div>
                <div class="col-12"> <?php /* Sem separador aqui para não ter 2 bordas seguidas */ ?>
                    <label for="escopo" class="form-label form-label-sm fw-semibold">Escopo</label>
                    <textarea class="form-control form-control-sm" id="escopo" name="escopo" rows="2" placeholder="Descreva o que será auditado..."><?= htmlspecialchars($escopo) ?></textarea>
                </div>
                <div class="col-12">
                    <label for="objetivo" class="form-label form-label-sm fw-semibold">Objetivo</label>
                    <textarea class="form-control form-control-sm" id="objetivo" name="objetivo" rows="2" placeholder="Descreva o objetivo principal..."><?= htmlspecialchars($objetivo) ?></textarea>
                </div>
                <div class="col-12">
                    <label for="instrucoes" class="form-label form-label-sm fw-semibold">Instruções para Auditor(es)</label> <?php /* Texto ajustado */ ?>
                    <textarea class="form-control form-control-sm" id="instrucoes" name="instrucoes" rows="2" placeholder="Instruções ou informações adicionais..."><?= htmlspecialchars($instrucoes) ?></textarea>
                </div>

                <?php /* NOVO: Campo para Upload de Documentos de Planejamento */ ?>
                 <div class="col-12 border-top pt-3 mt-3">
                     <label for="documentos" class="form-label form-label-sm fw-semibold mb-2">Documentos de Planejamento (Opcional)</label>
                     <input type="file" class="form-control form-control-sm" id="documentos" name="documentos[]" multiple accept=".pdf, .xlsx, .xls, .docx, .doc, .jpg, .jpeg, .png, .gif">
                     <div class="invalid-feedback">Arquivo(s) inválido(s) ou muito grande(s).</div> <?php /* Validação Bootstrap */ ?>
                     <small class="form-text text-muted">Anexe documentos relevantes para a auditoria (Escopo detalhado, normas de referência, políticas internas, etc.). Tipos permitidos: PDF, Planilhas, Word, Imagens. Tamanho máximo por arquivo: 10MB.</small>
                 </div>
                 <?php /* FIM NOVO: Upload */ ?>

            </div> <?php /* Fim row g-3 */ ?>
        </div> <?php /* Fim card-body */ ?>
    </div> <?php /* Fim card */ ?>

    <div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2">
            <h6 class="mb-0 fw-bold"><i class="fas fa-tasks me-2 text-primary opacity-75"></i>Itens da Auditoria</h6>
        </div>
        <div class="card-body p-4">
            <div class="mb-3 border-bottom pb-3">
                <label class="form-label form-label-sm fw-semibold">Origem dos Requisitos: <span class="text-danger">*</span></label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="modo_criacao" id="modoModelo" value="modelo" <?= ($modo_criacao === 'modelo') ? 'checked' : '' ?> required>
                    <label class="form-check-label small" for="modoModelo">Usar Modelo Pré-definido</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="modo_criacao" id="modoManual" value="manual" <?= ($modo_criacao === 'manual') ? 'checked' : '' ?> required>
                    <label class="form-check-label small" for="modoManual">Selecionar Manualmente</label>
                </div>
                 <div class="invalid-feedback d-none d-sm-block">Selecione um modo de criação.</div> <?php /* Feedback Bootstrap */ ?>
            </div>

            <div id="selecaoModeloDiv" class="mb-3 <?= ($modo_criacao === 'manual') ? 'd-none' : '' ?>">
                <label for="modelo_id" class="form-label form-label-sm fw-semibold">Modelo Base <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" id="modelo_id" name="modelo_id" <?= ($modo_criacao === 'modelo') ? 'required' : '' ?>>
                    <option value="" <?= empty($modelo_id_post) ? 'selected' : '' ?>>-- Selecione um Modelo --</option>
                    <?php if (empty($modelos)): ?>
                        <option value="" disabled>Nenhum modelo ativo cadastrado</option>
                    <?php else: ?>
                        <?php foreach ($modelos as $modelo): ?>
                            <option value="<?= $modelo['id'] ?>" <?= ($modelo_id_post == $modelo['id']) ? 'selected' : '' ?>><?= htmlspecialchars($modelo['nome']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <div class="invalid-feedback">Selecione um modelo.</div>
                <small class="form-text text-muted">Os requisitos deste modelo serão adicionados à auditoria.</small>
            </div>

            <div id="selecaoManualDiv" class="mb-3 <?= ($modo_criacao === 'modelo') ? 'd-none' : '' ?>">
                <label class="form-label form-label-sm fw-semibold">Requisitos Disponíveis <span class="text-danger">*</span></label>
                <p class="text-muted small mb-2">Marque os requisitos a serem incluídos nesta auditoria.</p>
                <input type="search" id="filtroRequisitos" class="form-control form-control-sm mb-2" placeholder="Filtrar requisitos por nome ou código..." value=""> <?php /* Valor inicial vazio para não forçar filtro no load */ ?>
                <div id="requisitosChecklist" class="requisitos-checklist-container border rounded p-3 bg-light-subtle" style="max-height: 450px; overflow-y: auto;">
                    <?php if (empty($requisitos_por_categoria)): ?>
                        <p class="text-danger small m-0">Nenhum requisito ativo encontrado. Cadastre requisitos na área de administração.</p>
                    <?php else: ?>
                        <?php foreach ($requisitos_por_categoria as $categoria => $requisitos): ?>
                            <fieldset class="mb-3 categoria-group">
                                <legend class="h6 small fw-bold text-secondary border-bottom pb-1 mb-2 sticky-top bg-light-subtle py-1"><?= htmlspecialchars($categoria) ?></legend>
                                <?php foreach ($requisitos as $req): ?>
                                    <div class="form-check requisito-item" data-texto="<?= htmlspecialchars(strtolower(($req['codigo'] ?? '') . ' ' . $req['nome'] . ' ' . ($req['categoria'] ?? '') . ' ' . ($req['norma_referencia'] ?? ''))) ?>"> <?php /* Busca em mais campos */ ?>
                                        <input class="form-check-input" type="checkbox" name="requisitos_selecionados[]" value="<?= $req['id'] ?>" id="req_<?= $req['id'] ?>" <?= (in_array($req['id'], $requisitos_selecionados_post)) ? 'checked' : '' ?>> <?php /* Usa variável _post */ ?>
                                        <label class="form-check-label small" for="req_<?= $req['id'] ?>">
                                            <strong title="<?= htmlspecialchars($req['nome']) ?>"><?= htmlspecialchars($req['codigo'] ?: 'ID ' . $req['id']) ?>:</strong> <?= htmlspecialchars($req['nome']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>
                        <p class="text-center text-muted small mt-3 no-results-message" style="display: none;">Nenhum requisito encontrado para o filtro.</p>
                    <?php endif; ?>
                </div>
                 <?php /* invalid-feedback for checklist */ ?>
                <div class="invalid-feedback d-block" id="requisitosError" style="<?= ($modo_criacao === 'manual' && empty($requisitos_selecionados_post)) ? 'display: block;' : 'display: none;' ?>">Selecione ao menos um requisito.</div>
            </div>
        </div>
    </div>

    <div class="mt-4 mb-5 d-flex justify-content-end">
        <a href="<?= BASE_URL ?>gestor/minhas_auditorias.php" class="btn btn-secondary rounded-pill px-4 me-2">Cancelar</a>
        <button type="submit" class="btn btn-success rounded-pill px-4 action-button-main">
            <i class="fas fa-save me-1"></i> Criar Auditoria
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formCriarAuditoria');

    // --- Lógica de Origem dos Requisitos ---
    const modoModeloRadio = document.getElementById('modoModelo');
    const modoManualRadio = document.getElementById('modoManual');
    const selecaoModeloDiv = document.getElementById('selecaoModeloDiv');
    const selecaoManualDiv = document.getElementById('selecaoManualDiv');
    const modeloSelect = document.getElementById('modelo_id');
    const requisitosContainer = document.getElementById('requisitosChecklist');
    // IMPORTANT: Get the checkboxes AFTER the DOM is loaded, and handle case where container/checkboxes don't exist
    let requisitosCheckboxes = [];
    if(requisitosContainer) {
       requisitosCheckboxes = requisitosContainer.querySelectorAll('.requisito-item input[type="checkbox"]');
    }
    const requisitosError = document.getElementById('requisitosError');
    const filtroReqManualInput = document.getElementById('filtroRequisitos'); // Renomeado para evitar conflito
    const noResultsMessage = requisitosContainer?.querySelector('.no-results-message');


    // --- Lógica de Atribuição (Auditor/Equipe) - NOVO ---
    const modoAtribuirAuditorRadio = document.getElementById('modoAtribuirAuditor');
    const modoAtribuirEquipeRadio = document.getElementById('modoAtribuirEquipe');
    const atribuirAuditorDiv = document.getElementById('atribuirAuditorDiv');
    const atribuirEquipeDiv = document.getElementById('atribuirEquipeDiv');
    const auditorSelect = document.getElementById('auditor_id');
    const equipeSelect = document.getElementById('equipe_id');

    function toggleCamposOrigem() {
        if (!modoModeloRadio || !modoManualRadio || !selecaoModeloDiv || !selecaoManualDiv) return;
        if (modoModeloRadio.checked) {
            selecaoModeloDiv.classList.remove('d-none');
            selecaoManualDiv.classList.add('d-none');
            if (modeloSelect) modeloSelect.required = true;
             // Remover required dos checkboxes se for modo modelo
             requisitosCheckboxes.forEach(cb => cb.removeAttribute('required')); // Checkboxes não têm required normal
             if (requisitosError) requisitosError.style.display = 'none';

        } else { // modoManualRadio.checked
            selecaoModeloDiv.classList.add('d-none');
            selecaoManualDiv.classList.remove('d-none');
            if (modeloSelect) {
                 modeloSelect.required = false;
                 modeloSelect.value = ''; // Limpa o valor do dropdown modelo
                 modeloSelect.classList.remove('is-invalid'); // Limpa visual
            }
            // A validação manual de checkboxes agora é tratada no evento submit do form
             // e na função toggleCamposOrigem pelo estilo display do requisitosError.

        }
         // Certifica que a validação de pelo menos um checkbox só é "ativada" quando o modo manual está visível
         // Adiciona/remove um atributo data para o submit handler verificar
        if (selecaoManualDiv) {
             selecaoManualDiv.dataset.visible = (modoManualRadio.checked ? 'true' : 'false');
        }
    }

     // NOVO: Lógica para alternar campos de atribuição
    function toggleCamposAtribuicao() {
         if (!modoAtribuirAuditorRadio || !modoAtribuirEquipeRadio || !atribuirAuditorDiv || !atribuirEquipeDiv) return;
        if (modoAtribuirAuditorRadio.checked) {
             atribuirAuditorDiv.classList.remove('d-none');
             atribuirEquipeDiv.classList.add('d-none');
            if (auditorSelect) auditorSelect.required = true;
            if (equipeSelect) {
                 equipeSelect.required = false;
                 equipeSelect.value = ''; // Limpa valor da equipe
                 equipeSelect.classList.remove('is-invalid'); // Limpa visual
            }
        } else { // modoAtribuirEquipeRadio.checked
             atribuirAuditorDiv.classList.add('d-none');
             atribuirEquipeDiv.classList.remove('d-none');
            if (auditorSelect) {
                 auditorSelect.required = false;
                 auditorSelect.value = ''; // Limpa valor do auditor
                 auditorSelect.classList.remove('is-invalid'); // Limpa visual
            }
            if (equipeSelect) equipeSelect.required = true;
        }
         // Assegura que pelo menos um modo esteja selecionado, e se um modo está visível,
         // que seu respectivo select tenha o required aplicado. A validação final de SELEÇÃO
         // (se o valor 'vazio' está selecionado no required) será no submit handler e nativa do Bootstrap.
    }


    // Inicializar os estados corretos ao carregar a página (com base nos valores de repopulação se houver erro POST)
    if (modoModeloRadio) modoModeloRadio.addEventListener('change', toggleCamposOrigem);
    if (modoManualRadio) modoManualRadio.addEventListener('change', toggleCamposOrigem);
    toggleCamposOrigem(); // Executa ao carregar

     // NOVO: Inicializar lógica de atribuição
     if (modoAtribuirAuditorRadio) modoAtribuirAuditorRadio.addEventListener('change', toggleCamposAtribuicao);
     if (modoAtribuirEquipeRadio) modoAtribuirEquipeRadio.addEventListener('change', toggleCamposAtribuicao);
     toggleCamposAtribuicao(); // Executa ao carregar


    // --- Lógica de Validação no Submit do Formulário ---
    if (form) {
        form.addEventListener('submit', event => {
            let formValido = true; // Flag geral
            let manualRequisitosValido = true; // Para o modo manual
            let datasValidas = true; // Para datas
            let atribuicaoValida = true; // NOVO: Para atribuição

            // 1. Validação Nativa do Bootstrap (campos com 'required')
            if (!form.checkValidity()) {
                formValido = false;
            }

            // 2. Validação Customizada: Ordem das Datas
            const dataInicioInput = document.getElementById('data_inicio');
            const dataFimInput = document.getElementById('data_fim');
            const dataInicio = dataInicioInput.value;
            const dataFim = dataFimInput.value;
             // Limpa validação customizada prévia em caso de submissão repetida
            if (dataFimInput) { dataFimInput.setCustomValidity(""); }

            if (dataInicio && dataFim) {
                try { // Usar try/catch para novas Date() para lidar com formatos inválidos caso a validação input type=date falhe
                    const inicio = new Date(dataInicio);
                    const fim = new Date(dataFim);
                    if (fim < inicio) {
                        datasValidas = false;
                        if (dataFimInput) {
                            dataFimInput.setCustomValidity("Data fim não pode ser anterior à data de início.");
                            dataFimInput.classList.add('is-invalid'); // Garante classe visual
                        }
                         formValido = false; // Invalida formulário geral
                    } else {
                        if (dataFimInput) { dataFimInput.classList.remove('is-invalid'); } // Remove classe se validado
                    }
                } catch(e) {
                    // Se Date() falhou, já deveria ter sido pego pela validação required/type=date do Bootstrap, mas garante
                    datasValidas = false;
                    if (dataFimInput) {
                         dataFimInput.setCustomValidity("Data inválida."); // Ou erro mais específico
                         dataFimInput.classList.add('is-invalid');
                    }
                    formValido = false;
                    console.error("Erro JS ao parsear datas:", e);
                }
            }


            // 3. Validação Customizada: Requisitos Selecionados (Modo Manual)
            if (selecaoManualDiv && selecaoManualDiv.dataset.visible === 'true') {
                let algumSelecionado = false;
                 if (requisitosCheckboxes) { // Check if checkboxes list exists
                    requisitosCheckboxes.forEach(cb => { if (cb.checked) algumSelecionado = true; });
                 }

                if (!algumSelecionado) {
                    manualRequisitosValido = false;
                    if (requisitosError) requisitosError.style.display = 'block'; // Mostra mensagem de erro customizada
                    formValido = false; // Invalida formulário geral
                     // Não foca automaticamente, mas pode sugerir scroll
                     // requisitosContainer?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    if (requisitosError) requisitosError.style.display = 'none'; // Esconde
                }
            } else {
                if (requisitosError) requisitosError.style.display = 'none'; // Esconde se não está no modo manual
            }

            // 4. Validação Customizada: Atribuição (Auditor/Equipe) - NOVO
             // A validação `required` nos dropdowns já garante que um item SELECIONADO
             // (não a opção padrão "Selecione...") será validado NATIVAMENTE pelo Bootstrap
             // QUANDO aquele dropdown estiver visível/required.
             // A validação `required` nos radio buttons garante que pelo menos um MODO seja selecionado.
             // Portanto, a validação nativa já cobre a necessidade de um ser selecionado.
             // Podemos adicionar aqui uma verificação extra apenas para clareza no JS:
             const auditorSelected = auditorSelect && auditorSelect.value !== "";
             const equipeSelected = equipeSelect && equipeSelect.value !== "";

             if (modoAtribuirAuditorRadio.checked && !auditorSelected) {
                  // Invalido via JS, se o required nativo falhou (garante mensagem customizada se necessário)
                  if(auditorSelect) auditorSelect.setCustomValidity("Selecione um Auditor.");
                  atribuicaoValida = false;
                  formValido = false; // Já coberto pelo checkValidity, mas redundância não faz mal
             } else {
                   if(auditorSelect) auditorSelect.setCustomValidity(""); // Reseta validade customizada
             }

              if (modoAtribuirEquipeRadio.checked && !equipeSelected) {
                  // Invalido via JS
                  if(equipeSelect) equipeSelect.setCustomValidity("Selecione uma Equipe.");
                  atribuicaoValida = false;
                   formValido = false; // Já coberto pelo checkValidity
             } else {
                  if(equipeSelect) equipeSelect.setCustomValidity(""); // Reseta validade customizada
             }


            // Impedir envio se alguma validação falhou
            if (!formValido) {
                event.preventDefault();
                event.stopPropagation();
            }

            // Adicionar classe de validação após as checagens, mesmo que customizadas
            form.classList.add('was-validated');
        }, false); // Fim do submit listener


        // Lógica para esconder/mostrar requisitosError quando checkboxes mudam no MODO MANUAL
        // Garante que a mensagem de erro desaparece assim que um item é marcado.
        if (requisitosCheckboxes && requisitosError) {
            requisitosCheckboxes.forEach(cb => {
                cb.addEventListener('change', () => {
                    // Verificar se o modo manual está ATIVO e se algum checkbox está marcado
                    if (modoManualRadio && modoManualRadio.checked) {
                        let algumSelecionado = false;
                        // Re-iterar sobre todos os checkboxes para verificar o estado atual
                        requisitosCheckboxes.forEach(innerCb => { if (innerCb.checked) algumSelecionado = true; });
                        // Esconder a mensagem de erro customizada se algum estiver marcado
                        if (algumSelecionado) {
                            requisitosError.style.display = 'none';
                        }
                         // Não precisamos adicionar a classe is-invalid nos checkboxes individualmente
                         // A validação Bootstrap já faz isso em alguns casos com required (se usarmos ele)
                         // A mensagem requisitosError já serve como feedback para o grupo.
                    }
                });
            });
        }
    } // Fim if form


    // --- Lógica de Filtro de Requisitos (Checklist Manual) ---
    if (filtroReqManualInput && requisitosContainer) {
        const todosItens = requisitosContainer.querySelectorAll('.requisito-item');
        const todasCategorias = requisitosContainer.querySelectorAll('.categoria-group');

        filtroReqManualInput.addEventListener('input', function() {
            const termo = this.value.toLowerCase().trim();
            let algumVisivelNaListaTotal = false; // Para mostrar a mensagem "Nenhum encontrado"

            todasCategorias.forEach(cat => {
                const itensNaCategoria = cat.querySelectorAll('.requisito-item');
                let algumVisivelNestaCategoria = false; // Para esconder/mostrar a legend/fieldset

                itensNaCategoria.forEach(item => {
                    const textoItem = item.dataset.texto || '';
                    // Normalize para comparar sem acentos, etc.
                    const visivel = termo === '' || textoItem.normalize("NFD").replace(/[\u0300-\u036f]/g, "").includes(termo.normalize("NFD").replace(/[\u0300-\u036f]/g, ""));
                    item.style.display = visivel ? '' : 'none';
                    if (visivel) {
                         algumVisivelNestaCategoria = true;
                         algumVisivelNaListaTotal = true;
                    }
                });

                 // Esconde/mostra o fieldset da categoria
                cat.style.display = algumVisivelNestaCategoria ? '' : 'none';
                // Opcional: pode esconder a legend também se todos os itens da categoria estão ocultos,
                // mas esconder o fieldset todo já resolve visualmente.

            }); // Fim forEach categorias

            // Mostra a mensagem "Nenhum requisito encontrado" se nada ficou visível na lista total
            if (noResultsMessage) {
                noResultsMessage.style.display = algumVisivelNaListaTotal ? 'none' : 'block';
            }
        });

        // Se havia um filtro prévio (vindo do POST de erro), aplica-o ao carregar
        if (filtroReqManualInput.value) {
            filtroReqManualInput.dispatchEvent(new Event('input'));
        }
    }


    // --- Lógica para Validação do Campo de Upload (Opcional - Extra nativa) ---
    // Embora o input type="file" não tenha required nativo para SELECIONAR um arquivo,
    // podemos adicionar validação via JS se quisermos forçar algum upload.
    // No seu caso, é Opcional, então a validação nativa via "accept" e limite de tamanho (implementada na função PHP) já é suficiente.
    // Se quisesse FORÇAR um ou mais arquivos, precisaria adicionar uma classe customizada como needs-file-upload e validar no submit.
});
</script>

<?php
echo getFooterGestor();
?>