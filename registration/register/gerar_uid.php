<?php
session_start();

// Impede o navegador de armazenar esta página no cache (Garante que o bloqueio de voltar funcione)
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/uid.php'; // Função gerarUID()

/* ================= BLOQUEIO DE ACESSO ================= */
if (empty($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* ================= BUSCA USUÁRIO ================= */
$stmt = $mysqli->prepare("
    SELECT type, public_id, email_verified_at, status, registration_step 
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

/* ================= BLOQUEIO DE RETROCESSO ================= */
// Se o usuário já estiver ativo ou o cadastro concluído, ele não pode ver esta página
if ($user['status'] === 'active' || $user['registration_step'] === 'completed') {
    $destino = ($user['type'] === 'business') ? '../../pages/business/dashboard.php' : '../../pages/person/dashboard_person.php';
    header("Location: " . $destino);
    exit;
}

/* ================= VERIFICA FLUXO ================= */
if ($user['email_verified_at'] === null && $user['registration_step'] === 'pending_email') {
    header("Location: verify_email.php");
    exit;
}

/* ================= GERA UID SE NÃO EXISTIR ================= */
if (empty($user['public_id'])) {
    $mysqli->begin_transaction();
    try {
        $categoria = ($user['type'] === 'business') ? 'C' : 'P';
        $uid = gerarUID($mysqli, $categoria);
        
        $now = date('Y-m-d H:i:s');
        $update = $mysqli->prepare("
            UPDATE users 
            SET public_id = ?, registration_step = 'uid_generated', uid_generated_at = ? 
            WHERE id = ?
        ");
        $update->bind_param('ssi', $uid, $now, $userId);
        $update->execute();
        $update->close();

        $mysqli->commit();
        
        $user['public_id'] = $uid;
        $user['registration_step'] = 'uid_generated';
    } catch (\Throwable $e) {
        $mysqli->rollback();
        error_log("Erro ao gerar UID para usuário {$userId}: " . $e->getMessage());
        die("Erro interno ao gerar seu identificador. Por favor, tente recarregar a página.");
    }
}

/* ================= ATIVA CONTA ================= */
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
    }
    // Preparamos o gatilho para o JavaScript iniciar a contagem
    $finalRedirect = true;
    unset($_SESSION['cadastro']); // Limpamos a sessão de fluxo, mas user_id mantém o login
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizando Cadastro - VisionGreen</title>
    <style>
        :root {
            --color-bg-000: #dcfce7;
            --color-bg-001: #ffffff;
            --color-bg-100: #1f2937;
            --color-bg-101: #111827;
            --color-bg-102: #101828;
            --color-bg-103: #364153;
            --color-bg-104: #00a63e;
            --color-bg-105: #4a5565;
            --color-bg-106: #99a1af;
            --color-bg-109: #4ade80;
            --color-dg-001: #ff3232;
        }

        body {
            background-color: var(--color-bg-000);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            color: var(--color-bg-101);
        }

        .container {
            background-color: var(--color-bg-001);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 450px;
            width: 90%;
            border: 1px solid var(--color-bg-109);
        }

        h1 { color: var(--color-bg-104); margin-bottom: 10px; }
        
        .uid-box {
            background-color: var(--color-bg-102);
            color: var(--color-bg-109);
            font-size: 2.5rem;
            font-weight: bold;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            letter-spacing: 5px;
            border: 2px dashed var(--color-bg-104);
        }

        .timer-text {
            color: var(--color-bg-105);
            font-size: 0.9rem;
            margin-top: 20px;
        }

        #seconds {
            font-weight: bold;
            color: var(--color-bg-104);
            font-size: 1.1rem;
        }

        .alert {
            background-color: #fef3c7;
            color: #92400e;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 0.85rem;
        }

        .btn-resend {
            background-color: var(--color-bg-103);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s;
        }

        .btn-resend:hover { background-color: var(--color-bg-101); }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($finalRedirect): ?>
            <h1>Cadastro Concluído!</h1>
            <p>Bem-vindo à VisionGreen. Guarde seu identificador:</p>
            
            <div class="uid-box">
                <?= htmlspecialchars($user['public_id']) ?>
            </div>

            <p class="timer-text">
                Redirecionando para o painel em <span id="seconds">30</span> segundos...
            </p>
        <?php else: ?>
            <h1>E-mail Pendente</h1>
            <div class="alert">
                Sua conta foi criada, mas ainda precisamos que você confirme seu e-mail para gerar seu acesso.
            </div>
            <form method="post" action="reset_email.php">
                <button type="submit" class="btn-resend">Reenviar Código de Confirmação</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($finalRedirect): ?>
    <script>
        let timeLeft = 30;
        const display = document.getElementById('seconds');
        
        // Define o destino dinamicamente via JS também
        const destino = "<?= ($user['type'] === 'business') ? '../../pages/business/dashboard.php' : '../../pages/person/dashboard_person.php' ?>";

        const timer = setInterval(() => {
            timeLeft--;
            display.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                window.location.replace(destino); // Usar replace impede o "voltar" pelo histórico do navegador
            }
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html>