/**
 * LÓGICA DE NEGÓCIOS - VISION GREEN (VERSÃO FINAL INTEGRADA)
 */

let isNavigating = false; // Trava para impedir cliques múltiplos

/* ================= 1. PERSISTÊNCIA (LOCALSTORAGE) ================= */

function saveProgress(step) {
    const currentStep = step || document.querySelector('.step-content.active')?.dataset.step || 1;
    localStorage.setItem('cadastro_step', currentStep);

    const formValues = {};
    document.querySelectorAll('#formBusiness input, #formBusiness select, #formBusiness textarea').forEach(el => {
        if ((el.id || el.name) && el.type !== 'file') {
            formValues[el.id || el.name] = el.value;
        }
    });
    localStorage.setItem('cadastro_data', JSON.stringify(formValues));
}

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

/* ================= 2. VALIDAÇÃO CENTRALIZADA ================= */

/**
 * Valida os campos de uma etapa específica.
 * @param {number} step - Etapa a validar.
 * @param {boolean} showErrors - Se true, aplica estilos vermelhos (falso no reload).
 */
function validateStep(step, showErrors = true) {
    const container = document.querySelector(`.step-content[data-step="${step}"]`);
    if (!container) return true;

    const inputs = container.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    inputs.forEach(input => {
        const parent = input.closest('.person-field-input');
        const itiContainer = input.closest('.iti'); // Plugin de telefone
        const value = input.value.trim();

        if (!value) {
            isValid = false;
            if (showErrors) {
                if (parent) parent.classList.add('field-error');
                input.classList.add('input-error');
                if (itiContainer) itiContainer.classList.add('input-error');
            }
        } else {
            // Limpa erros se estiver preenchido
            if (showErrors) {
                if (parent) parent.classList.remove('field-error');
                input.classList.remove('input-error');
                if (itiContainer) itiContainer.classList.remove('input-error');
            }
        }
    });

    return isValid;
}

/* ================= 3. NAVEGAÇÃO E INICIALIZAÇÃO ================= */

function getCorrectInitialStep() {
    const savedStep = parseInt(localStorage.getItem('cadastro_step')) || 1;
    if (savedStep === 1) return 1;

    // Verifica etapas anteriores sem mostrar erro visual (Silent Check)
    for (let i = 1; i < savedStep; i++) {
        if (!validateStep(i, false)) return i; 
    }
    return savedStep;
}

function changeStep(current, next) {
    if (isNavigating) return;

    // BARREIRA: Valida a etapa atual antes de avançar
    if (next > current) {
        if (!validateStep(current, true)) {
            // Scroll para o primeiro erro encontrado
            const errorEl = document.querySelector('.step-content.active .input-error, .step-content.active .field-error');
            if(errorEl) errorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
    }

    isNavigating = true;

    // Salva antes de mudar (Simulando inserção temporária)
    saveProgress(next);

    document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
    const nextContent = document.querySelector(`.step-content[data-step="${next}"]`);
    
    if (nextContent) {
        nextContent.classList.add('active');
        localStorage.setItem('cadastro_step', next);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    setTimeout(() => { isNavigating = false; }, 500);
}

/* ================= 4. DETECÇÃO DE LOCALIZAÇÃO (SUA VERSÃO FUNCIONAL) ================= */

async function detectarLocalizacaoRobusta() {
    const selectPais = document.getElementById('select_pais');
    const regiaoInput = document.getElementById('regiao');
    const localidadeInput = document.getElementById('localidade');

    try {
        const response = await fetch('https://ipapi.co/json/');
        const data = await response.json();
        if (data.country_code) {
            selectPais.value = data.country_code;
            if (data.region) regiaoInput.value = data.region;
            if (data.city) localidadeInput.value = data.city;
            selectPais.dispatchEvent(new Event('change'));
            saveProgress();
            if (data.city) return;
        }
    } catch (e) { console.warn("API 1 falhou..."); }

    try {
        const responseBackup = await fetch('http://ip-api.com/json/?fields=status,countryCode,regionName,city');
        const dataBackup = await responseBackup.json();
        if (dataBackup.status === 'success') {
            if (!selectPais.value) selectPais.value = dataBackup.countryCode;
            if (!regiaoInput.value) regiaoInput.value = dataBackup.regionName;
            if (!localidadeInput.value) localidadeInput.value = dataBackup.city;
            selectPais.dispatchEvent(new Event('change'));
            saveProgress();
        }
    } catch (e) { console.error("Falha total na detecção de localização."); }
}

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

        restoreData(); // Restaura dados salvos

        if (!selectPais.value) {
            await detectarLocalizacaoRobusta();
        }
    } catch (e) {
        console.error("Erro API Países");
        restoreData();
    }
}

/* ================= 5. EVENT LISTENERS ================= */

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Carrega Países e Restaura Dados
    await initCountries();

    // 2. Define a etapa correta (Silent Check) para evitar erro no reload
    const startStep = getCorrectInitialStep();
    document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
    document.querySelector(`.step-content[data-step="${startStep}"]`).classList.add('active');

    // 3. Listener para Labels Fiscais
    const selPais = document.getElementById('select_pais');
    if (selPais) {
        selPais.addEventListener('change', function() {
            const config = {
                'BR': { l: 'CNPJ', p: '00.000.000/0000-00' },
                'AO': { l: 'NUIT', p: '9 dígitos' },
                'PT': { l: 'NIF', p: '9 dígitos' },
                'MZ': { l: 'NUIT', p: '9 dígitos' }
            }[this.value] || { l: 'Tax ID / Registro Fiscal', p: 'Documento oficial' };
            
            document.getElementById('label_fiscal').innerText = config.l;
            document.getElementById('tax_id').placeholder = config.p;
        });
    }

    // 4. Salvar ao digitar e Limpar Erros
    document.querySelectorAll('#formBusiness input, #formBusiness select, #formBusiness textarea').forEach(el => {
        el.addEventListener('input', function() {
            const p = this.closest('.person-field-input');
            const iti = this.closest('.iti');
            
            if(p) p.classList.remove('field-error');
            this.classList.remove('input-error');
            if(iti) iti.classList.remove('input-error');

            saveProgress();
        });
    });

    // 5. BARREIRA FINAL: Interceptar o Submit
    const formBusiness = document.getElementById('formBusiness');
    if (formBusiness) {
        formBusiness.addEventListener('submit', function(e) {
            // Valida a Última Etapa (3) antes de deixar o AJAX processar
            if (!validateStep(3, true)) {
                e.preventDefault();
                e.stopImmediatePropagation(); // Impede o envio para o script de AJAX
                
                // Rola para o erro
                const errorEl = document.querySelector('.step-content[data-step="3"] .input-error');
                if(errorEl) errorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
});

function clearStorage() {
    localStorage.removeItem('cadastro_step');
    localStorage.removeItem('cadastro_data');
}