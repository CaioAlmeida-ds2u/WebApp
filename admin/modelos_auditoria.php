<?php
// admin/modelos_auditoria.php

require_once __DIR__ . '/../includes/config.php';        // Config, DB, CSRF Token, Base URL
require_once __DIR__ . '/../includes/layout_admin.php';   // Layout unificado
require_once __DIR__ . '/../includes/admin_functions.php'; // Funções de modelos

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens ---
$sucesso_msg = $_SESSION['sucesso'] ?? null;
$erro_msg = $_SESSION['erro'] ?? null;
$erro_criar_msg = $_SESSION['erro_criar_modelo'] ?? null; // Erro específico da criação
unset($_SESSION['sucesso'], $_SESSION['erro'], $_SESSION['erro_criar_modelo']);

// Dados do formulário para repopular em caso de erro na criação
$form_data = $_SESSION['form_data_modelo'] ?? [];
unset($_SESSION['form_data_modelo']);

// --- Processamento de Ações POST (Criar, Ativar, Desativar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['erro'] = "Erro de validação da sessão. Ação não executada.";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'modelo_csrf_falha', 0, 'Token CSRF inválido.', $conexao);
        header('Location: ' . $_SERVER['PHP_SELF']); // Recarrega a própria página
        exit;
    }

    // Regenerar token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $action = $_POST['action'] ?? null;

    // --- Ação: Criar Modelo ---
    if ($action === 'criar_modelo') {
        $nome = trim($_POST['nome_modelo'] ?? '');
        $descricao = trim($_POST['descricao_modelo'] ?? ''); // Permite ser vazio
        $errors = [];

        if (empty($nome)) {
            $errors[] = "O nome do modelo é obrigatório.";
        } // Outras validações podem ser adicionadas (ex: tamanho máximo)

        if (empty($errors)) {
            $resultado = criarModeloAuditoria($conexao, $nome, $descricao ?: null); // Passa null se descricao for vazia
            if ($resultado === true) {
                $_SESSION['sucesso'] = "Modelo '".htmlspecialchars($nome)."' criado com sucesso!";
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_modelo_sucesso', 1, "Modelo criado: $nome", $conexao);
            } else {
                $_SESSION['erro_criar_modelo'] = $resultado; // Mensagem de erro da função (ex: duplicado)
                $_SESSION['form_data_modelo'] = $_POST; // Salva dados para repopular
                dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_modelo_falha_db', 0, "Falha ao criar modelo: $resultado", $conexao);
            }
        } else {
            $_SESSION['erro_criar_modelo'] = "<strong>Erro ao criar:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
            $_SESSION['form_data_modelo'] = $_POST; // Salva dados para repopular
             dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_modelo_falha_valid', 0, "Erro de validação", $conexao);
        }
    }
    // --- Ação: Ativar Modelo ---
    elseif ($action === 'ativar_modelo') {
        $id_ativar = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id_ativar && ativarModeloAuditoria($conexao, $id_ativar)) {
            $_SESSION['sucesso'] = "Modelo ID $id_ativar ativado.";
             dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_modelo', 1, "ID: $id_ativar", $conexao);
        } else {
            $_SESSION['erro'] = "Erro ao ativar o modelo ID $id_ativar.";
             dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_modelo', 0, "ID: $id_ativar", $conexao);
        }
    }
    // --- Ação: Desativar Modelo ---
    elseif ($action === 'desativar_modelo') {
         $id_desativar = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id_desativar && desativarModeloAuditoria($conexao, $id_desativar)) {
            $_SESSION['sucesso'] = "Modelo ID $id_desativar desativado.";
             dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_modelo', 1, "ID: $id_desativar", $conexao);
        } else {
            $_SESSION['erro'] = "Erro ao desativar o modelo ID $id_desativar.";
             dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_modelo', 0, "ID: $id_desativar", $conexao);
        }
    }
    // --- Ação: Excluir Modelo (PLACEHOLDER - Não implementado ainda) ---
    elseif ($action === 'excluir_modelo') {
         $id_excluir = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
         // !! Chamar função de exclusão AQUI quando criada !!
         $_SESSION['erro'] = "A funcionalidade de excluir modelo (ID: $id_excluir) ainda não está implementada.";
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_modelo_tentativa', 0, "Tentativa exclusão ID: $id_excluir", $conexao);
    }

    // Redireciona para evitar reenvio do POST
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// --- Paginação e Filtros para a Lista ---
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$itens_por_pagina = 15; // Ou busque de uma configuração
// Poderia adicionar filtros aqui (status, busca por nome) - Exemplo:
// $filtro_status = $_GET['status'] ?? 'todos';
// $termo_busca = trim($_GET['busca'] ?? '');

// --- Obter Lista de Modelos ---
$modelos_data = getModelosAuditoria($conexao, $pagina_atual, $itens_por_pagina /*, $filtro_status, $termo_busca */);
$lista_modelos = $modelos_data['modelos'];
$paginacao = $modelos_data['paginacao'];


// --- Geração do HTML ---
$title = "ACodITools - Modelos de Auditoria";
echo getHeaderAdmin($title); // Layout unificado
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-clipboard-list me-2"></i>Modelos de Auditoria</h1>
        <?php /* Botão para abrir/focar no form de criação (opcional) */ ?>
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCriarModelo" aria-expanded="<?= $erro_criar_msg ? 'true' : 'false' ?>" aria-controls="collapseCriarModelo">
            <i class="fas fa-plus me-1"></i> Novo Modelo
        </button>
    </div>

    <?php /* Exibir mensagens de sucesso/erro */ ?>
    <?php if ($sucesso_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($sucesso_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($erro_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($erro_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php /* ----- Card para Criar Novo Modelo (Colapsável) ----- */ ?>
    <div class="collapse <?= $erro_criar_msg ? 'show' : '' ?> mb-4" id="collapseCriarModelo">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle me-1"></i> Criar Novo Modelo de Auditoria
            </div>
            <div class="card-body">
                <?php if ($erro_criar_msg): ?>
                    <div class="alert alert-warning" role="alert">
                         <?= $erro_criar_msg ?> <?php /* Permite HTML */ ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="createModelForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="criar_modelo">

                    <div class="mb-3">
                        <label for="nome_modelo" class="form-label">Nome do Modelo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome_modelo" name="nome_modelo" required maxlength="255" value="<?= htmlspecialchars($form_data['nome_modelo'] ?? '') ?>">
                        <div class="invalid-feedback">O nome do modelo é obrigatório (máx. 255 caracteres).</div>
                    </div>
                    <div class="mb-3">
                        <label for="descricao_modelo" class="form-label">Descrição (Opcional)</label>
                        <textarea class="form-control" id="descricao_modelo" name="descricao_modelo" rows="3"><?= htmlspecialchars($form_data['descricao_modelo'] ?? '') ?></textarea>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-toggle="collapse" data-bs-target="#collapseCriarModelo">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Novo Modelo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <?php /* ----- Card para Listar Modelos Existentes ----- */ ?>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list me-1"></i> Modelos Cadastrados
            <?php /* Adicionar filtros aqui depois, se necessário */ ?>
        </div>
        <div class="card-body">
             <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th>Modificado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                         <?php if (empty($lista_modelos)): ?>
                            <tr><td colspan="7" class="text-center text-muted">Nenhum modelo de auditoria cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_modelos as $modelo): ?>
                                <tr>
                                    <td><?= htmlspecialchars($modelo['id']) ?></td>
                                    <td><?= htmlspecialchars($modelo['nome']) ?></td>
                                    <td title="<?= htmlspecialchars($modelo['descricao'] ?? '') ?>">
                                        <?= htmlspecialchars(mb_strimwidth($modelo['descricao'] ?? '', 0, 70, "...")) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $modelo['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $modelo['ativo'] ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars((new DateTime($modelo['data_criacao']))->format('d/m/y H:i')) ?></td>
                                    <td><?= htmlspecialchars((new DateTime($modelo['data_modificacao']))->format('d/m/y H:i')) ?></td>
                                    <td>
                                        <div class="d-flex flex-nowrap">
                                            <?php /* Botão para Editar (link para futura página) */ ?>
                                            <a href="<?= BASE_URL ?>admin/editar_modelo.php?id=<?= $modelo['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Editar Itens do Modelo"><i class="fas fa-edit"></i></a>

                                            <?php /* Botão Ativar/Desativar (Form POST) */ ?>
                                            <?php if ($modelo['ativo']): ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Desativar o modelo <?= htmlspecialchars(addslashes($modelo['nome'])) ?>? Ele não poderá ser usado para novas auditorias.');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                    <input type="hidden" name="action" value="desativar_modelo">
                                                    <input type="hidden" name="id" value="<?= $modelo['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Desativar"><i class="fas fa-toggle-off"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline me-1" onsubmit="return confirm('Ativar o modelo <?= htmlspecialchars(addslashes($modelo['nome'])) ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                    <input type="hidden" name="action" value="ativar_modelo">
                                                    <input type="hidden" name="id" value="<?= $modelo['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Ativar"><i class="fas fa-toggle-on"></i></button>
                                                </form>
                                            <?php endif; ?>

                                             <?php /* Botão Excluir (Form POST - AINDA NÃO IMPLEMENTADO) */ ?>
                                            <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline" onsubmit="return confirm('ATENÇÃO! Excluir o modelo <?= htmlspecialchars(addslashes($modelo['nome'])) ?>? Esta ação é irreversível e só deve ser feita se o modelo não estiver em uso.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="action" value="excluir_modelo">
                                                <input type="hidden" name="id" value="<?= $modelo['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir (Não implementado)"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

             <?php /* Paginação */ ?>
             <?php if ($paginacao['total_paginas'] > 1): ?>
                <nav aria-label="Paginação de Modelos">
                    <ul class="pagination pagination-sm justify-content-center">
                        <?php if ($paginacao['pagina_atual'] > 1): ?>
                            <li class="page-item"><a class="page-link" href="?pagina=<?= $paginacao['pagina_atual'] - 1 ?>">Anterior</a></li>
                        <?php else: ?> <li class="page-item disabled"><span class="page-link">Anterior</span></li> <?php endif; ?>
                        <?php
                        $inicio = max(1, $paginacao['pagina_atual'] - 2); $fim = min($paginacao['total_paginas'], $paginacao['pagina_atual'] + 2);
                        if ($inicio > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        for ($i = $inicio; $i <= $fim; $i++): ?> <li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>"><a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a></li> <?php endfor;
                        if ($fim < $paginacao['total_paginas']) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        ?>
                        <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?>
                            <li class="page-item"><a class="page-link" href="?pagina=<?= $paginacao['pagina_atual'] + 1 ?>">Próxima</a></li>
                        <?php else: ?> <li class="page-item disabled"><span class="page-link">Próxima</span></li> <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        </div> <?php /* Fim card-body */ ?>
    </div> <?php /* Fim card */ ?>

</div> <?php /* Fim container-fluid */ ?>

<?php /* Script para validação Bootstrap do formulário de criação */ ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('createModelForm');
    if (form) {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
              event.preventDefault();
              event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }
});
</script>


<?php
// Inclui o Footer
echo getFooterAdmin();
?>