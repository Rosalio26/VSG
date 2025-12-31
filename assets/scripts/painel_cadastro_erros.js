/**
 * VISION GREEN - SISTEMA DE CADASTRO CENTRALIZADO V2
 * Centraliza validações, navegação de steps, uploads e efeitos visuais.
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
    form.querySelectorAll(".input-error, .custom-file-upload").forEach(el => {
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

      // Estiliza erro conforme tipo do campo
      if (input.type === 'file') {
        const customArea = input.closest('.custom-file-upload');
        if (customArea) customArea.classList.add("input-error");
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
  // 2. VALIDAÇÃO DE CAMPOS E STEPS
  // ============================================================

  function validarContainer(container) {
    let valido = true;
    const obrigatorios = container.querySelectorAll('input[required], select[required], textarea[required]');

    obrigatorios.forEach(input => {
        if (input.offsetParent === null) return; // Ignora se estiver oculto (ex: modo fiscal alternativo)

        // Lógica para Logo opcional via Checkbox
        if (input.name === 'logo' && document.getElementsByName('no_logo')[0]?.checked) return;

        let valor = input.value.trim();
        let erroDetectado = false;
        let mensagem = 'Campo obrigatório';

        if (valor === "" || valor === "selet") {
            erroDetectado = true;
            if (input.tagName === "SELECT") mensagem = 'Selecione uma opção válida';
        } 
        else if (input.type === "password" && valor.length < 8) {
            erroDetectado = true;
            mensagem = 'Mínimo 8 caracteres';
        }
        else if (input.name === "password_confirm") {
            const senhaOriginal = container.querySelector('input[name="password"]')?.value;
            if (valor !== senhaOriginal) {
                erroDetectado = true;
                mensagem = 'As senhas não coincidem';
            }
        }

        if (erroDetectado) {
            valido = false;
            const target = input.type === 'file' ? input.closest('.custom-file-upload') : input;
            if (target) target.classList.add('input-error');
            
            const parent = input.closest('.person-field-input');
            if(parent && !parent.querySelector('.error-msg-js')) {
                const msg = document.createElement('span');
                msg.className = 'error-msg-js';
                msg.style.cssText = "color:red; font-size:11px;";
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
    }
  };

  function validarStepAtual(step) {
    const container = document.querySelector(`.step-content[data-step="${step}"]`);
    return container ? validarContainer(container) : true;
  }

  // ============================================================
  // 3. PERSISTÊNCIA (LOCALSTORAGE)
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
            const el = document.getElementsByName(key)[0] || document.getElementById(key);
            if (!el) return;

            if (el.type === 'radio') {
                const radio = document.querySelector(`input[name="${key}"][value="${dados[key]}"]`);
                if (radio) { radio.checked = true; radio.click(); }
            } else if (el.type === 'checkbox') {
                el.checked = true;
                el.dispatchEvent(new Event('change'));
            } else {
                el.value = dados[key];
            }
        });
    }

    if (stepSalvo && !formBusiness.hidden) window.changeStep(1, stepSalvo, true);

    if (mainContainer) {
        mainContainer.style.transition = "opacity 0.3s ease";
        mainContainer.style.opacity = "1";
    }
  }

  // ============================================================
  // 4. LÓGICAS DE UI (UPLOAD, FISCAL, SENHA, SLIDERS)
  // ============================================================

  window.updateFileName = (input) => {
    const fileNameDisplay = input.parentElement.querySelector('.file-selected-name');
    if (input.files.length > 0) {
        fileNameDisplay.textContent = "Selecionado: " + input.files[0].name;
    } else {
        fileNameDisplay.textContent = "";
    }
  };

  window.toggleFiscalMode = (mode) => {
    const input = document.getElementById('tax_id');
    const fileArea = document.getElementById('area_tax_file');
    const fileInput = document.getElementById('tax_id_file');
    if (mode === 'text') {
        input.style.display = 'block'; input.required = true;
        fileArea.style.display = 'none'; fileInput.required = false;
    } else {
        input.style.display = 'none'; input.required = false;
        fileArea.style.display = 'block'; fileInput.required = true;
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
    const labels = ['Força da senha', 'Muito fraca ❌', 'Razoável ⚠️', 'Forte ✅', 'Muito forte 💪'];
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
  // 5. SUBMISSÃO E INICIALIZAÇÃO
  // ============================================================

  function handleAjaxSubmit(form, url) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const isBus = form.id === 'formBusiness';
      if (isBus && !validarStepAtual(4)) return;
      if (!isBus && !validarContainer(form)) return;

      const btn = form.querySelector('button[type="submit"]');
      const oldTxt = btn.innerHTML;
      btn.disabled = true; btn.innerHTML = "Processando...";
      clearErrors(form);

      try {
        const res = await fetch(url, { method: "POST", body: new FormData(form) });
        const data = await res.json();
        if (data.success) {
            localStorage.clear();
            window.location.href = data.redirect;
        } else if (data.errors) {
            showErrors(form, data.errors);
        } else {
            alert(data.error || "Erro desconhecido");
        }
      } catch (err) { alert("Falha na conexão."); }
      finally { if(!window.location.href.includes("verify")) { btn.disabled = false; btn.innerHTML = oldTxt; } }
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
      });
  });

  // Países
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