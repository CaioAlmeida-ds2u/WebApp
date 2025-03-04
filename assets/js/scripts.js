document.addEventListener("DOMContentLoaded", function () {
    // --- Inicializa as abas do Bootstrap ---
    var tabButtons = document.querySelectorAll('#adminTabs button[data-bs-toggle="tab"]');

    tabButtons.forEach(function (tab) {
        tab.addEventListener("click", function (event) {
            event.preventDefault();
            var tabInstance = new bootstrap.Tab(tab);
            tabInstance.show();
        });
    });

    // --- Modal de Confirmação de Exclusão (já existente) ---
    var confirmDeleteModal = document.getElementById('confirmDeleteModal'); // O ID *deve* ser confirmDeleteModal

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
