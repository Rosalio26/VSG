document.addEventListener("DOMContentLoaded", () => {
  const tipoInicial = document.body.dataset.tipoInicial;
  let tipoAtual = tipoInicial;

  const titulo = document.getElementById("titulo");
  const formPessoa = document.getElementById("formPessoa");
  const formBusiness = document.getElementById("formBusiness");
  const switchConta = document.getElementById("switchConta");
  const csrfToken = document.body.dataset.csrf;

  function render() {
    if (tipoAtual === "business") {
      if (titulo) titulo.textContent = "Cadastro de Negócio";
      if (formPessoa) formPessoa.hidden = true;
      if (formBusiness) formBusiness.hidden = false;
    } else {
      if (titulo) titulo.textContent = "Cadastro de Pessoa";
      if (formPessoa) formPessoa.hidden = false;
      if (formBusiness) formBusiness.hidden = true;
    }
  }

  if (switchConta) {
    switchConta.addEventListener("click", async (e) => {
      const tipo = e.target.dataset.tipo;
      if (!tipo) return;

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

        if (!res.ok) {
          alert("Troca não permitida");
          return;
        }

        tipoAtual = tipo;
        render();
      } catch (err) {
        console.error(err);
        alert("Erro de conexão");
      }
    });
  }

  render();
});
