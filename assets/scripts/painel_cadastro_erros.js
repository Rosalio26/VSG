/**
 * VISION GREEN - SISTEMA DE CADASTRO REFORÇADO V2
 * Foco: Redirecionamento automático de Steps baseado em erros do Servidor.
 */

document.addEventListener("DOMContentLoaded", () => {
  
  const formPessoa = document.getElementById("formPessoa");
  const formBusiness = document.getElementById("formBusiness");
  const mainContainer = document.querySelector(".main-container");

  if (mainContainer) mainContainer.style.opacity = "0";

  // ============================================================
  // 1. GESTÃO DE ERROS COM NAVEGAÇÃO AUTOMÁTICA
  // ============================================================
  
  function clearErrors(form) {
    form.querySelectorAll(".error-msg, .error-msg-js").forEach(el => el.remove());
    form.querySelectorAll(".input-error").forEach(el => {
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

      input.classList.add("input-error");
      input.style.borderColor = "red";
      
      const parent = input.closest('.person-field-input');
      if (parent) {
          const span = document.createElement("span");
          span.className = "error-msg";
          span.textContent = errors[field];
          span.style.cssText = "color:red; font-size:12px; display:block; margin-top:5px;";
          parent.appendChild(span);
      }

      // Identifica o Step onde este erro está (Apenas para Business)
      if (form.id === 'formBusiness') {
          const stepContainer = input.closest('.step-content');
          if (stepContainer && !stepDoErro) {
              stepDoErro = parseInt(stepContainer.dataset.step);
          }
      }
    }

    // --- LÓGICA DE REDIRECIONAMENTO DE STEP ---
    if (stepDoErro) {
        window.changeStep(parseInt(localStorage.getItem('vg_step')), stepDoErro, true);
    }

    if (primeiroInputComErro) {
      primeiroInputComErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
      primeiroInputComErro.focus();
    }
  }

  // ============================================================
  // 2. VALIDAÇÃO E NAVEGAÇÃO
  // ============================================================

  function validarContainer(container) {
    let valido = true;
    const obrigatorios = container.querySelectorAll('input[required], select[required], textarea[required]');

    obrigatorios.forEach(input => {
        if (input.offsetParent === null) return;

        let valor = input.value.trim();
        let erroDetectado = false;
        let mensagem = 'Campo obrigatório';

        if (valor === "" || valor === "selet") {
            erroDetectado = true;
            if (input.tagName === "SELECT") mensagem = 'Selecione uma opção válida';
        } 
        else if (input.type === "password" && valor.length < 8) {
            erroDetectado = true;
            mensagem = 'A senha deve ter no mínimo 8 caracteres';
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
            input.classList.add('input-error');
            input.style.borderColor = 'red';
            
            const parent = input.closest('.person-field-input');
            if(parent && !parent.querySelector('.error-msg-js')) {
                const msg = document.createElement('span');
                msg.className = 'error-msg-js';
                msg.style.cssText = "color:red; font-size:12px;";
                msg.innerText = mensagem;
                parent.appendChild(msg);
            }
        }
    });
    return valido;
  }

  // Função estendida para suportar saltos forçados por erro (forceJump)
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
  // 3. PERSISTÊNCIA E RESTAURAÇÃO
  // ============================================================
  
  function salvarSessao() {
    const dados = {};
    document.querySelectorAll('input, select, textarea').forEach(el => {
        if ((el.name || el.id) && el.type !== 'password' && el.type !== 'file') {
            dados[el.name || el.id] = el.value;
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
    }

    if (dados) {
        Object.keys(dados).forEach(key => {
            const el = document.getElementsByName(key)[0] || document.getElementById(key);
            if (el && el.type !== 'file') el.value = dados[key];
        });
    }

    if (stepSalvo && !formBusiness.hidden) {
        window.changeStep(1, stepSalvo, true);
    }

    if (mainContainer) {
        mainContainer.style.transition = "opacity 0.3s ease";
        mainContainer.style.opacity = "1";
    }
  }

  // ============================================================
  // 4. SUBMISSÃO AJAX
  // ============================================================

  function handleAjaxSubmit(form, url) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const isBusiness = form.id === 'formBusiness';
      if (isBusiness && !validarStepAtual(4)) return;
      if (!isBusiness && !validarContainer(form)) return;

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn.innerHTML;
      
      clearErrors(form);
      submitBtn.disabled = true;
      submitBtn.innerHTML = "Validando..."; 

      const formData = new FormData(form);
      formData.append("csrf", document.body.dataset.csrf || "");

      try {
        const res = await fetch(url, { 
          method: "POST", 
          body: formData,
          headers: { 'Accept': 'application/json' }
        });

        const data = await res.json();

        if (data.success) {
          submitBtn.innerHTML = "Sucesso! Redirecionando...";
          localStorage.clear();
          window.location.href = data.redirect;
          return;
        }

        if (data.errors) {
            showErrors(form, data.errors);
        } else if (data.error) {
            alert(data.error);
        }

      } catch (err) {
        alert("Falha de conexão com o servidor.");
      } finally {
        if (!submitBtn.innerHTML.includes("Redirecionando")) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
      }
    });
  }

  // ============================================================
  // 5. LISTENERS E INICIALIZAÇÃO
  // ============================================================

  document.querySelectorAll('input, select, textarea').forEach(el => {
      const eventType = el.tagName === 'SELECT' ? 'change' : 'input';
      el.addEventListener(eventType, () => {
          salvarSessao();
          el.classList.remove('input-error');
          el.style.borderColor = "";
          const parent = el.closest('.person-field-input');
          if(parent) parent.querySelectorAll('.error-msg, .error-msg-js').forEach(m => m.remove());
      });
  });

  document.querySelectorAll('.btn-toggle').forEach(btn => {
      btn.addEventListener('click', function() {
          const tipo = this.dataset.tipo;
          formBusiness.hidden = (tipo !== 'business');
          formPessoa.hidden = (tipo === 'business');
          document.querySelectorAll('.btn-toggle').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          salvarSessao();
      });
  });

  fetch('https://restcountries.com/v3.1/all?fields=name,cca2,translations')
    .then(r => r.json())
    .then(data => {
        const selectPais = document.getElementById('select_pais');
        if (selectPais) {
            data.sort((a, b) => (a.translations.por?.common || a.name.common).localeCompare(b.translations.por?.common || b.name.common));
            let opts = '<option value="">Selecione...</option>';
            data.forEach(p => opts += `<option value="${p.cca2}">${p.translations.por?.common || p.name.common}</option>`);
            selectPais.innerHTML = opts;
        }
        restaurarSessao();
    }).catch(() => restaurarSessao());

  if (formPessoa) handleAjaxSubmit(formPessoa, "../process/pessoa.store.php");
  if (formBusiness) handleAjaxSubmit(formBusiness, "../process/business.store.php");
});