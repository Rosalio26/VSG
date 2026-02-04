/**
 * ================================================================
 * VISION GREEN - SISTEMA DE CADASTRO UNIFICADO COMPLETO
 * Integração dos 3 scripts: Ultimate V4.0 + Checkboxes Exclusivos + Melhorias
 * ================================================================
 */

/* ========================================
   PARTE 1: VARIÁVEIS GLOBAIS E STORAGE DE ARQUIVOS
   (De melhorias-cadastro.js)
======================================== */

// Armazena arquivos em Base64 no localStorage
const FileStorage = {
    // Converte arquivo para Base64
    toBase64: (file) => {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    },
    
    // Salva arquivo no localStorage
    save: async (inputId, file) => {
        try {
            if (!file) return;
            
            // Verifica tamanho (limite de 4MB para localStorage)
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
    
    // Restaura arquivo do localStorage
    restore: async (inputId) => {
        try {
            const stored = localStorage.getItem(`vg_file_${inputId}`);
            if (!stored) return null;
            
            const fileData = JSON.parse(stored);
            
            // Verifica se não expirou (24 horas)
            const age = Date.now() - fileData.timestamp;
            if (age > 24 * 60 * 60 * 1000) {
                localStorage.removeItem(`vg_file_${inputId}`);
                return null;
            }
            
            // Converte Base64 de volta para File
            const response = await fetch(fileData.data);
            const blob = await response.blob();
            const file = new File([blob], fileData.name, { type: fileData.type });
            
            return { file, data: fileData };
            
        } catch (e) {
            console.error('Erro ao restaurar arquivo:', e);
            return null;
        }
    },
    
    // Remove arquivo do localStorage
    remove: (inputId) => {
        localStorage.removeItem(`vg_file_${inputId}`);
    },
    
    // Limpa todos os arquivos
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
   (De melhorias-checkbox-exclusivo.js)
======================================== */

function setupCamposOpcionaisExclusivos() {
    // ===== FORMULÁRIO BUSINESS =====
    const postalCodeFieldBusiness = document.querySelector('#formBusiness [name="postal_code"]')?.closest('.person-field-input');
    const taxIdFieldBusiness = document.querySelector('#formBusiness #tax_id')?.closest('.person-field-input');
    
    if (postalCodeFieldBusiness && taxIdFieldBusiness) {
        // HTML dos checkboxes exclusivos
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
        
        // Insere antes do campo de código postal
        postalCodeFieldBusiness.insertAdjacentHTML('beforebegin', checkboxHTML);
        
        // Referências aos elementos
        const postalCheckbox = document.getElementById('has_postal_code');
        const taxCheckbox = document.getElementById('has_tax_id_only');
        const postalInput = document.querySelector('#formBusiness [name="postal_code"]');
        const taxIdInput = document.getElementById('tax_id');
        const taxIdFile = document.getElementById('tax_id_file');
        const taxIdFileArea = document.getElementById('area_tax_file');
        const postalLabel = postalCodeFieldBusiness.querySelector('label');
        const taxLabel = taxIdFieldBusiness.querySelector('label');
        
        // Função para atualizar estados
        function atualizarEstadosCampos() {
            const postalAtivo = postalCheckbox.checked;
            const taxAtivo = taxCheckbox.checked;
            
            // Se CEP está ativo
            if (postalAtivo) {
                // Ativa CEP
                postalInput.disabled = false;
                postalInput.required = true;
                postalInput.style.opacity = '1';
                postalInput.style.cursor = 'text';
                postalInput.placeholder = 'Digite o código postal';
                postalLabel.style.opacity = '1';
                postalCodeFieldBusiness.style.opacity = '1';
                
                // Desativa NUIT
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
                
                // Visual do checkbox
                postalCheckbox.parentElement.parentElement.style.background = 'rgba(0, 208, 132, 0.1)';
                postalCheckbox.parentElement.parentElement.style.borderColor = '#00d084';
                taxCheckbox.parentElement.parentElement.style.background = 'transparent';
                taxCheckbox.parentElement.parentElement.style.borderColor = 'transparent';
            }
            // Se NUIT está ativo
            else if (taxAtivo) {
                // Desativa CEP
                postalInput.disabled = true;
                postalInput.required = false;
                postalInput.value = '';
                postalInput.style.opacity = '0.5';
                postalInput.style.cursor = 'not-allowed';
                postalInput.placeholder = 'Campo desabilitado (Documento Fiscal selecionado)';
                postalLabel.style.opacity = '0.5';
                postalCodeFieldBusiness.style.opacity = '0.5';
                
                // Ativa NUIT
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
                
                // Visual do checkbox
                taxCheckbox.parentElement.parentElement.style.background = 'rgba(88, 101, 242, 0.1)';
                taxCheckbox.parentElement.parentElement.style.borderColor = '#5865f2';
                postalCheckbox.parentElement.parentElement.style.background = 'transparent';
                postalCheckbox.parentElement.parentElement.style.borderColor = 'transparent';
            }
            // Se nenhum está ativo (ambos podem ser usados)
            else {
                // Ativa ambos
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
                
                // Remove destaque visual
                postalCheckbox.parentElement.parentElement.style.background = 'transparent';
                postalCheckbox.parentElement.parentElement.style.borderColor = 'transparent';
                taxCheckbox.parentElement.parentElement.style.background = 'transparent';
                taxCheckbox.parentElement.parentElement.style.borderColor = 'transparent';
            }
            
            // Salva estado
            if (typeof salvarProgresso === 'function') {
                salvarProgresso();
            }
        }
        
        // Listeners dos checkboxes (exclusivos)
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
        
        // Listener para mudança de modo fiscal
        document.querySelectorAll('[name="fiscal_mode"]').forEach(radio => {
            radio.addEventListener('change', atualizarEstadosCampos);
        });
        
        // Restaura estado salvo
        const savedData = JSON.parse(localStorage.getItem('vg_data') || '{}');
        if (savedData.field_option === 'postal') {
            postalCheckbox.checked = true;
        } else if (savedData.field_option === 'tax') {
            taxCheckbox.checked = true;
        }
        
        // Aplica estado inicial
        atualizarEstadosCampos();
    }
    
    // ===== FORMULÁRIO PESSOA (Similar, mas mais simples) =====
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
        
        // Restaura estado
        const savedData = JSON.parse(localStorage.getItem('vg_data') || '{}');
        if (savedData.no_postal_code_pessoa === 'on' || savedData.no_postal_code_pessoa === true) {
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change'));
        }
    }
}

/* ========================================
   ESTILOS DINÂMICOS PARA CHECKBOXES
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
        
        /* Tooltip de ajuda */
        .field-info-tooltip {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            background: #d0d7de;
            color: white;
            border-radius: 50%;
            font-size: 11px;
            font-weight: 700;
            cursor: help;
            margin-left: 4px;
        }
        
        .field-info-tooltip:hover {
            background: #00d084;
        }
        
        body[data-tipo-ativo="business"] .field-info-tooltip:hover {
            background: #5865f2;
        }
    `;
    
    // Remove estilo anterior se existir
    const oldStyle = document.getElementById('exclusive-checkbox-styles');
    if (oldStyle) oldStyle.remove();
    
    document.head.appendChild(style);
}

/* ========================================
   PARTE 3: AUTO-SAVE DE ARQUIVOS AO SELECIONAR
   (De melhorias-cadastro.js)
======================================== */

function setupFileInputs() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', async function() {
            const file = this.files[0];
            if (file) {
                // Salva arquivo
                await FileStorage.save(this.id || this.name, file);
                
                // Atualiza display
                if (typeof updateFileName === 'function') {
                    updateFileName(this);
                }
            }
        });
    });
}

/* ========================================
   RESTAURAÇÃO DE ARQUIVOS AO CARREGAR
======================================== */

async function restaurarArquivos() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    for (const input of fileInputs) {
        const inputId = input.id || input.name;
        const restored = await FileStorage.restore(inputId);
        
        if (restored) {
            // Cria DataTransfer para adicionar arquivo ao input
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(restored.file);
            input.files = dataTransfer.files;
            
            // Atualiza display
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
   PARTE 4: SISTEMA PRINCIPAL DE CADASTRO
   (De painel_cadastro_ultimate.js)
======================================== */

document.addEventListener("DOMContentLoaded", async () => {
    
    /* ========================================
       VARIÁVEIS GLOBAIS E DETECÇÃO DE MODO
    ======================================== */
    
    const formPessoa = document.getElementById("formPessoa");
    const formBusiness = document.getElementById("formBusiness");
    const titulo = document.getElementById("titulo");
    const switchConta = document.getElementById("switchConta");
    const csrfToken = document.body.dataset.csrf;
    
    // Detecta se está em modo mobile (apenas pessoa permitida)
    const tiposPermitidosStr = document.body.dataset.tiposPermitidos || '["pessoal"]';
    const tiposPermitidos = JSON.parse(tiposPermitidosStr);
    const isMobileMode = tiposPermitidos.length === 1 && tiposPermitidos[0] === 'pessoal';
    
    let tipoAtual = localStorage.getItem('vg_type') || document.body.dataset.tipoInicial || 'pessoal';
    
    // Em mobile, força o tipo para "pessoal"
    if (isMobileMode) {
        tipoAtual = 'pessoal';
        localStorage.setItem('vg_type', 'pessoal');
    }
    
    let currentStep = 1;
    let isNavigating = false;

    console.log('📱 Modo:', isMobileMode ? 'MOBILE (apenas pessoa)' : 'DESKTOP (ambos)');
    console.log('🎯 Tipo atual:', tipoAtual);

    /* ========================================
       FUNÇÃO DE LIMPEZA TOTAL
    ======================================== */
    
    function limparTodosDados() {
        localStorage.removeItem('vg_data');
        localStorage.removeItem('vg_type');
        localStorage.removeItem('vg_step');
        
        // Limpa arquivos em cache
        FileStorage.clearAll();
        
        // Limpa formulário business (se existir)
        if (formBusiness) {
            formBusiness.reset();
            formBusiness.querySelectorAll('input, select, textarea').forEach(el => {
                el.value = '';
                if (el.type === 'checkbox' || el.type === 'radio') {
                    el.checked = false;
                }
                if (el.type === 'file') {
                    el.value = '';
                    const display = el.parentElement?.querySelector('.file-selected-name');
                    if (display) display.textContent = '';
                }
            });
        }
        
        // Limpa formulário pessoa (sempre existe)
        if (formPessoa) {
            formPessoa.reset();
            formPessoa.querySelectorAll('input, select, textarea').forEach(el => {
                el.value = '';
                if (el.type === 'checkbox' || el.type === 'radio') {
                    el.checked = false;
                }
                if (el.type === 'file') {
                    el.value = '';
                    const display = el.parentElement?.querySelector('.file-selected-name');
                    if (display) display.textContent = '';
                }
            });
        }
        
        // Limpa indicadores visuais
        const strengthBars = document.querySelectorAll('[id^="strengthBar"]');
        const strengthTexts = document.querySelectorAll('[id^="strengthText"]');
        
        strengthBars.forEach(bar => {
            bar.style.width = '0%';
            bar.style.backgroundColor = '#e5e7eb';
        });
        
        strengthTexts.forEach(text => {
            text.textContent = '';
        });
        
        document.querySelectorAll('.file-selected-name').forEach(el => {
            el.textContent = '';
        });
        
        document.querySelectorAll('.error-msg-visual').forEach(el => el.remove());
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        
        // Reset de steps (apenas para business)
        if (formBusiness) {
            document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
            const primeiroStep = document.querySelector('.step-content[data-step="1"]');
            if (primeiroStep) {
                primeiroStep.classList.add('active');
            }
        }
        currentStep = 1;
        
        console.log('🧹 Dados limpos completamente');
    }

    /* ========================================
       SISTEMA DE TOASTS (NOTIFICAÇÕES)
    ======================================== */
    
    function showToast(mensagem, tipo = 'info', duracao = 3000) {
        const toast = document.createElement('div');
        const cores = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };
        
        const icones = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${cores[tipo]};
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            z-index: 10000;
            font-size: 14px;
            font-weight: 600;
            animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            max-width: 350px;
            display: flex;
            align-items: center;
            gap: 12px;
        `;
        
        toast.innerHTML = `
            <i class="fa-solid ${icones[tipo]}" style="font-size: 20px;"></i>
            <span>${mensagem}</span>
        `;
        
        document.body.appendChild(toast);
        
        if (duracao > 0) {
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                setTimeout(() => toast.remove(), 400);
            }, duracao);
        }
        
        return toast;
    }

    /* ========================================
       DETECÇÃO AUTOMÁTICA DE LOCALIZAÇÃO
    ======================================== */
    
    async function detectarLocalizacaoCompleta(mostrarToast = false) {
        try {
            const res1 = await fetch('https://ipapi.co/json/', { timeout: 5000 });
            const data1 = await res1.json();
            
            if (data1.country_code && !data1.error) {
                preencherLocalizacao({
                    country: data1.country_code,
                    state: data1.region,
                    city: data1.city,
                    postal: data1.postal,
                    lat: data1.latitude,
                    lon: data1.longitude
                });
                
                console.log('✅ Localização detectada via ipapi.co');
                return true;
            }
        } catch (e) {
            console.warn('API 1 (ipapi.co) falhou:', e);
        }

        try {
            const res2 = await fetch('http://ip-api.com/json/?fields=status,countryCode,regionName,city,zip,lat,lon');
            const data2 = await res2.json();
            
            if (data2.status === 'success') {
                preencherLocalizacao({
                    country: data2.countryCode,
                    state: data2.regionName,
                    city: data2.city,
                    postal: data2.zip,
                    lat: data2.lat,
                    lon: data2.lon
                });
                
                console.log('✅ Localização detectada via ip-api.com');
                return true;
            }
        } catch (e) {
            console.warn('API 2 (ip-api.com) falhou:', e);
        }

        console.warn('⚠️ Localização automática não disponível');
        return false;
    }

    function preencherLocalizacao(data) {
        // Detecta qual formulário está ativo
        const isBusinessAtivo = formBusiness && !formBusiness.hidden;
        const prefix = isBusinessAtivo ? '' : '_pessoa';
        
        const selectPais = document.getElementById(`select_pais${prefix}`);
        const stateInput = document.getElementById(`state_input${prefix}`);
        const cityInput = document.getElementById(`city_input${prefix}`);
        const postalInput = document.getElementById(`postal_code_input${prefix}`);
        const latInput = document.getElementById(`latitude_input${prefix}`);
        const lonInput = document.getElementById(`longitude_input${prefix}`);
        const countryCodeInput = document.getElementById(`country_code_input${prefix}`);
        
        if (selectPais && data.country) {
            selectPais.value = data.country;
            selectPais.dispatchEvent(new Event('change'));
        }
        
        if (stateInput && data.state) {
            stateInput.value = data.state;
            stateInput.dispatchEvent(new Event('input'));
        }
        
        if (cityInput && data.city) {
            cityInput.value = data.city;
            cityInput.dispatchEvent(new Event('input'));
        }
        
        if (postalInput && data.postal) {
            postalInput.value = data.postal;
            postalInput.dispatchEvent(new Event('input'));
        }
        
        if (latInput && data.lat) latInput.value = data.lat;
        if (lonInput && data.lon) lonInput.value = data.lon;
        if (countryCodeInput && data.country) countryCodeInput.value = data.country;
        
        salvarProgresso();
        
        console.log('✅ Localização preenchida:', data);
    }

    /* ========================================
       DETECÇÃO MANUAL (BOTÕES)
    ======================================== */
    
    window.detectarLocalizacaoManual = async function() {
        await detectarLocalizacaoCompleta(false);
    };

    window.detectarLocalizacaoManualPessoa = async function() {
        await detectarLocalizacaoCompleta(false);
    };

    /* ========================================
       CARREGAMENTO DE PAÍSES
    ======================================== */
    
    async function carregarPaises() {
        const selectsBusiness = document.getElementById('select_pais');
        const selectsPessoa = document.getElementById('select_pais_pessoa');
        
        if (selectsBusiness) selectsBusiness.innerHTML = '<option value="">Carregando países...</option>';
        if (selectsPessoa) selectsPessoa.innerHTML = '<option value="">Carregando países...</option>';
        
        try {
            const res = await fetch('https://restcountries.com/v3.1/all?fields=name,cca2,translations');
            const paises = await res.json();
            
            paises.sort((a, b) => {
                const nomeA = a.translations?.por?.common || a.name.common;
                const nomeB = b.translations?.por?.common || b.name.common;
                return nomeA.localeCompare(nomeB, 'pt');
            });
            
            let options = '<option value="">Selecione o país...</option>';
            paises.forEach(p => {
                const nome = p.translations?.por?.common || p.name.common;
                options += `<option value="${p.cca2}">${nome}</option>`;
            });
            
            if (selectsBusiness) selectsBusiness.innerHTML = options;
            if (selectsPessoa) selectsPessoa.innerHTML = options;
            
            console.log('✅ Países carregados:', paises.length);
            
            // Restaura progresso APÓS carregar países
            restaurarProgresso();
            
            // Detecta localização se não houver país selecionado
            const paisAtual = selectsBusiness?.value || selectsPessoa?.value;
            if (!paisAtual) {
                setTimeout(() => {
                    detectarLocalizacaoCompleta(false);
                }, 500);
            }
            
        } catch (e) {
            console.error('Erro ao carregar países:', e);
            
            const optionsBasic = `
                <option value="">Selecione o país...</option>
                <option value="MZ">Moçambique</option>
                <option value="BR">Brasil</option>
                <option value="PT">Portugal</option>
                <option value="AO">Angola</option>
                <option value="ZA">África do Sul</option>
            `;
            if (selectsBusiness) selectsBusiness.innerHTML = optionsBasic;
            if (selectsPessoa) selectsPessoa.innerHTML = optionsBasic;
        }
    }

    /* ========================================
       VALIDAÇÃO RIGOROSA POR STEP
    ======================================== */
    
    function validarStep(stepNum) {
        const container = document.querySelector(`.step-content[data-step="${stepNum}"]`);
        if (!container) return true;
        
        limparErros(container);
        
        const campos = container.querySelectorAll('input[required], select[required], textarea[required]');
        let valido = true;
        let primeiroErro = null;
        
        campos.forEach(campo => {
            if (campo.offsetParent === null) return;
            
            if (campo.name === 'logo') {
                const noLogo = document.querySelector('input[name="no_logo"]');
                if (noLogo && noLogo.checked) return;
            }
            
            const valor = campo.value.trim();
            let erro = null;
            
            if (campo.type === 'file') {
                if (campo.files.length === 0) {
                    erro = 'Por favor, selecione um arquivo.';
                } else {
                    const arquivo = campo.files[0];
                    const ext = arquivo.name.split('.').pop().toLowerCase();
                    const permitidos = ['png', 'jpg', 'jpeg', 'pdf'];
                    const maxSize = 5 * 1024 * 1024;
                    
                    if (!permitidos.includes(ext)) {
                        erro = 'Formato inválido. Use PNG, JPEG ou PDF.';
                    } else if (arquivo.size > maxSize) {
                        erro = 'Arquivo muito grande (máx. 5MB).';
                    }
                }
            }
            else if (campo.type === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!valor) {
                    erro = 'E-mail é obrigatório.';
                } else if (!emailRegex.test(valor)) {
                    erro = 'E-mail inválido.';
                }
            }
            else if (campo.type === 'tel') {
                if (!valor) {
                    erro = 'Telefone é obrigatório.';
                } else if (!valor.startsWith('+')) {
                    erro = 'Inclua o código do país (ex: +258).';
                } else if (valor.length < 10) {
                    erro = 'Número de telefone incompleto.';
                }
            }
            else if (campo.type === 'password') {
                if (!valor) {
                    erro = 'Senha é obrigatória.';
                } else if (valor.length < 8) {
                    erro = 'Senha deve ter no mínimo 8 caracteres.';
                }
            }
            else if (campo.name === 'password_confirm') {
                const senhaOriginal = container.querySelector('input[name="password"]')?.value;
                if (!valor) {
                    erro = 'Confirme sua senha.';
                } else if (valor !== senhaOriginal) {
                    erro = 'As senhas não coincidem.';
                }
            }
            else if (campo.tagName === 'SELECT') {
                if (!valor || valor === 'selet') {
                    erro = 'Selecione uma opção válida.';
                }
            }
            else if (!valor) {
                erro = 'Este campo é obrigatório.';
            }
            
            if (erro) {
                valido = false;
                if (!primeiroErro) primeiroErro = campo;
                mostrarErro(campo, erro);
            }
        });
        
        if (primeiroErro) {
            primeiroErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => primeiroErro.focus(), 300);
        }
        
        return valido;
    }

    function mostrarErro(campo, mensagem) {
        const parent = campo.closest('.person-field-input');
        if (!parent) return;
        
        if (campo.type === 'file') {
            campo.closest('.custom-file-upload')?.classList.add('input-error');
        } else {
            campo.classList.add('input-error');
        }
        
        const span = document.createElement('span');
        span.className = 'error-msg-visual';
        span.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${mensagem}`;
        parent.appendChild(span);
    }

    function limparErros(container) {
        container.querySelectorAll('.error-msg-visual').forEach(el => el.remove());
        container.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    }

    /* ========================================
       NAVEGAÇÃO ENTRE STEPS (APENAS BUSINESS)
    ======================================== */
    
    window.changeStep = function(atual, proximo) {
        if (isNavigating) return;
        
        if (proximo > atual && !validarStep(atual)) {
            showToast('⚠️ Preencha todos os campos obrigatórios corretamente', 'warning');
            return;
        }
        
        isNavigating = true;
        
        document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
        
        const proximoStep = document.querySelector(`.step-content[data-step="${proximo}"]`);
        
        if (proximoStep) {
            proximoStep.classList.add('active');
            currentStep = proximo;
            localStorage.setItem('vg_step', proximo);
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            if (proximo === 4) sincronizarEmail();
            
            salvarProgresso();
        }
        
        setTimeout(() => { isNavigating = false; }, 500);
    };

    function sincronizarEmail() {
        const emailInput = document.getElementById('email_business');
        const displayInput = document.getElementById('email_confirm_display');
        if (emailInput && displayInput) {
            displayInput.value = emailInput.value;
        }
    }

    /* ========================================
       PERSISTÊNCIA DE DADOS (LocalStorage)
    ======================================== */
    
    function salvarProgresso() {
        const dados = {};
        
        document.querySelectorAll('input:not([type="password"]):not([type="file"]), select, textarea').forEach(el => {
            if (el.name || el.id) {
                if (el.type === 'radio' || el.type === 'checkbox') {
                    if (el.checked) dados[el.name || el.id] = el.value;
                } else {
                    dados[el.name || el.id] = el.value;
                }
            }
        });
        
        // Salva estado dos checkboxes exclusivos
        const postalCheckbox = document.getElementById('has_postal_code');
        const taxCheckbox = document.getElementById('has_tax_id_only');
        const postalCheckboxPessoa = document.getElementById('no_postal_code_pessoa');
        
        if (postalCheckbox) {
            dados.field_option = postalCheckbox.checked ? 'postal' : (taxCheckbox?.checked ? 'tax' : null);
        }
        
        if (postalCheckboxPessoa) {
            dados.no_postal_code_pessoa = postalCheckboxPessoa.checked;
        }
        
        localStorage.setItem('vg_data', JSON.stringify(dados));
        localStorage.setItem('vg_type', tipoAtual);
        localStorage.setItem('vg_step', currentStep);
    }

    function restaurarProgresso() {
        const dados = JSON.parse(localStorage.getItem('vg_data') || '{}');
        const stepSalvo = parseInt(localStorage.getItem('vg_step')) || 1;
        
        Object.keys(dados).forEach(key => {
            const campos = document.querySelectorAll(`[name="${key}"], #${key}`);
            campos.forEach(el => {
                if (el.type === 'radio') {
                    if (el.value === dados[key]) el.checked = true;
                } else if (el.type === 'checkbox') {
                    el.checked = (dados[key] === 'on' || dados[key] === true || dados[key] === el.value);
                } else {
                    el.value = dados[key];
                    el.dispatchEvent(new Event('input'));
                }
            });
        });
        
        // Só restaura step se formBusiness existir E não estiver hidden
        if (formBusiness && !isMobileMode && stepSalvo > 1) {
            document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
            const targetStep = document.querySelector(`.step-content[data-step="${stepSalvo}"]`);
            if (targetStep) {
                targetStep.classList.add('active');
                currentStep = stepSalvo;
            }
        }
        
        console.log('✅ Progresso restaurado:', Object.keys(dados).length, 'campos');
    }

    /* ========================================
       ALTERNÂNCIA BUSINESS/PESSOA (APENAS DESKTOP)
    ======================================== */
    
    function renderizar() {
        const isBusiness = tipoAtual === 'business';
        
        document.body.setAttribute('data-tipo-ativo', tipoAtual);
        
        if (titulo) {
            titulo.textContent = isBusiness ? 'Cadastro de Negócio' : 'Cadastro Pessoal';
        }
        
        if (formBusiness) formBusiness.hidden = !isBusiness;
        if (formPessoa) formPessoa.hidden = isBusiness;
        
        if (switchConta) {
            switchConta.querySelectorAll('button').forEach(btn => {
                const ativo = btn.dataset.tipo === tipoAtual;
                btn.classList.toggle('active', ativo);
            });
        }
        
        const imgPerson = document.getElementById('img-person-fixa');
        const imgBusiness = document.getElementById('img-business-fixa');
        const cntImage = document.querySelector('.cnt-image');
        
        if (imgPerson && imgBusiness && cntImage) {
            if (isBusiness) {
                imgPerson.classList.remove('active');
                imgBusiness.classList.add('active');
                cntImage.classList.remove('person-mode');
                cntImage.classList.add('business-mode');
            } else {
                imgBusiness.classList.remove('active');
                imgPerson.classList.add('active');
                cntImage.classList.remove('business-mode');
                cntImage.classList.add('person-mode');
            }
        }
        
        salvarProgresso();
    }

    // Só adiciona listener de switch se NÃO for mobile
    if (switchConta && !isMobileMode) {
        switchConta.addEventListener('click', async (e) => {
            const btn = e.target.closest('button');
            if (!btn || btn.dataset.tipo === tipoAtual) return;
            
            const novoTipo = btn.dataset.tipo;
            btn.disabled = true;
            
            try {
                const res = await fetch('../../registration/ajax/switch-tipo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `tipo=${novoTipo}&csrf=${csrfToken}`
                });
                
                const data = await res.json();
                
                if (data.error) {
                    showToast(`❌ ${data.error}`, 'error');
                    return;
                }
                
                tipoAtual = data.tipo;
                currentStep = 1;
                
                if (formBusiness) limparErros(formBusiness);
                if (formPessoa) limparErros(formPessoa);
                
                renderizar();
                
            } catch (e) {
                console.error('Erro ao alternar tipo:', e);
                showToast('❌ Erro ao alternar tipo de cadastro', 'error');
            } finally {
                btn.disabled = false;
            }
        });
    }

    /* ========================================
       UTILITÁRIOS DE UI
    ======================================== */
    
    window.updateFileName = async (input) => {
        const display = input.parentElement.querySelector('.file-selected-name');
        if (input.files.length > 0) {
            const fileName = input.files[0].name;
            const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);
            display.innerHTML = `<i class="fa-solid fa-check-circle"></i> ${fileName} (${fileSize} MB)`;
            
            // Salva arquivo
            await FileStorage.save(input.id || input.name, input.files[0]);
        } else {
            display.textContent = '';
        }
    };

    window.toggleFiscalMode = (mode) => {
        const input = document.getElementById('tax_id');
        const fileArea = document.getElementById('area_tax_file');
        const fileInput = document.getElementById('tax_id_file');
        
        if (mode === 'text') {
            input.style.display = 'block';
            input.required = true;
            fileArea.style.display = 'none';
            fileInput.required = false;
            fileInput.value = '';
            if (fileArea.querySelector('.file-selected-name')) {
                fileArea.querySelector('.file-selected-name').textContent = '';
            }
        } else {
            input.style.display = 'none';
            input.required = false;
            input.value = '';
            fileArea.style.display = 'block';
            fileInput.required = true;
        }
    };

    window.toggleLogoRequired = (checkbox) => {
        const logoInput = document.getElementById('input_logo');
        const container = document.getElementById('logo_container');
        
        logoInput.required = !checkbox.checked;
        logoInput.disabled = checkbox.checked;
        container.style.opacity = checkbox.checked ? '0.5' : '1';
        container.style.pointerEvents = checkbox.checked ? 'none' : 'auto';
        
        if (checkbox.checked && logoInput.value) {
            logoInput.value = '';
            const display = container.querySelector('.file-selected-name');
            if (display) display.textContent = '';
        }
    };

    window.checkStrengthBus = (pass) => checkPasswordStrength(pass, 'strengthBarBus', 'strengthTextBus');
    window.checkStrengthPessoa = (pass) => checkPasswordStrength(pass, 'strengthBarPessoa', 'strengthTextPessoa');

    function checkPasswordStrength(pass, barId, textId) {
        const bar = document.getElementById(barId);
        const txt = document.getElementById(textId);
        
        if (!bar || !txt) return;
        
        let strength = 0;
        
        if (pass.length >= 8) strength += 25;
        if (pass.length >= 12) strength += 15;
        if (/[A-Z]/.test(pass) && /[a-z]/.test(pass)) strength += 20;
        if (/[0-9]/.test(pass)) strength += 20;
        if (/[^a-zA-Z0-9]/.test(pass)) strength += 20;
        
        const colors = ['#ef4444', '#f59e0b', '#eab308', '#84cc16', '#10b981'];
        const labels = ['Muito Fraca ❌', 'Fraca ⚠️', 'Média ⚡', 'Boa ✅', 'Forte 💪'];
        
        const index = Math.min(Math.floor(strength / 20), 4);
        
        bar.style.width = `${strength}%`;
        bar.style.backgroundColor = colors[index];
        txt.textContent = labels[index];
        txt.style.color = colors[index];
    }

    /* ========================================
       AUTO-SAVE AO DIGITAR
    ======================================== */
    
    document.querySelectorAll('input, select, textarea').forEach(el => {
        el.addEventListener('input', () => {
            salvarProgresso();
            
            el.classList.remove('input-error');
            const parent = el.closest('.person-field-input');
            if (parent) {
                parent.querySelectorAll('.error-msg-visual').forEach(m => m.remove());
            }
            
            if (el.id === 'email_business') sincronizarEmail();
        });
    });

    /* ========================================
       LABELS DINÂMICAS POR PAÍS
    ======================================== */
    
    const selectPais = document.getElementById('select_pais');
    if (selectPais) {
        selectPais.addEventListener('change', function() {
            const labelFiscal = document.getElementById('label_fiscal');
            const taxIdInput = document.getElementById('tax_id');
            const labelState = document.getElementById('label_state');
            const countryCodeInput = document.getElementById('country_code_input');
            
            const configs = {
                'BR': { fiscal: 'CNPJ', placeholder: '00.000.000/0000-00', state: 'Estado' },
                'PT': { fiscal: 'NIF', placeholder: '9 dígitos', state: 'Distrito' },
                'MZ': { fiscal: 'NUIT', placeholder: '9 dígitos', state: 'Província' },
                'AO': { fiscal: 'NUIT', placeholder: '9 dígitos', state: 'Província' },
                'US': { fiscal: 'EIN', placeholder: '00-0000000', state: 'State' },
                'ZA': { fiscal: 'Company Registration', placeholder: 'Registration number', state: 'Province' }
            };
            
            const config = configs[this.value] || { fiscal: 'Tax ID', placeholder: 'Documento fiscal', state: 'Província/Estado' };
            
            if (labelFiscal) labelFiscal.innerHTML = `${config.fiscal} <span class="required">*</span>`;
            if (taxIdInput) taxIdInput.placeholder = config.placeholder;
            if (labelState) labelState.innerHTML = `${config.state} <span class="required">*</span>`;
            if (countryCodeInput) countryCodeInput.value = this.value;
        });
    }

    const selectPaisPessoa = document.getElementById('select_pais_pessoa');
    if (selectPaisPessoa) {
        selectPaisPessoa.addEventListener('change', function() {
            const labelState = document.getElementById('label_state_pessoa');
            const countryCodeInput = document.getElementById('country_code_input_pessoa');
            
            const configs = {
                'BR': 'Estado',
                'PT': 'Distrito',
                'MZ': 'Província',
                'AO': 'Província',
                'US': 'State',
                'ZA': 'Province'
            };
            
            const config = configs[this.value] || 'Província/Estado';
            
            if (labelState) labelState.innerHTML = `${config} <span class="required">*</span>`;
            if (countryCodeInput) countryCodeInput.value = this.value;
        });
    }

    /* ========================================
       SUBMISSÃO AJAX DOS FORMULÁRIOS
    ======================================== */
    
    function handleSubmit(form, url) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const isBusiness = form.id === 'formBusiness';
            
            if (isBusiness) {
                if (!validarStep(4)) {
                    showToast('⚠️ Preencha todos os campos obrigatórios', 'warning');
                    return;
                }
            } else {
                if (!validarFormularioCompleto(form)) {
                    showToast('⚠️ Preencha todos os campos obrigatórios', 'warning');
                    return;
                }
            }
            
            const btn = form.querySelector('button[type="submit"]');
            const oldText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processando...';
            
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    body: new FormData(form)
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showToast('✅ Cadastro realizado! Redirecionando...', 'success');
                    
                    limparTodosDados();
                    
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                } else if (data.errors) {
                    processarErrosBackend(data, form);
                    btn.disabled = false;
                    btn.innerHTML = oldText;
                } else {
                    showToast(`❌ ${data.error || 'Erro desconhecido'}`, 'error');
                    btn.disabled = false;
                    btn.innerHTML = oldText;
                }
            } catch (e) {
                console.error('Erro ao submeter formulário:', e);
                showToast('❌ Erro de conexão com o servidor', 'error');
                btn.disabled = false;
                btn.innerHTML = oldText;
            }
        });
    }

    function processarErrosBackend(data, form) {
        const { errors, errorStep, errorSteps } = data;
        
        if (!errors) return;
        
        const isBusiness = form.id === 'formBusiness';
        if (isBusiness && errorStep) {
            document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
            const targetStep = document.querySelector(`.step-content[data-step="${errorStep}"]`);
            if (targetStep) {
                targetStep.classList.add('active');
                currentStep = errorStep;
                localStorage.setItem('vg_step', errorStep);
            }
            
            showToast(`❌ Erros encontrados na Etapa ${errorStep}`, 'error');
        }
        
        let primeiroErro = null;
        Object.keys(errors).forEach(field => {
            const campo = form.querySelector(`[name="${field}"]`);
            if (campo) {
                if (!primeiroErro) primeiroErro = campo;
                mostrarErro(campo, errors[field]);
            }
        });
        
        if (primeiroErro) {
            setTimeout(() => {
                primeiroErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
                primeiroErro.focus();
            }, 300);
        }
        
        const numErros = Object.keys(errors).length;
        showToast(`❌ ${numErros} erro(s) encontrado(s). Corrija os campos destacados.`, 'error', 5000);
    }

    function validarFormularioCompleto(form) {
        limparErros(form);
        
        const campos = form.querySelectorAll('input[required], select[required], textarea[required]');
        let valido = true;
        let primeiroErro = null;
        
        campos.forEach(campo => {
            const valor = campo.value.trim();
            let erro = null;
            
            if (!valor) {
                erro = 'Este campo é obrigatório.';
            } else if (campo.type === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(valor)) {
                    erro = 'E-mail inválido.';
                }
            } else if (campo.type === 'tel') {
                if (!valor.startsWith('+')) {
                    erro = 'Inclua o código do país (ex: +258).';
                } else if (valor.length < 10) {
                    erro = 'Número incompleto.';
                }
            } else if (campo.type === 'password') {
                if (valor.length < 8) {
                    erro = 'Mínimo 8 caracteres.';
                }
            } else if (campo.name === 'password_confirm') {
                const senhaOriginal = form.querySelector('input[name="password"]')?.value;
                if (valor !== senhaOriginal) {
                    erro = 'As senhas não coincidem.';
                }
            }
            
            if (erro) {
                valido = false;
                if (!primeiroErro) primeiroErro = campo;
                mostrarErro(campo, erro);
            }
        });
        
        if (primeiroErro) {
            primeiroErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        return valido;
    }

    if (formBusiness) handleSubmit(formBusiness, '../process/business.store.php');
    if (formPessoa) handleSubmit(formPessoa, '../process/pessoa.store.php');

    /* ========================================
       INICIALIZAÇÃO COMPLETA
    ======================================== */
    
    console.log('🚀 Sistema de Cadastro Ultimate V4.0 inicializado');
    console.log('📱 Modo:', isMobileMode ? 'MOBILE' : 'DESKTOP');
    
    // Aguarda um pouco para garantir que o DOM está pronto
    setTimeout(async () => {
        console.log('🔧 Inicializando componentes adicionais...');
        
        // Adiciona estilos dos checkboxes
        adicionarEstilosCheckboxExclusivo();
        
        // Configura checkboxes exclusivos
        setupCamposOpcionaisExclusivos();
        
        // Configura inputs de arquivo
        setupFileInputs();
        
        // Restaura arquivos salvos
        await restaurarArquivos();
        
        console.log('✅ Todos os componentes inicializados!');
    }, 600);
    
    await carregarPaises();
    
    renderizar();
    
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
    document.head.appendChild(style);
    
    // Exporta funções globais
    window.limparTodosDados = limparTodosDados;
    window.FileStorage = FileStorage;
    window.setupCamposOpcionaisExclusivos = setupCamposOpcionaisExclusivos;
    window.restaurarArquivos = restaurarArquivos;
    window.setupFileInputs = setupFileInputs;
    
    console.log('✅ Sistema pronto para uso!');
});