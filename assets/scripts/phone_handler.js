window.visionGreenIti = { pessoal: null, business: null };

document.addEventListener("DOMContentLoaded", () => {
    const setupTelefone = (inputSelector, hiddenSelector, type) => {
        const inputEl = document.querySelector(inputSelector);
        const hiddenEl = document.querySelector(hiddenSelector);
        
        if (!inputEl || !hiddenEl) {
            console.warn(`VisionGreen: Campos de telefone não encontrados para ${type}`);
            return;
        }

        // Inicializa a biblioteca de forma limpa
        const iti = window.intlTelInput(inputEl, {
            initialCountry: "",      // Começa vazio (Estado Zero)
            separateDialCode: true,  // Exibe o código do país separado
            nationalMode: true,      // Permite escrever no formato nacional
            autoPlaceholder: "polite",
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js"
        });

        // Armazena a instância para uso global (Necessário para o script Unificado)
        window.visionGreenIti[type] = iti;

        const dialCodeDisplay = inputEl.parentElement.querySelector(".iti__selected-dial-code");

        // Função para atualizar o valor oculto
        const atualizar = () => {
            const val = inputEl.value.trim();
            const countryData = iti.getSelectedCountryData();

            // Lógica de visualização do DDI
            if (val.length === 0 && !countryData.iso2) {
                if (dialCodeDisplay) dialCodeDisplay.style.display = "none";
                hiddenEl.value = "";
            } else {
                if (dialCodeDisplay) dialCodeDisplay.style.display = ""; // Reset para padrão
                
                // Salva o número completo (E.164) no input hidden
                hiddenEl.value = iti.getNumber();
            }
        };

        inputEl.addEventListener('input', atualizar);
        inputEl.addEventListener('countrychange', atualizar);
        
        // Dispara uma vez para garantir estado inicial
        atualizar();
    };

    // 1. Inicializa FORM PESSOA (IDs confirmados no seu HTML)
    setupTelefone("#formPessoa #telefone_input", "#formPessoa #telefone_final", "pessoal");
    
    // 2. Inicializa FORM BUSINESS (CORRIGIDO AQUI)
    // O seletor deve apontar para 'tel_business' e o hidden que criamos 'telefone_business_final'
    setupTelefone("#tel_business", "#telefone_business_final", "business");
});