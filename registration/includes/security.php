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

/* ================= 5. CONTROLE DE ACESSO E REDIRECIONAMENTO ================= */

// Atualiza atividade
$_SESSION['last_activity'] = time();

/**
 * TRAVA DE SEGURANÇA:
 * Se for Admin ou se a página se declarar Admin, paramos as verificações restritivas aqui,
 * mas mantemos as funções acima carregadas no escopo global.
 */
if (defined('IS_ADMIN_PAGE') || (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'superadmin']))) {
    if(!defined('BYPASS_DOCUMENT_CHECK')) define('BYPASS_DOCUMENT_CHECK', true);
    // Não usamos mais o 'return' para não matar as funções CSRF
} else {
    /* --- Lógica para Usuários Comuns (Person/Business) --- */
    
    // 1. Check de Inatividade (30 min)
    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        if (isset($_POST['csrf'])) { // Se for uma requisição POST, avisa que o token expirou
            die("Sessão expirada. Por favor, recarregue a página.");
        }
        header("Location: ../login/login.php?info=timeout");
        exit;
    }
}