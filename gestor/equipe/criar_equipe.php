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
$nome_equipe_post = '';
$descricao_equipe_post = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $nome_equipe_post = trim(filter_input(INPUT_POST, 'nome_equipe', FILTER_SANITIZE_STRING));
        $descricao_equipe_post = trim(filter_input(INPUT_POST, 'descricao_equipe', FILTER_SANITIZE_STRING));

        if (empty($nome_equipe_post)) {
            definir_flash_message('erro', 'O nome da equipe é obrigatório.');
        } else {
            $nova_equipe_id = criarEquipe($conexao, $nome_equipe_post, $descricao_equipe_post, $empresa_id, $gestor_id);
            if ($nova_equipe_id && $nova_equipe_id > 0) {
                definir_flash_message('sucesso', "Equipe '".htmlspecialchars($nome_equipe_post)."' criada com sucesso! Você pode adicionar membros editando a equipe.");
                header('Location: ' . BASE_URL . 'gestor/equipe/gerenciar_equipes.php');
                exit;
            } elseif ($nova_equipe_id === -1) { // Código para nome duplicado
                 definir_flash_message('erro', "Já existe uma equipe com o nome '".htmlspecialchars($nome_equipe_post)."' na sua empresa.");
            }
            else {
                definir_flash_message('erro', 'Erro ao criar a equipe. Tente novamente.');
            }
        }
    }
    // Se houve erro, permanece na página e repopula os campos
}

$csrf_token = gerar_csrf_token();
$title = "Criar Nova Equipe";
echo getHeaderGestor($title);
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-users-plus me-2 text-primary"></i>Criar Nova Equipe</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb small bg-transparent p-0 m-0 page-breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/dashboard_gestor.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>gestor/equipe/gerenciar_equipes.php">Equipes</a></li>
            <li class="breadcrumb-item active" aria-current="page">Criar</li>
        </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>gestor/equipe/gerenciar_equipes.php" class="btn btn-outline-secondary rounded-pill px-3 action-button-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar para Equipes</a>
</div>

<?php if ($msg_erro = obter_flash_message('erro')): ?>
    <div class="alert alert-danger gestor-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i> <?= $msg_erro ?> <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<?php if ($msg_sucesso = obter_flash_message('sucesso')): // Raramente mostrado aqui, pois redireciona ?>
    <div class="alert alert-success gestor-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i> <?= $msg_sucesso ?> <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>

<div class="card shadow-sm dashboard-list-card mb-4 rounded-3 border-0">
    <div class="card-header bg-light border-bottom pt-3 pb-2">
        <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary opacity-75"></i>Dados da Equipe</h6>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="formCriarEquipe" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="row g-3">
                <div class="col-12">
                    <label for="nome_equipe" class="form-label form-label-sm fw-semibold">Nome da Equipe <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="nome_equipe" name="nome_equipe" value="<?= htmlspecialchars($nome_equipe_post) ?>" required maxlength="100">
                    <div class="invalid-feedback">O nome da equipe é obrigatório (máx. 100 caracteres).</div>
                </div>
                <div class="col-12">
                    <label for="descricao_equipe" class="form-label form-label-sm fw-semibold">Descrição (Opcional)</label>
                    <textarea class="form-control form-control-sm" id="descricao_equipe" name="descricao_equipe" rows="3" maxlength="500"><?= htmlspecialchars($descricao_equipe_post) ?></textarea>
                    <div class="form-text small text-muted">Breve descrição sobre o foco ou membros da equipe (máx. 500 caracteres).</div>
                </div>
            </div>
            <div class="mt-4 d-flex justify-content-end">
                <a href="<?= BASE_URL ?>gestor/equipe/gerenciar_equipes.php" class="btn btn-secondary rounded-pill px-4 me-2">Cancelar</a>
                <button type="submit" class="btn btn-success rounded-pill px-4 action-button-main"><i class="fas fa-save me-1"></i> Salvar Equipe</button>
            </div>
        </form>
    </div>
</div>

<script>
// Validação Bootstrap padrão
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