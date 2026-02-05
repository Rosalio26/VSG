/**
 * ================================================================
 * VISION GREEN - SISTEMA DE CADASTRO UNIFICADO COMPLETO
 * Versão Enhanced com Código de País Automático
 * ================================================================
 */

/* ========================================
   MAPEAMENTO DE CÓDIGOS TELEFÔNICOS POR PAÍS
======================================== */

const COUNTRY_PHONE_CODES = {
    // África
    'MZ': '+258',  // Moçambique
    'AO': '+244',  // Angola
    'ZA': '+27',   // África do Sul
    'ZW': '+263',  // Zimbabwe
    'TZ': '+255',  // Tanzânia
    'KE': '+254',  // Quênia
    'UG': '+256',  // Uganda
    'MW': '+265',  // Malawi
    'ZM': '+260',  // Zâmbia
    'NA': '+264',  // Namíbia
    'BW': '+267',  // Botsuana
    'EG': '+20',   // Egito
    'NG': '+234',  // Nigéria
    'GH': '+233',  // Gana
    'ET': '+251',  // Etiópia
    'MA': '+212',  // Marrocos
    'TN': '+216',  // Tunísia
    'DZ': '+213',  // Argélia
    
    // América do Sul
    'BR': '+55',   // Brasil
    'AR': '+54',   // Argentina
    'CL': '+56',   // Chile
    'CO': '+57',   // Colômbia
    'PE': '+51',   // Peru
    'VE': '+58',   // Venezuela
    'EC': '+593',  // Equador
    'UY': '+598',  // Uruguai
    'PY': '+595',  // Paraguai
    'BO': '+591',  // Bolívia
    
    // América do Norte
    'US': '+1',    // Estados Unidos
    'CA': '+1',    // Canadá
    'MX': '+52',   // México
    
    // Europa
    'PT': '+351',  // Portugal
    'ES': '+34',   // Espanha
    'FR': '+33',   // França
    'IT': '+39',   // Itália
    'DE': '+49',   // Alemanha
    'GB': '+44',   // Reino Unido
    'NL': '+31',   // Holanda
    'BE': '+32',   // Bélgica
    'CH': '+41',   // Suíça
    'AT': '+43',   // Áustria
    'SE': '+46',   // Suécia
    'NO': '+47',   // Noruega
    'DK': '+45',   // Dinamarca
    'FI': '+358',  // Finlândia
    'IE': '+353',  // Irlanda
    'PL': '+48',   // Polônia
    'RO': '+40',   // Romênia
    'GR': '+30',   // Grécia
    'RU': '+7',    // Rússia
    
    // Ásia
    'CN': '+86',   // China
    'JP': '+81',   // Japão
    'IN': '+91',   // Índia
    'KR': '+82',   // Coreia do Sul
    'TH': '+66',   // Tailândia
    'VN': '+84',   // Vietnã
    'PH': '+63',   // Filipinas
    'ID': '+62',   // Indonésia
    'MY': '+60',   // Malásia
    'SG': '+65',   // Singapura
    'PK': '+92',   // Paquistão
    'BD': '+880',  // Bangladesh
    'AE': '+971',  // Emirados Árabes
    'SA': '+966',  // Arábia Saudita
    'IL': '+972',  // Israel
    'TR': '+90',   // Turquia
    
    // Oceania
    'AU': '+61',   // Austrália
    'NZ': '+64',   // Nova Zelândia
};

/* ========================================
   FUNÇÃO PARA APLICAR CÓDIGO DE PAÍS AO TELEFONE
======================================== */

function aplicarCodigoPais(countryCode, formularioTipo = 'business') {
    const phoneCode = COUNTRY_PHONE_CODES[countryCode];
    
    if (!phoneCode) {
        console.warn(`⚠️ Código de país não identificado para: ${countryCode}`);
        mostrarAlertaCodigoNaoIdentificado(countryCode, formularioTipo);
        return false;
    }
    
    // Determina qual campo de telefone usar
    const phoneInput = formularioTipo === 'business' 
        ? document.getElementById('tel_business')
        : document.getElementById('telefone_input');
    
    if (!phoneInput) {
        console.error('Campo de telefone não encontrado');
        return false;
    }
    
    // Aplica o código apenas se o campo estiver vazio ou não começar com +
    const currentValue = phoneInput.value.trim();
    
    if (!currentValue || !currentValue.startsWith('+')) {
        phoneInput.value = phoneCode + ' ';
        phoneInput.placeholder = `Ex: ${phoneCode} 84 123 4567`;
        
        // Adiciona classe de sucesso visual
        phoneInput.classList.add('phone-code-applied');
        setTimeout(() => {
            phoneInput.classList.remove('phone-code-applied');
        }, 2000);
        
        console.log(`✅ Código de país aplicado: ${phoneCode} (${countryCode})`);
        
        // Mostra toast de confirmação
        if (typeof showToast === 'function') {
            showToast(`📞 Código de país aplicado: ${phoneCode}`, 'success', 2000);
        }
        
        return true;
    }
    
    return true;
}

/* ========================================
   ALERTA QUANDO CÓDIGO NÃO FOR IDENTIFICADO
======================================== */

function mostrarAlertaCodigoNaoIdentificado(countryCode, formularioTipo) {
    const phoneInput = formularioTipo === 'business' 
        ? document.getElementById('tel_business')
        : document.getElementById('telefone_input');
    
    if (!phoneInput) return;
    
    // Cria alerta visual no campo
    const parent = phoneInput.closest('.person-field-input');
    if (!parent) return;
    
    // Remove alertas anteriores
    parent.querySelectorAll('.codigo-nao-identificado-warning').forEach(el => el.remove());
    
    const warningDiv = document.createElement('div');
    warningDiv.className = 'codigo-nao-identificado-warning';
    warningDiv.style.cssText = `
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-left: 4px solid #f59e0b;
        padding: 12px 16px;
        border-radius: 8px;
        margin-top: 8px;
        animation: slideInDown 0.4s ease-out;
    `;
    
    warningDiv.innerHTML = `
        <div style="display: flex; align-items: start; gap: 12px;">
            <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b; font-size: 20px; margin-top: 2px;"></i>
            <div style="flex: 1;">
                <strong style="display: block; color: #92400e; font-size: 14px; margin-bottom: 4px;">
                    Código de País Não Identificado
                </strong>
                <p style="margin: 0; color: #78350f; font-size: 13px; line-height: 1.5;">
                    O país selecionado (<strong>${countryCode}</strong>) não tem código telefônico cadastrado em nosso sistema.
                    Por favor, insira manualmente o código do país no formato: <strong>+XXX</strong>
                </p>
                <small style="display: block; margin-top: 6px; color: #a16207; font-size: 12px;">
                    <i class="fa-solid fa-lightbulb"></i> Exemplo: +258 para Moçambique, +55 para Brasil
                </small>
            </div>
        </div>
    `;
    
    parent.appendChild(warningDiv);
    
    // Remove o alerta após 10 segundos
    setTimeout(() => {
        warningDiv.style.animation = 'slideOutUp 0.4s ease-out';
        setTimeout(() => warningDiv.remove(), 400);
    }, 10000);
    
    // Adiciona placeholder explicativo
    phoneInput.placeholder = `Digite o código do país (ex: +258) seguido do número`;
    
    // Toast de alerta
    if (typeof showToast === 'function') {
        showToast(`⚠️ Código não identificado para ${countryCode}. Insira manualmente.`, 'warning', 5000);
    }
}

/* ========================================
   VALIDAÇÃO AVANÇADA DE NÚMERO DE TELEFONE
======================================== */

function validarNumeroTelefone(phoneInput, countryCode) {
    const valor = phoneInput.value.trim();
    const phoneCode = COUNTRY_PHONE_CODES[countryCode];
    
    // Verifica se começa com +
    if (!valor.startsWith('+')) {
        return {
            valido: false,
            erro: 'O número deve começar com o código do país (ex: +258)'
        };
    }
    
    // Se temos o código do país, verifica se está correto
    if (phoneCode && !valor.startsWith(phoneCode)) {
        return {
            valido: false,
            erro: `Para ${countryCode}, o código deve ser ${phoneCode}`
        };
    }
    
    // Verifica comprimento mínimo
    if (valor.length < 10) {
        return {
            valido: false,
            erro: 'Número de telefone incompleto'
        };
    }
    
    // Remove espaços e caracteres especiais para contar dígitos
    const apenasNumeros = valor.replace(/[^\d]/g, '');
    
    // Verifica se tem número suficiente de dígitos (mínimo 8 após código do país)
    if (apenasNumeros.length < 8) {
        return {
            valido: false,
            erro: 'Número muito curto. Verifique o número digitado.'
        };
    }
    
    return { valido: true };
}

/* ========================================
   FORMATAÇÃO AUTOMÁTICA DO NÚMERO
======================================== */

function formatarNumeroTelefone(phoneInput, countryCode) {
    let valor = phoneInput.value;
    
    // Remove tudo exceto números e o símbolo +
    valor = valor.replace(/[^\d+]/g, '');
    
    // Garante que começa com +
    if (!valor.startsWith('+')) {
        const phoneCode = COUNTRY_PHONE_CODES[countryCode];
        if (phoneCode && valor.length > 0) {
            valor = phoneCode + valor;
        }
    }
    
    // Adiciona espaços para melhor legibilidade
    // Formato: +XXX XX XXX XXXX
    if (valor.length > 4) {
        const codigo = valor.substring(0, 4); // +XXX
        const resto = valor.substring(4);
        
        let formatado = codigo;
        
        if (resto.length > 0) {
            formatado += ' ' + resto.substring(0, 2);
        }
        if (resto.length > 2) {
            formatado += ' ' + resto.substring(2, 5);
        }
        if (resto.length > 5) {
            formatado += ' ' + resto.substring(5, 9);
        }
        
        phoneInput.value = formatado;
    }
}

/* ========================================
   PARTE 1: VARIÁVEIS GLOBAIS E STORAGE DE ARQUIVOS
======================================== */

const FileStorage = {
    toBase64: (file) => {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    },
    
    save: async (inputId, file) => {
        try {
            if (!file) return;
            
            const maxSize = 4 * 1024 * 1024;
            if (file.size > maxSize) {
                console.warn(`Arquivo ${file.name} muito grande para cache (${(file.size/1024/1024).toFixed(2)}MB)`);
                return;
            }
            
            const base64 = await FileStorage.toBase64(file);
            const fileData = {
                name: file.name,
                type: file.type,
                size: file.size,
                data: base64,
                timestamp: Date.now()
            };
            
            localStorage.setItem(`vg_file_${inputId}`, JSON.stringify(fileData));
            console.log(`✅ Arquivo salvo: ${file.name}`);
            
        } catch (e) {
            console.error('Erro ao salvar arquivo:', e);
        }
    },
    
    restore: async (inputId) => {
        try {
            const stored = localStorage.getItem(`vg_file_${inputId}`);
            if (!stored) return null;
            
            const fileData = JSON.parse(stored);
            
            const age = Date.now() - fileData.timestamp;
            if (age > 24 * 60 * 60 * 1000) {
                localStorage.removeItem(`vg_file_${inputId}`);
                return null;
            }
            
            const response = await fetch(fileData.data);
            const blob = await response.blob();
            const file = new File([blob], fileData.name, { type: fileData.type });
            
            return { file, data: fileData };
            
        } catch (e) {
            console.error('Erro ao restaurar arquivo:', e);
            return null;
        }
    },
    
    remove: (inputId) => {
        localStorage.removeItem(`vg_file_${inputId}`);
    },
    
    clearAll: () => {
        Object.keys(localStorage).forEach(key => {
            if (key.startsWith('vg_file_')) {
                localStorage.removeItem(key);
            }
        });
    }
};

/* ========================================
   PARTE 2: SISTEMA DE CHECKBOXES EXCLUSIVOS
======================================== */

function setupCamposOpcionaisExclusivos() {
    // ===== FORMULÁRIO BUSINESS =====
    const postalCodeFieldBusiness = document.querySelector('#formBusiness [name="postal_code"]')?.closest('.person-field-input');
    const taxIdFieldBusiness = document.querySelector('#formBusiness #tax_id')?.closest('.person-field-input');
    
    if (postalCodeFieldBusiness && taxIdFieldBusiness) {
        const checkboxHTML = `
            <div class="exclusive-field-selector" style="margin-bottom: 20px;">
                <div style="background: #f6f8fa; padding: 16px; border-radius: 6px; border: 1px solid #d0d7de;">
                    <div style="margin-bottom: 12px;">
                        <strong style="font-size: 14px; color: #24292f; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-circle-check" style="color: #00d084;"></i>
                            Selecione qual documento você possui:
                        </strong>
                        <small style="display: block; margin-top: 4px; color: #586069;">
                            Marque apenas a opção correspondente ao documento que você tem disponível
                        </small>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <label class="exclusive-option" style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px; border-radius: 4px; transition: all 0.2s ease; border: 1px solid transparent;">
                            <input type="checkbox" id="has_postal_code" name="field_option" value="postal" style="width: 18px; height: 18px; accent-color: #00d084; cursor: pointer;">
                            <div style="flex: 1;">
                                <span style="font-size: 14px; color: #24292f; font-weight: 500; display: flex; align-items: center; gap: 6px;">
                                    <i class="fa-solid fa-location-dot"></i>
                                    Tenho Código Postal (CEP)
                                </span>
                                <small style="display: block; color: #586069; margin-top: 2px;">
                                    Mantém o campo de Código Postal ativo
                                </small>
                            </div>
                        </label>
                        
                        <label class="exclusive-option" style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px; border-radius: 4px; transition: all 0.2s ease; border: 1px solid transparent;">
                            <input type="checkbox" id="has_tax_id_only" name="field_option" value="tax" style="width: 18px; height: 18px; accent-color: #5865f2; cursor: pointer;">
                            <div style="flex: 1;">
                                <span style="font-size: 14px; color: #24292f; font-weight: 500; display: flex; align-items: center; gap: 6px;">
                                    <i class="fa-solid fa-file-invoice"></i>
                                    Tenho apenas Documento Fiscal (NUIT/NIF)
                                </span>
                                <small style="display: block; color: #586069; margin-top: 2px;">
                                    Mantém o campo de Documento Fiscal ativo
                                </small>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        `;
        
        postalCodeFieldBusiness.insertAdjacentHTML('beforebegin', checkboxHTML);
        
        const postalCheckbox = document.getElementById('has_postal_code');
        const taxCheckbox = document.getElementById('has_tax_id_only');
        const postalInput = document.querySelector('#formBusiness [name="postal_code"]');
        const taxIdInput = document.getElementById('tax_id');
        const taxIdFile = document.getElementById('tax_id_file');
        const taxIdFileArea = document.getElementById('area_tax_file');
        const postalLabel = postalCodeFieldBusiness.querySelector('label');
        const taxLabel = taxIdFieldBusiness.querySelector('label');
        
        function atualizarEstadosCampos() {
            const postalAtivo = postalCheckbox.checked;
            const taxAtivo = taxCheckbox.checked;
            
            if (postalAtivo) {
                postalInput.disabled = false;
                postalInput.required = true;
                postalInput.style.opacity = '1';
                postalInput.style.cursor = 'text';
                postalInput.placeholder = 'Digite o código postal';
                postalLabel.style.opacity = '1';
                postalCodeFieldBusiness.style.opacity = '1';
                
                taxIdInput.disabled = true;
                taxIdInput.required = false;
                taxIdInput.value = '';
                taxIdInput.style.opacity = '0.5';
                taxIdInput.style.cursor = 'not-allowed';
                taxIdInput.placeholder = 'Campo desabilitado (CEP selecionado)';
                taxLabel.style.opacity = '0.5';
                taxIdFieldBusiness.style.opacity = '0.5';
                
                if (taxIdFile) {
                    taxIdFile.disabled = true;
                    taxIdFile.required = false;
                    taxIdFile.value = '';
                }
                if (taxIdFileArea) {
                    taxIdFileArea.style.opacity = '0.5';
                    taxIdFileArea.style.pointerEvents = 'none';
                }
                
                postalCheckbox.parentElement.parentElement.style.background = 'rgba(0, 208, 132, 0.1)';
                postalCheckbox.parentElement.parentElement.style.borderColor = '#00d084';
                taxCheckbox.parentElement.parentElement.style.background = 'transparent';
                taxCheckbox.parentElement.parentElement.style.borderColor = 'transparent';
            }
            else if (taxAtivo) {
                postalInput.disabled = true;
                postalInput.required = false;
                postalInput.value = '';
                postalInput.style.opacity = '0.5';
                postalInput.style.cursor = 'not-allowed';
                postalInput.placeholder = 'Campo desabilitado (Documento Fiscal selecionado)';
                postalLabel.style.opacity = '0.5';
                postalCodeFieldBusiness.style.opacity = '0.5';
                
                const fiscalMode = document.querySelector('[name="fiscal_mode"]:checked')?.value || 'text';
                if (fiscalMode === 'text') {
                    taxIdInput.disabled = false;
                    taxIdInput.required = true;
                    taxIdInput.style.opacity = '1';
                    taxIdInput.style.cursor = 'text';
                    taxIdInput.placeholder = 'Digite o CNPJ / NUIT / NIF';
                } else {
                    taxIdInput.disabled = true;
                    if (taxIdFile) {
                        taxIdFile.disabled = false;
                        taxIdFile.required = true;
                    }
                    if (taxIdFileArea) {
                        taxIdFileArea.style.opacity = '1';
                        taxIdFileArea.style.pointerEvents = 'auto';
                    }
                }
                taxLabel.style.opacity = '1';
                taxIdFieldBusiness.style.opacity = '1';
                
                taxCheckbox.parentElement.parentElement.style.background = 'rgba(88, 101, 242, 0.1)';
                taxCheckbox.parentElement.parentElement.style.borderColor = '#5865f2';
                postalCheckbox.parentElement.parentElement.style.background = 'transparent';
                postalCheckbox.parentElement.parentElement.style.borderColor = 'transparent';
            }
            else {
                postalInput.disabled = false;
                postalInput.required = false;
                postalInput.style.opacity = '1';
                postalInput.style.cursor = 'text';
                postalInput.placeholder = 'Digite o código postal (opcional)';
                postalLabel.style.opacity = '1';
                postalCodeFieldBusiness.style.opacity = '1';
                
                const fiscalMode = document.querySelector('[name="fiscal_mode"]:checked')?.value || 'text';
                taxIdInput.disabled = false;
                taxIdInput.required = true;
                taxIdInput.style.opacity = '1';
                taxIdInput.style.cursor = 'text';
                taxIdInput.placeholder = 'Digite o CNPJ / NUIT / NIF';
                taxLabel.style.opacity = '1';
                taxIdFieldBusiness.style.opacity = '1';
                
                postalCheckbox.parentElement.parentElement.style.background = 'transparent';
                postalCheckbox.parentElement.parentElement.style.borderColor = 'transparent';
                taxCheckbox.parentElement.parentElement.style.background = 'transparent';
                taxCheckbox.parentElement.parentElement.style.borderColor = 'transparent';
            }
            
            if (typeof salvarProgresso === 'function') {
                salvarProgresso();
            }
        }
        
        postalCheckbox.addEventListener('change', function() {
            if (this.checked) {
                taxCheckbox.checked = false;
            }
            atualizarEstadosCampos();
        });
        
        taxCheckbox.addEventListener('change', function() {
            if (this.checked) {
                postalCheckbox.checked = false;
            }
            atualizarEstadosCampos();
        });
        
        document.querySelectorAll('[name="fiscal_mode"]').forEach(radio => {
            radio.addEventListener('change', atualizarEstadosCampos);
        });
        
        const savedData = JSON.parse(localStorage.getItem('vg_data') || '{}');
        if (savedData.field_option === 'postal') {
            postalCheckbox.checked = true;
        } else if (savedData.field_option === 'tax') {
            taxCheckbox.checked = true;
        }
        
        atualizarEstadosCampos();
    }
    
    // ===== FORMULÁRIO PESSOA =====
    const postalCodeFieldPessoa = document.querySelector('#formPessoa [name="postal_code"]')?.closest('.person-field-input');
    
    if (postalCodeFieldPessoa) {
        const checkboxHTMLPessoa = `
            <div class="exclusive-field-selector-pessoa" style="margin-bottom: 20px;">
                <div style="background: #f6f8fa; padding: 12px; border-radius: 6px; border: 1px solid #d0d7de;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                        <input type="checkbox" id="no_postal_code_pessoa" style="width: 18px; height: 18px; accent-color: #00d084; cursor: pointer;">
                        <span style="font-size: 14px; color: #24292f; font-weight: 500;">
                            <i class="fa-solid fa-location-dot"></i>
                            Não sei meu código postal
                        </span>
                    </label>
                    <small style="display: block; margin-top: 6px; color: #586069; padding-left: 26px;">
                        Marque se não souber o CEP/Código Postal da sua região
                    </small>
                </div>
            </div>
        `;
        
        postalCodeFieldPessoa.insertAdjacentHTML('beforebegin', checkboxHTMLPessoa);
        
        const checkbox = document.getElementById('no_postal_code_pessoa');
        const postalInput = document.getElementById('postal_code_input_pessoa');
        const postalLabel = postalCodeFieldPessoa.querySelector('label');
        
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                postalInput.disabled = true;
                postalInput.required = false;
                postalInput.value = '';
                postalInput.style.opacity = '0.5';
                postalInput.style.cursor = 'not-allowed';
                postalInput.placeholder = 'Campo opcional (não informado)';
                postalLabel.style.opacity = '0.5';
                postalCodeFieldPessoa.style.opacity = '0.5';
            } else {
                postalInput.disabled = false;
                postalInput.required = false;
                postalInput.style.opacity = '1';
                postalInput.style.cursor = 'text';
                postalInput.placeholder = 'Digite o código postal';
                postalLabel.style.opacity = '1';
                postalCodeFieldPessoa.style.opacity = '1';
            }
            
            if (typeof salvarProgresso === 'function') {
                salvarProgresso();
            }
        });
        
        const savedData = JSON.parse(localStorage.getItem('vg_data') || '{}');
        if (savedData.no_postal_code_pessoa === 'on' || savedData.no_postal_code_pessoa === true) {
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change'));
        }
    }
}

/* ========================================
   ESTILOS DINÂMICOS
======================================== */

function adicionarEstilosCheckboxExclusivo() {
    const style = document.createElement('style');
    style.id = 'exclusive-checkbox-styles';
    style.textContent = `
        .exclusive-option:hover {
            background: rgba(0, 0, 0, 0.02) !important;
            border-color: #d0d7de !important;
        }
        
        .exclusive-option input:checked + div span {
            font-weight: 600;
        }
        
        .exclusive-field-selector {
            animation: fadeInDown 0.4s ease-out;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideOutUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
        
        .phone-code-applied {
            animation: pulseGreen 0.6s ease-out;
            border-color: #10b981 !important;
        }
        
        @keyframes pulseGreen {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
            }
        }
    `;
    
    const oldStyle = document.getElementById('exclusive-checkbox-styles');
    if (oldStyle) oldStyle.remove();
    
    document.head.appendChild(style);
}

/* ========================================
   SETUP DE FILE INPUTS
======================================== */

function setupFileInputs() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', async function() {
            const file = this.files[0];
            if (file) {
                await FileStorage.save(this.id || this.name, file);
                
                if (typeof updateFileName === 'function') {
                    updateFileName(this);
                }
            }
        });
    });
}

/* ========================================
   RESTAURAÇÃO DE ARQUIVOS
======================================== */

async function restaurarArquivos() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    for (const input of fileInputs) {
        const inputId = input.id || input.name;
        const restored = await FileStorage.restore(inputId);
        
        if (restored) {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(restored.file);
            input.files = dataTransfer.files;
            
            const display = input.parentElement.querySelector('.file-selected-name');
            if (display) {
                const fileSize = (restored.data.size / 1024 / 1024).toFixed(2);
                display.innerHTML = `<i class="fa-solid fa-check-circle"></i> ${restored.data.name} (${fileSize} MB) <span style="color: #00d084;">✓ Restaurado</span>`;
            }
            
            console.log(`✅ Arquivo restaurado: ${restored.data.name}`);
        }
    }
}

/* ========================================
   CONTINUA NO PRÓXIMO ARQUIVO...
   (O restante do código permanece o mesmo do original)
======================================== */