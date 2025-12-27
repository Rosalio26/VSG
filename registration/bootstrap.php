<?php

/* ===== CONFIGURAÇÃO DE AMBIENTE ===== */
// Pode ser 'dev' ou 'prod'
if (!defined('APP_ENV')) {
    define('APP_ENV', 'dev');
}

/* ===== CONFIGURAÇÃO DE ERROS (Baseado no Ambiente) ===== */
if (APP_ENV === 'dev') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

/* ===== INICIAR SESSÃO (Configuração Robusta) ===== */
if (session_status() === PHP_SESSION_NONE) {
    
    // PHP 7.3+ suporta o array de opções diretamente no session_set_cookie_params
    $cookieParams = [
        'lifetime' => 0, // Expira quando o navegador fecha
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax', 
    ];

    // Verifica se a versão do PHP suporta o array de opções (PHP 7.3+)
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        // Fallback para versões mais antigas do PHP
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }

    session_start();
}

/* ===== CSRF GLOBAL ===== */
/**
 * Garante que o token CSRF exista. 
 * Note que agora usamos a chave 'csrf_token' de forma consistente 
 * com o seu arquivo security.php.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ===== TIMEZONE ===== */
date_default_timezone_set('America/Sao_Paulo');