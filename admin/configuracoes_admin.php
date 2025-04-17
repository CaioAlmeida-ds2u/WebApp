<?php
// admin/configuracoes_admin.php

require_once __DIR__ . '/../includes/config.php';        // Config, DB, CSRF Token, Base URL
require_once __DIR__ . '/../includes/layout_admin.php';   // Layout unificado (Header/Footer)
require_once __DIR__ . '/../includes/admin_functions.php'; // Funções admin (getUsuario, dbEmailExisteEmOutroUsuario, dbVerificaSenha)
require_once __DIR__ . '/../includes/funcoes_upload.php'; // Funções de upload (validarImagem, processarUploadFoto)
require_once __DIR__ . '/../includes/db.php';           // Funções DB (dbAtualizarUsuario)

// Proteção da página: Verifica se está logado
protegerPagina($conexao);

// Verificação de Perfil: Apenas Admin pode acessar (ou o próprio usuário?)
// Se qualquer usuário puder acessar suas configs, a lógica de perfil muda.
// Por hora, mantendo apenas para Admin, conforme o nome da pasta.
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$erro = '';
$sucesso = '';
$usuario_id = $_SESSION['usuario_id']; // ID do admin logado

// --- Carregar Dados Atuais do Usuário ---
// Usar getUsuario que já temos e retorna um array ou null
$dadosUsuario = getUsuario($conexao, $usuario_id);

if (!$dadosUsuario) {
    // Se não encontrar o usuário logado, algo está muito errado.
    error_log("Erro crítico: Não foi possível carregar dados para o usuário ID $usuario_id em configuracoes_admin.php");
    // Redirecionar para logout ou exibir erro fatal
    header('Location: ' . BASE_URL . 'logout.php?erro=dados_usuario_invalidos');
    exit;
}

// --- Processamento do Formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erro = "Erro de validação da sessão. Por favor, tente recarregar a página e enviar novamente.";
        dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'config_admin_csrf_falha', 0, 'Token CSRF inválido ou ausente.', $conexao);
    } else {
        // Token válido, processar dados
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senhaAtual = $_POST['senha_atual'] ?? ''; // Não usar trim
        $novaSenha = $_POST['nova_senha'] ?? '';   // Não usar trim
        $confirmarNovaSenha = $_POST['confirmar_nova_senha'] ?? ''; // Não usar trim
        $foto = $_FILES['foto'] ?? null; // Obtém dados do arquivo

        // 2. Validação dos Campos
        $errors = [];
        if (empty($nome)) {
            $errors[] = "O campo Nome Completo é obrigatório.";
        }
        if (empty($email)) {
            $errors[] = "O campo E-mail é obrigatório.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Formato de e-mail inválido.";
        } elseif (dbEmailExisteEmOutroUsuario($email, $usuario_id, $conexao)) {
            // Verifica se o email já existe em OUTRO usuário
            $errors[] = "Este e-mail já está sendo usado por outro usuário.";
        }

        // Validação de senha (APENAS se uma nova senha foi fornecida)
        $senhaParaAtualizar = null; // Iniciamos como null
        if (!empty($novaSenha)) {
            if (empty($senhaAtual)) {
                $errors[] = "Para alterar a senha, você deve fornecer a senha atual.";
            } elseif (!dbVerificaSenha($usuario_id, $senhaAtual, $conexao)) { // Verifica a senha atual
                $errors[] = "Senha atual incorreta.";
            } elseif (strlen($novaSenha) < 8) { // Valida comprimento da nova senha
                 // Poderia adicionar validação de complexidade aqui (regex)
                $errors[] = "A nova senha deve ter pelo menos 8 caracteres.";
            } elseif ($novaSenha !== $confirmarNovaSenha) {
                $errors[] = "A nova senha e a confirmação não coincidem.";
            } else {
                // Se todas as validações de senha passaram, preparamos a nova senha
                $senhaParaAtualizar = $novaSenha;
            }
        } elseif (!empty($senhaAtual) && empty($novaSenha)) {
             // Caso o usuário digite a senha atual mas não a nova, apenas para confirmar outras alterações.
             // Verificar se a senha atual está correta é uma boa prática de segurança para *qualquer* alteração.
             if (!dbVerificaSenha($usuario_id, $senhaAtual, $conexao)) {
                 $errors[] = "Senha atual incorreta. Necessária para salvar alterações.";
             }
        } elseif (empty($senhaAtual) && empty($novaSenha) && $foto['error'] === UPLOAD_ERR_NO_FILE && ($nome === $dadosUsuario['nome'] && $email === $dadosUsuario['email'])) {
            // Nenhuma alteração real detectada (nem nome, nem email, nem senha, nem foto)
            // Poderíamos dar um aviso ou simplesmente não fazer nada.
            // $errors[] = "Nenhuma alteração detectada.";
            // Ou redirecionar sem mensagem, ou com msg "Nenhuma alteração feita"
             $sucesso = "Nenhuma alteração foi feita."; // Define sucesso, mas nada será salvo.
             $nenhuma_alteracao = true;
        } elseif(empty($senhaAtual) && !empty($novaSenha)) {
             // Se tentou definir nova senha sem a atual
             $errors[] = "Digite a senha atual para definir uma nova senha.";
        } elseif (empty($senhaAtual)) {
            // Se fez outras alterações (nome, email, foto) sem digitar a senha atual
            $errors[] = "Digite sua senha atual para confirmar as alterações.";
        }


        // Validação da foto (se um arquivo foi enviado)
        $fotoAntiga = $dadosUsuario['foto']; // Guarda o nome da foto antiga
        $caminhoFotoNova = null; // Caminho relativo da nova foto, se houver upload
        if ($foto && $foto['error'] !== UPLOAD_ERR_NO_FILE) {
            $foto_erro = validarImagem($foto); // Função de funcoes_upload.php
            if ($foto_erro) {
                $errors[] = $foto_erro;
            }
             // A função processarUploadFoto fará a validação novamente, moverá e atualizará DB
        }

        // 3. Atualizar Dados se não houver erros E houver alterações
        if (empty($errors) && !isset($nenhuma_alteracao)) {

            // Tenta processar o upload da foto PRIMEIRO (se houver)
            // Assim, se o upload falhar, não atualizamos nome/email/senha desnecessariamente.
            $upload_success = true; // Assume sucesso se não houver upload
            if ($foto && $foto['error'] !== UPLOAD_ERR_NO_FILE) {
                 // !! ALERTA !! processarUploadFoto precisa ser seguro (verificação MIME no servidor)
                $uploadResult = processarUploadFoto($foto, $usuario_id, $conexao);
                if (!$uploadResult['success']) {
                    $errors[] = "Erro no upload da foto: " . $uploadResult['message'];
                    $upload_success = false;
                     dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'config_admin_upload_falha', 0, 'Falha no upload: ' . $uploadResult['message'], $conexao);
                } else {
                    $caminhoFotoNova = 'uploads/fotos/' . $uploadResult['caminho']; // Guarda o caminho relativo
                     dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'config_admin_upload_sucesso', 1, 'Foto de perfil atualizada.', $conexao);
                    // A função processarUploadFoto já deve ter atualizado a SESSION['foto'] e excluído a antiga
                }
            }

            // Se o upload foi bem-sucedido (ou não houve upload), atualiza os outros dados
            $dbUpdateOK = false;
            if ($upload_success) {
                // Verifica se houve mudança no nome ou email, ou se uma nova senha foi definida
                 if ($nome !== $dadosUsuario['nome'] || $email !== $dadosUsuario['email'] || $senhaParaAtualizar !== null) {

                    $dbUpdateOK = dbAtualizarUsuario($usuario_id, $nome, $email, $senhaParaAtualizar, $conexao);

                    if ($dbUpdateOK) {
                        // Atualiza nome na sessão se mudou
                        if ($nome !== $dadosUsuario['nome']) {
                             $_SESSION['nome'] = $nome;
                        }
                        $log_details = "Perfil atualizado com sucesso.";
                        if ($senhaParaAtualizar !== null) $log_details .= " Senha alterada.";
                         dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'config_admin_update_sucesso', 1, $log_details, $conexao);
                    } else {
                         $errors[] = "Erro ao atualizar os dados no banco. Tente novamente.";
                          dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'config_admin_update_falha_db', 0, 'Erro DB ao atualizar perfil.', $conexao);
                    }
                } else {
                    // Se só houve upload de foto (e já foi sucesso), consideramos a operação OK
                     $dbUpdateOK = $upload_success;
                }
            }

            // Define mensagem final de sucesso ou erro
            if (empty($errors) && ($dbUpdateOK || $upload_success)) {
                 $sucesso = "Dados atualizados com sucesso!";
                 // Recarrega os dados do usuário para exibir o formulário atualizado
                 $dadosUsuario = getUsuario($conexao, $usuario_id);
            }
            // Se $errors não estiver vazio (por falha no upload ou DB update), o erro será montado abaixo.

        } // Fim do if (empty($errors))

        // Monta a mensagem de erro final, se houver
        if (!empty($errors)) {
             $erro = "<strong>Foram encontrados os seguintes erros:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
             // Não logamos novamente aqui, os logs foram feitos nos pontos de falha.
        }

    } // Fim do else da validação CSRF
} // Fim do if POST


// --- Geração do HTML ---
$title = "Configurações do Perfil";
echo getHeaderAdmin($title); // Abre HTML, head, body, navbar, sidebar, main
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Configurações do Perfil</h1>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $erro ?> <?php /* Permite HTML na mensagem de erro (<ul><li>) */ ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
         <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($sucesso) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>admin/configuracoes_admin.php" enctype="multipart/form-data" id="configForm" novalidate>
                 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                 <div class="row">
                    <div class="col-md-8"> <?php /* Campos de texto */ ?>
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($dadosUsuario['nome']) ?>" required>
                            <div class="invalid-feedback">O nome completo é obrigatório.</div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($dadosUsuario['email']) ?>" required>
                             <div class="invalid-feedback">Por favor, insira um e-mail válido.</div>
                        </div>

                        <hr>
                        <h5 class="mb-3">Alterar Senha (opcional)</h5>
                         <div class="mb-3">
                            <label for="nova_senha" class="form-label">Nova Senha</label>
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha" minlength="8" aria-describedby="novaSenhaHelp">
                             <div id="novaSenhaHelp" class="form-text">Deixe em branco para manter a senha atual. Mínimo 8 caracteres.</div>
                              <div class="invalid-feedback">A senha deve ter pelo menos 8 caracteres.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirmar_nova_senha" class="form-label">Confirmar Nova Senha</label>
                            <input type="password" class="form-control" id="confirmar_nova_senha" name="confirmar_nova_senha">
                             <div class="invalid-feedback">As senhas não coincidem.</div>
                        </div>
                    </div>

                    <div class="col-md-4"> <?php /* Upload de foto */ ?>
                        <div class="mb-3 text-center">
                            <label for="foto" class="form-label">Foto de Perfil</label>
                            <div id="preview-container" class="mb-2">
                                <?php
                                    $fotoAtualSrc = BASE_URL . 'assets/img/default_profile.png'; // Padrão
                                    if (!empty($dadosUsuario['foto'])) {
                                        $fotoAtualSrc = BASE_URL . 'uploads/fotos/' . $dadosUsuario['foto'] . '?v=' . time(); // Adiciona versão para cache bust
                                    }
                                ?>
                                <img id="current-foto" src="<?= $fotoAtualSrc ?>" alt="Foto Atual" class="img-thumbnail rounded-circle mb-2" style="width: 150px; height: 150px; object-fit: cover;">
                                <img id="preview" src="#" alt="Nova Foto" class="img-thumbnail rounded-circle mb-2" style="width: 150px; height: 150px; object-fit: cover; display: none;">
                            </div>
                            <input type="file" class="form-control" id="foto" name="foto" accept="image/jpeg, image/png, image/gif">
                            <small class="form-text text-muted">Envie JPG, PNG ou GIF (máx 5MB).</small>
                        </div>
                    </div>
                 </div>

                <hr>
                 <div class="mb-3">
                    <label for="senha_atual" class="form-label">Senha Atual <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="senha_atual" name="senha_atual" required autocomplete="current-password" aria-describedby="senhaAtualHelp">
                    <div id="senhaAtualHelp" class="form-text">Digite sua senha atual para confirmar CADA alteração.</div>
                    <div class="invalid-feedback">A senha atual é obrigatória para salvar.</div>
                 </div>

                <div class="d-flex justify-content-end"> <?php /* Botões alinhados à direita */ ?>
                    <a href="<?= BASE_URL ?>admin/dashboard_admin.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

</div> <?php /* Fim container-fluid (ou onde o conteúdo principal termina) */ ?>

<?php /* Script para preview da imagem e validação Bootstrap */ ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputFoto = document.getElementById('foto');
    const preview = document.getElementById('preview');
    const currentFoto = document.getElementById('current-foto');

    if (inputFoto) {
        inputFoto.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    if (currentFoto) {
                        currentFoto.style.display = 'none';
                    }
                }
                reader.readAsDataURL(this.files[0]);
            } else {
                // Se nenhum arquivo for selecionado (ou seleção for cancelada)
                preview.src = '#';
                preview.style.display = 'none';
                if (currentFoto) {
                    currentFoto.style.display = 'block';
                }
            }
        });
    }

    // Ativar validação Bootstrap no formulário
    const form = document.getElementById('configForm');
    if (form) {
        form.addEventListener('submit', event => {
            // Validação extra para confirmação de senha
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = document.getElementById('confirmar_nova_senha').value;
            const confirmarInput = document.getElementById('confirmar_nova_senha');

            if (novaSenha && novaSenha !== confirmarSenha) {
                 confirmarInput.setCustomValidity("As senhas não coincidem."); // Define erro Bootstrap
            } else {
                 confirmarInput.setCustomValidity(""); // Limpa erro
            }

            // Força validação Bootstrap e previne envio se inválido
            if (!form.checkValidity()) {
              event.preventDefault();
              event.stopPropagation();
            }
            form.classList.add('was-validated'); // Mostra feedback visual
        }, false);
    }
});
</script>

<?php
// Inclui o Footer e fecha o HTML
echo getFooterAdmin();
?>