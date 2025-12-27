<?php
/**
 * VisionGreen - Logout Seguro
 * Finaliza a sessão e invalida tokens persistentes (Remember Me)
 */

require_once '../includes/security.php';
require_once '../includes/db.php';

// 1. INVALIDAR O "LEMBRAR-ME" NO BANCO DE DADOS
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $token_hash = hash('sha256', $token);

    // Remove o token específico do banco de dados
    $stmt = $mysqli->prepare("DELETE FROM remember_me WHERE token_hash = ?");
    if ($stmt) {
        $stmt->bind_param('s', $token_hash);
        $stmt->execute();
        $stmt->close();
    }

    // 2. APAGAR O COOKIE NO NAVEGADOR
    // Definimos uma data no passado para forçar o navegador a deletar o cookie
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    ]);
}

// 3. DESTRUIR A SESSÃO PHP
$_SESSION = array(); // Limpa todas as variáveis de sessão

// Se desejar matar o cookie da sessão também (PHPSESSID)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// 4. REDIRECIONAR PARA A LANDING PAGE
header("Location: login.php?info=logged_out");
exit;