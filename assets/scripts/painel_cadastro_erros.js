/**
 * VISION GREEN - SISTEMA DE CADASTRO V3.0
 * Foco: Design Melhorado + Localização Automática + Validação Rigorosa
 */

document.addEventListener("DOMContentLoaded", async () => {
    const formPessoa = document.getElementById("formPessoa");
    const formBusiness = document.getElementById("formBusiness");
    const titulo = document.getElementById("titulo");
    const switchConta = document.getElementById("switchConta");
    const csrfToken = document.body.dataset.csrf;

    let tipoAtual = localStorage.getItem('vg_type') || document.body.dataset.tipoInicial || 'business';
    let currentStep = 1;

    /* ========================================
       DETECÇÃO AUTOMÁTICA DE LOCALIZAÇÃO
    ======================================== */
    
    async function detectarLocalizacaoCompleta() {
        const loadingToast = showToast('🌍 Detectando sua localização...', 'info', 0);
        
        try {
            // Tenta API 1: IPApi.co (Mais completa)
            const res1 = await fetch('https://ipapi.co/json/');
            const data1 = await res1.json();
            
            if (data1.country_code) {
                preencherLocalizacao({
                    country: data1.country_code,
                    state: data1.region,
                    city: data1.city,
                    postal: data1.postal,
                    lat: data1.latitude,
                    lon: data1.longitude
                });
                
                closeToast(loadingToast);
                showToast('✅ Localização detectada!', 'success');
                return;
            }
        } catch (e) {
            console.warn('API 1 falhou, tentando backup...');
        }

        try {
            // Backup: IP-API
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
                
                closeToast(loadingToast);
                showToast('✅ Localização detectada!', 'success');
                return;
            }
        } catch (e) {
            console.error('Todas APIs falharam');
        }
        
        closeToast(loadingToast);
        showToast('⚠️ Não foi possível detectar localização', 'warning');
    }

    function preencherLocalizacao(data) {
        const isBusiness = !formBusiness.hidden;
        const prefix = isBusiness ? '' : '_pessoa';
        
        // Preenche campos
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
        if (stateInput && data.state) stateInput.value = data.state;
        if (cityInput && data.city) cityInput.value = data.city;
        if (postalInput && data.postal) postalInput.value = data.postal;
        if (latInput && data.lat) latInput.value = data.lat;
        if (lonInput && data.lon) lonInput.value = data.lon;
        if (countryCodeInput && data.country) countryCodeInput.value = data.country;
        
        salvarProgresso();
    }

    /* ========================================
       CARREGAMENTO DE PAÍSES
    ======================================== */
    
    async function carregarPaises() {
        const selectsBusiness = document.getElementById('select_pais');
        const selectsPessoa = document.getElementById('select_pais_pessoa');
        
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
            
            // Restaura dados salvos
            restaurarProgresso();
            
            // Detecta localização se não tiver país salvo
            const paisAtual = selectsBusiness?.value || selectsPessoa?.value;
            if (!paisAtual) {
                await detectarLocalizacaoCompleta();
            }
            
        } catch (e) {
            console.error('Erro ao carregar países:', e);
            showToast('❌ Erro ao carregar lista de países', 'error');
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
            // Ignora campos invisíveis
            if (campo.offsetParent === null) return;
            
            // Ignora logo se checkbox marcado
            if (campo.name === 'logo' && document.querySelector('input[name="no_logo"]')?.checked) {
                return;
            }
            
            const valor = campo.value.trim();
            let erro = null;
            
            // Validações específicas
            if (campo.type === 'file') {
                if (campo.files.length === 0) {
                    erro = 'Por favor, selecione um arquivo.';
                } else {
                    const arquivo = campo.files[0];
                    const ext = arquivo.name.split('.').pop().toLowerCase();
                    const permitidos = ['png', 'jpg', 'jpeg', 'pdf'];
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    
                    if (!permitidos.includes(ext)) {
                        erro = 'Formato inválido. Use PNG, JPEG ou PDF.';
                    } else if (arquivo.size > maxSize) {
                        erro = 'Arquivo muito grande (máx. 5MB).';
                    }
                }
            }
            else if (campo.type === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(valor)) {
                    erro = 'E-mail inválido.';
                }
            }
            else if (campo.type === 'tel') {
                if (!valor.startsWith('+')) {
                    erro = 'Inclua o código do país (ex: +258).';
                } else if (valor.length < 8) {
                    erro = 'Número de telefone incompleto.';
                }
            }
            else if (campo.type === 'password') {
                if (valor.length < 8) {
                    erro = 'Senha deve ter no mínimo 8 caracteres.';
                }
            }
            else if (campo.name === 'password_confirm') {
                const senhaOriginal = container.querySelector('input[name="password"]')?.value;
                if (valor !== senhaOriginal) {
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
            
            // Aplica erro visual
            if (erro) {
                valido = false;
                if (!primeiroErro) primeiroErro = campo;
                
                mostrarErro(campo, erro);
            }
        });
        
        // Scroll para primeiro erro
        if (primeiroErro) {
            primeiroErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
            primeiroErro.focus();
        }
        
        return valido;
    }

    function mostrarErro(campo, mensagem) {
        const parent = campo.closest('.person-field-input');
        if (!parent) return;
        
        // Marca campo como erro
        if (campo.type === 'file') {
            campo.closest('.custom-file-upload')?.classList.add('input-error');
        } else if (campo.closest('.iti')) {
            campo.closest('.iti').classList.add('input-error');
        } else {
            campo.classList.add('input-error');
        }
        
        // Adiciona mensagem
        const span = document.createElement('span');
        span.className = 'error-msg-visual';
        span.style.cssText = 'color: #dc2626; font-size: 12px; font-weight: 600; display: block; margin-top: 6px; animation: shake 0.3s;';
        span.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${mensagem}`;
        parent.appendChild(span);
    }

    function limparErros(container) {
        container.querySelectorAll('.error-msg-visual').forEach(el => el.remove());
        container.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    }

    /* ========================================
       NAVEGAÇÃO ENTRE STEPS
    ======================================== */
    
    window.changeStep = function(atual, proximo) {
        // Valida step atual antes de avançar
        if (proximo > atual && !validarStep(atual)) {
            showToast('⚠️ Preencha todos os campos obrigatórios', 'warning');
            return;
        }
        
        // Muda step
        document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
        const proximoStep = document.querySelector(`.step-content[data-step="${proximo}"]`);
        
        if (proximoStep) {
            proximoStep.classList.add('active');
            currentStep = proximo;
            localStorage.setItem('vg_step', proximo);
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Sincroniza email no step 4
            if (proximo === 4) sincronizarEmail();
            
            salvarProgresso();
        }
    };

    function sincronizarEmail() {
        const emailInput = document.getElementById('email_business');
        const displayInput = document.getElementById('email_confirm_display');
        if (emailInput && displayInput) {
            displayInput.value = emailInput.value;
        }
    }

    /* ========================================
       PERSISTÊNCIA DE DADOS
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
                    el.checked = dados[key] === 'on' || dados[key] === true;
                } else {
                    el.value = dados[key];
                    el.dispatchEvent(new Event('input'));
                }
            });
        });
        
        // Restaura step no business
        if (!formBusiness.hidden && stepSalvo > 1) {
            document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
            const targetStep = document.querySelector(`.step-content[data-step="${stepSalvo}"]`);
            if (targetStep) {
                targetStep.classList.add('active');
                currentStep = stepSalvo;
            }
        }
    }

    /* ========================================
       ALTERNÂNCIA BUSINESS/PESSOA
    ======================================== */
    
    function renderizar() {
        const isBusiness = tipoAtual === 'business';
        
        if (titulo) titulo.textContent = isBusiness ? 'Cadastro de Negócio' : 'Cadastro Pessoal';
        if (formBusiness) formBusiness.hidden = !isBusiness;
        if (formPessoa) formPessoa.hidden = isBusiness;
        
        // Atualiza botões
        if (switchConta) {
            switchConta.querySelectorAll('button').forEach(btn => {
                const ativo = btn.dataset.tipo === tipoAtual;
                btn.classList.toggle('active', ativo);
            });
        }
        
        // Gerencia sliders
        if (isBusiness) {
            if (typeof stopSlide === 'function') stopSlide();
        } else {
            if (typeof startSlide === 'function') startSlide();
        }
        
        salvarProgresso();
    }

    if (switchConta) {
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
                
                // Limpa erros
                limparErros(formBusiness);
                limparErros(formPessoa);
                
                renderizar();
                showToast('✅ Tipo alterado com sucesso', 'success');
                
            } catch (e) {
                showToast('❌ Erro ao alternar tipo', 'error');
            } finally {
                btn.disabled = false;
            }
        });
    }

    /* ========================================
       UTILITÁRIOS DE UI
    ======================================== */
    
    function showToast(mensagem, tipo = 'info', duracao = 3000) {
        const toast = document.createElement('div');
        const cores = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };
        
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${cores[tipo]};
            color: white;
            padding: 14px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            font-size: 14px;
            font-weight: 600;
            animation: slideInRight 0.3s ease-out;
            max-width: 300px;
        `;
        toast.textContent = mensagem;
        document.body.appendChild(toast);
        
        if (duracao > 0) {
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => toast.remove(), 300);
            }, duracao);
        }
        
        return toast;
    }

    function closeToast(toast) {
        if (toast && toast.parentElement) {
            toast.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => toast.remove(), 300);
        }
    }

    window.updateFileName = (input) => {
        const display = input.parentElement.querySelector('.file-selected-name');
        if (input.files.length > 0) {
            display.textContent = `📎 ${input.files[0].name}`;
            display.style.color = '#10b981';
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
        container.style.opacity = checkbox.checked ? '0.5' : '1';
        container.style.pointerEvents = checkbox.checked ? 'none' : 'auto';
    };

    window.checkStrengthBus = (pass) => {
        const bar = document.getElementById('strengthBarBus');
        const txt = document.getElementById('strengthTextBus');
        let s = 0;
        if (pass.length >= 8) s++;
        if (/[A-Z]/.test(pass) && /[a-z]/.test(pass)) s++;
        if (/[0-9]/.test(pass)) s++;
        if (/[^a-zA-Z0-9]/.test(pass)) s++;
        
        const colors = ['#ef4444', '#f59e0b', '#eab308', '#10b981'];
        const labels = ['Fraca ❌', 'Média ⚠️', 'Boa ✅', 'Forte 💪'];
        bar.style.width = `${(s + 1) * 20}%`;
        bar.style.backgroundColor = colors[s] || '#ef4444';
        txt.textContent = labels[s] || 'Muito fraca';
    };

    window.checkStrengthPessoa = window.checkStrengthBus;

    /* ========================================
       AUTO-SAVE AO DIGITAR
    ======================================== */
    
    document.querySelectorAll('input, select, textarea').forEach(el => {
        el.addEventListener('input', () => {
            salvarProgresso();
            
            // Remove erro ao começar a digitar
            el.classList.remove('input-error');
            const parent = el.closest('.person-field-input');
            if (parent) {
                parent.querySelectorAll('.error-msg-visual').forEach(m => m.remove());
            }
            
            // Sincroniza email
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
            
            const config = {
                'BR': { fiscal: 'CNPJ', placeholder: '00.000.000/0000-00', state: 'Estado' },
                'PT': { fiscal: 'NIF', placeholder: '9 dígitos', state: 'Distrito' },
                'MZ': { fiscal: 'NUIT', placeholder: '9 dígitos', state: 'Província' },
                'AO': { fiscal: 'NUIT', placeholder: '9 dígitos', state: 'Província' },
                'US': { fiscal: 'EIN', placeholder: '00-0000000', state: 'State' }
            }[this.value] || { fiscal: 'Tax ID', placeholder: 'Documento fiscal', state: 'Província/Estado' };
            
            if (labelFiscal) labelFiscal.innerHTML = `${config.fiscal} <span style="color: red;">*</span>`;
            if (taxIdInput) taxIdInput.placeholder = config.placeholder;
            if (labelState) labelState.innerHTML = `${config.state} <span style="color: red;">*</span>`;
            if (countryCodeInput) countryCodeInput.value = this.value;
        });
    }

    // Mesmo para pessoa
    const selectPaisPessoa = document.getElementById('select_pais_pessoa');
    if (selectPaisPessoa) {
        selectPaisPessoa.addEventListener('change', function() {
            const labelState = document.getElementById('label_state_pessoa');
            const countryCodeInput = document.getElementById('country_code_input_pessoa');
            
            const config = {
                'BR': 'Estado',
                'PT': 'Distrito',
                'MZ': 'Província',
                'AO': 'Província',
                'US': 'State'
            }[this.value] || 'Província/Estado';
            
            if (labelState) labelState.innerHTML = `${config} <span style="color: red;">*</span>`;
            if (countryCodeInput) countryCodeInput.value = this.value;
        });
    }

    /* ========================================
       SUBMISSÃO AJAX
    ======================================== */
    
    function handleSubmit(form, url) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Validação final
            const isBusiness = form.id === 'formBusiness';
            if (isBusiness && !validarStep(4)) {
                showToast('⚠️ Preencha todos os campos obrigatórios', 'warning');
                return;
            }
            if (!isBusiness && !validarStep(1)) {
                showToast('⚠️ Preencha todos os campos obrigatórios', 'warning');
                return;
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
                    localStorage.clear();
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else if (data.errors) {
                    // Mostra erros do backend
                    Object.keys(data.errors).forEach(field => {
                        const campo = form.querySelector(`[name="${field}"]`);
                        if (campo) {
                            mostrarErro(campo, data.errors[field]);
                        }
                    });
                    showToast('❌ Verifique os campos com erro', 'error');
                } else {
                    showToast(`❌ ${data.error || 'Erro desconhecido'}`, 'error');
                }
            } catch (e) {
                showToast('❌ Erro de conexão', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = oldText;
            }
        });
    }

    if (formBusiness) handleSubmit(formBusiness, '../process/business.store.php');
    if (formPessoa) handleSubmit(formPessoa, '../process/pessoa.store.php');

    /* ========================================
       INICIALIZAÇÃO
    ======================================== */
    
    await carregarPaises();
    renderizar();
    
    // Animações CSS
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
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    `;
    document.head.appendChild(style);
});