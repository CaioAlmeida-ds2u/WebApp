<?php
// admin/modelo/editar_modelo.php - Página para Gerenciar Itens de um Modelo

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_admin.php';   // Layout Admin
require_once __DIR__ . '/../../includes/admin_functions.php'; // Funções CRUD modelo/requisito/item

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php'); exit;
}

// --- Obter ID do Modelo ---
$modelo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$modelo_id) {
    definir_flash_message('erro', "ID de modelo inválido.");
    header('Location: ' . BASE_URL . 'admin/modelo/modelo_index.php'); exit;
}

// --- Carregar Dados do Modelo ---
$modelo = getModeloAuditoria($conexao, $modelo_id);
if (!$modelo) {
    definir_flash_message('erro', "Modelo com ID $modelo_id não encontrado.");
    header('Location: ' . BASE_URL . 'admin/modelo/modelo_index.php'); exit;
}

// --- Lógica de Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// --- Processamento de Ações POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        $erro_msg = "Erro de validação de segurança. Tente novamente."; // Define erro local
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_modelo_csrf_fail', 0, "ID Modelo: $modelo_id", $conexao);
    } else {
        $action = $_POST['action'] ?? '';
        $csrf_token = gerar_csrf_token(); // Regenera token logo após validação POST
        $_SESSION['csrf_token'] = $csrf_token; // Salva novo token na sessão

        switch ($action) {
            // ----- MODIFICADO: Case para adicionar múltiplos itens -----
            case 'adicionar_item':
                // Recupera o ARRAY de IDs de requisitos
                $requisitos_ids_add = filter_input(INPUT_POST, 'requisitos_ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
                $secao_add = trim(filter_input(INPUT_POST, 'secao_add', FILTER_SANITIZE_SPECIAL_CHARS)) ?: null;

                // Valida se o array foi recebido e não está vazio
                if ($requisitos_ids_add && is_array($requisitos_ids_add) && !empty($requisitos_ids_add)) {
                    $adicionados_sucesso = 0;
                    $erros_adicionar = [];
                    $ids_processados_validos = []; // Armazena apenas os IDs válidos que tentaremos adicionar

                    foreach ($requisitos_ids_add as $req_id_raw) {
                        // Validar cada ID individualmente
                        $req_id = filter_var($req_id_raw, FILTER_VALIDATE_INT);
                        if ($req_id === false || $req_id <= 0) {
                            // Registra apenas o ID bruto inválido se precisar depurar, mas não adiciona ao array de erros principal
                            // Você pode logar isso se quiser: dbRegistrarLogAcesso(... 'ID Inválido Recebido: ' . $req_id_raw ...)
                            continue; // Pula para o próximo ID
                        }

                        $ids_processados_validos[] = $req_id; // Adiciona o ID válido à lista de processados

                        // Chama a função para adicionar (a mesma função de antes)
                        $resultadoAdd = adicionarRequisitoAoModelo($conexao, $modelo_id, $req_id, $secao_add);

                        if ($resultadoAdd === true) {
                            $adicionados_sucesso++;
                        } else {
                            // Guarda a mensagem de erro específica para este ID
                            $erros_adicionar[$req_id] = htmlspecialchars($resultadoAdd); // Usa ID como chave para evitar duplicação de erro por ID
                        }
                    }

                    // Monta as mensagens de feedback
                    $mensagem_sucesso_final = "";
                    $mensagem_erro_final = "";

                    if ($adicionados_sucesso > 0) {
                        $mensagem_sucesso_final = $adicionados_sucesso . " requisito(s) adicionado(s) ao modelo.";
                        definir_flash_message('sucesso', $mensagem_sucesso_final);
                        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'add_req_modelo_multi', 1, "Mod: $modelo_id, Qtd Sucesso: $adicionados_sucesso, IDs: ".implode(',', $ids_processados_validos).", Secao: " .($secao_add ?? 'N/A'), $conexao);
                    }

                    if (!empty($erros_adicionar)) {
                         $erros_formatados = [];
                         foreach ($erros_adicionar as $id_erro => $msg_erro) {
                            $erros_formatados[] = "ID $id_erro: $msg_erro";
                         }
                        $mensagem_erro_final = "Falha ao adicionar ". count($erros_adicionar) ." item(ns). Detalhes: " . implode('; ', $erros_formatados);
                        // Usar `append_flash_message` se quiser combinar com sucesso, ou `definir_flash_message` se quiser priorizar o erro.
                        definir_flash_message('erro', $mensagem_erro_final); // Prioriza mostrar erro se houver falhas
                        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'add_req_modelo_multi_fail', 0, "Mod: $modelo_id, Qtd Falha: ".count($erros_adicionar).", IDs Tentados: ".implode(',', $ids_processados_validos).", Erros: " . implode('; ',$erros_formatados), $conexao);
                    }

                } else {
                    // Nenhum requisito selecionado ou erro no recebimento do array
                    definir_flash_message('erro', "Nenhum requisito válido foi selecionado para adicionar.");
                     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'add_req_modelo_multi_vazio', 0, "Mod: $modelo_id", $conexao);
                }
                header('Location: ' . $_SERVER['REQUEST_URI']); // Recarrega a página
                exit;
            // ----- FIM DA MODIFICAÇÃO -----
            
            case 'remover_item':
                 $modelo_item_id_rem = filter_input(INPUT_POST, 'modelo_item_id', FILTER_VALIDATE_INT);
                 if ($modelo_item_id_rem && removerRequisitoDoModelo($conexao, $modelo_item_id_rem)) {
                      definir_flash_message('sucesso', "Item removido do modelo.");
                      dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'rem_req_modelo', 1, "Mod: $modelo_id, Item: $modelo_item_id_rem", $conexao);
                 } else {
                        // Se falhar, captura o erro do DB (se houver) e registra
                      $conexao_mysql = new mysqli("localhost", "root", "", "acoditools");  
                      $db_error = mysqli_error($conexao_mysql); // Captura erro do DB se houver
                      definir_flash_message('erro', "Erro ao remover o item do modelo." . ($db_error ? " Detalhe: ".$db_error : ""));
                      dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'rem_req_modelo_fail', 0, "Mod: $modelo_id, Item: $modelo_item_id_rem, Erro DB: $db_error", $conexao);
                 }
                 header('Location: ' . $_SERVER['REQUEST_URI']); exit;

            case 'salvar_modelo': // Salvar dados GERAIS do modelo
                 $nome_modelo = trim(filter_input(INPUT_POST, 'nome_modelo', FILTER_SANITIZE_SPECIAL_CHARS));
                 $desc_modelo = trim(filter_input(INPUT_POST, 'descricao_modelo', FILTER_SANITIZE_SPECIAL_CHARS));
                 $ativo_modelo = isset($_POST['ativo_modelo']) && $_POST['ativo_modelo'] == '1'; // Verifica o valor explicitamente

                 if(empty($nome_modelo)) { $erro_msg = "O nome do modelo não pode ser vazio."; }
                 else {
                     $resultadoUpdate = atualizarModeloAuditoria($conexao, $modelo_id, $nome_modelo, $desc_modelo ?: null, $ativo_modelo, $_SESSION['usuario_id']);
                     if ($resultadoUpdate === true) {
                          $sucesso_msg = "Dados do modelo atualizados."; // Define msg local
                          dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'edit_modelo_sucesso', 1, "ID: $modelo_id", $conexao);
                          $modelo = getModeloAuditoria($conexao, $modelo_id); // Recarrega dados para mostrar na página
                     } else {
                          $erro_msg = "Erro ao atualizar modelo: " . htmlspecialchars($resultadoUpdate);
                          dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'edit_modelo_falha', 0, "ID: $modelo_id, Erro: $resultadoUpdate", $conexao);
                     }
                 }
                 // Permanece na página para mostrar erro ou sucesso
                 break;

            // A ação 'salvar_ordem' é tratada via AJAX pelo ajax_handler.php
            // Não deve ser processada aqui em um POST normal
            case 'salvar_ordem':
                 $erro_msg = "Ação inválida. A reordenação é feita automaticamente ao arrastar.";
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_modelo_salvar_ordem_post_invalido', 0, "Tentativa POST: salvar_ordem", $conexao);
                 break;

            default:
                 $erro_msg = "Ação desconhecida.";
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_modelo_acao_desconhecida', 0, "Ação: ".htmlspecialchars($action), $conexao);
        } // Fim do switch
    } // Fim else CSRF Válido
} else {
     // Regenera o token CSRF na carga inicial da página (GET)
     $csrf_token = gerar_csrf_token();
     $_SESSION['csrf_token'] = $csrf_token;
}


// --- Buscar Dados para Exibição ---
// É importante buscar DEPOIS do processamento POST para refletir as mudanças
$modelo = getModeloAuditoria($conexao, $modelo_id); // Recarrega dados do modelo caso tenham sido alterados
$itensDoModeloAgrupados = getItensDoModelo($conexao, $modelo_id, true);
$totalItensModelo = array_sum(array_map("count", $itensDoModeloAgrupados));
$requisitosDisponiveis = getRequisitosDisponiveisParaModelo($conexao, $modelo_id);

// --- Geração do HTML ---
$title = "Editar Modelo: " . htmlspecialchars($modelo['nome']);
echo getHeaderAdmin($title);

// Garantir que o token CSRF esteja disponível para os formulários
$csrf_token = $_SESSION['csrf_token'] ?? gerar_csrf_token(); // Pega o atual ou gera um novo
if(!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = $csrf_token; }

?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
        <div class="mb-2 mb-md-0">
            <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-edit me-2 text-primary"></i>Editar Modelo de Auditoria</h1>
            <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb"><li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/dashboard_admin.php">Dashboard</a></li><li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/modelo/modelo_index.php">Modelos</a></li><li class="breadcrumb-item active" aria-current="page">Editar: <?= htmlspecialchars($modelo['nome']) ?></li></ol></nav>
        </div>
        <a href="<?= BASE_URL ?>admin/modelo/modelo_index.php" class="btn btn-outline-secondary rounded-pill px-3 action-button-secondary"> <i class="fas fa-arrow-left me-1"></i> Voltar para Modelos </a>
    </div>

    <?php if ($sucesso_msg): ?><div class="alert alert-success custom-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger custom-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= $erro_msg ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="row g-4">
        <?php /* Coluna Esquerda: Edição do Modelo e Adicionar Requisitos */ ?>
        <div class="col-lg-5 order-lg-2">
            <div class="card shadow-sm mb-4 rounded-3 border-0">
                <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary opacity-75"></i>Dados do Modelo</h6></div>
                <div class="card-body p-3">
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" id="editModelForm" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="action" value="salvar_modelo">
                        <div class="mb-3"><label for="nome_modelo" class="form-label form-label-sm fw-semibold">Nome <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="nome_modelo" name="nome_modelo" value="<?= htmlspecialchars($modelo['nome']) ?>" required maxlength="255"><div class="invalid-feedback">Nome obrigatório.</div></div>
                        <div class="mb-3"><label for="descricao_modelo" class="form-label form-label-sm fw-semibold">Descrição</label><textarea class="form-control form-control-sm" id="descricao_modelo" name="descricao_modelo" rows="3"><?= htmlspecialchars($modelo['descricao']) ?></textarea></div>
                        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="ativo_modelo" name="ativo_modelo" value="1" <?= !empty($modelo['ativo']) ? 'checked' : '' ?>><label class="form-check-label small" for="ativo_modelo">Modelo Ativo</label></div>
                        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-save me-1"></i> Salvar Dados do Modelo</button>
                    </form>
                </div>
            </div>

             <?php /* ----- MODIFICADO: Card Adicionar Requisitos (Múltiplos) ----- */ ?>
            <div class="card shadow-sm rounded-3 border-0 sticky-lg-top" style="top: 20px;">
                <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-plus-circle me-2 text-success opacity-75"></i>Adicionar Requisitos ao Modelo</h6></div>
                <div class="card-body p-3">
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" id="addItemForm" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="action" value="adicionar_item">
                        <div class="mb-2">
                            <label for="requisitos_ids" class="form-label form-label-sm fw-semibold">Requisitos Disponíveis <span class="text-danger">*</span></label> <!-- MODIFICADO: for="requisitos_ids" -->
                            <input type="search" id="filtroReqDisponiveis" class="form-control form-control-sm mb-1" placeholder="Filtrar requisitos...">

                            <!-- MODIFICADO: select multiple, name="requisitos_ids[]", id="requisitos_ids", size aumentado -->
                            <select multiple class="form-select form-select-sm" id="requisitos_ids" name="requisitos_ids[]" size="10" required>
                                <?php if(empty($requisitosDisponiveis)): ?>
                                    <option value="" disabled>Nenhum requisito disponível</option>
                                <?php else:
                                    $grupoAtualDisp = null;
                                    foreach($requisitosDisponiveis as $reqDisp):
                                        $grupoDisp = $reqDisp['norma_referencia'] ?? ($reqDisp['categoria'] ?? 'Geral');
                                        // Abrir novo optgroup se mudou
                                        if($grupoDisp !== $grupoAtualDisp) {
                                            if($grupoAtualDisp !== null) echo '</optgroup>'; // Fechar anterior se não for o primeiro
                                            echo '<optgroup label="'.htmlspecialchars($grupoDisp).'">';
                                            $grupoAtualDisp = $grupoDisp;
                                        } ?>
                                        <option value="<?= $reqDisp['id'] ?>" data-texto="<?= htmlspecialchars(strtolower($reqDisp['codigo'] . ' ' . $reqDisp['nome'] . ' ' . $grupoDisp )) ?>">
                                            <?= htmlspecialchars($reqDisp['codigo'] ? $reqDisp['codigo'].': ' : 'ID '.$reqDisp['id'].': ') . htmlspecialchars($reqDisp['nome']) ?>
                                            <?php if(!empty($reqDisp['categoria']) && $grupoDisp !== $reqDisp['categoria']): ?>
                                                <span class="text-muted x-small"> (Cat: <?= htmlspecialchars($reqDisp['categoria']) ?>)</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach;
                                    if($grupoAtualDisp !== null) echo '</optgroup>'; // Fechar o último optgroup
                                    ?>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">Selecione pelo menos um requisito.</div>
                             <!-- MODIFICADO: Texto de ajuda para seleção múltipla -->
                            <div class="form-text small text-muted mt-1">Use Ctrl+Click (ou Cmd+Click no Mac) para selecionar/deselecionar múltiplos itens não sequenciais, ou Shift+Click para selecionar um bloco.</div>
                        </div>
                        <div class="mb-3">
                            <label for="secao_add" class="form-label form-label-sm fw-semibold">Agrupar na Seção (Opcional)</label>
                            <input type="text" class="form-control form-control-sm" id="secao_add" name="secao_add" placeholder="Ex: Controles de Acesso (deixe vazio para 'Itens Gerais')" list="secoesExistentesModelo">
                            <datalist id="secoesExistentesModelo">
                                <?php
                                $secoesUnicas = array_keys($itensDoModeloAgrupados);
                                sort($secoesUnicas); // Opcional: ordenar as seções na lista
                                foreach ($secoesUnicas as $sec):
                                    if($sec != 'Itens Gerais'): ?>
                                        <option value="<?= htmlspecialchars($sec) ?>">
                                <?php endif; endforeach; ?>
                            </datalist>
                        </div>
                         <!-- MODIFICADO: Texto do botão -->
                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-plus me-1"></i> Adicionar Selecionados ao Modelo</button>
                    </form>
                </div>
            </div>
             <?php /* ----- FIM DA MODIFICAÇÃO ----- */ ?>
        </div>

        <?php /* Coluna Direita: Itens Atuais do Modelo */ ?>
        <div class="col-lg-7 order-lg-1">
            <div class="card shadow-sm rounded-3 border-0">
                <div class="card-header bg-light border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-list-ol me-2 text-primary opacity-75"></i>Itens Atuais no Modelo</h6>
                    <span class="badge bg-secondary rounded-pill"><?= $totalItensModelo ?> item(s)</span>
                </div>
                <div class="card-body p-3">
                   <?php if (empty($itensDoModeloAgrupados)): ?>
                       <p class="text-center text-muted py-4">Nenhum requisito adicionado a este modelo ainda.</p>
                   <?php else: ?>
                       <p class="small text-muted fst-italic mb-3"><i class="fas fa-arrows-alt-v me-1"></i> Arraste e solte os itens <i class="fas fa-grip-vertical text-muted mx-1"></i> para reordenar dentro de cada seção.</p>
                       <?php foreach ($itensDoModeloAgrupados as $secao => $itensDaSecao): ?>
                           <fieldset class="border rounded p-3 mb-3 bg-light-subtle section-container" data-secao="<?= htmlspecialchars($secao) ?>">
                               <legend class="h6 small fw-bold text-primary mb-2 d-flex justify-content-between align-items-center">
                                   <span>
                                      <i class="fas <?= ($secao === 'Itens Gerais') ? 'fa-stream' : 'fa-folder' ?> me-1"></i>
                                      <?= htmlspecialchars($secao) ?>
                                    </span>
                                   <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill"><?= count($itensDaSecao) ?></span>
                               </legend>
                               <ul class="list-group list-group-flush item-list rounded" style="cursor: move;">
                                   <?php foreach ($itensDaSecao as $item): ?>
                                   <li class="list-group-item d-flex justify-content-between align-items-center px-2 py-1 item-row" data-item-id="<?= $item['modelo_item_id'] ?>">
                                       <div class="flex-grow-1 text-truncate me-2" style="min-width: 0;"> <?php // Flex grow e min-width para truncar corretamente ?>
                                           <i class="fas fa-grip-vertical text-muted me-2 fa-xs drag-handle" title="Reordenar"></i>
                                           <span class="text-muted small me-1" title="ID do Item no Modelo: <?= $item['modelo_item_id'] ?>, ID Mestre Requisito: <?= $item['requisito_id'] ?>">(#<?= $item['requisito_id'] ?>)</span>
                                           <strong class="small me-1"><?= htmlspecialchars($item['codigo'] ?: '') ?></strong>
                                           <span class="small" title="<?= htmlspecialchars($item['nome']) ?>">
                                               <?= htmlspecialchars(mb_strimwidth($item['nome'], 0, 60, "...")) ?>
                                           </span>
                                            <span class="badge <?= $item['requisito_ativo'] ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?> ms-1 x-small" title="Status do Requisito Mestre">
                                              <?= $item['requisito_ativo'] ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                       </div>
                                       <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" class="d-inline form-remove-item ms-auto" onsubmit="return confirm('Tem certeza que deseja remover este item especifico [ID: <?= $item['modelo_item_id'] ?>] deste modelo?\nRequisito: <?= htmlspecialchars(addslashes($item['nome'])) ?>');">
                                           <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                           <input type="hidden" name="action" value="remover_item">
                                           <input type="hidden" name="modelo_item_id" value="<?= $item['modelo_item_id'] ?>">
                                           <button type="submit" class="btn btn-sm btn-outline-danger border-0 py-0 px-1" title="Remover Item do Modelo"><i class="fas fa-times fa-xs"></i></button>
                                       </form>
                                   </li>
                                   <?php endforeach; ?>
                               </ul>
                               <?php if(empty($itensDaSecao)): ?>
                                 <p class="small text-muted text-center mb-0">(Seção vazia)</p>
                               <?php endif; ?>
                           </fieldset>
                       <?php endforeach; ?>
                   <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div> <?php // Fecha container-fluid ?>

<?php // Fechamento do <main> é feito pelo getFooterAdmin() ?>

<?php /* Scripts JS */ ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ativar validação Bootstrap nos formulários que precisam
    const formsToValidate = document.querySelectorAll('.needs-validation');
    formsToValidate.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Filtro da lista de requisitos disponíveis
    const filtroReqInput = document.getElementById('filtroReqDisponiveis');
    const selectReq = document.getElementById('requisitos_ids'); // MODIFICADO: ID do select

    if (filtroReqInput && selectReq) {
        // Guarda as opções originais, incluindo o texto para busca e o grupo
         let originalOptionsData = Array.from(selectReq.querySelectorAll('option')).map(opt => {
              if (!opt.value) return null; // Ignora options vazias ou desabilitadas de placeholder
              const parentLabel = opt.parentElement.tagName === 'OPTGROUP' ? opt.parentElement.label : null;
              return {
                   value: opt.value,
                   text: opt.text, // Texto visível
                   searchText: opt.dataset.texto || (opt.text + (parentLabel ? ' ' + parentLabel : '')).toLowerCase(), // Texto para busca (data-texto ou composição)
                   group: parentLabel // Nome do grupo (optgroup)
               };
           }).filter(Boolean); // Remove nulos

        filtroReqInput.addEventListener('input', function() {
            const termo = this.value.toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, ""); // Normaliza e remove acentos para busca
            selectReq.innerHTML = ''; // Limpa o select

            let currentGroupLabel = null;
            let optgroupElement = null;
            let hasResults = false;

            originalOptionsData.forEach(optData => {
                // Compara termo com o texto de busca pré-processado
                 if (termo === '' || optData.searchText.normalize("NFD").replace(/[\u0300-\u036f]/g, "").includes(termo)) {
                    hasResults = true;
                    // Cria optgroup se for um novo grupo e se existir um nome de grupo
                    if (optData.group && optData.group !== currentGroupLabel) {
                        optgroupElement = document.createElement('optgroup');
                        optgroupElement.label = optData.group;
                        selectReq.appendChild(optgroupElement);
                        currentGroupLabel = optData.group;
                    }
                    // Cria a option
                    const optionElement = document.createElement('option');
                    optionElement.value = optData.value;
                    optionElement.textContent = optData.text;
                     // Opcional: recriar data-* attributes se você os usar em outro lugar
                     // if(optData.dataAttributes) { Object.keys(optData.dataAttributes).forEach(key => optionElement.dataset[key] = optData.dataAttributes[key]); }

                    // Adiciona ao optgroup ou direto ao select
                    if (optgroupElement && optData.group) {
                        optgroupElement.appendChild(optionElement);
                    } else {
                         // Se não tem grupo ou o grupo já foi adicionado (pouco provável cair aqui se não for grupo 'Geral')
                        selectReq.appendChild(optionElement);
                    }
                }
            });

             // Adiciona placeholder se filtro não retornou resultados
            if (!hasResults && termo !== '') {
                  const noOpt = document.createElement('option');
                  noOpt.disabled = true;
                  noOpt.textContent = 'Nenhum requisito encontrado para "' + this.value + '"';
                  selectReq.appendChild(noOpt);
            } else if (!hasResults && termo === '') {
                // Se estava vazio e continua vazio (caso original)
                 const noOpt = document.createElement('option');
                 noOpt.disabled = true;
                 noOpt.textContent = 'Nenhum requisito disponível';
                 selectReq.appendChild(noOpt);
            }
        });
    } else {
        console.warn('Elemento de filtro ou select de requisitos não encontrado.');
    }


    // Lógica Drag & Drop para reordenar itens DENTRO de cada seção
    document.querySelectorAll('.item-list').forEach(list => {
        if(!list.querySelector('li')) return; // Não inicializar Sortable em listas vazias

        new Sortable(list, {
            animation: 150,
            ghostClass: 'bg-info-subtle',
            handle: '.drag-handle',
            onEnd: function (evt) {
                // Verifica se a ordem realmente mudou
                if (evt.oldIndex === evt.newIndex) {
                     console.log('Item dropped in the same position.');
                    return; // Sai se não houve mudança de posição
                }

                const items = evt.to.querySelectorAll('li.item-row');
                const ordemIds = Array.from(items).map(el => el.dataset.itemId);
                const secaoElement = evt.to.closest('.section-container');
                const secao = secaoElement ? secaoElement.dataset.secao : null;

                console.log('Nova ordem detectada para seção:', secao, ' IDs:', ordemIds);

                // Prepara dados para enviar via AJAX
                const formData = new FormData();
                formData.append('action', 'salvar_ordem');
                formData.append('modelo_id', <?= $modelo_id ?>);
                formData.append('secao', secao === 'Itens Gerais' ? '' : secao); // Envia vazio para null/Itens Gerais
                formData.append('ordem_ids', JSON.stringify(ordemIds));
                // Precisamos pegar o token CSRF atualizado (que foi regerado no início do script PHP ou após um POST)
                const currentCsrfToken = document.querySelector('input[name="csrf_token"]')?.value || '<?= htmlspecialchars($csrf_token) ?>';
                formData.append('csrf_token', currentCsrfToken);

                // Feedback visual temporário
                evt.to.closest('.section-container').classList.add('saving-order');

                // Chamada AJAX para salvar a ordem no ajax_handler.php
                fetch('<?= BASE_URL ?>admin/modelo/ajax_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                     console.log('Resposta AJAX salvar ordem:', data);
                    evt.to.closest('.section-container').classList.remove('saving-order'); // Remove feedback
                    if (data.success) {
                        // Opcional: Mostrar um toast de sucesso rápido
                        console.log('Ordem salva com sucesso.');
                        // Atualiza o token CSRF no formulário se um novo foi retornado (boa prática)
                        if(data.novo_csrf) {
                            document.querySelectorAll('input[name="csrf_token"]').forEach(input => input.value = data.novo_csrf);
                             console.log('Token CSRF atualizado via AJAX.');
                        }
                    } else {
                        // Erro - Mostrar mensagem de erro (talvez mais proeminente que alert)
                        console.error('Erro ao salvar a nova ordem:', data.message);
                        alert('Erro ao salvar a nova ordem: ' + data.message + '\nA página pode precisar ser recarregada para refletir a ordem correta.');
                        // Poderia tentar reverter a ordem visualmente, mas recarregar pode ser mais seguro
                        // evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]); // Tentativa de reverter (complexo)
                    }
                })
                .catch(error => {
                    evt.to.closest('.section-container').classList.remove('saving-order');
                    console.error('Erro na chamada AJAX:', error);
                    alert('Erro de comunicação ao tentar salvar a ordem. Verifique sua conexão e tente novamente.');
                });
            }
        });
    }); // Fim forEach .item-list

    // Adiciona uma pequena classe CSS para feedback visual durante o salvamento da ordem
    const style = document.createElement('style');
    style.textContent = '.saving-order { border-color: #ffc107 !important; box-shadow: 0 0 5px rgba(255, 193, 7, 0.5); }';
    document.head.appendChild(style);

}); // Fim DOMContentLoaded
</script>

<?php
echo getFooterAdmin();
?>