<?php
// admin/configuracoes_admin.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/layout_admin.php'; // Layout da área administrativa
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/funcoes_upload.php'; // para upload de fotos

// Verifica se o usuário está logado e é um administrador
if (!usuarioEstaLogado() || $_SESSION['perfil'] !== 'admin') {
    redirecionarParaLogin($conexao); // Usa a função que agora está em config.php
}

$erro = '';
$sucesso = '';
$usuario_id = $_SESSION['usuario_id'];

// Carrega os dados atuais do usuário
$dadosUsuario = getUsuario($conexao, $usuario_id); // Correção: Usar getUsuario

if (!$dadosUsuario) {
    $erro = "Usuário não encontrado.";
}

// --- Processamento do Formulário ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarNovaSenha = $_POST['confirmar_nova_senha'] ?? '';
    $foto = $_FILES['foto'] ?? null; // Obtém o arquivo (ou null se nenhum arquivo for enviado)

    // Validação
    $errors = [];

    if (empty($nome)) {
        $errors[] = "O campo Nome é obrigatório.";
    }
    if (empty($email)) {
        $errors[] = "O campo E-mail é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Por favor, insira um endereço de e-mail válido.";
    }

    //Verifica se o email já existe em outro usuário
    if (dbEmailExisteEmOutroUsuario($email, $usuario_id, $conexao)) {
        $errors[] = "Este e-mail já está sendo usado por outro usuário.";
    }

    // Se nova senha foi fornecida, validar
    if (!empty($novaSenha)) {
        // ... (validação da senha - OK) ...
         if (empty($senhaAtual)) {
            $errors[] = "Para alterar a senha, você deve digitar a senha atual.";
        } elseif (!dbVerificaSenha($usuario_id, $senhaAtual, $conexao)) {
            $errors[] = "Senha atual incorreta.";
        } elseif ($novaSenha !== $confirmarNovaSenha) {
            $errors[] = "A nova senha e a confirmação não coincidem.";
        } elseif (strlen($novaSenha) < 8) {
            $errors[] = "A nova senha deve ter pelo menos 8 caracteres.";
        }
    }

    // Validação da foto (se uma foto foi enviada)
    if ($foto && $foto['error'] !== UPLOAD_ERR_NO_FILE) {
        $foto_erro = validarImagem($foto);
        if ($foto_erro) {
            $errors[] = $foto_erro;
        }
    }

    if (empty($errors)) {
        // 1. Atualiza nome, e-mail e senha (se necessário)
        $atualizacaoOK = dbAtualizarUsuario($usuario_id, $nome, $email, empty($novaSenha) ? null : $novaSenha, $conexao);


        // 2. Processar o upload da foto (se houver)
        if ($foto && $foto['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = processarUploadFoto($foto, $usuario_id, $conexao);

            if (!$upload['success']) {
                $erro = $upload['message'];
            } else {
                // Se o upload da foto foi bem-sucedido:
                $fotoNova = $upload['caminho'];

                // Excluir a foto antiga (se existir)
                if (!empty($dadosUsuario['foto'])) {
                     $caminhoFotoAntiga = __DIR__ . '/../uploads/fotos/' . $dadosUsuario['foto']; // Caminho ABSOLUTO para exclusão
                     if (file_exists($caminhoFotoAntiga)) {  //Verifica se realmente existe.
                        unlink($caminhoFotoAntiga);
                     }
                }
            }
        }

        // 3. Recarregar os dados do usuário *APÓS* todas as atualizações
         $dadosUsuario = getUsuario($conexao, $usuario_id); // RECARREGA

        if(empty($erro)){ //Verifica se existe erros.
            if ($atualizacaoOK || ($foto && $foto['error'] !== UPLOAD_ERR_NO_FILE) ) {  //Verifica se foi realizado alguma alteração.
                $sucesso = "Dados atualizados com sucesso!";
                // Atualiza o nome na sessão, se tiver sido modificado
                $_SESSION['nome'] = $nome;
                if (isset($fotoNova)) {
                  $_SESSION['foto'] = $fotoNova; // Atualiza a foto na sessão
                }

            } else {
                $erro = "Erro ao atualizar os dados. Por favor, tente novamente.";
                  dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'atualizacao_perfil_erro', 0, 'Falha na atualização do perfil: ' . $erro, $conexao);
            }
        }
    } else {
         $erro = implode("<br>", $errors);
    }
}

// --- Geração do HTML ---
$title = "Configurações do Perfil";
echo getHeaderAdmin($title);
?>

<div class="container mt-5">
    <h1>Configurações do Perfil</h1>

    <?php if ($erro): ?>
        <div class="alert alert-danger" role="alert">
            <?= $erro ?>
        </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alert alert-success" role="alert">
            <?= htmlspecialchars($sucesso) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="configuracoes_admin.php" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="nome" class="form-label">Nome Completo</label>
            <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($dadosUsuario['nome']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($dadosUsuario['email']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="foto" class="form-label">Foto de Perfil</label>
            <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
            <div id="preview-container" class="mt-2">
                <?php if ($dadosUsuario['foto']): ?>
                    <img src="<?= BASE_URL . 'uploads/fotos/' . $dadosUsuario['foto'] ?>" alt="Foto Atual" style="max-width: 200px; max-height: 200px;" id="current-foto">
                <?php else: ?>
                    <img src="<?= BASE_URL . 'assets/img/default_profile.png' ?>" alt="Foto Padrão" style="max-width: 200px; max-height: 200px;" id="current-foto">
                <?php endif; ?>
                <img id="preview" src="#" alt="Pré-visualização" style="max-width: 200px; max-height: 200px; display: none;">
            </div>
        </div>

        <div class="mb-3">
            <label for="senha_atual" class="form-label">Senha Atual</label>
            <input type="password" class="form-control" id="senha_atual" name="senha_atual" autocomplete="current-password">
            <small class="form-text text-muted">Digite a senha atual para confirmar alterações ou alterar a senha.</small>
        </div>
        <div class="mb-3">
            <label for="nova_senha" class="form-label">Nova Senha</label>
            <input type="password" class="form-control" id="nova_senha" name="nova_senha" autocomplete="new-password">
             <small class="form-text text-muted">Deixe em branco para manter a senha atual.</small>
        </div>
        <div class="mb-3">
            <label for="confirmar_nova_senha" class="form-label">Confirmar Nova Senha</label>
            <input type="password" class="form-control" id="confirmar_nova_senha" name="confirmar_nova_senha" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
         <a href="dashboard_admin.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
<script>
    const inputFoto = document.getElementById('foto');
    const preview = document.getElementById('preview');
    const currentFoto = document.getElementById('current-foto');

    inputFoto.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();

            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block'; // Mostra a pré-visualização
                if (currentFoto) {
                    currentFoto.style.display = 'none'; // Oculta a foto atual, se houver
                }
            }

            reader.readAsDataURL(this.files[0]); // Lê o arquivo como uma URL de dados (base64)
        }
    });
</script>
<?php echo getFooterAdmin(); ?>