document.addEventListener("DOMContentLoaded", () => {
  let tipoAtual = document.body.dataset.tipoInicial;
  const csrfToken = document.body.dataset.csrf;

  const titulo = document.getElementById("titulo");
  const formPessoa = document.getElementById("formPessoa");
  const formBusiness = document.getElementById("formBusiness");
  const switchConta = document.getElementById("switchConta");

  function render() {
    if (tipoAtual === "business") {
      if (titulo) titulo.textContent = "Cadastro de Negócio";
      if (formBusiness) formBusiness.hidden = false;
      if (formPessoa) formPessoa.hidden = true;
    } else {
      if (titulo) titulo.textContent = "Cadastro de Pessoa";
      if (formPessoa) formPessoa.hidden = false;
      if (formBusiness) formBusiness.hidden = true;
    }
  }

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
          console.error("Erro:", data.error);

          if (data.error === "csrf") {
            alert("Sessão expirada. Recarregue a página.");
          } else if (data.error === "device") {
            alert("Troca não permitida neste dispositivo.");
          } else {
            alert("Erro inesperado.");
          }
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

  render();
});
