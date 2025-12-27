<?php
session_start();

require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception as MailException;

$erro = '';
$info = '';

// ================= BLOQUEIO DE ACESSO =================
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
$userId = (int) $_SESSION['user_id'];

// ================= MENSAGEM DE INFORMAÇÃO =================
if (!empty($_GET['info'])) {
    $info_key = $_GET['info'];
    $messages = [
        'processando_envio' => 'Código enviado com sucesso! Verifique sua caixa de entrada.',
        'codigo_enviado'    => 'Um novo código foi enviado para seu e-mail.',
        'expirado'          => 'O código anterior expirou. Um novo foi gerado.'
    ];
    $info = $messages[$info_key] ?? 'Aguardando confirmação do código.';
}

// ================= PROCESSAMENTO DO FORMULÁRIO (MANTIDO PARA FALLBACK) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo'])) {
    
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $erro = "Token de segurança inválido. Recarregue a página.";
    } else {
        $codigo = trim($_POST['codigo'] ?? '');

        if (!preg_match('/^\d{6}$/', $codigo)) {
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
                } elseif ($user['email_token'] !== $codigo) {
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
    <style>
        :root {
            --color-bg-000: #dcfce7;
            --color-bg-001: #ffffff;
            --color-bg-101: #111827;
            --color-bg-103: #364153;
            --color-bg-104: #00a63e;
            --color-bg-105: #4a5565;
            --color-bg-109: #4ade80;
            --color-dg-001: #ff3232;
        }

        body {
            background-color: var(--color-bg-000);
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            background-color: var(--color-bg-001);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border: 1px solid var(--color-bg-109);
        }

        h1 { color: var(--color-bg-104); font-size: 1.5rem; margin-bottom: 10px; }
        p { color: var(--color-bg-105); font-size: 0.9rem; }

        .msg { padding: 12px; margin-bottom: 20px; border-radius: 8px; font-size: 0.85rem; }
        .msg-error { background: #fee2e2; color: var(--color-dg-001); border: 1px solid #fecaca; }
        .msg-info { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

        form { display: flex; flex-direction: column; gap: 15px; }
        
        input[type="text"] {
            padding: 12px;
            font-size: 1.2rem;
            text-align: center;
            letter-spacing: 8px;
            border: 2px solid var(--color-bg-109);
            border-radius: 8px;
            outline: none;
            transition: 0.3s;
        }

        /* Estilo para erro automático */
        input.input-error {
            border-color: var(--color-dg-001);
            background-color: #fff5f5;
        }

        button {
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-confirm { background-color: var(--color-bg-104); color: white; }
        .btn-confirm:hover { background-color: #008f35; }

        .btn-resend { 
            background-color: var(--color-bg-105); 
            color: white; 
            font-size: 0.8rem;
        }
        
        .btn-resend:disabled {
            background-color: #cbd5e1;
            cursor: not-allowed;
            color: #64748b;
        }

        hr { border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0; }
        .resend-box { font-size: 0.85rem; color: var(--color-bg-105); }

        /* Loader simples */
        .loader {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--color-bg-104);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="container">
    <h1>Confirmação de E-mail</h1>
    <p>Insira o código de 6 dígitos enviado para seu e-mail.</p>

    <div id="ajaxLoader" class="loader"></div>

    <div id="feedbackMsg">
        <?php if ($info): ?>
            <div class="msg msg-info"><?= htmlspecialchars($info) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="msg msg-error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
    </div>

    <form id="verifyForm" method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= $csrf_token ?>">
        <input 
            type="text" 
            name="codigo" 
            id="inputCodigo"
            maxlength="6" 
            pattern="\d{6}" 
            inputmode="numeric" 
            placeholder="000000"
            required
            autofocus
        >
        <button type="submit" class="btn-confirm">Confirmar Código</button>
    </form>

    <hr>

    <div class="resend-box">
        <p>Não recebeu o código?</p>
        <form method="post" action="reset_email.php">
            <input type="hidden" name="csrf" value="<?= $csrf_token ?>">
            <button type="submit" id="btnResend" class="btn-resend" disabled>
                Reenviar Código (<span id="timer">5</span>s)
            </button>
        </form>
    </div>
</div>



<script>
    const inputCodigo = document.getElementById('inputCodigo');
    const feedbackMsg = document.getElementById('feedbackMsg');
    const ajaxLoader = document.getElementById('ajaxLoader');

    // Lógica de Verificação Automática
    inputCodigo.addEventListener('input', function() {
        this.classList.remove('input-error');
        
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
                feedbackMsg.innerHTML = '<div class="msg msg-info">Código correto! Redirecionando...</div>';
                window.location.href = '../register/gerar_uid.php';
            } else {
                ajaxLoader.style.display = "none";
                inputCodigo.classList.add('input-error');
                inputCodigo.value = ""; // Limpa para nova tentativa
                feedbackMsg.innerHTML = `<div class="msg msg-error">${data.error}</div>`;
                inputCodigo.focus();
            }
        } catch (error) {
            ajaxLoader.style.display = "none";
            feedbackMsg.innerHTML = '<div class="msg msg-error">Erro na conexão. Tente novamente.</div>';
        }
    }

    // Lógica do botão de reenvio com atraso de 5 segundos
    let timeLeft = 5;
    const btnResend = document.getElementById('btnResend');
    const timerDisplay = document.getElementById('timer');

    const countdown = setInterval(() => {
        timeLeft--;
        if (timeLeft > 0) {
            timerDisplay.textContent = timeLeft;
        } else {
            clearInterval(countdown);
            btnResend.disabled = false;
            btnResend.textContent = "Reenviar Código Agora";
            btnResend.style.backgroundColor = "var(--color-bg-103)";
        }
    }, 1000);
</script>

</body>
</html>