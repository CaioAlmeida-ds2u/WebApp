// assets/js/scripts.js

document.addEventListener('DOMContentLoaded', function() {

    // --- Alternar Abas (Customizado) ---
    const tabButtons = document.querySelectorAll('.tab-button'); // Seleciona todos os botões com a classe .tab-button
    const tabSections = document.querySelectorAll('.tab-section'); // Seleciona todas as seções com a classe .tab-section

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remover classe 'active' de todos os botões
            tabButtons.forEach(btn => btn.classList.remove('active'));
            // Ocultar todas as seções
            tabSections.forEach(section => section.classList.remove('active'));

            // Adicionar 'active' ao botão clicado
            this.classList.add('active');
            // Exibir a seção correspondente
            const tabId = this.dataset.tab; // Obtém o valor do atributo data-tab (ex: "usuarios")
            document.getElementById(tabId).classList.add('active'); // Adiciona 'active' à seção
        });
    });

    // --- Modal de Confirmação de Exclusão (Genérico) ---
    var confirmDeleteModal = document.getElementById('confirmDeleteModal');
    if (confirmDeleteModal) {
        confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-userid');
            var confirmDeleteBtn = confirmDeleteModal.querySelector('.modal-footer #confirmDeleteBtn');
            confirmDeleteBtn.value = id;
        });

        document.getElementById("confirmDeleteBtn").addEventListener("click", function() {
            window.location.href = this.closest('.modal').getAttribute('data-action') + "?id=" + this.value;
        });
    }
});