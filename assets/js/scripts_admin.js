// assets/js/scripts_admin.js
// Versão usando a variável global BASE_URL (confirmada como correta)

document.addEventListener('DOMContentLoaded', function () {

    // --- Tenta obter BASE_URL definida no HTML ---
    const appBaseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '/WebApp/'; // Fallback
    if (typeof BASE_URL === 'undefined') {
        console.warn('Variável global JS BASE_URL não definida no layout. Usando fallback:', appBaseUrl);
    }

    /**
     * Função auxiliar para lidar com fetch.
     */
     async function fetchData(url, options) {
        try {
            const response = await fetch(url, options);
            if (!response.ok) {
                let errorData;
                try { errorData = await response.json(); } catch (e) { /* Ignora */ }
                throw new Error(errorData?.erro || `Erro ${response.status}: ${response.statusText}`);
            }
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                return await response.json();
            } else { return null; }
        } catch (error) { console.error('Erro no Fetch:', error); throw new Error(error.message || 'Erro ao comunicar com o servidor.'); }
    }

    // --- Modal de Primeiro Acesso ---
    const primeiroAcessoModalElement = document.getElementById('primeiroAcessoModal');
    const bloqueioConteudoDiv = document.getElementById('bloqueio-conteudo');

    // Verifica se o modal existe na página atual
    if (primeiroAcessoModalElement && bloqueioConteudoDiv) {
        console.log("Modal de primeiro acesso encontrado. Anexando listener..."); // LOG para confirmar

        const formRedefinirSenha = document.getElementById('formRedefinirSenha');
        const novaSenhaInput = document.getElementById('nova_senha_modal');
        const confirmarSenhaInput = document.getElementById('confirmar_senha_modal');
        const senhaErrorDiv = document.getElementById('senha_error');
        const senhaSucessoDiv = document.getElementById('senha_sucesso');
        const novaSenhaFeedback = novaSenhaInput?.parentElement.querySelector('.invalid-feedback');
        const confirmarSenhaFeedback = confirmarSenhaInput?.parentElement.querySelector('.invalid-feedback');

        if (formRedefinirSenha && novaSenhaInput && confirmarSenhaInput && senhaErrorDiv && senhaSucessoDiv) {

            formRedefinirSenha.addEventListener('submit', async function (event) {
                console.log('Submit do form de redefinição interceptado!'); // LOG
                event.preventDefault();
                event.stopPropagation();

                // Limpar erros...
                senhaErrorDiv.style.display = 'none'; senhaErrorDiv.textContent = '';
                senhaSucessoDiv.style.display = 'none'; senhaSucessoDiv.textContent = '';
                novaSenhaInput.classList.remove('is-invalid', 'is-valid');
                confirmarSenhaInput.classList.remove('is-invalid', 'is-valid');
                if (novaSenhaFeedback) novaSenhaFeedback.textContent = 'Senha inválida. Verifique os requisitos.';
                if (confirmarSenhaFeedback) confirmarSenhaFeedback.textContent = 'As senhas não coincidem.';

                console.log('Iniciando validação JS...');
                let isValid = true;
                const novaSenha = novaSenhaInput.value;
                const confirmarSenha = confirmarSenhaInput.value;
                const senhaRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/;

                // ... (Lógica de validação igual) ...
                 if (!novaSenha) { isValid = false; novaSenhaInput.classList.add('is-invalid'); if (novaSenhaFeedback) novaSenhaFeedback.textContent = 'Nova senha é obrigatória.';}
                 else if (!senhaRegex.test(novaSenha)) { isValid = false; novaSenhaInput.classList.add('is-invalid'); if (novaSenhaFeedback) novaSenhaFeedback.textContent = 'Senha deve ter 8+ chars, maiúscula, minúscula e número.';}
                 else { novaSenhaInput.classList.add('is-valid'); }
                 if (!confirmarSenha) { isValid = false; confirmarSenhaInput.classList.add('is-invalid'); if (confirmarSenhaFeedback) confirmarSenhaFeedback.textContent = 'Confirmação de senha é obrigatória.';}
                 else if (novaSenha && novaSenha !== confirmarSenha) { isValid = false; confirmarSenhaInput.classList.add('is-invalid'); if (confirmarSenhaFeedback) confirmarSenhaFeedback.textContent = 'As senhas não coincidem.';}
                 else if (novaSenha && novaSenha === confirmarSenha) { confirmarSenhaInput.classList.add('is-valid');}

                if (!isValid) { console.log('Validação JS falhou.'); return; }

                console.log('Validação JS OK. Fazendo fetch...');
                const formData = new FormData(formRedefinirSenha);
                const submitButton = formRedefinirSenha.querySelector('button[type="submit"]');
                const originalButtonText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Redefinindo...';

                try {
                    const urlEndpoint = `${appBaseUrl}includes/atualizar_senha_primeiro_acesso.php`; // Usa BASE_URL
                    console.log('Chamando fetch para:', urlEndpoint);
                    const data = await fetchData(urlEndpoint, { method: 'POST', body: formData });
                    console.log('Resposta do fetch recebida:', data);

                    if (data && data.sucesso) {
                        console.log('Sucesso retornado pelo PHP.');
                        senhaSucessoDiv.textContent = "Senha redefinida com sucesso! Recarregando...";
                        senhaSucessoDiv.style.display = 'block';
                        novaSenhaInput.classList.remove('is-invalid', 'is-valid');
                        confirmarSenhaInput.classList.remove('is-invalid', 'is-valid');
                        setTimeout(() => { bloqueioConteudoDiv.style.display = 'none'; window.location.reload(); }, 1500);
                    } else {
                         console.error('PHP não retornou sucesso:', data);
                        throw new Error(data?.erro || 'Resposta inesperada do servidor.');
                    }
                } catch (error) {
                    console.error('Erro capturado no bloco try/catch do fetch:', error);
                    senhaErrorDiv.textContent = error.message;
                    senhaErrorDiv.style.display = 'block';
                    novaSenhaInput.classList.remove('is-valid'); confirmarSenhaInput.classList.remove('is-valid');
                    novaSenhaInput.classList.add('is-invalid'); confirmarSenhaInput.classList.add('is-invalid');
                } finally {
                    console.log('Bloco finally executado.');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            }); // Fim addEventListener submit

            // Validação live ...
            confirmarSenhaInput.addEventListener('input', () => { /* ... */ });
        } else {
             console.error("Elementos do formulário de redefinição de senha não encontrados dentro do modal.");
        }
    } else {
         console.log("Modal de primeiro acesso não encontrado nesta página."); // Log normal se não houver modal
    }

}); // Fim DOMContentLoaded