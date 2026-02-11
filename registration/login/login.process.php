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
/* ================= INPUT & SANITIZAÇÃO ================= */
$identifier = strtolower(cleanInput($_POST['identifier'] ?? ''));
$password   = $_POST['password'] ?? '';
$csrf       = $_POST['csrf'] ?? '';
$remember   = isset($_POST['remember']); 
$ip_address = $_SERVER['REMOTE_ADDR'];

/* ================= SALVAR DADOS DO FORMULÁRIO PARA MANTER EM CASO DE ERRO ================= */
$_SESSION['login_form_data'] = [
    'identifier' => $identifier,
    'remember' => $remember
];

/* ================= CSRF ================= */
if (!csrf_validate($csrf)) {
    $_SESSION['login_error'] = '🔒 Sessão inválida. Por favor, recarregue a página e tente novamente.';
    header("Location: login.php");
    exit;
}

/* ================= VALIDAÇÃO DE CAMPOS ================= */
if (empty($identifier) || empty($password)) {
    $_SESSION['login_error'] = '⚠️ Por favor, preencha todos os campos (email/UID e senha).';
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
$stmt_check->close();

if ($res_check && $res_check['attempts'] >= 5) {
    $_SESSION['login_error'] = '🚫 Muitas tentativas de login falhadas. Por segurança, aguarde 30 minutos antes de tentar novamente.';
    header("Location: login.php");
    exit;
}

try {
    /* ================= FLUXO PARA FUNCIONÁRIO ================= */
    if ($isEmployeeLogin) {
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
        ");
        
        $stmt->bind_param('s', $identifier);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$employee) {
            $stmt_fail = $mysqli->prepare("
                INSERT INTO login_attempts (email, ip_address, attempts, last_attempt) 
                VALUES (?, ?, 1, NOW()) 
                ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
            ");
            $stmt_fail->bind_param('ss', $identifier, $ip_address);
            $stmt_fail->execute();
            $stmt_fail->close();
            
            $_SESSION['login_error'] = '❌ Email corporativo <strong>' . htmlspecialchars($identifier) . '</strong> não encontrado ou sem permissão de acesso ao sistema.';
            header("Location: login.php");
            exit;
        }
        
        if (!$employee['pode_acessar_sistema']) {
            $_SESSION['login_error'] = '🚫 Você não tem permissão para acessar o sistema. Entre em contato com seu gestor.';
            header("Location: login.php");
            exit;
        }
        
        if ($employee['primeiro_acesso']) {
            $_SESSION['login_error'] = '📧 Você ainda não definiu sua senha. Verifique seu email corporativo e clique no link de ativação.';
            header("Location: login.php");
            exit;
        }
        
        if ($employee['employee_status'] !== 'ativo') {
            $status_msg = [
                'inativo' => 'inativa',
                'ferias' => 'em férias',
                'afastado' => 'afastada'
            ];
            $status_text = $status_msg[$employee['employee_status']] ?? $employee['employee_status'];
            
            $_SESSION['login_error'] = '⚠️ Sua conta está <strong>' . $status_text . '</strong>. Entre em contato com seu gestor para mais informações.';
            header("Location: login.php");
            exit;
        }
        
        if ($employee['status'] === 'blocked') {
            $_SESSION['login_error'] = '🔒 Sua conta está bloqueada. Entre em contato com o departamento de RH ou seu gestor.';
            header("Location: login.php");
            exit;
        }
        
        if (!password_verify($password, $employee['password_hash'])) {
            $stmt_fail = $mysqli->prepare("
                INSERT INTO login_attempts (email, ip_address, attempts, last_attempt) 
                VALUES (?, ?, 1, NOW()) 
                ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
            ");
            $stmt_fail->bind_param('ss', $identifier, $ip_address);
            $stmt_fail->execute();
            $stmt_fail->close();
            
            $stmt_count = $mysqli->prepare("SELECT attempts FROM login_attempts WHERE email = ?");
            $stmt_count->bind_param('s', $identifier);
            $stmt_count->execute();
            $attempts_data = $stmt_count->get_result()->fetch_assoc();
            $attempts = $attempts_data['attempts'] ?? 1;
            $stmt_count->close();
            
            $remaining = 5 - $attempts;
            
            if ($remaining > 0) {
                $_SESSION['login_error'] = "🔑 <strong>Senha incorreta.</strong> Você tem mais {$remaining} tentativa(s) antes do bloqueio temporário.";
            } else {
                $_SESSION['login_error'] = '🚫 Senha incorreta. Sua conta foi bloqueada por 30 minutos devido a múltiplas tentativas falhadas.';
            }
            
            header("Location: login.php");
            exit;
        }
        
        $stmt_clear = $mysqli->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt_clear->bind_param('s', $identifier);
        $stmt_clear->execute();
        $stmt_clear->close();
        
        session_regenerate_id(true);

        unset($_SESSION['login_form_data']);
        
        $_SESSION['employee_auth'] = [
            'employee_id' => $employee['employee_id'],
            'user_id' => $employee['id'],
            'empresa_id' => $employee['empresa_id'],
            'nome' => $employee['nome'],
            'email_pessoal' => $employee['email_pessoal'],
            'email_company' => $employee['email_company'],
            'cargo' => $employee['cargo'],
            'empresa_nome' => $employee['empresa_nome'],
            'login_time' => time()
        ];
        
        $mysqli->query("UPDATE employees SET ultimo_login = NOW() WHERE id = " . $employee['employee_id']);
        
        $stmt_log = $mysqli->prepare("
            INSERT INTO employee_access_logs (employee_id, action, ip_address, user_agent)
            VALUES (?, 'login', ?, ?)
        ");
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt_log->bind_param('iss', $employee['employee_id'], $ip_address, $userAgent);
        $stmt_log->execute();
        $stmt_log->close();
        
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

    if (!$user) {
        $stmt_fail = $mysqli->prepare("
            INSERT INTO login_attempts (email, ip_address, attempts, last_attempt) 
            VALUES (?, ?, 1, NOW()) 
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
        ");
        $stmt_fail->bind_param('ss', $identifier, $ip_address);
        $stmt_fail->execute();
        $stmt_fail->close();
        
        $_SESSION['login_error'] = '❌ Nenhuma conta encontrada com o identificador <strong>' . htmlspecialchars($identifier) . '</strong>. Verifique se digitou corretamente ou <a href="../process/start.php" style="color: #00a63e; text-decoration: underline;">cadastre-se aqui</a>.';
        header("Location: login.php");
        exit;
    }

    if ($user['status'] === 'blocked') {
        $_SESSION['login_error'] = '🔒 Esta conta está <strong>permanentemente bloqueada</strong> por motivos de segurança. Entre em contato com o suporte se achar que isso é um erro.';
        header("Location: login.php");
        exit;
    }

    if ($user['status'] === 'pending') {
        $_SESSION['login_error'] = '⏳ Sua conta ainda está <strong>pendente de ativação</strong>. Verifique seu email para completar o cadastro.';
        header("Location: login.php");
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        $stmt_fail = $mysqli->prepare("
            INSERT INTO login_attempts (email, ip_address, attempts, last_attempt) 
            VALUES (?, ?, 1, NOW()) 
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
        ");
        $stmt_fail->bind_param('ss', $identifier, $ip_address);
        $stmt_fail->execute();
        $stmt_fail->close();
        
        $stmt_count = $mysqli->prepare("SELECT attempts FROM login_attempts WHERE email = ?");
        $stmt_count->bind_param('s', $identifier);
        $stmt_count->execute();
        $attempts_data = $stmt_count->get_result()->fetch_assoc();
        $attempts = $attempts_data['attempts'] ?? 1;
        $stmt_count->close();
        
        $remaining = 5 - $attempts;
        
        if ($remaining > 0) {
            $_SESSION['login_error'] = "🔑 <strong>Senha incorreta.</strong> Você tem mais <strong>{$remaining} tentativa(s)</strong> antes do bloqueio temporário. <a href='forgot_password.php' style='color: #00a63e;'>Esqueceu sua senha?</a>";
        } else {
            $_SESSION['login_error'] = '🚫 Senha incorreta. Sua conta foi <strong>bloqueada por 30 minutos</strong> devido a múltiplas tentativas falhadas.';
        }
        
        header("Location: login.php");
        exit;
    }

    if (in_array($user['role'], ['admin', 'superadmin']) || $user['type'] === 'admin') {
        $stmt_clear = $mysqli->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt_clear->bind_param('s', $identifier);
        $stmt_clear->execute();
        $stmt_clear->close();
        
        session_regenerate_id(true);

        unset($_SESSION['login_form_data']);
        
        $_SESSION['temp_admin_auth'] = [
            'user_id'   => $user['id'], 
            'email'     => $user['email'], 
            'public_id' => $user['public_id'], 
            'nome'      => $user['nome'],
            'type'      => $user['type'],
            'role'      => $user['role']
        ];
        
        unset($_SESSION['auth']); 
        header("Location: ../../pages/admin/verify_secure_access.php");
        exit;
    }

    if (!$user['email_verified_at']) {
        $token_mail = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $upd_mail = $mysqli->prepare("UPDATE users SET email_token = ?, email_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
        $upd_mail->bind_param('si', $token_mail, $user['id']);
        $upd_mail->execute();
        $upd_mail->close();
        
        enviarEmailVisionGreen($user['email'], $user['nome'], $token_mail);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['login_error'] = '📧 Seu email ainda não foi verificado. Um novo código foi enviado para <strong>' . htmlspecialchars($user['email']) . '</strong>.';
        header("Location: ../process/verify_email.php");
        exit;
    }

    $stmt_clear = $mysqli->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt_clear->bind_param('s', $identifier);
    $stmt_clear->execute();
    $stmt_clear->close();
    
    session_regenerate_id(true);

    unset($_SESSION['login_form_data']);

    $_SESSION['auth'] = [
        'user_id'   => $user['id'], 
        'email'     => $user['email'], 
        'public_id' => $user['public_id'], 
        'nome'      => $user['nome'],
        'type'      => $user['type'],
        'role'      => $user['role']
    ];

    $stmt_log = $mysqli->prepare("
        INSERT INTO login_logs (user_id, ip_address, user_agent, status)
        VALUES (?, ?, ?, 'success')
    ");
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $stmt_log->bind_param('iss', $user['id'], $ip_address, $userAgent);
    $stmt_log->execute();
    $stmt_log->close();

    if ($user['type'] === 'person') {
        header("Location: ../../pages/person/index.php");
    } elseif ($user['type'] === 'company') {
        header("Location: ../../pages/business/dashboard_business.php");
    } else {
        session_destroy();
        $_SESSION['login_error'] = '⚠️ Tipo de conta inválido. Entre em contato com o suporte.';
        header("Location: login.php");
    }
    exit;

} catch (Exception $e) {
    error_log("Erro Crítico no Login: " . $e->getMessage() . " | Arquivo: " . $e->getFile() . " | Linha: " . $e->getLine());
    $_SESSION['login_error'] = '🔧 Ocorreu um erro interno no sistema. Por favor, tente novamente em alguns instantes. Se o problema persistir, entre em contato com o suporte.';
    header("Location: login.php");
    exit;
}