<?php
require_once '../includes/security.php';

// AÇÃO 1: Bloqueio de usuário já logado
if (!empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../pages/person/dashboard_person.php");
    exit;
}

// NOVO: Lógica de Limpeza de Conflitos
// Se o usuário acabou de recuperar a senha, limpamos QUALQUER erro de login da sessão
if (isset($_GET['recovery']) && $_GET['recovery'] === 'success') {
    unset($_SESSION['login_error']); // Remove mensagens como "Senha incorreta" ou "Bloqueado"
}

// Limpeza de IDs de processos anteriores
unset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VisionGreen</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background:#f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { width: 100%; max-width: 360px; background:#fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; margin-bottom: 25px; }
        input[type="email"], input[type="password"] { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        
        /* Notificações */
        .msg { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85em; text-align: center; border: 1px solid; }
        .error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; } /* Estilo para timeout */
        
        /* NOVO: Estilo Lembrar-me */
        .remember-container { display: flex; align-items: center; margin-bottom: 20px; font-size: 0.85em; color: #666; cursor: pointer; }
        .remember-container input { margin-right: 8px; cursor: pointer; }

        button { width: 100%; padding: 12px; background: #28a745; border: none; color: white; font-weight: bold; border-radius: 6px; cursor: pointer; }
        .links { margin-top: 20px; text-align: center; font-size: 0.85em; }
        .links a { color: #28a745; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="box">
    <h2>Entrar</h2>

    <?php 
    /**
     * PRIORIDADE DE VISIBILIDADE:
     * 1. Sucesso na recuperação
     * 2. Aviso de Inatividade (Timeout)
     * 3. Erros comuns
     */
    if (isset($_GET['recovery']) && $_GET['recovery'] === 'success'): ?>
        <div class="msg success">
            <strong>Senha Alterada!</strong><br>
            Sua conta foi desbloqueada. Faça login com a nova senha.
        </div>
    <?php elseif (isset($_GET['info']) && $_GET['info'] === 'timeout'): ?>
        <div class="msg info">
            Sua sessão expirou por inatividade. Por favor, entre novamente.
        </div>
    <?php elseif (!empty($_SESSION['login_error'])): ?>
        <div class="msg error">
            <?= htmlspecialchars($_SESSION['login_error']) ?>
        </div>
        <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>

    <form method="post" action="login.process.php" id="formLogin">
        <?= csrf_field(); ?>
        
        <input type="email" name="email" placeholder="E-mail" required autofocus>
        <input type="password" name="password" placeholder="Senha" required>

        <label class="remember-container">
            <input type="checkbox" name="remember"> Mantenha-me conectado por 30 dias
        </label>

        <button type="submit" id="btnSubmit">Entrar na Conta</button>
    </form>

    <div class="links">
        <p><a href="forgot_password.php">Esqueceu sua senha?</a></p>
        <p>Ainda não tem conta? <a href="../process/start.php">Cadastre-se</a></p>
    </div>
</div>

<script>
    document.getElementById('formLogin').onsubmit = function() {
        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerText = 'Autenticando...';
    };
</script>

</body>
</html>