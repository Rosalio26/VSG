<?php
session_start();

// Impede o cache para evitar problemas ao usar o botão "voltar" do navegador
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/uid.php'; // Função gerarUID($mysqli, $categoria)

/* ================= 1. BLOQUEIO DE ACESSO ================= */
if (empty($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* ================= 2. BUSCA DADOS DO USUÁRIO ================= */
$stmt = $mysqli->prepare("
    SELECT type, public_id, email, email_corporativo, email_verified_at, status, registration_step 
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

// CORREÇÃO: Alinhando variável com o ENUM 'company' do banco de dados
$isBusiness = ($user['type'] === 'company');

/* ================= 3. BLOQUEIO DE FLUXO (RETROCESSO) ================= */
if ($user['status'] === 'active' || $user['registration_step'] === 'completed') {
    $destino = ($isBusiness) ? '../../pages/business/dashboard.php' : '../../pages/person/dashboard_person.php';
    header("Location: " . $destino);
    exit;
}

if ($user['email_verified_at'] === null) {
    header("Location: verify_email.php");
    exit;
}

/* ================= 4. GERAÇÃO DE IDENTIDADE (UID E E-MAIL CORP) ================= */
if (empty($user['public_id'])) {
    $mysqli->begin_transaction();
    try {
        // CORREÇÃO: Se o tipo for 'company', categoria deve ser 'C'
        $categoria = ($isBusiness) ? 'C' : 'P';
        
        $uid = gerarUID($mysqli, $categoria);
        
        $emailCorp = null;
        if ($isBusiness) {
            $prefixo = explode('@', $user['email'])[0];
            $emailCorp = $prefixo . '@visiongreen.com';
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

// Configurações visuais dinâmicas baseadas na correção 'company'
$colorMain  = $isBusiness ? '#2563eb' : '#00a63e'; 
$bgContainer = $isBusiness ? '#eff6ff' : '#dcfce7';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizando Cadastro - VisionGreen</title>
    <style>
        :root {
            --color-main: <?= $colorMain ?>;
            --color-bg-page: <?= $bgContainer ?>;
            --color-white: #ffffff;
            --color-dark: #111827;
            --color-uid-bg: #101828;
            --color-uid-text: <?= $isBusiness ? '#93c5fd' : '#4ade80' ?>;
        }

        body {
            background-color: var(--color-bg-page);
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            color: var(--color-dark);
        }

        .container {
            background-color: var(--color-white);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 450px;
            width: 90%;
            border: 2px solid var(--color-main);
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .type-label {
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            color: var(--color-main);
            letter-spacing: 1px;
            margin-bottom: 5px;
            display: block;
        }

        h1 { margin: 10px 0; color: var(--color-main); font-size: 1.8rem; }
        p { color: #4b5563; font-size: 0.95rem; margin-bottom: 20px; }

        .uid-box {
            background-color: var(--color-uid-bg);
            color: var(--color-uid-text);
            font-size: 2.2rem;
            font-weight: bold;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            letter-spacing: 4px;
            border: 2px dashed var(--color-main);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
        }

        .email-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .email-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; margin-bottom: 5px; display: block; }
        .email-value { font-weight: bold; color: var(--color-dark); font-size: 1.1rem; }

        .timer-text {
            color: #6b7280;
            font-size: 0.85rem;
            margin-top: 30px;
        }

        #seconds { font-weight: bold; color: var(--color-main); font-size: 1.1rem; }
    </style>
</head>
<body>

<div class="container">
    <?php if ($finalRedirect): ?>
        <span class="type-label">Cadastro <?= $isBusiness ? 'Empresarial' : 'Pessoal' ?></span>
        <h1>Conta Ativada!</h1>
        <p>Seja bem-vindo à VisionGreen. Guarde seu identificador de acesso:</p>
        
        <div class="email-label">Seu Identificador (UID)</div>
        <div class="uid-box">
            <?= htmlspecialchars($user['public_id']) ?>
        </div>

        <?php if ($isBusiness): ?>
            <div class="email-box">
                <span class="email-label">Seu Novo E-mail Corporativo</span>
                <span class="email-value"><?= htmlspecialchars($user['email_corporativo']) ?></span>
            </div>
        <?php endif; ?>

        <p class="timer-text">
            Redirecionando para seu painel em <span id="seconds">30</span> segundos...
        </p>
    <?php else: ?>
        <h1>Processando...</h1>
        <p>Estamos finalizando as configurações da sua conta.</p>
    <?php endif; ?>
</div>

<?php if ($finalRedirect): ?>
<script>
    let timeLeft = 30;
    const display = document.getElementById('seconds');
    const destino = "<?= $isBusiness ? '../../pages/business/dashboard_business.php' : '../../pages/person/dashboard_person.php' ?>";

    const timer = setInterval(() => {
        timeLeft--;
        display.textContent = timeLeft;
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            window.location.replace(destino);
        }
    }, 1000);
</script>
<?php endif; ?>

</body>
</html>