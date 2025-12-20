<?php
// Ambiente
define('APP_ENV', 'dev'); // prod em produção

// Headers globais de segurança
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// CSP básica
header(
  "Content-Security-Policy: default-src 'self'; " .
  "script-src 'self'; " .
  "style-src 'self';"
);

// Cookies seguros
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict',
]);

session_start();

// CSRF token global
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
