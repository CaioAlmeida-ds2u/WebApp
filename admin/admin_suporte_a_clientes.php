<?php
// admin/admin_suporte_a_clientes.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Funções CRUD para tickets e busca de dados

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Admin da Acoditools
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// --- Buscar Dados para Filtros ---
// $lista_empresas_clientes = getEmpresasClientesParaFiltro($conexao); // Função simples para buscar IDs e Nomes de empresas
// $lista_admins_plataforma = getAdminsPlataformaParaFiltro($conexao); // Função para buscar IDs e Nomes de admins Acoditools
// Simulação por enquanto
$lista_empresas_clientes = dbGetEmpresasSolic($conexao); // Reutilizando uma existente, mas idealmente seria uma função específica
$lista_admins_plataforma = getTodosUsuariosPorPerfil($conexao, 'admin'); // Função nova, ou adaptar getUsuarios

$status_ticket_possiveis = ['Aberto', 'Em Andamento', 'Aguardando Cliente', 'Resolvido', 'Fechado'];
$prioridades_ticket_possiveis = ['Baixa', 'Normal', 'Alta', 'Urgente'];

// --- Processamento de Filtros GET para a Listagem ---
$filtros_tickets = [
    'pagina' => filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]),
    'busca_assunto' => trim(filter_input(INPUT_GET, 'busca_assunto', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''),
    'empresa_id_filtro' => filter_input(INPUT_GET, 'empresa_id_filtro', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
    'status_ticket_filtro' => trim(filter_input(INPUT_GET, 'status_ticket_filtro', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'todos'),
    'prioridade_filtro' => trim(filter_input(INPUT_GET, 'prioridade_filtro', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'todos'),
    'admin_resp_filtro' => filter_input(INPUT_GET, 'admin_resp_filtro', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
];
$itens_por_pagina_tickets = 15;


// --- Processamento de Ações POST (Ex: Mudar Status, Atribuir Admin) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $_SESSION['csrf_token'] = gerar_csrf_token(); // Regenera
        $action_ticket = $_POST['action'] ?? '';
        $ticket_id_acao = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);

        switch ($action_ticket) {
            case 'atualizar_ticket_admin': // Ação chamada pelo modal de detalhes/edição
                if ($ticket_id_acao) {
                    $novo_status_ticket_post = trim($_POST['novo_status_ticket_modal'] ?? '');
                    $novo_admin_resp_id_post = filter_input(INPUT_POST, 'novo_admin_resp_id_modal', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                    $nova_prioridade_post = trim($_POST['nova_prioridade_ticket_modal'] ?? '');
                    $comentario_admin_post = trim($_POST['comentario_admin_ticket_modal'] ?? '');

                    // Validar $novo_status_ticket_post e $nova_prioridade_post contra listas válidas
                    if (!in_array($novo_status_ticket_post, $status_ticket_possiveis) && !empty($novo_status_ticket_post)) {
                         definir_flash_message('erro', "Status inválido para o ticket ID $ticket_id_acao.");
                         break; // Sai do switch
                    }
                     if (!in_array($nova_prioridade_post, $prioridades_ticket_possiveis) && !empty($nova_prioridade_post)) {
                         definir_flash_message('erro', "Prioridade inválida para o ticket ID $ticket_id_acao.");
                         break;
                    }


                    // *** Chamar função: atualizarTicketPeloAdmin($conexao, $ticket_id_acao, $novo_status_ticket_post, $novo_admin_resp_id_post, $nova_prioridade_post, $comentario_admin_post, $_SESSION['usuario_id']) ***
                    $res_upd_ticket = atualizarTicketPeloAdmin($conexao, $ticket_id_acao, $novo_status_ticket_post, $novo_admin_resp_id_post, $nova_prioridade_post, $comentario_admin_post, $_SESSION['usuario_id']);
                    if ($res_upd_ticket === true) {
                        definir_flash_message('sucesso', "Ticket ID $ticket_id_acao atualizado.");
                    } else {
                        definir_flash_message('erro', is_string($res_upd_ticket) ? $res_upd_ticket : "Erro ao atualizar ticket ID $ticket_id_acao.");
                    }
                }
                break;
            // Outras ações como 'fechar_ticket_massa', etc.
        }
    }
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?' . http_build_query($filtros_tickets)); // Mantém filtros na URL
    exit;
}


// --- Busca de Dados para Listagem ---
// *** Chamar função: listarTicketsSuportePaginado($conexao, $filtros_tickets['pagina'], $itens_por_pagina_tickets, $filtros_tickets) ***
$tickets_data = listarTicketsSuportePaginado($conexao, $filtros_tickets['pagina'], $itens_por_pagina_tickets, $filtros_tickets);
$lista_tickets = $tickets_data['tickets'] ?? [];
$paginacao_tickets = $tickets_data['paginacao'] ?? ['total_paginas' => 0, 'pagina_atual' => 1, 'total_itens' => 0];

// Para o modal de edição, se um ID for passado via GET (para abrir o modal direto)
$ticket_para_editar_modal = null;
$edit_ticket_id_get = filter_input(INPUT_GET, 'ver_ticket_id', FILTER_VALIDATE_INT);
if ($edit_ticket_id_get) {
    // *** Chamar função: getTicketSuporteDetalhes($conexao, $edit_ticket_id_get) ***
    // Esta função buscaria o ticket e também os comentários/histórico dele
    $ticket_para_editar_modal = getTicketSuporteDetalhes($conexao, $edit_ticket_id_get);
    if (!$ticket_para_editar_modal) definir_flash_message('erro', "Ticket ID $edit_ticket_id_get não encontrado para visualização.");
}


$csrf_token_page = $_SESSION['csrf_token'];

$title = "ACodITools - Suporte a Clientes";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-headset me-2"></i>Gerenciamento de Suporte a Clientes</h1>
        <?php /* Botão de "Novo Ticket" (Admin pode abrir em nome de um cliente?) ou link para base de conhecimento */ ?>
    </div>

    <?php if ($sucesso_msg): /* ... Mensagens ... */ endif; ?>
    <?php if ($erro_msg): /* ... Mensagens ... */ endif; ?>

    <!-- Card de Filtros -->
    <div class="card shadow-sm mb-4 rounded-3 border-0">
        <div class="card-header bg-light"><h6 class="mb-0 fw-bold"><i class="fas fa-filter me-1"></i> Filtrar Tickets de Suporte</h6></div>
        <div class="card-body p-3">
            <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" id="filtroTicketsForm">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="busca_assunto_filtro" class="form-label form-label-sm">Assunto/ID:</label>
                        <input type="search" name="busca_assunto" id="busca_assunto_filtro" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros_tickets['busca_assunto']) ?>" placeholder="Buscar por título ou ID...">
                    </div>
                    <div class="col-md-3">
                        <label for="empresa_id_filtro" class="form-label form-label-sm">Empresa Cliente:</label>
                        <select name="empresa_id_filtro" id="empresa_id_filtro" class="form-select form-select-sm">
                            <option value="">Todas as Empresas</option>
                            <?php foreach ($lista_empresas_clientes as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= ($filtros_tickets['empresa_id_filtro'] == $emp['id']) ? 'selected' : '' ?>><?= htmlspecialchars($emp['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status_ticket_filtro" class="form-label form-label-sm">Status:</label>
                        <select name="status_ticket_filtro" id="status_ticket_filtro" class="form-select form-select-sm">
                            <option value="todos" <?= ($filtros_tickets['status_ticket_filtro'] === 'todos') ? 'selected' : '' ?>>Todos Status</option>
                            <?php foreach($status_ticket_possiveis as $stat): ?>
                            <option value="<?= $stat ?>" <?= ($filtros_tickets['status_ticket_filtro'] === $stat) ? 'selected' : '' ?>><?= htmlspecialchars($stat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="col-md-2">
                        <label for="prioridade_filtro" class="form-label form-label-sm">Prioridade:</label>
                        <select name="prioridade_filtro" id="prioridade_filtro" class="form-select form-select-sm">
                            <option value="todos" <?= ($filtros_tickets['prioridade_filtro'] === 'todos') ? 'selected' : '' ?>>Todas</option>
                            <?php foreach($prioridades_ticket_possiveis as $prio): ?>
                            <option value="<?= $prio ?>" <?= ($filtros_tickets['prioridade_filtro'] === $prio) ? 'selected' : '' ?>><?= htmlspecialchars($prio) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="col-md-2">
                        <label for="admin_resp_filtro" class="form-label form-label-sm">Admin Resp.:</label>
                        <select name="admin_resp_filtro" id="admin_resp_filtro" class="form-select form-select-sm">
                            <option value="">Qualquer Admin</option>
                            <option value="nao_atribuido" <?= ($filtros_tickets['admin_resp_filtro'] === 'nao_atribuido') ? 'selected' : '' ?>>Não Atribuído</option>
                            <?php foreach ($lista_admins_plataforma as $adm): ?>
                            <option value="<?= $adm['id'] ?>" <?= ($filtros_tickets['admin_resp_filtro'] == $adm['id']) ? 'selected' : '' ?>><?= htmlspecialchars($adm['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0 d-flex">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1 me-1"><i class="fas fa-filter me-1"></i>Filtrar</button>
                        <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="btn btn-sm btn-outline-secondary flex-grow-1" title="Limpar Filtros"><i class="fas fa-times"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Card Lista de Tickets -->
    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-header bg-light"><h6 class="mb-0 fw-bold"><i class="fas fa-list-alt me-2 text-primary opacity-75"></i>Tickets de Suporte (<?= $paginacao_tickets['total_itens'] ?? 0 ?>)</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th>ID</th>
                            <th>Assunto</th>
                            <th>Cliente</th>
                            <th>Solicitante</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Admin Resp.</th>
                            <th>Aberto em</th>
                            <th>Últ. Atu.</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_tickets)): ?>
                            <tr><td colspan="10" class="text-center text-muted p-4">Nenhum ticket encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_tickets as $ticket):
                                // Lógica para badge de status e prioridade
                                $status_badge_class = 'bg-secondary';
                                switch ($ticket['status_ticket']) {
                                    case 'Aberto': $status_badge_class = 'bg-danger'; break;
                                    case 'Em Andamento': $status_badge_class = 'bg-info text-dark'; break;
                                    case 'Aguardando Cliente': $status_badge_class = 'bg-warning text-dark'; break;
                                    case 'Resolvido': case 'Fechado': $status_badge_class = 'bg-success'; break;
                                }
                                $prio_badge_class = 'bg-light text-dark border';
                                switch ($ticket['prioridade_ticket']) {
                                    case 'Baixa': $prio_badge_class = 'bg-success-subtle text-success-emphasis'; break;
                                    case 'Normal': $prio_badge_class = 'bg-primary-subtle text-primary-emphasis'; break;
                                    case 'Alta': $prio_badge_class = 'bg-warning-subtle text-warning-emphasis'; break;
                                    case 'Urgente': $prio_badge_class = 'bg-danger-subtle text-danger-emphasis'; break;
                                }
                            ?>
                            <tr>
                                <td class="fw-bold">#<?= htmlspecialchars($ticket['id']) ?></td>
                                <td title="<?= htmlspecialchars($ticket['assunto_ticket']) ?>">
                                    <a href="#" class="text-decoration-none btn-view-ticket" data-bs-toggle="modal" data-bs-target="#modalVerDetalhesTicket" data-ticket-id="<?= $ticket['id'] ?>">
                                        <?= htmlspecialchars(mb_strimwidth($ticket['assunto_ticket'], 0, 40, "...")) ?>
                                    </a>
                                </td>
                                <td class="small text-muted" title="<?= htmlspecialchars($ticket['nome_empresa_cliente']) ?>"><?= htmlspecialchars(mb_strimwidth($ticket['nome_empresa_cliente'], 0, 25, "...")) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($ticket['nome_usuario_solicitante']) ?></td>
                                <td><span class="badge <?= $status_badge_class ?>"><?= htmlspecialchars($ticket['status_ticket']) ?></span></td>
                                <td><span class="badge <?= $prio_badge_class ?>"><?= htmlspecialchars($ticket['prioridade_ticket']) ?></span></td>
                                <td class="small text-muted"><?= htmlspecialchars($ticket['nome_admin_responsavel'] ?? 'N/A') ?></td>
                                <td class="small text-muted text-nowrap" title="<?= formatarDataCompleta($ticket['data_abertura']) ?>"><?= formatarDataRelativa($ticket['data_abertura']) ?></td>
                                <td class="small text-muted text-nowrap" title="<?= formatarDataCompleta($ticket['data_ultima_atualizacao']) ?>"><?= formatarDataRelativa($ticket['data_ultima_atualizacao']) ?></td>
                                <td class="text-center action-buttons-table">
                                     <button type="button" class="btn btn-sm btn-outline-primary action-btn btn-view-ticket"
                                            data-bs-toggle="modal" data-bs-target="#modalVerDetalhesTicket"
                                            data-ticket-id="<?= $ticket['id'] ?>"
                                            title="Ver Detalhes e Responder ao Ticket">
                                        <i class="fas fa-eye fa-fw"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (isset($paginacao_tickets) && $paginacao_tickets['total_paginas'] > 1): ?>
        <div class="card-footer bg-light py-2">
            <nav aria-label="Paginação de Tickets">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php
                        $params_pag_ticket = $filtros_tickets; unset($params_pag_ticket['pagina']);
                        $link_pag_tkt = "?" . http_build_query($params_pag_ticket) . "&pagina=";
                    ?>
                    <?php if ($paginacao_tickets['pagina_atual'] > 1): ?><li class="page-item"><a class="page-link" href="<?= $link_pag_tkt . ($paginacao_tickets['pagina_atual'] - 1) ?>">«</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">«</span></li><?php endif; ?>
                    <?php for ($i = 1; $i <= $paginacao_tickets['total_paginas']; $i++): /* Simples por enquanto */ ?>
                        <li class="page-item <?= ($i == $paginacao_tickets['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="<?= $link_pag_tkt . $i ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($paginacao_tickets['pagina_atual'] < $paginacao_tickets['total_paginas']): ?><li class="page-item"><a class="page-link" href="<?= $link_pag_tkt . ($paginacao_tickets['pagina_atual'] + 1) ?>">»</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">»</span></li><?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Ver Detalhes e Atualizar Ticket -->
<div class="modal fade" id="modalVerDetalhesTicket" tabindex="-1" aria-labelledby="modalTicketLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= strtok($_SERVER["REQUEST_URI"], '?') . '?' . http_build_query($filtros_tickets) ?>" id="formModalTicketAdmin">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                <input type="hidden" name="action" value="atualizar_ticket_admin">
                <input type="hidden" name="ticket_id" id="modal_ticket_id_admin" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTicketLabelAdmin">Detalhes do Ticket de Suporte #<span id="modal_ticket_id_display"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="modal_ticket_loading_admin" class="text-center py-5" style="display:none;"><div class="spinner-border text-primary"></div> <p class="mt-2">Carregando dados do ticket...</p></div>
                    <div id="modal_ticket_error_admin" class="alert alert-danger" style="display:none;"></div>
                    <div id="modal_ticket_content_admin" style="display:none;">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <strong class="small text-muted">Assunto:</strong>
                                <p id="modal_ticket_assunto_admin" class="fw-semibold"></p>
                            </div>
                            <div class="col-md-4">
                                <strong class="small text-muted">Empresa Cliente:</strong>
                                <p id="modal_ticket_empresa_admin"></p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <strong class="small text-muted">Descrição do Problema (Cliente):</strong>
                            <div id="modal_ticket_descricao_admin" class="p-2 border rounded bg-light-subtle" style="white-space: pre-wrap; max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <div class="mb-3">
                            <strong class="small text-muted">Histórico de Comentários:</strong>
                            <div id="modal_ticket_comentarios_lista_admin" class="p-2 border rounded" style="max-height: 250px; overflow-y: auto; font-size: 0.9em;">
                                <!-- Comentários carregados via JS -->
                            </div>
                        </div>
                        <hr>
                        <h6 class="mb-3">Ações do Administrador:</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="novo_status_ticket_modal" class="form-label form-label-sm">Novo Status:</label>
                                <select class="form-select form-select-sm" id="novo_status_ticket_modal" name="novo_status_ticket_modal">
                                    <?php foreach($status_ticket_possiveis as $s_opt): ?>
                                    <option value="<?= $s_opt ?>"><?= htmlspecialchars($s_opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                             <div class="col-md-4">
                                <label for="nova_prioridade_ticket_modal" class="form-label form-label-sm">Prioridade:</label>
                                <select class="form-select form-select-sm" id="nova_prioridade_ticket_modal" name="nova_prioridade_ticket_modal">
                                     <?php foreach($prioridades_ticket_possiveis as $p_opt): ?>
                                    <option value="<?= $p_opt ?>"><?= htmlspecialchars($p_opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="novo_admin_resp_id_modal" class="form-label form-label-sm">Atribuir a Admin:</label>
                                <select class="form-select form-select-sm" id="novo_admin_resp_id_modal" name="novo_admin_resp_id_modal">
                                    <option value="">-- Não Atribuído --</option>
                                     <?php foreach($lista_admins_plataforma as $admin_opt): ?>
                                    <option value="<?= $admin_opt['id'] ?>"><?= htmlspecialchars($admin_opt['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label for="comentario_admin_ticket_modal" class="form-label form-label-sm">Adicionar Comentário / Resposta:</label>
                            <textarea class="form-control form-control-sm" id="comentario_admin_ticket_modal" name="comentario_admin_ticket_modal" rows="4" placeholder="Seu comentário ou solução para o cliente..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnSalvarModalTicketAdmin"><i class="fas fa-save me-1"></i> Salvar Alterações e Responder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalTicketAdmin = document.getElementById('modalVerDetalhesTicket');
    if (modalTicketAdmin) {
        const modalForm = modalTicketAdmin.querySelector('#formModalTicketAdmin');
        const ticketIdDisplay = modalTicketAdmin.querySelector('#modal_ticket_id_display');
        const ticketIdInput = modalTicketAdmin.querySelector('#modal_ticket_id_admin');
        const assuntoP = modalTicketAdmin.querySelector('#modal_ticket_assunto_admin');
        const empresaP = modalTicketAdmin.querySelector('#modal_ticket_empresa_admin');
        const descricaoDiv = modalTicketAdmin.querySelector('#modal_ticket_descricao_admin');
        const comentariosDiv = modalTicketAdmin.querySelector('#modal_ticket_comentarios_lista_admin');
        const statusSelect = modalTicketAdmin.querySelector('#novo_status_ticket_modal');
        const prioridadeSelect = modalTicketAdmin.querySelector('#nova_prioridade_ticket_modal');
        const adminRespSelect = modalTicketAdmin.querySelector('#novo_admin_resp_id_modal');
        const comentarioTextarea = modalTicketAdmin.querySelector('#comentario_admin_ticket_modal');
        const loadingDiv = modalTicketAdmin.querySelector('#modal_ticket_loading_admin');
        const errorDiv = modalTicketAdmin.querySelector('#modal_ticket_error_admin');
        const contentDiv = modalTicketAdmin.querySelector('#modal_ticket_content_admin');

        modalTicketAdmin.addEventListener('show.bs.modal', async function (event) {
            const button = event.relatedTarget;
            const ticketId = button.dataset.ticketId;

            // Reset e Loading
            ticketIdInput.value = ticketId;
            ticketIdDisplay.textContent = ticketId;
            assuntoP.textContent = 'Carregando...';
            empresaP.textContent = 'Carregando...';
            descricaoDiv.innerHTML = '<span class="text-muted">Carregando...</span>';
            comentariosDiv.innerHTML = '<span class="text-muted">Carregando...</span>';
            modalForm.reset(); // Reseta campos do form
            statusSelect.value = ''; prioridadeSelect.value = ''; adminRespSelect.value = ''; // Reset selects

            loadingDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            contentDiv.style.display = 'none';

            try {
                // *** Chamar função AJAX: getTicketSuporteDetalhesAJAX($conexao, $ticketId) ***
                // Esta função buscaria no backend os detalhes, incluindo os comentários
                // A função PHP `getTicketSuporteDetalhes` já pode servir, mas precisa de um AJAX handler.
                // Simulação da chamada:
                // const response = await fetch(`ajax_get_ticket_details.php?id=${ticketId}&csrf_token=<?= $csrf_token_page ?>`);
                // const data = await response.json();
                // if (!data.success) throw new Error(data.message || 'Erro ao buscar ticket.');

                // Placeholder - substitua pela chamada AJAX e processamento real
                // const data = await getTicketSuporteDetalhesSimulado(ticketId);
                 const data = await buscarDetalhesTicket(ticketId);


                assuntoP.textContent = data.ticket.assunto_ticket;
                empresaP.textContent = data.ticket.nome_empresa_cliente + (data.ticket.empresa_id_cliente ? ` (ID: ${data.ticket.empresa_id_cliente})` : '');
                descricaoDiv.textContent = data.ticket.descricao_ticket;
                statusSelect.value = data.ticket.status_ticket;
                prioridadeSelect.value = data.ticket.prioridade_ticket;
                adminRespSelect.value = data.ticket.admin_acoditools_responsavel_id || '';

                let comentariosHtml = '<small class="text-muted">Nenhum comentário ainda.</small>';
                if (data.comentarios && data.comentarios.length > 0) {
                    comentariosHtml = data.comentarios.map(com =>
                       `<div class="mb-2 p-2 border-bottom ${com.origem === 'admin' ? 'bg-light' : 'bg-white'}">
                           <p class="mb-0">${com.texto.replace(/\n/g, '<br>')}</p>
                           <small class="text-muted">Por: ${com.nome_autor} (${com.perfil_autor}) em ${new Date(com.data_comentario).toLocaleString('pt-BR')}</small>
                        </div>`
                    ).join('');
                }
                comentariosDiv.innerHTML = comentariosHtml;

                loadingDiv.style.display = 'none';
                contentDiv.style.display = 'block';

            } catch (err) {
                loadingDiv.style.display = 'none';
                errorDiv.textContent = `Erro ao carregar ticket: ${err.message}`;
                errorDiv.style.display = 'block';
            }
        });
    }

    // Função simula chamada AJAX
    async function buscarDetalhesTicket(ticketId) {
        // Simulação de delay da rede
        await new Promise(resolve => setTimeout(resolve, 500));

        // ** No seu backend, esta função buscaria do DB **
        // Exemplo de retorno simulado
        if (ticketId == "<?= $lista_tickets[0]['id'] ?? '' ?>") { // Pega o ID do primeiro ticket da lista para simular
             return {
                 success: true,
                 ticket: {
                     id: ticketId,
                     assunto_ticket: "<?= htmlspecialchars($lista_tickets[0]['assunto_ticket'] ?? 'Assunto Teste Ticket X') ?>",
                     nome_empresa_cliente: "<?= htmlspecialchars($lista_tickets[0]['nome_empresa_cliente'] ?? 'Empresa Exemplo') ?>",
                     empresa_id_cliente: "<?= $lista_tickets[0]['empresa_id_cliente'] ?? 1 ?>",
                     descricao_ticket: "Descrição detalhada do problema do cliente aqui... \nCom várias linhas para teste.",
                     status_ticket: "<?= $lista_tickets[0]['status_ticket'] ?? 'Aberto' ?>",
                     prioridade_ticket: "<?= $lista_tickets[0]['prioridade_ticket'] ?? 'Normal' ?>",
                     admin_acoditools_responsavel_id: "<?= $lista_tickets[0]['admin_acoditools_responsavel_id'] ?? '' ?>"
                 },
                 comentarios: [
                     { texto: "Cliente: Tentei fazer X e deu erro Y.", nome_autor: "<?= htmlspecialchars($lista_tickets[0]['nome_usuario_solicitante'] ?? 'Cliente') ?>", perfil_autor: "Cliente", data_comentario: "<?= date('Y-m-d H:i:s', strtotime('-2 hours')) ?>" },
                     { texto: "Admin: Recebemos sua solicitação, estamos analisando.", nome_autor: "Admin Suporte", perfil_autor: "Admin Plataforma", data_comentario: "<?= date('Y-m-d H:i:s', strtotime('-1 hour')) ?>" }
                 ]
             };
        } else {
            return { success: false, message: "Ticket não encontrado (simulado)." };
        }
    }
});
</script>

<?php
echo getFooterAdmin();
?>