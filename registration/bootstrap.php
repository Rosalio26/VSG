<?php
/* ===== BUFFER DE SAÍDA (Captura qualquer output acidental) ===== */
ob_start();

/* ===== CONFIGURAÇÃO DE AMBIENTE ===== */
if (!defined('APP_ENV')) {
    define('APP_ENV', 'dev'); // 'dev' ou 'prod'
}

/* ===== CONFIGURAÇÃO DE ERROS ===== */
if (APP_ENV === 'dev') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

/* ===== TIMEZONE GLOBAL (UTC para sincronização internacional) ===== */
date_default_timezone_set('UTC');

/* ===== INICIAR SESSÃO (Configuração Segura e Unificada) ===== */
if (session_status() === PHP_SESSION_NONE) {
    
    $cookieParams = [
        'lifetime' => 0, // Expira quando o navegador fecha
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax', 
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
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

/* ===== CSRF TOKEN GLOBAL ===== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ===== CARREGAR DEPENDÊNCIAS ESSENCIAIS ===== */
require_once __DIR__ . '/includes/device.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/../registration/includes/db.php';
// Sistema de Câmbio
require_once __DIR__ . '/../includes/currency/currency_bootstrap.php';