<?php
require_once '../includes/security.php';
require_once '../includes/db.php'; // Necessário para verificar o tipo se já estiver logado

/* ================= 1. BLOQUEIO DE USUÁRIO JÁ LOGADO ================= */
if (!empty($_SESSION['auth']['user_id'])) {
    $userId = (int) $_SESSION['auth']['user_id'];
    
    // Verificamos o tipo do usuário para redirecionar ao dashboard correto
    $stmt = $mysqli->prepare("SELECT type FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res && $res['type'] === 'company') {
        header("Location: ../../pages/business/dashboard_business.php");
    } else {
        header("Location: ../../pages/person/dashboard_person.php");
    }
    exit;
}

/* ================= 2. LÓGICA DE LIMPEZA DE CONFLITOS ================= */
if (isset($_GET['recovery']) && $_GET['recovery'] === 'success') {
    unset($_SESSION['login_error']); 
}

// Limpeza de IDs de processos de cadastro anteriores
unset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VisionGreen</title>
    <style>
        :root {
            --color-primary: #28a745;
            --color-business: #2563eb;
            --color-bg: #f0f2f5;
        }
        body { font-family: 'Segoe UI', sans-serif; background: var(--color-bg); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; padding: 0px 10px; }
        .box { width: 100%; max-width: 360px; background:#fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); border-top: 5px solid var(--color-primary); }
        
        /* Se o usuário digitar algo com 'C' no final, podemos mudar a cor dinamicamente via JS se desejar */
        .box.business-mode { border-top-color: var(--color-business); }

        h2 { text-align: center; color: #333; margin-bottom: 5px; }
        .subtitle { text-align: center; font-size: 0.8rem; color: #666; margin-bottom: 25px; }

        input[type="text"], input[type="password"] { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
        
        .msg { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85em; text-align: center; border: 1px solid; }
        .error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        
        .remember-container { display: flex; align-items: center; margin-bottom: 20px; font-size: 0.85em; color: #666; cursor: pointer; }
        .remember-container input { margin-right: 8px; cursor: pointer; }

        button { width: 100%; padding: 12px; background: var(--color-primary); border: none; color: white; font-weight: bold; border-radius: 6px; cursor: pointer; transition: 0.3s; }
        button:hover { opacity: 0.9; }
        button:disabled { background: #ccc; cursor: not-allowed; }

        .links { margin-top: 20px; text-align: center; font-size: 0.85em; }
        .links a { color: var(--color-primary); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="box" id="loginBox">
    <h2>Entrar</h2>
    <p class="subtitle">Use seu E-mail ou Identificador (UID)</p>

    <?php 
    if (isset($_GET['recovery']) && $_GET['recovery'] === 'success'): ?>
        <div class="msg success">
            <strong>Senha Alterada!</strong><br>
            Sua conta foi desbloqueada. Faça login.
        </div>
    <?php elseif (isset($_GET['info']) && $_GET['info'] === 'timeout'): ?>
        <div class="msg info">
            Sessão expirada por inatividade. Entre novamente.
        </div>
    <?php elseif (!empty($_SESSION['login_error'])): ?>
        <div class="msg error">
            <?= htmlspecialchars($_SESSION['login_error']) ?>
        </div>
        <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>

    <form method="post" action="login.process.php" id="formLogin">
        <?= csrf_field(); ?>
        
        <input type="text" name="identifier" id="identifier" placeholder="E-mail ou UID" required autofocus>
        
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
    const identifierInput = document.getElementById('identifier');
    const loginBox = document.getElementById('loginBox');

    // Feedback visual se for UID de empresa (termina com C)
    identifierInput.addEventListener('input', function() {
        const val = this.value.trim().toUpperCase();
        if (val.endsWith('C') && val.length === 9) {
            loginBox.classList.add('business-mode');
            document.getElementById('btnSubmit').style.background = '#2563eb';
        } else {
            loginBox.classList.remove('business-mode');
            document.getElementById('btnSubmit').style.background = '#28a745';
        }
    });

    document.getElementById('formLogin').onsubmit = function() {
        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerText = 'Autenticando...';
    };
</script>

</body>
</html>