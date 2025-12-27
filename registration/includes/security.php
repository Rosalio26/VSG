<?php

/* ================= GARANTE SESSÃO ================= */
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/* ================= NOVO: CONTROLE DE INATIVIDADE ================= */
// Define o tempo limite (ex: 30 minutos = 1800 segundos)
$timeout_duration = 1800;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    // Se exceder o tempo, limpa a sessão
    session_unset();
    session_destroy();
    
    // Redireciona para o login informando o timeout
    header("Location: ../login/login.php?info=timeout");
    exit;
}
// Atualiza o timestamp da última atividade
$_SESSION['last_activity'] = time();

/* ================= HEADERS DE SEGURANÇA ================= */
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (defined('APP_ENV') && APP_ENV === 'prod') {
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self'; " .
        "style-src 'self' 'unsafe-inline'; " . 
        "img-src 'self' data:;"
    );
}

/* ================= NOVO: SANITIZAÇÃO GLOBAL ================= */

/**
 * Limpa dados de entrada contra XSS e espaços desnecessários
 */
function cleanInput($data) 
{
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    // Remove tags HTML, espaços extras e converte caracteres especiais
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/* ================= NOVO: LÓGICA "LEMBRAR-ME" (AUTO-LOGIN) ================= */

// Se o usuário NÃO está logado mas possui o cookie de "Lembrar-me"
if (!isset($_SESSION['auth']) && isset($_COOKIE['remember_token'])) {
    // Inclui a conexão com o banco (ajuste o caminho se necessário)
    require_once __DIR__ . '/db.php';
    
    $token = $_COOKIE['remember_token'];
    $token_hash = hash('sha256', $token);
    
    // Busca o token no banco cruzando com a tabela users
    $stmt = $mysqli->prepare("
        SELECT u.id, u.nome, u.email, u.public_id, u.type 
        FROM users u 
        JOIN remember_me r ON u.id = r.user_id 
        WHERE r.token_hash = ? AND r.expires_at > NOW() 
        LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param('s', $token_hash);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            // Recria a sessão de autenticação automaticamente
            $_SESSION['auth'] = [
                'user_id'   => $user['id'],
                'nome'      => $user['nome'],
                'email'     => $user['email'],
                'public_id' => $user['public_id'],
                'type'      => $user['type']
            ];
            // Opcional: registrar novo log de auto-login aqui
        }
        $stmt->close();
    }
}

/* ================= CSRF HELPERS ================= */

/**
 * Gera um token CSRF se não existir
 */
function csrf_generate(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Retorna input hidden CSRF
 */
function csrf_field(): string
{
    $token = csrf_generate();
    return '<input type="hidden" name="csrf" value="' . 
           htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . 
           '">';
}

/**
 * Valida CSRF
 */
function csrf_validate(?string $token): bool
{
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}