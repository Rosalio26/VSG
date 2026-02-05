<?php
session_start();

require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';

$erro = '';
$info = '';
$codigo_digitado = '';

// ================= 1. BLOQUEIO DE ACESSO E BUSCA DE DADOS =================
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
$userId = (int) $_SESSION['user_id'];

$stmtData = $mysqli->prepare("SELECT type, email, email_verified_at FROM users WHERE id = ? LIMIT 1");
$stmtData->bind_param('i', $userId);
$stmtData->execute();
$userData = $stmtData->get_result()->fetch_assoc();
$stmtData->close();

if (!$userData) {
    session_destroy();
    header("Location: ../login.php?error=user_not_found");
    exit;
}

$isCompany = ($userData['type'] === 'company');
$userEmail = $userData['email'];

// ================= 2. MENSAGEM DE INFORMAÇÃO (GET) =================
if (!empty($_GET['info'])) {
    $info_key = $_GET['info'];
    $messages = [
        'processando_envio' => 'Código enviado com sucesso! Verifique sua caixa de entrada.',
        'codigo_enviado'    => 'Um novo código foi enviado para seu e-mail.',
        'expirado'          => 'O código anterior expirou. Um novo foi gerado.'
    ];
    $info = $messages[$info_key] ?? 'Aguardando confirmação do código.';
}

// ================= 3. PROCESSAMENTO POST (FALLBACK) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo'])) {
    
    $codigo_digitado = trim($_POST['codigo'] ?? '');

    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $erro = "Token de segurança inválido. Recarregue a página.";
    } else {
        if (!preg_match('/^\d{6}$/', $codigo_digitado)) {
            $erro = "Informe um código válido de 6 dígitos.";
        } else {
            try {
                $stmt = $mysqli->prepare("
                    SELECT email_token, email_token_expires, email_verified_at 
                    FROM users 
                    WHERE id = ? LIMIT 1
                ");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$user) {
                    $erro = "Usuário não encontrado.";
                } elseif ($user['email_verified_at']) {
                    header("Location: ../register/gerar_uid.php");
                    exit;
                } elseif ($user['email_token'] !== $codigo_digitado) {
                    $erro = "Código incorreto. Verifique o número digitado.";
                } elseif (strtotime($user['email_token_expires']) < time()) {
                    $erro = "Código expirado. Clique em 'Reenviar' abaixo.";
                } else {
                    $now = date('Y-m-d H:i:s');
                    $update = $mysqli->prepare("
                        UPDATE users 
                        SET email_verified_at = ?, 
                            email_token = NULL, 
                            email_token_expires = NULL,
                            registration_step = 'email_verified'
                        WHERE id = ?
                    ");
                    $update->bind_param('si', $now, $userId);
                    $update->execute();
                    $update->close();
                    
                    header("Location: ../register/gerar_uid.php");
                    exit;
                }
            } catch (\Exception $e) {
                error_log("Erro em verify_email.php: " . $e->getMessage());
                $erro = "Ocorreu um erro interno. Tente novamente mais tarde.";
            }
        }
    }
}

$csrf_token = csrf_generate(); 
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Email - VisionGreen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-bg-000: #dcfce7;
            --color-bg-001: #ffffff;
            --color-bg-101: #111827;
            --color-bg-103: #364153;
            --color-bg-104: #00a63e;
            --color-bg-105: #4a5565;
            --color-bg-109: #4ade80;
            --color-dg-001: #ff3232;
            --color-bus-blue: #2563eb;
            --shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        }

        body {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica', 'Arial', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(74, 222, 128, 0.15) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }

        .container {
            background: var(--color-bg-001);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(74, 222, 128, 0.2);
        }

        .header {
            padding: 32px 32px 24px;
            text-align: center;
            border-bottom: 1px solid #f3f4f6;
            background: linear-gradient(to bottom, rgba(220, 252, 231, 0.3) 0%, rgba(255, 255, 255, 0) 100%);
        }

        .logo-badge {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--color-bg-104) 0%, var(--color-bg-109) 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 16px rgba(0, 166, 62, 0.2);
            animation: bounceIn 0.6s ease-out;
        }

        .logo-badge.company {
            background: linear-gradient(135deg, var(--color-bus-blue) 0%, #3b82f6 100%);
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.2);
        }

        .logo-badge i {
            font-size: 28px;
            color: white;
        }

        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); opacity: 1; }
        }

        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }

        .badge-company {
            background: #dbeafe;
            color: var(--color-bus-blue);
            border: 1px solid #bfdbfe;
        }

        .badge-person {
            background: #dcfce7;
            color: #059669;
            border: 1px solid #bbf7d0;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--color-bg-101);
            margin-bottom: 12px;
            line-height: 1.2;
        }

        h1.company-title {
            color: var(--color-bus-blue);
        }

        .subtitle {
            font-size: 15px;
            color: var(--color-bg-105);
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .email-display {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f9fafb;
            padding: 8px 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            font-weight: 600;
            color: var(--color-bg-104);
            border: 1px solid #e5e7eb;
            margin-top: 12px;
        }

        .email-display.company {
            color: var(--color-bus-blue);
        }

        .content {
            padding: 32px;
        }

        .msg {
            padding: 14px 16px;
            margin-bottom: 24px;
            border-radius: 10px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .msg i {
            font-size: 16px;
            margin-top: 2px;
        }

        .msg-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--color-dg-001);
        }

        .msg-info {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid var(--color-bg-104);
        }

        .code-input-wrapper {
            position: relative;
            margin-bottom: 20px;
        }

        .code-input-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--color-bg-105);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="text"] {
            width: 100%;
            padding: 16px 20px;
            font-size: 28px;
            font-weight: 700;
            text-align: center;
            letter-spacing: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            outline: none;
            transition: all 0.3s ease;
            background: #f9fafb;
            font-family: 'Courier New', monospace;
        }

        input[type="text"]:focus {
            border-color: var(--color-bg-104);
            background: white;
            box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.1);
        }

        input[type="text"].company:focus {
            border-color: var(--color-bus-blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        input.input-error {
            border-color: var(--color-dg-001);
            background-color: #fff5f5;
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        button {
            width: 100%;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        button:active::before {
            width: 300px;
            height: 300px;
        }

        .btn-confirm {
            background: linear-gradient(135deg, var(--color-bg-104) 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 166, 62, 0.3);
        }

        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 166, 62, 0.4);
        }

        .btn-confirm:active {
            transform: translateY(0);
        }

        .btn-confirm-bus {
            background: linear-gradient(135deg, var(--color-bus-blue) 0%, #3b82f6 100%);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-confirm-bus:hover {
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 28px 0;
            color: var(--color-bg-105);
            font-size: 13px;
            font-weight: 600;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        .divider span {
            padding: 0 16px;
        }

        .resend-box {
            text-align: center;
        }

        .resend-text {
            font-size: 14px;
            color: var(--color-bg-105);
            margin-bottom: 12px;
        }

        .btn-resend {
            background: var(--color-bg-103);
            color: white;
            font-size: 14px;
            box-shadow: var(--shadow);
        }

        .btn-resend:hover:not(:disabled) {
            background: var(--color-bg-101);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-resend:disabled {
            background: #e5e7eb;
            color: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .timer-badge {
            display: inline-block;
            background: var(--color-bg-104);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            margin-left: 6px;
        }

        .loader {
            display: none;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(74, 222, 128, 0.2);
            border-top: 3px solid var(--color-bg-104);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .footer {
            padding: 20px 32px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }

        .footer-text {
            font-size: 12px;
            color: var(--color-bg-105);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        @media (max-width: 480px) {
            .container {
                border-radius: 0;
                max-width: 100%;
            }

            input[type="text"] {
                font-size: 24px;
                letter-spacing: 8px;
            }

            .header {
                padding: 24px 20px 20px;
            }

            .content {
                padding: 24px 20px;
            }
        }

        /* Animação de sucesso */
        @keyframes checkmark {
            0% { stroke-dashoffset: 100; }
            100% { stroke-dashoffset: 0; }
        }

        .success-checkmark {
            display: none;
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
        }

        .success-checkmark circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke: var(--color-bg-104);
            fill: none;
            animation: checkmark 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .success-checkmark path {
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            stroke: var(--color-bg-104);
            animation: checkmark 0.3s 0.3s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="type-badge <?= $isCompany ? 'badge-company' : 'badge-person' ?>">
            <i class="fas <?= $isCompany ? 'fa-briefcase' : 'fa-user' ?>"></i>
            <span>Conta <?= $isCompany ? 'Business' : 'Pessoal' ?></span>
        </div>

        <?php if ($isCompany): ?>
            <h1 class="company-title">Verificação Corporativa</h1>
            <p class="subtitle">Confirme sua identidade empresarial</p>
        <?php else: ?>
            <h1>Verificação de E-mail</h1>
            <p class="subtitle">Protegendo sua conta VisionGreen</p>
        <?php endif; ?>

        <div class="email-display <?= $isCompany ? 'company' : '' ?>">
            <i class="fas fa-envelope"></i>
            <span><?= htmlspecialchars($userEmail) ?></span>
        </div>
    </div>

    <div class="content">
        <div id="ajaxLoader" class="loader"></div>

        <svg class="success-checkmark" id="successCheck" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
            <circle cx="26" cy="26" r="25" fill="none"/>
            <path fill="none" stroke-width="3" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
        </svg>

        <div id="feedbackMsg">
            <?php if ($info): ?>
                <div class="msg msg-info">
                    <i class="fas fa-info-circle"></i>
                    <span><?= htmlspecialchars($info) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="msg msg-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($erro) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <form id="verifyForm" method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= $csrf_token ?>">
            
            <div class="code-input-wrapper">
                <label class="code-input-label">
                    <i class="fas fa-key"></i> Código de Verificação
                </label>
                <input 
                    type="text" 
                    name="codigo" 
                    id="inputCodigo"
                    class="<?= $isCompany ? 'company' : '' ?>"
                    maxlength="6" 
                    pattern="\d{6}" 
                    inputmode="numeric" 
                    placeholder="000000"
                    required
                    autofocus
                    value="<?= htmlspecialchars($codigo_digitado) ?>" 
                >
            </div>

            <button type="submit" class="btn-confirm <?= $isCompany ? 'btn-confirm-bus' : '' ?>">
                <i class="fas fa-check-circle"></i>
                <span>Confirmar Identidade</span>
            </button>
        </form>

        <div class="divider">
            <span>Não recebeu?</span>
        </div>

        <div class="resend-box">
            <form method="post" action="reset_email.php">
                <input type="hidden" name="csrf" value="<?= $csrf_token ?>">
                <button type="submit" id="btnResend" class="btn-resend" disabled>
                    <i class="fas fa-paper-plane"></i>
                    <span id="btnText">Aguarde <span class="timer-badge"><span id="timer">5</span>s</span></span>
                </button>
            </form>
        </div>
    </div>

    <div class="footer">
        <p class="footer-text">
            <i class="fas fa-lock"></i>
            <span>Conexão segura e criptografada</span>
        </p>
    </div>
</div>

<script>
    const inputCodigo = document.getElementById('inputCodigo');
    const feedbackMsg = document.getElementById('feedbackMsg');
    const ajaxLoader = document.getElementById('ajaxLoader');
    const successCheck = document.getElementById('successCheck');

    inputCodigo.addEventListener('input', function() {
        this.classList.remove('input-error');
        this.value = this.value.replace(/\D/g, '');
        
        if (this.value.length === 6) {
            verificarAutomatico(this.value);
        }
    });

    async function verificarAutomatico(codigo) {
        ajaxLoader.style.display = "block";
        feedbackMsg.innerHTML = "";

        const formData = new FormData();
        formData.append('codigo', codigo);
        formData.append('csrf', '<?= $csrf_token ?>');

        try {
            const response = await fetch('verificar_ajax.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                ajaxLoader.style.display = "none";
                successCheck.style.display = "block";
                inputCodigo.style.borderColor = "var(--color-bg-104)";
                feedbackMsg.innerHTML = `
                    <div class="msg msg-info">
                        <i class="fas fa-check-circle"></i>
                        <span>Verificação concluída com sucesso!</span>
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = '../register/gerar_uid.php';
                }, 1500);
            } else {
                ajaxLoader.style.display = "none";
                inputCodigo.classList.add('input-error');
                feedbackMsg.innerHTML = `
                    <div class="msg msg-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>${data.error}</span>
                    </div>
                `;
                inputCodigo.focus();
            }
        } catch (error) {
            ajaxLoader.style.display = "none";
            feedbackMsg.innerHTML = `
                <div class="msg msg-error">
                    <i class="fas fa-wifi"></i>
                    <span>Erro na conexão. Verifique sua internet.</span>
                </div>
            `;
        }
    }

    let timeLeft = 5;
    const btnResend = document.getElementById('btnResend');
    const timerDisplay = document.getElementById('timer');
    const btnText = document.getElementById('btnText');

    const countdown = setInterval(() => {
        timeLeft--;
        if (timeLeft > 0) {
            timerDisplay.textContent = timeLeft;
        } else {
            clearInterval(countdown);
            btnResend.disabled = false;
            btnText.innerHTML = 'Reenviar Código';
            btnResend.style.background = "var(--color-bg-103)";
        }
    }, 1000);
</script>

</body>
</html>