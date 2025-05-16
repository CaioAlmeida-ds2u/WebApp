<?php
// admin/admin_comunicados_plataforma.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Funções CRUD para comunicados e planos

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Admin da Acoditools
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');
$erro_form_comunicado = $_SESSION['erro_form_comunicado'] ?? null; unset($_SESSION['erro_form_comunicado']);
$form_data_comunicado = $_SESSION['form_data_comunicado'] ?? []; unset($_SESSION['form_data_comunicado']);

// --- Buscar Dados para Dropdowns ---
$planos_disponiveis_para_segmento = listarPlanosAssinatura($conexao, true); // Apenas ativos

// --- Processamento de Ações POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $action_com = $_POST['action'] ?? '';
        $comunicado_id_acao = filter_input(INPUT_POST, 'comunicado_id', FILTER_VALIDATE_INT);

        $dados_comunicado_form = [
            'titulo_comunicado' => trim($_POST['titulo_comunicado'] ?? ''),
            'conteudo_comunicado' => trim($_POST['conteudo_comunicado'] ?? ''), // Considere um editor WYSIWYG no futuro
            'data_publicacao_str' => trim($_POST['data_publicacao'] ?? ''), // Formato YYYY-MM-DDTHH:MM
            'data_expiracao_str' => trim($_POST['data_expiracao'] ?? ''),   // Formato YYYY-MM-DDTHH:MM
            'segmento_planos_ids' => $_POST['segmento_planos_ids'] ?? [], // Array de IDs de planos
            'ativo_comunicado' => isset($_POST['ativo_comunicado']) ? 1 : 0
        ];

        // Validar e converter datas
        $data_publicacao_valida = null;
        if (!empty($dados_comunicado_form['data_publicacao_str'])) {
            try { $dt = new DateTime($dados_comunicado_form['data_publicacao_str']); $data_publicacao_valida = $dt->format('Y-m-d H:i:s'); } catch (Exception $e) { $data_publicacao_valida = false; }
        }
        $data_expiracao_valida = null;
        if (!empty($dados_comunicado_form['data_expiracao_str'])) {
            try { $dt_exp = new DateTime($dados_comunicado_form['data_expiracao_str']); $data_expiracao_valida = $dt_exp->format('Y-m-d H:i:s'); } catch (Exception $e) { $data_expiracao_valida = false; }
        }
         if ($data_publicacao_valida === false) { $_SESSION['erro_form_comunicado'] = "Data de publicação inválida."; $_SESSION['form_data_comunicado'] = $_POST;}
         elseif ($data_expiracao_valida === false) { $_SESSION['erro_form_comunicado'] = "Data de expiração inválida."; $_SESSION['form_data_comunicado'] = $_POST;}
         elseif ($data_publicacao_valida && $data_expiracao_valida && $data_expiracao_valida <= $data_publicacao_valida) { $_SESSION['erro_form_comunicado'] = "Data de expiração deve ser após a data de publicação."; $_SESSION['form_data_comunicado'] = $_POST; }


        // Regenerar token (a menos que haja erro de validação no form de criar/editar)
        $is_form_action_com = in_array($action_com, ['criar_comunicado', 'salvar_edicao_comunicado']);
        $has_form_error_com = !empty($_SESSION['erro_form_comunicado']);
        if (!($is_form_action_com && $has_form_error_com)) {
             $_SESSION['csrf_token'] = gerar_csrf_token();
        }


        switch ($action_com) {
            case 'criar_comunicado':
                if (empty($dados_comunicado_form['titulo_comunicado']) || empty($dados_comunicado_form['conteudo_comunicado']) || !$data_publicacao_valida) {
                    $_SESSION['erro_form_comunicado'] = ($_SESSION['erro_form_comunicado'] ?? '') . (empty($_SESSION['erro_form_comunicado']) ? '' : '<br>') . "Título, Conteúdo e Data de Publicação são obrigatórios.";
                    $_SESSION['form_data_comunicado'] = $_POST;
                } else {
                    $dados_comunicado_form['data_publicacao_db'] = $data_publicacao_valida;
                    $dados_comunicado_form['data_expiracao_db'] = $data_expiracao_valida; // Pode ser null
                    $dados_comunicado_form['segmento_planos_json_db'] = !empty($dados_comunicado_form['segmento_planos_ids']) ? json_encode(array_map('intval', $dados_comunicado_form['segmento_planos_ids'])) : null;

                    // *** Chamar função: criarComunicadoPlataforma($conexao, $dados_comunicado_form, $_SESSION['usuario_id']) ***
                    $res_cria_com = criarComunicadoPlataforma($conexao, $dados_comunicado_form, $_SESSION['usuario_id']);
                    if ($res_cria_com === true) {
                        definir_flash_message('sucesso', "Comunicado '".htmlspecialchars($dados_comunicado_form['titulo_comunicado'])."' criado.");
                    } else {
                        $_SESSION['erro_form_comunicado'] = is_string($res_cria_com) ? $res_cria_com : "Erro ao criar comunicado.";
                        $_SESSION['form_data_comunicado'] = $_POST;
                    }
                }
                break;

            case 'salvar_edicao_comunicado':
                 if ($comunicado_id_acao && !empty($dados_comunicado_form['titulo_comunicado']) && !empty($dados_comunicado_form['conteudo_comunicado']) && $data_publicacao_valida) {
                    $dados_comunicado_form['data_publicacao_db'] = $data_publicacao_valida;
                    $dados_comunicado_form['data_expiracao_db'] = $data_expiracao_valida;
                    $dados_comunicado_form['segmento_planos_json_db'] = !empty($dados_comunicado_form['segmento_planos_ids']) ? json_encode(array_map('intval', $dados_comunicado_form['segmento_planos_ids'])) : null;

                     // *** Chamar função: atualizarComunicadoPlataforma($conexao, $comunicado_id_acao, $dados_comunicado_form, $_SESSION['usuario_id']) ***
                    $res_edit_com = atualizarComunicadoPlataforma($conexao, $comunicado_id_acao, $dados_comunicado_form, $_SESSION['usuario_id']);
                     if ($res_edit_com === true) {
                        definir_flash_message('sucesso', "Comunicado (ID: $comunicado_id_acao) atualizado.");
                    } else {
                        definir_flash_message('erro', is_string($res_edit_com) ? $res_edit_com : "Erro ao atualizar comunicado.");
                    }
                } else {
                     $erro_validacao_edit = $_SESSION['erro_form_comunicado'] ?? "Dados inválidos para edição do Comunicado.";
                     if (empty($_SESSION['erro_form_comunicado']) && (empty($dados_comunicado_form['titulo_comunicado']) || empty($dados_comunicado_form['conteudo_comunicado']) || !$data_publicacao_valida)){
                        $erro_validacao_edit = "Título, Conteúdo e Data de Publicação são obrigatórios.";
                     }
                    definir_flash_message('erro', $erro_validacao_edit);
                }
                break;
            // Cases para ativar/desativar/excluir
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}


// --- Busca de Dados para Exibição ---
// *** Chamar função: listarComunicadosPlataformaPaginado($conexao, ...) ***
$lista_comunicados = listarComunicadosPlataformaPaginado($conexao); // Simplificado, sem paginação por enquanto

// Para o modal de edição
$comunicado_para_editar = null;
$edit_id_com = filter_input(INPUT_GET, 'edit_com_id', FILTER_VALIDATE_INT);
if ($edit_id_com) {
    $comunicado_para_editar = getComunicadoPlataformaPorId($conexao, $edit_id_com);
    if (!$comunicado_para_editar) definir_flash_message('erro', "Comunicado para edição não encontrado (ID: $edit_id_com).");
    else {
        $comunicado_para_editar['segmento_planos_ids'] = json_decode($comunicado_para_editar['segmento_planos_ids_json'] ?? '[]', true);
        // Formatar datas para datetime-local input
        $comunicado_para_editar['data_publicacao_str'] = $comunicado_para_editar['data_publicacao'] ? (new DateTime($comunicado_para_editar['data_publicacao']))->format('Y-m-d\TH:i') : '';
        $comunicado_para_editar['data_expiracao_str'] = $comunicado_para_editar['data_expiracao'] ? (new DateTime($comunicado_para_editar['data_expiracao']))->format('Y-m-d\TH:i') : '';
    }
}

if (empty($_SESSION['csrf_token']) || ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$edit_id_com) || !empty($erro_form_comunicado)) {
    $_SESSION['csrf_token'] = gerar_csrf_token();
}
$csrf_token_page = $_SESSION['csrf_token'];

$title = "ACodITools - Gerenciar Comunicados da Plataforma";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-bullhorn me-2"></i>Gerenciar Comunicados da Plataforma</h1>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="modal" data-bs-target="#modalCriarEditarComunicado" data-action="criar">
            <i class="fas fa-plus me-1"></i> Novo Comunicado
        </button>
    </div>

    <?php if ($sucesso_msg): /* ... Mensagens ... */ endif; ?>
    <?php if ($erro_msg): /* ... Mensagens ... */ endif; ?>
    <?php if ($erro_form_comunicado && !$edit_id_com): ?>
        <div class="alert alert-warning alert-dismissible fade show small" role="alert">
            <strong>Erro ao criar:</strong> <?= $erro_form_comunicado ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-header bg-light"><h6 class="mb-0 fw-bold"><i class="fas fa-list-ul me-2 text-primary opacity-75"></i>Comunicados Publicados/Agendados</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Publicação</th>
                            <th>Expiração</th>
                            <th>Segmento</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_comunicados)): ?>
                            <tr><td colspan="7" class="text-center text-muted p-4">Nenhum comunicado cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_comunicados as $com):
                                $planos_ids_decoded_list = json_decode($com['segmento_planos_ids_json'] ?? '[]', true);
                                $planos_nomes_list_arr = [];
                                if(is_array($planos_ids_decoded_list) && !empty($planos_ids_decoded_list)){
                                    foreach($planos_ids_decoded_list as $pid_list){
                                        foreach($planos_disponiveis as $pl_list){ if($pl_list['id'] == $pid_list){$planos_nomes_list_arr[] = $pl_list['nome_plano']; break;}}
                                    }
                                }
                                $segmento_display = !empty($planos_nomes_list_arr) ? implode(', ', $planos_nomes_list_arr) : 'Todos Clientes';
                            ?>
                            <tr>
                                <td class="fw-bold">#<?= htmlspecialchars($com['id']) ?></td>
                                <td title="<?= htmlspecialchars($com['titulo_comunicado']) ?>"><?= htmlspecialchars(mb_strimwidth($com['titulo_comunicado'],0,50,"...")) ?></td>
                                <td class="small"><?= htmlspecialchars(formatarDataCompleta($com['data_publicacao'])) ?></td>
                                <td class="small"><?= htmlspecialchars(formatarDataCompleta($com['data_expiracao'], 'd/m/Y H:i', 'Não expira')) ?></td>
                                <td class="small text-muted" title="<?= htmlspecialchars($segmento_display) ?>"><?= htmlspecialchars(mb_strimwidth($segmento_display,0,30,"...")) ?></td>
                                <td class="text-center"><span class="badge <?= $com['ativo'] ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= $com['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
                                <td class="text-center action-buttons-table">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1 action-btn btn-edit-comunicado"
                                            data-bs-toggle="modal" data-bs-target="#modalCriarEditarComunicado"
                                            data-id="<?= $com['id'] ?>"
                                            data-titulo="<?= htmlspecialchars($com['titulo_comunicado']) ?>"
                                            data-conteudo="<?= htmlspecialchars($com['conteudo_comunicado']) ?>"
                                            data-publicacao="<?= $com['data_publicacao'] ? (new DateTime($com['data_publicacao']))->format('Y-m-d\TH:i') : '' ?>"
                                            data-expiracao="<?= $com['data_expiracao'] ? (new DateTime($com['data_expiracao']))->format('Y-m-d\TH:i') : '' ?>"
                                            data-segmento_json="<?= htmlspecialchars($com['segmento_planos_ids_json'] ?? '[]') ?>"
                                            data-ativo="<?= $com['ativo'] ?>"
                                            title="Editar Comunicado"><i class="fas fa-edit fa-fw"></i>
                                    </button>
                                    <?php /* Botões Ativar/Desativar/Excluir */ ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php /* Paginação */ ?>
    </div>
</div>

<!-- Modal para Criar/Editar Comunicado -->
<div class="modal fade" id="modalCriarEditarComunicado" tabindex="-1" aria-labelledby="modalComunicadoLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="formModalComunicado" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                <input type="hidden" name="action" id="modal_comunicado_action" value="criar_comunicado">
                <input type="hidden" name="comunicado_id" id="modal_comunicado_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalComunicadoLabel">Novo Comunicado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if ($erro_form_comunicado && $edit_id_com): ?>
                        <div class="alert alert-warning small p-2" role="alert"><?= $erro_form_comunicado ?></div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="modal_titulo_comunicado" class="form-label form-label-sm">Título do Comunicado <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="modal_titulo_comunicado" name="titulo_comunicado" required maxlength="255" value="<?= htmlspecialchars($form_data_comunicado['titulo_comunicado'] ?? ($comunicado_para_editar['titulo_comunicado'] ?? '')) ?>">
                        <div class="invalid-feedback">Título é obrigatório.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_conteudo_comunicado" class="form-label form-label-sm">Conteúdo <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="modal_conteudo_comunicado" name="conteudo_comunicado" rows="8" required><?= htmlspecialchars($form_data_comunicado['conteudo_comunicado'] ?? ($comunicado_para_editar['conteudo_comunicado'] ?? '')) ?></textarea>
                        <small class="form-text text-muted">Você pode usar HTML básico para formatação.</small>
                        <div class="invalid-feedback">Conteúdo é obrigatório.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="modal_data_publicacao" class="form-label form-label-sm">Data e Hora de Publicação <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control form-control-sm" id="modal_data_publicacao" name="data_publicacao" required value="<?= htmlspecialchars($form_data_comunicado['data_publicacao'] ?? ($comunicado_para_editar['data_publicacao_str'] ?? '')) ?>">
                            <div class="invalid-feedback">Data de publicação inválida/obrigatória.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_data_expiracao" class="form-label form-label-sm">Data e Hora de Expiração (Opcional)</label>
                            <input type="datetime-local" class="form-control form-control-sm" id="modal_data_expiracao" name="data_expiracao" value="<?= htmlspecialchars($form_data_comunicado['data_expiracao'] ?? ($comunicado_para_editar['data_expiracao_str'] ?? '')) ?>">
                            <small class="form-text text-muted">Deixe em branco se não expira.</small>
                             <div class="invalid-feedback">Data de expiração inválida (deve ser após publicação).</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-3">
                        <label class="form-label form-label-sm">Segmentar para Planos de Assinatura Específicos:</label>
                        <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y:auto;">
                            <?php if(empty($planos_disponiveis_para_segmento)): ?>
                                <small class="text-muted">Nenhum plano de assinatura ativo para segmentação.</small>
                            <?php else: foreach($planos_disponiveis_para_segmento as $plano_seg): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="segmento_planos_ids[]" value="<?= $plano_seg['id'] ?>" id="modal_seg_plano_<?= $plano_seg['id'] ?>"
                                        <?php
                                        $planos_selecionados_form = $form_data_comunicado['segmento_planos_ids'] ?? ($comunicado_para_editar['segmento_planos_ids'] ?? []);
                                        if (in_array($plano_seg['id'], $planos_selecionados_form)) echo 'checked';
                                        ?>
                                    >
                                    <label class="form-check-label small" for="modal_seg_plano_<?= $plano_seg['id'] ?>"><?= htmlspecialchars($plano_seg['nome_plano']) ?></label>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <small class="form-text text-muted">Se nenhum selecionado, o comunicado será para todos os clientes.</small>
                    </div>
                    <div class="form-check form-switch">
                         <input class="form-check-input" type="checkbox" role="switch" id="modal_ativo_comunicado" name="ativo_comunicado" value="1"
                         <?= ($edit_id_com && $comunicado_para_editar) ? (!empty($form_data_comunicado) ? ($form_data_comunicado['ativo_comunicado'] ?? 0) : $comunicado_para_editar['ativo']) ? 'checked' : '' : (!empty($form_data_comunicado) ? ($form_data_comunicado['ativo_comunicado'] ?? 0) : 'checked') ?>
                         >
                        <label class="form-check-label small" for="modal_ativo_comunicado">Comunicado Ativo/Publicado</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane me-1"></i> Salvar Comunicado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalComunicado = document.getElementById('modalCriarEditarComunicado');
    if (modalComunicado) {
        const form = modalComunicado.querySelector('form');
        // ... (lógica JS para popular o modal em modo de edição, similar às outras páginas)
        // Lembre-se de pegar os atributos data-* dos botões de editar e preencher o form.
        // Ex: modalNomeInput.value = button.dataset.titulo;
        // Para os checkboxes de planos, você precisará iterar e marcar os corretos.
        modalComunicado.addEventListener('show.bs.modal', function(event){
            const button = event.relatedTarget;
            const action = button ? button.getAttribute('data-action') : 'criar';
            const modalTitle = modalComunicado.querySelector('.modal-title');
            const actionInput = modalComunicado.querySelector('#modal_comunicado_action');
            const idInput = modalComunicado.querySelector('#modal_comunicado_id');
            const tituloInput = modalComunicado.querySelector('#modal_titulo_comunicado');
            const conteudoTextarea = modalComunicado.querySelector('#modal_conteudo_comunicado');
            const publicacaoInput = modalComunicado.querySelector('#modal_data_publicacao');
            const expiracaoInput = modalComunicado.querySelector('#modal_data_expiracao');
            const ativoCheckbox = modalComunicado.querySelector('#modal_ativo_comunicado');
            const planosCheckboxesModal = modalComunicado.querySelectorAll('input[name="segmento_planos_ids[]"]');
            const errorPlaceholderCom = modalComunicado.querySelector('.alert-warning'); // Se houver um placeholder para erros de edição

            form.classList.remove('was-validated');
            if(errorPlaceholderCom) errorPlaceholderCom.style.display = 'none';

            if(action === 'editar' && button) {
                modalTitle.textContent = 'Editar Comunicado';
                actionInput.value = 'salvar_edicao_comunicado';
                idInput.value = button.dataset.id;
                tituloInput.value = button.dataset.titulo;
                conteudoTextarea.value = button.dataset.conteudo; // Cuidado com HTML aqui
                publicacaoInput.value = button.dataset.publicacao;
                expiracaoInput.value = button.dataset.expiracao;
                ativoCheckbox.checked = button.dataset.ativo === '1';

                const planosSegmentoSalvos = JSON.parse(button.dataset.segmento_json || '[]');
                planosCheckboxesModal.forEach(cb => {
                    cb.checked = planosSegmentoSalvos.includes(parseInt(cb.value));
                });

            } else {
                modalTitle.textContent = 'Novo Comunicado';
                actionInput.value = 'criar_comunicado';
                idInput.value = '';
                if (!<?= json_encode(!empty($erro_form_comunicado) && !$edit_id_com) ?>) { // Se não for repopulação de erro
                    form.reset();
                    ativoCheckbox.checked = true;
                    planosCheckboxesModal.forEach(cb => cb.checked = false); // Desmarcar todos os planos
                }
                 // Se for repopulação de erro de criação, os values PHP já fizeram o trabalho
            }
        });

         <?php if ($erro_form_comunicado && $edit_id_com && $comunicado_para_editar): ?>
            const modalInstanceCom = new bootstrap.Modal(modalComunicado);
            modalComunicado.querySelector('.modal-title').textContent = 'Editar Comunicado (Verifique Erros)';
            modalComunicado.querySelector('#modal_comunicado_action').value = 'salvar_edicao_comunicado';
            modalComunicado.querySelector('#modal_comunicado_id').value = '<?= $edit_id_com ?>';
            modalInstanceCom.show();
        <?php endif; ?>
    }
});
</script>

<?php
echo getFooterAdmin();
?>