<?php

define('APP_ENV', 'dev');

/* ===== INICIAR SESSÃO (UMA ÚNICA VEZ) ===== */
if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax', // 🔴 IMPORTANTE (explico abaixo)
    ]);

    session_start();
}

/* ===== CSRF GLOBAL ===== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
