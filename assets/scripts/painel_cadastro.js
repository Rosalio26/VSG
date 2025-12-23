document.addEventListener("DOMContentLoaded", () => {

  let tipoAtual = document.body.dataset.tipoInicial;
  const csrfToken = document.body.dataset.csrf;

  const titulo = document.getElementById("titulo");
  const formPessoa = document.getElementById("formPessoa");
  const formBusiness = document.getElementById("formBusiness");
  const switchConta = document.getElementById("switchConta");

  /* ================= UTIL ================= */

  function clearErrors(form) {
    form.querySelectorAll(".error-msg").forEach(el => el.remove());
  }

  function showErrors(form, errors) {
    clearErrors(form);

    for (const field in errors) {
      const input = form.querySelector(`[name="${field}"]`);
      if (!input) continue;

      const span = document.createElement("span");
      span.className = "error-msg";
      span.style.color = "red";
      span.style.fontSize = "0.85em";
      span.textContent = errors[field];

      input.insertAdjacentElement("afterend", span);
    }
  }

  function render() {
    if (tipoAtual === "business") {
      if (titulo) titulo.textContent = "Cadastro de Negócio";
      if (formBusiness) formBusiness.hidden = false;
      if (formPessoa) formPessoa.hidden = true;
    } else {
      if (titulo) titulo.textContent = "Cadastro Pessoal";
      if (formPessoa) formPessoa.hidden = false;
      if (formBusiness) formBusiness.hidden = true;
    }
  }

  /* ================= SWITCH (DESKTOP) ================= */

  if (switchConta) {
    switchConta.addEventListener("click", async (e) => {
      const tipo = e.target.dataset.tipo;
      if (!tipo || tipo === tipoAtual) return;

      try {
        const res = await fetch("../../registration/ajax/switch-tipo.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body:
            "tipo=" + encodeURIComponent(tipo) +
            "&csrf=" + encodeURIComponent(csrfToken),
        });

        const data = await res.json();

        if (!res.ok || data.error) {
          alert("Erro ao trocar tipo de cadastro.");
          return;
        }

        tipoAtual = data.tipo;
        render();

      } catch (err) {
        console.error(err);
        alert("Erro de conexão com o servidor.");
      }
    });
  }

  /* ================= SUBMIT AJAX ================= */

  function handleAjaxSubmit(form, url) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      clearErrors(form);

      const formData = new FormData(form);
      formData.append("csrf", csrfToken);

      try {
        const res = await fetch(url, {
          method: "POST",
          body: formData,
        });

        let data;
        try {
          data = await res.json();
        } catch {
          alert("Resposta inválida do servidor.");
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

        alert("Erro inesperado.");

      } catch (err) {
        console.error(err);
        alert("Erro de conexão com o servidor.");
      }
    });
  }

  if (formPessoa) {
    handleAjaxSubmit(formPessoa, "../process/pessoa.store.php");
  }

  if (formBusiness) {
    handleAjaxSubmit(formBusiness, "../process/cadastro.process.php");
  }

  render();
});
