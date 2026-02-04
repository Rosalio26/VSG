/**
 * ================================================================
 * MELHORIAS - PERSISTÊNCIA DE ARQUIVOS E CAMPOS OPCIONAIS
 * Versão 2.0 - Integrado com Sistema de Checkboxes Exclusivos
 * ================================================================
 */

/* ========================================
   SISTEMA DE PERSISTÊNCIA DE ARQUIVOS
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
   AUTO-SAVE DE ARQUIVOS AO SELECIONAR
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
   SISTEMA DE CAMPOS OPCIONAIS - VERSÃO ANTIGA
   (Mantido para compatibilidade retroativa)
======================================== */

function setupCamposOpcionaisLegacy() {
    // HTML a ser inserido antes do campo de código postal (VERSÃO ANTIGA)
    const checkboxHTML = `
        <div class="person-field-input optional-field-checkbox-legacy" style="margin-bottom: 20px;">
            <div style="background: #f6f8fa; padding: 12px; border-radius: 6px; border: 1px solid #d0d7de;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                    <input type="checkbox" id="has_nuit_only_legacy" style="width: 18px; height: 18px; accent-color: #5865f2;">
                    <span style="font-size: 14px; color: #24292f; font-weight: 500;">
                        <i class="fa-solid fa-file-invoice"></i>
                        Não possuo Código Postal, apenas documento fiscal (NUIT/NIF)
                    </span>
                </label>
                <small style="display: block; margin-top: 6px; color: #586069; padding-left: 26px;">
                    Marque esta opção se você só tiver o documento fiscal da empresa
                </small>
            </div>
        </div>
    `;
    
    // Verifica se já existe o sistema novo (checkboxes exclusivos)
    const exclusiveSelector = document.querySelector('.exclusive-field-selector');
    if (exclusiveSelector) {
        console.log('ℹ️ Sistema de checkboxes exclusivos detectado - versão legacy desabilitada');
        return; // Não adiciona a versão antiga se a nova já existe
    }
    
    // Insere checkbox antes do campo de código postal (Business) - VERSÃO ANTIGA
    const postalCodeField = document.querySelector('#formBusiness [name="postal_code"]')?.closest('.person-field-input');
    if (postalCodeField && !document.getElementById('has_nuit_only_legacy')) {
        postalCodeField.insertAdjacentHTML('beforebegin', checkboxHTML);
        
        const checkbox = document.getElementById('has_nuit_only_legacy');
        const postalInput = document.querySelector('#formBusiness [name="postal_code"]');
        const taxIdInput = document.getElementById('tax_id');
        const taxIdFileArea = document.getElementById('area_tax_file');
        
        if (checkbox && postalInput && taxIdInput) {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    // Desabilita código postal
                    postalInput.disabled = true;
                    postalInput.required = false;
                    postalInput.value = '';
                    postalInput.style.opacity = '0.5';
                    postalInput.style.cursor = 'not-allowed';
                    postalInput.placeholder = 'Campo desabilitado (apenas documento fiscal)';
                    postalCodeField.querySelector('label').style.opacity = '0.5';
                    
                    // Torna documento fiscal obrigatório
                    const currentMode = document.querySelector('[name="fiscal_mode"]:checked')?.value || 'text';
                    if (currentMode === 'text') {
                        taxIdInput.required = true;
                    } else {
                        const taxIdFile = document.getElementById('tax_id_file');
                        if (taxIdFile) taxIdFile.required = true;
                    }
                    
                } else {
                    // Reabilita código postal
                    postalInput.disabled = false;
                    postalInput.style.opacity = '1';
                    postalInput.style.cursor = 'text';
                    postalInput.placeholder = 'Digite o código postal';
                    postalCodeField.querySelector('label').style.opacity = '1';
                    
                    // Documento fiscal continua obrigatório
                    const currentMode = document.querySelector('[name="fiscal_mode"]:checked')?.value || 'text';
                    if (currentMode === 'text') {
                        taxIdInput.required = true;
                    } else {
                        const taxIdFile = document.getElementById('tax_id_file');
                        if (taxIdFile) taxIdFile.required = true;
                    }
                }
                
                // Salva estado
                if (typeof salvarProgresso === 'function') {
                    salvarProgresso();
                }
            });
            
            // Restaura estado do checkbox
            const savedData = JSON.parse(localStorage.getItem('vg_data') || '{}');
            if (savedData.has_nuit_only_legacy === 'on' || savedData.has_nuit_only_legacy === true) {
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change'));
            }
        }
    }
}

function setupCamposOpcionaisPessoaLegacy() {
    // Verifica se já existe o sistema novo
    const exclusiveSelector = document.querySelector('.exclusive-field-selector-pessoa');
    if (exclusiveSelector) {
        console.log('ℹ️ Sistema de checkboxes pessoa detectado - versão legacy desabilitada');
        return;
    }
    
    const checkboxHTML = `
        <div class="person-field-input optional-field-checkbox-legacy" style="margin-bottom: 20px;">
            <div style="background: #f6f8fa; padding: 12px; border-radius: 6px; border: 1px solid #d0d7de;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                    <input type="checkbox" id="has_address_only_legacy" style="width: 18px; height: 18px; accent-color: #00d084;">
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
    
    const formPessoa = document.getElementById('formPessoa');
    if (formPessoa) {
        const pessoaPostalField = formPessoa.querySelector('[name="postal_code"]')?.closest('.person-field-input');
        
        if (pessoaPostalField && !document.getElementById('has_address_only_legacy')) {
            pessoaPostalField.insertAdjacentHTML('beforebegin', checkboxHTML);
            
            const checkbox = document.getElementById('has_address_only_legacy');
            const postalInput = document.getElementById('postal_code_input_pessoa');
            
            if (checkbox && postalInput) {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        postalInput.disabled = true;
                        postalInput.required = false;
                        postalInput.value = '';
                        postalInput.style.opacity = '0.5';
                        postalInput.style.cursor = 'not-allowed';
                        postalInput.placeholder = 'Campo opcional';
                        pessoaPostalField.querySelector('label').style.opacity = '0.5';
                    } else {
                        postalInput.disabled = false;
                        postalInput.style.opacity = '1';
                        postalInput.style.cursor = 'text';
                        postalInput.placeholder = 'Digite o código postal';
                        pessoaPostalField.querySelector('label').style.opacity = '1';
                    }
                    
                    if (typeof salvarProgresso === 'function') {
                        salvarProgresso();
                    }
                });
                
                // Restaura estado
                const savedData = JSON.parse(localStorage.getItem('vg_data') || '{}');
                if (savedData.has_address_only_legacy === 'on' || savedData.has_address_only_legacy === true) {
                    checkbox.checked = true;
                    checkbox.dispatchEvent(new Event('change'));
                }
            }
        }
    }
}

/* ========================================
   WRAPPER INTELIGENTE - USA NOVA VERSÃO OU FALLBACK
======================================== */

function setupCamposOpcionais() {
    // Tenta usar a versão nova (checkboxes exclusivos)
    if (typeof setupCamposOpcionaisExclusivos === 'function') {
        console.log('✅ Usando sistema de checkboxes exclusivos (nova versão)');
        setupCamposOpcionaisExclusivos();
    } else {
        // Fallback para versão antiga
        console.log('⚠️ Sistema exclusivo não encontrado - usando versão legacy');
        setupCamposOpcionaisLegacy();
        setupCamposOpcionaisPessoaLegacy();
    }
}

/* ========================================
   INTEGRAÇÃO COM SISTEMA EXISTENTE
======================================== */

// Sobrescreve a função limparTodosDados original para incluir limpeza de arquivos
const limparTodosDadosOriginal = window.limparTodosDados;
window.limparTodosDados = function() {
    if (limparTodosDadosOriginal) {
        limparTodosDadosOriginal();
    }
    FileStorage.clearAll();
    console.log('🧹 Arquivos em cache limpos');
};

/* ========================================
   ATUALIZAÇÃO DA FUNÇÃO updateFileName
======================================== */

// Sobrescreve função original para incluir salvamento
const updateFileNameOriginal = window.updateFileName;
window.updateFileName = async function(input) {
    // Chama função original
    if (updateFileNameOriginal) {
        updateFileNameOriginal(input);
    }
    
    // Salva arquivo
    if (input.files.length > 0) {
        await FileStorage.save(input.id || input.name, input.files[0]);
    }
};

/* ========================================
   VISUALIZAÇÃO DE PROGRESSO DE UPLOAD
======================================== */

function adicionarIndicadorProgresso(input) {
    const parent = input.closest('.person-field-input');
    if (!parent || parent.querySelector('.upload-progress')) return;
    
    const progressHTML = `
        <div class="upload-progress" style="display: none; margin-top: 8px;">
            <div style="background: #e5e7eb; height: 4px; border-radius: 2px; overflow: hidden;">
                <div class="progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #00d084, #00b574); transition: width 0.3s;"></div>
            </div>
            <small style="display: block; margin-top: 4px; color: #586069; font-size: 12px;">
                <i class="fa-solid fa-circle-notch fa-spin"></i> Salvando arquivo...
            </small>
        </div>
    `;
    
    const display = input.parentElement.querySelector('.file-selected-name');
    if (display) {
        display.insertAdjacentHTML('afterend', progressHTML);
    }
}

function mostrarProgresso(input, progresso) {
    const parent = input.closest('.person-field-input');
    const progressContainer = parent?.querySelector('.upload-progress');
    const progressBar = progressContainer?.querySelector('.progress-bar');
    
    if (progressContainer && progressBar) {
        progressContainer.style.display = 'block';
        progressBar.style.width = `${progresso}%`;
        
        if (progresso >= 100) {
            setTimeout(() => {
                progressContainer.style.display = 'none';
            }, 500);
        }
    }
}

/* ========================================
   INTEGRAÇÃO COM SALVAMENTO DE PROGRESSO
======================================== */

// Sobrescreve a função salvarProgresso para incluir checkboxes
const salvarProgressoOriginal = window.salvarProgresso;
window.salvarProgresso = function() {
    if (salvarProgressoOriginal) {
        salvarProgressoOriginal();
    }
    
    const dados = JSON.parse(localStorage.getItem('vg_data') || '{}');
    
    // Salva estado dos checkboxes (tanto versão nova quanto antiga)
    const postalCheckbox = document.getElementById('has_postal_code');
    const taxCheckbox = document.getElementById('has_tax_id_only');
    const postalCheckboxPessoa = document.getElementById('no_postal_code_pessoa');
    
    // Checkboxes Legacy
    const legacyNuit = document.getElementById('has_nuit_only_legacy');
    const legacyAddress = document.getElementById('has_address_only_legacy');
    
    // Nova versão (exclusivos)
    if (postalCheckbox) {
        dados.field_option = postalCheckbox.checked ? 'postal' : (taxCheckbox?.checked ? 'tax' : null);
    }
    
    if (postalCheckboxPessoa) {
        dados.no_postal_code_pessoa = postalCheckboxPessoa.checked;
    }
    
    // Versão Legacy
    if (legacyNuit) {
        dados.has_nuit_only_legacy = legacyNuit.checked;
    }
    
    if (legacyAddress) {
        dados.has_address_only_legacy = legacyAddress.checked;
    }
    
    localStorage.setItem('vg_data', JSON.stringify(dados));
};

/* ========================================
   INICIALIZAÇÃO INTELIGENTE
======================================== */

document.addEventListener('DOMContentLoaded', async () => {
    console.log('🔧 Inicializando sistema de melhorias v2.0...');
    
    // Aguarda um pouco para garantir que o DOM está pronto
    setTimeout(async () => {
        // Configura inputs de arquivo
        setupFileInputs();
        
        // Adiciona indicadores de progresso
        document.querySelectorAll('input[type="file"]').forEach(adicionarIndicadorProgresso);
        
        // Restaura arquivos salvos
        await restaurarArquivos();
        
        // Configura campos opcionais (usa versão nova ou fallback)
        setupCamposOpcionais();
        
        console.log('✅ Sistema de melhorias v2.0 inicializado com sucesso!');
    }, 600); // Aguarda 600ms para garantir que o script exclusivo carregou primeiro
});

/* ========================================
   EXPORT FUNCTIONS (para uso externo)
======================================== */

window.FileStorage = FileStorage;
window.setupCamposOpcionais = setupCamposOpcionais;
window.setupCamposOpcionaisLegacy = setupCamposOpcionaisLegacy;
window.setupCamposOpcionaisPessoaLegacy = setupCamposOpcionaisPessoaLegacy;
window.restaurarArquivos = restaurarArquivos;
window.setupFileInputs = setupFileInputs;