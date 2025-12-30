<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once 'login_rate_limit.php'; 
require_once '../includes/mailer.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

/* ================= INPUT & SANITIZAÇÃO ================= */
// Alterado de 'email' para 'identifier' para aceitar UID ou Email Corporativo
$identifier = strtolower(cleanInput($_POST['identifier'] ?? ''));
$password   = $_POST['password'] ?? '';
$csrf       = $_POST['csrf'] ?? '';
$remember   = isset($_POST['remember']); 

/* ================= CSRF ================= */
if (!csrf_validate($csrf)) {
    $_SESSION['login_error'] = 'Sessão inválida. Por favor, recarregue a página.';
    header("Location: login.php");
    exit;
}

/* ================= RATE LIMIT ================= */
if (!checkLoginRateLimit($mysqli, $identifier)) {
    $_SESSION['login_error'] = 'Muitas tentativas. Por segurança, aguarde alguns minutos.';
    header("Location: login.php");
    exit;
}

if (!$identifier || !$password) {
    $_SESSION['login_error'] = 'Preencha todos os campos.';
    header("Location: login.php");
    exit;
}

try {
    /* ================= BUSCAR USUÁRIO (LÓGICA TRÍPLICE) ================= */
    // Agora busca por Email Real OU Email Corporativo OU UID (public_id)
    $stmt = $mysqli->prepare("
        SELECT id, type, nome, email, password_hash, email_verified_at, public_id, status, login_attempts, lock_until 
        FROM users 
        WHERE email = ? OR email_corporativo = ? OR public_id = ? 
        LIMIT 1
    ");
    $stmt->bind_param('sss', $identifier, $identifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    /* ================= AÇÃO: CONTA NÃO ENCONTRADA ================= */
    if (!$user) {
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <title>Conta Inexistente - VisionGreen</title>
            <style>
                body { background: #f4f7f6; font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 400px; border-top: 5px solid #28a745; }
                .timer { font-weight: bold; color: #28a745; font-size: 1.5em; }
                .btn { padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; cursor: pointer; border: none; display: inline-block; margin: 5px; }
                .btn-primary { background: #28a745; color: white; }
                .btn-secondary { background: #6c757d; color: white; }
            </style>
        </head>
        <body>
            <div class="card">
                <h2>Acesso não encontrado</h2>
                <p>O identificador <strong><?= htmlspecialchars($identifier) ?></strong> não está vinculado a nenhuma conta ativa.</p>
                <p>Redirecionando em <span id="counter" class="timer">10</span>s...</p>
                <div class="actions">
                    <button onclick="cancelar()" class="btn btn-secondary">Tentar Novamente</button>
                    <a href="../../index.php" class="btn btn-primary">Criar Conta</a>
                </div>
            </div>
            <script>
                let count = 10;
                const timer = setInterval(() => {
                    count--;
                    document.getElementById('counter').textContent = count;
                    if (count <= 0) window.location.href = 'login.php';
                }, 1000);
                function cancelar() { clearInterval(timer); window.location.href = 'login.php'; }
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    /* ================= VERIFICAÇÃO DE BLOQUEIO ================= */
    if ($user['lock_until'] && strtotime($user['lock_until']) > time()) {
        $_SESSION['login_error'] = 'Conta bloqueada temporariamente.';
        header("Location: forgot_password.php?info=locked");
        exit;
    }

    /* ================= VALIDAÇÃO DE SENHA ================= */
    if (!password_verify($password, $user['password_hash'])) {
        $attempts = $user['login_attempts'] + 1;
        if ($attempts >= 5) {
            $lockUntil = date('Y-m-d H:i:s', time() + 1800);
            $upd = $mysqli->prepare("UPDATE users SET login_attempts = 0, lock_until = ? WHERE id = ?");
            $upd->bind_param('si', $lockUntil, $user['id']);
            $upd->execute();
            $_SESSION['login_error'] = 'Limite de tentativas atingido. Conta suspensa por 30 min.';
            header("Location: forgot_password.php?reason=bruteforce");
        } else {
            $upd = $mysqli->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
            $upd->bind_param('ii', $attempts, $user['id']);
            $upd->execute();
            $_SESSION['login_error'] = "Senha incorreta. Tentativa $attempts de 5.";
            header("Location: login.php");
        }
        exit;
    }

    /* ================= LOGIN BEM-SUCEDIDO ================= */
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $log_stmt = $mysqli->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
    $log_stmt->bind_param('iss', $user['id'], $ip_address, $user_agent);
    $log_stmt->execute();

    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        $rem_stmt = $mysqli->prepare("INSERT INTO remember_me (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $rem_stmt->bind_param('iss', $user['id'], $token_hash, $expires_at);
        $rem_stmt->execute();

        setcookie('remember_token', $token, [
            'expires' => time() + (86400 * 30),
            'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        ]);
    }

    // Reset de tentativas
    $reset = $mysqli->prepare("UPDATE users SET login_attempts = 0, lock_until = NULL WHERE id = ?");
    $reset->bind_param('i', $user['id']);
    $reset->execute();
    clearLoginAttempts($mysqli, $identifier);

    /* ================= VERIFICAÇÃO DE E-MAIL PENDENTE ================= */
    if (!$user['email_verified_at']) {
        $token_mail = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $upd_mail = $mysqli->prepare("UPDATE users SET email_token = ?, email_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
        $upd_mail->bind_param('si', $token_mail, $user['id']);
        $upd_mail->execute();

        enviarEmailVisionGreen($user['email'], $user['nome'], $token_mail);
        $_SESSION['user_id'] = $user['id'];
        header("Location: ../process/verify_email.php?info=expirado");
        exit;
    }

    /* ================= FINALIZAÇÃO E REDIRECIONAMENTO POR TIPO ================= */
    session_regenerate_id(true);
    $_SESSION['auth'] = [
        'user_id'   => $user['id'], 
        'email'     => $user['email'], 
        'public_id' => $user['public_id'], 
        'nome'      => $user['nome'],
        'type'      => $user['type']
    ];
    
    // REDIRECIONAMENTO INTELIGENTE
    if ($user['type'] === 'company') {
        header("Location: ../../pages/business/dashboard_business.php");
    } else {
        header("Location: ../../pages/person/dashboard_person.php");
    }
    exit;

} catch (Exception $e) {
    error_log("Erro Crítico no Login: " . $e->getMessage());
    $_SESSION['login_error'] = 'Ocorreu um erro interno. Tente novamente.';
    header("Location: login.php");
    exit;
}