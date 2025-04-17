document.addEventListener('DOMContentLoaded', function () {

    /**
     * Função auxiliar para lidar com fetch e tratamento de erros comuns.
     * @param {string} url URL do endpoint.
     * @param {object} options Opções do fetch (method, body, headers, etc.).
     * @returns {Promise<object>} Promise que resolve com o JSON da resposta ou rejeita com um erro.
     */
    async function fetchData(url, options) {
        try {
            const response = await fetch(url, options);
            if (!response.ok) {
                let errorData;
                try {
                    errorData = await response.json();
                } catch (e) {
                    const text = await response.text();
                    console.error('Resposta bruta do servidor (erro):', text);
                    throw new Error(`Erro ${response.status}: ${response.statusText}`);
                }
                throw new Error(errorData?.erro || `Erro ${response.status}: ${response.statusText}`);
            }
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                return await response.json();
            } else {
                const text = await response.text();
                console.error('Resposta bruta do servidor (não-JSON):', text);
                throw new Error('Resposta inesperada do servidor.');
            }
        } catch (error) {
            console.error('Erro no Fetch:', error);
            throw new Error(error.message || 'Erro ao comunicar com o servidor.');
        }
    }

    // --- Modal de Primeiro Acesso ---
    const primeiroAcessoModalElement = document.getElementById('primeiroAcessoModal');
    const bloqueioConteudoDiv = document.getElementById('bloqueio-conteudo');

    if (primeiroAcessoModalElement && bloqueioConteudoDiv) {
        const formRedefinirSenha = document.getElementById('formRedefinirSenha');
        const novaSenhaInput = document.getElementById('nova_senha_modal');
        const confirmarSenhaInput = document.getElementById('confirmar_senha_modal');
        const senhaErrorDiv = document.getElementById('senha_error');
        const senhaSucessoDiv = document.getElementById('senha_sucesso');
        const novaSenhaFeedback = novaSenhaInput?.nextElementSibling;
        const confirmarSenhaFeedback = confirmarSenhaInput?.nextElementSibling;

        if (formRedefinirSenha && novaSenhaInput && confirmarSenhaInput && senhaErrorDiv && senhaSucessoDiv) {
            formRedefinirSenha.addEventListener('submit', async function (event) {
                event.preventDefault();
                event.stopPropagation();

                // Limpar erros anteriores
                senhaErrorDiv.style.display = 'none';
                senhaErrorDiv.textContent = '';
                senhaSucessoDiv.style.display = 'none';
                senhaSucessoDiv.textContent = '';
                novaSenhaInput.classList.remove('is-invalid');
                confirmarSenhaInput.classList.remove('is-invalid');
                if (novaSenhaFeedback) novaSenhaFeedback.textContent = 'A senha deve ter pelo menos 8 caracteres.';
                if (confirmarSenhaFeedback) confirmarSenhaFeedback.textContent = 'As senhas não coincidem.';

                let isValid = true;
                const novaSenha = novaSenhaInput.value;
                const confirmarSenha = confirmarSenhaInput.value;

                // Regex: mínimo 8 caracteres, 1 maiúscula, 1 minúscula, 1 número
                const senhaRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/;

                if (!novaSenha) {
                    isValid = false;
                    novaSenhaInput.classList.add('is-invalid');
                    if (novaSenhaFeedback) novaSenhaFeedback.textContent = 'Nova senha é obrigatória.';
                } else if (novaSenha.length < 8) {
                    isValid = false;
                    novaSenhaInput.classList.add('is-invalid');
                    if (novaSenhaFeedback) novaSenhaFeedback.textContent = 'A senha deve ter pelo menos 8 caracteres.';
                } else if (!senhaRegex.test(novaSenha)) {
                    isValid = false;
                    novaSenhaInput.classList.add('is-invalid');
                    if (novaSenhaFeedback) novaSenhaFeedback.textContent = 'A senha deve incluir maiúscula, minúscula e número.';
                } else {
                    novaSenhaInput.classList.remove('is-invalid');
                    novaSenhaInput.classList.add('is-valid');
                }

                if (!confirmarSenha) {
                    isValid = false;
                    confirmarSenhaInput.classList.add('is-invalid');
                    if (confirmarSenhaFeedback) confirmarSenhaFeedback.textContent = 'Confirmação de senha é obrigatória.';
                } else if (novaSenha && novaSenha !== confirmarSenha) {
                    isValid = false;
                    confirmarSenhaInput.classList.add('is-invalid');
                    if (confirmarSenhaFeedback) confirmarSenhaFeedback.textContent = 'As senhas não coincidem.';
                } else if (novaSenha && novaSenha === confirmarSenha) {
                    confirmarSenhaInput.classList.remove('is-invalid');
                    confirmarSenhaInput.classList.add('is-valid');
                }

                if (!isValid) {
                    return;
                }

                // Preparar dados para envio
                const formData = new FormData(formRedefinirSenha);

                // Indicador de carregamento
                const submitButton = formRedefinirSenha.querySelector('button[type="submit"]');
                const originalButtonText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Redefinindo...';

                try {
                    const data = await fetchData('/WebApp/includes/atualizar_senha_primeiro_acesso.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (data && data.sucesso) {
                        senhaSucessoDiv.textContent = "Senha redefinida com sucesso! Recarregando...";
                        senhaSucessoDiv.style.display = 'block';
                        novaSenhaInput.classList.remove('is-invalid', 'is-valid');
                        confirmarSenhaInput.classList.remove('is-invalid', 'is-valid');

                        setTimeout(function () {
                            bloqueioConteudoDiv.style.display = 'none';
                            window.location.reload();
                        }, 1500);
                    } else {
                        throw new Error(data?.erro || 'Resposta inesperada do servidor.');
                    }
                } catch (error) {
                    senhaErrorDiv.textContent = error.message;
                    senhaErrorDiv.style.display = 'block';
                    novaSenhaInput.classList.remove('is-valid');
                    confirmarSenhaInput.classList.remove('is-valid');
                    novaSenhaInput.classList.add('is-invalid');
                    confirmarSenhaInput.classList.add('is-invalid');
                } finally {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            });

            // Validação live ao digitar na confirmação
            confirmarSenhaInput.addEventListener('input', () => {
                if (novaSenhaInput.value && confirmarSenhaInput.value && novaSenhaInput.value !== confirmarSenhaInput.value) {
                    confirmarSenhaInput.classList.add('is-invalid');
                    if (confirmarSenhaFeedback) confirmarSenhaFeedback.textContent = 'As senhas não coincidem.';
                } else {
                    confirmarSenhaInput.classList.remove('is-invalid');
                }
            });
        }
    }
});