<?php
// header('X-Frame-Options: DENY');
// header('X-Content-Type-Options: nosniff');
// header('Referrer-Policy: same-origin');

// // CSP simples (não quebra JS inline atual)
// header(
//   "Content-Security-Policy: default-src 'self'; " .
//   "script-src 'self'; " .
//   "style-src 'self';"
// );

// // Deve rodar ANTES do session_start()

// session_set_cookie_params([
//     'lifetime' => 0,
//     'path' => '/',
//     'domain' => '',
//     'secure' => isset($_SERVER['HTTPS']), // true em HTTPS
//     'httponly' => true,
//     'samesite' => 'Strict',
// ]);

// session_start();

// // ================= CSRF TOKEN =================
// if (empty($_SESSION['csrf_token'])) {
//     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// }

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/device.php';

$device = detectDevice();

/**
 * Regras fortes:
 * - Android / iOS → sempre mobile
 * - Windows / Mac / Linux → desktop
 */
$isMobileReal =
    $device['os'] === 'android' ||
    $device['os'] === 'ios';

$isDesktopReal =
    in_array($device['os'], ['windows', 'mac', 'linux'], true);

// Sessão fingerprint (anti-tamper simples)
$fingerprint = hash(
    'sha256',
    $device['os'] .
    $device['browser'] .
    ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
);



if (!isset($_SESSION['fingerprint'])) {
    $_SESSION['fingerprint'] = $fingerprint;
}

if ($_SESSION['fingerprint'] !== $fingerprint) {
    http_response_code(403);
    exit('Sessão inválida.');
}

/* =================== REGRAS DE NEGÓCIO =================== */

if ($isMobileReal) {
    $_SESSION['tipos_permitidos'] = ['pessoal'];
    $_SESSION['tipo_atual'] = 'pessoal';
} elseif ($isDesktopReal) {
    $_SESSION['tipos_permitidos'] = ['business', 'pessoal'];
    $_SESSION['tipo_atual'] = $_SESSION['tipo_atual'] ?? 'business';
} else {
    http_response_code(403);
    exit('Dispositivo não suportado.');
}

/* =================== BLOQUEIO DE POST =================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';

    if (!in_array($tipo, $_SESSION['tipos_permitidos'], true)) {
        http_response_code(403);
        exit('Tipo de cadastro não permitido neste dispositivo.');
    }
}


/* ===== DEBUG TEMPORÁRIO ===== */
if (($_ENV['APP_ENV'] ?? 'prod') === 'dev') {
    file_put_contents(
        __DIR__ . '/../logs/device.log',
        date('Y-m-d H:i:s') . PHP_EOL .
        json_encode($device, JSON_PRETTY_PRINT) .
        PHP_EOL . str_repeat('-', 40) . PHP_EOL,
        FILE_APPEND
    );
}

/* ============================ */
