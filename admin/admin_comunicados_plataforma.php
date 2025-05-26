<?php
// admin/admin_comunicados_plataforma.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');
$erro_form_comunicado = $_SESSION['erro_form_comunicado'] ?? null; unset($_SESSION['erro_form_comunicado']);
$form_data_comunicado = $_SESSION['form_data_comunicado'] ?? []; unset($_SESSION['form_data_comunicado']);

// Carrega TODOS os planos para exibir nomes na lista, mesmo que um plano associado
// a um comunicado antigo tenha sido desativado.
$todos_os_planos_sistema = listarPlanosAssinatura($conexao, false); // false para pegar todos

// Filtra apenas os planos ATIVOS para o modal de criação/edição (onde se segmenta)
$planos_ativos_para_modal = [];
if (is_array($todos_os_planos_sistema)) {
    $planos_ativos_para_modal = array_filter($todos_os_planos_sistema, function($plano) {
        return isset($plano['ativo']) && $plano['ativo'] == 1;
    });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $action_com = $_POST['action'] ?? '';
        $comunicado_id_acao = filter_input(INPUT_POST, 'comunicado_id', FILTER_VALIDATE_INT);

        $dados_comunicado_form = [
            'titulo_comunicado' => trim($_POST['titulo_comunicado'] ?? ''),
            'conteudo_comunicado' => trim($_POST['conteudo_comunicado'] ?? ''),
            'data_publicacao_str' => trim($_POST['data_publicacao'] ?? ''),
            'data_expiracao_str' => trim($_POST['data_expiracao'] ?? ''),
            'segmento_planos_ids' => $_POST['segmento_planos_ids'] ?? [],
            'ativo_comunicado' => isset($_POST['ativo_comunicado']) ? 1 : 0,
            // 'usuario_criacao_id' e 'usuario_modificacao_id' são gerenciados nas funções de backend
        ];

        $data_publicacao_valida = null;
        if (!empty($dados_comunicado_form['data_publicacao_str'])) {
            try { $dt = new DateTime($dados_comunicado_form['data_publicacao_str']); $data_publicacao_valida = $dt->format('Y-m-d H:i:s'); } catch (Exception $e) { $data_publicacao_valida = false; }
        }
        $data_expiracao_valida = null;
        if (!empty($dados_comunicado_form['data_expiracao_str'])) {
            try { $dt_exp = new DateTime($dados_comunicado_form['data_expiracao_str']); $data_expiracao_valida = $dt_exp->format('Y-m-d H:i:s'); } catch (Exception $e) { $data_expiracao_valida = false; }
        }

        $erros_validacao_form = false;
        if ($data_publicacao_valida === false) { $_SESSION['erro_form_comunicado'] = "Data de publicação inválida."; $erros_validacao_form = true;}
        elseif ($data_expiracao_valida === false) { $_SESSION['erro_form_comunicado'] = "Data de expiração inválida."; $erros_validacao_form = true;}
        elseif ($data_publicacao_valida && $data_expiracao_valida && $data_expiracao_valida <= $data_publicacao_valida) { $_SESSION['erro_form_comunicado'] = "Data de expiração deve ser após a data de publicação."; $erros_validacao_form = true; }

        if (empty($dados_comunicado_form['titulo_comunicado'])) {
            $_SESSION['erro_form_comunicado'] = ($_SESSION['erro_form_comunicado'] ?? '') . (empty($_SESSION['erro_form_comunicado']) ? '' : '<br>') . "Título é obrigatório."; $erros_validacao_form = true;
        }
        if (empty($dados_comunicado_form['conteudo_comunicado'])) {
             $_SESSION['erro_form_comunicado'] = ($_SESSION['erro_form_comunicado'] ?? '') . (empty($_SESSION['erro_form_comunicado']) ? '' : '<br>') . "Conteúdo é obrigatório."; $erros_validacao_form = true;
        }
        if (!$data_publicacao_valida && !isset($_SESSION['erro_form_comunicado'])) { // Se a data já não deu erro, mas é obrigatória e não foi válida
            $_SESSION['erro_form_comunicado'] = ($_SESSION['erro_form_comunicado'] ?? '') . (empty($_SESSION['erro_form_comunicado']) ? '' : '<br>') . "Data de Publicação é obrigatória."; $erros_validacao_form = true;
        }


        if($erros_validacao_form) {
            $_SESSION['form_data_comunicado'] = $_POST;
        }

        $is_form_crud_action = in_array($action_com, ['criar_comunicado', 'salvar_edicao_comunicado']);
        if (!($is_form_crud_action && $erros_validacao_form)) { // Não regenera token se for erro de validação do form de criar/editar
             $_SESSION['csrf_token'] = gerar_csrf_token();
        }

        if (!$erros_validacao_form) {
            switch ($action_com) {
                case 'criar_comunicado':
                    $dados_comunicado_form['data_publicacao_db'] = $data_publicacao_valida;
                    $dados_comunicado_form['data_expiracao_db'] = $data_expiracao_valida;
                    $dados_comunicado_form['segmento_planos_json_db'] = !empty($dados_comunicado_form['segmento_planos_ids']) ? json_encode(array_map('intval', $dados_comunicado_form['segmento_planos_ids'])) : null;
                    
                    $res_cria_com = criarComunicadoPlataforma($conexao, $dados_comunicado_form, $_SESSION['usuario_id']);
                    if ($res_cria_com === true) {
                        definir_flash_message('sucesso', "Comunicado '".htmlspecialchars($dados_comunicado_form['titulo_comunicado'])."' criado.");
                    } else {
                        $_SESSION['erro_form_comunicado'] = is_string($res_cria_com) ? $res_cria_com : "Erro ao criar comunicado.";
                        $_SESSION['form_data_comunicado'] = $_POST;
                    }
                    break;

                case 'salvar_edicao_comunicado':
                    // Validação de ID de comunicado aqui é importante.
                    if (!$comunicado_id_acao) {
                         definir_flash_message('erro', 'ID do comunicado para edição não fornecido.');
                         break; // Sai do switch
                    }
                    $dados_comunicado_form['data_publicacao_db'] = $data_publicacao_valida;
                    $dados_comunicado_form['data_expiracao_db'] = $data_expiracao_valida;
                    $dados_comunicado_form['segmento_planos_json_db'] = !empty($dados_comunicado_form['segmento_planos_ids']) ? json_encode(array_map('intval', $dados_comunicado_form['segmento_planos_ids'])) : null;
                    
                    $res_edit_com = atualizarComunicadoPlataforma($conexao, $comunicado_id_acao, $dados_comunicado_form, $_SESSION['usuario_id']);
                    if ($res_edit_com === true) {
                        definir_flash_message('sucesso', "Comunicado (ID: $comunicado_id_acao) atualizado.");
                    } else {
                        // Guarda erro na sessão para ser exibido DENTRO do modal de edição
                        $_SESSION['erro_form_comunicado'] = is_string($res_edit_com) ? $res_edit_com : "Erro ao atualizar comunicado.";
                        $_SESSION['form_data_comunicado'] = $_POST; // Para repopular
                        $_SESSION['form_data_comunicado']['comunicado_id_com_erro'] = $comunicado_id_acao; // Para identificar qual modal abrir com erro
                    }
                    break;

                case 'setStatusComunicado':
                    $novo_status_com = filter_input(INPUT_POST, 'novo_status', FILTER_VALIDATE_INT);
                    if ($comunicado_id_acao && ($novo_status_com === 0 || $novo_status_com === 1)) {
                        if(setStatusComunicadoPlataforma($conexao, $comunicado_id_acao, (bool)$novo_status_com, $_SESSION['usuario_id'])) {
                            definir_flash_message('sucesso', 'Status do comunicado atualizado.');
                        } else {
                            definir_flash_message('erro', 'Erro ao atualizar status do comunicado.');
                        }
                    } else {
                         definir_flash_message('erro', 'Dados inválidos para alterar status.');
                    }
                    break;
                case 'excluirComunicado':
                    if ($comunicado_id_acao) {
                        $res_del_com = excluirComunicadoPlataforma($conexao, $comunicado_id_acao, $_SESSION['usuario_id']);
                        if ($res_del_com === true) {
                            definir_flash_message('sucesso', "Comunicado ID $comunicado_id_acao excluído.");
                        } else {
                            definir_flash_message('erro', is_string($res_del_com) ? $res_del_com : "Erro ao excluir comunicado.");
                        }
                    } else {
                        definir_flash_message('erro', 'ID inválido para exclusão.');
                    }
                    break;
                default:
                    definir_flash_message('info', 'Ação desconhecida.');
            }
        }
    }
    // Limpar Query String após POST para evitar reabertura de modal de edição por URL
    // E para garantir que as mensagens flash sejam a principal fonte de feedback.
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// --- Busca de Dados para Exibição ---
$pagina_atual_com = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina_com = 10;
$comunicadosPaginados = listarComunicadosPlataformaPaginado($conexao, $pagina_atual_com, $itens_por_pagina_com); // Função precisa existir e funcionar com paginação
$lista_comunicados = $comunicadosPaginados['comunicados'] ?? [];
$paginacao_com = $comunicadosPaginados['paginacao'] ?? ['total_itens' => 0, 'total_paginas' => 0, 'pagina_atual' => 1];

$comunicado_para_editar = null;
$edit_id_com_get_param = filter_input(INPUT_GET, 'edit_com_id', FILTER_VALIDATE_INT);
// Verifica também se há um erro de formulário para um ID específico para forçar a reabertura do modal de edição
$reabrir_modal_edicao_id = $_SESSION['form_data_comunicado']['comunicado_id_com_erro'] ?? null;

if ($edit_id_com_get_param || $reabrir_modal_edicao_id) {
    $id_para_buscar = $reabrir_modal_edicao_id ?: $edit_id_com_get_param;
    $comunicado_para_editar = getComunicadoPlataformaPorId($conexao, $id_para_buscar);
    if (!$comunicado_para_editar && !$reabrir_modal_edicao_id) { // Só mostra erro se não for reabertura de modal
        definir_flash_message('erro', "Comunicado para edição não encontrado (ID: $id_para_buscar).");
    } elseif ($comunicado_para_editar) {
        $comunicado_para_editar['segmento_planos_ids'] = json_decode($comunicado_para_editar['segmento_planos_ids_json'] ?? '[]', true);
        if (!is_array($comunicado_para_editar['segmento_planos_ids'])) $comunicado_para_editar['segmento_planos_ids'] = [];
        
        $comunicado_para_editar['data_publicacao_str'] = '';
        if (isset($comunicado_para_editar['data_publicacao']) && $comunicado_para_editar['data_publicacao']) {
            try { $comunicado_para_editar['data_publicacao_str'] = (new DateTime($comunicado_para_editar['data_publicacao']))->format('Y-m-d\TH:i'); } catch (Exception $e) {}
        }
        $comunicado_para_editar['data_expiracao_str'] = '';
        if (isset($comunicado_para_editar['data_expiracao']) && $comunicado_para_editar['data_expiracao']) {
            try { $comunicado_para_editar['data_expiracao_str'] = (new DateTime($comunicado_para_editar['data_expiracao']))->format('Y-m-d\TH:i'); } catch (Exception $e) {}
        }
    }
    // Se estiver reabrindo com erro, $form_data_comunicado (sessão) terá prioridade no JS do modal
}


if (empty($_SESSION['csrf_token']) || ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$edit_id_com_get_param && !$reabrir_modal_edicao_id) || !empty($erro_form_comunicado) ) {
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

    <?php if ($sucesso_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($erro_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
    
    <?php if ($erro_form_comunicado && !($edit_id_com_get_param || $reabrir_modal_edicao_id) ): /* Mostrar erro de CRIAÇÃO aqui, erros de edição vão para o modal */?>
        <div class="alert alert-warning alert-dismissible fade show small" role="alert">
            <strong>Erro ao criar:</strong> <?= $erro_form_comunicado ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-header bg-light"><h6 class="mb-0 fw-bold"><i class="fas fa-list-ul me-2 text-primary opacity-75"></i>Comunicados (<?= htmlspecialchars($paginacao_com['total_itens'] ?? 0) ?>)</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th>ID</th><th>Título</th><th>Publicação</th><th>Expiração</th><th>Segmento</th><th class="text-center">Status</th><th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_comunicados)): ?>
                            <tr><td colspan="7" class="text-center text-muted p-4">Nenhum comunicado cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_comunicados as $com):
                                $planos_ids_decoded_list_item = json_decode($com['segmento_planos_ids_json'] ?? '[]', true);
                                $planos_nomes_item_arr = [];
                                if (is_array($planos_ids_decoded_list_item) && !empty($planos_ids_decoded_list_item) && is_array($todos_os_planos_sistema)) {
                                    foreach ($planos_ids_decoded_list_item as $pid_item) {
                                        foreach ($todos_os_planos_sistema as $pl_sis) {
                                            if (isset($pl_sis['id']) && $pl_sis['id'] == $pid_item) {
                                                $planos_nomes_item_arr[] = htmlspecialchars($pl_sis['nome_plano']);
                                                break;
                                            }
                                        }
                                    }
                                }
                                $segmento_display_item = !empty($planos_nomes_item_arr) ? implode(', ', $planos_nomes_item_arr) : 'Todos Clientes';
                            ?>
                            <tr class="<?= isset($com['ativo']) && !$com['ativo'] ? 'table-light text-muted opacity-75' : '' ?>">
                                <td class="fw-bold">#<?= htmlspecialchars($com['id']) ?></td>
                                <td title="<?= htmlspecialchars($com['titulo_comunicado']) ?>"><?= htmlspecialchars(mb_strimwidth($com['titulo_comunicado'],0,45,"...")) ?></td>
                                <td class="small"><?= htmlspecialchars(formatarDataCompleta($com['data_publicacao'] ?? null)) ?></td>
                                <td class="small"><?= htmlspecialchars(formatarDataCompleta($com['data_expiracao'] ?? null, 'd/m/Y H:i', 'Não expira')) ?></td>
                                <td class="small text-muted" title="<?= htmlspecialchars($segmento_display_item) ?>"><?= htmlspecialchars(mb_strimwidth($segmento_display_item,0,30,"...")) ?></td>
                                <td class="text-center">
                                    <form method="POST" action="<?= strtok($_SERVER['REQUEST_URI'], '?') // Mantém paginação da página principal ?>" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                                        <input type="hidden" name="action" value="setStatusComunicado">
                                        <input type="hidden" name="comunicado_id" value="<?= $com['id'] ?>">
                                        <input type="hidden" name="novo_status" value="<?= isset($com['ativo']) && $com['ativo'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm border-0 p-0" title="<?= isset($com['ativo']) && $com['ativo'] ? 'Clique para Desativar' : 'Clique para Ativar' ?>" style="cursor: pointer;">
                                            <span class="badge rounded-pill <?= isset($com['ativo']) && $com['ativo'] ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                                                <?= isset($com['ativo']) && $com['ativo'] ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center action-buttons-table">
                                     <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>?edit_com_id=<?= $com['id'] ?><?= ($pagina_atual_com > 1 ? '&pagina='.$pagina_atual_com : '') ?>" class="btn btn-sm btn-outline-primary me-1 action-btn" title="Editar Comunicado"><i class="fas fa-edit fa-fw"></i></a>
                                     <form method="POST" action="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este comunicado?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                                        <input type="hidden" name="action" value="excluirComunicado">
                                        <input type="hidden" name="comunicado_id" value="<?= $com['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Excluir Comunicado">
                                            <i class="fas fa-trash-alt fa-fw"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (isset($paginacao_com) && $paginacao_com['total_paginas'] > 1): ?>
        <div class="card-footer bg-light py-2">
            <nav aria-label="Paginação de Comunicados">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php $link_pag_com = "?pagina="; ?>
                    <?php if ($paginacao_com['pagina_atual'] > 1): ?><li class="page-item"><a class="page-link" href="<?= $link_pag_com . ($paginacao_com['pagina_atual'] - 1) ?>">«</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">«</span></li><?php endif; ?>
                    <?php $inicio_pc = max(1, $paginacao_com['pagina_atual'] - 2); $fim_pc = min($paginacao_com['total_paginas'], $paginacao_com['pagina_atual'] + 2); if($inicio_pc > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; for($i_pc = $inicio_pc; $i_pc <= $fim_pc; $i_pc++): ?><li class="page-item <?= ($i_pc == $paginacao_com['pagina_atual'])?'active':'' ?>"><a class="page-link" href="<?= $link_pag_com . $i_pc ?>"><?= $i_pc ?></a></li><?php endfor; if($fim_pc < $paginacao_com['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                    <?php if ($paginacao_com['pagina_atual'] < $paginacao_com['total_paginas']): ?><li class="page-item"><a class="page-link" href="<?= $link_pag_com . ($paginacao_com['pagina_atual'] + 1) ?>">»</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">»</span></li><?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Criar/Editar Comunicado -->
<div class="modal fade" id="modalCriarEditarComunicado" tabindex="-1" aria-labelledby="modalComunicadoLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <!-- O action do form do modal é a página atual, mas SEM query string (para evitar que ?edit_com_id=X interfira no submit de CRIAR) -->
            <form method="POST" action="<?= strtok($_SERVER['PHP_SELF'], '?') ?>" id="formModalComunicado" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                <input type="hidden" name="action" id="modal_comunicado_action" value="criar_comunicado">
                <input type="hidden" name="comunicado_id" id="modal_comunicado_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalComunicadoLabel">Novo Comunicado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-warning small p-2" role="alert" id="modal_error_placeholder_com" style="display:none;"></div>

                    <div class="mb-3">
                        <label for="modal_titulo_comunicado" class="form-label form-label-sm">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="modal_titulo_comunicado" name="titulo_comunicado" required maxlength="255">
                        <div class="invalid-feedback">Título é obrigatório.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_conteudo_comunicado" class="form-label form-label-sm">Conteúdo <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="modal_conteudo_comunicado" name="conteudo_comunicado" rows="8" required></textarea>
                        <small class="form-text text-muted">HTML básico permitido.</small>
                        <div class="invalid-feedback">Conteúdo é obrigatório.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="modal_data_publicacao" class="form-label form-label-sm">Data Publicação <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control form-control-sm" id="modal_data_publicacao" name="data_publicacao" required>
                            <div class="invalid-feedback">Data de publicação inválida/obrigatória.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_data_expiracao" class="form-label form-label-sm">Data Expiração (Opcional)</label>
                            <input type="datetime-local" class="form-control form-control-sm" id="modal_data_expiracao" name="data_expiracao">
                            <small class="form-text text-muted">Deixe em branco se não expira.</small>
                             <div class="invalid-feedback">Data de expiração deve ser após publicação.</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-3">
                        <label class="form-label form-label-sm">Segmentar para Planos:</label>
                        <div class="border rounded p-2 bg-light-subtle" style="max-height: 150px; overflow-y:auto;">
                            <?php if(empty($planos_ativos_para_modal)): ?>
                                <small class="text-muted">Nenhum plano ativo para segmentação.</small>
                            <?php else: foreach($planos_ativos_para_modal as $plano_modal_item): ?>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input" type="checkbox" name="segmento_planos_ids[]" value="<?= $plano_modal_item['id'] ?>" id="modal_seg_plano_<?= $plano_modal_item['id'] ?>">
                                    <label class="form-check-label" for="modal_seg_plano_<?= $plano_modal_item['id'] ?>"><?= htmlspecialchars($plano_modal_item['nome_plano']) ?></label>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <small class="form-text text-muted">Se nenhum selecionado, será para todos clientes.</small>
                    </div>
                    <div class="form-check form-switch">
                         <input class="form-check-input" type="checkbox" role="switch" id="modal_ativo_comunicado" name="ativo_comunicado" value="1">
                        <label class="form-check-label small" for="modal_ativo_comunicado">Comunicado Ativo/Publicado</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnSalvarModalComunicado"><i class="fas fa-save me-1"></i> Salvar Comunicado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalComunicadoEl = document.getElementById('modalCriarEditarComunicado');
    if (modalComunicadoEl) {
        const formModal = modalComunicadoEl.querySelector('form');
        const modalTitleEl = modalComunicadoEl.querySelector('.modal-title');
        const actionInputEl = modalComunicadoEl.querySelector('#modal_comunicado_action');
        const idInputEl = modalComunicadoEl.querySelector('#modal_comunicado_id');
        const tituloInputEl = modalComunicadoEl.querySelector('#modal_titulo_comunicado');
        const conteudoTextareaEl = modalComunicadoEl.querySelector('#modal_conteudo_comunicado');
        const publicacaoInputEl = modalComunicadoEl.querySelector('#modal_data_publicacao');
        const expiracaoInputEl = modalComunicadoEl.querySelector('#modal_data_expiracao');
        const ativoCheckboxEl = modalComunicadoEl.querySelector('#modal_ativo_comunicado');
        const planosCheckboxesModalEl = modalComunicadoEl.querySelectorAll('input[name="segmento_planos_ids[]"]');
        const errorPlaceholderModalEl = modalComunicadoEl.querySelector('#modal_error_placeholder_com');

        // Dados do PHP para JS (para repopulação e controle)
        const phpErroForm = <?= json_encode($erro_form_comunicado) ?>;
        const phpEditIdGet = <?= json_encode($edit_id_com_get_param) ?>; // Usar a var do GET
        const phpComunicadoEditarObj = <?= json_encode($comunicado_para_editar) ?>;
        const phpFormDataSessao = <?= json_encode($form_data_comunicado) ?>;

        modalComunicadoEl.addEventListener('show.bs.modal', function(event){
            const button = event.relatedTarget;
            const action = button ? button.dataset.action : 'criar'; // Ação padrão para novo

            formModal.classList.remove('was-validated');
            if(errorPlaceholderModalEl) { errorPlaceholderModalEl.style.display = 'none'; errorPlaceholderModalEl.innerHTML = ''; }

            let comunicadoData = { // Estrutura para popular o modal
                id: '', titulo_comunicado: '', conteudo_comunicado: '',
                data_publicacao_str: '', data_expiracao_str: '',
                segmento_planos_ids: [], ativo: true // Default ativo para novo
            };

            if (action === 'editar' && button) {
                modalTitleEl.textContent = 'Editar Comunicado';
                actionInputEl.value = 'salvar_edicao_comunicado';
                comunicadoData.id = button.dataset.id;

                // Prioriza dados da sessão (se houver erro POST na edição deste item)
                if (phpErroForm && phpFormDataSessao && phpFormDataSessao.comunicado_id == button.dataset.id) {
                    comunicadoData.titulo_comunicado = phpFormDataSessao.titulo_comunicado || '';
                    comunicadoData.conteudo_comunicado = phpFormDataSessao.conteudo_comunicado || '';
                    comunicadoData.data_publicacao_str = phpFormDataSessao.data_publicacao || '';
                    comunicadoData.data_expiracao_str = phpFormDataSessao.data_expiracao || '';
                    comunicadoData.ativo = phpFormDataSessao.ativo_comunicado == '1';
                    comunicadoData.segmento_planos_ids = Array.isArray(phpFormDataSessao.segmento_planos_ids) ? phpFormDataSessao.segmento_planos_ids.map(id => parseInt(id)) : [];
                    if(errorPlaceholderModalEl) { errorPlaceholderModalEl.innerHTML = phpErroForm; errorPlaceholderModalEl.style.display = 'block';}
                } else { // Senão, usa os data attributes do botão (que vêm do DB)
                    comunicadoData.titulo_comunicado = button.dataset.titulo;
                    comunicadoData.conteudo_comunicado = button.dataset.conteudo;
                    comunicadoData.data_publicacao_str = button.dataset.publicacao;
                    comunicadoData.data_expiracao_str = button.dataset.expiracao;
                    comunicadoData.ativo = button.dataset.ativo === '1';
                    comunicadoData.segmento_planos_ids = JSON.parse(button.dataset.segmento_json || '[]');
                }
            } else { // Ação é 'criar'
                modalTitleEl.textContent = 'Novo Comunicado';
                actionInputEl.value = 'criar_comunicado';
                // Prioriza dados da sessão se houver erro na criação
                if (phpErroForm && phpFormDataSessao && !phpFormDataSessao.comunicado_id) { // Erro de criação
                    comunicadoData.titulo_comunicado = phpFormDataSessao.titulo_comunicado || '';
                    comunicadoData.conteudo_comunicado = phpFormDataSessao.conteudo_comunicado || '';
                    comunicadoData.data_publicacao_str = phpFormDataSessao.data_publicacao || '';
                    comunicadoData.data_expiracao_str = phpFormDataSessao.data_expiracao || '';
                    comunicadoData.ativo = phpFormDataSessao.ativo_comunicado !== undefined ? phpFormDataSessao.ativo_comunicado == '1' : true;
                    comunicadoData.segmento_planos_ids = Array.isArray(phpFormDataSessao.segmento_planos_ids) ? phpFormDataSessao.segmento_planos_ids.map(id => parseInt(id)) : [];
                    if(errorPlaceholderModalEl) { errorPlaceholderModalEl.innerHTML = phpErroForm; errorPlaceholderModalEl.style.display = 'block';}
                } else {
                    // Não é repopulação de erro, então reseta para um form limpo de criação
                    formModal.reset(); // Reseta os valores nativos dos inputs
                    // Defaults para criação
                    comunicadoData.ativo = true;
                    comunicadoData.segmento_planos_ids = [];
                }
            }

            // Preencher o modal
            idInputEl.value = comunicadoData.id;
            tituloInputEl.value = comunicadoData.titulo_comunicado;
            conteudoTextareaEl.value = comunicadoData.conteudo_comunicado;
            publicacaoInputEl.value = comunicadoData.data_publicacao_str;
            expiracaoInputEl.value = comunicadoData.data_expiracao_str;
            ativoCheckboxEl.checked = comunicadoData.ativo;
            planosCheckboxesModalEl.forEach(cb => {
                cb.checked = comunicadoData.segmento_planos_ids.includes(parseInt(cb.value));
            });
        });

        // Forçar abertura do modal se a página carregou devido a um erro de validação NO POST de edição
        // E o ID de edição estava na URL
        if (phpErroForm && phpEditIdGet && phpComunicadoEditarObj && (phpFormDataSessao.comunicado_id_com_erro == phpEditIdGet) ) {
            const modalToOpenWithEditError = new bootstrap.Modal(modalComunicadoEl);
            // Os valores do form já foram setados via $form_data_comunicado no PHP e serão pegos pelo JS acima
            // apenas garantimos que o erro é exibido
             if(errorPlaceholderModalEl) {
                 errorPlaceholderModalEl.innerHTML = phpErroForm;
                 errorPlaceholderModalEl.style.display = 'block';
             }
            modalToOpenWithEditError.show();
            // Limpar o ID de erro da sessão para não reabrir indefinidamente
            <?php unset($_SESSION['form_data_comunicado']['comunicado_id_com_erro']); ?>
        }
    }

    const formsNeedingValidation = document.querySelectorAll('.needs-validation');
    Array.from(formsNeedingValidation).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

<?php
echo getFooterAdmin();
?>