// assets/js/admin_scripts.js

document.addEventListener('DOMContentLoaded', function() {

    // --- Alternar Abas (Customizado) ---
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabSections = document.querySelectorAll('.tab-section');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remover classe 'active' de todos os botões e seções
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabSections.forEach(section => section.classList.remove('active'));

            // Adicionar 'active' ao botão clicado e à seção correspondente
            this.classList.add('active');
            const tabId = this.dataset.tab; // 'usuarios', 'solicitacoes-acesso', etc.
            document.getElementById(tabId).classList.add('active');
        });
    });
});