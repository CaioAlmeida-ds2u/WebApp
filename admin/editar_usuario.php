<?php
// admin/editar_usuario.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Contém getUsuario, getEmpresasAtivas, dbEmailExisteEmOutroUsuario, etc.
require_once __DIR__ . '/../includes/db.php';             // Pode conter atualizarUsuario

// Proteção e Verificação de Perfil
protegerPagina($conexao); // Já deve checar sessão iniciada e redirecionar se não logado
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Obter ID do Usuário a Editar ---
$usuario_id_editar = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$usuario_id_editar) {
    definir_flash_message('erro', 'ID de usuário inválido ou não fornecido.');
    header('Location: ' . BASE_URL . 'admin/usuarios.php');
    exit;
}

// --- Carregar Dados do Usuário ---
$usuario = getUsuario($conexao, $usuario_id_editar); // **Certifique-se que retorna empresa_id**
if (!$usuario) {
    definir_flash_message('erro', "Usuário com ID $usuario_id_editar não encontrado.");
    header('Location: ' . BASE_URL . 'admin/usuarios.php');
    exit;
}

// ***** NOVO: Carregar Lista de Empresas Ativas *****
// Assumindo que existe uma função getEmpresasAtivas em admin_functions.php ou db.php
$empresas = getEmpresasAtivas($conexao); // Retorna um array de empresas [ ['id' => x, 'nome' => 'Nome'], ... ]

// Variáveis para mensagens e dados do formulário
$erro = '';
$sucesso = ''; // Não usamos flash message de sucesso? (você está usando sessão)

// Definição de perfis válidos (mantido)
$perfis_validos = ['admin', 'gestor', 'auditor', 'usuario'];

// --- Processar o formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validar CSRF Token (mantido)
    $id_post = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
    if (!isset($_POST['csrf_token']) || !validar_csrf_token($_POST['csrf_token']) || $id_post !== $usuario_id_editar) {
        definir_flash_message('erro', "Erro de validação ou ID inconsistente. Ação não executada.");
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_usuario_csrf_falha', 0, 'Token/ID inválido para ID: ' . $usuario_id_editar, $conexao);
        header('Location: ' . BASE_URL . 'admin/usuarios.php');
        exit;
    }
    // Regenerar token (mantido)
    // $_SESSION['csrf_token'] = gerar_csrf_token(); // Se usar token único por requisição

    // 2. Obter e Validar Dados do Formulário
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $perfil = $_POST['perfil'] ?? '';
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    // ***** NOVO: Obter Empresa ID *****
    $empresa_id_selecionada = filter_input(INPUT_POST, 'empresa_id', FILTER_VALIDATE_INT); // Pega como INT
    if ($empresa_id_selecionada === false) { // Se a conversão falhar (não for número ou estiver vazio e não for zero)
        $empresa_id_selecionada = null; // Trata como nulo se a entrada não for um inteiro válido
    }

    $errors = [];
    // Validações de Nome, Email, Perfil (mantidas)
    if (empty($nome)) { $errors[] = "O nome completo é obrigatório."; }
    if (empty($email)) { $errors[] = "O e-mail é obrigatório."; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Formato de e-mail inválido."; }
    elseif (dbEmailExisteEmOutroUsuario($email, $usuario_id_editar, $conexao)) { $errors[] = "Este e-mail já está sendo usado por outro usuário."; }
    if (empty($perfil) || !in_array($perfil, $perfis_validos)) { $errors[] = "Perfil selecionado inválido."; }
    if ($usuario_id_editar == $_SESSION['usuario_id'] && $ativo == 0) { $errors[] = "Você não pode desativar sua própria conta."; }

    // ***** NOVO: Validação da Empresa ID *****
    $empresaObrigatoria = ($perfil === 'gestor' || $perfil === 'auditor' || $perfil === 'usuario'); // Admin pode não ter empresa
    if ($empresaObrigatoria && ($empresa_id_selecionada === null || $empresa_id_selecionada <= 0)) {
        $errors[] = "Selecione uma empresa para este perfil de usuário.";
    } elseif ($empresa_id_selecionada !== null) {
        // Verifica se a empresa selecionada realmente existe na lista de empresas carregada
        $empresaValida = false;
        foreach ($empresas as $emp) {
            if ($emp['id'] == $empresa_id_selecionada) {
                $empresaValida = true;
                break;
            }
        }
        if (!$empresaValida) {
            $errors[] = "A empresa selecionada é inválida.";
             $empresa_id_selecionada = null; // Reseta se inválida para não tentar salvar
        }
    } elseif (!$empresaObrigatoria && $empresa_id_selecionada !== null && $empresa_id_selecionada <= 0) {
        // Se for admin e escolheu "nenhuma" ou inválido, força null
        $empresa_id_selecionada = null;
    }

    // 3. Atualizar no Banco de Dados se não houver erros
    if (empty($errors)) {
        // **** ATENÇÃO: Modificar a função 'atualizarUsuario' para aceitar $empresa_id_selecionada ****
        $atualizacaoOK = atualizarUsuario(
            $conexao,
            $usuario_id_editar,
            $nome,
            $email,
            $perfil,
            $ativo,
            $empresa_id_selecionada // <<< NOVO PARÂMETRO
        );

        if ($atualizacaoOK) {
            definir_flash_message('sucesso', "Usuário '" . htmlspecialchars($nome) . "' atualizado com sucesso!");
            dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_usuario_sucesso', 1, "Dados atualizados para ID: $usuario_id_editar", $conexao);

            // Atualizar sessão se o usuário logado for o editado
            if ($usuario_id_editar == $_SESSION['usuario_id']) {
                 $_SESSION['nome'] = $nome;
                 $_SESSION['email'] = $email; // Pode ter mudado
                 // Normalmente não se muda o próprio perfil ou empresa via edição de terceiros,
                 // mas se fosse permitido:
                 // $_SESSION['perfil'] = $perfil;
                 // $_SESSION['empresa_id'] = $empresa_id_selecionada;
                 // Buscaria o nome da empresa e atualizaria $_SESSION['empresa_nome'];
            }

            header('Location: ' . BASE_URL . 'admin/usuarios.php');
            exit;
        } else {
             // (Tratamento de erro DB mantido)
             $erro = "Nenhuma alteração detectada ou erro ao salvar no banco de dados.";
             dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_usuario_falha_db', 0, "Erro DB ou nenhuma alteração para ID: $usuario_id_editar", $conexao);
             // Repopular form
             $usuario['nome'] = $nome;
             $usuario['email'] = $email;
             $usuario['perfil'] = $perfil;
             $usuario['ativo'] = $ativo;
             $usuario['empresa_id'] = $empresa_id_selecionada; // Repopular empresa selecionada
        }
    } else {
         // (Tratamento de erro de validação mantido)
        $erro = "<strong>Foram encontrados os seguintes erros:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'editar_usuario_falha_valid', 0, "Erro de validação ao editar ID: $usuario_id_editar", $conexao);
         // Repopular form
         $usuario['nome'] = $nome;
         $usuario['email'] = $email;
         $usuario['perfil'] = $perfil;
         $usuario['ativo'] = $ativo;
         $usuario['empresa_id'] = $empresa_id_selecionada; // Repopular empresa selecionada (mesmo que inválida)
    }
}// Fim do if POST

// --- Geração do HTML ---
$title = "ACodITools - Editar Usuário: " . htmlspecialchars($usuario['nome']);
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
         <h1 class="h2">Editar Usuário</h1>
         <a href="<?= BASE_URL ?>admin/usuarios.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar
         </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            <div class="card shadow-sm"> <?php /* Sombra leve */ ?>
                 <div class="card-header bg-light"> <?php /* Header claro */ ?>
                    Dados de: <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
                 </div>
                <div class="card-body p-4"> <?php /* Mais padding */ ?>

                     <?php if ($erro): ?>
                        <div class="alert alert-danger d-flex align-items-center alert-dismissible fade show" role="alert"> <?php /* Ícone e permite HTML */ ?>
                           <i class="fas fa-exclamation-triangle flex-shrink-0 me-2"></i>
                           <div><?= $erro ?></div>
                           <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>admin/editar_usuario.php?id=<?= htmlspecialchars($usuario['id']) ?>" id="editUserForm" novalidate>
                         <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerar_csrf_token()) ?>"> <?php /* Sempre gerar novo token no load */ ?>
                         <input type="hidden" name="usuario_id" value="<?= htmlspecialchars($usuario['id']) ?>">

                        <div class="row g-3"> <?php /* Usa row e col para melhor alinhamento se precisar */ ?>
                             <div class="col-12">
                                <label for="nome" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" required>
                                <div class="invalid-feedback">O nome completo é obrigatório.</div>
                            </div>
                            <div class="col-12">
                                <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" required>
                                <div class="invalid-feedback">Por favor, insira um e-mail válido.</div>
                            </div>
                            <div class="col-md-6"> <?php /* Metade da largura */ ?>
                                <label for="perfil" class="form-label">Perfil <span class="text-danger">*</span></label>
                                <select class="form-select" id="perfil" name="perfil" required>
                                    <option value="" disabled <?= empty($usuario['perfil']) ? 'selected' : ''?>>Selecione...</option>
                                    <?php foreach ($perfis_validos as $p): ?>
                                        <option value="<?= $p ?>" <?= ($usuario['perfil'] === $p) ? 'selected' : '' ?>>
                                            <?= ucfirst($p) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Selecione um perfil válido.</div>
                            </div>

                            <?php // ***** NOVO: Campo Select para Empresa ***** ?>
                             <div class="col-md-6"> <?php /* Metade da largura */ ?>
                                <label for="empresa_id" class="form-label">Empresa <?php /* O asterisco pode ser condicional */ ?>
                                    <span class="text-danger" id="empresa_obrigatoria_asterisco" style="display: none;">*</span>
                                 </label>
                                <select class="form-select" id="empresa_id" name="empresa_id">
                                     <option value="">-- Nenhuma --</option> <?php /* Opção para admin sem empresa */ ?>
                                    <?php if (empty($empresas)): ?>
                                        <option value="" disabled>Nenhuma empresa cadastrada</option>
                                    <?php else: ?>
                                        <?php foreach ($empresas as $emp): ?>
                                            <option value="<?= $emp['id'] ?>" <?= (isset($usuario['empresa_id']) && $usuario['empresa_id'] == $emp['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($emp['nome']) ?> (ID: <?= $emp['id'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                 <div class="invalid-feedback" id="empresa_error_msg">Selecione uma empresa válida para este perfil.</div>
                            </div>

                             <div class="col-12 mt-4"> <?php /* Coluna inteira para o switch */ ?>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?= ($usuario['ativo'] ?? 0) ? 'checked' : '' ?> <?= ($usuario['id'] == $_SESSION['usuario_id']) ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="ativo">Usuário Ativo</label>
                                    <?php if ($usuario['id'] == $_SESSION['usuario_id']): ?>
                                        <small class="form-text text-muted d-block">Você não pode desativar sua própria conta aqui.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                         <hr class="my-4"> <?php /* Divisor visual */ ?>

                         <div class="d-flex justify-content-end">
                            <a href="<?= BASE_URL ?>admin/usuarios.php" class="btn btn-secondary me-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                               <i class="fas fa-save me-1"></i> Salvar Alterações
                            </button>
                        </div>
                    </form>
                 </div>
            </div>
        </div>
    </div>
</div>

<?php // Script para validação Bootstrap E lógica da empresa obrigatória ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editUserForm');
    const perfilSelect = document.getElementById('perfil');
    const empresaSelect = document.getElementById('empresa_id');
    const empresaAsterisco = document.getElementById('empresa_obrigatoria_asterisco');
    const empresaFeedback = document.getElementById('empresa_error_msg'); // Pega a div do feedback

    // Função para verificar se empresa é obrigatória e ajustar UI
    function checkEmpresaObrigatoria() {
        const perfilSelecionado = perfilSelect.value;
        // Define quais perfis EXIGEM empresa
        const perfisComEmpresa = ['gestor', 'auditor', 'usuario'];
        const ehObrigatorio = perfisComEmpresa.includes(perfilSelecionado);

        if (ehObrigatorio) {
            empresaAsterisco.style.display = 'inline'; // Mostra asterisco
            empresaSelect.required = true;             // Torna o select obrigatório HTML5
        } else {
            empresaAsterisco.style.display = 'none';   // Esconde asterisco
            empresaSelect.required = false;            // NÃO é obrigatório
            empresaSelect.classList.remove('is-invalid'); // Remove erro visual se não for mais obrigatório
            empresaFeedback.textContent = 'Selecione uma empresa válida para este perfil.'; // Reset msg erro
        }
    }

    // Verifica no carregamento da página
    if (perfilSelect && empresaSelect) {
        checkEmpresaObrigatoria();

        // Adiciona listener para quando o perfil mudar
        perfilSelect.addEventListener('change', checkEmpresaObrigatoria);
    }

    // Ativa validação Bootstrap no submit
    if (form) {
        form.addEventListener('submit', event => {
            // Validação extra para o campo empresa ANTES de submeter
             checkEmpresaObrigatoria(); // Garante que 'required' está correto

            // Se a empresa é obrigatória e nenhuma foi selecionada
            if (empresaSelect.required && !empresaSelect.value) {
                 empresaSelect.classList.add('is-invalid'); // Mostra erro visual
                 empresaFeedback.textContent = 'Uma empresa é obrigatória para este perfil.'; // Msg específica
                event.preventDefault(); // Impede envio
                event.stopPropagation();
            } else {
                 empresaSelect.classList.remove('is-invalid'); // Limpa erro se estiver OK
                 empresaFeedback.textContent = 'Selecione uma empresa válida para este perfil.'; // Reset msg erro
             }

            // Validação padrão do Bootstrap
            if (!form.checkValidity()) {
              event.preventDefault();
              event.stopPropagation();
            }
            form.classList.add('was-validated');

        }, false);

         // Limpa erro da empresa se o usuário selecionar uma opção válida (mesmo "Nenhuma")
         if(empresaSelect) {
            empresaSelect.addEventListener('change', () => {
                if (empresaSelect.value || !empresaSelect.required) {
                     empresaSelect.classList.remove('is-invalid');
                     empresaFeedback.textContent = 'Selecione uma empresa válida para este perfil.';
                }
            });
        }
    }
});
</script>

<?php
echo getFooterAdmin();
?>