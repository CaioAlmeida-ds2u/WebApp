<?php
// admin/plataforma_gerenciamento_campos_personalizados.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Funções CRUD para campos personalizados e planos

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Admin da Acoditools
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');
$erro_form_campo_pers = $_SESSION['erro_form_campo_pers'] ?? null; unset($_SESSION['erro_form_campo_pers']);
$form_data_campo_pers = $_SESSION['form_data_campo_pers'] ?? []; unset($_SESSION['form_data_campo_pers']);

// --- Buscar Dados para Dropdowns ---
// *** Chamar função: listarPlanosAssinatura($conexao, true) // Apenas ativos ***
$planos_disponiveis = listarPlanosAssinatura($conexao, true); // Para o multi-select de disponibilidade
$entidades_aplicaveis = [ // Onde o campo pode ser usado
    'AUDITORIA_GERAL' => 'Auditoria (Dados Gerais)',
    'ITEM_AUDITORIA' => 'Item de Auditoria (Avaliação)',
    'PLANO_ACAO' => 'Plano de Ação'
];
$tipos_de_campo = [ // Tipos de dados que o campo pode ter
    'TEXTO_CURTO' => 'Texto Curto (Input)',
    'TEXTO_LONGO' => 'Texto Longo (Textarea)',
    'NUMERO_INT' => 'Número Inteiro',
    'NUMERO_DEC' => 'Número Decimal',
    'DATA' => 'Data',
    'LISTA_OPCOES_UNICA' => 'Lista de Opções (Seleção Única)',
    'LISTA_OPCOES_MULTIPLA' => 'Lista de Opções (Seleção Múltipla)',
    'CHECKBOX_SIM_NAO' => 'Caixa de Seleção (Sim/Não)'
];


// --- Processamento de Ações POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $action_cp = $_POST['action'] ?? '';
        $campo_pers_id_acao = filter_input(INPUT_POST, 'campo_pers_id', FILTER_VALIDATE_INT);
        // Regenera token após uso (exceto se for erro de validação do form de criar/editar)
        // ... (lógica de regeneração do token similar às outras páginas) ...

        $dados_campo_form = [
            'nome_campo_interno' => trim($_POST['nome_campo_interno'] ?? ''), // Será usado como chave no DB
            'label_campo_exibicao' => trim($_POST['label_campo_exibicao'] ?? ''),
            'tipo_campo' => $_POST['tipo_campo'] ?? '',
            'opcoes_lista_texto' => trim($_POST['opcoes_lista_texto'] ?? ''), // Opções separadas por vírgula ou linha
            'aplicavel_a_entidade' => $_POST['aplicavel_a_entidade'] ?? [], // Pode ser array se permitir múltipla aplicabilidade
            'disponivel_para_planos_ids' => $_POST['disponivel_para_planos_ids'] ?? [], // Array de IDs de planos
            'obrigatorio' => isset($_POST['obrigatorio_campo_pers']) ? 1 : 0,
            'ativo' => isset($_POST['ativo_campo_pers']) ? 1 : 0,
            'placeholder_campo' => trim($_POST['placeholder_campo'] ?? ''),
            'texto_ajuda_campo' => trim($_POST['texto_ajuda_campo'] ?? '')
        ];


        switch ($action_cp) {
            case 'criar_campo_pers':
                if (empty($dados_campo_form['nome_campo_interno']) || empty($dados_campo_form['label_campo_exibicao']) || empty($dados_campo_form['tipo_campo']) || empty($dados_campo_form['aplicavel_a_entidade'])) {
                    $_SESSION['erro_form_campo_pers'] = "Nome interno, Label de exibição, Tipo e Entidade Aplicável são obrigatórios.";
                    $_SESSION['form_data_campo_pers'] = $_POST;
                } elseif ( (str_contains($dados_campo_form['tipo_campo'], 'LISTA_OPCOES')) && empty($dados_campo_form['opcoes_lista_texto']) ) {
                    $_SESSION['erro_form_campo_pers'] = "Para tipo 'Lista de Opções', as opções são obrigatórias.";
                    $_SESSION['form_data_campo_pers'] = $_POST;
                }
                else {
                    // Processar 'aplicavel_a_entidade' e 'opcoes_lista_texto' para formato de DB (JSON ou string separada)
                    $dados_campo_form['aplicavel_a_entidade_db'] = is_array($dados_campo_form['aplicavel_a_entidade']) ? json_encode($dados_campo_form['aplicavel_a_entidade']) : json_encode([$dados_campo_form['aplicavel_a_entidade']]);
                    $dados_campo_form['opcoes_lista_db'] = null;
                    if (str_contains($dados_campo_form['tipo_campo'], 'LISTA_OPCOES') && !empty($dados_campo_form['opcoes_lista_texto'])) {
                        $opcoes_array = array_map('trim', preg_split('/[\n,]+/', $dados_campo_form['opcoes_lista_texto']));
                        $dados_campo_form['opcoes_lista_db'] = json_encode(array_filter($opcoes_array));
                    }
                    $dados_campo_form['disponivel_para_planos_ids_db'] = json_encode(array_map('intval', $dados_campo_form['disponivel_para_planos_ids']));


                    // *** Chamar função: criarCampoPersonalizadoGlobal($conexao, $dados_campo_form, $_SESSION['usuario_id']) ***
                    $res_criacao_cp = criarCampoPersonalizadoGlobal($conexao, $dados_campo_form, $_SESSION['usuario_id']);
                    if ($res_criacao_cp === true) {
                        definir_flash_message('sucesso', "Campo Personalizado '".htmlspecialchars($dados_campo_form['label_campo_exibicao'])."' criado.");
                         $_SESSION['csrf_token'] = gerar_csrf_token();
                    } else {
                        $_SESSION['erro_form_campo_pers'] = is_string($res_criacao_cp) ? $res_criacao_cp : "Erro ao criar campo.";
                        $_SESSION['form_data_campo_pers'] = $_POST;
                    }
                }
                break;

            case 'salvar_edicao_campo_pers':
                // Lógica de edição similar, chamando atualizarCampoPersonalizadoGlobal()
                if ($campo_pers_id_acao && !empty($dados_campo_form['nome_campo_interno']) && !empty($dados_campo_form['label_campo_exibicao'])) {
                    // Processar campos array/json
                    $dados_campo_form['aplicavel_a_entidade_db'] = is_array($dados_campo_form['aplicavel_a_entidade']) ? json_encode($dados_campo_form['aplicavel_a_entidade']) : json_encode([$dados_campo_form['aplicavel_a_entidade']]);
                    $dados_campo_form['opcoes_lista_db'] = null;
                    if (str_contains($dados_campo_form['tipo_campo'], 'LISTA_OPCOES') && !empty($dados_campo_form['opcoes_lista_texto'])) {
                        $opcoes_array = array_map('trim', preg_split('/[\n,]+/', $dados_campo_form['opcoes_lista_texto']));
                        $dados_campo_form['opcoes_lista_db'] = json_encode(array_filter($opcoes_array));
                    }
                    $dados_campo_form['disponivel_para_planos_ids_db'] = json_encode(array_map('intval', $dados_campo_form['disponivel_para_planos_ids']));

                    $res_edicao_cp = atualizarCampoPersonalizadoGlobal($conexao, $campo_pers_id_acao, $dados_campo_form, $_SESSION['usuario_id']);
                     if ($res_edicao_cp === true) {
                        definir_flash_message('sucesso', "Campo Personalizado (ID: $campo_pers_id_acao) atualizado.");
                         $_SESSION['csrf_token'] = gerar_csrf_token();
                    } else {
                        definir_flash_message('erro', is_string($res_edicao_cp) ? $res_edicao_cp : "Erro ao atualizar campo ID $campo_pers_id_acao.");
                    }
                } else {
                    definir_flash_message('erro', "Dados inválidos para edição do Campo Personalizado.");
                }
                break;
            // Cases para ativar/desativar/excluir (similares às outras páginas)
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}


// --- Busca de Dados para Exibição ---
// *** Chamar função: listarCamposPersonalizadosGlobaisPaginado($conexao, $pagina, $itens_por_pagina, $filtros) ***
$lista_campos_pers = listarCamposPersonalizadosGlobaisPaginado($conexao); // Simplificado sem paginação por enquanto
$total_campos_pers = count($lista_campos_pers);

// Para o modal de edição
$campo_pers_para_editar = null;
$edit_id_cp = filter_input(INPUT_GET, 'edit_cp_id', FILTER_VALIDATE_INT);
if ($edit_id_cp) {
    $campo_pers_para_editar = getCampoPersonalizadoGlobalPorId($conexao, $edit_id_cp); // Função a ser criada
    if (!$campo_pers_para_editar) definir_flash_message('erro', "Campo para edição não encontrado (ID: $edit_id_cp).");
     else { // Decodificar JSON para o formulário
         $campo_pers_para_editar['aplicavel_a_entidade'] = json_decode($campo_pers_para_editar['aplicavel_a_entidade_db'] ?? '[]', true);
         $campo_pers_para_editar['opcoes_lista_texto'] = !empty($campo_pers_para_editar['opcoes_lista_db']) ? implode("\n", json_decode($campo_pers_para_editar['opcoes_lista_db'], true)) : '';
         $campo_pers_para_editar['disponivel_para_planos_ids'] = json_decode($campo_pers_para_editar['disponivel_para_planos_ids_db'] ?? '[]', true);
     }
}


if (empty($_SESSION['csrf_token']) || ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$edit_id_cp) || !empty($erro_form_campo_pers)) {
    $_SESSION['csrf_token'] = gerar_csrf_token();
}
$csrf_token_page = $_SESSION['csrf_token'];

$title = "ACodITools - Gerenciar Campos Personalizados Globais";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-puzzle-piece me-2"></i>Gerenciar Campos Personalizados Globais</h1>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="modal" data-bs-target="#modalCriarEditarCampoPers" data-action="criar">
            <i class="fas fa-plus me-1"></i> Novo Campo Personalizado
        </button>
    </div>

    <?php if ($sucesso_msg): /* ... Mensagens ... */ endif; ?>
    <?php if ($erro_msg): /* ... Mensagens ... */ endif; ?>
     <?php if ($erro_form_campo_pers && !$edit_id_cp): ?>
        <div class="alert alert-warning alert-dismissible fade show small" role="alert">
            <strong>Erro ao criar:</strong> <?= $erro_form_campo_pers ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>


    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-header bg-light border-bottom"><h6 class="mb-0 fw-bold"><i class="fas fa-list-ul me-2 text-primary opacity-75"></i>Campos Personalizados Definidos (<?= $total_campos_pers ?>)</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th>ID</th>
                            <th>Label de Exibição</th>
                            <th>Nome Interno (Chave)</th>
                            <th>Tipo</th>
                            <th>Aplicável a</th>
                            <th>Planos</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_campos_pers)): ?>
                            <tr><td colspan="8" class="text-center text-muted p-4">Nenhum campo personalizado definido.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_campos_pers as $campo_p):
                                $aplicavel_decoded = json_decode($campo_p['aplicavel_a_entidade_db'] ?? '[]', true);
                                $aplicavel_display = is_array($aplicavel_decoded) ? implode(', ', array_map(function($key) use ($entidades_aplicaveis) { return $entidades_aplicaveis[$key] ?? $key; }, $aplicavel_decoded)) : 'N/A';

                                $planos_ids_decoded = json_decode($campo_p['disponivel_para_planos_ids_db'] ?? '[]', true);
                                $planos_display_arr = [];
                                if (is_array($planos_ids_decoded)) {
                                    foreach ($planos_ids_decoded as $pid) {
                                        foreach ($planos_disponiveis as $pl) { if ($pl['id'] == $pid) {$planos_display_arr[] = $pl['nome_plano']; break;} }
                                    }
                                }
                                $planos_display = !empty($planos_display_arr) ? implode(', ', $planos_display_arr) : 'Todos (Padrão)';
                            ?>
                            <tr>
                                <td class="fw-bold">#<?= htmlspecialchars($campo_p['id']) ?></td>
                                <td><?= htmlspecialchars($campo_p['label_campo_exibicao']) ?></td>
                                <td><code><?= htmlspecialchars($campo_p['nome_campo_interno']) ?></code></td>
                                <td class="small"><?= htmlspecialchars($tipos_de_campo[$campo_p['tipo_campo']] ?? $campo_p['tipo_campo']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($aplicavel_display) ?></td>
                                <td class="small text-muted" title="<?= htmlspecialchars($planos_display) ?>"><?= htmlspecialchars(mb_strimwidth($planos_display, 0, 30, "...")) ?></td>
                                <td class="text-center"><span class="badge <?= $campo_p['ativo'] ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= $campo_p['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
                                <td class="text-center action-buttons-table">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1 action-btn btn-edit-campo-pers"
                                            data-bs-toggle="modal" data-bs-target="#modalCriarEditarCampoPers"
                                            data-id="<?= $campo_p['id'] ?>"
                                            data-nome_interno="<?= htmlspecialchars($campo_p['nome_campo_interno']) ?>"
                                            data-label_exibicao="<?= htmlspecialchars($campo_p['label_campo_exibicao']) ?>"
                                            data-tipo_campo="<?= htmlspecialchars($campo_p['tipo_campo']) ?>"
                                            data-opcoes_lista_texto="<?= htmlspecialchars(!empty($campo_p['opcoes_lista_db']) ? implode("\n", json_decode($campo_p['opcoes_lista_db'], true)) : '') ?>"
                                            data-aplicavel_a_entidade_db="<?= htmlspecialchars($campo_p['aplicavel_a_entidade_db'] ?? '[]') ?>"
                                            data-disponivel_para_planos_ids_db="<?= htmlspecialchars($campo_p['disponivel_para_planos_ids_db'] ?? '[]') ?>"
                                            data-obrigatorio="<?= $campo_p['obrigatorio'] ?? 0 ?>"
                                            data-ativo="<?= $campo_p['ativo'] ?>"
                                            data-placeholder="<?= htmlspecialchars($campo_p['placeholder_campo'] ?? '')?>"
                                            data-texto_ajuda="<?= htmlspecialchars($campo_p['texto_ajuda_campo'] ?? '')?>"
                                            title="Editar Campo"> <i class="fas fa-edit fa-fw"></i>
                                    </button>
                                    <?php /* Forms para Ativar/Desativar/Excluir (similar às outras páginas) */ ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php /* Paginação se for implementada para esta lista */ ?>
    </div>
</div>

<!-- Modal para Criar/Editar Campo Personalizado -->
<div class="modal fade" id="modalCriarEditarCampoPers" tabindex="-1" aria-labelledby="modalCampoPersLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl"> <?php /* Modal grande para mais campos */ ?>
        <div class="modal-content">
            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="formModalCampoPers" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                <input type="hidden" name="action" id="modal_campo_pers_action" value="criar_campo_pers">
                <input type="hidden" name="campo_pers_id" id="modal_campo_pers_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalCampoPersLabel">Novo Campo Personalizado Global</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if ($erro_form_campo_pers && $edit_id_cp): ?>
                        <div class="alert alert-warning small p-2" role="alert" id="modal_edit_error_placeholder_cp">
                           <?= $erro_form_campo_pers ?>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="modal_label_campo_exibicao" class="form-label form-label-sm">Label de Exibição <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="modal_label_campo_exibicao" name="label_campo_exibicao" required maxlength="100">
                            <small class="form-text text-muted">Como o campo aparecerá para o usuário.</small>
                            <div class="invalid-feedback">Label é obrigatório.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="modal_nome_campo_interno" class="form-label form-label-sm">Nome Interno (Chave) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="modal_nome_campo_interno" name="nome_campo_interno" required maxlength="50" pattern="[a-z0-9_]+" title="Apenas letras minúsculas, números e underscore.">
                            <small class="form-text text-muted">Ex: `ref_contrato`, `data_aprov_doc`. Não pode ser alterado após criação.</small>
                             <div class="invalid-feedback">Nome interno inválido/obrigatório.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="modal_tipo_campo" class="form-label form-label-sm">Tipo do Campo <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="modal_tipo_campo" name="tipo_campo" required>
                                <option value="">-- Selecione --</option>
                                <?php foreach ($tipos_de_campo as $key => $label_tipo): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label_tipo) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione o tipo.</div>
                        </div>

                        <div class="col-12" id="container_opcoes_lista" style="display:none;">
                            <label for="modal_opcoes_lista_texto" class="form-label form-label-sm">Opções da Lista <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-sm" id="modal_opcoes_lista_texto" name="opcoes_lista_texto" rows="4" placeholder="Uma opção por linha ou separadas por vírgula. Ex: Sim,Não,Talvez OU Pendente;Em Andamento;Concluído"></textarea>
                            <div class="invalid-feedback">Opções são obrigatórias para tipo lista.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Aplicável a Entidade(s) <span class="text-danger">*</span></label>
                            <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y:auto;">
                            <?php foreach ($entidades_aplicaveis as $key_ent => $label_ent): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="aplicavel_a_entidade[]" value="<?= $key_ent ?>" id="modal_ent_<?= $key_ent ?>">
                                <label class="form-check-label small" for="modal_ent_<?= $key_ent ?>"><?= htmlspecialchars($label_ent) ?></label>
                            </div>
                            <?php endforeach; ?>
                            </div>
                             <small class="form-text text-muted">Onde este campo personalizado poderá ser utilizado.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Disponível para Plano(s) de Assinatura</label>
                             <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y:auto;">
                                <?php if(empty($planos_disponiveis)): ?> <small class="text-muted">Nenhum plano cadastrado.</small>
                                <?php else: foreach ($planos_disponiveis as $plano_disp): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="disponivel_para_planos_ids[]" value="<?= $plano_disp['id'] ?>" id="modal_plano_<?= $plano_disp['id'] ?>">
                                    <label class="form-check-label small" for="modal_plano_<?= $plano_disp['id'] ?>"><?= htmlspecialchars($plano_disp['nome_plano']) ?></label>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                             <small class="form-text text-muted">Se nenhum selecionado, disponível para todos por padrão.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="modal_placeholder_campo" class="form-label form-label-sm">Texto de Exemplo (Placeholder)</label>
                            <input type="text" class="form-control form-control-sm" id="modal_placeholder_campo" name="placeholder_campo" maxlength="150">
                        </div>
                         <div class="col-md-6">
                            <label for="modal_texto_ajuda_campo" class="form-label form-label-sm">Texto de Ajuda (Abaixo do campo)</label>
                            <input type="text" class="form-control form-control-sm" id="modal_texto_ajuda_campo" name="texto_ajuda_campo" maxlength="255">
                        </div>

                        <div class="col-12 mt-3">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="modal_obrigatorio_campo_pers" name="obrigatorio_campo_pers" value="1">
                                <label class="form-check-label small" for="modal_obrigatorio_campo_pers">Obrigatório (usuário deve preencher)</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="modal_ativo_campo_pers" name="ativo_campo_pers" value="1" checked>
                                <label class="form-check-label small" for="modal_ativo_campo_pers">Ativo (disponível para uso na plataforma)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnSalvarModalCampoPers"><i class="fas fa-save me-1"></i> Salvar Campo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validação Bootstrap
    const formsModalCampoPers = document.querySelectorAll('#formModalCampoPers.needs-validation');
    formsModalCampoPers.forEach(form => { /* ... (validação Bootstrap padrão) ... */ });

    const modalCampoPers = document.getElementById('modalCriarEditarCampoPers');
    if (modalCampoPers) {
        const modalForm = modalCampoPers.querySelector('form');
        const modalTitle = modalCampoPers.querySelector('.modal-title');
        // Inputs do form
        const actionInput = modalCampoPers.querySelector('#modal_campo_pers_action');
        const idInput = modalCampoPers.querySelector('#modal_campo_pers_id');
        const nomeInternoInput = modalCampoPers.querySelector('#modal_nome_campo_interno');
        const labelExibicaoInput = modalCampoPers.querySelector('#modal_label_campo_exibicao');
        const tipoCampoSelect = modalCampoPers.querySelector('#modal_tipo_campo');
        const opcoesListaTextarea = modalCampoPers.querySelector('#modal_opcoes_lista_texto');
        const containerOpcoesLista = document.getElementById('container_opcoes_lista');
        const aplicavelCheckboxes = modalCampoPers.querySelectorAll('input[name="aplicavel_a_entidade[]"]');
        const planosCheckboxes = modalCampoPers.querySelectorAll('input[name="disponivel_para_planos_ids[]"]');
        const obrigatorioCheckbox = modalCampoPers.querySelector('#modal_obrigatorio_campo_pers');
        const ativoCheckbox = modalCampoPers.querySelector('#modal_ativo_campo_pers');
        const placeholderInput = modalCampoPers.querySelector('#modal_placeholder_campo');
        const ajudaInput = modalCampoPers.querySelector('#modal_texto_ajuda_campo');
        const modalErrorCP = modalCampoPers.querySelector('#modal_edit_error_placeholder_cp');


        function toggleOpcoesLista() {
            containerOpcoesLista.style.display = (tipoCampoSelect.value.includes('LISTA_OPCOES')) ? 'block' : 'none';
            opcoesListaTextarea.required = tipoCampoSelect.value.includes('LISTA_OPCOES');
        }
        tipoCampoSelect.addEventListener('change', toggleOpcoesLista);

        modalCampoPers.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const action = button ? button.getAttribute('data-action') : 'criar';
            modalForm.classList.remove('was-validated');
            if(modalErrorCP) modalErrorCP.style.display = 'none';

            if (action === 'editar' && button) {
                modalTitle.textContent = 'Editar Campo Personalizado Global';
                actionInput.value = 'salvar_edicao_campo_pers';
                idInput.value = button.dataset.id;
                nomeInternoInput.value = button.dataset.nome_interno;
                nomeInternoInput.readOnly = true; // Não permitir editar nome interno
                labelExibicaoInput.value = button.dataset.label_exibicao;
                tipoCampoSelect.value = button.dataset.tipo_campo;
                opcoesListaTextarea.value = button.dataset.opcoes_lista_texto;
                placeholderInput.value = button.dataset.placeholder;
                ajudaInput.value = button.dataset.texto_ajuda;
                obrigatorioCheckbox.checked = button.dataset.obrigatorio === '1';
                ativoCheckbox.checked = button.dataset.ativo === '1';

                const aplicaveis = JSON.parse(button.dataset.aplicavel_a_entidade_db || '[]');
                aplicavelCheckboxes.forEach(cb => cb.checked = aplicaveis.includes(cb.value));
                const planos = JSON.parse(button.dataset.disponivel_para_planos_ids_db || '[]');
                planosCheckboxes.forEach(cb => cb.checked = planos.includes(parseInt(cb.value)));

            } else {
                modalTitle.textContent = 'Novo Campo Personalizado Global';
                actionInput.value = 'criar_campo_pers';
                idInput.value = '';
                nomeInternoInput.readOnly = false;
                 if (!<?= json_encode(!empty($erro_form_campo_pers) && !$edit_id_cp) ?>) {
                    modalForm.reset();
                    ativoCheckbox.checked = true;
                    obrigatorioCheckbox.checked = false;
                 } else {
                    // Repopular com $form_data_campo_pers (feito pelo PHP nos values dos inputs)
                 }
            }
            toggleOpcoesLista(); // Ajusta visibilidade do campo de opções
        });
        
        <?php if ($erro_form_campo_pers && $edit_id_cp && $campo_pers_para_editar): ?>
        // Lógica para reabrir modal em caso de erro de edição, similar a outras páginas
            const modalInstanceCP = new bootstrap.Modal(modalCampoPers);
            modalCampoPers.querySelector('.modal-title').textContent = 'Editar Campo Personalizado (Verifique Erros)';
            modalCampoPers.querySelector('#modal_campo_pers_action').value = 'salvar_edicao_campo_pers';
            modalCampoPers.querySelector('#modal_campo_pers_id').value = '<?= $edit_id_cp ?>';
            // Os campos já foram populados pelos values do PHP usando $form_data_campo_pers
            // ou $campo_pers_para_editar se $form_data_campo_pers não estiver setado (primeira tentativa de editar com erro)
            modalInstanceCP.show();
        <?php endif; ?>

    } // fim if modalCampoPers
});
</script>

<?php
echo getFooterAdmin();
?>