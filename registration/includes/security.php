<?php

/* ================= GARANTE SESSÃO ================= */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ================= HEADERS DE SEGURANÇA ================= */

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (defined('APP_ENV') && APP_ENV === 'prod') {
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self'; " .
        "style-src 'self';"
    );
}

/* ================= CSRF HELPERS ================= */

/**
 * Retorna input hidden CSRF
 */
function csrf_field(): string
{
    if (empty($_SESSION['csrf_token'])) {
        return '';
    }

    return '<input type="hidden" name="csrf" value="' .
           htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') .
           '">';
}

/**
 * Valida CSRF
 */
function csrf_validate(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
