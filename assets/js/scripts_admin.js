document.addEventListener('DOMContentLoaded', function () {

    // --- Inicializa DropDown do Bootstrap (JS) ---
    if (typeof bootstrap !== 'undefined') {
        const dropdownElements = document.querySelectorAll('.dropdown-toggle');
        dropdownElements.forEach(dropdown => {
            new bootstrap.Dropdown(dropdown);
        });
    } else {
        console.error("Bootstrap JS não foi carregado corretamente.");
    }

    // --- Alternar Abas (Customizado) ---
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabSections = document.querySelectorAll('.tab-section');

    if (tabButtons.length > 0 && tabSections.length > 0) {
        tabButtons.forEach(button => {
            button.addEventListener('click', function () {
                // Remover classe 'active' de todos os botões e seções
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabSections.forEach(section => section.classList.remove('active'));

                // Adicionar 'active' ao botão clicado e à seção correspondente
                this.classList.add('active');
                const tabId = this.dataset.tab; // 'usuarios', 'solicitacoes-acesso', etc.
                const targetSection = document.getElementById(tabId);
                
                if (targetSection) {
                    targetSection.classList.add('active');
                } else {
                    console.error(`Elemento com ID "${tabId}" não encontrado.`);
                }
            });
        });
    } else {
        console.error("Elementos de abas não encontrados.");
    }

});
document.addEventListener('DOMContentLoaded', function() {

    // --- Modal de Confirmação de Exclusão (Genérico) ---
      var confirmDeleteModal = document.getElementById('confirmDeleteModal');
      if (confirmDeleteModal) { //Verifica se o modal existe
          confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
              var button = event.relatedTarget;
              var id = button.getAttribute('data-userid');
              var confirmDeleteBtn = confirmDeleteModal.querySelector('.modal-footer #confirmDeleteBtn');
              confirmDeleteBtn.value = id; //Passa o valor
          });
  
          document.getElementById("confirmDeleteBtn").addEventListener("click", function() {
              window.location.href = this.closest('.modal').getAttribute('data-action') + "?id=" + this.value; //Usa o data-action
          });
      }
  });