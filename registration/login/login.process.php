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

/* ================= RATE LIMIT ================= */
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
        // ✅ CORRIGIDO: Usando prepared statement
        $stmt_fail = $mysqli->prepare("
            INSERT INTO login_attempts (email, ip, attempts, last_attempt) 
            VALUES (?, ?, 1, NOW()) 
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
        ");
        $stmt_fail->bind_param('ss', $identifier, $ip_address);
        $stmt_fail->execute();
        
        $_SESSION['login_error'] = "Credenciais inválidas.";
        header("Location: login.php");
        exit;
    }

    /* ================= 2. LÓGICA DE SEGURANÇA PARA ADMINS (CORRIGIDA) ================= */
    if (in_array($user['role'], ['admin', 'superadmin'])) {
        $lastChange = strtotime($user['password_changed_at']);
        $currentTime = time();
        $timeoutLimit = ($user['role'] === 'superadmin') ? 3600 : 86400; // 1h ou 24h
        $timeSinceChange = $currentTime - $lastChange;

        // ✅ NOVO: Apenas AVISA se a senha expirou, mas DEIXA entrar
        if ($timeSinceChange >= $timeoutLimit) {
            // Calcula tempo decorrido
            $hoursExpired = floor($timeSinceChange / 3600);
            $minutesExpired = floor(($timeSinceChange % 3600) / 60);
            
            // Salva na sessão para mostrar no dashboard
            $_SESSION['password_expired'] = true;
            $_SESSION['password_expired_since'] = $lastChange;
            $_SESSION['password_expired_time'] = "{$hoursExpired}h {$minutesExpired}m";
            
            // ✅ CORRIGIDO: Prepared statement para log
            $stmt_log = $mysqli->prepare("
                INSERT INTO admin_audit_logs (admin_id, action, ip_address, details) 
                VALUES (?, 'LOGIN_WITH_EXPIRED_PASSWORD', ?, ?)
            ");
            $details = "Senha expirada há {$hoursExpired}h {$minutesExpired}m - Login permitido com aviso";
            $stmt_log->bind_param('iss', $user['id'], $ip_address, $details);
            $stmt_log->execute();
            
            // Define mensagem de aviso (não erro!)
            $_SESSION['password_warning'] = '⚠️ SEGURANÇA: Sua senha expirou! Renove imediatamente no painel.';
            
            // ✅ IMPORTANTE: NÃO FAZ EXIT AQUI - Continua o login normalmente!
        }
        
        // ✅ NOVO: Sistema de "última chance" - Após 48h, força renovação
        if ($timeSinceChange >= 172800) { // 48 horas
            // Em vez de bloquear, redireciona para renovação obrigatória
            $_SESSION['force_password_renewal'] = true;
            $_SESSION['temp_admin_auth'] = [
                'user_id'   => $user['id'], 
                'email'     => $user['email'], 
                'public_id' => $user['public_id'], 
                'nome'      => $user['nome'],
                'type'      => $user['type'],
                'role'      => $user['role']
            ];
            
            // ✅ CORRIGIDO: Prepared statement
            $stmt_log2 = $mysqli->prepare("
                INSERT INTO admin_audit_logs (admin_id, action, ip_address, details) 
                VALUES (?, 'FORCED_PASSWORD_RENEWAL_TRIGGER', ?, '48h sem renovação - Renovação obrigatória')
            ");
            $stmt_log2->bind_param('is', $user['id'], $ip_address);
            $stmt_log2->execute();
            
            header("Location: ../../admin/system/force_password_change.php");
            exit;
        }
    }

    /* ================= 3. LOGIN BEM-SUCEDIDO ================= */
    // ✅ CORRIGIDO: Prepared statement
    $stmt_clear = $mysqli->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt_clear->bind_param('s', $identifier);
    $stmt_clear->execute();
    
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

    /* ================= 4. FLUXO DE REDIRECIONAMENTO FINAL ================= */

    // A. FLUXO PARA ADMINS (STEP 2: SECURE ID)
    if (in_array($user['role'], ['admin', 'superadmin']) || $user['type'] === 'admin') {
        
        // ✅ CORRIGIDO: Prepared statement para log
        $stmt_log3 = $mysqli->prepare("
            INSERT INTO admin_audit_logs (admin_id, action, ip_address) 
            VALUES (?, 'LOGIN_STEP_1_SUCCESS', ?)
        ");
        $stmt_log3->bind_param('is', $user['id'], $ip_address);
        $stmt_log3->execute();

        // Movemos para sessão temporária para exigir o Secure ID
        $_SESSION['temp_admin_auth'] = $_SESSION['auth'];
        unset($_SESSION['auth']); 

        // Redireciona para a página do Secure ID (V-S-G)
        header("Location: ../../pages/admin/verify_secure_access.php");
        exit;
    }

    // B. FLUXO PARA USUÁRIOS COMUNS (PERSON/COMPANY)
    
    // Verificação de e-mail pendente
    if (!$user['email_verified_at']) {
        // Gera código 2FA de 6 dígitos
        $token_mail = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // ✅ CORRIGIDO: Prepared statement
        $upd_mail = $mysqli->prepare("
            UPDATE users 
            SET email_token = ?, email_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) 
            WHERE id = ?
        ");
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
?>