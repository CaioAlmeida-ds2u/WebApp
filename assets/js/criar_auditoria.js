document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formCriarAuditoria');
    const modoModeloRadio = document.getElementById('modoModelo');
    const modoManualRadio = document.getElementById('modoManual');
    const selecaoModeloDiv = document.getElementById('selecaoModeloDiv');
    const selecaoManualDiv = document.getElementById('selecaoManualDiv');
    const modeloSelect = document.getElementById('modelo_id');
    const requisitosContainer = document.getElementById('requisitosChecklist');
    const requisitosCheckboxes = requisitosContainer?.querySelectorAll('.requisito-item input[type="checkbox"]');
    const requisitosError = document.getElementById('requisitosError');
    const filtroInput = document.getElementById('filtroRequisitos');
    const noResultsMessage = requisitosContainer?.querySelector('.no-results-message');

    function toggleCampos() {
        if (!modoModeloRadio || !modoManualRadio) return;
        if (modoModeloRadio.checked) {
            selecaoModeloDiv.classList.remove('d-none');
            selecaoManualDiv.classList.add('d-none');
            if (modeloSelect) modeloSelect.required = true;
            if (requisitosError) requisitosError.style.display = 'none';
        } else {
            selecaoModeloDiv.classList.add('d-none');
            selecaoManualDiv.classList.remove('d-none');
            if (modeloSelect) { modeloSelect.required = false; modeloSelect.value = ''; }
        }
    }

    if (modoModeloRadio) modoModeloRadio.addEventListener('change', toggleCampos);
    if (modoManualRadio) modoManualRadio.addEventListener('change', toggleCampos);
    toggleCampos();

    if (form) {
        form.addEventListener('submit', event => {
            let manualRequisitosValido = true;
            let datasValidas = true;
            const dataInicio = document.getElementById('data_inicio').value;
            const dataFim = document.getElementById('data_fim').value;

            if (dataInicio && dataFim && new Date(dataFim) < new Date(dataInicio)) {
                datasValidas = false;
                const dataFimInput = document.getElementById('data_fim');
                dataFimInput.classList.add('is-invalid');
                dataFimInput.nextElementSibling.textContent = 'Data fim não pode ser anterior à data de início.';
            }

            if (modoManualRadio.checked) {
                let algumSelecionado = false;
                if (requisitosCheckboxes) {
                    requisitosCheckboxes.forEach(cb => { if (cb.checked) algumSelecionado = true; });
                }
                if (!algumSelecionado) {
                    manualRequisitosValido = false;
                    if (requisitosError) requisitosError.style.display = 'block';
                    requisitosContainer?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    if (requisitosError) requisitosError.style.display = 'none';
                }
            } else {
                if (requisitosError) requisitosError.style.display = 'none';
            }

            if (!form.checkValidity() || !manualRequisitosValido || !datasValidas) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
            if (modoModeloRadio.checked && modeloSelect && !modeloSelect.checkValidity()) {
                modeloSelect.classList.add('is-invalid');
            } else if (modeloSelect) {
                modeloSelect.classList.remove('is-invalid');
            }
        });

        if (requisitosCheckboxes && requisitosError) {
            requisitosCheckboxes.forEach(cb => {
                cb.addEventListener('change', () => {
                    if (modoManualRadio.checked) {
                        let algumSelecionado = false;
                        requisitosCheckboxes.forEach(innerCb => { if (innerCb.checked) algumSelecionado = true; });
                        if (algumSelecionado) requisitosError.style.display = 'none';
                    }
                });
            });
        }
    }

    if (filtroInput && requisitosContainer) {
        filtroInput.addEventListener('input', function() {
            const termo = this.value.toLowerCase().trim();
            let algumVisivel = false;
            const todosItens = requisitosContainer.querySelectorAll('.requisito-item');
            const todasCategorias = requisitosContainer.querySelectorAll('.categoria-group');

            todosItens.forEach(item => {
                const textoItem = item.dataset.texto || '';
                const visivel = termo === '' || textoItem.includes(termo);
                item.style.display = visivel ? '' : 'none';
                if (visivel) algumVisivel = true;
            });

            todasCategorias.forEach(cat => {
                const itensVisiveisNaCategoria = cat.querySelectorAll('.requisito-item[style*="display: none;"]');
                const totalItensNaCategoria = cat.querySelectorAll('.requisito-item');
                cat.style.display = (itensVisiveisNaCategoria.length === totalItensNaCategoria.length && termo !== '') ? 'none' : '';
                if (cat.style.display === '' && termo !== '') algumVisivel = true;
            });

            if (noResultsMessage) {
                noResultsMessage.style.display = algumVisivel ? 'none' : 'block';
            }
        });

        if (filtroInput.value) {
            filtroInput.dispatchEvent(new Event('input'));
        }
    }
});