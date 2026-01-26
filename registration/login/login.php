<?php
require_once '../includes/security.php';
require_once '../includes/db.php';

/* ================= BLOQUEIO DE USUÁRIO JÁ LOGADO ================= */
if (!empty($_SESSION['auth']['user_id'])) {
    $userId = (int) $_SESSION['auth']['user_id'];
    
    $stmt = $mysqli->prepare("SELECT type FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res && $res['type'] === 'company') {
        header("Location: ../../pages/business/dashboard_business.php");
    } else {
        header("Location: ../../pages/person/index.php");
    }
    exit;
}

/* ================= VERIFICAR SE É FUNCIONÁRIO LOGADO ================= */
if (!empty($_SESSION['employee_auth']['employee_id'])) {
    header("Location: ../../pages/business/dashboard_business.php");
    exit;
}

/* ================= LIMPEZA ================= */
if (isset($_GET['recovery']) && $_GET['recovery'] === 'success') {
    unset($_SESSION['login_error']); 
}

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
            --color-employee: #4da3ff;
            --color-bg: #f0f2f5;
        }
        body { font-family: 'Segoe UI', sans-serif; background: var(--color-bg); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; padding: 0px 10px; }
        .box { width: 100%; max-width: 360px; background:#fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); border-top: 5px solid var(--color-primary); transition: all 0.3s ease; }
        
        .box.business-mode { border-top-color: var(--color-business); }
        .box.employee-mode { border-top-color: var(--color-employee); }

        h2 { text-align: center; color: #333; margin-bottom: 5px; }
        .subtitle { text-align: center; font-size: 0.8rem; color: #666; margin-bottom: 25px; }

        input[type="text"], input[type="password"] { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
        
        .msg { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85em; text-align: center; border: 1px solid; }
        .error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        
        /* Hint de funcionário */
        .employee-hint {
            display: none;
            background: rgba(77, 163, 255, 0.1);
            border: 1px solid rgba(77, 163, 255, 0.3);
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 0.85em;
            color: #4da3ff;
            text-align: center;
            animation: slideDown 0.3s ease;
        }
        
        .employee-hint.show {
            display: block;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
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
    <?php elseif (isset($_GET['employee_logout'])): ?>
        <div class="msg success">
            ✅ Você saiu do sistema com sucesso.
        </div>
    <?php elseif (!empty($_SESSION['login_error'])): ?>
        <div class="msg error">
            <?= htmlspecialchars($_SESSION['login_error']) ?>
        </div>
        <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>

    <!-- Hint para funcionários -->
    <div id="employeeHint" class="employee-hint">
        <strong>👔 Login de Funcionário Detectado</strong><br>
        <small>Use o email corporativo fornecido pela empresa</small>
    </div>

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
    const btnSubmit = document.getElementById('btnSubmit');
    const employeeHint = document.getElementById('employeeHint');

    identifierInput.addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        
        // Detectar funcionário pelo email @*.vsg.com
        if (val.includes('@') && val.endsWith('.vsg.com')) {
            loginBox.classList.add('employee-mode');
            loginBox.classList.remove('business-mode');
            btnSubmit.style.background = '#4da3ff';
            employeeHint.classList.add('show');
        }
        // Detectar empresa pelo UID (termina com C)
        else if (val.toUpperCase().endsWith('C') && val.length === 9) {
            loginBox.classList.add('business-mode');
            loginBox.classList.remove('employee-mode');
            btnSubmit.style.background = '#2563eb';
            employeeHint.classList.remove('show');
        }
        // Padrão (pessoa)
        else {
            loginBox.classList.remove('business-mode', 'employee-mode');
            btnSubmit.style.background = '#28a745';
            employeeHint.classList.remove('show');
        }
    });

    document.getElementById('formLogin').onsubmit = function() {
        btnSubmit.disabled = true;
        btnSubmit.innerText = 'Autenticando...';
    };
</script>

</body>
</html>