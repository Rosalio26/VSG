document.addEventListener("DOMContentLoaded", () => {

  const csrfToken = document.body.dataset.csrf;
  const formPessoa = document.getElementById("formPessoa");
  const formBusiness = document.getElementById("formBusiness");

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

  function handleAjaxSubmit(form, url) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      clearErrors(form);

      const formData = new FormData(form);
      formData.append("csrf", csrfToken);

      try {
        const res = await fetch(url, { method: "POST", body: formData });

        let data;
        try { data = await res.json(); }
        catch {
          alert("Resposta inválida do servidor.");
          return;
        }

        if (data.success && data.redirect) {
          // Novo fluxo: redireciona para a página de aviso de confirmação de email
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

  if (formPessoa) handleAjaxSubmit(formPessoa, "../process/pessoa.store.php");
  if (formBusiness) handleAjaxSubmit(formBusiness, "../process/cadastro.process.php");

});
