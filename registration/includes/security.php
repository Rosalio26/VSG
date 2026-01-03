<?php

/* ================= 1. GARANTE SESSÃO E SEGURANÇA BÁSICA ================= */
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/* ================= 2. HEADERS DE SEGURANÇA ================= */
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

/* ================= 3. FUNÇÕES GLOBAIS (SEMPRE DISPONÍVEIS) ================= */

/**
 * Sanitização contra XSS
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Gera token CSRF
 */
function csrf_generate(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Retorna o campo hidden para formulários
 */
function csrf_field(): string {
    $token = csrf_generate();
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Valida o token recebido
 */
function csrf_validate(?string $token): bool {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/* ================= 4. LÓGICA DE AUTO-LOGIN (REMEMBER ME) ================= */
if (!isset($_SESSION['auth']) && isset($_COOKIE['remember_token'])) {
    require_once __DIR__ . '/db.php';
    $token_hash = hash('sha256', $_COOKIE['remember_token']);
    
    $stmt = $mysqli->prepare("
        SELECT u.id, u.nome, u.email, u.public_id, u.type, u.role 
        FROM users u 
        JOIN remember_me r ON u.id = r.user_id 
        WHERE r.token_hash = ? AND r.expires_at > NOW() 
        LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param('s', $token_hash);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            $_SESSION['auth'] = [
                'user_id'   => $user['id'],
                'nome'      => $user['nome'],
                'email'     => $user['email'],
                'public_id' => $user['public_id'],
                'type'      => $user['type'],
                'role'      => $user['role'] 
            ];
        }
        $stmt->close();
    }
}

/* ================= 5. VIGILANTE DE ROTAÇÃO PARA ADMINS (1 HORA) ================= */
if (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/mailer.php';

    $admin_id = $_SESSION['auth']['user_id'];
    
    // Forçar UTC para sincronia
    date_default_timezone_set('UTC');
    
    $stmt_check = $mysqli->prepare("SELECT password_changed_at, email, nome FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $admin_id);
    $stmt_check->execute();
    $admin_info = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($admin_info) {
        $last_change = strtotime($admin_info['password_changed_at']);
        $seconds_passed = time() - $last_change;

        if ($seconds_passed >= 3600) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*";
            $new_pass = "";
            for ($i = 0; $i < 10; $i++) { 
                $new_pass .= $chars[random_int(0, strlen($chars) - 1)]; 
            }
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);

            $mysqli->begin_transaction();
            try {
                $php_now = date('Y-m-d H:i:s');
                $mysqli->query("UPDATE users SET password_hash = '$new_hash', password_changed_at = '$php_now' WHERE id = $admin_id");
                
                $ip = $_SERVER['REMOTE_ADDR'];
                $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ($admin_id, 'FORCE_ROTATION_EXPIRED', '$ip')");

                $assunto = "⚠️ Sessão Expirada - Nova Senha VisionGreen";
                $mensagem = "Olá " . $admin_info['nome'] . ", sua sessão administrativa expirou.<br>Nova senha: <b>$new_pass</b>";
                
                enviarEmailVisionGreen($admin_info['email'], $admin_info['nome'], $mensagem);

                $mysqli->commit();

                session_unset();
                session_destroy();
                header("Location: ../../registration/login/login.php?info=session_expired");
                exit;

            } catch (Exception $e) {
                $mysqli->rollback();
                error_log("Erro na rotação forçada de admin: " . $e->getMessage());
            }
        }
    }
}

/* ================= 6. CONTROLE DE ACESSO E PROTEÇÃO DE PASTAS ================= */

// Atualiza atividade
$_SESSION['last_activity'] = time();

/**
 * LÓGICA DE BLOQUEIO DE REDIRECIONAMENTO INDEVIDO:
 * Verifica em qual pasta o usuário está e se o seu 'type' e 'role' condizem com ela.
 */
$current_path = $_SERVER['PHP_SELF'];

if (isset($_SESSION['auth']['user_id'])) {
    $user_type = $_SESSION['auth']['type'];
    $user_role = $_SESSION['auth']['role'];

    // 1. TRAVA PARA ADMINS: Impede que entrem nas pastas de clientes (person/business)
    if (in_array($user_role, ['admin', 'superadmin'])) {
        if (strpos($current_path, '/pages/person/') !== false || strpos($current_path, '/pages/business/') !== false) {
            // Se o admin tentar acessar dashboards de usuários, ele é mandado para a área segura dele
            header("Location: ../../pages/admin/dashboard_admin.php");
            exit;
        }
    }

    // 2. TRAVA PARA EMPRESAS: Se um usuário 'company' tentar entrar na pasta '/person/'
    if (strpos($current_path, '/pages/person/') !== false && $user_type !== 'person') {
        header("Location: ../../pages/business/dashboard_business.php?error=unauthorized_path");
        exit;
    }

    // 3. TRAVA PARA PESSOAS: Se um usuário 'person' tentar entrar na pasta '/business/'
    if (strpos($current_path, '/pages/business/') !== false && $user_type !== 'company') {
        header("Location: ../../pages/person/dashboard_person.php?error=unauthorized_path");
        exit;
    }
}

/**
 * TRAVA PARA PÁGINAS ADMINISTRATIVAS
 */
if (defined('IS_ADMIN_PAGE') || (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'superadmin']))) {
    if(!defined('BYPASS_DOCUMENT_CHECK')) define('BYPASS_DOCUMENT_CHECK', true);
} else {
    /* --- Lógica para Usuários Comuns (Inatividade 30 min) --- */
    $timeout = 1800; 
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        header("Location: ../login/login.php?info=timeout");
        exit;
    }
}