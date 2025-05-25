<?php
// admin/logs_aplicacao_detalhados.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// Lógica de Mensagens Flash
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// Processamento de Filtros GET
$pagina_atual_log_app = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina_log_app = 25; // Pode ajustar

$filtros_log_app = [
    'data_inicio' => filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
    'data_fim' => filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
    'tipo_erro' => filter_input(INPUT_GET, 'tipo_erro', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
    'busca_livre' => trim(filter_input(INPUT_GET, 'busca_livre', FILTER_SANITIZE_SPECIAL_CHARS) ?? '')
    // Adicionar 'gravidade' se o campo for implementado no DB
];
// Remover filtros vazios para não poluir a URL na paginação
$filtros_ativos_log_app_pag = array_filter($filtros_log_app, function($value) { return $value !== null && $value !== ''; });


$logs_data = listarLogsAplicacaoPaginado($conexao, $pagina_atual_log_app, $itens_por_pagina_log_app, $filtros_log_app);
$lista_logs_erros = $logs_data['logs_erros'];
$paginacao_log_app = $logs_data['paginacao'];

$tipos_erro_distintos = getTiposDeErroDistintos($conexao);
// Se tiver gravidade: $gravidades_distintas = ['ERROR', 'CRITICAL', ...];

$title = "ACodITools - Logs Detalhados da Aplicação";
$csrf_token_page = $_SESSION['csrf_token']; // Usado se houver ações POST (ex: marcar como visto)
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-file-medical-alt me-2"></i>Logs Detalhados da Aplicação</h1>
        <a href="<?= BASE_URL ?>admin/plataforma_monitoramento_e_saude.php#logs-erros-tab" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar para Monitoramento
        </a>
    </div>

    <?php if ($sucesso_msg): /* ... HTML mensagem sucesso ... */ endif; ?>
    <?php if ($erro_msg): /* ... HTML mensagem erro ... */ endif; ?>

    <div class="card shadow-sm mb-4 rounded-3 border-0">
        <div class="card-header bg-light border-bottom pt-3 pb-2">
            <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" id="filtroLogsAppForm">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label for="data_inicio_log_app" class="form-label form-label-sm">Data Início:</label>
                        <input type="date" name="data_inicio" id="data_inicio_log_app" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros_log_app['data_inicio'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="data_fim_log_app" class="form-label form-label-sm">Data Fim:</label>
                        <input type="date" name="data_fim" id="data_fim_log_app" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros_log_app['data_fim'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="tipo_erro_log_app" class="form-label form-label-sm">Tipo de Erro:</label>
                        <select name="tipo_erro" id="tipo_erro_log_app" class="form-select form-select-sm">
                            <option value="">Todos os Tipos</option>
                            <?php foreach ($tipos_erro_distintos as $tipo_e): ?>
                                <option value="<?= htmlspecialchars($tipo_e) ?>" <?= ($filtros_log_app['tipo_erro'] === $tipo_e) ? 'selected' : '' ?>><?= htmlspecialchars($tipo_e) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="busca_livre_log_app" class="form-label form-label-sm">Busca Livre:</label>
                        <input type="search" name="busca_livre" id="busca_livre_log_app" class="form-control form-control-sm" placeholder="Mensagem, arquivo..." value="<?= htmlspecialchars($filtros_log_app['busca_livre']) ?>">
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0 d-flex">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1 me-1"><i class="fas fa-filter me-1"></i>Filtrar Logs</button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-sm btn-outline-secondary flex-grow-1" title="Limpar Filtros"><i class="fas fa-times"></i></a>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 15%;">Data/Hora</th>
                            <th style="width: 15%;">Tipo</th>
                            <th style="width: 20%;">Arquivo Origem</th>
                            <th>Mensagem do Erro</th>
                            <th class="text-center" style="width: 10%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_logs_erros)): ?>
                            <tr><td colspan="6" class="text-center text-muted p-4">Nenhum log de erro encontrado<?= !empty($filtros_ativos_log_app_pag) ? ' com os filtros aplicados.' : '.' ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_logs_erros as $log_e): ?>
                                <tr>
                                    <td class="fw-bold">#<?= htmlspecialchars($log_e['id']) ?></td>
                                    <td class="text-nowrap small text-muted"><?= htmlspecialchars(formatarDataCompleta($log_e['data_erro'])) ?></td>
                                    <td><span class="badge bg-danger-subtle text-danger-emphasis"><?= htmlspecialchars($log_e['tipo_erro'] ?: 'Geral') ?></span></td>
                                    <td class="small text-break" title="<?= htmlspecialchars($log_e['arquivo_origem'] ?? 'N/A') ?>"><?= htmlspecialchars(mb_strimwidth($log_e['arquivo_origem'] ?? 'N/A', 0, 40, "...")) ?></td>
                                    <td class="small text-break" title="<?= htmlspecialchars($log_e['mensagem_erro']) ?>">
                                        <?= nl2br(htmlspecialchars(mb_strimwidth($log_e['mensagem_erro'], 0, 120, "..."))) ?>
                                    </td>
                                    <td class="text-center action-buttons-table">
                                        <button type="button" class="btn btn-sm btn-outline-info action-btn btn-view-log-details"
                                                data-bs-toggle="modal" data-bs-target="#modalLogDetalhes"
                                                data-log-id="<?= $log_e['id'] ?>"
                                                title="Ver Detalhes do Log">
                                            <i class="fas fa-eye fa-fw"></i>
                                        </button>
                                        <?php /* Adicionar Ações como Marcar Visto, se implementado */ ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($paginacao_log_app['total_paginas'] > 1): ?>
        <div class="card-footer bg-light py-2">
            <nav aria-label="Paginação de Logs da Aplicação">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php
                    $link_pag_log_app = "?" . http_build_query($filtros_ativos_log_app_pag) . "&pagina=";
                    ?>
                    <?php if ($paginacao_log_app['pagina_atual'] > 1): ?><li class="page-item"><a class="page-link" href="<?= $link_pag_log_app . ($paginacao_log_app['pagina_atual'] - 1) ?>">«</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">«</span></li><?php endif; ?>
                    <?php $inicio_pla = max(1, $paginacao_log_app['pagina_atual'] - 2); $fim_pla = min($paginacao_log_app['total_paginas'], $paginacao_log_app['pagina_atual'] + 2); if($inicio_pla > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; for($i = $inicio_pla; $i <= $fim_pla; $i++):?><li class="page-item <?= ($i == $paginacao_log_app['pagina_atual'])?'active':'' ?>"><a class="page-link" href="<?= $link_pag_log_app . $i ?>"><?= $i ?></a></li><?php endfor; if($fim_pla < $paginacao_log_app['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                    <?php if ($paginacao_log_app['pagina_atual'] < $paginacao_log_app['total_paginas']): ?><li class="page-item"><a class="page-link" href="<?= $link_pag_log_app . ($paginacao_log_app['pagina_atual'] + 1) ?>">»</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">»</span></li><?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Detalhes do Log -->
<div class="modal fade" id="modalLogDetalhes" tabindex="-1" aria-labelledby="modalLogDetalhesLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLogDetalhesLabel">Detalhes do Log de Erro #<span id="modal_log_id_display"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="modal_log_loading_content" class="text-center py-5" style="display:none;"><div class="spinner-border text-primary"></div> <p class="mt-2">Carregando detalhes...</p></div>
                <div id="modal_log_error_content" class="alert alert-danger" style="display:none;"></div>
                <div id="modal_log_data_content" style="display:none;">
                    <dl class="row">
                        <dt class="col-sm-3">ID do Log:</dt><dd class="col-sm-9" id="detail_log_id"></dd>
                        <dt class="col-sm-3">Data/Hora:</dt><dd class="col-sm-9" id="detail_log_data"></dd>
                        <dt class="col-sm-3">Tipo de Erro:</dt><dd class="col-sm-9" id="detail_log_tipo"></dd>
                        <dt class="col-sm-3">Arquivo Origem:</dt><dd class="col-sm-9"><code id="detail_log_arquivo"></code></dd>
                    </dl>
                    <h6>Mensagem Completa:</h6>
                    <pre class="bg-light p-3 rounded border small" id="detail_log_mensagem" style="white-space: pre-wrap; word-wrap: break-word; max-height: 300px; overflow-y: auto;"></pre>

                    <?php /* Se você adicionar stack_trace e contexto_adicional à tabela logs_erros:
                    <h6>Stack Trace:</h6>
                    <pre class="bg-dark text-white p-3 rounded small" id="detail_log_stacktrace" style="white-space: pre-wrap; word-wrap: break-word; max-height: 200px; overflow-y: auto;"></pre>
                    <h6>Contexto Adicional:</h6>
                    <pre class="bg-light p-2 rounded border x-small" id="detail_log_contexto" style="max-height: 150px; overflow-y: auto;"></pre>
                    */ ?>
                </div>
            </div>
            <div class="modal-footer">
                <?php /* Adicionar botões de ação para o log, se houver */ ?>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>


<?php echo getFooterAdmin(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ... (validação Bootstrap padrão para filtros, se necessário)

    const modalLogDetalhes = document.getElementById('modalLogDetalhes');
    if (modalLogDetalhes) {
        const modalTitleId = modalLogDetalhes.querySelector('#modal_log_id_display');
        const loadingDivModal = modalLogDetalhes.querySelector('#modal_log_loading_content');
        const errorDivModal = modalLogDetalhes.querySelector('#modal_log_error_content');
        const dataDivModal = modalLogDetalhes.querySelector('#modal_log_data_content');

        modalLogDetalhes.addEventListener('show.bs.modal', async function (event) {
            const button = event.relatedTarget;
            const logId = button.dataset.logId;

            // Reset e Loading
            modalTitleId.textContent = logId;
            loadingDivModal.style.display = 'block';
            errorDivModal.style.display = 'none'; errorDivModal.textContent = '';
            dataDivModal.style.display = 'none';
            // Limpar campos de detalhes
            ['detail_log_id', 'detail_log_data', 'detail_log_tipo', 'detail_log_arquivo', 'detail_log_mensagem'/*, 'detail_log_stacktrace', 'detail_log_contexto'*/].forEach(id => {
                const el = document.getElementById(id);
                if(el) el.textContent = '';
            });

            try {
                // *** AQUI SERIA UMA CHAMADA AJAX PARA O BACKEND BUSCAR OS DETALHES DO LOG ***
                // Ex: const response = await fetch(`ajax_get_log_details.php?id=${logId}&csrf=<?= $csrf_token_page ?>`);
                // const data = await response.json();
                // if (!data.success) throw new Error(data.message || 'Erro ao buscar detalhes do log.');

                // SIMULAÇÃO da chamada AJAX:
                const data = await buscarDetalhesLogSimulado(logId); // Substituir pela chamada real

                document.getElementById('detail_log_id').textContent = data.log.id;
                document.getElementById('detail_log_data').textContent = formatarDataHoraUserFriendly(data.log.data_erro);
                document.getElementById('detail_log_tipo').textContent = data.log.tipo_erro || 'Geral';
                document.getElementById('detail_log_arquivo').textContent = data.log.arquivo_origem || 'N/A';
                document.getElementById('detail_log_mensagem').innerHTML = nl2br(htmlspecialchars(data.log.mensagem_erro)); // Usar innerHTML para nl2br
                // Se tiver stack trace e contexto:
                // document.getElementById('detail_log_stacktrace').textContent = data.log.stack_trace || 'Nenhum stack trace disponível.';
                // document.getElementById('detail_log_contexto').textContent = data.log.contexto_adicional ? JSON.stringify(data.log.contexto_adicional, null, 2) : 'Nenhum contexto adicional.';


                loadingDivModal.style.display = 'none';
                dataDivModal.style.display = 'block';

            } catch (err) {
                loadingDivModal.style.display = 'none';
                errorDivModal.textContent = `Erro ao carregar detalhes do log: ${err.message}`;
                errorDivModal.style.display = 'block';
            }
        });
    }

    // Função auxiliar de simulação (REMOVER EM PRODUÇÃO E USAR AJAX REAL)
    async function buscarDetalhesLogSimulado(logId) {
        await new Promise(resolve => setTimeout(resolve, 300)); // Simula delay
        // No PHP, você faria: $log = getLogAplicacaoDetalhes($conexao, $logId);
        // E retornaria json_encode(['success' => true, 'log' => $log]);
        <?php
        // Simular a busca do primeiro log da lista para popular o modal como exemplo
        $primeiro_log_simulado = !empty($lista_logs_erros) ? $lista_logs_erros[0] : null;
        if ($primeiro_log_simulado) {
            // Adicionar campos faltantes com valores dummy se você adicionou à tabela
            // $primeiro_log_simulado['stack_trace'] = $primeiro_log_simulado['stack_trace'] ?? "Exemplo de Stack Trace:\n#0 /var/www/html/index.php(10): A->b()\n#1 {main}";
            // $primeiro_log_simulado['contexto_adicional'] = $primeiro_log_simulado['contexto_adicional'] ?? json_encode(['GET' => ['param' => 'valor'], 'USER_ID' => 123]);
        }
        ?>
        const todosLogsJs = <?= json_encode($lista_logs_erros) ?>; // Todos os logs já carregados
        const logEncontrado = todosLogsJs.find(log => log.id == logId);

        if (logEncontrado) {
            return { success: true, log: logEncontrado };
        }
        return { success: false, message: 'Log não encontrado (simulado).' };
    }

    function formatarDataHoraUserFriendly(dataIso) {
        if (!dataIso) return 'N/A';
        try {
            const d = new Date(dataIso);
            return d.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' });
        } catch (e) { return dataIso; }
    }
    function nl2br(str) {
        if (typeof str === 'undefined' || str === null) return '';
        return str.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
    }
    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        const map = { '&': '&', '<': '<', '>': '>', '"': '"', "'": ''' };
        return str.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

});
</script>