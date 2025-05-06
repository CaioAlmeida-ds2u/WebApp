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
$equipe_id_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$equipe_id_edit) {
    definir_flash_message('erro', 'ID da equipe inválido.');
    header('Location: ' . BASE_URL . 'gestor/equipe/gerenciar_equipes.php');
    exit;
}

// Busca detalhes da equipe e seus membros (garantindo que pertence à empresa do gestor)
$equipe = getDetalhesEquipe($conexao, $equipe_id_edit, $empresa_id);

if (!$equipe) {
    definir_flash_message('erro', "Equipe não encontrada ou não pertence à sua empresa.");
    header('Location: ' . BASE_URL . 'gestor/equipe/gerenciar_equipes.php');
    exit;
}

// Auditores disponíveis da empresa para adicionar à equipe (que ainda não são membros)
$auditores_todos_empresa = getAuditoresDaEmpresa($conexao, $empresa_id);
$ids_membros_atuais = array_column($equipe['membros'], 'id');
$auditores_disponiveis = array_filter($auditores_todos_empresa, function ($auditor) use ($ids_membros_atuais) {
    return !in_array($auditor['id'], $ids_membros_atuais);
});


// Processamento de Ações POST (Salvar dados, Adicionar/Remover Membro)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!validar_csrf_token($_POST['csrf_token'])) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'salvar_dados_equipe':
                $nome_equipe_post = trim(filter_input(INPUT_POST, 'nome_equipe', FILTER_SANITIZE_STRING));
                $descricao_equipe_post = trim(filter_input(INPUT_POST, 'descricao_equipe', FILTER_SANITIZE_STRING));
                $ativo_post = isset($_POST['ativo']) && $_POST['ativo'] == '1';

                if (empty($nome_equipe_post)) {
                    definir_flash_message('erro', 'O nome da equipe é obrigatório.');
                } else {
                    if (atualizarEquipe($conexao, $equipe_id_edit, $nome_equipe_post, $descricao_equipe_post, $ativo_post, $empresa_id, $gestor_id)) {
                        definir_flash_message('sucesso', 'Dados da equipe atualizados.');
                        // Recarregar dados após salvar para refletir mudanças imediatamente
                        $equipe = getDetalhesEquipe($conexao, $equipe_id_edit, $empresa_id);
                    } else {
                        definir_flash_message('erro', 'Erro ao atualizar dados da equipe. Verifique se o nome já não existe.');
                    }
                }
                break;

            case 'adicionar_membro':
                $auditor_id_add = filter_input(INPUT_POST, 'auditor_id_add', FILTER_VALIDATE_INT);
                if ($auditor_id_add) {
                    if (adicionarMembroEquipe($conexao, $equipe_id_edit, $auditor_id_add, $empresa_id)) {
                        definir_flash_message('sucesso', 'Auditor adicionado à equipe.');
                        // Recarregar dados e lista de disponíveis
                        $equipe = getDetalhesEquipe($conexao, $equipe_id_edit, $empresa_id);
                        $ids_membros_atuais = array_column($equipe['membros'], 'id');
                        $auditores_disponiveis = array_filter($auditores_todos_empresa, fn($auditor) => !in_array($auditor['id'], $ids_membros_atuais));
                    } else {
                        definir_flash_message('erro', 'Erro ao adicionar auditor à equipe. Auditor pode não pertencer à empresa ou já ser membro.');
                    }
                }
                break;

            case 'remover_membro':
                $auditor_id_remove = filter_input(INPUT_POST, 'auditor_id_remove', FILTER_VALIDATE_INT);
                if ($auditor_id_remove) {
                    if (removerMembroEquipe($conexao, $equipe_id_edit, $auditor_id_remove)) {
                        definir_flash_message('sucesso', 'Auditor removido da equipe.');
                         // Recarregar dados e lista de disponíveis
                        $equipe = getDetalhesEquipe($conexao, $equipe_id_edit, $empresa_id);
                        $ids_membros_atuais = array_column($equipe['membros'], 'id');
                        $auditores_disponiveis = array_filter($auditores_todos_empresa, fn($auditor) => !in_array($auditor['id'], $ids_membros_atuais));
                    } else {
                        definir_flash_message('erro', 'Erro ao remover auditor da equipe.');
                    }
                }
                break;
        }
    }
    // Redirecionar para a mesma página para mostrar mensagens e dados atualizados
    header('Location: ' . BASE_URL . 'gestor/equipe/editar_equipe.php?id=' . $equipe_id_edit);
    exit;
}


$csrf_token = gerar_csrf_token();
$title = "Editar Equipe: " . htmlspecialchars($equipe['nome']);
echo getHeaderGestor($title);
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-users-edit me-2 text-primary"></i>Editar Equipe: <span class="fw-normal"><?= htmlspecialchars($equipe['nome']) ?></span></h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/equipe/gerenciar_equipes.php">Equipes</a></li>
            <li class="breadcrumb-item active" aria-current="page">Editar</li>
        </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>gestor/equipe/gerenciar_equipes.php" class="btn btn-outline-secondary rounded-pill px-3 action-button-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar para Equipes</a>
</div>

<?php if ($msg_erro = obter_flash_message('erro')): ?>
    <div class="alert alert-danger gestor-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i> <?= $msg_erro ?> <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<?php if ($msg_sucesso = obter_flash_message('sucesso')): ?>
    <div class="alert alert-success gestor-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i> <?= $msg_sucesso ?> <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Coluna Dados da Equipe -->
    <div class="col-lg-5">
        <div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
            <div class="card-header bg-light border-bottom pt-3 pb-2">
                <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary opacity-75"></i>Dados da Equipe</h6>
            </div>
            <div class="card-body p-3">
                <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" id="formEditarEquipe" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="salvar_dados_equipe">
                    <div class="mb-3">
                        <label for="nome_equipe" class="form-label form-label-sm fw-semibold">Nome da Equipe <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="nome_equipe" name="nome_equipe" value="<?= htmlspecialchars($equipe['nome']) ?>" required maxlength="100">
                        <div class="invalid-feedback">Nome obrigatório.</div>
                    </div>
                    <div class="mb-3">
                        <label for="descricao_equipe" class="form-label form-label-sm fw-semibold">Descrição</label>
                        <textarea class="form-control form-control-sm" id="descricao_equipe" name="descricao_equipe" rows="3" maxlength="500"><?= htmlspecialchars($equipe['descricao'] ?? '') ?></textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?= $equipe['ativo'] ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="ativo">Equipe Ativa</label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-save me-1"></i> Salvar Alterações</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Coluna Gerenciar Membros -->
    <div class="col-lg-7">
        <div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
            <div class="card-header bg-light border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-user-friends me-2 text-primary opacity-75"></i>Membros da Equipe (<?= count($equipe['membros']) ?>)</h6>
            </div>
            <div class="card-body p-3">
                <!-- Adicionar Membro -->
                <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" class="mb-3 p-3 border rounded bg-light-subtle">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="adicionar_membro">
                    <label for="auditor_id_add" class="form-label form-label-sm fw-semibold">Adicionar Auditor à Equipe:</label>
                    <div class="input-group input-group-sm">
                        <select class="form-select form-select-sm" id="auditor_id_add" name="auditor_id_add" required>
                            <option value="">-- Selecione um Auditor --</option>
                            <?php foreach ($auditores_disponiveis as $auditor_disp): ?>
                                <option value="<?= $auditor_disp['id'] ?>"><?= htmlspecialchars($auditor_disp['nome']) ?> (<?= htmlspecialchars($auditor_disp['email']) ?>)</option>
                            <?php endforeach; ?>
                            <?php if (empty($auditores_disponiveis)): ?>
                                <option value="" disabled>Nenhum auditor disponível para adicionar</option>
                            <?php endif; ?>
                        </select>
                        <button class="btn btn-success" type="submit" <?= empty($auditores_disponiveis) ? 'disabled' : '' ?>><i class="fas fa-plus me-1"></i> Adicionar</button>
                    </div>
                    <div class="invalid-feedback">Selecione um auditor para adicionar.</div> <!-- Para validação JS se necessário -->
                </form>

                <!-- Lista de Membros Atuais -->
                <?php if (empty($equipe['membros'])): ?>
                    <p class="text-muted text-center">Nenhum membro nesta equipe ainda.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($equipe['membros'] as $membro): ?>
                            <li class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-2">
                                <div>
                                    <i class="fas fa-user fa-fw text-muted me-2"></i><?= htmlspecialchars($membro['nome']) ?>
                                    <small class="text-muted ms-1">(<?= htmlspecialchars($membro['email']) ?>)</small>
                                </div>
                                <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" onsubmit="return confirm('Remover <?= htmlspecialchars(addslashes($membro['nome'])) ?> desta equipe?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="remover_membro">
                                    <input type="hidden" name="auditor_id_remove" value="<?= $membro['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger border-0 py-0 px-1" title="Remover Membro"><i class="fas fa-times"></i></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<script>
// Validação Bootstrap padrão (pode colocar em um arquivo JS global se usar em mais páginas)
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>
<?php
echo getFooterGestor();
?>