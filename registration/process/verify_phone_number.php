<?php
session_start();
require_once '../includes/db.php'; // Sua conexão com banco

// 1. LÓGICA DE PROCESSAMENTO (Executa quando o formulário é enviado)
$erro = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo_final'])) {
    $codigo_digitado = $_POST['codigo_final'];
    $dados_sessao = $_SESSION['verificacao'] ?? null;

    if (!$dados_sessao) {
        $erro = "Sessão expirada. Por favor, tente se cadastrar novamente.";
    } elseif (time() > $dados_sessao['expira_em']) {
        $erro = "O código expirou. Solicite um novo envio.";
    } elseif ($codigo_digitado === $dados_sessao['codigo']) {
        // SUCESSO: Ativar conta no banco
        $stmt = $pdo->prepare("UPDATE usuarios SET status = 'ativo', verificado_em = NOW() WHERE email = ?");
        $stmt->execute([$dados_sessao['email']]);
        
        // Limpa a sessão de verificação e redireciona
        unset($_SESSION['verificacao']);
        header("Location: ../dashboard/welcome.php?status=sucesso");
        exit;
    } else {
        $erro = "Código incorreto. Verifique o SMS e tente novamente.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Verificação de Segurança - VisionGreen</title>
    <link rel="stylesheet" href="../../assets/style/geral.css">
    <style>
        .verify-box { max-width: 400px; margin: 100px auto; text-align: center; padding: 30px; border-radius: 12px; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .code-inputs { display: flex; justify-content: center; gap: 10px; margin: 25px 0; }
        .code-inputs input { width: 45px; height: 55px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid #e2e8f0; border-radius: 8px; color: var(--color-bg-104); transition: all 0.3s; }
        .code-inputs input:focus { border-color: var(--color-bg-104); outline: none; box-shadow: 0 0 8px rgba(0,166,62,0.2); }
        .btn-verify { width: 100%; padding: 12px; background: var(--color-bg-104); color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .error-banner { color: #e53e3e; background: #fff5f5; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; border: 1px solid #feb2b2; }
    </style>
</head>
<body>

<div class="verify-box">
    <h2>Confirme seu código</h2>
    <p>Enviamos um código de 6 dígitos para o seu telefone.</p>

    <?php if ($erro): ?>
        <div class="error-banner"><?= $erro ?></div>
    <?php endif; ?>

    <form method="POST" id="formVerify">
        <div class="code-inputs">
            <?php for($i=1; $i<=6; $i++): ?>
                <input type="text" maxlength="1" pattern="\d*" inputmode="numeric" required>
            <?php endfor; ?>
        </div>
        
        <input type="hidden" name="codigo_final" id="codigo_final">
        <button type="submit" class="btn-verify">Verificar Conta</button>
    </form>
</div>



<script>
document.addEventListener("DOMContentLoaded", () => {
    const inputs = document.querySelectorAll(".code-inputs input");
    const hiddenField = document.getElementById("codigo_final");

    inputs.forEach((input, index) => {
        // Pular para o próximo campo ao digitar
        input.addEventListener("input", (e) => {
            if (e.target.value.length === 1 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
            atualizarCodigoFinal();
        });

        // Voltar para o campo anterior ao apagar (Backspace)
        input.addEventListener("keydown", (e) => {
            if (e.key === "Backspace" && e.target.value === "" && index > 0) {
                inputs[index - 1].focus();
            }
        });
    });

    function atualizarCodigoFinal() {
        let codigo = "";
        inputs.forEach(input => codigo += input.value);
        hiddenField.value = codigo;
    }
});
</script>

</body>
</html>