document.addEventListener("DOMContentLoaded", () => {
  let tipoAtual = document.body.dataset.tipoInicial;
  const csrfToken = document.body.dataset.csrf;

  const titulo = document.getElementById("titulo");
  const formPessoa = document.getElementById("formPessoa");
  const formBusiness = document.getElementById("formBusiness");
  const switchConta = document.getElementById("switchConta");

  // Função auxiliar para limpar erros (caso o script de erros esteja carregado)
  function resetFormErrors() {
    if (typeof clearErrors === "function") {
      if (formPessoa) clearErrors(formPessoa);
      if (formBusiness) clearErrors(formBusiness);
    }
  }

  function render() {
    const isBusiness = tipoAtual === "business";

    if (titulo) {
      titulo.textContent = isBusiness ? "Cadastro de Negócio" : "Cadastro Pessoal";
    }

    if (formBusiness) formBusiness.hidden = !isBusiness;
    if (formPessoa) formPessoa.hidden = isBusiness;

    // Atualiza a classe ativa nos botões do switch
    if (switchConta) {
      switchConta.querySelectorAll("button").forEach(btn => {
        if (btn.dataset.tipo === tipoAtual) {
          btn.classList.add("active");
          btn.style.backgroundColor = "#28a745"; // Destaque visual simples
          btn.style.color = "#fff";
        } else {
          btn.classList.remove("active");
          btn.style.backgroundColor = "";
          btn.style.color = "";
        }
      });
    }
  }

  if (switchConta) {
    switchConta.addEventListener("click", async (e) => {
      const btn = e.target.closest("button"); // Garante que pegamos o botão mesmo se clicar no texto
      if (!btn) return;

      const tipo = btn.dataset.tipo;
      if (!tipo || tipo === tipoAtual) return;

      // Desabilita o botão para evitar múltiplos cliques
      btn.disabled = true;

      try {
        const res = await fetch("../../registration/ajax/switch-tipo.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `tipo=${encodeURIComponent(tipo)}&csrf=${encodeURIComponent(csrfToken)}`,
        });

        // Tenta ler a resposta com segurança
        const responseText = await res.text();
        let data;
        try {
          data = JSON.parse(responseText);
        } catch (err) {
          throw new Error("Resposta inválida do servidor.");
        }

        if (!res.ok || data.error) {
          alert(data.error || "Erro ao trocar tipo de cadastro.");
          return;
        }

        // Sucesso na troca
        tipoAtual = data.tipo;
        resetFormErrors(); // Limpa alertas da aba anterior
        render();

      } catch (err) {
        console.error("Erro no Switch:", err);
        alert("Erro de conexão ou sessão expirada.");
      } finally {
        btn.disabled = false;
      }
    });
  }

  // Inicializa a tela
  render();
});