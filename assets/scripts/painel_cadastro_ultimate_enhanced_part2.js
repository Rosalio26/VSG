/* ========================================
   CONTINUAÇÃO DO SCRIPT PRINCIPAL
   Detecção de Localização com Código de País Automático
======================================== */

document.addEventListener("DOMContentLoaded", async () => {
    
    const formPessoa = document.getElementById("formPessoa");
    const formBusiness = document.getElementById("formBusiness");
    const titulo = document.getElementById("titulo");
    const switchConta = document.getElementById("switchConta");
    const csrfToken = document.body.dataset.csrf;
    
    const tiposPermitidosStr = document.body.dataset.tiposPermitidos || '["pessoal"]';
    const tiposPermitidos = JSON.parse(tiposPermitidosStr);
    const isMobileMode = tiposPermitidos.length === 1 && tiposPermitidos[0] === 'pessoal';
    
    let tipoAtual = localStorage.getItem('vg_type') || document.body.dataset.tipoInicial || 'pessoal';
    
    if (isMobileMode) {
        tipoAtual = 'pessoal';
        localStorage.setItem('vg_type', 'pessoal');
    }
    
    let currentStep = 1;
    let isNavigating = false;

    console.log('📱 Modo:', isMobileMode ? 'MOBILE (apenas pessoa)' : 'DESKTOP (ambos)');
    console.log('🎯 Tipo atual:', tipoAtual);

    /* ========================================
       DETECÇÃO AUTOMÁTICA DE LOCALIZAÇÃO COM CÓDIGO DE PAÍS
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
        const isBusinessAtivo = formBusiness && !formBusiness.hidden;
        const prefix = isBusinessAtivo ? '' : '_pessoa';
        const formularioTipo = isBusinessAtivo ? 'business' : 'pessoal';
        
        const selectPais = document.getElementById(`select_pais${prefix}`);
        const stateInput = document.getElementById(`state_input${prefix}`);
        const cityInput = document.getElementById(`city_input${prefix}`);
        const postalInput = document.getElementById(`postal_code_input${prefix}`);
        const latInput = document.getElementById(`latitude_input${prefix}`);
        const lonInput = document.getElementById(`longitude_input${prefix}`);
        const countryCodeInput = document.getElementById(`country_code_input${prefix}`);
        
        // Preenche país
        if (selectPais && data.country) {
            selectPais.value = data.country;
            selectPais.dispatchEvent(new Event('change'));
            
            // ===== APLICA CÓDIGO DE PAÍS AUTOMATICAMENTE =====
            aplicarCodigoPais(data.country, formularioTipo);
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
       DETECÇÃO MANUAL COM CÓDIGO DE PAÍS
    ======================================== */
    
    window.detectarLocalizacaoManual = async function() {
        const loadingToast = showToast('🔍 Detectando sua localização...', 'info', 0);
        const sucesso = await detectarLocalizacaoCompleta(false);
        
        if (loadingToast && loadingToast.remove) {
            loadingToast.remove();
        }
        
        if (sucesso) {
            showToast('✅ Localização detectada com sucesso!', 'success');
        } else {
            showToast('⚠️ Não foi possível detectar a localização automaticamente', 'warning');
        }
    };

    window.detectarLocalizacaoManualPessoa = async function() {
        const loadingToast = showToast('🔍 Detectando sua localização...', 'info', 0);
        const sucesso = await detectarLocalizacaoCompleta(false);
        
        if (loadingToast && loadingToast.remove) {
            loadingToast.remove();
        }
        
        if (sucesso) {
            showToast('✅ Localização detectada com sucesso!', 'success');
        } else {
            showToast('⚠️ Não foi possível detectar a localização automaticamente', 'warning');
        }
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
            
            restaurarProgresso();
            
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
       LISTENERS PARA MUDANÇA DE PAÍS (APLICA CÓDIGO AUTOMATICAMENTE)
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
            
            // ===== APLICA CÓDIGO DE PAÍS AO TELEFONE =====
            if (this.value) {
                aplicarCodigoPais(this.value, 'business');
            }
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
            
            // ===== APLICA CÓDIGO DE PAÍS AO TELEFONE =====
            if (this.value) {
                aplicarCodigoPais(this.value, 'pessoal');
            }
        });
    }

    /* ========================================
       VALIDAÇÃO COM CÓDIGO DE PAÍS
    ======================================== */
    
    // Adiciona validação especial para telefone nos campos
    const telBusiness = document.getElementById('tel_business');
    const telPessoa = document.getElementById('telefone_input');
    
    if (telBusiness) {
        telBusiness.addEventListener('blur', function() {
            const selectPais = document.getElementById('select_pais');
            const countryCode = selectPais ? selectPais.value : null;
            
            if (countryCode && this.value) {
                const validacao = validarNumeroTelefone(this, countryCode);
                
                if (!validacao.valido) {
                    const parent = this.closest('.person-field-input');
                    if (parent) {
                        parent.querySelectorAll('.error-msg-visual').forEach(el => el.remove());
                        
                        const span = document.createElement('span');
                        span.className = 'error-msg-visual';
                        span.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${validacao.erro}`;
                        parent.appendChild(span);
                        
                        this.classList.add('input-error');
                    }
                } else {
                    const parent = this.closest('.person-field-input');
                    if (parent) {
                        parent.querySelectorAll('.error-msg-visual').forEach(el => el.remove());
                        this.classList.remove('input-error');
                    }
                }
            }
        });
        
        // Formatação automática ao digitar
        telBusiness.addEventListener('input', function() {
            const selectPais = document.getElementById('select_pais');
            const countryCode = selectPais ? selectPais.value : null;
            
            if (countryCode) {
                formatarNumeroTelefone(this, countryCode);
            }
        });
    }
    
    if (telPessoa) {
        telPessoa.addEventListener('blur', function() {
            const selectPais = document.getElementById('select_pais_pessoa');
            const countryCode = selectPais ? selectPais.value : null;
            
            if (countryCode && this.value) {
                const validacao = validarNumeroTelefone(this, countryCode);
                
                if (!validacao.valido) {
                    const parent = this.closest('.person-field-input');
                    if (parent) {
                        parent.querySelectorAll('.error-msg-visual').forEach(el => el.remove());
                        
                        const span = document.createElement('span');
                        span.className = 'error-msg-visual';
                        span.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${validacao.erro}`;
                        parent.appendChild(span);
                        
                        this.classList.add('input-error');
                    }
                } else {
                    const parent = this.closest('.person-field-input');
                    if (parent) {
                        parent.querySelectorAll('.error-msg-visual').forEach(el => el.remove());
                        this.classList.remove('input-error');
                    }
                }
            }
        });
        
        telPessoa.addEventListener('input', function() {
            const selectPais = document.getElementById('select_pais_pessoa');
            const countryCode = selectPais ? selectPais.value : null;
            
            if (countryCode) {
                formatarNumeroTelefone(this, countryCode);
            }
        });
    }

    /* ========================================
       RESTANTE DAS FUNÇÕES (IGUAL AO ORIGINAL)
       Incluir aqui todas as outras funções:
       - limparTodosDados()
       - showToast()
       - validarStep()
       - changeStep()
       - salvarProgresso()
       - restaurarProgresso()
       - renderizar()
       - etc.
    ======================================== */
    
    // [Todas as outras funções do arquivo original permanecem aqui]
    
    /* ========================================
       INICIALIZAÇÃO
    ======================================== */
    
    console.log('🚀 Sistema de Cadastro Ultimate V4.0 Enhanced inicializado');
    console.log('📞 Sistema de código de país automático ativado');
    console.log('📱 Modo:', isMobileMode ? 'MOBILE' : 'DESKTOP');
    
    setTimeout(async () => {
        console.log('🔧 Inicializando componentes adicionais...');
        
        adicionarEstilosCheckboxExclusivo();
        setupCamposOpcionaisExclusivos();
        setupFileInputs();
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
    
    window.limparTodosDados = limparTodosDados;
    window.FileStorage = FileStorage;
    window.setupCamposOpcionaisExclusivos = setupCamposOpcionaisExclusivos;
    window.restaurarArquivos = restaurarArquivos;
    window.setupFileInputs = setupFileInputs;
    window.aplicarCodigoPais = aplicarCodigoPais;
    window.validarNumeroTelefone = validarNumeroTelefone;
    
    console.log('✅ Sistema pronto para uso com código de país automático!');
});