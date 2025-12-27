<?php
session_start();
require_once '../includes/db.php';
require_once 'login_rate_limit.php'; // Incluído para usar clearLoginAttempts

if (!isset($_SESSION['recovery'])) {
    header("Location: forgot_password.php");
    exit;
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo']);
    $telefone_digitado = trim($_POST['telefone']);
    $nova_senha = $_POST['password'];
    $confirma_senha = $_POST['confirm_password'];
    $user_id = $_SESSION['recovery']['user_id'];
    $email_usuario = $_SESSION['recovery']['email'];

    // 1. Buscar dados reais do usuário, incluindo status de tentativas
    $stmt = $mysqli->prepare("SELECT telefone, email_token, email_token_expires FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $db_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 2. VALIDAÇÕES DE SEGURANÇA
    if (!$db_user) {
        $error = "Usuário não encontrado.";
    } elseif ($codigo !== $db_user['email_token'] || time() > strtotime($db_user['email_token_expires'])) {
        $error = "Código de verificação inválido ou expirado.";
    } elseif ($telefone_digitado !== $db_user['telefone']) {
        // SEGURANÇA: Bloqueia a troca se o telefone não for o cadastrado
        $error = "O número de telefone não corresponde ao cadastro deste usuário.";
    } elseif ($nova_senha !== $confirma_senha) {
        $error = "As senhas não coincidem.";
    } elseif (strlen($nova_senha) < 8) {
        $error = "A nova senha deve ter pelo menos 8 caracteres.";
    } else {
        // SUCESSO: Atualizar senha e LIMPAR BLOQUEIOS
        $new_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        
        /**
         * Ação Corrigida: 
         * - Define a nova senha
         * - Remove o token de e-mail (para não ser reutilizado)
         * - Zera as tentativas de login (login_attempts = 0)
         * - Remove o tempo de bloqueio (lock_until = NULL)
         */
        $update = $mysqli->prepare("
            UPDATE users 
            SET password_hash = ?, 
                email_token = NULL, 
                login_attempts = 0, 
                lock_until = NULL 
            WHERE id = ?
        ");
        $update->bind_param('si', $new_hash, $user_id);
        $update->execute();
        $update->close();

        // Limpa também o Rate Limit do IP associado a este e-mail
        clearLoginAttempts($mysqli, $email_usuario);

        // Limpa a sessão de recuperação
        unset($_SESSION['recovery']);

        // Redireciona para o login com flag de sucesso para exibir a barra verde
        header("Location: login.php?recovery=success");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Definir Nova Senha - VisionGreen</title>
    <link rel="stylesheet" href="../../assets/style/geral.css">
    <style>
        .box { max-width: 450px; margin: 50px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .error { color: #d9534f; background: #fdf7f7; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9em; border: 1px solid #ebccd1; text-align: center; }
        input { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        label { font-size: 0.85em; color: #666; font-weight: bold; display: block; margin-top: 10px; }
        button { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; margin-top: 20px; font-weight: bold; transition: background 0.3s; }
        button:hover { background: #218838; }
        h2 { text-align: center; color: #333; }
        p.subtitle { text-align: center; font-size: 0.9em; color: #777; margin-bottom: 20px; }

        /* NOVO: Estilos do Medidor de Força */
        .strength-meter { height: 6px; width: 100%; background: #eee; margin-top: -5px; margin-bottom: 10px; border-radius: 0 0 6px 6px; overflow: hidden; }
        .strength-bar { height: 100%; width: 0%; transition: 0.4s ease; }
        .weak { background: #ff4d4d; width: 33.33%; }
        .medium { background: #ffd633; width: 66.66%; }
        .strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Recuperar Acesso</h2>
        <p class="subtitle">Confirme seus dados para desbloquear a conta.</p>

        <?php if ($error): ?> 
            <div class="error"><?= htmlspecialchars($error) ?></div> 
        <?php endif; ?>

        <form method="POST">
            <label>Código do E-mail</label>
            <input type="text" name="codigo" placeholder="6 dígitos" required maxlength="6" inputmode="numeric">

            <label>Seu Telefone Cadastrado</label>
            <input type="text" name="telefone" placeholder="+244..." required>

            <hr style="margin: 25px 0; border: 0; border-top: 1px solid #eee;">

            <label>Crie uma Nova Senha</label>
            <input type="password" name="password" id="passInput" placeholder="Mínimo 8 caracteres" required oninput="checkStrength(this.value)">
            <div class="strength-meter">
                <div id="strengthBar" class="strength-bar"></div>
            </div>

            <label>Confirme a Nova Senha</label>
            <input type="password" name="confirm_password" placeholder="Repita a senha" required>

            <button type="submit">Atualizar Senha e Liberar Login</button>
        </form>
    </div>

    <script>
        /**
         * Lógica de Verificação de Força da Senha
         */
        function checkStrength(password) {
            let bar = document.getElementById('strengthBar');
            let strength = 0;

            if (password.length === 0) {
                bar.className = 'strength-bar';
                return;
            }

            // Critérios
            if (password.length >= 8) strength++; // Tamanho
            if (password.match(/[A-Z]/) && password.match(/[0-9]/)) strength++; // Letra maiúscula + Número
            if (password.match(/[^a-zA-Z0-9]/)) strength++; // Caractere Especial

            // Aplica as classes baseadas na força
            bar.className = 'strength-bar';
            if (strength === 1) {
                bar.classList.add('weak');
            } else if (strength === 2) {
                bar.classList.add('medium');
            } else if (strength >= 3) {
                bar.classList.add('strong');
            }
        }
    </script>
</body>
</html>