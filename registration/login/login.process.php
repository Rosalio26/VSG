<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once 'login_rate_limit.php'; 
require_once '../includes/mailer.php'; 

// Garante sincronia global UTC
date_default_timezone_set('UTC');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

/* ================= INPUT & SANITIZAÇÃO ================= */
$identifier = strtolower(cleanInput($_POST['identifier'] ?? ''));
$password   = $_POST['password'] ?? '';
$csrf       = $_POST['csrf'] ?? '';
$remember   = isset($_POST['remember']); 
$ip_address = $_SERVER['REMOTE_ADDR'];

/* ================= CSRF ================= */
if (!csrf_validate($csrf)) {
    $_SESSION['login_error'] = 'Sessão inválida. Por favor, recarregue a página.';
    header("Location: login.php");
    exit;
}

/* ================= RATE LIMIT (Usando sua tabela login_attempts) ================= */
$stmt_check = $mysqli->prepare("SELECT attempts FROM login_attempts WHERE email = ? AND last_attempt > NOW() - INTERVAL 30 MINUTE");
$stmt_check->bind_param("s", $identifier);
$stmt_check->execute();
$res_check = $stmt_check->get_result()->fetch_assoc();

if ($res_check && $res_check['attempts'] >= 5) {
    $_SESSION['login_error'] = 'Muitas tentativas. Por segurança, aguarde 30 minutos.';
    header("Location: login.php");
    exit;
}

if (!$identifier || !$password) {
    $_SESSION['login_error'] = 'Preencha todos os campos.';
    header("Location: login.php");
    exit;
}

try {
    /* ================= BUSCAR USUÁRIO ================= */
    $stmt = $mysqli->prepare("
        SELECT id, type, role, nome, email, password_hash, email_verified_at, public_id, status, password_changed_at 
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
                body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 400px; border-top: 5px solid #00a63e; }
                .timer { font-weight: bold; color: #00a63e; font-size: 1.5em; }
                .btn { padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; cursor: pointer; border: none; display: inline-block; margin: 5px; }
                .btn-primary { background: #00a63e; color: white; }
                .btn-secondary { background: #64748b; color: white; }
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
    if ($user['status'] === 'blocked') {
        $_SESSION['login_error'] = 'Esta conta está permanentemente bloqueada por motivos de segurança.';
        header("Location: login.php");
        exit;
    }

    /* ================= 1. VALIDAÇÃO DE SENHA (FÍSICA) ================= */
    if (!password_verify($password, $user['password_hash'])) {
        $mysqli->query("INSERT INTO login_attempts (email, ip, attempts, last_attempt) 
                        VALUES ('$identifier', '$ip_address', 1, NOW()) 
                        ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()");
        
        $_SESSION['login_error'] = "Credenciais inválidas.";
        header("Location: login.php");
        exit;
    }

    /* ================= 2. LÓGICA DE SEGURANÇA PARA ADMINS ================= */
    if (in_array($user['role'], ['admin', 'superadmin'])) {
        $lastChange = strtotime($user['password_changed_at']);
        $currentTime = time();

        // KILL-SWITCH (24 HORAS)
        if ($currentTime > ($lastChange + 86400)) {
            $mysqli->query("UPDATE users SET status = 'blocked', is_in_lockdown = 1 WHERE id = " . $user['id']);
            $mysqli->query("UPDATE businesses SET status_documentos = 'rejeitado', motivo_rejeicao = 'LOCKOUT: INATIVIDADE ADMINISTRATIVA SUPERIOR A 24H'");
            $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ({$user['id']}, 'KILL_SWITCH_24H', '$ip_address')");
            
            $_SESSION['login_error'] = 'Protocolo de segurança 24h ativado. O acesso foi revogado.';
            header("Location: login.php");
            exit;
        }

        // ROTAÇÃO SILENCIOSA (1 HORA)
        if ($currentTime > ($lastChange + 3600)) {
            $new_pass = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*"), 0, 10);
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $php_now = date('Y-m-d H:i:s');

            $mysqli->query("UPDATE users SET password_hash = '$new_hash', password_changed_at = '$php_now' WHERE id = " . $user['id']);
            $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ({$user['id']}, 'OFFLINE_AUTO_ROTATION', '$ip_address')");
            
            enviarEmailVisionGreen($user['email'], $user['nome'], $new_pass);

            $_SESSION['login_error'] = 'Sua senha expirou por segurança. Verifique seu e-mail para obter a nova credencial.';
            header("Location: login.php?info=new_password_sent");
            exit;
        }
    }

    /* ================= 3. LOGIN BEM-SUCEDIDO ================= */
    $mysqli->query("DELETE FROM login_attempts WHERE email = '$identifier'");
    session_regenerate_id(true);

    // Salva os dados na sessão
    $_SESSION['auth'] = [
        'user_id'   => $user['id'], 
        'email'     => $user['email'], 
        'public_id' => $user['public_id'], 
        'nome'      => $user['nome'],
        'type'      => $user['type'],
        'role'      => $user['role']
    ];

    /* ================= 4. FLUXO DE REDIRECIONAMENTO FINAL ABSOLUTO ================= */

    // A. FLUXO PARA ADMINS (STEP 2: 2FA + SECURE ID)
    // Agora validamos tanto pelo cargo (role) quanto pelo novo tipo (admin)
    if (in_array($user['role'], ['admin', 'superadmin']) || $user['type'] === 'admin') {
        $two_fa_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $stmt_2fa = $mysqli->prepare("UPDATE users SET email_token = ?, email_token_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?");
        $stmt_2fa->bind_param('si', $two_fa_code, $user['id']);
        $stmt_2fa->execute();

        $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ({$user['id']}, 'LOGIN_STEP_1_SUCCESS', '$ip_address')");

        enviarEmailVisionGreen($user['email'], $user['nome'], $two_fa_code);

        // Movemos para sessão temporária
        $_SESSION['temp_admin_auth'] = $_SESSION['auth'];
        unset($_SESSION['auth']); 

        header("Location: ../../pages/admin/verify_secure_access.php");
        exit;
    }

    // B. FLUXO PARA USUÁRIOS COMUNS (PERSON/COMPANY)
    
    // Verificação de e-mail pendente
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

    // Redirecionamento por tipo de conta
    if ($user['type'] === 'person') {
        header("Location: ../../pages/person/dashboard_person.php");
    } elseif ($user['type'] === 'company') {
        header("Location: ../../pages/business/dashboard_business.php");
    } else {
        // Fallback de segurança caso o type seja desconhecido
        session_destroy();
        header("Location: login.php?error=invalid_account_type");
    }
    exit;

} catch (Exception $e) {
    error_log("Erro Crítico no Login: " . $e->getMessage());
    $_SESSION['login_error'] = 'Ocorreu um erro interno.';
    header("Location: login.php");
    exit;
}