<?php
// gestor/configuracoes_gestor.php - Página de Configurações para o Gestor

require_once __DIR__ . '/../includes/config.php';        // Config, DB, CSRF Token, Base URL
// **** USA O LAYOUT DO GESTOR ****
require_once __DIR__ . '/../includes/layout_gestor.php';
// Funções de usuário/admin são reutilizadas
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/funcoes_upload.php';
require_once __DIR__ . '/../includes/db.php';

// Proteção da página: Verifica se está logado e se é GESTOR
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'gestor') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

$erro = '';
$sucesso = '';
$usuario_id = $_SESSION['usuario_id']; // ID do GESTOR logado

// --- Carregar Dados Atuais do Usuário (Gestor) ---
$dadosUsuario = getUsuario($conexao, $usuario_id); // Usa a mesma função getUsuario

if (!$dadosUsuario) {
    error_log("Erro crítico: Não foi possível carregar dados para o GESTOR ID $usuario_id em configuracoes_gestor.php");
    header('Location: ' . BASE_URL . 'logout.php?erro=dados_usuario_invalidos');
    exit;
}

// --- Processamento do Formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erro = "Erro de validação da sessão. Recarregue e tente novamente.";
        // Usar um código de ação diferente para o log do gestor
        dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'config_gestor_csrf_falha', 0, 'Token CSRF inválido.', $conexao);
    } else {
        // Token válido, processar dados
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senhaAtual = $_POST['senha_atual'] ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';
        $confirmarNovaSenha = $_POST['confirmar_nova_senha'] ?? '';
        $foto = $_FILES['foto'] ?? null;

        // 2. Validação dos Campos (Lógica idêntica à do admin)
        $errors = [];
        if (empty($nome)) { $errors[] = "Nome Completo obrigatório."; }
        if (empty($email)) { $errors[] = "E-mail obrigatório."; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Formato de e-mail inválido."; }
        elseif (dbEmailExisteEmOutroUsuario($email, $usuario_id, $conexao)) { $errors[] = "Este e-mail já pertence a outro usuário."; }

        // Validação de senha (idêntica)
        $senhaParaAtualizar = null;
        $nenhuma_alteracao = false; // Flag para verificar se algo mudou
        if (!empty($novaSenha)) {
            if (empty($senhaAtual)) { $errors[] = "Senha atual necessária para definir nova senha."; }
            elseif (!dbVerificaSenha($usuario_id, $senhaAtual, $conexao)) { $errors[] = "Senha atual incorreta."; }
            elseif (strlen($novaSenha) < 8) { $errors[] = "Nova senha: mínimo 8 caracteres."; }
             elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $novaSenha)){ $errors[] = "Nova senha: deve conter maiúscula, minúscula e número."; } // Validação de complexidade
            elseif ($novaSenha !== $confirmarNovaSenha) { $errors[] = "Nova senha e confirmação não coincidem."; }
            else { $senhaParaAtualizar = $novaSenha; }
        } elseif (!empty($senhaAtual)) { // Digitou senha atual, mas não nova senha (para confirmar outras mudanças)
             if (!dbVerificaSenha($usuario_id, $senhaAtual, $conexao)) { $errors[] = "Senha atual incorreta. Necessária para salvar alterações."; }
        } elseif (empty($senhaAtual) && $foto['error'] === UPLOAD_ERR_NO_FILE && $nome === $dadosUsuario['nome'] && $email === $dadosUsuario['email']) {
             // Nenhuma alteração detectada
             $sucesso = "Nenhuma alteração foi feita.";
             $nenhuma_alteracao = true;
        } elseif (empty($senhaAtual)) { // Mudou algo mas não digitou a senha atual
            $errors[] = "Digite sua senha atual para confirmar as alterações.";
        }


        // Validação da foto (idêntica)
        $fotoAntiga = $dadosUsuario['foto'];
        $caminhoFotoNova = null;
        if ($foto && $foto['error'] !== UPLOAD_ERR_NO_FILE) {
            $foto_erro = validarImagem($foto); // Usa mesma função
            if ($foto_erro) { $errors[] = $foto_erro; }
        }

        // 3. Atualizar Dados se não houver erros E houver alterações
        if (empty($errors) && !$nenhuma_alteracao) {
            $upload_success = true;
            if ($foto && $foto['error'] === UPLOAD_ERR_OK) {
                // Usa mesma função de upload, ela já atualiza a sessão
                $uploadResult = processarUploadFoto($foto, $usuario_id, $conexao);
                if (!$uploadResult['success']) {
                    $errors[] = "Erro no upload: " . $uploadResult['message'];
                    $upload_success = false;
                    dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'config_gestor_upload_falha', 0, $uploadResult['message'], $conexao);
                } else {
                    $caminhoFotoNova = 'uploads/fotos/' . $uploadResult['caminho'];
                    dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'config_gestor_upload_sucesso', 1, 'Foto de perfil atualizada.', $conexao);
                }
            }

            $dbUpdateOK = false;
            if ($upload_success) {
                 if ($nome !== $dadosUsuario['nome'] || $email !== $dadosUsuario['email'] || $senhaParaAtualizar !== null) {
                    // Usa mesma função de update
                    $dbUpdateOK = dbAtualizarUsuario($usuario_id, $nome, $email, $senhaParaAtualizar, $conexao);
                    if ($dbUpdateOK) {
                        if ($nome !== $dadosUsuario['nome']) { $_SESSION['nome'] = $nome; } // Atualiza nome na sessão
                        $log_details = "Perfil gestor atualizado.";
                        if ($senhaParaAtualizar !== null) $log_details .= " Senha alterada.";
                         dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'config_gestor_update_sucesso', 1, $log_details, $conexao);
                    } else {
                         $errors[] = "Erro ao salvar dados no banco.";
                          dbRegistrarLogAcesso($usuario_id, $_SERVER['REMOTE_ADDR'], 'config_gestor_update_falha_db', 0, 'Erro DB ao atualizar perfil.', $conexao);
                    }
                } else {
                    $dbUpdateOK = $upload_success; // Se só mudou a foto, já está ok
                }
            }

            if (empty($errors) && ($dbUpdateOK || ($upload_success && $houveTentativaUpload = ($foto && $foto['error'] === UPLOAD_ERR_OK)))) {
                 $sucesso = "Seus dados foram atualizados com sucesso!";
                 $dadosUsuario = getUsuario($conexao, $usuario_id); // Recarrega dados
            }
        } // Fim if empty(errors)

        // Monta mensagem de erro final
        if (!empty($errors)) {
             $erro = "<strong>Não foi possível salvar:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
        }
    } // Fim do else da validação CSRF
} // Fim do if POST

// --- Geração do HTML ---
$title = "Minhas Configurações";
// **** CHAMA O HEADER DO GESTOR ****
echo getHeaderGestor($title);
?>

    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-user-cog me-2"></i>Minhas Configurações</h1>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $erro ?> <?php /* Permite HTML */ ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($sucesso && !isset($nenhuma_alteracao)): // Só mostra sucesso se houve alteração real ?>
         <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso) ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
     <?php elseif ($sucesso && isset($nenhuma_alteracao)): // Mensagem informativa se não houve alteração ?>
           <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i><?= htmlspecialchars($sucesso) ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>gestor/configuracoes_gestor.php" enctype="multipart/form-data" id="configFormGestor" class="needs-validation" novalidate>
         <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
         <div class="card shadow-sm border-0 rounded-3"> <?php /* Estilo do card */ ?>
            <div class="card-body p-4">
                 <div class="row g-4"> <?php /* Espaçamento entre colunas */ ?>
                    <div class="col-lg-8"> <?php /* Campos de texto */ ?>
                        <h5 class="mb-3 fw-semibold border-bottom pb-2">Dados Pessoais</h5>
                        <div class="mb-3">
                            <label for="nome" class="form-label form-label-sm">Nome Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="nome" name="nome" value="<?= htmlspecialchars($dadosUsuario['nome']) ?>" required>
                            <div class="invalid-feedback">Campo obrigatório.</div>
                        </div>
                        <div class="mb-4"> <?php /* Mais margem inferior */ ?>
                            <label for="email" class="form-label form-label-sm">E-mail <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-sm" id="email" name="email" value="<?= htmlspecialchars($dadosUsuario['email']) ?>" required>
                             <div class="invalid-feedback">E-mail inválido ou obrigatório.</div>
                        </div>

                        <h5 class="mb-3 fw-semibold border-bottom pb-2">Alterar Senha</h5>
                         <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label for="nova_senha" class="form-label form-label-sm">Nova Senha</label>
                                <input type="password" class="form-control form-control-sm" id="nova_senha" name="nova_senha" minlength="8" aria-describedby="novaSenhaHelp">
                                 <div id="novaSenhaHelp" class="form-text small text-muted">Mínimo 8 caracteres, maiúscula, minúscula e número. Deixe em branco para não alterar.</div>
                                  <div class="invalid-feedback small">Senha inválida. Verifique os requisitos.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirmar_nova_senha" class="form-label form-label-sm">Confirmar Nova Senha</label>
                                <input type="password" class="form-control form-control-sm" id="confirmar_nova_senha" name="confirmar_nova_senha">
                                 <div class="invalid-feedback small">As senhas não coincidem.</div>
                            </div>
                         </div>
                    </div>

                    <div class="col-lg-4 border-start-lg"> <?php /* Upload de foto */ ?>
                        <div class="mb-3 text-center sticky-lg-top pt-lg-3">
                            <label for="foto" class="form-label form-label-sm fw-semibold d-block mb-2">Foto de Perfil</label>
                            <div id="preview-container" class="mb-2">
                                <?php
                                    $fotoAtualSrc = BASE_URL . 'assets/img/default_profile.png'; // Padrão
                                    if (!empty($dadosUsuario['foto']) && file_exists(__DIR__.'/../uploads/fotos/'.$dadosUsuario['foto'])) {
                                        $fotoAtualSrc = BASE_URL . 'uploads/fotos/' . $dadosUsuario['foto'] . '?v=' . filemtime(__DIR__.'/../uploads/fotos/'.$dadosUsuario['foto']);
                                    }
                                ?>
                                <img id="current-foto" src="<?= $fotoAtualSrc ?>" alt="Foto Atual" class="img-thumbnail rounded-circle mb-2 shadow-sm" style="width: 150px; height: 150px; object-fit: cover;">
                                <img id="preview" src="#" alt="Nova Foto" class="img-thumbnail rounded-circle mb-2 shadow-sm" style="width: 150px; height: 150px; object-fit: cover; display: none;">
                            </div>
                             <label for="foto" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-upload me-1"></i> Alterar Foto
                             </label>
                            <input type="file" class="form-control d-none" id="foto" name="foto" accept="image/jpeg, image/png, image/gif">
                            <small class="form-text text-muted d-block mt-1">JPG, PNG, GIF (máx 5MB).</small>
                            <div class="invalid-feedback small">Arquivo inválido ou muito grande.</div> <?php /* Feedback para erro de upload */ ?>
                        </div>
                    </div>
                 </div>

                <hr class="my-4">
                 <div class="mb-3 bg-light-subtle p-3 rounded border"> <?php /* Destaque para senha atual */ ?>
                    <label for="senha_atual" class="form-label form-label-sm fw-bold">Senha Atual <span class="text-danger">*</span></label>
                    <input type="password" class="form-control form-control-sm" id="senha_atual" name="senha_atual" required autocomplete="current-password" aria-describedby="senhaAtualHelp">
                    <div id="senhaAtualHelp" class="form-text small text-muted mt-1">Necessária para confirmar CADA alteração salva.</div>
                    <div class="invalid-feedback small">Senha atual obrigatória para salvar.</div>
                 </div>

            </div> <?php /* Fim card-body */ ?>
            <div class="card-footer text-end bg-light border-0 pt-3 pb-3"> <?php /* Footer do card */ ?>
                 <?php // O link Cancelar agora volta para o dashboard do GESTOR ?>
                 <a href="<?= BASE_URL ?>gestor/dashboard_gestor.php" class="btn btn-secondary btn-sm me-2">Cancelar</a>
                 <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Salvar Alterações</button>
            </div>
         </div> <?php /* Fim card */ ?>
    </form>

<?php /* Script JS (idêntico ao do admin, pois usa os mesmos IDs) */ ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputFoto = document.getElementById('foto');
    const preview = document.getElementById('preview');
    const currentFoto = document.getElementById('current-foto');

    if (inputFoto) {
        inputFoto.addEventListener('change', function() {
             const file = this.files[0];
             if (file && file.type.startsWith('image/')) {
                 const fileErrorDiv = inputFoto.parentElement.querySelector('.invalid-feedback'); // Encontra div de erro
                 inputFoto.classList.remove('is-invalid'); // Limpa erro visual prévio
                 if (fileErrorDiv) fileErrorDiv.textContent = 'Arquivo inválido ou muito grande.'; // Reseta msg

                 // Validação rápida de tamanho no JS (ex: 5MB)
                 if (file.size > 5 * 1024 * 1024) {
                     inputFoto.classList.add('is-invalid'); // Mostra erro visual
                     if (fileErrorDiv) fileErrorDiv.textContent = 'Arquivo excede 5MB!';
                     this.value = ''; // Limpa seleção
                     preview.src = '#'; preview.style.display = 'none'; currentFoto.style.display = 'block';
                     return; // Interrompe
                 }

                const reader = new FileReader();
                reader.onload = function(e) { preview.src = e.target.result; preview.style.display = 'block'; if (currentFoto) currentFoto.style.display = 'none'; }
                reader.readAsDataURL(file);
            } else {
                preview.src = '#'; preview.style.display = 'none';
                if (currentFoto) currentFoto.style.display = 'block';
                 if (this.files.length > 0) { // Se selecionou algo que não é imagem
                      inputFoto.classList.add('is-invalid');
                      const fileErrorDiv = inputFoto.parentElement.querySelector('.invalid-feedback');
                       if (fileErrorDiv) fileErrorDiv.textContent = 'Tipo de arquivo inválido.';
                 }
            }
        });
    }

    // Ativar validação Bootstrap no formulário
    const form = document.getElementById('configFormGestor'); // Usa ID do form do gestor
    if (form) {
        form.addEventListener('submit', event => {
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = document.getElementById('confirmar_nova_senha').value;
            const confirmarInput = document.getElementById('confirmar_nova_senha');
            const senhaAtualInput = document.getElementById('senha_atual');

             // Limpa validade customizada prévia
             confirmarInput.setCustomValidity("");
             // Valida confirmação
            if (novaSenha && novaSenha !== confirmarSenha) {
                 confirmarInput.setCustomValidity("As senhas não coincidem.");
            }
            // Força a senha atual ser preenchida se qualquer outro campo relevante foi alterado
            const nomeAtual = "<?= htmlspecialchars($dadosUsuario['nome']) ?>"; // Pega valor PHP inicial
            const emailAtual = "<?= htmlspecialchars($dadosUsuario['email']) ?>";
             const fotoEnviada = document.getElementById('foto').files.length > 0;

            if ( (document.getElementById('nome').value !== nomeAtual ||
                  document.getElementById('email').value !== emailAtual ||
                  novaSenha ||
                  fotoEnviada) &&
                 !senhaAtualInput.value)
            {
                 senhaAtualInput.setCustomValidity("Senha atual obrigatória para salvar alterações.");
            } else {
                  senhaAtualInput.setCustomValidity("");
            }


            if (!form.checkValidity()) {
              event.preventDefault();
              event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);

        // Limpa validação da senha atual quando o usuário começa a digitar nela
        const senhaAtualInput = document.getElementById('senha_atual');
        if (senhaAtualInput) {
            senhaAtualInput.addEventListener('input', () => {
                senhaAtualInput.setCustomValidity(""); // Limpa erro customizado ao digitar
            });
        }
    }
});
</script>

<?php
// Inclui o Footer e fecha o HTML
echo getFooterGestor(); // Usa o footer do gestor
?>