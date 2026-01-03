/**
 * VISION GREEN - SISTEMA DE CADASTRO CENTRALIZADO V2.2
 * Foco: Persistência no Reload, Notificação de Processamento e Sincronização de Dados.
 */

document.addEventListener("DOMContentLoaded", () => {
  const formPessoa = document.getElementById("formPessoa");
  const formBusiness = document.getElementById("formBusiness");
  const mainContainer = document.querySelector(".main-container");

  if (mainContainer) mainContainer.style.opacity = "0";

  // ============================================================
  // 1. GESTÃO DE ERROS E LIMPEZA
  // ============================================================
  
  function clearErrors(form) {
    form.querySelectorAll(".error-msg, .error-msg-js").forEach(el => el.remove());
    form.querySelectorAll(".input-error, .custom-file-upload, .iti").forEach(el => {
      el.classList.remove("input-error");
      el.style.borderColor = ""; 
    });
  }

  function showErrors(form, errors) {
    clearErrors(form);
    let primeiroInputComErro = null;
    let stepDoErro = null;

    for (const field in errors) {
      let input = form.querySelector(`[name="${field}"]`);
      if (!input) continue;

      if (!primeiroInputComErro) primeiroInputComErro = input;

      // Estiliza erro conforme o container (suporte a arquivos e telefone ITI)
      if (input.type === 'file') {
        const customArea = input.closest('.custom-file-upload');
        if (customArea) customArea.classList.add("input-error");
      } else if (input.closest('.iti')) {
        input.closest('.iti').classList.add("input-error");
      } else {
        input.classList.add("input-error");
        input.style.borderColor = "red";
      }
      
      const parent = input.closest('.person-field-input');
      if (parent) {
          const span = document.createElement("span");
          span.className = "error-msg";
          span.textContent = errors[field];
          span.style.cssText = "color:red; font-size:12px; display:block; margin-top:5px;";
          parent.appendChild(span);
      }

      if (form.id === 'formBusiness') {
          const stepContainer = input.closest('.step-content');
          if (stepContainer && !stepDoErro) {
              stepDoErro = parseInt(stepContainer.dataset.step);
          }
      }
    }

    if (stepDoErro) {
        window.changeStep(parseInt(localStorage.getItem('vg_step')), stepDoErro, true);
    }

    if (primeiroInputComErro) {
      primeiroInputComErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
      primeiroInputComErro.focus();
    }
  }

  // ============================================================
  // 2. SINCRONIZAÇÃO DE DADOS (EMAIL AUTOMÁTICO)
  // ============================================================

  function sincronizarEmailDisplay() {
      const emailInput = document.getElementById('email_business');
      const displayInput = document.getElementById('email_confirm_display');
      if (emailInput && displayInput) {
          displayInput.value = emailInput.value;
      }
  }

  // ============================================================
  // 3. VALIDAÇÃO DE CAMPOS E STEPS
  // ============================================================

  function validarContainer(container) {
    let valido = true;
    const obrigatorios = container.querySelectorAll('input[required], select[required], textarea[required]');

    obrigatorios.forEach(input => {
        // Ignora campos que não estão visíveis na etapa atual
        if (input.offsetParent === null) return;

        // Regra específica para ignorar Logo se o checkbox "no_logo" estiver marcado
        if (input.name === 'logo' && document.getElementsByName('no_logo')[0]?.checked) return;

        let valor = input.value.trim();
        let erroDetectado = false;
        let mensagem = 'Campo obrigatório';

        // 1. VALIDAÇÃO DE ARQUIVOS (Alvará, Logo, Tax ID File, etc)
        if (input.type === 'file') {
            if (input.files.length === 0) {
                erroDetectado = true;
                mensagem = 'Por favor, selecione um arquivo.';
            } else {
                const arquivo = input.files[0];
                const extensao = arquivo.name.split('.').pop().toLowerCase();
                const extensoesPermitidas = ['png', 'jpg', 'jpeg', 'pdf'];
                const tamanhoMaximo = 5 * 1024 * 1024; // 5MB

                if (!extensoesPermitidas.includes(extensao)) {
                    erroDetectado = true;
                    mensagem = 'Formato inválido. Use PNG, JPEG ou PDF.';
                } else if (arquivo.size > tamanhoMaximo) {
                    erroDetectado = true;
                    mensagem = 'O arquivo excede o limite de 5MB.';
                }
            }
        }
        // 2. VALIDAÇÃO DE TELEFONE (Exige o "+")
        else if (input.type === "tel" && !valor.startsWith('+')) {
            erroDetectado = true;
            mensagem = 'Insira o codigo do pais (ex: +258) e o número';
        }
        // 3. VALIDAÇÃO DE SENHA (Mínimo 8 caracteres)
        else if (input.type === "password" && valor.length < 8) {
            erroDetectado = true;
            mensagem = 'A senha deve ter no mínimo 8 caracteres';
        }
        // 4. CONFIRMAÇÃO DE SENHA
        else if (input.name === "password_confirm") {
            const senhaOriginal = container.querySelector('input[name="password"]')?.value;
            if (valor !== senhaOriginal) {
                erroDetectado = true;
                mensagem = 'As senhas não coincidem';
            }
        }
        // 5. CAMPOS VAZIOS OU SELECT NÃO SELECIONADO
        else if (valor === "" || valor === "selet") {
            erroDetectado = true;
            if (input.tagName === "SELECT") mensagem = 'Selecione uma opção válida';
        }

        // --- APLICAÇÃO VISUAL DO ERRO ---
        if (erroDetectado) {
            valido = false;
            
            const target = input.type === 'file' ? input.closest('.custom-file-upload') : (input.closest('.iti') || input);
            if (target) target.classList.add('input-error');
            
            const parent = input.closest('.person-field-input');
            if (parent && !parent.querySelector('.error-msg-js')) {
                const msg = document.createElement('span');
                msg.className = 'error-msg-js';
                msg.style.color = "red";
                msg.style.fontSize = "12pt";
                msg.style.display = "block";
                msg.style.marginTop = "5px";
                msg.style.padding = "5px 0";
                msg.innerText = mensagem;
                parent.appendChild(msg);
            }
        }
    });

    return valido;
  }

  window.changeStep = function(atual, proximo, forceJump = false) {
    if (!forceJump && proximo > atual && !validarStepAtual(atual)) return;

    document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
    const proximoEl = document.querySelector(`.step-content[data-step="${proximo}"]`);
    
    if (proximoEl) {
        proximoEl.classList.add('active');
        localStorage.setItem('vg_step', proximo);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        sincronizarEmailDisplay();
    }
  };

  function validarStepAtual(step) {
    const container = document.querySelector(`.step-content[data-step="${step}"]`);
    return container ? validarContainer(container) : true;
  }

  // ============================================================
  // 4. PERSISTÊNCIA (RELOAD INTELIGENTE)
  // ============================================================
  
  function salvarSessao() {
    const dados = {};
    document.querySelectorAll('input, select, textarea').forEach(el => {
        if (!el.name || el.type === 'password' || el.type === 'file') return;
        
        if (el.type === 'radio' || el.type === 'checkbox') {
            if (el.checked) dados[el.name] = el.value;
        } else {
            dados[el.name] = el.value;
        }
    });
    localStorage.setItem('vg_data', JSON.stringify(dados));
    localStorage.setItem('vg_type', formBusiness.hidden ? 'pessoal' : 'business');
  }

  function restaurarSessao() {
    const dados = JSON.parse(localStorage.getItem('vg_data'));
    const tipoSalvo = localStorage.getItem('vg_type');
    const stepSalvo = parseInt(localStorage.getItem('vg_step'));

    if (tipoSalvo) {
        const isBus = tipoSalvo === 'business';
        formBusiness.hidden = !isBus;
        formPessoa.hidden = isBus;
        document.querySelectorAll('.btn-toggle').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tipo === tipoSalvo);
        });
        document.getElementById('titulo').innerText = isBus ? 'Cadastro de Negócio' : 'Cadastro Pessoal';
    }

    if (dados) {
        Object.keys(dados).forEach(key => {
            const inputs = document.querySelectorAll(`[name="${key}"], #${key}`);
            inputs.forEach(el => {
                if (el.type === 'radio') {
                    if (el.value === dados[key]) { el.checked = true; el.click(); }
                } else if (el.type === 'checkbox') {
                    el.checked = true;
                    el.dispatchEvent(new Event('change'));
                } else {
                    el.value = dados[key];
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        });
    }

    if (stepSalvo && !formBusiness.hidden) window.changeStep(1, stepSalvo, true);
    sincronizarEmailDisplay();

    if (mainContainer) {
        mainContainer.style.transition = "opacity 0.3s ease";
        mainContainer.style.opacity = "1";
    }
  }

  // ============================================================
  // 5. LÓGICAS DE UI (UPLOAD, FISCAL, SENHA, SLIDERS)
  // ============================================================

  window.updateFileName = (input) => {
    const fileNameDisplay = input.parentElement.querySelector('.file-selected-name');
    if (input.files.length > 0) {
      fileNameDisplay.textContent = "Selecionado: " + input.files[0].name;
      fileNameDisplay.style.color = "#28a745";
    } else {
      fileNameDisplay.textContent = "";
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
        fileInput.value = ""; // Limpa seleção se mudar de modo
        fileArea.querySelector('.file-selected-name').textContent = "";
    } else {
        input.style.display = 'none'; 
        input.required = false;
        input.value = ""; // Limpa texto se mudar de modo
        fileArea.style.display = 'block';
        fileInput.required = true;
    }
  };

  window.toggleLogoRequired = (checkbox) => {
    const logoInput = document.getElementById('input_logo');
    const container = document.getElementById('logo_container');
    logoInput.required = !checkbox.checked;
    logoInput.disabled = checkbox.checked;
    container.style.opacity = checkbox.checked ? "0.5" : "1";
    container.style.pointerEvents = checkbox.checked ? "none" : "auto";
  };

  window.checkStrengthBus = (pass) => {
    const bar = document.getElementById('strengthBarBus');
    const txt = document.getElementById('strengthTextBus');
    let s = 0;
    if (pass.length >= 8) s++;
    if (pass.match(/[A-Z]/) && pass.match(/[a-z]/)) s++;
    if (pass.match(/[0-9]/)) s++;
    if (pass.match(/[^a-zA-Z0-9]/)) s++;
    const colors = ['#eee', '#ff4d4d', '#ffd633', '#2ecc71', '#27ae60'];
    const labels = ['Senha', 'Muito fraca ❌', 'Razoável ⚠️', 'Forte ✅', 'Muito forte 💪'];
    bar.style.width = (s * 25) + '%';
    bar.style.backgroundColor = colors[s];
    txt.innerHTML = labels[s];
  };

  // Sliders
  let slideInterval = null; 
  let currentIndex = 0;
  const slidesPessoal = document.querySelectorAll('.slide-pessoal');
  const imgBusiness = document.getElementById('img-business-fixa');

  function startSlide() {
    if(!slidesPessoal.length) return;
    stopSlide();
    if(imgBusiness) imgBusiness.classList.remove('active');
    slidesPessoal[currentIndex].classList.add('active');
    slideInterval = setInterval(() => {
        slidesPessoal[currentIndex].classList.remove('active');
        currentIndex = (currentIndex + 1) % slidesPessoal.length;
        slidesPessoal[currentIndex].classList.add('active');
    }, 5000); 
  }

  function stopSlide() {
    if(slideInterval) clearInterval(slideInterval);
    slideInterval = null;
    slidesPessoal.forEach(s => s.classList.remove('active'));
    if(imgBusiness) imgBusiness.classList.add('active');
  }

  // ============================================================
  // 6. SUBMISSÃO COM NOTIFICAÇÃO DE CÓDIGO
  // ============================================================

  function handleAjaxSubmit(form, url) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const isBus = form.id === 'formBusiness';
      if (isBus && !validarStepAtual(4)) return;
      if (!isBus && !validarContainer(form)) return;

      const btn = form.querySelector('button[type="submit"]');
      const oldTxt = btn.innerHTML;
      
      btn.disabled = true; 
      btn.innerHTML = "<span>Gerando código de acesso...</span>";
      clearErrors(form);

      try {
        const res = await fetch(url, { method: "POST", body: new FormData(form) });
        const data = await res.json();
        
        if (data.success) {
            btn.innerHTML = "Enviando e-mail...";
            localStorage.clear();
            window.location.href = data.redirect;
        } else if (data.errors) {
            showErrors(form, data.errors);
            btn.disabled = false;
            btn.innerHTML = oldTxt;
        } else {
            alert(data.error || "Erro desconhecido");
            btn.disabled = false;
            btn.innerHTML = oldTxt;
        }
      } catch (err) { 
          alert("Falha na conexão."); 
          btn.disabled = false;
          btn.innerHTML = oldTxt;
      }
    });
  }

  document.querySelectorAll('.btn-toggle').forEach(btn => {
      btn.addEventListener('click', function() {
          const t = this.dataset.tipo;
          formBusiness.hidden = (t !== 'business');
          formPessoa.hidden = (t === 'business');
          document.querySelectorAll('.btn-toggle').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          document.getElementById('titulo').innerText = (t === 'business') ? 'Cadastro de Negócio' : 'Cadastro Pessoal';
          if (t === 'pessoal') startSlide(); else stopSlide();
          salvarSessao();
      });
  });

  document.querySelectorAll('input, select, textarea').forEach(el => {
      el.addEventListener('input', () => {
          salvarSessao();
          el.classList.remove('input-error');
          const p = el.closest('.person-field-input');
          if(p) p.querySelectorAll('.error-msg, .error-msg-js').forEach(m => m.remove());
          if (el.id === 'email_business') sincronizarEmailDisplay();
      });
  });

  // Carregamento de Países
  fetch('https://restcountries.com/v3.1/all?fields=name,cca2,translations')
    .then(r => r.json()).then(data => {
        const s = document.getElementById('select_pais');
        if (s) {
            data.sort((a,b) => (a.translations.por?.common || a.name.common).localeCompare(b.translations.por?.common || b.name.common));
            let h = '<option value="">Selecione...</option>';
            data.forEach(p => h += `<option value="${p.cca2}">${p.translations.por?.common || p.name.common}</option>`);
            s.innerHTML = h;
        }
        restaurarSessao();
    }).catch(() => restaurarSessao());

  handleAjaxSubmit(formPessoa, "../process/pessoa.store.php");
  handleAjaxSubmit(formBusiness, "../process/business.store.php");
  
  const tipoIni = document.body.dataset.tipoInicial;
  if(tipoIni === 'pessoal') startSlide(); else stopSlide();
});