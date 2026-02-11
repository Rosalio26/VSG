<?php
session_start();
require_once '../includes/security.php';
require_once '../includes/db.php';

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

if (!empty($_SESSION['employee_auth']['employee_id'])) {
    header("Location: ../../pages/business/dashboard_business.php");
    exit;
}

$info_messages = [
    'logout_success' => 'Você saiu com sucesso.',
    'session_timeout' => '⏰ Sua sessão expirou após 10 minutos de inatividade. Por favor, faça login novamente.',
    'login_required' => 'Você precisa estar logado para acessar esta página.',
    'session_expired' => 'Sua sessão expirou. Por favor, faça login novamente.'
];

$info = isset($_GET['info']) ? $_GET['info'] : null;
$info_message = isset($info_messages[$info]) ? $info_messages[$info] : null;

// Recuperar valores anteriores em caso de erro
$saved_identifier = isset($_SESSION['login_form_data']['identifier']) ? htmlspecialchars($_SESSION['login_form_data']['identifier']) : '';
$saved_remember = isset($_SESSION['login_form_data']['remember']) ? 'checked' : '';

// Limpar dados salvos após uso
if (isset($_SESSION['login_form_data'])) {
    unset($_SESSION['login_form_data']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VisionGreen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --color-primary: #28a745;
            --color-business: #2563eb;
            --color-employee: #4da3ff;
            --color-bg: #f0f2f5;
            --color-error: #dc3545;
            --color-success: #28a745;
            --color-info: #0d6efd;
            --color-warning: #ffc107;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px;
        }
        
        .box { 
            width: 100%; 
            max-width: 420px; 
            background: #fff; 
            padding: 40px 35px; 
            border-radius: 16px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
            border-top: 5px solid var(--color-primary); 
            transition: all 0.3s ease;
            animation: slideUp 0.4s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .box.business-mode { 
            border-top-color: var(--color-business); 
        }
        
        .box.employee-mode { 
            border-top-color: var(--color-employee); 
        }

        h2 { 
            text-align: center; 
            color: #333; 
            margin-bottom: 8px;
            font-size: 28px;
            font-weight: 700;
        }
        
        .subtitle { 
            text-align: center; 
            font-size: 0.9rem; 
            color: #666; 
            margin-bottom: 30px;
        }

        input[type="text"], 
        input[type="password"] { 
            width: 100%; 
            padding: 14px 16px; 
            margin-bottom: 16px; 
            border: 2px solid #e0e0e0; 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        input[type="text"]:focus, 
        input[type="password"]:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }
        
        .box.business-mode input[type="text"]:focus,
        .box.business-mode input[type="password"]:focus {
            border-color: var(--color-business);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .box.employee-mode input[type="text"]:focus,
        .box.employee-mode input[type="password"]:focus {
            border-color: var(--color-employee);
            box-shadow: 0 0 0 3px rgba(77, 163, 255, 0.1);
        }
        
        .msg { 
            padding: 16px 18px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            font-size: 0.9em; 
            border: 2px solid;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideDown 0.3s ease;
            line-height: 1.5;
        }
        
        .msg i {
            font-size: 20px;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .msg-content {
            flex: 1;
        }
        
        .msg strong {
            display: inline;
        }
        
        .error { 
            background: #fee; 
            color: #c33; 
            border-color: #fcc;
        }
        
        .error i {
            color: #dc3545;
        }
        
        .error a {
            color: #00a63e;
            text-decoration: underline;
        }
        
        .success { 
            background: #d4edda; 
            color: #155724; 
            border-color: #c3e6cb;
        }
        
        .success i {
            color: #28a745;
        }
        
        .info { 
            background: #e7f3ff; 
            color: #004085; 
            border-color: #b8daff;
        }
        
        .info i {
            color: #0d6efd;
        }
        
        .warning {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeeba;
        }
        
        .warning i {
            color: #ffc107;
        }
        
        @keyframes slideDown {
            from { 
                opacity: 0; 
                transform: translateY(-15px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        .employee-hint {
            display: none;
            background: linear-gradient(135deg, rgba(77, 163, 255, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
            border: 2px solid rgba(77, 163, 255, 0.3);
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 0.88em;
            color: #2563eb;
            text-align: center;
            animation: slideDown 0.3s ease;
        }
        
        .employee-hint.show {
            display: block;
        }
        
        .employee-hint i {
            font-size: 18px;
            margin-right: 6px;
        }
        
        .remember-container { 
            display: flex; 
            align-items: center; 
            margin-bottom: 24px; 
            font-size: 0.9em; 
            color: #666; 
            cursor: pointer;
            user-select: none;
        }
        
        .remember-container input { 
            margin-right: 10px; 
            cursor: pointer;
            width: 18px;
            height: 18px;
        }
        
        .remember-container:hover {
            color: #333;
        }

        button { 
            width: 100%; 
            padding: 14px; 
            background: var(--color-primary); 
            border: none; 
            color: white; 
            font-weight: 600; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: all 0.3s;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button:disabled { 
            background: #ccc; 
            cursor: not-allowed;
            transform: none;
        }

        .links { 
            margin-top: 25px; 
            text-align: center; 
            font-size: 0.9em;
        }
        
        .links p {
            margin: 10px 0;
        }
        
        .links a { 
            color: var(--color-primary); 
            text-decoration: none; 
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .links a:hover {
            text-decoration: underline;
            color: #1e7e34;
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 480px) {
            .box {
                padding: 30px 25px;
            }
            
            h2 {
                font-size: 24px;
            }
            
            .msg {
                padding: 14px;
                font-size: 0.85em;
            }
        }
    </style>
</head>
<body>

<div class="box" id="loginBox">
    <?php if ($info_message): ?>
    <div class="msg info">
        <i class="fa-solid fa-info-circle"></i>
        <div class="msg-content">
            <?= $info_message ?>
        </div>
    </div>
    <?php endif; ?>

    <h2>Entrar</h2>
    <p class="subtitle">Use seu E-mail ou Identificador (UID)</p>

    <?php if (isset($_GET['recovery']) && $_GET['recovery'] === 'success'): ?>
        <div class="msg success">
            <i class="fa-solid fa-check-circle"></i>
            <div class="msg-content">
                <strong>✅ Senha Alterada com Sucesso!</strong>
                Sua conta foi desbloqueada. Você já pode fazer login.
            </div>
        </div>
    <?php elseif (isset($_GET['employee_logout'])): ?>
        <div class="msg success">
            <i class="fa-solid fa-check-circle"></i>
            <div class="msg-content">
                <strong>✅ Logout Realizado</strong>
                Você saiu do sistema com sucesso.
            </div>
        </div>
    <?php elseif (isset($_SESSION['login_error'])): ?>
        <div class="msg error">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <div class="msg-content">
                <?= $_SESSION['login_error'] ?>
            </div>
        </div>
        <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>

    <div id="employeeHint" class="employee-hint">
        <i class="fa-solid fa-user-tie"></i>
        <strong>Login de Funcionário Detectado</strong><br>
        <small>Use o email corporativo fornecido pela empresa</small>
    </div>

    <form method="post" action="login.process.php" id="formLogin">
        <?= csrf_field(); ?>
        
        <input 
            type="text" 
            name="identifier" 
            id="identifier" 
            placeholder="E-mail, UID ou Email Corporativo" 
            value="<?= $saved_identifier ?>"
            required 
            autofocus
        >
        
        <input 
            type="password" 
            name="password" 
            id="password"
            placeholder="Digite sua senha" 
            required
        >

        <label class="remember-container">
            <input type="checkbox" name="remember" <?= $saved_remember ?>> 
            Mantenha-me conectado por 30 dias
        </label>

        <button type="submit" id="btnSubmit">
            Entrar na Conta
        </button>
    </form>

    <div class="links">
        <p>
            <i class="fa-solid fa-key"></i>
            <a href="forgot_password.php">Esqueceu sua senha?</a>
        </p>
        <p>
            <i class="fa-solid fa-user-plus"></i>
            Ainda não tem conta? 
            <a href="../process/start.php">Cadastre-se gratuitamente</a>
        </p>
    </div>
</div>

<script>
    const identifierInput = document.getElementById('identifier');
    const loginBox = document.getElementById('loginBox');
    const btnSubmit = document.getElementById('btnSubmit');
    const employeeHint = document.getElementById('employeeHint');

    // Detectar tipo de login ao carregar a página (caso tenha valor salvo)
    function detectLoginType() {
        const val = identifierInput.value.trim().toLowerCase();
        
        if (val.includes('@') && val.endsWith('.vsg.com')) {
            loginBox.classList.add('employee-mode');
            loginBox.classList.remove('business-mode');
            btnSubmit.style.background = '#4da3ff';
            employeeHint.classList.add('show');
        }
        else if (val.toUpperCase().endsWith('C') && val.length === 9) {
            loginBox.classList.add('business-mode');
            loginBox.classList.remove('employee-mode');
            btnSubmit.style.background = '#2563eb';
            employeeHint.classList.remove('show');
        }
        else if (val.length > 0) {
            loginBox.classList.remove('business-mode', 'employee-mode');
            btnSubmit.style.background = '#28a745';
            employeeHint.classList.remove('show');
        }
    }

    // Executar ao carregar a página
    detectLoginType();

    // Detectar enquanto digita
    identifierInput.addEventListener('input', detectLoginType);

    document.getElementById('formLogin').onsubmit = function() {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = 'Autenticando... <span class="spinner"></span>';
    };
</script>

</body>
</html>