<?php
// admin/requisitos/editar_requisito.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_admin.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Obter ID do Requisito a Editar ---
$requisito_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$requisito = null;

if (!$requisito_id) {
    $_SESSION['erro'] = "ID de requisito inválido ou não fornecido.";
    header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php');
    exit;
}

// --- Carregar Dados do Requisito ---
$requisito = getRequisitoAuditoria($conexao, $requisito_id);

if (!$requisito) {
    $_SESSION['erro'] = "Requisito com ID $requisito_id não encontrado.";
    header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php');
    exit;
}

// Variáveis para mensagens e dados do formulário
$erro = '';
$sucesso = ''; // Usaremos sessão para sucesso ao redirecionar

// --- Processar o formulário de Edição (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar CSRF Token e ID
    $id_post = filter_input(INPUT_POST, 'requisito_id', FILTER_VALIDATE_INT);
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) || $id_post !== $requisito_id) {
        $_SESSION['erro'] = "Erro de validação ou ID inconsistente. Ação não executada.";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_requisito_csrf_falha', 0, "Token/ID inválido para ID: $requisito_id", $conexao);
        header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php');
        exit;
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenera token

    // 2. Obter e Validar Dados do Formulário
    $dados_form = [
        'codigo' => trim($_POST['codigo'] ?? ''),
        'nome' => trim($_POST['nome'] ?? ''),
        'descricao' => trim($_POST['descricao'] ?? ''),
        'categoria' => trim($_POST['categoria'] ?? ''),
        'norma_referencia' => trim($_POST['norma_referencia'] ?? ''),
        'ativo' => isset($_POST['ativo']) ? 1 : 0
    ];

    // 3. Chamar função de atualização
    $resultado = atualizarRequisitoAuditoria($conexao, $requisito_id, $dados_form, $_SESSION['usuario_id']);

    if ($resultado === true) {
        $_SESSION['sucesso'] = "Requisito '".htmlspecialchars($dados_form['nome'])."' (ID: $requisito_id) atualizado com sucesso!";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_requisito_sucesso', 1, "ID: $requisito_id", $conexao);
        header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); // Volta para lista
        exit;
    } else {
        // Erro retornado pela função (ex: código duplicado, DB error)
        $erro = $resultado; // Mostra o erro na página
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_requisito_falha', 0, "ID: $requisito_id, Erro: $resultado", $conexao);
        // Mantém os dados POSTADOS no array $requisito para repopular o form com as tentativas do usuário
        $requisito = array_merge($requisito, $dados_form);
    }

} // Fim do if POST

// --- Obter listas para dropdowns (datalist) ---
$categorias_filtro = getCategoriasRequisitos($conexao);
$normas_filtro = getNormasRequisitos($conexao);

// --- Geração do HTML ---
$title = "Editar Requisito: " . htmlspecialchars($requisito['nome']);
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-edit me-2"></i>Editar Requisito</h1>
        <a href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar para Lista
        </a>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
             <i class="fas fa-exclamation-triangle me-2"></i><?= $erro ?> <?php /* Erro já vem formatado se for validação */ ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
         <div class="card-header bg-light">
             Editando Requisito ID: <strong><?= htmlspecialchars($requisito['id']) ?></strong>
         </div>
         <div class="card-body">
             <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" id="editRequisitoForm" class="needs-validation" novalidate>
                 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                 <input type="hidden" name="requisito_id" value="<?= htmlspecialchars($requisito['id']) ?>"> <?php /* Confirma ID */ ?>

                 <div class="row g-3">
                    <div class="col-md-3">
                        <label for="codigo" class="form-label">Código (Opcional)</label>
                        <input type="text" class="form-control form-control-sm" id="codigo" name="codigo" value="<?= htmlspecialchars($requisito['codigo'] ?? '') ?>" maxlength="50">
                        <small class="form-text text-muted">Deve ser único se preenchido.</small>
                    </div>
                    <div class="col-md-9">
                        <label for="nome" class="form-label">Nome/Título Curto <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="nome" name="nome" required maxlength="255" value="<?= htmlspecialchars($requisito['nome'] ?? '') ?>">
                        <div class="invalid-feedback">O nome/título é obrigatório.</div>
                    </div>
                    <div class="col-12">
                        <label for="descricao" class="form-label">Descrição Detalhada / Pergunta <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="descricao" name="descricao" rows="4" required><?= htmlspecialchars($requisito['descricao'] ?? '') ?></textarea>
                         <div class="invalid-feedback">A descrição detalhada é obrigatória.</div>
                    </div>
                    <div class="col-md-6">
                         <label for="categoria" class="form-label">Categoria</label>
                         <input type="text" class="form-control form-control-sm" id="categoria" name="categoria" value="<?= htmlspecialchars($requisito['categoria'] ?? '') ?>" maxlength="100" list="categoriasExistentes">
                         <datalist id="categoriasExistentes">
                             <?php foreach ($categorias_filtro as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?>
                         </datalist>
                    </div>
                    <div class="col-md-6">
                         <label for="norma_referencia" class="form-label">Norma de Referência</label>
                         <input type="text" class="form-control form-control-sm" id="norma_referencia" name="norma_referencia" value="<?= htmlspecialchars($requisito['norma_referencia'] ?? '') ?>" maxlength="100" list="normasExistentes">
                           <datalist id="normasExistentes">
                             <?php foreach ($normas_filtro as $norma): ?><option value="<?= htmlspecialchars($norma) ?>"><?php endforeach; ?>
                         </datalist>
                    </div>
                     <div class="col-12">
                         <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?= !empty($requisito['ativo']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Requisito Ativo</label>
                        </div>
                     </div>
                 </div>
                 <hr>
                 <div class="d-flex justify-content-end">
                     <a href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php" class="btn btn-secondary btn-sm me-2">Cancelar</a>
                     <button type="submit" class="btn btn-primary btn-sm">Salvar Alterações</button>
                 </div>
             </form>
         </div>
    </div>
</div>

<?php /* Script JS para validação */ ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editRequisitoForm');
    if (form) {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
            form.classList.add('was-validated');
        }, false);
    }
});
</script>

<?php
echo getFooterAdmin();
?>