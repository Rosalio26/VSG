document.addEventListener("DOMContentLoaded", () => {
  const formPessoa = document.getElementById("formPessoa");
  const formBusiness = document.getElementById("formBusiness");

  /**
   * Limpa os estados de erro visuais
   */
  function clearErrors(form) {
    form.querySelectorAll(".error-msg").forEach(el => el.remove());
    form.querySelectorAll("input, select, textarea").forEach(input => {
      input.classList.remove("input-error");
      input.style.borderColor = ""; 
    });
  }

  /**
   * Aplica o destaque visual nos campos com erro
   */
  function showErrors(form, errors) {
    clearErrors(form);
    for (const field in errors) {
      const input = form.querySelector(`[name="${field}"]`);
      if (!input) continue;
      input.classList.add("input-error");
      const span = document.createElement("span");
      span.className = "error-msg";
      span.textContent = errors[field];
      input.insertAdjacentElement("afterend", span);
    }
    const firstError = form.querySelector(".input-error");
    if (firstError) {
      firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  /**
   * Gerencia o envio AJAX dos formulários
   */
  function handleAjaxSubmit(form, url) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

        // --- NOVA LÓGICA DE CAPTURA FINAL ---
      const inputTel = form.querySelector("#telefone_input");
      const hiddenTel = form.querySelector("#telefone_final");
      
      if (inputTel && hiddenTel) {
          // Pega a instância correta (pessoa ou business) que criamos no outro arquivo
          const type = (form.id === "formPessoa") ? "pessoa" : "business";
          const itiInstance = window.visionGreenIti[type];
          
          if (itiInstance) {
              hiddenTel.value = itiInstance.getNumber(); // Garante o formato +244...
          }
      }
      // ------------------------------------

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn.textContent;
      
      clearErrors(form);

      submitBtn.disabled = true;
      submitBtn.textContent = "Processando...";

      const formData = new FormData(form);
      if (!formData.has("csrf")) {
        const csrfToken = document.body.dataset.csrf || "";
        formData.append("csrf", csrfToken);
      }

      try {
        const res = await fetch(url, { 
          method: "POST", 
          body: formData,
          headers: { 'Accept': 'application/json' }
        });

        const responseText = await res.text();
        let data;
        try {
          data = JSON.parse(responseText);
        } catch (err) {
          console.error("Resposta não é JSON:", responseText);
          alert("Erro técnico: Resposta inválida.");
          return;
        }

        if (data.success && data.redirect) {
          window.location.href = data.redirect;
          return;
        }

        if (data.errors) {
          showErrors(form, data.errors);
          return;
        }

        if (data.error) {
          alert(data.error);
          return;
        }

        alert("Não foi possível processar o cadastro.");
      } catch (err) {
        console.error("Erro AJAX:", err);
        alert("Falha de conexão.");
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText;
      }
    });
  }

  if (formPessoa) handleAjaxSubmit(formPessoa, "../process/pessoa.store.php");
  if (formBusiness) handleAjaxSubmit(formBusiness, "../process/cadastro.process.php");
});