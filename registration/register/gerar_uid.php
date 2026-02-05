<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/uid.php';

/* ================= 1. BLOQUEIO DE ACESSO ================= */
if (empty($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* ================= 2. BUSCA DADOS DO USUÁRIO ================= */
$stmt = $mysqli->prepare("
    SELECT type, nome, public_id, email, email_corporativo, email_verified_at, status, registration_step 
    FROM users 
    WHERE id = ? LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../login/login.php?error=user_not_found");
    exit;
}

$isBusiness = ($user['type'] === 'company');

/* ================= 3. BLOQUEIO DE FLUXO (RETROCESSO) ================= */
if ($user['status'] === 'active' || $user['registration_step'] === 'completed') {
    $destino = ($isBusiness) ? '../../pages/business/dashboard_business.php' : '../../pages/person/dashboard_person.php';
    header("Location: " . $destino);
    exit;
}

if ($user['email_verified_at'] === null) {
    header("Location: verify_email.php");
    exit;
}

/* ================= 4. GERAÇÃO DE IDENTIDADE CORRIGIDA ================= */
if (empty($user['public_id'])) {
    $mysqli->begin_transaction();
    try {
        $categoria = ($isBusiness) ? 'C' : 'P';
        $uid = gerarUID($mysqli, $categoria);
        
        $emailCorp = null;
        if ($isBusiness) {
            $nomeBase = $user['nome'];
            $nomeLimpo = iconv('UTF-8', 'ASCII//TRANSLIT', $nomeBase);
            $nomeLimpo = preg_replace('/[^a-zA-Z0-9\s]/', '', $nomeLimpo);
            $nomeLimpo = strtolower(str_replace(' ', '.', trim($nomeLimpo)));
            $nomeLimpo = preg_replace('/\.+/', '.', $nomeLimpo);
            $emailCorp = $nomeLimpo . '@visiongreen.com';
        }

        $now = date('Y-m-d H:i:s');
        
        $update = $mysqli->prepare("
            UPDATE users 
            SET public_id = ?, 
                email_corporativo = ?, 
                registration_step = 'uid_generated', 
                uid_generated_at = ? 
            WHERE id = ?
        ");
        $update->bind_param('sssi', $uid, $emailCorp, $now, $userId);
        $update->execute();
        $update->close();

        $mysqli->commit();
        
        $user['public_id'] = $uid;
        $user['email_corporativo'] = $emailCorp;
        $user['registration_step'] = 'uid_generated';

    } catch (\Throwable $e) {
        $mysqli->rollback();
        error_log("Erro crítico no gerar_uid.php para ID {$userId}: " . $e->getMessage());
        die("Erro ao finalizar seu perfil. Por favor, tente recarregar a página.");
    }
}

/* ================= 5. ATIVAÇÃO FINAL DA CONTA ================= */
$finalRedirect = false;
if ($user['email_verified_at'] !== null) {
    if ($user['status'] !== 'active') {
        $update = $mysqli->prepare("
            UPDATE users 
            SET status = 'active', registration_step = 'completed' 
            WHERE id = ?
        ");
        $update->bind_param('i', $userId);
        $update->execute();
        $update->close();
        
        $user['status'] = 'active';
        $user['registration_step'] = 'completed';
    }
    $finalRedirect = true;
    unset($_SESSION['cadastro']); 
}

$colorMain = $isBusiness ? '#2563eb' : '#00a63e'; 
$colorSecondary = $isBusiness ? '#3b82f6' : '#059669';
$bgContainer = $isBusiness ? '#eff6ff' : '#dcfce7';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conta Ativada - VisionGreen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-main: <?= $colorMain ?>;
            --color-secondary: <?= $colorSecondary ?>;
            --color-bg-page: <?= $bgContainer ?>;
            --color-white: #ffffff;
            --color-dark: #111827;
            --color-gray: #6b7280;
            --color-uid-bg: #0f172a;
            --color-uid-text: <?= $isBusiness ? '#93c5fd' : '#4ade80' ?>;
        }

        body {
            background: linear-gradient(135deg, var(--color-bg-page) 0%, <?= $isBusiness ? '#dbeafe' : '#bbf7d0' ?> 100%);
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
            background: var(--color-white);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
            position: relative;
            z-index: 1;
            border: 2px solid var(--color-main);
            animation: fadeInScale 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .header {
            background: linear-gradient(135deg, var(--color-main) 0%, var(--color-secondary) 100%);
            padding: 40px 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
            z-index: 1;
            animation: bounceIn 0.8s ease-out;
        }

        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        .success-icon i {
            font-size: 40px;
            color: white;
        }

        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            font-size: 13px;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
        }

        h1 {
            color: white;
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: 16px;
            line-height: 1.5;
            position: relative;
            z-index: 1;
        }

        .content {
            padding: 40px 32px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--color-gray);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .uid-box {
            background: var(--color-uid-bg);
            border: 3px dashed var(--color-main);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .uid-box::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--color-main), var(--color-secondary));
            border-radius: 16px;
            opacity: 0.1;
            animation: glow 3s ease-in-out infinite;
        }

        @keyframes glow {
            0%, 100% { opacity: 0.1; }
            50% { opacity: 0.2; }
        }

        .uid-value {
            color: var(--color-uid-text);
            font-size: 36px;
            font-weight: 900;
            letter-spacing: 6px;
            text-align: center;
            font-family: 'Courier New', monospace;
            position: relative;
            z-index: 1;
            text-shadow: 0 0 20px rgba(74, 222, 128, 0.3);
        }

        .uid-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            flex: 1;
            padding: 12px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--color-uid-text);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-action:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .email-box {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .email-label {
            font-size: 11px;
            color: var(--color-gray);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: block;
        }

        .email-value {
            color: var(--color-dark);
            font-size: 18px;
            font-weight: 700;
            word-break: break-all;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .email-value i {
            color: var(--color-main);
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .btn {
            flex: 1;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
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

        .btn:active::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-main) 0%, var(--color-secondary) 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: white;
            color: var(--color-main);
            border: 2px solid var(--color-main);
        }

        .btn-secondary:hover {
            background: var(--color-main);
            color: white;
            transform: translateY(-3px);
        }

        .timer-box {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .timer-text {
            color: var(--color-gray);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .timer-value {
            font-size: 48px;
            font-weight: 900;
            color: var(--color-main);
            font-family: 'Courier New', monospace;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .timer-label {
            font-size: 12px;
            color: var(--color-gray);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }

        .footer {
            padding: 24px 32px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }

        .footer-text {
            font-size: 13px;
            color: var(--color-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        @media (max-width: 640px) {
            .container {
                border-radius: 0;
                border-left: none;
                border-right: none;
            }

            .action-buttons {
                flex-direction: column;
            }

            .uid-value {
                font-size: 28px;
                letter-spacing: 4px;
            }

            h1 {
                font-size: 26px;
            }

            .timer-value {
                font-size: 36px;
            }
        }

        /* Animação de download */
        @keyframes downloadIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(5px); }
        }

        .btn-action:hover i.fa-download {
            animation: downloadIcon 0.6s ease-in-out infinite;
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($finalRedirect): ?>
        <div class="header">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="type-badge">
                <i class="fas <?= $isBusiness ? 'fa-briefcase' : 'fa-user-check' ?>"></i>
                <span>Conta <?= $isBusiness ? 'Business' : 'Pessoal' ?></span>
            </div>
            <h1>🎉 Bem-vindo à VisionGreen!</h1>
            <p class="subtitle">Sua conta foi ativada com sucesso</p>
        </div>

        <div class="content">
            <div class="section-title">
                <i class="fas fa-fingerprint"></i>
                <span>Seu Identificador Único</span>
            </div>

            <div class="uid-box">
                <div class="uid-value" id="uidValue">
                    <?= htmlspecialchars($user['public_id']) ?>
                </div>
                <div class="uid-actions">
                    <button class="btn-action" onclick="copyUID()" id="btnCopy">
                        <i class="fas fa-copy"></i>
                        <span>Copiar</span>
                    </button>
                    <button class="btn-action" onclick="downloadUID()">
                        <i class="fas fa-download"></i>
                        <span>Baixar</span>
                    </button>
                </div>
            </div>

            <?php if ($isBusiness): ?>
                <div class="section-title">
                    <i class="fas fa-envelope-open-text"></i>
                    <span>E-mail Corporativo</span>
                </div>
                <div class="email-box">
                    <span class="email-label">Novo E-mail VisionGreen</span>
                    <div class="email-value">
                        <i class="fas fa-at"></i>
                        <span><?= htmlspecialchars($user['email_corporativo']) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="action-buttons">
                <button class="btn btn-primary" onclick="goToDashboard()">
                    <i class="fas fa-rocket"></i>
                    <span>Acessar Agora</span>
                </button>
                <button class="btn btn-secondary" onclick="downloadCredentials()">
                    <i class="fas fa-file-download"></i>
                    <span>Baixar Credenciais</span>
                </button>
            </div>

            <div class="timer-box">
                <p class="timer-text">Redirecionamento automático em:</p>
                <div class="timer-value" id="seconds">30</div>
                <p class="timer-label">segundos</p>
            </div>
        </div>

        <div class="footer">
            <p class="footer-text">
                <i class="fas fa-shield-alt"></i>
                <span>Guarde seu UID em local seguro • Ele identifica sua conta</span>
            </p>
        </div>

    <?php else: ?>
        <div class="header">
            <div class="success-icon">
                <i class="fas fa-cog fa-spin"></i>
            </div>
            <h1>Processando...</h1>
            <p class="subtitle">Finalizando configurações da sua conta</p>
        </div>
    <?php endif; ?>
</div>

<?php if ($finalRedirect): ?>
<script>
    const uid = "<?= htmlspecialchars($user['public_id']) ?>";
    const email = "<?= htmlspecialchars($user['email']) ?>";
    const emailCorp = "<?= $isBusiness ? htmlspecialchars($user['email_corporativo']) : '' ?>";
    const nome = "<?= htmlspecialchars($user['nome']) ?>";
    const isBusiness = <?= $isBusiness ? 'true' : 'false' ?>;
    const destino = "<?= $isBusiness ? '../../pages/business/dashboard_business.php' : '../../pages/person/dashboard_person.php' ?>";

    // Timer de redirecionamento
    let timeLeft = 30;
    const display = document.getElementById('seconds');

    const timer = setInterval(() => {
        timeLeft--;
        display.textContent = timeLeft;
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            goToDashboard();
        }
    }, 1000);

    // Copiar UID
    function copyUID() {
        navigator.clipboard.writeText(uid).then(() => {
            const btnCopy = document.getElementById('btnCopy');
            const originalHTML = btnCopy.innerHTML;
            btnCopy.innerHTML = '<i class="fas fa-check"></i><span>Copiado!</span>';
            btnCopy.style.background = 'rgba(74, 222, 128, 0.2)';
            
            setTimeout(() => {
                btnCopy.innerHTML = originalHTML;
                btnCopy.style.background = '';
            }, 2000);
        }).catch(err => {
            alert('Erro ao copiar: ' + err);
        });
    }

    // Download UID simples
    function downloadUID() {
        const content = `╔════════════════════════════════════════╗
║     VISIONGREEN - IDENTIFICADOR       ║
╚════════════════════════════════════════╝

Tipo de Conta: ${isBusiness ? 'Business / Empresa' : 'Pessoal / Cliente'}
Nome: ${nome}
UID: ${uid}
E-mail: ${email}
${isBusiness ? `E-mail Corporativo: ${emailCorp}` : ''}

Data: ${new Date().toLocaleString('pt-BR')}

⚠️  IMPORTANTE:
• Guarde este identificador em local seguro
• Use o UID para fazer login na plataforma
• Não compartilhe com terceiros

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
© VisionGreen - Sistema de Gestão`;

        const blob = new Blob([content], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `VisionGreen_UID_${uid}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    // Download completo
    function downloadCredentials() {
        const content = `╔════════════════════════════════════════════════════════╗
║           VISIONGREEN - CREDENCIAIS COMPLETAS          ║
╚════════════════════════════════════════════════════════╝

┌─ INFORMAÇÕES DA CONTA ─────────────────────────────────┐
│ Tipo: ${isBusiness ? 'Conta Business / Empresa' : 'Conta Pessoal / Cliente'}
│ Nome: ${nome}
│ Status: ✓ ATIVA
│ Data de Ativação: ${new Date().toLocaleString('pt-BR')}
└────────────────────────────────────────────────────────┘

┌─ CREDENCIAIS DE ACESSO ────────────────────────────────┐
│ 
│ IDENTIFICADOR (UID):
│ ${uid}
│ 
│ E-MAIL PRINCIPAL:
│ ${email}
│ 
${isBusiness ? `│ E-MAIL CORPORATIVO:
│ ${emailCorp}
│ ` : ''}└────────────────────────────────────────────────────────┘

┌─ INSTRUÇÕES DE LOGIN ──────────────────────────────────┐
│ 1. Acesse: www.visiongreen.com/login
│ 2. Digite seu UID ou E-mail
│ 3. Digite sua senha
│ 4. Clique em "Entrar"
└────────────────────────────────────────────────────────┘

⚠️  SEGURANÇA:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• Guarde este arquivo em local seguro e privado
• Não compartilhe seu UID com terceiros
• Use senha forte e única para esta conta
• Ative autenticação de dois fatores (2FA) quando disponível
• Em caso de perda, entre em contato: suporte@visiongreen.com

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
© ${new Date().getFullYear()} VisionGreen - Todos os direitos reservados
Documento gerado automaticamente pelo sistema`;

        const blob = new Blob([content], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `VisionGreen_Credenciais_${uid}_${new Date().toISOString().split('T')[0]}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    // Ir para dashboard
    function goToDashboard() {
        clearInterval(timer);
        window.location.replace(destino);
    }
</script>
<?php endif; ?>

</body>
</html>