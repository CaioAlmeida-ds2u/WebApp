<?php
// admin/plataforma_gestao_planos_assinatura.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Precisará de funções CRUD para planos

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');
$erro_criar_msg = $_SESSION['erro_criar_plano'] ?? null; unset($_SESSION['erro_criar_plano']);
$form_data_plano = $_SESSION['form_data_plano'] ?? []; unset($_SESSION['form_data_plano']);


// --- Processamento de Ações POST (Criar, Ativar/Desativar, Excluir - similar a outras páginas CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão. Ação não executada.');
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'plano_csrf_falha', 0, 'Token inválido.', $conexao);
    } else {
        $action = $_POST['action'] ?? '';
        $plano_id_acao = filter_input(INPUT_POST, 'plano_id', FILTER_VALIDATE_INT);
        // Regenera token após uso (exceto para criação se houver erro de validação e precisar repopular)
        // Vamos regenerar no final do bloco POST se a ação não for 'criar_plano' com erro

        switch ($action) {
            case 'criar_plano':
                $dados_plano_form = [
                    'nome_plano' => trim($_POST['nome_plano'] ?? ''),
                    'descricao_plano' => trim($_POST['descricao_plano'] ?? ''),
                    'preco_mensal' => filter_input(INPUT_POST, 'preco_mensal', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
                    'limite_empresas_filhas' => filter_input(INPUT_POST, 'limite_empresas_filhas', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                    'limite_gestores_por_empresa' => filter_input(INPUT_POST, 'limite_gestores_por_empresa', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                    'limite_auditores_por_empresa' => filter_input(INPUT_POST, 'limite_auditores_por_empresa', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                    'limite_usuarios_auditados_por_empresa' => filter_input(INPUT_POST, 'limite_usuarios_auditados_por_empresa', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                    'limite_auditorias_ativas_por_empresa' => filter_input(INPUT_POST, 'limite_auditorias_ativas_por_empresa', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                    'limite_armazenamento_mb_por_empresa' => filter_input(INPUT_POST, 'limite_armazenamento_mb_por_empresa', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                    'permite_modelos_customizados_empresa' => isset($_POST['permite_modelos_customizados_empresa']) ? 1 : 0,
                    'permite_campos_personalizados_empresa' => isset($_POST['permite_campos_personalizados_empresa']) ? 1 : 0,
                    'ativo' => isset($_POST['ativo_plano']) ? 1 : 0
                    // funcionalides_extras_json precisa de tratamento especial se for um array
                ];

                if (empty($dados_plano_form['nome_plano'])) {
                    $_SESSION['erro_criar_plano'] = "O nome do plano é obrigatório.";
                    $_SESSION['form_data_plano'] = $_POST; // Repopular
                } else {
                    // *** Chamar função de backend: criarPlanoAssinatura($conexao, $dados_plano_form) ***
                    // Esta função precisará ser criada em admin_functions.php
                    $resultado_criacao = criarPlanoAssinatura($conexao, $dados_plano_form);
                    if ($resultado_criacao === true) {
                        definir_flash_message('sucesso', "Plano '".htmlspecialchars($dados_plano_form['nome_plano'])."' criado com sucesso!");
                        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_plano_sucesso', 1, "Plano: {$dados_plano_form['nome_plano']}", $conexao);
                         $_SESSION['csrf_token'] = gerar_csrf_token(); // Regenera
                    } else {
                        $_SESSION['erro_criar_plano'] = is_string($resultado_criacao) ? $resultado_criacao : "Erro desconhecido ao criar o plano.";
                        $_SESSION['form_data_plano'] = $_POST;
                        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_plano_falha', 0, "Erro: {$resultado_criacao}", $conexao);
                        // CSRF token não regenera aqui para repopular form com o mesmo token da tentativa
                    }
                }
                break;

            case 'ativar_plano':
            case 'desativar_plano':
                if ($plano_id_acao) {
                    $novo_status = ($action === 'ativar_plano');
                    // *** Chamar função de backend: setStatusPlanoAssinatura($conexao, $plano_id_acao, $novo_status) ***
                    if (setStatusPlanoAssinatura($conexao, $plano_id_acao, $novo_status)) {
                        definir_flash_message('sucesso', "Status do plano ID $plano_id_acao atualizado.");
                    } else {
                        definir_flash_message('erro', "Erro ao atualizar status do plano ID $plano_id_acao.");
                    }
                     $_SESSION['csrf_token'] = gerar_csrf_token();
                }
                break;

            case 'excluir_plano':
                if ($plano_id_acao) {
                    // *** Chamar função de backend: excluirPlanoAssinatura($conexao, $plano_id_acao) ***
                    // Esta função deve verificar se o plano está em uso por alguma empresa antes de excluir.
                    $resultado_exclusao = excluirPlanoAssinatura($conexao, $plano_id_acao);
                    if ($resultado_exclusao === true) {
                        definir_flash_message('sucesso', "Plano ID $plano_id_acao excluído com sucesso (se não estiver em uso).");
                    } else {
                        definir_flash_message('erro', is_string($resultado_exclusao) ? $resultado_exclusao : "Erro ao excluir plano ID $plano_id_acao.");
                    }
                     $_SESSION['csrf_token'] = gerar_csrf_token();
                }
                break;
            default:
                definir_flash_message('erro', "Ação desconhecida.");
                $_SESSION['csrf_token'] = gerar_csrf_token();
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); // Evitar reenvio do POST, mantém a aba ativa se houver
    exit;
}

// --- Busca de Dados para Exibição ---
// *** Chamar função de backend: listarPlanosAssinaturaPaginado($conexao, $pagina_atual, $itens_por_pagina, $filtros) ***
// Por agora, uma listagem simples:
$pagina_atual_planos = filter_input(INPUT_GET, 'pagina_planos', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina_planos = 10; // Exemplo
$planos_data = listarPlanosAssinaturaPaginado($conexao, $pagina_atual_planos, $itens_por_pagina_planos); // Função a ser criada
$lista_planos = $planos_data['planos'] ?? [];
$paginacao_planos = $planos_data['paginacao'] ?? ['total_paginas' => 0, 'pagina_atual' => 1, 'total_itens' => 0];


// Regenera token CSRF para os formulários na página (GET request ou após POST sem erro de validação)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['erro_criar_plano'])) {
    $_SESSION['csrf_token'] = gerar_csrf_token();
}
$csrf_token_page = $_SESSION['csrf_token']; // Para usar nos forms da página

// --- Geração do HTML ---
$title = "ACodITools - Gestão de Planos de Assinatura";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-file-invoice-dollar me-2"></i>Gestão de Planos de Assinatura</h1>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCriarPlano" aria-expanded="<?= $erro_criar_msg ? 'true' : 'false' ?>" aria-controls="collapseCriarPlano">
            <i class="fas fa-plus me-1"></i> Novo Plano
        </button>
    </div>

    <?php if ($sucesso_msg): ?><div class="alert alert-success custom-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger custom-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= $erro_msg ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <!-- Card para Criar Novo Plano (Colapsável) -->
    <div class="collapse <?= !empty($erro_criar_msg) ? 'show' : '' ?> mb-4" id="collapseCriarPlano">
        <div class="card shadow-sm border-start border-primary border-3">
            <div class="card-header bg-light"><i class="fas fa-plus-circle me-1"></i> Criar Novo Plano de Assinatura</div>
            <div class="card-body p-4">
                <?php if ($erro_criar_msg): ?>
                    <div class="alert alert-warning small p-2" role="alert"><?= $erro_criar_msg /* Permite HTML */ ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="createPlanoForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">
                    <input type="hidden" name="action" value="criar_plano">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nome_plano" class="form-label form-label-sm fw-semibold">Nome do Plano <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="nome_plano" name="nome_plano" value="<?= htmlspecialchars($form_data_plano['nome_plano'] ?? '') ?>" required maxlength="100">
                            <div class="invalid-feedback">Nome do plano é obrigatório.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="preco_mensal" class="form-label form-label-sm fw-semibold">Preço Mensal (ex: 99.90)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="preco_mensal" name="preco_mensal" value="<?= htmlspecialchars($form_data_plano['preco_mensal'] ?? '') ?>" placeholder="0.00">
                        </div>
                        <div class="col-12">
                            <label for="descricao_plano" class="form-label form-label-sm fw-semibold">Descrição Curta</label>
                            <textarea class="form-control form-control-sm" id="descricao_plano" name="descricao_plano" rows="2"><?= htmlspecialchars($form_data_plano['descricao_plano'] ?? '') ?></textarea>
                        </div>

                        <h6 class="mt-4 mb-1 fw-semibold small text-muted border-top pt-3">Limites de Recursos por Empresa:</h6>
                        <div class="col-md-4 col-lg-3">
                            <label for="limite_gestores_por_empresa" class="form-label form-label-sm">Gestores</label>
                            <input type="number" class="form-control form-control-sm" id="limite_gestores_por_empresa" name="limite_gestores_por_empresa" value="<?= htmlspecialchars($form_data_plano['limite_gestores_por_empresa'] ?? '1') ?>" min="0" title="0 ou vazio para ilimitado">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label for="limite_auditores_por_empresa" class="form-label form-label-sm">Auditores</label>
                            <input type="number" class="form-control form-control-sm" id="limite_auditores_por_empresa" name="limite_auditores_por_empresa" value="<?= htmlspecialchars($form_data_plano['limite_auditores_por_empresa'] ?? '5') ?>" min="0">
                        </div>
                         <div class="col-md-4 col-lg-3">
                            <label for="limite_usuarios_auditados_por_empresa" class="form-label form-label-sm">Auditados</label>
                            <input type="number" class="form-control form-control-sm" id="limite_usuarios_auditados_por_empresa" name="limite_usuarios_auditados_por_empresa" value="<?= htmlspecialchars($form_data_plano['limite_usuarios_auditados_por_empresa'] ?? '0') ?>" min="0" title="0 ou vazio para ilimitado">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label for="limite_auditorias_ativas_por_empresa" class="form-label form-label-sm">Auditorias Ativas</label>
                            <input type="number" class="form-control form-control-sm" id="limite_auditorias_ativas_por_empresa" name="limite_auditorias_ativas_por_empresa" value="<?= htmlspecialchars($form_data_plano['limite_auditorias_ativas_por_empresa'] ?? '10') ?>" min="0">
                        </div>
                         <div class="col-md-4 col-lg-3">
                            <label for="limite_armazenamento_mb_por_empresa" class="form-label form-label-sm">Armazenamento (MB)</label>
                            <input type="number" class="form-control form-control-sm" id="limite_armazenamento_mb_por_empresa" name="limite_armazenamento_mb_por_empresa" value="<?= htmlspecialchars($form_data_plano['limite_armazenamento_mb_por_empresa'] ?? '1024') ?>" min="0">
                        </div>
                         <div class="col-md-4 col-lg-3">
                            <label for="limite_empresas_filhas" class="form-label form-label-sm">Empresas Filhas</label>
                            <input type="number" class="form-control form-control-sm" id="limite_empresas_filhas" name="limite_empresas_filhas" value="<?= htmlspecialchars($form_data_plano['limite_empresas_filhas'] ?? '0') ?>" min="0" title="Para planos que permitem estrutura de holding. 0 para não permitir.">
                        </div>


                        <h6 class="mt-4 mb-1 fw-semibold small text-muted border-top pt-3">Permissões de Funcionalidades:</h6>
                        <div class="col-12">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="permite_modelos_customizados_empresa" name="permite_modelos_customizados_empresa" value="1" <?= !empty($form_data_plano['permite_modelos_customizados_empresa']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="permite_modelos_customizados_empresa">Permitir que empresas clientes customizem/criem modelos de auditoria</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="permite_campos_personalizados_empresa" name="permite_campos_personalizados_empresa" value="1" <?= !empty($form_data_plano['permite_campos_personalizados_empresa']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="permite_campos_personalizados_empresa">Permitir uso de campos personalizados (habilitados pela AcodITools)</label>
                            </div>
                        </div>
                        <div class="col-12 mt-3">
                             <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="ativo_plano" name="ativo_plano" value="1" <?= array_key_exists('ativo_plano', $form_data_plano) ? ($form_data_plano['ativo_plano'] ? 'checked' : '') : 'checked' ?>>
                                <label class="form-check-label" for="ativo_plano">Plano Ativo (disponível para novas assinaturas)</label>
                            </div>
                         </div>

                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-toggle="collapse" data-bs-target="#collapseCriarPlano">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Salvar Novo Plano</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Card Lista de Planos -->
    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="mb-0 fw-bold d-flex align-items-center"><i class="fas fa-list me-2 text-primary opacity-75"></i>Planos de Assinatura Cadastrados</h6>
            <span class="badge bg-secondary rounded-pill"><?= $paginacao_planos['total_itens'] ?? 0 ?> plano(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th>Nome do Plano</th>
                            <th>Preço Mensal</th>
                            <th class="text-center">Empresas</th>
                            <th class="text-center">Auditores</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 15%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_planos)): ?>
                            <tr><td colspan="7" class="text-center text-muted p-4"><i class="fas fa-info-circle d-block fs-4 mb-2 opacity-50"></i>Nenhum plano de assinatura cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_planos as $plano): ?>
                                <tr>
                                    <td class="fw-bold">#<?= htmlspecialchars($plano['id']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($plano['nome_plano']) ?>
                                        <?php if(!empty($plano['descricao_plano'])): ?>
                                            <small class="d-block text-muted text-truncate" title="<?= htmlspecialchars($plano['descricao_plano']) ?>" style="max-width: 300px;"><?= htmlspecialchars(mb_strimwidth($plano['descricao_plano'], 0, 70, "...")) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>R$ <?= htmlspecialchars(number_format($plano['preco_mensal'] ?? 0, 2, ',', '.')) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($plano['limite_empresas_filhas'] ?? 'N/A') ?></td>
                                    <td class="text-center"><?= htmlspecialchars($plano['limite_auditores_por_empresa'] ?? 'N/A') ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $plano['ativo'] ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle' ?>">
                                            <?= $plano['ativo'] ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td class="text-center action-buttons-table">
                                        <div class="d-inline-flex flex-nowrap">
                                            <a href="<?= BASE_URL ?>admin/plataforma_editar_plano.php?id=<?= $plano['id'] ?>" class="btn btn-sm btn-outline-primary me-1 action-btn" title="Editar Plano"><i class="fas fa-edit fa-fw"></i></a>
                                            <?php if ($plano['ativo']): ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Desativar o plano <?= htmlspecialchars(addslashes($plano['nome_plano'])) ?>?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="desativar_plano"><input type="hidden" name="plano_id" value="<?= $plano['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary action-btn" title="Desativar"><i class="fas fa-toggle-off fa-fw"></i></button></form>
                                            <?php else: ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Ativar o plano <?= htmlspecialchars(addslashes($plano['nome_plano'])) ?>?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="ativar_plano"><input type="hidden" name="plano_id" value="<?= $plano['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-success action-btn" title="Ativar"><i class="fas fa-toggle-on fa-fw"></i></button></form>
                                            <?php endif; ?>
                                            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline" onsubmit="return confirm('EXCLUIR plano <?= htmlspecialchars(addslashes($plano['nome_plano'])) ?>? Verifique se não está em uso por empresas clientes.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>"><input type="hidden" name="action" value="excluir_plano"><input type="hidden" name="plano_id" value="<?= $plano['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Excluir Plano"><i class="fas fa-trash-alt fa-fw"></i></button></form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (isset($paginacao_planos) && $paginacao_planos['total_paginas'] > 1): ?>
        <div class="card-footer bg-light py-2">
            <nav aria-label="Paginação de Planos">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php $link_pag = "?pagina_planos="; ?>
                    <?php if ($paginacao_planos['pagina_atual'] > 1): ?><li class="page-item"><a class="page-link" href="<?= $link_pag . ($paginacao_planos['pagina_atual'] - 1) ?>">«</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">«</span></li><?php endif; ?>
                    <?php $inicio_p = max(1, $paginacao_planos['pagina_atual'] - 2); $fim_p = min($paginacao_planos['total_paginas'], $paginacao_planos['pagina_atual'] + 2); if ($inicio_p > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; for ($i = $inicio_p; $i <= $fim_p; $i++): ?><li class="page-item <?= ($i == $paginacao_planos['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="<?= $link_pag . $i ?>"><?= $i ?></a></li><?php endfor; if ($fim_p < $paginacao_planos['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                    <?php if ($paginacao_planos['pagina_atual'] < $paginacao_planos['total_paginas']): ?><li class="page-item"><a class="page-link" href="<?= $link_pag . ($paginacao_planos['pagina_atual'] + 1) ?>">»</a></li><?php else: ?><li class="page-item disabled"><span class="page-link">»</span></li><?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validação Bootstrap
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Foco no campo nome ao mostrar o form de criação por erro
    <?php if ($erro_criar_msg): ?>
        const collapseElement = document.getElementById('collapseCriarPlano');
        if (collapseElement) {
            const bsCollapse = new bootstrap.Collapse(collapseElement, { toggle: false });
            bsCollapse.show();
            const nomePlanoInput = document.getElementById('nome_plano');
            if (nomePlanoInput) { setTimeout(() => nomePlanoInput.focus(), 200); }
        }
    <?php endif; ?>
});
</script>

<?php
echo getFooterAdmin();
?>