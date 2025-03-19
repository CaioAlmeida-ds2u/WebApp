<?php
// admin/dashboard_admin.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/layout_admin_dash.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens (Sucesso/Erro) ---
$sucesso = '';
$erro = '';

if (isset($_SESSION['sucesso'])) {
    $sucesso = $_SESSION['sucesso'];
    unset($_SESSION['sucesso']);
}

if (isset($_SESSION['erro'])) {
    $erro = $_SESSION['erro'];
    unset($_SESSION['erro']);
}

// Obter dados do usuário
$usuario_id = $_SESSION['usuario_id'];
$usuario = dbGetDadosUsuario($conexao, $usuario_id); // Certifique-se de que esta função existe em admin_functions.php

if (!$usuario) {
    header('Location: logout.php'); // Redirecionar se o usuário não for encontrado
    exit;
}

// Verificar se é o primeiro acesso
$primeiro_acesso = (isset($usuario['primeiro_acesso']) && $usuario['primeiro_acesso'] == 1);

$title = "ACodITools - Dashboard do Administrador";
echo getHeaderAdmin($title); // Usando getHeaderAdmin()
?>

<div class="container mt-4">
    <div class="jumbotron">
        <h1 class="display-5">Bem-vindo à Dashboard do Administrador!</h1>
        <p class="lead">Aqui você pode gerenciar usuários, empresas, requisitos e monitorar solicitações do sistema.</p>
        <hr class="my-3">
        <p>Use o menu lateral para navegar pelas funcionalidades administrativas.</p>
    </div>
</div>

<?php if ($primeiro_acesso): ?>
    <div class="modal fade show" id="primeiroAcessoModal" tabindex="-1" aria-labelledby="primeiroAcessoModalLabel" aria-hidden="true" style="display: block;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="primeiroAcessoModalLabel">Primeiro Acesso - Redefinir Senha</h5>
                    </div>
                <div class="modal-body">
                    <form id="formRedefinirSenha">
                        <div class="mb-3">
                            <label for="nova_senha" class="form-label">Nova Senha</label>
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                        </div>
                        <div id="senha_error" class="alert alert-danger" style="display: none;"></div>
                        <div id="senha_sucesso" class="alert alert-success" style="display: none;"></div>
                        <button type="submit" class="btn btn-primary">Redefinir Senha</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show" id="modalBackdrop"></div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = new bootstrap.Modal(document.getElementById('primeiroAcessoModal'), {
                    backdrop: 'static', // Impede o fechamento ao clicar fora
                    keyboard: false    // Impede o fechamento ao pressionar Esc
                });
                modal.show();
            
                const form = document.getElementById('formRedefinirSenha');
                const senhaError = document.getElementById('senha_error');
                const senhaSucesso = document.getElementById('senha_sucesso');
            
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    senhaError.style.display = 'none';
                    senhaSucesso.style.display = 'none';
                
                    const novaSenha = document.getElementById('nova_senha').value;
                    const confirmarSenha = document.getElementById('confirmar_senha').value;
                
                    // Nova validação: 6 caracteres, somente letras e números
                    const senhaRegex = /^[a-zA-Z0-9]{6,}$/;
                
                    if (!senhaRegex.test(novaSenha)) {
                        senhaError.textContent = "A senha deve ter pelo menos 6 caracteres e conter apenas letras e números.";
                        senhaError.style.display = 'block';
                        return;
                    }
                
                    if (novaSenha !== confirmarSenha) {
                        senhaError.textContent = "As senhas não coincidem.";
                        senhaError.style.display = 'block';
                        return;
                    }
                
                    const formData = new FormData(form);
                
                    fetch('../atualizar_senha_primeiro_acesso.php', { // Ajuste o caminho para o arquivo
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.sucesso) {
                            senhaSucesso.textContent = "Senha redefinida com sucesso!";
                            senhaSucesso.style.display = 'block';
                            setTimeout(function() {
                                window.location.reload(); // Recarrega a página para remover o modal
                            }, 1500);
                        } else {
                            senhaError.textContent = data.erro || "Erro ao redefinir a senha. Tente novamente.";
                            senhaError.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        senhaError.textContent = "Erro ao comunicar com o servidor.";
                        senhaError.style.display = 'block';
                    });
                });
            });
        </script>
    <?php endif; ?>

<?php echo getFooterAdmin(); ?>