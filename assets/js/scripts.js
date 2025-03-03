// assets/js/scripts.js

document.addEventListener('DOMContentLoaded', function() {

    // --- Modal de Confirmação de Exclusão (Genérico) ---

    var confirmDeleteModal = document.getElementById('confirmDeleteModal'); // O ID *deve* ser confirmDeleteModal

    if (confirmDeleteModal) { // Verifica se o modal existe na página
        confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-userid'); // Pega o ID do data-userid (pode ser outro nome, se você mudar no HTML)
            var confirmDeleteBtn = confirmDeleteModal.querySelector('.modal-footer #confirmDeleteBtn'); //O ID *deve* ser confirmDeleteBtn
            confirmDeleteBtn.value = id;
        });

        document.getElementById("confirmDeleteBtn").addEventListener("click", function() {
             //Redireciona, agora com ação.
            window.location.href = this.closest('.modal').getAttribute('data-action') + "?id=" + this.value; // Obtém a ação do atributo data-action
        });
    }
});

// --- Outras funções JavaScript reutilizáveis podem ser adicionadas aqui ---