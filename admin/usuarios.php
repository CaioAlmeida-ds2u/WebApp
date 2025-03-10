<?php
// admin/usuarios.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/layout_admin_dash.php'; // layout correto!

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../acesso_negado.php');
    exit;
}

$title = "ACodITools - Gestão de Usuários";
echo getHeaderAdmin($title);
?>

<ul class="nav nav-tabs" id="adminTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button" role="tab" aria-controls="usuarios" aria-selected="true" data-url="usuarios_conteudo.php">Usuários</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="solicitacoes-acesso-tab" data-bs-toggle="tab" data-bs-target="#solicitacoes-acesso" type="button" role="tab" aria-controls="solicitacoes-acesso" aria-selected="false" data-url="solicitacoes_acesso_conteudo.php">Solicitações de Acesso</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="solicitacoes-reset-tab" data-bs-toggle="tab" data-bs-target="#solicitacoes-reset" type="button" role="tab" aria-controls="solicitacoes-reset" aria-selected="false" data-url="solicitacoes_reset_conteudo.php">Solicitações de Reset</button>
    </li>
</ul>

<div class="tab-content" id="adminTabContent">
    <div class="tab-pane fade show active" id="usuarios" role="tabpanel" aria-labelledby="usuarios-tab">
        </div>
    <div class="tab-pane fade" id="solicitacoes-acesso" role="tabpanel" aria-labelledby="solicitacoes-acesso-tab">
        </div>
    <div class="tab-pane fade" id="solicitacoes-reset" role="tabpanel" aria-labelledby="solicitacoes-reset-tab">
        </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Carrega o conteúdo da primeira aba por padrão.  Importante para SEO!
    carregarConteudoAba('usuarios_conteudo.php', 'usuarios');

    const tabLinks = document.querySelectorAll('#adminTabs .nav-link');

    tabLinks.forEach(link => {
        link.addEventListener('click', function(event) {
            event.preventDefault(); // Previne o comportamento padrão do link
            const url = this.getAttribute('data-url');
            const targetId = this.getAttribute('data-bs-target').substring(1); // Remove o '#'
            carregarConteudoAba(url, targetId);
             // Adiciona/Remove classe 'active' manualmente.  O Bootstrap *não* fará isso sozinho com AJAX!
            tabLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');

        });
    });

    function carregarConteudoAba(url, targetId) {
        fetch(url) // Faz a requisição AJAX
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status}`);
                }
                return response.text(); // Obtém a resposta como texto
            })
            .then(data => {
                document.getElementById(targetId).innerHTML = data; // Insere o HTML no container
                //Reativa os event listeners dos modais, após o carregamento via AJAX.
                reativarModais();

            })
            .catch(error => {
                console.error('Erro ao carregar a aba:', error);
                document.getElementById(targetId).innerHTML = '<p>Erro ao carregar o conteúdo.</p>';
            });
    }

    function reativarModais(){
      var deleteModals = document.querySelectorAll('[id^="confirmDeleteModal"]');
        deleteModals.forEach(function(modal) {
        //Verifica se o eventListner já foi adicionado
        if (!modal.dataset.listenerAdicionado){
          modal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var userId = button.getAttribute('data-userid');
                var action = button.getAttribute('data-action');
                var deleteLink = modal.querySelector('.btn-danger');
                if (deleteLink) {
                    deleteLink.href = action + '?id=' + userId;
                }
            });
            modal.dataset.listenerAdicionado = 'true'; //Marca que o listener foi adicionado
          }

        });
    }
});
</script>
<?php echo getFooterAdmin(); ?>