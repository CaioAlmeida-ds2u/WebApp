<?php
// admin/editar_usuario.php

require_once __DIR__ . '/../includes/config.php';        // Config, DB, CSRF Token, Base URL
require_once __DIR__ . '/../includes/layout_admin.php';   // Layout unificado (Header/Footer)
require_once __DIR__ . '/../includes/admin_functions.php'; // Funções admin (getUsuario, atualizarUsuario, dbEmailExisteEmOutroUsuario)
require_once __DIR__ . '/../includes/db.php';           // A função atualizarUsuario pode estar aqui ou em admin_functions

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Obter ID do Usuário a Editar ---
$usuario_id_editar = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$usuario = null; // Inicializa

if (!$usuario_id_editar) {
    $_SESSION['erro'] = "ID de usuário inválido ou não fornecido.";
    header('Location: ' . BASE_URL . 'admin/usuarios.php');
    exit;
}

// --- Carregar Dados do Usuário ---
$usuario = getUsuario($conexao, $usuario_id_editar); // Busca dados do usuário a ser editado

if (!$usuario) {
    $_SESSION['erro'] = "Usuário com ID $usuario_id_editar não encontrado.";
    header('Location: ' . BASE_URL . 'admin/usuarios.php');
    exit;
}

// Variáveis para mensagens e dados do formulário
$erro = '';
$sucesso = ''; // Não usaremos sucesso aqui, pois redirecionamos

// ***** CORREÇÃO: Definir perfis válidos AQUI *****
$perfis_validos = ['admin', 'gestor', 'auditor']; // Defina os perfis permitidos globalmente para esta página

// --- Processar o formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validar CSRF Token
    $id_post = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) || $id_post !== $usuario_id_editar) {
        $_SESSION['erro'] = "Erro de validação ou ID inconsistente. Ação não executada.";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_usuario_csrf_falha', 0, 'Token/ID inválido para ID: ' . $usuario_id_editar, $conexao);
        header('Location: ' . BASE_URL . 'admin/usuarios.php');
        exit;
    }

     // Regenerar token após ação válida
     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // 2. Obter e Validar Dados do Formulário
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $perfil = $_POST['perfil'] ?? ''; // Pega o perfil submetido
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    $errors = [];
    if (empty($nome)) {
        $errors[] = "O nome completo é obrigatório.";
    }
    if (empty($email)) {
        $errors[] = "O e-mail é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Formato de e-mail inválido.";
    } elseif (dbEmailExisteEmOutroUsuario($email, $usuario_id_editar, $conexao)) {
        $errors[] = "Este e-mail já está sendo usado por outro usuário.";
    }

    // Usa a variável $perfis_validos definida ANTES do IF
    if (empty($perfil) || !in_array($perfil, $perfis_validos)) {
        $errors[] = "Perfil selecionado inválido.";
    }

    // Segurança: Não permitir desativar o próprio admin logado
     if ($usuario_id_editar == $_SESSION['usuario_id'] && $ativo == 0) {
         $errors[] = "Você não pode desativar sua própria conta através desta tela.";
     }

    // 3. Atualizar no Banco de Dados se não houver erros
    if (empty($errors)) {
        $atualizacaoOK = atualizarUsuario($conexao, $usuario_id_editar, $nome, $email, $perfil, $ativo);

        if ($atualizacaoOK) {
            $_SESSION['sucesso'] = "Usuário '" . htmlspecialchars($nome) . "' atualizado com sucesso!";
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_usuario_sucesso', 1, "Dados atualizados para ID: $usuario_id_editar", $conexao);

            if ($usuario_id_editar == $_SESSION['usuario_id']) {
                 $_SESSION['nome'] = $nome;
                 // Nota: Normalmente não se permite que um admin mude o PRÓPRIO perfil aqui.
                 // Se permitisse, teria que atualizar $_SESSION['perfil'] = $perfil;
            }

            header('Location: ' . BASE_URL . 'admin/usuarios.php');
            exit;

        } else {
             $erro = "Nenhuma alteração detectada ou erro ao salvar no banco de dados. Tente novamente.";
             dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_usuario_falha_db', 0, "Erro DB ou nenhuma alteração para ID: $usuario_id_editar", $conexao);
             // Repopular o form com os dados enviados para correção
             $usuario['nome'] = $nome;
             $usuario['email'] = $email;
             $usuario['perfil'] = $perfil; // Repopula com o perfil que foi enviado
             $usuario['ativo'] = $ativo;
        }
    } else {
        $erro = "<strong>Foram encontrados os seguintes erros:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_usuario_falha_valid', 0, "Erro de validação ao editar ID: $usuario_id_editar", $conexao);
         // Repopular o form com os dados enviados para correção
         $usuario['nome'] = $nome;
         $usuario['email'] = $email;
         $usuario['perfil'] = $perfil; // Repopula com o perfil que foi enviado (mesmo que inválido, para o usuário ver o que enviou)
         $usuario['ativo'] = $ativo;
    }

}// Fim do if POST

// --- Geração do HTML ---
$title = "ACodITools - Editar Usuário: " . htmlspecialchars($usuario['nome']); // Título dinâmico
echo getHeaderAdmin($title); // Layout unificado
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
         <h1 class="h2">Editar Usuário</h1>
         <a href="<?= BASE_URL ?>admin/usuarios.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar para Lista
         </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7"> <?php /* Coluna ajustada */ ?>
            <div class="card">
                 <div class="card-header">
                    Dados de: <strong><?= htmlspecialchars($usuario['nome']) ?></strong> (ID: <?= htmlspecialchars($usuario['id']) ?>)
                 </div>
                <div class="card-body">

                     <?php if ($erro): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $erro ?> <?php /* Permite HTML */ ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>admin/editar_usuario.php?id=<?= htmlspecialchars($usuario['id']) ?>" id="editUserForm" novalidate>
                         <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                         <input type="hidden" name="usuario_id" value="<?= htmlspecialchars($usuario['id']) ?>"> <?php /* Confirma ID no POST */ ?>

                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                            <div class="invalid-feedback">O nome completo é obrigatório.</div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
                             <div class="invalid-feedback">Por favor, insira um e-mail válido.</div>
                        </div>
                        <div class="mb-3">
                            <label for="perfil" class="form-label">Perfil <span class="text-danger">*</span></label>
                            <select class="form-select" id="perfil" name="perfil" required>
                                <?php foreach ($perfis_validos as $p): ?>
                                    <option value="<?= $p ?>" <?= ($usuario['perfil'] === $p) ? 'selected' : '' ?>>
                                        <?= ucfirst($p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                             <div class="invalid-feedback">Selecione um perfil válido.</div>
                        </div>
                        <div class="mb-3 form-check form-switch"> <?php /* Usando form-switch para melhor visual */ ?>
                            <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" <?= $usuario['ativo'] ? 'checked' : '' ?> <?= ($usuario['id'] == $_SESSION['usuario_id']) ? 'disabled' : '' ?>> <?php /* Desabilita se for o próprio admin */ ?>
                            <label class="form-check-label" for="ativo">Usuário Ativo</label>
                             <?php if ($usuario['id'] == $_SESSION['usuario_id']): ?>
                                <small class="form-text text-muted d-block">Você não pode desativar sua própria conta aqui.</small>
                            <?php endif; ?>
                        </div>

                         <div class="d-flex justify-content-end mt-4">
                            <a href="<?= BASE_URL ?>admin/usuarios.php" class="btn btn-secondary me-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        </div>
                    </form>
                 </div>
            </div>
        </div>
    </div>
</div>

<?php /* Script para ativar validação Bootstrap */ ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editUserForm');
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