<?php
// admin/modelo/modelo_index.php - CORRIGIDO: Lógica de criação restaurada

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_admin.php';
require_once __DIR__ . '/../../includes/admin_functions.php'; // Precisa de criarModeloAuditoria, etc.

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { header('Location: '.BASE_URL.'acesso_negado.php'); exit; }

// --- Mensagens e Formulário ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');
$erro_criar_msg = $_SESSION['erro_criar_modelo'] ?? null; unset($_SESSION['erro_criar_modelo']);
$form_data = $_SESSION['form_data_modelo'] ?? []; unset($_SESSION['form_data_modelo']);

// --- Processamento POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', "Erro de validação da sessão.");
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'modelo_csrf_falha', 0, 'Token inválido.', $conexao);
    } else {
        $action = $_POST['action'] ?? null;
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $_SESSION['csrf_token'] = gerar_csrf_token(); // Regenera

        switch($action) {
            // **** INÍCIO DO CASE RESTAURADO/CORRIGIDO ****
            case 'criar_modelo':
                $nome = trim($_POST['nome_modelo'] ?? '');
                $descricao = trim($_POST['descricao_modelo'] ?? '');
                $errors = [];

                if (empty($nome)) {
                    $errors[] = "O nome do modelo é obrigatório.";
                } // Adicionar outras validações se necessário

                if (empty($errors)) {
                    // Chama a função PHP para criar o modelo no banco
                    $resultado = criarModeloAuditoria($conexao, $nome, $descricao ?: null, $_SESSION['usuario_id']);

                    if ($resultado === true) {
                        definir_flash_message('sucesso', "Modelo '".htmlspecialchars($nome)."' criado com sucesso!");
                        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_modelo_sucesso', 1, "Modelo criado: $nome", $conexao);
                        // Limpa form_data em caso de sucesso
                        unset($_SESSION['form_data_modelo']);
                    } else {
                        // Erro retornado pela função (ex: duplicado, erro DB)
                        $_SESSION['erro_criar_modelo'] = $resultado; // Guarda erro específico
                        $_SESSION['form_data_modelo'] = $_POST; // Guarda dados para repopular
                        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_modelo_falha_db', 0, "Falha: $resultado", $conexao);
                    }
                } else {
                    // Erro de validação antes de chamar a função
                    $_SESSION['erro_criar_modelo'] = "<strong>Erro ao criar:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
                    $_SESSION['form_data_modelo'] = $_POST; // Guarda dados para repopular
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_modelo_falha_valid', 0, "Erro de validação: " . implode(', ', $errors), $conexao);
                }
                break; // Fim do case 'criar_modelo'
             // **** FIM DO CASE RESTAURADO/CORRIGIDO ****

            case 'ativar_modelo':
                if ($id && ativarModeloAuditoria($conexao, $id)) { definir_flash_message('sucesso', "Modelo ID $id ativado."); dbRegistrarLogAcesso(...); }
                else { definir_flash_message('erro', "Erro ao ativar modelo ID $id."); dbRegistrarLogAcesso(...); }
                break;

            case 'desativar_modelo':
                 if ($id && desativarModeloAuditoria($conexao, $id)) { definir_flash_message('sucesso', "Modelo ID $id desativado."); dbRegistrarLogAcesso(...); }
                 else { definir_flash_message('erro', "Erro ao desativar modelo ID $id."); dbRegistrarLogAcesso(...); }
                break;

            case 'excluir_modelo':
                 if($id) {
                    $resultado_exclusao = excluirModeloAuditoria($conexao, $id);
                     if ($resultado_exclusao === true) { definir_flash_message('sucesso', "Modelo ID $id excluído."); dbRegistrarLogAcesso(...); }
                     else { $msgErroExclusao = is_string($resultado_exclusao) ? $resultado_exclusao : "Erro ao excluir modelo ID $id."; definir_flash_message('erro', $msgErroExclusao); dbRegistrarLogAcesso(...);}
                 } else { definir_flash_message('erro', "ID inválido para exclusão."); dbRegistrarLogAcesso(...); }
                 break;

            default:
                definir_flash_message('erro', "Ação desconhecida.");
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'modelo_acao_desconhecida', 0, 'Ação: ' . ($action ?? 'N/A'), $conexao);
        } // Fim do switch
    } // Fim do else CSRF válido
    header('Location: ' . $_SERVER['PHP_SELF']); exit; // Redireciona após processar POST
} // Fim if POST

// --- Paginação e Busca ---
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina = 15;
$modelos_data = getModelosAuditoria($conexao, $pagina_atual, $itens_por_pagina);
$lista_modelos = $modelos_data['modelos'];
$paginacao = $modelos_data['paginacao'];

// --- HTML ---
$title = "Modelos de Auditoria";
echo getHeaderAdmin($title);
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom flex-wrap">
        <h1 class="h2"><i class="fas fa-clipboard-list me-2"></i>Modelos de Auditoria</h1>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCriarModelo" aria-expanded="<?= $erro_criar_msg ? 'true' : 'false' ?>"><i class="fas fa-plus me-1"></i> Novo Modelo</button>
    </div>
    <?php /* Mensagens */ ?>
    <?php if ($sucesso_msg): ?><div class="alert alert-success custom-alert fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger custom-alert fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($erro_msg) ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <?php /* Card Criar Modelo */ ?>
    <div class="collapse <?= $erro_criar_msg ? 'show' : '' ?> mb-4" id="collapseCriarModelo">
        <div class="card shadow-sm border-start border-primary border-3">
            <div class="card-header bg-light"><i class="fas fa-plus-circle me-1"></i> Criar Novo Modelo</div>
            <div class="card-body">
                <?php if ($erro_criar_msg): ?>
                    <div class="alert alert-warning small p-2" role="alert"><?= $erro_criar_msg /* Permite HTML */ ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="createModelForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerar_csrf_token()) ?>">
                    <input type="hidden" name="action" value="criar_modelo">
                    <div class="mb-3">
                        <label for="nome_modelo" class="form-label form-label-sm fw-semibold">Nome do Modelo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="nome_modelo" name="nome_modelo" required maxlength="255" value="<?= htmlspecialchars($form_data['nome_modelo'] ?? '') ?>">
                        <div class="invalid-feedback">Nome obrigatório (máx. 255).</div>
                    </div>
                    <div class="mb-3">
                        <label for="descricao_modelo" class="form-label form-label-sm fw-semibold">Descrição</label>
                        <textarea class="form-control form-control-sm" id="descricao_modelo" name="descricao_modelo" rows="3"><?= htmlspecialchars($form_data['descricao_modelo'] ?? '') ?></textarea>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-toggle="collapse" data-bs-target="#collapseCriarModelo">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Salvar Modelo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

     <?php /* Card Lista de Modelos */ ?>
    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="mb-0 fw-bold d-flex align-items-center"><i class="fas fa-list me-2 text-primary opacity-75"></i>Modelos Cadastrados</h6>
            <span class="badge bg-secondary rounded-pill"><?= $paginacao['total_itens'] ?? 0 ?> item(s)</span>
        </div>
        <div class="card-body p-0">
             <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                     <thead class="table-light small text-uppercase text-muted"><tr><th style="width: 5%;">ID</th><th style="width: 30%;">Nome</th><th style="width: 35%;">Descrição</th><th class="text-center" style="width: 10%;">Status</th><th class="text-center" style="width: 20%;">Ações</th></tr></thead>
                     <tbody>
                        <?php if (empty($lista_modelos)): ?> <tr><td colspan="5" class="text-center text-muted p-4"><i class="fas fa-info-circle d-block fs-4 mb-2 opacity-50"></i>Nenhum modelo cadastrado.</td></tr>
                        <?php else: foreach ($lista_modelos as $modelo): ?>
                        <tr>
                            <td class="fw-bold">#<?= htmlspecialchars($modelo['id']) ?></td><td><?= htmlspecialchars($modelo['nome']) ?></td><td class="small text-muted" title="<?= htmlspecialchars($modelo['descricao'] ?? '') ?>"><?= htmlspecialchars(mb_strimwidth($modelo['descricao'] ?? '', 0, 90, "...")) ?></td>
                            <td class="text-center"><span class="badge <?= $modelo['ativo']?'bg-success-subtle text-success-emphasis border border-success-subtle':'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle' ?>"><?= $modelo['ativo']?'Ativo':'Inativo' ?></span></td>
                            <td class="text-center action-buttons-table"><div class="d-inline-flex flex-nowrap">
                                <a href="<?= BASE_URL ?>admin/modelo/editar_modelo.php?id=<?= $modelo['id'] ?>" class="btn btn-sm btn-outline-primary me-1 action-btn" title="Editar Modelo e Seus Itens"><i class="fas fa-edit fa-fw"></i></a>
                                <?php if ($modelo['ativo']): ?><form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Desativar <?= htmlspecialchars(addslashes($modelo['nome'])) ?>?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="action" value="desativar_modelo"><input type="hidden" name="id" value="<?= $modelo['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary action-btn" title="Desativar"><i class="fas fa-toggle-off fa-fw"></i></button></form>
                                <?php else: ?><form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Ativar <?= htmlspecialchars(addslashes($modelo['nome'])) ?>?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="action" value="ativar_modelo"><input type="hidden" name="id" value="<?= $modelo['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-success action-btn" title="Ativar"><i class="fas fa-toggle-on fa-fw"></i></button></form>
                                <?php endif; ?>
                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline" onsubmit="return confirm('ATENÇÃO! Excluir <?= htmlspecialchars(addslashes($modelo['nome'])) ?>? Verifique se não está em uso em auditorias.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="action" value="excluir_modelo"><input type="hidden" name="id" value="<?= $modelo['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Excluir Modelo"><i class="fas fa-trash-alt fa-fw"></i></button></form>
                            </div></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
             </div>
        </div>
         <?php /* Paginação */ ?>
         <?php if (isset($paginacao) && $paginacao['total_paginas'] > 1): ?>
         <div class="card-footer bg-light py-2"> <nav aria-label="Paginação de Modelos"> <ul class="pagination pagination-sm justify-content-center mb-0"> <?php /* $link_paginacao = "?" . http_build_query($filtros_ativos_pag) . "&pagina="; // Se houver filtros */ $link_paginacao = "?pagina="; ?> <?php if ($paginacao['pagina_atual'] > 1): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] - 1) ?>">«</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">«</span></li> <?php endif; ?> <?php $inicio = max(1, $paginacao['pagina_atual'] - 2); $fim = min($paginacao['total_paginas'], $paginacao['pagina_atual'] + 2); if ($inicio > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; for ($i = $inicio; $i <= $fim; $i++): ?><li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="<?= $link_paginacao . $i ?>"><?= $i ?></a></li><?php endfor; if ($fim < $paginacao['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?> <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?> <li class="page-item"><a class="page-link" href="<?= $link_paginacao . ($paginacao['pagina_atual'] + 1) ?>">»</a></li> <?php else: ?> <li class="page-item disabled"><span class="page-link">»</span></li> <?php endif; ?> </ul> </nav> </div>
         <?php endif; ?>
    </div>

<?php // Fechamento do <main> aberto pelo layout ?>
<?php
echo getFooterAdmin();
?>

<?php /* Script JS (apenas validação do form de criar) */ ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validação Bootstrap
    const forms = document.querySelectorAll('.needs-validation'); forms.forEach(form => { form.addEventListener('submit', event => { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false); });
    // Foco no campo nome ao mostrar o form de criação por erro
    <?php if ($erro_criar_msg): ?> const collapseElement = document.getElementById('collapseCriarModelo'); if(collapseElement){const bsCollapse = new bootstrap.Collapse(collapseElement,{toggle:false}); bsCollapse.show(); const nomeInput = document.getElementById('nome_modelo'); if(nomeInput){setTimeout(()=>nomeInput.focus(),200);}} <?php endif; ?>
});
</script>