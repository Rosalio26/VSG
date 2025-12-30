/**
 * ARQUIVO UNIFICADO FINAL E ATUALIZADO
 * Lógica de Negócios + Localização Automática + Validação + Persistência + AJAX
 */

/* ==========================================================================
   PARTE 1: FUNÇÕES GLOBAIS (Navegação e Persistência)
   ========================================================================== */

let isNavigating = false;
window.visionGreenIti = {}; // Objeto global para instâncias do intl-tel-input

/**
 * Salva o progresso no LocalStorage
 */
function saveProgress(step) {
    const activeStep = step || document.querySelector('.step-content.active')?.dataset.step || 1;
    localStorage.setItem('cadastro_step', activeStep);

    const formValues = {};
    // Salva dados de ambos os formulários (Business e Pessoa)
    document.querySelectorAll('input:not([type="password"]):not([type="file"]), select, textarea').forEach(el => {
        if (el.id || el.name) {
            formValues[el.id || el.name] = el.value;
        }
    });
    localStorage.setItem('cadastro_data', JSON.stringify(formValues));
}

/**
 * Restaura os dados ao recarregar a página
 */
function restoreData() {
    const savedData = JSON.parse(localStorage.getItem('cadastro_data'));
    if (!savedData) return;

    Object.keys(savedData).forEach(key => {
        const el = document.getElementById(key) || document.getElementsByName(key)[0];
        if (el) {
            el.value = savedData[key];
            if (el.tagName === 'SELECT') el.dispatchEvent(new Event('change'));
        }
    });
}

function clearStorage() {
    localStorage.removeItem('cadastro_step');
    localStorage.removeItem('cadastro_data');
}

/**
 * Validação de Campos
 */
function validateStep(step, showErrors = true) {
    const container = document.querySelector(`.step-content[data-step="${step}"]`);
    if (!container) return true;

    const inputs = container.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    inputs.forEach(input => {
        const parent = input.closest('.person-field-input');
        if (!input.value.trim()) {
            isValid = false;
            if (showErrors) {
                if (parent) parent.classList.add('field-error');
                input.classList.add('input-error');
            }
        } else {
            if (parent) parent.classList.remove('field-error');
            input.classList.remove('input-error');
        }
    });

    return isValid;
}

/**
 * Navegação entre Steps
 */
window.changeStep = function(current, next) {
    if (isNavigating) return;

    if (next > current && !validateStep(current, true)) {
        const errorEl = document.querySelector('.step-content.active .input-error');
        if (errorEl) errorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    isNavigating = true;
    saveProgress(next);

    document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
    const nextContent = document.querySelector(`.step-content[data-step="${next}"]`);
    
    if (nextContent) {
        nextContent.classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    setTimeout(() => { isNavigating = false; }, 500);
};

/* ==========================================================================
   PARTE 2: LOCALIZAÇÃO E TELEFONE
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
    
    // 1. Inicializa o intl-tel-input para Pessoa e Business
    const telPessoa = document.querySelector("#telefone_input");
    const telBusiness = document.querySelector("#tel_business");

    const itiOptions = {
        initialCountry: "auto",
        geoIpLookup: function(success, failure) {
            fetch("https://ipapi.co/json/")
                .then(res => res.json())
                .then(data => success(data.country_code))
                .catch(() => success("br"));
        },
        utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js"
    };

    if (telPessoa) window.visionGreenIti.pessoa = window.intlTelInput(telPessoa, itiOptions);
    if (telBusiness) window.visionGreenIti.business = window.intlTelInput(telBusiness, itiOptions);

    // 2. Detecção de Localização Robusta para o Formulário de Negócio
    async function detectarLocalizacao() {
        const selectPais = document.getElementById('select_pais');
        const regiaoInput = document.getElementById('regiao');
        const localidadeInput = document.getElementById('localidade');

        try {
            const response = await fetch('https://ipapi.co/json/');
            const data = await response.json();
            
            if (data.country_code && selectPais) {
                selectPais.value = data.country_code;
                if (regiaoInput && data.region) regiaoInput.value = data.region;
                if (localidadeInput && data.city) localidadeInput.value = data.city;
                selectPais.dispatchEvent(new Event('change'));
            }
        } catch (e) { console.warn("Erro ao autodetectar localização"); }
    }

    // 3. Carregamento da Lista de Países
    async function initCountries() {
        const selectPais = document.getElementById('select_pais');
        if (!selectPais) return;

        try {
            const response = await fetch('https://restcountries.com/v3.1/all?fields=name,cca2,translations');
            const paises = await response.json();
            paises.sort((a, b) => (a.translations.por?.common || a.name.common).localeCompare(b.translations.por?.common || b.name.common));

            let options = '<option value="">Selecione o país</option>';
            paises.forEach(p => {
                options += `<option value="${p.cca2}">${p.translations.por?.common || p.name.common}</option>`;
            });
            selectPais.innerHTML = options;

            restoreData(); 
            if (!selectPais.value) await detectarLocalizacao();

        } catch (e) {
            console.error("Erro API Países");
            restoreData();
        }
    }

    await initCountries();

    // 4. Garante que o usuário comece no step correto salvo
    const savedStep = parseInt(localStorage.getItem('cadastro_step')) || 1;
    if (savedStep > 1) {
        document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
        const target = document.querySelector(`.step-content[data-step="${savedStep}"]`);
        if (target) target.classList.add('active');
    }

    /* ==========================================================================
       PARTE 3: SUBMISSÃO AJAX
       ========================================================================== */

    function setupFormSubmit(form, url) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();

            // Validação final de step se for business
            if (form.id === 'formBusiness' && !validateStep(3, true)) return;

            // Sincroniza telefone internacional
            const type = (form.id === "formPessoa") ? "pessoa" : "business";
            const hiddenTel = form.querySelector("input[name='telefone']");
            if (window.visionGreenIti[type]) {
                hiddenTel.value = window.visionGreenIti[type].getNumber();
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = "Processando...";

            try {
                const res = await fetch(url, {
                    method: "POST",
                    body: new FormData(form),
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                if (data.success) {
                    clearStorage();
                    if (data.redirect) window.location.href = data.redirect;
                } else if (data.errors) {
                    // Lógica para mostrar erros do PHP (reutilize sua showErrors aqui)
                    alert("Verifique os campos com erro.");
                }
            } catch (err) {
                alert("Falha na conexão.");
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    const fPessoa = document.getElementById("formPessoa");
    const fBus = document.getElementById("formBusiness");
    if (fPessoa) setupFormSubmit(fPessoa, "../process/pessoa.store.php");
    if (fBus) setupFormSubmit(fBus, "../process/cadastro.process.php");
});