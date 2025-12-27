window.visionGreenIti = { pessoal: null, business: null };

document.addEventListener("DOMContentLoaded", () => {
    const setupTelefone = (inputSelector, hiddenSelector, type) => {
        const inputEl = document.querySelector(inputSelector);
        const hiddenEl = document.querySelector(hiddenSelector);
        
        if (!inputEl || !hiddenEl) return;

        // Inicializa a biblioteca de forma limpa
        const iti = window.intlTelInput(inputEl, {
            initialCountry: "",      // Começa vazio como solicitado (Estado Zero)
            separateDialCode: true,  // Exibe o código do país (+244) separado
            nationalMode: true,      // Permite escrever o número no formato nacional
            autoPlaceholder: "polite",
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js"
        });

        // Armazena a instância para uso global se necessário
        window.visionGreenIti[type] = iti;

        const dialCodeDisplay = inputEl.parentElement.querySelector(".iti__selected-dial-code");

        // Função para atualizar o valor oculto e a interface
        const atualizar = () => {
            const val = inputEl.value.trim();
            const countryData = iti.getSelectedCountryData();

            // Se o campo estiver vazio e nenhum país tiver sido selecionado manualmente
            if (val.length === 0 && !countryData.iso2) {
                if (dialCodeDisplay) dialCodeDisplay.style.display = "none";
                hiddenEl.value = "";
            } else {
                // Assim que o usuário começa a digitar ou seleciona o país, mostramos o código
                if (dialCodeDisplay) dialCodeDisplay.style.display = "inline-block";
                
                // Salva o número completo (DDI + Número) para o PHP
                hiddenEl.value = iti.getNumber();
            }
        };

        // Escuta mudanças no input e também quando o usuário clica na lista de países
        inputEl.addEventListener('input', atualizar);
        inputEl.addEventListener('countrychange', atualizar);
    };

    // Inicializa para o formulário de Pessoa
    setupTelefone("#formPessoa #telefone_input", "#formPessoa #telefone_final", "pessoal");
    
    // Inicializa para o formulário de Empresa (Business)
    setupTelefone("#formBusiness #telefone_input", "#formBusiness #telefone_final", "business");
});