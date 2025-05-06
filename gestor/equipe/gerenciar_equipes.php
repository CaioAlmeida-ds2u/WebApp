<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_gestor.php';
require_once __DIR__ . '/../../includes/gestor_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$empresa_id = $_SESSION['usuario_empresa_id'];
$gestor_id = $_SESSION['usuario_id'];

// Processamento de Ações POST (Ativar/Desativar/Excluir)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!validar_csrf_token($_POST['csrf_token'])) {
        definir_flash_message('erro', 'Erro de validação da sessão. Tente novamente.');
    } else {
        $action = $_POST['action'] ?? '';
        $equipe_id_acao = filter_input(INPUT_POST, 'equipe_id', FILTER_VALIDATE_INT);

        if ($equipe_id_acao) {
            switch ($action) {
                case 'ativar_equipe':
                    // Implementar função ativarEquipe em gestor_functions.php
                    // Por agora, podemos usar a atualizarEquipe (precisaria do nome/desc antigos ou buscar)
                    // Vamos simplificar: atualiza apenas o status 'ativo'
                    if (atualizarStatusEquipe($conexao, $equipe_id_acao, true, $empresa_id)) {
                        definir_flash_message('sucesso', "Equipe ID $equipe_id_acao ativada.");
                    } else {
                        definir_flash_message('erro', "Erro ao ativar equipe ID $equipe_id_acao.");
                    }
                    break;
                case 'desativar_equipe':
                    if (atualizarStatusEquipe($conexao, $equipe_id_acao, false, $empresa_id)) {
                        definir_flash_message('sucesso', "Equipe ID $equipe_id_acao desativada.");
                    } else {
                        definir_flash_message('erro', "Erro ao desativar equipe ID $equipe_id_acao.");
                    }
                    break;
                case 'excluir_equipe':
                    $resultado_exclusao = excluirEquipe($conexao, $equipe_id_acao, $empresa_id);
                    if ($resultado_exclusao === "sucesso") {
                        definir_flash_message('sucesso', "Equipe ID $equipe_id_acao excluída.");
                    } elseif ($resultado_exclusao === "em_uso") {
                        definir_flash_message('erro', "Equipe ID $equipe_id_acao não pode ser excluída pois está vinculada a auditorias.");
                    } else {
                        definir_flash_message('erro', "Erro ao excluir equipe ID $equipe_id_acao.");
                    }
                    break;
            }
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']); // Redireciona após POST
    exit;
}


$filtros_equipe = [];
if (isset($_GET['filtro_nome']) && !empty(trim($_GET['filtro_nome']))) {
    $filtros_equipe['nome'] = trim(filter_input(INPUT_GET, 'filtro_nome', FILTER_SANITIZE_STRING));
}
if (isset($_GET['filtro_status']) && $_GET['filtro_status'] !== '') {
    $filtros_equipe['ativo'] = filter_input(INPUT_GET, 'filtro_status', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
}


// Busca equipes da empresa do gestor
$equipes_data = getTodasEquipesDaEmpresaPaginado($conexao, $empresa_id, $filtros_equipe); // Precisamos desta função com paginação
$equipes = $equipes_data['equipes'];
$paginacao = $equipes_data['paginacao'];

$csrf_token = gerar_csrf_token();
$title = "Gerenciar Equipes de Auditoria";
echo getHeaderGestor($title);
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-users-cog me-2 text-primary"></i>Gerenciar Equipes</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb"><li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page">Equipes</li></ol></nav>
    </div>
    <a href="<?= BASE_URL ?>gestor/equipe/criar_equipe.php" class="btn btn-success rounded-pill px-3 action-button-main"><i class="fas fa-plus me-1"></i> Nova Equipe</a>
</div>

<?php if ($msg_erro = obter_flash_message('erro')): ?>
    <div class="alert alert-danger gestor-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i> <?= $msg_erro ?> <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<?php if ($msg_sucesso = obter_flash_message('sucesso')): ?>
    <div class="alert alert-success gestor-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i> <?= $msg_sucesso ?> <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>

<div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
    <div class="card-header bg-light border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center flex-wrap">
        <h6 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-primary opacity-75"></i>Equipes Cadastradas (<?= htmlspecialchars($paginacao['total_itens'] ?? 0) ?>)</h6>
        <!-- Formulário de Filtro -->
        <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex ms-auto small align-items-center">
            <input type="text" name="filtro_nome" class="form-control form-control-sm me-2" placeholder="Filtrar por nome..." value="<?= htmlspecialchars($filtros_equipe['nome'] ?? '') ?>" style="max-width: 150px;">
            <select name="filtro_status" class="form-select form-select-sm me-2" style="max-width: 120px;">
                <option value="">Todos Status</option>
                <option value="1" <?= (isset($filtros_equipe['ativo']) && $filtros_equipe['ativo'] == 1) ? 'selected' : '' ?>>Ativas</option>
                <option value="0" <?= (isset($filtros_equipe['ativo']) && $filtros_equipe['ativo'] == 0) ? 'selected' : '' ?>>Inativas</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrar</button>
            <?php if (!empty($filtros_equipe)): ?>
                <a href="<?= BASE_URL ?>gestor/equipe/gerenciar_equipes.php" class="btn btn-sm btn-outline-light ms-1" title="Limpar Filtros"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped table-sm mb-0 align-middle">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th>Nome da Equipe</th>
                        <th>Descrição</th>
                        <th class="text-center">Membros</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 20%;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($equipes)): ?>
                        <tr><td colspan="5" class="text-center text-muted p-4"><i class="fas fa-info-circle d-block fs-4 mb-2 opacity-50"></i>Nenhuma equipe encontrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($equipes as $equipe): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($equipe['nome']) ?></td>
                                <td class="small text-muted" title="<?= htmlspecialchars($equipe['descricao'] ?? '') ?>"><?= htmlspecialchars(mb_strimwidth($equipe['descricao'] ?? 'N/A', 0, 70, "...")) ?></td>
                                <td class="text-center"><?= (int)$equipe['total_membros'] ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $equipe['ativo'] ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle' ?>">
                                        <?= $equipe['ativo'] ? 'Ativa' : 'Inativa' ?>
                                    </span>
                                </td>
                                <td class="text-center action-buttons-table">
                                    <div class="d-inline-flex flex-nowrap">
                                        <a href="<?= BASE_URL ?>gestor/equipe/editar_equipe.php?id=<?= $equipe['id'] ?>" class="btn btn-sm btn-outline-primary me-1 action-btn" title="Editar Equipe e Membros">
                                            <i class="fas fa-edit fa-fw"></i>
                                        </a>
                                        <?php if ($equipe['ativo']): ?>
                                            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Desativar equipe <?= htmlspecialchars(addslashes($equipe['nome'])) ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="action" value="desativar_equipe">
                                                <input type="hidden" name="equipe_id" value="<?= $equipe['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary action-btn" title="Desativar"><i class="fas fa-toggle-off fa-fw"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Ativar equipe <?= htmlspecialchars(addslashes($equipe['nome'])) ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="action" value="ativar_equipe">
                                                <input type="hidden" name="equipe_id" value="<?= $equipe['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success action-btn" title="Ativar"><i class="fas fa-toggle-on fa-fw"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline" onsubmit="return confirm('EXCLUIR equipe <?= htmlspecialchars(addslashes($equipe['nome'])) ?>? Esta ação não pode ser desfeita. Verifique se não está em uso.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="action" value="excluir_equipe">
                                            <input type="hidden" name="equipe_id" value="<?= $equipe['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Excluir Equipe"><i class="fas fa-trash-alt fa-fw"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (isset($paginacao) && $paginacao['total_paginas'] > 1): ?>
        <div class="card-footer bg-light py-2">
            <nav aria-label="Paginação de Equipes">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php
                    $queryParams = $_GET; // Pega todos os query params atuais
                    unset($queryParams['pagina']); // Remove 'pagina' para construir o link base
                    $link_base_paginacao = http_build_query($queryParams);
                    if (!empty($link_base_paginacao)) $link_base_paginacao .= '&';
                    $link_base_paginacao = "?" . $link_base_paginacao . "pagina=";
                    ?>
                    <?php if ($paginacao['pagina_atual'] > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= $link_base_paginacao . ($paginacao['pagina_atual'] - 1) ?>">«</a></li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link">«</span></li>
                    <?php endif; ?>

                    <?php
                    $inicio_pag = max(1, $paginacao['pagina_atual'] - 2);
                    $fim_pag = min($paginacao['total_paginas'], $paginacao['pagina_atual'] + 2);
                    if ($inicio_pag > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    for ($i = $inicio_pag; $i <= $fim_pag; $i++):
                    ?>
                        <li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $link_base_paginacao . $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor;
                    if ($fim_pag < $paginacao['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    ?>

                    <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?>
                        <li class="page-item"><a class="page-link" href="<?= $link_base_paginacao . ($paginacao['pagina_atual'] + 1) ?>">»</a></li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link">»</span></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php
echo getFooterGestor();
?>