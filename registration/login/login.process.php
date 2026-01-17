<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once 'login_rate_limit.php'; 
require_once '../includes/mailer.php'; 

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

/* ================= DETECTAR SE É FUNCIONÁRIO ================= */
$isEmployeeLogin = false;
if (strpos($identifier, '@') !== false && strpos($identifier, '.vsg.com') !== false) {
    $isEmployeeLogin = true;
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
    /* ================= FLUXO PARA FUNCIONÁRIO ================= */
    /* ================= FLUXO PARA FUNCIONÁRIO ================= */
    if ($isEmployeeLogin) {
        // Buscar funcionário usando email_company para localizar em employees
        // Depois fazer JOIN com users usando user_employee_id
        $stmt = $mysqli->prepare("
            SELECT 
                u.id, 
                u.nome, 
                u.email as email_pessoal, 
                u.telefone, 
                u.password_hash, 
                u.status,
                e.id as employee_id, 
                e.cargo, 
                e.user_id as empresa_id, 
                e.email_company, 
                e.pode_acessar_sistema, 
                e.primeiro_acesso,
                e.status as employee_status,
                emp.nome as empresa_nome
            FROM employees e
            INNER JOIN users u ON e.user_employee_id = u.id
            INNER JOIN users emp ON e.user_id = emp.id
            WHERE e.email_company COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
            AND u.type = 'employee'
            AND e.is_active = 1
            AND e.pode_acessar_sistema = 1
        ");
        
        $stmt->bind_param('s', $identifier);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$employee) {
            $_SESSION['login_error'] = 'Email corporativo não encontrado ou sem permissão de acesso.';
            header("Location: login.php");
            exit;
        }
        
        // Verificar se ainda é primeiro acesso
        if ($employee['primeiro_acesso']) {
            $_SESSION['login_error'] = 'Você ainda não definiu sua senha. Verifique seu email.';
            header("Location: login.php");
            exit;
        }
        
        // Verificar status do funcionário
        if ($employee['employee_status'] !== 'ativo') {
            $_SESSION['login_error'] = 'Sua conta está ' . $employee['employee_status'] . '. Entre em contato com seu gestor.';
            header("Location: login.php");
            exit;
        }
        
        // Verificar status do usuário
        if ($employee['status'] === 'blocked') {
            $_SESSION['login_error'] = 'Sua conta está bloqueada. Entre em contato com seu gestor.';
            header("Location: login.php");
            exit;
        }
        
        // Verificar senha
        if (!password_verify($password, $employee['password_hash'])) {
            $stmt_fail = $mysqli->prepare("
                INSERT INTO login_attempts (email, ip, attempts, last_attempt) 
                VALUES (?, ?, 1, NOW()) 
                ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
            ");
            $stmt_fail->bind_param('ss', $identifier, $ip_address);
            $stmt_fail->execute();
            
            $_SESSION['login_error'] = "Senha incorreta.";
            header("Location: login.php");
            exit;
        }
        
        // LOGIN DE FUNCIONÁRIO BEM-SUCEDIDO!
        $stmt_clear = $mysqli->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt_clear->bind_param('s', $identifier);
        $stmt_clear->execute();
        
        session_regenerate_id(true);
        
        $_SESSION['employee_auth'] = [
            'employee_id' => $employee['employee_id'],
            'user_id' => $employee['id'], // ID na tabela users
            'empresa_id' => $employee['empresa_id'],
            'nome' => $employee['nome'],
            'email_pessoal' => $employee['email_pessoal'],
            'email_company' => $employee['email_company'],
            'cargo' => $employee['cargo'],
            'empresa_nome' => $employee['empresa_nome'],
            'login_time' => time()
        ];
        
        // Atualizar último login
        $mysqli->query("UPDATE employees SET ultimo_login = NOW() WHERE id = " . $employee['employee_id']);
        
        // Registrar log
        $stmt = $mysqli->prepare("
            INSERT INTO employee_access_logs (employee_id, action, ip_address, user_agent)
            VALUES (?, 'login', ?, ?)
        ");
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt->bind_param('iss', $employee['employee_id'], $ip_address, $userAgent);
        $stmt->execute();
        
        // Redirecionar para dashboard
        header("Location: ../../pages/business/dashboard_business.php");
        exit;
    }

    /* ================= FLUXO PARA USUÁRIO NORMAL (GESTOR/PESSOA) ================= */
    $stmt = $mysqli->prepare("
        SELECT id, type, role, nome, email, password_hash, email_verified_at, public_id, status, password_changed_at 
        FROM users 
        WHERE (email = ? OR email_corporativo = ? OR public_id = ?)
        AND type != 'employee'
        LIMIT 1
    ");
    $stmt->bind_param('sss', $identifier, $identifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    /* ================= CONTA NÃO ENCONTRADA ================= */
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

    /* ================= BLOQUEIO ================= */
    if ($user['status'] === 'blocked') {
        $_SESSION['login_error'] = 'Esta conta está permanentemente bloqueada por motivos de segurança.';
        header("Location: login.php");
        exit;
    }

    /* ================= VALIDAÇÃO DE SENHA ================= */
    if (!password_verify($password, $user['password_hash'])) {
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

    /* ================= RESTO DO CÓDIGO PERMANECE IGUAL ================= */
    // ... (código de segurança admin, verificação de email, etc)

    /* ================= LOGIN BEM-SUCEDIDO ================= */
    $stmt_clear = $mysqli->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt_clear->bind_param('s', $identifier);
    $stmt_clear->execute();
    
    session_regenerate_id(true);

    $_SESSION['auth'] = [
        'user_id'   => $user['id'], 
        'email'     => $user['email'], 
        'public_id' => $user['public_id'], 
        'nome'      => $user['nome'],
        'type'      => $user['type'],
        'role'      => $user['role']
    ];

    /* ================= REDIRECIONAMENTO ================= */
    if (in_array($user['role'], ['admin', 'superadmin']) || $user['type'] === 'admin') {
        $_SESSION['temp_admin_auth'] = $_SESSION['auth'];
        unset($_SESSION['auth']); 
        header("Location: ../../pages/admin/verify_secure_access.php");
        exit;
    }

    // Verificação de email
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

    // Redirecionamento final
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