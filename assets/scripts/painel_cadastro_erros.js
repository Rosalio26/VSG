/**
 * VISION GREEN - SISTEMA DE CADASTRO REFORÇADO
 * Foco: Zero Layout Shift (Refresh Invisível) e UX de Erros Dinâmica.
 */

document.addEventListener("DOMContentLoaded", () => {
  
  // ============================================================
  // 1. VARIÁVEIS E CONTROLE DE VISIBILIDADE INICIAL
  // ============================================================
  const formPessoa = document.getElementById("formPessoa");
  const formBusiness = document.getElementById("formBusiness");
  const mainContainer = document.querySelector(".main-container");

  // Esconde o container para evitar que o usuário veja a troca de campos/steps no refresh
  if (mainContainer) mainContainer.style.opacity = "0";

  // ============================================================
  // 2. FUNÇÕES DE ERRO (VISUAL & DINÂMICO)
  // ============================================================
  
  function clearErrors(form) {
    form.querySelectorAll(".error-msg, .error-msg-js").forEach(el => el.remove());
    form.querySelectorAll(".field-error").forEach(el => el.classList.remove("field-error"));
    form.querySelectorAll("input, select, textarea").forEach(input => {
      input.classList.remove("input-error");
      input.style.borderColor = ""; 
    });
  }

  function showErrors(form, errors) {
    clearErrors(form);
    for (const field in errors) {
      let input = form.querySelector(`[name="${field}"]`);
      if (!input) continue;

      input.classList.add("input-error");
      input.style.borderColor = "red";
      
      const parent = input.closest('.person-field-input');
      if (parent) {
          parent.classList.add('field-error');
          const span = document.createElement("span");
          span.className = "error-msg";
          span.textContent = errors[field];
          span.style.cssText = "color:red; font-size:12px; display:block; margin-top:5px;";
          parent.appendChild(span);
      }
    }
    const firstError = form.querySelector(".input-error");
    if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  // ============================================================
  // 3. VALIDAÇÃO DE CONTAINER (STEPS OU FORMS)
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

    if(!valido) {
        const erro = container.querySelector('.input-error');
        if(erro) erro.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return valido;
  }

  window.changeStep = function(atual, proximo) {
    if (proximo > atual && !validarStepAtual(atual)) return;

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
  // 4. PERSISTÊNCIA (RESTAURAÇÃO SEM FLICKER)
  // ============================================================
  
  function salvarSessao() {
    const dados = {};
    document.querySelectorAll('input, select, textarea').forEach(el => {
        if ((el.name || el.id) && el.type !== 'password' && el.type !== 'file') {
            dados[el.name || el.id] = el.value;
        }
    });
    localStorage.setItem('vg_data', JSON.stringify(dados));
    // Salva também qual formulário estava aberto
    localStorage.setItem('vg_type', formBusiness.hidden ? 'pessoal' : 'business');
  }

  function restaurarSessao() {
    const dados = JSON.parse(localStorage.getItem('vg_data'));
    const tipoSalvo = localStorage.getItem('vg_type');
    const stepSalvo = parseInt(localStorage.getItem('vg_step'));

    // 1. Restaura o tipo de aba primeiro para evitar o flicker de troca
    if (tipoSalvo) {
        const isBus = tipoSalvo === 'business';
        formBusiness.hidden = !isBus;
        formPessoa.hidden = isBus;
        document.querySelectorAll('.btn-toggle').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tipo === tipoSalvo);
        });
    }

    // 2. Preenche os valores
    if (dados) {
        Object.keys(dados).forEach(key => {
            const el = document.getElementsByName(key)[0] || document.getElementById(key);
            if (el && el.type !== 'file') el.value = dados[key];
        });
    }

    // 3. Restaura o Step se for Business
    if (stepSalvo && !formBusiness.hidden) {
        window.changeStep(1, stepSalvo);
    }

    // 4. FINALMENTE: Revela o conteúdo suavemente
    if (mainContainer) {
        mainContainer.style.transition = "opacity 0.3s ease";
        mainContainer.style.opacity = "1";
    }
  }

  // Monitora digitação para limpar erros IMEDIATAMENTE
  document.querySelectorAll('input, select, textarea').forEach(el => {
      const evento = el.tagName === 'SELECT' ? 'change' : 'input';
      el.addEventListener(evento, () => {
          salvarSessao();
          
          if (el.value.trim() !== "" && el.value.trim() !== "selet") {
              el.classList.remove('input-error');
              el.style.borderColor = "";
              const parent = el.closest('.person-field-input');
              if(parent) {
                  parent.querySelectorAll('.error-msg, .error-msg-js').forEach(m => m.remove());
              }
          }
      });
  });

  // ============================================================
  // 5. SUBMISSÃO AJAX
  // ============================================================

  function handleAjaxSubmit(form, url) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      if (form.id === 'formBusiness' && !validarStepAtual(3)) return;
      if (form.id === 'formPessoa' && !validarContainer(form)) return;

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
          window.location.href = data.redirect || window.location.reload();
          return;
        }

        if (data.errors) showErrors(form, data.errors);
        else if (data.error) alert(data.error);

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
  // 6. INICIALIZAÇÃO
  // ============================================================

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

  // Carrega países e inicia restauração só no final para evitar saltos no Select
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