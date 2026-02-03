<?php

/**
 * Security.php - Funções de Segurança Globais
 * NOTA: Sessão e headers já são configurados no bootstrap.php
 */

/* ================= FUNÇÕES CSRF (Usam sessão já iniciada) ================= */

/**
 * Gera token CSRF (se não existir)
 */
function csrf_generate(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Retorna campo hidden HTML para formulários
 */
function csrf_field(): string {
    $token = csrf_generate();
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Valida token CSRF
 */
function csrf_validate(?string $token): bool {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/* ================= SANITIZAÇÃO ================= */

/**
 * Limpa input contra XSS
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/* ================= AUTO-LOGIN (REMEMBER ME) ================= */

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

/* ================= VERIFICAÇÃO DE SENHA EXPIRADA (ADMIN) ================= */

if (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    require_once __DIR__ . '/db.php';

    $admin_id = $_SESSION['auth']['user_id'];
    $admin_role = $_SESSION['auth']['role'];
    
    // Busca dados do admin
    $stmt_check = $mysqli->prepare("SELECT password_changed_at, email, nome FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $admin_id);
    $stmt_check->execute();
    $admin_info = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($admin_info) {
        $last_change = strtotime($admin_info['password_changed_at']);
        $seconds_passed = time() - $last_change;
        $timeoutLimit = ($admin_role === 'superadmin') ? 3600 : 86400; // 1h ou 24h

        // APENAS AVISA, NÃO EXPULSA
        if ($seconds_passed >= $timeoutLimit) {
            $hours_expired = floor($seconds_passed / 3600);
            $minutes_expired = floor(($seconds_passed % 3600) / 60);
            
            $_SESSION['password_expired'] = true;
            $_SESSION['password_expired_since'] = $last_change;
            $_SESSION['password_expired_time'] = "{$hours_expired}h {$minutes_expired}m";
            $_SESSION['password_warning'] = '⚠️ SEGURANÇA: Sua senha expirou! Renove imediatamente no painel.';
            
            // Log de auditoria
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt_log = $mysqli->prepare("
                INSERT INTO admin_audit_logs (admin_id, action, ip_address, details) 
                VALUES (?, 'PASSWORD_EXPIRED_WARNING', ?, ?)
            ");
            $details = "Senha expirada há {$hours_expired}h {$minutes_expired}m - Admin continua navegando com aviso";
            if ($stmt_log) {
                $stmt_log->bind_param('iss', $admin_id, $ip, $details);
                $stmt_log->execute();
                $stmt_log->close();
            }
        } else {
            // Limpa avisos se a senha foi renovada
            unset($_SESSION['password_expired']);
            unset($_SESSION['password_expired_since']);
            unset($_SESSION['password_expired_time']);
            unset($_SESSION['password_warning']);
        }
    }
}

/* ================= CONTROLE DE ATIVIDADE E TIMEOUT ================= */

$current_time = time();
$_SESSION['last_activity'] = $current_time;

// Sincroniza atividade no banco
if (isset($_SESSION['auth']['user_id'])) {
    require_once __DIR__ . '/db.php';
    $u_id = (int)$_SESSION['auth']['user_id'];
    
    $stmt_activity = $mysqli->prepare("UPDATE users SET last_activity = ? WHERE id = ?");
    if ($stmt_activity) {
        $stmt_activity->bind_param('ii', $current_time, $u_id);
        $stmt_activity->execute();
        $stmt_activity->close();
    }
}

/* ================= PROTEÇÃO DE ROTAS (Type/Role Check) ================= */

$current_path = $_SERVER['PHP_SELF'];

if (isset($_SESSION['auth']['user_id'])) {
    $user_type = $_SESSION['auth']['type'];
    $user_role = $_SESSION['auth']['role'];

    // ADMINS não podem acessar áreas de clientes
    if (in_array($user_role, ['admin', 'superadmin'])) {
        if (strpos($current_path, '/pages/person/') !== false || strpos($current_path, '/pages/business/') !== false) {
            header("Location: ../../pages/admin/dashboard_admin.php");
            exit;
        }
    }

    // EMPRESAS não podem acessar área de pessoas
    if (strpos($current_path, '/pages/person/') !== false && $user_type !== 'person') {
        header("Location: ../../pages/business/dashboard_business.php?error=unauthorized_path");
        exit;
    }

    // PESSOAS não podem acessar área de empresas
    if (strpos($current_path, '/pages/business/') !== false && $user_type !== 'company') {
        header("Location: ../../pages/person/dashboard_person.php?error=unauthorized_path");
        exit;
    }
}

/* ================= TIMEOUT DE SESSÃO ================= */

if (defined('IS_ADMIN_PAGE') || (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'superadmin']))) {
    if(!defined('BYPASS_DOCUMENT_CHECK')) define('BYPASS_DOCUMENT_CHECK', true);
} else {
    // Timeout de 30 minutos para usuários comuns
    $timeout = 1800; 
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        header("Location: ../login/login.php?info=timeout");
        exit;
    }
}